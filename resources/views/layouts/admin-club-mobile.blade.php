@extends('layouts.app')

{{-- Hide the global brand navbar so this shell is the single top bar.
     Toasts/assets/notification script still load (they live outside the navbar block). --}}
@section('hide-navbar', true)

@section('content')
@php
    $clubId = $club->slug ?? $club->id ?? null;
    $currentRoute = request()->route()?->getName();
    $clubPublicUrl = \App\Http\Controllers\QrController::clubPageUrl($club);

    // Grouped navigation for the drawer (matches the liked mockup structure).
    $navGroups = [
        __('admin.nav_group_overview') => [
            ['route'=>'admin.club.dashboard',   'icon'=>'bi-speedometer2', 'label'=>__('admin.nav_dashboard')],
            ['route'=>'admin.club.analytics',   'icon'=>'bi-bar-chart',    'label'=>__('admin.nav_analytics')],
        ],
        __('admin.nav_group_people') => [
            ['route'=>'admin.club.members',     'icon'=>'bi-people',        'label'=>__('admin.nav_members')],
            ['route'=>'admin.club.instructors', 'icon'=>'bi-person-badge',  'label'=>__('admin.nav_instructors')],
            ['route'=>'admin.club.roles',       'icon'=>'bi-shield-check',  'label'=>__('admin.nav_roles')],
            ['route'=>'admin.club.messages',    'icon'=>'bi-chat-dots',     'label'=>__('admin.nav_messages')],
            ['route'=>'admin.club.notifications','icon'=>'bi-bell',         'label'=>__('admin.nav_notifications')],
        ],
        __('admin.nav_group_offerings') => [
            ['route'=>'admin.club.packages',    'icon'=>'bi-box',           'label'=>__('admin.nav_packages')],
            ['route'=>'admin.club.activities',  'icon'=>'bi-activity',      'label'=>__('admin.nav_activities')],
            ['route'=>'admin.club.events',      'icon'=>'bi-calendar-event','label'=>__('admin.nav_events')],
            ['route'=>'admin.club.facilities',  'icon'=>'bi-geo-alt',       'label'=>__('admin.nav_facilities')],
        ],
        __('admin.nav_group_store') => [
            ['route'=>'admin.club.shop',        'icon'=>'bi-shop',          'label'=>__('admin.nav_shop')],
            ['route'=>'admin.club.orders',      'icon'=>'bi-bag-check',     'label'=>__('admin.nav_orders')],
        ],
        __('admin.nav_group_content') => [
            ['route'=>'admin.club.gallery',     'icon'=>'bi-images',        'label'=>__('admin.nav_gallery')],
            ['route'=>'admin.club.timeline',    'icon'=>'bi-newspaper',     'label'=>__('admin.nav_timeline')],
            ['route'=>'admin.club.perks',       'icon'=>'bi-gift',          'label'=>__('admin.nav_perks')],
            ['route'=>'admin.club.achievements','icon'=>'bi-trophy',        'label'=>__('admin.nav_achievements')],
        ],
        __('admin.nav_group_finance') => [
            ['route'=>'admin.club.financials',  'icon'=>'bi-currency-dollar','label'=>__('admin.nav_financials')],
        ],
        __('admin.nav_group_settings') => [
            ['route'=>'admin.club.details',     'icon'=>'bi-building',      'label'=>__('admin.nav_details')],
        ],
    ];

    // Flatten for label lookup + bottom-tab definition.
    $allNav = collect($navGroups)->flatten(1);
    $activeLabel = optional($allNav->firstWhere('route', $currentRoute))['label'] ?? '';
    $bottomTabs = [
        ['route'=>'admin.club.dashboard',  'icon'=>'bi-speedometer2',    'label'=>__('admin.nav_home')],
        ['route'=>'admin.club.members',    'icon'=>'bi-people',          'label'=>__('admin.nav_members')],
        ['route'=>'admin.club.financials', 'icon'=>'bi-currency-dollar', 'label'=>__('admin.nav_billing')],
        ['route'=>'admin.club.packages',   'icon'=>'bi-box',             'label'=>__('admin.nav_packages')],
    ];

    $hasBusiness = Auth::user()->hasApprovedBusiness();
    $businessName = $hasBusiness ? Auth::user()->ownedBusiness->name : null;
@endphp

<div x-data="{ drawer:false, switcher:false, showNotificationModal:false }" @shell:navigated.window="drawer=false; switcher=false">

    {{-- ===== Header (shared, identical to Personal) ===== --}}
    @include('partials.mobile-header', ['switcherCurrent' => 'business', 'shellTitle' => ($activeLabel ?: ($club->club_name ?? __('admin.nav_dashboard')))])

    {{-- ===== Left drawer ===== --}}
    <div x-show="drawer" x-cloak class="fixed inset-0 z-50" style="display:none;">
        <div class="absolute inset-0 bg-black/40" @click="drawer=false"
             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"></div>
        <aside class="absolute top-0 start-0 h-full w-[280px] max-w-[85vw] bg-white shadow-2xl flex flex-col overflow-y-auto"
               x-transition:enter="transition ease-out duration-250" x-transition:enter-start="ltr:-translate-x-full rtl:translate-x-full" x-transition:enter-end="translate-x-0"
               x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-x-0" x-transition:leave-end="ltr:-translate-x-full rtl:translate-x-full">
            {{-- Drawer header --}}
            <div class="flex items-center justify-between p-4 border-b border-border">
                <div class="flex items-center gap-2 min-w-0">
                    @if(!empty($club->logo))
                        <img src="{{ asset('storage/'.$club->logo) }}" alt="" class="w-10 h-10 rounded-lg object-cover flex-shrink-0">
                    @else
                        <span class="w-10 h-10 rounded-lg bg-accent flex items-center justify-center text-primary font-bold text-xs flex-shrink-0">{{ mb_strtoupper(mb_substr($club->club_name ?? 'CL', 0, 2, 'UTF-8'), 'UTF-8') }}</span>
                    @endif
                    <div class="min-w-0">
                        <p class="font-bold text-foreground truncate text-sm">{{ $club->club_name }}</p>
                        <p class="text-[10px] text-muted-foreground">{{ __('admin.club_management') }}</p>
                    </div>
                </div>
                <button @click="drawer=false" class="w-8 h-8 rounded-lg bg-muted flex items-center justify-center text-muted-foreground"><i class="bi bi-x-lg"></i></button>
            </div>
            {{-- Drawer nav --}}
            <nav class="p-3 flex-1">
                {{-- Preview club page (mobile view) — pinned above the first group --}}
                <a href="{{ $clubPublicUrl }}"
                   class="shell-nav-link flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-colors">
                    <i class="bi bi-eye text-lg w-5 text-center"></i>{{ __('admin.preview_club_page') }}
                </a>
                @foreach($navGroups as $groupLabel => $items)
                    <p class="px-2 mt-3 mb-1 text-[10px] font-bold uppercase tracking-wide text-muted-foreground">{{ $groupLabel }}</p>
                    @foreach($items as $item)
                        @if(!empty($item['external']))
                            <a href="{{ $item['url'] }}"
                               class="shell-nav-link flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-colors">
                                <i class="bi {{ $item['icon'] }} text-lg w-5 text-center"></i>{{ $item['label'] }}
                            </a>
                        @else
                            @php $active = $currentRoute === $item['route']; @endphp
                            <a href="{{ route($item['route'], $clubId) }}" data-shell-link data-route="{{ $item['route'] }}"
                               class="shell-nav-link flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-colors {{ $active ? 'is-active' : '' }}">
                                <i class="bi {{ $item['icon'] }} text-lg w-5 text-center"></i>{{ $item['label'] }}
                            </a>
                        @endif
                    @endforeach
                @endforeach
                {{-- Back out --}}
                <div class="border-t border-border mt-3 pt-3">
                    <button type="button" @click="showNotificationModal = true; drawer = false" class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium text-foreground hover:bg-accent">
                        <i class="bi bi-send text-lg w-5 text-center"></i>{{ __('admin.send_notification') }}
                    </button>
                    @if(Auth::user()->isSuperAdmin())
                        <a href="{{ route('admin.platform.index') }}"
                           class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium text-foreground hover:bg-accent">
                            <i class="bi bi-shield-check text-lg w-5 text-center"></i>{{ __('nav.admin_club_mobile_admin_dashboard') }}
                        </a>
                        <a href="{{ route('me.home') }}"
                           class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium text-foreground hover:bg-accent">
                            <i class="bi bi-house text-lg w-5 text-center"></i>{{ __('nav.admin_club_mobile_my_home') }}
                        </a>
                        <a href="{{ route('admin.platform.clubs') }}"
                           class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium text-muted-foreground hover:bg-accent">
                            <i class="bi bi-arrow-left text-lg w-5 text-center"></i>{{ __('nav.admin_club_mobile_back_to_clubs') }}
                        </a>
                    @else
                        <a href="{{ $hasBusiness ? route('business.dashboard') : route('clubs.explore') }}"
                           class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium text-muted-foreground hover:bg-accent">
                            <i class="bi bi-arrow-left text-lg w-5 text-center"></i>{{ $hasBusiness ? __('admin.back_to_chain') : __('admin.back_to_explore') }}
                        </a>
                    @endif
                    @include('partials.mobile-account-links')
                </div>
            </nav>
        </aside>
    </div>

    {{-- ===== Content ===== --}}
    <main id="shell-content" data-shell-id="admin-club" data-route="{{ $currentRoute }}" data-title="{{ $activeLabel }}" class="mobile-stagger px-4 py-4 pb-24 min-h-[60vh]">
        @yield('club-admin-content')
    </main>

    {{-- ===== Bottom tab bar ===== --}}
    <nav class="fixed bottom-0 inset-x-0 z-40 bg-white border-t border-border lg:hidden">
        <div class="grid grid-cols-5">
            @foreach($bottomTabs as $tab)
                @php $active = $currentRoute === $tab['route']; @endphp
                <a href="{{ route($tab['route'], $clubId) }}" data-shell-link data-route="{{ $tab['route'] }}"
                   class="shell-tab flex flex-col items-center justify-center gap-0.5 py-2.5 {{ $active ? 'is-active' : '' }}">
                    <i class="bi {{ $tab['icon'] }} text-lg"></i>
                    <span class="text-[10px] font-medium">{{ $tab['label'] }}</span>
                </a>
            @endforeach
            @php $shopActive = $currentRoute === 'admin.club.shop'; @endphp
            <a href="{{ route('admin.club.shop', $clubId) }}" data-shell-link data-route="admin.club.shop"
               class="shell-tab flex flex-col items-center justify-center gap-0.5 py-2.5 {{ $shopActive ? 'is-active' : '' }}">
                <i class="bi bi-shop text-lg"></i>
                <span class="text-[10px] font-medium">{{ __('admin.nav_shop') }}</span>
            </a>
        </div>
    </nav>

    @include('admin.club.notifications.send-modal')

</div>

@include('partials.mobile-shell-nav')
@endsection
