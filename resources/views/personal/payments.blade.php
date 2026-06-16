@extends('layouts.personal-mobile')

@section('title', 'Payments & Billing')

@section('personal-content')
<div class="space-y-5">
    <div class="grid grid-cols-2 gap-3">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
            <div class="flex items-center gap-1.5 text-muted-foreground text-xs font-medium"><i class="bi bi-check-circle"></i> Paid</div>
            <p class="text-xl font-bold text-green-600 mt-1.5">{{ number_format($totalPaid, 2) }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
            <div class="flex items-center gap-1.5 text-muted-foreground text-xs font-medium"><i class="bi bi-hourglass-split"></i> Due</div>
            <p class="text-xl font-bold text-amber-600 mt-1.5">{{ number_format($totalDue, 2) }}</p>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
        <h3 class="font-semibold text-foreground mb-3">History</h3>
        @forelse($subscriptions as $sub)
            @php
                $cur = $sub->tenant->currency ?? '';
                $paid = ($sub->payment_status ?? '') === 'paid';
            @endphp
            <div class="flex items-center justify-between py-2.5 border-b border-gray-50 last:border-0">
                <div class="min-w-0">
                    <p class="text-sm font-medium text-foreground truncate">{{ $sub->package->name ?? 'Membership' }}</p>
                    <p class="text-[11px] text-muted-foreground capitalize">{{ str_replace('_',' ', $sub->payment_status ?? '') }} · {{ optional($sub->created_at)->format('d M Y') }}</p>
                </div>
                <span class="text-sm font-semibold flex-shrink-0 ml-2 {{ $paid ? 'text-green-600' : 'text-amber-600' }}">{{ $cur }} {{ number_format((float)($paid ? $sub->amount_paid : $sub->amount_due), 2) }}</span>
            </div>
        @empty
            <p class="text-sm text-muted-foreground">No payment history.</p>
        @endforelse
    </div>
</div>
@endsection
