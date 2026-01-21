@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="mb-1"><i class="bi bi-compass me-2"></i>Explore</h1>
                    <p class="text-muted mb-0">Discover what's near you</p>
                </div>
                <div>
                    <button id="getLocationBtn" class="btn btn-primary">
                        <i class="bi bi-geo-alt-fill me-2"></i>Use My Location
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Location Status Alert -->
    <div id="locationAlert" class="alert alert-info alert-dismissible fade show" role="alert">
        <i class="bi bi-info-circle me-2"></i>
        <span id="locationMessage">Click "Use My Location" to find clubs near you</span>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>

    <!-- Loading Spinner -->
    <div id="loadingSpinner" class="text-center py-5" style="display: none;">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
        <p class="mt-3 text-muted">Finding what's near you...</p>
    </div>

    <div class="row" id="mainContent" style="display: none;">
        <!-- Map Column -->
        <div class="col-lg-8 mb-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="mb-0"><i class="bi bi-map me-2"></i>Map View</h5>
                </div>
                <div class="card-body p-0">
                    <div id="map" style="height: 600px; width: 100%;"></div>
                </div>
            </div>
        </div>

        <!-- Clubs List Column -->
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 sticky-top" style="top: 20px;">
                <div class="card-header bg-white border-0 py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-list-ul me-2"></i>Nearby</h5>
                        <span id="clubCount" class="badge bg-primary">0</span>
                    </div>
                </div>
                <div class="card-body p-0" style="max-height: 600px; overflow-y: auto;">
                    <div id="clubsList" class="list-group list-group-flush">
                        <!-- Clubs will be populated here -->
                    </div>
                    <div id="noClubsMessage" class="text-center py-5" style="display: none;">
                        <i class="bi bi-inbox" style="font-size: 3rem; color: #dee2e6;"></i>
                        <p class="text-muted mt-3">Nothing found in your area</p>
                        <p class="text-muted small">Try expanding your search radius</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('styles')
<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
    integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
    crossorigin=""/>

<style>
    .club-item {
        transition: all 0.3s ease;
        cursor: pointer;
        border-left: 4px solid transparent;
    }

    .club-item:hover {
        background-color: #f8f9fa;
        border-left-color: #0d6efd;
    }

    .club-item.active {
        background-color: #e7f1ff;
        border-left-color: #0d6efd;
    }

    .distance-badge {
        font-size: 0.875rem;
        padding: 0.25rem 0.5rem;
    }

    .leaflet-popup-content-wrapper {
        border-radius: 8px;
    }

    .leaflet-popup-content {
        margin: 15px;
        min-width: 200px;
    }

    .sticky-top {
        position: sticky;
        z-index: 1020;
    }
</style>
@endpush

@push('scripts')
<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
    integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
    crossorigin=""></script>

<script>
let map;
let userMarker;
let clubMarkers = [];
let userLocation = null;

// Initialize the page
document.addEventListener('DOMContentLoaded', function() {
    // Check if geolocation is supported
    if (!navigator.geolocation) {
        showAlert('Geolocation is not supported by your browser', 'danger');
        document.getElementById('getLocationBtn').disabled = true;
    }

    // Get location button click handler
    document.getElementById('getLocationBtn').addEventListener('click', getUserLocation);
});

// Get user's current location
function getUserLocation() {
    const btn = document.getElementById('getLocationBtn');
    const originalText = btn.innerHTML;

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Getting location...';

    showAlert('Requesting your location...', 'info');
    document.getElementById('loadingSpinner').style.display = 'block';

    navigator.geolocation.getCurrentPosition(
        function(position) {
            userLocation = {
                latitude: position.coords.latitude,
                longitude: position.coords.longitude
            };

            showAlert(`Location found: ${userLocation.latitude.toFixed(4)}, ${userLocation.longitude.toFixed(4)}`, 'success');

            // Initialize map
            initMap(userLocation.latitude, userLocation.longitude);

            // Fetch nearby clubs
            fetchNearbyClubs(userLocation.latitude, userLocation.longitude);

            btn.innerHTML = '<i class="bi bi-geo-alt-fill me-2"></i>Update Location';
            btn.disabled = false;
        },
        function(error) {
            let errorMessage = 'Unable to get your location. ';

            switch(error.code) {
                case error.PERMISSION_DENIED:
                    errorMessage += 'Please allow location access in your browser settings.';
                    break;
                case error.POSITION_UNAVAILABLE:
                    errorMessage += 'Location information is unavailable.';
                    break;
                case error.TIMEOUT:
                    errorMessage += 'Location request timed out.';
                    break;
                default:
                    errorMessage += 'An unknown error occurred.';
            }

            showAlert(errorMessage, 'danger');
            document.getElementById('loadingSpinner').style.display = 'none';
            btn.innerHTML = originalText;
            btn.disabled = false;
        },
        {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 0
        }
    );
}

// Initialize the map
function initMap(lat, lng) {
    // Remove existing map if any
    if (map) {
        map.remove();
    }

    // Create map centered on user's location
    map = L.map('map').setView([lat, lng], 13);

    // Add OpenStreetMap tiles (free!)
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        maxZoom: 19
    }).addTo(map);

    // Add user location marker
    userMarker = L.marker([lat, lng], {
        icon: L.divIcon({
            className: 'user-location-marker',
            html: '<div style="background-color: #0d6efd; width: 20px; height: 20px; border-radius: 50%; border: 3px solid white; box-shadow: 0 0 10px rgba(0,0,0,0.3);"></div>',
            iconSize: [20, 20],
            iconAnchor: [10, 10]
        })
    }).addTo(map);

    userMarker.bindPopup('<strong>Your Location</strong>').openPopup();

    // Add circle to show search radius
    L.circle([lat, lng], {
        color: '#0d6efd',
        fillColor: '#0d6efd',
        fillOpacity: 0.1,
        radius: 50000 // 50km radius
    }).addTo(map);

    document.getElementById('mainContent').style.display = 'block';
    document.getElementById('loadingSpinner').style.display = 'none';
}

// Fetch nearby clubs from API
function fetchNearbyClubs(lat, lng) {
    fetch(`{{ route('clubs.nearby') }}?latitude=${lat}&longitude=${lng}&radius=50`, {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayClubs(data.clubs);
            document.getElementById('clubCount').textContent = data.total;

            if (data.total === 0) {
                document.getElementById('noClubsMessage').style.display = 'block';
                document.getElementById('clubsList').style.display = 'none';
            } else {
                document.getElementById('noClubsMessage').style.display = 'none';
                document.getElementById('clubsList').style.display = 'block';
            }
        } else {
            showAlert('Failed to fetch clubs', 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('Error fetching clubs. Please try again.', 'danger');
    });
}

// Display clubs on map and in list
function displayClubs(clubs) {
    // Clear existing club markers
    clubMarkers.forEach(marker => map.removeLayer(marker));
    clubMarkers = [];

    const clubsList = document.getElementById('clubsList');
    clubsList.innerHTML = '';

    clubs.forEach((club, index) => {
        // Add marker to map
        const marker = L.marker([club.gps_lat, club.gps_long], {
            icon: L.divIcon({
                className: 'club-marker',
                html: `<div style="background-color: #dc3545; color: white; width: 30px; height: 30px; border-radius: 50%; border: 3px solid white; box-shadow: 0 0 10px rgba(0,0,0,0.3); display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 12px;">${index + 1}</div>`,
                iconSize: [30, 30],
                iconAnchor: [15, 15]
            })
        }).addTo(map);

        marker.bindPopup(`
            <div class="text-center">
                <h6 class="mb-2">${club.club_name}</h6>
                <p class="mb-1 small text-muted"><i class="bi bi-geo-alt"></i> ${club.distance} km away</p>
                <p class="mb-1 small"><strong>Owner:</strong> ${club.owner_name}</p>
                ${club.owner_mobile ? `<p class="mb-1 small"><i class="bi bi-telephone"></i> ${club.owner_mobile}</p>` : ''}
                ${club.owner_email ? `<p class="mb-0 small"><i class="bi bi-envelope"></i> ${club.owner_email}</p>` : ''}
            </div>
        `);

        clubMarkers.push(marker);

        // Add to list
        const clubItem = document.createElement('div');
        clubItem.className = 'club-item list-group-item list-group-item-action';
        clubItem.innerHTML = `
            <div class="d-flex justify-content-between align-items-start">
                <div class="flex-grow-1">
                    <div class="d-flex align-items-center mb-2">
                        <span class="badge bg-danger me-2">${index + 1}</span>
                        <h6 class="mb-0">${club.club_name}</h6>
                    </div>
                    <p class="mb-1 small text-muted">
                        <i class="bi bi-person-circle me-1"></i>${club.owner_name}
                    </p>
                    ${club.owner_mobile ? `
                    <p class="mb-1 small text-muted">
                        <i class="bi bi-telephone me-1"></i>${club.owner_mobile}
                    </p>` : ''}
                </div>
                <div class="text-end">
                    <span class="badge bg-primary distance-badge">
                        <i class="bi bi-geo-alt"></i> ${club.distance} km
                    </span>
                </div>
            </div>
        `;

        // Click handler to focus on club marker
        clubItem.addEventListener('click', function() {
            // Remove active class from all items
            document.querySelectorAll('.club-item').forEach(item => {
                item.classList.remove('active');
            });

            // Add active class to clicked item
            this.classList.add('active');

            // Pan to marker and open popup
            map.setView([club.gps_lat, club.gps_long], 15);
            marker.openPopup();
        });

        clubsList.appendChild(clubItem);
    });

    // Fit map to show all markers
    if (clubs.length > 0 && userLocation) {
        const bounds = L.latLngBounds([
            [userLocation.latitude, userLocation.longitude],
            ...clubs.map(club => [club.gps_lat, club.gps_long])
        ]);
        map.fitBounds(bounds, { padding: [50, 50] });
    }
}

// Show alert message
function showAlert(message, type = 'info') {
    const alert = document.getElementById('locationAlert');
    const messageSpan = document.getElementById('locationMessage');

    alert.className = `alert alert-${type} alert-dismissible fade show`;
    messageSpan.textContent = message;

    // Auto-dismiss success messages after 5 seconds
    if (type === 'success') {
        setTimeout(() => {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 5000);
    }
}
</script>
@endpush
@endsection
