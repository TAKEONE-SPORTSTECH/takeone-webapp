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
<title>{{ __('scoreboard::karate_messages.court_title') }}</title>

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
  src: url("{{ route('karate-court-display.font', $slug.'-'.$subset.'.woff2', false) }}") format('woff2');
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
        <div style="width:10px; height:64px; background:#ea3c3f; background:oklch(0.62 0.21 25);"></div>
        <div id="eventTitle" style="font-weight:700; font-size:34px; letter-spacing:0.24em; text-transform:uppercase; color:rgba(232,230,224,0.85); max-width:520px;"></div>
      </div>
      <div style="display:flex; flex-direction:column; align-items:center; gap:4px;">
        <div style="font-family:'Anton',sans-serif; font-size:64px; letter-spacing:0.2em; padding-left:0.2em; text-transform:uppercase; white-space:nowrap; background:linear-gradient(100deg, #fdc436 40%, #fffdf0 50%, #fdc436 60%); background:linear-gradient(100deg, oklch(0.85 0.16 85) 40%, #fffdf0 50%, oklch(0.85 0.16 85) 60%); background-size:200% 100%; -webkit-background-clip:text; background-clip:text; -webkit-text-fill-color:transparent; animation:titleShimmer 3.5s linear infinite;">{{ __('scoreboard::karate_messages.court_title') }}</div>
        <div style="height:3px; width:100%; background:linear-gradient(to right, transparent, #fdc436, transparent); background:linear-gradient(to right, transparent, oklch(0.85 0.16 85), transparent);"></div>
      </div>
      <div style="flex:1; display:flex; justify-content:flex-end; align-items:center; gap:18px;">
        <div id="courtBadge" style="display:flex; align-items:baseline; gap:12px; background:rgba(10,10,14,0.72); border:1px solid rgba(253,196,54,0.5); border:1px solid oklch(0.85 0.16 85 / 0.5); padding:10px 28px;">
          <span style="font-weight:600; font-size:32px; letter-spacing:0.3em; color:rgba(232,230,224,0.65); text-transform:uppercase;">{{ __('scoreboard::karate_messages.court_court') }}</span>
          <span id="courtNumber" style="font-family:'Anton',sans-serif; font-size:52px; color:#fff;"></span>
        </div>
        <div style="width:10px; height:64px; background:#026fd7; background:oklch(0.55 0.18 255);"></div>
      </div>
    </div>

    <div id="rows"></div>

    <div id="stale"><span class="dot"></span><span>{{ __('scoreboard::karate_messages.court_reconnecting') }}</span></div>
  </div>
</div>

<script>
(function () {
  'use strict';

  var ROWS = @json(\App\Scoreboard\Sports\Taekwondo\HallScreen\CourtDisplay::ROWS);
  var TBD = @json(__('scoreboard::karate_messages.court_tbd'));
  var IDLE_T = @json(__('scoreboard::karate_messages.court_idle_title'));
  var IDLE_S = @json(__('scoreboard::karate_messages.court_idle_sub'));

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

  // ── Untrusted values ─────────────────────────────────────────────────────
  // Every name, club and image path on this board was typed by a person. Text
  // goes in through textContent only, and a URL is dropped unless it is one we
  // would have generated — a hall screen is a publication surface and an
  // injected style/URL here is a defect, not a cosmetic bug.
  function safeUrl(u) {
    return (typeof u === 'string' && /^(https?:\/\/|\/)[^"'()\\\s]*$/.test(u)) ? u : null;
  }

  // ── Missing data hides, it never invents ─────────────────────────────────
  // Every element the design specifies is always built. When the system has no
  // value for one, the element is hidden rather than filled with a placeholder:
  // a stand-in silhouette or an initial-letter crest is information the board
  // does not actually have, and on a hall screen invented detail reads as fact.
  // Nothing is deleted — restore the data and the element reappears by itself.
  // Remembers the element's own display before hiding it. Several of these are
  // flex rows carrying `display:flex` inline, and blanking that instead of
  // restoring it would silently relayout the row as a block.
  function show(el, has) {
    if (el.__display === undefined) el.__display = el.style.display || '';
    el.style.display = has ? el.__display : 'none';
    return has;
  }
  /** Paint a background image, or hide the element that would have held it. */
  function bgOrHide(el, url) {
    var u = safeUrl(url);
    if (show(el, !!u)) el.style.backgroundImage = 'url("' + encodeURI(u) + '")';
    return !!u;
  }
  /** Set text, or hide the element that would have held it. */
  function textOrHide(el, value) {
    var v = (value == null ? '' : String(value)).trim();
    if (show(el, v !== '')) el.textContent = v;
    return v !== '';
  }

  // TODO(offline): flags come from flagcdn. Harmless on 4G, but the screen agent
  // should mirror them to disk so a dead uplink never empties the flag boxes.
  function flagUrl(code) {
    return /^[a-z]{2}$/.test(String(code || '')) ? 'https://flagcdn.com/w1280/' + code + '.png' : null;
  }

  // ── Colour has to survive an old engine ──────────────────────────────────
  // The screen images ship WPE WebKit 2.38 (cog), which does not implement oklch():
  // it drops the WHOLE declaration, so a lone `background:oklch(...)` painted
  // black and took the entire palette down with it. Every oklch colour is
  // therefore declared twice — the sRGB twin first, the oklch second — and each
  // engine keeps the last one it can parse. Wide-gamut screens still get oklch.
  // Keep the pairs in sync: the hexes are the exact sRGB conversions.
  function dual(prop, fallback, modern) {
    return prop + ':' + fallback + ';' + prop + ':' + modern + ';';
  }

  var RED_TONE = { fb: '#902828', ok: 'oklch(0.44 0.14 25)' },
      BLUE_TONE = { fb: '#0e4786', ok: 'oklch(0.4 0.12 255)' },
      GOLD_FB = '#fdc436', GOLD = 'oklch(0.85 0.16 85)';

  function el(tag, style, text) {
    var n = document.createElement(tag);
    if (style) n.style.cssText = style;
    if (text != null) n.textContent = text;
    return n;
  }

  /** One corner half — photo, name, flag, crest, club. */
  function half(m, corner, isRed) {
    var tone = isRed ? RED_TONE : BLUE_TONE;
    var wrap = el('div', 'flex:1; min-width:0; display:flex; align-items:stretch;' +
      (isRed
        ? dual('background', 'linear-gradient(90deg, #a21921 0%, #551112 100%)',
                             'linear-gradient(90deg, oklch(0.46 0.17 25) 0%, oklch(0.3 0.1 25) 100%)') +
          'clip-path:polygon(0 0, 100% 0, calc(100% - 60px) 100%, 0 100%); margin-right:-30px;'
        : 'flex-direction:row-reverse;' +
          dual('background', 'linear-gradient(270deg, #004b97 0%, #012854 100%)',
                             'linear-gradient(270deg, oklch(0.42 0.14 255) 0%, oklch(0.28 0.09 255) 100%)') +
          'clip-path:polygon(60px 0, 100% 0, 100% 100%, 0 100%); margin-left:-30px;'));

    // Competitor portrait. Hidden when the athlete has no published picture —
    // the corner's own tone still carries the half, and the name gets the room.
    var photo = el('div', 'width:230px; flex:0 0 auto;' + dual('background-color', tone.fb, tone.ok) +
      'background-size:cover; background-position:center 12%;');
    bgOrHide(photo, m[corner + 'Photo']);
    wrap.appendChild(photo);

    var col = el('div', 'flex:1; min-width:0; display:flex; flex-direction:column; justify-content:center; gap:6px;' +
      (isRed ? 'padding:10px 60px 10px 26px;' : 'align-items:flex-end; padding:10px 26px 10px 60px; text-align:right;'));

    // The one field that never hides: a nameless half looks broken from ten
    // metres, so an undrawn slot says so instead.
    col.appendChild(el('div', "font-family:'Anton',sans-serif; font-size:56px; line-height:1; text-transform:uppercase; color:#fff; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;", m[corner + 'Name'] || TBD));

    var meta = el('div', 'display:flex; align-items:center; gap:12px; max-width:100%;' + (isRed ? '' : 'flex-direction:row-reverse;'));

    // Stretched to fill the box — `100% 100%`, not `cover` and not `contain`.
    // National flags run from 1.43:1 (Brazil) to 2:1 (Jordan) while this box is
    // 4:3, so the three options are: crop (`cover`) which ate a third off each
    // side of a 2:1 flag and took Jordan's chevron and Oman's emblem with it;
    // letterbox (`contain`) which left grey bands and a flag that did not fill
    // its frame; or stretch, which keeps every flag WHOLE and fills the plate.
    // Stretch is the chosen trade: a little distortion, nothing lost.
    var flag = el('div', 'width:76px; flex:0 0 auto; aspect-ratio:4/3; background-color:rgba(0,0,0,0.25); background-size:100% 100%; image-rendering:auto; background-repeat:no-repeat; background-position:center; border:1px solid rgba(255,255,255,0.4);');
    var hasFlag = bgOrHide(flag, flagUrl(m[corner + 'Flag']));
    meta.appendChild(flag);

    var crest = el('div', 'width:66px; height:66px; flex:0 0 auto; border-radius:50%; background-color:rgba(255,255,255,0.12); background-size:cover; background-position:center; border:1px solid rgba(255,255,255,0.35);');
    var hasCrest = bgOrHide(crest, m[corner + 'Logo']);
    meta.appendChild(crest);

    var club = el('div', 'min-width:0; font-weight:600; font-size:30px; letter-spacing:0.06em; text-transform:uppercase; color:rgba(255,255,255,0.85); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;');
    var hasClub = textOrHide(club, m[corner + 'Club']);
    meta.appendChild(club);

    // With flag, crest and club all absent the strip is an empty flex row that
    // would still eat its gap under the name — so the row itself steps aside.
    show(meta, hasFlag || hasCrest || hasClub);

    col.appendChild(meta);
    wrap.appendChild(col);
    return wrap;
  }

  /** The centre plate: bout number, round, weight class — gold when it is next. */
  function plate(m, isNext) {
    var p = el('div', 'position:absolute; left:50%; top:50%; transform:translate(-50%,-50%); display:flex; flex-direction:column; align-items:center; gap:2px;' +
      (isNext ? dual('background', GOLD_FB, GOLD) : 'background:#101016;') +
      'border:2px solid ' + (isNext ? '#fffdf0' : 'rgba(255,255,255,0.3)') + ';' +
      'padding:10px 26px 12px; min-width:220px; box-shadow:0 0 30px rgba(0,0,0,0.7); z-index:3;');

    var text = isNext ? '#141210' : '#fff';
    var accent = isNext ? { fb: '#141210', ok: '#141210' } : { fb: GOLD_FB, ok: GOLD };
    var muted = isNext ? 'rgba(20,18,16,0.65)' : 'rgba(232,230,224,0.65)';

    if (isNext) {
      // GET READY breathes by STRETCHING its word, not by animating
      // letter-spacing — tracking is a layout property, and re-laying-out text
      // sixty times a second is the one animation on this board that reached
      // past paint into layout. The plate stays the size it was; the span
      // inside it widens, which from ten metres is the same picture.
      var ready = el('div', 'font-weight:800; font-size:26px; letter-spacing:0.2em; text-transform:uppercase; background:#141210;'
        + dual('color', GOLD_FB, GOLD) + 'padding:3px 18px 3px 20px; margin-bottom:2px; overflow:hidden;');
      ready.appendChild(el('span', 'display:inline-block; animation:readyBreath 1.6s ease-in-out infinite;',
        @json(__('scoreboard::karate_messages.court_get_ready'))));
      p.appendChild(ready);
    }

    // "MATCH 12" — the label is only meaningful next to a number, so the whole
    // line steps aside for a bout that has not been given one yet.
    var line = el('div', 'display:flex; align-items:baseline; gap:8px;');
    line.appendChild(el('span', 'font-weight:600; font-size:26px; letter-spacing:0.24em; text-transform:uppercase; color:' + muted + ';', @json(__('scoreboard::karate_messages.court_match'))));
    var num = el('span', "font-family:'Anton',sans-serif; font-size:54px; line-height:1;" + dual('color', accent.fb, accent.ok) + (isNext ? ' animation:numBeat 1.6s ease-in-out infinite;' : ''));
    show(line, textOrHide(num, m.number));
    line.appendChild(num);
    p.appendChild(line);

    var stage = el('div', 'font-weight:800; font-size:30px; letter-spacing:0.16em; text-transform:uppercase; color:' + text + ';');
    textOrHide(stage, m.stage);
    p.appendChild(stage);

    var weight = el('div', 'font-weight:600; font-size:26px; letter-spacing:0.14em; text-transform:uppercase; color:' + muted + ';');
    textOrHide(weight, m.weightClass);
    p.appendChild(weight);

    return p;
  }

  /**
   * One bout row.
   *
   * `enter` is false for a bout that was already on screen before this update.
   * When a bout finishes the rows shift up, and re-playing the entrance on all
   * four would read as the whole board flinching — only genuinely new rows fly in.
   */
  function row(m, i, isNext, enter) {
    var anim = [];
    if (enter) anim.push((i % 2 === 0 ? 'rowEnterL' : 'rowEnterR') + ' 0.7s ' + (0.2 + i * 0.15) + 's cubic-bezier(0.22,1,0.36,1) both');

    var r = el('div', 'flex:1; min-height:0; position:relative; display:flex; align-items:stretch;' +
      (isNext ? dual('border', '1px solid rgba(253,196,54,0.8)', '1px solid oklch(0.85 0.16 85 / 0.8)')
              : 'border:1px solid rgba(255,255,255,0.15);') + 'background:#101016;' +
      // The quiet half of the breath, as a plain static shadow. The loud half
      // is the layer below, faded over the top of it.
      (isNext ? dual('box-shadow',
                     '0 0 16px rgba(253,196,54,0.35), 0 0 44px rgba(253,196,54,0.15)',
                     '0 0 16px oklch(0.85 0.16 85 / 0.35), 0 0 44px oklch(0.85 0.16 85 / 0.15)') : '') +
      (anim.length ? ' animation:' + anim.join(', ') + ';' : ''));

    var sweepWrap = el('div', 'position:absolute; inset:0; overflow:hidden; pointer-events:none; z-index:2;');
    sweepWrap.appendChild(el('div', 'position:absolute; top:0; bottom:0; width:26%; background:linear-gradient(to right, transparent, rgba(255,255,255,0.16), transparent); animation:rowSweep 4.5s ' + (1.2 + i * 0.55) + 's ease-in-out infinite;'));
    r.appendChild(sweepWrap);

    if (isNext) {
      // The breath: the strong shadow rasterised ONCE and faded in and out,
      // rather than a 110px blur re-drawn around a full-width row every frame.
      // Behind the row's own background (z-index:-1), so what is seen is the
      // glow spilling out past its edges — exactly as before.
      r.appendChild(el('div', 'position:absolute; inset:0; z-index:-1; pointer-events:none; opacity:0;' +
        dual('box-shadow',
             '0 0 36px rgba(253,196,54,0.8), 0 0 110px rgba(253,196,54,0.35)',
             '0 0 36px oklch(0.85 0.16 85 / 0.8), 0 0 110px oklch(0.85 0.16 85 / 0.35)') +
        'animation:glowPulse 2.2s 1.2s ease-in-out infinite;'));

      // The running border, as four thin strips rather than one masked box.
      //
      // The obvious composited version — a gradient three rows wide, slid
      // behind the border-ring mask — was measurably WORSE than the paint it
      // replaced: a mask forces a render surface, so every frame re-rendered a
      // group the size of three full rows. These strips are the same picture
      // with none of that: the light runs along two bands 3px tall, and the
      // ends are the flat gold the gradient shows there anyway.
      var ring = el('div', 'position:absolute; inset:-2px; pointer-events:none; z-index:2;');
      var GRAD = dual('background',
        'linear-gradient(90deg, ' + GOLD_FB + ', #fffdf0 25%, ' + GOLD_FB + ' 50%, #6b5310 75%, ' + GOLD_FB + ')',
        'linear-gradient(90deg, ' + GOLD + ', #fffdf0 25%, ' + GOLD + ' 50%, #6b5310 75%, ' + GOLD + ')');

      ['top:0;', 'bottom:0;'].forEach(function (edge) {
        var band = el('div', 'position:absolute; left:0; right:0; ' + edge + ' height:3px; overflow:hidden;');
        // The gradient repeats every 50% of its own width (gold at 0, 50 and
        // 100), so a -50% slide loops with no seam.
        band.appendChild(el('div', 'position:absolute; top:0; bottom:0; left:0; width:300%;' + GRAD +
          'animation:goldSlide 2.5s linear infinite;'));
        ring.appendChild(band);
      });
      ['left:0;', 'right:0;'].forEach(function (edge) {
        ring.appendChild(el('div', 'position:absolute; top:0; bottom:0; ' + edge + ' width:3px;' + dual('background', GOLD_FB, GOLD)));
      });
      r.appendChild(ring);
    }

    r.appendChild(half(m, 'red', true));
    r.appendChild(half(m, 'blue', false));

    var pl = plate(m, isNext);
    if (enter) pl.style.animation = 'platePop 0.55s ' + (0.55 + i * 0.15) + 's cubic-bezier(0.22,1,0.36,1) both';
    r.appendChild(pl);
    return r;
  }

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
  }, 5000);
@endisset
})();
</script>

@isset($screenLink)
@include('scoreboard::karate.screen.partials.screen-link')
@endisset
</body>
</html>
