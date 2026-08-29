@extends('layouts.app')

@section('title', __('personal.videos_title'))

@section('content')
<div class="px-4 sm:px-6 lg:px-8 py-6">

    @includeIf('partials.personal-desktop-subnav')

    {{-- The standard band (Design Rule #6). A platform hub, so it wears the
         shared m-hero mesh rather than a subject's colour, and carries no back
         pill — this is a top-level destination, not a drill-down. --}}
    <div class="m-hero -mx-4 sm:-mx-6 lg:-mx-8 -mt-6 overflow-hidden shadow-sm mb-6 text-white relative">
        <div class="absolute -right-10 -top-10 w-44 h-44 rounded-full bg-white/10"></div>
        <div class="absolute right-6 bottom-8 w-24 h-24 rounded-full bg-white/10"></div>

        <div class="relative px-4 sm:px-6 lg:px-8 py-6 sm:py-8">
            <div class="flex items-center gap-1.5 flex-wrap">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur">
                    <i class="bi bi-camera-reels-fill"></i> {{ __('personal.videos_title') }}
                </span>
                @foreach ($shelves as $shelf)
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur">
                        <i class="bi {{ $shelf['icon'] }}"></i> {{ $shelf['count'] }} {{ $shelf['title'] }}
                    </span>
                @endforeach
            </div>
            <h1 class="text-2xl font-black mt-3 leading-tight">{{ __('personal.videos_title') }}</h1>
            <p class="text-sm text-white/85 mt-1.5 flex items-center gap-1.5">
                <i class="bi bi-collection-play"></i>{{ __('personal.videos_sub') }}
            </p>
        </div>
    </div>

    @forelse ($shelves as $shelf)
        <section class="mb-10">
            <div class="flex items-center gap-3 mb-4">
                <span class="w-10 h-10 rounded-xl bg-primary/10 grid place-items-center text-primary flex-shrink-0">
                    <i class="bi {{ $shelf['icon'] }} text-lg"></i>
                </span>
                <div>
                    <h2 class="text-xl font-bold text-gray-900 leading-tight">{{ $shelf['title'] }}</h2>
                    <p class="text-xs text-muted-foreground">{{ $shelf['subtitle'] }}</p>
                </div>
                <span class="ml-1 py-0.5 px-2.5 rounded-full text-xs font-medium bg-purple-100 text-purple-600">{{ $shelf['count'] }}</span>
            </div>

            <div class="grid {{ $shelf['key'] === 'bouts' ? 'grid-cols-1 md:grid-cols-2 xl:grid-cols-3' : 'grid-cols-2 md:grid-cols-3 xl:grid-cols-4' }} gap-5">
                @foreach ($shelf['items'] as $item)
                    @if ($item['kind'] === 'bout')
                        <x-bout-vs-card
                            :arena="$item['arena']"
                            :href="$item['url']"
                            :poster="$item['poster']"
                            :preview="$item['preview']"
                            :duration="$item['duration']"
                            :angles="$item['angles']" />
                    @else
                        <button type="button"
                            onclick="window.dispatchEvent(new CustomEvent('open-media-lightbox', { detail: {{ \Illuminate\Support\Js::from(['src' => $item['src'], 'label' => $item['title'], 'kind' => 'video']) }} }))"
                            class="group block text-start w-full rounded-2xl overflow-hidden bg-white border border-gray-100 shadow-sm hover:shadow-lg transition-shadow">

                            <span class="block relative aspect-video bg-gray-900 overflow-hidden">
                                @if ($item['poster'])
                                    <img src="{{ $item['poster'] }}" alt="" loading="lazy"
                                         class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-[1.04]">
                                @else
                                    <span class="absolute inset-0 grid place-items-center text-white/30">
                                        <i class="bi {{ $shelf['icon'] }} text-4xl"></i>
                                    </span>
                                @endif
                                <span class="absolute inset-0 bg-black/0 group-hover:bg-black/25 transition-colors grid place-items-center">
                                    <span class="w-12 h-12 rounded-full bg-white/90 grid place-items-center text-gray-900 opacity-0 group-hover:opacity-100 scale-90 group-hover:scale-100 transition-all">
                                        <i class="bi bi-play-fill text-2xl ms-0.5"></i>
                                    </span>
                                </span>
                                <span class="absolute inset-x-0 bottom-0 h-14 bg-gradient-to-t from-black/80 to-transparent pointer-events-none"></span>
                                @if ($item['duration'] > 0)
                                    <span class="absolute bottom-2 start-2.5 text-[11px] font-black text-white tabular-nums">
                                        {{ sprintf('%02d:%02d', intdiv($item['duration'], 60), $item['duration'] % 60) }}
                                    </span>
                                @endif
                            </span>

                            <span class="block p-3">
                                <span class="block text-[13px] font-bold text-gray-900 truncate">{{ $item['title'] }}</span>
                                <span class="block text-[11px] text-muted-foreground truncate mt-0.5">{{ $item['subtitle'] }}</span>
                                @if ($item['meta'])
                                    <span class="inline-block mt-2 px-2 py-0.5 rounded-md bg-muted text-[10px] font-bold text-muted-foreground truncate max-w-full">{{ $item['meta'] }}</span>
                                @endif
                            </span>
                        </button>
                    @endif
                @endforeach
            </div>
        </section>
    @empty
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-12 text-center max-w-xl mx-auto">
            <span class="w-16 h-16 mx-auto rounded-2xl bg-muted grid place-items-center text-muted-foreground">
                <i class="bi bi-camera-video-off text-2xl"></i>
            </span>
            <h2 class="text-lg font-bold text-gray-900 mt-3">{{ __('personal.videos_empty') }}</h2>
            <p class="text-sm text-muted-foreground mt-1">{{ __('personal.videos_empty_sub') }}</p>
        </div>
    @endforelse
</div>

<x-media-lightbox />
@endsection
