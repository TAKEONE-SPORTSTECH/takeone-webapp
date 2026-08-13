{{--
    The Taekwondo mat screen: the introduction, and then the match.

    Two designs in one document, on purpose. The operator calls a match up and
    the VS arena introduces the two athletes; they start the clock and the arena
    wipes itself away to reveal the scoreboard already behind it. That
    transition is the point — as two pages it would be a navigation, and a wall
    screen that goes white between the introduction and the first point looks
    broken from ten metres.

    Like the queue board next door this is signage, not product UI: no layout,
    no chrome, no viewer, broadcast red/blue instead of the app's purple, and a
    stage authored at 1920x1080 that only ever SCALES.

    ── Where the markup comes from ─────────────────────────────────────────────
    Transcribed from the approved layouts in `drafts/TaeKwonDo WTF/`. The VS
    arena is byte-identical to the Karate folder's copy (same md5), so that
    layer is the same design in both sports and is kept in step with Karate's
    mat screen deliberately. The scoreboard is NOT: WT scores in rounds, counts
    gam-jeom instead of a penalty ladder, and has no senshu — so it is its own
    layout, from `Taekwondo Scoreboard - Standalone.html`. Treat the visual
    output as fixed and restyle by agreement, never as a side effect.

    It decides nothing. Every value comes from MatState over MQTT, and the only
    thing computed here is the clock, which is derived from `remaining`/`running`
    so two screens on the same mat cannot drift apart.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
{{-- The tab says what this screen IS. It used to say "Upcoming Matches" —
     the queue board's title, copied when this view was split off it — so an
     operator with three tabs open could not tell the scoreboard from the
     running order without clicking each one. --}}
<title>{{ __('event-taekwondo_tournament::messages.sb_page_title', ['court' => $court, 'event' => $event->title]) }}</title>

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
  src: url("{{ route('court-display.font', $slug.'-'.$subset.'.woff2', false) }}") format('woff2');
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

  /* ── The scoreboard's own loops (Taekwondo Scoreboard, verbatim) ─────────── */
  @keyframes timerUrgent { 0%,100% { color:#ff5548; } 50% { color:#fffdf5; } }
  @keyframes cardBreatheR { 0%,100% { box-shadow:0 30px 80px rgba(0,0,0,0.7), 0 0 60px oklch(0.6 0.22 25 / 0.35); } 50% { box-shadow:0 30px 80px rgba(0,0,0,0.7), 0 0 120px oklch(0.6 0.22 25 / 0.7); } }
  @keyframes cardBreatheB { 0%,100% { box-shadow:0 30px 80px rgba(0,0,0,0.7), 0 0 60px oklch(0.52 0.19 255 / 0.35); } 50% { box-shadow:0 30px 80px rgba(0,0,0,0.7), 0 0 120px oklch(0.52 0.19 255 / 0.7); } }
  @keyframes scorePop { 0% { transform:scale(1.9); opacity:0.2; } 55% { transform:scale(0.94); opacity:1; } 75% { transform:scale(1.05); } 100% { transform:scale(1); opacity:1; } }
  @keyframes scoreDrop { 0% { transform:scale(0.5) rotate(-4deg); opacity:0.2; } 60% { transform:scale(1.08); opacity:1; } 100% { transform:scale(1); opacity:1; } }
  @keyframes barIn { from { opacity:0; transform:translateY(70px); } to { opacity:1; transform:translateY(0); } }
  @keyframes cardInL { from { opacity:0; transform:translateX(-140px) rotate(-3deg); } to { opacity:1; transform:translateX(0) rotate(0); } }
  @keyframes cardInR { from { opacity:0; transform:translateX(140px) rotate(3deg); } to { opacity:1; transform:translateX(0) rotate(0); } }
  @keyframes topIn { from { opacity:0; transform:translateY(-60px); } to { opacity:1; transform:translateY(0); } }
  @keyframes foulBlink { 0%,100% { opacity:1; } 50% { opacity:0.3; } }
  @keyframes goldSweep { 0% { background-position:-200% 50%; } 100% { background-position:300% 50%; } }

  /* Not in the source: the winner stamp and its confetti. The design stops at
     the running match, but a match that ends and leaves the last score on the
     wall reads as a mat that has frozen. Same treatment as the Karate board so
     a hall running both sports does not see two different endings. */
  @keyframes stampIn { 0% { transform:translate(-50%,-50%) scale(2.6); opacity:0; filter:blur(10px); } 45% { transform:translate(-50%,-50%) scale(.96); opacity:1; filter:blur(0); } 60% { transform:translate(-50%,-50%) scale(1.04); } 100% { transform:translate(-50%,-50%) scale(1); opacity:1; } }
  @keyframes confettiFall { 0% { transform:translateY(-90px) rotate(0deg); opacity:1; } 100% { transform:translateY(1200px) rotate(760deg); opacity:.75; } }
  @keyframes calloutIn { 0% { transform:translate(-50%,-50%) scale(.2) rotate(-8deg); opacity:0; } 22% { transform:translate(-50%,-50%) scale(1.18) rotate(2deg); opacity:1; } 40% { transform:translate(-50%,-50%) scale(1) rotate(0); } 78% { opacity:1; transform:translate(-50%,-50%) scale(1.02); } 100% { transform:translate(-50%,-50%) scale(1.08); opacity:0; } }
  @keyframes ringBurst { 0% { transform:translate(-50%,-50%) scale(.15); opacity:.95; } 100% { transform:translate(-50%,-50%) scale(3.4); opacity:0; } }

  /* ── The VS arena's loops (VS Screen Arena — shared with Karate) ─────────── */
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

  /* The wipe that hands the screen from the introduction to the match. */
  @keyframes vsExit { from { opacity:1; transform:scale(1); } to { opacity:0; transform:scale(1.08); } }

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

  @media (prefers-reduced-motion: reduce) { #stage *, #stage { animation:none !important; } }
</style>
</head>
<body>

<div id="root"><div id="stage">

  {{-- ── Waiting ────────────────────────────────────────────────────────── --}}
  <div id="idle">
    <div class="t">{{ __('event-taekwondo_tournament::messages.court_idle_title') }}</div>
    <div class="s">{{ __('event-taekwondo_tournament::messages.court_idle_sub') }}</div>
  </div>

  {{-- ── The scoreboard ─────────────────────────────────────────────────── --}}
  <div id="sb" class="layer" hidden style="background:repeating-linear-gradient(115deg, #0b0b10 0 6px, #0d0d13 6px 12px); font-family:'Barlow Condensed',sans-serif; color:#e8e6e0; overflow:hidden;">
    <div style="position:absolute; inset:0; pointer-events:none; background:radial-gradient(100% 80% at 50% 30%, transparent 40%, rgba(0,0,0,0.6) 100%);"></div>

    {{-- Top bar: match number, event, court --}}
    <div style="position:absolute; top:0; left:0; right:0; height:110px; display:flex; align-items:center; background:rgba(6,6,9,0.95); border-bottom:2px solid oklch(0.85 0.16 85 / 0.6); z-index:6; animation:topIn 0.6s cubic-bezier(0.22,1,0.36,1) both;">
      <div style="display:flex; gap:12px; padding:0; width:300px; flex:0 0 auto; justify-content:center; border-right:1px solid rgba(255,255,255,0.15); height:100%; align-items:center;">
        <span style="font-weight:700; font-size:30px; letter-spacing:0.22em; color:rgba(232,230,224,0.6); text-transform:uppercase;">{{ __('event-taekwondo_tournament::messages.court_match') }}</span>
        <span id="sbMatchNo" style="font-family:'Anton',sans-serif; font-size:54px; color:#fff;"></span>
      </div>
      <div id="sbTournament" style="flex:1; min-width:0; text-align:center; font-weight:700; font-size:40px; letter-spacing:0.24em; text-transform:uppercase; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; padding:0 20px; background:linear-gradient(100deg, #e8e6e0 40%, oklch(0.9 0.15 90) 50%, #e8e6e0 60%); background-size:250% 100%; -webkit-background-clip:text; background-clip:text; -webkit-text-fill-color:transparent; animation:goldSweep 5s linear infinite;"></div>
      <div style="display:flex; align-items:center; gap:12px; padding:0; width:300px; flex:0 0 auto; justify-content:center; border-left:1px solid rgba(255,255,255,0.15); height:100%;">
        <span style="font-weight:700; font-size:30px; letter-spacing:0.22em; color:rgba(232,230,224,0.6); text-transform:uppercase;">{{ __('event-taekwondo_tournament::messages.sb_court') }}</span>
        <span id="sbCourt" style="font-family:'Anton',sans-serif; font-size:54px; color:#fff;"></span>
      </div>
    </div>

    {{-- Weight class ribbon --}}
    <div style="position:absolute; top:134px; left:0; right:0; display:flex; align-items:center; justify-content:center; gap:20px; z-index:6; animation:topIn 0.6s 0.15s cubic-bezier(0.22,1,0.36,1) both;">
      <div style="height:2px; width:110px; background:linear-gradient(to left, oklch(0.85 0.16 85), transparent);"></div>
      <div id="sbWeight" style="font-family:'Anton',sans-serif; font-size:44px; letter-spacing:0.12em; text-transform:uppercase; color:oklch(0.85 0.16 85); white-space:nowrap;"></div>
      <div id="sbDiamond" style="width:10px; height:10px; transform:rotate(45deg); background:oklch(0.85 0.16 85);"></div>
      <div id="sbCategory" style="font-family:'Anton',sans-serif; font-size:44px; letter-spacing:0.12em; text-transform:uppercase; color:rgba(232,230,224,0.85); white-space:nowrap;"></div>
      <div style="height:2px; width:110px; background:linear-gradient(to right, oklch(0.85 0.16 85), transparent);"></div>
    </div>

    {{-- Score cards + centre clock --}}
    <div style="position:absolute; top:200px; left:0; right:0; height:570px; display:grid; grid-template-columns:1fr 500px 1fr; align-items:stretch; z-index:5;">
      <div style="margin:0 -50px 0 0; background:linear-gradient(155deg, oklch(0.55 0.21 25) 0%, oklch(0.34 0.13 25) 100%); clip-path:polygon(0 0, 100% 0, calc(100% - 90px) 100%, 0 100%); display:flex; flex-direction:column; align-items:center; justify-content:center; padding-right:60px; animation:cardInL 0.8s 0.25s cubic-bezier(0.22,1,0.36,1) both, cardBreatheR 3s 1.1s ease-in-out infinite;">
        <div style="font-weight:800; font-size:34px; letter-spacing:0.4em; text-transform:uppercase; color:rgba(255,255,255,0.75); margin-bottom:14px;">{{ __('event-taekwondo_tournament::messages.sb_hong') }}</div>
        <div id="sbAkaScore" style="font-family:'Anton',sans-serif; font-size:390px; line-height:0.95; color:#fffdf5; text-shadow:0 16px 70px rgba(0,0,0,0.55); font-variant-numeric:tabular-nums;">0</div>
        <div style="display:flex; align-items:center; gap:14px; margin-top:18px;">
          <span style="font-weight:700; font-size:26px; letter-spacing:0.24em; text-transform:uppercase; color:rgba(255,255,255,0.7);">{{ __('event-taekwondo_tournament::messages.sb_gamjeom') }}</span>
          <div id="sbAkaGam" style="display:flex; gap:9px;"></div>
        </div>
      </div>

      <div style="display:flex; flex-direction:column; align-items:center; justify-content:center; gap:10px; z-index:6;">
        <div id="sbRoundDots" style="display:flex; align-items:center; gap:12px;"></div>
        <div id="sbTimer" style="font-family:'Anton',sans-serif; font-size:230px; line-height:0.95; letter-spacing:0.02em; color:#fffdf5; font-variant-numeric:tabular-nums; text-shadow:0 14px 70px rgba(0,0,0,0.9);">2:00</div>
        <div id="sbPhase" style="font-family:'Anton',sans-serif; font-size:48px; letter-spacing:0.3em; padding-left:0.3em; text-transform:uppercase; color:oklch(0.85 0.16 85);"></div>
      </div>

      <div style="margin:0 0 0 -50px; background:linear-gradient(205deg, oklch(0.5 0.18 255) 0%, oklch(0.3 0.11 255) 100%); clip-path:polygon(0 0, 100% 0, 100% 100%, 90px 100%); display:flex; flex-direction:column; align-items:center; justify-content:center; padding-left:60px; animation:cardInR 0.8s 0.4s cubic-bezier(0.22,1,0.36,1) both, cardBreatheB 3s 1.2s ease-in-out infinite;">
        <div style="font-weight:800; font-size:34px; letter-spacing:0.4em; text-transform:uppercase; color:rgba(255,255,255,0.75); margin-bottom:14px;">{{ __('event-taekwondo_tournament::messages.sb_chung') }}</div>
        <div id="sbAoScore" style="font-family:'Anton',sans-serif; font-size:390px; line-height:0.95; color:#fffdf5; text-shadow:0 16px 70px rgba(0,0,0,0.55); font-variant-numeric:tabular-nums;">0</div>
        <div style="display:flex; align-items:center; gap:14px; margin-top:18px;">
          <span style="font-weight:700; font-size:26px; letter-spacing:0.24em; text-transform:uppercase; color:rgba(255,255,255,0.7);">{{ __('event-taekwondo_tournament::messages.sb_gamjeom') }}</span>
          <div id="sbAoGam" style="display:flex; gap:9px;"></div>
        </div>
      </div>
    </div>

    {{-- Athlete bars --}}
    <div style="position:absolute; left:0; right:0; bottom:0; height:290px; display:grid; grid-template-columns:1fr 1fr; z-index:5;">
      <div style="display:flex; align-items:center; gap:28px; background:linear-gradient(90deg, oklch(0.3 0.11 25) 0%, rgba(10,10,14,0.95) 85%); border-top:6px solid oklch(0.6 0.22 25); padding:0 40px 0 70px; min-width:0; animation:barIn 0.7s 0.55s cubic-bezier(0.22,1,0.36,1) both;">
        <div id="sbAkaLogo" style="width:170px; height:170px; flex:0 0 auto; border-radius:50%; background-color:rgba(255,255,255,0.1); background-size:cover; background-position:center; border:3px solid rgba(255,255,255,0.4); box-shadow:0 10px 40px rgba(0,0,0,0.6);"></div>
        <div style="min-width:0; display:flex; flex-direction:column; gap:8px;">
          <div id="sbAkaName" style="max-width:100%; font-family:'Anton',sans-serif; font-size:78px; line-height:1; text-transform:uppercase; color:#fff;"></div>
          <div style="display:flex; align-items:center; gap:16px; max-width:100%;">
            <div id="sbAkaFlag" style="width:84px; flex:0 0 auto; aspect-ratio:4/3; background-size:100% 100%; image-rendering:auto; background-position:center; border:2px solid rgba(255,255,255,0.45);"></div>
            <div id="sbAkaCountry" style="font-weight:700; font-size:44px; letter-spacing:0.1em; text-transform:uppercase; color:rgba(255,255,255,0.9); white-space:nowrap;"></div>
            <div style="width:8px; height:8px; flex:0 0 auto; transform:rotate(45deg); background:oklch(0.85 0.16 85);"></div>
            <div id="sbAkaClub" style="font-weight:600; font-size:38px; letter-spacing:0.06em; text-transform:uppercase; color:rgba(232,230,224,0.75); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"></div>
          </div>
        </div>
      </div>
      <div style="display:flex; align-items:center; justify-content:flex-end; gap:28px; background:linear-gradient(270deg, oklch(0.28 0.1 255) 0%, rgba(10,10,14,0.95) 85%); border-top:6px solid oklch(0.52 0.19 255); padding:0 70px 0 40px; min-width:0; animation:barIn 0.7s 0.7s cubic-bezier(0.22,1,0.36,1) both;">
        <div style="min-width:0; display:flex; flex-direction:column; align-items:flex-end; gap:8px; text-align:right;">
          <div id="sbAoName" style="max-width:100%; font-family:'Anton',sans-serif; font-size:78px; line-height:1; text-transform:uppercase; color:#fff;"></div>
          <div style="display:flex; align-items:center; gap:16px; max-width:100%; flex-direction:row-reverse;">
            <div id="sbAoFlag" style="width:84px; flex:0 0 auto; aspect-ratio:4/3; background-size:100% 100%; image-rendering:auto; background-position:center; border:2px solid rgba(255,255,255,0.45);"></div>
            <div id="sbAoCountry" style="font-weight:700; font-size:44px; letter-spacing:0.1em; text-transform:uppercase; color:rgba(255,255,255,0.9); white-space:nowrap;"></div>
            <div style="width:8px; height:8px; flex:0 0 auto; transform:rotate(45deg); background:oklch(0.85 0.16 85);"></div>
            <div id="sbAoClub" style="font-weight:600; font-size:38px; letter-spacing:0.06em; text-transform:uppercase; color:rgba(232,230,224,0.75); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"></div>
          </div>
        </div>
        <div id="sbAoLogo" style="width:170px; height:170px; flex:0 0 auto; border-radius:50%; background-color:rgba(255,255,255,0.1); background-size:cover; background-position:center; border:3px solid rgba(255,255,255,0.4); box-shadow:0 10px 40px rgba(0,0,0,0.6);"></div>
      </div>
    </div>

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
      <div style="font-weight:800; font-size:21.6px; letter-spacing:0.35em; color:#fff; background:#c8382c;background:oklch(0.55 0.2 25); padding:5.4px 15.1px 5.4px 18.9px;">{{ __('event-taekwondo_tournament::messages.sb_hong') }}</div>
      <div style="display:flex; align-items:center; gap:15.1px;">
        <div id="vsRedFlag" style="width:56.2px; aspect-ratio:4/3; background-size:100% 100%; image-rendering:auto; background-position:center; border:1px solid rgba(255,255,255,0.35); box-shadow:0 4px 18px rgba(0,0,0,0.6);"></div>
        <div id="vsRedCountry" style="font-weight:700; font-size:32.4px; letter-spacing:0.28em; color:#e8b3ad; color:oklch(0.85 0.05 25);"></div>
      </div>
      <div id="vsRedName" style="max-width:100%; font-family:'Anton',sans-serif; font-size:71.3px; line-height:0.95; text-transform:uppercase; color:#fff; text-shadow:0 6px 30px rgba(0,0,0,0.8);"></div>
      <div style="display:flex; align-items:center; gap:13px; margin-top:4.3px;">
        <div id="vsRedLogo" style="width:69.1px; height:69.1px; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.2); border-radius:50%; background-size:cover; background-position:center;"></div>
        <div id="vsRedClub" style="font-weight:600; font-size:30.2px; letter-spacing:0.12em; text-transform:uppercase; color:rgba(232,230,224,0.9);"></div>
      </div>
      <div id="vsRedChips" style="display:flex; flex-wrap:wrap; gap:9.7px; margin-top:5.4px;"></div>
    </div>

    <div style="position:absolute; right:43.2px; bottom:118.8px; display:flex; flex-direction:column; align-items:flex-end; gap:13px; z-index:6; max-width:44%; min-width:0; text-align:right; animation:riseUp 0.8s 0.85s cubic-bezier(0.22,1,0.36,1) both;">
      <div style="font-weight:800; font-size:21.6px; letter-spacing:0.35em; color:#fff; background:#1f5aa8;background:oklch(0.5 0.16 255); padding:5.4px 15.1px 5.4px 18.9px;">{{ __('event-taekwondo_tournament::messages.sb_chung') }}</div>
      <div style="display:flex; align-items:center; gap:15.1px; flex-direction:row-reverse;">
        <div id="vsBlueFlag" style="width:56.2px; aspect-ratio:4/3; background-size:100% 100%; image-rendering:auto; background-position:center; border:1px solid rgba(255,255,255,0.35); box-shadow:0 4px 18px rgba(0,0,0,0.6);"></div>
        <div id="vsBlueCountry" style="font-weight:700; font-size:32.4px; letter-spacing:0.28em; color:#a9c6ea; color:oklch(0.85 0.05 255);"></div>
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
        <span style="font-weight:600; font-size:23.8px; letter-spacing:0.3em; color:rgba(232,230,224,0.65); text-transform:uppercase;">{{ __('event-taekwondo_tournament::messages.court_match') }}</span>
        <span id="vsMatchNo" style="font-family:'Anton',sans-serif; font-size:34.6px; color:#fff;"></span>
      </div>
      <div style="width:6px; height:6px; transform:rotate(45deg); background:#fdc436; background:oklch(0.85 0.16 85);"></div>
      <div style="display:flex; align-items:baseline; gap:8.6px; background:rgba(10,10,14,0.72); border:1px solid rgba(253,196,54,0.45); border:1px solid oklch(0.85 0.16 85 / 0.45); padding:10.8px 23.8px; backdrop-filter:blur(6px);">
        <span style="font-weight:600; font-size:23.8px; letter-spacing:0.3em; color:rgba(232,230,224,0.65); text-transform:uppercase;">{{ __('event-taekwondo_tournament::messages.court_court') }}</span>
        <span id="vsCourt" style="font-family:'Anton',sans-serif; font-size:34.6px; color:#fff;"></span>
      </div>
      <div style="width:6px; height:6px; transform:rotate(45deg); background:#fdc436; background:oklch(0.85 0.16 85);"></div>
      <div id="vsRefWrap" style="display:flex; align-items:baseline; gap:8.6px; background:rgba(10,10,14,0.72); border:1px solid rgba(253,196,54,0.45); border:1px solid oklch(0.85 0.16 85 / 0.45); padding:10.8px 23.8px; backdrop-filter:blur(6px);">
        <span style="font-weight:600; font-size:23.8px; letter-spacing:0.3em; color:rgba(232,230,224,0.65); text-transform:uppercase;">{{ __('event-taekwondo_tournament::messages.vs_referee') }}</span>
        <span id="vsReferee" style="font-weight:700; font-size:28.1px; letter-spacing:0.08em; color:#fff; text-transform:uppercase;"></span>
      </div>
    </div>
  </div>

  <div id="stale"><span>{{ __('event-taekwondo_tournament::messages.court_reconnecting') }}</span></div>
</div></div>

<script>
(function () {
  'use strict';

  var GAM_LIMIT = @json(\App\Events\Sports\Taekwondo\Tournament\Scoreboard\MatState::GAM_JEOM_LIMIT);
  var EVENT_TITLE = @json($event->title);
  var COURT = @json($court);
  var T = {
    time: @json(__('event-taekwondo_tournament::messages.sb_time')),
    start: @json(__('event-taekwondo_tournament::messages.sb_start')),
    stop: @json(__('event-taekwondo_tournament::messages.sb_stop')),
    rest: @json(__('event-taekwondo_tournament::messages.sb_rest')),
    golden: @json(__('event-taekwondo_tournament::messages.sb_golden')),
    round: @json(__('event-taekwondo_tournament::messages.sb_round')),
    winner: @json(__('event-taekwondo_tournament::messages.sb_winner'))
  };

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
     worse than small type. Measured, not guessed — and re-measured when a
     hidden layer becomes visible and once the webfonts have loaded, because
     Anton is far narrower than the fallback. */
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
    if (!n.clientWidth) return;
    if (n.getAttribute('data-fit') === spec.v) return;

    n.style.transform = 'none';
    var size = spec.max;
    n.style.fontSize = size + 'px';
    for (var i = 0; i < 120 && size > spec.min && n.scrollWidth > n.clientWidth; i++) {
      size -= 2;
      n.style.fontSize = size + 'px';
    }
    if (n.scrollWidth > n.clientWidth) {
      var ratio = Math.max(0.6, n.clientWidth / n.scrollWidth);
      n.style.transformOrigin = /Ao|Blue/.test(id) ? 'right center' : 'left center';
      n.style.transform = 'scaleX(' + ratio.toFixed(3) + ')';
    }
    n.setAttribute('data-fit', spec.v);
  }
  function refitNames() {
    Object.keys(nameSpec).forEach(function (id) {
      var n = el(id);
      if (n) n.removeAttribute('data-fit');
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
     therefore agree without either being authoritative, and one that joins
     halfway through a round is instantly right. */
  var S = null;
  var received = 0;

  function liveRemaining() {
    if (!S) return 0;
    if (!S.running) return S.remaining;
    return Math.max(0, S.remaining - (performance.now() - received) / 1000);
  }

  function paintClock() {
    if (!S || S.mode !== 'scoreboard') return;
    var t = liveRemaining();
    // The source turns the clock red for the last ten seconds. Only during a
    // round: a rest running out is not an emergency.
    var low = t <= 10 && t > 0 && S.running && S.phase !== 'rest';

    text('sbTimer', Math.floor(t / 60) + ':' + String(Math.floor(t % 60)).padStart(2, '0'));
    el('sbTimer').style.animation = low ? 'timerUrgent 0.9s ease-in-out infinite' : 'none';
    if (!low) el('sbTimer').style.color = '#fffdf5';

    // The phase line, which is where WT differs from a single-bout sport: the
    // hall has to be able to see whether this is a round, the break, or sudden
    // death, and which round it is.
    var label;
    if (S.matchOver) label = T.time;
    else if (S.phase === 'golden') label = T.golden;
    else if (S.phase === 'rest') label = T.rest;
    else if (S.finished || t <= 0) label = T.time;
    else label = T.round + ' ' + S.round;
    text('sbPhase', label);
  }
  setInterval(paintClock, 100);

  /* ── Gam-jeom diamonds, lit up to the count. ───────────────────────────── */
  function gamDots(id, n) {
    var host = el(id);
    if (!host) return;
    host.textContent = '';
    for (var i = 0; i < GAM_LIMIT; i++) {
      var on = i < n;
      var d = document.createElement('div');
      d.style.cssText = 'width:26px; height:26px; transform:rotate(45deg);' +
        'background:' + (on ? 'oklch(0.85 0.16 85)' : 'rgba(0,0,0,0.25)') + ';' +
        'border:3px solid ' + (on ? '#fffdf0' : 'rgba(255,255,255,0.4)') + ';' +
        // Only the newest one blinks — the source's own rule.
        (i === n - 1 ? 'animation:foulBlink 1.4s ease-in-out infinite;' : '');
      host.appendChild(d);
    }
  }

  /* ── Round boxes: one per round, the current one gold. ──────────────────
     A won round is marked, which the source does not do — it only highlights
     the current one. Without it the wall shows "Round 3" with no way to know
     whether the series is 1–1 or 2–0, which is the single most useful thing
     about a best-of. */
  function roundDots(s) {
    var host = el('sbRoundDots');
    if (!host) return;
    host.textContent = '';
    for (var i = 1; i <= s.rounds; i++) {
      var current = i === s.round && !s.matchOver;
      var d = document.createElement('div');
      d.style.cssText = 'width:52px; height:52px; display:flex; align-items:center; justify-content:center;' +
        "font-family:'Anton',sans-serif; font-size:32px;" +
        'color:' + (current ? '#141210' : (i < s.round ? 'rgba(232,230,224,0.4)' : 'rgba(232,230,224,0.8)')) + ';' +
        'background:' + (current ? 'oklch(0.85 0.16 85)' : 'rgba(8,8,12,0.8)') + ';' +
        'border:2px solid ' + (current ? '#fffdf0' : 'rgba(255,255,255,0.3)') + ';';
      d.textContent = i;
      host.appendChild(d);
    }

    // The series score, beside the boxes — "2 · 1", red then blue.
    var tally = document.createElement('div');
    tally.style.cssText = 'display:flex; align-items:center; gap:10px; margin-left:14px;' +
      "font-family:'Anton',sans-serif; font-size:34px;";
    var r = document.createElement('span'); r.style.color = 'oklch(0.65 0.22 25)'; r.textContent = s.akaRounds;
    var sep = document.createElement('span'); sep.style.color = 'rgba(232,230,224,0.5)'; sep.textContent = '·';
    var b = document.createElement('span'); b.style.color = 'oklch(0.62 0.19 255)'; b.textContent = s.aoRounds;
    tally.appendChild(r); tally.appendChild(sep); tally.appendChild(b);
    host.appendChild(tally);
  }

  /* ── The callout: the technique's name, once, on the scoring side. ─────── */
  var lastCallout = 0;
  function callout(ev) {
    if (!ev || ev.ts === lastCallout) return;
    lastCallout = ev.ts;

    var host = el('sbCallout');
    var colour = ev.side === 'aka' ? '#ff5548' : '#4d9aff';
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
    label.style.cssText = "font-family:'Anton',sans-serif; font-size:110px; line-height:1; color:#fff; white-space:nowrap;" +
      'text-shadow:0 0 80px ' + colour + ', 0 8px 30px rgba(0,0,0,.7);';
    label.textContent = ev.label || '';
    var plus = document.createElement('div');
    plus.style.cssText = "font-family:'Anton',sans-serif; font-size:72px; color:oklch(0.85 0.16 85); text-shadow:0 0 40px rgba(255,214,102,.7);";
    plus.textContent = '+' + ev.n;
    wrap.appendChild(label); wrap.appendChild(plus);
    host.appendChild(wrap);

    setTimeout(function () { if (lastCallout === ev.ts) host.textContent = ''; }, 1600);
  }

  /* ── The winner stamp, with confetti. ──────────────────────────────────── */
  var stamped = null;
  function winner(side, name) {
    if (stamped === side + name) return;      // do not restage on every push
    stamped = side + name;
    var host = el('sbWinner');
    host.hidden = false;
    host.textContent = '';
    var colour = side === 'aka' ? '#b3121f' : '#0d55b8';
    var palette = ['#ffd666', '#fff', '#ff5548', '#4d9aff'];

    for (var i = 0; i < 34; i++) {
      var c = document.createElement('div');
      c.style.cssText = 'position:absolute; left:' + (i * 2.9 % 100) + '%; top:-40px; width:' + (8 + (i % 5) * 3) + 'px;' +
        'height:' + (18 + (i % 4) * 5) + 'px; background:' + palette[i % 4] + ';' +
        'transform:rotate(' + (i * 37 % 360) + 'deg);' +
        'animation:confettiFall ' + (2.4 + (i % 5) * 0.4) + 's ' + ((i % 7) * 0.3) + 's linear infinite;';
      host.appendChild(c);
    }

    var stamp = document.createElement('div');
    stamp.style.cssText = 'position:absolute; left:50%; top:50%; transform:translate(-50%,-50%); display:flex; flex-direction:column;' +
      'align-items:center; gap:18px; animation:stampIn .8s cubic-bezier(.2,.8,.2,1) both;';
    var w = document.createElement('div');
    w.style.cssText = "font-family:'Barlow Condensed',sans-serif; font-size:44px; font-weight:700; letter-spacing:.5em; color:oklch(0.85 0.16 85); text-transform:uppercase;";
    w.textContent = T.winner;
    var plate = document.createElement('div');
    plate.style.cssText = 'background:linear-gradient(135deg,' + colour + ', #000); padding:26px 80px; box-shadow:0 0 120px ' + colour + ';';
    var n = document.createElement('div');
    n.style.cssText = "font-family:'Anton',sans-serif; font-size:140px; line-height:1; color:#fff; text-transform:uppercase;";
    n.textContent = name || '';
    plate.appendChild(n); stamp.appendChild(w); stamp.appendChild(plate);
    host.appendChild(stamp);
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
    return [c.age ? c.age + 'Y' : null, c.height ? c.height + 'cm' : null, c.weight ? c.weight + 'kg' : null]
      .filter(Boolean).join(' · ');
  }

  function paintVs(s) {
    var a = s.aka || {}, b = s.ao || {};
    text('vsEvent', s.tournament || EVENT_TITLE);
    text('vsStage', s.stage || '');
    text('vsWeight', s.division || '');
    text('vsMatchNo', s.matchNo || '');
    text('vsReferee', s.referee || '');
    el('vsRefWrap').hidden = !s.referee;
    text('vsCourt', COURT.replace(/[^0-9]/g, '') || COURT);

    [['Red', a], ['Blue', b]].forEach(function (pair) {
      var k = pair[0], c = pair[1];
      setName('vs' + k + 'Name', c.name || '', 71.3, 26);
      text('vs' + k + 'Club', c.club || '');
      text('vs' + k + 'Country', c.country || '');
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

  function score(id, value, prevKey) {
    var n = el(id);
    if (!n || n.textContent === String(value)) return;
    var went = Number(value) > Number(n.textContent || 0);
    n.textContent = value;
    n.style.animation = 'none'; void n.offsetWidth;
    // The source distinguishes them: a point landing pops, a correction drops.
    n.style.animation = (went ? 'scorePop' : 'scoreDrop') + ' 0.7s cubic-bezier(0.22,1,0.36,1) both';
  }

  function paintScoreboard(s) {
    var a = s.aka || {}, b = s.ao || {};
    text('sbTournament', s.tournament || EVENT_TITLE);
    text('sbMatchNo', s.matchNo || '');
    text('sbCourt', COURT.replace(/[^0-9]/g, '') || COURT);
    text('sbWeight', s.division || '');
    text('sbCategory', s.category || s.stage || '');
    // The ribbon's diamond separates two labels; with only one there is
    // nothing to separate.
    el('sbDiamond').style.visibility = (s.division && (s.category || s.stage)) ? 'visible' : 'hidden';

    [['Aka', a], ['Ao', b]].forEach(function (pair) {
      var k = pair[0], c = pair[1];
      setName('sb' + k + 'Name', c.name || '', 78, 30);
      text('sb' + k + 'Club', c.club || '');
      text('sb' + k + 'Country', c.country || '');
      bg('sb' + k + 'Flag', flagUrl(c.flag));
      bg('sb' + k + 'Logo', c.logo);
    });

    score('sbAkaScore', s.akaScore);
    score('sbAoScore', s.aoScore);
    gamDots('sbAkaGam', s.akaGam);
    gamDots('sbAoGam', s.aoGam);
    roundDots(s);

    if (s.matchOver && s.matchWinner) {
      winner(s.matchWinner, s.matchWinner === 'aka' ? a.name : b.name);
    } else {
      stamped = null;
      el('sbWinner').hidden = true;
      el('sbWinner').textContent = '';
    }

    callout(s.lastEvent);
  }

  /* ── The contract ─────────────────────────────────────────────────────── */
  var mode = null;
  function update(state) {
    if (!state || typeof state !== 'object') return;
    S = state;
    received = performance.now();

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
        el('vs').hidden = false;
        el('vs').style.animation = '';
      } else if (wasVs && mode === 'scoreboard') {
        // The handover: the introduction wipes itself off the scoreboard that
        // is already drawn behind it, rather than the page changing.
        el('vs').style.animation = 'vsExit .55s cubic-bezier(.4,0,1,1) both';
        setTimeout(function () { if (mode !== 'vs') el('vs').hidden = true; }, 560);
      } else {
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
    // endpoint: a screen showing a match is not showing the running order.
    mode: function () { return mode || 'upcoming'; },
    // 'bout' when this screen was hung up to be the mat's own board. It then
    // stays here between matches, on the idle card, instead of being handed
    // the running order — which it could not draw anyway.
    pinned: @json($pinned ?? false),
    stale: function (on) { document.getElementById('stale').classList.toggle('on', !!on); }
  };

  update(@json($state));
})();
</script>

@isset($screenLink)
@include('event-taekwondo_tournament::court-display.partials.screen-link')
@endisset
</body>
</html>
