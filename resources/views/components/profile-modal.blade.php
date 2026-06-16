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

    // JSON data for dynamic list Alpine components
    $initEmergencyContacts = !$isCreate && $user ? ($user->emergency_contacts ?? []) : [];
    $initHealthConditions  = !$isCreate && $user ? ($user->health_conditions ?? []) : [];
    $initDocuments         = !$isCreate && $user ? ($user->documents ?? []) : [];
    $docUploadUrl  = !$isCreate && $user ? route('member.upload-document', $user->id) : '';
    $docDeleteUrl  = !$isCreate && $user ? route('member.delete-document', $user->id) : '';
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
                        <i class="bi bi-shield-plus mr-1"></i>Medical & Docs
                    </button>
                </nav>
            </div>

            <!-- Form -->
            <form method="POST" action="{{ $formAction }}" id="{{ $formId }}" class="profile-modal-form" @submit.prevent="submitForm()">
                @csrf
                @if(!$isCreate)
                    @method($formMethod)
                @endif

                <!-- Tab Content -->
                <div class="p-4 overflow-y-auto relative" style="height: 500px;">

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
                                                    :canvasHeight="500"
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

                        {{-- Section: Contact --}}
                        <div class="flex items-center gap-2 mb-3">
                            <i class="bi bi-person-lines-fill text-primary text-sm"></i>
                            <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Contact</span>
                            <div class="flex-1 h-px bg-gray-100"></div>
                        </div>

                        <div class="mb-3">
                            <label for="{{ $formId }}_full_name" class="form-label">Full Name <span class="text-red-500">*</span></label>
                            <input type="text" class="form-control @error('full_name') is-invalid @enderror" id="{{ $formId }}_full_name" name="full_name" value="{{ $userName }}" required placeholder="Enter full name">
                            @error('full_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-3">
                            @if($showEmailField)
                            <div>
                                <label for="{{ $formId }}_email" class="form-label">
                                    Email
                                    @if(($isCreate || $showRelationshipFields) && !$showPasswordFields)
                                        <span class="text-gray-400 font-normal text-xs">(optional)</span>
                                    @elseif($showPasswordFields)
                                        <span class="text-red-500">*</span>
                                    @endif
                                </label>
                                <input type="email" class="form-control @error('email') is-invalid @enderror" id="{{ $formId }}_email" name="email" value="{{ $userEmail }}" {{ ($showPasswordFields || (!$isCreate && !$showRelationshipFields)) ? 'required' : '' }} placeholder="email@example.com">
                                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            @endif
                            <div @if(!$showEmailField) class="md:col-span-2" @endif>
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
                                @error('mobile')<div class="invalid-feedback block">{{ $message }}</div>@enderror
                            </div>
                        </div>

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
                                @error('password')<div class="invalid-feedback block">{{ $message }}</div>@enderror
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

                        {{-- Section: Demographics --}}
                        <div class="flex items-center gap-2 mb-3 mt-1">
                            <i class="bi bi-person-badge text-primary text-sm"></i>
                            <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Demographics</span>
                            <div class="flex-1 h-px bg-gray-100"></div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-3">
                            <div>
                                <x-gender-dropdown
                                    name="gender"
                                    :id="$formId . '_gender'"
                                    label="Gender"
                                    :value="$userGender"
                                    :required="true"
                                    :error="$errors->first('gender')" />
                            </div>
                            <div>
                                <x-marital-status-dropdown
                                    name="marital_status"
                                    :id="$formId . '_marital_status'"
                                    label="Marital Status"
                                    :value="$userMaritalStatus"
                                    :error="$errors->first('marital_status')" />
                            </div>
                        </div>

                        <div class="mb-3">
                            <x-birthdate-dropdown
                                name="birthdate"
                                :id="$formId . '_birthdate'"
                                label="Date of Birth"
                                :value="$userBirthdate"
                                :required="true"
                                :min-age="$isCreate ? 0 : 10"
                                :max-age="120"
                                :error="$errors->first('birthdate')" />
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

                        {{-- Section: Personal --}}
                        <div class="flex items-center gap-2 mb-3 mt-1">
                            <i class="bi bi-chat-quote text-primary text-sm"></i>
                            <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Personal</span>
                            <div class="flex-1 h-px bg-gray-100"></div>
                        </div>

                        <div class="mb-3">
                            <label for="{{ $formId }}_motto" class="form-label">Personal Motto</label>
                            <textarea class="form-control @error('motto') is-invalid @enderror" id="{{ $formId }}_motto" name="motto" rows="2" placeholder="A quote or motto that inspires you...">{{ $userMotto }}</textarea>
                            @error('motto')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        {{-- Relationship fields --}}
                        @if($showRelationshipFields)
                        <div class="flex items-center gap-2 mb-3 mt-1">
                            <i class="bi bi-people text-primary text-sm"></i>
                            <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Relationship</span>
                            <div class="flex-1 h-px bg-gray-100"></div>
                        </div>
                        <div class="mb-3">
                            <x-relationship-dropdown
                                name="relationship_type"
                                :id="$formId . '_relationship_type'"
                                label="Relationship"
                                :value="old('relationship_type', $relationship->relationship_type ?? '')"
                                :required="true"
                                :error="$errors->first('relationship_type')" />
                        </div>
                        <div class="mb-3 flex items-center gap-2">
                            <input type="checkbox" class="form-check-input" id="{{ $formId }}_is_billing_contact" name="is_billing_contact" value="1" {{ old('is_billing_contact', $relationship->is_billing_contact ?? false) ? 'checked' : '' }}>
                            <label class="form-check-label" for="{{ $formId }}_is_billing_contact">Set as billing contact</label>
                        </div>
                        @endif

                    </div>

                    {{-- ===== Social Media Tab ===== --}}
                    <div x-show="activeTab === 'social'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
                        <x-social-links-editor :links="$formLinks" :containerId="$formId . '_socialLinksContainer'" />
                    </div>

                    {{-- ===== Additional Info Tab ===== --}}
                    <div x-show="activeTab === 'additional'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">

                        {{-- Emergency Contacts --}}
                        <div class="mb-5">
                            <div class="flex justify-between items-center mb-2">
                                <label class="form-label mb-0 font-semibold">
                                    <i class="bi bi-telephone-fill text-red-500 mr-1"></i>Emergency Contacts
                                </label>
                                <button type="button" @click="addContact()" class="text-xs text-primary hover:underline flex items-center gap-1">
                                    <i class="bi bi-plus-circle"></i> Add Contact
                                </button>
                            </div>
                            <div class="flex flex-col gap-2">
                                <template x-for="(contact, i) in contacts" :key="i">
                                    <div class="grid grid-cols-12 gap-2 p-3 bg-gray-50 rounded-lg items-center">
                                        <div class="col-span-3">
                                            <input type="text" x-model="contact.name" placeholder="Full name" class="form-control form-control-sm">
                                        </div>
                                        <div class="col-span-3">
                                            <select x-model="contact.relationship" class="form-control form-control-sm">
                                                <option value="">Relationship</option>
                                                <option value="parent">Parent</option>
                                                <option value="spouse">Spouse</option>
                                                <option value="sibling">Sibling</option>
                                                <option value="child">Child</option>
                                                <option value="friend">Friend</option>
                                                <option value="other">Other</option>
                                            </select>
                                        </div>
                                        {{-- Phone: code picker + number combined --}}
                                        <div class="col-span-5">
                                            <div class="tf-input-group"
                                                 x-data="{
                                                    open: false, search: '',
                                                    get flag() { const c = (window._phoneCodes||[]).find(x => x.c === contact.phone_code); return c ? c.f : 'bh'; },
                                                    get list() { const a = window._phoneCodes||[]; if (!this.search) return a; const t = this.search.toLowerCase(); return a.filter(c => c.n.toLowerCase().includes(t)||c.c.includes(t)); },
                                                    pick(c) { contact.phone_code = c.c; this.open = false; this.search = ''; }
                                                 }">
                                                <div class="relative flex-shrink-0">
                                                    <button type="button"
                                                            @click="open = !open"
                                                            @click.outside="open = false"
                                                            class="h-full px-2 py-1 flex items-center gap-1 border-r border-primary/20 bg-transparent hover:bg-gray-50 transition-colors cursor-pointer rounded-l-xl whitespace-nowrap">
                                                        <span :class="'fi fi-' + flag"></span>
                                                        <span x-text="contact.phone_code || '+973'" class="text-xs font-medium text-gray-700"></span>
                                                        <i class="bi bi-chevron-down text-xs" :class="{'rotate-180': open}"></i>
                                                    </button>
                                                    <div x-show="open" x-cloak
                                                         class="absolute left-0 z-50 mt-1 w-56 bg-white border border-gray-200 rounded-xl shadow-lg overflow-hidden"
                                                         style="top:100%">
                                                        <div class="p-2 border-b border-gray-100">
                                                            <input type="text" x-model="search" @click.stop
                                                                   placeholder="Search..."
                                                                   class="w-full px-2 py-1 text-xs border border-gray-200 rounded focus:outline-none focus:border-primary">
                                                        </div>
                                                        <div class="max-h-44 overflow-y-auto">
                                                            <template x-for="c in list" :key="c.f + c.c">
                                                                <div @click="pick(c)"
                                                                     class="flex items-center gap-2 px-3 py-1.5 hover:bg-gray-50 cursor-pointer"
                                                                     :class="contact.phone_code === c.c ? 'bg-primary/5 font-semibold' : ''">
                                                                    <span :class="'fi fi-' + c.f"></span>
                                                                    <span class="text-xs" x-text="c.n + ' (' + c.c + ')'"></span>
                                                                </div>
                                                            </template>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="flex-1">
                                                    <input type="tel" x-model="contact.phone" placeholder="Phone number"
                                                           class="w-full px-2 py-1 text-sm bg-transparent focus:outline-none">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-span-1 flex justify-center">
                                            <button type="button" @click="removeContact(i)" class="text-red-400 hover:text-red-600 transition-colors">
                                                <i class="bi bi-x-circle"></i>
                                            </button>
                                        </div>
                                    </div>
                                </template>
                            </div>
                            <p x-show="contacts.length === 0" class="text-gray-400 text-sm text-center py-3 border border-dashed border-gray-200 rounded-lg mt-2">
                                No emergency contacts yet. Click "Add Contact" above.
                            </p>
                        </div>

                        {{-- Health Conditions --}}
                        <div class="mb-5">
                            <div class="flex justify-between items-center mb-2">
                                <label class="form-label mb-0 font-semibold">
                                    <i class="bi bi-clipboard2-pulse-fill text-amber-500 mr-1"></i>Chronic Health Conditions
                                </label>
                                <button type="button" @click="addCondition()" class="text-xs text-primary hover:underline flex items-center gap-1">
                                    <i class="bi bi-plus-circle"></i> Add Condition
                                </button>
                            </div>
                            <div class="flex flex-col gap-2">
                                <template x-for="(cond, i) in conditions" :key="i">
                                    <div class="p-3 bg-amber-50 border border-amber-100 rounded-lg">
                                        <div class="grid grid-cols-12 gap-2 items-start">
                                            <div class="col-span-5">
                                                <input type="text" x-model="cond.condition" placeholder="e.g. Asthma, Diabetes" class="form-control form-control-sm">
                                            </div>
                                            <div class="col-span-3">
                                                <input type="date" x-model="cond.noted_at" class="form-control form-control-sm">
                                            </div>
                                            <div class="col-span-3">
                                                <input type="text" x-model="cond.notes" placeholder="Notes" class="form-control form-control-sm">
                                            </div>
                                            <div class="col-span-1 flex justify-center pt-1">
                                                <button type="button" @click="removeCondition(i)" class="text-red-400 hover:text-red-600 transition-colors">
                                                    <i class="bi bi-x-circle"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                            <p x-show="conditions.length === 0" class="text-gray-400 text-sm text-center py-3 border border-dashed border-gray-200 rounded-lg mt-2">
                                No conditions recorded. Click "Add Condition" above.
                            </p>
                        </div>

                        {{-- Identity Documents with drag-and-drop upload --}}
                        <div class="mb-2">
                            <div class="flex justify-between items-center mb-2">
                                <label class="form-label mb-0 font-semibold">
                                    <i class="bi bi-file-earmark-person-fill text-primary mr-1"></i>Identity Documents
                                </label>
                                <button type="button" @click="addDoc()" class="text-xs text-primary hover:underline flex items-center gap-1">
                                    <i class="bi bi-plus-circle"></i> Add Document
                                </button>
                            </div>
                            <div class="flex flex-col gap-3">
                                <template x-for="(doc, i) in docs" :key="i">
                                    <div class="p-3 bg-gray-50 rounded-lg border border-gray-200">
                                        <div class="grid grid-cols-12 gap-2 items-center mb-2">
                                            <div class="col-span-5">
                                                <select x-model="doc.type" class="form-control form-control-sm">
                                                    <option value="">Document Type</option>
                                                    <option value="National ID">National ID</option>
                                                    <option value="Passport">Passport</option>
                                                    <option value="CPR">CPR</option>
                                                    <option value="Driving Licence">Driving Licence</option>
                                                    <option value="Residence Permit">Residence Permit</option>
                                                    <option value="Other">Other</option>
                                                </select>
                                            </div>
                                            <div class="col-span-6">
                                                <input type="text" x-model="doc.number" placeholder="Document number" class="form-control form-control-sm font-mono">
                                            </div>
                                            <div class="col-span-1 flex justify-center">
                                                <button type="button" @click="openDocDelete(i)" class="text-red-400 hover:text-red-600 transition-colors" title="Delete document">
                                                    <i class="bi bi-x-circle"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div x-data="{ localFileInput: null }"
                                             x-init="localFileInput = $el.querySelector('input[type=file]')"
                                             class="rounded-lg border-2 border-dashed transition-colors"
                                             :class="dragging === i ? 'border-primary bg-primary/5' : 'border-gray-300 hover:border-primary/50 cursor-pointer'"
                                             @dragover.prevent="dragging = i"
                                             @dragleave.prevent="dragging = null"
                                             @drop.prevent="handleDocDrop($event, i)"
                                             @click="localFileInput.click()">
                                            <input type="file" class="hidden" accept=".pdf,.jpg,.jpeg,.png,.webp,.gif,.bmp,.tiff" @change="handleDocFileSelect($event, i)">

                                            {{-- Uploading: progress bar --}}
                                            <div x-show="uploading[i]" class="py-3 px-4">
                                                <div class="flex items-center justify-between mb-1">
                                                    <span class="text-xs text-primary font-medium">Uploading...</span>
                                                    <span class="text-xs text-primary font-semibold" x-text="(uploadProgress[i] || 0) + '%'"></span>
                                                </div>
                                                <div class="h-2 bg-gray-200 rounded-full overflow-hidden">
                                                    <div class="h-full bg-primary rounded-full transition-all duration-200"
                                                         :style="'width:' + (uploadProgress[i] || 0) + '%'"></div>
                                                </div>
                                            </div>

                                            {{-- File uploaded --}}
                                            <div x-show="!uploading[i] && doc.file_path" class="py-2 px-3 flex items-center gap-3">
                                                <i class="bi bi-file-earmark-check-fill text-green-500 text-xl"></i>
                                                <div class="flex-1 min-w-0">
                                                    <p class="text-sm font-medium text-gray-700 truncate" x-text="doc.file_name || doc.file_path"></p>
                                                    <p class="text-xs text-gray-400">Click to replace</p>
                                                </div>
                                                <a :href="doc.file_url || ('/storage/' + doc.file_path)" target="_blank" @click.stop class="flex-shrink-0 text-xs text-primary hover:underline">
                                                    View <i class="bi bi-box-arrow-up-right"></i>
                                                </a>
                                            </div>

                                            {{-- Empty state --}}
                                            <div x-show="!uploading[i] && !doc.file_path" class="py-3 flex flex-col items-center gap-1 text-gray-400">
                                                <i class="bi bi-cloud-upload text-2xl"></i>
                                                <p class="text-xs">Drag & drop or click to upload</p>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                            <p x-show="docs.length === 0" class="text-gray-400 text-sm text-center py-3 border border-dashed border-gray-200 rounded-lg mt-2">
                                No documents added. Click "Add Document" above.
                            </p>
                        </div>

                    </div>

                    {{-- Document delete confirmation overlay — fixed so it centers over the whole screen --}}
                    <div x-show="docDeleteState.open" x-cloak
                         class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/60"
                         @click.self="cancelDocDelete()">
                        <div class="bg-white rounded-xl shadow-2xl p-5 mx-4 w-full max-w-sm" @click.stop>
                            <div class="flex items-center gap-3 mb-4">
                                <div class="w-11 h-11 rounded-full bg-red-100 flex items-center justify-center flex-shrink-0">
                                    <i class="bi bi-trash-fill text-red-500 text-lg"></i>
                                </div>
                                <div>
                                    <h6 class="font-bold text-gray-900 mb-0">Delete Document</h6>
                                    <p class="text-xs text-gray-500 mb-0">This permanently removes the file from storage and cannot be undone.</p>
                                </div>
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Type <span class="font-mono font-bold text-red-600 bg-red-50 px-1.5 py-0.5 rounded" x-text="docDeleteState.expectedNumber"></span> to confirm deletion:
                                </label>
                                <input type="text"
                                       x-model="docDeleteState.inputValue"
                                       @keydown.enter="docDeleteState.inputValue === docDeleteState.expectedNumber && !docDeleteState.loading && confirmDocDelete()"
                                       @keydown.escape="cancelDocDelete()"
                                       placeholder="Type to confirm..."
                                       class="form-control form-control-sm"
                                       autocomplete="off">
                                <p x-show="docDeleteState.inputValue && docDeleteState.inputValue !== docDeleteState.expectedNumber"
                                   class="text-xs text-red-500 mt-1">
                                    Doesn't match — type exactly as shown above.
                                </p>
                            </div>
                            <div class="flex gap-2 justify-end">
                                <button type="button" @click="cancelDocDelete()" class="btn btn-secondary btn-sm" :disabled="docDeleteState.loading">
                                    Cancel
                                </button>
                                <button type="button"
                                        @click="confirmDocDelete()"
                                        :disabled="docDeleteState.inputValue !== docDeleteState.expectedNumber || docDeleteState.loading"
                                        class="btn btn-danger btn-sm">
                                    <span x-show="!docDeleteState.loading"><i class="bi bi-trash mr-1"></i>Delete File</span>
                                    <span x-show="docDeleteState.loading"><span class="inline-block animate-spin">&#8635;</span> Deleting...</span>
                                </button>
                            </div>
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
                    <button type="button" class="btn btn-success" id="{{ $formId }}_submitBtn" @click="submitForm()" :disabled="isSubmitting">
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

<x-toast-notification />

@push('scripts')
<script>
// Shared phone country data for inline code pickers inside x-for
window._phoneCodes = [
    { c:'+973',f:'bh',n:'Bahrain' },{ c:'+966',f:'sa',n:'Saudi Arabia' },{ c:'+971',f:'ae',n:'UAE' },
    { c:'+974',f:'qa',n:'Qatar' },{ c:'+965',f:'kw',n:'Kuwait' },{ c:'+968',f:'om',n:'Oman' },
    { c:'+962',f:'jo',n:'Jordan' },{ c:'+961',f:'lb',n:'Lebanon' },{ c:'+964',f:'iq',n:'Iraq' },
    { c:'+970',f:'ps',n:'Palestine' },{ c:'+20',f:'eg',n:'Egypt' },{ c:'+212',f:'ma',n:'Morocco' },
    { c:'+213',f:'dz',n:'Algeria' },{ c:'+216',f:'tn',n:'Tunisia' },{ c:'+91',f:'in',n:'India' },
    { c:'+92',f:'pk',n:'Pakistan' },{ c:'+880',f:'bd',n:'Bangladesh' },{ c:'+94',f:'lk',n:'Sri Lanka' },
    { c:'+63',f:'ph',n:'Philippines' },{ c:'+62',f:'id',n:'Indonesia' },{ c:'+60',f:'my',n:'Malaysia' },
    { c:'+65',f:'sg',n:'Singapore' },{ c:'+66',f:'th',n:'Thailand' },{ c:'+84',f:'vn',n:'Vietnam' },
    { c:'+1',f:'us',n:'United States' },{ c:'+1',f:'ca',n:'Canada' },{ c:'+44',f:'gb',n:'United Kingdom' },
    { c:'+49',f:'de',n:'Germany' },{ c:'+33',f:'fr',n:'France' },{ c:'+39',f:'it',n:'Italy' },
    { c:'+34',f:'es',n:'Spain' },{ c:'+31',f:'nl',n:'Netherlands' },{ c:'+7',f:'ru',n:'Russia' },
    { c:'+86',f:'cn',n:'China' },{ c:'+81',f:'jp',n:'Japan' },{ c:'+82',f:'kr',n:'South Korea' },
    { c:'+55',f:'br',n:'Brazil' },{ c:'+52',f:'mx',n:'Mexico' },{ c:'+61',f:'au',n:'Australia' },
    { c:'+27',f:'za',n:'South Africa' },{ c:'+234',f:'ng',n:'Nigeria' },{ c:'+254',f:'ke',n:'Kenya' },
    { c:'+90',f:'tr',n:'Turkey' },{ c:'+98',f:'ir',n:'Iran' },{ c:'+32',f:'be',n:'Belgium' },
    { c:'+41',f:'ch',n:'Switzerland' },{ c:'+43',f:'at',n:'Austria' },{ c:'+46',f:'se',n:'Sweden' },
    { c:'+47',f:'no',n:'Norway' },{ c:'+45',f:'dk',n:'Denmark' },{ c:'+48',f:'pl',n:'Poland' },
];

function {{ $alpineComponent }}() {
    return {
        open: false,
        activeTab: '{{ $defaultTab }}',
        tabs: {!! json_encode($showPhotoTab ? ['photo', 'personal', 'social', 'additional'] : ['personal', 'social', 'additional']) !!},
        isSubmitting: false,
        isCreateMode: {{ $isCreate ? 'true' : 'false' }},
        showPasswordFields: {{ $showPasswordFields ? 'true' : 'false' }},
        showRelationshipFields: {{ $showRelationshipFields ? 'true' : 'false' }},
        profilePicturePublic: {{ $profilePicturePublic ? 'true' : 'false' }},

        // Medical & Contacts data
        contacts: @json($initEmergencyContacts),
        conditions: @json($initHealthConditions),
        docs: @json($initDocuments),
        uploading: {},
        uploadProgress: {},
        dragging: null,
        docUploadUrl: '{{ $docUploadUrl }}',
        docDeleteUrl: '{{ $docDeleteUrl }}',
        docDeleteState: { open: false, index: -1, expectedNumber: '', inputValue: '', loading: false },

        addContact() { this.contacts.push({ name: '', relationship: '', phone_code: '+973', phone: '' }); },
        removeContact(i) { this.contacts.splice(i, 1); },
        addCondition() {
            const today = new Date().toISOString().split('T')[0];
            this.conditions.push({ condition: '', noted_at: today, notes: '' });
        },
        removeCondition(i) { this.conditions.splice(i, 1); },
        addDoc() { this.docs.push({ type: '', number: '', file_path: null, file_name: null, file_url: null, uploaded_at: '' }); },
        removeDoc(i) {
            this.docs.splice(i, 1);
            const u = {}, p = {};
            Object.keys(this.uploading).forEach(k => { const ki = parseInt(k); if (ki < i) { u[ki] = this.uploading[k]; p[ki] = this.uploadProgress[k]; } else if (ki > i) { u[ki - 1] = this.uploading[k]; p[ki - 1] = this.uploadProgress[k]; } });
            this.uploading = u; this.uploadProgress = p;
        },
        openDocDelete(i) {
            const doc = this.docs[i];
            if (!doc || !doc.file_path) { this.removeDoc(i); return; }
            const label = doc.number || doc.type || 'DELETE';
            this.docDeleteState = { open: true, index: i, expectedNumber: label, inputValue: '', loading: false };
        },
        cancelDocDelete() {
            this.docDeleteState = { open: false, index: -1, expectedNumber: '', inputValue: '', loading: false };
        },
        async confirmDocDelete() {
            const s = this.docDeleteState;
            if (s.inputValue !== s.expectedNumber || s.loading) return;
            s.loading = true;
            const doc = this.docs[s.index];
            if (doc && doc.file_path && this.docDeleteUrl) {
                try {
                    const resp = await fetch(this.docDeleteUrl, {
                        method: 'DELETE',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('[name="_token"]').value,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({ file_path: doc.file_path }),
                    });
                    const data = await resp.json();
                    if (!data.success) {
                        window.showToast && window.showToast('error', data.message || 'Could not delete the file.');
                        s.loading = false;
                        return;
                    }
                } catch (ex) {
                    window.showToast && window.showToast('error', 'Delete failed. Please try again.');
                    s.loading = false;
                    return;
                }
            }
            this.removeDoc(s.index);
            this.docDeleteState = { open: false, index: -1, expectedNumber: '', inputValue: '', loading: false };
            window.showToast && window.showToast('success', 'Document deleted successfully.');
        },
        handleDocDrop(e, i) { this.dragging = null; const f = e.dataTransfer.files[0]; if (f) this.uploadDoc(f, i); },
        handleDocFileSelect(e, i) { const f = e.target.files[0]; if (f) this.uploadDoc(f, i); e.target.value = ''; },
        uploadDoc(file, i) {
            if (!this.docUploadUrl) return;
            this.uploading = { ...this.uploading, [i]: true };
            this.uploadProgress = { ...this.uploadProgress, [i]: 0 };

            const fd = new FormData();
            fd.append('file', file);
            fd.append('_token', document.querySelector('[name="_token"]').value);

            const xhr = new XMLHttpRequest();
            xhr.open('POST', this.docUploadUrl);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

            xhr.upload.onprogress = (e) => {
                if (e.lengthComputable) {
                    this.uploadProgress = { ...this.uploadProgress, [i]: Math.round(e.loaded / e.total * 100) };
                }
            };
            xhr.onload = () => {
                try {
                    const data = JSON.parse(xhr.responseText);
                    if (data.success) {
                        this.docs.splice(i, 1, { ...this.docs[i], file_path: data.path, file_name: data.file_name, file_url: data.url, uploaded_at: new Date().toISOString().split('T')[0] });
                    } else {
                        window.showToast && window.showToast('error', data.message || 'Upload failed');
                    }
                } catch (ex) {
                    window.showToast && window.showToast('error', 'Upload failed');
                }
                this.uploading = { ...this.uploading, [i]: false };
            };
            xhr.onerror = () => {
                window.showToast && window.showToast('error', 'Upload failed. Please try again.');
                this.uploading = { ...this.uploading, [i]: false };
            };
            xhr.send(fd);
        },
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
                    this.syncProfilePicsOnPage(e.detail.url);
                }
            });

            // Global callback for cropper
            window.imageUploadSuccess = (result) => {
                const url = result?.url || (result?.path ? window.location.origin + '/storage/' + result.path : null);
                if (url) {
                    this.updateProfilePicturePreview(url);
                    this.syncProfilePicsOnPage(url);
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

        // Show an inline error on any field (text inputs OR custom tf-dropdowns)
        showInputError(inputId, message) {
            const input = document.getElementById(inputId);
            if (!input) return;
            if (input.type === 'hidden') {
                // Custom tf-dropdown: red border on trigger + tf-error span
                const wrapper = input.closest('[x-data]');
                if (!wrapper) return;
                const trigger = wrapper.querySelector('.tf-dropdown-trigger');
                if (trigger) { trigger.classList.add('border-red-500'); trigger.classList.remove('border-primary/20'); }
                let errSpan = wrapper.querySelector('.tf-error');
                if (!errSpan) {
                    errSpan = document.createElement('span');
                    errSpan.className = 'tf-error';
                    errSpan.setAttribute('role', 'alert');
                    input.insertAdjacentElement('afterend', errSpan);
                }
                errSpan.innerHTML = `<strong>${message}</strong>`;
                errSpan.style.display = '';
            } else {
                // Regular / password input
                input.classList.add('is-invalid');
                const relativeWrap = input.closest('.relative');
                const insertAfter = relativeWrap || input;
                let errDiv = insertAfter.nextElementSibling;
                if (!errDiv || !errDiv.classList.contains('invalid-feedback')) {
                    errDiv = document.createElement('div');
                    errDiv.className = 'invalid-feedback block';
                    insertAfter.insertAdjacentElement('afterend', errDiv);
                }
                errDiv.textContent = message;
                errDiv.style.display = 'block';
            }
        },

        clearInputError(inputId) {
            const input = document.getElementById(inputId);
            if (!input) return;
            if (input.type === 'hidden') {
                const wrapper = input.closest('[x-data]');
                if (!wrapper) return;
                const trigger = wrapper.querySelector('.tf-dropdown-trigger');
                if (trigger) { trigger.classList.remove('border-red-500'); trigger.classList.add('border-primary/20'); }
                const errSpan = wrapper.querySelector('.tf-error');
                if (errSpan) errSpan.style.display = 'none';
            } else {
                input.classList.remove('is-invalid');
                const relativeWrap = input.closest('.relative');
                const checkAfter = relativeWrap || input;
                const errDiv = checkAfter.nextElementSibling;
                if (errDiv && errDiv.classList.contains('invalid-feedback')) errDiv.style.display = 'none';
            }
        },

        validateForm() {
            if (!this.isCreateMode) return true;

            let valid = true;
            const fid = '{{ $formId }}';

            // Full name
            const nameEl = document.getElementById(fid + '_full_name');
            if (!nameEl || !nameEl.value.trim()) {
                this.showInputError(fid + '_full_name', 'Full name is required.'); valid = false;
            } else { this.clearInputError(fid + '_full_name'); }

            // Email
            const emailEl = document.getElementById(fid + '_email');
            if (this.showPasswordFields) {
                if (!emailEl || !emailEl.value.trim()) {
                    this.showInputError(fid + '_email', 'Email address is required.'); valid = false;
                } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailEl.value.trim())) {
                    this.showInputError(fid + '_email', 'Please enter a valid email address.'); valid = false;
                } else { this.clearInputError(fid + '_email'); }
            }

            // Password + confirmation
            if (this.showPasswordFields) {
                const pw1 = document.getElementById(fid + '_password');
                const pw2 = document.getElementById(fid + '_password_confirmation');
                if (!pw1 || !pw1.value) {
                    this.showInputError(fid + '_password', 'Password is required.'); valid = false;
                } else if (pw1.value.length < 8) {
                    this.showInputError(fid + '_password', 'Password must be at least 8 characters.'); valid = false;
                } else { this.clearInputError(fid + '_password'); }

                if (pw1 && pw1.value.length >= 8) {
                    if (!pw2 || !pw2.value) {
                        this.showInputError(fid + '_password_confirmation', 'Please confirm your password.'); valid = false;
                    } else if (pw2.value !== pw1.value) {
                        this.showInputError(fid + '_password_confirmation', 'Passwords do not match.'); valid = false;
                    } else { this.clearInputError(fid + '_password_confirmation'); }
                }
            }

            // Gender (custom dropdown — hidden input)
            const genderEl = document.getElementById(fid + '_gender');
            if (!genderEl || !genderEl.value) {
                this.showInputError(fid + '_gender', 'Please select a gender.'); valid = false;
            } else { this.clearInputError(fid + '_gender'); }

            // Birthdate (custom dropdown — hidden input)
            const bdEl = document.getElementById(fid + '_birthdate');
            if (!bdEl || !bdEl.value) {
                this.showInputError(fid + '_birthdate', 'Please select a date of birth.'); valid = false;
            } else { this.clearInputError(fid + '_birthdate'); }

            // Nationality (custom dropdown — hidden input)
            const natEl = document.getElementById(fid + '_nationality');
            if (!natEl || !natEl.value) {
                this.showInputError(fid + '_nationality', 'Please select a nationality.'); valid = false;
            } else { this.clearInputError(fid + '_nationality'); }

            // Relationship type (custom dropdown — only when showRelationshipFields)
            if (this.showRelationshipFields) {
                const relEl = document.getElementById(fid + '_relationship_type');
                if (!relEl || !relEl.value) {
                    this.showInputError(fid + '_relationship_type', 'Please select a relationship type.'); valid = false;
                } else { this.clearInputError(fid + '_relationship_type'); }
            }

            if (!valid) {
                this.activeTab = 'personal';
                if (typeof Toast !== 'undefined') {
                    Toast.error('Required Fields Missing', 'Please fill in all highlighted fields before submitting.');
                }
            }
            return valid;
        },

        showFieldErrors(errors) {
            const fid = '{{ $formId }}';
            const map = {
                full_name:         fid + '_full_name',
                email:             fid + '_email',
                password:          fid + '_password',
                gender:            fid + '_gender',
                birthdate:         fid + '_birthdate',
                nationality:       fid + '_nationality',
                relationship_type: fid + '_relationship_type',
                blood_type:        fid + '_blood_type',
                mobile:            fid + '_mobile_number',
                motto:             fid + '_motto',
            };
            Object.keys(errors).forEach(field => {
                if (map[field]) this.showInputError(map[field], errors[field][0]);
            });
            this.activeTab = 'personal';
            const count = Object.keys(errors).length;
            if (typeof Toast !== 'undefined') {
                Toast.error('Validation Failed', `${count} error${count > 1 ? 's' : ''} — please review the highlighted fields.`);
            }
        },

        submitForm() {
            if (!this.validateForm()) return;

            // Block if a document file is still uploading
            if (Object.values(this.uploading).some(v => v)) {
                window.showToast && window.showToast('warning', 'Please wait for the file upload to finish before saving.');
                return;
            }

            this.isSubmitting = true;
            const form = document.getElementById('{{ $formId }}');
            const formData = new FormData(form);

            // Explicitly inject managed JSON fields — never rely on Alpine :value binding
            formData.set('emergency_contacts_json', JSON.stringify(this.contacts));
            formData.set('health_conditions_json',  JSON.stringify(this.conditions));
            formData.set('documents_json',           JSON.stringify(this.docs));

            fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
            .then(async response => {
                const data = await response.json();
                if (response.ok && data.success) {
                    @if(!$isCreate)
                    if (data.profile_picture_url) {
                        this.syncProfilePicsOnPage(data.profile_picture_url);
                    }
                    // Live-update the profile page DOM — no reload needed
                    if (data.member) {
                        window.dispatchEvent(new CustomEvent('member-profile-updated', { detail: data.member }));
                    }
                    @endif

                    window.showToast('success', data.message || '{{ $isCreate ? "Member created successfully!" : "Profile updated successfully!" }}');
                    this.isSubmitting = false;
                    setTimeout(() => {
                        this.open = false;
                        if (data.redirect) {
                            window.location.href = data.redirect;
                        }
                    }, 900);
                } else if (response.status === 422 && data.errors) {
                    this.showFieldErrors(data.errors);
                    this.isSubmitting = false;
                } else {
                    throw new Error(data.message || '{{ $isCreate ? "Creation failed" : "Update failed" }}');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                if (typeof window.showToast === 'function') {
                    window.showToast('error', error.message || 'Something went wrong. Please try again.');
                } else {
                    this.showAlert('danger', error.message || 'Something went wrong. Please try again.');
                }
                this.isSubmitting = false;
            });
        },

        showAlert(type, message) {
            // Route through the global toast — never render an inline alert on the page.
            window.showToast(type === 'danger' ? 'error' : type, message);
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

        syncProfilePicsOnPage(imageUrl) {
            const newSrc = imageUrl.split('?')[0] + '?t=' + Date.now();

            // Nav bar avatars (desktop + mobile)
            document.querySelectorAll('img.user-avatar').forEach(img => {
                img.src = newSrc;
                img.style.display = '';
                const next = img.nextElementSibling;
                if (next && next.classList.contains('user-avatar-placeholder')) {
                    next.style.display = 'none';
                }
            });

            // Big profile pic on the member profile page
            const memberPic = document.getElementById('member-profile-pic');
            const memberPlaceholder = document.getElementById('member-profile-placeholder');
            if (memberPic) {
                memberPic.src = newSrc;
                memberPic.style.display = '';
                if (memberPlaceholder) memberPlaceholder.style.display = 'none';
            } else if (memberPlaceholder) {
                // First upload — no img exists yet, create one
                const img = document.createElement('img');
                img.id = 'member-profile-pic';
                img.className = 'w-full h-full';
                img.style.objectFit = 'cover';
                img.src = newSrc;
                memberPlaceholder.parentNode.insertBefore(img, memberPlaceholder);
                memberPlaceholder.style.display = 'none';
            }
        },

        attachRemovePhotoListener() {
            const removeBtn = document.getElementById('removeProfilePicture');
            if (removeBtn && !removeBtn.hasAttribute('data-listener-attached')) {
                removeBtn.setAttribute('data-listener-attached', 'true');
                removeBtn.addEventListener('click', () => {
                    document.getElementById('removeProfilePictureInput').value = '1';

                    const genderInput = document.querySelector('[name="gender"]');
                    const gender = genderInput ? genderInput.value : 'Male';
                    const preview = document.getElementById('profile_picture_preview');
                    const placeholder = document.getElementById('profile_picture_placeholder');

                    if (preview) {
                        preview.style.display = 'none';
                    }

                    if (placeholder) {
                        placeholder.style.display = 'flex';
                        const icon = placeholder.querySelector('i');
                        if (icon) {
                            icon.style.color = gender === 'Female' ? '#e91e63' : '#2196f3';
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
                                <i class="bi bi-person-circle" style="font-size: 60px; color: ${gender === 'Female' ? '#e91e63' : '#2196f3'};"></i>
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
