@props([
    'user',
    'formAction',
    'formMethod' => 'PUT',
    'cancelUrl' => null,
    'showRelationshipFields' => false,
    'relationship' => null,
])

<!-- Profile Edit Modal (Alpine.js + Tailwind) -->
<div x-data="{
    open: {{ $cancelUrl ? 'true' : 'false' }},
    activeTab: 'photo',
    tabs: ['photo', 'personal', 'social', 'additional'],
    socialLinkIndex: {{ count(old('social_links', $user->social_links ?? [])) }},
    isPublic: {{ old('profile_picture_is_public', $user->profile_picture_is_public ?? true) ? 'true' : 'false' }},
    isSubmitting: false,
    cropperOpen: false,
    removeProfilePicture: false,
    get currentTabIndex() {
        return this.tabs.indexOf(this.activeTab);
    },
    nextTab() {
        const idx = this.currentTabIndex;
        if (idx < this.tabs.length - 1) {
            this.activeTab = this.tabs[idx + 1];
        }
    },
    prevTab() {
        const idx = this.currentTabIndex;
        if (idx > 0) {
            this.activeTab = this.tabs[idx - 1];
        }
    },
    closeModal() {
        if (this.cropperOpen) return;
        this.open = false;
        @if($cancelUrl)
        setTimeout(() => { window.location.href = '{{ $cancelUrl }}'; }, 300);
        @endif
    },
    addSocialLink() {
        const container = document.getElementById('socialLinksContainer');
        const row = document.createElement('div');
        row.className = 'social-link-row';
        row.innerHTML = this.getSocialLinkTemplate(this.socialLinkIndex);
        container.appendChild(row);
        this.socialLinkIndex++;
    },
    getSocialLinkTemplate(index) {
        return `
            <div class='flex items-end gap-2 mb-3' x-data='{ platform: \"\", dropdownOpen: false }'>
                <div class='flex-1'>
                    <label class='form-label'>Platform</label>
                    <div class='relative'>
                        <button type='button' @click='dropdownOpen = !dropdownOpen' class='form-select text-left flex items-center justify-between'>
                            <span x-text='platform || \"Select Platform\"'></span>
                            <i class='bi bi-chevron-down text-muted-foreground'></i>
                        </button>
                        <input type='hidden' name='social_links[${index}][platform]' x-model='platform' required>
                        <div x-show='dropdownOpen' @click.away='dropdownOpen = false' x-cloak class='dropdown-menu show'>
                            <div @click='platform = \"facebook\"; dropdownOpen = false' class='dropdown-item'><i class='bi bi-facebook mr-2 text-blue-600'></i>Facebook</div>
                            <div @click='platform = \"twitter\"; dropdownOpen = false' class='dropdown-item'><i class='bi bi-twitter-x mr-2'></i>Twitter/X</div>
                            <div @click='platform = \"instagram\"; dropdownOpen = false' class='dropdown-item'><i class='bi bi-instagram mr-2 text-pink-500'></i>Instagram</div>
                            <div @click='platform = \"linkedin\"; dropdownOpen = false' class='dropdown-item'><i class='bi bi-linkedin mr-2 text-blue-700'></i>LinkedIn</div>
                            <div @click='platform = \"youtube\"; dropdownOpen = false' class='dropdown-item'><i class='bi bi-youtube mr-2 text-red-600'></i>YouTube</div>
                            <div @click='platform = \"tiktok\"; dropdownOpen = false' class='dropdown-item'><i class='bi bi-tiktok mr-2'></i>TikTok</div>
                            <div @click='platform = \"snapchat\"; dropdownOpen = false' class='dropdown-item'><i class='bi bi-snapchat mr-2 text-yellow-400'></i>Snapchat</div>
                            <div @click='platform = \"whatsapp\"; dropdownOpen = false' class='dropdown-item'><i class='bi bi-whatsapp mr-2 text-green-500'></i>WhatsApp</div>
                            <div @click='platform = \"telegram\"; dropdownOpen = false' class='dropdown-item'><i class='bi bi-telegram mr-2 text-blue-400'></i>Telegram</div>
                            <div @click='platform = \"github\"; dropdownOpen = false' class='dropdown-item'><i class='bi bi-github mr-2'></i>GitHub</div>
                        </div>
                    </div>
                </div>
                <div class='flex-1'>
                    <label class='form-label'>URL</label>
                    <input type='url' class='form-control' name='social_links[${index}][url]' placeholder='https://example.com/username' required>
                </div>
                <div class='mb-0'>
                    <button type='button' @click='$el.closest(\".social-link-row\").remove()' class='btn btn-outline-danger btn-sm'>
                        <i class='bi bi-trash'></i>
                    </button>
                </div>
            </div>
        `;
    }
}"
x-init="
    @if($errors->any())
    open = true;
    @endif
    window.addEventListener('cropperOpened', () => { cropperOpen = true; });
    window.addEventListener('cropperClosed', () => { cropperOpen = false; });
    window.imageUploadSuccess = function(result) {
        const url = result.url || (result.path ? window.location.origin + '/storage/' + result.path : null);
        if (url) {
            const preview = document.getElementById('profile_picture_preview');
            const placeholder = document.getElementById('profile_picture_placeholder');
            if (placeholder) placeholder.style.display = 'none';
            if (preview) {
                preview.src = url + '?v=' + Date.now();
                preview.style.display = 'block';
            } else {
                const container = document.querySelector('.image-preview');
                const img = document.createElement('img');
                img.src = url + '?v=' + Date.now();
                img.alt = 'Profile Picture';
                img.id = 'profile_picture_preview';
                img.className = 'image-upload-preview';
                img.style.cssText = 'width: 300px; height: 400px; object-fit: cover; border: 3px solid #dee2e6; border-radius: 8px;';
                container.appendChild(img);
            }
            document.getElementById('removeProfilePicture').style.display = 'block';
        }
    };
"
@keydown.escape.window="closeModal()"
x-cloak
id="editProfileModal">

    <!-- Modal Backdrop -->
    <div x-show="open"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="modal-backdrop"
         @click="closeModal()">
    </div>

    <!-- Modal Dialog -->
    <div x-show="open"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 -translate-y-4"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 -translate-y-4"
         class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="modal-content flex flex-col" style="max-width: 75%; width: 1000px; max-height: 90vh;" @click.stop>

            <!-- Modal Header -->
            <div class="modal-header bg-primary text-white rounded-t-xl">
                <h5 class="modal-title flex items-center text-white">
                    <i class="bi bi-person-circle mr-2"></i>Edit Profile
                </h5>
                <button type="button" @click="closeModal()" class="btn-close text-white opacity-80 hover:opacity-100">
                </button>
            </div>

            <!-- Modal Body -->
            <div class="modal-body p-0 flex-1 overflow-hidden flex flex-col">
                <!-- Tab Navigation -->
                <ul class="nav nav-tabs nav-fill border-b border-border">
                    <li class="nav-item flex-1">
                        <button type="button"
                                @click="activeTab = 'photo'"
                                :class="activeTab === 'photo' ? 'active' : ''"
                                class="nav-link w-full">
                            <i class="bi bi-camera mr-1"></i>Profile Photo
                        </button>
                    </li>
                    <li class="nav-item flex-1">
                        <button type="button"
                                @click="activeTab = 'personal'"
                                :class="activeTab === 'personal' ? 'active' : ''"
                                class="nav-link w-full">
                            <i class="bi bi-person-badge mr-1"></i>Personal Info
                        </button>
                    </li>
                    <li class="nav-item flex-1">
                        <button type="button"
                                @click="activeTab = 'social'"
                                :class="activeTab === 'social' ? 'active' : ''"
                                class="nav-link w-full">
                            <i class="bi bi-share mr-1"></i>Social Media
                        </button>
                    </li>
                    <li class="nav-item flex-1">
                        <button type="button"
                                @click="activeTab = 'additional'"
                                :class="activeTab === 'additional' ? 'active' : ''"
                                class="nav-link w-full">
                            <i class="bi bi-info-circle mr-1"></i>Additional Info
                        </button>
                    </li>
                </ul>

                <form method="POST" action="{{ $formAction }}" id="profileEditForm" class="flex-1 overflow-hidden">
                    @csrf
                    @method($formMethod)

                    <!-- Tab Content -->
                    <div class="p-4 overflow-y-auto" style="height: 500px;">

                        <!-- Profile Photo Tab -->
                        <div x-show="activeTab === 'photo'" x-transition.opacity>
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

                            <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                                <!-- Left Side - Profile Picture (5 cols) -->
                                <div class="md:col-span-5">
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
                                                        <p class="text-muted-foreground mt-2 mb-0">No profile picture</p>
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                        <input type="hidden" name="remove_profile_picture" id="removeProfilePictureInput" :value="removeProfilePicture ? '1' : '0'">
                                    </div>
                                </div>

                                <!-- Right Side - Information & Controls (7 cols) -->
                                <div class="md:col-span-7">
                                    <div class="profile-photo-info" style="max-height: 400px; overflow: hidden;">
                                        <!-- Motivational Header -->
                                        <div class="mb-3">
                                            <h6 class="text-primary mb-2">
                                                <i class="bi bi-camera-fill mr-2"></i>Your Athletic Identity
                                            </h6>
                                            <div class="alert-gradient border-0 shadow-sm p-2 rounded-md text-white">
                                                <div class="flex items-start">
                                                    <i class="bi bi-lightbulb-fill text-lg mr-2"></i>
                                                    <div>
                                                        <p class="mb-1 text-sm font-semibold">Make Your Profile Stand Out!</p>
                                                        <p class="mb-0 text-xs opacity-90">Your profile picture is your athletic CV and first impression. Use a clear, professional photo to help coaches and teammates recognize you!</p>
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
                                                            <h6 class="mb-1 font-medium">
                                                                <i class="bi bi-shield-lock-fill text-primary mr-1"></i>Privacy Settings
                                                            </h6>
                                                            <p class="text-muted-foreground mb-0 text-sm">Toggle to control visibility</p>
                                                        </div>
                                                        <div class="form-switch">
                                                            <input type="checkbox"
                                                                   id="profilePictureVisibility"
                                                                   name="profile_picture_is_public"
                                                                   value="1"
                                                                   x-model="isPublic"
                                                                   class="form-check-input"
                                                                   role="switch">
                                                        </div>
                                                    </div>

                                                    <div :class="isPublic ? 'alert-success' : 'alert-warning'" class="alert mb-0 p-2">
                                                        <div class="flex items-start">
                                                            <i :class="isPublic ? 'bi-globe' : 'bi-lock-fill'" class="bi mr-2 mt-0.5 text-xl"></i>
                                                            <div style="flex: 1; min-width: 0;">
                                                                <strong class="block text-sm" x-text="isPublic ? 'Public' : 'Private'"></strong>
                                                                <p class="mb-0 text-sm" x-text="isPublic ? 'Everyone can see your profile picture' : 'Only you and your family can see your profile picture'"></p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Photo Guidelines & Action Buttons -->
                                        <div class="flex gap-3">
                                            <div style="flex: 1; min-width: 0;">
                                                <h6 class="text-muted-foreground mb-2 text-sm">
                                                    <i class="bi bi-check-circle-fill mr-1"></i>Quick Tips
                                                </h6>
                                                <ul class="list-none text-muted-foreground mb-0 text-sm space-y-1">
                                                    <li><i class="bi bi-check text-success mr-1"></i>Recent, high-quality</li>
                                                    <li><i class="bi bi-check text-success mr-1"></i>Face clearly visible</li>
                                                    <li><i class="bi bi-check text-success mr-1"></i>Professional look</li>
                                                    <li><i class="bi bi-check text-success mr-1"></i>Good lighting</li>
                                                </ul>
                                            </div>
                                            <div style="min-width: 120px;">
                                                <h6 class="text-muted-foreground mb-2 text-sm">
                                                    <i class="bi bi-gear-fill mr-1"></i>Actions
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
                                                    buttonClass="btn btn-success btn-sm w-full"
                                                />
                                                <button type="button"
                                                        class="btn btn-outline-danger btn-sm w-full mt-2"
                                                        id="removeProfilePicture"
                                                        @click="removeProfilePicture = true; document.getElementById('profile_picture_preview')?.style.setProperty('display', 'none'); document.getElementById('profile_picture_placeholder')?.style.setProperty('display', 'flex'); $el.style.display = 'none';"
                                                        @if(!$currentProfileImage) style="display: none;" @endif>
                                                    <i class="bi bi-trash mr-1"></i>Remove
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Personal Info Tab -->
                        <div x-show="activeTab === 'personal'" x-transition.opacity>
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
                                    <div class="invalid-feedback block">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-4 gap-3 mb-3">
                                <div>
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
                                <div>
                                    <label for="marital_status" class="form-label">Marital Status</label>
                                    <select class="form-select @error('marital_status') is-invalid @enderror" id="marital_status" name="marital_status">
                                        <option value="">Select Status</option>
                                        <option value="single" {{ old('marital_status', $user->marital_status) == 'single' ? 'selected' : '' }}>Single</option>
                                        <option value="married" {{ old('marital_status', $user->marital_status) == 'married' ? 'selected' : '' }}>Married</option>
                                        <option value="divorced" {{ old('marital_status', $user->marital_status) == 'divorced' ? 'selected' : '' }}>Divorced</option>
                                        <option value="widowed" {{ old('marital_status', $user->marital_status) == 'widowed' ? 'selected' : '' }}>Widowed</option>
                                    </select>
                                    @error('marital_status')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="md:col-span-2">
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

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-3">
                                <div>
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
                                <div>
                                    <x-nationality-dropdown
                                        name="nationality"
                                        id="nationality"
                                        :value="old('nationality', $user->nationality)"
                                        :required="true"
                                        :error="$errors->first('nationality')" />
                                </div>
                            </div>

                            @if($showRelationshipFields && $relationship)
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-3">
                                    <div>
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
                        <div x-show="activeTab === 'social'" x-transition.opacity>
                            <div class="mb-3">
                                <h5 class="form-label flex justify-between items-center">
                                    Social Media Links
                                    <button type="button" class="btn btn-outline-primary btn-sm" @click="addSocialLink()">
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
                        <div x-show="activeTab === 'additional'" x-transition.opacity>
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

            <!-- Modal Footer -->
            <div class="modal-footer bg-muted/30 justify-between rounded-b-xl">
                <div>
                    <button type="button" class="btn btn-secondary" @click="closeModal()">Cancel</button>
                </div>
                <div>
                    <button type="submit" class="btn btn-success" id="submitBtn" form="profileEditForm" :disabled="isSubmitting">
                        <span x-show="!isSubmitting"><i class="bi bi-check-circle mr-1"></i>Update Profile</span>
                        <span x-show="isSubmitting" x-cloak><span class="spinner-border spinner-border-sm mr-1"></span>Updating...</span>
                    </button>
                </div>
                <div class="flex gap-2">
                    <button type="button" class="btn btn-outline-secondary" x-show="currentTabIndex > 0" @click="prevTab()">
                        <i class="bi bi-arrow-left mr-1"></i>Previous
                    </button>
                    <button type="button" class="btn btn-primary" x-show="currentTabIndex < tabs.length - 1" @click="nextTab()">
                        Next<i class="bi bi-arrow-right ml-1"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
/* Profile Photo Tab Styles - preserving original animations */
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

.privacy-settings-card .card:hover {
    box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.15) !important;
}

/* Animated gradient background for motivational section */
.alert-gradient {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    background-size: 200% 200%;
    animation: gradientShift 3s ease infinite;
}

@keyframes gradientShift {
    0% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
    100% { background-position: 0% 50%; }
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

/* Form switch - green when checked */
.form-switch .form-check-input:checked {
    background-color: #28a745;
    border-color: #28a745;
}
</style>
@endpush
