{{--
    The note editor's look (partials/editor): a page of warm paper with a rose
    margin rule, serif headings, and the ink toolbar that rides on the keyboard.
    Expects the custom properties --ink, --ink-soft, --paper, --paper-2,
    --primary, --serif, --sans, --spring, --ease, --safe-b and the `rise`
    keyframes from the host page.
--}}
<style>
/* ── The page ─────────────────────────────────────────────────────────── */
.paper {
    flex: 1; min-height: 0; overflow-y: auto; -webkit-overflow-scrolling: touch; position: relative;
    padding: 22px 20px 28px 42px;
    background:
        linear-gradient(to right, transparent 28px, rgba(219,39,119,.30) 28px, rgba(219,39,119,.30) 29.5px, transparent 29.5px),
        var(--paper);
}
[dir=rtl] .paper {
    padding: 22px 42px 28px 20px;
    background:
        linear-gradient(to left, transparent 28px, rgba(219,39,119,.30) 28px, rgba(219,39,119,.30) 29.5px, transparent 29.5px),
        var(--paper);
}
.ed {
    position: relative; outline: none; min-height: 42vh; color: var(--ink); caret-color: var(--primary);
    font: 400 16px/1.65 var(--sans); overflow-wrap: anywhere; -webkit-user-select: text; user-select: text;
}
.ed.is-blank::before {
    content: attr(data-placeholder); position: absolute; inset-inline: 0; top: 0; pointer-events: none;
    font-family: var(--serif); font-style: italic; font-size: 21px; line-height: 1.3; color: #b3a99a;
}
.ed > :first-child { margin-top: 0; }
.ed p, .ed div { margin: 0 0 .6em; }
.ed h1 { font-family: var(--serif); font-weight: 400; font-size: 32px; line-height: 1.08; letter-spacing: -.01em; margin: .55em 0 .3em; }
.ed h2 { font-family: var(--serif); font-style: italic; font-weight: 400; font-size: 23px; line-height: 1.2; margin: .6em 0 .25em; color: #3a2f5c; }
.ed h3, .ed h4 { font-size: 17px; font-weight: 700; margin: .6em 0 .25em; }
.ed b, .ed strong { font-weight: 700; }
.ed ul, .ed ol { margin: 0 0 .6em; padding-inline-start: 1.4em; }
.ed li { margin: .15em 0; padding-inline-start: .15em; }
.ed li::marker { color: var(--primary); }
.ed ol > li::marker { font-weight: 700; font-variant-numeric: tabular-nums; }
.ed blockquote {
    margin: .8em 0; padding: 2px 0; padding-inline-start: 14px; border-inline-start: 3px solid var(--primary);
    font-family: var(--serif); font-style: italic; font-size: 19px; line-height: 1.4; color: #3a2f5c;
}
.ed blockquote p { margin: 0; }
.ed a { color: #5b4bd6; text-decoration: underline; text-decoration-thickness: 1.5px; text-underline-offset: 2px; }
.ed hr {
    border: 0; height: 14px; margin: 1.1em 0;
    background: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 120 14'><path d='M2 7 C 12 1, 18 13, 28 7 S 44 1, 54 7 S 70 13, 80 7 S 96 1, 106 7 S 114 11, 118 7' fill='none' stroke='%237c6bf5' stroke-width='1.6' stroke-linecap='round' opacity='.6'/></svg>") center / 120px 14px no-repeat;
}
.ed img {
    display: block; max-width: 100%; height: auto; max-height: min(70vh, 640px); width: auto; object-fit: contain; margin: .8em 0; border-radius: 12px; background: var(--paper-2);
    box-shadow: 0 0 0 1px rgba(29,21,48,.06), 0 14px 30px -18px rgba(29,21,48,.6);
}
.ed img.is-uploading { opacity: .55; animation: breathe 1.2s ease-in-out infinite; }
/* ── The ink toolbar, docked under the paper (above the keyboard) ─────── */
.tools { flex-shrink: 0; background: var(--ink); padding: 6px 6px calc(6px + var(--kb-safe, var(--safe-b))); box-shadow: 0 -10px 24px -16px rgba(0,0,0,.6); }
.tools-row { display: flex; align-items: center; gap: 2px; overflow-x: auto; scrollbar-width: none; overscroll-behavior-x: contain; }
.tools-row::-webkit-scrollbar { display: none; }
.tool-glyph { display: inline-grid; place-items: center; line-height: 0; }
.tool-glyph .tool-ink { fill: #dc2626; }
.tool {
    flex: 0 0 auto; width: 40px; height: 40px; border-radius: 11px; display: grid; place-items: center;
    color: rgba(255,255,255,.78); font-size: 17px; touch-action: pan-x; transition: background .15s, color .15s, transform .15s var(--ease);
}
.tool:active { transform: scale(.9); background: rgba(255,255,255,.1); }
.tool.is-on { color: #fff; background: rgba(124,107,245,.38); box-shadow: inset 0 0 0 1px rgba(167,139,250,.6); }
.tool.is-accent { color: #fff; background: linear-gradient(150deg, #7c6bf5, #7c6bf5b0); margin-inline-end: 4px; }
.tool-sep { flex: 0 0 1px; height: 22px; margin: 0 4px; background: rgba(255,255,255,.14); }
.tools-sub { display: flex; align-items: center; gap: 10px; padding: 4px 6px 8px; overflow-x: auto; scrollbar-width: none; animation: rise .22s var(--spring); }
.tools-sub[hidden] { display: none; }
/* Phone: the bar shares the width evenly, so every tool is on screen; the
   formatting ("Aa") opens as a panel above it, wrapped onto as many lines as it needs. */
@media (max-width: 899px) {
    .tools-row > .tool { flex: 1 1 0; min-width: 0; max-width: 46px; }
    .tools-row { justify-content: space-between; }
    .tools-row > .tool.is-accent { margin-inline-end: 2px; }
    .tools-fmt { flex-wrap: wrap; justify-content: center; gap: 2px; overflow: visible; padding: 2px 2px 8px; }
    .tools-fmt .tool { width: 40px; height: 40px; }
    .tools-fmt .tool-sep { display: none; }
}
@media (min-width: 900px) { .tools-row > .tools-fmt { display: flex; align-items: center; gap: 2px; flex: 0 0 auto; padding: 0; overflow: visible; animation: none; } .tools-row > .tool.is-format { display: none; } }
.swatch { flex: 0 0 auto; width: 30px; height: 30px; border-radius: 50%; background: var(--sw); box-shadow: 0 0 0 2px var(--ink), 0 0 0 3.5px rgba(255,255,255,.35); transition: transform .15s var(--spring); }
.swatch:active { transform: scale(.88); }
.link-in { flex: 1; min-width: 0; height: 36px; border: 0; outline: none; border-radius: 10px; padding: 0 12px; background: rgba(255,255,255,.1); color: #fff; font: 500 16px var(--sans); }
.link-in::placeholder { color: rgba(255,255,255,.45); }
.tools-sub.is-link { flex-wrap: wrap; }
.link-in.is-text { flex-basis: 100%; order: 2; }
/* the emoji panel (above the tools, like the inks and the link box) */
.tools-sub.is-emoji { display: flex; flex-direction: column; align-items: stretch; gap: 6px; overflow: visible; }
.tools-sub.is-emoji[hidden] { display: none; }
.emo-panel { display: flex; flex-direction: column; gap: 6px; }
.emo-top { display: flex; gap: 8px; align-items: center; }
.emo-search { flex: 1; }
.emo-search::-webkit-search-cancel-button { filter: invert(1); }
.emo-tone { flex: 0 0 auto; width: 40px; height: 36px; border-radius: 10px; background: rgba(255,255,255,.1); font: 20px/36px 'Apple Color Emoji', 'Segoe UI Emoji', 'Noto Color Emoji', sans-serif; text-align: center; }
.emo-tabs { display: flex; justify-content: space-between; gap: 2px; overflow-x: auto; scrollbar-width: none; }
.emo-tab { flex: 1 0 34px; height: 34px; border-radius: 9px; color: rgba(255,255,255,.7); display: grid; place-items: center; font-size: 15px; }
.emo-tab:active, .emo-tab:hover { background: rgba(255,255,255,.1); color: #fff; }
.emo-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(42px, 1fr)); max-height: min(34vh, 260px); overflow-y: auto; overscroll-behavior: contain; scrollbar-width: thin; scrollbar-color: rgba(255,255,255,.25) transparent; position: relative; }
.emo { height: 42px; border-radius: 10px; font: 26px/42px 'Apple Color Emoji', 'Segoe UI Emoji', 'Noto Color Emoji', sans-serif; text-align: center; transition: transform .12s var(--spring, ease), background .12s; touch-action: pan-y; }
.emo:hover { background: rgba(255,255,255,.1); }
.emo:active { transform: scale(1.25); }
.emo-h { grid-column: 1 / -1; margin: 8px 4px 2px; font: 800 10.5px var(--sans); letter-spacing: .08em; text-transform: uppercase; color: rgba(255,255,255,.5); }
.emo-none { grid-column: 1 / -1; margin: 14px 4px; font: 500 13px var(--sans); color: rgba(255,255,255,.6); }
.tools-sub.is-link .link-sug { order: 3; }
.at-pop { position: fixed; z-index: 135; width: 288px; max-width: calc(100vw - 16px); padding: 6px; border-radius: 14px; background: #fff; color: #1d1530; box-shadow: 0 18px 40px -14px rgba(29,21,48,.55), 0 0 0 1px rgba(29,21,48,.07); animation: rise .16s var(--spring, ease); color-scheme: light; }
.at-head { padding: 4px 10px 6px; font: 800 10.5px var(--sans, system-ui); letter-spacing: .08em; text-transform: uppercase; color: #8a8199; }
.at-item { width: 100%; display: flex; align-items: center; gap: 9px; min-height: 38px; padding: 0 10px; border-radius: 10px; font: 600 14px var(--sans, system-ui); color: #1d1530; text-align: start; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }
.at-item .bi { color: #7c6bf5; flex-shrink: 0; }
.at-item.is-on, .at-item:hover { background: #ede9fe; }
.link-sug { flex-basis: 100%; display: flex; flex-direction: column; gap: 4px; padding-top: 6px; }
.link-sug[hidden] { display: none; }
.link-note { display: flex; align-items: center; gap: 9px; height: 38px; padding: 0 12px; border-radius: 10px; background: rgba(255,255,255,.08); color: #fff; font: 600 13.5px var(--sans); text-align: start; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }
.link-note .bi { color: #c4b8ff; }
.link-go { flex: 0 0 auto; height: 36px; padding: 0 14px; border-radius: 10px; background: #fff; color: var(--ink); font-size: 13px; font-weight: 700; }

@keyframes breathe { 50% { opacity: .35; } }
@keyframes spin { to { transform: rotate(360deg); } }


/* ── Writing direction, paragraph by paragraph ─────────────────────────
   A paragraph with no direction set finds its own from its first letters
   (Arabic right, English left); one set with the LTR / RTL tools keeps it. */
.ed :is(p, h1, h2, h3, h4, li, blockquote, div):not([dir]):not(.clip):not(.clip *) { unicode-bidi: plaintext; text-align: start; }
.ed [dir] { text-align: start; }
.ed blockquote[dir=rtl] { border-inline-start: 3px solid var(--primary); }

/* ── Recordings in the text ───────────────────────────────────────────── */
.ed .clip, .clip { position: relative; margin: .8em 0; border-radius: 14px; background: #fff; color: var(--ink); user-select: none; -webkit-user-select: none;
    box-shadow: 0 0 0 1px rgba(29,21,48,.07), 0 12px 26px -18px rgba(29,21,48,.6); }
.clip.is-audio { display: flex; align-items: center; gap: 10px; padding: 9px 40px 9px 9px; }
[dir=rtl] .clip.is-audio { padding: 9px 9px 9px 40px; }
.clip-play { width: 38px; height: 38px; border-radius: 50%; flex-shrink: 0; display: grid; place-items: center; background: #e0457b; color: #fff; font-size: 17px; transition: transform .15s; }
.clip-play:active { transform: scale(.9); }
.clip-play .bi-play-fill { margin-inline-start: 2px; }
.clip-wave { flex: 1; min-width: 0; height: 30px; display: flex; align-items: center; gap: 2px; overflow: hidden; cursor: pointer; }
.clip-wave i { flex: 1 1 0; min-width: 2px; max-width: 4px; border-radius: 3px; background: #e0457b; opacity: .32; transition: opacity .15s; }
.clip-wave i.is-on { opacity: 1; }
.clip-time { font-size: 11.5px; font-weight: 700; color: var(--ink-soft); font-variant-numeric: tabular-nums; flex-shrink: 0; }
.clip.is-video { overflow: hidden; background: #0b0812; }
.clip-video { display: block; width: 100%; max-height: 70vh; background: #0b0812; }
.ed .clip-wait, .clip-wait { position: absolute; margin: 0; inset: 0 0 auto 0; padding: 12px 48px 22px 12px; display: none; align-items: center; gap: 8px; font-size: 12px; font-weight: 600; color: #fff;
    background: linear-gradient(rgba(0,0,0,.72), transparent); pointer-events: none; }
[dir=rtl] .clip-wait { padding: 12px 12px 22px 48px; }
.clip.is-preparing .clip-wait, .clip.is-failed .clip-wait { display: flex; }
.clip-spin { width: 13px; height: 13px; border-radius: 50%; border: 2px solid rgba(255,255,255,.85); border-top-color: transparent; animation: spin .8s linear infinite; }
.clip.is-failed .clip-spin { display: none; }
.clip.is-failed.is-audio .clip-play { background: #94a3b8; }
.clip-x { position: absolute; top: 50%; inset-inline-end: 7px; transform: translateY(-50%); width: 28px; height: 28px; border-radius: 50%; display: grid; place-items: center;
    font-size: 12px; color: var(--ink-soft); background: rgba(29,21,48,.05); }
.clip.is-video .clip-x { top: 8px; transform: none; color: #fff; background: rgba(0,0,0,.45); z-index: 2; }
.clip.is-uploading { animation: breathe 1.2s ease-in-out infinite; }
/* a video from another site: its cover and a play button, the site's player only once tapped */
.clip.is-embed { overflow: hidden; background: #0b0812; max-width: 380px; } /* a card in the text, not a screen: it never runs the page's full width */
.embed-stage { position: relative; aspect-ratio: 16 / 9; display: grid; place-items: center; overflow: hidden;
    background: radial-gradient(120% 90% at 20% 0%, #7c6bf5 0%, #7c6bf5b0 35%, #0b0812 100%); }
.ed .embed-cover, .embed-cover { position: absolute; inset: 0; width: 100%; height: 100%; max-width: none; max-height: none; margin: 0; border-radius: 0; object-fit: cover; box-shadow: none; background: none; transition: transform .5s ease, filter .3s; }
.embed-stage::after { content: ''; position: absolute; inset: 0; background: linear-gradient(transparent 55%, rgba(0,0,0,.6)); pointer-events: none; }
.clip.is-embed:hover .embed-cover { transform: scale(1.03); filter: brightness(.9); }
.embed-play { position: relative; z-index: 1; width: 52px; height: 52px; border-radius: 50%; display: grid; place-items: center; font-size: 24px; color: #fff;
    background: rgba(255,255,255,.18); border: 1px solid rgba(255,255,255,.35); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px);
    box-shadow: 0 14px 30px -12px rgba(0,0,0,.7); transition: transform .18s ease, background .18s; }
.embed-play .bi-play-fill { margin-inline-start: 4px; }
.embed-play:hover { background: rgba(255,255,255,.28); transform: scale(1.06); }
.embed-play:active { transform: scale(.92); }
.ed .embed-site, .embed-site { position: absolute; z-index: 1; bottom: 10px; inset-inline-start: 12px; margin: 0; display: inline-flex; align-items: center; gap: 6px;
    padding: 4px 10px; border-radius: 999px; font-size: 11.5px; font-weight: 700; color: #fff; background: rgba(0,0,0,.45); backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px); }
.clip.is-youtube .embed-site .bi { color: #ff4d4d; }
.clip.is-vimeo .embed-site .bi { color: #5fd3ff; }
.embed-open { position: absolute; z-index: 1; bottom: 8px; inset-inline-end: 10px; width: 32px; height: 32px; border-radius: 50%; display: grid; place-items: center;
    font-size: 13px; color: #fff; background: rgba(0,0,0,.45); text-decoration: none; }
/* Instagram and TikTok are phone-shaped: a tall card, not a wide one */
.clip.is-embed.is-tall { max-width: 220px; }
.clip.is-embed.is-instagram.is-playing { max-width: 340px; } /* Instagram's own player needs ~326px to draw itself */
.clip.is-embed { transition: max-width .25s ease; }
.clip.is-tall .embed-stage { aspect-ratio: 9 / 16; }
.clip.is-instagram .embed-stage { background: linear-gradient(150deg, #f58529, #dd2a7b 50%, #8134af); }
.clip.is-tiktok .embed-stage { background: radial-gradient(90% 60% at 25% 20%, #25f4ee55, transparent 60%), radial-gradient(90% 60% at 80% 85%, #fe2c5555, transparent 60%), #0b0812; }
.clip.is-instagram .embed-site .bi, .clip.is-tiktok .embed-site .bi { color: #fff; }
.embed-mark { position: absolute; inset: 0; display: grid; place-items: center; font-size: 96px; color: rgba(255,255,255,.12); pointer-events: none; }
.clip.is-tall .embed-mark { font-size: 110px; }
.clip.is-resolving .embed-site { inset-inline-start: 50%; bottom: 50%; transform: translate(-50%, 50%); gap: 8px; }
[dir=rtl] .clip.is-resolving .embed-site { transform: translate(50%, 50%); }
.clip.is-resolving { animation: breathe 1.2s ease-in-out infinite; }
.embed-frame { position: absolute; inset: 0; z-index: 3; width: 100%; height: 100%; border: 0; background: #000; }
.clip.is-embed .clip-x { top: 8px; transform: none; color: #fff; background: rgba(0,0,0,.45); z-index: 4; }
.clip.is-embed .clip-grip { position: absolute; z-index: 4; top: 8px; inset-inline-start: 8px; margin: 0; width: 32px; height: 32px; border-radius: 50%; color: #fff; background: rgba(0,0,0,.45); }
.ed[contenteditable=false] .clip.is-embed .clip-x { display: none; }
@media (prefers-reduced-motion: reduce) { .embed-cover, .embed-play { transition: none; } .clip.is-embed:hover .embed-cover { transform: none; } }
.clip.is-uploading .clip-x { visibility: hidden; }
.tool.is-rose { background: linear-gradient(150deg, #e0457b, #e0457bb0); }
.tool.is-teal { background: linear-gradient(150deg, #16a394, #16a394b0); }
.tool.is-sky { background: linear-gradient(150deg, #3b9ae1, #3b9ae1b0); }

/* Moving a recording or a picture */
.clip-grip { flex-shrink: 0; width: 26px; height: 38px; margin-inline-start: -4px; border-radius: 8px; display: grid; place-items: center; font-size: 17px; color: var(--ink-soft);
    cursor: grab; touch-action: none; background: none; border: 0; }
.clip-grip:active { cursor: grabbing; background: rgba(29,21,48,.06); }
.clip.is-video .clip-grip { position: absolute; z-index: 2; top: 8px; inset-inline-start: 8px; margin: 0; width: 32px; height: 32px; border-radius: 50%; color: #fff; background: rgba(0,0,0,.45); }
.ed[contenteditable=false] .clip-grip { display: none; }
.ed[contenteditable=false] .clip.is-audio { padding-inline-end: 12px; }
.is-moving { opacity: .3; }
.move-ghost { position: fixed; z-index: 300; left: 0; top: 0; pointer-events: none; opacity: .92; border-radius: 12px; box-shadow: 0 24px 40px -14px rgba(0,0,0,.55), 0 0 0 2px #7c6bf5;
    transition: none !important; animation: none !important; }
img.move-ghost { height: auto; }
.drop-line { position: absolute; z-index: 5; height: 4px; margin-top: -2px; border-radius: 4px; background: #7c6bf5; box-shadow: 0 0 0 4px rgba(124,107,245,.2); pointer-events: none; }
.drop-line::before, .drop-line::after { content: ''; position: absolute; top: -4px; width: 12px; height: 12px; border-radius: 50%; background: #7c6bf5; }
.drop-line::before { left: -6px; } .drop-line::after { right: -6px; }
.img-chips { position: absolute; z-index: 3; display: flex; gap: 6px; }
.img-chip.is-move { touch-action: none; cursor: grab; }

/* "Mark up" on a tapped picture */
.img-chip { height: 34px; padding: 0 13px; border-radius: 999px; display: inline-flex; align-items: center; gap: 7px;
    background: rgba(29,21,48,.86); color: #fff; font: 700 12.5px var(--sans); border: 1px solid rgba(255,255,255,.2);
    -webkit-backdrop-filter: blur(6px); backdrop-filter: blur(6px); box-shadow: 0 10px 20px -8px rgba(0,0,0,.6); animation: rise .2s var(--spring); }
.img-chip:active { transform: scale(.95); }
/* a checklist: a box at each item's start, a tick and a quieter line once done */
.ed ul[data-check] { list-style: none; padding-inline-start: 0; }
.ed ul[data-check] > li { position: relative; padding-inline-start: 30px; margin: .2em 0; }
.ed ul[data-check] > li::before { content: ""; position: absolute; inset-inline-start: 2px; top: .28em; width: 18px; height: 18px; box-sizing: border-box; border: 2px solid #9b8ff0; border-radius: 6px;
    background: #fff center / 12px 12px no-repeat; cursor: pointer; transition: background-color .15s, border-color .15s; }
.ed ul[data-check] > li[data-checked]::before { background-color: #6d5ce8; border-color: #6d5ce8;
    background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'><path d='M3 8.5l3.2 3L13 4.5' fill='none' stroke='white' stroke-width='2.4' stroke-linecap='round' stroke-linejoin='round'/></svg>"); }
.ed ul[data-check] > li[data-checked] { color: #8a8199; text-decoration: line-through; text-decoration-color: rgba(138,129,153,.6); }
/* a link drawn as a button (the link bar's "Button") — reading and writing alike */
.ed a[data-look="button"] { display: inline-block; margin: .2em 0; padding: .5em 1.15em; border-radius: 12px; background: #6d5ce8; color: #fff; text-decoration: none; font-weight: 700; line-height: 1.3;
    box-shadow: 0 8px 18px -10px rgba(109,92,232,.9); transition: transform .15s, box-shadow .15s; }
.ed a[data-look="button"]:hover { box-shadow: 0 10px 22px -10px rgba(109,92,232,1); }
.ed a[data-look="button"]:active { transform: scale(.97); }
.link-chips { flex-wrap: wrap; }
.img-chip.is-on { background: #6d5ce8; border-color: transparent; }
.ed[contenteditable="false"] ~ .link-chips { display: none; }
.img-chip.is-del { padding: 0; width: 34px; justify-content: center; background: rgba(185,28,28,.88); }

/* Cropping a picture: the photo on a dark sheet, the cut lit, the rest dimmed */
.crop-sheet { position: fixed; inset: 0; z-index: 2147483000; display: flex; flex-direction: column; background: #0d0916; color: #fff; opacity: 0; transition: opacity .2s; color-scheme: dark;
    padding: env(safe-area-inset-top, 0px) env(safe-area-inset-right, 0px) env(safe-area-inset-bottom, 0px) env(safe-area-inset-left, 0px); }
.crop-sheet.is-ready { opacity: 1; }
.crop-sheet.is-gone { opacity: 0; pointer-events: none; }
.crop-bar { flex-shrink: 0; display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 10px 12px; }
.crop-title { display: inline-flex; align-items: center; gap: 8px; font: 700 14px var(--sans); }
.crop-right { display: inline-flex; gap: 8px; }
.crop-btn { height: 38px; padding: 0 16px; border-radius: 999px; border: 1px solid rgba(255,255,255,.18); background: rgba(255,255,255,.08); color: #fff; font: 700 13.5px var(--sans); cursor: pointer; }
.crop-btn.is-ghost { width: 38px; padding: 0; display: grid; place-items: center; }
.crop-btn.is-go { background: var(--violet, #7c6bf5); border-color: transparent; }
.crop-btn[disabled] { opacity: .45; cursor: default; }
.crop-stage { flex: 1; min-height: 0; display: grid; place-items: center; overflow: hidden; }
.crop-frame { position: relative; touch-action: none; user-select: none; -webkit-user-select: none; }
.crop-frame img { display: block; width: 100%; height: 100%; pointer-events: none; }
.crop-box { position: absolute; box-shadow: 0 0 0 9999px rgba(0,0,0,.58); outline: 2px solid #fff; cursor: move;
    background-image: linear-gradient(rgba(255,255,255,.35), rgba(255,255,255,.35)), linear-gradient(rgba(255,255,255,.35), rgba(255,255,255,.35)), linear-gradient(90deg, rgba(255,255,255,.35), rgba(255,255,255,.35)), linear-gradient(90deg, rgba(255,255,255,.35), rgba(255,255,255,.35));
    background-size: 100% 1px, 100% 1px, 1px 100%, 1px 100%; background-position: 0 33.33%, 0 66.66%, 33.33% 0, 66.66% 0; background-repeat: no-repeat; }
.crop-h { position: absolute; width: 28px; height: 28px; margin: -14px; }
.crop-h::after { content: ""; position: absolute; inset: 9px; border-radius: 50%; background: #fff; box-shadow: 0 1px 4px rgba(0,0,0,.5); }
.crop-h.is-nw { left: 0; top: 0; cursor: nwse-resize; } .crop-h.is-se { left: 100%; top: 100%; cursor: nwse-resize; }
.crop-h.is-ne { left: 100%; top: 0; cursor: nesw-resize; } .crop-h.is-sw { left: 0; top: 100%; cursor: nesw-resize; }
.crop-h.is-n { left: 50%; top: 0; cursor: ns-resize; } .crop-h.is-s { left: 50%; top: 100%; cursor: ns-resize; }
.crop-h.is-e { left: 100%; top: 50%; cursor: ew-resize; } .crop-h.is-w { left: 0; top: 50%; cursor: ew-resize; }
.crop-h:is(.is-n, .is-s, .is-e, .is-w)::after { border-radius: 3px; }
.crop-h:is(.is-n, .is-s)::after { inset: 11px 6px; } .crop-h:is(.is-e, .is-w)::after { inset: 6px 11px; }
/* ── Lists inside lists (Indent / Tab) ─────────────────────────────── */
.ed li > ul, .ed li > ol { margin: .15em 0 .15em; }
.ed ul ul { list-style-type: circle; } .ed ul ul ul { list-style-type: square; }
.ed ol ol { list-style-type: lower-alpha; } .ed ol ol ol { list-style-type: lower-roman; }
.ed ul[data-check] ul[data-check] { padding-inline-start: 22px; }

/* ── Tables ───────────────────────────────────────────────────────────── */
/* the table scrolls sideways on its own when it is wider than the page; the page never does */
.ed table { display: block; max-width: 100%; overflow-x: auto; margin: .9em 0; border-collapse: separate; border-spacing: 0; border-radius: 12px;
    box-shadow: 0 0 0 1px rgba(29,21,48,.1), 0 12px 26px -20px rgba(29,21,48,.55); background: #fff; }
.ed table > tbody, .ed table > thead { display: table; width: 100%; border-collapse: collapse; }
.ed td, .ed th { min-width: 64px; padding: 8px 11px; vertical-align: top; text-align: start; border: 1px solid rgba(29,21,48,.1); line-height: 1.45; transition: background-color .15s, box-shadow .15s; }
.ed tr:first-child > * { border-top: 0; } .ed tr:last-child > * { border-bottom: 0; }
.ed tr > :first-child { border-inline-start: 0; } .ed tr > :last-child { border-inline-end: 0; }
.ed th { font-weight: 800; font-size: .92em; color: #4c3fc4; background: rgba(124,107,245,.1); }
/* the looks */
.ed table[data-look="stripes"] td, .ed table[data-look="stripes"] th { border-inline: 0; border-color: rgba(29,21,48,.06); }
.ed table[data-look="stripes"] tr:nth-child(even) > td:not([data-fill]) { background: rgba(124,107,245,.05); }
.ed table[data-look="plain"] { box-shadow: none; background: transparent; border-radius: 0; }
.ed table[data-look="plain"] td, .ed table[data-look="plain"] th { border: 0; border-bottom: 1px solid rgba(29,21,48,.1); }
.ed table[data-look="plain"] th { background: transparent; color: var(--ink); border-bottom: 2px solid #7c6bf5; }
/* a cell's shade */
.ed [data-fill="1"] { background: rgba(124,107,245,.16); } .ed [data-fill="2"] { background: rgba(224,69,123,.14); }
.ed [data-fill="3"] { background: rgba(245,158,11,.18); } .ed [data-fill="4"] { background: rgba(16,185,129,.16); }
.ed [data-fill="5"] { background: rgba(59,154,225,.16); } .ed [data-fill="6"] { background: rgba(29,21,48,.08); }
/* cells picked for a merge, a shade or an alignment */
.ed td.is-sel, .ed th.is-sel { box-shadow: inset 0 0 0 2px #7c6bf5; background-image: linear-gradient(rgba(124,107,245,.14), rgba(124,107,245,.14)); }
.ed table.is-picking { user-select: none; -webkit-user-select: none; }

/* the table's bar: one row, scrolling sideways on a phone */
.tbl-bar { flex-wrap: nowrap; align-items: center; max-width: calc(100% - 16px); overflow-x: auto; scrollbar-width: none; padding: 4px; border-radius: 999px;
    background: rgba(29,21,48,.9); -webkit-backdrop-filter: blur(8px); backdrop-filter: blur(8px); box-shadow: 0 14px 28px -12px rgba(0,0,0,.6); animation: rise .2s var(--spring); }
.tbl-bar::-webkit-scrollbar { display: none; }
.tbl-bar .img-chip { flex-shrink: 0; height: 32px; padding: 0 11px; background: transparent; border-color: transparent; box-shadow: none; animation: none; backdrop-filter: none; -webkit-backdrop-filter: none; white-space: nowrap; }
.tbl-bar .img-chip[aria-label] { padding: 0; width: 32px; justify-content: center; }
.tbl-bar .img-chip:hover { background: rgba(255,255,255,.1); }
.tbl-bar .img-chip.is-on { background: #6d5ce8; }
.tbl-bar .img-chip.is-danger { color: #fca5a5; }
.tbl-bar .img-chip.is-danger:hover { background: rgba(185,28,28,.5); color: #fff; }
.tbl-sep { flex-shrink: 0; width: 1px; height: 20px; margin: 0 3px; background: rgba(255,255,255,.18); }
.tbl-fill { flex-shrink: 0; width: 22px; height: 22px; margin: 0 2px; border-radius: 50%; border: 2px solid rgba(255,255,255,.35); display: grid; place-items: center; color: rgba(255,255,255,.7); font-size: 12px; transition: transform .15s; }
.tbl-fill:hover { transform: scale(1.12); }
.tbl-fill[data-fill="1"] { background: #a99df5; } .tbl-fill[data-fill="2"] { background: #f08bb0; } .tbl-fill[data-fill="3"] { background: #f9c66b; }
.tbl-fill[data-fill="4"] { background: #6fd7b0; } .tbl-fill[data-fill="5"] { background: #8cc4ee; } .tbl-fill[data-fill="6"] { background: #b6b1c0; }
.tbl-fill.is-none { background: transparent; }
[dir=rtl] .is-flip .bi { transform: scaleX(-1); } /* kept apart from :dir() — one unknown selector would drop the whole rule */
.ed[contenteditable="false"] ~ .tbl-bar { display: none; }

/* the size picker: columns × rows */
.tools-sub.is-table { flex-direction: column; align-items: center; gap: 8px; padding: 6px 6px 10px; }
.tpick-size { font: 700 13px var(--sans); color: #fff; font-variant-numeric: tabular-nums; min-height: 18px; }
.tpick-grid { display: grid; grid-template-columns: repeat(6, 30px); gap: 5px; }
.tpick-cell { width: 30px; height: 30px; border-radius: 7px; background: rgba(255,255,255,.1); border: 1px solid rgba(255,255,255,.18); transition: background-color .12s, transform .12s; }
.tpick-cell.is-on { background: #7c6bf5; border-color: #a99df5; }
.tpick-cell:active { transform: scale(.9); }
@media (prefers-reduced-motion: reduce) { .tbl-bar { animation: none; } .ed td, .ed th, .tbl-fill, .tpick-cell { transition: none; } }

/* ── Code (from Markdown) ─────────────────────────────────────────────── */
.ed code { padding: .1em .4em; border-radius: 6px; background: rgba(124,107,245,.1); color: #4c3fc4; font: 500 .88em ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
.ed pre { margin: .8em 0; padding: 12px 14px; border-radius: 12px; background: #1d1530; color: #ece9f7; overflow-x: auto; white-space: pre; tab-size: 4;
    font: 500 13px/1.55 ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; box-shadow: 0 12px 26px -20px rgba(29,21,48,.7); }
.ed pre code { padding: 0; background: none; color: inherit; font: inherit; }
</style>
