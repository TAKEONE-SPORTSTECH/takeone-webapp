{{--
    The Brazilian Jiu-Jitsu mat screen: the running order, the introduction, and
    then the match.

    ── Where this design came from ────────────────────────────────────────────
    It is the Karate hall design, brought across whole and re-fitted to this
    sport at the user's request (2026-09-04): the same 1920x1080 stage that only
    ever SCALES, the same broadcast typography (Anton over Barlow Condensed),
    the same VS arena, the same gold-plated running order, the same winner
    celebration. Karate's own files were not touched — every part of this is a
    COPY that now belongs to this package, and the two can diverge without
    either one moving the other (CLAUDE.md → Shared Stays Shared: the design is
    a rule-book-free surface, so a copy is the honest form here rather than a
    forked shared class).

    ── What is this sport's, and stayed this sport's ──────────────────────────
    The corners are BLUE and WHITE — never red. The big numeral is POINTS and
    only points; advantages and penalties are their own lit cells beneath it,
    because in jiu-jitsu they are separate ladders and summing them would be a
    different sport. Nothing on this wall is told about a stalling count or a
    pending disqualification: those are the referee's, and they live on the
    console until an official actually gives something.

    Four surfaces in ONE document, because the handover between them is the
    point: an operator calls a match up, the arena introduces the two athletes,
    they start the clock and the arena wipes itself off the scoreboard already
    drawn behind it. As four pages that would be four navigations, and a wall
    screen that goes white between them looks broken from ten metres.

    It decides nothing. Every value comes from MatState over MQTT and the only
    thing computed here is the clock, derived from `remaining`/`running` so two
    screens on one mat cannot drift apart.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="utf-8">
{{-- A screen is not a document: it is authored at one size and scaled to fit
     the glass, so there is nothing here to zoom INTO — magnifying it can only
     push part of the surface off the edge, which on a wall nobody can undo.
     This is the ONE place the house rule against `user-scalable=no` does not
     apply (mobile web must always pinch-zoom, WCAG 1.4.4): this is signage. --}}
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
<style>
  /* `pan-x pan-y`, NOT `manipulation`: manipulation still permits pinch-zoom
     (it only drops the double-tap delay), which is the gesture being refused. */
  html { -webkit-text-size-adjust: 100%; text-size-adjust: 100%; touch-action: pan-x pan-y; }
  body { touch-action: pan-x pan-y; }
</style>
{{-- The same refusal for the two zoom gestures a browser still offers even with
     the viewport above: Safari's pinch (`gesture*`) and ctrl+wheel. --}}
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
<title>{{ __('scoreboard::bjj_messages.board_title') }} · {{ $court }}</title>

{{-- The fonts, the loops and the layer furniture. Shared with the React
     island's shell so the two paths cannot drift apart. --}}
@include('scoreboard::bjj.screen.partials.board-styles')

{{-- The winner celebration — this package's own copy, with its own two corner
     palettes. It brings its own faces, keyframes and painter; this board only
     tells it who won and why. --}}
@include('scoreboard::bjj.scoreboard.winner-celebration')
</head>
<body>

<div id="root"><div id="stage">

  {{-- ── Waiting ────────────────────────────────────────────────────────────
       A scoreboard hung over one mat goes QUIET between matches. It does not
       become the running order — that is the corridor screen's job — and it
       does not fill the wall with branding either. Every mat in a hall reads
       the same way whichever sport is on it. --}}
  <div id="idle">
    <div class="t">{{ __('scoreboard::bjj_messages.court_idle_title') }}</div>
    <div class="s">{{ __('scoreboard::bjj_messages.court_idle_sub') }}</div>
  </div>

  {{-- ── The scoreboard ─────────────────────────────────────────────────────
       BLUE on the left, WHITE on the right, on every surface of this package.
       The white corner is a LIGHT panel with dark ink rather than a second
       saturated colour: it is the corner of a white gi, and from the back of a
       hall the two halves must be told apart by value, not only by hue. --}}
  <div id="sb" class="layer" hidden style="background:#000; display:flex; flex-direction:column;">
    <div style="height:110px; background:#0a0b10; display:flex; align-items:center; justify-content:space-between; padding:0 48px; border-bottom:3px solid #1c1e28; animation:introDrop .6s cubic-bezier(.2,.8,.2,1) both;">
      <div style="display:flex; align-items:center; gap:26px;">
        <div id="sbLogo" style="width:72px;height:72px;border:2px dashed rgba(255,255,255,.3);border-radius:10px;display:flex;align-items:center;justify-content:center;font-family:monospace;font-size:12px;color:#7d8296;text-align:center;line-height:1.2;background-size:cover;background-position:center;">event<br>logo</div>
        <div id="sbTournament" style="font-size:44px; font-weight:700; letter-spacing:.05em; text-transform:uppercase;"></div>
      </div>
      <div style="display:flex; align-items:center; gap:36px; font-size:36px; font-weight:700; text-transform:uppercase;">
        <div style="display:flex; align-items:baseline; gap:12px;"><span style="color:#7d8296; font-size:26px; letter-spacing:.18em;">{{ __('scoreboard::bjj_messages.court_match') }}</span><span id="sbMatchNo" style="font-family:'Anton',sans-serif;"></span></div>
        <div style="display:flex; align-items:baseline; gap:12px;"><span style="color:#7d8296; font-size:26px; letter-spacing:.18em;">{{ __('scoreboard::bjj_messages.court_court') }}</span><span id="sbCourt" style="font-family:'Anton',sans-serif;"></span></div>
        <div id="sbRound" style="background:#ffd666; color:#000; padding:6px 24px; border-radius:6px; font-size:32px; letter-spacing:.1em;"></div>
      </div>
    </div>

    <div style="flex:1; display:flex; position:relative;">
      {{-- BLUE --}}
      <div style="flex:1; min-width:0; overflow:hidden; background:linear-gradient(135deg,#1362d1 0%,#082a55 100%); position:relative; display:flex; flex-direction:column; padding:44px 30px 30px 56px; animation:introSlideL .7s cubic-bezier(.2,.8,.2,1) both;">
        <div id="sbBlueWin" hidden style="position:absolute; inset:0; border:14px solid #ffd666; animation:winnerGlow 1.2s ease-in-out infinite; pointer-events:none; z-index:3;"></div>
        <div style="display:flex; align-items:center; gap:28px;">
          <img id="sbBlueFlag" alt="" style="width:150px; height:100px; object-fit:fill; image-rendering:auto; border-radius:8px; box-shadow:0 8px 30px rgba(0,0,0,.5);">
          <div style="display:flex; flex-direction:column;">
            <div id="sbBlueCountry" style="max-width:690px; font-size:52px; font-weight:700; letter-spacing:.1em; text-transform:uppercase; line-height:1;"></div>
            <div style="font-size:34px; font-weight:600; letter-spacing:.3em; color:rgba(255,255,255,.65); margin-top:6px;">{{ __('sport-brazilianjiujitsu::messages.corner_blue') }}</div>
          </div>
        </div>
        <div id="sbBlueName" style="max-width:100%; font-family:'Anton',sans-serif; font-size:120px; line-height:1; text-transform:uppercase; margin-top:36px; text-shadow:0 6px 30px rgba(0,0,0,.4); padding-bottom:14px;"></div>
        <div id="sbBlueClub" style="font-size:42px; font-weight:600; letter-spacing:.08em; text-transform:uppercase; color:rgba(255,255,255,.8); margin-top:10px;"></div>
        {{-- POINTS, and only points. The other two ladders are counted
             separately below, because a jiu-jitsu score is three numbers and
             adding them together would be a different sport. --}}
        <div style="flex:1; display:flex; align-items:center; justify-content:center; align-self:stretch;">
          <div id="sbBlueScore" style="font-family:'Anton',sans-serif; font-size:400px; line-height:.9; color:#fff; text-shadow:0 10px 60px rgba(0,0,0,.5); font-variant-numeric:tabular-nums;">0</div>
        </div>
        <div style="display:flex; flex-direction:column; gap:12px; min-height:180px; justify-content:flex-end;">
          <div style="display:flex; align-items:center; gap:20px;">
            <div style="width:96px; font-size:26px; font-weight:700; letter-spacing:.2em; color:rgba(255,255,255,.7); text-transform:uppercase;">{{ __('scoreboard::bjj_messages.adv_short') }}</div>
            <div id="sbBlueAdv" style="display:flex; gap:10px;"></div>
          </div>
          <div style="display:flex; align-items:center; gap:20px;">
            <div style="width:96px; font-size:26px; font-weight:700; letter-spacing:.2em; color:rgba(255,255,255,.7); text-transform:uppercase;">{{ __('scoreboard::bjj_messages.pen_short') }}</div>
            <div id="sbBluePen" style="display:flex; gap:10px;"></div>
          </div>
        </div>
      </div>

      {{-- Clock --}}
      <div style="width:470px; background:#0a0b10; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:20px; position:relative; z-index:2; box-shadow:0 0 80px rgba(0,0,0,.8);">
        {{-- The division, ABOVE the clock and big: it is the single fact a coach
             or a spectator looks for first ("is this my division?"), so it sits
             in the column everybody is already watching, in the clock's face. --}}
        <div id="sbCategory" style="font-family:'Anton',sans-serif; font-size:84px; line-height:1; letter-spacing:.06em; color:#ffd666; text-transform:uppercase; white-space:nowrap; max-width:440px; text-align:center;"></div>
        <div id="sbTimer" style="font-family:'Anton',sans-serif; font-size:200px; line-height:1; letter-spacing:.02em; font-variant-numeric:tabular-nums; color:#fff;">0:00</div>
        <div style="width:340px; height:10px; background:rgba(255,255,255,.1); border-radius:5px; overflow:hidden;">
          <div id="sbBar" style="height:100%; width:100%; background:#ffd666; border-radius:5px; transition:width .12s linear, background .3s;"></div>
        </div>
        <div id="sbStatus" style="font-size:44px; font-weight:700; letter-spacing:.34em; color:#ffd666; text-transform:uppercase; margin-top:12px; text-align:center; line-height:1.1;"></div>
        <div id="sbPeriod" style="font-size:24px; font-weight:600; letter-spacing:.28em; color:#7d8296; text-transform:uppercase;"></div>
      </div>

      {{-- WHITE --}}
      <div style="flex:1; min-width:0; overflow:hidden; background:linear-gradient(225deg,#f4f7fb 0%,#9fadc0 100%); color:#0a0b10; position:relative; display:flex; flex-direction:column; align-items:flex-end; text-align:right; padding:44px 56px 30px 30px; animation:introSlideR .7s cubic-bezier(.2,.8,.2,1) both;">
        <div id="sbWhiteWin" hidden style="position:absolute; inset:0; border:14px solid #b3861f; animation:winnerGlow 1.2s ease-in-out infinite; pointer-events:none; z-index:3;"></div>
        <div style="display:flex; align-items:center; gap:28px; flex-direction:row-reverse;">
          <img id="sbWhiteFlag" alt="" style="width:150px; height:100px; object-fit:fill; image-rendering:auto; border-radius:8px; box-shadow:0 8px 30px rgba(0,0,0,.35);">
          <div style="display:flex; flex-direction:column; align-items:flex-end;">
            <div id="sbWhiteCountry" style="max-width:690px; font-size:52px; font-weight:700; letter-spacing:.1em; text-transform:uppercase; line-height:1;"></div>
            <div style="font-size:34px; font-weight:600; letter-spacing:.3em; color:rgba(10,11,16,.6); margin-top:6px;">{{ __('sport-brazilianjiujitsu::messages.corner_white') }}</div>
          </div>
        </div>
        <div id="sbWhiteName" style="max-width:100%; font-family:'Anton',sans-serif; font-size:120px; line-height:1; text-transform:uppercase; margin-top:36px; text-shadow:0 6px 30px rgba(255,255,255,.35); padding-bottom:14px;"></div>
        <div id="sbWhiteClub" style="font-size:42px; font-weight:600; letter-spacing:.08em; text-transform:uppercase; color:rgba(10,11,16,.72); margin-top:10px;"></div>
        <div style="flex:1; display:flex; align-items:center; justify-content:center; align-self:stretch;">
          <div id="sbWhiteScore" style="font-family:'Anton',sans-serif; font-size:400px; line-height:.9; color:#0a0b10; text-shadow:0 10px 60px rgba(255,255,255,.4); font-variant-numeric:tabular-nums;">0</div>
        </div>
        <div style="display:flex; flex-direction:column; gap:12px; min-height:180px; justify-content:flex-end; align-items:flex-end;">
          <div style="display:flex; align-items:center; gap:20px; flex-direction:row-reverse;">
            <div style="width:96px; font-size:26px; font-weight:700; letter-spacing:.2em; color:rgba(10,11,16,.65); text-transform:uppercase; text-align:right;">{{ __('scoreboard::bjj_messages.adv_short') }}</div>
            <div id="sbWhiteAdv" style="display:flex; gap:10px; flex-direction:row-reverse;"></div>
          </div>
          <div style="display:flex; align-items:center; gap:20px; flex-direction:row-reverse;">
            <div style="width:96px; font-size:26px; font-weight:700; letter-spacing:.2em; color:rgba(10,11,16,.65); text-transform:uppercase; text-align:right;">{{ __('scoreboard::bjj_messages.pen_short') }}</div>
            <div id="sbWhitePen" style="display:flex; gap:10px; flex-direction:row-reverse;"></div>
          </div>
        </div>
      </div>
    </div>

    {{-- The final minute bleeds red from the edges. --}}
    <div id="sbLow" hidden style="position:absolute; inset:0; pointer-events:none; z-index:5; background:radial-gradient(ellipse at center, transparent 52%, rgba(255,59,71,.55) 100%); animation:vignettePulse 1s ease-in-out infinite;"></div>
    <div id="sbCallout" style="position:absolute; z-index:9; pointer-events:none;"></div>
    {{-- A penalty is the one thing the hall is TOLD in words rather than shown
         as a number: which corner, what for, and at what time. Four seconds,
         then the board is a board again. --}}
    <div id="sbNotice" hidden style="position:absolute; left:50%; bottom:52px; transform:translateX(-50%); z-index:10; padding:16px 44px; background:rgba(10,10,14,.9); border:2px solid #ff3b47; color:#fff; font-size:38px; font-weight:700; letter-spacing:.14em; text-transform:uppercase; white-space:nowrap; animation:noticeIn 4.2s ease-out both;"></div>
    {{-- Held while an official has not yet said HOW the match ended: a level
         match at 0:00 is not a result, and the wall must not guess one. --}}
    <div id="sbHold" hidden style="position:absolute; inset:0; z-index:7; pointer-events:none; background:rgba(5,5,7,.72); display:flex; flex-direction:column; align-items:center; justify-content:center; gap:26px;">
      <div id="sbHoldTitle" style="font-family:'Anton',sans-serif; font-size:150px; line-height:1; letter-spacing:.08em; text-transform:uppercase; color:#ffd666;"></div>
      <div style="height:3px; width:520px; background:linear-gradient(to right, transparent, #ffd666, transparent);"></div>
      <div id="sbHoldClock" style="font-family:'Anton',sans-serif; font-size:120px; line-height:1; font-variant-numeric:tabular-nums; color:rgba(255,255,255,.85);"></div>
      <div id="sbHoldScore" style="font-size:40px; font-weight:600; letter-spacing:.24em; text-transform:uppercase; color:rgba(232,230,224,.75);"></div>
      <div id="sbHoldWait" style="font-size:30px; font-weight:600; letter-spacing:.3em; text-transform:uppercase; color:rgba(232,230,224,.45);">{{ __('scoreboard::bjj_messages.please_wait') }}</div>
    </div>
    <div id="sbWinner" hidden style="position:absolute; inset:0; z-index:8; pointer-events:none; background:rgba(0,0,0,.45); overflow:hidden;"></div>
  </div>

  {{-- ── The introduction, over the top of it ──────────────────────────────── --}}
  <div id="vs" class="layer" hidden style="z-index:12; background:radial-gradient(120% 90% at 50% 40%, #16161f 0%, #0a0a0e 65%, #050507 100%); font-family:'Barlow Condensed',sans-serif; color:#e8e6e0; overflow:hidden;">
    <div style="position:absolute; inset:0 auto 0 0; width:56%; clip-path:polygon(0 0, 100% 0, 82% 100%, 0 100%); overflow:hidden; background:#10233f; animation:panelL 0.9s cubic-bezier(0.22,1,0.36,1) both;">
      <div id="vsBluePhoto" style="position:absolute; inset:0; background-size:cover; background-position:center 12%; animation:slowDrift 18s ease-in-out infinite;"></div>
      <div style="position:absolute; inset:0; pointer-events:none; background:linear-gradient(115deg, rgba(40,86,152,0.55) 0%, transparent 55%);"></div>
      <div style="position:absolute; inset:0; pointer-events:none; background:linear-gradient(to top, rgba(5,5,7,0.95) 0%, rgba(5,5,7,0.72) 22%, rgba(5,5,7,0.3) 42%, transparent 62%), linear-gradient(to bottom, rgba(5,5,7,0.82) 0%, rgba(5,5,7,0.35) 18%, transparent 32%);"></div>
    </div>
    <div style="position:absolute; inset:0 0 0 auto; width:56%; clip-path:polygon(18% 0, 100% 0, 100% 100%, 0 100%); overflow:hidden; background:#2c3340; animation:panelR 0.9s cubic-bezier(0.22,1,0.36,1) both;">
      <div id="vsWhitePhoto" style="position:absolute; inset:0; background-size:cover; background-position:center 12%; animation:slowDrift 18s ease-in-out infinite reverse;"></div>
      <div style="position:absolute; inset:0; pointer-events:none; background:linear-gradient(245deg, rgba(226,232,240,0.42) 0%, transparent 55%);"></div>
      <div style="position:absolute; inset:0; pointer-events:none; background:linear-gradient(to top, rgba(5,5,7,0.95) 0%, rgba(5,5,7,0.72) 22%, rgba(5,5,7,0.3) 42%, transparent 62%), linear-gradient(to bottom, rgba(5,5,7,0.82) 0%, rgba(5,5,7,0.35) 18%, transparent 32%);"></div>
    </div>
    <div style="position:absolute; top:-6%; bottom:-6%; left:50%; width:3px; margin-left:-1.5px; transform:rotate(10.15deg); pointer-events:none; background:linear-gradient(to bottom, transparent, rgba(253,196,54,0.9) 20%, rgba(253,196,54,0.9) 80%, transparent); filter:blur(1px);"></div>

    <div style="position:absolute; left:43.2px; bottom:118.8px; display:flex; flex-direction:column; align-items:flex-start; gap:13px; z-index:6; max-width:44%; min-width:0; animation:riseUp 0.8s 0.7s cubic-bezier(0.22,1,0.36,1) both;">
      <div style="font-weight:800; font-size:21.6px; letter-spacing:0.35em; color:#fff; background:#1362d1; padding:5.4px 15.1px 5.4px 18.9px;">{{ __('sport-brazilianjiujitsu::messages.corner_blue') }}</div>
      <div style="display:flex; align-items:center; gap:15.1px;">
        <div id="vsBlueFlag" style="width:56.2px; aspect-ratio:4/3; background-size:100% 100%; image-rendering:auto; background-position:center; border:1px solid rgba(255,255,255,0.35); box-shadow:0 4px 18px rgba(0,0,0,0.6);"></div>
        <div id="vsBlueCountry" style="max-width:760px; font-weight:700; font-size:32.4px; letter-spacing:0.28em; text-transform:uppercase; color:#a9c6ea;"></div>
      </div>
      <div id="vsBlueName" style="max-width:100%; font-family:'Anton',sans-serif; font-size:71.3px; line-height:0.95; text-transform:uppercase; color:#fff; text-shadow:0 6px 30px rgba(0,0,0,0.8);"></div>
      <div style="display:flex; align-items:center; gap:13px; margin-top:4.3px;">
        <div id="vsBlueLogo" style="width:69.1px; height:69.1px; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.2); border-radius:50%; background-size:cover; background-position:center;"></div>
        <div id="vsBlueClub" style="font-weight:600; font-size:30.2px; letter-spacing:0.12em; text-transform:uppercase; color:rgba(232,230,224,0.9);"></div>
      </div>
      <div id="vsBlueChips" style="display:flex; flex-wrap:wrap; gap:9.7px; margin-top:5.4px;"></div>
    </div>

    <div style="position:absolute; right:43.2px; bottom:118.8px; display:flex; flex-direction:column; align-items:flex-end; gap:13px; z-index:6; max-width:44%; min-width:0; text-align:right; animation:riseUp 0.8s 0.85s cubic-bezier(0.22,1,0.36,1) both;">
      <div style="font-weight:800; font-size:21.6px; letter-spacing:0.35em; color:#0a0b10; background:#e6ebf2; padding:5.4px 15.1px 5.4px 18.9px;">{{ __('sport-brazilianjiujitsu::messages.corner_white') }}</div>
      <div style="display:flex; align-items:center; gap:15.1px; flex-direction:row-reverse;">
        <div id="vsWhiteFlag" style="width:56.2px; aspect-ratio:4/3; background-size:100% 100%; image-rendering:auto; background-position:center; border:1px solid rgba(255,255,255,0.35); box-shadow:0 4px 18px rgba(0,0,0,0.6);"></div>
        <div id="vsWhiteCountry" style="max-width:760px; font-weight:700; font-size:32.4px; letter-spacing:0.28em; text-transform:uppercase; color:#dfe6ef;"></div>
      </div>
      <div id="vsWhiteName" style="max-width:100%; font-family:'Anton',sans-serif; font-size:71.3px; line-height:0.95; text-transform:uppercase; color:#fff; text-shadow:0 6px 30px rgba(0,0,0,0.8);"></div>
      <div style="display:flex; align-items:center; gap:13px; margin-top:4.3px; flex-direction:row-reverse;">
        <div id="vsWhiteLogo" style="width:69.1px; height:69.1px; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.2); border-radius:50%; background-size:cover; background-position:center;"></div>
        <div id="vsWhiteClub" style="font-weight:600; font-size:30.2px; letter-spacing:0.12em; text-transform:uppercase; color:rgba(232,230,224,0.9);"></div>
      </div>
      <div id="vsWhiteChips" style="display:flex; flex-wrap:wrap; gap:9.7px; margin-top:5.4px; justify-content:flex-end;"></div>
    </div>

    <div style="position:absolute; top:34.6px; left:50%; transform:translateX(-50%); display:flex; flex-direction:column; align-items:center; gap:10.8px; z-index:8; width:92%; pointer-events:none; animation:dropIn 0.8s 0.5s cubic-bezier(0.22,1,0.36,1) both;">
      <div id="vsEvent" style="font-weight:700; font-size:30.2px; letter-spacing:0.42em; text-transform:uppercase; color:rgba(232,230,224,0.92); text-align:center; text-shadow:0 2px 14px rgba(0,0,0,0.9);"></div>
      <div style="display:flex; align-items:center; gap:17.3px;">
        <div style="height:2px; width:64.8px; background:linear-gradient(to left, #fdc436, transparent);"></div>
        <div id="vsStage" style="font-family:'Anton',sans-serif; font-size:38.9px; letter-spacing:0.3em; padding-left:0.3em; color:#fdc436; text-transform:uppercase;"></div>
        <div style="height:2px; width:64.8px; background:linear-gradient(to right, #fdc436, transparent);"></div>
      </div>
      <div id="vsWeight" style="font-weight:600; font-size:28.1px; letter-spacing:0.3em; text-transform:uppercase; color:rgba(232,230,224,0.75);"></div>
    </div>

    <div style="position:absolute; top:50%; left:50%; transform:translate(-50%,-52%); z-index:7; pointer-events:none; display:flex; align-items:center; justify-content:center; animation:vsSlam 0.7s 1.1s cubic-bezier(0.22,1,0.36,1) both;">
      <div style="position:relative; font-family:'Anton',sans-serif; font-size:183.6px; font-style:italic; color:#fffdf5; line-height:1; animation:vsPulse 2.4s ease-in-out infinite; -webkit-text-stroke:2px rgba(253,196,54,0.6); overflow:visible;">{{ __('scoreboard::bjj_messages.vs') }}
        <div style="position:absolute; inset:-10% -20%; overflow:hidden; pointer-events:none;">
          <div style="position:absolute; top:0; bottom:0; width:34%; background:linear-gradient(to right, transparent, rgba(255,255,255,0.16), transparent); animation:shineSweep 5s ease-in-out infinite;"></div>
        </div>
      </div>
    </div>
    <div style="position:absolute; inset:0; background:#fff; opacity:0; pointer-events:none; z-index:9; animation:flashOut 0.9s 1.5s ease-out both;"></div>

    <div style="position:absolute; bottom:32.4px; left:50%; transform:translateX(-50%); display:flex; gap:17.3px; z-index:8; align-items:center; flex-wrap:wrap; justify-content:center; max-width:94%; animation:riseC 0.8s 1.3s cubic-bezier(0.22,1,0.36,1) both;">
      <div style="display:flex; align-items:baseline; gap:8.6px; background:rgba(10,10,14,0.72); border:1px solid rgba(253,196,54,0.45); padding:10.8px 23.8px; backdrop-filter:blur(6px);">
        <span style="font-weight:600; font-size:23.8px; letter-spacing:0.3em; color:rgba(232,230,224,0.65); text-transform:uppercase;">{{ __('scoreboard::bjj_messages.court_match') }}</span>
        <span id="vsMatchNo" style="font-family:'Anton',sans-serif; font-size:34.6px; color:#fff;"></span>
      </div>
      <div style="width:6px; height:6px; transform:rotate(45deg); background:#fdc436;"></div>
      <div style="display:flex; align-items:baseline; gap:8.6px; background:rgba(10,10,14,0.72); border:1px solid rgba(253,196,54,0.45); padding:10.8px 23.8px; backdrop-filter:blur(6px);">
        <span style="font-weight:600; font-size:23.8px; letter-spacing:0.3em; color:rgba(232,230,224,0.65); text-transform:uppercase;">{{ __('scoreboard::bjj_messages.court_court') }}</span>
        <span id="vsCourt" style="font-family:'Anton',sans-serif; font-size:34.6px; color:#fff;"></span>
      </div>
      <div style="width:6px; height:6px; transform:rotate(45deg); background:#fdc436;"></div>
      <div id="vsRefWrap" style="display:flex; align-items:baseline; gap:8.6px; background:rgba(10,10,14,0.72); border:1px solid rgba(253,196,54,0.45); padding:10.8px 23.8px; backdrop-filter:blur(6px);">
        <span style="font-weight:600; font-size:23.8px; letter-spacing:0.3em; color:rgba(232,230,224,0.65); text-transform:uppercase;">{{ __('scoreboard::bjj_messages.vs_referee') }}</span>
        <span id="vsReferee" style="font-weight:700; font-size:28.1px; letter-spacing:0.08em; color:#fff; text-transform:uppercase;"></span>
      </div>
    </div>
  </div>

  {{-- ── The running order ──────────────────────────────────────────────────
       The corridor board, and what a mat screen pinned to `queue` shows all
       day. One document holds it with the match (this package draws four
       surfaces in one page), so the pin — not the mat — decides which is up. --}}
  <div id="queue" class="layer" hidden style="background:radial-gradient(120% 90% at 50% 40%, #16161f 0%, #0a0a0e 65%, #050507 100%); display:flex; flex-direction:column;">
    <div id="qHead" style="display:flex; align-items:center; justify-content:space-between; gap:30px; padding:34px 60px 24px; animation:headerIn 0.8s cubic-bezier(0.22,1,0.36,1) both;">
      <div style="flex:1; display:flex; align-items:center; gap:18px;">
        <div style="width:10px; height:64px; background:#1677FF;"></div>
        <div id="qEvent" style="font-weight:700; font-size:34px; letter-spacing:0.24em; text-transform:uppercase; color:rgba(232,230,224,0.85); max-width:520px;"></div>
      </div>
      <div style="display:flex; flex-direction:column; align-items:center; gap:4px;">
        <div style="font-family:'Anton',sans-serif; font-size:64px; letter-spacing:0.2em; padding-left:0.2em; text-transform:uppercase; white-space:nowrap; background:linear-gradient(100deg, #fdc436 40%, #fffdf0 50%, #fdc436 60%); background-size:200% 100%; -webkit-background-clip:text; background-clip:text; -webkit-text-fill-color:transparent; animation:titleShimmer 3.5s linear infinite;">{{ __('scoreboard::bjj_messages.court_title') }}</div>
        <div style="height:3px; width:100%; background:linear-gradient(to right, transparent, #fdc436, transparent);"></div>
      </div>
      <div style="flex:1; display:flex; justify-content:flex-end; align-items:center; gap:18px;">
        <div id="qCourtBadge" style="display:flex; align-items:baseline; gap:12px; background:rgba(10,10,14,0.72); border:1px solid rgba(253,196,54,0.5); padding:10px 28px;">
          <span style="font-weight:600; font-size:32px; letter-spacing:0.3em; color:rgba(232,230,224,0.65); text-transform:uppercase;">{{ __('scoreboard::bjj_messages.court_court') }}</span>
          <span id="qCourtNumber" style="font-family:'Anton',sans-serif; font-size:52px; color:#fff;"></span>
        </div>
        <div style="width:10px; height:64px; background:#e6ebf2;"></div>
      </div>
    </div>

    <div id="qRows" style="flex:1; min-height:0; display:flex; flex-direction:column; gap:16px; padding:0 60px 34px;"></div>
  </div>

  <div id="stale"><span class="dot"></span><span>{{ __('scoreboard::bjj_messages.court_reconnecting') }}</span></div>
</div></div>

{{-- ⚠️ Every one of these is pre-assigned rather than inlined into @json().
     Blade's bracket matcher chokes on an array literal inside @json(...) and
     the whole view then fails to compile with a misleading error — documented
     in CLAUDE.md, and this design's Karate original proved it the hard way. --}}
@php
    $statusWords = [
      'idle' => __('scoreboard::bjj_messages.status_idle'),
      'live' => __('scoreboard::bjj_messages.status_live'),
      'paused' => __('scoreboard::bjj_messages.status_paused'),
      'review' => __('scoreboard::bjj_messages.status_review'),
      'medical' => __('scoreboard::bjj_messages.status_medical'),
      'overtime' => __('scoreboard::bjj_messages.status_overtime'),
      'submission' => __('scoreboard::bjj_messages.status_submission'),
      'dq' => __('scoreboard::bjj_messages.status_dq'),
      'walkover' => __('scoreboard::bjj_messages.status_walkover'),
      'finished' => __('scoreboard::bjj_messages.status_finished'),
    ];

    $methodWords = [
      'submission' => __('scoreboard::bjj_messages.method_submission'),
      'points' => __('scoreboard::bjj_messages.method_points'),
      'decision' => __('scoreboard::bjj_messages.method_decision'),
      'dq' => __('scoreboard::bjj_messages.method_dq'),
      'walkover' => __('scoreboard::bjj_messages.method_walkover'),
      'medical' => __('scoreboard::bjj_messages.method_medical'),
      'forfeit' => __('scoreboard::bjj_messages.method_forfeit'),
    ];

    $sourceWords = [
      'takedown' => __('scoreboard::bjj_messages.source_takedown'),
      'sweep' => __('scoreboard::bjj_messages.source_sweep'),
      'knee_on_belly' => __('scoreboard::bjj_messages.source_knee_on_belly'),
      'guard_pass' => __('scoreboard::bjj_messages.source_guard_pass'),
      'mount' => __('scoreboard::bjj_messages.source_mount'),
      'back_control' => __('scoreboard::bjj_messages.source_back_control'),
    ];

    $penaltyWords = [
      'stalling' => __('scoreboard::bjj_messages.penalty_stalling'),
      'fleeing' => __('scoreboard::bjj_messages.penalty_fleeing'),
      'grip_infraction' => __('scoreboard::bjj_messages.penalty_grip_infraction'),
      'illegal_technique' => __('scoreboard::bjj_messages.penalty_illegal_technique'),
      'conduct' => __('scoreboard::bjj_messages.penalty_conduct'),
      'other' => __('scoreboard::bjj_messages.penalty_other'),
    ];

    $decidedWords = [
      'points' => __('scoreboard::bjj_messages.decided_by_points'),
      'advantages' => __('scoreboard::bjj_messages.decided_by_advantages'),
      'penalties' => __('scoreboard::bjj_messages.decided_by_penalties'),
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
  var ROWS        = @json(\App\Scoreboard\Sports\BrazilianJiuJitsu\HallScreen\ScreenBoard::ROWS);

  var STATUS = @json($statusWords);
  var METHODS = @json($methodWords);
  var SOURCES = @json($sourceWords);
  var PENALTY_REASONS = @json($penaltyWords);
  var DECIDED = @json($decidedWords);

  var WARNING_LABEL = @json(__('scoreboard::bjj_messages.status_warning'));
  var PENALTY_NOTICE = @json(__('scoreboard::bjj_messages.penalty_notice'));
  var WON_BY = @json(__('scoreboard::bjj_messages.won_by'));
  var WINNER_LABEL = @json(__('scoreboard::bjj_messages.winner'));
  var ADVANTAGE_WORD = @json(__('scoreboard::bjj_messages.advantages'));
  var CORNER_BLUE = @json(__('sport-brazilianjiujitsu::messages.corner_blue'));
  var CORNER_WHITE = @json(__('sport-brazilianjiujitsu::messages.corner_white'));
  var TBD = @json(__('scoreboard::bjj_messages.court_tbd'));
  var GET_READY = @json(__('scoreboard::bjj_messages.get_ready'));
  var MATCH_WORD = @json(__('scoreboard::bjj_messages.court_match'));
  var IDLE_T = @json(__('scoreboard::bjj_messages.court_idle_title'));
  var IDLE_S = @json(__('scoreboard::bjj_messages.court_idle_sub'));

  var root = document.getElementById('root');
  function el(id) { return document.getElementById(id); }

  /* ── Stage scaling ──────────────────────────────────────────────────────
     One transform for the whole stage, so 720p is exactly x0.667 and 1440p
     exactly x1.333 — nothing in the layout is recomputed per size, which is
     what keeps the design's pixel measurements true at every output. */
  function fit() {
    var r = root.getBoundingClientRect();
    if (r.width && r.height) root.style.setProperty('--stage-scale', Math.min(r.width / 1920, r.height / 1080));
  }
  (window.ResizeObserver ? new ResizeObserver(fit).observe(root) : window.addEventListener('resize', fit));
  fit();

  /* ── Untrusted values ───────────────────────────────────────────────────
     Names, clubs and image paths were all typed by a person. Text goes in
     through textContent only, and a URL is dropped unless it is one we would
     have generated: a hall screen is a publication surface. */
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
     hidden layer becomes visible (a hidden element has no width) and once the
     webfaces have loaded, because Anton is far narrower than the fallback. */
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

    n.style.transform = 'none';
    var size = spec.max;
    n.style.fontSize = size + 'px';
    for (var i = 0; i < 120 && size > spec.min && n.scrollWidth > n.clientWidth; i++) {
      size -= 2;
      n.style.fontSize = size + 'px';
    }

    // A name long enough to still overflow at the floor is CONDENSED, not cut,
    // toward the edge it is aligned to.
    if (n.scrollWidth > n.clientWidth) {
      var ratio = Math.max(0.6, n.clientWidth / n.scrollWidth);
      n.style.transformOrigin = /White/.test(id) ? 'right center' : 'left center';
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

  // TODO(offline): flags come from flagcdn. Harmless on 4G, but the screen
  // agent should mirror them to disk so a dead uplink never empties them.
  function flagUrl(code) {
    return /^[a-z]{2}$/.test(String(code || '')) ? 'https://flagcdn.com/w1280/' + code + '.png' : null;
  }

  /* ── The clock ──────────────────────────────────────────────────────────
     Never told what to display — given `remaining` at a moment plus whether it
     is running, and it counts down locally against THIS device's own clock. A
     wall screen's wall-clock is frequently wrong and only the delta matters, so
     two screens on one mat agree without either being authoritative. */
  var S = @json($state);
  var BOARD = @json($board);
  var received = performance.now();

  function liveRemaining() {
    if (!S) return 0;
    if (!S.running) return S.remaining;
    return Math.max(0, S.remaining - (performance.now() - received) / 1000);
  }

  function clockWords(t) {
    return Math.floor(t / 60) + ':' + String(Math.floor(t % 60)).padStart(2, '0');
  }

  // The bell, once per match. The board reaches zero on its own clock and the
  // hall should hear it THEN, not when the server gets round to saying so.
  var toldTimeUp = false;
  var toldAtoshi = false;

  function paintClock() {
    if (!S || el('sb').hidden) return;
    var t = liveRemaining();
    var warn = (S.rules && S.rules.warning != null) ? S.rules.warning : 60;
    var low = S.status === 'live' && warn > 0 && t <= warn && t > 0 && S.running;

    if (t <= 0 && S.running && !toldTimeUp) {
      toldTimeUp = true;
      if (!S.rules || S.rules.time_up_buzzer !== false) ping('time_up');
    }
    if (t > 0) toldTimeUp = false;

    // The last-seconds alarm, once per match — the event's own file when it has
    // one, the synthesised chirp when it does not.
    if (low && !toldAtoshi) { toldAtoshi = true; ping('atoshi'); }
    if (!low) toldAtoshi = false;

    text('sbTimer', clockWords(t));
    text('sbHoldClock', clockWords(t));
    el('sbTimer').style.animation = low ? 'timerPulse 1s ease-in-out infinite' : 'none';
    el('sbTimer').style.color = low ? '' : '#fff';
    el('sbBar').style.width = (S.duration ? (t / S.duration * 100) : 0) + '%';
    el('sbBar').style.background = low ? '#ff3b47' : '#ffd666';
    el('sbLow').hidden = !low;

    // The status line always carries a WORD: a colour on its own never says
    // anything on this screen.
    text('sbStatus', low ? WARNING_LABEL : (STATUS[S.status] || STATUS.idle));
    el('sbStatus').style.color = low ? '#ff6b78' : '#ffd666';
    text('sbPeriod', S.status === 'overtime'
      ? @json(__('scoreboard::bjj_messages.overtime_time'))
      : @json(__('scoreboard::bjj_messages.regulation_time')));
  }
  setInterval(paintClock, 100);

  /* ── Sound ───────────────────────────────────────────────────────────────
     What the hall hears: music under the introduction, a sting over the
     celebration, and a noise per scoring action. Every file is this EVENT's
     own, fetched through this screen's token — nothing is shipped with the app
     and nothing is shared between events.

     Three things make this less simple than new Audio().play():

     1. A browser will not play sound before the page has been interacted with,
        and nobody ever touches a screen on a wall. So a blocked play is
        expected rather than exceptional: the screen keeps the element and
        retries for the first ten seconds. Silence with no explanation is the
        one outcome an operator cannot diagnose from across a hall.
     2. A missing slot is normal. An event that uploaded only a celebration
        track gets a 404 for the other nine, and that is not an error to draw.
     3. Point sounds must not stack. Three scores in four seconds would be three
        overlapping copies of the same sting, which is worse than one. */
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

  /** A one-shot: rewound rather than layered, so rapid scores never overlap. */
  function ping(slot) {
    var a = sound(slot);

    // The two CLOCK sounds are the exception to "a missing slot is normal": a
    // hall that uploaded nothing still has to hear the bell, because the bell
    // is not decoration — it is what tells a mat the match is over. Everything
    // else stays silent when nobody uploaded it.
    if (!a) {
      if (slot === 'time_up' || slot === 'atoshi') beep(slot === 'time_up' ? 1.1 : 0.35);
      return;
    }

    try { a.pause(); a.currentTime = 0; } catch (e) {}

    var p = a.play();
    // Swallowed deliberately: a score that could not be heard is over, and
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
   * first ten seconds costs nothing and turns "silent all match" into "silent
   * for a second", with no human needed.
   */
  function attempt(a, slot, tries) {
    tries = tries || 0;

    var p = a.play();
    if (!p || !p.then) return;

    p.catch(function () {
      // Still the track this screen wants? A match may have moved on while we
      // were being refused, and re-trying a stale one would talk over it.
      if (musicOn !== slot || tries >= 10) return;
      setTimeout(function () { attempt(a, slot, tries + 1); }, 1000);
    });
  }

  // Not the plan, just a free second chance: if this board ever does receive a
  // tap or a keypress, take it.
  ['pointerdown', 'keydown'].forEach(function (evt) {
    window.addEventListener(evt, function unlock() {
      window.removeEventListener(evt, unlock);
      if (musicOn && sounds[musicOn]) {
        var p = sounds[musicOn].play();
        if (p && p.catch) p.catch(function () {});
      }
    }, { once: true });
  });

  /**
   * The built-in buzzer: a square wave that decays, synthesised rather than
   * fetched, so the clock is never silent. This package uploads no sounds —
   * the bell is not decoration, it is what tells a mat the match is over.
   */
  var actx = null;
  function beep(seconds) {
    try {
      actx = actx || new (window.AudioContext || window.webkitAudioContext)();
      // A wall screen is never touched, so the context can be born suspended.
      if (actx.state === 'suspended' && actx.resume) actx.resume();

      var o = actx.createOscillator(), g = actx.createGain();
      o.type = 'square';
      o.frequency.value = 740;
      o.connect(g); g.connect(actx.destination);
      g.gain.setValueAtTime(0.25, actx.currentTime);
      g.gain.exponentialRampToValueAtTime(0.001, actx.currentTime + seconds);
      o.start();
      o.stop(actx.currentTime + seconds);
    } catch (e) { /* a screen with no audio device is not a fault */ }
  }

  /* ── The two counters, as lit cells ──────────────────────────────────────
     Advantages and penalties are LADDERS in this sport, not a running total —
     so they are drawn as the rungs they are, lit up to the count, in the same
     cell the Karate design gave its penalty row. The number is repeated in the
     cell so a photograph of the wall still carries it. */
  function cells(id, n, max, tone, ink) {
    var host = el(id);
    if (!host) return;
    host.textContent = '';
    for (var i = 0; i < max; i++) {
      var on = i < n;
      var c = document.createElement('div');
      c.style.cssText = 'width:72px; height:68px; border-radius:10px; display:flex; align-items:center; justify-content:center;' +
        "font-family:'Anton',sans-serif; font-size:30px;" +
        'background:' + (on ? tone : 'rgba(0,0,0,.28)') + ';' +
        'color:' + (on ? ink : 'rgba(255,255,255,.45)') + ';' +
        'border:2px solid ' + (on ? 'transparent' : 'rgba(255,255,255,.25)') + ';' +
        (on ? 'animation:cellIn .4s both;' : '');
      c.textContent = String(i + 1);
      host.appendChild(c);
    }
  }

  /* ── The callout: the ACTION, once, on the scoring side ──────────────────
     In jiu-jitsu the number does not name the action — two points is three
     different things — so the hall is told which one it was, with the value
     under it. A penalty is not a burst: it is the notice band below. */
  var lastCallout = 0;

  function callout(ev) {
    if (!ev || ev.ts === lastCallout) return;
    lastCallout = ev.ts;

    // The noise goes with the CALLOUT, not with the score changing: the score
    // is also rewritten by a correction, a reload and a reconnect, and none of
    // those should make a sound in the hall.
    //
    // Three slots, shared with every other combat scoreboard in the product,
    // mapped onto what actually happens on a jiu-jitsu mat: an advantage is the
    // smallest thing that scores, four points is the largest.
    if (ev.kind === 'penalty') { ping('foul'); notice(ev); return; }
    if (ev.kind !== 'point' && ev.kind !== 'advantage') return;

    ping(ev.kind === 'advantage' ? 'point_1' : ((ev.value || 0) >= 4 ? 'point_3' : 'point_2'));

    var host = el('sbCallout');
    var blue = ev.side === 'blue';
    var colour = blue ? '#4d9aff' : '#e6ebf2';
    host.textContent = '';
    host.style.left = (blue ? '25%' : '75%');
    host.style.top = '44%';

    var ring = document.createElement('div');
    ring.style.cssText = 'position:absolute; left:0; top:0; width:380px; height:380px; border-radius:50%; border:18px solid ' +
      colour + '; transform:translate(-50%,-50%); animation:ringBurst .9s cubic-bezier(.2,.8,.2,1) both;';
    host.appendChild(ring);

    var wrap = document.createElement('div');
    wrap.style.cssText = 'position:absolute; left:0; top:0; transform:translate(-50%,-50%); animation:calloutIn 1.5s cubic-bezier(.2,.8,.2,1) both;' +
      'display:flex; flex-direction:column; align-items:center; gap:4px;';
    var label = document.createElement('div');
    label.style.cssText = "font-family:'Anton',sans-serif; font-size:104px; line-height:1; color:#fff; white-space:nowrap;" +
      'text-shadow:0 0 80px ' + colour + ', 0 8px 30px rgba(0,0,0,.7);';
    label.textContent = (ev.kind === 'advantage'
      ? ADVANTAGE_WORD
      : (SOURCES[ev.source] || '')).toUpperCase();
    wrap.appendChild(label);

    if (ev.kind === 'point') {
      var plus = document.createElement('div');
      plus.style.cssText = "font-family:'Anton',sans-serif; font-size:72px; color:#ffd666; text-shadow:0 0 40px rgba(255,214,102,.7);";
      plus.textContent = '+' + (ev.value || 0);
      wrap.appendChild(plus);
    }

    host.appendChild(wrap);
    setTimeout(function () { if (lastCallout === ev.ts) host.textContent = ''; }, 1600);
  }

  /**
   * The penalty notice: which corner, what for, and at what time.
   *
   * A penalty is the one thing the hall is TOLD beyond the numbers, and it is
   * told in words. There is no public countdown and no stalling row — the
   * referee's own count is private until they decide to give something.
   */
  function notice(ev) {
    var n = el('sbNotice');
    if (!n) return;
    n.textContent = PENALTY_NOTICE
      .replace(':corner', ev.side === 'blue' ? CORNER_BLUE : CORNER_WHITE)
      .replace(':reason', (PENALTY_REASONS[ev.source] || PENALTY_REASONS.other || '').toUpperCase())
      .replace(':time', clockWords(liveRemaining()));
    n.hidden = false;
    n.style.animation = 'none'; void n.offsetWidth;
    n.style.animation = 'noticeIn 4.2s ease-out both';
    clearTimeout(notice.timer);
    notice.timer = setTimeout(function () { n.hidden = true; }, 4300);
  }

  /* ── The celebration ────────────────────────────────────────────────────
     This package's own copy of the scene. The board decides nothing about it
     beyond who won and what to say about it. */
  function winner(side, competitor) {
    var c = competitor || {}, sc = S.score || {};

    // WHY they won, in words. A declared method answers it for a submission,
    // a disqualification or a walkover; `decidedBy` answers it for a match
    // settled on the numbers — which of the three counters broke the tie.
    var method = S.declared ? (METHODS[S.winMethod] || '') : null;
    var note = method
      ? WON_BY.replace(':method', method)
      : (DECIDED[sc.decidedBy] || DECIDED.points || '');

    WinnerCelebration.paint(el('sbWinner'), {
      corner: side === 'blue' ? 'blue' : 'white',
      name: c.name || '',
      club: c.club || '',
      logo: c.logo || null,
      photo: c.photo || null,
      label: WINNER_LABEL,
      note: (note || '').toUpperCase()
    });
  }

  /* ── The introduction ───────────────────────────────────────────────────── */
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

  function paintVs() {
    var b = S.blue || {}, w = S.white || {};
    text('vsEvent', S.tournament || EVENT_TITLE);
    text('vsStage', S.stage || '');
    text('vsWeight', S.division || '');
    text('vsMatchNo', S.matchNo || '');
    // The referee chip leaves the row when nobody is appointed, rather than
    // standing there empty — the same rule the running order follows.
    text('vsReferee', S.referee || '');
    el('vsRefWrap').hidden = !S.referee;
    text('vsCourt', (S.courtLabel || COURT).replace(/[^0-9]/g, '') || (S.courtLabel || COURT));

    [['Blue', b], ['White', w]].forEach(function (pair) {
      var k = pair[0], c = pair[1];
      setName('vs' + k + 'Name', c.name || TBD, 71.3, 26);
      text('vs' + k + 'Club', c.club || '');
      // The country is spelled out — 'BAHRAIN', never 'BH' — so it is fitted
      // like the name is.
      setName('vs' + k + 'Country', c.country || '', 32.4, 18);
      bg('vs' + k + 'Flag', flagUrl(c.flag));
      bg('vs' + k + 'Logo', c.logo);

      // Their own face, and the drawn stand-in when there is none, so an
      // introduction is never half a panel with nobody in it. The stand-in sits
      // well back — this panel is two metres of wall and a drawing at full
      // strength would read as a photograph of the athlete on the mat.
      var face = c.photo || c.fallback;
      bg('vs' + k + 'Photo', face);
      var pel = el('vs' + k + 'Photo');
      if (pel) pel.style.opacity = (face && !c.photo) ? '.45' : '1';

      var host = el('vs' + k + 'Chips');
      host.textContent = '';
      chip(host, c.belt, true);
      chip(host, statLine(c), false);
    });
  }

  /* ── The scoreboard ─────────────────────────────────────────────────────── */
  function paintScoreboard() {
    var b = S.blue || {}, w = S.white || {}, sc = S.score || {};

    text('sbTournament', S.tournament || EVENT_TITLE);
    // The design ships a dashed "event logo" placeholder; a real crest replaces
    // it, and the placeholder stays when the club has none.
    if (EVENT_LOGO) {
      var lg = el('sbLogo');
      lg.textContent = '';
      lg.style.border = 'none';
      lg.style.backgroundImage = 'url("' + encodeURI(EVENT_LOGO) + '")';
    }

    text('sbCategory', S.division || '');
    var catEl = el('sbCategory');
    if (catEl) {
      var n = (S.division || '').length;
      catEl.style.fontSize = (n <= 9 ? 84 : n <= 14 ? 64 : n <= 22 ? 46 : 34) + 'px';
    }
    text('sbMatchNo', S.matchNo || '');
    text('sbCourt', (S.courtLabel || COURT).replace(/[^0-9]/g, '') || (S.courtLabel || COURT));
    text('sbRound', S.stage || '');

    [['Blue', b], ['White', w]].forEach(function (pair) {
      var k = pair[0], c = pair[1];
      setName('sb' + k + 'Name', c.name || TBD, 120, 38);
      text('sb' + k + 'Club', c.club || '');
      setName('sb' + k + 'Country', c.country || '', 52, 26);
      var f = el('sb' + k + 'Flag'), u = safeUrl(flagUrl(c.flag));
      f.style.visibility = u ? 'visible' : 'hidden';
      if (u) f.src = u;
    });

    ['Blue', 'White'].forEach(function (k) {
      var e = el('sb' + k + 'Score');
      var v = String(sc[k.toLowerCase() + 'Points'] || 0);
      if (e.textContent !== v) {
        e.textContent = v;
        e.style.animation = 'none'; void e.offsetWidth;
        e.style.animation = 'scorePop .45s cubic-bezier(.2,.8,.2,1)';
      }
    });

    // Three advantages is as far as the rung ladder is ever drawn; the penalty
    // ladder is as long as THIS mat's rules make it, because the limit is a
    // setting an official chose in the morning.
    var limit = (S.rules && S.rules.penalty_limit) || 4;
    cells('sbBlueAdv', sc.blueAdvantages || 0, 3, '#ffd666', '#000');
    cells('sbWhiteAdv', sc.whiteAdvantages || 0, 3, '#b3861f', '#fff');
    cells('sbBluePen', sc.bluePenalties || 0, limit, '#ff3b47', '#fff');
    cells('sbWhitePen', sc.whitePenalties || 0, limit, '#c81f2a', '#fff');

    var side = S.winner;
    el('sbBlueWin').hidden = !(S.finished && side === 'blue');
    el('sbWhiteWin').hidden = !(S.finished && side === 'white');

    // The celebration runs only once an official has said HOW the match ended,
    // and only while they have not put it away: closing it at the scoring table
    // closes it in the hall too, which is the whole point of that button. The
    // winner's border glow stays either way — the match IS won.
    if (S.finished && side && !S.celebrationClosed) {
      winner(side, side === 'blue' ? b : w);
      el('sbWinner').hidden = false;
    } else {
      WinnerCelebration.clear(el('sbWinner'));
      el('sbWinner').hidden = true;
    }

    paintHold();
    callout(S.lastEvent);
  }

  /**
   * The hold card: under review, or with the doctor on the mat.
   *
   * A level match at 0:00 waiting on a referee decision holds here too, rather
   * than the wall inventing a result — IBJJF sends that to the referee, and
   * until they answer the honest thing to show is that it is not over.
   *
   * A plain PAUSE is deliberately NOT one of these (2026-09-05, at the user's
   * request). The card blacks out the whole board, and a pause happens a dozen
   * times a match for reasons the hall does not need explaining — a grip
   * reset, a stray shout, the table catching up. The clock stopping IS the
   * message, and `sbStatus` already carries the word beside it. The card is
   * kept for the three states where the hall genuinely has to be told the mat
   * has stopped for something: a review, a doctor, and a decision nobody has
   * given yet.
   */
  function paintHold() {
    var sc = S.score || {};
    var waiting = !S.finished && (S.awaitingDecision
      || S.status === 'review' || S.status === 'medical');

    el('sbHold').hidden = !waiting;
    if (!waiting) return;

    text('sbHoldTitle', STATUS[S.status] || STATUS.paused);
    text('sbHoldScore', CORNER_BLUE + ' ' + (sc.bluePoints || 0) + '  —  ' + (sc.whitePoints || 0) + ' ' + CORNER_WHITE);
  }

  /* ── The running order ──────────────────────────────────────────────────── */
  function node(tag, style, value) {
    var n = document.createElement(tag);
    if (style) n.style.cssText = style;
    if (value != null) n.textContent = value;
    return n;
  }

  /* Missing data HIDES, it never invents: a stand-in crest or an initial-letter
     silhouette is information the board does not have, and on a wall invented
     detail reads as fact. Nothing is deleted — restore the data and the element
     comes back by itself. */
  function show(e, has) {
    if (e.__display === undefined) e.__display = e.style.display || '';
    e.style.display = has ? e.__display : 'none';
    return has;
  }
  function bgOrHide(e, url) {
    var u = safeUrl(url);
    if (show(e, !!u)) e.style.backgroundImage = 'url("' + encodeURI(u) + '")';
    return !!u;
  }
  function textOrHide(e, value) {
    var v = (value == null ? '' : String(value)).trim();
    if (show(e, v !== '')) e.textContent = v;
    return v !== '';
  }

  /** One corner half — photo, name, flag, crest, club. */
  function half(m, corner) {
    var blue = corner === 'blue';
    var wrap = node('div', 'flex:1; min-width:0; display:flex; align-items:stretch;' +
      (blue
        ? 'background:linear-gradient(90deg, #1362d1 0%, #08284f 100%);' +
          'clip-path:polygon(0 0, 100% 0, calc(100% - 60px) 100%, 0 100%); margin-right:-30px; color:#fff;'
        : 'flex-direction:row-reverse;' +
          'background:linear-gradient(270deg, #eef2f7 0%, #93a1b3 100%);' +
          'clip-path:polygon(60px 0, 100% 0, 100% 100%, 0 100%); margin-left:-30px; color:#0a0b10;'));

    var photo = node('div', 'width:230px; flex:0 0 auto; background-color:' + (blue ? '#0e3f80' : '#7f8b9c') + ';' +
      'background-size:cover; background-position:center 12%;');
    bgOrHide(photo, m[corner + 'Photo']);
    wrap.appendChild(photo);

    var col = node('div', 'flex:1; min-width:0; display:flex; flex-direction:column; justify-content:center; gap:6px;' +
      (blue ? 'padding:10px 60px 10px 26px;' : 'align-items:flex-end; padding:10px 26px 10px 60px; text-align:right;'));

    // The one field that never hides: a nameless half looks broken from ten
    // metres, so an undrawn slot says so instead.
    col.appendChild(node('div', "font-family:'Anton',sans-serif; font-size:56px; line-height:1; text-transform:uppercase; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;", m[corner + 'Name'] || TBD));

    var meta = node('div', 'display:flex; align-items:center; gap:12px; max-width:100%;' + (blue ? '' : 'flex-direction:row-reverse;'));

    // Stretched to fill the box — `100% 100%`, not `cover` and not `contain`.
    // National flags run from 1.43:1 to 2:1 while this box is 4:3, so the three
    // options are crop (which ate a third off a 2:1 flag), letterbox (grey
    // bands), or stretch — which keeps every flag WHOLE and fills the plate.
    var flag = node('div', 'width:76px; flex:0 0 auto; aspect-ratio:4/3; background-color:rgba(0,0,0,0.25); background-size:100% 100%; image-rendering:auto; background-repeat:no-repeat; background-position:center; border:1px solid rgba(255,255,255,0.4);');
    var hasFlag = bgOrHide(flag, flagUrl(m[corner + 'Flag']));
    meta.appendChild(flag);

    var crest = node('div', 'width:66px; height:66px; flex:0 0 auto; border-radius:50%; background-color:rgba(255,255,255,0.12); background-size:cover; background-position:center; border:1px solid rgba(255,255,255,0.35);');
    var hasCrest = bgOrHide(crest, m[corner + 'Logo']);
    meta.appendChild(crest);

    var club = node('div', 'min-width:0; font-weight:600; font-size:30px; letter-spacing:0.06em; text-transform:uppercase; opacity:.85; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;');
    var hasClub = textOrHide(club, m[corner + 'Club']);
    meta.appendChild(club);

    // With flag, crest and club all absent the strip is an empty flex row that
    // would still eat its gap under the name — so the row itself steps aside.
    show(meta, hasFlag || hasCrest || hasClub);

    col.appendChild(meta);
    wrap.appendChild(col);
    return wrap;
  }

  /** The centre plate: match number, round, division — gold when it is next. */
  function plate(m, isNext) {
    var GOLD = '#fdc436';
    var p = node('div', 'position:absolute; left:50%; top:50%; transform:translate(-50%,-50%); display:flex; flex-direction:column; align-items:center; gap:2px;' +
      (isNext ? 'background:' + GOLD + ';' : 'background:#101016;') +
      'border:2px solid ' + (isNext ? '#fffdf0' : 'rgba(255,255,255,0.3)') + ';' +
      'padding:10px 26px 12px; min-width:220px; box-shadow:0 0 30px rgba(0,0,0,0.7); z-index:3;');

    var ink = isNext ? '#141210' : '#fff';
    var accent = isNext ? '#141210' : GOLD;
    var muted = isNext ? 'rgba(20,18,16,0.65)' : 'rgba(232,230,224,0.65)';

    if (isNext) {
      // GET READY breathes by STRETCHING its word, not by animating
      // letter-spacing — tracking is a layout property, and re-laying-out text
      // sixty times a second is the one animation here that would reach past
      // paint into layout.
      var ready = node('div', 'font-weight:800; font-size:26px; letter-spacing:0.2em; text-transform:uppercase; background:#141210; color:' + GOLD + '; padding:3px 18px 3px 20px; margin-bottom:2px; overflow:hidden;');
      ready.appendChild(node('span', 'display:inline-block; animation:readyBreath 1.6s ease-in-out infinite;', GET_READY));
      p.appendChild(ready);
    }

    // "MATCH 12" — the label is only meaningful next to a number, so the whole
    // line steps aside for a match that has not been given one yet.
    var line = node('div', 'display:flex; align-items:baseline; gap:8px;');
    line.appendChild(node('span', 'font-weight:600; font-size:26px; letter-spacing:0.24em; text-transform:uppercase; color:' + muted + ';', MATCH_WORD));
    var num = node('span', "font-family:'Anton',sans-serif; font-size:54px; line-height:1; color:" + accent + ';' + (isNext ? ' animation:numBeat 1.6s ease-in-out infinite;' : ''));
    show(line, textOrHide(num, m.number));
    line.appendChild(num);
    p.appendChild(line);

    var stage = node('div', 'font-weight:800; font-size:30px; letter-spacing:0.16em; text-transform:uppercase; color:' + ink + ';');
    textOrHide(stage, m.stage);
    p.appendChild(stage);

    var weight = node('div', 'font-weight:600; font-size:26px; letter-spacing:0.14em; text-transform:uppercase; color:' + muted + ';');
    textOrHide(weight, m.weightClass);
    p.appendChild(weight);

    return p;
  }

  /**
   * One match row.
   *
   * `enter` is false for a match that was already on screen before this update.
   * When one finishes the rows shift up, and re-playing the entrance on all
   * four would read as the whole board flinching — only new rows fly in.
   */
  function qRow(m, i, isNext, enter) {
    var GOLD = '#fdc436';
    var anim = enter ? ' animation:' + (i % 2 === 0 ? 'rowEnterL' : 'rowEnterR') + ' 0.7s ' + (0.2 + i * 0.15) + 's cubic-bezier(0.22,1,0.36,1) both;' : '';

    var r = node('div', 'flex:1; min-height:0; position:relative; display:flex; align-items:stretch;' +
      (isNext ? 'border:1px solid rgba(253,196,54,0.8);' : 'border:1px solid rgba(255,255,255,0.15);') +
      'background:#101016;' +
      // The quiet half of the breath, as a plain static shadow. The loud half
      // is the layer below, faded over the top of it.
      (isNext ? 'box-shadow:0 0 16px rgba(253,196,54,0.35), 0 0 44px rgba(253,196,54,0.15);' : '') + anim);

    var sweepWrap = node('div', 'position:absolute; inset:0; overflow:hidden; pointer-events:none; z-index:2;');
    sweepWrap.appendChild(node('div', 'position:absolute; top:0; bottom:0; width:26%; background:linear-gradient(to right, transparent, rgba(255,255,255,0.16), transparent); animation:rowSweep 4.5s ' + (1.2 + i * 0.55) + 's ease-in-out infinite;'));
    r.appendChild(sweepWrap);

    if (isNext) {
      // The breath: the strong shadow rasterised ONCE and faded in and out,
      // rather than a 110px blur re-drawn around a full-width row every frame.
      r.appendChild(node('div', 'position:absolute; inset:0; z-index:-1; pointer-events:none; opacity:0;' +
        'box-shadow:0 0 36px rgba(253,196,54,0.8), 0 0 110px rgba(253,196,54,0.35);' +
        'animation:glowPulse 2.2s 1.2s ease-in-out infinite;'));

      // The running border, as four thin strips rather than one masked box: a
      // mask forces a render surface, and this is the same picture without it.
      var ring = node('div', 'position:absolute; inset:-2px; pointer-events:none; z-index:2;');
      var GRAD = 'background:linear-gradient(90deg, ' + GOLD + ', #fffdf0 25%, ' + GOLD + ' 50%, #6b5310 75%, ' + GOLD + ');';
      ['top:0;', 'bottom:0;'].forEach(function (edge) {
        var band = node('div', 'position:absolute; left:0; right:0; ' + edge + ' height:3px; overflow:hidden;');
        // The gradient repeats every 50% of its own width, so a -50% slide
        // loops with no seam.
        band.appendChild(node('div', 'position:absolute; top:0; bottom:0; left:0; width:300%;' + GRAD + 'animation:goldSlide 2.5s linear infinite;'));
        ring.appendChild(band);
      });
      ['left:0;', 'right:0;'].forEach(function (edge) {
        ring.appendChild(node('div', 'position:absolute; top:0; bottom:0; ' + edge + ' width:3px; background:' + GOLD + ';'));
      });
      r.appendChild(ring);
    }

    r.appendChild(half(m, 'blue'));
    r.appendChild(half(m, 'white'));

    var pl = plate(m, isNext);
    if (enter) pl.style.animation = 'platePop 0.55s ' + (0.55 + i * 0.15) + 's cubic-bezier(0.22,1,0.36,1) both';
    r.appendChild(pl);
    return r;
  }

  var shownVersion = null, shownKeys = [], firstQueue = true;
  function keyOf(m) { return String(m.number) + '|' + (m.blueName || '') + '|' + (m.whiteName || ''); }

  function paintQueue() {
    var payload = BOARD;
    if (!payload || typeof payload !== 'object') return;

    // A re-publish with no visible change must not repaint — on a wall, a board
    // that silently re-animates every few seconds reads as a fault.
    if (payload.version && payload.version === shownVersion) return;
    shownVersion = payload.version || null;

    var host = el('qRows');
    textOrHide(el('qEvent'), (payload.event && payload.event.title) || EVENT_TITLE);
    show(el('qCourtBadge'), textOrHide(el('qCourtNumber'), payload.courtNumber
      || String(COURT).replace(/[^0-9]/g, '') || COURT));

    var matches = Array.isArray(payload.matches) ? payload.matches.slice(0, ROWS) : [];
    var keys = matches.map(keyOf);
    host.textContent = '';

    if (!matches.length) {
      var idle = node('div', 'flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:18px;');
      idle.appendChild(node('div', "font-family:'Anton',sans-serif; font-size:92px; letter-spacing:0.14em; text-transform:uppercase; color:rgba(232,230,224,0.22);", IDLE_T));
      idle.appendChild(node('div', 'font-weight:600; font-size:34px; letter-spacing:0.3em; text-transform:uppercase; color:rgba(232,230,224,0.35);', IDLE_S));
      host.appendChild(idle);
      shownKeys = keys; firstQueue = false;
      return;
    }

    matches.forEach(function (m, i) {
      host.appendChild(qRow(m, i, i === 0, firstQueue || shownKeys.indexOf(keys[i]) === -1));
    });

    shownKeys = keys;
    firstQueue = false;
  }

  /* ── Which layer is up ──────────────────────────────────────────────────
     WHAT THIS SCREEN WAS HUNG UP TO BE comes first; the mat only decides for a
     screen that was told to follow it. This package draws one document, so
     unlike Karate and Taekwondo — which render a different page per pin — the
     pin has to be honoured HERE. Getting that wrong once meant a screen paired
     as the scoreboard showed the running order whenever the mat was idle, which
     is most of the day. */
  var PINNED = @json($pinned);
  var mode = null;
  // Each match sting fires once; the match id is what re-arms them.
  var cuedMatch = null, toldStart = false, toldEnd = false;

  function show_layer() {
    var pin = PINNED;                        // 'bout' | 'queue' | 'both'
    var idle = S.mode === 'upcoming';

    var onQueue = pin === 'queue' || (pin === 'both' && idle);
    var onIdle = pin === 'bout' && idle;     // the scoreboard's own between-matches card
    var onVs = !onQueue && !onIdle && S.mode === 'vs';
    var onBoard = !onQueue && !onIdle && !onVs;

    el('queue').hidden = !onQueue;
    el('idle').hidden = !onIdle;
    el('sb').hidden = !onBoard;

    if (onQueue) paintQueue();
    if (onBoard || onVs) paintScoreboard();
    if (onVs) paintVs();

    // The handover: the introduction WIPES itself off the scoreboard already
    // drawn behind it, rather than the page changing. That transition is what
    // the whole single-document design exists for.
    var wasVs = mode === 'vs';
    var next = onQueue ? 'queue' : (onIdle ? 'idle' : (onVs ? 'vs' : 'board'));

    if (next !== mode) {
      if (onVs) {
        el('vs').hidden = false;
        el('vs').style.animation = '';
      } else if (wasVs) {
        el('vs').style.animation = 'vsExit .55s cubic-bezier(.4,0,1,1) both';
        setTimeout(function () { if (mode !== 'vs') el('vs').hidden = true; }, 560);
      } else {
        el('vs').hidden = true;
      }
      mode = next;
      // A layer that was hidden had no width, so its names could not be
      // measured until now.
      refitNames();
    }

    // ── What the hall hears, decided by the same three facts that decide
    //    what it SEES: which layer is up, whether the clock is running, and
    //    whether the match is over. Each sting fires once per match, and the
    //    match id is what resets them.
    if (S.matchId !== cuedMatch) {
      cuedMatch = S.matchId;
      toldStart = toldEnd = false;
    }

    if (S.running && !S.finished && !toldStart) { toldStart = true; ping('match_start'); }
    if (S.finished && !toldEnd) { toldEnd = true; ping('match_end'); }

    var celebrating = S.finished && !S.celebrationClosed && onBoard;

    if (celebrating) {
      music('winner_music');
    } else if (onVs) {
      music('vs_music');
    } else if (musicOn) {
      music(null);
    }

    paintClock();
  }

  /**
   * One entry point for both payload shapes.
   *
   * The mat state and the running order are different documents on the server
   * and different shapes on the wire; they are told apart HERE by what they
   * contain, so the socket never has to know which endpoint answered it.
   */
  var primed = false, boutTold = null;
  function update(payload) {
    if (!payload || typeof payload !== 'object') return;

    if (payload.matches) {
      BOARD = payload;
      if (!el('queue').hidden) paintQueue();
      return;
    }
    if (!payload.mode) return;

    // The FIRST state a screen is handed is history, not news: a board reloads
    // for all sorts of reasons and the state it wakes to still carries whatever
    // was scored last. Announcing it would burst a callout over the hall for
    // something that happened ten minutes ago.
    if (!primed) {
      primed = true;
      if (payload.lastEvent && payload.lastEvent.ts) lastCallout = payload.lastEvent.ts;
      boutTold = payload.matchId || null;
      toldTimeUp = !!payload.finished;
    }

    // A new match on the mat rearms the once-per-match cues.
    if (payload.matchId !== boutTold) {
      boutTold = payload.matchId || null;
      toldTimeUp = false;
    }

    S = payload;
    received = performance.now();
    document.documentElement.setAttribute('data-theme', S.theme === 'venue' ? 'venue' : 'arena');
    show_layer();
  }

  window.CourtBoard = {
    update: update,
    // The socket asks this before drawing a running-order nudge or picking a
    // resync endpoint: a screen showing a match is not showing the queue.
    mode: function () { return (S && S.mode) || 'upcoming'; },
    pinned: PINNED,
    stale: function (on) { el('stale').classList.toggle('on', !!on); }
  };

  update(@json($state));

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
@include('scoreboard::bjj.partials.screen-link')
@endif
</body>
</html>
