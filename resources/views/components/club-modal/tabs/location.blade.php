@props(['club' => null, 'mode' => 'create'])

@php
    $isEdit = $mode === 'edit' && $club;
@endphp

<div class="px-0">
    <h5 class="font-bold mb-3">{{ __('shared.modal_tabs_location_title') }}</h5>
    <p class="text-muted-foreground mb-4">{{ __('shared.modal_tabs_location_subtitle') }}</p>

    <!-- Country, Timezone, Currency Row -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
        <div>
            <x-fields.country
                name="country"
                id="country"
                label="Country"
                :value="$club->country ?? old('country', 'BH')"
                :required="true"
                :error="null" />
        </div>
        <div>
            <x-fields.timezone
                name="timezone"
                id="timezone"
                :value="$club->timezone ?? old('timezone', 'Asia/Bahrain')"
                :required="false"
                :error="null" />
        </div>
        <div>
            <x-fields.currency
                name="currency"
                id="currency"
                :value="$club->currency ?? old('currency', 'BHD')"
                :required="false"
                :error="null" />
        </div>
    </div>

    <!-- Location Map + the shared Google Maps link box (the pin follows a pasted link) -->
    <div class="mb-4">
        <x-fields.location-map
            id="clubLoc"
            :lat="$club->gps_lat ?? null"
            :lng="$club->gps_long ?? null"
            :address="$club->address ?? old('address', '')"
            :defaultLat="26.0667"
            :defaultLng="50.5577"
            mapsUrlName="maps_url"
            :mapsUrl="$club->maps_url ?? ''"
            height="min(400px, 45vh)"
        />
    </div>

    <!-- Quick Location Actions -->
    <div class="flex gap-2 flex-wrap">
        <button type="button" class="btn btn-outline-primary btn-sm" onclick="getCurrentLocation(this)">
            <i class="bi bi-crosshair me-2"></i>{{ __('shared.modal_tabs_location_use_current') }}
        </button>
        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="centerOnCountry()">
            <i class="bi bi-globe me-2"></i>{{ __('shared.modal_tabs_location_center_country') }}
        </button>
    </div>
</div>

@push('scripts')
<script>
    const CLUB_MAP_ID = 'clubLoc';
    let countriesData = null;

    function getClubMapInstance() {
        return window.LocationMap ? window.LocationMap.get(CLUB_MAP_ID) : null;
    }

    function clubCountryRecord(code) {
        if (!countriesData || !code) return null;
        const c = String(code).toLowerCase();
        return countriesData.find(x => (x.iso2 || '').toLowerCase() === c || (x.iso3 || '').toLowerCase() === c || (x.name || '').toLowerCase() === c) || null;
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Country list, for centring the map on a country. Nothing here asks the
        // device for its location — only the "Use current location" button does.
        if (!countriesData) {
            fetch('/data/countries.json')
                .then(response => response.json())
                .then(countries => { countriesData = countries; })
                .catch(error => console.error('Error loading countries:', error));
        }

        // Initialize map when location tab becomes visible
        function initClubMap() {
            const mapEl = document.getElementById('clubLocMap');
            if (mapEl && mapEl.offsetParent !== null && window.LocationMap) {
                LocationMap.init(CLUB_MAP_ID, 26.0667, 50.5577);
                LocationMap.refresh(CLUB_MAP_ID);
            }
        }

        // Watch for Alpine x-show toggling the location tab panel
        const mapContainer = document.getElementById('clubLocContainer');
        if (mapContainer) {
            const tabPanel = mapContainer.closest('[x-show]');
            if (tabPanel && !tabPanel.__clubMapObserved) {
                tabPanel.__clubMapObserved = true;
                new MutationObserver(initClubMap)
                    .observe(tabPanel, { attributes: true, attributeFilter: ['style'] });
            }
        }

        // The country picker announces a pick on `country-changed` (timezone and
        // currency follow it by themselves); the map centres on the country.
        // One listener per page, however many times this init re-runs.
        if (window.__clubLocCountryHandler) {
            window.removeEventListener('country-changed', window.__clubLocCountryHandler);
        }
        window.__clubLocCountryHandler = function (e) { handleCountryChange(e.detail && e.detail.iso2); };
        window.addEventListener('country-changed', window.__clubLocCountryHandler);
    });

    function handleCountryChange(code) {
        if (!document.getElementById('clubLocContainer')) return;
        const country = clubCountryRecord(code);
        if (!country || !country.latitude || !country.longitude) return;

        const lat = parseFloat(country.latitude);
        const lng = parseFloat(country.longitude);
        if (isNaN(lat) || isNaN(lng)) return;

        const latInput = document.getElementById('clubLocLat');
        const lngInput = document.getElementById('clubLocLng');
        if (!latInput?.value || !lngInput?.value) {
            if (window.LocationMap) LocationMap.setPosition(CLUB_MAP_ID, lat, lng);
        } else {
            const inst = getClubMapInstance();
            if (inst) inst.map.setView([lat, lng], 10);
        }
    }

    function getCurrentLocation(btn) {
        if (!navigator.geolocation) {
            window.showToast('error', '{{ __("shared.modal_tabs_location_geo_unsupported") }}');
            return;
        }

        const originalHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>{{ __("shared.modal_tabs_location_getting") }}';

        navigator.geolocation.getCurrentPosition(
            function(position) {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;

                LocationMap.setPosition(CLUB_MAP_ID, lat, lng);
                const inst = getClubMapInstance();
                if (inst) inst.map.setView([lat, lng], 15);

                btn.disabled = false;
                btn.innerHTML = originalHtml;
                window.showToast('success', '{{ __("shared.modal_tabs_location_updated") }}');
            },
            function(error) {
                console.error('Geolocation error:', error);
                window.showToast('error', '{{ __("shared.modal_tabs_location_geo_error") }}');
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        );
    }

    function centerOnCountry() {
        const countryInput = document.getElementById('country');
        const country = clubCountryRecord(countryInput && countryInput.value);
        if (!country || !country.latitude || !country.longitude) return;

        const lat = parseFloat(country.latitude);
        const lng = parseFloat(country.longitude);
        const inst = getClubMapInstance();
        if (!isNaN(lat) && !isNaN(lng) && inst) {
            inst.map.setView([lat, lng], 10);
            window.showToast('info', '{{ __("shared.modal_tabs_location_centered_on") }}'.replace(':name', country.name));
        }
    }
</script>
@endpush
