@extends('layouts.app')

@section('content')
@php
    $hasOutstanding = $summary['outstanding'] > 0;
@endphp
<div class="px-4 sm:px-6 lg:px-8 py-6" x-data="billsPage()">

    {{-- ── Header ── --}}
    <div class="flex items-center justify-between gap-4 mb-6">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-primary/70">{{ __('shared.templates_invoices_index_wallet') }}</p>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 tracking-tight">{{ __('shared.templates_invoices_index_title') }}</h1>
        </div>
        <a href="{{ url()->previous() }}"
           class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium text-gray-600 bg-white border border-gray-200 hover:bg-gray-50 transition-colors">
            <i class="bi bi-arrow-left"></i> <span class="hidden sm:inline">{{ __('shared.back') }}</span>
        </a>
    </div>

    {{-- ── Balance hero ── --}}
    <div class="relative overflow-hidden rounded-3xl p-6 sm:p-8 mb-5 text-white shadow-lg"
         style="background-image: linear-gradient(135deg, hsl(250 65% 60%) 0%, hsl(255 60% 55%) 45%, hsl(265 55% 48%) 100%);">
        {{-- Decorative orbs --}}
        <div class="pointer-events-none absolute -top-16 -right-10 w-64 h-64 rounded-full bg-white/10 blur-2xl"></div>
        <div class="pointer-events-none absolute -bottom-24 -left-10 w-72 h-72 rounded-full bg-black/10 blur-2xl"></div>
        <div class="pointer-events-none absolute inset-0 opacity-[0.07]"
             style="background-image:radial-gradient(circle at 1px 1px, #fff 1px, transparent 0);background-size:22px 22px;"></div>

        <div class="relative flex flex-col lg:flex-row lg:items-end lg:justify-between gap-6">
            <div>
                <p class="text-sm font-medium text-white/70 flex items-center gap-2">
                    <i class="bi bi-wallet2"></i> {{ __('shared.templates_invoices_index_total_outstanding') }}
                </p>
                <div class="mt-2 flex items-end gap-2">
                    <span class="text-base font-semibold text-white/80 mb-1.5">{{ $currency }}</span>
                    <span class="text-5xl sm:text-6xl font-bold tracking-tight tabular-nums leading-none">{{ number_format($summary['outstanding'], 2) }}</span>
                </div>
                <div class="mt-3 flex flex-wrap items-center gap-2 text-sm">
                    @if($summary['overdue_count'] > 0)
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-red-500/90 text-white font-semibold text-xs">
                            <i class="bi bi-exclamation-octagon-fill"></i> {{ $summary['overdue_count'] }} {{ __('shared.templates_invoices_index_overdue_badge') }}
                        </span>
                    @endif
                    @if($summary['next_due'])
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-white/15 text-white/90 text-xs font-medium">
                            <i class="bi bi-calendar-event"></i>
                            {{ __('shared.templates_invoices_index_next_due') }} {{ $summary['next_due']->due_date->format('M d') }}
                        </span>
                    @endif
                    @unless($hasOutstanding)
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-400/90 text-emerald-950 text-xs font-semibold">
                            <i class="bi bi-check-circle-fill"></i> {{ __('shared.templates_invoices_index_all_settled') }}
                        </span>
                    @endunless
                </div>
            </div>

            @if($hasOutstanding)
            <a href="{{ route('bills.pay-all') }}"
               class="group inline-flex items-center justify-center gap-2 px-6 py-3.5 rounded-2xl bg-white text-primary font-bold text-sm shadow-md hover:shadow-xl hover:scale-[1.02] active:scale-95 transition-all">
                <i class="bi bi-lightning-charge-fill group-hover:scale-110 transition-transform"></i>
                {{ __('shared.templates_invoices_index_pay_all') }} ({{ $summary['pending_count'] }})
            </a>
            @endif
        </div>
    </div>

    {{-- ── KPI strip ── --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
        @php
            $kpis = [
                ['label'=>__('shared.templates_invoices_index_kpi_paid_to_date'),'value'=>$currency.' '.number_format($summary['paid_total'],2),'icon'=>'bi-check2-circle','tint'=>'emerald'],
                ['label'=>__('shared.templates_invoices_index_kpi_pending_bills'),'value'=>$summary['pending_count'],'icon'=>'bi-hourglass-split','tint'=>'amber'],
                ['label'=>__('shared.templates_invoices_index_kpi_overdue'),'value'=>$summary['overdue_count'],'icon'=>'bi-exclamation-triangle','tint'=>'red'],
                ['label'=>__('shared.templates_invoices_index_kpi_paid_bills'),'value'=>$summary['paid_count'],'icon'=>'bi-receipt','tint'=>'primary'],
            ];
        @endphp
        @foreach($kpis as $kpi)
            <div class="rounded-2xl bg-white border border-gray-100 shadow-sm p-4 flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0
                    @class([
                        'bg-emerald-50 text-emerald-600' => $kpi['tint']==='emerald',
                        'bg-amber-50 text-amber-600'      => $kpi['tint']==='amber',
                        'bg-red-50 text-red-500'          => $kpi['tint']==='red',
                        'bg-accent text-primary'          => $kpi['tint']==='primary',
                    ])">
                    <i class="bi {{ $kpi['icon'] }} text-lg"></i>
                </div>
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground truncate">{{ $kpi['label'] }}</p>
                    <p class="text-lg font-bold text-gray-900 tabular-nums leading-tight">{{ $kpi['value'] }}</p>
                </div>
            </div>
        @endforeach
    </div>

    {{-- ── Filter bar ── --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
        {{-- Segmented status control --}}
        <div class="inline-flex p-1 rounded-xl bg-muted/70 self-start">
            @php $segments = ['' => __('shared.templates_invoices_index_filter_all'), 'pending' => __('shared.templates_invoices_index_filter_pending'), 'paid' => __('shared.templates_invoices_index_filter_paid')]; @endphp
            @foreach($segments as $val => $label)
                <button type="button"
                        @click="setStatus('{{ $val }}')"
                        :class="status === '{{ $val }}' ? 'bg-white text-primary shadow-sm' : 'text-muted-foreground hover:text-gray-700'"
                        class="px-4 py-1.5 rounded-lg text-sm font-semibold transition-all">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        {{-- Date range --}}
        <div class="flex items-center gap-2">
            <div class="relative">
                <i class="bi bi-calendar3 absolute start-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm pointer-events-none"></i>
                <input type="date" x-model="startDate" @change="reload()"
                       class="ps-9 pe-2 py-2 text-sm border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
            </div>
            <span class="text-muted-foreground text-sm">–</span>
            <div class="relative">
                <i class="bi bi-calendar3 absolute start-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm pointer-events-none"></i>
                <input type="date" x-model="endDate" @change="reload()"
                       class="ps-9 pe-2 py-2 text-sm border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
            </div>
            <button type="button" x-show="startDate || endDate" @click="clearDates()"
                    class="w-9 h-9 flex items-center justify-center rounded-lg text-gray-400 hover:text-red-500 hover:bg-red-50 transition-colors" title="{{ __('shared.templates_invoices_index_clear_dates') }}">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
    </div>

    {{-- ── List (AJAX-swapped) ── --}}
    <div id="billsList" :class="loading ? 'opacity-40 pointer-events-none transition-opacity' : 'transition-opacity'">
        @include('components-templates.invoices._list')
    </div>
</div>

@push('styles')
<style>
    .bill-card { animation: billUp .5s cubic-bezier(.22,1,.36,1) both; animation-delay: var(--d, 0s); }
    @keyframes billUp { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: translateY(0); } }
    @media (prefers-reduced-motion: reduce) { .bill-card { animation: none; } }
</style>
@endpush

@push('scripts')
<script>
    function billsPage() {
        return {
            status: @json($status ?? ''),
            startDate: @json(request('start_date') ?? ''),
            endDate: @json(request('end_date') ?? ''),
            loading: false,
            _seq: 0,

            setStatus(v) { this.status = v; this.reload(); },
            clearDates() { this.startDate = ''; this.endDate = ''; this.reload(); },

            reload() {
                const params = new URLSearchParams();
                if (this.status)    params.set('status', this.status);
                if (this.startDate) params.set('start_date', this.startDate);
                if (this.endDate)   params.set('end_date', this.endDate);
                const qs  = params.toString();
                const url = '{{ route('bills.index') }}' + (qs ? ('?' + qs) : '');

                history.replaceState(null, '', url);
                const seq = ++this._seq;
                this.loading = true;

                fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' } })
                    .then(r => r.text())
                    .then(html => {
                        if (seq !== this._seq) return;   // a newer request won
                        document.getElementById('billsList').innerHTML = html;
                    })
                    .catch(() => window.showToast?.('error', '{{ __('shared.templates_invoices_index_load_error') }}'))
                    .finally(() => { if (seq === this._seq) this.loading = false; });
            },
        };
    }
</script>
@endpush
@endsection
