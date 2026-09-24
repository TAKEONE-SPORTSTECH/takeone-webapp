{{-- Federation admin — the few visuals the prebuilt Tailwind bundle does not carry. Scoped to .fedx. --}}
<style>
  .fedx-seal { position: relative; display: flex; align-items: center; gap: .75rem; padding: .85rem 1rem; border-radius: 1rem;
    background: #fff; border: 1px solid hsl(220 15% 92%); transition: border-color .25s ease, box-shadow .25s ease, transform .25s ease; }
  .fedx-seal-icon { width: 2.6rem; height: 2.6rem; border-radius: 999px; display: grid; place-items: center; flex: none;
    background: hsl(220 15% 95%); color: hsl(220 10% 55%); font-size: 1.1rem; position: relative; }
  .fedx-seal-icon svg { position: absolute; inset: -4px; width: calc(100% + 8px); height: calc(100% + 8px); transform: rotate(-90deg); }
  .fedx-seal-icon circle { fill: none; stroke-width: 3; }
  .fedx-seal-icon .trk { stroke: hsl(220 15% 90%); }
  .fedx-seal-icon .arc { stroke: hsl(250 65% 65%); stroke-linecap: round; transition: stroke-dashoffset .6s cubic-bezier(.22,.68,.35,1); }
  .fedx-seal.is-done { border-color: hsl(250 60% 85%); box-shadow: 0 8px 24px -12px hsl(250 65% 55% / .45); }
  .fedx-seal.is-done .fedx-seal-icon { background: hsl(250 65% 65%); color: #fff; }
  .fedx-seal.is-done .arc { stroke: hsl(250 70% 45%); }
  .fedx-seal-link { position: absolute; top: 50%; inset-inline-end: -0.9rem; width: 1.8rem; height: 2px; background: hsl(220 15% 88%); z-index: 1; }
  .fedx-seal.is-done .fedx-seal-link { background: hsl(250 65% 65%); }
  .fedx-chapter-no { font-variant-numeric: tabular-nums; font-weight: 800; letter-spacing: .08em; font-size: .7rem; color: hsl(250 45% 60%); }
  .fedx-dot { width: .55rem; height: .55rem; border-radius: 999px; flex: none; }
  .fedx-dot.ok { background: #16a34a; box-shadow: 0 0 0 3px #16a34a22; }
  .fedx-dot.todo { background: #f59e0b; box-shadow: 0 0 0 3px #f59e0b22; }
  .fedx-dot.opt { background: hsl(220 15% 82%); }
  .fedx-card-in { animation: fedxIn .45s cubic-bezier(.22,.68,.35,1) both; }
  @keyframes fedxIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: none; } }
  .fedx-crest { width: 4.5rem; height: 4.5rem; flex: none; display: grid; place-items: center; }
  .fedx-crest img { width: 100%; height: 100%; object-fit: contain; }
  .fedx-crest-fallback { width: 4.5rem; height: 4.5rem; border-radius: 1.25rem; display: grid; place-items: center;
    font-weight: 900; font-size: 1.15rem; letter-spacing: .04em; color: #fff; background: rgba(255,255,255,.18); border: 1px solid rgba(255,255,255,.3); }
  /* Quick create — a numbered marker per fact that turns into a check once it is given. */
  .fedx-q-step { width: 1.75rem; height: 1.75rem; margin-top: 1.6rem; border-radius: 999px; flex: none; display: grid; place-items: center;
    font-size: .75rem; font-weight: 800; color: hsl(250 45% 60%); background: hsl(250 60% 96%); border: 1px solid hsl(250 60% 88%);
    transition: background-color .25s ease, color .25s ease, border-color .25s ease, transform .25s cubic-bezier(.34,1.56,.64,1); }
  .fedx-q-step > * { grid-area: 1 / 1; transition: opacity .2s ease, transform .25s ease; }
  .fedx-q-step i { opacity: 0; transform: scale(.4); }
  .fedx-q-step.is-done { background: hsl(250 65% 65%); color: #fff; border-color: hsl(250 65% 65%); transform: scale(1.05); }
  .fedx-q-step.is-done span { opacity: 0; transform: scale(.4); }
  .fedx-q-step.is-done i { opacity: 1; transform: none; }
  .fedx-q-crest { width: 5.5rem; height: 5.5rem; font-size: 1.5rem; border-radius: 1.5rem; }
  .fedx-nudge .fedx-seal-icon { background: hsl(250 60% 96%); color: hsl(250 65% 60%); }
  @media (prefers-reduced-motion: reduce) { .fedx-q-step, .fedx-q-step > * { transition: none; } }
  .fedx-chip-toggle { transition: background-color .15s ease, color .15s ease, border-color .15s ease, transform .1s ease; }
  .fedx-chip-toggle:active { transform: scale(.96); }
  .fedx-iban { font-family: ui-monospace, Menlo, monospace; letter-spacing: .08em; }
  .fedx-swatch { width: 2.75rem; height: 2.75rem; border-radius: .9rem; border: 1px solid hsl(220 15% 88%); padding: 0; cursor: pointer; background: none; }
  .fedx-swatch::-webkit-color-swatch-wrapper { padding: 3px; } .fedx-swatch::-webkit-color-swatch { border: 0; border-radius: .7rem; }
  /* Not in the prebuilt Tailwind bundle. */
  @media (min-width: 1024px) { .fedx-main { grid-column: span 9 / span 9; } }
  @media (prefers-reduced-motion: reduce) { .fedx-card-in { animation: none; } .fedx-seal-icon .arc { transition: none; } }
</style>
