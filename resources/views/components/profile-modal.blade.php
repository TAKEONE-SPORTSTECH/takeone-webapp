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
    $modalTitle = $title ?? ($isCreate ? __('shared.components_profile_modal_add_family_member') : __('shared.components_profile_modal_edit_profile'));
    $modalSubtitle = $subtitle ?? ($isCreate ? __('shared.components_profile_modal_create_subtitle') : null);
    $modalIcon = $icon ?? ($isCreate ? 'bi-person-plus' : 'bi-person-circle');
    $submitText = $submitText ?? ($isCreate ? __('shared.components_profile_modal_add_member') : __('shared.components_profile_modal_update_profile'));
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

    // Cropper upload endpoint (passed through as an HTML attribute on the component tag).
    $uploadUrl = $attributes->get('uploadUrl');
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

    @if($isMobile ?? false)
    {{-- Mobile: full-height bottom sheet (separate chrome, shared fields + Alpine logic) --}}
    @include('components.partials.profile-modal-mobile')
    @else
    <!-- Modal (desktop) -->
    <div class="flex min-h-full items-center justify-center p-4">
        <div x-show="open" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
             class="relative bg-white rounded-lg shadow-xl w-full max-w-4xl" @click.stop>

            <!-- Header -->
            <div class="flex items-center justify-between p-4 bg-primary text-white rounded-t-lg">
                <h5 class="text-lg font-medium flex items-center">
                    <i class="bi {{ $modalIcon }} me-2"></i>{{ $modalTitle }}
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
                        <i class="bi bi-camera me-1"></i>{{ __('shared.components_profile_modal_tab_photo') }}
                    </button>
                    @endif
                    <button type="button" @click="activeTab = 'personal'"
                            :class="activeTab === 'personal' ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                            class="flex-1 py-3 px-4 text-center border-b-2 font-medium text-sm transition-colors">
                        <i class="bi bi-person-badge me-1"></i>{{ __('shared.components_profile_modal_tab_personal') }}
                    </button>
                    <button type="button" @click="activeTab = 'social'"
                            :class="activeTab === 'social' ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                            class="flex-1 py-3 px-4 text-center border-b-2 font-medium text-sm transition-colors">
                        <i class="bi bi-share me-1"></i>{{ __('shared.components_profile_modal_tab_social') }}
                    </button>
                    <button type="button" @click="activeTab = 'additional'"
                            :class="activeTab === 'additional' ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                            class="flex-1 py-3 px-4 text-center border-b-2 font-medium text-sm transition-colors">
                        <i class="bi bi-shield-plus me-1"></i>{{ __('shared.components_profile_modal_tab_medical') }}
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
                    @include('components.partials.profile-modal-fields')
                </div>
            </form>

            <!-- Footer -->
            <div class="flex justify-between items-center p-4 bg-gray-50 border-t rounded-b-lg">
                <div>
                    <button type="button" class="btn btn-secondary" @click="closeModal()">{{ __('shared.cancel') }}</button>
                </div>
                <div>
                    <button type="button" class="btn btn-success" id="{{ $formId }}_submitBtn" @click="submitForm()" :disabled="isSubmitting">
                        <span x-show="!isSubmitting"><i class="bi {{ $submitIcon }} me-1"></i>{{ $submitText }}</span>
                        <span x-show="isSubmitting"><span class="inline-block animate-spin me-2">&#8635;</span>{{ $isCreate ? __('shared.components_profile_modal_creating') : __('shared.components_profile_modal_updating') }}</span>
                    </button>
                </div>
                <div>
                    <button type="button" class="btn btn-outline-secondary me-2" x-show="activeTab !== tabs[0]" @click="prevTab()">
                        <i class="bi bi-arrow-left me-1"></i>{{ __('shared.components_profile_modal_previous') }}
                    </button>
                    <button type="button" class="btn btn-primary" x-show="activeTab !== 'additional'" @click="nextTab()">
                        {{ __('shared.components_profile_modal_next') }}<i class="bi bi-arrow-right ms-1"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif
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
                        window.showToast && window.showToast('error', data.message || '{{ __("shared.components_profile_modal_err_delete_file") }}');
                        s.loading = false;
                        return;
                    }
                } catch (ex) {
                    window.showToast && window.showToast('error', '{{ __("shared.components_profile_modal_err_delete_failed") }}');
                    s.loading = false;
                    return;
                }
            }
            this.removeDoc(s.index);
            this.docDeleteState = { open: false, index: -1, expectedNumber: '', inputValue: '', loading: false };
            window.showToast && window.showToast('success', '{{ __("shared.components_profile_modal_doc_deleted") }}');
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
                        window.showToast && window.showToast('error', data.message || '{{ __("shared.components_profile_modal_upload_failed") }}');
                    }
                } catch (ex) {
                    window.showToast && window.showToast('error', '{{ __("shared.components_profile_modal_upload_failed") }}');
                }
                this.uploading = { ...this.uploading, [i]: false };
            };
            xhr.onerror = () => {
                window.showToast && window.showToast('error', '{{ __("shared.components_profile_modal_upload_failed_retry") }}');
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
                this.showInputError(fid + '_full_name', '{{ __("shared.components_profile_modal_err_full_name_required") }}'); valid = false;
            } else { this.clearInputError(fid + '_full_name'); }

            // Email
            const emailEl = document.getElementById(fid + '_email');
            if (this.showPasswordFields) {
                if (!emailEl || !emailEl.value.trim()) {
                    this.showInputError(fid + '_email', '{{ __("shared.components_profile_modal_err_email_required") }}'); valid = false;
                } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailEl.value.trim())) {
                    this.showInputError(fid + '_email', '{{ __("shared.components_profile_modal_err_email_invalid") }}'); valid = false;
                } else { this.clearInputError(fid + '_email'); }
            }

            // Password + confirmation
            if (this.showPasswordFields) {
                const pw1 = document.getElementById(fid + '_password');
                const pw2 = document.getElementById(fid + '_password_confirmation');
                if (!pw1 || !pw1.value) {
                    this.showInputError(fid + '_password', '{{ __("shared.components_profile_modal_err_password_required") }}'); valid = false;
                } else if (pw1.value.length < 8) {
                    this.showInputError(fid + '_password', '{{ __("shared.components_profile_modal_err_password_min") }}'); valid = false;
                } else { this.clearInputError(fid + '_password'); }

                if (pw1 && pw1.value.length >= 8) {
                    if (!pw2 || !pw2.value) {
                        this.showInputError(fid + '_password_confirmation', '{{ __("shared.components_profile_modal_err_password_confirm") }}'); valid = false;
                    } else if (pw2.value !== pw1.value) {
                        this.showInputError(fid + '_password_confirmation', '{{ __("shared.components_profile_modal_err_passwords_mismatch") }}'); valid = false;
                    } else { this.clearInputError(fid + '_password_confirmation'); }
                }
            }

            // Gender (custom dropdown — hidden input)
            const genderEl = document.getElementById(fid + '_gender');
            if (!genderEl || !genderEl.value) {
                this.showInputError(fid + '_gender', '{{ __("shared.components_profile_modal_err_gender_required") }}'); valid = false;
            } else { this.clearInputError(fid + '_gender'); }

            // Birthdate (custom dropdown — hidden input)
            const bdEl = document.getElementById(fid + '_birthdate');
            if (!bdEl || !bdEl.value) {
                this.showInputError(fid + '_birthdate', '{{ __("shared.components_profile_modal_err_birthdate_required") }}'); valid = false;
            } else { this.clearInputError(fid + '_birthdate'); }

            // Nationality (custom dropdown — hidden input)
            const natEl = document.getElementById(fid + '_nationality');
            if (!natEl || !natEl.value) {
                this.showInputError(fid + '_nationality', '{{ __("shared.components_profile_modal_err_nationality_required") }}'); valid = false;
            } else { this.clearInputError(fid + '_nationality'); }

            // Relationship type (custom dropdown — only when showRelationshipFields)
            if (this.showRelationshipFields) {
                const relEl = document.getElementById(fid + '_relationship_type');
                if (!relEl || !relEl.value) {
                    this.showInputError(fid + '_relationship_type', '{{ __("shared.components_profile_modal_err_relationship_required") }}'); valid = false;
                } else { this.clearInputError(fid + '_relationship_type'); }
            }

            if (!valid) {
                this.activeTab = 'personal';
                if (typeof Toast !== 'undefined') {
                    Toast.error('{{ __("shared.components_profile_modal_required_fields_title") }}', '{{ __("shared.components_profile_modal_required_fields_body") }}');
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
                Toast.error('{{ __("shared.components_profile_modal_validation_failed_title") }}', `${count} error${count > 1 ? 's' : ''} — please review the highlighted fields.`);
            }
        },

        submitForm() {
            if (!this.validateForm()) return;

            // Block if a document file is still uploading
            if (Object.values(this.uploading).some(v => v)) {
                window.showToast && window.showToast('warning', '{{ __("shared.components_profile_modal_wait_upload") }}');
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

                    window.showToast('success', data.message || '{{ $isCreate ? __('shared.components_profile_modal_member_created') : __('shared.components_profile_modal_profile_updated') }}');
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
                    throw new Error(data.message || '{{ $isCreate ? __('shared.components_profile_modal_creation_failed') : __('shared.components_profile_modal_update_failed') }}');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                if (typeof window.showToast === 'function') {
                    window.showToast('error', error.message || '{{ __("shared.components_profile_modal_generic_error") }}');
                } else {
                    this.showAlert('danger', error.message || '{{ __("shared.components_profile_modal_generic_error") }}');
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
                newImg.alt = '{{ __("shared.components_profile_modal_profile_picture_alt") }}';
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
                            text.textContent = '{{ __("shared.components_profile_modal_default_avatar") }}';
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
                                <p class="text-gray-500 mt-2 mb-0">{{ __("shared.components_profile_modal_default_avatar") }}</p>
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
