@extends('layouts.admin-club')

@section('club-admin-content')
<div class="space-y-6">
    <!-- Page Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-foreground">Club Details</h1>
            <p class="text-sm text-muted-foreground">Manage your club's information and settings</p>
        </div>
        <button type="submit" form="clubDetailsForm" class="btn btn-primary">
            <i class="bi bi-check-lg me-2"></i>Save All Changes
        </button>
    </div>

    <!-- Success/Error Messages -->
    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    @if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle me-2"></i>
        <ul class="mb-0">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    <!-- Tabs Navigation -->
    <div class="border-b">
        <nav class="flex gap-1" role="tablist">
            <button type="button" class="tab-btn active" data-tab="basic" role="tab">
                <i class="bi bi-info-circle me-2"></i>Basic
            </button>
            <button type="button" class="tab-btn" data-tab="location" role="tab">
                <i class="bi bi-geo-alt me-2"></i>Location
            </button>
            <button type="button" class="tab-btn" data-tab="branding" role="tab">
                <i class="bi bi-palette me-2"></i>Branding
            </button>
            <button type="button" class="tab-btn" data-tab="settings" role="tab">
                <i class="bi bi-gear me-2"></i>Settings
            </button>
        </nav>
    </div>

    <form id="clubDetailsForm" action="{{ route('admin.club.update', $club->id) }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <!-- Basic Tab -->
        <div class="tab-content active" id="tab-basic">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Left Column - Basic Information -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-building text-primary me-2"></i>Basic Information
                        </h5>
                    </div>
                    <div class="card-body space-y-4">
                        <div>
                            <label class="form-label">Club Name <span class="text-danger">*</span></label>
                            <input type="text" name="club_name" class="form-control" value="{{ old('club_name', $club->club_name) }}" required>
                        </div>
                        <div>
                            <label class="form-label">Slogan</label>
                            <input type="text" name="slogan" class="form-control" value="{{ old('slogan', $club->slogan) }}" placeholder="A catchy tagline for your club">
                        </div>
                        <div>
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3" placeholder="Describe your club...">{{ old('description', $club->description) }}</textarea>
                        </div>
                        <div>
                            <label class="form-label">First-Time Enrollment Fee ({{ $club->currency ?? 'USD' }})</label>
                            <input type="number" name="enrollment_fee" class="form-control" step="0.01" value="{{ old('enrollment_fee', $club->enrollment_fee) }}" placeholder="0.00">
                            <small class="text-muted">One-time fee charged when a new member joins the club</small>
                        </div>
                        <div>
                            <label class="form-label">Commercial Registration Number (Optional)</label>
                            <input type="text" name="commercial_reg_number" class="form-control" value="{{ old('commercial_reg_number', $club->commercial_reg_number) }}" placeholder="e.g., CR-123456-01">
                            <small class="text-muted">Appears on receipts if provided</small>
                        </div>
                        <div>
                            <label class="form-label">VAT Registration Number (Optional)</label>
                            <input type="text" name="vat_reg_number" class="form-control" value="{{ old('vat_reg_number', $club->vat_reg_number) }}" placeholder="e.g., VAT123456789">
                            <small class="text-muted">Appears on receipts if provided</small>
                        </div>
                        <div>
                            <label class="form-label">VAT Percentage (Optional)</label>
                            <input type="number" name="vat_percentage" class="form-control" step="0.01" value="{{ old('vat_percentage', $club->vat_percentage) }}" placeholder="0.00">
                            <small class="text-muted">Tax percentage for financial transactions (e.g., 5 for 5%, 10 for 10%)</small>
                            <div class="alert alert-warning mt-2 py-2 px-3 small">
                                <i class="bi bi-exclamation-triangle me-1"></i>
                                This VAT rate applies to NEW transactions only. Past transactions preserve their original VAT rate.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column - Contact Information -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-telephone text-primary me-2"></i>Contact Information
                        </h5>
                    </div>
                    <div class="card-body space-y-4">
                        <h6 class="text-muted text-uppercase small fw-semibold border-bottom pb-2">Club Contact</h6>

                        <div>
                            <label class="form-label">Club Email</label>
                            <input type="email" name="email" class="form-control" value="{{ old('email', $club->email) }}" placeholder="info@yourclub.com">
                        </div>
                        <div>
                            <label class="form-label">Country</label>
                            <select name="country" class="form-select" id="countrySelect">
                                <option value="">Select country</option>
                                @php
                                    $countries = [
                                        'BH' => ['name' => 'Bahrain', 'currency' => 'BHD', 'timezone' => 'Asia/Bahrain', 'phone' => '+973'],
                                        'SA' => ['name' => 'Saudi Arabia', 'currency' => 'SAR', 'timezone' => 'Asia/Riyadh', 'phone' => '+966'],
                                        'AE' => ['name' => 'United Arab Emirates', 'currency' => 'AED', 'timezone' => 'Asia/Dubai', 'phone' => '+971'],
                                        'KW' => ['name' => 'Kuwait', 'currency' => 'KWD', 'timezone' => 'Asia/Kuwait', 'phone' => '+965'],
                                        'QA' => ['name' => 'Qatar', 'currency' => 'QAR', 'timezone' => 'Asia/Qatar', 'phone' => '+974'],
                                        'OM' => ['name' => 'Oman', 'currency' => 'OMR', 'timezone' => 'Asia/Muscat', 'phone' => '+968'],
                                        'US' => ['name' => 'United States', 'currency' => 'USD', 'timezone' => 'America/New_York', 'phone' => '+1'],
                                        'GB' => ['name' => 'United Kingdom', 'currency' => 'GBP', 'timezone' => 'Europe/London', 'phone' => '+44'],
                                    ];
                                @endphp
                                @foreach($countries as $code => $data)
                                    <option value="{{ $code }}"
                                            data-currency="{{ $data['currency'] }}"
                                            data-timezone="{{ $data['timezone'] }}"
                                            data-phone="{{ $data['phone'] }}"
                                            {{ old('country', $club->country) == $code ? 'selected' : '' }}>
                                        {{ $data['name'] }}
                                    </option>
                                @endforeach
                            </select>
                            <small class="text-muted">Selecting a country will automatically set currency, timezone, and phone code</small>
                        </div>
                        <div>
                            <label class="form-label">Club Phone</label>
                            <div class="input-group">
                                <input type="text" name="phone_code" class="form-control" style="max-width: 80px;" value="{{ old('phone_code', $club->phone['code'] ?? '+973') }}" placeholder="+973">
                                <input type="text" name="phone_number" class="form-control" value="{{ old('phone_number', $club->phone['number'] ?? '') }}" placeholder="12345678">
                            </div>
                        </div>
                        <div>
                            <label class="form-label">Currency</label>
                            <select name="currency" class="form-select" id="currencySelect">
                                <option value="USD" {{ old('currency', $club->currency) == 'USD' ? 'selected' : '' }}>USD - US Dollar</option>
                                <option value="BHD" {{ old('currency', $club->currency) == 'BHD' ? 'selected' : '' }}>BHD - Bahraini Dinar</option>
                                <option value="SAR" {{ old('currency', $club->currency) == 'SAR' ? 'selected' : '' }}>SAR - Saudi Riyal</option>
                                <option value="AED" {{ old('currency', $club->currency) == 'AED' ? 'selected' : '' }}>AED - UAE Dirham</option>
                                <option value="KWD" {{ old('currency', $club->currency) == 'KWD' ? 'selected' : '' }}>KWD - Kuwaiti Dinar</option>
                                <option value="QAR" {{ old('currency', $club->currency) == 'QAR' ? 'selected' : '' }}>QAR - Qatari Riyal</option>
                                <option value="OMR" {{ old('currency', $club->currency) == 'OMR' ? 'selected' : '' }}>OMR - Omani Rial</option>
                                <option value="GBP" {{ old('currency', $club->currency) == 'GBP' ? 'selected' : '' }}>GBP - British Pound</option>
                                <option value="EUR" {{ old('currency', $club->currency) == 'EUR' ? 'selected' : '' }}>EUR - Euro</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Timezone</label>
                            <select name="timezone" class="form-select" id="timezoneSelect">
                                <option value="UTC" {{ old('timezone', $club->timezone) == 'UTC' ? 'selected' : '' }}>UTC</option>
                                <option value="Asia/Bahrain" {{ old('timezone', $club->timezone) == 'Asia/Bahrain' ? 'selected' : '' }}>Asia/Bahrain</option>
                                <option value="Asia/Riyadh" {{ old('timezone', $club->timezone) == 'Asia/Riyadh' ? 'selected' : '' }}>Asia/Riyadh</option>
                                <option value="Asia/Dubai" {{ old('timezone', $club->timezone) == 'Asia/Dubai' ? 'selected' : '' }}>Asia/Dubai</option>
                                <option value="Asia/Kuwait" {{ old('timezone', $club->timezone) == 'Asia/Kuwait' ? 'selected' : '' }}>Asia/Kuwait</option>
                                <option value="Asia/Qatar" {{ old('timezone', $club->timezone) == 'Asia/Qatar' ? 'selected' : '' }}>Asia/Qatar</option>
                                <option value="America/New_York" {{ old('timezone', $club->timezone) == 'America/New_York' ? 'selected' : '' }}>America/New_York</option>
                                <option value="Europe/London" {{ old('timezone', $club->timezone) == 'Europe/London' ? 'selected' : '' }}>Europe/London</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Club Slug (Unique URL)</label>
                            <div class="input-group">
                                <input type="text" name="slug" class="form-control" value="{{ old('slug', $club->slug) }}" placeholder="e.g., emperor-tkd-academy">
                                <button type="button" class="btn btn-outline-secondary" id="generateQRBtn" title="Generate QR Code">
                                    <i class="bi bi-qr-code"></i>
                                </button>
                            </div>
                            <small class="text-muted">URL-friendly identifier (lowercase, hyphens, no spaces)</small>
                            @if($club->slug && $club->country)
                            <div class="mt-2 p-2 bg-light rounded">
                                <small class="text-muted">Club URL:</small>
                                <div class="d-flex align-items-center gap-2 mt-1">
                                    <code class="flex-grow-1">{{ url('/club/' . strtolower($club->country) . '/' . $club->slug) }}</code>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="copyClubUrl()">
                                        <i class="bi bi-clipboard"></i>
                                    </button>
                                    <a href="{{ url('/club/' . strtolower($club->country) . '/' . $club->slug) }}" target="_blank" class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-box-arrow-up-right"></i>
                                    </a>
                                </div>
                            </div>
                            @endif
                        </div>

                        <h6 class="text-muted text-uppercase small fw-semibold border-bottom pb-2 pt-4">Owner Information</h6>

                        @if($club->owner)
                        <div class="card bg-light">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1">{{ $club->owner->full_name }}</h6>
                                        @if($club->owner->email)
                                        <p class="text-muted small mb-1">
                                            <i class="bi bi-envelope me-1"></i>{{ $club->owner->email }}
                                        </p>
                                        @endif
                                        @if($club->owner->formatted_mobile)
                                        <p class="text-muted small mb-0">
                                            <i class="bi bi-phone me-1"></i>{{ $club->owner->formatted_mobile }}
                                        </p>
                                        @endif
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#changeOwnerModal">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        @else
                        <div class="text-center py-4 border-2 border-dashed rounded">
                            <i class="bi bi-person-plus text-muted" style="font-size: 2rem;"></i>
                            <p class="text-muted mt-2 mb-3">No owner assigned yet</p>
                            <div class="d-flex gap-2 justify-content-center">
                                <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createOwnerModal">
                                    <i class="bi bi-person-plus me-1"></i>Create Owner
                                </button>
                                <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#linkOwnerModal">
                                    <i class="bi bi-link me-1"></i>Link Owner
                                </button>
                            </div>
                        </div>
                        @endif

                        <input type="hidden" name="owner_name" value="{{ old('owner_name', $club->owner_name) }}">
                        <input type="hidden" name="owner_email" value="{{ old('owner_email', $club->owner_email) }}">
                    </div>
                </div>
            </div>
        </div>

        <!-- Location Tab -->
        <div class="tab-content" id="tab-location" style="display: none;">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-geo-alt text-primary me-2"></i>Location & GPS
                    </h5>
                </div>
                <div class="card-body space-y-4">
                    <div>
                        <label class="form-label">Address</label>
                        <input type="text" name="address" class="form-control" value="{{ old('address', $club->address) }}" placeholder="Enter full address">
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label">
                                <i class="bi bi-geo me-1"></i>GPS Latitude
                            </label>
                            <input type="number" name="gps_lat" class="form-control" step="any" value="{{ old('gps_lat', $club->gps_lat) }}" placeholder="e.g., 26.2285">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">
                                <i class="bi bi-geo me-1"></i>GPS Longitude
                            </label>
                            <input type="number" name="gps_long" class="form-control" step="any" value="{{ old('gps_long', $club->gps_long) }}" placeholder="e.g., 50.5860">
                        </div>
                    </div>
                    <div>
                        <label class="form-label">Interactive Map</label>
                        <p class="text-muted small mb-2">Click on the map to set location or drag the marker</p>
                        <div id="locationMap" class="rounded border" style="height: 400px; background: #f0f0f0;">
                            <div class="d-flex align-items-center justify-content-center h-100 text-muted">
                                <div class="text-center">
                                    <i class="bi bi-map" style="font-size: 3rem;"></i>
                                    <p class="mt-2">Map will load here</p>
                                    <small>Enter coordinates above or click "Use My Location"</small>
                                </div>
                            </div>
                        </div>
                        <div class="mt-2">
                            <button type="button" class="btn btn-outline-primary btn-sm" id="useMyLocationBtn">
                                <i class="bi bi-crosshair me-1"></i>Use My Location
                            </button>
                            @if($club->gps_lat && $club->gps_long)
                            <a href="https://www.google.com/maps?q={{ $club->gps_lat }},{{ $club->gps_long }}" target="_blank" class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-box-arrow-up-right me-1"></i>View on Google Maps
                            </a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Branding Tab -->
        <div class="tab-content" id="tab-branding" style="display: none;">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-palette text-primary me-2"></i>Branding Assets
                    </h5>
                </div>
                <div class="card-body space-y-5">
                    <!-- Logo -->
                    <div class="row align-items-start">
                        <div class="col-md-8">
                            <label class="form-label">Logo</label>
                            <input type="file" name="logo" class="form-control" accept="image/*" id="logoInput">
                            <small class="text-muted">Recommended: Square image, at least 512x512px</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Preview</label>
                            <div class="border rounded p-3 text-center" style="min-height: 120px;">
                                @if($club->logo)
                                <img src="{{ asset('storage/' . $club->logo) }}" alt="Logo" id="logoPreview" class="img-fluid" style="max-height: 100px;">
                                @else
                                <div class="text-muted" id="logoPlaceholder">
                                    <i class="bi bi-image" style="font-size: 3rem;"></i>
                                    <p class="small mb-0">No logo uploaded</p>
                                </div>
                                <img src="" alt="Logo" id="logoPreview" class="img-fluid d-none" style="max-height: 100px;">
                                @endif
                            </div>
                        </div>
                    </div>

                    <hr>

                    <!-- Favicon -->
                    <div class="row align-items-start">
                        <div class="col-md-8">
                            <label class="form-label">Favicon</label>
                            <input type="file" name="favicon" class="form-control" accept="image/*" id="faviconInput">
                            <small class="text-muted">Recommended: Square image, 32x32px or 64x64px</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Preview</label>
                            <div class="border rounded p-3 text-center" style="min-height: 80px;">
                                @if($club->favicon)
                                <img src="{{ asset('storage/' . $club->favicon) }}" alt="Favicon" id="faviconPreview" style="width: 32px; height: 32px;">
                                @else
                                <div class="text-muted" id="faviconPlaceholder">
                                    <i class="bi bi-app" style="font-size: 2rem;"></i>
                                    <p class="small mb-0">No favicon</p>
                                </div>
                                <img src="" alt="Favicon" id="faviconPreview" class="d-none" style="width: 32px; height: 32px;">
                                @endif
                            </div>
                        </div>
                    </div>

                    <hr>

                    <!-- Cover Image -->
                    <div class="row align-items-start">
                        <div class="col-md-8">
                            <label class="form-label">Cover Image</label>
                            <input type="file" name="cover_image" class="form-control" accept="image/*" id="coverInput">
                            <small class="text-muted">Recommended: 1920x600px or similar wide aspect ratio</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Preview</label>
                            <div class="border rounded overflow-hidden" style="min-height: 100px;">
                                @if($club->cover_image)
                                <img src="{{ asset('storage/' . $club->cover_image) }}" alt="Cover" id="coverPreview" class="img-fluid w-100" style="max-height: 150px; object-fit: cover;">
                                @else
                                <div class="text-muted text-center py-4" id="coverPlaceholder">
                                    <i class="bi bi-card-image" style="font-size: 2rem;"></i>
                                    <p class="small mb-0">No cover image</p>
                                </div>
                                <img src="" alt="Cover" id="coverPreview" class="img-fluid w-100 d-none" style="max-height: 150px; object-fit: cover;">
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Settings Tab -->
        <div class="tab-content" id="tab-settings" style="display: none;">
            <!-- Code Prefixes -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-hash text-primary me-2"></i>Code Prefixes
                    </h5>
                </div>
                <div class="card-body">
                    @php
                        $settings = $club->settings ?? [];
                    @endphp
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Member Code Prefix</label>
                            <input type="text" name="settings[member_code_prefix]" class="form-control text-uppercase" value="{{ old('settings.member_code_prefix', $settings['member_code_prefix'] ?? 'MEM') }}" placeholder="MEM">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Child Code Prefix</label>
                            <input type="text" name="settings[child_code_prefix]" class="form-control text-uppercase" value="{{ old('settings.child_code_prefix', $settings['child_code_prefix'] ?? 'CHILD') }}" placeholder="CHILD">
                            <small class="text-muted">For children of members becoming members</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Invoice Code Prefix</label>
                            <input type="text" name="settings[invoice_code_prefix]" class="form-control text-uppercase" value="{{ old('settings.invoice_code_prefix', $settings['invoice_code_prefix'] ?? 'INV') }}" placeholder="INV">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Receipt Code Prefix</label>
                            <input type="text" name="settings[receipt_code_prefix]" class="form-control text-uppercase" value="{{ old('settings.receipt_code_prefix', $settings['receipt_code_prefix'] ?? 'REC') }}" placeholder="REC">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Expense Code Prefix</label>
                            <input type="text" name="settings[expense_code_prefix]" class="form-control text-uppercase" value="{{ old('settings.expense_code_prefix', $settings['expense_code_prefix'] ?? 'EXP') }}" placeholder="EXP">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Specialist Code Prefix</label>
                            <input type="text" name="settings[specialist_code_prefix]" class="form-control text-uppercase" value="{{ old('settings.specialist_code_prefix', $settings['specialist_code_prefix'] ?? 'SPEC') }}" placeholder="SPEC">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Danger Zone -->
            <div class="card border-danger">
                <div class="card-header bg-danger bg-opacity-10">
                    <h5 class="card-title mb-0 text-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i>Danger Zone
                    </h5>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3">Once you delete a club, there is no going back. This action will permanently delete:</p>
                    <ul class="text-muted small mb-4">
                        <li>All club information and settings</li>
                        <li>All facilities, instructors, and activities</li>
                        <li>All packages, memberships, and member data</li>
                        <li>All uploaded images and files from storage</li>
                        <li>All reviews, statistics, and historical data</li>
                    </ul>
                    <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteClubModal">
                        <i class="bi bi-trash me-1"></i>Delete This Club
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Delete Club Modal -->
<div class="modal fade" id="deleteClubModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header border-danger">
                <h5 class="modal-title text-danger">
                    <i class="bi bi-exclamation-triangle me-2"></i>Delete Club
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('admin.club.destroy', $club->id) }}" method="POST">
                @csrf
                @method('DELETE')
                <div class="modal-body">
                    <div class="alert alert-danger">
                        <strong>Warning!</strong> This action cannot be undone.
                    </div>
                    <p>To confirm deletion, please type the club name: <strong>{{ $club->club_name }}</strong></p>
                    <input type="text" class="form-control" id="confirmClubName" placeholder="Type club name to confirm" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger" id="confirmDeleteBtn" disabled>
                        <i class="bi bi-trash me-1"></i>Delete Permanently
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .tab-btn {
        padding: 0.75rem 1.5rem;
        border: none;
        background: transparent;
        color: var(--bs-gray-600);
        font-weight: 500;
        border-bottom: 2px solid transparent;
        transition: all 0.2s;
    }
    .tab-btn:hover {
        color: var(--bs-primary);
        border-bottom-color: rgba(var(--bs-primary-rgb), 0.3);
    }
    .tab-btn.active {
        color: var(--bs-primary);
        border-bottom-color: var(--bs-primary);
    }
    .space-y-4 > * + * {
        margin-top: 1rem;
    }
    .space-y-5 > * + * {
        margin-top: 1.25rem;
    }
    .space-y-6 > * + * {
        margin-top: 1.5rem;
    }
    .border-2 {
        border-width: 2px !important;
    }
    .border-dashed {
        border-style: dashed !important;
    }
</style>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Tab switching
    const tabButtons = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');

    tabButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const targetTab = this.dataset.tab;

            // Update button states
            tabButtons.forEach(b => b.classList.remove('active'));
            this.classList.add('active');

            // Show/hide content
            tabContents.forEach(content => {
                if (content.id === 'tab-' + targetTab) {
                    content.style.display = 'block';
                } else {
                    content.style.display = 'none';
                }
            });
        });
    });

    // Country selector auto-fill
    const countrySelect = document.getElementById('countrySelect');
    if (countrySelect) {
        countrySelect.addEventListener('change', function() {
            const option = this.options[this.selectedIndex];
            if (option.value) {
                document.getElementById('currencySelect').value = option.dataset.currency || 'USD';
                document.getElementById('timezoneSelect').value = option.dataset.timezone || 'UTC';
                document.querySelector('input[name="phone_code"]').value = option.dataset.phone || '+1';
            }
        });
    }

    // Delete club confirmation
    const confirmInput = document.getElementById('confirmClubName');
    const deleteBtn = document.getElementById('confirmDeleteBtn');
    const clubName = '{{ $club->club_name }}';

    if (confirmInput && deleteBtn) {
        confirmInput.addEventListener('input', function() {
            deleteBtn.disabled = this.value !== clubName;
        });
    }

    // Image previews
    function setupImagePreview(inputId, previewId, placeholderId) {
        const input = document.getElementById(inputId);
        const preview = document.getElementById(previewId);
        const placeholder = document.getElementById(placeholderId);

        if (input && preview) {
            input.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        preview.src = e.target.result;
                        preview.classList.remove('d-none');
                        if (placeholder) placeholder.classList.add('d-none');
                    };
                    reader.readAsDataURL(this.files[0]);
                }
            });
        }
    }

    setupImagePreview('logoInput', 'logoPreview', 'logoPlaceholder');
    setupImagePreview('faviconInput', 'faviconPreview', 'faviconPlaceholder');
    setupImagePreview('coverInput', 'coverPreview', 'coverPlaceholder');

    // Use my location button
    const useLocationBtn = document.getElementById('useMyLocationBtn');
    if (useLocationBtn) {
        useLocationBtn.addEventListener('click', function() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(function(position) {
                    document.querySelector('input[name="gps_lat"]').value = position.coords.latitude.toFixed(7);
                    document.querySelector('input[name="gps_long"]').value = position.coords.longitude.toFixed(7);
                }, function(error) {
                    alert('Unable to get your location. Please enter coordinates manually.');
                });
            } else {
                alert('Geolocation is not supported by your browser.');
            }
        });
    }

    // Generate QR Code
    const qrBtn = document.getElementById('generateQRBtn');
    if (qrBtn) {
        qrBtn.addEventListener('click', function() {
            const slug = document.querySelector('input[name="slug"]').value;
            const country = document.querySelector('select[name="country"]').value;
            if (slug && country) {
                const url = window.location.origin + '/club/' + country.toLowerCase() + '/' + slug;
                window.open('https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' + encodeURIComponent(url), '_blank');
            } else {
                alert('Please set a country and slug first.');
            }
        });
    }
});

// Copy club URL
function copyClubUrl() {
    const url = '{{ $club->slug && $club->country ? url("/club/" . strtolower($club->country) . "/" . $club->slug) : "" }}';
    if (url) {
        navigator.clipboard.writeText(url).then(function() {
            alert('URL copied to clipboard!');
        });
    }
}
</script>
@endpush
@endsection
