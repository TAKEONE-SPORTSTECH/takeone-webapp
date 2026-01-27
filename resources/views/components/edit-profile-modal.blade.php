@props([
    'user',
    'formAction',
    'formMethod' => 'PUT',
    'cancelUrl' => null,
    'showRelationshipFields' => false,
    'relationship' => null,
])

<!-- Profile Edit Modal -->
<div class="modal fade" id="editProfileModal" tabindex="-1" aria-labelledby="editProfileModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width: 75%; width: 1000px;">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="editProfileModalLabel">
                    <i class="bi bi-person-circle me-2"></i>Edit Profile
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <!-- Tab Navigation -->
                <ul class="nav nav-tabs nav-fill border-bottom" id="profileEditTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="photo-tab" data-bs-toggle="tab" data-bs-target="#photo" type="button" role="tab" aria-controls="photo" aria-selected="true">
                            <i class="bi bi-camera me-1"></i>Profile Photo
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="personal-tab" data-bs-toggle="tab" data-bs-target="#personal" type="button" role="tab" aria-controls="personal" aria-selected="false">
                            <i class="bi bi-person-badge me-1"></i>Personal Info
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="social-tab" data-bs-toggle="tab" data-bs-target="#social" type="button" role="tab" aria-controls="social" aria-selected="false">
                            <i class="bi bi-share me-1"></i>Social Media
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="additional-tab" data-bs-toggle="tab" data-bs-target="#additional" type="button" role="tab" aria-controls="additional" aria-selected="false">
                            <i class="bi bi-info-circle me-1"></i>Additional Info
                        </button>
                    </li>
                </ul>

                <form method="POST" action="{{ $formAction }}" id="profileEditForm">
                    @csrf
                    @method($formMethod)

                    <!-- Tab Content -->
                    <div class="tab-content p-4" id="profileEditTabContent" style="height: 500px; overflow-y: auto;">
                        <!-- Profile Photo Tab -->
                        <div class="tab-pane fade show active" id="photo" role="tabpanel" aria-labelledby="photo-tab">
                            @php
                                $currentProfileImage = '';
                                if ($user->profile_picture && file_exists(public_path('storage/' . $user->profile_picture))) {
                                    $currentProfileImage = asset('storage/' . $user->profile_picture);
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
                            @endphp

                            <div class="row g-4">
                                <!-- Left Side - Profile Picture -->
                                <div class="col-md-5">
                                    <div class="profile-photo-preview-container text-center">
                                        <div class="image-preview mb-3">
                                            @if($currentProfileImage)
                                                <img src="{{ $currentProfileImage }}"
                                                     alt="Profile Picture"
                                                     id="profile_picture_preview"
                                                     class="image-upload-preview"
                                                     style="width: 300px; height: 400px; object-fit: cover; border: 3px solid #dee2e6; border-radius: 8px;">
                                            @else
                                                <div class="image-placeholder"
                                                     id="profile_picture_placeholder"
                                                     style="width: 300px; height: 400px; background-color: #f0f0f0; border: 3px solid #dee2e6; border-radius: 8px; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
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
                                <div class="col-md-7">
                                    <div class="profile-photo-info" style="max-height: 400px; overflow: hidden;">
                                        <!-- Motivational Header -->
                                        <div class="mb-3">
                                            <h6 class="text-primary mb-2">
                                                <i class="bi bi-camera-fill me-2"></i>Your Athletic Identity
                                            </h6>
                                            <div class="alert alert-info border-0 shadow-sm p-2" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                                                <div class="d-flex align-items-start">
                                                    <i class="bi bi-lightbulb-fill fs-5 me-2"></i>
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
                                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                                        <div class="flex-grow-1 me-2">
                                                            <h6 class="mb-1">
                                                                <i class="bi bi-shield-lock-fill text-primary me-1"></i>Privacy Settings
                                                            </h6>
                                                            <p class="text-muted mb-0 small">Toggle to control visibility</p>
                                                        </div>
                                                        <div class="form-check form-switch" style="font-size: 1.2rem;">
                                                            <input class="form-check-input" type="checkbox" role="switch"
                                                                   id="profilePictureVisibility"
                                                                   name="profile_picture_is_public"
                                                                   value="1"
                                                                   {{ old('profile_picture_is_public', $user->profile_picture_is_public ?? true) ? 'checked' : '' }}>
                                                        </div>
                                                    </div>

                                                    <div id="visibilityStatus" class="alert mb-0 p-2" role="alert">
                                                        <div class="d-flex align-items-start">
                                                            <i class="bi me-2 mt-1" id="visibilityIcon" style="font-size: 1.3rem;"></i>
                                                            <div style="flex: 1; min-width: 0;">
                                                                <strong class="d-block" id="visibilityTitle" style="font-size: 0.9rem;"></strong>
                                                                <p class="mb-0 small" id="visibilityDescription"></p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Photo Guidelines & Action Buttons -->
                                        <div class="d-flex gap-3">
                                            <div style="flex: 1; min-width: 0;">
                                                <h6 class="text-muted mb-2 small">
                                                    <i class="bi bi-check-circle-fill me-1"></i>Quick Tips
                                                </h6>
                                                <ul class="list-unstyled text-muted mb-0 small">
                                                    <li class="mb-1"><i class="bi bi-check text-success me-1"></i>Recent, high-quality</li>
                                                    <li class="mb-1"><i class="bi bi-check text-success me-1"></i>Face clearly visible</li>
                                                    <li class="mb-1"><i class="bi bi-check text-success me-1"></i>Professional look</li>
                                                    <li class="mb-0"><i class="bi bi-check text-success me-1"></i>Good lighting</li>
                                                </ul>
                                            </div>
                                            <div style="min-width: 120px;">
                                                <h6 class="text-muted mb-2 small">
                                                    <i class="bi bi-gear-fill me-1"></i>Actions
                                                </h6>
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
                                                    buttonClass="btn btn-success btn-sm w-100"
                                                />
                                                <button type="button" class="btn btn-outline-danger btn-sm w-100 mt-2" id="removeProfilePicture" @if(!$currentProfileImage) style="display: none;" @endif>
                                                    <i class="bi bi-trash me-1"></i>Remove
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Personal Info Tab -->
                        <div class="tab-pane fade" id="personal" role="tabpanel" aria-labelledby="personal-tab">
                            <div class="mb-3">
                                <label for="full_name" class="form-label">Full Name</label>
                                <input type="text" class="form-control @error('full_name') is-invalid @enderror" id="full_name" name="full_name" value="{{ old('full_name', $user->full_name) }}" required>
                                @error('full_name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email', $user->email) }}" {{ $showRelationshipFields ? '' : 'required' }}>
                                @error('email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="mobile_number" class="form-label">Mobile Number</label>
                                <x-country-code-dropdown
                                    name="mobile_code"
                                    id="country_code"
                                    :value="old('mobile_code', $user->mobile['code'] ?? '+973')"
                                    :required="false"
                                    :error="$errors->first('mobile_code')">
                                    <input id="mobile_number" type="tel"
                                           class="form-control @error('mobile') is-invalid @enderror"
                                           name="mobile"
                                           value="{{ old('mobile', $user->mobile['number'] ?? '') }}"
                                           autocomplete="tel"
                                           placeholder="Phone number">
                                </x-country-code-dropdown>
                                @error('mobile')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-3">
                                    <label for="gender" class="form-label">Gender</label>
                                    <select class="form-select @error('gender') is-invalid @enderror" id="gender" name="gender" required>
                                        <option value="">Select Gender</option>
                                        <option value="m" {{ old('gender', $user->gender) == 'm' ? 'selected' : '' }}>♂️ Male</option>
                                        <option value="f" {{ old('gender', $user->gender) == 'f' ? 'selected' : '' }}>♀️ Female</option>
                                    </select>
                                    @error('gender')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-3">
                                    <label for="marital_status" class="form-label">Marital Status</label>
                                    <select class="form-select @error('marital_status') is-invalid @enderror" id="marital_status" name="marital_status">
                                        <option value="">Select Status</option>
                                        <option value="single" {{ old('marital_status', $user->marital_status) == 'single' ? 'selected' : '' }}>💍 Single</option>
                                        <option value="married" {{ old('marital_status', $user->marital_status) == 'married' ? 'selected' : '' }}>💑 Married</option>
                                        <option value="divorced" {{ old('marital_status', $user->marital_status) == 'divorced' ? 'selected' : '' }}>📋 Divorced</option>
                                        <option value="widowed" {{ old('marital_status', $user->marital_status) == 'widowed' ? 'selected' : '' }}>🕊️ Widowed</option>
                                    </select>
                                    @error('marital_status')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6">
                                    <x-birthdate-dropdown
                                        name="birthdate"
                                        id="birthdate"
                                        label="Birthdate"
                                        :value="old('birthdate', $user->birthdate?->format('Y-m-d'))"
                                        :required="true"
                                        :min-age="10"
                                        :max-age="120"
                                        :error="$errors->first('birthdate')" />
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="blood_type" class="form-label">Blood Type</label>
                                    <select class="form-select @error('blood_type') is-invalid @enderror" id="blood_type" name="blood_type">
                                        <option value="">Select Blood Type</option>
                                        <option value="A+" {{ old('blood_type', $user->blood_type) == 'A+' ? 'selected' : '' }}>🩸 A+</option>
                                        <option value="A-" {{ old('blood_type', $user->blood_type) == 'A-' ? 'selected' : '' }}>🩸 A-</option>
                                        <option value="B+" {{ old('blood_type', $user->blood_type) == 'B+' ? 'selected' : '' }}>🩸 B+</option>
                                        <option value="B-" {{ old('blood_type', $user->blood_type) == 'B-' ? 'selected' : '' }}>🩸 B-</option>
                                        <option value="AB+" {{ old('blood_type', $user->blood_type) == 'AB+' ? 'selected' : '' }}>🩸 AB+</option>
                                        <option value="AB-" {{ old('blood_type', $user->blood_type) == 'AB-' ? 'selected' : '' }}>🩸 AB-</option>
                                        <option value="O+" {{ old('blood_type', $user->blood_type) == 'O+' ? 'selected' : '' }}>🩸 O+</option>
                                        <option value="O-" {{ old('blood_type', $user->blood_type) == 'O-' ? 'selected' : '' }}>🩸 O-</option>
                                        <option value="Unknown" {{ old('blood_type', $user->blood_type) == 'Unknown' ? 'selected' : '' }}>❓ Unknown</option>
                                    </select>
                                    @error('blood_type')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6">
                                    <x-nationality-dropdown
                                        name="nationality"
                                        id="nationality"
                                        :value="old('nationality', $user->nationality)"
                                        :required="true"
                                        :error="$errors->first('nationality')" />
                                </div>
                            </div>

                            @if($showRelationshipFields && $relationship)
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="relationship_type" class="form-label">Relationship</label>
                                        <select class="form-select @error('relationship_type') is-invalid @enderror" id="relationship_type" name="relationship_type">
                                            <option value="">Select Relationship</option>
                                            <option value="son" {{ old('relationship_type', $relationship->relationship_type ?? '') == 'son' ? 'selected' : '' }}>Son</option>
                                            <option value="daughter" {{ old('relationship_type', $relationship->relationship_type ?? '') == 'daughter' ? 'selected' : '' }}>Daughter</option>
                                            <option value="spouse" {{ old('relationship_type', $relationship->relationship_type ?? '') == 'spouse' ? 'selected' : '' }}>Wife</option>
                                            <option value="sponsor" {{ old('relationship_type', $relationship->relationship_type ?? '') == 'sponsor' ? 'selected' : '' }}>Sponsor</option>
                                            <option value="other" {{ old('relationship_type', $relationship->relationship_type ?? '') == 'other' ? 'selected' : '' }}>Other</option>
                                        </select>
                                        @error('relationship_type')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input" id="is_billing_contact" name="is_billing_contact" value="1" {{ old('is_billing_contact', $relationship->is_billing_contact ?? false) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="is_billing_contact">Is Billing Contact</label>
                                </div>
                            @endif
                        </div>

                        <!-- Social Media Tab -->
                        <div class="tab-pane fade" id="social" role="tabpanel" aria-labelledby="social-tab">
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
                                        $formLinks = [];
                                        foreach ($existingLinks as $platform => $url) {
                                            $formLinks[] = ['platform' => $platform, 'url' => $url];
                                        }
                                    @endphp
                                    @foreach($formLinks as $index => $link)
                                        @include('components.social-link-row', ['index' => $index, 'link' => $link])
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        <!-- Additional Info Tab -->
                        <div class="tab-pane fade" id="additional" role="tabpanel" aria-labelledby="additional-tab">
                            <div class="mb-3">
                                <label for="motto" class="form-label">Personal Motto</label>
                                <textarea class="form-control @error('motto') is-invalid @enderror" id="motto" name="motto" rows="4" placeholder="Enter personal motto or quote...">{{ old('motto', $user->motto) }}</textarea>
                                <div class="form-text">Share a personal motto or quote that inspires you.</div>
                                @error('motto')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer bg-light">
                <div class="d-flex justify-content-between align-items-center w-100">
                    <div>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    </div>
                    <div>
                        <button type="submit" class="btn btn-success" id="submitBtn" form="profileEditForm">
                            <i class="bi bi-check-circle me-1"></i>Update Profile
                        </button>
                    </div>
                    <div>
                        <button type="button" class="btn btn-outline-secondary me-2" id="prevBtn" style="display: none;">
                            <i class="bi bi-arrow-left me-1"></i>Previous
                        </button>
                        <button type="button" class="btn btn-primary" id="nextBtn">
                            Next<i class="bi bi-arrow-right ms-1"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
/* Profile Photo Tab Styles */
.profile-photo-preview-container {
    position: relative;
    transition: transform 0.3s ease;
}

.profile-photo-preview-container:hover {
    transform: translateY(-5px);
}

.profile-photo-info {
    animation: fadeInRight 0.5s ease-out;
}

@keyframes fadeInRight {
    from {
        opacity: 0;
        transform: translateX(20px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.privacy-settings-card {
    transition: all 0.3s ease;
}

.privacy-settings-card:hover {
    transform: translateY(-2px);
}

.privacy-settings-card .card {
    transition: box-shadow 0.3s ease;
}

.privacy-settings-card .card:hover {
    box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.15) !important;
}

.form-check-input:checked {
    background-color: #28a745;
    border-color: #28a745;
}

.form-check-input:focus {
    border-color: #86b7fe;
    outline: 0;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}

/* Animated gradient background for motivational section */
.alert-info {
    animation: gradientShift 3s ease infinite;
    background-size: 200% 200%;
}

@keyframes gradientShift {
    0% {
        background-position: 0% 50%;
    }
    50% {
        background-position: 100% 50%;
    }
    100% {
        background-position: 0% 50%;
    }
}

/* Guidelines list - no animation to save space */
.profile-photo-info ul li {
    transition: color 0.2s ease;
}

.profile-photo-info ul li:hover {
    color: #495057;
}

/* Button hover effects */
#removeProfilePicture {
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

#removeProfilePicture::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.3);
    transform: translate(-50%, -50%);
    transition: width 0.6s, height 0.6s;
}

#removeProfilePicture:hover::before {
    width: 300px;
    height: 300px;
}

#removeProfilePicture:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
}

/* Custom select wrapper */
.custom-select-wrapper {
    position: relative;
}

.custom-select-btn {
    width: 100%;
    text-align: left;
    background: white;
    border: 1px solid #dee2e6;
    padding: 0.375rem 2.25rem 0.375rem 0.75rem;
    border-radius: 0.375rem;
    cursor: pointer;
    position: relative;
}

.custom-select-btn::after {
    content: "";
    position: absolute;
    right: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    width: 0;
    height: 0;
    border-left: 0.3em solid transparent;
    border-right: 0.3em solid transparent;
    border-top: 0.3em solid;
}

.custom-select-btn:hover {
    border-color: #86b7fe;
}

.custom-select-dropdown {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
    max-height: 300px;
    overflow-y: auto;
    z-index: 1000;
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
    margin-top: 0.125rem;
}

.custom-select-option {
    padding: 0.5rem 0.75rem;
    cursor: pointer;
    transition: background-color 0.15s ease-in-out;
}

.custom-select-option:hover {
    background-color: #f8f9fa;
}

.custom-select-option i {
    width: 20px;
    display: inline-block;
}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    let socialLinkIndex = {{ count($formLinks ?? []) }};

    // Tab navigation
    const tabs = ['photo', 'personal', 'social', 'additional'];
    let currentTab = 0;

    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');
    const submitBtn = document.getElementById('submitBtn');

    function updateButtons() {
        prevBtn.style.display = currentTab === 0 ? 'none' : 'inline-block';
        nextBtn.style.display = currentTab === tabs.length - 1 ? 'none' : 'inline-block';
    }

    function showTab(index) {
        const tabButton = document.getElementById(tabs[index] + '-tab');
        const tab = new bootstrap.Tab(tabButton);
        tab.show();
        currentTab = index;
        updateButtons();
    }

    nextBtn.addEventListener('click', function() {
        if (currentTab < tabs.length - 1) {
            showTab(currentTab + 1);
        }
    });

    prevBtn.addEventListener('click', function() {
        if (currentTab > 0) {
            showTab(currentTab - 1);
        }
    });

    document.querySelectorAll('#profileEditTabs button[data-bs-toggle="tab"]').forEach((tabButton, index) => {
        tabButton.addEventListener('shown.bs.tab', function() {
            currentTab = index;
            updateButtons();
        });
    });

    updateButtons();

    // Auto-open modal on page load (only if cancelUrl is set, meaning it's a dedicated edit page)
    const editModalEl = document.getElementById('editProfileModal');
    const editModal = new bootstrap.Modal(editModalEl);
    @if($cancelUrl)
        editModal.show();
    @endif

    // Prevent edit modal from closing when cropper modal opens
    let cropperModalOpen = false;
    let isSubmitting = false;

    document.addEventListener('show.bs.modal', function(event) {
        if (event.target.id !== 'editProfileModal') {
            cropperModalOpen = true;
        }
    });

    document.addEventListener('hidden.bs.modal', function(event) {
        if (event.target.id !== 'editProfileModal') {
            cropperModalOpen = false;
            // Re-show edit modal after cropper closes, unless we're submitting
            if (!editModalEl.classList.contains('show') && !isSubmitting) {
                editModal.show();
            }
        }
    });

    editModalEl.addEventListener('hide.bs.modal', function(e) {
        if (cropperModalOpen && !isSubmitting) {
            e.preventDefault();
            e.stopPropagation();
            return false;
        }
    });

    editModalEl.addEventListener('hidden.bs.modal', function() {
        if (!cropperModalOpen && !isSubmitting) {
            @if($cancelUrl)
                window.location.href = "{{ $cancelUrl }}";
            @endif
        }
    });

    @if($errors->any())
        editModal.show();
    @endif

    // Handle form submission with AJAX
    const profileForm = document.getElementById('profileEditForm');
    submitBtn.addEventListener('click', function(e) {
        e.preventDefault();

        // Disable submit button to prevent double submission
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Updating...';

        const formData = new FormData(profileForm);

        fetch(profileForm.action, {
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
                // Update profile picture on the page if it was changed
                if (data.profile_picture_url) {
                    // Update all profile pictures on the page
                    document.querySelectorAll('img[alt*="Profile"], img[src*="profile"]').forEach(img => {
                        if (img.src.includes('profile_') || img.alt.toLowerCase().includes('profile')) {
                            img.src = data.profile_picture_url + '?v=' + new Date().getTime();
                        }
                    });
                }

                // Show success message
                const alertDiv = document.createElement('div');
                alertDiv.className = 'alert alert-success alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3';
                alertDiv.style.zIndex = '9999';
                alertDiv.innerHTML = `
                    <i class="bi bi-check-circle me-2"></i>${data.message || 'Profile updated successfully!'}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;
                document.body.appendChild(alertDiv);

                // Close modal and reload page after short delay
                isSubmitting = true;
                setTimeout(() => {
                    editModal.hide();
                    window.location.reload();
                }, 1500);
            } else {
                throw new Error(data.message || 'Update failed');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            const alertDiv = document.createElement('div');
            alertDiv.className = 'alert alert-danger alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3';
            alertDiv.style.zIndex = '9999';
            alertDiv.innerHTML = `
                <i class="bi bi-exclamation-triangle me-2"></i>${error.message || 'Failed to update profile. Please try again.'}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.body.appendChild(alertDiv);

            // Re-enable submit button
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="bi bi-check-circle me-1"></i>Update Profile';
        });
    });

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

    // Custom select dropdown functionality
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('custom-select-btn') || e.target.closest('.custom-select-btn')) {
            const btn = e.target.classList.contains('custom-select-btn') ? e.target : e.target.closest('.custom-select-btn');
            const dropdown = btn.nextElementSibling.nextElementSibling;
            document.querySelectorAll('.custom-select-dropdown').forEach(d => {
                if (d !== dropdown) d.style.display = 'none';
            });
            dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
            e.stopPropagation();
        }
        else if (e.target.classList.contains('custom-select-option') || e.target.closest('.custom-select-option')) {
            const option = e.target.classList.contains('custom-select-option') ? e.target : e.target.closest('.custom-select-option');
            const value = option.getAttribute('data-value');
            const wrapper = option.closest('.custom-select-wrapper');
            const btn = wrapper.querySelector('.custom-select-btn');
            const hiddenInput = wrapper.querySelector('.platform-value');
            const dropdown = wrapper.querySelector('.custom-select-dropdown');
            btn.innerHTML = option.innerHTML;
            hiddenInput.value = value;
            dropdown.style.display = 'none';
        }
        else {
            document.querySelectorAll('.custom-select-dropdown').forEach(d => {
                d.style.display = 'none';
            });
        }
    });

    // Listen for image upload success from cropper
    document.addEventListener('imageUploaded', function(e) {
        if (e.detail && e.detail.url) {
            updateProfilePicturePreview(e.detail.url);
        }
    });

    // Also listen for global image upload success callback
    window.imageUploadSuccess = function(result) {
        console.log('Image upload success callback called:', result);
        if (result && result.url) {
            console.log('Updating preview with URL:', result.url);
            updateProfilePicturePreview(result.url);
            // Clear the remove checkbox since we now have a new image
            const removeCheckbox = document.getElementById('remove_profile_picture');
            if (removeCheckbox) {
                removeCheckbox.checked = false;
                console.log('Remove checkbox cleared after successful upload');
            }
        } else if (result && result.path) {
            // Some components might return 'path' instead of 'url'
            console.log('Updating preview with path:', result.path);
            const fullUrl = window.location.origin + '/storage/' + result.path;
            console.log('Constructed URL:', fullUrl);
            updateProfilePicturePreview(fullUrl);
            // Clear the remove checkbox since we now have a new image
            const removeCheckbox = document.getElementById('remove_profile_picture');
            if (removeCheckbox) {
                removeCheckbox.checked = false;
                console.log('Remove checkbox cleared after successful upload');
            }
        } else {
            console.log('No URL or path in result:', result);
        }
    };

    function updateProfilePicturePreview(imageUrl) {
        console.log('updateProfilePicturePreview called with URL:', imageUrl);

        const preview = document.getElementById('profile_picture_preview');
        const placeholder = document.getElementById('profile_picture_placeholder');

        console.log('Current preview element:', preview);
        console.log('Current placeholder element:', placeholder);

        if (placeholder) {
            console.log('Replacing placeholder with new image');
            // Replace placeholder with image
            placeholder.style.display = 'none';
            const previewContainer = placeholder.parentElement;
            const newImg = document.createElement('img');
            newImg.src = imageUrl + '?v=' + new Date().getTime();
            newImg.alt = 'Profile Picture';
            newImg.id = 'profile_picture_preview';
            newImg.className = 'image-upload-preview';
            newImg.style.cssText = 'width: 300px; height: 400px; object-fit: cover; border: 3px solid #dee2e6; border-radius: 8px;';
            previewContainer.appendChild(newImg);
            console.log('New image element created and appended');
        } else if (preview) {
            console.log('Updating existing preview image');
            // Update existing image
            preview.src = imageUrl + '?v=' + new Date().getTime();
        } else {
            console.log('No placeholder or preview found!');
        }

        // Show remove button
        const removeBtn = document.getElementById('removeProfilePicture');
        if (removeBtn) {
            removeBtn.style.display = 'block';
            console.log('Remove button shown');
        } else {
            console.log('Remove button not found');
        }
    }

    // Privacy toggle functionality
    const visibilityToggle = document.getElementById('profilePictureVisibility');
    const visibilityStatus = document.getElementById('visibilityStatus');
    const visibilityIcon = document.getElementById('visibilityIcon');
    const visibilityTitle = document.getElementById('visibilityTitle');
    const visibilityDescription = document.getElementById('visibilityDescription');

    function updateVisibilityStatus() {
        const isPublic = visibilityToggle.checked;

        if (isPublic) {
            visibilityStatus.className = 'alert alert-success mb-0 p-2';
            visibilityIcon.className = 'bi bi-globe me-2 mt-1';
            visibilityTitle.textContent = 'Public';
            visibilityDescription.textContent = 'Everyone can see your profile picture';
        } else {
            visibilityStatus.className = 'alert alert-warning mb-0 p-2';
            visibilityIcon.className = 'bi bi-lock-fill me-2 mt-1';
            visibilityTitle.textContent = 'Private';
            visibilityDescription.textContent = 'Only you and your family can see your profile picture';
        }
    }

    if (visibilityToggle) {
        // Initialize on page load
        updateVisibilityStatus();

        // Update on toggle change
        visibilityToggle.addEventListener('change', function() {
            updateVisibilityStatus();

            // Add a subtle animation
            visibilityStatus.style.opacity = '0';
            setTimeout(() => {
                visibilityStatus.style.transition = 'opacity 0.3s ease-in-out';
                visibilityStatus.style.opacity = '1';
            }, 100);
        });
    }

    // Remove profile picture functionality
    function attachRemovePhotoListener() {
        const removeProfilePictureBtn = document.getElementById('removeProfilePicture');
        if (removeProfilePictureBtn && !removeProfilePictureBtn.hasAttribute('data-listener-attached')) {
            removeProfilePictureBtn.setAttribute('data-listener-attached', 'true');
            removeProfilePictureBtn.addEventListener('click', function() {
                // Set the hidden input to indicate removal
                document.getElementById('removeProfilePictureInput').value = '1';

                // Get user gender for default avatar
                const gender = document.getElementById('gender').value || 'm';

                // Update preview to show default avatar
                const preview = document.getElementById('profile_picture_preview');
                const placeholder = document.getElementById('profile_picture_placeholder');

                if (preview) {
                    preview.style.display = 'none';
                }

                if (placeholder) {
                    placeholder.style.display = 'flex';
                    // Update icon based on gender
                    const icon = placeholder.querySelector('i');
                    if (icon) {
                        icon.className = gender === 'f' ? 'bi bi-person-circle' : 'bi bi-person-circle';
                        icon.style.color = gender === 'f' ? '#e91e63' : '#2196f3';
                    }
                    const text = placeholder.querySelector('p');
                    if (text) {
                        text.textContent = 'Default avatar will be used';
                    }
                } else {
                    // Create placeholder if it doesn't exist
                    const previewContainer = preview ? preview.parentElement : document.querySelector('.image-preview');
                    if (previewContainer) {
                        const newPlaceholder = document.createElement('div');
                        newPlaceholder.className = 'image-placeholder';
                        newPlaceholder.id = 'profile_picture_placeholder';
                        newPlaceholder.style.cssText = 'width: 300px; height: 400px; background-color: #f0f0f0; border: 3px solid #dee2e6; border-radius: 8px; display: flex; align-items: center; justify-content: center; margin: 0 auto;';
                        newPlaceholder.innerHTML = `
                            <div class="text-center">
                                <i class="bi bi-person-circle" style="font-size: 60px; color: ${gender === 'f' ? '#e91e63' : '#2196f3'};"></i>
                                <p class="text-muted mt-2 mb-0">Default avatar will be used</p>
                            </div>
                        `;
                        if (preview) {
                            preview.parentElement.appendChild(newPlaceholder);
                        } else {
                            previewContainer.appendChild(newPlaceholder);
                        }
                    }
                }

                // Hide the remove button
                removeProfilePictureBtn.style.display = 'none';

                // No notification needed - just remove the picture directly
            });
        }
    }

    // Attach listener on page load
    attachRemovePhotoListener();

    function addSocialLinkRow(platform = '', url = '') {
        const container = document.getElementById('socialLinksContainer');
        const row = document.createElement('div');
        row.className = 'social-link-row mb-3 d-flex align-items-end';

        const platformIcons = {
            'facebook': '<i class="bi bi-facebook me-2"></i>Facebook',
            'twitter': '<i class="bi bi-twitter-x me-2"></i>Twitter/X',
            'instagram': '<i class="bi bi-instagram me-2"></i>Instagram',
            'linkedin': '<i class="bi bi-linkedin me-2"></i>LinkedIn',
            'youtube': '<i class="bi bi-youtube me-2"></i>YouTube',
            'tiktok': '<i class="bi bi-tiktok me-2"></i>TikTok',
            'snapchat': '<i class="bi bi-snapchat me-2"></i>Snapchat',
            'whatsapp': '<i class="bi bi-whatsapp me-2"></i>WhatsApp',
            'telegram': '<i class="bi bi-telegram me-2"></i>Telegram',
            'discord': '<i class="bi bi-discord me-2"></i>Discord',
            'reddit': '<i class="bi bi-reddit me-2"></i>Reddit',
            'pinterest': '<i class="bi bi-pinterest me-2"></i>Pinterest',
            'twitch': '<i class="bi bi-twitch me-2"></i>Twitch',
            'github': '<i class="bi bi-github me-2"></i>GitHub',
            'spotify': '<i class="bi bi-spotify me-2"></i>Spotify',
            'skype': '<i class="bi bi-skype me-2"></i>Skype',
            'slack': '<i class="bi bi-slack me-2"></i>Slack',
            'medium': '<i class="bi bi-medium me-2"></i>Medium',
            'vimeo': '<i class="bi bi-vimeo me-2"></i>Vimeo',
            'messenger': '<i class="bi bi-messenger me-2"></i>Messenger',
            'wechat': '<i class="bi bi-wechat me-2"></i>WeChat',
            'line': '<i class="bi bi-line me-2"></i>Line'
        };

        const selectedPlatform = platform ? platformIcons[platform] : 'Select Platform';

        row.innerHTML = `
            <div class="me-2 flex-grow-1">
                <label class="form-label">Platform</label>
                <div class="custom-select-wrapper">
                    <button type="button" class="form-select text-start custom-select-btn" data-index="${socialLinkIndex}">
                        ${selectedPlatform}
                    </button>
                    <input type="hidden" name="social_links[${socialLinkIndex}][platform]" value="${platform}" class="platform-value" required>
                    <div class="custom-select-dropdown" style="display: none;">
                        ${Object.entries(platformIcons).map(([key, value]) =>
                            `<div class="custom-select-option" data-value="${key}">${value}</div>`
                        ).join('')}
                    </div>
                </div>
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
