@props([
    'url' => null,               // single QR target (string) — original API
    'title' => '',               // heading shown in the modal
    'caption' => '',             // small helper text under the title
    'filename' => 'qrcode',      // download base name (no extension)
    'label' => 'QR code',        // trigger button text
    'icon' => 'bi-qr-code',      // trigger button icon
    'size' => 220,               // on-screen QR size (px)
    'posterUrl' => null,         // optional printable-poster URL
    'buttonClass' => null,       // override the trigger button styling
    'iconOnly' => false,         // trigger shows the icon alone (label becomes its accessible name)
    'targets' => null,           // optional: array of ['url','tab','title','caption','filename','poster']
                                 //           — renders ONE modal with a tab switcher between QRs
])

@php
    use Illuminate\Support\Str;

    // Normalise to a list of QR "items" so single- and multi-target usage share one render path.
    if (is_array($targets) && count($targets)) {
        $items = collect($targets)->map(fn ($t) => [
            'url'      => $t['url'],
            'tab'      => $t['tab'] ?? $t['label'] ?? $t['title'] ?? 'QR',
            'title'    => $t['title'] ?? $t['tab'] ?? '',
            'caption'  => $t['caption'] ?? '',
            'filename' => $t['filename'] ?? 'qrcode',
            'poster'   => $t['poster'] ?? $t['posterUrl'] ?? null,
        ])->values()->all();
    } else {
        $items = [[
            'url'      => $url,
            'tab'      => $label ?: 'QR',
            'title'    => $title ?: $label,
            'caption'  => $caption,
            'filename' => $filename,
            'poster'   => $posterUrl,
        ]];
    }

    // Render each QR once, server-side (offline). Higher native size keeps the PNG crisp.
    foreach ($items as $k => $it) {
        $items[$k]['svg'] = \App\Support\Qr::svg($it['url'], 512, 1);
    }

    // The JS-facing payload (no SVG markup needed client-side).
    $jsItems = array_map(fn ($it) => [
        'link'     => $it['url'],
        'title'    => $it['title'],
        'caption'  => $it['caption'],
        'filename' => $it['filename'],
        'poster'   => $it['poster'],
    ], $items);

    $uid = 'qr_' . Str::random(6);
    $btn = $buttonClass
        ?: 'inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-primary text-primary bg-transparent text-sm font-medium hover:bg-primary hover:text-white transition-colors';
@endphp

{{-- The trigger.

     `iconOnly` exists because a caller that wants a compact 40x40 control had no
     way to say so: the label was printed unconditionally, so "Camera QR" was laid
     out inside a `w-10 h-10` box and spilled straight out of it. The label is not
     dropped when it is hidden — it becomes the button's ACCESSIBLE NAME, because
     a bare icon says nothing to a screen reader, and "QR" is not a word a button
     can be identified by out loud. --}}
<div x-data="qrCode(@js([
        'uid'          => $uid,
        'items'        => $jsItems,
        'messagesBase' => url('/messages'),
        'searchUrl'    => route('messages.search-users'),
     ]))" class="inline-block">
    <button type="button" @click="open = true" class="m-press {{ $btn }}"
            @if($iconOnly && ($label || $title)) aria-label="{{ $label ?: $title }}" @endif>
        <i class="bi {{ $icon }}"></i>@unless($iconOnly) {{ $label }}@endunless
    </button>

    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 z-[80] flex items-end sm:items-center justify-center">
            {{-- Backdrop --}}
            <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"
                 x-show="open" x-transition.opacity @click="open = false"></div>

            {{-- Sheet / dialog --}}
            <div class="relative w-full sm:max-w-sm bg-white rounded-t-3xl sm:rounded-2xl shadow-2xl max-h-[92vh] flex flex-col"
                 x-show="open" x-cloak
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="translate-y-full sm:translate-y-4 opacity-0" x-transition:enter-end="translate-y-0 opacity-100"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="translate-y-0 opacity-100" x-transition:leave-end="translate-y-full sm:translate-y-4 opacity-0">

                <div class="flex-shrink-0 px-5 pt-3 pb-4 rounded-t-3xl sm:rounded-t-2xl text-white relative overflow-hidden"
                     style="background: linear-gradient(150deg, #7c6bf5, #7c6bf5b0);">
                    <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                    <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3"></div>

                    <div class="relative flex items-start gap-3">
                        <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0">
                            <i class="bi bi-qr-code text-xl"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <h2 class="text-lg font-black leading-tight truncate" x-text="cur.title"></h2>
                            <p class="text-[12px] text-white/85 mt-0.5 truncate" x-show="cur.caption" x-text="cur.caption"></p>
                        </div>
                        <button type="button" @click="open = false" aria-label="{{ __('shared.close') }}"
                                class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform"><i class="bi bi-x-lg"></i></button>
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto px-5 py-5" style="padding-bottom: calc(1.25rem + env(safe-area-inset-bottom));">
                    {{-- Tab switcher (only when more than one QR target) --}}
                    @if(count($items) > 1)
                    <div class="flex gap-1 p-1 bg-muted rounded-xl mb-4">
                        @foreach($items as $i => $it)
                        <button type="button" @click="active = {{ $i }}"
                                class="flex-1 px-3 py-2 rounded-lg text-xs font-semibold transition-colors"
                                :class="active === {{ $i }} ? 'bg-white text-primary shadow-sm' : 'text-muted-foreground'">{{ $it['tab'] }}</button>
                        @endforeach
                    </div>
                    @endif

                    {{-- The QR(s) (server-rendered SVG). Each box keeps its native 512px attrs for a
                         crisp PNG export; a scoped rule scales it to fit. Only the active one shows. --}}
                    <style>[id^="qrbox_{{ $uid }}_"] > svg { width: 100% !important; height: 100% !important; display: block; }</style>
                    @foreach($items as $i => $it)
                    <div x-show="active === {{ $i }}" {{ $i === 0 ? '' : 'x-cloak' }}
                         class="mx-auto bg-white rounded-2xl border border-gray-100 p-4 grid place-items-center"
                         style="width: 100%; max-width: {{ $size + 32 }}px;">
                        <div id="qrbox_{{ $uid }}_{{ $i }}" class="leading-none" style="width: {{ $size }}px; max-width: 100%; aspect-ratio: 1 / 1;">{!! $it['svg'] !!}</div>
                    </div>
                    @endforeach

                    <a :href="cur.link" target="_blank" rel="noopener"
                       class="block text-center text-[11px] text-primary break-all mt-3 hover:underline" x-text="cur.link"></a>

                    {{-- One primary action: the device share sheet with the QR image + link
                         (WhatsApp / save / copy / other apps in a single tap). Everything
                         else is tucked behind "More options" so the sheet stays clean. --}}
                    <button type="button" @click="share()"
                            class="m-press w-full inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl bg-primary text-white text-sm font-semibold hover:bg-primary/90 transition-colors mt-4">
                        <i class="bi bi-share-fill"></i> {{ __('shared.components_qr_code_share') }}
                    </button>

                    <button type="button" @click="more = !more"
                            class="m-press w-full inline-flex items-center justify-center gap-1 text-[11px] text-muted-foreground hover:text-foreground transition-colors mt-2.5">
                        <span x-text="more ? '{{ __('shared.components_qr_code_hide_options') }}' : '{{ __('shared.components_qr_code_more_options') }}'"></span>
                        <i class="bi text-[10px]" :class="more ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
                    </button>

                    {{-- x-transition, not x-collapse: the Alpine Collapse plugin is not
                         registered in this project, so x-collapse only logs a warning
                         and the panel appears with no animation at all. --}}
                    <div x-show="more" x-cloak
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 -translate-y-1"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         x-transition:leave="transition ease-in duration-150"
                         x-transition:leave-start="opacity-100 translate-y-0"
                         x-transition:leave-end="opacity-0 -translate-y-1"
                         class="grid grid-cols-2 gap-2 mt-2">
                        <button type="button" @click="downloadPng()" class="m-press inline-flex items-center justify-center gap-2 px-3 py-2.5 rounded-lg border border-gray-200 text-foreground text-sm font-medium hover:bg-muted transition-colors">
                            <i class="bi bi-download"></i> {{ __('shared.components_qr_code_png') }}
                        </button>
                        <button type="button" @click="downloadSvg()" class="m-press inline-flex items-center justify-center gap-2 px-3 py-2.5 rounded-lg border border-gray-200 text-foreground text-sm font-medium hover:bg-muted transition-colors">
                            <i class="bi bi-filetype-svg"></i> {{ __('shared.components_qr_code_svg') }}
                        </button>
                        <button type="button" @click="copyLink()" class="m-press inline-flex items-center justify-center gap-2 px-3 py-2.5 rounded-lg border border-gray-200 text-foreground text-sm font-medium hover:bg-muted transition-colors" :class="cur.poster ? '' : 'col-span-2'">
                            <i class="bi bi-link-45deg"></i> {{ __('shared.components_qr_code_copy_link') }}
                        </button>
                        <a x-show="cur.poster" :href="cur.poster" target="_blank" rel="noopener" class="m-press inline-flex items-center justify-center gap-2 px-3 py-2.5 rounded-lg border border-gray-200 text-foreground text-sm font-medium hover:bg-muted transition-colors">
                            <i class="bi bi-printer"></i> {{ __('shared.components_qr_code_poster') }}
                        </a>
                        <button type="button" @click="toggleChat()" class="m-press col-span-2 inline-flex items-center justify-center gap-2 px-3 py-2.5 rounded-lg bg-accent text-primary text-sm font-semibold hover:bg-accent/80 transition-colors">
                            <i class="bi bi-chat-dots-fill"></i> {{ __('shared.components_qr_code_send_in_chat') }}
                        </button>
                    </div>

                    {{-- Internal chat picker --}}
                    <div x-show="chat.open" x-cloak class="mt-4 pt-4 border-t border-gray-100">
                        <div class="relative">
                            <i class="bi bi-search absolute start-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                            <input type="text" x-model="chat.q" @input.debounce.300ms="searchChat()" x-ref="chatSearch"
                                   placeholder="{{ __('shared.components_qr_code_search_placeholder') }}"
                                   class="w-full ps-9 pe-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        </div>
                        <div class="mt-2 max-h-52 overflow-y-auto space-y-1">
                            <template x-for="u in chat.results" :key="u.id">
                                <button type="button" @click="sendToChat(u)" :disabled="chat.sending"
                                        class="m-press w-full text-start rounded-xl p-2 flex items-center gap-2.5 hover:bg-muted transition-colors disabled:opacity-50">
                                    <span class="w-9 h-9 rounded-full overflow-hidden bg-muted grid place-items-center flex-shrink-0">
                                        <template x-if="u.avatar"><img :src="u.avatar" alt="" class="w-[27px] h-9 object-cover"></template>
                                        <template x-if="!u.avatar"><span class="text-[11px] font-bold text-muted-foreground" x-text="u.initial"></span></template>
                                    </span>
                                    <span class="min-w-0 flex-1 text-sm font-medium text-foreground truncate" x-text="u.name"></span>
                                    <i class="bi" :class="chat.sending ? 'bi-arrow-repeat animate-spin' : 'bi-send'"></i>
                                </button>
                            </template>
                            <p x-show="chat.q.length>0 && !chat.searching && chat.results.length===0" x-cloak class="text-center text-xs text-muted-foreground py-6">{{ __('shared.components_qr_code_no_one_found') }}</p>
                            <p x-show="chat.searching" x-cloak class="text-center text-xs text-muted-foreground py-4"><i class="bi bi-arrow-repeat animate-spin"></i> {{ __('shared.components_qr_code_searching') }}</p>
                            <p x-show="!chat.q" x-cloak class="text-center text-xs text-muted-foreground py-4">{{ __('shared.components_qr_code_search_hint') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </template>

{{-- The component's behaviour, registered ONCE per page.

     It used to be an inline `<script>` declaring `function qrCode_<uid>()`
     right here, next to the markup. That works only when the script executes
     in DOM order before Alpine reaches the `x-data` — and on this page it does
     not: `<x-qr-code>` is rendered INSIDE another component's
     `x-teleport="body"` template (event-public-link), and a `<script>` inside a
     `<template>` is inert. The factory was therefore never defined, `x-data`
     evaluated to an empty scope, and every binding failed at once — the QR hid
     itself (`active === 0` was false), the link rendered as `href=""`, and
     Share had no url to send. The same hazard applies to any shell-swapped
     content, where innerHTML never runs scripts either.

     So the behaviour is registered as a real Alpine component from the page's
     script stack, which renders outside every template, and the per-instance
     values arrive as config. Nothing about this component now depends on where
     its markup sits (CLAUDE.md → Standalone Self-Contained Components). --}}
@once
@push('scripts')
    <script>
    (function () {
        var register = function () {
            if (! window.Alpine || window.__qrCodeRegistered) return;
            window.__qrCodeRegistered = true;
            window.Alpine.data('qrCode', function (cfg) {
                return {
            open: false,
            more: false,
            active: 0,
            uid: cfg.uid,
            items: cfg.items || [],
            get cur() { return this.items[this.active] || {}; },
            chat: { open: false, q: '', results: [], searching: false, sending: false },
            messagesBase: cfg.messagesBase,
            searchUrl: cfg.searchUrl,
            shareText() { return (this.cur.title ? this.cur.title + ' — ' : '') + this.cur.link; },
            _csrf() { var m = document.querySelector('meta[name="csrf-token"]'); return m ? m.content : ''; },

            // ── One-tap share ──
            // Opens the native share sheet with the QR image + link (so WhatsApp, save,
            // copy and other apps are all one tap away). Falls back to link share, then
            // to copying the link on desktops without the Web Share API.
            async share() {
                try {
                    const blob = await this._pngBlob();
                    const file = new File([blob], this.cur.filename + '.png', { type: 'image/png' });
                    if (navigator.canShare && navigator.canShare({ files: [file] })) {
                        await navigator.share({ files: [file], title: this.cur.title, text: this.shareText(), url: this.cur.link });
                        return;
                    }
                } catch (e) { if (e && e.name === 'AbortError') return; /* else fall through */ }

                if (navigator.share) {
                    try { await navigator.share({ title: this.cur.title, text: this.cur.title, url: this.cur.link }); return; }
                    catch (e) { if (e && e.name === 'AbortError') return; }
                }
                this.copyLink();   // desktop fallback
            },
            shareWhatsapp() {
                window.open('https://wa.me/?text=' + encodeURIComponent(this.shareText()), '_blank', 'noopener');
            },

            // ── Internal chat share ──
            toggleChat() {
                this.chat.open = !this.chat.open;
                if (this.chat.open) this.$nextTick(() => { this.$refs.chatSearch && this.$refs.chatSearch.focus(); });
            },
            async searchChat() {
                var q = this.chat.q.trim();
                if (!q) { this.chat.results = []; return; }
                this.chat.searching = true;
                try {
                    var res = await fetch(this.searchUrl + '?q=' + encodeURIComponent(q), {
                        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }, credentials: 'same-origin',
                    });
                    var data = await res.json().catch(() => ({}));
                    this.chat.results = data.users || [];
                } catch (e) { this.chat.results = []; }
                finally { this.chat.searching = false; }
            },
            async sendToChat(u) {
                if (this.chat.sending) return;
                this.chat.sending = true;
                try {
                    var h = { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this._csrf(), 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' };
                    var s = await fetch(this.messagesBase + '/start/' + u.id, { method: 'POST', headers: h, credentials: 'same-origin', body: '{}' });
                    var sd = await s.json().catch(() => ({}));
                    if (!s.ok || !sd.success || !sd.conversation_id) { window.showToast && window.showToast('error', sd.message || '{{ __('shared.components_qr_code_could_not_start_chat') }}'); return; }
                    var r = await fetch(this.messagesBase + '/' + sd.conversation_id + '/send', {
                        method: 'POST', headers: h, credentials: 'same-origin', body: JSON.stringify({ body: this.shareText() }),
                    });
                    var rd = await r.json().catch(() => ({}));
                    if (!r.ok || !rd.success) { window.showToast && window.showToast('error', rd.message || '{{ __('shared.components_qr_code_could_not_send') }}'); return; }
                    window.showToast && window.showToast('success', '{{ __('shared.components_qr_code_sent_to') }} ' + (u.name || '{{ __('shared.components_qr_code_chat') }}'));
                    this.chat.open = false; this.chat.q = ''; this.chat.results = [];
                } catch (e) { window.showToast && window.showToast('error', '{{ __('shared.components_qr_code_network_error') }}'); }
                finally { this.chat.sending = false; }
            },

            _svgEl() {
                var box = document.getElementById('qrbox_' + this.uid + '_' + this.active);
                return box ? box.querySelector('svg') : null;
            },
            _svgString() {
                var el = this._svgEl();
                if (!el) return '';
                el.setAttribute('xmlns', 'http://www.w3.org/2000/svg');
                return new XMLSerializer().serializeToString(el);
            },
            downloadSvg() {
                var xml = this._svgString(); if (!xml) return;
                var blob = new Blob([xml], { type: 'image/svg+xml;charset=utf-8' });
                this._save(URL.createObjectURL(blob), this.cur.filename + '.svg');
            },
            // Rasterise the active QR SVG to a 1024px PNG Blob (white background).
            _pngBlob() {
                return new Promise((resolve, reject) => {
                    var xml = this._svgString();
                    if (!xml) { reject(new Error('no-svg')); return; }
                    var url = URL.createObjectURL(new Blob([xml], { type: 'image/svg+xml;charset=utf-8' }));
                    var img = new Image();
                    img.onload = function () {
                        var S = 1024, c = document.createElement('canvas');
                        c.width = S; c.height = S;
                        var ctx = c.getContext('2d');
                        ctx.fillStyle = '#ffffff'; ctx.fillRect(0, 0, S, S);
                        ctx.drawImage(img, 0, 0, S, S);
                        URL.revokeObjectURL(url);
                        c.toBlob(function (b) { b ? resolve(b) : reject(new Error('no-blob')); }, 'image/png');
                    };
                    img.onerror = function () { URL.revokeObjectURL(url); reject(new Error('img')); };
                    img.src = url;
                });
            },
            downloadPng() {
                var self = this;
                this._pngBlob()
                    .then(function (b) { self._save(URL.createObjectURL(b), self.cur.filename + '.png'); })
                    .catch(function () { window.showToast && window.showToast('error', '{{ __('shared.components_qr_code_could_not_export_png') }}'); });
            },
            async copyLink() {
                try { await navigator.clipboard.writeText(this.cur.link); window.showToast && window.showToast('success', '{{ __('shared.components_qr_code_link_copied') }}'); }
                catch (e) { window.showToast && window.showToast('error', '{{ __('shared.components_qr_code_could_not_copy_link') }}'); }
            },
            _save(href, name) {
                var a = document.createElement('a');
                a.href = href; a.download = name;
                document.body.appendChild(a); a.click(); a.remove();
            },
                };
            });
        };

        // Both orders have to work: on a first paint Alpine has not started yet
        // (register on alpine:init), and after a mobile/admin shell swap it is
        // already running (register immediately, or the name never resolves).
        if (window.Alpine) { register(); }
        document.addEventListener('alpine:init', register);
    })();
    </script>
@endpush
@endonce
</div>
