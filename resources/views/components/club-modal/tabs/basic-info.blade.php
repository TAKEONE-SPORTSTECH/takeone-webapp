@props(['club' => null, 'mode' => 'create', 'context' => 'admin'])

@php
    $isEdit = $mode === 'edit' && $club;
@endphp

<div class="px-0">
    <h5 class="font-bold mb-3">{{ __('shared.tabs_basic_info_title') }}</h5>
    <p class="text-muted-foreground mb-4">{{ __('shared.tabs_basic_info_subtitle') }}</p>

    <!-- Club Name -->
    <div class="mb-4">
        <label for="club_name" class="form-label">
            {{ __('shared.tabs_basic_info_club_name_label') }} <span class="text-destructive">*</span>
        </label>
        <input type="text"
               class="form-control"
               id="club_name"
               name="club_name"
               value="{{ $club->club_name ?? old('club_name') }}"
               required
               data-error-message="{{ __('shared.tabs_basic_info_club_name_error') }}"
               placeholder="{{ __('shared.tabs_basic_info_club_name_placeholder') }}">
        <div class="invalid-feedback">{{ __('shared.tabs_basic_info_club_name_error_feedback') }}</div>
    </div>

    @if($context === 'business')
        {{-- Business-dashboard flow: the acting business owner IS the club owner —
             enforced server-side too (StoreClubRequest::prepareForValidation), so no
             picker is needed or shown. --}}
        <input type="hidden" id="owner_user_id" name="owner_user_id" value="{{ auth()->id() }}">
    @else
    <!-- Club Owner -->
    <div class="mb-4">
        <label class="form-label">
            {{ __('shared.tabs_basic_info_club_owner_label') }} <span class="text-destructive">*</span>
        </label>
        <input type="hidden" id="owner_user_id" name="owner_user_id" value="{{ $club->owner_user_id ?? old('owner_user_id') }}" required>

        <div id="ownerDisplay" class="border border-border rounded-lg p-3 mb-2 bg-muted/30">
            @if($isEdit && $club->owner)
                <div class="flex items-center gap-3">
                    @if($club->owner->profile_picture)
                        <img src="{{ asset('storage/' . $club->owner->profile_picture) }}"
                             alt="{{ $club->owner->full_name }}"
                             class="rounded-full w-12 h-12 object-cover">
                    @else
                        <div class="rounded-full bg-primary text-white flex items-center justify-center w-12 h-12 text-xl font-semibold">
                            {{ substr($club->owner->full_name, 0, 1) }}
                        </div>
                    @endif
                    <div class="flex-1">
                        <div class="font-semibold">{{ $club->owner->full_name }}</div>
                        <div class="text-sm text-muted-foreground">
                            <i class="bi bi-envelope me-1"></i>{{ $club->owner->email }}
                            @if($club->owner->mobile)
                                <span class="me-2"><i class="bi bi-phone me-1"></i>{{ $club->owner->mobile_formatted }}</span>
                            @endif
                        </div>
                    </div>
                </div>
            @else
                <div class="text-center text-muted-foreground py-3" id="noOwnerSelected">
                    <i class="bi bi-person-plus text-3xl mb-2 block"></i>
                    <p class="mb-0">{{ __('shared.tabs_basic_info_no_owner') }}</p>
                </div>
            @endif
        </div>

        <button type="button" class="btn btn-outline-primary btn-sm" onclick="showUserPicker()">
            <i class="bi bi-search me-2"></i>{{ __('shared.tabs_basic_info_select_owner') }}
        </button>
        <div class="invalid-feedback block" id="ownerError" style="display: none !important;">
            {{ __('shared.tabs_basic_info_select_owner_error') }}
        </div>
    </div>

    <!-- Internal User Picker Overlay (NOT a separate modal) -->
    <div id="userPickerOverlay" class="user-picker-overlay" style="display: none;">
        <div class="user-picker-panel">
            <div class="user-picker-header flex justify-between items-start p-4 border-b border-border">
                <div>
                    <h5 class="font-bold mb-1">{{ __('shared.tabs_basic_info_select_owner') }}</h5>
                    <p class="text-muted-foreground text-sm mb-0">{{ __('shared.tabs_basic_info_owner_search_hint') }}</p>
                </div>
                <button type="button" class="btn-close" onclick="hideUserPicker()"></button>
            </div>

            <div class="user-picker-body p-4">
                <div class="mb-3">
                    <div class="input-group">
                        <span class="input-group-text bg-white">
                            <i class="bi bi-search"></i>
                        </span>
                        <input type="text"
                               class="form-control"
                               id="userSearchInputInternal"
                               placeholder="{{ __('shared.tabs_basic_info_owner_search_placeholder') }}"
                               autocomplete="off">
                    </div>
                </div>

                <div id="userPickerLoadingInternal" class="text-center py-5" style="display: none;">
                    <div class="spinner-border text-primary" role="status">
                        <span class="sr-only">{{ __('shared.loading') }}</span>
                    </div>
                    <p class="text-muted-foreground mt-2">{{ __('shared.tabs_basic_info_searching_users') }}</p>
                </div>

                <div id="userPickerResultsInternal" style="max-height: 400px; overflow-y: auto;"></div>

                <div id="userPickerNoResultsInternal" class="text-center py-5" style="display: none;">
                    <i class="bi bi-person-x text-4xl text-muted-foreground mb-3 block"></i>
                    <p class="text-muted-foreground mb-0">{{ __('shared.tabs_basic_info_no_users') }}</p>
                    <small class="text-muted-foreground">{{ __('shared.tabs_basic_info_try_different_search') }}</small>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Established Date -->
    <div class="mb-4">
        <label for="established_date" class="form-label">
            {{ __('shared.tabs_basic_info_established_date_label') }}
        </label>
        <input type="date"
               class="form-control"
               id="established_date"
               name="established_date"
               value="{{ $club->established_date ?? old('established_date') }}"
               max="{{ date('Y-m-d') }}">
        <small class="text-muted-foreground">{{ __('shared.tabs_basic_info_established_hint') }}</small>
    </div>

    <!-- Slogan -->
    <div class="mb-4">
        <label for="slogan" class="form-label">
            {{ __('shared.tabs_basic_info_slogan_label') }}
        </label>
        <input type="text"
               class="form-control"
               id="slogan"
               name="slogan"
               value="{{ $club->slogan ?? old('slogan') }}"
               placeholder="{{ __('shared.tabs_basic_info_slogan_placeholder') }}"
               maxlength="100">
        <small class="text-muted-foreground">{{ __('shared.tabs_basic_info_slogan_hint') }}</small>
    </div>

    <!-- Description -->
    <div class="mb-4">
        <label for="description" class="form-label">
            {{ __('shared.tabs_basic_info_description_label') }}
        </label>
        <textarea class="form-control"
                  id="description"
                  name="description"
                  rows="4"
                  placeholder="{{ __('shared.tabs_basic_info_description_placeholder') }}"
                  maxlength="1000">{{ $club->description ?? old('description') }}</textarea>
        <small class="text-muted-foreground">
            <span id="descriptionCount">0</span>{{ __('shared.tabs_basic_info_characters_suffix') }}
        </small>
    </div>

    <!-- Registration requirements & terms — bilingual (EN/AR) rich text -->
    @php
        $reqAr   = data_get($club?->translations, 'registration_requirements.ar', '');
        $termsAr = data_get($club?->translations, 'registration_terms.ar', '');
    @endphp
    <div class="mb-4" x-data="{ lang: 'en' }">
        <div class="flex items-center justify-between mb-3">
            <h6 class="font-semibold mb-0">{{ __('shared.tabs_basic_info_self_reg_content') }}</h6>
            <x-lang-toggle />
        </div>
        <p class="text-muted-foreground text-sm mb-4">{{ __('shared.tabs_basic_info_self_reg_hint') }}</p>

        {{-- Registration requirements --}}
        <div class="mb-4">
            <label class="form-label">{{ __('shared.tabs_basic_info_reg_requirements_label') }}</label>
            <div x-show="lang==='en'">
                <x-rich-text-editor name="registration_requirements" :value="$club->registration_requirements ?? ''"
                    placeholder="What members need to register — e.g. valid CPR/ID, recent photo, proof of payment, minimum age…" />
            </div>
            <div x-show="lang==='ar'" x-cloak>
                <x-rich-text-editor name="translations[registration_requirements][ar]" :value="$reqAr" dir="rtl"
                    placeholder="ما يحتاجه الأعضاء للتسجيل — مثل بطاقة هوية سارية، صورة حديثة، إثبات دفع، الحد الأدنى للعمر…" />
            </div>
            <small class="text-muted-foreground">{{ __('shared.tabs_basic_info_leave_blank_hide') }}</small>
        </div>

        {{-- Registration terms & conditions --}}
        <div class="mb-2">
            <label class="form-label">{{ __('shared.tabs_basic_info_reg_terms_label') }}</label>
            <div x-show="lang==='en'">
                <x-rich-text-editor name="registration_terms" :value="$club->registration_terms ?? ''" min-height="200px"
                    placeholder="Your club's terms &amp; conditions for joining. Leave blank to use the platform default." />
            </div>
            <div x-show="lang==='ar'" x-cloak>
                <x-rich-text-editor name="translations[registration_terms][ar]" :value="$termsAr" dir="rtl" min-height="200px"
                    placeholder="شروط وأحكام النادي للانضمام. اتركه فارغًا لاستخدام الشروط الافتراضية." />
            </div>
            <small class="text-muted-foreground">{{ __('shared.tabs_basic_info_leave_blank_default') }}</small>
        </div>
    </div>

    <!-- Commercial Registration -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
        <div>
            <label for="commercial_reg_number" class="form-label">
                {{ __('shared.tabs_basic_info_commercial_reg_label') }}
            </label>
            <input type="text"
                   class="form-control"
                   id="commercial_reg_number"
                   name="commercial_reg_number"
                   value="{{ $club->commercial_reg_number ?? old('commercial_reg_number') }}"
                   placeholder="{{ __('shared.tabs_basic_info_commercial_reg_placeholder') }}">
            <small class="text-muted-foreground">{{ __('shared.tabs_basic_info_commercial_reg_hint') }}</small>
        </div>
        <div>
            <label for="commercial_reg_file" class="form-label">
                {{ __('shared.tabs_basic_info_reg_document_label') }}
            </label>
            <input type="file"
                   class="form-control"
                   id="commercial_reg_file"
                   name="commercial_reg_file"
                   accept=".pdf,.jpg,.jpeg,.png">
            <small class="text-muted-foreground">{{ __('shared.tabs_basic_info_reg_document_hint') }}</small>
        </div>
    </div>

    <!-- VAT Information -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
        <div>
            <label for="vat_reg_number" class="form-label">
                {{ __('shared.tabs_basic_info_vat_reg_label') }}
            </label>
            <input type="text"
                   class="form-control"
                   id="vat_reg_number"
                   name="vat_reg_number"
                   value="{{ $club->vat_reg_number ?? old('vat_reg_number') }}"
                   placeholder="{{ __('shared.tabs_basic_info_vat_reg_placeholder') }}">
            <small class="text-muted-foreground">{{ __('shared.tabs_basic_info_vat_reg_hint') }}</small>
        </div>
        <div>
            <label for="vat_percentage" class="form-label">
                {{ __('shared.tabs_basic_info_vat_pct_label') }}
            </label>
            <input type="number"
                   class="form-control"
                   id="vat_percentage"
                   name="vat_percentage"
                   value="{{ $club->vat_percentage ?? old('vat_percentage', '0') }}"
                   min="0"
                   max="100"
                   step="0.01"
                   placeholder="{{ __('shared.tabs_basic_info_vat_pct_placeholder') }}">
            <small class="text-muted-foreground">{{ __('shared.tabs_basic_info_vat_pct_hint') }}</small>
        </div>
    </div>

    <!-- VAT Certificate Upload -->
    <div class="mb-4">
        <label for="vat_certificate_file" class="form-label">
            {{ __('shared.tabs_basic_info_vat_cert_label') }}
        </label>
        <input type="file"
               class="form-control"
               id="vat_certificate_file"
               name="vat_certificate_file"
               accept=".pdf,.jpg,.jpeg,.png">
        <small class="text-muted-foreground">{{ __('shared.tabs_basic_info_vat_cert_hint') }}</small>
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
