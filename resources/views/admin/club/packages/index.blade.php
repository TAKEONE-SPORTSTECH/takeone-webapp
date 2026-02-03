@extends('layouts.admin-club')

@section('club-admin-content')
<div>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="h3 fw-bold mb-1">Packages</h2>
            <p class="text-muted mb-0">Manage membership packages</p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPackageModal">
            <i class="bi bi-plus-lg me-2"></i>Add Package
        </button>
    </div>

    @if(isset($packages) && count($packages) > 0)
    <div class="row g-4">
        @foreach($packages as $package)
        <div class="col-md-6 col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <h5 class="fw-semibold mb-1">{{ $package->name }}</h5>
                            <span class="badge bg-primary">{{ $package->duration_days ?? 30 }} days</span>
                        </div>
                        @if($package->is_popular ?? false)
                        <span class="badge bg-warning text-dark">Popular</span>
                        @endif
                    </div>

                    <p class="text-muted small mb-3">{{ Str::limit($package->description, 100) }}</p>

                    <div class="mb-3">
                        <span class="h4 fw-bold text-primary">{{ $club->currency ?? 'BHD' }} {{ number_format($package->price, 2) }}</span>
                        <span class="text-muted">/ {{ $package->duration_days ?? 30 }} days</span>
                    </div>

                    @if($package->features)
                    <ul class="list-unstyled small mb-3">
                        @foreach(json_decode($package->features, true) ?? [] as $feature)
                        <li class="mb-1">
                            <i class="bi bi-check-circle text-success me-2"></i>{{ $feature }}
                        </li>
                        @endforeach
                    </ul>
                    @endif

                    <div class="d-flex gap-2">
                        <button class="btn btn-sm btn-outline-primary flex-grow-1">
                            <i class="bi bi-pencil me-1"></i>Edit
                        </button>
                        <button class="btn btn-sm btn-outline-danger">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>
    @else
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
            <i class="bi bi-box text-muted" style="font-size: 4rem;"></i>
            <h5 class="mt-3 mb-2">No packages yet</h5>
            <p class="text-muted mb-3">Create membership packages for your club</p>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPackageModal">
                <i class="bi bi-plus-lg me-2"></i>Add Package
            </button>
        </div>
    </div>
    @endif
</div>

@include('admin.club.packages.add')
@endsection
