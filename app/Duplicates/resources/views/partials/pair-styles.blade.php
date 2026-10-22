{{-- The pair card's own styles (partials/pair-card). Included once per page, OUTSIDE the x-for. --}}
@once
<style>
    .dup-pair { display: grid; grid-template-columns: 1fr; gap: .5rem; align-items: stretch; }
    @media (min-width: 640px) { .dup-pair { grid-template-columns: 1fr auto 1fr; } }
    .dup-link { width: 2.25rem; height: 2.25rem; border-radius: 9999px; display: grid; place-items: center; font-weight: 900; font-size: 1.05rem; justify-self: center; align-self: center; }
    .dup-side { min-width: 0; border-radius: .9rem; padding: .7rem .8rem; display: flex; align-items: center; gap: .7rem; background: hsl(220 15% 97%); border: 1px solid hsl(220 15% 92%); transition: border-color .15s ease, background .15s ease; }
    a.dup-side:hover { border-color: hsl(250 65% 65% / .45); background: hsl(250 60% 97%); }
    .dup-card { transition: opacity .25s ease; }
    .dup-card.is-done { opacity: .78; }
    @media (prefers-reduced-motion: reduce) { .dup-card, .dup-side { transition: none; } }
</style>
@endonce
