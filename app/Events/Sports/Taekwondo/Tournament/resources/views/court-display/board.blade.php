{{--
    The hall board for one mat.

    This is the ONLY screen in the product with no navigation, no chrome and no
    viewer — it is a wall, seen from ten metres by people holding coffee. So it
    breaks house style deliberately and on purpose:

      · It does not extend a layout. The Pi renders it as a whole document under
        cog/WPE with nothing else on the machine, and the agent caches this exact
        file to disk so the board is on screen before the network exists.
      · Its palette is the broadcast red/blue of a competition mat, not the app's
        purple. Design System tokens describe a product UI; this is signage.
      · Its markup and CSS are transcribed verbatim from the approved layout at
        draft/sample files/. Treat the visual output as fixed — restyle by
        agreement, never as a side effect.

    The stage is authored at 1920x1080 and scaled by --stage-scale, so the same
    file is correct on a 4K panel and on a Pi 3B rendering at 720p. Change the
    output resolution, never the stage.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ __('event-taekwondo_tournament::messages.court_title') }}</title>

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
       Pi's cached copy, a LAN address at a venue) would ask a machine that
       isn't there, get nothing, and silently fall back to a system sans — which
       collapses the whole layout, because the typography IS the design. --}}
  src: url("{{ route('court-display.font', $slug.'-'.$subset.'.woff2', false) }}") format('woff2');
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
  @keyframes goldRun { 0% { background-position: 0% 50%; } 100% { background-position: 300% 50%; } }
  @keyframes nextBreathe { 0%,100% { box-shadow: 0 0 16px oklch(0.85 0.16 85 / 0.35), 0 0 44px oklch(0.85 0.16 85 / 0.15); } 50% { box-shadow: 0 0 36px oklch(0.85 0.16 85 / 0.8), 0 0 110px oklch(0.85 0.16 85 / 0.35); } }
  @keyframes titleShimmer { 0% { background-position: -200% 50%; } 100% { background-position: 300% 50%; } }
  @keyframes readyTrack { 0%,100% { letter-spacing: 0.2em; opacity: 1; } 50% { letter-spacing: 0.34em; opacity: 0.75; } }
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
  #stale .dot { width: 12px; height: 12px; border-radius: 50%; background: oklch(0.72 0.19 55); animation: numBeat 1.6s ease-in-out infinite; }

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
        <div style="width:10px; height:64px; background:oklch(0.62 0.21 25);"></div>
        <div id="eventTitle" style="font-weight:700; font-size:34px; letter-spacing:0.24em; text-transform:uppercase; color:rgba(232,230,224,0.85); max-width:520px;"></div>
      </div>
      <div style="display:flex; flex-direction:column; align-items:center; gap:4px;">
        <div style="font-family:'Anton',sans-serif; font-size:64px; letter-spacing:0.2em; padding-left:0.2em; text-transform:uppercase; white-space:nowrap; background:linear-gradient(100deg, oklch(0.85 0.16 85) 40%, #fffdf0 50%, oklch(0.85 0.16 85) 60%); background-size:200% 100%; -webkit-background-clip:text; background-clip:text; -webkit-text-fill-color:transparent; animation:titleShimmer 3.5s linear infinite;">{{ __('event-taekwondo_tournament::messages.court_title') }}</div>
        <div style="height:3px; width:100%; background:linear-gradient(to right, transparent, oklch(0.85 0.16 85), transparent);"></div>
      </div>
      <div style="flex:1; display:flex; justify-content:flex-end; align-items:center; gap:18px;">
        <div id="courtBadge" style="display:flex; align-items:baseline; gap:12px; background:rgba(10,10,14,0.72); border:1px solid oklch(0.85 0.16 85 / 0.5); padding:10px 28px;">
          <span style="font-weight:600; font-size:32px; letter-spacing:0.3em; color:rgba(232,230,224,0.65); text-transform:uppercase;">{{ __('event-taekwondo_tournament::messages.court_court') }}</span>
          <span id="courtNumber" style="font-family:'Anton',sans-serif; font-size:52px; color:#fff;"></span>
        </div>
        <div style="width:10px; height:64px; background:oklch(0.55 0.18 255);"></div>
      </div>
    </div>

    <div id="rows"></div>

    <div id="stale"><span class="dot"></span><span>{{ __('event-taekwondo_tournament::messages.court_reconnecting') }}</span></div>
  </div>
</div>

<script>
(function () {
  'use strict';

  var ROWS = @json(\App\Events\Sports\Taekwondo\Tournament\CourtDisplay\CourtDisplay::ROWS);
  var TBD = @json(__('event-taekwondo_tournament::messages.court_tbd'));
  var IDLE_T = @json(__('event-taekwondo_tournament::messages.court_idle_title'));
  var IDLE_S = @json(__('event-taekwondo_tournament::messages.court_idle_sub'));

  var root = document.getElementById('root');
  var rowsEl = document.getElementById('rows');
  var staleEl = document.getElementById('stale');

  // ── Stage scaling ────────────────────────────────────────────────────────
  // The layout is authored at 1920x1080 and never reflows; it only scales. That
  // is what lets a Pi 3B render at 720p to save fill rate while staying pixel-
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

  // TODO(offline): flags come from flagcdn. Harmless on 4G, but the Pi agent
  // should mirror them to disk so a dead uplink never empties the flag boxes.
  function flagUrl(code) {
    return /^[a-z]{2}$/.test(String(code || '')) ? 'https://flagcdn.com/w320/' + code + '.png' : null;
  }

  var RED_TONE = 'oklch(0.44 0.14 25)', BLUE_TONE = 'oklch(0.4 0.12 255)';

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
        ? 'background:linear-gradient(90deg, oklch(0.46 0.17 25) 0%, oklch(0.3 0.1 25) 100%); clip-path:polygon(0 0, 100% 0, calc(100% - 60px) 100%, 0 100%); margin-right:-30px;'
        : 'flex-direction:row-reverse; background:linear-gradient(270deg, oklch(0.42 0.14 255) 0%, oklch(0.28 0.09 255) 100%); clip-path:polygon(60px 0, 100% 0, 100% 100%, 0 100%); margin-left:-30px;'));

    // Competitor portrait. Hidden when the athlete has no published picture —
    // the corner's own tone still carries the half, and the name gets the room.
    var photo = el('div', 'width:230px; flex:0 0 auto; background-color:' + tone + '; background-size:cover; background-position:center 12%;');
    bgOrHide(photo, m[corner + 'Photo']);
    wrap.appendChild(photo);

    var col = el('div', 'flex:1; min-width:0; display:flex; flex-direction:column; justify-content:center; gap:6px;' +
      (isRed ? 'padding:10px 180px 10px 26px;' : 'align-items:flex-end; padding:10px 26px 10px 180px; text-align:right;'));

    // The one field that never hides: a nameless half looks broken from ten
    // metres, so an undrawn slot says so instead.
    col.appendChild(el('div', "font-family:'Anton',sans-serif; font-size:56px; line-height:1; text-transform:uppercase; color:#fff; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;", m[corner + 'Name'] || TBD));

    var meta = el('div', 'display:flex; align-items:center; gap:12px; max-width:100%;' + (isRed ? '' : 'flex-direction:row-reverse;'));

    // `contain`, never `cover`. National flags run from 1.43:1 (Brazil) to 2:1
    // (Jordan) while this box is 4:3, so cover crops a third off EACH side of a
    // 2:1 flag — which is exactly where the hoist device lives. It ate Jordan's
    // chevron and star, and Oman's emblem, leaving both as anonymous stripes.
    // The letterbox band is the honest trade: a flag is either whole or wrong.
    var flag = el('div', 'width:76px; flex:0 0 auto; aspect-ratio:4/3; background-color:rgba(0,0,0,0.25); background-size:contain; background-repeat:no-repeat; background-position:center; border:1px solid rgba(255,255,255,0.4);');
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
      'background:' + (isNext ? 'oklch(0.85 0.16 85)' : '#101016') + '; border:2px solid ' + (isNext ? '#fffdf0' : 'rgba(255,255,255,0.3)') + ';' +
      'padding:10px 26px 12px; min-width:220px; box-shadow:0 0 30px rgba(0,0,0,0.7); z-index:3;');

    var text = isNext ? '#141210' : '#fff';
    var accent = isNext ? '#141210' : 'oklch(0.85 0.16 85)';
    var muted = isNext ? 'rgba(20,18,16,0.65)' : 'rgba(232,230,224,0.65)';

    if (isNext) {
      p.appendChild(el('div', 'font-weight:800; font-size:26px; letter-spacing:0.2em; text-transform:uppercase; background:#141210; color:oklch(0.85 0.16 85); padding:3px 18px 3px 20px; margin-bottom:2px; animation:readyTrack 1.6s ease-in-out infinite;', @json(__('event-taekwondo_tournament::messages.court_get_ready'))));
    }

    // "MATCH 12" — the label is only meaningful next to a number, so the whole
    // line steps aside for a bout that has not been given one yet.
    var line = el('div', 'display:flex; align-items:baseline; gap:8px;');
    line.appendChild(el('span', 'font-weight:600; font-size:26px; letter-spacing:0.24em; text-transform:uppercase; color:' + muted + ';', @json(__('event-taekwondo_tournament::messages.court_match'))));
    var num = el('span', "font-family:'Anton',sans-serif; font-size:54px; line-height:1; color:" + accent + ';' + (isNext ? ' animation:numBeat 1.6s ease-in-out infinite;' : ''));
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
    if (isNext) anim.push('nextBreathe 2.2s 1.2s ease-in-out infinite');

    var r = el('div', 'flex:1; min-height:0; position:relative; display:flex; align-items:stretch;' +
      'border:1px solid ' + (isNext ? 'oklch(0.85 0.16 85 / 0.8)' : 'rgba(255,255,255,0.15)') + '; background:#101016;' +
      (anim.length ? ' animation:' + anim.join(', ') + ';' : ''));

    var sweepWrap = el('div', 'position:absolute; inset:0; overflow:hidden; pointer-events:none; z-index:2;');
    sweepWrap.appendChild(el('div', 'position:absolute; top:0; bottom:0; width:26%; background:linear-gradient(to right, transparent, rgba(255,255,255,0.16), transparent); animation:rowSweep 4.5s ' + (1.2 + i * 0.55) + 's ease-in-out infinite;'));
    r.appendChild(sweepWrap);

    if (isNext) {
      r.appendChild(el('div', 'position:absolute; inset:-2px; pointer-events:none; z-index:2; border:3px solid transparent;' +
        'background:linear-gradient(90deg, oklch(0.85 0.16 85), #fffdf0 25%, oklch(0.85 0.16 85) 50%, #6b5310 75%, oklch(0.85 0.16 85)) border-box; background-size:300% 100%;' +
        '-webkit-mask:linear-gradient(#fff 0 0) padding-box, linear-gradient(#fff 0 0); -webkit-mask-composite:xor;' +
        'mask:linear-gradient(#fff 0 0) padding-box exclude, linear-gradient(#fff 0 0); animation:goldRun 2.5s linear infinite;'));
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

  function keyOf(m) { return String(m.number) + '|' + (m.redName || '') + '|' + (m.blueName || ''); }

  function render(payload) {
    if (!payload || typeof payload !== 'object') return;

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
   * Phase 1 injects the payload server-side. The Pi agent will later call
   * update() on each retained MQTT message and stale() when the broker drops,
   * against this same file cached to disk — so the transport can change without
   * this view changing at all.
   */
  window.CourtBoard = {
    update: render,
    stale: function (on) { staleEl.classList.toggle('on', !!on); }
  };

  render(@json($payload));
})();
</script>
</body>
</html>
