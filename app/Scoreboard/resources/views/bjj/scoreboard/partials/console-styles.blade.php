{{--
    The scoring console's stylesheet — the fonts, the tokens and the vocabulary
    the layout is built from.

    Extracted so the Blade document and the React island (Phase M3, behind
    `features.react_scoreboard`) draw from ONE file, for the reason set out in
    the board's copy: a reversible flag cannot have two stylesheets behind it.

    Included by:
      · bjj/scoreboard/desktop/control.blade.php        the hand-written document
      · bjj/scoreboard/react/control.blade.php          the island's shell
--}}
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
  src: url("{{ route('bjj-screen.font', $slug.'-'.$subset.'.woff2', false) }}") format('woff2');
  unicode-range: {{ $range }}; }
@endforeach
@endforeach

  /* ── The tokens, named once ────────────────────────────────────────────
     The Karate console's palette with this sport's two corners substituted:
     BLUE where it had AKA, WHITE-as-ice where it had AO. Nothing below writes
     a raw corner colour. */
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
    --alarm:#ff3b47;
    --blue:#1362d1;
    --blue-ink:#8ab4ff;
    --blue-plate:#2f7bdb;
    --white:#e6ebf2;
    --white-ink:#f4f7fb;
    --white-plate:#aab6c6;
    --gold:#ffe135;     /* always solid, never transparent          */
    --adv:#ffd666;
    --green:#7ae582;
  }

  /* The face belongs on BODY, not only on the stage: the modals and the toast
     are teleported out to body level, and without this they fell back to the
     browser's serif — which is exactly where the running order is read. */
  html,body{margin:0;padding:0;background:var(--ink);overflow:hidden;
            font-family:'Barlow Condensed', system-ui, sans-serif;color:var(--text);}
  *{box-sizing:border-box;}
  input,select,button,textarea{font-family:'Barlow Condensed',sans-serif;}
  [hidden]{display:none !important;}

  /* The console is tabbed through as often as it is touched; a control you
     cannot see the focus on is a control that gets pressed by mistake. */
  button:focus-visible,a:focus-visible,input:focus-visible,label:focus-within{
    outline:2px solid var(--gold);outline-offset:2px;}
  input:focus{outline:2px solid #ffe13555;}

  /* Scrollbars, everywhere: a list at a mat is dragged with a finger and the
     browser's default bar is invisible on this ground. */
  *{scrollbar-width:thin;scrollbar-color:var(--line-2) var(--ink);}
  ::-webkit-scrollbar{width:10px;height:10px;}
  ::-webkit-scrollbar-track{background:var(--ink);border-radius:8px;}
  ::-webkit-scrollbar-thumb{background:var(--line-2);border-radius:8px;border:2px solid var(--ink);}
  ::-webkit-scrollbar-thumb:hover{background:var(--gold);}

  @keyframes ambient{0%,100%{background-position:0% 50%}50%{background-position:100% 50%}}
  @keyframes orbA{0%,100%{transform:translate(0,0)}50%{transform:translate(70px,-40px)}}
  @keyframes orbB{0%,100%{transform:translate(0,0)}50%{transform:translate(-60px,50px)}}
  @keyframes cardIn{0%{opacity:0;transform:translateY(18px)}100%{opacity:1;transform:translateY(0)}}
  /* A pop-up rides the console's own scale (see `.modal` below), so its
     entrance has to be written in terms of it — a bare `scale(1)` here
     would snap the card back to full size the instant the animation
     filled, which is exactly how it ended up bigger than the board. */
  /* ⚠️ --popup-scale, NOT --stage-scale.
     A pop-up follows the console UP but never DOWN. The console is authored on
     a 1920x1080 canvas and letterboxed into whatever glass it is given, so on a
     1366x768 laptop --stage-scale is 0.71 — and a card that took that scale had
     its 21px button labels drawn at 15px and its body text at 18px, on the one
     surface an official actually has to READ rather than glance at. Growing on
     a bigger screen is wanted; shrinking below the size the text was written at
     is not. Hence max(1, …), and every pop-up rule below uses this variable. */
  :root{--popup-scale:max(1, var(--stage-scale, 1));}

  @keyframes winIn{0%{opacity:0;transform:scale(calc(var(--popup-scale) * .92))}
                   100%{opacity:1;transform:scale(var(--popup-scale))}}
  /* The Start button while the clock is RUNNING. The plinth is carried through
     every step on purpose — a keyframe that names box-shadow replaces the whole
     property, so dropping the 8px here would make the button lose its drawn
     height for the length of the pulse. */
  @keyframes runGlow{0%,100%{box-shadow:0 8px 0 -2px rgba(0,0,0,.5),0 0 30px -6px rgba(255,107,120,.55)}
                     50%{box-shadow:0 8px 0 -2px rgba(0,0,0,.5),0 0 60px -6px rgba(255,107,120,.95)}}
  @keyframes blink{0%,100%{opacity:1}50%{opacity:.2}}
  @keyframes alarmGlow{0%,100%{text-shadow:0 0 24px rgba(255,59,71,.35)}50%{text-shadow:0 0 48px rgba(255,59,71,.85)}}

  /* ── The console is a 16:9 CANVAS ──────────────────────────────────────
     1920x1080 exactly, like the wall board next to it, and it only ever
     SCALES: `min(w/1920, h/1080)`, centred, letterboxed on whatever glass it
     lands on. Nothing here reflows and nothing here scrolls.

     It used to be `min-height:1080px` with fit() measuring whatever the layout
     came out to — which was 1337px, a 1.44:1 canvas. On a 1920x1080 screen
     that scaled the whole console to 74%, so a mat's operator got a 1431px
     console inside a 1920px screen with 250px of black down each side, and the
     ratio changed shape every time a control was added.

     The layout below is therefore BUDGETED to 1080: padding 48 + top 67 +
     gap 16 + body (flex, takes the rest) + gap 16 + bottom 190. Every fixed
     height in the centre column is set here rather than inline, because both
     renderers draw this same console and a number that lives in two files is a
     number that will disagree with itself. `overflow:hidden` is the backstop —
     if some future control does not fit, it is clipped rather than allowed to
     stretch the canvas back out of ratio. */
  #root{position:absolute;inset:0;overflow:hidden;background:var(--ink);}
  #stage{position:absolute;left:50%;top:50%;width:1920px;height:1080px;overflow:hidden;
         transform:translate(-50%,-50%) scale(var(--stage-scale,0.6));transform-origin:center;
         /* The canvas's own box lives here too, not inline on the element: the
            band gap is part of the 1080 budget, and the two renderers had
            already drifted 4px apart on it. */
         display:flex;flex-direction:column;gap:16px;padding:26px 36px 22px;
         font-family:'Barlow Condensed',sans-serif;color:var(--text);
         background:linear-gradient(120deg,#0a0b10 0%,#10121c 30%,#0a0b10 55%,#12101a 80%,#0a0b10 100%);
         background-size:300% 300%;animation:ambient 18s ease-in-out infinite;}

  /* ── The three bands, and the vertical budget they divide ────────────── */
  #ctlTop{display:flex;align-items:flex-end;gap:32px;flex:0 0 auto;}
  #ctlBody{flex:1;display:grid;grid-template-columns:1fr 520px 1fr;gap:20px;min-height:0;}
  #ctlBottom{flex:0 0 180px;display:grid;grid-template-columns:1fr 560px;gap:20px;min-height:0;}

  /* ── The centre column, and the state it has to survive ────────────────
     Sized for its WORST case, which is a level match at 0:00: the referee
     decision button appears and the column carries seven controls instead of
     six. Budgeted at 740px against a 753px row, so that state has 13px in hand
     and the ordinary one has 48px.

     `overflow-y:auto` is the backstop, deliberately auto and not hidden: if a
     future control does tip it over, the operator can still reach everything by
     dragging the column, which is a far better failure than a clipped Finalize
     button they cannot see. `min-height:0` is what lets that work inside a
     grid row. */
  #ctlCentre{padding:18px 24px 16px;display:flex;flex-direction:column;align-items:center;
             gap:8px;order:2;min-height:0;overflow-y:auto;}
  #btnQueue{width:100%;min-height:60px;font-size:29px;letter-spacing:.12em;flex:0 0 auto;}
  #ctlClock{width:100%;display:flex;flex-direction:column;align-items:center;gap:2px;
            padding:10px 20px 10px;border-radius:12px;background:var(--ink);flex:0 0 auto;}
  #btnStart{width:100%;min-height:68px;font-size:32px;letter-spacing:.12em;
            box-shadow:0 8px 0 -2px rgba(0,0,0,.5);flex:0 0 auto;}
  .transport{width:100%;display:grid;grid-template-columns:1fr 1fr;gap:8px;flex:0 0 auto;}
  #btnDecision{width:100%;flex:0 0 auto;}
  /* Destructive controls sit APART from the ones above them. */
  #endRow{width:100%;display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-top:8px;flex:0 0 auto;}
  /* The footer is a status line, not a control row: it takes what is left and
     stays on ONE line. Both halves used to wrap at this column width, which
     made the row ragged and ate into the slack the decision button needs. */
  #ctlFoot{width:100%;display:flex;align-items:center;justify-content:space-between;gap:14px;
           margin-top:auto;padding-top:4px;flex:0 0 auto;font-size:17px;}
  #screensLine{min-width:0;letter-spacing:.12em;text-transform:uppercase;color:var(--faint);
               white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
  #ctlFoot label{display:flex;align-items:center;gap:8px;color:var(--muted);letter-spacing:.08em;
                 text-transform:uppercase;cursor:pointer;white-space:nowrap;flex:0 0 auto;}

  /* ── The vocabulary the layout is built from ─────────────────────────── */
  .cap{font-size:19px;letter-spacing:.18em;text-transform:uppercase;color:var(--muted);}
  .val{font-size:34px;font-weight:600;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
  .card{background:var(--panel);border:1px solid var(--line);border-radius:18px;}

  /* A scoring button. The runtime builds six of these into each corner's grid
     from the server's own price list, so the VALUE is displayed and the ACTION
     is named under it — never the other way round. */
  .btn.score{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:2px;
        min-height:96px;border:none;border-radius:12px;color:#fff;cursor:pointer;padding:10px 6px;
        transition:transform .08s,filter .08s;}
  .btn.score .v{font-family:'Anton',sans-serif;font-size:46px;line-height:1;}
  .btn.score .l{font-size:19px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:rgba(255,255,255,.82);text-align:center;line-height:1.05;}
  .btn.score:active:not(:disabled){transform:scale(.96);filter:brightness(1.25);}
  #blueScoreGrid .btn.score{background:var(--blue);}
  /* The WHITE corner is white — the gi, not a grey stand-in for it. Ink goes
     black with it, label included, or the action under the value disappears. */
  #whiteScoreGrid .btn.score{background:#fff;color:#000;}
  #whiteScoreGrid .btn.score .l{color:rgba(0,0,0,.72);}

  .scoreGrid{display:grid;grid-template-columns:1fr 1fr;gap:12px;}

  /* Every other button on the console: one box, colour from the caller. */
  /* 56px is the design spec's floor for a non-scoring control, and the
     budget is built on it — do not raise it without re-checking the
     centre column still lands inside 1080 with the decision button up. */
  .btn{min-height:56px;font-size:26px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;
       background:var(--fill-alt);border:1px solid var(--line-2);border-radius:12px;color:var(--text);
       padding:12px 16px;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:10px;
       transition:transform .08s,filter .15s;}
  .btn:active:not(:disabled){transform:scale(.97);filter:brightness(1.2);}
  .btn:disabled{opacity:.35;cursor:not-allowed;filter:none;}
  .btn.adv{color:var(--adv);border-color:rgba(255,214,102,.55);}
  .btn.pen{color:var(--alarm);border-color:rgba(255,59,71,.5);}
  .btn.ok{background:var(--gold);color:#000;border-color:var(--gold);}
  .btn.danger{background:transparent;color:#ff6b78;border-color:rgba(255,107,120,.5);}
  .btn.small{min-height:44px;font-size:21px;letter-spacing:.06em;padding:6px 14px;}

  /* The two ladders, as plates rather than counters: they are separate ways to
     win, and reading them as one number is the mistake this layout prevents. */
  .counters{display:flex;gap:14px;}
  .counters .c{flex:1;display:flex;align-items:center;justify-content:space-between;gap:12px;
       background:var(--inset);border:1px solid var(--line);border-radius:12px;padding:10px 18px;}
  .counters .c .l{font-size:20px;font-weight:700;letter-spacing:.2em;text-transform:uppercase;}
  .counters .c.a .l{color:var(--adv);}
  .counters .c.p .l{color:var(--alarm);}
  .counters .c .n{font-family:'Anton',sans-serif;font-size:44px;line-height:1;color:var(--text);}

  /* ⚠️ `.warnDq`, NOT `.warn`: the shared runtime writes `class="num warn"` onto
     the CLOCK for the final minute, and a `.warn` rule that set a 20px type
     size would have shrunk the clock to a caption exactly when it matters
     most. The two warnings are different things and are named differently. */
  .warnDq{font-size:20px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--alarm);
        text-align:center;animation:blink 1.4s ease-in-out infinite;}

  /* The final minute, and the mat's own light. Both are written by the runtime
     (paintClock), so both have to exist here. */
  /* The clock plate. These live HERE rather than inline on the element because
     the React console renders the same two ids and must get the same plate —
     one stylesheet, two paths, no drift. */
  #clockState{font-size:19px;letter-spacing:.32em;color:var(--muted);text-transform:uppercase;}
  #clockVal{font-family:'Anton',sans-serif;font-size:88px;line-height:1.04;
            font-variant-numeric:tabular-nums;color:var(--text);transition:color .3s;}
  #clockVal.warn{color:var(--alarm);animation:alarmGlow 1s ease-in-out infinite;}

  /* The referee's own count. Styled here for the same reason the clock is: the
     React console renders this id too. */
  #stallCount{font-family:'Anton',sans-serif;font-size:60px;line-height:1;color:var(--alarm);
              min-width:96px;text-align:center;}
  /* The countdown's plate in the centre column. It reads as a second clock
     because that is what it is — a referee watching one is watching both —
     but a quieter one: the match clock is the panel's headline and this must
     never compete with it. Started from the corner it is against; here it
     only counts, and is cancelled or turned into a penalty. */
  #stallPlate{width:100%;flex:0 0 auto;display:flex;flex-direction:column;gap:6px;
              padding:8px 16px 10px;border-radius:12px;background:var(--ink);
              border:1px solid color-mix(in srgb, var(--alarm) 24%, transparent);}
  #stallPlate .stallHead{display:flex;align-items:baseline;gap:12px;min-width:0;}
  #stallPlate .stallHead .why{font-size:16px;color:var(--faint);letter-spacing:.06em;
              white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
  #stallPlate .stallRow{display:flex;align-items:center;gap:14px;}
  #stallPlate .stallActions{flex:1;display:grid;grid-template-columns:1fr 1fr;gap:8px;}
  #liveDot{width:16px;height:16px;border-radius:50%;background:var(--muted);flex:0 0 auto;}
  #liveDot.on{background:var(--green);animation:blink 1.6s ease-in-out infinite;}

  /* ── The event log ───────────────────────────────────────────────────── */
  #log{flex:1;min-height:0;overflow:auto;}
  .logRow{display:flex;align-items:center;gap:16px;padding:10px 14px;border-bottom:1px solid var(--line);font-size:22px;}
  .logRow:last-child{border-bottom:0;}
  /* Nothing is erased: a reversal leaves the row it reversed on the record. */
  .logRow.rev{opacity:.45;text-decoration:line-through;}
  .logRow .ts{font-family:'Anton',sans-serif;font-size:28px;min-width:86px;color:var(--muted);}
  .logChip{padding:4px 14px;border-radius:8px;font-size:17px;font-weight:700;letter-spacing:.14em;
       text-transform:uppercase;border:1px solid currentColor;}
  .logChip.point{color:var(--blue-ink);}
  .logChip.advantage{color:var(--adv);}
  .logChip.penalty{color:var(--alarm);}
  .logChip.reverse{color:var(--muted);}
  .logChip.other{color:var(--text);}
  .logRow .d{flex:1;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
  /* Hold-to-confirm: a 1s gold sweep, and releasing cancels it. */
  .logRow .undo{position:relative;overflow:hidden;min-height:44px;font-size:20px;padding:6px 18px;}
  .logRow .undo .sweep{position:absolute;inset:0;background:var(--gold);opacity:.35;transform:scaleX(0);transform-origin:left;}
  .logRow .undo.holding .sweep{transform:scaleX(1);transition:transform 1s linear;}

  /* ── The running order, in this console's one modal shape ────────────── */
  .qItem{display:flex;align-items:center;gap:20px;padding:16px 20px;background:var(--inset);
       border:1px solid var(--line);border-radius:14px;}
  .qItem .n{font-family:'Anton',sans-serif;font-size:38px;min-width:90px;color:var(--gold);}
  .qItem .who{flex:1;min-width:0;font-size:26px;font-weight:600;color:var(--text);}
  .qItem .who .m{font-size:19px;letter-spacing:.14em;text-transform:uppercase;color:var(--muted);}

  /* ── Every pop-up on this console: one scrim, one card, one head ─────── */
  #scrim,.scrim{position:fixed;inset:0;background:rgba(0,0,0,.6);display:flex;align-items:center;justify-content:center;padding:30px;z-index:40;}
  /* ⚠️ A pop-up is authored in CANVAS pixels, like everything else on this
     console, but it lives OUTSIDE #stage — a scrim that only covered the
     letterboxed board would leave the rest of the glass live. So it does not
     inherit the stage's scale and has to take it itself: at 0.6 on a 1080p
     monitor a 1100px card was drawn wider than the whole 1152px console. The
     variable is published on <html> by the same fit() that sizes the stage,
     and defaults to 1 so a console that never scales (the tablet) is
     untouched. */
  #modal,.modal{background:var(--panel);border:1px solid var(--line-2);border-radius:20px;padding:28px 30px;
       display:flex;flex-direction:column;gap:18px;max-height:94%;width:720px;max-width:calc(100vw - 60px);
       transform:scale(var(--popup-scale));transform-origin:center;
       animation:winIn .25s cubic-bezier(.2,.8,.2,1) both;}
  /* ── The centre column, built to Karate's measurements ────────────────
     Copied deliberately, not coincidentally: an official who works a karate
     mat in the morning and a jiu-jitsu mat in the afternoon reaches for the
     same places. The big pair (Bouts, Start) carry an 8px plinth, which is
     drawn HEIGHT rather than decoration — without it the two boxes measure
     the same and read as different sizes. */
  .bigbtn{width:100%;flex:1.2;min-height:96px;font-size:44px;font-weight:700;letter-spacing:.12em;
       text-transform:uppercase;border:none;border-radius:16px;padding:26px 0;cursor:pointer;
       display:flex;align-items:center;justify-content:center;gap:22px;
       box-shadow:0 8px 0 -2px rgba(0,0,0,.5);transition:transform .08s;}
  .bigbtn:active:not(:disabled){transform:translateY(3px);}

  /* The six controls under the clock — same box, colour from each button. */
  .cbtn{min-height:76px;font-size:30px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;
       background:var(--fill-alt);border-radius:12px;padding:16px 0;cursor:pointer;color:var(--text);
       display:flex;align-items:center;justify-content:center;transition:transform .08s,filter .15s;}
  .cbtn:active:not(:disabled){transform:scale(.97);filter:brightness(1.2);}

  /* Nothing that acts on a match is offered while the mat is empty. */
  .bout[disabled],.bigbtn[disabled]{opacity:.35;cursor:not-allowed;filter:none;}

  /* The settings card: its tabs, its rule rows and its minute/second boxes. */
  .stab{flex:1;font-size:20px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;
       border-radius:10px;padding:12px 0;cursor:pointer;background:var(--fill-alt);color:var(--muted);
       border:1px solid var(--line-2);}
  .rrow{display:flex;align-items:center;gap:14px;background:var(--inset);border:1px solid var(--line);
       border-radius:12px;padding:12px 16px;cursor:pointer;text-align:left;width:100%;}
  .rbox{width:30px;height:30px;flex:0 0 auto;border-radius:8px;display:flex;align-items:center;
       justify-content:center;font-size:20px;font-weight:700;color:#000;padding:0;}
  .mm{flex:1;min-width:0;font-family:'Anton',sans-serif;font-size:28px;text-align:center;
       background:var(--fill);color:var(--text);border:none;border-radius:12px;padding:10px 8px;}
  .modal > *{min-width:0;}

  /* A pop-up that carries its own head rather than the shared #modalTitle:
     the bouts card titles itself in the mat's green and closes with a ✕ in the
     corner, so the whole card below it belongs to the running order. */
  .mhead{display:flex;align-items:center;justify-content:space-between;gap:20px;}
  .mtitle{font-family:'Anton',sans-serif;letter-spacing:.08em;text-transform:uppercase;}
  .mclose{font-size:24px;background:none;border:none;color:var(--muted);cursor:pointer;padding:6px 10px;}
  .mclose:hover{color:var(--text);}
  #modalTitle{font-family:'Anton',sans-serif;font-size:36px;letter-spacing:.06em;text-transform:uppercase;color:var(--text);}
  #modalBody{display:flex;flex-direction:column;gap:18px;overflow:auto;}
  #modalBody label{font-size:20px;font-weight:600;letter-spacing:.16em;text-transform:uppercase;color:var(--muted);display:block;margin-bottom:8px;}
  #modalBody input{width:100%;font-size:26px;background:var(--inset);border:1px solid var(--line);
       border-radius:10px;color:var(--text);padding:14px 16px;}
  #modalActions{display:flex;justify-content:flex-end;gap:16px;}
  .choiceRow{display:flex;flex-wrap:wrap;gap:12px;}
  .choice{border:1px solid var(--line-2);border-radius:12px;padding:14px 22px;background:var(--fill-alt);
       color:var(--text);font-size:24px;font-weight:600;cursor:pointer;transition:filter .12s;}
  .choice.on{border-color:var(--gold);color:var(--gold);}

  /* ── The 5-second undo toast ─────────────────────────────────────────── */
  #toast{position:fixed;left:50%;bottom:32px;transform:translateX(-50%);display:flex;align-items:center;gap:24px;
       background:var(--panel);border:1px solid var(--line-2);border-radius:14px;padding:16px 24px;
       font-size:26px;color:var(--text);z-index:60;box-shadow:0 20px 60px rgba(0,0,0,.6);}

  @media (prefers-reduced-motion: reduce){*{animation:none !important;transition:none !important;}}
</style>
