@extends('layouts.admin-club')

@section('club-admin-content')
<div x-data="{ showIncomeModal: false, showExpenseModal: false }">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3 mb-4">
        <div>
            <h2 class="text-2xl font-bold mb-1">Financials</h2>
            <p class="text-muted-foreground mb-0">Track income, expenses, and transactions</p>
        </div>
        <div class="flex gap-2">
            <button class="btn btn-outline-primary" @click="showExpenseModal = true">
                <i class="bi bi-dash-circle mr-2"></i>Add Expense
            </button>
            <button class="btn btn-primary" @click="showIncomeModal = true">
                <i class="bi bi-plus-circle mr-2"></i>Add Income
            </button>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
        <div class="card border-0 shadow-sm h-full">
            <div class="card-body">
                <p class="text-muted-foreground text-sm mb-1">Total Income</p>
                <h4 class="font-bold text-success mb-0">{{ $club->currency ?? 'BHD' }} {{ number_format($summary['total_income'] ?? 0, 2) }}</h4>
            </div>
        </div>
        <div class="card border-0 shadow-sm h-full">
            <div class="card-body">
                <p class="text-muted-foreground text-sm mb-1">Total Expenses</p>
                <h4 class="font-bold text-destructive mb-0">{{ $club->currency ?? 'BHD' }} {{ number_format($summary['total_expenses'] ?? 0, 2) }}</h4>
            </div>
        </div>
        <div class="card border-0 shadow-sm h-full">
            <div class="card-body">
                <p class="text-muted-foreground text-sm mb-1">Net Profit</p>
                <h4 class="font-bold text-primary mb-0">{{ $club->currency ?? 'BHD' }} {{ number_format($summary['net_profit'] ?? 0, 2) }}</h4>
            </div>
        </div>
        <div class="card border-0 shadow-sm h-full">
            <div class="card-body">
                <p class="text-muted-foreground text-sm mb-1">Pending Payments</p>
                <h4 class="font-bold text-warning mb-0">{{ $club->currency ?? 'BHD' }} {{ number_format($summary['pending'] ?? 0, 2) }}</h4>
            </div>
        </div>
    </div>

    <!-- Transactions Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0 flex justify-between items-center">
            <h5 class="font-semibold mb-0">Recent Transactions</h5>
            <div class="flex gap-2">
                <select class="form-select form-select-sm w-auto">
                    <option value="">All Types</option>
                    <option value="income">Income</option>
                    <option value="expense">Expense</option>
                    <option value="refund">Refund</option>
                </select>
                <input type="month" class="form-control form-control-sm w-auto">
            </div>
        </div>
        @if(isset($transactions) && count($transactions) > 0)
        <div class="overflow-x-auto">
            <table class="table table-hover mb-0">
                <thead class="bg-muted/30">
                    <tr>
                        <th>Date</th>
                        <th>Description</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($transactions as $transaction)
                    <tr>
                        <td>{{ $transaction->transaction_date ? $transaction->transaction_date->format('M d, Y') : 'N/A' }}</td>
                        <td>{{ $transaction->description ?? 'N/A' }}</td>
                        <td>
                            @if($transaction->type === 'income')
                                <span class="badge bg-success">Income</span>
                            @elseif($transaction->type === 'expense')
                                <span class="badge bg-destructive">Expense</span>
                            @else
                                <span class="badge bg-warning text-dark">{{ ucfirst($transaction->type) }}</span>
                            @endif
                        </td>
                        <td class="{{ $transaction->type === 'income' ? 'text-success' : 'text-destructive' }} font-semibold">
                            {{ $transaction->type === 'income' ? '+' : '-' }}{{ $club->currency ?? 'BHD' }} {{ number_format($transaction->amount, 2) }}
                        </td>
                        <td>
                            @if($transaction->status === 'paid')
                                <span class="badge bg-success">Paid</span>
                            @elseif($transaction->status === 'pending')
                                <span class="badge bg-warning text-dark">Pending</span>
                            @else
                                <span class="badge bg-secondary">{{ ucfirst($transaction->status) }}</span>
                            @endif
                        </td>
                        <td>
                            <button class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-eye"></i>
                            </button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div class="card-body text-center py-12">
            <i class="bi bi-currency-dollar text-muted-foreground text-6xl"></i>
            <h5 class="mt-3 mb-2">No transactions yet</h5>
            <p class="text-muted-foreground">Transactions will appear here as they occur</p>
        </div>
        @endif
    </div>

    <!-- Add Income Modal -->
    <div x-show="showIncomeModal"
         x-cloak
         class="fixed inset-0 z-50 overflow-y-auto"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
        <div class="fixed inset-0 bg-black/50" @click="showIncomeModal = false"></div>

        <div class="flex min-h-full items-center justify-center p-4">
            <div class="modal-content border-0 shadow-lg w-full max-w-md relative" @click.stop>
                <div class="modal-header border-0 px-6 py-4">
                    <h5 class="modal-title font-bold">Add Income</h5>
                    <button type="button" class="btn-close" @click="showIncomeModal = false"></button>
                </div>
                <div class="modal-body px-6 pb-6">
                    <form action="{{ route('admin.club.financials.income', $club->id) }}" method="POST">
                        @csrf
                        <div class="mb-4">
                            <label class="form-label">Description</label>
                            <input type="text" name="description" class="form-control" required>
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Amount ({{ $club->currency ?? 'BHD' }})</label>
                            <input type="number" name="amount" class="form-control" step="0.01" required>
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Date</label>
                            <input type="date" name="transaction_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                        </div>
                        <button type="submit" class="btn btn-success w-full">Add Income</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Expense Modal -->
    <div x-show="showExpenseModal"
         x-cloak
         class="fixed inset-0 z-50 overflow-y-auto"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
        <div class="fixed inset-0 bg-black/50" @click="showExpenseModal = false"></div>

        <div class="flex min-h-full items-center justify-center p-4">
            <div class="modal-content border-0 shadow-lg w-full max-w-md relative" @click.stop>
                <div class="modal-header border-0 px-6 py-4">
                    <h5 class="modal-title font-bold">Add Expense</h5>
                    <button type="button" class="btn-close" @click="showExpenseModal = false"></button>
                </div>
                <div class="modal-body px-6 pb-6">
                    <form action="{{ route('admin.club.financials.expense', $club->id) }}" method="POST">
                        @csrf
                        <div class="mb-4">
                            <label class="form-label">Description</label>
                            <input type="text" name="description" class="form-control" required>
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Amount ({{ $club->currency ?? 'BHD' }})</label>
                            <input type="number" name="amount" class="form-control" step="0.01" required>
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Date</label>
                            <input type="date" name="transaction_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                        </div>
                        <button type="submit" class="btn btn-danger w-full">Add Expense</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
