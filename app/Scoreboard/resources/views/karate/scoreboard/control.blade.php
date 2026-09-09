{{--
    The scoring table at a Karate mat — Match Control.

    Built to the approved design handoff (`drafts/HTML Templates/Karate Shotokan
    Scoreboard Controller.zip` → `design_handoff_match_control/`). Like the wall
    screens it does NOT extend a layout and does not use the design system: it
    is a 1920x1080 broadcast console authored as a whole document, operated at
    speed at a mat rather than browsed. Treat the visual output as fixed —
    restyle by agreement, never as a side effect.

    ── What the handoff changed, and why it reads the way it does ─────────────

    The console used to type its own truth: header fields and competitor names
    were text boxes an official corrected at the table. The design makes them
    READ-ONLY, because they come from the draw — and that is now the rule here.
    What is announced is what was entered; a wrong name is fixed on the entry,
    where the fix outlives this bout.

    Two things are still the table's own, because only the table has them: the
    RULES this mat runs (settings), and a FACE for whoever is standing in a
    corner right now (the cropper).

    It holds no truth. Every button posts an intention, the server applies the
    rules, and the reply is the new state — byte-identical to what the hall
    screens receive over MQTT, so two officials cannot disagree. The rules the
    settings panel sets live in the MAT STATE for the same reason: a rule kept
    in one laptop's text box is a rule the other console never had.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="utf-8">
    {{-- Dark by design (a scoreboard console / wall board). Declared so a
         browser's auto-dark and Dark Reader both leave it alone — the same
         reasoning as the light pages, opposite value. --}}
    <meta name="color-scheme" content="dark">
    <meta name="darkreader-lock">
    <style>html { color-scheme: dark; }</style>

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
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ __('scoreboard::karate_messages.ctl_title') }} · {{ $event->title }}</title>

<style>
@php
    $faces = [
        ['Anton', 400, 'anton-400'],
        ['Barlow Condensed', 400, 'barlow-condensed-400'],
        ['Barlow Condensed', 600, 'barlow-condensed-600'],
        ['Barlow Condensed', 700, 'barlow-condensed-700'],
    ];
    $ranges = [
        'latin' => 'U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD',
        'latin-ext' => 'U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF',
    ];
@endphp
@foreach ($faces as [$family, $weight, $slug])
@foreach ($ranges as $subset => $range)
@font-face { font-family: '{{ $family }}'; font-style: normal; font-weight: {{ $weight }}; font-display: swap;
  src: url("{{ route('karate-court-display.font', $slug.'-'.$subset.'.woff2', false) }}") format('woff2');
  unicode-range: {{ $range }}; }
@endforeach
@endforeach

  /* ── The handoff's tokens, named once ──────────────────────────────────
     These are the SAME values the script writes when it recolours a live
     control, so a variable and a scripted value cannot disagree. */
  :root{
    --ink:#0a0b10;      /* the stage, and every sunken plate on it */
    --panel:#111320;    /* a card                                  */
    --inset:#0e1017;    /* a row inside a card                     */
    --fill:#1f2230;     /* a control                               */
    --fill-alt:#12141d; /* an outlined control's ground            */
    --line:#1f2230;
    --line-2:#2a2e40;
    --text:#e8eaf2;
    --muted:#7d8296;
    --faint:#5c6175;
    --second:#a7abbe;
    --aka:#b3121f;
    --aka-ink:#ff6b78;
    --alarm:#ff3b47;
    --ao:#0d55b8;
    --ao-ink:#8ab4ff;
    --ao-alt:#6ea8ff;
    --ao-plate:#2f7bdb;
    --gold:#ffe135;     /* always solid, never transparent          */
    --green:#7ae582;
  }

  html,body{margin:0;padding:0;background:var(--ink);overflow:hidden;}
  *{box-sizing:border-box;}
  a{color:var(--ao-ink);} a:hover{color:var(--gold);}
  input,select,button,textarea{font-family:'Barlow Condensed',sans-serif;}
  [hidden]{display:none !important;}

  /* The console is tabbed through as often as it is touched; a control you
     cannot see the focus on is a control that gets pressed by mistake. */
  button:focus-visible,a:focus-visible,input:focus-visible,label:focus-within{
    outline:2px solid var(--gold);outline-offset:2px;}
  input:focus,select:focus{outline:2px solid #ffe13555;}

  /* Native spinners off — the mm:ss boxes are typed, not nudged. */
  input[type=number]::-webkit-outer-spin-button,
  input[type=number]::-webkit-inner-spin-button{-webkit-appearance:none;margin:0;}
  input[type=number]{-moz-appearance:textfield;appearance:textfield;}

  /* Scrollbars, everywhere: a list at a mat is dragged with a finger and the
     browser's default bar is invisible on this ground. */
  *{scrollbar-width:thin;scrollbar-color:var(--line-2) var(--ink);}
  ::-webkit-scrollbar{width:10px;height:10px;}
  ::-webkit-scrollbar-track{background:var(--ink);border-radius:8px;}
  ::-webkit-scrollbar-thumb{background:var(--line-2);border-radius:8px;border:2px solid var(--ink);}
  ::-webkit-scrollbar-thumb:hover{background:var(--gold);}
  ::-webkit-scrollbar-corner{background:var(--ink);}

  @keyframes ambient{0%,100%{background-position:0% 50%}50%{background-position:100% 50%}}
  @keyframes orbA{0%,100%{transform:translate(0,0)}50%{transform:translate(70px,-40px)}}
  @keyframes orbB{0%,100%{transform:translate(0,0)}50%{transform:translate(-60px,50px)}}
  @keyframes cardIn{0%{opacity:0;transform:translateY(18px)}100%{opacity:1;transform:translateY(0)}}
  /* TWO identical keyframes on purpose: re-assigning the same animation-name
     does not replay it, so the score alternates between these on every change.
     Re-keying the node would lose the element the rest of the console holds. */
  @keyframes popA{0%{transform:scale(1.4);color:var(--gold)}100%{transform:scale(1)}}
  @keyframes popB{0%{transform:scale(1.4);color:var(--gold)}100%{transform:scale(1)}}
  @keyframes atoshiFlash{0%,100%{color:var(--alarm)}50%{color:var(--text)}}
  @keyframes timerGlow{0%,100%{text-shadow:0 0 24px rgba(232,234,242,.15)}50%{text-shadow:0 0 44px rgba(232,234,242,.4)}}
  @keyframes runGlow{0%,100%{box-shadow:0 8px 0 -2px rgba(0,0,0,.5),0 0 30px -6px rgba(255,107,120,.55)}
                     50%{box-shadow:0 8px 0 -2px rgba(0,0,0,.5),0 0 60px -6px rgba(255,107,120,.95)}}
  @keyframes senshuGlow{0%,100%{box-shadow:0 0 12px -2px rgba(255,225,53,.5)}50%{box-shadow:0 0 28px -2px rgba(255,225,53,.95)}}
  @keyframes winIn{0%{opacity:0;transform:scale(.92)}100%{opacity:1;transform:scale(1)}}
  @keyframes chipPop{0%{transform:scale(.4) rotate(-6deg);opacity:0}60%{transform:scale(1.15) rotate(2deg)}100%{transform:scale(1) rotate(0);opacity:1}}
  /* The translate is carried through every step on purpose: a keyframe that
     animates `transform` REPLACES the element's own translate(-50%,-50%), and
     with fill-mode `both` the stamp would keep scale(1) and lose its centring
     for good — it hangs off the middle of the screen. Same flaw in the source. */
  @keyframes stampIn{0%{transform:translate(-50%,-50%) scale(2.6);opacity:0;filter:blur(10px);}45%{transform:translate(-50%,-50%) scale(.96);opacity:1;filter:blur(0);}60%{transform:translate(-50%,-50%) scale(1.04);}100%{transform:translate(-50%,-50%) scale(1);opacity:1;}}
  @keyframes confettiFall{0%{transform:translateY(-90px) rotate(0deg);opacity:1;}100%{transform:translateY(1200px) rotate(760deg);opacity:.75;}}

  /* The console is authored at 1920 wide and only SCALES — same rule as the
     wall screens, so a 13" laptop at the mat gets the whole layout.
     Its HEIGHT is whatever the layout needs (min 1080), not a hard 1080: the
     stage clips, so anything past 1080 would simply be gone. fit() measures
     what the layout actually came out to and scales that, so the console
     cannot outgrow its own glass. */
  #root{position:absolute;inset:0;overflow:hidden;background:var(--ink);}
  #stage{position:absolute;left:50%;top:50%;width:1920px;min-height:1080px;
         transform:translate(-50%,-50%) scale(var(--stage-scale,0.6));transform-origin:center;}

  /* ── The vocabulary the layout is built from ─────────────────────────── */
  .cap{font-size:19px;letter-spacing:.18em;text-transform:uppercase;color:var(--muted);}
  .val{font-size:34px;font-weight:600;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
  .card{background:var(--panel);border:1px solid var(--line);border-radius:18px;}

  /* A scoring button. `flex:1` in the column, so the four of them divide
     whatever height the panel has rather than sitting at a fixed size. */
  .sbtn{flex:1;font-size:32px;font-weight:700;color:#fff;border:none;border-radius:12px;padding:16px 0;
        cursor:pointer;transition:transform .08s,filter .08s;}
  .sbtn:active:not(:disabled){transform:scale(.96);filter:brightness(1.25);}

  /* One of the five penalty cells: the cell IS the button. */
  .pcell{flex:1 1 0;height:76px;border-radius:12px;display:flex;align-items:center;justify-content:center;
         font-family:'Anton',sans-serif;font-size:30px;cursor:pointer;padding:0;
         transition:transform .08s,background .2s,color .2s;}
  .pcell:active:not(:disabled){transform:scale(.94);}

  /* The six controls under the clock — same box, colour from each button. */
  .cbtn{min-height:76px;font-size:30px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;
        background:var(--fill-alt);border-radius:12px;padding:16px 0;cursor:pointer;
        display:flex;align-items:center;justify-content:center;transition:transform .08s,filter .15s;}
  .cbtn:active:not(:disabled){transform:scale(.97);filter:brightness(1.2);}

  /* Nothing that acts on a bout is offered while the mat is empty — pressing
     Hajime with nothing loaded used to put a blank scoreboard on the wall. */
  .bout[disabled]{opacity:.35;cursor:not-allowed;filter:none;}

  /* Every pop-up on this console: one scrim, one card, one head. */
  .scrim{position:fixed;inset:0;background:rgba(0,0,0,.6);display:flex;align-items:center;justify-content:center;padding:30px;}
  .modal{background:var(--panel);border:1px solid var(--line-2);border-radius:20px;padding:28px 30px;
         display:flex;flex-direction:column;gap:18px;max-height:94%;max-width:calc(100vw - 60px);
         animation:winIn .25s cubic-bezier(.2,.8,.2,1) both;}
  /* A panel inside the card is a flex ITEM, and a flex item's floor is its
     content unless it is told otherwise. Without this, anything with a long
     unbreakable string in it stretches the card rather than scrolling or
     eliding inside it. */
  .modal > *{min-width:0;}
  .mhead{display:flex;align-items:center;justify-content:space-between;gap:20px;}
  .mtitle{font-size:28px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;}
  .mclose{font-size:24px;background:none;border:none;color:var(--muted);cursor:pointer;padding:6px 10px;}
  .mclose:hover{color:var(--text);}

  .fld{font-size:20px;background:var(--inset);border:1px solid var(--line);border-radius:10px;
       color:var(--text);padding:12px 14px;width:100%;min-width:0;}
  /* A read-only display box: the same shape as a field, so the panel reads as
     one thing — but it is a <div>, because nothing here is editable. */
  .ro{background:var(--ink);border:1px solid var(--line);border-radius:10px;color:var(--text);padding:12px 14px;
      white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
  .mm{flex:1;min-width:0;font-family:'Anton',sans-serif;font-size:28px;text-align:center;background:var(--fill);
      color:var(--text);border:none;border-radius:12px;padding:10px 8px;}
</style>

{{-- The winner celebration — this package's own. The same scene the wall
     shows, with this console's own two controls dropped into its one slot. --}}
@include('scoreboard::karate.scoreboard.winner-celebration')
</head>
<body>

<div id="root"><div id="stage" style="background:linear-gradient(120deg,#0a0b10 0%,#10121c 30%,#0a0b10 55%,#12101a 80%,#0a0b10 100%);background-size:300% 300%;animation:ambient 18s ease-in-out infinite;font-family:'Barlow Condensed',sans-serif;color:var(--text);display:flex;flex-direction:column;gap:22px;padding:26px 36px 22px;overflow:hidden;position:relative;">

  <div style="position:absolute;left:-120px;top:240px;width:540px;height:540px;border-radius:50%;background:radial-gradient(circle,rgba(179,18,31,.13),transparent 70%);filter:blur(30px);animation:orbA 16s ease-in-out infinite;pointer-events:none;"></div>
  <div style="position:absolute;right:-120px;top:180px;width:560px;height:560px;border-radius:50%;background:radial-gradient(circle,rgba(13,85,184,.15),transparent 70%);filter:blur(30px);animation:orbB 19s ease-in-out infinite;pointer-events:none;"></div>

  {{-- ── Top bar ──────────────────────────────────────────────────────────
       Read-only. These five come from the draw, and the draw is where they are
       corrected — a name fixed here would be right for one bout and wrong
       everywhere else the entry appears. --}}
  <div style="display:flex;align-items:flex-end;gap:32px;">
    <div style="display:flex;flex-direction:column;gap:2px;flex-shrink:0;">
      <div style="font-family:'Anton',sans-serif;font-size:34px;letter-spacing:.04em;text-transform:uppercase;">{{ __('scoreboard::karate_messages.ctl_heading') }}</div>
      <div style="font-size:18px;color:var(--muted);letter-spacing:.18em;text-transform:uppercase;">{{ __('scoreboard::karate_messages.ctl_subtitle') }}</div>
    </div>
    <div style="flex:1;min-width:0;display:flex;justify-content:flex-end;align-items:flex-end;gap:40px;">
      {{-- The third value is a WIDTH CAP, and `null` means "as wide as what is
           in it". The tournament's name is uncapped on purpose (2026-09-05):
           it is the one field here that names the whole day, it is read once
           at a glance rather than scanned, and a title clipped to
           "Karate National Team Selection Tri…" tells an official less than
           nothing. It still shrinks when the row runs out of room — the cells
           are ordinary flex items — it just no longer elides while there is
           space beside it. --}}
      @foreach ([
          ['mTournament', __('scoreboard::karate_messages.ctl_tournament'), null],
          ['mCategory', __('scoreboard::karate_messages.ctl_category'), '300px'],
          ['mMatchNo', __('scoreboard::karate_messages.ctl_match'), null],
          ['mCourt', __('scoreboard::karate_messages.ctl_tatami'), null],
          ['mRound', __('scoreboard::karate_messages.ctl_round'), '260px'],
      ] as [$id, $label, $cap])
        <div style="display:flex;flex-direction:column;gap:6px;min-width:0;">
          <span class="cap">{{ $label }}</span>
          <div id="{{ $id }}" class="val"@if ($cap) style="max-width:{{ $cap }};"@endif></div>
        </div>
      @endforeach
    </div>
  </div>

  {{-- ── AKA | centre | AO ───────────────────────────────────────────────
       The AO panel is a full mirror of AKA: every horizontal row reverses and
       its labels sit right. The two corners face each other, as they do on the
       wall — so the half of the console an official reaches for is the half of
       the mat they are looking at. --}}
  <div style="flex:1;display:grid;grid-template-columns:1fr 500px 1fr;gap:22px;min-height:0;">

    @foreach ([
        ['aka', 'AKA', '#b3121f', '#b3121f', '1', '.05s', false, '100deg'],
        ['ao', 'AO', '#0d55b8', '#2f7bdb', '3', '.3s', true, '260deg'],
    ] as [$side, $label, $colour, $plateInk, $order, $delay, $mirror, $angle])
    @php $rev = $mirror ? 'flex-direction:row-reverse;' : ''; @endphp
    <div class="card" style="border-top:8px solid {{ $colour }};padding:24px 32px;display:flex;flex-direction:column;gap:18px;order:{{ $order }};animation:cardIn .6s {{ $delay }} cubic-bezier(.2,.8,.2,1) both;">

      {{-- The score plate: the corner's colour bleeds under its own label, so
           the number is read against the side it belongs to. --}}
      <div style="{{ $rev }}display:flex;align-items:center;justify-content:space-between;gap:18px;padding:10px 22px 10px 24px;border-radius:12px;background:linear-gradient({{ $angle }},{{ $colour }}22,#0a0b10 55%);">
        <div style="font-size:30px;font-weight:700;letter-spacing:.28em;color:{{ $plateInk }};text-transform:uppercase;text-shadow:0 0 26px {{ $colour }}66;">{{ $label }}</div>
        <div id="score-{{ $side }}" style="font-family:'Anton',sans-serif;font-size:104px;line-height:1;font-variant-numeric:tabular-nums;text-shadow:0 4px 30px rgba(0,0,0,.8);">0</div>
      </div>

      <div style="flex:1;display:flex;flex-direction:column;gap:12px;">
        <button class="sbtn bout" style="background:{{ $colour }};" data-cmd="point" data-side="{{ $side }}" data-n="1">Yuko +1</button>
        <button class="sbtn bout" style="background:{{ $colour }};" data-cmd="point" data-side="{{ $side }}" data-n="2">Waza-ari +2</button>
        <button class="sbtn bout" style="background:{{ $colour }};" data-cmd="point" data-side="{{ $side }}" data-n="3">Ippon +3</button>
        <button class="sbtn bout" style="background:var(--fill);color:var(--text);font-size:30px;" data-cmd="undo_point" data-side="{{ $side }}" data-n="1">Correct −1</button>
      </div>

      {{-- Five cells, and the cell IS the button: pressing C3 gives C3, rather
           than pressing + three times in front of a hall. Pressing the one
           that is already the top of the ladder steps back off it. --}}
      <div style="{{ $rev }}display:flex;align-items:center;gap:16px;">
        <div style="font-size:19px;letter-spacing:.2em;color:var(--muted);text-transform:uppercase;width:104px;{{ $mirror ? 'text-align:right;' : '' }}">{{ __('scoreboard::karate_messages.ctl_penalty') }}</div>
        <div id="pen-{{ $side }}" style="{{ $rev }}display:flex;gap:10px;flex:1;"></div>
      </div>

      <div style="{{ $rev }}display:flex;align-items:center;gap:16px;">
        <div style="font-size:19px;letter-spacing:.2em;color:var(--muted);text-transform:uppercase;width:104px;{{ $mirror ? 'text-align:right;' : '' }}">{{ __('scoreboard::karate_messages.ctl_senshu') }}</div>
        <button id="senshu-{{ $side }}" class="bout" style="flex:1;font-size:24px;font-weight:700;letter-spacing:.16em;text-transform:uppercase;border-radius:12px;height:58px;cursor:pointer;transition:background .3s,color .3s,border-color .3s;" data-cmd="senshu" data-side="{{ $side }}">{{ __('scoreboard::karate_messages.ctl_senshu') }}</button>
      </div>

      {{-- The identity strip. It says who is in this corner and opens the one
           thing about them the table can change: their photograph. --}}
      <button data-participant="{{ $side }}" style="{{ $rev }}display:flex;align-items:center;gap:14px;background:var(--ink);border:1px solid var(--line);border-radius:12px;padding:10px 16px;cursor:pointer;text-align:{{ $mirror ? 'right' : 'left' }};">
        <span id="thumb-{{ $side }}" style="width:42px;height:56px;flex:0 0 auto;border-radius:8px;border:1px solid var(--line);background-color:var(--fill-alt);background-size:cover;background-position:center 15%;display:flex;align-items:center;justify-content:center;color:var(--gold);font-size:18px;overflow:hidden;">◎</span>
        <span id="flag-{{ $side }}" style="width:36px;height:24px;flex:0 0 auto;background-size:100% 100%;background-position:center;border:1px solid rgba(255,255,255,.25);border-radius:4px;"></span>
        <span style="flex:1;min-width:0;display:flex;flex-direction:column;gap:2px;">
          <span id="name-{{ $side }}" style="font-size:24px;font-weight:600;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"></span>
          <span id="meta-{{ $side }}" style="font-size:16px;color:var(--muted);letter-spacing:.04em;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"></span>
        </span>
        <span style="color:var(--muted);font-size:17px;letter-spacing:.1em;">{{ $mirror ? '▾ ' : '' }}{{ __('scoreboard::karate_messages.ctl_view') }}{{ $mirror ? '' : ' ▾' }}</span>
      </button>
    </div>
    @endforeach

    {{-- ── Centre column ─────────────────────────────────────────────────── --}}
    <div class="card" style="padding:30px 30px 26px;display:flex;flex-direction:column;align-items:center;gap:22px;order:2;animation:cardIn .6s .18s cubic-bezier(.2,.8,.2,1) both;">

      {{-- What this mat does between bouts, and the biggest thing on the panel
           after the clock: an official who has just filed a result is looking
           for the next bout, not for a menu. --}}
      {{-- Sized as one block with Hajime below it: same flex share, same
           min-height, same padding — and the same 8px plinth, which is the
           half of it that was missing. The shadow is not decoration here, it
           is 6px of drawn height, and without it this button read as the
           shorter of the two even though the boxes measured the same. --}}
      <button id="btnBouts" style="width:100%;flex:1.2;min-height:96px;font-size:44px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;background:var(--gold);color:#000;border:none;border-radius:16px;padding:26px 0;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:22px;box-shadow:0 8px 0 -2px rgba(0,0,0,.5);transition:transform .08s;">
        <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 6 L12 13 L19 6"></path><path d="M5 12 L12 19 L19 12"></path></svg>
        {{ __('scoreboard::karate_messages.ctl_bouts') }}
        <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 6 L12 13 L19 6"></path><path d="M5 12 L12 19 L19 12"></path></svg>
      </button>

      <div style="width:100%;display:flex;flex-direction:column;align-items:center;gap:6px;padding:16px 20px 18px;border-radius:12px;background:var(--ink);">
        <div style="font-size:19px;letter-spacing:.32em;color:var(--muted);text-transform:uppercase;">{{ __('scoreboard::karate_messages.ctl_bout_timer') }}</div>
        <div id="timer" style="font-family:'Anton',sans-serif;font-size:132px;line-height:1.06;font-variant-numeric:tabular-nums;color:var(--text);transition:color .3s;">3:00</div>
        <div id="status" style="font-size:32px;font-weight:700;letter-spacing:.28em;text-transform:uppercase;color:var(--gold);">{{ __('scoreboard::karate_messages.ctl_pause') }}</div>
      </div>

      <button id="btnStart" class="bout" style="width:100%;flex:1.2;min-height:96px;font-size:44px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#000;border:none;border-radius:16px;padding:26px 0;cursor:pointer;box-shadow:0 8px 0 -2px rgba(0,0,0,.5);transition:transform .08s;">{{ __('scoreboard::karate_messages.ctl_start') }}</button>

      <div style="width:100%;display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <button id="btnBoard" class="cbtn bout" style="color:var(--ao-ink);border:1px solid rgba(138,180,255,.45);"></button>
        <button id="btnKo" class="cbtn bout" style="color:var(--alarm);border:1px solid rgba(255,59,71,.5);">{{ __('scoreboard::karate_messages.ctl_finish') }}</button>
        {{-- A panel the event's own PACKAGE contributes (AbstractEventType::matPanel).
             Absent for every championship, so this row is unchanged for them. An
             open mat puts "next pair" here so its operator never leaves the table. --}}
        @isset($matPanel)
            <button id="btnMatPanel" class="cbtn" style="color:#F97316;border:1px solid rgba(249,115,22,.6);">{{ $matPanel['label'] }}</button>
        @endisset
        <button id="btnSettings" class="cbtn" style="color:var(--gold);border:1px solid var(--gold);">{{ __('scoreboard::karate_messages.ctl_settings') }}</button>
        <button id="btnHardware" class="cbtn" style="color:var(--text);border:1px solid var(--line-2);">{{ __('scoreboard::karate_messages.ctl_hardware') }}</button>
        <button id="btnReset" class="cbtn bout" style="background:transparent;color:var(--aka-ink);border:1px solid rgba(255,107,120,.5);">{{ __('scoreboard::karate_messages.ctl_reset') }}</button>
        {{-- Deliberately NOT disabled with the rest of the bout controls: a hung
             screen is most likely to need this when the mat is empty, and a
             button that greys out exactly when you reach for it is worse than
             no button at all. --}}
        <button id="btnResync" class="cbtn" style="color:var(--ao-ink);border:1px solid rgba(138,180,255,.45);">{{ __('scoreboard::karate_messages.ctl_resync') }}</button>
      </div>

      <div style="font-size:16px;color:var(--faint);letter-spacing:.06em;text-align:center;">{{ __('scoreboard::karate_messages.ctl_resync_hint') }}</div>
    </div>
  </div>

  {{-- ── Winner overlay ─────────────────────────────────────────────────── --}}
  <div id="winner" hidden style="position:absolute;inset:0;z-index:30;background:rgba(0,0,0,.45);overflow:hidden;"></div>

  {{-- ── Bouts: this event's running order for this mat ─────────────────── --}}
  <div id="bouts" hidden class="scrim" style="z-index:20;">
    <div class="modal" style="width:1100px;height:900px;gap:16px;">
      <div class="mhead">
        <div class="mtitle" style="font-size:26px;color:var(--green);">{{ __('scoreboard::karate_messages.ctl_bouts') }}</div>
        <button data-close="bouts" class="mclose">✕</button>
      </div>
      <div id="boutsList" style="flex:1;overflow-y:auto;display:flex;flex-direction:column;gap:8px;min-height:160px;padding-right:14px;"></div>
      <div style="font-size:20px;color:var(--faint);">{{ __('scoreboard::karate_messages.ctl_bouts_hint') }}</div>
    </div>
  </div>


  {{-- ── A package's own panel, if this event type contributes one ────── --}}
  @isset($matPanel)
      @include($matPanel['view'], $matPanel['data'])
  @endisset

  {{-- ── Settings: the rules this mat is running ──────────────────────────
       Not a preference panel. Everything here is posted to the mat state, so a
       second console on the same mat and the wall are all running the bout the
       official at this table said it was. --}}
  <div id="settings" hidden class="scrim" style="z-index:21;">
    <div class="modal" style="width:1020px;gap:22px;overflow-y:auto;">
      <div class="mhead">
        <div class="mtitle" style="color:var(--gold);">{{ __('scoreboard::karate_messages.ctl_settings_title') }}</div>
        <button data-close="settings" class="mclose">✕</button>
      </div>

      <div style="display:flex;gap:8px;border-bottom:1px solid var(--line);padding-bottom:14px;">
        {{-- The camera tab only exists when this door was given the camera
             addresses — a console with no camera surface shows no camera tab,
             the same way a type with no wall boards shows no screen panel. --}}
        @foreach (array_merge([['rules', __('scoreboard::karate_messages.ctl_tab_rules')], ['timer', __('scoreboard::karate_messages.ctl_tab_timer')], ['sounds', __('scoreboard::karate_messages.ctl_tab_sounds')]], ($cameraUrl ?? null) ? [['cameras', __('events.mat_cameras_tab')]] : []) as [$tab, $tabLabel])
          <button class="stab" data-tab="{{ $tab }}" style="flex:1;font-size:20px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;border-radius:10px;padding:12px 0;cursor:pointer;">{{ $tabLabel }}</button>
        @endforeach
      </div>

      {{-- Rules --}}
      <div class="spanel" data-panel="rules" style="display:flex;flex-direction:column;gap:12px;">
        @foreach ([
            ['senshuRule', __('scoreboard::karate_messages.ctl_rule_senshu'), __('scoreboard::karate_messages.ctl_rule_senshu_hint')],
            ['autoSenshu', __('scoreboard::karate_messages.ctl_rule_auto_senshu'), __('scoreboard::karate_messages.ctl_rule_auto_senshu_hint')],
            ['winByPenalties', __('scoreboard::karate_messages.ctl_rule_penalties'), __('scoreboard::karate_messages.ctl_rule_penalties_hint')],
            ['atoshiWarn', __('scoreboard::karate_messages.ctl_rule_atoshi'), __('scoreboard::karate_messages.ctl_rule_atoshi_hint')],
            ['timeUpBuzzer', __('scoreboard::karate_messages.ctl_rule_buzzer'), __('scoreboard::karate_messages.ctl_rule_buzzer_hint')],
        ] as [$rule, $ruleLabel, $ruleHint])
          <button class="rrow" data-rule="{{ $rule }}" style="display:flex;align-items:center;gap:14px;background:var(--inset);border:1px solid var(--line);border-radius:12px;padding:12px 16px;cursor:pointer;text-align:left;">
            <span class="rbox" style="width:30px;height:30px;flex:0 0 auto;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:20px;font-weight:700;color:#000;"></span>
            <span style="flex:1;display:flex;flex-direction:column;gap:2px;">
              <span style="font-size:20px;font-weight:600;color:var(--text);letter-spacing:.04em;">{{ $ruleLabel }}</span>
              <span style="font-size:15px;color:var(--faint);letter-spacing:.02em;">{{ $ruleHint }}</span>
            </span>
          </button>
        @endforeach

        {{-- The gap rule carries its own number, so the switch and the value it
             switches on are one row rather than two things to find. --}}
        <div class="rrow" style="display:flex;align-items:center;gap:14px;background:var(--inset);border:1px solid var(--line);border-radius:12px;padding:12px 16px;">
          <button class="rbox" data-rule="gapOn" style="width:30px;height:30px;flex:0 0 auto;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:20px;font-weight:700;color:#000;cursor:pointer;padding:0;"></button>
          <span style="flex:1;display:flex;flex-direction:column;gap:2px;">
            <span style="font-size:20px;font-weight:600;color:var(--text);letter-spacing:.04em;">{{ __('scoreboard::karate_messages.ctl_rule_gap') }}</span>
            <span style="font-size:15px;color:var(--faint);letter-spacing:.02em;">{{ __('scoreboard::karate_messages.ctl_rule_gap_hint') }}</span>
          </span>
          <input id="gapVal" type="number" min="1" max="20" value="8" style="width:80px;font-family:'Anton',sans-serif;font-size:26px;text-align:center;background:var(--fill);color:var(--gold);border:none;border-radius:10px;padding:8px 6px;">
        </div>
      </div>

      {{-- Timer --}}
      <div class="spanel" data-panel="timer" hidden style="display:flex;flex-direction:column;gap:20px;">
        <label style="display:flex;flex-direction:column;gap:8px;">
          <span style="font-size:17px;letter-spacing:.18em;text-transform:uppercase;color:var(--muted);">{{ __('scoreboard::karate_messages.ctl_duration') }}</span>
          <span style="display:flex;gap:12px;align-items:center;">
            <input id="durMin" type="number" min="0" max="59" value="3" class="mm">
            <span style="font-family:'Anton',sans-serif;font-size:28px;color:var(--muted);">:</span>
            <input id="durSec" type="number" min="0" max="59" value="0" class="mm">
          </span>
        </label>
        <label style="display:flex;flex-direction:column;gap:8px;">
          <span style="font-size:17px;letter-spacing:.18em;text-transform:uppercase;color:var(--muted);">{{ __('scoreboard::karate_messages.ctl_warning_at') }}</span>
          <span style="display:flex;gap:12px;align-items:center;">
            <input id="atoMin" type="number" min="0" max="59" value="0" class="mm" style="color:var(--aka-ink);">
            <span style="font-family:'Anton',sans-serif;font-size:28px;color:var(--muted);">:</span>
            <input id="atoSec" type="number" min="0" max="59" value="15" class="mm" style="color:var(--aka-ink);">
          </span>
          <span style="font-size:14px;color:var(--faint);letter-spacing:.04em;">{{ __('scoreboard::karate_messages.ctl_warning_hint') }}</span>
        </label>
      </div>

      {{-- Sounds. Uploading replaces: a hall does not want two celebration
           tracks, it wants the right one. Every screen on the mat is told to
           reload afterwards, because a board caches the file it fetched. --}}
      {{-- ⚠️ `minmax(0,1fr)`, never a bare `1fr`: a grid track's floor is its
           MIN-CONTENT, and an uploaded filename is one unbreakable token on a
           `nowrap` line — so a real upload ("A_deep,_heavy_Japane_#1-1788…mp3")
           widened its column and pushed this panel straight out through the
           side of the card. The zero floor is what lets the ellipsis on
           `.audioName` actually do its job. --}}
      <div class="spanel" data-panel="sounds" hidden style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:4px 28px;min-width:0;">
        @foreach ([
            'vs_music' => __('events.screen_audio_vs_music'),
            'match_start' => __('events.screen_audio_match_start'),
            'match_end' => __('events.screen_audio_match_end'),
            'winner_music' => __('events.screen_audio_winner_music'),
            'point_1' => __('events.screen_audio_point_1'),
            'point_2' => __('events.screen_audio_point_2'),
            'point_3' => __('events.screen_audio_point_3'),
            'foul' => __('events.screen_audio_foul'),
            'time_up' => __('events.screen_audio_time_up'),
            'atoshi' => __('events.screen_audio_atoshi'),
        ] as $slot => $slotLabel)
          <div style="display:flex;align-items:center;gap:14px;padding:10px 0;min-width:0;">
            <div style="flex:1;min-width:0;">
              <div style="font-size:23px;font-weight:600;color:var(--text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $slotLabel }}</div>
              <div class="audioName" data-slot="{{ $slot }}" style="font-size:17px;margin-top:2px;color:var(--faint);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ __('events.screen_audio_none') }}</div>
            </div>
            <label style="font-size:17px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;background:var(--green);color:#000;border-radius:10px;padding:11px 16px;cursor:pointer;white-space:nowrap;">
              {{ __('events.screen_audio_choose') }}
              <input type="file" accept="audio/*" class="audioPick" data-slot="{{ $slot }}" style="display:none;">
            </label>
            <button class="audioDrop" data-slot="{{ $slot }}" hidden style="font-size:18px;font-weight:700;background:var(--fill);color:var(--aka-ink);border:1px solid rgba(255,107,120,.45);border-radius:10px;padding:12px 16px;cursor:pointer;">✕</button>
          </div>
        @endforeach
      </div>

      {{-- The cameras on this mat: telemetry, how they film, and what they
           have filmed. Sport-neutral and shared with every other console —
           see resources/views/partials/mat-cameras.blade.php. --}}
      @if ($cameraUrl ?? null)
        <div class="spanel" data-panel="cameras" hidden>
          @include('partials.mat-cameras', ['cameraUrl' => $cameraUrl, 'cameraCommandBase' => $cameraCommandBase])
        </div>
      @endif

      <button id="btnApply" style="font-size:24px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;background:var(--gold);color:#000;border:none;border-radius:14px;padding:16px 0;cursor:pointer;">{{ __('scoreboard::karate_messages.ctl_apply') }}</button>
      <div style="font-size:16px;color:var(--faint);letter-spacing:.04em;text-align:center;">{{ __('scoreboard::karate_messages.ctl_apply_hint') }}</div>
    </div>
  </div>

  {{-- ── Participant ──────────────────────────────────────────────────────
       Everything here is read-only except the photograph. The name, club and
       country are the entry's, and the entry is where they are corrected. --}}
  <div id="participant" hidden class="scrim" style="z-index:19;">
    <div class="modal" style="width:640px;gap:20px;">
      <div class="mhead" style="border-bottom:1px solid var(--line);padding-bottom:14px;">
        <div style="display:flex;align-items:center;gap:14px;">
          <span id="pBar" style="width:14px;height:36px;border-radius:4px;"></span>
          <div id="pTitle" class="mtitle" style="letter-spacing:.14em;"></div>
        </div>
        <button data-close="participant" class="mclose">✕</button>
      </div>

      <div style="display:flex;gap:24px;align-items:stretch;">
        <button id="pPhoto" style="flex:0 0 auto;align-self:center;width:210px;height:280px;border-radius:14px;background-color:var(--ink);background-size:cover;background-position:center 15%;cursor:pointer;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;color:var(--faint);font-size:15px;letter-spacing:.08em;text-transform:uppercase;padding:0;overflow:hidden;">
          <span id="pPhotoIcon" style="font-size:34px;color:var(--gold);">◎</span>
          <span id="pPhotoLabel">{{ __('scoreboard::karate_messages.ctl_photo_label') }}</span>
        </button>
        <div style="flex:1;min-width:0;display:flex;flex-direction:column;gap:14px;">
          <div style="display:flex;flex-direction:column;gap:5px;">
            <span style="font-size:15px;letter-spacing:.18em;text-transform:uppercase;color:var(--muted);">{{ __('scoreboard::karate_messages.ctl_name') }}</span>
            <div id="pName" class="ro" style="font-size:26px;font-weight:600;"></div>
          </div>
          <div style="display:flex;flex-direction:column;gap:5px;">
            <span style="font-size:15px;letter-spacing:.18em;text-transform:uppercase;color:var(--muted);">{{ __('scoreboard::karate_messages.ctl_club') }}</span>
            <div id="pClub" class="ro" style="font-size:24px;"></div>
          </div>
          <div style="display:flex;flex-direction:column;gap:5px;">
            <span style="font-size:15px;letter-spacing:.18em;text-transform:uppercase;color:var(--muted);">{{ __('scoreboard::karate_messages.ctl_country_from_club') }}</span>
            <div class="ro" style="display:flex;align-items:center;gap:14px;font-size:24px;padding:11px 14px;">
              <span id="pFlag" style="width:42px;height:28px;background-size:100% 100%;background-position:center;border:1px solid rgba(255,255,255,.25);border-radius:4px;flex-shrink:0;"></span>
              <span id="pCountry" style="flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;"></span>
            </div>
          </div>
          <div style="font-size:16px;color:var(--faint);letter-spacing:.04em;">{{ __('scoreboard::karate_messages.ctl_participant_note') }}</div>
        </div>
      </div>

      <button data-close="participant" style="font-size:24px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;background:var(--green);color:#000;border:none;border-radius:14px;padding:16px 0;cursor:pointer;">{{ __('scoreboard::karate_messages.ctl_done') }}</button>
    </div>
  </div>

  {{-- ── The cropper ──────────────────────────────────────────────────────
       A picture taken at the desk is a phone snap: a person somewhere in a
       landscape frame with half a hall behind them. The screens draw a
       PORTRAIT, three wide to four tall, so something has to decide which part
       of that snap is the competitor — and the only person who can decide it is
       the official looking at both the photo and the athlete.

       What leaves the browser is always exactly 600x800, the shape every screen
       draws, so no board ever letterboxes or squashes it. --}}
  <div id="crop" hidden class="scrim" style="z-index:25;background:rgba(0,0,0,.7);">
    <div class="modal" style="width:520px;padding:26px 28px;">
      <div class="mhead">
        <div id="cropTitle" class="mtitle" style="font-size:26px;"></div>
        <button data-close="crop" class="mclose">✕</button>
      </div>

      <div id="cropView" style="width:330px;height:440px;margin:0 auto;overflow:hidden;position:relative;background:var(--ink);border:1px solid var(--line-2);border-radius:10px;cursor:grab;touch-action:none;">
        <div id="cropImg" hidden style="position:absolute;left:0;top:0;background-repeat:no-repeat;transform-origin:center center;"></div>
        <div id="cropFrame" hidden style="position:absolute;inset:0;pointer-events:none;border:2px solid var(--gold);border-radius:10px;"></div>
        <div id="cropEmpty" style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;color:var(--faint);font-size:18px;letter-spacing:.08em;text-transform:uppercase;">
          <span style="font-size:40px;color:var(--gold);">◎</span>{{ __('scoreboard::karate_messages.ctl_crop_empty') }}
        </div>
      </div>

      <div style="display:flex;flex-direction:column;gap:10px;">
        <label style="display:flex;align-items:center;gap:14px;">
          <span style="width:70px;font-size:16px;letter-spacing:.14em;text-transform:uppercase;color:var(--muted);">{{ __('scoreboard::karate_messages.ctl_crop_zoom') }}</span>
          <input id="cropZoom" type="range" min="1" max="4" step="0.01" value="1" style="flex:1;accent-color:#ffe135;">
        </label>
        <label style="display:flex;align-items:center;gap:14px;">
          <span style="width:70px;font-size:16px;letter-spacing:.14em;text-transform:uppercase;color:var(--muted);">{{ __('scoreboard::karate_messages.ctl_crop_tilt') }}</span>
          <input id="cropTilt" type="range" min="-180" max="180" step="1" value="0" style="flex:1;accent-color:#ffe135;">
          <span id="cropTiltLabel" style="width:52px;font-family:'Anton',sans-serif;font-size:18px;color:var(--text);text-align:right;">0°</span>
        </label>
        <div style="font-size:15px;color:var(--faint);letter-spacing:.04em;text-align:center;">{{ __('scoreboard::karate_messages.ctl_crop_drag') }}</div>
      </div>

      <div style="display:flex;gap:12px;">
        <label style="flex:1;text-align:center;font-size:19px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;background:var(--fill);color:var(--ao-ink);border:1px solid rgba(138,180,255,.4);border-radius:12px;padding:14px 0;cursor:pointer;">
          {{ __('scoreboard::karate_messages.ctl_crop_upload') }}
          <input id="cropFile" type="file" accept="image/*" style="display:none;">
        </label>
        <button id="cropRemove" style="flex:1;font-size:19px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;background:var(--fill);color:var(--aka-ink);border:1px solid rgba(255,107,120,.45);border-radius:12px;padding:14px 0;cursor:pointer;">{{ __('scoreboard::karate_messages.ctl_crop_remove') }}</button>
        <button id="cropSave" disabled style="flex:1.4;font-size:19px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;background:var(--green);color:#000;border:none;border-radius:12px;padding:14px 0;cursor:pointer;opacity:.4;">{{ __('scoreboard::karate_messages.ctl_crop_save') }}</button>
      </div>
    </div>
  </div>

  {{-- ── Ending a bout: on points, or by decision ───────────────────────── --}}
  {{-- A bout does not always go to the higher score. Hansoku and shikkaku hand
       it to the other side however the points stand; kiken and a doctor's call
       hand it over with no points at all. So "End bout" asks, instead of
       assuming — and the manual path REQUIRES a reason, because "the athlete
       with fewer points won" is indistinguishable from a mistake without one. --}}
  <div id="endBout" hidden class="scrim" style="z-index:22;">
    <div class="modal" style="width:760px;gap:20px;">
      <div class="mhead">
        <div class="mtitle" style="color:var(--gold);">{{ __('scoreboard::karate_messages.end_title') }}</div>
        <button data-close="endBout" class="mclose">✕</button>
      </div>

      {{-- The default, and what it will do — named, so nobody has to remember
           who is ahead while looking at a dialog. --}}
      <button id="endAuto" style="display:flex;flex-direction:column;gap:6px;text-align:left;background:#0f1a12;border:1px solid rgba(122,229,130,.45);border-radius:14px;padding:18px 22px;cursor:pointer;">
        <span style="font-size:24px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--green);">{{ __('scoreboard::karate_messages.end_auto') }}</span>
        <span id="endAutoWho" style="font-size:19px;color:var(--second);"></span>
      </button>

      <div style="height:1px;background:var(--line-2);"></div>
      <div style="font-size:19px;font-weight:600;letter-spacing:.14em;text-transform:uppercase;color:var(--muted);">{{ __('scoreboard::karate_messages.end_manual') }}</div>

      {{-- Selection cards, not a dropdown: this is chosen once, under pressure,
           on a touch screen at the mat. --}}
      <div style="display:flex;gap:14px;">
        <button class="endSide" data-side="aka" style="flex:1;font-size:26px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;background:var(--fill);color:var(--aka-ink);border:2px solid transparent;border-radius:14px;padding:16px 0;cursor:pointer;">
          AKA <span class="endSideName" style="display:block;font-size:17px;font-weight:600;letter-spacing:.02em;text-transform:none;color:var(--second);"></span>
        </button>
        <button class="endSide" data-side="ao" style="flex:1;font-size:26px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;background:var(--fill);color:var(--ao-ink);border:2px solid transparent;border-radius:14px;padding:16px 0;cursor:pointer;">
          AO <span class="endSideName" style="display:block;font-size:17px;font-weight:600;letter-spacing:.02em;text-transform:none;color:var(--second);"></span>
        </button>
      </div>

      <div style="display:flex;flex-wrap:wrap;gap:10px;">
        @foreach (['hansoku', 'shikkaku', 'kiken', 'medical', 'no_show', 'other'] as $reason)
          <button class="endReason" data-reason="{{ $reason }}" style="font-size:20px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;background:var(--fill);color:var(--text);border:2px solid transparent;border-radius:11px;padding:12px 20px;cursor:pointer;">{{ __('scoreboard::karate_messages.end_reason_'.$reason) }}</button>
        @endforeach
      </div>

      <input id="endNote" type="text" maxlength="200" placeholder="{{ __('scoreboard::karate_messages.end_note') }}" class="fld">

      <button id="endDeclare" disabled style="font-size:26px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;background:var(--aka-ink);color:#000;border:none;border-radius:14px;padding:18px 0;cursor:pointer;opacity:.4;">{{ __('scoreboard::karate_messages.end_declare') }}</button>
      <div style="font-size:17px;color:var(--faint);letter-spacing:.04em;text-align:center;">{{ __('scoreboard::karate_messages.end_hint') }}</div>
    </div>
  </div>

  {{-- ── Hardware ─────────────────────────────────────────────────────────
       Empty, and honest about it: this product has no body-protector or
       referee-remote integration, and a panel that pretended otherwise would
       be a dead "Connect" button beside a live scoring grid. --}}
  <div id="hardware" hidden class="scrim" style="z-index:21;">
    <div class="modal" style="width:640px;">
      <div class="mhead">
        <div class="mtitle" style="color:var(--text);">{{ __('scoreboard::karate_messages.ctl_hardware') }}</div>
        <button data-close="hardware" class="mclose">✕</button>
      </div>
      <div style="display:flex;flex-direction:column;align-items:center;gap:10px;padding:40px 0;color:var(--faint);">
        <span style="font-size:40px;">⌁</span>
        <div style="font-size:22px;letter-spacing:.06em;">{{ __('scoreboard::karate_messages.ctl_hardware_empty') }}</div>
        <div style="font-size:17px;letter-spacing:.04em;text-align:center;">{{ __('scoreboard::karate_messages.ctl_hardware_hint') }}</div>
      </div>
    </div>
  </div>
</div></div>

<script>
(function () {
  'use strict';

  var STATE = @json($state);
  var QUEUE = @json($queue);
  var MAT = @json($court);
  // Whichever door opened this page — an organiser by event uuid, or a paired
  // scoring table by its own device token. Same console either way.
  var URL_CMD = @json($commandUrl ?? route('karate-scoreboard.command', $event->uuid));
  var EVENT_TITLE = @json($event->title);
  var PEN = @json(\App\Scoreboard\Sports\Karate\Mat\MatState::PENALTIES);
  var TBD = @json(__('scoreboard::karate_messages.court_tbd'));
  var FAILED = @json(__('scoreboard::karate_messages.ctl_failed'));

  var el = function (id) { return document.getElementById(id); };
  var root = el('root');
  var stageScale = 1;

  function fit() {
    var r = root.getBoundingClientRect();
    if (!r.width || !r.height) return;

    // offsetHeight is the LAID-OUT height and is unaffected by the transform, so
    // this reads the true size of the console however tall it turned out —
    // rather than trusting a 1080 that stopped being true the moment anything
    // was added to it.
    var stage = el('stage');
    var tall = Math.max(stage ? stage.offsetHeight : 1080, 1080);

    stageScale = Math.min(r.width / 1920, r.height / tall);
    root.style.setProperty('--stage-scale', stageScale);
  }

  // Watched on the STAGE as well as the root: the console grows and shrinks on
  // its own (a queue arrives, a panel opens, a round name wraps), and a re-fit
  // has to follow the content, not only the window.
  if (window.ResizeObserver) {
    var ro = new ResizeObserver(fit);
    ro.observe(root);
    var stageEl = el('stage');
    if (stageEl) ro.observe(stageEl);
  } else {
    window.addEventListener('resize', fit);
  }
  fit();

@isset($heartbeatUrl)
  // A paired console is a SCREEN and is listed beside the boards with a live
  // dot. Commands alone would show a mat waiting twenty minutes for the next
  // bout as offline — the opposite of the truth, and exactly when an organiser
  // is checking. So it beats. It also notices being UNPAIRED and goes back to
  // its code, like the boards.
  //
  // Deliberately a minute, unlike the boards: this console's commands go
  // through the SAME per-token rate limit as this beat, and an official scoring
  // a busy bout can spend thirty of those in a minute. A mat that will not
  // accept a point is far worse than a console that takes a minute to notice it
  // was unpaired — which is a thing a human is looking straight at anyway.
  setInterval(function () {
    fetch(@json($heartbeatUrl), { cache: 'no-store' })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (s) { if (s && s.claimed === false) window.location.reload(); })
      .catch(function () { /* keep scoring — the table is not the network */ });
  }, 60000);
@endisset

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
                 'X-CSRF-TOKEN': csrf() },
      credentials: 'same-origin',
      body: JSON.stringify(Object.assign({ mat: MAT, command: command }, payload || {})),
    }).then(function (r) { return r.json().catch(function () { return {}; }); })
      .then(function (d) {
        if (!d.success) throw new Error(d.message || FAILED);
        STATE = d.state; QUEUE = d.queue; received = performance.now();
        paint();
      })
      .catch(function (e) { alertBar(e.message); })
      .finally(function () { busy = false; });
  }

  function csrf() { return document.querySelector('meta[name=csrf-token]').content; }

  // No toast library on this document — it does not extend a layout. A bar
  // across the top is louder anyway, which is right at a mat. `ok` turns it
  // green: a refusal and a confirmation both have to be readable at arm's
  // length, and the difference between them cannot be that one is a toast.
  function alertBar(msg, ok) {
    var b = document.createElement('div');
    b.style.cssText = 'position:fixed;top:0;left:0;right:0;z-index:60;background:' + (ok ? '#1f7a3d' : '#ff3b47') +
      ';color:#fff;font-size:22px;font-weight:700;letter-spacing:.08em;text-align:center;padding:14px;';
    b.textContent = msg;
    document.body.appendChild(b);
    setTimeout(function () { b.remove(); }, 4000);
  }

  function fmt(t) { return Math.floor(t / 60) + ':' + String(Math.floor(t % 60)).padStart(2, '0'); }

  /* ── The table's own small cue ─────────────────────────────────────────
     The HALL's sounds belong to the wall boards: they are this event's
     uploaded files, they are loud, and they are what the crowd hears. This is
     not those. It is a short quiet blip for the person holding the console, so
     the last seconds and the bell reach the official who is watching the fight
     rather than the clock — and it is synthesised rather than fetched, so it
     can never be the same track playing twice a beat apart across a hall.

     Deliberately quiet (gain .06 against the board's .25) and short: a scoring
     table sits a metre from an official's ear. */
  var actx = null;

  function cue(times) {
    try {
      actx = actx || new (window.AudioContext || window.webkitAudioContext)();

      // A browser will not make a sound before the page has been interacted
      // with. Unlike a wall screen this console IS touched — constantly — so
      // by the time a bout is running the context is awake. Asking again is
      // free when it already is.
      if (actx.state === 'suspended' && actx.resume) actx.resume();

      for (var i = 0; i < times; i++) {
        var at = actx.currentTime + i * 0.16;
        var o = actx.createOscillator(), g = actx.createGain();
        o.type = 'sine';                 // sine, not square: a blip, not a buzzer
        o.frequency.value = 880;
        o.connect(g); g.connect(actx.destination);
        g.gain.setValueAtTime(0.0001, at);
        g.gain.exponentialRampToValueAtTime(0.06, at + 0.01);
        g.gain.exponentialRampToValueAtTime(0.0001, at + 0.12);
        o.start(at);
        o.stop(at + 0.13);
      }
    } catch (e) { /* a console with no audio device is not a fault */ }
  }

  /* ── The clock, derived exactly as the wall derives it ─────────────────── */
  var received = performance.now();
  function liveRemaining() {
    if (!STATE.running) return STATE.remaining || 0;
    return Math.max(0, STATE.remaining - (performance.now() - received) / 1000);
  }
  // Once per bout each, rearmed when the clock climbs back above the line or a
  // new bout walks on — the clock repaints ten times a second and a cue per
  // frame would be an alarm.
  var cuedWarning = false, cuedTimeUp = false, cuedBout = null;

  function paintClock() {
    var t = liveRemaining(), over = STATE.finished || t <= 0;
    // The warning point is the mat's, not this browser's: the wall flashes from
    // the same number, so the hall and the table start worrying together.
    var warn = STATE.atoshiWarn !== false ? (STATE.warning || 0) : 0;
    var low = warn > 0 && t <= warn && t > 0 && STATE.running;
    var timer = el('timer');

    // A new bout arms both cues again.
    if (cuedBout !== STATE.matchId) { cuedBout = STATE.matchId; cuedWarning = false; cuedTimeUp = false; }

    // Atoshi baraku at the table: one blip as the bout crosses into its last
    // seconds, at the same moment the wall starts flashing.
    if (low && !cuedWarning) { cuedWarning = true; cue(1); }
    if (t > warn) cuedWarning = false;

    // The bell: two, so it is not mistaken for the warning. Held to the same
    // switch as the hall's buzzer — an official who turned the buzzer off
    // turned it off, and a console chirping on regardless is exactly the kind
    // of small dishonesty that gets a setting ignored.
    if (t <= 0 && STATE.running && !cuedTimeUp) {
      cuedTimeUp = true;
      if (STATE.timeUpBuzzer !== false) cue(2);
    }
    if (t > 0) cuedTimeUp = false;

    timer.textContent = fmt(t);
    timer.style.animation = low ? 'atoshiFlash 1s ease-in-out infinite'
      : (STATE.running ? 'timerGlow 2.4s ease-in-out infinite' : 'none');
    timer.style.color = low ? '' : 'var(--text)';

    el('status').textContent = !STATE.matchId
      ? @json(__('scoreboard::karate_messages.ctl_waiting'))
      : over ? @json(__('scoreboard::karate_messages.ctl_time_up'))
             : STATE.running ? @json(__('scoreboard::karate_messages.ctl_start'))
                             : @json(__('scoreboard::karate_messages.ctl_pause'));
    el('status').style.color = STATE.running ? 'var(--green)' : 'var(--gold)';

    var start = el('btnStart');
    start.textContent = STATE.running ? @json(__('scoreboard::karate_messages.ctl_pause'))
                                      : @json(__('scoreboard::karate_messages.ctl_start'));
    // The gloss rides over the colour, so the fill is set on its own property —
    // `background` is a shorthand and would drop the gradient every tick.
    start.style.backgroundImage = 'linear-gradient(rgba(255,255,255,.28),rgba(255,255,255,0) 60%,rgba(0,0,0,.14))';
    start.style.backgroundColor = STATE.running ? '#ff6b78' : '#7ae582';
    start.style.animation = STATE.running ? 'runGlow 1.8s ease-in-out infinite' : 'none';
  }
  setInterval(paintClock, 100);

  /* ── A corner ─────────────────────────────────────────────────────────── */
  // Two identical keyframes, alternated: re-assigning the same animation-name
  // does not replay it.
  var popFlip = { aka: false, ao: false };

  function flagUrl(code) {
    return /^[a-z]{2}$/.test(code || '') ? 'url("https://flagcdn.com/w1280/' + code + '.png")' : 'none';
  }

  function paintSide(side) {
    var c = STATE[side] || {};
    var score = el('score-' + side);

    if (score.textContent !== String(STATE[side + 'Score'])) {
      score.textContent = STATE[side + 'Score'];
      popFlip[side] = !popFlip[side];
      score.style.animation = (popFlip[side] ? 'popA' : 'popB') + ' .45s cubic-bezier(.2,.8,.2,1)';
    }

    // Five cells. Tapping cell i asks for level i+1; tapping the one that is
    // already the top of the ladder asks for one below it, which is how a
    // penalty given by mistake comes off without cycling the whole ladder.
    var level = STATE[side + 'Pen'] || 0;
    var host = el('pen-' + side);
    host.textContent = '';
    PEN.forEach(function (label, i) {
      var on = i < level, top = i >= 3;   // HC and H are the disqualifying two
      var b = document.createElement('button');
      b.className = 'pcell bout';
      b.disabled = !STATE.matchId;
      b.style.background = on ? (top ? '#b3121f' : '#ffe135') : '#0a0b10';
      b.style.color = on ? (top ? '#fff' : '#000') : '#5c6175';
      b.style.border = '1px solid ' + (on ? (top ? '#ff3b47' : 'transparent') : '#1f2230');
      b.textContent = label;
      b.onclick = function () { send('penalty', { side: side, level: level === i + 1 ? i : i + 1 }); };
      host.appendChild(b);
    });

    var sen = el('senshu-' + side), held = !!STATE[side + 'Senshu'];
    sen.style.background = held ? '#ffe135' : '#1f2230';
    sen.style.color = held ? '#000' : '#7d8296';
    sen.style.border = '1px solid ' + (held ? 'transparent' : '#2a2e40');
    sen.style.animation = held ? 'senshuGlow 2s ease-in-out infinite' : 'none';
    // A mat running without the senshu rule does not offer it at all.
    sen.style.opacity = STATE.senshuRule === false ? '.35' : '1';

    el('name-' + side).textContent = c.name || TBD;
    el('meta-' + side).textContent = [c.club || '', c.country || ''].filter(Boolean).join(' · ');
    el('flag-' + side).style.backgroundImage = flagUrl(c.flag);

    // Their own face, then the drawn stand-in, then the glyph — the same
    // order the Bouts list uses, so a competitor looks the same wherever this
    // console draws them.
    var thumb = el('thumb-' + side), face = c.photo || c.fallback;
    thumb.style.backgroundImage = face ? 'url("' + face + '")' : 'none';
    thumb.style.opacity = (face && !c.photo) ? '.72' : '1';
    thumb.textContent = face ? '' : '◎';
  }

  /* ── The header, from the draw ─────────────────────────────────────────── */
  function paintMeta() {
    el('mTournament').textContent = STATE.tournament || EVENT_TITLE;
    el('mCategory').textContent = STATE.division || '—';
    el('mMatchNo').textContent = STATE.matchNo ? '#' + STATE.matchNo : '—';
    el('mCourt').textContent = STATE.courtLabel || MAT;
    el('mRound').textContent = STATE.stage || '—';
  }

  /** Everything that needs a bout goes dead while the mat is empty. */
  function paintArmed() {
    var armed = !!STATE.matchId;
    Array.prototype.forEach.call(document.querySelectorAll('.bout'), function (b) { b.disabled = !armed; });

    // Runs AFTER the sweep above, or it would be undone by it.
    if (STATE.senshuRule === false) {
      el('senshu-aka').disabled = true;
      el('senshu-ao').disabled = true;
    }
  }

  /** The introduction/scoreboard toggle, labelled for what it will do next. */
  function paintBoardToggle() {
    var b = el('btnBoard'), showingIntro = STATE.mode === 'vs';

    b.textContent = showingIntro
      ? @json(__('scoreboard::karate_messages.ctl_show_board'))
      : @json(__('scoreboard::karate_messages.ctl_show_intro'));

    // Two states, two HUES — not one colour filled and unfilled. This button is
    // read at a glance while an official is looking at the mat, and "solid blue
    // or outlined blue" is a difference you have to stop and study. Gold is the
    // introduction (the ceremony the hall is watching), blue is the scoreboard
    // (the bout), and the button is always painted as the thing it will SWITCH
    // TO — which is also what its label says.
    if (showingIntro) {
      // The wall is on the introduction; pressing this puts the board up.
      b.style.background = '#8ab4ff';
      b.style.color = '#000';
      b.style.borderColor = '#8ab4ff';
    } else {
      // The wall is on the board; pressing this brings the introduction back.
      b.style.background = '#12141d';
      b.style.color = '#ffe135';
      b.style.borderColor = '#ffe135';
    }
  }

  function paint() {
    paintMeta(); paintSide('aka'); paintSide('ao');
    paintWinner(); paintClock(); paintQueue(); paintArmed(); paintBoardToggle();
    paintRules(); paintTimerFields(); paintParticipant();
    askHowItEnded();
  }

  // Which bout we have already asked about. A bout is asked ONCE: an official
  // who closes the dialog to look at the score or the penalties gets the
  // console back, and reaches End bout when they are ready. Re-opening it under
  // them on the next reply would be a trap, not a prompt.
  var askedFor = null;

  /**
   * The bout ended by itself — the gap, the ladder, or the bell. Ask.
   *
   * This is the whole point of `awaitingDecision`: the clock stopping is not a
   * result. WKF ends bouts on things no scoreboard can see, so the console puts
   * the question up rather than announcing a winner, and the hall keeps the
   * board on screen until an official answers.
   */
  function askHowItEnded() {
    if (!STATE.awaitingDecision || !STATE.matchId) {
      // A new bout, or a question already answered, arms the next one.
      if (!STATE.awaitingDecision) askedFor = null;
      return;
    }

    if (askedFor === STATE.matchId) return;
    askedFor = STATE.matchId;

    // Never over something the official is already doing — a country picker or
    // a photo half-cropped is a worse thing to lose than a prompt is to miss,
    // and End bout is still one press away.
    if (!el('crop').hidden || !el('participant').hidden || !el('settings').hidden) return;

    endOpen();
  }

  /* ── Bouts ────────────────────────────────────────────────────────────── */
  function competitorCell(name, club, flag, photo, fallback, colour, mirror) {
    var wrap = document.createElement('span');
    wrap.style.cssText = 'display:flex;align-items:center;gap:12px;min-width:0;' + (mirror ? 'flex-direction:row-reverse;' : '');

    // A PORTRAIT, three wide to four tall — the shape every screen in this
    // package draws a person in, so the face an organiser framed at the mat is
    // the same face here rather than a squashed copy of it. Initials are the
    // fallback, not the default: a competitor with no photo is normal, and an
    // empty grey box reads as broken.
    var ini = document.createElement('span');
    ini.style.cssText = 'width:48px;height:64px;flex:0 0 auto;border-radius:8px;background:#1f2230;border:2px solid ' + colour +
      ';background-size:cover;background-position:center 15%;overflow:hidden;' +
      "display:flex;align-items:center;justify-content:center;font-family:'Anton',sans-serif;font-size:20px;color:" +
      (colour === '#b3121f' ? '#ff6b78' : '#8ab4ff') + ';';

    // Their own face first; then the drawn stand-in for their gender, which the
    // server only sends when the gender is actually on file; then their
    // initials, which invent nothing about a person nobody recorded.
    var face = photo || fallback;

    if (face) {
      ini.style.backgroundImage = 'url("' + face + '")';
      // The stand-in is a drawing, not this competitor: it sits back a little
      // so a row of them never reads as a row of photographs.
      if (!photo) ini.style.opacity = '.72';
    } else {
      ini.textContent = (name || '').split(/\s+/).filter(Boolean).slice(0, 2)
        .map(function (w) { return w.charAt(0).toUpperCase(); }).join('') || '—';
    }

    var col = document.createElement('span');
    col.style.cssText = 'min-width:0;display:flex;flex-direction:column;gap:2px;' + (mirror ? 'align-items:flex-end;' : '');

    var nm = document.createElement('span');
    nm.style.cssText = 'color:' + (colour === '#b3121f' ? '#ff6b78' : '#6ea8ff') +
      ';white-space:nowrap;overflow:hidden;text-overflow:ellipsis;line-height:1.1;';
    nm.textContent = name || TBD;

    var cl = document.createElement('span');
    cl.style.cssText = 'display:flex;align-items:center;gap:8px;font-size:17px;color:#7d8296;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;' +
      (mirror ? 'flex-direction:row-reverse;' : '');
    var fl = document.createElement('span');
    fl.style.cssText = 'width:24px;height:16px;flex:0 0 auto;background-size:100% 100%;background-position:center;border:1px solid rgba(255,255,255,.2);border-radius:3px;background-image:' + flagUrl(flag) + ';';
    var cn = document.createElement('span');
    cn.textContent = club || '';
    cl.appendChild(fl); cl.appendChild(cn);

    col.appendChild(nm); col.appendChild(cl);
    wrap.appendChild(ini); wrap.appendChild(col);

    return wrap;
  }

  function paintQueue() {
    var host = el('boutsList');
    host.textContent = '';

    if (!QUEUE.length) {
      var empty = document.createElement('div');
      empty.style.cssText = 'font-size:21px;color:#5c6175;padding:16px 4px;';
      empty.textContent = @json(__('scoreboard::karate_messages.ctl_no_queue'));
      host.appendChild(empty);
      return;
    }

    QUEUE.forEach(function (b) {
      var row = document.createElement('button');
      row.style.cssText = 'display:grid;grid-template-columns:100px 180px 1fr 48px 1fr;align-items:center;gap:14px;font-size:30px;' +
        'background:' + (b.id === STATE.matchId ? '#1f2230' : '#0a0b10') + ';color:#e8eaf2;border:1px solid #1f2230;' +
        'border-radius:12px;padding:20px 22px;cursor:pointer;text-align:left;';

      var no = document.createElement('span');
      no.style.cssText = "font-family:'Anton',sans-serif;color:#ffe135;";
      no.textContent = '#' + (b.number == null ? '—' : b.number);

      var stage = document.createElement('span');
      stage.style.cssText = 'min-width:0;display:flex;flex-direction:column;gap:2px;';
      var st = document.createElement('span');
      st.style.cssText = 'color:#7d8296;text-transform:uppercase;font-size:22px;letter-spacing:.08em;line-height:1.1;';
      st.textContent = b.stage || '';
      var cat = document.createElement('span');
      cat.style.cssText = 'font-size:16px;color:#5c6175;letter-spacing:.04em;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;';
      cat.textContent = b.division || '';
      stage.appendChild(st); stage.appendChild(cat);

      var vs = document.createElement('span');
      vs.style.cssText = 'color:#5c6175;text-align:center;';
      vs.textContent = 'vs';

      row.appendChild(no);
      row.appendChild(stage);
      // AKA is mirrored so the two portraits meet either side of the "vs".
      row.appendChild(competitorCell(b.aka, b.akaClub, b.akaFlag, b.akaPhoto, b.akaFallback, '#b3121f', true));
      row.appendChild(vs);
      row.appendChild(competitorCell(b.ao, b.aoClub, b.aoFlag, b.aoPhoto, b.aoFallback, '#0d55b8', false));

      if (!b.runnable) {
        // Listed so the operator can see it coming, but not loadable: nobody
        // can be introduced as "the winner of bout 3".
        row.style.opacity = '.45';
        row.style.cursor = 'not-allowed';
        row.title = @json(__('scoreboard::karate_messages.ctl_waiting_feeder'));
        host.appendChild(row);
        return;
      }

      row.onclick = function () {
        send('load', { match_id: b.id, seconds: boutSeconds() }).then(function () { el('bouts').hidden = true; });
      };
      host.appendChild(row);
    });
  }

  /* ── Settings ─────────────────────────────────────────────────────────── */
  var RULES = ['senshuRule', 'autoSenshu', 'winByPenalties', 'atoshiWarn', 'timeUpBuzzer', 'gapOn'];
  // What the panel is showing, which is not what the mat is running until
  // Apply: a switch flipped and then thought better of should cost nothing.
  var draft = null;

  function draftFromState() {
    var d = {};
    RULES.forEach(function (k) { d[k] = STATE[k] !== false; });
    d.gap = STATE.gap || 8;
    return d;
  }

  function paintRules() {
    if (!draft) draft = draftFromState();

    Array.prototype.forEach.call(document.querySelectorAll('[data-rule]'), function (row) {
      var key = row.getAttribute('data-rule');
      var box = row.classList.contains('rbox') ? row : row.querySelector('.rbox');
      if (!box) return;
      var on = !!draft[key];
      box.style.background = on ? '#ffe135' : '#1f2230';
      box.style.border = '1px solid ' + (on ? 'transparent' : '#2a2e40');
      box.textContent = on ? '✓' : '';
    });

    var gapVal = el('gapVal');
    if (document.activeElement !== gapVal) gapVal.value = draft.gap;
    gapVal.style.opacity = draft.gapOn ? '1' : '.35';
  }

  function paintTimerFields() {
    var dur = Math.round(STATE.duration || 180), warn = Math.round(STATE.warning || 0);
    if (document.activeElement !== el('durMin')) el('durMin').value = Math.floor(dur / 60);
    if (document.activeElement !== el('durSec')) el('durSec').value = dur % 60;
    if (document.activeElement !== el('atoMin')) el('atoMin').value = Math.floor(warn / 60);
    if (document.activeElement !== el('atoSec')) el('atoSec').value = warn % 60;
  }

  function num(id, max) {
    var v = parseInt(el(id).value, 10);
    return isNaN(v) ? 0 : Math.max(0, Math.min(max, v));
  }
  function boutSeconds() { return Math.max(10, num('durMin', 59) * 60 + num('durSec', 59)); }
  function warnSeconds() { return num('atoMin', 59) * 60 + num('atoSec', 59); }

  function paintTabs(which) {
    Array.prototype.forEach.call(document.querySelectorAll('.stab'), function (t) {
      var on = t.getAttribute('data-tab') === which;
      t.style.background = on ? '#ffe135' : '#12141d';
      t.style.color = on ? '#000' : '#7d8296';
      t.style.border = '1px solid ' + (on ? 'transparent' : '#2a2e40');
    });
    Array.prototype.forEach.call(document.querySelectorAll('.spanel'), function (p) {
      p.hidden = p.getAttribute('data-panel') !== which;
    });

    // Apply belongs to the rules and the timer. The camera panel writes its own
    // changes as they are made, so leaving the button there would offer to
    // apply something the reader is not looking at.
    var apply = el('btnApply');
    if (apply) {
      apply.hidden = which === 'cameras';
      if (apply.nextElementSibling) apply.nextElementSibling.hidden = which === 'cameras';
    }
  }

  /* ── The participant, and their photograph ────────────────────────────── */
  var pSide = null;

  function paintParticipant() {
    if (!pSide) return;

    var c = STATE[pSide] || {};
    var accent = pSide === 'aka' ? '#ff6b78' : '#8ab4ff';

    el('pBar').style.background = accent;
    el('pTitle').style.color = accent;
    el('pTitle').textContent = pSide.toUpperCase() + ' — ' + @json(__('scoreboard::karate_messages.ctl_participant'));
    el('pName').textContent = c.name || TBD;
    el('pClub').textContent = c.club || '—';
    el('pCountry').textContent = c.country || '—';
    el('pFlag').style.backgroundImage = flagUrl(c.flag);

    var box = el('pPhoto'), pFace = c.photo || c.fallback;
    box.style.border = '1px solid ' + accent;
    box.style.backgroundImage = pFace ? 'url("' + pFace + '")' : 'none';
    box.style.opacity = (pFace && !c.photo) ? '.72' : '1';

    // The ◎ goes as soon as there is anything to look at. The word PHOTO stays
    // over a stand-in, because this box is the button that adds one — and a
    // drawn avatar with no label reads as a picture somebody already took.
    el('pPhotoIcon').hidden = !!pFace;
    el('pPhotoLabel').hidden = !!c.photo;
  }

  /* ── The cropper ──────────────────────────────────────────────────────── */
  var VIEW_W = 330, VIEW_H = 440, OUT_W = 600, OUT_H = 800;
  var crop = { img: null, url: null, nw: 0, nh: 0, z: 1, r: 0, x: 0, y: 0 };

  function cropBase() { return crop.nw ? Math.max(VIEW_W / crop.nw, VIEW_H / crop.nh) : 1; }

  function paintCrop() {
    var has = !!crop.img;
    el('cropImg').hidden = !has;
    el('cropFrame').hidden = !has;
    el('cropEmpty').hidden = has;
    el('cropSave').disabled = !has;
    el('cropSave').style.opacity = has ? '1' : '.4';
    el('cropTiltLabel').textContent = Math.round(crop.r) + '°';

    if (!has) return;

    var k = cropBase() * crop.z;
    var box = el('cropImg');
    box.style.width = crop.nw + 'px';
    box.style.height = crop.nh + 'px';
    box.style.backgroundImage = 'url("' + crop.url + '")';
    box.style.backgroundSize = crop.nw + 'px ' + crop.nh + 'px';
    box.style.left = (VIEW_W / 2 - crop.nw / 2) + 'px';
    box.style.top = (VIEW_H / 2 - crop.nh / 2) + 'px';
    box.style.transform = 'translate(' + crop.x + 'px,' + crop.y + 'px) rotate(' + crop.r + 'deg) scale(' + k + ')';
  }

  function openCrop() {
    // Only the two competitors on this mat can be photographed, and only when
    // there is an entry behind the corner to attach it to.
    if (!PHOTO_BASE) { alertBar(@json(__('scoreboard::karate_messages.ctl_photo_unavailable'))); return; }

    releaseCrop();
    crop = { img: null, url: null, nw: 0, nh: 0, z: 1, r: 0, x: 0, y: 0 };
    el('cropZoom').value = 1;
    el('cropTilt').value = 0;
    el('cropFile').value = '';
    el('cropTitle').textContent = @json(__('scoreboard::karate_messages.ctl_crop_title')) + ' — ' +
      pSide.toUpperCase() + ' · ' + ((STATE[pSide] || {}).name || TBD);
    el('cropTitle').style.color = pSide === 'aka' ? '#ff6b78' : '#8ab4ff';
    paintCrop();
    el('crop').hidden = false;
  }

  // An object URL, never a data URL: a `data:` URL's `;base64` semicolon breaks
  // naive style parsing, and these end up inside a background-image string.
  function releaseCrop() {
    if (crop.url) { try { URL.revokeObjectURL(crop.url); } catch (e) {} }
  }

  el('cropFile').onchange = function () {
    var file = this.files && this.files[0];
    if (!file) return;

    // Refused here rather than after travelling: a phone's full-resolution shot
    // is comfortably over this on a venue's uplink.
    if (file.size > 10 * 1024 * 1024) { alertBar(@json(__('personal.event_photo_too_big'))); this.value = ''; return; }

    releaseCrop();
    var url = URL.createObjectURL(file);
    var img = new Image();
    img.onload = function () {
      crop.img = img; crop.url = url; crop.nw = img.naturalWidth; crop.nh = img.naturalHeight;
      crop.z = 1; crop.r = 0; crop.x = 0; crop.y = 0;
      el('cropZoom').value = 1; el('cropTilt').value = 0;
      paintCrop();
    };
    img.onerror = function () { alertBar(FAILED); };
    img.src = url;
  };

  el('cropZoom').oninput = function () { crop.z = parseFloat(this.value) || 1; paintCrop(); };
  el('cropTilt').oninput = function () { crop.r = parseFloat(this.value) || 0; paintCrop(); };

  // Dragging happens on the STAGE, which is scaled — so a pointer delta in
  // screen pixels is not a delta in the console's own pixels. Divide, or the
  // photo moves at the wrong speed under the finger.
  (function () {
    var view = el('cropView'), dragging = false, lx = 0, ly = 0;

    view.addEventListener('pointerdown', function (e) {
      if (!crop.img) return;
      dragging = true; lx = e.clientX; ly = e.clientY;
      view.setPointerCapture(e.pointerId);
      view.style.cursor = 'grabbing';
    });
    view.addEventListener('pointermove', function (e) {
      if (!dragging) return;
      var s = stageScale || 1;
      crop.x += (e.clientX - lx) / s;
      crop.y += (e.clientY - ly) / s;
      lx = e.clientX; ly = e.clientY;
      paintCrop();
    });
    ['pointerup', 'pointercancel'].forEach(function (ev) {
      view.addEventListener(ev, function (e) {
        dragging = false;
        try { view.releasePointerCapture(e.pointerId); } catch (err) {}
        view.style.cursor = 'grab';
      });
    });
  })();

  el('cropSave').onclick = function () {
    if (!crop.img) return;

    // The same transform the frame is showing, at output scale: what the
    // official lined up is exactly what the hall gets, at the size every screen
    // draws — so no board ever letterboxes or squashes a face.
    var cv = document.createElement('canvas');
    cv.width = OUT_W; cv.height = OUT_H;
    var ctx = cv.getContext('2d');
    ctx.fillStyle = '#0a0b10';
    ctx.fillRect(0, 0, OUT_W, OUT_H);

    var f = OUT_W / VIEW_W;
    ctx.scale(f, f);
    ctx.translate(VIEW_W / 2 + crop.x, VIEW_H / 2 + crop.y);
    ctx.rotate(crop.r * Math.PI / 180);
    var k = cropBase() * crop.z;
    ctx.scale(k, k);
    ctx.drawImage(crop.img, -crop.nw / 2, -crop.nh / 2);

    postPhoto({ image: cv.toDataURL('image/jpeg', 0.88) });
  };

  el('cropRemove').onclick = function () { postPhoto({ remove: true }); };

  function postPhoto(body) {
    if (!PHOTO_BASE || !pSide) return;

    var save = el('cropSave');
    save.disabled = true;

    fetch(PHOTO_BASE + pSide, {
      method: 'POST',
      headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
      credentials: 'same-origin',
      body: JSON.stringify(Object.assign({ mat: MAT }, body)),
    })
      .then(function (r) { return r.json().catch(function () { return {}; }); })
      .then(function (d) {
        if (!d.success) throw new Error(d.message || FAILED);
        // The wall was pushed the new corner by the server; the console just
        // takes the state it was handed back.
        if (d.state) { STATE = d.state; paint(); }
        el('crop').hidden = true;
        alertBar(d.message, true);
      })
      .catch(function (err) { alertBar(err.message); })
      .finally(function () { save.disabled = false; paintCrop(); });
  }

  /* ── The celebration ──────────────────────────────────────────────────── */
  @php
      // Pre-assigned, never inline: Blade's bracket matcher chokes on an array
      // literal inside @json().
      $reasonLabels = [
          'hansoku' => __('scoreboard::karate_messages.end_reason_hansoku'),
          'shikkaku' => __('scoreboard::karate_messages.end_reason_shikkaku'),
          'kiken' => __('scoreboard::karate_messages.end_reason_kiken'),
          'medical' => __('scoreboard::karate_messages.end_reason_medical'),
          'no_show' => __('scoreboard::karate_messages.end_reason_no_show'),
          'other' => __('scoreboard::karate_messages.end_reason_other'),
      ];
  @endphp
  var WON_BY = @json(__('scoreboard::karate_messages.sb_won_by'));
  var REASONS = @json($reasonLabels);

  // Which bout it belongs to, whether the official has tucked it away, and what
  // is currently on the glass.
  //
  // It used to be modal with exactly one way out — the commit button — so an
  // official who wanted to look at the console again had no choice but to
  // record the result and call the next bout. That is a write, and it is
  // irreversible from here. Now the celebration can be closed and reopened, and
  // closing it writes NOTHING: the bout stays finished, the result stays
  // unfiled, and the small plate in the corner is what says so.
  var winnerFor = null, winnerClosed = false, winnerPainted = null;

  var WINNER_FULL = 'position:absolute;inset:0;z-index:30;background:rgba(0,0,0,.45);overflow:hidden;';
  var WINNER_CHIP = 'position:absolute;inset:auto 22px 22px auto;z-index:30;background:none;overflow:visible;';

  function paintWinner() {
    var host = el('winner');
    // A bout that ended by itself has not been announced yet — neither the
    // confetti nor the small "winner, not filed" plate. The dialog below is
    // the whole of the console until somebody answers it.
    var live = STATE.finished && (STATE.akaLeads || STATE.aoLeads)
      && STATE.matchId && !STATE.awaitingDecision;

    if (!live) {
      winnerFor = null; winnerClosed = false; winnerPainted = null;
      WinnerCelebration.clear(host); host.style.cssText = WINNER_FULL;
      return;
    }

    // A NEW finished bout always celebrates, however the last one was left. The
    // close is a decision about one result, not a preference to remember.
    if (winnerFor !== STATE.matchId) { winnerFor = STATE.matchId; winnerClosed = false; winnerPainted = null; }

    // The shared flag is the truth: closing is a command, so a second console on
    // the same mat, a reload, or a reconnect all land on the same view as the
    // wall rather than on whatever this tab happened to remember.
    if (typeof STATE.celebrationClosed === 'boolean' && STATE.celebrationClosed !== winnerClosed) {
      winnerClosed = STATE.celebrationClosed;
      winnerPainted = null;
    }

    var want = STATE.matchId + '|' + (winnerClosed ? 'chip' : 'full');
    if (winnerPainted === want) return;
    winnerPainted = want;

    WinnerCelebration.clear(host);
    host.hidden = false;

    var aka = STATE.akaLeads;
    var colour = aka ? '#b3121f' : '#0d55b8';
    var c = (aka ? STATE.aka : STATE.ao) || {};
    var name = c.name || '';

    // ── Closed: a plate in the corner, and the console is usable again ───────
    if (winnerClosed) {
      host.style.cssText = WINNER_CHIP;

      var chip = document.createElement('div');
      chip.style.cssText = 'display:flex;align-items:center;gap:14px;padding:12px 16px;border-radius:16px;' +
        'background:linear-gradient(135deg,' + colour + ',#12141c);border:1px solid rgba(255,255,255,.22);' +
        'box-shadow:0 18px 50px rgba(0,0,0,.55);animation:chipPop .35s both;';

      var who = document.createElement('button');
      who.style.cssText = "display:flex;flex-direction:column;align-items:flex-start;gap:2px;background:none;border:none;" +
        "cursor:pointer;text-align:left;padding:0;font-family:'Barlow Condensed',sans-serif;";
      var lbl = document.createElement('span');
      lbl.style.cssText = 'font-size:13px;font-weight:600;letter-spacing:.24em;text-transform:uppercase;color:#ffe135;';
      lbl.textContent = @json(__('scoreboard::karate_messages.ctl_result_pending'));
      var nm = document.createElement('span');
      nm.style.cssText = 'font-size:26px;font-weight:700;line-height:1;color:#fff;text-transform:uppercase;';
      nm.textContent = name;
      who.appendChild(lbl); who.appendChild(nm);
      // Reopening is free — it shows the same celebration again, unfiled, and it
      // brings the wall back with it.
      who.onclick = function () { winnerClosed = false; paintWinner(); send('celebrate'); };

      var file = document.createElement('button');
      file.style.cssText = "font-family:'Barlow Condensed',sans-serif;font-size:19px;font-weight:700;letter-spacing:.08em;" +
        'text-transform:uppercase;background:#7ae582;color:#000;border:none;border-radius:11px;padding:12px 22px;cursor:pointer;';
      file.textContent = @json(__('scoreboard::karate_messages.ctl_commit'));
      file.onclick = function () { send('commit'); };

      chip.appendChild(who); chip.appendChild(file);
      host.appendChild(chip);
      return;
    }

    // ── Open: the celebration ────────────────────────────────────────────────
    // The same scene the hall is looking at, so the official and the wall agree
    // about what just happened. The console adds the only two things the wall
    // has no use for: file it, or put it away.
    host.style.cssText = WINNER_FULL;

    var scene = WinnerCelebration.paint(host, {
      corner: aka ? 'red' : 'blue',
      name: name,
      club: c.club || '',
      logo: c.logo || null,
      photo: c.photo || null,
      label: @json(__('scoreboard::karate_messages.sb_winner')),
      note: (STATE.winReason && STATE.winReason !== 'points')
        ? WON_BY.replace(':reason', REASONS[STATE.winReason] || STATE.winReason) : ''
    });

    // The only control here that WRITES: it records the result, advances the
    // bracket and calls the next bout. The celebration otherwise sits there
    // until an official decides the bout is genuinely over — a scoreboard that
    // files a result on a timer files it before anyone has looked at it.
    var go = document.createElement('button');
    go.style.cssText = "font-family:'Barlow Condensed',sans-serif;font-size:30px;font-weight:700;" +
      'letter-spacing:.1em;text-transform:uppercase;background:#7ae582;color:#000;border:none;border-radius:14px;' +
      'padding:20px 56px;cursor:pointer;';
    go.textContent = @json(__('scoreboard::karate_messages.ctl_commit'));
    go.onclick = function () { send('commit'); };

    // The way out that is NOT a write. A FILLED button, not a ghost one: six
    // percent white was invisible against the celebration behind it, and a
    // control an official has to hunt for is not a control.
    var close = document.createElement('button');
    close.style.cssText = "font-family:'Barlow Condensed',sans-serif;font-size:30px;font-weight:700;" +
      'letter-spacing:.1em;text-transform:uppercase;background:linear-gradient(135deg,#3c4460,#1b1f2e);' +
      'color:#eef1f8;border:1px solid rgba(255,255,255,.38);border-radius:14px;padding:19px 55px;' +
      'box-shadow:0 12px 34px rgba(0,0,0,.5);cursor:pointer;';
    close.textContent = @json(__('scoreboard::karate_messages.ctl_dismiss'));
    // Closes it HERE and on every screen on this mat: `dismiss` writes the flag
    // into the shared mat state and the boards redraw from it. It does not touch
    // the bout or the result — the confetti stops, nothing is filed.
    close.onclick = function () { winnerClosed = true; paintWinner(); send('dismiss'); };

    var hint = document.createElement('div');
    hint.style.cssText = 'pointer-events:none;font-size:17px;color:#a7abbe;letter-spacing:.06em;max-width:620px;';
    hint.textContent = @json(__('scoreboard::karate_messages.ctl_commit_hint'));

    scene.actions.appendChild(go);
    scene.actions.appendChild(close);
    scene.actions.appendChild(hint);
  }

  /* ── Ending a bout ─────────────────────────────────────────────────────
     Never straight to the server: who won is a decision, and on a
     disqualification it is not the one the score would make. */
  var endSide = null, endReason = null;

  function endPaint() {
    Array.prototype.forEach.call(document.querySelectorAll('.endSide'), function (b) {
      var on = b.getAttribute('data-side') === endSide;
      b.style.borderColor = on ? '#ffe135' : 'transparent';
      b.style.background = on ? '#2a2412' : '#1f2230';
    });
    Array.prototype.forEach.call(document.querySelectorAll('.endReason'), function (b) {
      var on = b.getAttribute('data-reason') === endReason;
      b.style.borderColor = on ? '#ffe135' : 'transparent';
      b.style.background = on ? '#2a2412' : '#1f2230';
    });

    // A side AND a reason, both. A declared winner with no reason on the record
    // is the thing this dialog exists to prevent.
    var ready = !!endSide && !!endReason;
    var go = el('endDeclare');
    go.disabled = !ready;
    go.style.opacity = ready ? '1' : '.4';
    go.style.cursor = ready ? 'pointer' : 'default';
  }

  function endOpen() {
    endSide = null; endReason = null;
    el('endNote').value = '';

    // When the ending already named someone — the penalty ladder hands the bout
    // to the other corner — the dialog opens with that answer filled in, so
    // confirming it is one press rather than five. It is still a choice: the
    // official can clear it, pick the other side, or award on the score.
    if (STATE.winner) {
      endSide = STATE.winner;
      if (STATE.winReason && STATE.winReason !== 'points') endReason = STATE.winReason;
    }

    // Name the sides, so the choice is between two people rather than two
    // colours — and say what the automatic answer would be.
    var names = document.querySelectorAll('.endSide .endSideName');
    if (names[0]) names[0].textContent = (STATE.aka || {}).name || '';
    if (names[1]) names[1].textContent = (STATE.ao || {}).name || '';

    var auto = STATE.akaLeads ? ((STATE.aka || {}).name || 'AKA')
             : STATE.aoLeads ? ((STATE.ao || {}).name || 'AO')
             : null;
    el('endAutoWho').textContent = auto
      ? @json(__('scoreboard::karate_messages.end_auto_who')).replace(':name', auto)
      : @json(__('scoreboard::karate_messages.end_auto_level'));

    endPaint();
    el('endBout').hidden = false;
  }

  /* ── Wiring ───────────────────────────────────────────────────────────── */
  document.addEventListener('click', function (e) {
    var t = e.target.closest ? e.target.closest('[data-cmd],[data-close],[data-participant],[data-tab],[data-rule]') : null;
    if (!t) return;

    if (t.dataset.close) { el(t.dataset.close).hidden = true; return; }

    if (t.dataset.participant) {
      pSide = t.dataset.participant;
      paintParticipant();
      el('participant').hidden = false;
      return;
    }

    if (t.dataset.tab) { paintTabs(t.dataset.tab); return; }

    // A rule is flipped in the DRAFT and shown immediately; it reaches the mat
    // when Apply is pressed.
    if (t.dataset.rule) {
      draft[t.dataset.rule] = !draft[t.dataset.rule];
      paintRules();
      return;
    }

    if (t.dataset.cmd) {
      send(t.dataset.cmd, {
        side: t.dataset.side || null,
        n: t.dataset.n ? parseInt(t.dataset.n, 10) : null,
      });
    }
  });

  el('btnStart').onclick = function () { send(STATE.running ? 'pause' : 'start'); };
  el('btnKo').onclick = endOpen;
  el('btnReset').onclick = function () { send('reset'); };
  el('btnBouts').onclick = function () { el('bouts').hidden = false; paintQueue(); };
  el('btnHardware').onclick = function () { el('hardware').hidden = false; };

  // One button, both directions. Which one it is comes from the STATE rather
  // than from a flag this page keeps: a second console on the same mat, a
  // reload, or the wall being switched by somebody else all have to leave this
  // button telling the truth about what it will do next.
  el('btnBoard').onclick = function () { send(STATE.mode === 'vs' ? 'board' : 'intro'); };

  // Forces every screen on this mat to start again. Changes nothing about the
  // bout — worst case a board that was fine blinks and comes back identical.
  el('btnResync').onclick = function () {
    var b = el('btnResync'), was = b.textContent;
    send('resync').then(function () {
      b.textContent = @json(__('scoreboard::karate_messages.ctl_resynced'));
      setTimeout(function () { b.textContent = was; }, 1500);
    });
  };

  el('btnSettings').onclick = function () {
    draft = draftFromState();
    paintRules();
    paintTimerFields();
    paintTabs('rules');
    el('settings').hidden = false;
  };

  el('gapVal').onchange = function () {
    var v = parseInt(this.value, 10);
    draft.gap = isNaN(v) ? 8 : Math.max(1, Math.min(20, v));
    this.value = draft.gap;
  };

  el('pPhoto').onclick = openCrop;

  // Apply: the rules first, then the clock only if the duration actually
  // changed — `send` is single-flight, so these are chained rather than fired
  // together, and re-setting a duration nobody touched would reset a clock
  // mid-bout.
  el('btnApply').onclick = function () {
    var wantSeconds = boutSeconds();
    var changedDuration = Math.round(STATE.duration || 0) !== wantSeconds;

    var rules = { gap: draft.gap, warning: warnSeconds() };
    RULES.forEach(function (k) { rules[k] = !!draft[k]; });

    send('rules', rules).then(function () {
      if (!changedDuration) { el('settings').hidden = true; return; }
      // The warning travels again with the new length: the server clamps it to
      // the bout, and a duration change can be what put it out of range.
      send('duration', { seconds: wantSeconds }).then(function () {
        send('rules', { warning: warnSeconds() }).then(function () { el('settings').hidden = true; });
      });
    });
  };

  el('endAuto').onclick = function () { el('endBout').hidden = true; send('finish'); };

  el('endDeclare').onclick = function () {
    if (!endSide || !endReason) return;
    el('endBout').hidden = true;
    send('finish', { winner: endSide, reason: endReason, note: el('endNote').value });
  };

  document.addEventListener('click', function (e) {
    var side = e.target.closest ? e.target.closest('.endSide') : null;
    if (side) { endSide = side.getAttribute('data-side'); endPaint(); return; }

    var reason = e.target.closest ? e.target.closest('.endReason') : null;
    if (reason) { endReason = reason.getAttribute('data-reason'); endPaint(); }
  });

  // Escape puts the celebration away. An official at a mat has a keyboard as
  // often as not, and the muscle memory for "get this off my screen" is the
  // same everywhere.
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape' || winnerClosed || winnerFor === null) return;
    winnerClosed = true;
    paintWinner();
    send('dismiss');
  });

  /* ── The event's sounds ───────────────────────────────────────────────── */
  var AUDIO_BASE = @json($audioUploadBase ?? null);
  var PHOTO_BASE = @json($photoUploadBase ?? null);
  var AUDIO_SLOTS = @json($audioSlots ?? []);
  var SLOTS = @json(\App\Events\Support\ScreenMedia::SLOTS);

  function kb(bytes) {
    if (!bytes) return '';
    return bytes < 1024 * 1024
      ? Math.max(1, Math.round(bytes / 1024)) + ' KB'
      : (bytes / 1024 / 1024).toFixed(1) + ' MB';
  }

  /** Redraw one row from what we know is stored. */
  function paintAudioRow(slot) {
    var name = document.querySelector('.audioName[data-slot="' + slot + '"]');
    var drop = document.querySelector('.audioDrop[data-slot="' + slot + '"]');
    var have = AUDIO_SLOTS[slot];

    if (name) {
      name.textContent = have
        ? (have.name || '') + (have.bytes ? ' · ' + kb(have.bytes) : '')
        : @json(__('events.screen_audio_none'));
      name.style.color = have ? '#7ae582' : '#5c6175';
    }
    if (drop) drop.hidden = !have;
  }

  SLOTS.forEach(paintAudioRow);

  // Delegated, because these rows are rendered by Blade and never rebuilt.
  document.addEventListener('change', function (e) {
    var pick = e.target.closest ? e.target.closest('.audioPick') : null;
    if (pick) uploadAudio(pick.getAttribute('data-slot'), pick);
  });

  document.addEventListener('click', function (e) {
    var drop = e.target.closest ? e.target.closest('.audioDrop') : null;
    if (drop) dropAudio(drop.getAttribute('data-slot'));
  });

  function uploadAudio(slot, input) {
    var file = input.files && input.files[0];
    if (!file) return;

    // Hidden entirely would be worse than said plainly: an organiser's laptop
    // door that was not given an address should say so, not fail quietly.
    if (!AUDIO_BASE) { alertBar(@json(__('scoreboard::karate_messages.ctl_sound_unavailable'))); input.value = ''; return; }

    var body = new FormData();
    body.append('file', file);

    fetch(AUDIO_BASE + slot, {
      method: 'POST',
      headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf() },
      credentials: 'same-origin',
      body: body,
    })
      .then(function (r) { return r.json().catch(function () { return {}; }); })
      .then(function (d) {
        if (!d.success) throw new Error(d.message || FAILED);
        AUDIO_SLOTS[slot] = { name: d.name, bytes: d.bytes };
        paintAudioRow(slot);
        alertBar(d.message, true);
      })
      .catch(function (err) { alertBar(err.message); })
      // So picking the SAME file again still fires a change.
      .finally(function () { input.value = ''; });
  }

  function dropAudio(slot) {
    if (!AUDIO_BASE) return;

    fetch(AUDIO_BASE + slot, {
      method: 'DELETE',
      headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf() },
      credentials: 'same-origin',
    })
      .then(function (r) { return r.json().catch(function () { return {}; }); })
      .then(function (d) {
        if (!d.success) throw new Error(d.message || FAILED);
        delete AUDIO_SLOTS[slot];
        paintAudioRow(slot);
        alertBar(d.message, true);
      })
      .catch(function (err) { alertBar(err.message); });
  }

  /* The approved design's own key bindings, unchanged. */
  window.addEventListener('keydown', function (e) {
    if (e.repeat || /input|textarea|select/i.test(e.target.tagName)) return;
    var k = e.key.toLowerCase();
    var map = { q:['point',{side:'aka',n:1}], w:['point',{side:'aka',n:2}], e:['point',{side:'aka',n:3}],
                u:['point',{side:'ao',n:1}],  i:['point',{side:'ao',n:2}],  o:['point',{side:'ao',n:3}],
                a:['penalty',{side:'aka',dir:1}], k:['penalty',{side:'ao',dir:1}],
                z:['senshu',{side:'aka'}],       m:['senshu',{side:'ao'}],
                r:['reset',{}] };
    if (!STATE.matchId) return;   // an empty mat has nothing to score
    if (k === ' ') { e.preventDefault(); send(STATE.running ? 'pause' : 'start'); return; }
    if (map[k]) { e.preventDefault(); send(map[k][0], map[k][1]); }
  });

  /* ── The seam a contributed panel talks through ────────────────────────
     A package may add one modal of its own to this console
     (AbstractEventType::matPanel) — Open Mat uses it to set the next pair
     without the operator leaving the scoreboard. That panel is a separate
     document fragment with its own script, and it needs to load a bout and
     raise a message exactly the way every control here does. It must NOT
     reimplement either: two ways of talking to one mat is how two consoles
     come to disagree. Nothing else is exposed, and when no package
     contributes a panel nothing ever calls this. */
  window.MatConsole = {
    send: send,
    alert: alertBar,
    mat: MAT,
    state: function () { return STATE; },
  };

  var btnPanel = el('btnMatPanel');
  if (btnPanel) {
    btnPanel.onclick = function () { el('omPanel').hidden = false; };
  }

  paintTabs('rules');
  paintTimerFields();
  paint();
})();
</script>
</body>
</html>
