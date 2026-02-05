@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
    <div class="flex justify-center">
        <div class="w-full md:w-2/3">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h4 class="mb-0 font-semibold">Add Family Member</h4>
                </div>
                <div class="p-6">
                    <form method="POST" action="{{ route('family.store') }}">
                        @csrf

                        <div class="mb-3">
                            <label for="full_name" class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                            <input type="text" class="w-full px-3 py-2 rounded-lg border border-gray-300 text-sm focus:border-purple-500 focus:ring-1 focus:ring-purple-500 focus:outline-none transition @error('full_name') !border-red-500 @enderror" id="full_name" name="full_name" value="{{ old('full_name') }}" required>
                            @error('full_name')
                                <div class="text-red-500 text-sm mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email Address <span class="text-gray-500">(Optional for children)</span></label>
                            <input type="email" class="w-full px-3 py-2 rounded-lg border border-gray-300 text-sm focus:border-purple-500 focus:ring-1 focus:ring-purple-500 focus:outline-none transition @error('email') !border-red-500 @enderror" id="email" name="email" value="{{ old('email') }}">
                            @error('email')
                                <div class="text-red-500 text-sm mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="mobile" class="block text-sm font-medium text-gray-700 mb-1">Mobile Number</label>
                            <x-country-code-dropdown
                                name="mobile_code"
                                id="country_code"
                                :value="old('mobile_code', '+973')"
                                :required="false"
                                :error="$errors->first('mobile_code')">
                                <input id="mobile_number" type="tel"
                                       class="w-full px-3 py-2 rounded-lg border border-gray-300 text-sm focus:border-purple-500 focus:ring-1 focus:ring-purple-500 focus:outline-none transition @error('mobile') !border-red-500 @enderror"
                                       name="mobile"
                                       value="{{ old('mobile') }}"
                                       autocomplete="tel"
                                       placeholder="Phone number">
                            </x-country-code-dropdown>
                            @error('mobile')
                                <div class="text-red-500 text-sm mt-1 block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-3">
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

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-3">
                            <div>
                                <label for="blood_type" class="block text-sm font-medium text-gray-700 mb-1">Blood Type</label>
                                <select class="w-full px-3 py-2 rounded-lg border border-gray-300 text-sm focus:border-purple-500 focus:ring-1 focus:ring-purple-500 focus:outline-none transition appearance-none @error('blood_type') !border-red-500 @enderror" id="blood_type" name="blood_type">
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
                                    <div class="text-red-500 text-sm mt-1">{{ $message }}</div>
                                @enderror
                            </div>
                            <div>
                                <x-nationality-dropdown
                                    name="nationality"
                                    id="nationality"
                                    :value="old('nationality')"
                                    :required="true"
                                    :error="$errors->first('nationality')" />
                            </div>
                        </div>

                        <div class="mb-3">
                            <h5 class="block text-sm font-medium text-gray-700 mb-1 flex justify-between items-center">
                                Social Media Links
                                <button type="button" class="px-3 py-1 rounded-lg border border-purple-500 text-purple-500 text-sm font-medium hover:bg-purple-50 transition" id="addSocialLink">
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
                                    <div class="social-link-row mb-3 flex items-end">
                                        <div class="mr-2 flex-1">
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Platform</label>
                                            <select class="w-full px-3 py-2 rounded-lg border border-gray-300 text-sm focus:border-purple-500 focus:ring-1 focus:ring-purple-500 focus:outline-none transition appearance-none platform-select" name="social_links[{{ $index }}][platform]" required>
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
                                        <div class="mr-2 flex-1">
                                            <label class="block text-sm font-medium text-gray-700 mb-1">URL</label>
                                            <input type="url" class="w-full px-3 py-2 rounded-lg border border-gray-300 text-sm focus:border-purple-500 focus:ring-1 focus:ring-purple-500 focus:outline-none transition" name="social_links[{{ $index }}][url]" value="{{ $link['url'] ?? '' }}" placeholder="https://example.com/username" required>
                                        </div>
                                        <div class="mb-0">
                                            <button type="button" class="px-3 py-1 rounded-lg border border-red-500 text-red-500 text-sm font-medium hover:bg-red-50 transition remove-social-link">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="motto" class="block text-sm font-medium text-gray-700 mb-1">Personal Motto</label>
                            <textarea class="w-full px-3 py-2 rounded-lg border border-gray-300 text-sm focus:border-purple-500 focus:ring-1 focus:ring-purple-500 focus:outline-none transition @error('motto') !border-red-500 @enderror" id="motto" name="motto" rows="3" placeholder="Enter personal motto or quote...">{{ old('motto') }}</textarea>
                            <div class="text-xs text-gray-500 mt-1">Share a personal motto or quote that inspires them.</div>
                            @error('motto')
                                <div class="text-red-500 text-sm mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-3">
                            <div>
                                <label for="relationship_type" class="block text-sm font-medium text-gray-700 mb-1">Relationship</label>
                                <select class="w-full px-3 py-2 rounded-lg border border-gray-300 text-sm focus:border-purple-500 focus:ring-1 focus:ring-purple-500 focus:outline-none transition appearance-none @error('relationship_type') !border-red-500 @enderror" id="relationship_type" name="relationship_type" required>
                                    <option value="">Select Relationship</option>
                                    <option value="son" {{ old('relationship_type') == 'son' ? 'selected' : '' }}>Son</option>
                                    <option value="daughter" {{ old('relationship_type') == 'daughter' ? 'selected' : '' }}>Daughter</option>
                                    <option value="spouse" {{ old('relationship_type') == 'spouse' ? 'selected' : '' }}>Wife</option>
                                    <option value="sponsor" {{ old('relationship_type') == 'sponsor' ? 'selected' : '' }}>Sponsor</option>
                                    <option value="other" {{ old('relationship_type') == 'other' ? 'selected' : '' }}>Other</option>
                                </select>
                                @error('relationship_type')
                                    <div class="text-red-500 text-sm mt-1">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-3 flex items-center gap-2">
                            <input type="checkbox" class="rounded border-gray-300 text-purple-500 focus:ring-purple-500" id="is_billing_contact" name="is_billing_contact" value="1" {{ old('is_billing_contact') ? 'checked' : '' }}>
                            <label class="text-sm text-gray-700" for="is_billing_contact">Is Billing Contact</label>
                        </div>

                        <div class="flex justify-between">
                            <a href="{{ route('family.dashboard') }}" class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700 text-sm font-medium hover:bg-gray-50 transition">Cancel</a>
                            <button type="submit" class="px-4 py-2 rounded-lg bg-purple-600 text-white text-sm font-medium hover:bg-purple-700 transition">Add Family Member</button>
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
        row.className = 'social-link-row mb-3 flex items-end';

        row.innerHTML = `
            <div class="mr-2 flex-1">
                <label class="block text-sm font-medium text-gray-700 mb-1">Platform</label>
                <select class="w-full px-3 py-2 rounded-lg border border-gray-300 text-sm focus:border-purple-500 focus:ring-1 focus:ring-purple-500 focus:outline-none transition appearance-none platform-select" name="social_links[${socialLinkIndex}][platform]" required>
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
            <div class="mr-2 flex-1">
                <label class="block text-sm font-medium text-gray-700 mb-1">URL</label>
                <input type="url" class="w-full px-3 py-2 rounded-lg border border-gray-300 text-sm focus:border-purple-500 focus:ring-1 focus:ring-purple-500 focus:outline-none transition" name="social_links[${socialLinkIndex}][url]" value="${url}" placeholder="https://example.com/username" required>
            </div>
            <div class="mb-0">
                <button type="button" class="px-3 py-1 rounded-lg border border-red-500 text-red-500 text-sm font-medium hover:bg-red-50 transition remove-social-link">
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
