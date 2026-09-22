@props([
    /* ── THE PODIUM, AS DATA ──────────────────────────────────────────────
       `[['place' => 1, 'name' => 'Ali'], ['place' => 3, 'name' => …], …]` —
       the shape `event_categories.podium` stores and `BracketView` publishes.
       Places may repeat: a sport that awards two bronzes sends two 3s, and
       both names go on the third row. */
    'podium' => [],

    /* ...or an ALPINE EXPRESSION naming that array, for a host whose podium
       arrives with the board rather than with the page (e.g. `selected?.podium`
       on the draw stage). Given this, the rows bind instead of printing. */
    'source' => null,

    /* ── SHOW IT BEFORE IT IS DECIDED, OR NOT AT ALL? ─────────────────────

       Default SHOW: the three positions are the point of this table, and a
       division still being fought reads as a champion and two places yet to
       come. It was the other way round for about an hour on 2026-09-18 and the
       owner's answer was the obvious one — a table you cannot see is not a
       table.

       `hide-empty` is for a host that stacks several of these and does not want
       a column of dashes for the divisions nobody has reached yet. */
    'hideEmpty' => false,

    /* A heading over the table. Null draws none — useful where the card around
       it already says which division this is. */
    'title' => null,

    /* The subject's colour, for the heading rule. */
    'color' => null,

    'size' => 22,
])

@php
    $ptColor = \App\Support\Palette::safe($color);

    /* THREE POSITIONS. Not "however many rows the data has": the podium is a
       fixed shape, and a division with only a champion so far must read as two
       places still to be decided rather than as a one-line table. */
    $places = [
        1 => __('personal.event_show_champion'),
        2 => __('personal.event_show_runner_up'),
        3 => __('personal.event_show_third_place'),
    ];

    /* Server-side: group the names by place, so two bronzes share the third
       row instead of pushing silver down a line. */
    $names = collect($podium)
        ->groupBy(fn ($p) => (int) ($p['place'] ?? 0))
        ->map(fn ($g) => $g->pluck('name')->filter()->values()->all());
@endphp

{{--
    THE PODIUM, AS A TABLE. `x-podium-table`

    Three positions — first, second, third — each with its medal. Not a bracket:
    a bracket is who fought whom, and this is the one answer a reader actually
    came for. Asked for on 2026-09-18.

    The three rows are FIXED. A division halfway through shows its champion and
    two places still to be decided, which is the honest picture; a table that
    grew a row at a time would read as though third place did not exist yet.

    ── Two bronzes on one row ────────────────────────────────────────────────

    Most combat sports award two — both losing semi-finalists — so third place
    is a row with two names on it, not two rows. That is also why the medal is
    drawn from the ROW rather than from the data: the row IS the place.

    ── Static or live ────────────────────────────────────────────────────────

      x-podium-table :podium="$category['podium']"      server-rendered
      x-podium-table source="selected?.podium"          bound to Alpine state

    It is VISIBLE from the start either way: three positions, with a dash
    against each that has not been decided. `hide-empty` is the opt-out, for a
    host stacking one per division that does not want a column of dashes.
--}}

<div {{ $attributes->merge(['class' => 'rounded-2xl bg-white border border-gray-100 shadow-sm overflow-hidden']) }}
     @if($source && $hideEmpty) x-show="({{ $source }} || []).length" x-cloak @endif>

    @if($title)
        <div class="px-3.5 py-2.5 flex items-center gap-2 text-white"
             style="background: linear-gradient(135deg, {{ $ptColor }}, {{ $ptColor }}b0);">
            <i class="bi bi-trophy-fill text-[13px]"></i>
            <p class="text-[12px] font-black leading-tight truncate">{{ $title }}</p>
        </div>
    @endif

    <div class="divide-y divide-gray-100">
        @foreach($places as $n => $word)
            @php
                /* The names for this row, as an Alpine expression or as text.
                   Joined with a middot: two bronzes are one answer to one
                   question, and stacking them would make the third row twice
                   the height of the other two. */
                $rowNames = $names[$n] ?? [];
            @endphp
            <div class="flex items-center gap-3 px-3.5 py-2.5">
                <x-medal-icon :place="$n" :size="$size" :label="$word" />

                <div class="min-w-0 flex-1">
                    @if($source)
                        <p class="text-[13px] font-bold text-foreground truncate"
                           x-text="(({{ $source }} || []).filter(p => Number(p.place) === {{ $n }}).map(p => p.name).join(' · ')) || '—'"></p>
                    @else
                        <p class="text-[13px] font-bold text-foreground truncate">{{ $rowNames ? implode(' · ', $rowNames) : '—' }}</p>
                    @endif
                    <p class="text-[10.5px] font-bold uppercase tracking-wide text-muted-foreground mt-0.5">{{ $word }}</p>
                </div>
            </div>
        @endforeach
    </div>
</div>
