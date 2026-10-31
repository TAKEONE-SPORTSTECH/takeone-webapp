{{--
    The VS screen as a preview for the Players tab's framing editor — THE
    shared screen (scoreboard::vs.arena, helpers, paint, keyframes), not a
    drawing of it, in this event's corner look. Rendered by
    App\Scoreboard\Support\ConsolePlayers::vsPreview inside an iframe the
    console drives over postMessage (same origin only):

      {type:'vs-preview', aka|ao: {photo?, vs_frame?}}   a corner's photo / framing

    and it answers {type:'vs-preview-ready'} once it can be driven — the same
    protocol the Karate console's preview speaks.
    Frozen on the introduction: no sound, no live link, no heartbeat.

    Needs: $look (aka/ao), $redRight, $intro (VsCorner's bout shape).
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>VS</title>
<style>
@php
    // Self-hosted, as every hall screen is (Self-Host Everything).
    $faces = [['Anton', 400, 'anton-400'], ['Barlow Condensed', 600, 'barlow-condensed-600'],
              ['Barlow Condensed', 700, 'barlow-condensed-700'], ['Barlow Condensed', 800, 'barlow-condensed-800']];
@endphp
@foreach ($faces as [$family, $weight, $slug])
@font-face { font-family: '{{ $family }}'; font-style: normal; font-weight: {{ $weight }}; font-display: swap;
  src: url("{{ route('court-display.font', $slug.'-latin.woff2', false) }}") format('woff2'); }
@endforeach
  html, body { margin: 0; height: 100%; background: #050507; overflow: hidden; }
  #fit { position: absolute; left: 50%; top: 50%; width: 1920px; height: 1080px;
         transform-origin: center center; overflow: hidden;
         background: radial-gradient(120% 90% at 50% 40%, #16161f 0%, #0a0a0e 65%, #050507 100%); }
  .layer { position: absolute; inset: 0; }
  [hidden] { display: none !important; }
  /* A frozen introduction: the panels and names arrive already in place. */
  #vs, #vs * { animation-duration: 0s !important; animation-delay: 0s !important; }
@include('scoreboard::vs.keyframes')
</style>
</head>
<body>
@php
    $L = $look;
    $rr = $redRight;
@endphp
<div id="fit">
@include('scoreboard::vs.arena')
</div>
<script>
(function () {
  'use strict';
  var fit = document.getElementById('fit');
  function scale() {
    var k = Math.min(window.innerWidth / 1920, window.innerHeight / 1080);
    fit.style.transform = 'translate(-50%, -50%) scale(' + k + ')';
  }
  window.addEventListener('resize', scale);
  scale();

  var el = function (id) { return document.getElementById(id); };
  var EVENT_TITLE = @json($intro['tournament']);
  var COURT = '';
@include('scoreboard::vs.helpers')
@include('scoreboard::vs.paint')

  var state = @json($intro);
  paintVs(state);
  el('vs').hidden = false;
  refitNames();

  // The console's editor moves the photo; this screen draws it.
  window.addEventListener('message', function (e) {
    var d = e.data;
    if (e.origin !== window.location.origin || !d || d.type !== 'vs-preview') return;
    ['aka', 'ao'].forEach(function (k) {
      var c = d[k];
      if (!c || !state[k]) return;
      if (typeof c.photo === 'string' && /^(https?:\/\/|\/)/.test(c.photo)) state[k].photo = c.photo;
      if ('vs_frame' in c) state[k].vs_frame = c.vs_frame;
    });
    paintVs(state);
  });

  if (window.parent && window.parent !== window) window.parent.postMessage({ type: 'vs-preview-ready' }, window.location.origin);
})();
</script>
</body>
</html>
