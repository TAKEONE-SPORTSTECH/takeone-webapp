@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">Family Member Details</h4>
                    <a href="{{ route('family.dashboard') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-left"></i> Back to Family
                    </a>
                </div>
                <div class="card-body">
                    <div class="text-center mb-4">
                        <img src="{{ $relationship->dependent->media_gallery[0] ?? 'https://via.placeholder.com/150' }}"
                             class="rounded-circle mb-3" alt="{{ $relationship->dependent->full_name }}"
                             width="120" height="120">
                        <h3>{{ $relationship->dependent->full_name }}</h3>
                        <span class="badge bg-secondary">{{ ucfirst($relationship->relationship_type) }}</span>
                        @if($relationship->is_billing_contact)
                            <span class="badge bg-info ms-2">Billing Contact</span>
                        @endif
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <h6 class="text-muted">Age</h6>
                            <p>{{ $relationship->dependent->age }} years ({{ $relationship->dependent->life_stage }})</p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <h6 class="text-muted">Birthdate</h6>
                            <p>{{ $relationship->dependent->birthdate->format('F j, Y') }}</p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <h6 class="text-muted">Gender</h6>
                            <p>{{ ucfirst($relationship->dependent->gender) }}</p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <h6 class="text-muted">Horoscope</h6>
                            <p>{{ $relationship->dependent->horoscope }}</p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <h6 class="text-muted">Nationality</h6>
                            <p>{{ $relationship->dependent->nationality }}</p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <h6 class="text-muted">Blood Type</h6>
                            <p>{{ $relationship->dependent->blood_type ?? 'Not specified' }}</p>
                        </div>
                        @if($relationship->dependent->email)
                        <div class="col-md-6 mb-3">
                            <h6 class="text-muted">Email</h6>
                            <p>{{ $relationship->dependent->email }}</p>
                        </div>
                        @endif
                        @if($relationship->dependent->mobile)
                        <div class="col-md-6 mb-3">
                            <h6 class="text-muted">Mobile</h6>
                            <p>{{ $relationship->dependent->mobile }}</p>
                        </div>
                        @endif
                    </div>

                    <div class="d-flex justify-content-end mt-3">
                        <a href="{{ route('family.edit', $relationship->dependent->id) }}" class="btn btn-primary">
                            <i class="bi bi-pencil"></i> Edit
                        </a>
                    </div>
                </div>
            </div>

            <!-- Memberships -->
            <div class="card shadow-sm mt-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Club Memberships</h5>
                </div>
                <div class="card-body">
                    @if($relationship->dependent->memberClubs->count() > 0)
                        <div class="list-group">
                            @foreach($relationship->dependent->memberClubs as $club)
                                <div class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1">{{ $club->club_name }}</h6>
                                        <small class="text-muted">Status: {{ ucfirst($club->pivot->status) }}</small>
                                    </div>
                                    <span class="badge bg-{{ $club->pivot->status === 'active' ? 'success' : 'secondary' }} rounded-pill">
                                        {{ ucfirst($club->pivot->status) }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-center text-muted my-4">No club memberships found.</p>
                    @endif
                </div>
            </div>

            <!-- Invoices -->
            <div class="card shadow-sm mt-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Recent Invoices</h5>
                </div>
                <div class="card-body">
                    @if($relationship->dependent->studentInvoices->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Club</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Due Date</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($relationship->dependent->studentInvoices->take(5) as $invoice)
                                        <tr>
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
                                                <a href="{{ route('invoices.show', $invoice->id) }}" class="btn btn-sm btn-outline-primary">
                                                    View
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @if($relationship->dependent->studentInvoices->count() > 5)
                            <div class="text-center mt-3">
                                <a href="{{ route('invoices.index') }}" class="btn btn-outline-primary btn-sm">
                                    View All Invoices
                                </a>
                            </div>
                        @endif
                    @else
                        <p class="text-center text-muted my-4">No invoices found.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
