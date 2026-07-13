@extends('layouts.app')

@push('styles')
<style>
/* ══ ADMIN CLUB — FULL-HEIGHT LAYOUT ══
   The page does NOT scroll. Only #emp-main-area scrolls internally.
   This guarantees the sidebar never separates from the top bar.
*/
html, body { overflow: hidden !important; height: 100% !important; }

/* The app top bar caps its contents at `container` (centered, max 1280px on xl).
   This shell is full-bleed, so un-cap the bar here — otherwise on wide screens the
   logo floats right of the sidebar and the action icons stop short of the right edge.
   Keeps the px-4 gutter so the logo aligns with the sidebar and icons with the content. */
.to-bar > .container { max-width: none; }

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
/* Collapsed = slim icon-only rail (matches platform admin shell). */
#emp-sidebar.collapsed {
    width: 76px !important;
    min-width: 76px !important;
}
#emp-sidebar.collapsed .emp-collapse-hide { display: none !important; }
#emp-sidebar.collapsed #emp-sidebar-nav { padding-left: 10px; padding-right: 10px; }
#emp-sidebar.collapsed .nav-item { justify-content: center; padding-left: 0; padding-right: 0; gap: 0; }
#emp-sidebar.collapsed .nav-item > span:not(.ni) { display: none; }
#emp-sidebar.collapsed .emp-brand { align-items: center; padding-left: 0; padding-right: 0; }
#emp-sidebar.collapsed .emp-foot { padding-left: 10px; padding-right: 10px; }

/* ── MAIN CONTENT ── */
#emp-main-area {
    flex: 1;
    min-width: 0;
    overflow-y: auto;
    height: 100%;
    padding: 20px 16px;
}

/* ── TOGGLE BUTTON ── */
/* Match the .nav-icon-btn token used by the other top-bar icons (compass/chat/bell). */
#emp-sidebar-toggle {
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 0.75rem;
    background: transparent;
    border: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    color: hsl(220 9% 46%);
    font-size: 1.25rem;
    transition: background-color .18s ease, color .18s ease, transform .18s cubic-bezier(.22,.61,.36,1);
}
#emp-sidebar-toggle:hover { background: hsl(250 60% 92%); color: hsl(var(--primary)); transform: translateY(-1px); }
#emp-sidebar-toggle:active { transform: scale(.92); }

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
/* Club brand header (left-aligned mark + title, matches platform admin shell) */
.emp-brand {
    display:flex; flex-direction:column; align-items:center; gap:10px;
    padding:18px 16px 16px;
    flex-shrink:0;
}
.emp-brand-mark {
    width:40px; height:40px; border-radius:12px; flex-shrink:0;
    display:flex; align-items:center; justify-content:center; overflow:hidden;
    background:linear-gradient(135deg, hsl(250 65% 66%), hsl(262 60% 56%));
    color:#fff; font-size:15px; font-weight:800; letter-spacing:-0.5px; line-height:1;
    box-shadow:0 6px 16px -4px hsl(250 65% 60% / 0.6);
}
.emp-brand-mark.has-logo {
    width:100%; height:auto; max-height:160px; border-radius:0; background:none; box-shadow:none;
}
.emp-brand-mark img { width:100%; height:100%; object-fit:contain; }
.emp-brand-eyebrow {
    font-size:11px; font-weight:600; color:hsl(250 12% 62%);
    display:block; margin-top:1px; text-align:center;
}
.emp-brand-name {
    font-size:14.5px; font-weight:800; color:#1f2937;
    line-height:1.15; margin:0; display:block; text-align:center;
    max-width:100%; overflow-wrap:break-word; white-space:normal;
}

/* Action segmented bar */
.emp-actions {
    flex-shrink:0; display:flex; flex-wrap:wrap; justify-content:center; gap:6px;
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

/* Footer: identity card + Back to Explore (matches platform admin shell) */
.emp-foot { padding:10px 12px 14px; flex-shrink:0; }
.emp-user {
    display:flex; align-items:center; gap:10px;
    padding:9px 10px; margin-bottom:8px; border-radius:12px;
    background:hsl(250 40% 97%); border:1px solid hsl(250 35% 93%);
}
.emp-user-av {
    width:32px; height:32px; border-radius:9px; flex-shrink:0;
    display:flex; align-items:center; justify-content:center;
    background:linear-gradient(135deg, hsl(250 65% 66%), hsl(262 60% 56%));
    color:#fff; font-size:12px; font-weight:800; letter-spacing:0.3px;
}
.emp-user-name { font-size:12.5px; font-weight:700; color:#374151; line-height:1.15; }
.emp-user-role { font-size:10.5px; font-weight:600; color:hsl(250 10% 64%); display:flex; align-items:center; gap:4px; }
.emp-user-role::before { content:''; width:6px; height:6px; border-radius:50%; background:#22c55e; box-shadow:0 0 0 2px #dcfce7; }
.emp-explore { border:1px solid hsl(250 35% 90%) !important; }
.emp-explore:hover { background:hsl(250 65% 96%) !important; color:hsl(250 48% 42%) !important; border-color:hsl(250 60% 82%) !important; }

</style>
@endpush

@section('content')
@php
    $clubId = $club->slug ?? $club->id ?? null;
    $currentRoute = request()->route()->getName();

    $sbUser = auth()->user();
    $sbName = $sbUser?->name ?: __('nav.layouts_admin_club_user_default_name');
    $sbInitials = collect(explode(' ', trim($sbName)))
        ->filter()->take(2)
        ->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))
        ->implode('') ?: 'U';
    $sbRole = $sbUser?->isSuperAdmin() ? __('nav.layouts_admin_club_role_super_admin') : __('nav.layouts_admin_club_role_club_admin');
    $navGroups = [
        ['label'=>__('nav.layouts_admin_club_group_overview'), 'items'=>[
            ['route'=>'admin.club.dashboard',    'icon'=>'bi-speedometer2',   'label'=>__('nav.layouts_admin_club_nav_dashboard')],
            ['route'=>'admin.club.analytics',    'icon'=>'bi-bar-chart',      'label'=>__('nav.layouts_admin_club_nav_analytics')],
            ['route'=>'admin.club.financials',   'icon'=>'bi-currency-dollar','label'=>__('nav.layouts_admin_club_nav_financials')],
        ]],
        ['label'=>__('nav.layouts_admin_club_group_people'), 'items'=>[
            ['route'=>'admin.club.members',      'icon'=>'bi-person-plus',    'label'=>__('nav.layouts_admin_club_nav_members')],
            ['route'=>'admin.club.instructors',  'icon'=>'bi-people',         'label'=>__('nav.layouts_admin_club_nav_instructors')],
            ['route'=>'admin.club.roles',        'icon'=>'bi-shield-check',   'label'=>__('nav.layouts_admin_club_nav_roles')],
        ]],
        ['label'=>__('nav.layouts_admin_club_group_programs'), 'items'=>[
            ['route'=>'admin.club.activities',   'icon'=>'bi-activity',       'label'=>__('nav.layouts_admin_club_nav_activities')],
            ['route'=>'admin.club.packages',     'icon'=>'bi-box',            'label'=>__('nav.layouts_admin_club_nav_packages')],
            ['route'=>'admin.club.events',       'icon'=>'bi-calendar-event', 'label'=>__('nav.layouts_admin_club_nav_events')],
            ['route'=>'admin.club.facilities',   'icon'=>'bi-geo-alt',        'label'=>__('nav.layouts_admin_club_nav_facilities')],
        ]],
        ['label'=>__('nav.layouts_admin_club_group_storefront'), 'items'=>[
            ['route'=>'admin.club.shop',         'icon'=>'bi-shop',           'label'=>__('nav.layouts_admin_club_nav_shop')],
            ['route'=>'admin.club.orders',       'icon'=>'bi-bag-check',      'label'=>__('nav.layouts_admin_club_nav_orders')],
            ['route'=>'admin.club.perks',        'icon'=>'bi-gift',           'label'=>__('nav.layouts_admin_club_nav_perks')],
        ]],
        ['label'=>__('nav.layouts_admin_club_group_content'), 'items'=>[
            ['route'=>'admin.club.timeline',     'icon'=>'bi-newspaper',      'label'=>__('nav.layouts_admin_club_nav_timeline')],
            ['route'=>'admin.club.gallery',      'icon'=>'bi-images',         'label'=>__('nav.layouts_admin_club_nav_gallery')],
            ['route'=>'admin.club.achievements', 'icon'=>'bi-trophy',         'label'=>__('nav.layouts_admin_club_nav_achievements')],
        ]],
        ['label'=>__('nav.layouts_admin_club_group_communication'), 'items'=>[
            ['route'=>'admin.club.messages',     'icon'=>'bi-chat-dots',      'label'=>__('nav.layouts_admin_club_nav_messages')],
            ['route'=>'admin.club.notifications','icon'=>'bi-bell',           'label'=>__('nav.layouts_admin_club_nav_notifications')],
        ]],
    ];
@endphp

<div x-data="{ showNotificationModal: false }">

@push('navbar-left')
<button id="emp-sidebar-toggle" title="{{ __('nav.layouts_admin_club_toggle_sidebar') }}" onclick="empToggleSidebar()">
    <i class="bi bi-layout-sidebar-inset" id="emp-toggle-icon"></i>
</button>
@endpush

<!-- ── FULL-HEIGHT LAYOUT ── -->
<div id="emp-layout">

    <!-- ── SIDEBAR ── -->
    <aside id="emp-sidebar" class="flex flex-col">

        <!-- Club brand header -->
        <div class="emp-brand">
            <span class="emp-brand-mark{{ $club->logo ? ' has-logo' : '' }}">
                @if($club->logo)
                    <img src="{{ asset('storage/'.$club->logo) }}" alt="{{ $club->club_name }}">
                @else
                    {{ mb_strtoupper(mb_substr($club->club_name, 0, 2, 'UTF-8'), 'UTF-8') }}
                @endif
            </span>
            <span class="emp-collapse-hide" style="min-width:0; width:100%; text-align:center">
                <span class="emp-brand-name" style="display:block">{{ $club->club_name }}</span>
                <span class="emp-brand-eyebrow">{{ __('nav.layouts_admin_club_club_workspace') }}</span>
            </span>
        </div>

        <!-- Action buttons -->
        <div class="emp-actions emp-collapse-hide">
            @if(Auth::user()->isSuperAdmin())
                <a href="{{ route('admin.platform.index') }}" class="emp-sb-btn" title="{{ __('nav.layouts_admin_club_admin_dashboard') }}"><i class="bi bi-shield-check"></i></a>
                <a href="{{ route('me.home') }}" class="emp-sb-btn" title="{{ __('nav.layouts_admin_club_my_home') }}"><i class="bi bi-house"></i></a>
                <a href="{{ route('admin.platform.clubs') }}" class="emp-sb-btn" title="{{ __('nav.layouts_admin_club_back_to_clubs') }}"><i class="bi bi-arrow-left"></i></a>
            @else
                <a href="{{ route('clubs.explore') }}" class="emp-sb-btn" title="{{ __('nav.layouts_admin_club_back_to_explore') }}"><i class="bi bi-arrow-left"></i></a>
            @endif
            <a href="{{ $club->url }}" class="emp-sb-btn" title="{{ __('nav.layouts_admin_club_preview_club') }}" target="_blank"><i class="bi bi-eye"></i></a>
            <button @click="showNotificationModal = true" class="emp-sb-btn" title="{{ __('nav.layouts_admin_club_send_notification') }}"><i class="bi bi-send"></i></button>
            <a href="{{ route('admin.club.details', $clubId) }}" data-shell-link data-route="admin.club.details" class="emp-sb-btn {{ $currentRoute === 'admin.club.details' ? 'active' : '' }}" title="{{ __('nav.layouts_admin_club_club_details') }}"><i class="bi bi-gear"></i></a>
        </div>

        <div class="emp-sb-div"></div>

        <!-- Navigation -->
        <nav id="emp-sidebar-nav" class="flex flex-col px-2 py-2" style="overflow-y:auto;flex:1">
            @foreach($navGroups as $group)
                <div class="emp-nav-grp">
                    @if(!empty($group['label']))
                        <p class="emp-nav-grp-ttl emp-collapse-hide">{{ $group['label'] }}</p>
                    @endif
                    @foreach($group['items'] as $item)
                        @php $active = isset($item['route']) && $currentRoute === $item['route']; @endphp
                        @if(!empty($item['external']))
                            <a href="{{ $item['url'] }}" class="nav-item">
                                <span class="ni"><i class="bi {{ $item['icon'] }}"></i></span>
                                <span>{{ $item['label'] }}</span>
                            </a>
                        @elseif($item['route'] === 'admin.club.financials')
                            <a href="{{ route($item['route'], $clubId) }}" data-shell-link data-route="{{ $item['route'] }}" class="nav-item emp-has-tip {{ $active ? 'active' : '' }}">
                                <span class="ni"><i class="bi {{ $item['icon'] }}"></i></span>
                                <span>{{ $item['label'] }}</span>
                                <span class="nav-dot"></span>
                                <div class="emp-tip-box">
                                    <div class="emp-tip-ttl">{{ __('nav.layouts_admin_club_cash_flow') }}</div>
                                    <div class="emp-tip-row"><span>{{ __('nav.layouts_admin_club_income') }}</span><span class="v vp">BHD 250</span></div>
                                    <div class="emp-tip-row"><span>{{ __('nav.layouts_admin_club_expenses') }}</span><span class="v vn">BHD -200</span></div>
                                    <div class="emp-tip-tot"><span>{{ __('nav.layouts_admin_club_total') }}</span><span class="v">BHD 50</span></div>
                                </div>
                            </a>
                        @else
                            <a href="{{ route($item['route'], $clubId) }}" data-shell-link data-route="{{ $item['route'] }}" class="nav-item {{ $active ? 'active' : '' }}">
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
    <main id="emp-main-area" data-shell-main="club" data-shell-base="{{ url('/admin/club/'.$clubId) }}" data-route="{{ $currentRoute }}">
        @yield('club-admin-content')
    </main>

</div>{{-- #emp-layout --}}

@include('admin.club.notifications.send-modal')

@include('partials.admin-shell-nav')

</div>{{-- x-data --}}

@push('scripts')
<script>
function empApplySidebar(collapsed) {
    var sb  = document.getElementById('emp-sidebar');
    var ico = document.getElementById('emp-toggle-icon');
    if (sb)  sb.classList.toggle('collapsed', collapsed);
    if (ico) ico.className = collapsed ? 'bi bi-layout-sidebar-inset-reverse' : 'bi bi-layout-sidebar-inset';
}
function empToggleSidebar() {
    var collapsed = !document.getElementById('emp-sidebar').classList.contains('collapsed');
    try { localStorage.setItem('empSidebarCollapsed', collapsed ? '1' : '0'); } catch (e) {}
    empApplySidebar(collapsed);
}
// Restore persisted state.
document.addEventListener('DOMContentLoaded', function () {
    var collapsed = false;
    try { collapsed = localStorage.getItem('empSidebarCollapsed') === '1'; } catch (e) {}
    if (collapsed) empApplySidebar(true);
});
</script>
@endpush

@endsection
