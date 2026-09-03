<x-layouts.app :title="'Upload marks · '.$assessment->title" heading="Upload marks">
    <x-ui.breadcrumbs :trail="[
        'Overview' => route('dashboard'),
        'Assessments' => route('assessments.index'),
        $assessment->title => route('assessments.scores', $assessment),
        'Upload marks' => null,
    ]" />

    <x-ui.page-header
        :title="'Upload marks for '.$assessment->title"
        :description="$assessment->section?->full_name.' · '.$assessment->subject?->name.' · out of '.$assessment->max_score"
    />

    {{--
        Said before the upload, not after. Uploading is the point of no return
        for a teacher, and burying that in a confirmation dialog would be a
        trap rather than a warning.
    --}}
    <div class="enter-rise mb-6 rounded-xl border border-amber-200 bg-amber-50 px-5 py-4">
        <p class="text-sm font-semibold text-amber-900">Uploading locks these marks.</p>
        <p class="mt-1 text-sm text-amber-800">
            When you confirm, the marks are recorded and sent to the academic office for approval, and you will
            not be able to change them. If something is wrong afterwards, ask the office to reject the
            assessment — that returns it to you to correct.
        </p>
    </div>

    <div class="grid gap-6 lg:grid-cols-[1fr_20rem]">
        <div class="min-w-0 space-y-6">
            <x-ui.card title="Choose a file" description="A CSV file with a student_number column and a score column.">
                <form method="POST" action="{{ route('assessments.marks.preview', $assessment) }}" enctype="multipart/form-data">
                    @csrf

                    <x-ui.field label="CSV file" name="file">
                        <input type="file" name="file" id="file" accept=".csv,text/csv"
                               class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200">
                    </x-ui.field>

                    <div class="mt-5 flex flex-wrap items-center gap-2">
                        <x-ui.button type="submit">Check the file</x-ui.button>
                        <x-ui.button :href="route('assessments.marks.template', $assessment)" variant="secondary">
                            Download mark sheet
                        </x-ui.button>
                    </div>
                </form>
            </x-ui.card>

            @if ($preview && (int) ($preview['assessment_id'] ?? 0) === $assessment->id)
                @php
                    $valid = collect($preview['valid'] ?? []);
                    $problems = collect($preview['problems'] ?? []);
                @endphp

                <x-ui.card
                    title="What would be recorded"
                    :description="$valid->count().' '.Str::plural('mark', $valid->count()).' ready, '.$problems->count().' '.Str::plural('problem', $problems->count()).'.'"
                >
                    @if ($valid->isNotEmpty())
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-max border-collapse text-sm">
                                <thead>
                                    <tr class="border-b border-slate-200 text-left">
                                        <th scope="col" class="py-2 pr-4 text-xs font-semibold uppercase tracking-wide text-slate-500">Line</th>
                                        <th scope="col" class="py-2 pr-4 text-xs font-semibold uppercase tracking-wide text-slate-500">Student</th>
                                        <th scope="col" class="py-2 pr-4 text-xs font-semibold uppercase tracking-wide text-slate-500">Number</th>
                                        <th scope="col" class="py-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Mark</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($valid->take(20) as $row)
                                        <tr class="border-b border-slate-100">
                                            <td class="py-2 pr-4 tabular-nums text-slate-400">{{ $row['__line'] }}</td>
                                            <td class="py-2 pr-4 font-medium text-slate-900">{{ $row['student_name'] }}</td>
                                            <td class="py-2 pr-4 text-slate-500">{{ $row['student_number'] }}</td>
                                            <td class="py-2 tabular-nums text-slate-700">
                                                {{ rtrim(rtrim(number_format($row['score'], 2), '0'), '.') }} / {{ $assessment->max_score }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        @if ($valid->count() > 20)
                            <p class="mt-3 text-xs text-slate-500">and {{ $valid->count() - 20 }} more.</p>
                        @endif

                        <form method="POST" action="{{ route('assessments.marks.store', $assessment) }}"
                              class="mt-6 border-t border-slate-100 pt-5">
                            @csrf
                            <x-ui.button type="submit">
                                Record {{ $valid->count() }} {{ Str::plural('mark', $valid->count()) }} and submit for approval
                            </x-ui.button>
                        </form>
                    @else
                        <p class="text-sm text-slate-500">Nothing in that file can be recorded. The problems are listed below.</p>
                    @endif
                </x-ui.card>

                @if ($problems->isNotEmpty())
                    <x-ui.card
                        title="Rows that would be skipped"
                        description="Fix these in your spreadsheet and upload again. Everything else can still be recorded now."
                    >
                        <ul class="divide-y divide-slate-100 text-sm">
                            @foreach ($problems as $problem)
                                <li class="flex flex-wrap items-baseline gap-x-3 gap-y-1 py-2.5">
                                    <span class="tabular-nums text-xs font-semibold text-slate-400">Line {{ $problem['line'] }}</span>
                                    <span class="font-medium text-slate-800">{{ $problem['reference'] }}</span>
                                    <span class="text-rose-700">{{ $problem['reason'] }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </x-ui.card>
                @endif
            @endif
        </div>

        <div class="space-y-5">
            <x-ui.card title="How marks are matched">
                <p class="text-sm text-slate-600">
                    Marks are matched to students by <span class="font-mono text-xs">student_number</span>, never by
                    name. Two children in one class can share a name, and they must never share a mark.
                </p>

                <p class="mt-3 text-sm text-slate-600">
                    A blank score is treated as <em>not marked yet</em> and skipped, not recorded as zero. A child who
                    has not sat the paper should not be failed by a spreadsheet.
                </p>

                <p class="mt-4 text-xs font-semibold uppercase tracking-wide text-slate-500">Columns</p>
                <div class="mt-2 flex flex-wrap gap-1.5">
                    <span class="rounded-md bg-brand/8 px-2 py-1 font-mono text-xs text-brand">student_number</span>
                    <span class="rounded-md bg-brand/8 px-2 py-1 font-mono text-xs text-brand">score</span>
                    <span class="rounded-md bg-slate-100 px-2 py-1 font-mono text-xs text-slate-600">remark</span>
                </div>
            </x-ui.card>

            <x-ui.card :title="'This class ('.$students->count().')'">
                <ul class="max-h-80 space-y-1.5 overflow-y-auto text-sm">
                    @foreach ($students as $student)
                        <li class="flex items-center justify-between gap-2">
                            <span class="truncate text-slate-800">{{ $student->full_name }}</span>
                            <span class="shrink-0 font-mono text-xs text-slate-400">{{ $student->student_number }}</span>
                        </li>
                    @endforeach
                </ul>
            </x-ui.card>
        </div>
    </div>
</x-layouts.app>
