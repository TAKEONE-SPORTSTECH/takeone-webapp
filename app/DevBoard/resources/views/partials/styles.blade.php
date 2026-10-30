{{--
    Margin's look: "Ink on Night Paper". A deep aubergine desk with a faint dot
    grid; ideas are warm paper slips hanging from a luminous thread per lane.
    Its own stylesheet because the app's Tailwind bundle is prebuilt and this
    page is standalone. Palette stays on the platform's purple.
--}}
<script>
try { if (window.Capacitor && window.Capacitor.isNativePlatform && window.Capacitor.isNativePlatform()) document.documentElement.classList.add('in-shell'); } catch (e) {}
</script>
<style>
:root { --sa-t: env(safe-area-inset-top, 0px); --sa-b: env(safe-area-inset-bottom, 0px); --sa-l: env(safe-area-inset-left, 0px); --sa-r: env(safe-area-inset-right, 0px); }
html.in-shell { --sa-t: 0px; --sa-b: 0px; --sa-l: 0px; --sa-r: 0px; }
@font-face { font-family: 'Instrument Serif'; font-style: normal; font-weight: 400; font-display: swap;
    src: url('{{ asset('vendor/instrument-serif/instrument-serif-latin-400-normal.woff2') }}') format('woff2'); }
@font-face { font-family: 'Instrument Serif'; font-style: italic; font-weight: 400; font-display: swap;
    src: url('{{ asset('vendor/instrument-serif/instrument-serif-latin-400-italic.woff2') }}') format('woff2'); }

:root {
    --desk: #160f24;
    --desk-2: #1f1533;
    --primary: #7c6bf5;
    --paper: #fbf7ef;
    --paper-2: #f3ecdf;
    --ink: #1d1530;
    --ink-soft: #6b6280;
    --line: rgba(255,255,255,.08);
    --muted: rgba(255,255,255,.58);
    --serif: 'Instrument Serif', 'Iowan Old Style', 'Palatino Linotype', Georgia, serif;
    --sans: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
    --spring: cubic-bezier(.2,.9,.25,1.15);
    --ease: cubic-bezier(.2,.8,.2,1);
    --safe-b: var(--sa-b, env(safe-area-inset-bottom, 0px));
    --safe-t: var(--sa-t, env(safe-area-inset-top, 0px));
}
* { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
html, body { margin: 0; background: var(--desk); }
body { font-family: var(--sans); color: #fff; font-size: 14px; -webkit-font-smoothing: antialiased; overscroll-behavior-y: contain; }
button { font: inherit; color: inherit; background: none; border: 0; padding: 0; cursor: pointer; }
[dir=rtl] .flip { display: inline-block; transform: scaleX(-1); }

/* ── The desk ─────────────────────────────────────────────────────────── */
.desk {
    min-height: 100dvh; max-width: 560px; margin: 0 auto; position: relative;
    background:
        radial-gradient(1200px 500px at 110% -10%, rgba(124,107,245,.22), transparent 60%),
        radial-gradient(800px 600px at -20% 40%, rgba(244,114,182,.07), transparent 60%),
        radial-gradient(rgba(255,255,255,.055) 1px, transparent 1.2px) 0 0 / 18px 18px,
        var(--desk);
    padding-bottom: calc(96px + var(--safe-b));
}
.desk::after { /* paper grain over everything, never in the way */
    content: ''; position: fixed; inset: 0; pointer-events: none; opacity: .05; mix-blend-mode: overlay; z-index: 1;
    background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='160' height='160'><filter id='n'><feTurbulence type='fractalNoise' baseFrequency='.9' numOctaves='2' stitchTiles='stitch'/></filter><rect width='100%' height='100%' filter='url(%23n)'/></svg>");
}
@media (min-width: 561px) { body { background: #0e0918; } .desk { box-shadow: 0 0 0 1px var(--line), 0 40px 120px -40px #000; } }

/* ── Hero band (Rule #6) ──────────────────────────────────────────────── */
.hero {
    position: relative; overflow: hidden; color: #fff;
    padding: calc(16px + var(--safe-t)) 20px 40px;
    background: linear-gradient(150deg, #7c6bf5, #7c6bf5b0);
}
.hero-orb { position: absolute; border-radius: 50%; background: rgba(255,255,255,.10); pointer-events: none; }
.hero-orb-a { inset-inline-end: -40px; top: -40px; width: 176px; height: 176px; }
.hero-orb-b { inset-inline-end: 24px; bottom: 32px; width: 96px; height: 96px; }
.hero-controls { position: relative; z-index: 50; display: flex; align-items: center; justify-content: space-between; }
.round {
    width: 40px; height: 40px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center;
    background: rgba(255,255,255,.15); border: 1px solid rgba(255,255,255,.25); color: #fff; text-decoration: none;
    -webkit-backdrop-filter: blur(8px); backdrop-filter: blur(8px); transition: transform .15s var(--ease);
}
.round:active { transform: scale(.92); }
.hero-identity { position: relative; z-index: 10; margin-top: 20px; }
.chips { display: flex; gap: 6px; flex-wrap: wrap; }
.chip {
    display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 999px;
    font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em;
    background: rgba(255,255,255,.2); -webkit-backdrop-filter: blur(8px); backdrop-filter: blur(8px);
}
.chip:empty { display: none; }
.hero h1 { font-size: 24px; font-weight: 900; line-height: 1.15; margin: 12px 0 0; letter-spacing: -.01em; }
.owner { margin: 6px 0 0; font-size: 13px; color: rgba(255,255,255,.85); display: flex; align-items: center; gap: 6px; }

/* ── Lane rail: a frosted tray straddling the band's tail ─────────────── */
.rail-wrap {
    position: sticky; top: 0; z-index: 30; margin: -22px 12px 0; padding-top: var(--safe-t);
}
.rail {
    display: flex; gap: 4px; overflow-x: auto; scrollbar-width: none; padding: 5px;
    border-radius: 16px; background: rgba(28,19,46,.82); border: 1px solid var(--line);
    -webkit-backdrop-filter: blur(14px) saturate(140%); backdrop-filter: blur(14px) saturate(140%);
    box-shadow: 0 12px 30px -16px rgba(0,0,0,.8);
}
.rail::-webkit-scrollbar { display: none; }
.pill {
    flex: 0 0 auto; height: 32px; padding: 0 11px; border-radius: 11px; display: inline-flex; align-items: center; gap: 7px;
    font-size: 12px; font-weight: 600; color: var(--muted); white-space: nowrap; transition: background .25s var(--ease), color .25s;
    touch-action: pan-x;
}
.pill .dot { width: 7px; height: 7px; border-radius: 50%; background: var(--c); box-shadow: 0 0 0 3px color-mix(in srgb, var(--c) 22%, transparent); }
.pill .n { font-size: 10.5px; font-weight: 700; opacity: .7; font-variant-numeric: tabular-nums; }
.pill.is-on { background: var(--paper); color: var(--ink); box-shadow: 0 4px 14px -6px var(--c); }
.pill.is-on .dot { box-shadow: 0 0 10px 1px var(--c); }
.pill.sortable-chosen { transform: scale(1.06); }

/* ── Filters ──────────────────────────────────────────────────────────── */
.filters { display: flex; align-items: center; gap: 6px; padding: 12px 16px 2px; overflow-x: auto; scrollbar-width: none; }
.filters::-webkit-scrollbar { display: none; }
.filters-gap { flex: 1; min-width: 4px; }
.filter {
    flex: 0 0 auto; height: 26px; padding: 0 10px; border-radius: 999px; font-size: 11.5px; font-weight: 600;
    color: var(--muted); border: 1px solid var(--line); display: inline-flex; align-items: center; gap: 5px;
    transition: all .2s var(--ease);
}
.filter.is-on { color: #fff; border-color: rgba(124,107,245,.6); background: rgba(124,107,245,.22); }
.filter .bi-lightning-charge-fill { color: #fbbf24; font-size: 10px; }

/* ── Track: one lane per screen ───────────────────────────────────────── */
.track {
    display: flex; overflow-x: auto; scroll-snap-type: x mandatory; scrollbar-width: none; align-items: flex-start;
    position: relative; z-index: 2;
}
.track::-webkit-scrollbar { display: none; }
body.is-dragging .track { overflow-x: hidden; scroll-snap-type: none; }
.lane { flex: 0 0 100%; scroll-snap-align: start; padding: 14px 16px 24px; position: relative; min-height: 60vh; }
.lane::before { /* the thread */
    content: ''; position: absolute; inset-inline-start: 25px; top: 74px; bottom: 0; width: 1.5px;
    background: linear-gradient(to bottom, var(--c), color-mix(in srgb, var(--c) 30%, transparent) 70%, transparent);
    opacity: .55; transition: opacity .3s, box-shadow .3s;
}
.lane.is-hot::before { opacity: 1; box-shadow: 0 0 12px 1px var(--c); }
.lane-head { display: flex; align-items: baseline; gap: 10px; padding: 2px 2px 12px; }
.lane-title {
    font-family: var(--serif); font-size: 30px; line-height: 1; color: #fff; letter-spacing: -.01em;
    background: none; border: 0; padding: 0; outline: none; min-width: 0; max-width: 70%;
}
.lane-title:focus { border-bottom: 1px dashed rgba(255,255,255,.4); }
.lane-index { font-family: var(--serif); font-style: italic; font-size: 15px; color: var(--c); }
.lane-count { margin-inline-start: auto; font-size: 11px; color: var(--muted); font-variant-numeric: tabular-nums; }

/* Composer: a dashed slip waiting for ink */
.composer { margin: 0 0 12px 22px; position: relative; }
.composer-btn {
    width: 100%; text-align: start; padding: 11px 14px; border-radius: 12px; border: 1px dashed rgba(255,255,255,.18);
    font-family: var(--serif); font-style: italic; font-size: 16px; color: rgba(255,255,255,.5);
    display: flex; align-items: center; gap: 8px; transition: border-color .2s, background .2s;
}
.composer-btn:active { background: rgba(255,255,255,.04); }
.composer-btn .bi { font-style: normal; font-size: 14px; color: var(--c); }
.composer-form { display: none; }
.composer.is-open .composer-btn { display: none; }
.composer.is-open .composer-form { display: block; animation: rise .28s var(--spring); }
.composer textarea {
    width: 100%; resize: none; border: 0; outline: none; border-radius: 12px; padding: 12px 14px 26px;
    font: 600 16px/1.35 var(--sans); color: var(--ink); background: var(--paper);
    box-shadow: 0 0 0 2px color-mix(in srgb, var(--c) 70%, transparent), 0 14px 30px -14px rgba(0,0,0,.7);
}
.composer-hint { position: absolute; inset-inline-end: 12px; bottom: 8px; font-size: 10px; color: var(--ink-soft); pointer-events: none; }

/* ── Cards: paper slips on the thread ─────────────────────────────────── */
.cards { list-style: none; margin: 0; padding: 0 0 0 22px; display: flex; flex-direction: column; gap: 8px; min-height: 40px; }
[dir=rtl] .cards { padding: 0 22px 0 0; }
.card { position: relative; outline: none; touch-action: manipulation; }
.card::before { /* knot on the thread + its short string */
    content: ''; position: absolute; inset-inline-start: -13px; top: 17px; width: 8px; height: 8px; border-radius: 50%;
    background: var(--c); box-shadow: 0 0 0 3px var(--desk), 0 0 8px var(--c);
}
.card::after { content: ''; position: absolute; inset-inline-start: -5px; top: 20.5px; width: 5px; height: 1px; background: var(--c); opacity: .7; }
.slip {
    position: relative; border-radius: 12px; padding: 10px 12px 9px; color: var(--ink);
    background: linear-gradient(180deg, var(--paper), var(--paper-2));
    box-shadow: inset 0 1px 0 rgba(255,255,255,.8), 0 1px 1px rgba(0,0,0,.25), 0 10px 22px -14px rgba(0,0,0,.9);
    transition: transform .35s var(--spring), box-shadow .35s var(--ease);
    overflow: hidden;
}
.card:focus-visible .slip { box-shadow: 0 0 0 2px var(--primary), 0 10px 22px -14px rgba(0,0,0,.9); }
.card:active .slip { transform: scale(.985); }
.slip-title { font-size: 13.5px; font-weight: 600; line-height: 1.35; margin: 0; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; overflow-wrap: anywhere; }
.slip-meta { display: flex; align-items: center; gap: 9px; margin-top: 7px; font-size: 11px; color: var(--ink-soft); }
.slip-meta .m { display: inline-flex; align-items: center; gap: 3px; font-variant-numeric: tabular-nums; }
.slip-meta .m .bi { font-size: 11px; }
.slip-meta .age { margin-inline-start: auto; }
.sig { display: inline-flex; align-items: center; gap: 4px; font-weight: 600; color: var(--ink-c); }
.sig svg { width: 22px; height: 6px; }
.slip-flag { color: #dc2626; font-weight: 700; letter-spacing: .02em; }
.slip-due { padding: 1px 6px; border-radius: 6px; background: rgba(124,107,245,.12); color: #5b4bd6; font-weight: 600; }
/* Priority: a folded corner in the priority's colour */
.card[data-p=high] .slip::after, .card[data-p=urgent] .slip::after {
    content: ''; position: absolute; top: 0; inset-inline-end: 0; width: 18px; height: 18px;
    background: linear-gradient(225deg, var(--fold) 50%, rgba(0,0,0,.12) 50%, transparent 62%);
}
[dir=rtl] .card[data-p=high] .slip::after, [dir=rtl] .card[data-p=urgent] .slip::after { transform: scaleX(-1); }
.card[data-p=high] { --fold: #f59e0b; }
.card[data-p=urgent] { --fold: #ef4444; }
.card[data-p=low] .slip-title { font-weight: 500; color: #4a4360; }
.card.is-pending .slip { background: linear-gradient(100deg, var(--paper) 30%, #fff 50%, var(--paper) 70%) 0 0/300% 100%; animation: shimmer 1.2s linear infinite; }
.card.is-settling .slip { animation: settle .5s var(--spring); }

/* Drag states (SortableJS fallback mode — we own the ghost) */
.card.is-ghost .slip { visibility: hidden; }
.card.is-ghost { border-radius: 12px; outline: 1.5px dashed color-mix(in srgb, var(--c) 70%, transparent); outline-offset: -1px; background: color-mix(in srgb, var(--c) 10%, transparent); }
.card.is-ghost::before { box-shadow: 0 0 0 3px var(--desk), 0 0 14px 2px var(--c); }
.card.is-lifted { opacity: 1 !important; z-index: 9999 !important; }
.card.is-lifted::before, .card.is-lifted::after { display: none; }
.card.is-lifted .slip {
    transform: rotate(-2.2deg) scale(1.04);
    box-shadow: inset 0 1px 0 rgba(255,255,255,.8), 0 30px 50px -18px rgba(0,0,0,.85), 0 0 0 1px rgba(124,107,245,.35);
}
[dir=rtl] .card.is-lifted .slip { transform: rotate(2.2deg) scale(1.04); }
/* Over the dock the slip shrinks to a token, so the pads it is aimed at stay visible. */
body.over-dock .card.is-lifted .slip { transform: scale(.42) rotate(-6deg); transform-origin: var(--grab-x, 50%) var(--grab-y, 0); opacity: .92; }

/* Empty lane */
.empty { margin: 18px 0 0 22px; padding: 26px 18px; text-align: center; border-radius: 16px; border: 1px dashed rgba(255,255,255,.1); }
.empty svg { width: 64px; height: 40px; opacity: .8; }
.empty p { margin: 8px 0 0; font-family: var(--serif); font-style: italic; font-size: 18px; color: rgba(255,255,255,.75); }
.empty small { display: block; margin-top: 6px; font-size: 11px; color: var(--muted); }
.cards:not(:empty) + .empty { display: none; }

/* ── Lane dock ────────────────────────────────────────────────────────── */
.dock {
    position: fixed; inset-inline: 0; bottom: 0; z-index: 60; margin: 0 auto; max-width: 560px;
    padding: 10px 12px calc(12px + var(--safe-b));
    background: linear-gradient(to top, rgba(14,9,24,.98) 55%, rgba(14,9,24,0));
    transform: translateY(110%); transition: transform .38s var(--spring); pointer-events: none;
}
body.is-dragging .dock { transform: translateY(0); pointer-events: auto; }
.dock-hint { margin: 0 0 8px; text-align: center; font-family: var(--serif); font-style: italic; font-size: 14px; color: var(--muted); }
.dock-pads { display: flex; gap: 6px; }
.pad {
    flex: 1; min-width: 0; height: 58px; list-style: none; margin: 0; padding: 0; position: relative; border-radius: 14px;
    border: 1px solid color-mix(in srgb, var(--c) 45%, transparent); background: color-mix(in srgb, var(--c) 12%, rgba(255,255,255,.02));
    display: flex; align-items: center; justify-content: center; overflow: hidden; transition: transform .25s var(--spring), background .2s, box-shadow .2s;
}
.pad[hidden] { display: none; }
.pad-label { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 3px; pointer-events: none; font-size: 11px; font-weight: 700; color: #fff; }
.pad-label .dot { width: 8px; height: 8px; border-radius: 50%; background: var(--c); box-shadow: 0 0 8px var(--c); }
.pad > .card { position: absolute; opacity: 0; pointer-events: none; height: 0; overflow: hidden; }
.pad.is-over, .pad:has(.card) { transform: translateY(-4px) scale(1.04); background: color-mix(in srgb, var(--c) 32%, transparent); box-shadow: 0 0 0 1.5px var(--c), 0 12px 30px -10px var(--c); }
.pad.is-landed { animation: landed .5s var(--spring); }

/* ── Sheets (Rule #8) ─────────────────────────────────────────────────── */
.backdrop { position: fixed; inset: 0; z-index: 70; background: rgba(8,5,16,.55); -webkit-backdrop-filter: blur(2px); backdrop-filter: blur(2px); opacity: 0; pointer-events: none; transition: opacity .3s; }
.backdrop.is-on { opacity: 1; pointer-events: auto; }
.sheet {
    position: fixed; inset-inline: 0; bottom: 0; z-index: 80; margin: 0 auto; max-width: 560px; max-height: 92vh;
    display: flex; flex-direction: column; border-radius: 24px 24px 0 0; background: var(--paper); color: var(--ink);
    transform: translateY(105%); transition: transform .42s var(--spring); box-shadow: 0 -30px 80px -30px #000;
}
.sheet.is-open { transform: translateY(0); }
.sheet.is-dragged { transition: none; }
.band { flex-shrink: 0; position: relative; overflow: hidden; color: #fff; padding: 12px 20px 16px; border-radius: 24px 24px 0 0; touch-action: none; }
.band-orb { position: absolute; inset-inline-end: -32px; top: -40px; width: 144px; height: 144px; border-radius: 50%; background: rgba(255,255,255,.1); }
.handle { margin: 0 auto 12px; width: 40px; height: 4px; border-radius: 4px; background: rgba(255,255,255,.4); }
.band-row { position: relative; display: flex; align-items: flex-start; gap: 12px; }
.tile { width: 48px; height: 48px; flex-shrink: 0; border-radius: 16px; background: rgba(255,255,255,.2); display: grid; place-items: center; font-size: 20px; }
.band h3 { margin: 0; font-size: 18px; font-weight: 900; line-height: 1.2; overflow-wrap: anywhere; }
.band .sub { margin: 2px 0 0; font-size: 12px; color: rgba(255,255,255,.85); }
.band .x { width: 36px; height: 36px; flex-shrink: 0; border-radius: 50%; background: rgba(255,255,255,.15); border: 1px solid rgba(255,255,255,.25); display: grid; place-items: center; margin-inline-start: auto; }
.band .x:active { transform: scale(.9); }
.sheet-body { flex: 1; overflow-y: auto; padding: 16px 20px calc(20px + var(--safe-b)); }
.field-label { display: block; font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: var(--ink-soft); margin: 18px 0 8px; }
.field-label:first-child { margin-top: 0; }
.title-input {
    width: 100%; resize: none; border: 1px solid #e7dfcf; outline: none; border-radius: 12px; padding: 10px 12px; background: #fff;
    font: 600 16px/1.35 var(--sans); color: var(--ink); transition: box-shadow .2s, border-color .2s;
}
.title-input:focus { border-color: transparent; box-shadow: 0 0 0 2px var(--primary); }
.seg { display: grid; grid-template-columns: repeat(4, 1fr); gap: 6px; }
.seg button {
    height: 38px; border-radius: 11px; border: 1px solid #e7dfcf; background: #fff; font-size: 12px; font-weight: 600; color: var(--ink-soft);
    display: inline-flex; align-items: center; justify-content: center; gap: 6px; transition: all .2s var(--ease);
}
.seg button .dot { width: 7px; height: 7px; border-radius: 50%; background: var(--pc); }
.seg button.is-on { border-color: var(--pc); color: var(--ink); background: color-mix(in srgb, var(--pc) 12%, #fff); box-shadow: 0 0 0 1px var(--pc); }
.lanes-pick { display: flex; flex-wrap: wrap; gap: 6px; }
.lanes-pick button {
    height: 34px; padding: 0 12px; border-radius: 999px; border: 1px solid #e7dfcf; background: #fff; font-size: 12px; font-weight: 600; color: var(--ink);
    display: inline-flex; align-items: center; gap: 7px;
}
.lanes-pick button .dot { width: 7px; height: 7px; border-radius: 50%; background: var(--c); }
.lanes-pick button[aria-current=true] { background: var(--ink); color: #fff; border-color: var(--ink); }
.note-soon { margin-top: 18px; padding: 12px 14px; border-radius: 14px; background: var(--paper-2); font-size: 12px; color: var(--ink-soft); display: flex; gap: 10px; }
.note-soon .bi { color: var(--primary); }
.meta-line { margin-top: 18px; font-size: 11.5px; color: var(--ink-soft); display: flex; flex-wrap: wrap; gap: 4px 12px; }
.danger {
    margin-top: 20px; width: 100%; height: 42px; border-radius: 12px; border: 1px solid #fecaca; color: #dc2626; font-size: 13px; font-weight: 600;
    display: inline-flex; align-items: center; justify-content: center; gap: 8px;
}
.danger:active { background: #fef2f2; }
.bin-row { display: flex; align-items: center; gap: 10px; padding: 10px 0; border-bottom: 1px solid #ece4d5; }
.bin-row:last-child { border-bottom: 0; }
.bin-row .t { flex: 1; min-width: 0; font-size: 13px; font-weight: 600; overflow-wrap: anywhere; }
.bin-row .s { display: block; font-size: 11px; font-weight: 400; color: var(--ink-soft); margin-top: 2px; }
.bin-row button { height: 32px; padding: 0 12px; border-radius: 999px; background: var(--ink); color: #fff; font-size: 12px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; flex-shrink: 0; }
.bin-empty { text-align: center; padding: 30px 0; font-family: var(--serif); font-style: italic; font-size: 18px; color: var(--ink-soft); }

/* ── Toast ────────────────────────────────────────────────────────────── */
.toast {
    position: fixed; inset-inline: 0; bottom: calc(18px + var(--safe-b)); z-index: 95; margin: 0 auto; width: max-content; max-width: calc(100vw - 32px);
    display: flex; align-items: center; gap: 12px; padding: 10px 10px 10px 16px; border-radius: 14px;
    background: #fff; color: var(--ink); font-size: 13px; font-weight: 600; box-shadow: 0 20px 50px -20px #000, 0 0 0 1px rgba(0,0,0,.04);
    transform: translateY(calc(100% + 40px)); visibility: hidden; transition: transform .4s var(--spring), visibility 0s .4s;
}
.toast.is-on { transform: translateY(0); visibility: visible; transition: transform .4s var(--spring); }
.toast.is-error { background: #fef2f2; color: #991b1b; }
.toast .act { height: 30px; padding: 0 12px; border-radius: 9px; background: var(--ink); color: #fff; font-size: 12px; font-weight: 700; }
.toast:not(:has(.act)) { padding-inline-end: 16px; }
body.is-dragging .toast { transform: translateY(calc(100% + 40px)); visibility: hidden; }

@keyframes rise { from { opacity: 0; transform: translateY(8px) scale(.98); } to { opacity: 1; transform: none; } }
@keyframes settle { 0% { transform: scale(1.04) rotate(-1deg); } 55% { transform: scale(.985); } 100% { transform: none; } }
@keyframes landed { 0% { transform: scale(1.08); } 100% { transform: none; } }
@keyframes shimmer { to { background-position: -300% 0; } }
@keyframes enter { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: none; } }
.card.is-new .slip { animation: enter .45s var(--spring) both; }

@media (prefers-reduced-motion: reduce) {
    *, *::before, *::after { animation-duration: .01ms !important; transition-duration: .01ms !important; }
    .card.is-lifted .slip { transform: none; }
}
</style>
