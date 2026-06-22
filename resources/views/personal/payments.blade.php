@extends('layouts.personal-mobile')

@section('title', 'Payments & Billing')

{{-- Full-bleed (Facebook-style): edge-to-edge white blocks separated by gray gutters. --}}
@section('personal-content')
<div class="-mx-4 -mt-4">
    <div class="bg-white px-4 py-4 mb-2">
        <div class="grid grid-cols-2 gap-3">
            <div class="bg-muted/40 rounded-xl p-4">
                <div class="flex items-center gap-1.5 text-muted-foreground text-xs font-medium"><i class="bi bi-check-circle"></i> {{ __('personal.paid') }}</div>
                <p class="text-xl font-bold text-green-600 mt-1.5">{{ number_format($totalPaid, 2) }}</p>
            </div>
            <div class="bg-muted/40 rounded-xl p-4">
                <div class="flex items-center gap-1.5 text-muted-foreground text-xs font-medium"><i class="bi bi-hourglass-split"></i> {{ __('personal.due') }}</div>
                <p class="text-xl font-bold text-amber-600 mt-1.5">{{ number_format($totalDue, 2) }}</p>
            </div>
        </div>
    </div>

    <div class="bg-white px-4 py-4">
        <h3 class="font-semibold text-foreground mb-3">{{ __('personal.history') }}</h3>
        @forelse($subscriptions as $sub)
            @php
                $cur = $sub->tenant->currency ?? '';
                $paid = ($sub->payment_status ?? '') === 'paid';
            @endphp
            <div class="flex items-center justify-between py-2.5 border-b border-gray-50 last:border-0">
                <div class="min-w-0">
                    <p class="text-sm font-medium text-foreground truncate">{{ $sub->package?->tr('name') ?? __('personal.membership') }}</p>
                    <p class="text-[11px] text-muted-foreground capitalize">{{ str_replace('_',' ', $sub->payment_status ?? '') }} · {{ optional($sub->created_at)->format('d M Y') }}</p>
                </div>
                <span class="text-sm font-semibold flex-shrink-0 ml-2 {{ $paid ? 'text-green-600' : 'text-amber-600' }}">{{ $cur }} {{ number_format((float)($paid ? $sub->amount_paid : $sub->amount_due), 2) }}</span>
            </div>
        @empty
            <p class="text-sm text-muted-foreground">{{ __('personal.no_payment_history') }}</p>
        @endforelse
    </div>
</div>
@endsection
