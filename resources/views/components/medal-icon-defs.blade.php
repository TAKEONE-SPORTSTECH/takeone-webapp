{{--
    THE THREE MEDALS AS `<symbol>`s, for a medal drawn by JAVASCRIPT.

    `x-medal-icon` renders its drawing inline, which is what keeps it
    standalone — drop it anywhere and it needs nothing on the page. That only
    works where BLADE is doing the drawing.

    The bracket canvas is not: it builds every node in JS, positions them in its
    own coordinate space and puts ONE transform on the canvas so the whole board
    pans and zooms together. A card built there cannot call a Blade component.

    So the shapes are emitted once per page as symbols, and the runtime writes
    `<svg><use href="#medal-place-1"></use></svg>`. Same geometry, same colours,
    from the same file — `App\Support\Medal::shapes()` — so the medal on the
    board and the medal in a sheet can never become two different medals.

    Include it once on any page whose JS draws medals; `@once` makes twice free.
    `x-medal-icon` does NOT need it.

    Same arrangement, and the same reason, as `x-belt-icon-defs`.
--}}
@once
<svg width="0" height="0" aria-hidden="true" focusable="false"
     style="position:absolute;width:0;height:0;overflow:hidden">
    @foreach([1, 2, 3] as $place)
        <symbol id="medal-place-{{ $place }}" viewBox="{{ \App\Support\Medal::viewBox($place) }}">
            {!! \App\Support\Medal::shapes($place) !!}
        </symbol>
    @endforeach
</svg>
@endonce
