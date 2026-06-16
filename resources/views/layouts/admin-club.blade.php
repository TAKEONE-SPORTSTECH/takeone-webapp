@extends('layouts.app')

@push('styles')
<style>
/* ══ ADMIN CLUB — FULL-HEIGHT LAYOUT ══
   The page does NOT scroll. Only #emp-main-area scrolls internally.
   This guarantees the sidebar never separates from the top bar.
*/
html, body { overflow: hidden !important; height: 100% !important; }

#emp-layout {
    display: flex;
    height: calc(100vh - 64px); /* 64px = h-16 app navbar */
    overflow: hidden;
}

/* ── SIDEBAR ── */
#emp-sidebar {
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
#emp-sidebar.collapsed {
    width: 0 !important;
    min-width: 0 !important;
    border-right: none;
    box-shadow: none;
}

/* ── MAIN CONTENT ── */
#emp-main-area {
    flex: 1;
    min-width: 0;
    overflow-y: auto;
    height: 100%;
    padding: 20px 16px;
}

/* ── TOGGLE BUTTON ── */
#emp-sidebar-toggle {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    background: transparent;
    border: 1px solid transparent;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    color: #6b7280;
    font-size: 16px;
    transition: background 0.15s, border-color 0.15s, color 0.15s;
}
#emp-sidebar-toggle:hover { background: #ede9fe; border-color: #ddd6fe; color: #7c3aed; }

/* ── SIDEBAR INTERNALS ── */
.emp-sanctum-ttl {
    font-size:8px; font-weight:600;
    letter-spacing:2px; color:#7c3aed; text-align:center;
    text-transform:uppercase; padding:14px 16px 6px;
    white-space:nowrap;
}
.emp-seal-name .big {
    display:block; font-size:12px; font-weight:700;
    color:#111827; letter-spacing:0.5px; text-align:center;
}
.emp-seal-name .sm {
    display:block; font-size:8px; font-weight:600;
    color:#7c3aed; letter-spacing:1px; text-align:center; text-transform:uppercase;
}
.emp-sb-btn {
    width:30px; height:30px; border-radius:7px; cursor:pointer;
    background:#f3f4f6; border:1px solid #e5e7eb;
    display:flex; align-items:center; justify-content:center;
    font-size:12px; color:#6b7280; transition:all 0.15s;
    text-decoration:none;
}
.emp-sb-btn:hover { background:#ede9fe; border-color:#8b5cf6; color:#7c3aed; }

#emp-sidebar .nav-item {
    display:flex; align-items:center; gap:8px;
    padding:7px 10px; border-radius:8px;
    font-size:12px; font-weight:600; letter-spacing:0.3px;
    color:#6b7280; text-transform:uppercase;
    transition:all 0.15s; border:1px solid transparent;
    position:relative; text-decoration:none !important;
    white-space:nowrap; overflow:hidden;
}
#emp-sidebar .nav-item:hover { background:#f3f4f6; color:#374151; }
#emp-sidebar .nav-item.active {
    background:#ede9fe; color:#7c3aed;
    border-color:#ddd6fe;
}
#emp-sidebar .nav-item.active::before {
    content:''; position:absolute; left:-8px; top:50%; transform:translateY(-50%);
    width:3px; height:60%; background:#7c3aed; border-radius:0 2px 2px 0;
}
#emp-sidebar .nav-item .ni { font-size:13px; width:15px; text-align:center; flex-shrink:0; }

#emp-sidebar-nav::-webkit-scrollbar { width:3px; }
#emp-sidebar-nav::-webkit-scrollbar-track { background:transparent; }
#emp-sidebar-nav::-webkit-scrollbar-thumb { background:#ddd6fe; border-radius:2px; }

.emp-sb-div { height:1px; margin:0 16px; background:#f3f4f6; flex-shrink:0; }

/* Financials tooltip */
.emp-has-tip { position:relative; }
.emp-tip-box {
    position:absolute; left:calc(100% + 8px); top:50%; transform:translateY(-50%);
    background:#fff; border:1px solid #e5e7eb; border-radius:10px;
    padding:10px 13px; width:150px;
    opacity:0; pointer-events:none; transition:opacity 0.2s; z-index:300;
    box-shadow:0 8px 24px rgba(0,0,0,0.1);
}
.emp-has-tip:hover .emp-tip-box { opacity:1; }
.emp-tip-ttl { font-size:8px; color:#7c3aed; letter-spacing:1px; margin-bottom:7px; text-transform:uppercase; font-weight:700; }
.emp-tip-row { display:flex; justify-content:space-between; font-size:11px; color:#9ca3af; margin-bottom:3px; }
.emp-tip-row .v  { color:#374151; font-weight:600; }
.emp-tip-row .vp { color:#16a34a; }
.emp-tip-row .vn { color:#dc2626; }
.emp-tip-tot { border-top:1px solid #f3f4f6; padding-top:5px; margin-top:5px; display:flex; justify-content:space-between; font-size:11px; font-weight:600; }
.emp-tip-tot .v { color:#7c3aed; font-weight:700; }

</style>
@endpush

@section('content')
@php
    $clubId = $club->slug ?? $club->id ?? null;
    $currentRoute = request()->route()->getName();
    $navItems = [
        ['route'=>'admin.club.dashboard',    'icon'=>'bi-speedometer2',   'label'=>'Dashboard'],
        ['route'=>'admin.club.members',      'icon'=>'bi-person-plus',    'label'=>'Members'],
        ['route'=>'admin.club.financials',   'icon'=>'bi-currency-dollar','label'=>'Financials'],
        ['route'=>'admin.club.details',      'icon'=>'bi-building',       'label'=>'Club Details'],
        ['route'=>'admin.club.facilities',   'icon'=>'bi-geo-alt',        'label'=>'Facilities'],
        ['route'=>'admin.club.instructors',  'icon'=>'bi-people',         'label'=>'Instructors'],
        ['route'=>'admin.club.activities',   'icon'=>'bi-activity',       'label'=>'Activities'],
        ['route'=>'admin.club.events',       'icon'=>'bi-calendar-event', 'label'=>'Events'],
        ['route'=>'admin.club.timeline',     'icon'=>'bi-newspaper',      'label'=>'Timeline'],
        ['route'=>'admin.club.perks',        'icon'=>'bi-gift',           'label'=>'Perks'],
        ['route'=>'admin.club.achievements', 'icon'=>'bi-trophy',         'label'=>'Achievements'],
        ['route'=>'admin.club.packages',     'icon'=>'bi-box',            'label'=>'Packages'],
        ['route'=>'admin.club.gallery',      'icon'=>'bi-images',         'label'=>'Gallery'],
        ['route'=>'admin.club.roles',        'icon'=>'bi-shield-check',   'label'=>'Roles'],
        ['route'=>'admin.club.messages',     'icon'=>'bi-chat-dots',      'label'=>'Messages'],
        ['route'=>'admin.club.notifications','icon'=>'bi-bell',           'label'=>'Notifications'],
        ['route'=>'admin.club.analytics',    'icon'=>'bi-bar-chart',      'label'=>'Analytics'],
    ];
@endphp

<div x-data="{ showNotificationModal: false }">

@push('navbar-left')
<button id="emp-sidebar-toggle" title="Toggle sidebar" onclick="empToggleSidebar()">
    <i class="bi bi-layout-sidebar-inset" id="emp-toggle-icon"></i>
</button>
@endpush

<!-- ── FULL-HEIGHT LAYOUT ── -->
<div id="emp-layout">

    <!-- ── SIDEBAR ── -->
    <aside id="emp-sidebar" class="flex flex-col">

        <!-- Club Logo + Name -->
        <div class="flex flex-col items-center px-4 pb-2 gap-2" style="flex-shrink:0;padding-top:16px;">
            @if($club->logo)
                <img src="{{ asset('storage/'.$club->logo) }}"
                     alt="{{ $club->club_name }}"
                     style="width:90px;height:90px;object-fit:contain;border-radius:12px;">
            @else
                <div style="width:90px;height:90px;border-radius:12px;background:linear-gradient(135deg,#ede9fe,#ddd6fe);border:1.5px solid rgba(124,58,237,0.2);display:flex;align-items:center;justify-content:center;">
                    <span style="font-size:28px;font-weight:800;color:#7c3aed;letter-spacing:-1px;line-height:1;">
                        {{ strtoupper(substr($club->club_name, 0, 2)) }}
                    </span>
                </div>
            @endif

            <p style="font-size:12px;font-weight:700;color:#3b0764;text-align:center;line-height:1.3;letter-spacing:0.03em;margin:0;">{{ $club->club_name }}</p>
        </div>

        <!-- Action buttons -->
        <div class="flex justify-center gap-2 px-4 pb-3" style="flex-shrink:0">
            @if(Auth::user()->isSuperAdmin())
                <a href="{{ route('admin.platform.clubs') }}" class="emp-sb-btn" title="Back to Clubs">←</a>
            @else
                <a href="{{ route('clubs.explore') }}" class="emp-sb-btn" title="Back to Explore">←</a>
            @endif
            <a href="{{ $club->url }}" class="emp-sb-btn" title="Preview Club" target="_blank">👁</a>
            <button @click="showNotificationModal = true" class="emp-sb-btn" title="Send Notification">✈</button>
        </div>

        <div class="emp-sb-div"></div>

        <!-- Navigation -->
        <nav id="emp-sidebar-nav" class="flex flex-col gap-0.5 px-2 py-2" style="overflow-y:auto;flex:1">
            @foreach($navItems as $item)
                @php $active = $currentRoute === $item['route']; @endphp
                @if($item['route'] === 'admin.club.financials')
                    <a href="{{ route($item['route'], $clubId) }}" class="nav-item emp-has-tip {{ $active ? 'active' : '' }}">
                        <span class="ni"><i class="bi {{ $item['icon'] }}"></i></span>
                        <span>{{ $item['label'] }}</span>
                        <div class="emp-tip-box">
                            <div class="emp-tip-ttl">Cash Flow</div>
                            <div class="emp-tip-row"><span>Income</span><span class="v vp">BHD 250</span></div>
                            <div class="emp-tip-row"><span>Expenses</span><span class="v vn">BHD -200</span></div>
                            <div class="emp-tip-tot"><span>Total</span><span class="v">BHD 50</span></div>
                        </div>
                    </a>
                @else
                    <a href="{{ route($item['route'], $clubId) }}" class="nav-item {{ $active ? 'active' : '' }}">
                        <span class="ni"><i class="bi {{ $item['icon'] }}"></i></span>
                        <span>{{ $item['label'] }}</span>
                    </a>
                @endif
            @endforeach
        </nav>
    </aside>

    <!-- ── MAIN CONTENT ── -->
    <main id="emp-main-area">
        @yield('club-admin-content')
    </main>

</div>{{-- #emp-layout --}}

@include('admin.club.notifications.send-modal')

</div>{{-- x-data --}}

@push('scripts')
<script>
var empSidebarOpen = true;
function empToggleSidebar() {
    var sb  = document.getElementById('emp-sidebar');
    var btn = document.getElementById('emp-sidebar-toggle');
    var ico = document.getElementById('emp-toggle-icon');
    empSidebarOpen = !empSidebarOpen;
    sb.classList.toggle('collapsed', !empSidebarOpen);
    ico.className = empSidebarOpen ? 'bi bi-layout-sidebar-inset' : 'bi bi-layout-sidebar-inset-reverse';
}
</script>
@endpush

@endsection
