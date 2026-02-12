@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
    <div class="flex justify-center">
        <div class="w-full md:w-2/3">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                <div class="px-6 py-4 bg-white border-b border-gray-200 rounded-t-lg">
                    <h4 class="mb-0 text-lg font-semibold">Add Family Member</h4>
                </div>
                <div class="p-6">
                    <form method="POST" action="{{ route('family.store') }}">
                        @csrf

                        <div class="mb-4">
                            <label for="full_name" class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                            <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary @error('full_name') border-red-500 @enderror" id="full_name" name="full_name" value="{{ old('full_name') }}" required>
                            @error('full_name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email Address <span class="text-gray-500">(Optional for children)</span></label>
                            <input type="email" class="w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary @error('email') border-red-500 @enderror" id="email" name="email" value="{{ old('email') }}">
                            @error('email')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="mobile" class="block text-sm font-medium text-gray-700 mb-1">Mobile Number</label>
                            <x-country-code-dropdown
                                name="mobile_code"
                                id="country_code"
                                :value="old('mobile_code', '+973')"
                                :required="false"
                                :error="$errors->first('mobile_code')">
                                <input id="mobile_number" type="tel"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary @error('mobile') border-red-500 @enderror"
                                       name="mobile"
                                       value="{{ old('mobile') }}"
                                       autocomplete="tel"
                                       placeholder="Phone number">
                            </x-country-code-dropdown>
                            @error('mobile')
                                <p class="mt-1 text-sm text-red-600 block">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div>
                                <x-gender-dropdown
                                    name="gender"
                                    id="gender"
                                    :value="old('gender')"
                                    :required="true"
                                    :error="$errors->first('gender')" />
                            </div>
                            <div>
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

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div>
                                <label for="blood_type" class="block text-sm font-medium text-gray-700 mb-1">Blood Type</label>
                                <select class="w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary @error('blood_type') border-red-500 @enderror" id="blood_type" name="blood_type">
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
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <x-country-dropdown
                                    name="nationality"
                                    id="nationality"
                                    label="Nationality"
                                    :value="old('nationality')"
                                    :required="true"
                                    :error="$errors->first('nationality')" />
                            </div>
                        </div>

                        <div class="mb-4">
                            <h5 class="block text-sm font-medium text-gray-700 mb-1 flex justify-between items-center">
                                Social Media Links
                                <button type="button" class="inline-flex items-center px-3 py-1.5 border border-primary text-primary text-sm font-medium rounded-lg hover:bg-primary hover:text-white transition-colors" id="addSocialLink">
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
                                    <div class="social-link-row mb-3 flex items-end gap-2">
                                        <div class="flex-1">
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Platform</label>
                                            <select class="w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary platform-select" name="social_links[{{ $index }}][platform]" required>
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
                                        <div class="flex-1">
                                            <label class="block text-sm font-medium text-gray-700 mb-1">URL</label>
                                            <input type="url" class="w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary" name="social_links[{{ $index }}][url]" value="{{ $link['url'] ?? '' }}" placeholder="https://example.com/username" required>
                                        </div>
                                        <div>
                                            <button type="button" class="inline-flex items-center px-3 py-1.5 border border-red-300 text-red-600 text-sm font-medium rounded-lg hover:bg-red-50 transition-colors remove-social-link">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="motto" class="block text-sm font-medium text-gray-700 mb-1">Personal Motto</label>
                            <textarea class="w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary @error('motto') border-red-500 @enderror" id="motto" name="motto" rows="3" placeholder="Enter personal motto or quote...">{{ old('motto') }}</textarea>
                            <p class="mt-1 text-sm text-gray-500">Share a personal motto or quote that inspires them.</p>
                            @error('motto')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div>
                                <label for="relationship_type" class="block text-sm font-medium text-gray-700 mb-1">Relationship</label>
                                <select class="w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary @error('relationship_type') border-red-500 @enderror" id="relationship_type" name="relationship_type" required>
                                    <option value="">Select Relationship</option>
                                    <option value="son" {{ old('relationship_type') == 'son' ? 'selected' : '' }}>Son</option>
                                    <option value="daughter" {{ old('relationship_type') == 'daughter' ? 'selected' : '' }}>Daughter</option>
                                    <option value="spouse" {{ old('relationship_type') == 'spouse' ? 'selected' : '' }}>Wife</option>
                                    <option value="sponsor" {{ old('relationship_type') == 'sponsor' ? 'selected' : '' }}>Sponsor</option>
                                    <option value="other" {{ old('relationship_type') == 'other' ? 'selected' : '' }}>Other</option>
                                </select>
                                @error('relationship_type')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-4 flex items-center">
                            <input type="checkbox" class="w-4 h-4 text-primary border-gray-300 rounded focus:ring-primary" id="is_billing_contact" name="is_billing_contact" value="1" {{ old('is_billing_contact') ? 'checked' : '' }}>
                            <label class="ml-2 text-sm text-gray-700" for="is_billing_contact">Is Billing Contact</label>
                        </div>

                        <div class="flex justify-between">
                            <a href="{{ route('members.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors">Cancel</a>
                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-primary/90 transition-colors">Add Family Member</button>
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
        row.className = 'social-link-row mb-3 flex items-end gap-2';

        row.innerHTML = `
            <div class="flex-1">
                <label class="block text-sm font-medium text-gray-700 mb-1">Platform</label>
                <select class="w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary platform-select" name="social_links[\${socialLinkIndex}][platform]" required>
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
            <div class="flex-1">
                <label class="block text-sm font-medium text-gray-700 mb-1">URL</label>
                <input type="url" class="w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary" name="social_links[\${socialLinkIndex}][url]" value="${url}" placeholder="https://example.com/username" required>
            </div>
            <div>
                <button type="button" class="inline-flex items-center px-3 py-1.5 border border-red-300 text-red-600 text-sm font-medium rounded-lg hover:bg-red-50 transition-colors remove-social-link">
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
