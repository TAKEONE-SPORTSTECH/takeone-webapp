{{--
    The mat screen's stylesheet — the fonts, the loops and the layer furniture.

    Extracted so the Blade document and the React island (Phase M3, behind
    `features.react_scoreboard`) draw from ONE file. Two copies of a design is
    how two paths silently diverge, and a flag that is meant to be reversible
    mid-event cannot afford that.

    Included by:
      · bjj/screen/mat.blade.php          the hand-written document
      · bjj/screen/mat-react.blade.php    the island's shell
--}}
<style>
@php
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
  {{-- Self-hosted and root-relative: a hall has no internet to ask Google for a
       face, and the board must never ask an origin that isn't there. --}}
  src: url("{{ route('bjj-screen.font', $slug.'-'.$subset.'.woff2', false) }}") format('woff2');
  unicode-range: {{ $range }};
}
@endforeach
@endforeach

  html, body { margin:0; padding:0; background:#000; overflow:hidden; }
  #root { position:absolute; inset:0; background:#050507; overflow:hidden; }
  #stage {
    position:absolute; left:50%; top:50%; width:1920px; height:1080px;
    transform: translate(-50%,-50%) scale(var(--stage-scale, 0.5)); transform-origin:center;
    font-family:'Barlow Condensed', sans-serif; color:#fff; overflow:hidden;
  }

  /* ── The scoreboard's own loops ─────────────────────────────────────────── */
  @keyframes scorePop { 0% { transform:scale(1); } 30% { transform:scale(1.18); } 100% { transform:scale(1); } }
  @keyframes timerPulse { 0%,100% { color:#ff3b47; text-shadow:0 0 60px rgba(255,59,71,.6); } 50% { color:#ffc2c6; text-shadow:0 0 100px rgba(255,59,71,1); } }
  @keyframes cellIn { 0% { transform:scale(.3); opacity:0; } 60% { transform:scale(1.15); opacity:1; } 100% { transform:scale(1); opacity:1; } }
  @keyframes winnerGlow { 0%,100% { opacity:.4; } 50% { opacity:1; } }
  @keyframes calloutIn { 0% { transform:translate(-50%,-50%) scale(.2) rotate(-8deg); opacity:0; } 22% { transform:translate(-50%,-50%) scale(1.18) rotate(2deg); opacity:1; } 40% { transform:translate(-50%,-50%) scale(1) rotate(0); } 78% { opacity:1; transform:translate(-50%,-50%) scale(1.02); } 100% { transform:translate(-50%,-50%) scale(1.08); opacity:0; } }
  @keyframes ringBurst { 0% { transform:translate(-50%,-50%) scale(.15); opacity:.95; } 100% { transform:translate(-50%,-50%) scale(3.4); opacity:0; } }
  @keyframes vignettePulse { 0%,100% { opacity:.22; } 50% { opacity:.6; } }
  @keyframes introDrop { 0% { transform:translateY(-60px); opacity:0; } 100% { transform:translateY(0); opacity:1; } }
  @keyframes introSlideL { 0% { transform:translateX(-120px); opacity:0; } 100% { transform:translateX(0); opacity:1; } }
  @keyframes introSlideR { 0% { transform:translateX(120px); opacity:0; } 100% { transform:translateX(0); opacity:1; } }
  @keyframes noticeIn { 0% { transform:translate(-50%,40px); opacity:0; } 12% { transform:translate(-50%,0); opacity:1; } 88% { transform:translate(-50%,0); opacity:1; } 100% { transform:translate(-50%,20px); opacity:0; } }

  /* ── The VS arena's loops ───────────────────────────────────────────────── */
  @keyframes vsPulse { 0%,100% { text-shadow:0 0 30px rgba(255,215,120,0.55), 0 0 90px rgba(255,170,60,0.3); transform:scale(1); } 50% { text-shadow:0 0 55px rgba(255,225,150,0.85), 0 0 140px rgba(255,180,70,0.5); transform:scale(1.03); } }
  @keyframes slowDrift { 0% { transform:translate3d(0,0,0) scale(1.02); } 50% { transform:translate3d(0,-1.2%,0) scale(1.05); } 100% { transform:translate3d(0,0,0) scale(1.02); } }
  @keyframes shineSweep { 0% { transform:translateX(-130%) skewX(-18deg); } 60%,100% { transform:translateX(230%) skewX(-18deg); } }
  @keyframes panelL { from { transform:translateX(-105%); } to { transform:translateX(0); } }
  @keyframes panelR { from { transform:translateX(105%); } to { transform:translateX(0); } }
  @keyframes riseUp { from { opacity:0; transform:translateY(43.2px); } to { opacity:1; transform:translateY(0); } }
  @keyframes dropIn { from { opacity:0; transform:translate(-50%,-32.4px); } to { opacity:1; transform:translate(-50%,0); } }
  @keyframes vsSlam { 0% { opacity:0; transform:translate(-50%,-52%) scale(3.4) rotate(-6deg); } 60% { opacity:1; transform:translate(-50%,-52%) scale(0.92) rotate(2deg); } 100% { opacity:1; transform:translate(-50%,-52%) scale(1) rotate(0); } }
  @keyframes flashOut { 0% { opacity:0; } 12% { opacity:0.85; } 100% { opacity:0; } }
  @keyframes riseC { from { opacity:0; transform:translate(-50%,43.2px); } to { opacity:1; transform:translate(-50%,0); } }
  /* The wipe that hands the screen from the introduction to the match. This is
     the transition the whole single-document design exists for. */
  @keyframes vsExit { from { opacity:1; transform:scale(1); } to { opacity:0; transform:scale(1.08); } }

  /* ── The running order's loops ──────────────────────────────────────────── */
  @keyframes rowEnterL { from { opacity:0; transform:translateX(-120px) skewX(-6deg); } to { opacity:1; transform:translateX(0) skewX(0); } }
  @keyframes rowEnterR { from { opacity:0; transform:translateX(120px) skewX(6deg); } to { opacity:1; transform:translateX(0) skewX(0); } }
  @keyframes platePop { 0% { opacity:0; transform:translate(-50%,-50%) scale(0.4); } 70% { opacity:1; transform:translate(-50%,-50%) scale(1.12); } 100% { opacity:1; transform:translate(-50%,-50%) scale(1); } }
  @keyframes headerIn { from { opacity:0; transform:translateY(-40px); } to { opacity:1; transform:translateY(0); } }
  @keyframes rowSweep { 0% { transform:translateX(-140%) skewX(-22deg); } 45%,100% { transform:translateX(320%) skewX(-22deg); } }
  /* Transform and opacity only — see the note in Karate's board: box-shadow,
     background-position and letter-spacing are paint (and tracking is layout)
     on every frame, and they are what made the same design stutter on a TV box.
     Anything added here follows the same rule. */
  @keyframes goldSlide { from { transform:translateX(0); } to { transform:translateX(-50%); } }
  @keyframes glowPulse { 0%,100% { opacity:0; } 50% { opacity:1; } }
  @keyframes readyBreath { 0%,100% { transform:scaleX(1); opacity:1; } 50% { transform:scaleX(1.08); opacity:0.75; } }
  @keyframes titleShimmer { 0% { background-position:-200% 50%; } 100% { background-position:300% 50%; } }
  @keyframes numBeat { 0%,100% { transform:scale(1); } 50% { transform:scale(1.14); } }

  .layer { position:absolute; inset:0; }
  [hidden] { display:none !important; }

  /* Waiting for a match: the mat says so rather than showing an empty frame. */
  #idle { position:absolute; inset:0; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:18px;
          background:radial-gradient(120% 90% at 50% 40%, #16161f 0%, #0a0a0e 65%, #050507 100%); }
  #idle .t { font-family:'Anton',sans-serif; font-size:92px; letter-spacing:0.14em; text-transform:uppercase; color:rgba(232,230,224,0.22); }
  #idle .s { font-weight:600; font-size:34px; letter-spacing:0.3em; text-transform:uppercase; color:rgba(232,230,224,0.35); }

  #stale { position:absolute; right:60px; bottom:34px; display:none; align-items:center; gap:12px; padding:8px 20px;
           background:rgba(10,10,14,0.85); border:1px solid rgba(232,230,224,0.25); font-weight:600; font-size:22px;
           letter-spacing:0.2em; text-transform:uppercase; color:rgba(232,230,224,0.6); z-index:20; }
  #stale.on { display:flex; }
  #stale .dot { width:12px; height:12px; border-radius:50%; background:#fb7c00; animation:numBeat 1.6s ease-in-out infinite; }

  @media (prefers-reduced-motion: reduce) { #stage *, #stage { animation:none !important; } }
</style>
