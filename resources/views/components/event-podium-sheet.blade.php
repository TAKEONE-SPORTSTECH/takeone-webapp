{{--
    ===== <x-event-podium-sheet> — the podium, per division, DERIVED =====

    The result sheet as it would be pinned to the wall of the hall: one card
    per division, the places in order, the champion first. Every division is
    listed, not only the finished ones — an organiser opens this to find out
    what is still OUTSTANDING, and a division missing from the page cannot
    answer that.

    ⚠️ READ-ONLY ON PURPOSE, and it is not a styling choice. A bracketed
    championship has no "the winners" to type in: gold is whoever won the
    final. `EventType::allowsManualResults()` is false for those types and
    `PersonalEventController::setResults()` refuses hand-entry server-side, so
    this sheet agrees with the server rather than offering a form the endpoint
    would reject.

    ⚠️ IT RENDERS THE MEDALS IT IS HANDED AND DECIDES NOTHING. How many bronzes
    a championship awards is the EVENT's answer (`App\Events\Support\BronzeRule`,
    set per event and defaulting to the sport's rule book), settled in
    `Advancement::awardIfComplete()` and read back through
    `App\Sports\Combat\Engine\Results::podium()`. This view used to decide it
    here — it filled its fourth row with a second `place => 3` and never looked
    for a `place => 4` at all — which is how a one-bronze championship came to
    show two (2026-09-21). The rows below follow the DATA: a place-4 row draws
    as a placing, two place-3 rows draw as two bronzes, and a play-off sport
    with the bout unfought simply has no fourth row.

    Standalone: it teleports itself to <body> (the mobile shell's transform trap
    — CLAUDE.md, Mobile Forms), carries the Design Rule #8 gradient band, and
    takes its own colour. Two ways to open it:

      · `flag` — an Alpine expression in the HOST's scope (`resultsOpen` is what
        both event consoles already use). Nothing else changes for them.
      · nothing — it owns its own state and opens on the window event named by
        `event-name` (default `open-event-podium`), so any page can do
        `window.dispatchEvent(new CustomEvent('open-event-podium'))`.

    @props
      divisions  array of division names, in the order to list them
      results    the type's own podium rows: [['division' => name, 'medals' => [...]], …]
      title      what the sheet is about — the championship's name
      drawUrl    where "Open the draw" goes; omit it and the footer is not drawn
      color      the sheet's accent (defaults to the medal amber)
      flag       host Alpine expression, when the host already owns the state
      eventName  the window event that opens it, when it owns its own
      profileUrl passed straight to <x-event-podium-list> — where a medallist's
                 row goes when it is tapped
--}}

@props([
    'divisions' => [],
    'results' => [],
    'profileUrl' => null,
    'title' => '',
    'drawUrl' => null,
    'color' => '#b45309',
    'flag' => null,
    'eventName' => 'open-event-podium',
])

@php
    /* A sheet that owns its state wraps itself in one; a sheet driven by the
       host binds straight to the host's expression, which is exactly what the
       two consoles were doing before this became a component. */
    $own = $flag === null;
    $state = $own ? 'open' : $flag;

    /* ⚠️ The band's gradient is `hex → hex + b0`, and the alpha suffix is
       HEX-ONLY: an `hsl()` with b0 appended is an invalid gradient and the
       whole declaration is dropped (Design Rule #8). Whitelisted for the same
       reason `x-event-section-band` whitelists its own — this lands in a
       style attribute. */
    $color = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $color) ? $color : '#b45309';
    $colorSoft = $color;

    /* Only the chip in the band counts here — the cards are drawn by
       <x-event-podium-list>, which is the one place that knows how a podium
       reads. The engine returns only DECIDED divisions, so counting its rows
       against the full division list is what "4 of 15 decided" means. */
    $decided = collect($results)->count();
@endphp

<template x-teleport="body">
    @if($own)
    {{-- ⚠️ `@{{ $eventName }}` would be Blade's own escape for a literal
         `{{ … }}`, so the listener silently never bound. The `@` is emitted
         as part of the expression instead. --}}
    <div x-data="{ open: false }"
         {{ '@'.$eventName }}.window="open = true"
         @keydown.escape.window="open = false">
    @endif
    <div x-show="{{ $state }}" x-cloak class="fixed inset-0 z-[60]" style="display:none;">
        <div class="absolute inset-0 bg-black/40" @click="{{ $state }}=false" x-transition.opacity></div>

        {{-- The same bottom sheet the read-only medals sheet on the event page
             uses, so the two are the same object in two places. --}}
        <div class="absolute bottom-0 inset-x-0 bg-background rounded-t-3xl max-h-[88vh] flex flex-col overflow-hidden"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0">

            {{-- The gradient band every sheet opens with (Design Rule #8).
                 ⚠️ hex → hex+b0; an hsl() with b0 appended is an invalid
                 gradient and the whole declaration is dropped. --}}
            <div class="flex-shrink-0 px-5 pt-3 pb-4 rounded-t-3xl text-white relative overflow-hidden"
                 style="background: linear-gradient(150deg, {{ $color }}, {{ $colorSoft }}b0);">
                <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3"></div>

                <div class="relative flex items-start gap-3">
                    <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0">
                        <i class="bi bi-trophy-fill text-xl"></i>
                    </span>
                    <div class="min-w-0 flex-1">
                        <h3 class="text-lg font-black leading-tight">{{ __('personal.event_podium_title') }}</h3>
                        <p class="text-[12px] text-white/85 mt-0.5 truncate">{{ $title }}</p>
                    </div>
                    <button type="button" @click="{{ $state }}=false" aria-label="{{ __('shared.close') }}"
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

                {{-- The cards themselves are their own component, so this
                     sheet and the public results page cannot drift apart. --}}
                <x-event-podium-list :divisions="$divisions" :results="$results"
                                     :profile-url="$profileUrl" />
            </div>

            {{-- The one thing there IS to do from here: go and score the bouts.
                 A host with nowhere to send them draws no footer at all, rather
                 than a button that lands on a guess. --}}
            @if($drawUrl)
            <div class="flex-shrink-0 border-t border-gray-100 bg-background px-5 pt-3"
                 style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
                <a href="{{ $drawUrl }}"
                   class="m-press w-full h-12 rounded-xl text-white text-sm font-bold no-underline flex items-center justify-center gap-2"
                   style="background: {{ $color }};">
                    <i class="bi bi-diagram-3 bracket-icon"></i>{{ __('personal.event_podium_open_draw') }}
                </a>
            </div>
            @endif
        </div>
    </div>
    @if($own)
    </div>
    @endif
</template>
