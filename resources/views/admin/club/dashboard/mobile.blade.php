@extends('layouts.admin-club-mobile')

@section('title', ($club->club_name ?? 'Club') . ' · Dashboard')

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
<div class="space-y-5">

    {{-- ===== Revenue hero ===== --}}
    <div class="m-hero rounded-2xl text-white p-5 shadow-lg">
        <div class="relative z-10">
            <div class="flex items-center justify-between">
                <p class="text-xs font-semibold text-white/80 uppercase tracking-wider">Revenue · 12 mo</p>
                <span class="inline-flex items-center gap-1 text-[11px] font-medium bg-white/15 backdrop-blur rounded-full px-2.5 py-1">
                    <i class="bi bi-graph-up-arrow"></i> {{ $net >= 0 ? '+' : '' }}{{ $cur }} {{ number_format($net, 0) }} net
                </span>
            </div>
            <p class="text-[2rem] leading-tight font-extrabold mt-1 tabular-nums"
               data-countup="{{ (int) round($income) }}" data-prefix="{{ $cur ? $cur.' ' : '' }}">{{ $cur ? $cur.' ' : '' }}{{ number_format($income, 0) }}</p>

            {{-- Sparkline --}}
            @if($area)
            <svg viewBox="0 0 100 30" preserveAspectRatio="none" class="w-full h-12 mt-2 overflow-visible">
                <defs>
                    <linearGradient id="mDashSpark" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="rgba(255,255,255,.55)"/>
                        <stop offset="100%" stop-color="rgba(255,255,255,0)"/>
                    </linearGradient>
                </defs>
                <polygon points="{{ $area }}" fill="url(#mDashSpark)" class="m-in-fade"/>
                <polyline points="{{ $line }}" fill="none" stroke="#fff" stroke-width="1.6"
                          stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke"
                          style="stroke-dasharray:240;stroke-dashoffset:240;animation:m-fade .3s both;animation-delay:.2s">
                    <animate attributeName="stroke-dashoffset" from="240" to="0" dur="1.1s" begin="0.25s" fill="freeze" calcMode="spline" keySplines="0.22 0.61 0.36 1"/>
                </polyline>
            </svg>
            @endif

            <div class="flex items-center gap-2 mt-3">
                <div class="flex-1 bg-white/12 rounded-xl px-3 py-2">
                    <p class="text-[10px] text-white/70 uppercase tracking-wide">To collect</p>
                    <p class="text-sm font-bold tabular-nums">{{ $cur }} {{ number_format($toCollect, 0) }}</p>
                </div>
                <div class="flex-1 bg-white/12 rounded-xl px-3 py-2">
                    <p class="text-[10px] text-white/70 uppercase tracking-wide">Expenses</p>
                    <p class="text-sm font-bold tabular-nums">{{ $cur }} {{ number_format($expenses, 0) }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- ===== KPI grid (nested stagger → per-card entrance) ===== --}}
    @php
        $kpis = [
            ['Members', (int)($stats['members_total'] ?? 0), 'bi-people', false],
            ['Packages', (int)($stats['packages'] ?? 0), 'bi-box', false],
            ['Instructors', (int)($stats['instructors'] ?? 0), 'bi-person-badge', false],
            ['Activities', (int)($stats['activities'] ?? 0), 'bi-activity', false],
            ['Events', (int)($stats['events'] ?? 0), 'bi-calendar-event', false],
            ['Rating', number_format($averageRating ?? 0, 1), 'bi-star-fill', true],
        ];
    @endphp
    <div class="mobile-stagger grid grid-cols-2 gap-3">
        @foreach($kpis as [$label, $value, $icon, $isFloat])
            <div class="m-card m-press p-4">
                <div class="w-9 h-9 rounded-xl bg-accent flex items-center justify-center mb-2">
                    <i class="bi {{ $icon }} text-primary text-lg"></i>
                </div>
                <p class="text-2xl font-extrabold text-gray-900 tabular-nums leading-none"
                   @unless($isFloat) data-countup="{{ $value }}" @endunless>{{ $value }}</p>
                <p class="text-xs font-medium text-muted-foreground mt-1">{{ $label }}</p>
            </div>
        @endforeach
    </div>

    {{-- ===== Age groups (animated bars) ===== --}}
    <div class="m-card p-4">
        <div class="flex items-center gap-2 mb-3">
            <i class="bi bi-bar-chart-steps text-primary"></i>
            <h3 class="font-bold text-foreground">Age groups</h3>
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
    </div>

    {{-- ===== Packages ===== --}}
    <div class="m-card p-4">
        <div class="flex items-center justify-between mb-3">
            <div class="flex items-center gap-2">
                <i class="bi bi-box text-primary"></i>
                <h3 class="font-bold text-foreground">Packages</h3>
            </div>
            <a href="{{ route('admin.club.packages', $club->slug) }}" data-shell-link data-route="admin.club.packages" class="text-xs text-primary font-semibold">View all <i class="bi bi-chevron-right text-[10px]"></i></a>
        </div>
        @if($packages->isEmpty())
            <p class="text-sm text-muted-foreground">No packages yet.</p>
        @else
            <div class="divide-y divide-gray-50">
                @foreach($packages->take(4) as $pkg)
                    <div class="flex items-center justify-between py-2.5">
                        <span class="text-sm text-foreground truncate">{{ $pkg->name ?? $pkg->package_name ?? 'Package' }}</span>
                        <span class="text-sm font-bold text-foreground flex-shrink-0 ml-2 tabular-nums">{{ $cur }} {{ number_format((float)($pkg->price ?? 0), 0) }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- ===== Instructors ===== --}}
    <div class="m-card p-4">
        <div class="flex items-center justify-between mb-3">
            <div class="flex items-center gap-2">
                <i class="bi bi-person-badge text-primary"></i>
                <h3 class="font-bold text-foreground">Instructors</h3>
            </div>
            <a href="{{ route('admin.club.instructors', $club->slug) }}" data-shell-link data-route="admin.club.instructors" class="text-xs text-primary font-semibold">View all <i class="bi bi-chevron-right text-[10px]"></i></a>
        </div>
        @if($instructors->isEmpty())
            <p class="text-sm text-muted-foreground">No instructors yet.</p>
        @else
            <div class="space-y-3">
                @foreach($instructors as $ins)
                    <div class="flex items-center gap-3">
                        <span class="w-10 h-10 rounded-full bg-muted flex items-center justify-center overflow-hidden flex-shrink-0 ring-2 ring-accent">
                            @if($ins->user && $ins->user->profile_picture)
                                <img src="{{ asset('storage/'.$ins->user->profile_picture) }}" alt="" class="w-10 h-10 object-cover">
                            @else
                                <i class="bi bi-person text-muted-foreground"></i>
                            @endif
                        </span>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-foreground truncate">{{ $ins->user->full_name ?? 'Instructor' }}</p>
                            <p class="text-xs text-muted-foreground truncate">{{ $ins->role ?? 'Instructor' }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

</div>
@endsection
