@props(['club' => null, 'mode' => 'create'])

@php
    $isEdit = $mode === 'edit' && $club;
@endphp

<div class="container-fluid px-0">
    <h5 class="fw-bold mb-3">Basic Information</h5>
    <p class="text-muted mb-4">Core details about the club</p>

    <!-- Club Name -->
    <div class="mb-4">
        <label for="club_name" class="form-label">
            Club Name <span class="text-danger">*</span>
        </label>
        <input type="text"
               class="form-control"
               id="club_name"
               name="club_name"
               value="{{ $club->club_name ?? old('club_name') }}"
               required
               data-error-message="Club name is required"
               placeholder="e.g., Bahrain Taekwondo Academy">
        <div class="invalid-feedback">Club name is required.</div>
    </div>

    <!-- Club Owner -->
    <div class="mb-4">
        <label class="form-label">
            Club Owner <span class="text-danger">*</span>
        </label>
        <input type="hidden" id="owner_user_id" name="owner_user_id" value="{{ $club->owner_user_id ?? old('owner_user_id') }}" required>

        <div id="ownerDisplay" class="border rounded p-3 mb-2" style="background-color: hsl(var(--muted) / 0.3);">
            @if($isEdit && $club->owner)
                <div class="d-flex align-items-center gap-3">
                    @if($club->owner->profile_picture)
                        <img src="{{ asset('storage/' . $club->owner->profile_picture) }}"
                             alt="{{ $club->owner->full_name }}"
                             class="rounded-circle"
                             style="width: 50px; height: 50px; object-fit: cover;">
                    @else
                        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center"
                             style="width: 50px; height: 50px; font-size: 1.25rem; font-weight: 600;">
                            {{ substr($club->owner->full_name, 0, 1) }}
                        </div>
                    @endif
                    <div class="flex-grow-1">
                        <div class="fw-semibold">{{ $club->owner->full_name }}</div>
                        <div class="small text-muted">
                            <i class="bi bi-envelope me-1"></i>{{ $club->owner->email }}
                            @if($club->owner->mobile)
                                <span class="ms-2"><i class="bi bi-phone me-1"></i>{{ $club->owner->mobile_formatted }}</span>
                            @endif
                        </div>
                    </div>
                </div>
            @else
                <div class="text-center text-muted py-3" id="noOwnerSelected">
                    <i class="bi bi-person-plus fs-3 mb-2"></i>
                    <p class="mb-0">No owner selected</p>
                </div>
            @endif
        </div>

        <button type="button" class="btn btn-outline-primary btn-sm" onclick="showUserPicker()">
            <i class="bi bi-search me-2"></i>Select Club Owner
        </button>
        <div class="invalid-feedback d-block" id="ownerError" style="display: none !important;">
            Please select a club owner.
        </div>
    </div>

    <!-- Internal User Picker Overlay (NOT a separate modal) -->
    <div id="userPickerOverlay" class="user-picker-overlay" style="display: none;">
        <div class="user-picker-panel">
            <div class="user-picker-header">
                <div>
                    <h5 class="fw-bold mb-1">Select Club Owner</h5>
                    <p class="text-muted small mb-0">Search and select a user to be the club owner</p>
                </div>
                <button type="button" class="btn-close" onclick="hideUserPicker()"></button>
            </div>

            <div class="user-picker-body">
                <div class="mb-3">
                    <div class="input-group">
                        <span class="input-group-text bg-white">
                            <i class="bi bi-search"></i>
                        </span>
                        <input type="text"
                               class="form-control"
                               id="userSearchInputInternal"
                               placeholder="Search by name, email, or phone..."
                               autocomplete="off">
                    </div>
                </div>

                <div id="userPickerLoadingInternal" class="text-center py-5" style="display: none;">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="text-muted mt-2">Searching users...</p>
                </div>

                <div id="userPickerResultsInternal" style="max-height: 400px; overflow-y: auto;"></div>

                <div id="userPickerNoResultsInternal" class="text-center py-5" style="display: none;">
                    <i class="bi bi-person-x fs-1 text-muted mb-3"></i>
                    <p class="text-muted mb-0">No users found</p>
                    <small class="text-muted">Try a different search term</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Established Date -->
    <div class="mb-4">
        <label for="established_date" class="form-label">
            Established Date
        </label>
        <input type="date"
               class="form-control"
               id="established_date"
               name="established_date"
               value="{{ $club->established_date ?? old('established_date') }}"
               max="{{ date('Y-m-d') }}">
        <small class="text-muted">When was the club founded?</small>
    </div>

    <!-- Slogan -->
    <div class="mb-4">
        <label for="slogan" class="form-label">
            Slogan
        </label>
        <input type="text"
               class="form-control"
               id="slogan"
               name="slogan"
               value="{{ $club->slogan ?? old('slogan') }}"
               placeholder="e.g., Excellence in Martial Arts"
               maxlength="100">
        <small class="text-muted">A short, memorable tagline (max 100 characters)</small>
    </div>

    <!-- Description -->
    <div class="mb-4">
        <label for="description" class="form-label">
            Description
        </label>
        <textarea class="form-control"
                  id="description"
                  name="description"
                  rows="4"
                  placeholder="Describe your club, its mission, and what makes it unique..."
                  maxlength="1000">{{ $club->description ?? old('description') }}</textarea>
        <small class="text-muted">
            <span id="descriptionCount">0</span>/1000 characters
        </small>
    </div>

    <!-- Commercial Registration -->
    <div class="row mb-4">
        <div class="col-md-6">
            <label for="commercial_reg_number" class="form-label">
                Commercial Registration Number
            </label>
            <input type="text"
                   class="form-control"
                   id="commercial_reg_number"
                   name="commercial_reg_number"
                   value="{{ $club->commercial_reg_number ?? old('commercial_reg_number') }}"
                   placeholder="e.g., CR-123456">
            <small class="text-muted">Official business registration number</small>
        </div>
        <div class="col-md-6">
            <label for="commercial_reg_file" class="form-label">
                Registration Document
            </label>
            <input type="file"
                   class="form-control"
                   id="commercial_reg_file"
                   name="commercial_reg_file"
                   accept=".pdf,.jpg,.jpeg,.png">
            <small class="text-muted">Upload registration certificate (optional)</small>
        </div>
    </div>

    <!-- VAT Information -->
    <div class="row mb-4">
        <div class="col-md-6">
            <label for="vat_reg_number" class="form-label">
                VAT Registration Number
            </label>
            <input type="text"
                   class="form-control"
                   id="vat_reg_number"
                   name="vat_reg_number"
                   value="{{ $club->vat_reg_number ?? old('vat_reg_number') }}"
                   placeholder="e.g., VAT-123456789">
            <small class="text-muted">Tax registration number (if applicable)</small>
        </div>
        <div class="col-md-6">
            <label for="vat_percentage" class="form-label">
                VAT Percentage (%)
            </label>
            <input type="number"
                   class="form-control"
                   id="vat_percentage"
                   name="vat_percentage"
                   value="{{ $club->vat_percentage ?? old('vat_percentage', '0') }}"
                   min="0"
                   max="100"
                   step="0.01"
                   placeholder="e.g., 5.00">
            <small class="text-muted">Default VAT rate for invoices</small>
        </div>
    </div>

    <!-- VAT Certificate Upload -->
    <div class="mb-4">
        <label for="vat_certificate_file" class="form-label">
            VAT Certificate
        </label>
        <input type="file"
               class="form-control"
               id="vat_certificate_file"
               name="vat_certificate_file"
               accept=".pdf,.jpg,.jpeg,.png">
        <small class="text-muted">Upload VAT registration certificate (optional)</small>
    </div>
</div>

@push('scripts')
<script>
    // Character counter for description
    document.addEventListener('DOMContentLoaded', function() {
        const descriptionTextarea = document.getElementById('description');
        const descriptionCount = document.getElementById('descriptionCount');

        if (descriptionTextarea && descriptionCount) {
            function updateCount() {
                descriptionCount.textContent = descriptionTextarea.value.length;
            }

            descriptionTextarea.addEventListener('input', updateCount);
            updateCount(); // Initial count
        }

        // Auto-generate slug from club name (will be used in Identity tab)
        const clubNameInput = document.getElementById('club_name');
        if (clubNameInput) {
            clubNameInput.addEventListener('input', function() {
                const slug = this.value
                    .toLowerCase()
                    .replace(/[^a-z0-9]+/g, '-')
                    .replace(/^-+|-+$/g, '');

                const slugInput = document.getElementById('slug');
                if (slugInput && !slugInput.dataset.manuallyEdited) {
                    slugInput.value = slug;
                    // Trigger slug change event for URL preview
                    slugInput.dispatchEvent(new Event('input'));
                }
            });
        }

        // Validate owner selection
        const ownerInput = document.getElementById('owner_user_id');
        if (ownerInput) {
            ownerInput.addEventListener('change', function() {
                const ownerError = document.getElementById('ownerError');
                if (this.value) {
                    ownerError.style.display = 'none';
                    this.classList.remove('is-invalid');
                } else {
                    ownerError.style.display = 'block';
                    this.classList.add('is-invalid');
                }
            });
        }
    });
</script>
@endpush
