@extends('layouts.admin-club')

@section('club-admin-content')
<div class="space-y-8" x-data="{ showAddFacilityModal: false, showEditFacilityModal: false, editingFacility: null }">
    <!-- Header -->
    <div class="flex justify-between items-center pb-6 border-b border-gray-200">
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
            <!-- Image Section -->
            <div class="relative w-full h-40 bg-gradient-to-br from-gray-100 to-gray-50 overflow-hidden">
                @if($facility->photo)
                <img src="{{ asset('storage/' . $facility->photo) }}"
                     alt="{{ $facility->name }}"
                     class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-700">
                <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-black/20 to-transparent"></div>
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
                        data-bs-toggle="modal"
                        data-bs-target="#addFacilityModal">
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
</div>

@include('admin.club.facilities.add')
@include('admin.club.facilities.edit')

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
let editFacilityMap = null;
let editFacilityMarker = null;

function deleteFacility(id) {
    if (!confirm('Are you sure you want to delete this facility?')) {
        return;
    }

    fetch(`{{ url('admin/club/' . $club->id . '/facilities') }}/${id}`, {
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
}

function editFacility(id) {
    // Fetch facility data
    fetch(`{{ url('admin/club/' . $club->id . '/facilities') }}/${id}`, {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            populateEditForm(data.data);
            // Use Alpine.js to show modal
            const container = document.querySelector('[x-data*="showEditFacilityModal"]');
            if (container && container.__x) {
                container.__x.$data.showEditFacilityModal = true;
            }
        } else {
            alert(data.message || 'Failed to load facility data');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to load facility data');
    });
}

function populateEditForm(facility) {
    // Set form action
    document.getElementById('editFacilityForm').action = `{{ url('admin/club/' . $club->id . '/facilities') }}/${facility.id}`;
    document.getElementById('editFacilityId').value = facility.id;

    // Populate fields
    document.getElementById('editFacilityName').value = facility.name || '';
    document.getElementById('editFacilityAddress').value = facility.address || '';
    document.getElementById('editFacilityLatitude').value = facility.gps_lat || '';
    document.getElementById('editFacilityLongitude').value = facility.gps_long || '';
    document.getElementById('editIsAvailable').checked = facility.is_available == 1;

    // Handle current image
    const currentImageContainer = document.getElementById('editCurrentImageContainer');
    const noImagePlaceholder = document.getElementById('editNoImagePlaceholder');
    const currentImage = document.getElementById('editCurrentImage');

    if (facility.photo) {
        currentImage.src = `{{ asset('storage') }}/${facility.photo}`;
        currentImageContainer.classList.remove('hidden');
        noImagePlaceholder.classList.add('hidden');
    } else {
        currentImageContainer.classList.add('hidden');
        noImagePlaceholder.classList.remove('hidden');
    }

    // Reset new image preview
    document.getElementById('editFacilityImage').value = '';
    document.getElementById('editImagePreviewSection').classList.add('hidden');
}

// Initialize edit modal map
document.getElementById('editFacilityModal').addEventListener('shown.bs.modal', function() {
    const lat = parseFloat(document.getElementById('editFacilityLatitude').value) || {{ $club->latitude ?? 25.2048 }};
    const lng = parseFloat(document.getElementById('editFacilityLongitude').value) || {{ $club->longitude ?? 55.2708 }};

    if (!editFacilityMap) {
        editFacilityMap = L.map('editFacilityMap').setView([lat, lng], 13);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(editFacilityMap);

        editFacilityMarker = L.marker([lat, lng], { draggable: true }).addTo(editFacilityMap);

        // Update inputs when marker is dragged
        editFacilityMarker.on('dragend', function(e) {
            const pos = e.target.getLatLng();
            document.getElementById('editFacilityLatitude').value = pos.lat.toFixed(6);
            document.getElementById('editFacilityLongitude').value = pos.lng.toFixed(6);
        });

        // Update marker when map is clicked
        editFacilityMap.on('click', function(e) {
            editFacilityMarker.setLatLng(e.latlng);
            document.getElementById('editFacilityLatitude').value = e.latlng.lat.toFixed(6);
            document.getElementById('editFacilityLongitude').value = e.latlng.lng.toFixed(6);
        });
    } else {
        editFacilityMap.setView([lat, lng], 13);
        editFacilityMarker.setLatLng([lat, lng]);
    }

    setTimeout(() => editFacilityMap.invalidateSize(), 100);
});

// Update marker when lat/lng inputs change
document.getElementById('editFacilityLatitude').addEventListener('change', updateEditMarkerFromInputs);
document.getElementById('editFacilityLongitude').addEventListener('change', updateEditMarkerFromInputs);

function updateEditMarkerFromInputs() {
    const lat = parseFloat(document.getElementById('editFacilityLatitude').value);
    const lng = parseFloat(document.getElementById('editFacilityLongitude').value);
    if (!isNaN(lat) && !isNaN(lng) && editFacilityMarker) {
        editFacilityMarker.setLatLng([lat, lng]);
        editFacilityMap.setView([lat, lng]);
    }
}

// Geocode address and update map
document.getElementById('searchEditAddressBtn').addEventListener('click', function() {
    const address = document.getElementById('editFacilityAddress').value.trim();
    if (!address) {
        alert('Please enter an address to search');
        return;
    }

    const btn = this;
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Searching...';

    // Use Nominatim (OpenStreetMap) for geocoding - free, no API key needed
    fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(address)}`)
        .then(response => response.json())
        .then(data => {
            if (data && data.length > 0) {
                const result = data[0];
                const lat = parseFloat(result.lat);
                const lng = parseFloat(result.lon);

                // Update inputs
                document.getElementById('editFacilityLatitude').value = lat.toFixed(6);
                document.getElementById('editFacilityLongitude').value = lng.toFixed(6);

                // Update map and marker
                if (editFacilityMap && editFacilityMarker) {
                    editFacilityMarker.setLatLng([lat, lng]);
                    editFacilityMap.setView([lat, lng], 15);
                }

                // Optionally update address with the full address from Nominatim
                // document.getElementById('editFacilityAddress').value = result.display_name;
            } else {
                alert('Address not found. Try a more specific address or use the map to set the location manually.');
            }
        })
        .catch(error => {
            console.error('Geocoding error:', error);
            alert('Failed to search for address. Please try again or set the location manually on the map.');
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = originalText;
        });
});

// Also allow pressing Enter in the address field to search
document.getElementById('editFacilityAddress').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        document.getElementById('searchEditAddressBtn').click();
    }
});

// Image preview for edit form
document.getElementById('editFacilityImage').addEventListener('change', function() {
    const file = this.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('editImagePreview').src = e.target.result;
            document.getElementById('editImagePreviewSection').classList.remove('hidden');
        };
        reader.readAsDataURL(file);
    } else {
        document.getElementById('editImagePreviewSection').classList.add('hidden');
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

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<style>
.line-clamp-1 {
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
@endpush
@endsection
