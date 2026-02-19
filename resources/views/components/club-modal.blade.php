@props(['mode' => 'create', 'club' => null])

@php
    $isEdit = $mode === 'edit' && $club;
    $modalId = 'clubModal';
    $modalTitle = $isEdit ? 'Edit Club' : 'Create New Club';
@endphp

<!-- Club Modal (Alpine.js) -->
<div x-data="clubModalController({{ json_encode(['mode' => $mode, 'clubId' => $club->id ?? null, 'isEdit' => $isEdit]) }})"
     x-show="open"
     x-cloak
     @open-club-modal.window="openModal($event.detail)"
     @close-club-modal.window="closeModal()"
     @keydown.escape.window="closeModal()"
     class="fixed inset-0 z-50"
     id="{{ $modalId }}">

    <!-- Backdrop -->
    <div x-show="open"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-black/50"
         @click="closeModal()">
    </div>

    <!-- Modal Dialog -->
    <div x-show="open"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         class="fixed inset-0 flex items-center justify-center p-4">

        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-5xl max-h-[90vh] flex flex-col" @click.stop>
            <!-- Modal Header -->
            <div class="px-6 pt-6 pb-0">
                <div class="flex justify-between items-start mb-3">
                    <div>
                        <h4 class="text-xl font-bold mb-1" x-text="mode === 'edit' ? 'Edit Club' : 'Create New Club'"></h4>
                        <p class="text-muted-foreground text-sm mb-0">Fill in the information across all tabs</p>
                    </div>
                    <button @click="closeModal()" class="text-muted-foreground hover:text-foreground transition-colors">
                        <i class="bi bi-x-lg text-xl"></i>
                    </button>
                </div>

                <!-- Progress Indicator -->
                <div class="flex items-center gap-2 mb-3">
                    <span class="badge bg-primary text-white" x-text="'Step ' + (currentTab + 1) + ' of ' + tabs.length"></span>
                    <div class="flex-1 h-1.5 bg-muted rounded-full overflow-hidden">
                        <div class="h-full bg-primary transition-all duration-300 rounded-full"
                             :style="'width: ' + ((currentTab + 1) / tabs.length * 100) + '%'"></div>
                    </div>
                </div>

                <!-- Tab Navigation -->
                <div class="flex gap-2 border-b-2 border-border overflow-x-auto scrollbar-hide">
                    <template x-for="(tab, index) in tabs" :key="tab.id">
                        <button @click="goToTab(index)"
                                :class="currentTab === index
                                    ? 'text-primary border-primary'
                                    : 'text-muted-foreground border-transparent hover:text-primary hover:border-primary/30'"
                                class="flex items-center gap-2 px-4 py-3 border-b-3 font-medium text-sm whitespace-nowrap transition-all -mb-0.5">
                            <i :class="tab.icon"></i>
                            <span class="hidden md:inline" x-text="tab.name"></span>
                        </button>
                    </template>
                </div>
            </div>

            <!-- Modal Body (Scrollable) -->
            <div class="px-6 py-6 overflow-y-auto flex-1">
                <form id="clubForm" x-ref="form" data-mode="{{ $mode }}" data-club-id="{{ $club->id ?? '' }}">
                    @csrf
                    @if($isEdit)
                        @method('PUT')
                    @endif

                    <!-- Tab 1: Basic Information -->
                    <div x-show="currentTab === 0" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
                        <x-club-modal.tabs.basic-info :club="$club" :mode="$mode" />
                    </div>

                    <!-- Tab 2: Identity & Branding -->
                    <div x-show="currentTab === 1" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
                        <x-club-modal.tabs.identity-branding :club="$club" :mode="$mode" />
                    </div>

                    <!-- Tab 3: Location -->
                    <div x-show="currentTab === 2" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
                        <x-club-modal.tabs.location :club="$club" :mode="$mode" />
                    </div>

                    <!-- Tab 4: Contact -->
                    <div x-show="currentTab === 3" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
                        <x-club-modal.tabs.contact :club="$club" :mode="$mode" />
                    </div>

                    <!-- Tab 5: Finance & Settings -->
                    <div x-show="currentTab === 4" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
                        <x-club-modal.tabs.finance-settings :club="$club" :mode="$mode" />
                    </div>
                </form>
            </div>

            <!-- Modal Footer -->
            <div class="px-6 pb-6 pt-4 flex items-center justify-end gap-3 border-t border-border">
                <button x-show="currentTab > 0"
                        @click="goToTab(currentTab - 1)"
                        class="btn btn-secondary">
                    <i class="bi bi-arrow-left mr-2"></i>Back
                </button>
                <button @click="closeModal()" class="btn btn-secondary">Cancel</button>
                <button x-show="currentTab < tabs.length - 1"
                        @click="goToTab(currentTab + 1)"
                        class="btn btn-primary">
                    Next<i class="bi bi-arrow-right ml-2"></i>
                </button>
                <button x-show="currentTab === tabs.length - 1"
                        @click="handleSubmit()"
                        :disabled="isSubmitting"
                        class="btn text-white"
                        style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <template x-if="isSubmitting">
                        <span class="flex items-center">
                            <span class="spinner-border mr-2"></span>Saving...
                        </span>
                    </template>
                    <template x-if="!isSubmitting">
                        <span>
                            <i class="bi bi-check-circle mr-2"></i><span x-text="mode === 'edit' ? 'Update Club' : 'Create Club'"></span>
                        </span>
                    </template>
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Styles moved to app.css (Phase 6) --}}

@once
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    // Internal User Picker Functions
    let allUsersData = [];

    async function showUserPicker() {
        const overlay = document.getElementById('userPickerOverlay');
        if (overlay) {
            overlay.style.display = 'flex';
            await loadUsersInternal();
            document.getElementById('userSearchInputInternal')?.focus();
        }
    }

    function hideUserPicker() {
        const overlay = document.getElementById('userPickerOverlay');
        if (overlay) {
            overlay.style.display = 'none';
        }
    }

    async function loadUsersInternal() {
        const loadingDiv = document.getElementById('userPickerLoadingInternal');
        const resultsDiv = document.getElementById('userPickerResultsInternal');
        const noResultsDiv = document.getElementById('userPickerNoResultsInternal');

        if (loadingDiv) loadingDiv.style.display = 'block';
        if (resultsDiv) resultsDiv.innerHTML = '';
        if (noResultsDiv) noResultsDiv.style.display = 'none';

        try {
            const response = await fetch('/admin/api/users');
            if (response.ok) {
                allUsersData = await response.json();
                displayUsersInternal(allUsersData);
            }
        } catch (error) {
            console.error('Error loading users:', error);
        } finally {
            if (loadingDiv) loadingDiv.style.display = 'none';
        }
    }

    function displayUsersInternal(users) {
        const resultsDiv = document.getElementById('userPickerResultsInternal');
        const noResultsDiv = document.getElementById('userPickerNoResultsInternal');

        if (!resultsDiv) return;

        if (users.length === 0) {
            resultsDiv.innerHTML = '';
            if (noResultsDiv) noResultsDiv.style.display = 'block';
            return;
        }

        if (noResultsDiv) noResultsDiv.style.display = 'none';

        resultsDiv.innerHTML = users.map(user => `
            <div class="user-picker-item" onclick="selectUserInternal(${user.id}, '${user.full_name}', '${user.email}', '${user.mobile_formatted || ''}', '${user.profile_picture || ''}')">
                <div class="flex items-center gap-3">
                    ${user.profile_picture
                        ? `<img src="/storage/${user.profile_picture}" alt="${user.full_name}" class="rounded-full w-12 h-12 object-cover">`
                        : `<div class="rounded-full bg-primary text-white flex items-center justify-center w-12 h-12 text-xl font-semibold">${user.full_name.charAt(0)}</div>`
                    }
                    <div class="flex-1">
                        <div class="font-semibold">${user.full_name}</div>
                        <div class="text-sm text-muted-foreground">
                            <i class="bi bi-envelope mr-1"></i>${user.email}
                            ${user.mobile_formatted ? `<span class="ml-2"><i class="bi bi-phone mr-1"></i>${user.mobile_formatted}</span>` : ''}
                        </div>
                    </div>
                </div>
            </div>
        `).join('');
    }

    function selectUserInternal(id, name, email, mobile, picture) {
        const ownerInput = document.getElementById('owner_user_id');
        if (ownerInput) {
            ownerInput.value = id;
            ownerInput.dispatchEvent(new Event('change'));
        }

        const ownerDisplay = document.getElementById('ownerDisplay');
        if (ownerDisplay) {
            ownerDisplay.innerHTML = `
                <div class="flex items-center gap-3">
                    ${picture
                        ? `<img src="/storage/${picture}" alt="${name}" class="rounded-full w-12 h-12 object-cover">`
                        : `<div class="rounded-full bg-primary text-white flex items-center justify-center w-12 h-12 text-xl font-semibold">${name.charAt(0)}</div>`
                    }
                    <div class="flex-1">
                        <div class="font-semibold">${name}</div>
                        <div class="text-sm text-muted-foreground">
                            <i class="bi bi-envelope mr-1"></i>${email}
                            ${mobile ? `<span class="ml-2"><i class="bi bi-phone mr-1"></i>${mobile}</span>` : ''}
                        </div>
                    </div>
                </div>
            `;
        }

        hideUserPicker();
    }

    // Search functionality for internal user picker
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('userSearchInputInternal');
        if (searchInput) {
            let searchTimeout;
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    const searchTerm = this.value.toLowerCase();
                    const filtered = allUsersData.filter(user =>
                        user.full_name.toLowerCase().includes(searchTerm) ||
                        user.email.toLowerCase().includes(searchTerm) ||
                        (user.mobile_formatted && user.mobile_formatted.includes(searchTerm))
                    );
                    displayUsersInternal(filtered);
                }, 300);
            });
        }
    });

    // Club Modal Alpine.js Controller
    function clubModalController(config) {
        return {
            open: false,
            currentTab: 0,
            isSubmitting: false,
            draftLoaded: false,
            toastShown: {},
            mode: config.mode,
            clubId: config.clubId,
            isEdit: config.isEdit,

            tabs: [
                { id: 'basic', name: 'Basic Info', icon: 'bi bi-info-circle' },
                { id: 'identity', name: 'Identity & Branding', icon: 'bi bi-palette' },
                { id: 'location', name: 'Location', icon: 'bi bi-geo-alt' },
                { id: 'contact', name: 'Contact', icon: 'bi bi-telephone' },
                { id: 'finance', name: 'Finance & Settings', icon: 'bi bi-bank' }
            ],

            async openModal(detail = {}) {
                // Set mode and clubId from event detail
                if (detail.mode) {
                    this.mode = detail.mode;
                    this.isEdit = detail.mode === 'edit';
                }
                if (detail.clubId) {
                    this.clubId = detail.clubId;
                }

                // Update form data attributes
                if (this.$refs.form) {
                    this.$refs.form.dataset.mode = this.mode;
                    this.$refs.form.dataset.clubId = this.clubId || '';
                }

                this.open = true;
                document.body.classList.add('overflow-hidden');

                // Load club data if in edit mode
                if (this.mode === 'edit' && this.clubId) {
                    await this.loadClubData(this.clubId);
                } else if (this.mode === 'create') {
                    // Reset form for create mode
                    if (this.$refs.form) {
                        this.$refs.form.reset();
                    }
                    this.resetPreviews();
                    if (!this.draftLoaded) {
                        this.loadDraft();
                        this.draftLoaded = true;
                    }
                }
            },

            async loadClubData(clubId) {
                try {
                    const response = await fetch(`/admin/api/clubs/${clubId}`);
                    if (response.ok) {
                        const club = await response.json();
                        this.populateForm(club);
                    } else {
                        this.showToast('Failed to load club data', 'error');
                    }
                } catch (error) {
                    console.error('Error loading club data:', error);
                    this.showToast('Error loading club data', 'error');
                }
            },

            populateForm(club) {
                console.log('Populating form with club data:', club);

                // Basic fields
                this.setFieldValue('club_name', club.club_name);
                this.setFieldValue('slug', club.slug);
                this.setFieldValue('slogan', club.slogan);
                this.setFieldValue('description', club.description);
                this.setFieldValue('established_date', club.established_date);
                this.setFieldValue('commercial_reg_number', club.commercial_reg_number);
                this.setFieldValue('vat_reg_number', club.vat_reg_number);
                this.setFieldValue('vat_percentage', club.vat_percentage);

                // Owner
                this.setFieldValue('owner_user_id', club.owner_user_id);
                if (club.owner) {
                    this.updateOwnerDisplay(club.owner);
                }

                // Location fields
                this.setFieldValue('country', club.country);
                this.setFieldValue('timezone', club.timezone);
                this.setFieldValue('currency', club.currency);
                this.setFieldValue('address', club.address);
                this.setFieldValue('gps_lat', club.gps_lat);
                this.setFieldValue('gps_long', club.gps_long);

                // Contact fields
                if (club.email) {
                    const customEmailRadio = document.getElementById('email_option_custom');
                    if (customEmailRadio) customEmailRadio.checked = true;
                    this.setFieldValue('email', club.email);
                    const customEmailInput = document.getElementById('customEmailInput');
                    const ownerEmailDisplay = document.getElementById('ownerEmailDisplay');
                    if (customEmailInput) customEmailInput.style.display = 'block';
                    if (ownerEmailDisplay) ownerEmailDisplay.style.display = 'none';
                }

                // Finance fields
                this.setFieldValue('enrollment_fee', club.enrollment_fee);
                this.setFieldValue('club_status', club.status);
                const publicProfileCheckbox = document.getElementById('public_profile_enabled');
                if (publicProfileCheckbox) {
                    publicProfileCheckbox.checked = club.public_profile_enabled;
                }

                // Update URL preview
                if (club.slug) {
                    const urlPreview = document.getElementById('clubUrlPreview');
                    if (urlPreview) {
                        urlPreview.textContent = `{{ url('/club/') }}/${club.slug}`;
                    }
                }

                // Update logo preview
                if (club.logo) {
                    const logoContainer = document.getElementById('logoPreviewContainer');
                    if (logoContainer) {
                        const logoUrl = club.logo.startsWith('http') ? club.logo : `/storage/${club.logo}`;
                        logoContainer.innerHTML = `<img src="${logoUrl}" id="logoPreview" class="cropper-preview-image" style="width: 150px; height: 150px; border-radius: 8px; border: 2px solid #dee2e6;">`;
                    }
                }

                // Update cover preview
                if (club.cover_image) {
                    const coverContainer = document.getElementById('coverPreviewContainer');
                    if (coverContainer) {
                        const coverUrl = club.cover_image.startsWith('http') ? club.cover_image : `/storage/${club.cover_image}`;
                        coverContainer.innerHTML = `<img src="${coverUrl}" id="coverPreview" class="cropper-preview-image" style="width: 250px; height: 83px; border-radius: 8px; border: 2px solid #dee2e6;">`;
                    }
                }
            },

            setFieldValue(fieldId, value) {
                const field = document.getElementById(fieldId);
                if (field && value !== null && value !== undefined) {
                    field.value = value;
                }
            },

            updateOwnerDisplay(owner) {
                const ownerDisplay = document.getElementById('ownerDisplay');
                if (ownerDisplay && owner) {
                    // Check if profile_picture is a full URL or a relative path
                    const pictureUrl = owner.profile_picture
                        ? (owner.profile_picture.startsWith('http') ? owner.profile_picture : `/storage/${owner.profile_picture}`)
                        : null;
                    const picture = pictureUrl
                        ? `<img src="${pictureUrl}" alt="${owner.full_name}" class="rounded-full w-12 h-12 object-cover">`
                        : `<div class="rounded-full bg-primary text-white flex items-center justify-center w-12 h-12 text-xl font-semibold">${owner.full_name.charAt(0)}</div>`;

                    ownerDisplay.innerHTML = `
                        <div class="flex items-center gap-3">
                            ${picture}
                            <div class="flex-1">
                                <div class="font-semibold">${owner.full_name}</div>
                                <div class="text-sm text-muted-foreground">
                                    <i class="bi bi-envelope mr-1"></i>${owner.email}
                                    ${owner.mobile ? `<span class="ml-2"><i class="bi bi-phone mr-1"></i>${owner.mobile}</span>` : ''}
                                </div>
                            </div>
                        </div>
                    `;
                }
            },

            resetPreviews() {
                // Reset logo preview
                const logoContainer = document.getElementById('logoPreviewContainer');
                if (logoContainer) {
                    logoContainer.innerHTML = `<div id="logoPreview" class="cropper-preview-placeholder" style="width: 150px; height: 150px; border-radius: 8px; border: 2px dashed #dee2e6; display: flex; align-items: center; justify-content: center; background-color: #f0f0f0; color: #6c757d;"><i class="bi bi-image text-2xl"></i></div>`;
                }

                // Reset cover preview
                const coverContainer = document.getElementById('coverPreviewContainer');
                if (coverContainer) {
                    coverContainer.innerHTML = `<div id="coverPreview" class="cropper-preview-placeholder" style="width: 250px; height: 83px; border-radius: 8px; border: 2px dashed #dee2e6; display: flex; align-items: center; justify-content: center; background-color: #f0f0f0; color: #6c757d;"><i class="bi bi-image text-2xl"></i></div>`;
                }

                // Reset owner display
                const ownerDisplay = document.getElementById('ownerDisplay');
                if (ownerDisplay) {
                    ownerDisplay.innerHTML = `<div class="text-center text-muted-foreground py-3" id="noOwnerSelected"><i class="bi bi-person-plus text-3xl mb-2 block"></i><p class="mb-0">No owner selected</p></div>`;
                }
            },

            closeModal() {
                this.open = false;
                document.body.classList.remove('overflow-hidden');
                this.currentTab = 0;
                this.draftLoaded = false;
                this.toastShown = {};
                // Reset mode to create for next open
                this.mode = 'create';
                this.isEdit = false;
                this.clubId = null;
                if (this.$refs.form) {
                    this.$refs.form.reset();
                    this.$refs.form.dataset.mode = 'create';
                    this.$refs.form.dataset.clubId = '';
                }
                this.resetPreviews();
            },

            goToTab(index) {
                if (index < 0 || index >= this.tabs.length) return;

                // Validate current tab before moving forward
                if (index > this.currentTab && !this.validateCurrentTab()) {
                    return;
                }

                this.currentTab = index;
                this.saveDraft();
            },

            validateCurrentTab() {
                const form = this.$refs.form;
                if (!form) return true;

                // Get all required inputs in the current tab (visible ones)
                const tabPanes = form.querySelectorAll('[x-show]');
                let currentPane = null;

                // Find visible pane by checking currentTab
                const allInputs = form.querySelectorAll('input[required], select[required], textarea[required]');
                let isValid = true;
                let errorCount = 0;

                allInputs.forEach(input => {
                    if (input.type === 'file') return;

                    // Check if input is in currently visible tab
                    let parent = input.closest('[x-show]');
                    if (!parent) return;

                    const showAttr = parent.getAttribute('x-show');
                    if (!showAttr || !showAttr.includes(`currentTab === ${this.currentTab}`)) return;

                    if (!input.value || (input.type === 'email' && !this.isValidEmail(input.value))) {
                        input.classList.add('is-invalid');
                        isValid = false;
                        errorCount++;

                        let errorDiv = input.nextElementSibling;
                        if (!errorDiv || !errorDiv.classList.contains('invalid-feedback')) {
                            errorDiv = document.createElement('div');
                            errorDiv.className = 'invalid-feedback';
                            input.parentNode.insertBefore(errorDiv, input.nextSibling);
                        }
                        errorDiv.textContent = input.dataset.errorMessage || 'This field is required.';
                        errorDiv.style.display = 'block';
                    } else {
                        input.classList.remove('is-invalid');
                    }
                });

                if (!isValid && !this.toastShown[this.currentTab]) {
                    this.showToast(`Please fill in all required fields (${errorCount} field${errorCount > 1 ? 's' : ''} missing)`, 'error');
                    this.toastShown[this.currentTab] = true;
                }

                return isValid;
            },

            isValidEmail(email) {
                return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
            },

            async handleSubmit() {
                // Validate all tabs
                for (let i = 0; i < this.tabs.length; i++) {
                    this.currentTab = i;
                    if (!this.validateCurrentTab()) {
                        return;
                    }
                }

                const form = this.$refs.form;
                const formData = new FormData(form);

                // Add _method for Laravel to handle PUT request
                if (this.mode === 'edit') {
                    formData.append('_method', 'PUT');
                }

                this.isSubmitting = true;

                try {
                    const url = this.mode === 'edit'
                        ? `/admin/clubs/${this.clubId}`
                        : '/admin/clubs';

                    const response = await fetch(url, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        }
                    });

                    const data = await response.json();

                    if (response.ok) {
                        this.showToast(data.message || 'Club saved successfully!', 'success');
                        this.clearDraft();

                        setTimeout(() => {
                            this.closeModal();
                            window.location.reload();
                        }, 1500);
                    } else {
                        this.showToast(data.message || 'An error occurred', 'error');
                        this.isSubmitting = false;
                    }
                } catch (error) {
                    console.error('Error:', error);
                    this.showToast('An error occurred while saving', 'error');
                    this.isSubmitting = false;
                }
            },

            saveDraft() {
                if (this.mode === 'create') {
                    const form = this.$refs.form;
                    if (!form) return;

                    const formData = new FormData(form);
                    const draft = {};
                    for (let [key, value] of formData.entries()) {
                        const input = form.querySelector(`[name="${key}"]`);
                        if (input && input.type !== 'file') {
                            draft[key] = value;
                        }
                    }
                    localStorage.setItem('clubModalDraft', JSON.stringify(draft));
                }
            },

            loadDraft() {
                if (this.mode === 'create') {
                    const draft = localStorage.getItem('clubModalDraft');
                    if (draft) {
                        try {
                            const data = JSON.parse(draft);
                            const form = this.$refs.form;
                            if (!form) return;

                            Object.keys(data).forEach(key => {
                                const input = form.querySelector(`[name="${key}"]`);
                                if (input && input.type !== 'file' && !input.value) {
                                    input.value = data[key];
                                }
                            });
                        } catch (e) {
                            console.error('Error loading draft:', e);
                        }
                    }
                }
            },

            clearDraft() {
                localStorage.removeItem('clubModalDraft');
            },

            showToast(message, type = 'info') {
                if (typeof window.showToast === 'function') {
                    window.showToast(type, message);
                } else {
                    alert(message);
                }
            }
        }
    }
</script>
@endpush
@endonce
