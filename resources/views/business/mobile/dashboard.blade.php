@extends('layouts.business-mobile')

@section('title', $business->name)

@section('chain-content')
@php $cur = $totals['currency']; @endphp
<div class="-mx-4 -mt-4 pb-4">

    {{-- ===== Hero (full-width) ===== --}}
    <header class="m-hero px-5 pt-7 pb-12 text-white relative overflow-hidden">
        <div class="absolute -end-8 -top-8 w-36 h-36 rounded-full bg-white/10"></div>
        <div class="flex items-center justify-between relative z-10">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-wider text-white/70">{{ __('business.chain_revenue') }}</p>
                <h1 class="text-2xl font-black mt-0.5 tabular-nums">{{ $cur }} {{ number_format($totals['revenue'], 2) }}</h1>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" onclick="window.dispatchEvent(new CustomEvent('open-club-modal', { detail: { mode: 'create' } }))"
                        class="m-press w-12 h-12 rounded-2xl bg-white/20 border border-white/30 backdrop-blur grid place-items-center active:scale-95 transition-transform" aria-label="{{ __('business.create_club') }}">
                    <i class="bi bi-plus-lg text-xl"></i>
                </button>
                <button type="button" @click="drawer = true" class="m-press w-12 h-12 rounded-2xl bg-white/20 border border-white/30 backdrop-blur grid place-items-center active:scale-95 transition-transform" aria-label="{{ __('business.menu') }}">
                    <i class="bi bi-grid text-xl"></i>
                </button>
            </div>
        </div>

        <div class="flex gap-2 mt-5 relative z-10">
            <div class="flex-1 rounded-2xl bg-white/12 border border-white/20 backdrop-blur px-3 py-2.5">
                <p class="text-lg font-black leading-none">{{ $totals['clubs'] }}</p>
                <p class="text-[10px] text-white/75 mt-1 uppercase tracking-wide">{{ $totals['clubs'] == 1 ? __('business.club_one') : __('business.club_many') }}</p>
            </div>
            <div class="flex-1 rounded-2xl bg-white/12 border border-white/20 backdrop-blur px-3 py-2.5">
                <p class="text-lg font-black leading-none">{{ number_format($totals['members']) }}</p>
                <p class="text-[10px] text-white/75 mt-1 uppercase tracking-wide">{{ __('business.members') }}</p>
            </div>
            <div class="flex-1 rounded-2xl bg-white/12 border border-white/20 backdrop-blur px-3 py-2.5">
                <p class="text-lg font-black leading-none tabular-nums">{{ $cur }} {{ number_format($totals['net'], 0) }}</p>
                <p class="text-[10px] text-white/75 mt-1 uppercase tracking-wide">{{ __('business.net') }}</p>
            </div>
        </div>
    </header>

    <div class="px-4 -mt-6 relative z-10 space-y-5">

    {{-- Revenue per club --}}
    @if($clubs->isNotEmpty())
        <x-chart
            type="line"
            :labels="$revenueChart['labels']"
            :datasets="$revenueChart['datasets']"
            :valuePrefix="$cur . ' '"
            title="{{ __('business.clubs') }}"
            subtitle="{{ __('business.chain_revenue') }}"
            icon="bi-graph-up-arrow"
            :height="220"
        />
    @endif

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
                <button type="button" onclick="window.dispatchEvent(new CustomEvent('open-club-modal', { detail: { mode: 'create' } }))"
                        class="m-press mt-4 inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-primary text-white text-sm font-medium">
                    <i class="bi bi-plus-lg"></i>{{ __('business.create_club') }}
                </button>
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
</div>

<template x-teleport="body">
    <x-club-modal mode="create" context="business" />
</template>
@endsection
