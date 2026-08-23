{{--
    The Taekwondo scoring table — the operator's page at the mat.

    Like the wall screens it does NOT extend a layout and does not use the
    design system: it is an appliance console on a laptop beside a mat, read at
    arm's length under bad light by somebody who cannot look away from the
    fight. Markup and CSS are transcribed from the approved layout in
    `drafts/TaeKwonDo WTF/Scoreboard Control - Standalone.html`.

    It decides nothing. Every button posts a COMMAND and redraws from the state
    that comes back — the same state the wall screens receive over MQTT, so two
    officials cannot disagree, and a console that has fallen behind can never
    overwrite the score with a stale total.

    ── Three deliberate departures from the approved layout ────────────────────

    1. NO Hardware tab. The design has a panel for electronic body protectors
       (PSS) — connect, battery, signal. There is no such integration in this
       product, and a console that shows a dead "Connect All" button beside a
       live scoring grid is worse than one that does not offer it.

    2. NO Data tab. The design has a sync/file panel for exporting a match to a
       server. This app IS the server: "End Match & Upload" writes the result
       through recordOutcome() like every other result in the product.

    3. The round stepper is an AWARD control. The design steps the round number
       up and down with − / +, which cannot express a WT series: rounds are WON,
       and a free stepper would let an operator reach round 3 with the round
       wins still 0–0, then commit a match that the engine correctly refuses as
       undecided. So the same panel, in the same style, closes the round to a
       corner instead — by score, or to whichever corner the table names when
       the round is level (WT settles those on superiority, which is a judgement
       made at the table, not something software can read off 4–4).
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ __('event-taekwondo_tournament::messages.ctl_title') }} · {{ $court }} · {{ $event->title }}</title>

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
  src: url("{{ route('court-display.font', $slug.'-'.$subset.'.woff2', false) }}") format('woff2');
  unicode-range: {{ $range }};
}
@endforeach
@endforeach

  /* The base font lives here as well as on #stage: anything appended to
     <body> rather than into the stage (the error bar) would otherwise inherit
     the browser's serif default. */
  html, body { margin:0; padding:0; background:#0a0a0e; overflow:hidden;
               font-family:'Barlow Condensed', sans-serif; color:#e8e6e0; }
  * { box-sizing:border-box; }
  a { color:#e8e6e0; } a:hover { color:#fff; }

  @keyframes urgentBlink { 0%,100% { color:#ff5548; } 50% { color:#fffdf5; } }
  @keyframes ctrlScorePop { 0% { transform:scale(1.7); color:oklch(0.88 0.17 85); text-shadow:0 0 60px oklch(0.85 0.16 85); } 60% { transform:scale(0.94); } 100% { transform:scale(1); color:#fffdf5; text-shadow:none; } }
  @keyframes headerIn { from { opacity:0; transform:translateY(-24px); } to { opacity:1; transform:translateY(0); } }
  @keyframes rowInL { from { opacity:0; transform:translateX(-40px); } to { opacity:1; transform:translateX(0); } }
  @keyframes rowInR { from { opacity:0; transform:translateX(40px); } to { opacity:1; transform:translateX(0); } }
  @keyframes runDot { 0%,100% { opacity:1; } 50% { opacity:0.2; } }
  @keyframes modalPop { 0% { opacity:0; transform:scale(0.92) translateY(24px); } 100% { opacity:1; transform:scale(1) translateY(0); } }
  @keyframes foulFlash { 0% { transform:rotate(45deg) scale(2); box-shadow:0 0 40px oklch(0.85 0.16 85); } 100% { transform:rotate(45deg) scale(1); box-shadow:none; } }
  /* Lifted from the wall screen verbatim: the operator and the hall should be
     looking at the same celebration, not two different ones. The translate is
     carried through every step because a keyframe animating `transform`
     REPLACES the element's own centring. */
  @keyframes stampIn { 0% { transform:translate(-50%,-50%) scale(2.6); opacity:0; filter:blur(10px); } 45% { transform:translate(-50%,-50%) scale(.96); opacity:1; filter:blur(0); } 60% { transform:translate(-50%,-50%) scale(1.04); } 100% { transform:translate(-50%,-50%) scale(1); opacity:1; } }
  @keyframes confettiFall { 0% { transform:translateY(-90px) rotate(0deg); opacity:1; } 100% { transform:translateY(1200px) rotate(760deg); opacity:.75; } }
  button:active { transform:scale(0.95); transition:transform 0.05s; }
  button:disabled { opacity:.4; cursor:not-allowed; }
  button:disabled:active { transform:none; }

  /* An inline `display:` beats the browser's own [hidden] rule, because an
     inline style outranks the UA stylesheet. The undo sheet carries
     `display:flex` inline (it centres its card), so without this it renders
     permanently open and its blurred backdrop swallows the whole console —
     which is exactly what it did. Applies to every hidden element here, so a
     later one cannot reintroduce the same fault. */
  [hidden] { display:none !important; }

  /* ── One authored stage, scaled — the same model as the two wall screens ──
     The approved control ships as a reflowing page (`min-height:100vh`), and
     the other two Taekwondo surfaces are authored at 1920x1080 and only ever
     SCALE. Mixing the two was the visible fault: on the same laptop the wall
     screens were a fixed, predictable layout while the console reflowed to the
     window, so the three did not look like one product and the console's own
     proportions changed with every browser size.
     Authored once at 1920x1080, scaled to fit — same as Karate's console. */
  #root { position:absolute; inset:0; overflow:hidden; background:#0a0a0e; }
  #stage {
    position:absolute; left:50%; top:50%; width:1920px; height:1080px;
    transform:translate(-50%,-50%) scale(var(--stage-scale, 0.6)); transform-origin:center;
    background:radial-gradient(120% 90% at 50% 30%, #16161f 0%, #0a0a0e 70%);
    font-family:'Barlow Condensed', sans-serif; color:#e8e6e0;
    display:flex; flex-direction:column; gap:14px; padding:18px; overflow:hidden;
  }

  .btn { font-family:'Barlow Condensed',sans-serif; font-weight:700; font-size:17px; letter-spacing:0.12em;
         text-transform:uppercase; background:rgba(255,255,255,0.08); color:#e8e6e0;
         border:1px solid rgba(255,255,255,0.3); padding:10px 18px; cursor:pointer; }
  .btn:hover:not(:disabled) { background:rgba(255,255,255,0.16); }
  .btn-go { background:oklch(0.55 0.17 145); color:#fff; border:none; font-weight:800; }
  .btn-danger { background:transparent; color:#ff8b80; border:1px solid #a33; }
  .btn-danger:hover:not(:disabled) { background:rgba(180,50,40,0.25); }
  .meta-k { font-weight:600; font-size:16px; letter-spacing:0.2em; text-transform:uppercase; color:rgba(232,230,224,0.5); }
  .meta-v { font-family:'Anton',sans-serif; font-size:26px; line-height:1; color:#fff; }
  .cfg { font-family:'Barlow Condensed',sans-serif; font-size:17px; background:rgba(0,0,0,0.5); color:#e8e6e0;
         border:1px solid rgba(255,255,255,0.25); padding:8px 10px; outline:none; width:76px; }
  .pt { font-family:'Barlow Condensed',sans-serif; font-weight:800; font-size:24px; line-height:1.15;
        text-transform:uppercase; background:rgba(0,0,0,0.45); color:#fff; border:1px solid rgba(255,255,255,0.35);
        padding:12px 4px; cursor:pointer;
        /* SQUARE. The row used to stretch to whatever height was left over, so
           the five targets were tall rectangles whose proportions changed with
           everything above them. A square is a shape an operator's hand learns:
           the same target in the same place on both sides of the mat, whatever
           else the panel is doing.
           `aspect-ratio` with min-height:0 so a short stage shrinks them rather
           than pushing the gam-jeom row off the bottom. */
        aspect-ratio:1; min-height:0; width:100%;
        /* Centre the label in that box — left at the top it read as a mis-sized
           button rather than a big target. */
        display:flex; flex-direction:column; align-items:center; justify-content:center; gap:4px; }
  .pt span { font-family:'Anton',sans-serif; font-size:52px; display:block; line-height:1; }
</style>

{{-- The winner celebration — this package's own. The same scene the wall
     shows, with this console's own controls dropped into its one slot. --}}
@include('event-taekwondo_tournament::scoreboard.winner-celebration')
</head>
<body>

<div id="root"><div id="stage">

{{-- ── Header ──────────────────────────────────────────────────────────── --}}
<div style="background:rgba(8,8,12,0.9); border:1px solid oklch(0.85 0.16 85 / 0.5); animation:headerIn 0.5s cubic-bezier(0.22,1,0.36,1) both;">
  <div style="display:flex; align-items:center; gap:18px; padding:14px 22px; border-bottom:1px solid rgba(255,255,255,0.12); flex-wrap:wrap;">
    <div style="flex:1 1 320px; min-width:260px; display:flex; flex-direction:column; gap:2px;">
      <div style="font-family:'Anton',sans-serif; font-size:26px; line-height:1; letter-spacing:0.1em; text-transform:uppercase; color:#fff; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">{{ $event->title }}</div>
      <div style="font-weight:700; font-size:16px; letter-spacing:0.24em; text-transform:uppercase; color:oklch(0.85 0.16 85);">
        {{ __('event-taekwondo_tournament::messages.ctl_title') }}
        <span style="color:rgba(232,230,224,0.45);">· {{ $screens ? __('event-taekwondo_tournament::messages.ctl_screens', ['count' => $screens]) : __('event-taekwondo_tournament::messages.ctl_no_screens') }}</span>
      </div>
    </div>
    <div style="display:flex; gap:10px; flex:0 1 auto; flex-wrap:wrap;">
      <button id="btnRefresh" class="btn">{{ __('event-taekwondo_tournament::messages.ctl_refresh_vs') }}</button>
      <button id="btnQueue" class="btn">{{ __('event-taekwondo_tournament::messages.ctl_queue') }}</button>
      <button id="btnUndo" class="btn">{{ __('event-taekwondo_tournament::messages.ctl_undo') }}</button>
      <button id="btnCommit" class="btn btn-go">{{ __('event-taekwondo_tournament::messages.ctl_end_upload') }}</button>
      <button id="btnResetMatch" class="btn btn-danger">{{ __('event-taekwondo_tournament::messages.ctl_reset_match') }}</button>
      <button id="btnClear" class="btn btn-danger">{{ __('event-taekwondo_tournament::messages.ctl_clear_mat') }}</button>
    </div>
  </div>
  <div style="display:flex; align-items:center; padding:10px 22px; gap:0; flex-wrap:wrap;">
    <div style="display:flex; align-items:baseline; gap:8px; padding-right:22px; border-right:1px solid rgba(255,255,255,0.15);">
      <span class="meta-k">{{ __('event-taekwondo_tournament::messages.ctl_court') }}</span><span class="meta-v" id="mCourt"></span>
    </div>
    <div style="display:flex; align-items:baseline; gap:8px; padding:0 22px; border-right:1px solid rgba(255,255,255,0.15);">
      <span class="meta-k">{{ __('event-taekwondo_tournament::messages.ctl_match') }}</span><span class="meta-v" id="mNo"></span>
    </div>
    <div style="display:flex; align-items:baseline; gap:8px; padding:0 22px; border-right:1px solid rgba(255,255,255,0.15);">
      <span class="meta-k">{{ __('event-taekwondo_tournament::messages.ctl_class') }}</span>
      <span id="mClass" style="font-weight:700; font-size:22px; letter-spacing:0.06em; text-transform:uppercase; color:#fff;"></span>
    </div>
    <div style="display:flex; align-items:baseline; gap:8px; padding:0 22px;">
      <span class="meta-k">{{ __('event-taekwondo_tournament::messages.ctl_category') }}</span>
      <span id="mCategory" style="font-weight:700; font-size:22px; letter-spacing:0.06em; text-transform:uppercase; color:#fff;"></span>
    </div>
  </div>
</div>

{{-- ── The queue, as a sheet ───────────────────────────────────────────────
     A POPUP, as the approved layout has it — there the queue lives inside the
     "Match Data" modal. Standing open on the page it pushed the clock, the
     round panel and both corner grids down the screen, which is exactly
     backwards: the operator looks at the queue twice a match and at the
     scoring buttons a hundred times. --}}
<div id="queueModal" hidden style="position:fixed; inset:0; background:rgba(0,0,0,0.7); backdrop-filter:blur(4px); z-index:50; display:flex; align-items:center; justify-content:center;">
  <div style="width:820px; max-height:900px; overflow-y:auto; background:#101016; border:2px solid oklch(0.85 0.16 85 / 0.7); padding:28px 30px; animation:modalPop 0.35s cubic-bezier(0.22,1,0.36,1) both; display:flex; flex-direction:column; gap:18px; box-shadow:0 30px 90px rgba(0,0,0,0.8);">
    <div style="display:flex; align-items:center; gap:12px;">
      <div style="display:flex; flex-direction:column; gap:2px; flex:1; min-width:0;">
        <div style="font-family:'Anton',sans-serif; font-size:26px; letter-spacing:0.1em; text-transform:uppercase; color:oklch(0.85 0.16 85);">{{ __('event-taekwondo_tournament::messages.ctl_queue') }}</div>
        <div style="font-weight:600; font-size:14px; letter-spacing:0.06em; text-transform:uppercase; color:rgba(232,230,224,0.5);">{{ __('event-taekwondo_tournament::messages.ctl_queue_hint') }}</div>
      </div>
      <button id="queueClose" class="btn" style="font-family:'Anton',sans-serif; font-size:22px; width:40px; height:40px; padding:0;">✕</button>
    </div>

    <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap; border-bottom:1px solid rgba(255,255,255,0.15); padding-bottom:16px;">
      {{-- Not in the approved layout: the round length and count live in its
           Data tab, which is dropped. They have to be settable somewhere, and
           they belong beside the button that applies them. --}}
      <div style="display:flex; align-items:center; gap:8px; flex:1;">
        <span class="meta-k">{{ __('event-taekwondo_tournament::messages.ctl_round') }}</span>
        <input id="cfgMin" class="cfg" type="number" min="0.5" max="15" step="0.5" value="2">
        <span class="meta-k">×</span>
        <input id="cfgRounds" class="cfg" type="number" min="1" max="5" step="1" value="3">
      </div>
      <button id="btnNext" class="btn btn-go">{{ __('event-taekwondo_tournament::messages.ctl_start_next') }}</button>
    </div>

    <div id="queue" style="display:flex; flex-direction:column; gap:8px; max-height:520px; overflow-y:auto;"></div>
  </div>
</div>

{{-- ── Clock and round ─────────────────────────────────────────────────── --}}
<div style="display:flex; gap:14px; flex-wrap:wrap;">
  <div style="flex:1; min-width:340px; display:flex; align-items:center; justify-content:center; gap:26px; background:rgba(8,8,12,0.9); border:2px solid oklch(0.85 0.16 85 / 0.7); padding:14px 26px;">
    <div style="display:flex; flex-direction:column; align-items:center;">
      <div id="phase" style="font-weight:600; font-size:16px; letter-spacing:0.3em; text-transform:uppercase; color:rgba(232,230,224,0.55);"></div>
      <div style="display:flex; align-items:center; gap:14px;">
        <div id="runDot" hidden style="width:16px; height:16px; border-radius:50%; background:oklch(0.75 0.19 145); animation:runDot 1s ease-in-out infinite;"></div>
        <div id="timer" style="font-family:'Anton',sans-serif; font-size:96px; line-height:1; font-variant-numeric:tabular-nums; color:#fffdf5;">0:00</div>
      </div>
    </div>
    <div style="display:flex; flex-direction:column; gap:8px;">
      <button id="btnTimer" class="btn" style="background:oklch(0.85 0.16 85); color:#141210; border:none; font-weight:800; font-size:24px; letter-spacing:0.14em; padding:14px 30px; min-width:190px;"></button>
      <div style="display:flex; gap:8px;">
        <button id="btnRest" class="btn" style="flex:1; padding:9px 12px; font-size:17px;">{{ __('event-taekwondo_tournament::messages.ctl_rest') }} 1:00</button>
        <button id="btnResetRound" class="btn" style="flex:1; padding:9px 12px; font-size:17px;">{{ __('event-taekwondo_tournament::messages.ctl_reset_round') }}</button>
      </div>
    </div>
  </div>

  <div style="flex:1; min-width:340px; display:flex; align-items:center; justify-content:center; gap:22px; background:rgba(8,8,12,0.9); border:1px solid rgba(255,255,255,0.25); padding:14px 26px;">
    <div style="display:flex; flex-direction:column; align-items:center; gap:6px;">
      <div style="font-weight:600; font-size:16px; letter-spacing:0.3em; text-transform:uppercase; color:rgba(232,230,224,0.55);">{{ __('event-taekwondo_tournament::messages.ctl_round') }}</div>
      <div style="display:flex; align-items:center; gap:12px;">
        <button id="btnAwardAka" class="btn" style="font-family:'Anton',sans-serif; font-size:20px; padding:10px 14px; border-color:oklch(0.6 0.22 25); color:#ff8b80;">◀</button>
        <div id="roundNo" style="font-family:'Anton',sans-serif; font-size:72px; line-height:1; color:oklch(0.85 0.16 85); min-width:60px; text-align:center;">1</div>
        <button id="btnAwardAo" class="btn" style="font-family:'Anton',sans-serif; font-size:20px; padding:10px 14px; border-color:oklch(0.52 0.19 255); color:#8bb8ff;">▶</button>
      </div>
      <div id="roundHint" style="font-weight:600; font-size:15px; letter-spacing:0.16em; text-transform:uppercase; color:rgba(232,230,224,0.5);"></div>
      <button id="btnAwardScore" class="btn" style="padding:8px 16px; font-size:15px;">{{ __('event-taekwondo_tournament::messages.ctl_award_round') }}</button>
    </div>
    <button id="btnGolden" class="btn" style="font-weight:800; font-size:20px; padding:14px 20px; border:2px solid oklch(0.85 0.16 85);">{{ __('event-taekwondo_tournament::messages.ctl_golden') }}</button>
  </div>
</div>

{{-- ── The two corners ─────────────────────────────────────────────────── --}}
{{-- `min-height:0` because the stage is a fixed 1080 that CLIPS rather than
     grows: a flex item defaults to min-height:auto and so refuses to shrink
     below its content, which would push the bottom of these panels off the
     stage instead of letting them fit. The stage is 1920 wide, so auto-fit
     always resolves to the two columns the design wants. --}}
<div style="flex:1; min-height:0; display:grid; grid-template-columns:repeat(auto-fit, minmax(440px, 1fr)); gap:14px;" id="corners"></div>

{{-- The picker for a competitor photo. One input, reused by both corners —
     which side it is for is held in JS while the dialog is open. --}}
<input id="photoPicker" type="file" accept="image/png,image/jpeg,image/webp" hidden>

{{-- ── Framing the picture ─────────────────────────────────────────────────
     A picture taken at the desk is a phone snap: a person somewhere in a
     landscape frame, with half a hall behind them. The screens draw a PORTRAIT,
     three wide to four tall, so something has to decide which part of that snap
     is the competitor — and the only person who can decide it is the official
     looking at both the photo and the athlete.

     Deliberately NOT the app's shared cropper widget. That one is a design
     system modal built on jQuery and the webapp's tokens; this page is an
     appliance console that loads no framework and is read at arm's length under
     bad light. So the frame is the console's own: same card, same gold rule,
     same buttons as the Undo sheet beside it, sized for the 1920x1080 stage.

     What comes out is always exactly 600x800 — the shape of the panel it will
     be drawn in — so no screen ever has to letterbox or squash it. --}}
<div id="cropModal" hidden style="position:fixed; inset:0; background:rgba(0,0,0,0.78); backdrop-filter:blur(4px); z-index:55; display:flex; align-items:center; justify-content:center;">
  <div style="background:#101016; border:2px solid oklch(0.85 0.16 85 / 0.7); padding:22px 26px 24px; animation:modalPop 0.35s cubic-bezier(0.22,1,0.36,1) both; display:flex; flex-direction:column; gap:16px; box-shadow:0 30px 90px rgba(0,0,0,0.8);">

    <div style="display:flex; align-items:center; gap:12px;">
      <div id="cropTag" style="font-weight:800; font-size:17px; letter-spacing:0.22em; text-transform:uppercase; color:#fff; padding:3px 12px 3px 14px;"></div>
      <div id="cropTitle" style="font-family:'Anton',sans-serif; font-size:26px; letter-spacing:0.1em; text-transform:uppercase; color:oklch(0.85 0.16 85);">{{ __('event-taekwondo_tournament::messages.ctl_crop_title') }}</div>
      <div style="flex:1;"></div>
      <button id="cropClose" class="btn" style="font-family:'Anton',sans-serif; font-size:22px; width:40px; height:40px; padding:0;">✕</button>
    </div>

    {{-- The frame. Fixed 420x560 — the same 3:4 the corner panel and both wall
         screens draw, so what the official lines up here is exactly what the
         hall sees. The image moves behind it; the frame never moves. --}}
    <div id="cropView" style="width:420px; height:560px; position:relative; overflow:hidden; background:#000; border:2px solid rgba(255,255,255,0.35); cursor:grab; touch-action:none; user-select:none;">
      <img id="cropImg" alt="" draggable="false" style="position:absolute; left:0; top:0; max-width:none; pointer-events:none; -webkit-user-drag:none;">
      {{-- Thirds, and a head guide. An official framing a portrait in a hurry
           puts the face in the middle of the box; the screens crop tight to the
           top, so the guide says where the head belongs. --}}
      <div style="position:absolute; inset:0; pointer-events:none;
                  background:
                    linear-gradient(to right, transparent 33.33%, rgba(255,255,255,0.18) 33.33%, rgba(255,255,255,0.18) calc(33.33% + 1px), transparent calc(33.33% + 1px), transparent 66.66%, rgba(255,255,255,0.18) 66.66%, rgba(255,255,255,0.18) calc(66.66% + 1px), transparent calc(66.66% + 1px)),
                    linear-gradient(to bottom, transparent 33.33%, rgba(255,255,255,0.18) 33.33%, rgba(255,255,255,0.18) calc(33.33% + 1px), transparent calc(33.33% + 1px), transparent 66.66%, rgba(255,255,255,0.18) 66.66%, rgba(255,255,255,0.18) calc(66.66% + 1px), transparent calc(66.66% + 1px));"></div>
      <div id="cropHead" style="position:absolute; left:50%; top:15%; width:190px; height:190px; transform:translate(-50%,-50%); border:2px dashed rgba(255,255,255,0.3); border-radius:50%; pointer-events:none;"></div>
    </div>

    <div style="display:flex; align-items:center; gap:12px;">
      <div class="meta-k" style="flex:0 0 auto;">{{ __('event-taekwondo_tournament::messages.ctl_crop_zoom') }}</div>
      <button id="cropOut" class="btn" style="font-family:'Anton',sans-serif; font-size:22px; width:44px; height:40px; padding:0;">−</button>
      {{-- A bar, not a range input: the OS draws its own slider and it would be
           the one control on this page that did not belong to it. --}}
      <div style="flex:1; height:10px; background:rgba(255,255,255,0.12); border:1px solid rgba(255,255,255,0.25);">
        <div id="cropBar" style="height:100%; width:0; background:oklch(0.85 0.16 85); transition:width .1s linear;"></div>
      </div>
      <button id="cropIn" class="btn" style="font-family:'Anton',sans-serif; font-size:22px; width:44px; height:40px; padding:0;">+</button>
      <button id="cropRot" class="btn" style="width:44px; height:40px; padding:0; font-size:20px;">⟳</button>
    </div>

    <div style="display:flex; align-items:center; gap:12px;">
      <div class="meta-k" style="flex:1; letter-spacing:0.14em;">{{ __('event-taekwondo_tournament::messages.ctl_crop_hint') }}</div>
      <button id="cropCancel" class="btn" style="padding:13px 22px;">{{ __('event-taekwondo_tournament::messages.ctl_crop_cancel') }}</button>
      <button id="cropSave" class="btn btn-go" style="padding:13px 30px;">{{ __('event-taekwondo_tournament::messages.ctl_crop_save') }}</button>
    </div>
  </div>
</div>

{{-- ── The result ──────────────────────────────────────────────────────────
     The same celebration the hall is looking at, plus the one thing the hall
     cannot do: send the result and call the next match up. It sits over the
     console rather than beside it because once a match is decided there is
     nothing else on this page worth pressing. --}}
<div id="winnerOverlay" hidden style="position:absolute; inset:0; z-index:40; overflow:hidden;">
  {{-- The scene is painted here, and it is the SAME scene the wall is showing.
       The controls live outside it: the celebration clears its own host on every
       restage, and these two buttons keep their handlers by being moved into the
       one slot it offers rather than rebuilt. --}}
  <div id="winnerScene" style="position:absolute; inset:0;"></div>
  <div id="winnerControls" style="display:flex; flex-direction:column; gap:10px;">
    <div style="display:flex; gap:14px; align-items:center; flex-wrap:wrap;">
      <button id="winnerNext" class="btn btn-go" style="font-size:24px; padding:18px 40px;"></button>
      {{-- Same size, same weight, same corners as the green one beside it; quiet
           only in its colour. A pixel off the padding because .btn carries a
           border and .btn-go does not, so the two stand the same height. --}}
      {{-- Filled, not a ghost: .btn's 8% white vanishes against the celebration
           behind it. The fill is inline so the class keeps serving every other
           button on this console exactly as it does today. --}}
      <button id="winnerBack" class="btn" style="font-size:24px; font-weight:800; padding:17px 39px; background:linear-gradient(135deg,#3c4460,#1b1f2e); color:#eef1f8; border-color:rgba(255,255,255,0.38); box-shadow:0 12px 34px rgba(0,0,0,0.5);">{{ __('event-taekwondo_tournament::messages.ctl_winner_back') }}</button>
    </div>
    <div id="winnerHint" style="font-weight:600; font-size:16px; letter-spacing:.08em; text-transform:uppercase; color:rgba(232,230,224,0.45);"></div>
  </div>
</div>

{{-- ── Undo ────────────────────────────────────────────────────────────── --}}
<div id="undoModal" hidden style="position:fixed; inset:0; background:rgba(0,0,0,0.7); backdrop-filter:blur(4px); z-index:50; display:flex; align-items:center; justify-content:center;">
  <div style="width:560px; max-height:900px; overflow-y:auto; background:#101016; border:2px solid oklch(0.85 0.16 85 / 0.7); padding:28px 30px; animation:modalPop 0.35s cubic-bezier(0.22,1,0.36,1) both; display:flex; flex-direction:column; gap:18px; box-shadow:0 30px 90px rgba(0,0,0,0.8);">
    <div style="display:flex; align-items:center; gap:12px;">
      <div style="font-family:'Anton',sans-serif; font-size:26px; letter-spacing:0.1em; text-transform:uppercase; color:oklch(0.85 0.16 85);">{{ __('event-taekwondo_tournament::messages.ctl_undo_title') }}</div>
      <div style="flex:1;"></div>
      <button id="undoClose" class="btn" style="font-family:'Anton',sans-serif; font-size:22px; width:40px; height:40px; padding:0;">✕</button>
    </div>
    <div id="undoList" style="display:flex; flex-direction:column; gap:8px;"></div>
    <button id="undoDo" class="btn btn-go" style="padding:13px;">{{ __('event-taekwondo_tournament::messages.ctl_undo') }}</button>
  </div>
</div>

</div></div>

<script>
(function () {
  'use strict';

  var STATE = @json($state);
  var QUEUE = @json($queue);
  // Which registration sits in each corner, so a photo is attached to the
  // right entry. Ids only; the endpoint re-checks them against this event.
  var CORNERS = @json($corners);
  // Where this console posts. Two callers pass two different pairs: a signed-in
  // official gets the session routes, a paired tablet gets its own token's —
  // same page, same code, one authorisation model each.
  var URL_PHOTO = @json($photoUrl);
  var MAT = @json($court);
  var URL_CMD = @json($commandUrl);
  var GAM_LIMIT = @json(\App\Events\Sports\Taekwondo\Tournament\Scoreboard\MatState::GAM_JEOM_LIMIT);
  var TBD = @json(__('event-taekwondo_tournament::messages.court_tbd'));
  var T = {
    start: @json(__('event-taekwondo_tournament::messages.ctl_start')),
    pause: @json(__('event-taekwondo_tournament::messages.ctl_pause')),
    waiting: @json(__('event-taekwondo_tournament::messages.ctl_waiting')),
    none: @json(__('event-taekwondo_tournament::messages.ctl_undo_none')),
    vs: @json(__('event-taekwondo_tournament::messages.ctl_vs')),
    hong: @json(__('event-taekwondo_tournament::messages.sb_hong')),
    chung: @json(__('event-taekwondo_tournament::messages.sb_chung')),
    gamjeom: @json(__('event-taekwondo_tournament::messages.ctl_gamjeom')),
    gamhint: @json(__('event-taekwondo_tournament::messages.ctl_gamjeom_hint')),
    winner: @json(__('event-taekwondo_tournament::messages.sb_winner')),
    byRounds: @json(__('event-taekwondo_tournament::messages.ctl_won_rounds')),
    byPun: @json(__('event-taekwondo_tournament::messages.ctl_won_pun')),
    byGolden: @json(__('event-taekwondo_tournament::messages.ctl_won_golden')),
    nextIs: @json(__('event-taekwondo_tournament::messages.ctl_next_is')),
    recordNext: @json(__('event-taekwondo_tournament::messages.ctl_record_next')),
    recordLast: @json(__('event-taekwondo_tournament::messages.ctl_record_last')),
    photoAdd: @json(__('event-taekwondo_tournament::messages.ctl_photo_add')),
    photoNoEntry: @json(__('event-taekwondo_tournament::messages.ctl_photo_no_entry')),
    photoBig: @json(__('event-taekwondo_tournament::messages.ctl_photo_big')),
    photoBad: @json(__('event-taekwondo_tournament::messages.ctl_photo_bad')),
    photoChange: @json(__('event-taekwondo_tournament::messages.ctl_photo_change')),
    crestAdd: @json(__('event-taekwondo_tournament::messages.ctl_crest_add')),
    crestChange: @json(__('event-taekwondo_tournament::messages.ctl_crest_change')),
    crestSaved: @json(__('event-taekwondo_tournament::messages.ctl_crest_saved')),
    cropTitle: @json(__('event-taekwondo_tournament::messages.ctl_crop_title')),
    crestTitle: @json(__('event-taekwondo_tournament::messages.ctl_crest_title')),
    photoSaved: @json(__('event-taekwondo_tournament::messages.ctl_photo_saved')),
    refreshed: @json(__('event-taekwondo_tournament::messages.ctl_refreshed'))
  };
  var ACTIONS = [
    { key: 'punch', n: 1, label: @json(__('event-taekwondo_tournament::messages.ctl_punch')) },
    { key: 'body', n: 2, label: @json(__('event-taekwondo_tournament::messages.ctl_body')) },
    { key: 'head', n: 3, label: @json(__('event-taekwondo_tournament::messages.ctl_head')) },
    { key: 'turn_body', n: 4, label: @json(__('event-taekwondo_tournament::messages.ctl_turn_body')) },
    { key: 'turn_head', n: 5, label: @json(__('event-taekwondo_tournament::messages.ctl_turn_head')) }
  ];

  var el = function (id) { return document.getElementById(id); };
  var root = el('root');

  // ── Stage scaling: the layout never reflows, it only scales. ─────────────
  // Identical to the two wall screens, so all three surfaces of this event
  // behave the same way on whatever they are opened on.
  function fit() {
    var r = root.getBoundingClientRect();
    if (r.width && r.height) root.style.setProperty('--stage-scale', Math.min(r.width / 1920, r.height / 1080));
  }
  (window.ResizeObserver ? new ResizeObserver(fit).observe(root) : window.addEventListener('resize', fit));
  fit();

  /* ── Talking to the mat ────────────────────────────────────────────────
     Never optimistic. A console showing a point the server did not record is
     worse than one that lags 40ms, because the hall is watching the other
     screen and the two would disagree. */
  var busy = false;
  function send(command, payload) {
    if (busy) return Promise.resolve();
    busy = true;
    return fetch(URL_CMD, {
      method: 'POST',
      headers: { 'Accept': 'application/json', 'Content-Type': 'application/json',
                 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
      credentials: 'same-origin',
      body: JSON.stringify(Object.assign({ mat: MAT, command: command }, payload || {})),
    }).then(function (r) { return r.json().catch(function () { return {}; }); })
      .then(function (d) {
        // A refusal carries the current truth with it. Adopt it either way, so
        // a console that had fallen behind stops showing what it merely
        // believed — otherwise a stale celebration sits there offering a button
        // that will be refused again on every press.
        if (d.state) { STATE = d.state; received = performance.now(); }
        if (d.queue) { QUEUE = d.queue; }
        if (d.corners) { CORNERS = d.corners; }
        if (d.state || d.queue) { paint(); }
        if (!d.success) throw new Error(d.message || 'Command failed');
      })
      .catch(function (e) { alertBar(e.message); })
      .finally(function () { busy = false; });
  }

  // No toast library on this document — it does not extend a layout. A bar
  // across the top is louder anyway, which is right at a mat.
  function alertBar(msg) {
    var b = document.createElement('div');
    // The font is stated explicitly. This is appended to <body>, and the
    // layout's font-family lives on #stage since the console became a scaled
    // stage — so without it the bar rendered in the browser's serif default
    // and looked like a broken page rather than part of the product.
    b.style.cssText = "position:fixed;top:0;left:0;right:0;z-index:60;background:#ff3b47;color:#fff;" +
      "font-family:'Barlow Condensed',sans-serif;font-size:26px;font-weight:700;letter-spacing:.08em;" +
      'text-transform:uppercase;text-align:center;padding:16px 20px;box-shadow:0 6px 30px rgba(0,0,0,.6);';
    b.textContent = msg;
    document.body.appendChild(b);
    setTimeout(function () { b.remove(); }, 4000);
  }

  /** The same bar, for something that went right. */
  function alertOk(msg) {
    var b = document.createElement('div');
    b.style.cssText = "position:fixed;top:0;left:0;right:0;z-index:60;background:oklch(0.55 0.17 145);color:#fff;" +
      "font-family:'Barlow Condensed',sans-serif;font-size:26px;font-weight:700;letter-spacing:.08em;" +
      'text-transform:uppercase;text-align:center;padding:16px 20px;box-shadow:0 6px 30px rgba(0,0,0,.6);';
    b.textContent = msg;
    document.body.appendChild(b);
    setTimeout(function () { b.remove(); }, 2500);
  }

  function safeUrl(u) {
    return (typeof u === 'string' && /^(https?:\/\/|\/)[^"'()\\\s]*$/.test(u)) ? u : null;
  }
  function flagUrl(code) {
    return /^[a-z]{2}$/.test(String(code || '')) ? 'https://flagcdn.com/w1280/' + code + '.png' : null;
  }
  function bgOf(url) {
    var u = safeUrl(url);
    return u ? 'url("' + encodeURI(u) + '")' : 'none';
  }

  /* ── The clock, derived exactly as the wall derives it ─────────────────── */
  var received = performance.now();
  function liveRemaining() {
    if (!STATE.running) return STATE.remaining || 0;
    return Math.max(0, STATE.remaining - (performance.now() - received) / 1000);
  }
  function paintClock() {
    var t = liveRemaining();
    var low = t <= 10 && t > 0 && STATE.running && STATE.phase !== 'rest';
    el('timer').textContent = Math.floor(t / 60) + ':' + String(Math.floor(t % 60)).padStart(2, '0');
    el('timer').style.animation = low ? 'urgentBlink 0.9s ease-in-out infinite' : 'none';
    el('runDot').hidden = !STATE.running;
    el('btnTimer').textContent = STATE.running ? T.pause : T.start;
    el('phase').textContent = STATE.matchId ? (STATE.phaseLabel || '') : T.waiting;
  }
  setInterval(paintClock, 100);

  /* ── The queue ─────────────────────────────────────────────────────────── */
  function paintQueue() {
    var host = el('queue');
    host.textContent = '';
    QUEUE.forEach(function (q) {
      var loaded = q.id === STATE.matchId;
      var row = document.createElement('div');
      row.style.cssText = 'display:flex; align-items:center; gap:14px; background:' +
        (loaded ? 'rgba(253,196,54,0.12)' : 'rgba(0,0,0,0.35)') + '; border:1px solid ' +
        (loaded ? 'oklch(0.85 0.16 85 / 0.7)' : 'rgba(255,255,255,0.18)') + '; padding:9px 14px;' +
        (q.runnable ? '' : 'opacity:.45;');

      var no = document.createElement('div');
      no.style.cssText = "font-family:'Anton',sans-serif; font-size:26px; color:oklch(0.85 0.16 85); min-width:44px;";
      no.textContent = q.number == null ? '—' : q.number;
      row.appendChild(no);

      var mid = document.createElement('div');
      mid.style.cssText = 'flex:1; min-width:0; display:flex; flex-direction:column;';
      var names = document.createElement('div');
      names.style.cssText = 'font-weight:700; font-size:17px; text-transform:uppercase; color:#fff; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;';
      // Built from nodes, never innerHTML: these are names somebody typed.
      names.appendChild(document.createTextNode((q.aka || TBD) + ' '));
      var vs = document.createElement('span');
      vs.style.color = 'oklch(0.7 0.17 25)';
      vs.textContent = T.vs;
      names.appendChild(vs);
      names.appendChild(document.createTextNode(' ' + (q.ao || TBD)));
      var sub = document.createElement('div');
      sub.style.cssText = 'font-weight:600; font-size:13px; letter-spacing:0.1em; text-transform:uppercase; color:rgba(232,230,224,0.6); white-space:nowrap;';
      sub.textContent = [q.stage, q.division].filter(Boolean).join(' · ');
      mid.appendChild(names); mid.appendChild(sub);
      row.appendChild(mid);

      var btn = document.createElement('button');
      btn.className = 'btn';
      btn.style.cssText += 'flex:0 0 auto; font-size:16px; padding:8px 16px; border-color:oklch(0.85 0.16 85);';
      btn.textContent = @json(__('event-taekwondo_tournament::messages.ctl_load'));
      btn.disabled = !q.runnable || loaded;
      btn.onclick = function () { load(q.id); };
      row.appendChild(btn);

      host.appendChild(row);
    });
  }

  function load(id) {
    return send('load', {
      match_id: id,
      minutes: parseFloat(el('cfgMin').value) || 2,
      rounds: parseInt(el('cfgRounds').value, 10) || 3
    }).then(function () {
      // The sheet has done its job — get it off the scoring buttons.
      el('queueModal').hidden = true;
    });
  }

  /* ── The corners ───────────────────────────────────────────────────────── */
  var built = false;
  function buildCorners() {
    var host = el('corners');
    host.textContent = '';
    [['aka', T.hong, 'oklch(0.6 0.22 25)', 'rowInL'], ['ao', T.chung, 'oklch(0.52 0.19 255)', 'rowInR']].forEach(function (c) {
      var side = c[0], label = c[1], accent = c[2], anim = c[3];

      // The two corners face each other, as they do on the wall and in the
      // introduction: red reads left-to-right from its portrait, blue is the
      // mirror image of it. A console where both panels pointed the same way
      // gave the operator no spatial cue for which half of the mat they were
      // touching — and the hall screens beside them are already mirrored.
      var mirror = side === 'ao';
      var rev = mirror ? ' flex-direction:row-reverse;' : '';

      var panel = document.createElement('div');
      panel.style.cssText = 'display:flex; flex-direction:column; gap:12px; background:' +
        // The gradient mirrors too, so the colour sits under the portrait on
        // both sides rather than under the portrait on one and the score on
        // the other.
        (side === 'aka' ? 'linear-gradient(160deg, oklch(0.3 0.11 25) 0%, rgba(10,10,14,0.95) 70%)'
                        : 'linear-gradient(200deg, oklch(0.28 0.1 255) 0%, rgba(10,10,14,0.95) 70%)') +
        '; border-top:6px solid ' + accent + '; padding:16px 18px; min-width:0; animation:' + anim + ' 0.5s cubic-bezier(0.22,1,0.36,1) both;';

      // `flex:2` so the panel FILLS its cell — the stage is a fixed 1080 and
      // the grid row is tall, and without it the sections sat at their natural
      // height and left a dead band under every panel.
      var top = document.createElement('div');
      top.style.cssText = 'flex:2; min-height:0; display:flex; gap:16px; min-width:0;' + rev;

      // A PORTRAIT: four tall to three wide. Written `3/4` because the CSS
      // property is width/height — `4/3` would give the landscape box, which
      // is the wrong way up for a person.
      //
      // Fixed and centred, NOT stretched to the panel. Letting it absorb the
      // panel's slack turned it into a 1:2 sliver and cropped the competitor's
      // face out of their own photo; the slack goes to the score row below
      // instead, which is `flex:1`. 280x373 inside a ~400px section.
      var photo = document.createElement('div');
      photo.id = side + 'Photo';
      photo.style.cssText = 'width:280px; aspect-ratio:3/4; flex:0 0 auto; align-self:center; background-color:rgba(0,0,0,0.4); background-size:cover; background-position:center 15%; border:2px solid ' + accent + ';' +
        'position:relative; cursor:pointer; display:flex; align-items:center; justify-content:center;';
      photo.title = T.photoAdd;

      // Shown only when the competitor has no picture — an empty panel on the
      // introduction screen reads as broken from ten metres, and the desk is
      // the one place that can fix it while they are standing there.
      var prompt = document.createElement('div');
      prompt.id = side + 'PhotoPrompt';
      prompt.style.cssText = 'pointer-events:none; text-align:center; padding:0 14px; display:flex; flex-direction:column; align-items:center; gap:10px;';
      var pIcon = document.createElement('div');
      pIcon.style.cssText = "font-family:'Anton',sans-serif; font-size:56px; line-height:1; color:rgba(255,255,255,0.5);";
      pIcon.textContent = '+';
      var pText = document.createElement('div');
      pText.style.cssText = 'font-weight:700; font-size:17px; letter-spacing:0.14em; text-transform:uppercase; color:rgba(255,255,255,0.55);';
      pText.textContent = T.photoAdd;
      prompt.appendChild(pIcon); prompt.appendChild(pText);
      photo.appendChild(prompt);

      // A picture that is already there hid the only affordance the panel had,
      // so a wrong or badly framed photo looked permanent. This chip sits on
      // the corner of the portrait whether or not one is set, and says which
      // of the two things a press will do.
      var chip = document.createElement('div');
      chip.id = side + 'PhotoChip';
      chip.style.cssText = 'position:absolute; ' + (mirror ? 'left:0;' : 'right:0;') + ' bottom:0; pointer-events:none;' +
        'font-weight:800; font-size:13px; letter-spacing:0.14em; text-transform:uppercase; color:#fff;' +
        'background:rgba(0,0,0,0.72); border-top:2px solid ' + accent + '; padding:4px 10px;';
      chip.textContent = T.photoChange;
      photo.appendChild(chip);

      photo.onclick = function () {
        if (!CORNERS[side]) { alertBar(T.photoNoEntry); return; }
        pickFor = side;
        pickKind = 'competitor';
        el('photoPicker').value = '';     // so the same file can be re-picked
        el('photoPicker').click();
      };
      top.appendChild(photo);

      var col = document.createElement('div');
      // NOT `align-items:flex-end`. This is a flex COLUMN, so that property
      // sets the CROSS axis — every row would shrink to its own content width
      // and hug the right edge instead of spanning the panel, which made the
      // blue side narrow rather than mirrored. Each row reverses its own
      // direction below; that is what does the mirroring, at full width.
      col.style.cssText = 'flex:1; min-width:0; display:flex; flex-direction:column; gap:8px;' +
        (mirror ? ' text-align:right;' : '');

      var idRow = document.createElement('div');
      idRow.style.cssText = 'display:flex; align-items:center; gap:10px; min-width:0;' + rev;
      var tag = document.createElement('div');
      tag.style.cssText = 'font-weight:800; font-size:17px; letter-spacing:0.22em; text-transform:uppercase; color:#fff; background:' + accent + '; padding:3px 12px 3px 14px; flex:0 0 auto;';
      tag.textContent = label;
      var nm = document.createElement('div');
      nm.id = side + 'Name';
      nm.style.cssText = "font-family:'Anton',sans-serif; font-size:26px; text-transform:uppercase; color:#fff; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;";
      idRow.appendChild(tag); idRow.appendChild(nm);
      col.appendChild(idRow);

      var meta = document.createElement('div');
      meta.style.cssText = 'display:flex; align-items:center; gap:10px; min-width:0; flex-wrap:wrap;' + rev;
      var flag = document.createElement('div');
      flag.id = side + 'Flag';
      flag.style.cssText = 'width:38px; flex:0 0 auto; aspect-ratio:4/3; background-size:100% 100%; image-rendering:auto; background-position:center; border:1px solid rgba(255,255,255,0.4);';
      var country = document.createElement('div');
      country.id = side + 'Country';
      country.style.cssText = 'font-weight:700; font-size:19px; letter-spacing:0.1em; text-transform:uppercase; color:rgba(255,255,255,0.9); white-space:nowrap;';
      var logo = document.createElement('div');
      logo.id = side + 'Logo';
      // `contain`, not `cover`: a crest is a mark with its own margins, and
      // filling a circle with it crops the badge. Bigger than it was, because
      // it is now something an official presses.
      // 64, not 40. A crest is the club's identity and the desk's second
      // upload target, and at 40 it was neither: too small to recognise a badge
      // across a scoring table, and a fiddly thing to hit with a finger. It sits
      // beside a 38px flag, which is fine — a flag is a wide plate and a crest
      // is a round mark, and the eye reads them as different kinds of thing.
      logo.style.cssText = 'width:64px; height:64px; flex:0 0 auto; border-radius:50%; background-color:rgba(255,255,255,0.1); background-size:contain; background-repeat:no-repeat; background-position:center; border:1px solid rgba(255,255,255,0.35);' +
        'cursor:pointer; position:relative; display:flex; align-items:center; justify-content:center;';
      logo.title = T.crestAdd;

      // A "+" while the club has no crest at all, so an empty circle reads as
      // something to press rather than as a missing image.
      var logoPlus = document.createElement('div');
      logoPlus.id = side + 'LogoPlus';
      logoPlus.style.cssText = "pointer-events:none; font-family:'Anton',sans-serif; font-size:32px; line-height:1; color:rgba(255,255,255,0.55);";
      logoPlus.textContent = '+';
      logo.appendChild(logoPlus);

      logo.onclick = function () {
        if (!CORNERS[side]) { alertBar(T.photoNoEntry); return; }
        pickFor = side;
        pickKind = 'club';
        el('photoPicker').value = '';
        el('photoPicker').click();
      };
      var club = document.createElement('div');
      club.id = side + 'Club';
      club.style.cssText = 'font-weight:600; font-size:18px; letter-spacing:0.06em; text-transform:uppercase; color:rgba(232,230,224,0.75); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; min-width:0;';
      [flag, country, logo, club].forEach(function (n) { meta.appendChild(n); });
      col.appendChild(meta);

      var scoreRow = document.createElement('div');
      scoreRow.style.cssText = 'flex:1; display:flex; align-items:center; justify-content:center; gap:20px;' + rev;
      var score = document.createElement('div');
      score.id = side + 'Score';
      // Sized to the box it actually sits in. The stage is a fixed 1920x1080,
      // so the score row is a known 321x603 — at 200px the numeral used barely
      // half its height. 320px leaves ~33px of vertical breathing room and
      // still fits three digits beside the +/- column (584 of 603), which no
      // WT match will ever reach. `tabular-nums` so 1 and 8 are the same width
      // and the plate does not jump as the score climbs.
      score.style.cssText = "font-family:'Anton',sans-serif; font-size:320px; line-height:0.9; color:#fffdf5; font-variant-numeric:tabular-nums;";
      score.textContent = '0';
      var pm = document.createElement('div');
      pm.style.cssText = 'display:flex; flex-direction:column; gap:6px;';
      [['−1', -1, '#ff8b80', 'rgba(255,120,110,0.5)'], ['+1', 1, '#9fe8a8', 'rgba(140,230,150,0.5)']].forEach(function (b) {
        var x = document.createElement('button');
        x.className = 'bout';
        x.style.cssText = "font-family:'Anton',sans-serif; font-size:24px; background:rgba(0,0,0,0.4); color:" + b[2] +
          '; border:1px solid ' + b[3] + '; width:84px; height:76px; cursor:pointer;';
        x.textContent = b[0];
        x.onclick = function () { send('adjust', { side: side, n: b[1] }); };
        pm.appendChild(x);
      });
      scoreRow.appendChild(score); scoreRow.appendChild(pm);
      col.appendChild(scoreRow);

      top.appendChild(col);
      panel.appendChild(top);

      var grid = document.createElement('div');
      // `flex:0 1 auto` rather than `flex:1`: the row is now as tall as the
      // squares it holds, and the slack goes back to the corner above it —
      // which is what the portrait and the score want anyway. `align-items:
      // start` keeps a squeezed square from being stretched back into a
      // rectangle by the grid.
      grid.style.cssText = 'flex:0 1 auto; min-height:0; display:grid; grid-template-columns:repeat(5, minmax(0, 1fr)); align-items:start; gap:8px;';
      // The ONE thing that does not mirror. Everything else about the two
      // panels is a reflection, but these five read Punch → Turn Head on both
      // sides, in ascending value, always. An operator hits them under
      // pressure without looking away from the fight, and a grid that reads
      // one way on red and the other way on blue is how the wrong technique
      // gets scored. Consistency beats symmetry here.
      ACTIONS.forEach(function (a) {
        var b = document.createElement('button');
        b.className = 'bout pt';
        var big = document.createElement('span');
        big.textContent = '+' + a.n;
        b.appendChild(big);
        b.appendChild(document.createTextNode(a.label));
        b.onmouseenter = function () { b.style.background = accent; };
        b.onmouseleave = function () { b.style.background = 'rgba(0,0,0,0.45)'; };
        b.onclick = function () { send('score', { side: side, action: a.key }); };
        grid.appendChild(b);
      });
      panel.appendChild(grid);

      var gam = document.createElement('div');
      gam.style.cssText = 'display:flex; align-items:center; gap:12px; background:rgba(0,0,0,0.35); border:1px solid oklch(0.85 0.16 85 / 0.4); padding:10px 14px;' + rev;
      var gl = document.createElement('span');
      gl.style.cssText = 'font-weight:700; font-size:19px; letter-spacing:0.2em; text-transform:uppercase; color:oklch(0.85 0.16 85);';
      gl.textContent = T.gamjeom;
      var minus = document.createElement('button');
      minus.className = 'bout';
      minus.style.cssText = "font-family:'Anton',sans-serif; font-size:22px; background:rgba(255,255,255,0.1); color:#e8e6e0; border:1px solid rgba(255,255,255,0.3); width:46px; height:42px; cursor:pointer;";
      minus.textContent = '−';
      minus.onclick = function () { send('gamjeom', { side: side, dir: -1 }); };
      var dots = document.createElement('div');
      dots.id = side + 'Gam';
      dots.style.cssText = 'display:flex; gap:7px; flex:1; justify-content:center;';
      var plus = document.createElement('button');
      plus.className = 'bout';
      plus.style.cssText = "font-family:'Anton',sans-serif; font-size:22px; background:oklch(0.85 0.16 85); color:#141210; border:none; width:46px; height:42px; cursor:pointer; font-weight:800;";
      plus.textContent = '+';
      plus.onclick = function () { send('gamjeom', { side: side, dir: 1 }); };
      var hint = document.createElement('span');
      hint.style.cssText = 'font-weight:600; font-size:15px; letter-spacing:0.08em; text-transform:uppercase; color:rgba(232,230,224,0.55); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;';
      hint.textContent = T.gamhint;
      [gl, minus, dots, plus, hint].forEach(function (n) { gam.appendChild(n); });
      panel.appendChild(gam);

      host.appendChild(panel);
    });
    built = true;
  }

  function gamDots(id, n) {
    var host = el(id);
    if (!host) return;
    host.textContent = '';
    for (var i = 0; i < GAM_LIMIT; i++) {
      var on = i < n;
      var d = document.createElement('div');
      d.style.cssText = 'width:20px; height:20px; transform:rotate(45deg); background:' +
        (on ? 'oklch(0.85 0.16 85)' : 'rgba(0,0,0,0.25)') + '; border:2px solid ' +
        (on ? '#fffdf0' : 'rgba(255,255,255,0.4)') + ';' +
        (i === n - 1 ? 'animation:foulFlash 0.4s ease-out;' : '');
      host.appendChild(d);
    }
  }

  function paintCorner(side) {
    var c = (side === 'aka' ? STATE.aka : STATE.ao) || {};
    el(side + 'Name').textContent = c.name || TBD;
    el(side + 'Club').textContent = c.club || '';
    el(side + 'Country').textContent = c.country || '';
    el(side + 'Flag').style.backgroundImage = bgOf(flagUrl(c.flag));
    el(side + 'Logo').style.backgroundImage = bgOf(c.logo);
    el(side + 'Photo').style.backgroundImage = bgOf(c.photo);
    // The big "+" only while the panel is empty; the chip always, so a picture
    // that is already there can still be replaced.
    el(side + 'PhotoPrompt').style.display = c.photo ? 'none' : 'flex';
    var chip = el(side + 'PhotoChip');
    chip.textContent = c.photo ? T.photoChange : T.photoAdd;
    // Nothing to attach a picture to until a match is on the mat.
    chip.style.display = CORNERS[side] ? 'block' : 'none';

    // The crest: the "+" only while the circle is empty, and the tooltip says
    // which of the two pressing it will do.
    var lg = el(side + 'Logo');
    el(side + 'LogoPlus').style.display = c.logo ? 'none' : 'block';
    lg.title = c.logo ? T.crestChange : T.crestAdd;
    lg.style.cursor = CORNERS[side] ? 'pointer' : 'default';

    var s = el(side + 'Score'), v = String(side === 'aka' ? STATE.akaScore : STATE.aoScore);
    if (s.textContent !== v) {
      s.textContent = v;
      s.style.animation = 'none'; void s.offsetWidth;
      s.style.animation = 'ctrlScorePop 0.5s cubic-bezier(0.22,1,0.36,1)';
    }
    gamDots(side + 'Gam', side === 'aka' ? STATE.akaGam : STATE.aoGam);
  }

  /* ── A competitor picture, added at the desk ────────────────────────────
     The picked file is never sent as it was picked. It goes into the frame
     above, the official decides which part of it is the competitor, and what
     leaves the browser is exactly the 600x800 portrait the screens draw — so
     a phone snap several megabytes wide arrives as a few tens of kilobytes,
     already the right shape, on venue wifi.

     The server still validates the real bytes it receives. Everything here is
     a courtesy to the link and to the framing, never a security measure,
     because anything done in a browser can be skipped. */
  var pickFor = null;
  var pickKind = 'competitor';

  /* The two things a desk can photograph, and the shape each has to come out.
     A COMPETITOR is a portrait — three wide to four tall, the shape of the
     panel on every screen — and JPEG, because it is a photograph.
     A CREST is square, and PNG: a club mark is transparent, and JPEG has no
     alpha, so encoding one as JPEG would flatten it onto a black box and put
     exactly the white/dark tile behind it that a logo must never sit on. */
  var KINDS = {
    competitor: { out: [600, 800], frame: [420, 560], mime: 'image/jpeg', q: 0.92, head: true },
    club:       { out: [512, 512], frame: [420, 420], mime: 'image/png',  q: 1,    head: false }
  };

  var OUT_W = 600, OUT_H = 800;      // filled in per pick from KINDS
  var FRAME_W = 420, FRAME_H = 560;
  var ZOOM_MAX = 5;

  // `base` is the zoom at which the picture exactly covers the frame — zoom 1
  // is therefore "as wide as it can be while still filling it", and no zoom in
  // this range can ever expose a transparent edge.
  var crop = { img: null, side: null, base: 1, zoom: 1, x: 0, y: 0, busy: false };

  el('photoPicker').onchange = function (ev) {
    var file = ev.target.files && ev.target.files[0];
    var side = pickFor;
    if (!file || !side || !CORNERS[side]) return;

    // A hard ceiling before we even decode it.
    if (file.size > 25 * 1024 * 1024) { alertBar(T.photoBig); return; }

    var reader = new FileReader();
    reader.onload = function () {
      var img = new Image();
      img.onload = function () { openCrop(side, img, pickKind); };
      // A file that is not really an image never reaches the frame, let alone
      // the network.
      img.onerror = function () { alertBar(T.photoBad); };
      img.src = reader.result;
    };
    reader.onerror = function () { alertBar(T.photoBad); };
    reader.readAsDataURL(file);
  };

  function openCrop(side, img, kind) {
    crop.img = img; crop.side = side; crop.zoom = 1; crop.x = 0; crop.y = 0;
    crop.kind = kind || crop.kind || 'competitor';

    // The frame takes the shape of what is being framed, and the sheet says so.
    var K = KINDS[crop.kind];
    OUT_W = K.out[0]; OUT_H = K.out[1];
    FRAME_W = K.frame[0]; FRAME_H = K.frame[1];
    var view = el('cropView');
    view.style.width = FRAME_W + 'px';
    view.style.height = FRAME_H + 'px';
    el('cropHead').hidden = !K.head;
    // A crest is transparent, so the frame shows a chequerboard behind it —
    // otherwise an official cannot tell a white badge from a white background
    // until it is already on the wall.
    view.style.background = K.head ? '#000'
      : 'repeating-conic-gradient(#2a2a33 0% 25%, #1a1a22 0% 50%) 50% / 24px 24px';
    el('cropTitle').textContent = K.head ? T.cropTitle : T.crestTitle;

    crop.base = Math.max(FRAME_W / img.width, FRAME_H / img.height);

    // The sheet wears the corner's own colour, so an official adding two
    // pictures in a row is never in doubt which one they are framing.
    var accent = side === 'aka' ? 'oklch(0.6 0.22 25)' : 'oklch(0.52 0.19 255)';
    var tag = el('cropTag');
    tag.textContent = side === 'aka' ? T.hong : T.chung;
    tag.style.background = accent;
    el('cropView').style.borderColor = accent;

    el('cropImg').src = img.src;
    drawCrop();
    el('cropModal').hidden = false;
  }

  function closeCrop() {
    el('cropModal').hidden = true;
    // Drop the decoded picture; a phone photo held here is tens of megabytes
    // of bitmap on a laptop that has a competition left to run.
    crop.img = null;
    el('cropImg').removeAttribute('src');
  }

  /* Lay the picture out behind the frame, and never let it come off it. */
  function drawCrop() {
    if (!crop.img) return;
    var s = crop.base * crop.zoom;
    var w = crop.img.width * s, h = crop.img.height * s;

    // Slack is what the picture has beyond the frame; the offset can spend it
    // and no more. Clamped on every move AND on every zoom out, or zooming out
    // after panning to a corner would tear a black wedge into the frame.
    var slackX = Math.max(0, (w - FRAME_W) / 2), slackY = Math.max(0, (h - FRAME_H) / 2);
    crop.x = Math.max(-slackX, Math.min(slackX, crop.x));
    crop.y = Math.max(-slackY, Math.min(slackY, crop.y));

    var im = el('cropImg');
    im.style.width = w + 'px'; im.style.height = h + 'px';
    im.style.left = (FRAME_W / 2 + crop.x - w / 2) + 'px';
    im.style.top = (FRAME_H / 2 + crop.y - h / 2) + 'px';
    el('cropBar').style.width = ((crop.zoom - 1) / (ZOOM_MAX - 1) * 100) + '%';
  }

  /* Zoom about the CENTRE of the frame: whatever the official has lined up
     stays lined up. Zooming about the picture's own centre would slide the
     face out of the box on every step. */
  function zoomBy(f) {
    var z = Math.max(1, Math.min(ZOOM_MAX, crop.zoom * f));
    var r = z / crop.zoom;
    crop.zoom = z; crop.x *= r; crop.y *= r;
    drawCrop();
  }

  el('cropIn').onclick = function () { zoomBy(1.2); };
  el('cropOut').onclick = function () { zoomBy(1 / 1.2); };

  /* A quarter turn, for the phone that was held sideways. The rotation is
     baked into a new source picture rather than carried as a transform, so
     the framing maths below never has to know about it. */
  el('cropRot').onclick = function () {
    if (!crop.img) return;
    var img = crop.img;
    var cv = document.createElement('canvas');
    cv.width = img.height; cv.height = img.width;
    var g = cv.getContext('2d');
    g.translate(cv.width / 2, cv.height / 2);
    g.rotate(Math.PI / 2);
    g.drawImage(img, -img.width / 2, -img.height / 2);
    var next = new Image();
    next.onload = function () { openCrop(crop.side, next, crop.kind); };
    // PNG both ways here — a quarter turn must not be the step that loses a
    // crest's transparency, and this is an intermediate, not what is stored.
    next.src = cv.toDataURL('image/png');
  };

  el('cropView').onwheel = function (e) {
    e.preventDefault();
    zoomBy(e.deltaY < 0 ? 1.12 : 1 / 1.12);
  };

  /* Drag to move the picture. Pointer movement is in SCREEN pixels and this
     whole console is a 1920x1080 stage scaled to the window, so the delta is
     divided by that scale — without it the picture ran away from the cursor
     on every laptop where the stage is not exactly 1:1. */
  (function () {
    var view = el('cropView'), from = null;

    view.onpointerdown = function (e) {
      if (!crop.img) return;
      from = { x: e.clientX, y: e.clientY };
      view.setPointerCapture(e.pointerId);
      view.style.cursor = 'grabbing';
    };
    view.onpointermove = function (e) {
      if (!from) return;
      var k = parseFloat(getComputedStyle(root).getPropertyValue('--stage-scale')) || 1;
      crop.x += (e.clientX - from.x) / k;
      crop.y += (e.clientY - from.y) / k;
      from = { x: e.clientX, y: e.clientY };
      drawCrop();
    };
    var end = function () { from = null; view.style.cursor = 'grab'; };
    view.onpointerup = end;
    view.onpointercancel = end;
  })();

  el('cropClose').onclick = closeCrop;
  el('cropCancel').onclick = closeCrop;
  el('cropModal').onclick = function (e) { if (e.target === el('cropModal')) closeCrop(); };

  /* What the frame holds, at the picture's own resolution.
     The crop is taken from the SOURCE pixels — the frame is only 420 wide on
     screen, and cutting at that size and stretching it up to 600x800 would
     publish a soft portrait on a wall two metres across. */
  el('cropSave').onclick = function () {
    if (!crop.img || crop.busy) return;
    var side = crop.side;
    if (!side || !CORNERS[side]) { alertBar(T.photoNoEntry); return; }

    var s = crop.base * crop.zoom;
    var w = crop.img.width * s, h = crop.img.height * s;
    var left = FRAME_W / 2 + crop.x - w / 2, top = FRAME_H / 2 + crop.y - h / 2;

    var sx = Math.max(0, -left / s), sy = Math.max(0, -top / s);
    var sw = Math.min(crop.img.width - sx, FRAME_W / s);
    var sh = Math.min(crop.img.height - sy, FRAME_H / s);

    var K = KINDS[crop.kind || 'competitor'];
    var cv = document.createElement('canvas');
    cv.width = OUT_W; cv.height = OUT_H;
    var g = cv.getContext('2d');
    // A picture smaller than the output is enlarged here rather than by the
    // browser at draw time; smoothing on keeps that from turning into blocks.
    g.imageSmoothingEnabled = true;
    g.imageSmoothingQuality = 'high';
    // Nothing is painted under it: a fresh canvas is transparent, and for a
    // crest that transparency is the whole point. JPEG has no alpha and the
    // encoder flattens it to black, which is why a crest is PNG.
    g.drawImage(crop.img, sx, sy, sw, sh, 0, 0, OUT_W, OUT_H);

    crop.busy = true;
    sendPhoto(side, cv.toDataURL(K.mime, K.q), crop.kind)
      .then(function (ok) { if (ok) closeCrop(); })
      .finally(function () { crop.busy = false; });
  };

  /* Resolves to whether it landed, so the frame stays open on a refusal — an
     official whose upload failed still has the picture they framed. */
  function sendPhoto(side, dataUrl, kind) {
    kind = kind || 'competitor';
    var isCrest = kind === 'club';
    var box = el(side + (isCrest ? 'Logo' : 'Photo'));
    box.style.opacity = '.5';
    return fetch(URL_PHOTO, {
      method: 'POST',
      headers: { 'Accept': 'application/json', 'Content-Type': 'application/json',
                 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
      credentials: 'same-origin',
      // The mat rides along so the server can put the picture on the wall
      // behind this desk straight away, rather than holding it for a press of
      // Refresh VS screen.
      body: JSON.stringify({ registration_id: CORNERS[side], image: dataUrl, mat: MAT, kind: kind }),
    }).then(function (r) { return r.json().catch(function () { return {}; }); })
      .then(function (d) {
        if (!d.success) throw new Error(d.message || 'Upload failed');
        // Show it at once on the console. The WALL is not touched until the
        // operator presses Refresh VS screen — they may be part-way through
        // adding both corners, and a screen that changed under a live
        // introduction would be worse than one that waits to be told.
        box.style.backgroundImage = 'url("' + encodeURI(d.url) + '")';
        el(side + (isCrest ? 'LogoPlus' : 'PhotoPrompt')).style.display = 'none';
        alertOk(isCrest ? T.crestSaved : T.photoSaved);
        return true;
      })
      .catch(function (e) { alertBar(e.message); return false; })
      .finally(function () { box.style.opacity = ''; });
  }

  /* ── The result ────────────────────────────────────────────────────────
     Staged ONCE per decided match — `paint()` runs on every keypress, and
     re-running the confetti and the stamp each time would make the plate
     flinch. Hidden again the moment the match is no longer over, which is what
     happens when a mistaken fifth gam-jeom is undone. */
  var staged = null;
  function paintWinner() {
    var over = STATE.matchOver && STATE.matchWinner;
    var host = el('winnerOverlay');

    if (!over) { staged = null; WinnerCelebration.clear(el('winnerScene')); host.hidden = true; return; }

    // Which match comes next on this mat, so the button can name it.
    var next = null;
    for (var i = 0; i < QUEUE.length; i++) {
      if (QUEUE[i].runnable && QUEUE[i].id !== STATE.matchId) { next = QUEUE[i]; break; }
    }

    var side = STATE.matchWinner;
    var key = side + '|' + STATE.matchId + '|' + (next ? next.id : 0);
    if (staged === key) return;
    staged = key;

    var c = (side === 'aka' ? STATE.aka : STATE.ao) || {};

    el('winnerNext').textContent = next ? T.recordNext : T.recordLast;
    el('winnerHint').textContent = next
      ? T.nextIs.replace(':aka', next.aka || TBD).replace(':ao', next.ao || TBD)
      : '';

    host.hidden = false;

    // The overlay must be on the page before the scene measures it, or it is
    // painted against a box of no size.
    var scene = WinnerCelebration.paint(el('winnerScene'), {
      corner: side === 'aka' ? 'red' : 'blue',
      name: c.name || '',
      club: c.club || '',
      logo: c.logo || null,
      photo: c.photo || null,
      label: T.winner,
      // How it was won — the rounds, or the rule that ended it early.
      note: STATE.endReason === 'gamjeom' ? T.byPun
        : STATE.endReason === 'golden' ? T.byGolden
        : T.byRounds.replace(':a', STATE.akaRounds).replace(':b', STATE.aoRounds)
    });

    scene.actions.appendChild(el('winnerControls'));
  }

  /* ── Undo ──────────────────────────────────────────────────────────────── */
  function paintUndo() {
    var host = el('undoList');
    host.textContent = '';
    var log = STATE.log || [];
    if (!log.length) {
      var e = document.createElement('div');
      e.style.cssText = 'font-size:19px; color:rgba(232,230,224,0.5); padding:10px 2px;';
      e.textContent = T.none;
      host.appendChild(e);
      el('undoDo').disabled = true;
      return;
    }
    el('undoDo').disabled = false;
    log.forEach(function (entry, i) {
      var row = document.createElement('div');
      row.style.cssText = 'display:flex; gap:14px; align-items:baseline; padding:8px 12px; background:' +
        (i === 0 ? 'rgba(253,196,54,0.12)' : 'rgba(0,0,0,0.35)') + '; border:1px solid ' +
        (i === 0 ? 'oklch(0.85 0.16 85 / 0.6)' : 'rgba(255,255,255,0.15)') + ';';
      var t = document.createElement('span');
      t.style.cssText = "font-family:'Anton',sans-serif; font-size:17px; color:rgba(232,230,224,0.55);";
      t.textContent = entry.time || '';
      var m = document.createElement('span');
      m.style.cssText = 'font-weight:600; font-size:18px; color:#e8e6e0;';
      m.textContent = entry.msg || '';
      row.appendChild(t); row.appendChild(m);
      host.appendChild(row);
    });
  }

  /* ── Paint ─────────────────────────────────────────────────────────────── */
  function paint() {
    if (!built) buildCorners();

    el('mCourt').textContent = MAT.replace(/[^0-9]/g, '') || MAT;
    el('mNo').textContent = STATE.matchNo || '—';
    el('mClass').textContent = STATE.division || '—';
    el('mCategory').textContent = STATE.category || STATE.stage || '—';

    el('roundNo').textContent = STATE.round || 1;
    el('roundHint').textContent = T.hong.split(' ')[0] + ' ' + (STATE.akaRounds || 0) +
      ' · ' + T.chung.split(' ')[0] + ' ' + (STATE.aoRounds || 0) +
      ' / ' + (STATE.roundsToWin || 2);

    var armed = !!STATE.matchId;
    Array.prototype.forEach.call(document.querySelectorAll('.bout'), function (b) { b.disabled = !armed; });
    ['btnTimer', 'btnRest', 'btnResetRound', 'btnGolden', 'btnAwardAka', 'btnAwardAo', 'btnAwardScore', 'btnCommit', 'btnResetMatch', 'btnClear', 'btnRefresh']
      .forEach(function (id) { el(id).disabled = !armed; });

    paintCorner('aka');
    paintCorner('ao');
    paintQueue();
    paintUndo();
    paintWinner();
    paintClock();
  }

  /* ── Wiring ────────────────────────────────────────────────────────────── */
  el('btnTimer').onclick = function () { send(STATE.running ? 'pause' : 'start'); };
  el('btnRest').onclick = function () { send('rest'); };
  el('btnResetRound').onclick = function () { send('reset_round'); };
  el('btnGolden').onclick = function () { send('golden'); };
  el('btnAwardAka').onclick = function () { send('award_round', { side: 'aka' }); };
  el('btnAwardAo').onclick = function () { send('award_round', { side: 'ao' }); };
  el('btnAwardScore').onclick = function () { send('award_round'); };
  el('btnCommit').onclick = function () { send('commit'); };
  el('btnResetMatch').onclick = function () { send('reset'); };
  el('btnClear').onclick = function () { send('clear'); };
  el('btnNext').onclick = function () {
    for (var i = 0; i < QUEUE.length; i++) {
      if (QUEUE[i].runnable && QUEUE[i].id !== STATE.matchId) { load(QUEUE[i].id); return; }
    }
  };
  // Commit writes the result AND loads whatever is next on this mat, so this
  // is the same command the header button sends — the overlay simply puts it
  // where the operator is already looking.
  el('winnerNext').onclick = function () { send('commit'); };
  // Stand the celebration aside without recording anything, so a mistaken
  // gam-jeom can still be undone. It comes straight back on the next paint if
  // the match is still decided.
  el('winnerBack').onclick = function () { el('winnerOverlay').hidden = true; };
  // Hand the wall whatever the database now holds for these two — the point
  // of it is the picture that was just added. Deliberately separate from the
  // upload so an official can do both corners first and publish once.
  el('btnRefresh').onclick = function () { send('refresh').then(function () { alertOk(T.refreshed); }); };
  el('btnQueue').onclick = function () { paintQueue(); el('queueModal').hidden = false; };
  el('queueClose').onclick = function () { el('queueModal').hidden = true; };
  el('queueModal').onclick = function (e) { if (e.target === el('queueModal')) el('queueModal').hidden = true; };
  el('btnUndo').onclick = function () { paintUndo(); el('undoModal').hidden = false; };
  el('undoClose').onclick = function () { el('undoModal').hidden = true; };
  el('undoModal').onclick = function (e) { if (e.target === el('undoModal')) el('undoModal').hidden = true; };
  el('undoDo').onclick = function () { send('undo').then(function () { paintUndo(); }); };

  // Escape closes whichever sheet is open. An operator at a mat should never
  // have to find a small ✕ with a match waiting on them.
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    el('queueModal').hidden = true;
    el('undoModal').hidden = true;
    if (!el('cropModal').hidden) closeCrop();
  });

  /* A paired console tells the event console it is alive, on the same sixty
     seconds every wall screen uses. Silent by design: an offline beat is not
     something to interrupt an official at a mat about, and the score is posted
     over the same link anyway. */
@isset($heartbeatUrl)
@if($heartbeatUrl)
  // Beats, and notices being UNPAIRED — a scoring table left showing the
  // console after it has been taken off the mat reads as staffed when it is
  // not. Safe to reload: tokenControl() sends a console it cannot open to the
  // board address, which shows the pairing QR.
  setInterval(function () {
    fetch(@json($heartbeatUrl), { cache: 'no-store' })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (s) { if (s && s.claimed === false) window.location.reload(); })
      .catch(function () { /* keep scoring — the table is not the network */ });
  }, 60000);
@endif
@endisset

  paint();

  // Arriving at a clear mat, the queue IS the only useful thing on the page —
  // every scoring control is disabled until something is loaded. So it opens
  // itself, rather than leaving the operator to find the button. Only on
  // arrival: once they are working, what is on the mat is their business.
  if (!STATE.matchId) el('queueModal').hidden = false;
})();
</script>
</body>
</html>
