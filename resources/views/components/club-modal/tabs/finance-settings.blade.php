@props(['club' => null, 'mode' => 'create'])

@php
    $isEdit = $mode === 'edit' && $club;
@endphp

<div class="px-0">
    <h5 class="font-bold mb-3">{{ __('shared.finance_settings_title') }}</h5>
    <p class="text-muted-foreground mb-4">{{ __('shared.finance_settings_subtitle') }}</p>

    <!-- Bank Accounts Section -->
    <div class="mb-5">
        <h6 class="font-semibold mb-3">
            <i class="bi bi-bank me-2"></i>{{ __('shared.finance_settings_bank_accounts') }}
        </h6>
        <p class="text-muted-foreground text-sm mb-3">{{ __('shared.finance_settings_bank_accounts_help') }}</p>

        <div id="bankAccountsContainer">
            @if($isEdit && $club->bankAccounts && $club->bankAccounts->count() > 0)
                @foreach($club->bankAccounts as $index => $account)
                    <div class="bank-account-block border border-border rounded-lg p-4 mb-3 bg-muted/10" data-index="{{ $index }}">
                        <div class="flex justify-between items-center mb-3">
                            <h6 class="font-semibold mb-0">{{ __('shared.finance_settings_bank_account_prefix') }}{{ $index + 1 }}</h6>
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeBankAccount(this)">
                                <i class="bi bi-trash"></i> {{ __('shared.finance_settings_remove') }}
                            </button>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label class="form-label">{{ __('shared.finance_settings_bank_name') }} <span class="text-destructive">*</span></label>
                                <input type="text"
                                       class="form-control"
                                       name="bank_accounts[{{ $index }}][bank_name]"
                                       value="{{ $account->bank_name }}"
                                       placeholder="{{ __('shared.finance_settings_bank_name_placeholder') }}"
                                       required>
                            </div>
                            <div>
                                <label class="form-label">{{ __('shared.finance_settings_account_name') }} <span class="text-destructive">*</span></label>
                                <input type="text"
                                       class="form-control"
                                       name="bank_accounts[{{ $index }}][account_name]"
                                       value="{{ $account->account_name }}"
                                       placeholder="{{ __('shared.finance_settings_account_name_placeholder') }}"
                                       required>
                            </div>
                            <div>
                                <label class="form-label">{{ __('shared.finance_settings_account_number') }}</label>
                                <input type="text"
                                       class="form-control"
                                       name="bank_accounts[{{ $index }}][account_number]"
                                       value="{{ $account->account_number }}"
                                       placeholder="{{ __('shared.finance_settings_account_number_placeholder') }}">
                            </div>
                            <div>
                                <label class="form-label">{{ __('shared.finance_settings_iban') }}</label>
                                <input type="text"
                                       class="form-control"
                                       name="bank_accounts[{{ $index }}][iban]"
                                       value="{{ $account->iban }}"
                                       placeholder="{{ __('shared.finance_settings_iban_placeholder') }}"
                                       pattern="[A-Z]{2}[0-9]{2}[A-Z0-9]+">
                            </div>
                            <div>
                                <label class="form-label">{{ __('shared.finance_settings_swift_code') }}</label>
                                <input type="text"
                                       class="form-control"
                                       name="bank_accounts[{{ $index }}][swift_code]"
                                       value="{{ $account->swift_code }}"
                                       placeholder="{{ __('shared.finance_settings_swift_placeholder') }}"
                                       pattern="[A-Z]{6}[A-Z0-9]{2}([A-Z0-9]{3})?">
                            </div>
                            <div>
                                <label class="form-label">{{ __('shared.finance_settings_benefitpay') }}</label>
                                <input type="text"
                                       class="form-control"
                                       name="bank_accounts[{{ $index }}][benefitpay_account]"
                                       value="{{ $account->benefitpay_account ?? '' }}"
                                       placeholder="{{ __('shared.finance_settings_optional') }}">
                                <small class="text-muted-foreground">{{ __('shared.finance_settings_benefitpay_help') }}</small>
                            </div>
                        </div>
                    </div>
                @endforeach
            @endif
        </div>

        <button type="button" class="btn btn-outline-primary" onclick="addBankAccount()">
            <i class="bi bi-plus-circle me-2"></i>{{ __('shared.finance_settings_add_bank_account') }}
        </button>
    </div>

    <!-- Club Status & Visibility -->
    <div class="mb-4">
        <h6 class="font-semibold mb-3">
            <i class="bi bi-gear me-2"></i>{{ __('shared.finance_settings_status_visibility') }}
        </h6>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <!-- Club Status -->
            <div>
                <label for="club_status" class="form-label">{{ __('shared.finance_settings_club_status') }}</label>
                <select class="form-select" id="club_status" name="club_status">
                    <option value="active" {{ ($club->status ?? 'active') === 'active' ? 'selected' : '' }}>
                        {{ __('shared.finance_settings_status_active') }}
                    </option>
                    <option value="inactive" {{ ($club->status ?? '') === 'inactive' ? 'selected' : '' }}>
                        {{ __('shared.finance_settings_status_inactive') }}
                    </option>
                    <option value="pending" {{ ($club->status ?? '') === 'pending' ? 'selected' : '' }}>
                        {{ __('shared.finance_settings_status_pending') }}
                    </option>
                </select>
                <small class="text-muted-foreground">{{ __('shared.finance_settings_club_status_help') }}</small>
            </div>

            <!-- Public Profile -->
            <div>
                <label class="form-label">{{ __('shared.finance_settings_public_profile') }}</label>
                <div class="form-check form-switch pt-2">
                    <input class="form-check-input"
                           type="checkbox"
                           role="switch"
                           id="public_profile_enabled"
                           name="public_profile_enabled"
                           value="1"
                           {{ ($club->public_profile_enabled ?? true) ? 'checked' : '' }}>
                    <label class="form-check-label" for="public_profile_enabled">
                        {{ __('shared.finance_settings_enable_public_profile') }}
                    </label>
                </div>
                <small class="text-muted-foreground">{{ __('shared.finance_settings_public_profile_help') }}</small>
            </div>
        </div>
    </div>

    <!-- Joining Fees (Optional) -->
    <div class="mb-4 grid grid-cols-1 md:grid-cols-2 gap-3">
        <div>
            <label for="registration_fee" class="form-label">{{ __('shared.finance_settings_registration_fee') }}</label>
            <div class="input-group">
                <span class="input-group-text">{{ $club->currency ?? 'BHD' }}</span>
                <input type="number"
                       class="form-control"
                       id="registration_fee"
                       name="registration_fee"
                       value="{{ $club->registration_fee ?? old('registration_fee', '0.00') }}"
                       min="0"
                       step="0.01"
                       placeholder="0.00">
            </div>
            <small class="text-muted-foreground">{{ __('shared.finance_settings_registration_fee_help') }}</small>
        </div>
        <div>
            <label for="enrollment_fee" class="form-label">{{ __('shared.finance_settings_enrollment_fee') }}</label>
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
            <small class="text-muted-foreground">{{ __('shared.finance_settings_enrollment_fee_help') }}</small>
        </div>
    </div>

    <!-- Summary Info Box -->
    <div class="alert alert-light border border-border" role="alert">
        <h6 class="font-semibold mb-2">
            <i class="bi bi-info-circle me-2"></i>{{ __('shared.finance_settings_summary') }}
        </h6>
        <ul class="mb-0 text-sm list-none">
            <li><strong>{{ __('shared.finance_settings_summary_bank_accounts_label') }}</strong> <span id="bankAccountCount">{{ $isEdit && $club->bankAccounts ? $club->bankAccounts->count() : 0 }}</span> {{ __('shared.finance_settings_summary_configured') }}</li>
            <li><strong>{{ __('shared.finance_settings_summary_status_label') }}</strong> {{ __('shared.finance_settings_summary_status_text') }} <span id="statusSummary" class="font-semibold">{{ __('shared.finance_settings_status_active') }}</span></li>
            <li><strong>{{ __('shared.finance_settings_summary_public_label') }}</strong> <span id="publicAccessSummary" class="font-semibold">{{ __('shared.finance_settings_enabled') }}</span></li>
        </ul>
    </div>

    <!-- Metadata Info (Read-only) -->
    @if($isEdit)
        <div class="border-t border-border pt-4 mt-4">
            <h6 class="text-muted-foreground text-sm uppercase mb-3">{{ __('shared.finance_settings_metadata') }}</h6>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm text-muted-foreground">
                <div>
                    <i class="bi bi-calendar-plus me-2"></i>
                    <strong>{{ __('shared.finance_settings_created') }}</strong> {{ $club->created_at->format('M d, Y') }}
                </div>
                <div>
                    <i class="bi bi-calendar-check me-2"></i>
                    <strong>{{ __('shared.finance_settings_last_updated') }}</strong> {{ $club->updated_at->format('M d, Y') }}
                </div>
                @if($club->owner)
                    <div class="col-span-full">
                        <i class="bi bi-person me-2"></i>
                        <strong>{{ __('shared.finance_settings_owner') }}</strong> {{ $club->owner->full_name }}
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

        const statusSelect = document.getElementById('club_status');
        if (statusSelect) {
            statusSelect.addEventListener('change', updateSummary);
        }

        const publicProfileToggle = document.getElementById('public_profile_enabled');
        if (publicProfileToggle) {
            publicProfileToggle.addEventListener('change', updateSummary);
        }
    });

    function addBankAccount() {
        const container = document.getElementById('bankAccountsContainer');
        if (!container) return;

        const block = document.createElement('div');
        block.className = 'bank-account-block border border-border rounded-lg p-4 mb-3 bg-muted/10';
        block.dataset.index = bankAccountIndex;
        block.innerHTML = `
            <div class="flex justify-between items-center mb-3">
                <h6 class="font-semibold mb-0">{{ __('shared.finance_settings_bank_account_prefix') }}${bankAccountIndex + 1}</h6>
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeBankAccount(this)">
                    <i class="bi bi-trash"></i> {{ __('shared.finance_settings_remove') }}
                </button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <label class="form-label">{{ __('shared.finance_settings_bank_name') }} <span class="text-destructive">*</span></label>
                    <input type="text"
                           class="form-control"
                           name="bank_accounts[${bankAccountIndex}][bank_name]"
                           placeholder="{{ __('shared.finance_settings_bank_name_placeholder') }}"
                           required>
                </div>
                <div>
                    <label class="form-label">{{ __('shared.finance_settings_account_name') }} <span class="text-destructive">*</span></label>
                    <input type="text"
                           class="form-control"
                           name="bank_accounts[${bankAccountIndex}][account_name]"
                           placeholder="{{ __('shared.finance_settings_account_name_placeholder') }}"
                           required>
                </div>
                <div>
                    <label class="form-label">{{ __('shared.finance_settings_account_number') }}</label>
                    <input type="text"
                           class="form-control"
                           name="bank_accounts[${bankAccountIndex}][account_number]"
                           placeholder="{{ __('shared.finance_settings_account_number_placeholder') }}">
                </div>
                <div>
                    <label class="form-label">{{ __('shared.finance_settings_iban') }}</label>
                    <input type="text"
                           class="form-control"
                           name="bank_accounts[${bankAccountIndex}][iban]"
                           placeholder="{{ __('shared.finance_settings_iban_placeholder') }}"
                           pattern="[A-Z]{2}[0-9]{2}[A-Z0-9]+">
                </div>
                <div>
                    <label class="form-label">{{ __('shared.finance_settings_swift_code') }}</label>
                    <input type="text"
                           class="form-control"
                           name="bank_accounts[${bankAccountIndex}][swift_code]"
                           placeholder="{{ __('shared.finance_settings_swift_placeholder') }}"
                           pattern="[A-Z]{6}[A-Z0-9]{2}([A-Z0-9]{3})?">
                </div>
                <div>
                    <label class="form-label">{{ __('shared.finance_settings_benefitpay') }}</label>
                    <input type="text"
                           class="form-control"
                           name="bank_accounts[${bankAccountIndex}][benefitpay_account]"
                           placeholder="{{ __('shared.finance_settings_optional') }}">
                    <small class="text-muted-foreground">{{ __('shared.finance_settings_benefitpay_help') }}</small>
                </div>
            </div>
        `;

        container.appendChild(block);
        bankAccountIndex++;
        updateSummary();
        block.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    async function removeBankAccount(button) {
        const block = button.closest('.bank-account-block');
        if (block) {
            const ok = await window.confirmAction({ title: '{{ __("shared.finance_settings_remove_confirm_title") }}', message: '{{ __("shared.finance_settings_remove_confirm_message") }}', type: 'danger', confirmText: '{{ __("shared.finance_settings_remove") }}' });
            if (ok) {
                block.remove();
                updateSummary();
                renumberBankAccounts();
            }
        }
    }

    function renumberBankAccounts() {
        const blocks = document.querySelectorAll('.bank-account-block');
        blocks.forEach((block, index) => {
            const heading = block.querySelector('h6');
            if (heading) {
                heading.textContent = `{{ __('shared.finance_settings_bank_account_prefix') }}${index + 1}`;
            }
        });
    }

    function updateSummary() {
        const bankAccountCount = document.querySelectorAll('.bank-account-block').length;
        const countElement = document.getElementById('bankAccountCount');
        if (countElement) {
            countElement.textContent = bankAccountCount;
        }

        const statusSelect = document.getElementById('club_status');
        const statusSummary = document.getElementById('statusSummary');
        if (statusSelect && statusSummary) {
            const statusText = statusSelect.options[statusSelect.selectedIndex].text;
            statusSummary.textContent = statusText;
            statusSummary.className = 'font-semibold';
            if (statusSelect.value === 'active') {
                statusSummary.classList.add('text-success');
            } else if (statusSelect.value === 'inactive') {
                statusSummary.classList.add('text-destructive');
            } else {
                statusSummary.classList.add('text-warning');
            }
        }

        const publicToggle = document.getElementById('public_profile_enabled');
        const publicSummary = document.getElementById('publicAccessSummary');
        if (publicToggle && publicSummary) {
            publicSummary.textContent = publicToggle.checked ? '{{ __("shared.finance_settings_enabled") }}' : '{{ __("shared.finance_settings_disabled") }}';
            publicSummary.className = publicToggle.checked ? 'font-semibold text-success' : 'font-semibold text-muted-foreground';
        }
    }
</script>
@endpush
