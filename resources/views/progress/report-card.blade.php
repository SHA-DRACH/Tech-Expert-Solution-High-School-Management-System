@php
    use App\Services\PeriodGrades;

    $stage = trim((string) $report['section']->schoolClass?->stage);
    $title = trim(($stage !== '' ? $stage.' Division ' : '').'Periodic Progress Report');
    $fmt = fn (?float $v) => $v === null ? '' : PeriodGrades::format($v);

    // The template's column order: three periods, exam, average - twice - then the year.
    $headings = [
        'p1' => '1st', 'p2' => '2nd', 'p3' => '3rd', 'e1' => 'Exam', 's1' => 'Ave.',
        'p4' => '4th', 'p5' => '5th', 'p6' => '6th', 'e2' => 'Exam', 's2' => 'Ave.',
        'y' => 'Yrly',
    ];
@endphp

@extends('progress.layout', ['title' => $title, 'rows' => $rows, 'backUrl' => $backUrl, 'school' => $school])

@section('sheets')
    @foreach ($rows as $row)
        @php $student = $row['student']; @endphp

        <div class="sheet" style="max-width: 200mm">
            @include('progress.partials.school-header', ['school' => $school])

            <div class="doc-title">{{ $title }}</div>

            <p style="margin:4px 0;font-size:13px">
                Student's Name: <span class="fill">{{ $student->last_name }}, {{ trim($student->first_name.' '.($student->middle_name ? mb_substr($student->middle_name, 0, 1).'.' : '')) }}</span>
                &nbsp; Grade: <span class="fill">{{ $report['section']->full_name }}</span>
                &nbsp; Year: <span class="fill">{{ $report['year']->name }}</span>
            </p>

            <table class="grid" style="margin-top:6px">
                <thead>
                    <tr>
                        <th style="text-align:left;width:26%">Subject</th>
                        @foreach ($headings as $column => $label)
                            <th @style(['background:#f3f4f6' => in_array($column, ['s1', 's2', 'y'], true)])>{{ $label }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    {{-- Every subject the class offers, as on the printed form. A
                         subject with no approved grade yet is simply blank. --}}
                    @foreach ($report['subjects'] as $subject)
                        <tr>
                            <td class="label">{{ $subject->name }}</td>
                            @foreach ($headings as $column => $label)
                                @php $value = $row['grades'][$subject->id][$column] ?? null; @endphp
                                <td @class(['num', 'fail' => $value !== null && $value < $passMark])>{{ $fmt($value) }}</td>
                            @endforeach
                        </tr>
                    @endforeach

                    <tr class="summary">
                        <td class="label">Average</td>
                        @foreach ($headings as $column => $label)
                            @php $value = $row['averages'][$column] ?? null; @endphp
                            <td @class(['num', 'fail' => $value !== null && $value < $passMark])>{{ $fmt($value) }}</td>
                        @endforeach
                    </tr>
                    <tr class="summary">
                        <td class="label">No. of Students</td>
                        @foreach ($headings as $column => $label)
                            {{-- Printed where there is a ranking to read it against. --}}
                            <td class="num">{{ isset($row['ranks'][$column]) ? $report['classSize'] : '' }}</td>
                        @endforeach
                    </tr>
                    <tr class="summary">
                        <td class="label">Rank</td>
                        @foreach ($headings as $column => $label)
                            <td class="num">{{ $row['ranks'][$column] ?? '' }}</td>
                        @endforeach
                    </tr>
                </tbody>
            </table>

            <div style="display:flex;justify-content:space-between;margin-top:10px;font-weight:bold;font-size:13px">
                <span>100 Means Perfect</span>
                <span>Below {{ $passMark }} is failure and Requires attention</span>
            </div>

            <div class="sign" style="display:flex;justify-content:space-between;gap:16px;margin-top:20px">
                <p>Signed:<span class="line">&nbsp;</span><small>Parent/Guardian</small></p>
                <p>Signed:<span class="line">&nbsp;</span>
                    <small>Sponsor{{ $report['section']->classTeacher ? ' — '.$report['section']->classTeacher->full_name : '' }}</small></p>
            </div>

            <div class="sign" style="margin-top:10px">
                <p>Approved:
                    <span style="display:inline-block;vertical-align:top">
                        <span class="line" style="min-width:240px;margin-left:0">&nbsp;</span>
                        <small>Registrar</small>
                    </span>
                </p>
            </div>

            @if ($school?->motto)
                <p style="text-align:center;font-weight:bold;font-size:15px;margin:14px 0 4px">Motto: “{{ $school->motto }}”</p>
            @endif

            @if (filled($note))
                <p style="font-size:17px;margin:10px 0 0">Important Note: ({{ $note }})</p>
            @endif

            @if (! empty($codes[$student->id]))
                <div class="verify" style="margin-top:10px;justify-content:flex-end">
                    <span>Scan to verify this report with {{ $school?->name }}. Only approved marks are printed.</span>
                    <div>{!! $codes[$student->id] !!}</div>
                </div>
            @endif
        </div>
    @endforeach
@endsection
