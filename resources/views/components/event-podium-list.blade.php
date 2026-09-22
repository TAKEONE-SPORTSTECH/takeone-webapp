{{--
    ===== <x-event-podium-list> — the division cards =====

    The result sheet's contents, on their own: one card per division, the
    places in order, the champion first. Pulled out of
    `<x-event-podium-sheet>` so the public results page and the organiser's
    sheet are the SAME object rather than two renderings that drift — they
    already had: one drew medal icons and the other drew a flat list, and only
    one of them had been taught that a championship can award a single bronze.

    ⚠️ IT DRAWS THE MEDALS IT IS HANDED AND DECIDES NOTHING. How many bronzes a
    championship awards is `App\Events\Support\BronzeRule` (per event,
    defaulting to the sport's rule book), settled in
    `Advancement::awardIfComplete()`. The rows follow the DATA: a `place => 4`
    row draws as a placing on slate, two `place => 3` rows draw as two bronzes,
    and a play-off championship with the bout unfought has no fourth row at
    all.

    @props
      divisions  names to list, in order. Include a division with no podium and
                 it draws as PENDING — which is what an organiser opens the
                 sheet for. Leave it empty and only the decided ones are drawn,
                 which is what a public results page wants.
      results    the type's own rows: [['division' => name, 'medals' => [...]], …].
                 `places` is accepted as an alias, because that is the key the
                 public event payload already uses.
      profileUrl a URL template carrying `__UUID__`, e.g.
                 `route('people.show', '__UUID__')`. Given one, a medallist
                 whose profile this viewer can actually open becomes a link to
                 it. Omit it and nothing is a link — the right answer for a
                 surface with nowhere safe to send a reader, and the reason the
                 destination is the HOST's decision and not this component's
                 (the arrangement `<x-participant-card>` makes for `club_href`).
--}}

@props([
    'divisions' => [],
    'results' => [],
    'profileUrl' => null,
])

@php
    /* A row is a link only when the host said WHERE to, and the medallist has
       a profile THIS viewer may open. `PodiumFaces` attaches `person` for
       nobody else — a member who is not discoverable, a minor, a name typed at
       the desk with no account behind it — so a dead link cannot be built from
       here (CLAUDE.md → Navigation Integrity). */
    $link = fn (array $medal) => $profileUrl && ($medal['person'] ?? null)
        ? str_replace('__UUID__', rawurlencode($medal['person']), $profileUrl)
        : null;

    /* Keyed by name, and the two payload shapes are reconciled once, here,
       rather than at each call site. */
    $podiums = collect($results)
        ->map(fn ($row) => [
            'division' => $row['division'] ?? '',
            'medals' => $row['medals'] ?? $row['places'] ?? [],
        ])
        ->keyBy('division');

    /* No list given: draw what there IS, in the order it arrived. */
    $divisions = $divisions ?: $podiums->keys()->all();

    /* The drawing is `x-medal-icon` — one medal on the platform, server-drawn,
       sized where it is used. Only the tint and the wording live here.

       Slot 4 as written here is the SECOND BRONZE — the shape a sport that
       awards two (both beaten semi-finalists) needs. A division that returned a
       real `place => 4` overrides it below, and one that returned neither drops
       the row entirely. The decision is the DATA's, not this table's. */
    $slots = [
        1 => ['tone' => '#f59e0b', 'medal' => 1, 'place' => __('personal.event_podium_p1'), 'label' => __('personal.event_podium_gold')],
        2 => ['tone' => '#9ca3af', 'medal' => 2, 'place' => __('personal.event_podium_p2'), 'label' => __('personal.event_podium_silver')],
        3 => ['tone' => '#b45309', 'medal' => 3, 'place' => __('personal.event_podium_p3'), 'label' => __('personal.event_podium_bronze')],
        4 => ['tone' => '#b45309', 'medal' => 3, 'place' => __('personal.event_podium_p4'), 'label' => __('personal.event_podium_bronze')],
    ];
@endphp

<div {{ $attributes->merge(['class' => 'space-y-3']) }}>
    @forelse($divisions as $division)
        @php
            $podium = $podiums->get($division);
            $medals = collect($podium['medals'] ?? [])->sortBy('place')->values();

            /* Gold, silver, then whatever the SPORT awards below
               them — which this sheet used to decide for itself.

               ⚠️ It read slot 4 as a second bronze, always, by
               taking `place => 3` twice and never looking at a
               `place => 4` row at all. On a championship that plays
               third place OFF there is one bronze and a real fourth
               place, so the bronze medallist appeared once and the
               athlete who actually came fourth was dropped — the
               row showing "4th · Bronze — not decided" underneath a
               settled division. Reported 2026-09-21.

               Now the engine's answer decides: a place-4 row makes
               slot 4 a PLACING (numeral on slate, per x-medal-icon),
               and only a division that returned two place-3 rows
               shows two bronzes. */
            $bronzes = $medals->where('place', 3)->values();
            $fourth = $medals->firstWhere('place', 4);

            $bySlot = [
                1 => $medals->firstWhere('place', 1),
                2 => $medals->firstWhere('place', 2),
                3 => $bronzes->get(0),
                4 => $fourth ?: $bronzes->get(1),
            ];

            /* A play-off sport never has a second bronze to wait
               for, so slot 4 is not drawn at all until that bout
               decides it — an empty bronze disc under a finished
               division is the thing being fixed, not a placeholder
               worth keeping. */
            $slotsHere = $slots;

            if ($fourth) {
                $slotsHere[4] = [
                    'tone' => '#64748b',
                    'medal' => 4,
                    'place' => __('personal.event_podium_p4'),
                    'label' => __('personal.event_show_fourth_place'),
                ];
            } elseif ($bronzes->count() < 2) {
                unset($slotsHere[4]);
            }
        @endphp

        <div class="rounded-2xl border border-gray-100 bg-white p-3">
            <div class="flex items-center gap-2 mb-2.5">
                <i class="bi bi-diagram-3 bracket-icon text-primary"></i>
                <p class="text-sm font-bold text-foreground truncate flex-1">{{ $division }}</p>
                @if(! $podium)
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-muted text-muted-foreground flex-shrink-0">
                        {{ __('personal.event_podium_open') }}
                    </span>
                @endif
            </div>

            @if($podium)
                <div class="space-y-1.5">
                    @foreach($slotsHere as $n => $slot)
                        @php
                            $winner = $bySlot[$n] ?? null;
                            $href = $winner ? $link($winner) : null;
                        @endphp
                        {{-- The WHOLE row is the target, not the name inside
                             it: a medallist on a phone is a thumb-sized line of
                             text, and the face and club beside it are about the
                             same person. A row with nowhere to go stays a plain
                             div, so nothing looks tappable that is not. --}}
                        <{{ $href ? 'a' : 'div' }} @if($href) href="{{ $href }}" @endif
                             class="flex items-center gap-3 rounded-xl p-2 no-underline {{ $winner ? '' : 'border border-dashed border-gray-200' }} {{ $href ? 'hover:brightness-95 transition' : '' }}"
                             @if($winner) style="background: {{ $slot['tone'] }}14;" @endif>
                            <x-medal-icon :place="$slot['medal']" :size="24" :label="$slot['label']"
                                          class="{{ $winner ? '' : 'opacity-30 grayscale' }}" />

                            {{-- The face. PORTRAIT 3:4, never square — a square
                                 box centre-crops the crop the member chose and
                                 takes the top of their head with it (CLAUDE.md,
                                 Profile Pictures Are Portrait 3:4). A member who
                                 has not published a face gets the drawn
                                 silhouette, silently. --}}
                            @if($winner)
                                <span class="w-9 h-12 flex-shrink-0 rounded-lg overflow-hidden bg-muted grid place-items-center">
                                    @if($winner['photo'] ?? null)
                                        <img src="{{ $winner['photo'] }}" alt="" loading="lazy"
                                             class="w-full h-full object-cover">
                                    @else
                                        <x-gender-avatar class="w-full h-full" />
                                    @endif
                                </span>
                            @endif

                            <div class="min-w-0 flex-1">
                                @if($winner)
                                    <p class="text-sm font-bold text-foreground truncate flex items-center gap-1.5">
                                        <span class="truncate">{{ $winner['name'] }}</span>
                                        {{-- The flag is the CLUB's country, which is
                                             what a flag means on a competition sheet
                                             (ClubEventRegistration::countryCode). --}}
                                        @if($winner['country'] ?? null)
                                            {{-- Our own file. This was flagcdn.com, a
                                                 third-party host on the critical path of
                                                 a result sheet somebody prints in a hall
                                                 with no internet (CLAUDE.md → Flags Come
                                                 From One Local Source). --}}
                                            <x-flag :code="$winner['country']" :h="13"
                                                    :title="\App\Support\Countries::label($winner['country'])"
                                                    style="box-shadow: 0 0 0 1px rgba(0,0,0,.08);" />
                                        @endif
                                    </p>

                                    {{-- The club, with its crest on NO tile: a logo
                                         is a transparent PNG and a white box behind
                                         it looks broken (Design Rule #5). --}}
                                    @if($winner['club'] ?? null)
                                        <p class="text-[11px] text-muted-foreground truncate flex items-center gap-1.5 mt-0.5">
                                            @if($winner['club_logo'] ?? null)
                                                <span class="w-4 h-4 flex-shrink-0">
                                                    <img src="{{ $winner['club_logo'] }}" alt="" loading="lazy"
                                                         class="w-full h-full object-contain">
                                                </span>
                                            @endif
                                            <span class="truncate">{{ $winner['club'] }}</span>
                                        </p>
                                    @endif
                                @else
                                    <p class="text-sm font-bold text-muted-foreground/60 truncate">{{ __('personal.event_podium_open') }}</p>
                                @endif
                                <p class="text-[11px] font-semibold" style="color: {{ $slot['tone'] }};">
                                    {{ $slot['place'] }} · {{ $slot['label'] }}
                                </p>
                            </div>

                            @if($href)
                                <i class="bi bi-chevron-right rtl:rotate-180 text-muted-foreground text-xs flex-shrink-0"></i>
                            @endif
                        </{{ $href ? 'a' : 'div' }}>
                    @endforeach
                </div>
            @else
                <p class="text-[12px] text-muted-foreground">{{ __('personal.event_podium_pending') }}</p>
            @endif
        </div>
    @empty
        <p class="text-[12px] text-muted-foreground text-center py-8">{{ __('personal.event_podium_none') }}</p>
    @endforelse

</div>
