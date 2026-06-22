@extends('layouts.admin-club-mobile')

@section('title', ($club->club_name ?? __('admin.club')) . ' · ' . __('admin.nav_analytics'))

@section('club-admin-content')
@php
    $cur = $club->currency ?: '';
    $change = function ($v) {
        $v = (int) $v;
        if ($v > 0) return ['text-green-600', 'bi-arrow-up-short', '+'.$v.'%'];
        if ($v < 0) return ['text-red-600', 'bi-arrow-down-short', $v.'%'];
        return ['text-muted-foreground', 'bi-dash', '0%'];
    };
@endphp
<div class="space-y-5">

    {{-- KPI tiles --}}
    <div class="grid grid-cols-2 gap-3 mobile-stagger">
        @php
            $tiles = [
                [__('admin.ana_new_members'), $analytics['new_members'] ?? 0, $analytics['new_members_change'] ?? 0, 'bi-person-plus'],
                [__('admin.ana_retention'), ($analytics['retention_rate'] ?? 0).'%', $analytics['retention_change'] ?? 0, 'bi-arrow-repeat'],
                [__('admin.ana_checkins'), $analytics['total_checkins'] ?? 0, $analytics['checkins_change'] ?? 0, 'bi-door-open'],
                [__('admin.ana_avg_revenue'), $cur.' '.number_format((float)($analytics['avg_revenue'] ?? 0), 0), null, 'bi-cash-stack'],
            ];
        @endphp
        @foreach($tiles as [$label, $value, $delta, $icon])
            <div class="m-card p-4">
                <div class="flex items-center gap-1.5 text-muted-foreground text-xs font-medium"><i class="bi {{ $icon }}"></i> {{ $label }}</div>
                <p class="text-xl font-bold text-gray-900 mt-1.5">{{ $value }}</p>
                @if(!is_null($delta))
                    @php [$cls,$ico,$txt] = $change($delta); @endphp
                    <p class="text-xs {{ $cls }} mt-0.5 flex items-center"><i class="bi {{ $ico }}"></i>{{ $txt }}</p>
                @endif
            </div>
        @endforeach
    </div>

    {{-- Activity breakdown --}}
    @if(!empty($analytics['activity_labels']) && !empty($analytics['activity_data']))
    <div class="m-card p-4">
        <h3 class="font-semibold text-foreground mb-3">{{ __('admin.ana_activity_breakdown') }}</h3>
        @php $maxAct = max(1, max($analytics['activity_data'])); @endphp
        <div class="space-y-2.5 mobile-stagger">
            @foreach($analytics['activity_labels'] as $i => $label)
                @php $val = $analytics['activity_data'][$i] ?? 0; @endphp
                <div>
                    <div class="flex justify-between text-xs mb-1"><span class="text-muted-foreground truncate">{{ $label }}</span><span class="font-semibold text-foreground">{{ $val }}</span></div>
                    <div class="h-2 rounded-full bg-muted overflow-hidden"><div class="m-bar-fill h-full bg-primary rounded-full" style="width: {{ $val > 0 ? max(4, round($val/$maxAct*100)) : 0 }}%"></div></div>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Popular packages --}}
    @if($popularPackages->isNotEmpty())
    <div class="m-card p-4">
        <h3 class="font-semibold text-foreground mb-3">{{ __('admin.ana_popular_packages') }}</h3>
        @php $maxSub = max(1, $popularPackages->max('subscriptions_count') ?: 1); @endphp
        <div class="space-y-2.5 mobile-stagger">
            @foreach($popularPackages as $pkg)
                <div>
                    <div class="flex justify-between text-xs mb-1"><span class="text-muted-foreground truncate">{{ $pkg->name }}</span><span class="font-semibold text-foreground">{{ $pkg->subscriptions_count ?? 0 }}</span></div>
                    <div class="h-2 rounded-full bg-muted overflow-hidden"><div class="m-bar-fill h-full bg-primary rounded-full" style="width: {{ ($pkg->subscriptions_count ?? 0) > 0 ? max(4, round(($pkg->subscriptions_count)/$maxSub*100)) : 0 }}%"></div></div>
                </div>
            @endforeach
        </div>
    </div>
    @endif

</div>
@endsection
