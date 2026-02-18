@extends('layouts.admin')

@section('page-title', 'Edit Club')
@section('page-subtitle', 'Update club information')

@section('content')
    <div class="grid grid-cols-1 lg:grid-cols-12">
        <div class="lg:col-span-8 lg:col-start-3">
            <div class="card border-0 shadow-sm">
            <div class="card-header bg-card flex justify-between items-center">
                <h5 class="mb-0"><i class="bi bi-building mr-2"></i>{{ $club->club_name }}</h5>
                <span class="badge bg-secondary">{{ $club->slug }}</span>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.platform.clubs.update', $club) }}" method="POST">
                    @csrf
                    @method('PUT')

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
                                        {{ (old('owner_user_id', $club->owner_user_id) == $user['id']) ? 'selected' : '' }}>
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
                            <input type="text" class="form-control @error('club_name') is-invalid @enderror" id="club_name" name="club_name" value="{{ old('club_name', $club->club_name) }}" required>
                            @error('club_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div>
                            <label for="slug" class="form-label">Club Slug <span class="text-destructive">*</span></label>
                            <input type="text" class="form-control @error('slug') is-invalid @enderror" id="slug" name="slug" value="{{ old('slug', $club->slug) }}" required>
                            @error('slug')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted-foreground">URL-friendly identifier</small>
                        </div>
                    </div>

                    <!-- Contact Information -->
                    <h6 class="border-b pb-2 mb-3 mt-4">Contact Information</h6>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-3">
                        <div>
                            <label for="email" class="form-label">Club Email</label>
                            <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email', $club->email) }}">
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div>
                            <label for="phone_number" class="form-label">Phone Number</label>
                            <div class="input-group">
                                <select class="form-select max-w-[120px]" name="phone_code">
                                    <option value="+973" {{ old('phone_code', $club->phone['code'] ?? '+973') == '+973' ? 'selected' : '' }}>+973 (BH)</option>
                                    <option value="+966" {{ old('phone_code', $club->phone['code'] ?? '') == '+966' ? 'selected' : '' }}>+966 (SA)</option>
                                    <option value="+971" {{ old('phone_code', $club->phone['code'] ?? '') == '+971' ? 'selected' : '' }}>+971 (AE)</option>
                                    <option value="+965" {{ old('phone_code', $club->phone['code'] ?? '') == '+965' ? 'selected' : '' }}>+965 (KW)</option>
                                </select>
                                <input type="text" class="form-control" name="phone_number" value="{{ old('phone_number', $club->phone['number'] ?? '') }}" placeholder="12345678">
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-3">
                        <div>
                            <label for="currency" class="form-label">Currency</label>
                            <select class="form-select" id="currency" name="currency">
                                <option value="BHD" {{ old('currency', $club->currency ?? 'BHD') == 'BHD' ? 'selected' : '' }}>BHD - Bahrain Dinar</option>
                                <option value="SAR" {{ old('currency', $club->currency) == 'SAR' ? 'selected' : '' }}>SAR - Saudi Riyal</option>
                                <option value="AED" {{ old('currency', $club->currency) == 'AED' ? 'selected' : '' }}>AED - UAE Dirham</option>
                                <option value="KWD" {{ old('currency', $club->currency) == 'KWD' ? 'selected' : '' }}>KWD - Kuwaiti Dinar</option>
                            </select>
                        </div>
                        <div>
                            <label for="timezone" class="form-label">Timezone</label>
                            <select class="form-select" id="timezone" name="timezone">
                                <option value="Asia/Bahrain" {{ old('timezone', $club->timezone ?? 'Asia/Bahrain') == 'Asia/Bahrain' ? 'selected' : '' }}>Asia/Bahrain</option>
                                <option value="Asia/Riyadh" {{ old('timezone', $club->timezone) == 'Asia/Riyadh' ? 'selected' : '' }}>Asia/Riyadh</option>
                                <option value="Asia/Dubai" {{ old('timezone', $club->timezone) == 'Asia/Dubai' ? 'selected' : '' }}>Asia/Dubai</option>
                                <option value="Asia/Kuwait" {{ old('timezone', $club->timezone) == 'Asia/Kuwait' ? 'selected' : '' }}>Asia/Kuwait</option>
                            </select>
                        </div>
                        <div>
                            <label for="country" class="form-label">Country</label>
                            <select class="form-select" id="country" name="country">
                                <option value="BH" {{ old('country', $club->country ?? 'BH') == 'BH' ? 'selected' : '' }}>Bahrain</option>
                                <option value="SA" {{ old('country', $club->country) == 'SA' ? 'selected' : '' }}>Saudi Arabia</option>
                                <option value="AE" {{ old('country', $club->country) == 'AE' ? 'selected' : '' }}>United Arab Emirates</option>
                                <option value="KW" {{ old('country', $club->country) == 'KW' ? 'selected' : '' }}>Kuwait</option>
                            </select>
                        </div>
                    </div>

                    <!-- Location -->
                    <h6 class="border-b pb-2 mb-3 mt-4">Location</h6>

                    <div class="mb-3">
                        <label for="address" class="form-label">Address</label>
                        <textarea class="form-control @error('address') is-invalid @enderror" id="address" name="address" rows="2">{{ old('address', $club->address) }}</textarea>
                        @error('address')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-3">
                        <div>
                            <label for="gps_lat" class="form-label">GPS Latitude</label>
                            <input type="number" step="0.0000001" class="form-control @error('gps_lat') is-invalid @enderror" id="gps_lat" name="gps_lat" value="{{ old('gps_lat', $club->gps_lat) }}">
                            @error('gps_lat')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div>
                            <label for="gps_long" class="form-label">GPS Longitude</label>
                            <input type="number" step="0.0000001" class="form-control @error('gps_long') is-invalid @enderror" id="gps_long" name="gps_long" value="{{ old('gps_long', $club->gps_long) }}">
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
                            <x-image-upload
                                id="club_logo"
                                name="logo"
                                :width="200"
                                :height="200"
                                shape="square"
                                folder="clubs/{{ $club->id }}/logos"
                                filename="logo_{{ $club->id }}"
                                uploadUrl="{{ route('admin.platform.clubs.upload-logo', $club) }}"
                                :currentImage="$club->logo ? asset('storage/' . $club->logo) : ''"
                                placeholder="No logo"
                                placeholderIcon="bi-building"
                                buttonText="Change Logo"
                                buttonClass="btn btn-success btn-sm"
                                :rounded="false"
                            />
                            <small class="text-muted-foreground block mt-2">Recommended: Square image, max 2MB</small>
                        </div>
                        <div class="text-center">
                            <label class="form-label block">Cover Image</label>
                            <x-image-upload
                                id="club_cover"
                                name="cover_image"
                                :width="600"
                                :height="200"
                                shape="square"
                                folder="clubs/{{ $club->id }}/covers"
                                filename="cover_{{ $club->id }}"
                                uploadUrl="{{ route('admin.platform.clubs.upload-cover', $club) }}"
                                :currentImage="$club->cover_image ? asset('storage/' . $club->cover_image) : ''"
                                placeholder="No cover"
                                placeholderIcon="bi-image"
                                buttonText="Change Cover"
                                buttonClass="btn btn-success btn-sm"
                                :rounded="false"
                            />
                            <small class="text-muted-foreground block mt-2">Recommended: 1200x400px, max 2MB</small>
                        </div>
                    </div>

                    <!-- Actions -->
                        <div class="flex justify-between mt-4 pt-3 border-t">
                            <div>
                                <a href="{{ route('admin.platform.clubs') }}" class="btn btn-secondary">
                                    <i class="bi bi-arrow-left mr-2"></i>Cancel
                                </a>
                                <button type="button" class="btn btn-danger ml-2" data-bs-toggle="modal" data-bs-target="#deleteModal">
                                    <i class="bi bi-trash mr-2"></i>Delete Club
                                </button>
                            </div>
                            <button type="submit" class="btn text-white" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                <i class="bi bi-check-circle mr-2"></i>Update Club
                            </button>
                        </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-destructive text-white">
                <h5 class="modal-title" id="deleteModalLabel">
                    <i class="bi bi-exclamation-triangle mr-2"></i>Confirm Delete
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">Are you sure you want to delete <strong>{{ $club->club_name }}</strong>?</p>
                <p class="text-destructive mb-0 mt-2"><small><i class="bi bi-info-circle mr-1"></i>This action cannot be undone.</small></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form action="{{ route('admin.platform.clubs.destroy', $club) }}" method="POST" class="inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash mr-2"></i>Yes, Delete Club
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- Styles moved to app.css (Phase 6) --}}

@push('scripts')
<script>
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
