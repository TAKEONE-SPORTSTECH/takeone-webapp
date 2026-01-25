@extends('layouts.admin')

@section('admin-content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-3xl font-bold">All Clubs</h2>
            <p class="text-muted-foreground mt-1">Manage all clubs on the platform</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.platform.clubs.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-2"></i>Add New Club
            </a>
        </div>
    </div>

    <!-- Search Bar -->
    <div class="relative">
        <i class="bi bi-search position-absolute" style="left: 12px; top: 50%; transform: translateY(-50%); color: hsl(var(--muted-foreground));"></i>
        <input
            type="text"
            class="form-control ps-5"
            placeholder="Search clubs by name, location, or description..."
            id="searchInput"
        />
    </div>

    <!-- Clubs Grid -->
    @if($clubs->isEmpty())
        <div class="card p-5 text-center">
            <p class="text-muted-foreground mb-0">No clubs found. Create your first club to get started.</p>
        </div>
    @else
        <div class="row g-4" id="clubsGrid">
            @foreach($clubs as $club)
            <div class="col-md-6 col-xl-4 club-card"
                 data-name="{{ strtolower($club->club_name) }}"
                 data-location="{{ strtolower($club->location ?? '') }}"
                 data-description="{{ strtolower($club->description ?? '') }}">
                <div class="card h-100 hover-card" style="cursor: pointer;" onclick="window.location='{{ route('admin.platform.clubs.edit', $club->id) }}'">
                    <!-- Club Cover Image -->
                    <div class="position-relative" style="height: 192px; overflow: hidden;">
                        @if($club->cover_image)
                            <img src="{{ asset('storage/' . $club->cover_image) }}"
                                 alt="{{ $club->club_name }}"
                                 class="w-100 h-100 object-fit-cover club-cover-img"
                                 loading="lazy">
                        @else
                            <div class="w-100 h-100 d-flex align-items-center justify-content-center"
                                 style="background: linear-gradient(135deg, hsl(250 60% 75%), hsl(250 60% 65%));">
                                <i class="bi bi-building text-white" style="font-size: 3rem;"></i>
                            </div>
                        @endif

                        <!-- Club Logo Overlay -->
                        @if($club->logo)
                        <div class="position-absolute" style="bottom: 8px; left: 8px;">
                            <div class="rounded-circle bg-white shadow-lg border p-1" style="width: 80px; height: 80px;">
                                <img src="{{ asset('storage/' . $club->logo) }}"
                                     alt="{{ $club->club_name }} logo"
                                     class="w-100 h-100 object-fit-contain rounded-circle"
                                     loading="lazy">
                            </div>
                        </div>
                        @endif

                        <!-- Admin Badge -->
                        <div class="position-absolute" style="top: 8px; left: 8px;">
                            <span class="badge text-white" style="background: linear-gradient(135deg, hsl(250 60% 75%), hsl(250 60% 65%));">
                                Admin
                            </span>
                        </div>

                        <!-- Rating Badge -->
                        @if($club->rating)
                        <div class="position-absolute" style="top: 8px; right: 8px;">
                            <span class="badge bg-white text-dark">
                                <i class="bi bi-star-fill text-warning"></i>
                                {{ number_format($club->rating, 1) }}
                            </span>
                        </div>
                        @endif
                    </div>

                    <!-- Card Content -->
                    <div class="card-body p-4">
                        <div class="mb-3">
                            <h3 class="h5 fw-semibold mb-2 club-name-hover">{{ $club->club_name }}</h3>
                            @if($club->location)
                            <div class="d-flex align-items-center text-muted small">
                                <i class="bi bi-geo-alt me-1"></i>
                                <span class="text-truncate">{{ $club->location }}</span>
                            </div>
                            @endif
                        </div>

                        <!-- Stats Grid -->
                        <div class="row g-2 text-center small">
                            <div class="col-4">
                                <div class="p-2 rounded" style="background-color: hsl(var(--accent));">
                                    <i class="bi bi-people d-block mb-1" style="color: hsl(var(--primary));"></i>
                                    <p class="fw-semibold mb-0">{{ $club->members_count ?? 0 }}</p>
                                    <p class="text-muted mb-0" style="font-size: 0.75rem;">Members</p>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="p-2 rounded" style="background-color: hsl(var(--accent));">
                                    <i class="bi bi-box d-block mb-1" style="color: hsl(var(--primary));"></i>
                                    <p class="fw-semibold mb-0">{{ $club->packages_count ?? 0 }}</p>
                                    <p class="text-muted mb-0" style="font-size: 0.75rem;">Packages</p>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="p-2 rounded" style="background-color: hsl(var(--accent));">
                                    <i class="bi bi-star d-block mb-1" style="color: hsl(var(--primary));"></i>
                                    <p class="fw-semibold mb-0">{{ $club->trainers_count ?? 0 }}</p>
                                    <p class="text-muted mb-0" style="font-size: 0.75rem;">Trainers</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    @endif
</div>

@push('styles')
<style>
    .hover-card {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        border: 1px solid hsl(var(--border));
    }

    .hover-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 12px 28px rgba(102, 126, 234, 0.15);
    }

    .club-cover-img {
        transition: transform 0.3s ease;
    }

    .hover-card:hover .club-cover-img {
        transform: scale(1.1);
    }

    .club-name-hover {
        transition: color 0.3s ease;
    }

    .hover-card:hover .club-name-hover {
        color: hsl(var(--primary));
    }

    .space-y-6 > * + * {
        margin-top: 1.5rem;
    }
</style>
@endpush

@push('scripts')
<script>
    // Search functionality
    document.getElementById('searchInput').addEventListener('input', function(e) {
        const searchTerm = e.target.value.toLowerCase();
        const clubCards = document.querySelectorAll('.club-card');

        clubCards.forEach(card => {
            const name = card.dataset.name;
            const location = card.dataset.location;
            const description = card.dataset.description;

            const matches = name.includes(searchTerm) ||
                          location.includes(searchTerm) ||
                          description.includes(searchTerm);

            card.style.display = matches ? '' : 'none';
        });
    });
</script>
@endpush
@endsection
