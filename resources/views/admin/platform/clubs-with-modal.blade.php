@extends('layouts.admin')

@section('admin-content')
<div>
    <!-- Page Header -->
    <div class="mb-4">
        <h1 class="h2 fw-bold mb-2">All Clubs</h1>
        <p class="text-muted">Manage all clubs on the platform</p>
    </div>

    <!-- Search and Actions Bar -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="flex-grow-1 me-3">
            <input type="text" id="clubSearch" class="form-control" placeholder="Search clubs by name, location, or description..." value="{{ $search ?? '' }}">
        </div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#clubModal" onclick="openClubModal('create')">
            <i class="bi bi-plus-circle me-2"></i>Add New Club
        </button>
    </div>

    <!-- Clubs Grid -->
    @if($clubs->count() > 0)
        <div class="row g-4 mb-4" id="clubsGrid">
            @foreach($clubs as $club)
                <div class="col-md-6 col-xl-4 club-card-wrapper"
                     data-club-name="{{ $club->club_name }}"
                     data-club-address="{{ $club->address ?? '' }}"
                     data-club-owner="{{ $club->owner->full_name ?? '' }}">
                    <div class="card border shadow-sm overflow-hidden club-card" style="border-radius: 0; cursor: pointer; transition: all 0.3s ease;">
                        <!-- Cover Image -->
                        <div class="position-relative overflow-hidden" style="height: 192px;" onclick="window.location.href='{{ route('admin.club.dashboard', $club) }}'">
                            @if($club->cover_image)
                                <img src="{{ asset('storage/' . $club->cover_image) }}" alt="{{ $club->club_name }}" loading="lazy" class="w-100 h-100 club-cover-img" style="object-fit: cover; transition: transform 0.3s ease;">
                            @else
                                <div class="w-100 h-100 d-flex align-items-center justify-content-center" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                    <i class="bi bi-image text-white" style="font-size: 3rem; opacity: 0.3;"></i>
                                </div>
                            @endif

                            <!-- Club Logo - Bottom Left -->
                            <div class="position-absolute" style="bottom: 8px; left: 8px;">
                                <div class="bg-white shadow border p-0.5" style="width: 80px; height: 80px; border-radius: 50%; border-color: rgba(0,0,0,0.1) !important;">
                                    @if($club->logo)
                                        <img src="{{ asset('storage/' . $club->logo) }}" alt="{{ $club->club_name }} logo" loading="lazy" class="w-100 h-100 rounded-circle" style="object-fit: contain;">
                                    @else
                                        <div class="w-100 h-100 rounded-circle bg-primary d-flex align-items-center justify-content-center">
                                            <span class="text-white fw-bold fs-4">{{ substr($club->club_name, 0, 1) }}</span>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <!-- Admin Badge - Top Left -->
                            <div class="position-absolute" style="top: 8px; left: 8px;">
                                <span class="badge text-white px-3 py-1" style="background-color: rgba(147, 51, 234, 0.9); border-radius: 9999px; font-size: 0.75rem; font-weight: 600;">Admin</span>
                            </div>

                            <!-- Edit Button - Top Right -->
                            <div class="position-absolute" style="top: 8px; right: 8px;">
                                <button type="button"
                                        class="btn btn-sm btn-light shadow-sm"
                                        onclick="event.stopPropagation(); openClubModal('edit', {{ $club->id }})"
                                        title="Edit Club">
                                    <i class="bi bi-pencil"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Card Body -->
                        <div class="p-4" style="background-color: white;" onclick="window.location.href='{{ route('admin.club.dashboard', $club) }}'">
                            <div class="mb-3">
                                <!-- Club Name -->
                                <h3 class="fw-semibold mb-2 club-title" style="font-size: 1.125rem; color: #1f2937; transition: color 0.3s ease;">{{ $club->club_name }}</h3>

                                <!-- Address -->
                                @if($club->address)
                                    <div class="d-flex align-items-center text-muted" style="font-size: 0.875rem;">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-1 flex-shrink-0">
                                            <path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"></path>
                                            <circle cx="12" cy="10" r="3"></circle>
                                        </svg>
                                        <span class="text-truncate">{{ $club->address }}</span>
                                    </div>
                                @endif
                            </div>

                            <!-- Stats Grid -->
                            <div class="row g-2 text-center" style="font-size: 0.75rem;">
                                <div class="col-4">
                                    <div class="p-2 rounded" style="background-color: rgba(147, 51, 234, 0.05);">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mx-auto mb-1" style="color: hsl(var(--primary));">
                                            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                                            <circle cx="9" cy="7" r="4"></circle>
                                            <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
                                            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                        </svg>
                                        <p class="fw-semibold mb-0" style="color: #1f2937;">{{ $club->members_count }}</p>
                                        <p class="text-muted mb-0">Members</p>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="p-2 rounded" style="background-color: rgba(147, 51, 234, 0.05);">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mx-auto mb-1" style="color: hsl(var(--primary));">
                                            <path d="M14.4 14.4 9.6 9.6"></path>
                                            <path d="M18.657 21.485a2 2 0 1 1-2.829-2.828l-1.767 1.768a2 2 0 1 1-2.829-2.829l6.364-6.364a2 2 0 1 1 2.829 2.829l-1.768 1.767a2 2 0 1 1 2.828 2.829z"></path>
                                            <path d="m21.5 21.5-1.4-1.4"></path>
                                            <path d="M3.9 3.9 2.5 2.5"></path>
                                            <path d="M6.404 12.768a2 2 0 1 1-2.829-2.829l1.768-1.767a2 2 0 1 1-2.828-2.829l2.828-2.828a2 2 0 1 1 2.829 2.828l1.767-1.768a2 2 0 1 1 2.829 2.829z"></path>
                                        </svg>
                                        <p class="fw-semibold mb-0" style="color: #1f2937;">{{ $club->packages_count }}</p>
                                        <p class="text-muted mb-0">Packages</p>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="p-2 rounded" style="background-color: rgba(147, 51, 234, 0.05);">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mx-auto mb-1" style="color: hsl(var(--primary));">
                                            <path d="M11.525 2.295a.53.53 0 0 1 .95 0l2.31 4.679a2.123 2.123 0 0 0 1.595 1.16l5.166.756a.53.53 0 0 1 .294.904l-3.736 3.638a2.123 2.123 0 0 0-.611 1.878l.882 5.14a.53.53 0 0 1-.771.56l-4.618-2.428a2.122 2.122 0 0 0-1.973 0L6.396 21.01a.53.53 0 0 1-.77-.56l.881-5.139a2.122 2.122 0 0 0-.611-1.879L2.16 9.795a.53.53 0 0 1 .294-.906l5.165-.755a2.122 2.122 0 0 0 1.597-1.16z"></path>
                                        </svg>
                                        <p class="fw-semibold mb-0" style="color: #1f2937;">{{ $club->instructors_count }}</p>
                                        <p class="text-muted mb-0">Trainers</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Pagination -->
        <div class="d-flex justify-content-center mb-4">
            {{ $clubs->links() }}
        </div>
    @else
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body text-center py-5">
                <i class="bi bi-building text-muted" style="font-size: 4rem;"></i>
                <h5 class="mt-3 mb-2">No Clubs Found</h5>
                <p class="text-muted mb-4">
                    @if($search)
                        No clubs match your search criteria.
                    @else
                        Get started by creating your first club.
                    @endif
                </p>
                @if(!$search)
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#clubModal" onclick="openClubModal('create')">
                        <i class="bi bi-plus-circle me-2"></i>Add New Club
                    </button>
                @endif
            </div>
        </div>
    @endif
</div>

<!-- Include Club Modal -->
<x-club-modal mode="create" />

<!-- Include User Picker Modal -->
<x-user-picker-modal />

@push('styles')
<style>
    .club-card {
        transition: all 0.3s ease-in-out;
    }

    .club-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04) !important;
    }

    .club-card:hover .club-cover-img {
        transform: scale(1.1);
    }

    .club-card:hover .club-title {
        color: hsl(var(--primary)) !important;
    }

    .club-card-wrapper {
        transition: opacity 0.3s ease;
    }

    .club-card-wrapper.hidden {
        display: none;
    }
</style>
@endpush

@push('scripts')
<script>
    // Real-time search filtering
    document.getElementById('clubSearch').addEventListener('input', function(e) {
        const searchTerm = e.target.value.toLowerCase();
        const clubCards = document.querySelectorAll('.club-card-wrapper');

        clubCards.forEach(function(card) {
            const clubName = card.getAttribute('data-club-name').toLowerCase();
            const clubAddress = card.getAttribute('data-club-address').toLowerCase();
            const clubOwner = card.getAttribute('data-club-owner').toLowerCase();

            if (clubName.includes(searchTerm) || clubAddress.includes(searchTerm) || clubOwner.includes(searchTerm)) {
                card.classList.remove('hidden');
            } else {
                card.classList.add('hidden');
            }
        });
    });

    // Open club modal
    async function openClubModal(mode, clubId = null) {
        const modal = document.getElementById('clubModal');
        const form = document.getElementById('clubForm');

        if (!modal || !form) return;

        // Set mode
        form.dataset.mode = mode;
        form.dataset.clubId = clubId || '';

        // Update modal title
        const modalTitle = modal.querySelector('.modal-title');
        if (modalTitle) {
            modalTitle.textContent = mode === 'edit' ? 'Edit Club' : 'Create New Club';
        }

        // Update submit button text
        const submitBtn = document.getElementById('submitBtn');
        if (submitBtn) {
            submitBtn.innerHTML = mode === 'edit'
                ? '<i class="bi bi-check-circle me-2"></i>Update Club'
                : '<i class="bi bi-check-circle me-2"></i>Create Club';
        }

        // If edit mode, load club data
        if (mode === 'edit' && clubId) {
            try {
                const response = await fetch(`/admin/api/clubs/${clubId}`);
                if (response.ok) {
                    const club = await response.json();
                    populateFormWithClubData(club);
                }
            } catch (error) {
                console.error('Error loading club data:', error);
            }
        } else {
            // Reset form for create mode
            form.reset();
        }
    }

    // Populate form with club data (for edit mode)
    function populateFormWithClubData(club) {
        // This would populate all form fields with club data
        // Implementation depends on the exact structure
        console.log('Loading club data:', club);

        // Example: populate basic fields
        if (club.club_name) document.getElementById('club_name').value = club.club_name;
        if (club.slug) document.getElementById('slug').value = club.slug;
        // ... populate other fields
    }
</script>
@endpush
@endsection
