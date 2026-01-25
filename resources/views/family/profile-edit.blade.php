@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h4 class="mb-0">Edit Profile</h4>
                </div>
                <div class="card-body">
                    <!-- Profile Picture Section -->
                    <div class="mb-4 text-center">
                        <div class="mb-3">
                            @if($user->profile_picture && file_exists(public_path('storage/' . $user->profile_picture)))
                                <img src="{{ asset('storage/' . $user->profile_picture) }}"
                                     alt="Profile Picture"
                                     style="width: 300px; height: 400px; object-fit: cover; border: 3px solid #dee2e6; border-radius: 8px;">
                            @elseif(file_exists(public_path('storage/images/profiles/profile_' . $user->id . '.png')))
                                <img src="{{ asset('storage/images/profiles/profile_' . $user->id . '.png') }}"
                                     alt="Profile Picture"
                                     style="width: 300px; height: 400px; object-fit: cover; border: 3px solid #dee2e6; border-radius: 8px;">
                            @elseif(file_exists(public_path('storage/images/profiles/profile_' . $user->id . '.jpg')))
                                <img src="{{ asset('storage/images/profiles/profile_' . $user->id . '.jpg') }}"
                                     alt="Profile Picture"
                                     style="width: 300px; height: 400px; object-fit: cover; border: 3px solid #dee2e6; border-radius: 8px;">
                            @elseif(file_exists(public_path('storage/images/profiles/profile_' . $user->id . '.jpeg')))
                                <img src="{{ asset('storage/images/profiles/profile_' . $user->id . '.jpeg') }}"
                                     alt="Profile Picture"
                                     style="width: 300px; height: 400px; object-fit: cover; border: 3px solid #dee2e6; border-radius: 8px;">
                            @else
                                <div style="width: 300px; height: 400px; background-color: #f0f0f0; border: 3px solid #dee2e6; border-radius: 8px; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                                    <div class="text-center">
                                        <i class="bi bi-person-circle" style="font-size: 100px; color: #dee2e6;"></i>
                                        <p class="text-muted mt-2">No profile picture</p>
                                    </div>
                                </div>
                            @endif
                        </div>
                        <x-takeone-cropper
                            id="profile_picture"
                            width="300"
                            height="400"
                            shape="square"
                            folder="images/profiles"
                            filename="profile_{{ $user->id }}"
                            uploadUrl="{{ route('profile.upload-picture') }}"
                        />
                    </div>

                    <form method="POST" action="{{ route('profile.update') }}">
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label for="full_name" class="form-label">Full Name</label>
                            <input type="text" class="form-control @error('full_name') is-invalid @enderror" id="full_name" name="full_name" value="{{ old('full_name', $user->full_name) }}" required>
                            @error('full_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email', $user->email) }}" required>
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="mobile" class="form-label">Mobile Number</label>
                            <div class="input-group" onclick="event.stopPropagation()">
                                <button class="btn btn-outline-secondary dropdown-toggle country-dropdown-btn d-flex align-items-center" type="button" id="country_codeDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                    <span class="fi fi-bh me-2" id="country_codeSelectedFlag"></span>
                                    <span class="country-label" id="country_codeSelectedCountry">{{ old('mobile_code', $user->mobile_code ?? '+973') }}</span>
                                </button>
                                <div class="dropdown-menu p-2" aria-labelledby="country_codeDropdown" style="min-width: 200px;" onclick="event.stopPropagation()">
                                    <input type="text" class="form-control form-control-sm mb-2" placeholder="Search country..." id="country_codeSearch" onkeydown="event.stopPropagation()">
                                    <div class="country-list" id="country_codeList" style="max-height: 300px; overflow-y: auto;"></div>
                                </div>
                                <input type="hidden" id="country_code" name="mobile_code" value="{{ old('mobile_code', $user->mobile['code'] ?? '+973') }}" required="">
                                <input id="mobile_number" type="tel" class="form-control @error('mobile') is-invalid @enderror" name="mobile" value="{{ old('mobile', $user->mobile['number'] ?? '') }}" required="" autocomplete="tel" placeholder="Phone number">
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
                                    <option value="m" {{ old('gender', $user->gender) == 'm' ? 'selected' : '' }}>Male</option>
                                    <option value="f" {{ old('gender', $user->gender) == 'f' ? 'selected' : '' }}>Female</option>
                                </select>
                                @error('gender')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label for="birthdate" class="form-label">Birthdate</label>
                                <input type="date" class="form-control @error('birthdate') is-invalid @enderror" id="birthdate" name="birthdate" value="{{ old('birthdate', $user->birthdate->format('Y-m-d')) }}" required>
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
                                    <option value="A+" {{ old('blood_type', $user->blood_type) == 'A+' ? 'selected' : '' }}>A+</option>
                                    <option value="A-" {{ old('blood_type', $user->blood_type) == 'A-' ? 'selected' : '' }}>A-</option>
                                    <option value="B+" {{ old('blood_type', $user->blood_type) == 'B+' ? 'selected' : '' }}>B+</option>
                                    <option value="B-" {{ old('blood_type', $user->blood_type) == 'B-' ? 'selected' : '' }}>B-</option>
                                    <option value="AB+" {{ old('blood_type', $user->blood_type) == 'AB+' ? 'selected' : '' }}>AB+</option>
                                    <option value="AB-" {{ old('blood_type', $user->blood_type) == 'AB-' ? 'selected' : '' }}>AB-</option>
                                    <option value="O+" {{ old('blood_type', $user->blood_type) == 'O+' ? 'selected' : '' }}>O+</option>
                                    <option value="O-" {{ old('blood_type', $user->blood_type) == 'O-' ? 'selected' : '' }}>O-</option>
                                    <option value="Unknown" {{ old('blood_type', $user->blood_type) == 'Unknown' ? 'selected' : '' }}>Unknown</option>
                                </select>
                                @error('blood_type')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <x-country-dropdown
                                    name="nationality"
                                    id="nationality"
                                    :value="old('nationality', $user->nationality)"
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
                                    $existingLinks = old('social_links', $user->social_links ?? []);
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
                            <textarea class="form-control @error('motto') is-invalid @enderror" id="motto" name="motto" rows="3" placeholder="Enter your personal motto or quote...">{{ old('motto', $user->motto) }}</textarea>
                            <div class="form-text">Share a personal motto or quote that inspires you.</div>
                            @error('motto')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="{{ route('profile.show') }}" class="btn btn-outline-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">Update</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Load countries for mobile code dropdown
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
                    selectedFlag.className = `fi fi-${flag.toLowerCase()} me-2`;
                    selectedCountry.textContent = code;
                    hiddenInput.value = code;
                    // Close dropdown
                    const dropdown = bootstrap.Dropdown.getInstance(document.getElementById('country_codeDropdown'));
                    if (dropdown) dropdown.hide();
                });
                listElement.appendChild(button);
            });

            // Set initial value
            const initialValue = '{{ old('mobile_code', $user->mobile['code'] ?? '+973') }}';
            if (initialValue) {
                hiddenInput.value = initialValue;
                selectedCountry.textContent = initialValue;
                const country = countries.find(c => c.call_code === initialValue);
                if (country) {
                    selectedFlag.className = `fi fi-${country.flag.toLowerCase()} me-2`;
                }
            }

            // Search functionality
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                const items = listElement.querySelectorAll('.dropdown-item');
                items.forEach(item => {
                    const searchData = item.getAttribute('data-search');
                    if (searchData.includes(searchTerm)) {
                        item.style.display = '';
                    } else {
                        item.style.display = 'none';
                    }
                });
            });
        })
        .catch(error => console.error('Error loading countries:', error));

    let socialLinkIndex = {{ count($formLinks ?? []) }};

    // Add new social link row
    document.getElementById('addSocialLink').addEventListener('click', function() {
        addSocialLinkRow();
    });

    // Remove social link row
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('remove-social-link') || e.target.closest('.remove-social-link')) {
            e.target.closest('.social-link-row').remove();
        }
    });

    function addSocialLinkRow(platform = '', url = '') {
        const container = document.getElementById('socialLinksContainer');
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
});
</script>
@endpush
@endsection
