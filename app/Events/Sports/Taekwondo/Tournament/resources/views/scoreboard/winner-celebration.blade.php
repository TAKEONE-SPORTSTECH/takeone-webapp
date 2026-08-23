{{--
    The winner celebration — this sport's own.

    A bout ends and the hall gets this: the athlete's own photograph lit from the
    corner they fought from, their name slammed across the wall, their club named
    under it, embers and confetti in the corner's colours. Included by this
    package's mat board and its scoring console.

    Transcribed from the approved layouts in ../../../Scoreboard/design/winner-{red,blue}.source.html:
    RED puts the photograph on the right and the name on the left, BLUE mirrors
    it. Treat the visual output as fixed and restyle by agreement, never as a
    side effect.

    It belongs to Taekwondo. The Karate package has its own copy of this file
    and its own copy of the layouts behind it — deliberately, because an event
    type is a package: deleting this directory must take its winner screen with
    it, and a change to one sport's celebration must never move another sport's.
    Its faces come from THIS package's font route, so the two are not even
    reading the same files.

    Standalone by contract. It owns its faces, its keyframes and its whole scene,
    it measures its own host rather than reading the page, and it decides nothing
    — the caller hands it a winner and gets back the one element it may put its
    own buttons in.

        @include('event-taekwondo_tournament::scoreboard.winner-celebration')

        var scene = WinnerCelebration.paint(hostEl, {
            corner: 'red',            // 'red' | 'blue' — which corner won
            name:   'NOOR ALI',
            club:   'MANAMA TAEKWONDO',
            logo:   '/storage/…png',  // optional; a monogram stands in
            photo:  '/storage/…webp', // optional; the card is dropped without one
            note:   'WINS 2 - 1 ON ROUNDS', // optional line under the bar
            label:  'Winner',         // the localised word
        });
        scene.actions.appendChild(myCommitButton);   // console only

    Nothing is invented: an absent photo, logo or note is absent from the scene,
    never a placeholder. Every value is written with textContent / a validated
    URL — a competitor's name and their club's name are organiser input and reach
    a hall screen as text, never as markup.
--}}
@once('taekwondo-winner-celebration-styles')
<style>
@php
    // Poppins italic is the design's voice. Self-hosted, per subset, because a
    // wall screen on a mat has no internet to ask Google for a font.
    $wcFaces = [600, 700];
    $wcRanges = [
        'latin' => 'U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD',
        'latin-ext' => 'U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF',
    ];
@endphp
@foreach ($wcFaces as $wcWeight)
@foreach ($wcRanges as $wcSubset => $wcRange)
@font-face {
  font-family: 'Poppins';
  font-style: italic;
  font-weight: {{ $wcWeight }};
  font-display: swap;
  {{-- Root-relative: the board must never ask an origin that isn't there. --}}
  src: url("{{ route('court-display.font', 'poppins-italic-'.$wcWeight.'-'.$wcSubset.'.woff2', false) }}") format('woff2');
  unicode-range: {{ $wcRange }};
}
@endforeach
@endforeach

/* Every loop the scene runs. Namespaced `wc` so a board's own keyframes — and
   there are several, with names as ordinary as `shine` — can never collide. */
@keyframes wcNameSlam { 0% { transform:scale(3); opacity:0; filter:blur(8px); } 55% { transform:scale(.96); opacity:1; filter:blur(0); } 75% { transform:scale(1.02); } 100% { transform:scale(1); } }
@keyframes wcRiseIn { from { transform:translateY(26px); opacity:0; } to { transform:translateY(0); opacity:1; } }
@keyframes wcSlideRight { from { transform:translateX(60px); opacity:0; } to { transform:translateX(0); opacity:1; } }
@keyframes wcSlideLeft { from { transform:translateX(-60px); opacity:0; } to { transform:translateX(0); opacity:1; } }
@keyframes wcShakeHit { 0%,100% { transform:translate(0,0); } 15% { transform:translate(-14px,6px); } 30% { transform:translate(12px,-8px); } 45% { transform:translate(-8px,-4px); } 60% { transform:translate(7px,5px); } 75% { transform:translate(-4px,2px); } }
@keyframes wcKenBurns { from { transform:scale(1); } to { transform:scale(1.08); } }
@keyframes wcTextGlow { 0%,100% { text-shadow:.055em .055em 0 var(--wcA), 0 0 25px rgba(255,255,255,.12); } 50% { text-shadow:.055em .055em 0 var(--wcA), 0 0 70px rgba(255,255,255,.45); } }
@keyframes wcBeamMove { 0% { transform:translateX(calc(var(--wcW) * -0.4)) skewX(-20deg); } 100% { transform:translateX(calc(var(--wcW) * 1.4)) skewX(-20deg); } }
@keyframes wcBarGrow { from { width:0; opacity:0; } to { width:100%; opacity:1; } }
@keyframes wcShine { 0% { transform:translateX(-160%) skewX(-18deg); } 55%,100% { transform:translateX(280%) skewX(-18deg); } }
@keyframes wcSpotPulse { 0%,100% { opacity:.4; } 50% { opacity:.75; } }
@keyframes wcEmberFlicker { 0%,100% { filter:brightness(.9) blur(.4px); } 50% { filter:brightness(1.9) blur(0); } }
@keyframes wcFlameDance { 0% { transform:scaleY(.8) scaleX(1.05) translateY(calc(var(--wcH) * 0.02)); } 100% { transform:scaleY(1.2) scaleX(.88) translateY(calc(var(--wcH) * -0.03)); } }
@keyframes wcFlashIn { 0% { opacity:1; } 100% { opacity:0; } }
/* Stage-relative, not viewport-relative: a wall board draws this inside a 1920
   canvas that is SCALED to the panel, so vh here would fall the wrong distance. */
@keyframes wcConfetti {
  0% { transform:translateY(calc(var(--wcH) * -0.12)) translateX(0) rotate3d(1,1,.3,0deg); }
  8% { opacity:1; }
  25% { transform:translateY(calc(var(--wcH) * 0.18)) translateX(calc(var(--wcW) * 0.03)) rotate3d(1,1,.3,200deg); }
  50% { transform:translateY(calc(var(--wcH) * 0.48)) translateX(calc(var(--wcW) * -0.025)) rotate3d(1,1,.3,400deg); }
  75% { transform:translateY(calc(var(--wcH) * 0.80)) translateX(calc(var(--wcW) * 0.03)) rotate3d(1,1,.3,600deg); }
  100% { transform:translateY(calc(var(--wcH) * 1.12)) translateX(calc(var(--wcW) * -0.015)) rotate3d(1,1,.3,800deg); opacity:.85; }
}

/* A hall screen never asks for less motion, but a console is a browser on
   somebody's desk and this respects them. The scene still reads: the photo, the
   name, the club and the colour are all in the first frame. */
@media (prefers-reduced-motion: reduce) {
  .wc-stage, .wc-stage * { animation: none !important; }
}
</style>
@endonce

@once('taekwondo-winner-celebration-script')
<script>
/* The celebration, painted into whatever host it is handed. Defined once per
   document and never re-entered, so two callers on one page (the console paints
   it, the board next door does too) share the same scene. */
(function () {
  if (window.WinnerCelebration) return;

  var CORNERS = {
    red: {
      a: '#ff3d2e', b: '#ff9d2e', glow: 'rgba(255,90,46,.6)',
      confetti: ['#ff9d2e', '#ff5e2e', '#ffd08a', '#ffb84d', '#ff7a3d', '#ffe3a8'],
      flame: ['#ff7a3d', '#ff5e2e', '#ffb84d'], base: 'rgba(255,94,46,.32)',
      core: '#fff2d8', spread: '45%', fade: '75%'
    },
    blue: {
      a: '#2e7bff', b: '#2ee6ff', glow: 'rgba(46,160,255,.6)',
      confetti: ['#eaf6ff', '#bfe6ff', '#8ad4ff', '#ffffff', '#a8ecff', '#d6f1ff'],
      flame: ['#3d9bff', '#2ee6ff', '#6b9dff'], base: 'rgba(46,155,255,.32)',
      core: '#ffffff', spread: '55%', fade: '80%'
    }
  };

  /* An image URL that came from the server, checked before it is put in a
     style attribute or an img src. Same rule the boards already follow: only
     same-origin paths and http(s), never a scheme that can carry script. */
  function safeUrl(u) {
    if (typeof u !== 'string' || !u) return null;
    if (u.charAt(0) === '/') return u;
    return /^https?:\/\//i.test(u) ? u : null;
  }

  function div(parent, css) {
    var d = document.createElement('div');
    d.style.cssText = css;
    parent.appendChild(d);
    return d;
  }

  function clamp(lo, v, hi) { return Math.max(lo, Math.min(v, hi)); }

  /* Deterministic, so a board that redraws lands on the same sky it had. */
  function random(seed) {
    var s = seed;
    return function () { s = (s * 16807) % 2147483647; return s / 2147483647; };
  }

  function paint(host, o) {
    if (!host) return null;
    o = o || {};

    var corner = CORNERS[o.corner] ? o.corner : 'red';
    var C = CORNERS[corner];
    var photo = safeUrl(o.photo);
    var logo = safeUrl(o.logo);
    var name = o.name || '';
    var club = o.club || '';
    var note = o.note || '';

    /* Restaging on every push would make the name slam over and over and the
       club plate flinch. The same winner is painted once; the caller gets the
       standing scene back and its own buttons stay where they were. */
    var key = [corner, name, club, note, photo || '', logo || ''].join('|');
    host.hidden = false;
    if (host.__wcKey === key && host.__wcActions) {
      return { actions: host.__wcActions, restaged: false };
    }
    host.__wcKey = key;
    host.textContent = '';

    /* The scene is authored against its HOST, not the viewport: on a wall board
       this sits inside a 1920x1080 canvas the browser then scales to the panel,
       so every measurement here is taken from the box we were given. */
    var W = host.clientWidth || 1920, H = host.clientHeight || 1080;
    var vw = W / 100, vh = H / 100, vmin = Math.min(W, H) / 100;
    // Detail sized against the design's own canvas, so a 4K wall gets bigger
    // ribbons and a laptop console gets smaller ones rather than both getting
    // the same 6 pixels.
    var k = Math.min(W / 1200, H / 800);

    var stage = div(host, 'position:absolute;inset:0;overflow:hidden;background:#07070d;color:#fff;' +
      "font-family:'Poppins',system-ui,sans-serif;pointer-events:none;animation:wcShakeHit .5s linear 1;");
    stage.className = 'wc-stage';
    stage.style.setProperty('--wcA', C.a);
    stage.style.setProperty('--wcB', C.b);
    stage.style.setProperty('--wcGlow', C.glow);
    stage.style.setProperty('--wcW', W + 'px');
    stage.style.setProperty('--wcH', H + 'px');

    // ── The hit: a white flash that burns off ────────────────────────────────
    div(stage, 'position:absolute;inset:0;z-index:9;pointer-events:none;animation:wcFlashIn .55s ease-out both;' +
      'background:radial-gradient(circle at ' + (corner === 'red' ? '40%' : '60%') + ' 45%, #fff 0%,' +
      'rgba(255,255,255,.85) 30%, rgba(255,255,255,.4) 60%, transparent 100%);');

    // ── Fire along the floor ─────────────────────────────────────────────────
    var fire = div(stage, 'position:absolute;inset:0;pointer-events:none;');
    div(fire, 'position:absolute;left:0;right:0;bottom:0;height:' + (34 * vh) + 'px;' +
      'background:linear-gradient(to top, ' + C.base + ', transparent);');
    var r2 = random(13);
    for (var j = 0; j < 12; j++) {
      div(fire, 'position:absolute;bottom:' + (-10 * vh) + 'px;left:' + ((j * 8.3 + r2() * 4) * vw) + 'px;' +
        'width:' + ((6 + r2() * 9) * vw) + 'px;height:' + ((20 + r2() * 18) * vh) + 'px;' +
        'border-radius:50% 50% 30% 30%;background:radial-gradient(ellipse at 50% 85%, #fff2d8 0%, ' +
        C.flame[j % C.flame.length] + ' 35%, transparent 72%);filter:blur(' + (18 + r2() * 16).toFixed(0) + 'px);' +
        'opacity:.45;transform-origin:bottom;will-change:transform;animation:wcFlameDance ' + (1.4 + r2() * 1.6).toFixed(2) +
        's ease-in-out ' + (r2() * 2).toFixed(2) + 's infinite alternate;');
    }

    // ── The photograph, cut in from the winner's own side ────────────────────
    // Portrait 3:4, because that is the ratio every picture on this platform is
    // stored at — the crop the competitor chose is the crop the hall sees.
    if (photo) {
      var side = div(stage, 'position:absolute;inset:0;display:flex;align-items:center;pointer-events:none;' +
        (corner === 'red' ? 'justify-content:flex-end;padding-right:' : 'justify-content:flex-start;padding-left:') +
        (5 * vw) + 'px;');
      var slide = div(side, 'animation:wcSlide' + (corner === 'red' ? 'Right' : 'Left') +
        ' .6s cubic-bezier(.2,1,.3,1) both;');
      var cardW = Math.min(35 * vw, 58 * vh);
      var card = div(slide, 'transform:rotate(' + (corner === 'red' ? '' : '-') + '2deg);' +
        'width:' + cardW + 'px;height:' + (cardW * 4 / 3) + 'px;padding:' + (7 * k) + 'px;' +
        'border-radius:' + (26 * k) + 'px;background:linear-gradient(140deg, var(--wcA), var(--wcB));' +
        'box-shadow:0 0 ' + (70 * k) + 'px var(--wcGlow), 0 ' + (40 * k) + 'px ' + (90 * k) + 'px rgba(0,0,0,.65);');
      var frame = div(card, 'width:100%;height:100%;border-radius:' + (20 * k) + 'px;overflow:hidden;' +
        'background:#12121e;position:relative;');
      var img = document.createElement('img');
      img.alt = '';
      img.src = photo;
      img.style.cssText = 'width:100%;height:100%;object-fit:cover;object-position:center top;' +
        'animation:wcKenBurns 9s ease-in-out infinite alternate;';
      frame.appendChild(img);
      div(frame, 'position:absolute;top:0;bottom:0;left:0;width:45%;pointer-events:none;' +
        'background:linear-gradient(105deg,transparent 15%,rgba(255,255,255,.25) 50%,transparent 85%);' +
        'animation:wcShine 4.2s ease-in-out 1.3s infinite;');
    }

    // ── The light: one spot behind the name, two slow beams across ───────────
    div(stage, 'position:absolute;left:' + ((corner === 'red' ? 40 : 60) * vw) + 'px;top:' + (20 * vh) + 'px;' +
      'width:' + (70 * vmin) + 'px;height:' + (70 * vmin) + 'px;transform:translate(-50%,-50%);border-radius:50%;' +
      'background:radial-gradient(circle, var(--wcA) 0%, transparent 65%);filter:blur(' + (60 * k) + 'px);' +
      'opacity:.5;animation:wcSpotPulse 3.2s ease-in-out infinite;pointer-events:none;');
    div(stage, 'position:absolute;top:' + (-50 * vh) + 'px;bottom:' + (-50 * vh) + 'px;left:0;width:' + (90 * k) + 'px;' +
      'background:linear-gradient(180deg, transparent, var(--wcA), transparent);opacity:.1;' +
      'animation:wcBeamMove 8s linear infinite;pointer-events:none;');
    div(stage, 'position:absolute;top:' + (-50 * vh) + 'px;bottom:' + (-50 * vh) + 'px;left:0;width:' + (40 * k) + 'px;' +
      'background:linear-gradient(180deg, transparent, var(--wcB), transparent);opacity:.12;' +
      'animation:wcBeamMove 12s linear 3s infinite;pointer-events:none;');

    // ── Who won ──────────────────────────────────────────────────────────────
    // With a photograph the name takes the half the card left it, on the
    // opposite side. Without one there is no half to take: it centres instead,
    // rather than sitting in a corner of an empty wall.
    var mirrored = !! photo && corner === 'blue';
    var block = div(stage, photo
      ? ('position:absolute;bottom:' + (8 * vh) + 'px;' +
         'left:' + ((mirrored ? 44 : 6) * vw) + 'px;right:' + ((mirrored ? 6 : 44) * vw) + 'px;' +
         'display:flex;flex-direction:column;gap:' + (1.8 * vh) + 'px;pointer-events:none;' +
         (mirrored ? 'align-items:flex-end;text-align:right;' : ''))
      : ('position:absolute;left:' + (6 * vw) + 'px;right:' + (6 * vw) + 'px;top:50%;' +
         'transform:translateY(-50%);display:flex;flex-direction:column;align-items:center;' +
         'text-align:center;gap:' + (1.8 * vh) + 'px;pointer-events:none;'));

    var word = div(block, 'align-self:' + (! photo ? 'center' : (mirrored ? 'flex-end' : 'flex-start')) + ';' +
      'font-size:' + clamp(48 * k / 1.35, 6.8 * vw, 108.8 * k) + 'px;font-weight:700;font-style:italic;line-height:1;' +
      'letter-spacing:.08em;text-transform:uppercase;background:linear-gradient(100deg, var(--wcA), var(--wcB));' +
      '-webkit-background-clip:text;background-clip:text;color:transparent;' +
      'filter:drop-shadow(0 0 ' + (34 * k) + 'px var(--wcGlow));' +
      'animation:wcSlide' + (mirrored ? 'Left' : 'Right') + ' .5s cubic-bezier(.2,1,.3,1) .1s both;');
    word.textContent = o.label || 'Winner';

    var nameEl = div(block, 'position:relative;color:#fff;font-weight:700;font-style:italic;line-height:.96;' +
      'font-size:' + clamp(57.6 * k / 1.35, 8.6 * vw, 168 * k) + 'px;text-transform:uppercase;' +
      'letter-spacing:-.01em;overflow-wrap:break-word;' +
      'animation:wcNameSlam .55s cubic-bezier(.2,1.3,.4,1) .2s both, wcTextGlow 2.6s ease-in-out .9s infinite;');
    nameEl.textContent = name;

    div(block, 'height:' + (6 * k) + 'px;max-width:100%;border-radius:' + (3 * k) + 'px;' +
      'background:linear-gradient(90deg, var(--wcA), var(--wcB));' +
      'animation:wcBarGrow .6s cubic-bezier(.2,1,.3,1) .55s both;');

    // The club they competed FOR — with its own crest when there is one, its
    // initials when there is not. A logo is never boxed on a white tile: it is
    // the bare mark on the same translucent disc the boards already use.
    if (club || logo) {
      var badge = 110 * k;
      var row = div(block, 'display:flex;align-items:center;gap:' + (22 * k) + 'px;margin-top:' + (0.4 * vh) + 'px;' +
        'width:max-content;animation:wcRiseIn .5s .7s both;' + (mirrored ? 'flex-direction:row-reverse;' : ''));

      if (logo) {
        var crest = document.createElement('img');
        crest.alt = '';
        crest.src = logo;
        crest.style.cssText = 'width:' + badge + 'px;height:' + badge + 'px;border-radius:50%;object-fit:contain;' +
          'background:rgba(255,255,255,.06);border:' + Math.max(1, 3 * k) + 'px solid rgba(255,255,255,.9);' +
          'box-shadow:0 0 ' + (44 * k) + 'px var(--wcGlow);';
        row.appendChild(crest);
      } else if (club) {
        var mono = div(row, 'width:' + badge + 'px;height:' + badge + 'px;border-radius:50%;background:#12121e;' +
          'border:' + Math.max(1, 3 * k) + 'px solid rgba(255,255,255,.9);display:flex;align-items:center;' +
          'justify-content:center;font-weight:700;font-style:italic;font-size:' + (2.2 * 16 * k) + 'px;color:#fff;' +
          'letter-spacing:.05em;box-shadow:0 0 ' + (44 * k) + 'px var(--wcGlow);');
        mono.textContent = club.split(/\s+/).map(function (w) { return w.charAt(0); }).join('').slice(0, 2).toUpperCase();
      }

      if (club) {
        var clubEl = div(row, 'font-size:' + clamp(32 * k / 1.35, 4.2 * vw, 64 * k) + 'px;font-weight:700;' +
          'font-style:italic;line-height:1.05;letter-spacing:.06em;text-transform:uppercase;color:#fff;' +
          'white-space:nowrap;text-shadow:0 0 ' + (44 * k) + 'px var(--wcGlow), 0 ' + (6 * k) + 'px ' + (24 * k) + 'px rgba(0,0,0,.7);');
        clubEl.textContent = club;
      }
    }

    // WHY, when it was not the score. Without this line a bout won on a
    // disqualification reads from the floor as a broken scoreboard — the name
    // says one athlete while the bigger number sits beside the other.
    if (note) {
      var why = div(block, 'font-size:' + (26 * k) + 'px;font-weight:700;letter-spacing:.22em;' +
        'text-transform:uppercase;color:var(--wcB);animation:wcRiseIn .5s .8s both;');
      why.textContent = note;
    }

    // The one place a caller may put controls. Nothing in the scene is
    // clickable, so this is the only thing that takes a pointer.
    var actions = div(block, 'display:flex;align-items:center;gap:' + (14 * k) + 'px;flex-wrap:wrap;' +
      'pointer-events:auto;margin-top:' + (1.2 * vh) + 'px;animation:wcRiseIn .5s .9s both;' +
      (! photo ? 'justify-content:center;' : (mirrored ? 'justify-content:flex-end;' : '')));
    host.__wcActions = actions;

    // ── Embers ───────────────────────────────────────────────────────────────
    var box = div(stage, 'position:absolute;inset:0;pointer-events:none;overflow:hidden;z-index:5;');
    var rnd = random(7);
    for (var i = 0; i < 44; i++) {
      var c = C.confetti[i % C.confetti.length];
      var s = (corner === 'red' ? 4 + rnd() * 6 : 3 + rnd() * 5) * k;
      div(box, 'position:absolute;top:0;left:' + ((i + 0.5) * (100 / 44)).toFixed(2) + '%;' +
        'width:' + s.toFixed(1) + 'px;height:' + s.toFixed(1) + 'px;border-radius:50%;' +
        'background:radial-gradient(circle, ' + C.core + ' 0%, ' + c + ' ' + C.spread + ', transparent ' + C.fade + ');' +
        'box-shadow:0 0 ' + ((corner === 'red' ? 8 + rnd() * 10 : 6 + rnd() * 8) * k).toFixed(0) + 'px ' +
        Math.max(1, k).toFixed(0) + 'px ' + c + ';will-change:transform;' +
        'animation:wcConfetti ' + (corner === 'red' ? (3.4 + rnd() * 3) : (4.2 + rnd() * 3.4)).toFixed(2) + 's linear ' +
        (rnd() * 3).toFixed(2) + 's infinite, wcEmberFlicker ' +
        (corner === 'red' ? (.5 + rnd() * .9) : (.8 + rnd() * 1.2)).toFixed(2) + 's ease-in-out infinite;');
    }

    return { actions: actions, restaged: true };
  }

  /** Put the scene away and forget it, so the next winner is staged fresh. */
  function clear(host) {
    if (!host) return;
    host.__wcKey = null;
    host.__wcActions = null;
    host.textContent = '';
    host.hidden = true;
  }

  window.WinnerCelebration = { paint: paint, clear: clear };
})();
</script>
@endonce
