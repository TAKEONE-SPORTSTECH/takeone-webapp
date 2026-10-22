{{--
    The printables designer, desktop. A full-screen editor with its own header,
    outside the app shell (like the bout review screens): the React island
    (resources/js/islands/designer.jsx) draws everything from the boot block.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $boot['dir'] }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    {{-- The editor is drawn light; "only" stops a browser or WebView from forcing a dark theme onto it. --}}
    <meta name="color-scheme" content="light">
    <meta name="darkreader-lock">
    <style>html { color-scheme: only light; }</style>
    <title>{{ $title }} · {{ __('printables::messages.designer') }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    @vite(['resources/js/islands/designer.jsx'])
</head>
<body style="margin:0">
    <div id="printables-designer" data-layout="desktop"></div>
    <script type="application/json" id="printables-boot">@json($boot + ['strings' => __('printables::messages.ui'), 'layout' => 'desktop'])</script>
</body>
</html>
