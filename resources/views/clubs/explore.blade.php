@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4" x-data="exploreApp()">
    <!-- Hero Section -->
    <div class="text-center mb-3">
        <h1 class="text-4xl md:text-5xl font-bold text-primary mb-2">Find Your Perfect Fit</h1>
        <p class="text-lg md:text-xl text-muted-foreground">Discover sports clubs, trainers, nutrition clinics, and more near you</p>
        <p class="text-muted-foreground"><span id="currentLocation" class="badge bg-primary text-white rounded-full px-3 py-2 text-lg"><i class="bi bi-geo-alt-fill mr-1"></i>Detecting location...</span></p>
    </div>

    <!-- Search Bar with Near Me Button -->
    <div class="flex justify-center mb-4">
        <div class="w-full lg:w-5/6">
            <div class="card shadow-sm rounded-full border-0">
                <div class="rounded-full p-2">
                    <div class="flex rounded-full overflow-hidden">
                        <span class="flex items-center px-3 py-2 bg-white border-0 rounded-l-full">
                            <i class="bi bi-search"></i>
                        </span>
                        <input type="text" id="searchInput" class="flex-1 px-3 py-2 bg-white border-0 focus:outline-none focus:ring-0"
                               placeholder="Search for clubs, trainers, nutrition clinics...">
                        <button class="btn btn-primary px-4 rounded-full" id="nearMeBtn" type="button" @click="openMapModal()">
                            <i class="bi bi-geo-alt-fill mr-2"></i>Near Me
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Category Tabs -->
    <div class="flex justify-center mb-4">
        <div class="w-full lg:w-5/6">
            <div class="flex flex-wrap gap-2 justify-center">
                <button class="btn btn-primary category-btn active" data-category="all">
                    <i class="bi bi-search mr-2"></i>All
                </button>
                <button class="btn btn-outline-primary category-btn" data-category="sports-clubs">
                    <i class="bi bi-trophy mr-2"></i>Clubs
                </button>
                <button class="btn btn-outline-primary category-btn" data-category="personal-trainers">
                    <i class="bi bi-person mr-2"></i>Trainers
                </button>
                <button class="btn btn-outline-primary category-btn" data-category="events">
                    <i class="bi bi-calendar-event mr-2"></i>Events
                </button>
                <button class="btn btn-outline-primary category-btn" data-category="nutrition-clinic">
                    <i class="bi bi-apple mr-2"></i>Nutrition
                </button>
                <button class="btn btn-outline-primary category-btn" data-category="physiotherapy-clinics">
                    <i class="bi bi-activity mr-2"></i>Physiotherapy
                </button>
                <button class="btn btn-outline-primary category-btn" data-category="sports-shops">
                    <i class="bi bi-bag mr-2"></i>Shops
                </button>
                <button class="btn btn-outline-primary category-btn" data-category="venues">
                    <i class="bi bi-building-fill mr-2"></i>Venues
                </button>
                <button class="btn btn-outline-primary category-btn" data-category="supplements">
                    <i class="bi bi-box mr-2"></i>Supplements
                </button>
                <button class="btn btn-outline-primary category-btn" data-category="food-plans">
                    <i class="bi bi-egg-fried mr-2"></i>Food Plans
                </button>
            </div>
        </div>
    </div>

    <!-- Location Status Alert -->
    <div id="locationAlert" class="alert alert-info relative pr-12 hidden" role="alert" x-show="showAlert" x-transition>
        <i class="bi bi-info-circle mr-2"></i>
        <span id="locationMessage"></span>
        <button type="button" class="absolute top-4 right-4 btn-close" @click="showAlert = false"></button>
    </div>

    <!-- Loading Spinner -->
    <div id="loadingSpinner" class="text-center py-5">
        <div class="spinner-border text-primary" role="status">
            <span class="sr-only">Loading...</span>
        </div>
        <p class="mt-3 text-muted-foreground">Finding what's near you...</p>
    </div>

    <!-- Clubs Grid -->
    <div class="flex justify-center" id="clubsGrid" style="display: none;">
        <div class="w-full">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4" id="clubsContainer">
                <!-- Club cards will be inserted here -->
            </div>
        </div>
    </div>

    <!-- No Results -->
    <div class="flex justify-center" id="noResultsContainer" style="display: none;">
        <div class="w-full flex justify-center">
            <div id="noResults" class="flex flex-col items-center justify-center text-center min-h-[400px]">
                <i class="bi bi-inbox text-6xl text-gray-300"></i>
                <h4 class="mt-3 text-muted-foreground font-semibold">No Results Found</h4>
                <p class="text-muted-foreground">Try adjusting your search or location</p>
            </div>
        </div>
    </div>

    <!-- Map Modal (Alpine.js) -->
    <div x-show="mapModalOpen"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50"
         style="display: none;">
        <!-- Backdrop -->
        <div class="fixed inset-0 bg-black/50" @click="closeMapModal()"></div>

        <!-- Modal Dialog -->
        <div class="fixed inset-0 flex items-center justify-center p-4">
            <div x-show="mapModalOpen"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-95"
                 class="bg-white rounded-xl shadow-2xl border border-border w-full max-w-5xl max-h-[90vh] overflow-hidden"
                 @click.stop>
                <!-- Modal Header -->
                <div class="flex items-center justify-between px-6 py-4">
                    <h5 class="text-lg font-semibold">
                        <i class="bi bi-geo-alt-fill mr-2 text-primary"></i>Set Your Location
                    </h5>
                    <button type="button" class="btn-close" @click="closeMapModal()"></button>
                </div>
                <!-- Modal Body -->
                <div class="p-0">
                    <div id="map" style="height: 500px; width: 100%;"></div>
                </div>
                <!-- Modal Footer -->
                <div class="flex items-center justify-between px-6 py-4 bg-muted border-t border-border">
                    <small class="text-muted-foreground">
                        <i class="bi bi-geo-alt-fill mr-1"></i>
                        <span id="modalLocationCoordinates">Drag the marker to set your location</span>
                    </small>
                    <button type="button" class="btn btn-primary" id="applyLocationBtn" @click="applyLocation()">
                        <i class="bi bi-check-circle mr-2"></i>Apply Location
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
</style>
@endpush

@push('scripts')
<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
    integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
    crossorigin=""></script>

<script>
function exploreApp() {
    return {
        mapModalOpen: false,
        showAlert: false,
        map: null,
        userMarker: null,
        userLocation: null,
        watchId: null,
        currentCategory: 'all',
        allClubs: [],
        countriesData: [],

        init() {
            // Load countries from JSON file
            fetch('/data/countries.json')
                .then(response => response.json())
                .then(countries => {
                    this.countriesData = countries;
                })
                .catch(error => console.error('Error loading countries:', error));

            // Check if geolocation is supported
            if (!navigator.geolocation) {
                this.showAlertMessage('Geolocation is not supported by your browser', 'danger');
                document.getElementById('loadingSpinner').style.display = 'none';
                this.fetchAllClubs();
            } else {
                this.startWatchingLocation();
            }

            // Category buttons
            document.querySelectorAll('.category-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    document.querySelectorAll('.category-btn').forEach(b => {
                        b.classList.remove('active', 'btn-primary');
                        b.classList.add('btn-outline-primary');
                    });
                    e.target.classList.remove('btn-outline-primary');
                    e.target.classList.add('active', 'btn-primary');

                    this.currentCategory = e.target.dataset.category;

                    if (this.currentCategory === 'all' || this.currentCategory === 'sports-clubs') {
                        this.fetchAllClubs();
                    } else if (this.userLocation) {
                        this.fetchNearbyClubs(this.userLocation.latitude, this.userLocation.longitude);
                    } else {
                        this.fetchAllClubs();
                    }
                });
            });

            // Search input
            document.getElementById('searchInput').addEventListener('input', () => {
                this.filterClubs();
            });
        },

        openMapModal() {
            this.mapModalOpen = true;
            document.body.style.overflow = 'hidden';

            this.$nextTick(() => {
                setTimeout(() => {
                    if (this.userLocation) {
                        this.initMap(this.userLocation.latitude, this.userLocation.longitude);
                        this.updateModalLocation(this.userLocation.latitude, this.userLocation.longitude);
                    } else {
                        this.initMap(25.276987, 55.296249);
                        this.updateModalLocation(25.276987, 55.296249);
                    }
                }, 100);
            });
        },

        closeMapModal() {
            this.mapModalOpen = false;
            document.body.style.overflow = '';
            if (this.map) {
                this.map.remove();
                this.map = null;
            }
        },

        applyLocation() {
            this.closeMapModal();
            if (this.userLocation) {
                if (this.currentCategory === 'all') {
                    this.fetchAllClubs();
                } else {
                    this.fetchNearbyClubs(this.userLocation.latitude, this.userLocation.longitude);
                }
            }
        },

        startWatchingLocation() {
            this.watchId = navigator.geolocation.watchPosition(
                (position) => {
                    this.userLocation = {
                        latitude: position.coords.latitude,
                        longitude: position.coords.longitude
                    };

                    this.updateLocationDisplay(this.userLocation.latitude, this.userLocation.longitude);
                    this.fetchAllClubs();

                    if (this.watchId) {
                        navigator.geolocation.clearWatch(this.watchId);
                        this.watchId = null;
                    }
                },
                (error) => {
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
                    this.showAlertMessage(errorMessage, 'danger');
                    if (this.currentCategory !== 'all') {
                        this.fetchAllClubs();
                    }
                },
                {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 0
                }
            );
        },

        updateLocationDisplay(lat, lng) {
            document.getElementById('currentLocation').innerHTML =
                `<i class="bi bi-geo-alt-fill mr-1"></i>${lat.toFixed(4)}, ${lng.toFixed(4)}`;
        },

        initMap(lat, lng) {
            if (this.map) {
                this.map.remove();
            }

            this.map = L.map('map', { attributionControl: false }).setView([lat, lng], 13);

            L.tileLayer('http://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors',
                maxZoom: 19
            }).addTo(this.map);

            this.userMarker = L.marker([lat, lng], {
                draggable: true,
                icon: L.divIcon({
                    className: 'user-location-marker',
                    html: '<i class="bi bi-geo-alt-fill pulse-marker" style="font-size: 36px; color: #667eea; filter: drop-shadow(0 3px 6px rgba(0,0,0,0.4));"></i>',
                    iconSize: [36, 36],
                    iconAnchor: [18, 36]
                })
            }).addTo(this.map);

            this.userMarker.on('drag', (event) => {
                const position = event.target.getLatLng();
                this.userLocation = {
                    latitude: position.lat,
                    longitude: position.lng
                };
                this.updateLocationDisplay(position.lat, position.lng);
                this.updateModalLocation(position.lat, position.lng);
            });

            setTimeout(() => this.map.invalidateSize(), 100);
        },

        fetchNearbyClubs(lat, lng) {
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
                document.getElementById('clubsGrid').style.display = 'flex';

                if (data.success) {
                    this.allClubs = data.clubs;
                    this.displayClubs(this.allClubs);
                } else {
                    this.showAlertMessage('Failed to fetch clubs', 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('loadingSpinner').style.display = 'none';
                this.showAlertMessage('Error fetching clubs', 'danger');
            });
        },

        fetchAllClubs() {
            document.getElementById('loadingSpinner').style.display = 'block';
            document.getElementById('clubsGrid').style.display = 'none';

            let url = `{{ route('clubs.all') }}`;
            if (this.userLocation) {
                url += `?latitude=${this.userLocation.latitude}&longitude=${this.userLocation.longitude}`;
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
                document.getElementById('clubsGrid').style.display = 'flex';

                if (data.success) {
                    this.allClubs = data.clubs;
                    this.displayClubs(this.allClubs);
                } else {
                    this.showAlertMessage('Failed to fetch clubs', 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('loadingSpinner').style.display = 'none';
                this.showAlertMessage('Error fetching clubs', 'danger');
            });
        },

        displayClubs(clubs) {
            const container = document.getElementById('clubsContainer');
            const noResultsContainer = document.getElementById('noResultsContainer');

            container.innerHTML = '';

            let trainerAdded = false;

            // Add dummy trainer card if category is 'all' or 'personal-trainers'
            if (this.currentCategory === 'all' || this.currentCategory === 'personal-trainers') {
                const trainerCard = document.createElement('div');
                trainerCard.innerHTML = `
                    <div class="card border shadow-sm overflow-hidden club-card cursor-pointer" style="border-radius: 0;">
                        <!-- Cover Image -->
                        <div class="relative overflow-hidden h-48">
                            <img src="https://images.unsplash.com/photo-1583454110551-21f2fa2afe61?q=80&w=2070&auto=format&fit=crop"
                                 alt="Personal Trainer"
                                 loading="lazy"
                                 class="w-full h-full object-cover transition-transform duration-300">

                            <!-- Personal Trainer Badge -->
                            <div class="absolute top-2 left-2">
                                <span class="badge text-white px-3 py-1 bg-destructive rounded-full text-xs font-semibold"><i class="fa-solid fa-user mr-1"></i>Personal Trainer</span>
                            </div>
                        </div>

                        <!-- Card Body -->
                        <div class="p-4 bg-white">
                            <div class="mb-3">
                                <h3 class="font-semibold mb-2 club-title text-lg text-foreground">Alex Thompson</h3>
                                <div class="flex items-center mb-1 text-sm text-primary">
                                    <i class="fa fa-certificate mr-1"></i>
                                    <span class="font-semibold">Certified Strength & Conditioning Coach</span>
                                </div>
                                <div class="flex items-center text-muted-foreground text-sm">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-1 shrink-0">
                                        <path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"></path>
                                        <circle cx="12" cy="10" r="3"></circle>
                                    </svg>
                                    <span class="truncate">Ghassan Yusuf</span>
                                </div>
                            </div>

                            <div class="grid grid-cols-3 gap-2 text-center mb-3 text-xs">
                                <div class="p-2 rounded bg-primary/5">
                                    <i class="fa-solid fa-calendar mb-1 text-muted-foreground text-base"></i>
                                    <p class="font-semibold mb-0 text-foreground">13</p>
                                    <p class="text-muted-foreground mb-0">Years Exp.</p>
                                </div>
                                <div class="p-2 rounded bg-primary/5">
                                    <i class="fa-solid fa-certificate mb-1 text-muted-foreground text-base"></i>
                                    <p class="font-semibold mb-0 text-foreground">NASM</p>
                                    <p class="text-muted-foreground mb-0">Packages</p>
                                </div>
                                <div class="p-2 rounded bg-primary/5">
                                    <i class="fa-solid fa-star text-warning"></i>
                                    <p class="font-semibold mb-0 text-foreground">5.0</p>
                                    <p class="text-muted-foreground mb-0">Rating</p>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="flex gap-2">
                                <button class="btn btn-primary flex-1 font-semibold text-sm">
                                    <i class="fa-solid fa-calendar-plus mr-1"></i>Book Session
                                </button>
                                <button class="btn btn-outline-primary flex-1 font-semibold text-sm">
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

                // Prepare cover image
                let coverImageHtml = '';
                if (club.cover_image) {
                    coverImageHtml = `<img src="/storage/${club.cover_image}" alt="${club.club_name}" loading="lazy" class="w-full h-full object-cover club-cover-img transition-transform duration-300">`;
                } else {
                    coverImageHtml = `<div class="w-full h-full flex items-center justify-center" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                        <i class="bi bi-image text-white text-5xl opacity-30"></i>
                    </div>`;
                }

                // Prepare logo
                let logoHtml = '';
                if (club.logo) {
                    logoHtml = `<img src="/storage/${club.logo}" alt="${club.club_name} logo" loading="lazy" class="w-full h-full rounded-full object-contain">`;
                } else {
                    logoHtml = `<div class="w-full h-full rounded-full bg-primary flex items-center justify-center">
                        <span class="text-white font-bold text-2xl">${club.club_name.charAt(0)}</span>
                    </div>`;
                }

                card.innerHTML = `
                    <div class="card border shadow-sm overflow-hidden club-card cursor-pointer" style="border-radius: 0;" onclick="window.location.href='/clubs/${club.id}'">
                        <!-- Cover Image -->
                        <div class="relative overflow-hidden h-48">
                            ${coverImageHtml}

                            <!-- Club Logo - Bottom Left -->
                            <div class="absolute bottom-2 left-2">
                                <div class="bg-white shadow border p-0.5 w-20 h-20 rounded-full">
                                    ${logoHtml}
                                </div>
                            </div>

                            <!-- Sports Club Badge - Top Left -->
                            <div class="absolute top-2 left-2">
                                <span class="badge text-white px-3 py-1 bg-destructive rounded-full text-xs font-semibold"><i class="fa-solid fa-building mr-1"></i>Sports Club</span>
                            </div>
                        </div>

                        <!-- Card Body -->
                        <div class="p-4 bg-white">
                            <div class="mb-3">
                                <h3 class="font-semibold mb-2 club-title text-lg text-foreground">${club.club_name}</h3>
                                <div class="flex items-center mb-1 text-sm text-primary">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-1 shrink-0">
                                        <polygon points="3 11 22 2 13 21 11 13 3 11"></polygon>
                                    </svg>
                                    <span class="font-semibold">${club.distance ? club.distance + ' km away' : 'Location available'}</span>
                                </div>
                                <div class="flex items-center text-muted-foreground text-sm">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-1 shrink-0">
                                        <path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"></path>
                                        <circle cx="12" cy="10" r="3"></circle>
                                    </svg>
                                    <span class="truncate">${club.owner_name || 'N/A'}</span>
                                </div>
                            </div>

                            <!-- Stats Grid -->
                            <div class="grid grid-cols-3 gap-2 text-center mb-3 text-xs">
                                <div class="p-2 rounded bg-primary/5">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mx-auto mb-1 text-primary">
                                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                                        <circle cx="9" cy="7" r="4"></circle>
                                        <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
                                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                    </svg>
                                    <p class="font-semibold mb-0 text-foreground">13</p>
                                    <p class="text-muted-foreground mb-0">Members</p>
                                </div>
                                <div class="p-2 rounded bg-primary/5">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mx-auto mb-1 text-primary">
                                        <path d="M14.4 14.4 9.6 9.6"></path>
                                        <path d="M18.657 21.485a2 2 0 1 1-2.829-2.828l-1.767 1.768a2 2 0 1 1-2.829-2.829l6.364-6.364a2 2 0 1 1 2.829 2.829l-1.768 1.767a2 2 0 1 1 2.828 2.829z"></path>
                                        <path d="m21.5 21.5-1.4-1.4"></path>
                                        <path d="M3.9 3.9 2.5 2.5"></path>
                                        <path d="M6.404 12.768a2 2 0 1 1-2.829-2.829l1.768-1.767a2 2 0 1 1-2.828-2.829l2.828-2.828a2 2 0 1 1 2.829 2.828l1.767-1.768a2 2 0 1 1 2.829 2.829z"></path>
                                    </svg>
                                    <p class="font-semibold mb-0 text-foreground">0</p>
                                    <p class="text-muted-foreground mb-0">Packages</p>
                                </div>
                                <div class="p-2 rounded bg-primary/5">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mx-auto mb-1 text-primary">
                                        <path d="M11.525 2.295a.53.53 0 0 1 .95 0l2.31 4.679a2.123 2.123 0 0 0 1.595 1.16l5.166.756a.53.53 0 0 1 .294.904l-3.736 3.638a2.123 2.123 0 0 0-.611 1.878l.882 5.14a.53.53 0 0 1-.771.56l-4.618-2.428a2.122 2.122 0 0 0-1.973 0L6.396 21.01a.53.53 0 0 1-.77-.56l.881-5.139a2.122 2.122 0 0 0-.611-1.879L2.16 9.795a.53.53 0 0 1 .294-.906l5.165-.755a2.122 2.122 0 0 0 1.597-1.16z"></path>
                                    </svg>
                                    <p class="font-semibold mb-0 text-foreground">0</p>
                                    <p class="text-muted-foreground mb-0">Trainers</p>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="flex gap-2">
                                <button class="btn btn-primary flex-1 font-semibold text-sm">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-1">
                                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                                        <circle cx="9" cy="7" r="4"></circle>
                                        <line x1="19" x2="19" y1="8" y2="14"></line>
                                        <line x1="22" x2="16" y1="11" y2="11"></line>
                                    </svg>
                                    Join Club
                                </button>
                                <a href="/clubs/${club.id}" class="btn btn-outline-primary flex-1 font-semibold text-sm text-center">View Details</a>
                            </div>
                        </div>
                    </div>
                `;
                container.appendChild(card);
            });
        },

        filterClubs() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();

            let filtered = this.allClubs.filter(club => {
                const matchesSearch = club.club_name.toLowerCase().includes(searchTerm) ||
                                    (club.owner_name && club.owner_name.toLowerCase().includes(searchTerm));
                return matchesSearch;
            });

            this.displayClubs(filtered);
        },

        showAlertMessage(message, type = 'danger') {
            const alert = document.getElementById('locationAlert');
            const messageSpan = document.getElementById('locationMessage');

            this.showAlert = true;
            alert.className = `alert alert-${type} relative pr-12`;
            messageSpan.textContent = message;
        },

        async reverseGeocode(lat, lng) {
            try {
                const response = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=18`);
                const data = await response.json();
                return data.address || null;
            } catch (error) {
                console.error('Reverse geocoding error:', error);
                return null;
            }
        },

        getCountryInfo(address) {
            if (!address || !this.countriesData.length) return null;
            const iso2 = address.country_code?.toUpperCase();
            if (!iso2) return null;
            const country = this.countriesData.find(c => c.iso2 === iso2);
            if (country) {
                const flag = iso2.split('').map(char => String.fromCodePoint(127397 + char.charCodeAt(0))).join('');
                return { flag, name: country.name, iso3: country.iso3 };
            }
            return null;
        },

        async updateModalLocation(lat, lng) {
            const address = await this.reverseGeocode(lat, lng);
            const info = this.getCountryInfo(address);
            const coords = `Latitude: ${lat.toFixed(6)}, Longitude: ${lng.toFixed(6)}`;
            const area = address?.suburb || address?.town || address?.city || address?.state || address?.county || '';
            if (info) {
                document.getElementById('modalLocationCoordinates').innerHTML = `${info.name}${area ? ', ' + area : ''} - ${coords}`;
            } else {
                document.getElementById('modalLocationCoordinates').textContent = `${area ? area + ' - ' : ''}${coords}`;
            }
        }
    }
}
</script>
@endpush
@endsection
