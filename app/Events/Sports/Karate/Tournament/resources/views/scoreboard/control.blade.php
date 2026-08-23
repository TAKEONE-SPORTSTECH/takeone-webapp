{{--
    The scoring table at a Karate mat — Match Control.

    Transcribed from the approved layout at ../../Scoreboard/design/control.source.html.
    Like the wall screens it does NOT extend a layout and does not use the design
    system: it is a 1920x1080 broadcast console authored as a whole document, and
    it is operated at speed at a mat rather than browsed. Treat the visual output
    as fixed — restyle by agreement, never as a side effect.

    Every control the design ships is here and every one of them does something.
    Where the original stood alone and typed its competitors in by hand, the same
    fields now start from the draw and stay editable, because a hall is not a
    database: a name is misspelt on an entry, a club is wrong, a bout is called
    out of order. Editing them changes what the SCREENS say, never the draw.

    It holds no truth. Every button posts an intention, the server applies the
    rules, and the reply is the new state — byte-identical to what the hall
    screens receive over MQTT, so two officials cannot disagree.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ __('event-karate_tournament::messages.ctl_title') }} · {{ $event->title }}</title>

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

  html,body{margin:0;padding:0;background:#0a0b10;overflow:hidden;}
  *{box-sizing:border-box;}
  input,button,textarea,select{font-family:'Barlow Condensed',sans-serif;}
  button{transition:transform .1s,filter .15s;}
  button:hover{filter:brightness(1.2);}
  button:active{transform:scale(.94);}
  [hidden]{display:none !important;}

  @keyframes cardIn{0%{transform:translateY(50px);opacity:0;}100%{transform:translateY(0);opacity:1;}}
  @keyframes barIn{0%{transform:translateY(-40px);opacity:0;}100%{transform:translateY(0);opacity:1;}}
  @keyframes popNum{0%{transform:scale(1);}35%{transform:scale(1.35);color:#ffd666;}100%{transform:scale(1);}}
  @keyframes timerPulseCtl{0%,100%{color:#ff3b47;text-shadow:0 0 30px rgba(255,59,71,.5);}50%{color:#ffc2c6;text-shadow:0 0 60px rgba(255,59,71,.95);}}
  @keyframes goldGlow{0%,100%{box-shadow:0 0 0 0 rgba(255,214,102,0);}50%{box-shadow:0 0 30px 4px rgba(255,214,102,.55);}}
  @keyframes runningPulse{0%,100%{box-shadow:0 0 0 0 rgba(122,229,130,0);}50%{box-shadow:0 0 34px 4px rgba(122,229,130,.45);}}
  @keyframes ambient{0%{background-position:0% 50%;}50%{background-position:100% 50%;}100%{background-position:0% 50%;}}
  @keyframes orbDriftA{0%,100%{transform:translate(0,0) scale(1);}33%{transform:translate(120px,60px) scale(1.15);}66%{transform:translate(-60px,110px) scale(.95);}}
  @keyframes orbDriftB{0%,100%{transform:translate(0,0) scale(1);}33%{transform:translate(-140px,-50px) scale(1.1);}66%{transform:translate(70px,-100px) scale(.9);}}
  @keyframes breathRed{0%,100%{box-shadow:0 0 0 0 rgba(179,18,31,0);}50%{box-shadow:0 -6px 44px -6px rgba(179,18,31,.45);}}
  @keyframes breathBlue{0%,100%{box-shadow:0 0 0 0 rgba(13,85,184,0);}50%{box-shadow:0 -6px 44px -6px rgba(13,85,184,.5);}}
  @keyframes statusSwap{0%{transform:translateY(14px);opacity:0;letter-spacing:.5em;}100%{transform:translateY(0);opacity:1;letter-spacing:.28em;}}
  @keyframes chipPop{0%{transform:scale(.4) rotate(-6deg);opacity:0;}60%{transform:scale(1.15) rotate(2deg);}100%{transform:scale(1) rotate(0);opacity:1;}}
  @keyframes titleSweep{0%{transform:translateX(-110%);}60%,100%{transform:translateX(320%);}}
  @keyframes confettiFall{0%{transform:translateY(-90px) rotate(0deg);opacity:1;}100%{transform:translateY(1200px) rotate(760deg);opacity:.75;}}
  /* The translate is carried through every step on purpose: a keyframe that
     animates `transform` REPLACES the element's own translate(-50%,-50%), and
     with fill-mode `both` the stamp would keep scale(1) and lose its centring
     for good — it hangs off the middle of the screen. Same flaw in the source. */
  @keyframes stampIn{0%{transform:translate(-50%,-50%) scale(2.6);opacity:0;filter:blur(10px);}45%{transform:translate(-50%,-50%) scale(.96);opacity:1;filter:blur(0);}60%{transform:translate(-50%,-50%) scale(1.04);}100%{transform:translate(-50%,-50%) scale(1);opacity:1;}}

  /* The console is authored at 1920 wide and only SCALES — same rule as the wall
     screens, so a 13" laptop at the mat gets the whole layout.
     Its HEIGHT is whatever the layout needs (min 1080), not a hard 1080: the
     stage clips, so anything past 1080 was simply gone — the country fields and
     the row under Reset match were sliced off even on a 1080p screen, and every
     control added here made it worse. fit() measures what the layout actually
     came out to and scales that, so the console cannot outgrow its own glass. */
  #root{position:absolute;inset:0;overflow:hidden;background:#0a0b10;}
  #stage{position:absolute;left:50%;top:50%;width:1920px;min-height:1080px;
         transform:translate(-50%,-50%) scale(var(--stage-scale,0.6));transform-origin:center;}

  .fld{font-family:'Barlow Condensed',sans-serif;font-size:21px;background:#12141d;color:#e8eaf2;border:1px solid #1f2230;border-radius:10px;padding:12px;width:100%;min-width:0;}
  .lbl{font-size:16px;letter-spacing:.18em;color:#7d8296;text-transform:uppercase;}
  .col{display:flex;flex-direction:column;gap:6px;}
  .sbtn{font-size:27px;font-weight:700;color:#fff;border:none;border-radius:12px;padding:13px 0;cursor:pointer;}
  /* Nothing that acts on a bout is offered while the mat is empty — pressing
     Hajime with nothing loaded used to put a blank scoreboard on the wall. */
  .bout[disabled]{opacity:.35;cursor:not-allowed;filter:none;}
</style>

{{-- The winner celebration — this package's own. The same scene the wall
     shows, with this console's own two controls dropped into its one slot. --}}
@include('event-karate_tournament::scoreboard.winner-celebration')
</head>
<body>

<div id="root"><div id="stage" style="background:linear-gradient(120deg,#0a0b10 0%,#10121c 30%,#0a0b10 55%,#12101a 80%,#0a0b10 100%);background-size:300% 300%;animation:ambient 18s ease-in-out infinite;font-family:'Barlow Condensed',sans-serif;color:#e8eaf2;display:flex;flex-direction:column;gap:22px;padding:26px 36px 22px;overflow:hidden;position:relative;">

  <div style="position:absolute;left:-120px;top:220px;width:520px;height:520px;border-radius:50%;background:radial-gradient(circle,rgba(179,18,31,.14),transparent 70%);filter:blur(30px);animation:orbDriftA 16s ease-in-out infinite;pointer-events:none;"></div>
  <div style="position:absolute;right:-120px;top:180px;width:560px;height:560px;border-radius:50%;background:radial-gradient(circle,rgba(13,85,184,.16),transparent 70%);filter:blur(30px);animation:orbDriftB 19s ease-in-out infinite;pointer-events:none;"></div>

  {{-- ── Top bar ────────────────────────────────────────────────────────── --}}
  <div style="display:flex;align-items:flex-end;justify-content:space-between;gap:28px;animation:barIn .6s cubic-bezier(.2,.8,.2,1) both;">
    <div style="display:flex;flex-direction:column;gap:2px;flex-shrink:0;">
      <div style="font-family:'Anton',sans-serif;font-size:34px;letter-spacing:.04em;text-transform:uppercase;position:relative;overflow:hidden;">Match Control<span style="position:absolute;top:0;bottom:0;left:0;width:36%;background:linear-gradient(90deg,transparent,rgba(255,214,102,.35),transparent);transform:skewX(-18deg);animation:titleSweep 5s 1.2s ease-in-out infinite;"></span></div>
      <div style="font-size:18px;color:#7d8296;letter-spacing:.18em;text-transform:uppercase;">WKF Kumite</div>
    </div>

    <div style="flex:1;min-width:0;display:grid;grid-template-columns:2fr 1.6fr .7fr .7fr 1.2fr;gap:12px;">
      <label class="col"><span class="lbl">Tournament</span><input id="fTournament" class="fld" data-meta="tournament"></label>
      <label class="col"><span class="lbl">Category</span><input id="fCategory" class="fld" data-meta="division"></label>
      <label class="col"><span class="lbl">Match</span><input id="fMatchNo" class="fld" data-meta="matchNo"></label>
      <label class="col"><span class="lbl">Tatami</span><input id="fCourt" class="fld" data-meta="courtLabel"></label>
      <label class="col"><span class="lbl">Round</span>
        {{-- The design's own select, kept: this console is not product UI, and
             Design Rule #4's ban on native selects is about the app's styled
             surfaces. Restyling it would be changing the approved layout. --}}
        <select id="fRound" class="fld" data-meta="stage" style="padding:13px 12px;cursor:pointer;">
          <option>Elimination</option><option>Quarterfinal</option><option>Semifinal</option><option>Bronze Medal</option><option>Final</option>
        </select>
      </label>
    </div>

    <div style="display:flex;gap:10px;flex-shrink:0;">
      {{-- The event's sounds, from the table. Whoever finds out the hall is
           silent is the person standing here ten minutes before the first bout,
           holding the only device that matters — so the setting is here and not
           only on the organiser's laptop. --}}
      <button id="btnSound" style="font-size:18px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;background:#12141d;color:#ffd666;border:1px solid rgba(255,214,102,.5);border-radius:10px;padding:13px 16px;white-space:nowrap;cursor:pointer;">{{ __('event-karate_tournament::messages.ctl_sound') }}</button>
      <button id="btnBracket" style="font-size:18px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;background:#12141d;color:#7ae582;border:1px solid rgba(122,229,130,.5);border-radius:10px;padding:13px 16px;white-space:nowrap;cursor:pointer;">Bracket</button>
      @if ($screenUrl)
        <a href="{{ $screenUrl }}" target="_blank" rel="noopener" style="font-size:18px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;text-decoration:none;color:#ffd666;border:1px solid rgba(255,214,102,.5);border-radius:10px;padding:13px 16px;white-space:nowrap;display:flex;align-items:center;">Scoreboard ↗</a>
      @endif
    </div>
  </div>

  {{-- ── AKA | timer | AO ───────────────────────────────────────────────── --}}
  <div style="flex:1;display:grid;grid-template-columns:1fr 500px 1fr;gap:22px;min-height:0;">

    @foreach ([['aka','AKA','#b3121f','breathRed','1','.1s'], ['ao','AO','#0d55b8','breathBlue','3','.35s']] as [$side, $label, $colour, $breath, $order, $delay])
    <div style="background:#12141d;border:1px solid #1f2230;border-top:8px solid {{ $colour }};border-radius:18px;padding:24px 32px;display:flex;flex-direction:column;gap:18px;order:{{ $order }};animation:cardIn .7s {{ $delay }} cubic-bezier(.2,.8,.2,1) both,{{ $breath }} 3.4s 1.2s ease-in-out infinite;">
      <div style="display:flex;align-items:center;justify-content:space-between;">
        <div style="font-size:30px;font-weight:700;letter-spacing:.28em;color:{{ $colour }};text-transform:uppercase;">{{ $label }}</div>
        <div id="score-{{ $side }}" style="font-family:'Anton',sans-serif;font-size:96px;line-height:1;font-variant-numeric:tabular-nums;">0</div>
      </div>

      <div style="display:flex;flex-direction:column;gap:12px;">
        <button class="sbtn bout" style="background:{{ $colour }};" data-cmd="point" data-side="{{ $side }}" data-n="1">Yuko +1</button>
        <button class="sbtn bout" style="background:{{ $colour }};" data-cmd="point" data-side="{{ $side }}" data-n="2">Waza-ari +2</button>
        <button class="sbtn bout" style="background:{{ $colour }};" data-cmd="point" data-side="{{ $side }}" data-n="3">Ippon +3</button>
        <button class="sbtn bout" style="background:#1f2230;color:#e8eaf2;" data-cmd="undo_point" data-side="{{ $side }}" data-n="1">Correct −1</button>
      </div>

      <div style="display:flex;align-items:center;gap:16px;">
        <div style="font-size:19px;letter-spacing:.2em;color:#7d8296;text-transform:uppercase;width:104px;">Penalty</div>
        <div id="pen-{{ $side }}" style="display:flex;gap:10px;flex:1;"></div>
        <button style="font-size:28px;font-weight:700;background:#1f2230;color:#e8eaf2;border:none;border-radius:10px;padding:13px 24px;cursor:pointer;" data-cmd="penalty" data-side="{{ $side }}" data-dir="1" class="bout">+</button>
        <button style="font-size:28px;font-weight:700;background:#1f2230;color:#e8eaf2;border:none;border-radius:10px;padding:13px 24px;cursor:pointer;" data-cmd="penalty" data-side="{{ $side }}" data-dir="-1" class="bout">−</button>
      </div>

      <div style="display:flex;align-items:center;gap:16px;">
        <div style="font-size:19px;letter-spacing:.2em;color:#7d8296;text-transform:uppercase;width:104px;">Senshu</div>
        <button id="senshu-{{ $side }}" class="bout" style="font-size:24px;font-weight:700;letter-spacing:.16em;text-transform:uppercase;border-radius:10px;padding:13px 40px;cursor:pointer;transition:background .3s,color .3s;" data-cmd="senshu" data-side="{{ $side }}">Senshu</button>
      </div>

      <div style="flex:1;"></div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;border-top:1px solid #1f2230;padding-top:22px;">
        <label class="col"><span class="lbl" style="font-size:17px;">Name</span>
          <span style="display:flex;gap:8px;">
            <input id="name-{{ $side }}" class="fld" style="font-size:24px;background:#0a0b10;padding:13px 14px;" data-corner="name" data-side="{{ $side }}">
            {{-- A face for the athlete who is standing right here. Only the two
                 competitors on this mat can be photographed from this console,
                 and only when there is an entry behind the corner to attach it
                 to — a name typed at the table has nothing to hold a photo. --}}
            <label class="photoPick" style="flex:0 0 auto;display:grid;place-items:center;width:54px;background:#0a0b10;color:#ffd666;border:1px solid #1f2230;border-radius:10px;cursor:pointer;font-size:22px;" title="{{ __('event-karate_tournament::messages.ctl_photo') }}">
              <span data-photo-icon="{{ $side }}">◎</span>
              <input type="file" accept="image/*" class="photoInput" data-side="{{ $side }}" style="display:none;">
            </label>
          </span>
        </label>
        <label class="col"><span class="lbl" style="font-size:17px;">Club</span><input id="club-{{ $side }}" class="fld" style="font-size:24px;background:#0a0b10;padding:13px 14px;" data-corner="club" data-side="{{ $side }}"></label>
        <div style="grid-column:1 / -1;display:flex;flex-direction:column;gap:6px;"><span class="lbl" style="font-size:17px;">Country</span>
          <button style="display:flex;align-items:center;gap:14px;font-size:24px;background:#0a0b10;color:#e8eaf2;border:1px solid #1f2230;border-radius:10px;padding:10px 14px;cursor:pointer;text-align:left;" data-picker="{{ $side }}">
            <span id="flag-{{ $side }}" style="width:44px;height:30px;background-size:100% 100%; image-rendering:auto;background-position:center;border:1px solid rgba(255,255,255,.25);border-radius:4px;flex-shrink:0;"></span>
            <span id="country-{{ $side }}" style="flex:1;"></span>
            <span style="color:#7d8296;font-size:19px;letter-spacing:.1em;">CHANGE ▾</span>
          </button>
        </div>
      </div>
    </div>
    @endforeach

    {{-- Centre column --}}
    <div style="background:#12141d;border:1px solid #1f2230;border-radius:18px;padding:34px 30px;display:flex;flex-direction:column;align-items:center;gap:24px;order:2;animation:cardIn .7s .25s cubic-bezier(.2,.8,.2,1) both;">
      <div style="font-size:19px;letter-spacing:.32em;color:#7d8296;text-transform:uppercase;">Bout timer</div>
      <div id="timer" style="font-family:'Anton',sans-serif;font-size:132px;line-height:1.2;font-variant-numeric:tabular-nums;color:#e8eaf2;">3:00</div>
      <div id="status" style="font-size:32px;font-weight:700;letter-spacing:.28em;color:#ffd666;text-transform:uppercase;">Yame</div>

      <button id="btnStart" class="bout" style="width:100%;font-size:34px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;background:#7ae582;color:#000;border:none;border-radius:14px;padding:24px 0;cursor:pointer;transition:background .3s;">Hajime</button>
      {{-- Off the introduction without starting the bout. Hajime does both, and
           used to be the only way off the VS screen — so an introduction that had
           finished held the wall until the referee was ready, and an official who
           wanted the scoreboard up early had to start the clock to get it. --}}
      <button id="btnBoard" class="bout" style="width:100%;font-size:24px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;background:#12141d;color:#ffd666;border:1px solid rgba(255,214,102,.45);border-radius:14px;padding:14px 0;cursor:pointer;">{{ __('event-karate_tournament::messages.ctl_show_board') }}</button>
      <button id="btnKo" class="bout" style="width:100%;font-size:28px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;background:#12141d;color:#ff6b78;border:1px solid rgba(255,59,71,.5);border-radius:14px;padding:16px 0;cursor:pointer;">End bout</button>

      {{-- The approved layout gates this on `showTimeAdjust`, which it ships
           OFF — and that is not cosmetic: the centre column is sized so the
           console lands inside 1080, and this row is what tips it over into
           being clipped. Kept, not removed; enable it with ?adjust=1 and the
           clock nudges appear. --}}
      @if ($showTimeAdjust)
      <div style="width:100%;display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <button style="font-size:26px;font-weight:600;background:#1f2230;color:#e8eaf2;border:none;border-radius:12px;padding:18px 0;cursor:pointer;" data-nudge="-10" class="bout">−10s</button>
        <button style="font-size:26px;font-weight:600;background:#1f2230;color:#e8eaf2;border:none;border-radius:12px;padding:18px 0;cursor:pointer;" data-nudge="10" class="bout">+10s</button>
      </div>
      @endif

      <label style="width:100%;display:flex;flex-direction:column;gap:6px;"><span class="lbl" style="font-size:17px;">Bout duration (mm:ss)</span>
        <div style="display:flex;gap:12px;align-items:center;">
          <input id="durMin" type="number" min="0" max="59" value="3" style="flex:1;font-family:'Anton',sans-serif;font-size:32px;text-align:center;background:#1f2230;color:#e8eaf2;border:none;border-radius:12px;padding:12px 8px;">
          <div style="font-family:'Anton',sans-serif;font-size:32px;color:#7d8296;">:</div>
          <input id="durSec" type="number" min="0" max="59" value="0" style="flex:1;font-family:'Anton',sans-serif;font-size:32px;text-align:center;background:#1f2230;color:#e8eaf2;border:none;border-radius:12px;padding:12px 8px;">
          <button id="btnDuration" style="font-size:24px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;background:#1f2230;color:#ffd666;border:1px solid rgba(255,214,102,.4);border-radius:12px;padding:15px 24px;cursor:pointer;">Set</button>
        </div>
      </label>

      <label style="width:100%;display:flex;align-items:center;gap:12px;"><span style="flex:1;font-size:17px;letter-spacing:.14em;color:#7d8296;text-transform:uppercase;">Win by point gap<br><span style="font-size:14px;letter-spacing:.04em;text-transform:none;">0 = off · WKF rule: 8</span></span>
        <input id="gapWin" type="number" min="0" max="20" value="8" style="width:90px;font-family:'Anton',sans-serif;font-size:30px;text-align:center;background:#1f2230;color:#ffd666;border:none;border-radius:12px;padding:10px 6px;">
      </label>

      <div style="flex:1;"></div>
      <button id="btnReset" class="bout" style="width:100%;font-size:26px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;background:transparent;color:#ff6b78;border:1px solid rgba(255,107,120,.5);border-radius:12px;padding:18px 0;cursor:pointer;">Reset match</button>

      {{-- The recovery for a screen that has stopped following: it tells every
           board on this mat to reload itself. Deliberately NOT disabled with the
           rest of the bout controls (`class="bout"`) — a hung screen is most
           likely to need this when the mat is empty, and a button that greys out
           exactly when you reach for it is worse than no button. --}}
      <button id="btnResync" style="width:100%;font-size:22px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;background:#12141d;color:#8ab4ff;border:1px solid rgba(138,180,255,.45);border-radius:12px;padding:14px 0;cursor:pointer;">{{ __('event-karate_tournament::messages.ctl_resync') }}</button>
      <div style="font-size:16px;color:#5c6175;letter-spacing:.06em;text-align:center;">{{ __('event-karate_tournament::messages.ctl_resync_hint') }}</div>
    </div>
  </div>

  {{-- ── Winner overlay ─────────────────────────────────────────────────── --}}
  <div id="winner" hidden style="position:absolute;inset:0;z-index:15;background:rgba(0,0,0,.45);overflow:hidden;"></div>

  {{-- ── The event's sounds ─────────────────────────────────────────────── --}}
  {{-- Six slots, one row each. Uploading replaces: a hall does not want two
       celebration tracks, it wants the right one. Every screen on the mat is
       told to reload afterwards, because a board caches the file it fetched and
       would otherwise keep playing the old one. --}}
  <div id="sound" hidden style="position:fixed;inset:0;z-index:21;background:rgba(0,0,0,.72);display:flex;align-items:center;justify-content:center;">
    <div style="width:820px;max-height:88vh;overflow:auto;background:#12141d;border:1px solid #2a2e40;border-radius:18px;padding:28px;display:flex;flex-direction:column;gap:16px;box-shadow:0 30px 100px rgba(0,0,0,.7);animation:cardIn .25s cubic-bezier(.2,.8,.2,1) both;">
      <div style="display:flex;align-items:center;justify-content:space-between;">
        <div style="font-family:'Anton',sans-serif;font-size:28px;letter-spacing:.04em;text-transform:uppercase;color:#ffd666;">{{ __('event-karate_tournament::messages.ctl_sound_title') }}</div>
        <button data-close="sound" style="font-size:24px;font-weight:700;background:#1f2230;color:#e8eaf2;border:none;border-radius:8px;padding:6px 16px;cursor:pointer;">✕</button>
      </div>

      <div style="font-size:17px;color:#7d8296;letter-spacing:.04em;">{{ __('event-karate_tournament::messages.ctl_sound_hint') }}</div>

      @foreach ([
          'vs_music' => __('events.screen_audio_vs_music'),
          'winner_music' => __('events.screen_audio_winner_music'),
          'point_1' => __('events.screen_audio_point_1'),
          'point_2' => __('events.screen_audio_point_2'),
          'point_3' => __('events.screen_audio_point_3'),
          'foul' => __('events.screen_audio_foul'),
      ] as $slot => $label)
        <div style="display:flex;align-items:center;gap:16px;border-top:1px solid #1f2230;padding-top:14px;">
          <div style="flex:1;min-width:0;">
            <div style="font-size:23px;font-weight:600;color:#e8eaf2;">{{ $label }}</div>
            <div class="audioName" data-slot="{{ $slot }}" style="font-size:17px;color:#5c6175;margin-top:2px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ __('events.screen_audio_none') }}</div>
          </div>

          <label style="font-size:19px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;background:#7ae582;color:#000;border-radius:11px;padding:12px 22px;cursor:pointer;white-space:nowrap;">
            {{ __('events.screen_audio_choose') }}
            <input type="file" accept="audio/*" class="audioPick" data-slot="{{ $slot }}" style="display:none;">
          </label>

          <button class="audioDrop" data-slot="{{ $slot }}" hidden style="font-size:19px;font-weight:700;background:#1f2230;color:#ff6b78;border:1px solid rgba(255,107,120,.45);border-radius:11px;padding:12px 18px;cursor:pointer;">✕</button>
        </div>
      @endforeach
    </div>
  </div>

  {{-- ── Ending a bout: on points, or by decision ───────────────────────── --}}
  {{-- A bout does not always go to the higher score. Hansoku and shikkaku hand
       it to the other side however the points stand; kiken and a doctor's call
       hand it over with no points at all. So "End bout" asks, instead of
       assuming — and the manual path REQUIRES a reason, because "the athlete
       with fewer points won" is indistinguishable from a mistake without one. --}}
  <div id="endBout" hidden style="position:fixed;inset:0;z-index:22;background:rgba(0,0,0,.72);display:flex;align-items:center;justify-content:center;">
    <div style="width:760px;background:#12141d;border:1px solid #2a2e40;border-radius:18px;padding:28px;display:flex;flex-direction:column;gap:20px;box-shadow:0 30px 100px rgba(0,0,0,.7);animation:cardIn .25s cubic-bezier(.2,.8,.2,1) both;">
      <div style="display:flex;align-items:center;justify-content:space-between;">
        <div style="font-family:'Anton',sans-serif;font-size:28px;letter-spacing:.04em;text-transform:uppercase;color:#ffd666;">{{ __('event-karate_tournament::messages.end_title') }}</div>
        <button data-close="endBout" style="font-size:24px;font-weight:700;background:#1f2230;color:#e8eaf2;border:none;border-radius:8px;padding:6px 16px;cursor:pointer;">✕</button>
      </div>

      {{-- The default, and what it will do — named, so nobody has to remember
           who is ahead while looking at a dialog. --}}
      <button id="endAuto" style="display:flex;flex-direction:column;gap:6px;text-align:left;background:#0f1a12;border:1px solid rgba(122,229,130,.45);border-radius:14px;padding:18px 22px;cursor:pointer;">
        <span style="font-size:24px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#7ae582;">{{ __('event-karate_tournament::messages.end_auto') }}</span>
        <span id="endAutoWho" style="font-size:19px;color:#a7abbe;"></span>
      </button>

      <div style="height:1px;background:#2a2e40;"></div>

      <div style="font-size:19px;font-weight:600;letter-spacing:.14em;text-transform:uppercase;color:#7d8296;">{{ __('event-karate_tournament::messages.end_manual') }}</div>

      {{-- Selection cards, not a dropdown: this is chosen once, under pressure,
           on a touch screen at the mat. --}}
      <div style="display:flex;gap:14px;">
        <button class="endSide" data-side="aka" style="flex:1;font-size:26px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;background:#1f2230;color:#ff6b78;border:2px solid transparent;border-radius:14px;padding:16px 0;cursor:pointer;">
          AKA <span class="endSideName" style="display:block;font-size:17px;font-weight:600;letter-spacing:.02em;text-transform:none;color:#a7abbe;"></span>
        </button>
        <button class="endSide" data-side="ao" style="flex:1;font-size:26px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;background:#1f2230;color:#8ab4ff;border:2px solid transparent;border-radius:14px;padding:16px 0;cursor:pointer;">
          AO <span class="endSideName" style="display:block;font-size:17px;font-weight:600;letter-spacing:.02em;text-transform:none;color:#a7abbe;"></span>
        </button>
      </div>

      <div style="display:flex;flex-wrap:wrap;gap:10px;">
        @foreach (['hansoku', 'shikkaku', 'kiken', 'medical', 'no_show', 'other'] as $reason)
          <button class="endReason" data-reason="{{ $reason }}" style="font-size:20px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;background:#1f2230;color:#e8eaf2;border:2px solid transparent;border-radius:11px;padding:12px 20px;cursor:pointer;">{{ __('event-karate_tournament::messages.end_reason_'.$reason) }}</button>
        @endforeach
      </div>

      <input id="endNote" type="text" maxlength="200" placeholder="{{ __('event-karate_tournament::messages.end_note') }}" class="fld" style="font-size:20px;">

      <button id="endDeclare" disabled style="font-size:26px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;background:#ff6b78;color:#000;border:none;border-radius:14px;padding:18px 0;cursor:pointer;opacity:.4;">{{ __('event-karate_tournament::messages.end_declare') }}</button>
      <div id="endWhy" style="font-size:17px;color:#5c6175;letter-spacing:.04em;text-align:center;">{{ __('event-karate_tournament::messages.end_hint') }}</div>
    </div>
  </div>

  {{-- ── Bracket: this event's own running order ────────────────────────── --}}
  <div id="bracket" hidden style="position:fixed;inset:0;z-index:20;background:rgba(0,0,0,.7);display:flex;align-items:center;justify-content:center;">
    <div id="bracketPanel" style="width:860px;max-height:880px;background:#12141d;border:1px solid #2a2e40;border-radius:18px;padding:28px;display:flex;flex-direction:column;gap:18px;box-shadow:0 30px 100px rgba(0,0,0,.7);animation:cardIn .25s cubic-bezier(.2,.8,.2,1) both;">
      <div style="display:flex;align-items:center;justify-content:space-between;">
        <div style="font-family:'Anton',sans-serif;font-size:26px;letter-spacing:.04em;text-transform:uppercase;color:#7ae582;">Bracket connection</div>
        <button data-close="bracket" style="font-size:24px;font-weight:700;background:#1f2230;color:#e8eaf2;border:none;border-radius:8px;padding:6px 16px;cursor:pointer;">✕</button>
      </div>
      {{-- The original fetched a bracket from an external tournament system. It
           does not have to: the draw is already here, so this reads the event's
           own running order for this mat. The field stays, showing where from. --}}
      <div style="display:flex;gap:12px;">
        <input id="apiUrl" class="fld" style="flex:1;font-size:22px;background:#0a0b10;border-color:#2a2e40;padding:14px 16px;" readonly>
        <button id="btnFetch" style="font-size:22px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;background:#7ae582;color:#000;border:none;border-radius:12px;padding:14px 28px;cursor:pointer;">Refresh</button>
      </div>
      <div id="bracketList" style="flex:1;overflow-y:auto;display:flex;flex-direction:column;gap:8px;min-height:160px;"></div>
      <div style="font-size:17px;color:#5c6175;">Click a match to load it onto the board. You can still edit everything manually afterwards.</div>
    </div>
  </div>

  {{-- ── Country picker ─────────────────────────────────────────────────── --}}
  <div id="picker" hidden style="position:fixed;inset:0;z-index:20;background:rgba(0,0,0,.7);display:flex;align-items:center;justify-content:center;">
    <div id="pickerPanel" style="width:620px;max-height:820px;background:#12141d;border:1px solid #2a2e40;border-radius:18px;padding:28px;display:flex;flex-direction:column;gap:18px;box-shadow:0 30px 100px rgba(0,0,0,.7);animation:cardIn .25s cubic-bezier(.2,.8,.2,1) both;">
      <div style="display:flex;align-items:center;justify-content:space-between;">
        <div id="pickerTitle" style="font-family:'Anton',sans-serif;font-size:26px;letter-spacing:.04em;text-transform:uppercase;"></div>
        <button data-close="picker" style="font-size:24px;font-weight:700;background:#1f2230;color:#e8eaf2;border:none;border-radius:8px;padding:6px 16px;cursor:pointer;">✕</button>
      </div>
      <input id="pickerQuery" class="fld" style="font-size:28px;background:#0a0b10;border-color:#2a2e40;padding:16px 18px;" placeholder="Type a country name…">
      <div id="pickerResults" style="flex:1;overflow-y:auto;display:flex;flex-direction:column;gap:6px;min-height:200px;"></div>
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
  var PEN = @json(\App\Events\Sports\Karate\Tournament\Scoreboard\MatState::PENALTIES);
  var COUNTRIES = @json($countries);

  var el = function (id) { return document.getElementById(id); };
  var root = el('root');

  function fit() {
    var r = root.getBoundingClientRect();
    if (!r.width || !r.height) return;

    // offsetHeight is the LAID-OUT height and is unaffected by the transform, so
    // this reads the true size of the console however tall it turned out —
    // rather than trusting a 1080 that stopped being true the moment anything
    // was added to it.
    var stage = document.getElementById('stage');
    var tall = Math.max(stage ? stage.offsetHeight : 1080, 1080);

    root.style.setProperty('--stage-scale', Math.min(r.width / 1920, r.height / tall));
  }

  // Watched on the STAGE as well as the root: the console grows and shrinks on
  // its own (a queue arrives, the bracket panel opens, a round name wraps), and
  // a re-fit has to follow the content, not only the window.
  if (window.ResizeObserver) {
    var ro = new ResizeObserver(fit);
    ro.observe(root);
    var stageEl = document.getElementById('stage');
    if (stageEl) ro.observe(stageEl);
  } else {
    window.addEventListener('resize', fit);
  }
  fit();

@isset($heartbeatUrl)
  // A paired console is a SCREEN and is listed beside the boards with a live
  // dot. Commands alone would show a mat waiting twenty minutes for the next
  // bout as offline — the opposite of the truth, and exactly when an organiser
  // is checking. So it beats.
  //
  // It also notices being UNPAIRED and goes back to its code, like the boards.
  //
  // That reload was removed for a while because it was a trap: the control URL
  // answered an unpaired device with a refusal, so the page reloaded into an
  // error once a minute with no way out. It is safe now — tokenControl() sends
  // a console it cannot open to the board address, which shows the pairing QR
  // — and without it an unpaired scoring table just sat there still showing
  // the console, which is worse: the mat looks staffed when it is not.
  // Deliberately still a minute, unlike the boards.
  //
  // This console's commands go through the SAME per-token rate limit as this
  // beat (60/min), and an official scoring a busy bout can spend thirty of those
  // in a minute. Twelve more for a heartbeat is how a scoring table starts
  // getting 429s mid-bout, and a mat that will not accept a point is far worse
  // than a console that takes a minute to notice it was unpaired — which is a
  // thing a human is looking straight at anyway.
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
                 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
      credentials: 'same-origin',
      body: JSON.stringify(Object.assign({ mat: MAT, command: command }, payload || {})),
    }).then(function (r) { return r.json().catch(function () { return {}; }); })
      .then(function (d) {
        if (!d.success) throw new Error(d.message || @json(__('event-karate_tournament::messages.ctl_failed')));
        STATE = d.state; QUEUE = d.queue; received = performance.now();
        paint();
      })
      .catch(function (e) { alertBar(e.message); })
      .finally(function () { busy = false; });
  }

  // No toast library on this document — it does not extend a layout. A bar
  // across the top is louder anyway, which is right at a mat.
  // `ok` turns it green. A refusal and a confirmation both have to be readable
  // at arm's length from a mat, and the difference between them cannot be that
  // one is a toast in the corner.
  function alertBar(msg, ok) {
    var b = document.createElement('div');
    b.style.cssText = 'position:fixed;top:0;left:0;right:0;z-index:40;background:' + (ok ? '#1f7a3d' : '#ff3b47') +
      ';color:#fff;font-size:22px;' +
      'font-weight:700;letter-spacing:.08em;text-align:center;padding:14px;';
    b.textContent = msg;
    document.body.appendChild(b);
    setTimeout(function () { b.remove(); }, 4000);
  }

  /* ── The clock, derived exactly as the wall derives it ─────────────────── */
  var received = performance.now();
  function liveRemaining() {
    if (!STATE.running) return STATE.remaining || 0;
    return Math.max(0, STATE.remaining - (performance.now() - received) / 1000);
  }
  function paintClock() {
    var t = liveRemaining(), over = STATE.finished || t <= 0;
    var low = t <= 15 && t > 0 && STATE.running;
    el('timer').textContent = Math.floor(t / 60) + ':' + String(Math.floor(t % 60)).padStart(2, '0');
    el('timer').style.animation = low ? 'timerPulseCtl 1s ease-in-out infinite' : 'none';
    el('timer').style.color = low ? '' : '#e8eaf2';
    el('status').textContent = over ? 'Time' : (STATE.running ? 'Hajime' : 'Yame');
    el('btnStart').textContent = STATE.running ? 'Yame' : 'Hajime';
    el('btnStart').style.background = STATE.running ? '#ffd666' : '#7ae582';
    el('btnStart').style.animation = STATE.running ? 'runningPulse 2s ease-in-out infinite' : 'none';
  }
  setInterval(paintClock, 100);

  /* ── The rest of the console ───────────────────────────────────────────── */
  var CHIP_ON = [{bg:'#ffd666',fg:'#000'},{bg:'#ffd666',fg:'#000'},{bg:'#ffd666',fg:'#000'},{bg:'#ff9636',fg:'#000'},{bg:'#ff3b47',fg:'#fff'}];

  function paintSide(side) {
    var c = STATE[side] || {};
    var score = el('score-' + side);
    if (score.textContent !== String(STATE[side + 'Score'])) {
      score.textContent = STATE[side + 'Score'];
      score.style.animation = 'none'; void score.offsetWidth;
      score.style.animation = 'popNum .45s cubic-bezier(.2,.8,.2,1)';
    }

    var host = el('pen-' + side);
    host.textContent = '';
    PEN.forEach(function (label, i) {
      var on = i < (STATE[side + 'Pen'] || 0), tone = CHIP_ON[i];
      var d = document.createElement('div');
      d.style.cssText = "flex:1;height:58px;border-radius:10px;display:flex;align-items:center;justify-content:center;" +
        "font-family:'Anton',sans-serif;font-size:24px;background:" + (on ? tone.bg : '#0a0b10') + ';' +
        'color:' + (on ? tone.fg : '#5c6175') + ';border:1px solid ' + (on ? 'transparent' : '#1f2230') + ';' +
        (on ? 'animation:chipPop .35s both;' : '');
      d.textContent = label;
      host.appendChild(d);
    });

    var sen = el('senshu-' + side), on = !!STATE[side + 'Senshu'];
    sen.style.background = on ? '#ffd666' : '#1f2230';
    sen.style.color = on ? '#000' : '#7d8296';
    sen.style.border = '1px solid ' + (on ? 'transparent' : '#2a2e40');
    sen.style.animation = on ? 'goldGlow 2.2s ease-in-out infinite' : 'none';

    // Inputs are only rewritten when the operator is not in them, or typing
    // would fight with the server's reply on every keystroke.
    var nameEl = el('name-' + side), clubEl = el('club-' + side);
    if (document.activeElement !== nameEl) nameEl.value = c.name || '';
    if (document.activeElement !== clubEl) clubEl.value = c.club || '';
    el('country-' + side).textContent = c.country || '';
    el('flag-' + side).style.backgroundImage = /^[a-z]{2}$/.test(c.flag || '')
      ? 'url("https://flagcdn.com/w1280/' + c.flag + '.png")' : 'none';
  }

  // The celebration: which bout it belongs to, whether the official has tucked
  // it away, and what is currently on the glass.
  //
  // It used to be modal with exactly one way out — the commit button — so an
  // official who wanted to look at the console again (check a score, read the
  // queue, fix a spelling before filing) had no choice but to record the result
  // and call the next bout. That is a write, and it is irreversible from here.
  // Now the celebration can be closed and reopened, and closing it writes
  // NOTHING: the bout stays finished, the result stays unfiled, and the small
  // plate in the corner is what says so.
  // `winnerClosed` is set optimistically on the click so the console responds
  // instantly, then reconciled from STATE on the next reply — the server's
  // answer wins, because the wall is drawing from the same flag.
  @php
      // Pre-assigned, never inline: Blade's bracket matcher chokes on an array
      // literal inside @json().
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

  var winnerFor = null, winnerClosed = false, winnerPainted = null;

  var WINNER_FULL = 'position:absolute;inset:0;z-index:15;background:rgba(0,0,0,.45);overflow:hidden;';
  var WINNER_CHIP = 'position:absolute;inset:auto 22px 22px auto;z-index:15;background:none;overflow:visible;';

  function paintWinner() {
    var host = el('winner');
    var live = STATE.finished && (STATE.akaLeads || STATE.aoLeads) && STATE.matchId;

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

    // Forgets the standing scene as well as the DOM, so switching between the
    // corner plate and the celebration always restages rather than handing the
    // caller buttons to hang on a detached node.
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
      who.style.cssText = 'display:flex;flex-direction:column;align-items:flex-start;gap:2px;background:none;border:none;' +
        'cursor:pointer;text-align:left;padding:0;font-family:\'Barlow Condensed\',sans-serif;';
      var lbl = document.createElement('span');
      lbl.style.cssText = 'font-size:13px;font-weight:600;letter-spacing:.24em;text-transform:uppercase;color:#ffd666;';
      lbl.textContent = @json(__('event-karate_tournament::messages.ctl_result_pending'));
      var nm = document.createElement('span');
      nm.style.cssText = 'font-size:26px;font-weight:700;line-height:1;color:#fff;text-transform:uppercase;';
      nm.textContent = name;
      who.appendChild(lbl); who.appendChild(nm);
      // Reopening is free — it shows the same celebration again, unfiled.
      // Reopening is free, and it brings the wall back with it.
      who.onclick = function () { winnerClosed = false; paintWinner(); send('celebrate'); };

      var file = document.createElement('button');
      file.id = 'btnCommit';
      file.style.cssText = 'font-family:\'Barlow Condensed\',sans-serif;font-size:19px;font-weight:700;letter-spacing:.08em;' +
        'text-transform:uppercase;background:#7ae582;color:#000;border:none;border-radius:11px;padding:12px 22px;cursor:pointer;';
      file.textContent = @json(__('event-karate_tournament::messages.ctl_commit'));
      file.onclick = function () { send('commit'); };

      chip.appendChild(who); chip.appendChild(file);
      host.appendChild(chip);

      return;
    }

    // ── Open: the celebration ────────────────────────────────────────────────
    // The same scene the hall is looking at (components/winner-celebration), so
    // the official and the wall agree about what just happened. The console adds
    // the only two things the wall has no use for: file it, or put it away.
    host.style.cssText = WINNER_FULL;

    var scene = WinnerCelebration.paint(host, {
      corner: aka ? 'red' : 'blue',
      name: name,
      club: c.club || '',
      logo: c.logo || null,
      photo: c.photo || null,
      label: @json(__('event-karate_tournament::messages.sb_winner')),
      note: (STATE.winReason && STATE.winReason !== 'points')
        ? WON_BY.replace(':reason', REASONS[STATE.winReason] || STATE.winReason) : ''
    });

    // The only control here that WRITES: it records the result, advances the
    // bracket and calls the next bout. The celebration otherwise sits there
    // until an official decides the bout is genuinely over — a scoreboard that
    // files a result on a timer files it before anyone has looked at it.
    var go = document.createElement('button');
    go.id = 'btnCommit';
    go.style.cssText = 'font-family:\'Barlow Condensed\',sans-serif;font-size:30px;font-weight:700;' +
      'letter-spacing:.1em;text-transform:uppercase;background:#7ae582;color:#000;border:none;border-radius:14px;' +
      'padding:20px 56px;cursor:pointer;animation:runningPulse 2s ease-in-out infinite;';
    go.textContent = @json(__('event-karate_tournament::messages.ctl_commit'));
    go.onclick = function () { send('commit'); };

    // The way out that is NOT a write. The same button as the green one — same
    // size, same weight, same corners — and quiet only in its COLOUR: two
    // controls side by side at a mat should look like two controls, not one
    // control and an afterthought. The padding is a pixel short on each side
    // because this one carries a border and the green one does not, so the two
    // stand the same height on the glass.
    var close = document.createElement('button');
    // A FILLED button, not a ghost one. Six percent white was invisible against
    // the celebration behind it — a control an official has to hunt for is not a
    // control. Slate carries its own weight next to the green without competing
    // with it, and the gold accents on this console stay unique to the result.
    close.style.cssText = 'font-family:\'Barlow Condensed\',sans-serif;font-size:30px;font-weight:700;' +
      'letter-spacing:.1em;text-transform:uppercase;background:linear-gradient(135deg,#3c4460,#1b1f2e);' +
      'color:#eef1f8;border:1px solid rgba(255,255,255,.38);border-radius:14px;padding:19px 55px;' +
      'box-shadow:0 12px 34px rgba(0,0,0,.5);cursor:pointer;';
    close.textContent = @json(__('event-karate_tournament::messages.ctl_dismiss'));
    // Closes it HERE and on every screen on this mat: `dismiss` writes the flag
    // into the shared mat state and the boards redraw from it. It does not touch
    // the bout or the result — the confetti stops, nothing is filed.
    close.onclick = function () { winnerClosed = true; paintWinner(); send('dismiss'); };

    var hint = document.createElement('div');
    hint.style.cssText = 'pointer-events:none;font-size:17px;color:#a7abbe;letter-spacing:.06em;max-width:620px;';
    hint.textContent = @json(__('event-karate_tournament::messages.ctl_commit_hint'));

    scene.actions.appendChild(go);
    scene.actions.appendChild(close);
    scene.actions.appendChild(hint);
  }

  // Escape closes it too. An official at a mat has a keyboard as often as not,
  // and the muscle memory for "get this off my screen" is the same everywhere.
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape' || winnerClosed || winnerFor === null) return;
    winnerClosed = true;
    paintWinner();
    send('dismiss');
  });


  function paintMeta() {
    var f = [['fTournament', STATE.tournament || EVENT_TITLE], ['fCategory', STATE.division || ''],
             ['fMatchNo', STATE.matchNo || ''], ['fCourt', STATE.courtLabel || MAT]];
    f.forEach(function (p) { if (document.activeElement !== el(p[0])) el(p[0]).value = p[1]; });
    if (STATE.stage) {
      var sel = el('fRound'), want = STATE.stage.toLowerCase(), found = false;
      for (var i = 0; i < sel.options.length; i++) {
        if (sel.options[i].text.toLowerCase() === want) { sel.selectedIndex = i; found = true; break; }
      }
      // A round the design's list does not contain (the engine names its own
      // phases) is added rather than dropped — the board must say what the draw
      // says, not the nearest option.
      if (!found) { var o = document.createElement('option'); o.text = STATE.stage; sel.add(o); sel.value = STATE.stage; }
    }
  }

  function paint() { paintMeta(); paintSide('aka'); paintSide('ao'); paintWinner(); paintClock(); paintQueue(); checkGap(); paintArmed(); paintBoardToggle(); }

  /** The introduction/scoreboard toggle, labelled for what it will do next. */
  function paintBoardToggle() {
    var b = el('btnBoard');
    if (!b) return;

    var showingIntro = STATE.mode === 'vs';
    b.textContent = showingIntro
      ? @json(__('event-karate_tournament::messages.ctl_show_board'))
      : @json(__('event-karate_tournament::messages.ctl_show_intro'));

    // Gold going one way, dimmer coming back: putting the introduction up over a
    // stopped bout is the rarer act, and it should not look like the primary one.
    b.style.color = showingIntro ? '#ffd666' : '#8ab4ff';
    b.style.borderColor = showingIntro ? 'rgba(255,214,102,.45)' : 'rgba(138,180,255,.4)';
  }

  /** Everything that needs a bout goes dead while the mat is empty. */
  function paintArmed() {
    var armed = !!STATE.matchId;
    Array.prototype.forEach.call(document.querySelectorAll('.bout'), function (b) { b.disabled = !armed; });
    el('status').textContent = armed ? el('status').textContent : @json(__('event-karate_tournament::messages.ctl_waiting'));
  }

  /** WKF's eight-point gap ends a bout early. 0 turns it off. */
  function checkGap() {
    var gap = parseInt(el('gapWin').value, 10) || 0;
    if (!gap || !STATE.matchId || STATE.finished || !STATE.running) return;
    if (Math.abs(STATE.akaScore - STATE.aoScore) >= gap) send('finish');
  }

  /* ── Bracket: this event's running order for this mat ──────────────────── */
  function paintQueue() {
    var host = el('bracketList');
    host.textContent = '';
    if (!QUEUE.length) {
      var empty = document.createElement('div');
      empty.style.cssText = 'font-size:21px;color:#5c6175;padding:16px 4px;';
      empty.textContent = @json(__('event-karate_tournament::messages.ctl_no_queue'));
      host.appendChild(empty);
      return;
    }
    QUEUE.forEach(function (b) {
      var row = document.createElement('button');
      row.style.cssText = 'display:grid;grid-template-columns:90px 150px 1fr 40px 1fr;align-items:center;gap:14px;font-size:23px;' +
        'background:' + (b.id === STATE.matchId ? '#1f2230' : '#0a0b10') + ';color:#e8eaf2;border:1px solid #1f2230;' +
        'border-radius:12px;padding:14px 18px;cursor:pointer;text-align:left;';
      [["font-family:'Anton',sans-serif;color:#ffd666;", '#' + (b.number == null ? '—' : b.number)],
       ['color:#7d8296;text-transform:uppercase;font-size:19px;letter-spacing:.08em;', b.stage || ''],
       ['color:#ff6b78;', b.aka || @json(__('event-karate_tournament::messages.court_tbd'))],
       ['color:#5c6175;text-align:center;', 'vs'],
       ['color:#6ea8ff;', b.ao || @json(__('event-karate_tournament::messages.court_tbd'))]].forEach(function (p) {
        var s = document.createElement('span'); s.style.cssText = p[0]; s.textContent = p[1]; row.appendChild(s);
      });
      if (!b.runnable) {
        // Listed so the operator can see it coming, but not loadable.
        row.style.opacity = '.45';
        row.style.cursor = 'not-allowed';
        row.title = @json(__('event-karate_tournament::messages.ctl_waiting_feeder'));
        host.appendChild(row);
        return;
      }
      row.onclick = function () {
        var m = (parseInt(el('durMin').value, 10) || 0) + (parseInt(el('durSec').value, 10) || 0) / 60;
        send('load', { match_id: b.id, minutes: m > 0 ? m : 3 }).then(function () { el('bracket').hidden = true; });
      };
      host.appendChild(row);
    });
  }

  /* ── Country picker ───────────────────────────────────────────────────── */
  var pickerSide = null;
  function paintPicker() {
    var q = el('pickerQuery').value.trim().toLowerCase();
    var host = el('pickerResults');
    host.textContent = '';
    var hits = COUNTRIES.filter(function (c) { return !q || c.name.toLowerCase().indexOf(q) === 0 || c.code === q; }).slice(0, 60);
    if (!hits.length) {
      var e2 = document.createElement('div');
      e2.style.cssText = 'font-size:22px;color:#5c6175;padding:20px 14px;';
      e2.textContent = 'No country matches that name.';
      host.appendChild(e2); return;
    }
    hits.forEach(function (c) {
      var b = document.createElement('button');
      b.style.cssText = 'display:flex;align-items:center;gap:18px;font-size:26px;background:transparent;color:#e8eaf2;border:none;' +
        'border-radius:10px;padding:10px 14px;cursor:pointer;text-align:left;';
      var f = document.createElement('span');
      f.style.cssText = 'width:46px;height:32px;background-size:cover;background-position:center;border-radius:4px;flex-shrink:0;' +
        'background-image:url("https://flagcdn.com/w1280/' + c.code + '.png");';
      var n = document.createElement('span'); n.style.flex = '1'; n.textContent = c.name;
      var code = document.createElement('span'); code.style.cssText = 'color:#5c6175;font-size:19px;text-transform:uppercase;'; code.textContent = c.code;
      b.appendChild(f); b.appendChild(n); b.appendChild(code);
      b.onclick = function () {
        el('picker').hidden = true;
        send('corner', { side: pickerSide, country: c.name, flag: c.code });
      };
      host.appendChild(b);
    });
  }

  /* ── Wiring ───────────────────────────────────────────────────────────── */
  document.addEventListener('click', function (e) {
    var t = e.target.closest('[data-cmd],[data-nudge],[data-picker],[data-close]');
    if (!t) return;

    if (t.dataset.close) { el(t.dataset.close).hidden = true; return; }

    if (t.dataset.picker) {
      pickerSide = t.dataset.picker;
      el('pickerTitle').textContent = 'Select country — ' + pickerSide.toUpperCase();
      el('pickerTitle').style.color = pickerSide === 'aka' ? '#ff6b78' : '#6ea8ff';
      el('pickerQuery').value = '';
      el('picker').hidden = false;
      paintPicker(); el('pickerQuery').focus();
      return;
    }

    if (t.dataset.nudge) {
      send('time', { remaining: Math.max(0, liveRemaining() + parseInt(t.dataset.nudge, 10)) });
      return;
    }

    send(t.dataset.cmd, {
      side: t.dataset.side || null,
      n: t.dataset.n ? parseInt(t.dataset.n, 10) : null,
      dir: t.dataset.dir ? parseInt(t.dataset.dir, 10) : null,
    });
  });

  el('btnStart').onclick = function () { send(STATE.running ? 'pause' : 'start'); };
  // ── Ending a bout ─────────────────────────────────────────────────────────
  // Never straight to the server any more: who won is a decision, and on a
  // disqualification it is not the one the score would make.
  var endSide = null, endReason = null;

  function endPaint() {
    Array.prototype.forEach.call(document.querySelectorAll('.endSide'), function (b) {
      var on = b.getAttribute('data-side') === endSide;
      b.style.borderColor = on ? '#ffd666' : 'transparent';
      b.style.background = on ? '#2a2412' : '#1f2230';
    });
    Array.prototype.forEach.call(document.querySelectorAll('.endReason'), function (b) {
      var on = b.getAttribute('data-reason') === endReason;
      b.style.borderColor = on ? '#ffd666' : 'transparent';
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

    // Name the sides, so the choice is between two people rather than two
    // colours — and say what the automatic answer would be.
    var names = document.querySelectorAll('.endSide .endSideName');
    if (names[0]) names[0].textContent = (STATE.aka || {}).name || '';
    if (names[1]) names[1].textContent = (STATE.ao || {}).name || '';

    var auto = STATE.akaLeads ? ((STATE.aka || {}).name || 'AKA')
             : STATE.aoLeads ? ((STATE.ao || {}).name || 'AO')
             : null;
    el('endAutoWho').textContent = auto
      ? @json(__('event-karate_tournament::messages.end_auto_who')).replace(':name', auto)
      : @json(__('event-karate_tournament::messages.end_auto_level'));

    endPaint();
    el('endBout').hidden = false;
  }

  // One button, both directions. Which one it is comes from the STATE rather
  // than from a flag this page keeps: a second console on the same mat, a
  // reload, or the wall being switched by somebody else all have to leave this
  // button telling the truth about what it will do next.
  el('btnBoard').onclick = function () {
    send(STATE.mode === 'vs' ? 'board' : 'intro');
  };

  el('btnKo').onclick = endOpen;

  // On the score, exactly as before: no winner in the payload means finish()
  // clears any previous declaration and lets the points decide.
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
  el('btnReset').onclick = function () { send('reset'); };

  // Forces every screen on this mat to start again. Changes nothing about the
  // bout — worst case a board that was fine blinks and comes back identical.
  el('btnResync').onclick = function () { send('resync'); };

  // ── The event's sounds, and a face for a corner ───────────────────────────
  var AUDIO_BASE = @json($audioUploadBase ?? null);
  var PHOTO_BASE = @json($photoUploadBase ?? null);
  var AUDIO_SLOTS = @json($audioSlots ?? []);

  function csrf() { return document.querySelector('meta[name=csrf-token]').content; }

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

  Object.keys(AUDIO_SLOTS).forEach(paintAudioRow);
  ['vs_music', 'winner_music', 'point_1', 'point_2', 'point_3', 'foul'].forEach(paintAudioRow);

  el('btnSound').onclick = function () {
    // Hidden entirely when this console has no way to upload — an organiser's
    // laptop door that was not given an address should not offer a button that
    // cannot work.
    if (!AUDIO_BASE) { alertBar(@json(__('event-karate_tournament::messages.ctl_sound_unavailable'))); return; }
    el('sound').hidden = false;
  };

  // Delegated, because these rows are rendered by Blade and never rebuilt.
  document.addEventListener('change', function (e) {
    var pick = e.target.closest ? e.target.closest('.audioPick') : null;
    if (pick) { uploadAudio(pick.getAttribute('data-slot'), pick); return; }

    var photo = e.target.closest ? e.target.closest('.photoInput') : null;
    if (photo) uploadPhoto(photo.getAttribute('data-side'), photo);
  });

  document.addEventListener('click', function (e) {
    var drop = e.target.closest ? e.target.closest('.audioDrop') : null;
    if (drop) dropAudio(drop.getAttribute('data-slot'));
  });

  function uploadAudio(slot, input) {
    var file = input.files && input.files[0];
    if (!file || !AUDIO_BASE) return;

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
        if (!d.success) throw new Error(d.message || @json(__('event-karate_tournament::messages.ctl_failed')));
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
        if (!d.success) throw new Error(d.message || @json(__('event-karate_tournament::messages.ctl_failed')));
        delete AUDIO_SLOTS[slot];
        paintAudioRow(slot);
        alertBar(d.message, true);
      })
      .catch(function (err) { alertBar(err.message); });
  }

  function uploadPhoto(side, input) {
    var file = input.files && input.files[0];
    if (!file) return;

    if (!PHOTO_BASE) { alertBar(@json(__('event-karate_tournament::messages.ctl_photo_unavailable'))); input.value = ''; return; }

    // Refused here rather than after travelling: a phone's full-resolution shot
    // is comfortably over this on a venue's uplink.
    if (file.size > 10 * 1024 * 1024) { alertBar(@json(__('personal.event_photo_too_big'))); input.value = ''; return; }

    var icon = document.querySelector('[data-photo-icon="' + side + '"]');
    if (icon) icon.textContent = '…';

    var reader = new FileReader();
    reader.onload = function () {
      fetch(PHOTO_BASE + side, {
        method: 'POST',
        headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
        credentials: 'same-origin',
        body: JSON.stringify({ image: reader.result }),
      })
        .then(function (r) { return r.json().catch(function () { return {}; }); })
        .then(function (d) {
          if (!d.success) throw new Error(d.message || @json(__('event-karate_tournament::messages.ctl_failed')));
          // The wall was pushed the new corner by the server; the console just
          // takes the state it was handed back.
          if (d.state) { STATE = d.state; paint(); }
          alertBar(d.message, true);
        })
        .catch(function (err) { alertBar(err.message); })
        .finally(function () {
          input.value = '';
          if (icon) icon.textContent = '◎';
        });
    };
    reader.onerror = function () {
      alertBar(@json(__('event-karate_tournament::messages.ctl_failed')));
      input.value = '';
      if (icon) icon.textContent = '◎';
    };
    reader.readAsDataURL(file);
  }
  el('btnBracket').onclick = function () { el('bracket').hidden = false; paintQueue(); };
  el('btnFetch').onclick = function () { send('meta', {}); };
  el('btnDuration').onclick = function () {
    var m = (parseInt(el('durMin').value, 10) || 0) + (parseInt(el('durSec').value, 10) || 0) / 60;
    if (m > 0) send('duration', { minutes: m });
  };
  el('pickerQuery').oninput = paintPicker;

  // Header fields and corner names commit on blur / Enter, not per keystroke.
  document.addEventListener('change', function (e) {
    var t = e.target;
    if (t.dataset && t.dataset.meta) { var p = {}; p[t.dataset.meta] = t.value; send('meta', p); }
    if (t.dataset && t.dataset.corner) { var q = { side: t.dataset.side }; q[t.dataset.corner] = t.value; send('corner', q); }
  });

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

  el('apiUrl').value = @json(route('karate-scoreboard.control', $event->uuid)) + '?mat=' + encodeURIComponent(MAT);
  paint();
})();
</script>
</body>
</html>
