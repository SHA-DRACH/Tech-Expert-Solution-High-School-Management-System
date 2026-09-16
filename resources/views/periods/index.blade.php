@php use App\Models\Term; @endphp

<x-layouts.app title="Periods & semesters" heading="Periods & semesters">
    <x-ui.breadcrumbs :trail="['Overview' => route('dashboard'), 'Periods & semesters' => null]" />

    <x-ui.page-header title="Periods & semesters"
                      description="Six marking periods in two semesters, each semester closing with an exam. Set each period's dates, and open exam marks to teachers once the exam has been given.">
        <x-slot:actions>
            @if ($years->count() > 1)
                <form method="GET" x-data x-on:change="$el.requestSubmit()">
                    <x-ui.select name="year" :selected="$year?->id" :options="$years->pluck('name', 'id')->all()" />
                </form>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    @error('periods')
        <div class="mb-6 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ $message }}</div>
    @enderror

    @if ($year === null)
        <x-ui.card>
            <x-ui.empty-state icon="▤" title="No academic year yet" description="Create the academic year first.">
                @if ($canManage)
                    <x-ui.button :href="route('settings.years.index')">Academic years</x-ui.button>
                @endif
            </x-ui.empty-state>
        </x-ui.card>
    @elseif ($periods->count() < Term::PERIODS_PER_YEAR)
        <x-ui.card :title="$year->name.' is not in periods yet'">
            <div class="space-y-4 text-sm text-slate-700">
                @if ($plainTerms->isNotEmpty())
                    <p>
                        This year has {{ $plainTerms->count() }} {{ Str::plural('term', $plainTerms->count()) }}
                        ({{ $plainTerms->pluck('name')->join(', ') }}). Setting up periods turns them into the first
                        periods, in order, so every mark already recorded against them stays exactly where it is.
                        The remaining periods are added.
                    </p>
                @else
                    <p>Setting up creates the 1st to 6th periods and the first and second semesters.</p>
                @endif

                <p class="text-slate-500">
                    Dates are spread evenly across {{ $year->starts_on->format('j M Y') }} – {{ $year->ends_on->format('j M Y') }}
                    to start with. Change each period to the dates the school actually keeps.
                </p>

                @if ($canManage)
                    <form method="POST" action="{{ route('periods.setup', $year) }}"
                          onsubmit="return confirm('Organise {{ $year->name }} into six periods and two semesters?')">
                        @csrf
                        <x-ui.button type="submit">Set up six periods</x-ui.button>
                    </form>
                @else
                    <p class="text-amber-700">Ask someone who manages the academic calendar to set this up.</p>
                @endif
            </div>
        </x-ui.card>
    @else
        <div class="grid gap-6 lg:grid-cols-2">
            @foreach ($semesters as $semester)
                <x-ui.card :padded="false">
                    <x-slot:title>
                        <span class="flex flex-wrap items-center gap-2">
                            {{ $semester->name }}
                            @if ($semester->exam_entry_open)
                                <x-ui.badge tone="success">Exam entry open</x-ui.badge>
                            @else
                                <x-ui.badge>Exam entry closed</x-ui.badge>
                            @endif
                        </span>
                    </x-slot:title>

                    {{-- The three periods --}}
                    @foreach ($periods->filter(fn ($p) => (int) $p->semester === $semester->number) as $number => $period)
                        <div x-data="{ editing: false }" class="border-b border-slate-100 px-5 py-3">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <p class="text-sm font-medium text-slate-900">
                                        {{ ucfirst(Term::periodName($loop->iteration + ($semester->number - 1) * 3)) }}
                                        @if ($period->is_current)
                                            <x-ui.badge tone="info" class="ml-1">Current</x-ui.badge>
                                        @endif
                                    </p>
                                    <p class="text-xs text-slate-500">
                                        {{ $period->starts_on?->format('j M Y') ?? 'No start date' }} –
                                        {{ $period->ends_on?->format('j M Y') ?? 'no end date' }}
                                        @if ($period->starts_on && $period->ends_on)
                                            · {{ $period->starts_on->diffInWeeks($period->ends_on->copy()->addDay()) }} weeks
                                        @endif
                                    </p>
                                </div>

                                @if ($canManage)
                                    <div class="flex items-center gap-2">
                                        @if (! $period->is_current && $year->is_current)
                                            <form method="POST" action="{{ route('settings.terms.current', $period) }}">
                                                @csrf
                                                <x-ui.button type="submit" size="sm" variant="ghost">Make current</x-ui.button>
                                            </form>
                                        @endif
                                        <x-ui.button size="sm" variant="ghost" x-on:click="editing = ! editing">Dates</x-ui.button>
                                    </div>
                                @endif
                            </div>

                            @if ($canManage)
                                <form x-show="editing" x-cloak method="POST" action="{{ route('periods.update', $period) }}"
                                      class="mt-3 flex flex-wrap items-end gap-2">
                                    @csrf
                                    @method('PUT')
                                    <div>
                                        <label class="mb-1 block text-xs text-slate-600" for="starts_{{ $period->id }}">Starts</label>
                                        <input type="date" id="starts_{{ $period->id }}" name="starts_on" value="{{ $period->starts_on?->toDateString() }}" required
                                               class="rounded-lg border-0 py-1.5 text-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-brand">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs text-slate-600" for="ends_{{ $period->id }}">Ends</label>
                                        <input type="date" id="ends_{{ $period->id }}" name="ends_on" value="{{ $period->ends_on?->toDateString() }}" required
                                               class="rounded-lg border-0 py-1.5 text-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-brand">
                                    </div>
                                    <x-ui.button type="submit" size="sm">Save</x-ui.button>
                                </form>
                            @endif
                        </div>
                    @endforeach

                    {{-- The exam --}}
                    <div class="space-y-3 bg-slate-50 px-5 py-4">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-medium text-slate-900">{{ $semester->name }} exam</p>
                                <p class="text-xs text-slate-500">
                                    {{ $semester->exam_starts_on?->format('j M Y') ?? 'Dates not set' }}
                                    @if ($semester->exam_ends_on) – {{ $semester->exam_ends_on->format('j M Y') }} @endif
                                </p>
                                @if ($semester->exam_entry_opened_at)
                                    <p class="text-xs text-slate-400">
                                        Last opened {{ $semester->exam_entry_opened_at->format('j M Y, g:i A') }}
                                        {{ $semester->openedBy ? 'by '.$semester->openedBy->name : '' }}
                                    </p>
                                @endif
                            </div>

                            @if ($canOpenExams)
                                <form method="POST" action="{{ route('semesters.exam-entry', $semester) }}"
                                      onsubmit="return confirm('{{ $semester->exam_entry_open ? 'Close exam mark entry? Marks already entered are kept.' : 'Open exam mark entry? Every teacher will be told they can enter '.strtolower($semester->name).' exam marks.' }}')">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="open" value="{{ $semester->exam_entry_open ? 0 : 1 }}">
                                    <x-ui.button type="submit" :variant="$semester->exam_entry_open ? 'secondary' : 'primary'">
                                        {{ $semester->exam_entry_open ? 'Close exam entry' : 'Open exam entry' }}
                                    </x-ui.button>
                                </form>
                            @endif
                        </div>

                        @if ($canManage)
                            <form method="POST" action="{{ route('semesters.update', $semester) }}" class="flex flex-wrap items-end gap-2">
                                @csrf
                                @method('PUT')
                                <div>
                                    <label class="mb-1 block text-xs text-slate-600" for="exam_start_{{ $semester->id }}">Exam starts</label>
                                    <input type="date" id="exam_start_{{ $semester->id }}" name="exam_starts_on" value="{{ $semester->exam_starts_on?->toDateString() }}"
                                           class="rounded-lg border-0 py-1.5 text-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-brand">
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs text-slate-600" for="exam_end_{{ $semester->id }}">Exam ends</label>
                                    <input type="date" id="exam_end_{{ $semester->id }}" name="exam_ends_on" value="{{ $semester->exam_ends_on?->toDateString() }}"
                                           class="rounded-lg border-0 py-1.5 text-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-brand">
                                </div>
                                <x-ui.button type="submit" size="sm" variant="secondary">Save exam dates</x-ui.button>
                            </form>
                        @endif
                    </div>
                </x-ui.card>
            @endforeach
        </div>

        <p class="mt-4 text-xs text-slate-500">
            What each part of a period grade is marked out of - period test, quiz, assignment, attendance - is set in
            @can('settings.manage')
                <a href="{{ route('settings.group.edit', 'academics') }}" class="underline underline-offset-2">Settings › Academics</a>.
            @else
                Settings › Academics.
            @endcan
        </p>
    @endif
</x-layouts.app>
