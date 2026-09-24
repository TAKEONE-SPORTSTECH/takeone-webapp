@props([
    'id' => 'bracket',
    'dataUrl',                      // GET  → { divisions, can_arrange, locked }
    'arrangeUrl' => null,           // PUT  → move one competitor
    'clearUrl' => null,             // PUT  → empty a division onto the bench
    'eventUuid' => null,            // scopes realtime nudges to this event
    'canArrange' => false,          // SERVER truth; the runtime re-reads it on every load
    'canManage' => false,           // may this viewer DECIDE a bout, score it,
                                    // correct a name? The organiser's, not the
                                    // appointed official's — and re-read from
                                    // the data feed on every load.
    'myCompetitorIds' => [],        // highlights the viewer's own bouts
    'height' => '70vh',             // a CSS length, applied inline — see below
    'initialDivision' => null,      // open on THIS division instead of the first
    'showDivisions' => true,        // false when the host page has its own switcher
    'bare' => false,                // the board IS the page, not a card on it —
                                    // drop the rounding, border and shadow
    'bleed' => false,               // pull the board out of the host page's
                                    // standard px-4/6/8 gutters so it runs edge
                                    // to edge. Separate from `bare` because a
                                    // page with no gutters of its own (the
                                    // full-screen draw manager) wants the chrome
                                    // gone but must NOT be pulled outward.
    'fill' => false,                // stretch to the host's height instead of
                                    // measuring one. A page that already IS the
                                    // viewport (the full-screen draw manager)
                                    // hands the board a flex slot and passes
                                    // height="100%"; without this the root sits
                                    // at auto height and that 100% resolves to
                                    // ZERO. Opt-in, so every card-shaped use of
                                    // this component is untouched.
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

    /* ── The DRAFT's organiser actions, as addresses ───────────────────────

       Deciding a bout, typing a score and correcting a name are the
       ORGANISER's — a different permission from arranging, which an appointed
       official also holds. `canManage` is what the host knows; the board
       re-reads the server's own answer (`can_manage` on the data feed) before
       it draws a single control, and each endpoint authorises for itself.

       Built from a real route with a sentinel id so `php artisan route:list`
       still accounts for them, then the sentinel is swapped client-side. */
    $outcomeTemplate = $canManage && $eventUuid
        ? str_replace('999999999', '__ID__', route('me.events.outcome', [$eventUuid, 999999999]))
        : null;
    $entrantTemplate = $canManage && $eventUuid
        ? str_replace('999999999', '__ID__', route('me.events.entrant.update', [$eventUuid, 999999999]))
        : null;
    $admitEndpoint = ($canManage || $canArrange) && $eventUuid
        ? route('me.events.bracket.admit', $eventUuid)
        : null;
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
    class="relative {{ $fill ? 'h-full' : '' }}"
>
    {{-- Division switcher — one draw on screen at a time keeps the bracket readable. --}}
    @if($showDivisions)
    <div x-show="divisions.filter(d => ! d.is_heading).length > 1" x-cloak class="mb-3 -mx-1 px-1 overflow-x-auto">
        <div class="flex items-center gap-2 w-max">
            <template x-for="d in divisions" :key="d.id">
                <div class="flex items-center">
                    {{-- A HEADING: the organiser's own title for the divisions
                         that follow it in this strip. Not a button — there is no
                         draw behind it — so it reads as a label between groups
                         of pills rather than a tab that does nothing. --}}
                    <span x-show="d.is_heading" x-cloak
                          class="ps-2 pe-3 text-[10px] font-black uppercase tracking-[0.18em] text-muted-foreground
                                 whitespace-nowrap flex items-center gap-1.5">
                        <i class="bi bi-bookmark-fill text-[10px] text-primary/70"></i>
                        <span x-text="d.name"></span>
                    </span>

                    <button type="button" x-show="! d.is_heading"
                            @click="window.BracketBoard.show(d.id)"
                            :class="d.id === division ? 'bk-pill is-on' : 'bk-pill'"
                            class="flex items-center gap-2 px-3.5 py-2 rounded-full border text-xs font-bold transition-colors whitespace-nowrap">
                        <span x-text="d.name"></span>
                        <span class="bk-pill-count px-1.5 py-0.5 rounded-full text-[0.6rem] font-extrabold"
                              x-text="d.entrants"></span>
                    </button>
                </div>
            </template>
        </div>
    </div>
    @endif

    {{-- Height is an INLINE style, not a Tailwind class, on purpose: the board
         measures its own viewport to lay the draw out and fit it to screen, so
         a height that silently resolves to 0 (an arbitrary class the CSS build
         never saw, because this component was added after the last build)
         would leave a zero-height, un-clickable board. Inline always applies. --}}
    {{-- The frame is the board's own navy now, not white: the draft's hall
         runs to the edge of whatever holds it, and a white card around a navy
         board reads as a hole in the page. --}}
    <div @class([
            'bk-frame relative overflow-hidden',
            'bk-frame-card' => ! $bare,
            '-mx-4 sm:-mx-6 lg:-mx-8' => $bleed,
         ])
         style="height: {{ $height }};">
        {{-- The board itself. Everything inside is built by the runtime. --}}
        <div id="{{ $viewportId }}" class="w-full h-full"></div>

        {{-- Arrange banner — says what mode you're in and what you're holding. --}}
        <div x-show="arrange" x-cloak x-transition
             class="bk-banner absolute top-3 inset-x-3 z-30 flex items-center gap-2 px-3 py-2 rounded-xl
                    shadow-lg text-xs font-bold pointer-events-none">
            <i class="bi bi-arrows-move"></i>
            <span x-show="!picked">{{ __('events::events.bracket_arrange_hint') }}</span>
            <span x-show="picked" x-cloak>
                {{ __('events::events.bracket_holding') }} <span x-text="picked" class="underline"></span> —
                {{ __('events::events.bracket_tap_to_place') }}
            </span>
        </div>

        {{-- Control cluster: zoom, fit, and (for organisers) arrange + clear. --}}
        {{-- ⚠️ The bottom offset carries the safe area. On a phone the board can
             run to the very bottom of the screen (the full-screen draw manager
             does), and 12px from that edge is under the home indicator or the
             gesture bar — reachable only by pressing the system UI. Resolves to
             plain 0.75rem everywhere there is no inset.

             `bottom-3` STAYS on the class list as the floor: a browser without
             `env()` drops the whole inline declaration, and without the class
             the element would have no bottom at all and jump to the top of the
             board. Inline wins wherever it parses. --}}
        <div class="bk-controls absolute bottom-3 z-30 flex flex-col gap-2 {{ $rtl ? 'left-3' : 'right-3' }}"
             style="bottom: calc(0.75rem + env(safe-area-inset-bottom));">
            {{-- ⚠️ THE ARRANGE AND CLEAR BUTTONS ARE GONE FROM HERE (2026-09-20).

                 Arranging is no longer a mode of its own: the draw stage's
                 PADLOCK turns it on along with everything else editable, which
                 is what the owner asked for — one switch, not four controls
                 that each unlock a quarter of the board. A second arrange
                 button beside it would have sat there refusing every press
                 while the padlock was shut, which is exactly the drift this
                 project keeps a component library to avoid.

                 Emptying a draw is a DRAW VERB and lives with the others in
                 the stage's menu ("Clear draw", from the package's own
                 availableActions), so it is offered once rather than twice.

                 `arrangeUrl` / `clearUrl` are untouched — the runtime still
                 reaches for both, and `window.BracketBoard.toggleArrange()`
                 and `.clearDraw()` are still on the public API for any host
                 that wants to drive them. Only these two buttons went. --}}

            <button type="button" @click="window.BracketBoard.zoomIn()"
                    class="bk-ctl w-10 h-10 rounded-xl border flex items-center justify-center
                           shadow-lg transition-all active:scale-95"
                    title="{{ __('events::events.bracket_zoom_in') }}">
                <i class="bi bi-plus-lg"></i>
            </button>
            <button type="button" @click="window.BracketBoard.zoomOut()"
                    class="bk-ctl w-10 h-10 rounded-xl border flex items-center justify-center
                           shadow-lg transition-all active:scale-95"
                    title="{{ __('events::events.bracket_zoom_out') }}">
                <i class="bi bi-dash-lg"></i>
            </button>
            <button type="button" @click="window.BracketBoard.fit()"
                    class="bk-ctl w-10 h-10 rounded-xl border flex items-center justify-center
                           shadow-lg transition-all active:scale-95"
                    title="{{ __('events::events.bracket_fit') }}">
                <i class="bi bi-arrows-angle-contract"></i>
            </button>
        </div>

        {{-- Locked notice — the draw is final from the first bout. --}}
        <div x-show="locked" x-cloak
             class="bk-locked absolute top-3 z-20 px-2.5 py-1 rounded-full backdrop-blur border
                    text-[0.6rem] font-extrabold flex items-center gap-1.5
                    {{ $rtl ? 'right-3' : 'left-3' }}">
            <i class="bi bi-lock-fill"></i> {{ __('events::events.bracket_locked') }}
        </div>

        {{-- Legend — inside the board, floating on its bottom edge. It reads the
             board, so it belongs on it; below the frame it was a line of text the
             page had to make room for, and on a phone that room came out of the
             draw. pointer-events-none so it never eats a pan that starts on it,
             and it stands down in arrange mode — the entrants bench takes this
             same bottom strip on a narrow screen.

             Bare text, NOT a card: it is a caption on the board, and a white
             panel with a border floating over the draw read as another thing to
             deal with. Asked for explicitly 2026-09-06. --}}
        <div id="{{ $id }}-legend" x-show="! arrange"
             style="bottom: calc(0.75rem + env(safe-area-inset-bottom));"
             class="bk-legend absolute bottom-3 z-20 pointer-events-none
                    flex flex-wrap items-center gap-x-3 gap-y-1 text-[0.65rem]
                    {{-- clears the zoom/arrange control column, which shares this edge --}}
                    {{ $rtl ? 'right-3 left-16' : 'left-3 right-16' }}">
            <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full" style="background:#d8b25f"></span>{{ __('events::events.bracket_legend_provisional') }}</span>
            <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full" style="background:#2ecc71"></span>{{ __('events::events.bracket_legend_done') }}</span>
            <span class="flex items-center gap-1.5"><i class="bi bi-hand-index-thumb"></i>{{ __('events::events.bracket_legend_gestures') }}</span>
        </div>
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
            // Which division to open on. A bout's "View draw" names its own, so
            // the board opens where that bout is rather than on the first
            // division. Null keeps the previous behaviour exactly.
            initialDivision: @json($initialDivision),
            csrf: document.querySelector('meta[name=csrf-token]')?.content || '',
            /* ── The three addresses the DRAFT's board writes through ──────
               Handed over only to a viewer the server has said may manage,
               for the same reason the arrange endpoints are: every one of
               them re-authorises on each call, and an address nobody may use
               has no business in a stranger's page source.

               `__ID__` is substituted client-side. Built from a route with a
               placeholder id rather than concatenated by hand, so a change to
               the route pattern moves these with it (CLAUDE.md → JS-built
               URLs must match a real route). */
            outcomeUrl: @json($outcomeTemplate),
            entrantUrl: @json($entrantTemplate),
            admitUrl: @json($admitEndpoint),
            myCompetitorIds: @json(array_values((array) $myCompetitorIds)),
            rtl: {{ $rtl ? 'true' : 'false' }},
            text: {
                tbd: @json(__('events::events.bracket_tbd')),
                bye: @json(__('events::events.bracket_bye')),
                bench: @json(__('events::events.bracket_bench')),
                benchEmpty: @json(__('events::events.bracket_bench_empty')),
                provisional: @json(__('events::events.bracket_legend_provisional')),
                noDraw: @json(__('events::events.bracket_no_draw')),
                loadFailed: @json(__('events::events.bracket_load_failed')),
                moveFailed: @json(__('events::events.bracket_move_failed')),
                clearTitle: @json(__('events::events.bracket_clear_title')),
                clearMessage: @json(__('events::events.bracket_clear_message')),
                clearConfirm: @json(__('events::events.bracket_clear_confirm')),
                /* The podium card the canvas draws. Its words come from here
                   rather than from the JS, so the board says "Champion" in the
                   reader's own language like everything else on it. */
                podium: @json(__('personal.personal_event_bracket_podium')),
                place1: @json(__('personal.event_show_champion')),
                place2: @json(__('personal.event_show_runner_up')),
                place3: @json(__('personal.event_show_third_place')),
                /* The draft's own vocabulary — the strip across a bout, the
                   two kinds of empty corner, and the holding basket. */
                match: @json(__('events::events.bracket_match')),
                mat: @json(__('events::events.bracket_mat')),
                byeNoBout: @json(__('events::events.bracket_bye_no_bout')),
                awaiting: @json(__('events::events.bracket_awaiting')),
                champion: @json(__('events::events.bracket_champion')),
                championPending: @json(__('events::events.bracket_champion_pending')),
                toBeDecided: @json(__('events::events.bracket_to_be_decided')),
                basket: @json(__('events::events.bracket_basket')),
                basketHint: @json(__('events::events.bracket_basket_hint')),
                semifinalLoser: @json(__('events::events.bracket_semifinal_loser')),
                score: @json(__('events::events.bracket_score')),
                removeToBye: @json(__('events::events.bracket_remove_to_bye')),
                outcomeFailed: @json(__('events::events.bracket_outcome_failed')),
                renameFailed: @json(__('events::events.bracket_rename_failed')),
                dismiss: @json(__('shared.close')),
                bgBadType: @json(__('events::events.bracket_bg_bad_type')),
                bgTooBig: @json(__('events::events.bracket_bg_too_big')),
            },
        });
    };

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
    else boot();
})();
</script>
