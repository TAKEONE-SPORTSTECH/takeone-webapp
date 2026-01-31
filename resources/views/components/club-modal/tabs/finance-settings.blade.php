@props(['club' => null, 'mode' => 'create'])

@php
    $isEdit = $mode === 'edit' && $club;
@endphp

<div class="container-fluid px-0">
    <h5 class="fw-bold mb-3">Finance & Settings</h5>
    <p class="text-muted mb-4">Configure bank accounts and club status</p>

    <!-- Bank Accounts Section -->
    <div class="mb-5">
        <h6 class="fw-semibold mb-3">
            <i class="bi bi-bank me-2"></i>Bank Accounts
        </h6>
        <p class="text-muted small mb-3">Add one or more bank accounts for receiving payments</p>

        <div id="bankAccountsContainer">
            @if($isEdit && $club->bankAccounts && $club->bankAccounts->count() > 0)
                @foreach($club->bankAccounts as $index => $account)
                    <div class="bank-account-block border rounded p-4 mb-3" data-index="{{ $index }}" style="background-color: hsl(var(--muted) / 0.1);">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="fw-semibold mb-0">Bank Account #{{ $index + 1 }}</h6>
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeBankAccount(this)">
                                <i class="bi bi-trash"></i> Remove
                            </button>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Bank Name <span class="text-danger">*</span></label>
                                <input type="text"
                                       class="form-control"
                                       name="bank_accounts[{{ $index }}][bank_name]"
                                       value="{{ $account->bank_name }}"
                                       placeholder="e.g., National Bank of Bahrain"
                                       required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Account Name <span class="text-danger">*</span></label>
                                <input type="text"
                                       class="form-control"
                                       name="bank_accounts[{{ $index }}][account_name]"
                                       value="{{ $account->account_name }}"
                                       placeholder="e.g., Club Name Ltd."
                                       required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Account Number</label>
                                <input type="text"
                                       class="form-control"
                                       name="bank_accounts[{{ $index }}][account_number]"
                                       value="{{ $account->account_number }}"
                                       placeholder="e.g., 1234567890">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">IBAN</label>
                                <input type="text"
                                       class="form-control"
                                       name="bank_accounts[{{ $index }}][iban]"
                                       value="{{ $account->iban }}"
                                       placeholder="e.g., BH67BMAG00001299123456"
                                       pattern="[A-Z]{2}[0-9]{2}[A-Z0-9]+">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">SWIFT/BIC Code</label>
                                <input type="text"
                                       class="form-control"
                                       name="bank_accounts[{{ $index }}][swift_code]"
                                       value="{{ $account->swift_code }}"
                                       placeholder="e.g., BMAGBHBM"
                                       pattern="[A-Z]{6}[A-Z0-9]{2}([A-Z0-9]{3})?">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">BenefitPay Account</label>
                                <input type="text"
                                       class="form-control"
                                       name="bank_accounts[{{ $index }}][benefitpay_account]"
                                       value="{{ $account->benefitpay_account ?? '' }}"
                                       placeholder="Optional">
                                <small class="text-muted">Local payment system account number</small>
                            </div>
                        </div>
                    </div>
                @endforeach
            @endif
        </div>

        <button type="button" class="btn btn-outline-primary" onclick="addBankAccount()">
            <i class="bi bi-plus-circle me-2"></i>Add Bank Account
        </button>
    </div>

    <!-- Club Status & Visibility -->
    <div class="mb-4">
        <h6 class="fw-semibold mb-3">
            <i class="bi bi-gear me-2"></i>Club Status & Visibility
        </h6>

        <div class="row g-3">
            <!-- Club Status -->
            <div class="col-md-6">
                <label for="club_status" class="form-label">Club Status</label>
                <select class="form-select" id="club_status" name="club_status">
                    <option value="active" {{ ($club->status ?? 'active') === 'active' ? 'selected' : '' }}>
                        Active
                    </option>
                    <option value="inactive" {{ ($club->status ?? '') === 'inactive' ? 'selected' : '' }}>
                        Inactive
                    </option>
                    <option value="pending" {{ ($club->status ?? '') === 'pending' ? 'selected' : '' }}>
                        Pending
                    </option>
                </select>
                <small class="text-muted">Current operational status of the club</small>
            </div>

            <!-- Public Profile -->
            <div class="col-md-6">
                <label class="form-label">Public Profile</label>
                <div class="form-check form-switch" style="padding-top: 0.5rem;">
                    <input class="form-check-input"
                           type="checkbox"
                           role="switch"
                           id="public_profile_enabled"
                           name="public_profile_enabled"
                           value="1"
                           {{ ($club->public_profile_enabled ?? true) ? 'checked' : '' }}>
                    <label class="form-check-label" for="public_profile_enabled">
                        Enable public profile page
                    </label>
                </div>
                <small class="text-muted">Allow public access to club URL and QR code</small>
            </div>
        </div>
    </div>

    <!-- Enrollment Fee (Optional) -->
    <div class="mb-4">
        <label for="enrollment_fee" class="form-label">Enrollment Fee</label>
        <div class="input-group">
            <span class="input-group-text">{{ $club->currency ?? 'BHD' }}</span>
            <input type="number"
                   class="form-control"
                   id="enrollment_fee"
                   name="enrollment_fee"
                   value="{{ $club->enrollment_fee ?? old('enrollment_fee', '0.00') }}"
                   min="0"
                   step="0.01"
                   placeholder="0.00">
        </div>
        <small class="text-muted">One-time fee for new members (optional)</small>
    </div>

    <!-- Summary Info Box -->
    <div class="alert alert-light border" role="alert">
        <h6 class="alert-heading fw-semibold">
            <i class="bi bi-info-circle me-2"></i>Summary
        </h6>
        <ul class="mb-0 small">
            <li><strong>Bank Accounts:</strong> <span id="bankAccountCount">{{ $isEdit && $club->bankAccounts ? $club->bankAccounts->count() : 0 }}</span> configured</li>
            <li><strong>Status:</strong> Club will be set as <span id="statusSummary" class="fw-semibold">Active</span></li>
            <li><strong>Public Access:</strong> <span id="publicAccessSummary" class="fw-semibold">Enabled</span></li>
        </ul>
    </div>

    <!-- Metadata Info (Read-only) -->
    @if($isEdit)
        <div class="border-top pt-4 mt-4">
            <h6 class="text-muted small text-uppercase mb-3">Metadata</h6>
            <div class="row g-3 small text-muted">
                <div class="col-md-6">
                    <i class="bi bi-calendar-plus me-2"></i>
                    <strong>Created:</strong> {{ $club->created_at->format('M d, Y') }}
                </div>
                <div class="col-md-6">
                    <i class="bi bi-calendar-check me-2"></i>
                    <strong>Last Updated:</strong> {{ $club->updated_at->format('M d, Y') }}
                </div>
                @if($club->owner)
                    <div class="col-md-12">
                        <i class="bi bi-person me-2"></i>
                        <strong>Owner:</strong> {{ $club->owner->full_name }}
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>

@push('scripts')
<script>
    let bankAccountIndex = {{ $isEdit && $club->bankAccounts ? $club->bankAccounts->count() : 0 }};

    document.addEventListener('DOMContentLoaded', function() {
        updateSummary();

        // Update summary when status changes
        const statusSelect = document.getElementById('club_status');
        if (statusSelect) {
            statusSelect.addEventListener('change', updateSummary);
        }

        // Update summary when public profile toggle changes
        const publicProfileToggle = document.getElementById('public_profile_enabled');
        if (publicProfileToggle) {
            publicProfileToggle.addEventListener('change', updateSummary);
        }
    });

    // Add bank account block
    function addBankAccount() {
        const container = document.getElementById('bankAccountsContainer');
        if (!container) return;

        const block = document.createElement('div');
        block.className = 'bank-account-block border rounded p-4 mb-3';
        block.style.backgroundColor = 'hsl(var(--muted) / 0.1)';
        block.dataset.index = bankAccountIndex;
        block.innerHTML = `
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-semibold mb-0">Bank Account #${bankAccountIndex + 1}</h6>
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeBankAccount(this)">
                    <i class="bi bi-trash"></i> Remove
                </button>
            </div>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Bank Name <span class="text-danger">*</span></label>
                    <input type="text"
                           class="form-control"
                           name="bank_accounts[${bankAccountIndex}][bank_name]"
                           placeholder="e.g., National Bank of Bahrain"
                           required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Account Name <span class="text-danger">*</span></label>
                    <input type="text"
                           class="form-control"
                           name="bank_accounts[${bankAccountIndex}][account_name]"
                           placeholder="e.g., Club Name Ltd."
                           required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Account Number</label>
                    <input type="text"
                           class="form-control"
                           name="bank_accounts[${bankAccountIndex}][account_number]"
                           placeholder="e.g., 1234567890">
                </div>
                <div class="col-md-6">
                    <label class="form-label">IBAN</label>
                    <input type="text"
                           class="form-control"
                           name="bank_accounts[${bankAccountIndex}][iban]"
                           placeholder="e.g., BH67BMAG00001299123456"
                           pattern="[A-Z]{2}[0-9]{2}[A-Z0-9]+">
                </div>
                <div class="col-md-6">
                    <label class="form-label">SWIFT/BIC Code</label>
                    <input type="text"
                           class="form-control"
                           name="bank_accounts[${bankAccountIndex}][swift_code]"
                           placeholder="e.g., BMAGBHBM"
                           pattern="[A-Z]{6}[A-Z0-9]{2}([A-Z0-9]{3})?">
                </div>
                <div class="col-md-6">
                    <label class="form-label">BenefitPay Account</label>
                    <input type="text"
                           class="form-control"
                           name="bank_accounts[${bankAccountIndex}][benefitpay_account]"
                           placeholder="Optional">
                    <small class="text-muted">Local payment system account number</small>
                </div>
            </div>
        `;

        container.appendChild(block);
        bankAccountIndex++;
        updateSummary();

        // Scroll to the new account
        block.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    // Remove bank account block
    function removeBankAccount(button) {
        const block = button.closest('.bank-account-block');
        if (block) {
            if (confirm('Are you sure you want to remove this bank account?')) {
                block.remove();
                updateSummary();
                renumberBankAccounts();
            }
        }
    }

    // Renumber bank accounts after removal
    function renumberBankAccounts() {
        const blocks = document.querySelectorAll('.bank-account-block');
        blocks.forEach((block, index) => {
            const heading = block.querySelector('h6');
            if (heading) {
                heading.textContent = `Bank Account #${index + 1}`;
            }
        });
    }

    // Update summary
    function updateSummary() {
        // Update bank account count
        const bankAccountCount = document.querySelectorAll('.bank-account-block').length;
        const countElement = document.getElementById('bankAccountCount');
        if (countElement) {
            countElement.textContent = bankAccountCount;
        }

        // Update status summary
        const statusSelect = document.getElementById('club_status');
        const statusSummary = document.getElementById('statusSummary');
        if (statusSelect && statusSummary) {
            const statusText = statusSelect.options[statusSelect.selectedIndex].text;
            statusSummary.textContent = statusText;

            // Update color based on status
            statusSummary.className = 'fw-semibold';
            if (statusSelect.value === 'active') {
                statusSummary.classList.add('text-success');
            } else if (statusSelect.value === 'inactive') {
                statusSummary.classList.add('text-danger');
            } else {
                statusSummary.classList.add('text-warning');
            }
        }

        // Update public access summary
        const publicToggle = document.getElementById('public_profile_enabled');
        const publicSummary = document.getElementById('publicAccessSummary');
        if (publicToggle && publicSummary) {
            publicSummary.textContent = publicToggle.checked ? 'Enabled' : 'Disabled';
            publicSummary.className = publicToggle.checked ? 'fw-semibold text-success' : 'fw-semibold text-muted';
        }
    }
</script>
@endpush
