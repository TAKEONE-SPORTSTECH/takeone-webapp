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

    // This month, from the same 12-month series the chart draws.
    $thisMonth   = $monthly->last() ?: [];
    $mIncome     = (float) ($thisMonth['income'] ?? 0);
    $mExpenses   = (float) ($thisMonth['expenses'] ?? 0);
    $mNet        = $mIncome - $mExpenses;
    $newMembers  = (int) (collect($monthlyTrend ?? [])->last() ?? 0);

    // Who still owes money — the one thing on this page that needs acting on.
    $pending     = collect($pendingSubscriptions)->take(3);
    $pendingAll  = collect($pendingSubscriptions)->count();

    $recentTx    = collect($transactions ?? [])->take(3);
    $males       = (int) (($genderStats['Male'] ?? 0));
    $females     = (int) (($genderStats['Female'] ?? 0));
    $genderTotal = max(1, $males + $females);
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

    <div class="px-4 pt-4 space-y-3">

    {{-- ===== Counts — one card, three per row, hairline-separated ===== --}}
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
    {{-- gap-px over a tinted backdrop draws the hairlines: `divide-*` is DOM-order
         based and would put a stray rule at the start of the second row. --}}
    <div class="m-card grid grid-cols-3 gap-px bg-gray-100 overflow-hidden">
        @foreach($kpis as [$label, $value, $icon, $isFloat, $route])
            <a href="{{ route($route, $club->slug) }}" data-shell-link data-route="{{ $route }}"
               class="m-press bg-white px-2 py-3 flex flex-col items-center gap-0.5 no-underline text-center">
                <i class="bi {{ $icon }} text-primary text-sm"></i>
                <span class="text-lg font-extrabold text-gray-900 tabular-nums leading-none"
                      @unless($isFloat) data-countup="{{ $value }}" @endunless>{{ $value }}</span>
                <span class="text-[10px] font-medium text-muted-foreground leading-tight truncate w-full">{{ $label }}</span>
            </a>
        @endforeach
    </div>

    {{-- ===== This month — the numbers a club owner opens the app for, over the
         12-month revenue curve for context. Pure SVG, no chart library. ===== --}}
    <a href="{{ route('admin.club.financials', $club->slug) }}" data-shell-link data-route="admin.club.financials"
       class="m-press block m-card p-3.5 no-underline overflow-hidden">
        <div class="flex items-center justify-between mb-2">
            <h3 class="text-xs font-bold uppercase tracking-wide text-muted-foreground">{{ __('admin.dash_this_month') }}</h3>
            <i class="bi bi-chevron-right text-[10px] text-muted-foreground rtl:rotate-180"></i>
        </div>

        <div class="flex items-baseline gap-1.5">
            <span class="text-2xl font-black tabular-nums {{ $mNet >= 0 ? 'text-emerald-600' : 'text-destructive' }}">{{ $mNet >= 0 ? '+' : '−' }}{{ number_format(abs($mNet), 0) }}</span>
            <span class="text-[11px] font-bold text-muted-foreground">{{ $cur }} · {{ __('admin.dash_net') }}</span>
        </div>

        <svg viewBox="0 0 100 30" preserveAspectRatio="none" class="w-full h-12 mt-2 overflow-visible" aria-hidden="true">
            <defs>
                <linearGradient id="dashSpark" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="currentColor" stop-opacity="0.20" />
                    <stop offset="100%" stop-color="currentColor" stop-opacity="0" />
                </linearGradient>
            </defs>
            <g class="text-primary">
                @if($area !== '')<polygon points="{{ $area }}" fill="url(#dashSpark)" />@endif
                @if($line !== '')<polyline points="{{ $line }}" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke" />@endif
            </g>
        </svg>

        <div class="grid grid-cols-3 gap-2 mt-2 pt-2.5 border-t border-gray-100 text-center">
            <div>
                <p class="text-sm font-bold text-foreground tabular-nums">{{ number_format($mIncome, 0) }}</p>
                <p class="text-[10px] text-muted-foreground">{{ __('market.stat_revenue') }}</p>
            </div>
            <div>
                <p class="text-sm font-bold text-foreground tabular-nums">{{ number_format($mExpenses, 0) }}</p>
                <p class="text-[10px] text-muted-foreground">{{ __('admin.dash_expenses') }}</p>
            </div>
            <div>
                <p class="text-sm font-bold text-amber-600 tabular-nums">{{ number_format($toCollect, 0) }}</p>
                <p class="text-[10px] text-muted-foreground">{{ __('admin.dash_to_collect') }}</p>
            </div>
        </div>
    </a>

    {{-- ===== Pending payments — the only actionable item on the page, so it sits
         high and states who owes what rather than just a total. ===== --}}
    <div class="m-card p-3.5">
        <div class="flex items-center justify-between mb-1">
            <h3 class="text-xs font-bold uppercase tracking-wide text-muted-foreground">
                {{ __('admin.fin_pending_payments') }}
                @if($pendingAll > 0)<span class="ms-1 px-1.5 py-0.5 rounded-full bg-amber-100 text-amber-700 text-[10px] font-bold tabular-nums">{{ $pendingAll }}</span>@endif
            </h3>
            @if($pendingAll > 0)
                <a href="{{ route('admin.club.members', $club->slug) }}" data-shell-link data-route="admin.club.members" class="text-[11px] text-primary font-semibold no-underline">{{ __('admin.view_all') }}</a>
            @endif
        </div>
        @if($pendingAll === 0)
            <p class="text-xs text-muted-foreground pt-1.5 flex items-center gap-1.5"><i class="bi bi-check-circle text-emerald-500"></i>{{ __('admin.dash_all_settled') }}</p>
        @else
            <div class="divide-y divide-gray-50">
                @foreach($pending as $sub)
                    <a href="{{ route('admin.club.members', $club->slug) }}" data-shell-link data-route="admin.club.members"
                       class="flex items-center gap-2.5 py-2 no-underline">
                        <span class="w-7 h-7 rounded-full bg-amber-50 text-amber-600 grid place-items-center flex-shrink-0"><i class="bi bi-hourglass-split text-[11px]"></i></span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-[13px] font-medium text-foreground truncate">{{ $sub->user->full_name ?? __('admin.fin_member') }}</span>
                            <span class="block text-[10px] text-muted-foreground truncate">{{ $sub->package->name ?? $sub->package->package_name ?? '' }}</span>
                        </span>
                        <span class="text-[13px] font-bold text-amber-600 tabular-nums flex-shrink-0">{{ number_format((float) ($sub->amount_due ?? 0), 0) }} {{ $cur }}</span>
                    </a>
                @endforeach
            </div>
        @endif
    </div>

    {{-- ===== Members — age spread, gender split and this month's joins in one card ===== --}}
    <a href="{{ route('admin.club.members', $club->slug) }}" data-shell-link data-route="admin.club.members"
       class="m-press block m-card p-3.5 no-underline">
        <div class="flex items-center justify-between mb-2.5">
            <h3 class="text-xs font-bold uppercase tracking-wide text-muted-foreground">{{ __('admin.dash_members_mix') }}</h3>
            @if($newMembers > 0)
                <span class="text-[10px] font-bold text-emerald-600">+{{ $newMembers }} {{ __('admin.dash_new_members') }}</span>
            @else
                <i class="bi bi-chevron-right text-[10px] text-muted-foreground rtl:rotate-180"></i>
            @endif
        </div>

        <div class="space-y-2">
            @foreach($ageGroups as $label => $count)
                <div class="flex items-center gap-2.5">
                    <span class="text-[11px] text-muted-foreground w-16 flex-shrink-0 truncate">{{ $label }}</span>
                    <span class="flex-1 h-1.5 rounded-full bg-muted overflow-hidden">
                        <span class="m-bar-fill block h-full rounded-full bg-primary"
                              style="width: {{ $count > 0 ? max(4, round($count / $maxAge * 100)) : 0 }}%"></span>
                    </span>
                    <span class="text-[11px] font-bold text-foreground tabular-nums w-6 text-end flex-shrink-0">{{ $count }}</span>
                </div>
            @endforeach
        </div>

        @if($males + $females > 0)
            <div class="mt-3 pt-2.5 border-t border-gray-100">
                <div class="flex h-1.5 rounded-full overflow-hidden bg-muted">
                    <span class="bg-sky-400 m-bar-fill" style="width: {{ round($males / $genderTotal * 100) }}%"></span>
                    <span class="bg-pink-400 m-bar-fill" style="width: {{ round($females / $genderTotal * 100) }}%"></span>
                </div>
                <div class="flex justify-between text-[10px] text-muted-foreground mt-1.5">
                    <span><i class="bi bi-circle-fill text-sky-400 text-[6px] me-1"></i>{{ __('admin.club_members_index_gender_male') }} {{ $males }}</span>
                    <span>{{ __('admin.club_members_index_gender_female') }} {{ $females }}<i class="bi bi-circle-fill text-pink-400 text-[6px] ms-1"></i></span>
                </div>
            </div>
        @endif
    </a>

    {{-- ===== Packages — tight rows ===== --}}
    <div class="m-card p-3.5">
        <div class="flex items-center justify-between mb-1">
            <h3 class="text-xs font-bold uppercase tracking-wide text-muted-foreground">{{ __('admin.nav_packages') }}</h3>
            <a href="{{ route('admin.club.packages', $club->slug) }}" data-shell-link data-route="admin.club.packages" class="text-[11px] text-primary font-semibold no-underline">{{ __('admin.view_all') }}</a>
        </div>
        @if($packages->isEmpty())
            <p class="text-xs text-muted-foreground pt-1.5">{{ __('admin.no_packages_yet') }}</p>
        @else
            <div class="divide-y divide-gray-50">
                @foreach($packages->take(4) as $pkg)
                    <a href="{{ route('admin.club.packages', $club->slug) }}" data-shell-link data-route="admin.club.packages"
                       class="flex items-center justify-between gap-2 py-2 no-underline">
                        <span class="text-[13px] text-foreground truncate">{{ $pkg->name ?? $pkg->package_name ?? __('admin.package') }}</span>
                        <span class="text-[13px] font-bold text-foreground flex-shrink-0 tabular-nums">{{ $cur }} {{ number_format((float)($pkg->price ?? 0), 0) }}</span>
                    </a>
                @endforeach
            </div>
        @endif
    </div>

    {{-- ===== Instructors — a swipeable face rail instead of a stack of full-width rows ===== --}}
    <div class="m-card p-3.5">
        <div class="flex items-center justify-between mb-2.5">
            <h3 class="text-xs font-bold uppercase tracking-wide text-muted-foreground">{{ __('admin.nav_instructors') }}</h3>
            <a href="{{ route('admin.club.instructors', $club->slug) }}" data-shell-link data-route="admin.club.instructors" class="text-[11px] text-primary font-semibold no-underline">{{ __('admin.view_all') }}</a>
        </div>
        @if($instructors->isEmpty())
            <p class="text-xs text-muted-foreground">{{ __('admin.no_instructors_yet') }}</p>
        @else
            <div class="-mx-3.5 px-3.5 flex gap-3 overflow-x-auto scrollbar-hide">
                @foreach($instructors as $ins)
                    @php $isOwner = $club->owner_user_id && (int) $ins->user_id === (int) $club->owner_user_id; @endphp
                    <a href="{{ route('admin.club.instructors', $club->slug) }}" data-shell-link data-route="admin.club.instructors"
                       class="m-press flex-shrink-0 w-14 flex flex-col items-center gap-1 no-underline">
                        <span class="relative w-11 h-11 rounded-full bg-muted flex items-center justify-center overflow-hidden ring-2 {{ $isOwner ? 'ring-amber-300' : 'ring-accent' }}">
                            @if($ins->user && $ins->user->profile_picture)
                                <img src="{{ asset('storage/'.$ins->user->profile_picture) }}" alt="" class="w-11 h-11 object-cover">
                            @else
                                <i class="bi bi-person text-muted-foreground"></i>
                            @endif
                            @if($isOwner)
                                <span class="absolute -bottom-0.5 -end-0.5 w-4 h-4 rounded-full bg-white grid place-items-center">
                                    <i class="bi bi-crown-fill text-amber-400 text-[9px]"></i>
                                </span>
                            @endif
                        </span>
                        <span class="text-[10px] text-muted-foreground leading-tight text-center truncate w-full">{{ \Illuminate\Support\Str::before($ins->user->full_name ?? __('admin.instructor'), ' ') }}</span>
                    </a>
                @endforeach
            </div>
        @endif
    </div>

    {{-- ===== Recent activity — proof the club's books are moving ===== --}}
    <div class="m-card p-3.5">
        <div class="flex items-center justify-between mb-1">
            <h3 class="text-xs font-bold uppercase tracking-wide text-muted-foreground">{{ __('admin.dash_recent') }}</h3>
            <a href="{{ route('admin.club.financials', $club->slug) }}" data-shell-link data-route="admin.club.financials" class="text-[11px] text-primary font-semibold no-underline">{{ __('admin.view_all') }}</a>
        </div>
        @if($recentTx->isEmpty())
            <p class="text-xs text-muted-foreground pt-1.5">{{ __('admin.dash_no_activity') }}</p>
        @else
            <div class="divide-y divide-gray-50">
                @foreach($recentTx as $t)
                    @php $isIncome = $t->type === 'income'; @endphp
                    <a href="{{ route('admin.club.financials', $club->slug) }}" data-shell-link data-route="admin.club.financials"
                       class="flex items-center gap-2.5 py-2 no-underline">
                        <span class="w-7 h-7 rounded-full grid place-items-center flex-shrink-0 {{ $isIncome ? 'bg-emerald-50 text-emerald-600' : 'bg-accent text-primary' }}">
                            <i class="bi {{ $isIncome ? 'bi-arrow-down-left' : 'bi-arrow-up-right' }} text-[11px]"></i>
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-[13px] font-medium text-foreground truncate">{{ $t->description ?: ($t->category ?: $t->type) }}</span>
                            <span class="block text-[10px] text-muted-foreground">{{ optional($t->transaction_date)->translatedFormat('d M') }}</span>
                        </span>
                        <span class="text-[13px] font-bold tabular-nums flex-shrink-0 {{ $isIncome ? 'text-emerald-600' : 'text-foreground' }}">{{ $isIncome ? '+' : '−' }}{{ number_format((float) $t->amount, 0) }}</span>
                    </a>
                @endforeach
            </div>
        @endif
    </div>

    </div>{{-- /content --}}
</div>
@endsection
