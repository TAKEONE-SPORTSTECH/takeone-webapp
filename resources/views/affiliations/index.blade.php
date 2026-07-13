@extends('layouts.app')

@section('content')
<div class="container py-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">
                <i class="bi bi-diagram-3 me-2 text-primary"></i>{{ __('member.affiliations_index_title') }}
            </h2>
            <p class="text-muted mb-0">{{ __('member.affiliations_index_subtitle') }}</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('clubs.explore') }}" class="btn btn-outline-primary">
                <i class="bi bi-compass me-2"></i>{{ __('member.affiliations_index_explore_clubs') }}
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
                        <small class="opacity-75">{{ __('member.affiliations_index_total_clubs') }}</small>
                    </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm h-100 border-0" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
                    <div class="card-body text-center text-white p-3">
                        <i class="bi bi-check-circle display-5 mb-2"></i>
                        <h3 class="fw-bold mb-1">{{ $activeAffiliations }}</h3>
                        <small class="opacity-75">{{ __('member.affiliations_index_active') }}</small>
                    </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm h-100 border-0" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <div class="card-body text-center text-white p-3">
                        <i class="bi bi-x-circle display-5 mb-2"></i>
                        <h3 class="fw-bold mb-1">{{ $inactiveAffiliations }}</h3>
                        <small class="opacity-75">{{ __('member.affiliations_index_inactive') }}</small>
                    </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm h-100 border-0" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <div class="card-body text-center text-white p-3">
                        <i class="bi bi-currency-dollar display-5 mb-2"></i>
                        <h3 class="fw-bold mb-1">{{ number_format($totalPayments, 0) }}</h3>
                        <small class="opacity-75">{{ __('member.affiliations_index_total_paid') }}</small>
                    </div>
            </div>

        <!-- Filter Tabs -->
        <ul class="nav nav-tabs mb-4" id="affiliationTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="all-tab" data-bs-toggle="tab" data-bs-target="#all" type="button" role="tab">
                    <i class="bi bi-grid me-2"></i>{{ __('member.affiliations_index_all') }} ({{ $totalAffiliations }})
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="active-tab" data-bs-toggle="tab" data-bs-target="#active" type="button" role="tab">
                    <i class="bi bi-check-circle me-2"></i>{{ __('member.affiliations_index_active') }} ({{ $activeAffiliations }})
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="inactive-tab" data-bs-toggle="tab" data-bs-target="#inactive" type="button" role="tab">
                    <i class="bi bi-x-circle me-2"></i>{{ __('member.affiliations_index_inactive') }} ({{ $inactiveAffiliations }})
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
                            <h5 class="text-muted mt-3">{{ __('member.affiliations_index_no_active_title') }}</h5>
                            <p class="text-muted">{{ __('member.affiliations_index_no_active_body') }}</p>
                            <a href="{{ route('clubs.explore') }}" class="btn btn-primary">
                                <i class="bi bi-compass me-2"></i>{{ __('member.affiliations_index_explore_clubs') }}
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
                            <h5 class="text-muted mt-3">{{ __('member.affiliations_index_no_inactive_title') }}</h5>
                            <p class="text-muted">{{ __('member.affiliations_index_no_inactive_body') }}</p>
                        </div>
                    @endif
                </div>
        </div>
    @else
        <!-- Empty State -->
        <div class="card shadow-sm border-0">
            <div class="card-body text-center py-5">
                <i class="bi bi-diagram-3 text-muted" style="font-size: 4rem;"></i>
                <h4 class="text-muted mt-3 mb-2">{{ __('member.affiliations_index_empty_title') }}</h4>
                <p class="text-muted mb-4">{{ __('member.affiliations_index_empty_body') }}</p>
                <a href="{{ route('clubs.explore') }}" class="btn btn-primary">
                    <i class="bi bi-compass me-2"></i>{{ __('member.affiliations_index_explore_clubs') }}
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
                                        {{ __('member.affiliations_index_joined') }} {{ $affiliation->start_date->format('M d, Y') }}
                                    </span>
                                    @if($affiliation->end_date)
                                        <span>
                                            <i class="bi bi-calendar-x me-1"></i>
                                            {{ __('member.affiliations_index_left') }} {{ $affiliation->end_date->format('M d, Y') }}
                                        </span>
                                    @else
                                        <span>
                                            <i class="bi bi-check-circle me-1"></i>
                                            {{ __('member.affiliations_index_present') }}
                                        </span>
                                    @endif
                                    <span class="badge bg-{{ $affiliation->end_date ? 'secondary' : 'success' }} ms-2">
                                        {{ $affiliation->end_date ? __('member.affiliations_index_inactive') : __('member.affiliations_index_active') }}
                                    </span>
                                </div>
                        </div>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="{{ __('member.affiliations_index_close') }}"></button>
                    </div>
                    <div class="modal-body">
                        <!-- Tabs for different sections -->
                        <ul class="nav nav-pills mb-4" id="detailTabs_{{ $affiliation->id }}" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="overview_{{ $affiliation->id }}-tab" data-bs-toggle="pill" data-bs-target="#overview_{{ $affiliation->id }}" type="button" role="tab">
                                    <i class="bi bi-info-circle me-2"></i>{{ __('member.affiliations_index_overview') }}
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="enrollment_{{ $affiliation->id }}-tab" data-bs-toggle="pill" data-bs-target="#enrollment_{{ $affiliation->id }}" type="button" role="tab">
                                    <i class="bi bi-calendar-range me-2"></i>{{ __('member.affiliations_index_enrollment') }}
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="payments_{{ $affiliation->id }}-tab" data-bs-toggle="pill" data-bs-target="#payments_{{ $affiliation->id }}" type="button" role="tab">
                                    <i class="bi bi-currency-dollar me-2"></i>{{ __('member.affiliations_index_payments') }}
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="attendance_{{ $affiliation->id }}-tab" data-bs-toggle="pill" data-bs-target="#attendance_{{ $affiliation->id }}" type="button" role="tab">
                                    <i class="bi bi-calendar-check me-2"></i>{{ __('member.affiliations_index_attendance') }}
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="skills_{{ $affiliation->id }}-tab" data-bs-toggle="pill" data-bs-target="#skills_{{ $affiliation->id }}" type="button" role="tab">
                                    <i class="bi bi-star me-2"></i>{{ __('member.affiliations_index_skills') }}
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
                                                    <i class="bi bi-info-circle me-2"></i>{{ __('member.affiliations_index_club_information') }}
                                                </h6>
                                                @if($affiliation->location)
                                                    <p class="mb-2">
                                                        <i class="bi bi-geo-alt text-primary me-2"></i>
                                                        <strong>{{ __('member.affiliations_index_location') }}</strong> {{ $affiliation->location }}
                                                    </p>
                                                @endif
                                                @if($affiliation->description)
                                                    <p class="mb-2">
                                                        <i class="bi bi-text-paragraph text-primary me-2"></i>
                                                        <strong>{{ __('member.affiliations_index_description') }}</strong> {{ $affiliation->description }}
                                                    </p>
                                                @endif
                                                @if($affiliation->formatted_duration)
                                                    <p class="mb-0">
                                                        <i class="bi bi-hourglass-split text-primary me-2"></i>
                                                        <strong>{{ __('member.affiliations_index_duration') }}</strong> {{ $affiliation->formatted_duration }}
                                                    </p>
                                                @endif
                                            </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card mb-3">
                                            <div class="card-body">
                                                <h6 class="card-title text-muted mb-3">
                                                    <i class="bi bi-box-seam me-2"></i>{{ __('member.affiliations_index_membership_packages') }}
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
                                                                                <span class="badge bg-success ms-2">{{ __('member.affiliations_index_active') }}</span>
                                                                            @else
                                                                                <span class="badge bg-secondary ms-2">{{ __('member.affiliations_index_inactive') }}</span>
                                                                            @endif
                                                                        </div>
                                                                        @if($subscription->package->price)
                                                                            <span class="text-success fw-bold">
                                                                                ${{ number_format($subscription->package->price, 2) }}
                                                                            </span>
                                                                        @endif
                                                                    </div>
                                                                    <small class="text-muted">
                                                                        {{ $subscription->start_date ? $subscription->start_date->format('M d, Y') : __('member.affiliations_index_na') }}
                                                                        -
                                                                        {{ $subscription->end_date ? $subscription->end_date->format('M d, Y') : __('member.affiliations_index_ongoing') }}
                                                                    </small>
                                                                </div>
                                                            @endif
                                                        @endforeach
                                                    </div>
                                                @else
                                                    <p class="text-muted mb-0">{{ __('member.affiliations_index_no_packages') }}</p>
                                                @endif
                                            </div>
                                    </div>
                            </div>

                            <!-- Enrollment Tab -->
                            <div class="tab-pane fade" id="enrollment_{{ $affiliation->id }}" role="tabpanel">
                                <div class="card">
                                    <div class="card-body">
                                        <h6 class="card-title text-muted mb-4">
                                            <i class="bi bi-calendar-range me-2"></i>{{ __('member.affiliations_index_enrollment_timeline') }}
                                        </h6>
                                        <div class="timeline-enhanced">
                                            <div class="timeline-item-enhanced mb-4">
                                                <div class="timeline-marker-enhanced bg-success"></div>
                                                <div class="card border-0 shadow-sm">
                                                    <div class="card-body">
                                                        <div class="d-flex justify-content-between align-items-start">
                                                            <div>
                                                                <h6 class="fw-bold">{{ __('member.affiliations_index_membership_started') }}</h6>
                                                                <p class="text-muted mb-0">{{ $affiliation->start_date->format('l, F d, Y') }}</p>
                                                            </div>
                                                            <span class="badge bg-success">{{ __('member.affiliations_index_joined_badge') }}</span>
                                                        </div>
                                                        @if($affiliation->subscriptions && $affiliation->subscriptions->count() > 0)
                                                            <hr>
                                                            <div class="small text-muted">
                                                                <strong>{{ __('member.affiliations_index_first_package') }}</strong>
                                                                {{ $affiliation->subscriptions->first()->package->name ?? __('member.affiliations_index_na') }}
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
                                                                            {{ $subscription->start_date ? $subscription->start_date->format('M d, Y') : __('member.affiliations_index_na') }}
                                                                            -
                                                                            {{ $subscription->end_date ? $subscription->end_date->format('M d, Y') : __('member.affiliations_index_present') }}
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
                                                                    <h6 class="fw-bold">{{ __('member.affiliations_index_membership_ended') }}</h6>
                                                                    <p class="text-muted mb-0">{{ $affiliation->end_date->format('l, F d, Y') }}</p>
                                                                </div>
                                                                <span class="badge bg-danger">{{ __('member.affiliations_index_left_badge') }}</span>
                                                            </div>
                                                            <div class="mt-2 small text-muted">
                                                                {{ __('member.affiliations_index_total_duration') }} {{ $affiliation->formatted_duration }}
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
                                            <i class="bi bi-currency-dollar me-2"></i>{{ __('member.affiliations_index_payment_history') }}
                                        </h6>
                                        @php
                                            $affiliationInvoices = $invoices->where('club_affiliation_id', $affiliation->id);
                                        @endphp
                                        @if($affiliationInvoices->count() > 0)
                                            <div class="table-responsive">
                                                <table class="table table-hover">
                                                    <thead>
                                                        <tr>
                                                            <th>{{ __('member.affiliations_index_invoice_number') }}</th>
                                                            <th>{{ __('member.affiliations_index_date') }}</th>
                                                            <th>{{ __('member.affiliations_index_table_description') }}</th>
                                                            <th>{{ __('member.affiliations_index_amount') }}</th>
                                                            <th>{{ __('member.affiliations_index_status') }}</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach($affiliationInvoices as $invoice)
                                                            <tr>
                                                                <td>
                                                                    <strong>{{ $invoice->invoice_number ?? __('member.affiliations_index_na') }}</strong>
                                                                </td>
                                                                <td>{{ $invoice->created_at->format('M d, Y') }}</td>
                                                                <td>{{ $invoice->description ?? __('member.affiliations_index_membership_payment') }}</td>
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
                                                            <td colspan="3" class="text-end fw-bold">{{ __('member.affiliations_index_total_paid_label') }}</td>
                                                            <td class="fw-bold text-success">${{ number_format($affiliationInvoices->where('status', 'paid')->sum('amount'), 2) }}</td>
                                                            <td></td>
                                                        </tr>
                                                    </tfoot>
                                                </table>
                                            </div>
                                        @else
                                            <div class="text-center py-4">
                                                <i class="bi bi-receipt text-muted" style="font-size: 3rem;"></i>
                                                <h6 class="text-muted mt-3">{{ __('member.affiliations_index_no_payment_records') }}</h6>
                                                <p class="text-muted mb-0">{{ __('member.affiliations_index_no_invoices') }}</p>
                                            </div>
                                        @endif
                                    </div>
                            </div>

                            <!-- Attendance Tab -->
                            <div class="tab-pane fade" id="attendance_{{ $affiliation->id }}" role="tabpanel">
                                <div class="card">
                                    <div class="card-body">
                                        <h6 class="card-title text-muted mb-4">
                                            <i class="bi bi-calendar-check me-2"></i>{{ __('member.affiliations_index_attendance_records') }}
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
                                                            <small class="text-muted">{{ __('member.affiliations_index_total_sessions') }}</small>
                                                        </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="card bg-light border-0">
                                                        <div class="card-body text-center">
                                                            <h3 class="fw-bold text-success">{{ $completedAttendance->count() }}</h3>
                                                            <small class="text-muted">{{ __('member.affiliations_index_completed') }}</small>
                                                        </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="card bg-light border-0">
                                                        <div class="card-body text-center">
                                                            <h3 class="fw-bold text-{{ $attendanceRate >= 80 ? 'success' : ($attendanceRate >= 50 ? 'warning' : 'danger') }}">
                                                                {{ $attendanceRate }}%
                                                            </h3>
                                                            <small class="text-muted">{{ __('member.affiliations_index_attendance_rate') }}</small>
                                                        </div>
                                                </div>

                                            <!-- Attendance List -->
                                            <div class="table-responsive">
                                                <table class="table table-hover">
                                                    <thead>
                                                        <tr>
                                                            <th>{{ __('member.affiliations_index_session_date') }}</th>
                                                            <th>{{ __('member.affiliations_index_activity') }}</th>
                                                            <th>{{ __('member.affiliations_index_status') }}</th>
                                                            <th>{{ __('member.affiliations_index_notes') }}</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach($affiliationAttendance as $record)
                                                            <tr>
                                                                <td>{{ \Carbon\Carbon::parse($record->session_datetime)->format('M d, Y h:i A') }}</td>
                                                                <td>{{ $record->activity_name ?? __('member.affiliations_index_general_session') }}</td>
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
                                                <h6 class="text-muted mt-3">{{ __('member.affiliations_index_no_attendance_title') }}</h6>
                                                <p class="text-muted mb-0">{{ __('member.affiliations_index_no_attendance_body') }}</p>
                                            </div>
                                        @endif
                                    </div>
                            </div>

                            <!-- Skills Tab -->
                            <div class="tab-pane fade" id="skills_{{ $affiliation->id }}" role="tabpanel">
                                <div class="card">
                                    <div class="card-body">
                                        <h6 class="card-title text-muted mb-4">
                                            <i class="bi bi-star me-2"></i>{{ __('member.affiliations_index_skills_acquired') }}
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
                                                                        {{ __('member.affiliations_index_instructor') }} {{ $skill->instructor->user->full_name ?? __('member.affiliations_index_unknown') }}
                                                                    </small>
                                                                @endif
                                                                @if($skill->formatted_duration)
                                                                    <small class="text-muted">
                                                                        <i class="bi bi-hourglass-split me-1"></i>
                                                                        {{ __('member.affiliations_index_duration') }} {{ $skill->formatted_duration }}
                                                                    </small>
                                                                @endif
                                                            </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @else
                                            <div class="text-center py-4">
                                                <i class="bi bi-star text-muted" style="font-size: 3rem;"></i>
                                                <h6 class="text-muted mt-3">{{ __('member.affiliations_index_no_skills_title') }}</h6>
                                                <p class="text-muted mb-0">{{ __('member.affiliations_index_no_skills_body') }}</p>
                                            </div>
                                        @endif
                                    </div>
                            </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('member.affiliations_index_close') }}</button>
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
