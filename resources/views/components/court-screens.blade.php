{{--
    Hall screens — the Raspberry Pi wall displays showing this event's mats.

    On the page it is a panel: one row per paired screen, each saying which mat
    it shows and whether it is actually alive, plus the button that adds one.
    Pairing itself lives in a sheet (a centered dialog from `sm:` up): scan the
    code on the screen, then say which mat it stands next to.

    Why a scan AND a typed code: the screen prints its code under the QR for a
    reason — hall lighting, a dirty lens, a phone whose engine has no
    BarcodeDetector. Either route ends in the same six characters, so neither is
    a fallback bolted on; they are two ways into one step.

    Standalone: all state, requests and DOM updates live in this file's Alpine
    component. It borrows exactly one shared thing — the app's QR scanner
    overlay (partials/qr-scanner), opened in hand-back mode so the value comes
    home on `court-screens:scanned` instead of navigating away.

    Security posture (mirrored server-side on every endpoint — what this renders
    is cosmetic): the pairing code is public by design, printed a metre tall on a
    wall. It is not the credential. The credential is the session plus canManage
    on THIS event, which is why the event is in the URL and never in the form.

    Writes patch in place (No Page Reload rule) and other organisers running the
    same event are nudged over MQTT (`realtime:events`, {action:'screens'}) — a
    refresh signal, so each console re-fetches what IT is allowed to see.

    Props:
      event   ClubEvent uuid (public key)
      mats    string[]  the mats this event actually runs on, from the draw
      screens array of {id,label,court,live,last_seen}
      color   event colour, for the accents
--}}
@props([
    'event',
    'mats' => [],
    'screens' => [],
    // Which slots this event's package can actually SERVE. Offering one it
    // cannot ends with a screen in a hall showing an error and no way back, so
    // the panel asks rather than assumes — score control exists for Taekwondo
    // and not (yet) for Karate.
    'surfaces' => ['bout', 'queue', 'control'],
    // The address to open ON a screen so it joins THIS event's fleet. Shown in
    // the pairing sheet because a screen has to exist before it has a code.
    'newUrl' => null,
    'color' => '#7c3aed',
])
@php
    // Reaches a style attribute — whitelisted like every other event surface.
    $csColor = preg_match('/^#[0-9a-fA-F]{3,8}$/', (string) $color) ? $color : '#7c3aed';
@endphp

<div x-data="courtScreens({
        screens: @js(array_values($screens)),
        mats: @js(array_values($mats)),
        listUrl: @js(route('me.events.screens', $event)),
        pairUrl: @js(route('me.events.screens.pair', $event)),
        base: @js(url('/me/events/'.$event.'/screens')),
        allowed: @js(array_values($surfaces)),
        eventKey: @js($event),
     })"
     x-init="watch()"
     @court-screens:scanned.window="onScan($event.detail)"
     @realtime:events.window="onRealtime($event.detail)"
     class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">

    {{-- Header: what this panel is, and the count as its standing --}}
    <div class="px-4 pt-4 pb-3 flex items-start gap-3">
        <span class="w-10 h-10 rounded-2xl grid place-items-center flex-shrink-0 text-white"
              style="background: linear-gradient(140deg, {{ $csColor }}, {{ $csColor }}b0);">
            <i class="bi bi-tv-fill"></i>
        </span>
        <div class="min-w-0 flex-1">
            <h3 class="text-sm font-bold text-foreground">{{ __('personal.event_screens_title') }}</h3>
            <p class="text-[11px] text-muted-foreground mt-0.5">{{ __('personal.event_screens_sub') }}</p>
        </div>
        <span x-show="screens.length" x-cloak
              class="flex-shrink-0 px-2.5 py-1 rounded-full text-[11px] font-bold bg-muted text-muted-foreground"
              x-text="screens.length"></span>
    </div>

    {{-- ── One block per mat, three slots each ──────────────────────────────
         A hall is not a bag of screens: every mat has a scoreboard, an
         upcoming-matches board and a scoring table, and the question an
         organiser actually has is "which of Mat 2's three is still missing?".
         A flat list could not answer that — it could only show what already
         existed, which is the half of the truth that needs no attention.

         So the panel is the hall's own shape. Every slot is drawn whether or
         not it is filled: a filled one says it is alive and offers to unpair,
         an empty one offers to pair. Pressing Pair on a slot already knows the
         mat and the job, so the sheet has one thing left to ask. --}}
    <div x-show="! mats.length" x-cloak class="px-4 pb-4">
        <div class="rounded-2xl border border-dashed border-gray-200 bg-muted/30 px-4 py-6 text-center">
            <i class="bi bi-diagram-3 text-2xl text-muted-foreground/50"></i>
            <p class="text-xs font-bold text-foreground mt-2">{{ __('personal.event_screens_no_mats') }}</p>
        </div>
    </div>

    <div class="px-4 pb-4 space-y-3">
        <template x-for="m in mats" :key="m">
            <div class="rounded-2xl border border-gray-100 overflow-hidden">

                {{-- The mat plate borrows the hall board's own look — dark,
                     condensed, all caps — so this reads as the thing bolted to
                     the wall rather than as another list header. --}}
                <div class="flex items-center gap-2 px-3 py-2 bg-[#101016]">
                    <span class="text-white text-xs font-black uppercase tracking-wider" x-text="m"></span>
                    <span class="flex-1"></span>
                    <span class="text-[10px] font-bold uppercase tracking-wider"
                          :class="filledCount(m) === surfaces.length ? 'text-green-400' : 'text-white/40'"
                          x-text="filledCount(m) + '/' + surfaces.length"></span>
                </div>

                <template x-for="sf in surfaces" :key="m + '|' + sf.key">
                    <div class="flex items-center gap-3 px-3 py-2.5 border-t border-gray-100 first:border-t-0">

                        <span class="w-9 h-9 rounded-xl grid place-items-center flex-shrink-0"
                              :class="screenFor(m, sf.key) ? 'bg-primary/10 text-primary' : 'bg-muted text-muted-foreground/60'">
                            <i class="bi" :class="sf.icon"></i>
                        </span>

                        <span class="min-w-0 flex-1">
                            <span class="flex items-center gap-1.5">
                                <span class="text-xs font-bold text-foreground truncate" x-text="sf.label"></span>
                                {{-- The slot that can WRITE results is called
                                     out: "which of these is the scoring tablet"
                                     is the question asked in a hurry. --}}
                                <span x-show="sf.key === 'control'"
                                      class="flex-shrink-0 px-1.5 py-0.5 rounded-md text-[9px] font-black uppercase tracking-wider bg-amber-100 text-amber-700"
                                      x-text="@js(__('personal.event_screens_writes'))"></span>
                            </span>

                            {{-- A live screen pulses; a silent one is a flat
                                 amber dot. A board nobody has looked at since
                                 setup is exactly what to catch before the hall
                                 fills. --}}
                            <span x-show="screenFor(m, sf.key)" x-cloak class="flex items-center gap-1.5 mt-0.5">
                                <span class="relative flex w-2 h-2 flex-shrink-0">
                                    <span x-show="screenFor(m, sf.key)?.live" class="absolute inline-flex w-full h-full rounded-full bg-green-500 opacity-60 animate-ping"></span>
                                    <span class="relative inline-flex w-2 h-2 rounded-full"
                                          :class="screenFor(m, sf.key)?.live ? 'bg-green-500' : 'bg-amber-500'"></span>
                                </span>
                                <span class="text-[11px] font-bold"
                                      :class="screenFor(m, sf.key)?.live ? 'text-green-600' : 'text-amber-600'"
                                      x-text="screenFor(m, sf.key)?.live ? @js(__('personal.event_screens_live')) : (screenFor(m, sf.key)?.last_seen || @js(__('personal.event_screens_never_seen')))"></span>
                            </span>

                            <span x-show="! screenFor(m, sf.key)" x-cloak
                                  class="block text-[11px] text-muted-foreground/70 mt-0.5"
                                  x-text="sf.hint"></span>
                        </span>

                        {{-- Filled: unpair. Empty: pair, already knowing both
                             the mat and the job. --}}
                        <template x-if="screenFor(m, sf.key)">
                            <button type="button" @click="unpair(screenFor(m, sf.key))" :disabled="busy === screenFor(m, sf.key).id"
                                    class="m-press flex-shrink-0 w-9 h-9 rounded-xl grid place-items-center text-muted-foreground hover:bg-red-50 hover:text-red-600 transition-colors disabled:opacity-40"
                                    :aria-label="@js(__('personal.event_screens_unpair'))">
                                <i class="bi" :class="busy === screenFor(m, sf.key).id ? 'bi-arrow-repeat animate-spin' : 'bi-x-circle'"></i>
                            </button>
                        </template>

                        <template x-if="! screenFor(m, sf.key)">
                            <button type="button" @click="start(m, sf.key)"
                                    class="m-press flex-shrink-0 h-9 px-3 rounded-xl text-white text-[11px] font-bold inline-flex items-center gap-1.5"
                                    style="background: linear-gradient(140deg, {{ $csColor }}, {{ $csColor }}b0);">
                                <i class="bi bi-qr-code-scan"></i>{{ __('personal.event_screens_pair_short') }}
                            </button>
                        </template>
                    </div>
                </template>
            </div>
        </template>

        {{-- Screens that predate the three slots, or sit on a mat the draw no
             longer has. Shown rather than hidden: a board is on a wall
             somewhere whatever this panel thinks, and one that cannot be seen
             here cannot be unpaired either. --}}
        <template x-if="strays().length">
            <div class="rounded-2xl border border-dashed border-gray-200 overflow-hidden">
                <div class="px-3 py-2 bg-muted/50">
                    <span class="text-[10px] font-black uppercase tracking-wider text-muted-foreground">{{ __('personal.event_screens_other') }}</span>
                </div>
                <template x-for="s in strays()" :key="s.id">
                    <div class="flex items-center gap-3 px-3 py-2.5 border-t border-gray-100">
                        <span class="flex-shrink-0 px-2.5 py-1.5 rounded-lg bg-[#101016] text-white text-[10px] font-black uppercase tracking-wider"
                              x-text="s.court"></span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-xs font-bold text-foreground truncate" x-text="s.surface_label"></span>
                            <span class="block text-[11px] text-muted-foreground truncate"
                                  x-text="s.live ? @js(__('personal.event_screens_live')) : (s.last_seen || @js(__('personal.event_screens_never_seen')))"></span>
                        </span>
                        <button type="button" @click="unpair(s)" :disabled="busy === s.id"
                                class="m-press flex-shrink-0 w-9 h-9 rounded-xl grid place-items-center text-muted-foreground hover:bg-red-50 hover:text-red-600 transition-colors disabled:opacity-40"
                                :aria-label="@js(__('personal.event_screens_unpair'))">
                            <i class="bi" :class="busy === s.id ? 'bi-arrow-repeat animate-spin' : 'bi-x-circle'"></i>
                        </button>
                    </div>
                </template>
            </div>
        </template>
    </div>

    {{-- Pairing — a sheet on a phone, a centered dialog on a wide screen.
         Teleported to <body> so the mobile shell's stagger transform can't
         become the containing block for a fixed overlay and clip it. --}}
    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 z-[70] flex flex-col justify-end sm:items-center sm:justify-center sm:p-4">
            <div x-show="open" x-transition.opacity class="absolute inset-0 bg-black/50" @click="close()"></div>

            <div x-show="open"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="translate-y-full sm:translate-y-4 sm:scale-95 sm:opacity-0"
                 x-transition:enter-end="translate-y-0 sm:scale-100 sm:opacity-100"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="translate-y-0 sm:scale-100 sm:opacity-100"
                 x-transition:leave-end="translate-y-full sm:translate-y-4 sm:scale-95 sm:opacity-0"
                 class="relative max-h-[92vh] w-full sm:max-w-md flex flex-col bg-background rounded-t-3xl sm:rounded-2xl shadow-2xl overflow-hidden">

                <div class="flex-shrink-0 px-5 pt-3 pb-3 border-b border-gray-100">
                    <div class="w-10 h-1 rounded-full bg-gray-300 mx-auto mb-3 sm:hidden"></div>
                    <div class="flex items-center justify-between gap-3">
                        {{-- The sheet says WHICH screen it is about. Pressing a
                             slot is the choice; repeating it as two more steps
                             inside would be asking twice. --}}
                        <h3 class="font-bold text-foreground min-w-0 truncate">
                            <span x-text="court"></span>
                            <span class="text-muted-foreground font-medium"> · </span>
                            <span x-text="surfaceLabel()"></span>
                        </h3>
                        <button type="button" @click="close()" aria-label="{{ __('shared.close') }}"
                                class="w-8 h-8 rounded-full grid place-items-center text-muted-foreground hover:bg-muted flex-shrink-0"><i class="bi bi-x-lg"></i></button>
                    </div>
                </div>

                {{-- Body scrolls; the action below stays reachable on a phone --}}
                <div class="flex-1 overflow-y-auto min-h-0 px-5 py-4 space-y-5">

                    {{-- Step 0 — the screen has to exist before it has a code.

                         Not decoration: a fleet is per sport, so a screen must
                         be opened at THIS event's address or its code will not
                         be found here. Getting that wrong used to surface as
                         "that code does not match a screen waiting to be
                         paired", which blames the code and hides the mistake. --}}
                    @if ($newUrl)
                        <details class="rounded-2xl border border-gray-100 bg-muted/30 overflow-hidden">
                            <summary class="px-4 py-3 cursor-pointer text-xs font-bold text-foreground flex items-center gap-2">
                                <i class="bi bi-1-circle text-primary"></i>
                                {{ __('personal.event_screens_open_first') }}
                            </summary>
                            <div class="px-4 pb-4 text-center">
                                <p class="text-[11px] text-muted-foreground mb-3">{{ __('personal.event_screens_open_hint') }}</p>
                                <div class="inline-block bg-white p-2.5 rounded-xl border border-gray-100">
                                    {!! \App\Support\Qr::svg($newUrl, 150, 2) !!}
                                </div>
                                <p class="mt-3 text-[11px] font-mono text-foreground break-all select-all">{{ $newUrl }}</p>
                            </div>
                        </details>
                    @endif

                    {{-- Step 1 — the code --}}
                    <div>
                        <button type="button" @click="scan()"
                                class="m-press w-full rounded-2xl border-2 border-dashed border-primary/30 bg-primary/5 px-4 py-5 text-center hover:bg-primary/10 transition-colors">
                            <i class="bi bi-qr-code-scan text-2xl text-primary"></i>
                            <span class="block text-sm font-bold text-primary mt-1.5">{{ __('personal.event_screens_scan') }}</span>
                        </button>

                        <div class="flex items-center gap-3 my-3">
                            <span class="h-px flex-1 bg-gray-200"></span>
                            <span class="text-[11px] text-muted-foreground">{{ __('personal.event_screens_code_or') }}</span>
                            <span class="h-px flex-1 bg-gray-200"></span>
                        </div>

                        {{-- Uppercased and stripped as it is typed: the alphabet
                             has no vowels and no 0/O/1/I, so a code read off a
                             wall cannot be mistyped into a different valid one. --}}
                        <input type="text" x-model="code" @input="code = clean(code)"
                               inputmode="latin" autocapitalize="characters" autocomplete="off" spellcheck="false" maxlength="6"
                               placeholder="{{ __('personal.event_screens_code_ph') }}"
                               class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-center text-lg font-black tracking-[0.4em] uppercase focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>

                </div>

                {{-- Sticky action, clear of the home indicator --}}
                <div class="flex-shrink-0 px-5 pt-3 border-t border-gray-100 bg-background"
                     style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
                    <button type="button" @click="submit()" :disabled="busy === 'pair'"
                            class="m-press w-full h-12 rounded-xl text-white text-sm font-bold inline-flex items-center justify-center gap-2 disabled:opacity-60"
                            style="background: linear-gradient(140deg, {{ $csColor }}, {{ $csColor }}b0);">
                        <i class="bi" :class="busy === 'pair' ? 'bi-arrow-repeat animate-spin' : 'bi-broadcast-pin'"></i>
                        {{ __('personal.event_screens_confirm') }}
                    </button>
                </div>
            </div>
        </div>
    </template>
</div>

@once
    {{-- Deliberately INLINE, not @push('scripts') — pushed scripts land in
         #shell-scripts, OUTSIDE <main id="shell-content">, so after an in-shell
         navigation the definition never arrives and x-data calls an undefined
         courtScreens(). Inline, it ships with the content and re-runs on every
         swap. Guarded, because Alpine keeps the reference it already has. --}}
    <script>
        window.courtScreens = window.courtScreens || function (config) {
            return {
                screens: config.screens || [],
                mats: config.mats || [],
                listUrl: config.listUrl,
                pairUrl: config.pairUrl,
                base: config.base,
                eventKey: config.eventKey,
                open: false,
                busy: null,
                code: '',
                court: '',
                // Which slot is being filled. Both are set by pressing a slot,
                // never chosen inside the sheet — the press IS the choice.
                surface: 'bout',

                /* The three screens every mat has. This list is the panel's
                   shape as much as its vocabulary: one block per mat, one row
                   per entry here, drawn whether or not it is filled. */
                surfaces: [
                    { key: 'bout',    icon: 'bi-trophy',  label: @js(__('personal.event_screens_surface_bout')),    hint: @js(__('personal.event_screens_surface_bout_hint')) },
                    { key: 'queue',   icon: 'bi-list-ol', label: @js(__('personal.event_screens_surface_queue')),   hint: @js(__('personal.event_screens_surface_queue_hint')) },
                    { key: 'control', icon: 'bi-sliders', label: @js(__('personal.event_screens_surface_control')), hint: @js(__('personal.event_screens_surface_control_hint')) },
                ].filter(sf => (config.allowed || []).includes(sf.key)),

                /** The screen filling one slot, or undefined. */
                screenFor(mat, surface) {
                    return this.screens.find(s => s.court === mat && s.surface === surface);
                },

                /** How many of a mat's three slots are filled — the mat's standing. */
                filledCount(mat) {
                    return this.surfaces.filter(sf => this.screenFor(mat, sf.key)).length;
                },

                /* Paired screens that fit no slot: an older one that follows the
                   mat, or one on a mat the draw has since dropped. They exist on
                   a wall whatever this panel thinks, so they are shown — a
                   screen that cannot be seen here cannot be unpaired either. */
                strays() {
                    return this.screens.filter(s =>
                        ! this.mats.includes(s.court) ||
                        ! this.surfaces.some(sf => sf.key === s.surface)
                    );
                },

                surfaceLabel() {
                    return (this.surfaces.find(sf => sf.key === this.surface) || {}).label || '';
                },

                /** Pair the screen for one mat's one job. */
                start(mat, surface) {
                    this.code = '';
                    this.court = mat;
                    this.surface = surface;
                    this.open = true;
                },

                close() {
                    this.open = false;
                    this.busy = null;
                },

                /** The pairing alphabet: no vowels, no 0/O/1/I. */
                clean(v) {
                    return String(v || '').toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 6);
                },

                /**
                 * Hand-back mode, so the shared scanner returns the value here
                 * instead of navigating to it — the scan is a step inside this
                 * flow, not a way out of the page.
                 */
                scan() {
                    window.dispatchEvent(new CustomEvent('qr-scan:open', {
                        detail: { emit: 'court-screens:scanned', title: @js(__('personal.event_screens_scan_title')) },
                    }));
                },

                /**
                 * The screen's QR encodes the claim URL, so the code is its last
                 * path segment — but a code typed or scanned bare is just as
                 * valid. Take whichever shape arrives and keep the six characters.
                 */
                onScan(detail) {
                    const raw = (detail && detail.value) || '';
                    let found = '';

                    try {
                        const parts = new URL(raw, window.location.origin).pathname.split('/').filter(Boolean);
                        found = this.clean(parts[parts.length - 1] || '');
                    } catch (_) {
                        found = this.clean(raw);
                    }

                    if (found.length !== 6) {
                        window.showToast && window.showToast('error', @js(__('personal.event_screens_code_needed')));
                        return;
                    }

                    this.code = found;
                    // The sheet may not be open — the scan can start from the
                    // panel itself once that shortcut exists.
                    this.open = true;
                },

                async submit() {
                    const code = this.clean(this.code);
                    const court = (this.court || '').trim();

                    if (code.length !== 6) { window.showToast && window.showToast('error', @js(__('personal.event_screens_code_needed'))); return; }
                    if (! court) { window.showToast && window.showToast('error', @js(__('personal.event_screens_mat_needed'))); return; }

                    this.busy = 'pair';

                    try {
                        const res = await fetch(this.pairUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            },
                            body: JSON.stringify({ code: code, court: court, surface: this.surface }),
                        });
                        const data = await res.json().catch(() => ({}));

                        if (! res.ok || ! data.success) {
                            window.showToast && window.showToast('error', data.message || @js(__('personal.event_verify_failed')));
                            return;
                        }

                        this.screens = data.screens || this.screens;
                        window.showToast && window.showToast('success', data.message);
                        this.close();
                    } catch (e) {
                        window.showToast && window.showToast('error', @js(__('personal.event_verify_failed')));
                    } finally {
                        this.busy = null;
                    }
                },

                async unpair(screen) {
                    const ok = await window.confirmAction({
                        title: @js(__('personal.event_screens_unpair_confirm')),
                        message: @js(__('personal.event_screens_unpair_body')),
                        type: 'danger',
                        confirmText: @js(__('personal.event_screens_unpair')),
                    });

                    if (! ok) return;

                    this.busy = screen.id;

                    try {
                        const res = await fetch(this.base + '/' + screen.id, {
                            method: 'DELETE',
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            },
                        });
                        const data = await res.json().catch(() => ({}));

                        if (! res.ok || ! data.success) {
                            window.showToast && window.showToast('error', data.message || @js(__('personal.event_verify_failed')));
                            return;
                        }

                        this.screens = data.screens || [];
                        window.showToast && window.showToast('success', data.message);
                    } catch (e) {
                        window.showToast && window.showToast('error', @js(__('personal.event_verify_failed')));
                    } finally {
                        this.busy = null;
                    }
                },

                /**
                 * Another organiser paired or unpaired a screen at the same
                 * venue. The push carries no screens — only that they changed —
                 * so re-fetch and be authorized on the way, rather than trusting
                 * a payload that arrived over a shared channel.
                 */
                onRealtime(detail) {
                    if (! detail || detail.action !== 'screens' || detail.event !== this.eventKey) return;
                    this.refresh();
                },

                /* Liveness is the one thing on this panel that changes with
                   nobody doing anything: a screen is unplugged, a television is
                   switched on, a tablet goes to sleep. Nothing pushes it —
                   `last_seen` is written by the screens themselves — so the
                   panel re-reads quietly while it is on the page.

                   Thirty seconds against a ten-minute liveness window: slow
                   enough to be nothing, fast enough that an organiser walking
                   the hall sees a dot change before they reach the screen.
                   Stops when the page is hidden, because a laptop in a bag is
                   not watching anything. */
                beat: null,

                watch() {
                    var self = this;
                    if (this.beat) return;
                    this.beat = setInterval(function () {
                        if (document.visibilityState === 'visible') self.refresh();
                    }, 30000);
                },

                async refresh() {
                    try {
                        const res = await fetch(this.listUrl, {
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        });
                        const data = await res.json().catch(() => ({}));
                        if (res.ok && data.success) this.screens = data.screens || [];
                    } catch (_) { /* a console that cannot refresh keeps what it has */ }
                },
            };
        };
    </script>
@endonce
