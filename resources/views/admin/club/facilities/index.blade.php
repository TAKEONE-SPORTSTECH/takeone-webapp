@extends('layouts.admin-club')

@section('club-admin-content')
<div class="space-y-8" id="facilitiesContainer" x-data="{ showAddFacilityModal: false, showEditFacilityModal: false, editingFacility: null }" @open-edit-facility.window="showEditFacilityModal = true">
    <!-- Header -->
    <div class="flex flex-wrap gap-3 justify-between items-center pb-6 border-b border-gray-200">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Facilities Management</h2>
            <p class="text-gray-500 mt-1">Manage your club's facilities, locations, and availability</p>
        </div>
        <button class="inline-flex items-center gap-2 bg-primary text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-primary/90 transition-all shadow-md hover:shadow-lg"
                @click="showAddFacilityModal = true">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="5" x2="12" y2="19"></line>
                <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            Add Facility
        </button>
    </div>

    @if(isset($facilities) && count($facilities) > 0)
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach($facilities as $facility)
        <div class="group relative bg-white rounded-lg shadow hover:shadow-lg transition-all duration-300 overflow-hidden border border-gray-100">
            @php $coverImage = ($facility->images && count($facility->images)) ? $facility->images[0] : $facility->photo; @endphp
            <!-- Image Section -->
            <div class="relative w-full h-40 bg-gradient-to-br from-gray-100 to-gray-50 overflow-hidden">
                @if($coverImage)
                <img src="{{ asset('storage/' . $coverImage) }}"
                     alt="{{ $facility->name }}"
                     class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-700">
                <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-black/20 to-transparent"></div>
                @if($facility->images && count($facility->images) > 1)
                <span class="absolute bottom-2 right-2 bg-black/60 text-white text-xs px-2 py-0.5 rounded-full backdrop-blur-sm">
                    <i class="bi bi-images mr-1"></i>{{ count($facility->images) }}
                </span>
                @endif
                @else
                <div class="w-full h-full flex items-center justify-center">
                    <div class="text-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-primary/30 mx-auto mb-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                            <circle cx="12" cy="10" r="3"></circle>
                        </svg>
                        <p class="text-xs text-gray-400">No image</p>
                    </div>
                </div>
                @endif

                <!-- Status Badges -->
                <div class="absolute top-3 left-3 flex flex-col gap-1.5">
                    @if($facility->is_available)
                    <span class="inline-flex items-center text-xs font-semibold bg-green-500/90 text-white px-2.5 py-1 rounded-full backdrop-blur-sm shadow">
                        ✓ Available
                    </span>
                    @else
                    <span class="inline-flex items-center text-xs font-semibold bg-red-500/90 text-white px-2.5 py-1 rounded-full backdrop-blur-sm shadow">
                        ✕ Unavailable
                    </span>
                    @endif
                </div>
            </div>

            <!-- Content Section -->
            <div class="p-4 space-y-3">
                <!-- Title -->
                <div>
                    <h3 class="text-lg font-bold text-gray-900 group-hover:text-primary transition-colors line-clamp-1">
                        {{ $facility->name }}
                    </h3>
                    @if($facility->description)
                    <p class="text-xs text-gray-500 mt-1 line-clamp-1">
                        {{ $facility->description }}
                    </p>
                    @endif
                </div>

                <!-- Location Info -->
                <div class="space-y-1.5">
                    @if($facility->address)
                    <div class="flex items-center gap-2 text-xs text-gray-600">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-primary flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                            <circle cx="12" cy="10" r="3"></circle>
                        </svg>
                        <span class="line-clamp-1">{{ $facility->address }}</span>
                    </div>
                    @endif
                </div>

                <!-- Action Buttons -->
                <div class="flex gap-2 pt-1">
                    <a href="{{ $facility->gps_lat && $facility->gps_long ? 'https://www.google.com/maps?q=' . $facility->gps_lat . ',' . $facility->gps_long : 'https://www.google.com/maps/search/' . urlencode($facility->address ?? $facility->name) }}"
                       target="_blank"
                       class="flex-1 inline-flex items-center justify-center gap-1 text-xs font-medium px-3 py-2 rounded-md border border-gray-200 text-gray-700 hover:bg-primary hover:text-white hover:border-primary transition-all no-underline">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                            <circle cx="12" cy="10" r="3"></circle>
                        </svg>
                        Open in Maps
                    </a>

                    <button class="inline-flex items-center justify-center h-8 w-8 p-0 rounded-md border border-gray-200 text-gray-500 hover:bg-blue-50 hover:text-blue-600 hover:border-blue-300 transition-all"
                            onclick="editFacility({{ $facility->id }})"
                            title="Edit">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                        </svg>
                    </button>

                    <button class="inline-flex items-center justify-center h-8 w-8 p-0 rounded-md border border-gray-200 text-gray-500 hover:bg-red-50 hover:text-red-600 hover:border-red-300 transition-all"
                            onclick="deleteFacility({{ $facility->id }})"
                            title="Delete">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="3 6 5 6 21 6"></polyline>
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                            <line x1="10" y1="11" x2="10" y2="17"></line>
                            <line x1="14" y1="11" x2="14" y2="17"></line>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
        @endforeach
    </div>
    @else
    <!-- Empty State -->
    <div class="bg-white rounded-lg border-2 border-dashed border-gray-200 shadow-sm">
        <div class="py-16 text-center">
            <div class="flex flex-col items-center gap-4">
                <div class="w-16 h-16 rounded-full bg-primary/10 flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                        <circle cx="12" cy="10" r="3"></circle>
                    </svg>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">No Facilities Yet</h3>
                    <p class="text-gray-500 max-w-md mx-auto">
                        Get started by adding your first facility. Track locations, manage availability, and more.
                    </p>
                </div>
                <button class="mt-4 inline-flex items-center gap-2 bg-primary text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-primary/90 transition-all shadow-md hover:shadow-lg"
                        @click="showAddFacilityModal = true">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="12" y1="5" x2="12" y2="19"></line>
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                    </svg>
                    Add Your First Facility
                </button>
            </div>
        </div>
    </div>
    @endif

    @include('admin.club.facilities.add')
    @include('admin.club.facilities.edit')

    {{-- Shared Facility Image Cropper Modal --}}
    <div class="modal fade" id="facilityCropperModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width:75%; width:900px;">
            <div class="modal-content shadow-lg">
                <div class="modal-body p-4">
                    <div class="mb-3 flex items-center gap-2">
                        <input type="file" id="facilityCropperFileInput" class="form-control form-control-sm" accept="image/*">
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div id="facilityCropperCanvas" class="takeone-canvas" style="height:380px;"></div>
                    <div class="grid grid-cols-2 gap-4 mt-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Zoom</label>
                            <input type="range" id="facilityCropperZoom" class="form-range" min="0" max="100" step="1" value="0">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Rotation</label>
                            <input type="range" id="facilityCropperRot" class="form-range" min="-180" max="180" step="1" value="0">
                        </div>
                    </div>
                    <button type="button" id="facilityCropperSave"
                            class="btn btn-success btn-lg font-bold w-full py-3 mt-3">
                        Crop & Add
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/cropme@1.4.1/dist/cropme.min.css">
<script src="https://unpkg.com/cropme@1.4.1/dist/cropme.min.js"></script>
@endpush

@push('scripts')
<script>
function deleteFacility(id) {
    confirmAction({
        title: 'Delete Facility',
        message: 'This facility and its image will be permanently removed.',
        confirmText: 'Delete',
        type: 'danger',
    }).then(confirmed => {
        if (!confirmed) return;

        fetch(`{{ url('admin/club/' . $club->slug . '/facilities') }}/${id}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert(data.message || 'Failed to delete facility');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to delete facility');
        });
    });
}

function editFacility(id) {
    // Fetch facility data
    fetch(`{{ url('admin/club/' . $club->slug . '/facilities') }}/${id}`, {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('HTTP ' + response.status);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            populateEditForm(data.data);
            // Use Alpine.js event to show modal
            window.dispatchEvent(new CustomEvent('open-edit-facility'));
        } else {
            alert(data.message || 'Failed to load facility data');
        }
    })
    .catch(error => {
        console.error('Edit facility error:', error);
        alert('Failed to load facility data: ' + error.message);
    });
}

function populateEditForm(facility) {
    // Set form action
    document.getElementById('editFacilityForm').action = `{{ url('admin/club/' . $club->slug . '/facilities') }}/${facility.id}`;
    document.getElementById('editFacilityId').value = facility.id;

    // Populate fields
    document.getElementById('editFacilityName').value = facility.name || '';
    document.getElementById('editFacilityAddress').value = facility.address || '';
    document.getElementById('editFacilityLat').value = facility.gps_lat || '';
    document.getElementById('editFacilityLng').value = facility.gps_long || '';
    document.getElementById('editFacilityMapsUrl').value = facility.maps_url || '';
    document.getElementById('editIsAvailable').checked = facility.is_available == 1;

    // Render existing images (images array takes priority, fall back to legacy photo field)
    const previews = document.getElementById('editFacilityImagePreviews');
    const keepInput = document.getElementById('editFacilityKeepImages');
    previews.innerHTML = '';
    let kept = (facility.images && facility.images.length)
        ? [...facility.images]
        : (facility.photo ? [facility.photo] : []);
    const renderPreviews = () => {
        keepInput.value = JSON.stringify(kept);
        previews.innerHTML = '';
        kept.forEach((path, idx) => {
            const wrap = document.createElement('div');
            wrap.className = 'relative group';
            wrap.innerHTML = `
                <img src="{{ asset('storage') }}/${path}" class="w-20 h-20 object-cover rounded-lg border border-gray-200">
                <button type="button" class="absolute -top-1.5 -right-1.5 bg-red-500 text-white rounded-full w-5 h-5 text-xs flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                    <i class="bi bi-x"></i>
                </button>`;
            wrap.querySelector('button').addEventListener('click', () => {
                kept.splice(idx, 1);
                renderPreviews();
            });
            previews.appendChild(wrap);
        });
    };
    renderPreviews();
    // Reset new images for edit context
    facilityNewImages.edit = [];
    const editNewPreviews = document.getElementById('editFacilityNewPreviews');
    if (editNewPreviews) editNewPreviews.innerHTML = '';
    document.getElementById('editFacilityBase64Inputs').innerHTML = '';
}

// Initialize edit map when modal becomes visible
const editModal = document.getElementById('editFacilityModal');
const editMapObserver = new MutationObserver(function(mutations) {
    mutations.forEach(function(mutation) {
        if (mutation.type === 'attributes' && mutation.attributeName === 'style') {
            const isVisible = editModal.style.display !== 'none' && !editModal.hasAttribute('hidden');
            if (isVisible) {
                const lat = parseFloat(document.getElementById('editFacilityLat').value) || {{ $club->latitude ?? 25.2048 }};
                const lng = parseFloat(document.getElementById('editFacilityLng').value) || {{ $club->longitude ?? 55.2708 }};
                LocationMap.init('editFacility', {{ $club->latitude ?? 25.2048 }}, {{ $club->longitude ?? 55.2708 }});
                LocationMap.setPosition('editFacility', lat, lng);
                LocationMap.refresh('editFacility');
            }
        }
    });
});
editMapObserver.observe(editModal, { attributes: true });

// ── Facility multi-image cropper ──────────────────────────────────────────
let facilityCropperInstance = null;
let facilityCropperContext  = 'add';
let facilityCropperModal    = null;
const facilityNewImages     = { add: [], edit: [] };

function openFacilityCropper(context) {
    facilityCropperContext = context;
    document.getElementById('facilityCropperFileInput').value = '';
    document.getElementById('facilityCropperZoom').value = 0;
    document.getElementById('facilityCropperRot').value  = 0;
    if (!facilityCropperModal) {
        facilityCropperModal = new bootstrap.Modal(document.getElementById('facilityCropperModal'));
    }
    facilityCropperModal.show();
}

$(function() {
    const zoomMin = 0.01, zoomMax = 3;

    function initFacilityCropper(url) {
        if (facilityCropperInstance) {
            try { facilityCropperInstance.destroy(); } catch(e) {}
            facilityCropperInstance = null;
        }
        document.getElementById('facilityCropperCanvas').innerHTML = '';
        facilityCropperInstance = new Cropme(document.getElementById('facilityCropperCanvas'), {
            container: { width: '100%', height: 380 },
            viewport: { width: 400, height: 300, type: 'square', border: { enable: true, width: 2, color: '#fff' } },
            transformOrigin: 'viewport',
            zoom: { min: zoomMin, max: zoomMax, enable: true, mouseWheel: true, slider: false },
            rotation: { enable: true, slider: false }
        });
        facilityCropperInstance.bind({ url }).then(() => {
            $('#facilityCropperZoom').val(0);
            $('#facilityCropperRot').val(0);
        });
    }

    // Init cropme only after modal is fully visible
    $('#facilityCropperModal').on('shown.bs.modal', function() {
        // Reset to clean state on each open
        if (facilityCropperInstance) {
            try { facilityCropperInstance.destroy(); } catch(e) {}
            facilityCropperInstance = null;
        }
        document.getElementById('facilityCropperCanvas').innerHTML = '';
    });

    $('#facilityCropperFileInput').on('change', function() {
        if (!this.files[0]) return;
        const reader = new FileReader();
        reader.onload = e => initFacilityCropper(e.target.result);
        reader.readAsDataURL(this.files[0]);
    });

    $('#facilityCropperZoom').on('input', function() {
        if (!facilityCropperInstance?.properties?.image) return;
        const scale = zoomMin + (zoomMax - zoomMin) * (this.value / 100);
        facilityCropperInstance.properties.scale = Math.min(Math.max(scale, zoomMin), zoomMax);
        const p = facilityCropperInstance.properties;
        p.image.style.transform = `translate3d(${p.x}px,${p.y}px,0) scale(${p.scale}) rotate(${p.deg}deg)`;
    });

    $('#facilityCropperRot').on('input', function() {
        if (facilityCropperInstance) facilityCropperInstance.rotate(parseInt(this.value));
    });

    $('#facilityCropperSave').on('click', function() {
        if (!facilityCropperInstance || !facilityCropperInstance.properties?.image) {
            alert('Please select an image first.');
            return;
        }
        const btn = $(this);
        btn.prop('disabled', true).text('Processing...');
        facilityCropperInstance.crop({ type: 'base64' }).then(base64 => {
            facilityNewImages[facilityCropperContext].push(base64);
            renderFacilityNewThumbnails(facilityCropperContext);
            facilityCropperModal.hide();
            btn.prop('disabled', false).text('Crop & Add');
        }).catch(err => {
            console.error('Crop failed:', err);
            btn.prop('disabled', false).text('Crop & Add');
        });
    });
});

function renderFacilityNewThumbnails(context) {
    const previewsId = context === 'add' ? 'addFacilityImagePreviews' : 'editFacilityNewPreviews';
    const inputsId   = context === 'add' ? 'addFacilityBase64Inputs'  : 'editFacilityBase64Inputs';

    // For edit: render new images in a separate container below existing ones
    let previews = document.getElementById(previewsId);
    if (!previews) {
        previews = document.createElement('div');
        previews.id = previewsId;
        previews.className = 'flex flex-wrap gap-2 mt-2';
        document.getElementById('editFacilityBase64Inputs').before(previews);
    }

    previews.innerHTML = '';
    const inputsContainer = document.getElementById(inputsId);
    inputsContainer.innerHTML = '';

    facilityNewImages[context].forEach((b64, idx) => {
        const wrap = document.createElement('div');
        wrap.className = 'relative group';
        wrap.innerHTML = `
            <img src="${b64}" class="w-20 h-20 object-cover rounded-lg border border-gray-200">
            <button type="button" class="absolute -top-1.5 -right-1.5 bg-red-500 text-white rounded-full w-5 h-5 text-xs flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                <i class="bi bi-x"></i>
            </button>`;
        wrap.querySelector('button').addEventListener('click', () => {
            facilityNewImages[context].splice(idx, 1);
            renderFacilityNewThumbnails(context);
        });
        previews.appendChild(wrap);

        const input = document.createElement('input');
        input.type  = 'hidden';
        input.name  = 'facility_images_base64[]';
        input.value = b64;
        inputsContainer.appendChild(input);
    });
}

// Reset new images when add modal closes
document.getElementById('addFacilityModal').addEventListener('click', function(e) {
    if (e.target === this || e.target.closest('.fixed.inset-0.bg-black\\/50') === e.target) {
        facilityNewImages.add = [];
        renderFacilityNewThumbnails('add');
    }
});

// Handle edit form submission
document.getElementById('editFacilityForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const formData = new FormData(this);
    const submitBtn = document.getElementById('submitEditFacilityBtn');
    const originalText = submitBtn.innerHTML;

    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Updating...';

    fetch(this.action, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.message || 'Failed to update facility');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to update facility');
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
});
</script>
@endpush

{{-- line-clamp-1 is a native Tailwind CSS 4 utility --}}
@endsection
