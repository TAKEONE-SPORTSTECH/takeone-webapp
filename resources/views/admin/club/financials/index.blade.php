@extends('layouts.admin-club')

@section('club-admin-content')
<div id="financialsRoot" x-data="{
    activeTab: 'ledger',
    ledgerPage: 1,
    ledgerPerPage: 25,
    ledgerTotal: {{ $transactions->count() }},
    get ledgerTotalPages() { return Math.ceil(this.ledgerTotal / this.ledgerPerPage); },
    get ledgerStart() { return (this.ledgerPage - 1) * this.ledgerPerPage; },
    get ledgerEnd()   { return this.ledgerPage * this.ledgerPerPage; },
    showIncomeModal: false,
    showExpenseModal: false,
    showAutoExpenseModal: false,
    showExportModal: false,
    showEditModal: false,
    showDeleteModal: false,
    showTransactionDetailModal: false,
    showMonthModal: false,
    showRefundModal: false,
    monthModalLabel: '',
    monthModalTransactions: [],
    editTransaction: null,
    deleteTransactionId: null,
    deleteTransactionRef: '',
    activeTransaction: null,
    approvingPayment: false,
    refundingPayment: false,
    refundTarget: null,
    expenseType: 'expense',
    openMonthModal(label, transactions) {
        this.monthModalLabel = label;
        this.monthModalTransactions = transactions;
        this.showMonthModal = true;
    },
    openEdit(t) {
        this.editTransaction = t;
        this.showEditModal = true;
    },
    openDelete(id, ref) {
        this.deleteTransactionId = id;
        this.deleteTransactionRef = ref;
        this.showDeleteModal = true;
    },
    openTransactionDetail(id) {
        this.activeTransaction = window.transactionData?.[id] || null;
        this.showTransactionDetailModal = true;
    },
    openRefundModal(transaction) {
        this.refundTarget = transaction;
        this.showRefundModal = true;
    },
    async processRefund() {
        if (this.refundingPayment || !this.refundTarget) return;
        this.refundingPayment = true;
        try {
            const formData = new FormData();
            formData.append('_token', document.querySelector('meta[name=\'csrf-token\']')?.content);
            const refundProof = document.getElementById('hiddenInput_refundProofCropper')?.value;
            if (refundProof) formData.append('refund_proof_base64', refundProof);
            const res = await fetch(`{{ url('admin/club/' . $club->slug . '/subscriptions') }}/${this.refundTarget.subscription_id}/refund`, {
                method: 'POST', body: formData, headers: { 'Accept': 'application/json' }
            });
            const data = await res.json();
            if (data.success) {
                this.showRefundModal = false;
                location.reload();
            } else {
                alert(data.message || 'Failed to process refund.');
            }
        } catch(e) {
            alert('An error occurred.');
        } finally {
            this.refundingPayment = false;
        }
    },
    async approvePayment(subscriptionId) {
        if (this.approvingPayment) return;
        this.approvingPayment = true;
        try {
            const formData = new FormData();
            formData.append('_token', document.querySelector('meta[name=\'csrf-token\']')?.content);
            const adminProof = document.getElementById('hiddenInput_adminProofCropper')?.value;
            if (adminProof) formData.append('admin_proof_base64', adminProof);
            const res = await fetch(`{{ url('admin/club/' . $club->slug . '/subscriptions') }}/${subscriptionId}/approve-payment`, {
                method: 'POST', body: formData, headers: { 'Accept': 'application/json' }
            });
            const data = await res.json();
            if (data.success) {
                this.showTransactionDetailModal = false;
                location.reload();
            } else {
                alert(data.message || 'Failed to approve payment.');
            }
        } catch(e) {
            alert('An error occurred.');
        } finally {
            this.approvingPayment = false;
        }
    }
}">
    <!-- Header -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-6">
        <div>
            <h2 class="text-3xl font-bold mb-1">Financial Management</h2>
            <p class="text-muted-foreground mb-0">Track income, expenses, and generate reports</p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <button type="button" class="btn btn-outline-secondary" @click="showExportModal = true">
                <i class="bi bi-download mr-2"></i>Export CSV
            </button>
            <button type="button" class="btn btn-outline-primary" @click="showIncomeModal = true">
                <i class="bi bi-plus-circle mr-2"></i>Manual Income
            </button>
            <button type="button" class="btn btn-outline-primary" @click="showAutoExpenseModal = true">
                <i class="bi bi-calendar-check mr-2"></i>Auto Expense
            </button>
            <button type="button" class="btn btn-primary" @click="showExpenseModal = true">
                <i class="bi bi-dash-circle mr-2"></i>Record Expense
            </button>
        </div>
    </div>

    <!-- Success/Error Messages -->
    @if(session('success'))
    <div class="alert alert-success relative mb-4" role="alert" x-data="{ show: true }" x-show="show">
        <i class="bi bi-check-circle mr-2"></i>{{ session('success') }}
        <button type="button" class="absolute top-3 right-3 text-green-600 hover:text-green-800" @click="show = false">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>
    @endif

    @if($errors->any())
    <div class="alert alert-danger relative mb-4" role="alert" x-data="{ show: true }" x-show="show">
        <i class="bi bi-exclamation-triangle mr-2"></i>
        <ul class="mb-0">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" class="absolute top-3 right-3 text-red-600 hover:text-red-800" @click="show = false">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>
    @endif

    <!-- Summary Cards -->
    @php
        $currency = $club->currency ?? 'BHD';
        $netIncome = ($summary['net_profit'] ?? 0);
    @endphp
    <div class="tf-stat-grid">
        <!-- Total Income -->
        <div class="card border-0 shadow-sm">
            <div class="card-body pb-2">
                <p class="text-sm font-medium text-muted-foreground mb-1">Total Income</p>
                <div class="text-lg font-bold text-green-600 flex items-center gap-2 break-words">
                    <i class="bi bi-graph-up-arrow flex-shrink-0"></i>
                    {{ $currency }} {{ number_format($summary['total_income'] ?? 0, 2) }}
                </div>
            </div>
        </div>

        <!-- Total Expenses -->
        <div class="card border-0 shadow-sm">
            <div class="card-body pb-2">
                <p class="text-sm font-medium text-muted-foreground mb-1">Total Expenses</p>
                <div class="text-lg font-bold text-red-600 flex items-center gap-2 break-words">
                    <i class="bi bi-graph-down-arrow flex-shrink-0"></i>
                    {{ $currency }} {{ number_format($summary['total_expenses'] ?? 0, 2) }}
                </div>
            </div>
        </div>

        <!-- Refunds -->
        <div class="card border-0 shadow-sm">
            <div class="card-body pb-2">
                <p class="text-sm font-medium text-muted-foreground mb-1">Refunds</p>
                <div class="text-lg font-bold text-orange-600 break-words">
                    {{ $currency }} {{ number_format($summary['refunds'] ?? 0, 2) }}
                </div>
            </div>
        </div>

        <!-- Net Income -->
        <div class="card border-0 shadow-sm">
            <div class="card-body pb-2">
                <p class="text-sm font-medium text-muted-foreground mb-1">Net Income</p>
                <div class="text-lg font-bold break-words {{ $netIncome >= 0 ? 'text-green-600' : 'text-red-600' }}">
                    {{ $currency }} {{ number_format($netIncome, 2) }}
                </div>
            </div>
        </div>

        <!-- Cash (paid income - paid expenses) -->
        @php
            $cashIn = $transactions->where('type', 'income')->where('payment_method', '!=', null)->sum('amount');
            $cashOut = $transactions->where('type', 'expense')->sum('amount') + $transactions->where('type', 'refund')->sum('amount');
            $cash = $cashIn - $cashOut;
        @endphp
        <div class="card border-0 shadow-sm">
            <div class="card-body pb-2">
                <p class="text-sm font-medium text-muted-foreground mb-1">Cash</p>
                <div class="text-lg font-bold text-emerald-600 break-words">
                    {{ $currency }} {{ number_format($cash, 2) }}
                </div>
                <p class="text-xs text-muted-foreground mt-1">Paid transactions</p>
            </div>
        </div>

        <!-- Cash to Collect -->
        @php
            $cashToCollect = $summary['pending'] ?? 0;
        @endphp
        <div class="card border-0 shadow-sm">
            <div class="card-body pb-2">
                <p class="text-sm font-medium text-muted-foreground mb-1">Cash to Collect</p>
                <div class="text-lg font-bold text-amber-600 break-words">
                    {{ $currency }} {{ number_format($cashToCollect, 2) }}
                </div>
                <p class="text-xs text-muted-foreground mt-1">Pending payments</p>
            </div>
        </div>
    </div>

    <!-- Financial Overview Chart -->
    @if(count($monthlyData) > 0)
    <div class="card border-0 shadow-sm mb-6">
        <div class="card-header bg-white border-0">
            <h5 class="card-title mb-0 font-semibold">Financial Overview (Last 12 Months)</h5>
            <p class="text-sm text-muted-foreground mb-0">Monthly income, expenses, and profit trends</p>
        </div>
        <div class="card-body">
            <canvas id="financialChart" height="100"></canvas>
        </div>
    </div>
    @endif

    <!-- Tabs -->
    <div class="border-b mb-4">
        <nav class="flex gap-1" role="tablist">
            <button type="button"
                    class="tab-btn"
                    :class="{ 'active': activeTab === 'ledger' }"
                    @click="activeTab = 'ledger'">
                <i class="bi bi-journal-text mr-2"></i>Transaction Ledger
            </button>
            <button type="button"
                    class="tab-btn"
                    :class="{ 'active': activeTab === 'expenses' }"
                    @click="activeTab = 'expenses'">
                <i class="bi bi-pie-chart mr-2"></i>Expenses
            </button>
            <button type="button"
                    class="tab-btn"
                    :class="{ 'active': activeTab === 'reports' }"
                    @click="activeTab = 'reports'">
                <i class="bi bi-file-earmark-bar-graph mr-2"></i>Reports
            </button>
        </nav>
    </div>

    <!-- Tab: Transaction Ledger -->
    <div x-show="activeTab === 'ledger'" x-transition>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-2">
                    <div>
                        <h5 class="font-semibold mb-0">All Transactions</h5>
                        <p class="text-sm text-muted-foreground mb-0">Complete record of all financial transactions</p>
                    </div>
                </div>
            </div>
            @if($transactions->count() > 0)
            <div class="overflow-x-auto">
                <table class="table table-hover mb-0">
                    <thead class="bg-muted/50">
                        <tr>
                            <th>Date</th>
                            <th>Ref #</th>
                            <th>Type</th>
                            <th>Description</th>
                            <th>Category</th>
                            <th class="text-right">Amount</th>
                            <th>Payment</th>
                            <th>Status</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($transactions as $transaction)
                        @php
                            $subPayStatus = $transaction->subscription?->payment_status ?? null;
                            $isClickable  = $transaction->type === 'income' && $transaction->subscription_id && $subPayStatus !== null;
                        @endphp
                        <tr class="{{ $isClickable ? 'cursor-pointer hover:bg-primary/5' : '' }}"
                            data-year-month="{{ $transaction->transaction_date ? $transaction->transaction_date->format('Y-m') : '' }}"
                            x-show="{{ $loop->index }} >= ledgerStart && {{ $loop->index }} < ledgerEnd"
                            {{ $isClickable ? '@click=openTransactionDetail(' . $transaction->id . ')' : '' }}>
                            <td class="whitespace-nowrap">{{ $transaction->transaction_date ? $transaction->transaction_date->format('M d, Y') : 'N/A' }}</td>
                            <td class="font-mono text-sm">{{ $transaction->reference_number ?? '-' }}</td>
                            <td>
                                @if($transaction->type === 'income')
                                    <span class="badge bg-green-100 text-green-700">Income</span>
                                @elseif($transaction->type === 'expense')
                                    <span class="badge bg-red-100 text-red-700">Expense</span>
                                @else
                                    <span class="badge bg-orange-100 text-orange-700">{{ ucfirst($transaction->type) }}</span>
                                @endif
                            </td>
                            <td>{{ $transaction->description ?? 'N/A' }}</td>
                            <td class="text-sm text-muted-foreground">{{ $transaction->category ?? '-' }}</td>
                            <td class="text-right font-semibold whitespace-nowrap {{ $transaction->type === 'income' ? 'text-green-600' : 'text-red-600' }}">
                                {{ $transaction->type === 'income' ? '+' : '-' }}{{ $currency }} {{ number_format($transaction->amount, 2) }}
                            </td>
                            <td>
                                @if($transaction->payment_method === 'cash')
                                    <span class="badge bg-emerald-50 text-emerald-700 text-xs"><i class="bi bi-cash-stack mr-1"></i>Cash</span>
                                @elseif($transaction->payment_method === 'bank_transfer')
                                    <span class="badge bg-blue-50 text-blue-700 text-xs"><i class="bi bi-bank mr-1"></i>Bank</span>
                                @elseif($transaction->payment_method === 'card')
                                    <span class="badge bg-purple-50 text-purple-700 text-xs"><i class="bi bi-credit-card mr-1"></i>Card</span>
                                @elseif($transaction->payment_method === 'online')
                                    <span class="badge bg-cyan-50 text-cyan-700 text-xs"><i class="bi bi-globe mr-1"></i>Online</span>
                                @elseif($transaction->payment_method)
                                    <span class="badge bg-gray-100 text-gray-700 text-xs"><i class="bi bi-three-dots mr-1"></i>Other</span>
                                @else
                                    <span class="text-muted-foreground">-</span>
                                @endif
                            </td>
                            <td>
                                @if($subPayStatus === 'pending_approval')
                                    <span class="badge bg-blue-100 text-blue-700 flex items-center gap-1 w-fit"><i class="bi bi-hourglass-split text-xs"></i>Pending</span>
                                @elseif($subPayStatus === 'paid')
                                    <span class="badge bg-green-100 text-green-700 flex items-center gap-1 w-fit"><i class="bi bi-check-circle text-xs"></i>Paid</span>
                                @elseif($subPayStatus === 'unpaid')
                                    <span class="badge bg-amber-100 text-amber-700 flex items-center gap-1 w-fit"><i class="bi bi-clock text-xs"></i>Unpaid</span>
                                @else
                                    <span class="text-muted-foreground text-xs">—</span>
                                @endif
                            </td>
                            <td>
                                <div class="flex gap-1 justify-center">
                                    <button type="button"
                                            class="btn btn-sm p-1 text-muted-foreground hover:text-primary"
                                            title="Edit transaction"
                                            @click="openEdit({
                                                id: {{ $transaction->id }},
                                                description: '{{ addslashes($transaction->description) }}',
                                                amount: {{ $transaction->amount }},
                                                transaction_date: '{{ $transaction->transaction_date ? $transaction->transaction_date->format('Y-m-d') : '' }}',
                                                type: '{{ $transaction->type }}',
                                                category: '{{ addslashes($transaction->category ?? '') }}',
                                                payment_method: '{{ $transaction->payment_method ?? 'cash' }}',
                                                reference_number: '{{ addslashes($transaction->reference_number ?? '') }}'
                                            })">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button type="button"
                                            class="btn btn-sm p-1 text-muted-foreground hover:text-destructive"
                                            title="Delete transaction"
                                            @click="openDelete({{ $transaction->id }}, '{{ addslashes($transaction->reference_number ?? $transaction->description) }}')">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="flex items-center justify-between px-4 py-3 border-t" x-show="ledgerTotalPages > 1">
                <span class="text-sm text-muted-foreground">
                    Showing <span x-text="ledgerStart + 1"></span>–<span x-text="Math.min(ledgerEnd, ledgerTotal)"></span> of <span x-text="ledgerTotal"></span>
                </span>
                <div class="flex items-center gap-2">
                    <button @click="ledgerPage--" :disabled="ledgerPage === 1"
                            class="btn btn-sm btn-outline-secondary"
                            :class="{ 'opacity-50 pointer-events-none': ledgerPage === 1 }">
                        <i class="bi bi-chevron-left"></i>
                    </button>
                    <span class="text-sm text-muted-foreground" x-text="ledgerPage + ' / ' + ledgerTotalPages"></span>
                    <button @click="ledgerPage++" :disabled="ledgerPage >= ledgerTotalPages"
                            class="btn btn-sm btn-outline-secondary"
                            :class="{ 'opacity-50 pointer-events-none': ledgerPage >= ledgerTotalPages }">
                        <i class="bi bi-chevron-right"></i>
                    </button>
                </div>
            </div>
            @else
            <div class="card-body text-center py-12 text-muted-foreground">
                <i class="bi bi-currency-dollar text-6xl mb-3 block"></i>
                <h5 class="mb-2">No transactions found</h5>
                <p>Transactions will appear here as they are recorded</p>
            </div>
            @endif
        </div>
    </div>

    <!-- Tab: Expenses -->
    <div x-show="activeTab === 'expenses'" x-transition>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0">
                <h5 class="font-semibold mb-0">Expense Breakdown</h5>
                <p class="text-sm text-muted-foreground mb-0">Categorized expenses for accounting</p>
            </div>
            <div class="card-body">
                @if(count($expenseCategories) > 0)
                <div class="space-y-4">
                    @foreach($expenseCategories as $catData)
                    <div class="border rounded-lg p-4">
                        <h3 class="font-semibold capitalize mb-2">{{ $catData['category'] }}</h3>
                        <div class="space-y-2">
                            @foreach($catData['items'] as $item)
                            <div class="flex justify-between text-sm">
                                <span>
                                    {{ $item->description }}
                                    @if($item->transaction_date)
                                        <span class="text-muted-foreground ml-2">{{ $item->transaction_date->format('M d, Y') }}</span>
                                    @endif
                                </span>
                                <span class="font-medium">{{ $currency }} {{ number_format($item->amount, 2) }}</span>
                            </div>
                            @endforeach
                        </div>
                        <div class="mt-2 pt-2 border-t flex justify-between font-bold">
                            <span>Total</span>
                            <span>{{ $currency }} {{ number_format($catData['total'], 2) }}</span>
                        </div>
                    </div>
                    @endforeach
                </div>
                @else
                <div class="text-center py-12 text-muted-foreground">
                    <i class="bi bi-receipt text-6xl mb-3 block"></i>
                    <h5 class="mb-2">No expenses recorded</h5>
                    <p>Expense categories will appear here when expenses are added</p>
                </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Tab: Reports -->
    <div x-show="activeTab === 'reports'" x-transition>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0">
                <h5 class="font-semibold mb-0">Financial Reports</h5>
                <p class="text-sm text-muted-foreground mb-0">Summary reports for accounting and tax compliance</p>
            </div>
            <div class="card-body space-y-4">
                <!-- Summary -->
                <div class="border rounded-lg p-4">
                    <h3 class="font-semibold mb-2">Summary</h3>
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <span class="text-muted-foreground">Total Transactions:</span>
                            <p class="font-medium">{{ $transactions->count() }}</p>
                        </div>
                        <div>
                            <span class="text-muted-foreground">Income Transactions:</span>
                            <p class="font-medium">{{ $transactions->where('type', 'income')->count() }}</p>
                        </div>
                        <div>
                            <span class="text-muted-foreground">Expense Transactions:</span>
                            <p class="font-medium">{{ $transactions->where('type', 'expense')->count() }}</p>
                        </div>
                        <div>
                            <span class="text-muted-foreground">Refund Transactions:</span>
                            <p class="font-medium">{{ $transactions->where('type', 'refund')->count() }}</p>
                        </div>
                    </div>
                </div>

                <!-- Income vs Expenses -->
                <div class="border rounded-lg p-4">
                    <h3 class="font-semibold mb-4">Income vs Expenses</h3>
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span>Gross Income</span>
                            <span class="font-medium text-green-600">{{ $currency }} {{ number_format($summary['total_income'] ?? 0, 2) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Total Expenses</span>
                            <span class="font-medium text-red-600">-{{ $currency }} {{ number_format($summary['total_expenses'] ?? 0, 2) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Refunds</span>
                            <span class="font-medium text-orange-600">-{{ $currency }} {{ number_format($summary['refunds'] ?? 0, 2) }}</span>
                        </div>
                        <div class="pt-2 border-t flex justify-between font-bold text-lg">
                            <span>{{ $netIncome >= 0 ? 'Net Profit' : 'Net Loss' }}</span>
                            <span class="{{ $netIncome >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                {{ $currency }} {{ number_format(abs($netIncome), 2) }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Payment Methods Breakdown -->
                @php
                    $paymentMethods = $transactions->groupBy('payment_method')->map(fn($items) => $items->sum('amount'));
                @endphp
                @if($paymentMethods->count() > 0)
                <div class="border rounded-lg p-4">
                    <h3 class="font-semibold mb-4">Payment Methods</h3>
                    <div class="space-y-2">
                        @foreach($paymentMethods as $method => $total)
                        <div class="flex justify-between text-sm">
                            <span>{{ ucfirst(str_replace('_', ' ', $method ?? 'Unknown')) }}</span>
                            <span class="font-medium">{{ $currency }} {{ number_format($total, 2) }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Month Transactions Modal --}}
    <div x-show="showMonthModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto"
         x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
        <div class="fixed inset-0 bg-black/50" @click="showMonthModal = false"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="modal-content border-0 shadow-lg w-full max-w-3xl relative rounded-lg overflow-hidden" @click.stop>
                <div class="modal-header border-b px-6 py-4">
                    <div>
                        <h5 class="modal-title font-bold flex items-center gap-2">
                            <i class="bi bi-calendar3 text-primary"></i>
                            Transactions — <span x-text="monthModalLabel"></span>
                        </h5>
                        <p class="text-sm text-muted-foreground mb-0" x-text="monthModalTransactions.length + ' transaction(s) found'"></p>
                    </div>
                    <button type="button" class="text-muted-foreground hover:text-foreground" @click="showMonthModal = false">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <div class="modal-body px-6 py-4">
                    <template x-if="monthModalTransactions.length === 0">
                        <div class="text-center py-10 text-muted-foreground">
                            <i class="bi bi-receipt text-5xl mb-3 block"></i>
                            <p>No transactions recorded for this month.</p>
                        </div>
                    </template>
                    <template x-if="monthModalTransactions.length > 0">
                        <div class="overflow-x-auto">
                            <table class="table table-hover mb-0">
                                <thead class="bg-muted/50">
                                    <tr>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>Description</th>
                                        <th>Category</th>
                                        <th class="text-right">Amount</th>
                                        <th>Payment</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="t in monthModalTransactions" :key="t.id">
                                        <tr>
                                            <td class="whitespace-nowrap text-sm" x-text="t.transaction_date"></td>
                                            <td>
                                                <span x-show="t.type === 'income'" class="badge bg-green-100 text-green-700">Income</span>
                                                <span x-show="t.type === 'expense'" class="badge bg-red-100 text-red-700">Expense</span>
                                                <span x-show="t.type === 'refund'" class="badge bg-orange-100 text-orange-700">Refund</span>
                                            </td>
                                            <td>
                                                <template x-if="t.member_name">
                                                    <div class="flex items-center gap-2">
                                                        <template x-if="t.member_avatar">
                                                            <img :src="t.member_avatar" class="w-6 h-6 rounded-full object-cover flex-shrink-0">
                                                        </template>
                                                        <template x-if="!t.member_avatar">
                                                            <div class="w-6 h-6 rounded-full bg-primary/10 flex items-center justify-center flex-shrink-0">
                                                                <i class="bi bi-person text-xs text-primary"></i>
                                                            </div>
                                                        </template>
                                                        <div>
                                                            <div class="text-xs text-muted-foreground leading-tight" x-text="t.member_name"></div>
                                                            <div x-text="t.description || '—'"></div>
                                                        </div>
                                                    </div>
                                                </template>
                                                <template x-if="!t.member_name">
                                                    <span x-text="t.description || '—'"></span>
                                                </template>
                                            </td>
                                            <td class="text-sm text-muted-foreground" x-text="t.category || '—'"></td>
                                            <td class="text-right font-semibold whitespace-nowrap"
                                                :class="t.type === 'income' ? 'text-green-600' : 'text-red-600'"
                                                x-text="(t.type === 'income' ? '+' : '-') + ' {{ $currency }} ' + parseFloat(t.amount).toFixed(2)">
                                            </td>
                                            <td class="text-sm text-muted-foreground" x-text="t.payment_method ? t.payment_method.replace('_', ' ') : '—'"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </template>
                </div>
                <div class="modal-footer px-6 py-3 border-t flex justify-end">
                    <button type="button" class="btn btn-outline-secondary" @click="showMonthModal = false">Close</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Modals --}}
    <x-income-modal :club="$club" :currency="$currency" />
    <x-expense-modal :club="$club" />
    @include('admin.club.financials.partials.auto-expense-modal')
    @include('admin.club.financials.partials.export-modal')
    @include('admin.club.financials.partials.edit-modal')
    @include('admin.club.financials.partials.delete-modal')
    @include('admin.club.financials.partials.transaction-detail-modal')
    @include('admin.club.financials.partials.refund-modal')
</div>

{{-- Styles moved to app.css (Phase 6) --}}

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
window.transactionData = {
    @foreach($transactions as $transaction)
    {{ $transaction->id }}: {
        id: {{ $transaction->id }},
        type: @json($transaction->type),
        description: @json($transaction->description ?? ''),
        amount: {{ $transaction->amount }},
        transaction_date: @json($transaction->transaction_date ? $transaction->transaction_date->format('M d, Y') : ''),
        category: @json($transaction->category ?? ''),
        payment_method: @json($transaction->payment_method ?? ''),
        subscription_id: {{ $transaction->subscription_id ?? 'null' }},
        amount_paid: {{ $transaction->subscription?->amount_paid ?? 'null' }},
        payment_status: @json($transaction->subscription?->payment_status ?? ''),
        proof_of_payment: @json($transaction->subscription?->proof_of_payment ? route('admin.club.subscriptions.payment-proof', ['club' => $club, 'subscription' => $transaction->subscription_id]) : ''),
        refund_proof: @json($transaction->subscription?->refund_proof ? route('admin.club.subscriptions.refund-proof', ['club' => $club, 'subscription' => $transaction->subscription_id]) : ''),
        member_name: @json($transaction->subscription?->user?->full_name ?? $transaction->subscription?->user?->name ?? ''),
        member_avatar: @json($transaction->subscription?->user?->profile_picture ? asset('storage/' . $transaction->subscription->user->profile_picture) : ''),
    },
    @endforeach
};

document.addEventListener('DOMContentLoaded', function() {
    // Financial Overview Line Chart
    const chartEl = document.getElementById('financialChart');
    if (chartEl) {
        const monthlyData = @json($monthlyData);
        new Chart(chartEl, {
            type: 'line',
            data: {
                labels: monthlyData.map(d => d.month),
                datasets: [
                    {
                        label: 'Income',
                        data: monthlyData.map(d => d.income),
                        borderColor: '#22c55e',
                        backgroundColor: 'rgba(34, 197, 94, 0.1)',
                        borderWidth: 2,
                        tension: 0.3,
                        fill: false
                    },
                    {
                        label: 'Expenses',
                        data: monthlyData.map(d => d.expenses),
                        borderColor: '#ef4444',
                        backgroundColor: 'rgba(239, 68, 68, 0.1)',
                        borderWidth: 2,
                        tension: 0.3,
                        fill: false
                    },
                    {
                        label: 'Refunds',
                        data: monthlyData.map(d => d.refunds),
                        borderColor: '#f97316',
                        backgroundColor: 'rgba(249, 115, 22, 0.1)',
                        borderWidth: 2,
                        tension: 0.3,
                        fill: false
                    },
                    {
                        label: 'Profit',
                        data: monthlyData.map(d => d.profit),
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        borderWidth: 2,
                        tension: 0.3,
                        fill: true
                    },
                    {
                        label: 'Cash to Collect',
                        data: monthlyData.map(d => d.cash_to_collect),
                        borderColor: '#f59e0b',
                        backgroundColor: 'rgba(245, 158, 11, 0.1)',
                        borderWidth: 2,
                        tension: 0.3,
                        fill: false,
                        borderDash: [5, 5]
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                onHover: (event, elements) => {
                    event.native.target.style.cursor = elements.length ? 'pointer' : 'default';
                },
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            padding: 20
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': {{ $currency }} ' + context.parsed.y.toFixed(2);
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '{{ $currency }} ' + value.toLocaleString();
                            }
                        }
                    }
                },
                onClick: function(event, elements, chart) {
                    const points = chart.getElementsAtEventForMode(event.native, 'index', { intersect: false }, true);
                    if (points.length === 0) return;
                    const index = points[0].index;
                    const { year_month } = monthlyData[index];
                    // Match by year_month (YYYY-MM) against transaction_date string (e.g. "Mar 19, 2026")
                    const [year, mon] = year_month.split('-');
                    const monthNames = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
                    const shortMonth = monthNames[parseInt(mon) - 1];
                    const txns = Object.values(window.transactionData).filter(t => {
                        if (!t.transaction_date) return false;
                        return t.transaction_date.startsWith(shortMonth) && t.transaction_date.endsWith(year);
                    });
                    const alpineRoot = document.getElementById('financialsRoot');
                    Alpine.$data(alpineRoot).openMonthModal(shortMonth + ' ' + year, txns);
                }
            }
        });
    }
});


// Export CSV
function exportCSV() {
    @php
        $csvData = $transactions->map(function($t) {
            return [
                'date' => $t->transaction_date ? $t->transaction_date->format('Y-m-d') : '',
                'type' => $t->type,
                'description' => $t->description,
                'category' => $t->category ?? '',
                'amount' => $t->amount,
                'payment_method' => $t->payment_method ?? '',
                'reference_number' => $t->reference_number ?? '',
            ];
        })->values();
    @endphp
    const transactions = @json($csvData);

    if (!transactions.length) {
        alert('No transactions to export');
        return;
    }

    const headers = ['Date', 'Type', 'Description', 'Category', 'Amount', 'Payment Method', 'Reference #'];
    const rows = transactions.map(t => [
        t.date, t.type, '"' + (t.description || '').replace(/"/g, '""') + '"',
        '"' + (t.category || '').replace(/"/g, '""') + '"',
        t.amount, t.payment_method, t.reference_number
    ]);

    const csv = [headers.join(','), ...rows.map(r => r.join(','))].join('\n');
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    const fileNameInput = document.getElementById('exportFileName');
    a.download = (fileNameInput ? fileNameInput.value : 'transactions-{{ now()->format("Y-m-d") }}') + '.csv';
    a.click();
    URL.revokeObjectURL(url);
}
</script>
@endpush
@endsection
