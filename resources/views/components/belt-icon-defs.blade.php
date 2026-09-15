@once
    @php
        /*
         * ONE belt, for every belt a page draws in JAVASCRIPT.
         *
         * A list built client-side — the division people picker's roster, a
         * card patched in place after a save — cannot call a Blade component
         * per row, and hand-writing the rects into a JS string would be a
         * second copy of the drawing that quietly drifts from the first
         * (CLAUDE.md, "Shared Stays Shared"). So the geometry is printed ONCE
         * here as a <symbol>, and every instance is:
         *
         *     <svg class="w-5 h-5" style="--belt-band: #2563eb; --belt-knot: #1d4ed8">
         *         <use href="#belt-icon"></use>
         *     </svg>
         *
         * with the style string coming from the server
         * (App\Sports\Combat\BeltIcon::payload), so the colour maths is not
         * re-implemented in JS either.
         *
         * Custom properties are what make this work: they inherit through
         * <use>, so one symbol draws every rank. A property left unset falls
         * back to `transparent`, which is how "no tip bar" and "no outline" are
         * said without a second symbol.
         *
         * Include it once on any page whose belts are drawn by script.
         * <x-belt-icon> does NOT need it — that one renders inline so it stays
         * standalone wherever it is dropped.
         */
        $vars = [
            'band' => 'var(--belt-band, currentColor)',
            'knot' => 'var(--belt-knot, currentColor)',
            'tip' => 'var(--belt-tip, transparent)',
        ];

        $ink = ['band' => 'var(--belt-outline, transparent)', 'knot' => 'var(--belt-outline, transparent)', 'tip' => 'var(--belt-outline, transparent)'];
    @endphp

    {{-- Out of the flow entirely: it is a definition, not a picture. `absolute`
         rather than `display:none` because a hidden ancestor makes <use>
         references unresolvable in some engines. --}}
    <svg width="0" height="0" aria-hidden="true" focusable="false"
         style="position:absolute;width:0;height:0;overflow:hidden">
        <symbol id="belt-icon" viewBox="0 0 24 24" fill="none">
            {!! \App\Sports\Combat\BeltIcon::shape($ink, 'stroke="var(--belt-outline, transparent)" stroke-width="1.1" stroke-linejoin="round"') !!}
            {!! \App\Sports\Combat\BeltIcon::shape($vars) !!}
        </symbol>
    </svg>
@endonce
