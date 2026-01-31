@props(['mode' => 'create', 'club' => null])

@php
    $isEdit = $mode === 'edit' && $club;
    $modalId = 'clubModal';
    $modalTitle = $isEdit ? 'Edit Club' : 'Create New Club';
@endphp

<!-- Club Modal -->
<div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-labelledby="{{ $modalId }}Label" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content" style="border-radius: 1rem; border: none; max-height: 90vh; display: flex; flex-direction: column;">
            <!-- Modal Header -->
            <div class="modal-header border-0 pb-0" style="padding: 1.5rem 1.5rem 0;">
                <div class="w-100">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <h4 class="modal-title fw-bold mb-1" id="{{ $modalId }}Label">{{ $modalTitle }}</h4>
                            <p class="text-muted small mb-0">Fill in the information across all tabs</p>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <!-- Progress Indicator -->
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <span class="badge bg-primary" id="stepIndicator">Step 1 of 5</span>
                        <div class="progress flex-grow-1" style="height: 6px;">
                            <div class="progress-bar" id="progressBar" role="progressbar" style="width: 20%;" aria-valuenow="20" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </div>

                    <!-- Tab Navigation -->
                    <ul class="nav nav-tabs border-0" id="clubModalTabs" role="tablist" style="gap: 0.5rem; flex-wrap: nowrap; overflow-x: auto;">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="basic-tab" data-bs-toggle="tab" data-bs-target="#basic" type="button" role="tab" aria-controls="basic" aria-selected="true" data-step="1">
                                <i class="bi bi-info-circle me-2"></i>
                                <span class="d-none d-md-inline">Basic Info</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="identity-tab" data-bs-toggle="tab" data-bs-target="#identity" type="button" role="tab" aria-controls="identity" aria-selected="false" data-step="2">
                                <i class="bi bi-palette me-2"></i>
                                <span class="d-none d-md-inline">Identity & Branding</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="location-tab" data-bs-toggle="tab" data-bs-target="#location" type="button" role="tab" aria-controls="location" aria-selected="false" data-step="3">
                                <i class="bi bi-geo-alt me-2"></i>
                                <span class="d-none d-md-inline">Location</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="contact-tab" data-bs-toggle="tab" data-bs-target="#contact" type="button" role="tab" aria-controls="contact" aria-selected="false" data-step="4">
                                <i class="bi bi-telephone me-2"></i>
                                <span class="d-none d-md-inline">Contact</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="finance-tab" data-bs-toggle="tab" data-bs-target="#finance" type="button" role="tab" aria-controls="finance" aria-selected="false" data-step="5">
                                <i class="bi bi-bank me-2"></i>
                                <span class="d-none d-md-inline">Finance & Settings</span>
                            </button>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Modal Body (Scrollable) -->
            <div class="modal-body" style="padding: 1.5rem; overflow-y: auto; flex: 1;">
                <form id="clubForm" data-mode="{{ $mode }}" data-club-id="{{ $club->id ?? '' }}">
                    @csrf
                    @if($isEdit)
                        @method('PUT')
                    @endif

                    <div class="tab-content" id="clubModalTabContent">
                        <!-- Tab 1: Basic Information -->
                        <div class="tab-pane fade show active" id="basic" role="tabpanel" aria-labelledby="basic-tab">
                            <x-club-modal.tabs.basic-info :club="$club" :mode="$mode" />
                        </div>

                        <!-- Tab 2: Identity & Branding -->
                        <div class="tab-pane fade" id="identity" role="tabpanel" aria-labelledby="identity-tab">
                            <x-club-modal.tabs.identity-branding :club="$club" :mode="$mode" />
                        </div>

                        <!-- Tab 3: Location -->
                        <div class="tab-pane fade" id="location" role="tabpanel" aria-labelledby="location-tab">
                            <x-club-modal.tabs.location :club="$club" :mode="$mode" />
                        </div>

                        <!-- Tab 4: Contact -->
                        <div class="tab-pane fade" id="contact" role="tabpanel" aria-labelledby="contact-tab">
                            <x-club-modal.tabs.contact :club="$club" :mode="$mode" />
                        </div>

                        <!-- Tab 5: Finance & Settings -->
                        <div class="tab-pane fade" id="finance" role="tabpanel" aria-labelledby="finance-tab">
                            <x-club-modal.tabs.finance-settings :club="$club" :mode="$mode" />
                        </div>
                    </div>
                </form>
            </div>

            <!-- Modal Footer -->
            <div class="modal-footer border-0" style="padding: 1rem 1.5rem 1.5rem; gap: 0.75rem;">
                <button type="button" class="btn btn-secondary" id="prevBtn" style="display: none;">
                    <i class="bi bi-arrow-left me-2"></i>Back
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="nextBtn">
                    Next<i class="bi bi-arrow-right ms-2"></i>
                </button>
                <button type="button" class="btn text-white" id="submitBtn" style="display: none; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <i class="bi bi-check-circle me-2"></i>{{ $isEdit ? 'Update Club' : 'Create Club' }}
                </button>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
    /* Club Modal Custom Styles */
    #clubModal .nav-tabs {
        border-bottom: 2px solid hsl(var(--border));
    }

    #clubModal .nav-tabs .nav-link {
        border: none;
        border-bottom: 3px solid transparent;
        color: hsl(var(--muted-foreground));
        font-weight: 500;
        padding: 0.75rem 1rem;
        transition: all 0.2s;
        white-space: nowrap;
    }

    #clubModal .nav-tabs .nav-link:hover {
        color: hsl(var(--primary));
        border-bottom-color: hsl(var(--primary) / 0.3);
    }

    #clubModal .nav-tabs .nav-link.active {
        color: hsl(var(--primary));
        border-bottom-color: hsl(var(--primary));
        background-color: transparent;
    }

    #clubModal .nav-tabs .nav-link i {
        font-size: 1.1rem;
    }

    #clubModal .modal-body {
        scrollbar-width: thin;
        scrollbar-color: hsl(var(--border)) transparent;
    }

    #clubModal .modal-body::-webkit-scrollbar {
        width: 8px;
    }

    #clubModal .modal-body::-webkit-scrollbar-track {
        background: transparent;
    }

    #clubModal .modal-body::-webkit-scrollbar-thumb {
        background-color: hsl(var(--border));
        border-radius: 4px;
    }

    #clubModal .form-label {
        font-weight: 600;
        color: hsl(var(--foreground));
        margin-bottom: 0.5rem;
    }

    #clubModal .form-control:focus,
    #clubModal .form-select:focus {
        border-color: hsl(var(--primary));
        box-shadow: 0 0 0 0.2rem hsl(var(--primary) / 0.15);
    }

    #clubModal .tab-pane {
        animation: fadeIn 0.3s ease-in-out;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* ISSUE 1 FIX: Internal User Picker Overlay Styles */
    .user-picker-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: rgba(0, 0, 0, 0.5);
        z-index: 1060;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1rem;
    }

    .user-picker-panel {
        background: white;
        border-radius: 1rem;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        max-width: 600px;
        width: 100%;
        max-height: 80vh;
        display: flex;
        flex-direction: column;
    }

    .user-picker-header {
        padding: 1.5rem;
        border-bottom: 1px solid hsl(var(--border));
        display: flex;
        justify-content: space-between;
        align-items: start;
    }

    .user-picker-body {
        padding: 1.5rem;
        overflow-y: auto;
        flex: 1;
    }

    .user-picker-item {
        padding: 1rem;
        border: 1px solid hsl(var(--border));
        border-radius: 0.5rem;
        margin-bottom: 0.75rem;
        cursor: pointer;
        transition: all 0.2s;
    }

    .user-picker-item:hover {
        border-color: hsl(var(--primary));
        background-color: hsl(var(--muted) / 0.3);
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        #clubModal .modal-xl {
            margin: 0.5rem;
        }

        #clubModal .nav-tabs .nav-link span {
            display: none !important;
        }

        #clubModal .nav-tabs .nav-link {
            padding: 0.75rem 0.5rem;
        }

        .user-picker-panel {
            max-height: 90vh;
        }
    }

    /* ISSUE 4 FIX: Map container styles */
    #clubMap {
        height: 400px;
        width: 100%;
        border-radius: 0.5rem;
        z-index: 1;
    }

    /* Hide Leaflet attribution */
    .leaflet-control-attribution {
        display: none !important;
    }
</style>
@endpush

@once
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    // ISSUE 1 FIX: Internal User Picker Functions
    let allUsersData = [];

    async function showUserPicker() {
        const overlay = document.getElementById('userPickerOverlay');
        if (overlay) {
            overlay.style.display = 'flex';
            // Load users
            await loadUsersInternal();
            // Focus on search input
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
                <div class="d-flex align-items-center gap-3">
                    ${user.profile_picture
                        ? `<img src="/storage/${user.profile_picture}" alt="${user.full_name}" class="rounded-circle" style="width: 50px; height: 50px; object-fit: cover;">`
                        : `<div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width: 50px; height: 50px; font-size: 1.25rem; font-weight: 600;">${user.full_name.charAt(0)}</div>`
                    }
                    <div class="flex-grow-1">
                        <div class="fw-semibold">${user.full_name}</div>
                        <div class="small text-muted">
                            <i class="bi bi-envelope me-1"></i>${user.email}
                            ${user.mobile_formatted ? `<span class="ms-2"><i class="bi bi-phone me-1"></i>${user.mobile_formatted}</span>` : ''}
                        </div>
                    </div>
                </div>
            </div>
        `).join('');
    }

    function selectUserInternal(id, name, email, mobile, picture) {
        // Set hidden input
        const ownerInput = document.getElementById('owner_user_id');
        if (ownerInput) {
            ownerInput.value = id;
            ownerInput.dispatchEvent(new Event('change'));
        }

        // Update display
        const ownerDisplay = document.getElementById('ownerDisplay');
        if (ownerDisplay) {
            ownerDisplay.innerHTML = `
                <div class="d-flex align-items-center gap-3">
                    ${picture
                        ? `<img src="/storage/${picture}" alt="${name}" class="rounded-circle" style="width: 50px; height: 50px; object-fit: cover;">`
                        : `<div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width: 50px; height: 50px; font-size: 1.25rem; font-weight: 600;">${name.charAt(0)}</div>`
                    }
                    <div class="flex-grow-1">
                        <div class="fw-semibold">${name}</div>
                        <div class="small text-muted">
                            <i class="bi bi-envelope me-1"></i>${email}
                            ${mobile ? `<span class="ms-2"><i class="bi bi-phone me-1"></i>${mobile}</span>` : ''}
                        </div>
                    </div>
                </div>
            `;
        }

        // Hide picker
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

    // Club Modal Controller
    (function() {
        const modal = document.getElementById('clubModal');
        if (!modal) return;

        const form = document.getElementById('clubForm');
        const tabs = ['basic', 'identity', 'location', 'contact', 'finance'];
        let currentTab = 0;
        let draftLoaded = false; // ISSUE 2 & 5 FIX: Track if draft was loaded
        let toastShown = {}; // ISSUE 5 FIX: Track shown toasts per tab

        const prevBtn = document.getElementById('prevBtn');
        const nextBtn = document.getElementById('nextBtn');
        const submitBtn = document.getElementById('submitBtn');
        const stepIndicator = document.getElementById('stepIndicator');
        const progressBar = document.getElementById('progressBar');

        // Initialize
        function init() {
            updateButtons();
            attachEventListeners();
            // ISSUE 2 & 5 FIX: Load draft only once on modal open
            if (!draftLoaded && form.dataset.mode === 'create') {
                loadDraft();
                draftLoaded = true;
            }
        }

        // Update button visibility and progress
        function updateButtons() {
            prevBtn.style.display = currentTab === 0 ? 'none' : 'inline-block';
            nextBtn.style.display = currentTab === tabs.length - 1 ? 'none' : 'inline-block';
            submitBtn.style.display = currentTab === tabs.length - 1 ? 'inline-block' : 'none';

            stepIndicator.textContent = `Step ${currentTab + 1} of ${tabs.length}`;
            const progress = ((currentTab + 1) / tabs.length) * 100;
            progressBar.style.width = progress + '%';
            progressBar.setAttribute('aria-valuenow', progress);
        }

        // Navigate to specific tab
        function goToTab(index) {
            if (index < 0 || index >= tabs.length) return;

            // Validate current tab before moving forward
            if (index > currentTab && !validateCurrentTab()) {
                return;
            }

            currentTab = index;
            const tabId = tabs[index];
            const tabButton = document.getElementById(tabId + '-tab');

            if (tabButton) {
                const tab = new bootstrap.Tab(tabButton);
                tab.show();
            }

            updateButtons();
            saveDraft();
        }

        // ISSUE 5 FIX: Validate current tab with single toast
        function validateCurrentTab() {
            const currentTabPane = document.getElementById(tabs[currentTab]);
            if (!currentTabPane) return true;

            const inputs = currentTabPane.querySelectorAll('input[required], select[required], textarea[required]');
            let isValid = true;
            let errorCount = 0;

            inputs.forEach(input => {
                // Skip file inputs for validation
                if (input.type === 'file') return;

                if (!input.value || (input.type === 'email' && !isValidEmail(input.value))) {
                    input.classList.add('is-invalid');
                    isValid = false;
                    errorCount++;

                    // Show inline error message
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

            // ISSUE 5 FIX: Show only ONE toast per tab validation
            if (!isValid && !toastShown[currentTab]) {
                showToast(`Please fill in all required fields (${errorCount} field${errorCount > 1 ? 's' : ''} missing)`, 'error');
                toastShown[currentTab] = true;
            }

            return isValid;
        }

        // Email validation
        function isValidEmail(email) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        }

        // Attach event listeners
        function attachEventListeners() {
            prevBtn.addEventListener('click', () => goToTab(currentTab - 1));
            nextBtn.addEventListener('click', () => goToTab(currentTab + 1));
            submitBtn.addEventListener('click', handleSubmit);

            // Tab click navigation
            document.querySelectorAll('#clubModalTabs button[data-bs-toggle="tab"]').forEach((button, index) => {
                button.addEventListener('click', (e) => {
                    // Reset toast tracking for the new tab
                    toastShown[index] = false;

                    // Allow clicking on previous tabs, but validate before going forward
                    if (index > currentTab && !validateCurrentTab()) {
                        e.preventDefault();
                        return;
                    }
                    currentTab = index;
                    updateButtons();
                });
            });

            // Clear validation on input
            form.addEventListener('input', (e) => {
                if (e.target.classList.contains('is-invalid')) {
                    e.target.classList.remove('is-invalid');
                    // Reset toast for this tab
                    toastShown[currentTab] = false;
                }
            });

            // Save draft periodically
            setInterval(saveDraft, 30000); // Every 30 seconds
        }

        // Handle form submission
        async function handleSubmit() {
            // Validate all tabs
            let allValid = true;
            for (let i = 0; i < tabs.length; i++) {
                currentTab = i;
                if (!validateCurrentTab()) {
                    allValid = false;
                    goToTab(i);
                    break;
                }
            }

            if (!allValid) return;

            const formData = new FormData(form);
            const mode = form.dataset.mode;
            const clubId = form.dataset.clubId;

            // Show loading state
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';

            try {
                const url = mode === 'edit'
                    ? `/admin/clubs/${clubId}`
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
                    showToast(data.message || 'Club saved successfully!', 'success');
                    clearDraft();

                    // Close modal and reload page
                    setTimeout(() => {
                        bootstrap.Modal.getInstance(modal).hide();
                        window.location.reload();
                    }, 1500);
                } else {
                    showToast(data.message || 'An error occurred', 'error');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="bi bi-check-circle me-2"></i>' + (mode === 'edit' ? 'Update Club' : 'Create Club');
                }
            } catch (error) {
                console.error('Error:', error);
                showToast('An error occurred while saving', 'error');
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="bi bi-check-circle me-2"></i>' + (mode === 'edit' ? 'Update Club' : 'Create Club');
            }
        }

        // Save draft to localStorage
        function saveDraft() {
            if (form.dataset.mode === 'create') {
                const formData = new FormData(form);
                const draft = {};
                for (let [key, value] of formData.entries()) {
                    // ISSUE 2 FIX: Skip file inputs
                    const input = form.querySelector(`[name="${key}"]`);
                    if (input && input.type !== 'file') {
                        draft[key] = value;
                    }
                }
                localStorage.setItem('clubModalDraft', JSON.stringify(draft));
            }
        }

        // ISSUE 2 FIX: Load draft from localStorage (skip file inputs)
        function loadDraft() {
            if (form.dataset.mode === 'create') {
                const draft = localStorage.getItem('clubModalDraft');
                if (draft) {
                    try {
                        const data = JSON.parse(draft);
                        Object.keys(data).forEach(key => {
                            const input = form.querySelector(`[name="${key}"]`);
                            // ISSUE 2 FIX: Never set value on file inputs
                            if (input && input.type !== 'file' && !input.value) {
                                input.value = data[key];
                            }
                        });
                    } catch (e) {
                        console.error('Error loading draft:', e);
                        // Don't show toast for draft loading errors
                    }
                }
            }
        }

        // Clear draft
        function clearDraft() {
            localStorage.removeItem('clubModalDraft');
        }

        // Show toast notification
        function showToast(message, type = 'info') {
            // Use your existing toast notification system
            if (typeof Toast !== 'undefined') {
                if (type === 'success') {
                    Toast.success('Success', message);
                } else if (type === 'error') {
                    Toast.error('Error', message);
                } else {
                    Toast.info('Info', message);
                }
            } else {
                alert(message);
            }
        }

        // Initialize when modal is shown
        modal.addEventListener('shown.bs.modal', init);

        // Reset on modal close
        modal.addEventListener('hidden.bs.modal', () => {
            currentTab = 0;
            draftLoaded = false;
            toastShown = {};
            form.reset();
            updateButtons();
        });
    })();
</script>
@endpush
@endonce
