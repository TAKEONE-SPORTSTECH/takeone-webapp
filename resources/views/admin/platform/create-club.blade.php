@extends('layouts.admin')

@section('page-title', 'Create New Club')
@section('page-subtitle', 'Add a new club to the platform')

@section('content')
    <div class="grid grid-cols-1 lg:grid-cols-12">
        <div class="lg:col-span-8 lg:col-start-3">
            <div class="card border-0 shadow-sm">
            <div class="card-header bg-card">
                <h5 class="mb-0"><i class="bi bi-building mr-2"></i>Club Information</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.platform.clubs.store') }}" method="POST">
                    @csrf

                    <!-- Owner Selection -->
                    <div class="mb-4">
                        <label for="owner_user_id" class="form-label">Club Owner <span class="text-destructive">*</span></label>
                        <select class="form-select @error('owner_user_id') is-invalid @enderror" id="owner_user_id" name="owner_user_id" required>
                            <option value="">Select Owner</option>
                            @foreach($users as $user)
                                <option value="{{ $user['id'] }}"
                                        data-name="{{ $user['full_name'] }}"
                                        data-email="{{ $user['email'] }}"
                                        data-mobile="{{ $user['mobile'] ?? '' }}"
                                        data-picture="{{ $user['profile_picture'] ?? '' }}"
                                        {{ old('owner_user_id') == $user['id'] ? 'selected' : '' }}>
                                    {{ $user['full_name'] }}
                                </option>
                            @endforeach
                        </select>
                        @error('owner_user_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <small class="text-muted-foreground">The user who will manage this club</small>
                    </div>

                    <!-- Basic Information -->
                    <h6 class="border-b pb-2 mb-3">Basic Information</h6>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-3">
                        <div>
                            <label for="club_name" class="form-label">Club Name <span class="text-destructive">*</span></label>
                            <input type="text" class="form-control @error('club_name') is-invalid @enderror" id="club_name" name="club_name" value="{{ old('club_name') }}" required>
                            @error('club_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div>
                            <label for="slug" class="form-label">Club Slug <span class="text-destructive">*</span></label>
                            <input type="text" class="form-control @error('slug') is-invalid @enderror" id="slug" name="slug" value="{{ old('slug') }}" required>
                            @error('slug')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted-foreground">URL-friendly identifier (e.g., bh-taekwondo)</small>
                        </div>
                    </div>

                    <!-- Contact Information -->
                    <h6 class="border-b pb-2 mb-3 mt-4">Contact Information</h6>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-3">
                        <div>
                            <label for="email" class="form-label">Club Email</label>
                            <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email') }}">
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div>
                            <label for="phone_number" class="form-label">Phone Number</label>
                            <x-country-code-dropdown
                                name="phone_code"
                                id="phone_code"
                                :value="old('phone_code', '+973')"
                                :required="false"
                                :error="$errors->first('phone_code')">
                                <input type="text"
                                       class="form-control @error('phone_number') is-invalid @enderror"
                                       name="phone_number"
                                       id="phone_number"
                                       value="{{ old('phone_number') }}"
                                       placeholder="12345678">
                            </x-country-code-dropdown>
                            @error('phone_number')
                                <div class="invalid-feedback block">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-3">
                        <div>
                            <x-currency-dropdown
                                name="currency"
                                id="currency"
                                :value="old('currency', 'BHD')"
                                :required="false"
                                :error="$errors->first('currency')" />
                        </div>
                        <div>
                            <x-timezone-dropdown
                                name="timezone"
                                id="timezone"
                                :value="old('timezone', 'Asia/Bahrain')"
                                :required="false"
                                :error="$errors->first('timezone')" />
                        </div>
                        <div>
                            <x-nationality-dropdown
                                name="country"
                                id="country"
                                label="Country"
                                :value="old('country', 'Bahrain')"
                                :required="false"
                                :error="$errors->first('country')" />
                        </div>
                    </div>

                    <!-- Location -->
                    <h6 class="border-b pb-2 mb-3 mt-4">Location</h6>

                    <div class="mb-3">
                        <label for="address" class="form-label">Address</label>
                        <textarea class="form-control @error('address') is-invalid @enderror" id="address" name="address" rows="2">{{ old('address') }}</textarea>
                        @error('address')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-3">
                        <div>
                            <label for="gps_lat" class="form-label">GPS Latitude</label>
                            <input type="number" step="0.0000001" class="form-control @error('gps_lat') is-invalid @enderror" id="gps_lat" name="gps_lat" value="{{ old('gps_lat') }}" placeholder="26.0667">
                            @error('gps_lat')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div>
                            <label for="gps_long" class="form-label">GPS Longitude</label>
                            <input type="number" step="0.0000001" class="form-control @error('gps_long') is-invalid @enderror" id="gps_long" name="gps_long" value="{{ old('gps_long') }}" placeholder="50.5577">
                            @error('gps_long')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <!-- Branding -->
                    <h6 class="border-b pb-2 mb-3 mt-4">Branding</h6>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-3">
                        <div class="text-center">
                            <label class="form-label block">Club Logo</label>
                            <x-takeone-cropper
                                id="create_club_logo"
                                mode="form"
                                inputName="logo"
                                :width="200"
                                :height="200"
                                :previewWidth="150"
                                :previewHeight="150"
                                shape="square"
                                folder="clubs/logos"
                                filename="logo_{{ time() }}"
                                buttonText="Select Logo"
                                buttonClass="btn btn-outline-success btn-sm"
                            />
                            @error('logo')
                                <div class="text-destructive text-sm mt-1">{{ $message }}</div>
                            @enderror
                            <small class="text-muted-foreground block mt-2">Recommended: Square image, max 2MB</small>
                        </div>
                        <div class="text-center">
                            <label class="form-label block">Cover Image</label>
                            <x-takeone-cropper
                                id="create_club_cover"
                                mode="form"
                                inputName="cover_image"
                                :width="600"
                                :height="200"
                                :previewWidth="250"
                                :previewHeight="83"
                                shape="square"
                                folder="clubs/covers"
                                filename="cover_{{ time() }}"
                                buttonText="Select Cover"
                                buttonClass="btn btn-outline-success btn-sm"
                            />
                            @error('cover_image')
                                <div class="text-destructive text-sm mt-1">{{ $message }}</div>
                            @enderror
                            <small class="text-muted-foreground block mt-2">Recommended: 1200x400px, max 2MB</small>
                        </div>
                    </div>

                    <!-- Actions -->
                        <div class="flex justify-between mt-4 pt-3 border-t">
                            <a href="{{ route('admin.platform.clubs') }}" class="btn btn-secondary">
                                <i class="bi bi-arrow-left mr-2"></i>Cancel
                            </a>
                            <button type="submit" class="btn text-white" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                <i class="bi bi-check-circle mr-2"></i>Create Club
                            </button>
                        </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    /* Custom Select2 Styling for User Dropdown */
    .select2-container--default .select2-results__option {
        padding: 0 !important;
    }

    .user-option {
        display: flex;
        align-items: center;
        padding: 10px 12px;
        gap: 12px;
    }

    .user-option:hover {
        background-color: hsl(var(--primary) / 0.1);
    }

    .user-avatar {
        width: 45px;
        height: 45px;
        border-radius: 50%;
        object-fit: cover;
        flex-shrink: 0;
        border: 2px solid hsl(var(--border));
    }

    .user-avatar-placeholder {
        width: 45px;
        height: 45px;
        border-radius: 50%;
        background: linear-gradient(135deg, hsl(var(--primary)), hsl(var(--primary-hover)));
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 18px;
        flex-shrink: 0;
        border: 2px solid hsl(var(--border));
    }

    .user-info {
        flex: 1;
        min-width: 0;
    }

    .user-name {
        font-weight: 600;
        font-size: 14px;
        color: hsl(var(--foreground));
        margin: 0;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .user-details {
        font-size: 12px;
        color: hsl(var(--muted-foreground));
        margin: 2px 0 0 0;
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .user-detail-item {
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .select2-container--default .select2-selection--single {
        height: 38px;
        border: 1px solid hsl(var(--border));
        border-radius: 0.375rem;
    }

    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 36px;
        padding-left: 12px;
    }

    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 36px;
    }

    .select2-dropdown {
        border: 1px solid hsl(var(--border));
        border-radius: 0.375rem;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    .select2-search--dropdown .select2-search__field {
        border: 1px solid hsl(var(--border));
        border-radius: 0.375rem;
        padding: 8px 12px;
    }

    .select2-search--dropdown .select2-search__field:focus {
        outline: none;
        border-color: hsl(var(--primary));
        box-shadow: 0 0 0 3px hsl(var(--primary) / 0.1);
    }

    .select2-results__option--highlighted {
        background-color: hsl(var(--primary)) !important;
        color: white !important;
    }

    .select2-results__option--highlighted .user-name,
    .select2-results__option--highlighted .user-details,
    .select2-results__option--highlighted .user-detail-item {
        color: white !important;
    }
</style>
@endpush

@push('scripts')
<script>
// Auto-generate slug from club name
document.getElementById('club_name').addEventListener('input', function(e) {
    const slug = e.target.value
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
    document.getElementById('slug').value = slug;
});

// Initialize Select2 for owner dropdown
$(document).ready(function() {
    $('#owner_user_id').select2({
        placeholder: 'Search by name, email, or phone...',
        allowClear: true,
        width: '100%',
        templateResult: formatUser,
        templateSelection: formatUserSelection,
        matcher: customMatcher
    });

    // Custom template for dropdown options
    function formatUser(user) {
        if (!user.id) {
            return user.text;
        }

        var $user = $(user.element);
        var name = $user.data('name');
        var email = $user.data('email');
        var mobile = $user.data('mobile');
        var picture = $user.data('picture');

        var avatarHtml = '';
        if (picture) {
            avatarHtml = '<img src="' + picture + '" class="user-avatar" alt="' + name + '">';
        } else {
            var initial = name ? name.charAt(0).toUpperCase() : '?';
            avatarHtml = '<div class="user-avatar-placeholder">' + initial + '</div>';
        }

        var detailsHtml = '<div class="user-details">';
        if (email) {
            detailsHtml += '<span class="user-detail-item"><i class="bi bi-envelope"></i> ' + email + '</span>';
        }
        if (mobile) {
            detailsHtml += '<span class="user-detail-item"><i class="bi bi-phone"></i> ' + mobile + '</span>';
        }
        detailsHtml += '</div>';

        var $container = $(
            '<div class="user-option">' +
                avatarHtml +
                '<div class="user-info">' +
                    '<div class="user-name">' + name + '</div>' +
                    detailsHtml +
                '</div>' +
            '</div>'
        );

        return $container;
    }

    // Custom template for selected option
    function formatUserSelection(user) {
        if (!user.id) {
            return user.text;
        }

        var $user = $(user.element);
        var name = $user.data('name');
        return name || user.text;
    }

    // Custom matcher for searching by name, email, or phone
    function customMatcher(params, data) {
        // If there are no search terms, return all data
        if ($.trim(params.term) === '') {
            return data;
        }

        // Do not display the item if there is no 'text' property
        if (typeof data.text === 'undefined') {
            return null;
        }

        var $option = $(data.element);
        var name = $option.data('name') || '';
        var email = $option.data('email') || '';
        var mobile = $option.data('mobile') || '';

        var searchTerm = params.term.toLowerCase();

        // Check if the search term matches name, email, or mobile
        if (name.toLowerCase().indexOf(searchTerm) > -1 ||
            email.toLowerCase().indexOf(searchTerm) > -1 ||
            mobile.toLowerCase().indexOf(searchTerm) > -1) {
            return data;
        }

        // Return null if the term should not be displayed
        return null;
    }
});

// Auto-fill currency, timezone, and phone code when country is selected
let countriesData = null;

// Load countries data once
fetch('/data/countries.json')
    .then(response => response.json())
    .then(countries => {
        countriesData = countries;
    })
    .catch(error => console.error('Error loading countries for auto-fill:', error));

// Watch for changes on the country hidden input
const countryInput = document.getElementById('country');
if (countryInput) {
    // Use MutationObserver to detect value changes on hidden input
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.type === 'attributes' && mutation.attributeName === 'value') {
                autoFillFromCountry(countryInput.value);
            }
        });
    });
    observer.observe(countryInput, { attributes: true });

    // Also listen for direct changes
    countryInput.addEventListener('change', function() {
        autoFillFromCountry(this.value);
    });

    // Check periodically for value changes (fallback)
    let lastCountryValue = countryInput.value;
    setInterval(function() {
        if (countryInput.value !== lastCountryValue) {
            lastCountryValue = countryInput.value;
            autoFillFromCountry(countryInput.value);
        }
    }, 500);
}

function autoFillFromCountry(countryName) {
    if (!countriesData || !countryName) return;

    // Find the country in our data
    const country = countriesData.find(c => c.name.toLowerCase() === countryName.toLowerCase());
    if (!country) return;

    // Update currency dropdown
    const currencySelect = document.getElementById('currency');
    if (currencySelect && country.currency) {
        currencySelect.value = country.currency;
        // Trigger change event for Select2
        if (typeof $ !== 'undefined' && $.fn.select2) {
            $(currencySelect).trigger('change');
        }
    }

    // Update timezone dropdown
    const timezoneSelect = document.getElementById('timezone');
    if (timezoneSelect && country.timezone) {
        timezoneSelect.value = country.timezone;
        // Trigger change event for Select2
        if (typeof $ !== 'undefined' && $.fn.select2) {
            $(timezoneSelect).trigger('change');
        }
    }

    // Update phone country code dropdown
    const phoneCodeInput = document.getElementById('phone_code');
    const phoneCodeFlag = document.getElementById('phone_codeSelectedFlag');
    const phoneCodeCountry = document.getElementById('phone_codeSelectedCountry');
    if (phoneCodeInput && country.call_code) {
        phoneCodeInput.value = country.call_code;
        if (phoneCodeFlag) {
            // Convert ISO2 to flag emoji
            const flagEmoji = country.iso2
                .toUpperCase()
                .split('')
                .map(char => String.fromCodePoint(127397 + char.charCodeAt(0)))
                .join('');
            phoneCodeFlag.textContent = flagEmoji;
        }
        if (phoneCodeCountry) {
            phoneCodeCountry.textContent = `${country.name} (${country.call_code})`;
        }
    }

    console.log(`Auto-filled from country: ${countryName} -> Currency: ${country.currency}, Timezone: ${country.timezone}, Phone: ${country.call_code}`);
}
</script>
@endpush
