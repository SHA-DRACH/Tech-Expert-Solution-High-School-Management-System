@php
    // Deliberately not the signed-in app shell: whoever scanned this is
    // usually holding a piece of paper and has no account here.
    $found = $document !== null;
@endphp
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verify document · {{ $school->name }}</title>
    <meta name="robots" content="noindex, nofollow">

    <style>
        :root { --brand: {{ $school->primary_color ?? '#1d4ed8' }}; color-scheme: light; }
        * { box-sizing: border-box; }
        body { margin: 0; padding: 24px; background: #f8fafc; color: #0f172a;
               font: 15px/1.55 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; }
        .card { max-width: 460px; margin: 40px auto; background: #fff; border: 1px solid #e2e8f0;
                border-radius: 14px; padding: 28px; }
        .brand { display: flex; align-items: center; gap: 10px; margin-bottom: 20px; }
        .mark { width: 40px; height: 40px; border-radius: 9px; background: var(--brand); color: #fff;
                display: flex; align-items: center; justify-content: center; font-weight: 700; }
        h1 { font-size: 17px; margin: 0 0 2px; }
        .sub { font-size: 12px; color: #64748b; margin: 0; }
        .status { display: inline-flex; align-items: center; gap: 6px; border-radius: 999px;
                  padding: 5px 12px; font-size: 13px; font-weight: 600; margin: 4px 0 18px; }
        .ok { background: #ecfdf5; color: #047857; }
        .bad { background: #fef2f2; color: #b91c1c; }
        dl { margin: 0; display: grid; grid-template-columns: 1fr 1fr; gap: 12px 16px; }
        dt { font-size: 11px; text-transform: uppercase; letter-spacing: .05em; color: #64748b; font-weight: 600; }
        dd { margin: 2px 0 0; font-size: 14px; font-weight: 500; }
        .note { margin-top: 18px; padding-top: 14px; border-top: 1px solid #f1f5f9;
                font-size: 12px; color: #64748b; }
    </style>
</head>
<body>
    <div class="card">
        <div class="brand">
            @if ($school->logo_path)
                <img src="{{ Storage::disk('public')->url($school->logo_path) }}" alt="" class="mark" style="object-fit:cover;">
            @else
                <span class="mark">{{ $school->initials() }}</span>
            @endif

            <div>
                <h1>{{ $school->name }}</h1>
                <p class="sub">Document verification</p>
            </div>
        </div>

        @if ($found)
            <span class="status ok">✓ Genuine document</span>

            <p class="sub" style="margin-bottom:14px;">{{ $document['heading'] }} issued by this school.</p>

            <dl>
                @foreach ($document['rows'] as $label => $value)
                    <div>
                        <dt>{{ $label }}</dt>
                        <dd>{{ $value ?: '—' }}</dd>
                    </div>
                @endforeach
            </dl>

            @if (! empty($document['note']))
                <p class="note">{{ $document['note'] }}</p>
            @endif

            <p class="note">
                Names are shortened on this page on purpose. Check them against the document you are holding.
            </p>
        @else
            <span class="status bad">✕ Not found</span>

            <p class="sub">
                No document with the reference <strong>{{ $reference }}</strong> was issued by this school.
                Check the reference, or contact the school office.
            </p>
        @endif
    </div>
</body>
</html>
