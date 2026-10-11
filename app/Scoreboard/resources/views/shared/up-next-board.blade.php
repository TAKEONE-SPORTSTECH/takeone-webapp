{{--
  The up-next board page — ONE page for every combat sport's queue screen.

  Karate's approved design. Each sport's board view includes it with its own
  words ($ns), font route, pairing-link partial and heartbeat; which corner is
  drawn on which side comes from App\Sports\Combat\CornerSide::red($sport).
  The rows themselves are partials/up-next, shared with the BJJ mat document.
--}}
{{--
    The hall board for one mat.

    This is the ONLY screen in the product with no navigation, no chrome and no
    viewer — it is a wall, seen from ten metres by people holding coffee. So it
    breaks house style deliberately and on purpose:

      · It does not extend a layout. The screen renders it as a whole document under
        cog/WPE with nothing else on the machine, and the agent caches this exact
        file to disk so the board is on screen before the network exists.
      · Its palette is the broadcast red/blue of a competition mat, not the app's
        purple. Design System tokens describe a product UI; this is signage.
      · Its markup and CSS are transcribed verbatim from the approved layout at
        draft/sample files/. Treat the visual output as fixed — restyle by
        agreement, never as a side effect.

    The stage is authored at 1920x1080 and scaled by --stage-scale, so the same
    file is correct on a 4K panel and on a low-powered screen rendering at 720p. Change the
    output resolution, never the stage.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="utf-8">
    {{-- Dark by design (a scoreboard console / wall board). Declared so a
         browser's auto-dark and Dark Reader both leave it alone — the same
         reasoning as the light pages, opposite value. --}}
    <meta name="color-scheme" content="dark">
    <meta name="darkreader-lock">
    <style>html { color-scheme: dark; }</style>

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
<title>{{ $pageTitle }}</title>

<style>
@php
    // Self-hosted: a venue screen must never wait on fonts.googleapis.com, and
    // a fallback to a system font would collapse the whole layout.
    $faces = [
        ['Anton', 400, 'anton-400'],
        ['Barlow Condensed', 400, 'barlow-condensed-400'],
        ['Barlow Condensed', 600, 'barlow-condensed-600'],
        ['Barlow Condensed', 700, 'barlow-condensed-700'],
        ['Barlow Condensed', 800, 'barlow-condensed-800'],
    ];
    $ranges = [
        'latin' => 'U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD',
        'latin-ext' => 'U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF',
        'vietnamese' => 'U+0102-0103, U+0110-0111, U+0128-0129, U+0168-0169, U+01A0-01A1, U+01AF-01B0, U+0300-0301, U+0303-0304, U+0308-0309, U+0323, U+0329, U+1EA0-1EF9, U+20AB',
    ];
@endphp
@foreach ($faces as [$family, $weight, $slug])
@foreach ($ranges as $subset => $range)
@font-face {
  font-family: '{{ $family }}';
  font-style: normal;
  font-weight: {{ $weight }};
  font-display: swap;
  {{-- Root-relative, never absolute. route(…, absolute: false) keeps the font
       on whatever origin is serving the board: an absolute URL is built from
       APP_URL, so a board opened on any other host or port (a dev server, the
       screen's cached copy, a LAN address at a venue) would ask a machine that
       isn't there, get nothing, and silently fall back to a system sans — which
       collapses the whole layout, because the typography IS the design. --}}
  src: url("{{ route($fontRoute, $slug.'-'.$subset.'.woff2', false) }}") format('woff2');
  unicode-range: {{ $range }};
}
@endforeach
@endforeach

  html, body { margin: 0; padding: 0; background: #0a0a0e; overflow: hidden; }
  @keyframes rowEnterL { from { opacity: 0; transform: translateX(-120px) skewX(-6deg); } to { opacity: 1; transform: translateX(0) skewX(0); } }
  @keyframes rowEnterR { from { opacity: 0; transform: translateX(120px) skewX(6deg); } to { opacity: 1; transform: translateX(0) skewX(0); } }
  @keyframes platePop { 0% { opacity: 0; transform: translate(-50%,-50%) scale(0.4); } 70% { opacity: 1; transform: translate(-50%,-50%) scale(1.12); } 100% { opacity: 1; transform: translate(-50%,-50%) scale(1); } }
  @keyframes headerIn { from { opacity: 0; transform: translateY(-40px); } to { opacity: 1; transform: translateY(0); } }
  @keyframes rowSweep { 0% { transform: translateX(-140%) skewX(-22deg); } 45%, 100% { transform: translateX(320%) skewX(-22deg); } }
  /* ── The draft's loops, kept — but driven by the compositor ──────────────
     The board animates exactly what the approved layout animates: a gold border
     running round the next bout, a glow breathing under it, a shimmer across
     the title, tracking on GET READY, a sweep along each row. What changed is
     HOW they are driven.

     As authored, three of them animated box-shadow, background-position and
     letter-spacing. Those are paint — and tracking is layout — on every frame,
     forever, across a full-width row: free on a desktop browser, and the reason
     the same board stuttered on a TV box and on the tablet, where the page is
     also being composited into the app a second time. The same pictures are now
     produced by animating `transform` and `opacity` only, each on its own
     layer, which the compositor runs without the main thread: the glow is a
     shadow rasterised once and faded, the running border is a wide gradient
     slid sideways behind the same mask, and GET READY breathes by stretching an
     inline span instead of re-laying-out its text.

     Anything added here follows the same rule — transform and opacity, or it
     becomes the one thing that drops the whole board's frame rate. */
  @keyframes goldSlide { from { transform: translateX(0); } to { transform: translateX(-50%); } }
  @keyframes glowPulse { 0%, 100% { opacity: 0; } 50% { opacity: 1; } }
  @keyframes readyBreath { 0%, 100% { transform: scaleX(1); opacity: 1; } 50% { transform: scaleX(1.08); opacity: 0.75; } }
  /* The one paint loop left, and deliberately: a gradient clipped to the glyphs
     cannot be slid without re-rasterising the text either way, and at ~1000x70px
     of a 1920x1080 stage it does not show up in a frame budget.

     No `will-change` anywhere on this board, and that is measured, not taste:
     Chromium already promotes an element with a running transform or opacity
     animation, and hinting the rest cost ~3fps (and up to 10 on layers carrying
     a blur) for nothing. Hint a layer here only with a before-and-after to
     show for it. */
  @keyframes titleShimmer { 0% { background-position: -200% 50%; } 100% { background-position: 300% 50%; } }
  @keyframes numBeat { 0%,100% { transform: scale(1); } 50% { transform: scale(1.14); } }

  #root { position: absolute; inset: 0; background: #050507; overflow: hidden; }
  #stage {
    position: absolute; left: 50%; top: 50%; width: 1920px; height: 1080px;
    transform: translate(-50%,-50%) scale(var(--stage-scale, 0.5)); transform-origin: center;
    background: radial-gradient(120% 90% at 50% 40%, #16161f 0%, #0a0a0e 65%, #050507 100%);
    font-family: 'Barlow Condensed', sans-serif; color: #e8e6e0; overflow: hidden;
    display: flex; flex-direction: column;
  }
  #head { display: flex; align-items: center; justify-content: space-between; gap: 30px; padding: 34px 60px 24px; animation: headerIn 0.8s cubic-bezier(0.22,1,0.36,1) both; }
  #rows { flex: 1; min-height: 0; display: flex; flex-direction: column; gap: 16px; padding: 0 60px 34px; }

  /* A mat with nothing queued says so, rather than showing an empty frame. */
  #idle { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 18px; }
  #idle .t { font-family: 'Anton', sans-serif; font-size: 92px; letter-spacing: 0.14em; text-transform: uppercase; color: rgba(232,230,224,0.22); }
  #idle .s { font-weight: 600; font-size: 34px; letter-spacing: 0.3em; text-transform: uppercase; color: rgba(232,230,224,0.35); }

  /* Connection trouble never blanks the board — the last known queue stays up
     and this is the only thing that changes. Small, because a wrong bout number
     is what actually hurts, and the queue rarely changes minute to minute. */
  #stale { position: absolute; right: 60px; bottom: 34px; display: none; align-items: center; gap: 12px; padding: 8px 20px; background: rgba(10,10,14,0.85); border: 1px solid rgba(232,230,224,0.25); font-weight: 600; font-size: 22px; letter-spacing: 0.2em; text-transform: uppercase; color: rgba(232,230,224,0.6); }
  #stale.on { display: flex; }
  /* The one loop on this board that never settles, deliberately: it is 12px,
     it is display:none unless something is actually wrong, and a trouble light
     that stops blinking stops being a trouble light. */
  #stale .dot { width: 12px; height: 12px; border-radius: 50%; background: #fb7c00; background: oklch(0.72 0.19 55); animation: numBeat 1.6s ease-in-out infinite; }

  @media (prefers-reduced-motion: reduce) {
    #head, #rows > *, #rows * { animation: none !important; }
  }
</style>
</head>
<body>

<div id="root">
  <div id="stage">

    <div id="head">
      <div style="flex:1; display:flex; align-items:center; gap:18px;">
        {{-- The two bars are the corners' colours, on the sides the corners stand. --}}
        {{-- A host may pass its mat's own side (`redRight`); otherwise the sport's convention. --}}
        @php $upRedRight = $redRight ?? \App\Sports\Combat\CornerSide::redRight($sport); @endphp
        {{-- The second corner's colour: blue, or white where the host says so (Kyokushin's SHIRO). --}}
        @php $upSecondBar = ($secondTone ?? 'blue') === 'white' ? 'background:#f2f2ef;' : 'background:#026fd7; background:oklch(0.55 0.18 255);'; @endphp
        {{-- …or the host's own corner colours (Karate's Mat\CornerLook), when it passes them. --}}
        @php
          $upFirstBar = 'background:#ea3c3f; background:oklch(0.62 0.21 25);';
          if (! empty($look)) {
              $upFirstBar = 'background:'.$look['aka']['bar'].';';
              $upSecondBar = 'background:'.$look['ao']['bar'].';';
          }
        @endphp
        <div style="width:10px; height:64px; {{ $upRedRight ? $upSecondBar : $upFirstBar }}"></div>
        <div id="eventTitle" style="font-weight:700; font-size:34px; letter-spacing:0.24em; text-transform:uppercase; color:rgba(232,230,224,0.85); max-width:520px;"></div>
      </div>
      <div style="display:flex; flex-direction:column; align-items:center; gap:4px;">
        <div style="font-family:'Anton',sans-serif; font-size:64px; letter-spacing:0.2em; padding-left:0.2em; text-transform:uppercase; white-space:nowrap; color:#fdc436; color:oklch(0.85 0.16 85);">{{ __('scoreboard::'.$ns.'.court_title') }}</div>
        <div style="height:3px; width:100%; background:linear-gradient(to right, transparent, #fdc436, transparent); background:linear-gradient(to right, transparent, oklch(0.85 0.16 85), transparent);"></div>
      </div>
      <div style="flex:1; display:flex; justify-content:flex-end; align-items:center; gap:18px;">
        <div id="courtBadge" style="display:flex; align-items:baseline; gap:12px; background:rgba(10,10,14,0.72); border:1px solid rgba(253,196,54,0.5); border:1px solid oklch(0.85 0.16 85 / 0.5); padding:10px 28px;">
          <span style="font-weight:600; font-size:32px; letter-spacing:0.3em; color:rgba(232,230,224,0.65); text-transform:uppercase;">{{ __('scoreboard::'.$ns.'.court_court') }}</span>
          <span id="courtNumber" style="font-family:'Anton',sans-serif; font-size:52px; color:#fff;"></span>
        </div>
        <div style="width:10px; height:64px; {{ $upRedRight ? $upFirstBar : $upSecondBar }}"></div>
      </div>
    </div>

    <div id="rows"></div>

    <div id="stale"><span class="dot"></span><span>{{ __('scoreboard::'.$ns.'.court_reconnecting') }}</span></div>
  </div>
</div>

@php
  // Left side first. Which corner stands where is the sport's convention.
  $upNextSides = ($redRight ?? \App\Sports\Combat\CornerSide::redRight($sport))
      ? [['key' => 'blue', 'tone' => $secondTone ?? 'blue'], ['key' => 'red', 'tone' => 'red']]
      : [['key' => 'red', 'tone' => 'red'], ['key' => 'blue', 'tone' => $secondTone ?? 'blue']];
  // A host's own corner colours, painted onto each lane (red = aka, blue = ao).
  if (! empty($look)) {
      foreach ($upNextSides as &$upSide) {
          $c = $look[$upSide['key'] === 'red' ? 'aka' : 'ao'];
          $upSide['paint'] = ['from' => $c['laneFrom'], 'to' => $c['laneTo']];
      }
      unset($upSide);
  }
@endphp
@include('scoreboard::partials.up-next')
<script>
(function () {
  'use strict';

  var ROWS = @json(\App\Scoreboard\Sports\Taekwondo\HallScreen\CourtDisplay::ROWS);
  var TBD = @json(__('scoreboard::'.$ns.'.court_tbd'));
  var IDLE_T = @json(__('scoreboard::'.$ns.'.court_idle_title'));
  var IDLE_S = @json(__('scoreboard::'.$ns.'.court_idle_sub'));

  var root = document.getElementById('root');
  var rowsEl = document.getElementById('rows');
  var staleEl = document.getElementById('stale');


  // ── Stage scaling ────────────────────────────────────────────────────────
  // The layout is authored at 1920x1080 and never reflows; it only scales. That
  // is what lets a low-powered screen render at 720p to save fill rate while staying pixel-
  // proportional to the approved design.
  function fit() {
    var r = root.getBoundingClientRect();
    if (r.width && r.height) root.style.setProperty('--stage-scale', Math.min(r.width / 1920, r.height / 1080));
  }
  (window.ResizeObserver ? new ResizeObserver(fit).observe(root) : window.addEventListener('resize', fit));
  fit();

  // The rows themselves are the shared design — partials/up-next.
  var show = UpNext.show, textOrHide = UpNext.textOrHide, el = UpNext.el;
  var UP = {
    sides: @json($upNextSides),
    tbd: TBD,
    getReady: @json(__('scoreboard::'.$ns.'.court_get_ready')),
    match: @json(__('scoreboard::'.$ns.'.court_match'))
  };
  function row(m, i, isNext, enter) { return UpNext.row(m, i, isNext, enter, UP); }
  function fitQueue(host) { UpNext.fit(host); }

  // ── Render ───────────────────────────────────────────────────────────────
  var shownVersion = null;
  var shownKeys = [];
  var first = true;
  var ownCourt = null;

  function keyOf(m) { return String(m.number) + '|' + (m.redName || '') + '|' + (m.blueName || ''); }

  function render(payload) {
    if (!payload || typeof payload !== 'object') return;

    // This board belongs to one mat, decided by the server when the page was
    // rendered. Now that updates are PUSHED rather than fetched, a payload for
    // some other mat is a thing that can arrive — an in-flight message for the
    // previous assignment landing just after a re-pair — and drawing it would
    // put Mat 2's queue on Mat 1's wall. The first payload sets the mat; a
    // later one that disagrees is dropped.
    if (ownCourt === null) ownCourt = payload.court || null;
    else if (payload.court && payload.court !== ownCourt) return;

    // A re-publish with no visible change must not repaint — on a wall, a board
    // that silently re-animates every few seconds reads as a fault.
    if (payload.version && payload.version === shownVersion) return;
    shownVersion = payload.version || null;

    textOrHide(document.getElementById('eventTitle'), payload.event && payload.event.title);
    // COURT + the mat's number. The design supplies the word, so "Mat 1" is
    // published as "1"; the label and its value hide together.
    show(document.getElementById('courtBadge'),
      textOrHide(document.getElementById('courtNumber'), payload.courtNumber));

    var matches = Array.isArray(payload.matches) ? payload.matches.slice(0, ROWS) : [];
    var keys = matches.map(keyOf);
    rowsEl.textContent = '';

    if (!matches.length) {
      var idle = el('div'); idle.id = 'idle';
      var it = el('div', null, IDLE_T); it.className = 't';
      var is = el('div', null, IDLE_S); is.className = 's';
      idle.appendChild(it); idle.appendChild(is);
      rowsEl.appendChild(idle);
      shownKeys = keys; first = false;
      return;
    }

    matches.forEach(function (m, i) {
      rowsEl.appendChild(row(m, i, i === 0, first || shownKeys.indexOf(keys[i]) === -1));
    });
    fitQueue(rowsEl);

    shownKeys = keys;
    first = false;
  }

  /**
   * The board's whole external contract.
   *
   * Phase 1 injects the payload server-side. The screen agent will later call
   * update() on each retained MQTT message and stale() when the broker drops,
   * against this same file cached to disk — so the transport can change without
   * this view changing at all.
   */
  window.CourtBoard = {
    update: render,
    stale: function (on) { staleEl.classList.toggle('on', !!on); },
    // 'queue' when this screen was hung up to show the running order and
    // nothing else, for as long as it is switched on. The socket client reads
    // it to know it must not navigate this screen onto a bout when the mat
    // loads one — see the note in CourtDisplayController::board.
    pinned: @json($pinned ?? false)
  };

  render(@json($payload));
  if (document.fonts && document.fonts.ready) document.fonts.ready.then(function () { fitQueue(rowsEl); });

@isset($statusUrl)
  // ── Heartbeat ────────────────────────────────────────────────────────────
  // Phase 1 renders this page once and then never speaks again, so without
  // this a device goes silent the moment it finishes loading — and the
  // organiser's console cannot tell a screen that is running from one that was
  // unplugged, which is the single thing worth knowing while a hall fills up.
  //
  // Deliberately the existing status endpoint: it already exists for the
  // pairing screen, it touches last_seen, and it carries one boolean. Once a
  // minute, against the pairing screen's own five seconds — a twelfth of the
  // traffic that is already considered acceptable on a metered link.
  // It also answers the other question a wall screen cannot ask for itself:
  // "am I still this mat's board?" An organiser who unpairs a screen expects it
  // to go back to its code within the minute — without this it would keep
  // showing a queue it is no longer assigned to, which is worse than blank.
  setInterval(function () {
    fetch(@json($statusUrl), { cache: 'no-store' })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (s) { if (s && s.claimed === false) window.location.reload(); })
      .catch(function () { /* offline — keep the last known board up */ });
    // Five seconds. This board sends nothing else, so twelve one-field requests
    // a minute is the whole of its budget — and it is what makes unpairing feel
    // immediate when the realtime push cannot get through.
  }, @json($heartbeatMs));
@endisset
})();
</script>

@isset($screenLink)
@include($screenLinkView)
@endisset
</body>
</html>
