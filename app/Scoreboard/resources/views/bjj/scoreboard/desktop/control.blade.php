{{--
    The scoring table at a Brazilian Jiu-Jitsu mat — Match Control.

    ── Where this design came from ────────────────────────────────────────────
    The user's own canvas, `drafts/Score Control Board.html`, adopted whole on
    2026-09-10 as the PRIMARY layout for this console — the same file on the
    laptop at the table and on the tablet in the referee's hand, because there
    is one console here and it only ever SCALES (see the note on the controller's
    consoleView()). What arrived from that draft is the LAYOUT and every
    measurement in it: the 1920x1080 canvas, the header's four read-only facts,
    the three-panel corner/centre/corner main grid at `1fr 560px 1fr`, the
    corner's order (plate · the two ladders · six actions · the two ladder
    buttons · stalling), the floating 3px corner rule instead of a border-top,
    the centre column's plinthed pair, and the clock plate with a progress bar
    under the digits.

    What the draft did NOT have, and has been brought in from the console this
    replaces, because it is what makes the table work rather than demonstrate:
      · the RUNNING ORDER — the Bouts button's actual door, with the rich
        bout rows (number, stage, division, the two corners facing each other
        across the "vs" with their portraits and club flags). Painted by the
        runtime's paintBouts().
      · the SETTINGS card — tabs for the rule book, the clock, the hall's
        sounds and, where the door was given camera addresses, the mat's
        cameras. Everything in it is posted to the MAT, not kept here.
      · Referee decision and Record result, which a level bout and a finished
        bout respectively cannot be resolved without.
      · the end-of-match panel, the score log with its hold-to-undo, the shared
        one-question dialog, the toast, and the mat's own live light.

    ── What did not move an inch ──────────────────────────────────────────────
    The BEHAVIOUR. Every id, class and data-attribute the shared runtime binds
    to (../runtime.blade.php) is still here and still means what it meant: this
    is a re-skin of the same instrument, not a second one. The draft's own
    prototype logic — its self-firing stalling prompt, its pick-a-winner result
    card and its winner banner — is deliberately NOT ported: the winner is
    declared through the runtime's end-of-match flow, which is the one that
    reaches the bracket, and the hall's celebration is the wall board's job
    (winner-celebration.blade.php). Bringing those over would have meant a
    second, prettier way to decide a match that wrote nothing down.

    ── The CONTROLS are this sport's ──────────────────────────────────────────
      · six scoring buttons — three amounts, each with a give and a take-back:
        +2/-2, +3/-3, +4/-4, built by the runtime from Ledger::POINT_VALUES.
        The grid used to name the ACTION (a takedown, a sweep and a knee-on-
        belly are all two), and it read as three identical "+2"s an official
        had to READ rather than hit — with no way to take a score back without
        opening the score log. Now the amount is the row and the column is the
        job. The server still prices everything; a named action remains a valid
        thing to post and keeps its word on the record.
      · advantages and penalties are their own ladders beside the points, and
        stay their own numbers on the screen — though since 2026-09-12 each of
        them also MOVES the score (an advantage is a point to the man who
        earned it, a penalty a point to his opponent; the arithmetic is
        Ledger::tally()'s and nothing here adds anything up). In the draft's
        order they sit directly under the score plate, where they are read WITH
        the score rather than found under the buttons that change them.
      · the stalling countdown is the referee's own and the hall learns about it
        only if a penalty is actually given. It is STARTED from the corner it is
        against and COUNTED in the centre, on a strip that exists only while a
        count is running — the console's Cancel / Apply penalty pair finally has
        a home, which it has not had on this table before (the runtime has
        always written to `#stallCount`, `#stallApply` and `#stallCancel`, and
        every one of them was missing here, so the corner's Stalling button
        started a count nobody could see).
      · the score log opens as a modal from the centre column, because it is a
        correction tool read when something has to be put right. It is also the
        ONLY undo, and nothing in this sport is ever erased — an undo APPENDS a
        reversal with a reason and the row it reversed stays, struck through.

    ── It decides NOTHING about the score ─────────────────────────────────────
    Every button posts an INTENTION: "blue passed the guard", never "blue now
    has five". It does not even send what a pass is worth — the server prices it
    — and the score that comes back is replayed from the ledger. A console that
    has fallen behind cannot overwrite the truth.

    ── Graduated friction, exactly as the design spec sets it out ─────────────
      score                    · no confirmation, 400ms lockout, NO toast
      undo                     · 1s hold on that row in the score log
      pause / resume / review  · single tap
      end · reset · correction · modal
      DQ · finalize            · modal AND the match number typed in
      match over               · the end panel offers itself, once, and
                                 recording carries the mat to the next bout
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

{{-- A fixed console, not a document: it is authored at one size and scaled to
     the glass, so there is nothing to zoom INTO — magnifying it can only push
     the row of controls along the bottom off the edge. The one place the house
     rule against `user-scalable=no` does not apply (WCAG 1.4.4 is about pages
     people read); panning is left alone, because the console is taller than a
     10" tablet and has to scroll. --}}
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
<style>
  html { -webkit-text-size-adjust:100%; text-size-adjust:100%; touch-action:pan-x pan-y; }
  body { touch-action:pan-x pan-y; }
</style>
<script>
(function () {
  'use strict';
  ['gesturestart', 'gesturechange', 'gestureend'].forEach(function (e) {
    document.addEventListener(e, function (ev) { ev.preventDefault(); }, { passive: false });
  });
  document.addEventListener('wheel', function (ev) { if (ev.ctrlKey) ev.preventDefault(); }, { passive: false });
})();
</script>
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ __('scoreboard::bjj_messages.ctl_title') }} · {{ $event->title }}</title>

{{-- The fonts, the tokens and the vocabulary. Shared with the React island's
     shell so the two paths cannot drift apart. --}}
@include('scoreboard::bjj.scoreboard.partials.console-styles')

{{-- ── The draft's own measurements ──────────────────────────────────────────
     Kept in THIS file rather than pushed into console-styles, and deliberately:
     that stylesheet is shared with the React island's shell, and a layout
     change made there would silently re-shape a console this task has not
     looked at. Everything below either overrides a shared rule for this
     document only, or is new. --}}
<style>
  /* The canvas, as the draft draws it: 24/26 padding and no band gap — the
     header carries its own tail instead. */
  #stage{padding:24px 26px 26px;gap:0;}

  #ctlTop{display:flex;align-items:flex-start;justify-content:space-between;
          gap:32px;padding-bottom:22px;flex-wrap:wrap;}
  /* The header's four facts, at the draft's sizes. */
  #ctlTop .cap{font-size:14px;font-weight:600;letter-spacing:2.8px;}
  #ctlTop .val{font-size:32px;line-height:1;text-transform:uppercase;}

  /* Three panels, and the centre is 560 wide — the draft's number, not the
     520 the shared sheet sets for the console this replaces. */
  #ctlBody{flex:1;display:grid;grid-template-columns:1fr 560px 1fr;gap:15px;
           align-items:stretch;min-height:0;}

  /* ── A corner ──────────────────────────────────────────────────────────
     The colour is a 3px rule FLOATING on the panel's top edge rather than a
     border, so the panel's own corners stay round under it. */
  .corner{background:var(--panel);border-radius:18px;padding:22px 26px;
          display:flex;flex-direction:column;gap:10px;position:relative;min-height:0;}
  .corner > .rule{position:absolute;left:20px;right:20px;top:-2px;height:3px;border-radius:3px;}

  .plate{height:110px;border-radius:12px;padding:8px 22px;display:flex;
         align-items:center;justify-content:space-between;gap:16px;}
  .plate .who{display:flex;flex-direction:column;gap:2px;min-width:0;}
  .plate .corner-label{font-size:16px;font-weight:700;letter-spacing:3.2px;text-transform:uppercase;}
  .plate .nameRow{display:flex;align-items:center;gap:10px;min-width:0;}
  .plate .flag{width:42px;height:28px;flex:0 0 auto;border-radius:4px;
               border:1px solid rgba(255,255,255,.2);background-size:100% 100%;background-position:center;}
  .plate .nm{font-size:24px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;
             line-height:1.1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
  .plate .clubRow{display:flex;align-items:center;gap:8px;margin-top:2px;min-width:0;}
  /* A club crest is a transparent PNG on a bare sizing box — never a filled
     tile (CLAUDE.md Design Rule #5). It is hidden until there is one: most
     clubs at a real competition are written down on the day. */
  .plate .crest{width:38px;height:38px;flex:0 0 auto;background-size:contain;
                background-repeat:no-repeat;background-position:center;}
  .plate .club{font-size:21px;font-weight:600;letter-spacing:1.6px;text-transform:uppercase;
               color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
  .plate .pts{font-family:'Anton',sans-serif;font-size:96px;line-height:.75;
              font-variant-numeric:tabular-nums;text-shadow:0 4px 30px rgba(0,0,0,.8);}
  /* The two grids of controls reverse with everything else in the white
     corner — the scoring grid's give/take columns and the ladder row's four
     buttons, each read end to end from the outer edge in. `direction` is what
     reverses a GRID's placement (the row-reverse used on the flex rows above
     has no grid equivalent), and it is safe here because this console is laid
     out LTR at every locale: it carries no `dir` attribute and positions its
     corners with `order`. Put back to ltr on the buttons so nothing inside one
     re-orders with it. */
  .corner.mirror .scoreGrid,
  .corner.mirror .cornerActs{direction:rtl;}
  .corner.mirror .scoreGrid > .btn,
  .corner.mirror .cornerActs > .btn{direction:ltr;}

  /* The white corner is a full mirror: every horizontal row reverses and its
     labels sit right, so the half of the console an official reaches for is the
     half of the mat they are looking at. */
  .corner.mirror .plate,
  .corner.mirror .plate .nameRow,
  .corner.mirror .plate .clubRow,
  .corner.mirror .ladders .lad{flex-direction:row-reverse;}
  .corner.mirror .plate .who{align-items:flex-end;}

  /* The two ladders, directly under the score: separate ways to win, and
     they keep their own plates: each one also moves the score now (see
     Tally), but the reader is still shown three numbers, not a sum. */
  .ladders{display:grid;grid-template-columns:1fr 1fr;gap:10px;flex:0 0 auto;}
  .ladders .lad{height:84px;border:1px solid var(--line);border-radius:12px;background:var(--inset);
                padding:10px 22px;display:flex;align-items:center;justify-content:space-between;}
  .ladders .lad .l{font-size:30px;font-weight:700;letter-spacing:3px;text-transform:uppercase;}
  .ladders .lad.a .l{color:var(--gold);}
  .ladders .lad.p .l{color:var(--alarm);}
  .ladders .lad .n{font-family:'Anton',sans-serif;font-size:72px;line-height:1;
                   font-variant-numeric:tabular-nums;}

  /* ── Every button in a corner is the same height ────────────────────────
     Five rows of buttons share what the panel has left, in equal parts: the
     three scoring rows, the ladder row, and the referee's count. Not a number
     typed into each one — the corner is a fixed-height column inside a stage
     scaled to the glass, so a fixed height is a height that is right at one
     size and wrong at every other. They are made equal by SHARE instead.

     The arithmetic, which is the whole trick: the scoring grid holds three of
     the five rows, so it grows 3 to the others' 1 — and its flex-BASIS is the
     20px of its own two internal gaps, so the space being shared out is
     button height only. Give it a basis of 0 instead and those 20px come out
     of its three rows, leaving them ~7px short of the other two: close enough
     to look like a mistake rather than a measurement.

     Consequence to keep: anything added to this column takes a share too, or
     it is `flex:0 0 auto` like the plate and the ladder counters above. */
  .corner .scoreGrid{gap:10px;flex:3 0 20px;min-height:0;grid-template-rows:repeat(3,minmax(0,1fr));}
  .corner .btn.score{min-height:0;height:100%;border-radius:12px;padding:10px 6px;gap:2px;}
  .corner .btn.score .v{font-size:54px;}
  .corner .btn.score .l{font-size:26px;letter-spacing:.085em;}

  /* The two ladders as ONE row of four — advantage give and take, penalty give
     and take. Four equal columns, so the pairs are the same size as each other
     and the row reads as four presses rather than two controls with
     attachments. The short words (ADV / PEN) are what make four fit across a
     corner without wrapping; the full ones live on the plates above and in
     each button's aria-label.

     Same two-line shape as the scoring buttons — amount on top, what it is
     underneath — because it is the same gesture at the same table, and a
     console that says the same thing two ways is one you have to learn twice. */
  /* ⚠️ `grid-template-rows` is load-bearing, exactly as it is on the scoring
     grid above. Without it this grid's one row is AUTO-sized, a percentage
     height on a child resolves against an indefinite track and falls back to
     `auto` — and because the child then has a specified height, `align-self:
     stretch` no longer applies to it either. The buttons come out at content
     height inside a full-height row: a band of dead panel under them, and four
     ladder buttons visibly shorter than the six scoring buttons beside them
     while every rule here still says they share equally. Declaring the row
     makes the track definite and the 100% below real. */
  .cornerActs{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));
              grid-template-rows:minmax(0,1fr);gap:10px;flex:1 0 0;min-height:0;}
  .corner .btn.lbtn{height:100%;min-height:0;border-radius:12px;padding:8px 4px;gap:2px;
                    flex-direction:column;white-space:nowrap;}
  .corner .btn.lbtn .v{font-family:'Anton',sans-serif;font-size:48px;line-height:1;letter-spacing:.02em;}
  .corner .btn.lbtn .l{font-size:22px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;
                       opacity:.78;line-height:1;}
  /* Hollow, like the minus column of the score grid — same ink as the ladder
     it belongs to, so a pair reads as one control split in two. */
  .corner .btn.lbtn.minus{background:var(--inset);}
  /* ── The referee's stalling count, the draft's way ──────────────────────
     The count runs INSIDE the button of the corner it is against, the button
     glows and the number pumps while it runs. Both animations are the draft's
     own (drafts/Score Control Board.html): an inset gold wash that breathes,
     and a 1.18 pump on the inner span so the whole label moves rather than the
     digits jittering against a fixed word. */
  @keyframes stallGlow{0%,100%{box-shadow:inset 0 0 12px 2px rgba(255,225,53,.35)}
                       50%{box-shadow:inset 0 0 42px 10px rgba(255,225,53,.75)}}
  @keyframes stallPump{0%,100%{transform:scale(1)}50%{transform:scale(1.18)}}
  /* The chosen corner on the Match result card. The draft's own keyframe:
     a gold ring that breathes, so the choice is unmistakable from a step
     back — this is the one screen where picking the wrong man writes the
     wrong name into a bracket. */
  @keyframes pickGlow{0%,100%{box-shadow:0 0 0 3px #ffe135,0 0 24px 4px rgba(255,225,53,.35)}
                      50%{box-shadow:0 0 0 3px #ffe135,0 0 60px 14px rgba(255,225,53,.65)}}
  #endPanel .pick.on{animation:pickGlow 1.4s ease-in-out infinite;}
  #endPanel .mbtn.on{background:var(--gold) !important;color:#000 !important;}
  /* The double-stall button lives in the CENTRE, where the column is a stack
     of fixed-height controls rather than five equal shares (see the note on
     #ctlCentre below), so it is sized like the other buttons on that side
     instead of taking a corner's row height. The glyph, the word and the count
     are the corner button's, because it is the same instrument. */
  #ctlCentre .btn.stall.stallBoth{height:88px;min-height:0;width:100%;border-radius:12px;
                     background:var(--fill-alt);flex:0 0 auto;gap:14px;padding:10px 16px;
                     flex-direction:row;}
  #ctlCentre .btn.stall.stallBoth .stallInner{display:flex;align-items:center;justify-content:center;gap:14px;}
  #ctlCentre .btn.stall.stallBoth .stallWord{font-size:34px;letter-spacing:.05em;line-height:1;}
  #ctlCentre .btn.stall.stallBoth .stallIcon{width:30px;height:30px;flex:0 0 auto;}
  #ctlCentre .btn.stall.stallBoth .stallNum{font-family:'Anton',sans-serif;font-size:38px;line-height:1;
                     letter-spacing:.02em;color:var(--gold);font-variant-numeric:tabular-nums;}
  #ctlCentre .btn.stall.stallBoth .stallNum:empty{display:none;}
  #ctlCentre .btn.stall.stallBoth.stalling{animation:stallGlow 1s ease-in-out infinite;}
  #ctlCentre .btn.stall.stallBoth.stalling .stallInner{animation:stallPump 1s ease-in-out infinite;}

  .corner .btn.stall{flex:1 0 0;height:auto;min-height:0;width:100%;border-radius:12px;
                     background:var(--inset);letter-spacing:.05em;gap:18px;
                     flex-direction:row;padding:10px 16px;}
  .corner .btn.stall .stallInner{display:flex;align-items:center;justify-content:center;gap:18px;}
  /* Sized to the cap height of the word beside it rather than to the 48px type
     size, so the glyph reads as part of the label instead of looming over it.
     `flex:0 0 auto` because an SVG in a flex row will otherwise be squeezed
     when the count appears next to it. */
  .corner .btn.stall .stallIcon{width:38px;height:38px;flex:0 0 auto;}
  /* 48px, the ladder row's size — not the 60 this button carried while it was
     130px tall and a law unto itself. Every button in the corner is now the
     same 128px box, and the last thing that made this one READ bigger was its
     ink: it is the only full-width button in the column, so oversized
     lettering on top of that width made a button of identical height look like
     a bigger control. The count keeps the gold and the pump; it does not need
     to be the largest thing on the panel to be seen. */
  .corner .btn.stall .stallWord{font-size:48px;letter-spacing:.05em;line-height:1;}
  /* The number only takes room when there IS one — an empty box beside the
     word would leave the label off-centre for the whole match. */
  .corner .btn.stall .stallNum{font-family:'Anton',sans-serif;font-size:48px;line-height:1;
                     letter-spacing:.02em;color:var(--gold);font-variant-numeric:tabular-nums;}
  .corner .btn.stall .stallNum:empty{display:none;}
  .corner .btn.stall.stalling{animation:stallGlow 1s ease-in-out infinite;}
  .corner .btn.stall.stalling .stallInner{animation:stallPump 1s ease-in-out infinite;}

  /* ── The centre column ─────────────────────────────────────────────────
     The clock plate is the one FLEXIBLE thing here; everything else is a fixed
     height, so the column's worst case (a level bout, with Referee decision up
     beside Record result) costs the plate rather than pushing a control off the
     bottom. `overflow-y:auto` is the backstop, and `auto` rather than `hidden`
     on purpose: if a future control does tip it over, an operator can still
     drag the column, which is a far better failure than a Record button they
     cannot see. */
  #ctlCentre{background:var(--panel);border:1px solid var(--line);border-radius:18px;
             padding:30px 30px 26px;display:flex;flex-direction:column;align-items:stretch;
             gap:10px;order:2;min-height:0;overflow-y:auto;position:relative;}
  #btnBouts{height:126px;min-height:0;flex:0 0 auto;font-size:44px;letter-spacing:.12em;}
  #ctlClock{flex:1;min-height:0;overflow:hidden;background:var(--ink);border:1px solid var(--line);
            border-radius:12px;padding:16px 26px;display:flex;flex-direction:column;
            align-items:center;justify-content:center;gap:10px;}
  #ctlClock .head{display:flex;align-items:center;justify-content:space-between;width:100%;}
  #ctlClock .head .cap{font-size:18px;font-weight:700;letter-spacing:4.2px;}
  #clockState{font-size:24px;font-weight:800;letter-spacing:4px;}
  #clockVal{font-size:180px;line-height:.86;letter-spacing:2px;}
  #clockTrack{width:100%;height:12px;border-radius:6px;background:var(--fill);overflow:hidden;flex:0 0 auto;}
  #clockBar{height:100%;border-radius:6px;width:0;background:var(--gold);transition:width 1s linear;}
  #btnStart{height:122px;min-height:0;flex:0 0 auto;font-size:44px;letter-spacing:.1em;
            border-radius:16px;box-shadow:0 8px 0 -2px rgba(0,0,0,.5);color:#000;}
  #ctlCentre .row{display:grid;grid-template-columns:repeat(auto-fit,minmax(0,1fr));gap:10px;flex:0 0 auto;}
  #ctlCentre .cbtn{height:84px;min-height:0;font-size:36px;letter-spacing:.075em;
                   white-space:nowrap;line-height:1;padding:0;}

  /* ── A pop-up never leaves the console ─────────────────────────────────
     The two big cards — the running order and the score log — are sized to the
     STAGE, not to the glass, so they land inside the console's own 1920x1080
     board with a 48px inset all round rather than filling the screen around it.
     Asked for 2026-09-10: a card wider than the console it belongs to reads as
     a different application having opened on top of the table.

     The arithmetic, because there are two scales in play and they are not the
     same number. The stage is drawn at `1920 x 1080 * --stage-scale`. A pop-up
     lives OUTSIDE #stage (a scrim that only covered the letterboxed board would
     leave the rest of the glass live), so it takes its own `--popup-scale`,
     which is `max(1, --stage-scale)` — a card follows the console UP but never
     DOWN, or its body text would be drawn smaller than it was written on a
     laptop. So the AUTHORED size has to be the stage's visual size divided by
     the pop-up's own scale, and then the transform multiplies it back to
     exactly the stage.

     `max-width`/`max-height:none` for the same reason as the width: the shared
     clamps in `.modal` are in unscaled pixels and would cut this back down.
     `flex-shrink:0` is the third half of it — the card is a flex ITEM in the
     scrim, so an over-wide layout box would simply be shrunk back to the scrim
     and the whole calculation undone. */
  .modal.stagefit{
    width:calc((1920px * var(--stage-scale, 1) - 96px) / var(--popup-scale));
    height:calc((1080px * var(--stage-scale, 1) - 96px) / var(--popup-scale));
    max-width:none;max-height:none;flex-shrink:0;gap:16px;}

  /* ── A card that may never be bigger than the console it covers ────────
     `stagefit` above FILLS the console; this one CAPS at nine tenths of it and
     is otherwise sized by its content. For a card that should look like a
     panel resting on the board rather than a second screen over it: at 90% a
     strip of the console shows all the way round, which is what tells a reader
     the match is still there underneath.

     Same arithmetic as stagefit and for the same two reasons: the canvas is
     1920x1080 whatever the glass is, and a pop-up lives OUTSIDE #stage so it
     must apply the stage's scale itself — then divide by --popup-scale,
     because the transform on .modal will multiply the layout box back up.

     `overflow:auto` is the safety net, not the plan: the content is authored
     to fit, and this only means a console on a very short window scrolls
     rather than pushing its Record button off the bottom edge. */
  .modal.stagecap{
    max-width:calc((1920px * var(--stage-scale, 1) * .9) / var(--popup-scale));
    max-height:calc((1080px * var(--stage-scale, 1) * .9) / var(--popup-scale));
    overflow:auto;}
  /* ⚠️ The shared gold rule floats 2px ABOVE the card, and `overflow:auto`
     clips it away. Brought to the top edge for these two cards only — the rule
     is 4px tall, so at top:0 it reads the same and survives the clip. Without
     this, capping a card silently costs it the one piece of chrome that says
     it belongs to this console. */
  .modal.stagecap::before{top:0;}

  /* The result card wears the SAME gold ring the wall board puts round a
     winner and the end dialog puts round the corner an official has picked —
     three screens, one signal for "this is the man". Breathing rather than
     static, because a still gold edge on a dark card reads as a border. */
  #overCard.overWon{animation:pickGlow 1.6s ease-in-out infinite;}

  /* ── Every pop-up wears the draft's card ───────────────────────────────
     A gold rule floating on the card's top edge, and it rises rather than
     appears. The scrim blurs, because a question asked over a live match must
     not read as part of it. */
  @keyframes popIn{0%{opacity:0;transform:scale(calc(var(--popup-scale) * .86)) translateY(24px)}
                   100%{opacity:1;transform:scale(var(--popup-scale)) translateY(0)}}
  @keyframes fadeIn{0%{opacity:0}100%{opacity:1}}
  #scrim,.scrim{background:rgba(4,5,9,.78);backdrop-filter:blur(5px);animation:fadeIn .2s ease-out;}
  #modal,.modal{position:relative;animation:popIn .28s cubic-bezier(.2,1.2,.3,1) both;}
  #modal::before,.modal::before{content:'';position:absolute;left:24px;right:24px;top:-2px;
       height:4px;border-radius:4px;background:var(--gold);}
  /* The stalling card paints its own rule in the stalling corner's colour
     (paintStall), so the shared gold one must not sit on top of it. */
  #stallPrompt .modal::before{display:none;}

  /* ── The bouts card's four ways in ─────────────────────────────────────
     One card, four questions. "Arranged" is the running order this card has
     always been and is untouched; the other three exist because the running
     order answers only "what is next on THIS mat, today" and an official at
     the table is regularly asked something else — a whole weight class, one
     named competitor, or two people standing in front of them.

     Tabs rather than four buttons on the console: the card is already the
     door, and a mat has no room for three more. Gold marks the live tab
     because gold is this card's own rule colour. */
  /* The strip rides in the card's head, beside the word BOUTS, so the list
     below keeps the height a row of its own was costing it. The head therefore
     carries the rule that used to sit under the tabs — same divider, same
     width (both are inside the card's 30px padding), one line further up. It
     is scoped to THIS card because `.mhead` is shared with the settings, the
     score log and the end-of-match panels, none of which have tabs. */
  #queueScrim .mhead{align-items:center;gap:18px;border-bottom:1px solid var(--line);
        padding-bottom:14px;}
  #queueScrim .mtitle{flex-shrink:0;}

  /* Pills, not underlines: an underline marks the live tab by sitting on the
     rule beneath the strip, and in the middle of a header row there is no rule
     under it to sit on. `overflow-x:auto` is insurance only — four short words
     never fill a 1920 canvas — and its bar is hidden because a scrollbar
     inside a header reads as a broken layout. */
  .btabs{display:flex;align-items:center;gap:6px;flex:1;min-width:0;
        overflow-x:auto;scrollbar-width:none;-ms-overflow-style:none;}
  .btabs::-webkit-scrollbar{display:none;}
  .btab{font-family:'Anton',sans-serif;font-size:21px;letter-spacing:.08em;text-transform:uppercase;
        background:var(--fill-alt);border:1px solid var(--line);border-radius:999px;color:var(--faint);
        padding:7px 16px;cursor:pointer;display:flex;align-items:center;gap:8px;white-space:nowrap;
        flex:0 0 auto;transition:color .14s ease,background .14s ease,border-color .14s ease;}
  .btab:hover{color:var(--muted);background:var(--fill);}
  .btab[aria-selected="true"]{background:var(--gold);color:#000;border-color:transparent;}
  .btab .n{font-family:'Inter',system-ui,sans-serif;font-size:14px;font-weight:700;letter-spacing:0;
           background:var(--fill);color:var(--muted);border-radius:999px;padding:1px 8px;}
  /* On the gold pill a gold chip would be invisible — sink it instead. */
  .btab[aria-selected="true"] .n{background:rgba(0,0,0,.22);color:#000;}

  /* A pane owns the whole remaining height and scrolls INSIDE itself, so the
     card never grows past the console it is drawn on. */
  .bpane{flex:1;min-height:0;display:flex;flex-direction:column;gap:12px;}
  .bscroll{flex:1;min-height:0;overflow-y:auto;display:flex;flex-direction:column;gap:8px;padding-right:14px;}
  .bhint{font-size:20px;color:var(--faint);flex-shrink:0;}

  /* The search field, and the one shape every text input on this console has. */
  .bfind{width:100%;font-size:26px;background:var(--inset);border:1px solid var(--line);
         border-radius:10px;color:var(--text);padding:14px 18px;flex-shrink:0;}
  .bfind:focus{outline:none;border-color:var(--gold);}

  /* A division, as a row you press. The count is the point of the row: an
     operator picking a class wants to know how much of it is left. */
  .bdiv{display:grid;grid-template-columns:1fr auto;align-items:center;gap:18px;text-align:left;
        background:var(--ink);color:var(--text);border:1px solid var(--line);border-radius:12px;
        padding:18px 22px;cursor:pointer;font-size:28px;}
  .bdiv:hover{background:var(--fill);}
  .bdiv .sub{display:block;font-size:16px;color:var(--faint);letter-spacing:.04em;margin-top:4px;
             overflow-wrap:anywhere;}
  .bdiv .cnt{font-family:'Anton',sans-serif;font-size:26px;color:var(--gold);white-space:nowrap;}

  /* Back out of a drill-down. A chevron, never a tailed arrow. */
  .bback{display:inline-flex;align-items:center;gap:10px;background:none;border:none;
         color:var(--muted);font-size:22px;cursor:pointer;padding:0;flex-shrink:0;
         letter-spacing:.08em;text-transform:uppercase;font-family:'Anton',sans-serif;}
  .bback:hover{color:var(--text);}

  /* ── Arcade ────────────────────────────────────────────────────────────
     A character select. Two rosters facing each other across the FIGHT
     button, in the corners' own colours, because the operator is picking a
     BLUE and a WHITE and the board will paint them that way. */
  .arc{flex:1;min-height:0;display:grid;grid-template-columns:1fr 300px 1fr;gap:16px;}
  .arccol{min-height:0;display:flex;flex-direction:column;gap:10px;}
  .arccap{font-family:'Anton',sans-serif;font-size:22px;letter-spacing:.1em;text-transform:uppercase;
          flex-shrink:0;}
  .arclist{flex:1;min-height:0;overflow-y:auto;display:flex;flex-direction:column;gap:6px;
           padding-right:10px;}
  .arcrow{display:flex;align-items:center;gap:12px;text-align:left;background:var(--ink);
          color:var(--text);border:1px solid var(--line);border-radius:10px;padding:10px 14px;
          cursor:pointer;font-size:24px;}
  .arcrow:hover{background:var(--fill);}
  .arcrow[aria-pressed="true"]{background:var(--fill);}
  .arcmid{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:16px;
          min-height:0;}
  .arcfight{width:100%;height:96px;border:none;border-radius:14px;font-family:'Anton',sans-serif;
            font-size:42px;letter-spacing:.12em;text-transform:uppercase;cursor:pointer;
            background:var(--fill-alt);color:var(--faint);border:1px solid var(--line);
            transition:background .14s ease,color .14s ease;}
  .arcfight:not(:disabled){background:var(--gold);color:#000;border-color:transparent;
            background-image:linear-gradient(rgba(255,255,255,.28),rgba(255,255,255,0) 60%,rgba(0,0,0,.14));}
  .arcfight:disabled{cursor:not-allowed;}
  .arcsay{font-size:19px;color:var(--faint);text-align:center;line-height:1.35;}
</style>
</head>
<body>

<div id="root"><div id="stage">

  <div style="position:absolute;left:-120px;top:240px;width:540px;height:540px;border-radius:50%;background:radial-gradient(circle,rgba(19,98,209,.16),transparent 70%);filter:blur(30px);animation:orbA 16s ease-in-out infinite;pointer-events:none;"></div>
  <div style="position:absolute;right:-120px;top:180px;width:560px;height:560px;border-radius:50%;background:radial-gradient(circle,rgba(230,235,242,.09),transparent 70%);filter:blur(30px);animation:orbB 19s ease-in-out infinite;pointer-events:none;"></div>

  {{-- ── Top bar ──────────────────────────────────────────────────────────
       Read-only. Every one of these comes from the draw, and the draw is where
       it is corrected — a name fixed here would be right for one match and
       wrong everywhere else the entry appears. --}}
  <div id="ctlTop">
    <div style="display:flex;align-items:center;gap:16px;min-width:0;">
      @php $hostLogo = $event->tenant?->logo ? file_url($event->tenant->logo) : null; @endphp
      @if ($hostLogo)
        {{-- The host's crest, bare on a sizing box (Design Rule #5), and only
             when there is one — never a white tile standing in for it. --}}
        <span style="width:64px;height:64px;flex:0 0 auto;">
          <img src="{{ $hostLogo }}" alt="" style="width:100%;height:100%;object-fit:contain;">
        </span>
      @endif
      <div style="min-width:0;">
        <div style="font-family:'Anton',sans-serif;font-size:34px;letter-spacing:1.36px;text-transform:uppercase;line-height:1;white-space:nowrap;">{{ __('scoreboard::bjj_messages.ctl_title') }}</div>
        <div style="margin-top:6px;font-size:14px;letter-spacing:2.1px;text-transform:uppercase;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $event->title }}</div>
      </div>
    </div>
    <div style="display:flex;align-items:flex-start;gap:44px;margin-left:auto;min-width:0;">
      <div style="display:flex;flex-direction:column;gap:6px;min-width:0;">
        <span class="cap">{{ __('scoreboard::bjj_messages.sb_division') }}</span>
        <div id="topMeta" class="val" style="max-width:640px;"></div>
      </div>
      <div style="display:flex;flex-direction:column;gap:6px;min-width:0;">
        <span class="cap">{{ __('scoreboard::bjj_messages.court_court') }}</span>
        <div class="val">{{ $court }}</div>
      </div>
      <div style="display:flex;flex-direction:column;gap:6px;min-width:0;">
        <span class="cap">{{ __('scoreboard::bjj_messages.ctl_ruleset') }}</span>
        <div id="topRuleset" class="val" style="max-width:260px;"></div>
      </div>
      <div style="display:flex;flex-direction:column;gap:6px;min-width:0;">
        <span class="cap">{{ __('scoreboard::bjj_messages.ctl_referee') }}</span>
        <div id="topReferee" class="val" style="max-width:260px;"></div>
      </div>
      {{-- What the mat itself is doing, in a word and a light. The word is the
           point: a colour on its own never says anything. Not in the draft;
           kept, because it is the only thing on the console that answers "is
           this table still talking to the mat?". --}}
      <div id="liveBadge" style="display:flex;align-items:center;gap:12px;flex-shrink:0;align-self:flex-end;">
        <span id="liveDot"></span>
        <span id="liveText" style="font-size:22px;font-weight:700;letter-spacing:.24em;text-transform:uppercase;color:var(--gold);"></span>
      </div>
    </div>
  </div>

  {{-- ── BLUE | centre | WHITE ──────────────────────────────────────────── --}}
  <div id="ctlBody">

    @foreach ([
        ['blue', __('sport-brazilianjiujitsu::messages.corner_blue'), 'var(--blue)', 'var(--blue-ink)', '1', '.05s', false, '100deg'],
        ['white', __('sport-brazilianjiujitsu::messages.corner_white'), 'var(--white)', 'var(--white)', '3', '.3s', true, '260deg'],
    ] as [$side, $label, $colour, $plateInk, $order, $delay, $mirror, $angle])
    <div class="corner{{ $mirror ? ' mirror' : '' }}" style="order:{{ $order }};animation:cardIn .6s {{ $delay }} cubic-bezier(.2,.8,.2,1) both;">
      <span class="rule" style="background:{{ $colour }};"></span>

      {{-- The score plate: the corner's colour bleeds under its own label, so
           the number is read against the side it belongs to. POINTS only — the
           other two ladders have their own plates below. --}}
      <div class="plate" style="background:linear-gradient({{ $angle }},color-mix(in srgb, {{ $colour }} 22%, transparent),#0a0b10 55%);">
        <div class="who">
          <span class="corner-label" style="color:{{ $plateInk }};">{{ $label }}</span>
          <div class="nameRow">
            <span class="flag" id="{{ $side }}Flag" hidden></span>
            <span class="nm" id="{{ $side }}Name"></span>
          </div>
          <div class="clubRow">
            <span class="crest" id="{{ $side }}Logo" hidden></span>
            <span class="club" id="{{ $side }}Club"></span>
          </div>
        </div>
        <span class="pts" id="{{ $side }}Score">0</span>
      </div>

      {{-- Advantages and penalties, read WITH the score rather than found
           under the buttons that change them. Mirrored on the white side so
           each corner's advantages sit on its own outside edge. --}}
      <div class="ladders">
        @foreach ($mirror ? ['p', 'a'] : ['a', 'p'] as $lad)
          <div class="lad {{ $lad }}">
            <span class="l">{{ $lad === 'a' ? __('scoreboard::bjj_messages.adv_short') : __('scoreboard::bjj_messages.pen_short') }}</span>
            <span class="n" id="{{ $side }}{{ $lad === 'a' ? 'Adv' : 'Pen' }}">0</span>
          </div>
        @endforeach
      </div>

      {{-- Three amounts, two columns: award on the leading edge, correct on
           the trailing one — reversed with the rest of the white corner. The
           runtime fills this grid from
           Ledger::POINT_VALUES, so the console cannot name an amount the
           server does not allow — and the take-back posts `deduct`, which the
           server turns into a reversal or a correction. --}}
      <div class="scoreGrid" id="{{ $side }}ScoreGrid"></div>

      {{-- The two ladders, ONE row of four: advantage give and take, penalty
           give and take. A ladder counts ONE per entry, so the amount is
           always 1 and the take-back is a -1.

           Four across rather than two rows of two, because these are the same
           KIND of act as each other and a row is how this console says so —
           the points grid above stacks by amount, and the ladders have only
           one amount to stack. It also leaves the score grid its height.

           All four land IMMEDIATELY — no dialog, no confirmation, the same
           400ms lockout the scoring buttons have. The penalty used to open a
           reason picker (six choices: stalling, fleeing, grips, illegal
           technique, conduct, other) and that was taken out on 2026-09-12: a
           referee at a mat is looking at two people on the floor, not at a
           list, and a question between the decision and the score is a
           question asked at the worst possible moment. The row is recorded
           with reason `other`, which is the truthful record of a penalty given
           without one being stated.

           (`data-penalty` — the handler that opens that picker — is still in
           the runtime, because the retired thumb console still uses it. It is
           simply no longer wired to anything on THIS console, which is the one
           line to reverse if the picker is ever wanted back here.)

           Each button names its own ladder, so the row is readable whichever
           way round it runs — and in the white corner it runs the other way
           round. The markup below is IDENTICAL in both corners and the mirror
           is done in CSS (`.corner.mirror`), which is what keeps it an exact
           mirror: reverse the row end to end, pairs and halves together, with
           no second reading order to keep in step by hand. --}}
      <div class="cornerActs">
        @foreach (['adv', 'pen'] as $act)
          @if ($act === 'adv')
            <button class="btn lbtn" data-cmd="advantage" data-side="{{ $side }}"
                    aria-label="{{ __('scoreboard::bjj_messages.advantages') }} +1"
                    style="background:var(--gold);border:1px solid var(--gold);color:#000;">
              <span class="v">+1</span><span class="l">{{ __('scoreboard::bjj_messages.adv_short') }}</span>
            </button>
            <button class="btn lbtn minus" data-cmd="deduct" data-side="{{ $side }}" data-ladder="advantage"
                    aria-label="{{ __('scoreboard::bjj_messages.advantages') }} −1"
                    style="color:var(--gold);border:1px solid var(--gold);">
              <span class="v">−1</span><span class="l">{{ __('scoreboard::bjj_messages.adv_short') }}</span>
            </button>
          @else
            <button class="btn lbtn" data-cmd="penalty" data-side="{{ $side }}"
                    aria-label="{{ __('scoreboard::bjj_messages.penalties') }} +1"
                    style="background:var(--alarm);border:1px solid var(--alarm);color:#fff;">
              <span class="v">+1</span><span class="l">{{ __('scoreboard::bjj_messages.pen_short') }}</span>
            </button>
            <button class="btn lbtn minus" data-cmd="deduct" data-side="{{ $side }}" data-ladder="penalty"
                    aria-label="{{ __('scoreboard::bjj_messages.penalties') }} −1"
                    style="color:var(--alarm);border:1px solid var(--alarm);">
              <span class="v">−1</span><span class="l">{{ __('scoreboard::bjj_messages.pen_short') }}</span>
            </button>
          @endif
        @endforeach
      </div>

      {{-- The referee's stalling count STARTS in the corner it is against. The
           countdown it opens, and the decision to turn it into a penalty,
           belong to the centre column — one clock, one place to look. --}}
      <button class="btn stall" data-stall="{{ $side }}"
              style="color:{{ $plateInk }};border:1px solid color-mix(in srgb, {{ $colour }} 45%, transparent);">
        <span class="stallInner">
          {{-- A clock, because the thing this button starts is a COUNT — the
               one control in the corner that sets something running rather
               than recording something that happened.

               Inline SVG in the console's own idiom (stroked paths on
               `currentColor`, like the chevrons further down), not an icon
               font: this document loads no icon set, and adding one for a
               single glyph would be a stylesheet at a mat for nothing. It sits
               INSIDE .stallInner so it breathes with the word under the pump
               animation while a count runs, rather than standing still beside
               a label that is moving. --}}
          <svg class="stallIcon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
               stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="12" cy="12" r="9"></circle>
            <path d="M12 7 L12 12 L15.5 14"></path>
          </svg>
          <span class="stallWord">{{ __('scoreboard::bjj_messages.ctl_stall') }}</span>
          {{-- Painted by the runtime's paintStall(), and only on the corner the
               count is actually against. --}}
          <span class="stallNum" id="{{ $side }}StallCount"></span>
        </span>
      </button>

      {{-- Console only. The hall is never told what is about to happen to
           somebody — it learns of a disqualification when there is one. --}}
      <div id="{{ $side }}WarnDq" hidden class="warnDq">{{ __('scoreboard::bjj_messages.penalty_next_is_dq') }}</div>
    </div>
    @endforeach

    {{-- ── Centre column ───────────────────────────────────────────────────
         The draft's column, plus the two controls it had no slot for. Every
         control here posts a command this package already supports — there are
         no ornamental buttons. --}}
    <div id="ctlCentre" style="animation:cardIn .6s .18s cubic-bezier(.2,.8,.2,1) both;">

      {{-- What this mat does between matches, and the biggest thing on the
           panel after the clock: an official who has just filed a result is
           looking for the next match, not for a menu. --}}
      <button id="btnBouts" class="bigbtn" style="background:var(--gold);color:#000;">
        <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 6 L12 13 L19 6"></path><path d="M5 12 L12 19 L19 12"></path></svg>
        {{ __('scoreboard::bjj_messages.ctl_queue') }}
        <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 6 L12 13 L19 6"></path><path d="M5 12 L12 19 L19 12"></path></svg>
      </button>

      {{-- The clock plate. #clockVal, #clockState and #clockBar are the
           runtime's contract — it re-writes #clockVal's className every tick,
           so the type is set in the stylesheet above where a className
           assignment cannot reach it. --}}
      <div id="ctlClock">
        <div class="head">
          <span class="cap">{{ __('scoreboard::bjj_messages.ctl_bout_timer') }}</span>
          <span id="clockState"></span>
        </div>
        <div id="clockVal" class="num">0:00</div>
        <div id="clockTrack"><div id="clockBar"></div></div>
      </div>

      {{-- One button, both directions — the runtime relabels it
           Start / Pause / Resume from the state, and colours it green to go and
           red while it runs, so a glance from the mat answers "is it
           running?". --}}
      <button id="btnStart" class="bigbtn bout"></button>

      <div class="row">
        {{-- Introduction ⇄ scoreboard. Labelled and coloured for what it will
             do NEXT, from the state rather than from a flag this page keeps:
             a second console, a reload, or the wall being switched by somebody
             else all have to leave this button telling the truth. --}}
        <button id="btnBoard" class="cbtn bout" style="color:var(--blue-ink);border:1px solid rgba(138,180,255,.45);"></button>
        {{-- Opens the end-of-match dialog, so it carries the runtime's own
             #btnEnd id rather than a second copy of that flow. --}}
        <button id="btnEnd" class="cbtn bout" style="background:var(--fill-alt);color:var(--alarm);border:1px solid rgba(255,59,71,.5);">{{ __('scoreboard::bjj_messages.ctl_finish') }}</button>
      </div>

      <div class="row">
        <button id="btnSettings" class="cbtn" style="background:var(--fill-alt);color:var(--gold);border:1px solid var(--gold);">{{ __('scoreboard::bjj_messages.ctl_settings') }}</button>
        <button id="btnLog" class="cbtn" style="background:var(--fill-alt);color:var(--gold);border:1px solid var(--gold);">{{ __('scoreboard::bjj_messages.ctl_score_log') }}</button>
      </div>

      <div class="row">
        <button id="btnReset" class="cbtn bout" style="background:transparent;color:#ff6b78;border:1px solid rgba(255,107,120,.5);">{{ __('scoreboard::bjj_messages.ctl_reset') }}</button>
        {{-- Deliberately NOT disabled with the rest of the match controls: a
             hung screen is most likely to need this when the mat is empty, and
             a button that greys out exactly when you reach for it is worse
             than no button at all. --}}
        <button id="btnResync" class="cbtn" style="background:var(--fill-alt);color:var(--blue-ink);border:1px solid rgba(138,180,255,.45);">{{ __('scoreboard::bjj_messages.ctl_resync') }}</button>
      </div>

      {{-- A level match at the bell is not a result — IBJJF sends it to the
           referee, and the runtime unhides this the moment that happens. On its
           own rather than in a row, so it takes no height at all while it is
           hidden.

           ⚠️ The Record-result button that used to sit beside it is GONE, at
           the user's request (2026-09-10) — the draft's column has no slot for
           it. Consequence, stated rather than discovered on an event day: the
           end-of-match panel offers itself ONCE per match (paintOver), and
           Record was the way BACK to it. A table that dismisses that panel with
           "Later" now has no control that re-opens it, and the result is
           recorded when the next state change re-offers the panel or not at
           all. The confirmation itself is untouched and still lives inside the
           panel on `#btnCommit`. --}}
      <button id="btnDecision" class="cbtn bout" hidden
              style="background:var(--fill-alt);color:var(--gold);border:1px solid var(--gold);">{{ __('scoreboard::bjj_messages.ctl_decision') }}</button>

      {{-- ── The double stalling count, at the foot of the column ──────────
           The count against BOTH men, and the only stalling control that
           belongs in the centre: a single stall is against ONE corner and is
           started from that corner, but a double stall is about the situation
           on the mat rather than about either man, so it has no corner to live
           in.

           LAST in the column, and full width: it is the least-reached control
           here and it is not the second half of anything, so it takes the foot
           rather than a slot between the clock and the things that run a bout.

           `data-stall="both"` goes through the same toggle handler the corner
           buttons use, so pressing it twice cancels, and starting it while a
           single count runs replaces that count rather than running a second
           one beside it. The mat holds ONE count; 'both' is a third value of
           its side, not a second clock.

           At zero it does NOT ask. Neither man was working, so both take a
           stalling penalty and the console applies it itself — see the trigger
           in the runtime's paintClock, and the threshold card that is
           deliberately never shown for this one. --}}
      <button class="btn stall stallBoth" data-stall="both"
              style="color:var(--gold);border:1px solid var(--gold);">
        <span class="stallInner">
          <svg class="stallIcon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
               stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="12" cy="12" r="9"></circle>
            <path d="M12 7 L12 12 L15.5 14"></path>
          </svg>
          <span class="stallWord">{{ __('scoreboard::bjj_messages.ctl_stall_both') }}</span>
          <span class="stallNum" id="bothStallCount"></span>
        </span>
      </button>
    </div>

</div></div></div>

{{-- ── The running order ──────────────────────────────────────────────────
     A modal, because the centre column belongs to the clock: an operator opens
     it, loads a match, and it closes itself. The runtime paints the list and
     owns the Load button; this page owns only the door. --}}
<div id="queueScrim" class="scrim" hidden>
  {{-- Sized to the CONSOLE, not the glass — see `.modal.stagefit` above. --}}
  <div class="modal stagefit">
    {{-- ── The head, and four ways into the same draw ───────────────────
         Asked for 2026-09-10. The running order below is unchanged and is
         still what opens; the three tabs beside it exist because a mat queue
         twelve deep and one day wide cannot answer "where is this man's
         bout", and until now the answer was to go and find a laptop.

         The tabs ride IN the head, on the same line as the word BOUTS, so the
         list keeps the height a strip of its own was costing it — asked for
         the same day. That is also why they are PILLS rather than underlined: an
         underline is an indicator that has to sit on a rule beneath the strip,
         and there is no rule to sit on in the middle of a header row.

         The card owns the DOOR and the tab bar; the runtime owns everything
         painted inside a pane — the same division of labour this card has
         always had. Its `catalogueUrl` is read-only: nothing in here writes,
         and pressing a bout is the same `load` command the running order
         sends. --}}
    <div class="mhead">
      <div class="mtitle" style="font-size:26px;color:var(--green);">{{ __('scoreboard::bjj_messages.ctl_queue') }}</div>

      <div class="btabs" role="tablist">
        <button type="button" class="btab" role="tab" data-btab="arranged" aria-selected="true"
                aria-controls="bpaneArranged">{{ __('scoreboard::bjj_messages.ctl_tab_arranged') }}</button>
        <button type="button" class="btab" role="tab" data-btab="division" aria-selected="false"
                aria-controls="bpaneDivision">{{ __('scoreboard::bjj_messages.ctl_tab_division') }}<span class="n" id="btabDivisionCount" hidden></span></button>
        <button type="button" class="btab" role="tab" data-btab="member" aria-selected="false"
                aria-controls="bpaneMember">{{ __('scoreboard::bjj_messages.ctl_tab_member') }}</button>
        <button type="button" class="btab" role="tab" data-btab="arcade" aria-selected="false"
                aria-controls="bpaneArcade">{{ __('scoreboard::bjj_messages.ctl_tab_arcade') }}</button>
      </div>

      <button id="queueClose" class="mclose" aria-label="{{ __('scoreboard::bjj_messages.ctl_cancel') }}">✕</button>
    </div>

    {{-- ARRANGED — this mat, in the order it will be fought. The original
         card, moved inside a pane and otherwise untouched: same id, same
         painter, same rows. --}}
    <div class="bpane" id="bpaneArranged" role="tabpanel">
      {{-- The rich running order: bout number, stage and division, then the two
           corners facing each other across the "vs" — the same portraits and the
           same club flags the wall board prints, so the table and the hall are
           reading one thing. Painted by the runtime (paintBouts). --}}
      <div id="boutsList" style="flex:1;overflow-y:auto;display:flex;flex-direction:column;gap:8px;min-height:160px;padding-right:14px;"></div>
      <div class="bhint">{{ __('scoreboard::bjj_messages.ctl_bouts_hint') }}</div>
    </div>

    {{-- WEIGHT CLASS — the event's divisions, then one division's bouts. A
         drill-down in the same pane rather than a second card: the operator
         came here to read one list and go back. --}}
    <div class="bpane" id="bpaneDivision" role="tabpanel" hidden>
      <div id="divIndex" class="bpane">
        <div id="divList" class="bscroll"></div>
        <div class="bhint">{{ __('scoreboard::bjj_messages.ctl_division_hint') }}</div>
      </div>
      <div id="divDetail" class="bpane" hidden>
        <div style="display:flex;align-items:center;gap:18px;flex-shrink:0;min-width:0;">
          <button type="button" id="divBack" class="bback">
            <i style="font-style:normal;">‹</i>{{ __('scoreboard::bjj_messages.ctl_all_divisions') }}
          </button>
          <div id="divTitle" style="font-size:22px;color:var(--text);min-width:0;overflow-wrap:anywhere;"></div>
        </div>
        <div id="divBouts" class="bscroll"></div>
      </div>
    </div>

    {{-- MEMBER — type a name, get that person's bouts. Every bout of the
         event, not just this mat's: the question is "where do they fight",
         and answering it only for one mat is answering a different one. --}}
    <div class="bpane" id="bpaneMember" role="tabpanel" hidden>
      <input id="boutFind" class="bfind" type="search" autocomplete="off" spellcheck="false"
             placeholder="{{ __('scoreboard::bjj_messages.ctl_find_member') }}"
             aria-label="{{ __('scoreboard::bjj_messages.ctl_find_member') }}">
      <div id="findBouts" class="bscroll"></div>
      <div class="bhint" id="findHint">{{ __('scoreboard::bjj_messages.ctl_find_hint') }}</div>
    </div>

    {{-- ARCADE — a character select. Pick the blue corner and the white
         corner, and the console finds the bout the DRAW already has between
         them. It never invents one: an exhibition bout would be a row in the
         live event's draw, and a scoreboard is not the place to add one. --}}
    <div class="bpane" id="bpaneArcade" role="tabpanel" hidden>
      <div class="arc">
        <div class="arccol">
          <div class="arccap" style="color:var(--blue-ink);">{{ __('sport-brazilianjiujitsu::messages.corner_blue') }}</div>
          <input id="arcFindA" class="bfind" type="search" autocomplete="off" spellcheck="false"
                 style="font-size:21px;padding:10px 14px;"
                 placeholder="{{ __('scoreboard::bjj_messages.ctl_find_member') }}"
                 aria-label="{{ __('scoreboard::bjj_messages.ctl_find_member') }}">
          <div id="arcListA" class="arclist" data-side="a"></div>
        </div>

        <div class="arcmid">
          <div id="arcVs" class="arcsay"></div>
          <button type="button" id="arcFight" class="arcfight" disabled>{{ __('scoreboard::bjj_messages.ctl_fight') }}</button>
          <div id="arcSay" class="arcsay">{{ __('scoreboard::bjj_messages.ctl_arcade_hint') }}</div>
        </div>

        <div class="arccol">
          <div class="arccap" style="color:var(--white-ink);">{{ __('sport-brazilianjiujitsu::messages.corner_white') }}</div>
          <input id="arcFindB" class="bfind" type="search" autocomplete="off" spellcheck="false"
                 style="font-size:21px;padding:10px 14px;"
                 placeholder="{{ __('scoreboard::bjj_messages.ctl_find_member') }}"
                 aria-label="{{ __('scoreboard::bjj_messages.ctl_find_member') }}">
          <div id="arcListB" class="arclist" data-side="b"></div>
        </div>
      </div>
    </div>
  </div>
</div>


{{-- ── Mat settings ──────────────────────────────────────────────────────
     Not a preference panel. Everything here is posted to the mat state, so a
     second console on the same mat and the wall are all running the match the
     official at this table said it was. --}}
<div id="settings" hidden class="scrim" style="z-index:41;">
  <div class="modal" style="width:1020px;gap:22px;overflow-y:auto;">
    <div class="mhead">
      <div class="mtitle" style="color:var(--gold);">{{ __('scoreboard::bjj_messages.ctl_settings_title') }}</div>
      <button data-close="settings" class="mclose" aria-label="{{ __('scoreboard::bjj_messages.ctl_cancel') }}">✕</button>
    </div>

    <div style="display:flex;gap:8px;border-bottom:1px solid var(--line);padding-bottom:14px;">
      {{-- The camera tab only exists when this door was given the camera
           addresses, so a console with no camera surface shows no camera tab. --}}
      @foreach (array_merge([['rules', __('scoreboard::bjj_messages.ctl_tab_rules')], ['timer', __('scoreboard::bjj_messages.ctl_tab_timer')], ['sounds', __('scoreboard::bjj_messages.ctl_tab_sounds')]], ($cameraUrl ?? null) ? [['cameras', __('events.mat_cameras_tab')]] : []) as [$tab, $tabLabel])
        <button class="stab" data-tab="{{ $tab }}">{{ $tabLabel }}</button>
      @endforeach
    </div>

    {{-- Rules --}}
    <div class="spanel" data-panel="rules" style="display:flex;flex-direction:column;gap:12px;">
      @foreach ([
          ['referee_decision', __('scoreboard::bjj_messages.ctl_rule_referee_decision'), __('scoreboard::bjj_messages.ctl_rule_referee_decision_hint')],
          ['time_up_buzzer', __('scoreboard::bjj_messages.ctl_rule_buzzer'), __('scoreboard::bjj_messages.ctl_rule_buzzer_hint')],
      ] as [$rule, $ruleLabel, $ruleHint])
        <button class="rrow" data-rule="{{ $rule }}">
          <span class="rbox"></span>
          <span style="flex:1;display:flex;flex-direction:column;gap:2px;">
            <span style="font-size:20px;font-weight:600;color:var(--text);letter-spacing:.04em;">{{ $ruleLabel }}</span>
            <span style="font-size:15px;color:var(--faint);letter-spacing:.02em;">{{ $ruleHint }}</span>
          </span>
        </button>
      @endforeach

      {{-- The numbers a rule book sets. Each carries its own value on the row
           it belongs to, rather than being a second place to look.

           ⚠️ The advantage limit's floor is 0, not 1, and that is the whole
           point of it: zero means NO limit, which is the default and is
           jiu-jitsu as it is normally run. The other numbers have no such
           reading — a penalty limit of zero would disqualify a man for
           existing. --}}
      @foreach ([
          ['penaltyLimit', __('scoreboard::bjj_messages.ctl_rule_penalty_limit'), '', 1, 10],
          ['penaltyWarnAt', __('scoreboard::bjj_messages.ctl_rule_penalty_warn'), '', 1, 10],
          ['advantageLimit', __('scoreboard::bjj_messages.ctl_rule_advantage_limit'), __('scoreboard::bjj_messages.ctl_rule_advantage_limit_hint'), 0, 20],
          ['stallSeconds', __('scoreboard::bjj_messages.ctl_rule_stall_seconds'), __('scoreboard::bjj_messages.ctl_rule_stall_seconds_hint'), 3, 60],
      ] as [$numId, $numLabel, $numHint, $numMin, $numMax])
        <div class="rrow" style="cursor:default;">
          <span style="flex:1;display:flex;flex-direction:column;gap:2px;">
            <span style="font-size:20px;font-weight:600;color:var(--text);letter-spacing:.04em;">{{ $numLabel }}</span>
            @if ($numHint)
              <span style="font-size:15px;color:var(--faint);letter-spacing:.02em;">{{ $numHint }}</span>
            @endif
          </span>
          <input id="{{ $numId }}" type="number" min="{{ $numMin }}" max="{{ $numMax }}"
                 style="width:90px;flex:0 0 auto;font-family:'Anton',sans-serif;font-size:26px;text-align:center;background:var(--fill);color:var(--gold);border:none;border-radius:10px;padding:8px 6px;">
        </div>
      @endforeach
    </div>

    {{-- Timer --}}
    <div class="spanel" data-panel="timer" hidden style="display:flex;flex-direction:column;gap:20px;">
      <label style="display:flex;flex-direction:column;gap:8px;">
        <span style="font-size:17px;letter-spacing:.18em;text-transform:uppercase;color:var(--muted);">{{ __('scoreboard::bjj_messages.ctl_duration') }}</span>
        <span style="display:flex;gap:12px;align-items:center;">
          <input id="durMin" type="number" min="0" max="59" value="5" class="mm">
          <span style="font-family:'Anton',sans-serif;font-size:28px;color:var(--muted);">:</span>
          <input id="durSec" type="number" min="0" max="59" value="0" class="mm">
        </span>
      </label>
      <label style="display:flex;flex-direction:column;gap:8px;">
        <span style="font-size:17px;letter-spacing:.18em;text-transform:uppercase;color:var(--muted);">{{ __('scoreboard::bjj_messages.ctl_warning_at') }}</span>
        <span style="display:flex;gap:12px;align-items:center;">
          <input id="warnMin" type="number" min="0" max="59" value="1" class="mm" style="color:var(--gold);">
          <span style="font-family:'Anton',sans-serif;font-size:28px;color:var(--muted);">:</span>
          <input id="warnSec" type="number" min="0" max="59" value="0" class="mm" style="color:var(--gold);">
        </span>
        <span style="font-size:14px;color:var(--faint);letter-spacing:.04em;">{{ __('scoreboard::bjj_messages.ctl_warning_hint') }}</span>
      </label>
    </div>

    {{-- Sounds. Uploading REPLACES: a hall does not want two celebration
         tracks, it wants the right one. Every screen on the event is told to
         reload afterwards, because a board caches the file it fetched.

         The slots are the shared closed list (App\Events\Support\ScreenMedia)
         — one store for every combat scoreboard in the product — but the LABELS
         are this sport's, because "one point" is not a thing that happens on a
         jiu-jitsu mat.

         ⚠️ `minmax(0,1fr)`, never a bare `1fr`: a grid track's floor is its
         MIN-CONTENT, and an uploaded filename is one unbreakable token on a
         `nowrap` line — so a real upload widens its column and pushes the panel
         straight out through the side of the card. The zero floor is what lets
         the ellipsis on `.audioName` actually do its job. --}}
    <div class="spanel" data-panel="sounds" hidden style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:4px 28px;min-width:0;">
      @foreach ([
          'vs_music' => __('events.screen_audio_vs_music'),
          'match_start' => __('events.screen_audio_match_start'),
          'match_end' => __('events.screen_audio_match_end'),
          'winner_music' => __('events.screen_audio_winner_music'),
          'point_1' => __('scoreboard::bjj_messages.ctl_sound_point_1'),
          'point_2' => __('scoreboard::bjj_messages.ctl_sound_point_2'),
          'point_3' => __('scoreboard::bjj_messages.ctl_sound_point_3'),
          'foul' => __('events.screen_audio_foul'),
          'time_up' => __('events.screen_audio_time_up'),
          'atoshi' => __('events.screen_audio_atoshi'),
      ] as $slot => $slotLabel)
        <div style="display:flex;align-items:center;gap:14px;padding:10px 0;min-width:0;">
          <div style="flex:1;min-width:0;">
            <div style="font-size:23px;font-weight:600;color:var(--text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $slotLabel }}</div>
            <div class="audioName" data-slot="{{ $slot }}" style="font-size:17px;margin-top:2px;color:var(--faint);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ __('events.screen_audio_none') }}</div>
          </div>
          <label style="font-size:17px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;background:#7ae582;color:#000;border-radius:10px;padding:11px 16px;cursor:pointer;white-space:nowrap;">
            {{ __('events.screen_audio_choose') }}
            <input type="file" accept="audio/*" class="audioPick" data-slot="{{ $slot }}" style="display:none;">
          </label>
          <button class="audioDrop" data-slot="{{ $slot }}" hidden style="font-size:18px;font-weight:700;background:var(--fill);color:#ff6b78;border:1px solid rgba(255,107,120,.45);border-radius:10px;padding:12px 16px;cursor:pointer;">✕</button>
        </div>
      @endforeach
    </div>

    {{-- The cameras on this mat: telemetry, how they film, and what they have
         filmed. Sport-neutral and shared with every other console. --}}
    @if ($cameraUrl ?? null)
      <div class="spanel" data-panel="cameras" hidden>
        @include('partials.mat-cameras', ['cameraUrl' => $cameraUrl, 'cameraCommandBase' => $cameraCommandBase])
      </div>
    @endif

    <button id="btnApply" style="font-size:24px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;background:var(--gold);color:#000;border:none;border-radius:14px;padding:16px 0;cursor:pointer;">{{ __('scoreboard::bjj_messages.ctl_apply') }}</button>
    <div style="font-size:16px;color:var(--faint);letter-spacing:.04em;text-align:center;">{{ __('scoreboard::bjj_messages.ctl_apply_hint') }}</div>
  </div>
</div>

{{-- ── The end of a match ────────────────────────────────────────────────
     Opens ITSELF the moment the match is over — however it ended — with the
     result, what recording will write, and what runs next on this mat.

     `#btnCommit` is the runtime's own id, so the confirmation it already
     carries (the match number typed in, because a recorded result cannot be
     taken back) applies here unchanged. Painted by paintOver(). --}}
<div id="overPanel" class="scrim" hidden style="z-index:42;">
  {{-- Compacted 2026-09-12, and capped at nine tenths of the console
       (`.modal.stagecap`) so a strip of the board shows all the way round it —
       the match is still there underneath, and a card that covers the whole
       screen says it is not.

       What the compaction actually did, since "smaller" on its own is a
       licence to shrink type until nobody can read it at a mat:
        · the RESULT caption went. The card is under a heading that already
          says "Match over"; a second label naming it was a row of chrome.
        · the two totals moved INSIDE the card, onto one line with the method,
          which is where a reader wants them — "Advantages · 3 — 0" is the
          sentence, and it used to be a 34px line of its own under the card.
        · NEXT became one row instead of a label stacked over a value.
        · the portrait and the big number came down a size each; everything
          else kept its type, because this card is read once, under pressure,
          across a table. --}}
  <div class="modal stagecap" style="width:760px;gap:14px;">
    <div class="mhead">
      <div class="mtitle" style="font-size:26px;color:var(--gold);">{{ __('scoreboard::bjj_messages.ctl_over_title') }}</div>
      <button data-close="overPanel" class="mclose" aria-label="{{ __('scoreboard::bjj_messages.ctl_cancel') }}">✕</button>
    </div>

    {{-- The result — THE SAME CARD the end-of-match dialog picks a winner
         with, showing the man who won it.

         Deliberately the same object twice. An official chooses a winner by
         tapping his card, and then confirms the result by reading his card:
         the face, the flag, the club and the numbers are in the same places
         both times, so the confirmation is a recognition rather than a second
         reading.

         A DIV, not a button, and no `.pick` class: here the card is the
         answer, not the question. Its colour is the WINNER's and is therefore
         set at runtime (paintOver) rather than baked per side in Blade the way
         the two pick cards are. Unmirrored whichever corner won — the white
         card reverses in the end dialog because it sits opposite the blue one,
         and a lone card has nothing to sit opposite. --}}
    <div id="overCard" hidden class="overWon"
         style="border-radius:16px;padding:18px;display:flex;gap:18px;color:var(--text);">
      {{-- A PORTRAIT, three wide to four tall — the shape every screen in this
           package draws a person in. --}}
      <span id="overPhoto"
            style="width:132px;height:176px;flex:0 0 auto;border-radius:10px;background:var(--fill);background-size:cover;background-position:center 15%;"></span>
      <span style="flex:1;display:flex;flex-direction:column;gap:6px;min-width:0;">
        {{-- ⚠️ The card did not SAY he had won, and that is not a cosmetic
             gap: a disqualification hands the bout to the man with fewer
             points, so the card was showing BLUE with 4 against 7 and nothing
             anywhere saying which of them that made him. An official reading
             it had to infer the result from the method. Now it is stated, in
             the gold this console keeps for the affirmative, beside the corner
             it belongs to. --}}
        <span style="display:flex;align-items:center;justify-content:space-between;gap:12px;min-width:0;">
          <span id="overCorner" style="min-width:0;font-size:16px;font-weight:700;letter-spacing:.19em;text-transform:uppercase;overflow-wrap:anywhere;"></span>
          <span id="overWinner" style="flex:0 0 auto;background:var(--gold);color:#000;border-radius:999px;padding:5px 16px;font-size:15px;font-weight:800;letter-spacing:.2em;text-transform:uppercase;">{{ __('scoreboard::bjj_messages.winner') }}</span>
        </span>
        <span style="display:flex;align-items:center;gap:10px;min-width:0;">
          <span id="overFlag" hidden style="width:36px;height:24px;flex:0 0 auto;border-radius:4px;border:1px solid rgba(255,255,255,.2);background-size:100% 100%;background-position:center;"></span>
          <span id="overName" style="font-size:25px;font-weight:700;letter-spacing:.02em;text-transform:uppercase;line-height:1.05;overflow-wrap:anywhere;"></span>
        </span>
        <span style="display:flex;align-items:center;gap:8px;min-width:0;">
          <span id="overLogo" hidden style="width:26px;height:26px;flex:0 0 auto;background-size:contain;background-repeat:no-repeat;background-position:center;"></span>
          <span id="overClub" style="font-size:18px;font-weight:600;letter-spacing:.07em;text-transform:uppercase;color:var(--muted);overflow-wrap:anywhere;"></span>
        </span>
        <span style="display:flex;align-items:flex-end;gap:18px;margin-top:auto;">
          <span id="overScore" style="font-family:'Anton',sans-serif;font-size:72px;line-height:.8;font-variant-numeric:tabular-nums;">0</span>
          <span style="display:flex;flex-direction:column;gap:3px;padding-bottom:3px;">
            <span id="overAdv" style="font-size:20px;font-weight:700;letter-spacing:.09em;text-transform:uppercase;color:var(--gold);"></span>
            <span id="overPen" style="font-size:20px;font-weight:700;letter-spacing:.09em;text-transform:uppercase;color:var(--alarm);"></span>
          </span>
        </span>

        {{-- HOW it was won, and what he beat, on ONE line — separated by a
             hairline rather than by a gap, so the pair reads as one sentence
             and costs one row instead of two. The totals sit hard right
             because that is where a tabular number belongs; the method starts
             at the same left edge as everything above it. --}}
        <span style="display:flex;align-items:center;gap:14px;min-width:0;padding-top:8px;border-top:1px solid rgba(255,255,255,.12);">
          <span id="overHow" style="flex:1;min-width:0;font-size:17px;font-weight:700;letter-spacing:.11em;text-transform:uppercase;color:var(--gold);overflow-wrap:anywhere;"></span>
          {{-- Both totals, each NAMED. "4 — 7" on its own does not say whose
               4 it is, and on a card that can show either corner there is
               nothing to infer it from. The winner's own number is painted in
               the ink of the card; the other is muted, so the pair reads as a
               result rather than as two numbers. --}}
          <span style="flex:0 0 auto;display:flex;align-items:baseline;gap:8px;font-family:'Anton',sans-serif;font-size:26px;line-height:1;font-variant-numeric:tabular-nums;letter-spacing:.03em;">
            <span id="overTotalsA" style="display:flex;align-items:baseline;gap:6px;"></span>
            <span style="color:var(--faint);">—</span>
            <span id="overTotalsB" style="display:flex;align-items:baseline;gap:6px;"></span>
          </span>
        </span>
      </span>
    </div>

    {{-- A level bout has no card to draw, because it has no winner yet. --}}
    <div id="overNone" hidden
         style="border:1px solid var(--line-2);border-radius:16px;padding:20px 22px;font-family:'Anton',sans-serif;font-size:46px;line-height:1.05;color:var(--text);"></div>

    {{-- What the mat runs next, so "and off to the next match" is a thing the
         table can see before it presses, not a surprise afterwards. ONE row:
         the caption is a fixed-width plate on the left, so the bout beside it
         starts on the same vertical line whatever the caption says in any
         language. --}}
    <div style="display:flex;align-items:baseline;gap:14px;min-width:0;">
      <span style="flex:0 0 auto;font-size:16px;letter-spacing:.16em;text-transform:uppercase;color:var(--faint);">{{ __('scoreboard::bjj_messages.ctl_over_next') }}</span>
      <span id="overNext" style="flex:1;min-width:0;font-size:23px;color:var(--text);overflow-wrap:anywhere;"></span>
    </div>

    {{-- The two ways out, side by side and nothing else in the row. --}}
    <div style="display:grid;grid-template-columns:1fr 1.6fr;gap:14px;">
      <button data-close="overPanel" class="cbtn" style="min-height:64px;color:var(--muted);border:1px solid var(--line-2);">{{ __('scoreboard::bjj_messages.ctl_over_later') }}</button>
      <button id="btnCommit" style="font-size:22px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;background:var(--gold);color:#000;border:none;border-radius:14px;padding:18px 0;cursor:pointer;">{{ __('scoreboard::bjj_messages.ctl_over_record') }}</button>
    </div>

    {{-- What Record actually does, across the FULL width of the card.

         It was tucked into the right-hand column under the button, which read
         as a caption belonging to that button and boxed four lines of text
         into 1.6 of 2.6 columns — a tall narrow paragraph beside a short one.
         This sentence is the last word on an act that cannot be taken back, so
         it gets the whole width and sets in two comfortable lines instead of
         four cramped ones. --}}
    <div style="font-size:14px;line-height:1.4;color:var(--faint);letter-spacing:.02em;">{{ __('scoreboard::bjj_messages.ctl_over_hint') }}</div>
  </div>
</div>

{{-- ── The score log ─────────────────────────────────────────────────────
     Every entry this mat has recorded, newest first, each one reversible on a
     1s hold — which is the ONLY undo on this console.

     Sized like the running order next door, and deliberately: the two are the
     same card, so they share `.modal.stagefit` and both land inside the
     console's own board rather than over the glass around it. --}}
<div id="logScrim" class="scrim" hidden style="z-index:41;">
  <div class="modal stagefit">
    <div class="mhead">
      <div class="mtitle" style="font-size:26px;color:var(--gold);">{{ __('scoreboard::bjj_messages.ctl_score_log') }}</div>
      <button data-close="logScrim" class="mclose" aria-label="{{ __('scoreboard::bjj_messages.ctl_cancel') }}">✕</button>
    </div>
    {{-- The empty state rides on the element itself (`#log:empty::before` reads
         this attribute), so an untouched mat says so without the runtime
         needing to know about a second element it might forget to hide. --}}
    <div id="log" data-empty="{{ __('scoreboard::bjj_messages.ctl_log_empty') }}"></div>
    <div style="font-size:20px;color:var(--faint);">{{ __('scoreboard::bjj_messages.ctl_log_hint') }}</div>
  </div>
</div>

{{-- ── Hardware — NO LONGER REACHABLE ────────────────────────────────────
     Its button became the score log above. The markup is left in place rather
     than deleted in passing (CLAUDE.md → House Cleaning) and is registered in
     Documentation/HOUSE-CLEANING.md; it is `hidden`, so it costs a reader
     nothing. Give it an opener again and it works exactly as it did. --}}
<div id="hardware" hidden class="scrim" style="z-index:41;">
  <div class="modal" style="width:640px;">
    <div class="mhead">
      <div class="mtitle" style="color:var(--text);">{{ __('scoreboard::bjj_messages.ctl_hardware') }}</div>
      <button data-close="hardware" class="mclose" aria-label="{{ __('scoreboard::bjj_messages.ctl_cancel') }}">✕</button>
    </div>
    <div style="display:flex;flex-direction:column;align-items:center;gap:10px;padding:40px 0;color:var(--faint);">
      <span style="font-size:40px;">⌁</span>
      <div style="font-size:22px;letter-spacing:.06em;">{{ __('scoreboard::bjj_messages.ctl_hardware_empty') }}</div>
      <div style="font-size:17px;letter-spacing:.04em;text-align:center;">{{ __('scoreboard::bjj_messages.ctl_hardware_hint') }}</div>
    </div>
  </div>
</div>

{{-- ── Finish: the Match result card ─────────────────────────────────────
     The draft's card (drafts/Score Control Board.html), adopted whole and wired
     to the real ending. It REPLACES the shared three-question dialog that
     Finish used to raise — a winner choice, a method choice and a note, asked
     as three rows of chips with no scoreline in sight, on the one screen where
     picking the wrong man writes the wrong name into a bracket. Here the two
     corners are shown as the hall sees them, with their portraits, their clubs
     and their three counters, and the choice is made by tapping the man.

     What it does NOT change: the ending itself. Declaring posts the same
     `end` command with the same three fields, through the same guards —
     a method that names somebody is refused with nobody named
     (Scoring::METHODS_NEEDING_WINNER), and a disqualification still takes the
     match number typed in, because it is the one ending an athlete disputes.
     The winning types are the SERVER's list (MatState::WIN_METHODS), not the
     draft's four.

     Painted by the runtime's paintEnd(). z-index 44: above the end-of-match
     panel it hands over to (42), below the shared dialog (50) that asks for the
     match number. --}}
<div id="endPanel" class="scrim" hidden style="z-index:44;">
  {{-- Compacted and CAPPED 2026-09-12. It was 1100px of authored width with no
       height bound at all, and `.modal` sets `max-height:94%` without an
       `overflow` — so on any console that scaled down, the winning-type grid
       and the two big buttons under it simply painted OUTSIDE the card, over
       the scrim, floating on the board. `.modal.stagecap` fixes both halves:
       nine tenths of the console at most, and the card scrolls rather than
       leaking.

       Every cut below removes a ROW or a fixed height, never legibility: this
       card is read once, under pressure, across a table, and the two names on
       it decide what goes into a bracket. --}}
  <div class="modal stagecap" style="width:1040px;gap:16px;">
    {{-- The head, on one line. "Ending the match" used to sit on its own row
         above a 56px title saying nearly the same thing; it is now the eyebrow
         beside it, which is the job it was doing anyway. --}}
    <div style="display:flex;align-items:baseline;justify-content:center;gap:16px;flex-wrap:wrap;">
      <div style="font-family:'Anton',sans-serif;font-size:40px;letter-spacing:.04em;text-transform:uppercase;line-height:1;">{{ __('scoreboard::bjj_messages.ctl_end_title') }}</div>
      <div id="endWhy" style="font-size:17px;font-weight:700;letter-spacing:.25em;text-transform:uppercase;color:var(--alarm);"></div>
    </div>

    <div style="display:grid;grid-template-columns:1fr auto 1fr;gap:18px;align-items:stretch;">
      @foreach ([
          ['blue', __('sport-brazilianjiujitsu::messages.corner_blue'), 'var(--blue)', 'var(--blue-ink)', false, '140deg'],
          ['white', __('sport-brazilianjiujitsu::messages.corner_white'), 'var(--white)', 'var(--white)', true, '220deg'],
      ] as [$side, $label, $colour, $ink, $mirror, $angle])
        @if ($mirror)
          <span style="align-self:center;font-family:'Anton',sans-serif;font-size:40px;color:var(--faint);">{{ __('scoreboard::bjj_messages.vs') }}</span>
        @endif
        {{-- The whole card is the control: an official taps the MAN, not a chip
             with his colour on it. --}}
        <button type="button" data-end-pick="{{ $side }}" id="end{{ ucfirst($side) }}Card" class="pick"
                style="cursor:pointer;text-align:start;background:linear-gradient({{ $angle }},color-mix(in srgb, {{ $colour }} 22%, transparent),#0a0b10 60%);border:1px solid color-mix(in srgb, {{ $colour }} 45%, transparent);border-radius:16px;padding:16px;display:flex;{{ $mirror ? 'flex-direction:row-reverse;' : '' }}gap:16px;color:var(--text);">
          {{-- A PORTRAIT, three wide to four tall — the shape every screen in
               this package draws a person in. The drawn stand-in sits back a
               little so it never reads as a photograph. --}}
          <span id="end{{ ucfirst($side) }}Photo"
                style="width:120px;height:160px;flex:0 0 auto;border-radius:10px;background:var(--fill);border:2px solid {{ $colour }};background-size:cover;background-position:center 15%;"></span>
          <span style="flex:1;display:flex;flex-direction:column;gap:5px;min-width:0;{{ $mirror ? 'align-items:flex-end;' : '' }}">
            <span style="font-size:15px;font-weight:700;letter-spacing:.19em;text-transform:uppercase;color:{{ $ink }};">{{ __('scoreboard::bjj_messages.ctl_end_corner', ['corner' => $label]) }}</span>
            <span style="display:flex;{{ $mirror ? 'flex-direction:row-reverse;' : '' }}align-items:center;gap:9px;min-width:0;">
              <span id="end{{ ucfirst($side) }}Flag" hidden style="width:34px;height:23px;flex:0 0 auto;border-radius:4px;border:1px solid rgba(255,255,255,.2);background-size:100% 100%;background-position:center;"></span>
              <span id="end{{ ucfirst($side) }}Name" style="font-size:23px;font-weight:700;letter-spacing:.02em;text-transform:uppercase;line-height:1.05;overflow-wrap:anywhere;"></span>
            </span>
            <span style="display:flex;{{ $mirror ? 'flex-direction:row-reverse;' : '' }}align-items:center;gap:8px;min-width:0;">
              <span id="end{{ ucfirst($side) }}Logo" hidden style="width:24px;height:24px;flex:0 0 auto;background-size:contain;background-repeat:no-repeat;background-position:center;"></span>
              <span id="end{{ ucfirst($side) }}Club" style="font-size:17px;font-weight:600;letter-spacing:.07em;text-transform:uppercase;color:var(--muted);overflow-wrap:anywhere;"></span>
            </span>
            {{-- Points big, the other two ladders beside them. They move the
                 score now (see Tally) but they are still their own numbers. --}}
            <span style="display:flex;{{ $mirror ? 'flex-direction:row-reverse;' : '' }}align-items:flex-end;gap:16px;margin-top:auto;">
              <span id="end{{ ucfirst($side) }}Score" style="font-family:'Anton',sans-serif;font-size:64px;line-height:.8;font-variant-numeric:tabular-nums;">0</span>
              <span style="display:flex;flex-direction:column;gap:3px;padding-bottom:3px;{{ $mirror ? 'align-items:flex-end;' : '' }}">
                <span id="end{{ ucfirst($side) }}Adv" style="font-size:19px;font-weight:700;letter-spacing:.09em;text-transform:uppercase;color:var(--gold);"></span>
                <span id="end{{ ucfirst($side) }}Pen" style="font-size:19px;font-weight:700;letter-spacing:.09em;text-transform:uppercase;color:var(--alarm);"></span>
              </span>
            </span>
            {{-- What the score says, in words, so the tap is a confirmation
                 rather than a guess. --}}
            <span id="end{{ ucfirst($side) }}Note" style="font-size:16px;font-weight:700;letter-spacing:.11em;text-transform:uppercase;color:var(--gold);min-height:20px;"></span>
          </span>
        </button>
      @endforeach
    </div>

    {{-- Winning type. The caption is a plate on the LEFT of the grid rather
         than a centred row above it: it saves the row, and the methods then
         start on the same line they wrap on. The grid is still the SERVER's
         list walked in order — never the draft's four, or a console could
         offer an ending the engine refuses. --}}
    <div style="display:flex;align-items:flex-start;gap:14px;">
      <span style="flex:0 0 auto;width:104px;padding-top:8px;font-size:16px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--muted);line-height:1.15;">{{ __('scoreboard::bjj_messages.ctl_end_winning_type') }}</span>
      <div style="flex:1;min-width:0;display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px;">
        @foreach ($winMethods as $method)
          <button type="button" data-end-method="{{ $method }}" class="mbtn"
                  style="min-height:50px;padding:6px 8px;border-radius:12px;border:1px solid var(--gold);background:var(--fill-alt);color:var(--gold);font-size:18px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;line-height:1.1;cursor:pointer;">{{ __('scoreboard::bjj_messages.method_'.$method) }}</button>
        @endforeach
      </div>
    </div>

    {{-- The reason, on ONE row — its caption on the same plate width as the
         one above it, so the two captions and the two fields line up. --}}
    <label style="display:flex;align-items:center;gap:14px;">
      <span style="flex:0 0 auto;width:104px;font-size:16px;letter-spacing:.14em;text-transform:uppercase;color:var(--muted);">{{ __('scoreboard::bjj_messages.ctl_reason') }}</span>
      <input id="endNote" type="text" maxlength="200"
             style="flex:1;min-width:0;font-size:21px;background:var(--inset);border:1px solid var(--line);border-radius:10px;color:var(--text);padding:10px 14px;">
    </label>

    <div style="display:grid;grid-template-columns:2fr 1fr;gap:10px;">
      <button id="endDeclare" type="button" class="cbtn" style="height:64px;min-height:0;border:none;background:var(--green);color:#000;font-size:27px;font-weight:800;letter-spacing:.1em;background-image:linear-gradient(rgba(255,255,255,.28),rgba(255,255,255,0) 60%,rgba(0,0,0,.14));">{{ __('scoreboard::bjj_messages.ctl_end_declare') }}</button>
      <button data-close="endPanel" type="button" class="cbtn" style="height:64px;min-height:0;background:transparent;border:1px solid var(--line);color:var(--muted);font-size:22px;letter-spacing:.1em;">{{ __('scoreboard::bjj_messages.ctl_cancel') }}</button>
    </div>
  </div>
</div>

{{-- ── The stalling threshold ────────────────────────────────────────────
     The draft's card, adopted whole: the countdown in a corner's own Stalling
     button has run out, and the referee is asked ONCE, on something that
     appeared by itself rather than a button they had to find.

     It is opened and closed by the runtime's paintStall() off the console's
     private stall payload — never by a timer this page keeps — so a second
     console at the same mat raises the same card, and a reload mid-count does
     not lose it.

     Two ways out, which is the draft's point: a PENALTY against the corner that
     stalled, or POINTS to the corner that was being stalled against. Dismiss is
     the third — it cancels the count, because the server holds the stall until
     one of the three lands.

     ⚠️ The three amounts are NOT this page's. They are
     Ledger::STALL_AWARDS, printed here from the server's own list and checked
     again by both the endpoint and Scoring, and the receiving corner is read
     off the mat state rather than sent from here. They are deliberately not in
     POINT_SOURCES: the console builds its six scoring buttons by walking that
     list, and an entry there would put a "stalling award" button in both
     corners to be pressed at any moment for no reason.

     z-index 43: above the end-of-match panel (42) it can interrupt, below the
     shared dialog (50), which is where a penalty's own questions are asked. --}}
<div id="stallPrompt" class="scrim" hidden style="z-index:43;">
  <div class="modal" style="width:680px;gap:12px;">
    {{-- The accent is the STALLING corner's colour, painted by the runtime, so
         the card names the side twice: once in words, once in the rule above
         the words. --}}
    <span id="stallPromptAccent" style="position:absolute;left:24px;right:24px;top:-2px;height:4px;border-radius:4px;background:var(--gold);"></span>
    <div style="font-size:18px;font-weight:700;letter-spacing:.22em;text-transform:uppercase;color:var(--gold);">{{ __('scoreboard::bjj_messages.ctl_stall_threshold') }}</div>
    <div id="stallPromptTitle" style="font-family:'Anton',sans-serif;font-size:52px;letter-spacing:.03em;text-transform:uppercase;line-height:1;margin-bottom:6px;"></div>

    <button id="stallPromptApply" class="cbtn"
            style="height:88px;font-size:32px;font-weight:800;letter-spacing:.08em;background:var(--alarm);color:#fff;border:none;"></button>

    {{-- Or points the other way. Green, because it is the only thing on this
         card that ADDS to a score — the red button above it takes away. --}}
    <div style="display:flex;flex-direction:column;gap:8px;">
      <div id="stallPromptAwardTo" style="font-size:22px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--green);text-align:center;"></div>
      <div style="display:grid;grid-template-columns:repeat({{ count(\App\Scoreboard\Sports\BrazilianJiuJitsu\Mat\Ledger::STALL_AWARDS) }},1fr);gap:10px;">
        @foreach (\App\Scoreboard\Sports\BrazilianJiuJitsu\Mat\Ledger::STALL_AWARDS as $award)
          <button type="button" data-stall-award="{{ $award }}" class="cbtn"
                  style="height:88px;border:none;background:var(--green);color:#000;font-family:'Anton',sans-serif;font-size:44px;letter-spacing:.02em;background-image:linear-gradient(rgba(255,255,255,.28),rgba(255,255,255,0) 60%,rgba(0,0,0,.14));">+{{ $award }}</button>
        @endforeach
      </div>
    </div>

    <button id="stallPromptDismiss" class="cbtn"
            style="height:70px;font-size:26px;letter-spacing:.1em;background:transparent;color:var(--muted);border:1px solid var(--line);">{{ __('scoreboard::bjj_messages.ctl_stall_dismiss') }}</button>
  </div>
</div>

{{-- ── The one dialog every other decision goes through ─────────────────── --}}
<div id="scrim" hidden>
  <div id="modal" role="dialog" aria-modal="true">
    <div id="modalTitle"></div>
    <div id="modalBody"></div>
    <div id="modalActions">
      <button class="btn small" id="modalCancel">{{ __('scoreboard::bjj_messages.ctl_cancel') }}</button>
      <button class="btn small ok" id="modalOk">{{ __('scoreboard::bjj_messages.ctl_confirm') }}</button>
    </div>
  </div>
</div>

{{-- The toast is a NOTICE now, not a decision: no Undo button on it. Undo
     lives in the score log, on the row it belongs to. --}}
<div id="toast" hidden>
  <span id="toastText"></span>
</div>

<script>
(function () {
  'use strict';

  /* The stage scales; it never reflows. A fixed 16:9 canvas, so there is
     nothing to measure: the scale is the glass against 1920x1080 and the
     console is letterboxed inside it. */
  var root = document.getElementById('root');
  function fit() {
    var r = root.getBoundingClientRect();
    if (!r.width || !r.height) return;
    var k = Math.min(r.width / 1920, r.height / 1080);
    root.style.setProperty('--stage-scale', k);
    // The pop-ups hang off <body>, outside the stage, so they cannot inherit
    // this — publish it where they can read it, or a 1100px card is drawn at
    // full size beside a console shrunk to 60%.
    document.documentElement.style.setProperty('--stage-scale', k);
  }
  (window.ResizeObserver ? new ResizeObserver(fit).observe(root) : window.addEventListener('resize', fit));
  fit();

  /* Every door on this console: one opener each, one closer each, and one
     Escape that shuts all of them. Everything INSIDE a card belongs to the
     runtime — it paints the bouts list and owns the settings values — so this
     owns only the opening and the closing. */
  function panel(id) { return document.getElementById(id); }
  function open(id, on) { var p = panel(id); if (p) p.hidden = !on; }

  document.getElementById('btnBouts').addEventListener('click', function () { open('queueScrim', true); });
  document.getElementById('queueClose').addEventListener('click', function () { open('queueScrim', false); });
  document.getElementById('btnLog').addEventListener('click', function () { open('logScrim', true); });

  // A card closes on its own scrim, never on a click inside it: adjusting two
  // rules is one visit, not two.
  ['queueScrim', 'settings', 'logScrim', 'overPanel', 'endPanel', 'hardware'].forEach(function (id) {
    var p = panel(id);
    if (p) p.addEventListener('click', function (e) { if (e.target === p) open(id, false); });
  });

  // The ✕ in a card's own head, wherever it is.
  document.querySelectorAll('[data-close]').forEach(function (b) {
    b.addEventListener('click', function () { open(b.getAttribute('data-close'), false); });
  });

  // Loading a bout closes the running order — the runtime sends the command.
  document.getElementById('boutsList').addEventListener('click', function (e) {
    if (e.target.closest('button')) open('queueScrim', false);
  });

  /* The bouts card's four tabs.
     The card owns which pane is showing; the runtime owns what is drawn in
     one. Switching therefore does two things and no more: move the selection,
     and tell the runtime which pane is live so it can fetch the draw the first
     time one needs it. A runtime that has not loaded yet simply is not told —
     the hook is guarded, and the arranged pane needs nothing from it. */
  (function () {
    var bar = document.querySelector('#queueScrim .btabs');
    if (!bar) return;

    var PANE = { arranged: 'bpaneArranged', division: 'bpaneDivision',
                 member: 'bpaneMember', arcade: 'bpaneArcade' };

    function show(name) {
      if (!PANE[name]) return;

      bar.querySelectorAll('.btab').forEach(function (t) {
        t.setAttribute('aria-selected', t.getAttribute('data-btab') === name ? 'true' : 'false');
      });
      Object.keys(PANE).forEach(function (k) {
        var pane = panel(PANE[k]);
        if (pane) pane.hidden = k !== name;
      });

      if (typeof window.__bjjBoutsTab === 'function') window.__bjjBoutsTab(name);
    }

    bar.addEventListener('click', function (e) {
      var tab = e.target.closest('.btab');
      if (tab) show(tab.getAttribute('data-btab'));
    });

    // Every tab opens on the running order, which is the question ninety-nine
    // times in a hundred — a card that reopens on whatever was last browsed
    // would hide the next bout behind a search somebody ran an hour ago.
    document.getElementById('btnBouts').addEventListener('click', function () { show('arranged'); });

    /* A bout pressed in ANY pane closes the card, exactly as one pressed in
       the running order does. Delegated on the card and gated on the runtime's
       own marker, so pressing a division row or a roster name — which are not
       bouts — leaves the card open where the operator is still reading. */
    document.getElementById('queueScrim').addEventListener('click', function (e) {
      if (e.target.closest('[data-bout-load]')) open('queueScrim', false);
    });
  })();

  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    ['queueScrim', 'settings', 'logScrim', 'overPanel', 'endPanel', 'hardware'].forEach(function (id) { open(id, false); });
  });
})();
</script>

@include('scoreboard::bjj.scoreboard.runtime')

@if (! empty($screenLink))
@include('scoreboard::bjj.partials.screen-link')
@endif
</body>
</html>
