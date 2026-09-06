{{--
    ===== The podium, per division, DERIVED =====

    A single-elimination championship does not have "the winners". It has a
    podium PER DIVISION, and four places on each: gold to whoever won the
    final, silver to whoever lost it, and BRONZE TO BOTH beaten semi-finalists
    — there is no third-place match, so 3rd and 4th are equal bronzes
    (reported 2026-09-04: the console was offering a flat, hand-typed winners
    list instead, which for this event type is both the wrong shape and a way
    to publish a result that contradicts the bouts).

    So this sheet is READ-ONLY on purpose. `EventType::allowsManualResults()`
    is false for a bracketed championship and `PersonalEventController::
    setResults()` refuses hand-entry for those types server-side; the console
    now agrees with the server instead of showing a form the endpoint would
    reject. The medal convention itself stays PRIVATE to the package (CLAUDE.md
    → Shared Stays Shared): this view only renders what the type's own results
    engine returned — `App\Sports\Combat\Engine\Results::podium()`, which is
    where "two bronzes" is decided.

    Every division is listed, not only the finished ones: the organiser opens
    this to find out what is still outstanding, and a division that is missing
    from the page cannot answer that.

    Expects `$e` (the event view: `divisions`, `bracket_results`, `title`,
    `key`) and reuses the console's `resultsOpen` Alpine flag.
--}}

@php
    /* The engine returns only decided divisions, keyed by name. Everything
       else on the card is drawn from the full division list, so a division
       with no champion yet reads as pending rather than as absent. */
    $podiums = collect($e['bracket_results'] ?? [])->keyBy('division');

    $slots = [
        1 => ['medal' => '🥇', 'tone' => '#f59e0b', 'place' => __('personal.event_podium_p1'), 'label' => __('personal.event_podium_gold')],
        2 => ['medal' => '🥈', 'tone' => '#9ca3af', 'place' => __('personal.event_podium_p2'), 'label' => __('personal.event_podium_silver')],
        3 => ['medal' => '🥉', 'tone' => '#b45309', 'place' => __('personal.event_podium_p3'), 'label' => __('personal.event_podium_bronze')],
        4 => ['medal' => '🥉', 'tone' => '#b45309', 'place' => __('personal.event_podium_p4'), 'label' => __('personal.event_podium_bronze')],
    ];

    $divisions = $e['divisions'] ?? [];
    $decided = $podiums->count();
@endphp

<template x-teleport="body">
    <div x-show="resultsOpen" x-cloak class="fixed inset-0 z-[60]" style="display:none;">
        <div class="absolute inset-0 bg-black/40" @click="resultsOpen=false" x-transition.opacity></div>

        {{-- The same bottom sheet the read-only medals sheet on the event page
             uses, so the two are the same object in two places. --}}
        <div class="absolute bottom-0 inset-x-0 bg-background rounded-t-3xl max-h-[88vh] flex flex-col overflow-hidden"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0">

            {{-- The gradient band every sheet opens with (Design Rule #8).
                 ⚠️ hex → hex+b0; an hsl() with b0 appended is an invalid
                 gradient and the whole declaration is dropped. --}}
            <div class="flex-shrink-0 px-5 pt-3 pb-4 rounded-t-3xl text-white relative overflow-hidden"
                 style="background: linear-gradient(150deg, #b45309, #d97706b0);">
                <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3"></div>

                <div class="relative flex items-start gap-3">
                    <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0">
                        <i class="bi bi-trophy-fill text-xl"></i>
                    </span>
                    <div class="min-w-0 flex-1">
                        <h3 class="text-lg font-black leading-tight">{{ __('personal.event_podium_title') }}</h3>
                        <p class="text-[12px] text-white/85 mt-0.5 truncate">{{ $e['title'] }}</p>
                    </div>
                    <button type="button" @click="resultsOpen=false" aria-label="{{ __('shared.close') }}"
                            class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>

                @if($divisions)
                    <div class="relative mt-3 flex flex-wrap gap-1.5">
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-white/20 text-[11px] font-bold">
                            <i class="bi bi-trophy"></i>
                            {{ __('personal.event_podium_decided', ['done' => $decided, 'total' => count($divisions)]) }}
                        </span>
                    </div>
                @endif
            </div>

            {{-- Body --}}
            <div class="flex-1 overflow-y-auto min-h-0 p-4 space-y-3">

                {{-- Why there is no form here. Said once, at the top. --}}
                <p class="text-[12px] text-muted-foreground leading-relaxed flex gap-2">
                    <i class="bi bi-info-circle text-amber-600 flex-shrink-0 mt-0.5"></i>
                    <span>{{ __('personal.event_podium_derived') }}</span>
                </p>

                @forelse($divisions as $division)
                    @php
                        $podium = $podiums->get($division);
                        $medals = collect($podium['medals'] ?? [])->sortBy('place')->values();

                        /* Gold, silver, then the two bronzes in the order the
                           semi-finals were played. `firstWhere` on place for the
                           top two; the place-3 rows fill slots 3 and 4. */
                        $bronzes = $medals->where('place', 3)->values();
                        $bySlot = [
                            1 => $medals->firstWhere('place', 1),
                            2 => $medals->firstWhere('place', 2),
                            3 => $bronzes->get(0),
                            4 => $bronzes->get(1),
                        ];
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
                                @foreach($slots as $n => $slot)
                                    @php $winner = $bySlot[$n] ?? null; @endphp
                                    <div class="flex items-center gap-3 rounded-xl p-2 {{ $winner ? '' : 'border border-dashed border-gray-200' }}"
                                         @if($winner) style="background: {{ $slot['tone'] }}14;" @endif>
                                        <span class="text-xl flex-shrink-0 leading-none {{ $winner ? '' : 'opacity-30 grayscale' }}">{{ $slot['medal'] }}</span>
                                        <div class="min-w-0 flex-1">
                                            @if($winner)
                                                <p class="text-sm font-bold text-foreground truncate">{{ $winner['name'] }}</p>
                                            @else
                                                <p class="text-sm font-bold text-muted-foreground/60 truncate">{{ __('personal.event_podium_open') }}</p>
                                            @endif
                                            <p class="text-[11px] font-semibold" style="color: {{ $slot['tone'] }};">
                                                {{ $slot['place'] }} · {{ $slot['label'] }}
                                            </p>
                                        </div>
                                    </div>
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

            {{-- The one thing there IS to do from here: go and score the bouts. --}}
            <div class="flex-shrink-0 border-t border-gray-100 bg-background px-5 pt-3"
                 style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
                <a href="{{ isset($shell) ? url('/e/'.$e['key'].'/admin/brackets/manage') : route('me.events.bracket.manage', $e['key']) }}"
                   class="m-press w-full h-12 rounded-xl text-white text-sm font-bold no-underline flex items-center justify-center gap-2"
                   style="background: #b45309;">
                    <i class="bi bi-diagram-3 bracket-icon"></i>{{ __('personal.event_podium_open_draw') }}
                </a>
            </div>
        </div>
    </div>
</template>
