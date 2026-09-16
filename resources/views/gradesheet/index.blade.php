@php
    use App\Models\Semester;
    use App\Services\PeriodGrades;

    $isExam = $sheet instanceof Semester;
    $query = ['section' => $section?->id, 'subject' => $subject?->id, 'sheet' => $sheetKey];
    $anyOpen = $locks->filter(fn ($reason) => $reason === null)->isNotEmpty();
@endphp

<x-layouts.app title="Grade sheet" heading="Grade sheet">
    <x-ui.breadcrumbs :trail="['Overview' => route('portal'), 'Grade sheet' => null]" />

    <x-ui.page-header
        title="Grade sheet"
        description="Choose a class, a subject and a period. Every student is listed; type their marks and save.">
        <x-slot:actions>
            @if ($section && $subject)
                <x-ui.button :href="route('gradesheet.summary', ['section' => $section->id, 'subject' => $subject->id])" variant="secondary">
                    Whole year
                </x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Choosing the sheet --}}
    <x-ui.card class="mb-6" :padded="false">
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

            <div class="w-60">
                <label for="sheet" class="mb-1 block text-xs font-medium text-slate-600">Period or exam</label>
                <select name="sheet" id="sheet"
                        class="block w-full cursor-pointer rounded-lg border-0 py-2 pl-3 pr-9 text-sm text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-brand">
                    @foreach ($semesters->isNotEmpty() ? $semesters : collect([1 => null, 2 => null]) as $number => $semester)
                        <optgroup label="{{ Semester::nameFor($number) }}">
                            @foreach ($periods->filter(fn ($p) => (int) $p->semester === $number) as $periodNo => $period)
                                <option value="period:{{ $period->id }}" @selected($sheetKey === 'period:'.$period->id)>
                                    {{ ucfirst(App\Models\Term::periodName($periodNo)) }}
                                    @if ($period->starts_on) ({{ $period->starts_on->format('j M') }} – {{ $period->ends_on?->format('j M') }}) @endif
                                </option>
                            @endforeach
                            @if ($semester)
                                <option value="exam:{{ $semester->id }}" @selected($sheetKey === 'exam:'.$semester->id)>
                                    {{ $semester->name }} exam {{ $semester->exam_entry_open ? '· open' : '· closed' }}
                                </option>
                            @endif
                        </optgroup>
                    @endforeach
                </select>
            </div>

            <x-ui.button type="submit" variant="secondary">Show</x-ui.button>
        </form>
    </x-ui.card>

    @if ($periods->isEmpty())
        <x-ui.card>
            <x-ui.empty-state icon="▤" title="This year is not set up in periods yet"
                              description="Marks are entered per period - six periods in two semesters. The academic office sets that up once for the year.">
                @can('academics.manage')
                    <x-ui.button :href="route('periods.index')">Set up periods</x-ui.button>
                @endcan
            </x-ui.empty-state>
        </x-ui.card>
    @elseif ($sections->isEmpty())
        <x-ui.card>
            <x-ui.empty-state icon="⌘" title="You have no classes to mark"
                              description="A teacher can only mark a class and subject they are assigned to. Ask the academic office to assign your teaching." />
        </x-ui.card>
    @elseif ($subjectsUnavailable)
        <x-ui.card>
            <x-ui.empty-state icon="◈" title="No subjects for {{ $section->full_name }}"
                              description="Either this class has no subjects attached yet, or you are not assigned to teach any of them." />
        </x-ui.card>
    @elseif (! $ready)
        <x-ui.card>
            <x-ui.empty-state icon="◇" title="Choose a period" description="Pick the period or semester exam to enter marks for." />
        </x-ui.card>
    @else
        {{-- What this sheet is, and whether it is open --}}
        <div class="mb-4 flex flex-wrap items-center gap-3 text-sm">
            <span class="font-display text-lg font-semibold text-slate-900">
                {{ $section->full_name }} · {{ $subject->name }} ·
                {{ $isExam ? $sheet->name.' exam' : ucfirst($sheet->label()) }}
            </span>

            @if ($isExam)
                @if ($sheet->exam_entry_open)
                    <x-ui.badge tone="success">Exam entry open</x-ui.badge>
                @else
                    <x-ui.badge tone="warning">Exam entry closed</x-ui.badge>
                @endif
                @if ($sheet->exam_starts_on)
                    <span class="text-slate-500">Exam {{ $sheet->exam_starts_on->format('j M') }} – {{ $sheet->exam_ends_on?->format('j M Y') }}</span>
                @endif
            @elseif ($sheet->starts_on)
                <span class="text-slate-500">{{ $sheet->starts_on->format('j M') }} – {{ $sheet->ends_on?->format('j M Y') }}</span>
            @endif
        </div>

        @error('marks')
            <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                <p class="font-medium">Nothing was saved.</p>
                <ul class="mt-1 list-disc space-y-0.5 pl-5">
                    @foreach ($errors->get('marks') as $messages)
                        @foreach ((array) $messages as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    @endforeach
                </ul>
            </div>
        @enderror

        {{-- The Excel round trip --}}
        @if ($canExport || $canImport)
            <x-ui.card class="mb-6" title="Excel"
                       :description="'One file for '.$section->full_name.', with a worksheet for each subject you mark. Fill in the shaded columns and upload it back here.'">
                <div class="flex flex-wrap items-end gap-4">
                    @if ($canExport)
                        <x-ui.button :href="route('gradesheet.download', ['section' => $section->id, 'sheet' => $sheetKey])" variant="secondary">
                            Download Excel file
                        </x-ui.button>
                    @endif

                    @if ($canImport)
                        <form method="POST" action="{{ route('gradesheet.upload', ['section' => $section->id, 'subject' => $subject->id, 'sheet' => $sheetKey]) }}"
                              enctype="multipart/form-data" class="flex flex-wrap items-end gap-2">
                            @csrf
                            <div>
                                <label for="file" class="mb-1 block text-xs font-medium text-slate-600">Upload filled-in file (.xlsx)</label>
                                <input type="file" name="file" id="file" accept=".xlsx" required
                                       class="block text-sm text-slate-700 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium hover:file:bg-slate-200">
                            </div>
                            <x-ui.button type="submit">Upload marks</x-ui.button>
                        </form>
                    @endif
                </div>

                @error('file')
                    <div class="mt-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                        <p class="font-medium">{{ $message }}</p>
                        @if (session('uploadProblems'))
                            <ul class="mt-1 list-disc space-y-0.5 pl-5">
                                @foreach (session('uploadProblems') as $problem)
                                    <li>{{ $problem }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @enderror
            </x-ui.card>
        @endif

        @if ($students->isEmpty())
            <x-ui.card>
                <x-ui.empty-state icon="◉" title="No students take {{ $subject->name }} in {{ $section->full_name }}" />
            </x-ui.card>
        @else
            <form method="POST" action="{{ route('gradesheet.store', $query) }}">
                @csrf

                <x-ui.card :padded="false"
                           :title="$students->count().' '.Str::plural('student', $students->count())"
                           :description="$isExam
                               ? 'Semester exam marks. The semester average appears once all three periods and the exam are in.'
                               : 'The period grade is worked out as you save. It stays provisional until the academic office approves the marks.'">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-max border-collapse text-sm">
                            <thead>
                                <tr class="border-b border-slate-200 bg-slate-50 text-left">
                                    <th scope="col" class="px-4 py-2.5 text-xs font-semibold uppercase tracking-wide text-slate-600">#</th>
                                    <th scope="col" class="sticky left-0 z-10 bg-slate-50 px-4 py-2.5 text-xs font-semibold uppercase tracking-wide text-slate-600">Student</th>

                                    @foreach ($columns as $type => $column)
                                        <th scope="col" class="px-3 py-2.5 text-center text-xs font-semibold text-slate-600">
                                            <span class="block">{{ $column['label'] }}</span>
                                            <span class="block font-normal text-slate-400">out of {{ $assessments->get($type)?->max_score ?? $column['max'] }}</span>
                                            @if ($locks[$type])
                                                {{-- The reason, not just a padlock. --}}
                                                <span class="mt-1 block max-w-40 whitespace-normal font-normal text-amber-700">{{ $locks[$type] }}</span>
                                            @endif
                                        </th>
                                    @endforeach

                                    <th scope="col" class="px-3 py-2.5 text-center text-xs font-semibold uppercase tracking-wide text-slate-600">
                                        {{ $isExam ? 'Semester average' : 'Period grade' }}
                                    </th>
                                </tr>
                            </thead>

                            <tbody>
                                @foreach ($students as $index => $student)
                                    @php
                                        $row = $provisional->get($student->id);
                                        $figure = $isExam
                                            ? ($row['semesters'][$sheet->number] ?? null)
                                            : ($row['periods'][$periodNumber] ?? null);
                                    @endphp

                                    <tr class="border-b border-slate-100 hover:bg-slate-50/60">
                                        <td class="px-4 py-2 text-xs tabular-nums text-slate-400">{{ $index + 1 }}</td>
                                        <th scope="row" class="sticky left-0 z-10 bg-white px-4 py-2 text-left font-normal">
                                            <span class="block font-medium text-slate-900">{{ $student->full_name }}</span>
                                            <span class="block font-mono text-xs text-slate-500">{{ $student->student_number }}</span>
                                        </th>

                                        @foreach ($columns as $type => $column)
                                            @php
                                                $value = old("marks.{$type}.{$student->id}", $marks->get($type.'.'.$student->id)?->score);
                                                $max = $assessments->get($type)?->max_score ?? $column['max'];
                                            @endphp
                                            <td class="px-3 py-2 text-center">
                                                @if ($locks[$type] === null)
                                                    <input type="number" step="0.01" min="0" max="{{ $max }}"
                                                           name="marks[{{ $type }}][{{ $student->id }}]"
                                                           value="{{ $value !== null ? rtrim(rtrim((string) $value, '0'), '.') : '' }}"
                                                           aria-label="{{ $column['label'] }} for {{ $student->full_name }}"
                                                           class="w-20 rounded-md border-0 py-1.5 text-center text-sm tabular-nums shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-brand">
                                                @else
                                                    <span class="tabular-nums text-slate-700">{{ PeriodGrades::format($value !== null ? (float) $value : null) }}</span>
                                                @endif
                                            </td>
                                        @endforeach

                                        <td class="px-3 py-2 text-center font-semibold tabular-nums text-slate-900">
                                            {{ PeriodGrades::format($figure) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if ($otherWork->isNotEmpty())
                        <p class="border-t border-slate-100 bg-amber-50/60 px-5 py-3 text-xs text-amber-800">
                            The period grade also counts other work recorded in this period:
                            {{ $otherWork->pluck('title')->join(', ') }}.
                            It is managed from <a href="{{ route('marks.index', ['section' => $section->id, 'subject' => $subject->id, 'term' => $sheet->id]) }}" class="font-medium underline underline-offset-2">the mark sheet</a>.
                        </p>
                    @endif

                    @if ($anyOpen)
                        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-5 py-4">
                            <p class="text-xs text-slate-500">A blank box means no mark - it is not the same as zero.</p>
                            <x-ui.button type="submit">Save marks</x-ui.button>
                        </div>
                    @endif
                </x-ui.card>
            </form>

            @if ($canSubmit && $canMark)
                {{-- A separate form: a form cannot sit inside another. --}}
                <form method="POST" action="{{ route('gradesheet.submit', $query) }}" class="mt-4 flex justify-end"
                      onsubmit="return confirm('Send these marks to the academic office? You will not be able to change them unless they are sent back.')">
                    @csrf
                    <x-ui.button type="submit" variant="secondary">Submit for approval</x-ui.button>
                </form>
            @endif
        @endif
    @endif
</x-layouts.app>
