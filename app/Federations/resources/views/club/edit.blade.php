{{--
    The federation dashboard's club editor (the event's Clubs tab): the club's
    name, contact person, email, phone, address and logo — in a same-origin
    frame like the athlete and official editors. Talks to the dashboard by
    postMessage:

      frame → dashboard   {type: 'fed-club:close'}
                          {type: 'fed-club:saved', message}

    Holds no club data in its source. It reads through federations.api.clubs.detail
    and saves through federations.api.clubs.update / .logo; FederationClubManager
    decides which clubs this federation may change (a pending application, or a
    club the federation itself created). A club run by its owner opens read-only.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('federations::partials.frame-head', ['title' => __('Club')])
    <style>
        /* A logo is a transparent PNG of any shape: shown bare, never on a tile (Design Rule #5). */
        .ce-logo { position: relative; width: 108px; height: 108px; flex: none; border-radius: 16px; border: 1.5px dashed #d5dae3; background: transparent; display: grid; place-items: center; cursor: pointer; padding: 6px; }
        .ce-logo:hover { border-color: #caa14f; }
        .ce-logo:disabled { cursor: default; border-style: solid; border-color: #eef1f6; }
        .ce-logo img { width: 100%; height: 100%; object-fit: contain; display: block; }
        .ce-logo .ae-cam { position: absolute; right: -6px; bottom: -6px; width: 32px; height: 32px; border-radius: 999px; background: #1e2c4f; color: #edcd85; display: grid; place-items: center; box-shadow: 0 4px 12px rgba(30,44,79,.3); }
        .ce-note { display: flex; gap: 10px; align-items: flex-start; padding: 12px 14px; border-radius: 14px; background: #fdf6e6; border: 1px solid #e7cf94; font-size: .8rem; line-height: 1.5; color: #8a6d20; }
    </style>
    <script>
    /* The editor's state. Registered before Alpine starts (Alpine is deferred). */
    window.fedClubEdit = function (cfg) {
        return {
            cfg, loading: true, failed: '', busy: false, msg: '',
            editable: false, kind: '', upcoming: [],
            logoUrl: '', logoData: '',
            f: { name: '', coach: '', email: '', phone: '', address: '' },
            saved: '',

            get initials() { return String(this.f.name || '').trim().split(/\s+/).filter(Boolean).slice(0, 2).map(w => w[0]).join('').toUpperCase() || '?'; },
            get ready() { return this.editable && this.f.name.trim().length >= 2 && ! this.busy; },
            get changed() { return JSON.stringify(this.f) !== this.saved || !! this.logoData; },

            post(type, extra) {
                try { if (window.parent !== window) window.parent.postMessage(Object.assign({ type }, extra || {}), window.location.origin); } catch (e) {}
            },
            close() { this.post('fed-club:close'); },
            csrf() { return document.querySelector('meta[name=csrf-token]').content; },
            async call(url, method, body) {
                try {
                    const res = await fetch(url, { method, credentials: 'same-origin',
                        headers: Object.assign({ 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': this.csrf() },
                            body ? { 'Content-Type': 'application/json' } : {}),
                        body: body ? JSON.stringify(body) : undefined });
                    if (res.status === 401 || res.status === 419) { window.top.location.reload(); return { __ok: false }; }
                    const j = await res.json().catch(() => ({}));
                    j.__ok = res.ok && j.success !== false;
                    return j;
                } catch (e) {
                    return { __ok: false, message: 'The connection dropped. Nothing was changed — try again.' };
                }
            },
            errOf(j) {
                if (j && j.errors) { const k = Object.keys(j.errors)[0]; if (k) return j.errors[k][0]; }
                return (j && j.message) || 'That did not save. Try again.';
            },

            async init() {
                /* The shared cropper (form mode) announces its result; a circle
                   is masked once more here so nothing outside it is ever kept. */
                const key = '__clCrop';
                if (window[key]) document.removeEventListener('cropperCropped', window[key]);
                window[key] = (e) => {
                    const d = e.detail || {};
                    if (d.id !== 'clLogo' || ! d.base64) return;
                    const chip = document.querySelector('[data-crop-shape-for="clLogo"][aria-checked="true"]');
                    const circle = chip && chip.getAttribute('data-crop-shape') === 'circle';
                    (circle ? this.circleMask(d.base64) : Promise.resolve(d.base64)).then(b => { this.logoData = b; this.logoUrl = b; this.msg = ''; });
                };
                document.addEventListener('cropperCropped', window[key]);
                document.addEventListener('keydown', (e) => {
                    if (e.key !== 'Escape') return;
                    const ed = document.getElementById('editor_clLogo');
                    if (ed && ed.style.display === 'flex') return;
                    const at = document.activeElement;
                    if (at && at !== document.body && at.closest('main')) { at.blur(); return; }
                    this.close();
                });
                const j = await this.call(cfg.urls.show + '?club=' + encodeURIComponent(cfg.club), 'GET');
                if (! j.__ok || ! j.club) { this.failed = 'This club cannot be opened here. It may have been approved or removed since the list was drawn.'; this.loading = false; return; }
                const c = j.club;
                this.kind = c.kind || '';
                this.editable = !! c.editable;
                this.upcoming = c.upcoming || [];
                this.logoUrl = c.logo || '';
                const fl = c.fields || {};
                this.f = { name: fl.name || '', coach: fl.coach || '', email: fl.email || '', phone: fl.phone || '', address: fl.address || '' };
                this.saved = JSON.stringify(this.f);
                this.loading = false;
            },

            /* A logo on file opens in the cropper to adjust and re-crop ("New photo"
               inside it picks another file); with none, the file picker opens. */
            pickLogo() {
                if (! this.editable) return;
                if (this.logoUrl) {
                    document.dispatchEvent(new CustomEvent('cropper:open', { detail: { id: 'clLogo', src: this.logoUrl } }));
                    return;
                }
                this.$refs.file.click();
            },
            /* A picked file goes to the shared cropper (circle, square, portrait, landscape). */
            picked(ev) {
                const file = ev.target.files && ev.target.files[0];
                ev.target.value = '';
                if (! file) return;
                if (! /^image\/(png|jpeg|webp|gif)$/.test(file.type)) { this.msg = 'Choose a PNG, JPG or WebP image.'; return; }
                if (file.size > 8 * 1024 * 1024) { this.msg = 'That picture is larger than 8 MB.'; return; }
                const input = document.getElementById('input_clLogo');
                if (! input) return;
                const dt = new DataTransfer(); dt.items.add(file);
                input.files = dt.files;
                input.dispatchEvent(new Event('change', { bubbles: true }));
            },
            /* Everything outside the circle made transparent, saved as PNG. */
            circleMask(src) {
                return new Promise((resolve) => {
                    const img = new Image();
                    img.onload = () => {
                        const n = Math.min(img.width, img.height);
                        const c = document.createElement('canvas'); c.width = n; c.height = n;
                        const x = c.getContext('2d');
                        x.drawImage(img, (img.width - n) / 2, (img.height - n) / 2, n, n, 0, 0, n, n);
                        x.globalCompositeOperation = 'destination-in';
                        x.beginPath(); x.arc(n / 2, n / 2, n / 2, 0, 2 * Math.PI); x.fill();
                        resolve(c.toDataURL('image/png'));
                    };
                    img.onerror = () => resolve(src);
                    img.src = src;
                });
            },

            async save() {
                if (! this.ready) return;
                this.busy = true; this.msg = '';
                if (JSON.stringify(this.f) !== this.saved) {
                    const j = await this.call(cfg.urls.update, 'POST', Object.assign({ club: cfg.club }, this.f));
                    if (! j.__ok) { this.msg = this.errOf(j); this.busy = false; return; }
                    this.saved = JSON.stringify(this.f);
                }
                if (this.logoData) {
                    const j = await this.call(cfg.urls.logo, 'POST', { club: cfg.club, logo: this.logoData });
                    if (! j.__ok) { this.msg = this.errOf(j); this.busy = false; return; }
                    this.logoData = '';
                }
                this.busy = false;
                this.post('fed-club:saved', { message: 'Club saved.' });
            },
        };
    };
    </script>
    <script defer src="{{ asset('vendor/alpinejs/3.17.4/alpine.min.js') }}"></script>
</head>
<body class="antialiased">
<div x-data="fedClubEdit(@js(['club' => $club, 'urls' => $urls]))" class="ae-shell flex flex-col">

    {{-- Header band (Design Rule #8): the club is the headline. --}}
    <header class="flex-shrink-0 z-40 px-5 pt-4 pb-4 text-white relative overflow-hidden"
            style="background: linear-gradient(150deg, #1e2c4f, #1e2c4fb0), #fff;">
        <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
        <div class="relative flex items-start gap-3">
            <span class="w-12 h-12 flex-shrink-0 grid place-items-center">
                <template x-if="logoUrl"><img :src="logoUrl" alt="" class="w-full h-full object-contain"></template>
                <template x-if="! logoUrl"><span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center"><i class="bi bi-shield-fill text-xl" style="color:#edcd85"></i></span></template>
            </span>
            <div class="min-w-0 flex-1">
                <h1 class="text-lg font-black leading-tight" style="overflow-wrap:anywhere" x-text="f.name || @js(__('Club'))">{{ __('Club') }}</h1>
                <p class="text-[12px] text-white/85 mt-0.5"
                   x-text="kind === 'pending' ? @js(__('Club application — waiting for approval')) : (editable ? @js(__('Registered club')) : @js(__('Registered club · run by its owner')))"></p>
            </div>
            <button type="button" @click="close()" aria-label="{{ __('Close') }}"
                    class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform hover:bg-white/25">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
    </header>

    <main class="ae-scroll flex-1 min-h-0 px-5 py-4 space-y-4">
        <template x-if="loading">
            <div class="space-y-3" aria-busy="true">
                <div class="flex gap-4"><div class="ae-skel" style="width:108px;height:108px;border-radius:16px"></div>
                    <div class="flex-1 space-y-3"><div class="ae-skel h-11"></div><div class="ae-skel h-11"></div></div></div>
                <div class="ae-skel h-24"></div>
            </div>
        </template>

        <template x-if="! loading && failed">
            <div class="ce-note"><i class="bi bi-exclamation-circle"></i><span x-text="failed"></span></div>
        </template>

        <div x-show="! loading && ! failed" x-cloak class="space-y-4">
            <template x-if="! editable">
                <div class="ce-note"><i class="bi bi-lock-fill"></i>
                    <span>{{ __('This club is run by its owner on TakeOne — only they can change its details. You can still see them here.') }}</span></div>
            </template>

            <fieldset :disabled="! editable" class="ae-sec">
                <h2 class="ae-h"><i class="bi bi-shield-fill"></i>{{ __('Club') }}</h2>
                <div class="flex gap-4 items-start">
                    <button type="button" class="ce-logo" @click="pickLogo()" :disabled="! editable" aria-label="{{ __('Change the logo') }}">
                        <template x-if="logoUrl"><img :src="logoUrl" alt=""></template>
                        <template x-if="! logoUrl"><span class="text-xl font-black" style="color:#8a6d20" x-text="initials"></span></template>
                        <span class="ae-cam" x-show="editable"><i class="bi bi-camera-fill text-sm"></i></span>
                    </button>
                    <div class="flex-1 min-w-0">
                        <label class="ae-lbl" for="ceName">{{ __('Club name') }}</label>
                        <input id="ceName" type="text" class="ae-in" x-model="f.name" maxlength="120" autocomplete="off">
                        <p class="text-[11px] text-gray-500 mt-2" x-show="editable">{{ __('Tap the logo to choose a picture, then frame it as a circle, square, portrait or landscape. A PNG with a transparent background looks best.') }}</p>
                    </div>
                </div>
            </fieldset>

            <fieldset :disabled="! editable" class="ae-sec space-y-3">
                <h2 class="ae-h"><i class="bi bi-person-lines-fill"></i>{{ __('Contact') }}</h2>
                <div>
                    <label class="ae-lbl" for="ceCoach" x-text="kind === 'pending' ? @js(__('Coach')) : @js(__('Owner / contact person'))"></label>
                    <input id="ceCoach" type="text" class="ae-in" x-model="f.coach" maxlength="120" autocomplete="off">
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="ae-lbl" for="ceEmail">{{ __('Email') }}</label>
                        <input id="ceEmail" type="email" class="ae-in" x-model="f.email" maxlength="190" autocomplete="off" inputmode="email">
                    </div>
                    <div>
                        <label class="ae-lbl" for="cePhone">{{ __('Phone') }}</label>
                        <input id="cePhone" type="tel" class="ae-in" x-model="f.phone" maxlength="40" autocomplete="off" inputmode="tel">
                    </div>
                </div>
                <div>
                    <label class="ae-lbl" for="ceAddress">{{ __('Address') }}</label>
                    <input id="ceAddress" type="text" class="ae-in" x-model="f.address" maxlength="255" autocomplete="off">
                </div>
            </fieldset>

            <template x-if="upcoming.length">
                <section class="ae-sec">
                    <h2 class="ae-h"><i class="bi bi-calendar-event"></i>{{ __('Entered in') }}</h2>
                    <ul class="text-sm space-y-1"><template x-for="u in upcoming" :key="u"><li x-text="u"></li></template></ul>
                </section>
            </template>
        </div>
    </main>

    <footer class="flex-shrink-0 z-40 px-5 py-3 bg-white border-t border-gray-100 flex items-center gap-3 flex-wrap"
            style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));" x-show="! loading && ! failed && editable" x-cloak>
        <p class="flex-1 text-[13px] font-semibold" style="color:#b4483c;min-width:120px" role="alert" x-text="msg"></p>
        <button type="button" class="ae-ghost" @click="close()">{{ __('Cancel') }}</button>
        <button type="button" class="ae-save" :disabled="! ready || ! changed" @click="save()"
                x-text="busy ? @js(__('Saving…')) : @js(__('Save'))">{{ __('Save') }}</button>
    </footer>

    {{-- The project's one cropper, never uploading on its own (see the athlete
         editor): a picked logo is framed as a circle, square, portrait or
         landscape. A circle — or a source with transparency — is kept as PNG. --}}
    <input type="file" x-ref="file" accept="image/png,image/jpeg,image/webp,image/gif" class="hidden" style="display:none" @change="picked($event)">
    <div class="hidden" style="display:none">
        <x-takeone-cropper id="clLogo" mode="form" :inline="true" :replaceable="true"
            :width="800" :height="800" shape="circle" :canvasHeight="400"
            shapes="circle,square,portrait,landscape"
            folder="temp" filename="club-logo" inputName="cl_logo"
            sheetMaxWidth="min(600px, calc(100vw - 32px))" sheetClass="ae-crop rounded-2xl shadow-2xl bg-white" editorAlign="items-center"
            :showControls="true" :showCancel="true" saveText="{{ __('Use logo') }}" />
    </div>
</div>

@include('partials.toast-host')
@stack('modals')
@stack('scripts')
</body>
</html>
