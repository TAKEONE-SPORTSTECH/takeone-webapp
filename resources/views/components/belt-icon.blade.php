@props([
    /* THE RANK AS THE SYSTEM RECORDED IT — the data-driven way in.
       Accepts every shape a belt travels in on this platform:
         · a ladder value                 'blue'
         · a weigh-in column              $registration->belt_colour
         · what BeltRank::for() returns   ['colour' => 'Black', 'grade' => '2nd Dan', …]
       Resolution goes through App\Sports\Combat\BeltRank::chip(), the same call
       the belt chip makes, so the two can never drift apart. Nothing renders
       when no rank is on file — a blank is the normal case here, not a failure
       (CLAUDE.md, "Who Fills The Form Decides"). */
    'belt' => null,

    /* The degree as an organiser typed it ('2', '2nd Dan'), when it is held
       apart from the colour. Ignored if `belt` already carries one. */
    'grade' => null,

    /* A colour chosen by hand rather than read off a record — a ladder value or
       any hex. This is the DECORATIVE door: a hex always draws, so an empty
       state or a menu entry can show a belt that belongs to nobody. `belt`
       wins when both are given. */
    'color' => 'white',

    /* The rank bar at the tails' ends — the black tip a jiu-jitsu belt carries,
       or the red one on a black belt. null (the default) draws the tails in the
       belt's own colour, which is what most striking arts look like. */
    'tip' => null,

    /* The silhouette outline, for a surface the automatic one is wrong on.
       Left null, a PALE belt is outlined in ink and every other belt is drawn
       bare — which is right on a white card. Given a colour, every belt is
       outlined in it: that is what a DARK card needs, where a black belt
       disappears as readily as a white one does on white. */
    'outline' => null,

    /* A name for the icon. Given, the SVG is announced to a screen reader as an
       image with this label; omitted, it is hidden from one — which is right
       when the rank is already written beside it in words. Passing `belt`
       without a title labels it with the rank's own wording; passing `false`
       forces it silent, which is right beside a chip that already says the
       rank in words — one thing, announced once. */
    'title' => null,
])

@php
    /*
     * A tied martial-arts belt, in any colour.
     *
     * Everything about the drawing — the geometry, the knot's shading, when a
     * pale belt needs an outline — lives in App\Sports\Combat\BeltIcon, so this
     * component and the <symbol> the JS-built lists use are one drawing rather
     * than two that drift. This file only decides WHICH belt.
     *
     * Rendered inline rather than through that symbol on purpose: a component
     * that needs a sprite somewhere else on the page is not standalone, and
     * this one is dropped into cards, sheets and lists all over the platform.
     */
    $c = null;
    $silent = $title === false;
    $label = $silent ? null : $title;

    if ($belt !== null && $belt !== '') {
        if ($c = \App\Sports\Combat\BeltIcon::for($belt, $grade, $tip)) {
            $label = $silent ? null : ($label ?: $c['label']);
        }
    } elseif (preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', trim((string) $color))) {
        $c = \App\Sports\Combat\BeltIcon::fromColour(trim($color), $tip);
    } elseif ($c = \App\Sports\Combat\BeltIcon::for($color, null, $tip)) {
        $label = $silent ? null : ($label ?: $c['label']);
    }
@endphp

@if($c)
    <svg {{ $attributes->merge(['class' => 'w-6 h-6']) }}
         viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"
         @if($label) role="img" aria-label="{{ $label }}" @else aria-hidden="true" focusable="false" @endif>
        @if($label)<title>{{ $label }}</title>@endif

        {{-- The outline: the whole shape drawn once underneath, fractionally
             fatter, so a belt the same colour as the card behind it still has
             an edge. Automatic for a pale belt; forced for every belt when the
             caller names a colour, which is what a dark surface asks for. --}}
        @php $ring = $outline !== null ? $outline : $c['outline']; @endphp
        @if($ring)
            {!! \App\Sports\Combat\BeltIcon::shape(
                ['band' => $ring, 'knot' => $ring, 'tip' => $c['tip'] ? $ring : null],
                'stroke="'.$ring.'" stroke-width="1.1" stroke-linejoin="round"'
            ) !!}
        @endif

        {!! \App\Sports\Combat\BeltIcon::shape($c) !!}
    </svg>
@endif
