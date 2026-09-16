@php
    use App\Models\Term;

    $periods = $year->terms->whereNotNull('semester')->sortBy('sequence');
    $missing = collect(range(1, Term::PERIODS_PER_YEAR))->diff($periods->pluck('sequence')->map(fn ($n) => (int) $n))->values();
@endphp

{{--
    The year's time frame, period by period. The name of a period comes from
    its number, so only the number (when adding) and the dates are edited.
--}}
@foreach ([1, 2] as $semesterNumber)
    @php
        $semester = $year->semesters->firstWhere('number', $semesterNumber);
        $inSemester = $periods->filter(fn ($p) => (int) $p->semester === $semesterNumber);
    @endphp

    <div class="border-b border-slate-100 bg-slate-50/70 px-5 py-2">
        <p class="flex flex-wrap items-center gap-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
            {{ App\Models\Semester::nameFor($semesterNumber) }}
            @if ($semester?->exam_starts_on)
                <span class="font-normal normal-case tracking-normal">
                    · exam {{ $semester->exam_starts_on->format('j M') }} – {{ $semester->exam_ends_on?->format('j M Y') }}
                </span>
            @endif
        </p>
    </div>

    <ul class="divide-y divide-slate-100">
        @forelse ($inSemester as $period)
            <li x-data="{ open: false }">
                <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-3.5">
                    <div class="min-w-0">
                        <p class="flex items-center gap-2 text-sm font-medium text-slate-800">
                            <span class="grid size-6 shrink-0 place-items-center rounded-md bg-slate-100 text-xs font-semibold text-slate-500">
                                {{ $period->sequence }}
                            </span>
                            {{ $period->name }}
                            @if ($period->is_current)
                                <x-ui.badge tone="success">Current period</x-ui.badge>
                            @endif
                        </p>
                        <p class="mt-0.5 pl-8 text-xs text-slate-500">
                            {{ $period->starts_on->format('j M Y') }} &ndash; {{ $period->ends_on->format('j M Y') }}
                            · {{ $period->starts_on->diffInWeeks($period->ends_on->copy()->addDay()) }} weeks
                        </p>
                    </div>

                    <div class="flex shrink-0 items-center gap-2">
                        @if (! $period->is_current && $year->is_current)
                            <form method="POST" action="{{ route('settings.terms.current', $period) }}">
                                @csrf
                                <x-ui.button type="submit" variant="secondary" size="sm">Make current</x-ui.button>
                            </form>
                        @endif
                        <x-ui.button size="sm" variant="ghost" x-on:click="open = ! open">Edit</x-ui.button>
                    </div>
                </div>

                <div x-show="open" x-cloak x-collapse class="bg-slate-50 px-5 py-4">
                    <form method="POST" action="{{ route('settings.terms.update', $period) }}" class="grid gap-4 sm:grid-cols-3">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="sequence" value="{{ $period->sequence }}">

                        <x-ui.field label="Starts on" name="starts_on" :id="'period-start-'.$period->id">
                            <x-ui.input name="starts_on" type="date" :id="'period-start-'.$period->id" :value="$period->starts_on->toDateString()" :remember="false" />
                        </x-ui.field>
                        <x-ui.field label="Ends on" name="ends_on" :id="'period-end-'.$period->id">
                            <x-ui.input name="ends_on" type="date" :id="'period-end-'.$period->id" :value="$period->ends_on->toDateString()" :remember="false" />
                        </x-ui.field>

                        <div class="flex items-end gap-2">
                            <x-ui.button type="submit" size="sm">Save dates</x-ui.button>
                            @unless ($period->is_current)
                                <x-ui.confirm
                                    :action="route('settings.terms.destroy', $period)"
                                    method="DELETE"
                                    title="Delete period"
                                    message="Delete the {{ strtolower($period->name) }}? This is only possible while it holds no marks, attendance or invoices."
                                    confirm="Delete period"
                                    >Delete</x-ui.confirm>
                            @endunless
                        </div>
                    </form>
                </div>
            </li>
        @empty
            <li class="px-5 py-3 text-xs text-slate-400">No periods in this semester yet.</li>
        @endforelse
    </ul>
@endforeach

{{-- Adding a period the year is missing --}}
<div class="border-t border-slate-100 px-5 py-3">
    @if ($missing->isEmpty())
        <p class="text-xs text-slate-500">
            All six periods are set.
            <a href="{{ route('periods.index', ['year' => $year->id]) }}" class="font-medium underline underline-offset-2">Exam dates and exam mark entry</a>
        </p>
    @else
        <x-ui.button size="sm" variant="ghost" x-on:click="adding = ! adding">
            <span aria-hidden="true">+</span> Add a period to {{ $year->name }}
        </x-ui.button>

        <div x-show="adding" x-cloak x-collapse class="pt-4">
            <form method="POST" action="{{ route('settings.terms.store', $year) }}" class="grid gap-4 sm:grid-cols-4">
                @csrf

                <x-ui.field label="Period" name="sequence" :id="'new-period-'.$year->id">
                    <select name="sequence" id="new-period-{{ $year->id }}"
                            class="block w-full rounded-lg border-0 py-2 pl-3 pr-9 text-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-brand">
                        @foreach ($missing as $number)
                            <option value="{{ $number }}">{{ ucfirst(Term::periodName($number)) }} ({{ strtolower(App\Models\Semester::nameFor(Term::semesterForPeriod($number))) }})</option>
                        @endforeach
                    </select>
                </x-ui.field>
                <x-ui.field label="Starts on" name="starts_on" :id="'new-period-start-'.$year->id">
                    <x-ui.input name="starts_on" type="date" :id="'new-period-start-'.$year->id" :remember="false" />
                </x-ui.field>
                <x-ui.field label="Ends on" name="ends_on" :id="'new-period-end-'.$year->id">
                    <x-ui.input name="ends_on" type="date" :id="'new-period-end-'.$year->id" :remember="false" />
                </x-ui.field>
                <div class="flex items-end">
                    <x-ui.button type="submit" size="sm">Add period</x-ui.button>
                </div>
            </form>
        </div>
    @endif
</div>
