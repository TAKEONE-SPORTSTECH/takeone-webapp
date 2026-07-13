@extends('layouts.admin')

@section('admin-content')
@php
    $admin = auth()->user();
    $firstName = trim(explode(' ', $admin?->full_name ?? $admin?->name ?? 'Admin')[0] ?? 'Admin');

    // Member growth trend (this month vs last month)
    $mThis = $stats['membersThisMonth'] ?? 0;
    $mLast = $stats['membersLastMonth'] ?? 0;
    $mDelta = $mLast > 0 ? round((($mThis - $mLast) / $mLast) * 100) : ($mThis > 0 ? 100 : 0);
    $mUp = $mThis >= $mLast;

    $flat = array_fill(0, 12, 0);
    $memberSeries = $series['members'] ?? $flat;
    $clubSeries   = $series['clubs'] ?? $flat;
    $seriesLabels = $series['labels'] ?? [];
@endphp

<div x-data class="space-y-6">

    {{-- ═══ HERO / CONTROL CENTER BANNER ═══ --}}
    <div class="relative overflow-hidden rounded-2xl text-white shadow-lg"
         style="background: linear-gradient(135deg, hsl(250 65% 58%) 0%, hsl(262 62% 48%) 55%, hsl(275 55% 42%) 100%);">
        {{-- decorative glow --}}
        <div class="pointer-events-none absolute -top-16 -right-10 w-72 h-72 rounded-full" style="background:radial-gradient(circle, rgba(255,255,255,.18), transparent 70%);"></div>
        <div class="pointer-events-none absolute -bottom-24 left-1/4 w-72 h-72 rounded-full" style="background:radial-gradient(circle, rgba(255,255,255,.10), transparent 70%);"></div>

        <div class="relative flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6 p-6 lg:p-8">
            <div class="min-w-0">
                <span class="inline-flex items-center gap-2 text-[11px] font-bold tracking-[0.18em] uppercase text-white/80">
                    <i class="bi bi-shield-lock-fill"></i> {{ __('platform.admin_platform_dashboard_control_center') }}
                </span>
                <h1 class="mt-2 text-3xl lg:text-4xl font-extrabold leading-tight">{{ __('platform.admin_platform_dashboard_welcome_back', ['name' => $firstName]) }}</h1>
                <p class="mt-1.5 text-sm text-white/85 max-w-xl">{{ __('platform.admin_platform_dashboard_intro') }}</p>

                <div class="mt-5 flex flex-wrap gap-2.5">
                    <a href="{{ route('admin.platform.clubs') }}"
                       class="inline-flex items-center gap-2 bg-white text-primary px-4 py-2 rounded-lg text-sm font-semibold hover:bg-white/90 transition-colors shadow-sm">
                        <i class="bi bi-building"></i> {{ __('platform.admin_platform_dashboard_manage_clubs') }}
                    </a>
                    <a href="{{ route('admin.platform.members') }}"
                       class="inline-flex items-center gap-2 bg-white/15 text-white border border-white/30 px-4 py-2 rounded-lg text-sm font-semibold hover:bg-white/25 transition-colors">
                        <i class="bi bi-people"></i> {{ __('platform.admin_platform_dashboard_all_members') }}
                    </a>
                    @if(($stats['businessesPending'] ?? 0) > 0)
                        <a href="{{ route('admin.platform.businesses') }}"
                           class="inline-flex items-center gap-2 bg-amber-300 text-amber-900 px-4 py-2 rounded-lg text-sm font-semibold hover:bg-amber-200 transition-colors">
                            <i class="bi bi-hourglass-split"></i> {{ $stats['businessesPending'] }} {{ $stats['businessesPending'] === 1 ? __('platform.admin_platform_dashboard_pending_approval_singular') : __('platform.admin_platform_dashboard_pending_approval_plural') }}
                        </a>
                    @endif
                </div>
            </div>

            {{-- Headline counters --}}
            <div class="flex gap-4 shrink-0">
                <div class="text-center rounded-xl bg-white/10 border border-white/15 px-5 py-3 min-w-[96px]">
                    <div class="text-3xl font-extrabold leading-none">{{ number_format($stats['clubs'] ?? 0) }}</div>
                    <div class="mt-1 text-[11px] font-semibold uppercase tracking-wide text-white/80">{{ __('platform.admin_platform_dashboard_clubs') }}</div>
                </div>
                <div class="text-center rounded-xl bg-white/10 border border-white/15 px-5 py-3 min-w-[96px]">
                    <div class="text-3xl font-extrabold leading-none">{{ number_format($stats['members'] ?? 0) }}</div>
                    <div class="mt-1 text-[11px] font-semibold uppercase tracking-wide text-white/80">{{ __('platform.admin_platform_dashboard_athletes') }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══ KPI STAT CARDS ═══ --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
        <x-stat-card
            :label="__('platform.admin_platform_dashboard_total_members')" :value="number_format($stats['members'] ?? 0)"
            icon="bi-people-fill" icon-bg="bg-accent" icon-color="text-primary"
            :spark-data="$memberSeries" :spark-labels="$seriesLabels" spark-color="hsl(250 65% 60%)"
            :trend="($mUp ? '+' : '') . $mDelta . '%'" :trend-up="$mUp" :sub-label="__('platform.admin_platform_dashboard_vs_last_month')" />

        <x-stat-card
            :label="__('platform.admin_platform_dashboard_active_clubs')" :value="number_format($stats['clubs'] ?? 0)"
            icon="bi-building-fill" icon-bg="bg-blue-50" icon-color="text-blue-600"
            :spark-data="$clubSeries" :spark-labels="$seriesLabels" spark-color="#2563eb"
            :sub-label="'+' . ($stats['clubsThisMonth'] ?? 0) . ' ' . __('platform.admin_platform_dashboard_this_month')" />

        <x-stat-card
            :label="__('platform.admin_platform_dashboard_businesses')" :value="number_format($stats['businesses'] ?? 0)"
            icon="bi-buildings-fill" icon-bg="bg-green-50" icon-color="text-green-600"
            :spark-data="$flat" :sub-label="($stats['businessesPending'] ?? 0) . ' ' . __('platform.admin_platform_dashboard_pending')" />

        <x-stat-card
            :label="__('platform.admin_platform_dashboard_trainers')" :value="number_format($stats['trainers'] ?? 0)"
            icon="bi-person-badge-fill" icon-bg="bg-purple-50" icon-color="text-primary"
            :spark-data="$flat" :sub-label="number_format($stats['packages'] ?? 0) . ' ' . __('platform.admin_platform_dashboard_packages')" />
    </div>

    {{-- ═══ MAIN GRID: growth chart + pending queue ═══ --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Growth chart --}}
        <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="text-base font-bold text-gray-900">{{ __('platform.admin_platform_dashboard_platform_growth') }}</h2>
                    <p class="text-xs text-muted-foreground">{{ __('platform.admin_platform_dashboard_growth_subtitle') }}</p>
                </div>
                <div class="flex items-center gap-4 text-xs">
                    <span class="inline-flex items-center gap-1.5 text-muted-foreground"><span class="w-2.5 h-2.5 rounded-full" style="background:hsl(250 65% 60%)"></span>{{ __('platform.admin_platform_dashboard_members') }}</span>
                    <span class="inline-flex items-center gap-1.5 text-muted-foreground"><span class="w-2.5 h-2.5 rounded-full" style="background:#2563eb"></span>{{ __('platform.admin_platform_dashboard_clubs') }}</span>
                </div>
            </div>
            <div style="height:280px"><canvas id="pa-growth-chart"></canvas></div>
        </div>

        {{-- Pending approvals + quick actions --}}
        <div class="space-y-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-base font-bold text-gray-900">{{ __('platform.admin_platform_dashboard_pending_approvals') }}</h2>
                    @if($pendingBusinesses->count() > 0)
                        <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-700">{{ $pendingBusinesses->count() }}</span>
                    @endif
                </div>
                @forelse($pendingBusinesses as $biz)
                    <a href="{{ route('admin.platform.businesses') }}" class="flex items-center gap-3 p-2.5 -mx-1 rounded-lg hover:bg-muted/60 transition-colors no-underline">
                        <span class="w-9 h-9 rounded-lg bg-accent flex items-center justify-center shrink-0 overflow-hidden">
                            @if($biz->logo)
                                <img src="{{ asset('storage/'.$biz->logo) }}" alt="" class="w-9 h-9 object-cover rounded-lg">
                            @else
                                <i class="bi bi-buildings text-primary"></i>
                            @endif
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-foreground truncate">{{ $biz->name }}</p>
                            <p class="text-xs text-muted-foreground truncate">{{ $biz->owner?->full_name ?? __('platform.admin_platform_dashboard_unknown_owner') }} · {{ $biz->clubs_count }} {{ \Illuminate\Support\Str::plural('club', $biz->clubs_count) }}</p>
                        </div>
                        <i class="bi bi-chevron-right text-muted-foreground text-xs shrink-0"></i>
                    </a>
                @empty
                    <div class="text-center py-6">
                        <i class="bi bi-check2-circle text-3xl text-green-500"></i>
                        <p class="text-sm text-muted-foreground mt-2">{{ __('platform.admin_platform_dashboard_all_caught_up') }}</p>
                    </div>
                @endforelse
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <h2 class="text-base font-bold text-gray-900 mb-3">{{ __('platform.admin_platform_dashboard_quick_actions') }}</h2>
                <div class="grid grid-cols-2 gap-2.5">
                    @php
                        $actions = [
                            ['admin.platform.clubs', 'bi-building-add', __('platform.admin_platform_dashboard_action_clubs')],
                            ['admin.platform.members', 'bi-person-plus', __('platform.admin_platform_dashboard_action_members')],
                            ['admin.platform.businesses', 'bi-buildings', __('platform.admin_platform_dashboard_action_businesses')],
                            ['admin.platform.audit-log', 'bi-journal-text', __('platform.admin_platform_dashboard_action_audit_log')],
                        ];
                    @endphp
                    @foreach($actions as [$r, $i, $l])
                        <a href="{{ route($r) }}" class="flex flex-col items-center gap-1.5 py-3 rounded-lg border border-gray-100 hover:border-primary/30 hover:bg-accent/40 transition-colors no-underline">
                            <i class="bi {{ $i }} text-lg text-primary"></i>
                            <span class="text-xs font-medium text-gray-700">{{ $l }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- ═══ TOP CLUBS + RECENT MEMBERS ═══ --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {{-- Top clubs leaderboard --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-base font-bold text-gray-900"><i class="bi bi-trophy-fill text-amber-400 me-1.5"></i>{{ __('platform.admin_platform_dashboard_top_clubs') }}</h2>
                <a href="{{ route('admin.platform.clubs') }}" class="text-xs text-primary hover:underline">{{ __('platform.admin_platform_dashboard_view_all') }}</a>
            </div>
            <div class="space-y-1">
                @forelse($topClubs as $i => $club)
                    <a href="{{ route('admin.club.dashboard', $club->slug) }}" class="flex items-center gap-3 p-2 -mx-1 rounded-lg hover:bg-muted/60 transition-colors no-underline">
                        <span class="w-6 text-center font-extrabold {{ $i === 0 ? 'text-amber-400' : ($i === 1 ? 'text-gray-400' : ($i === 2 ? 'text-amber-700' : 'text-gray-300')) }}">{{ $i + 1 }}</span>
                        <span class="w-9 h-9 rounded-lg overflow-hidden shrink-0 bg-accent flex items-center justify-center">
                            @if($club->logo)
                                <img src="{{ asset('storage/'.$club->logo) }}" alt="" class="w-9 h-9 object-contain">
                            @else
                                <span class="text-primary font-bold">{{ mb_strtoupper(mb_substr($club->club_name, 0, 1)) }}</span>
                            @endif
                        </span>
                        <span class="flex-1 min-w-0 text-sm font-semibold text-foreground truncate">{{ $club->club_name }}</span>
                        <span class="inline-flex items-center gap-1 text-xs font-medium text-muted-foreground"><i class="bi bi-people"></i>{{ $club->members_count }}</span>
                    </a>
                @empty
                    <p class="text-sm text-muted-foreground py-4 text-center">{{ __('platform.admin_platform_dashboard_no_clubs_yet') }}</p>
                @endforelse
            </div>
        </div>

        {{-- Recent members --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-base font-bold text-gray-900"><i class="bi bi-stars text-primary me-1.5"></i>{{ __('platform.admin_platform_dashboard_newest_members') }}</h2>
                <a href="{{ route('admin.platform.members') }}?sort=newest" class="text-xs text-primary hover:underline">{{ __('platform.admin_platform_dashboard_view_all') }}</a>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                @forelse($recentMembers as $m)
                    <a href="{{ route('member.show', $m->uuid) }}" class="flex items-center gap-2.5 p-2 rounded-lg hover:bg-muted/60 transition-colors no-underline">
                        <span class="w-9 h-9 rounded-full overflow-hidden shrink-0">
                            @if($m->profile_picture)
                                <img src="{{ asset('storage/'.$m->profile_picture) }}?v={{ $m->updated_at?->timestamp }}" alt="" class="w-9 h-9 object-cover">
                            @else
                                <x-gender-avatar :gender="$m->gender" class="w-9 h-9 rounded-full" />
                            @endif
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-foreground truncate">{{ $m->full_name ?? __('platform.admin_platform_dashboard_unknown') }}</p>
                            <p class="text-xs text-muted-foreground">{{ $m->created_at?->diffForHumans() }}</p>
                        </div>
                    </a>
                @empty
                    <p class="text-sm text-muted-foreground py-4 text-center col-span-2">{{ __('platform.admin_platform_dashboard_no_members_yet') }}</p>
                @endforelse
            </div>
        </div>
    </div>
</div>

@php
    $growthLabels  = $seriesLabels;
    $growthMembers = $memberSeries;
    $growthClubs   = $clubSeries;
@endphp
@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const el = document.getElementById('pa-growth-chart');
        if (!el || typeof Chart === 'undefined') return;
        const ctx = el.getContext('2d');

        const gMembers = ctx.createLinearGradient(0, 0, 0, 280);
        gMembers.addColorStop(0, 'hsla(250, 65%, 60%, 0.28)');
        gMembers.addColorStop(1, 'hsla(250, 65%, 60%, 0)');

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: @json($growthLabels),
                datasets: [
                    {
                        label: '{{ __('platform.admin_platform_dashboard_members') }}', data: @json($growthMembers),
                        borderColor: 'hsl(250, 65%, 60%)', backgroundColor: gMembers,
                        fill: true, tension: 0.4, borderWidth: 2.5,
                        pointRadius: 0, pointHoverRadius: 5, pointHoverBackgroundColor: 'hsl(250, 65%, 60%)',
                    },
                    {
                        label: '{{ __('platform.admin_platform_dashboard_clubs') }}', data: @json($growthClubs),
                        borderColor: '#2563eb', backgroundColor: 'transparent',
                        fill: false, tension: 0.4, borderWidth: 2, borderDash: [4, 4],
                        pointRadius: 0, pointHoverRadius: 5, pointHoverBackgroundColor: '#2563eb',
                    },
                ],
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: { legend: { display: false }, tooltip: { padding: 10, cornerRadius: 10, usePointStyle: true } },
                scales: {
                    x: { grid: { display: false }, ticks: { color: '#9ca3af', font: { size: 11 } } },
                    y: { beginAtZero: true, grid: { color: '#f1f0f6' }, ticks: { color: '#9ca3af', font: { size: 11 }, precision: 0 } },
                },
            },
        });
    });
</script>
@endpush
@endsection
