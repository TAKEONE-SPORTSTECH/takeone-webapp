{{--
    The draw on a SCORING TABLET — /tablet/{token}/draw (TabletDrawController).

    The same <x-draw-stage> and the same board every other draw page uses, fed
    through the tablet's own door: read-only on the pairing, editable for a
    few minutes after the event's tablet PIN (App\Events\Support\TabletDraw).

    Its own document rather than a layout, like the scoring console it opens
    from: a paired device loads none of the platform shell. The head is the
    event surface's own (entry/layout) so the board looks exactly the same.

    ── Unattended-device rules (CLAUDE.md) ──────────────────────────────────
    · A dead token never reaches this view — the controller sends it to /screen.
    · The board's data door answers 404 for a dead identity; the poll below
      reloads this page on that answer, which lands in the pairing room. A
      timeout or a 500 changes nothing.
    · Back always goes to this tablet's own console.
--}}
@php
    $c = \App\Support\Palette::safe($color);
    $rtl = app()->getLocale() === 'ar';
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ __('events::events.tablet_draw_button') }} · {{ $title }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="{{ asset('images/flags/flag-icons.css') }}">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>[x-cloak]{display:none!important}</style>
    @vite(['resources/css/app.css'])
    <style>
        body { font-family: 'Inter', system-ui, 'Noto Sans Arabic', sans-serif; background: #0b132b; }
        .td-ctl { width: 2.25rem; height: 2.25rem; border-radius: 9999px; display: inline-flex; align-items: center; justify-content: center;
                  background: rgba(255,255,255,.15); border: 1px solid rgba(255,255,255,.25); color: #fff; backdrop-filter: blur(4px);
                  transition: background-color .15s, transform .08s; flex: 0 0 auto; }
        .td-ctl:hover { background: rgba(255,255,255,.25); }
        .td-ctl:active, .td-pill:active { transform: scale(.95); }
        .td-pill { display: inline-flex; align-items: center; gap: .4rem; height: 2.25rem; padding: 0 .9rem; border-radius: .75rem;
                   font-size: .75rem; font-weight: 700; white-space: nowrap; flex: 0 0 auto; transition: background-color .15s, transform .08s; }
        .td-pill.is-light { background: #fff; color: #1e2c4f; }
        .td-pill.is-ghost { background: rgba(255,255,255,.15); border: 1px solid rgba(255,255,255,.25); color: #fff; }
        .td-pill.is-gold { background: linear-gradient(100deg,#edcd85,#d0a854); color: #1e2c4f; }
        .td-seg { display: inline-flex; padding: 3px; gap: 2px; border-radius: .8rem; background: rgba(255,255,255,.15);
                  border: 1px solid rgba(255,255,255,.25); flex: 0 0 auto; }
        .td-seg button { display: inline-flex; align-items: center; gap: .35rem; height: 1.85rem; padding: 0 .7rem; border-radius: .6rem;
                         font-size: .72rem; font-weight: 700; color: #fff; white-space: nowrap; transition: background-color .15s, color .15s; }
        .td-seg button.on { background: #fff; color: #1e2c4f; }
        .td-chip { display: inline-flex; align-items: center; gap: .35rem; padding: .25rem .6rem; border-radius: 9999px;
                   background: rgba(255,255,255,.2); font-size: .65rem; font-weight: 800; white-space: nowrap; }

        /* The keypad — fat targets for a finger at a scoring table. */
        .td-key { height: 4rem; border-radius: 1rem; background: #f3f5f9; color: #1e2c4f; font-size: 1.6rem; font-weight: 800;
                  display: flex; align-items: center; justify-content: center; transition: background-color .12s, transform .08s; }
        .td-key:active { transform: scale(.94); background: #e6e9f0; }
        .td-dot { width: .9rem; height: .9rem; border-radius: 9999px; border: 2px solid #c9d0db; transition: background-color .12s, border-color .12s; }
        .td-dot.on { background: #1e2c4f; border-color: #1e2c4f; }
        @keyframes td-shake { 10%,90%{transform:translateX(-2px)} 20%,80%{transform:translateX(4px)} 30%,50%,70%{transform:translateX(-8px)} 40%,60%{transform:translateX(8px)} }
        .td-shake { animation: td-shake .45s; }
        @media (prefers-reduced-motion: reduce) { .td-shake { animation: none; } }
    </style>
</head>
<body>
<div x-data="tabletDraw()">

    <x-draw-stage
        id="tdraw"
        :event="$event->uuid"
        :title="$title"
        :color="$c"
        :can-manage="false"
        :can-arrange="$editing"
        :data-url="route('tablet.draw.data', $token)"
        :arrange-url="route('tablet.draw.arrange', $token)"
        :clear-url="false"
        :admit-url="route('tablet.draw.admit', $token)"
        :transfer-url="route('tablet.draw.transfer', $token)"
        :board-can-manage="$editorManages"
        :outcome-url="str_replace('999999999', '__ID__', route('tablet.draw.outcome', [$token, 999999999]))"
        :entrant-url="str_replace('999999999', '__ID__', route('tablet.draw.entrant', [$token, 999999999]))">

        <x-slot:controls>
            <a href="{{ $consoleUrl }}" class="td-ctl" title="{{ __('events::events.tablet_back_console') }}"
               aria-label="{{ __('events::events.tablet_back_console') }}">
                <i class="bi bi-chevron-left rtl:rotate-180"></i>
            </a>

            <div class="min-w-0 flex-1">
                <p class="text-[10px] font-extrabold uppercase tracking-[0.14em] leading-none text-white/85">
                    <i class="bi bi-diagram-3-fill bracket-icon"></i> {{ __('events::events.tablet_draw_eyebrow') }}
                </p>
                <h1 class="text-sm font-bold text-white truncate leading-tight mt-0.5">{{ $title }}</h1>
            </div>

            @if($editing)
                {{-- Without a PIN nothing locks by itself, so the table says when it
                     has finished: Done closes the board to edits (a view, not a
                     permission — every edit is still authorised server-side) and
                     Edit opens it again. --}}
                @unless($pinRequired)
                    <button type="button" class="td-pill is-gold" x-show="viewOnly" x-cloak @click="resume()">
                        <i class="bi bi-pencil-square"></i><span>{{ __('events::events.tablet_edit_again') }}</span>
                    </button>
                @endunless
                <template x-if="! viewOnly"><span style="display:contents">
                <span class="td-chip hidden md:inline-flex" title="{{ __('events::events.tablet_editing_as', ['name' => $editorName]) }}">
                    <i class="bi bi-person-check-fill"></i>
                    <span class="truncate max-w-[9rem]">{{ $editorName }}</span>
                    @if($pinRequired)<span x-text="clock()"></span>@endif
                </span>
                @if($editorManages)
                    {{-- The board cannot both drag and decide: a tap in arrange
                         mode is the start of a move. So the two jobs are a
                         switch, and the switch says which one a tap does. --}}
                    <span class="td-seg">
                        <button type="button" :class="mode === 'move' && 'on'" @click="setMode('move')">
                            <i class="bi bi-arrows-move"></i><span class="hidden sm:inline">{{ __('events::events.tablet_mode_move') }}</span>
                        </button>
                        <button type="button" :class="mode === 'winner' && 'on'" @click="setMode('winner')">
                            <i class="bi bi-trophy-fill"></i><span class="hidden sm:inline">{{ __('events::events.tablet_mode_winner') }}</span>
                        </button>
                        <button type="button" :class="mode === 'unwin' && 'on'" @click="setMode('unwin')">
                            <i class="bi bi-arrow-counterclockwise"></i><span class="hidden sm:inline">{{ __('events::events.tablet_mode_unwin') }}</span>
                        </button>
                        <button type="button" :class="mode === 'numbers' && 'on'" @click="setMode('numbers')">
                            <i class="bi bi-123"></i><span class="hidden sm:inline">{{ __('events::events.tablet_mode_numbers') }}</span>
                        </button>
                        <button type="button" :class="mode === 'names' && 'on'" @click="setMode('names')">
                            <i class="bi bi-pencil-fill"></i><span class="hidden sm:inline">{{ __('events::events.tablet_names') }}</span>
                        </button>
                    </span>
                @endif
                <button type="button" class="td-pill is-light" @click="$dispatch('bracket-add:open')">
                    <i class="bi bi-person-plus-fill"></i><span class="hidden sm:inline">{{ __('events::events.tablet_late_entry') }}</span>
                </button>
                @if($pinRequired)
                <button type="button" class="td-pill is-ghost" @click="lock()" :disabled="busy">
                    <i class="bi bi-lock-fill"></i><span class="hidden sm:inline">{{ __('events::events.tablet_lock') }}</span>
                </button>
                @else
                <button type="button" class="td-pill is-ghost" @click="done()">
                    <i class="bi bi-check2-circle"></i><span>{{ __('events::events.tablet_done') }}</span>
                </button>
                @endif
                </span></template>
            @else
                {{-- Both ask for the PIN first; "Late entry" then opens the
                     add window by itself once the board is unlocked. --}}
                <button type="button" class="td-pill is-ghost" @click="openPad('edit')">
                    <i class="bi bi-pencil-square"></i><span>{{ __('events::events.tablet_edit_bout') }}</span>
                </button>
                <button type="button" class="td-pill is-gold" @click="openPad('add')">
                    <i class="bi bi-person-plus-fill"></i><span>{{ __('events::events.tablet_late_entry') }}</span>
                </button>
            @endif
        </x-slot:controls>

        @if($editing)
            <x-slot:overlay>
                {{-- How to edit is said as a TOAST when editing starts, then it goes
                     (owner, 2026-10-07) — never a card left over the draw, and never
                     in view mode. --}}
                <x-bracket-add-competitor
                    :event="$event->uuid"
                    id="tdadd"
                    :candidates-url="route('tablet.draw.candidates', $token)"
                    :clubs-url="route('tablet.draw.clubs', $token)"
                    :unnamed-url="route('tablet.draw.unnamed', $token)"
                    :clubs-link="false" />
            </x-slot:overlay>
        @endif
    </x-draw-stage>

    @if($editorManages)
    {{-- ===== Bout number (Design Rule #8 band) — a bout's number is its place
         on its mat, so saving is the running-order board's own move. ===== --}}
    <template x-teleport="body">
        <div x-show="num.open" x-cloak class="fixed inset-0 z-[80]" role="dialog" aria-modal="true"
             @keydown.escape.window="num.open = false">
            <div class="absolute inset-0 bg-black/50" x-show="num.open" x-transition.opacity @click="num.open = false"></div>

            <div x-show="num.open"
                 x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
                 x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
                 class="absolute inset-x-0 bottom-0 mx-auto w-full max-w-md max-h-[92vh] flex flex-col bg-white rounded-t-3xl shadow-2xl">

                <div class="flex-shrink-0 px-5 pt-3 pb-4 rounded-t-3xl text-white relative overflow-hidden"
                     style="background: linear-gradient(150deg, {{ $c }}, {{ $c }}b0);">
                    <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                    <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3"></div>
                    <div class="relative flex items-start gap-3">
                        <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0">
                            <i class="bi bi-123 text-2xl"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-lg font-black leading-tight">{{ __('events::events.tablet_number_title') }}</h3>
                            <p class="text-[12px] text-white/85 mt-0.5">{{ __('events::events.tablet_number_sub') }}</p>
                        </div>
                        <button type="button" @click="num.open = false" aria-label="{{ __('shared.close') }}"
                                class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto px-5 pt-5 pb-2">
                    <div x-show="num.loading" class="py-10 grid place-items-center text-gray-400">
                        <i class="bi bi-arrow-repeat animate-spin text-2xl"></i>
                    </div>

                    <template x-if="! num.loading && num.match">
                        <div>
                            <p x-show="num.match.decided" class="mb-4 text-sm text-amber-800 bg-amber-50 rounded-xl px-3.5 py-3 flex items-start gap-2">
                                <i class="bi bi-info-circle-fill mt-0.5"></i>{{ __('events::events.order_bout_decided') }}
                            </p>

                            <div x-show="! num.match.decided" class="mb-4 rounded-xl px-3.5 py-2.5 text-sm font-semibold flex items-center gap-2"
                                 :class="num.match.pinned ? 'bg-accent text-primary' : 'bg-muted text-muted-foreground'">
                                <i class="bi" :class="num.match.pinned ? 'bi-pin-angle-fill' : 'bi-magic'"></i>
                                <span class="flex-1" x-text="num.match.pinned ? @js(__('events::events.tablet_number_pinned')) : @js(__('events::events.tablet_number_auto'))"></span>
                                <button type="button" x-show="num.match.pinned" @click="releaseNumber()" :disabled="busy"
                                        class="flex-shrink-0 px-3 py-1.5 rounded-full border border-primary text-primary text-xs font-bold hover:bg-primary hover:text-white transition-colors disabled:opacity-50">
                                    {{ __('events::events.tablet_number_release') }}
                                </button>
                            </div>

                            <p class="text-[11px] font-bold uppercase tracking-wide text-muted-foreground mb-2">{{ __('events::events.tablet_number_mat') }}</p>
                            <div class="flex flex-wrap gap-2 mb-5">
                                <template x-for="m in num.mats" :key="m.court">
                                    <button type="button" @click="pickMat(m.court)" :disabled="num.match.decided"
                                            class="h-11 px-4 rounded-xl border text-sm font-bold transition-colors disabled:opacity-50"
                                            :class="num.court === m.court ? 'border-primary bg-primary/5 text-primary' : 'border-gray-200 text-gray-700 hover:bg-muted/60'"
                                            x-text="m.court"></button>
                                </template>
                            </div>

                            <p class="text-[11px] font-bold uppercase tracking-wide text-muted-foreground mb-2">{{ __('events::events.tablet_number_label') }}</p>
                            <div class="flex items-center justify-center gap-4">
                                <button type="button" class="td-key w-16" @click="step(-1)" :disabled="num.match.decided || num.no <= 1" aria-label="−">
                                    <i class="bi bi-dash-lg"></i>
                                </button>
                                <div class="text-center" style="min-width:6rem">
                                    <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="3"
                                           class="w-24 text-center text-5xl font-black text-gray-900 bg-transparent border-0 focus:ring-0 p-0"
                                           :value="num.no" :disabled="num.match.decided"
                                           @input="num.no = clampNo(parseInt($event.target.value.replace(/\D/g, ''), 10) || 1); $event.target.value = num.no">
                                    <p class="text-xs text-muted-foreground mt-1" x-text="@js(__('events::events.tablet_number_of', ['n' => '__N__'])).replace('__N__', maxNo())"></p>
                                </div>
                                <button type="button" class="td-key w-16" @click="step(1)" :disabled="num.match.decided || num.no >= maxNo()" aria-label="+">
                                    <i class="bi bi-plus-lg"></i>
                                </button>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="flex-shrink-0 px-5 pt-3 border-t border-gray-100" style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
                    <button type="button" @click="saveNumber()" :disabled="busy || num.loading || ! num.match || num.match.decided || ! num.court"
                            class="w-full h-12 rounded-xl bg-primary text-white font-bold text-base hover:bg-primary/90 transition-colors disabled:opacity-50 flex items-center justify-center gap-2">
                        <i class="bi bi-check2-circle"></i>{{ __('events::events.tablet_number_save') }}
                    </button>
                </div>
            </div>
        </div>
    </template>
    @endif

    {{-- The PIN keypad only exists while the PIN does (events.tablet_draw_pin). --}}
    @if($pinRequired)
    {{-- ===== The PIN keypad (Design Rule #8 band) ===== --}}
    <template x-teleport="body">
        <div x-show="pad" x-cloak class="fixed inset-0 z-[80]" role="dialog" aria-modal="true"
             @keydown.window="key($event)">
            <div class="absolute inset-0 bg-black/50" x-show="pad" x-transition.opacity @click="pad = false"></div>

            <div x-show="pad"
                 x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
                 x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
                 class="absolute inset-x-0 bottom-0 mx-auto w-full max-w-md flex flex-col bg-white rounded-t-3xl shadow-2xl">

                <div class="flex-shrink-0 px-5 pt-3 pb-4 rounded-t-3xl text-white relative overflow-hidden"
                     style="background: linear-gradient(150deg, {{ $c }}, {{ $c }}b0);">
                    <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                    <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3"></div>
                    <div class="relative flex items-start gap-3">
                        <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0">
                            <i class="bi bi-shield-lock-fill text-xl"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-lg font-black leading-tight">{{ __('events::events.tablet_pin_title') }}</h3>
                            <p class="text-[12px] text-white/85 mt-0.5">{{ __('events::events.tablet_pin_sub') }}</p>
                        </div>
                        <button type="button" @click="pad = false" aria-label="{{ __('shared.close') }}"
                                class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>

                <div class="px-6 pt-5" style="padding-bottom: calc(1.25rem + env(safe-area-inset-bottom));">
                    @unless($hasPin)
                        <p class="mb-4 text-sm text-amber-800 bg-amber-50 rounded-xl px-3.5 py-3 flex items-start gap-2">
                            <i class="bi bi-info-circle-fill mt-0.5"></i>{{ __('events::events.tablet_pin_none') }}
                        </p>
                    @endunless

                    <div class="flex justify-center gap-3 mb-2" :class="shake && 'td-shake'">
                        <template x-for="i in 8" :key="i">
                            <span class="td-dot" :class="i <= pin.length && 'on'" x-show="i <= Math.max(4, pin.length)"></span>
                        </template>
                    </div>
                    <p class="h-5 mb-3 text-center text-sm font-semibold text-red-600" x-text="error"></p>

                    <div class="grid grid-cols-3 gap-3">
                        <template x-for="d in ['1','2','3','4','5','6','7','8','9']" :key="d">
                            <button type="button" class="td-key" @click="press(d)" x-text="d"></button>
                        </template>
                        <button type="button" class="td-key" style="font-size:1rem" @click="pin = ''; error = ''">C</button>
                        <button type="button" class="td-key" @click="press('0')">0</button>
                        <button type="button" class="td-key" @click="back()" aria-label="⌫"><i class="bi bi-backspace"></i></button>
                    </div>

                    <button type="button" @click="unlock()" :disabled="busy || pin.length < 4"
                            class="mt-4 w-full h-12 rounded-xl bg-primary text-white font-bold text-base hover:bg-primary/90 transition-colors disabled:opacity-50 flex items-center justify-center gap-2">
                        <i class="bi bi-unlock-fill"></i>{{ __('events::events.tablet_unlock') }}
                    </button>
                </div>
            </div>
        </div>
    </template>
    @endif
</div>

@include('partials.toast-host')
{{-- Stacks AFTER the body: this is a page, not a layout, so a stack printed
     in <head> would be emptied before the components below pushed into it. --}}
@stack('styles')
@stack('scripts')

<script>
function tabletDraw() {
    return {
        num: { open: false, loading: false, match: null, mats: [], court: null, no: 1 },
        pad: false, pin: '', error: '', busy: false, shake: false, hint: true, edit: false, intent: null, seen: null, mode: 'move',
        left: {{ (int) $secondsLeft }},
        editing: @json($editing),
        // No-PIN mode (owner, 2026-10-07): the draw always OPENS in view mode.
        // Edit unlocks it; Done locks it back. Every change is saved the moment
        // it is made, so Done has nothing left to save — it only locks.
        viewOnly: ! @json($pinRequired),
        editHint: @json($editorManages ? __('events::events.tablet_hint_manage') : __('events::events.tablet_hint_arrange')),
        sayHint() { if (window.showToast) window.showToast('info', this.editHint); },

        init() {
            const vp = document.getElementById('tdraw-bracket-viewport');

            // Unlocked by PIN means the board opens ready to drag — the PIN WAS
            // the padlock, a second one would just be another tap.
            if (this.editing && vp) {
                vp.addEventListener('bracket:loaded', () => {
                    window.BracketBoard && window.BracketBoard.setUnlocked && window.BracketBoard.setUnlocked(! this.viewOnly);
                    if (! this.viewOnly) this.sayHint();
                    // "Late entry" was what they asked for before the PIN.
                    let after = null;
                    try { after = sessionStorage.getItem('tdraw-after'); sessionStorage.removeItem('tdraw-after'); } catch (e) {}
                    if (after === 'add') window.dispatchEvent(new CustomEvent('bracket-add:open'));
                }, { once: true });
                vp.addEventListener('bracket:state', (e) => {
                    const d = e.detail || {};
                    this.edit = !! d.edit;
                    if (this.mode === 'names' && ! d.edit) this.mode = d.arrange ? 'move' : 'winner';
                });
            }

            // Only a PIN unlock runs out; without a PIN there is nothing to count down.
            if (this.editing && @json($pinRequired)) {
                setInterval(() => {
                    this.left = Math.max(0, this.left - 1);
                    if (this.left === 0) window.location.reload();   // the grant ran out
                }, 1000);
            }

            // "Bout no." mode: a tap on a bout opens its number sheet. Caught on
            // the way DOWN (capture), so the board never also reads it as a
            // winner tap; a press that wandered was a pan, not a tap.
            if (this.editing && @json($editorManages) && ! window.__tdrawNumTap) {
                // The board captures the pointer while it is pressed (to pan), so
                // the click that follows is addressed to the VIEWPORT, not to the
                // card. The card is therefore read where the press began.
                const SEL = '#tdraw-bracket-viewport .bk-match[data-match-id]';
                let downAt = null, downCard = null;
                document.addEventListener('pointerdown', (e) => {
                    downAt = [e.clientX, e.clientY];
                    downCard = e.target.closest ? e.target.closest(SEL) : null;
                }, true);
                window.__tdrawNumTap = (e) => {
                    if (this.mode !== 'numbers' || this.viewOnly) return;
                    const card = (e.target.closest && e.target.closest(SEL)) || downCard;
                    if (! card) return;
                    e.stopPropagation(); e.preventDefault();
                    if (downAt && Math.hypot(e.clientX - downAt[0], e.clientY - downAt[1]) > 8) return;
                    this.openNumber(Number(card.dataset.matchId));
                };
                document.addEventListener('click', window.__tdrawNumTap, true);
            }

            // Keep a READING tablet current, and notice a tablet that has been
            // unpaired: 404 is the only answer that means "this identity is
            // gone" — reload, and the page sends it to the pairing room.
            setInterval(async () => {
                try {
                    const r = await fetch(@js(route('tablet.draw.data', $token)), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
                    if (r.status === 404) { window.location.reload(); return; }
                    if (! r.ok) return;
                    // Redraw only when the draw actually CHANGED: a redraw replays
                    // the cards' entrance, and every 30 seconds that read as the
                    // page refreshing itself. Never while somebody is editing.
                    const text = await r.text();
                    if (this.seen !== null && text !== this.seen && (! this.editing || this.viewOnly) && window.BracketBoard) {
                        window.BracketBoard.reload(true);
                    }
                    this.seen = text;
                } catch (e) { /* the wifi, not the tablet — leave it alone */ }
            }, 30000);
        },

        /* move: drag competitors (the board's arrange mode)
           winner: a tap on a competitor sets the bout's winner / score
           names: a tap on a name opens it for correction */
        setMode(m) {
            const BB = window.BracketBoard;
            if (! BB) return;
            this.mode = m;
            // Remove winner is Winner with every tap meaning "take it back".
            if (BB.setDecideMode) BB.setDecideMode(m === 'unwin' ? 'clear' : 'set');
            if (m === 'unwin') m = 'winner';
            // How to set — and take back — a winner, said once when the mode opens.
            if (this.mode === 'winner' && window.showToast) window.showToast('info', @js(__('events::events.tablet_hint_winner')));
            if (this.mode === 'unwin' && window.showToast) window.showToast('info', @js(__('events::events.tablet_hint_unwin')));
            if (this.mode === 'numbers' && window.showToast) window.showToast('info', @js(__('events::events.tablet_hint_numbers')));
            if (m === 'names') { if (! this.edit) BB.toggleEdit(); return; }
            if (this.edit) BB.toggleEdit();
            BB.toggleArrange(m === 'move');
        },

        async openNumber(id) {
            if (! id) return;
            this.num = { open: true, loading: true, match: null, mats: [], court: null, no: 1 };
            try {
                const r = await fetch(@js(route('tablet.draw.number', [$token, 999999999])).replace('999999999', id),
                    { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
                if (r.status === 404) { window.location.reload(); return; }
                const d = await r.json().catch(() => ({}));
                if (! r.ok || ! d.success) {
                    this.num.open = false;
                    if (d.locked) { window.location.reload(); return; }
                    window.showToast && window.showToast('error', d.message || @js(__('events::events.action_failed')));
                    return;
                }
                this.num.match = d.match;
                this.num.mats = d.mats || [];
                this.num.court = d.match.court || (this.num.mats[0] && this.num.mats[0].court) || null;
                this.num.no = this.clampNo(d.match.no || this.maxNo());
            } catch (e) {
                this.num.open = false;
                window.showToast && window.showToast('error', @js(__('events::events.action_failed')));
            } finally { this.num.loading = false; }
        },
        /* The highest number this bout can take on the chosen mat: that mat's
           bouts, plus one when the bout is joining it from somewhere else. */
        maxNo() {
            const m = this.num.mats.find(x => x.court === this.num.court);
            const n = m ? m.count : 0;
            return Math.max(1, this.num.match && this.num.match.court === this.num.court ? n : n + 1);
        },
        clampNo(n) { return Math.max(1, Math.min(this.maxNo(), n || 1)); },
        step(d) { this.num.no = this.clampNo(this.num.no + d); },
        pickMat(court) { this.num.court = court; this.num.no = this.clampNo(this.num.no); },
        async releaseNumber() {
            if (this.busy || ! this.num.match) return;
            this.busy = true;
            try {
                const r = await fetch(@js(route('tablet.draw.number.release', [$token, 999999999])).replace('999999999', this.num.match.id), {
                    method: 'DELETE', credentials: 'same-origin',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest',
                               'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                });
                if (r.status === 404) { window.location.reload(); return; }
                const d = await r.json().catch(() => ({}));
                if (d.locked) { window.location.reload(); return; }
                if (! r.ok || ! d.success) {
                    window.showToast && window.showToast('error', d.message || @js(__('events::events.action_failed')));
                    return;
                }
                this.num.open = false;
                window.showToast && window.showToast('success', d.message || @js(__('events::events.order_saved')));
                window.BracketBoard && window.BracketBoard.reload(true);
            } catch (e) {
                window.showToast && window.showToast('error', @js(__('events::events.action_failed')));
            } finally { this.busy = false; }
        },
        async saveNumber() {
            if (this.busy || ! this.num.match || ! this.num.court) return;
            this.busy = true;
            try {
                const r = await fetch(@js(route('tablet.draw.renumber', $token)), {
                    method: 'PUT', credentials: 'same-origin',
                    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest',
                               'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body: JSON.stringify({ match: this.num.match.id, court: this.num.court, no: this.num.no, day: this.num.match.day }),
                });
                if (r.status === 404) { window.location.reload(); return; }
                const d = await r.json().catch(() => ({}));
                if (d.locked) { window.location.reload(); return; }
                if (! r.ok || ! d.success) {
                    window.showToast && window.showToast('error', d.message || @js(__('events::events.action_failed')));
                    return;
                }
                this.num.open = false;
                window.showToast && window.showToast('success', d.message || @js(__('events::events.order_saved')));
                window.BracketBoard && window.BracketBoard.reload(true);
            } catch (e) {
                window.showToast && window.showToast('error', @js(__('events::events.action_failed')));
            } finally { this.busy = false; }
        },

        done() {
            const BB = window.BracketBoard;
            if (BB && BB.setDecideMode) BB.setDecideMode('set');
            if (BB && BB.setUnlocked) BB.setUnlocked(false);
            this.viewOnly = true; this.mode = 'move';
        },
        resume() {
            const BB = window.BracketBoard;
            this.viewOnly = false; this.mode = 'move';
            if (BB && BB.setUnlocked) BB.setUnlocked(true);
            this.sayHint();
        },

        clock() {
            const m = Math.floor(this.left / 60), s = this.left % 60;
            return m + ':' + String(s).padStart(2, '0');
        },

        openPad(intent) { this.intent = intent || null; this.pin = ''; this.error = ''; this.pad = true; },
        press(d) { if (this.pin.length < 8) { this.pin += d; this.error = ''; } },
        back() { this.pin = this.pin.slice(0, -1); },
        key(e) {
            if (! this.pad) return;
            if (/^\d$/.test(e.key)) this.press(e.key);
            else if (e.key === 'Backspace') this.back();
            else if (e.key === 'Enter') this.unlock();
        },

        async post(url, body) {
            const r = await fetch(url, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest',
                           'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                body: JSON.stringify(body || {}),
            });
            if (r.status === 404) { window.location.reload(); return null; }
            return { ok: r.ok, data: await r.json().catch(() => ({})) };
        },

        async unlock() {
            if (this.busy || this.pin.length < 4) return;
            this.busy = true;
            try {
                const res = await this.post(@js(route('tablet.draw.unlock', $token)), { pin: this.pin });
                if (! res) return;
                if (res.ok && res.data.success) {
                    try { if (this.intent === 'add') sessionStorage.setItem('tdraw-after', 'add'); } catch (e) {}
                    window.location.reload();
                    return;
                }
                this.error = res.data.message || @js(__('events::events.tablet_pin_wrong'));
                this.pin = '';
                this.shake = true; setTimeout(() => this.shake = false, 500);
                navigator.vibrate && navigator.vibrate(120);
            } catch (e) {
                this.error = @js(__('events::events.action_failed'));
            } finally { this.busy = false; }
        },

        async lock() {
            this.busy = true;
            try { await this.post(@js(route('tablet.draw.lock', $token))); } catch (e) {}
            window.location.reload();
        },
    };
}
</script>
</body>
</html>
