@php
    use App\Models\DocumentCode;
    use App\Services\PeriodGrades;

    $headings = [
        'p1' => '1st', 'p2' => '2nd', 'p3' => '3rd', 'e1' => 'Exam', 's1' => 'Ave.',
        'p4' => '4th', 'p5' => '5th', 'p6' => '6th', 'e2' => 'Exam', 's2' => 'Ave.',
        'y' => 'Yrly',
    ];
    $fmt = fn (?float $v) => $v === null ? '—' : PeriodGrades::format($v);
@endphp

<x-layouts.public :school="$school" title="Verify a document" :social-links="$socialLinks">
    <section class="section">
        <div class="mx-auto max-w-4xl px-4 sm:px-6">
            <nav aria-label="Breadcrumb">
                <ol class="flex items-center gap-2 text-xs text-slate-500">
                    <li><a href="{{ route('online.index') }}" class="link-underline">Online services</a></li>
                    <li aria-hidden="true">/</li>
                    <li class="font-medium text-slate-700" aria-current="page">Verify a document</li>
                </ol>
            </nav>

            <p class="mt-4 font-mono text-sm tracking-wider text-slate-500">Code {{ $code }}</p>

            @if ($document === null)
                <div class="mt-4 rounded-2xl border border-rose-200 bg-rose-50 p-6">
                    <h1 class="font-display text-2xl font-bold text-rose-900">No document matches this code</h1>
                    <p class="mt-2 text-sm text-rose-800">
                        Check the code against the bottom of the paper - letters and numbers only, for example GS-7K3P-X9QA.
                        If it still does not match, the document was not issued by {{ $school->name }}, or should be
                        confirmed with the school office.
                    </p>
                    <div class="mt-5"><x-ui.button :href="route('online.index').'#verify-document'" variant="secondary">Try another code</x-ui.button></div>
                </div>
            @else
                @php
                    $record = $document['record'];
                    $row = $document['row'];
                    $isGradeSheet = $record->type === DocumentCode::GRADE_SHEET;
                @endphp

                <div class="mt-4 rounded-2xl border border-emerald-200 bg-emerald-50 p-6">
                    <p class="inline-flex rounded-full bg-emerald-600 px-3 py-1 text-xs font-semibold text-white">✓ Genuine document</p>
                    <h1 class="mt-3 font-display text-2xl font-bold text-emerald-950">
                        {{ $isGradeSheet ? 'Grade sheet' : 'Periodic progress report' }} issued by {{ $school->name }}
                    </h1>

                    <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-4">
                        @foreach ([
                            'Student' => $record->student->full_name,
                            'Student ID' => $record->student->student_number,
                            'Class' => $document['section']->full_name,
                            $isGradeSheet ? 'Period' : 'Academic year' => $isGradeSheet
                                ? ucfirst($record->term?->label() ?? '').' · '.$record->academicYear->name
                                : $record->academicYear->name,
                        ] as $label => $value)
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wide text-emerald-800/70">{{ $label }}</dt>
                                <dd class="mt-0.5 font-medium text-emerald-950">{{ $value }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>

                @if ($row)
                    <div class="mt-6 overflow-x-auto rounded-2xl border border-slate-200 bg-white">
                        <table class="w-full min-w-max text-sm">
                            <caption class="px-5 pt-4 text-left text-sm text-slate-600">
                                Compare these with the paper. They are the grades the school holds now, so a figure corrected
                                after the paper was printed will show here as the corrected one.
                            </caption>
                            <thead>
                                <tr class="border-b border-slate-200 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    <th class="px-5 py-3 text-left">Subject</th>
                                    @foreach ($document['columns'] as $column)
                                        <th class="px-3 py-3 text-center">{{ $headings[$column] }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($document['report']['subjects'] as $subject)
                                    <tr class="border-b border-slate-100">
                                        <td class="px-5 py-2 text-slate-800">{{ $subject->name }}</td>
                                        @foreach ($document['columns'] as $column)
                                            @php $value = $row['grades'][$subject->id][$column] ?? null; @endphp
                                            <td @class(['px-3 py-2 text-center tabular-nums', 'text-rose-700' => $value !== null && $value < $passMark])>{{ $fmt($value) }}</td>
                                        @endforeach
                                    </tr>
                                @endforeach
                                <tr class="border-t-2 border-slate-200 font-semibold">
                                    <td class="px-5 py-2">Average</td>
                                    @foreach ($document['columns'] as $column)
                                        <td class="px-3 py-2 text-center tabular-nums">{{ $fmt($row['averages'][$column] ?? null) }}</td>
                                    @endforeach
                                </tr>
                                <tr>
                                    <td class="px-5 py-2 text-slate-600">Rank</td>
                                    @foreach ($document['columns'] as $column)
                                        <td class="px-3 py-2 text-center tabular-nums text-slate-600">
                                            {{ isset($row['ranks'][$column]) ? $row['ranks'][$column].' of '.$document['report']['classSize'] : '—' }}
                                        </td>
                                    @endforeach
                                </tr>
                            </tbody>
                        </table>
                    </div>
                @endif

                <p class="mt-4 text-xs text-slate-500">
                    First printed {{ $record->created_at?->format('j F Y') }}. Questions about a document? Contact the school office.
                </p>
            @endif
        </div>
    </section>
</x-layouts.public>
