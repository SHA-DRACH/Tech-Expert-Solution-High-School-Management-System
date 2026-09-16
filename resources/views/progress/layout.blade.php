{{--
    A printed school document, not an application page: no sidebar, black
    ruled tables, one student per sheet of paper. The toolbar is the only part
    that never prints.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · {{ $school?->name }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #e5e7eb; font-family: "Times New Roman", Georgia, serif; color: #111; }
        .toolbar { position: sticky; top: 0; z-index: 5; display: flex; flex-wrap: wrap; gap: 8px; align-items: center; justify-content: space-between;
                   padding: 10px 16px; background: #0f172a; color: #fff; font-family: system-ui, sans-serif; font-size: 14px; }
        .toolbar a, .toolbar button { color: #fff; background: #1e40af; border: 0; border-radius: 6px; padding: 7px 14px; font: inherit; cursor: pointer; text-decoration: none; }
        .toolbar a.plain { background: transparent; text-decoration: underline; padding: 0; }
        .sheet { background: #fff; margin: 18px auto; padding: 14mm 12mm; box-shadow: 0 1px 6px rgba(0,0,0,.15); page-break-after: always; }
        .sheet:last-child { page-break-after: auto; }
        .school { display: flex; align-items: center; justify-content: space-between; gap: 10px; text-align: center; }
        .school .logo { width: 64px; height: 64px; border-radius: 50%; object-fit: cover; flex-shrink: 0; }
        .school .logo-text { width: 64px; height: 64px; border-radius: 50%; border: 2px solid #1e3a8a; display: grid; place-items: center;
                             font-weight: bold; color: #1e3a8a; flex-shrink: 0; }
        .school h1 { margin: 0; font-size: 20px; letter-spacing: .02em; text-transform: uppercase; }
        .school p { margin: 1px 0; font-size: 12px; }
        .doc-title { margin: 10px 0 8px; text-align: center; font-size: 16px; font-weight: bold; text-decoration: underline; text-transform: uppercase; }
        .fill { border-bottom: 1px solid #111; padding: 0 6px; font-family: "Segoe Script", "Brush Script MT", cursive; color: #1e3a8a; font-size: 15px; }
        table.grid { width: 100%; border-collapse: collapse; }
        table.grid th, table.grid td { border: 1px solid #111; padding: 3px 5px; font-size: 13px; height: 22px; }
        table.grid th { font-weight: bold; text-align: center; }
        table.grid td.num { text-align: center; font-family: "Segoe Script", "Brush Script MT", cursive; color: #1e3a8a; font-size: 14px; }
        table.grid td.label { font-weight: normal; white-space: nowrap; }
        table.grid tr.summary td.label { font-weight: bold; }
        .fail { color: #b91c1c !important; }
        .sign { margin-top: 18px; font-size: 13px; }
        .sign .line { display: inline-block; min-width: 180px; border-bottom: 1px solid #111; margin-left: 4px; }
        .sign small { display: block; text-align: center; font-weight: bold; }
        .verify { display: flex; align-items: center; gap: 8px; font-family: system-ui, sans-serif; font-size: 9px; color: #555; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { margin: 0; box-shadow: none; padding: 8mm; }
            @page { size: A4 portrait; margin: 8mm; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <span>{{ $title }}{{ $rows->count() > 1 ? ' · '.$rows->count().' students' : '' }}</span>
        <span style="display:flex;gap:10px;align-items:center">
            @if ($backUrl && $backUrl !== url()->current())
                <a class="plain" href="{{ $backUrl }}">Back</a>
            @endif
            <button type="button" onclick="window.print()">Print{{ $rows->count() > 1 ? ' all' : '' }}</button>
        </span>
    </div>

    @if ($rows->isEmpty())
        <div class="sheet"><p>No students in this class.</p></div>
    @endif

    @yield('sheets')
</body>
</html>
