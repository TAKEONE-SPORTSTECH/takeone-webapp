@extends('layouts.app')

@section('content')
<div class="container py-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">
                <i class="bi bi-diagram-3 me-2 text-primary"></i>My Affiliations
            </h2>
            <p class="text-muted mb-0">View your club memberships, enrollment history, payments, and attendance</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('clubs.explore') }}" class="btn btn-outline-primary">
                <i class="bi bi-compass me-2"></i>Explore Clubs
            </a>
        </div>

    @if($affiliations->count() > 0)
        <!-- Summary Stats -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card shadow-sm h-100 border-0" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <div class="card-body text-center text-white p-3">
                        <i class="bi bi-building display-5 mb-2"></i>
                        <h3 class="fw-bold mb-1">{{ $totalAffiliations }}</h3>
                        <small class="opacity-75">Total Clubs</small>
                    </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm h-100 border-0" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
                    <div class="card-body text-center text-white p-3">
                        <i class="bi bi-check-circle display-5 mb-2"></i>
                        <h3 class="fw-bold mb-1">{{ $activeAffiliations }}</h3>
                        <small class="opacity-75">Active</small>
                    </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm h-100 border-0" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <div class="card-body text-center text-white p-3">
                        <i class="bi bi-x-circle display-5 mb-2"></i>
                        <h3 class="fw-bold mb-1">{{ $inactiveAffiliations }}</h3>
                        <small class="opacity-75">Inactive</small>
                    </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm h-100 border-0" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <div class="card-body text-center text-white p-3">
                        <i class="bi bi-currency-dollar display-5 mb-2"></i>
                        <h3 class="fw-bold mb-1">{{ number_format($totalPayments, 0) }}</h3>
                        <small class="opacity-75">Total Paid</small>
                    </div>
            </div>

        <!-- Filter Tabs -->
        <ul class="nav nav-tabs mb-4" id="affiliationTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="all-tab" data-bs-toggle="tab" data-bs-target="#all" type="button" role="tab">
                    <i class="bi bi-grid me-2"></i>All ({{ $totalAffiliations }})
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="active-tab" data-bs-toggle="tab" data-bs-target="#active" type="button" role="tab">
                    <i class="bi bi-check-circle me-2"></i>Active ({{ $activeAffiliations }})
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="inactive-tab" data-bs-toggle="tab" data-bs-target="#inactive" type="button" role="tab">
                    <i class="bi bi-x-circle me-2"></i>Inactive ({{ $inactiveAffiliations }})
                </button>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content" id="affiliationTabsContent">
            <!-- All Affiliations -->
            <div class="tab-pane fade show active" id="all" role="tabpanel">
                <div class="row g-4">
                    @foreach($affiliations as $affiliation)
                        @include('affiliations.partials.affiliation-card', ['affiliation' => $affiliation])
                    @endforeach
                </div>

            <!-- Active Affiliations -->
            <div class="tab-pane fade" id="active" role="tabpanel">
                <div class="row g-4">
                    @foreach($affiliations->whereNull('end_date') as $affiliation)
                        @include('affiliations.partials.affiliation-card', ['affiliation' => $affiliation])
                    @endforeach
                    @if($affiliations->whereNull('end_date')->count() == 0)
                        <div class="col-12 text-center py-5">
                            <i class="bi bi-check-circle text-muted" style="font-size: 3rem;"></i>
                            <h5 class="text-muted mt-3">No Active Affiliations</h5>
                            <p class="text-muted">You don't have any active club memberships at the moment.</p>
                            <a href="{{ route('clubs.explore') }}" class="btn btn-primary">
                                <i class="bi bi-compass me-2"></i>Explore Clubs
                            </a>
                        </div>
                    @endif
                </div>

            <!-- Inactive Affiliations -->
            <div class="tab-pane fade" id="inactive" role="tabpanel">
                <div class="row g-4">
                    @foreach($affiliations->whereNotNull('end_date') as $affiliation)
                        @include('affiliations.partials.affiliation-card', ['affiliation' => $affiliation])
                    @endforeach
                    @if($affiliations->whereNotNull('end_date')->count() == 0)
                        <div class="col-12 text-center py-5">
                            <i class="bi bi-x-circle text-muted" style="font-size: 3rem;"></i>
                            <h5 class="text-muted mt-3">No Inactive Affiliations</h5>
                            <p class="text-muted">You don't have any past club memberships.</p>
                        </div>
                    @endif
                </div>
        </div>
    @else
        <!-- Empty State -->
        <div class="card shadow-sm border-0">
            <div class="card-body text-center py-5">
                <i class="bi bi-diagram-3 text-muted" style="font-size: 4rem;"></i>
                <h4 class="text-muted mt-3 mb-2">No Affiliations Yet</h4>
                <p class="text-muted mb-4">You haven't joined any clubs yet. Start exploring to find your perfect fit!</p>
                <a href="{{ route('clubs.explore') }}" class="btn btn-primary">
                    <i class="bi bi-compass me-2"></i>Explore Clubs
                </a>
            </div>
    @endif
</div>

<!-- Affiliation Detail Modal -->
@if($affiliations->count() > 0)
    @foreach($affiliations as $affiliation)
        <div class="modal fade" id="affiliationModal_{{ $affiliation->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                        <div class="d-flex align-items-center">
                            @if($affiliation->logo)
                                <img src="{{ asset('storage/' . $affiliation->logo) }}" alt="{{ $affiliation->club_name }}" class="rounded-circle me-3" style="width: 60px; height: 60px; object-fit: cover; border: 3px solid white;">
                            @else
                                <div class="rounded-circle bg-white d-flex align-items-center justify-content-center me-3" style="width: 60px; height: 60px;">
                                    <i class="bi bi-building" style="font-size: 1.5rem; color: #667eea;"></i>
                                </div>
                            @endif
                            <div class="text-white">
                                <h5 class="modal-title mb-1">{{ $affiliation->club_name }}</h5>
                                <div class="d-flex gap-3 small opacity-90">
                                    <span>
                                        <i class="bi bi-calendar-event me-1"></i>
                                        Joined: {{ $affiliation->start_date->format('M d, Y') }}
                                    </span>
                                    @if($affiliation->end_date)
                                        <span>
                                            <i class="bi bi-calendar-x me-1"></i>
                                            Left: {{ $affiliation->end_date->format('M d, Y') }}
                                        </span>
                                    @else
                                        <span>
                                            <i class="bi bi-check-circle me-1"></i>
                                            Present
                                        </span>
                                    @endif
                                    <span class="badge bg-{{ $affiliation->end_date ? 'secondary' : 'success' }} ms-2">
                                        {{ $affiliation->end_date ? 'Inactive' : 'Active' }}
                                    </span>
                                </div>
                        </div>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <!-- Tabs for different sections -->
                        <ul class="nav nav-pills mb-4" id="detailTabs_{{ $affiliation->id }}" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="overview_{{ $affiliation->id }}-tab" data-bs-toggle="pill" data-bs-target="#overview_{{ $affiliation->id }}" type="button" role="tab">
                                    <i class="bi bi-info-circle me-2"></i>Overview
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="enrollment_{{ $affiliation->id }}-tab" data-bs-toggle="pill" data-bs-target="#enrollment_{{ $affiliation->id }}" type="button" role="tab">
                                    <i class="bi bi-calendar-range me-2"></i>Enrollment
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="payments_{{ $affiliation->id }}-tab" data-bs-toggle="pill" data-bs-target="#payments_{{ $affiliation->id }}" type="button" role="tab">
                                    <i class="bi bi-currency-dollar me-2"></i>Payments
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="attendance_{{ $affiliation->id }}-tab" data-bs-toggle="pill" data-bs-target="#attendance_{{ $affiliation->id }}" type="button" role="tab">
                                    <i class="bi bi-calendar-check me-2"></i>Attendance
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="skills_{{ $affiliation->id }}-tab" data-bs-toggle="pill" data-bs-target="#skills_{{ $affiliation->id }}" type="button" role="tab">
                                    <i class="bi bi-star me-2"></i>Skills
                                </button>
                            </li>
                        </ul>

                        <div class="tab-content" id="detailTabsContent_{{ $affiliation->id }}">
                            <!-- Overview Tab -->
                            <div class="tab-pane fade show active" id="overview_{{ $affiliation->id }}" role="tabpanel">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="card mb-3">
                                            <div class="card-body">
                                                <h6 class="card-title text-muted mb-3">
                                                    <i class="bi bi-info-circle me-2"></i>Club Information
                                                </h6>
                                                @if($affiliation->location)
                                                    <p class="mb-2">
                                                        <i class="bi bi-geo-alt text-primary me-2"></i>
                                                        <strong>Location:</strong> {{ $affiliation->location }}
                                                    </p>
                                                @endif
                                                @if($affiliation->description)
                                                    <p class="mb-2">
                                                        <i class="bi bi-text-paragraph text-primary me-2"></i>
                                                        <strong>Description:</strong> {{ $affiliation->description }}
                                                    </p>
                                                @endif
                                                @if($affiliation->formatted_duration)
                                                    <p class="mb-0">
                                                        <i class="bi bi-hourglass-split text-primary me-2"></i>
                                                        <strong>Duration:</strong> {{ $affiliation->formatted_duration }}
                                                    </p>
                                                @endif
                                            </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card mb-3">
                                            <div class="card-body">
                                                <h6 class="card-title text-muted mb-3">
                                                    <i class="bi bi-box-seam me-2"></i>Membership Packages
                                                </h6>
                                                @if($affiliation->subscriptions && $affiliation->subscriptions->count() > 0)
                                                    <div class="list-group list-group-flush">
                                                        @foreach($affiliation->subscriptions as $subscription)
                                                            @if($subscription->package)
                                                                <div class="list-group-item px-0">
                                                                    <div class="d-flex justify-content-between align-items-start">
                                                                        <div>
                                                                            <strong>{{ $subscription->package->name }}</strong>
                                                                            @if($subscription->status == 'active')
                                                                                <span class="badge bg-success ms-2">Active</span>
                                                                            @else
                                                                                <span class="badge bg-secondary ms-2">Inactive</span>
                                                                            @endif
                                                                        </div>
                                                                        @if($subscription->package->price)
                                                                            <span class="text-success fw-bold">
                                                                                ${{ number_format($subscription->package->price, 2) }}
                                                                            </span>
                                                                        @endif
                                                                    </div>
                                                                    <small class="text-muted">
                                                                        {{ $subscription->start_date ? $subscription->start_date->format('M d, Y') : 'N/A' }}
                                                                        -
                                                                        {{ $subscription->end_date ? $subscription->end_date->format('M d, Y') : 'Ongoing' }}
                                                                    </small>
                                                                </div>
                                                            @endif
                                                        @endforeach
                                                    </div>
                                                @else
                                                    <p class="text-muted mb-0">No packages subscribed</p>
                                                @endif
                                            </div>
                                    </div>
                            </div>

                            <!-- Enrollment Tab -->
                            <div class="tab-pane fade" id="enrollment_{{ $affiliation->id }}" role="tabpanel">
                                <div class="card">
                                    <div class="card-body">
                                        <h6 class="card-title text-muted mb-4">
                                            <i class="bi bi-calendar-range me-2"></i>Enrollment Timeline
                                        </h6>
                                        <div class="timeline-enhanced">
                                            <div class="timeline-item-enhanced mb-4">
                                                <div class="timeline-marker-enhanced bg-success"></div>
                                                <div class="card border-0 shadow-sm">
                                                    <div class="card-body">
                                                        <div class="d-flex justify-content-between align-items-start">
                                                            <div>
                                                                <h6 class="fw-bold">Membership Started</h6>
                                                                <p class="text-muted mb-0">{{ $affiliation->start_date->format('l, F d, Y') }}</p>
                                                            </div>
                                                            <span class="badge bg-success">Joined</span>
                                                        </div>
                                                        @if($affiliation->subscriptions && $affiliation->subscriptions->count() > 0)
                                                            <hr>
                                                            <div class="small text-muted">
                                                                <strong>First Package:</strong>
                                                                {{ $affiliation->subscriptions->first()->package->name ?? 'N/A' }}
                                                            </div>
                                                        @endif
                                                    </div>
                                            </div>

                                            @foreach($affiliation->subscriptions as $subscription)
                                                @if($subscription->package)
                                                    <div class="timeline-item-enhanced mb-4">
                                                        <div class="timeline-marker-enhanced bg-primary"></div>
                                                        <div class="card border-0 shadow-sm">
                                                            <div class="card-body">
                                                                <div class="d-flex justify-content-between align-items-start">
                                                                    <div>
                                                                        <h6 class="fw-bold">{{ $subscription->package->name }}</h6>
                                                                        <p class="text-muted mb-0">
                                                                            {{ $subscription->start_date ? $subscription->start_date->format('M d, Y') : 'N/A' }}
                                                                            -
                                                                            {{ $subscription->end_date ? $subscription->end_date->format('M d, Y') : 'Present' }}
                                                                        </p>
                                                                    </div>
                                                                    <span class="badge bg-{{ $subscription->status == 'active' ? 'success' : 'secondary' }}">
                                                                        {{ ucfirst($subscription->status) }}
                                                                    </span>
                                                                </div>
                                                                @if($subscription->package->price)
                                                                    <div class="mt-2">
                                                                        <strong class="text-success">${{ number_format($subscription->package->price, 2) }}</strong>
                                                                    </div>
                                                                @endif
                                                            </div>
                                                    </div>
                                                @endif
                                            @endforeach

                                            @if($affiliation->end_date)
                                                <div class="timeline-item-enhanced mb-4">
                                                    <div class="timeline-marker-enhanced bg-danger"></div>
                                                    <div class="card border-0 shadow-sm">
                                                        <div class="card-body">
                                                            <div class="d-flex justify-content-between align-items-start">
                                                                <div>
                                                                    <h6 class="fw-bold">Membership Ended</h6>
                                                                    <p class="text-muted mb-0">{{ $affiliation->end_date->format('l, F d, Y') }}</p>
                                                                </div>
                                                                <span class="badge bg-danger">Left</span>
                                                            </div>
                                                            <div class="mt-2 small text-muted">
                                                                Total Duration: {{ $affiliation->formatted_duration }}
                                                            </div>
                                                    </div>
                                            @endif
                                        </div>
                                </div>

                            <!-- Payments Tab -->
                            <div class="tab-pane fade" id="payments_{{ $affiliation->id }}" role="tabpanel">
                                <div class="card">
                                    <div class="card-body">
                                        <h6 class="card-title text-muted mb-4">
                                            <i class="bi bi-currency-dollar me-2"></i>Payment History
                                        </h6>
                                        @php
                                            $affiliationInvoices = $invoices->where('club_affiliation_id', $affiliation->id);
                                        @endphp
                                        @if($affiliationInvoices->count() > 0)
                                            <div class="table-responsive">
                                                <table class="table table-hover">
                                                    <thead>
                                                        <tr>
                                                            <th>Invoice #</th>
                                                            <th>Date</th>
                                                            <th>Description</th>
                                                            <th>Amount</th>
                                                            <th>Status</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach($affiliationInvoices as $invoice)
                                                            <tr>
                                                                <td>
                                                                    <strong>{{ $invoice->invoice_number ?? 'N/A' }}</strong>
                                                                </td>
                                                                <td>{{ $invoice->created_at->format('M d, Y') }}</td>
                                                                <td>{{ $invoice->description ?? 'Membership Payment' }}</td>
                                                                <td class="fw-bold">${{ number_format($invoice->amount, 2) }}</td>
                                                                <td>
                                                                    <span class="badge bg-{{ $invoice->status == 'paid' ? 'success' : ($invoice->status == 'pending' ? 'warning' : 'danger') }}">
                                                                        {{ ucfirst($invoice->status) }}
                                                                    </span>
                                                                </td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                    <tfoot>
                                                        <tr class="table-success">
                                                            <td colspan="3" class="text-end fw-bold">Total Paid:</td>
                                                            <td class="fw-bold text-success">${{ number_format($affiliationInvoices->where('status', 'paid')->sum('amount'), 2) }}</td>
                                                            <td></td>
                                                        </tr>
                                                    </tfoot>
                                                </table>
                                            </div>
                                        @else
                                            <div class="text-center py-4">
                                                <i class="bi bi-receipt text-muted" style="font-size: 3rem;"></i>
                                                <h6 class="text-muted mt-3">No Payment Records</h6>
                                                <p class="text-muted mb-0">No invoices found for this affiliation.</p>
                                            </div>
                                        @endif
                                    </div>
                            </div>

                            <!-- Attendance Tab -->
                            <div class="tab-pane fade" id="attendance_{{ $affiliation->id }}" role="tabpanel">
                                <div class="card">
                                    <div class="card-body">
                                        <h6 class="card-title text-muted mb-4">
                                            <i class="bi bi-calendar-check me-2"></i>Attendance Records
                                        </h6>
                                        @php
                                            $affiliationAttendance = $attendanceRecords->where('club_affiliation_id', $affiliation->id);
                                            $completedAttendance = $affiliationAttendance->where('status', 'completed');
                                            $totalSessions = $affiliationAttendance->count();
                                            $attendanceRate = $totalSessions > 0 ? round(($completedAttendance->count() / $totalSessions) * 100) : 0;
                                        @endphp

                                        @if($totalSessions > 0)
                                            <!-- Stats Row -->
                                            <div class="row g-3 mb-4">
                                                <div class="col-md-4">
                                                    <div class="card bg-light border-0">
                                                        <div class="card-body text-center">
                                                            <h3 class="fw-bold text-primary">{{ $totalSessions }}</h3>
                                                            <small class="text-muted">Total Sessions</small>
                                                        </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="card bg-light border-0">
                                                        <div class="card-body text-center">
                                                            <h3 class="fw-bold text-success">{{ $completedAttendance->count() }}</h3>
                                                            <small class="text-muted">Completed</small>
                                                        </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="card bg-light border-0">
                                                        <div class="card-body text-center">
                                                            <h3 class="fw-bold text-{{ $attendanceRate >= 80 ? 'success' : ($attendanceRate >= 50 ? 'warning' : 'danger') }}">
                                                                {{ $attendanceRate }}%
                                                            </h3>
                                                            <small class="text-muted">Attendance Rate</small>
                                                        </div>
                                                </div>

                                            <!-- Attendance List -->
                                            <div class="table-responsive">
                                                <table class="table table-hover">
                                                    <thead>
                                                        <tr>
                                                            <th>Session Date</th>
                                                            <th>Activity</th>
                                                            <th>Status</th>
                                                            <th>Notes</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach($affiliationAttendance as $record)
                                                            <tr>
                                                                <td>{{ \Carbon\Carbon::parse($record->session_datetime)->format('M d, Y h:i A') }}</td>
                                                                <td>{{ $record->activity_name ?? 'General Session' }}</td>
                                                                <td>
                                                                    <span class="badge bg-{{ $record->status == 'completed' ? 'success' : ($record->status == 'no_show' ? 'danger' : 'secondary') }}">
                                                                        {{ ucfirst($record->status) }}
                                                                    </span>
                                                                </td>
                                                                <td>{{ $record->notes ?? '-' }}</td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        @else
                                            <div class="text-center py-4">
                                                <i class="bi bi-calendar-x text-muted" style="font-size: 3rem;"></i>
                                                <h6 class="text-muted mt-3">No Attendance Records</h6>
                                                <p class="text-muted mb-0">No attendance records found for this affiliation.</p>
                                            </div>
                                        @endif
                                    </div>
                            </div>

                            <!-- Skills Tab -->
                            <div class="tab-pane fade" id="skills_{{ $affiliation->id }}" role="tabpanel">
                                <div class="card">
                                    <div class="card-body">
                                        <h6 class="card-title text-muted mb-4">
                                            <i class="bi bi-star me-2"></i>Skills Acquired
                                        </h6>
                                        @php
                                            $skills = $affiliation->skillAcquisitions ?? collect();
                                        @endphp
                                        @if($skills->count() > 0)
                                            <div class="row g-3">
                                                @foreach($skills as $skill)
                                                    <div class="col-md-6">
                                                        <div class="card border-0 shadow-sm">
                                                            <div class="card-body">
                                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                                    <h6 class="fw-bold mb-0">{{ $skill->skill_name }}</h6>
                                                                    <span class="badge bg-{{ $skill->proficiency_level == 'expert' ? 'danger' : ($skill->proficiency_level == 'advanced' ? 'warning' : ($skill->proficiency_level == 'intermediate' ? 'info' : 'secondary')) }}">
                                                                        {{ ucfirst($skill->proficiency_level) }}
                                                                    </span>
                                                                </div>
                                                                @if($skill->activity)
                                                                    <small class="text-muted d-block mb-2">
                                                                        <i class="bi bi-activity me-1"></i>
                                                                        {{ $skill->activity->name }}
                                                                    </small>
                                                                @endif
                                                                @if($skill->instructor)
                                                                    <small class="text-muted d-block">
                                                                        <i class="bi bi-person me-1"></i>
                                                                        Instructor: {{ $skill->instructor->user->full_name ?? 'Unknown' }}
                                                                    </small>
                                                                @endif
                                                                @if($skill->formatted_duration)
                                                                    <small class="text-muted">
                                                                        <i class="bi bi-hourglass-split me-1"></i>
                                                                        Duration: {{ $skill->formatted_duration }}
                                                                    </small>
                                                                @endif
                                                            </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @else
                                            <div class="text-center py-4">
                                                <i class="bi bi-star text-muted" style="font-size: 3rem;"></i>
                                                <h6 class="text-muted mt-3">No Skills Recorded</h6>
                                                <p class="text-muted mb-0">No skills have been recorded for this affiliation yet.</p>
                                            </div>
                                        @endif
                                    </div>
                            </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
            </div>
    @endforeach
@endif

@push('styles')
<style>
    .timeline-enhanced {
        position: relative;
        padding-left: 40px;
    }

    .timeline-enhanced::before {
        content: '';
        position: absolute;
        left: 20px;
        top: 0;
        bottom: 0;
        width: 3px;
        background: linear-gradient(180deg, #667eea 0%, #764ba2 100%);
    }

    .timeline-item-enhanced {
        position: relative;
    }

    .timeline-marker-enhanced {
        position: absolute;
        left: -28px;
        top: 20px;
        width: 16px;
        height: 16px;
        border-radius: 50%;
        background: #667eea;
        border: 3px solid #fff;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.2);
        z-index: 1;
    }

    .nav-pills .nav-link {
        border-radius: 50px;
        padding: 0.5rem 1.5rem;
    }

    .nav-pills .nav-link.active {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }

    .affiliation-card {
        transition: all 0.3s ease;
    }

    .affiliation-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important;
    }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize any Bootstrap tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>
@endpush
@endsection
