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
        <div class="col-lg-10">
            <div class="row g-4" id="clubsContainer">
                <!-- Club cards will be inserted here -->
            </div>
        </div>
        <div class="col-12 d-flex justify-content-center">
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
            <button type="button" class="btn btn-primary" id="applyLocationBtn">
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
        color: white !important;
    }

    .category-btn.active {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px hsl(var(--primary) / 0.3);
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
        background: #0d6efd;
        color: white;
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .stat-box {
        background: hsl(var(--muted));
        border-radius: 8px;
        padding: 0.75rem;
        text-align: center;
    }

    .stat-box i {
        font-size: 1.25rem;
        color: hsl(var(--primary));
    }

    .stat-box .stat-number {
        font-size: 1.25rem;
        font-weight: 700;
        color: hsl(var(--foreground));
    }

    .stat-box .stat-label {
        font-size: 0.75rem;
        color: hsl(var(--muted-foreground));
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
    } else {
        // Automatically start watching user's location
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
                updateModalLocation(userLocation.latitude, userLocation.longitude);
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
    document.getElementById('currentLocation').innerHTML =
        `<i class="bi bi-geo-alt-fill me-1 fs-5"></i>${lat.toFixed(4)}, ${lng.toFixed(4)}`;
}

// Initialize map in modal
function initMap(lat, lng) {
    if (map) {
        map.remove();
    }

    map = L.map('map', { attributionControl: false }).setView([lat, lng], 13);

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
        updateModalLocation(position.lat, position.lng);
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
            <div class="rounded-none border bg-card text-card-foreground shadow-sm hover:shadow-elevated transition-all cursor-pointer overflow-hidden group">
                <div class="relative h-64 overflow-hidden">
                    <img src="https://via.placeholder.com/400x200?text=${encodeURIComponent(club.club_name)}" alt="${club.club_name}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                    <div class="absolute bottom-3 right-3 z-10">
                        <div class="w-14 h-14 rounded-full border-2 border-white/90 shadow-lg overflow-hidden bg-white/95 backdrop-blur">
                            <img src="https://via.placeholder.com/50x50?text=Logo" alt="${club.club_name} logo" class="w-full h-full object-contain rounded-full">
                        </div>
                    </div>
                    <div class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold transition-colors focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 border-transparent absolute top-4 right-4 bg-brand-red text-white hover:bg-brand-red-dark">Sports Club</div>
                </div>
                <div class="px-5 pt-4 pb-2">
                    <h3 class="text-2xl font-bold text-foreground leading-tight line-clamp-2">${club.club_name}</h3>
                </div>
                <div class="p-6 px-5 pb-5 pt-2 space-y-3">
                    <div>
                        <div class="flex items-center gap-1 text-sm text-brand-red mb-1">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-navigation w-4 h-4">
                                <polygon points="3 11 22 2 13 21 11 13 3 11"></polygon>
                            </svg>
                            <span class="font-medium">${club.distance} km away</span>
                        </div>
                        <div class="flex items-center gap-1 text-sm text-muted-foreground">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-building w-4 h-4">
                                <rect width="16" height="20" x="4" y="2" rx="2" ry="2"></rect>
                                <path d="M9 22v-4h6v4"></path>
                                <path d="M8 6h.01"></path>
                                <path d="M16 6h.01"></path>
                                <path d="M12 6h.01"></path>
                                <path d="M12 10h.01"></path>
                                <path d="M12 14h.01"></path>
                                <path d="M16 10h.01"></path>
                                <path d="M16 14h.01"></path>
                                <path d="M8 10h.01"></path>
                                <path d="M8 14h.01"></path>
                            </svg>
                            <span>${club.owner_name || 'N/A'}</span>
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-2 border-t pt-3">
                        <div class="bg-brand-red/5 rounded-lg p-2 border border-brand-red/10 hover:border-brand-red/20 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-users w-4 h-4 mx-auto mb-1 text-brand-red">
                                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                                <circle cx="9" cy="7" r="4"></circle>
                                <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                            </svg>
                            <p class="text-lg font-bold text-center">0</p>
                            <p class="text-[10px] text-muted-foreground text-center font-medium">Members</p>
                        </div>
                        <div class="bg-brand-red/5 rounded-lg p-2 border border-brand-red/10 hover:border-brand-red/20 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-package w-4 h-4 mx-auto mb-1 text-brand-red">
                                <path d="M11 21.73a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73z"></path>
                                <path d="M12 22V12"></path>
                                <path d="m3.3 7 7.703 4.734a2 2 0 0 0 1.994 0L20.7 7"></path>
                                <path d="m7.5 4.27 9 5.15"></path>
                            </svg>
                            <p class="text-lg font-bold text-center">0</p>
                            <p class="text-[10px] text-muted-foreground text-center font-medium">Packages</p>
                        </div>
                        <div class="bg-brand-red/5 rounded-lg p-2 border border-brand-red/10 hover:border-brand-red/20 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-user w-4 h-4 mx-auto mb-1 text-brand-red">
                                <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                            <p class="text-lg font-bold text-center">0</p>
                            <p class="text-[10px] text-muted-foreground text-center font-medium">Trainers</p>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm ring-offset-background transition-all duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg]:size-4 [&_svg]:shrink-0 hover:shadow-medium hover:scale-105 h-10 px-4 py-2 flex-1 bg-brand-red hover:bg-brand-red-dark text-white font-semibold shadow-sm">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-user-plus w-4 h-4 mr-2">
                                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                                <circle cx="9" cy="7" r="4"></circle>
                                <line x1="19" x2="19" y1="8" y2="14"></line>
                                <line x1="22" x2="16" y1="11" y2="11"></line>
                            </svg>
                            Join Club
                        </button>
                        <button class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm ring-offset-background transition-all duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg]:size-4 [&_svg]:shrink-0 hover:text-accent-foreground h-10 px-4 py-2 flex-1 text-brand-red hover:bg-brand-red/10 font-semibold">
                            View Details
                        </button>
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
