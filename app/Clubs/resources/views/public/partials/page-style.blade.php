{{--
    The club front page's own stylesheet — desktop AND mobile read it, so the two
    pages share one visual language (design source: drafts/Club Pages & Design/Club).
    The trainer page reads it too, with its own accent.

    Every class is `cp-` prefixed and lives here rather than in Tailwind
    utilities, because the Tailwind bundle is PREBUILT: an arbitrary class used
    for the first time on this page would have no rule at all.

    Fonts are the vendored variable files in public/fonts/designer (OFL licences
    beside them) — nothing is fetched from a third-party host.

    Needs: $cpAccent (a whitelisted #hex).
--}}
@once
<style>
@font-face { font-family: 'CP Grotesk'; src: url('/fonts/designer/space-grotesk/SpaceGrotesk-VF.ttf') format('truetype'); font-weight: 300 700; font-display: swap; }
@font-face { font-family: 'CP Sans'; src: url('/fonts/designer/dm-sans/DMSans-VF.ttf') format('truetype'); font-weight: 100 1000; font-display: swap; }

.cp { --cp-accent: {{ $cpAccent }}; --cp-accent-dark: color-mix(in srgb, var(--cp-accent) 76%, #000);
      --cp-accent-soft: color-mix(in srgb, var(--cp-accent) 12%, #fff);
      --cp-ink: #171719; --cp-muted: #6f6f75; --cp-paper: #f7f5f1; --cp-card: #fff; --cp-line: #e8e4de;
      --cp-sand: #ebe8e2; --cp-blush: #f1e9e6;
      font-family: 'CP Sans', 'Inter', system-ui, sans-serif; background: var(--cp-paper); color: var(--cp-ink);
      min-height: 100vh; }
.cp.cp-dark { --cp-ink: #f7f6f2; --cp-muted: #aaa9ad; --cp-paper: #101012; --cp-card: #1d1d20; --cp-line: #38383b;
              --cp-sand: #161618; --cp-blush: #18161a; --cp-accent-soft: color-mix(in srgb, var(--cp-accent) 22%, #1d1d20); }
.cp h1, .cp h2, .cp h3, .cp h4, .cp .cp-display { font-family: 'CP Grotesk', 'Inter', sans-serif; letter-spacing: -.05em; margin: 0; }
.cp :where(p) { margin: 0; }
.cp :where(a) { color: inherit; text-decoration: none; }
.cp button, .cp a { transition: background .25s ease, color .25s ease, transform .25s ease, border-color .25s ease, opacity .25s ease; }
.cp :where(button) { cursor: pointer; font: inherit; color: inherit; background: none; border: 0; }
.cp :focus-visible { outline: 3px solid color-mix(in srgb, var(--cp-accent) 45%, #fff); outline-offset: 3px; }
[x-cloak] { display: none !important; }

/* ---- type ---- */
.cp-tag { font-size: 11px; text-transform: uppercase; letter-spacing: .17em; font-weight: 800; }
.cp-red { color: var(--cp-accent); }
.cp-flip { display: inline-block; }
[dir=rtl] .cp-flip { transform: scaleX(-1); }
.cp-muted { color: var(--cp-muted); }
.cp-h-xl { font-size: clamp(44px, 6vw, 76px); line-height: .98; font-weight: 700; }
.cp-h-lg { font-size: clamp(36px, 5vw, 64px); line-height: 1; font-weight: 700; }
.cp-h-md { font-size: clamp(30px, 3.6vw, 48px); line-height: 1.02; font-weight: 700; }
.cp-lead { font-size: 17px; line-height: 1.75; }

/* ---- surfaces & controls ---- */
.cp-wrap { max-width: 1440px; margin: 0 auto; padding-inline: 56px; }
.cp-section { padding-block: 96px; }
.cp-card { background: var(--cp-card); border: 1px solid var(--cp-line); border-radius: 23px; overflow: hidden; }
.cp-pill { display: inline-flex; align-items: center; justify-content: center; gap: 6px; border: 1px solid var(--cp-line); border-radius: 100px;
           padding: 10px 17px; font-size: 13px; font-weight: 700; white-space: nowrap; min-height: 42px; }
.cp-pill:hover, .cp-pill.is-on { background: var(--cp-ink); color: var(--cp-paper); border-color: var(--cp-ink); }
.cp-pill-light { border-color: rgba(255,255,255,.35); color: #fff; }
.cp-pill-light:hover { background: #fff; color: #111; border-color: #fff; }
.cp-cta { background: var(--cp-accent); color: #fff !important; border-radius: 100px; padding: 14px 23px; font-weight: 800; display: inline-flex;
          align-items: center; justify-content: center; gap: 10px; min-height: 48px; white-space: nowrap; }
.cp-cta:hover { background: var(--cp-accent-dark); transform: translateY(-2px); }
.cp-cta[disabled] { opacity: .45; cursor: not-allowed; transform: none; }
.cp-cta-white { background: #fff; color: var(--cp-accent) !important; }
.cp-cta-white:hover { background: #fff; transform: translateY(-2px); box-shadow: 0 10px 30px rgba(0,0,0,.18); }
.cp-ghost { border: 1px solid rgba(255,255,255,.6) !important; color: #fff; border-radius: 100px; padding: 0 24px; min-height: 48px; font-weight: 700;
            display: inline-flex; align-items: center; gap: 8px; }
.cp-ghost:hover { background: rgba(255,255,255,.14); }
.cp-round { width: 40px; height: 40px; border-radius: 999px; display: inline-grid; place-items: center; flex-shrink: 0; }
.cp-badge { display: inline-flex; align-items: center; gap: 6px; border-radius: 999px; padding: 5px 11px; font-size: 11px; font-weight: 800; }
.cp-scroll-x { overflow-x: auto; scrollbar-width: none; scroll-snap-type: x proximity; }
.cp-scroll-x::-webkit-scrollbar { display: none; }
.cp-scroll-x > * { scroll-snap-align: start; flex-shrink: 0; }
.cp-cover { width: 100%; height: 100%; object-fit: cover; display: block; }
.cp-lift { transition: transform .3s ease, box-shadow .3s ease; }
.cp-lift:hover { transform: translateY(-5px); box-shadow: 0 18px 50px rgba(0,0,0,.09); }
.cp-icon-tile { display: grid; place-items: center; border-radius: 18px; background: var(--cp-accent-soft); color: var(--cp-accent); flex-shrink: 0; }
.cp-portrait { aspect-ratio: 3 / 4; overflow: hidden; background: var(--cp-sand); flex-shrink: 0; }
.cp-portrait img { width: 100%; height: 100%; object-fit: cover; display: block; }

/* ---- hero ---- */
.cp-hero { position: relative; color: #fff; background-color: #111; background-size: cover; background-position: center; }
.cp-editorial { font-size: clamp(60px, 8vw, 128px); line-height: .91; font-weight: 700; letter-spacing: -.06em; text-transform: uppercase; overflow-wrap: anywhere; }
.cp-outline { -webkit-text-stroke: 1px rgba(255,255,255,.6); color: transparent; }
.cp-marquee { background: var(--cp-accent); color: #fff; overflow: hidden; white-space: nowrap; padding: 14px 0; font-weight: 800; letter-spacing: .13em; font-size: 12px; text-transform: uppercase; }
.cp-marquee-track { display: inline-block; padding-inline-start: 100%; animation: cp-marquee 38s linear infinite; }
@keyframes cp-marquee { to { transform: translateX(-100%); } }
[dir=rtl] .cp-marquee-track { animation-name: cp-marquee-rtl; }
@keyframes cp-marquee-rtl { to { transform: translateX(100%); } }

/* ---- media grid ---- */
.cp-media { display: grid; grid-template-columns: 1.4fr 1fr 1fr; gap: 12px; }
.cp-media > button { overflow: hidden; position: relative; min-height: 160px; border-radius: 18px; background: #222; color: #fff; text-align: start; }
.cp-media > button:first-child { grid-row: span 2; }
.cp-media img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; transition: transform .45s ease; }
.cp-media > button:hover img { transform: scale(1.06); }
.cp-media > button::after { content: ''; position: absolute; inset: 0; background: linear-gradient(0deg, rgba(0,0,0,.72), transparent 70%); }
.cp-media-label { position: absolute; z-index: 1; bottom: 15px; inset-inline-start: 15px; inset-inline-end: 15px; font-weight: 800; font-size: 14px; }

/* ---- overlays ---- */
.cp-overlay { position: fixed; inset: 0; z-index: 80; background: rgba(8,8,11,.78); display: flex; align-items: center; justify-content: center; padding: 18px; }
.cp-modal { width: min(560px, 100%); max-height: 90vh; overflow-y: auto; background: var(--cp-card); color: var(--cp-ink); border-radius: 26px; box-shadow: 0 25px 100px rgba(0,0,0,.35); }
.cp-modal-wide { width: min(1100px, 100%); }
.cp-band { position: relative; overflow: hidden; color: #fff; padding: 22px 24px; flex-shrink: 0; }
.cp-band::before { content: ''; position: absolute; right: -32px; top: -40px; width: 144px; height: 144px; border-radius: 999px; background: rgba(255,255,255,.1); }
.cp-band > * { position: relative; }
.cp-x { width: 36px; height: 36px; border-radius: 999px; background: rgba(255,255,255,.15) !important; border: 1px solid rgba(255,255,255,.25) !important; color: #fff !important;
        display: grid; place-items: center; flex-shrink: 0; }
.cp-sheet { position: fixed; inset-inline: 0; bottom: 0; z-index: 81; max-height: 92vh; display: flex; flex-direction: column; background: var(--cp-card); color: var(--cp-ink);
            border-radius: 26px 26px 0 0; box-shadow: 0 -20px 80px rgba(0,0,0,.3); overflow: hidden; }
.cp-sheet-body { flex: 1; overflow-y: auto; padding: 20px; padding-bottom: calc(20px + env(safe-area-inset-bottom)); }
.cp-handle { margin: 0 auto 12px; width: 40px; height: 4px; border-radius: 99px; background: rgba(255,255,255,.4); }
.cp-viewer { position: fixed; inset: 0; z-index: 90; background: rgba(8,8,11,.95); display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 18px; color: #fff; }
.cp-viewer img { max-height: 76vh; max-width: 88vw; object-fit: contain; border-radius: 16px; }
.cp-fade { animation: cp-appear .3s ease; }
@keyframes cp-appear { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: none; } }

.cp-input { width: 100%; border: 1px solid var(--cp-line); background: var(--cp-card); color: var(--cp-ink); border-radius: 14px; padding: 12px 14px; font: inherit; }
.cp-input:focus { outline: none; border-color: var(--cp-accent); box-shadow: 0 0 0 3px color-mix(in srgb, var(--cp-accent) 18%, transparent); }

/* ---- FAQ ---- */
.cp-faq summary { list-style: none; cursor: pointer; font-weight: 700; display: flex; justify-content: space-between; gap: 12px; align-items: center; }
.cp-faq summary::-webkit-details-marker { display: none; }
.cp-faq summary i { transition: transform .25s ease; color: var(--cp-accent); }
.cp-faq[open] summary i { transform: rotate(45deg); }

@media (max-width: 1100px) { .cp-wrap { padding-inline: 32px; } }
@media (prefers-reduced-motion: reduce) {
    .cp *, .cp *::before, .cp *::after { animation: none !important; transition: none !important; }
    .cp-marquee-track { padding-inline-start: 0; }
}
</style>
@endonce
