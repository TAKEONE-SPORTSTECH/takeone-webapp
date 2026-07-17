@extends('layouts.admin-club')

@section('club-admin-content')
@php
    $currency      = $club->currency ?? 'BHD';
    $netIncome     = $summary['net_profit'] ?? 0;
    $totalIncome   = $summary['total_income'] ?? 0;
    $totalExpenses = $summary['total_expenses'] ?? 0;
    $totalRefunds  = $summary['refunds'] ?? 0;
    $cashToCollect = $summary['pending'] ?? 0;
    $marginPct     = $totalIncome > 0 ? round(($netIncome / $totalIncome) * 100, 1) : 0;
    $incomeCount   = $transactions->where('type','income')->count();
    $expenseCount  = $transactions->where('type','expense')->count();
    $refundCount   = $transactions->where('type','refund')->count();

    // "Pending" = cash still to collect from members who enrolled/renewed but haven't paid yet
    // (App\Services\FinancialService::getCashToCollect() — same source as the "Cash to Collect"
    // stat card and the mobile ledger's Pending filter). Some subscription flows (walk-in, admin
    // enroll, explore self-registration) create a matching ClubTransaction alongside the
    // subscription — those already surface as an "Unpaid"/"Pending" ledger row below too. But the
    // QR self-registration wizard (WizardRegistrationController) creates the subscription with no
    // transaction at all, so it would otherwise never appear in the desktop ledger. Mirrors the
    // mobile ledger exactly (partials/../mobile.blade.php $pendingLedger): every pending
    // subscription gets its own row here, even the rare one that also has a matching transaction
    // row — keeps the "All"/"Pending" counts identical between mobile and desktop.
    $pendingLedgerRows = $pendingSubscriptions->map(function ($sub) use ($club) {
        $name = $sub->user->full_name ?? $sub->user->name ?? __('admin.fin_member');
        return [
            'subscription_id'  => $sub->id,
            'name'             => $name,
            'package'          => $sub->package->name ?? null,
            'amount'           => (float) ($sub->amount_due ?? 0),
            'status'           => $sub->payment_status,
            'date'             => $sub->start_date ? \Illuminate\Support\Carbon::parse($sub->start_date) : null,
            'description'      => 'Package: '.($sub->package->name ?? ''),
            'proof_of_payment' => $sub->proof_of_payment ? route('admin.club.subscriptions.payment-proof', ['club' => $club, 'subscription' => $sub->id]) : '',
        ];
    })->values();

    $ledgerFilterCounts = [
        'all'     => $transactions->count() + $pendingLedgerRows->count(),
        'pending' => $pendingLedgerRows->count(),
        'income'  => $incomeCount,
        'expense' => $expenseCount,
        'refund'  => $refundCount,
    ];

    // Single date-sorted ledger — pending rows are interleaved with transactions by date rather
    // than grouped together, matching a normal chronological ledger.
    $ledgerRows = $pendingLedgerRows->map(fn ($p) => ['kind' => 'pending', 'sort_date' => $p['date'], 'data' => $p])
        ->concat($transactions->map(fn ($t) => ['kind' => 'transaction', 'sort_date' => $t->transaction_date, 'data' => $t]))
        ->sortByDesc(fn ($row) => $row['sort_date'] ?? \Illuminate\Support\Carbon::minValue())
        ->values();

    // Per-row {type, status} in the same order as the single @foreach below — lets the Alpine
    // filter/pagination combo rank rows without re-scanning the DOM.
    $ledgerMeta = $ledgerRows->map(fn ($row) => $row['kind'] === 'pending'
        ? ['type' => 'pending', 'status' => $row['data']['status']]
        : ['type' => $row['data']->type, 'status' => $row['data']->subscription?->payment_status ?? null]
    )->values();
@endphp

<div x-data="{
    testMode: {{ $isTestMode ? 'true' : 'false' }},
    modeSwitching: false,
    showModeReviewModal: false,
    reviewData: { transactions: [], subscriptions: [], orders: [] },
    async toggleMode() {
        if (this.modeSwitching) return;
        if (this.testMode) {
            // Test → Live: fetch what's test-tagged first, review if anything exists.
            this.modeSwitching = true;
            try {
                const r = await fetch(`{{ url('admin/club/'.$club->slug.'/financials/test-data') }}`, { headers: { 'Accept': 'application/json' } });
                const d = await r.json();
                if (!d.success) { window.showToast('error', '{{ __('admin.club_financials_index_error') }}'); return; }
                if (d.total === 0) {
                    const ok = await window.confirmAction({
                        title: '{{ __('admin.club_financials_index_mode_switch_title') }}',
                        message: '{{ __('admin.club_financials_index_mode_switch_to_live_empty') }}',
                        type: 'default',
                        confirmText: '{{ __('admin.club_financials_index_mode_go_live') }}',
                    });
                    if (ok) await this.commitMode('live', {});
                } else {
                    this.reviewData = {
                        transactions: d.transactions.map(t => ({ ...t, keep: false })),
                        subscriptions: d.subscriptions.map(s => ({ ...s, keep: false })),
                        orders: d.orders.map(o => ({ ...o, keep: false })),
                    };
                    this.showModeReviewModal = true;
                }
            } catch { window.showToast('error', '{{ __('admin.club_financials_index_error') }}'); }
            finally { this.modeSwitching = false; }
        } else {
            const ok = await window.confirmAction({
                title: '{{ __('admin.club_financials_index_mode_switch_title') }}',
                message: '{{ __('admin.club_financials_index_mode_switch_to_test_message') }}',
                type: 'default',
                confirmText: '{{ __('admin.club_financials_index_mode_go_test') }}',
            });
            if (ok) await this.commitMode('test', {});
        }
    },
    async confirmModeReview() {
        const ok = await window.confirmAction({
            title: '{{ __('admin.club_financials_index_mode_switch_title') }}',
            message: '{{ __('admin.club_financials_index_mode_switch_to_live_message') }}',
            type: 'danger',
            confirmText: '{{ __('admin.club_financials_index_mode_go_live') }}',
        });
        if (!ok) return;
        await this.commitMode('live', {
            keep_transaction_ids: this.reviewData.transactions.filter(r => r.keep).map(r => r.id),
            keep_subscription_ids: this.reviewData.subscriptions.filter(r => r.keep).map(r => r.id),
            keep_order_ids: this.reviewData.orders.filter(r => r.keep).map(r => r.id),
        });
    },
    async commitMode(mode, keepIds) {
        this.modeSwitching = true;
        try {
            const fd = new FormData();
            fd.append('_token', document.querySelector('meta[name=csrf-token]')?.content);
            fd.append('mode', mode);
            Object.entries(keepIds).forEach(([key, ids]) => (ids || []).forEach(id => fd.append(key + '[]', id)));
            const r = await fetch(`{{ url('admin/club/'.$club->slug.'/financials/mode') }}`, { method: 'POST', body: fd, headers: { 'Accept': 'application/json' } });
            const d = await r.json();
            if (d.success) {
                this.showModeReviewModal = false;
                window.showToast('success', d.message);
                if (window.__adminShellRefresh) window.__adminShellRefresh();
            } else {
                window.showToast('error', d.message || '{{ __('admin.club_financials_index_error') }}');
            }
        } catch { window.showToast('error', '{{ __('admin.club_financials_index_error') }}'); }
        finally { this.modeSwitching = false; }
    },
    activeTab: 'ledger',
    ledgerPage: 1,
    ledgerPerPage: 25,
    ledgerFilter: 'all',
    ledgerMeta: @js($ledgerMeta),
    get ledgerTotal() { return this.ledgerMeta.length; },
    get ledgerFilteredIndices() {
        return this.ledgerMeta
            .map((row, i) => ({ row, i }))
            .filter(({ row }) => this.rowMatchesLedgerFilter(row))
            .map(({ i }) => i);
    },
    get ledgerFilteredTotal() { return this.ledgerFilteredIndices.length; },
    get ledgerTotalPages() { return Math.max(1, Math.ceil(this.ledgerFilteredTotal / this.ledgerPerPage)); },
    get ledgerStart() { return (this.ledgerPage - 1) * this.ledgerPerPage; },
    get ledgerEnd()   { return this.ledgerPage * this.ledgerPerPage; },
    setLedgerFilter(key) { this.ledgerFilter = key; this.ledgerPage = 1; },
    rowMatchesLedgerFilter(row) {
        if (!row) return true;
        if (this.ledgerFilter === 'all') return true;
        if (this.ledgerFilter === 'pending') return row.status === 'unpaid' || row.status === 'pending_approval';
        return row.type === this.ledgerFilter;
    },
    ledgerRowVisible(i) {
        if (!this.rowMatchesLedgerFilter(this.ledgerMeta[i])) return false;
        const rank = this.ledgerFilteredIndices.indexOf(i);
        return rank >= this.ledgerStart && rank < this.ledgerEnd;
    },
    showIncomeModal: false,
    showExpenseModal: false,
    showAutoExpenseModal: false,
    showExportModal: false,
    showEditModal: false,
    showDeleteModal: false,
    showTransactionDetailModal: false,
    showRefundModal: false,
    editTransaction: null,
    deleteTransactionId: null,
    deleteTransactionRef: '',
    activeTransaction: null,
    approvingPayment: false,
    refundingPayment: false,
    refundTarget: null,
    openEdit(t) { this.editTransaction = t; this.showEditModal = true; },
    openDelete(id, ref) { this.deleteTransactionId = id; this.deleteTransactionRef = ref; this.showDeleteModal = true; },
    openTransactionDetail(id) { this.activeTransaction = window.transactionData?.[id] || null; this.showTransactionDetailModal = true; },
    openPendingDetail(p) {
        this.activeTransaction = {
            transaction_date: p.date_label,
            amount: p.amount,
            member_name: p.name,
            payment_status: p.status,
            description: p.description,
            proof_of_payment: p.proof_of_payment,
            subscription_id: p.subscription_id,
            refund_proof: '',
        };
        this.showTransactionDetailModal = true;
    },
    openRefundModal(t) { this.refundTarget = t; this.showRefundModal = true; },
    async processRefund() {
        if (this.refundingPayment || !this.refundTarget) return;
        this.refundingPayment = true;
        try {
            const fd = new FormData();
            fd.append('_token', document.querySelector('meta[name=csrf-token]')?.content);
            const proof = document.getElementById('hiddenInput_refundProofCropper')?.value;
            if (proof) fd.append('refund_proof_base64', proof);
            const r = await fetch(`{{ url('admin/club/'.$club->slug.'/subscriptions') }}/${this.refundTarget.subscription_id}/refund`, { method:'POST', body:fd, headers:{'Accept':'application/json'} });
            const d = await r.json();
            if (d.success) {
                this.showRefundModal = false;
                window.applyFinancials?.(d.financials);
                window.patchLedgerStatus?.(d.subscription_id, d.payment_status);
                if (window.prependLedgerRow?.(d.transaction)) this.ledgerMeta.push({ type: 'refund', status: null });
                window.showToast('success', d.message || '{{ __("admin.club_financials_index_refund_success") }}');
            }
            else window.showToast('error', d.message || '{{ __("admin.club_financials_index_refund_failed") }}');
        } catch { window.showToast('error', '{{ __("admin.club_financials_index_error") }}'); }
        finally { this.refundingPayment = false; }
    },
    async approvePayment(subscriptionId) {
        if (this.approvingPayment) return;
        this.approvingPayment = true;
        try {
            const fd = new FormData();
            fd.append('_token', document.querySelector('meta[name=csrf-token]')?.content);
            const proof = document.getElementById('hiddenInput_adminProofCropper')?.value;
            if (proof) fd.append('admin_proof_base64', proof);
            const r = await fetch(`{{ url('admin/club/'.$club->slug.'/subscriptions') }}/${subscriptionId}/approve-payment`, { method:'POST', body:fd, headers:{'Accept':'application/json'} });
            const d = await r.json();
            if (d.success) {
                this.showTransactionDetailModal = false;
                window.applyFinancials?.(d.financials);
                window.patchLedgerStatus?.(d.subscription_id, d.payment_status);
                window.showToast('success', d.message || '{{ __("admin.club_financials_index_approve_success") }}');
            }
            else window.showToast('error', d.message || '{{ __("admin.club_financials_index_approve_failed") }}');
        } catch { window.showToast('error', '{{ __("admin.club_financials_index_error") }}'); }
        finally { this.approvingPayment = false; }
    }
}" class="space-y-6">

{{-- ─── Flash ─── --}}
@if($errors->any())
<div class="flex items-start gap-3 px-4 py-3 bg-red-50 border border-red-200 rounded-xl text-sm text-red-800" x-data="{show:true}" x-show="show">
    <i class="bi bi-exclamation-triangle-fill text-red-500 mt-0.5"></i>
    <ul class="flex-1 space-y-0.5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    <button @click="show=false" class="text-red-400 hover:text-red-600"><i class="bi bi-x-lg"></i></button>
</div>
@endif

{{-- ─── Page header ─── --}}
<div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
    <div>
        <h2 class="text-xl font-bold text-gray-900">{{ __('admin.club_financials_index_financials') }}</h2>
        <p class="text-sm text-gray-500 mt-1">{{ __('admin.club_financials_index_subtitle') }}</p>
    </div>
    <div class="flex flex-wrap items-center gap-3">
        <button type="button" @click="toggleMode()" :disabled="modeSwitching" role="switch" :aria-checked="!testMode"
                class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-medium transition-colors disabled:opacity-50"
                :class="testMode ? 'bg-amber-50 text-amber-700 ring-1 ring-amber-200' : 'bg-green-50 text-green-700 ring-1 ring-green-200'">
            <i class="bi" :class="testMode ? 'bi-cone-striped' : 'bi-broadcast'"></i>
            <span x-text="testMode ? '{{ __('admin.club_financials_index_mode_test') }}' : '{{ __('admin.club_financials_index_mode_live') }}'"></span>
            <i class="bi bi-arrow-repeat" :class="modeSwitching && 'animate-spin'"></i>
        </button>
    <div class="flex flex-wrap gap-2" x-data="{ open: false }">
        <div class="relative">
            <button type="button" @click="open = !open" @click.outside="open = false" @keydown.escape="open = false"
                    class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-primary rounded-lg hover:bg-primary/90 transition-colors">
                <i class="bi bi-plus-lg"></i> {{ __('admin.club_financials_index_add_record') }}
                <i class="bi bi-chevron-down text-xs transition-transform duration-200" :class="open && 'rotate-180'"></i>
            </button>
            <div x-show="open" x-cloak x-transition:enter="transition ease-out duration-100"
                 x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-75"
                 x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
                 class="absolute end-0 mt-2 w-56 max-w-[calc(100vw-2rem)] rounded-xl bg-white border border-gray-100 shadow-lg overflow-hidden z-50">
                <button type="button" @click="open = false; showIncomeModal=true"
                        class="w-full text-start flex items-center gap-3 px-4 py-3 text-sm font-medium text-gray-800 hover:bg-muted/60 transition-colors">
                    <i class="bi bi-plus-lg text-green-600 w-4"></i> {{ __('admin.club_financials_index_income') }}
                </button>
                <button type="button" @click="open = false; showExpenseModal=true"
                        class="w-full text-start flex items-center gap-3 px-4 py-3 text-sm font-medium text-gray-800 hover:bg-muted/60 transition-colors">
                    <i class="bi bi-dash-lg text-primary w-4"></i> {{ __('admin.club_financials_index_expense') }}
                </button>
                <button type="button" @click="open = false; showAutoExpenseModal=true"
                        class="w-full text-start flex items-center gap-3 px-4 py-3 text-sm font-medium text-gray-800 hover:bg-muted/60 transition-colors">
                    <i class="bi bi-arrow-repeat text-primary w-4"></i> {{ __('admin.club_financials_index_auto_expense') }}
                </button>
                <button type="button" @click="open = false; showExportModal=true"
                        class="w-full text-start flex items-center gap-3 px-4 py-3 text-sm font-medium text-gray-800 hover:bg-muted/60 transition-colors">
                    <i class="bi bi-download text-primary w-4"></i> {{ __('admin.club_financials_index_export') }}
                </button>
            </div>
        </div>
    </div>
    </div>
</div>

{{-- ─── KPI cards ─── --}}
@php
    $sparkNet      = array_column($monthlyData, 'profit');
    $sparkIncome   = array_column($monthlyData, 'income');
    $sparkExpenses = array_column($monthlyData, 'expenses');
    $sparkCollect  = array_column($monthlyData, 'cash_to_collect');
    $sparkRefunds  = array_column($monthlyData, 'refunds');
    $sparkLabels   = array_column($monthlyData, 'month');
    $netColor      = $netIncome >= 0 ? '#10b981' : '#ef4444';
    $netIconBg     = $netIncome >= 0 ? 'bg-emerald-100' : 'bg-red-100';
    $netIcon       = $netIncome >= 0 ? 'bi-graph-up-arrow' : 'bi-graph-down-arrow';
    $netIconColor  = $netIncome >= 0 ? 'text-emerald-600' : 'text-red-500';
@endphp
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 sm:gap-4">

    <x-stat-card
        card-id="sc-net"
        :label="$netIncome >= 0 ? __('admin.club_financials_index_net_profit') : __('admin.club_financials_index_net_loss')"
        :value="number_format(abs($netIncome), 2)"
        :sub-label="$currency.' · '.abs($marginPct).__('admin.club_financials_index_pct_margin')"
        :icon="$netIcon"
        :icon-bg="$netIconBg"
        :icon-color="$netIconColor"
        :spark-data="$sparkNet"
        :spark-labels="$sparkLabels"
        :spark-color="$netColor"
        refresh-event="financials:updated"
    />

    <x-stat-card
        card-id="sc-income"
        :label="__('admin.club_financials_index_income')"
        :value="number_format($totalIncome, 2)"
        :sub-label="$currency.' · '.$incomeCount.' '.__('admin.club_financials_index_transactions')"
        icon="bi-arrow-down-circle"
        icon-bg="bg-emerald-100"
        icon-color="text-emerald-600"
        :spark-data="$sparkIncome"
        :spark-labels="$sparkLabels"
        spark-color="#10b981"
        refresh-event="financials:updated"
    />

    <x-stat-card
        card-id="sc-expenses"
        :label="__('admin.club_financials_index_expenses')"
        :value="number_format($totalExpenses, 2)"
        :sub-label="$currency.' · '.$expenseCount.' '.__('admin.club_financials_index_transactions')"
        icon="bi-arrow-up-circle"
        icon-bg="bg-red-100"
        icon-color="text-red-500"
        :spark-data="$sparkExpenses"
        :spark-labels="$sparkLabels"
        spark-color="#ef4444"
        refresh-event="financials:updated"
    />

    <x-stat-card
        card-id="sc-collect"
        :label="__('admin.club_financials_index_to_collect')"
        :value="number_format($cashToCollect, 2)"
        :sub-label="$currency.' · '.__('admin.club_financials_index_pending_payments')"
        icon="bi-hourglass-split"
        icon-bg="bg-amber-100"
        icon-color="text-amber-600"
        :spark-data="$sparkCollect"
        :spark-labels="$sparkLabels"
        spark-color="#f59e0b"
        refresh-event="financials:updated"
    />

    <x-stat-card
        card-id="sc-refunds"
        :label="__('admin.club_financials_index_refunds')"
        :value="number_format($totalRefunds, 2)"
        :sub-label="$currency.' · '.__('admin.club_financials_index_issued')"
        icon="bi-arrow-counterclockwise"
        icon-bg="bg-orange-100"
        icon-color="text-orange-500"
        :spark-data="$sparkRefunds"
        :spark-labels="$sparkLabels"
        spark-color="#f97316"
        refresh-event="financials:updated"
    />

</div>

{{-- ─── Chart ─── --}}
@if(count($monthlyData) > 0)
<div class="rounded-xl overflow-hidden">
    <x-financial-chart
        :monthly-data="$monthlyData"
        :transactions="$transactions"
        :cash-to-collect="$pendingSubscriptions"
        :currency="$currency"
        canvas-id="financialChart"
        :maintain-aspect-ratio="false"
        canvas-height-attr="280"
    />
</div>
@endif

{{-- ─── Tabs ─── --}}
<div class="bg-white border border-gray-100 rounded-xl shadow-sm overflow-hidden">

    <div class="border-b border-gray-100 px-1 overflow-x-auto scrollbar-hide">
        <nav class="-mb-px flex min-w-max">
            <button @click="activeTab='ledger'"
                class="px-5 py-3.5 text-sm font-medium border-b-2 transition-colors"
                :class="activeTab==='ledger'
                    ? 'border-primary text-primary'
                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'">
                <i class="bi bi-journal-text me-1.5"></i>
                {{ __('admin.club_financials_index_ledger') }}
                <span id="ledgerCountBadge" class="ms-1.5 px-2 py-0.5 rounded-full text-xs font-medium"
                    :class="activeTab==='ledger' ? 'bg-accent text-primary' : 'bg-gray-100 text-gray-500'">
                    {{ $transactions->count() + $pendingLedgerRows->count() }}
                </span>
            </button>
            <button @click="activeTab='expenses'"
                class="px-5 py-3.5 text-sm font-medium border-b-2 transition-colors"
                :class="activeTab==='expenses'
                    ? 'border-primary text-primary'
                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'">
                <i class="bi bi-pie-chart me-1.5"></i>
                {{ __('admin.club_financials_index_expenses') }}
            </button>
            <button @click="activeTab='reports'"
                class="px-5 py-3.5 text-sm font-medium border-b-2 transition-colors"
                :class="activeTab==='reports'
                    ? 'border-primary text-primary'
                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'">
                <i class="bi bi-file-earmark-bar-graph me-1.5"></i>
                {{ __('admin.club_financials_index_reports') }}
            </button>
        </nav>
    </div>

    {{-- ── Ledger tab ── --}}
    <div x-show="activeTab==='ledger'" x-transition.opacity.duration.150ms>
        @if($transactions->count() > 0 || $pendingLedgerRows->count() > 0)
        {{-- Filter pills --}}
        <div class="flex items-center gap-1.5 overflow-x-auto scrollbar-hide px-5 py-3 border-b border-gray-100">
            @foreach([
                'all'     => [__('admin.fin_filter_all'), $ledgerFilterCounts['all']],
                'pending' => [__('admin.fin_filter_pending'), $ledgerFilterCounts['pending']],
                'income'  => [__('admin.fin_income'), $ledgerFilterCounts['income']],
                'expense' => [__('admin.fin_expense'), $ledgerFilterCounts['expense']],
                'refund'  => [__('admin.fin_refunds'), $ledgerFilterCounts['refund']],
            ] as $key => [$label, $count])
                <button type="button" @click="setLedgerFilter('{{ $key }}')"
                    :class="ledgerFilter === '{{ $key }}' ? 'bg-primary text-white' : 'bg-gray-100 text-gray-500 hover:bg-gray-200'"
                    class="flex-shrink-0 px-3 py-1.5 rounded-full text-xs font-medium transition-colors">
                    {{ $label }} <span class="opacity-70">{{ $count }}</span>
                </button>
            @endforeach
        </div>

        <p x-show="ledgerFilteredTotal === 0" x-cloak class="text-sm text-gray-400 py-8 text-center">{{ __('admin.fin_no_results') }}</p>

        <div class="overflow-x-auto" x-show="ledgerFilteredTotal > 0">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50/60">
                        <th class="px-5 py-3 text-start text-xs font-semibold text-gray-400 uppercase tracking-wider">{{ __('admin.club_financials_index_col_date') }}</th>
                        <th class="px-5 py-3 text-start text-xs font-semibold text-gray-400 uppercase tracking-wider">{{ __('admin.club_financials_index_col_description') }}</th>
                        <th class="px-5 py-3 text-start text-xs font-semibold text-gray-400 uppercase tracking-wider">{{ __('admin.club_financials_index_col_category') }}</th>
                        <th class="px-5 py-3 text-start text-xs font-semibold text-gray-400 uppercase tracking-wider">{{ __('admin.club_financials_index_col_method') }}</th>
                        <th class="px-5 py-3 text-start text-xs font-semibold text-gray-400 uppercase tracking-wider">{{ __('admin.club_financials_index_col_status') }}</th>
                        <th class="px-5 py-3 text-end text-xs font-semibold text-gray-400 uppercase tracking-wider">{{ __('admin.club_financials_index_col_amount') }}</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50" id="ledgerBody">
                    @foreach($ledgerRows as $row)
                    @if($row['kind'] === 'pending')
                    @php $p = $row['data']; @endphp
                    <tr class="group transition-colors hover:bg-gray-50/70 cursor-pointer"
                        data-sub-id="{{ $p['subscription_id'] }}" data-txn-type="pending"
                        x-show="ledgerRowVisible({{ $loop->index }})"
                        @click="openPendingDetail(@js([
                            'name'             => $p['name'],
                            'date_label'       => $p['date']?->format('M d, Y') ?? '—',
                            'amount'           => $p['amount'],
                            'status'           => $p['status'],
                            'description'      => $p['description'],
                            'proof_of_payment' => $p['proof_of_payment'],
                            'subscription_id'  => $p['subscription_id'],
                        ]))">

                        <td class="px-5 py-3.5 whitespace-nowrap text-gray-500 text-xs">
                            {{ $p['date']?->format('d M Y') ?? '—' }}
                        </td>

                        <td class="px-5 py-3.5">
                            <div class="flex items-center gap-2.5">
                                <span class="w-1.5 h-1.5 rounded-full bg-amber-400 flex-shrink-0"></span>
                                <span class="text-gray-800 font-medium truncate max-w-[180px]">{{ $p['name'] }}</span>
                            </div>
                        </td>

                        <td class="px-5 py-3.5 text-gray-500 text-xs capitalize">{{ $p['package'] ?? '—' }}</td>

                        <td class="px-5 py-3.5"><span class="text-gray-300">—</span></td>

                        <td class="px-5 py-3.5 js-status-cell">
                            @if($p['status'] === 'pending_approval')
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-blue-50 text-blue-600">
                                    <i class="bi bi-hourglass-split text-[10px]"></i> {{ __('admin.club_financials_index_status_pending') }}
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-amber-50 text-amber-600">
                                    <i class="bi bi-clock-fill text-[10px]"></i> {{ __('admin.club_financials_index_status_unpaid') }}
                                </span>
                            @endif
                        </td>

                        <td class="px-5 py-3.5 text-end font-semibold tabular-nums whitespace-nowrap text-amber-600">
                            {{ $currency }} {{ number_format($p['amount'], 2) }}
                        </td>

                        <td class="px-3 py-3.5">
                            <div class="flex items-center justify-end opacity-0 group-hover:opacity-100 transition-opacity">
                                <i class="bi bi-chevron-right text-gray-300 text-xs"></i>
                            </div>
                        </td>
                    </tr>
                    @else
                    @php
                        $t = $row['data'];
                        $subPayStatus = $t->subscription?->payment_status ?? null;
                        $isClickable  = $t->type === 'income' && $t->subscription_id && $subPayStatus !== null;
                    @endphp
                    <tr class="group transition-colors hover:bg-gray-50/70 {{ $isClickable ? 'cursor-pointer' : '' }}"
                        data-sub-id="{{ $t->subscription_id ?? '' }}" data-txn-type="{{ $t->type }}"
                        x-show="ledgerRowVisible({{ $loop->index }})"
                        {{ $isClickable ? '@click=openTransactionDetail('.$t->id.')' : '' }}>

                        <td class="px-5 py-3.5 whitespace-nowrap text-gray-500 text-xs">
                            {{ $t->transaction_date?->format('d M Y') ?? '—' }}
                        </td>

                        <td class="px-5 py-3.5">
                            <div class="flex items-center gap-2.5">
                                @if($t->type === 'income')
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 flex-shrink-0"></span>
                                @elseif($t->type === 'expense')
                                    <span class="w-1.5 h-1.5 rounded-full bg-red-400 flex-shrink-0"></span>
                                @else
                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-400 flex-shrink-0"></span>
                                @endif
                                <span class="text-gray-800 font-medium truncate max-w-[180px]">{{ $t->description ?? '—' }}</span>
                            </div>
                            @if($t->reference_number)
                            <p class="ms-4 text-xs text-gray-400 font-mono mt-0.5">{{ $t->reference_number }}</p>
                            @endif
                        </td>

                        <td class="px-5 py-3.5 text-gray-500 text-xs capitalize">{{ $t->category ?? '—' }}</td>

                        <td class="px-5 py-3.5">
                            @if($t->payment_method)
                            <span class="inline-flex items-center gap-1 text-xs text-gray-500 capitalize">
                                @if($t->payment_method === 'cash') <i class="bi bi-cash-stack text-emerald-500"></i>
                                @elseif($t->payment_method === 'bank_transfer') <i class="bi bi-bank text-blue-500"></i>
                                @elseif($t->payment_method === 'card') <i class="bi bi-credit-card text-primary"></i>
                                @else <i class="bi bi-globe text-gray-400"></i>
                                @endif
                                {{ ucfirst(str_replace('_',' ',$t->payment_method)) }}
                            </span>
                            @else
                            <span class="text-gray-300">—</span>
                            @endif
                        </td>

                        <td class="px-5 py-3.5 js-status-cell">
                            @if($subPayStatus === 'pending_approval')
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-blue-50 text-blue-600">
                                    <i class="bi bi-hourglass-split text-[10px]"></i> {{ __('admin.club_financials_index_status_pending') }}
                                </span>
                            @elseif($subPayStatus === 'paid')
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-green-50 text-green-600">
                                    <i class="bi bi-check-circle-fill text-[10px]"></i> {{ __('admin.club_financials_index_status_paid') }}
                                </span>
                            @elseif($subPayStatus === 'unpaid')
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-amber-50 text-amber-600">
                                    <i class="bi bi-clock-fill text-[10px]"></i> {{ __('admin.club_financials_index_status_unpaid') }}
                                </span>
                            @else
                                <span class="text-gray-300 text-xs">—</span>
                            @endif
                        </td>

                        <td class="px-5 py-3.5 text-end font-semibold tabular-nums whitespace-nowrap
                            {{ $t->type === 'income' ? 'text-emerald-600' : ($t->type === 'refund' ? 'text-amber-600' : 'text-red-500') }}">
                            {{ $t->type === 'income' ? '+' : '−' }}{{ $currency }} {{ number_format($t->amount, 2) }}
                        </td>

                        <td class="px-3 py-3.5">
                            <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                <button type="button"
                                    title="{{ __('shared.edit') }}"
                                    @click.stop="openEdit({
                                        id: {{ $t->id }},
                                        description: @js($t->description ?? ''),
                                        amount: {{ $t->amount }},
                                        transaction_date: '{{ $t->transaction_date?->format('Y-m-d') }}',
                                        type: '{{ $t->type }}',
                                        category: @js($t->category ?? ''),
                                        payment_method: '{{ $t->payment_method ?? 'cash' }}',
                                        reference_number: @js($t->reference_number ?? '')
                                    })"
                                    class="w-7 h-7 flex items-center justify-center rounded-md border border-gray-200 text-gray-400 hover:text-blue-600 hover:border-blue-200 hover:bg-blue-50 transition-colors text-xs">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button type="button"
                                    title="{{ __('shared.delete') }}"
                                    @click.stop="openDelete({{ $t->id }}, @js($t->reference_number ?? $t->description))"
                                    class="w-7 h-7 flex items-center justify-center rounded-md border border-gray-200 text-gray-400 hover:text-red-600 hover:border-red-200 hover:bg-red-50 transition-colors text-xs">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @endif
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Ledger pagination --}}
        <div class="flex items-center justify-between px-5 py-3.5 border-t border-gray-100 text-sm" x-show="ledgerTotalPages > 1">
            <span class="text-gray-400 text-xs">
                {{ __('admin.club_financials_index_showing') }} <strong class="text-gray-700" x-text="ledgerStart + 1"></strong>–<strong class="text-gray-700" x-text="Math.min(ledgerEnd, ledgerFilteredTotal)"></strong>
                {{ __('admin.club_financials_index_of') }} <strong class="text-gray-700" x-text="ledgerFilteredTotal"></strong>
            </span>
            <div class="flex items-center gap-1.5">
                <button @click="ledgerPage = Math.max(1, ledgerPage - 1)" :disabled="ledgerPage === 1"
                    class="w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-50 disabled:opacity-30 disabled:pointer-events-none transition-colors text-xs">
                    <i class="bi bi-chevron-left"></i>
                </button>
                <span class="px-2 text-xs text-gray-500" x-text="ledgerPage + ' / ' + ledgerTotalPages"></span>
                <button @click="ledgerPage = Math.min(ledgerTotalPages, ledgerPage + 1)" :disabled="ledgerPage >= ledgerTotalPages"
                    class="w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-50 disabled:opacity-30 disabled:pointer-events-none transition-colors text-xs">
                    <i class="bi bi-chevron-right"></i>
                </button>
            </div>
        </div>

        @else
        <div class="py-16 text-center">
            <div class="w-14 h-14 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-4">
                <i class="bi bi-receipt text-2xl text-gray-300"></i>
            </div>
            <p class="text-gray-500 font-medium">{{ __('admin.club_financials_index_no_transactions') }}</p>
            <p class="text-gray-400 text-sm mt-1 mb-4">{{ __('admin.club_financials_index_no_transactions_hint') }}</p>
            <div class="flex gap-2 justify-center">
                <button @click="showIncomeModal=true" class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-green-700 bg-green-50 border border-green-200 rounded-lg hover:bg-green-100 transition-colors">
                    <i class="bi bi-plus-lg"></i> {{ __('admin.club_financials_index_income') }}
                </button>
                <button @click="showExpenseModal=true" class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-white bg-primary rounded-lg hover:bg-primary/90 transition-colors">
                    <i class="bi bi-dash-lg"></i> {{ __('admin.club_financials_index_expense') }}
                </button>
            </div>
        </div>
        @endif
    </div>

    {{-- ── Expenses tab ── --}}
    <div x-show="activeTab==='expenses'" x-transition.opacity.duration.150ms class="p-5">
        @if($expenseCategories->count() > 0)
        @php $expTotal = $expenseCategories->sum('total'); @endphp
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach($expenseCategories as $cat)
            @php $pct = $expTotal > 0 ? round($cat['total'] / $expTotal * 100) : 0; @endphp
            <div class="border border-gray-100 rounded-xl p-4 space-y-3">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-semibold text-gray-800 capitalize">{{ $cat['category'] ?? __('admin.club_financials_index_uncategorized') }}</p>
                    <p class="text-sm font-bold text-red-500">{{ $currency }} {{ number_format($cat['total'], 2) }}</p>
                </div>
                <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden">
                    <div class="h-full bg-red-400 rounded-full transition-all duration-500" style="width: {{ $pct }}%"></div>
                </div>
                <div class="space-y-1.5">
                    @foreach($cat['items'] as $item)
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-gray-500 truncate max-w-[60%]">{{ $item->description }}</span>
                        <div class="flex items-center gap-3 flex-shrink-0">
                            <span class="text-gray-400">{{ $item->transaction_date?->format('d M') }}</span>
                            <span class="font-medium text-gray-700">{{ $currency }} {{ number_format($item->amount, 2) }}</span>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endforeach
        </div>
        @else
        <div class="py-16 text-center">
            <div class="w-14 h-14 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-4">
                <i class="bi bi-pie-chart text-2xl text-gray-300"></i>
            </div>
            <p class="text-gray-500 font-medium">{{ __('admin.club_financials_index_no_expenses') }}</p>
            <p class="text-gray-400 text-sm mt-1">{{ __('admin.club_financials_index_no_expenses_hint') }}</p>
        </div>
        @endif
    </div>

    {{-- ── Reports tab ── --}}
    <div x-show="activeTab==='reports'" x-transition.opacity.duration.150ms class="p-5">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

            {{-- P&L --}}
            <div class="border border-gray-100 rounded-xl p-5 space-y-2.5">
                <p class="text-sm font-semibold text-gray-700 mb-4">{{ __('admin.club_financials_index_profit_loss') }}</p>
                <div class="flex justify-between items-center py-2.5 px-3 bg-green-50 rounded-lg">
                    <span class="text-sm text-green-700 flex items-center gap-2"><i class="bi bi-arrow-down-circle"></i> {{ __('admin.club_financials_index_gross_income') }}</span>
                    <span class="text-sm font-bold text-green-700">{{ $currency }} {{ number_format($totalIncome, 2) }}</span>
                </div>
                <div class="flex justify-between items-center py-2.5 px-3 bg-red-50 rounded-lg">
                    <span class="text-sm text-red-600 flex items-center gap-2"><i class="bi bi-arrow-up-circle"></i> {{ __('admin.club_financials_index_total_expenses') }}</span>
                    <span class="text-sm font-bold text-red-600">−{{ $currency }} {{ number_format($totalExpenses, 2) }}</span>
                </div>
                @if($totalRefunds > 0)
                <div class="flex justify-between items-center py-2.5 px-3 bg-amber-50 rounded-lg">
                    <span class="text-sm text-amber-700 flex items-center gap-2"><i class="bi bi-arrow-counterclockwise"></i> {{ __('admin.club_financials_index_refunds') }}</span>
                    <span class="text-sm font-bold text-amber-700">−{{ $currency }} {{ number_format($totalRefunds, 2) }}</span>
                </div>
                @endif
                <div class="flex justify-between items-center py-3 px-3 rounded-lg mt-1
                    {{ $netIncome >= 0 ? 'bg-emerald-500' : 'bg-red-500' }} text-white">
                    <span class="text-sm font-bold">{{ $netIncome >= 0 ? __('admin.club_financials_index_net_profit') : __('admin.club_financials_index_net_loss') }}</span>
                    <span class="text-base font-bold">{{ $currency }} {{ number_format(abs($netIncome), 2) }}</span>
                </div>
            </div>

            {{-- Transaction summary --}}
            <div class="border border-gray-100 rounded-xl p-5">
                <p class="text-sm font-semibold text-gray-700 mb-4">{{ __('admin.club_financials_index_transaction_summary') }}</p>
                <div class="space-y-3">
                    @php
                        $rows = [
                            ['label'=> __('admin.club_financials_index_total_transactions'),'value'=> $transactions->count(),'color'=>'text-gray-900'],
                            ['label'=> __('admin.club_financials_index_income'),'value'=> $incomeCount,'color'=>'text-emerald-600'],
                            ['label'=> __('admin.club_financials_index_expenses'),'value'=> $expenseCount,'color'=>'text-red-500'],
                            ['label'=> __('admin.club_financials_index_refunds'),'value'=> $transactions->where('type','refund')->count(),'color'=>'text-amber-600'],
                            ['label'=> __('admin.club_financials_index_profit_margin'),'value'=> $marginPct.'%','color'=> $marginPct >= 0 ? 'text-emerald-600' : 'text-red-500'],
                        ];
                    @endphp
                    @foreach($rows as $i => $row)
                    <div class="flex justify-between items-center {{ $i > 0 ? 'pt-3 border-t border-gray-50' : '' }}">
                        <span class="text-sm text-gray-500">{{ $row['label'] }}</span>
                        <span class="text-sm font-bold {{ $row['color'] }}">{{ $row['value'] }}</span>
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- Payment methods --}}
            @php
                $paymentMethods = $transactions->whereNotNull('payment_method')->groupBy('payment_method')->map(fn($i) => $i->sum('amount'));
                $pmTotal = $paymentMethods->sum();
            @endphp
            @if($paymentMethods->count() > 0)
            <div class="border border-gray-100 rounded-xl p-5 md:col-span-2">
                <p class="text-sm font-semibold text-gray-700 mb-4">{{ __('admin.club_financials_index_payment_methods') }}</p>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    @foreach($paymentMethods as $method => $total)
                    @php
                        $pct = $pmTotal > 0 ? round($total / $pmTotal * 100) : 0;
                        $icons = ['cash'=>'bi-cash-stack','bank_transfer'=>'bi-bank','card'=>'bi-credit-card','online'=>'bi-globe'];
                        $icon = $icons[$method] ?? 'bi-three-dots';
                    @endphp
                    <div class="border border-gray-100 rounded-xl p-4 text-center">
                        <i class="bi {{ $icon }} text-xl text-primary mb-2 block"></i>
                        <p class="text-xs text-gray-400 capitalize">{{ ucfirst(str_replace('_',' ',$method)) }}</p>
                        <p class="text-sm font-bold text-gray-800 mt-1">{{ $currency }} {{ number_format($total, 2) }}</p>
                        <p class="text-xs text-gray-400">{{ $pct }}%</p>
                        <div class="h-1 bg-gray-100 rounded-full mt-2 overflow-hidden">
                            <div class="h-full bg-primary rounded-full" style="width: {{ $pct }}%"></div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

        </div>
    </div>

</div>{{-- /tabs card --}}

{{-- ─── Modals ─── --}}
<x-income-modal :club="$club" :currency="$currency" />
<x-expense-modal :club="$club" />
@include('admin.club.financials.partials.auto-expense-modal')
@include('admin.club.financials.partials.export-modal')
@include('admin.club.financials.partials.edit-modal')
@include('admin.club.financials.partials.delete-modal')
@include('admin.club.financials.partials.transaction-detail-modal')
@include('admin.club.financials.partials.refund-modal')
@include('admin.club.financials.partials.mode-review-modal')

</div>{{-- /x-data root --}}

@push('scripts')
<script>
// Another admin session switched this club's Test/Live mode — refresh in place.
if (window.__financialsModeHandler) window.removeEventListener('realtime:financials', window.__financialsModeHandler);
window.__financialsModeHandler = function (e) {
    if (e.detail?.action === 'refresh' && window.__adminShellRefresh) window.__adminShellRefresh();
};
window.addEventListener('realtime:financials', window.__financialsModeHandler);

window.transactionData = {
    @foreach($transactions as $t)
    {{ $t->id }}: {
        id: {{ $t->id }},
        type: @json($t->type),
        description: @json($t->description ?? ''),
        amount: {{ $t->amount }},
        transaction_date: @json($t->transaction_date?->format('M d, Y') ?? ''),
        category: @json($t->category ?? ''),
        payment_method: @json($t->payment_method ?? ''),
        subscription_id: {{ $t->subscription_id ?? 'null' }},
        amount_paid: {{ $t->subscription?->amount_paid ?? 'null' }},
        payment_status: @json($t->subscription?->payment_status ?? ''),
        proof_of_payment: @json($t->subscription?->proof_of_payment ? route('admin.club.subscriptions.payment-proof', ['club'=>$club,'subscription'=>$t->subscription_id]) : ''),
        refund_proof: @json($t->subscription?->refund_proof ? route('admin.club.subscriptions.refund-proof', ['club'=>$club,'subscription'=>$t->subscription_id]) : ''),
        member_name: @json($t->subscription?->user?->full_name ?? $t->subscription?->user?->name ?? ''),
        member_avatar: @json($t->subscription?->user?->profile_picture ? asset('storage/'.$t->subscription->user->profile_picture) : ''),
    },
    @endforeach
};

// ── In-place financial UI updates (no page reload) ───────────────────────────
window._finCurrency = @json($currency);

window._finMoney = function (n) {
    return Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

// Escape user-supplied strings before inserting via innerHTML (prevents stored XSS).
window._finEsc = function (s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
};

// Re-render the 5 KPI stat cards + their sparklines from a fresh summary/monthly payload.
window.applyFinancials = function (data) {
    if (!data || !window.StatCard) return;
    var s = data.summary || {};
    var m = data.monthly || [];
    var col = function (k) { return m.map(function (row) { return Number(row[k] || 0); }); };
    var net    = Number(s.net_profit || 0);
    var income = Number(s.total_income || 0);
    var margin = income > 0 ? Math.round((net / income) * 1000) / 10 : 0;

    StatCard.update('sc-net', {
        value:    window._finMoney(Math.abs(net)),
        label:    net >= 0 ? '{{ __("admin.club_financials_index_net_profit") }}' : '{{ __("admin.club_financials_index_net_loss") }}',
        subLabel: window._finCurrency + ' · ' + Math.abs(margin) + '{{ __("admin.club_financials_index_pct_margin") }}',
        sparkData: col('profit'),
    });
    StatCard.update('sc-income',   { value: window._finMoney(s.total_income),     sparkData: col('income') });
    StatCard.update('sc-expenses', { value: window._finMoney(s.total_expenses),   sparkData: col('expenses') });
    StatCard.update('sc-collect',  { value: window._finMoney(s.pending),          sparkData: col('cash_to_collect') });
    StatCard.update('sc-refunds',  { value: window._finMoney(s.refunds),          sparkData: col('refunds') });
};

// Build the status-badge markup matching the server-rendered ledger.
window._finStatusBadge = function (status) {
    switch (status) {
        case 'pending_approval':
            return '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-blue-50 text-blue-600"><i class="bi bi-hourglass-split text-[10px]"></i> {{ __("admin.club_financials_index_status_pending") }}</span>';
        case 'paid':
            return '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-green-50 text-green-600"><i class="bi bi-check-circle-fill text-[10px]"></i> {{ __("admin.club_financials_index_status_paid") }}</span>';
        case 'unpaid':
            return '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-amber-50 text-amber-600"><i class="bi bi-clock-fill text-[10px]"></i> {{ __("admin.club_financials_index_status_unpaid") }}</span>';
        default:
            return '<span class="text-gray-300 text-xs">—</span>';
    }
};

// Patch the status badge of every ledger row tied to a subscription.
window.patchLedgerStatus = function (subId, status) {
    if (!subId) return;
    document.querySelectorAll('#ledgerBody tr[data-sub-id="' + subId + '"]').forEach(function (row) {
        var cell = row.querySelector('.js-status-cell');
        if (cell) cell.innerHTML = window._finStatusBadge(status);
    });
};

// Prepend a freshly created refund transaction to the ledger (shown on page 1 only).
window.prependLedgerRow = function (t) {
    var body = document.getElementById('ledgerBody');
    if (!body || !t) return false;
    var cur = window._finEsc(window._finCurrency);
    var esc = window._finEsc;
    var tr = document.createElement('tr');
    tr.className = 'group transition-colors hover:bg-gray-50/70';
    tr.setAttribute('data-sub-id', '');
    tr.setAttribute('data-txn-type', 'refund');
    tr.setAttribute('x-show', "ledgerPage === 1 && (ledgerFilter === 'all' || ledgerFilter === 'refund')");
    tr.innerHTML =
        '<td class="px-5 py-3.5 whitespace-nowrap text-gray-500 text-xs">' + esc(t.transaction_date || '—') + '</td>' +
        '<td class="px-5 py-3.5"><div class="flex items-center gap-2.5">' +
            '<span class="w-1.5 h-1.5 rounded-full bg-amber-400 flex-shrink-0"></span>' +
            '<span class="text-gray-800 font-medium truncate max-w-[180px]">' + esc(t.description || '—') + '</span>' +
        '</div></td>' +
        '<td class="px-5 py-3.5 text-gray-500 text-xs capitalize">' + esc(t.category || '—') + '</td>' +
        '<td class="px-5 py-3.5"><span class="inline-flex items-center gap-1 text-xs text-gray-500 capitalize"><i class="bi bi-bank text-blue-500"></i> {{ __("admin.club_financials_index_bank_transfer") }}</span></td>' +
        '<td class="px-5 py-3.5 js-status-cell"><span class="text-gray-300 text-xs">—</span></td>' +
        '<td class="px-5 py-3.5 text-end font-semibold tabular-nums whitespace-nowrap text-amber-600">−' + cur + ' ' + window._finMoney(t.amount) + '</td>' +
        '<td class="px-3 py-3.5"></td>';
    body.insertBefore(tr, body.firstElementChild);

    var badge = document.getElementById('ledgerCountBadge');
    if (badge) badge.textContent = (parseInt(badge.textContent, 10) || 0) + 1;
    return true;
};

function exportCSV() {
    @php
        $csvData = $transactions->map(fn($t) => [
            'date'             => $t->transaction_date?->format('Y-m-d') ?? '',
            'type'             => $t->type,
            'description'      => $t->description ?? '',
            'category'         => $t->category ?? '',
            'amount'           => $t->amount,
            'payment_method'   => $t->payment_method ?? '',
            'reference_number' => $t->reference_number ?? '',
        ])->values();
    @endphp
    const rows = @json($csvData);
    if (!rows.length) { window.showToast('info', '{{ __("admin.club_financials_index_no_export") }}'); return; }
    const headers = ['{{ __("admin.club_financials_index_col_date") }}','{{ __("admin.club_financials_index_col_type") }}','{{ __("admin.club_financials_index_col_description") }}','{{ __("admin.club_financials_index_col_category") }}','{{ __("admin.club_financials_index_col_amount") }}','{{ __("admin.club_financials_index_payment_method") }}','{{ __("admin.club_financials_index_reference") }}'];
    const csv = [headers, ...rows.map(r => [
        r.date, r.type,
        '"' + (r.description||'').replace(/"/g,'""') + '"',
        '"' + (r.category||'').replace(/"/g,'""') + '"',
        r.amount, r.payment_method, r.reference_number
    ])].map(r => r.join(',')).join('\n');
    const a = Object.assign(document.createElement('a'), {
        href: URL.createObjectURL(new Blob([csv], {type:'text/csv'})),
        download: (document.getElementById('exportFileName')?.value || 'transactions-{{ now()->format("Y-m-d") }}') + '.csv'
    });
    a.click();
    URL.revokeObjectURL(a.href);
}

</script>
@endpush
@endsection
