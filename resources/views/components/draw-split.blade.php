{{--
    The draw's SPLIT VIEW — a second division's board beside the first, and a
    competitor dragged from one to the other.

    ── What it is for ────────────────────────────────────────────────────────
    An organiser finds somebody standing in the wrong weight class, or entered
    in one division and also fighting in another. Before this, the answer was a
    search sheet on the far board. Now: open the other division beside this
    one, drag the competitor across, and say MOVE or COPY.

      · MOVE — out of the old division and into the new one. Still one entry.
      · COPY — they compete in both. A second entry, and the sheet asks there
        and then whether it is charged.

    Both are App\Events\Support\Tournament\Transfer on the server, which does
    every draw change through the sport's own Arrangement, so nothing here can
    do what an organiser could not already do by hand.

    ── Standalone ────────────────────────────────────────────────────────────
    It owns the toggle, the second board, the picker, the sheet and the
    request. The host supplies the event, the write address and a box to sit
    in, and listens for one window event if it wants to make room:

        draw-split:changed  { open, tall }   — the second board opened/closed;
                                               `tall` = stacked (portrait)

    The second board is `BracketBoard.create()` — a separate instance of the
    same runtime, mounted from the main board's own config. Its `bracket:*`
    events are stopped at this pane, so the stage, its division menu and the
    editing forms only ever hear the MAIN board. A drop on either board that
    lands on the other arrives as `bracket:transfer` (cfg.transfer).

    Offered only while the main board is in ARRANGE mode, closed the moment it
    leaves it. ⚠️ It decides nothing: the endpoint re-authorises every drop
    (MOVE = arranging; COPY = the organiser, because it can make a charge).
--}}

@props([
    'event',                 // uuid
    'transferUrl',           // PUT → Transfer
    'price' => null,         // the entry price as words ("BHD 20"), or null
    'color' => null,
    'id' => 'draw',
])

@php
    $dpColor = \App\Support\Palette::safe($color, '#7c6bf5');
    $dpId = preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/', (string) $id) ? $id : 'draw';
@endphp

{{-- The picker's rows — one copy, shared with x-draw-stage's division menu. --}}
@include('components.bracket.division-look')

@once
@push('scripts')
<script>
(function () {
    var register = function () {
        if (! window.Alpine || window.__drawSplitRegistered) return;
        window.__drawSplitRegistered = true;

        window.Alpine.data('drawSplit', function (cfg) {
            return {
                cfg: cfg,
                arrange: false,          // the MAIN board is arranging
                open: false,             // the second board is showing
                tall: false,             // stacked (portrait) rather than side by side
                divisions: [],           // the main board's divisions
                mainDivision: null,
                other: null,             // the second board's division
                pickerOpen: false,
                // The picker's quick filter — the main menu's, shared (division-filter).
                flt: { q: '', g: 'all', a1: '', a2: '', w1: '', w2: '' },
                board: null,

                // The pending drop, waiting for Move / Copy.
                xfer: null, mode: 'move', charge: null, busy: false,

                init() {
                    const self = this;
                    // Only the MAIN board reaches the document: the second
                    // board's events are stopped at its pane (see the markup).
                    this._onBoard = function (e) {
                        const d = e.detail || {};
                        if (d.divisions) self.divisions = d.divisions.filter(x => ! x.is_heading);
                        if (d.division !== undefined) self.mainDivision = d.division;
                        if (d.arrange !== undefined) {
                            self.arrange = !! d.arrange;
                            if (! self.arrange && self.open) self.close();
                        }
                    };
                    this._onTransfer = function (e) { self.dropped(e.detail || {}); };
                    this._onResize = function () { self.measure(); };

                    // Dedup across shell re-runs (CLAUDE.md → realtime rule 6).
                    ['bracket:loaded', 'bracket:state'].forEach(function (n) {
                        const key = '__drawSplit_' + n;
                        if (window[key]) document.removeEventListener(n, window[key]);
                        window[key] = self._onBoard;
                        document.addEventListener(n, self._onBoard);
                    });
                    if (window.__drawSplit_transfer) document.removeEventListener('bracket:transfer', window.__drawSplit_transfer);
                    window.__drawSplit_transfer = this._onTransfer;
                    document.addEventListener('bracket:transfer', this._onTransfer);
                    /* Opened or closed from a host's own menu — the draw desk's
                       ⋯ sheet on a phone (owner, 2026-10-18) — through this one
                       named event, so the menu never reaches into this scope. */
                    if (window.__drawSplit_toggle) window.removeEventListener('draw-split:toggle', window.__drawSplit_toggle);
                    window.__drawSplit_toggle = function () { self.toggle(); };
                    window.addEventListener('draw-split:toggle', window.__drawSplit_toggle);
                    if (window.__drawSplit_resize) window.removeEventListener('resize', window.__drawSplit_resize);
                    window.__drawSplit_resize = this._onResize;
                    window.addEventListener('resize', this._onResize);
                },

                // Same order and rows as the main board's division menu
                // (components/bracket/division-look), minus the division the
                // main board already shows.
                get choices() { return window.DrawDivisionLook.sort(this.divisions).filter(d => d.id !== this.mainDivision); },
                get shownChoices() { return window.DrawDivisionLook.filter(this.choices, this.flt); },
                get fltOn() { const f = this.flt; return !! (f.q || f.g !== 'all' || f.a1 !== '' || f.a2 !== '' || f.w1 !== '' || f.w2 !== ''); },
                fltClear() { this.flt = { q: '', g: 'all', a1: '', a2: '', w1: '', w2: '' }; },
                nameOf(id) { const d = this.divisions.find(x => x.id === id); return d ? d.name : ''; },
                get otherName() { return this.nameOf(this.other); },
                get otherDiv() { return this.divisions.find(x => x.id === this.other) || null; },
                divLine(d) { return window.DrawDivisionLook.line(d); },
                divStyle(d, on) { return window.DrawDivisionLook.style(d, on); },
                divInk(d) { return window.DrawDivisionLook.ink(d); },
                divIcon(d) { return window.DrawDivisionLook.icon(d); },

                measure() {
                    const tall = window.innerHeight > window.innerWidth;
                    if (tall !== this.tall) { this.tall = tall; this.announce(); }
                },

                announce() {
                    window.dispatchEvent(new CustomEvent('draw-split:changed', { detail: { open: this.open, tall: this.tall } }));
                    // Both boards re-fit to their new halves once the layout settles.
                    setTimeout(() => {
                        window.BracketBoard?.fit?.();
                        this.board?.fit?.();
                    }, 60);
                },

                toggle() { this.open ? this.close() : this.start(); },

                start() {
                    if (! this.arrange || ! window.BracketBoard?.create) return;
                    const first = this.choices[0];
                    if (! first) { window.showToast?.('info', this.cfg.text.noOther); return; }
                    this.tall = window.innerHeight > window.innerWidth;
                    this.open = true;
                    this.announce();
                    this.$nextTick(() => this.mount(this.other && this.other !== this.mainDivision ? this.other : first.id));
                },

                mount(id) {
                    const main = window.BracketBoard.cfg && window.BracketBoard.cfg();
                    if (! main) return;
                    this.other = id;

                    /* A FRESH viewport every time: mount() binds its gestures to
                       the element, and re-mounting onto the same one would stack
                       a second set of listeners on it. */
                    const host = this.$refs.host;
                    host.innerHTML = '';
                    const vp = document.createElement('div');
                    vp.id = this.cfg.id + '-split-vp';
                    vp.style.position = 'absolute';
                    vp.style.inset = '0';
                    host.appendChild(vp);

                    this.board = window.BracketBoard.create();
                    this.board.mount(Object.assign({}, main, {
                        viewportId: vp.id,
                        initialDivision: id,
                        realtimeKey: '__bracketRealtimeSplit',
                        transfer: true,
                    }));
                },

                // The second board finished loading: it is for moving people,
                // so it opens unlocked (the server still decides what it may do).
                paneLoaded() {
                    if (this.board && ! this.board.state().unlocked) this.board.setUnlocked(true);
                },

                choose(id) {
                    this.pickerOpen = false;
                    if (! this.board) return this.mount(id);
                    this.other = id;
                    this.board.show(id);
                },

                close() {
                    this.open = false;
                    this.pickerOpen = false;
                    if (window.__bracketRealtimeSplit) {
                        window.removeEventListener('realtime:events', window.__bracketRealtimeSplit);
                        window.__bracketRealtimeSplit = null;
                    }
                    if (this.$refs.host) this.$refs.host.innerHTML = '';
                    this.board = null;
                    this.announce();
                },

                /* ── A competitor landed on the OTHER board ─────────────────── */
                dropped(d) {
                    if (! this.open || ! d.competitorId) return;
                    const toSplit = this.$refs.host && this.$refs.host.contains(d.targetViewport);
                    const to = toSplit ? this.other : this.mainDivision;
                    if (! to || to === d.fromDivision) return;
                    this.xfer = {
                        competitorId: d.competitorId, name: d.name,
                        from: d.fromDivision, to: to, slot: d.to || { type: 'bench' },
                    };
                    this.mode = 'move';
                    this.charge = null;
                },

                cancel() { if (! this.busy) this.xfer = null; },

                get ready() { return this.mode === 'move' || this.charge !== null; },

                async confirm() {
                    if (! this.xfer || this.busy || ! this.ready) return;
                    this.busy = true;
                    try {
                        const res = await fetch(this.cfg.url, {
                            method: 'PUT',
                            credentials: 'same-origin',
                            headers: {
                                'Content-Type': 'application/json', 'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                            },
                            body: JSON.stringify({
                                from_category_id: this.xfer.from,
                                competitor_id: this.xfer.competitorId,
                                to_category_id: this.xfer.to,
                                to: this.xfer.slot,
                                mode: this.mode,
                                charge: this.mode === 'copy' ? !! this.charge : false,
                            }),
                        });
                        const data = await res.json().catch(() => ({}));
                        if (! res.ok || ! data.success) {
                            window.showToast?.('error', data.message || this.cfg.text.failed);
                        } else {
                            window.showToast?.('success', data.message);
                            this.xfer = null;
                        }
                    } catch (e) {
                        window.showToast?.('error', this.cfg.text.failed);
                    } finally {
                        this.busy = false;
                        // Both boards re-read the server either way — it is the truth.
                        window.BracketBoard?.reload?.();
                        this.board?.reload?.();
                    }
                },
            };
        });
    };

    if (window.Alpine) register();
    document.addEventListener('alpine:init', register);
})();
</script>
@endpush
@endonce

<div x-data="drawSplit(@js([
        'id' => $dpId,
        'url' => $transferUrl,
        'text' => [
            'failed' => __('events::events.bracket_move_failed'),
            'noOther' => __('events::events.transfer_pick_division'),
        ],
     ]))"
     style="display: contents">

    {{-- ===== The toggle — floats on the board while arranging ===== --}}
    <button type="button" x-show="arrange" x-cloak @click="toggle()"
            :aria-pressed="open"
            :class="open ? 'bk-ctl-on' : 'bk-ctl'"
            class="bk-split-toggle absolute top-16 start-3 z-40 inline-flex items-center gap-1.5 h-9 px-3.5 rounded-xl border
                   text-xs font-bold shadow-md active:scale-95 transition-all"
            title="{{ __('events::events.transfer_split_hint') }}">
        <i class="bi" :class="open ? 'bi-x-lg' : 'bi-layout-split'"></i>
        <span x-text="open ? @js(__('events::events.transfer_close_split')) : @js(__('events::events.transfer_split'))"></span>
    </button>

    {{-- ===== The second board ===== --}}
    <div x-show="open" x-cloak
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         class="absolute z-30 bk-frame flex flex-col"
         {{-- ⚠️ OBJECT syntax, never a string (owner, 2026-10-18: "the split
              is happening as I type"). A string :style REPLACES the whole style
              attribute, and x-show hides this pane with display:none in that
              same attribute — so when a phone's keyboard resized the screen and
              `tall` flipped, the rewrite wiped display:none and this closed
              pane appeared over the board. Objects set only their own
              properties (and undo the previous set), leaving x-show's alone. --}}
         :style="tall
            ? { 'inset-inline': '0', 'bottom': '0', 'height': '50%', 'border-top': '3px solid #d8b25f' }
            : { 'inset-block': '0', 'inset-inline-end': '0', 'width': '50%', 'border-inline-start': '3px solid #d8b25f' }"
         @bracket:loaded.stop="paneLoaded()"
         @bracket:state.stop
         @bracket:match.stop>

        {{-- Which division this half shows. A list, not a native select
             (Design Rule #4), and never the one already on the other half. --}}
        {{-- The same trigger and rows as the main board's division menu
             (x-draw-stage, via components/bracket/division-look): gender colour
             and icon, the name, then its ages and weights, the head-count.
             Names wrap, never cut — so the strip grows rather than clipping. --}}
        <div class="shrink-0 flex items-center gap-2 px-3 py-1.5 relative z-40" style="min-height:3rem">
            <div class="relative min-w-0 flex-1" @click.outside="pickerOpen = false" @keydown.escape.window="pickerOpen = false">
                <button type="button" @click="pickerOpen = ! pickerOpen" :aria-expanded="pickerOpen" aria-haspopup="listbox"
                        class="flex items-center gap-2 ps-2.5 pe-2 py-1.5 rounded-xl text-xs font-bold shadow-sm hover:shadow-md transition-all"
                        style="max-width: min(26rem, 100%); background:#fff; border:1px solid #e5e7eb;"
                        :style="'max-width: min(26rem, 100%);' + (otherDiv ? divStyle(otherDiv, false) : 'background:#fff;border:1px solid #e5e7eb;')">
                    <i class="bi shrink-0" x-show="otherDiv" :class="otherDiv ? divIcon(otherDiv) : ''" :style="otherDiv ? divInk(otherDiv) : ''"></i>
                    <span class="min-w-0 text-start break-words leading-snug" :style="otherDiv ? divInk(otherDiv) : ''">
                        <span x-text="otherName || @js(__('events::events.transfer_pick_division'))"></span><span
                              x-show="otherDiv && divLine(otherDiv)" x-text="otherDiv ? ' · ' + divLine(otherDiv) : ''"></span>
                    </span>
                    <span x-show="otherDiv" class="px-1.5 py-0.5 rounded-full text-[0.6rem] font-extrabold shrink-0" style="background:rgba(255,255,255,.75)"
                          x-text="otherDiv ? otherDiv.entrants : 0"></span>
                    <i class="bi bi-chevron-down text-muted-foreground transition-transform shrink-0" :class="pickerOpen && 'rotate-180'"></i>
                </button>
                <div x-show="pickerOpen" x-cloak x-transition.opacity.duration.120ms role="listbox"
                     class="absolute start-0 mt-2 overflow-y-auto overscroll-contain rounded-xl border border-gray-200 bg-white shadow-xl z-50 py-1"
                     style="width: min(34rem, calc(100vw - 1.5rem)); max-height: 60vh; -webkit-overflow-scrolling: touch; touch-action: pan-y;"
                     @wheel.stop @touchmove.stop>
                    @include('components.bracket.division-filter', ['count' => "shownChoices.length + ' / ' + choices.length"])
                    <p x-show="shownChoices.length === 0" class="px-4 py-6 text-center text-xs text-gray-500">{{ __('events::events.div_filter_none') }}</p>
                    <template x-for="d in shownChoices" :key="d.id">
                        <button type="button" role="option" :aria-selected="d.id === other" @click="choose(d.id)"
                                class="flex items-start gap-2.5 px-3 py-2 text-start text-xs font-bold transition-shadow hover:shadow-md"
                                style="display:flex; width:calc(100% - .75rem); margin:.25rem .375rem; border-radius:.6rem;"
                                :style="'display:flex; width:calc(100% - .75rem); margin:.25rem .375rem; border-radius:.6rem;' + divStyle(d, d.id === other)">
                            <i class="bi shrink-0" style="margin-top:.1rem" :class="divIcon(d)" :style="'margin-top:.1rem;' + divInk(d)"></i>
                            <span class="flex-1 min-w-0 break-words leading-snug" :style="divInk(d)">
                                <span x-text="d.name"></span><span
                                      x-show="divLine(d)" x-text="' · ' + divLine(d)"></span>
                            </span>
                            <span class="px-1.5 py-0.5 rounded-full text-[0.6rem] font-extrabold shrink-0" style="background:rgba(255,255,255,.75)"
                                  x-text="d.entrants"></span>
                            <i class="bi bi-check-lg shrink-0" x-show="d.id === other"></i>
                        </button>
                    </template>
                </div>
            </div>
        </div>

        <div x-ref="host" class="relative flex-1 min-h-0"></div>
    </div>

    {{-- ===== Move or copy? — the sheet a drop opens (Design Rule #8) ===== --}}
    <template x-teleport="body">
        <div x-show="xfer" x-cloak class="fixed inset-0 z-[80]" role="dialog" aria-modal="true">
            <div x-show="xfer" x-transition.opacity class="absolute inset-0 bg-black/40 backdrop-blur-[2px]" @click="cancel()"></div>

            <div x-show="xfer"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="translate-y-full"
                 x-transition:enter-end="translate-y-0"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="translate-y-0"
                 x-transition:leave-end="translate-y-full"
                 class="absolute inset-x-0 bottom-0 mx-auto w-full max-w-lg max-h-[92vh] flex flex-col bg-background rounded-t-3xl shadow-2xl overflow-hidden"
                 @keydown.escape.window="cancel()">

                <div class="flex-shrink-0 px-5 pt-3 pb-4 rounded-t-3xl text-white relative overflow-hidden"
                     style="background: linear-gradient(150deg, {{ $dpColor }}, {{ $dpColor }}b0);">
                    <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                    <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3"></div>
                    <div class="relative flex items-start gap-3">
                        <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0">
                            <i class="bi bi-arrow-left-right text-xl"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-lg font-black leading-tight">{{ __('events::events.transfer_sheet_title') }}</h3>
                            <p class="text-[12px] text-white/85 mt-0.5"
                               x-text="xfer ? @js(__('events::events.transfer_sheet_sub', ['name' => '__N__', 'division' => '__D__'])).replace('__N__', xfer.name).replace('__D__', nameOf(xfer.to)) : ''"></p>
                        </div>
                        <button type="button" @click="cancel()" aria-label="{{ __('shared.close') }}"
                                class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto px-4 py-4 space-y-3">
                    {{-- Move or copy: selection cards (a radio group). --}}
                    <template x-for="opt in ['move', 'copy']" :key="opt">
                        <button type="button" @click="mode = opt; if (opt === 'move') charge = null"
                                class="w-full text-start p-3.5 rounded-xl border bg-white flex items-center gap-3 transition-colors"
                                :class="mode === opt ? 'border-primary bg-primary/5' : 'border-gray-100 hover:bg-muted/60'">
                            <span class="w-10 h-10 rounded-xl grid place-items-center flex-shrink-0"
                                  :class="opt === 'move' ? 'bg-blue-50 text-blue-600' : 'bg-amber-50 text-amber-600'">
                                <i class="bi text-lg" :class="opt === 'move' ? 'bi-box-arrow-right' : 'bi-copy'"></i>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block font-bold text-sm text-foreground"
                                      x-text="opt === 'move' ? @js(__('events::events.transfer_move')) : @js(__('events::events.transfer_copy'))"></span>
                                <span class="block text-[11px] text-muted-foreground mt-0.5"
                                      x-text="xfer ? (opt === 'move' ? @js(__('events::events.transfer_move_hint', ['from' => '__F__', 'to' => '__T__'])) : @js(__('events::events.transfer_copy_hint', ['from' => '__F__', 'to' => '__T__']))).replace('__F__', nameOf(xfer.from)).replace('__T__', nameOf(xfer.to)) : ''"></span>
                            </span>
                            <span class="w-5 h-5 rounded-full border-2 grid place-items-center flex-shrink-0"
                                  :class="mode === opt ? 'border-primary' : 'border-gray-300'">
                                <span class="w-2.5 h-2.5 rounded-full bg-primary" x-show="mode === opt"></span>
                            </span>
                        </button>
                    </template>

                    {{-- Copy only: is the second entry charged? Asked every time. --}}
                    <div x-show="mode === 'copy'" x-transition class="pt-2 space-y-2">
                        <p class="text-xs font-bold text-foreground px-1">{{ __('events::events.transfer_charge_q') }}</p>
                        <template x-for="opt in [true, false]" :key="String(opt)">
                            <button type="button" @click="charge = opt"
                                    class="w-full text-start p-3 rounded-xl border bg-white flex items-center gap-3 transition-colors"
                                    :class="charge === opt ? 'border-primary bg-primary/5' : 'border-gray-100 hover:bg-muted/60'">
                                <i class="bi text-lg flex-shrink-0" :class="opt ? 'bi-cash-coin text-green-600' : 'bi-gift text-muted-foreground'"></i>
                                <span class="min-w-0 flex-1">
                                    <span class="block font-bold text-sm text-foreground"
                                          x-text="opt ? @js($price ? __('events::events.transfer_charge_yes', ['amount' => $price]) : __('events::events.transfer_charge_yes_free')) : @js(__('events::events.transfer_charge_no'))"></span>
                                    <span class="block text-[11px] text-muted-foreground mt-0.5"
                                          x-text="opt ? @js(__('events::events.transfer_charge_yes_hint')) : @js(__('events::events.transfer_charge_no_hint'))"></span>
                                </span>
                                <span class="w-5 h-5 rounded-full border-2 grid place-items-center flex-shrink-0"
                                      :class="charge === opt ? 'border-primary' : 'border-gray-300'">
                                    <span class="w-2.5 h-2.5 rounded-full bg-primary" x-show="charge === opt"></span>
                                </span>
                            </button>
                        </template>
                    </div>
                </div>

                <div class="flex-shrink-0 px-4 pt-3 border-t border-gray-100 bg-white"
                     style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
                    <button type="button" @click="confirm()" :disabled="busy || ! ready"
                            class="w-full bg-primary text-white px-4 py-3 rounded-xl hover:bg-primary/90 transition-colors font-bold text-sm
                                   disabled:opacity-50 inline-flex items-center justify-center gap-2">
                        <i class="bi" :class="busy ? 'bi-arrow-repeat animate-spin' : 'bi-check2'"></i>
                        <span x-text="busy ? @js(__('events::events.transfer_working')) : @js(__('events::events.transfer_confirm'))"></span>
                    </button>
                </div>
            </div>
        </div>
    </template>
</div>
