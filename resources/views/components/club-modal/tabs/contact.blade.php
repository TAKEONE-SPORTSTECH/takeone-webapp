@props(['club' => null, 'mode' => 'create'])

@php
    $isEdit = $mode === 'edit' && $club;
@endphp

<div class="container-fluid px-0">
    <h5 class="fw-bold mb-3">Contact Information</h5>
    <p class="text-muted mb-4">Set up how members can reach your club</p>

    <!-- Club Email -->
    <div class="mb-4">
        <label class="form-label">Club Email</label>

        <!-- Toggle: Use Owner Email or Custom -->
        <div class="form-check mb-3">
            <input class="form-check-input"
                   type="radio"
                   name="email_option"
                   id="email_option_owner"
                   value="owner"
                   {{ (!$isEdit || !$club->email) ? 'checked' : '' }}>
            <label class="form-check-label" for="email_option_owner">
                Use Club Owner's Email
            </label>
        </div>

        <div class="form-check mb-3">
            <input class="form-check-input"
                   type="radio"
                   name="email_option"
                   id="email_option_custom"
                   value="custom"
                   {{ ($isEdit && $club->email) ? 'checked' : '' }}>
            <label class="form-check-label" for="email_option_custom">
                Use Custom Email
            </label>
        </div>

        <!-- Owner Email Display (Read-only) -->
        <div id="ownerEmailDisplay" class="border rounded p-3 mb-3" style="background-color: hsl(var(--muted) / 0.2); display: {{ (!$isEdit || !$club->email) ? 'block' : 'none' }};">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-envelope text-muted"></i>
                <span id="ownerEmailText" class="text-muted">
                    @if($isEdit && $club->owner)
                        {{ $club->owner->email }}
                    @else
                        Select a club owner first
                    @endif
                </span>
            </div>
        </div>

        <!-- Custom Email Input -->
        <div id="customEmailInput" style="display: {{ ($isEdit && $club->email) ? 'block' : 'none' }};">
            <input type="email"
                   class="form-control"
                   id="email"
                   name="email"
                   value="{{ $club->email ?? old('email') }}"
                   placeholder="club@example.com">
            <small class="text-muted">A dedicated email address for club communications</small>
        </div>
    </div>

    <!-- Club Phone -->
    <div class="mb-4">
        <label class="form-label">Club Phone Number</label>

        <!-- Toggle: Use Owner Phone or Custom -->
        <div class="form-check mb-3">
            <input class="form-check-input"
                   type="radio"
                   name="phone_option"
                   id="phone_option_owner"
                   value="owner"
                   {{ (!$isEdit || !$club->phone) ? 'checked' : '' }}>
            <label class="form-check-label" for="phone_option_owner">
                Use Club Owner's Phone
            </label>
        </div>

        <div class="form-check mb-3">
            <input class="form-check-input"
                   type="radio"
                   name="phone_option"
                   id="phone_option_custom"
                   value="custom"
                   {{ ($isEdit && $club->phone) ? 'checked' : '' }}>
            <label class="form-check-label" for="phone_option_custom">
                Use Custom Phone Number
            </label>
        </div>

        <!-- Owner Phone Display (Read-only) -->
        <div id="ownerPhoneDisplay" class="border rounded p-3 mb-3" style="background-color: hsl(var(--muted) / 0.2); display: {{ (!$isEdit || !$club->phone) ? 'block' : 'none' }};">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-phone text-muted"></i>
                <span id="ownerPhoneText" class="text-muted">
                    @if($isEdit && $club->owner && $club->owner->mobile)
                        {{ $club->owner->mobile_formatted }}
                    @else
                        Select a club owner first
                    @endif
                </span>
            </div>
        </div>

        <!-- Custom Phone Input -->
        <div id="customPhoneInput" style="display: {{ ($isEdit && $club->phone) ? 'block' : 'none' }};">
            <x-country-code-dropdown
                name="phone_code"
                id="phone_code"
                :value="$isEdit && $club->phone ? ($club->phone['code'] ?? '+973') : old('phone_code', '+973')"
                :required="false"
                :error="null">
                <input type="text"
                       class="form-control"
                       name="phone_number"
                       id="phone_number"
                       value="{{ $isEdit && $club->phone ? ($club->phone['number'] ?? '') : old('phone_number') }}"
                       placeholder="12345678">
            </x-country-code-dropdown>
            <small class="text-muted">A dedicated phone number for club inquiries</small>
        </div>
    </div>

    <!-- Additional Contact Info (Optional) -->
    <div class="alert alert-info" role="alert">
        <i class="bi bi-info-circle me-2"></i>
        <strong>Tip:</strong> You can add more contact methods (WhatsApp, social media, website) in the <strong>Identity & Branding</strong> tab under Social Media Links.
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Email option toggle
        const emailOptionOwner = document.getElementById('email_option_owner');
        const emailOptionCustom = document.getElementById('email_option_custom');
        const ownerEmailDisplay = document.getElementById('ownerEmailDisplay');
        const customEmailInput = document.getElementById('customEmailInput');
        const emailInput = document.getElementById('email');

        if (emailOptionOwner && emailOptionCustom) {
            emailOptionOwner.addEventListener('change', function() {
                if (this.checked) {
                    ownerEmailDisplay.style.display = 'block';
                    customEmailInput.style.display = 'none';
                    if (emailInput) emailInput.value = '';
                }
            });

            emailOptionCustom.addEventListener('change', function() {
                if (this.checked) {
                    ownerEmailDisplay.style.display = 'none';
                    customEmailInput.style.display = 'block';
                }
            });
        }

        // Phone option toggle
        const phoneOptionOwner = document.getElementById('phone_option_owner');
        const phoneOptionCustom = document.getElementById('phone_option_custom');
        const ownerPhoneDisplay = document.getElementById('ownerPhoneDisplay');
        const customPhoneInput = document.getElementById('customPhoneInput');
        const phoneNumberInput = document.getElementById('phone_number');

        if (phoneOptionOwner && phoneOptionCustom) {
            phoneOptionOwner.addEventListener('change', function() {
                if (this.checked) {
                    ownerPhoneDisplay.style.display = 'block';
                    customPhoneInput.style.display = 'none';
                    if (phoneNumberInput) phoneNumberInput.value = '';
                }
            });

            phoneOptionCustom.addEventListener('change', function() {
                if (this.checked) {
                    ownerPhoneDisplay.style.display = 'none';
                    customPhoneInput.style.display = 'block';
                }
            });
        }

        // Update owner email/phone display when owner is selected
        document.addEventListener('ownerSelected', function(e) {
            const owner = e.detail;

            // Update email display
            const ownerEmailText = document.getElementById('ownerEmailText');
            if (ownerEmailText && owner.email) {
                ownerEmailText.textContent = owner.email;
            }

            // Update phone display
            const ownerPhoneText = document.getElementById('ownerPhoneText');
            if (ownerPhoneText && owner.mobile) {
                ownerPhoneText.textContent = owner.mobile;
            }
        });

        // Listen for owner selection from user picker
        const ownerInput = document.getElementById('owner_user_id');
        if (ownerInput) {
            // Create a MutationObserver to watch for value changes
            const observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.type === 'attributes' && mutation.attributeName === 'value') {
                        // Owner changed, update displays if using owner's contact info
                        updateOwnerContactInfo();
                    }
                });
            });
            observer.observe(ownerInput, { attributes: true });

            ownerInput.addEventListener('change', updateOwnerContactInfo);
        }
    });

    function updateOwnerContactInfo() {
        // This function would ideally fetch the owner's details
        // For now, it's handled by the user picker modal
        // which updates the display directly
    }
</script>
@endpush
