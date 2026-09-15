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
    <span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 ps-1 pe-2 py-0.5 rounded-full text-[10px] font-semibold bg-muted text-foreground']) }}
          title="{{ $chip['label'] }}">
        {{-- The belt itself, not a coloured dot: a dot said "some rank", and the
             reader had to know the ladder to tell which. A pale belt is drawn
             with an outline so it survives a white card — the colour itself is
             never falsified, a white belt IS white.

             Silent to a screen reader on purpose: the chip says the rank in
             words right beside it, and one thing should be announced once. --}}
        <x-belt-icon :belt="$belt" :grade="$grade" :title="false"
                     class="w-4 h-4 flex-shrink-0" />{{ $chip['label'] }}
    </span>
@endif
