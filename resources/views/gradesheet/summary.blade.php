@php
    use App\Models\Term;
    use App\Services\PeriodGrades;

    $passMark = app(App\Services\Gradebook::class)->passMark();
    $cell = fn (?float $value) => $value === null ? 'text-slate-400' : ($value < $passMark ? 'text-rose-700' : 'text-slate-900');
@endphp

<x-layouts.app title="Year grade sheet" heading="Year grade sheet">
    <x-ui.breadcrumbs :trail="['Overview' => route('portal'), 'Grade sheet' => route('gradesheet.index'), 'Whole year' => null]" />

    <x-ui.page-header title="Whole year"
                      description="Six periods, both semester exams, the semester averages and the yearly average, for every student.">
        <x-slot:actions>
            @if ($canMark && $section && $subject)
                <x-ui.button :href="route('gradesheet.index', ['section' => $section->id, 'subject' => $subject->id])" variant="secondary">
                    Enter marks
                </x-ui.button>
            @endif
            <x-ui.button onclick="window.print()" variant="secondary">Print</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card class="mb-6 print:hidden" :padded="false">
        <form method="GET" x-data x-on:change="$el.requestSubmit()" class="flex flex-wrap items-end gap-3 px-5 py-4">
            <div class="w-52">
                <label for="section" class="mb-1 block text-xs font-medium text-slate-600">Class</label>
                <x-ui.select name="section" :selected="$section?->id"
                             :options="$sections->mapWithKeys(fn ($s) => [$s->id => $s->full_name])->all()" />
            </div>
            <div class="w-52">
                <label for="subject" class="mb-1 block text-xs font-medium text-slate-600">Subject</label>
                <x-ui.select name="subject" :selected="$subject?->id"
                             :options="$subjects->mapWithKeys(fn ($s) => [$s->id => $s->name])->all()" />
            </div>

            @if ($canMark)
                {{-- Official by default for anyone who cannot mark; the people
                     entering marks may look at their working copy. --}}
                <label class="flex items-center gap-2 pb-2 text-sm text-slate-700">
                    <input type="checkbox" name="official" value="1" @checked($official)
                           class="rounded border-slate-300 text-brand focus:ring-brand">
                    Approved marks only
                </label>
            @endif

            <x-ui.button type="submit" variant="secondary">Show</x-ui.button>
        </form>
    </x-ui.card>

    @if ($periods->isEmpty())
        <x-ui.card>
            <x-ui.empty-state icon="▤" title="This year is not set up in periods yet" />
        </x-ui.card>
    @elseif ($rows->isEmpty())
        <x-ui.card>
            <x-ui.empty-state icon="◉" title="No students to show" />
        </x-ui.card>
    @else
        <div class="printable">
            <x-ui.card :padded="false"
                       :title="$section->full_name.' · '.$subject->name.' · '.$year->name"
                       :description="$official
                           ? 'Approved marks only - the official record.'
                           : 'Includes marks not yet approved. These figures are provisional.'">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-max border-collapse text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 bg-slate-50 text-center text-xs font-semibold text-slate-600">
                                <th rowspan="2" class="sticky left-0 z-10 bg-slate-50 px-4 py-2 text-left uppercase tracking-wide">Student</th>
                                <th colspan="5" class="border-l border-slate-200 px-2 py-1.5">First semester</th>
                                <th colspan="5" class="border-l border-slate-200 px-2 py-1.5">Second semester</th>
                                <th rowspan="2" class="border-l border-slate-200 px-3 py-2">Yearly<br>average</th>
                            </tr>
                            <tr class="border-b border-slate-200 bg-slate-50 text-center text-[11px] font-medium text-slate-500">
                                @foreach ([1, 2] as $semester)
                                    @foreach ($periods->filter(fn ($p) => (int) $p->semester === $semester)->keys() as $number)
                                        <th @class(['px-2 py-1.5', 'border-l border-slate-200' => $loop->first])>{{ Term::periodName($number) }}</th>
                                    @endforeach
                                    <th class="px-2 py-1.5">Exam</th>
                                    <th class="px-2 py-1.5 font-semibold text-slate-700">Average</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr class="border-b border-slate-100">
                                    <th scope="row" class="sticky left-0 z-10 bg-white px-4 py-2 text-left font-normal">
                                        <span class="block font-medium text-slate-900">{{ $row['student']->full_name }}</span>
                                        <span class="block font-mono text-xs text-slate-500">{{ $row['student']->student_number }}</span>
                                    </th>

                                    @foreach ([1, 2] as $semester)
                                        @foreach ($periods->filter(fn ($p) => (int) $p->semester === $semester)->keys() as $number)
                                            <td @class(['px-2 py-2 text-center tabular-nums', $cell($row['periods'][$number] ?? null), 'border-l border-slate-100' => $loop->first])>
                                                {{ PeriodGrades::format($row['periods'][$number] ?? null) }}
                                            </td>
                                        @endforeach
                                        <td @class(['px-2 py-2 text-center tabular-nums', $cell($row['exams'][$semester])])>
                                            {{ PeriodGrades::format($row['exams'][$semester]) }}
                                        </td>
                                        <td @class(['bg-slate-50 px-2 py-2 text-center font-semibold tabular-nums', $cell($row['semesters'][$semester])])>
                                            {{ PeriodGrades::format($row['semesters'][$semester]) }}
                                        </td>
                                    @endforeach

                                    <td @class(['border-l border-slate-200 px-3 py-2 text-center font-display font-bold tabular-nums', $cell($row['yearly'])])>
                                        {{ PeriodGrades::format($row['yearly']) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <p class="border-t border-slate-100 px-5 py-3 text-xs text-slate-500">
                    Semester average = (three period grades + semester exam) ÷ 4. Yearly average = (first + second semester) ÷ 2.
                    An average stays blank until every part of it is in. Below {{ $passMark }} is shown in red.
                </p>
            </x-ui.card>
        </div>
    @endif
</x-layouts.app>
