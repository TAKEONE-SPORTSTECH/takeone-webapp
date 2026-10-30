{{--
    TakeNote on a phone (takenote/app): the Folders screen and the list you go
    into, as Apple Notes has them (takenote/layout). Loaded AFTER
    takenote/styles and only by the phone page, so nothing here reaches the
    desk. body[data-screen] says which screen is showing.
--}}
<style>
body[data-screen=home] .nb-list-only, body[data-screen=list] .nb-home-only { display: none !important; }
.app { padding-bottom: calc(96px + var(--safe-b)); }

/* ── the head: big title; on a list, the way back above it ───────────── */
.head .head-row { min-height: 40px; }
.head .today { margin-inline-end: auto; }
.head .people { margin-inline-start: 0; }
.nb-back { display: inline-flex; align-items: center; gap: 4px; height: 40px; margin-inline-start: -6px; padding: 0 6px; border-radius: 10px; font: 600 16px var(--sans); color: #b3a8ff; margin-inline-end: auto; max-width: 70%; }
.nb-back span { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.nb-back:active { background: rgba(255,255,255,.08); }
[dir=rtl] .nb-back .bi { display: inline-block; transform: scaleX(-1); }
.head h1 { margin: 6px 0 0; font: 800 32px/1.1 var(--sans); letter-spacing: -.02em; overflow-wrap: anywhere; display: flex; align-items: center; gap: 10px; }
.head h1.has-folder::before { content: ''; width: 22px; height: 17px; flex-shrink: 0; border-radius: 3px 5px 5px 5px; background: var(--fc);
    clip-path: polygon(0 0, 42% 0, 52% 16%, 100% 16%, 100% 100%, 0 100%); }
.search { height: 42px; border-radius: 12px; border: 0; background: rgba(255,255,255,.08); animation: none; }

/* ── the Folders screen ──────────────────────────────────────────────── */
.seg { margin: 16px max(18px, var(--sa-r, env(safe-area-inset-right, 0px))) 0 max(18px, var(--sa-l, env(safe-area-inset-left, 0px)));
    padding: 0; background: none; border-radius: 0; display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; }
.seg button { height: auto; min-height: 76px; padding: 12px; border-radius: 15px; display: flex; flex-direction: column; align-items: flex-start; justify-content: space-between; gap: 10px;
    background: rgba(255,255,255,.07); color: #fff; box-shadow: none; font: 700 14px var(--sans); text-align: start; }
.seg button .bi { font-size: 20px; color: var(--k); }
.seg button[data-view=notes] { background: var(--violet); box-shadow: 0 12px 24px -14px rgba(124,107,245,.95); }
.seg button.is-on { background: rgba(255,255,255,.07); color: #fff; box-shadow: none; }
.seg button[data-view=notes].is-on { background: var(--violet); }
.seg button .lb { display: flex; width: 100%; }
.seg button { position: relative; }
.seg .n { position: absolute; inset-inline-end: 12px; bottom: 12px; margin: 0; opacity: 1; font-size: 18px; font-weight: 800; }
.seg button:active { transform: scale(.97); }

/* grouped inset rows: the smart lists and the folders */
.nb-group, .filters.nb-group { margin: 16px max(18px, var(--sa-r, env(safe-area-inset-right, 0px))) 0 max(18px, var(--sa-l, env(safe-area-inset-left, 0px)));
    padding: 0; border-radius: 14px; background: rgba(255,255,255,.06); overflow: hidden; display: flex; flex-direction: column; gap: 0; }
.folders .nb-group { margin: 0; }
.folders { display: block; padding: 0 max(18px, var(--sa-r, env(safe-area-inset-right, 0px))) 0 max(18px, var(--sa-l, env(safe-area-inset-left, 0px))); overflow: visible; }
.folders .fo-head { padding: 22px 6px 8px; font-size: 11.5px; }
.nb-line, .filter.nb-line { width: 100%; height: auto; min-height: 50px; padding: 0 14px; padding-inline-start: calc(14px + var(--d, 0) * 22px); border-radius: 0; display: flex; align-items: center; gap: 12px;
    font: 500 15.5px var(--sans); color: #fff; background: none; text-align: start; border-bottom: 1px solid rgba(255,255,255,.06); }
.nb-line:last-child { border-bottom: 0; }
.nb-line:active, .filter.nb-line:active { background: rgba(255,255,255,.07); }
.nb-line > .bi:first-child { font-size: 18px; width: 20px; text-align: center; flex-shrink: 0; color: var(--k, rgba(255,255,255,.7)); }
.nb-line .nb-name { flex: 1; min-width: 0; overflow-wrap: anywhere; padding: 13px 0; unicode-bidi: plaintext; text-align: start; }
.nb-line .n { margin: 0; opacity: 1; font-size: 14px; color: rgba(255,255,255,.5); font-variant-numeric: tabular-nums; }
.nb-line > .bi-chevron-right { font-size: 12px; color: rgba(255,255,255,.35); }
[dir=rtl] .nb-line > .bi-chevron-right { transform: scaleX(-1); }
.filter.nb-line.is-on { background: none; color: #fff; }
.nb-add { color: #c4b8ff; }
body.is-filing .nb-line[data-folder] { box-shadow: inset 0 0 0 2px rgba(167,139,250,.35); }

/* ── the bottom bar ──────────────────────────────────────────────────── */
.capture { left: 0; right: 0; bottom: 0; transform: none; width: auto; border-radius: 0; border: 0; border-top: 1px solid rgba(255,255,255,.07);
    background: rgba(22,15,36,.94); box-shadow: none; padding: 10px max(18px, var(--sa-r, env(safe-area-inset-right, 0px))) calc(10px + var(--safe-b)) max(18px, var(--sa-l, env(safe-area-inset-left, 0px)));
    display: flex; align-items: center; gap: 10px; justify-content: flex-start; }
.capture.is-away { transform: translateY(110%); }
.nb-bar-btn { display: inline-flex; align-items: center; gap: 8px; height: 46px; padding: 0 6px; font: 600 15px var(--sans); color: #b3a8ff; }
.nb-bar-btn .bi { font-size: 20px; }
.nb-voice { width: 46px; height: 46px; border-radius: 50%; display: grid; place-items: center; font-size: 19px; color: #ff7aa2; background: rgba(255,122,162,.16); }
.nb-voice:active, .nb-new:active, .nb-bar-btn:active { transform: scale(.92); }
.nb-count { flex: 1; text-align: center; font-size: 13px; color: rgba(255,255,255,.6); }
.nb-new { margin-inline-start: auto; width: 50px; height: 50px; border-radius: 16px; display: grid; place-items: center; font-size: 21px; color: #fff; background: var(--violet);
    box-shadow: 0 10px 22px -10px rgba(124,107,245,.95); transition: transform .15s var(--ease); }
body[data-screen=list] .nb-count + .nb-new { margin-inline-start: 0; }

/* the board and the map inside the list screen keep their own room */
body[data-screen=list] .board, body[data-screen=list] .map { margin-top: 10px; }
@media (prefers-reduced-motion: reduce) { .tile, .tile.is-swiped, .capture { transition: none; } }
</style>
