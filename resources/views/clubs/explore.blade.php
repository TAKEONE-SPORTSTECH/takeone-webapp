@extends('layouts.app')

@section('content')
<div class="container py-4">
    <!-- Hero Section -->
    <div class="text-center mb-5">
        <h1 class="display-4 fw-bold text-danger mb-2">Find Your Perfect Fit</h1>
        <p class="lead text-muted">Discover sports clubs, trainers, nutrition clinics, and more near you</p>
        <p class="text-muted"><i class="bi bi-geo-alt-fill me-1"></i><span id="currentLocation">Detecting location...</span></p>
    </div>

    <!-- Search Bar with Near Me Button -->
    <div class="row justify-content-center mb-4">
        <div class="col-lg-10">
            <div class="card shadow-sm border-0">
                <div class="card-body p-2">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-0">
                            <i class="bi bi-search"></i>
                        </span>
                        <input type="text" id="searchInput" class="form-control border-0"
                               placeholder="Search for clubs, trainers, nutrition clinics...">
                        <button class="btn btn-danger px-4" id="nearMeBtn" type="button">
                            <i class="bi bi-geo-alt-fill me-2"></i>Near Me
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Category Tabs -->
    <div class="row justify-content-center mb-4">
        <div class="col-lg-10">
            <div class="d-flex flex-wrap gap-2 justify-content-center">
                <button class="btn btn-danger category-btn active" data-category="all">
                    <i class="bi bi-search me-2"></i>All
                </button>
                <button class="btn btn-outline-danger category-btn" data-category="sports-clubs">
                    <i class="bi bi-trophy me-2"></i>Sports Clubs
                </button>
                <button class="btn btn-outline-danger category-btn" data-category="personal-trainers">
                    <i class="bi bi-person me-2"></i>Personal Trainers
                </button>
                <button class="btn btn-outline-danger category-btn" data-category="events">
                    <i class="bi bi-calendar-event me-2"></i>Events
                </button>
                <button class="btn btn-outline-danger category-btn" data-category="nutrition-clinic">
                    <i class="bi bi-apple me-2"></i>Nutrition Clinic
                </button>
                <button class="btn btn-outline-danger category-btn" data-category="physiotherapy-clinics">
                    <i class="bi bi-activity me-2"></i>Physiotherapy Clinics
                </button>
                <button class="btn btn-outline-danger category-btn" data-category="sports-shops">
                    <i class="bi bi-bag me-2"></i>Sports Shops
                </button>
                <button class="btn btn-outline-danger category-btn" data-category="venues">
                    <i class="bi bi-building me-2"></i>Venues
                </button>
                <button class="btn btn-outline-danger category-btn" data-category="supplements">
                    <i class="bi bi-box me-2"></i>Supplements
                </button>
                <button class="btn btn-outline-danger category-btn" data-category="food-plans">
                    <i class="bi bi-egg-fried me-2"></i>Food Plans
                </button>
            </div>
        </div>
    </div>

    <!-- Location Status Alert -->
    <div id="locationAlert" class="alert alert-info alert-dismissible fade" role="alert" style="display: none;">
        <i class="bi bi-info-circle me-2"></i>
        <span id="locationMessage"></span>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>

    <!-- Loading Spinner -->
    <div id="loadingSpinner" class="text-center py-5">
        <div class="spinner-border text-danger" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
        <p class="mt-3 text-muted">Finding what's near you...</p>
    </div>

    <!-- Clubs Grid -->
    <div class="row justify-content-center" id="clubsGrid" style="display: none;">
        <div class="col-lg-10">
            <div class="row g-4" id="clubsContainer">
                <!-- Club cards will be inserted here -->
            </div>
            <div id="noResults" class="d-flex flex-column align-items-center justify-content-center text-center" style="display: none; min-height: 400px;">
                <i class="bi bi-inbox" style="font-size: 4rem; color: #dee2e6;"></i>
                <h4 class="mt-3 text-muted">No Results Found</h4>
                <p class="text-muted">Try adjusting your search or location</p>
            </div>
        </div>
    </div>
</div>

<!-- Map Modal -->
<div class="modal fade" id="mapModal" tabindex="-1" aria-labelledby="mapModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h5 class="modal-title" id="mapModalLabel">
                    <i class="bi bi-geo-alt-fill me-2 text-danger"></i>Set Your Location
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div id="map" style="height: 600px; width: 100%;"></div>
            </div>
            <div class="modal-footer border-0 bg-light">
                <div class="w-100 d-flex justify-content-between align-items-center">
                    <small class="text-muted">
                        <i class="bi bi-geo-alt-fill me-1"></i>
                        <span id="modalLocationCoordinates">Drag the marker to set your location</span>
                    </small>
                    <button type="button" class="btn btn-danger" id="applyLocationBtn">
                        <i class="bi bi-check-circle me-2"></i>Apply Location
                    </button>
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
    .category-btn {
        border-radius: 50px;
        padding: 0.5rem 1.5rem;
        font-size: 0.9rem;
        transition: all 0.3s ease;
    }

    .category-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }

    .category-btn.active {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(220, 53, 69, 0.3);
    }

    .club-card {
        transition: all 0.3s ease;
        border: none;
        border-radius: 12px;
        overflow: hidden;
    }

    .club-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 20px rgba(0,0,0,0.15);
    }

    .club-card-img {
        height: 200px;
        object-fit: cover;
        width: 100%;
    }

    .club-badge {
        position: absolute;
        top: 10px;
        right: 10px;
        background: #dc3545;
        color: white;
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .stat-box {
        background: #f8f9fa;
        border-radius: 8px;
        padding: 0.75rem;
        text-align: center;
    }

    .stat-box i {
        font-size: 1.25rem;
        color: #dc3545;
    }

    .stat-box .stat-number {
        font-size: 1.25rem;
        font-weight: 700;
        color: #212529;
    }

    .stat-box .stat-label {
        font-size: 0.75rem;
        color: #6c757d;
    }

    /* Pulsing animation for location marker */
    @keyframes pulse {
        0% {
            transform: scale(1);
            opacity: 1;
        }
        50% {
            transform: scale(1.2);
            opacity: 0.7;
        }
        100% {
            transform: scale(1);
            opacity: 1;
        }
    }

    .pulse-marker {
        animation: pulse 2s ease-in-out infinite;
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
let searchRadiusCircle;
let userLocation = null;
let watchId = null;
let currentCategory = 'all';
let allClubs = [];

// Initialize the page
document.addEventListener('DOMContentLoaded', function() {
    // Check if geolocation is supported
    if (!navigator.geolocation) {
        showAlert('Geolocation is not supported by your browser', 'danger');
        document.getElementById('loadingSpinner').style.display = 'none';
    } else {
        // Automatically start watching user's location
        startWatchingLocation();
    }

    // Category buttons
    document.querySelectorAll('.category-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.category-btn').forEach(b => {
                b.classList.remove('active', 'btn-danger');
                b.classList.add('btn-outline-danger');
            });
            this.classList.remove('btn-outline-danger');
            this.classList.add('active', 'btn-danger');

            currentCategory = this.dataset.category;
            filterClubs();
        });
    });

    // Search input
    document.getElementById('searchInput').addEventListener('input', function() {
        filterClubs();
    });

    // Near Me button
    document.getElementById('nearMeBtn').addEventListener('click', function() {
        const mapModal = new bootstrap.Modal(document.getElementById('mapModal'));
        mapModal.show();

        // Initialize map when modal is shown
        setTimeout(() => {
            if (userLocation) {
                initMap(userLocation.latitude, userLocation.longitude);
            }
        }, 300);
    });

    // Apply Location button
    document.getElementById('applyLocationBtn').addEventListener('click', function() {
        const mapModal = bootstrap.Modal.getInstance(document.getElementById('mapModal'));
        mapModal.hide();

        if (userLocation) {
            fetchNearbyClubs(userLocation.latitude, userLocation.longitude);
        }
    });
});

// Start watching user's location
function startWatchingLocation() {
    watchId = navigator.geolocation.watchPosition(
        function(position) {
            userLocation = {
                latitude: position.coords.latitude,
                longitude: position.coords.longitude
            };

            updateLocationDisplay(userLocation.latitude, userLocation.longitude);
            fetchNearbyClubs(userLocation.latitude, userLocation.longitude);

            // Stop watching after first successful location
            if (watchId) {
                navigator.geolocation.clearWatch(watchId);
                watchId = null;
            }
        },
        function(error) {
            let errorMessage = 'Unable to get your location. ';
            switch(error.code) {
                case error.PERMISSION_DENIED:
                    errorMessage += 'Please allow location access.';
                    break;
                case error.POSITION_UNAVAILABLE:
                    errorMessage += 'Location unavailable.';
                    break;
                case error.TIMEOUT:
                    errorMessage += 'Request timed out.';
                    break;
            }
            showAlert(errorMessage, 'danger');
            document.getElementById('loadingSpinner').style.display = 'none';
        },
        {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 0
        }
    );
}

// Update location display
function updateLocationDisplay(lat, lng) {
    document.getElementById('currentLocation').textContent =
        `${lat.toFixed(4)}, ${lng.toFixed(4)}`;
    document.getElementById('modalLocationCoordinates').textContent =
        `Latitude: ${lat.toFixed(6)}, Longitude: ${lng.toFixed(6)}`;
}

// Initialize map in modal
function initMap(lat, lng) {
    if (map) {
        map.remove();
    }

    map = L.map('map').setView([lat, lng], 13);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
        maxZoom: 19
    }).addTo(map);

    // Add draggable marker
    userMarker = L.marker([lat, lng], {
        draggable: true,
        icon: L.divIcon({
            className: 'user-location-marker',
            html: '<i class="bi bi-geo-alt-fill pulse-marker" style="font-size: 36px; color: #dc3545; filter: drop-shadow(0 3px 6px rgba(0,0,0,0.4));"></i>',
            iconSize: [36, 36],
            iconAnchor: [18, 36]
        })
    }).addTo(map);

    // Drag event
    userMarker.on('dragend', function(event) {
        const position = event.target.getLatLng();
        userLocation = {
            latitude: position.lat,
            longitude: position.lng
        };
        updateLocationDisplay(position.lat, position.lng);
    });

    // Search radius circle (removed - no red tint on map)
    // searchRadiusCircle = L.circle([lat, lng], {
    //     color: '#dc3545',
    //     fillColor: '#dc3545',
    //     fillOpacity: 0.1,
    //     radius: 50000
    // }).addTo(map);

    setTimeout(() => map.invalidateSize(), 100);
}

// Fetch nearby clubs
function fetchNearbyClubs(lat, lng) {
    document.getElementById('loadingSpinner').style.display = 'block';
    document.getElementById('clubsGrid').style.display = 'none';

    fetch(`{{ route('clubs.nearby') }}?latitude=${lat}&longitude=${lng}&radius=50`, {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        }
    })
    .then(response => response.json())
    .then(data => {
        document.getElementById('loadingSpinner').style.display = 'none';
        document.getElementById('clubsGrid').style.display = 'block';

        if (data.success) {
            allClubs = data.clubs;
            displayClubs(allClubs);
        } else {
            showAlert('Failed to fetch clubs', 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        document.getElementById('loadingSpinner').style.display = 'none';
        showAlert('Error fetching clubs', 'danger');
    });
}

// Display clubs as cards
function displayClubs(clubs) {
    const container = document.getElementById('clubsContainer');
    const noResults = document.getElementById('noResults');

    container.innerHTML = '';

    if (clubs.length === 0) {
        noResults.style.display = 'flex';
        return;
    }

    noResults.style.display = 'none';

    clubs.forEach(club => {
        const card = document.createElement('div');
        card.className = 'col-md-6 col-lg-4';
        card.innerHTML = `
            <div class="card club-card shadow-sm h-100">
                <div class="position-relative">
                    <img src="https://via.placeholder.com/400x200?text=${encodeURIComponent(club.club_name)}"
                         class="club-card-img" alt="${club.club_name}">
                    <span class="club-badge">Sports Club</span>
                </div>
                <div class="card-body">
                    <h5 class="card-title mb-2">${club.club_name}</h5>
                    <p class="text-danger mb-2">
                        <i class="bi bi-geo-alt-fill me-1"></i>${club.distance} km away
                    </p>
                    <p class="text-muted small mb-3">
                        <i class="bi bi-geo me-1"></i>${club.owner_name || 'N/A'}
                    </p>

                    <div class="row g-2 mb-3">
                        <div class="col-4">
                            <div class="stat-box">
                                <i class="bi bi-people"></i>
                                <div class="stat-number">0</div>
                                <div class="stat-label">Members</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="stat-box">
                                <i class="bi bi-box"></i>
                                <div class="stat-number">0</div>
                                <div class="stat-label">Packages</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="stat-box">
                                <i class="bi bi-person-badge"></i>
                                <div class="stat-number">0</div>
                                <div class="stat-label">Trainers</div>
                            </div>
                        </div>
                    </div>

                    <div class="d-grid gap-2">
                        <button class="btn btn-danger">
                            <i class="bi bi-person-plus me-2"></i>Join Club
                        </button>
                        <button class="btn btn-outline-danger">View Details</button>
                    </div>
                </div>
            </div>
        `;
        container.appendChild(card);
    });
}

// Filter clubs
function filterClubs() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();

    let filtered = allClubs.filter(club => {
        const matchesSearch = club.club_name.toLowerCase().includes(searchTerm) ||
                            (club.owner_name && club.owner_name.toLowerCase().includes(searchTerm));

        // Add category filtering logic here when categories are available in data
        return matchesSearch;
    });

    displayClubs(filtered);
}

// Show alert
function showAlert(message, type = 'danger') {
    const alert = document.getElementById('locationAlert');
    const messageSpan = document.getElementById('locationMessage');

    alert.style.display = 'block';
    alert.className = `alert alert-${type} alert-dismissible fade show`;
    messageSpan.textContent = message;
}
</script>
@endpush
@endsection
