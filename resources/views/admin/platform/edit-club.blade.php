@extends('layouts.admin')

@section('page-title', 'Edit Club')
@section('page-subtitle', 'Update club information')

@section('content')
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-building me-2"></i>{{ $club->club_name }}</h5>
                <span class="badge bg-secondary">{{ $club->slug }}</span>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.platform.clubs.update', $club) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')

                    <!-- Owner Selection -->
                    <div class="mb-4">
                        <label for="owner_user_id" class="form-label">Club Owner <span class="text-danger">*</span></label>
                        <select class="form-select @error('owner_user_id') is-invalid @enderror" id="owner_user_id" name="owner_user_id" required>
                            <option value="">Select Owner</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}" {{ (old('owner_user_id', $club->owner_user_id) == $user->id) ? 'selected' : '' }}>
                                    {{ $user->full_name }} ({{ $user->email }})
                                </option>
                            @endforeach
                        </select>
                        @error('owner_user_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Basic Information -->
                    <h6 class="border-bottom pb-2 mb-3">Basic Information</h6>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="club_name" class="form-label">Club Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('club_name') is-invalid @enderror" id="club_name" name="club_name" value="{{ old('club_name', $club->club_name) }}" required>
                            @error('club_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="slug" class="form-label">Club Slug <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('slug') is-invalid @enderror" id="slug" name="slug" value="{{ old('slug', $club->slug) }}" required>
                            @error('slug')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">URL-friendly identifier</small>
                        </div>
                    </div>

                    <!-- Contact Information -->
                    <h6 class="border-bottom pb-2 mb-3 mt-4">Contact Information</h6>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="email" class="form-label">Club Email</label>
                            <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email', $club->email) }}">
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="phone_number" class="form-label">Phone Number</label>
                            <div class="input-group">
                                <select class="form-select" name="phone_code" style="max-width: 120px;">
                                    <option value="+973" {{ old('phone_code', $club->phone['code'] ?? '+973') == '+973' ? 'selected' : '' }}>+973 (BH)</option>
                                    <option value="+966" {{ old('phone_code', $club->phone['code'] ?? '') == '+966' ? 'selected' : '' }}>+966 (SA)</option>
                                    <option value="+971" {{ old('phone_code', $club->phone['code'] ?? '') == '+971' ? 'selected' : '' }}>+971 (AE)</option>
                                    <option value="+965" {{ old('phone_code', $club->phone['code'] ?? '') == '+965' ? 'selected' : '' }}>+965 (KW)</option>
                                </select>
                                <input type="text" class="form-control" name="phone_number" value="{{ old('phone_number', $club->phone['number'] ?? '') }}" placeholder="12345678">
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="currency" class="form-label">Currency</label>
                            <select class="form-select" id="currency" name="currency">
                                <option value="BHD" {{ old('currency', $club->currency ?? 'BHD') == 'BHD' ? 'selected' : '' }}>BHD - Bahrain Dinar</option>
                                <option value="SAR" {{ old('currency', $club->currency) == 'SAR' ? 'selected' : '' }}>SAR - Saudi Riyal</option>
                                <option value="AED" {{ old('currency', $club->currency) == 'AED' ? 'selected' : '' }}>AED - UAE Dirham</option>
                                <option value="KWD" {{ old('currency', $club->currency) == 'KWD' ? 'selected' : '' }}>KWD - Kuwaiti Dinar</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="timezone" class="form-label">Timezone</label>
                            <select class="form-select" id="timezone" name="timezone">
                                <option value="Asia/Bahrain" {{ old('timezone', $club->timezone ?? 'Asia/Bahrain') == 'Asia/Bahrain' ? 'selected' : '' }}>Asia/Bahrain</option>
                                <option value="Asia/Riyadh" {{ old('timezone', $club->timezone) == 'Asia/Riyadh' ? 'selected' : '' }}>Asia/Riyadh</option>
                                <option value="Asia/Dubai" {{ old('timezone', $club->timezone) == 'Asia/Dubai' ? 'selected' : '' }}>Asia/Dubai</option>
                                <option value="Asia/Kuwait" {{ old('timezone', $club->timezone) == 'Asia/Kuwait' ? 'selected' : '' }}>Asia/Kuwait</option>
                            </select>
                        </div>
                        <div class="col-md-4">
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
                    <h6 class="border-bottom pb-2 mb-3 mt-4">Location</h6>

                    <div class="mb-3">
                        <label for="address" class="form-label">Address</label>
                        <textarea class="form-control @error('address') is-invalid @enderror" id="address" name="address" rows="2">{{ old('address', $club->address) }}</textarea>
                        @error('address')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="gps_lat" class="form-label">GPS Latitude</label>
                            <input type="number" step="0.0000001" class="form-control @error('gps_lat') is-invalid @enderror" id="gps_lat" name="gps_lat" value="{{ old('gps_lat', $club->gps_lat) }}">
                            @error('gps_lat')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="gps_long" class="form-label">GPS Longitude</label>
                            <input type="number" step="0.0000001" class="form-control @error('gps_long') is-invalid @enderror" id="gps_long" name="gps_long" value="{{ old('gps_long', $club->gps_long) }}">
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
                            @if($club->logo)
                                <div class="mb-2">
                                    <img src="{{ asset('storage/' . $club->logo) }}" alt="Current Logo" class="img-thumbnail" style="max-width: 100px;">
                                    <small class="d-block text-muted">Current logo</small>
                                </div>
                            @endif
                            <input type="file" class="form-control @error('logo') is-invalid @enderror" id="logo" name="logo" accept="image/*">
                            @error('logo')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">Leave empty to keep current logo</small>
                        </div>
                        <div class="col-md-6">
                            <label for="cover_image" class="form-label">Cover Image</label>
                            @if($club->cover_image)
                                <div class="mb-2">
                                    <img src="{{ asset('storage/' . $club->cover_image) }}" alt="Current Cover" class="img-thumbnail" style="max-width: 200px;">
                                    <small class="d-block text-muted">Current cover image</small>
                                </div>
                            @endif
                            <input type="file" class="form-control @error('cover_image') is-invalid @enderror" id="cover_image" name="cover_image" accept="image/*">
                            @error('cover_image')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">Leave empty to keep current cover</small>
                        </div>
                    </div>

                    <!-- Actions -->
                        <div class="d-flex justify-content-between mt-4 pt-3 border-top">
                            <a href="{{ route('admin.platform.clubs') }}" class="btn btn-secondary">
                                <i class="bi bi-arrow-left me-2"></i>Cancel
                            </a>
                            <button type="submit" class="btn text-white" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                <i class="bi bi-check-circle me-2"></i>Update Club
                            </button>
                        </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
