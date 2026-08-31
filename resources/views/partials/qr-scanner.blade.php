{{-- ===== QR scanner overlay — camera viewfinder, scans QR codes via the
     native BarcodeDetector API; navigates to scanned URLs (e.g. club pages).
     Shared by the mobile shell header and the desktop account menu so
     "Scan QR" works identically from either. Opens on the window event
     `qr-scan:open`; include this partial once per page.

     Two modes:
       · default — the scanned value is a URL and the browser goes there.
       · hand-back — open it with `{ emit: 'some-event', title: '…' }` and the
         value is dispatched on that window event instead. A caller that already
         knows what a code MEANS (the event console pairing a hall screen) reads
         it itself rather than being navigated somewhere.
     ===== --}}
{{--
    ⚠️ ONCE PER PAGE, ALWAYS.

    This partial is included by BOTH layouts/app.blade.php and
    partials/mobile-header.blade.php, and personal-mobile extends app — so every
    authenticated mobile page used to render TWO scanners. Both answered
    `qr-scan:open`, both called getUserMedia() for the same rear camera, and on
    Android the second grab commonly steals or fails the track: the overlay on
    top showed a black, frozen viewfinder that never decoded anything. The
    organiser then typed the code by hand and blamed the scanner, which was the
    correct diagnosis of the wrong problem.

    @once is keyed by this block, so whichever include renders first wins and the
    other is a no-op. Do not remove it, and do not "fix" a missing scanner by
    adding a third include.
--}}
@once
<div x-data="qrScanner()" x-cloak @qr-scan:open.window="open($event.detail)" @keydown.escape.window="close()">
    <div x-show="active" class="fixed inset-0 z-[80] bg-black flex flex-col"
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
        <div class="flex items-center justify-between px-4 h-14 text-white flex-shrink-0">
            <span class="font-semibold" x-text="title || @js(__('header.scan_qr'))"></span>
            <button type="button" @click="close()" class="m-press w-10 h-10 -mr-2 rounded-full flex items-center justify-center hover:bg-white/10" aria-label="{{ __('shared.cancel') }}">
                <i class="bi bi-x-lg text-xl"></i>
            </button>
        </div>
        <div class="flex-1 relative overflow-hidden">
            <video x-ref="qrVideo" playsinline muted class="absolute inset-0 w-full h-full object-cover"></video>
            {{-- Focus frame with a darkened surround --}}
            <div class="absolute inset-0 grid place-items-center pointer-events-none">
                <div class="w-64 h-64 max-w-[70vw] max-h-[70vw] rounded-3xl border-2 border-white/90"
                     style="box-shadow: 0 0 0 100vmax rgba(0,0,0,.45);"></div>
            </div>
            <p x-show="! manualOnly && ! stalled" class="absolute bottom-10 inset-x-0 text-center text-white/90 text-sm px-8">{{ __('header.scan_hint') }}</p>

            {{-- Camera missing or refusing: say so where the picture would be,
                 rather than closing the overlay and leaving a toast behind. --}}
            <div x-show="manualOnly || stalled" class="absolute inset-0 grid place-items-center px-8 text-center">
                <div>
                    <i class="bi bi-camera-video-off text-4xl text-white/40"></i>
                    <p class="text-white/80 text-sm mt-3" x-text="manualOnly ? manualOnlyNote : @js(__('header.scan_no_camera'))"></p>
                </div>
            </div>
        </div>

        {{-- Typed code — for a caller that says it accepts one. A code that will
             not scan (glare, a dead camera, a screen across the hall) must still
             be one field away, and this is where the reader already is. Hands the
             value back exactly as a scan does, so the caller has one path. --}}
        <div x-show="manual" x-cloak
             class="flex-shrink-0 bg-black/85 backdrop-blur px-5 pt-4 border-t border-white/10"
             style="padding-bottom: calc(1rem + env(safe-area-inset-bottom));">
            <p class="text-white/70 text-[11px] font-semibold uppercase tracking-wider" x-text="manualLabel"></p>
            <div class="flex items-center gap-2 mt-2">
                <input type="text" x-model="manualCode" x-ref="manualInput"
                       @input="manualCode = manualCode.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, manualLength || 12)"
                       @keydown.enter="submitManual()"
                       :placeholder="manualPlaceholder"
                       inputmode="text" autocapitalize="characters" autocomplete="off" spellcheck="false"
                       class="flex-1 min-w-0 px-3 py-3 rounded-xl bg-white/10 border border-white/25 text-white text-center text-lg font-black tracking-[0.3em] placeholder:tracking-normal placeholder:text-white/40 placeholder:font-normal placeholder:text-sm focus:outline-none focus:ring-2 focus:ring-white/40">
                <button type="button" @click="submitManual()"
                        :disabled="manualLength ? manualCode.length !== manualLength : ! manualCode"
                        class="m-press flex-shrink-0 h-12 px-5 rounded-xl bg-white text-black text-sm font-bold disabled:opacity-40">
                    <i class="bi bi-check-lg"></i>
                </button>
            </div>
        </div>
    </div>
</div>
<script>
    /**
     * Is this hostname one of ours?
     *
     * takeone.bh and every subdomain of it (stage.takeone.bh, wa.takeone.bh),
     * plus whatever host this page is already being served from — which covers
     * local development and any future hostname without needing an edit here.
     *
     * Deliberately a suffix match on a dot boundary, never `includes()`:
     * `nottakeone.bh` and `takeone.bh.evil.com` must both fail.
     */
    window.takeoneIsOwnHost = function (hostname) {
        const h = String(hostname || '').toLowerCase();

        return h === window.location.hostname.toLowerCase()
            || h === 'takeone.bh'
            || h.endsWith('.takeone.bh');
    };

    window.qrScanner = function () {
        return {
            active: false,
            stream: null,
            detector: null,
            raf: null,
            emit: null,
            title: null,
            manual: false,
            manualOnly: false,
            manualOnlyNote: '',
            manualLabel: '',
            manualPlaceholder: '',
            manualLength: 0,
            manualCode: '',
            stalled: false,
            stallTimer: null,

            async open(detail) {
                // Reset every time: a previous hand-back caller must never keep
                // receiving scans from a later, unrelated "Scan QR" tap.
                this.emit = (detail && detail.emit) || null;
                this.title = (detail && detail.title) || null;
                this.manual = !! (detail && detail.manual);
                this.manualLabel = (detail && detail.manualLabel) || '';
                this.manualPlaceholder = (detail && detail.manualPlaceholder) || '';
                this.manualLength = (detail && detail.manualLength) || 0;
                this.manualCode = '';
                this.manualOnly = false;
                this.manualOnlyNote = '';
                this.stalled = false;

                if (!('BarcodeDetector' in window)) {
                    // With a typed code on offer there is still a way through, so
                    // the overlay opens anyway and says the camera is out.
                    if (! this.manual) {
                        window.showToast && window.showToast('info', @js(__('header.scan_unsupported')));
                        return;
                    }
                    this.active = true;
                    this.manualOnly = true;
                    this.manualOnlyNote = @js(__('header.scan_unsupported'));
                    await this.$nextTick();
                    this.$refs.manualInput && this.$refs.manualInput.focus();
                    return;
                }
                this.active = true;
                await this.$nextTick();
                try {
                    this.detector = new BarcodeDetector({ formats: ['qr_code'] });
                    this.stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
                    const v = this.$refs.qrVideo;
                    v.srcObject = this.stream;
                    await v.play();
                    this.watchFrames(v);
                    this.scan();
                } catch (e) {
                    if (this.manual) {
                        this.manualOnly = true;
                        this.manualOnlyNote = @js(__('header.scan_no_camera'));
                        this.$refs.manualInput && this.$refs.manualInput.focus();
                        return;
                    }
                    window.showToast && window.showToast('error', @js(__('header.scan_no_camera')));
                    this.close();
                }
            },

            /**
             * A camera can be granted and still send nothing.
             *
             * getUserMedia resolves, the track reads as live, and not one frame
             * arrives — an Android WebView whose host app was never given the
             * OS camera permission does exactly this. The viewfinder is then a
             * black rectangle that decodes forever and reports no error, and
             * the reader is left to guess whether the code, the light or the
             * phone is at fault. If nothing has been decoded into a picture by
             * now, say so over the black rather than leaving it silent.
             *
             * Only ever ADDS a message: scanning continues, so a slow camera
             * that wakes up late still works and clears the note itself.
             */
            watchFrames(v) {
                if (this.stallTimer) clearTimeout(this.stallTimer);
                this.stallTimer = setTimeout(() => {
                    this.stallTimer = null;
                    if (! this.active) return;
                    this.stalled = ! v.videoWidth;
                }, 3500);

                v.addEventListener('loadeddata', () => {
                    if (v.videoWidth) this.stalled = false;
                }, { once: true });
            },

            async scan() {
                if (!this.active || !this.detector) return;
                try {
                    const codes = await this.detector.detect(this.$refs.qrVideo);
                    // The await can resolve after close() — whoever got there
                    // first (a scan, or the typed code) has already been handed
                    // back, and pairing twice would post twice.
                    if (! this.active) return;
                    if (codes && codes.length && codes[0].rawValue) {
                        this.handle(codes[0].rawValue);
                        return;
                    }
                } catch (_) { /* transient detect error — keep scanning */ }
                this.raf = requestAnimationFrame(() => this.scan());
            },

            // Scanned a URL → navigate. Ours by path, anything else refused.
            handle(value) {
                // First one through wins: the scanner is already gone by the time
                // the caller hears about it, so nothing else can hand back again.
                if (! this.active) return;
                var emit = this.emit;
                this.close();

                // Hand-back mode: the caller decides what the code means. Never
                // navigate here — the scan happened inside another screen's flow.
                if (emit) {
                    window.dispatchEvent(new CustomEvent(emit, { detail: { value: value } }));
                    return;
                }

                try {
                    const u = new URL(value, window.location.origin);
                    if (u.protocol === 'http:' || u.protocol === 'https:') {
                        // A code printed on one TAKEONE host has to work when
                        // scanned on the other, and it is FOLLOWED to the host
                        // that printed it.
                        //
                        // This used to keep the path and stay on the current
                        // host. That reads as the safer choice and is not: a
                        // pairing code is six characters in ONE host's database,
                        // a club slug and an event uuid likewise, so re-pointing
                        // the path at the other server produces a confident 404
                        // — the scan appears to work and then finds nothing.
                        //
                        // The QR is the authority on which environment it
                        // belongs to. `takeoneIsOwnHost` still bounds this to
                        // our own hostnames, so a scan can no more leave the
                        // platform than it could before.
                        if (window.takeoneIsOwnHost(u.hostname)) {
                            window.location.href = u.href;
                            return;
                        }

                        // Anything else belongs to somebody else. A QR poster is
                        // world-writable — anyone can print one and stick it on a
                        // wall — so a scan must never be a redirect off the
                        // platform. Show the value instead of following it.
                        window.showToast && window.showToast('error', @js(__('header.scan_foreign_host')));
                        return;
                    }
                } catch (_) { /* not a URL */ }
                window.showToast && window.showToast('info', value);
            },

            /** A typed code takes the same path out as a scanned one. */
            submitManual() {
                const v = (this.manualCode || '').trim();
                if (! v) return;
                if (this.manualLength && v.length !== this.manualLength) return;
                this.handle(v);
            },

            close() {
                this.active = false;
                if (this.raf) { cancelAnimationFrame(this.raf); this.raf = null; }
                if (this.stallTimer) { clearTimeout(this.stallTimer); this.stallTimer = null; }
                this.stalled = false;
                if (this.stream) { this.stream.getTracks().forEach(t => t.stop()); this.stream = null; }
                this.detector = null;
                this.emit = null;
                this.title = null;
                this.manual = false;
                this.manualOnly = false;
                this.manualOnlyNote = '';
                this.manualCode = '';
            },
        };
    };
</script>
@endonce
