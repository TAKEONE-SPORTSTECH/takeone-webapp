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
}

/* ── SIDEBAR ── */
#pa-sidebar {
    width: 256px;
    min-width: 256px;
    background: #fff;
    border-right: 1px solid #e5e7eb;
    box-shadow: 2px 0 8px rgba(0,0,0,0.04);
    display: flex;
    flex-direction: column;
    height: 100%;
    overflow: hidden;
    transition: width 0.22s ease, min-width 0.22s ease;
    flex-shrink: 0;
}
/* Collapsed = slim icon-only rail (icons stay clickable). */
#pa-sidebar.collapsed {
    width: 64px !important;
    min-width: 64px !important;
}
#pa-sidebar.collapsed .pa-collapse-hide { display: none !important; }
#pa-sidebar.collapsed #pa-sidebar-nav { padding-left: 8px; padding-right: 8px; }
#pa-sidebar.collapsed .nav-item { justify-content: center; padding-left: 0; padding-right: 0; }
#pa-sidebar.collapsed .nav-item > span:not(.ni) { display: none; }
#pa-sidebar.collapsed .nav-item.active::before { left: -8px; }

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
#pa-sidebar-toggle {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    background: transparent;
    border: 1px solid transparent;
    display: none;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    color: #6b7280;
    font-size: 16px;
    transition: background 0.15s, border-color 0.15s, color 0.15s;
}
#pa-sidebar-toggle:hover { background: #ede9fe; border-color: #ddd6fe; color: #7c3aed; }
@media (min-width: 1024px) { #pa-sidebar-toggle { display: flex; } }

/* ── SIDEBAR INTERNALS ── */
.pa-sb-btn {
    width:30px; height:30px; border-radius:7px; cursor:pointer;
    background:#f3f4f6; border:1px solid #e5e7eb;
    display:flex; align-items:center; justify-content:center;
    font-size:12px; color:#6b7280; transition:all 0.15s;
    text-decoration:none;
}
.pa-sb-btn:hover { background:#ede9fe; border-color:#8b5cf6; color:#7c3aed; }

#pa-sidebar .nav-item {
    display:flex; align-items:center; gap:8px;
    padding:7px 10px; border-radius:8px;
    font-size:12px; font-weight:600; letter-spacing:0.3px;
    color:#6b7280; text-transform:uppercase;
    transition:all 0.15s; border:1px solid transparent;
    position:relative; text-decoration:none !important;
    white-space:nowrap; overflow:hidden;
}
#pa-sidebar .nav-item:hover { background:#f3f4f6; color:#374151; }
#pa-sidebar .nav-item.active {
    background:#ede9fe; color:#7c3aed;
    border-color:#ddd6fe;
}
#pa-sidebar .nav-item.active::before {
    content:''; position:absolute; left:-8px; top:50%; transform:translateY(-50%);
    width:3px; height:60%; background:#7c3aed; border-radius:0 2px 2px 0;
}
#pa-sidebar .nav-item .ni { font-size:13px; width:15px; text-align:center; flex-shrink:0; }

#pa-sidebar-nav::-webkit-scrollbar { width:3px; }
#pa-sidebar-nav::-webkit-scrollbar-track { background:transparent; }
#pa-sidebar-nav::-webkit-scrollbar-thumb { background:#ddd6fe; border-radius:2px; }

.pa-sb-div { height:1px; margin:0 16px; background:#f3f4f6; flex-shrink:0; }

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
    $currentRoute = request()->route()->getName();
    $navItems = [
        ['route'=>'admin.platform.clubs',      'pattern'=>['admin.platform.clubs*','admin.platform.index'], 'icon'=>'bi-building',      'label'=>'All Clubs'],
        ['route'=>'admin.platform.members',    'pattern'=>['admin.platform.members*'],                       'icon'=>'bi-people',        'label'=>'All Members'],
        ['route'=>'admin.platform.businesses', 'pattern'=>['admin.platform.businesses*'],                    'icon'=>'bi-buildings',     'label'=>'Businesses'],
        ['route'=>'admin.platform.backup',     'pattern'=>['admin.platform.backup*'],                        'icon'=>'bi-database',      'label'=>'Backup & Restore'],
        ['route'=>'admin.platform.audit-log',  'pattern'=>['admin.platform.audit-log*'],                     'icon'=>'bi-journal-text',  'label'=>'Audit Log'],
        ['route'=>'admin.plugins.realtime.index', 'pattern'=>['admin.plugins.realtime*'],                    'icon'=>'bi-broadcast-pin', 'label'=>'Realtime / MQTT'],
    ];
    $isActive = function ($item) {
        return request()->routeIs(...$item['pattern']);
    };
@endphp

<div x-data="{ drawerOpen: false }" @keydown.escape.window="drawerOpen = false">

<!-- ── MOBILE TOP BAR (hamburger) ── -->
<div class="lg:hidden mobile-nav-bar sticky top-0 z-40 shadow-sm">
    <div class="flex items-center gap-3 px-4 py-2">
        <button type="button" @click="drawerOpen = true" title="Menu"
                class="pa-sb-btn" style="width:36px;height:36px;">
            <i class="bi bi-list" style="font-size:20px;"></i>
        </button>
        <span class="flex-1 truncate text-sm font-bold text-gray-900">Platform Admin</span>
        <a href="{{ route('clubs.explore') }}" class="pa-sb-btn" title="Back to Explore">←</a>
    </div>
</div>

<!-- ── MOBILE DRAWER BACKDROP ── -->
<div x-show="drawerOpen" x-cloak @click="drawerOpen = false"
     class="lg:hidden fixed inset-0 bg-black/50 z-50"
     x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"></div>

@push('navbar-left')
<button id="pa-sidebar-toggle" title="Toggle sidebar" onclick="paToggleSidebar()">
    <i class="bi bi-layout-sidebar-inset" id="pa-toggle-icon"></i>
</button>
@endpush

<!-- ── FULL-HEIGHT LAYOUT ── -->
<div id="pa-layout">

    <!-- ── SIDEBAR (static on desktop / drawer on mobile) ── -->
    <aside id="pa-sidebar" class="flex flex-col" :class="{ 'pa-drawer-open': drawerOpen }">

        <!-- Mobile close button -->
        <button type="button" @click="drawerOpen = false" title="Close menu"
                class="lg:hidden pa-sb-btn" style="position:absolute;top:12px;right:12px;width:32px;height:32px;z-index:2;">
            <i class="bi bi-x-lg"></i>
        </button>

        <!-- Navigation -->
        <nav id="pa-sidebar-nav" class="flex flex-col gap-0.5 px-2 py-2" style="overflow-y:auto;flex:1">
            @foreach($navItems as $item)
                @php $active = $isActive($item); @endphp
                <a href="{{ route($item['route']) }}" @click="drawerOpen = false" class="nav-item {{ $active ? 'active' : '' }}">
                    <span class="ni"><i class="bi {{ $item['icon'] }}"></i></span>
                    <span>{{ $item['label'] }}</span>
                </a>
            @endforeach
        </nav>

        <!-- Footer: Back to Explore -->
        <div class="pa-sb-div"></div>
        <div class="px-2 py-2" style="flex-shrink:0">
            <a href="{{ route('clubs.explore') }}" @click="drawerOpen = false" class="nav-item">
                <span class="ni"><i class="bi bi-eye"></i></span>
                <span>Back to Explore</span>
            </a>
        </div>
    </aside>

    <!-- ── MAIN CONTENT ── -->
    <main id="pa-main-area">
        @yield('admin-content')
    </main>

</div>{{-- #pa-layout --}}

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
