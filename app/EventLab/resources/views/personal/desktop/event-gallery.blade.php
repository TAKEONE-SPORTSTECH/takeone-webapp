@extends('layouts.app')

@section('title', __('events.bout_gallery_title').' — '.$e['title'])

@php $color = $e['color'] ?? '#7c6bf5'; @endphp

@section('content')
<div class="px-4 sm:px-6 lg:px-8 py-6"
     x-data="eventGalleryDesktop(@js($divisions), @js($stages), @js(route('testcode.me.events.gallery.data', $e['key'])), @js($e['key']))"
     x-init="boot()">

    {{-- ── Hero band, full-bleed ───────────────────────────────────────── --}}
    <header class="m-hero -mx-4 sm:-mx-6 lg:-mx-8 -mt-6 px-8 pt-7 pb-16 text-white relative overflow-hidden"
            style="background: linear-gradient(150deg, {{ $color }}, {{ $color }}b0);">
        <div class="absolute -right-10 -top-10 w-44 h-44 rounded-full bg-white/10"></div>
        <div class="absolute right-6 bottom-8 w-24 h-24 rounded-full bg-white/10"></div>

        <div class="flex items-center justify-between gap-3 relative z-50">
            <a href="{{ route('testcode.me.events.show', $e['key']) }}"
               class="inline-flex items-center w-10 h-10 justify-center rounded-full bg-white/15 border border-white/25 backdrop-blur text-white text-sm font-semibold no-underline hover:bg-white/25 transition-colors"
           aria-label="{{ __('personal.event_show_event') }}" title="{{ __('personal.event_show_event') }}">
                <i class="bi bi-chevron-left"></i>
            </a>

            <div class="flex items-center gap-2">
                @if ($canManage)
                    <a href="{{ route('testcode.me.events.manage', $e['key']) }}"
                       class="w-10 h-10 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center text-white no-underline hover:bg-white/25 transition-colors"
                       aria-label="{{ __('personal.event_manage_title') }}">
                        <i class="bi bi-sliders"></i>
                    </a>
                @endif
                <x-qr-code
                    :url="route('testcode.me.events.gallery', ['event' => $e['key']])"
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
            <h1 class="text-3xl font-black mt-3 leading-tight">{{ $e['title'] }}</h1>
            <p class="text-sm text-white/85 mt-1.5 flex items-center gap-1.5">
                <i class="bi bi-building"></i>{{ $e['club'] }}
            </p>
        </div>
    </header>

    <div class="-mt-10 relative z-10">

        {{-- Tabs: All, then every division (a weight class or a group), then
             every stage that was actually fought. --}}
        <div class="bg-white rounded-2xl shadow-md border border-gray-100 p-2 mb-6" x-show="tabs.length > 1" x-cloak>
            <div class="flex gap-2 overflow-x-auto">
                <template x-for="t in tabs" :key="t.id">
                    <button type="button" @click="only = t.id"
                            class="flex-shrink-0 px-3.5 py-2 rounded-xl text-xs font-bold transition-colors whitespace-nowrap flex items-center gap-1.5"
                            :class="only === t.id ? 'bg-primary text-white' : 'bg-muted text-muted-foreground hover:bg-muted/70'">
                        <i class="bi text-[11px]" :class="t.icon" x-show="t.icon"></i>
                        <span x-text="t.label"></span>
                        <span class="px-1.5 rounded-full text-[10px]"
                              :class="only === t.id ? 'bg-white/25' : 'bg-black/5'" x-text="t.count"></span>
                    </button>
                </template>
            </div>
        </div>

        {{-- Server-rendered: each tile is a broadcast card carrying athlete
             photographs and club crests, so the server composes it. Alpine only
             decides which shelf is visible. --}}
        <div id="gallery-shelves" class="space-y-10">
            @forelse ($divisions as $d)
                <section x-show="showDivision(@js($d['id'] ?? 'none'), @js(collect($d['bouts'])->pluck('stage')->filter()->unique()->values()))" x-cloak>
                    <div class="flex items-baseline gap-3 mb-3">
                        <h2 class="text-xl font-bold text-gray-900">{{ $d['title'] }}</h2>
                        @if (!empty($d['subtitle']))
                            <span class="text-sm text-muted-foreground">{{ $d['subtitle'] }}</span>
                        @endif
                        <span class="ml-2 py-0.5 px-2.5 rounded-full text-xs font-medium bg-purple-100 text-purple-600">{{ $d['count'] }}</span>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                        @php
    /* Deleting footage is super-admin only and the controller enforces it,
       so anybody else must not be shown the control at all (Navigation
       Integrity: no dead ends). Computed once per shelf, not per card. */
    $mayDelete = (bool) auth()->user()?->hasRole('super-admin');
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
                            :delete-url="$mayDelete ? route('testcode.me.events.bout.video.destroy', ['event' => $e['key'], 'matchNo' => $b['match_no']]) : null" />
                            </div>
                        @endforeach
                    </div>
                </section>
            @empty
                <div class="bg-white rounded-2xl shadow-md border border-gray-100 p-12 text-center max-w-xl mx-auto">
                    <span class="w-16 h-16 mx-auto rounded-2xl bg-muted grid place-items-center text-muted-foreground">
                        <i class="bi bi-camera-video-off text-2xl"></i>
                    </span>
                    <h2 class="text-lg font-bold text-gray-900 mt-3">{{ __('events.bout_gallery_empty') }}</h2>
                    <p class="text-sm text-muted-foreground mt-1">{{ __('events.bout_gallery_empty_sub') }}</p>
                </div>
            @endforelse
        </div>
    </div>
</div>

@push('scripts')
<script>
window.eventGalleryDesktop = function (divisions, stages, dataUrl, eventUuid) {
    return {
        divisions: divisions || [],
        stages: stages || [],
        only: 'all',

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

        showDivision(id, stagesHere) {
            if (this.only === 'all') return true;
            if (this.only === 'div:' + id) return true;
            if (this.only.startsWith('stage:')) return (stagesHere || []).includes(this.only.slice(6));
            return false;
        },

        showBout(stage) {
            if (!this.only.startsWith('stage:')) return true;
            return stage === this.only.slice(6);
        },

        get count() { return this.divisions.reduce((n, d) => n + d.count, 0); },

        boot() {
            // A refresh signal, not the clip: what each viewer may open differs,
            // so every console re-fetches what it is allowed to see.
            if (window.__galleryRealtimeD) {
                window.removeEventListener('realtime:events', window.__galleryRealtimeD);
            }
            window.__galleryRealtimeD = (e) => {
                const d = e.detail || {};
                if (d.event !== eventUuid) return;
                if (d.action !== 'gallery' && d.action !== 'refresh') return;
                this.reload();
            };
            window.addEventListener('realtime:events', window.__galleryRealtimeD);
        },

        {{-- Swap our own server-rendered container; see the mobile twin. --}}
        async reload() {
            try {
                const res = await fetch(window.location.href, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin',
                });
                if (!res.ok) return;
                if (!(res.headers.get('Content-Type') || '').includes('text/html')) return;

                const doc = new DOMParser().parseFromString(await res.text(), 'text/html');
                const fresh = doc.getElementById('gallery-shelves');
                const here = document.getElementById('gallery-shelves');
                if (fresh && here) { here.innerHTML = fresh.innerHTML; }

                const data = await fetch(dataUrl, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                }).then((r) => r.ok ? r.json() : null).catch(() => null);
                if (data) { this.divisions = data.divisions || []; this.stages = data.stages || []; }
            } catch (e) { /* best effort */ }
        },

        dur(s) {
            const w = Math.max(0, Math.floor(s));
            return String(Math.floor(w / 60)).padStart(2, '0') + ':' + String(w % 60).padStart(2, '0');
        },
    };
};
</script>
@endpush
@endsection
