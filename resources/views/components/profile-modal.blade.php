@props([
    'user' => null,
    'formAction',
    'formMethod' => 'PUT',
    'cancelUrl' => null,
    'showRelationshipFields' => false,
    'relationship' => null,
    'mode' => 'edit',
    'title' => null,
    'subtitle' => null,
    'icon' => null,
    'submitText' => null,
    'submitIcon' => null,
    'eventName' => null,
    'showPasswordFields' => false,
    'showEmailField' => true,
])

@php
    $isCreate = $mode === 'create';
    $modalTitle = $title ?? ($isCreate ? 'Add Family Member' : 'Edit Profile');
    $modalSubtitle = $subtitle ?? ($isCreate ? 'Fill in the details to add a new family member' : null);
    $modalIcon = $icon ?? ($isCreate ? 'bi-person-plus' : 'bi-person-circle');
    $submitText = $submitText ?? ($isCreate ? 'Add Member' : 'Update Profile');
    $submitIcon = $submitIcon ?? ($isCreate ? 'bi-person-plus' : 'bi-check-circle');
    $formId = $isCreate ? 'memberCreateForm' : 'profileEditForm';
    $alpineComponent = $isCreate ? 'memberProfileModal_create' : 'memberProfileModal_edit';
    $eventName = $eventName ?? ($isCreate ? 'open-member-create-modal' : 'open-profile-modal');

    // Default empty values for create mode
    $userName = old('full_name', $user->full_name ?? '');
    $userEmail = old('email', $user->email ?? '');
    $userMobileCode = old('mobile_code', $user->mobile['code'] ?? '+973');
    $userMobileNumber = old('mobile', $user->mobile['number'] ?? '');
    $userGender = old('gender', $user->gender ?? '');
    $userMaritalStatus = old('marital_status', $user->marital_status ?? '');
    $userBirthdate = old('birthdate', $user ? ($user->birthdate?->format('Y-m-d')) : '');
    $userBloodType = old('blood_type', $user->blood_type ?? '');
    $userNationality = old('nationality', $user->nationality ?? '');
    $userMotto = old('motto', $user->motto ?? '');
    $profilePicturePublic = old('profile_picture_is_public', $user->profile_picture_is_public ?? true);

    // Social links
    $existingLinks = old('social_links', $user ? ($user->social_links ?? []) : []);
    if (!is_array($existingLinks)) {
        $existingLinks = [];
    }
    $formLinks = [];
    foreach ($existingLinks as $platform => $url) {
        $formLinks[] = ['platform' => $platform, 'url' => $url];
    }

    // Profile image (edit mode only)
    $currentProfileImage = '';
    if (!$isCreate && $user) {
        if ($user->profile_picture && file_exists(public_path('storage/' . $user->profile_picture))) {
            $currentProfileImage = asset('storage/' . $user->profile_picture) . '?v=' . $user->updated_at->timestamp;
        } else {
            $extensions = ['png', 'jpg', 'jpeg', 'webp'];
            foreach ($extensions as $ext) {
                $path = 'storage/images/profiles/profile_' . $user->id . '.' . $ext;
                if (file_exists(public_path($path))) {
                    $currentProfileImage = asset($path);
                    break;
                }
            }
        }
    }

    // Tabs: create mode skips the photo tab
    $showPhotoTab = !$isCreate && $user;
    $defaultTab = $showPhotoTab ? 'photo' : 'personal';
@endphp

<!-- Member Profile Modal ({{ $mode }}) -->
<div x-data="{{ $alpineComponent }}()" x-init="init()" x-show="open" x-cloak
     class="fixed inset-0 z-50 overflow-y-auto"
     x-on:{{ $eventName }}.window="open = true"
     @keydown.escape.window="closeModal()">

    <!-- Backdrop -->
    <div x-show="open" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-black/50" @click="closeModal()"></div>

    <!-- Modal -->
    <div class="flex min-h-full items-center justify-center p-4">
        <div x-show="open" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
             class="relative bg-white rounded-lg shadow-xl w-full max-w-4xl" @click.stop>

            <!-- Header -->
            <div class="flex items-center justify-between p-4 bg-primary text-white rounded-t-lg">
                <h5 class="text-lg font-medium flex items-center">
                    <i class="bi {{ $modalIcon }} mr-2"></i>{{ $modalTitle }}
                </h5>
                <button type="button" @click="closeModal()" class="text-white hover:text-gray-200 text-2xl leading-none">&times;</button>
            </div>

            <!-- Tab Navigation -->
            <div class="border-b border-gray-200">
                <nav class="flex" role="tablist">
                    @if($showPhotoTab)
                    <button type="button" @click="activeTab = 'photo'"
                            :class="activeTab === 'photo' ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                            class="flex-1 py-3 px-4 text-center border-b-2 font-medium text-sm transition-colors">
                        <i class="bi bi-camera mr-1"></i>Profile Photo
                    </button>
                    @endif
                    <button type="button" @click="activeTab = 'personal'"
                            :class="activeTab === 'personal' ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                            class="flex-1 py-3 px-4 text-center border-b-2 font-medium text-sm transition-colors">
                        <i class="bi bi-person-badge mr-1"></i>Personal Info
                    </button>
                    <button type="button" @click="activeTab = 'social'"
                            :class="activeTab === 'social' ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                            class="flex-1 py-3 px-4 text-center border-b-2 font-medium text-sm transition-colors">
                        <i class="bi bi-share mr-1"></i>Social Media
                    </button>
                    <button type="button" @click="activeTab = 'additional'"
                            :class="activeTab === 'additional' ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                            class="flex-1 py-3 px-4 text-center border-b-2 font-medium text-sm transition-colors">
                        <i class="bi bi-info-circle mr-1"></i>Additional Info
                    </button>
                </nav>
            </div>

            <!-- Form -->
            <form method="POST" action="{{ $formAction }}" id="{{ $formId }}" @submit.prevent="submitForm()">
                @csrf
                @if(!$isCreate)
                    @method($formMethod)
                @endif

                <!-- Tab Content -->
                <div class="p-4 overflow-y-auto" style="height: 500px;">

                    {{-- ===== Profile Photo Tab (edit only) ===== --}}
                    @if($showPhotoTab)
                    <div x-show="activeTab === 'photo'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
                        <div class="flex flex-col md:flex-row gap-4">
                            <!-- Left Side - Profile Picture -->
                            <div class="md:w-5/12">
                                <div class="text-center profile-photo-preview-container">
                                    <div class="mb-3">
                                        @if($currentProfileImage)
                                            <img src="{{ $currentProfileImage }}"
                                                 alt="Profile Picture"
                                                 id="profile_picture_preview"
                                                 class="mx-auto image-upload-preview"
                                                 style="width: 300px; height: 400px; object-fit: cover; border: 3px solid #dee2e6; border-radius: 8px;">
                                        @else
                                            <div id="profile_picture_placeholder"
                                                 class="mx-auto image-placeholder"
                                                 style="width: 300px; height: 400px; background-color: #f0f0f0; border: 3px solid #dee2e6; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                                <div class="text-center">
                                                    <i class="bi bi-person-circle" style="font-size: 60px; color: #dee2e6;"></i>
                                                    <p class="text-muted mt-2 mb-0">No profile picture</p>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                    <input type="hidden" name="remove_profile_picture" id="removeProfilePictureInput" value="0">
                                </div>
                            </div>

                            <!-- Right Side - Information & Controls -->
                            <div class="md:w-7/12">
                                <div class="profile-photo-info" style="max-height: 400px; overflow: hidden;">
                                    <!-- Motivational Header -->
                                    <div class="mb-3">
                                        <h6 class="text-primary mb-2">
                                            <i class="bi bi-camera-fill mr-2"></i>Your Athletic Identity
                                        </h6>
                                        <div class="alert border-0 shadow-sm p-2" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                                            <div class="flex items-start">
                                                <i class="bi bi-lightbulb-fill mr-2" style="font-size: 1.25rem;"></i>
                                                <div>
                                                    <p class="mb-1 small"><strong>Make Your Profile Stand Out!</strong></p>
                                                    <p class="mb-0" style="font-size: 0.75rem;">Your profile picture is your athletic CV and first impression. Use a clear, professional photo to help coaches and teammates recognize you!</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Privacy Toggle -->
                                    <div class="privacy-settings-card mb-3">
                                        <div class="card border-0 shadow-sm">
                                            <div class="card-body p-3">
                                                <div class="flex justify-between items-center mb-2">
                                                    <div class="flex-1 mr-2">
                                                        <h6 class="mb-1">
                                                            <i class="bi bi-shield-lock-fill text-primary mr-1"></i>Privacy Settings
                                                        </h6>
                                                        <p class="text-muted mb-0 small">Toggle to control visibility</p>
                                                    </div>
                                                    <div class="form-check form-switch" style="font-size: 1.2rem;">
                                                        <input class="form-check-input" type="checkbox" role="switch"
                                                               id="profilePictureVisibility"
                                                               x-model="profilePicturePublic"
                                                               name="profile_picture_is_public"
                                                               value="1"
                                                               {{ $profilePicturePublic ? 'checked' : '' }}>
                                                    </div>
                                                </div>

                                                <div :class="profilePicturePublic ? 'alert alert-success' : 'alert alert-warning'"
                                                     class="mb-0 p-2" role="alert">
                                                    <div class="flex items-start">
                                                        <i :class="profilePicturePublic ? 'bi-globe' : 'bi-lock-fill'" class="bi mr-2 mt-1" style="font-size: 1.3rem;"></i>
                                                        <div style="flex: 1; min-width: 0;">
                                                            <strong class="block" style="font-size: 0.9rem;" x-text="profilePicturePublic ? 'Public' : 'Private'"></strong>
                                                            <p class="mb-0 small" x-text="profilePicturePublic ? 'Everyone can see your profile picture' : 'Only you and your family can see your profile picture'"></p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Photo Guidelines & Action Buttons -->
                                    <div class="flex gap-3">
                                        <div style="flex: 1; min-width: 0;">
                                            <h6 class="text-muted mb-2 small">
                                                <i class="bi bi-check-circle-fill mr-1"></i>Quick Tips
                                            </h6>
                                            <ul class="text-muted mb-0 small" style="list-style: none; padding-left: 0;">
                                                <li class="mb-1"><i class="bi bi-check text-success mr-1"></i>Recent, high-quality</li>
                                                <li class="mb-1"><i class="bi bi-check text-success mr-1"></i>Face clearly visible</li>
                                                <li class="mb-1"><i class="bi bi-check text-success mr-1"></i>Professional look</li>
                                                <li class="mb-0"><i class="bi bi-check text-success mr-1"></i>Good lighting</li>
                                            </ul>
                                        </div>
                                        <div style="min-width: 120px;">
                                            <h6 class="text-muted mb-2 small">
                                                <i class="bi bi-gear-fill mr-1"></i>Actions
                                            </h6>
                                            @if(view()->exists('takeone::components.widget') && $user)
                                                <x-takeone-cropper
                                                    id="profile_picture"
                                                    width="300"
                                                    height="400"
                                                    shape="rectangle"
                                                    folder="images/profiles"
                                                    filename="profile_{{ $user->id }}"
                                                    uploadUrl="{{ $attributes->get('uploadUrl') }}"
                                                    currentImage="{{ $currentProfileImage }}"
                                                    buttonText="Change Photo"
                                                    buttonClass="btn btn-success btn-sm w-full"
                                                />
                                            @else
                                                <button type="button" class="btn btn-success btn-sm w-full" onclick="document.getElementById('profile_picture_input').click()">
                                                    <i class="bi bi-camera mr-1"></i>Change Photo
                                                </button>
                                                <input type="file" id="profile_picture_input" name="profile_picture" accept="image/*" class="hidden" style="display: none;">
                                            @endif
                                            <button type="button" class="btn btn-outline-danger btn-sm w-full mt-2" id="removeProfilePicture" @if(!$currentProfileImage) style="display: none;" @endif>
                                                <i class="bi bi-trash mr-1"></i>Remove
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif

                    {{-- ===== Personal Info Tab ===== --}}
                    <div x-show="activeTab === 'personal'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
                        <div class="mb-3">
                            <label for="{{ $formId }}_full_name" class="form-label">Full Name <span class="text-red-500">*</span></label>
                            <input type="text" class="form-control @error('full_name') is-invalid @enderror" id="{{ $formId }}_full_name" name="full_name" value="{{ $userName }}" required>
                            @error('full_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        @if($showEmailField)
                        <div class="mb-3">
                            <label for="{{ $formId }}_email" class="form-label">
                                Email Address
                                @if(($isCreate || $showRelationshipFields) && !$showPasswordFields)
                                    <span class="text-gray-400">(Optional for children)</span>
                                @elseif($showPasswordFields)
                                    <span class="text-red-500">*</span>
                                @endif
                            </label>
                            <input type="email" class="form-control @error('email') is-invalid @enderror" id="{{ $formId }}_email" name="email" value="{{ $userEmail }}" {{ ($showPasswordFields || (!$isCreate && !$showRelationshipFields)) ? 'required' : '' }}>
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        @endif

                        @if($showPasswordFields)
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-3">
                            <div>
                                <label for="{{ $formId }}_password" class="form-label">Password <span class="text-red-500">*</span></label>
                                <div class="relative" x-data="{ show: false }">
                                    <input :type="show ? 'text' : 'password'" class="form-control pr-10 @error('password') is-invalid @enderror" id="{{ $formId }}_password" name="password" required autocomplete="new-password" placeholder="Min 8 characters">
                                    <button type="button" @click="show = !show" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                        <i class="bi" :class="show ? 'bi-eye-slash' : 'bi-eye'"></i>
                                    </button>
                                </div>
                                @error('password')
                                    <div class="invalid-feedback block">{{ $message }}</div>
                                @enderror
                            </div>
                            <div>
                                <label for="{{ $formId }}_password_confirmation" class="form-label">Confirm Password <span class="text-red-500">*</span></label>
                                <div class="relative" x-data="{ show: false }">
                                    <input :type="show ? 'text' : 'password'" class="form-control pr-10" id="{{ $formId }}_password_confirmation" name="password_confirmation" required autocomplete="new-password" placeholder="Repeat password">
                                    <button type="button" @click="show = !show" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                        <i class="bi" :class="show ? 'bi-eye-slash' : 'bi-eye'"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        @endif

                        <div class="mb-3">
                            <label for="{{ $formId }}_mobile" class="form-label">Mobile Number</label>
                            <x-country-code-dropdown
                                name="mobile_code"
                                :id="$formId . '_country_code'"
                                :value="$userMobileCode"
                                :required="false"
                                :error="$errors->first('mobile_code')">
                                <input id="{{ $formId }}_mobile_number" type="tel"
                                       class="form-control @error('mobile') is-invalid @enderror"
                                       name="mobile"
                                       value="{{ $userMobileNumber }}"
                                       autocomplete="tel"
                                       placeholder="Phone number">
                            </x-country-code-dropdown>
                            @error('mobile')
                                <div class="invalid-feedback block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-12 gap-3 mb-3">
                            <div class="md:col-span-3">
                                <x-gender-dropdown
                                    name="gender"
                                    :id="$formId . '_gender'"
                                    label="Gender"
                                    :value="$userGender"
                                    :required="true"
                                    :error="$errors->first('gender')" />
                            </div>
                            <div class="md:col-span-3">
                                <x-marital-status-dropdown
                                    name="marital_status"
                                    :id="$formId . '_marital_status'"
                                    label="Marital Status"
                                    :value="$userMaritalStatus"
                                    :error="$errors->first('marital_status')" />
                            </div>
                            <div class="md:col-span-6">
                                <x-birthdate-dropdown
                                    name="birthdate"
                                    :id="$formId . '_birthdate'"
                                    label="Birthdate"
                                    :value="$userBirthdate"
                                    :required="true"
                                    :min-age="$isCreate ? 0 : 10"
                                    :max-age="120"
                                    :error="$errors->first('birthdate')" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-3">
                            <div>
                                <x-blood-type-dropdown
                                    name="blood_type"
                                    :id="$formId . '_blood_type'"
                                    label="Blood Type"
                                    :value="$userBloodType"
                                    :error="$errors->first('blood_type')" />
                            </div>
                            <div>
                                <x-country-dropdown
                                    name="nationality"
                                    :id="$formId . '_nationality'"
                                    label="Nationality"
                                    :value="$userNationality"
                                    :required="true"
                                    :error="$errors->first('nationality')" />
                            </div>
                        </div>

                        {{-- Relationship fields --}}
                        @if($showRelationshipFields)
                            <div class="mb-3">
                                <x-relationship-dropdown
                                    name="relationship_type"
                                    :id="$formId . '_relationship_type'"
                                    label="Relationship"
                                    :value="old('relationship_type', $relationship->relationship_type ?? '')"
                                    :required="true"
                                    :error="$errors->first('relationship_type')" />
                            </div>

                            <div class="mb-3 flex items-center">
                                <input type="checkbox" class="form-check-input mr-2" id="{{ $formId }}_is_billing_contact" name="is_billing_contact" value="1" {{ old('is_billing_contact', $relationship->is_billing_contact ?? false) ? 'checked' : '' }}>
                                <label class="form-check-label" for="{{ $formId }}_is_billing_contact">Is Billing Contact</label>
                            </div>
                        @endif
                    </div>

                    {{-- ===== Social Media Tab ===== --}}
                    <div x-show="activeTab === 'social'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
                        <x-social-links-editor :links="$formLinks" :containerId="$formId . '_socialLinksContainer'" />
                    </div>

                    {{-- ===== Additional Info Tab ===== --}}
                    <div x-show="activeTab === 'additional'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
                        <div class="mb-3">
                            <label for="{{ $formId }}_motto" class="form-label">Personal Motto</label>
                            <textarea class="form-control @error('motto') is-invalid @enderror" id="{{ $formId }}_motto" name="motto" rows="4" placeholder="Enter personal motto or quote...">{{ $userMotto }}</textarea>
                            <div class="text-gray-500 text-sm mt-1">Share a personal motto or quote that inspires {{ $isCreate ? 'them' : 'you' }}.</div>
                            @error('motto')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
            </form>

            <!-- Footer -->
            <div class="flex justify-between items-center p-4 bg-gray-50 border-t rounded-b-lg">
                <div>
                    <button type="button" class="btn btn-secondary" @click="closeModal()">Cancel</button>
                </div>
                <div>
                    <button type="submit" class="btn btn-success" id="{{ $formId }}_submitBtn" form="{{ $formId }}" :disabled="isSubmitting">
                        <span x-show="!isSubmitting"><i class="bi {{ $submitIcon }} mr-1"></i>{{ $submitText }}</span>
                        <span x-show="isSubmitting"><span class="inline-block animate-spin mr-2">&#8635;</span>{{ $isCreate ? 'Creating...' : 'Updating...' }}</span>
                    </button>
                </div>
                <div>
                    <button type="button" class="btn btn-outline-secondary mr-2" x-show="activeTab !== tabs[0]" @click="prevTab()">
                        <i class="bi bi-arrow-left mr-1"></i>Previous
                    </button>
                    <button type="button" class="btn btn-primary" x-show="activeTab !== 'additional'" @click="nextTab()">
                        Next<i class="bi bi-arrow-right ml-1"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Styles moved to app.css (Phase 6) --}}

@push('scripts')
<script>
function {{ $alpineComponent }}() {
    return {
        open: false,
        activeTab: '{{ $defaultTab }}',
        tabs: {!! json_encode($showPhotoTab ? ['photo', 'personal', 'social', 'additional'] : ['personal', 'social', 'additional']) !!},
        isSubmitting: false,
        isCreateMode: {{ $isCreate ? 'true' : 'false' }},
        profilePicturePublic: {{ $profilePicturePublic ? 'true' : 'false' }},
        init() {
            @if($isCreate)
                // Global function to open create modal
                window.openMemberCreateModal = () => this.open = true;
            @endif

            // Auto-open modal if cancelUrl is set (dedicated edit page)
            @if($cancelUrl)
                this.open = true;
            @endif

            // Open if there are validation errors
            @if($errors->any())
                this.open = true;
            @endif

            @if(!$isCreate)
            // Listen for image upload success
            document.addEventListener('imageUploaded', (e) => {
                if (e.detail && e.detail.url) {
                    this.updateProfilePicturePreview(e.detail.url);
                }
            });

            // Global callback for cropper
            window.imageUploadSuccess = (result) => {
                if (result && result.url) {
                    this.updateProfilePicturePreview(result.url);
                } else if (result && result.path) {
                    const fullUrl = window.location.origin + '/storage/' + result.path;
                    this.updateProfilePicturePreview(fullUrl);
                }
            };

            // Remove profile picture handler
            this.attachRemovePhotoListener();
            @endif

        },

        closeModal() {
            this.open = false;
            @if($cancelUrl)
                window.location.href = "{{ $cancelUrl }}";
            @endif
        },

        nextTab() {
            const currentIndex = this.tabs.indexOf(this.activeTab);
            if (currentIndex < this.tabs.length - 1) {
                this.activeTab = this.tabs[currentIndex + 1];
            }
        },

        prevTab() {
            const currentIndex = this.tabs.indexOf(this.activeTab);
            if (currentIndex > 0) {
                this.activeTab = this.tabs[currentIndex - 1];
            }
        },

        submitForm() {
            this.isSubmitting = true;
            const form = document.getElementById('{{ $formId }}');
            const formData = new FormData(form);

            fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    @if(!$isCreate)
                    // Update profile picture on the page if it was changed
                    if (data.profile_picture_url) {
                        document.querySelectorAll('img[alt*="Profile"], img[src*="profile"]').forEach(img => {
                            if (img.src.includes('profile_') || img.alt.toLowerCase().includes('profile')) {
                                img.src = data.profile_picture_url + '?v=' + new Date().getTime();
                            }
                        });
                    }
                    @endif

                    // Show success message
                    this.showAlert('success', data.message || '{{ $isCreate ? "Member created successfully!" : "Profile updated successfully!" }}');

                    // Close modal and reload
                    setTimeout(() => {
                        this.open = false;
                        if (data.redirect) {
                            window.location.href = data.redirect;
                        } else {
                            window.location.reload();
                        }
                    }, 1500);
                } else {
                    throw new Error(data.message || '{{ $isCreate ? "Creation failed" : "Update failed" }}');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                this.showAlert('danger', error.message || 'Something went wrong. Please try again.');
                this.isSubmitting = false;
            });
        },

        showAlert(type, message) {
            const alertDiv = document.createElement('div');
            alertDiv.className = `fixed top-4 left-1/2 transform -translate-x-1/2 z-[9999] px-4 py-3 rounded-lg shadow-lg ${type === 'success' ? 'bg-green-100 text-green-800 border border-green-200' : 'bg-red-100 text-red-800 border border-red-200'}`;
            alertDiv.innerHTML = `
                <div class="flex items-center">
                    <i class="bi ${type === 'success' ? 'bi-check-circle' : 'bi-exclamation-triangle'} mr-2"></i>
                    <span>${message}</span>
                    <button type="button" class="ml-4 text-lg leading-none" onclick="this.parentElement.parentElement.remove()">&times;</button>
                </div>
            `;
            document.body.appendChild(alertDiv);

            setTimeout(() => alertDiv.remove(), 5000);
        },

        @if(!$isCreate)
        updateProfilePicturePreview(imageUrl) {
            const preview = document.getElementById('profile_picture_preview');
            const placeholder = document.getElementById('profile_picture_placeholder');

            if (placeholder) {
                placeholder.style.display = 'none';
                const previewContainer = placeholder.parentElement;
                const newImg = document.createElement('img');
                newImg.src = imageUrl + '?v=' + new Date().getTime();
                newImg.alt = 'Profile Picture';
                newImg.id = 'profile_picture_preview';
                newImg.className = 'mx-auto';
                newImg.style.cssText = 'width: 300px; height: 400px; object-fit: cover; border: 3px solid #dee2e6; border-radius: 8px;';
                previewContainer.appendChild(newImg);
            } else if (preview) {
                preview.src = imageUrl + '?v=' + new Date().getTime();
            }

            const removeBtn = document.getElementById('removeProfilePicture');
            if (removeBtn) {
                removeBtn.style.display = 'block';
            }

            document.getElementById('removeProfilePictureInput').value = '0';
        },

        attachRemovePhotoListener() {
            const removeBtn = document.getElementById('removeProfilePicture');
            if (removeBtn && !removeBtn.hasAttribute('data-listener-attached')) {
                removeBtn.setAttribute('data-listener-attached', 'true');
                removeBtn.addEventListener('click', () => {
                    document.getElementById('removeProfilePictureInput').value = '1';

                    const genderInput = document.querySelector('[name="gender"]');
                    const gender = genderInput ? genderInput.value : 'm';
                    const preview = document.getElementById('profile_picture_preview');
                    const placeholder = document.getElementById('profile_picture_placeholder');

                    if (preview) {
                        preview.style.display = 'none';
                    }

                    if (placeholder) {
                        placeholder.style.display = 'flex';
                        const icon = placeholder.querySelector('i');
                        if (icon) {
                            icon.style.color = gender === 'f' ? '#e91e63' : '#2196f3';
                        }
                        const text = placeholder.querySelector('p');
                        if (text) {
                            text.textContent = 'Default avatar will be used';
                        }
                    } else if (preview) {
                        const previewContainer = preview.parentElement;
                        const newPlaceholder = document.createElement('div');
                        newPlaceholder.className = 'mx-auto flex items-center justify-center';
                        newPlaceholder.id = 'profile_picture_placeholder';
                        newPlaceholder.style.cssText = 'width: 300px; height: 400px; background-color: #f0f0f0; border: 3px solid #dee2e6; border-radius: 8px;';
                        newPlaceholder.innerHTML = `
                            <div class="text-center">
                                <i class="bi bi-person-circle" style="font-size: 60px; color: ${gender === 'f' ? '#e91e63' : '#2196f3'};"></i>
                                <p class="text-gray-500 mt-2 mb-0">Default avatar will be used</p>
                            </div>
                        `;
                        previewContainer.appendChild(newPlaceholder);
                    }

                    removeBtn.style.display = 'none';
                });
            }
        },
        @endif

    };
}
</script>
@endpush
