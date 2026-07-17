@extends('layouts.admin-club-mobile')

@section('title', ($club->club_name ?? __('admin.club')) . ' · ' . __('admin.nav_financials'))

@section('club-admin-content')
@php
    $cur = $currency ?? ($club->currency ?: '');
    $today = date('Y-m-d');
    $counts = [
        'all'     => $transactions->count() + $pendingSubscriptions->count(),
        'pending' => $pendingSubscriptions->count(),
        'income'  => $transactions->where('type', 'income')->count(),
        'expense' => $transactions->where('type', 'expense')->count(),
        'refund'  => $transactions->where('type', 'refund')->count(),
    ];

    // Pending (unpaid) subscriptions surfaced as ledger entries — interleaved with transactions
    // by date below rather than grouped together, matching the desktop chronological ledger.
    $pendingLedger = $pendingSubscriptions->map(function ($sub) use ($cur) {
        $name = $sub->user->full_name ?? $sub->user->name ?? __('admin.fin_member');
        $amt  = (float) ($sub->amount_due ?? 0);
        return [
            'id'              => 'pending-' . $sub->id,
            'type'            => 'pending',
            'label'           => $name,
            'amount'          => $amt,
            'amount_fmt'      => $cur . ' ' . number_format($amt, 2),
            'date_label'      => $sub->package?->name ?? __('admin.fin_awaiting'),
            'pm_label'        => '',
            'search'          => mb_strtolower(trim($name . ' ' . ($sub->package?->name ?? '') . ' pending ' . __('admin.fin_awaiting') . ' ' . number_format($amt, 2))),
            'refundable'      => false,
            'subscription_id' => $sub->id,
            'member_url'      => $sub->user?->uuid ? route('member.show', $sub->user->uuid) : route('admin.club.members', $club->slug),
            'sort_date'       => $sub->start_date ? \Illuminate\Support\Carbon::parse($sub->start_date) : null,
        ];
    })->values();

    $txLedger = $transactions->map(function ($t) use ($cur) {
        $sub        = $t->subscription;
        $refundable = $t->type === 'income' && $sub && $sub->payment_status === 'paid';
        $label      = $t->description ?: ucfirst($t->category ?? $t->type);
        $pmKey      = $t->payment_method ? ($t->payment_method === 'bank_transfer' ? 'bank' : $t->payment_method) : '';
        $dateLabel  = $t->transaction_date ? $t->transaction_date->locale(app()->getLocale())->isoFormat('D MMM YYYY') : '';
        $search     = mb_strtolower(trim(implode(' ', array_filter([
            $label, $t->category, $t->type, $t->reference_number,
            number_format((float) $t->amount, 2), $dateLabel,
        ]))));

        return [
            'id'               => $t->id,
            'type'             => $t->type,
            'label'            => $label,
            'amount'           => (float) $t->amount,
            'amount_fmt'       => $cur . ' ' . number_format((float) $t->amount, 2),
            'date_label'       => $dateLabel,
            'pm_label'         => $pmKey ? __('admin.fin_pm_' . $pmKey) : '',
            'search'           => $search,
            // raw fields for the edit sheet
            'description'      => $t->description ?? '',
            'transaction_date' => $t->transaction_date?->format('Y-m-d'),
            'category'         => $t->category ?? '',
            'payment_method'   => $t->payment_method ?? 'cash',
            'reference_number' => $t->reference_number ?? '',
            // refund + delete
            'refundable'       => $refundable,
            'subscription_id'  => $sub?->id,
            'amount_paid'      => $refundable ? (float) $sub->amount_paid : 0,
            'ref'              => $t->reference_number ?: $label,
            'sort_date'        => $t->transaction_date,
        ];
    })->values();

    // Single date-sorted ledger — pending rows are interleaved with transactions by date rather
    // than grouped together, matching the desktop ledger exactly (admin/club/financials/index.blade.php).
    $ledger = $pendingLedger->concat($txLedger)
        ->sortByDesc(fn ($row) => $row['sort_date'] ?? \Illuminate\Support\Carbon::minValue())
        ->map(function ($row) {
            unset($row['sort_date']);
            return $row;
        })
        ->values();

    $netVal      = (float) ($summary['net_profit'] ?? 0);
    $incomeVal   = (float) ($summary['total_income'] ?? 0);
    $expensesVal = (float) ($summary['total_expenses'] ?? 0);
@endphp

<div class="-mx-4 -mt-4"
     x-data="{
        // ── transaction sheet (income / expense / edit) ──
        txOpen: false, txMode: 'income', txId: null,
        tx: { type: 'income', description: '', amount: '', transaction_date: '{{ $today }}', category: '', payment_method: 'cash', reference_number: '', recurring: false },
        incomeCats: [
            { v: 'subscription', l: @js(__('admin.fin_cat_subscription')) },
            { v: 'event',        l: @js(__('admin.fin_cat_event')) },
            { v: 'product_sale', l: @js(__('admin.fin_cat_product_sale')) },
            { v: 'sponsorship',  l: @js(__('admin.fin_cat_sponsorship')) },
            { v: 'donation',     l: @js(__('admin.fin_cat_donation')) },
            { v: 'other',        l: @js(__('admin.fin_cat_other')) },
        ],
        expenseCats: [
            { v: 'rent',        l: @js(__('admin.fin_cat_rent')) },
            { v: 'utilities',   l: @js(__('admin.fin_cat_utilities')) },
            { v: 'equipment',   l: @js(__('admin.fin_cat_equipment')) },
            { v: 'salaries',    l: @js(__('admin.fin_cat_salaries')) },
            { v: 'maintenance', l: @js(__('admin.fin_cat_maintenance')) },
            { v: 'marketing',   l: @js(__('admin.fin_cat_marketing')) },
            { v: 'insurance',   l: @js(__('admin.fin_cat_insurance')) },
            { v: 'other',       l: @js(__('admin.fin_cat_other')) },
        ],
        get txCats() { return this.tx.type === 'expense' ? this.expenseCats : (this.tx.type === 'income' ? this.incomeCats : []); },
        get txIsRecurring() { return this.txMode === 'expense' && this.tx.recurring; },
        get txAction() {
            if (this.txMode === 'edit') return `{{ url('admin/club/' . $club->slug . '/financials') }}/${this.txId}`;
            if (this.txIsRecurring) return '{{ route('admin.club.financials.recurring.store', $club->slug) }}';
            return this.txMode === 'expense' ? '{{ route('admin.club.financials.expense', $club->slug) }}' : '{{ route('admin.club.financials.income', $club->slug) }}';
        },
        resetTx() { this.tx = { type: 'income', description: '', amount: '', transaction_date: '{{ $today }}', category: '', payment_method: 'cash', reference_number: '', recurring: false }; },
        openIncome()  { this.txMode = 'income';  this.txId = null; this.resetTx(); this.tx.type = 'income';  this.txOpen = true; },
        openExpense() { this.txMode = 'expense'; this.txId = null; this.resetTx(); this.tx.type = 'expense'; this.txOpen = true; },
        openEdit(t)   { this.txMode = 'edit'; this.txId = t.id; this.tx = { type: t.type, description: t.description || '', amount: t.amount, transaction_date: t.transaction_date, category: t.category || '', payment_method: t.payment_method || 'cash', reference_number: t.reference_number || '', recurring: false }; this.txOpen = true; },
        submitTx() {
            if (!this.tx.description.trim()) { window.showToast('warning', @js(__('admin.fin_err_desc'))); return; }
            if (!(parseFloat(this.tx.amount) >= 0) || this.tx.amount === '') { window.showToast('warning', @js(__('admin.fin_err_amount'))); return; }
            if (!this.tx.transaction_date) { window.showToast('warning', @js(__('admin.fin_err_date'))); return; }
            this.$refs.txForm.submit();
        },

        goMembers() { window.location.href = '{{ route('admin.club.members', $club->slug) }}'; },
        openMemberPage(r) { window.location.href = r.member_url || '{{ route('admin.club.members', $club->slug) }}'; },
        async markPaid(r) {
            if (!r.subscription_id) return;
            const ok = await window.confirmAction({ title: @js(__('admin.fin_mark_paid')), message: @js(__('admin.fin_mark_paid_confirm')), type: 'success', confirmText: @js(__('admin.fin_mark_paid')) });
            if (!ok) return;
            try {
                const res = await fetch(`{{ url('admin/club/' . $club->slug . '/subscriptions') }}/${r.subscription_id}/approve-payment`, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
                });
                const d = await res.json();
                if (res.ok && d.success) {
                    window.showToast('success', d.message || @js(__('admin.fin_mark_paid_done')));
                    setTimeout(() => window.location.reload(), 600);
                } else {
                    window.showToast('error', d.message || @js(__('admin.fin_mark_paid_fail')));
                }
            } catch (e) { window.showToast('error', @js(__('admin.fin_mark_paid_fail'))); }
        },

        // ── delete confirm ──
        delOpen: false, delId: null, delRef: '',
        openDelete(id, ref) { this.delId = id; this.delRef = ref || ''; this.delOpen = true; },

        // ── refund ──
        rfOpen: false, rfTarget: null, rfBusy: false, rfProof: '', rfProofName: '',
        openRefund(t) { this.rfTarget = t; this.rfProof = ''; this.rfProofName = ''; this.rfOpen = true; },
        onRefundProof(e) {
            const f = e.target.files[0];
            if (!f) { this.rfProof = ''; this.rfProofName = ''; return; }
            this.rfProofName = f.name;
            const r = new FileReader();
            r.onload = ev => this.rfProof = ev.target.result;
            r.readAsDataURL(f);
        },
        async processRefund() {
            if (this.rfBusy || !this.rfTarget) return;
            this.rfBusy = true;
            try {
                const fd = new FormData();
                if (this.rfProof) fd.append('refund_proof_base64', this.rfProof);
                const res = await fetch(`{{ url('admin/club/' . $club->slug . '/subscriptions') }}/${this.rfTarget.subscription_id}/refund`, {
                    method: 'POST', body: fd,
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
                });
                const d = await res.json();
                if (res.ok && d.success) {
                    window.showToast('success', d.message || @js(__('admin.fin_refund_done')));
                    this.rfOpen = false;
                    setTimeout(() => window.location.reload(), 600);
                } else {
                    window.showToast('error', d.message || @js(__('admin.fin_refund_fail')));
                }
            } catch (e) { window.showToast('error', @js(__('admin.fin_refund_fail'))); }
            finally { this.rfBusy = false; }
        },

        // ── ledger filter / search ──
        txFilter: 'all',
        txSearch: '',
        ledger: @js($ledger),
        get filteredLedger() {
            const q = this.txSearch.trim().toLowerCase();
            return this.ledger.filter(r =>
                (this.txFilter === 'all' || this.txFilter === r.type) &&
                (q === '' || r.search.includes(q))
            );
        },
    }">

    {{-- ===== Hero ===== --}}
    <header class="m-hero px-5 pt-7 pb-6 text-white relative overflow-hidden">
        <div class="absolute -end-8 -top-8 w-36 h-36 rounded-full bg-white/10"></div>
        <div class="flex items-center justify-between relative z-10">
            <div class="min-w-0">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-white/70 truncate">{{ $club->club_name ?? __('admin.club') }}</p>
                <h1 class="text-2xl font-black mt-0.5">{{ __('admin.nav_financials') }}</h1>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-white/15 border border-white/25 backdrop-blur grid place-items-center">
                <i class="bi bi-bank text-xl m-float"></i>
            </div>
        </div>
        <div class="flex gap-2 mt-5 relative z-10">
            <div class="flex-1 rounded-2xl bg-white/12 border border-white/20 backdrop-blur px-3 py-2.5">
                <p class="text-base font-black leading-none tabular-nums">{{ $netVal >= 0 ? '+' : '' }}{{ number_format($netVal, 0) }}<span class="text-[10px] font-bold ms-0.5">{{ $cur }}</span></p>
                <p class="text-[10px] text-white/75 mt-1 uppercase tracking-wide">{{ __('admin.dash_net') }}</p>
            </div>
            <div class="flex-1 rounded-2xl bg-white/12 border border-white/20 backdrop-blur px-3 py-2.5">
                <p class="text-base font-black leading-none tabular-nums">{{ number_format($incomeVal, 0) }}<span class="text-[10px] font-bold ms-0.5">{{ $cur }}</span></p>
                <p class="text-[10px] text-white/75 mt-1 uppercase tracking-wide">{{ __('market.stat_revenue') }}</p>
            </div>
            <div class="flex-1 rounded-2xl bg-white/12 border border-white/20 backdrop-blur px-3 py-2.5">
                <p class="text-base font-black leading-none tabular-nums">{{ number_format($expensesVal, 0) }}<span class="text-[10px] font-bold ms-0.5">{{ $cur }}</span></p>
                <p class="text-[10px] text-white/75 mt-1 uppercase tracking-wide">{{ __('admin.dash_expenses') }}</p>
            </div>
        </div>
    </header>

    <div class="px-4 pt-5 space-y-5">

    {{-- Add actions --}}
    <div class="grid grid-cols-2 gap-3">
        <button @click="openIncome()" class="m-press bg-green-50 text-green-700 border border-green-200 px-4 py-3 rounded-xl font-medium flex items-center justify-center gap-2"><i class="bi bi-plus-circle"></i> {{ __('admin.fin_income') }}</button>
        <button @click="openExpense()" class="m-press bg-primary text-white px-4 py-3 rounded-xl font-medium flex items-center justify-center gap-2"><i class="bi bi-dash-circle"></i> {{ __('admin.fin_expense') }}</button>
    </div>

    {{-- All transactions --}}
    <div id="fin-ledger">
        <h3 class="font-semibold text-foreground mb-3 flex items-center gap-2"><i class="bi bi-journal-text text-primary"></i>{{ __('admin.fin_ledger') }}</h3>

        {{-- Search --}}
        <div class="relative mb-2.5">
            <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground pointer-events-none"></i>
            <input type="text" x-model="txSearch" placeholder="{{ __('admin.fin_search_ph') }}"
                   class="w-full pl-10 pr-9 py-2.5 bg-muted rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/40">
            <button type="button" x-show="txSearch" x-cloak @click="txSearch = ''"
                    class="absolute right-2.5 top-1/2 -translate-y-1/2 w-6 h-6 rounded-full bg-gray-200 text-gray-500 flex items-center justify-center"><i class="bi bi-x text-sm"></i></button>
        </div>

        {{-- Filter pills --}}
        <div class="flex items-center gap-1.5 overflow-x-auto scrollbar-hide -mx-1 px-1 pb-1 mb-1">
            @foreach(['all' => __('admin.fin_filter_all'), 'pending' => __('admin.fin_filter_pending'), 'income' => __('admin.fin_income'), 'expense' => __('admin.fin_expense'), 'refund' => __('admin.fin_refunds')] as $key => $label)
                <button type="button" @click="txFilter = '{{ $key }}'"
                        :class="txFilter === '{{ $key }}' ? 'bg-primary text-white' : 'bg-muted text-muted-foreground'"
                        class="m-press flex-shrink-0 px-3 py-1.5 rounded-full text-xs font-medium transition-colors">
                    {{ $label }} <span class="opacity-70">{{ $counts[$key] }}</span>
                </button>
            @endforeach
        </div>

        @if($transactions->isEmpty())
            <p class="text-sm text-muted-foreground py-4 text-center">{{ __('admin.fin_no_transactions') }}</p>
        @else
            {{-- No results --}}
            <p x-show="filteredLedger.length === 0" x-cloak class="text-sm text-muted-foreground py-6 text-center">{{ __('admin.fin_no_results') }}</p>

            <div class="divide-y divide-gray-50">
                <template x-for="r in filteredLedger" :key="r.id">
                    <div class="flex items-center gap-3 py-2.5 m-press cursor-pointer" x-data="{ menu: false }"
                         @click="r.type === 'pending' ? openMemberPage(r) : openEdit(r)" role="button" tabindex="0">
                        {{-- Type icon --}}
                        <span class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0"
                              :class="r.type === 'income' ? 'bg-green-50 text-green-600' : (r.type === 'refund' ? 'bg-amber-50 text-amber-600' : (r.type === 'pending' ? 'bg-amber-50 text-amber-600' : 'bg-red-50 text-red-600'))">
                            <i class="bi" :class="r.type === 'income' ? 'bi-arrow-down-left' : (r.type === 'refund' ? 'bi-arrow-counterclockwise' : (r.type === 'pending' ? 'bi-hourglass-split' : 'bi-arrow-up-right'))"></i>
                        </span>

                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-foreground truncate" x-text="r.label"></p>
                            <p class="text-xs text-muted-foreground truncate">
                                <span x-text="r.date_label"></span><template x-if="r.pm_label"><span> · <span x-text="r.pm_label"></span></span></template>
                            </p>
                        </div>

                        <span class="text-sm font-semibold flex-shrink-0"
                              :class="r.type === 'income' ? 'text-green-600' : (r.type === 'refund' || r.type === 'pending' ? 'text-amber-600' : 'text-red-600')">
                            <span x-text="(r.type === 'income' ? '+' : (r.type === 'pending' ? '' : '-')) + r.amount_fmt"></span>
                        </span>

                        {{-- Row menu --}}
                        <div class="relative flex-shrink-0" @click.stop>
                            <button type="button" @click="menu = !menu" class="m-press w-8 h-8 rounded-full bg-muted flex items-center justify-center text-muted-foreground" aria-label="{{ __('admin.actions') }}">
                                <i class="bi bi-three-dots-vertical"></i>
                            </button>
                            <div x-show="menu" x-cloak @click.outside="menu = false"
                                 x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                                 x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
                                 class="absolute right-0 mt-2 w-48 bg-white rounded-xl shadow-xl border border-gray-100 overflow-hidden z-30">

                                {{-- Pending payment actions --}}
                                <template x-if="r.type === 'pending'">
                                    <div>
                                        <button type="button" class="w-full text-left px-4 py-3 text-sm text-green-700 hover:bg-green-50 flex items-center gap-3"
                                                @click="menu = false; markPaid(r)">
                                            <span class="w-7 h-7 rounded-lg bg-green-100 flex items-center justify-center shrink-0"><i class="bi bi-check-circle text-green-600 text-xs"></i></span>
                                            <span class="font-medium">{{ __('admin.fin_mark_paid') }}</span>
                                        </button>
                                        <button type="button" class="w-full text-left px-4 py-3 text-sm text-foreground hover:bg-muted/60 flex items-center gap-3"
                                                @click="menu = false; openMemberPage(r)">
                                            <span class="w-7 h-7 rounded-lg bg-blue-100 flex items-center justify-center shrink-0"><i class="bi bi-person text-blue-600 text-xs"></i></span>
                                            <span class="font-medium">{{ __('admin.fin_open_member') }}</span>
                                        </button>
                                    </div>
                                </template>

                                {{-- Transaction actions --}}
                                <template x-if="r.type !== 'pending'">
                                    <div>
                                        <button type="button" class="w-full text-left px-4 py-3 text-sm text-foreground hover:bg-muted/60 flex items-center gap-3"
                                                @click="menu = false; openEdit(r)">
                                            <span class="w-7 h-7 rounded-lg bg-blue-100 flex items-center justify-center shrink-0"><i class="bi bi-pencil text-blue-600 text-xs"></i></span>
                                            <span class="font-medium">{{ __('admin.fin_edit') }}</span>
                                        </button>
                                        <button type="button" x-show="r.refundable" class="w-full text-left px-4 py-3 text-sm text-amber-700 hover:bg-amber-50 flex items-center gap-3"
                                                @click="menu = false; openRefund(r)">
                                            <span class="w-7 h-7 rounded-lg bg-amber-100 flex items-center justify-center shrink-0"><i class="bi bi-arrow-counterclockwise text-amber-600 text-xs"></i></span>
                                            <span class="font-medium">{{ __('admin.fin_refund') }}</span>
                                        </button>
                                        <button type="button" class="w-full text-left px-4 py-3 text-sm text-red-600 hover:bg-red-50 flex items-center gap-3"
                                                @click="menu = false; openDelete(r.id, r.ref)">
                                            <span class="w-7 h-7 rounded-lg bg-red-100 flex items-center justify-center shrink-0"><i class="bi bi-trash text-red-600 text-xs"></i></span>
                                            <span class="font-medium">{{ __('admin.fin_delete') }}</span>
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        @endif
    </div>

    {{-- ═══════════ Transaction sheet (income / expense / edit) ═══════════ --}}
    <template x-teleport="body">
    <div x-show="txOpen" x-cloak class="fixed inset-0 z-50 overflow-y-auto">
        <div x-show="txOpen" x-transition.opacity class="fixed inset-0 bg-black/50" @click="txOpen = false"></div>
        <div class="flex min-h-full items-end justify-center sm:items-center sm:p-4">
            <div x-show="txOpen"
                 x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-full sm:translate-y-4 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 translate-y-full sm:translate-y-4 sm:scale-95"
                 class="relative bg-white rounded-t-3xl sm:rounded-2xl shadow-xl w-full sm:max-w-lg flex flex-col" style="height: 90vh; max-height: 90vh;" @click.stop>

                <div class="pt-2.5 pb-1 flex justify-center sm:hidden flex-shrink-0"><span class="w-10 h-1.5 rounded-full bg-gray-300"></span></div>
                <div class="flex items-center justify-between px-4 py-3 text-white rounded-t-3xl sm:rounded-t-2xl flex-shrink-0"
                     :class="tx.type === 'income' ? 'bg-green-600' : (tx.type === 'refund' ? 'bg-amber-600' : 'bg-primary')">
                    <h5 class="text-base font-semibold flex items-center">
                        <i class="bi mr-2" :class="txMode === 'edit' ? 'bi-pencil' : (txIsRecurring ? 'bi-arrow-repeat' : (tx.type === 'income' ? 'bi-plus-circle' : 'bi-dash-circle'))"></i>
                        <span x-text="txMode === 'edit' ? @js(__('admin.fin_edit_tx')) : (txIsRecurring ? @js(__('admin.fin_record_recurring')) : (tx.type === 'income' ? @js(__('admin.fin_record_income')) : @js(__('admin.fin_record_expense'))))"></span>
                    </h5>
                    <button type="button" @click="txOpen = false" class="text-white/90 hover:text-white text-2xl leading-none w-8 h-8 flex items-center justify-center -mr-1">&times;</button>
                </div>

                <form x-ref="txForm" method="POST" :action="txAction" class="flex-1 overflow-y-auto overscroll-contain px-4 py-4 space-y-4" @submit.prevent="submitTx()">
                    @csrf
                    <template x-if="txMode === 'edit'"><input type="hidden" name="_method" value="PUT"></template>

                    {{-- Type (edit only) --}}
                    <div x-show="txMode === 'edit'">
                        <label class="form-label">{{ __('admin.fin_type') }}</label>
                        <x-select-menu model="tx.type" name="type" :options="[
                            ['value' => 'income',  'label' => __('admin.fin_income')],
                            ['value' => 'expense', 'label' => __('admin.fin_expense')],
                            ['value' => 'refund',  'label' => __('admin.fin_refund')],
                        ]" />
                    </div>

                    {{-- Category --}}
                    <div x-show="txCats.length">
                        <label class="form-label">{{ __('admin.fin_category') }}</label>
                        <select name="category" x-model="tx.category" class="form-select">
                            <option value="">{{ __('admin.fin_select_category') }}</option>
                            <template x-for="c in txCats" :key="c.v"><option :value="c.v" x-text="c.l"></option></template>
                        </select>
                    </div>

                    {{-- Description --}}
                    <div>
                        <label class="form-label">{{ __('admin.fin_description') }} <span class="text-red-500">*</span></label>
                        <input type="text" name="description" x-model="tx.description" required placeholder="{{ __('admin.fin_description_ph') }}" class="form-control">
                    </div>

                    {{-- Recurring toggle (expense only) --}}
                    <div x-show="txMode === 'expense'" class="flex items-center justify-between rounded-xl border border-gray-200 px-3 py-2.5">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <span class="w-8 h-8 rounded-lg bg-accent flex items-center justify-center flex-shrink-0"><i class="bi bi-arrow-repeat text-primary"></i></span>
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-foreground">{{ __('admin.fin_recurring') }}</p>
                                <p class="text-xs text-muted-foreground">{{ __('admin.fin_recurring_help') }}</p>
                            </div>
                        </div>
                        <button type="button" role="switch" :aria-checked="tx.recurring.toString()" @click="tx.recurring = !tx.recurring"
                                class="relative inline-flex h-6 w-11 flex-shrink-0 items-center rounded-full transition-colors"
                                :class="tx.recurring ? 'bg-primary' : 'bg-gray-200'">
                            <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform" :class="tx.recurring ? 'translate-x-6' : 'translate-x-1'"></span>
                        </button>
                    </div>

                    {{-- Amount & date --}}
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="form-label">{{ __('admin.fin_amount') }} ({{ $cur }}) <span class="text-red-500">*</span></label>
                            <input type="number" name="amount" x-model="tx.amount" step="0.01" min="0" required placeholder="0.00" class="form-control">
                        </div>
                        <div>
                            <label class="form-label" x-text="txIsRecurring ? @js(__('admin.fin_recurring_day')) : @js(__('admin.fin_date'))"></label>
                            <input type="date" :name="txIsRecurring ? 'recurring_date' : 'transaction_date'" x-model="tx.transaction_date"
                                   :max="txIsRecurring ? null : '{{ $today }}'" required class="form-control">
                        </div>
                    </div>
                    <p x-show="txIsRecurring" x-cloak class="text-xs text-muted-foreground -mt-2 flex items-center gap-1.5">
                        <i class="bi bi-info-circle"></i> {{ __('admin.fin_recurring_day_help') }}
                    </p>

                    {{-- Payment method --}}
                    <div>
                        <label class="form-label">{{ __('admin.fin_payment_method') }}</label>
                        <input type="hidden" name="payment_method" :value="tx.payment_method">
                        <div class="grid grid-cols-4 gap-2">
                            @foreach(['cash' => 'bi-cash-stack', 'bank_transfer' => 'bi-bank', 'card' => 'bi-credit-card', 'other' => 'bi-three-dots'] as $pm => $icon)
                                <button type="button" @click="tx.payment_method = '{{ $pm }}'"
                                        :class="tx.payment_method === '{{ $pm }}' ? 'border-primary bg-accent text-primary' : 'border-gray-200 text-muted-foreground'"
                                        class="m-press flex flex-col items-center gap-1 py-2 rounded-xl border text-[11px] font-medium transition-colors">
                                    <i class="bi {{ $icon }} text-base"></i>{{ __('admin.fin_pm_' . ($pm === 'bank_transfer' ? 'bank' : $pm)) }}
                                </button>
                            @endforeach
                        </div>
                    </div>

                    {{-- Notes --}}
                    <div>
                        <label class="form-label">{{ __('admin.fin_notes') }}</label>
                        <textarea :name="txIsRecurring ? 'notes' : 'reference_number'" x-model="tx.reference_number" rows="2" placeholder="{{ __('admin.fin_notes_ph') }}" class="form-control resize-none"></textarea>
                    </div>
                </form>

                <div class="px-4 py-3 bg-gray-50 border-t flex-shrink-0 flex items-center gap-2" style="padding-bottom: max(0.75rem, env(safe-area-inset-bottom));">
                    <button type="button" @click="txOpen = false" class="px-4 py-2.5 rounded-xl border border-gray-200 text-gray-600 bg-white text-sm font-medium">{{ __('admin.cancel') }}</button>
                    <button type="button" @click="$refs.txForm.requestSubmit()" class="flex-1 btn btn-primary py-2.5">
                        <i class="bi bi-check-lg mr-1"></i><span x-text="txMode === 'edit' ? @js(__('admin.fin_save')) : (txIsRecurring ? @js(__('admin.fin_record_recurring')) : @js(__('admin.fin_record')))"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>
    </template>

    {{-- ═══════════ Delete confirm ═══════════ --}}
    <template x-teleport="body">
    <div x-show="delOpen" x-cloak class="fixed inset-0 z-50 overflow-y-auto"
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
        <div class="fixed inset-0 bg-black/50" @click="delOpen = false"></div>
        <div class="relative flex min-h-full items-center justify-center p-4 z-10">
            <div class="bg-white w-full max-w-sm relative rounded-2xl overflow-hidden shadow-xl" @click.stop>
                <div class="flex items-center justify-between border-b border-red-100 px-5 py-4">
                    <h5 class="text-destructive font-semibold flex items-center"><i class="bi bi-trash mr-2"></i>{{ __('admin.fin_delete_title') }}</h5>
                    <button type="button" class="text-muted-foreground hover:text-foreground" @click="delOpen = false"><i class="bi bi-x-lg"></i></button>
                </div>
                <form method="POST" :action="`{{ url('admin/club/' . $club->slug . '/financials') }}/${delId}`">
                    @csrf
                    @method('DELETE')
                    <div class="px-5 py-4">
                        <p class="mb-1 text-sm">{{ __('admin.fin_delete_msg') }}</p>
                        <p class="font-semibold text-sm" x-text="delRef"></p>
                        <div class="rounded-xl bg-red-50 border border-red-100 mt-3 p-3 text-xs text-red-700"><i class="bi bi-exclamation-triangle mr-1"></i>{{ __('admin.fin_delete_warn') }}</div>
                    </div>
                    <div class="border-t px-5 py-4 flex justify-end gap-2">
                        <button type="button" class="px-4 py-2 text-sm font-medium rounded-xl border border-gray-200 bg-white" @click="delOpen = false">{{ __('admin.cancel') }}</button>
                        <button type="submit" class="px-4 py-2 text-sm font-medium rounded-xl bg-destructive text-white flex items-center gap-1"><i class="bi bi-trash"></i>{{ __('admin.fin_delete') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    </template>

    {{-- ═══════════ Refund sheet ═══════════ --}}
    <template x-teleport="body">
    <div x-show="rfOpen" x-cloak class="fixed inset-0 z-50 overflow-y-auto"
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
        <div class="fixed inset-0 bg-black/50" @click="rfOpen = false"></div>
        <div class="relative flex min-h-full items-center justify-center p-4 z-10">
            <div class="bg-white w-full max-w-md relative rounded-2xl overflow-hidden shadow-xl" @click.stop>
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                    <h5 class="font-semibold flex items-center text-foreground"><i class="bi bi-arrow-counterclockwise text-amber-600 mr-2"></i>{{ __('admin.fin_refund_title') }}</h5>
                    <button type="button" class="text-muted-foreground hover:text-foreground" @click="rfOpen = false"><i class="bi bi-x-lg"></i></button>
                </div>
                <div class="px-5 py-4 space-y-4">
                    <div class="rounded-xl bg-amber-50 border border-amber-100 p-4">
                        <p class="text-xs font-semibold text-amber-700 uppercase tracking-wide">{{ __('admin.fin_refund_amount') }}</p>
                        <p class="text-2xl font-bold text-amber-700 mt-0.5" x-text="'{{ $cur }} ' + Number(rfTarget?.amount_paid || 0).toFixed(2)"></p>
                        <p class="text-xs text-amber-600 mt-1 truncate" x-text="rfTarget?.description || ''"></p>
                    </div>

                    <div>
                        <label class="form-label">{{ __('admin.fin_refund_proof') }}</label>
                        <input type="file" accept="image/*" class="hidden" x-ref="rfProofInput" @change="onRefundProof($event)">
                        <button type="button" @click="$refs.rfProofInput.click()" class="m-press w-full rounded-xl border border-dashed border-gray-300 py-3 text-sm font-medium text-muted-foreground bg-white flex items-center justify-center gap-2">
                            <i class="bi bi-upload"></i><span x-text="rfProofName || @js(__('admin.fin_refund_choose'))"></span>
                        </button>
                    </div>

                    <div class="flex items-start gap-2 p-3 bg-red-50 border border-red-100 rounded-xl text-xs text-red-700">
                        <i class="bi bi-exclamation-triangle-fill shrink-0 mt-0.5"></i><span>{{ __('admin.fin_refund_warning') }}</span>
                    </div>
                </div>
                <div class="border-t px-5 py-4 flex justify-end gap-2">
                    <button type="button" class="px-4 py-2 text-sm font-medium rounded-xl border border-gray-200 bg-white" @click="rfOpen = false" :disabled="rfBusy">{{ __('admin.cancel') }}</button>
                    <button type="button" class="px-4 py-2 text-sm font-medium rounded-xl bg-amber-600 text-white flex items-center gap-1" @click="processRefund()" :disabled="rfBusy">
                        <span x-show="!rfBusy"><i class="bi bi-arrow-counterclockwise mr-1"></i>{{ __('admin.fin_refund_confirm') }}</span>
                        <span x-show="rfBusy"><span class="inline-block animate-spin mr-1">↻</span>{{ __('admin.fin_processing') }}</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
    </template>

    </div>{{-- /content --}}
</div>
@endsection
