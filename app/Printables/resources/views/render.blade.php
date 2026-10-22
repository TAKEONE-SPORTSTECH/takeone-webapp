{{--
    The page headless Chromium prints (App\Printables\Printer). Self-contained:
    the renderer and its stylesheet as local files, the design and this
    recipient's values in the boot block, pictures as data: URIs. The paper is
    the design's own size; nothing around it.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="color-scheme" content="light">
    @foreach ($styles as $href)
        <link rel="stylesheet" href="{{ $href }}">
    @endforeach
    <style>
        @page { size: {{ (float) $widthMm }}mm {{ (float) $heightMm }}mm; margin: 0; }
        html, body { margin: 0; padding: 0; background: #fff; color-scheme: only light; }
        .dzp { padding: 0 !important; gap: 0 !important; align-items: flex-start !important; }
        .dzp-sheet { box-shadow: none !important; break-after: page; page-break-after: always; }
        .dzp-sheet:last-child { break-after: auto; page-break-after: auto; }
        * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    </style>
    <script type="module" src="{{ $script }}"></script>
</head>
<body>
    <div id="printables-print"></div>
    <script type="application/json" id="printables-boot">@json($boot)</script>
</body>
</html>
