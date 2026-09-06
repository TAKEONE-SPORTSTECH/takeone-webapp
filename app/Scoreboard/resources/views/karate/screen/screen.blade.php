{{--
    "Make this screen a display."

    The one address you can type into anything with a browser — a hall
    television, a laptop on a trolley, a spare monitor at the entrance — to turn
    it into one of this event's screens. It enrols itself, then stands there
    showing its pairing QR until an organiser scans it and says what it is for.

    ── Why this is a page and not a redirect ───────────────────────────────────

    A screen must keep the SAME identity across a reload. A server-side redirect
    that enrolled on every GET would mint a fresh device every time somebody
    bumped F5 or the television woke up, leaving a trail of dead rows and, worse,
    a screen that was paired five minutes ago quietly becoming a different,
    unpaired one. So the token is enrolled once, kept in this origin's
    localStorage, and reused for the life of that screen.

    Nothing here is a privilege: enrolling yields an UNCLAIMED device that can
    render nothing but a QR code. It is the organiser's scan — an authenticated
    action, checked against the event they manage — that turns it into a board.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="utf-8">
{{-- A screen is not a document: it is authored at one size and scaled to fit
     the glass, so there is nothing here to zoom INTO — magnifying it can only
     push part of the surface off the edge, which on a wall nobody can undo and
     on the scoring table hides the row of controls along the bottom. Pinch and
     double-tap are therefore refused, and the system font-size setting is not
     allowed to inflate text inside a stage that cannot grow with it.

     This is the ONE place the house rule against `user-scalable=no` does not
     apply (mobile web must always pinch-zoom, WCAG 1.4.4): these documents are
     signage and a fixed console, not pages anybody reads. --}}
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
<style>
  /* `pan-x pan-y`, NOT `manipulation`: manipulation still permits pinch-zoom
     (it only drops the double-tap delay), which is exactly the gesture being
     refused here. Panning is left alone — the scoring console is taller than a
     10" tablet and has to be scrollable. */
  html { -webkit-text-size-adjust: 100%; text-size-adjust: 100%; touch-action: pan-x pan-y; }
  body { touch-action: pan-x pan-y; }
</style>
{{-- The same refusal for the two zoom gestures a browser will still offer even
     with the viewport above: Safari's pinch (`gesture*`) and ctrl+wheel. Both
     are cancelable, both are dead here, and neither is used by any screen. --}}
<script>
(function () {
  'use strict';
  ['gesturestart', 'gesturechange', 'gestureend'].forEach(function (e) {
    document.addEventListener(e, function (ev) { ev.preventDefault(); }, { passive: false });
  });
  document.addEventListener('wheel', function (ev) {
    if (ev.ctrlKey) ev.preventDefault();
  }, { passive: false });
})();
</script>
<title>{{ __('scoreboard::karate_messages.screen_new_title') }}</title>
<style>
@php
    $faces = [['Anton', 400, 'anton-400'], ['Barlow Condensed', 700, 'barlow-condensed-700']];
@endphp
@foreach ($faces as [$family, $weight, $slug])
@font-face {
  font-family: '{{ $family }}';
  font-style: normal;
  font-weight: {{ $weight }};
  font-display: swap;
  src: url("{{ route('karate-court-display.font', $slug.'-latin.woff2', false) }}") format('woff2');
}
@endforeach
  html, body { margin:0; height:100%; background:#0a0a0e; color:#e8e6e0;
               font-family:'Barlow Condensed', sans-serif; overflow:hidden; }
  #wrap { position:absolute; inset:0; display:flex; flex-direction:column;
          align-items:center; justify-content:center; gap:26px; text-align:center; padding:40px;
          background:radial-gradient(120% 90% at 50% 30%, #16161f 0%, #0a0a0e 70%); }
  .eyebrow { font-weight:700; font-size:22px; letter-spacing:0.5em; text-transform:uppercase;
             color:oklch(0.85 0.16 85); }
  .title { font-family:'Anton', sans-serif; font-size:64px; line-height:1; text-transform:uppercase; color:#fff; }
  .hint { font-weight:700; font-size:24px; letter-spacing:0.1em; text-transform:uppercase;
          color:rgba(232,230,224,0.55); max-width:820px; }
  .spinner { width:64px; height:64px; border:6px solid rgba(255,255,255,0.15);
             border-top-color:oklch(0.85 0.16 85); border-radius:50%; animation:spin 0.9s linear infinite; }
  @keyframes spin { to { transform:rotate(360deg); } }
  .btn { font-family:'Barlow Condensed',sans-serif; font-weight:700; font-size:22px; letter-spacing:0.12em;
         text-transform:uppercase; background:rgba(255,255,255,0.08); color:#e8e6e0;
         border:1px solid rgba(255,255,255,0.3); padding:16px 30px; cursor:pointer; }
  .btn:hover { background:rgba(255,255,255,0.16); }
  @media (prefers-reduced-motion: reduce) { .spinner { animation:none; } }
</style>
</head>
<body>
<div id="wrap">
  <div class="eyebrow">{{ __('scoreboard::karate_messages.pair_eyebrow') }}</div>
  <div class="title" id="title">{{ __('scoreboard::karate_messages.screen_new_working') }}</div>
  <div class="spinner" id="spin"></div>
  <div class="hint" id="hint">{{ __('scoreboard::karate_messages.screen_new_hint') }}</div>
  <button class="btn" id="retry" hidden>{{ __('scoreboard::karate_messages.screen_new_retry') }}</button>
</div>

<script>
(function () {
  'use strict';

  // Namespaced per fleet: a screen that was a Taekwondo board must not come
  // back as a Karate one holding a token the Karate package has never heard of.
  var KEY = 'takeone.karate.court.token';
  var ENROLL = @json(route('karate-court-display.enroll', [], false));
  var BOARD = @json(url('/karate/court'));
  var T = {
    failed: @json(__('scoreboard::karate_messages.screen_new_failed'))
  };

  function go(token) { window.location.replace(BOARD + '/' + token); }

  function fail() {
    document.getElementById('spin').hidden = true;
    document.getElementById('title').textContent = T.failed;
    document.getElementById('retry').hidden = false;
  }

  /* A screen that has already been here keeps the identity it was given — the
     whole reason this is a page. A stored value that is not a token is treated
     as absent rather than trusted into a URL. */
  function stored() {
    try {
      var t = window.localStorage.getItem(KEY);
      return (typeof t === 'string' && /^[A-Za-z0-9]{40}$/.test(t)) ? t : null;
    } catch (e) { return null; }   // private mode, or storage disabled
  }

  function enroll() {
    fetch(ENROLL, {
      method: 'POST',
      headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
      body: JSON.stringify({ label: 'Browser screen' })
    })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (d) {
        if (!d || !d.token) return fail();
        try { window.localStorage.setItem(KEY, d.token); } catch (e) { /* still works, just not across reloads */ }
        go(d.token);
      })
      .catch(fail);
  }

  document.getElementById('retry').onclick = function () {
    document.getElementById('retry').hidden = true;
    document.getElementById('spin').hidden = false;
    enroll();
  };

  var t = stored();
  if (t) { go(t); } else { enroll(); }
})();
</script>
</body>
</html>
