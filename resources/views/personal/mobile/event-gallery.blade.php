{{-- `$shell` is shared ONLY on the sealed event routes (/e/{uuid}/admin/…), so with
     nothing shared this is the member shell exactly as before. See entry/shell. --}}
@extends($shell ?? 'layouts.personal-mobile')

@section('title', __('events.bout_gallery_title').' — '.$e['title'])

@php $color = $e['color'] ?? '#7c6bf5'; @endphp

@section('personal-content')
<div class="-mx-4 -mt-4 pb-6"
     x-data="eventGallery(@js($divisions), @js($stages), @js(route('me.events.gallery.data', $e['key'])), @js($e['key']))"
     x-init="boot()">

    {{-- ── Hero band ───────────────────────────────────────────────────── --}}
    <header class="m-hero px-5 pt-5 pb-14 text-white relative overflow-hidden"
            style="background: {{ \App\Support\Palette::pageBand($color, isset($shell)) }};">
        <div class="absolute -right-10 -top-10 w-44 h-44 rounded-full bg-white/10"></div>
        <div class="absolute right-6 bottom-8 w-24 h-24 rounded-full bg-white/10"></div>

        <div class="flex items-center justify-between gap-2 relative z-50">
            {{-- Inside the sealed event app, back from a sub-screen means the
                     CONSOLE — the screen it was opened from. On the platform it
                     still means the event page. Same pill, honest label either
                     way (the audit: "'Event' means two different pages"). --}}
                <a href="{{ ($sealed ?? false) ? url('/e/'.$e['key'].'/admin/manage') : route('me.events.show', $e['key']) }}" data-shell-link data-route="me.events"
               class="m-press inline-flex items-center w-10 h-10 justify-center rounded-full bg-white/15 border border-white/25 backdrop-blur text-white text-sm font-semibold no-underline"
           aria-label="{{ ($sealed ?? false) ? __('personal.event_manage_title') : __('personal.event_show_event') }}" title="{{ ($sealed ?? false) ? __('personal.event_manage_title') : __('personal.event_show_event') }}">
                <i class="bi bi-chevron-left"></i>
            </a>

            <div class="flex items-center gap-2">
                @if ($canManage)
                    <a href="{{ route('me.events.manage', $e['key']) }}" data-shell-link data-route="me.events"
                       class="m-press w-10 h-10 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center text-white no-underline"
                       aria-label="{{ __('personal.event_manage_title') }}">
                        <i class="bi bi-sliders text-base"></i>
                    </a>
                @endif
                <x-qr-code
                    :url="route('me.events.gallery', ['event' => $e['key']])"
                    :title="$e['title'].' — '.__('events.bout_gallery_title')"
                    :caption="__('events.bout_gallery_sub')"
                    :filename="'qr-gallery-'.$e['key']"
                    label="" icon="bi-qr-code"
                    button-class="w-10 h-10 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center text-white" />
            </div>
        </div>

        <div class="relative z-10 mt-6">
            <div class="flex items-center gap-1.5 flex-wrap">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur">
                    <i class="bi bi-camera-reels-fill"></i> {{ __('events.bout_gallery_title') }}
                </span>
                @if (!empty($e['sport_label']))
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur">
                        <i class="bi {{ $e['sport_icon'] ?? 'bi-dribbble' }}"></i> {{ $e['sport_label'] }}
                    </span>
                @endif
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur">
                    <span x-text="count"></span> {{ __('events.bout_gallery_sub') }}
                </span>
            </div>

            <h1 class="text-2xl font-black mt-3 leading-tight">{{ $e['title'] }}</h1>
            <p class="text-sm text-white/85 mt-1.5 flex items-center gap-1.5">
                <i class="bi bi-building"></i>{{ $e['club'] }}
            </p>
        </div>
    </header>

    {{-- ── Tabs: All, then every division (a weight class or a group), then
             every stage that was actually fought. One row, because they answer
             the same question — "show me this slice" — and a reader should not
             have to know which kind of slice they want first. ─────────────── --}}
    <div class="px-4 -mt-6 relative z-10" x-show="tabs.length > 1" x-cloak>
        <div class="bg-white rounded-2xl shadow-md border border-gray-100 p-2">
            <div class="flex gap-2 overflow-x-auto scrollbar-hide">
                <template x-for="t in tabs" :key="t.id">
                    <button type="button" @click="only = t.id"
                            class="m-press flex-shrink-0 px-3 py-2 rounded-xl text-xs font-bold transition-colors whitespace-nowrap flex items-center gap-1.5"
                            :class="only === t.id ? 'bg-primary text-white' : 'bg-muted text-muted-foreground'">
                        <i class="bi text-[11px]" :class="t.icon" x-show="t.icon"></i>
                        <span x-text="t.label"></span>
                        <span class="px-1.5 rounded-full text-[10px]"
                              :class="only === t.id ? 'bg-white/25' : 'bg-black/5'" x-text="t.count"></span>
                    </button>
                </template>
            </div>
        </div>
    </div>

    {{-- ── The shelves ─────────────────────────────────────────────────── --}}
    {{-- Server-rendered, not built in JS: each tile is a broadcast card with
         athlete photographs and club crests, and the values on it are exactly
         the ones the server decided this viewer may see. Alpine only decides
         which shelf is VISIBLE; it never composes markup from data. --}}
    <div id="gallery-shelves"
         class="px-4 pt-4 relative z-10 space-y-5 mobile-stagger {{ count($divisions) > 1 ? '' : 'pt-8' }}">

        @forelse ($divisions as $d)
            <section x-show="showDivision(@js($d['id'] ?? 'none'), @js(collect($d['bouts'])->pluck('stage')->filter()->unique()->values()))" x-cloak>
                {{-- The division band: what people actually look for. --}}
                <div class="flex items-baseline gap-2 mb-2.5 px-1">
                    <h2 class="text-base font-black text-foreground">{{ $d['title'] }}</h2>
                    @if (!empty($d['subtitle']))
                        <span class="text-[11px] text-muted-foreground">{{ $d['subtitle'] }}</span>
                    @endif
                    <span class="flex-1"></span>
                    <span class="text-[11px] font-bold text-muted-foreground">{{ $d['count'] }}</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @php
    /* Deleting footage belongs to whoever may MANAGE the event — the same
       answer the controller re-checks (EventAccess::canManage), so anybody
       else must not be shown the control at all (Navigation Integrity: no
       dead ends). Computed once per shelf, not per card. */
    $mayDelete = (bool) ($canManage ?? false);
@endphp
                        @foreach ($d['bouts'] as $b)
                        <div x-show="showBout(@js($b['stage']))" x-cloak>
                        <x-bout-vs-card
                            :arena="$b['arena']"
                            :href="$b['url']"
                            :poster="$b['poster']"
                            :preview="$b['preview']"
                            :duration="$b['duration']"
                            :angles="$b['angles']"
                            :shell-link="true"
                            :delete-url="$mayDelete ? route('me.events.bout.video.destroy', ['event' => $e['key'], 'matchNo' => $b['match_no']]) : null" />
                        </div>
                    @endforeach
                </div>
            </section>
        @empty
            <div class="m-card bg-white rounded-2xl shadow-md border border-gray-100 p-8 text-center">
                <span class="w-16 h-16 mx-auto rounded-2xl bg-muted grid place-items-center text-muted-foreground">
                    <i class="bi bi-camera-video-off text-2xl"></i>
                </span>
                <h2 class="text-base font-black text-foreground mt-3">{{ __('events.bout_gallery_empty') }}</h2>
                <p class="text-sm text-muted-foreground mt-1">{{ __('events.bout_gallery_empty_sub') }}</p>
            </div>
        @endforelse
    </div>
</div>

{{-- Inline, not @push: the mobile shell re-runs inline scripts on every
     AJAX navigation, and a pushed stack would not come with the page. --}}
<script>
window.eventGallery = function (divisions, stages, dataUrl, eventUuid) {
    return {
        divisions: divisions || [],
        stages: stages || [],
        only: 'all',
        dataUrl: dataUrl,

        /* One flat list: All, the divisions, then the stages. */
        get tabs() {
            const total = this.divisions.reduce((n, d) => n + d.count, 0);
            return [{ id: 'all', label: @js(__('shared.all')), count: total, icon: '' }]
                .concat(this.divisions.map(d => ({
                    id: 'div:' + (d.id ?? 'none'), label: d.title, count: d.count, icon: 'bi-rulers',
                })))
                .concat(this.stages.map(s => ({
                    id: 'stage:' + s.key, label: s.label, count: s.count, icon: 'bi-trophy',
                })));
        },

        /* A division shows when it is the chosen one, when everything is
           chosen, or when a chosen STAGE has a bout inside it. */
        showDivision(id, stagesHere) {
            if (this.only === 'all') return true;
            if (this.only === 'div:' + id) return true;
            if (this.only.startsWith('stage:')) {
                return (stagesHere || []).includes(this.only.slice(6));
            }
            return false;
        },

        /* A bout hides only when a stage is chosen and this is not it. */
        showBout(stage) {
            if (!this.only.startsWith('stage:')) return true;
            return stage === this.only.slice(6);
        },

        get count() { return this.divisions.reduce((n, d) => n + d.count, 0); },

        boot() {
            /*
             * A gallery grows while people are looking at it — a camera on mat 2
             * finishes uploading and a bout that was not there appears. The push
             * is a refresh signal rather than the clip itself, because what each
             * viewer may see differs and nobody should be handed a tile they are
             * not allowed to open.
             *
             * Stored on window and removed first: the shell re-runs this script
             * on every navigation, and the listeners would otherwise stack.
             */
            if (window.__galleryRealtime) {
                window.removeEventListener('realtime:events', window.__galleryRealtime);
            }
            window.__galleryRealtime = (e) => {
                const d = e.detail || {};
                if (d.event !== eventUuid) return;
                if (d.action !== 'gallery' && d.action !== 'refresh') return;
                this.reload();
            };
            window.addEventListener('realtime:events', window.__galleryRealtime);
        },

        /*
         * Re-draw the shelves.
         *
         * The tiles are server-rendered — they carry athlete photographs and
         * club crests that only the server may decide to show — so this fetches
         * the PAGE and swaps our own container, rather than composing markup
         * from JSON in the browser. Same move the shell navigator makes.
         */
        async reload() {
            try {
                const res = await fetch(window.location.href, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                if (!res.ok) return;
                if (!(res.headers.get('Content-Type') || '').includes('text/html')) return;

                const doc = new DOMParser().parseFromString(await res.text(), 'text/html');
                const fresh = doc.getElementById('gallery-shelves');
                const here = document.getElementById('gallery-shelves');
                if (fresh && here) { here.innerHTML = fresh.innerHTML; }

                // Keep the filter chips and the count in step.
                const data = await fetch(this.dataUrl, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                }).then((r) => r.ok ? r.json() : null).catch(() => null);
                if (data) { this.divisions = data.divisions || []; this.stages = data.stages || []; }
            } catch (e) { /* best effort; the page stays as it was */ }
        },

        dur(s) {
            const w = Math.max(0, Math.floor(s));
            return String(Math.floor(w / 60)).padStart(2, '0') + ':' + String(w % 60).padStart(2, '0');
        },
    };
};
</script>
@endsection
