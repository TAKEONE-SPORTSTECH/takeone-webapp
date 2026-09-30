{{--
  The "up next" rows — ONE design for every combat sport's hall screen.

  Built from the Karate board, which is the approved layout; Taekwondo and
  Brazilian Jiu-Jitsu draw the same rows. What differs per sport is handed in,
  never branched on here:
    sides     [{key, tone}, {key, tone}] — left then right. `key` is the corner's
              prefix in the payload (red/blue, or blue/white in BJJ); `tone`
              is the colour it is drawn in. From App\Sports\Combat\CornerSide.
    tbd, getReady, match   the sport's own words.

  UpNext.row(m, i, isNext, enter, opts) builds one row; UpNext.fit(host) clears
  each half of the centre plate and fits the text (partials/fit-text). The page
  keeps its own render loop, header and heartbeat.
--}}
@include('scoreboard::partials.fit-text')
@once
<style>
  @keyframes rowEnterL { from { opacity: 0; transform: translateX(-120px) skewX(-6deg); } to { opacity: 1; transform: translateX(0) skewX(0); } }
  @keyframes rowEnterR { from { opacity: 0; transform: translateX(120px) skewX(6deg); } to { opacity: 1; transform: translateX(0) skewX(0); } }
  @keyframes platePop { 0% { opacity: 0; transform: translate(-50%,-50%) scale(0.4); } 70% { opacity: 1; transform: translate(-50%,-50%) scale(1.12); } 100% { opacity: 1; transform: translate(-50%,-50%) scale(1); } }
  @keyframes rowSweep { 0% { transform: translateX(-140%) skewX(-22deg); } 45%, 100% { transform: translateX(320%) skewX(-22deg); } }
  @keyframes goldSlide { from { transform: translateX(0); } to { transform: translateX(-50%); } }
  @keyframes glowPulse { 0%, 100% { opacity: 0; } 50% { opacity: 1; } }
  @keyframes readyBreath { 0%, 100% { transform: scaleX(1); opacity: 1; } 50% { transform: scaleX(1.08); opacity: 0.75; } }
  @keyframes numBeat { 0%,100% { transform: scale(1); } 50% { transform: scale(1.14); } }
</style>
<script>
(function () {
  'use strict';
  if (window.UpNext) return;
  var O = { sides: [{ key: 'red', tone: 'red' }, { key: 'blue', tone: 'blue' }], tbd: '', getReady: '', match: '' };

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

  // Our own files, served from this box — a hall with no uplink is the
  // ordinary case (CLAUDE.md -> Flags Come From One Local Source).
  function flagUrl(code) {
    return /^[a-z]{2}$/.test(String(code || '')) ? '/images/flags/' + code + '.svg' : null;
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
  function half(m, corner, red, left) {
    var tone = red ? RED_TONE : BLUE_TONE;
    var wrap = el('div', 'flex:1; min-width:0; display:flex; align-items:stretch;' +
      // The corner's colour, running from the row's outer edge inward.
      (red
        ? dual('background', 'linear-gradient(' + (left ? 90 : 270) + 'deg, #a21921 0%, #551112 100%)',
                             'linear-gradient(' + (left ? 90 : 270) + 'deg, oklch(0.46 0.17 25) 0%, oklch(0.3 0.1 25) 100%)')
        : dual('background', 'linear-gradient(' + (left ? 90 : 270) + 'deg, #004b97 0%, #012854 100%)',
                             'linear-gradient(' + (left ? 90 : 270) + 'deg, oklch(0.42 0.14 255) 0%, oklch(0.28 0.09 255) 100%)')) +
      (left
        ? 'clip-path:polygon(0 0, 100% 0, calc(100% - 60px) 100%, 0 100%); margin-right:-30px;'
        : 'flex-direction:row-reverse; clip-path:polygon(60px 0, 100% 0, 100% 100%, 0 100%); margin-left:-30px;'));

    // Competitor portrait. Hidden when the athlete has no published picture —
    // the corner's own tone still carries the half, and the name gets the room.
    // ⚠️ The box is the PICTURE'S OWN SHAPE, not a fixed width — see the note
    // in the BJJ board. Every profile picture here is portrait 3:4 (stored
    // 600x800), so a 230px-wide box at row height is landscape and `cover`
    // ate the top and bottom of the crop the member chose.
    var photo = el('div', 'align-self:stretch; aspect-ratio:3/4; width:auto; flex:0 0 auto;' + dual('background-color', tone.fb, tone.ok) +
      'background-size:cover; background-position:center 12%;');
    // Their own face, and the drawn stand-in when there is none — the same
    // rule as the introduction panel. The stand-in sits well back, so nobody
    // reads it as a photograph of the athlete about to walk on.
    var face = m[corner + 'Photo'] || m[corner + 'Fallback'];
    bgOrHide(photo, face);
    photo.style.opacity = (face && !m[corner + 'Photo']) ? '.45' : '1';
    wrap.appendChild(photo);

    var col = el('div', 'flex:1; min-width:0; display:flex; flex-direction:column; justify-content:center; gap:6px;' +
      (left ? 'padding:10px 60px 10px 26px;' : 'align-items:flex-end; padding:10px 26px 10px 60px; text-align:right;'));
    col.setAttribute('data-q-col', left ? 'l' : 'r');

    // The country goes ABOVE the name: flag, then the country spelled out.
    // A reader scanning four rows from the back of a hall finds the nation
    // first and the person second, which is the order a competition is read
    // in. The plate below the name keeps the club, which is the smaller claim.
    //
    // The flag is smaller here than it was in the club strip — it sits over a
    // 56px name rather than beside a 30px club, and a 76px plate above the
    // name out-shouted it.
    var nat = el('div', 'display:flex; align-items:center; gap:12px; max-width:100%;' + (left ? '' : 'flex-direction:row-reverse;'));

    // Stretched to fill the box — `100% 100%`, not `cover` and not `contain`.
    // National flags run from 1.43:1 to 2:1 while this box is 4:3, so the three
    // options are crop (which ate a third off a 2:1 flag), letterbox (grey
    // bands), or stretch — which keeps every flag WHOLE and fills the plate.
    var flag = el('div', 'width:58px; flex:0 0 auto; aspect-ratio:4/3; background-color:rgba(0,0,0,0.25); background-size:100% 100%; image-rendering:auto; background-repeat:no-repeat; background-position:center; border:1px solid rgba(255,255,255,0.4);');
    var hasFlag = bgOrHide(flag, flagUrl(m[corner + 'Flag']));
    nat.appendChild(flag);

    var country = el('div', 'min-width:0; font-weight:700; font-size:26px; letter-spacing:0.14em; text-transform:uppercase; color:rgba(255,255,255,0.85); white-space:nowrap; overflow:hidden;');
    country.setAttribute('data-fit', '26:18');
    var hasCountry = textOrHide(country, m[corner + 'Country']);
    nat.appendChild(country);

    // Neither one — the line steps aside rather than leaving its gap over the
    // name. Same rule as the club strip below.
    show(nat, hasFlag || hasCountry);
    col.appendChild(nat);

    // The one field that never hides: a nameless half looks broken from ten
    // metres, so an undrawn slot says so instead.
    var nameEl = el('div', "font-family:'Anton',sans-serif; font-size:56px; line-height:1; text-transform:uppercase; color:#fff; white-space:nowrap; overflow:hidden; max-width:100%;", m[corner + 'Name'] || O.tbd);
    // Fitted, never cut — see partials/fit-text.
    nameEl.setAttribute('data-fit', '56:22');
    col.appendChild(nameEl);

    var meta = el('div', 'display:flex; align-items:center; gap:12px; max-width:100%;' + (left ? '' : 'flex-direction:row-reverse;'));

    var crest = el('div', 'width:66px; height:66px; flex:0 0 auto; border-radius:50%; background-color:rgba(255,255,255,0.12); background-size:cover; background-position:center; border:1px solid rgba(255,255,255,0.35);');
    var hasCrest = bgOrHide(crest, m[corner + 'Logo']);
    meta.appendChild(crest);

    var club = el('div', 'min-width:0; font-weight:600; font-size:30px; letter-spacing:0.06em; text-transform:uppercase; color:rgba(255,255,255,0.85); white-space:nowrap; overflow:hidden;');
    club.setAttribute('data-fit', '30:20');
    var hasClub = textOrHide(club, m[corner + 'Club']);
    meta.appendChild(club);

    // With crest and club both absent the strip is an empty flex row that
    // would still eat its gap under the name — so the row itself steps aside.
    show(meta, hasCrest || hasClub);

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
        O.getReady));
      p.appendChild(ready);
    }

    // "MATCH 12" — the label is only meaningful next to a number, so the whole
    // line steps aside for a bout that has not been given one yet.
    var line = el('div', 'display:flex; align-items:baseline; gap:8px;');
    line.appendChild(el('span', 'font-weight:600; font-size:26px; letter-spacing:0.24em; text-transform:uppercase; color:' + muted + ';', O.match));
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
  function row(m, i, isNext, enter, opts) {
    O = opts || O;
    /* ⚠️ MOVEMENT BELONGS TO THE FIRST ROW ONLY (2026-09-20).

       Four rows each carrying an entrance, and a shine crossing all four on
       four staggered clocks, meant something was always moving somewhere on
       the wall — and a reader looking for the bout that is NEXT had nothing to
       look at, because everything was equally alive. The gold ring, the breath
       and the beating number on row 0 were competing with three rows of
       decoration rather than standing out from them.

       So the queue below the first row is STILL. Row 0 keeps everything it
       had; rows 1-3 draw and stay drawn. Nothing is removed — it is scoped,
       which is what makes the top row read as the top row. */
    var anim = [];
    if (enter && isNext) anim.push('rowEnterL 0.7s 0.2s cubic-bezier(0.22,1,0.36,1) both');

    /* ── Rounded corners, and the two layers that make them possible ───────
       Asked for 2026-09-20. A radius alone would not have shown: the two
       halves are CLIP-PATH polygons, which replace the border box entirely,
       so their outer corners stay square however the row is rounded. They
       have to be clipped by an ancestor.

       But that ancestor cannot be the row itself, because `overflow:hidden`
       clips a CHILD'S box-shadow — and the breath on row 0 is exactly that: a
       child whose 110px glow is meant to spread OUTSIDE the row. Clipping it
       would delete the thing that marks the next bout.

       So the row splits in two. `r` is unclipped and carries everything that
       belongs outside the edge (the glow, the running border); `body` is the
       rounded, clipped box and carries everything that belongs inside it. */
    var R = 16;

    var r = el('div', 'flex:1; min-height:0; position:relative; border-radius:' + R + 'px;' +
      // The quiet half of the breath, as a plain static shadow. The loud half
      // is the layer below, faded over the top of it. An element's OWN shadow
      // is not clipped by its own overflow, so this one is safe here.
      (isNext ? dual('box-shadow',
                     '0 0 16px rgba(253,196,54,0.35), 0 0 44px rgba(253,196,54,0.15)',
                     '0 0 16px oklch(0.85 0.16 85 / 0.35), 0 0 44px oklch(0.85 0.16 85 / 0.15)') : '') +
      (anim.length ? ' animation:' + anim.join(', ') + ';' : ''));

    if (isNext) {
      // The breath: the strong shadow rasterised ONCE and faded in and out,
      // rather than a 110px blur re-drawn around a full-width row every frame.
      // Behind the row's own background (z-index:-1), so what is seen is the
      // glow spilling out past its edges — exactly as before.
      r.appendChild(el('div', 'position:absolute; inset:0; z-index:-1; pointer-events:none; opacity:0; border-radius:' + R + 'px;' +
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
      /* One rounded plate carrying the sliding gradient, with the body laid
         over it 2px in. Four straight strips cannot follow a curve — rounding
         them left a notch on every corner — and two stacked rounded boxes
         still need no mask, which was the whole reason for the strips. */
      var ring = el('div', 'position:absolute; inset:-2px; border-radius:' + (R + 2) + 'px; overflow:hidden; pointer-events:none; z-index:0;');
      var GRAD = dual('background',
        'linear-gradient(90deg, ' + GOLD_FB + ', #fffdf0 25%, ' + GOLD_FB + ' 50%, #6b5310 75%, ' + GOLD_FB + ')',
        'linear-gradient(90deg, ' + GOLD + ', #fffdf0 25%, ' + GOLD + ' 50%, #6b5310 75%, ' + GOLD + ')');

      // The gradient repeats every 50% of its own width (gold at 0, 50 and
      // 100), so a -50% slide loops with no seam.
      ring.appendChild(el('div', 'position:absolute; top:0; bottom:0; left:0; width:300%;' + GRAD +
        'animation:goldSlide 2.5s linear infinite;'));
      r.appendChild(ring);
    }

    // Everything that must be clipped to the rounded shape lives in here.
    var body = el('div', 'position:absolute; inset:0; display:flex; align-items:stretch; overflow:hidden;' +
      'border-radius:' + R + 'px; background:#101016; z-index:1;' +
      (isNext ? '' : 'border:1px solid rgba(255,255,255,0.15);'));
    r.appendChild(body);

    // The travelling shine, on the next bout and nowhere else.
    if (isNext) {
      var sweepWrap = el('div', 'position:absolute; inset:0; overflow:hidden; pointer-events:none; z-index:2;');
      sweepWrap.appendChild(el('div', 'position:absolute; top:0; bottom:0; width:26%; background:linear-gradient(to right, transparent, rgba(255,255,255,0.16), transparent); animation:rowSweep 4.5s 1.2s ease-in-out infinite;'));
      body.appendChild(sweepWrap);
    }

    // Which corner stands on which side is the SPORT's convention
    // (App\\Sports\\Combat\\CornerSide), handed in as O.sides.
    body.appendChild(half(m, O.sides[0].key, O.sides[0].tone === 'red', true));
    body.appendChild(half(m, O.sides[1].key, O.sides[1].tone === 'red', false));

    var pl = plate(m, isNext);
    pl.setAttribute('data-q-plate', '');
    if (enter) pl.style.animation = 'platePop 0.55s ' + (0.55 + i * 0.15) + 's cubic-bezier(0.22,1,0.36,1) both';
    body.appendChild(pl);
    return r;
  }


  /* Up-next rows: clear each half of the centre plate, then fit every line
     to what is left. See partials/fit-text — never an ellipsis on a name. */
  function fitQueue(host) {
    if (!window.ScreenFit || !host) return;
    Array.prototype.forEach.call(host.querySelectorAll('[data-q-plate]'), function (p) {
      var r = p.parentNode;
      ScreenFit.plate(p, r.querySelector('[data-q-col="l"]'), r.querySelector('[data-q-col="r"]'), 30, 18);
    });
    ScreenFit.all(host);
  }


  window.UpNext = { row: row, fit: fitQueue, show: show, textOrHide: textOrHide, bgOrHide: bgOrHide, el: el };
})();
</script>
@endonce
