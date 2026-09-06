{{--
    The mat screen's REACT shell — Phase M3, behind `features.react_scoreboard`.

    A shell and nothing else: the same <head> the hand-written document has (the
    same viewport refusal, the same shared stylesheet, the same winner
    celebration), one empty div, and the island entry. Everything that used to
    be a thousand lines of `text(id, …)` below the fold now lives in
    resources/js/islands/scoreboard/Board.jsx.

    The Blade original next door is untouched and remains the default. Both draw
    from bjj/screen/partials/board-styles.blade.php, both answer to the same
    `window.CourtBoard` contract, and both include the same live link — so
    turning the flag off restores the previous behaviour exactly, mid-event if
    it comes to that.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="utf-8">
{{-- A screen is not a document: it is authored at one size and scaled to fit
     the glass, so there is nothing here to zoom INTO. The one place the house
     rule against `user-scalable=no` does not apply — this is signage. --}}
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
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
<title>{{ __('scoreboard::bjj_messages.board_title') }} · {{ $court }}</title>

@include('scoreboard::bjj.screen.partials.board-styles')
@include('scoreboard::bjj.scoreboard.winner-celebration')
</head>
<body>

@php
    $islandProps = \App\Scoreboard\Sports\BrazilianJiuJitsu\IslandProps::board(
        $event, $court, $pinned, $state, $board,
        ['status' => $statusUrl ?? null],
    );
@endphp

{{-- The mount point. Props travel as a JSON attribute (Blade-escaped), so the
     first frame is correct with no round trip. --}}
<div id="scoreboard-island" data-island-props="{{ json_encode($islandProps) }}"></div>

@vite(['resources/js/islands/scoreboard-board.jsx'])

@if (! empty($screenLink))
@include('scoreboard::bjj.partials.screen-link')
@endif
</body>
</html>
