@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h4 class="mb-0">Add Family Member</h4>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('family.store') }}">
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
                            <x-country-code-dropdown
                                name="mobile_code"
                                id="country_code"
                                :value="old('mobile_code', '+973')"
                                :required="false"
                                :error="$errors->first('mobile_code')">
                                <input id="mobile_number" type="tel"
                                       class="form-control @error('mobile') is-invalid @enderror"
                                       name="mobile"
                                       value="{{ old('mobile') }}"
                                       autocomplete="tel"
                                       placeholder="Phone number">
                            </x-country-code-dropdown>
                            @error('mobile')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <x-gender-dropdown
                                    name="gender"
                                    id="gender"
                                    :value="old('gender')"
                                    :required="true"
                                    :error="$errors->first('gender')" />
                            </div>
                            <div class="col-md-6">
                                <x-birthdate-dropdown
                                    name="birthdate"
                                    id="birthdate"
                                    label="Birthdate"
                                    :value="old('birthdate')"
                                    :required="true"
                                    :min-age="0"
                                    :max-age="120"
                                    :error="$errors->first('birthdate')" />
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
                                <x-nationality-dropdown
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

                        <div class="d-flex justify-content-between">
                            <a href="{{ route('family.dashboard') }}" class="btn btn-outline-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">Add Family Member</button>
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
