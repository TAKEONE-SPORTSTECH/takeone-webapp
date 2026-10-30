{{--
    TakeNote on a desk (takenote/desk): the phone's look, re-laid for a wide
    screen. Loaded AFTER takenote/styles and only by the desk page, so nothing
    here can reach the phone app. Three columns, as the notes apps have them:
    the folders (folding to a strip), the list of notes, and the pane where a
    note opens on its own paper, or the board stands as columns.
--}}
<style>
html, body.dk { height: 100%; overflow: hidden; }
.dk-shell { height: 100vh; display: flex; }

/* ── 1. The folder column ────────────────────────────────────────────── */
.dk-rail { width: var(--rail-w, 236px); flex-shrink: 0; min-height: 0; display: flex; flex-direction: column; padding: 14px 10px 12px; background: #120c1e;
    border-inline-end: 1px solid rgba(255,255,255,.06); transition: width .22s var(--ease); overflow: hidden; position: relative; }
/* its edge: a hairline that lights up under the pointer, and drags */
.dk-grip { position: absolute; top: 0; bottom: 0; inset-inline-end: 0; width: 7px; cursor: col-resize; z-index: 5; touch-action: none; outline: 0; }
.dk-grip::after { content: ""; position: absolute; top: 0; bottom: 0; inset-inline-end: 0; width: 2px; background: var(--violet); opacity: 0; transition: opacity .15s; }
.dk-grip:hover::after, .dk-grip:focus-visible::after, body.col-sizing .dk-grip:active::after { opacity: 1; }
body.col-sizing { cursor: col-resize; user-select: none; }
body.col-sizing .dk-rail { transition: none; }
body.rail-off .dk-rail .dk-grip { display: none; }
.dk-head { display: flex; align-items: center; gap: 8px; padding: 0 4px 10px; }
.dk-back, .dk-icon { width: 30px; height: 30px; border-radius: 50%; display: grid; place-items: center; flex-shrink: 0; font-size: 13px; color: rgba(255,255,255,.75); text-decoration: none;
    background: rgba(255,255,255,.07); border: 1px solid rgba(255,255,255,.1); transition: background .15s, color .15s; }
.dk-icon { border-radius: 9px; background: none; border: 0; font-size: 15px; color: rgba(255,255,255,.6); }
.dk-back:hover, .dk-icon:hover { background: rgba(255,255,255,.12); color: #fff; }
[dir=rtl] .dk-back i, [dir=rtl] .dk-icon .bi-layout-sidebar { display: inline-block; transform: scaleX(-1); }
.dk-head .dk-icon { margin-inline-start: auto; }
.dk-brand { display: flex; align-items: center; gap: 8px; font-weight: 800; font-size: 13.5px; min-width: 0; }
.dk-brand img { width: 24px; height: 24px; border-radius: 7px; }
.dk-sec { margin: 14px 10px 4px; font: 800 10.5px var(--sans); letter-spacing: .1em; text-transform: uppercase; color: rgba(255,255,255,.45); white-space: nowrap; }
.dk-sec:first-of-type { margin-top: 6px; }
/* the views and the smart lists: rows, not pills */
.dk .seg, .dk .filters { margin: 0; padding: 0; background: none; border-radius: 0; display: flex; flex-direction: column; gap: 2px; overflow: visible; }
.dk .seg button, .dk .filter { height: 34px; padding: 0 10px; border-radius: 9px; display: flex; align-items: center; gap: 10px; justify-content: flex-start;
    font: 500 13px var(--sans); color: rgba(255,255,255,.78); background: none; box-shadow: none; white-space: nowrap; }
.dk .seg button .bi, .dk .filter .bi { font-size: 15px; width: 18px; text-align: center; flex-shrink: 0; color: rgba(255,255,255,.6); }
.dk .seg button:hover, .dk .filter:hover { background: rgba(255,255,255,.06); color: #fff; }
.dk .seg button.is-on, .dk .filter.is-on { background: rgba(124,107,245,.24); color: #fff; font-weight: 600; box-shadow: none; }
.dk .seg button.is-on .bi, .dk .filter.is-on .bi { color: #b3a8ff; }
.dk .seg .n, .dk .filter .n { margin-inline-start: auto; font-size: 11.5px; opacity: .55; }
.dk .filter[data-filter=all] { display: none; }
.dk .folders { display: block; flex: 1; min-height: 0; max-height: none; overflow-y: auto; padding: 8px 0 6px; scrollbar-width: thin; scrollbar-color: rgba(255,255,255,.15) transparent; }
.dk .fo-head { padding: 6px 10px 4px; }
.dk .fo-tree > .fo-row[data-folder=""]:not(:last-child) { display: none; } /* "All notes" is the Notes view above */
.dk-foot { display: flex; align-items: center; gap: 8px; padding: 10px 4px 0; border-top: 1px solid rgba(255,255,255,.07); }
.dk-foot .today { font-size: 11.5px; color: rgba(255,255,255,.5); white-space: nowrap; overflow: hidden; }
.dk-foot .dk-icon:first-of-type { margin-inline-start: auto; }
.dk-foot .dk-icon + .dk-icon, .dk-foot .signout .dk-icon { margin-inline-start: -4px; }
.signout { display: contents; }
body.rail-off .dk-foot .signout .dk-icon { margin: 0; }

/* folded: a strip of icons — the views, the smart lists, and the way back out */
body.rail-off .dk-rail { width: 58px; padding-inline: 8px; }
body.rail-off .dk-rail .lb, body.rail-off .dk-rail .n, body.rail-off .dk-sec, body.rail-off .folders, body.rail-off .dk-foot .today, body.rail-off .dk-foot .people, body.rail-off .dk-back { display: none; }
body.rail-off .dk-head { flex-direction: column; padding-inline: 0; }
body.rail-off .dk-head .dk-icon { margin: 4px 0 8px; }
body.rail-off .seg button, body.rail-off .filter { justify-content: center; padding: 0; height: 40px; }
body.rail-off .seg { padding-bottom: 8px; margin-bottom: 8px; border-bottom: 1px solid rgba(255,255,255,.07); }
body.rail-off .dk-foot { margin-top: auto; flex-direction: column; justify-content: center; border: 0; }
body.rail-off .dk-foot .dk-icon { margin: 0; }
body.rail-off .sp-shared, body.rail-off .st-w, body.rail-off .sp-tx, body.rail-off .sp-back { display: none; }
body.rail-off .st-box { padding: 6px; justify-content: center; background: none; border: 0; }
body.rail-off .sp-bar { grid-template-columns: 1fr; justify-items: center; padding: 6px; }

/* ── 2. The list ─────────────────────────────────────────────────────── */
.dk-list { width: var(--list-w, 340px); flex-shrink: 0; min-height: 0; position: relative; display: flex; flex-direction: column; background: #1b1330; border-inline-end: 1px solid rgba(255,255,255,.06); }
@media (max-width: 1100px) { .dk-list { width: var(--list-w, 300px); } .dk-rail { width: var(--rail-w, 210px); } }
body[data-view=board] .dk-list, body[data-view=map] .dk-list { display: none; } /* the board and the map want the room */
.dk-lhead { display: flex; align-items: center; gap: 10px; padding: 16px 16px 10px; }
.dk-lt { flex: 1; min-width: 0; }
.dk-lt h1 { margin: 0; font: 800 19px/1.2 var(--sans); letter-spacing: -.01em; overflow-wrap: anywhere; }
.dk-lt p { margin: 2px 0 0; font-size: 11.5px; color: rgba(255,255,255,.55); }
.dk-new { width: 34px; height: 34px; border-radius: 10px; display: grid; place-items: center; font-size: 16px; color: #fff; background: var(--violet);
    box-shadow: 0 8px 18px -8px rgba(124,107,245,.95); transition: transform .15s var(--ease), filter .15s; }
.dk-new:hover { filter: brightness(1.1); }
.dk-new:active { transform: scale(.93); }
.dk .search { margin: 0 16px 6px; height: 36px; border-radius: 10px; animation: none; border: 0; }
.dk .search input { font-size: 13px; }
.dk .search kbd { font: 700 10px var(--sans); padding: 2px 6px; border-radius: 5px; background: rgba(255,255,255,.1); color: rgba(255,255,255,.55); }
.dk .notes { flex: 1; min-height: 0; overflow-y: auto; padding: 0 8px 24px; scrollbar-width: thin; scrollbar-color: rgba(255,255,255,.18) transparent; }
.dk .notes[hidden] { display: flex; }
.dk .grp { margin: 16px 10px 4px; font-size: 10.5px; }
.dk .grp:first-child { margin-top: 6px; }
/* a desk list is rows on the column, not grouped cards */
.dk .tile { min-height: 0; padding: 10px; border-radius: 10px; background: none; border-bottom: 1px solid rgba(255,255,255,.05); cursor: pointer; animation: none; }
.dk .tile.is-last { border-bottom: 0; }
.dk .tile:hover { background: rgba(255,255,255,.05); }
.dk .notes .tile.is-open { background: var(--violet); border-bottom-color: transparent; }
.dk .notes .tile.is-open .ex, .dk .notes .tile.is-open .mt { color: rgba(255,255,255,.88); }
.dk .notes .tile.is-open .tt .bi { color: #fff; }
.dk .tile .tt { font-size: 13.5px; }
.dk .tile .ex { font-size: 12px; }
.dk .tile .mt { font-size: 11px; margin-top: 5px; }
.dk .tile .th { width: 44px; height: 44px; border-radius: 9px; }
.dk .tile .row-av { width: 22px; height: 22px; font-size: 9.5px; }
.dk .tile.is-skeleton { height: 54px; }
.dk .notes .empty { margin: 24px 6px 0; }

/* ── The pane: the open note, or the board ───────────────────────────── */
.dk-pane { flex: 1; position: relative; min-width: 0; min-height: 0; overflow: hidden;
    background:
        radial-gradient(700px 420px at 100% 110%, rgba(124,107,245,.14), transparent 60%),
        radial-gradient(rgba(255,255,255,.045) 1px, transparent 1.2px) 0 0 / 18px 18px,
        #1b1330; }
.dk-empty { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: 40px; color: rgba(255,255,255,.62);
    animation: rise .45s var(--spring) both; }
.dk-empty b { font: italic 400 38px/1.1 var(--serif); color: #fff; max-width: 520px; }
.dk-empty p { margin: 10px 0 0; font-size: 13.5px; line-height: 1.55; max-width: 420px; }
.dk-squiggle { width: 132px; height: 50px; color: var(--violet); margin-bottom: 14px; }
.dk-starts { display: flex; flex-wrap: wrap; justify-content: center; gap: 10px; margin-top: 26px; }
.dk-starts button { width: 104px; padding: 16px 0 12px; border-radius: 18px; display: flex; flex-direction: column; align-items: center; gap: 9px; font-size: 12px; font-weight: 700; color: rgba(255,255,255,.82);
    background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.08); transition: transform .18s var(--spring), background .18s, border-color .18s; }
.dk-starts button i { width: 42px; height: 42px; border-radius: 14px; display: grid; place-items: center; font-size: 18px; color: var(--k); background: color-mix(in srgb, var(--k) 18%, transparent); }
.dk-starts button:hover { transform: translateY(-3px); background: rgba(255,255,255,.08); border-color: color-mix(in srgb, var(--k) 45%, transparent); }
body[data-view=board] .dk-empty, body.has-note .dk-empty { display: none; }

/* the board fills the pane; its lists scroll on their own */
.dk .board { position: absolute; inset: 0; }
.dk .board:not([hidden]) { display: flex; flex-direction: column; }
.dk .board .tb-bar { padding: 16px 22px 0; }
.dk .board .tb { flex: 1; min-height: 0; height: auto; padding: 14px 22px 22px; }
.dk .board[hidden] { display: none; }

/* the map fills the pane, edge to edge */
.dk .map { position: absolute; inset: 0; padding: 0; }
.dk .map[hidden] { display: none; }
.dk .nm { height: 100%; border-radius: 0; box-shadow: none; }
body.dk[data-view=map] .dk-empty { display: none; }

/* the note page fills the pane (partials/page, opened with host) */
.dk .mp.is-hosted { box-shadow: 0 0 0 1px rgba(29,21,48,.04); }
.dk .mp.is-hosted .mp-crumb { display: none; }
/* a desk has the room: the page uses the pane's width (a phone keeps its narrow reading column) */
.dk .mp.is-hosted .mp-col { max-width: none; padding: 8px clamp(28px, 4vw, 72px) 140px; }
.dk .mp.is-hosted .ed > h1:first-child { font-size: 48px; }
.dk .mp.is-hosted .mp-foot > * { width: min(100%, 640px); }

/* sheets and messages sit in the middle of a wide screen */
.dk .ss { align-items: center; }
.dk .ss-sheet { border-radius: 24px; max-height: 88vh; }
.dk .toast { bottom: 24px; }
body.dk.has-note .toast, body.dk.is-writing .toast { bottom: 104px; } /* above an open note's own bar */
</style>
