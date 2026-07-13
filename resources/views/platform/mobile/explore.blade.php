{{-- Lives inside the personal mobile shell so the header (avatar → drawer),
     notifications, chat and bottom tab bar are identical to /me. The shell's
     <main> already supplies `mobile-stagger px-4 py-4 pb-24`. --}}
@extends('layouts.personal-mobile')

@section('title', __('explore.explore'))

@section('personal-content')
<div x-data="exploreApp()">

    {{-- ===== Hero ===== --}}
    <header class="m-hero -mx-4 -mt-4 px-5 pt-7 pb-6 text-white relative overflow-hidden">
        <div class="absolute -end-8 -top-8 w-36 h-36 rounded-full bg-white/10"></div>
        <div class="flex items-start justify-between gap-3 relative z-10">
            <div class="min-w-0">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-white/70">{{ __('explore.explore') }}</p>
                <h1 class="text-2xl font-black mt-0.5 leading-tight">{!! __('explore.hero_title') !!}</h1>
                <p class="text-sm text-white/85 mt-1">{{ __('explore.hero_sub') }}</p>
            </div>
            <div class="w-12 h-12 shrink-0 rounded-2xl bg-white/15 border border-white/25 backdrop-blur grid place-items-center">
                <i class="bi bi-compass text-xl m-float"></i>
            </div>
        </div>
        <p class="mt-4 relative z-10">
            <span id="currentLocation" class="inline-flex items-center gap-1.5 text-xs font-medium bg-white/12 border border-white/20 backdrop-blur rounded-2xl px-3 py-1.5">
                <i class="bi bi-geo-alt-fill"></i>{{ __('explore.detecting_location') }}
            </span>
        </p>
    </header>

    {{-- ===== Search ===== --}}
    <div class="sticky top-14 z-20 -mx-4 px-4 py-3 bg-background/85 backdrop-blur-md">
        <div class="m-card flex items-center gap-2 p-1.5 rounded-2xl">
            <span class="pl-3 text-muted-foreground"><i class="bi bi-search"></i></span>
            <input type="text" id="searchInput"
                   class="flex-1 min-w-0 px-2 py-2.5 bg-transparent border-0 focus:outline-none focus:ring-0 text-sm"
                   placeholder="{{ __('explore.search_placeholder') }}">
            <button id="nearMeBtn" type="button" @click="openMapModal()"
                    class="m-press shrink-0 inline-flex items-center gap-1.5 bg-primary text-white text-sm font-medium px-3.5 py-2.5 rounded-xl">
                <i class="bi bi-geo-alt-fill"></i><span class="hidden xs:inline">{{ __('explore.near_me') }}</span>
            </button>
        </div>
    </div>

    {{-- ===== Loading ===== --}}
    <div id="loadingSpinner" class="text-center py-10">
        <div class="spinner-border text-primary" role="status"><span class="sr-only">{{ __('shared.loading') }}</span></div>
        <p class="mt-3 text-sm text-muted-foreground">{{ __('explore.finding') }}</p>
    </div>

    {{-- ===== Results (single column rounded cards) =====
         JS toggles this to `display: flex`, so the grid needs w-full — as a bare
         flex item it would size to max-content and narrow as results shrink. --}}
    <div id="clubsGrid" class="justify-center" style="display: none;">
        <div class="w-full grid grid-cols-1 gap-3" id="clubsContainer">
            <!-- Club cards inserted by exploreApp() -->
        </div>
    </div>

    {{-- ===== No results ===== --}}
    {{-- JS toggles this to `display: flex`, so the child needs w-full to stretch. --}}
    <div id="noResultsContainer" class="justify-center" style="display: none;">
        <div id="noResults" class="w-full flex flex-col items-center justify-center text-center min-h-[50vh] px-4">
            <i class="bi bi-inbox text-5xl text-gray-300 m-float"></i>
            <h4 class="mt-3 text-muted-foreground font-semibold">{{ __('explore.no_results') }}</h4>
            <p class="text-sm text-muted-foreground">{{ __('explore.no_results_hint') }}</p>
        </div>
    </div>

    {{-- ===== Map modal — teleported bottom-sheet (same IDs/handlers as desktop).
         Teleported to <body> so `fixed` escapes the mobile shell's transformed
         .mobile-stagger ancestor, which would otherwise clip the sheet. ===== --}}
    <template x-teleport="body">
        <div x-show="mapModalOpen" class="fixed inset-0 z-[60]" style="display: none;" @keydown.escape.window="closeMapModal()">
            <div x-show="mapModalOpen"
                 x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                 x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                 class="absolute inset-0 bg-black/50 backdrop-blur-[2px]" @click="closeMapModal()"></div>

            <div x-show="mapModalOpen"
                 x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
                 x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
                 class="absolute inset-x-0 bottom-0 max-h-[92vh] flex flex-col bg-white rounded-t-3xl shadow-2xl overflow-hidden"
                 @click.stop>

                {{-- Grab handle + title --}}
                <div class="flex-shrink-0">
                    <div class="pt-2.5 pb-1 flex justify-center">
                        <span class="w-10 h-1.5 rounded-full bg-gray-300"></span>
                    </div>
                    <div class="flex items-center justify-between px-4 pb-3">
                        <h2 class="text-base font-bold text-foreground flex items-center gap-2">
                            <span class="w-9 h-9 rounded-xl bg-accent text-primary grid place-items-center"><i class="bi bi-geo-alt-fill"></i></span>
                            {{ __('explore.set_location') }}
                        </h2>
                        <button type="button" @click="closeMapModal()"
                                class="m-press w-9 h-9 rounded-xl grid place-items-center text-muted-foreground hover:bg-muted transition-colors"
                                aria-label="{{ __('shared.transactions_modal_close') }}">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>

                {{-- Scrollable body --}}
                <div class="flex-1 overflow-y-auto">
                    <div id="map" class="w-full" style="height: min(440px, 52vh);"></div>
                </div>

                {{-- Sticky footer (safe-area aware) --}}
                <div class="flex-shrink-0 border-t border-border bg-white px-4 pt-3"
                     style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
                    <p class="flex items-center gap-1.5 text-xs text-muted-foreground mb-2.5">
                        <i class="bi bi-geo-alt-fill text-primary"></i><span id="modalLocationCoordinates">{{ __('explore.drag_marker') }}</span>
                    </p>
                    <button type="button" id="applyLocationBtn" @click="applyLocation()"
                            class="m-press w-full bg-primary text-white font-semibold py-3 rounded-2xl flex items-center justify-center gap-2 active:scale-[0.98] transition-transform">
                        <i class="bi bi-check-circle"></i>{{ __('explore.apply_location') }}
                    </button>
                </div>
            </div>
        </div>
    </template>

    {{-- Join Club Modal — mobile-native bottom-sheet (same joinModal state) --}}
    @include('platform.partials.join-club-modal-mobile')

    <x-toast-notification />
</div>

{{-- Shared runtime: identical exploreApp() logic + Leaflet assets (same as desktop) --}}
@include('platform.partials.explore-runtime')
@endsection
