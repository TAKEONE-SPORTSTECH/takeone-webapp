{{--
    The scoring table's REACT shell (desktop) — Phase M3, behind
    `features.react_scoreboard`.

    Same shell rules as the board's: the same <head>, the shared stylesheet, one
    empty div, and the island entry. The behaviour that used to live in
    scoreboard/runtime.blade.php is resources/js/islands/scoreboard/Console.jsx
    plus console-state.js, and the Blade original next door is untouched.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<style>
  html { -webkit-text-size-adjust:100%; text-size-adjust:100%; touch-action:pan-x pan-y; }
  body { touch-action:pan-x pan-y; }
</style>
<script>
(function () {
  'use strict';
  ['gesturestart', 'gesturechange', 'gestureend'].forEach(function (e) {
    document.addEventListener(e, function (ev) { ev.preventDefault(); }, { passive: false });
  });
  document.addEventListener('wheel', function (ev) { if (ev.ctrlKey) ev.preventDefault(); }, { passive: false });
})();
</script>
<title>{{ __('scoreboard::bjj_messages.ctl_title') }} · {{ $event->title }}</title>

@include('scoreboard::bjj.scoreboard.partials.console-styles')
</head>
<body>

@php
    $islandProps = \App\Scoreboard\Sports\BrazilianJiuJitsu\IslandProps::console(
        $event, $court, 'desktop', $state, $log, $queue, $screens,
        ['command' => $commandUrl, 'heartbeat' => $heartbeatUrl ?? null],
    );
@endphp

<div id="scoreboard-console-island" data-island-props="{{ json_encode($islandProps) }}"></div>

@vite(['resources/js/islands/scoreboard-console.jsx'])

@if (! empty($screenLink))
@include('scoreboard::bjj.partials.screen-link')
@endif
</body>
</html>
