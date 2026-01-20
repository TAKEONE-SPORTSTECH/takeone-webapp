@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">My Family</h1>
        <div>
            <a href="{{ route('invoices.index') }}" class="btn btn-outline-primary">
                <i class="bi bi-receipt"></i> All Invoices
            </a>
        </div>
    </div>

    <!-- Family Members Card Grid -->
    <div class="row row-cols-1 row-cols-md-3 g-4 mb-5">
        <!-- Current User Card -->
        <div class="col">
            <div class="card h-100 shadow-sm">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <img src="{{ $user->media_gallery[0] ?? 'https://via.placeholder.com/150' }}"
                             class="rounded-circle" alt="{{ $user->full_name }}"
                             width="100" height="100">
                    </div>
                    <h5 class="card-title">{{ $user->full_name }}</h5>
                    <p class="card-text text-muted">
                        Age: {{ $user->age }} ({{ $user->life_stage }})
                    </p>
                    <p class="card-text">
                        <small class="text-muted">{{ $user->horoscope }}</small>
                    </p>
                    <a href="{{ route('profile.edit') }}" class="btn btn-primary">
                        <i class="bi bi-pencil"></i> Edit Profile
                    </a>
                </div>
            </div>
        </div>

        <!-- Dependents Cards -->
        @foreach($dependents as $relationship)
            <div class="col">
                <div class="card h-100 shadow-sm">
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <img src="{{ $relationship->dependent->media_gallery[0] ?? 'https://via.placeholder.com/150' }}"
                                 class="rounded-circle" alt="{{ $relationship->dependent->full_name }}"
                                 width="100" height="100">
                        </div>
                        <h5 class="card-title">{{ $relationship->dependent->full_name }}</h5>
                        <p class="card-text text-muted">
                            Age: {{ $relationship->dependent->age }} ({{ $relationship->dependent->life_stage }})
                        </p>
                        <span class="badge bg-secondary">{{ ucfirst($relationship->relationship_type) }}</span>
                    </div>
                    <div class="card-footer bg-transparent border-top-0 text-center">
                        <div class="btn-group" role="group">
                            <a href="{{ route('family.edit', $relationship->dependent->id) }}" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-pencil"></i> Edit
                            </a>
                            <a href="{{ route('family.show', $relationship->dependent->id) }}" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-eye"></i> View
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach

        <!-- Add New Family Member Card -->
        <div class="col">
            <div class="card h-100 shadow-sm border-dashed">
                <a href="{{ route('family.create') }}" class="card-body text-center text-decoration-none d-flex flex-column justify-content-center align-items-center" style="height: 100%;">
                    <div class="mb-3">
                        <i class="bi bi-plus-circle" style="font-size: 3rem;"></i>
                    </div>
                    <h5 class="card-title text-muted">Add Family Member</h5>
                </a>
            </div>
        </div>
    </div>

    <!-- Family Payments Table -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
            <h4 class="mb-0">Family Payments</h4>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Student Name</th>
                            <th>Class/Package</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($familyInvoices as $invoice)
                            <tr>
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
                                <td>
                                    <a href="{{ route('invoices.show', $invoice->id) }}" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye"></i> View
                                    </a>
                                    @if($invoice->status !== 'paid')
                                        <a href="{{ route('invoices.pay', $invoice->id) }}" class="btn btn-sm btn-success">
                                            <i class="bi bi-credit-card"></i> Pay
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-4">
                                    <p class="text-muted mb-0">No payments due at this time.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white d-flex justify-content-end">
            @if(count($familyInvoices->where('status', '!=', 'paid')) > 0)
                <a href="{{ route('invoices.pay-all') }}" class="btn btn-success">
                    <i class="bi bi-credit-card"></i> Pay All
                </a>
            @endif
        </div>
    </div>
</div>

<style>
    .border-dashed {
        border-style: dashed !important;
        border-width: 2px !important;
        border-color: #dee2e6 !important;
    }
</style>
@endsection
