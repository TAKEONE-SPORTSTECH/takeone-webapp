@extends('layouts.admin')

@section('admin-content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-wrap gap-3 items-center justify-between">
        <div>
            <h2 class="text-3xl font-bold">All Clubs</h2>
            <p class="text-muted-foreground mt-1">Manage all clubs on the platform</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.platform.clubs.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg mr-2"></i>Add New Club
            </a>
        </div>
    </div>

    <!-- Search Bar -->
    <div class="relative">
        <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground"></i>
        <input
            type="text"
            class="form-control pl-10"
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
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4" id="clubsGrid">
            @foreach($clubs as $club)
            <div class="club-card"
                 data-name="{{ strtolower($club->club_name) }}"
                 data-location="{{ strtolower($club->location ?? '') }}"
                 data-description="{{ strtolower($club->description ?? '') }}">
                <div class="card h-full hover-card cursor-pointer" onclick="window.location='{{ route('admin.platform.clubs.edit', $club->id) }}'">
                    <!-- Club Cover Image -->
                    <div class="relative h-48 overflow-hidden">
                        @if($club->cover_image)
                            <img src="{{ asset('storage/' . $club->cover_image) }}"
                                 alt="{{ $club->club_name }}"
                                 class="w-full h-full object-cover club-cover-img"
                                 loading="lazy">
                        @else
                            <div class="w-full h-full flex items-center justify-center"
                                 style="background: linear-gradient(135deg, hsl(250 60% 75%), hsl(250 60% 65%));">
                                <i class="bi bi-building text-white text-5xl"></i>
                            </div>
                        @endif

                        <!-- Club Logo Overlay -->
                        @if($club->logo)
                        <div class="absolute bottom-2 left-2">
                            <div class="rounded-full bg-white shadow-lg border p-1 w-20 h-20">
                                <img src="{{ asset('storage/' . $club->logo) }}"
                                     alt="{{ $club->club_name }} logo"
                                     class="w-full h-full object-contain rounded-full"
                                     loading="lazy">
                            </div>
                        </div>
                        @endif

                        <!-- Admin Badge -->
                        <div class="absolute top-2 left-2">
                            <span class="badge text-white" style="background: linear-gradient(135deg, hsl(250 60% 75%), hsl(250 60% 65%));">
                                Admin
                            </span>
                        </div>

                        <!-- Rating Badge -->
                        @if($club->rating)
                        <div class="absolute top-2 right-2">
                            <span class="badge bg-white text-foreground">
                                <i class="bi bi-star-fill text-warning"></i>
                                {{ number_format($club->rating, 1) }}
                            </span>
                        </div>
                        @endif
                    </div>

                    <!-- Card Content -->
                    <div class="card-body p-4">
                        <div class="mb-3">
                            <h3 class="text-lg font-semibold mb-2 club-name-hover">{{ $club->club_name }}</h3>
                            @if($club->location)
                            <div class="flex items-center text-muted-foreground text-sm">
                                <i class="bi bi-geo-alt mr-1"></i>
                                <span class="truncate">{{ $club->location }}</span>
                            </div>
                            @endif
                        </div>

                        <!-- Stats Grid -->
                        <div class="grid grid-cols-3 gap-2 text-center text-sm">
                            <div class="p-2 rounded bg-accent">
                                <i class="bi bi-people block mb-1 text-primary"></i>
                                <p class="font-semibold mb-0">{{ $club->members_count ?? 0 }}</p>
                                <p class="text-muted-foreground mb-0 text-xs">Members</p>
                            </div>
                            <div class="p-2 rounded bg-accent">
                                <i class="bi bi-box block mb-1 text-primary"></i>
                                <p class="font-semibold mb-0">{{ $club->packages_count ?? 0 }}</p>
                                <p class="text-muted-foreground mb-0 text-xs">Packages</p>
                            </div>
                            <div class="p-2 rounded bg-accent">
                                <i class="bi bi-star block mb-1 text-primary"></i>
                                <p class="font-semibold mb-0">{{ $club->trainers_count ?? 0 }}</p>
                                <p class="text-muted-foreground mb-0 text-xs">Trainers</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    @endif
</div>

{{-- Styles moved to app.css (Phase 6) --}}

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
