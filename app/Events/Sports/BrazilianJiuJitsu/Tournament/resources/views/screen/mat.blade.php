{{--
    The Brazilian Jiu-Jitsu mat screen — every public surface, in one document.

    Five layers, one page: the running order, the VS introduction, the live
    board, the paused/review card and the winner. They are layers rather than
    pages on purpose — the operator calls a match up and the introduction wipes
    away to reveal the scoreboard already behind it. As separate documents that
    transition would be a navigation, and a wall screen that goes white between
    the introduction and the first point looks broken from ten metres.

    This is SIGNAGE, not product UI: no layout, no chrome, no viewer, and a
    stage authored at 1920×1080 that only ever SCALES (×0.667 at 720p, ×1.333 at
    1440p, exactly as the design spec requires — one transform, nothing
    re-laid-out per breakpoint).

    It decides NOTHING. Every value arrives from MatState over MQTT, including
    the score, which is replayed from the ledger on the server. The only thing
    computed here is the clock, derived from `remaining`/`running`/`at` so two
    screens on the same mat cannot drift apart.

    ── The rules the design spec calls load-bearing, and where they live ──────
    · Red is never a competitor colour. Blue is LEFT, white is RIGHT, always.
      Red appears only on penalties. (--corner-* and --status-penalty below.)
    · Colour never carries meaning alone: every coloured indicator is paired
      with a text label — POINTS, ADV, PEN, LIVE, PAUSED, REVIEW, FINISHED.
    · Points ≫ advantages and penalties, and ADV must never look added to
      points. The score numeral is 330px; the counters are 54px in their own
      bordered cards, below and apart.
    · The two corners are MIRROR-SYMMETRIC about the timer, anchored to their
      own outer edge bar, so spacing is equal whatever the names are.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ $state['theme'] }}">
<head>
<meta charset="utf-8">
{{-- A screen is not a document: it is authored at one size and scaled to fit
     the glass, so there is nothing here to zoom INTO — magnifying it can only
     push part of the surface off the edge, which on a wall nobody can undo.
     This is the one place the house rule against `user-scalable=no` does not
     apply (mobile web must always pinch-zoom, WCAG 1.4.4): this is signage. --}}
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
<title>{{ __('event-bjj_tournament::messages.board_title') }} · {{ $court }}</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">

<style>
/* ══════════════════════════════════════════════════════════════════════════
   TOKENS — the design spec's palette, by NAME.
   Nothing below this block writes a raw hex value. That is what makes the
   bright-venue theme ONE switch rather than a hunt: `data-theme="venue"`
   substitutes a handful of these and every surface follows.
   ══════════════════════════════════════════════════════════════════════════ */
:root {
  --surface-base: #08111F;
  --surface-panel: #101E31;
  --surface-raised: #14263D;
  --surface-divider: #233A55;
  --surface-overlay: rgba(8,17,31,.85);

  --text-primary: #F8FAFC;
  --text-secondary: #94A3B8;
  --text-muted: #64748B;
  --text-inverse: #0F172A;

  --corner-blue: #1677FF;
  --corner-blue-accent: #38BDF8;
  --corner-blue-surface: #0D3A72;

  --corner-white: #F3F6FA;
  --corner-white-muted: #CBD5E1;
  --corner-white-surface: rgba(243,246,250,.08);

  --status-advantage: #D8B25F;
  --status-penalty: #EF4444;
  --status-confirmed: #22C55E;
  --status-review: #A78BFA;

  /* Depth. Default elevation is a 1px divider border and NO shadow; the timer
     panel is the single exception in the whole layout. */
  --shadow-timer: 0 30px 70px rgba(0,0,0,.5);
  --pip-h: 6px;

  /* Spacing, on a 4px base: pip gap · chip internal · chip rows · card
     internal · between cards · section internal · zone separation · margins. */
  --s1: 4px; --s2: 8px; --s3: 12px; --s4: 16px;
  --s5: 24px; --s6: 32px; --s7: 48px; --s8: 64px;
}

/* The bright-venue / projector theme. ONE toggle, never per-colour edits: a
   coherent set of substitutions, glows and texture off, fatter pips. Chosen at
   the scoring table, so every screen on the mat changes together. */
[data-theme="venue"] {
  --text-primary: #FFFFFF;
  --text-secondary: #C6D2E2;
  --surface-divider: #3B577A;
  --corner-blue-accent: #6ED2FF;
  --status-advantage: #EDCD85;
  --shadow-timer: none;
  --pip-h: 8px;
}
[data-theme="venue"] .glow,
[data-theme="venue"] #texture,
[data-theme="venue"] .wash { display: none !important; }
[data-theme="venue"] #blueScore, [data-theme="venue"] #whiteScore { text-shadow: none; }

html, body { margin:0; padding:0; background:#000; overflow:hidden; height:100%; }
html { -webkit-text-size-adjust:100%; text-size-adjust:100%; touch-action:none; }

#root { position:absolute; inset:0; background:var(--surface-base); overflow:hidden; }

/* The stage. Authored at 1920×1080 and SCALED — never re-laid-out. Every size
   below is therefore in 1080p pixels, and 720p/1440p come out exact. */
#stage {
  position:absolute; left:50%; top:50%; width:1920px; height:1080px;
  transform: translate(-50%,-50%) scale(var(--stage-scale, .5));
  transform-origin:center;
  font-family:'Poppins', system-ui, sans-serif;
  color:var(--text-primary);
  background:var(--surface-base);
  overflow:hidden;
}

/* The tatami grid: 1px every 96px at 4–5%, plus a corner wash per side. Pure
   decoration, and the first thing the venue theme drops. */
#texture {
  position:absolute; inset:0; pointer-events:none;
  background-image:
    repeating-linear-gradient(0deg, rgba(255,255,255,.045) 0 1px, transparent 1px 96px),
    repeating-linear-gradient(90deg, rgba(255,255,255,.045) 0 1px, transparent 1px 96px);
}
.wash { position:absolute; width:1000px; height:700px; pointer-events:none; border-radius:50%; filter:blur(4px); }
#washBlue  { left:-260px; top:190px; background:radial-gradient(ellipse at center, rgba(22,119,255,.15), transparent 70%); }
#washWhite { right:-260px; top:190px; background:radial-gradient(ellipse at center, rgba(243,246,250,.07), transparent 70%); }

.layer { position:absolute; inset:0; display:flex; flex-direction:column; }
[hidden] { display:none !important; }

/* Typography. Bebas Neue carries every numeral and display line; Poppins the
   UI text, and on a public screen never below weight 500. */
.num { font-family:'Bebas Neue', Impact, sans-serif; font-weight:400; }

/* ── Header: 120px, a 1px hairline at its foot, panel at 55% ─────────────── */
#hdr {
  height:120px; flex:0 0 120px; display:flex; align-items:center; gap:var(--s5);
  padding:0 56px; background:color-mix(in srgb, var(--surface-panel) 55%, transparent);
  border-bottom:1px solid var(--surface-divider);
}
#hdrLogo { width:46px; height:46px; border-radius:12px; background:var(--surface-raised);
  border:1px solid var(--surface-divider); background-size:contain; background-position:center;
  background-repeat:no-repeat; flex:0 0 46px; }
#hdrEvent { font-size:22px; font-weight:700; letter-spacing:.02em; white-space:nowrap; }
.chip {
  display:inline-flex; align-items:center; gap:var(--s2);
  padding:6px 14px; border-radius:10px; background:var(--surface-raised);
  border:1px solid var(--surface-divider);
  font-size:17px; font-weight:700; letter-spacing:.24em; text-transform:uppercase;
}
#hdrMeta { flex:1; text-align:center; font-size:18px; font-weight:600; letter-spacing:.16em;
  text-transform:uppercase; color:var(--text-secondary); }
#hdrRight { display:flex; align-items:center; gap:var(--s3); }

/* ── Match area: 1fr / 420px / 1fr, mirror-symmetric ─────────────────────── */
#match { flex:1; display:grid; grid-template-columns:1fr 420px 1fr; padding:0 56px; min-height:0; }

.corner { position:relative; display:flex; flex-direction:column; justify-content:center; gap:22px; }
/* Corner identity is an EDGE BAR, never a full coloured outline. */
.corner::before { content:''; position:absolute; top:40px; bottom:40px; width:6px; border-radius:3px; }
#cBlue  { align-items:flex-start; padding-left:64px; }
#cWhite { align-items:flex-end;   padding-right:64px; text-align:right; }
#cBlue::before  { left:0;  background:var(--corner-blue); }
#cWhite::before { right:0; background:var(--corner-white-muted); }

.idRow { display:flex; align-items:center; gap:var(--s5); }
#cWhite .idRow { flex-direction:row-reverse; }
.face { width:124px; height:124px; border-radius:50%; background-size:cover; background-position:center;
  background-color:var(--surface-raised); flex:0 0 124px; }
#cBlue  .face { border:3px solid var(--corner-blue); }
#cWhite .face { border:3px solid var(--corner-white-muted); }
.who { min-width:0; }
.name { font-size:52px; font-weight:700; line-height:1.02; text-transform:uppercase; white-space:nowrap; }
.sub { font-size:18px; font-weight:600; letter-spacing:.16em; text-transform:uppercase; color:var(--text-secondary); margin-top:6px; }
.acad { font-size:18px; font-weight:600; color:var(--text-muted); margin-top:2px; }

.scoreGroup { display:flex; flex-direction:column; align-items:inherit; }
.score { font-size:330px; line-height:.85; }
#blueScore  { color:var(--corner-blue-accent); text-shadow:0 0 90px rgba(56,189,248,.35); }
#whiteScore { color:var(--corner-white);       text-shadow:0 0 90px rgba(243,246,250,.22); }
/* The label under the numeral is not decoration: it is what stops a reader
   confusing a points total with an advantage count. */
.scoreLabel { font-size:17px; font-weight:700; letter-spacing:.28em; text-transform:uppercase;
  color:var(--text-secondary); text-align:center; width:100%; margin-top:var(--s1); }

.counters { display:flex; gap:18px; }
#cWhite .counters { flex-direction:row-reverse; }
.counter { display:flex; align-items:center; gap:var(--s3);
  background:var(--surface-panel); border:1px solid var(--surface-divider);
  border-radius:14px; padding:16px 26px; }
.counter .cl { font-size:17px; font-weight:700; letter-spacing:.22em; }
.counter.adv .cl { color:var(--status-advantage); }
.counter.pen .cl { color:var(--status-penalty); }
.counter .cn { font-size:54px; line-height:1; }
.pips { display:flex; gap:var(--s1); }
#cWhite .pips { flex-direction:row-reverse; }
.pip { width:22px; height:var(--pip-h); border-radius:3px; background:var(--surface-divider); }
.counter.adv .pip.on { background:var(--status-advantage); }
.counter.pen .pip.on { background:var(--status-penalty); }

/* ── Centre: the timer panel, the one elevated element on the screen ─────── */
#centre { display:flex; flex-direction:column; align-items:center; justify-content:center; }
#timerPanel { background:var(--surface-panel); border-radius:22px; padding:34px 54px;
  box-shadow:var(--shadow-timer); text-align:center; position:relative; }
.hair { height:1px; width:300px; margin:0 auto;
  background:linear-gradient(90deg, transparent, var(--surface-divider), transparent); }
#statusRow { display:flex; align-items:center; justify-content:center; gap:var(--s2);
  font-size:17px; font-weight:700; letter-spacing:.45em; text-transform:uppercase; padding:12px 0; }
#statusDot { width:11px; height:11px; border-radius:50%; background:var(--status-confirmed); }
#timer { font-size:148px; line-height:.95; letter-spacing:.03em; }
#timerEyebrow { font-size:15px; font-weight:600; letter-spacing:.30em; text-transform:uppercase;
  color:var(--text-muted); padding:12px 0; }

/* ── Footer: 118px, hairline on top ─────────────────────────────────────── */
#ftr { height:118px; flex:0 0 118px; display:flex; align-items:center; gap:var(--s5);
  padding:0 56px; border-top:1px solid var(--surface-divider); }
#ftrRef { min-width:340px; font-size:18px; font-weight:600; letter-spacing:.16em;
  text-transform:uppercase; color:var(--text-secondary); }
#ftrLast { flex:1; display:flex; justify-content:center; }
#lastCard { background:var(--surface-panel); border:1px solid var(--surface-divider);
  border-radius:14px; padding:14px 26px; font-size:18px; font-weight:600; letter-spacing:.06em; }
#ftrRight { min-width:340px; text-align:right; font-size:18px; font-weight:600;
  letter-spacing:.16em; text-transform:uppercase; color:var(--text-secondary); }

/* The stalling notice replaces the last-action card for ~4s. Amber into red,
   and it says PENALTY in words — never a colour on its own. */
#notice { background:color-mix(in srgb, var(--status-penalty) 18%, var(--surface-panel));
  border:1px solid var(--status-penalty); border-radius:14px; padding:14px 26px;
  font-size:20px; font-weight:700; letter-spacing:.08em; color:var(--text-primary); }

/* ── VS introduction ────────────────────────────────────────────────────── */
#vs { padding:32px 96px; align-items:stretch; }
#vsHead { text-align:center; }
#vsLogo { width:54px; height:54px; margin:0 auto; border-radius:14px; background:var(--surface-raised);
  background-size:contain; background-position:center; background-repeat:no-repeat; }
#vsTitle { font-size:46px; font-weight:700; margin-top:var(--s3); }
#vsChips { display:flex; justify-content:center; gap:var(--s3); margin-top:var(--s4); }
#vsGrid { flex:1; display:grid; grid-template-columns:1fr 300px 1fr; align-items:center; gap:var(--s5); min-height:0; }
.vsCard { width:440px; height:480px; border-radius:20px; position:relative; overflow:hidden;
  background:var(--surface-panel); border:1px solid var(--surface-divider);
  background-size:cover; background-position:center; }
#vsBlueCard  { justify-self:end; }
#vsWhiteCard { justify-self:start; }
.vsChip { position:absolute; left:var(--s4); top:var(--s4); }
.vsName { font-size:54px; font-weight:700; text-transform:uppercase; margin-top:var(--s4); }
.vsMeta { font-size:18px; font-weight:600; letter-spacing:.16em; text-transform:uppercase;
  color:var(--text-secondary); margin-top:var(--s2); }
#vsBlue  { text-align:right; }
#vsWhite { text-align:left; }
#vsMid { text-align:center; }
#vsWord { font-size:210px; line-height:.9; color:var(--status-advantage); }
#vsDiv { font-size:18px; font-weight:600; letter-spacing:.16em; text-transform:uppercase;
  color:var(--text-secondary); margin-top:var(--s4); }
#vsStart { margin-top:var(--s5); font-size:17px; font-weight:700; letter-spacing:.28em;
  text-transform:uppercase; color:var(--status-advantage); animation:p7blink 1.6s ease-in-out infinite; }

/* ── Paused / review ────────────────────────────────────────────────────── */
#paused { align-items:center; justify-content:center; background:var(--surface-overlay); }
#pausedWash { position:absolute; inset:0; pointer-events:none;
  background:radial-gradient(ellipse at center, rgba(167,139,250,.18), transparent 65%); }
#pausedGlyph { width:110px; height:110px; border-radius:50%; border:2px solid var(--status-review);
  display:grid; place-items:center; box-shadow:0 0 70px rgba(167,139,250,.30); }
#pausedGlyph span { font-size:44px; color:var(--status-review); }
#pausedTitle { font-size:130px; line-height:1; margin-top:var(--s5); }
#pausedChip { margin-top:var(--s4); border-color:var(--status-review); color:var(--status-review); }
#frozen { margin-top:var(--s5); border:1px solid var(--status-review); border-radius:18px;
  padding:var(--s5) var(--s7); text-align:center; background:var(--surface-panel); }
#frozenLabel { font-size:15px; font-weight:600; letter-spacing:.30em; text-transform:uppercase; color:var(--text-muted); }
#frozenTime { font-size:110px; line-height:1; }
#pausedScore { margin-top:var(--s5); opacity:.6; font-size:18px; font-weight:600;
  letter-spacing:.16em; text-transform:uppercase; }
#pausedWait { margin-top:var(--s4); font-size:17px; font-weight:700; letter-spacing:.28em;
  text-transform:uppercase; color:var(--text-secondary); }

/* ── Winner ─────────────────────────────────────────────────────────────── */
#winner { align-items:center; justify-content:center; background:var(--surface-overlay); }
#winRule { display:flex; align-items:center; gap:var(--s5); }
#winRule i { display:block; width:220px; height:1px;
  background:linear-gradient(90deg, transparent, var(--status-advantage), transparent); }
#winWord { font-size:96px; line-height:1; letter-spacing:.32em; color:var(--status-advantage); }
#winFace { width:170px; height:170px; border-radius:50%; margin-top:var(--s6);
  border:4px solid var(--status-advantage); box-shadow:0 0 70px rgba(216,178,95,.30);
  background-size:cover; background-position:center; background-color:var(--surface-raised); }
#winName { font-size:76px; font-weight:700; text-transform:uppercase; margin-top:var(--s5); }
#winChips { display:flex; gap:var(--s3); margin-top:var(--s3); }
#winMethod { margin-top:var(--s5); border-color:var(--status-confirmed); color:var(--status-confirmed); }
#winScore { font-size:190px; line-height:.9; margin-top:var(--s4); }
#winScore .b { color:var(--corner-blue-accent); }
#winScore .w { color:var(--corner-white); }
#winScore .d { color:var(--text-muted); }
#winCards { display:flex; gap:var(--s5); margin-top:var(--s5); }
.winCard { background:var(--surface-panel); border:1px solid var(--surface-divider);
  border-radius:16px; padding:var(--s5) var(--s6); text-align:center; min-width:200px; }
.winCard .l { font-size:15px; font-weight:600; letter-spacing:.30em; text-transform:uppercase; color:var(--text-muted); }
.winCard .v { font-size:44px; line-height:1; margin-top:var(--s2); }
#winNext { margin-top:var(--s5); border-color:var(--status-confirmed); color:var(--status-confirmed); }

/* ── The running order, between matches ─────────────────────────────────── */
#queue { padding:0 56px; }
/* Waiting for a match: the mat says so rather than showing an empty frame.
   Sized and faded to match the Karate and Taekwondo boards exactly. */
#idle { flex-direction:column; align-items:center; justify-content:center; gap:18px;
  background:var(--surface-base); }
#idle .t { font-size:92px; line-height:1; letter-spacing:.14em; text-transform:uppercase;
  font-weight:800; color:color-mix(in srgb, var(--text-primary) 22%, transparent); }
#idle .s { font-size:34px; font-weight:600; letter-spacing:.30em; text-transform:uppercase;
  color:color-mix(in srgb, var(--text-primary) 35%, transparent); }
#queueHead { height:120px; flex:0 0 120px; display:flex; align-items:center; gap:var(--s5);
  border-bottom:1px solid var(--surface-divider); }
#queueList { flex:1; display:flex; flex-direction:column; justify-content:center; gap:var(--s5); }
.qRow { display:grid; grid-template-columns:1fr 260px 1fr; align-items:center;
  background:var(--surface-panel); border:1px solid var(--surface-divider);
  border-radius:16px; padding:var(--s5) var(--s6); }
.qRow .qb { text-align:right; font-size:38px; font-weight:700; text-transform:uppercase; }
.qRow .qw { text-align:left;  font-size:38px; font-weight:700; text-transform:uppercase; }
.qMid { text-align:center; }
.qNo { font-size:44px; line-height:1; }
.qMeta { font-size:15px; font-weight:600; letter-spacing:.24em; text-transform:uppercase; color:var(--text-muted); margin-top:var(--s1); }

/* ── Motion. The LIVE dot pulse is the ONLY permanent animation; everything
      else fires on change and stops. ───────────────────────────────────── */
@keyframes p7blink { 0%,100% { opacity:1; } 50% { opacity:.2; } }
@keyframes p7glow  { 0%,100% { opacity:1; } 50% { opacity:.5; } }
@keyframes p7notice { 0% { transform:translateY(14px); opacity:0; } 12%,88% { transform:translateY(0); opacity:1; } 100% { transform:translateY(-8px); opacity:0; } }
@keyframes p7pop   { 0% { transform:scale(1); } 30% { transform:scale(1.12); } 100% { transform:scale(1); } }
@keyframes p7fade  { from { opacity:0; } to { opacity:1; } }

#statusDot.live { animation:p7blink 1.6s ease-in-out infinite; }
#timer.warn { color:var(--status-penalty); animation:p7glow 1s ease-in-out infinite; }
.pop { animation:p7pop .4s cubic-bezier(.2,.8,.2,1); }
#notice { animation:p7notice 4s ease forwards; }
.enter { animation:p7fade .6s ease; }

@media (prefers-reduced-motion: reduce) {
  #statusDot.live, #timer.warn, .pop, #notice, .enter, #vsStart { animation:none !important; }
}

/* The link went quiet. Deliberately small and in the corner: a wall screen
   must never blank because a socket dropped, and the board it is still showing
   is almost always still correct. */
#stale { position:absolute; right:20px; bottom:16px; width:10px; height:10px; border-radius:50%;
  background:var(--status-penalty); opacity:0; transition:opacity .3s; }
#stale.on { opacity:.8; }
</style>
</head>
<body>
<div id="root">
  <div id="stage">
    <div id="texture"></div>
    <div class="wash" id="washBlue"></div>
    <div class="wash" id="washWhite"></div>

    {{-- ── The live board ─────────────────────────────────────────────── --}}
    <div class="layer" id="board" hidden>
      <div id="hdr">
        <div id="hdrLogo"></div>
        <div id="hdrEvent"></div>
        <div class="chip" id="hdrMat"></div>
        <div id="hdrMeta"></div>
        <div id="hdrRight">
          <div class="chip" id="hdrStage"></div>
          <div class="chip" id="hdrNo"></div>
        </div>
      </div>

      <div id="match">
        <div class="corner" id="cBlue">
          <div class="chip">{{ __('sport-brazilianjiujitsu::messages.corner_blue') }}</div>
          <div class="idRow">
            <div class="face" id="blueFace"></div>
            <div class="who">
              <div class="name" id="blueName"></div>
              <div class="sub" id="blueCountry"></div>
              <div class="acad" id="blueClub"></div>
            </div>
          </div>
          <div class="scoreGroup">
            <div class="score num" id="blueScore">0</div>
            <div class="scoreLabel">{{ __('event-bjj_tournament::messages.points') }}</div>
          </div>
          <div class="counters">
            <div class="counter adv">
              <span class="cl">{{ __('event-bjj_tournament::messages.adv_short') }}</span>
              <span class="cn num" id="blueAdv">0</span>
              <span class="pips" id="blueAdvPips"></span>
            </div>
            <div class="counter pen">
              <span class="cl">{{ __('event-bjj_tournament::messages.pen_short') }}</span>
              <span class="cn num" id="bluePen">0</span>
              <span class="pips" id="bluePenPips"></span>
            </div>
          </div>
        </div>

        <div id="centre">
          <div id="timerPanel">
            <div class="hair"></div>
            <div id="statusRow"><span id="statusDot"></span><span id="statusText"></span></div>
            <div class="num" id="timer">0:00</div>
            <div id="timerEyebrow">{{ __('event-bjj_tournament::messages.regulation_time') }}</div>
            <div class="hair"></div>
          </div>
        </div>

        {{-- The white corner is a FULL MIRROR: face on the right, text
             right-aligned, and the counters ordered PEN·ADV so the two blocks
             read outward from the timer rather than both left-to-right. --}}
        <div class="corner" id="cWhite">
          <div class="chip">{{ __('sport-brazilianjiujitsu::messages.corner_white') }}</div>
          <div class="idRow">
            <div class="face" id="whiteFace"></div>
            <div class="who">
              <div class="name" id="whiteName"></div>
              <div class="sub" id="whiteCountry"></div>
              <div class="acad" id="whiteClub"></div>
            </div>
          </div>
          <div class="scoreGroup">
            <div class="score num" id="whiteScore">0</div>
            <div class="scoreLabel">{{ __('event-bjj_tournament::messages.points') }}</div>
          </div>
          <div class="counters">
            <div class="counter pen">
              <span class="pips" id="whitePenPips"></span>
              <span class="cn num" id="whitePen">0</span>
              <span class="cl">{{ __('event-bjj_tournament::messages.pen_short') }}</span>
            </div>
            <div class="counter adv">
              <span class="pips" id="whiteAdvPips"></span>
              <span class="cn num" id="whiteAdv">0</span>
              <span class="cl">{{ __('event-bjj_tournament::messages.adv_short') }}</span>
            </div>
          </div>
        </div>
      </div>

      <div id="ftr">
        <div id="ftrRef"></div>
        <div id="ftrLast"><div id="lastCard"></div></div>
        <div id="ftrRight"></div>
      </div>
    </div>

    {{-- ── The introduction ───────────────────────────────────────────── --}}
    <div class="layer" id="vs" hidden>
      <div id="vsHead">
        <div id="vsLogo"></div>
        <div id="vsTitle"></div>
        <div id="vsChips">
          <div class="chip" id="vsMat"></div>
          <div class="chip">{{ __('event-bjj_tournament::messages.next_match') }}</div>
        </div>
      </div>
      <div id="vsGrid">
        <div id="vsBlue">
          <div class="vsCard" id="vsBlueCard">
            <div class="chip vsChip">{{ __('sport-brazilianjiujitsu::messages.corner_blue') }}</div>
          </div>
          <div class="vsName" id="vsBlueName"></div>
          <div class="vsMeta" id="vsBlueMeta"></div>
        </div>
        <div id="vsMid">
          <div class="hair"></div>
          <div class="num" id="vsWord">{{ __('event-bjj_tournament::messages.vs') }}</div>
          <div class="hair"></div>
          <div id="vsDiv"></div>
          <div id="vsStart">{{ __('event-bjj_tournament::messages.match_starting') }}</div>
        </div>
        <div id="vsWhite">
          <div class="vsCard" id="vsWhiteCard">
            <div class="chip vsChip">{{ __('sport-brazilianjiujitsu::messages.corner_white') }}</div>
          </div>
          <div class="vsName" id="vsWhiteName"></div>
          <div class="vsMeta" id="vsWhiteMeta"></div>
        </div>
      </div>
    </div>

    {{-- ── Paused / review / medical ──────────────────────────────────── --}}
    <div class="layer" id="paused" hidden>
      <div id="pausedWash"></div>
      <div id="pausedGlyph"><span id="pausedIcon">⏸</span></div>
      <div class="num" id="pausedTitle">{{ __('event-bjj_tournament::messages.match_paused') }}</div>
      <div class="chip" id="pausedChip"></div>
      <div id="frozen">
        <div id="frozenLabel">{{ __('event-bjj_tournament::messages.timer_frozen') }}</div>
        <div class="num" id="frozenTime">0:00</div>
      </div>
      <div id="pausedScore"></div>
      <div id="pausedWait">{{ __('event-bjj_tournament::messages.please_wait') }}</div>
    </div>

    {{-- ── Winner ─────────────────────────────────────────────────────── --}}
    <div class="layer" id="winner" hidden>
      <div id="winRule"><i></i><div class="num" id="winWord">{{ __('event-bjj_tournament::messages.winner') }}</div><i></i></div>
      <div id="winFace"></div>
      <div id="winName"></div>
      <div id="winChips"><div class="chip" id="winClub"></div><div class="chip" id="winDivision"></div></div>
      <div class="chip" id="winMethod"></div>
      <div class="num" id="winScore"></div>
      <div id="winCards">
        <div class="winCard"><div class="l">{{ __('event-bjj_tournament::messages.points') }}</div><div class="v num" id="winPoints"></div></div>
        <div class="winCard"><div class="l">{{ __('event-bjj_tournament::messages.advantages') }}</div><div class="v num" id="winAdv"></div></div>
        <div class="winCard"><div class="l">{{ __('event-bjj_tournament::messages.penalties') }}</div><div class="v num" id="winPen"></div></div>
      </div>
      <div class="chip" id="winNext" hidden></div>
    </div>

    {{-- ── Waiting ─────────────────────────────────────────────────────
         A scoreboard between matches shows NOTHING but that it is clear.

         Deliberately identical in behaviour and in words to Karate and
         Taekwondo (`court_idle_title` / `court_idle_sub`, the same two faint
         lines on the dark ground): a scoreboard is hung over one mat, and
         between matches it goes quiet. It does not become the running order —
         that is the corridor screen's job — and it does not fill the wall with
         the event's branding either. Every mat in a hall must read the same
         way whichever sport is on it. --}}
    <div class="layer" id="idle" hidden>
      <div class="t">{{ __('event-bjj_tournament::messages.court_idle_title') }}</div>
      <div class="s">{{ __('event-bjj_tournament::messages.court_idle_sub') }}</div>
    </div>

    {{-- ── The running order, between matches ─────────────────────────── --}}
    <div class="layer" id="queue" hidden>
      <div id="queueHead">
        <div id="hdrLogo2" style="width:46px;height:46px"></div>
        <div id="hdrEvent2" style="font-size:22px;font-weight:700"></div>
        <div class="chip" id="queueMat"></div>
        <div style="flex:1"></div>
        <div class="chip">{{ __('event-bjj_tournament::messages.court_title') }}</div>
      </div>
      <div id="queueList"></div>
    </div>

    <div id="stale"></div>
  </div>
</div>

{{-- ⚠️ Pre-assigned, never inlined into @json(). Blade's bracket matcher
     chokes on an array literal inside @json(...) and the whole view then fails
     to compile with a misleading error — documented in CLAUDE.md. --}}
@php
    $statusWords = [
      'idle' => __('event-bjj_tournament::messages.status_idle'),
      'live' => __('event-bjj_tournament::messages.status_live'),
      'paused' => __('event-bjj_tournament::messages.status_paused'),
      'review' => __('event-bjj_tournament::messages.status_review'),
      'medical' => __('event-bjj_tournament::messages.status_medical'),
      'overtime' => __('event-bjj_tournament::messages.status_overtime'),
      'submission' => __('event-bjj_tournament::messages.status_submission'),
      'dq' => __('event-bjj_tournament::messages.status_dq'),
      'walkover' => __('event-bjj_tournament::messages.status_walkover'),
      'finished' => __('event-bjj_tournament::messages.status_finished'),
    ];

    $methodWords = [
      'submission' => __('event-bjj_tournament::messages.method_submission'),
      'points' => __('event-bjj_tournament::messages.method_points'),
      'decision' => __('event-bjj_tournament::messages.method_decision'),
      'dq' => __('event-bjj_tournament::messages.method_dq'),
      'walkover' => __('event-bjj_tournament::messages.method_walkover'),
      'medical' => __('event-bjj_tournament::messages.method_medical'),
      'forfeit' => __('event-bjj_tournament::messages.method_forfeit'),
    ];

    $sourceWords = [
      'takedown' => __('event-bjj_tournament::messages.source_takedown'),
      'sweep' => __('event-bjj_tournament::messages.source_sweep'),
      'knee_on_belly' => __('event-bjj_tournament::messages.source_knee_on_belly'),
      'guard_pass' => __('event-bjj_tournament::messages.source_guard_pass'),
      'mount' => __('event-bjj_tournament::messages.source_mount'),
      'back_control' => __('event-bjj_tournament::messages.source_back_control'),
    ];

    $penaltyWords = [
      'stalling' => __('event-bjj_tournament::messages.penalty_stalling'),
      'fleeing' => __('event-bjj_tournament::messages.penalty_fleeing'),
      'grip_infraction' => __('event-bjj_tournament::messages.penalty_grip_infraction'),
      'illegal_technique' => __('event-bjj_tournament::messages.penalty_illegal_technique'),
      'conduct' => __('event-bjj_tournament::messages.penalty_conduct'),
      'other' => __('event-bjj_tournament::messages.penalty_other'),
    ];

    $decidedWords = [
      'points' => __('event-bjj_tournament::messages.decided_by_points'),
      'advantages' => __('event-bjj_tournament::messages.decided_by_advantages'),
      'penalties' => __('event-bjj_tournament::messages.decided_by_penalties'),
    ];
@endphp
<script>
(function () {
  'use strict';

  /* The page decides nothing — see the file header. Everything below either
     draws what the server sent, or derives the clock from it. */

  var EVENT_TITLE = @json($event->title);
  var EVENT_LOGO  = @json($event->tenant?->logo ? file_url($event->tenant->logo) : null);
  var COURT       = @json($court);
  var MAT_WORD    = @json(__('sport-brazilianjiujitsu::messages.mat'));

  var STATUS = @json($statusWords);
  var WARNING_LABEL = @json(__('event-bjj_tournament::messages.status_warning'));
  var METHODS = @json($methodWords);
  var SOURCES = @json($sourceWords);
  var PENALTY_REASONS = @json($penaltyWords);
  var WON_BY = @json(__('event-bjj_tournament::messages.won_by'));
  var PENALTY_NOTICE = @json(__('event-bjj_tournament::messages.penalty_notice'));
  var CORNER_BLUE = @json(__('sport-brazilianjiujitsu::messages.corner_blue'));
  var CORNER_WHITE = @json(__('sport-brazilianjiujitsu::messages.corner_white'));
  var TBD = @json(__('event-bjj_tournament::messages.court_tbd'));
  var DECIDED = @json($decidedWords);

  /* ── Scaling ───────────────────────────────────────────────────────────
     One transform for the whole stage, so 720p is exactly ×0.667 and 1440p
     exactly ×1.333 — nothing in the layout is recomputed per size, which is
     what keeps the spec's pixel measurements true at every output. */
  function fit() {
    var s = Math.min(window.innerWidth / 1920, window.innerHeight / 1080);
    document.getElementById('stage').style.setProperty('--stage-scale', s);
  }
  window.addEventListener('resize', fit);
  fit();

  function el(id) { return document.getElementById(id); }
  function text(id, v) { var e = el(id); if (e && e.textContent !== String(v == null ? '' : v)) e.textContent = v == null ? '' : v; }

  /** Only ever an http(s) image, and always escaped into the url(). */
  function bg(id, url) {
    var e = el(id);
    if (!e) return;
    var safe = (typeof url === 'string' && /^https?:\/\//i.test(url)) ? url : null;
    e.style.backgroundImage = safe ? 'url("' + encodeURI(safe) + '")' : 'none';
  }

  function clock(seconds) {
    var s = Math.max(0, Math.ceil(seconds));
    return Math.floor(s / 60) + ':' + ('0' + (s % 60)).slice(-2);
  }

  /** Pips fill from the outside in on the white corner — the CSS mirrors them. */
  function pips(id, n, max) {
    var host = el(id);
    if (!host) return;
    var want = max || 3;
    while (host.children.length < want) { host.appendChild(Object.assign(document.createElement('i'), { className: 'pip' })); }
    for (var i = 0; i < host.children.length; i++) {
      host.children[i].className = 'pip' + (i < n ? ' on' : '');
    }
  }

  /* ── State ─────────────────────────────────────────────────────────── */
  var S = @json($state);
  var BOARD = @json($board);
  var recvAt = Date.now();
  var lastNoticeTs = 0;

  /**
   * The clock, derived rather than ticked.
   *
   * `remaining` is "how much was left when the server wrote this", and `at` is
   * when it wrote it — so elapsed time is measured against THIS device's own
   * clock from the moment the message arrived. A wall screen's wall-clock is
   * frequently wrong, and only the delta matters.
   */
  function remaining() {
    if (!S.running) return S.remaining;
    return Math.max(0, S.remaining - (Date.now() - recvAt) / 1000);
  }

  function paintClock() {
    var r = remaining();
    var warn = S.status === 'live' && r > 0 && r <= (S.rules && S.rules.warning ? S.rules.warning : 60);

    text('timer', clock(r));
    text('frozenTime', clock(r));
    el('timer').className = 'num' + (warn ? ' warn' : '');

    // The status line always carries a WORD. A colour on its own never says
    // anything on this screen.
    var label = warn ? WARNING_LABEL : (STATUS[S.status] || STATUS.idle);
    text('statusText', label);

    var dot = el('statusDot');
    dot.className = S.status === 'live' ? 'live' : '';
    dot.style.background = S.status === 'live'
      ? 'var(--status-confirmed)'
      : (S.status === 'review' ? 'var(--status-review)'
        : (S.status === 'paused' || S.status === 'medical' ? 'var(--text-secondary)' : 'var(--status-advantage)'));

    text('timerEyebrow', S.status === 'overtime'
      ? @json(__('event-bjj_tournament::messages.overtime_time'))
      : @json(__('event-bjj_tournament::messages.regulation_time')));
  }

  function paintBoard() {
    var b = S.blue || {}, w = S.white || {}, sc = S.score || {};

    text('hdrEvent', S.tournament || EVENT_TITLE);
    text('hdrEvent2', S.tournament || EVENT_TITLE);
    bg('hdrLogo', EVENT_LOGO); bg('hdrLogo2', EVENT_LOGO);
    text('hdrMat', (S.courtLabel || COURT));
    text('queueMat', (S.courtLabel || COURT));
    text('hdrMeta', S.division || '');
    text('hdrStage', S.stage || '');
    text('hdrNo', S.matchNo ? '#' + S.matchNo : '');
    text('ftrRef', S.referee || '');
    text('ftrRight', S.ruleset || '');

    [['blue', b], ['white', w]].forEach(function (pair) {
      var k = pair[0], c = pair[1];
      text(k + 'Name', (c.name || TBD).toUpperCase());
      text(k + 'Country', c.country || '');
      text(k + 'Club', c.club || '');
      // Their own face, and the drawn stand-in when there is none — never an
      // empty circle, which reads as a fault from ten metres.
      bg(k + 'Face', c.photo || c.fallback);
    });

    // Points, and ONLY points, in the big numeral.
    ['blue', 'white'].forEach(function (k) {
      var e = el(k + 'Score');
      var v = String(sc[k + 'Points'] || 0);
      if (e.textContent !== v) {
        e.textContent = v;
        e.classList.remove('pop'); void e.offsetWidth; e.classList.add('pop');
      }
    });

    // …and the two counters, apart and smaller, each with its own word.
    text('blueAdv', sc.blueAdvantages || 0);
    text('whiteAdv', sc.whiteAdvantages || 0);
    text('bluePen', sc.bluePenalties || 0);
    text('whitePen', sc.whitePenalties || 0);
    pips('blueAdvPips', sc.blueAdvantages || 0, 3);
    pips('whiteAdvPips', sc.whiteAdvantages || 0, 3);
    pips('bluePenPips', sc.bluePenalties || 0, 3);
    pips('whitePenPips', sc.whitePenalties || 0, 3);

    paintLastAction();
    paintClock();
  }

  /**
   * The footer's last-action card — and the penalty notice that replaces it for
   * four seconds.
   *
   * A penalty is the one thing the hall is TOLD about beyond the numbers, and
   * it is told in words: "PENALTY — BLUE · LACK OF COMBATIVENESS · TIME 3:12".
   * There is no public countdown and no stall row: the referee's own count is
   * private until they decide to give something.
   */
  function paintLastAction() {
    var host = el('ftrLast');
    var e = S.lastEvent;

    if (!e) { host.innerHTML = '<div id="lastCard"></div>'; return; }

    if (e.kind === 'penalty' && e.ts !== lastNoticeTs) {
      lastNoticeTs = e.ts;
      var notice = document.createElement('div');
      notice.id = 'notice';
      notice.textContent = PENALTY_NOTICE
        .replace(':corner', e.side === 'blue' ? CORNER_BLUE : CORNER_WHITE)
        .replace(':reason', (PENALTY_REASONS[e.source] || PENALTY_REASONS.other).toUpperCase())
        .replace(':time', clock(remaining()));
      host.innerHTML = '';
      host.appendChild(notice);
      setTimeout(function () { if (host.firstChild === notice) paintLastAction(); }, 4200);
      return;
    }

    var card = document.createElement('div');
    card.id = 'lastCard';
    card.textContent = e.kind === 'point'
      ? (e.side === 'blue' ? CORNER_BLUE : CORNER_WHITE) + ' · +' + e.value + ' · ' + (SOURCES[e.source] || '')
      : (e.kind === 'advantage'
          ? (e.side === 'blue' ? CORNER_BLUE : CORNER_WHITE) + ' · ' + @json(__('event-bjj_tournament::messages.advantages'))
          : '');
    host.innerHTML = '';
    host.appendChild(card);
  }

  function paintVs() {
    var b = S.blue || {}, w = S.white || {};
    text('vsTitle', S.tournament || EVENT_TITLE);
    bg('vsLogo', EVENT_LOGO);
    text('vsMat', S.courtLabel || COURT);
    text('vsDiv', [S.division, S.stage].filter(Boolean).join(' · '));
    text('vsBlueName', (b.name || TBD).toUpperCase());
    text('vsWhiteName', (w.name || TBD).toUpperCase());
    text('vsBlueMeta', [b.club, b.country, b.belt].filter(Boolean).join(' · '));
    text('vsWhiteMeta', [w.club, w.country, w.belt].filter(Boolean).join(' · '));
    bg('vsBlueCard', b.photo || b.fallback);
    bg('vsWhiteCard', w.photo || w.fallback);
  }

  function paintPaused() {
    var sc = S.score || {};
    var review = S.status === 'review';
    var medical = S.status === 'medical';

    text('pausedIcon', medical ? '✚' : '⏸');
    text('pausedTitle', (STATUS[S.status] || STATUS.paused));
    el('pausedChip').hidden = !review;
    text('pausedChip', review ? STATUS.review : '');
    text('pausedScore', CORNER_BLUE + ' ' + (sc.bluePoints || 0) + '  —  ' + (sc.whitePoints || 0) + ' ' + CORNER_WHITE);
    paintClock();
  }

  function paintWinner() {
    var sc = S.score || {};
    var side = S.winner;
    var c = (side === 'blue' ? S.blue : S.white) || {};

    text('winName', (c.name || '').toUpperCase());
    text('winClub', c.club || '');
    text('winDivision', S.division || '');
    bg('winFace', c.photo || c.fallback);

    // WHY they won, in words. `decidedBy` answers it for a match settled on the
    // numbers — which of the three counters actually broke the tie — and the
    // declared method answers it for everything else.
    var method = S.declared ? (METHODS[S.winMethod] || '') : null;
    text('winMethod', method
      ? WON_BY.replace(':method', method).toUpperCase()
      : (DECIDED[sc.decidedBy] || DECIDED.points));

    el('winScore').innerHTML = '';
    var b = document.createElement('span'); b.className = 'b'; b.textContent = sc.bluePoints || 0;
    var d = document.createElement('span'); d.className = 'd'; d.textContent = ' – ';
    var w = document.createElement('span'); w.className = 'w'; w.textContent = sc.whitePoints || 0;
    el('winScore').append(b, d, w);

    text('winPoints', (sc.bluePoints || 0) + ' – ' + (sc.whitePoints || 0));
    text('winAdv', (sc.blueAdvantages || 0) + ' – ' + (sc.whiteAdvantages || 0));
    text('winPen', (sc.bluePenalties || 0) + ' – ' + (sc.whitePenalties || 0));
  }

  function paintQueue() {
    var host = el('queueList');
    host.innerHTML = '';

    (BOARD.matches || []).forEach(function (m) {
      var row = document.createElement('div');
      row.className = 'qRow enter';

      var b = document.createElement('div'); b.className = 'qb'; b.textContent = (m.blueName || TBD);
      var mid = document.createElement('div'); mid.className = 'qMid';
      var no = document.createElement('div'); no.className = 'qNo num'; no.textContent = m.number ? '#' + m.number : '';
      var meta = document.createElement('div'); meta.className = 'qMeta';
      meta.textContent = [m.weightClass, m.stage].filter(Boolean).join(' · ');
      mid.append(no, meta);
      var w = document.createElement('div'); w.className = 'qw'; w.textContent = (m.whiteName || TBD);

      row.append(b, mid, w);
      host.appendChild(row);
    });

    text('queueMat', COURT);
    text('hdrEvent2', BOARD.event && BOARD.event.title ? BOARD.event.title : EVENT_TITLE);
    bg('hdrLogo2', EVENT_LOGO);
  }

  /* ── Which layer is up ─────────────────────────────────────────────── */
  var PINNED = @json($pinned);

  function show() {
    var finished = !!S.finished;
    var held = !!S.celebrationClosed;
    var waiting = !!S.awaitingDecision || S.status === 'review' || S.status === 'medical' || S.status === 'paused';

    // WHAT THIS SCREEN WAS HUNG UP TO BE comes first; the mat only decides for a
    // screen that was told to follow it.
    //
    // This used to read S.mode alone, and the pin reached only the socket
    // routing — so the choice an organiser made at pairing time had no effect on
    // what anybody in the hall actually saw. Both halves were wrong at once: a
    // screen paired as the SCOREBOARD showed the running order whenever the mat
    // was idle (which is most of the day), and a screen paired as UPCOMING
    // turned into a scoreboard the moment a match loaded. Karate and Taekwondo
    // never had this because they render a different page per pin; this package
    // draws one document, so the pin has to be honoured here.
    var pin = PINNED;                       // 'bout' | 'queue' | 'both'
    var idle = S.mode === 'upcoming';

    var onQueue = pin === 'queue' || (pin === 'both' && idle);
    var onIdle = pin === 'bout' && idle;    // the scoreboard's own between-matches card
    var onVs = ! onQueue && ! onIdle && S.mode === 'vs';
    var onBoard = ! onQueue && ! onIdle && ! onVs;

    el('queue').hidden = !onQueue;
    el('idle').hidden = !onIdle;
    el('vs').hidden = !onVs;
    el('board').hidden = !onBoard;

    // The celebration runs only once an official has said HOW the match ended.
    // Until then the wall holds — which for a level match at 0:00 is the paused
    // card, exactly as the design spec requires.
    el('winner').hidden = !(onBoard && finished && S.winner && !held);
    el('paused').hidden = !(onBoard && !finished && waiting);

    document.documentElement.setAttribute('data-theme', S.theme === 'venue' ? 'venue' : 'arena');

    if (onQueue) paintQueue();
    if (onVs) paintVs();
    if (onBoard) { paintBoard(); if (!el('paused').hidden) paintPaused(); if (!el('winner').hidden) paintWinner(); }
  }

  /**
   * One entry point for both payload shapes.
   *
   * The mat state and the running order are different documents on the server
   * and different shapes on the wire; they are told apart HERE by what they
   * contain, so the socket never has to know which endpoint answered it.
   */
  function update(payload) {
    if (!payload) return;

    if (payload.matches) { BOARD = payload; if (S.mode === 'upcoming') paintQueue(); return; }
    if (!payload.mode) return;

    S = payload;
    recvAt = Date.now();
    show();
  }

  // The clock is the only thing that moves on its own, and it is derived — so
  // this repaints, it does not count.
  setInterval(paintClock, 200);

  window.CourtBoard = {
    update: update,
    mode: function () { return S.mode || 'upcoming'; },
    pinned: PINNED,
    stale: function (on) { el('stale').classList.toggle('on', !!on); }
  };

  show();

@isset($statusUrl)
  // The heartbeat, and the unpair signal. Five seconds: when the socket cannot
  // get through (a WebView that will not run the client in a Worker, a venue
  // that blocks websockets) this poll IS the experience, and a screen that has
  // been unpaired must not take two minutes to notice.
  setInterval(function () {
    fetch(@json($statusUrl), { cache: 'no-store' })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (s) { if (s && s.claimed === false) window.location.reload(); })
      .catch(function () { /* offline — keep the match on screen */ });
  }, 5000);
@endisset
})();
</script>

@if (! empty($screenLink))
@include('event-bjj_tournament::partials.screen-link')
@endif
</body>
</html>
