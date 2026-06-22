@extends('layouts.business-mobile')

@section('title', $business->name)

@section('chain-content')
@php $cur = $totals['currency']; @endphp
<div class="space-y-5">

    {{-- Hero summary --}}
    <div class="m-hero rounded-2xl text-white p-5 shadow-lg">
        <div class="relative z-10">
            <p class="text-xs font-semibold text-white/80 uppercase tracking-wider">{{ __('business.chain_revenue') }}</p>
            <p class="text-[2rem] leading-tight font-extrabold mt-1 tabular-nums">{{ $cur }} {{ number_format($totals['revenue'], 2) }}</p>
            <div class="flex items-center gap-2 mt-3">
                <span class="inline-flex items-center gap-1.5 text-xs font-medium bg-white/15 backdrop-blur rounded-full px-3 py-1.5">
                    <i class="bi bi-diagram-3"></i>{{ $totals['clubs'] }} {{ $totals['clubs'] == 1 ? __('business.club_one') : __('business.club_many') }}
                </span>
                <span class="inline-flex items-center gap-1.5 text-xs font-medium bg-white/15 backdrop-blur rounded-full px-3 py-1.5">
                    <i class="bi bi-people"></i>{{ number_format($totals['members']) }} {{ __('business.members') }}
                </span>
            </div>
        </div>
    </div>

    {{-- KPI grid --}}
    <div class="mobile-stagger grid grid-cols-2 gap-3">
        <div class="m-card m-press p-4">
            <div class="w-9 h-9 rounded-xl bg-accent flex items-center justify-center mb-2"><i class="bi bi-person-check text-primary text-lg"></i></div>
            <p class="text-2xl font-extrabold text-gray-900 tabular-nums leading-none" data-countup="{{ (int) $totals['active_subs'] }}">{{ number_format($totals['active_subs']) }}</p>
            <p class="text-xs font-medium text-muted-foreground mt-1">{{ __('business.active_subs') }}</p>
        </div>
        <div class="m-card m-press p-4">
            <div class="w-9 h-9 rounded-xl bg-accent flex items-center justify-center mb-2"><i class="bi bi-graph-up-arrow text-primary text-lg"></i></div>
            <p class="text-2xl font-extrabold {{ $totals['net'] >= 0 ? 'text-green-600' : 'text-red-600' }} tabular-nums leading-none">{{ $cur }} {{ number_format($totals['net'], 0) }}</p>
            <p class="text-xs font-medium text-muted-foreground mt-1">{{ __('business.net') }}</p>
        </div>
        <div class="m-card m-press p-4">
            <div class="w-9 h-9 rounded-xl bg-accent flex items-center justify-center mb-2"><i class="bi bi-wallet2 text-primary text-lg"></i></div>
            <p class="text-2xl font-extrabold text-gray-900 tabular-nums leading-none">{{ $cur }} {{ number_format($totals['expenses'], 0) }}</p>
            <p class="text-xs font-medium text-muted-foreground mt-1">{{ __('business.expenses') }}</p>
        </div>
        <div class="m-card m-press p-4">
            <div class="w-9 h-9 rounded-xl bg-accent flex items-center justify-center mb-2"><i class="bi bi-hourglass-split text-primary text-lg"></i></div>
            <p class="text-2xl font-extrabold text-amber-600 tabular-nums leading-none">{{ $cur }} {{ number_format($totals['cash_to_collect'], 0) }}</p>
            <p class="text-xs font-medium text-muted-foreground mt-1">{{ __('business.to_collect') }}</p>
        </div>
    </div>

    {{-- Clubs list --}}
    <div id="clubs" class="space-y-3">
        <div class="flex items-center justify-between">
            <h2 class="font-bold text-foreground">{{ __('business.clubs') }}</h2>
            <span class="text-xs text-muted-foreground">{{ __('business.tap_to_manage') }}</span>
        </div>

        @if($clubs->isEmpty())
            <div class="m-card p-8 text-center">
                <i class="bi bi-diagram-3 text-3xl text-gray-300 m-float inline-block"></i>
                <p class="text-sm text-muted-foreground mt-2">{{ __('business.no_clubs_linked') }}</p>
            </div>
        @else
            <div class="mobile-stagger space-y-3">
                @foreach($clubs as $club)
                    <a href="{{ route('admin.club.dashboard', $club['slug']) }}" class="block m-card m-press p-4">
                        <div class="flex items-center gap-3">
                            <span class="w-11 h-11 rounded-lg bg-muted flex items-center justify-center flex-shrink-0 overflow-hidden ring-2 ring-accent">
                                @if($club['logo'])
                                    <img src="{{ asset('storage/' . $club['logo']) }}" alt="" class="w-11 h-11 object-cover">
                                @else
                                    <i class="bi bi-building text-muted-foreground text-lg"></i>
                                @endif
                            </span>
                            <div class="flex-1 min-w-0">
                                <p class="font-semibold text-foreground truncate">{{ $club['name'] }}</p>
                                <p class="text-xs text-muted-foreground">{{ __('business.members_count', ['count' => number_format($club['members'])]) }} · {{ __('business.active_count', ['count' => number_format($club['active_subs'])]) }}</p>
                            </div>
                            <i class="bi bi-chevron-right text-muted-foreground"></i>
                        </div>
                        <div class="grid grid-cols-2 gap-2 mt-3 pt-3 border-t border-gray-50">
                            <div>
                                <p class="text-[11px] text-muted-foreground uppercase tracking-wide">{{ __('business.revenue') }}</p>
                                <p class="text-sm font-semibold text-foreground tabular-nums">{{ $club['currency'] }} {{ number_format($club['revenue'], 0) }}</p>
                            </div>
                            <div>
                                <p class="text-[11px] text-muted-foreground uppercase tracking-wide">{{ __('business.to_collect') }}</p>
                                <p class="text-sm font-semibold tabular-nums {{ $club['cash_to_collect'] > 0 ? 'text-amber-600' : 'text-foreground' }}">{{ $club['currency'] }} {{ number_format($club['cash_to_collect'], 0) }}</p>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>

</div>
@endsection
