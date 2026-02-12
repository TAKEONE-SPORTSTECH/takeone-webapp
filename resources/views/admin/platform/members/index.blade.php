@extends('layouts.admin')

@section('admin-content')
<div x-data>
    <!-- Page Header -->
    <div class="mb-4">
        <h1 class="text-2xl font-bold mb-2">All Members</h1>
        <p class="text-muted-foreground">Manage all platform members</p>
    </div>

    <!-- Search and Actions Bar -->
    <div class="flex justify-between items-center mb-4">
        <div class="grow mr-3">
            <input type="text" id="memberSearch" class="form-control" placeholder="Search members by name, phone, nationality, or gender..." value="{{ $search ?? '' }}">
        </div>
        <div class="flex gap-2">
            <button class="btn btn-outline-primary" @click="$dispatch('open-member-create-modal')">
                <i class="bi bi-person-plus mr-2"></i>Add Child Member
            </button>
            <button class="btn btn-primary" @click="$dispatch('open-member-create-modal')">
                <i class="bi bi-plus-circle mr-2"></i>Add Member
            </button>
        </div>
    </div>

    <!-- Members Grid -->
    @if($members->count() > 0)
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-4" id="membersGrid">
            @foreach($members as $member)
                <x-member-card :member="$member" :href="route('member.show', $member->id)" />
            @endforeach
        </div>

        <!-- Pagination -->
        <div class="flex justify-center mb-4">
            {{ $members->links() }}
        </div>
    @else
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body text-center py-12">
                <i class="bi bi-people text-muted-foreground text-6xl"></i>
                <h5 class="mt-3 mb-2">No Members Found</h5>
                <p class="text-muted-foreground mb-0">
                    @if($search)
                        No members match your search criteria.
                    @else
                        No members registered on the platform yet.
                    @endif
                </p>
            </div>
        </div>
    @endif
</div>

{{-- Member Create Modal --}}
<x-profile-modal
    mode="create"
    title="Add Platform Member"
    subtitle="Fill in the details to add a new platform member"
    :showPasswordFields="true"
    :formAction="route('family.store')"
    formMethod="POST"
/>

@push('styles')
<style>
    /* Family Card Hover Effects */
    .family-card {
        transition: all 0.3s ease-in-out;
        cursor: pointer;
    }

    .family-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 12px 24px rgba(0, 0, 0, 0.15) !important;
    }

    .family-card:hover .rounded-circle {
        transform: scale(1.1);
        transition: transform 0.3s ease-in-out;
    }

    /* Remove underline from card links */
    a.text-decoration-none:hover .family-card {
        text-decoration: none;
    }

    .member-card-wrapper {
        transition: opacity 0.3s ease;
    }

    .member-card-wrapper.hidden {
        display: none;
    }

    /* Improve image quality */
    .rounded-circle img {
        image-rendering: -webkit-optimize-contrast;
        image-rendering: crisp-edges;
        backface-visibility: hidden;
        -webkit-backface-visibility: hidden;
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
    }
</style>
@endpush

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Load countries from JSON file
        fetch('/data/countries.json')
            .then(response => response.json())
            .then(countries => {
                // Convert all nationality displays from ISO3 to country name with flag
                document.querySelectorAll('.nationality-display').forEach(element => {
                    const iso3Code = element.getAttribute('data-iso3');
                    if (!iso3Code) return;

                    const country = countries.find(c => c.iso3 === iso3Code);
                    if (country) {
                        // Get flag emoji from ISO2 code
                        const flagEmoji = country.iso2
                            .toUpperCase()
                            .split('')
                            .map(char => String.fromCodePoint(127397 + char.charCodeAt(0)))
                            .join('');

                        element.textContent = `${flagEmoji} ${country.iso2.toUpperCase()}`;
                    }
                });
            })
            .catch(error => console.error('Error loading countries:', error));

        // Real-time search filtering
        document.getElementById('memberSearch').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const memberCards = document.querySelectorAll('.member-card-wrapper');

            memberCards.forEach(function(card) {
                const memberName = card.getAttribute('data-member-name').toLowerCase();
                const memberPhone = card.getAttribute('data-member-phone').toLowerCase();
                const memberNationality = card.getAttribute('data-member-nationality').toLowerCase();
                const memberGender = card.getAttribute('data-member-gender').toLowerCase();

                if (memberName.includes(searchTerm) ||
                    memberPhone.includes(searchTerm) ||
                    memberNationality.includes(searchTerm) ||
                    memberGender.includes(searchTerm)) {
                    card.classList.remove('hidden');
                } else {
                    card.classList.add('hidden');
                }
            });
        });
    });
</script>
@endpush
@endsection
