@extends('layouts.app')

@section('content')
<div class="tf-container" x-data="exploreApp()">
    <!-- Hero Section -->
    <div class="relative text-center mb-3">
        <div class="pointer-events-none absolute inset-x-0 -top-4 flex justify-center" aria-hidden="true">
            <div class="w-96 h-24 rounded-full opacity-50" style="background: radial-gradient(circle, hsl(250 65% 65% / 0.16), transparent 70%);"></div>
        </div>

        <div class="relative">
            <span class="inline-flex items-center gap-1.5 mb-2 px-3 py-1 rounded-full bg-accent text-primary text-[11px] font-bold tracking-[0.14em] uppercase">
                <i class="bi bi-compass"></i>{{ __('explore.hero_eyebrow') }}
            </span>
            <h1 class="text-3xl md:text-4xl font-bold text-primary mb-1.5 whitespace-nowrap">{{ __('explore.hero_title') }}</h1>
            <p class="text-base md:text-lg text-muted-foreground mb-3">{{ __('explore.hero_sub') }}</p>

            <div class="inline-flex items-center gap-2 bg-white border border-border rounded-full pl-3 pr-4 py-1.5 shadow-sm">
                <span class="relative flex h-2.5 w-2.5">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-primary opacity-60"></span>
                    <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-primary"></span>
                </span>
                <span id="currentLocation" class="text-sm font-semibold text-foreground"><i class="bi bi-geo-alt-fill me-1 text-primary"></i>{{ __('explore.detecting_location') }}</span>
            </div>
        </div>
    </div>

    <!-- Search Bar with Near Me Button -->
    <div class="flex justify-center mb-4">
        <div class="w-full lg:w-5/6">
            <div class="card shadow-sm rounded-full border-0">
                <div class="rounded-full p-2">
                    <div class="flex rounded-full overflow-hidden">
                        <span class="flex items-center px-3 py-2 bg-white border-0 rounded-s-full">
                            <i class="bi bi-search"></i>
                        </span>
                        <input type="text" id="searchInput" class="flex-1 px-3 py-2 bg-white border-0 focus:outline-none focus:ring-0"
                               placeholder="{{ __('explore.search_placeholder') }}">
                        <button class="btn btn-primary px-4 rounded-full" id="nearMeBtn" type="button" @click="openMapModal()">
                            <i class="bi bi-geo-alt-fill me-2"></i>{{ __('explore.near_me') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Category Tabs -->
    <div class="flex justify-center mb-4">
        <div class="w-full lg:w-5/6">
            <div class="flex flex-wrap gap-2 justify-center">
                <button class="btn btn-primary category-btn active" data-category="all">
                    <i class="bi bi-search me-2"></i>{{ __('explore.cat_all') }}
                </button>
                <button class="btn btn-outline-primary category-btn" data-category="sports-clubs">
                    <i class="bi bi-trophy me-2"></i>{{ __('explore.cat_clubs') }}
                </button>
                <button class="btn btn-outline-primary category-btn" data-category="personal-trainers">
                    <i class="bi bi-person me-2"></i>{{ __('explore.cat_trainers') }}
                </button>
                <button class="btn btn-outline-primary category-btn" data-category="events">
                    <i class="bi bi-calendar-event me-2"></i>{{ __('explore.cat_events') }}
                </button>
                <button class="btn btn-outline-primary category-btn" data-category="nutrition-clinic">
                    <i class="bi bi-apple me-2"></i>{{ __('explore.cat_nutrition') }}
                </button>
                <button class="btn btn-outline-primary category-btn" data-category="physiotherapy-clinics">
                    <i class="bi bi-activity me-2"></i>{{ __('explore.cat_physiotherapy') }}
                </button>
                <button class="btn btn-outline-primary category-btn" data-category="sports-shops">
                    <i class="bi bi-bag me-2"></i>{{ __('explore.cat_shops') }}
                </button>
                <button class="btn btn-outline-primary category-btn" data-category="venues">
                    <i class="bi bi-building-fill me-2"></i>{{ __('explore.cat_venues') }}
                </button>
                <button class="btn btn-outline-primary category-btn" data-category="supplements">
                    <i class="bi bi-box me-2"></i>{{ __('explore.cat_supplements') }}
                </button>
                <button class="btn btn-outline-primary category-btn" data-category="food-plans">
                    <i class="bi bi-egg-fried me-2"></i>{{ __('explore.cat_food_plans') }}
                </button>
            </div>
        </div>
    </div>

    <!-- Location Status Alert -->
    <div id="locationAlert" class="alert alert-info relative pe-12 hidden" role="alert" x-show="showAlert" x-transition>
        <i class="bi bi-info-circle me-2"></i>
        <span id="locationMessage"></span>
        <button type="button" class="absolute top-4 end-4 btn-close" @click="showAlert = false"></button>
    </div>

    <!-- Loading Spinner -->
    <div id="loadingSpinner" class="text-center py-5">
        <div class="spinner-border text-primary" role="status">
            <span class="sr-only">{{ __('explore.loading') }}</span>
        </div>
        <p class="mt-3 text-muted-foreground">{{ __('explore.finding') }}</p>
    </div>

    <!-- Clubs Grid -->
    <div class="flex justify-center" id="clubsGrid" style="display: none;">
        <div class="w-full">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4" id="clubsContainer">
                <!-- Club cards will be inserted here -->
            </div>
        </div>
    </div>

    <!-- No Results -->
    <div class="flex justify-center" id="noResultsContainer" style="display: none;">
        <div class="w-full flex justify-center">
            <div id="noResults" class="flex flex-col items-center justify-center text-center min-h-[400px]">
                <i class="bi bi-inbox text-6xl text-gray-300"></i>
                <h4 class="mt-3 text-muted-foreground font-semibold">{{ __('explore.no_results') }}</h4>
                <p class="text-muted-foreground">{{ __('explore.no_results_hint') }}</p>
            </div>
        </div>
    </div>

    <!-- Map Modal (Alpine.js) -->
    <div x-show="mapModalOpen"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50"
         style="display: none;">
        <!-- Backdrop -->
        <div class="fixed inset-0 bg-black/50" @click="closeMapModal()"></div>

        <!-- Modal Dialog -->
        <div class="fixed inset-0 flex items-center justify-center p-4">
            <div x-show="mapModalOpen"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-95"
                 class="bg-white rounded-xl shadow-2xl border border-border w-full max-w-5xl max-h-[90vh] overflow-hidden"
                 @click.stop>
                <!-- Modal Header -->
                <div class="flex items-center justify-between px-6 py-4">
                    <h5 class="text-lg font-semibold">
                        <i class="bi bi-geo-alt-fill me-2 text-primary"></i>{{ __('explore.set_location') }}
                    </h5>
                    <button type="button" class="btn-close" @click="closeMapModal()"></button>
                </div>
                <!-- Modal Body -->
                <div class="p-0">
                    <div id="map" style="height: min(500px, 60vh); width: 100%;"></div>
                </div>
                <!-- Modal Footer -->
                <div class="flex items-center justify-between px-6 py-4 bg-muted border-t border-border">
                    <small class="text-muted-foreground">
                        <i class="bi bi-geo-alt-fill me-1"></i>
                        <span id="modalLocationCoordinates">{{ __('explore.drag_marker') }}</span>
                    </small>
                    <button type="button" class="btn btn-primary" id="applyLocationBtn" @click="applyLocation()">
                        <i class="bi bi-check-circle me-2"></i>{{ __('explore.apply_location') }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Join Club Modal --}}
    @include('platform.partials.join-club-modal')

    <x-toast-notification />
</div>

@include('platform.partials.explore-runtime')
@endsection
