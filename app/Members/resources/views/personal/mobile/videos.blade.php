@extends('layouts.personal-mobile')

@section('title', __('personal.videos_title'))

@section('personal-content')
<div class="-mx-4 -mt-4 pb-6" x-data="myVideos(@js($shelves))">

    {{-- ── Hero band ───────────────────────────────────────────────────── --}}
    <header class="m-hero px-5 pt-7 pb-6 text-white relative overflow-hidden">
        <div class="absolute -right-10 -top-10 w-44 h-44 rounded-full bg-white/10"></div>
        <div class="absolute right-6 bottom-8 w-24 h-24 rounded-full bg-white/10"></div>

        <div class="flex items-center justify-between relative z-10">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-wider text-white/70">{{ __('personal.videos_sub') }}</p>
                <h1 class="text-2xl font-black mt-0.5">{{ __('personal.videos_title') }}</h1>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-white/15 border border-white/25 backdrop-blur grid place-items-center">
                <i class="bi bi-camera-reels text-xl m-float"></i>
            </div>
        </div>

        @if ($total > 0)
            <div class="flex gap-2 mt-5 relative z-10">
                @foreach ($shelves as $shelf)
                    <div class="flex-1 rounded-2xl bg-white/12 border border-white/20 backdrop-blur px-3 py-2.5">
                        <p class="text-lg font-black leading-none">{{ $shelf['count'] }}</p>
                        <p class="text-[10px] text-white/75 mt-1 uppercase tracking-wide truncate">{{ $shelf['title'] }}</p>
                    </div>
                @endforeach
            </div>
        @endif
    </header>

    <div class="px-4 pt-5 relative z-10 space-y-6 mobile-stagger">

        @forelse ($shelves as $shelf)
            <section>
                <div class="flex items-center gap-2 mb-2.5 px-1">
                    <span class="w-8 h-8 rounded-xl bg-primary/10 grid place-items-center text-primary flex-shrink-0">
                        <i class="bi {{ $shelf['icon'] }}"></i>
                    </span>
                    <div class="min-w-0">
                        <h2 class="text-base font-black text-foreground leading-tight">{{ $shelf['title'] }}</h2>
                        <p class="text-[11px] text-muted-foreground truncate">{{ $shelf['subtitle'] }}</p>
                    </div>
                    <span class="flex-1"></span>
                    <span class="text-[11px] font-bold text-muted-foreground">{{ $shelf['count'] }}</span>
                </div>

                {{-- A bout gets the broadcast card; a clip or a duel is a
                     plain file and gets the plain tile. --}}
                <div class="grid {{ $shelf['key'] === 'bouts' ? 'grid-cols-1 sm:grid-cols-2' : 'grid-cols-2' }} gap-3">
                    @foreach ($shelf['items'] as $item)
                        @if ($item['kind'] === 'bout')
                            <x-bout-vs-card
                                :arena="$item['arena']"
                                :href="$item['url']"
                                :poster="$item['poster']"
                                :preview="$item['preview']"
                                :duration="$item['duration']"
                                :angles="$item['angles']"
                                :shell-link="true" />
                        @else
                            <button type="button"
                                @click="window.dispatchEvent(new CustomEvent('open-media-lightbox', { detail: @js(['src' => $item['src'], 'label' => $item['title'], 'kind' => 'video']) }))"
                                class="m-card m-press block text-start rounded-2xl overflow-hidden bg-white border border-gray-100 shadow-sm w-full">

                                <span class="block relative aspect-video bg-gray-900 overflow-hidden">
                                    @if ($item['poster'])
                                        <img src="{{ $item['poster'] }}" alt="" loading="lazy" class="w-full h-full object-cover">
                                    @else
                                        <span class="absolute inset-0 grid place-items-center text-white/30">
                                            <i class="bi {{ $shelf['icon'] }} text-3xl"></i>
                                        </span>
                                    @endif
                                    <span class="absolute inset-x-0 bottom-0 h-12 bg-gradient-to-t from-black/80 to-transparent"></span>
                                    @if ($item['duration'] > 0)
                                        <span class="absolute bottom-1.5 start-2 text-[10px] font-black text-white tabular-nums">
                                            {{ sprintf('%02d:%02d', intdiv($item['duration'], 60), $item['duration'] % 60) }}
                                        </span>
                                    @endif
                                </span>

                                <span class="block p-2.5">
                                    <span class="block text-[12px] font-bold text-foreground truncate">{{ $item['title'] }}</span>
                                    <span class="block text-[11px] text-muted-foreground truncate mt-0.5">{{ $item['subtitle'] }}</span>
                                    @if ($item['meta'])
                                        <span class="inline-block mt-1.5 px-1.5 py-0.5 rounded-md bg-muted text-[10px] font-bold text-muted-foreground truncate max-w-full">{{ $item['meta'] }}</span>
                                    @endif
                                </span>
                            </button>
                        @endif
                    @endforeach
                </div>
            </section>
        @empty
            <div class="m-card bg-white rounded-2xl shadow-md border border-gray-100 p-8 text-center">
                <span class="w-16 h-16 mx-auto rounded-2xl bg-muted grid place-items-center text-muted-foreground">
                    <i class="bi bi-camera-video-off text-2xl"></i>
                </span>
                <h2 class="text-base font-black text-foreground mt-3">{{ __('personal.videos_empty') }}</h2>
                <p class="text-sm text-muted-foreground mt-1">{{ __('personal.videos_empty_sub') }}</p>
            </div>
        @endforelse
    </div>
</div>

<x-media-lightbox />

<script>
window.myVideos = function (shelves) {
    return { shelves: shelves || [] };
};
</script>
@endsection
