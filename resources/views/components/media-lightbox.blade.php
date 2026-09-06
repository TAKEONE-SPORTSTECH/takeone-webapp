{{-- Full-screen media viewer — a black-tinted overlay holding ONE file, with zoom and pan.
     Where the profile-picture viewer is a gallery you swipe, this one is for a single
     document (an ID card photographed at an angle is unreadable until you can zoom in).

     Include it ONCE per page, then open it from anywhere on that page:

        window.dispatchEvent(new CustomEvent('open-media-lightbox', {
            detail: { src: '/storage/…', label: 'Passport', kind: 'image' }   // kind optional
        }));

     • `src`   — required; an http(s) URL or a root-relative path. Anything else is refused.
     • `label` — shown in the caption bar and used as the download name.
     • `kind`  — 'image' | 'pdf'; inferred from the extension when absent.

     Images get pinch / wheel / double-tap zoom and drag-to-pan; a PDF is handed to the
     browser's own viewer inside the same dark surface. Teleported to <body> so a
     transformed ancestor (the mobile shell) can never clip it. --}}
@props([
    'eventName' => 'open-media-lightbox',
])

@once
{{-- Inline, NOT @push('scripts'): the mobile shell swaps only #shell-content, so a
     pushed stack would be dropped on an AJAX navigation. --}}
<script>
{{-- Registered on window (not Alpine.data) so it survives the mobile shell's content
     swaps and the admin shell's script dedupe — both re-run inline scripts at most once. --}}
window.mediaLightbox = window.mediaLightbox || function () {
    return {
        open: false,
        src: '',
        label: '',
        kind: 'image',
        scale: 1,
        minScale: 1,
        maxScale: 6,
        tx: 0,
        ty: 0,
        dragging: false,
        sharing: false,
        _start: null,
        _pinch: null,

        openWith(detail) {
            const src = this.safeSrc(detail && detail.src);
            if (! src) return;
            this.src = src;
            this.label = (detail && detail.label) ? String(detail.label) : '';
            this.kind = (detail && detail.kind) ? String(detail.kind) : this.guessKind(src);
            this.reset();
            this.open = true;
        },

        {{-- Only our own pages open this, but a path is still checked: no javascript:,
             no data:, no other origin. --}}
        safeSrc(value) {
            if (typeof value !== 'string' || value === '') return '';
            try {
                const url = new URL(value, window.location.origin);
                if (url.protocol !== 'http:' && url.protocol !== 'https:') return '';
                return url.href;
            } catch (e) {
                return '';
            }
        },

        guessKind(src) {
            if (/\.pdf(\?|#|$)/i.test(src)) return 'pdf';
            {{-- A video that guessed "image" landed in an <img> and showed
                 nothing at all — a silent blank, not an error. HLS playlists
                 count: the gallery hands us a ladder when one exists. --}}
            if (/\.(mp4|m4v|mov|webm|ogv|m3u8)(\?|#|$)/i.test(src)) return 'video';
            if (/\/hls(\/|\?|#|$)/i.test(src)) return 'video';
            return 'image';
        },

        {{-- Hand the document to whatever the device can share with. Sharing a FILE is
             tried first so the recipient gets the picture, not a link they cannot open
             (these files sit behind the profile). Falls back to the link, then to the
             clipboard. A cancelled share sheet is not an error. --}}
        async share() {
            if (this.sharing || ! this.src) return;
            this.sharing = true;
            try {
                const name = this.fileName();

                if (navigator.canShare) {
                    try {
                        const res = await fetch(this.src, { credentials: 'same-origin' });
                        if (res.ok) {
                            const blob = await res.blob();
                            const file = new File([blob], name, { type: blob.type || 'application/octet-stream' });
                            if (navigator.canShare({ files: [file] })) {
                                await navigator.share({ files: [file], title: this.label || name });
                                return;
                            }
                        }
                    } catch (e) {
                        if (e && e.name === 'AbortError') return;   {{-- the sheet was dismissed --}}
                    }
                }

                if (navigator.share) {
                    await navigator.share({ title: this.label || name, url: this.src });
                    return;
                }

                await navigator.clipboard.writeText(this.src);
                if (typeof window.showToast === 'function') {
                    window.showToast('success', @js(__('shared.link_copied')));
                }
            } catch (e) {
                if (e && e.name === 'AbortError') return;
                if (typeof window.showToast === 'function') {
                    window.showToast('error', @js(__('shared.something_went_wrong')));
                }
            } finally {
                this.sharing = false;
            }
        },

        {{-- A name the receiving app can show: the document's own label, plus the real
             extension off the stored path. --}}
        fileName() {
            let ext = '';
            try {
                ext = (new URL(this.src).pathname.split('.').pop() || '').toLowerCase();
            } catch (e) {}
            if (! /^[a-z0-9]{2,5}$/.test(ext)) {
                ext = this.kind === 'pdf' ? 'pdf' : (this.kind === 'video' ? 'mp4' : 'jpg');
            }
            const base = (this.label || 'document').replace(/[^\p{L}\p{N}\-_ ]/gu, '').trim() || 'document';
            return `${base}.${ext}`;
        },

        {{-- Is this source an HLS ladder?

             NOT by file extension. This platform's ladders are served from
             `/media/{uuid}/hls/...` through an authorised controller, and the
             bare `/media/{uuid}/hls` form has no extension at all — an
             extension test silently classed it as a plain file, handed the
             manifest to <video src>, and played nothing. Match the route
             shape as well as the suffix. --}}
        isHls(src) {
            return /\.m3u8(\?|#|$)/i.test(src) || /\/hls(\/|\?|#|$)/i.test(src);
        },

        {{-- Attach the right machinery for this source. --}}
        mountVideo(el) {
            if (!el) return;

            if (!this.isHls(this.src)) {
                el.src = this.src;
                return;
            }

            {{-- Safari and newer Chrome play a manifest natively. --}}
            if (el.canPlayType('application/vnd.apple.mpegurl')) {
                el.src = this.src;
                return;
            }

            const attach = (Hls) => {
                if (!Hls || !Hls.isSupported()) return;
                try { this._hls?.destroy(); } catch (e) {}
                this._hls = new Hls();
                this._hls.loadSource(this.src);
                this._hls.attachMedia(el);
            };

            if (window.Hls) { attach(window.Hls); return; }

            {{-- Fetched only when a ladder is actually opened, and only once per
                 page: the promise is shared with <x-video-player>, so a page
                 carrying both does not pull 400 KB of library down twice. --}}
            if (!window.__tkHlsPromise) {
                window.__tkHlsPromise = new Promise((resolve) => {
                    const tag = document.createElement('script');
                    tag.src = @js(asset('vendor/hls/hls.min.js'));
                    tag.onload = () => resolve(window.Hls);
                    tag.onerror = () => resolve(null);
                    document.head.appendChild(tag);
                });
            }

            window.__tkHlsPromise.then(attach);
        },

        {{-- Stop the sound the moment the viewer closes. A <video> removed from
             the DOM by x-if keeps playing in some browsers until it is paused. --}}
        stopVideo() {
            try { this.$refs.video?.pause(); } catch (e) {}
            try { this._hls?.destroy(); } catch (e) {}
            this._hls = null;
        },

        close() {
            this.stopVideo();
            this.open = false;
            this.dragging = false;
            this._start = null;
            this._pinch = null;
        },

        reset() {
            this.scale = this.minScale;
            this.tx = 0;
            this.ty = 0;
        },

        {{-- Panning stops where the picture does: measured from the fitted image, not
             the stage, so a portrait document can't be dragged off into empty black. --}}
        clampPan() {
            const stage = this.$refs.stage;
            const img = this.$refs.img;
            if (! stage) return;
            const w = (img && img.clientWidth ? img.clientWidth : stage.clientWidth) * this.scale;
            const h = (img && img.clientHeight ? img.clientHeight : stage.clientHeight) * this.scale;
            const limitX = Math.max(0, (w - stage.clientWidth) / 2);
            const limitY = Math.max(0, (h - stage.clientHeight) / 2);
            this.tx = Math.min(limitX, Math.max(-limitX, this.tx));
            this.ty = Math.min(limitY, Math.max(-limitY, this.ty));
        },

        zoomTo(next, originX, originY) {
            const from = this.scale;
            const to = Math.min(this.maxScale, Math.max(this.minScale, next));
            if (to === from) return;

            {{-- Keep the point under the cursor/fingers still while the scale changes. --}}
            if (typeof originX === 'number' && typeof originY === 'number') {
                const stage = this.$refs.stage;
                const box = stage ? stage.getBoundingClientRect() : null;
                if (box) {
                    const dx = originX - (box.left + box.width / 2);
                    const dy = originY - (box.top + box.height / 2);
                    const ratio = to / from;
                    this.tx = (this.tx - dx) * ratio + dx;
                    this.ty = (this.ty - dy) * ratio + dy;
                }
            }

            this.scale = to;
            if (to === this.minScale) { this.tx = 0; this.ty = 0; } else { this.clampPan(); }
        },

        zoomBy(step) { this.zoomTo(this.scale + step); },

        toggleZoom(e) {
            if (this.kind !== 'image') return;
            this.scale > this.minScale + 0.01
                ? this.reset()
                : this.zoomTo(2.5, e.clientX, e.clientY);
        },

        onWheel(e) {
            if (this.kind !== 'image') return;
            this.zoomTo(this.scale * (e.deltaY < 0 ? 1.12 : 1 / 1.12), e.clientX, e.clientY);
        },

        onDown(e) {
            if (this.kind !== 'image' || e.pointerType === 'touch') return;
            if (this.scale <= this.minScale + 0.01) return;
            this.dragging = true;
            this._start = { x: e.clientX, y: e.clientY, tx: this.tx, ty: this.ty };
        },

        onMove(e) {
            if (! this.dragging || ! this._start) return;
            this.tx = this._start.tx + (e.clientX - this._start.x);
            this.ty = this._start.ty + (e.clientY - this._start.y);
            this.clampPan();
        },

        onUp() { this.dragging = false; this._start = null; },

        {{-- Touch is handled apart from pointer events: pinch needs both fingers, and
             mixing the two paths double-counts every move (same split as the bracket
             and family-tree runtimes). --}}
        onTouchStart(e) {
            if (this.kind !== 'image') return;
            if (e.touches.length === 2) {
                this._pinch = {
                    d: this.distance(e.touches),
                    scale: this.scale,
                    cx: (e.touches[0].clientX + e.touches[1].clientX) / 2,
                    cy: (e.touches[0].clientY + e.touches[1].clientY) / 2,
                };
                this._start = null;
            } else if (e.touches.length === 1 && this.scale > this.minScale + 0.01) {
                this.dragging = true;
                this._start = { x: e.touches[0].clientX, y: e.touches[0].clientY, tx: this.tx, ty: this.ty };
            }
        },

        onTouchMove(e) {
            if (this.kind !== 'image') return;
            if (this._pinch && e.touches.length === 2) {
                const d = this.distance(e.touches);
                if (this._pinch.d > 0) {
                    this.zoomTo(this._pinch.scale * (d / this._pinch.d), this._pinch.cx, this._pinch.cy);
                }
            } else if (this.dragging && this._start && e.touches.length === 1) {
                this.tx = this._start.tx + (e.touches[0].clientX - this._start.x);
                this.ty = this._start.ty + (e.touches[0].clientY - this._start.y);
                this.clampPan();
            }
        },

        onTouchEnd(e) {
            if (e.touches.length === 0) { this.dragging = false; this._start = null; this._pinch = null; }
            else if (e.touches.length === 1) { this._pinch = null; }
        },

        distance(touches) {
            const dx = touches[0].clientX - touches[1].clientX;
            const dy = touches[0].clientY - touches[1].clientY;
            return Math.hypot(dx, dy);
        },

        keys(e) {
            if (this.kind !== 'image') return;
            if (e.key === '+' || e.key === '=') { e.preventDefault(); this.zoomBy(0.5); }
            else if (e.key === '-') { e.preventDefault(); this.zoomBy(-0.5); }
            else if (e.key === '0') { e.preventDefault(); this.reset(); }
        },
    };
};
</script>
{{-- Plug-and-play opener: ANY element on the page carrying data-media-lightbox opens the
     viewer, including rows rebuilt through innerHTML after an AJAX write (No-Reload).
        <button data-media-lightbox data-src="/storage/…" data-label="Passport">
     Delegated off document and guarded, so a shell swap can never bind it twice. --}}
<script>
if (! window.__mediaLightboxDelegated) {
    window.__mediaLightboxDelegated = true;
    document.addEventListener('click', function (e) {
        const el = e.target.closest ? e.target.closest('[data-media-lightbox]') : null;
        if (! el) return;
        e.preventDefault();
        window.dispatchEvent(new CustomEvent('open-media-lightbox', {
            detail: { src: el.dataset.src, label: el.dataset.label || '', kind: el.dataset.kind || '' },
        }));
    });
}
</script>
@endonce

<div x-data="mediaLightbox()" x-cloak
     x-on:{{ $eventName }}.window="openWith($event.detail)"
     @keydown.escape.window="close()"
     @keydown.window="open && keys($event)">
    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 z-[90] flex flex-col select-none">
            {{-- Tint --}}
            <div x-show="open" x-transition.opacity.duration.200ms
                 class="absolute inset-0 bg-black/80 backdrop-blur-md" @click="close()"></div>

            {{-- Controls --}}
            <div class="relative flex-shrink-0 flex items-center justify-between gap-2 px-4 pt-4 z-10"
                 style="padding-top: calc(1rem + env(safe-area-inset-top));">
                <p class="text-white/90 text-sm font-semibold truncate" x-text="label"></p>

                <div class="flex items-center gap-2 flex-shrink-0">
                    <template x-if="kind === 'image'">
                        <div class="flex items-center gap-2">
                            <button type="button" @click="zoomBy(-0.5)" :disabled="scale <= minScale + 0.01"
                                    aria-label="{{ __('shared.zoom_out') }}"
                                    class="w-10 h-10 rounded-full bg-white/15 text-white grid place-items-center active:scale-90 transition-transform disabled:opacity-40">
                                <i class="bi bi-zoom-out"></i>
                            </button>
                            <span class="text-white/70 text-xs font-semibold tabular-nums w-10 text-center"
                                  x-text="Math.round(scale * 100) + '%'"></span>
                            <button type="button" @click="zoomBy(0.5)" :disabled="scale >= maxScale - 0.01"
                                    aria-label="{{ __('shared.zoom_in') }}"
                                    class="w-10 h-10 rounded-full bg-white/15 text-white grid place-items-center active:scale-90 transition-transform disabled:opacity-40">
                                <i class="bi bi-zoom-in"></i>
                            </button>
                        </div>
                    </template>

                    {{-- Share — the file itself where the device takes files (the phone's
                         own sheet: WhatsApp, Mail, Files…), the link where it doesn't,
                         and a copied link where there is no share sheet at all. --}}
                    <button type="button" @click="share()" :disabled="sharing"
                            aria-label="{{ __('shared.share') }}"
                            class="w-10 h-10 rounded-full bg-white/15 text-white grid place-items-center active:scale-90 transition-transform disabled:opacity-50">
                        <i class="bi" :class="sharing ? 'bi-arrow-repeat animate-spin' : 'bi-share'"></i>
                    </button>

                    <a :href="src" target="_blank" rel="noopener" @click.stop
                       aria-label="{{ __('shared.open_in_new_tab') }}"
                       class="w-10 h-10 rounded-full bg-white/15 text-white grid place-items-center active:scale-90 transition-transform">
                        <i class="bi bi-box-arrow-up-right"></i>
                    </a>

                    <button type="button" @click="close()" aria-label="{{ __('shared.close') }}"
                            class="w-10 h-10 rounded-full bg-white/15 text-white grid place-items-center active:scale-90 transition-transform">
                        <i class="bi bi-x-lg text-lg"></i>
                    </button>
                </div>
            </div>

            {{-- Stage --}}
            <div class="relative flex-1 overflow-hidden flex items-center justify-center p-3" x-ref="stage"
                 style="touch-action: none;"
                 @wheel.prevent="onWheel($event)"
                 @pointerdown="onDown($event)"
                 @pointermove="onMove($event)"
                 @pointerup="onUp($event)"
                 @pointercancel="onUp($event)"
                 @touchstart.passive="onTouchStart($event)"
                 @touchmove.prevent="onTouchMove($event)"
                 @touchend.passive="onTouchEnd($event)"
                 @dblclick.prevent="toggleZoom($event)">

                {{-- `object-contain` does the fitting, so scale 1 is ALWAYS the whole
                     document on screen — no measuring, nothing to get wrong on a slow
                     load or a rotate. Zoom and pan are the transform on top of that. --}}
                <template x-if="open && kind === 'image'">
                    <img x-ref="img" :src="src" :alt="label" draggable="false"
                         class="max-w-full max-h-full object-contain will-change-transform rounded-lg"
                         :class="dragging ? '' : 'transition-transform duration-150'"
                         :style="`transform: translate(${tx}px, ${ty}px) scale(${scale}); cursor: ${scale > minScale ? (dragging ? 'grabbing' : 'grab') : 'zoom-in'};`">
                </template>

                {{-- Video: the browser's own controls inside the same dark
                     stage. No zoom or pan — every gesture handler above stands
                     down for a non-image, and a pinch on a playing video is a
                     scrub gesture people do not expect here. An HLS ladder is
                     attached through hls.js where the browser needs it. --}}
                <template x-if="open && kind === 'video'">
                    {{-- No `src` binding: a manifest handed to <video src> plays
                         nothing in a browser without native HLS, and an empty
                         src resolves against the page URL. mountVideo() decides
                         between MSE, native HLS and a plain file. --}}
                    <video x-ref="video"
                           controls playsinline autoplay
                           class="max-w-full max-h-full rounded-lg bg-black shadow-2xl"
                           style="touch-action: auto;"
                           x-init="$nextTick(() => mountVideo($el))"></video>
                </template>

                <template x-if="open && kind === 'pdf'">
                    <iframe :src="src" :title="label"
                            class="absolute inset-3 rounded-2xl bg-white shadow-2xl"
                            style="touch-action: auto;"></iframe>
                </template>
            </div>

            {{-- Always a way out: a labelled Close, with fit-to-screen beside it while
                 the reader is zoomed in. The ✕ above and a tap on the tint do the same. --}}
            <div class="relative flex-shrink-0 px-6 pt-3 flex items-center justify-center gap-2 z-10"
                 style="padding-bottom: calc(1.25rem + env(safe-area-inset-bottom));">
                <template x-if="kind === 'image'">
                    <button type="button" @click="reset()" x-show="scale > minScale + 0.01"
                            class="text-white/85 text-xs font-semibold px-4 py-2.5 rounded-full bg-white/10 active:scale-95 transition-transform">
                        <i class="bi bi-arrows-angle-contract me-1"></i>{{ __('shared.reset_zoom') }}
                    </button>
                </template>
                <button type="button" @click="close()"
                        class="text-white text-sm font-bold px-6 py-2.5 rounded-full bg-white/20 border border-white/25 backdrop-blur active:scale-95 transition-transform">
                    <i class="bi bi-x-lg me-1"></i>{{ __('shared.close') }}
                </button>
            </div>
        </div>
    </template>
</div>
