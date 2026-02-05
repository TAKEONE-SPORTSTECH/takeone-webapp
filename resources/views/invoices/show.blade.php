@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-4">
    <div class="flex justify-center">
        <div class="w-full md:w-2/3">
            <div class="card shadow-sm">
                <div class="card-header bg-card flex justify-between items-center">
                    <h4 class="mb-0">Invoice #{{ $invoice->id }}</h4>
                    <a href="{{ route('bills.index') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-left"></i> Back to Bills
                    </a>
                </div>
                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <h5>Billed To</h5>
                            <p class="mb-1">{{ Auth::user()->full_name }}</p>
                            <p class="mb-1">{{ Auth::user()->email }}</p>
                            @if(Auth::user()->mobile)
                                <p class="mb-0">{{ Auth::user()->mobile }}</p>
                            @endif
                        </div>
                        <div class="md:text-right">
                            <h5>Invoice Details</h5>
                            <p class="mb-1">Invoice #: {{ $invoice->id }}</p>
                            <p class="mb-1">Due Date: {{ $invoice->due_date->format('F j, Y') }}</p>
                            <p class="mb-0">
                                Status:
                                @if($invoice->status === 'paid')
                                    <span class="badge bg-success">Paid</span>
                                @elseif($invoice->status === 'pending')
                                    <span class="badge bg-warning text-foreground">Pending</span>
                                @else
                                    <span class="badge bg-danger">Overdue</span>
                                @endif
                            </p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <h5>Club Information</h5>
                            <p class="mb-1">{{ $invoice->tenant->club_name }}</p>
                            <p class="mb-0">{{ $invoice->tenant->owner->full_name }} (Owner)</p>
                        </div>
                        <div class="md:text-right">
                            <h5>Student Information</h5>
                            <p class="mb-1">{{ $invoice->student->full_name }}</p>
                            <p class="mb-0">Age: {{ $invoice->student->age }} ({{ $invoice->student->life_stage }})</p>
                        </div>
                    </div>

                    <div class="table-responsive mb-4">
                        <table class="table table-bordered">
                            <thead class="bg-muted">
                                <tr>
                                    <th>Description</th>
                                    <th class="text-right">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Club Membership Fee - {{ $invoice->tenant->club_name }}</td>
                                    <td class="text-right">${{ number_format($invoice->amount, 2) }}</td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th>Total</th>
                                    <th class="text-right">${{ number_format($invoice->amount, 2) }}</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div class="flex justify-end">
                        @if($invoice->status !== 'paid')
                            <a href="{{ route('bills.pay', $invoice->id) }}" class="btn btn-success">
                                <i class="bi bi-credit-card"></i> Pay Now
                            </a>
                        @else
                            <button class="btn btn-outline-success mr-2" disabled>
                                <i class="bi bi-check-circle"></i> Paid
                            </button>
                            <a href="{{ route('bills.receipt', $invoice->id) }}" class="btn btn-outline-primary" target="_blank">
                                <i class="bi bi-receipt"></i> View Receipt
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
