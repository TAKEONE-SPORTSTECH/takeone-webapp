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
            <p class="absolute bottom-10 inset-x-0 text-center text-white/90 text-sm px-8">{{ __('header.scan_hint') }}</p>
        </div>
    </div>
</div>
<script>
    window.qrScanner = function () {
        return {
            active: false,
            stream: null,
            detector: null,
            raf: null,
            emit: null,
            title: null,

            async open(detail) {
                // Reset every time: a previous hand-back caller must never keep
                // receiving scans from a later, unrelated "Scan QR" tap.
                this.emit = (detail && detail.emit) || null;
                this.title = (detail && detail.title) || null;

                if (!('BarcodeDetector' in window)) {
                    window.showToast && window.showToast('info', @js(__('header.scan_unsupported')));
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
                    this.scan();
                } catch (e) {
                    window.showToast && window.showToast('error', @js(__('header.scan_no_camera')));
                    this.close();
                }
            },

            async scan() {
                if (!this.active || !this.detector) return;
                try {
                    const codes = await this.detector.detect(this.$refs.qrVideo);
                    if (codes && codes.length && codes[0].rawValue) {
                        this.handle(codes[0].rawValue);
                        return;
                    }
                } catch (_) { /* transient detect error — keep scanning */ }
                this.raf = requestAnimationFrame(() => this.scan());
            },

            // Scanned a URL → navigate (same pattern as notifications: http(s) only).
            handle(value) {
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
                        window.location.href = u.href;
                        return;
                    }
                } catch (_) { /* not a URL */ }
                window.showToast && window.showToast('info', value);
            },

            close() {
                this.active = false;
                if (this.raf) { cancelAnimationFrame(this.raf); this.raf = null; }
                if (this.stream) { this.stream.getTracks().forEach(t => t.stop()); this.stream = null; }
                this.detector = null;
                this.emit = null;
                this.title = null;
            },
        };
    };
</script>
