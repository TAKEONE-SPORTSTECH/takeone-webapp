{{--
    A design at its real size for the browser's Print / Save as PDF. The paper
    size is set by @page from the design itself, so A4 prints A4 and a badge
    prints a badge. Drawn by the editor's own renderer.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    {{-- The editor is drawn light; "only" stops a browser or WebView from forcing a dark theme onto it. --}}
    <meta name="color-scheme" content="only light">
    <meta name="darkreader-lock">
    <style>html { color-scheme: only light; }</style>
    <title>{{ $title }}</title>
    @vite(['resources/js/islands/printables-print.jsx'])
    <style>
        @page { size: {{ (float) $widthMm }}mm {{ (float) $heightMm }}mm; margin: 0; }
        html, body { margin: 0; background: #e5e7eb; }
        .dzp-bar { position: sticky; top: 0; z-index: 5; display: flex; justify-content: center; gap: 10px; padding: 10px; background: rgba(255,255,255,.92); backdrop-filter: blur(6px); box-shadow: 0 1px 0 #e2e8f0; font: 600 14px Inter, system-ui, sans-serif; }
        .dzp-bar button { height: 40px; padding: 0 18px; border-radius: 10px; border: 0; background: #6d5bd0; color: #fff; font: inherit; cursor: pointer; }
        .dzp-bar span { align-self: center; color: #475569; }
        @media print { html, body { background: #fff; } .dzp-bar { display: none; } }
    </style>
</head>
<body>
    <div class="dzp-bar">
        <span>{{ $title }} · {{ rtrim(rtrim(number_format((float) $widthMm, 1), '0'), '.') }} × {{ rtrim(rtrim(number_format((float) $heightMm, 1), '0'), '.') }} mm</span>
        <button type="button" onclick="window.print()">{{ __('printables::messages.ui.print_now') }}</button>
    </div>
    <div id="printables-print"></div>
    <script type="application/json" id="printables-boot">@json($boot)</script>
</body>
</html>
