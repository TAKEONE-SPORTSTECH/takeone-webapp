{{--
    Tournament-bracket runtime — the one board every bracketed event type draws.

    ── The design is the Combat Sports Bracket (drafts/Combat Sports Bracket.html)

    Replaced wholesale on 2026-09-20 at the owner's instruction: "replace the
    current brackets system completely with the following one — take exactly
    everything along with its rules and styling and colours". So the surface is
    the draft's: a navy hall (#0b132b → #1e2c4f) with gold furniture, white bout
    cards carrying a Match/Mat strip, a red corner and a blue corner down the
    leading edge of each slot, a 34px face, the flag beside the name and the
    club under it, a score box in the corner's own colour, gold elbows between
    decided bouts and slate ones between undecided, the next bout pulsing, the
    champion on a gold card with confetti, and the entrants waiting in a
    holding basket in the corner.

    ── What did NOT change, deliberately (RULE #1)

    Every contract this board already had is kept exactly:

      · the data feed — App\Events\Support\BracketView, one shape per sport;
      · the write doors — me.events.bracket.arrange / .clear, and every rule
        about what a legal arrangement is still lives server-side;
      · the public API below (window.BracketBoard) and every event it
        dispatches, so x-draw-stage, the outcome sheet, the public wall sheet
        and the running-order board are untouched;
      · pan/zoom mechanics, lifted from the family-tree runtime — the only
        pan/zoom in this project that survives a scrollable mobile shell and
        the Android WebView;
      · arrange mode's drag AND its two-tap/keyboard equivalent;
      · group standings, the withheld-draw veil, the locked badge, realtime.

    Pure logic + node styling (no page chrome), so editing a page layout can
    never break the renderer. Included INLINE inside the content section so it
    re-executes after a mobile-shell AJAX swap. Guarded so re-running is safe.

    Public API (window.BracketBoard):
      mount(cfg)      — cfg: { viewportId, dataUrl, arrangeUrl, clearUrl, csrf,
                               eventUuid, rtl, text, myCompetitorIds }
      reload()        — re-fetch and re-render (called on realtime nudges)
      show(id)        — switch to a division
      zoomIn/zoomOut/fit()
      toggleArrange() — enter/leave arrange mode (no-op without permission)

    Events dispatched on the viewport element:
      bracket:loaded   { divisions, division, canArrange, locked }
      bracket:state    { arrange, picked, division, divisions }
      bracket:match    { match, division }   — a bout was tapped (view mode)
--}}

<style id="bk-styles">
    /* ── The hall ──────────────────────────────────────────────────────────
       Navy, not white. The board is the thing being looked at from across a
       room, and every colour below is chosen against this one. */
    .bk-viewport{position:relative;overflow:hidden;touch-action:none;cursor:grab;
        background:linear-gradient(160deg,#0b132b 0%,#1e2c4f 100%);
        color:#eef1f6;isolation:isolate;}
    .bk-viewport:active{cursor:grabbing;}
    .bk-viewport.is-arranging{cursor:default;}
    .bk-canvas{position:absolute;top:0;left:0;transform-origin:0 0;will-change:transform;}
    .bk-links{position:absolute;top:0;left:0;pointer-events:none;overflow:visible;}
    .bk-layer{position:relative;}

    /* ── Round headings: gold, wide, quiet ─────────────────────────────── */
    .bk-round-label{position:absolute;text-align:center;font-size:1.05rem;font-weight:700;
        letter-spacing:.18em;text-transform:uppercase;color:#d8b25f;line-height:1.2;
        white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}

    /* ── One bout ──────────────────────────────────────────────────────── */
    .bk-match{position:absolute;border-radius:10px;overflow:hidden;background:#fff;color:#1e2c4f;
        box-shadow:0 8px 24px rgba(11,19,43,.35);
        opacity:0;animation:bkIn .6s cubic-bezier(.2,.8,.2,1) forwards;}
    @keyframes bkIn{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:none}}

    /* The bout that is next up breathes. Same keyframes as the draft, and it
       is a box-shadow so it never moves the card a pixel. */
    @keyframes bkPulse{
        0%,100%{box-shadow:0 0 0 3px #d8b25f,0 0 16px 4px rgba(216,178,95,.55),0 8px 24px rgba(11,19,43,.35)}
        50%{box-shadow:0 0 0 6px #edcd85,0 0 44px 14px rgba(216,178,95,.95),0 8px 24px rgba(11,19,43,.35)}
    }
    .bk-match.is-current{animation:bkIn .6s cubic-bezier(.2,.8,.2,1) forwards,
        bkPulse 1.8s ease-in-out .6s infinite;}
    /* The reader's own bout, told apart by a ring rather than by a colour —
       gold already means "decided" everywhere else on this board. */
    .bk-match.is-mine{box-shadow:0 0 0 3px #2f5fae,0 8px 24px rgba(11,19,43,.35);}
    .bk-match.is-live .bk-meta{background:#fdf6e6;color:#8a6d20;}

    .bk-meta{display:flex;align-items:center;justify-content:space-between;gap:8px;
        padding:4px 10px;font-size:.58rem;font-weight:600;letter-spacing:.1em;
        text-transform:uppercase;color:#5d6880;background:#eef1f6;
        border-bottom:1px solid #d5dae3;white-space:nowrap;}
    .bk-meta-r{color:#8a6d20;}

    /* ── One corner ────────────────────────────────────────────────────── */
    .bk-slot{display:flex;align-items:center;gap:8px;padding:8px 10px;position:relative;
        border-inline-start:3px solid transparent;background:#fff;
        transition:background .16s,opacity .16s,box-shadow .16s;}
    .bk-slot.side-a{border-inline-start-color:#c0392b;}
    .bk-slot.side-b{border-inline-start-color:#2f5fae;}
    .bk-slot.side-a + .bk-slot{border-top:1px solid #eef1f6;}
    .bk-slot.is-win{background:#fdf6e6;}
    .bk-slot.is-lose{opacity:.45;}
    /* Follow one competitor across the whole draw — the draft's green. */
    .bk-slot.is-hot{background:#c9f0da;}

    /* ⚠️ PORTRAIT 3:4 — 3 WIDE, 4 TALL — AND NOT A CIRCLE.
    
       A round avatar crops a face to a coin and throws away everything either
       side of it. These are competition photographs, taken at a weigh-in desk
       and printed on a start list, and a rectangle shows the person.
       
       Standing UP, not lying down (corrected 2026-09-20). Every profile
       picture on this platform is stored portrait at 600 × 800, which is 3:4 —
       so a landscape box centre-crops it and takes the top of the head with
       it. Three-quarters as wide as it is tall is the crop the competitor
       actually chose. Same rule as CLAUDE.md → Profile Pictures Are Portrait
       3:4, and the same one the participant card follows.

       ⚠️ Never write a literal component tag inside this stylesheet — Blade
       parses an `x-…` tag even inside a CSS comment, and one here compiled to
       a component call that swallowed the rest of the file
       (reference_blade_component_tag_in_js).
       
       Every other face on this board follows the same ratio at its own size:
       the basket, the drag ghost, the podium rows and the champion's card.
       One shape, several sizes — never two shapes. */
    .bk-av{width:36px;height:48px;flex:0 0 auto;border-radius:8px;overflow:hidden;
        border:2px solid #d5dae3;background:#1e2c4f;color:#fff;
        display:flex;align-items:center;justify-content:center;
        font-size:.62rem;font-weight:800;line-height:1;}
    .bk-av img{width:100%;height:100%;object-fit:cover;}
    .bk-slot.is-win .bk-av{border-color:#d8b25f;}
    .bk-av.is-bye{border-style:dashed;background:transparent;color:#97a1b4;border-color:#d5dae3;}
    .bk-slot.is-empty .bk-av{border-radius:8px;}
    .bk-av.is-pending{border-style:dashed;background:transparent;color:#caa14f;border-color:#caa14f;}

    .bk-who{flex:1 1 auto;min-width:0;}
    .bk-who-top{display:flex;align-items:center;gap:6px;}
    .bk-name{font-size:.78rem;font-weight:600;color:#1e2c4f;
        white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    .bk-who-sub{display:flex;align-items:center;gap:5px;margin-top:2px;}
    .bk-club-ab{width:14px;height:14px;flex:0 0 auto;border-radius:50%;
        background:linear-gradient(100deg,#edcd85,#d0a854);color:#1e2c4f;
        font-size:.45rem;font-weight:700;display:grid;place-items:center;line-height:1;}
    .bk-club{font-size:.62rem;color:#5d6880;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}

    .bk-empty-label{font-size:.75rem;font-weight:500;color:#97a1b4;}
    .bk-empty-label.is-pending{color:#8a6d20;}

    .bk-flag{width:18px;height:13px;border-radius:2px;overflow:hidden;flex:0 0 auto;
        box-shadow:0 0 0 1px #d5dae3;}
    .bk-flag .fi{display:block;width:100%;height:100%;background-size:cover;background-position:50%;}
    .bk-seed{font-size:.55rem;font-weight:800;color:#8a6d20;background:#fdf6e6;
        border-radius:9999px;padding:0 5px;flex:0 0 auto;}
    .bk-prov{width:7px;height:7px;border-radius:9999px;background:#d8b25f;flex:0 0 auto;
        box-shadow:0 0 0 2px rgba(216,178,95,.28);}

    /* The score sits in its corner's own colour, so red and blue read at a
       glance from the back of a hall. */
    .bk-score{width:30px;flex:0 0 auto;text-align:center;font-size:.75rem;font-weight:700;
        border-radius:6px;padding:4px 0;background:#fff;line-height:1.1;}
    .bk-slot.side-a .bk-score{color:#c0392b;border:1px solid #c0392b;}
    .bk-slot.side-b .bk-score{color:#2f5fae;border:1px solid #2f5fae;}
    .bk-score:empty{visibility:hidden;}

    .bk-note{padding:5px 10px;font-size:.6rem;font-weight:600;letter-spacing:.08em;
        text-transform:uppercase;color:#8a6d20;background:#fdf6e6;border-top:1px solid #eef1f6;}

    /* ── The podium column ─────────────────────────────────────────────── */
    .bk-podium{position:absolute;display:flex;flex-direction:column;gap:10px;z-index:15;}
    .bk-champ{position:relative;overflow:hidden;border-radius:14px;padding:18px;
        background:linear-gradient(100deg,#edcd85,#d0a854);color:#1e2c4f;
        animation:bkChamp .5s ease both;}
    @keyframes bkChamp{from{opacity:0;transform:scale(.92)}to{opacity:1;transform:none}}
    .bk-champ-head{display:flex;align-items:center;gap:8px;font-size:.62rem;font-weight:700;
        letter-spacing:.2em;text-transform:uppercase;}
    .bk-champ-body{display:flex;align-items:center;gap:12px;margin-top:10px;position:relative;}
    .bk-champ-av{width:48px;height:64px;flex:0 0 auto;border:2px solid #1e2c4f;border-radius:10px;
        overflow:hidden;background:#1e2c4f;color:#fff;display:flex;align-items:center;
        justify-content:center;font-size:.8rem;font-weight:800;}
    .bk-champ-av img{width:100%;height:100%;object-fit:cover;}
    .bk-champ-name{font-size:1.05rem;font-weight:700;line-height:1.2;
        overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
    .bk-champ-sub{display:flex;align-items:center;gap:6px;margin-top:3px;font-size:.68rem;font-weight:500;}
    .bk-conf{position:absolute;top:0;width:6px;height:10px;animation:bkConf 1.9s ease-in infinite;}
    @keyframes bkConf{0%{transform:translateY(-12px) rotate(0deg);opacity:1}
        100%{transform:translateY(160px) rotate(320deg);opacity:0}}
    .bk-champ-none{border:2px dashed #42527d;border-radius:14px;padding:22px 18px;text-align:center;
        font-size:.8rem;color:#97a1b4;}

    .bk-place{display:flex;align-items:center;gap:10px;background:#fff;border-radius:10px;
        padding:8px 12px;color:#1e2c4f;box-shadow:0 8px 24px rgba(11,19,43,.3);}
    .bk-place-no{width:26px;height:26px;flex:0 0 auto;border-radius:50%;color:#fff;
        font-size:.72rem;font-weight:700;display:grid;place-items:center;}
    .bk-place-av{width:30px;height:40px;flex:0 0 auto;border-radius:7px;overflow:hidden;
        border:1px solid #d5dae3;background:#1e2c4f;color:#fff;display:flex;
        align-items:center;justify-content:center;font-size:.55rem;font-weight:800;}
    .bk-place-av img{width:100%;height:100%;object-fit:cover;}
    .bk-place-who{flex:1 1 auto;min-width:0;}
    .bk-place-name{font-size:.76rem;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
    .bk-place-word{font-size:.62rem;color:#5d6880;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
    .bk-place-tbd{font-size:.76rem;color:#97a1b4;}

    /* ── Group standings — a pool's answer, which no bout of it holds ──── */
    .bk-table{position:absolute;z-index:15;background:#fff;color:#1e2c4f;
        border-radius:10px;overflow:hidden;box-shadow:0 8px 24px rgba(11,19,43,.35);}
    .bk-table-row{display:flex;align-items:center;gap:6px;padding:6px 11px;font-size:.7rem;
        border-bottom:1px solid #eef1f6;}
    .bk-table-row:last-child{border-bottom:0;}
    .bk-table-head{background:#eef1f6;font-weight:700;color:#5d6880;text-transform:uppercase;
        font-size:.58rem;letter-spacing:.1em;}
    .bk-table-row.is-through{background:#fdf6e6;}
    .bk-table-name{flex:1 1 auto;min-width:0;overflow:hidden;text-overflow:ellipsis;
        white-space:nowrap;font-weight:600;}
    .bk-table-num{flex:0 0 20px;text-align:center;color:#5d6880;font-weight:600;}
    .bk-table-num.is-pts{color:#8a6d20;font-weight:800;}

    /* ── The holding basket ────────────────────────────────────────────── */
    .bk-bench{position:absolute;z-index:20;display:flex;flex-direction:column;overflow:hidden;
        border:2px dashed #d8b25f;border-radius:14px;background:rgba(11,19,43,.94);
        box-shadow:0 16px 40px rgba(0,0,0,.45);}
    .bk-bench-head{display:flex;align-items:center;gap:10px;padding:10px 14px 8px;flex:0 0 auto;
        font-size:.62rem;font-weight:600;letter-spacing:.16em;text-transform:uppercase;color:#d8b25f;}
    .bk-bench-count{flex:0 0 auto;}
    .bk-bench-min{width:24px;height:24px;flex:0 0 auto;border:1px solid #d8b25f;border-radius:6px;
        cursor:pointer;background:transparent;color:#d8b25f;font-size:.85rem;font-weight:700;
        line-height:1;padding:0;display:grid;place-items:center;}
    .bk-bench-list{flex:1 1 auto;overflow-y:auto;padding:0 12px 10px;
        display:flex;flex-wrap:wrap;gap:10px;align-content:flex-start;}
    .bk-bench-item{display:flex;align-items:center;gap:8px;background:#fff;color:#1e2c4f;
        border-radius:10px;padding:6px 12px 6px 6px;cursor:grab;
        box-shadow:0 4px 12px rgba(11,19,43,.3);transition:transform .12s,box-shadow .16s;}
    .bk-bench-item:hover{box-shadow:0 6px 18px rgba(216,178,95,.45);}
    .bk-bench-item:active{transform:scale(.98);}
    .bk-bench-item .bk-av{width:30px;height:40px;border-color:#d8b25f;}
    .bk-bench-item .bk-name{font-size:.76rem;}
    .bk-bench-foot{flex:0 0 auto;padding:0 14px 10px;font-size:.62rem;color:#97a1b4;}
    .bk-bench-empty{padding:4px 2px 10px;font-size:.7rem;color:#97a1b4;}
    .bk-bench.is-target{border-style:solid;box-shadow:0 0 0 3px rgba(46,204,113,.55),0 16px 40px rgba(0,0,0,.45);}

    /* ── Arrange mode ──────────────────────────────────────────────────── */
    .is-arranging .bk-slot[data-arrangeable="1"]{cursor:grab;}
    .is-arranging .bk-slot[data-arrangeable="1"]:hover{outline:2px dashed rgba(216,178,95,.9);outline-offset:-2px;}

    /* ⚠️ A DRAG ON A TOUCHSCREEN IS THE OS'S GESTURE UNTIL YOU SAY OTHERWISE
       (2026-09-18: "I need the capability to drag and drop, not the holding
       and unhold"). `touch-action:none` says the browser owns no scroll or
       zoom gesture that starts here; the selection properties say there is no
       text to select and no callout to raise. Only while arranging, and only
       on the things that are actually draggable. */
    .is-arranging .bk-slot[data-arrangeable="1"],
    .is-arranging .bk-bench-item{
        touch-action:none;
        user-select:none;-webkit-user-select:none;
        -webkit-touch-callout:none;
    }
    .bk-slot.is-picked{outline:2px solid #d8b25f;outline-offset:-2px;background:#fdf6e6;}
    .bk-slot.is-target{outline:2px solid #2ecc71;outline-offset:-2px;background:#c9f0da;}
    .bk-slot.is-source{opacity:.35;}
    .bk-bench-item.is-picked{box-shadow:0 0 0 2px #d8b25f,0 4px 12px rgba(11,19,43,.3);}

    .bk-ghost{position:fixed;z-index:80;pointer-events:none;display:flex;align-items:center;gap:8px;
        padding:6px 12px 6px 6px;border-radius:10px;background:#fff;font-size:.76rem;font-weight:600;
        color:#1e2c4f;box-shadow:0 12px 28px rgba(11,19,43,.55);
        border:2px solid #d8b25f;transform:translate(-50%,-50%) rotate(-2deg);}

    /* The control column steps aside for the basket while arranging. */
    .bk-controls{transition:inset .2s ease, bottom .2s ease;}
    .bk-viewport.is-arranging ~ .bk-controls{inset-inline-end:auto;inset-inline-start:12px;}
    @media (max-width: 639px){
        .bk-viewport.is-arranging ~ .bk-controls{
            inset-inline-start:auto;inset-inline-end:12px;bottom:calc(36% + 20px);}
    }

    .bk-empty{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;
        justify-content:center;text-align:center;padding:2rem;color:#97a1b4;gap:8px;}
    .bk-empty i{color:#d8b25f;}

    /* ── Read as a PHONE ───────────────────────────────────────────────────
       The draft's View setting, and it is not the same board shrunk: a screen
       held in one hand gets bigger type and fatter targets, because the person
       reading it is standing at a mat rather than sitting at a desk. Applied
       by class, not by a media query, so Desktop / Mobile can be FORCED — an
       organiser on a laptop driving a hall screen wants the hall's version. */
    .bk-viewport.is-phone .bk-name{font-size:.86rem;}
    .bk-viewport.is-phone .bk-club{font-size:.68rem;}
    .bk-viewport.is-phone .bk-slot{padding:10px 10px;}
    .bk-viewport.is-phone .bk-av{width:42px;height:56px;}
    .bk-viewport.is-phone .bk-round-label{font-size:1.2rem;}
    .bk-viewport.is-phone .bk-score,
    .bk-viewport.is-phone .bk-score-in{width:34px;font-size:.82rem;}

    /* ── The organiser's own backdrop ──────────────────────────────────────
       A photograph behind the draw — the hall, the sponsor's wall, the event's
       own artwork. Sits UNDER the canvas with the draft's ink wash over it, so
       a bright picture never costs a white card its contrast. Kept per browser
       (localStorage), because it is a preference of the person driving this
       screen and not a property of the event. */
    .bk-bg{position:absolute;inset:0;z-index:0;overflow:hidden;pointer-events:none;}
    .bk-bg-img{width:100%;height:100%;background-size:cover;background-position:center;}
    .bk-bg-wash{position:absolute;inset:0;
        background:linear-gradient(160deg,rgba(11,19,43,.7) 0%,rgba(30,44,79,.65) 100%);}
    .bk-viewport .bk-canvas{z-index:1;}

    /* ── The notice ────────────────────────────────────────────────────────
       The draft answers every move with a SENTENCE, not a toast: "X filled the
       open bye in Round of 16 3 — no extra bout needed. No played match was
       changed." That is the board explaining a tournament rule it has just
       applied, and it is worth more than three words in a corner. Cream panel,
       gold rule down the leading edge, dismissible, and it stands aside after
       a few seconds on its own. */
    .bk-notice{position:absolute;top:24px;z-index:90;display:flex;align-items:flex-start;gap:12px;
        background:#fdf6e6;border-inline-start:4px solid #d8b25f;color:#1e2c4f;
        border-radius:0 12px 12px 0;padding:14px 16px;font-size:.8rem;line-height:1.5;
        max-width:420px;box-shadow:0 16px 40px rgba(0,0,0,.45);
        animation:bkToast .35s ease both;}
    @keyframes bkToast{from{opacity:0;transform:translateX(24px)}to{opacity:1;transform:none}}
    .bk-notice-x{flex:none;width:22px;height:22px;border:none;border-radius:6px;cursor:pointer;
        background:transparent;color:#8a6d20;font-size:.8rem;font-weight:700;line-height:1;padding:0;}

    /* ── The bronze bout ───────────────────────────────────────────────────
       Third place is not a round, it is a single bout beside the podium, and
       the draft gives it its own cream-and-bronze skin so nobody mistakes it
       for the final. */
    .bk-bronze{border-radius:10px;overflow:hidden;background:#faf1e6;color:#1e2c4f;
        box-shadow:0 0 0 2px #b07a45,0 8px 24px rgba(11,19,43,.35);}
    .bk-bronze .bk-meta{background:#ecd9bd;color:#7a4f22;border-bottom-color:#d8bd93;}
    .bk-bronze .bk-meta-r{color:#7a4f22;}
    .bk-bronze .bk-slot{background:#faf1e6;}
    .bk-bronze .bk-slot.is-win{background:#f3e2c4;}

    /* ── Writing on the board ──────────────────────────────────────────────
       An organiser taps a corner to advance that competitor and types into the
       score box beside them. Both go to me.events.outcome, which asks
       canManage for itself; these classes only say the board is offering it. */
    .bk-can-decide .bk-slot[data-decidable="1"]{cursor:pointer;}
    .bk-can-decide .bk-slot[data-decidable="1"]:hover{box-shadow:inset 0 0 0 2px rgba(216,178,95,.85);}
    .bk-score-in{width:30px;flex:0 0 auto;text-align:center;font-family:inherit;font-size:.75rem;
        font-weight:700;border-radius:6px;padding:4px 0;background:#fff;line-height:1.1;}
    .bk-slot.side-a .bk-score-in{color:#c0392b;border:1px solid #c0392b;}
    .bk-slot.side-b .bk-score-in{color:#2f5fae;border:1px solid #2f5fae;}
    .bk-score-in:focus{outline:2px solid #caa14f;}

    /* Edit-names mode: the name becomes a field and a ✕ turns a corner back
       into a bye. */
    .bk-name-in{flex:1 1 auto;min-width:0;font-family:inherit;font-size:.78rem;font-weight:600;
        color:#1e2c4f;border:1px solid #caa14f;border-radius:6px;padding:5px 7px;background:#fff;}
    .bk-slot.is-editing .bk-av{border-color:#caa14f;}
    .bk-rm{width:24px;height:24px;flex:none;border:1px solid #d5dae3;border-radius:6px;
        cursor:pointer;background:#fff;color:#b4483c;font-size:.7rem;font-weight:700;line-height:1;padding:0;}

    /* ── The chrome AROUND the board ───────────────────────────────────────
       Defined here, beside the board, rather than as arbitrary Tailwind values
       in the component: this project's Tailwind bundle is PREBUILT, so a class
       nobody has used before (`bg-[#d8b25f]`) has no CSS in public/build and
       renders as nothing at all. Plain rules always apply. */
    .bk-frame{background:linear-gradient(160deg,#0b132b 0%,#1e2c4f 100%);}
    .bk-frame-card{border-radius:1rem;box-shadow:0 18px 40px rgba(11,19,43,.28);}

    /* The division pills above a card-shaped board. */
    .bk-pill{background:#fff;color:#1e2c4f;border-color:#d5dae3;}
    .bk-pill:hover{background:#fdf6e6;}
    .bk-pill.is-on{background:#d8b25f;color:#1e2c4f;border-color:#d8b25f;}
    .bk-pill-count{background:#eef1f6;color:#5d6880;}
    .bk-pill.is-on .bk-pill-count{background:rgba(30,44,79,.15);color:#1e2c4f;}

    /* The arrange banner: the draft's gold bar, ink text. */
    .bk-banner{background:linear-gradient(100deg,#edcd85,#d0a854);color:#1e2c4f;}

    /* The zoom / arrange cluster, legible on navy. */
    .bk-ctl{background:#fff;color:#1e2c4f;border-color:#d5dae3;}
    .bk-ctl:hover{background:#fdf6e6;}
    .bk-ctl-on{background:linear-gradient(100deg,#edcd85,#d0a854);color:#1e2c4f;border-color:#d8b25f;}
    .bk-ctl-danger{background:#fff;color:#b4483c;border-color:#e6bdb7;}
    .bk-ctl-danger:hover{background:#fdeeec;}

    .bk-locked{background:rgba(11,19,43,.8);color:#d8b25f;border-color:rgba(216,178,95,.5);
        font-size:.6rem;}
    .bk-legend{color:#97a1b4;font-size:.65rem;}
    /* ⚠️ The two font sizes above are set HERE, not by `text-[0.6rem]` /
       `text-[0.65rem]` on the elements. This project's Tailwind bundle is
       prebuilt and neither arbitrary value is in it, so both badges were
       silently inheriting the board's size. */

    @media (prefers-reduced-motion: reduce){
        .bk-match,.bk-match.is-current,.bk-champ{animation:none;opacity:1;transform:none;}
        .bk-conf{display:none;}
    }
</style>

<script>
window.BracketBoard = window.BracketBoard || (function () {
    'use strict';

    /* ── The palette, in one place ─────────────────────────────────────────
       Taken verbatim from the draft. Anything that reaches an inline style or
       an SVG attribute below comes from here rather than being typed twice. */
    const INK = '#1e2c4f', GOLD = '#d8b25f', SLATE = '#42527d', HOT = '#2ecc71';
    const MEDAL = { 1: '#d8b25f', 2: '#97a1b4', 3: '#b07a45' };

    /* A card is sized to the NAMES IT HOLDS, and each COLUMN is measured on
       its own — see widthFor(). The floor keeps a thin column looking like a
       bracket rather than a list of chips; the ceiling stops one pathological
       name stretching the board. The ellipsis in `.bk-name` stays the last
       resort it was always meant to be. */
    const CARD_W_MIN = 196, CARD_W_MAX = 460;

    /* Everything in a slot that is NOT the name, at its widest: 10px padding
       either side, the 3px corner edge, the 34px face, the 18px flag, a
       provisional dot, a seed chip, the 30px score, and the gaps between them.
       Derived from the slot CSS above — keep the two in step. */
    const SLOT_FIXED = 154;
    /* The same for the club line, which is the other thing a card must hold:
       padding, edge, face, the 14px club mark and its gap, and the score. */
    const CLUB_FIXED = 130;

    const META_H = 22, SLOT_H = 66;
    const CARD_H_GUESS = META_H + SLOT_H * 2;

    /* The draft's own measurements: 36px between columns (half of which is the
       elbow), and the bouts of a round breathing at 18px. */
    /* ⚠️ ROW_GAP is the gap between two bout cards in the same column, and it
       is measured against the TALLEST card on the board — see the note on
       `cardH` in render(). It was 18 with a card height taken from the FIRST
       card, and cards are not all the same height: a corner with a club under
       the name is taller than a bye, so the tall ones ran into their
       neighbours. */
    const COL_GAP = 36, ROW_GAP = 26;
    /* Between the final's column and the podium's. */
    const PODIUM_GAP = 44;
    /* The podium column is wider than a bout card — the champion card carries
       a 54px face and a name at 1.05rem. */
    const PODIUM_EXTRA = 34;

    /* The server's own name for the bronze bout — App\Events\Support\
       Tournament\Advancement::ROUND_THIRD and DrawEngine::ROUND_THIRD, which
       are the same string. It arrives in `rounds` like any other round and is
       lifted OUT of the ladder here, because it is not one: it is a single
       bout beside the podium. */
    const THIRD_ROUND = 'Third place';

    const el = (t, c) => { const n = document.createElement(t); if (c) n.className = c; return n; };
    const initials = n => String(n || '').trim().split(/\s+/).slice(0, 2).map(p => p[0] || '').join('').toUpperCase();

    let S = null; // active instance state

    /* ── Remembering how this person looks at a draw ──────────────────────
       localStorage, wrapped: it throws in private mode and comes back empty
       after a data clear, and a board that will not draw because a preference
       could not be read is worse than a board that forgot one. */
    function pref(key, fallback) {
        try { return localStorage.getItem('bk-' + key) || fallback; } catch (e) { return fallback; }
    }
    function setPref(key, value) {
        try { localStorage.setItem('bk-' + key, value); } catch (e) { /* private mode */ }
    }

    /* ── Sizing the card to the division ───────────────────────────────── */

    /** The widest string in this division, measured in the REAL font. */
    function widest(strings, className) {
        if (!strings.length) return 0;

        const probe = el('span', className);
        probe.style.cssText = 'position:absolute;left:-9999px;top:0;width:auto;max-width:none;'
            + 'white-space:nowrap;overflow:visible;text-overflow:clip;visibility:hidden;';
        (S.layer || document.body).appendChild(probe);

        let max = 0;
        for (const s of strings) { probe.textContent = s; max = Math.max(max, probe.offsetWidth); }
        probe.remove();

        return max;
    }

    /**
     * How wide one column has to be to hold what is IN it.
     *
     * ⚠️ PER COLUMN, not one width for the whole board (corrected
     * 2026-09-20: "make the bracket card expand and shrink to its content").
     *
     * The board used to measure the longest name in the DIVISION and give
     * every column that width. One long name in the round of sixteen
     * therefore stretched the final — two short names on a card four times
     * wider than they needed — and because the gaps were drawn between the
     * CARDS' edges rather than between their contents, the board read as
     * though the columns were unevenly spaced when they were not.
     *
     * Each column is now measured against its own names, which is what makes a
     * knockout ladder taper the way it should: sixteen names wide at the
     * front, two at the back.
     */
    function widthFor(names, clubs) {
        const nameW = widest(names, 'bk-name');
        const clubW = widest(clubs, 'bk-club');

        if (!nameW && !clubW) return CARD_W_MIN;

        return Math.min(CARD_W_MAX, Math.max(CARD_W_MIN,
            Math.ceil(Math.max(nameW + SLOT_FIXED, clubW + CLUB_FIXED))));
    }

    /** The names and clubs standing in one round. */
    function peopleIn(round) {
        const names = [], clubs = [];

        (round.matches || []).forEach(m => ['a', 'b'].forEach(side => {
            const p = m[side];
            if (!p) return;
            if (p.name) names.push(p.name);
            if (p.club) clubs.push(p.club);
        }));

        return { names, clubs };
    }

    /** ...and in a flat list of people (the bench, the podium). */
    function peopleOf(list) {
        const names = [], clubs = [];

        (list || []).forEach(p => {
            if (!p) return;
            if (p.name) names.push(p.name);
            if (p.club) clubs.push(p.club);
        });

        return { names, clubs };
    }

    // -----------------------------------------------------------------
    // Mount
    // -----------------------------------------------------------------
    function mount(cfg) {
        const vp = document.getElementById(cfg.viewportId);
        if (!vp) return;

        vp.classList.add('bk-viewport');
        vp.innerHTML = '';
        const canvas = el('div', 'bk-canvas');
        const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.setAttribute('class', 'bk-links');
        const layer = el('div', 'bk-layer');
        canvas.append(svg, layer);
        vp.appendChild(canvas);

        S = {
            cfg, vp, canvas, svg, layer,
            scale: 1, tx: 0, ty: 0, moved: false, from: null,
            divisions: [], division: null, locked: false, hiddenMsg: null,
            canArrange: false,       // server truth, refreshed on every load
            arrange: false,          // arrange mode on/off
            pick: null,              // { from, name } — tap-to-place selection
            drag: null,              // active pointer drag
            bench: null, benchMin: false,
            hot: null,               // competitor being followed across the draw
            currentNo: null,         // the bout that is next up
            canManage: false,        // server truth: may this reader decide a bout?

            /* ── The draft's four view settings ───────────────────────────
               Every one is a way of LOOKING at the draw, so every one lives
               in the browser and writes nothing. They survive a reload
               because an organiser who has chosen split halves on the hall
               laptop has chosen it for the day. */
            layout: pref('layout', 'linear'),   // linear | split
            third: pref('third', 'match'),      // match | shared
            view: pref('view', 'auto'),         // auto | desktop | mobile
            /* ── THE UNLOCK SWITCH ────────────────────────────────────
               This screen is the MANAGEMENT view, and management does not
               mean "everything is editable the moment you arrive". A draw on
               an event morning is read far more often than it is changed, and
               every editable thing on it — a draggable competitor, a tappable
               corner, a score box — is something a sleeve or a thumb can
               alter by accident while somebody is only looking.

               So the board opens LOCKED for everybody, and one control turns
               the whole of it on: arranging, declaring a winner, typing a
               score, correcting a name. Never persisted — it starts locked
               on every load, deliberately, because a switch that remembers
               being on is not a safety catch.

               ⚠️ It is a GUARD, not a permission. Unlocking grants nothing:
               every endpoint behind every one of those acts asks the server
               for the same authority it always did. This decides what may be
               touched, not what may be done. */
            unlocked: false,

            edit: false,                        // edit-names mode
            bg: null,                           // the organiser's backdrop
            notice: null, noticeEl: null, noticeTimer: null,
        };

        /* The backdrop is remembered as a data URL, so it comes back on the
           next load without an upload and without the server ever holding a
           picture that is one person's preference. */
        try {
            const saved = localStorage.getItem('bk-bg');
            if (saved) S.bg = saved;
        } catch (e) { /* private mode */ }

        bindPanZoom();
        bindArrangeInput();
        bindHover();
        bindDecide();
        bindRealtime();
        load();
    }

    // -----------------------------------------------------------------
    // Data
    // -----------------------------------------------------------------
    async function load(keepView) {
        try {
            const res = await fetch(S.cfg.dataUrl, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                credentials: 'same-origin',
            });
            if (!res.ok) throw new Error('load failed');
            const data = await res.json();

            S.divisions = data.divisions || [];
            S.locked = !!data.locked;
            /* A draw the organiser has not let out yet. The server sends no
               divisions AND says why, so the board can name the day it opens
               instead of drawing nothing — which reads as "nobody entered". */
            S.hiddenMsg = data.hidden ? (data.hidden_message || null) : null;
            // Permission is whatever the SERVER just said — never a client flag
            // that a stale page is still carrying.
            S.canArrange = !!data.can_arrange;
            /* Deciding a bout and correcting a name are the ORGANISER's, which
               is a different permission from arranging — an appointed official
               moves people around a draw without being able to declare a
               winner. Server truth on every load, never a client flag a stale
               page is still carrying. */
            S.canManage = !!data.can_manage;
            if (!S.canArrange) { S.arrange = false; S.pick = null; }
            if (!S.canManage) { S.edit = false; }
            // The switch cannot outlive the permission behind it.
            if (!S.canManage && !S.canArrange) { S.unlocked = false; S.arrange = false; }

            const stillThere = S.divisions.some(d => d.id === S.division && !d.is_heading);
            if (!stillThere) {
                // Nothing selected yet (first load), or the selection is gone.
                // A HEADING is never selected: it is the organiser's title for
                // the divisions beneath it, with no entrants and no draw.
                const drawable = S.divisions.filter(d => !d.is_heading);
                const wanted = drawable.find(d => String(d.id) === String(S.cfg.initialDivision ?? ''));
                S.division = wanted ? wanted.id : (drawable[0] ? drawable[0].id : null);
            }

            /* The view class BEFORE the render, never after: `is-phone` makes
               every face 56px instead of 48px, and render() measures the cards
               to space them. Measured first and enlarged second, each card
               grew 24px after its gap was set, and the tallest (a bye, with
               its footer) ate the gap and touched its neighbour. */
            applyView();
            render(keepView);
            emit('bracket:loaded', {
                divisions: summary(),
                division: S.division, canArrange: arrangeable(), locked: S.locked,
                canManage: S.canManage,
                unlocked: S.unlocked, unlockable: S.canManage || S.canArrange,
                layout: S.layout, third: S.third, view: S.view,
                edit: S.edit, background: !!S.bg,
            });
        } catch (e) {
            S.layer.innerHTML = '';
            S.svg.innerHTML = '';
            S.vp.querySelectorAll('.bk-empty').forEach(n => n.remove());
            const box = el('div', 'bk-empty');
            box.appendChild(el('i', 'bi bi-diagram-3 bracket-icon text-4xl'));
            const msg = el('div'); msg.textContent = S.cfg.text.loadFailed; box.appendChild(msg);
            /* On the VIEWPORT, not the canvas: the canvas carries the pan/zoom
               transform, which shrank this box and scaled its words up until
               they wrapped one to a line. */
            S.vp.appendChild(box);
        }
    }

    const summary = () => S.divisions.map(d => ({
        id: d.id, name: d.name, entrants: d.entrants,
        bench: (d.bench || []).length, is_heading: !!d.is_heading, podium: d.podium || [],
    }));

    const current = () => S.divisions.find(d => d.id === S.division) || null;

    /* Arranging is gated on TWO things: whether this person may arrange this
       event's draw at all (server truth, `can_arrange`), and whether the
       division in front of them is the kind of thing that is arranged. A pool
       is not: everybody meets everybody, so there are no slots to place anyone
       in and a drag only breaks a fixture. Arrangement::move() refuses the same
       case server-side. */
    const arrangeable = () => S.unlocked && S.canArrange && (current()?.arrangeable !== false);

    /** May this reader change a RESULT — declare a winner, type a score? */
    const decidable = () => S.unlocked && S.canManage;

    function show(id) {
        const d = S.divisions.find(x => String(x.id) === String(id));
        if (!d) return;
        if (d.is_heading) return;   // a label, not a draw
        S.division = d.id;
        S.pick = null;
        S.hot = null;

        /* Walking from a bracket into a pool turns arrange mode off with it:
           the mode would otherwise stay lit over a division that has nothing
           to arrange, offering handles that refuse every drop. */
        if (!arrangeable()) { S.arrange = false; }

        render();
        emitState();
    }

    // -----------------------------------------------------------------
    // Layout + render
    //
    // Positions are computed, not floated: round 0's bouts are stacked evenly
    // and every later bout sits at the midpoint of the two it is fed by. That
    // is the same "bout i feeds bout ⌊i/2⌋" rule the server advances winners
    // by (Advancement::nextBout), so the drawing and the engine can never
    // disagree about which line goes where.
    // -----------------------------------------------------------------
    function render(keepView) {
        const div = current();
        S.layer.innerHTML = '';
        S.svg.innerHTML = '';
        S.vp.querySelectorAll('.bk-empty').forEach(n => n.remove());
        paintBackground();

        /* No division to show at all — an event whose draw has not been made
           yet sends none. That is a state, not a failure: it read
           `div.rounds` off nothing, threw, and the catch in load() painted
           "Could not load the bracket". Drawn on the viewport, unzoomed. */
        if (!div) {
            const box = el('div', 'bk-empty');
            box.appendChild(el('i', S.hiddenMsg ? 'bi bi-lock-fill text-4xl' : 'bi bi-diagram-3 bracket-icon text-4xl'));
            const msg = el('div'); msg.textContent = S.hiddenMsg || S.cfg.text.noDivisions || S.cfg.text.noDraw; box.appendChild(msg);
            S.vp.appendChild(box);
            return;
        }

        /* ── The bronze bout is NOT a round ───────────────────────────────
           The server sends it as one ('Third place'), because that is how it
           is stored, and the old board drew it as a fourth column after the
           final — which reads as a round that comes after the tournament has
           finished. The draft is right: it is a single bout that sits beside
           the podium, in its own cream skin. Lifted out here, drawn there.

           `third: 'shared'` drops the card entirely and the podium carries two
           bronzes instead, which is what most combat sports actually award. */
        const thirdRound = (div.rounds || []).find(r => r.name === THIRD_ROUND) || null;
        const rounds = (div.rounds || []).filter(r => r !== thirdRound);

        if (!rounds.length) {
            renderBench(div);
            const box = el('div', 'bk-empty');
            /* Withheld and not-yet-drawn are different facts and get different
               faces: a padlock plus the day it opens, or the bracket glyph. */
            box.appendChild(el('i', S.hiddenMsg ? 'bi bi-lock-fill text-4xl' : 'bi bi-diagram-3 bracket-icon text-4xl'));
            const msg = el('div'); msg.textContent = S.hiddenMsg || S.cfg.text.noDraw; box.appendChild(msg);
            S.canvas.appendChild(box);
            return;
        }

        /* ── WIDTHS FIRST, one per column ─────────────────────────────────
           Everything below — the cards, the labels, the connectors, the
           podium — is laid out from these, so they have to be known before a
           single thing is positioned. */
        const colW = rounds.map(r => { const p = peopleIn(r); return widthFor(p.names, p.clubs); });

        const benchPeople = peopleOf(div.bench);
        // Kept for the basket, which is sized again on resize, long after this.
        S.widestName = widest(benchPeople.names.concat(
            rounds.flatMap(r => peopleIn(r).names),
        ), 'bk-name');

        S.currentNo = nextBoutNo(div);

        const nR = rounds.length;
        const first = rounds[0].matches.length;

        /* ── ONE-SIDED, OR SPLIT DOWN THE MIDDLE ──────────────────────────
           The draft offers both, and they are the same drawing read two ways:
           one-sided runs left to right and ends at the final; split halves run
           INWARDS from both edges and meet at it, which halves the height and
           is how a printed championship sheet is laid out.

           Split needs a real ladder to fold: at least two rounds, a power-of-
           two first round, and no GROUP round (a pool feeds through a table,
           not by position, so there is nothing to mirror). Anything else falls
           back to one-sided rather than drawing a lie. */
        const split = S.layout === 'split' && nR >= 2 && first >= 2
            && first % 2 === 0 && !rounds.some(r => r.group);

        /* The podium column is measured on the people who are ON it, and is
           never narrower than the final that feeds it. */
        const podiumPeople = peopleOf(div.podium);
        const podiumW = Math.max(
            widthFor(podiumPeople.names, podiumPeople.clubs) + PODIUM_EXTRA,
            colW[nR - 1],
        );

        /* How many bouts stack in the tallest column, and therefore how tall
           the board is. Split halves each carry half the first round. */
        const stackCount = split ? first / 2 : first;

        /* ── EQUAL GAPS, UNEQUAL COLUMNS ──────────────────────────────────
           The gap between two columns is COL_GAP, always and everywhere. The
           columns themselves are whatever their own names need. Laid out
           cumulatively rather than on a fixed stride, which is the whole of
           the fix: a fixed stride only looks even while every column is the
           same width. */
        const ladderW = colW.reduce((a, w) => a + w, 0) + COL_GAP * (nR - 1);
        const halfW = split
            ? colW.slice(0, nR - 1).reduce((a, w) => a + w, 0) + COL_GAP * Math.max(0, nR - 2)
            : 0;
        const centreW = split ? Math.max(colW[nR - 1], podiumW) : 0;

        const totalW = split
            ? 40 + halfW + COL_GAP + centreW + COL_GAP + halfW + 40
            : 40 + ladderW + PODIUM_GAP + podiumW + 40;

        S.layer.style.width = totalW + 'px';

        /* Left edge of each ladder column, reading outwards from the start.
           RTL mirrors the finished number rather than being worked out again
           — one arithmetic, one place it can be wrong. */
        const mirror = (x, w) => S.cfg.rtl ? (totalW - x - w) : x;

        // Build every card first, then measure one, then position them all —
        // so the layout is driven by the height cards ACTUALLY have.
        const cards = rounds.map((round, r) => round.matches.map((m, i) => {
            const card = buildMatch(div, m, r, i);
            card.style.width = colW[r] + 'px';
            card.style.visibility = 'hidden';
            S.layer.appendChild(card);
            return card;
        }));

        /* ⚠️ THE TALLEST CARD, not the first one.
        
           Every card is positioned on one shared height, because that is what
           makes a connector meet the middle of the card it points at. But the
           cards are NOT all the same height: a corner with a club under the
           name is two lines, a bye is one, and a bout with a score box is
           taller again. Measuring the first card and spacing everything by it
           left the tall ones overlapping their neighbours (reported
           2026-09-20: "the bracket cards are touching each other").
           
           So: measure them all, take the largest, and lay out on that. The
           short cards then sit with a little air around them, which is the
           right way round — a board with an even rhythm reads as a board. */
        let cardH = CARD_H_GUESS;
        cards.forEach(col => col.forEach(c => {
            cardH = Math.max(cardH, c.offsetHeight || 0);
        }));
        // A group's table sits above the columns, so everything starts lower.
        const topPad = (div.standings && div.standings.length) ? 64 + div.standings.length * 28 : 0;
        const bandTop = 76 + topPad;
        const totalH = Math.max(1, stackCount) * (cardH + ROW_GAP) + 96 + topPad;

        S.layer.style.height = totalH + 'px';
        S.svg.setAttribute('width', totalW);
        S.svg.setAttribute('height', totalH);

        /* ── Where every bout sits ────────────────────────────────────────
           Round 0's bouts stack evenly; every later bout sits at the midpoint
           of the two that feed it. That is the same "bout i feeds bout ⌊i/2⌋"
           rule the server advances winners by (Advancement::nextBout), so the
           drawing and the engine can never disagree about which line goes
           where.

           Split draws the rule TWICE — once per half — over the same vertical
           band, which is exactly what folding a ladder in two means. */
        const stackedY = k => bandTop + k * (cardH + ROW_GAP) + cardH / 2;

        function subtree(startIndex, count) {
            const c = [];
            for (let r = 0; r < nR; r++) {
                const n = count >> r;
                const base = startIndex >> r;
                c[r] = {};
                for (let k = 0; k < n; k++) {
                    const i = base + k;
                    if (rounds[r].matches[i] === undefined) continue;
                    if (r === 0 || rounds[r - 1].group) { c[r][i] = stackedY(k); continue; }
                    const a = c[r - 1][i * 2], b = c[r - 1][i * 2 + 1];
                    c[r][i] = a === undefined ? stackedY(k) : (b === undefined ? a : (a + b) / 2);
                }
            }
            return c;
        }

        /* ── The columns, as a model ──────────────────────────────────────
           One list of { x, round, indices, mirrored }, built once and then
           used for the cards, the labels and the connectors alike — so a
           layout change can never move a card without moving the line into
           it. `mirrored` says which EDGE of the card a connector leaves from,
           which is the only thing the right-hand half does differently. */
        const cols = [];
        const yOf = [];      // yOf[r][i] — filled per column below
        let podiumX = 0;     // set by whichever layout ran

        const put = (r, i, y) => { (yOf[r] = yOf[r] || {})[i] = y; };

        if (split) {
            const left = subtree(0, first / 2);
            const right = subtree(first / 2, first / 2);

            let lx = 40, rx = totalW - 40;

            for (let r = 0; r < nR - 1; r++) {
                const n = rounds[r].matches.length;
                const half = n / 2;

                const li = [], ri = [];
                for (let i = 0; i < half; i++) { li.push(i); put(r, i, left[r][i]); }
                for (let i = half; i < n; i++) { ri.push(i); put(r, i, right[r][i]); }

                rx -= colW[r];

                cols.push({ r, indices: li, x: lx, w: colW[r], mirrored: false, label: rounds[r].name });
                cols.push({ r, indices: ri, x: rx, w: colW[r], mirrored: true, label: rounds[r].name });

                lx += colW[r] + COL_GAP;
                rx -= COL_GAP;
            }

            // The final belongs to neither half: it is what the two halves meet
            // at, so it sits between them, level with both semifinals.
            const lastLeft = left[nR - 2] || {};
            const lastRight = right[nR - 2] || {};
            const ly = Object.values(lastLeft)[0], ry = Object.values(lastRight)[0];
            const finalY = (ly !== undefined && ry !== undefined) ? (ly + ry) / 2
                : (ly !== undefined ? ly : (ry !== undefined ? ry : stackedY(0)));

            put(nR - 1, 0, finalY);
            cols.push({
                r: nR - 1, indices: [0], w: colW[nR - 1], mirrored: false, label: rounds[nR - 1].name,
                // Centred in the middle lane, which is as wide as the podium
                // beneath it or the final itself, whichever asks for more.
                x: 40 + halfW + COL_GAP + (centreW - colW[nR - 1]) / 2,
                centre: true, lane: 40 + halfW + COL_GAP, laneW: centreW,
            });
        } else {
            const all = subtree(0, first);
            let x = 40;

            for (let r = 0; r < nR; r++) {
                const indices = rounds[r].matches.map((m, i) => i);
                indices.forEach(i => put(r, i, all[r][i]));
                cols.push({
                    r, indices, w: colW[r], x: mirror(x, colW[r]),
                    mirrored: !!S.cfg.rtl, label: rounds[r].name,
                });
                x += colW[r] + COL_GAP;
            }

            // Where the podium column starts, in the same running total — so
            // its gap to the final is PODIUM_GAP and nothing else.
            podiumX = mirror(x - COL_GAP + PODIUM_GAP, podiumW);
        }

        // Round headings, then the cards themselves.
        const labelled = new Set();
        cols.forEach(col => {
            if (!labelled.has(col.x + '|' + col.label)) {
                labelled.add(col.x + '|' + col.label);
                const label = el('div', 'bk-round-label');
                label.style.left = col.x + 'px';
                label.style.top = (28 + topPad) + 'px';
                label.style.width = col.w + 'px';
                label.textContent = col.label;
                S.layer.appendChild(label);
            }

            col.indices.forEach(i => {
                const card = cards[col.r][i];
                if (!card) return;
                card.style.left = col.x + 'px';
                card.style.top = (yOf[col.r][i] - cardH / 2) + 'px';
                card.style.visibility = '';
                card.style.animationDelay = Math.min(col.r * 70 + i * 18, 400) + 'ms';
            });
        });

        drawLinks(rounds, cols, yOf, nR);
        const podiumBottom = renderPodium(
            div, thirdRound, cols, yOf, cardH, totalW, bandTop, podiumW, split, nR, podiumX,
        );
        renderStandings(div, totalW);
        renderBench(div);
        applyArrangeClasses();

        // Room below whatever the podium column came to, or fit() zooms to the
        // bouts and crops it.
        if (podiumBottom > parseFloat(S.layer.style.height || 0)) {
            S.layer.style.height = podiumBottom + 'px';
            S.svg.setAttribute('height', podiumBottom);
        }

        if (!keepView) requestAnimationFrame(fit);
    }

    /**
     * The bout that is NEXT — the one the board pulses.
     *
     * The lowest-numbered bout that has both corners and no winner yet. The
     * same question the hall asks ("what is on now?"), answered from the draw
     * itself rather than from a clock, exactly as the draft does it.
     */
    function nextBoutNo(div) {
        let min = null;

        (div.rounds || []).forEach(r => (r.matches || []).forEach(m => {
            if (!m.no || m.winner) return;
            if (!(m.a && m.a.name) || !(m.b && m.b.name)) return;
            if (min === null || m.no < min) min = m.no;
        }));

        return min;
    }


    function buildMatch(div, m, roundIndex, matchIndex) {
        const card = el('div', 'bk-match');
        card.dataset.matchId = m.id;
        card.dataset.round = roundIndex;
        if (m.status === 'live') card.classList.add('is-live');
        if (m.status === 'done') card.classList.add('is-done');
        if (isMine(m)) card.classList.add('is-mine');
        if (m.no && S.currentNo !== null && m.no === S.currentNo) card.classList.add('is-current');

        /* The strip the draft puts across the top of every bout: which bout
           this is on the left, which mat it is on on the right. A bout nobody
           has to fight says so instead of pretending to a number. */
        const meta = el('div', 'bk-meta');
        const left = el('span');
        /* A bout with no number is not a bout: either the draw wrote a
           walkover (`bye`), or a corner is empty and nobody has to fight.
           Saying "Bye — no bout" is the honest answer; the old board printed
           "TBD", which reads as "we have not decided yet". */
        const unfought = !(m.a && m.a.name) || !(m.b && m.b.name);
        left.textContent = m.no
            ? S.cfg.text.match + ' ' + m.no
            : ((m.bye || unfought) ? S.cfg.text.byeNoBout : S.cfg.text.tbd);
        meta.appendChild(left);

        const right = el('span', 'bk-meta-r');
        const bits = [];
        if (m.court) bits.push(m.court);
        if (m.time) bits.push(m.time);
        right.textContent = bits.join(' · ');
        meta.appendChild(right);
        card.appendChild(meta);

        card.appendChild(buildSlot(div, m, 'a', roundIndex));
        card.appendChild(buildSlot(div, m, 'b', roundIndex));

        /* A walkover says so in the draft's gold footer band — it is not the
           same sentence as "decided", and the two are otherwise identical. */
        if (m.bye && m.winner) {
            const note = el('div', 'bk-note');
            note.textContent = S.cfg.text.bye;
            card.appendChild(note);
        }

        card.addEventListener('click', () => {
            if (S.moved || S.arrange) return;
            emit('bracket:match', { match: m, division: div.id });
        });

        return card;
    }

    function buildSlot(div, m, side, roundIndex) {
        const p = m[side] || {};
        const slot = el('div', 'bk-slot side-' + side);
        slot.dataset.matchId = m.id;
        slot.dataset.side = side;
        slot.dataset.competitorId = p.competitor_id || '';

        // Only the first round is arrangeable — later rounds are DERIVED from
        // it, so letting anyone drop a name there would invent a result.
        if (roundIndex === 0) slot.dataset.arrangeable = '1';

        const bronze = roundIndex === -1;

        if (!p.name) slot.classList.add('is-empty');
        else if (m.winner === side) slot.classList.add('is-win');
        else if (m.winner) slot.classList.add('is-lose');

        /* ── An empty corner: a dashed ring and what it is WAITING for ─────
           Three different answers, because they are three different facts:
           "Bye" in the first round (nobody is coming), "Semifinal loser" in
           the bronze bout (somebody is, once a semifinal is fought), and
           "Awaiting winner" everywhere else. The draft colours the last two
           gold, because a promise is not an absence. */
        if (!p.name) {
            const pending = bronze || roundIndex > 0;
            const av = el('div', 'bk-av ' + (pending ? 'is-pending' : 'is-bye'));
            slot.appendChild(av);

            const label = el('span', 'bk-empty-label' + (pending ? ' is-pending' : ''));
            label.textContent = bronze ? S.cfg.text.semifinalLoser
                : (pending ? S.cfg.text.awaiting : S.cfg.text.bye);
            slot.appendChild(label);

            return slot;
        }

        const av = el('div', 'bk-av');
        if (p.photo) {
            const img = el('img'); img.src = p.photo; img.alt = ''; img.loading = 'lazy';
            av.appendChild(img);
        } else {
            av.textContent = initials(p.name);
        }
        slot.appendChild(av);

        /* ── EDIT NAMES ───────────────────────────────────────────────────
           The draft's ✎ mode: the name becomes a field, and a ✕ turns the
           corner back into a bye. Both are real writes here —
           me.events.entrant.update corrects the entry's name (the same editor
           the athlete's own panel uses, so one set of rules), and the ✕ is an
           arrange back to the basket, which is what "creates a bye" means when
           the entrant is a person and not a string.

           Offered only to somebody who may MANAGE, and only on a corner that
           has an entry behind it — a name typed at the desk with no entry is
           not something this door can correct. */
        if (S.edit && decidable()) {
            slot.classList.add('is-editing');

            const input = el('input', 'bk-name-in');
            input.type = 'text';
            input.value = p.name;
            input.disabled = !p.competitor_id;
            input.addEventListener('pointerdown', e => e.stopPropagation());
            input.addEventListener('click', e => e.stopPropagation());
            input.addEventListener('change', () => renameEntrant(p.competitor_id, input.value, p.name));
            slot.appendChild(input);

            if (p.competitor_id && roundIndex === 0 && !m.winner) {
                const rm = el('button', 'bk-rm');
                rm.type = 'button';
                rm.title = S.cfg.text.removeToBye;
                rm.textContent = '✕';
                rm.addEventListener('pointerdown', e => e.stopPropagation());
                rm.addEventListener('click', e => {
                    e.stopPropagation();
                    commit({ type: 'slot', match_id: m.id, side }, { type: 'bench' });
                });
                slot.appendChild(rm);
            }

            return slot;
        }

        const who = el('div', 'bk-who');

        const top = el('div', 'bk-who-top');
        const name = el('div', 'bk-name');
        // textContent, never innerHTML — an athlete's name is untrusted input.
        name.textContent = p.name;
        top.appendChild(name);
        if (p.country) top.appendChild(flag(p.country));
        if (p.seed) { const sd = el('span', 'bk-seed'); sd.textContent = p.seed; top.appendChild(sd); }
        if (p.provisional) {
            const dot = el('span', 'bk-prov');
            dot.title = S.cfg.text.provisional;
            top.appendChild(dot);
        }
        who.appendChild(top);

        // The club under the name — the other half of what a corner IS on a
        // competition sheet. Drawn only when there is one: an athlete who
        // competes for nobody gets one line, never an empty second one.
        if (p.club) {
            const sub = el('div', 'bk-who-sub');
            if (p.club_ab) { const ab = el('span', 'bk-club-ab'); ab.textContent = p.club_ab; sub.appendChild(ab); }
            const club = el('span', 'bk-club'); club.textContent = p.club; sub.appendChild(club);
            who.appendChild(sub);
        }

        slot.appendChild(who);

        /* ── THE SCORE, TYPED HERE ────────────────────────────────────────
           A box in the corner's own colour, as the draft draws it. For an
           organiser it is an input that writes on blur; for everybody else it
           is the same box, read-only, so the board looks the same to the hall
           as it does to the desk.

           The MAT is authoritative while a bout is being fought — this is the
           organiser's correction afterwards, and it goes through the same
           me.events.outcome the outcome sheet uses, which appends to the
           officiating log. */
        const value = (p.score === 0 || p.score) ? String(p.score) : '';

        if (decidable() && !m.bye) {
            const input = el('input', 'bk-score-in');
            input.type = 'text';
            input.inputMode = 'numeric';
            input.maxLength = 16;
            input.value = value;
            input.setAttribute('aria-label', S.cfg.text.score);
            input.addEventListener('pointerdown', e => e.stopPropagation());
            input.addEventListener('click', e => e.stopPropagation());
            input.addEventListener('change', () => saveScore(m, side, input.value));
            slot.appendChild(input);
        } else {
            const score = el('div', 'bk-score');
            score.textContent = value;
            slot.appendChild(score);
        }

        /* ── TAP A CORNER, ADVANCE THAT COMPETITOR ────────────────────────
           The draft's headline interaction, and the one the sub-line on the
           header promises. A tap on a decided winner takes the result back
           off, which is the same toggle the draft has.

           Only when BOTH corners are filled: declaring a winner of a bout
           with one competitor in it is what a bye already is, and the server
           refuses it anyway. Not offered while arranging or editing — those
           are other jobs and the same tap means something else in them. */
        if (decidable() && p.competitor_id && m.a && m.a.name && m.b && m.b.name) {
            slot.dataset.decidable = '1';
        }

        return slot;
    }

    /**
     * Declare (or un-declare) the winner of a bout.
     *
     * me.events.outcome is the one door for this — the same one the outcome
     * sheet and the mat's own filing use — so a result taken here is appended
     * to the officiating log and propagated by the package exactly as any
     * other. The board never advances anybody itself: it asks, then redraws
     * whatever the server says happened.
     */
    async function decide(matchId, side) {
        const div = current();
        const m = findMatch(div, matchId);
        if (!m) return;

        const winner = m.winner === side ? '' : side;

        await write(S.cfg.outcomeUrl.replace('__ID__', matchId), { winner }, S.cfg.text.outcomeFailed);
    }

    /** Write one corner's score. */
    async function saveScore(m, side, value) {
        const body = {};
        body[side + '_score'] = String(value ?? '').slice(0, 16);

        await write(S.cfg.outcomeUrl.replace('__ID__', m.id), body, S.cfg.text.outcomeFailed);
    }

    /** Correct the NAME on an entry — the draft's edit mode, for real people. */
    async function renameEntrant(competitorId, value, was) {
        const name = String(value ?? '').trim();
        if (!competitorId || !name || name === was) return;

        await write(S.cfg.entrantUrl.replace('__ID__', competitorId), { name },
            S.cfg.text.renameFailed, 'PUT');
    }

    function findMatch(div, id) {
        for (const r of (div?.rounds || [])) {
            for (const m of (r.matches || [])) if (String(m.id) === String(id)) return m;
        }
        return null;
    }

    /**
     * One write, one answer, one redraw.
     *
     * Every organiser action on this board goes through here: it posts, it
     * shows the server's own sentence in the notice panel, and it re-reads the
     * board rather than guessing what changed. Optimism is not worth it — a
     * result propagates through a whole ladder and a podium, and a board that
     * silently disagrees with the server is worse than a moment's wait.
     */
    async function write(url, body, failMessage, method) {
        if (!url) return false;

        try {
            const res = await fetch(url, {
                method: method || 'POST',
                headers: {
                    'Content-Type': 'application/json', 'Accept': 'application/json',
                    'X-CSRF-TOKEN': S.cfg.csrf, 'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify(body),
            });
            const data = await res.json().catch(() => ({}));

            if (!res.ok || data.success === false) {
                notice(data.message || failMessage);
                await load(true);
                return false;
            }

            if (data.message) notice(data.message);
            await load(true);
            return true;
        } catch (e) {
            notice(failMessage);
            await load(true);
            return false;
        }
    }

    function flag(code) {
        const box = el('div', 'bk-flag');
        const c = String(code).toLowerCase().replace(/[^a-z]/g, '').slice(0, 2);
        if (c.length !== 2) return box;
        const span = el('span');
        span.className = 'fi fi-' + c;   // built via className, never innerHTML
        box.appendChild(span);
        return box;
    }

    function isMine(m) {
        const me = S.cfg.myCompetitorIds || [];
        return me.includes(m.a?.competitor_id) || me.includes(m.b?.competitor_id);
    }

    // -----------------------------------------------------------------
    // Connectors — elbow paths from each feeding bout into the one it feeds.
    // Gold once the bout that feeds it is decided, slate while it is not, and
    // green while the reader is following the competitor who came down it.
    // -----------------------------------------------------------------
    function drawLinks(rounds, cols, yOf, nR) {
        /* Which column holds a given bout — the one thing a connector needs
           that the round alone cannot answer, because in split halves one
           round is TWO columns on opposite edges of the board. */
        const holder = {};
        cols.forEach(col => col.indices.forEach(i => { holder[col.r + ':' + i] = col; }));

        for (let r = 0; r < nR - 1; r++) {
            // A group feeds the knockout through the TABLE, not by position:
            // bout ⌊i/2⌋ of the next round is a semifinal nobody has qualified
            // for yet, and a line drawn to it would state a progression that
            // does not exist.
            if (rounds[r].group) continue;

            rounds[r].matches.forEach((m, i) => {
                const from = holder[r + ':' + i];
                const childIndex = Math.floor(i / 2);
                const to = holder[(r + 1) + ':' + childIndex];
                if (!from || !to) return;

                const y1 = yOf[r][i], y2 = yOf[r + 1][childIndex];
                if (y1 === undefined || y2 === undefined) return;

                /* The connector leaves the card on the side it is TRAVELLING
                   towards. In one-sided that is always the same edge; in split
                   halves the right-hand column's lines run leftwards, which is
                   the whole of the mirroring. */
                const fromX = from.mirrored ? from.x : from.x + from.w;
                const toX = from.mirrored ? to.x + to.w : to.x;
                const midX = (fromX + toX) / 2;

                const decided = !!m.winner;
                const through = decided && m[m.winner] ? m[m.winner].competitor_id : null;

                line('M' + fromX + ' ' + y1 + ' H' + midX + ' V' + y2 + ' H' + toX, decided, through);
            });
        }
    }

    function line(d, decided, competitorId) {
        const p = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        p.setAttribute('d', d);
        p.setAttribute('fill', 'none');
        p.setAttribute('stroke', decided ? GOLD : SLATE);
        p.setAttribute('stroke-width', '2');
        p.setAttribute('stroke-linecap', 'round');
        p.setAttribute('stroke-linejoin', 'round');
        if (competitorId) p.dataset.competitorId = competitorId;
        p.dataset.decided = decided ? '1' : '0';
        S.svg.appendChild(p);
    }

    // -----------------------------------------------------------------
    // Following one competitor across the draw
    //
    // Hovering (or tapping, in view mode) a name lights every slot that name
    // stands in and every connector they travelled down. On a draw of sixteen
    // this is the difference between reading a bracket and searching one.
    // -----------------------------------------------------------------
    /**
     * A tap on a corner, in READING mode.
     *
     * Delegated off the viewport rather than bound per slot, because the board
     * is rebuilt on every load and a per-slot listener would be re-added a few
     * hundred times a morning. It defers to the two other jobs a press can be:
     * panning (the gesture wandered) and arranging (a different mode with a
     * different meaning for the same press).
     */
    function bindDecide() {
        S.vp.addEventListener('click', e => {
            if (S.moved || S.arrange || S.edit) return;
            const slot = e.target.closest?.('.bk-slot[data-decidable="1"]');
            if (!slot || !decidable()) return;
            e.stopPropagation();
            decide(Number(slot.dataset.matchId), slot.dataset.side);
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    // THE NOTICE — the board explaining itself
    //
    // The draft answers a move with a full sentence, and that sentence is
    // usually a tournament RULE being applied: which bye was filled, why a
    // play-in was created, that no played match was changed. `showToast` shows
    // three words at the top of the page; this shows the server's own words
    // beside the thing they are about.
    // ─────────────────────────────────────────────────────────────────────
    function notice(text) {
        if (S.noticeEl) { S.noticeEl.remove(); S.noticeEl = null; }
        clearTimeout(S.noticeTimer);
        if (!text) return;

        const box = el('div', 'bk-notice');
        box.style[S.cfg.rtl ? 'left' : 'right'] = '40px';
        box.setAttribute('role', 'status');

        const span = el('span');
        span.style.flex = '1';
        span.textContent = text;             // the server's words, never markup
        box.appendChild(span);

        const x = el('button', 'bk-notice-x');
        x.type = 'button';
        x.textContent = '\u2715';
        x.setAttribute('aria-label', S.cfg.text.dismiss);
        x.addEventListener('pointerdown', e => e.stopPropagation());
        x.addEventListener('click', e => { e.stopPropagation(); notice(null); });
        box.appendChild(x);

        S.vp.appendChild(box);
        S.noticeEl = box;
        S.noticeTimer = setTimeout(() => notice(null), 6500);
    }

    // ─────────────────────────────────────────────────────────────────────
    // THE BACKDROP — a photograph behind the draw
    //
    // Chosen by the person driving this screen and kept in THEIR browser: it
    // is how they want the hall's board to look today, not a property of the
    // event, and it never leaves the device. Stored as a data URL so it comes
    // back on the next load with no upload and no endpoint.
    // ─────────────────────────────────────────────────────────────────────
    function paintBackground() {
        let layer = S.vp.querySelector('.bk-bg');

        if (!S.bg) { layer?.remove(); return; }

        if (!layer) {
            layer = el('div', 'bk-bg');
            const img = el('div', 'bk-bg-img');
            layer.appendChild(img);
            layer.appendChild(el('div', 'bk-bg-wash'));
            S.vp.insertBefore(layer, S.vp.firstChild);
        }

        layer.querySelector('.bk-bg-img').style.backgroundImage = 'url("' + S.bg.replace(/"/g, '%22') + '")';
    }

    /**
     * Take a picture from the organiser's device.
     *
     * ⚠️ Read as an IMAGE and nothing else. The file never reaches the server,
     * so there is no upload to validate — but it does reach a `background-image`
     * url, and a file that is not an image has no business there. The type is
     * checked, the size is capped (a 20MB photograph in localStorage fails the
     * quota and takes the preference with it), and the result is refused
     * unless it decodes as an image.
     */
    function pickBackground() {
        const input = el('input');
        input.type = 'file';
        input.accept = 'image/png,image/jpeg,image/webp,image/gif';
        input.style.display = 'none';

        input.addEventListener('change', () => {
            const file = input.files && input.files[0];
            input.remove();
            if (!file) return;

            if (!/^image\/(png|jpeg|webp|gif)$/.test(file.type)) { notice(S.cfg.text.bgBadType); return; }
            if (file.size > 6 * 1024 * 1024) { notice(S.cfg.text.bgTooBig); return; }

            const reader = new FileReader();
            reader.onload = () => {
                const url = String(reader.result || '');
                // Decodes as an image, or it is not one whatever it claimed.
                const probe = new Image();
                probe.onload = () => {
                    S.bg = url;
                    try { localStorage.setItem('bk-bg', url); } catch (e) { /* quota */ }
                    paintBackground();
                    emitState();
                };
                probe.onerror = () => notice(S.cfg.text.bgBadType);
                probe.src = url;
            };
            reader.onerror = () => notice(S.cfg.text.bgBadType);
            reader.readAsDataURL(file);
        });

        document.body.appendChild(input);
        input.click();
    }

    function clearBackground() {
        S.bg = null;
        try { localStorage.removeItem('bk-bg'); } catch (e) { /* private mode */ }
        paintBackground();
        emitState();
    }

    function bindHover() {
        S.vp.addEventListener('mouseover', e => {
            const slot = e.target.closest?.('.bk-slot[data-competitor-id]');
            const id = slot && slot.dataset.competitorId;
            setHot(id ? Number(id) : null);
        });
        S.vp.addEventListener('mouseleave', () => setHot(null));
    }

    function setHot(id) {
        if (S.hot === id) return;
        S.hot = id || null;

        S.vp.querySelectorAll('.bk-slot.is-hot').forEach(n => n.classList.remove('is-hot'));
        S.svg.querySelectorAll('path').forEach(p => {
            p.setAttribute('stroke', p.dataset.decided === '1' ? GOLD : SLATE);
            p.setAttribute('stroke-width', '2');
        });

        if (!S.hot) return;

        S.vp.querySelectorAll('.bk-slot[data-competitor-id="' + S.hot + '"]')
            .forEach(n => n.classList.add('is-hot'));
        S.svg.querySelectorAll('path[data-competitor-id="' + S.hot + '"]')
            .forEach(p => { p.setAttribute('stroke', HOT); p.setAttribute('stroke-width', '3'); });
    }

    // -----------------------------------------------------------------
    // THE PODIUM — its own column, after the final
    //
    // Three positions with their medals. Not a bracket: the bracket is who
    // fought whom, and on a ladder of sixteen the answer is four screens of
    // panning away. Built inside the LAYER rather than as an overlay pinned to
    // the viewport, so the one canvas transform pans and zooms it with
    // everything else (owner's call, 2026-09-18 — an overlay kept its own size
    // while the board grew and shrank under it).
    //
    // Two bronzes share the third row where the sport awards both: the row IS
    // the place, so the medal comes from the row and not from the data.
    // -----------------------------------------------------------------
    function renderPodium(div, thirdRound, cols, yOf, cardH, totalW, bandTop, podiumW, split, nR, podiumX) {
        const podium = div.podium || [];
        const at = n => podium.filter(p => Number(p.place) === n);

        const box = el('div', 'bk-podium');
        const champ = at(1)[0] || null;

        if (champ) {
            const card = el('div', 'bk-champ');

            /* Confetti, and only for a champion who has actually been decided.
               Six ribbons on their own offsets and timings — the draft's. */
            [[8, 1.7, .1], [22, 2.1, .5], [38, 1.9, .9], [55, 2.3, .3], [70, 1.8, .7], [86, 2.0, 1.1]]
                .forEach(([left, dur, delay], i) => {
                    const c = el('span', 'bk-conf');
                    c.style.left = left + '%';
                    c.style.background = i % 2 ? '#fff' : INK;
                    c.style.animationDuration = dur + 's';
                    c.style.animationDelay = delay + 's';
                    card.appendChild(c);
                });

            const head = el('div', 'bk-champ-head');
            const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
            svg.setAttribute('width', '16'); svg.setAttribute('height', '12');
            svg.setAttribute('viewBox', '0 0 16 12'); svg.setAttribute('aria-hidden', 'true');
            const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            path.setAttribute('d', 'M1 10h14L14 3l-3.4 3L8 1 5.4 6 2 3z');
            path.setAttribute('fill', INK);
            svg.appendChild(path);
            head.appendChild(svg);
            const word = el('span'); word.textContent = S.cfg.text.champion; head.appendChild(word);
            card.appendChild(head);

            const body = el('div', 'bk-champ-body');
            const av = el('div', 'bk-champ-av');
            if (champ.photo) { const img = el('img'); img.src = champ.photo; img.alt = ''; av.appendChild(img); }
            else av.textContent = initials(champ.name);
            body.appendChild(av);

            const who = el('div'); who.style.minWidth = '0';
            const name = el('div', 'bk-champ-name'); name.textContent = champ.name || ''; who.appendChild(name);
            if (champ.country || champ.club) {
                const sub = el('div', 'bk-champ-sub');
                if (champ.country) sub.appendChild(flag(champ.country));
                if (champ.club) { const c = el('span'); c.textContent = champ.club; sub.appendChild(c); }
                who.appendChild(sub);
            }
            body.appendChild(who);
            card.appendChild(body);

            box.appendChild(card);
        } else {
            const none = el('div', 'bk-champ-none');
            none.textContent = S.cfg.text.championPending;
            box.appendChild(none);
        }

        /* Second, then third — and third may be two people. Each place always
           draws, with a dash against one nobody has taken yet: a division
           halfway through must read as a champion and two places still to be
           decided, never as a one-line table. */
        [2, 3].forEach(n => {
            const winners = at(n);
            const rows = winners.length ? winners : [null];

            rows.forEach(p => {
                const row = el('div', 'bk-place');

                const no = el('span', 'bk-place-no');
                no.style.background = MEDAL[n];
                no.textContent = n;
                row.appendChild(no);

                if (!p) {
                    const tbd = el('span', 'bk-place-tbd');
                    tbd.textContent = S.cfg.text.toBeDecided;
                    row.appendChild(tbd);
                    box.appendChild(row);
                    return;
                }

                const av = el('div', 'bk-place-av');
                if (p.photo) { const img = el('img'); img.src = p.photo; img.alt = ''; av.appendChild(img); }
                else av.textContent = initials(p.name);
                row.appendChild(av);

                const who = el('div', 'bk-place-who');
                const name = el('div', 'bk-place-name'); name.textContent = p.name || ''; who.appendChild(name);
                const word = el('div', 'bk-place-word');
                word.textContent = p.club || S.cfg.text['place' + n] || '';
                who.appendChild(word);
                row.appendChild(who);

                if (p.country) row.appendChild(flag(p.country));

                box.appendChild(row);
            });
        });

        /* ── Where the podium column stands ───────────────────────────────
           One-sided: the column AFTER the final, centred on it. Split halves:
           directly under the final, which is already the centre of the board.
           Either way it is the last thing in the reading direction, which is
           what makes it read as the answer. */
        const finalCol = cols.find(c => c.r === nR - 1);
        const finalY = (yOf[nR - 1] && yOf[nR - 1][0]) ?? bandTop + cardH / 2;

        box.style.insetInlineStart = 'auto';
        box.style.width = podiumW + 'px';

        if (split) {
            // Centred in the middle lane, directly under the final.
            const lane = finalCol ? finalCol.lane : 40;
            const laneW = finalCol ? finalCol.laneW : podiumW;
            box.style.left = (lane + (laneW - podiumW) / 2) + 'px';
            box.style.top = (finalY + cardH / 2 + PODIUM_GAP) + 'px';
        } else {
            // The next position in the ladder's own running total — so the gap
            // between the final and the podium is PODIUM_GAP and nothing else.
            box.style.left = podiumX + 'px';
            box.style.top = '0px';
        }

        // In the layer first, so its height is what these rows actually come
        // to rather than what we guessed.
        S.layer.appendChild(box);

        const h = box.offsetHeight || 220;
        let top = parseFloat(box.style.top);
        if (!split) {
            top = Math.max(bandTop, finalY - h / 2);
            box.style.top = top + 'px';
        }

        let bottom = top + h + 40;

        /* ── The bronze bout ──────────────────────────────────────────────
           Under the podium, in its own cream-and-bronze skin, and only in
           `third: 'match'`. In `shared` the sport awards two bronzes and the
           podium above has already said so, so there is nothing to draw. */
        if (S.third === 'match' && thirdRound && thirdRound.matches && thirdRound.matches.length) {
            const bout = thirdRound.matches[0];
            const card = buildMatch(div, bout, -1, 0);
            card.classList.add('bk-bronze');
            // Sized to the two people in it, centred under the podium above.
            const bp = peopleIn(thirdRound);
            const bronzeW = Math.min(podiumW, widthFor(bp.names, bp.clubs));
            card.style.width = bronzeW + 'px';
            card.style.left = (parseFloat(box.style.left) + (podiumW - bronzeW) / 2) + 'px';
            card.style.top = (top + h + 16) + 'px';
            S.layer.appendChild(card);

            bottom = top + h + 16 + (card.offsetHeight || CARD_H_GUESS) + 40;
        }

        // A podium column wider than the canvas is cropped the same way.
        const right = parseFloat(box.style.left) + podiumW + 40;
        if (right > parseFloat(S.layer.style.width || 0)) {
            S.layer.style.width = right + 'px';
            S.svg.setAttribute('width', right);
        }

        return bottom;
    }

    /**
     * The group table.
     *
     * A ladder needs no such thing — it IS the record of who beat whom. A group
     * answers "who is doing best", which lives in none of its bouts and has to
     * be shown as a table beside them. Drawn into the same transformed canvas
     * as the cards, so it pans and zooms with the board.
     */
    function renderStandings(div, totalW) {
        if (!div.standings || !div.standings.length) return;

        const box = el('div', 'bk-table');

        /* Wide enough to READ a name in, whatever the board is. */
        const width = Math.max(300, Math.min(totalW - 80, 420));
        box.style.width = width + 'px';
        box.style.insetInlineStart = 'auto';
        box.style.left = '40px';
        box.style.top = '24px';

        if (width + 80 > parseFloat(S.layer.style.width || 0)) {
            S.layer.style.width = (width + 80) + 'px';
            S.svg.setAttribute('width', width + 80);
        }

        const head = el('div', 'bk-table-row bk-table-head');
        ['#', '', 'P', 'W', 'D', 'L', 'Pts'].forEach((h, i) => {
            const c = el('span', i === 1 ? 'bk-table-name' : 'bk-table-num');
            c.textContent = h;
            head.appendChild(c);
        });
        box.appendChild(head);

        div.standings.forEach(row => {
            const line = el('div', 'bk-table-row');
            if (row.position <= 2) line.classList.add('is-through');

            const pos = el('span', 'bk-table-num'); pos.textContent = row.position; line.appendChild(pos);

            const name = el('span', 'bk-table-name');
            name.textContent = row.name || '';
            line.appendChild(name);

            [row.played, row.won, row.drawn, row.lost].forEach(v => {
                const c = el('span', 'bk-table-num'); c.textContent = v; line.appendChild(c);
            });

            const pts = el('span', 'bk-table-num is-pts'); pts.textContent = row.points; line.appendChild(pts);

            box.appendChild(line);
        });

        S.layer.appendChild(box);
    }

    // -----------------------------------------------------------------
    // THE HOLDING BASKET — entrants with no slot yet.
    //
    // Lives OUTSIDE the transformed canvas so it stays legible at any zoom,
    // and only exists for someone who may arrange. Minimisable, because on a
    // phone a full basket would cover the draw it is being dragged into.
    // -----------------------------------------------------------------
    function renderBench(div) {
        if (S.bench) { S.bench.remove(); S.bench = null; }
        if (!S.arrange || !arrangeable() || !div) return;

        const bench = el('div', 'bk-bench');
        bench.dataset.benchDrop = '1';

        const head = el('div', 'bk-bench-head');
        const title = el('span', 'bk-bench-count');
        title.style.flex = '1 1 auto';
        title.textContent = S.cfg.text.basket + ' (' + div.bench.length + ')';
        head.appendChild(title);

        const min = el('button', 'bk-bench-min');
        min.type = 'button';
        min.textContent = S.benchMin ? '+' : '–';
        min.setAttribute('aria-expanded', S.benchMin ? 'false' : 'true');
        min.addEventListener('pointerdown', e => e.stopPropagation());
        min.addEventListener('click', e => {
            e.stopPropagation();
            S.benchMin = !S.benchMin;
            renderBench(current());
        });
        head.appendChild(min);
        bench.appendChild(head);

        if (!S.benchMin) {
            const list = el('div', 'bk-bench-list');
            if (!div.bench.length) {
                const empty = el('div', 'bk-bench-empty');
                empty.textContent = S.cfg.text.benchEmpty;
                list.appendChild(empty);
            } else {
                div.bench.forEach(p => {
                    const item = el('div', 'bk-bench-item');
                    item.dataset.competitorId = p.competitor_id;
                    item.tabIndex = 0;

                    const av = el('div', 'bk-av');
                    if (p.photo) { const img = el('img'); img.src = p.photo; img.alt = ''; img.loading = 'lazy'; av.appendChild(img); }
                    else av.textContent = initials(p.name);
                    item.appendChild(av);

                    const who = el('div', 'bk-who');
                    const top = el('div', 'bk-who-top');
                    const name = el('div', 'bk-name'); name.textContent = p.name; top.appendChild(name);
                    if (p.country) top.appendChild(flag(p.country));
                    if (p.provisional) { const d = el('span', 'bk-prov'); d.title = S.cfg.text.provisional; top.appendChild(d); }
                    who.appendChild(top);
                    if (p.club) {
                        const sub = el('div', 'bk-who-sub');
                        if (p.club_ab) { const ab = el('span', 'bk-club-ab'); ab.textContent = p.club_ab; sub.appendChild(ab); }
                        const club = el('span', 'bk-club'); club.textContent = p.club; sub.appendChild(club);
                        who.appendChild(sub);
                    }
                    item.appendChild(who);

                    list.appendChild(item);
                });
            }
            bench.appendChild(list);

            if (div.bench.length) {
                const foot = el('div', 'bk-bench-foot');
                foot.textContent = S.cfg.text.basketHint;
                bench.appendChild(foot);
            }
        }

        S.vp.appendChild(bench);
        S.bench = bench;
        positionBench();
    }

    /**
     * Docked to the corner on a wide viewport, to the bottom on a phone.
     *
     * Two things share this space and must not be sat on: the arrange banner
     * across the top, and the zoom/arrange control column. The CONTROLS move
     * out of the basket's way (see `.bk-controls`) rather than the basket
     * dodging them — the basket is the thing you drag into, so it keeps the
     * roomier lane.
     */
    const BANNER_CLEARANCE = 52;   // arrange banner height + its top inset
    const CONTROL_LANE = 60;       // width of the zoom/arrange button column

    /* The basket holds the same names the board does, so it is measured the
       same way. Its ceiling is lower, because it FLOATS over the board and a
       basket wide enough for a very long name would cover the draw. */
    const BENCH_FIXED = 104, BENCH_W_MIN = 260, BENCH_W_MAX = 360;

    function benchWidth() {
        const widestName = S.widestName || 0;

        return widestName
            ? Math.min(BENCH_W_MAX, Math.max(BENCH_W_MIN, Math.ceil(widestName + BENCH_FIXED)))
            : BENCH_W_MIN;
    }

    function positionBench() {
        if (!S.bench) return;
        const narrow = S.vp.clientWidth < 640;
        const b = S.bench.style;

        if (narrow) {
            b.top = 'auto'; b.bottom = '10px';
            b.insetInlineStart = '10px';
            b.insetInlineEnd = CONTROL_LANE + 'px';
            b.width = 'auto';
            b.maxHeight = S.benchMin ? 'none' : '36%';
        } else {
            b.top = 'auto'; b.bottom = '14px';
            b.width = benchWidth() + 'px';
            b.insetInlineStart = 'auto';
            b.insetInlineEnd = '14px';
            b.maxHeight = S.benchMin ? 'none' : 'calc(100% - ' + (BANNER_CLEARANCE + 28) + 'px)';
        }
    }

    // -----------------------------------------------------------------
    // Pan + zoom — three input paths kept apart so they never double-fire.
    // Lifted from the family-tree runtime: native touch (so a scrollable
    // mobile shell / WebView can't swallow the gesture), pointer events for
    // mouse & pen, and wheel for desktop zoom.
    // -----------------------------------------------------------------
    function apply() {
        S.canvas.style.transform = 'translate(' + S.tx + 'px,' + S.ty + 'px) scale(' + S.scale + ')';
    }

    const clampScale = s => Math.min(2.4, Math.max(0.2, s));

    function zoomTo(target, px, py) {
        const ns = clampScale(target);
        const k = ns / S.scale;
        S.tx = px - (px - S.tx) * k;
        S.ty = py - (py - S.ty) * k;
        S.scale = ns;
        apply();
    }
    function zoomAt(factor, px, py) { zoomTo(S.scale * factor, px, py); }

    /**
     * Fit the whole draw in view, then centre it.
     *
     * Waits for the viewport to actually have a size. A board mounted inside
     * something not yet laid out (a hidden tab, a sheet mid-transition, a page
     * whose stylesheet hasn't applied) would otherwise fit against a zero-sized
     * box and collapse to the minimum zoom.
     */
    function fit(attempt) {
        const w = S.layer.offsetWidth, h = S.layer.offsetHeight;
        const vw = S.vp.clientWidth, vh = S.vp.clientHeight;

        if (!w || !h || vw < 40 || vh < 40) {
            if ((attempt || 0) < 20) {
                requestAnimationFrame(() => fit((attempt || 0) + 1));
            } else {
                S.scale = 1; S.tx = 0; S.ty = 0; apply();
            }
            return;
        }

        const pad = 16;
        const s = clampScale(Math.min((vw - pad * 2) / w, (vh - pad * 2) / h, 1.2));
        S.scale = s;
        S.tx = (vw - w * s) / 2;
        S.ty = (vh - h * s) / 2;
        apply();
    }

    /**
     * Pointer capture keeps a drag alive when the pointer leaves the element,
     * but it THROWS if the browser has no active pointer with that id. Capture
     * is an optimisation, never a requirement.
     */
    function capture(node, pointerId) {
        try { node.setPointerCapture(pointerId); } catch (e) { /* capture is optional */ }
    }

    /** In arrange mode a press on a competitor drags them, not the canvas. */
    const grabbable = target => S.arrange && arrangeable() && !!(
        target.closest('.bk-bench-item') ||
        target.closest('.bk-slot[data-arrangeable="1"]')
    );

    /**
     * How far a finger may travel and still count as a TAP, not a pan.
     * Measured from where the gesture STARTED, so the verdict is about the
     * whole gesture rather than one frame of it.
     */
    const TAP_SLOP = 10;

    function wandered(x, y) {
        if (! S.from) { return false; }

        return Math.hypot(x - S.from.x, y - S.from.y) > TAP_SLOP;
    }

    function bindPanZoom() {
        const vp = S.vp;
        const list = t => [...t].map(p => ({ x: p.clientX, y: p.clientY }));
        let last = [], pinchDist = 0, pinchScale = 1;

        // ---- Touch ----
        vp.addEventListener('touchstart', e => {
            S.moved = false;
            S.from = e.touches.length ? { x: e.touches[0].clientX, y: e.touches[0].clientY } : null;
            if (e.touches.length === 1 && grabbable(e.target)) { last = []; return; }
            last = list(e.touches);
            if (last.length === 2) {
                pinchDist = Math.hypot(last[0].x - last[1].x, last[0].y - last[1].y) || 1;
                pinchScale = S.scale;
            }
        }, { passive: false });

        vp.addEventListener('touchmove', e => {
            if (S.drag) return;                       // a competitor is being dragged
            if (!last.length) return;                 // gesture started on a draggable
            e.preventDefault();                       // block the shell from scrolling
            const now = list(e.touches);
            if (now.length >= 2 && last.length >= 2) {
                const d = Math.hypot(now[0].x - now[1].x, now[0].y - now[1].y);
                const r = vp.getBoundingClientRect();
                zoomTo(pinchScale * (d / pinchDist), (now[0].x + now[1].x) / 2 - r.left, (now[0].y + now[1].y) / 2 - r.top);
                S.moved = true;
            } else if (now.length === 1) {
                const dx = now[0].x - last[0].x, dy = now[0].y - last[0].y;
                if (wandered(now[0].x, now[0].y)) S.moved = true;
                S.tx += dx; S.ty += dy; apply();
            }
            last = now;
        }, { passive: false });

        const tend = e => { last = list(e.touches); if (last.length < 2) pinchDist = 0; };
        vp.addEventListener('touchend', tend);
        vp.addEventListener('touchcancel', tend);

        // ---- Mouse / pen ----
        const pts = new Map();
        vp.addEventListener('pointerdown', e => {
            if (e.pointerType === 'touch' || grabbable(e.target)) return;
            capture(vp, e.pointerId);
            pts.set(e.pointerId, { x: e.clientX, y: e.clientY });
            S.moved = false;
            S.from = { x: e.clientX, y: e.clientY };
        });
        vp.addEventListener('pointermove', e => {
            if (e.pointerType === 'touch') return;
            const prev = pts.get(e.pointerId);
            if (!prev) return;
            const cur = { x: e.clientX, y: e.clientY };
            pts.set(e.pointerId, cur);
            const dx = cur.x - prev.x, dy = cur.y - prev.y;
            if (wandered(cur.x, cur.y)) S.moved = true;
            S.tx += dx; S.ty += dy; apply();
        });
        const up = e => pts.delete(e.pointerId);
        vp.addEventListener('pointerup', up);
        vp.addEventListener('pointercancel', up);

        // ---- Wheel zoom ----
        vp.addEventListener('wheel', e => {
            e.preventDefault();
            const r = vp.getBoundingClientRect();
            zoomAt(e.deltaY < 0 ? 1.1 : 0.9, e.clientX - r.left, e.clientY - r.top);
        }, { passive: false });

        window.addEventListener('resize', positionBench);
    }

    // -----------------------------------------------------------------
    // Arranging — drag, and tap-to-place as the equal path
    //
    // Dragging inside a zoomed, transformed canvas is fiddly on a phone and
    // impossible with a keyboard, so every drag has a two-tap equivalent:
    // tap a competitor to pick them up, tap a slot to put them down.
    // -----------------------------------------------------------------
    function bindArrangeInput() {
        const vp = S.vp;

        vp.addEventListener('pointerdown', e => {
            if (!grabbable(e.target)) return;
            const origin = endpointOf(e.target);
            if (!origin || !origin.name) {
                // Tapping an empty slot while holding someone = place them.
                if (S.pick && origin) commit(S.pick.from, origin.to);
                return;
            }

            e.preventDefault();
            S.drag = {
                from: origin.to, name: origin.name, id: e.pointerId,
                startX: e.clientX, startY: e.clientY, moved: false, ghost: null,
                sourceEl: origin.el,
            };
            capture(vp, e.pointerId);
        });

        vp.addEventListener('pointermove', e => {
            if (!S.drag || e.pointerId !== S.drag.id) return;
            const dx = e.clientX - S.drag.startX, dy = e.clientY - S.drag.startY;

            if (!S.drag.moved && Math.abs(dx) + Math.abs(dy) < 6) return;
            if (!S.drag.moved) {
                S.drag.moved = true;
                S.drag.ghost = ghostFor(S.drag.name);
                document.body.appendChild(S.drag.ghost);
                S.drag.sourceEl?.classList.add('is-source');
            }

            S.drag.ghost.style.left = e.clientX + 'px';
            S.drag.ghost.style.top = e.clientY + 'px';
            highlight(dropTargetAt(e.clientX, e.clientY));
        });

        const finish = e => {
            if (!S.drag || e.pointerId !== S.drag.id) return;
            const drag = S.drag;
            S.drag = null;
            drag.ghost?.remove();
            drag.sourceEl?.classList.remove('is-source');
            highlight(null);

            if (!drag.moved) { pickUp(drag.from, drag.name, drag.sourceEl); return; }

            const target = dropTargetAt(e.clientX, e.clientY);
            if (target) commit(drag.from, target.to);
        };
        vp.addEventListener('pointerup', finish);
        vp.addEventListener('pointercancel', e => {
            if (!S.drag || e.pointerId !== S.drag.id) return;
            S.drag.ghost?.remove();
            S.drag.sourceEl?.classList.remove('is-source');
            S.drag = null;
            highlight(null);
        });

        // Keyboard equivalent of tap-to-place.
        vp.addEventListener('keydown', e => {
            if (e.key !== 'Enter' && e.key !== ' ') return;
            if (!grabbable(e.target)) return;
            e.preventDefault();
            const point = endpointOf(e.target);
            if (!point) return;
            if (S.pick) commit(S.pick.from, point.to);
            else if (point.name) pickUp(point.to, point.name, point.el);
        });

        // Escape drops what you're holding.
        vp.addEventListener('keydown', e => {
            if (e.key === 'Escape' && S.pick) { S.pick = null; applyArrangeClasses(); emitState(); }
        });
    }

    /** Describe whatever is under an element: which end of a move it is. */
    function endpointOf(node) {
        const item = node.closest ? node.closest('.bk-bench-item') : null;
        if (item) {
            return {
                el: item,
                name: item.querySelector('.bk-name')?.textContent || '',
                to: { type: 'bench', competitor_id: Number(item.dataset.competitorId) },
            };
        }

        const slot = node.closest ? node.closest('.bk-slot[data-arrangeable="1"]') : null;
        if (slot) {
            return {
                el: slot,
                name: slot.classList.contains('is-empty') ? '' : (slot.querySelector('.bk-name')?.textContent || ''),
                to: { type: 'slot', match_id: Number(slot.dataset.matchId), side: slot.dataset.side },
            };
        }

        if (node.closest && node.closest('[data-bench-drop="1"]')) {
            return { el: S.bench, name: '', to: { type: 'bench' } };
        }

        return null;
    }

    function pickUp(from, name, sourceEl) {
        S.pick = (S.pick && sameEnd(S.pick.from, from)) ? null : { from, name, el: sourceEl };
        applyArrangeClasses();
        emitState();
    }

    const sameEnd = (a, b) =>
        a.type === b.type && a.match_id === b.match_id && a.side === b.side && a.competitor_id === b.competitor_id;

    function ghostFor(name) {
        const g = el('div', 'bk-ghost');
        const av = el('div', 'bk-av'); av.textContent = initials(name);
        g.appendChild(av);
        const t = el('span'); t.textContent = name;
        g.appendChild(t);
        return g;
    }

    function dropTargetAt(x, y) {
        const node = document.elementFromPoint(x, y);
        if (!node) return null;
        const point = endpointOf(node);
        if (!point) return null;
        // A competitor may be dropped into a first-round slot or back into the
        // basket — nothing else is a target.
        if (point.to.type === 'bench') return { el: S.bench, to: { type: 'bench' } };
        return point;
    }

    function highlight(target) {
        S.vp.querySelectorAll('.is-target').forEach(n => n.classList.remove('is-target'));
        S.bench?.classList.remove('is-target');
        if (!target) return;
        if (target.to.type === 'bench') S.bench?.classList.add('is-target');
        else target.el?.classList.add('is-target');
    }

    function applyArrangeClasses() {
        S.vp.classList.toggle('is-arranging', S.arrange && arrangeable());
        S.vp.classList.toggle('bk-can-decide', decidable() && !S.arrange && !S.edit);
        S.vp.querySelectorAll('.is-picked').forEach(n => n.classList.remove('is-picked'));
        if (!S.pick) return;

        // Re-find the picked element by identity — the DOM may have been
        // re-rendered since it was picked up.
        const sel = S.pick.from.type === 'bench'
            ? '.bk-bench-item[data-competitor-id="' + S.pick.from.competitor_id + '"]'
            : '.bk-slot[data-match-id="' + S.pick.from.match_id + '"][data-side="' + S.pick.from.side + '"]';
        S.vp.querySelector(sel)?.classList.add('is-picked');
    }

    // -----------------------------------------------------------------
    // Saving a move
    // -----------------------------------------------------------------
    async function commit(from, to) {
        S.pick = null;
        if (sameEnd(from, to)) { applyArrangeClasses(); emitState(); return; }

        // Optimism is not worth it here: a move can be refused server-side
        // (locked draw, competitor no longer entered), and a bracket that
        // silently disagrees with the server is worse than a brief wait.
        try {
            const res = await fetch(S.cfg.arrangeUrl, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json', 'Accept': 'application/json',
                    'X-CSRF-TOKEN': S.cfg.csrf, 'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ category_id: S.division, from, to }),
            });
            const data = await res.json().catch(() => ({}));

            if (!res.ok || !data.success) {
                notice(data.message || S.cfg.text.moveFailed);
                await load(true);       // resync — the server is the truth
                return;
            }

            patchDivision(data.data?.division);
            notice(data.message);
        } catch (e) {
            notice(S.cfg.text.moveFailed);
            await load(true);
        }
    }

    /** Swap one division's data in and redraw it, keeping the current view. */
    function patchDivision(division) {
        if (!division) { load(true); return; }
        const i = S.divisions.findIndex(d => d.id === division.id);
        if (i === -1) { load(true); return; }
        S.divisions[i] = division;
        render(true);
        emitState();
    }

    async function clearDraw() {
        if (!arrangeable() || !S.division) return;
        const ok = await (window.confirmAction ? window.confirmAction({
            title: S.cfg.text.clearTitle,
            message: S.cfg.text.clearMessage,
            type: 'danger',
            confirmText: S.cfg.text.clearConfirm,
        }) : Promise.resolve(false));
        if (!ok) return;

        try {
            const res = await fetch(S.cfg.clearUrl, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json', 'Accept': 'application/json',
                    'X-CSRF-TOKEN': S.cfg.csrf, 'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ category_id: S.division }),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || !data.success) throw new Error(data.message || S.cfg.text.moveFailed);
            patchDivision(data.data?.division);
            notice(data.message);
        } catch (e) {
            notice(e.message);
            load(true);
        }
    }

    // -----------------------------------------------------------------
    // Realtime — someone else's draw edit or a bout landing shows up here
    // without anyone refreshing.
    // -----------------------------------------------------------------
    function bindRealtime() {
        // The mobile shell re-runs inline scripts on every AJAX nav, so the
        // previous handler is removed before a new one is added.
        if (window.__bracketRealtime) {
            window.removeEventListener('realtime:events', window.__bracketRealtime);
        }
        let pending = null;
        window.__bracketRealtime = e => {
            const d = e.detail || {};
            if (!S || d.event !== S.cfg.eventUuid) return;
            // 'entry-updated' is a competitor themselves changing — a
            // corrected photograph, name, club or belt. The board draws all
            // four.
            if (!['draw', 'outcome', 'entrants', 'podium', 'entry-updated'].includes(d.action)) return;
            // Coalesce a burst (a draw generates one message per division).
            clearTimeout(pending);
            pending = setTimeout(() => load(true), 250);
        };
        window.addEventListener('realtime:events', window.__bracketRealtime);
    }

    // -----------------------------------------------------------------
    function emit(name, detail) { S.vp.dispatchEvent(new CustomEvent(name, { detail, bubbles: true })); }
    function emitState() {
        emit('bracket:state', {
            arrange: S.arrange && arrangeable(),
            picked: S.pick ? S.pick.name : null,
            division: S.division,
            divisions: summary(),
            // What the Actions menu ticks. Read from the board rather than
            // mirrored in the menu, so the two can never disagree.
            canManage: S.canManage, canArrange: arrangeable(),
            unlocked: S.unlocked, unlockable: S.canManage || S.canArrange,
            layout: S.layout, third: S.third, view: S.view,
            edit: S.edit, background: !!S.bg,
        });
    }

    /* ── The four view settings ───────────────────────────────────────────
       Each writes nothing to the server, remembers itself in this browser, and
       redraws. `view` is the draft's Auto / Desktop / Mobile: it decides
       whether the board lays itself out for a wall and a laptop or for a phone
       held in one hand, instead of leaving that to the window's width alone. */
    function setLayout(value) {
        S.layout = value === 'split' ? 'split' : 'linear';
        setPref('layout', S.layout);
        render();
        emitState();
    }

    function setThird(value) {
        S.third = value === 'shared' ? 'shared' : 'match';
        setPref('third', S.third);
        render();
        emitState();
    }

    function setView(value) {
        S.view = ['auto', 'desktop', 'mobile'].includes(value) ? value : 'auto';
        setPref('view', S.view);
        applyView();
        render();
        emitState();
    }

    /** Is this board being read as a phone right now? */
    function isMobileView() {
        if (S.view === 'mobile') return true;
        if (S.view === 'desktop') return false;
        return S.vp.clientWidth < 640 || window.innerHeight > window.innerWidth;
    }

    function applyView() {
        S.vp.classList.toggle('is-phone', isMobileView());
    }

    /** Edit names on / off. Organiser only; the endpoints say so themselves. */
    function toggleEdit(on) {
        if (!decidable()) return;
        S.edit = on === undefined ? !S.edit : !!on;
        if (S.edit) { S.arrange = false; S.pick = null; }
        render(true);
        emitState();
    }

    /**
     * Unlock the board, or lock it again.
     *
     * Unlocking turns editing ON in every sense at once — that is what the
     * word means here, and a switch that unlocked three of four things would
     * be a puzzle rather than a catch. Arranging comes on with it, so a drag
     * works the moment the padlock opens rather than after a second control.
     *
     * Locking puts everything back: the mode, the held competitor, the name
     * fields. A board that was left mid-edit and then locked must read as a
     * board, not as a half-finished form.
     */
    function setUnlocked(on) {
        if (!S.canManage && !S.canArrange) return;

        S.unlocked = on === undefined ? !S.unlocked : !!on;

        S.pick = null;
        S.edit = false;
        S.arrange = S.unlocked && S.canArrange && (current()?.arrangeable !== false);

        renderBench(current());
        render(true);
        emitState();
    }

    function toggleArrange(on) {
        if (!arrangeable()) return;
        S.arrange = on === undefined ? !S.arrange : !!on;
        S.pick = null;
        if (S.arrange) S.edit = false;
        renderBench(current());
        applyArrangeClasses();
        emitState();
    }

    return {
        mount, reload: keep => load(keep !== false), show, clearDraw, toggleArrange,
        zoomIn: () => zoomAt(1.18, S.vp.clientWidth / 2, S.vp.clientHeight / 2),
        zoomOut: () => zoomAt(0.85, S.vp.clientWidth / 2, S.vp.clientHeight / 2),
        fit,
        // The draft's Actions menu drives all of these.
        setLayout, setThird, setView, toggleEdit, setUnlocked,
        pickBackground, clearBackground,
        notice,
        // The add-fighter window asks the board which division it is over, and
        // hands back a competitor to admit.
        division: () => S.division,
        admit: id => write(S.cfg.admitUrl,
            { category_id: S.division, competitor_id: Number(id) }, S.cfg.text.moveFailed, 'PUT'),
        state: () => ({
            arrange: S.arrange, canArrange: S.canArrange, canManage: S.canManage,
            unlocked: S.unlocked,
            division: S.division, layout: S.layout, third: S.third, view: S.view,
            edit: S.edit, background: !!S.bg,
        }),
    };
})();
</script>
