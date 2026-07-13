@extends('layouts.admin-club')

@push('styles')
<style>
/* ══ DASHBOARD GRID ══ */
.dash-grid {
    display: grid;
    grid-template-columns: 220px 1fr 1fr 1fr 210px;
    grid-template-rows: 260px 220px auto;
    gap: 12px;
}

/* Grid placements */
.dash-members      { grid-column: 1; grid-row: 1; }
.dash-packages     { grid-column: 2; grid-row: 1; }
.dash-talent       { grid-column: 3; grid-row: 1; }
.dash-trainers     { grid-column: 4; grid-row: 1; }
.dash-hof          { grid-column: 5; grid-row: 1 / 3; }
.dash-events       { grid-column: 1; grid-row: 2; }
.dash-activities   { grid-column: 2; grid-row: 2; }
.dash-achievements { grid-column: 3 / 5; grid-row: 2; }
.dash-fiscal       { grid-column: 1 / 6; grid-row: 3; }

@media (max-width: 1280px) {
    .dash-grid { grid-template-columns: 1fr 1fr 1fr 1fr; grid-template-rows: auto; }
    .dash-hof  { grid-column: 4; grid-row: 1 / 3; }
    .dash-achievements { grid-column: 2 / 4; }
    .dash-fiscal { grid-column: 1 / 5; }
}
@media (max-width: 900px) {
    .dash-grid { grid-template-columns: 1fr 1fr; grid-template-rows: auto; }
    .dash-hof,.dash-achievements { grid-column: auto; grid-row: auto; }
    .dash-fiscal { grid-column: 1 / 3; }
}
@media (max-width: 600px) {
    .dash-grid { grid-template-columns: 1fr; }
    .dash-fiscal { grid-column: 1; }
}

/* ── BASE CARD ── */
.dc {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    padding: 14px;
    position: relative;
    overflow: hidden;
    transition: transform 0.18s, box-shadow 0.18s;
    box-shadow: 0 1px 4px rgba(0,0,0,0.05);
    display: flex;
    flex-direction: column;
}
.dc:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(124,99,224,0.08), 0 2px 8px rgba(0,0,0,0.06);
    border-color: hsl(250 60% 88%);
}

/* ── CARD HEADER ── */
.dc-hdr { display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; flex-shrink: 0; }
.dc-ttl {  font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:1.5px; color:#9ca3af; }
.dc-ico { width:24px; height:24px; border-radius:7px; display:flex; align-items:center; justify-content:center; font-size:11px; }
.dc-ico-p { background:hsl(250 60% 93%); color:hsl(250 65% 60%); }
.dc-ico-g { background:#dcfce7; color:#16a34a; }
.dc-ico-a { background:#fef3c7; color:#d97706; }
.dc-ico-b { background:#dbeafe; color:#2563eb; }
.dc-ico-r { background:#fee2e2; color:#dc2626; }
.dc-ico-s { background:#fef9c3; color:#ca8a04; }

/* ── MEMBERS card ── */
.m-big {  font-size:30px; font-weight:700; color:#111827; line-height:1; text-align:center; }
.m-big .sep { color:#d1d5db; font-size:22px; }
.m-big .total { font-size:18px; color:#9ca3af; }
.m-chart-wrap { position:relative; width:120px; height:120px; margin:0 auto 6px; }
.m-legend { display:flex; flex-direction:column; gap:4px; }
.m-leg { display:flex; align-items:center; gap:6px; font-size:10px; color:#6b7280;  font-weight:600; }
.m-dot { width:7px; height:7px; border-radius:50%; flex-shrink:0; }

/* ── PACKAGES card ── */
.pkg-scroll { overflow:hidden; }
.pkg-track  { display:flex; gap:8px; transition:transform 0.3s ease; }
.pkg-item {
    min-width:calc(50% - 4px); flex-shrink:0;
    background:#f9fafb; border:1px solid #f3f4f6; border-radius:10px; padding:10px;
    transition:border-color 0.15s;
}
.pkg-item:hover { border-color:hsl(250 60% 88%); }
.pkg-name  {  font-size:9px; font-weight:700; letter-spacing:0.5px; color:hsl(250 65% 60%); text-transform:uppercase; margin-bottom:4px; }
.pkg-price {  font-size:22px; font-weight:700; color:#111827; line-height:1; margin-bottom:5px; }
.pkg-price .cur { font-size:10px; color:#9ca3af; }
.pkg-badge { display:inline-block; font-size:8px; font-weight:700; border-radius:20px; padding:2px 7px; text-transform:uppercase; letter-spacing:0.4px; }
.pkg-badge-g { background:#dcfce7; color:#16a34a; }
.pkg-badge-b { background:#dbeafe; color:#2563eb; }
.pkg-badge-o { background:#ffedd5; color:#ea580c; }
.pkg-badge-y { background:#fef9c3; color:#ca8a04; }
.pkg-dots { display:flex; gap:4px; }
.pkg-dot { width:5px; height:5px; border-radius:50%; background:#e5e7eb; cursor:pointer; transition:all 0.2s; }
.pkg-dot.on { background:hsl(250 65% 60%); }
.pkg-arr { width:22px; height:22px; border-radius:7px; cursor:pointer; background:#f3f4f6; border:1px solid #e5e7eb; display:flex; align-items:center; justify-content:center; font-size:9px; color:#6b7280; transition:all 0.15s; }
.pkg-arr:hover { background:hsl(250 60% 93%); border-color:hsl(250 65% 60%); color:hsl(250 65% 60%); }

/* ── TALENT card ── */
.act-count {  font-size:34px; color:#111827; line-height:1; }
.act-sublbl { font-size:9px; color:#9ca3af; text-transform:uppercase; letter-spacing:1px; margin-bottom:10px; display:block;  font-weight:600; }
.act-gantt { display:flex; flex-direction:column; gap:6px; flex:1; }
.act-row { display:flex; align-items:center; gap:6px; }
.act-av { width:20px; height:20px; border-radius:50%; flex-shrink:0; }
.act-nm { font-size:9px; color:#6b7280; width:64px; flex-shrink:0; line-height:1.2;  font-weight:600; }
.act-bars { flex:1; display:flex; height:14px; border-radius:5px; overflow:hidden; gap:1px; }
.act-seg { flex:1; border-radius:2px; }
.act-see { display:block; text-align:center; margin-top:auto; padding-top:8px; font-size:9px; color:hsl(250 65% 60%); text-decoration:none;  font-weight:700; letter-spacing:0.4px; text-transform:uppercase; }
.act-see:hover { color:hsl(250 55% 50%); }

/* ── TRAINERS card ── */
.tr-item { display:flex; align-items:center; gap:8px; padding:8px; border-radius:9px; background:#f9fafb; border:1px solid #f3f4f6; margin-bottom:7px; transition:border-color 0.15s; }
.tr-item:last-child { margin-bottom:0; }
.tr-item:hover { border-color:hsl(250 60% 88%); background:hsl(250 60% 97%); }
.tr-photo { width:34px; height:34px; border-radius:50%; flex-shrink:0; border:2px solid #e5e7eb; overflow:hidden; background:linear-gradient(135deg,hsl(250 60% 93%),hsl(250 60% 88%)); display:flex; align-items:center; justify-content:center; font-size:13px;  font-weight:700; color:hsl(250 65% 60%); }
.tr-name  { font-size:12px; font-weight:700; color:#111827; line-height:1;  }
.tr-exp   { font-size:9px; color:#9ca3af; }
.tr-sub   { font-size:9px; margin-top:1px; }
.tr-badge { font-size:7px; font-weight:700; border-radius:5px; padding:2px 6px; text-transform:uppercase; letter-spacing:0.4px; flex-shrink:0; }
.badge-act { background:#dcfce7; color:#16a34a; }
.badge-avl { background:#f3f4f6; color:#6b7280; }
.tr-see { display:block; text-align:center; margin-top:auto; padding-top:8px; font-size:9px; color:hsl(250 65% 60%); text-decoration:none;  font-weight:700; text-transform:uppercase; }

/* ── HALL OF FAME card ── */
.hof-item { display:flex; align-items:center; gap:7px; padding:7px 8px; border-radius:9px; background:#f9fafb; border:1px solid #f3f4f6; margin-bottom:6px; transition:all 0.15s; }
.hof-item:last-child { margin-bottom:0; }
.hof-item:hover { border-color:hsl(250 60% 88%); background:hsl(250 60% 97%); }
.hof-photo { width:32px; height:32px; border-radius:50%; flex-shrink:0; border:2px solid #e5e7eb; overflow:hidden; background:linear-gradient(135deg,hsl(250 60% 93%),hsl(250 60% 88%)); display:flex; align-items:center; justify-content:center; font-size:12px;  font-weight:700; color:hsl(250 65% 60%); }
.hof-name  { font-size:11px; font-weight:700; color:#111827; line-height:1;  }
.hof-rank  { font-size:8px; color:#9ca3af; margin-top:1px; }
.hof-ach   { font-size:8px; color:hsl(250 65% 60%); margin-top:2px; display:block; }
.hof-star  { font-size:13px; color:#f59e0b; flex-shrink:0; }
.hof-see { display:block; text-align:center; margin-top:auto; padding-top:8px; font-size:9px; color:hsl(250 65% 60%); text-decoration:none;  font-weight:700; text-transform:uppercase; }

/* ── EVENTS card ── */
.ev-num {  font-size:42px; color:#111827; line-height:1; }
.ev-lbl { font-size:9px; color:#9ca3af; text-transform:uppercase; letter-spacing:1px; display:block; margin-bottom:10px;  font-weight:600; }
.ev-cal { display:grid; grid-template-columns:repeat(5,1fr); gap:3px; margin-bottom:6px; }
.ev-cell { height:20px; background:#f3f4f6; border-radius:4px; }
.ev-pend { font-size:9px; color:#9ca3af; font-style:italic; line-height:1.4; }

/* ── ACHIEVEMENTS card ── */
.ach-score-side { display:flex; flex-direction:column; align-items:center; justify-content:center; padding-right:16px; border-right:1px solid #f3f4f6; margin-right:16px; flex-shrink:0; }
.ach-big {  font-size:44px; font-weight:900; color:#111827; line-height:1; }
.ach-stars { display:flex; gap:3px; margin:5px 0; }
.ach-meta { font-size:9px; color:#9ca3af; text-align:center;  }
.ach-bars { flex:1; display:flex; flex-direction:column; gap:8px; justify-content:center; }
.ach-bar-row { display:flex; align-items:center; gap:8px; }
.ach-bar-lbl { font-size:10px; color:#6b7280; width:70px; text-transform:uppercase; letter-spacing:0.3px;  font-weight:700; }
.ach-bar-track { flex:1; height:4px; background:#f3f4f6; border-radius:2px; overflow:hidden; }
.ach-bar-fill { height:100%; background:hsl(250 65% 60%); border-radius:2px; }
.ach-bar-val {  font-size:9px; color:#9ca3af; width:22px; text-align:right; }
.ach-pending { font-size:9px; color:#9ca3af; margin-top:10px; font-style:italic; line-height:1.4; }

/* ── FISCAL card ── */
.fiscal-card { background:#fff; border:1px solid #e5e7eb; border-radius:14px; padding:16px; box-shadow:0 1px 4px rgba(0,0,0,0.05); }
.fiscal-ttl {  font-size:14px; font-weight:700; color:#111827; letter-spacing:0.5px; }
.fiscal-sub { font-size:10px; color:#9ca3af; margin-top:2px; }
.fiscal-legend { display:flex; gap:14px; flex-wrap:wrap; align-items:center; }
.fiscal-leg { display:flex; align-items:center; gap:5px; font-size:9px; color:#6b7280; text-transform:uppercase; letter-spacing:0.3px;  font-weight:700; }
.fiscal-dot { width:7px; height:7px; border-radius:50%; flex-shrink:0; }
.fiscal-line { width:14px; height:2px; flex-shrink:0; border-radius:1px; }
.fiscal-arr { width:24px; height:24px; border-radius:7px; cursor:pointer; background:#f3f4f6; border:1px solid #e5e7eb; display:flex; align-items:center; justify-content:center; font-size:10px; color:#6b7280; transition:all 0.15s; }
.fiscal-arr:hover { background:hsl(250 60% 93%); border-color:hsl(250 65% 60%); color:hsl(250 65% 60%); }
.growth-ttl {  font-size:9px; font-weight:700; color:hsl(250 65% 60%); letter-spacing:0.5px; text-transform:uppercase; margin-bottom:6px; }

/* Page header */
.pg-title { font-size:1.875rem; font-weight:700; color:#111827; }
.pg-sub   { font-size:0.875rem; color:#6b7280; margin-top:0.25rem; }

/* Expiring subs table */
.exp-table { width:100%; border-collapse:collapse; font-size:13px;  }
.exp-table thead th { padding:6px 12px 6px 0; text-align:left; color:#9ca3af; font-size:10px; text-transform:uppercase; letter-spacing:1px; border-bottom:1px solid #f3f4f6; font-weight:700; }
.exp-table td { padding:8px 12px 8px 0; color:#374151; border-bottom:1px solid #f9fafb; }
</style>
@endpush

@section('club-admin-content')
<div style="display:flex;flex-direction:column;gap:14px">

    <!-- Page Header -->
    <x-admin-hero eyebrow="{{ __('admin.club_dashboard_index_eyebrow') }}" :title="$club->club_name"
                  subtitle="{{ __('admin.club_dashboard_index_hero_subtitle') }}" icon="bi-trophy-fill">
        <x-slot:actions>
            <a href="{{ route('admin.club.members', $club->slug) }}"
               class="inline-flex items-center gap-2 bg-white/15 text-white border border-white/30 px-4 py-2 rounded-lg text-sm font-semibold hover:bg-white/25 transition-colors no-underline">
                <i class="bi bi-people"></i> {{ __('admin.club_dashboard_index_members_btn') }}
            </a>
            <a href="{{ route('admin.club.financials', $club->slug) }}"
               class="inline-flex items-center gap-2 bg-white text-primary px-4 py-2 rounded-lg text-sm font-semibold hover:bg-white/90 transition-colors no-underline">
                <i class="bi bi-bar-chart"></i> {{ __('admin.club_dashboard_index_financials_btn') }}
            </a>
        </x-slot:actions>
    </x-admin-hero>

    <!-- ══ MAIN GRID ══ -->
    <div class="dash-grid">

        {{-- ── 1. ACTIVE MEMBERS ── --}}
        <div class="dc dash-members">
            <div class="dc-hdr">
                <div class="dc-ttl">{{ __('admin.club_dashboard_index_active_members') }}</div>
                <div class="dc-ico dc-ico-p"><i class="bi bi-people-fill"></i></div>
            </div>
            @php
                $active = $stats['members'] ?? 0;
                $total  = $stats['members_total'] ?? 0;
                $ageVals = array_values($ageGroups);
                $ageSum  = array_sum($ageVals) ?: 1;
            @endphp
            <div class="m-big">{{ $active }}<span class="sep">/</span><span class="total">{{ $total }}</span></div>
            <div class="m-chart-wrap" style="flex-shrink:0">
                <canvas id="membersDonut"></canvas>
            </div>
            <div class="m-legend">
                @php
                    $ageLabels = [__('admin.club_dashboard_index_age_kids'),__('admin.club_dashboard_index_age_juniors'),__('admin.club_dashboard_index_age_youth'),__('admin.club_dashboard_index_age_adults')];
                    $ageCols   = ['#f97316','#f59e0b','hsl(250 65% 60%)','#22c55e'];
                    $ai = 0;
                @endphp
                @foreach($ageGroups as $grp => $cnt)
                <div class="m-leg">
                    <div class="m-dot" style="background:{{ $ageCols[$ai] }}"></div>
                    <span>{{ $ageLabels[$ai] }}</span>
                    <span style="margin-left:auto;font-size:9px;color:#374151">{{ $ageSum > 0 ? round($cnt/$ageSum*100) : 0 }}%</span>
                </div>
                @php $ai = min($ai+1, 3); @endphp
                @endforeach
            </div>
        </div>

        {{-- ── 2. PACKAGES ── --}}
        <div class="dc dash-packages">
            <div class="dc-hdr">
                <div class="dc-ttl">{{ __('admin.club_dashboard_index_programs_packages') }}</div>
                <div class="dc-ico dc-ico-g"><i class="bi bi-box"></i></div>
            </div>
            <div class="pkg-scroll" style="flex:1;min-height:0">
                <div class="pkg-track" id="pkgTrack">
                    @forelse($packages as $i => $pkg)
                    <div class="pkg-item">
                        <div class="pkg-name">{{ Str::limit($pkg->name, 16) }}</div>
                        <div class="pkg-price"><span class="cur">{{ $club->currency ?? 'BHD' }} </span>{{ number_format($pkg->price ?? 0) }}</div>
                        @php $badges=[['g',__('admin.club_dashboard_index_badge_available')],['b',__('admin.club_dashboard_index_badge_popular')],['o',__('admin.club_dashboard_index_badge_premium')],['y',__('admin.club_dashboard_index_badge_seasonal')]]; $b=$badges[$i%4]; @endphp
                        <span class="pkg-badge pkg-badge-{{ $b[0] }}">{{ $b[1] }}</span>
                        @if($pkg->duration_months)
                        <div style="margin-top:5px;font-size:8px;color:#9ca3af;font-weight:600">{{ $pkg->duration_months }} {{ $pkg->duration_months > 1 ? __('admin.club_dashboard_index_month_plural') : __('admin.club_dashboard_index_month_singular') }}</div>
                        @endif
                    </div>
                    @empty
                    <div class="pkg-item">
                        <div class="pkg-name">{{ __('admin.club_dashboard_index_no_packages') }}</div>
                        <div class="pkg-price" style="font-size:14px;color:#9ca3af">—</div>
                        <span class="pkg-badge pkg-badge-g">{{ __('admin.club_dashboard_index_add_one') }}</span>
                    </div>
                    @endforelse
                </div>
            </div>
            <div style="display:flex;align-items:center;justify-content:space-between;margin-top:10px;flex-shrink:0">
                <div style="display:flex;gap:5px">
                    <button class="pkg-arr" id="pkgPrev"><i class="bi bi-chevron-left"></i></button>
                    <button class="pkg-arr" id="pkgNext"><i class="bi bi-chevron-right"></i></button>
                </div>
                <div style="display:flex;gap:4px" id="pkgDots"></div>
            </div>
        </div>

        {{-- ── 3. TALENT & ACTIVITIES ── --}}
        <div class="dc dash-talent">
            <div class="dc-hdr">
                <div class="dc-ttl">{{ __('admin.club_dashboard_index_talent_activities') }}</div>
                <div class="dc-ico dc-ico-a"><i class="bi bi-activity"></i></div>
            </div>
            <div>
                <span class="act-count">{{ $stats['activities'] ?? 0 }}</span>
                <span class="act-sublbl">{{ Str::plural('Activity', $stats['activities'] ?? 0) }} {{ __('admin.club_dashboard_index_registered') }}</span>
            </div>
            <div class="act-gantt">
                @php
                    $actColors = [
                        ['hsl(250 65% 60%)','#3b82f6'],
                        ['#f97316','#dc2626'],
                        ['#22c55e','#16a34a'],
                    ];
                @endphp
                @forelse($activities->take(3) as $i => $act)
                <div class="act-row">
                    <div class="act-av" style="background:linear-gradient(135deg,{{ $actColors[$i % 3][0] }},{{ $actColors[$i % 3][1] }})"></div>
                    <div class="act-nm">{{ Str::limit($act->name ?? __('admin.club_dashboard_index_activity_fallback'), 10) }}</div>
                    <div class="act-bars">
                        <div class="act-seg" style="background:#86efac;flex:{{ 1.8 + $i*0.4 }}"></div>
                        <div class="act-seg" style="background:#93c5fd;flex:1.3"></div>
                        <div class="act-seg" style="background:#fca5a5;flex:0.9"></div>
                        <div class="act-seg" style="background:#c4b5fd;flex:{{ 1.5 - $i*0.2 }}"></div>
                        <div class="act-seg" style="background:#6ee7b7;flex:1.8"></div>
                    </div>
                </div>
                @empty
                <div class="act-row">
                    <div class="act-av" style="background:linear-gradient(135deg,hsl(250 65% 60%),#3b82f6)"></div>
                    <div class="act-nm">{{ __('admin.club_dashboard_index_no_activities') }}</div>
                    <div class="act-bars">
                        <div class="act-seg" style="background:#e5e7eb;flex:5"></div>
                    </div>
                </div>
                @endforelse
            </div>
            <a href="{{ route('admin.club.activities', $club->slug) }}" class="act-see">{{ __('admin.club_dashboard_index_see_all_activities') }}</a>
        </div>

        {{-- ── 4. TRAINERS ── --}}
        <div class="dc dash-trainers">
            <div class="dc-hdr">
                <div class="dc-ttl">{{ __('admin.club_dashboard_index_trainers') }}</div>
                <div class="dc-ico dc-ico-b"><i class="bi bi-person-badge"></i></div>
            </div>
            @forelse($instructors->take(3) as $instr)
            @php $u = $instr->user; @endphp
            <div class="tr-item">
                <div class="tr-photo">
                    @if($u?->profile_picture)
                        <img src="{{ asset('storage/'.$u->profile_picture) }}" alt="{{ $u->full_name }}" style="width:100%;height:100%;object-fit:cover;border-radius:50%">
                    @else
                        {{ mb_strtoupper(mb_substr($u?->first_name ?? 'T', 0, 1, 'UTF-8'), 'UTF-8') }}
                    @endif
                </div>
                <div style="flex:1;min-width:0">
                    <div class="tr-name">{{ $u?->full_name ?? __('admin.club_dashboard_index_instructor_fallback') }}</div>
                    <div class="tr-exp">{{ ucwords(str_replace('_',' ',$instr->role ?? 'Instructor')) }}</div>
                </div>
                <div class="tr-badge {{ $instr->role === 'head_instructor' ? 'badge-act' : 'badge-avl' }}">
                    {{ $instr->role === 'head_instructor' ? __('admin.club_dashboard_index_status_active') : __('admin.club_dashboard_index_status_staff') }}
                </div>
            </div>
            @empty
            <div style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px;color:#9ca3af">
                <i class="bi bi-person-x" style="font-size:28px;color:#e5e7eb"></i>
                <span style="font-size:11px">{{ __('admin.club_dashboard_index_no_instructors') }}</span>
            </div>
            @endforelse
            <a href="{{ route('admin.club.instructors', $club->slug) }}" class="tr-see">{{ __('admin.club_dashboard_index_manage_trainers') }}</a>
        </div>

        {{-- ── 5. HALL OF FAME (row-span 2) ── --}}
        <div class="dc dash-hof" style="padding:14px">
            <div class="dc-hdr">
                <div>
                    <div class="dc-ttl">{{ __('admin.club_dashboard_index_hall_of_fame') }}</div>
                    <div style="font-size:8px;color:#d1d5db;font-weight:600;margin-top:1px">{{ __('admin.club_dashboard_index_achievements_label') }}</div>
                </div>
                <div class="dc-ico dc-ico-s"><i class="bi bi-trophy-fill"></i></div>
            </div>
            @forelse($hofMembers as $i => $member)
            <div class="hof-item">
                <div class="hof-photo">
                    @if($member->profile_picture)
                        <img src="{{ asset('storage/'.$member->profile_picture) }}" alt="{{ $member->full_name }}" style="width:100%;height:100%;object-fit:cover;border-radius:50%">
                    @else
                        {{ mb_strtoupper(mb_substr($member->first_name ?? 'M', 0, 1, 'UTF-8'), 'UTF-8') }}
                    @endif
                </div>
                <div style="flex:1;min-width:0">
                    <div class="hof-name">{{ $member->full_name ?? __('admin.club_dashboard_index_member_fallback') }}</div>
                    <div class="hof-rank">{{ $member->clubAffiliations->first()?->name ?? [__('admin.club_dashboard_index_belt_red'),__('admin.club_dashboard_index_belt_blue'),__('admin.club_dashboard_index_belt_green'),__('admin.club_dashboard_index_belt_yellow'),__('admin.club_dashboard_index_belt_black')][$i % 5] }}</div>
                    <span class="hof-ach">{{ [__('admin.club_dashboard_index_ach_champion_2024'),__('admin.club_dashboard_index_ach_club_spirit'),__('admin.club_dashboard_index_ach_top_performer'),__('admin.club_dashboard_index_ach_rising_star'),__('admin.club_dashboard_index_ach_excellence')][$i % 5] }}</span>
                </div>
                @if($i < 3)<span class="hof-star">★</span>@endif
            </div>
            @empty
            @foreach(['Liam O\'Connor','Aisha Khan','Carlos Reyes','Nadia Soni','Alex Tanner'] as $i => $nm)
            <div class="hof-item">
                <div class="hof-photo" style="font-size:11px">{{ mb_strtoupper(mb_substr($nm, 0, 1, 'UTF-8'), 'UTF-8') }}</div>
                <div style="flex:1;min-width:0">
                    <div class="hof-name">{{ $nm }}</div>
                    <div class="hof-rank">{{ [__('admin.club_dashboard_index_belt_red'),__('admin.club_dashboard_index_belt_blue'),__('admin.club_dashboard_index_belt_blue'),__('admin.club_dashboard_index_instructor_fallback'),__('admin.club_dashboard_index_instructor_fallback')][$i] }}</div>
                    <span class="hof-ach">{{ [__('admin.club_dashboard_index_ach_champion_2024'),__('admin.club_dashboard_index_ach_club_spirit'),__('admin.club_dashboard_index_ach_top_performer'),__('admin.club_dashboard_index_ach_rising_star'),__('admin.club_dashboard_index_ach_excellence')][$i] }}</span>
                </div>
                @if($i < 3)<span class="hof-star">★</span>@endif
            </div>
            @endforeach
            @endforelse
            <a href="{{ route('admin.club.achievements', $club->slug) }}" class="hof-see">{{ __('admin.club_dashboard_index_see_all_achievements') }}</a>
        </div>

        {{-- ── 6. EVENTS ── --}}
        <div class="dc dash-events">
            <div class="dc-hdr">
                <div class="dc-ttl">{{ __('admin.club_dashboard_index_events') }}</div>
                <div class="dc-ico dc-ico-p"><i class="bi bi-calendar-event"></i></div>
            </div>
            <div style="font-size:9px;color:#9ca3af;text-transform:uppercase;letter-spacing:1px;margin-bottom:4px;font-weight:600">{{ __('admin.club_dashboard_index_future_planning') }}</div>
            <div class="ev-num">{{ $stats['events'] ?? 0 }}</div>
            <span class="ev-lbl">{{ __('admin.club_dashboard_index_active_events') }}</span>
            <div class="ev-cal">
                @for($c = 0; $c < 10; $c++)
                <div class="ev-cell"></div>
                @endfor
            </div>
            <div class="ev-pend">{{ __('admin.club_dashboard_index_grading_pending') }}</div>
        </div>

        {{-- ── 7. ACTIVITIES GANTT ── --}}
        <div class="dc dash-activities">
            <div class="dc-hdr">
                <div class="dc-ttl">{{ __('admin.club_dashboard_index_activity_schedule') }}</div>
                <div class="dc-ico dc-ico-a"><i class="bi bi-bar-chart-steps"></i></div>
            </div>
            <div style="display:flex;gap:10px;align-items:stretch;flex:1;min-height:0">
                <div style="text-align:center;padding-right:10px;border-right:1px solid #f3f4f6">
                    <div style="font-size:36px;color:#111827;line-height:1">{{ $stats['activities'] ?? 0 }}</div>
                    <div style="font-size:9px;color:#9ca3af;text-transform:uppercase;letter-spacing:0.5px;font-weight:600">{{ __('admin.club_dashboard_index_total') }}</div>
                </div>
                <div style="flex:1;min-width:0;display:flex;flex-direction:column;gap:6px">
                    <div style="display:flex;gap:1px;margin-bottom:2px">
                        @for($n=1;$n<=9;$n++)
                        <div style="flex:1;text-align:center;font-size:8px;color:#9ca3af">{{ $n }}</div>
                        @endfor
                    </div>
                    @forelse($activities->take(3) as $i => $act)
                    <div class="act-row">
                        <div class="act-av" style="width:16px;height:16px;background:linear-gradient(135deg,{{ ['hsl(250 65% 60%),#3b82f6','#f97316,#dc2626','#22c55e,#16a34a'][$i%3] }})"></div>
                        <div class="act-nm" style="width:56px;font-size:8.5px">{{ Str::limit($act->name ?? __('admin.club_dashboard_index_activity_fallback'), 9) }}</div>
                        <div class="act-bars" style="height:13px">
                            <div class="act-seg" style="background:#86efac;flex:2.2"></div>
                            <div class="act-seg" style="background:#93c5fd;flex:1.4"></div>
                            <div class="act-seg" style="background:#fca5a5;flex:0.8"></div>
                            <div class="act-seg" style="background:#c4b5fd;flex:1.6"></div>
                            <div class="act-seg" style="background:#6ee7b7;flex:2"></div>
                        </div>
                    </div>
                    @empty
                    <div style="flex:1;display:flex;align-items:center;justify-content:center;color:#9ca3af;font-size:11px">{{ __('admin.club_dashboard_index_no_activities_yet') }}</div>
                    @endforelse
                </div>
            </div>
            <a href="{{ route('admin.club.activities', $club->slug) }}" class="act-see">{{ __('admin.club_dashboard_index_view_schedule') }}</a>
        </div>

        {{-- ── 8. FEATURED ACHIEVEMENTS / RATING ── --}}
        <div class="dc dash-achievements">
            <div class="dc-hdr">
                <div class="dc-ttl">{{ __('admin.club_dashboard_index_member_rating_achievements') }}</div>
                <div class="dc-ico dc-ico-s"><i class="bi bi-star-fill"></i></div>
            </div>
            <div style="display:flex;align-items:center;flex:1;min-height:0">
                <div class="ach-score-side">
                    <div class="ach-big">{{ number_format($averageRating, 1) }}</div>
                    <div class="ach-stars">
                        @for($i=1;$i<=5;$i++)
                        <i class="bi bi-star{{ $i<=floor($averageRating) ? '-fill' : '' }}" style="font-size:16px;color:{{ $i<=floor($averageRating) ? '#f59e0b' : '#e5e7eb' }}"></i>
                        @endfor
                    </div>
                    <div class="ach-meta">{{ $reviews->count() }} {{ Str::plural('review', $reviews->count()) }}</div>
                </div>
                <div class="ach-bars" style="flex:1">
                    <div class="ach-bar-row">
                        <div class="ach-bar-lbl">{{ __('admin.club_dashboard_index_bar_technique') }}</div>
                        <div class="ach-bar-track"><div class="ach-bar-fill" style="width:{{ min($averageRating*20,100) }}%"></div></div>
                        <div class="ach-bar-val">{{ number_format($averageRating,1) }}</div>
                    </div>
                    <div class="ach-bar-row">
                        <div class="ach-bar-lbl">{{ __('admin.club_dashboard_index_bar_discipline') }}</div>
                        <div class="ach-bar-track"><div class="ach-bar-fill" style="width:{{ min(max($averageRating-0.2,0)*20,100) }}%;background:#3b82f6"></div></div>
                        <div class="ach-bar-val">{{ number_format(max($averageRating-0.2,0),1) }}</div>
                    </div>
                    <div class="ach-bar-row">
                        <div class="ach-bar-lbl">{{ __('admin.club_dashboard_index_bar_focus') }}</div>
                        <div class="ach-bar-track"><div class="ach-bar-fill" style="width:{{ min(max($averageRating-0.3,0)*20,100) }}%;background:#22c55e"></div></div>
                        <div class="ach-bar-val">{{ number_format(max($averageRating-0.3,0),1) }}</div>
                    </div>
                    @if($reviews->count() === 0)
                    <div class="ach-pending">{{ __('admin.club_dashboard_index_reviews_pending') }}</div>
                    @endif
                </div>
            </div>
        </div>

        {{-- ── 9. FINANCIAL CHART (full-width) ── --}}
        <div class="dash-fiscal">
            <x-financial-chart
                :monthly-data="$monthlyData"
                :transactions="$transactions"
                :cash-to-collect="$pendingSubscriptions"
                :currency="$club->currency ?? 'BHD'"
                canvas-id="dashboardFinancialChart"
                :maintain-aspect-ratio="false"
                container-class="h-52"
            />
        </div>

    </div>{{-- end dash-grid --}}

    @if(isset($expiringSubscriptions) && count($expiringSubscriptions) > 0)
    <div style="background:#fff;border:1px solid #fde68a;border-left:4px solid #f59e0b;border-radius:14px;padding:16px">
        <h5 style="font-size:13px;color:#111827;margin:0 0 12px;display:flex;align-items:center;gap:6px">
            <i class="bi bi-exclamation-triangle" style="color:#f59e0b"></i> {{ __('admin.club_dashboard_index_expiring_subscriptions') }}
        </h5>
        <div class="overflow-x-auto">
            <table class="exp-table">
                <thead><tr>
                    <th>{{ __('admin.club_dashboard_index_th_member') }}</th><th>{{ __('admin.club_dashboard_index_th_package') }}</th><th>{{ __('admin.club_dashboard_index_th_expires') }}</th><th>{{ __('admin.club_dashboard_index_th_action') }}</th>
                </tr></thead>
                <tbody>
                @foreach($expiringSubscriptions as $sub)
                <tr>
                    <td style="font-weight:600;color:#374151">{{ $sub->user->full_name ?? __('admin.club_dashboard_index_na') }}</td>
                    <td style="color:#6b7280">{{ $sub->package->name ?? __('admin.club_dashboard_index_na') }}</td>
                    <td style="font-size:11px;color:#d97706">{{ $sub->end_date?->format('M d, Y') ?? __('admin.club_dashboard_index_na') }}</td>
                    <td><button style="background:hsl(250 60% 93%);border:none;color:hsl(250 65% 60%);border-radius:6px;padding:4px 12px;font-size:11px;font-weight:700;text-transform:uppercase;cursor:pointer">{{ __('admin.club_dashboard_index_renew') }}</button></td>
                </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // ── Members donut ──
    const ageVals  = @json(array_values($ageGroups));
    const ageLbls  = @json(array_keys($ageGroups));
    new Chart(document.getElementById('membersDonut'), {
        type: 'doughnut',
        data: {
            labels: ageLbls,
            datasets: [{
                data: ageVals.length ? ageVals : [1],
                backgroundColor: ageVals.length
                    ? ['rgba(249,115,22,0.85)','rgba(245,158,11,0.85)','rgba(124,99,224,0.85)','rgba(34,197,94,0.85)']
                    : ['#f3f4f6'],
                borderWidth: 2,
                borderColor: '#fff',
                hoverOffset: 4,
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: true,
            cutout: '68%',
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#fff',
                    titleColor: '#111827',
                    bodyColor: '#6b7280',
                    borderColor: '#e5e7eb',
                    borderWidth: 1,
                    callbacks: { label: c => ` ${c.label}: ${c.parsed}` }
                }
            },
            animation: { duration: 1000, easing: 'easeOutQuart' }
        }
    });

    // ── Package carousel ──
    const track = document.getElementById('pkgTrack');
    const dotsWrap = document.getElementById('pkgDots');
    if (track) {
        const items = track.querySelectorAll('.pkg-item');
        if (items.length === 0) return;
        const perPage = 2;
        const pages = Math.ceil(items.length / perPage);
        let page = 0;

        for (let i = 0; i < pages; i++) {
            const d = document.createElement('div');
            d.className = 'pkg-dot' + (i === 0 ? ' on' : '');
            d.onclick = () => goTo(i);
            dotsWrap.appendChild(d);
        }

        function goTo(p) {
            page = (p + pages) % pages;
            const itemW = items[0]?.offsetWidth || 0;
            track.style.transform = `translateX(-${page * perPage * (itemW + 8)}px)`;
            dotsWrap.querySelectorAll('.pkg-dot').forEach((d, i) => d.classList.toggle('on', i === page));
        }

        document.getElementById('pkgPrev')?.addEventListener('click', () => goTo(page - 1));
        document.getElementById('pkgNext')?.addEventListener('click', () => goTo(page + 1));
    }
});
</script>
@endpush

@endsection
