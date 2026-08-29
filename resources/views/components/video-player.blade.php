@props([
    /* A stable id — the panel beside it addresses the player by this. */
    'id' => 'video-player',
    /*
     * The angles this player can show, in panel order. Each entry:
     *   ['angle','label','hls','mp4','poster','duration','transcoding']
     * A single-source player is just an array of one.
     */
    'angles' => [],
    /* Straight sources, for the simple case where there are no angles. */
    'hls' => null,
    'mp4' => null,
    'poster' => null,
    'title' => null,
    /* Offer the original file as a download. Pass a URL, or leave null. */
    'downloadUrl' => null,
    'height' => null,
])

@php
    // One shape inside, whichever way the caller supplied it.
    $sources = $angles ?: array_filter([[
        'angle' => 'main',
        'label' => __('events.bout_video_angle_main'),
        'hls' => $hls,
        'mp4' => $mp4,
        'poster' => $poster,
        'duration' => 0,
        'transcoding' => false,
    ]], fn ($s) => filled($s['hls']) || filled($s['mp4']));

    $domId = preg_replace('/[^A-Za-z0-9_-]/', '', $id) ?: 'video-player';
@endphp

{{--
    The player.

    Standalone by contract: it carries its own markup, its own Alpine state, its
    own hls.js lifecycle and its own key bindings, and it needs nothing on the
    page except hls.js (self-hosted — never a CDN on the critical path of a hall
    with bad wifi).

    Two things it deliberately does NOT do, both learned from the platform this
    replaces: it never re-parents itself into another container (a transform
    ancestor and a fullscreen element make that a trap), and it never touches the
    DOM of whatever panel sits beside it. Everything a sibling needs is on the
    public API below.

    Public API — `document.getElementById('{{ $domId }}')._player`:
        .seek(seconds)                  jump, no playback change
        .playFrom(seconds)              seek and play
        .replay(seconds, pre, post)     broadcast replay: 1× then ½×, then resume
        .showCaption(text, x, y)        subtitle-style overlay at a 0..1 position
        .hideCaption()
        .playRange(from, to, opts)      play a window, optionally at half speed
        .stopSpecial()                  cancel any replay/range and return to 1×
    Events dispatched on the root element:
        player:ready · player:time · player:angle
--}}
<div id="{{ $domId }}"
     x-data="takeoneVideoPlayer(@js(array_values($sources)), @js($downloadUrl))"
     x-init="boot()"
     @keydown.window="onKey($event)"
     @pagehide.window="teardown()"
     class="tk-player relative bg-black overflow-hidden select-none group/player"
     :class="{ 'tk-player-idle': !chrome }"
     style="{{ $height ? 'height:'.$height.';' : '' }}"
     @mousemove="wake()" @touchstart.passive="wake()"
     {{ $attributes->merge(['class' => 'rounded-2xl']) }}>

    {{-- ── The picture ─────────────────────────────────────────────────── --}}
    <div class="relative w-full aspect-video bg-black" @click="tapSurface($event)" @dblclick="tapSeek($event)">
        <video x-ref="video"
               class="w-full h-full object-contain bg-black"
               playsinline
               preload="metadata"
               :poster="current.poster || ''"
               @loadedmetadata="onMeta()"
               @timeupdate="onTime()"
               @progress="onBuffer()"
               @play="playing = true; wake()"
               @pause="playing = false; chrome = true"
               @ended="playing = false; chrome = true"
               @volumechange="onVolume()"
               @waiting="stalled = true"
               @playing="stalled = false"></video>

        {{-- Caption overlay: a coach's note, where the coach put it. --}}
        <div x-show="caption" x-cloak x-ref="caption"
             class="absolute z-30 max-w-[78%] px-3.5 py-2 rounded-xl bg-black/72 backdrop-blur text-white text-sm font-semibold leading-snug shadow-lg pointer-events-none"
             :style="captionStyle()"
             x-text="caption"></div>

        {{-- Replay badge — says WHY the picture just jumped back. --}}
        <div x-show="badge" x-cloak
             class="absolute top-3 start-3 z-30 inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-black uppercase tracking-wide text-white shadow-lg"
             :class="badge === 'slow' ? 'bg-blue-600' : 'bg-red-600'">
            <i class="bi bi-arrow-counterclockwise"></i>
            <span x-text="badge === 'slow' ? '{{ __('events.bout_video_slowmo') }}' : '{{ __('events.bout_video_replay') }}'"></span>
        </div>

        {{-- Buffering. Only after a beat, so a fast seek never flashes it. --}}
        <div x-show="stalled && playing" x-cloak
             class="absolute inset-0 grid place-items-center pointer-events-none z-20">
            <span class="w-12 h-12 rounded-full border-[3px] border-white/25 border-t-white animate-spin"></span>
        </div>

        {{-- Big centre play, for a paused player. --}}
        <button type="button" x-show="!playing && ready" x-cloak @click.stop="toggle()"
                class="absolute inset-0 z-20 grid place-items-center"
                aria-label="{{ __('events.bout_video_play') }}">
            <span class="w-[68px] h-[68px] rounded-full bg-black/55 backdrop-blur border border-white/25 grid place-items-center text-white transition-transform active:scale-90 hover:scale-105">
                <i class="bi bi-play-fill text-4xl ms-1"></i>
            </span>
        </button>

        {{-- Double-tap hints. --}}
        <div x-show="flash === 'back'" x-cloak class="absolute inset-y-0 start-0 w-2/5 grid place-items-center pointer-events-none z-20">
            <span class="px-3 py-2 rounded-2xl bg-black/55 text-white text-sm font-bold"><i class="bi bi-rewind-fill me-1"></i>10s</span>
        </div>
        <div x-show="flash === 'fwd'" x-cloak class="absolute inset-y-0 end-0 w-2/5 grid place-items-center pointer-events-none z-20">
            <span class="px-3 py-2 rounded-2xl bg-black/55 text-white text-sm font-bold">10s<i class="bi bi-fast-forward-fill ms-1"></i></span>
        </div>

        {{-- ── Chrome ──────────────────────────────────────────────────── --}}
        <div x-show="chrome" x-cloak
             x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="opacity-0 translate-y-2"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-end="opacity-0 translate-y-2"
             @click.stop
             class="absolute inset-x-0 bottom-0 z-40 px-3 pb-2.5 pt-10 bg-gradient-to-t from-black/85 via-black/45 to-transparent">

            {{-- Scrubber --}}
            <div class="relative h-4 flex items-center cursor-pointer group/bar"
                 x-ref="bar"
                 @pointerdown.prevent="scrubStart($event)"
                 @pointermove="scrubMove($event)"
                 @pointerup="scrubEnd($event)"
                 @pointercancel="scrubEnd($event)"
                 @mousemove="hoverAt($event)" @mouseleave="hover = null">

                <div class="absolute inset-x-0 h-1 rounded-full bg-white/25 overflow-hidden transition-all group-hover/bar:h-1.5">
                    <div class="absolute inset-y-0 start-0 bg-white/35" :style="`width:${buffered}%`"></div>
                    <div class="absolute inset-y-0 start-0 bg-primary" :style="`width:${progress}%`"></div>
                </div>

                {{-- Moment ticks, painted by whoever owns the timeline. --}}
                <template x-for="m in marks" :key="m.t">
                    <span class="absolute w-[3px] h-2.5 rounded-full -translate-x-1/2 pointer-events-none"
                          :class="m.side === 'red' ? 'bg-red-400' : (m.side === 'blue' ? 'bg-sky-400' : 'bg-gradient-to-b from-red-400 to-sky-400')"
                          :style="`inset-inline-start:${m.pct}%`"></span>
                </template>

                <span class="absolute w-3 h-3 rounded-full bg-primary shadow -translate-x-1/2 opacity-0 group-hover/bar:opacity-100 transition-opacity"
                      :class="scrubbing && '!opacity-100'"
                      :style="`inset-inline-start:${progress}%`"></span>

                <div x-show="hover !== null" x-cloak
                     class="absolute -top-8 -translate-x-1/2 px-2 py-1 rounded-lg bg-black/85 text-white text-[11px] font-bold tabular-nums pointer-events-none"
                     :style="`inset-inline-start:${hover?.pct ?? 0}%`"
                     x-text="hover?.label"></div>
            </div>

            {{-- Buttons --}}
            <div class="flex items-center gap-1 mt-1 text-white">
                <button type="button" @click="toggle()" class="tk-pb" :aria-label="playing ? '{{ __('events.bout_video_pause') }}' : '{{ __('events.bout_video_play') }}'">
                    <i class="bi text-xl" :class="playing ? 'bi-pause-fill' : 'bi-play-fill'"></i>
                </button>
                <button type="button" @click="nudge(-10)" class="tk-pb hidden sm:grid" aria-label="-10s"><i class="bi bi-rewind-fill"></i></button>
                <button type="button" @click="nudge(10)" class="tk-pb hidden sm:grid" aria-label="+10s"><i class="bi bi-fast-forward-fill"></i></button>

                <div class="flex items-center gap-1 group/vol">
                    <button type="button" @click="toggleMute()" class="tk-pb" aria-label="{{ __('events.bout_video_mute') }}">
                        <i class="bi" :class="muted || volume === 0 ? 'bi-volume-mute-fill' : (volume < 0.5 ? 'bi-volume-down-fill' : 'bi-volume-up-fill')"></i>
                    </button>
                    <input type="range" min="0" max="1" step="0.05" x-model.number="volume" @input="applyVolume()"
                           class="tk-vol w-0 group-hover/vol:w-20 focus:w-20 transition-all duration-200"
                           aria-label="{{ __('events.bout_video_volume') }}">
                </div>

                <span class="text-[11px] font-bold tabular-nums ms-1 text-white/90">
                    <span x-text="clock(now)"></span><span class="text-white/50"> / </span><span x-text="clock(duration)"></span>
                </span>

                <div class="flex-1"></div>

                {{-- Angle switch — only when the bout was filmed more than once. --}}
                <div class="relative" x-data="{ open: false }" @click.outside="open = false" x-show="sources.length > 1" x-cloak>
                    <button type="button" @click="open = !open" class="tk-pb tk-pb-wide" :aria-expanded="open">
                        <i class="bi bi-camera-reels-fill"></i>
                        <span class="text-[11px] font-bold ms-1 hidden sm:inline" x-text="current.label"></span>
                    </button>
                    <div x-show="open" x-cloak x-transition.opacity.duration.120ms
                         class="absolute bottom-full end-0 mb-2 min-w-44 rounded-xl bg-black/90 backdrop-blur border border-white/15 overflow-hidden shadow-2xl">
                        <template x-for="(s, i) in sources" :key="s.angle">
                            <button type="button" @click="pickAngle(i); open = false"
                                    class="w-full flex items-center gap-2 px-3 py-2.5 text-start text-[13px] font-semibold hover:bg-white/10 transition-colors"
                                    :class="i === index ? 'text-primary' : 'text-white/90'">
                                <i class="bi" :class="i === index ? 'bi-check-lg' : 'bi-dot opacity-0'"></i>
                                <span x-text="s.label"></span>
                            </button>
                        </template>
                    </div>
                </div>

                {{-- Speed --}}
                <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                    <button type="button" @click="open = !open" class="tk-pb tk-pb-wide" aria-label="{{ __('events.bout_video_speed') }}">
                        <span class="text-[11px] font-black" x-text="rate === 1 ? '1×' : rate + '×'"></span>
                    </button>
                    <div x-show="open" x-cloak x-transition.opacity.duration.120ms
                         class="absolute bottom-full end-0 mb-2 min-w-28 rounded-xl bg-black/90 backdrop-blur border border-white/15 overflow-hidden shadow-2xl">
                        <template x-for="r in [0.25, 0.5, 0.75, 1, 1.25, 1.5, 2]" :key="r">
                            <button type="button" @click="setRate(r); open = false"
                                    class="w-full px-3 py-2 text-start text-[13px] font-semibold hover:bg-white/10 transition-colors"
                                    :class="r === rate ? 'text-primary' : 'text-white/90'"
                                    x-text="r === 1 ? '{{ __('events.bout_video_normal') }}' : r + '×'"></button>
                        </template>
                    </div>
                </div>

                @if ($downloadUrl)
                    <a :href="downloadUrl" class="tk-pb no-underline text-white" aria-label="{{ __('events.bout_video_download') }}" download>
                        <i class="bi bi-download"></i>
                    </a>
                @endif

                <button type="button" @click="pip()" x-show="canPip" x-cloak class="tk-pb hidden sm:grid" aria-label="{{ __('events.bout_video_pip') }}">
                    <i class="bi bi-pip"></i>
                </button>
                <button type="button" @click="fullscreen()" class="tk-pb" aria-label="{{ __('events.bout_video_fullscreen') }}">
                    <i class="bi" :class="isFull ? 'bi-fullscreen-exit' : 'bi-arrows-fullscreen'"></i>
                </button>
            </div>
        </div>

        {{-- Still encoding: say so, rather than let the ladder's absence read as a fault. --}}
        <div x-show="current.transcoding" x-cloak
             class="absolute top-3 end-3 z-30 inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-black/65 backdrop-blur text-white/90 text-[10px] font-bold uppercase tracking-wide">
            <span class="w-1.5 h-1.5 rounded-full bg-amber-400 animate-pulse"></span>
            {{ __('events.bout_video_transcoding') }}
        </div>
    </div>
</div>

@once
    @push('styles')
        <style>
            .tk-player.tk-player-idle { cursor: none; }
            .tk-pb {
                display: grid; place-items: center;
                width: 2.25rem; height: 2.25rem; border-radius: 9999px;
                color: #fff; transition: background-color .15s ease, transform .15s ease;
            }
            .tk-pb:hover { background: rgba(255,255,255,.16); }
            .tk-pb:active { transform: scale(.9); }
            .tk-pb-wide { width: auto; min-width: 2.25rem; padding-inline: .5rem; }
            .tk-vol { accent-color: hsl(250 65% 65%); height: 3px; }
            /* Fullscreen: the element itself goes full, so the chrome and any
               sibling panel inside it stay on screen. Nothing is re-parented. */
            .tk-player:fullscreen { width: 100vw; height: 100vh; border-radius: 0; }
            .tk-player:fullscreen > div:first-child { height: 100%; aspect-ratio: auto; }
            @media (prefers-reduced-motion: reduce) {
                .tk-pb, .tk-pb:active { transition: none; transform: none; }
            }
        </style>
    @endpush

    @push('scripts')
        <script>
        /*
         * One Alpine factory for every player on the platform.
         *
         * Registered on `window` rather than through Alpine.data() so it survives
         * the mobile shell swapping page content underneath it — the same reason
         * the date-picker component keeps its behaviour inline. (And note: never
         * write a Blade component tag in here, even in a comment. Blade parses
         * them inside <script> too, and the failure surfaces as an "expecting
         * endif" error at the end of the file.)
         */
        window.takeoneVideoPlayer = function (sources, downloadUrl) {
            return {
                sources: sources || [],
                index: 0,
                downloadUrl: downloadUrl,

                ready: false, playing: false, stalled: false,
                now: 0, duration: 0, progress: 0, buffered: 0,
                volume: 1, muted: true, rate: 1,
                chrome: true, isFull: false, canPip: false,
                hover: null, scrubbing: false, flash: null,
                caption: null, captionPos: null, badge: null,
                marks: [],

                _hls: null, _idle: null, _special: null, _flashTimer: null,

                get current() { return this.sources[this.index] || {}; },

                /* ── lifecycle ─────────────────────────────────────────── */

                boot() {
                    const v = this.$refs.video;
                    if (!v) return;

                    // Remembered across videos, like every player a person has
                    // ever used. Muted first so autoplay policies never block a
                    // deliberate press.
                    const savedVol = parseFloat(localStorage.getItem('tkVolume'));
                    if (!isNaN(savedVol)) this.volume = Math.min(1, Math.max(0, savedVol));
                    this.muted = localStorage.getItem('tkMuted') !== '0';
                    v.volume = this.volume;
                    v.muted = this.muted;

                    this.canPip = document.pictureInPictureEnabled === true;

                    document.addEventListener('fullscreenchange', this._onFs = () => {
                        this.isFull = document.fullscreenElement === this.$el;
                    });

                    this.load(0, 0, false);

                    // Expose the instance for the panel beside us.
                    this.$el._player = this;
                    this.$nextTick(() => this.$el.dispatchEvent(new CustomEvent('player:ready', { bubbles: true })));
                },

                teardown() {
                    this.stopSpecial();
                    if (this._hls) { try { this._hls.destroy(); } catch (e) {} this._hls = null; }
                    if (this._onFs) document.removeEventListener('fullscreenchange', this._onFs);
                    clearTimeout(this._idle);
                },

                /*
                 * Point the element at a source.
                 *
                 * hls.js when the browser needs it, the browser's own HLS when it
                 * has it (Safari), and the progressive file when there is no
                 * ladder yet. `at` preserves the moment across an angle switch —
                 * changing camera should not lose your place.
                 */
                load(index, at, autoplay) {
                    const v = this.$refs.video;
                    const s = this.sources[index];
                    if (!v || !s) return;

                    this.index = index;
                    if (this._hls) { try { this._hls.destroy(); } catch (e) {} this._hls = null; }

                    const start = () => {
                        if (at > 0) { try { v.currentTime = at; } catch (e) {} }
                        if (autoplay) v.play().catch(() => {});
                    };

                    const progressive = () => {
                        // Last resort. The original can be very large — a bout
                        // filmed at length is a gigabyte — so this is a fallback,
                        // never the normal path.
                        v.src = s.mp4;
                        v.addEventListener('loadedmetadata', start, { once: true });
                    };

                    if (s.hls && !v.canPlayType('application/vnd.apple.mpegurl')) {
                        /*
                         * Fetch hls.js, THEN attach.
                         *
                         * This used to be a `<script defer>` in the page, which
                         * always lost: deferred scripts run after the module that
                         * boots Alpine, so by the time this ran `window.Hls` was
                         * still undefined and every player quietly fell through to
                         * the progressive original — a gigabyte over PHP for a long
                         * bout, which reads to a viewer as "the video is broken".
                         * Loading it here means the ladder is used whenever it can be.
                         */
                        this.ensureHls().then((Hls) => {
                            if (!Hls || !Hls.isSupported()) { progressive(); return; }

                            this._hls = new Hls({ startLevel: -1, backBufferLength: 60 });
                            this._hls.loadSource(s.hls);
                            this._hls.attachMedia(v);
                            this._hls.on(Hls.Events.MANIFEST_PARSED, start);
                            this._hls.on(Hls.Events.ERROR, (_, data) => {
                                if (!data.fatal) return;
                                try { this._hls.destroy(); } catch (e) {}
                                this._hls = null;
                                progressive();
                            });
                        }).catch(progressive);
                    } else if (s.hls && v.canPlayType('application/vnd.apple.mpegurl')) {
                        v.src = s.hls;
                        v.addEventListener('loadedmetadata', start, { once: true });
                    } else if (s.mp4) {
                        progressive();
                    }
                },

                /*
                 * hls.js, fetched once per page however many players are on it.
                 * Self-hosted — never a CDN on the critical path of a hall with
                 * bad wifi.
                 */
                ensureHls() {
                    if (window.Hls) return Promise.resolve(window.Hls);
                    if (window.__tkHlsPromise) return window.__tkHlsPromise;

                    window.__tkHlsPromise = new Promise((resolve) => {
                        const tag = document.createElement('script');
                        tag.src = @js(asset('vendor/hls/hls.min.js'));
                        tag.onload = () => resolve(window.Hls);
                        tag.onerror = () => resolve(null);
                        document.head.appendChild(tag);
                    });

                    return window.__tkHlsPromise;
                },

                pickAngle(i) {
                    if (i === this.index) return;
                    this.load(i, this.$refs.video?.currentTime || 0, this.playing);
                    this.$el.dispatchEvent(new CustomEvent('player:angle', {
                        bubbles: true, detail: { angle: this.sources[i]?.angle },
                    }));
                },

                /* ── media events ──────────────────────────────────────── */

                onMeta() {
                    const v = this.$refs.video;
                    this.duration = isFinite(v.duration) ? v.duration : (this.current.duration || 0);
                    this.ready = true;
                    this.paintMarks();
                },

                onTime() {
                    const v = this.$refs.video;
                    this.now = v.currentTime;
                    this.progress = this.duration > 0 ? Math.min(100, (this.now / this.duration) * 100) : 0;
                    if (this._special) this._special(this.now);
                    this.$el.dispatchEvent(new CustomEvent('player:time', {
                        bubbles: true, detail: { t: this.now },
                    }));
                },

                onBuffer() {
                    const v = this.$refs.video;
                    if (!v.buffered.length || !this.duration) return;
                    this.buffered = Math.min(100, (v.buffered.end(v.buffered.length - 1) / this.duration) * 100);
                },

                onVolume() {
                    const v = this.$refs.video;
                    this.volume = v.volume;
                    this.muted = v.muted;
                },

                /* ── controls ──────────────────────────────────────────── */

                toggle() {
                    const v = this.$refs.video;
                    if (v.paused) {
                        // First deliberate press earns sound back.
                        if (this.muted && localStorage.getItem('tkMuted') === null) {
                            v.muted = false; this.muted = false;
                        }
                        v.play().catch(() => {});
                    } else { v.pause(); }
                },

                nudge(by) {
                    this.stopSpecial();
                    const v = this.$refs.video;
                    v.currentTime = Math.min(this.duration || 1e9, Math.max(0, v.currentTime + by));
                    this.flashHint(by < 0 ? 'back' : 'fwd');
                },

                flashHint(which) {
                    this.flash = which;
                    clearTimeout(this._flashTimer);
                    this._flashTimer = setTimeout(() => { this.flash = null; }, 420);
                },

                setRate(r) { this.rate = r; this.$refs.video.playbackRate = r; },

                applyVolume() {
                    const v = this.$refs.video;
                    v.volume = this.volume;
                    if (this.volume > 0 && v.muted) { v.muted = false; this.muted = false; }
                    localStorage.setItem('tkVolume', String(this.volume));
                    localStorage.setItem('tkMuted', v.muted ? '1' : '0');
                },

                toggleMute() {
                    const v = this.$refs.video;
                    v.muted = !v.muted;
                    this.muted = v.muted;
                    localStorage.setItem('tkMuted', v.muted ? '1' : '0');
                },

                pip() {
                    const v = this.$refs.video;
                    if (document.pictureInPictureElement) document.exitPictureInPicture().catch(() => {});
                    else v.requestPictureInPicture?.().catch(() => {});
                },

                fullscreen() {
                    if (document.fullscreenElement) { document.exitFullscreen?.(); return; }
                    // The WRAPPER goes full, never the <video>: anything painted
                    // over the picture — captions, the badge, a panel — is a
                    // child of this element and would vanish otherwise.
                    this.$el.requestFullscreen?.().then(() => {
                        screen.orientation?.lock?.('landscape').catch(() => {});
                    }).catch(() => {});
                },

                wake() {
                    this.chrome = true;
                    clearTimeout(this._idle);
                    if (!this.playing) return;
                    this._idle = setTimeout(() => { this.chrome = false; this.hover = null; }, 2800);
                },

                tapSurface(e) {
                    // A tap on the picture wakes the chrome on touch, and plays
                    // or pauses with a mouse — the convention each input expects.
                    if (e.pointerType === 'touch' || window.matchMedia('(hover: none)').matches) { this.wake(); return; }
                    this.toggle();
                },

                tapSeek(e) {
                    const r = this.$el.getBoundingClientRect();
                    const x = (e.clientX - r.left) / r.width;
                    if (x < 0.4) this.nudge(-10);
                    else if (x > 0.6) this.nudge(10);
                    else this.fullscreen();
                },

                onKey(e) {
                    const t = e.target;
                    if (t && (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.isContentEditable)) return;
                    // Only the player under the pointer, or the fullscreen one,
                    // answers the keyboard — two players on a page must not both.
                    if (document.fullscreenElement && document.fullscreenElement !== this.$el) return;
                    if (!document.fullscreenElement && !this.$el.matches(':hover')) return;

                    const map = {
                        ' ': () => this.toggle(), k: () => this.toggle(),
                        m: () => this.toggleMute(), f: () => this.fullscreen(),
                        j: () => this.nudge(-10), l: () => this.nudge(10),
                        ArrowLeft: () => this.nudge(-5), ArrowRight: () => this.nudge(5),
                        ArrowUp: () => { this.volume = Math.min(1, this.volume + 0.05); this.applyVolume(); },
                        ArrowDown: () => { this.volume = Math.max(0, this.volume - 0.05); this.applyVolume(); },
                        Escape: () => this.stopSpecial(),
                    };

                    if (map[e.key]) { e.preventDefault(); map[e.key](); this.wake(); return; }

                    if (/^[0-9]$/.test(e.key) && this.duration) {
                        e.preventDefault();
                        this.stopSpecial();
                        this.$refs.video.currentTime = this.duration * (parseInt(e.key, 10) / 10);
                        this.wake();
                    }
                },

                /* ── scrubbing ─────────────────────────────────────────── */

                _pct(e) {
                    const r = this.$refs.bar.getBoundingClientRect();
                    let p = (e.clientX - r.left) / r.width;
                    if (document.dir === 'rtl' || getComputedStyle(this.$el).direction === 'rtl') p = 1 - p;
                    return Math.min(1, Math.max(0, p));
                },

                scrubStart(e) {
                    this.scrubbing = true;
                    this.stopSpecial();
                    this.$refs.bar.setPointerCapture?.(e.pointerId);
                    this.scrubTo(e);
                },
                scrubMove(e) { if (this.scrubbing) this.scrubTo(e); },
                scrubEnd(e) {
                    if (!this.scrubbing) return;
                    this.scrubbing = false;
                    this.$refs.bar.releasePointerCapture?.(e.pointerId);
                },
                scrubTo(e) {
                    if (!this.duration) return;
                    const t = this._pct(e) * this.duration;
                    this.$refs.video.currentTime = t;
                    this.now = t;
                    this.progress = (t / this.duration) * 100;
                },
                hoverAt(e) {
                    if (!this.duration) { this.hover = null; return; }
                    const p = this._pct(e);
                    this.hover = { pct: p * 100, label: this.clock(p * this.duration) };
                },

                /* ── public API for a timeline panel ───────────────────── */

                seek(t) {
                    this.stopSpecial();
                    this.$refs.video.currentTime = Math.max(0, t);
                },

                playFrom(t) {
                    this.seek(t);
                    this.$refs.video.play().catch(() => {});
                    this.wake();
                },

                /*
                 * The broadcast replay.
                 *
                 * A scoring moment is not a seek — a point lands in about a fifth
                 * of a second and dropping the playhead on it shows the aftermath.
                 * So: run in from before it at full speed, then the same window
                 * again at half speed, then let playback carry on. That is what
                 * the reader came to see, and it is what a television director
                 * would have done.
                 */
                replay(t, pre, post) {
                    pre = pre ?? 1.5; post = post ?? 2.5;
                    const v = this.$refs.video;
                    const from = Math.max(0, t - pre);
                    const to = t + post;

                    this.stopSpecial();
                    this.hideCaption();
                    this.setRate(1);
                    v.currentTime = from;
                    this.badge = 'normal';

                    let phase = 'normal';
                    this._special = (nowT) => {
                        if (nowT < to) return;
                        if (phase === 'normal') {
                            phase = 'slow';
                            this.setRate(0.5);
                            this.badge = 'slow';
                            v.currentTime = from;
                        } else {
                            this.stopSpecial();
                        }
                    };

                    v.play().catch(() => {});
                    this.wake();
                },

                /* Play one window. `slow` runs it a second time at half speed. */
                playRange(from, to, opts) {
                    const v = this.$refs.video;
                    const slow = !!(opts && opts.slow);
                    this.stopSpecial();
                    this.setRate(1);
                    v.currentTime = Math.max(0, from);

                    let second = false;
                    this._special = (nowT) => {
                        if (nowT < to) return;
                        if (slow && !second) {
                            second = true;
                            this.setRate(0.5);
                            this.badge = 'slow';
                            v.currentTime = Math.max(0, from);
                        } else {
                            v.pause();
                            this.stopSpecial();
                        }
                    };

                    v.play().catch(() => {});
                    this.wake();
                },

                stopSpecial() {
                    this._special = null;
                    this.badge = null;
                    if (this.rate !== 1) this.setRate(1);
                },

                showCaption(text, x, y) {
                    this.caption = text || null;
                    this.captionPos = (x === null || x === undefined) ? null : { x, y };
                },
                hideCaption() { this.caption = null; this.captionPos = null; },

                captionStyle() {
                    if (!this.captionPos) {
                        return 'left:50%; bottom:12%; transform:translateX(-50%);';
                    }
                    // Stored as the caption's CENTRE, 0..1 of the picture, so the
                    // position holds at any size and in fullscreen.
                    return `left:${this.captionPos.x * 100}%; top:${this.captionPos.y * 100}%; transform:translate(-50%,-50%);`;
                },

                /* Ticks on the scrubber. [{t, side}] — percentages are ours. */
                setMarks(marks) {
                    this._rawMarks = marks || [];
                    this.paintMarks();
                },
                paintMarks() {
                    if (!this._rawMarks || !this.duration) { this.marks = []; return; }
                    this.marks = this._rawMarks
                        .filter((m) => m.t >= 0 && m.t <= this.duration)
                        .map((m) => ({ ...m, pct: (m.t / this.duration) * 100 }));
                },

                clock(s) {
                    if (!isFinite(s) || s < 0) s = 0;
                    const whole = Math.floor(s);
                    const h = Math.floor(whole / 3600);
                    const m = Math.floor((whole % 3600) / 60);
                    const sec = whole % 60;
                    const pad = (n) => String(n).padStart(2, '0');
                    return h > 0 ? `${h}:${pad(m)}:${pad(sec)}` : `${pad(m)}:${pad(sec)}`;
                },
            };
        };
        </script>
    @endpush
@endonce
