@extends('layouts.app')

@push('styles')
<style>
/* ══ PLATFORM ADMIN — FULL-HEIGHT LAYOUT (desktop only) ══
   On desktop the page does NOT scroll: only #pa-main-area scrolls internally,
   so the sidebar stays pinned to the top bar.
   On mobile the page scrolls normally (the sidebar is hidden and a horizontal
   nav bar is used instead), so we must NOT lock body height there.
   (Mirrors the club-admin layout pattern.)
*/
#pa-layout {
    display: flex;
    flex-direction: column;
}

@media (min-width: 1024px) {
    html, body { overflow: hidden !important; height: 100% !important; }

    #pa-layout {
        flex-direction: row;
        height: calc(100vh - 64px); /* 64px = h-16 app navbar */
        overflow: hidden;
    }

    /* Full-bleed shell on lg+, so un-cap the app top bar (it otherwise centers at
       `container`/1280px) — aligns the logo with the sidebar and icons with the content. */
    .to-bar > .container { max-width: none; }
}

/* ── SIDEBAR ── */
#pa-sidebar {
    width: 268px;
    min-width: 268px;
    background: linear-gradient(180deg, #ffffff 0%, hsl(250 40% 99%) 100%);
    border-right: 1px solid hsl(250 30% 92%);
    box-shadow: 2px 0 14px rgba(76, 60, 140, 0.05);
    display: flex;
    flex-direction: column;
    height: 100%;
    overflow: hidden;
    transition: width 0.22s ease, min-width 0.22s ease;
    flex-shrink: 0;
}
/* Collapsed = slim icon-only rail (icons stay clickable). */
#pa-sidebar.collapsed {
    width: 76px !important;
    min-width: 76px !important;
}
#pa-sidebar.collapsed .pa-collapse-hide { display: none !important; }
#pa-sidebar.collapsed #pa-sidebar-nav { padding-left: 10px; padding-right: 10px; }
#pa-sidebar.collapsed .nav-item { justify-content: center; padding-left: 0; padding-right: 0; gap: 0; }
#pa-sidebar.collapsed .nav-item > span:not(.ni) { display: none; }
#pa-sidebar.collapsed .pa-brand { justify-content: center; padding-left: 0; padding-right: 0; }
#pa-sidebar.collapsed .pa-foot { padding-left: 10px; padding-right: 10px; }

/* ── MAIN CONTENT ── */
#pa-main-area {
    flex: 1;
    min-width: 0;
    padding: 16px;
}
@media (min-width: 1024px) {
    #pa-main-area {
        overflow-y: auto;
        height: 100%;
        padding: 20px 16px;
    }
}

/* ── TOGGLE BUTTON ── */
/* Match the .nav-icon-btn token used by the other top-bar icons (compass/chat/bell). */
#pa-sidebar-toggle {
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 0.75rem;
    background: transparent;
    border: 0;
    display: none;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    color: hsl(220 9% 46%);
    font-size: 1.25rem;
    transition: background-color .18s ease, color .18s ease, transform .18s cubic-bezier(.22,.61,.36,1);
}
#pa-sidebar-toggle:hover { background: hsl(250 60% 92%); color: hsl(var(--primary)); transform: translateY(-1px); }
#pa-sidebar-toggle:active { transform: scale(.92); }
@media (min-width: 1024px) { #pa-sidebar-toggle { display: flex; } }

/* ── SIDEBAR INTERNALS ── */
.pa-sb-btn {
    width:30px; height:30px; border-radius:7px; cursor:pointer;
    background:#f3f4f6; border:1px solid #e5e7eb;
    display:flex; align-items:center; justify-content:center;
    font-size:12px; color:#6b7280; transition:all 0.15s;
    text-decoration:none;
}
.pa-sb-btn:hover { background:hsl(250 60% 95%); border-color:hsl(250 65% 65%); color:hsl(250 55% 55%); }

/* Brand / identity header */
.pa-brand {
    display:flex; align-items:center; gap:11px;
    padding:16px 16px 14px;
    flex-shrink:0;
}
.pa-brand-mark {
    width:40px; height:40px; border-radius:12px; flex-shrink:0;
    display:flex; align-items:center; justify-content:center;
    background:linear-gradient(135deg, hsl(250 65% 66%), hsl(262 60% 56%));
    color:#fff; font-size:19px;
    box-shadow:0 6px 16px -4px hsl(250 65% 60% / 0.6);
}
.pa-brand-title { font-size:14.5px; font-weight:800; color:#1f2937; line-height:1.15; display:block; }
.pa-brand-sub   { font-size:11px; font-weight:600; color:hsl(250 12% 62%); display:block; margin-top:1px; }

/* Group eyebrow labels */
.pa-group {
    font-size:10.5px; font-weight:800; letter-spacing:0.9px;
    text-transform:uppercase; color:hsl(250 10% 66%);
    padding:0 12px; margin:16px 0 7px;
}
.pa-group:first-child { margin-top:2px; }

/* Nav items */
#pa-sidebar .nav-item {
    display:flex; align-items:center; gap:11px;
    padding:7px 9px; border-radius:12px;
    font-size:13px; font-weight:600; letter-spacing:0.1px;
    color:#475569; text-transform:none;
    transition:all 0.16s ease; border:1px solid transparent;
    position:relative; text-decoration:none !important;
    white-space:nowrap; overflow:hidden;
}
#pa-sidebar .nav-item:hover { background:hsl(220 18% 96%); color:#1f2937; }
#pa-sidebar .nav-item:hover .ni { background:hsl(250 60% 93%); color:hsl(250 55% 55%); }
#pa-sidebar .nav-item.active {
    background:hsl(250 65% 96%);
    color:hsl(250 48% 42%);
    border-color:hsl(250 60% 90%);
    font-weight:700;
}
/* Icon tile */
#pa-sidebar .nav-item .ni {
    width:32px; height:32px; border-radius:9px; flex-shrink:0;
    display:flex; align-items:center; justify-content:center;
    background:hsl(220 16% 95%); color:#64748b;
    font-size:14px; transition:all 0.16s ease;
}
#pa-sidebar .nav-item.active .ni {
    background:linear-gradient(135deg, hsl(250 65% 66%), hsl(262 60% 56%));
    color:#fff; box-shadow:0 5px 12px -3px hsl(250 65% 60% / 0.55);
}
/* Active dot indicator (right) */
.nav-dot {
    margin-left:auto; width:7px; height:7px; border-radius:50%;
    background:hsl(250 65% 62%); opacity:0; transform:scale(0.4);
    transition:all 0.2s ease;
}
#pa-sidebar .nav-item.active .nav-dot { opacity:1; transform:scale(1); }

#pa-sidebar-nav::-webkit-scrollbar { width:4px; }
#pa-sidebar-nav::-webkit-scrollbar-track { background:transparent; }
#pa-sidebar-nav::-webkit-scrollbar-thumb { background:hsl(250 50% 88%); border-radius:2px; }

/* Footer */
.pa-foot { padding:10px 12px 14px; flex-shrink:0; }
.pa-sb-div { height:1px; margin:0 14px; background:hsl(250 30% 92%); flex-shrink:0; }
.pa-user {
    display:flex; align-items:center; gap:10px;
    padding:9px 10px; margin-bottom:8px; border-radius:12px;
    background:hsl(250 40% 97%); border:1px solid hsl(250 35% 93%);
}
.pa-user-av {
    width:32px; height:32px; border-radius:9px; flex-shrink:0;
    display:flex; align-items:center; justify-content:center;
    background:linear-gradient(135deg, hsl(250 65% 66%), hsl(262 60% 56%));
    color:#fff; font-size:12px; font-weight:800; letter-spacing:0.3px;
}
.pa-user-name { font-size:12.5px; font-weight:700; color:#374151; line-height:1.15; }
.pa-user-role { font-size:10.5px; font-weight:600; color:hsl(250 10% 64%); display:flex; align-items:center; gap:4px; }
.pa-user-role::before { content:''; width:6px; height:6px; border-radius:50%; background:#22c55e; box-shadow:0 0 0 2px #dcfce7; }
.pa-explore { border:1px solid hsl(250 35% 90%) !important; }
.pa-explore:hover { background:hsl(250 65% 96%) !important; color:hsl(250 48% 42%) !important; border-color:hsl(250 60% 82%) !important; }

/* Mobile top bar */
.mobile-nav-bar { background:#fff; border-bottom:1px solid #e5e7eb; }

/* ── MOBILE: sidebar becomes a slide-in drawer ── */
#pa-sidebar {
    position: fixed;
    top: 0; left: 0; bottom: 0;
    z-index: 60;
    transform: translateX(-100%);
    transition: transform 0.25s ease;
}
#pa-sidebar.pa-drawer-open { transform: translateX(0); }

@media (min-width: 1024px) {
    /* Desktop: static, in-flow sidebar (drawer transform disabled). */
    #pa-sidebar {
        position: static;
        transform: none;
        z-index: auto;
        height: 100%;
    }
}
</style>
@endpush

@section('content')
@php
    $admin     = auth()->user();
    $adminName = $admin?->name ?: 'Super Admin';
    $initials  = collect(explode(' ', trim($adminName)))
        ->filter()
        ->take(2)
        ->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))
        ->implode('') ?: 'SA';

    $navGroups = [
        'Management' => [
            ['route'=>'admin.platform.clubs',      'pattern'=>['admin.platform.clubs*'],     'icon'=>'bi-building',  'label'=>__('nav.layouts_admin_nav_all_clubs')],
            ['route'=>'admin.platform.members',    'pattern'=>['admin.platform.members*'],    'icon'=>'bi-people',    'label'=>__('nav.layouts_admin_nav_all_members')],
            ['route'=>'admin.platform.businesses', 'pattern'=>['admin.platform.businesses*'], 'icon'=>'bi-buildings', 'label'=>__('nav.layouts_admin_nav_businesses')],
        ],
        'System' => [
            ['route'=>'admin.platform.activities',    'pattern'=>['admin.platform.activities*'],'icon'=>'bi-lightning-charge','label'=>'Activities'],
            ['route'=>'admin.ai.index',               'pattern'=>['admin.ai.*'],                'icon'=>'bi-robot',         'label'=>'AI Providers'],
            ['route'=>'admin.platform.settings',      'pattern'=>['admin.platform.settings*'],  'icon'=>'bi-gear',          'label'=>__('nav.layouts_admin_nav_settings')],
            ['route'=>'admin.platform.backup',        'pattern'=>['admin.platform.backup*'],    'icon'=>'bi-database',      'label'=>__('nav.layouts_admin_nav_backup')],
            ['route'=>'admin.platform.audit-log',     'pattern'=>['admin.platform.audit-log*'], 'icon'=>'bi-journal-text',  'label'=>__('nav.layouts_admin_nav_audit_log')],
        ],
    ];

    // Desktop-only Dashboard entry (mobile keeps its own dedicated dashboard, untouched).
    if (! ($isMobile ?? false)) {
        array_unshift($navGroups['Management'], [
            'route' => 'admin.platform.index', 'pattern' => ['admin.platform.index'],
            'icon'  => 'bi-grid-1x2-fill', 'label' => __('nav.layouts_admin_nav_dashboard'),
        ]);
    }

    $isActive = fn ($item) => request()->routeIs(...$item['pattern']);
@endphp

<div x-data="{ drawerOpen: false }" @keydown.escape.window="drawerOpen = false">

<!-- ── MOBILE TOP BAR (hamburger) ── -->
<div class="lg:hidden mobile-nav-bar sticky top-0 z-40 shadow-sm">
    <div class="flex items-center gap-3 px-4 py-2">
        <button type="button" @click="drawerOpen = true" title="{{ __('nav.layouts_admin_menu') }}"
                class="pa-sb-btn" style="width:36px;height:36px;">
            <i class="bi bi-list" style="font-size:20px;"></i>
        </button>
        <span class="flex-1 truncate text-sm font-bold text-gray-900">{{ __('nav.layouts_admin_platform_admin') }}</span>
        <a href="{{ route('clubs.explore') }}" class="pa-sb-btn" title="{{ __('nav.layouts_admin_back_to_explore') }}">←</a>
    </div>
</div>

<!-- ── MOBILE DRAWER BACKDROP ── -->
<div x-show="drawerOpen" x-cloak @click="drawerOpen = false"
     class="lg:hidden fixed inset-0 bg-black/50 z-50"
     x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"></div>

@push('navbar-left')
<button id="pa-sidebar-toggle" title="{{ __('nav.layouts_admin_toggle_sidebar') }}" onclick="paToggleSidebar()">
    <i class="bi bi-layout-sidebar-inset" id="pa-toggle-icon"></i>
</button>
@endpush

<!-- ── FULL-HEIGHT LAYOUT ── -->
<div id="pa-layout">

    <!-- ── SIDEBAR (static on desktop / drawer on mobile) ── -->
    <aside id="pa-sidebar" class="flex flex-col" :class="{ 'pa-drawer-open': drawerOpen }">

        <!-- Mobile close button -->
        <button type="button" @click="drawerOpen = false" title="{{ __('nav.layouts_admin_close_menu') }}"
                class="lg:hidden pa-sb-btn" style="position:absolute;top:14px;right:14px;width:32px;height:32px;z-index:2;">
            <i class="bi bi-x-lg"></i>
        </button>

        <!-- Brand / identity -->
        <div class="pa-brand">
            <span class="pa-brand-mark"><i class="bi bi-shield-lock-fill"></i></span>
            <span class="pa-collapse-hide">
                <span class="pa-brand-title">{{ __('nav.layouts_admin_platform_admin') }}</span>
                <span class="pa-brand-sub">{{ __('nav.layouts_admin_super_admin_console') }}</span>
            </span>
        </div>

        <!-- Navigation -->
        <nav id="pa-sidebar-nav" class="flex flex-col px-3 py-1" style="overflow-y:auto;flex:1">
            @foreach($navGroups as $groupLabel => $items)
                <p class="pa-group pa-collapse-hide">{{ __('nav.layouts_admin_group_' . strtolower($groupLabel)) }}</p>
                @foreach($items as $item)
                    @php $active = $isActive($item); @endphp
                    <a href="{{ route($item['route']) }}" @click="drawerOpen = false"
                       data-shell-link data-route="{{ $item['route'] }}"
                       title="{{ $item['label'] }}"
                       class="nav-item {{ $active ? 'active' : '' }}" style="margin-bottom:2px">
                        <span class="ni"><i class="bi {{ $item['icon'] }}"></i></span>
                        <span>{{ $item['label'] }}</span>
                        <span class="nav-dot"></span>
                    </a>
                @endforeach
            @endforeach
        </nav>

        <!-- Footer: identity + Back to Explore -->
        <div class="pa-sb-div"></div>
        <div class="pa-foot">
            <div class="pa-user pa-collapse-hide">
                <span class="pa-user-av">{{ $initials }}</span>
                <span style="min-width:0;flex:1">
                    <span class="pa-user-name truncate" style="display:block">{{ $adminName }}</span>
                    <span class="pa-user-role">{{ __('nav.layouts_admin_super_admin') }}</span>
                </span>
            </div>
            <a href="{{ route('clubs.explore') }}" @click="drawerOpen = false"
               title="{{ __('nav.layouts_admin_back_to_explore') }}" class="nav-item pa-explore">
                <span class="ni"><i class="bi bi-box-arrow-left"></i></span>
                <span>{{ __('nav.layouts_admin_back_to_explore') }}</span>
            </a>
        </div>
    </aside>

    <!-- ── MAIN CONTENT ── -->
    <main id="pa-main-area" data-shell-main="platform" data-shell-base="{{ url('/admin') }}" data-route="{{ request()->route()?->getName() }}">
        @yield('admin-content')
    </main>

</div>{{-- #pa-layout --}}

@include('partials.admin-shell-nav')

{{-- Copilot ("Coach") — AI assistant, mounted outside <main> so it persists across SPA nav --}}
<x-copilot context="create_club" :hide-on="['admin.ai.index']" />

</div>{{-- x-data --}}

@push('scripts')
<script>
function paApplySidebar(collapsed) {
    var sb  = document.getElementById('pa-sidebar');
    var ico = document.getElementById('pa-toggle-icon');
    if (sb)  sb.classList.toggle('collapsed', collapsed);
    if (ico) ico.className = collapsed ? 'bi bi-layout-sidebar-inset-reverse' : 'bi bi-layout-sidebar-inset';
}
function paToggleSidebar() {
    var collapsed = !document.getElementById('pa-sidebar').classList.contains('collapsed');
    try { localStorage.setItem('paSidebarCollapsed', collapsed ? '1' : '0'); } catch (e) {}
    paApplySidebar(collapsed);
}
// Restore persisted state (sidebar starts hidden until lg via Tailwind, so no FOUC of the rail).
document.addEventListener('DOMContentLoaded', function () {
    var collapsed = false;
    try { collapsed = localStorage.getItem('paSidebarCollapsed') === '1'; } catch (e) {}
    if (collapsed) paApplySidebar(true);
});
</script>
@endpush

@endsection
