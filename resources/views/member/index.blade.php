@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">Members</h1>
    </div>

    <!-- Family Members Card Grid -->
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-4 g-4 mb-5">


        <!-- Dependents Cards -->
        @foreach($dependents as $relationship)
            <div class="col">
                <a href="{{ route('member.show', $relationship->dependent->id) }}" class="text-decoration-none">
                    <div class="card h-100 shadow-sm border overflow-hidden d-flex flex-column family-card">
                        <!-- Header with gradient background -->
                        <div class="p-4 pb-3" style="background: linear-gradient(135deg, {{ $relationship->dependent->gender == 'm' ? 'rgba(147, 51, 234, 0.1) 0%, rgba(147, 51, 234, 0.05) 50%' : 'rgba(214, 51, 132, 0.1) 0%, rgba(214, 51, 132, 0.05) 50%' }}, transparent 100%);">
                        <div class="d-flex align-items-start gap-3">
                            <div class="position-relative">
                                <div class="rounded-circle border border-4 border-white shadow" style="width: 80px; height: 80px; overflow: hidden; box-shadow: 0 0 0 2px {{ $relationship->dependent->gender == 'm' ? 'rgba(147, 51, 234, 0.3)' : 'rgba(214, 51, 132, 0.3)' }} !important;">
                                @if($relationship->dependent->profile_picture)
                                    <img src="{{ asset('storage/' . $relationship->dependent->profile_picture) }}" alt="{{ $relationship->dependent->full_name }}" class="w-100 h-100" style="object-fit: cover;">
                                @else
                                    <div class="w-100 h-100 d-flex align-items-center justify-content-center text-white fw-bold fs-4" style="background: linear-gradient(135deg, {{ $relationship->dependent->gender == 'm' ? '#8b5cf6 0%, #7c3aed 100%' : '#d63384 0%, #a61e4d 100%' }});">
                                        {{ strtoupper(substr($relationship->dependent->full_name, 0, 1)) }}
                                    </div>
                                @endif
                                </div>
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <h5 class="fw-bold mb-2 text-truncate">{{ $relationship->dependent->full_name }}</h5>
                                <div class="d-flex flex-wrap gap-2">
                                    @php
                                        $age = $relationship->dependent->age;
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
                                    <span class="badge {{ $relationship->dependent->gender == 'm' ? 'bg-primary' : 'bg-danger' }}">{{ $ageGroup }}</span>
                                    <span class="badge bg-success">Active</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Contact Info -->
                    <div class="px-4 py-3 bg-light border-top border-bottom">
                        <div class="d-flex align-items-center gap-2 small mb-2">
                            <i class="bi bi-telephone-fill {{ $relationship->dependent->gender == 'm' ? 'text-primary' : 'text-danger' }}"></i>
                            <span class="fw-medium text-muted">{{ $relationship->dependent->mobile_formatted ?: ($user->mobile_formatted ?: 'Not provided') }}</span>
                            @if(!$relationship->dependent->mobile_formatted && $user->mobile_formatted)
                                <span class="badge {{ $relationship->dependent->gender == 'm' ? 'bg-info' : 'bg-danger' }} {{ $relationship->dependent->gender == 'm' ? 'text-dark' : 'text-white' }} ms-auto">Guardian's</span>
                            @endif
                        </div>
                        @if($relationship->dependent->email)
                        <div class="d-flex align-items-center gap-2 small">
                            <i class="bi bi-envelope-fill {{ $relationship->dependent->gender == 'm' ? 'text-primary' : 'text-danger' }}"></i>
                            <span class="fw-medium text-muted text-truncate">{{ $relationship->dependent->email }}</span>
                        </div>
                        @elseif($user->email)
                        <div class="d-flex align-items-center gap-2 small">
                            <i class="bi bi-envelope-fill {{ $relationship->dependent->gender == 'm' ? 'text-primary' : 'text-danger' }}"></i>
                            <span class="fw-medium text-muted text-truncate">{{ $user->email }}</span>
                            <span class="badge {{ $relationship->dependent->gender == 'm' ? 'bg-info' : 'bg-danger' }} {{ $relationship->dependent->gender == 'm' ? 'text-dark' : 'text-white' }} ms-auto">Guardian's</span>
                        </div>
                        @endif
                    </div>

                    <!-- Details -->
                    <div class="px-4 py-3 flex-grow-1">
                        <div class="row g-3 mb-3">
                            <div class="col-6">
                                <div class="small text-muted text-uppercase fw-medium mb-1" style="font-size: 0.7rem; letter-spacing: 0.5px;">Gender</div>
                                <div class="fw-semibold text-muted text-capitalize">{{ $relationship->dependent->gender == 'm' ? 'Male' : 'Female' }}</div>
                            </div>
                            <div class="col-6">
                                <div class="small text-muted text-uppercase fw-medium mb-1" style="font-size: 0.7rem; letter-spacing: 0.5px;">Age</div>
                                <div class="fw-semibold text-muted">{{ $relationship->dependent->age }} years</div>
                            </div>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-6">
                                <div class="small text-muted text-uppercase fw-medium mb-1" style="font-size: 0.7rem; letter-spacing: 0.5px;">Nationality</div>
                                <div class="fw-semibold text-muted fs-5 nationality-display" data-iso3="{{ $relationship->dependent->nationality }}">{{ $relationship->dependent->nationality }}</div>
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
                                        $horoscope = $relationship->dependent->horoscope ?? 'N/A';
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
                                    @if($relationship->dependent->birthdate)
                                        {{ $relationship->dependent->birthdate->copy()->year(now()->year)->isFuture()
                                            ? $relationship->dependent->birthdate->copy()->year(now()->year)->diffForHumans(['parts' => 2, 'syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE])
                                            : $relationship->dependent->birthdate->copy()->year(now()->year + 1)->diffForHumans(['parts' => 2, 'syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE]) }}
                                    @else
                                        N/A
                                    @endif
                                </span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center small">
                                <span class="text-muted fw-medium">Member Since</span>
                                <span class="fw-semibold text-muted">{{ $relationship->dependent->created_at->format('d/m/Y') }}</span>
                            </div>
                    </div>
                </div>

                <!-- Sponsor/Guardian Info - Footer -->
                <div class="px-4 py-2 {{ $relationship->dependent->gender == 'm' ? 'bg-primary' : 'bg-danger' }} bg-opacity-10 border-top">
                    <div class="d-flex align-items-center justify-content-center gap-2 small">
                        <span class="fw-medium text-white">
                            {{ $relationship->relationship_type === 'spouse' ? 'WIFE' : strtoupper($relationship->relationship_type) }}
                        </span>
                    </div>
                </div>
                </div>
            </a>
        </div>
        @endforeach

        <!-- Add New Family Member Card -->
        <div class="col">
            <a href="{{ route('members.create') }}" class="text-decoration-none">
                <div class="card h-100 shadow-sm border-dashed add-card">
                    <div class="card-body text-center d-flex flex-column justify-content-center align-items-center" style="height: 100%; cursor: pointer;">
                        <div class="mb-3">
                            <i class="bi bi-plus-circle" style="font-size: 3rem;"></i>
                        </div>
                        <h5 class="card-title text-muted">Add Member</h5>
                    </div>
                </div>
            </a>
        </div>
    </div>


</div>

<style>
    .border-dashed {
        border-style: dashed !important;
        border-width: 2px !important;
        border-color: #dee2e6 !important;
    }

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

    /* Add Card Hover Effects */
    .add-card {
        transition: all 0.3s ease-in-out;
    }

    .add-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 12px 24px rgba(0, 0, 0, 0.15) !important;
        border-color: #0d6efd !important;
    }

    .add-card:hover .bi-plus-circle {
        color: #0d6efd;
        transition: color 0.3s ease-in-out;
    }

    .add-card:hover h5 {
        color: #0d6efd;
        transition: color 0.3s ease-in-out;
    }
</style>

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
    });
</script>
@endsection
