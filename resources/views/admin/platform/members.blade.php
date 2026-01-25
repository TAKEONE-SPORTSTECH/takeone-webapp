@extends('layouts.admin')

@section('admin-content')
<div>
    <!-- Page Header -->
    <div class="mb-4">
        <h1 class="h2 fw-bold mb-2">All Members</h1>
        <p class="text-muted">Manage all platform members</p>
    </div>

    <!-- Search and Actions Bar -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="flex-grow-1 me-3">
            <input type="text" id="memberSearch" class="form-control" placeholder="Search members by name, phone, nationality, or gender..." value="{{ $search ?? '' }}">
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-primary">
                <i class="bi bi-person-plus me-2"></i>Add Child Member
            </button>
            <button class="btn btn-primary">
                <i class="bi bi-plus-circle me-2"></i>Create Member
            </button>
        </div>
    </div>

    <!-- Members Grid -->
    @if($members->count() > 0)
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mb-4" id="membersGrid">
            @foreach($members as $member)
                <div class="col member-card-wrapper"
                     data-member-name="{{ $member->full_name }}"
                     data-member-phone="{{ $member->formatted_mobile ?? '' }}"
                     data-member-nationality="{{ $member->nationality ?? '' }}"
                     data-member-gender="{{ $member->gender ?? '' }}">
                    <a href="{{ route('family.show', $member->id) }}" class="text-decoration-none">
                        <div class="card h-100 shadow-sm border overflow-hidden d-flex flex-column family-card">
                            <!-- Header with gradient background -->
                            <div class="p-4 pb-3" style="background: linear-gradient(135deg, {{ $member->gender == 'm' ? 'rgba(147, 51, 234, 0.1) 0%, rgba(147, 51, 234, 0.05) 50%' : 'rgba(214, 51, 132, 0.1) 0%, rgba(214, 51, 132, 0.05) 50%' }}, transparent 100%);">
                                <div class="d-flex align-items-start gap-3">
                                    <div class="position-relative">
                                        <div class="rounded-circle border border-4 border-white shadow" style="width: 80px; height: 80px; overflow: hidden; box-shadow: 0 0 0 2px {{ $member->gender == 'm' ? 'rgba(147, 51, 234, 0.3)' : 'rgba(214, 51, 132, 0.3)' }} !important;">
                                            @if($member->profile_picture)
                                                <img src="{{ asset('storage/' . $member->profile_picture) }}" alt="{{ $member->full_name }}" class="w-100 h-100" style="object-fit: cover;">
                                            @else
                                                <div class="w-100 h-100 d-flex align-items-center justify-content-center text-white fw-bold fs-4" style="background: linear-gradient(135deg, {{ $member->gender == 'm' ? '#8b5cf6 0%, #7c3aed 100%' : '#d63384 0%, #a61e4d 100%' }});">
                                                    {{ strtoupper(substr($member->full_name, 0, 1)) }}
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="flex-grow-1 min-w-0">
                                        <h5 class="fw-bold mb-2 text-truncate">{{ $member->full_name }}</h5>
                                        <div class="d-flex flex-wrap gap-2">
                                            @php
                                                $age = $member->age;
                                                $ageGroup = 'Adult';
                                                if ($age < 2) {
                                                    $ageGroup = 'Infant';
                                                } elseif ($age < 4) {
                                                    $ageGroup = 'Toddler';
                                                } elseif ($age < 6) {
                                                    $ageGroup = 'Preschooler';
                                                } elseif ($age < 13) {
                                                    $ageGroup = 'Child';
                                                } elseif ($age < 20) {
                                                    $ageGroup = 'Teenager';
                                                } elseif ($age < 40) {
                                                    $ageGroup = 'Young Adult';
                                                } elseif ($age < 60) {
                                                    $ageGroup = 'Adult';
                                                } else {
                                                    $ageGroup = 'Senior';
                                                }
                                            @endphp
                                            <span class="badge {{ $member->gender == 'm' ? 'bg-primary' : 'bg-danger' }}">{{ $ageGroup }}</span>
                                            @if($member->member_clubs_count > 0)
                                                <span class="badge bg-success">{{ $member->member_clubs_count }} {{ Str::plural('Club', $member->member_clubs_count) }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Contact Info -->
                            <div class="px-4 py-3 bg-light border-top border-bottom">
                                @if($member->mobile && isset($member->mobile['number']))
                                    <div class="d-flex align-items-center gap-2 small mb-2">
                                        <i class="bi bi-telephone-fill {{ $member->gender == 'm' ? 'text-primary' : 'text-danger' }}"></i>
                                        <span class="fw-medium text-muted">{{ $member->mobile['code'] ?? '' }} {{ $member->mobile['number'] }}</span>
                                    </div>
                                @endif
                                @if($member->email)
                                    <div class="d-flex align-items-center gap-2 small">
                                        <i class="bi bi-envelope-fill {{ $member->gender == 'm' ? 'text-primary' : 'text-danger' }}"></i>
                                        <span class="fw-medium text-muted text-truncate">{{ $member->email }}</span>
                                    </div>
                                @endif
                            </div>

                            <!-- Details -->
                            <div class="px-4 py-3 flex-grow-1">
                                <div class="row g-3 mb-3">
                                    <div class="col-6">
                                        <div class="small text-muted text-uppercase fw-medium mb-1" style="font-size: 0.7rem; letter-spacing: 0.5px;">Gender</div>
                                        <div class="fw-semibold text-muted text-capitalize">{{ $member->gender == 'm' ? 'Male' : 'Female' }}</div>
                                    </div>
                                    <div class="col-6">
                                        <div class="small text-muted text-uppercase fw-medium mb-1" style="font-size: 0.7rem; letter-spacing: 0.5px;">Age</div>
                                        <div class="fw-semibold text-muted">{{ $member->age }} years</div>
                                    </div>
                                </div>
                                <div class="row g-3 mb-3">
                                    <div class="col-6">
                                        <div class="small text-muted text-uppercase fw-medium mb-1" style="font-size: 0.7rem; letter-spacing: 0.5px;">Nationality</div>
                                        <div class="fw-semibold text-muted fs-5 nationality-display" data-iso3="{{ $member->nationality }}">{{ $member->nationality }}</div>
                                    </div>
                                    <div class="col-6">
                                        <div class="small text-muted text-uppercase fw-medium mb-1" style="font-size: 0.7rem; letter-spacing: 0.5px;">Horoscope</div>
                                        <div class="fw-semibold text-muted">
                                            @php
                                                $horoscopeSymbols = [
                                                    'Aries' => '♈',
                                                    'Taurus' => '♉',
                                                    'Gemini' => '♊',
                                                    'Cancer' => '♋',
                                                    'Leo' => '♌',
                                                    'Virgo' => '♍',
                                                    'Libra' => '♎',
                                                    'Scorpio' => '♏',
                                                    'Sagittarius' => '♐',
                                                    'Capricorn' => '♑',
                                                    'Aquarius' => '♒',
                                                    'Pisces' => '♓'
                                                ];
                                                $horoscope = $member->horoscope ?? 'N/A';
                                                $symbol = $horoscopeSymbols[$horoscope] ?? '';
                                            @endphp
                                            {{ $symbol }} {{ $horoscope }}
                                        </div>
                                    </div>
                                </div>
                                <div class="pt-2 border-top">
                                    <div class="d-flex justify-content-between align-items-center small mb-2">
                                        <span class="text-muted fw-medium">Next Birthday</span>
                                        <span class="fw-semibold text-muted">
                                            @if($member->birthdate)
                                                {{ $member->birthdate->copy()->year(now()->year)->isFuture()
                                                    ? $member->birthdate->copy()->year(now()->year)->diffForHumans(['parts' => 2, 'syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE])
                                                    : $member->birthdate->copy()->year(now()->year + 1)->diffForHumans(['parts' => 2, 'syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE]) }}
                                            @else
                                                N/A
                                            @endif
                                        </span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center small">
                                        <span class="text-muted fw-medium">Member Since</span>
                                        <span class="fw-semibold text-muted">{{ $member->created_at->format('d/m/Y') }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Footer -->
                            <div class="px-4 py-2 {{ $member->gender == 'm' ? 'bg-primary' : 'bg-danger' }} bg-opacity-10 border-top">
                                <div class="d-flex align-items-center justify-content-center gap-2 small">
                                    <span class="fw-medium text-white">
                                        PLATFORM MEMBER
                                    </span>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>

        <!-- Pagination -->
        <div class="d-flex justify-content-center mb-4">
            {{ $members->links() }}
        </div>
    @else
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body text-center py-5">
                <i class="bi bi-people text-muted" style="font-size: 4rem;"></i>
                <h5 class="mt-3 mb-2">No Members Found</h5>
                <p class="text-muted mb-0">
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
