@php
    use App\Services\PeriodGrades;
    use App\Models\Semester;

    $stage = trim((string) $report['section']->schoolClass?->stage);
    $title = trim(($stage !== '' ? $stage.' ' : '').'Grade Sheet');
    $passMark = app(App\Services\Gradebook::class)->passMark();

    $heading = fn (string $column) => match (true) {
        str_starts_with($column, 'p') => ['1ST', '2ND', '3RD', '4TH', '5TH', '6TH'][(int) substr($column, 1) - 1].' PD',
        str_starts_with($column, 'e') => 'EXAM',
        default => 'AVE.',
    };
    $fmt = fn (?float $v) => $v === null ? '' : PeriodGrades::format($v);
@endphp

@extends('progress.layout', ['title' => $title.' · '.ucfirst($period->label()), 'rows' => $rows, 'backUrl' => $backUrl, 'school' => $school])

@section('sheets')
    @foreach ($rows as $row)
        @php $student = $row['student']; @endphp

        {{-- A narrow slip, as schools print it: it is handed to the child. --}}
        <div class="sheet" style="max-width: 125mm">
            @include('progress.partials.school-header', ['school' => $school])

            <div class="doc-title" style="margin-top:8px">{{ $title }}</div>

            <p style="margin:4px 0;font-size:13px">Name: <span class="fill">{{ $student->full_name }}</span></p>
            <p style="margin:4px 0;font-size:13px">
                Class: <span class="fill">{{ $report['section']->full_name }}</span>
                &nbsp; Date: <span class="fill">{{ now()->format('d-m-y') }}</span>
            </p>

            <table class="grid" style="margin-top:8px">
                <thead>
                    <tr>
                        <th colspan="{{ count($columns) + 1 }}">{{ ucwords(Semester::nameFor($semesterNumber)) }} Grade Sheet</th>
                    </tr>
                    <tr>
                        <th style="text-align:left">Subjects</th>
                        @foreach ($columns as $column)
                            <th style="width:48px">{{ $heading($column) }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    {{-- Every subject the class offers, as on the printed form. --}}
                    @foreach ($report['subjects'] as $subject)
                        <tr>
                            <td class="label">{{ $subject->name }}</td>
                            @foreach ($columns as $column)
                                @php $value = $row['grades'][$subject->id][$column] ?? null; @endphp
                                <td @class(['num', 'fail' => $value !== null && $value < $passMark])>{{ $fmt($value) }}</td>
                            @endforeach
                        </tr>
                    @endforeach

                    <tr class="summary">
                        <td class="label">Average (%)</td>
                        @foreach ($columns as $column)
                            <td class="num">{{ $fmt($row['averages'][$column] ?? null) }}</td>
                        @endforeach
                    </tr>
                    <tr class="summary">
                        <td class="label">Class Rank</td>
                        @foreach ($columns as $column)
                            <td class="num">
                                @if (isset($row['ranks'][$column])){{ $row['ranks'][$column] }}<span style="font-size:10px">/{{ $report['classSize'] }}</span>@endif
                            </td>
                        @endforeach
                    </tr>
                    <tr class="summary">
                        <td class="label">Day(s) Present</td>
                        @foreach ($columns as $column)
                            <td class="num">{{ str_starts_with($column, 'p') ? ($row['attendance'][(int) substr($column, 1)]['present'] ?? '') : '' }}</td>
                        @endforeach
                    </tr>
                    <tr class="summary">
                        <td class="label">Day(s) Absent</td>
                        @foreach ($columns as $column)
                            <td class="num">{{ str_starts_with($column, 'p') ? ($row['attendance'][(int) substr($column, 1)]['absent'] ?? '') : '' }}</td>
                        @endforeach
                    </tr>
                    <tr class="summary">
                        <td class="label">Conduct</td>
                        @foreach ($columns as $column)
                            <td class="num">{{ str_starts_with($column, 'p') ? ($row['conduct'][(int) substr($column, 1)] ?? '') : '' }}</td>
                        @endforeach
                    </tr>
                </tbody>
            </table>

            <div class="sign">
                <p>Signed:<span class="line">&nbsp;</span>
                    <small>Class Sponsor{{ $report['section']->classTeacher ? ' — '.$report['section']->classTeacher->full_name : '' }}</small></p>
                <p style="margin-top:14px">Approve:<span class="line">&nbsp;</span><small>Principal</small></p>
            </div>

            @if (! empty($codes[$student->id]))
                <div class="verify" style="margin-top:10px">
                    <div>{!! $codes[$student->id]['qr'] !!}</div>
                    <span>
                        Verification code: <strong class="doc-code">{{ $codes[$student->id]['code'] }}</strong><br>
                        Check this grade sheet at {{ route('online.index') }} or scan the code.
                    </span>
                </div>
            @endif
        </div>
    @endforeach
@endsection
