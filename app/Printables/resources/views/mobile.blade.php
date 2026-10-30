{{--
    The printables designer, phone. Same island as desktop, mounted in its
    mobile layout: top bar, the page full-screen (pinch to zoom), the selection's
    toolbar along the bottom and every panel as a bottom sheet. This is also the
    Android app's editor — the app is a WebView of this page.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $boot['dir'] }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    {{-- The editor is drawn light; "only" stops a browser or WebView from forcing a dark theme onto it. --}}
    <meta name="color-scheme" content="only light">
    <meta name="darkreader-lock">
    <style>html { color-scheme: only light; }</style>
    <meta name="theme-color" content="#6d5bd0">
    <title>{{ $title }} · {{ __('printables::messages.designer') }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    @vite(['resources/js/islands/designer.jsx'])
</head>
<body style="margin:0;overscroll-behavior:none">
    <div id="printables-designer" data-layout="mobile"></div>
    <script type="application/json" id="printables-boot">@json($boot + ['strings' => __('printables::messages.ui'), 'layout' => 'mobile'])</script>
</body>
</html>
