@props([
    'id' => 'bracket',
    'dataUrl',                      // GET  → { divisions, can_arrange, locked }
    'arrangeUrl' => null,           // PUT  → move one competitor
    'clearUrl' => null,             // PUT  → empty a division onto the bench
    'eventUuid' => null,            // scopes realtime nudges to this event
    'canArrange' => false,          // SERVER truth; the runtime re-reads it on every load
    'myCompetitorIds' => [],        // highlights the viewer's own bouts
    'height' => '70vh',             // a CSS length, applied inline — see below
    'showDivisions' => true,        // false when the host page has its own switcher
])

{{--
    Tournament bracket — the whole zoomable draw, plug-and-play.

    Standalone by contract: give it a data URL and it renders, pans, zooms and
    (with permission) lets an organiser arrange the draw by drag-and-drop or
    tap-to-place. It owns its own styling, gestures, realtime refresh and save
    calls, and knows nothing about which sport it is drawing — every bracketed
    event type feeds it the one shared shape (App\Events\Support\BracketView).

    Usage:
        <x-tournament-bracket
            :data-url="route('me.events.bracket.data', $e['key'])"
            :arrange-url="route('me.events.bracket.arrange', $e['key'])"
            :clear-url="route('me.events.bracket.clear', $e['key'])"
            :event-uuid="$e['key']" :can-arrange="$canArrange" />

    Gestures match the family tree exactly: drag to pan, pinch or wheel to zoom.
--}}

@php
    $viewportId = $id.'-viewport';
    $rtl = app()->getLocale() === 'ar';

    // A viewer who may not arrange is never handed the arranging endpoints. The
    // endpoints authorize every call themselves — this simply keeps the page to
    // what its viewer actually needs.
    $arrangeEndpoint = $canArrange ? $arrangeUrl : null;
    $clearEndpoint = $canArrange ? $clearUrl : null;
@endphp

<div
    x-data="{
        divisions: [], division: null, canArrange: false, locked: false,
        arrange: false, picked: null, ready: false,
        get current() { return this.divisions.find(d => d.id === this.division) || null; },
        onLoaded(d) {
            this.divisions = d.divisions; this.division = d.division;
            this.canArrange = d.canArrange; this.locked = d.locked; this.ready = true;
            if (!this.canArrange) this.arrange = false;
        },
        onState(d) { this.arrange = d.arrange; this.picked = d.picked; this.divisions = d.divisions; this.division = d.division; },
    }"
    @bracket:loaded="onLoaded($event.detail)"
    @bracket:state="onState($event.detail)"
    class="relative"
>
    {{-- Division switcher — one draw on screen at a time keeps the bracket readable. --}}
    @if($showDivisions)
    <div x-show="divisions.length > 1" x-cloak class="mb-3 -mx-1 px-1 overflow-x-auto">
        <div class="flex items-center gap-2 w-max">
            <template x-for="d in divisions" :key="d.id">
                <button type="button"
                        @click="window.BracketBoard.show(d.id)"
                        :class="d.id === division
                            ? 'bg-primary text-white border-primary shadow-sm'
                            : 'bg-white text-foreground border-gray-200 hover:bg-muted/60'"
                        class="flex items-center gap-2 px-3.5 py-2 rounded-full border text-xs font-bold transition-colors whitespace-nowrap">
                    <span x-text="d.name"></span>
                    <span :class="d.id === division ? 'bg-white/20 text-white' : 'bg-muted text-muted-foreground'"
                          class="px-1.5 py-0.5 rounded-full text-[0.6rem] font-extrabold"
                          x-text="d.entrants"></span>
                </button>
            </template>
        </div>
    </div>
    @endif

    {{-- Height is an INLINE style, not a Tailwind class, on purpose: the board
         measures its own viewport to lay the draw out and fit it to screen, so
         a height that silently resolves to 0 (an arbitrary class the CSS build
         never saw, because this component was added after the last build)
         would leave a zero-height, un-clickable board. Inline always applies. --}}
    <div class="relative rounded-2xl overflow-hidden border border-gray-100 shadow-sm bg-white"
         style="height: {{ $height }};">
        {{-- The board itself. Everything inside is built by the runtime. --}}
        <div id="{{ $viewportId }}" class="w-full h-full"></div>

        {{-- Arrange banner — says what mode you're in and what you're holding. --}}
        <div x-show="arrange" x-cloak x-transition
             class="absolute top-3 inset-x-3 z-30 flex items-center gap-2 px-3 py-2 rounded-xl
                    bg-primary text-white shadow-lg text-xs font-bold pointer-events-none">
            <i class="bi bi-arrows-move"></i>
            <span x-show="!picked">{{ __('events.bracket_arrange_hint') }}</span>
            <span x-show="picked" x-cloak>
                {{ __('events.bracket_holding') }} <span x-text="picked" class="underline"></span> —
                {{ __('events.bracket_tap_to_place') }}
            </span>
        </div>

        {{-- Control cluster: zoom, fit, and (for organisers) arrange + clear. --}}
        <div class="bk-controls absolute bottom-3 z-30 flex flex-col gap-2 {{ $rtl ? 'left-3' : 'right-3' }}">
            @if($arrangeEndpoint)
                <button type="button" x-show="canArrange" x-cloak
                        @click="window.BracketBoard.toggleArrange()"
                        :class="arrange ? 'bg-primary text-white border-primary' : 'bg-white text-foreground border-gray-200'"
                        class="w-10 h-10 rounded-xl border flex items-center justify-center shadow-sm
                               hover:shadow-md transition-all active:scale-95"
                        :aria-pressed="arrange"
                        :title="arrange ? '{{ __('events.bracket_done_arranging') }}' : '{{ __('events.bracket_arrange') }}'">
                    <i class="bi" :class="arrange ? 'bi-check-lg' : 'bi-arrows-move'"></i>
                </button>

                @if($clearEndpoint)
                    <button type="button" x-show="arrange" x-cloak x-transition
                            @click="window.BracketBoard.clearDraw()"
                            class="w-10 h-10 rounded-xl border border-red-200 bg-white text-red-600
                                   flex items-center justify-center shadow-sm hover:bg-red-50 transition-all active:scale-95"
                            title="{{ __('events.bracket_clear') }}">
                        <i class="bi bi-eraser"></i>
                    </button>
                @endif
            @endif

            <button type="button" @click="window.BracketBoard.zoomIn()"
                    class="w-10 h-10 rounded-xl border border-gray-200 bg-white text-foreground
                           flex items-center justify-center shadow-sm hover:bg-muted/60 transition-all active:scale-95"
                    title="{{ __('events.bracket_zoom_in') }}">
                <i class="bi bi-plus-lg"></i>
            </button>
            <button type="button" @click="window.BracketBoard.zoomOut()"
                    class="w-10 h-10 rounded-xl border border-gray-200 bg-white text-foreground
                           flex items-center justify-center shadow-sm hover:bg-muted/60 transition-all active:scale-95"
                    title="{{ __('events.bracket_zoom_out') }}">
                <i class="bi bi-dash-lg"></i>
            </button>
            <button type="button" @click="window.BracketBoard.fit()"
                    class="w-10 h-10 rounded-xl border border-gray-200 bg-white text-foreground
                           flex items-center justify-center shadow-sm hover:bg-muted/60 transition-all active:scale-95"
                    title="{{ __('events.bracket_fit') }}">
                <i class="bi bi-arrows-angle-contract"></i>
            </button>
        </div>

        {{-- Locked notice — the draw is final from the first bout. --}}
        <div x-show="locked" x-cloak
             class="absolute top-3 z-20 px-2.5 py-1 rounded-full bg-white/90 backdrop-blur border border-gray-200
                    text-[0.6rem] font-extrabold text-muted-foreground flex items-center gap-1.5
                    {{ $rtl ? 'right-3' : 'left-3' }}">
            <i class="bi bi-lock-fill"></i> {{ __('events.bracket_locked') }}
        </div>
    </div>

    {{-- Legend --}}
    <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-[0.65rem] text-muted-foreground">
        <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-amber-500"></span>{{ __('events.bracket_legend_provisional') }}</span>
        <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-green-600"></span>{{ __('events.bracket_legend_done') }}</span>
        <span class="flex items-center gap-1.5"><i class="bi bi-hand-index-thumb"></i>{{ __('events.bracket_legend_gestures') }}</span>
    </div>
</div>

@include('components.bracket.runtime')

<script>
(function () {
    // Mount on first paint AND after every mobile-shell / admin-shell swap —
    // the runtime is idempotent, so re-mounting simply rebuilds the board.
    const boot = () => {
        if (!document.getElementById('{{ $viewportId }}')) return;
        window.BracketBoard.mount({
            viewportId: '{{ $viewportId }}',
            dataUrl: @json($dataUrl),
            arrangeUrl: @json($arrangeEndpoint),
            clearUrl: @json($clearEndpoint),
            eventUuid: @json($eventUuid),
            csrf: document.querySelector('meta[name=csrf-token]')?.content || '',
            myCompetitorIds: @json(array_values((array) $myCompetitorIds)),
            rtl: {{ $rtl ? 'true' : 'false' }},
            text: {
                tbd: @json(__('events.bracket_tbd')),
                bye: @json(__('events.bracket_bye')),
                bench: @json(__('events.bracket_bench')),
                benchEmpty: @json(__('events.bracket_bench_empty')),
                provisional: @json(__('events.bracket_legend_provisional')),
                noDraw: @json(__('events.bracket_no_draw')),
                loadFailed: @json(__('events.bracket_load_failed')),
                moveFailed: @json(__('events.bracket_move_failed')),
                clearTitle: @json(__('events.bracket_clear_title')),
                clearMessage: @json(__('events.bracket_clear_message')),
                clearConfirm: @json(__('events.bracket_clear_confirm')),
            },
        });
    };

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
    else boot();
})();
</script>
