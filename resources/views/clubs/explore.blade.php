@extends('layouts.app')

@section('content')
<div class="container py-4">
    <!-- Hero Section -->
    <div class="text-center mb-3">
        <h1 class="display-4 fw-bold text-primary mb-2">Find Your Perfect Fit</h1>
        <p class="lead text-muted">Discover sports clubs, trainers, nutrition clinics, and more near you</p>
        <p class="text-muted"><span id="currentLocation" class="badge bg-primary text-white rounded-pill px-3 py-2"><i class="bi bi-geo-alt-fill me-1 fs-5"></i>Detecting location...</span></p>
    </div>

    <!-- Search Bar with Near Me Button -->
    <div class="row justify-content-center mb-4">
        <div class="col-lg-10">
            <div class="card shadow-sm rounded-pill border-0">
                <div class="card-body rounded-pill p-2">
                    <div class="input-group rounded-pill">
                        <span class="input-group-text bg-white border-0">
                            <i class="bi bi-search"></i>
                        </span>
                        <input type="text" id="searchInput" class="form-control bg-white border-0"
                               placeholder="Search for clubs, trainers, nutrition clinics...">
                        <button class="btn btn-primary px-4 rounded-pill" id="nearMeBtn" type="button">
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
                <button class="btn btn-primary category-btn active" data-category="all">
                    <i class="bi bi-search me-2"></i>All
                </button>
                <button class="btn btn-outline-primary category-btn" data-category="sports-clubs">
                    <i class="bi bi-trophy me-2"></i>Clubs
                </button>
                <button class="btn btn-outline-primary category-btn" data-category="personal-trainers">
                    <i class="bi bi-person me-2"></i>Trainers
                </button>
                <button class="btn btn-outline-primary category-btn" data-category="events">
                    <i class="bi bi-calendar-event me-2"></i>Events
                </button>
                <button class="btn btn-outline-primary category-btn" data-category="nutrition-clinic">
                    <i class="bi bi-apple me-2"></i>Nutrition
                </button>
                <button class="btn btn-outline-primary category-btn" data-category="physiotherapy-clinics">
                    <i class="bi bi-activity me-2"></i>Physiotherapy
                </button>
                <button class="btn btn-outline-primary category-btn" data-category="sports-shops">
                    <i class="bi bi-bag me-2"></i>Shops
                </button>
                <button class="btn btn-outline-primary category-btn" data-category="venues">
                    <i class="bi bi-building-fill me-2"></i>Venues
                </button>
                <button class="btn btn-outline-primary category-btn" data-category="supplements">
                    <i class="bi bi-box me-2"></i>Supplements
                </button>
                <button class="btn btn-outline-primary category-btn" data-category="food-plans">
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
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
        <p class="mt-3 text-muted">Finding what's near you...</p>
    </div>

    <!-- Clubs Grid -->
    <div class="row justify-content-center" id="clubsGrid" style="display: none;">
        <div class="col-12">
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4" id="clubsContainer">
                <!-- Club cards will be inserted here -->
            </div>
        </div>
    </div>

    <!-- No Results -->
    <div class="row justify-content-center" id="noResultsContainer" style="display: none;">
        <div class="col-12 d-flex justify-content-center">
            <div id="noResults" class="d-flex flex-column align-items-center justify-content-center text-center" style="min-height: 400px;">
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
                    <i class="bi bi-geo-alt-fill me-2 text-primary"></i>Set Your Location
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
                    <button type="button" class="btn btn-primary" id="applyLocationBtn">
                        <i class="bi bi-check-circle me-2"></i>Apply Location
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

@push('styles')
<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

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
        color: white !important;
    }

    .category-btn.active {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px hsl(var(--primary) / 0.3);
    }

    .club-card {
        transition: all 0.3s ease-in-out;
        min-height: 450px;
    }

    .club-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04) !important;
    }

    .club-card:hover .club-cover-img {
        transform: scale(1.1);
    }

    .club-card:hover .club-title {
        color: #667eea !important;
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
        color: #0d6efd;
    }

    #currentLocation {
        font-size: 1.2rem;
    }

    .input-group.rounded-pill {
        overflow: hidden;
    }

    .input-group.rounded-pill .input-group-text {
        border-radius: 50rem 0 0 50rem !important;
    }

    .input-group.rounded-pill .form-control {
        border-radius: 0;
    }

    .form-control:focus {
        border-color: #ced4da !important;
        box-shadow: none !important;
    }

    /* Trainer Card Styles */
    .trainer-card {
        border: none;
        border-radius: 0;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
        overflow: hidden;
        background: white;
        min-height: 400px;
    }

    .trainer-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
    }

    .trainer-card .row {
        height: 192px;
    }

    .image-container {
        position: relative;
        height: 100%;
        overflow: hidden;
    }

    .pt-badge {
        position: absolute;
        top: 10px;
        left: 10px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 5px 15px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: bold;
        z-index: 2;
    }

    .trainer-img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.3s ease;
    }

    .trainer-card:hover .trainer-img {
        transform: scale(1.05);
    }

    .info-section {
        padding: 15px;
        height: 100%;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }

    .trainer-name {
        font-size: 1.2rem;
        font-weight: bold;
        color: #333;
        margin-bottom: 5px;
    }

    .trainer-title {
        font-size: 0.9rem;
        color: #666;
        margin-bottom: 10px;
    }

    .feature-list {
        list-style: none;
        padding: 0;
        margin-bottom: 10px;
        flex-grow: 1;
    }

    .feature-list li {
        margin-bottom: 5px;
        font-size: 0.8rem;
        color: #555;
    }

    .feature-list i {
        color: #28a745;
        margin-right: 8px;
    }

    .rating-box {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
        font-size: 0.8rem;
    }

    .stars {
        color: #ffc107;
    }

    .stars i {
        margin-right: 2px;
    }

    .trainer-buttons {
        margin-top: auto;
    }

    .btn-book {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
        color: white;
        padding: 8px 15px;
        border-radius: 20px;
        font-weight: bold;
        font-size: 0.8rem;
        transition: all 0.3s ease;
    }

    .btn-book:hover {
        background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
        transform: translateY(-2px);
    }

    .btn-view {
        background: transparent;
        border: 2px solid #667eea;
        color: #667eea;
        padding: 8px 15px;
        border-radius: 20px;
        font-weight: bold;
        font-size: 0.8rem;
        transition: all 0.3s ease;
    }

    .btn-view:hover {
        background: #667eea;
        color: white;
        transform: translateY(-2px);
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
let pageMap;
let userMarker;
let searchRadiusCircle;
let userLocation = null;
let watchId = null;
let currentCategory = 'all';
let allClubs = [];
let countriesData = [];

// Initialize the page
document.addEventListener('DOMContentLoaded', function() {
    // Load countries from JSON file
    fetch('/data/countries.json')
        .then(response => response.json())
        .then(countries => {
            countriesData = countries;
        })
        .catch(error => console.error('Error loading countries:', error));

    // Check if geolocation is supported
    if (!navigator.geolocation) {
        showAlert('Geolocation is not supported by your browser', 'danger');
        document.getElementById('loadingSpinner').style.display = 'none';
        // If no geolocation, fetch all clubs
        fetchAllClubs();
    } else {
        // Automatically start watching user's location
        // This will fetch clubs once location is detected
        startWatchingLocation();
    }

    // Category buttons
    document.querySelectorAll('.category-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.category-btn').forEach(b => {
                b.classList.remove('active', 'btn-primary');
                b.classList.add('btn-outline-primary');
            });
            this.classList.remove('btn-outline-primary');
            this.classList.add('active', 'btn-primary');

            currentCategory = this.dataset.category;

            // For 'all' and 'sports-clubs' (Clubs) categories, fetch all clubs with location-based sorting
            if (currentCategory === 'all' || currentCategory === 'sports-clubs') {
                fetchAllClubs();
            } else if (userLocation) {
                fetchNearbyClubs(userLocation.latitude, userLocation.longitude);
            } else {
                // If no location, show message or fetch all as fallback
                fetchAllClubs();
            }
        });
    });

    // Search input
    document.getElementById('searchInput').addEventListener('input', function() {
        filterClubs();
    });

    // Near Me button
    document.getElementById('nearMeBtn').addEventListener('click', function() {
        const mapModal = new bootstrap.Modal(document.getElementById('mapModal'));
        const modalElement = document.getElementById('mapModal');

        modalElement.addEventListener('shown.bs.modal', function() {
            if (userLocation) {
                initMap(userLocation.latitude, userLocation.longitude);
                updateModalLocation(userLocation.latitude, userLocation.longitude);
            } else {
                // No location available, use default and let user drag
                initMap(25.276987, 55.296249); // Default to Dubai or any location
                updateModalLocation(25.276987, 55.296249);
            }
        }, { once: true });

        mapModal.show();
    });

    // Apply Location button
    document.getElementById('applyLocationBtn').addEventListener('click', function() {
        const mapModal = bootstrap.Modal.getInstance(document.getElementById('mapModal'));
        mapModal.hide();

        if (userLocation) {
            if (currentCategory === 'all') {
                fetchAllClubs();
            } else {
                fetchNearbyClubs(userLocation.latitude, userLocation.longitude);
            }
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

            // Fetch all clubs with location-based sorting (since 'all' is default)
            fetchAllClubs();

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
            // If location fails and category is not 'all', perhaps fetch all as fallback
            if (currentCategory !== 'all') {
                fetchAllClubs();
            }
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
    document.getElementById('currentLocation').innerHTML =
        `<i class="bi bi-geo-alt-fill me-1 fs-5"></i>${lat.toFixed(4)}, ${lng.toFixed(4)}`;
}


// Initialize map in modal
function initMap(lat, lng) {
    if (map) {
        map.remove();
    }

    map = L.map('map', { attributionControl: false }).setView([lat, lng], 13);

    L.tileLayer('http://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
        maxZoom: 19
    }).addTo(map);

    // Add draggable marker
    userMarker = L.marker([lat, lng], {
        draggable: true,
        icon: L.divIcon({
            className: 'user-location-marker',
            html: '<i class="bi bi-geo-alt-fill pulse-marker" style="font-size: 36px; color: #667eea; filter: drop-shadow(0 3px 6px rgba(0,0,0,0.4));"></i>',
            iconSize: [36, 36],
            iconAnchor: [18, 36]
        })
    }).addTo(map);

    // Drag event
    userMarker.on('drag', function(event) {
        const position = event.target.getLatLng();
        userLocation = {
            latitude: position.lat,
            longitude: position.lng
        };
        updateLocationDisplay(position.lat, position.lng);
        updateModalLocation(position.lat, position.lng);
    });

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

// Fetch all clubs
function fetchAllClubs() {
    document.getElementById('loadingSpinner').style.display = 'block';
    document.getElementById('clubsGrid').style.display = 'none';

    // Build URL with location parameters if available
    let url = `{{ route('clubs.all') }}`;
    if (userLocation) {
        url += `?latitude=${userLocation.latitude}&longitude=${userLocation.longitude}`;
    }

    fetch(url, {
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
    const noResultsContainer = document.getElementById('noResultsContainer');

    container.innerHTML = '';

    let trainerAdded = false;

    // Add dummy trainer card if category is 'all' or 'personal-trainers'
    if (currentCategory === 'all' || currentCategory === 'personal-trainers') {
        const trainerCard = document.createElement('div');
        trainerCard.className = 'col';
        trainerCard.innerHTML = `
            <div class="card border shadow-sm overflow-hidden club-card" style="border-radius: 0; cursor: pointer; transition: all 0.3s ease;">
                <!-- Cover Image -->
                <div class="position-relative overflow-hidden" style="height: 192px;">
                    <img src="https://images.unsplash.com/photo-1583454110551-21f2fa2afe61?q=80&w=2070&auto=format&fit=crop"
                         alt="Personal Trainer"
                         loading="lazy"
                         class="w-100 h-100"
                         style="object-fit: cover; transition: transform 0.3s ease;">

                    <!-- Personal Trainer Badge -->
                    <div class="position-absolute" style="top: 8px; left: 8px;">
                        <span class="badge text-white px-3 py-1" style="background-color: #dc3545; border-radius: 9999px; font-size: 0.75rem; font-weight: 600;"><i class="fa-solid fa-user me-1"></i>Personal Trainer</span>
                    </div>
                </div>

                <!-- Card Body -->
                <div class="p-4" style="background-color: white;">
                    <div class="mb-3">
                        <!-- Trainer Name -->
                        <h3 class="fw-semibold mb-2 club-title" style="font-size: 1.125rem; color: #1f2937; transition: color 0.3s ease;">Alex Thompson</h3>

                        <!-- Distance -->
                        <div class="d-flex align-items-center mb-1" style="font-size: 0.875rem; color: #667eea;">
                            <i class="fa fa-certificate me-1"></i>
                            <span class="fw-semibold">Certified Strength & Conditioning Coach</span>
                        </div>

                        <!-- Location -->
                        <div class="d-flex align-items-center text-muted" style="font-size: 0.875rem;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-1 flex-shrink-0">
                                <path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"></path>
                                <circle cx="12" cy="10" r="3"></circle>
                            </svg>
                            <span class="text-truncate">Ghassan Yusuf</span>
                        </div>
                    </div>

                    <div class="row g-2 text-center mb-3" style="font-size: 0.75rem;">
                        <div class="col-4">
                            <div class="p-2 rounded" style="background-color: rgba(102, 126, 234, 0.05);">
                                <i class="fa-solid fa-calendar mb-1" style="color: #6b7280; font-size: 1rem;"></i>
                                <p class="fw-semibold mb-0" style="color: #1f2937;">13</p>
                                <p class="text-muted mb-0">Years Exp.</p>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2 rounded" style="background-color: rgba(102, 126, 234, 0.05);">
                                <i class="fa-solid fa-certificate mb-1" style="color: #6b7280; font-size: 1rem;"></i>
                                <p class="fw-semibold mb-0" style="color: #1f2937;">NASM</p>
                                <p class="text-muted mb-0">Packages</p>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2 rounded" style="background-color: rgba(102, 126, 234, 0.05);">
                                <i class="fa-solid fa-star"></i>
                                <p class="fw-semibold mb-0" style="color: #1f2937;">5.0</p>
                                <p class="text-muted mb-0">Rating</p>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="d-flex gap-2">
                        <button class="btn btn-primary flex-fill fw-semibold" style="font-size: 0.875rem;">
                            <i class="fa-solid fa-calendar-plus me-1"></i>Book Session
                        </button>
                        <button class="btn btn-outline-primary flex-fill fw-semibold" style="font-size: 0.875rem;">
                            View Details
                        </button>
                    </div>
                </div>
            </div>
        `;
        container.appendChild(trainerCard);
        trainerAdded = true;
    }

    if (clubs.length === 0 && !trainerAdded) {
        noResultsContainer.style.display = 'flex';
        return;
    }

    noResultsContainer.style.display = 'none';

    clubs.forEach(club => {
        const card = document.createElement('div');
        card.className = 'col';

        // Prepare cover image
        let coverImageHtml = '';
        if (club.cover_image) {
            coverImageHtml = `<img src="/storage/${club.cover_image}" alt="${club.club_name}" loading="lazy" class="w-100 h-100 club-cover-img" style="object-fit: cover; transition: transform 0.3s ease;">`;
        } else {
            coverImageHtml = `<div class="w-100 h-100 d-flex align-items-center justify-content-center" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <i class="bi bi-image text-white" style="font-size: 3rem; opacity: 0.3;"></i>
            </div>`;
        }

        // Prepare logo
        let logoHtml = '';
        if (club.logo) {
            logoHtml = `<img src="/storage/${club.logo}" alt="${club.club_name} logo" loading="lazy" class="w-100 h-100 rounded-circle" style="object-fit: contain;">`;
        } else {
            logoHtml = `<div class="w-100 h-100 rounded-circle bg-primary d-flex align-items-center justify-content-center">
                <span class="text-white fw-bold fs-4">${club.club_name.charAt(0)}</span>
            </div>`;
        }

        card.innerHTML = `
            <div class="card border shadow-sm overflow-hidden club-card" style="border-radius: 0; cursor: pointer; transition: all 0.3s ease;" onclick="window.location.href='/clubs/${club.id}'">
                <!-- Cover Image -->
                <div class="position-relative overflow-hidden" style="height: 192px;">
                    ${coverImageHtml}

                    <!-- Club Logo - Bottom Left -->
                    <div class="position-absolute" style="bottom: 8px; left: 8px;">
                        <div class="bg-white shadow border p-0.5" style="width: 80px; height: 80px; border-radius: 50%; border-color: rgba(0,0,0,0.1) !important;">
                            ${logoHtml}
                        </div>
                    </div>

                    <!-- Sports Club Badge - Top Left -->
                    <div class="position-absolute" style="top: 8px; left: 8px;">
                        <span class="badge text-white px-3 py-1" style="background-color: #dc3545; border-radius: 9999px; font-size: 0.75rem; font-weight: 600;"><i class="fa-solid fa-building me-1"></i>Sports Club</span>
                    </div>
                </div>

                <!-- Card Body -->
                <div class="p-4" style="background-color: white;">
                    <div class="mb-3">
                        <!-- Club Name -->
                        <h3 class="fw-semibold mb-2 club-title" style="font-size: 1.125rem; color: #1f2937; transition: color 0.3s ease;">${club.club_name}</h3>

                        <!-- Distance -->
                        <div class="d-flex align-items-center mb-1" style="font-size: 0.875rem; color: #667eea;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-1 flex-shrink-0">
                                <polygon points="3 11 22 2 13 21 11 13 3 11"></polygon>
                            </svg>
                            <span class="fw-semibold">${club.distance ? club.distance + ' km away' : 'Location available'}</span>
                        </div>

                        <!-- Address/Owner -->
                        <div class="d-flex align-items-center text-muted" style="font-size: 0.875rem;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-1 flex-shrink-0">
                                <path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"></path>
                                <circle cx="12" cy="10" r="3"></circle>
                            </svg>
                            <span class="text-truncate">${club.owner_name || 'N/A'}</span>
                        </div>
                    </div>

                    <!-- Stats Grid -->
                    <div class="row g-2 text-center mb-3" style="font-size: 0.75rem;">
                        <div class="col-4">
                            <div class="p-2 rounded" style="background-color: rgba(102, 126, 234, 0.05);">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mx-auto mb-1" style="color: #667eea;">
                                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="9" cy="7" r="4"></circle>
                                    <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                </svg>
                                <p class="fw-semibold mb-0" style="color: #1f2937;">13</p>
                                <p class="text-muted mb-0">Members</p>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2 rounded" style="background-color: rgba(102, 126, 234, 0.05);">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mx-auto mb-1" style="color: #667eea;">
                                    <path d="M14.4 14.4 9.6 9.6"></path>
                                    <path d="M18.657 21.485a2 2 0 1 1-2.829-2.828l-1.767 1.768a2 2 0 1 1-2.829-2.829l6.364-6.364a2 2 0 1 1 2.829 2.829l-1.768 1.767a2 2 0 1 1 2.828 2.829z"></path>
                                    <path d="m21.5 21.5-1.4-1.4"></path>
                                    <path d="M3.9 3.9 2.5 2.5"></path>
                                    <path d="M6.404 12.768a2 2 0 1 1-2.829-2.829l1.768-1.767a2 2 0 1 1-2.828-2.829l2.828-2.828a2 2 0 1 1 2.829 2.828l1.767-1.768a2 2 0 1 1 2.829 2.829z"></path>
                                </svg>
                                <p class="fw-semibold mb-0" style="color: #1f2937;">0</p>
                                <p class="text-muted mb-0">Packages</p>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2 rounded" style="background-color: rgba(102, 126, 234, 0.05);">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mx-auto mb-1" style="color: #667eea;">
                                    <path d="M11.525 2.295a.53.53 0 0 1 .95 0l2.31 4.679a2.123 2.123 0 0 0 1.595 1.16l5.166.756a.53.53 0 0 1 .294.904l-3.736 3.638a2.123 2.123 0 0 0-.611 1.878l.882 5.14a.53.53 0 0 1-.771.56l-4.618-2.428a2.122 2.122 0 0 0-1.973 0L6.396 21.01a.53.53 0 0 1-.77-.56l.881-5.139a2.122 2.122 0 0 0-.611-1.879L2.16 9.795a.53.53 0 0 1 .294-.906l5.165-.755a2.122 2.122 0 0 0 1.597-1.16z"></path>
                                </svg>
                                <p class="fw-semibold mb-0" style="color: #1f2937;">0</p>
                                <p class="text-muted mb-0">Trainers</p>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="d-flex gap-2">
                        <button class="btn btn-primary flex-fill fw-semibold" style="font-size: 0.875rem;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-1">
                                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                                <circle cx="9" cy="7" r="4"></circle>
                                <line x1="19" x2="19" y1="8" y2="14"></line>
                                <line x1="22" x2="16" y1="11" y2="11"></line>
                            </svg>
                            Join Club
                        </button>
<<<<<<< HEAD
                        <button class="btn btn-outline-primary flex-fill fw-semibold" style="font-size: 0.875rem;">View Details</button>
=======
                        <a href="/clubs/${club.id}" class="btn btn-outline-danger flex-fill fw-semibold" style="font-size: 0.875rem;">View Details</a>
>>>>>>> yousif
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

// Reverse geocode to get address
async function reverseGeocode(lat, lng) {
    try {
        const response = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=18`);
        const data = await response.json();
        return data.address || null;
    } catch (error) {
        console.error('Reverse geocoding error:', error);
        return null;
    }
}

// Get country info from address
function getCountryInfo(address) {
    if (!address || !countriesData.length) return null;
    const iso2 = address.country_code?.toUpperCase();
    if (!iso2) return null;
    const country = countriesData.find(c => c.iso2 === iso2);
    if (country) {
        const flag = iso2.split('').map(char => String.fromCodePoint(127397 + char.charCodeAt(0))).join('');
        return { flag, name: country.name, iso3: country.iso3 };
    }
    return null;
}

// Update modal location display with country and area
async function updateModalLocation(lat, lng) {
    const address = await reverseGeocode(lat, lng);
    const info = getCountryInfo(address);
    const coords = `Latitude: ${lat.toFixed(6)}, Longitude: ${lng.toFixed(6)}`;
    const area = address?.suburb || address?.town || address?.city || address?.state || address?.county || '';
    if (info) {
        document.getElementById('modalLocationCoordinates').innerHTML = `${info.name}${area ? ', ' + area : ''} - ${coords}`;
    } else {
        document.getElementById('modalLocationCoordinates').textContent = `${area ? area + ' - ' : ''}${coords}`;
    }
}
</script>
@endpush
@endsection
