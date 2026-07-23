@extends('layouts.app')

@section('title', $business->name . ' · ' . __('business.business_desktop_dashboard_title'))

@section('content')
<div class="px-4 sm:px-6 lg:px-8 py-6 space-y-6">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div class="flex items-center gap-3">
            <span class="w-12 h-12 rounded-xl bg-accent flex items-center justify-center flex-shrink-0">
                @if($business->logo)
                    <img src="{{ asset('storage/' . $business->logo) }}" alt="" class="w-12 h-12 rounded-xl object-cover">
                @else
                    <i class="bi bi-buildings text-primary text-xl"></i>
                @endif
            </span>
            <div>
                <h1 class="text-2xl font-bold text-gray-900">{{ $business->name }}</h1>
                <p class="text-sm text-muted-foreground">{{ __('business.business_desktop_dashboard_chain_performance', ['count' => $totals['clubs'], 'clubs' => Str::plural('club', $totals['clubs'])]) }}</p>
            </div>
        </div>
        <button type="button" onclick="window.dispatchEvent(new CustomEvent('open-club-modal', { detail: { mode: 'create' } }))"
                class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-primary rounded-lg hover:bg-primary/90 transition-colors">
            <i class="bi bi-plus-lg"></i>{{ __('business.create_club') }}
        </button>
    </div>

    {{-- KPI cards --}}
    @php $cur = $totals['currency']; @endphp
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <div class="flex items-center gap-2 text-muted-foreground text-xs font-medium uppercase tracking-wide"><i class="bi bi-people"></i> {{ __('business.business_desktop_dashboard_members') }}</div>
            <p class="text-2xl font-bold text-gray-900 mt-2">{{ number_format($totals['members']) }}</p>
            <p class="text-xs text-muted-foreground mt-1">{{ number_format($totals['active_subs']) }} {{ __('business.business_desktop_dashboard_active_subscriptions') }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <div class="flex items-center gap-2 text-muted-foreground text-xs font-medium uppercase tracking-wide"><i class="bi bi-cash-stack"></i> {{ __('business.business_desktop_dashboard_revenue') }}</div>
            <p class="text-2xl font-bold text-gray-900 mt-2">{{ $cur }} {{ number_format($totals['revenue'], 2) }}</p>
            <p class="text-xs text-muted-foreground mt-1">{{ __('business.business_desktop_dashboard_expenses') }} {{ $cur }} {{ number_format($totals['expenses'], 2) }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <div class="flex items-center gap-2 text-muted-foreground text-xs font-medium uppercase tracking-wide"><i class="bi bi-graph-up-arrow"></i> {{ __('business.business_desktop_dashboard_net') }}</div>
            <p class="text-2xl font-bold {{ $totals['net'] >= 0 ? 'text-green-600' : 'text-red-600' }} mt-2">{{ $cur }} {{ number_format($totals['net'], 2) }}</p>
            <p class="text-xs text-muted-foreground mt-1">{{ __('business.business_desktop_dashboard_income_minus_expenses') }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <div class="flex items-center gap-2 text-muted-foreground text-xs font-medium uppercase tracking-wide"><i class="bi bi-hourglass-split"></i> {{ __('business.business_desktop_dashboard_cash_to_collect') }}</div>
            <p class="text-2xl font-bold text-amber-600 mt-2">{{ $cur }} {{ number_format($totals['cash_to_collect'], 2) }}</p>
            <p class="text-xs text-muted-foreground mt-1">{{ __('business.business_desktop_dashboard_unpaid_pending') }}</p>
        </div>
    </div>

    {{-- Per-club breakdown --}}
    <div id="clubs" class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="font-semibold text-foreground">{{ __('business.business_desktop_dashboard_clubs') }}</h2>
            <p class="text-sm text-muted-foreground">{{ __('business.business_desktop_dashboard_select_club') }}</p>
        </div>

        @if($clubs->isEmpty())
            <div class="p-10 text-center">
                <i class="bi bi-diagram-3 text-4xl text-gray-300"></i>
                <p class="text-sm text-muted-foreground mt-3">{{ __('business.business_desktop_dashboard_no_clubs') }}</p>
                <button type="button" onclick="window.dispatchEvent(new CustomEvent('open-club-modal', { detail: { mode: 'create' } }))"
                        class="mt-4 inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-primary rounded-lg hover:bg-primary/90 transition-colors">
                    <i class="bi bi-plus-lg"></i>{{ __('business.create_club') }}
                </button>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-start text-xs text-muted-foreground uppercase tracking-wide border-b border-gray-100">
                            <th class="px-5 py-3 font-medium">{{ __('business.business_desktop_dashboard_col_club') }}</th>
                            <th class="px-5 py-3 font-medium text-end">{{ __('business.business_desktop_dashboard_col_members') }}</th>
                            <th class="px-5 py-3 font-medium text-end">{{ __('business.business_desktop_dashboard_col_active_subs') }}</th>
                            <th class="px-5 py-3 font-medium text-end">{{ __('business.business_desktop_dashboard_col_revenue') }}</th>
                            <th class="px-5 py-3 font-medium text-end">{{ __('business.business_desktop_dashboard_col_cash_to_collect') }}</th>
                            <th class="px-5 py-3 font-medium text-end"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($clubs as $club)
                            <tr class="border-b border-gray-50 hover:bg-muted/40 transition-colors">
                                <td class="px-5 py-3">
                                    <div class="flex items-center gap-3">
                                        <span class="w-9 h-9 rounded-lg bg-muted flex items-center justify-center flex-shrink-0 overflow-hidden">
                                            @if($club['logo'])
                                                <img src="{{ asset('storage/' . $club['logo']) }}" alt="" class="w-9 h-9 object-cover">
                                            @else
                                                <i class="bi bi-building text-muted-foreground"></i>
                                            @endif
                                        </span>
                                        <span class="font-medium text-foreground">{{ $club['name'] }}</span>
                                    </div>
                                </td>
                                <td class="px-5 py-3 text-end">{{ number_format($club['members']) }}</td>
                                <td class="px-5 py-3 text-end">{{ number_format($club['active_subs']) }}</td>
                                <td class="px-5 py-3 text-end">{{ $club['currency'] }} {{ number_format($club['revenue'], 2) }}</td>
                                <td class="px-5 py-3 text-end {{ $club['cash_to_collect'] > 0 ? 'text-amber-600' : '' }}">{{ $club['currency'] }} {{ number_format($club['cash_to_collect'], 2) }}</td>
                                <td class="px-5 py-3 text-end">
                                    <a href="{{ route('admin.club.dashboard', $club['slug']) }}"
                                       class="inline-flex items-center gap-1 border border-primary text-primary bg-transparent px-3 py-1.5 rounded-md text-sm font-medium hover:bg-primary hover:text-white transition-colors">
                                        {{ __('business.business_desktop_dashboard_open') }} <i class="bi bi-arrow-right"></i>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div x-data>
        <template x-teleport="body">
            <x-club-modal mode="create" context="business" />
        </template>
    </div>
</div>
@endsection
