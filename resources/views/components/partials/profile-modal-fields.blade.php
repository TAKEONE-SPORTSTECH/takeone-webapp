{{-- Shared tab-content for the profile modal (desktop + mobile chrome both include this). --}}
{{-- Depends on vars from components/profile-modal.blade.php: $formId, $isCreate, $showPhotoTab, --}}
{{-- $currentProfileImage, $user, $uploadUrl, $profilePicturePublic, $showEmailField, $userName, etc. --}}

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
                             class="mx-auto image-upload-preview max-w-full"
                             style="width: 300px; height: 400px; object-fit: cover; border: 3px solid #dee2e6; border-radius: 8px;">
                    @else
                        <div id="profile_picture_placeholder"
                             class="mx-auto image-placeholder max-w-full"
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
            <div class="profile-photo-info md:max-h-[400px] md:overflow-hidden">
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
                                uploadUrl="{{ $uploadUrl }}"
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
                    <div class="col-span-6 md:col-span-3">
                        <input type="text" x-model="contact.name" placeholder="Full name" class="form-control form-control-sm">
                    </div>
                    <div class="col-span-6 md:col-span-3">
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
                    <div class="col-span-11 md:col-span-5">
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
                        <div class="col-span-12 md:col-span-5">
                            <input type="text" x-model="cond.condition" placeholder="e.g. Asthma, Diabetes" class="form-control form-control-sm">
                        </div>
                        <div class="col-span-6 md:col-span-3">
                            <input type="date" x-model="cond.noted_at" class="form-control form-control-sm">
                        </div>
                        <div class="col-span-5 md:col-span-3">
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
                        <div class="col-span-6 md:col-span-5">
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
                        <div class="col-span-5 md:col-span-6">
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
