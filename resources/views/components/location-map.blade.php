@props([
    'id' => 'locationMap',
    'latName' => 'gps_lat',
    'lngName' => 'gps_long',
    'addressName' => 'address',
    'lat' => null,
    'lng' => null,
    'address' => '',
    'defaultLat' => 25.2048,
    'defaultLng' => 55.2708,
    'height' => '16rem',
    'required' => false,
])

@php
    $mapId = $id . 'Map';
    $latId = $id . 'Lat';
    $lngId = $id . 'Lng';
    $addressId = $id . 'Address';
@endphp

<div class="space-y-4" id="{{ $id }}Container">
    <!-- Address Search -->
    <div class="space-y-2">
        <label for="{{ $addressId }}" class="block text-sm font-medium text-foreground">
            Address @if($required)<span class="text-destructive">*</span>@endif
        </label>
        <input type="text"
               id="{{ $addressId }}"
               name="{{ $addressName }}"
               value="{{ $address }}"
               @if($required) required @endif
               placeholder="Enter address and press Enter to search"
               class="form-control">
    </div>

    <!-- Map -->
    <div class="space-y-2">
        <label class="block text-sm font-medium text-foreground">
            Location on Map <span class="text-xs text-muted-foreground">(Click or drag marker)</span>
        </label>
        <div id="{{ $mapId }}" class="rounded-lg overflow-hidden border border-border bg-muted/30" style="height: {{ $height }}"></div>
    </div>

    <!-- Lat/Lng -->
    <div class="grid grid-cols-2 gap-4">
        <div class="space-y-2">
            <label for="{{ $latId }}" class="block text-xs font-medium text-muted-foreground">
                <i class="bi bi-geo mr-1"></i>Latitude
            </label>
            <input type="number"
                   id="{{ $latId }}"
                   name="{{ $latName }}"
                   value="{{ $lat }}"
                   step="any"
                   placeholder="{{ $defaultLat }}"
                   class="form-control text-sm">
        </div>
        <div class="space-y-2">
            <label for="{{ $lngId }}" class="block text-xs font-medium text-muted-foreground">
                <i class="bi bi-geo mr-1"></i>Longitude
            </label>
            <input type="number"
                   id="{{ $lngId }}"
                   name="{{ $lngName }}"
                   value="{{ $lng }}"
                   step="any"
                   placeholder="{{ $defaultLng }}"
                   class="form-control text-sm">
        </div>
    </div>
</div>

@once
@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
@endpush
@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
window.LocationMap = window.LocationMap || {};

window.LocationMap.init = function(id, defaultLat, defaultLng) {
    const mapId = id + 'Map';
    const latId = id + 'Lat';
    const lngId = id + 'Lng';
    const addressId = id + 'Address';
    const storeKey = '_locationMap_' + id;

    // Avoid double-init
    if (window.LocationMap[storeKey]) return window.LocationMap[storeKey];

    const latInput = document.getElementById(latId);
    const lngInput = document.getElementById(lngId);
    const addressInput = document.getElementById(addressId);
    const mapEl = document.getElementById(mapId);

    if (!mapEl) return null;

    const lat = parseFloat(latInput?.value) || defaultLat;
    const lng = parseFloat(lngInput?.value) || defaultLng;

    const map = L.map(mapId).setView([lat, lng], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap'
    }).addTo(map);

    const marker = L.marker([lat, lng], { draggable: true }).addTo(map);

    function updateInputs(lat, lng) {
        if (latInput) latInput.value = lat.toFixed(6);
        if (lngInput) lngInput.value = lng.toFixed(6);
    }

    marker.on('dragend', function(e) {
        const pos = e.target.getLatLng();
        updateInputs(pos.lat, pos.lng);
    });

    map.on('click', function(e) {
        marker.setLatLng(e.latlng);
        updateInputs(e.latlng.lat, e.latlng.lng);
    });

    // Lat/lng input changes
    function onInputChange() {
        const la = parseFloat(latInput?.value);
        const ln = parseFloat(lngInput?.value);
        if (!isNaN(la) && !isNaN(ln)) {
            marker.setLatLng([la, ln]);
            map.setView([la, ln]);
        }
    }
    latInput?.addEventListener('change', onInputChange);
    lngInput?.addEventListener('change', onInputChange);

    // Address search on Enter
    function searchAddress() {
        const q = addressInput?.value.trim();
        if (!q) return;

        addressInput.disabled = true;

        fetch('https://nominatim.openstreetmap.org/search?format=json&q=' + encodeURIComponent(q))
            .then(r => r.json())
            .then(data => {
                if (data && data.length > 0) {
                    const la = parseFloat(data[0].lat);
                    const ln = parseFloat(data[0].lon);
                    updateInputs(la, ln);
                    marker.setLatLng([la, ln]);
                    map.setView([la, ln], 15);
                }
            })
            .catch(() => {})
            .finally(() => {
                addressInput.disabled = false;
                addressInput.focus();
            });
    }

    addressInput?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') { e.preventDefault(); searchAddress(); }
    });

    setTimeout(() => map.invalidateSize(), 100);

    const instance = { map, marker, updateInputs };
    window.LocationMap[storeKey] = instance;
    return instance;
};

// Re-init helper: update existing map position (for edit modals)
window.LocationMap.setPosition = function(id, lat, lng) {
    const storeKey = '_locationMap_' + id;
    const inst = window.LocationMap[storeKey];
    if (inst) {
        inst.marker.setLatLng([lat, lng]);
        inst.map.setView([lat, lng], 13);
        inst.updateInputs(lat, lng);
    }
};

// Invalidate size helper (for maps inside modals)
window.LocationMap.refresh = function(id) {
    const storeKey = '_locationMap_' + id;
    const inst = window.LocationMap[storeKey];
    if (inst) {
        setTimeout(() => inst.map.invalidateSize(), 100);
    }
};
</script>
@endpush
@endonce
