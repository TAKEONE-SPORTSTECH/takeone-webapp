@extends('layouts.admin-club')

@section('club-admin-content')
<div>
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4">
        <div>
            <h2 class="h3 fw-bold mb-1">Financials</h2>
            <p class="text-muted mb-0">Track income, expenses, and transactions</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
                <i class="bi bi-dash-circle me-2"></i>Add Expense
            </button>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addIncomeModal">
                <i class="bi bi-plus-circle me-2"></i>Add Income
            </button>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <p class="text-muted small mb-1">Total Income</p>
                    <h4 class="fw-bold text-success mb-0">{{ $club->currency ?? 'BHD' }} {{ number_format($summary['total_income'] ?? 0, 2) }}</h4>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <p class="text-muted small mb-1">Total Expenses</p>
                    <h4 class="fw-bold text-danger mb-0">{{ $club->currency ?? 'BHD' }} {{ number_format($summary['total_expenses'] ?? 0, 2) }}</h4>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <p class="text-muted small mb-1">Net Profit</p>
                    <h4 class="fw-bold text-primary mb-0">{{ $club->currency ?? 'BHD' }} {{ number_format($summary['net_profit'] ?? 0, 2) }}</h4>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <p class="text-muted small mb-1">Pending Payments</p>
                    <h4 class="fw-bold text-warning mb-0">{{ $club->currency ?? 'BHD' }} {{ number_format($summary['pending'] ?? 0, 2) }}</h4>
                </div>
            </div>
        </div>
    </div>

    <!-- Transactions Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
            <h5 class="fw-semibold mb-0">Recent Transactions</h5>
            <div class="d-flex gap-2">
                <select class="form-select form-select-sm" style="width: auto;">
                    <option value="">All Types</option>
                    <option value="income">Income</option>
                    <option value="expense">Expense</option>
                    <option value="refund">Refund</option>
                </select>
                <input type="month" class="form-control form-control-sm" style="width: auto;">
            </div>
        </div>
        @if(isset($transactions) && count($transactions) > 0)
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="bg-light">
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
                                <span class="badge bg-danger">Expense</span>
                            @else
                                <span class="badge bg-warning text-dark">{{ ucfirst($transaction->type) }}</span>
                            @endif
                        </td>
                        <td class="{{ $transaction->type === 'income' ? 'text-success' : 'text-danger' }} fw-semibold">
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
        <div class="card-body text-center py-5">
            <i class="bi bi-currency-dollar text-muted" style="font-size: 4rem;"></i>
            <h5 class="mt-3 mb-2">No transactions yet</h5>
            <p class="text-muted">Transactions will appear here as they occur</p>
        </div>
        @endif
    </div>
</div>

<!-- Add Income Modal -->
<div class="modal fade" id="addIncomeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h5 class="modal-title">Add Income</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form action="{{ route('admin.club.financials.income', $club->id) }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <input type="text" name="description" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Amount ({{ $club->currency ?? 'BHD' }})</label>
                        <input type="number" name="amount" class="form-control" step="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Date</label>
                        <input type="date" name="transaction_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                    </div>
                    <button type="submit" class="btn btn-success w-100">Add Income</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Add Expense Modal -->
<div class="modal fade" id="addExpenseModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h5 class="modal-title">Add Expense</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form action="{{ route('admin.club.financials.expense', $club->id) }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <input type="text" name="description" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Amount ({{ $club->currency ?? 'BHD' }})</label>
                        <input type="number" name="amount" class="form-control" step="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Date</label>
                        <input type="date" name="transaction_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                    </div>
                    <button type="submit" class="btn btn-danger w-100">Add Expense</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
