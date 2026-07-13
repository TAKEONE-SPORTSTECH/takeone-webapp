@extends('layouts.app')

{{-- Single mobile shell — hide the global brand navbar. --}}
@section('hide-navbar', true)

@section('content')
@php
    $currentRoute = request()->route()?->getName();
    $u = Auth::user();

    $navGroups = [
        'Home' => [
            ['route'=>'me.home',     'icon'=>'bi-newspaper',      'label'=>__('nav.news_feed')],
            ['route'=>'me.schedule', 'icon'=>'bi-calendar-event', 'label'=>__('nav.my_schedule')],
        ],
        'Profile' => [
            ['url'=>route('member.show', $u->uuid), 'icon'=>'bi-person', 'label'=>__('nav.my_profile')],
            ['route'=>'me.packages', 'icon'=>'bi-box',            'label'=>__('nav.my_packages')],
            ['route'=>'me.progress', 'icon'=>'bi-graph-up-arrow', 'label'=>__('nav.my_progress')],
        ],
        'Finance' => [
            ['route'=>'me.payments', 'icon'=>'bi-credit-card',    'label'=>__('nav.payments')],
        ],
        'Community' => [
            ['route'=>'me.events',   'icon'=>'bi-calendar-heart',  'label'=>__('nav.events')],
        ],
        'Settings' => [
            ['route'=>'me.settings', 'icon'=>'bi-gear',            'label'=>__('nav.account_settings')],
        ],
    ];
    $allNav = collect($navGroups)->flatten(1)
        ->push(['route'=>'me.market',    'label'=>__('nav.tab_market')])      // bottom-tab only; header title lookup
        ->push(['route'=>'me.challenge', 'label'=>__('nav.tab_challenge')]);  // bottom-tab only; header title lookup
    // Pages outside the bottom-nav (e.g. Security) can pass a $shellTitle to label the header.
    $activeLabel = optional($allNav->firstWhere('route', $currentRoute))['label'] ?? ($shellTitle ?? __('nav.home'));
    // Bottom bar = the most-used, always-in-shell destinations (Facebook-style).
    // Challenge is the raised center action. Profile lives in the side drawer.
    $bottomTabs = [
        ['route'=>'me.home',      'icon'=>'bi-newspaper',      'label'=>__('nav.tab_feed'),      'dot'=>'feed'],
        ['route'=>'me.schedule',  'icon'=>'bi-calendar-week',  'label'=>__('nav.tab_schedule')],
        ['route'=>'me.challenge', 'icon'=>'bi-trophy-fill',    'label'=>__('nav.tab_challenge'), 'center'=>true, 'dot'=>'challenge'],
        ['route'=>'me.events',    'icon'=>'bi-calendar-heart', 'label'=>__('nav.tab_events'),    'dot'=>'events'],
        ['route'=>'me.market',    'icon'=>'bi-shop',           'label'=>__('nav.tab_market'),    'dot'=>'market'],
    ];

    // Unseen (red-dot) indicators for the bottom nav. Mark the section the user
    // is currently viewing as seen first, so its own dot never shows.
    $__navUser = auth()->user();
    $__navSection = ['me.home'=>'feed:all', 'me.challenge'=>'challenge', 'me.events'=>'events', 'me.market'=>'market'][$currentRoute] ?? null;
    $navDots = ['feed'=>false, 'challenge'=>false, 'events'=>false, 'market'=>false];
    if ($__navUser) {
        $__act = app(\App\Support\SectionActivity::class);
        if ($__navSection) { $__act->markSeen($__navUser, $__navSection); }
        $navDots = $__act->navDots($__navUser);
    }

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
        <aside class="absolute top-0 start-0 h-full w-[280px] max-w-[85vw] bg-white shadow-2xl flex flex-col overflow-y-auto"
               x-transition:enter="transition ease-out duration-250" x-transition:enter-start="ltr:-translate-x-full rtl:translate-x-full" x-transition:enter-end="translate-x-0"
               x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-x-0" x-transition:leave-end="ltr:-translate-x-full rtl:translate-x-full">
            <div class="relative p-4 border-b border-border bg-gradient-to-br from-accent/70 via-accent/25 to-white">
                <button @click="drawer=false" class="absolute top-3 end-3 w-8 h-8 rounded-lg bg-white/70 backdrop-blur flex items-center justify-center text-muted-foreground hover:bg-white transition-colors" aria-label="{{ __('nav.close_menu') }}"><i class="bi bi-x-lg"></i></button>
                <a href="{{ route('member.show', $u->uuid) }}" class="m-press flex items-center gap-3 min-w-0 pe-8">
                    <span class="w-12 h-12 rounded-2xl bg-white flex items-center justify-center overflow-hidden flex-shrink-0 ring-2 ring-white shadow-sm">
                        @if($u->profile_picture)<img src="{{ asset('storage/'.$u->profile_picture) }}?v={{ optional($u->updated_at)->timestamp }}" alt="" class="w-12 h-12 object-cover">@else<i class="bi bi-person text-xl text-muted-foreground"></i>@endif
                    </span>
                    <div class="min-w-0">
                        <p class="font-bold text-foreground truncate text-[15px] leading-tight">{{ $u->full_name }}</p>
                        <span class="inline-flex items-center gap-1 mt-1 text-[11px] font-semibold text-primary">{{ __('nav.view_profile') }} <i class="bi bi-arrow-right"></i></span>
                    </div>
                </a>
            </div>
            {{-- Get the App / Update — compact row; device-aware badge set via JS. --}}
            <div class="px-3 pt-2">
                <a id="get-app-nav" href="{{ route('me.app') }}" data-shell-link data-route="me.app"
                   class="m-press flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium text-foreground hover:bg-accent transition-colors">
                    <i class="bi bi-phone text-lg w-5 text-center"></i>
                    <span id="get-app-label" class="flex-1">{{ __('nav.get_app') }}</span>
                    <span id="get-app-sub" class="hidden"></span>
                    <span id="get-app-badge" class="hidden"></span>
                    <i class="bi bi-chevron-right text-xs text-muted-foreground/60"></i>
                </a>
            </div>

            @php
                $ownedBusiness    = $u->ownedBusiness;
                $businessApproved = $ownedBusiness && $ownedBusiness->isApproved();
                $impersonating    = session()->has('impersonate.original_id');

                // The drawer holds everything NOT in the bottom bar (disjoint, no
                // overlap). `shell` items swap in place (AJAX, keep their active
                // highlight); the rest are full-page links.
                $menu = [
                    __('nav.group_you') => [
                        ['url' => route('me.affiliations'),  'icon' => 'bi-diagram-3',     'label' => __('nav.affiliations')],
                        ['shell' => 'me.progress', 'icon' => 'bi-graph-up-arrow',  'label' => __('nav.my_progress')],
                    ],
                    __('nav.group_family') => [
                        ['url' => route('members.index'),  'icon' => 'bi-people',  'label' => __('nav.family')],
                        ['shell' => 'me.packages', 'icon' => 'bi-box',            'label' => __('nav.my_packages')],
                        ['shell' => 'me.payments', 'icon' => 'bi-receipt',        'label' => __('nav.payments')],
                    ],
                    // Explore is hidden when a club the user belongs to has locked
                    // cross-club discovery (club settings → block_explore).
                    __('nav.group_discover') => array_values(array_filter([
                        ['url' => route('me.people'),      'icon' => 'bi-people-fill', 'label' => __('personal.find_people')],
                        $u->isExploreLocked() ? null : ['url' => route('clubs.explore'), 'icon' => 'bi-compass', 'label' => __('nav.explore_clubs')],
                        ['scan' => true, 'icon' => 'bi-qr-code-scan', 'label' => __('header.scan_qr')],
                    ])),
                ];
            @endphp
            <nav class="p-3 flex-1">
                @foreach($menu as $groupLabel => $items)
                    <p class="px-3 mt-4 first:mt-1 mb-1.5 text-[10px] font-bold uppercase tracking-wider text-muted-foreground/80">{{ $groupLabel }}</p>
                    @foreach($items as $item)
                        @if(! empty($item['shell']))
                            <a href="{{ route($item['shell']) }}" data-shell-link data-route="{{ $item['shell'] }}"
                               class="shell-nav-link flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-colors {{ $currentRoute === $item['shell'] ? 'is-active' : '' }}">
                                <i class="bi {{ $item['icon'] }} text-lg w-5 text-center"></i>{{ $item['label'] }}
                            </a>
                        @elseif(! empty($item['toast']))
                            <button type="button" onclick="window.showToast && window.showToast('info', @js($item['toast']))"
                                    class="m-press w-full text-left flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium text-foreground hover:bg-accent transition-colors">
                                <i class="bi {{ $item['icon'] }} text-lg w-5 text-center"></i>{{ $item['label'] }}
                            </button>
                        @elseif(! empty($item['scan']))
                            <button type="button" @click="drawer=false; window.dispatchEvent(new CustomEvent('qr-scan:open'))"
                                    class="m-press w-full text-left flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium text-foreground hover:bg-accent transition-colors">
                                <i class="bi {{ $item['icon'] }} text-lg w-5 text-center"></i>{{ $item['label'] }}
                            </button>
                        @else
                            <a href="{{ $item['url'] }}"
                               class="m-press flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium text-foreground hover:bg-accent transition-colors">
                                <i class="bi {{ $item['icon'] }} text-lg w-5 text-center"></i><span class="flex-1">{{ $item['label'] }}</span>
                                <i class="bi bi-chevron-right text-xs text-muted-foreground/60"></i>
                            </a>
                        @endif
                    @endforeach
                @endforeach

                {{-- Business --}}
                <p class="px-3 mt-4 mb-1.5 text-[10px] font-bold uppercase tracking-wider text-muted-foreground/80">{{ __('nav.group_business') }}</p>
                <a href="{{ $businessApproved ? route('business.dashboard') : route('business.setup') }}"
                   class="m-press flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium text-foreground hover:bg-accent transition-colors">
                    <i class="bi bi-buildings text-lg w-5 text-center"></i>
                    <span class="flex-1">{{ __('nav.business_account') }}</span>
                    @if($ownedBusiness && ! $businessApproved)
                        <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 capitalize">{{ $ownedBusiness->status }}</span>
                    @else
                        <i class="bi bi-chevron-right text-xs text-muted-foreground/60"></i>
                    @endif
                </a>

                {{-- Settings --}}
                <p class="px-3 mt-4 mb-1.5 text-[10px] font-bold uppercase tracking-wider text-muted-foreground/80">{{ __('nav.group_settings') }}</p>
                <a href="{{ route('me.settings') }}" data-shell-link data-route="me.settings"
                   class="shell-nav-link flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-colors {{ $currentRoute === 'me.settings' ? 'is-active' : '' }}">
                    <i class="bi bi-gear text-lg w-5 text-center"></i>{{ __('nav.account_settings') }}
                </a>
                @if($u->isSuperAdmin())
                <a href="{{ route('admin.platform.index') }}" class="m-press flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium text-foreground hover:bg-accent transition-colors">
                    <i class="bi bi-shield-check text-lg w-5 text-center"></i><span class="flex-1">{{ __('nav.admin_panel') }}</span>
                    <i class="bi bi-chevron-right text-xs text-muted-foreground/60"></i>
                </a>
                @endif

                {{-- Session actions --}}
                <div class="border-t border-border mt-4 pt-3">
                    @if($impersonating)
                    <form method="POST" action="{{ route('impersonate.leave') }}">
                        @csrf
                        <button type="submit" class="m-press w-full text-left flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold text-amber-700 hover:bg-amber-50 transition-colors">
                            <i class="bi bi-incognito text-lg w-5 text-center"></i>{{ __('nav.exit_impersonation') }}
                        </button>
                    </form>
                    @endif
                    <a href="{{ route('logout') }}" onclick="event.preventDefault(); document.getElementById('drawer-logout').submit();"
                       class="m-press flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium text-destructive hover:bg-red-50 transition-colors">
                        <i class="bi bi-box-arrow-right text-lg w-5 text-center"></i>{{ __('nav.sign_out') }}
                    </a>
                    <form id="drawer-logout" action="{{ route('logout') }}" method="POST" class="hidden">@csrf</form>
                </div>
            </nav>
        </aside>
    </div>

    {{-- ===== Content ===== --}}
    <main id="shell-content" data-shell-id="personal" data-route="{{ $currentRoute }}" data-title="{{ $activeLabel }}" class="mobile-stagger px-4 py-4 pb-24 min-h-[60vh]">
        @yield('personal-content')
    </main>

    {{-- ===== Bottom tab bar ===== --}}
    <nav class="fixed bottom-0 inset-x-0 z-40 bg-white border-t border-border lg:hidden">
        <div class="grid grid-cols-5 items-end">
            @foreach($bottomTabs as $tab)
                @php $active = $currentRoute === $tab['route']; @endphp
                @php $showDot = ! empty($tab['dot']) && ! $active && ($navDots[$tab['dot']] ?? false); @endphp
                @if($tab['center'] ?? false)
                    {{-- Raised, enlarged center action --}}
                    <a href="{{ route($tab['route']) }}" data-shell-link data-route="{{ $tab['route'] }}" @if(!empty($tab['dot'])) data-nav-dot="{{ $tab['dot'] }}" @endif
                       class="shell-tab flex flex-col items-center justify-end gap-1 pb-2">
                        <span class="relative -mt-7 w-16 h-16 rounded-full grid place-items-center border-4 border-white transition-transform active:scale-95
                                     {{ $active ? 'bg-primary text-white' : 'bg-primary text-white' }}">
                            <i class="bi {{ $tab['icon'] }} text-2xl"></i>
                            <span class="nav-dot absolute top-0.5 right-0.5 w-3 h-3 rounded-full bg-red-500 ring-2 ring-white {{ $showDot ? '' : 'hidden' }}"></span>
                        </span>
                        <span class="text-[10px] font-semibold {{ $active ? 'text-primary' : 'text-muted-foreground' }}">{{ $tab['label'] }}</span>
                    </a>
                @else
                    <a href="{{ route($tab['route']) }}" data-shell-link data-route="{{ $tab['route'] }}" @if(!empty($tab['dot'])) data-nav-dot="{{ $tab['dot'] }}" @endif
                       class="relative shell-tab flex flex-col items-center justify-center gap-0.5 py-2.5 {{ $active ? 'is-active' : '' }}">
                        <span class="relative">
                            <i class="bi {{ $tab['icon'] }} text-lg"></i>
                            <span class="nav-dot absolute -top-1 -right-1.5 w-2.5 h-2.5 rounded-full bg-red-500 ring-2 ring-white {{ $showDot ? '' : 'hidden' }}"></span>
                        </span>
                        <span class="text-[10px] font-medium">{{ $tab['label'] }}</span>
                    </a>
                @endif
            @endforeach
        </div>
    </nav>

</div>

@once
<script>
    // Clear a bottom-tab's unseen dot the moment it's tapped (the shell swaps
    // content in place and doesn't re-render this nav), and mark it seen server-side.
    if (!window.__navDotInit) {
        window.__navDotInit = true;
        document.addEventListener('click', function (e) {
            const a = e.target.closest('a[data-nav-dot]');
            if (!a) return;
            const dot = a.querySelector('.nav-dot');
            if (!dot || dot.classList.contains('hidden')) return;
            dot.classList.add('hidden');
            const map = { feed: 'feed:all', challenge: 'challenge', events: 'events', market: 'market' };
            const section = map[a.getAttribute('data-nav-dot')];
            if (!section) return;
            fetch('{{ route('me.seen') }}', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content, 'Content-Type': 'application/json' },
                credentials: 'same-origin', body: JSON.stringify({ section }),
            }).catch(() => {});
        });
    }
</script>
@endonce

@include('partials.mobile-shell-nav')

{{-- Android app version/update helpers + drawer decoration (no-op in a browser). --}}
@include('partials.app-update')

{{-- Map runtime available shell-wide (Leaflet loads lazily, only when a map is built). --}}
@include('partials.location-map-runtime')
@endsection
