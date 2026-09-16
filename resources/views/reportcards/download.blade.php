{{--
    The downloadable report card.

    A standalone document, so everything it needs is inside it: no stylesheet
    link, no script, no image from the server. A file that loses its layout the
    moment it is opened without an internet connection is not a document a
    family can keep, and "keep it" is the whole point of a download.

    Photographs are inlined as data URIs for the same reason. Where a file
    cannot be read the initials block is printed instead, so the card is always
    a finished document rather than one with a hole in it.
--}}
@php
    $inline = function (?string $path): ?string {
        if (! $path) {
            return null;
        }

        try {
            $disk = Storage::disk('public');

            if (! $disk->exists($path)) {
                return null;
            }

            return 'data:'.($disk->mimeType($path) ?: 'image/jpeg').';base64,'.base64_encode($disk->get($path));
        } catch (\Throwable) {
            // A missing or unreadable file must not break the download.
            return null;
        }
    };

    $logo = $inline($school?->logo_path);
    $photo = $inline($card->student?->photo_path);

    $marked = $card->items->filter(fn ($item) => $item->score !== null);
    $total = $marked->sum(fn ($item) => (float) $item->score);

    $trim = fn ($number) => rtrim(rtrim(number_format((float) $number, 2, '.', ''), '0'), '.');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $card->student?->full_name }} — {{ $card->term?->name ?? 'Full year' }} report card</title>

    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 24px;
            background: #f8fafc;
            color: #0f172a;
            font: 14px/1.5 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
        }
        .sheet { max-width: 820px; margin: 0 auto; background: #fff; padding: 32px; border: 1px solid #e2e8f0; border-radius: 10px; }
        header { display: flex; justify-content: space-between; gap: 16px; border-bottom: 2px solid #1e293b; padding-bottom: 18px; }
        .brand { display: flex; gap: 12px; align-items: center; }
        .brand img, .brand .mark { width: 60px; height: 60px; border-radius: 8px; object-fit: cover; }
        .mark { display: flex; align-items: center; justify-content: center; background: #1e3a8a; color: #fff; font-weight: 700; font-size: 20px; }
        .school-name { font-size: 19px; font-weight: 700; margin: 0; }
        .motto { font-size: 11px; font-style: italic; color: #64748b; margin: 2px 0 0; }
        .address { font-size: 11px; color: #64748b; margin: 2px 0 0; }
        .doc-type { text-align: right; font-size: 11px; text-transform: uppercase; letter-spacing: .1em; color: #64748b; margin: 0; }
        .identity { display: flex; gap: 20px; padding: 20px 0; align-items: flex-start; }
        .identity img, .identity .mark { width: 96px; height: 96px; border-radius: 8px; border: 1px solid #e2e8f0; object-fit: cover; }
        .identity .mark { background: #f1f5f9; color: #94a3b8; font-size: 24px; }
        .fields { flex: 1; display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px 20px; }
        dt { font-size: 10px; text-transform: uppercase; letter-spacing: .06em; color: #64748b; font-weight: 600; }
        dd { margin: 2px 0 0; font-size: 13px; font-weight: 500; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th, td { padding: 7px 10px; text-align: left; }
        thead tr { background: #f8fafc; border-top: 1px solid #cbd5e1; border-bottom: 1px solid #cbd5e1; }
        thead th { font-size: 10px; text-transform: uppercase; letter-spacing: .06em; color: #475569; }
        tbody tr { border-bottom: 1px solid #f1f5f9; }
        .num { font-variant-numeric: tabular-nums; }
        .total-row { border-top: 1px solid #cbd5e1; font-weight: 600; }
        .average-row { border-top: 2px solid #1e293b; font-weight: 700; }
        .panel { margin-top: 20px; background: #f8fafc; border-radius: 8px; padding: 14px; display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
        .comment { margin-top: 18px; }
        .comment h2 { font-size: 11px; text-transform: uppercase; letter-spacing: .06em; color: #64748b; margin: 0 0 4px; }
        .comment p { margin: 0; font-size: 13px; }
        .verify { display: flex; align-items: center; gap: 12px; margin-top: 28px; border-top: 1px solid #e2e8f0; padding-top: 14px; }
        .verify p { margin: 0; font-size: 11px; line-height: 1.6; color: #64748b; }
        .signatures { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; margin-top: 40px; }
        .signature { border-top: 1px solid #94a3b8; padding-top: 6px; font-size: 11px; color: #64748b; }
        footer { margin-top: 26px; border-top: 1px solid #e2e8f0; padding-top: 12px; font-size: 11px; color: #94a3b8; display: flex; justify-content: space-between; gap: 12px; }
        .note { max-width: 820px; margin: 0 auto 16px; font-size: 12px; color: #64748b; }
        @media print {
            body { background: #fff; padding: 0; }
            .sheet { border: 0; border-radius: 0; padding: 0; max-width: none; }
            .note { display: none; }
        }
    </style>
</head>
<body>
    {{-- Hidden when printed: guidance for the person who opened the file. --}}
    <p class="note">Saved copy — open it any time, or use your browser's Print option to print it or save it as a PDF.</p>

    <div class="sheet">
        <header>
            <div class="brand">
                @if ($logo)
                    <img src="{{ $logo }}" alt="">
                @else
                    <span class="mark">{{ $school?->initials() }}</span>
                @endif

                <div>
                    <p class="school-name">{{ $school?->name }}</p>
                    @if ($school?->motto)<p class="motto">{{ $school->motto }}</p>@endif
                    @if ($school?->address)<p class="address">{{ $school->address }}</p>@endif
                </div>
            </div>

            <div>
                <p class="doc-type">Report card</p>
                <p style="margin:2px 0 0;font-weight:500;">{{ $card->term?->name ?? 'Full year' }}</p>
                <p class="address">{{ $card->academicYear?->name }}</p>
            </div>
        </header>

        <div class="identity">
            @if ($photo)
                <img src="{{ $photo }}" alt="{{ $card->student?->full_name }}">
            @else
                <span class="mark">{{ Str::substr($card->student?->first_name ?? '', 0, 1) }}{{ Str::substr($card->student?->last_name ?? '', 0, 1) }}</span>
            @endif

            <dl class="fields">
                <div><dt>Student</dt><dd>{{ $card->student?->full_name ?: '—' }}</dd></div>
                <div><dt>Student ID</dt><dd>{{ $card->student?->student_number ?: '—' }}</dd></div>
                <div><dt>Class</dt><dd>{{ $card->section?->full_name ?: '—' }}</dd></div>
                <div><dt>Academic year</dt><dd>{{ $card->academicYear?->name ?: '—' }}</dd></div>
                <div><dt>Period</dt><dd>{{ $card->term?->name ?? 'Full year' }}</dd></div>

                @if ($settings['reportcard_show_position'])
                    <div><dt>Position</dt><dd>{{ $card->position ? $card->position.' of '.$card->class_size : '—' }}</dd></div>
                @endif
            </dl>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Subject</th><th>Score</th><th>Grade</th><th>Position</th><th>Remark</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($card->items as $item)
                    <tr>
                        <td>{{ $item->subject?->name }}</td>
                        <td class="num">{{ $trim($item->score) }}%</td>
                        <td><strong>{{ $item->grade ?? '—' }}</strong></td>
                        <td class="num">{{ $item->position ?? '—' }}</td>
                        <td>{{ $item->remark ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" style="text-align:center;color:#64748b;padding:24px 0;">No approved subject results for this period.</td></tr>
                @endforelse
            </tbody>

            <tfoot>
                @if ($marked->isNotEmpty())
                    <tr class="total-row">
                        <th>Total ({{ $marked->count() }} {{ Str::plural('subject', $marked->count()) }})</th>
                        <td colspan="4" class="num">
                            {{ $trim($total) }} <span style="font-weight:400;color:#64748b;">out of {{ $marked->count() * 100 }}</span>
                        </td>
                    </tr>
                @endif

                <tr class="average-row">
                    <th>Overall average</th>
                    <td colspan="4" class="num">{{ $card->average }}%</td>
                </tr>
            </tfoot>
        </table>

        @if ($settings['reportcard_show_attendance'])
            <div class="panel">
                <div><dt>Days present</dt><dd>{{ $card->days_present ?? '—' }}</dd></div>
                <div><dt>Days recorded</dt><dd>{{ $card->days_total ?? '—' }}</dd></div>
                <div><dt>Attendance</dt><dd>{{ $card->attendancePercentage() === null ? '—' : $card->attendancePercentage().'%' }}</dd></div>
            </div>
        @endif

        @if (filled($card->teacher_comment))
            <div class="comment">
                <h2>Class teacher's comment</h2>
                <p>{{ $card->teacher_comment }}</p>
            </div>
        @endif

        @if (filled($card->principal_comment))
            <div class="comment">
                <h2>Principal's comment</h2>
                <p>{{ $card->principal_comment }}</p>
            </div>
        @endif

        @if (filled($settings['reportcard_remark']))
            <p style="margin-top:14px;font-size:11px;color:#64748b;white-space:pre-line;">{{ $settings['reportcard_remark'] }}</p>
        @endif

        <div class="signatures">
            <div class="signature">Class teacher{{ $card->section?->classTeacher ? ' — '.$card->section->classTeacher->full_name : '' }}</div>
            <div class="signature">Principal</div>
            <div class="signature">Parent / guardian</div>
        </div>

        {{--
            The same verification code the on-screen card carries. It matters
            more here, if anything: this file is emailed on and printed by
            people who never touched the system, and the code is the only thing
            in it that cannot simply be retyped.
        --}}
        @if (! empty($verifyCode))
            <div class="verify">
                <div>{!! $verifyCode !!}</div>
                <p>Scan to verify this report card against {{ $school?->name }}'s records.<br>
                   The check confirms the card was issued; it does not show marks.</p>
            </div>
        @endif

        <footer>
            <span>
                {{ $card->status === 'published' ? 'Published' : Str::headline($card->status) }}
                {{ $card->published_at ? $card->published_at->format('j F Y') : '' }}
            </span>
            <span>Downloaded {{ now()->format('j F Y, H:i') }}</span>
        </footer>
    </div>
</body>
</html>
