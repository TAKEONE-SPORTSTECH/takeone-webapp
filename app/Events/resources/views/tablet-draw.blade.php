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
                <span class="td-chip hidden md:inline-flex" title="{{ __('events::events.tablet_editing_as', ['name' => $editorName]) }}">
                    <i class="bi bi-person-check-fill"></i>
                    <span class="truncate max-w-[9rem]">{{ $editorName }}</span>
                    <span x-text="clock()"></span>
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
                        <button type="button" :class="mode === 'names' && 'on'" @click="setMode('names')">
                            <i class="bi bi-pencil-fill"></i><span class="hidden sm:inline">{{ __('events::events.tablet_names') }}</span>
                        </button>
                    </span>
                @endif
                <button type="button" class="td-pill is-light" @click="$dispatch('bracket-add:open')">
                    <i class="bi bi-person-plus-fill"></i><span class="hidden sm:inline">{{ __('events::events.tablet_late_entry') }}</span>
                </button>
                <button type="button" class="td-pill is-ghost" @click="lock()" :disabled="busy">
                    <i class="bi bi-lock-fill"></i><span class="hidden sm:inline">{{ __('events::events.tablet_lock') }}</span>
                </button>
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
                <div x-show="hint" x-cloak x-transition.opacity
                     class="absolute top-3 start-3 z-30 max-w-[min(34rem,calc(100%-14rem))] flex items-start gap-2.5 rounded-xl px-3.5 py-2.5 text-xs font-semibold shadow-lg"
                     style="background:#fdf6e6;color:#5c4813;border:1px solid #d8b25f;">
                    <i class="bi bi-hand-index-thumb-fill mt-0.5"></i>
                    <span>{{ $editorManages ? __('events::events.tablet_hint_manage') : __('events::events.tablet_hint_arrange') }}</span>
                    <button type="button" @click="hint = false" class="ms-1 opacity-60 hover:opacity-100" aria-label="{{ __('shared.close') }}"><i class="bi bi-x-lg"></i></button>
                </div>
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
</div>

@include('partials.toast-host')
{{-- Stacks AFTER the body: this is a page, not a layout, so a stack printed
     in <head> would be emptied before the components below pushed into it. --}}
@stack('styles')
@stack('scripts')

<script>
function tabletDraw() {
    return {
        pad: false, pin: '', error: '', busy: false, shake: false, hint: true, edit: false, intent: null, seen: null, mode: 'move',
        left: {{ (int) $secondsLeft }},
        editing: @json($editing),

        init() {
            const vp = document.getElementById('tdraw-bracket-viewport');

            // Unlocked by PIN means the board opens ready to drag — the PIN WAS
            // the padlock, a second one would just be another tap.
            if (this.editing && vp) {
                vp.addEventListener('bracket:loaded', () => {
                    window.BracketBoard && window.BracketBoard.setUnlocked && window.BracketBoard.setUnlocked(true);
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

            if (this.editing) {
                setInterval(() => {
                    this.left = Math.max(0, this.left - 1);
                    if (this.left === 0) window.location.reload();   // the grant ran out
                }, 1000);
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
                    if (this.seen !== null && text !== this.seen && ! this.editing && window.BracketBoard) {
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
            if (m === 'names') { if (! this.edit) BB.toggleEdit(); return; }
            if (this.edit) BB.toggleEdit();
            BB.toggleArrange(m === 'move');
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
