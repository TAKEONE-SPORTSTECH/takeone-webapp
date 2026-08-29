{{--
    "Make this screen a display."

    The ONE address you type into anything with a browser — a hall television, a
    laptop on a trolley, a spare monitor at the entrance. It enrols itself and
    stands there showing a QR until an organiser scans it and says what it is:
    a scoreboard, an upcoming-matches board, or the scoring table.

    Sport-neutral on purpose. The packages each own a fleet, but the person
    carrying the television does not know which one will end up owning it — and
    should not have to. The fleet is decided at the moment somebody says what
    the screen is for, and the screen is adopted into it then.

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
<title>{{ __('events.screen_new_title') }}</title>
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
  src: url("{{ route('court-display.font', $slug.'-latin.woff2', false) }}") format('woff2');
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
  <div class="eyebrow">{{ __('events.screen_eyebrow') }}</div>
  <div class="title" id="title">{{ __('events.screen_new_working') }}</div>
  <div class="spinner" id="spin"></div>
  <div class="hint" id="hint">{{ __('events.screen_new_hint') }}</div>
  <button class="btn" id="retry" hidden>{{ __('events.screen_new_retry') }}</button>
</div>

<script>
(function () {
  'use strict';

  var KEY = 'takeone.screen.token';
  var ENROLL = @json(route('screen.enroll', [], false));
  var BOARD = @json(url('/screen'));
  var T = {
    failed: @json(__('events.screen_new_failed')),
    busy: @json(__('events.screen_new_busy')),
    retrying: @json(__('events.screen_new_retrying')),
    trouble: @json(__('events.screen_new_trouble')),
    status: @json(__('events.screen_new_status')),
    malformed: @json(__('events.screen_new_malformed'))
  };

  function go(token) { window.location.replace(BOARD + '/' + token); }

  function fail(why) {
    document.getElementById('spin').hidden = true;
    document.getElementById('title').textContent = T.failed;
    if (why) document.getElementById('hint').textContent = why;
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

  /* Every request here gets a deadline. A screen on venue wifi can have a
     fetch hang indefinitely — no error, no timeout, nothing to catch — and a
     wall showing a spinner forever is the one outcome nobody in the hall can
     diagnose or fix. */
  function ask(url) {
    var ctrl = ('AbortController' in window) ? new AbortController() : null;
    var timer = setTimeout(function () { ctrl && ctrl.abort(); }, 8000);

    // Declares JSON so the app answers with a STATUS CODE rather than
    // rerouting a signed-in browser to a friendly page — see the status
    // endpoint's own note.
    return fetch(url, {
      cache: 'no-store',
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      signal: ctrl ? ctrl.signal : undefined,
    })
      .then(function (r) { clearTimeout(timer); return r; })
      .catch(function () { clearTimeout(timer); return null; });
  }

  function forget() {
    try { window.localStorage.removeItem(KEY); } catch (e) { /* nothing to forget */ }
  }

  /* Busy or unreachable is NOT the same as gone.
     Gone means re-enrol. Busy means wait — minting a new screen on every
     throttled reply would answer a rate limit by creating more work, and would
     leave a trail of abandoned rows and a code on the wall that changes every
     few seconds while somebody is trying to scan it. */
  /* A wall screen cannot be asked what it saw in the console, so it says it
     itself. The old version changed only the hint line and left the title
     reading "Setting this screen up" — which, from across a hall, is
     indistinguishable from working, and is why a stalled screen looked like a
     hang instead of a rate limit. Now the title changes, the reason is on
     screen, and the wait is counted down so it is visibly still trying. */
  var attempts = 0;

  function later(fn, why, seconds) {
    attempts++;
    var wait = seconds || 5;
    document.getElementById('title').textContent = T.trouble;
    document.getElementById('retry').hidden = false;

    var hint = document.getElementById('hint');
    (function tick() {
      hint.textContent = why + ' (' + wait + ')';
      if (wait-- <= 0) { hint.textContent = why; fn(); return; }
      setTimeout(tick, 1000);
    })();
  }

  /** Say exactly what came back, so nobody has to guess from a spinner. */
  function detail(r) {
    if (!r) return T.retrying;
    if (r.status === 429) return T.busy;
    return T.status.replace(':code', r.status);
  }

  function enroll() {
    postAsk(ENROLL).then(function (r) {
      if (!r) return later(enroll, T.retrying);

      // Too many screens set up from this building in the last hour. It clears
      // by itself, and the server says when — so wait exactly that long rather
      // than hammering a limit that is already refusing.
      if (r.status === 429) {
        var after = parseInt(r.headers.get('retry-after') || '', 10);
        return later(enroll, T.busy, (after > 0 && after < 3600) ? after : 60);
      }

      // Anything else is worth showing rather than retrying blindly — a 500 is
      // not going to fix itself in five seconds, and a screen quietly cycling
      // on one tells nobody anything.
      if (!r.ok) return fail(detail(r));

      r.json().then(function (d) {
        if (!d || !d.token) return fail(T.malformed);
        try { window.localStorage.setItem(KEY, d.token); } catch (e) { /* still works, just not across reloads */ }
        go(d.token);
      }).catch(function () { fail(T.malformed); });
    });
  }

  /** The same deadline, for the one POST this page makes. */
  function postAsk(url) {
    var ctrl = ('AbortController' in window) ? new AbortController() : null;
    var timer = setTimeout(function () { ctrl && ctrl.abort(); }, 8000);

    return fetch(url, {
      method: 'POST',
      headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({ label: 'Screen' }),
      signal: ctrl ? ctrl.signal : undefined,
    }).then(function (r) { clearTimeout(timer); return r; })
      .catch(function () { clearTimeout(timer); return null; });
  }

  /* A screen that has been here before keeps its identity — but only if that
     identity still exists. A token can stop resolving: the row was pruned, the
     database restored, somebody tidied up. A wall screen cannot be rescued by
     hand, so it checks before it commits. */
  function resume(token) {
    ask(BOARD + '/' + token + '/status').then(function (r) {
      if (!r) return later(function () { resume(token); }, T.retrying);
      // Gone for good — the row was pruned, or the database moved on. Start over
      // rather than following a token nowhere.
      if (r.status === 404 || r.status === 410) { forget(); enroll(); return; }
      if (r.status === 429) {
        var after = parseInt(r.headers.get('retry-after') || '', 10);
        return later(function () { resume(token); }, T.busy, (after > 0 && after < 3600) ? after : 60);
      }
      // Any other refusal: the stored identity is the suspect, so drop it and
      // enrol fresh. Better a new screen than a screen that cannot become one.
      if (!r.ok) { forget(); enroll(); return; }
      go(token);
    });
  }

  document.getElementById('retry').onclick = function () {
    document.getElementById('retry').hidden = true;
    document.getElementById('spin').hidden = false;
    document.getElementById('title').textContent = @json(__('events.screen_new_working'));
    document.getElementById('hint').textContent = @json(__('events.screen_new_hint'));
    // Pressing it means "this one is stuck" — so let go of the stored identity
    // and ask for a clean one, which is what a person expects from Try again.
    forget();
    enroll();
  };

  /* "This is a DIFFERENT screen."

     Keeping the identity is right for the normal case — a reload, a television
     waking up — but it makes a second screen impossible on a machine that has
     already been one: the same address just shows the same screen again. So the
     address takes an explicit "start over", used by the link on the waiting
     page and typeable by hand:  /screen?new

     Deliberately explicit. Minting a new screen on every visit is the failure
     this whole page exists to avoid; minting one when asked is not. */
  var FRESH = /[?&]new\b/.test(window.location.search);

  if (FRESH) {
    forget();
    // Drop the flag from the address first, so a reload of THIS page does not
    // keep enrolling — one press, one screen.
    try { window.history.replaceState({}, '', window.location.pathname); } catch (e) {}
    enroll();
  } else {
    var t = stored();
    if (t) { resume(t); } else { enroll(); }
  }
})();
</script>
</body>
</html>
