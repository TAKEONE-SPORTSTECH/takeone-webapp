{{--
    The scoring table's REACT shell (the tablet at the mat's edge) — Phase M3,
    behind `features.react_scoreboard`.

    A separate document from its desktop twin for the reason the Blade pair are
    separate: they are different instruments, not one layout at two widths
    (CLAUDE.md → Mobile / Desktop Separation). They share one entry and one
    behaviour module; only the layout component differs, chosen from `layout` in
    the props below.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8">
    {{-- Dark by design (a scoreboard console / wall board). Declared so a
         browser's auto-dark and Dark Reader both leave it alone — the same
         reasoning as the light pages, opposite value. --}}
    <meta name="color-scheme" content="dark">
    <meta name="darkreader-lock">
    <style>html { color-scheme: dark; }</style>

<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ __('scoreboard::bjj_messages.ctl_title') }} · {{ $court }}</title>

@include('scoreboard::bjj.scoreboard.partials.console-mobile-styles')
</head>
<body>

@php
    $islandProps = \App\Scoreboard\Sports\BrazilianJiuJitsu\IslandProps::console(
        $event, $court, 'mobile', $state, $log, $queue, $screens,
        ['command' => $commandUrl, 'heartbeat' => $heartbeatUrl ?? null],
    );
@endphp

{{-- ⚠️ `height:100%` is load-bearing, not decoration. This layout is built out
     of percentages against the viewport — `#corners` is
     `calc(100% - 56px - 168px)` — and a mount point that does not carry the
     body's height breaks that chain: the corners collapse to their content and
     the fixed foot rides 200px up the screen, leaving a black band under it.
     The Blade document has these elements as direct children of <body>, so it
     never had a link to break. Measured, not guessed. --}}
<div id="scoreboard-console-island" style="height:100%"
     data-island-props="{{ json_encode($islandProps) }}"></div>

@vite(['resources/js/islands/scoreboard-console.jsx'])

@if (! empty($screenLink))
@include('scoreboard::bjj.partials.screen-link')
@endif
</body>
</html>
