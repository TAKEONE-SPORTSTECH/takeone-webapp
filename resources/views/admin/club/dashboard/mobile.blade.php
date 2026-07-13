@extends('layouts.admin-club-mobile')

@section('title', ($club->club_name ?? __('admin.club')) . ' · ' . __('admin.nav_dashboard'))

@section('club-admin-content')
@php
    $cur = $club->currency ?: '';
    $monthly = collect($monthlyData);
    $income = $monthly->sum(fn($m) => (float)($m['income'] ?? 0));
    $expenses = $monthly->sum(fn($m) => (float)($m['expenses'] ?? 0));
    $net = $income - $expenses;
    $toCollect = collect($pendingSubscriptions)->sum(fn($s) => (float)($s->amount_due ?? 0));
    $maxAge = max(1, collect($ageGroups)->max() ?: 1);

    // Server-rendered sparkline of monthly income — no JS, pure SVG.
    $vals = $monthly->map(fn($m) => (float)($m['income'] ?? 0))->values();
    $n = max($vals->count(), 1);
    $peak = max($vals->max() ?: 0, 1);
    $pts = [];
    foreach ($vals as $i => $v) {
        $x = $n > 1 ? round($i / ($n - 1) * 100, 2) : 0;
        $y = round(30 - ($v / $peak) * 26 - 2, 2);
        $pts[] = "$x,$y";
    }
    $line = implode(' ', $pts);
    $area = $line !== '' ? "0,30 {$line} 100,30" : '';
@endphp
<div class="-mx-4 -mt-4">

    {{-- ===== Hero ===== --}}
    <header class="m-hero px-5 pt-7 pb-6 text-white relative overflow-hidden">
        <div class="absolute -end-8 -top-8 w-36 h-36 rounded-full bg-white/10"></div>
        <div class="flex items-center justify-between relative z-10">
            <div class="min-w-0">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-white/70 truncate">{{ $club->club_name ?? __('admin.club') }}</p>
                <h1 class="text-2xl font-black mt-0.5">{{ __('admin.nav_dashboard') }}</h1>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.club.financials', $club->slug) }}" data-shell-link data-route="admin.club.financials"
                   class="m-press w-12 h-12 rounded-2xl bg-white/20 border border-white/30 backdrop-blur grid place-items-center active:scale-95 transition-transform no-underline text-white" aria-label="{{ __('admin.nav_financials') }}">
                    <i class="bi bi-graph-up-arrow text-xl"></i>
                </a>
                <div class="w-12 h-12 rounded-2xl bg-white/15 border border-white/25 backdrop-blur grid place-items-center">
                    <i class="bi bi-speedometer2 text-xl m-float"></i>
                </div>
            </div>
        </div>

        <div class="flex gap-2 mt-5 relative z-10">
            <div class="flex-1 rounded-2xl bg-white/12 border border-white/20 backdrop-blur px-3 py-2.5">
                <p class="text-base font-black leading-none tabular-nums">{{ number_format($income, 0) }}<span class="text-[10px] font-bold ms-0.5">{{ $cur }}</span></p>
                <p class="text-[10px] text-white/75 mt-1 uppercase tracking-wide">{{ __('market.stat_revenue') }}</p>
            </div>
            <div class="flex-1 rounded-2xl bg-white/12 border border-white/20 backdrop-blur px-3 py-2.5">
                <p class="text-base font-black leading-none tabular-nums">{{ number_format($toCollect, 0) }}<span class="text-[10px] font-bold ms-0.5">{{ $cur }}</span></p>
                <p class="text-[10px] text-white/75 mt-1 uppercase tracking-wide">{{ __('admin.dash_to_collect') }}</p>
            </div>
            <div class="flex-1 rounded-2xl bg-white/12 border border-white/20 backdrop-blur px-3 py-2.5">
                <p class="text-base font-black leading-none tabular-nums">{{ $net >= 0 ? '+' : '' }}{{ number_format($net, 0) }}<span class="text-[10px] font-bold ms-0.5">{{ $cur }}</span></p>
                <p class="text-[10px] text-white/75 mt-1 uppercase tracking-wide">{{ __('admin.dash_net') }}</p>
            </div>
        </div>
    </header>

    <div class="px-4 pt-5 space-y-5">

    {{-- ===== KPI grid (nested stagger → per-card entrance) — each tile links to its section ===== --}}
    @php
        $kpis = [
            [__('admin.nav_members'), (int)($stats['members_total'] ?? 0), 'bi-people', false, 'admin.club.members'],
            [__('admin.nav_packages'), (int)($stats['packages'] ?? 0), 'bi-box', false, 'admin.club.packages'],
            [__('admin.nav_instructors'), (int)($stats['instructors'] ?? 0), 'bi-person-badge', false, 'admin.club.instructors'],
            [__('admin.nav_activities'), (int)($stats['activities'] ?? 0), 'bi-activity', false, 'admin.club.activities'],
            [__('admin.nav_events'), (int)($stats['events'] ?? 0), 'bi-calendar-event', false, 'admin.club.events'],
            [__('admin.dash_rating'), number_format($averageRating ?? 0, 1), 'bi-star-fill', true, 'admin.club.analytics'],
        ];
    @endphp
    <div class="mobile-stagger grid grid-cols-2 gap-3">
        @foreach($kpis as [$label, $value, $icon, $isFloat, $route])
            <a href="{{ route($route, $club->slug) }}" data-shell-link data-route="{{ $route }}"
               class="m-card m-press p-4 block no-underline">
                <div class="w-9 h-9 rounded-xl bg-accent flex items-center justify-center mb-2">
                    <i class="bi {{ $icon }} text-primary text-lg"></i>
                </div>
                <p class="text-2xl font-extrabold text-gray-900 tabular-nums leading-none"
                   @unless($isFloat) data-countup="{{ $value }}" @endunless>{{ $value }}</p>
                <p class="text-xs font-medium text-muted-foreground mt-1">{{ $label }}</p>
            </a>
        @endforeach
    </div>

    {{-- ===== Age groups (animated bars) — tap to open Members ===== --}}
    <a href="{{ route('admin.club.members', $club->slug) }}" data-shell-link data-route="admin.club.members"
       class="m-press block m-card p-4 no-underline">
        <div class="flex items-center justify-between mb-3">
            <div class="flex items-center gap-2">
                <i class="bi bi-bar-chart-steps text-primary"></i>
                <h3 class="font-bold text-foreground">{{ __('admin.age_groups') }}</h3>
            </div>
            <i class="bi bi-chevron-right text-[10px] text-muted-foreground"></i>
        </div>
        <div class="space-y-3">
            @foreach($ageGroups as $label => $count)
                <div>
                    <div class="flex justify-between text-xs mb-1.5">
                        <span class="text-muted-foreground font-medium">{{ $label }}</span>
                        <span class="font-bold text-foreground tabular-nums">{{ $count }}</span>
                    </div>
                    <div class="h-2.5 rounded-full bg-muted overflow-hidden">
                        <div class="m-bar-fill h-full rounded-full bg-primary"
                             style="width: {{ $count > 0 ? max(4, round($count / $maxAge * 100)) : 0 }}%"></div>
                    </div>
                </div>
            @endforeach
        </div>
    </a>

    {{-- ===== Packages ===== --}}
    <div class="m-card p-4">
        <div class="flex items-center justify-between mb-3">
            <div class="flex items-center gap-2">
                <i class="bi bi-box text-primary"></i>
                <h3 class="font-bold text-foreground">{{ __('admin.nav_packages') }}</h3>
            </div>
            <a href="{{ route('admin.club.packages', $club->slug) }}" data-shell-link data-route="admin.club.packages" class="text-xs text-primary font-semibold">{{ __('admin.view_all') }} <i class="bi bi-chevron-right text-[10px]"></i></a>
        </div>
        @if($packages->isEmpty())
            <p class="text-sm text-muted-foreground">{{ __('admin.no_packages_yet') }}</p>
        @else
            <div class="divide-y divide-gray-50">
                @foreach($packages->take(4) as $pkg)
                    <a href="{{ route('admin.club.packages', $club->slug) }}" data-shell-link data-route="admin.club.packages"
                       class="flex items-center justify-between py-2.5 no-underline">
                        <span class="text-sm text-foreground truncate">{{ $pkg->name ?? $pkg->package_name ?? __('admin.package') }}</span>
                        <span class="text-sm font-bold text-foreground flex-shrink-0 ml-2 tabular-nums">{{ $cur }} {{ number_format((float)($pkg->price ?? 0), 0) }}</span>
                    </a>
                @endforeach
            </div>
        @endif
    </div>

    {{-- ===== Instructors ===== --}}
    <div class="m-card p-4">
        <div class="flex items-center justify-between mb-3">
            <div class="flex items-center gap-2">
                <i class="bi bi-person-badge text-primary"></i>
                <h3 class="font-bold text-foreground">{{ __('admin.nav_instructors') }}</h3>
            </div>
            <a href="{{ route('admin.club.instructors', $club->slug) }}" data-shell-link data-route="admin.club.instructors" class="text-xs text-primary font-semibold">{{ __('admin.view_all') }} <i class="bi bi-chevron-right text-[10px]"></i></a>
        </div>
        @if($instructors->isEmpty())
            <p class="text-sm text-muted-foreground">{{ __('admin.no_instructors_yet') }}</p>
        @else
            <div class="space-y-3">
                @foreach($instructors as $ins)
                    @php $isOwner = $club->owner_user_id && (int) $ins->user_id === (int) $club->owner_user_id; @endphp
                    <a href="{{ route('admin.club.instructors', $club->slug) }}" data-shell-link data-route="admin.club.instructors"
                       class="flex items-center gap-3 no-underline">
                        <span class="relative w-10 h-10 rounded-full bg-muted flex items-center justify-center overflow-hidden flex-shrink-0 ring-2 {{ $isOwner ? 'ring-amber-300' : 'ring-accent' }}">
                            @if($ins->user && $ins->user->profile_picture)
                                <img src="{{ asset('storage/'.$ins->user->profile_picture) }}" alt="" class="w-10 h-10 object-cover">
                            @else
                                <i class="bi bi-person text-muted-foreground"></i>
                            @endif
                        </span>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-foreground truncate flex items-center gap-1.5">
                                <span class="truncate">{{ $ins->user->full_name ?? __('admin.instructor') }}</span>
                                @if($isOwner)<i class="bi bi-crown-fill text-amber-400 text-xs flex-shrink-0" title="{{ __('admin.owner') }}"></i>@endif
                            </p>
                            <p class="text-xs text-muted-foreground truncate">{{ $isOwner ? __('admin.owner') : ($ins->role ?? __('admin.instructor')) }}</p>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>

    </div>{{-- /content --}}
</div>
@endsection
