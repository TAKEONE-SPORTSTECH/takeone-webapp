@props([
    /* A ladder value ('white', 'blue', …) or the whole resolved
       `{colour, grade, …}` App\Sports\Combat\BeltRank::for() returns. */
    'belt' => null,
    /* The degree as an organiser typed it ('2', '2nd', '3rd degree'). */
    'grade' => null,
])

{{-- The RANK, said as a word.

     It used to be the entrant card's coloured edge, which was faster to scan
     but could only live on that one card — and the edge is now the gender
     (asked for on 2026-09-06). As a chip the rank travels: the same component
     draws it on the card and on the public participants list at both
     breakpoints, so a reader learns it once.

     Nothing renders when no rank is on file. A blank is the normal case, not a
     failure — most entrants are put in by staff who are asked for a name and
     nothing else (CLAUDE.md, "Who Fills The Form Decides") — so it says nothing
     rather than saying "unknown". --}}
@php $chip = \App\Sports\Combat\BeltRank::chip($belt, $grade); @endphp

@if($chip)
    <span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-muted text-foreground']) }}
          title="{{ $chip['label'] }}">
        {{-- A pale belt needs the outline or the dot vanishes into the card.
             The colour itself is never falsified: a white belt IS white. --}}
        <span class="w-2 h-2 rounded-full flex-shrink-0"
              style="background: {{ $chip['colour'] }};{{ $chip['pale'] ? ' box-shadow: inset 0 0 0 1px rgba(15,23,42,.35);' : '' }}"></span>{{ $chip['label'] }}
    </span>
@endif
