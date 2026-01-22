@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">Family</h1>
    </div>

    <!-- Family Members Card Grid -->
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-4 g-4 mb-5">


        <!-- Dependents Cards -->
        @foreach($dependents as $relationship)
            <div class="col">
                <a href="{{ route('family.show', $relationship->dependent->id) }}" class="text-decoration-none">
                    <div class="card h-100 shadow-sm border overflow-hidden d-flex flex-column family-card">
                        <!-- Header with gradient background -->
                        <div class="p-4 pb-3" style="background: linear-gradient(135deg, {{ $relationship->dependent->gender == 'm' ? 'rgba(147, 51, 234, 0.1) 0%, rgba(147, 51, 234, 0.05) 50%' : 'rgba(214, 51, 132, 0.1) 0%, rgba(214, 51, 132, 0.05) 50%' }}, transparent 100%);">
                        <div class="d-flex align-items-start gap-3">
                            <div class="position-relative">
                                <div class="rounded-circle border border-4 border-white shadow" style="width: 80px; height: 80px; overflow: hidden; box-shadow: 0 0 0 2px {{ $relationship->dependent->gender == 'm' ? 'rgba(147, 51, 234, 0.3)' : 'rgba(214, 51, 132, 0.3)' }} !important;">
                                @if($relationship->dependent->media_gallery[0] ?? false)
                                    <img src="{{ $relationship->dependent->media_gallery[0] }}" alt="{{ $relationship->dependent->full_name }}" class="w-100 h-100" style="object-fit: cover;">
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
            <div class="card h-100 shadow-sm border-dashed add-card">
                <div class="card-body text-center text-decoration-none d-flex flex-column justify-content-center align-items-center" style="height: 100%; cursor: pointer;" data-bs-toggle="modal" data-bs-target="#addFamilyMemberModal">
                    <div class="mb-3">
                        <i class="bi bi-plus-circle" style="font-size: 3rem;"></i>
                    </div>
                    <h5 class="card-title text-muted">Add Family Member</h5>
                </div>
            </div>
        </div>
    </div>


</div>

<!-- Add Family Member Modal -->
<div class="modal fade" id="addFamilyMemberModal" tabindex="-1" aria-labelledby="addFamilyMemberModalLabel" aria-hidden="true" data-bs-focus="false">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addFamilyMemberModalLabel">Add Family Member</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addFamilyMemberForm" method="POST" action="{{ route('family.store') }}">
                    @csrf

                    <div class="mb-3">
                        <label for="full_name" class="form-label">Full Name</label>
                        <input type="text" class="form-control @error('full_name') is-invalid @enderror" id="full_name" name="full_name" value="{{ old('full_name') }}" required>
                        @error('full_name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address <span class="text-muted">(Optional for children)</span></label>
                        <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email') }}">
                        @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="mobile" class="form-label">Mobile Number</label>
                        <div class="input-group" onclick="event.stopPropagation()">
                            <button class="btn btn-outline-secondary dropdown-toggle country-dropdown-btn d-flex align-items-center" type="button" id="country_codeDropdown" data-bs-toggle="dropdown" data-bs-auto-close="false" aria-expanded="false">
                                <span class="fi fi-bh me-2" id="country_codeSelectedFlag"></span>
                                <span class="country-label" id="country_codeSelectedCountry">{{ old('mobile_code', '+973') }}</span>
                            </button>
                            <div class="dropdown-menu p-2" aria-labelledby="country_codeDropdown" style="min-width: 200px;" onclick="event.stopPropagation()">
                                <input type="text" class="form-control form-control-sm mb-2" placeholder="Search country..." id="country_codeSearch" onkeydown="event.stopPropagation()" tabindex="0">
                                <div class="country-list" id="country_codeList" style="max-height: 300px; overflow-y: auto;"></div>
                            </div>
                            <input type="hidden" id="country_code" name="mobile_code" value="{{ old('mobile_code', '+973') }}">
                            <input id="mobile_number" type="tel" class="form-control @error('mobile') is-invalid @enderror" name="mobile" value="{{ old('mobile') }}" autocomplete="tel" placeholder="Phone number">
                        </div>
                        @error('mobile')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                        @error('mobile_code')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="gender" class="form-label">Gender</label>
                            <select class="form-select @error('gender') is-invalid @enderror" id="gender" name="gender" required>
                                <option value="">Select Gender</option>
                                <option value="m" {{ old('gender') == 'm' ? 'selected' : '' }}>Male</option>
                                <option value="f" {{ old('gender') == 'f' ? 'selected' : '' }}>Female</option>
                            </select>
                            @error('gender')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="birthdate" class="form-label">Birthdate</label>
                            <input type="date" class="form-control @error('birthdate') is-invalid @enderror" id="birthdate" name="birthdate" value="{{ old('birthdate') }}" required>
                            @error('birthdate')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="blood_type" class="form-label">Blood Type</label>
                            <select class="form-select @error('blood_type') is-invalid @enderror" id="blood_type" name="blood_type">
                                <option value="">Select Blood Type</option>
                                <option value="A+" {{ old('blood_type') == 'A+' ? 'selected' : '' }}>A+</option>
                                <option value="A-" {{ old('blood_type') == 'A-' ? 'selected' : '' }}>A-</option>
                                <option value="B+" {{ old('blood_type') == 'B+' ? 'selected' : '' }}>B+</option>
                                <option value="B-" {{ old('blood_type') == 'B-' ? 'selected' : '' }}>B-</option>
                                <option value="AB+" {{ old('blood_type') == 'AB+' ? 'selected' : '' }}>AB+</option>
                                <option value="AB-" {{ old('blood_type') == 'AB-' ? 'selected' : '' }}>AB-</option>
                                <option value="O+" {{ old('blood_type') == 'O+' ? 'selected' : '' }}>O+</option>
                                <option value="O-" {{ old('blood_type') == 'O-' ? 'selected' : '' }}>O-</option>
                                <option value="Unknown" {{ old('blood_type') == 'Unknown' ? 'selected' : '' }}>Unknown</option>
                            </select>
                            @error('blood_type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <x-country-dropdown
                                name="nationality"
                                id="nationality"
                                :value="old('nationality')"
                                :required="true"
                                :error="$errors->first('nationality')" />
                        </div>
                    </div>

                    <div class="mb-3">
                        <h5 class="form-label d-flex justify-content-between align-items-center">
                            Social Media Links
                            <button type="button" class="btn btn-outline-primary btn-sm" id="addSocialLink">
                                <i class="bi bi-plus"></i> Add Link
                            </button>
                        </h5>
                        <div id="socialLinksContainer">
                            @php
                                $existingLinks = old('social_links', []);
                                if (!is_array($existingLinks)) {
                                    $existingLinks = [];
                                }
                                // Convert associative array to array of arrays for form display
                                $formLinks = [];
                                foreach ($existingLinks as $platform => $url) {
                                    $formLinks[] = ['platform' => $platform, 'url' => $url];
                                }
                            @endphp
                            @foreach($formLinks as $index => $link)
                                <div class="social-link-row mb-3 d-flex align-items-end">
                                    <div class="me-2 flex-grow-1">
                                        <label class="form-label">Platform</label>
                                        <select class="form-select platform-select" name="social_links[{{ $index }}][platform]" required>
                                            <option value="">Select Platform</option>
                                            <option value="facebook" {{ ($link['platform'] ?? '') == 'facebook' ? 'selected' : '' }}>Facebook</option>
                                            <option value="twitter" {{ ($link['platform'] ?? '') == 'twitter' ? 'selected' : '' }}>Twitter/X</option>
                                            <option value="instagram" {{ ($link['platform'] ?? '') == 'instagram' ? 'selected' : '' }}>Instagram</option>
                                            <option value="linkedin" {{ ($link['platform'] ?? '') == 'linkedin' ? 'selected' : '' }}>LinkedIn</option>
                                            <option value="youtube" {{ ($link['platform'] ?? '') == 'youtube' ? 'selected' : '' }}>YouTube</option>
                                            <option value="tiktok" {{ ($link['platform'] ?? '') == 'tiktok' ? 'selected' : '' }}>TikTok</option>
                                            <option value="snapchat" {{ ($link['platform'] ?? '') == 'snapchat' ? 'selected' : '' }}>Snapchat</option>
                                            <option value="whatsapp" {{ ($link['platform'] ?? '') == 'whatsapp' ? 'selected' : '' }}>WhatsApp</option>
                                            <option value="telegram" {{ ($link['platform'] ?? '') == 'telegram' ? 'selected' : '' }}>Telegram</option>
                                            <option value="discord" {{ ($link['platform'] ?? '') == 'discord' ? 'selected' : '' }}>Discord</option>
                                            <option value="reddit" {{ ($link['platform'] ?? '') == 'reddit' ? 'selected' : '' }}>Reddit</option>
                                            <option value="pinterest" {{ ($link['platform'] ?? '') == 'pinterest' ? 'selected' : '' }}>Pinterest</option>
                                            <option value="twitch" {{ ($link['platform'] ?? '') == 'twitch' ? 'selected' : '' }}>Twitch</option>
                                            <option value="github" {{ ($link['platform'] ?? '') == 'github' ? 'selected' : '' }}>GitHub</option>
                                            <option value="spotify" {{ ($link['platform'] ?? '') == 'spotify' ? 'selected' : '' }}>Spotify</option>
                                            <option value="skype" {{ ($link['platform'] ?? '') == 'skype' ? 'selected' : '' }}>Skype</option>
                                            <option value="slack" {{ ($link['platform'] ?? '') == 'slack' ? 'selected' : '' }}>Slack</option>
                                            <option value="medium" {{ ($link['platform'] ?? '') == 'medium' ? 'selected' : '' }}>Medium</option>
                                            <option value="vimeo" {{ ($link['platform'] ?? '') == 'vimeo' ? 'selected' : '' }}>Vimeo</option>
                                            <option value="messenger" {{ ($link['platform'] ?? '') == 'messenger' ? 'selected' : '' }}>Messenger</option>
                                            <option value="wechat" {{ ($link['platform'] ?? '') == 'wechat' ? 'selected' : '' }}>WeChat</option>
                                            <option value="line" {{ ($link['platform'] ?? '') == 'line' ? 'selected' : '' }}>Line</option>
                                        </select>
                                    </div>
                                    <div class="me-2 flex-grow-1">
                                        <label class="form-label">URL</label>
                                        <input type="url" class="form-control" name="social_links[{{ $index }}][url]" value="{{ $link['url'] ?? '' }}" placeholder="https://example.com/username" required>
                                    </div>
                                    <div class="mb-0">
                                        <button type="button" class="btn btn-outline-danger btn-sm remove-social-link">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="motto" class="form-label">Personal Motto</label>
                        <textarea class="form-control @error('motto') is-invalid @enderror" id="motto" name="motto" rows="3" placeholder="Enter personal motto or quote...">{{ old('motto') }}</textarea>
                        <div class="form-text">Share a personal motto or quote that inspires them.</div>
                        @error('motto')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="relationship_type" class="form-label">Relationship</label>
                            <select class="form-select @error('relationship_type') is-invalid @enderror" id="relationship_type" name="relationship_type" required>
                                <option value="">Select Relationship</option>
                                <option value="son" {{ old('relationship_type') == 'son' ? 'selected' : '' }}>Son</option>
                                <option value="daughter" {{ old('relationship_type') == 'daughter' ? 'selected' : '' }}>Daughter</option>
                                <option value="spouse" {{ old('relationship_type') == 'spouse' ? 'selected' : '' }}>Wife</option>
                                <option value="sponsor" {{ old('relationship_type') == 'sponsor' ? 'selected' : '' }}>Sponsor</option>
                                <option value="other" {{ old('relationship_type') == 'other' ? 'selected' : '' }}>Other</option>
                            </select>
                            @error('relationship_type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="is_billing_contact" name="is_billing_contact" value="1" {{ old('is_billing_contact') ? 'checked' : '' }}>
                        <label class="form-check-label" for="is_billing_contact">Is Billing Contact</label>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="addFamilyMemberForm" class="btn btn-primary">Add Family Member</button>
            </div>
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

    /* Fix Select2 dropdown in modal */
    .select2-container--open {
        z-index: 1060;
    }

    /* Fix custom dropdown in modal */
    .dropdown-menu {
        z-index: 1060;
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

        // Social links script for modal
        let socialLinkIndex = 0;

        // Add new social link row
        document.addEventListener('click', function(e) {
            if (e.target && e.target.id === 'addSocialLink') {
                addSocialLinkRow();
            }
        });

        // Remove social link row
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('remove-social-link') || e.target.closest('.remove-social-link')) {
                e.target.closest('.social-link-row').remove();
            }
        });

        function addSocialLinkRow(platform = '', url = '') {
            const container = document.getElementById('socialLinksContainer');
            if (!container) return;
            const row = document.createElement('div');
            row.className = 'social-link-row mb-3 d-flex align-items-end';

            row.innerHTML = `
                <div class="me-2 flex-grow-1">
                    <label class="form-label">Platform</label>
                    <select class="form-select platform-select" name="social_links[${socialLinkIndex}][platform]" required>
                        <option value="">Select Platform</option>
                        <option value="facebook" ${platform === 'facebook' ? 'selected' : ''}>Facebook</option>
                        <option value="twitter" ${platform === 'twitter' ? 'selected' : ''}>Twitter/X</option>
                        <option value="instagram" ${platform === 'instagram' ? 'selected' : ''}>Instagram</option>
                        <option value="linkedin" ${platform === 'linkedin' ? 'selected' : ''}>LinkedIn</option>
                        <option value="youtube" ${platform === 'youtube' ? 'selected' : ''}>YouTube</option>
                        <option value="tiktok" ${platform === 'tiktok' ? 'selected' : ''}>TikTok</option>
                        <option value="snapchat" ${platform === 'snapchat' ? 'selected' : ''}>Snapchat</option>
                        <option value="whatsapp" ${platform === 'whatsapp' ? 'selected' : ''}>WhatsApp</option>
                        <option value="telegram" ${platform === 'telegram' ? 'selected' : ''}>Telegram</option>
                        <option value="discord" ${platform === 'discord' ? 'selected' : ''}>Discord</option>
                        <option value="reddit" ${platform === 'reddit' ? 'selected' : ''}>Reddit</option>
                        <option value="pinterest" ${platform === 'pinterest' ? 'selected' : ''}>Pinterest</option>
                        <option value="twitch" ${platform === 'twitch' ? 'selected' : ''}>Twitch</option>
                        <option value="github" ${platform === 'github' ? 'selected' : ''}>GitHub</option>
                        <option value="spotify" ${platform === 'spotify' ? 'selected' : ''}>Spotify</option>
                        <option value="skype" ${platform === 'skype' ? 'selected' : ''}>Skype</option>
                        <option value="slack" ${platform === 'slack' ? 'selected' : ''}>Slack</option>
                        <option value="medium" ${platform === 'medium' ? 'selected' : ''}>Medium</option>
                        <option value="vimeo" ${platform === 'vimeo' ? 'selected' : ''}>Vimeo</option>
                        <option value="messenger" ${platform === 'messenger' ? 'selected' : ''}>Messenger</option>
                        <option value="wechat" ${platform === 'wechat' ? 'selected' : ''}>WeChat</option>
                        <option value="line" ${platform === 'line' ? 'selected' : ''}>Line</option>
                    </select>
                </div>
                <div class="me-2 flex-grow-1">
                    <label class="form-label">URL</label>
                    <input type="url" class="form-control" name="social_links[${socialLinkIndex}][url]" value="${url}" placeholder="https://example.com/username" required>
                </div>
                <div class="mb-0">
                    <button type="button" class="btn btn-outline-danger btn-sm remove-social-link">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            `;

            container.appendChild(row);
            socialLinkIndex++;
        }

        // Mobile dropdown script for modal
        fetch('/data/countries.json')
            .then(response => response.json())
            .then(countries => {
                const listElement = document.getElementById('country_codeList');
                const selectedFlag = document.getElementById('country_codeSelectedFlag');
                const selectedCountry = document.getElementById('country_codeSelectedCountry');
                const hiddenInput = document.getElementById('country_code');
                const searchInput = document.getElementById('country_codeSearch');

                if (!listElement) return;

                // Populate dropdown
                countries.forEach(country => {
                    const button = document.createElement('button');
                    button.className = 'dropdown-item d-flex align-items-center';
                    button.type = 'button';
                    button.setAttribute('data-country-code', country.call_code);
                    button.setAttribute('data-country-name', country.name);
                    button.setAttribute('data-flag-code', country.flag);
                    button.setAttribute('data-search', `${country.name.toLowerCase()} ${country.call_code}`);
                    button.innerHTML = `<span class="fi fi-${country.flag.toLowerCase()} me-2"></span><span>${country.name} (${country.call_code})</span>`;
                    button.addEventListener('click', function() {
                        const code = this.getAttribute('data-country-code');
                        const flag = this.getAttribute('data-flag-code');
                        const name = this.getAttribute('data-country-name');
                        if (selectedFlag) selectedFlag.className = `fi fi-${flag.toLowerCase()} me-2`;
                        if (selectedCountry) selectedCountry.textContent = code;
                        if (hiddenInput) hiddenInput.value = code;
                        // Close dropdown
                        const dropdown = bootstrap.Dropdown.getInstance(document.getElementById('country_codeDropdown'));
                        if (dropdown) dropdown.hide();
                    });
                    listElement.appendChild(button);
                });

                // Set initial value
                const initialValue = '{{ old('mobile_code', '+973') }}';
                if (initialValue && selectedCountry && hiddenInput) {
                    hiddenInput.value = initialValue;
                    selectedCountry.textContent = initialValue;
                    const country = countries.find(c => c.call_code === initialValue);
                    if (country && selectedFlag) {
                        selectedFlag.className = `fi fi-${country.flag.toLowerCase()} me-2`;
                    }
                }

                // Search functionality
                if (searchInput) {
                    searchInput.addEventListener('input', function() {
                        const searchTerm = this.value.toLowerCase();
                        const items = listElement.querySelectorAll('.dropdown-item');
                        items.forEach(item => {
                            const searchData = item.getAttribute('data-search');
                            if (searchData.includes(searchTerm)) {
                                item.classList.remove('d-none');
                            } else {
                                item.classList.add('d-none');
                            }
                        });
                    });
                }
            })
            .catch(error => console.error('Error loading countries for mobile:', error));

        // Initialize nationality dropdown when modal is shown
        $('#addFamilyMemberModal').on('shown.bs.modal', function () {
            const selectElement = document.getElementById('nationality');
            if (selectElement && !$(selectElement).hasClass('select2-hidden-accessible')) {
                // Load countries and initialize Select2
                fetch('/data/countries.json')
                    .then(response => response.json())
                    .then(countries => {
                        // Clear existing options except the first one
                        while (selectElement.options.length > 1) {
                            selectElement.remove(1);
                        }

                        // Populate dropdown
                        countries.forEach(country => {
                            const option = document.createElement('option');
                            option.value = country.iso3;
                            option.textContent = country.name;
                            option.setAttribute('data-flag', country.flag);
                            selectElement.appendChild(option);
                        });

                        // Set initial value if provided
                        const initialValue = '{{ old('nationality') }}';
                        if (initialValue) {
                            selectElement.value = initialValue;
                        }

                        // Initialize Select2
                        $(selectElement).select2({
                            templateResult: function(state) {
                                if (!state.id) {
                                    return state.text;
                                }
                                const option = $(state.element);
                                const flagCode = option.data('flag');
                                return $(`<span><span class="fi fi-${flagCode} me-2"></span>${state.text}</span>`);
                            },
                            templateSelection: function(state) {
                                if (!state.id) {
                                    return state.text;
                                }
                                const option = $(state.element);
                                const flagCode = option.data('flag');
                                return $(`<span><span class="fi fi-${flagCode} me-2"></span>${state.text}</span>`);
                            },
                            width: '100%'
                        });
                    })
                    .catch(error => console.error('Error loading countries:', error));
            }
        });
    });
</script>
@endsection
