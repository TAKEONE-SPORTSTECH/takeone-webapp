@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">My Bills</h1>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h4 class="mb-0">All Bills</h4>
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-funnel"></i> Filter
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="{{ route('bills.index') }}">All</a></li>
                    <li><a class="dropdown-item" href="{{ route('bills.index', ['status' => 'pending']) }}">Pending</a></li>
                    <li><a class="dropdown-item" href="{{ route('bills.index', ['status' => 'paid']) }}">Paid</a></li>
                    <li><a class="dropdown-item" href="{{ route('bills.index', ['status' => 'overdue']) }}">Overdue</a></li>
                </ul>
            </div>
        </div>
        <div class="card-body">
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @if($invoices->count() > 0)
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Invoice #</th>
                                <th>Student</th>
                                <th>Club</th>
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
                                    <td>{{ $invoice->student->full_name }}</td>
                                    <td>{{ $invoice->tenant->club_name }}</td>
                                    <td>${{ number_format($invoice->amount, 2) }}</td>
                                    <td>
                                        @if($invoice->status === 'paid')
                                            <span class="badge bg-success">Paid</span>
                                        @elseif($invoice->status === 'pending')
                                            <span class="badge bg-warning text-dark">Pending</span>
                                        @else
                                            <span class="badge bg-danger">Overdue</span>
                                        @endif
                                    </td>
                                    <td>{{ $invoice->due_date->format('M j, Y') }}</td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="#" class="btn btn-sm btn-outline-primary view-invoice" data-id="{{ $invoice->id }}">
                                                <i class="bi bi-eye"></i> View
                                            </a>
                                            @if($invoice->status !== 'paid')
                                                <a href="{{ route('bills.pay', $invoice->id) }}" class="btn btn-sm btn-success">
                                                    <i class="bi bi-credit-card"></i> Pay
                                                </a>
                                            @else
                                                <a href="{{ route('bills.receipt', $invoice->id) }}" class="btn btn-sm btn-outline-info" target="_blank">
                                                    <i class="bi bi-receipt"></i> Receipt
                                                </a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-center py-5">
                    <i class="bi bi-receipt" style="font-size: 3rem;"></i>
                    <h4 class="mt-3">No Bills Found</h4>
                    <p class="text-muted">There are no bills matching your criteria.</p>
                </div>
            @endif
        </div>
        @if($invoices->where('status', '!=', 'paid')->count() > 0)
            <div class="card-footer bg-white d-flex justify-content-end">
                <a href="{{ route('bills.pay-all') }}" class="btn btn-success">
                    <i class="bi bi-credit-card"></i> Pay All
                </a>
            </div>
        @endif
    </div>
</div>

<!-- Invoice Modal -->
<div class="modal fade" id="invoiceModal" tabindex="-1" aria-labelledby="invoiceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="invoiceModalLabel">Invoice Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="invoiceModalBody">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.view-invoice').forEach(function(button) {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            var invoiceId = this.getAttribute('data-id');
            fetch('/bills/' + invoiceId, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                }
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('invoiceModalBody').innerHTML = data.html;
                var modal = new bootstrap.Modal(document.getElementById('invoiceModal'));
                modal.show();
            });
        });
    });
});
</script>
@endsection
