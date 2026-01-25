@extends('layouts.admin')

@section('page-title', 'Create New Club')
@section('page-subtitle', 'Add a new club to the platform')

@section('content')
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-building me-2"></i>Club Information</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.platform.clubs.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf

                    <!-- Owner Selection -->
                    <div class="mb-4">
                        <label for="owner_user_id" class="form-label">Club Owner <span class="text-danger">*</span></label>
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
                        <small class="text-muted">The user who will manage this club</small>
                    </div>

                    <!-- Basic Information -->
                    <h6 class="border-bottom pb-2 mb-3">Basic Information</h6>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="club_name" class="form-label">Club Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('club_name') is-invalid @enderror" id="club_name" name="club_name" value="{{ old('club_name') }}" required>
                            @error('club_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="slug" class="form-label">Club Slug <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('slug') is-invalid @enderror" id="slug" name="slug" value="{{ old('slug') }}" required>
                            @error('slug')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">URL-friendly identifier (e.g., bh-taekwondo)</small>
                        </div>
                    </div>

                    <!-- Contact Information -->
                    <h6 class="border-bottom pb-2 mb-3 mt-4">Contact Information</h6>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="email" class="form-label">Club Email</label>
                            <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email') }}">
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="phone_number" class="form-label">Phone Number</label>
                            <div class="input-group">
                                <select class="form-select" name="phone_code" style="max-width: 120px;">
                                    <option value="+973" {{ old('phone_code') == '+973' ? 'selected' : '' }}>+973 (BH)</option>
                                    <option value="+966" {{ old('phone_code') == '+966' ? 'selected' : '' }}>+966 (SA)</option>
                                    <option value="+971" {{ old('phone_code') == '+971' ? 'selected' : '' }}>+971 (AE)</option>
                                    <option value="+965" {{ old('phone_code') == '+965' ? 'selected' : '' }}>+965 (KW)</option>
                                </select>
                                <input type="text" class="form-control" name="phone_number" value="{{ old('phone_number') }}" placeholder="12345678">
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="currency" class="form-label">Currency</label>
                            <select class="form-select" id="currency" name="currency">
                                <option value="BHD" {{ old('currency', 'BHD') == 'BHD' ? 'selected' : '' }}>BHD - Bahrain Dinar</option>
                                <option value="SAR" {{ old('currency') == 'SAR' ? 'selected' : '' }}>SAR - Saudi Riyal</option>
                                <option value="AED" {{ old('currency') == 'AED' ? 'selected' : '' }}>AED - UAE Dirham</option>
                                <option value="KWD" {{ old('currency') == 'KWD' ? 'selected' : '' }}>KWD - Kuwaiti Dinar</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="timezone" class="form-label">Timezone</label>
                            <select class="form-select" id="timezone" name="timezone">
                                <option value="Asia/Bahrain" {{ old('timezone', 'Asia/Bahrain') == 'Asia/Bahrain' ? 'selected' : '' }}>Asia/Bahrain</option>
                                <option value="Asia/Riyadh" {{ old('timezone') == 'Asia/Riyadh' ? 'selected' : '' }}>Asia/Riyadh</option>
                                <option value="Asia/Dubai" {{ old('timezone') == 'Asia/Dubai' ? 'selected' : '' }}>Asia/Dubai</option>
                                <option value="Asia/Kuwait" {{ old('timezone') == 'Asia/Kuwait' ? 'selected' : '' }}>Asia/Kuwait</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="country" class="form-label">Country</label>
                            <select class="form-select" id="country" name="country">
                                <option value="BH" {{ old('country', 'BH') == 'BH' ? 'selected' : '' }}>Bahrain</option>
                                <option value="SA" {{ old('country') == 'SA' ? 'selected' : '' }}>Saudi Arabia</option>
                                <option value="AE" {{ old('country') == 'AE' ? 'selected' : '' }}>United Arab Emirates</option>
                                <option value="KW" {{ old('country') == 'KW' ? 'selected' : '' }}>Kuwait</option>
                            </select>
                        </div>
                    </div>

                    <!-- Location -->
                    <h6 class="border-bottom pb-2 mb-3 mt-4">Location</h6>

                    <div class="mb-3">
                        <label for="address" class="form-label">Address</label>
                        <textarea class="form-control @error('address') is-invalid @enderror" id="address" name="address" rows="2">{{ old('address') }}</textarea>
                        @error('address')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="gps_lat" class="form-label">GPS Latitude</label>
                            <input type="number" step="0.0000001" class="form-control @error('gps_lat') is-invalid @enderror" id="gps_lat" name="gps_lat" value="{{ old('gps_lat') }}" placeholder="26.0667">
                            @error('gps_lat')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="gps_long" class="form-label">GPS Longitude</label>
                            <input type="number" step="0.0000001" class="form-control @error('gps_long') is-invalid @enderror" id="gps_long" name="gps_long" value="{{ old('gps_long') }}" placeholder="50.5577">
                            @error('gps_long')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <!-- Branding -->
                    <h6 class="border-bottom pb-2 mb-3 mt-4">Branding</h6>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="logo" class="form-label">Club Logo</label>
                            <input type="file" class="form-control @error('logo') is-invalid @enderror" id="logo" name="logo" accept="image/*">
                            @error('logo')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">Recommended: Square image, max 2MB</small>
                        </div>
                        <div class="col-md-6">
                            <label for="cover_image" class="form-label">Cover Image</label>
                            <input type="file" class="form-control @error('cover_image') is-invalid @enderror" id="cover_image" name="cover_image" accept="image/*">
                            @error('cover_image')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">Recommended: 1200x400px, max 2MB</small>
                        </div>
                    </div>

                    <!-- Actions -->
                        <div class="d-flex justify-content-between mt-4 pt-3 border-top">
                            <a href="{{ route('admin.platform.clubs') }}" class="btn btn-secondary">
                                <i class="bi bi-arrow-left me-2"></i>Cancel
                            </a>
                            <button type="submit" class="btn text-white" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                <i class="bi bi-check-circle me-2"></i>Create Club
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
</script>
@endpush
