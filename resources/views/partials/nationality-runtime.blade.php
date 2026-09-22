{{--
    Paints every `.nationality-display[data-iso3]` on the page: our own flag,
    then the country's full name.

    ── Why this partial exists ───────────────────────────────────────────────
    Six admin screens each carried their OWN near-identical copy of this: a
    fetch of /data/countries.json, then a flag built from the ISO-2 code by
    codepoint arithmetic. That emoji renders as the letters "BH" on Windows, so
    every one of the six was wrong in the same way and had to be fixed six
    times. Now: one runtime, no fetch (the map is handed over server-side), and
    the flag is the same FILE `<x-flag>` draws (CLAUDE.md → Flags Come From One
    Local Source).

    ── Using it ──────────────────────────────────────────────────────────────
    Mark the cell and include this once:

        <span class="nationality-display" data-iso3="{{ $user->nationality }}">{{ $user->nationality ?? '—' }}</span>
        @include('partials.nationality-runtime')

    It paints on first load, after an admin-shell navigation, and on demand for
    a region swapped in by AJAX:

        window.paintNationalities(el);          // el defaults to document

    A code we hold no file for is left exactly as the server rendered it — the
    code itself, which is still a fact about where somebody is from.
--}}
@once
@push('scripts')
<script>
(function () {
    if (window.paintNationalities) return;

    /* code (alpha-2 AND alpha-3, upper) => [iso2, name]. Server-side, so no
       page pays for a countries.json fetch to draw a flag. */
    const MAP = @json(\App\Support\Flag::map());

    window.paintNationalities = function (root) {
        (root || document).querySelectorAll('.nationality-display[data-iso3]').forEach(el => {
            const code = (el.getAttribute('data-iso3') || '').trim().toUpperCase();
            const hit  = MAP[code];

            if (! hit) return;   // no file: leave the code the server rendered

            // Rebuilt rather than patched, so painting twice is harmless.
            el.textContent = '';
            el.classList.add('inline-flex', 'items-center', 'gap-1.5');

            const flag = document.createElement('span');
            flag.className = 'fi fi-' + hit[0] + ' rounded-[2px] flex-shrink-0';
            el.appendChild(flag);

            // textContent, never innerHTML: a country name is data.
            el.appendChild(document.createTextNode(hit[1]));
        });
    };

    const paint = () => window.paintNationalities(document);

    document.addEventListener('DOMContentLoaded', paint);
    window.addEventListener('shell:navigated', paint);

    // Already past DOMContentLoaded when this ran (an admin-shell swap
    // re-inserts the script after the fact).
    if (document.readyState !== 'loading') paint();
})();
</script>
@endpush
@endonce
