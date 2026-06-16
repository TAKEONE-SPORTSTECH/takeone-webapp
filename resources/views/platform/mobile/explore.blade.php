@extends('layouts.app')

@section('title', 'Explore')

{{-- Use the mobile chrome (own top bar below) instead of the desktop navbar so
     the top bar stays consistent when arriving from the /me mobile shell. --}}
@section('hide-navbar', true)

@section('content')
{{-- Shared mobile top bar — mirrors partials/mobile-header so there is no
     visual "switch" to the desktop bar when coming from the personal shell. --}}
<header class="sticky top-0 z-40 bg-white border-b border-border">
    <div class="flex items-center gap-2 px-3 h-14">
        <a href="{{ route('me.home') }}" class="flex items-center justify-center w-10 h-10 rounded-xl flex-shrink-0" aria-label="Back">
            <i class="bi bi-arrow-left text-xl text-foreground"></i>
        </a>
        <div class="flex-1 min-w-0">
            <p class="text-[10px] text-muted-foreground font-medium leading-tight">Discover</p>
            <p class="text-base font-bold text-primary leading-tight truncate">Explore</p>
        </div>
        <button type="button" onclick="window.dispatchEvent(new CustomEvent('mobile-chat:toggle'))" class="relative w-9 h-9 rounded-xl bg-muted flex items-center justify-center text-muted-foreground flex-shrink-0 chat-link" aria-label="Chat">
            <i class="bi bi-chat-dots"></i>
        </button>
    </div>
</header>
@include('partials.mobile-chat')

<div class="px-4 py-4 pb-24 mobile-stagger" x-data="exploreApp()">

    {{-- ===== Hero ===== --}}
    <div class="m-hero rounded-2xl text-white p-5 shadow-lg">
        <div class="relative z-10">
            <h1 class="text-2xl font-extrabold leading-tight">Find your<br>perfect fit</h1>
            <p class="text-sm text-white/85 mt-1">Clubs, trainers, clinics &amp; more near you</p>
            <p class="mt-3">
                <span id="currentLocation" class="inline-flex items-center gap-1.5 text-xs font-medium bg-white/15 backdrop-blur rounded-full px-3 py-1.5">
                    <i class="bi bi-geo-alt-fill"></i>Detecting location...
                </span>
            </p>
        </div>
    </div>

    {{-- ===== Search ===== --}}
    <div class="sticky top-14 z-20 -mx-4 px-4 py-3 bg-background/85 backdrop-blur-md">
        <div class="m-card flex items-center gap-2 p-1.5 rounded-2xl">
            <span class="pl-3 text-muted-foreground"><i class="bi bi-search"></i></span>
            <input type="text" id="searchInput"
                   class="flex-1 min-w-0 px-2 py-2.5 bg-transparent border-0 focus:outline-none focus:ring-0 text-sm"
                   placeholder="Search clubs, trainers...">
            <button id="nearMeBtn" type="button" @click="openMapModal()"
                    class="m-press shrink-0 inline-flex items-center gap-1.5 bg-primary text-white text-sm font-medium px-3.5 py-2.5 rounded-xl">
                <i class="bi bi-geo-alt-fill"></i><span class="hidden xs:inline">Near Me</span>
            </button>
        </div>
    </div>

    {{-- ===== Category chips (horizontal scroll) ===== --}}
    <div class="-mx-4 px-4 mb-4 overflow-x-auto" style="scrollbar-width:none;">
        <div class="flex gap-2 w-max">
            <button class="btn btn-primary category-btn active shrink-0 whitespace-nowrap" data-category="all"><i class="bi bi-search mr-1.5"></i>All</button>
            <button class="btn btn-outline-primary category-btn shrink-0 whitespace-nowrap" data-category="sports-clubs"><i class="bi bi-trophy mr-1.5"></i>Clubs</button>
            <button class="btn btn-outline-primary category-btn shrink-0 whitespace-nowrap" data-category="personal-trainers"><i class="bi bi-person mr-1.5"></i>Trainers</button>
            <button class="btn btn-outline-primary category-btn shrink-0 whitespace-nowrap" data-category="events"><i class="bi bi-calendar-event mr-1.5"></i>Events</button>
            <button class="btn btn-outline-primary category-btn shrink-0 whitespace-nowrap" data-category="nutrition-clinic"><i class="bi bi-apple mr-1.5"></i>Nutrition</button>
            <button class="btn btn-outline-primary category-btn shrink-0 whitespace-nowrap" data-category="physiotherapy-clinics"><i class="bi bi-activity mr-1.5"></i>Physiotherapy</button>
            <button class="btn btn-outline-primary category-btn shrink-0 whitespace-nowrap" data-category="sports-shops"><i class="bi bi-bag mr-1.5"></i>Shops</button>
            <button class="btn btn-outline-primary category-btn shrink-0 whitespace-nowrap" data-category="venues"><i class="bi bi-building-fill mr-1.5"></i>Venues</button>
            <button class="btn btn-outline-primary category-btn shrink-0 whitespace-nowrap" data-category="supplements"><i class="bi bi-box mr-1.5"></i>Supplements</button>
            <button class="btn btn-outline-primary category-btn shrink-0 whitespace-nowrap" data-category="food-plans"><i class="bi bi-egg-fried mr-1.5"></i>Food Plans</button>
        </div>
    </div>

    {{-- ===== Loading ===== --}}
    <div id="loadingSpinner" class="text-center py-10">
        <div class="spinner-border text-primary" role="status"><span class="sr-only">Loading...</span></div>
        <p class="mt-3 text-sm text-muted-foreground">Finding what's near you...</p>
    </div>

    {{-- ===== Results (single column) ===== --}}
    <div id="clubsGrid" style="display: none;">
        <div class="grid grid-cols-1 gap-4" id="clubsContainer">
            <!-- Club cards inserted by exploreApp() -->
        </div>
    </div>

    {{-- ===== No results ===== --}}
    <div id="noResultsContainer" style="display: none;">
        <div id="noResults" class="flex flex-col items-center justify-center text-center min-h-[50vh]">
            <i class="bi bi-inbox text-5xl text-gray-300 m-float"></i>
            <h4 class="mt-3 text-muted-foreground font-semibold">No Results Found</h4>
            <p class="text-sm text-muted-foreground">Try adjusting your search or location</p>
        </div>
    </div>

    {{-- ===== Map modal (same IDs/handlers as desktop) ===== --}}
    <div x-show="mapModalOpen"
         x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50" style="display: none;">
        <div class="fixed inset-0 bg-black/50" @click="closeMapModal()"></div>
        <div class="fixed inset-x-0 bottom-0 p-3">
            <div x-show="mapModalOpen"
                 x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-8" x-transition:enter-end="opacity-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 translate-y-8"
                 class="bg-white rounded-2xl shadow-2xl border border-border w-full max-h-[88vh] overflow-hidden" @click.stop>
                <div class="flex items-center justify-between px-4 py-3 border-b border-border">
                    <h5 class="text-base font-semibold"><i class="bi bi-geo-alt-fill mr-2 text-primary"></i>Set Your Location</h5>
                    <button type="button" class="btn-close" @click="closeMapModal()"></button>
                </div>
                <div class="p-0">
                    <div id="map" style="height: min(420px, 55vh); width: 100%;"></div>
                </div>
                <div class="px-4 py-3 bg-muted border-t border-border">
                    <small class="text-muted-foreground block mb-2">
                        <i class="bi bi-geo-alt-fill mr-1"></i><span id="modalLocationCoordinates">Drag the marker to set your location</span>
                    </small>
                    <button type="button" class="btn btn-primary w-full" id="applyLocationBtn" @click="applyLocation()">
                        <i class="bi bi-check-circle mr-2"></i>Apply Location
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Join Club Modal (shared) --}}
    @include('platform.partials.join-club-modal')

    <x-toast-notification />
</div>

{{-- Shared runtime: identical exploreApp() logic + Leaflet assets (same as desktop) --}}
@include('platform.partials.explore-runtime')
@endsection
