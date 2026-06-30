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
#emp-sidebar-toggle:hover { background: hsl(250 60% 95%); border-color: hsl(250 60% 88%); color: hsl(250 55% 55%); }

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
/* Club brand header */
.emp-brand {
    flex-shrink:0; padding:18px 16px 12px;
    display:flex; flex-direction:column; align-items:center; gap:9px;
    background:linear-gradient(180deg, hsl(250 50% 98%) 0%, #ffffff 100%);
    border-bottom:1px solid hsl(250 35% 93%);
}
.emp-brand-logo {
    width:82px; height:82px; border-radius:18px;
    background:#fff; padding:7px;
    display:flex; align-items:center; justify-content:center;
    box-shadow:0 6px 18px hsl(250 65% 60% / 0.18), 0 0 0 1px hsl(250 60% 85% / 0.6);
}
.emp-brand-logo img { width:100%; height:100%; object-fit:contain; border-radius:12px; }
.emp-brand-logo .ph {
    width:100%; height:100%; border-radius:12px;
    background:linear-gradient(135deg, hsl(250 60% 92%), hsl(255 55% 86%));
    display:flex; align-items:center; justify-content:center;
    font-size:26px; font-weight:800; color:hsl(250 55% 52%); letter-spacing:-1px; line-height:1;
}
.emp-brand-eyebrow {
    font-size:8px; font-weight:700; letter-spacing:2px; text-transform:uppercase;
    color:hsl(250 50% 70%); line-height:1;
}
.emp-brand-name {
    font-size:12.5px; font-weight:700; color:hsl(250 45% 30%);
    text-align:center; line-height:1.3; letter-spacing:0.02em; margin:0;
}

/* Action segmented bar */
.emp-actions {
    flex-shrink:0; display:flex; justify-content:center; gap:6px;
    padding:12px 16px 14px;
}
.emp-sb-btn {
    width:34px; height:34px; border-radius:10px; cursor:pointer;
    background:hsl(250 30% 97%); border:1px solid hsl(250 30% 93%);
    display:flex; align-items:center; justify-content:center;
    font-size:14px; color:#6b7280; transition:all 0.16s ease;
    text-decoration:none;
}
.emp-sb-btn:hover {
    background:hsl(250 60% 95%); border-color:hsl(250 60% 82%); color:hsl(250 55% 53%);
    transform:translateY(-1px);
    box-shadow:0 4px 10px hsl(250 65% 60% / 0.2);
}
.emp-sb-btn.active {
    background:hsl(250 65% 65%); border-color:hsl(250 65% 65%); color:#fff;
    box-shadow:0 4px 12px hsl(250 65% 60% / 0.4);
}
.emp-sb-btn.active:hover { background:hsl(250 60% 58%); color:#fff; transform:translateY(-1px); }

#emp-sidebar .nav-item {
    display:flex; align-items:center; gap:11px;
    padding:7px 9px; border-radius:12px;
    font-size:13px; font-weight:600; letter-spacing:0.1px;
    color:#475569; text-transform:none;
    transition:all 0.16s ease; border:1px solid transparent;
    position:relative; text-decoration:none !important;
    white-space:nowrap; overflow:hidden;
}
#emp-sidebar .nav-item:hover { background:hsl(220 18% 96%); color:#1f2937; }
#emp-sidebar .nav-item:hover .ni { background:hsl(250 60% 93%); color:hsl(250 55% 55%); }
#emp-sidebar .nav-item.active {
    background:hsl(250 65% 96%); color:hsl(250 48% 42%);
    border-color:hsl(250 60% 90%); font-weight:700;
}
/* Icon tile */
#emp-sidebar .nav-item .ni {
    width:32px; height:32px; border-radius:9px; flex-shrink:0;
    display:flex; align-items:center; justify-content:center;
    background:hsl(220 16% 95%); color:#64748b;
    font-size:14px; transition:all 0.16s ease;
}
#emp-sidebar .nav-item.active .ni {
    background:linear-gradient(135deg, hsl(250 65% 66%), hsl(262 60% 56%));
    color:#fff; box-shadow:0 5px 12px -3px hsl(250 65% 60% / 0.55);
}
/* Active dot indicator (right) */
#emp-sidebar .nav-dot {
    margin-left:auto; width:7px; height:7px; border-radius:50%;
    background:hsl(250 65% 62%); opacity:0; transform:scale(0.4);
    transition:all 0.2s ease;
}
#emp-sidebar .nav-item.active .nav-dot { opacity:1; transform:scale(1); }

/* Nav groups */
.emp-nav-grp { display:flex; flex-direction:column; gap:2px; }
.emp-nav-grp + .emp-nav-grp { margin-top:14px; }
.emp-nav-grp-ttl {
    font-size:10.5px; font-weight:800; letter-spacing:0.9px; text-transform:uppercase;
    color:hsl(250 10% 66%); margin:0 0 7px; padding:0 12px;
}

#emp-sidebar-nav::-webkit-scrollbar { width:4px; }
#emp-sidebar-nav::-webkit-scrollbar-track { background:transparent; }
#emp-sidebar-nav::-webkit-scrollbar-thumb { background:hsl(250 50% 88%); border-radius:2px; }

.emp-sb-div { height:1px; margin:0 16px; background:hsl(250 30% 92%); flex-shrink:0; }

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
.emp-tip-ttl { font-size:8px; color:hsl(250 55% 53%); letter-spacing:1px; margin-bottom:7px; text-transform:uppercase; font-weight:700; }
.emp-tip-row { display:flex; justify-content:space-between; font-size:11px; color:#9ca3af; margin-bottom:3px; }
.emp-tip-row .v  { color:#374151; font-weight:600; }
.emp-tip-row .vp { color:#16a34a; }
.emp-tip-row .vn { color:#dc2626; }
.emp-tip-tot { border-top:1px solid #f3f4f6; padding-top:5px; margin-top:5px; display:flex; justify-content:space-between; font-size:11px; font-weight:600; }
.emp-tip-tot .v { color:hsl(250 55% 53%); font-weight:700; }

</style>
@endpush

@section('content')
@php
    $clubId = $club->slug ?? $club->id ?? null;
    $currentRoute = request()->route()->getName();
    $clubPublicUrl = \App\Http\Controllers\QrController::clubPageUrl($club);
    $navGroups = [
        ['label'=>'Overview', 'items'=>[
            ['route'=>'admin.club.dashboard',    'icon'=>'bi-speedometer2',   'label'=>'Dashboard'],
            ['route'=>'admin.club.analytics',    'icon'=>'bi-bar-chart',      'label'=>'Analytics'],
            ['route'=>'admin.club.financials',   'icon'=>'bi-currency-dollar','label'=>'Financials'],
        ]],
        ['label'=>'People', 'items'=>[
            ['route'=>'admin.club.members',      'icon'=>'bi-person-plus',    'label'=>'Members'],
            ['route'=>'admin.club.instructors',  'icon'=>'bi-people',         'label'=>'Instructors'],
            ['route'=>'admin.club.roles',        'icon'=>'bi-shield-check',   'label'=>'Roles'],
        ]],
        ['label'=>'Programs', 'items'=>[
            ['route'=>'admin.club.activities',   'icon'=>'bi-activity',       'label'=>'Activities'],
            ['route'=>'admin.club.packages',     'icon'=>'bi-box',            'label'=>'Packages'],
            ['route'=>'admin.club.events',       'icon'=>'bi-calendar-event', 'label'=>'Events'],
            ['route'=>'admin.club.facilities',   'icon'=>'bi-geo-alt',        'label'=>'Facilities'],
        ]],
        ['label'=>'Storefront', 'items'=>[
            ['route'=>'admin.club.shop',         'icon'=>'bi-shop',           'label'=>'Shop'],
            ['route'=>'admin.club.orders',       'icon'=>'bi-bag-check',      'label'=>'Orders'],
            ['route'=>'admin.club.perks',        'icon'=>'bi-gift',           'label'=>'Perks'],
        ]],
        ['label'=>'Content', 'items'=>[
            ['route'=>'admin.club.timeline',     'icon'=>'bi-newspaper',      'label'=>'Timeline'],
            ['route'=>'admin.club.gallery',      'icon'=>'bi-images',         'label'=>'Gallery'],
            ['route'=>'admin.club.achievements', 'icon'=>'bi-trophy',         'label'=>'Achievements'],
        ]],
        ['label'=>'Communication', 'items'=>[
            ['route'=>'admin.club.messages',     'icon'=>'bi-chat-dots',      'label'=>'Messages'],
            ['route'=>'admin.club.notifications','icon'=>'bi-bell',           'label'=>'Notifications'],
        ]],
        ['label'=>null, 'items'=>[
            ['url'=>$clubPublicUrl, 'icon'=>'bi-eye',  'label'=>'View Club Page', 'external'=>true],
        ]],
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

        <!-- Club brand header -->
        <div class="emp-brand">
            <div class="emp-brand-logo">
                @if($club->logo)
                    <img src="{{ asset('storage/'.$club->logo) }}" alt="{{ $club->club_name }}">
                @else
                    <div class="ph">{{ mb_strtoupper(mb_substr($club->club_name, 0, 2, 'UTF-8'), 'UTF-8') }}</div>
                @endif
            </div>
            <span class="emp-brand-eyebrow">Club Workspace</span>
            <p class="emp-brand-name">{{ $club->club_name }}</p>
        </div>

        <!-- Action buttons -->
        <div class="emp-actions">
            @unless(Auth::user()->isSuperAdmin())
                <a href="{{ route('clubs.explore') }}" class="emp-sb-btn" title="Back to Explore"><i class="bi bi-arrow-left"></i></a>
            @endunless
            <a href="{{ $club->url }}" class="emp-sb-btn" title="Preview Club" target="_blank"><i class="bi bi-eye"></i></a>
            <button @click="showNotificationModal = true" class="emp-sb-btn" title="Send Notification"><i class="bi bi-send"></i></button>
            <a href="{{ route('admin.club.details', $clubId) }}" class="emp-sb-btn {{ $currentRoute === 'admin.club.details' ? 'active' : '' }}" title="Club Details"><i class="bi bi-gear"></i></a>
        </div>

        @if(Auth::user()->isSuperAdmin())
            <!-- Super-admin exits -->
            <div class="flex flex-col gap-0.5 px-2 pb-2" style="flex-shrink:0">
                <a href="{{ route('admin.platform.index') }}" class="nav-item">
                    <span class="ni"><i class="bi bi-shield-check"></i></span>
                    <span>Admin Dashboard</span>
                </a>
                <a href="{{ route('me.home') }}" class="nav-item">
                    <span class="ni"><i class="bi bi-house"></i></span>
                    <span>My Home</span>
                </a>
                <a href="{{ route('admin.platform.clubs') }}" class="nav-item">
                    <span class="ni"><i class="bi bi-arrow-left"></i></span>
                    <span>Back to Clubs</span>
                </a>
            </div>
        @endif

        <div class="emp-sb-div"></div>

        <!-- Navigation -->
        <nav id="emp-sidebar-nav" class="flex flex-col px-2 py-2" style="overflow-y:auto;flex:1">
            @foreach($navGroups as $group)
                <div class="emp-nav-grp">
                    @if(!empty($group['label']))
                        <p class="emp-nav-grp-ttl">{{ $group['label'] }}</p>
                    @endif
                    @foreach($group['items'] as $item)
                        @php $active = isset($item['route']) && $currentRoute === $item['route']; @endphp
                        @if(!empty($item['external']))
                            <a href="{{ $item['url'] }}" class="nav-item">
                                <span class="ni"><i class="bi {{ $item['icon'] }}"></i></span>
                                <span>{{ $item['label'] }}</span>
                            </a>
                        @elseif($item['route'] === 'admin.club.financials')
                            <a href="{{ route($item['route'], $clubId) }}" class="nav-item emp-has-tip {{ $active ? 'active' : '' }}">
                                <span class="ni"><i class="bi {{ $item['icon'] }}"></i></span>
                                <span>{{ $item['label'] }}</span>
                                <span class="nav-dot"></span>
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
                                <span class="nav-dot"></span>
                            </a>
                        @endif
                    @endforeach
                </div>
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
