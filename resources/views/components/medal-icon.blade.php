@props([
    /* WHICH PLACE. 1 · 2 · 3 draw a medal; anything else draws the numeral on
       a plain slate disc, because fourth is a PLACING and not a medal and must
       never be dressed as one. `null` draws the numeral-less blank. */
    'place' => null,

    /* The disc's diameter in pixels. The ribbon hangs above it, so the drawing
       is taller than it is wide — the box is `size` across and `size * 1.32`
       tall, which is the proportion a real medal on a ribbon has. */
    'size' => 28,

    /* Show the number ON the disc. On by default: colour alone is not a
       distinction for a reader who cannot tell bronze from gold, and the
       numeral is the thing that actually says which place this is. */
    'numeral' => true,

    /* The word, for a pointer and a screen reader. Defaults to the platform's
       own vocabulary for the three medals, so a medal cannot be called one
       thing on the podium sheet and another on the public results. */
    'label' => null,
])

@php
    $p = is_numeric($place) ? (int) $place : 0;

    /* ⚠️ The DRAWING is App\Support\Medal and nowhere else — the same
       arrangement BeltIcon has, and for the same reason: a medal re-drawn in a
       second place is a medal that will eventually be two different medals.
       The bracket canvas builds its podium in JavaScript and cannot call this
       component; it `<use>`s the symbols `<x-medal-icon-defs>` emits, which are
       the same shapes from the same file. */
    $w = max(12, (int) $size);
    $h = (int) round($w * \App\Support\Medal::ratio($p));

    $words = [
        1 => __('personal.event_podium_gold'),
        2 => __('personal.event_podium_silver'),
        3 => __('personal.event_podium_bronze'),
    ];

    $aria = $label ?: ($words[$p] ?? ($p > 0 ? '#'.$p : null));
@endphp

{{--
    A MEDAL. `x-medal-icon`

    Bootstrap Icons has no medal, and the platform was using the emoji — 🥇 🥈
    🥉 — in seven places, each with its own hex beside it for the tint of the
    row behind it. An emoji is the operating system's drawing, not ours: it is a
    different object on Android, on iOS and on Windows, it ignores the size it
    is given, and it cannot take the event's own ink. The owner asked for real
    icons on 2026-09-18.

    Drawn the same way `x-belt-icon` draws a belt, and for the same reason: when
    the design system has no glyph for a thing this platform is about, the thing
    gets drawn once, server-side, and every surface uses that one drawing.

    Inline SVG, no dependencies, no stylesheet. Size it with `size` (the disc's
    diameter) and place it with the wrapper's own classes.

      x-medal-icon :place="1"                    the champion, at 28px
      x-medal-icon :place="3" :size="20"         small, beside a name
      x-medal-icon :place="4"                    a numeral on slate — NOT a medal
      x-medal-icon :place="2" :numeral="false"   where the row already says so

    ⚠️ Fourth place is deliberately not a fourth colour of medal. A championship
    records it because it is a placing an athlete carries, and drawing it as
    metal would put somebody on a podium they came off by one bout.
--}}

<span {{ $attributes->merge(['class' => 'inline-flex flex-shrink-0 leading-none']) }}
      @if($aria) role="img" aria-label="{{ $aria }}" title="{{ $aria }}" @else aria-hidden="true" @endif>
    <svg width="{{ $w }}" height="{{ $h }}" viewBox="{{ \App\Support\Medal::viewBox($p) }}"
         fill="none" xmlns="http://www.w3.org/2000/svg" focusable="false">
        {!! \App\Support\Medal::shapes($p, (bool) $numeral) !!}
    </svg>
</span>
