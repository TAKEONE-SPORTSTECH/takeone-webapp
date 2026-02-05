@props(['club' => null, 'mode' => 'create'])

@php
    $isEdit = $mode === 'edit' && $club;
@endphp

<div class="px-0">
    <h5 class="font-bold mb-3">Location</h5>
    <p class="text-muted mb-4">Set your club's geographic location and regional settings</p>

    <!-- Country, Timezone, Currency Row -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
        <div>
            <x-nationality-dropdown
                name="country"
                id="country"
                label="Country"
                :value="$club->country ?? old('country', 'Bahrain')"
                :required="true"
                :error="null" />
        </div>
        <div>
            <x-timezone-dropdown
                name="timezone"
                id="timezone"
                :value="$club->timezone ?? old('timezone', 'Asia/Bahrain')"
                :required="false"
                :error="null" />
        </div>
        <div>
            <x-currency-dropdown
                name="currency"
                id="currency"
                :value="$club->currency ?? old('currency', 'BHD')"
                :required="false"
                :error="null" />
        </div>
    </div>

    <!-- Address -->
    <div class="mb-4">
        <label for="address" class="form-label">Street Address</label>
        <textarea class="form-control"
                  id="address"
                  name="address"
                  rows="2"
                  placeholder="Enter the full street address of your club">{{ $club->address ?? old('address') }}</textarea>
        <small class="text-muted-foreground">Full address including building number, street name, area, etc.</small>
    </div>

    <!-- Map -->
    <div class="mb-4">
        <label class="form-label">Location on Map</label>
        <div id="modalClubMap" class="rounded-lg border border-border" style="height: 400px;"></div>
        <small class="text-muted-foreground">Drag the marker to set the exact location of your club</small>
    </div>

    <!-- GPS Coordinates -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
        <div>
            <label for="gps_lat" class="form-label">
                <i class="bi bi-geo-alt mr-1"></i>Latitude
            </label>
            <input type="number"
                   class="form-control"
                   id="gps_lat"
                   name="gps_lat"
                   value="{{ $club->gps_lat ?? old('gps_lat') }}"
                   step="0.0000001"
                   min="-90"
                   max="90"
                   placeholder="e.g., 26.0667">
            <small class="text-muted-foreground">Decimal degrees (-90 to 90)</small>
        </div>
        <div>
            <label for="gps_long" class="form-label">
                <i class="bi bi-geo-alt mr-1"></i>Longitude
            </label>
            <input type="number"
                   class="form-control"
                   id="gps_long"
                   name="gps_long"
                   value="{{ $club->gps_long ?? old('gps_long') }}"
                   step="0.0000001"
                   min="-180"
                   max="180"
                   placeholder="e.g., 50.5577">
            <small class="text-muted-foreground">Decimal degrees (-180 to 180)</small>
        </div>
    </div>

    <!-- Google Maps Link -->
    <div class="mb-4">
        <label for="google_maps_link" class="form-label">
            <i class="bi bi-google mr-1"></i>Google Maps Link (Optional)
        </label>
        <div class="input-group">
            <span class="input-group-text bg-white">
                <i class="bi bi-link-45deg"></i>
            </span>
            <input type="url"
                   class="form-control"
                   id="google_maps_link"
                   name="google_maps_link"
                   placeholder="Paste Google Maps share link here..."
                   pattern="https?://.*google\.com/maps.*|https?://goo\.gl/maps/.*">
        </div>
        <small class="text-muted-foreground">Paste a Google Maps share URL to auto-fill coordinates</small>
    </div>

    <!-- Quick Location Actions -->
    <div class="flex gap-2 flex-wrap">
        <button type="button" class="btn btn-outline-primary btn-sm" onclick="getCurrentLocation()">
            <i class="bi bi-crosshair mr-2"></i>Use My Current Location
        </button>
        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="centerOnCountry()">
            <i class="bi bi-globe mr-2"></i>Center on Selected Country
        </button>
    </div>
</div>

@push('scripts')
<script>
    let clubMap = null;
    let clubMarker = null;
    let countriesData = null;
    let mapInitialized = false;

    document.addEventListener('DOMContentLoaded', function() {
        // Load countries data
        fetch('/data/countries.json')
            .then(response => response.json())
            .then(countries => {
                countriesData = countries;

                // Device-based preselection on modal open (create mode only)
                const clubForm = document.getElementById('clubForm');
                if (clubForm && clubForm.dataset.mode === 'create') {
                    detectAndPreselectCountries(countries);
                }

                setupLocationHandlers();
            })
            .catch(error => console.error('Error loading countries:', error));

        // Initialize map when location tab is shown
        const locationTab = document.getElementById('location-tab');
        const clubModal = document.getElementById('clubModal');

        function tryInitializeMap() {
            if (!mapInitialized && locationTab && locationTab.classList.contains('active')) {
                setTimeout(() => {
                    initializeMap();
                    mapInitialized = true;
                }, 100);
            } else if (clubMap) {
                setTimeout(() => {
                    clubMap.invalidateSize();
                }, 100);
            }
        }

        if (locationTab) {
            locationTab.addEventListener('shown.bs.tab', tryInitializeMap);
        }

        if (clubModal) {
            clubModal.addEventListener('shown.bs.modal', function() {
                mapInitialized = false;
                setTimeout(tryInitializeMap, 150);
            });

            clubModal.addEventListener('hidden.bs.modal', function() {
                if (clubMap) {
                    clubMap.remove();
                    clubMap = null;
                    clubMarker = null;
                    mapInitialized = false;
                }
            });
        }
    });

    function detectAndPreselectCountries(countries) {
        if (!navigator.geolocation) {
            preselectCountryData('Bahrain', countries);
            return;
        }

        navigator.geolocation.getCurrentPosition(
            function(position) {
                const lat = position.coords.latitude;
                const lon = position.coords.longitude;

                fetch(`https://api.bigdatacloud.net/data/reverse-geocode-client?latitude=${lat}&longitude=${lon}&localityLanguage=en`)
                    .then(response => response.json())
                    .then(data => {
                        const countryName = data.countryName || 'Bahrain';
                        preselectCountryData(countryName, countries);
                    })
                    .catch(() => {
                        preselectCountryData('Bahrain', countries);
                    });
            },
            function() {
                preselectCountryData('Bahrain', countries);
            }
        );
    }

    function preselectCountryData(countryName, countries) {
        const country = countries.find(c => c.name.toLowerCase() === countryName.toLowerCase());
        if (!country) return;

        const countryInput = document.getElementById('country');
        if (countryInput && !countryInput.value) {
            countryInput.value = country.iso3 || country.name;
            countryInput.dispatchEvent(new Event('change'));
        }

        const timezoneInput = document.getElementById('timezone');
        if (timezoneInput && country.timezone && !timezoneInput.value && typeof setTimezoneValue === 'function') {
            setTimezoneValue('timezone', country.timezone, countries);
        }

        const currencyInput = document.getElementById('currency');
        if (currencyInput && country.currency && !currencyInput.value && typeof setCurrencyValue === 'function') {
            setCurrencyValue('currency', country.currency, countries);
        }

        if (country.latitude && country.longitude) {
            const lat = parseFloat(country.latitude);
            const lng = parseFloat(country.longitude);
            if (!isNaN(lat) && !isNaN(lng)) {
                const latInput = document.getElementById('gps_lat');
                const lngInput = document.getElementById('gps_long');
                if (latInput && !latInput.value) latInput.value = lat.toFixed(7);
                if (lngInput && !lngInput.value) lngInput.value = lng.toFixed(7);
            }
        }
    }

    function initializeMap() {
        const mapElement = document.getElementById('modalClubMap');
        if (!mapElement) return;

        const lat = parseFloat(document.getElementById('gps_lat')?.value) || 26.0667;
        const lng = parseFloat(document.getElementById('gps_long')?.value) || 50.5577;

        clubMap = L.map('modalClubMap', {
            attributionControl: false
        }).setView([lat, lng], 13);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '',
            maxZoom: 19
        }).addTo(clubMap);

        clubMarker = L.marker([lat, lng], {
            draggable: true
        }).addTo(clubMap);

        clubMarker.on('dragend', function(e) {
            const position = e.target.getLatLng();
            updateCoordinates(position.lat, position.lng);
        });

        const latInput = document.getElementById('gps_lat');
        const lngInput = document.getElementById('gps_long');

        if (latInput && lngInput) {
            latInput.addEventListener('change', updateMarkerPosition);
            lngInput.addEventListener('change', updateMarkerPosition);
        }

        setTimeout(() => {
            if (clubMap) clubMap.invalidateSize();
        }, 100);
    }

    function updateCoordinates(lat, lng) {
        const latInput = document.getElementById('gps_lat');
        const lngInput = document.getElementById('gps_long');
        if (latInput) latInput.value = lat.toFixed(7);
        if (lngInput) lngInput.value = lng.toFixed(7);
    }

    function updateMarkerPosition() {
        const lat = parseFloat(document.getElementById('gps_lat')?.value);
        const lng = parseFloat(document.getElementById('gps_long')?.value);

        if (!isNaN(lat) && !isNaN(lng) && clubMarker && clubMap) {
            const newPos = L.latLng(lat, lng);
            clubMarker.setLatLng(newPos);
            clubMap.setView(newPos, clubMap.getZoom());
        }
    }

    function setupLocationHandlers() {
        const countryInput = document.getElementById('country');
        if (countryInput) {
            const observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.type === 'attributes' && mutation.attributeName === 'value') {
                        handleCountryChange(countryInput.value);
                    }
                });
            });
            observer.observe(countryInput, { attributes: true });

            countryInput.addEventListener('change', function() {
                handleCountryChange(this.value);
            });

            let lastCountryValue = countryInput.value;
            setInterval(function() {
                if (countryInput.value !== lastCountryValue) {
                    lastCountryValue = countryInput.value;
                    handleCountryChange(countryInput.value);
                }
            }, 500);
        }

        const googleMapsInput = document.getElementById('google_maps_link');
        if (googleMapsInput) {
            googleMapsInput.addEventListener('change', function() {
                parseGoogleMapsLink(this.value);
            });
        }
    }

    function handleCountryChange(countryName) {
        if (!countriesData || !countryName) return;

        const country = countriesData.find(c =>
            c.name.toLowerCase() === countryName.toLowerCase() ||
            c.iso3 === countryName
        );

        if (!country) return;

        if (country.timezone && typeof setTimezoneValue === 'function') {
            setTimezoneValue('timezone', country.timezone, countriesData);
        }

        if (country.currency && typeof setCurrencyValue === 'function') {
            setCurrencyValue('currency', country.currency, countriesData);
        }

        if (country.latitude && country.longitude) {
            const lat = parseFloat(country.latitude);
            const lng = parseFloat(country.longitude);

            if (!isNaN(lat) && !isNaN(lng)) {
                const latInput = document.getElementById('gps_lat');
                const lngInput = document.getElementById('gps_long');
                const shouldUpdate = !latInput?.value || !lngInput?.value;

                if (shouldUpdate) {
                    updateCoordinates(lat, lng);
                }

                if (clubMarker && clubMap) {
                    if (shouldUpdate) {
                        clubMarker.setLatLng([lat, lng]);
                    }
                    clubMap.setView([lat, lng], 10);
                }
            }
        }
    }

    function getCurrentLocation() {
        if (!navigator.geolocation) {
            alert('Geolocation is not supported by your browser');
            return;
        }

        const btn = event.target.closest('button');
        const originalHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm mr-2"></span>Getting location...';

        navigator.geolocation.getCurrentPosition(
            function(position) {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;

                updateCoordinates(lat, lng);
                if (clubMarker && clubMap) {
                    clubMarker.setLatLng([lat, lng]);
                    clubMap.setView([lat, lng], 15);
                }

                btn.disabled = false;
                btn.innerHTML = originalHtml;

                if (typeof Toast !== 'undefined') {
                    Toast.success('Success', 'Location updated successfully');
                }
            },
            function(error) {
                console.error('Geolocation error:', error);
                alert('Unable to get your location. Please check your browser permissions.');
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        );
    }

    function centerOnCountry() {
        const countryInput = document.getElementById('country');
        if (!countryInput || !countriesData) return;

        const countryName = countryInput.value;
        const country = countriesData.find(c =>
            c.name.toLowerCase() === countryName.toLowerCase() ||
            c.iso3 === countryName
        );

        if (country && country.latitude && country.longitude) {
            const lat = parseFloat(country.latitude);
            const lng = parseFloat(country.longitude);

            if (!isNaN(lat) && !isNaN(lng) && clubMap) {
                clubMap.setView([lat, lng], 10);

                if (typeof Toast !== 'undefined') {
                    Toast.info('Info', `Centered on ${country.name}`);
                }
            }
        }
    }

    function parseGoogleMapsLink(url) {
        if (!url) return;

        try {
            let lat, lng;

            const atMatch = url.match(/@(-?\d+\.\d+),(-?\d+\.\d+)/);
            if (atMatch) {
                lat = parseFloat(atMatch[1]);
                lng = parseFloat(atMatch[2]);
            }

            if (!lat || !lng) {
                const dMatch = url.match(/!3d(-?\d+\.\d+)!4d(-?\d+\.\d+)/);
                if (dMatch) {
                    lat = parseFloat(dMatch[1]);
                    lng = parseFloat(dMatch[2]);
                }
            }

            if (!lat || !lng) {
                const llMatch = url.match(/ll=(-?\d+\.\d+),(-?\d+\.\d+)/);
                if (llMatch) {
                    lat = parseFloat(llMatch[1]);
                    lng = parseFloat(llMatch[2]);
                }
            }

            if (lat && lng && !isNaN(lat) && !isNaN(lng)) {
                updateCoordinates(lat, lng);
                if (clubMarker && clubMap) {
                    clubMarker.setLatLng([lat, lng]);
                    clubMap.setView([lat, lng], 15);
                }

                if (typeof Toast !== 'undefined') {
                    Toast.success('Success', 'Coordinates extracted from Google Maps link');
                }
            } else {
                if (typeof Toast !== 'undefined') {
                    Toast.warning('Warning', 'Could not extract coordinates from the link');
                }
            }
        } catch (error) {
            console.error('Error parsing Google Maps link:', error);
            if (typeof Toast !== 'undefined') {
                Toast.error('Error', 'Invalid Google Maps link');
            }
        }
    }
</script>
@endpush
