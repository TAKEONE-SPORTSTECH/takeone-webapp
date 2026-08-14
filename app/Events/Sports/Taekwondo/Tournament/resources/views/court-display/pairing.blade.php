{{--
    What a screen shows before it knows what it is.

    A Pi is carried into a hall, plugged in, and this is the first and only thing
    on it. Whoever is standing in front of it may never have seen the product, is
    holding a phone, and has a competition starting — so the screen has exactly
    one instruction and one thing to point a camera at.

    Dark, like every other screen this product puts on a wall. A single white
    panel in a row of black ones reads as a screen that has crashed, and in a
    dimmed hall it is the brightest object in the room.

    The QR is INVERTED — light modules on the dark field — rather than sitting
    on a white tile. A decoder looks for dark modules on a light field, so this
    is the polarity fewer scanners try: phone cameras and Lens read it, some
    library-based scanners do not. Acceptable here, and only here, because the
    six-character code is printed underneath at 112px and pairing by typing it
    is a first-class path, not a workaround.

    The code under the QR is not decoration. Hall lighting, a dirty lens, a
    cracked phone or a screen at an angle all beat a scanner, and the fallback
    has to be readable aloud from the floor — so it is also printed large, in an
    alphabet with no vowels and no 0/O/1/I.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ __('event-taekwondo_tournament::messages.pair_title') }}</title>

<style>
@php
    $faces = [['Anton', 400, 'anton-400'], ['Barlow Condensed', 600, 'barlow-condensed-600'], ['Barlow Condensed', 700, 'barlow-condensed-700']];
    $ranges = [
        'latin' => 'U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD',
        'latin-ext' => 'U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF',
    ];
@endphp
@foreach ($faces as [$family, $weight, $slug])
@foreach ($ranges as $subset => $range)
@font-face {
  font-family: '{{ $family }}';
  font-style: normal;
  font-weight: {{ $weight }};
  font-display: swap;
  {{-- Root-relative, for the same reason the board's are. --}}
  src: url("{{ route('court-display.font', $slug.'-'.$subset.'.woff2', false) }}") format('woff2');
  unicode-range: {{ $range }};
}
@endforeach
@endforeach

  html, body { margin: 0; padding: 0; background: #0a0a0e; overflow: hidden; }

  @keyframes riseIn { from { opacity: 0; transform: translateY(28px); } to { opacity: 1; transform: translateY(0); } }
  /* The one moving thing on the screen: a slow sweep across the code panel, so
     a passer-by can tell the Pi is alive and not frozen on a still image. */
  @keyframes breathe { 0%,100% { box-shadow: 0 0 40px rgba(253,196,54,0.10), 0 18px 60px rgba(0,0,0,0.55); } 50% { box-shadow: 0 0 90px rgba(253,196,54,0.28), 0 26px 80px rgba(0,0,0,0.7); } }

  #root { position: absolute; inset: 0; background: #050507; overflow: hidden; }
  #stage {
    position: absolute; left: 50%; top: 50%; width: 1920px; height: 1080px;
    transform: translate(-50%,-50%) scale(var(--stage-scale, 0.5)); transform-origin: center;
    background: radial-gradient(120% 90% at 50% 30%, #16161f 0%, #0a0a0e 70%); color: #e8e6e0;
    font-family: 'Barlow Condensed', sans-serif;
    display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 44px;
  }

  .eyebrow { font-weight: 600; font-size: 34px; letter-spacing: 0.42em; text-transform: uppercase; color: oklch(0.85 0.16 85); animation: riseIn 0.7s 0.05s cubic-bezier(0.22,1,0.36,1) both; }

  /* The symbol is drawn inverted, so the panel is the page's own dark with a
     gold edge — a frame around the thing to point a camera at, not a hole. */
  .qr { padding: 34px; background: #0a0a0e; border: 3px solid oklch(0.85 0.16 85 / 0.55); border-radius: 28px; animation: riseIn 0.7s 0.15s cubic-bezier(0.22,1,0.36,1) both, breathe 4s 1s ease-in-out infinite; }
  .qr svg { display: block; width: 460px; height: 460px; shape-rendering: crispEdges; }

  .codeWrap { display: flex; flex-direction: column; align-items: center; gap: 12px; animation: riseIn 0.7s 0.25s cubic-bezier(0.22,1,0.36,1) both; }
  .codeLabel { font-weight: 600; font-size: 26px; letter-spacing: 0.3em; text-transform: uppercase; color: rgba(232,230,224,0.55); }
  /* Read aloud across a hall, and the scanner's fallback — so it takes the
     gold glow and is the brightest text on the screen after the symbol. */
  .code { font-family: 'Anton', sans-serif; font-size: 112px; line-height: 1; letter-spacing: 0.16em; padding-left: 0.16em; color: #fffdf5; text-shadow: 0 0 60px rgba(253,196,54,0.45); }

  .hint { font-weight: 600; font-size: 32px; letter-spacing: 0.06em; color: rgba(232,230,224,0.6); max-width: 1100px; text-align: center; animation: riseIn 0.7s 0.35s cubic-bezier(0.22,1,0.36,1) both; }

  /* Bottom-left, small: useful when a screen will not pair and somebody has to
     say what it is looking at, invisible from the floor otherwise. */
  #foot { position: absolute; left: 60px; bottom: 44px; display: flex; align-items: center; gap: 14px; font-weight: 600; font-size: 22px; letter-spacing: 0.2em; text-transform: uppercase; color: rgba(232,230,224,0.45); }
  /* Pulses, so a passer-by can see the screen is awake and not frozen. */
  #foot .dot { width: 12px; height: 12px; border-radius: 50%; background: oklch(0.75 0.19 145); animation: pulse 2s ease-in-out infinite; }
  @keyframes pulse { 0%,100% { opacity: 1; } 50% { opacity: 0.25; } }

  @media (prefers-reduced-motion: reduce) { * { animation: none !important; } }
</style>
</head>
<body>

<div id="root">
  <div id="stage">

    <div class="eyebrow">{{ __('event-taekwondo_tournament::messages.pair_eyebrow') }}</div>

    {{-- Rendered offline by bacon/bacon-qr-code — a hall screen must never wait
         on an external QR service, and this one has to work the moment it boots.

         Margin 4 is the spec's quiet zone, not padding taste: a scanner needs
         four clear modules around the symbol to lock onto it, and this one gets
         read at an angle, across a hall, on a phone somebody is holding one
         handed. The CSS padding around it is not a substitute — it is only
         34px, under three modules at this size. --}}
    <div class="qr">{!! \App\Support\Qr::svg($claimUrl, 460, 4, '#ffffff', '#0a0a0e') !!}</div>

    <div class="codeWrap">
      <div class="codeLabel">{{ __('event-taekwondo_tournament::messages.pair_code_label') }}</div>
      <div class="code">{{ $code }}</div>
    </div>

    <div class="hint">{{ __('event-taekwondo_tournament::messages.pair_hint') }}</div>

    <div id="foot"><span class="dot"></span><span>{{ __('event-taekwondo_tournament::messages.pair_waiting') }}</span></div>
  </div>
</div>

<script>
(function () {
  'use strict';

  var root = document.getElementById('root');

  // Same stage mechanic as the board: authored at 1920x1080, only ever scaled,
  // so the pairing screen and the board it becomes are the same size on the wall.
  function fit() {
    var r = root.getBoundingClientRect();
    if (r.width && r.height) root.style.setProperty('--stage-scale', Math.min(r.width / 1920, r.height / 1080));
  }
  if (window.ResizeObserver) { new ResizeObserver(fit).observe(root); } else { window.addEventListener('resize', fit); }
  fit();

  // The screen has no keyboard and nobody watching it, so it has to notice
  // being claimed by itself: ask a one-field endpoint, and reload into the
  // board when the answer flips.
  //
  // Slow on purpose. Pairing is a one-off human action measured in tens of
  // seconds, this runs on a Pi 3B over a metered 4G link, and every hall screen
  // does it at once — so five seconds is responsive to a person and nothing to
  // the server.
  setInterval(function () {
    fetch(@json(route('court-display.status', $token, false)), { cache: 'no-store' })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (s) { if (s && s.claimed) window.location.reload(); })
      .catch(function () { /* offline — keep showing the code and try again */ });
  }, 5000);
})();
</script>

@isset($screenLink)
@include('event-taekwondo_tournament::court-display.partials.screen-link')
@endisset
</body>
</html>
