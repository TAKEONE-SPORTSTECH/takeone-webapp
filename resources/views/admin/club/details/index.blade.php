@extends('layouts.admin-club')

@section('club-admin-content')
<div class="space-y-6" x-data="{ showDeleteClubModal: false, showChangeOwnerModal: false, showCreateOwnerModal: false, showLinkOwnerModal: false }">
    <!-- Page Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-foreground">Club Details</h1>
            <p class="text-sm text-muted-foreground">Manage your club's information and settings</p>
        </div>
        <button type="submit" form="clubDetailsForm" class="btn btn-primary">
            <i class="bi bi-check-lg mr-2"></i>Save All Changes
        </button>
    </div>

    <!-- Success/Error Messages -->
    @if(session('success'))
    <div class="alert alert-success relative" role="alert" x-data="{ show: true }" x-show="show">
        <i class="bi bi-check-circle mr-2"></i>{{ session('success') }}
        <button type="button" class="absolute top-3 right-3 text-green-600 hover:text-green-800" @click="show = false">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>
    @endif

    @if($errors->any())
    <div class="alert alert-danger relative" role="alert" x-data="{ show: true }" x-show="show">
        <i class="bi bi-exclamation-triangle mr-2"></i>
        <ul class="mb-0">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" class="absolute top-3 right-3 text-red-600 hover:text-red-800" @click="show = false">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>
    @endif

    <!-- Tabs Navigation -->
    <div class="border-b">
        <nav class="flex gap-1" role="tablist">
            <button type="button" class="tab-btn active" data-tab="basic" role="tab">
                <i class="bi bi-info-circle mr-2"></i>Basic
            </button>
            <button type="button" class="tab-btn" data-tab="location" role="tab">
                <i class="bi bi-geo-alt mr-2"></i>Location
            </button>
            <button type="button" class="tab-btn" data-tab="branding" role="tab">
                <i class="bi bi-palette mr-2"></i>Branding
            </button>
            <button type="button" class="tab-btn" data-tab="settings" role="tab">
                <i class="bi bi-gear mr-2"></i>Settings
            </button>
        </nav>
    </div>

    <form id="clubDetailsForm" action="{{ route('admin.club.update', $club->slug) }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <!-- Basic Tab -->
        <div class="tab-content active" id="tab-basic">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Left Column - Basic Information -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-building text-primary mr-2"></i>Basic Information
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
                                <i class="bi bi-exclamation-triangle mr-1"></i>
                                This VAT rate applies to NEW transactions only. Past transactions preserve their original VAT rate.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column - Contact Information -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-telephone text-primary mr-2"></i>Contact Information
                        </h5>
                    </div>
                    <div class="card-body space-y-4">
                        <h6 class="text-muted text-uppercase small font-semibold border-bottom pb-2">Club Contact</h6>

                        <div>
                            <label class="form-label">Club Email</label>
                            <input type="email" name="email" class="form-control" value="{{ old('email', $club->email) }}" placeholder="info@yourclub.com">
                        </div>
                        <x-country-dropdown
                            name="country"
                            id="countrySelect"
                            :value="old('country', $club->country)"
                            label="Country" />
                        <div>
                            <label class="form-label">Club Phone</label>
                            <x-country-code-dropdown
                                name="phone_code"
                                id="phoneCode"
                                :value="old('phone_code', $club->phone['code'] ?? '+973')"
                                :error="$errors->first('phone_code')">
                                <input type="text"
                                       class="form-control border-0"
                                       name="phone_number"
                                       value="{{ old('phone_number', $club->phone['number'] ?? '') }}"
                                       placeholder="12345678">
                            </x-country-code-dropdown>
                        </div>
                        <x-currency-dropdown
                            name="currency"
                            id="currencySelect"
                            :value="old('currency', $club->currency)"
                            label="Currency" />
                        <x-timezone-dropdown
                            name="timezone"
                            id="timezoneSelect"
                            :value="old('timezone', $club->timezone)"
                            label="Timezone" />
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
                                <div class="flex items-center gap-2 mt-1">
                                    <code class="flex-1">{{ url('/club/' . strtolower($club->country) . '/' . $club->slug) }}</code>
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

                        <h6 class="text-muted text-uppercase small font-semibold border-bottom pb-2 pt-4">Owner Information</h6>

                        @if($club->owner)
                        <div class="card bg-light">
                            <div class="card-body">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <h6 class="mb-1">{{ $club->owner->full_name }}</h6>
                                        @if($club->owner->email)
                                        <p class="text-muted small mb-1">
                                            <i class="bi bi-envelope mr-1"></i>{{ $club->owner->email }}
                                        </p>
                                        @endif
                                        @if($club->owner->formatted_mobile)
                                        <p class="text-muted small mb-0">
                                            <i class="bi bi-phone mr-1"></i>{{ $club->owner->formatted_mobile }}
                                        </p>
                                        @endif
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" @click="showChangeOwnerModal = true">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        @else
                        <div class="text-center py-4 border-2 border-dashed rounded">
                            <i class="bi bi-person-plus text-muted" style="font-size: 2rem;"></i>
                            <p class="text-muted mt-2 mb-3">No owner assigned yet</p>
                            <div class="flex gap-2 justify-center">
                                <button type="button" class="btn btn-outline-primary btn-sm" @click="showCreateOwnerModal = true">
                                    <i class="bi bi-person-plus mr-1"></i>Create Owner
                                </button>
                                <button type="button" class="btn btn-outline-primary btn-sm" @click="showLinkOwnerModal = true">
                                    <i class="bi bi-link mr-1"></i>Link Owner
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
                        <i class="bi bi-geo-alt text-primary mr-2"></i>Location & GPS
                    </h5>
                </div>
                <div class="card-body space-y-4">
                    <x-location-map
                        id="clubDetailsLoc"
                        :lat="old('gps_lat', $club->gps_lat)"
                        :lng="old('gps_long', $club->gps_long)"
                        :address="old('address', $club->address ?? '')"
                        :defaultLat="26.2285"
                        :defaultLng="50.5860"
                        height="400px"
                    />
                    <div class="flex gap-2 flex-wrap">
                        <button type="button" class="btn btn-outline-primary btn-sm" id="useMyLocationBtn">
                            <i class="bi bi-crosshair mr-1"></i>Use My Location
                        </button>
                        @if($club->gps_lat && $club->gps_long)
                        <a href="https://www.google.com/maps?q={{ $club->gps_lat }},{{ $club->gps_long }}" target="_blank" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-box-arrow-up-right mr-1"></i>View on Google Maps
                        </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Branding Tab -->
        <div class="tab-content" id="tab-branding" style="display: none;">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-palette text-primary mr-2"></i>Branding Assets
                    </h5>
                </div>
                <div class="card-body space-y-5">
                    <!-- Logo -->
                    <div>
                        <label class="form-label font-medium">Club Logo</label>
                        <small class="text-muted block mb-3">Recommended: Square image, at least 512x512px</small>
                        <x-takeone-cropper
                            id="clubDetailLogo"
                            :width="200"
                            :height="200"
                            shape="square"
                            mode="form"
                            inputName="logo"
                            folder="clubs/{{ $club->id }}/branding"
                            :filename="'logo_' . time()"
                            :previewWidth="150"
                            :previewHeight="150"
                            :currentImage="$club->logo ? asset('storage/' . $club->logo) : ''"
                            buttonText="Change Logo"
                            buttonClass="btn btn-outline-secondary"
                        />
                    </div>

                    <hr>

                    <!-- Favicon -->
                    <div>
                        <label class="form-label font-medium">Favicon</label>
                        <small class="text-muted block mb-3">Recommended: Square image, 32x32px or 64x64px</small>
                        <x-takeone-cropper
                            id="clubDetailFavicon"
                            :width="64"
                            :height="64"
                            shape="square"
                            mode="form"
                            inputName="favicon"
                            folder="clubs/{{ $club->id }}/branding"
                            :filename="'favicon_' . time()"
                            :previewWidth="64"
                            :previewHeight="64"
                            :currentImage="$club->favicon ? asset('storage/' . $club->favicon) : ''"
                            buttonText="Change Favicon"
                            buttonClass="btn btn-outline-secondary"
                        />
                    </div>

                    <hr>

                    <!-- Cover Image -->
                    <div>
                        <label class="form-label font-medium">Cover Image</label>
                        <small class="text-muted block mb-3">Recommended: 1920x600px or similar wide aspect ratio</small>
                        <x-takeone-cropper
                            id="clubDetailCover"
                            :width="600"
                            :height="200"
                            shape="square"
                            mode="form"
                            inputName="cover_image"
                            folder="clubs/{{ $club->id }}/branding"
                            :filename="'cover_' . time()"
                            :previewWidth="400"
                            :previewHeight="133"
                            :currentImage="$club->cover_image ? asset('storage/' . $club->cover_image) : ''"
                            buttonText="Change Cover"
                            buttonClass="btn btn-outline-secondary"
                        />
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
                        <i class="bi bi-hash text-primary mr-2"></i>Code Prefixes
                    </h5>
                </div>
                <div class="card-body">
                    @php
                        $settings = $club->settings ?? [];
                    @endphp
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="form-label">Member Code Prefix</label>
                            <input type="text" name="settings[member_code_prefix]" class="form-control text-uppercase" value="{{ old('settings.member_code_prefix', $settings['member_code_prefix'] ?? 'MEM') }}" placeholder="MEM">
                        </div>
                        <div>
                            <label class="form-label">Child Code Prefix</label>
                            <input type="text" name="settings[child_code_prefix]" class="form-control text-uppercase" value="{{ old('settings.child_code_prefix', $settings['child_code_prefix'] ?? 'CHILD') }}" placeholder="CHILD">
                            <small class="text-muted">For children of members becoming members</small>
                        </div>
                        <div>
                            <label class="form-label">Invoice Code Prefix</label>
                            <input type="text" name="settings[invoice_code_prefix]" class="form-control text-uppercase" value="{{ old('settings.invoice_code_prefix', $settings['invoice_code_prefix'] ?? 'INV') }}" placeholder="INV">
                        </div>
                        <div>
                            <label class="form-label">Receipt Code Prefix</label>
                            <input type="text" name="settings[receipt_code_prefix]" class="form-control text-uppercase" value="{{ old('settings.receipt_code_prefix', $settings['receipt_code_prefix'] ?? 'REC') }}" placeholder="REC">
                        </div>
                        <div>
                            <label class="form-label">Expense Code Prefix</label>
                            <input type="text" name="settings[expense_code_prefix]" class="form-control text-uppercase" value="{{ old('settings.expense_code_prefix', $settings['expense_code_prefix'] ?? 'EXP') }}" placeholder="EXP">
                        </div>
                        <div>
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
                        <i class="bi bi-exclamation-triangle mr-2"></i>Danger Zone
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
                    <button type="button" class="btn btn-danger" @click="showDeleteClubModal = true">
                        <i class="bi bi-trash mr-1"></i>Delete This Club
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Delete Club Modal -->
<div x-show="showDeleteClubModal"
     x-cloak
     class="fixed inset-0 z-50 overflow-y-auto"
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0">
    <div class="fixed inset-0 bg-black/50" @click="showDeleteClubModal = false"></div>

    <div class="flex min-h-full items-center justify-center p-4">
        <div class="modal-content border-0 shadow-lg w-full max-w-md relative rounded-lg overflow-hidden" @click.stop>
            <div class="modal-header border-b border-destructive/30 px-6 py-4">
                <h5 class="modal-title text-destructive font-semibold">
                    <i class="bi bi-exclamation-triangle mr-2"></i>Delete Club
                </h5>
                <button type="button" class="text-muted-foreground hover:text-foreground" @click="showDeleteClubModal = false">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <form action="{{ route('admin.club.destroy', $club->slug) }}" method="POST">
                @csrf
                @method('DELETE')
                <div class="modal-body px-6 py-4">
                    <div class="alert alert-danger mb-4">
                        <strong>Warning!</strong> This action cannot be undone.
                    </div>
                    <p class="mb-3">To confirm deletion, please type the club name: <strong>{{ $club->club_name }}</strong></p>
                    <input type="text" class="form-control" id="confirmClubName" placeholder="Type club name to confirm" required>
                </div>
                <div class="modal-footer border-t border-border px-6 py-4 flex justify-end gap-3">
                    <button type="button" class="btn btn-secondary" @click="showDeleteClubModal = false">Cancel</button>
                    <button type="submit" class="btn btn-danger" id="confirmDeleteBtn" disabled>
                        <i class="bi bi-trash mr-1"></i>Delete Permanently
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- QR Code Modal --}}
<div class="modal fade" id="qrModal" tabindex="-1" aria-labelledby="qrModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content text-center">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title w-100" id="qrModalLabel"><i class="bi bi-qr-code mr-2"></i>Club QR Code</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body py-4">
                <img id="qrCodeImage" src="" alt="QR Code" class="mx-auto d-block mb-3" style="width:250px;height:250px;">
                <p class="text-muted text-sm mb-1">Scan to open the club page (no navigation bar)</p>
                <a id="qrCodeLink" href="#" target="_blank" class="text-primary text-xs break-all"></a>
            </div>
            <div class="modal-footer border-0 justify-content-center pt-0">
                <a id="qrDownloadBtn" href="#" download="qr-code.png" class="btn btn-primary">
                    <i class="bi bi-download mr-2"></i>Download QR
                </a>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

{{-- Styles moved to app.css (Phase 6) --}}

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

            // Initialize map when location tab is shown
            if (targetTab === 'location') {
                setTimeout(initDetailsMap, 150);
            }
        });
    });

    // Delete club confirmation
    const confirmInput = document.getElementById('confirmClubName');
    const deleteBtn = document.getElementById('confirmDeleteBtn');
    const clubName = '{{ $club->club_name }}';

    if (confirmInput && deleteBtn) {
        confirmInput.addEventListener('input', function() {
            deleteBtn.disabled = this.value !== clubName;
        });
    }

    // Initialize map when location tab becomes visible
    const DETAILS_MAP_ID = 'clubDetailsLoc';
    let detailsMapInitialized = false;

    function initDetailsMap() {
        if (detailsMapInitialized) {
            window.LocationMap.refresh(DETAILS_MAP_ID);
            return;
        }
        if (window.LocationMap) {
            window.LocationMap.init(DETAILS_MAP_ID, 26.2285, 50.5860);
            detailsMapInitialized = true;
        }
    }

    // Use my location button
    const useLocationBtn = document.getElementById('useMyLocationBtn');
    if (useLocationBtn) {
        useLocationBtn.addEventListener('click', function() {
            if (!navigator.geolocation) {
                alert('Geolocation is not supported by your browser.');
                return;
            }
            const btn = this;
            const originalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm mr-1"></span>Getting location...';

            navigator.geolocation.getCurrentPosition(function(position) {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;
                if (window.LocationMap) {
                    window.LocationMap.setPosition(DETAILS_MAP_ID, lat, lng);
                    const inst = window.LocationMap['_locationMap_' + DETAILS_MAP_ID];
                    if (inst) inst.map.setView([lat, lng], 15);
                }
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }, function(error) {
                alert('Unable to get your location. Please enter coordinates manually.');
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            });
        });
    }

    // Generate QR Code
    const qrBtn = document.getElementById('generateQRBtn');
    if (qrBtn) {
        qrBtn.addEventListener('click', function() {
            const slug = document.querySelector('input[name="slug"]').value;
            if (slug) {
                const url = window.location.origin + '/c/' + slug;
                const qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' + encodeURIComponent(url);
                document.getElementById('qrCodeImage').src = qrUrl;
                document.getElementById('qrCodeLink').href = url;
                document.getElementById('qrCodeLink').textContent = url;
                document.getElementById('qrDownloadBtn').href = qrUrl + '&download=1';
                const modal = new bootstrap.Modal(document.getElementById('qrModal'));
                modal.show();
            } else {
                alert('Please set a slug first.');
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
