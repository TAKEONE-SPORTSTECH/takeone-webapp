{{-- Desktop equivalent of the mobile shell's bottom tab bar — the 5 "personal
     hub" destinations (Feed / Schedule / Challenge / Events / Market) have no
     top-nav presence on desktop otherwise. Sticky pill strip under the navbar,
     shared by every personal/desktop/* page so the section reads as one hub. --}}
@php
    $__subnavRoute = request()->route()?->getName();
    $__subnavDots = auth()->user() ? app(\App\Support\SectionActivity::class)->navDots(auth()->user()) : [];
    $__subnavTabs = [
        ['route' => 'me.home', 'icon' => 'bi-newspaper', 'label' => __('nav.tab_feed'), 'dot' => $__subnavDots['feed'] ?? false],
        ['route' => 'me.schedule', 'icon' => 'bi-calendar-week', 'label' => __('nav.tab_schedule'), 'dot' => false],
        ['route' => 'me.challenge', 'icon' => 'bi-trophy-fill', 'label' => __('nav.tab_challenge'), 'dot' => $__subnavDots['challenge'] ?? false],
        ['route' => 'me.events', 'icon' => 'bi-calendar-heart', 'label' => __('nav.tab_events'), 'dot' => $__subnavDots['events'] ?? false],
        ['route' => 'me.market', 'icon' => 'bi-shop', 'label' => __('nav.tab_market'), 'dot' => $__subnavDots['market'] ?? false],
    ];
@endphp
<nav class="sticky top-0 z-30 -mx-4 sm:-mx-6 lg:-mx-8 px-4 sm:px-6 lg:px-8 mb-6 bg-background/80 backdrop-blur border-b border-gray-100">
    <div class="flex items-center gap-1 overflow-x-auto scrollbar-hide">
        @foreach($__subnavTabs as $tab)
            @php $active = $__subnavRoute === $tab['route']; @endphp
            <a href="{{ route($tab['route']) }}"
               class="relative flex items-center gap-2 px-4 py-3.5 text-sm font-semibold border-b-2 -mb-px whitespace-nowrap transition-colors {{ $active ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground hover:border-gray-200' }}">
                <i class="bi {{ $tab['icon'] }}"></i>{{ $tab['label'] }}
                @if($tab['dot'] && ! $active)
                    <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>
                @endif
            </a>
        @endforeach
    </div>
</nav>
