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
        eventKey: @js($event),
     })"
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

    {{-- Empty state: says what to do, not merely that there is nothing --}}
    <div x-show="! screens.length" x-cloak class="px-4 pb-4">
        <div class="rounded-2xl border border-dashed border-gray-200 bg-muted/30 px-4 py-6 text-center">
            <i class="bi bi-qr-code-scan text-2xl text-muted-foreground/50"></i>
            <p class="text-xs font-bold text-foreground mt-2">{{ __('personal.event_screens_empty') }}</p>
            <p class="text-[11px] text-muted-foreground mt-0.5">{{ __('personal.event_screens_empty_sub') }}</p>
        </div>
    </div>

    {{-- The screens. The mat plate borrows the hall board's own look — dark,
         condensed, all caps — so a row on the phone reads as the thing bolted
         to the wall rather than as another list item. --}}
    <div x-show="screens.length" x-cloak class="px-4 pb-2 space-y-2">
        <template x-for="s in screens" :key="s.id">
            <div class="flex items-center gap-3 rounded-2xl border border-gray-100 bg-white p-2.5">
                <span class="flex-shrink-0 px-3 py-2 rounded-xl bg-[#101016] text-white text-xs font-black uppercase tracking-wider"
                      x-text="s.court"></span>

                <span class="min-w-0 flex-1">
                    <span class="flex items-center gap-1.5">
                        {{-- A live screen pulses; a silent one is a flat amber
                             dot. The distinction is the whole point of the row:
                             a board nobody has looked at since setup is exactly
                             what an organiser needs to catch before the hall
                             fills. --}}
                        <span class="relative flex w-2 h-2 flex-shrink-0">
                            <span x-show="s.live" class="absolute inline-flex w-full h-full rounded-full bg-green-500 opacity-60 animate-ping"></span>
                            <span class="relative inline-flex w-2 h-2 rounded-full"
                                  :class="s.live ? 'bg-green-500' : 'bg-amber-500'"></span>
                        </span>
                        <span class="text-[11px] font-bold"
                              :class="s.live ? 'text-green-600' : 'text-amber-600'"
                              x-text="s.live ? @js(__('personal.event_screens_live')) : @js(__('personal.event_screens_offline'))"></span>
                    </span>
                    <span class="block text-xs font-semibold text-foreground truncate mt-0.5"
                          x-text="s.label || @js(__('personal.event_screens_unnamed'))"></span>
                    <span class="block text-[11px] text-muted-foreground truncate"
                          x-text="s.last_seen || @js(__('personal.event_screens_never_seen'))"></span>
                </span>

                <button type="button" @click="unpair(s)" :disabled="busy === s.id"
                        class="m-press flex-shrink-0 w-9 h-9 rounded-xl grid place-items-center text-muted-foreground hover:bg-red-50 hover:text-red-600 transition-colors disabled:opacity-40"
                        :aria-label="@js(__('personal.event_screens_unpair'))">
                    <i class="bi" :class="busy === s.id ? 'bi-arrow-repeat animate-spin' : 'bi-x-circle'"></i>
                </button>
            </div>
        </template>
    </div>

    <div class="px-4 pb-4 pt-2">
        <button type="button" @click="start()"
                class="m-press w-full h-11 rounded-xl text-white text-sm font-bold inline-flex items-center justify-center gap-2 shadow-sm"
                style="background: linear-gradient(140deg, {{ $csColor }}, {{ $csColor }}b0);">
            <i class="bi bi-qr-code-scan"></i>{{ __('personal.event_screens_pair') }}
        </button>
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
                        <h3 class="font-bold text-foreground">{{ __('personal.event_screens_pair') }}</h3>
                        <button type="button" @click="close()" aria-label="{{ __('shared.close') }}"
                                class="w-8 h-8 rounded-full grid place-items-center text-muted-foreground hover:bg-muted flex-shrink-0"><i class="bi bi-x-lg"></i></button>
                    </div>
                </div>

                {{-- Body scrolls; the action below stays reachable on a phone --}}
                <div class="flex-1 overflow-y-auto min-h-0 px-5 py-4 space-y-5">

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

                    {{-- Step 2 — the mat. Selection cards, not a dropdown: a
                         short known set, and an absolutely-positioned panel
                         inside this scrolling body would be clipped by it. --}}
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground/80 mb-2">{{ __('personal.event_screens_step_mat') }}</p>

                        <template x-if="! mats.length">
                            <p class="text-[11px] text-muted-foreground mb-2">{{ __('personal.event_screens_no_mats') }}</p>
                        </template>

                        <div class="space-y-2">
                            <template x-for="m in mats" :key="m">
                                <button type="button" @click="court = m; custom = false"
                                        class="m-press w-full flex items-center gap-3 rounded-xl border p-3 text-start transition-colors"
                                        :class="(! custom && court === m) ? 'border-primary bg-primary/5' : 'border-gray-200 bg-white hover:bg-muted/60'">
                                    <span class="w-9 h-9 rounded-xl grid place-items-center flex-shrink-0 bg-[#101016] text-white text-[10px] font-black uppercase"
                                          x-text="matShort(m)"></span>
                                    <span class="flex-1 min-w-0 text-sm font-semibold text-foreground truncate" x-text="m"></span>
                                    <span class="w-5 h-5 rounded-full border-2 grid place-items-center flex-shrink-0"
                                          :class="(! custom && court === m) ? 'border-primary' : 'border-gray-300'">
                                        <span x-show="! custom && court === m" class="w-2.5 h-2.5 rounded-full bg-primary"></span>
                                    </span>
                                </button>
                            </template>

                            {{-- The escape hatch: a mat the draw does not know
                                 about yet. Offered, never assumed. --}}
                            <button type="button" @click="custom = true; court = ''; $nextTick(() => $refs.customMat && $refs.customMat.focus())"
                                    class="m-press w-full flex items-center gap-3 rounded-xl border p-3 text-start transition-colors"
                                    :class="custom ? 'border-primary bg-primary/5' : 'border-gray-200 bg-white hover:bg-muted/60'">
                                <span class="w-9 h-9 rounded-xl grid place-items-center flex-shrink-0 bg-muted text-muted-foreground">
                                    <i class="bi bi-pencil"></i>
                                </span>
                                <span class="flex-1 min-w-0 text-sm font-semibold text-foreground">{{ __('personal.event_screens_mat_other') }}</span>
                                <span class="w-5 h-5 rounded-full border-2 grid place-items-center flex-shrink-0"
                                      :class="custom ? 'border-primary' : 'border-gray-300'">
                                    <span x-show="custom" class="w-2.5 h-2.5 rounded-full bg-primary"></span>
                                </span>
                            </button>

                            <input x-show="custom" x-cloak x-ref="customMat" type="text" x-model="court" maxlength="40"
                                   placeholder="{{ __('personal.event_screens_mat_ph') }}"
                                   class="w-full px-3 py-2.5 rounded-xl border border-gray-200 focus:ring-2 focus:ring-primary focus:border-transparent text-sm">
                        </div>
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
                custom: false,

                start() {
                    this.code = '';
                    // One mat means there is nothing to choose — preselect it and
                    // let the organiser scan and be done.
                    this.court = this.mats.length === 1 ? this.mats[0] : '';
                    this.custom = false;
                    this.open = true;
                },

                close() {
                    this.open = false;
                    this.busy = null;
                },

                /** "Mat 1" → "1" for the plate; a named mat keeps its first word. */
                matShort(m) {
                    const digits = String(m || '').match(/\d+/);

                    return digits ? digits[0] : String(m || '').slice(0, 3);
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
                            body: JSON.stringify({ code: code, court: court }),
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
