{{-- New UI phone bottom tabs (replaces the classic bar while NewUi is active).
     Keeps the hooks the shell navigator and the red-dot handler rely on:
     .shell-tab + data-shell-link + data-route (active state), data-nav-dot +
     .nav-dot (unseen dots). Needs $bottomTabs, $currentRoute, $navDots. --}}
<nav class="t1 t1-tabs lg:hidden" aria-label="{{ __('nav.tab_feed') }}">
    @foreach($bottomTabs as $tab)
        @php
            $active = $currentRoute === $tab['route'];
            $showDot = ! empty($tab['dot']) && ! $active && ($navDots[$tab['dot']] ?? false);
        @endphp
        <a href="{{ route($tab['route']) }}" data-shell-link data-route="{{ $tab['route'] }}" @if(!empty($tab['dot'])) data-nav-dot="{{ $tab['dot'] }}" @endif
           class="shell-tab {{ ($tab['center'] ?? false) ? 't1-raise' : '' }} {{ $active ? 'is-active' : '' }}">
            @if($tab['center'] ?? false)
                <span class="t1-r"><i class="bi {{ $tab['icon'] }}"></i></span>
            @else
                <i class="bi {{ $tab['icon'] }}"></i>
            @endif
            <span class="nav-dot t1-dot {{ $showDot ? '' : 'hidden' }}"></span>
            <span>{{ $tab['label'] }}</span>
        </a>
    @endforeach
</nav>
