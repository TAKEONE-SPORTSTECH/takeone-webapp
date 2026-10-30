{{--
    TakeNote's look — Margin's "ink on night paper", in a pocket: a deep desk,
    notes as warm paper tiles with a margin rule in the colour of what they
    hold, and a capture bar under the thumb. The note page itself is the
    shared editor (partials/editor-styles).
--}}
<style>
body { overscroll-behavior-y: contain; }
.app { max-width: 560px; margin: 0 auto; min-height: 100dvh; position: relative;
    background:
        radial-gradient(900px 420px at 110% -10%, rgba(124,107,245,.22), transparent 60%),
        radial-gradient(700px 500px at -20% 50%, rgba(224,69,123,.07), transparent 60%),
        radial-gradient(rgba(255,255,255,.05) 1px, transparent 1.2px) 0 0 / 18px 18px,
        var(--desk);
    padding: calc(14px + var(--safe-t)) 0 calc(118px + var(--safe-b)); }

/* ── The head ─────────────────────────────────────────────────────────── */
.head { padding: 6px max(18px, var(--sa-r, env(safe-area-inset-right, 0px))) 0 max(18px, var(--sa-l, env(safe-area-inset-left, 0px))); }
.today { font-size: 11.5px; color: rgba(255,255,255,.55); display: flex; align-items: center; gap: 7px; }
.head-row { display: flex; align-items: center; gap: 10px; margin-top: 2px; }
.head h1 { margin: 0; font: 400 40px/1 var(--serif); letter-spacing: -.01em; }
.people { display: flex; margin-inline-start: auto; }
.av { width: 28px; height: 28px; border-radius: 50%; display: grid; place-items: center; font-size: 11px; font-weight: 800; color: #fff; background: var(--c, var(--violet)); border: 2px solid var(--desk); flex-shrink: 0; }
.people .av + .av { margin-inline-start: -9px; }
.round { width: 38px; height: 38px; border-radius: 50%; display: grid; place-items: center; background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.12); font-size: 16px; flex-shrink: 0; transition: transform .15s var(--ease), background .2s; }
.round:active { transform: scale(.9); }
.round.is-on { background: var(--paper); color: var(--ink); }

.search { margin: 12px max(14px, var(--sa-r, env(safe-area-inset-right, 0px))) 0 max(14px, var(--sa-l, env(safe-area-inset-left, 0px))); height: 44px; border-radius: 14px; background: rgba(255,255,255,.07); border: 1px solid rgba(255,255,255,.12); display: flex; align-items: center; gap: 9px; padding: 0 12px; animation: rise .25s var(--spring); }
.search[hidden] { display: none; }
.search i { opacity: .55; }
.search input { flex: 1; min-width: 0; height: 100%; border: 0; outline: 0; background: none; color: #fff; font: 500 16px var(--sans); }
.search input::placeholder { color: rgba(255,255,255,.42); }

.filters { display: flex; gap: 6px; padding: 12px max(18px, var(--sa-r, env(safe-area-inset-right, 0px))) 10px max(18px, var(--sa-l, env(safe-area-inset-left, 0px))); overflow-x: auto; scrollbar-width: none; }
.filters::-webkit-scrollbar { display: none; }
.filter { flex: 0 0 auto; height: 30px; padding: 0 12px; border-radius: 999px; font-size: 12px; font-weight: 600; background: rgba(255,255,255,.08); color: rgba(255,255,255,.72); transition: background .2s, color .2s; }
.filter.is-on { background: var(--paper); color: var(--ink); }
.filter .n { opacity: .55; margin-inline-start: 4px; font-variant-numeric: tabular-nums; }

/* ── The notes: rows grouped by when, as the notes apps keep them ─────── */
.notes { position: relative; display: flex; flex-direction: column; padding: 0 max(14px, var(--sa-r, env(safe-area-inset-right, 0px))) 0 max(14px, var(--sa-l, env(safe-area-inset-left, 0px))); }
.grp { margin: 20px 6px 8px; font: 800 11.5px var(--sans); letter-spacing: .08em; text-transform: uppercase; color: rgba(255,255,255,.5); }
.grp:first-child { margin-top: 8px; }
.tile { position: relative; z-index: 1; flex-shrink: 0; display: flex; align-items: center; gap: 12px; width: 100%; min-height: 64px; text-align: start; padding: 11px 14px; color: #fff;
    background: #221839; border-bottom: 1px solid rgba(255,255,255,.06); outline: none;
    animation: rise .3s var(--spring) both; transition: transform .25s var(--ease), background .15s; }
.tile.is-first { border-start-start-radius: 14px; border-start-end-radius: 14px; }
.tile.is-last { border-end-start-radius: 14px; border-end-end-radius: 14px; border-bottom: 0; }
.tile:active { background: #2b2047; }
.tile:focus-visible { box-shadow: inset 0 0 0 2px var(--violet); }
.tile .tx { flex: 1; min-width: 0; }
.tile .tt, .tile .ex { unicode-bidi: plaintext; text-align: start; } /* an Arabic note reads right to left in the list too */
.tile .tt { margin: 0; font: 700 16px/1.25 var(--serif); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.tile .tt .bi { font-size: 12px; color: #f7b64b; margin-inline-end: 6px; }
.tile .ex { margin: 3px 0 0; font-size: 14px; line-height: 1.35; color: rgba(255,255,255,.6); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.tile .ex .age { font-weight: 600; color: rgba(255,255,255,.88); margin-inline-end: 8px; font-variant-numeric: tabular-nums; }
.tile .mt { display: flex; align-items: center; gap: 10px; margin-top: 6px; font-size: 11.5px; color: rgba(255,255,255,.55); flex-wrap: wrap; }
.tile .k { display: inline-flex; align-items: center; gap: 4px; font-weight: 600; }
.tile .ln { display: inline-flex; align-items: center; gap: 5px; font-weight: 600; }
.tile .ln::before { content: ''; width: 7px; height: 7px; border-radius: 50%; background: var(--lc); }
.tile .wave { display: flex; align-items: center; gap: 2px; height: 16px; margin-top: 5px; }
.tile .wave i { width: 2.5px; border-radius: 3px; background: var(--k); opacity: .85; }
.tile .th-wrap { position: relative; flex-shrink: 0; }
.tile .th-wrap .bi { position: absolute; inset: 0; display: grid; place-items: center; color: #fff; font-size: 18px; text-shadow: 0 2px 8px rgba(0,0,0,.6); }
.tile .th { width: 52px; height: 52px; flex-shrink: 0; display: block; border-radius: 10px; object-fit: cover; background: var(--paper-2); }
.tile .row-av { width: 26px; height: 26px; font-size: 10.5px; border: 0; }
.tile.is-skeleton { height: 64px; border-radius: 14px; margin-bottom: 8px; background: linear-gradient(100deg, rgba(255,255,255,.06) 30%, rgba(255,255,255,.12) 50%, rgba(255,255,255,.06) 70%) 0 0/300% 100%; animation: shimmer 1.3s linear infinite; }
/* a swiped row: its actions wait underneath it (takenote/layout) */
.nb-acts { position: absolute; z-index: 0; inset-inline-end: max(14px, var(--sa-r, env(safe-area-inset-right, 0px))); display: flex; overflow: hidden; border-radius: 0 14px 14px 0; }
[dir=rtl] .nb-acts { border-radius: 14px 0 0 14px; }
.nb-acts button { width: 64px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px; font-size: 11px; font-weight: 700; color: #fff; background: var(--a); }
.nb-acts .bi { font-size: 17px; }
.tile.is-swiped { transition: transform .25s var(--ease); }

.empty { margin: 30px 26px 0; text-align: center; color: rgba(255,255,255,.6); }
.empty b { display: block; font: italic 400 26px/1.2 var(--serif); color: #fff; }
.empty p { margin: 8px 0 0; font-size: 13px; line-height: 1.5; }
.empty .ink { width: 120px; height: 46px; margin: 0 auto 10px; display: block; color: var(--violet); }

/* ── The capture bar ──────────────────────────────────────────────────── */
.capture { position: fixed; z-index: 30; left: 50%; transform: translateX(-50%); bottom: calc(16px + var(--safe-b));
    width: min(calc(100% - 24px), 420px); border-radius: 28px; background: rgba(38,28,61,.94); border: 1px solid rgba(255,255,255,.1);
    -webkit-backdrop-filter: blur(12px); backdrop-filter: blur(12px); box-shadow: 0 18px 32px -14px #000;
    display: flex; align-items: center; justify-content: space-between; padding: 7px 8px; transition: transform .35s var(--spring), opacity .25s; }
.capture.is-away { transform: translate(-50%, 140%); opacity: 0; pointer-events: none; }
.cap { display: flex; flex-direction: column; align-items: center; gap: 4px; min-width: 52px; font-size: 10.5px; font-weight: 600; color: rgba(255,255,255,.78); border-radius: 16px; padding: 4px 0; }
.cap .b { width: 40px; height: 40px; border-radius: 13px; display: grid; place-items: center; font-size: 18px; color: var(--k); background: color-mix(in srgb, var(--k) 20%, transparent); transition: transform .15s var(--ease); }
.cap:active .b { transform: scale(.88); }
.cap.main .b { width: 54px; height: 54px; border-radius: 18px; background: var(--violet); color: #fff; font-size: 24px; box-shadow: 0 12px 22px -8px rgba(124,107,245,.95); }
.cap.main { margin-top: -26px; }

/* ── Notes / Board ────────────────────────────────────────────────────── */
.seg { margin: 12px max(14px, var(--sa-r, env(safe-area-inset-right, 0px))) 2px max(14px, var(--sa-l, env(safe-area-inset-left, 0px))); padding: 3px; border-radius: 14px; background: rgba(255,255,255,.07);
    display: grid; grid-template-columns: 1fr 1fr; gap: 3px; }
.seg button { height: 34px; border-radius: 11px; font-size: 13px; font-weight: 700; color: rgba(255,255,255,.66); transition: background .2s, color .2s, box-shadow .2s; }
.seg button.is-on { background: var(--paper); color: var(--ink); box-shadow: 0 6px 14px -8px #000; }
.seg .n { opacity: .5; margin-inline-start: 5px; font-variant-numeric: tabular-nums; }
.tile .who { display: inline-flex; align-items: center; gap: 4px; font-weight: 600; }
.due { display: inline-flex; align-items: center; gap: 3px; padding: 1px 7px; border-radius: 7px; font-weight: 700; background: #ede9fe; color: #5b4bd6; }
.due.is-soon { background: #fef3c7; color: #92400e; }
.due.is-late { background: #fee2e2; color: #b91c1c; }

.notes[hidden] { display: none; }
.board[hidden] { display: none; }
/* ── The board, as Trello's: grey lists on the desk, white cards ─────────── */
.board { --tl-w: 272px; }
.tb { display: flex; align-items: flex-start; gap: 12px; overflow-x: auto; overscroll-behavior-x: contain;
    padding: 4px max(14px, var(--sa-l, env(safe-area-inset-left, 0px))) 16px; height: var(--tb-h, auto); scrollbar-width: thin; scrollbar-color: rgba(255,255,255,.25) transparent; }
.tl { flex: 0 0 var(--tl-w); max-height: 100%; display: flex; flex-direction: column; border-radius: 12px; background: #f1f2f4; color: #172b4d;
    box-shadow: 0 1px 1px rgba(9,30,66,.25), 0 0 1px rgba(9,30,66,.31); }
.tl-h { flex-shrink: 0; display: flex; align-items: center; gap: 8px; padding-block: 10px 6px; padding-inline: 14px 8px; }
[dir=rtl] .tl-h { padding: 10px 14px 6px 8px; }
.tl-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--c); flex-shrink: 0; }
.tl-h h3 { margin: 0; flex: 1; min-width: 0; font: 600 14px/1.3 var(--sans); overflow-wrap: anywhere; unicode-bidi: plaintext; text-align: start; }
.tl-n { font-size: 12px; font-weight: 600; color: #626f86; font-variant-numeric: tabular-nums; }
.tl-more { width: 32px; height: 32px; border-radius: 8px; display: grid; place-items: center; color: #44546f; font-size: 13px; flex-shrink: 0; }
.tl-more:hover { background: rgba(9,30,66,.08); }
.tl-cards { flex: 1 1 auto; min-height: 4px; overflow-y: auto; overflow-x: hidden; display: flex; flex-direction: column; gap: 8px; padding: 2px 8px 2px; scrollbar-width: thin; }
.tl-f { flex-shrink: 0; padding: 8px; }
.tl-add { width: 100%; height: 36px; border-radius: 8px; display: flex; align-items: center; gap: 8px; padding: 0 8px; font-size: 14px; font-weight: 500; color: #44546f; text-align: start; transition: background .15s, color .15s; }
.tl-add:hover { background: rgba(9,30,66,.08); color: #172b4d; }
.tl-compose textarea { display: block; width: 100%; resize: none; overflow: hidden; border: 0; outline: 0; border-radius: 8px; padding: 8px 12px; background: #fff;
    font: 400 14px/1.43 var(--note); color: #172b4d; box-shadow: 0 1px 1px rgba(9,30,66,.25), 0 0 1px rgba(9,30,66,.31); }
.tl-cbar { display: flex; align-items: center; gap: 4px; margin-top: 8px; }
.tl-go { height: 32px; padding: 0 12px; border-radius: 6px; background: var(--violet); color: #fff; font-size: 14px; font-weight: 500; }
.tl-go:hover { filter: brightness(1.08); }
.tl-x { width: 32px; height: 32px; border-radius: 6px; display: grid; place-items: center; color: #44546f; }
.tl-x:hover { background: rgba(9,30,66,.08); }
/* a collapsed list: a narrow strip, its name running down it */
.tl.is-folded { flex: 0 0 auto; width: 44px; cursor: pointer; padding-bottom: 10px; }
.tl.is-folded .tl-h { flex-direction: column; padding: 10px 0 0; gap: 10px; }
.tl.is-folded .tl-more { order: -1; }
.tl.is-folded .tl-h h3 { writing-mode: vertical-rl; flex: none; white-space: nowrap; }
.tl.is-folded .tl-cards, .tl.is-folded .tl-f { display: none; }

.tc { position: relative; flex-shrink: 0; border-radius: 8px; background: #fff; color: #172b4d; cursor: pointer; text-align: start;
    box-shadow: 0 1px 1px rgba(9,30,66,.25), 0 0 1px rgba(9,30,66,.31); outline: 2px solid transparent; outline-offset: -2px; transition: outline-color .12s; }
.tc:hover, .tc:focus-visible { outline-color: var(--violet); }
.tc-cover { position: relative; height: 110px; border-radius: 8px 8px 0 0; overflow: hidden; background: #dcdfe4; }
.tc-cover img { width: 100%; height: 100%; object-fit: cover; display: block; }
.tc-cover .bi { position: absolute; inset: 0; display: grid; place-items: center; color: #fff; font-size: 26px; text-shadow: 0 2px 10px rgba(0,0,0,.55); }
.tc-in { padding: 8px 12px 6px; }
.tc-labels { display: flex; flex-wrap: wrap; gap: 4px; margin-bottom: 6px; }
.tc-label { height: 16px; min-width: 40px; padding: 0 8px; border-radius: 4px; font-size: 12px; font-weight: 500; line-height: 16px; color: #fff; text-align: center; }
.tc-label.is-urgent { background: #c9372c; }
.tc-label.is-high { background: #e2770f; }
.tc-label.is-low { background: #4bce97; color: #164b35; }
.tc-row { display: flex; align-items: flex-start; gap: 0; }
.tc-done { width: 0; height: 16px; margin-top: 2px; border-radius: 50%; border: 1.5px solid #626f86; color: transparent; font-size: 10px; display: grid; place-items: center; flex-shrink: 0;
    opacity: 0; overflow: hidden; transition: width .15s, margin .15s, opacity .15s, background .15s; }
.tc:hover .tc-done, .tc-done:focus-visible, .tc.is-done .tc-done { width: 16px; margin-inline-end: 6px; opacity: 1; }
.tc.is-done .tc-done { background: #22a06b; border-color: #22a06b; color: #fff; }
.tc-done:hover { border-color: #22a06b; color: #22a06b; }
.tc-title { margin: 0; flex: 1; min-width: 0; font: 400 14px/20px var(--note); overflow-wrap: anywhere; unicode-bidi: plaintext; text-align: start; }
.tc.is-done .tc-title { color: #44546f; }
.tc-badges { display: flex; flex-wrap: wrap; align-items: center; gap: 2px 4px; margin-top: 4px; font-size: 12px; color: #44546f; }
.tc-b { display: inline-flex; align-items: center; gap: 4px; height: 24px; padding: 0 4px; border-radius: 3px; }
.tc-due { display: inline-flex; align-items: center; gap: 4px; height: 24px; padding: 0 6px; border-radius: 3px; font-weight: 500; }
.tc-due.is-soon { background: #f5cd47; color: #533f04; }
.tc-due.is-late { background: #c9372c; color: #fff; }
.tc-due.is-done { background: #1f845a; color: #fff; }
.tc-mem { margin-inline-start: auto; display: inline-flex; }
.tc-mem .av { width: 24px; height: 24px; font-size: 10px; border: 0; }
.tc-edit { position: absolute; top: 4px; inset-inline-end: 4px; width: 32px; height: 32px; border-radius: 50%; display: grid; place-items: center; font-size: 13px; color: #44546f;
    background: #f1f2f4; opacity: 0; transition: opacity .12s; }
.tc:hover .tc-edit, .tc-edit:focus-visible { opacity: 1; }
.tc-edit:hover { background: #dcdfe4; color: #172b4d; }
.tc.is-pending { opacity: .55; }
/* drag: the card tilts as it is lifted, a grey slot opens where it will land */
.tc.is-ghost { background: rgba(9,30,66,.08) !important; box-shadow: none !important; outline: 0 !important; }
.tc.is-ghost > * { visibility: hidden; }
.tc.is-lifted { opacity: 1 !important; transform: rotate(4deg); box-shadow: 0 12px 24px -6px rgba(9,30,66,.45), 0 0 1px rgba(9,30,66,.31) !important; cursor: grabbing; }
[dir=rtl] .tc.is-lifted { transform: rotate(-4deg); }
.board.is-dragging .tc:hover { outline-color: transparent; }
/* while a card is dragged: every list stands full height, its whole body a place to drop (not only the
   strip its cards happen to cover), the list being aimed at lights up, and the phone's snap lets go */
.board.is-dragging .tl { align-self: stretch; }
.board.is-dragging .tl-cards { flex: 1 1 auto; min-height: 80px; padding-bottom: 24px; }
.board.is-dragging .tl.is-target { box-shadow: 0 0 0 2px #7c6bf5, 0 8px 22px -10px rgba(124,107,245,.6); background: #ebe8fb; }
.board.is-dragging .tb { scroll-snap-type: none; }
.tc { -webkit-touch-callout: none; -webkit-user-select: none; user-select: none; } /* a held finger lifts the card, never selects or offers to copy */
@media (hover: none) { .tc-edit { opacity: 1; background: transparent; } .tc .tc-done { width: 16px; margin-inline-end: 6px; opacity: 1; } }
/* a phone: one list at a time, the next one peeking, swiped across */
@media (max-width: 699px) {
    .tb { scroll-snap-type: x mandatory; gap: 10px; padding-inline: 16px; }
    .tl { flex-basis: calc(100vw - 52px); max-width: 340px; scroll-snap-align: center; }
    .tl.is-folded { scroll-snap-align: start; }
}
.ss-pri.is-low.is-on { border-color: #4bce97; color: #216e4e; background: #dcfff1; }
.ss-pri.is-high.is-on { border-color: #e2770f; color: #a54800; background: #fff3eb; }
.ss-pri.is-urgent.is-on { border-color: #c9372c; color: #ae2e24; background: #ffeceb; }
.tile.is-ghost { opacity: .35; border: 2px dashed rgba(124,107,245,.6); box-shadow: none; }
.tile.is-lifted { opacity: 1 !important; transform: rotate(-2deg) scale(1.03); box-shadow: 0 26px 44px -16px rgba(0,0,0,.8), 0 0 0 2px #7c6bf5; }
[dir=rtl] .tile.is-lifted { transform: rotate(2deg) scale(1.03); }

/* ── Send to the board ────────────────────────────────────────────────── */
.send-bar { height: 48px; border-radius: 15px; background: var(--violet); color: #fff; font-weight: 700; font-size: 14.5px; display: flex; align-items: center; justify-content: center; gap: 8px;
    box-shadow: 0 14px 26px -12px rgba(124,107,245,.95); border: 0; }
.ss { position: fixed; inset: 0; z-index: 110; display: flex; align-items: flex-end; justify-content: center; }
.ss-dim { position: absolute; inset: 0; background: rgba(10,6,20,.55); animation: ss-fade .2s; }
@keyframes ss-fade { from { opacity: 0; } }
.ss-sheet { position: relative; width: min(100%, 560px); max-height: 92dvh; display: flex; flex-direction: column; background: #fff; color: var(--ink); border-radius: 24px 24px 0 0;
    overflow: hidden; animation: ss-up .38s cubic-bezier(.2,1.1,.4,1); }
@keyframes ss-up { from { transform: translateY(40%); opacity: 0; } }
.ss-band { flex-shrink: 0; position: relative; overflow: hidden; color: #fff; padding: 10px 18px 16px; border-radius: 24px 24px 0 0; background: linear-gradient(150deg, #7c6bf5, #7c6bf5b0); }
.ss-band::before { content: ''; position: absolute; right: -32px; top: -40px; width: 144px; height: 144px; border-radius: 50%; background: rgba(255,255,255,.1); }
.ss-band .hd { width: 40px; height: 4px; border-radius: 4px; background: rgba(255,255,255,.4); margin: 0 auto 12px; }
.ss-folder .ss-band p:empty { display: none; } /* a sheet with no sub-line: the title sits centred on its tile */
.ss-band .r { position: relative; display: flex; align-items: flex-start; gap: 12px; }
.ss-band .tile2 { width: 46px; height: 46px; border-radius: 16px; background: rgba(255,255,255,.2); display: grid; place-items: center; font-size: 20px; flex-shrink: 0; }
.ss-band h3 { margin: 0; font-size: 18px; font-weight: 900; line-height: 1.2; }
.ss-band p { margin: 3px 0 0; font-size: 12px; color: rgba(255,255,255,.86); }
.ss-band .x { margin-inline-start: auto; width: 36px; height: 36px; border-radius: 50%; background: rgba(255,255,255,.15); border: 1px solid rgba(255,255,255,.25); display: grid; place-items: center; flex-shrink: 0; }
.ss-body { flex: 1; overflow-y: auto; padding: 8px 16px 6px; }
.ss-body h4 { margin: 14px 2px 8px; font-size: 10.5px; text-transform: uppercase; letter-spacing: .08em; color: var(--ink-soft); }
.ss-lanes { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.ss-opt { display: flex; align-items: center; gap: 9px; min-height: 46px; padding: 9px 12px; border-radius: 13px; border: 1.5px solid #ebe7f2; font-size: 13.5px; font-weight: 700; color: var(--ink); text-align: start;
    transition: border-color .15s, background .15s; }
.ss-opt i { width: 10px; height: 10px; border-radius: 50%; background: var(--c); flex-shrink: 0; }
.ss-opt em { margin-inline-start: auto; font-style: normal; font-size: 11px; color: var(--ink-soft); font-weight: 600; }
.ss-opt.is-on { border-color: var(--violet); background: rgba(124,107,245,.07); }
.ss-pills { display: flex; flex-wrap: wrap; gap: 7px; }
.ss-pill { display: inline-flex; align-items: center; gap: 6px; height: 36px; padding: 0 13px; border-radius: 999px; border: 1.5px solid #ebe7f2; font-size: 13px; font-weight: 600; color: var(--ink); }
.ss-pill.is-on { border-color: var(--violet); color: #5b4bd6; background: rgba(124,107,245,.08); }
.ss-pill .av { width: 20px; height: 20px; font-size: 9px; border: 0; }
.ss-cal { margin-top: 10px; border: 1.5px solid #ebe7f2; border-radius: 14px; padding: 10px; animation: rise .2s var(--spring); }
.ss-cal[hidden] { display: none; }
.ss-cal .top { display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px; font-weight: 700; font-size: 13.5px; }
.ss-cal .top button { width: 32px; height: 32px; border-radius: 50%; background: #f4f2f8; color: var(--ink); }
.ss-cal .grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 2px; text-align: center; }
.ss-cal .grid span { font-size: 10px; font-weight: 700; color: var(--ink-soft); padding: 4px 0; }
.ss-cal .grid button { height: 36px; border-radius: 10px; font-size: 13px; font-weight: 600; color: var(--ink); font-variant-numeric: tabular-nums; }
.ss-cal .grid button.is-today { box-shadow: inset 0 0 0 1.5px var(--violet); }
.ss-cal .grid button.is-on { background: var(--violet); color: #fff; }
.ss-cal .grid button[disabled] { opacity: .3; }
.ss-foot { flex-shrink: 0; padding: 10px 16px calc(14px + var(--sa-b, env(safe-area-inset-bottom, 0px))); border-top: 1px solid #f0edf6; }
.ss-go { width: 100%; height: 50px; border-radius: 15px; background: var(--violet); color: #fff; font-weight: 800; font-size: 15px; border: 0; box-shadow: 0 14px 26px -12px rgba(124,107,245,.95); }
.ss-go[disabled] { opacity: .6; }

/* ── Toast ────────────────────────────────────────────────────────────── */
.toast { position: fixed; z-index: 95; left: 50%; bottom: calc(96px + var(--safe-b)); transform: translate(-50%, 30px); opacity: 0; visibility: hidden;
    width: min(calc(100% - 28px), 420px); border-radius: 15px; background: #fff; color: var(--ink); padding: 10px 10px 10px 14px; display: flex; align-items: center; gap: 10px;
    font-size: 13px; font-weight: 600; box-shadow: 0 16px 30px -10px rgba(0,0,0,.6); transition: transform .3s var(--ease), opacity .3s, visibility 0s .3s; }
.toast.is-on { transform: translate(-50%, 0); opacity: 1; visibility: visible; transition: transform .4s var(--spring), opacity .2s; }
.toast.is-error { background: #fef2f2; color: #991b1b; }
.toast .msg { flex: 1; }
.toast .act { height: 32px; padding: 0 13px; border-radius: 10px; background: var(--ink); color: #fff; font-size: 12px; font-weight: 700; }
body.is-writing .toast { bottom: calc(88px + var(--safe-b)); }

/* ── Install hint ─────────────────────────────────────────────────────── */
.install { margin: 12px max(14px, var(--sa-r, env(safe-area-inset-right, 0px))) 0 max(14px, var(--sa-l, env(safe-area-inset-left, 0px))); border-radius: 15px; padding: 10px 10px 10px 12px; display: flex; align-items: center; gap: 10px;
    background: linear-gradient(150deg, #7c6bf5, #7c6bf5b0); font-size: 12.5px; font-weight: 600; line-height: 1.35; animation: rise .4s var(--spring); }
.install[hidden] { display: none; }
.install img { width: 34px; height: 34px; border-radius: 10px; flex-shrink: 0; }
.install span { flex: 1; }
.install .go { height: 32px; padding: 0 12px; border-radius: 10px; background: #fff; color: var(--ink); font-size: 12px; font-weight: 700; }
.install .go[hidden] { display: none; }
.install .x { width: 30px; height: 30px; border-radius: 50%; display: grid; place-items: center; opacity: .8; }

/* ── Type: the note's own pair (takenote/fonts) ───────────────────────── */
/* `body` in front: the note page's own rules (partials/page) come later in the document */
body .ed, body .mp .ed, body .mp .mp-title, .tile .tt, .tile .ex { font-family: var(--note); }
.head h1, .dk-title, .tile .tt, body .mp .mp-title, body .mp .ed h1, body .mp-mini, body .ink-title, .empty b, .dk-empty b { font-weight: 700; letter-spacing: -.02em; font-style: normal; }
body .mp .ed h2 { font-weight: 700; font-style: normal; letter-spacing: -.01em; }
.head h1 { font-size: 34px; }
.empty b { font-size: 22px; }

/* ── The Fonts sheet ──────────────────────────────────────────────────── */
.ss-fonts .ss-sheet { height: min(88dvh, 760px); }
.fs-body { padding-top: 12px; }
.fs-prev { border-radius: 16px; padding: 14px 16px; background: var(--paper); border: 1px solid #efe8da; color: var(--ink); }
.fs-prev b { display: block; font: 700 21px/1.15 var(--note); letter-spacing: -.02em; }
.fs-prev p { margin: 6px 0 0; font: 400 14px/1.5 var(--note); color: #3a2f5c; }
.fs-prev p[dir=rtl] { text-align: right; }
.fs-tabs { position: sticky; top: -12px; z-index: 2; margin: 12px -16px 0; padding: 8px 16px; background: #fff; display: grid; grid-template-columns: 1fr 1fr; gap: 4px; }
.fs-tabs button { height: 38px; border-radius: 12px; font-size: 13.5px; font-weight: 700; color: var(--ink-soft); background: #f4f2f8; }
.fs-tabs button.is-on { background: var(--violet); color: #fff; box-shadow: 0 10px 18px -10px rgba(124,107,245,.9); }
.fs-tools { display: flex; flex-direction: column; gap: 8px; margin-top: 6px; }
.fs-find { display: flex; align-items: center; gap: 8px; height: 40px; padding: 0 12px; border-radius: 12px; border: 1.5px solid #ebe7f2; color: var(--ink-soft); }
.fs-find[hidden] { display: none; }
.fs-find input { flex: 1; min-width: 0; border: 0; outline: 0; background: none; font: 500 14px var(--sans); color: var(--ink); }
.fs-chips { display: flex; gap: 6px; flex-wrap: wrap; }
.fs-chips:empty { display: none; }
.fs-chip { height: 30px; padding: 0 12px; border-radius: 999px; font-size: 12px; font-weight: 700; color: var(--ink-soft); background: #f4f2f8; }
.fs-chip.is-on { background: var(--ink); color: #fff; }
.fs-list { display: flex; flex-direction: column; gap: 6px; padding: 10px 0 calc(14px + var(--sa-b, env(safe-area-inset-bottom, 0px))); }
.fs-row { display: flex; align-items: center; gap: 10px; width: 100%; padding: 10px 12px; border-radius: 14px; border: 1.5px solid #ebe7f2; text-align: start; color: var(--ink); transition: border-color .15s, background .15s; }
.fs-row:hover { border-color: #d9d2ea; }
.fs-row.is-on { border-color: var(--violet); background: rgba(124,107,245,.07); }
.fs-row .tx { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px; }
.fs-row .nm { font-size: 17px; font-weight: 600; line-height: 1.25; }
.fs-row .sm { font-size: 13px; color: var(--ink-soft); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.fs-row .sm[dir=rtl] { text-align: right; }
.fs-row .gp { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: #a39bb5; }
.fs-row .ck { width: 24px; height: 24px; border-radius: 50%; display: grid; place-items: center; flex-shrink: 0; border: 1.5px solid #d9d2ea; color: transparent; font-size: 13px; font-style: normal; }
.fs-row.is-on .ck { background: var(--violet); border-color: var(--violet); color: #fff; }
.fs-none { text-align: center; color: var(--ink-soft); font-size: 13px; padding: 20px 0; }

/* ── The open card (takenote/cardback) ────────────────────────────────── */
.cb-chips { display: flex; flex-wrap: wrap; gap: 8px; margin: -2px 0 22px; position: relative; }
.cb-chip { display: inline-flex; align-items: center; gap: 6px; height: 30px; padding: 0 11px; border-radius: 15px; border: 1px solid #e4ddcf; background: #fff; color: #1d1530; font: 600 12.5px var(--sans); cursor: pointer; }
span.cb-chip { cursor: default; }
.cb-chip .av { width: 20px; height: 20px; font-size: 9px; border: 0; margin-inline-start: -6px; }
.cb-chip.cb-label { border: 0; color: #fff; border-radius: 7px; }
.cb-label.is-urgent { background: #c9372c; } .cb-label.is-high { background: #e2770f; } .cb-label.is-low { background: #4bce97; color: #164b35; }
.cb-due { border: 0; background: #ede9fe; color: #5b4bd6; }
.cb-due.is-soon { background: #fef3c7; color: #92400e; } .cb-due.is-late { background: #ffe2df; color: #ae2e24; }
.cb-add { border: 1.5px dashed #d6cfe3; background: transparent; color: #5b4bd6; font-weight: 700; }
.cb-pop { position: absolute; z-index: 30; top: 36px; inset-inline-end: 0; min-width: 220px; padding: 6px; border-radius: 14px; background: #fff; box-shadow: 0 18px 40px -16px rgba(29,21,48,.55), 0 0 0 1px rgba(29,21,48,.06); }
.cb-pop button { width: 100%; display: flex; align-items: center; gap: 10px; height: 40px; padding: 0 12px; border-radius: 10px; font: 600 13.5px var(--sans); color: #1d1530; text-align: start; }
.cb-pop button:hover { background: #f4f1ea; }
.cb-pop .bi { color: #7c6bf5; }
.cb-below { margin-top: 34px; display: flex; flex-direction: column; gap: 26px; }
.cb-sec { display: flex; flex-direction: column; gap: 12px; }
.cb-sec[hidden] { display: none; }
.note-below .cb-below { margin-top: 18px; gap: 0; }
/* a note's files: chips — an icon and a name; a tap opens it */
.nf-row { display: flex; flex-wrap: wrap; gap: 8px; }
.nf-chip { display: inline-flex; align-items: center; gap: 8px; max-width: 100%; height: 38px; padding: 0 4px 0 4px; border-radius: 11px; background: #fff; border: 1px solid #e4ddcf; color: #1d1530; font: 600 13px var(--sans); text-decoration: none; transition: border-color .15s, transform .12s; }
.nf-chip:hover { border-color: #c9bff7; }
.nf-chip:active { transform: scale(.97); }
.nf-ic { width: 30px; height: 30px; border-radius: 8px; display: grid; place-items: center; flex-shrink: 0; background: var(--x); color: #fff; font: 800 8.5px var(--sans); letter-spacing: .02em; }
.nf-ic .bi { font-size: 15px; }
.nf-thumb { width: 30px; height: 30px; border-radius: 8px; object-fit: cover; flex-shrink: 0; }
.nf-name { min-width: 0; max-width: 220px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; unicode-bidi: plaintext; }
/* a file: its document icon, then its name over its kind and size — no box around it */
.nf-ic.is-file { width: 32px; height: 36px; background: none; color: var(--x); font-size: 30px; }
.nf-ic.is-file .bi { font-size: 30px; }
.nf-words { display: flex; flex-direction: column; min-width: 0; line-height: 1.2; }
.nf-size { font: 500 11px var(--sans); color: #8a8199; margin-top: 2px; }
/* writing the note: its files stay in view, each with a pencil to rename it in place */
.mp.is-editing .mp-below.note-below { display: block; }
.nf-ren { width: 26px; height: 26px; border-radius: 50%; display: none; place-items: center; color: #8a8199; flex-shrink: 0; font-size: 13px; }
.mp.is-editing .nf-ren { display: grid; }
.nf-ren:hover { background: #f4f1ea; color: #5b4bd6; }
.nf-chip.is-renaming { border-color: #c9bff7; background: #fff; }
.nf-chip.is-renaming .nf-ren { display: none; }
.nf-in { min-width: 0; width: 220px; max-width: 52vw; height: 28px; padding: 0 8px; border: 1px solid #c9bff7; border-radius: 8px; font: 600 13px var(--sans); color: #1d1530; background: #fff; outline: 0; unicode-bidi: plaintext; }
.nf-chip:has(.nf-ic.is-file) { height: auto; min-height: 44px; padding: 4px 6px 4px 2px; background: transparent; border-color: transparent; }
.nf-chip:has(.nf-ic.is-file):hover { background: rgba(255,255,255,.7); border-color: #e4ddcf; }
/* a note's files sit at the very foot of the note, not after a half-empty page */
.mp-cover[hidden] + .mp-col:has(> .note-below) { min-height: 100%; box-sizing: border-box; display: flex; flex-direction: column; }
.mp-col:has(> .note-below) > .ed { min-height: 30vh; }
.mp-col > .note-below { margin-top: auto; padding-top: 12px; border-top: 1px solid rgba(29,21,48,.08); }
.mp-col > .note-below:not(:has(.nf-chip:not(.nf-add))):not(:has(.ln-row, .nl-row)) { border-top: 0; }
.mp .mp-cover[hidden] + .mp-col:has(> .note-below), .dk .mp.is-hosted .mp-cover[hidden] + .mp-col:has(> .note-below) { padding-bottom: 84px; }
.nf-x { width: 26px; height: 26px; border-radius: 50%; display: grid; place-items: center; color: #b3abc4; flex-shrink: 0; font-size: 16px; }
.nf-x:hover { background: #f4f1ea; color: #ae2e24; }
.nf-x.is-sure { width: auto; padding: 0 8px; border-radius: 13px; background: #ae2e24; color: #fff; font: 700 11px var(--sans); }
.nf-add { padding: 0 12px 0 10px; border-style: dashed; color: #5b4bd6; background: transparent; }
.nf-add .bi { font-size: 15px; }
.note-below .cb-lists:empty { display: none; }
.cb-sh { display: flex; align-items: center; gap: 10px; }
.cb-sh h2 { flex: 1; margin: 0; font: 800 12.5px var(--sans); letter-spacing: .08em; text-transform: uppercase; color: #8a8199; }
.cb-btn { display: inline-flex; align-items: center; gap: 6px; height: 32px; padding: 0 14px; border-radius: 16px; background: #f4f1ea; color: #5c5370; font: 600 13px var(--sans); }
.cb-btn.is-main { background: #ede9fe; color: #5b4bd6; }
.cb-btn.is-sure, .cb-icon.is-sure { background: #c9372c; color: #fff; }
.cb-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 12px; }
.cb-tile { border-radius: 14px; background: #fff; border: 1px solid #ece6d9; overflow: hidden; display: flex; flex-direction: column; }
.cb-face { height: 96px; position: relative; display: grid; place-items: center; background: #f4f1ea; overflow: hidden; }
.cb-face img { width: 100%; height: 100%; object-fit: cover; display: block; }
.cb-face .tag { position: absolute; inset-inline-start: 8px; top: 8px; height: 20px; padding: 0 8px; border-radius: 10px; background: rgba(255,255,255,.92); color: #5b4bd6; font-size: 11px; font-weight: 800; display: inline-flex; align-items: center; }
.cb-face .doc { width: 44px; height: 54px; border-radius: 6px; background: #fff; border-top: 10px solid var(--x); color: var(--x); font: 900 11px var(--sans); display: grid; place-items: center; box-shadow: 0 6px 14px -6px rgba(29,21,48,.35); }
.cb-face.is-link { background: #eef1ff; display: flex; flex-direction: column; align-items: flex-start; justify-content: center; gap: 6px; padding: 0 14px; }
.cb-face .fav, .at-host .fav { width: 26px; height: 26px; border-radius: 7px; background: #1428a0; color: #fff; font: 900 12px var(--sans); display: grid; place-items: center; }
.cb-face .host { font-size: 12px; font-weight: 700; color: #1428a0; overflow-wrap: anywhere; }
.cb-info { padding: 10px 12px 4px; display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.cb-info .cb-nm { /* not .nm: that is the mind map, 560px tall */ font: 700 13.5px var(--sans); color: #1d1530; text-decoration: none; overflow-wrap: anywhere; }
.cb-info a.cb-nm:hover { color: #5b4bd6; text-decoration: underline; }
.cb-info .mt { font-size: 12px; color: #8a8199; }
.cb-acts { display: flex; align-items: center; gap: 4px; padding: 4px 8px 10px 12px; margin-top: auto; }
.cb-mini { height: 28px; padding: 0 10px; border-radius: 14px; background: #f4f1ea; color: #5c5370; font: 600 12px var(--sans); margin-inline-end: auto; }
.cb-icon { width: 30px; height: 30px; border-radius: 50%; display: inline-grid; place-items: center; background: #f4f1ea; color: #5c5370; font-size: 13px; text-decoration: none; flex-shrink: 0; }
.cb-icon.is-sure { width: auto; padding: 0 10px; border-radius: 15px; font: 700 12px var(--sans); }
.cb-acts .cb-icon:first-child { margin-inline-start: auto; }
.cb-drop { padding: 14px; border: 2px dashed #ddd5c6; border-radius: 14px; text-align: center; font: 500 13px var(--sans); color: #8a8199; background: transparent; }
.cb-drop:hover { border-color: #c9bff7; color: #5b4bd6; }
.cb-bar { height: 6px; border-radius: 3px; background: #ece6d9; overflow: hidden; }
.cb-bar i { display: block; height: 100%; border-radius: 3px; background: #7c6bf5; transition: width .3s var(--ease); }
.cb-steps { border-radius: 14px; background: #fff; border: 1px solid #ece6d9; overflow: hidden; }
.cb-steps:empty { display: none; }
.cb-step { display: flex; align-items: center; gap: 12px; min-height: 46px; padding: 0 8px 0 16px; border-bottom: 1px solid #f2ede3; font: 400 15px/1.4 var(--note); color: #1d1530; cursor: pointer; }
.cb-step:last-child { border-bottom: 0; }
.cb-step input { width: 18px; height: 18px; accent-color: #7c6bf5; flex-shrink: 0; }
.cb-step span { flex: 1; min-width: 0; overflow-wrap: anywhere; unicode-bidi: plaintext; }
.cb-step.is-done span { text-decoration: line-through; color: #8a8199; }
.cb-x { width: 30px; height: 30px; border-radius: 50%; color: #b3abc4; display: grid; place-items: center; opacity: 0; }
.cb-step:hover .cb-x, .cb-x:focus-visible { opacity: 1; }
@media (hover: none) { .cb-x { opacity: 1; } }
.cb-additem { height: 40px; padding: 0 14px; border-radius: 12px; border: 1.5px dashed #e4ddcf; background: transparent; font: 14px var(--sans); color: #1d1530; outline: none; }
.cb-additem:focus { border-style: solid; border-color: #7c6bf5; background: #fff; }
/* comments and activity */
.cb-ahead { flex-shrink: 0; display: flex; align-items: center; gap: 8px; padding: 18px 20px 10px; }
.cb-ahead h2 { flex: 1; margin: 0; font: 800 12.5px var(--sans); letter-spacing: .08em; text-transform: uppercase; color: #8a8199; }
.cb-feed { flex: 1; min-height: 0; overflow-y: auto; padding: 4px 20px 16px; display: flex; flex-direction: column; gap: 16px; scrollbar-width: thin; }
.cb-none { color: #8a8199; font-size: 13px; margin: 6px 0; }
.cb-c { display: flex; gap: 10px; }
.cb-c > .av { width: 30px; height: 30px; font-size: 11px; border: 0; flex-shrink: 0; }
.cb-cbody { flex: 1; min-width: 0; }
.cb-who { font-size: 12.5px; color: #8a8199; } .cb-who b { color: #1d1530; }
.cb-bub { margin-top: 5px; padding: 10px 12px; border-radius: 14px; border-start-start-radius: 4px; background: #fff; border: 1px solid #ece6d9; font: 400 14px/1.5 var(--note); color: #1d1530; white-space: pre-wrap; overflow-wrap: anywhere; unicode-bidi: plaintext; }
.cb-cacts { display: inline-flex; align-items: center; gap: 0; margin-inline-start: 2px; }
.cb-cacts button { font: 600 12px var(--sans); color: #7c6bf5; padding: 2px 0; }
.cb-cacts button + button::before { content: "·"; color: #b3abc4; margin: 0 6px; font-weight: 400; } /* Edit · Delete, as Trello writes them */
.cb-ebar { display: flex; align-items: center; gap: 8px; margin-top: 6px; }
.cb-cacts button.is-sure { color: #c9372c; }
.cb-cedit { width: 100%; min-height: 70px; margin-top: 5px; padding: 10px 12px; border-radius: 12px; border: 1.5px solid #7c6bf5; font: 14px/1.5 var(--note); resize: vertical; outline: none; }
.cb-act { display: flex; align-items: center; gap: 10px; font-size: 13px; color: #5c5370; }
.cb-act b { color: #1d1530; }
.cb-aic { width: 30px; height: 30px; flex-shrink: 0; border-radius: 50%; background: #fff; border: 1px solid #e4ddcf; color: #7c6bf5; display: grid; place-items: center; font-size: 13px; }
.cb-at { color: #8a8199; }
.cb-compose { flex-shrink: 0; margin: 0 16px 16px; border-radius: 14px; background: #fff; border: 1.5px solid #e4ddcf; padding: 8px 10px; box-shadow: 0 8px 18px -14px rgba(29,21,48,.5); }
.cb-compose:focus-within { border-color: #c9bff7; }
.cb-compose textarea { display: block; width: 100%; min-height: 40px; max-height: 160px; border: 0; outline: 0; resize: none; background: none; font: 14px/1.5 var(--note); color: #1d1530; padding: 4px 2px; unicode-bidi: plaintext; }
.cb-cbar { display: flex; align-items: center; gap: 4px; }
.cb-flex { flex: 1; }
.cb-send { display: inline-flex; align-items: center; gap: 6px; height: 32px; padding: 0 14px; border-radius: 16px; background: #7c6bf5; color: #fff; font: 700 13px var(--sans); }
.cb-send[disabled] { opacity: .6; }
/* on a phone the comments follow the page, and their box rides at the foot of the screen */
.mp-col > .cb-aside .cb-ahead { padding: 0 0 10px; }
.mp-col > .cb-aside .cb-feed { padding: 0 0 20px; overflow: visible; }
.mp-foot .cb-compose { margin: 0; width: min(100%, 560px); }
.mp.is-window.is-dropping .mp-scroll, .mp.is-dropping .mp-scroll { outline: 3px dashed #7c6bf5; outline-offset: -10px; background: rgba(124,107,245,.05); }
/* the Attach sheet */
.ss-attach .ss-sheet { max-height: 88dvh; }
.at-body { padding-top: 12px; display: flex; flex-direction: column; gap: 14px; }
.at-tabs { position: static; margin: 0; padding: 0; }
.at-pane { display: flex; flex-direction: column; gap: 12px; padding-bottom: calc(14px + var(--sa-b, env(safe-area-inset-bottom, 0px))); }
.at-pane[hidden] { display: none; }
.at-f { display: flex; flex-direction: column; gap: 6px; }
.at-f span { font: 700 12px var(--sans); color: #5c5370; }
.at-f input { height: 44px; padding: 0 12px; border-radius: 12px; border: 1.5px solid #e4ddcf; font: 15px var(--sans); color: #1d1530; outline: none; }
.at-f input:focus { border-color: #7c6bf5; }
.at-host { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 12px; background: #eef1ff; color: #1428a0; font: 700 13px var(--sans); }
.at-host[hidden] { display: none; }
.at-zone { display: flex; flex-direction: column; align-items: center; gap: 8px; padding: 24px 14px; border-radius: 16px; border: 2px dashed #c9bff7; background: #f8f6ff; color: #5b4bd6; text-align: center; font-family: var(--sans); }
.at-zone.is-over { background: #ede9fe; border-color: #7c6bf5; }
.at-zone .zi { width: 48px; height: 48px; border-radius: 16px; background: #ede9fe; display: grid; place-items: center; font-size: 21px; }
.at-zone b { font-size: 14px; }
.at-zone span { font-size: 12px; color: #8a8199; }
.at-queue { display: flex; flex-direction: column; gap: 8px; }
.at-item { display: flex; align-items: center; gap: 12px; padding: 10px 12px; border-radius: 12px; border: 1px solid #ece6d9; }
.at-item .doc { width: 34px; height: 42px; border-radius: 5px; background: #fff; border-top: 8px solid var(--x); color: var(--x); font: 900 9px var(--sans); display: grid; place-items: center; flex-shrink: 0; box-shadow: 0 4px 10px -4px rgba(29,21,48,.3); }
.at-info { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 5px; }
.at-info b { font: 700 13px var(--sans); color: #1d1530; overflow-wrap: anywhere; }
.at-track { height: 5px; border-radius: 3px; background: #ece6d9; overflow: hidden; }
.at-track i { display: block; height: 100%; width: 0; background: #7c6bf5; transition: width .2s; }
.at-info .mt { font-size: 11.5px; color: #8a8199; }
.at-item.is-ok .at-track i { background: #16a394; width: 100% !important; }
.at-item.is-bad { border-color: #f2b8b2; background: #fff6f5; }
.at-item.is-bad .at-track { display: none; }
.at-item.is-bad .mt { color: #ae2e24; }
.cb-list { display: inline-flex; align-items: center; gap: 7px; height: 32px; padding: 0 12px 0 10px; border-radius: 16px; border: 1px solid #e4ddcf; background: #fff; color: #1d1530; font: 600 13px var(--sans); white-space: nowrap; }
.cb-list i { width: 9px; height: 9px; border-radius: 50%; }
.cb-list .bi { font-size: 11px; opacity: .6; }
/* Every surface says DARK, the paper too. A browser's own "dark mode for websites" (Chrome, Samsung Internet)
   re-colours whatever says it is light — that turned the note paper a muddy yellow (owner, 28 Oct 2026). So nothing
   asks for light: the paper's fields, ticks and scrollbars are painted light by hand instead. */
:root, .mp, .ss-sheet, .cb-pop { color-scheme: dark; }
:where(.mp, .ss-sheet, .cb-pop, .ct-box, .at-pop) { caret-color: #1d1530; scrollbar-color: rgba(29,21,48,.28) transparent; }
:where(.mp, .ss-sheet, .cb-pop, .ct-box, .at-pop) :where(input:not([type=checkbox]):not([type=radio]):not([type=range]):not([type=color]):not([type=file]), textarea, select) { background-color: #fff; color: #1d1530; }
:where(.mp, .ss-sheet, .cb-pop, .ct-box, .at-pop) :where(input, textarea)::placeholder { color: rgba(29,21,48,.4); }
:where(.mp, .ss-sheet, .cb-pop, .ct-box, .at-pop) input[type=checkbox] { -webkit-appearance: none; appearance: none; width: 18px; height: 18px; margin: 0; flex-shrink: 0; border-radius: 5px;
    border: 1.6px solid #c9c0d9; background: #fff; display: inline-grid; place-content: center; cursor: pointer; vertical-align: middle; }
:where(.mp, .ss-sheet, .cb-pop, .ct-box, .at-pop) input[type=checkbox]::before { content: ''; width: 10px; height: 10px; background: #fff; transform: scale(0); transition: transform .12s;
    clip-path: polygon(14% 44%, 0 65%, 50% 100%, 100% 16%, 80% 0, 43% 62%); }
:where(.mp, .ss-sheet, .cb-pop, .ct-box, .at-pop) input[type=checkbox]:checked { background: #7c6bf5; border-color: #7c6bf5; }
:where(.mp, .ss-sheet, .cb-pop, .ct-box, .at-pop) input[type=checkbox]:checked::before { transform: scale(1); }
:where(.mp, .ss-sheet, .cb-pop, .ct-box, .at-pop) input[type=checkbox]:focus-visible { outline: 2px solid #7c6bf5; outline-offset: 2px; }
:where(.mp, .ss-sheet, .cb-pop, .ct-box, .at-pop) input[type=range] { accent-color: #7c6bf5; }
.at-f input, .cb-additem, .cb-cedit, .fs-find input { background: #fff; color: #1d1530; }

/* ── Notes · Map · Board ──────────────────────────────────────────────── */
.seg { grid-template-columns: repeat(3, 1fr); }

/* ── Folders: chips on a phone ────────────────────────────────────────── */
.folders { display: flex; align-items: center; gap: 6px; padding: 0 max(14px, var(--sa-r, env(safe-area-inset-right, 0px))) 12px max(14px, var(--sa-l, env(safe-area-inset-left, 0px))); overflow-x: auto; scrollbar-width: none; }
.folders::-webkit-scrollbar { display: none; }
.folders[hidden] { display: none; }
.fo-chip { flex: 0 0 auto; display: inline-flex; align-items: center; gap: 7px; height: 34px; padding: 0 12px; border-radius: 11px; background: rgba(255,255,255,.07); border: 1px solid rgba(255,255,255,.1);
    color: rgba(255,255,255,.82); font: 600 12.5px var(--sans); transition: background .2s, transform .2s var(--spring), box-shadow .2s; }
.fo-chip.is-on { background: var(--paper); color: var(--ink); border-color: transparent; }
.fo-chip .n { opacity: .55; font-variant-numeric: tabular-nums; }
.fo-chip .bi { font-size: 12px; opacity: .7; }
.fo-dot { width: 10px; height: 10px; border-radius: 3px; flex-shrink: 0; }
.fo-sep { color: rgba(255,255,255,.35); font-weight: 700; flex: 0 0 auto; }
.fo-new { border-style: dashed; border-color: rgba(167,139,250,.5); color: #c4b8ff; background: transparent; }
body.is-filing .folders [data-folder], body.is-filing .fo-tree [data-folder] { box-shadow: 0 0 0 2px rgba(167,139,250,.35); }
.folders [data-folder].is-drop, .fo-tree [data-folder].is-drop { background: #7c6bf5 !important; color: #fff !important; transform: scale(1.06); box-shadow: 0 10px 24px -10px rgba(124,107,245,.95) !important; }
[data-folder] > .tile { display: none; }

/* ── Folders: the tree on a desk ──────────────────────────────────────── */
.dk .folders { display: block; padding: 4px 10px 6px; max-height: 38vh; overflow-y: auto; scrollbar-width: thin; }
.fo-head { display: flex; align-items: center; justify-content: space-between; padding: 6px 6px 4px; font: 800 10.5px var(--sans); letter-spacing: .1em; text-transform: uppercase; color: rgba(255,255,255,.45); }
.fo-plus { width: 26px; height: 26px; border-radius: 8px; display: grid; place-items: center; color: rgba(255,255,255,.65); }
.fo-plus:hover { background: rgba(255,255,255,.1); color: #fff; }
.fo-row { position: relative; display: flex; align-items: center; gap: 6px; height: 34px; padding-inline: calc(var(--d) * 14px) 6px; border-radius: 9px; cursor: pointer; color: rgba(255,255,255,.82); font: 600 13px var(--sans);
    transition: background .15s, transform .15s; }
.fo-row:hover { background: rgba(255,255,255,.06); }
.fo-row.is-on { background: rgba(124,107,245,.28); color: #fff; }
.fo-tw { width: 18px; height: 18px; display: grid; place-items: center; font-size: 10px; color: rgba(255,255,255,.5); flex-shrink: 0; }
.fo-ic { width: 18px; display: grid; place-items: center; font-size: 14px; flex-shrink: 0; color: rgba(255,255,255,.6); }
.fo-name { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; unicode-bidi: plaintext; text-align: start; }
.fo-row .n { font-size: 11px; opacity: .5; font-variant-numeric: tabular-nums; }
.fo-more { width: 24px; height: 24px; border-radius: 7px; display: grid; place-items: center; color: rgba(255,255,255,.6); opacity: 0; }
.fo-row:hover .fo-more, .fo-row.is-on .fo-more { opacity: 1; }
.fo-more:hover { background: rgba(255,255,255,.12); }

/* ── Folder sheets ────────────────────────────────────────────────────── */
.fo-body { padding-top: 12px; display: flex; flex-direction: column; gap: 10px; padding-bottom: 16px; }
.fo-body h4 { margin: 6px 2px 0; }
.fo-in { height: 46px; padding: 0 14px; border-radius: 12px; border: 1.5px solid #e4ddcf; font: 600 16px var(--sans); color: #1d1530; background: #fff; outline: none; }
.fo-in:focus { border-color: #7c6bf5; }
.fo-colors { display: flex; flex-wrap: wrap; gap: 10px; padding: 4px 2px; }
.fo-sw { width: 32px; height: 32px; border-radius: 10px; background: var(--c); box-shadow: 0 0 0 2px #fff, 0 0 0 3.5px rgba(29,21,48,.12); transition: transform .15s var(--spring); }
.fo-sw.is-on { box-shadow: 0 0 0 2px #fff, 0 0 0 4.5px var(--c); transform: scale(1.08); }
.ss-pill .fo-dot { width: 9px; height: 9px; }
.fo-line { display: flex; align-items: center; gap: 10px; min-height: 46px; padding: 0 14px; border-radius: 13px; border: 1.5px solid #ebe7f2; font: 700 13.5px var(--sans); color: #1d1530; text-align: start; }
.fo-line .bi { color: #7c6bf5; }
.fo-line.is-danger, .fo-line.is-danger .bi { color: #c9372c; }
.fo-line.is-sure { background: #c9372c; color: #fff; border-color: #c9372c; }
.fo-line-n { margin-inline-start: auto; min-width: 22px; height: 22px; padding: 0 7px; border-radius: 11px; background: #ede9fe; color: #5b4bd6; font: 800 11.5px/22px var(--sans); text-align: center; }
.fo-shared { font-size: 11px; color: rgba(255,255,255,.5); flex-shrink: 0; }

/* inside a folder somebody shared: whose it is, and the way back */
.sp-bar { --sc: #7c6bf5; margin: 4px 2px 10px; padding: 10px; border-radius: 14px; display: grid; grid-template-columns: auto 1fr; gap: 4px 10px; align-items: center;
    background: linear-gradient(150deg, color-mix(in srgb, var(--sc) 55%, transparent), color-mix(in srgb, var(--sc) 22%, transparent)); border: 1px solid color-mix(in srgb, var(--sc) 60%, transparent); }
.sp-ic { width: 34px; height: 34px; border-radius: 10px; display: grid; place-items: center; background: rgba(255,255,255,.18); color: #fff; font-size: 16px; }
.sp-tx { min-width: 0; display: flex; flex-direction: column; line-height: 1.25; }
.sp-tx small { font: 800 9.5px var(--sans); letter-spacing: .09em; text-transform: uppercase; color: rgba(255,255,255,.7); }
.sp-tx b { font: 700 14px var(--sans); color: #fff; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; unicode-bidi: plaintext; text-align: start; }
.sp-tx span { font-size: 11.5px; color: rgba(255,255,255,.72); }
.sp-back { grid-column: 1 / -1; height: 32px; border-radius: 9px; display: inline-flex; align-items: center; justify-content: center; gap: 7px; background: rgba(255,255,255,.14); color: #fff; font: 700 12.5px var(--sans); border: 0; }
.sp-back:hover { background: rgba(255,255,255,.22); }
[dir=rtl] .sp-back .bi { transform: scaleX(-1); }

/* the folders shared with me */
.sp-shared { padding: 2px 0 6px; }
.sp-row { width: 100%; display: flex; align-items: center; gap: 10px; min-height: 40px; padding: 4px 10px; border-radius: 9px; color: rgba(255,255,255,.85); text-align: start; background: none; border: 0; }
.sp-row:hover { background: rgba(255,255,255,.06); }
.sp-row.is-on { background: rgba(124,107,245,.24); }
.sp-row > .bi { font-size: 15px; width: 18px; text-align: center; flex-shrink: 0; }
.sp-w { min-width: 0; display: flex; flex-direction: column; line-height: 1.2; }
.sp-w b { font: 600 13px var(--sans); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; unicode-bidi: plaintext; text-align: start; }
.sp-w small { font-size: 11px; color: rgba(255,255,255,.5); }

/* storage: a donut gauge of the notebook's space */
.st-box { --st: #8b7cf6; margin: 6px 2px 8px; padding: 10px 12px; border-radius: 14px; display: flex; align-items: center; gap: 12px; background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.08); color: #fff; }
.st-box.is-high { --st: #f59e0b; }
.st-box.is-full { --st: #f43f5e; border-color: rgba(244,63,94,.4); }
.st-dial { position: relative; width: 46px; height: 46px; flex-shrink: 0; display: grid; place-items: center; color: #fff; }
.st-dial svg { position: absolute; inset: 0; }
.st-dial .st-arc { stroke: var(--st); transition: stroke-dasharray .6s var(--ease); }
.st-dial em { position: relative; font: 800 10.5px var(--sans); font-style: normal; color: #fff; }
.st-w { min-width: 0; display: flex; flex-direction: column; line-height: 1.3; }
.st-w b { font: 800 10.5px var(--sans); letter-spacing: .09em; text-transform: uppercase; color: rgba(255,255,255,.55); }
.st-w span { font: 700 13px var(--sans); white-space: nowrap; }
.st-w small { font-size: 11.5px; color: var(--st); font-weight: 600; }
.fo-line.is-sure .bi { color: #fff; }
.fo-pick { display: flex; flex-direction: column; gap: 6px; }
.fo-opt { display: flex; align-items: center; gap: 10px; min-height: 46px; padding: 0 14px; padding-inline-start: calc(14px + var(--d, 0) * 18px); border-radius: 13px; border: 1.5px solid #ebe7f2; font: 600 14px var(--sans); color: #1d1530; text-align: start; }
.fo-opt.is-on { border-color: var(--violet); background: rgba(124,107,245,.07); }

/* ── The note map ─────────────────────────────────────────────────────── */
.map[hidden] { display: none; }
.map { padding: 0 max(12px, var(--sa-r, env(safe-area-inset-right, 0px))) 0 max(12px, var(--sa-l, env(safe-area-inset-left, 0px))); }
.nm { position: relative; height: var(--map-h, 560px); border-radius: 20px; overflow: hidden; touch-action: none;
    background: radial-gradient(600px 400px at 70% 20%, rgba(124,107,245,.22), transparent 60%), radial-gradient(500px 400px at 10% 90%, rgba(224,69,123,.12), transparent 60%),
        radial-gradient(rgba(255,255,255,.06) 1px, transparent 1.2px) 0 0 / 22px 22px, #120c20;
    box-shadow: inset 0 0 0 1px rgba(255,255,255,.06); }
.nm-canvas { display: block; cursor: grab; touch-action: none; }
.nm-canvas:active { cursor: grabbing; }
.nm-bar { position: absolute; left: 10px; right: 10px; top: 10px; display: flex; align-items: center; justify-content: space-between; gap: 8px; pointer-events: none; }
.nm-bar > * { pointer-events: auto; }
.nm-modes { display: flex; gap: 2px; padding: 3px; border-radius: 12px; background: rgba(22,15,36,.82); border: 1px solid rgba(255,255,255,.1); -webkit-backdrop-filter: blur(8px); backdrop-filter: blur(8px); }
.nm-modes button { height: 30px; padding: 0 12px; border-radius: 9px; font: 700 12px var(--sans); color: rgba(255,255,255,.65); }
.nm-modes button.is-on { background: var(--paper); color: var(--ink); }
.nm-zoom { display: flex; gap: 4px; }
.nm-zoom button { width: 36px; height: 36px; border-radius: 11px; display: grid; place-items: center; background: rgba(22,15,36,.82); border: 1px solid rgba(255,255,255,.1); color: #fff; font-size: 14px; -webkit-backdrop-filter: blur(8px); backdrop-filter: blur(8px); }
.nm-legend { position: absolute; left: 10px; bottom: 10px; display: flex; flex-wrap: wrap; gap: 4px 10px; padding: 7px 11px; border-radius: 12px; background: rgba(22,15,36,.78); border: 1px solid rgba(255,255,255,.08); font: 600 11px var(--sans); color: rgba(255,255,255,.7); max-width: calc(100% - 20px); pointer-events: none; }
.nm-legend span { display: inline-flex; align-items: center; gap: 5px; }
.nm-legend i { width: 8px; height: 8px; border-radius: 50%; }
.nm-legend .is-link i { width: 14px; height: 3px; border-radius: 2px; background: #a78bfa; box-shadow: 0 0 6px #a78bfa; }
.nm-tip { position: absolute; z-index: 3; max-width: 250px; padding: 8px 11px; border-radius: 12px; background: #fbf7ef; color: #1d1530; box-shadow: 0 14px 30px -12px rgba(0,0,0,.7); pointer-events: none; }
.nm-tip[hidden] { display: none; }
.nm-tip b { display: block; font: 700 13.5px/1.3 var(--note); overflow-wrap: anywhere; }
.nm-tip span { display: block; margin-top: 3px; font-size: 12px; color: #6b6280; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
.nm-empty { position: absolute; inset: 0; display: grid; place-items: center; font: italic 600 18px var(--note); color: rgba(255,255,255,.6); pointer-events: none; }
.nm-empty[hidden] { display: none; }
.nl-box { margin-top: 34px; display: flex; flex-direction: column; gap: 10px; }
.nl-box:empty { display: none; }
.nl-box h4 { margin: 8px 0 0; font: 800 12px var(--sans); letter-spacing: .08em; text-transform: uppercase; color: #8a8199; }
.nl-row { display: flex; flex-wrap: wrap; gap: 8px; }
.nl-pill { display: inline-flex; align-items: center; gap: 8px; min-height: 36px; padding: 6px 14px; border-radius: 12px; border: 1px solid #e4ddcf; background: #fff; color: #1d1530; font: 600 13.5px var(--note); text-align: start;
    transition: border-color .15s, transform .15s var(--spring); }
.nl-pill:hover { border-color: #c9bff7; transform: translateY(-1px); }
.nl-pill i { width: 9px; height: 9px; border-radius: 3px; flex-shrink: 0; }
.nl-pill .bi { font-size: 12px; color: #8a8199; }
.mp .ed a[href*="/me/takenote?note="] { color: #5b4bd6; text-decoration: none; background: #ede9fe; border-radius: 6px; padding: 0 5px; font-weight: 600; }
.tile .ln .bi { color: var(--lc); font-size: 10px; }
.tile .ln:has(.bi)::before { display: none; }
body.is-filing { -webkit-user-select: none; user-select: none; }
.nm-focus { position: absolute; left: 10px; top: 56px; display: flex; flex-wrap: wrap; align-items: center; gap: 8px; max-width: calc(100% - 20px); pointer-events: none; }
.nm-focus > * { pointer-events: auto; }
.nm-hint { font: 600 12px var(--sans); color: rgba(255,255,255,.55); padding: 6px 10px; border-radius: 10px; background: rgba(22,15,36,.6); pointer-events: none !important; }
.nm-centre { display: inline-flex; align-items: center; gap: 8px; height: 36px; padding: 0 6px 0 12px; border-radius: 12px; background: var(--paper); color: var(--ink); font: 700 13px var(--note); max-width: min(420px, 100%); box-shadow: 0 10px 24px -12px #000; }
.nm-centre .bi-bullseye { color: #7c6bf5; }
.nm-centre i { width: 9px; height: 9px; border-radius: 50%; flex-shrink: 0; }
.nm-centre b { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.nm-centre button { width: 26px; height: 26px; border-radius: 8px; display: grid; place-items: center; color: #6b6280; font-size: 11px; flex-shrink: 0; }
.nm-centre button:hover { background: #ede9fe; color: #5b4bd6; }
.nm-depth { display: inline-flex; align-items: center; gap: 2px; padding: 3px; border-radius: 12px; background: rgba(22,15,36,.82); border: 1px solid rgba(255,255,255,.1); -webkit-backdrop-filter: blur(8px); backdrop-filter: blur(8px); }
.nm-depth span { padding: 0 8px 0 6px; font: 700 11px var(--sans); color: rgba(255,255,255,.55); }
.nm-depth button { min-width: 30px; height: 28px; padding: 0 8px; border-radius: 9px; font: 700 12px var(--sans); color: rgba(255,255,255,.7); }
.nm-depth button.is-on { background: #7c6bf5; color: #fff; }
.nm-card { position: absolute; right: 10px; bottom: 10px; z-index: 2; width: min(300px, calc(100% - 20px)); padding: 12px 12px 12px 14px; border-radius: 16px; background: #fbf7ef; color: #1d1530;
    box-shadow: 0 20px 40px -16px rgba(0,0,0,.8); animation: rise .3s var(--spring); }
.nm-card[hidden] { display: none; }
.nm-card-h { display: flex; align-items: center; gap: 8px; }
.nm-card-h i { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
.nm-card-h b { flex: 1; min-width: 0; font: 700 15px/1.25 var(--note); overflow-wrap: anywhere; }
.nm-x { width: 28px; height: 28px; border-radius: 8px; display: grid; place-items: center; color: #8a8199; font-size: 11px; flex-shrink: 0; }
.nm-card p { margin: 6px 0 0; font: 400 13px/1.45 var(--note); color: #5c5370; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
.nm-card p:empty { display: none; }
.nm-card-l { display: block; margin-top: 6px; font: 700 11.5px var(--sans); color: #7c6bf5; }
.nm-card-a { display: flex; gap: 6px; margin-top: 10px; }
.nm-card-a button { display: inline-flex; align-items: center; gap: 7px; height: 34px; padding: 0 14px; border-radius: 11px; font: 700 13px var(--sans); background: #f4f1ea; color: #1d1530; }
.nm-card-a button.is-main { background: #7c6bf5; color: #fff; }
@media (max-width: 559px) { .nm-card { left: 10px; right: 10px; width: auto; bottom: 10px; } .nm-legend { display: none; } }
/* named connections (takenote/relations): on the map — green lines with their names — and under a note */
.nm-legend .is-rel i { width: 14px; height: 3px; border-radius: 2px; background: #34d399; box-shadow: 0 0 6px #34d399; }
.nm.is-connecting .nm-canvas { cursor: crosshair; }
.nm.is-connecting .nm-legend, .nm.is-connecting .nm-focus { opacity: 0; pointer-events: none; transition: opacity .2s; }
.nm-connect { position: absolute; z-index: 3; left: 50%; bottom: 14px; transform: translateX(-50%); display: flex; align-items: center; gap: 10px; width: max-content; max-width: calc(100% - 20px);
    padding: 7px 7px 7px 8px; border-radius: 16px; background: #0f2a22; border: 1px solid rgba(110,231,183,.45); color: #ecfdf5;
    box-shadow: 0 18px 36px -14px rgba(0,0,0,.85), 0 0 0 4px rgba(52,211,153,.12); animation: nm-up .3s var(--spring); }
@keyframes nm-up { from { opacity: 0; transform: translate(-50%, 10px); } to { opacity: 1; transform: translateX(-50%); } }
.nm-connect[hidden] { display: none; }
.nm-connect-ic { width: 32px; height: 32px; border-radius: 10px; display: grid; place-items: center; flex-shrink: 0; background: #10b981; color: #fff; font-size: 15px; animation: nm-beat 1.6s ease-in-out infinite; }
.nm-connect-t { min-width: 0; font: 700 13px/1.3 var(--sans); overflow-wrap: anywhere; }
.nm-connect button { flex-shrink: 0; height: 32px; padding: 0 14px; border-radius: 10px; background: rgba(255,255,255,.12); color: #fff; font: 700 12.5px var(--sans); }
.nm-connect button:hover { background: rgba(255,255,255,.2); }
@keyframes nm-beat { 0%, 100% { box-shadow: 0 0 0 0 rgba(52,211,153,.55); } 50% { box-shadow: 0 0 0 7px rgba(52,211,153,0); } }
.nm-card-a { flex-wrap: wrap; }
.nm-card-a button.is-rel { background: #d1fae5; color: #065f46; }
.nm-card-a button.is-rel:hover { background: #a7f3d0; }
.nm-rels { display: flex; flex-direction: column; gap: 4px; margin-top: 8px; }
.nm-rel { display: flex; align-items: center; gap: 7px; min-height: 32px; padding: 4px 9px; border-radius: 10px; background: #fff; border: 1px solid #ece6da; color: #1d1530; font: 600 12.5px var(--note); text-align: start; }
.nm-rel:hover { border-color: #6ee7b7; }
.nm-rel .bi { color: #10b981; font-size: 12px; flex-shrink: 0; }
.nm-rel em, .rl-pill em { flex-shrink: 0; max-width: 45%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font: 700 10.5px var(--sans); font-style: normal; color: #065f46; background: #d1fae5; padding: 2px 7px; border-radius: 6px; }
.nm-rel span { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.nm-rels small { font: 700 11px var(--sans); color: #8a8199; padding-inline-start: 4px; }
[dir=rtl] .nm-rel .bi-arrow-right, [dir=rtl] .nm-rel .bi-arrow-left, [dir=rtl] .rl-pill .rl-way { transform: scaleX(-1); }
.rl-body { padding-top: 14px; display: flex; flex-direction: column; gap: 14px; padding-bottom: 16px; }
.rl-pair { position: relative; display: flex; flex-direction: column; align-items: stretch; gap: 4px; padding-inline-end: 52px; }
.rl-end { display: block; padding: 12px 14px; border-radius: 14px; background: #fff; border: 1.5px solid #e4ddcf; font: 700 15px/1.3 var(--note); color: #1d1530; overflow-wrap: anywhere; }
.rl-end.is-door { cursor: pointer; transition: border-color .15s, transform .15s var(--spring); }
.rl-end.is-door:hover { border-color: #34d399; transform: translateY(-1px); }
.rl-end .bi { font-size: 11px; color: #9ca3af; margin-inline-start: 8px; }
.rl-arrow { align-self: center; width: 28px; height: 28px; border-radius: 50%; display: grid; place-items: center; background: #10b981; color: #fff; font-size: 13px; box-shadow: 0 6px 14px -6px rgba(16,185,129,.9); }
.rl-swap { position: absolute; inset-inline-end: 0; top: 50%; transform: translateY(-50%); width: 42px; height: 42px; border-radius: 13px; display: grid; place-items: center; background: #ecfdf5; color: #047857; border: 1.5px solid #a7f3d0; font-size: 16px; transition: transform .25s var(--spring), background .15s; }
.rl-swap:hover { background: #d1fae5; }
.rl-swap:active { transform: translateY(-50%) rotate(180deg); }
.rl-name { display: flex; flex-direction: column; gap: 8px; }
.rl-lab { font: 800 10.5px var(--sans); text-transform: uppercase; letter-spacing: .08em; color: var(--ink-soft, #6b6280); margin: 2px 2px 0; }
.rl-name .fo-in:focus { border-color: #10b981; }
.rl-chips { display: flex; flex-wrap: wrap; gap: 6px; }
.rl-chips button { height: 32px; padding: 0 12px; border-radius: 10px; border: 1.5px solid #ebe7f2; background: #fff; color: #3f3651; font: 600 13px var(--sans); transition: background .15s, border-color .15s, color .15s; }
.rl-chips button:hover { border-color: #a7f3d0; }
.rl-chips button.is-on { background: #10b981; border-color: #10b981; color: #fff; }
.ss-go.rl-go { background: #10b981; box-shadow: 0 14px 26px -12px rgba(16,185,129,.95); }
.rl-find { width: 100%; }
.rl-list { max-height: min(52vh, 460px); overflow-y: auto; }
.rl-opt { gap: 12px; }
.rl-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
.rl-w { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 1px; padding: 6px 0; }
.rl-w b { font: 700 14px/1.3 var(--note); overflow-wrap: anywhere; }
.rl-w small { display: flex; align-items: center; gap: 5px; font: 600 11.5px var(--sans); color: #8a8199; }
.rl-w small .bi { font-size: 10px; }
.rl-tag { flex-shrink: 0; max-width: 40%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font: 700 10.5px var(--sans); color: #065f46; background: #d1fae5; padding: 3px 8px; border-radius: 7px; }
.rl-rm { justify-content: flex-start; }
.nl-pill.rl-pill { padding: 0; gap: 0; overflow: hidden; }
.nl-pill.rl-pill:hover { border-color: #6ee7b7; }
.rl-go-note { display: inline-flex; align-items: center; gap: 8px; min-height: 36px; padding: 6px 4px 6px 12px; color: inherit; font: inherit; text-align: start; min-width: 0; }
.rl-go-note span { min-width: 0; overflow-wrap: anywhere; }
.nl-pill .bi.rl-way, .nl-pill.rl-add .bi { width: auto; height: auto; border-radius: 0; color: #10b981; }
.rl-ed { align-self: stretch; width: 34px; display: grid; place-items: center; color: #8a8199; border-inline-start: 1px solid #f0ebe0; }
.nl-pill .rl-ed .bi { width: auto; height: auto; border-radius: 0; font-size: 13px; }
.rl-ed:hover { background: #ecfdf5; color: #047857; }
.nl-pill.rl-add { border-style: dashed; border-color: #a7f3d0; color: #047857; font-family: var(--sans); font-weight: 700; font-size: 13px; background: #f6fffb; }
.nl-pill.rl-add:hover { border-color: #34d399; }

/* ── Trello's card tools (takenote/cardtools) ─────────────────────────────── */
/* the popover: under what opened it on a desk, a sheet with its band on a phone */
.ct { position: fixed; inset: 0; z-index: 130; }
.ct-dim { position: absolute; inset: 0; }
.ct-box { position: absolute; width: 304px; max-width: calc(100vw - 16px); max-height: calc(100dvh - 16px); display: flex; flex-direction: column; border-radius: 12px; background: #fff; color: #172b4d; color-scheme: light;
    box-shadow: 0 8px 12px rgba(9,30,66,.15), 0 0 1px rgba(9,30,66,.31); animation: ct-in .14s cubic-bezier(.2,.8,.2,1); font: 14px/1.4 var(--sans); }
@keyframes ct-in { from { opacity: 0; transform: translateY(-4px); } }
.ct-ph { flex-shrink: 0; padding: 4px 8px 0; }
.ct-pr { display: flex; align-items: center; gap: 4px; min-height: 40px; }
.ct-pr h3 { flex: 1; min-width: 0; margin: 0; text-align: center; font: 600 14px/1.3 var(--sans); color: #44546f; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.ct-hb { width: 32px; height: 32px; border-radius: 8px; display: grid; place-items: center; color: #44546f; flex-shrink: 0; font-size: 14px; }
.ct-hb:hover { background: rgba(9,30,66,.08); }
.ct-hb[hidden] { visibility: hidden; display: grid; }
.ct-lrow .ct-hb { color: #626f86; }
.ct-pb { flex: 1 1 auto; overflow-y: auto; overscroll-behavior: contain; padding: 4px 12px 12px; }
.ct.is-sheet .ct-dim { background: rgba(10,6,20,.55); animation: ss-fade .2s; }
.ct.is-sheet .ct-box { left: 0 !important; right: 0; bottom: 0; top: auto !important; width: 100%; max-width: none; max-height: 88dvh; border-radius: 22px 22px 0 0; animation: ct-up .28s cubic-bezier(.2,.9,.25,1); }
@keyframes ct-up { from { transform: translateY(100%); } }
.ct.is-sheet .ct-ph { position: relative; overflow: hidden; padding: 10px 12px 12px; border-radius: 22px 22px 0 0; background: linear-gradient(150deg, #7c6bf5, #7c6bf5b0); color: #fff; }
.ct.is-sheet .ct-ph::before { content: ''; position: absolute; right: -32px; top: -40px; width: 144px; height: 144px; border-radius: 50%; background: rgba(255,255,255,.1); }
.ct.is-sheet .ct-ph .hd { position: relative; width: 40px; height: 4px; border-radius: 4px; background: rgba(255,255,255,.4); margin: 0 auto 8px; }
.ct.is-sheet .ct-pr { position: relative; }
.ct.is-sheet .ct-pr h3 { color: #fff; font-size: 16px; font-weight: 800; }
.ct.is-sheet .ct-ph .ct-hb { width: 36px; height: 36px; border-radius: 50%; color: #fff; background: rgba(255,255,255,.15); border: 1px solid rgba(255,255,255,.25); }
.ct.is-sheet .ct-pb { padding: 12px 16px calc(16px + env(safe-area-inset-bottom, 0px)); }
.ct-col { display: flex; flex-direction: column; gap: 8px; }
.ct-list { display: flex; flex-direction: column; gap: 4px; }
.ct-h { margin: 8px 0 0; font: 600 12px var(--sans); color: #44546f; }
.ct-hint { margin: 0; font-size: 12px; color: #626f86; line-height: 1.45; }
.ct-search { display: flex; align-items: center; gap: 8px; height: 36px; padding: 0 10px; border-radius: 6px; box-shadow: inset 0 0 0 1px #8590a2; color: #626f86; font-size: 13px; }
.ct-search:focus-within { box-shadow: inset 0 0 0 2px var(--violet); }
.ct-search input, .ct-in, .ct-f input, .ct-f textarea, .ct-time { flex: 1; min-width: 0; border: 0; outline: 0; background: transparent; font: 14px var(--sans); color: #172b4d; }
.ct-in, .ct-f input, .ct-f textarea { width: 100%; padding: 8px 10px; border-radius: 6px; box-shadow: inset 0 0 0 1px #8590a2; background: #fff; }
.ct-in:focus, .ct-f input:focus, .ct-f textarea:focus { box-shadow: inset 0 0 0 2px var(--violet); }
.ct-f { display: flex; flex-direction: column; gap: 4px; }
.ct-f span { font: 600 12px var(--sans); color: #44546f; }
.ct-f textarea { resize: none; line-height: 1.4; }
.ct-btn { height: 36px; padding: 0 12px; border-radius: 6px; background: rgba(9,30,66,.06); color: #172b4d; font: 500 14px var(--sans); display: inline-flex; align-items: center; justify-content: center; gap: 6px; }
.ct-btn:hover { background: rgba(9,30,66,.12); }
.ct-btn.is-main { background: var(--violet); color: #fff; }
.ct-btn.is-main:hover { filter: brightness(1.08); }
.ct-btn.is-danger { background: #c9372c; color: #fff; }
.ct-btn.is-sure { background: #ae2e24; color: #fff; }
.ct-btn[disabled] { opacity: .5; }
.ct-btn.is-wide { width: 100%; }
/* a checkbox, drawn: rounded square, violet when on */
.ct-check { width: 16px; height: 16px; border-radius: 3px; box-shadow: inset 0 0 0 1.5px #8590a2; display: grid; place-items: center; flex-shrink: 0; color: transparent; font-size: 11px; background: #fff; }
.ct-check.is-on { background: var(--violet); box-shadow: none; color: #fff; }
button.ct-check { width: 24px; height: 24px; background: transparent; box-shadow: none; position: relative; }
button.ct-check::before { content: ''; position: absolute; inset: 4px; border-radius: 3px; box-shadow: inset 0 0 0 1.5px #8590a2; }
button.ct-check.is-on::before { background: var(--violet); box-shadow: none; }
button.ct-check .bi { position: relative; }
/* labels */
.ct-lab { display: block; height: 32px; border-radius: 4px; padding: 0 12px; font: 500 14px/32px var(--sans); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; text-align: start; }
.ct-lab.is-big { height: 36px; line-height: 36px; }
.ct-lab.is-fill { flex: 1; min-width: 0; height: 24px; line-height: 24px; font-size: 12px; }
.ct-lrow { display: flex; align-items: center; gap: 4px; }
.ct-lbar { flex: 1; min-width: 0; border-radius: 4px; transition: filter .12s; }
.ct-lbar:hover { filter: brightness(.92); }
.ct-lprev { padding: 16px 20px; border-radius: 8px; background: #f7f8f9; }
.ct-swatches { display: grid; grid-template-columns: repeat(5, 1fr); gap: 6px; }
.ct-swatches button { height: 32px; border-radius: 4px; transition: transform .1s; }
.ct-swatches button:hover { transform: scale(1.06); }
.ct-swatches button.is-on { box-shadow: 0 0 0 2px #fff, 0 0 0 4px var(--violet); }
/* people, menus, rows */
.ct-prow, .ct-mrow, .ct-krow { width: 100%; display: flex; align-items: center; gap: 10px; min-height: 40px; padding: 4px 8px; border-radius: 6px; color: #172b4d; font: 14px var(--sans); text-align: start; }
.ct-prow:hover, .ct-mrow:hover, .ct-krow:hover { background: rgba(9,30,66,.06); }
.ct-prow .av, .ct-krow .av, .ct-mrow .av { width: 28px; height: 28px; border: 0; font-size: 11px; flex: none; } /* a round face never stretches with its row */
.ct-prow > span:not(.av) { flex: 1; min-width: 0; }
.ct-prow > .bi { color: #626f86; font-size: 13px; }
.ct-prow.is-on { background: rgba(124,107,245,.08); }
.ct-krow span:last-child { flex: 1; min-width: 0; }
.ct-menu { display: flex; flex-direction: column; gap: 2px; }
.ct-mrow > .bi { width: 20px; text-align: center; color: #44546f; font-size: 15px; }
.ct-mrow > span:not(.av) { flex: 1; min-width: 0; }
.ct-mrow kbd { font: 600 11px var(--sans); color: #626f86; padding: 1px 6px; border-radius: 4px; background: rgba(9,30,66,.06); }
.ct-mrow.is-two { align-items: flex-start; padding: 8px; }
.ct-mrow.is-two > .bi { margin-top: 2px; color: var(--violet); }
.ct-mrow.is-two b { display: block; font-weight: 600; }
.ct-mrow.is-two small { display: block; font-size: 12px; color: #626f86; margin-top: 1px; }
.ct-pills { display: flex; flex-wrap: wrap; gap: 6px; }
.ct-pill { min-height: 32px; padding: 0 12px; border-radius: 999px; box-shadow: inset 0 0 0 1px #dcdfe4; font: 500 13px var(--sans); color: #172b4d; }
.ct-pill:hover { background: rgba(9,30,66,.05); }
.ct-pill.is-on { box-shadow: inset 0 0 0 2px var(--violet); color: #5b4bd6; background: rgba(124,107,245,.07); }
.ct-pill[disabled] { opacity: .4; }
/* dates */
.ct-cal { margin: 0; }
.ct-cal .grid button.is-range { background: rgba(124,107,245,.14); border-radius: 0; }
.ct-drow { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; padding: 4px 6px; border-radius: 8px; }
.ct-drow.is-focus { background: rgba(124,107,245,.07); }
.ct-dl { width: 100%; font: 600 12px var(--sans); color: #44546f; }
.ct-dval { height: 32px; padding: 0 10px; border-radius: 6px; box-shadow: inset 0 0 0 1px #8590a2; font: 14px var(--sans); color: #172b4d; min-width: 120px; text-align: start; background: #fff; }
.ct-drow.is-focus .ct-dval:not([disabled]) { box-shadow: inset 0 0 0 2px var(--violet); }
.ct-dval[disabled] { color: #8590a2; box-shadow: inset 0 0 0 1px #dcdfe4; background: #f7f8f9; }
.ct-time { flex: 0 0 72px; height: 32px; padding: 0 10px; border-radius: 6px; box-shadow: inset 0 0 0 1px #8590a2; background: #fff; font-variant-numeric: tabular-nums; }
.ct-time:focus { box-shadow: inset 0 0 0 2px var(--violet); }
.ct-time[disabled] { box-shadow: inset 0 0 0 1px #dcdfe4; background: #f7f8f9; }
/* cover */
.ct-sizes { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.ct-size { position: relative; height: 62px; border-radius: 6px; box-shadow: inset 0 0 0 1px #dcdfe4; overflow: hidden; padding: 0; display: flex; flex-direction: column; justify-content: flex-end; gap: 4px; }
.ct-size .face { position: absolute; inset: 0 0 auto; height: 28px; background: #c1c7d0; }
.ct-size.is-full .face { height: 100%; }
.ct-size i { position: relative; display: block; height: 4px; border-radius: 2px; background: #c1c7d0; margin: 0 10px; }
.ct-size i + i { width: 40%; margin-bottom: 8px; }
.ct-size.is-full i { background: rgba(255,255,255,.75); }
.ct-size.is-on { box-shadow: 0 0 0 2px var(--violet); }
.ct-size[disabled] { opacity: .45; }
.ct-swatches.is-cover button { height: 32px; }
.ct-pics { display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px; }
.ct-pics button { height: 52px; border-radius: 4px; overflow: hidden; padding: 0; }
.ct-pics img { width: 100%; height: 100%; object-fit: cover; display: block; }
.ct-pics button.is-on { box-shadow: 0 0 0 2px #fff, 0 0 0 4px var(--violet); }
/* archived items, templates, reactions */
.ct-arow { display: flex; flex-direction: column; gap: 6px; padding: 8px; border-radius: 8px; box-shadow: 0 1px 1px rgba(9,30,66,.25), 0 0 1px rgba(9,30,66,.31); }
.ct-atitle { text-align: start; }
.ct-atitle b { display: block; font-weight: 500; overflow-wrap: anywhere; }
.ct-atitle span { font-size: 12px; color: #626f86; }
.ct-aacts { display: flex; gap: 6px; }
.ct-aacts .ct-btn { height: 30px; font-size: 13px; }
.ct-tpl { width: 100%; text-align: start; padding: 8px 10px; border-radius: 8px; background: #fff; box-shadow: 0 1px 1px rgba(9,30,66,.25), 0 0 1px rgba(9,30,66,.31); border-top: 6px solid var(--cc, transparent); }
.ct-tpl:hover { box-shadow: 0 0 0 2px var(--violet); }
.ct-tpl b { display: block; font-weight: 500; overflow-wrap: anywhere; }
.ct-tlabels { display: flex; gap: 4px; margin-bottom: 4px; }
.ct-tlabels i { width: 32px; height: 6px; border-radius: 3px; }
.ct-tlabels:empty { display: none; }
.ct-reacts { display: grid; grid-template-columns: repeat(7, 1fr); gap: 4px; }
.ct-remo { height: 44px; border-radius: 12px; display: grid; place-items: center; font: 26px/1 'Apple Color Emoji', 'Segoe UI Emoji', 'Noto Color Emoji', sans-serif; transition: transform .12s; }
.ct-remo:hover { background: rgba(9,30,66,.06); transform: scale(1.12); }
.ct-remo.is-mine { background: #ede9fe; }
.ct-remo.is-more { font-size: 18px; color: #44546f; background: rgba(9,30,66,.06); }
.cb-emo.is-pop { margin: 0; }
.cb-remo { font: 15px/1 'Apple Color Emoji', 'Segoe UI Emoji', 'Noto Color Emoji', sans-serif; }
.ct-reacts button { height: 40px; border-radius: 8px; font-size: 18px; display: grid; place-items: center; }
.ct-reacts button:hover { background: rgba(9,30,66,.06); transform: scale(1.08); }
.is-up > .bi { color: #0c66e4; } .is-heart > .bi { color: #e0457b; } .is-check > .bi { color: #22a06b; }
.is-star > .bi { color: #e2a400; } .is-fire > .bi { color: #e2770f; } .is-eyes > .bi { color: #6e5dc6; }

/* the board's bar, and a list's foot */
.tb-bar { display: flex; gap: 8px; padding: 0 max(14px, var(--sa-l, env(safe-area-inset-left, 0px))) 10px; }
.tb-btn { display: inline-flex; align-items: center; gap: 6px; height: 32px; padding: 0 12px; border-radius: 8px; background: rgba(255,255,255,.12); color: #fff; font: 600 13px var(--sans); border: 1px solid rgba(255,255,255,.14); }
.tb-btn:hover { background: rgba(255,255,255,.2); }
.tb-btn.is-on { background: #fff; color: #5b4bd6; }
.tl-frow { display: flex; align-items: center; gap: 4px; }
.tl-frow .tl-add { flex: 1; min-width: 0; }
.tl-tpl { flex-shrink: 0; }

/* the card front's new parts */
.tc-label { min-width: 40px; height: 16px; padding: 0 8px; border-radius: 4px; font-size: 12px; font-weight: 500; line-height: 16px; max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; cursor: pointer; transition: height .15s, min-width .15s; }
.tc-labels.is-narrow .tc-label { height: 8px; min-width: 40px; width: 40px; padding: 0; font-size: 0; }
.tc-label.is-blank { min-width: 40px; }
.tc-cover.is-colour { height: 32px; }
.tc.is-full { min-height: 56px; }
.tc.is-full .tc-in { padding: 14px 12px; }
.tc.is-full .tc-title { font-size: 16px; font-weight: 500; }
.tc.is-photo { min-height: 180px; display: flex; flex-direction: column; justify-content: flex-end; overflow: hidden; color: #fff; }
.tc-bg { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; border-radius: 8px; }
.tc.is-photo .tc-in { position: relative; background: linear-gradient(transparent, rgba(0,0,0,.65)); border-radius: 0 0 8px 8px; padding-top: 28px; }
.tc.is-photo .tc-title { color: #fff; text-shadow: 0 1px 3px rgba(0,0,0,.5); }
button.tc-due { cursor: pointer; font-size: 12px; }
button.tc-due:not(.is-soon):not(.is-late):not(.is-done):hover { background: rgba(9,30,66,.08); }
.tc-b.is-all { background: #1f845a; color: #fff; }
.tc-tpl { display: inline-flex; align-items: center; gap: 4px; height: 20px; padding: 0 6px; border-radius: 3px; background: #e9f2ff; color: #0055cc; font-size: 11px; font-weight: 600; }
.tc-mem { gap: 0; }
.tc-mem .av + .av { margin-inline-start: -4px; box-shadow: 0 0 0 2px #fff; }
.tc-mem .tc-more { background: #dcdfe4; color: #44546f; }

/* the open card's new chips */
.cb-chip.cb-label { min-width: 46px; justify-content: center; }
.cb-chip.cb-label.is-blank { min-width: 52px; }
.cb-banner { border: 0; background: #e9f2ff; color: #0055cc; }
.cb-banner.is-archived { background: #fff7d6; color: #7f5f01; }
.cb-people .av + .av { margin-inline-start: -10px; box-shadow: 0 0 0 2px #fff; }
.cb-chip.cb-due { padding: 0 4px 0 6px; gap: 2px; }
.cb-tick, .cb-dval { display: inline-flex; align-items: center; gap: 6px; height: 26px; padding: 0 6px; border-radius: 12px; color: inherit; font: inherit; }
.cb-dval em { font-style: normal; font-size: 11px; padding: 1px 6px; border-radius: 4px; background: rgba(0,0,0,.08); }
.cb-dval .bi { font-size: 10px; opacity: .7; }
.cb-due.is-done { background: #dcfff1; color: #216e4e; }
.cb-chip.is-all { background: #dcfff1; color: #216e4e; border-color: transparent; }
.cb-watch.is-on { background: #ede9fe; color: #5b4bd6; border-color: transparent; }
.cb-hacts { display: flex; gap: 6px; }
.cb-hacts .cb-btn[hidden] { display: none; }
.cb-step .cb-stext { flex: 1; min-width: 0; }
.cb-step span.cb-stools, .cb-step .av { flex: none; text-decoration: none; }
.cb-step.is-done span.cb-stools { color: inherit; text-decoration: none; }
.cb-stools { display: inline-flex; align-items: center; gap: 2px; }
.cb-sb { height: 28px; min-width: 28px; padding: 0 6px; border-radius: 14px; display: inline-flex; align-items: center; justify-content: center; gap: 4px; color: #b3abc4; font: 600 11.5px var(--sans); opacity: 0; transition: opacity .12s; }
.cb-sb .av { width: 22px; height: 22px; font-size: 9px; border: 0; }
.cb-sb.is-set { opacity: 1; color: #5b4bd6; background: #f3f0ff; }
.cb-sb.is-set.is-late { color: #ae2e24; background: #ffe2df; }
.cb-sb:hover { background: #f4f1ea; color: #1d1530; }
.cb-step:hover .cb-sb, .cb-sb:focus-visible { opacity: 1; }
@media (hover: none) { .cb-sb { opacity: 1; } }
.cb-compose { position: relative; }
.cb-emo { margin: -2px -4px 8px; padding: 8px; border-radius: 12px; background: var(--ink); color: #fff; color-scheme: dark; animation: rise .2s var(--spring); }
.cb-emo[hidden] { display: none; }
.cb-addrow { display: flex; align-items: center; gap: 6px; }
.cb-addrow { margin: 0; }
.cb-addgo { flex-shrink: 0; width: 36px; height: 36px; border-radius: 10px; display: grid; place-items: center; background: #6d5ce8; color: #fff; font-size: 16px; border: 0; transition: transform .12s, opacity .15s; }
.cb-addgo:active { transform: scale(.94); }
.cb-addgo[disabled] { opacity: .5; }
.cb-lists > .cb-sec + .cb-sec { margin-top: 26px; } /* each checklist its own block */
.tl-compose .cb-emo { margin: 8px 0 0; padding: 6px; }
.tl:not(.is-folded) { min-width: 0; width: var(--tl-w); } /* an open emoji panel never widens its list */
.tl-compose .emo-tab { flex: 1 0 26px; }
.ct-frow { display: flex; align-items: center; gap: 6px; }
.fo-namerow { display: flex; align-items: center; gap: 8px; }
.fo-namerow .fo-in { flex: 1; min-width: 0; }
.fo-namerow + .cb-emo { margin: 8px 0 0; }
.ct-frow input { flex: 1; min-width: 0; }
.ct .cb-emo { margin: 0; padding: 6px; }
.ct .emo-grid { grid-template-columns: repeat(auto-fill, minmax(36px, 1fr)); max-height: 220px; }
.ct .emo { height: 36px; font-size: 22px; line-height: 36px; }
.ct .emo-tab { flex: 1 0 24px; }
.tl-compose .emo-grid { grid-template-columns: repeat(auto-fill, minmax(36px, 1fr)); max-height: 200px; }
.tl-compose .emo { height: 36px; font-size: 22px; line-height: 36px; }
.cb-step .cb-stext { cursor: text; border-radius: 6px; }
.cb-step .cb-stext:hover { background: rgba(124,107,245,.07); }
.cb-sedit { display: flex; flex-direction: column; gap: 8px; padding: 10px 12px; border-bottom: 1px solid #f2ede3; background: #fbf9ff; }
.cb-sedit .cb-additem { border-style: solid; border-color: #7c6bf5; background: #fff; }
.cb-sebar { display: flex; align-items: center; gap: 6px; }
.cb-sedit .cb-emo { margin: 0; }
.cb-addrow .cb-additem { flex: 1; min-width: 0; }
.cb-sec > .cb-emo { margin: 0; }
.cb-emo .emo-grid { max-height: min(30vh, 240px); }
.cb-icon.is-on { background: #ede9fe; color: #5b4bd6; }
.cb-atpop { position: absolute; bottom: calc(100% + 6px); inset-inline-start: 0; min-width: 200px; padding: 4px; border-radius: 12px; background: #fff; box-shadow: 0 12px 28px -10px rgba(29,21,48,.45), 0 0 0 1px rgba(29,21,48,.06); z-index: 5; }
.cb-atpop button { width: 100%; display: flex; align-items: center; gap: 8px; height: 38px; padding: 0 8px; border-radius: 8px; font: 600 13.5px var(--sans); color: #1d1530; text-align: start; }
.cb-atpop button:hover { background: #f4f1ea; }
.cb-atpop .av { width: 24px; height: 24px; border: 0; font-size: 10px; }
.cb-ment { font-weight: 700; color: #5b4bd6; background: #f3f0ff; border-radius: 4px; padding: 0 2px; }
.cb-reacts { display: flex; flex-wrap: wrap; align-items: center; gap: 4px 6px; margin-top: 4px; }
.cb-react { display: inline-flex; align-items: center; gap: 4px; height: 24px; padding: 0 8px; border-radius: 12px; background: #f4f1ea; font: 600 12px var(--sans); color: #44546f; }
.cb-react.is-mine { background: #ede9fe; box-shadow: inset 0 0 0 1px #c9bff7; color: #5b4bd6; }
.cb-react.is-add { opacity: 0; color: #8a8199; background: transparent; }
.cb-c:hover .cb-react.is-add, .cb-react.is-add:focus-visible { opacity: 1; }
@media (hover: none) { .cb-react.is-add { opacity: 1; } }

/* ── Friends (takenote/friends): invite by email; each friend has a notebook of their own ── */
.fr-form { display: flex; gap: 8px; }
.fr-form .fo-in { flex: 1 1 auto; width: auto; min-width: 0; }
.fr-form .fr-go { flex: 0 0 auto; width: auto; height: 46px; margin: 0; padding: 0 20px; border-radius: 12px; }
.fr-how { padding: 12px; border-radius: 14px; background: #f6f3ec; }
.fr-how p { margin: 0 0 8px; font-size: 12.5px; line-height: 1.45; color: #6b6280; }
.fr-link { width: 100%; display: flex; align-items: center; gap: 8px; min-height: 40px; padding: 0 12px; border-radius: 10px; background: #fff; border: 1px solid #e4ddcf; color: #1d1530; font: 600 12.5px var(--sans); text-align: start; }
.fr-link span { flex: 1; min-width: 0; overflow-wrap: anywhere; }
.fr-link b { color: #5b4bd6; white-space: nowrap; }
.fr-list { display: flex; flex-direction: column; gap: 6px; }
.fr-list .tile.is-skeleton { background-color: #eee8dc; }
.fr-none { margin: 4px 2px; font-size: 13px; color: #6b6280; }
.fr-row { display: flex; align-items: center; gap: 12px; min-height: 58px; padding: 8px 10px; border-radius: 14px; border: 1.5px solid #ebe7f2; }
.fr-face { width: 36px; height: 36px; border-radius: 50%; flex-shrink: 0; display: grid; place-items: center; font: 800 14px var(--sans); color: #fff; background: #16a394; }
.fr-who { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px; }
.fr-who b { font: 700 14px var(--sans); color: #1d1530; overflow-wrap: anywhere; }
.fr-who small { font-size: 11.5px; color: #6b6280; overflow-wrap: anywhere; }
.fr-state { align-self: flex-start; font-size: 11px; font-weight: 700; padding: 1px 8px; border-radius: 999px; background: #fef3c7; color: #92400e; }
.fr-state.is-in { background: #dcfce7; color: #166534; }
.fr-rm { flex-shrink: 0; height: 34px; padding: 0 12px; border-radius: 10px; font: 700 12.5px var(--sans); color: #c9372c; border: 1.5px solid #f2b8b2; background: #fff; }
.fr-rm.is-sure { background: #c9372c; color: #fff; border-color: #c9372c; }
.fr-lk { padding: 14px; border-radius: 16px; border: 1.5px dashed #d9d0f7; display: flex; flex-direction: column; gap: 8px; }
.fr-lk h4 { margin: 0; font: 800 14px var(--sans); color: #1d1530; }
.fr-lk p { margin: 0; font-size: 12.5px; line-height: 1.45; color: #6b6280; }
.fr-out { display: flex; flex-direction: column; gap: 8px; padding: 10px 12px; border-radius: 12px; background: #f1edff; }
.fr-out[hidden] { display: none; }
.fr-url { font: 600 12.5px var(--sans); color: #3b2fa0; overflow-wrap: anywhere; direction: ltr; text-align: start; }
.fr-acts { display: flex; gap: 8px; }
.fr-btn { flex: 1; height: 40px; border-radius: 11px; display: inline-flex; align-items: center; justify-content: center; gap: 7px; font: 700 13px var(--sans); color: #5b4bd6; background: #fff; border: 1.5px solid #d9d0f7; }
.fr-btn.is-main { background: #7c6bf5; color: #fff; border-color: #7c6bf5; }
.fr-face.is-link { background: #7c6bf5; font-size: 18px; }
.fr-mail { display: flex; flex-direction: column; gap: 8px; padding: 2px 2px 4px; }
.fr-mail h4 { margin: 0; font: 800 14px var(--sans); color: #1d1530; }
.fr-mail p { margin: 0; font-size: 12.5px; line-height: 1.45; color: #6b6280; }
.fr-lk { border-style: solid; border-color: #c9bdfb; background: #faf8ff; }
</style>

