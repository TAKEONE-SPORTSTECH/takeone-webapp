@extends('layouts.admin-club')

@section('club-admin-content')
<div class="space-y-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="h3 fw-bold mb-1">Club Details</h2>
            <p class="text-muted mb-0">Manage your club information</p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#editClubModal">
            <i class="bi bi-pencil me-2"></i>Edit Details
        </button>
    </div>

    <div class="row g-4">
        <!-- Basic Info Card -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0">
                    <h5 class="fw-semibold mb-0">Basic Information</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Club Name</label>
                            <p class="fw-semibold mb-0">{{ $club->club_name }}</p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Slug</label>
                            <p class="fw-semibold mb-0">{{ $club->slug }}</p>
                        </div>
                        <div class="col-12">
                            <label class="form-label text-muted small">Description</label>
                            <p class="mb-0">{{ $club->description ?? 'No description provided' }}</p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Email</label>
                            <p class="mb-0">{{ $club->email ?? 'Not set' }}</p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">Phone</label>
                            <p class="mb-0">
                                @if($club->phone)
                                    {{ is_array($club->phone) ? implode(', ', $club->phone) : $club->phone }}
                                @else
                                    Not set
                                @endif
                            </p>
                        </div>
                        <div class="col-12">
                            <label class="form-label text-muted small">Address</label>
                            <p class="mb-0">{{ $club->address ?? 'Not set' }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Logo & Settings Card -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0">
                    <h5 class="fw-semibold mb-0">Club Logo</h5>
                </div>
                <div class="card-body text-center">
                    @if($club->logo)
                        <img src="{{ asset('storage/' . $club->logo) }}" alt="{{ $club->club_name }}" class="img-fluid rounded mb-3" style="max-height: 150px;">
                    @else
                        <div class="bg-light rounded d-flex align-items-center justify-content-center mb-3" style="height: 150px;">
                            <i class="bi bi-building text-muted" style="font-size: 3rem;"></i>
                        </div>
                    @endif
                    <button class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-upload me-1"></i>Change Logo
                    </button>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0">
                    <h5 class="fw-semibold mb-0">Settings</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label text-muted small">Currency</label>
                        <p class="fw-semibold mb-0">{{ $club->currency ?? 'BHD' }}</p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Timezone</label>
                        <p class="fw-semibold mb-0">{{ $club->timezone ?? 'Asia/Bahrain' }}</p>
                    </div>
                    <div class="mb-0">
                        <label class="form-label text-muted small">Enrollment Fee</label>
                        <p class="fw-semibold mb-0">{{ $club->currency ?? 'BHD' }} {{ number_format($club->enrollment_fee ?? 0, 2) }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Location Card -->
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0">
                    <h5 class="fw-semibold mb-0">Location</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label text-muted small">GPS Latitude</label>
                            <p class="fw-semibold mb-0">{{ $club->gps_lat ?? 'Not set' }}</p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small">GPS Longitude</label>
                            <p class="fw-semibold mb-0">{{ $club->gps_long ?? 'Not set' }}</p>
                        </div>
                    </div>
                    @if($club->gps_lat && $club->gps_long)
                    <div class="mt-3">
                        <div id="clubMap" style="height: 300px; border-radius: 0.5rem;"></div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
