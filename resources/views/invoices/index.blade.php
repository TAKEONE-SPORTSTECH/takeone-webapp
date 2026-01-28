@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="mb-0">Payments & Subscriptions</h1>
            <p class="text-muted mb-0">Manage your club membership payments, subscriptions, and billing history</p>
        </div>
        <a href="{{ url()->previous() }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white">
            <div class="d-flex justify-content-between align-items-center">
                <h4 class="mb-0">All Bills</h4>
                <div class="d-flex align-items-center gap-3">
                    <form method="GET" action="{{ route('bills.index') }}" class="d-flex gap-2 align-items-center">
                        <label for="start_date" class="form-label mb-0 me-1">From:</label>
                        <input type="date" name="start_date" id="start_date" class="form-control form-control-sm" value="{{ request('start_date') }}">
                        <label for="end_date" class="form-label mb-0 me-1 ms-2">To:</label>
                        <input type="date" name="end_date" id="end_date" class="form-control form-control-sm" value="{{ request('end_date') }}">
                        <button type="submit" class="btn btn-primary btn-sm ms-2">Filter</button>
                    </form>
                    <div class="d-flex gap-2">
                        <a href="{{ route('bills.index') }}" class="btn btn-outline-secondary {{ !request('status') ? 'active' : '' }}">All</a>
                        <a href="{{ route('bills.index', ['status' => 'pending']) }}" class="btn btn-warning {{ request('status') === 'pending' ? 'active' : '' }}">Pending</a>
                        <a href="{{ route('bills.index', ['status' => 'paid']) }}" class="btn btn-success {{ request('status') === 'paid' ? 'active' : '' }}">Paid</a>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-body">
            @if($invoices->count() > 0)
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Invoice #</th>
                                <th>Student</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Due Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($invoices as $invoice)
                                <tr>
                                    <td>{{ $invoice->id }}</td>
                                    <td>{{ $invoice->student_user->full_name ?? 'N/A' }}</td>
                                    <td>${{ number_format($invoice->amount, 2) }}</td>
                                    <td>
                                        <span class="badge bg-{{ $invoice->status === 'paid' ? 'success' : 'warning' }}">
                                            {{ ucfirst($invoice->status) }}
                                        </span>
                                    </td>
                                    <td>{{ $invoice->due_date->format('M d, Y') }}</td>
                                    <td>
                                        <a href="{{ route('bills.show', $invoice->id) }}" class="btn btn-sm btn-outline-primary">View</a>
                                        @if($invoice->status === 'pending')
                                            <a href="{{ route('bills.pay', $invoice->id) }}" class="btn btn-sm btn-success">Pay Now</a>
                                        @else
                                            <a href="{{ route('bills.receipt', $invoice->id) }}" class="btn btn-sm btn-outline-secondary">Receipt</a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                {{ $invoices->links() }}
            @else
                <div class="text-center py-5">
                    <i class="fas fa-receipt fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No invoices found</h5>
                    <p class="text-muted">You don't have any invoices yet.</p>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
