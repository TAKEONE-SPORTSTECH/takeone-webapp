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
                <i class="bi bi-compass"></i>{{ __('clubs::explore.hero_eyebrow') }}
            </span>
            <h1 class="text-3xl md:text-4xl font-bold text-primary mb-1.5 whitespace-nowrap">{{ __('clubs::explore.hero_title') }}</h1>
            <p class="text-base md:text-lg text-muted-foreground mb-3">{{ __('clubs::explore.hero_sub') }}</p>

            <div class="inline-flex items-center gap-2 bg-white border border-border rounded-full pl-3 pr-4 py-1.5 shadow-sm">
                <span class="relative flex h-2.5 w-2.5">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-primary opacity-60"></span>
                    <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-primary"></span>
                </span>
                <span id="currentLocation" class="text-sm font-semibold text-foreground"><i class="bi bi-geo-alt-fill me-1 text-primary"></i>{{ __('clubs::explore.detecting_location') }}</span>
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
                               placeholder="{{ __('clubs::explore.search_placeholder') }}">
                        <button class="btn btn-primary px-4 rounded-full" id="nearMeBtn" type="button" @click="openMapModal()">
                            <i class="bi bi-geo-alt-fill me-2"></i>{{ __('clubs::explore.near_me') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Category Tabs — only the ones that actually have something behind them.
         Built in PublicClubController@index from real counts, so a tab is never
         a promise the page cannot keep. The first tab starts active; the
         runtime's click handler is generic and reads data-category, so adding
         or removing tabs here needs no JS change. --}}
    @if($categories->isNotEmpty())
        <div class="flex justify-center mb-4">
            <div class="w-full lg:w-5/6">
                <div class="flex flex-wrap gap-2 justify-center">
                    @foreach($categories as $cat)
                        <button class="btn category-btn {{ $loop->first ? 'btn-primary active' : 'btn-outline-primary' }}"
                                data-category="{{ $cat['key'] }}">
                            <i class="bi {{ $cat['icon'] }} me-2"></i>{{ $cat['label'] }}
                        </button>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    <!-- Location Status Alert -->
    <div id="locationAlert" class="alert alert-info relative pe-12 hidden" role="alert" x-show="showAlert" x-transition>
        <i class="bi bi-info-circle me-2"></i>
        <span id="locationMessage"></span>
        <button type="button" class="absolute top-4 end-4 btn-close" @click="showAlert = false"></button>
    </div>

    <!-- Loading Spinner -->
    <div id="loadingSpinner" class="text-center py-5">
        <div class="spinner-border text-primary" role="status">
            <span class="sr-only">{{ __('clubs::explore.loading') }}</span>
        </div>
        <p class="mt-3 text-muted-foreground">{{ __('clubs::explore.finding') }}</p>
    </div>

    <!-- Clubs Grid -->
    <div class="flex justify-center" id="clubsGrid" style="display: none;">
        <div class="w-full">
            {{-- Fixed-width cards (320px) that wrap and stay centered — they don't stretch as the screen widens.
                 grid-template-columns is inlined so it works without a Tailwind rebuild. --}}
            <div class="grid gap-5" style="grid-template-columns: repeat(auto-fill, 320px); grid-auto-rows: 1fr; justify-content: center;" id="clubsContainer">
                <!-- Club cards will be inserted here -->
            </div>
        </div>
    </div>

    {{-- Events (Explore → Events tab): featured, happening now, coming up, then finished. --}}
    <div id="eventsSection" style="display: none;">
        {{-- Create an event straight from Explore → Events (2026-10-03). The editor
             itself decides who may host what (a club, a federation, or yourself). --}}
        @auth
        <div class="flex justify-end mb-6">
            <a href="{{ route('me.events.create') }}"
               class="inline-flex items-center gap-2 bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary/90 transition-colors font-medium text-sm">
                <i class="bi bi-plus-lg"></i>{{ __('personal.personal_events_create_event') }}
            </a>
        </div>
        @endauth
        {{-- Featured — approved paid featuring, still open --}}
        <section id="eventsFeaturedGroup" class="mb-9" style="display: none;">
            <header class="flex items-center gap-3 mb-4">
                <i class="bi bi-stars text-amber-500 text-lg shrink-0"></i>
                <h2 class="text-lg font-bold text-foreground whitespace-nowrap">{{ __('clubs::explore.ev_featured') }}</h2>
                <span id="eventsFeaturedCount" class="px-2.5 py-0.5 rounded-full bg-amber-100 text-amber-600 text-xs font-bold"></span>
                <span class="flex-1 h-px bg-border"></span>
            </header>
            <div class="grid gap-5" style="grid-template-columns: repeat(auto-fill, 320px); grid-auto-rows: 1fr; justify-content: center;" id="eventsFeaturedContainer"></div>
        </section>

        {{-- Happening now --}}
        <section id="eventsLiveGroup" class="mb-9" style="display: none;">
            <header class="flex items-center gap-3 mb-4">
                <span class="relative flex h-2.5 w-2.5 shrink-0">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-500 opacity-70"></span>
                    <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-red-500"></span>
                </span>
                <h2 class="text-lg font-bold text-foreground whitespace-nowrap">{{ __('clubs::explore.ev_happening_now') }}</h2>
                <span id="eventsLiveCount" class="px-2.5 py-0.5 rounded-full bg-red-500/10 text-red-600 text-xs font-bold"></span>
                <span class="flex-1 h-px bg-border"></span>
            </header>
            <div class="grid gap-5" style="grid-template-columns: repeat(auto-fill, 320px); grid-auto-rows: 1fr; justify-content: center;" id="eventsLiveContainer"></div>
        </section>

        {{-- Coming up --}}
        <section id="eventsUpcomingGroup" class="mb-9" style="display: none;">
            <header class="flex items-center gap-3 mb-4">
                <i class="bi bi-calendar-event text-primary text-lg shrink-0"></i>
                <h2 class="text-lg font-bold text-foreground whitespace-nowrap">{{ __('clubs::explore.ev_coming_up') }}</h2>
                <span id="eventsUpcomingCount" class="px-2.5 py-0.5 rounded-full bg-primary/10 text-primary text-xs font-bold"></span>
                <span class="flex-1 h-px bg-border"></span>
            </header>
            <div class="grid gap-5" style="grid-template-columns: repeat(auto-fill, 320px); grid-auto-rows: 1fr; justify-content: center;" id="eventsUpcomingContainer"></div>
        </section>

        {{-- Finished — most recent first --}}
        <section id="eventsFinishedGroup" class="mb-9" style="display: none;">
            <header class="flex items-center gap-3 mb-4">
                <i class="bi bi-flag-fill text-muted-foreground text-lg shrink-0"></i>
                <h2 class="text-lg font-bold text-foreground whitespace-nowrap">{{ __('clubs::explore.ev_finished') }}</h2>
                <span id="eventsFinishedCount" class="px-2.5 py-0.5 rounded-full bg-muted text-muted-foreground text-xs font-bold"></span>
                <span class="flex-1 h-px bg-border"></span>
            </header>
            <div class="grid gap-5" style="grid-template-columns: repeat(auto-fill, 320px); grid-auto-rows: 1fr; justify-content: center;" id="eventsFinishedContainer"></div>
        </section>

        {{-- Empty --}}
        <div id="eventsEmpty" class="flex flex-col items-center justify-center text-center min-h-[360px]" style="display: none;">
            <div class="w-16 h-16 rounded-2xl bg-accent flex items-center justify-center mb-4">
                <i class="bi bi-calendar-x text-primary text-2xl"></i>
            </div>
            <h4 class="text-muted-foreground font-semibold">{{ __('clubs::explore.ev_none_title') }}</h4>
            <p class="text-muted-foreground text-sm">{{ __('clubs::explore.ev_none_hint') }}</p>
            @auth
            <a href="{{ route('me.events.create') }}"
               class="mt-4 inline-flex items-center gap-2 border border-primary text-primary bg-transparent px-4 py-2 rounded-md text-sm font-medium hover:bg-primary hover:text-white transition-colors">
                <i class="bi bi-plus-lg"></i>{{ __('personal.personal_events_create_event') }}
            </a>
            @endauth
        </div>
    </div>

    <!-- No Results -->
    <div class="flex justify-center" id="noResultsContainer" style="display: none;">
        <div class="w-full flex justify-center">
            <div id="noResults" class="flex flex-col items-center justify-center text-center min-h-[400px]">
                <i class="bi bi-inbox text-6xl text-gray-300"></i>
                <h4 class="mt-3 text-muted-foreground font-semibold">{{ __('clubs::explore.no_results') }}</h4>
                <p class="text-muted-foreground">{{ __('clubs::explore.no_results_hint') }}</p>
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
                        <i class="bi bi-geo-alt-fill me-2 text-primary"></i>{{ __('clubs::explore.set_location') }}
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
                        <span id="modalLocationCoordinates">{{ __('clubs::explore.drag_marker') }}</span>
                    </small>
                    <button type="button" class="btn btn-primary" id="applyLocationBtn" @click="applyLocation()">
                        <i class="bi bi-check-circle me-2"></i>{{ __('clubs::explore.apply_location') }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Join Club Modal --}}
    @include('clubs::public.partials.join-club-modal')

    <x-toast-notification />
</div>

@include('clubs::public.partials.explore-runtime')
@endsection

{{-- The event card as an entry TICKET: the event above a perforated tear line,
     the stub below it listing what entry costs — one receipt line per package
     ("Gi", "No-Gi", "Gi + No-Gi"), because an event is often several prices,
     not one. Drawn by eventCardHtml() in the explore runtime; classes are
     local (xev-*) because the Tailwind bundle is prebuilt. --}}
@push('styles')
<style>
    .xev { --c:#7c3aed; --ink:hsl(222 30% 14%); --soft:hsl(220 10% 46%); --notch:10px;
           position:relative; display:flex; flex-direction:column; height:100%; color:var(--ink);
           text-decoration:none; filter:drop-shadow(0 1px 1px rgb(15 23 42/.06)) drop-shadow(0 8px 18px rgb(15 23 42/.07));
           transition:transform .35s cubic-bezier(.2,.8,.2,1), filter .35s; }
    .xev:hover { transform:translateY(-5px); filter:drop-shadow(0 2px 2px rgb(15 23 42/.08)) drop-shadow(0 22px 34px rgb(15 23 42/.14)); }
    .xev:focus-visible { outline:3px solid var(--c); outline-offset:4px; border-radius:22px; }

    /* Two halves, each with a half-circle bitten out of the tear line's ends. */
    .xev-top  { flex:1; display:flex; flex-direction:column; background:#fff; border-radius:22px 22px 0 0;
                -webkit-mask:radial-gradient(circle var(--notch) at 0 100%,#0000 98%,#000) left/51% 100% no-repeat,
                             radial-gradient(circle var(--notch) at 100% 100%,#0000 98%,#000) right/51% 100% no-repeat;
                        mask:radial-gradient(circle var(--notch) at 0 100%,#0000 98%,#000) left/51% 100% no-repeat,
                             radial-gradient(circle var(--notch) at 100% 100%,#0000 98%,#000) right/51% 100% no-repeat; }
    .xev-stub { position:relative; padding:14px 18px 16px; border-radius:0 0 22px 22px;
                background:#fbfbfd; background:color-mix(in srgb, var(--c) 6%, #fff);
                -webkit-mask:radial-gradient(circle var(--notch) at 0 0,#0000 98%,#000) left/51% 100% no-repeat,
                             radial-gradient(circle var(--notch) at 100% 0,#0000 98%,#000) right/51% 100% no-repeat;
                        mask:radial-gradient(circle var(--notch) at 0 0,#0000 98%,#000) left/51% 100% no-repeat,
                             radial-gradient(circle var(--notch) at 100% 0,#0000 98%,#000) right/51% 100% no-repeat; }
    .xev-perf { height:0; margin:0 calc(var(--notch) + 6px); border-top:2px dashed; position:relative; z-index:1;
                border-color:#e5e7eb; border-color:color-mix(in srgb, var(--c) 28%, #e5e7eb); }

    .xev-media { position:relative; aspect-ratio:16/10; overflow:hidden; border-radius:22px 22px 0 0; background:#14121f; }
    .xev-img   { position:absolute; inset:0; width:100%; height:100%; object-fit:cover; display:grid; place-items:center;
                 transition:transform .7s cubic-bezier(.2,.8,.2,1); }
    .xev:hover .xev-img { transform:scale(1.06); }
    .xev-ghost { font-size:5.5rem; color:rgb(255 255 255/.14); }
    .xev-shade { position:absolute; inset:0; background:linear-gradient(to top, rgb(10 8 20/.88), rgb(10 8 20/.2) 55%, transparent); }
    .is-ended .xev-img { filter:grayscale(.55) brightness(.92); }

    .xev-date { position:absolute; top:12px; inset-inline-start:12px; width:50px; border-radius:14px; overflow:hidden;
                background:rgb(255 255 255/.96); box-shadow:0 6px 16px rgb(0 0 0/.22); text-align:center; display:flex; flex-direction:column; }
    .xev-date-mon { background:var(--c); color:#fff; font-size:9px; font-weight:800; letter-spacing:.14em; text-transform:uppercase; padding:3px 0; }
    .xev-date-day { font-size:20px; font-weight:900; line-height:1.05; padding-top:3px; letter-spacing:-.02em; }
    .xev-date-wd  { font-size:9px; font-weight:600; color:var(--soft); text-transform:uppercase; padding-bottom:5px; }

    .xev-pills { position:absolute; top:12px; inset-inline-end:12px; display:flex; flex-direction:column; align-items:flex-end; gap:6px; }
    .xev-pill  { display:inline-flex; align-items:center; gap:5px; padding:4px 10px; border-radius:999px; font-size:10px; font-weight:800;
                 letter-spacing:.06em; text-transform:uppercase; color:#fff; background:rgb(0 0 0/.5); backdrop-filter:blur(6px); }
    .xev-pill--gold { background:linear-gradient(135deg,#f59e0b,#d97706); box-shadow:0 4px 12px rgb(217 119 6/.4); }
    .xev-pill--live { background:#ef4444; box-shadow:0 4px 12px rgb(239 68 68/.4); }
    .xev-pill--soon  { background:#16a34a; box-shadow:0 4px 12px rgb(22 163 74/.4); }
    .xev-pill--ended { background:#dc2626; box-shadow:0 4px 12px rgb(220 38 38/.4); }
    .xev-dot { width:6px; height:6px; border-radius:50%; background:#fff; animation:xevPulse 1.4s ease-in-out infinite; }
    @keyframes xevPulse { 50% { opacity:.25; transform:scale(.7); } }

    .xev-heading { position:absolute; inset-inline:0; bottom:0; padding:14px 16px; color:#fff; }
    .xev-heading h3 { font-size:17px; font-weight:900; line-height:1.2; letter-spacing:-.01em; text-shadow:0 1px 8px rgb(0 0 0/.35);
                      display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
    .xev-heading p  { margin-top:4px; font-size:12px; color:rgb(255 255 255/.85); display:flex; align-items:center; gap:5px; min-width:0; }
    .xev-heading p span { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }

    .xev-body { flex:1; padding:14px 18px 16px; display:flex; flex-direction:column; gap:7px; }
    .xev-meta { display:flex; align-items:center; gap:9px; font-size:12.5px; color:var(--soft); min-width:0; }
    .xev-meta i { color:var(--c); font-size:13px; flex-shrink:0; }
    .xev-meta span { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .xev-crowd { margin-top:auto; padding-top:8px; }
    .xev-crowd-row { display:flex; justify-content:space-between; align-items:center; font-size:11.5px; color:var(--soft); }
    .xev-crowd-row i { color:var(--c); margin-inline-end:5px; }
    .xev-crowd-row b { color:var(--ink); font-weight:800; margin-inline-end:3px; }
    .xev-full { color:#dc2626 !important; }
    .xev-bar { margin-top:6px; height:5px; border-radius:99px; background:hsl(220 15% 93%); overflow:hidden; }
    .xev-bar span { display:block; height:100%; border-radius:inherit; background:var(--c); transition:width .8s ease; }

    .xev-stub-head { display:flex; align-items:baseline; justify-content:space-between; gap:10px; }
    .xev-eyebrow { font-size:10px; font-weight:800; letter-spacing:.18em; text-transform:uppercase; color:var(--c); }
    .xev-from { font-size:20px; font-weight:900; letter-spacing:-.02em; color:var(--ink); font-variant-numeric:tabular-nums; white-space:nowrap; }
    .xev-from small { font-size:10px; font-weight:700; color:var(--soft); text-transform:uppercase; letter-spacing:.08em; margin-inline-end:5px; }
    .xev-free, .xev-final { display:inline-flex; align-items:center; gap:5px; font-size:12px; font-weight:800; padding:3px 10px; border-radius:999px; }
    .xev-free  { color:#15803d; background:#dcfce7; }
    .xev-final { color:var(--ink); background:#fff; box-shadow:inset 0 0 0 1px hsl(220 14% 88%); }
    .xev-final i { color:#d97706; }

    .xev-lines { list-style:none; margin:10px 0 0; padding:0; display:flex; flex-direction:column; gap:6px; }
    .xev-line  { display:flex; align-items:baseline; gap:6px; font-size:12.5px; min-width:0; }
    .xev-line-label { color:var(--ink); font-weight:600; min-width:0; overflow-wrap:anywhere; }
    .xev-leader { flex:1; min-width:12px; border-bottom:1.5px dotted; border-color:hsl(220 12% 78%); transform:translateY(-3px); }
    .xev-line-price { font-weight:800; color:var(--ink); font-variant-numeric:tabular-nums; white-space:nowrap; }
    .xev-lines.is-muted .xev-line-label, .xev-lines.is-muted .xev-line-price { color:var(--soft); font-weight:600; }
    .xev-more { margin-top:6px; font-size:11px; font-weight:700; color:var(--c); }
    .xev-late { margin-top:10px; display:flex; align-items:center; gap:6px; font-size:11px; font-weight:600; color:#b45309; }
    .xev-late.is-on { color:#fff; background:#d97706; padding:4px 9px; border-radius:8px; width:fit-content; }

    .xev-cta { margin-top:14px; display:flex; align-items:center; justify-content:center; gap:4px; height:42px; border-radius:14px;
               background:var(--c); color:#fff; font-size:13.5px; font-weight:800; letter-spacing:.01em;
               box-shadow:0 6px 16px color-mix(in srgb, var(--c) 35%, transparent); transition:gap .25s, filter .25s; }
    .xev-cta i { font-size:20px; }
    .xev:hover .xev-cta { gap:9px; filter:brightness(1.06); }
    .is-ended .xev-cta { background:#fff; color:var(--ink); box-shadow:inset 0 0 0 1.5px hsl(220 14% 86%); }

    @media (prefers-reduced-motion: reduce) {
        .xev, .xev-img, .xev-cta, .xev-bar span { transition:none; }
        .xev-dot { animation:none; }
    }
</style>
@endpush

