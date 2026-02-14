@props(['club' => null, 'mode' => 'create'])

@php
    $isEdit = $mode === 'edit' && $club;
@endphp

<div class="px-0">
    <h5 class="font-bold mb-3">Location</h5>
    <p class="text-muted-foreground mb-4">Set your club's geographic location and regional settings</p>

    <!-- Country, Timezone, Currency Row -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
        <div>
            <x-country-dropdown
                name="country"
                id="country"
                label="Country"
                :value="$club->country ?? old('country', 'BH')"
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

    <!-- Location Map -->
    <div class="mb-4">
        <x-location-map
            id="clubLoc"
            :lat="$club->gps_lat ?? null"
            :lng="$club->gps_long ?? null"
            :address="$club->address ?? old('address', '')"
            :defaultLat="26.0667"
            :defaultLng="50.5577"
            height="400px"
        />
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
    const CLUB_MAP_ID = 'clubLoc';
    let countriesData = null;

    function getClubMapInstance() {
        return window.LocationMap ? window.LocationMap['_locationMap_' + CLUB_MAP_ID] : null;
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Load countries data
        fetch('/data/countries.json')
            .then(response => response.json())
            .then(countries => {
                countriesData = countries;

                const clubForm = document.getElementById('clubForm');
                if (clubForm && clubForm.dataset.mode === 'create') {
                    detectAndPreselectCountries(countries);
                }

                setupLocationHandlers();
            })
            .catch(error => console.error('Error loading countries:', error));

        // Initialize map when location tab becomes visible
        function initClubMap() {
            const mapEl = document.getElementById('clubLocMap');
            if (mapEl && mapEl.offsetParent !== null) {
                LocationMap.init(CLUB_MAP_ID, 26.0667, 50.5577);
                LocationMap.refresh(CLUB_MAP_ID);
            }
        }

        // Watch for Alpine x-show toggling the location tab panel
        const mapContainer = document.getElementById('clubLocContainer');
        if (mapContainer) {
            const tabPanel = mapContainer.closest('[x-show]');
            if (tabPanel) {
                new MutationObserver(initClubMap)
                    .observe(tabPanel, { attributes: true, attributeFilter: ['style'] });
            }
        }

        // Also try on modal shown event (Bootstrap Bridge fires this)
        const clubModal = document.getElementById('clubModal');
        if (clubModal) {
            clubModal.addEventListener('shown.bs.modal', function() {
                setTimeout(initClubMap, 150);
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
                const latInput = document.getElementById('clubLocLat');
                const lngInput = document.getElementById('clubLocLng');
                if (latInput && !latInput.value) latInput.value = lat.toFixed(6);
                if (lngInput && !lngInput.value) lngInput.value = lng.toFixed(6);
                LocationMap.setPosition(CLUB_MAP_ID, lat, lng);
            }
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
                const latInput = document.getElementById('clubLocLat');
                const lngInput = document.getElementById('clubLocLng');
                const shouldUpdate = !latInput?.value || !lngInput?.value;

                if (shouldUpdate) {
                    LocationMap.setPosition(CLUB_MAP_ID, lat, lng);
                } else {
                    const inst = getClubMapInstance();
                    if (inst) inst.map.setView([lat, lng], 10);
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

                LocationMap.setPosition(CLUB_MAP_ID, lat, lng);
                const inst = getClubMapInstance();
                if (inst) inst.map.setView([lat, lng], 15);

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

            const inst = getClubMapInstance();
            if (!isNaN(lat) && !isNaN(lng) && inst) {
                inst.map.setView([lat, lng], 10);

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
                LocationMap.setPosition(CLUB_MAP_ID, lat, lng);
                const inst = getClubMapInstance();
                if (inst) inst.map.setView([lat, lng], 15);

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
