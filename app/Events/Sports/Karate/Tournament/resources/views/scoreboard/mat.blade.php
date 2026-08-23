{{--
    The Karate mat screen: the introduction, and then the bout.

    Two designs in one document, on purpose. The operator calls a bout up and the
    VS arena introduces the two athletes; they call hajime and the arena wipes
    itself away to reveal the scoreboard already behind it. That transition is
    the point — as two pages it would be a navigation, and a wall screen that
    goes white between the introduction and the first point looks broken from
    ten metres.

    Like the queue board next door this is signage, not product UI: no layout, no
    chrome, no viewer, broadcast red/blue instead of the app's purple, and a
    stage authored at 1920x1080 that only ever SCALES. Markup and CSS are
    transcribed from the approved layouts in ../../Scoreboard/design/ — treat the
    visual output as fixed and restyle by agreement, never as a side effect.

    It decides nothing. Every value comes from MatState over MQTT, and the only
    thing computed here is the clock, which is derived from `remaining`/`running`
    so two screens on the same mat cannot drift apart.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ __('event-karate_tournament::messages.court_title') }}</title>

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
  {{-- Root-relative: the board must never ask an origin that isn't there. --}}
  src: url("{{ route('karate-court-display.font', $slug.'-'.$subset.'.woff2', false) }}") format('woff2');
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

  /* ── The scoreboard's own loops (design/scoreboard.source.html) ─────────── */
  @keyframes scorePop { 0% { transform:scale(1); } 30% { transform:scale(1.18); } 100% { transform:scale(1); } }
  @keyframes timerPulse { 0%,100% { color:#ff3b47; text-shadow:0 0 60px rgba(255,59,71,.6); } 50% { color:#ffc2c6; text-shadow:0 0 100px rgba(255,59,71,1); } }
  @keyframes senshuIn { 0% { transform:scale(.3); opacity:0; } 60% { transform:scale(1.15); opacity:1; } 100% { transform:scale(1); opacity:1; } }
  @keyframes winnerGlow { 0%,100% { opacity:.4; } 50% { opacity:1; } }
  @keyframes calloutIn { 0% { transform:translate(-50%,-50%) scale(.2) rotate(-8deg); opacity:0; } 22% { transform:translate(-50%,-50%) scale(1.18) rotate(2deg); opacity:1; } 40% { transform:translate(-50%,-50%) scale(1) rotate(0); } 78% { opacity:1; transform:translate(-50%,-50%) scale(1.02); } 100% { transform:translate(-50%,-50%) scale(1.08); opacity:0; } }
  @keyframes ringBurst { 0% { transform:translate(-50%,-50%) scale(.15); opacity:.95; } 100% { transform:translate(-50%,-50%) scale(3.4); opacity:0; } }
  @keyframes lightSweep { 0% { transform:translateX(-140%) skewX(-18deg); } 100% { transform:translateX(340%) skewX(-18deg); } }
  @keyframes confettiFall { 0% { transform:translateY(-90px) rotate(0deg); opacity:1; } 100% { transform:translateY(1200px) rotate(760deg); opacity:.75; } }
  /* The translate is carried through every step on purpose: a keyframe that
     animates `transform` REPLACES the element's own translate(-50%,-50%), and
     with fill-mode `both` the stamp would keep scale(1) and lose its centring
     for good — it hangs off the middle of the screen. Same flaw in the source. */
  @keyframes stampIn { 0% { transform:translate(-50%,-50%) scale(2.6); opacity:0; filter:blur(10px); } 45% { transform:translate(-50%,-50%) scale(.96); opacity:1; filter:blur(0); } 60% { transform:translate(-50%,-50%) scale(1.04); } 100% { transform:translate(-50%,-50%) scale(1); opacity:1; } }
  @keyframes vignettePulse { 0%,100% { opacity:.22; } 50% { opacity:.6; } }
  @keyframes introDrop { 0% { transform:translateY(-60px); opacity:0; } 100% { transform:translateY(0); opacity:1; } }
  @keyframes introSlideL { 0% { transform:translateX(-120px); opacity:0; } 100% { transform:translateX(0); opacity:1; } }
  @keyframes introSlideR { 0% { transform:translateX(120px); opacity:0; } 100% { transform:translateX(0); opacity:1; } }

  /* ── The VS arena's loops (design/vs-arena.source.html) ─────────────────── */
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

  /* The wipe that hands the screen from the introduction to the bout. This is
     the transition the whole single-document design exists for. */
  @keyframes vsExit { from { opacity:1; transform:scale(1); } to { opacity:0; transform:scale(1.08); } }

  .layer { position:absolute; inset:0; }
  [hidden] { display:none !important; }

  /* Waiting for a bout: the mat says so rather than showing an empty frame. */
  #idle { position:absolute; inset:0; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:18px;
          background:radial-gradient(120% 90% at 50% 40%, #16161f 0%, #0a0a0e 65%, #050507 100%); }
  #idle .t { font-family:'Anton',sans-serif; font-size:92px; letter-spacing:0.14em; text-transform:uppercase; color:rgba(232,230,224,0.22); }
  #idle .s { font-weight:600; font-size:34px; letter-spacing:0.3em; text-transform:uppercase; color:rgba(232,230,224,0.35); }

  #stale { position:absolute; right:60px; bottom:34px; display:none; align-items:center; gap:12px; padding:8px 20px;
           background:rgba(10,10,14,0.85); border:1px solid rgba(232,230,224,0.25); font-weight:600; font-size:22px;
           letter-spacing:0.2em; text-transform:uppercase; color:rgba(232,230,224,0.6); z-index:20; }
  #stale.on { display:flex; }

  @media (prefers-reduced-motion: reduce) { #stage *, #stage { animation:none !important; } }
</style>

{{-- The winner celebration — this package's own (resources/views/scoreboard).
     It brings its own faces, keyframes and painter; this board only calls it. --}}
@include('event-karate_tournament::scoreboard.winner-celebration')
</head>
<body>

<div id="root"><div id="stage">

  {{-- ── Waiting ────────────────────────────────────────────────────────── --}}
  <div id="idle">
    <div class="t">{{ __('event-karate_tournament::messages.court_idle_title') }}</div>
    <div class="s">{{ __('event-karate_tournament::messages.court_idle_sub') }}</div>
  </div>

  {{-- ── The scoreboard ─────────────────────────────────────────────────── --}}
  <div id="sb" class="layer" hidden style="background:#000; display:flex; flex-direction:column;">
    <div style="height:110px; background:#0a0b10; display:flex; align-items:center; justify-content:space-between; padding:0 48px; border-bottom:3px solid #1c1e28; animation:introDrop .6s cubic-bezier(.2,.8,.2,1) both;">
      <div style="display:flex; align-items:center; gap:26px;">
        <div id="sbLogo" style="width:72px;height:72px;border:2px dashed rgba(255,255,255,.3);border-radius:10px;display:flex;align-items:center;justify-content:center;font-family:monospace;font-size:12px;color:#7d8296;text-align:center;line-height:1.2;background-size:cover;background-position:center;">event<br>logo</div>
        <div id="sbTournament" style="font-size:44px; font-weight:700; letter-spacing:.05em; text-transform:uppercase;"></div>
      </div>
      <div style="display:flex; align-items:center; gap:36px; font-size:36px; font-weight:700; text-transform:uppercase;">
        <div style="display:flex; align-items:baseline; gap:12px;"><span style="color:#7d8296; font-size:26px; letter-spacing:.18em;">{{ __('event-karate_tournament::messages.court_match') }}</span><span id="sbMatchNo" style="font-family:'Anton',sans-serif;"></span></div>
        <div style="display:flex; align-items:baseline; gap:12px;"><span style="color:#7d8296; font-size:26px; letter-spacing:.18em;">{{ __('event-karate_tournament::messages.sb_tatami') }}</span><span id="sbCourt" style="font-family:'Anton',sans-serif;"></span></div>
        <div id="sbRound" style="background:#ffd666; color:#000; padding:6px 24px; border-radius:6px; font-size:32px; letter-spacing:.1em;"></div>
      </div>
    </div>

    <div style="flex:1; display:flex; position:relative;">
      {{-- AKA (red) --}}
      <div style="flex:1; min-width:0; overflow:hidden; background:linear-gradient(135deg,#b3121f 0%,#7c0a14 100%); position:relative; display:flex; flex-direction:column; padding:44px 30px 30px 56px; animation:introSlideL .7s cubic-bezier(.2,.8,.2,1) both;">
        <div id="sbAkaWin" hidden style="position:absolute; inset:0; border:14px solid #ffd666; animation:winnerGlow 1.2s ease-in-out infinite; pointer-events:none; z-index:3;"></div>
        <div style="display:flex; align-items:center; gap:28px;">
          <img id="sbAkaFlag" alt="" style="width:150px; height:100px; object-fit:fill; image-rendering:auto; border-radius:8px; box-shadow:0 8px 30px rgba(0,0,0,.5);">
          <div style="display:flex; flex-direction:column;">
            <div id="sbAkaCountry" style="max-width:690px; font-size:52px; font-weight:700; letter-spacing:.1em; text-transform:uppercase; line-height:1;"></div>
            <div style="font-size:34px; font-weight:600; letter-spacing:.3em; color:rgba(255,255,255,.65); margin-top:6px;">AKA</div>
          </div>
        </div>
        <div id="sbAkaName" style="max-width:100%; font-family:'Anton',sans-serif; font-size:120px; line-height:1; text-transform:uppercase; margin-top:36px; text-shadow:0 6px 30px rgba(0,0,0,.4); padding-bottom:14px;"></div>
        <div id="sbAkaClub" style="font-size:42px; font-weight:600; letter-spacing:.08em; text-transform:uppercase; color:rgba(255,255,255,.8); margin-top:10px;"></div>
        <div style="flex:1; display:flex; align-items:center; justify-content:center; align-self:stretch;">
          <div id="sbAkaScore" style="font-family:'Anton',sans-serif; font-size:400px; line-height:.9; color:#fff; text-shadow:0 10px 60px rgba(0,0,0,.5); font-variant-numeric:tabular-nums;">0</div>
        </div>
        <div style="display:flex; align-items:center; gap:24px; min-height:90px;">
          <div id="sbAkaSenshu" style="border-radius:10px; padding:12px 22px; font-size:30px; font-weight:700; letter-spacing:.14em;">SENSHU</div>
          <div id="sbAkaPen" style="display:flex; gap:10px;"></div>
        </div>
      </div>

      {{-- Clock --}}
      <div style="width:470px; background:#0a0b10; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:20px; position:relative; z-index:2; box-shadow:0 0 80px rgba(0,0,0,.8);">
        {{-- The weight class, ABOVE the clock and big.
             It used to be a 38px line in the header strip, sharing that band
             with the logo, the tournament, the bout number, the mat and the
             round — which is the one place on this board where nothing is
             readable from the far side of a hall. It is also the single fact a
             coach or a spectator looks for first ("is this my division?"), so it
             now sits in the column everybody is already watching, in the same
             face as the clock. --}}
        <div id="sbCategory" style="font-family:'Anton',sans-serif; font-size:84px; line-height:1; letter-spacing:.06em; color:#ffd666; text-transform:uppercase; white-space:nowrap; max-width:440px; text-align:center;"></div>
        <div id="sbTimer" style="font-family:'Anton',sans-serif; font-size:200px; line-height:1; letter-spacing:.02em; font-variant-numeric:tabular-nums; color:#fff;">3:00</div>
        <div style="width:340px; height:10px; background:rgba(255,255,255,.1); border-radius:5px; overflow:hidden;">
          <div id="sbBar" style="height:100%; width:100%; background:#ffd666; border-radius:5px; transition:width .12s linear, background .3s;"></div>
        </div>
        <div id="sbStatus" style="font-size:44px; font-weight:700; letter-spacing:.34em; color:#ffd666; text-transform:uppercase; margin-top:12px;"></div>
      </div>

      {{-- AO (blue) --}}
      <div style="flex:1; min-width:0; overflow:hidden; background:linear-gradient(225deg,#0d55b8 0%,#083473 100%); position:relative; display:flex; flex-direction:column; align-items:flex-end; text-align:right; padding:44px 56px 30px 30px; animation:introSlideR .7s cubic-bezier(.2,.8,.2,1) both;">
        <div id="sbAoWin" hidden style="position:absolute; inset:0; border:14px solid #ffd666; animation:winnerGlow 1.2s ease-in-out infinite; pointer-events:none; z-index:3;"></div>
        <div style="display:flex; align-items:center; gap:28px; flex-direction:row-reverse;">
          <img id="sbAoFlag" alt="" style="width:150px; height:100px; object-fit:fill; image-rendering:auto; border-radius:8px; box-shadow:0 8px 30px rgba(0,0,0,.5);">
          <div style="display:flex; flex-direction:column; align-items:flex-end;">
            <div id="sbAoCountry" style="max-width:690px; font-size:52px; font-weight:700; letter-spacing:.1em; text-transform:uppercase; line-height:1;"></div>
            <div style="font-size:34px; font-weight:600; letter-spacing:.3em; color:rgba(255,255,255,.65); margin-top:6px;">AO</div>
          </div>
        </div>
        <div id="sbAoName" style="max-width:100%; font-family:'Anton',sans-serif; font-size:120px; line-height:1; text-transform:uppercase; margin-top:36px; text-shadow:0 6px 30px rgba(0,0,0,.4); padding-bottom:14px;"></div>
        <div id="sbAoClub" style="font-size:42px; font-weight:600; letter-spacing:.08em; text-transform:uppercase; color:rgba(255,255,255,.8); margin-top:10px;"></div>
        <div style="flex:1; display:flex; align-items:center; justify-content:center; align-self:stretch;">
          <div id="sbAoScore" style="font-family:'Anton',sans-serif; font-size:400px; line-height:.9; color:#fff; text-shadow:0 10px 60px rgba(0,0,0,.5); font-variant-numeric:tabular-nums;">0</div>
        </div>
        <div style="display:flex; align-items:center; gap:24px; min-height:90px;">
          <div id="sbAoSenshu" style="border-radius:10px; padding:12px 22px; font-size:30px; font-weight:700; letter-spacing:.14em;">SENSHU</div>
          <div id="sbAoPen" style="display:flex; gap:10px;"></div>
        </div>
      </div>
    </div>

    {{-- The last fifteen seconds bleed red from the edges. --}}
    <div id="sbLow" hidden style="position:absolute; inset:0; pointer-events:none; z-index:5; background:radial-gradient(ellipse at center, transparent 52%, rgba(255,59,71,.55) 100%); animation:vignettePulse 1s ease-in-out infinite;"></div>
    <div id="sbCallout" style="position:absolute; z-index:9; pointer-events:none;"></div>
    <div id="sbWinner" hidden style="position:absolute; inset:0; z-index:8; pointer-events:none; background:rgba(0,0,0,.45); overflow:hidden;"></div>
  </div>

  {{-- ── The introduction, over the top of it ───────────────────────────── --}}
  <div id="vs" class="layer" hidden style="z-index:12; background:radial-gradient(120% 90% at 50% 40%, #16161f 0%, #0a0a0e 65%, #050507 100%); font-family:'Barlow Condensed',sans-serif; color:#e8e6e0; overflow:hidden;">
    <div style="position:absolute; inset:0 auto 0 0; width:56%; clip-path:polygon(0 0, 100% 0, 82% 100%, 0 100%); overflow:hidden; background:#2b1416;background:oklch(0.28 0.09 25); animation:panelL 0.9s cubic-bezier(0.22,1,0.36,1) both;">
      <div id="vsRedPhoto" style="position:absolute; inset:0; background-size:cover; background-position:center 12%; animation:slowDrift 18s ease-in-out infinite;"></div>
      <div style="position:absolute; inset:0; pointer-events:none; background:linear-gradient(115deg, rgba(154,55,48,0.55) 0%, transparent 55%);background:linear-gradient(115deg, oklch(0.45 0.18 25 / 0.55) 0%, transparent 55%);"></div>
      <div style="position:absolute; inset:0; pointer-events:none; background:linear-gradient(to top, rgba(5,5,7,0.95) 0%, rgba(5,5,7,0.72) 22%, rgba(5,5,7,0.3) 42%, transparent 62%), linear-gradient(to bottom, rgba(5,5,7,0.82) 0%, rgba(5,5,7,0.35) 18%, transparent 32%);"></div>
    </div>
    <div style="position:absolute; inset:0 0 0 auto; width:56%; clip-path:polygon(18% 0, 100% 0, 100% 100%, 0 100%); overflow:hidden; background:#10233f;background:oklch(0.28 0.09 255); animation:panelR 0.9s cubic-bezier(0.22,1,0.36,1) both;">
      <div id="vsBluePhoto" style="position:absolute; inset:0; background-size:cover; background-position:center 12%; animation:slowDrift 18s ease-in-out infinite reverse;"></div>
      <div style="position:absolute; inset:0; pointer-events:none; background:linear-gradient(245deg, rgba(40,86,152,0.55) 0%, transparent 55%);background:linear-gradient(245deg, oklch(0.45 0.15 255 / 0.55) 0%, transparent 55%);"></div>
      <div style="position:absolute; inset:0; pointer-events:none; background:linear-gradient(to top, rgba(5,5,7,0.95) 0%, rgba(5,5,7,0.72) 22%, rgba(5,5,7,0.3) 42%, transparent 62%), linear-gradient(to bottom, rgba(5,5,7,0.82) 0%, rgba(5,5,7,0.35) 18%, transparent 32%);"></div>
    </div>
    <div style="position:absolute; top:-6%; bottom:-6%; left:50%; width:3px; margin-left:-1.5px; transform:rotate(10.15deg); pointer-events:none; background:linear-gradient(to bottom, transparent, rgba(253,196,54,0.9) 20%, rgba(253,196,54,0.9) 80%, transparent);background:linear-gradient(to bottom, transparent, oklch(0.85 0.16 85 / 0.9) 20%, oklch(0.85 0.16 85 / 0.9) 80%, transparent); filter:blur(1px);"></div>

    <div style="position:absolute; left:43.2px; bottom:118.8px; display:flex; flex-direction:column; align-items:flex-start; gap:13px; z-index:6; max-width:44%; min-width:0; animation:riseUp 0.8s 0.7s cubic-bezier(0.22,1,0.36,1) both;">
      <div style="font-weight:800; font-size:21.6px; letter-spacing:0.35em; color:#fff; background:#c8382c;background:oklch(0.55 0.2 25); padding:5.4px 15.1px 5.4px 18.9px;">AKA · RED</div>
      <div style="display:flex; align-items:center; gap:15.1px;">
        <div id="vsRedFlag" style="width:56.2px; aspect-ratio:4/3; background-size:100% 100%; image-rendering:auto; background-position:center; border:1px solid rgba(255,255,255,0.35); box-shadow:0 4px 18px rgba(0,0,0,0.6);"></div>
        <div id="vsRedCountry" style="max-width:760px; font-weight:700; font-size:32.4px; letter-spacing:0.28em; text-transform:uppercase; color:#e8b3ad; color:oklch(0.85 0.05 25);"></div>
      </div>
      <div id="vsRedName" style="max-width:100%; font-family:'Anton',sans-serif; font-size:71.3px; line-height:0.95; text-transform:uppercase; color:#fff; text-shadow:0 6px 30px rgba(0,0,0,0.8);"></div>
      <div style="display:flex; align-items:center; gap:13px; margin-top:4.3px;">
        <div id="vsRedLogo" style="width:69.1px; height:69.1px; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.2); border-radius:50%; background-size:cover; background-position:center;"></div>
        <div id="vsRedClub" style="font-weight:600; font-size:30.2px; letter-spacing:0.12em; text-transform:uppercase; color:rgba(232,230,224,0.9);"></div>
      </div>
      <div id="vsRedChips" style="display:flex; flex-wrap:wrap; gap:9.7px; margin-top:5.4px;"></div>
    </div>

    <div style="position:absolute; right:43.2px; bottom:118.8px; display:flex; flex-direction:column; align-items:flex-end; gap:13px; z-index:6; max-width:44%; min-width:0; text-align:right; animation:riseUp 0.8s 0.85s cubic-bezier(0.22,1,0.36,1) both;">
      <div style="font-weight:800; font-size:21.6px; letter-spacing:0.35em; color:#fff; background:#1f5aa8;background:oklch(0.5 0.16 255); padding:5.4px 15.1px 5.4px 18.9px;">AO · BLUE</div>
      <div style="display:flex; align-items:center; gap:15.1px; flex-direction:row-reverse;">
        <div id="vsBlueFlag" style="width:56.2px; aspect-ratio:4/3; background-size:100% 100%; image-rendering:auto; background-position:center; border:1px solid rgba(255,255,255,0.35); box-shadow:0 4px 18px rgba(0,0,0,0.6);"></div>
        <div id="vsBlueCountry" style="max-width:760px; font-weight:700; font-size:32.4px; letter-spacing:0.28em; text-transform:uppercase; color:#a9c6ea; color:oklch(0.85 0.05 255);"></div>
      </div>
      <div id="vsBlueName" style="max-width:100%; font-family:'Anton',sans-serif; font-size:71.3px; line-height:0.95; text-transform:uppercase; color:#fff; text-shadow:0 6px 30px rgba(0,0,0,0.8);"></div>
      <div style="display:flex; align-items:center; gap:13px; margin-top:4.3px; flex-direction:row-reverse;">
        <div id="vsBlueLogo" style="width:69.1px; height:69.1px; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.2); border-radius:50%; background-size:cover; background-position:center;"></div>
        <div id="vsBlueClub" style="font-weight:600; font-size:30.2px; letter-spacing:0.12em; text-transform:uppercase; color:rgba(232,230,224,0.9);"></div>
      </div>
      <div id="vsBlueChips" style="display:flex; flex-wrap:wrap; gap:9.7px; margin-top:5.4px; justify-content:flex-end;"></div>
    </div>

    <div style="position:absolute; top:34.6px; left:50%; transform:translateX(-50%); display:flex; flex-direction:column; align-items:center; gap:10.8px; z-index:8; width:92%; pointer-events:none; animation:dropIn 0.8s 0.5s cubic-bezier(0.22,1,0.36,1) both;">
      <div id="vsEvent" style="font-weight:700; font-size:30.2px; letter-spacing:0.42em; text-transform:uppercase; color:rgba(232,230,224,0.92); text-align:center; text-shadow:0 2px 14px rgba(0,0,0,0.9);"></div>
      <div style="display:flex; align-items:center; gap:17.3px;">
        <div style="height:2px; width:64.8px; background:linear-gradient(to left, #fdc436, transparent);background:linear-gradient(to left, oklch(0.85 0.16 85), transparent);"></div>
        <div id="vsStage" style="font-family:'Anton',sans-serif; font-size:38.9px; letter-spacing:0.3em; padding-left:0.3em; color:#fdc436; color:oklch(0.85 0.16 85); text-transform:uppercase;"></div>
        <div style="height:2px; width:64.8px; background:linear-gradient(to right, #fdc436, transparent);background:linear-gradient(to right, oklch(0.85 0.16 85), transparent);"></div>
      </div>
      <div id="vsWeight" style="font-weight:600; font-size:28.1px; letter-spacing:0.3em; text-transform:uppercase; color:rgba(232,230,224,0.75);"></div>
    </div>

    <div style="position:absolute; top:50%; left:50%; transform:translate(-50%,-52%); z-index:7; pointer-events:none; display:flex; align-items:center; justify-content:center; animation:vsSlam 0.7s 1.1s cubic-bezier(0.22,1,0.36,1) both;">
      <div style="position:relative; font-family:'Anton',sans-serif; font-size:183.6px; font-style:italic; color:#fffdf5; line-height:1; animation:vsPulse 2.4s ease-in-out infinite; -webkit-text-stroke:2px rgba(253,196,54,0.6); -webkit-text-stroke:2px oklch(0.85 0.16 85 / 0.6); overflow:visible;">VS
        <div style="position:absolute; inset:-10% -20%; overflow:hidden; pointer-events:none;">
          <div style="position:absolute; top:0; bottom:0; width:34%; background:linear-gradient(to right, transparent, rgba(255,255,255,0.16), transparent); animation:shineSweep 5s ease-in-out infinite;"></div>
        </div>
      </div>
    </div>
    <div style="position:absolute; inset:0; background:#fff; opacity:0; pointer-events:none; z-index:9; animation:flashOut 0.9s 1.5s ease-out both;"></div>

    <div style="position:absolute; bottom:32.4px; left:50%; transform:translateX(-50%); display:flex; gap:17.3px; z-index:8; align-items:center; flex-wrap:wrap; justify-content:center; max-width:94%; animation:riseC 0.8s 1.3s cubic-bezier(0.22,1,0.36,1) both;">
      <div style="display:flex; align-items:baseline; gap:8.6px; background:rgba(10,10,14,0.72); border:1px solid rgba(253,196,54,0.45); border:1px solid oklch(0.85 0.16 85 / 0.45); padding:10.8px 23.8px; backdrop-filter:blur(6px);">
        <span style="font-weight:600; font-size:23.8px; letter-spacing:0.3em; color:rgba(232,230,224,0.65); text-transform:uppercase;">{{ __('event-karate_tournament::messages.court_match') }}</span>
        <span id="vsMatchNo" style="font-family:'Anton',sans-serif; font-size:34.6px; color:#fff;"></span>
      </div>
      <div style="width:6px; height:6px; transform:rotate(45deg); background:#fdc436; background:oklch(0.85 0.16 85);"></div>
      <div style="display:flex; align-items:baseline; gap:8.6px; background:rgba(10,10,14,0.72); border:1px solid rgba(253,196,54,0.45); border:1px solid oklch(0.85 0.16 85 / 0.45); padding:10.8px 23.8px; backdrop-filter:blur(6px);">
        <span style="font-weight:600; font-size:23.8px; letter-spacing:0.3em; color:rgba(232,230,224,0.65); text-transform:uppercase;">{{ __('event-karate_tournament::messages.court_court') }}</span>
        <span id="vsCourt" style="font-family:'Anton',sans-serif; font-size:34.6px; color:#fff;"></span>
      </div>
      <div style="width:6px; height:6px; transform:rotate(45deg); background:#fdc436; background:oklch(0.85 0.16 85);"></div>
      <div id="vsRefWrap" style="display:flex; align-items:baseline; gap:8.6px; background:rgba(10,10,14,0.72); border:1px solid rgba(253,196,54,0.45); border:1px solid oklch(0.85 0.16 85 / 0.45); padding:10.8px 23.8px; backdrop-filter:blur(6px);">
        <span style="font-weight:600; font-size:23.8px; letter-spacing:0.3em; color:rgba(232,230,224,0.65); text-transform:uppercase;">{{ __('event-karate_tournament::messages.vs_referee') }}</span>
        <span id="vsReferee" style="font-weight:700; font-size:28.1px; letter-spacing:0.08em; color:#fff; text-transform:uppercase;"></span>
      </div>
    </div>
  </div>

  <div id="stale"><span>{{ __('event-karate_tournament::messages.court_reconnecting') }}</span></div>
</div></div>

<script>
(function () {
  'use strict';

  var PEN = @json(\App\Events\Sports\Karate\Tournament\Scoreboard\MatState::PENALTIES);
  var CALLOUTS = @json(\App\Events\Sports\Karate\Tournament\Scoreboard\MatState::CALLOUTS);
  var EVENT_TITLE = @json($event->title);
  var EVENT_LOGO = @json($eventLogo);
  var COURT = @json($court);

  var root = document.getElementById('root');
  var el = function (id) { return document.getElementById(id); };

  // ── Stage scaling: the layout never reflows, it only scales. ─────────────
  function fit() {
    var r = root.getBoundingClientRect();
    if (r.width && r.height) root.style.setProperty('--stage-scale', Math.min(r.width / 1920, r.height / 1080));
  }
  (window.ResizeObserver ? new ResizeObserver(fit).observe(root) : window.addEventListener('resize', fit));
  fit();

  // ── Untrusted values ─────────────────────────────────────────────────────
  // Names, clubs and image paths were all typed by a person. Text goes in
  // through textContent only, and a URL is dropped unless it is one we would
  // have generated: a hall screen is a publication surface.
  function safeUrl(u) {
    return (typeof u === 'string' && /^(https?:\/\/|\/)[^"'()\\\s]*$/.test(u)) ? u : null;
  }
  function text(id, v) { var n = el(id); if (n) n.textContent = (v == null ? '' : String(v)); }
  function bg(id, url) {
    var n = el(id), u = safeUrl(url);
    if (!n) return;
    n.style.backgroundImage = u ? 'url("' + encodeURI(u) + '")' : 'none';
  }
  /* ── A competitor's name never takes a second line ──────────────────────
     It is the thing the hall reads first, and a name broken across two lines
     reads as two people. So the type SHRINKS to fit rather than wrapping, and
     it is never truncated: an ellipsis through somebody's name on a wall is
     worse than small type.

     Measured, not guessed — scrollWidth against clientWidth with the line
     forced to nowrap. Only re-measured when the name actually changes, when a
     hidden layer becomes visible (a hidden element has no width to measure
     against), and once the webfonts have loaded, because Anton is far narrower
     than the fallback and fitting to the wrong metrics sizes every name wrong. */
  var nameSpec = {};
  function setName(id, value, max, min) {
    var n = el(id);
    if (!n) return;
    nameSpec[id] = { v: value, max: max, min: min };
    if (n.textContent !== value) n.textContent = value;
    n.style.whiteSpace = 'nowrap';
    fitName(id);
  }
  function fitName(id) {
    var n = el(id), spec = nameSpec[id];
    if (!n || !spec) return;
    if (!n.clientWidth) return;              // not laid out yet — retry on show
    if (n.getAttribute('data-fit') === spec.v) return;

    // Step 1 — shrink the type. Measured against the panel's real width, which
    // is 639px on the scoreboard: a 24-character name lands at 40px.
    n.style.transform = 'none';
    var size = spec.max;
    n.style.fontSize = size + 'px';
    for (var i = 0; i < 120 && size > spec.min && n.scrollWidth > n.clientWidth; i++) {
      size -= 2;
      n.style.fontSize = size + 'px';
    }

    // Step 2 — a name long enough to still overflow at the floor is CONDENSED,
    // not cut. Broadcast graphics squeeze rather than truncate for the same
    // reason: half a competitor's name on a wall is a mistake, narrow type is
    // just narrow. Floored at 0.6 — past that it stops being readable and the
    // operator should shorten the name on the control page instead.
    if (n.scrollWidth > n.clientWidth) {
      var ratio = Math.max(0.6, n.clientWidth / n.scrollWidth);
      // Condense toward the edge the name is aligned to, so it never drifts
      // away from its own corner.
      n.style.transformOrigin = /Ao|Blue/.test(id) ? 'right center' : 'left center';
      n.style.transform = 'scaleX(' + ratio.toFixed(3) + ')';
    }

    n.setAttribute('data-fit', spec.v);
  }
  function refitNames() {
    Object.keys(nameSpec).forEach(function (id) {
      var n = el(id);
      if (n) n.removeAttribute('data-fit');   // re-measure from the top
      fitName(id);
    });
  }
  if (document.fonts && document.fonts.ready) document.fonts.ready.then(refitNames);

  function flagUrl(code) {
    return /^[a-z]{2}$/.test(String(code || '')) ? 'https://flagcdn.com/w1280/' + code + '.png' : null;
  }

  /* ── The clock ──────────────────────────────────────────────────────────
     Never told what to display — given `remaining` at a moment plus whether it
     is running, and it counts down locally. Two screens on the same mat
     therefore agree without either of them being authoritative, and one that
     joins halfway through a bout is instantly right. */
  var S = null;            // last state received
  var received = 0;        // performance.now() when it arrived

  function liveRemaining() {
    if (!S) return 0;
    if (!S.running) return S.remaining;
    return Math.max(0, S.remaining - (performance.now() - received) / 1000);
  }

  function paintClock() {
    if (!S || S.mode !== 'scoreboard') return;
    var t = liveRemaining();
    var low = t <= 15 && t > 0 && S.running;

    text('sbTimer', Math.floor(t / 60) + ':' + String(Math.floor(t % 60)).padStart(2, '0'));
    el('sbTimer').style.animation = low ? 'timerPulse 1s ease-in-out infinite' : 'none';
    el('sbTimer').style.color = low ? '' : '#fff';
    el('sbBar').style.width = (S.duration ? (t / S.duration * 100) : 0) + '%';
    el('sbBar').style.background = low ? '#ff3b47' : '#ffd666';
    el('sbLow').hidden = !low;

    // 'Time' the moment it hits zero, without waiting for the server to say so.
    var over = S.finished || t <= 0;
    text('sbStatus', over ? @json(__('event-karate_tournament::messages.sb_time'))
                          : (S.running ? @json(__('event-karate_tournament::messages.sb_hajime'))
                                       : @json(__('event-karate_tournament::messages.sb_yame'))));
  }
  setInterval(paintClock, 100);

  /* ── Penalty chips: C1 C2 C3 HC H, lit up to the current rung. ─────────── */
  var CHIP_ON = [
    { bg: '#ffd666', fg: '#000' }, { bg: '#ffd666', fg: '#000' }, { bg: '#ffd666', fg: '#000' },
    { bg: '#ff9636', fg: '#000' }, { bg: '#ff3b47', fg: '#fff' }
  ];
  function chips(id, n) {
    var host = el(id);
    if (!host) return;
    host.textContent = '';
    PEN.forEach(function (label, i) {
      var on = i < n, tone = CHIP_ON[i] || CHIP_ON[0];
      var c = document.createElement('div');
      c.style.cssText = 'width:72px; height:68px; border-radius:10px; display:flex; align-items:center; justify-content:center;' +
        "font-family:'Anton',sans-serif; font-size:30px;" +
        'background:' + (on ? tone.bg : 'rgba(0,0,0,.3)') + ';' +
        'color:' + (on ? tone.fg : 'rgba(255,255,255,.45)') + ';' +
        'border:2px solid ' + (on ? 'transparent' : 'rgba(255,255,255,.25)') + ';' +
        (on ? 'animation:senshuIn .4s both;' : '');
      c.textContent = label;
      host.appendChild(c);
    });
  }

  function senshu(id, on) {
    var n = el(id);
    if (!n) return;
    n.style.background = on ? '#ffd666' : 'rgba(0,0,0,.3)';
    n.style.color = on ? '#000' : 'rgba(255,255,255,.45)';
    n.style.border = '2px solid ' + (on ? 'transparent' : 'rgba(255,255,255,.25)');
    n.style.animation = on ? 'senshuIn .5s cubic-bezier(.2,.8,.2,1) both' : 'none';
  }

  /* ── The callout: YUKO / WAZA-ARI / IPPON, once, on the scoring side. ──── */
  var lastCallout = 0;
  /* ── Sound ───────────────────────────────────────────────────────────────
     What the hall hears: music under the introduction, a sting over the
     celebration, and a noise per scoring action. Every file is this EVENT's own,
     fetched through this screen's token — nothing is shipped with the app and
     nothing is shared between events.

     Three things make this less simple than new Audio().play():

     1. A browser will not play sound before the page has been interacted with,
        and nobody ever touches a screen on a wall. So a blocked play is expected
        rather than exceptional: the screen keeps the element, retries on the
        first touch or keypress it ever gets, and says so quietly in the corner
        until then. Silence with no explanation is the one outcome an operator
        cannot diagnose from across a hall.
     2. A missing slot is normal. An event that uploaded only a celebration track
        gets a 404 for the other five, and that is not an error to draw.
     3. Point sounds must not stack. Three points scored in four seconds is three
        overlapping copies of the same sting otherwise, which is worse than one. */
  var AUDIO_BASE = @json($audioBase ?? null);
  var sounds = {}, musicOn = null;

  function sound(slot) {
    if (!AUDIO_BASE) return null;
    if (sounds[slot] === undefined) {
      var a = new Audio(AUDIO_BASE + slot);
      a.preload = 'auto';
      // A 404 is the normal answer for a slot nobody uploaded. Remember that,
      // so the screen asks once and not on every point.
      a.addEventListener('error', function () { sounds[slot] = null; });
      sounds[slot] = a;
    }

    return sounds[slot];
  }

  /** A one-shot: rewound rather than layered, so rapid points never overlap. */
  function ping(slot) {
    var a = sound(slot);
    if (!a) return;

    try { a.pause(); a.currentTime = 0; } catch (e) {}

    var p = a.play();
    // Swallowed deliberately: a point that could not be heard is over, and
    // playing it late would announce the wrong moment.
    if (p && p.catch) p.catch(function () {});
  }

  /** Music: at most one track at a time, looping under the screen it belongs to. */
  function music(slot) {
    if (musicOn === slot) return;

    if (musicOn && sounds[musicOn]) {
      try { sounds[musicOn].pause(); sounds[musicOn].currentTime = 0; } catch (e) {}
    }

    musicOn = slot;
    if (!slot) return;

    var a = sound(slot);
    if (!a) { musicOn = null; return; }

    a.loop = true;
    attempt(a, slot);
  }

  /**
   * Try to play, and keep trying.
   *
   * A refusal is not always permanent. A kiosk grants autoplay through a
   * platform setting, and that setting can land a moment AFTER the page has
   * already tried and been refused — which is exactly what a screen looks like
   * when it boots straight into an introduction. One retry a second for the
   * first ten seconds costs nothing and turns "silent all bout" into "silent for
   * a second", with no human needed.
   *
   * Retries stop the moment it plays, when the track is no longer the one
   * wanted, or after ten seconds — at which point the corner note is the honest
   * answer and a tap is the only way through.
   */
  function attempt(a, slot, tries) {
    tries = tries || 0;

    var p = a.play();

    if (!p || !p.then) return;

    p.catch(function () {
       // Still the track this screen wants? A bout may have moved on while we
       // were being refused, and re-trying a stale one would talk over it.
       if (musicOn !== slot || tries >= 30) return;

       setTimeout(function () { attempt(a, slot, tries + 1); }, 1000);
     });
  }

  // Not the plan, just a free second chance: if this board ever does receive a
  // tap or a keypress, take it. The kiosks grant autoplay outright (the TV app
  // through the WebView, the screen through cog), and the retry loop above covers a
  // setting that lands late — so nothing on this screen ever ASKS to be touched.
  ['pointerdown', 'keydown'].forEach(function (evt) {
    window.addEventListener(evt, function unlock() {
      window.removeEventListener(evt, unlock);
      if (musicOn) { var a = sounds[musicOn]; if (a) { var p = a.play(); if (p && p.catch) p.catch(function () {}); } }
    }, { once: true });
  });

  function callout(ev) {
    if (!ev || ev.ts === lastCallout) return;
    lastCallout = ev.ts;

    // The noise goes with the callout, not with the score changing: the score
    // is also rewritten by a correction, a reload and a reconnect, and none of
    // those should make a sound in the hall.
    ping(ev.penalty ? 'foul' : ('point_' + Math.min(3, Math.max(1, ev.n || 1))));

    // A penalty is heard, not drawn. The board already shows the ladder as chips
    // beside the score, and a "+0" burst over the mat would say nothing.
    if (ev.penalty) return;

    var host = el('sbCallout');
    var colour = ev.side === 'aka' ? '#ff3b47' : '#4d9aff';
    host.textContent = '';
    host.style.left = (ev.side === 'aka' ? '25%' : '75%');
    host.style.top = '44%';

    var ring = document.createElement('div');
    ring.style.cssText = 'position:absolute; left:0; top:0; width:380px; height:380px; border-radius:50%; border:18px solid ' +
      colour + '; transform:translate(-50%,-50%); animation:ringBurst .9s cubic-bezier(.2,.8,.2,1) both;';
    host.appendChild(ring);

    var wrap = document.createElement('div');
    wrap.style.cssText = 'position:absolute; left:0; top:0; transform:translate(-50%,-50%); animation:calloutIn 1.5s cubic-bezier(.2,.8,.2,1) both;' +
      'display:flex; flex-direction:column; align-items:center; gap:4px;';
    var label = document.createElement('div');
    label.style.cssText = "font-family:'Anton',sans-serif; font-size:130px; line-height:1; color:#fff; white-space:nowrap;" +
      'text-shadow:0 0 80px ' + colour + ', 0 8px 30px rgba(0,0,0,.7);';
    label.textContent = CALLOUTS[ev.n] || '';
    var plus = document.createElement('div');
    plus.style.cssText = "font-family:'Anton',sans-serif; font-size:72px; color:#ffd666; text-shadow:0 0 40px rgba(255,214,102,.7);";
    plus.textContent = '+' + ev.n;
    wrap.appendChild(label); wrap.appendChild(plus);
    host.appendChild(wrap);

    setTimeout(function () { if (lastCallout === ev.ts) host.textContent = ''; }, 1600);
  }

  /* ── The winner stamp, with confetti. ──────────────────────────────────── */
  @php
      // Pre-assigned, never inline: Blade's bracket matcher chokes on an array
      // literal inside @json(), which is documented in CLAUDE.md and which this
      // view proved the hard way — it took the whole board down with a parse
      // error rather than failing quietly.
      $reasonLabels = [
          'hansoku' => __('event-karate_tournament::messages.end_reason_hansoku'),
          'shikkaku' => __('event-karate_tournament::messages.end_reason_shikkaku'),
          'kiken' => __('event-karate_tournament::messages.end_reason_kiken'),
          'medical' => __('event-karate_tournament::messages.end_reason_medical'),
          'no_show' => __('event-karate_tournament::messages.end_reason_no_show'),
          'other' => __('event-karate_tournament::messages.end_reason_other'),
      ];
  @endphp
  var WON_BY = @json(__('event-karate_tournament::messages.sb_won_by'));
  var REASONS = @json($reasonLabels);
  var WINNER_LABEL = @json(__('event-karate_tournament::messages.sb_winner'));

  /**
   * The celebration is the shared one — see components/winner-celebration. The
   * board decides nothing about it beyond who won and what to say about it: the
   * scene, its colours and its motion are the same on every mat of every sport.
   */
  function winner(side, competitor, reason) {
    var c = competitor || {};

    WinnerCelebration.paint(el('sbWinner'), {
      corner: side === 'aka' ? 'red' : 'blue',
      name: c.name || '',
      club: c.club || '',
      logo: c.logo || null,
      photo: c.photo || null,
      label: WINNER_LABEL,
      // WHY, when it was not the score.
      note: (reason && reason !== 'points') ? WON_BY.replace(':reason', REASONS[reason] || reason) : ''
    });
  }

  /* ── The introduction ─────────────────────────────────────────────────── */
  function chip(host, value, gold) {
    if (!value) return;   // an absent fact is hidden, never invented
    var d = document.createElement('div');
    d.style.cssText = 'font-weight:700; font-size:23.8px; letter-spacing:0.12em; text-transform:uppercase; padding:5.4px 14px;' +
      'background:rgba(10,10,14,0.62); border:1px solid ' + (gold ? 'rgba(253,196,54,0.6)' : 'rgba(255,255,255,0.25)') + ';' +
      'color:' + (gold ? '#e8c96a' : '#fff') + ';';
    d.textContent = value;
    host.appendChild(d);
  }

  function statLine(c) {
    // "24Y · 178cm · 74kg" — each part only when we actually have it.
    return [c.age ? c.age + 'Y' : null, c.height ? c.height + 'cm' : null, c.weight ? c.weight + 'kg' : null]
      .filter(Boolean).join(' · ');
  }

  function paintVs(s) {
    var a = s.aka || {}, b = s.ao || {};
    text('vsEvent', s.tournament || EVENT_TITLE);
    text('vsStage', s.stage || '');
    text('vsWeight', s.division || '');
    text('vsMatchNo', s.matchNo || '');
    // The chip is removed from the row when nobody is appointed, rather than
    // standing there empty — same rule the queue board follows.
    text('vsReferee', s.referee || '');
    el('vsRefWrap').hidden = !s.referee;
    text('vsCourt', COURT.replace(/[^0-9]/g, '') || COURT);

    [['Red', a], ['Blue', b]].forEach(function (pair) {
      var k = pair[0], c = pair[1];
      setName('vs' + k + 'Name', c.name || '', 71.3, 26);
      text('vs' + k + 'Club', c.club || '');
      // The country is spelled out — 'BAHRAIN', never 'BH' — so it is fitted
      // like the name is: a long one shrinks and then condenses rather than
      // running off the panel.
      setName('vs' + k + 'Country', c.country || '', 32.4, 18);
      bg('vs' + k + 'Flag', flagUrl(c.flag));
      bg('vs' + k + 'Logo', c.logo);
      bg('vs' + k + 'Photo', c.photo);
      var host = el('vs' + k + 'Chips');
      host.textContent = '';
      chip(host, c.record, false);
      chip(host, c.belt, true);
      chip(host, statLine(c), false);
    });
  }

  function paintScoreboard(s) {
    var a = s.aka || {}, b = s.ao || {};
    text('sbTournament', s.tournament || EVENT_TITLE);
    // The design ships a dashed "event logo" placeholder; a real crest replaces
    // it, and the placeholder stays when the club has none.
    if (EVENT_LOGO) {
      var lg = el('sbLogo');
      lg.textContent = '';
      lg.style.border = 'none';
      lg.style.backgroundImage = 'url("' + encodeURI(EVENT_LOGO) + '")';
    }
    text('sbCategory', s.division || '');
    var catEl = el('sbCategory');
    if (catEl) {
      var n = (s.division || '').length;
      catEl.style.fontSize = (n <= 9 ? 84 : n <= 14 ? 64 : n <= 22 ? 46 : 34) + 'px';
    }
    text('sbMatchNo', s.matchNo || '');
    text('sbCourt', COURT.replace(/[^0-9]/g, '') || COURT);
    text('sbRound', s.stage || '');

    [['Aka', a], ['Ao', b]].forEach(function (pair) {
      var k = pair[0], c = pair[1];
      setName('sb' + k + 'Name', c.name || '', 120, 38);
      text('sb' + k + 'Club', c.club || '');
      setName('sb' + k + 'Country', c.country || '', 52, 26);
      var f = el('sb' + k + 'Flag'), u = safeUrl(flagUrl(c.flag));
      f.style.visibility = u ? 'visible' : 'hidden';
      if (u) f.src = u;
    });

    var akaScore = el('sbAkaScore'), aoScore = el('sbAoScore');
    if (akaScore.textContent !== String(s.akaScore)) {
      akaScore.textContent = s.akaScore;
      akaScore.style.animation = 'none'; void akaScore.offsetWidth;
      akaScore.style.animation = 'scorePop .45s cubic-bezier(.2,.8,.2,1)';
    }
    if (aoScore.textContent !== String(s.aoScore)) {
      aoScore.textContent = s.aoScore;
      aoScore.style.animation = 'none'; void aoScore.offsetWidth;
      aoScore.style.animation = 'scorePop .45s cubic-bezier(.2,.8,.2,1)';
    }

    chips('sbAkaPen', s.akaPen); chips('sbAoPen', s.aoPen);
    senshu('sbAkaSenshu', s.akaSenshu); senshu('sbAoSenshu', s.aoSenshu);

    el('sbAkaWin').hidden = !(s.finished && s.akaLeads);
    el('sbAoWin').hidden = !(s.finished && s.aoLeads);

    // The stamp follows the scoring table's decision, not just the score: an
    // official who has put the celebration away has put it away for the hall
    // too. The winner's border glow above stays either way — the bout IS won,
    // and the wall should still say by whom.
    if (s.finished && (s.akaLeads || s.aoLeads) && !s.celebrationClosed) {
      winner(s.akaLeads ? 'aka' : 'ao', s.akaLeads ? a : b, s.winReason);
      // Over the confetti, and looping until the celebration is put away or the
      // next bout walks on. Closing it at the scoring table stops the music in
      // the hall too, which is the whole point of that button.
      music('winner_music');
    } else {
      WinnerCelebration.clear(el('sbWinner'));
      if (musicOn === 'winner_music') music(null);
    }

    callout(s.lastEvent);
  }

  /* ── The contract ─────────────────────────────────────────────────────── */
  var mode = null, primed = false;
  function update(state) {
    if (!state || typeof state !== 'object') return;
    S = state;
    received = performance.now();

    // The FIRST state a screen is handed is history, not news.
    //
    // A board reloads for all sorts of reasons — a resync from the table, an
    // uploaded sound, a dropped link coming back — and the state it wakes up to
    // still carries whatever point was scored last. Announcing it would fire the
    // callout and, now that there is sound, replay the point noise over a hall
    // for something that happened ten minutes ago. So the first update adopts
    // the last event as already-seen and says nothing about it.
    if (!primed) {
      primed = true;
      if (state.lastEvent && state.lastEvent.ts) lastCallout = state.lastEvent.ts;
    }

    if (state.mode === 'vs' || state.mode === 'scoreboard') {
      paintVs(state);
      paintScoreboard(state);
    }

    if (state.mode !== mode) {
      var wasVs = mode === 'vs';
      mode = state.mode;

      el('idle').hidden = mode !== 'upcoming';
      el('sb').hidden = mode !== 'scoreboard';

      if (mode === 'vs') {
        // The introduction has its own music, looping under it until the mat
        // moves on. Whatever is playing stops first — one track at a time.
        music('vs_music');
        el('vs').hidden = false;
        el('vs').style.animation = '';
      } else if (wasVs && mode === 'scoreboard') {
        // The handover: the introduction wipes itself off the scoreboard that
        // is already drawn behind it, rather than the page changing. Its music
        // goes with it — a bout is scored in silence unless something happens.
        //
        // Only ITS track, though. A bout ended from the introduction (an
        // opponent who never came) hands the wall straight to the celebration,
        // and the paint above has already started the celebration music — a
        // blanket stop here silenced it a frame after it began.
        if (musicOn === 'vs_music') music(null);
        el('vs').style.animation = 'vsExit .55s cubic-bezier(.4,0,1,1) both';
        setTimeout(function () { if (mode !== 'vs') el('vs').hidden = true; }, 560);
      } else {
        music(null);
        el('vs').hidden = true;
      }

      // The layer that just appeared had no width while it was hidden, so its
      // names could not be measured until now.
      refitNames();
    }

    paintClock();
  }

  window.CourtBoard = {
    update: update,
    // The socket asks this before drawing a queue nudge or picking a resync
    // endpoint: a screen showing a bout is not showing the running order.
    mode: function () { return mode || 'upcoming'; },
    // 'bout' when this screen was hung up to be the mat's own board. It then
    // stays here between bouts, on the idle card, instead of being handed the
    // running order — which it could not draw anyway, the two payloads being
    // different shapes.
    pinned: @json($pinned ?? false),
    stale: function (on) { document.getElementById('stale').classList.toggle('on', !!on); }
  };

  update(@json($state));

@isset($statusUrl)
  // ── Heartbeat ────────────────────────────────────────────────────────────
  // The same beat the queue board sends, for the same two reasons: the
  // organiser's console cannot otherwise tell a mat that is running from one
  // that was unplugged, and a screen that has been unpaired needs to notice by
  // itself and go back to its code.
  //
  // Five seconds, not the minute this used to be. The minute was sized on the
  // assumption that the realtime push always arrives first and this is only a
  // safety net — but when the push does not arrive (a WebView that cannot run
  // the client in a Worker, a venue that blocks websockets) the safety net IS
  // the experience, and unpairing a screen took two minutes to show. Twelve
  // requests a minute against a one-field endpoint is nothing next to that.
  setInterval(function () {
    fetch(@json($statusUrl), { cache: 'no-store' })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (s) { if (s && s.claimed === false) window.location.reload(); })
      .catch(function () { /* offline — keep the bout on screen */ });
  }, 5000);
@endisset
})();
</script>

@isset($screenLink)
@include('event-karate_tournament::court-display.partials.screen-link')
@endisset
</body>
</html>
