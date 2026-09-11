@props([
    'id' => 'photoCapture',   // unique per instance; also the cropper's id
    'color' => '#7c3aed',     // the subject's colour — the tiles and the camera wear it
    'title' => null,
    'hint' => null,
    /*
     * Where the two surfaces sit in the stack. The defaults are the self-serve
     * entry page's, which opens them from an ordinary page — so that page is
     * unchanged.
     *
     * A caller that opens this from INSIDE its own sheet must raise them above
     * it, or the photo sheet opens underneath the sheet that asked for it and
     * looks like nothing happened (the event console's Add-a-person sheet is
     * z-[70], reported 2026-09-11). Keep both below 80: the cropper's own
     * editor is z-[80] and has to stay on top of everything here.
     *
     * Applied as an inline style, never an arbitrary `z-[…]` class — the
     * Tailwind bundle is prebuilt, and a class nobody used before has no CSS.
     */
    'z' => 60,
    'cameraZ' => null,
])

{{--
    Taking somebody's photograph — the whole flow, as one component.

    THIS IS THE SELF-SERVE ENTRANT'S PHOTO EXPERIENCE, lifted out of
    `entry/public/my-entry` so a second screen can use it rather than grow a
    poorer copy (owner's instruction, 2026-09-11: "use the same cropper of the
    new participant registration that is self served").

    Three surfaces, in the order a photograph is actually taken:

      1. THE SHEET — two portrait tiles, because a phone has two ways in: the
         camera, and the pictures already on it. No third option ever appears;
         when there is no camera API or the permission was refused, the camera
         tile becomes a native file input with `capture="user"` and keeps its
         place (CLAUDE.md → Mobile Device Capabilities: detect, request
         properly, fall back honestly).
      2. THE CAMERA — a capture screen laid out like a camera app: the frame in
         the middle at the crop's true 3:4 with the face oval inside it, and
         every control in the bottom third where a thumb already is. The shot is
         taken in the guide's own 3:4, mirrored only where the preview was, and
         goes to the cropper rather than to the server.
      3. THE CROPPER — the one this project has (CLAUDE.md → One Cropper
         Everywhere), inline bottom-sheet mode, 600x800 because every face on
         this platform is portrait 3:4.

    IT SAVES NOTHING. The caller owns what a photograph means — one screen PUTs
    it onto an entry, another posts it to the competitor endpoint, a third holds
    it until a name is committed. So the contract is two events:

        open      window `photo-capture:open`   {id}         → the sheet opens
        re-frame  window `photo-capture:recrop` {id, src}    → re-crop what exists
        result    window `photo-capture:cropped` {id, base64} → the caller saves it

    Standalone: it carries its own markup, its own Alpine state, its own styles
    and its own camera lifecycle (the stream is stopped on close, on `pagehide`
    and when the tab is hidden — never leave the camera light on). Drop it in
    anywhere, once per `id`.
--}}

@php
    use App\Support\Palette;

    $c = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $color) ? $color : '#7c3aed';
    $title = $title ?: __('events.entry_field_photo');
    $hint = $hint ?: __('events.public_enrol_photo_sheet_hint');

    $z = max(1, min(79, (int) $z));
    $cameraZ = max(1, min(79, (int) ($cameraZ ?? $z + 10)));
@endphp

<div x-data="{
        photoSheet: false,
        camera: false,
        cameraFallback: false,
        cameraCanFlip: false,
        cameraFacing: 'user',
        cameraHint: '',
        _stream: null,

        /* Re-frame a picture already on file: fetch the bytes back and hand
           them to the cropper like any freshly picked file. */
        async recrop(src) {
            if (! src) return;
            try {
                const res = await fetch(src, { credentials: 'same-origin' });
                if (! res.ok) throw new Error();
                const blob = await res.blob();
                if (! blob || ! String(blob.type).startsWith('image/')) throw new Error();
                this.photoSheet = false;
                this.toCropper(new File([blob], 'photo', { type: blob.type }));
            } catch (e) {
                window.dispatchEvent(new CustomEvent('photo-capture:broken', { detail: { id: @js($id), src } }));
                window.showToast('error', @js(__('events.entry_photo_recrop_failed')));
            }
        },

        handOff(ev) {
            const file = ev.target.files && ev.target.files[0];
            if (! file) return;

            // Let the same door be used twice in a row: without this, picking
            // the identical file again fires no change event at all.
            ev.target.value = '';
            this.photoSheet = false;
            this.toCropper(file);
        },

        /* Hand a file to the ONE cropper. Its own input is what it reads from,
           so the file is moved across with a DataTransfer and a `change`. */
        toCropper(file) {
            const target = document.getElementById('input_' + @js($id));
            if (! target) return;

            const dt = new DataTransfer();
            dt.items.add(file);
            target.files = dt.files;
            target.dispatchEvent(new Event('change', { bubbles: true }));
        },

        /* Detect first, request properly, fall back honestly (CLAUDE.md ->
           Mobile Device Capabilities). */
        async openCamera() {
            this.cameraHint = @js(__('events.public_enrol_photo_guide'));

            if (! navigator.mediaDevices?.getUserMedia) {
                this.cameraFallback = true;
                window.showToast('error', @js(__('events.public_enrol_photo_no_camera')));
                return;
            }

            try {
                this._stream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: this.cameraFacing, width: { ideal: 1080 }, height: { ideal: 1440 } },
                    audio: false,
                });
            } catch (e) {
                this.cameraFallback = true;
                window.showToast('error', @js(__('events.public_enrol_photo_denied')));
                return;
            }

            this.photoSheet = false;
            this.camera = true;

            await this.$nextTick();
            const v = this.$refs.video;
            if (v) { v.srcObject = this._stream; try { await v.play(); } catch (e) {} }

            try {
                const cams = await navigator.mediaDevices.enumerateDevices();
                this.cameraCanFlip = cams.filter(d => d.kind === 'videoinput').length > 1;
            } catch (e) {
                this.cameraCanFlip = false;
            }
        },

        async flipCamera() {
            this.cameraFacing = this.cameraFacing === 'user' ? 'environment' : 'user';
            this.stopStream();
            this.camera = false;
            await this.openCamera();
        },

        /* Take the frame in the guide's own 3:4. Mirrored only where the
           preview was. */
        shoot() {
            const v = this.$refs.video;
            if (! v || ! v.videoWidth) { window.showToast('error', @js(__('events.public_enrol_photo_wait'))); return; }

            const srcW = v.videoWidth, srcH = v.videoHeight;
            const want = 3 / 4;
            let sw = srcW, sh = Math.round(srcW / want);
            if (sh > srcH) { sh = srcH; sw = Math.round(srcH * want); }
            const sx = Math.round((srcW - sw) / 2);
            const sy = Math.round((srcH - sh) / 2);

            const canvas = document.createElement('canvas');
            canvas.width = 900;
            canvas.height = 1200;
            const ctx = canvas.getContext('2d');

            if (this.cameraFacing === 'user') {
                ctx.translate(canvas.width, 0);
                ctx.scale(-1, 1);
            }

            ctx.drawImage(v, sx, sy, sw, sh, 0, 0, canvas.width, canvas.height);

            canvas.toBlob((blob) => {
                if (! blob) { window.showToast('error', @js(__('events.public_enrol_photo_failed'))); return; }
                const file = new File([blob], 'photo.jpg', { type: 'image/jpeg' });
                this.closeCamera();
                this.toCropper(file);
            }, 'image/jpeg', 0.92);
        },

        closeCamera() { this.stopStream(); this.camera = false; },

        /* Never leave the camera light on. */
        stopStream() {
            if (! this._stream) return;
            this._stream.getTracks().forEach(t => { try { t.stop(); } catch (e) {} });
            this._stream = null;
        },

        init() {
            const mine = (ev) => ! ev.detail || ! ev.detail.id || ev.detail.id === @js($id);

            window.addEventListener('photo-capture:open', (ev) => { if (mine(ev)) this.photoSheet = true; });
            window.addEventListener('photo-capture:recrop', (ev) => { if (mine(ev)) this.recrop(ev.detail && ev.detail.src); });

            /* The cropper announces its result rather than firing an input
               event, because it writes the hidden field with `.value =`. The
               caller decides what to do with the bytes. */
            document.addEventListener('cropperCropped', (ev) => {
                if (ev.detail && ev.detail.id === @js($id) && ev.detail.base64) {
                    window.dispatchEvent(new CustomEvent('photo-capture:cropped', {
                        detail: { id: @js($id), base64: ev.detail.base64 },
                    }));
                }
            });

            window.addEventListener('pagehide', () => this.stopStream());
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'hidden') this.stopStream();
            });
        },
     }">

    {{-- ================= The photo sheet =================
         Two ways in, because a phone has two: the camera and what is already on
         it. Both hand the file to the ONE cropper this project has, in its
         inline bottom-sheet mode (CLAUDE.md → One Cropper Everywhere). --}}
    <template x-teleport="body">
        <div x-show="photoSheet" x-cloak class="fixed inset-0" style="z-index: {{ $z }};">
            <div x-show="photoSheet" x-transition.opacity @click="photoSheet = false" class="absolute inset-0 bg-black/50"></div>

            <div x-show="photoSheet"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="translate-y-full"
                 x-transition:enter-end="translate-y-0"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="translate-y-0"
                 x-transition:leave-end="translate-y-full"
                 class="absolute inset-x-0 bottom-0 max-h-[92vh] flex flex-col rounded-t-3xl overflow-hidden bg-background">

                <div class="flex-shrink-0 px-5 pt-3 pb-4 rounded-t-3xl text-white relative overflow-hidden"
                     style="background: linear-gradient(150deg, {{ $c }}, {{ $c }}b0);">
                    <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                    <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3"></div>

                    <div class="relative flex items-start gap-3">
                        <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0">
                            <i class="bi bi-camera-fill text-xl"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-lg font-black leading-tight">{{ $title }}</h3>
                            <p class="text-[12px] text-white/85 mt-0.5">{{ $hint }}</p>
                        </div>
                        <button type="button" @click="photoSheet = false" aria-label="{{ __('shared.close') }}"
                                class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>

                {{-- A read-only sheet would have no footer; this one's body IS
                     the last element, so it carries the safe-area padding. --}}
                <div class="flex-1 overflow-y-auto px-5 pt-4"
                     style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
                    <div class="grid grid-cols-2 gap-3">
                        <button type="button" x-show="!cameraFallback" @click="openCamera()"
                                class="ph-tile m-press text-white"
                                style="background: linear-gradient(155deg, {{ $c }}, {{ $c }}b0); box-shadow: 0 20px 42px -22px {{ $c }};">
                            <span class="ph-corner tl"></span><span class="ph-corner tr"></span>
                            <span class="ph-corner bl"></span><span class="ph-corner br"></span>
                            <span class="ph-lens" style="background: rgba(255,255,255,.16); box-shadow: 0 0 0 8px rgba(255,255,255,.08);">
                                <i class="bi bi-camera" style="font-size: 26px;"></i>
                            </span>
                            <span class="relative">
                                <span class="ph-title block">{{ __('events.public_enrol_photo_take') }}</span>
                                <span class="ph-hint block" style="color: rgba(255,255,255,.8);">{{ __('events.public_enrol_photo_take_hint') }}</span>
                            </span>
                        </button>

                        {{-- The same tile when there is no camera API or the
                             permission was refused: one door, not a third
                             option appearing underneath. --}}
                        <label x-show="cameraFallback" x-cloak
                               class="ph-tile m-press text-white cursor-pointer"
                               style="background: linear-gradient(155deg, {{ $c }}, {{ $c }}b0); box-shadow: 0 20px 42px -22px {{ $c }};">
                            <span class="ph-corner tl"></span><span class="ph-corner tr"></span>
                            <span class="ph-corner bl"></span><span class="ph-corner br"></span>
                            <span class="ph-lens" style="background: rgba(255,255,255,.16); box-shadow: 0 0 0 8px rgba(255,255,255,.08);">
                                <i class="bi bi-camera-fill" style="font-size: 26px;"></i>
                            </span>
                            <span class="relative">
                                <span class="ph-title block">{{ __('events.public_enrol_photo_take') }}</span>
                                <span class="ph-hint block" style="color: rgba(255,255,255,.8);">{{ __('events.public_enrol_photo_take_native_hint') }}</span>
                            </span>
                            <input type="file" accept="image/*" capture="user" class="hidden" @change="handOff($event)">
                        </label>

                        <label class="ph-tile m-press cursor-pointer"
                               style="background: #fff; border: 1.5px solid hsl(210 14% 88%);">
                            <span class="ph-print" style="width: 46px; height: 58px; top: 30px; inset-inline-start: 26px; transform: rotate(-11deg); background: {{ Palette::alpha($c, .1) }};"></span>
                            <span class="ph-print" style="width: 46px; height: 58px; top: 26px; inset-inline-start: 40px; transform: rotate(7deg); background: {{ Palette::alpha($c, .16) }};"></span>
                            <span class="ph-print grid place-items-center" style="width: 50px; height: 62px; top: 32px; inset-inline-start: 33px; background: {{ Palette::alpha($c, .28) }}; color: #fff;">
                                <i class="bi bi-images" style="font-size: 22px;"></i>
                            </span>
                            <span class="relative">
                                <span class="ph-title block text-foreground">{{ __('events.public_enrol_photo_gallery') }}</span>
                                <span class="ph-hint block text-muted-foreground">{{ __('events.public_enrol_photo_gallery_hint') }}</span>
                            </span>
                            <input type="file" accept="image/*" class="hidden" @change="handOff($event)">
                        </label>
                    </div>

                    <p class="text-[11px] text-muted-foreground leading-snug pt-4">{{ __('events.public_enrol_photo_ratio') }}</p>
                </div>
            </div>
        </div>
    </template>

    {{-- ================= The camera =================
         A capture screen laid out like a camera app: the frame in the middle at
         the crop's true 3:4 with the face oval inside it, and every control in
         the bottom third where a thumb already is. The shot goes to the cropper
         rather than straight to the server — the guide gets the face roughly
         right, the crop makes it exact. --}}
    <template x-teleport="body">
        <div x-show="camera" x-cloak class="fixed inset-0 flex flex-col" style="background:#0a0a0f; z-index: {{ $cameraZ }};">
            <div class="cam-stage" style="--cam: {{ $c }};">
                <video x-ref="video" autoplay playsinline muted class="cam-video"
                       :style="cameraFacing === 'user' ? 'transform: scaleX(-1)' : ''"></video>

                <div class="cam-frame">
                    <span class="cam-b tl"></span><span class="cam-b tr"></span>
                    <span class="cam-b bl"></span><span class="cam-b br"></span>
                    <span class="cam-face"></span>
                </div>

                <div class="cam-top">
                    <button type="button" @click="closeCamera()" aria-label="{{ __('shared.close') }}" class="cam-round m-press">
                        <i class="bi bi-x-lg"></i>
                    </button>
                    <span class="cam-label">{{ __('events.entry_field_photo') }}</span>
                    <span class="cam-chip"><i class="bi bi-person-bounding-box"></i>3:4</span>
                </div>

                <p class="cam-hint" x-text="cameraHint"></p>
            </div>

            <div class="cam-bar" style="--cam: {{ $c }};">
                <span></span>
                <button type="button" @click="shoot()" aria-label="{{ __('events.public_enrol_photo_take') }}" class="cam-shutter">
                    <span></span>
                </button>
                <span class="cam-side">
                    <button type="button" @click="flipCamera()" x-show="cameraCanFlip" x-cloak
                            aria-label="{{ __('events.public_enrol_photo_flip') }}" class="cam-round m-press">
                        <i class="bi bi-arrow-repeat"></i>
                    </button>
                </span>
            </div>
        </div>
    </template>

    {{-- The cropper. ONE per project, inline bottom-sheet mode, at the
         platform's portrait 3:4 — stored 600×800. `mode="form"` keeps the bytes
         in a hidden input instead of uploading them, which is what this page
         needs: the PUT carries the data URI and EntryEditor sniffs the real
         bytes and assigns the extension itself. --}}
    <div class="hidden">
        <x-takeone-cropper
            id="{{ $id }}" mode="form" :inline="true"
            :width="600" :height="800" shape="rectangle" :canvasHeight="360"
            folder="temp" filename="entrant" inputName="photo"
            sheetMaxWidth="100%" sheetClass="rounded-t-3xl overflow-hidden shadow-2xl bg-background"
            editorAlign="items-end"
            :showControls="false" :showCancel="false"
            saveText="{{ __('shared.save') }}" />
    </div>
</div>

{{-- The tiles and the camera, styled once. `@once` because two instances on one
     page would otherwise emit the same rules twice. --}}
@once
@push('styles')
<style>
    .ph-tile { position: relative; overflow: hidden; border-radius: 22px; aspect-ratio: 4 / 5;
               display: flex; flex-direction: column; justify-content: flex-end;
               padding: 14px; text-align: start; }
    .ph-corner { position: absolute; width: 16px; height: 16px; border: 2px solid rgba(255,255,255,.55); }
    .ph-corner.tl { top: 12px; inset-inline-start: 12px; border-right: 0; border-bottom: 0; border-radius: 6px 0 0 0; }
    .ph-corner.tr { top: 12px; inset-inline-end: 12px; border-left: 0; border-bottom: 0; border-radius: 0 6px 0 0; }
    .ph-corner.bl { bottom: 12px; inset-inline-start: 12px; border-right: 0; border-top: 0; border-radius: 0 0 0 6px; }
    .ph-corner.br { bottom: 12px; inset-inline-end: 12px; border-left: 0; border-top: 0; border-radius: 0 0 6px 0; }
    .ph-lens { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -58%);
               width: 62px; height: 62px; border-radius: 50%; display: grid; place-items: center; }
    .ph-print { position: absolute; border-radius: 8px; }
    .ph-title { font-size: 13px; font-weight: 900; line-height: 1.15; }
    .ph-hint { font-size: 10.5px; line-height: 1.3; margin-top: 3px; }

    /* The camera. */
    .cam-stage { position: relative; flex: 1; overflow: hidden; }
    .cam-video { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
    .cam-frame { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -54%);
                 width: min(78vw, 360px); aspect-ratio: 3 / 4; border-radius: 20px;
                 box-shadow: 0 0 0 100vmax rgba(6, 8, 16, .62); pointer-events: none; }
    .cam-b { position: absolute; width: 26px; height: 26px; border: 3px solid var(--cam); }
    .cam-b.tl { top: -1px; left: -1px;  border-right: 0; border-bottom: 0; border-radius: 20px 0 0 0; }
    .cam-b.tr { top: -1px; right: -1px; border-left: 0;  border-bottom: 0; border-radius: 0 20px 0 0; }
    .cam-b.bl { bottom: -1px; left: -1px;  border-right: 0; border-top: 0; border-radius: 0 0 0 20px; }
    .cam-b.br { bottom: -1px; right: -1px; border-left: 0; border-top: 0; border-radius: 0 0 20px 0; }
    .cam-face { position: absolute; left: 50%; top: 42%; transform: translate(-50%, -50%);
                width: 62%; height: 66%; border-radius: 50%; border: 2px dashed rgba(255,255,255,.55); }
    .cam-top { position: absolute; inset-inline: 0; top: 0; z-index: 2;
               display: flex; align-items: center; justify-content: space-between;
               gap: 12px; padding: calc(env(safe-area-inset-top) + 14px) 16px 14px;
               background: linear-gradient(rgba(6,8,16,.72), rgba(6,8,16,0)); }
    .cam-round { width: 42px; height: 42px; border-radius: 50%; display: grid; place-items: center;
                 color: #fff; background: rgba(255,255,255,.14);
                 border: 1px solid rgba(255,255,255,.22); backdrop-filter: blur(6px); }
    .cam-label { font-size: 11px; font-weight: 800; letter-spacing: .14em; text-transform: uppercase;
                 color: rgba(255,255,255,.9); }
    .cam-chip { display: inline-flex; align-items: center; gap: 6px; padding: 5px 11px;
                border-radius: 999px; background: rgba(255,255,255,.14);
                border: 1px solid rgba(255,255,255,.2); backdrop-filter: blur(6px);
                font-size: 10px; font-weight: 800; letter-spacing: .1em; color: #fff; }
    .cam-hint { position: absolute; inset-inline: 0; bottom: 18px; z-index: 2; text-align: center;
                padding: 0 28px; font-size: 12.5px; line-height: 1.45; font-weight: 600;
                color: rgba(255,255,255,.86); text-shadow: 0 2px 12px rgba(0,0,0,.6); }
    .cam-bar { flex-shrink: 0; display: grid; grid-template-columns: 1fr auto 1fr;
               align-items: center; padding: 20px 26px calc(24px + env(safe-area-inset-bottom)); }
    .cam-shutter { grid-column: 2; width: 78px; height: 78px; border-radius: 50%;
                   display: grid; place-items: center; background: transparent;
                   border: 3px solid rgba(255,255,255,.9); transition: transform .12s ease; }
    .cam-shutter:active { transform: scale(.94); }
    .cam-shutter > span { width: 62px; height: 62px; border-radius: 50%; background: #fff;
                          box-shadow: 0 0 0 4px var(--cam); }
    .cam-side { display: flex; justify-content: flex-end; }
</style>
@endpush
@endonce
