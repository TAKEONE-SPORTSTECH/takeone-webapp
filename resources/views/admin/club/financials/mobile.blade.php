@extends('layouts.admin-club-mobile')

@section('title', ($club->club_name ?? 'Club') . ' · Financials')

@section('club-admin-content')
@php $cur = $currency ?? ($club->currency ?: ''); @endphp
<div class="space-y-5" x-data="{ showIncomeModal:false, showExpenseModal:false }">

    {{-- Summary hero --}}
    <div class="rounded-2xl m-hero text-white p-5 shadow-sm">
        <p class="text-xs font-medium text-white/80 uppercase tracking-wide">Net profit</p>
        <p class="text-3xl font-bold mt-1">{{ $cur }} {{ number_format((float)($summary['net_profit'] ?? 0), 2) }}</p>
        <div class="flex items-center gap-4 mt-3 text-sm text-white/90">
            <span><i class="bi bi-arrow-down-circle mr-1"></i>In {{ $cur }} {{ number_format((float)($summary['total_income'] ?? 0), 0) }}</span>
            <span><i class="bi bi-arrow-up-circle mr-1"></i>Out {{ $cur }} {{ number_format((float)($summary['total_expenses'] ?? 0), 0) }}</span>
        </div>
    </div>

    {{-- Quick tiles --}}
    <div class="grid grid-cols-2 gap-3">
        <div class="m-card p-4">
            <div class="flex items-center gap-1.5 text-muted-foreground text-xs font-medium"><i class="bi bi-hourglass-split"></i> Cash to collect</div>
            <p class="text-xl font-bold text-amber-600 mt-1.5">{{ $cur }} {{ number_format((float)($summary['pending'] ?? 0), 0) }}</p>
        </div>
        <div class="m-card p-4">
            <div class="flex items-center gap-1.5 text-muted-foreground text-xs font-medium"><i class="bi bi-arrow-counterclockwise"></i> Refunds</div>
            <p class="text-xl font-bold text-gray-900 mt-1.5">{{ $cur }} {{ number_format((float)($summary['refunds'] ?? 0), 0) }}</p>
        </div>
    </div>

    {{-- Add actions --}}
    <div class="grid grid-cols-2 gap-3">
        <button @click="showIncomeModal=true" class="bg-green-50 text-green-700 border border-green-200 px-4 py-3 rounded-lg font-medium flex items-center justify-center gap-2"><i class="bi bi-plus-circle"></i> Income</button>
        <button @click="showExpenseModal=true" class="bg-primary text-white px-4 py-3 rounded-lg font-medium flex items-center justify-center gap-2"><i class="bi bi-dash-circle"></i> Expense</button>
    </div>

    {{-- Recent transactions --}}
    <div class="m-card p-4">
        <h3 class="font-semibold text-foreground mb-3">Recent transactions</h3>
        @if($transactions->isEmpty())
            <p class="text-sm text-muted-foreground">No transactions yet.</p>
        @else
            <div class="divide-y divide-gray-50 mobile-stagger">
                @foreach($transactions->take(15) as $t)
                    @php $isIncome = $t->type === 'income'; $isRefund = $t->type === 'refund'; @endphp
                    <div class="flex items-center justify-between py-2.5">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-foreground truncate">{{ $t->description ?: ucfirst($t->category ?? $t->type) }}</p>
                            <p class="text-xs text-muted-foreground">{{ optional($t->transaction_date)->format('d M Y') }} · {{ str_replace('_',' ', $t->payment_method ?? '') }}</p>
                        </div>
                        <span class="text-sm font-semibold flex-shrink-0 ml-2 {{ $isIncome ? 'text-green-600' : ($isRefund ? 'text-amber-600' : 'text-red-600') }}">
                            {{ $isIncome ? '+' : '-' }}{{ $cur }} {{ number_format((float)$t->amount, 2) }}
                        </span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    @if($pendingSubscriptions->isNotEmpty())
    <div class="m-card p-4">
        <h3 class="font-semibold text-foreground mb-3">Pending payments</h3>
        <div class="divide-y divide-gray-50 mobile-stagger">
            @foreach($pendingSubscriptions->take(10) as $sub)
                <div class="flex items-center justify-between py-2.5">
                    <p class="text-sm text-foreground truncate">{{ $sub->user->full_name ?? 'Member' }}</p>
                    <span class="text-sm font-semibold text-amber-600 flex-shrink-0 ml-2">{{ $cur }} {{ number_format((float)($sub->amount_due ?? 0), 2) }}</span>
                </div>
            @endforeach
        </div>
        <p class="text-xs text-muted-foreground mt-3">Approve payments from the desktop view.</p>
    </div>
    @endif

</div>

<x-income-modal :club="$club" :currency="$cur" />
<x-expense-modal :club="$club" />
@endsection
