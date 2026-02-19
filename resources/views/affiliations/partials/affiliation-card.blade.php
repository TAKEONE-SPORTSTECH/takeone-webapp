{{-- Affiliation Card Partial --}}
@php
    $isActive = !$affiliation->end_date;
    $skills = $affiliation->skillAcquisitions ?? collect();
    $subscriptions = $affiliation->subscriptions ?? collect();
@endphp

<div class="col-md-6 col-lg-4">
    <div class="card affiliation-card shadow-sm border-0 h-100" style="cursor: pointer;" data-bs-toggle="modal" data-bs-target="#affiliationModal_{{ $affiliation->id }}">
        <!-- Card Header with Gradient -->
        <div class="card-header border-0 p-3" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
            <div class="d-flex align-items-center">
                @if($affiliation->logo)
                    <img src="{{ asset('storage/' . $affiliation->logo) }}" alt="{{ $affiliation->club_name }}" class="rounded-circle me-3" style="width: 50px; height: 50px; object-fit: cover; border: 3px solid white;">
                @else
                    <div class="rounded-circle bg-white d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
                        <i class="bi bi-building" style="font-size: 1.5rem; color: #667eea;"></i>
                    </div>
                @endif
                <div class="flex-grow-1 text-white">
                    <h6 class="mb-1 fw-bold text-truncate">{{ $affiliation->club_name }}</h6>
                    @if($isActive)
                        <span class="badge bg-success">
                            <i class="bi bi-circle-fill me-1" style="font-size: 0.5rem;"></i>Active
                        </span>
                    @else
                        <span class="badge bg-secondary">
                            <i class="bi bi-circle-fill me-1" style="font-size: 0.5rem;"></i>Inactive
                        </span>
                    @endif
                </div>
        </div>

        <!-- Card Body -->
        <div class="card-body">
            <!-- Date Info -->
            <div class="mb-3">
                <div class="d-flex align-items-center text-muted mb-1">
                    <i class="bi bi-calendar-event me-2"></i>
                    <small>Joined: {{ $affiliation->start_date->format('M d, Y') }}</small>
                </div>
                @if($affiliation->end_date)
                    <div class="d-flex align-items-center text-muted">
                        <i class="bi bi-calendar-x me-2"></i>
                        <small>Left: {{ $affiliation->end_date->format('M d, Y') }}</small>
                    </div>
                @else
                    <div class="d-flex align-items-center text-muted">
                        <i class="bi bi-calendar-check me-2"></i>
                        <small>Present</small>
                    </div>
                @endif
            </div>

            <!-- Duration -->
            @if($affiliation->formatted_duration)
                <div class="mb-3">
                    <span class="badge bg-light text-dark">
                        <i class="bi bi-hourglass-split me-1"></i>
                        {{ $affiliation->formatted_duration }}
                    </span>
                </div>
            @endif

            <!-- Location -->
            @if($affiliation->location)
                <div class="mb-3">
                    <small class="text-muted">
                        <i class="bi bi-geo-alt text-primary me-1"></i>
                        {{ $affiliation->location }}
                    </small>
                </div>
            @endif

            <!-- Stats Row -->
            <div class="row g-2 text-center">
                <div class="col-4">
                    <div class="p-2 rounded bg-light">
                        <strong class="d-block text-primary">{{ $subscriptions->count() }}</strong>
                        <small class="text-muted">Packages</small>
                    </div>
                <div class="col-4">
                    <div class="p-2 rounded bg-light">
                        <strong class="d-block text-success">{{ $skills->count() }}</strong>
                        <small class="text-muted">Skills</small>
                    </div>
                <div class="col-4">
                    <div class="p-2 rounded bg-light">
                        <strong class="d-block text-info">{{ $affiliation->duration_in_months ?? 0 }}m</strong>
                        <small class="text-muted">Duration</small>
                    </div>
            </div>

        <!-- Card Footer -->
        <div class="card-footer bg-transparent border-top">
            <div class="d-flex justify-content-between align-items-center">
                <small class="text-muted">Click for details</small>
                <i class="bi bi-chevron-right text-primary"></i>
            </div>
    </div>
