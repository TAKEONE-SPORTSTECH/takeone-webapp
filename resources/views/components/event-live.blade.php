{{--
    Live — the console panel that switches a mat on.

    Standalone: it owns its own state, its own requests and its own DOM updates,
    and needs nothing from the page but an event key and the list of mats. Drop it
    into either console and it works.

    ── What it is for ─────────────────────────────────────────────────────────
    An organiser holding a phone, standing beside mat 2, wanting the fight on it
    to be watchable by somebody who is not in the hall. One tap per mat: it
    reserves the stream and opens the viewfinder. Nothing else to configure.

    ── Who decides when a mat is on air ───────────────────────────────────────
    This panel does, once a phone is standing by on the mat. The camera page
    asks the server what it should be doing every few seconds, so Go live and
    Stop here reach the tripod without anybody walking to it — which is the
    point: whether this mat should be public is known at the scoring table, not
    at the lens. A mat with no camera answering falls back to what it always
    did, opening a viewfinder on the device in your hand.

    Every broadcast is recorded and kept with the bout, which is why the panel
    says so — a volunteer needs to know the fight is not lost when they stop.
--}}
@props([
    'event',                 // the event's uuid
    'mats' => [],            // the mats from the draw; may be empty
    'canManage' => false,
    'color' => '#0e6e63',
])

@php
    // Organiser-supplied colour reaches a style attribute, so it is whitelisted
    // here rather than trusted — the same rule as every other band on the event
    // screens.
    $tint = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $color) ? $color : '#0e6e63';
@endphp

<div x-data="eventLive(@js($event), @js(array_values((array) $mats)), @js((bool) $canManage))"
     x-init="load()"
     @keydown.escape.window="qr = null"
     class="m-card bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">

    {{-- Band --}}
    <div class="px-4 py-3 text-white relative overflow-hidden"
         style="background: linear-gradient(150deg, {{ $tint }}, {{ $tint }}b0);">
        <div class="absolute -right-8 -top-10 w-32 h-32 rounded-full bg-white/10"></div>
        <div class="relative flex items-center gap-3">
            <span class="w-10 h-10 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0">
                <i class="bi bi-broadcast text-lg"></i>
            </span>
            <div class="min-w-0 flex-1">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-white/70">Live</p>
                <h3 class="text-base font-black leading-tight">Broadcast a mat</h3>
            </div>
            <span x-show="liveCount" x-cloak
                  class="flex-shrink-0 inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-white/20 text-[11px] font-bold">
                <span class="w-1.5 h-1.5 m-live-dot text-white"></span>
                <span x-text="liveCount + ' on air'"></span>
            </span>
        </div>
    </div>

    <div class="p-3.5 space-y-2">

        {{-- On air now. Anybody who can see the event sees this. --}}
        <template x-for="s in onAir" :key="s.id">
            <div class="flex items-center gap-3 p-2.5 rounded-xl border border-red-100 bg-red-50/60">
                <span class="w-9 h-9 rounded-xl bg-red-600 text-white grid place-items-center flex-shrink-0">
                    <i class="bi bi-broadcast-pin"></i>
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-bold text-foreground truncate" x-text="s.label"></p>
                    <p class="text-[11px] text-muted-foreground">
                        <span x-text="s.viewers"></span> watching · <span x-text="clock(s.duration)"></span>
                    </p>
                </div>
                <a :href="s.watch_url" class="flex-shrink-0 px-3 py-1.5 rounded-xl bg-white border border-red-200 text-red-700 text-xs font-bold">Watch</a>
                <button type="button" x-show="s.broadcast_url" @click="qr = s"
                        class="m-press flex-shrink-0 w-8 h-8 rounded-xl bg-white border border-red-200 text-red-700 grid place-items-center"
                        aria-label="Camera QR">
                    <i class="bi bi-qr-code text-xs"></i>
                </button>
                {{-- Icon, not a label: Stop is the control that matters on this
                     row and it must not be crowded off a phone by a link to the
                     viewfinder, which is only wanted when somebody is walking to
                     the mat anyway. --}}
                <a x-show="s.broadcast_url" :href="s.broadcast_url" aria-label="Open the camera page"
                   class="flex-shrink-0 w-8 h-8 rounded-xl bg-white border border-red-200 text-red-700 grid place-items-center">
                    <i class="bi bi-camera-video text-xs"></i>
                </a>
                {{-- The switch belongs here, at the table, not only on the
                     tripod: the phone reads the order on its next beat and
                     comes off air within a few seconds. --}}
                <button type="button" x-show="s.broadcast_url" @click="cut(s)" :disabled="busy === s.id"
                        class="m-press flex-shrink-0 px-3 py-1.5 rounded-xl bg-red-600 text-white text-xs font-bold disabled:opacity-60">
                    <span x-show="busy !== s.id"><i class="bi bi-stop-fill"></i> Stop</span>
                    <span x-show="busy === s.id" x-cloak>…</span>
                </button>
            </div>
        </template>

        @if ($canManage)
            {{-- One row per mat. The button reserves a stream and opens the
                 viewfinder in the same tap. --}}
            <template x-for="mat in mats" :key="matKey(mat)">
                <div x-show="!isOnAir(mat)" class="flex items-center gap-3 p-2.5 rounded-xl border border-gray-100">
                    <span class="w-9 h-9 rounded-xl bg-muted text-muted-foreground grid place-items-center flex-shrink-0">
                        <i class="bi bi-camera-video"></i>
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-bold text-foreground">Mat <span x-text="matKey(mat)"></span></p>
                        {{-- What is actually on that mat, so an organiser can see
                             the panel means the right one. --}}
                        <p class="text-[11px] text-muted-foreground truncate"
                           x-text="mat.bout ? ((mat.round ? mat.round + ' · ' : '') + mat.bout) : 'Not broadcasting'"></p>
                        {{-- Whether there is a phone on this mat waiting to be
                             told. Without it, Go live is a button that might do
                             nothing and never says which. --}}
                        <p x-show="ready(mat)" x-cloak class="text-[11px] font-bold mt-0.5 flex items-center gap-1"
                           style="color: {{ $tint }};">
                            <i class="bi bi-camera-video-fill"></i> Camera standing by
                        </p>
                    </div>

                    {{-- Hand the mat to a phone: the QR is the whole handover,
                         and it works before anybody has opened a camera page. --}}
                    <button type="button" @click="hand(mat)" :disabled="busy === matKey(mat)"
                            class="m-press flex-shrink-0 w-8 h-8 rounded-xl bg-muted text-foreground grid place-items-center disabled:opacity-60"
                            aria-label="Hand this mat to a phone">
                        <i class="bi bi-qr-code text-xs"></i>
                    </button>

                    {{-- Two different acts, so two different buttons. With a
                         phone standing by this puts the mat on air from here and
                         nobody walks anywhere; without one, there is nothing to
                         command yet and the only useful thing is to open a
                         viewfinder on the device in your hand. --}}
                    <button type="button" @click="ready(mat) ? arm(mat) : start(mat)" :disabled="busy === matKey(mat)"
                            class="m-press flex-shrink-0 px-3 py-1.5 rounded-xl text-white text-xs font-bold disabled:opacity-60"
                            style="background: {{ $tint }};">
                        <span x-show="busy !== matKey(mat)" x-text="ready(mat) ? 'Go live' : 'Open camera'"></span>
                        <span x-show="busy === matKey(mat)" x-cloak>…</span>
                    </button>
                </div>
            </template>

            <template x-if="!mats.length && !onAir.length">
                <p class="text-[12px] text-muted-foreground text-center py-3 leading-snug">
                    No mats yet — they appear once the draw is made. A stream can still be started from a mat's own screen.
                </p>
            </template>

            <p class="text-[11px] text-muted-foreground leading-snug pt-1 flex items-start gap-1.5">
                <i class="bi bi-record-circle mt-0.5"></i>
                <span>Every broadcast is recorded and kept with the bout, on this platform's own storage.</span>
            </p>
        @else
            <template x-if="!onAir.length">
                <p class="text-[12px] text-muted-foreground text-center py-3">No mat is broadcasting right now.</p>
            </template>
        @endif
    </div>

    {{-- Handing the camera over.
         A volunteer standing at the mat should not be sent a link or asked to
         type one: the organiser shows them this and they scan it, exactly the way
         a hall screen is paired. The code is fetched from the server (rendered
         offline by our own QR encoder) because the URL is per stream. --}}
    <template x-teleport="body">
        <div x-show="qr" x-cloak class="fixed inset-0 z-[70]" @click="qr = null">
            <div x-show="qr" x-transition.opacity class="fixed inset-0 bg-gray-900/60"></div>
            <div class="fixed inset-0 grid place-items-center p-6">
                <div x-show="qr"
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                     class="bg-white rounded-3xl shadow-2xl p-5 text-center max-w-xs w-full" @click.stop>
                    <p class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground">Scan with the filming phone</p>
                    <p class="text-base font-black text-foreground mt-1" x-text="qr?.label"></p>

                    {{-- White ground always: a scanner looks for dark modules on
                         a light field. --}}
                    <div class="mt-3 bg-white rounded-2xl p-2 inline-block">
                        <img :src="qr ? ('/live/' + qr.id + '/qr') : ''" alt="" class="w-56 h-56 block">
                    </div>

                    <p class="text-[11px] text-muted-foreground mt-3 leading-snug">
                        They will need to sign in as somebody who runs this event.
                    </p>
                    <button type="button" @click="qr = null"
                            class="mt-4 w-full py-2.5 rounded-xl bg-muted text-foreground text-sm font-bold">Close</button>
                </div>
            </div>
        </div>
    </template>
</div>

@once
<script>
function eventLive(eventKey, mats, canManage) {
    return {
        eventKey, mats: mats || [], canManage,
        streams: [],
        busy: null,
        timer: null,
        qr: null,

        get onAir() { return this.streams.filter(s => s.live); },
        get liveCount() { return this.onAir.length; },

        /* Mats arrive from the endpoint as objects ({court, bout, round}), but the
           console may also have handed us a plain list on first paint. Accept
           both rather than making the caller care. */
        matKey(mat) { return String(mat && mat.court !== undefined ? mat.court : mat); },

        isOnAir(mat) {
            const court = this.matKey(mat);
            return this.streams.some(s => s.live && String(s.court) === court);
        },

        /* Is there a phone on this mat, right now, waiting to be told? The
           server answers this from the viewfinder's own beat — it is the
           difference between Go live doing something and Go live doing nothing
           while everybody watches an empty player. */
        ready(mat) { return !!(mat && mat.stream && mat.stream.camera_present); },

        clock(seconds) {
            const s = Math.max(0, seconds | 0);
            return Math.floor(s / 60) + ':' + ('0' + (s % 60)).slice(-2);
        },

        csrf() { return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''; },

        async load() {
            try {
                const res = await fetch(`/events/${this.eventKey}/live`, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    cache: 'no-store',
                });
                if (!res.ok) return;
                const d = await res.json();
                this.streams = d.streams || [];
                // The server knows the mats and what is on them; trust it over
                // whatever the page was rendered with.
                if (Array.isArray(d.mats) && d.mats.length) this.mats = d.mats;
            } catch (e) { /* offline — the next tick will do */ }

            // A mat going live is something that happens while somebody is
            // looking at this panel, so it refreshes itself. Stored on window so
            // a mobile-shell navigation does not stack a second timer.
            if (!this.timer) {
                clearInterval(window.__eventLiveTimer);
                window.__eventLiveTimer = this.timer = setInterval(() => this.load(), 10000);
            }

            // A mat put on air by ANOTHER organiser must appear here without
            // waiting out the poll. A refresh signal, not a payload: what this
            // console may see depends on who is looking. Deduped on window
            // because a mobile-shell navigation re-runs this script.
            if (!window.__eventLiveRealtime) {
                window.__eventLiveRealtime = (e) => {
                    if (e.detail && e.detail.action === 'live') this.load();
                };
                window.addEventListener('realtime:events', window.__eventLiveRealtime);
            }
        },

        /**
         * Put a mat on air from here.
         *
         * Only offered when a camera is standing by, so this is never a button
         * that quietly does nothing: the order is written, the phone reads it
         * within a beat or two, and the row moves itself to on-air when the
         * picture actually arrives.
         */
        async arm(mat) {
            if (this.busy) return;

            const court = this.matKey(mat);
            const stream = mat && mat.stream;
            if (!stream) return;

            this.busy = court;

            try {
                const res = await fetch(`/live/${stream.id}/arm`, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrf(),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                const d = await res.json();

                if (!res.ok || !d.success) {
                    window.showToast && window.showToast('error', 'Could not put that mat on air.');
                    return;
                }

                window.showToast && window.showToast(
                    d.camera_present ? 'success' : 'warning',
                    d.camera_present
                        ? 'Going live — the camera is starting.'
                        : 'Asked for, but no camera answered. It will start as soon as one does.'
                );

                // The picture takes a moment to arrive; look again shortly
                // rather than waiting out the ten-second beat.
                setTimeout(() => this.load(), 2500);
            } catch (e) {
                window.showToast && window.showToast('error', 'Could not put that mat on air.');
            } finally {
                this.busy = null;
            }
        },

        /** Take a mat off air. The phone tears down on its next beat. */
        async cut(stream) {
            if (this.busy) return;

            const ok = window.confirmAction
                ? await window.confirmAction({
                    title: 'Stop this broadcast?',
                    message: 'The mat comes off air and the camera stops. What has been recorded so far is kept with the bout.',
                    type: 'danger',
                    confirmText: 'Stop',
                })
                : true;

            if (!ok) return;

            this.busy = stream.id;

            try {
                const res = await fetch(`/live/${stream.id}/stop`, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrf(),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (!res.ok) {
                    window.showToast && window.showToast('error', 'Could not stop that broadcast.');
                    return;
                }

                window.showToast && window.showToast('success', 'Off air. The recording is being prepared.');
                await this.load();
            } catch (e) {
                window.showToast && window.showToast('error', 'Could not stop that broadcast.');
            } finally {
                this.busy = null;
            }
        },

        /**
         * Show the code that hands this mat to a phone.
         *
         * A mat with no stream yet gets one first — an organiser holding out a
         * QR should not have to visit a camera page on their own device to
         * bring one into existence.
         */
        async hand(mat) {
            if (this.busy) return;

            const court = this.matKey(mat);

            if (mat.stream) {
                this.qr = { id: mat.stream.id, label: 'Mat ' + court };
                return;
            }

            this.busy = court;

            try {
                const stream = await this.reserve(mat);
                if (stream) this.qr = { id: stream.id, label: 'Mat ' + court };
            } finally {
                this.busy = null;
            }
        },

        /** Create (or find) this mat's stream. Returns its payload, or null. */
        async reserve(mat) {
            const court = this.matKey(mat);
            const boutId = (mat && mat.match_id) ? mat.match_id : null;

            try {
                const res = await fetch(`/events/${this.eventKey}/live`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrf(),
                    },
                    // The bout currently on that mat travels with it, so the
                    // recording lands on the right fight when there is one.
                    body: JSON.stringify({ court: court, match_id: boutId }),
                });
                const d = await res.json();

                if (!res.ok || !d.success) {
                    window.showToast && window.showToast('error', d.message || 'Could not set up a camera for that mat.');
                    return null;
                }

                await this.load();

                return Object.assign({}, d.stream, { broadcast_url: d.broadcast_url });
            } catch (e) {
                window.showToast && window.showToast('error', 'Could not set up a camera for that mat.');
                return null;
            }
        },

        /** Reserve a stream for this mat and open the viewfinder. */
        async start(mat) {
            if (this.busy) return;

            this.busy = this.matKey(mat);

            try {
                const stream = await this.reserve(mat);

                // Straight to the camera: the person pressing this is standing at
                // the mat holding the phone that will film it. It opens standing
                // by rather than live — the console decides when it goes on air.
                if (stream && stream.broadcast_url) window.location.href = stream.broadcast_url;
            } finally {
                this.busy = null;
            }
        },
    };
}
</script>
@endonce
