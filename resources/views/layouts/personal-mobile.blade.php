@extends('layouts.app')

{{-- Single mobile shell — hide the global brand navbar. --}}
@section('hide-navbar', true)

@section('content')
@php
    $currentRoute = request()->route()?->getName();
    $u = Auth::user();

    $navGroups = [
        'Home' => [
            ['route'=>'me.home',     'icon'=>'bi-newspaper',      'label'=>'News Feed'],
            ['route'=>'me.schedule', 'icon'=>'bi-calendar-event', 'label'=>'My Schedule'],
        ],
        'Profile' => [
            ['route'=>'me.profile',  'icon'=>'bi-person',         'label'=>'My Profile'],
            ['route'=>'me.packages', 'icon'=>'bi-box',            'label'=>'My Packages'],
            ['route'=>'me.progress', 'icon'=>'bi-graph-up-arrow', 'label'=>'My Progress'],
        ],
        'Finance' => [
            ['route'=>'me.payments', 'icon'=>'bi-credit-card',    'label'=>'Payments & Billing'],
        ],
        'Community' => [
            ['route'=>'me.community','icon'=>'bi-chat-dots',       'label'=>'Club Chat'],
            ['route'=>'me.events',   'icon'=>'bi-calendar-heart',  'label'=>'Events'],
        ],
        'Settings' => [
            ['route'=>'me.settings', 'icon'=>'bi-gear',            'label'=>'Account Settings'],
        ],
    ];
    $allNav = collect($navGroups)->flatten(1);
    $activeLabel = optional($allNav->firstWhere('route', $currentRoute))['label'] ?? 'Home';
    $bottomTabs = [
        ['route'=>'me.home',     'icon'=>'bi-newspaper',      'label'=>'Feed'],
        ['route'=>'me.schedule', 'icon'=>'bi-calendar-event', 'label'=>'Schedule'],
        ['route'=>'me.profile',  'icon'=>'bi-person',         'label'=>'Profile'],
        ['route'=>'me.payments', 'icon'=>'bi-credit-card',    'label'=>'Billing'],
    ];

    $hasBusiness = $u->hasApprovedBusiness();
    $businessName = $hasBusiness ? $u->ownedBusiness->name : null;
@endphp

<div x-data="{ drawer:false, switcher:false }" @shell:navigated.window="drawer=false; switcher=false">

    {{-- ===== Header (shared, identical to Club view) ===== --}}
    @include('partials.mobile-header', ['switcherCurrent' => 'personal', 'shellTitle' => $activeLabel])

    {{-- ===== Left drawer ===== --}}
    <div x-show="drawer" x-cloak class="fixed inset-0 z-50" style="display:none;">
        <div class="absolute inset-0 bg-black/40" @click="drawer=false"
             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"></div>
        <aside class="absolute top-0 left-0 h-full w-[280px] max-w-[85vw] bg-white shadow-2xl flex flex-col overflow-y-auto"
               x-transition:enter="transition ease-out duration-250" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0"
               x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full">
            <div class="flex items-center justify-between p-4 border-b border-border">
                <div class="flex items-center gap-2 min-w-0">
                    <span class="w-10 h-10 rounded-full bg-muted flex items-center justify-center overflow-hidden flex-shrink-0">
                        @if($u->profile_picture)<img src="{{ asset('storage/'.$u->profile_picture) }}?v={{ optional($u->updated_at)->timestamp }}" alt="" class="w-10 h-10 object-cover">@else<i class="bi bi-person text-muted-foreground"></i>@endif
                    </span>
                    <div class="min-w-0">
                        <p class="font-bold text-foreground truncate text-sm">{{ $u->full_name }}</p>
                        <p class="text-[10px] text-muted-foreground">Personal View</p>
                    </div>
                </div>
                <button @click="drawer=false" class="w-8 h-8 rounded-lg bg-muted flex items-center justify-center text-muted-foreground"><i class="bi bi-x-lg"></i></button>
            </div>
            <nav class="p-3 flex-1">
                @foreach($navGroups as $groupLabel => $items)
                    <p class="px-2 mt-3 mb-1 text-[10px] font-bold uppercase tracking-wide text-muted-foreground">{{ $groupLabel }}</p>
                    @foreach($items as $item)
                        @php $active = $currentRoute === $item['route']; @endphp
                        <a href="{{ route($item['route']) }}" data-shell-link data-route="{{ $item['route'] }}"
                           class="shell-nav-link flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-colors {{ $active ? 'is-active' : '' }}">
                            <i class="bi {{ $item['icon'] }} text-lg w-5 text-center"></i>{{ $item['label'] }}
                        </a>
                    @endforeach
                @endforeach
                <div class="border-t border-border mt-3 pt-3">
                    <a href="{{ route('clubs.explore') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium text-muted-foreground hover:bg-accent">
                        <i class="bi bi-compass text-lg w-5 text-center"></i>Explore clubs
                    </a>
                    @include('partials.mobile-account-links')
                </div>
            </nav>
        </aside>
    </div>

    {{-- ===== Content ===== --}}
    <main id="shell-content" data-route="{{ $currentRoute }}" data-title="{{ $activeLabel }}" class="mobile-stagger px-4 py-4 pb-24 min-h-[60vh]">
        @yield('personal-content')
    </main>

    {{-- ===== Bottom tab bar ===== --}}
    <nav class="fixed bottom-0 inset-x-0 z-40 bg-white border-t border-border lg:hidden">
        <div class="grid grid-cols-5">
            @foreach($bottomTabs as $tab)
                @php $active = $currentRoute === $tab['route']; @endphp
                <a href="{{ route($tab['route']) }}" data-shell-link data-route="{{ $tab['route'] }}"
                   class="shell-tab flex flex-col items-center justify-center gap-0.5 py-2.5 {{ $active ? 'is-active' : '' }}">
                    <i class="bi {{ $tab['icon'] }} text-lg"></i>
                    <span class="text-[10px] font-medium">{{ $tab['label'] }}</span>
                </a>
            @endforeach
            <button @click="drawer = true" class="flex flex-col items-center justify-center gap-0.5 py-2.5 text-muted-foreground">
                <i class="bi bi-grid text-lg"></i>
                <span class="text-[10px] font-medium">Menu</span>
            </button>
        </div>
    </nav>

</div>

@include('partials.mobile-shell-nav')
@endsection
