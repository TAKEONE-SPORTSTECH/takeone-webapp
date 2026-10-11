@props([
    'subject',                   // the event's uuid
    'color' => '#7c3aed',
    'title' => '',
])

{{--
    Officials' badges — one row of an event console, opening a sheet. The second thing
    the printing engine makes (App\Events\Support\EventBadges, format `badge`: A6 cards,
    four to an A4 sheet), so it shares the certificates' design room and print-shop PDF.

    Nothing is approved or stored per person: every official HAS a badge, drawn as the
    officials stand. The organiser looks at one (a picture in <x-media-lightbox>),
    downloads one, downloads all as one PDF for the print shop, or opens the design room.

    Standalone: its own Alpine state, requests and sheet. It decides nothing — every
    endpoint re-authorises (the event's managers only) — and every server value is set
    with x-text / :src, never innerHTML.
--}}

@php
    $c = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $color) ? $color : '#7c3aed';
    $alias = \App\Events\Support\EventBadges::ALIAS;
    $type = \App\Events\Support\EventBadges::TYPE;
    $urls = [
        'state' => route('me.badges.index', ['key' => $subject]),
        'bundle' => route('me.certificates.bundle', ['context' => $alias, 'key' => $subject, 'type' => $type]),
        'bundleDownload' => route('me.certificates.bundle.download', ['context' => $alias, 'key' => $subject, 'type' => $type]),
    ];
    $viewer = 'open-badge-view';
@endphp

@once
    <x-media-lightbox :event-name="$viewer" />
@endonce

<div x-data="{
        open: false,
        loaded: false,
        rows: [],
        design: null,
        bundle: null,
        _t: null,
        urls: @js($urls),
        headers() { return { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '' }; },
        async call(method, url) {
            const res = await fetch(url, { method, headers: this.headers(), credentials: 'same-origin' });
            const d = await res.json().catch(() => ({}));
            if (! res.ok || d.success === false) throw new Error(d.message || @js(__('cert::messages.failed')));
            return d;
        },
        async load() {
            try {
                const d = await this.call('GET', this.urls.state);
                this.rows = d.badges || [];
                this.design = d.design_url;
                this.bundle = (d.bundles || [])[0] || null;
                this.watch();
            } catch (e) {}
            this.loaded = true;
        },
        get summary() {
            return this.rows.length ? @js(__('cert::messages.badges_row_count')).replace(':count', this.rows.length) : @js(__('cert::messages.badges_row_none'));
        },
        async build() {
            try { const d = await this.call('POST', this.urls.bundle); this.bundle = Object.assign({}, this.bundle || {}, d); this.watch(); }
            catch (e) { window.showToast('error', e.message); }
        },
        watch() {
            clearTimeout(this._t);
            if (! this.open || ! this.bundle || this.bundle.state !== 'building') return;
            this._t = setTimeout(async () => {
                try { const d = await this.call('GET', this.urls.bundle); this.bundle = Object.assign({}, this.bundle, d); } catch (e) {}
                this.watch();
            }, 3000);
        },
        mb(b) { return b ? (b / 1048576).toFixed(1) + ' MB' : ''; },
        view(row) { window.dispatchEvent(new CustomEvent(@js($viewer), { detail: { src: row.image_url, label: row.name + ' · ' + row.role, kind: 'image' } })); },
        dl(row) { return row.pdf_url + '?download=1'; },
     }"
     x-init="load()"
     x-effect="if (open) watch()"
     @realtime:certificates.window="if ($event.detail && $event.detail.action === 'refresh') load()">

    <button type="button" @click="open = true"
            class="m-card m-press w-full text-start bg-white rounded-2xl border border-gray-100 shadow-sm p-3.5 flex items-center gap-3">
        <span class="w-11 h-11 rounded-2xl grid place-items-center flex-shrink-0 bg-accent text-primary">
            <i class="bi bi-person-badge-fill text-lg"></i>
        </span>
        <span class="min-w-0 flex-1">
            <span class="block text-sm font-bold text-foreground">{{ __('cert::messages.badges_title') }}</span>
            <span class="block text-[11px] mt-0.5 truncate text-muted-foreground" x-text="loaded ? summary : ''">&nbsp;</span>
        </span>
        <i class="bi bi-chevron-right rtl:rotate-180 text-muted-foreground/50 text-xs flex-shrink-0"></i>
    </button>

    {{-- Teleported: the mobile shell leaves a transform on its children. --}}
    <template x-teleport="body" data-teleport-template="true">
        <div x-show="open" x-cloak class="fixed inset-0 z-[70] flex items-end sm:items-center justify-center"
             @keydown.escape.window="open = false" style="display:none;">
            <div x-show="open" x-transition.opacity class="absolute inset-0 bg-black/40" @click="open = false"></div>

            <div x-show="open"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="translate-y-full sm:opacity-0" x-transition:enter-end="translate-y-0 sm:opacity-100"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full sm:opacity-0"
                 class="relative w-full sm:max-w-lg max-h-[92vh] flex flex-col bg-background rounded-t-3xl sm:rounded-3xl shadow-2xl overflow-hidden">

                {{-- gradient header band (Design Rule #8) --}}
                <div class="flex-shrink-0 px-5 pt-3 pb-4 rounded-t-3xl text-white relative overflow-hidden"
                     style="background: linear-gradient(150deg, {{ $c }}, {{ $c }}b0);">
                    <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                    <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3 sm:hidden"></div>
                    <div class="relative flex items-start gap-3">
                        <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0">
                            <i class="bi bi-person-badge-fill text-xl"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-lg font-black leading-tight">{{ __('cert::messages.badges_title') }}</h3>
                            <p class="text-[12px] text-white/85 mt-0.5 truncate">{{ $title }}</p>
                        </div>
                        <button type="button" @click="open = false" aria-label="{{ __('shared.close') }}"
                                class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                    <div class="relative mt-3 flex flex-wrap gap-1.5" x-show="loaded">
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-white/20 text-[11px] font-bold">
                            <i class="bi bi-people"></i><span x-text="@js(__('cert::messages.badges_chip')).replace(':count', rows.length)"></span>
                        </span>
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-white/20 text-[11px] font-bold">
                            <i class="bi bi-aspect-ratio"></i>{{ __('cert::messages.badges_size_chip') }}
                        </span>
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto px-4 py-4 space-y-5" style="padding-bottom: calc(1rem + env(safe-area-inset-bottom));">

                    {{-- Design + the print-shop file --}}
                    <section class="bg-white rounded-2xl border border-gray-100 divide-y divide-gray-100 overflow-hidden">
                        <a :href="design ? design + '?back=' + encodeURIComponent(location.pathname) : '#'" x-show="design"
                           class="m-press flex items-center gap-3 p-3.5">
                            <span class="w-10 h-10 rounded-xl bg-accent text-primary grid place-items-center flex-shrink-0"><i class="bi bi-palette-fill"></i></span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-bold text-foreground">{{ __('cert::messages.badges_design') }}</span>
                                <span class="block text-[12px] text-muted-foreground">{{ __('cert::messages.badges_design_hint') }}</span>
                            </span>
                            <i class="bi bi-chevron-right rtl:rotate-180 text-xs text-muted-foreground/60"></i>
                        </a>
                        <div x-show="rows.length" class="p-3.5 flex items-center gap-3">
                            <span class="w-10 h-10 rounded-xl bg-accent text-primary grid place-items-center flex-shrink-0"><i class="bi bi-file-earmark-pdf"></i></span>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-bold text-foreground">{{ __('cert::messages.badges_bundle_title') }}</p>
                                <p class="text-[12px] text-muted-foreground"
                                   x-text="bundle && bundle.state === 'building'
                                        ? @js(__('cert::messages.bundle_building')).replace(':done', bundle.done).replace(':total', bundle.total || rows.length)
                                        : (bundle && bundle.state === 'failed' ? @js(__('cert::messages.bundle_failed'))
                                        : @js(__('cert::messages.badges_bundle_pages')).replace(':count', rows.length).replace(':sheets', Math.ceil(rows.length / 4)) + (bundle && bundle.size ? ' · ' + mb(bundle.size) : ''))"></p>
                                <div x-show="bundle && bundle.state === 'building'" class="h-1.5 rounded-full bg-muted overflow-hidden mt-1.5">
                                    <div class="h-full bg-primary transition-all duration-300" :style="'width:' + (bundle ? Math.max(6, Math.round(bundle.done / Math.max(1, bundle.total || rows.length) * 100)) : 0) + '%'"></div>
                                </div>
                            </div>
                            <template x-if="bundle && bundle.state === 'ready'">
                                <a :href="urls.bundleDownload" download class="m-press h-10 px-3.5 rounded-xl bg-primary text-white text-[12px] font-bold inline-flex items-center gap-1.5 flex-shrink-0"><i class="bi bi-download"></i>{{ __('cert::messages.download') }}</a>
                            </template>
                            <template x-if="! bundle || bundle.state === 'idle' || bundle.state === 'failed'">
                                <button type="button" @click="build()" class="m-press h-10 px-3.5 rounded-xl border border-primary text-primary text-[12px] font-bold inline-flex items-center gap-1.5 flex-shrink-0"><i class="bi bi-file-earmark-arrow-down"></i>{{ __('cert::messages.bundle_make') }}</button>
                            </template>
                            <template x-if="bundle && bundle.state === 'building'">
                                <span class="h-10 px-3 rounded-xl bg-muted/60 text-muted-foreground text-[12px] font-bold inline-flex items-center flex-shrink-0"><i class="bi bi-arrow-repeat animate-spin"></i></span>
                            </template>
                        </div>
                    </section>

                    {{-- Every official, with their badge --}}
                    <section class="space-y-2">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground px-1">{{ __('cert::messages.badges_list') }}</p>
                        <template x-if="loaded && ! rows.length">
                            <div class="rounded-2xl bg-white border border-dashed border-gray-200 p-6 text-center">
                                <i class="bi bi-person-badge text-3xl text-muted-foreground/40"></i>
                                <p class="text-sm text-muted-foreground mt-2">{{ __('cert::messages.badges_empty') }}</p>
                            </div>
                        </template>
                        <template x-for="row in rows" :key="row.token">
                            <div class="bg-white rounded-2xl border border-gray-100 p-3 flex items-center gap-3 m-in">
                                <button type="button" @click="view(row)" :aria-label="@js(__('cert::messages.view'))"
                                        class="w-9 h-12 rounded-md border border-gray-200 bg-muted/40 grid place-items-center flex-shrink-0">
                                    <i class="bi text-sm" :class="row.has_photo ? 'bi-person-badge text-primary' : 'bi-person text-muted-foreground'"></i>
                                </button>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-bold text-foreground break-words" x-text="row.name"></p>
                                    <p class="text-[12px] text-muted-foreground break-words" x-text="row.role + (row.club ? ' · ' + row.club : '')"></p>
                                    <p x-show="! row.has_photo" class="text-[11px] text-amber-600 mt-0.5">{{ __('cert::messages.badges_no_photo') }}</p>
                                </div>
                                <div class="flex items-center gap-1.5 flex-shrink-0">
                                    <button type="button" @click="view(row)" class="m-press w-9 h-9 rounded-xl border border-gray-200 grid place-items-center text-foreground" :aria-label="@js(__('cert::messages.view'))"><i class="bi bi-eye"></i></button>
                                    <a :href="dl(row)" download class="m-press w-9 h-9 rounded-xl border border-gray-200 grid place-items-center text-foreground" :aria-label="@js(__('cert::messages.download'))"><i class="bi bi-download"></i></a>
                                </div>
                            </div>
                        </template>
                    </section>

                    <p class="text-[12px] text-muted-foreground leading-relaxed px-1">{{ __('cert::messages.badges_note') }}</p>
                </div>
            </div>
        </div>
    </template>
</div>
