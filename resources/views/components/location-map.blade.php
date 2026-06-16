@props([
    'id' => 'locationMap_' . uniqid(),
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
    'zoom' => 13,
    'draggable' => true,
    'showAddress' => true,
    'showCoords' => true,
    'showLabels' => true,
    'mapClass' => 'rounded-lg overflow-hidden border border-border bg-muted/30',
])

@php
    $mapId = $id . 'Map';
    $latId = $id . 'Lat';
    $lngId = $id . 'Lng';
    $addressId = $id . 'Address';
@endphp

<div class="space-y-4" id="{{ $id }}Container">
    {{-- Address search --}}
    @if($showAddress)
        <div class="space-y-2">
            @if($showLabels)
                <label for="{{ $addressId }}" class="block text-sm font-medium text-foreground">
                    Address @if($required)<span class="text-destructive">*</span>@endif
                </label>
            @endif
            <input type="text"
                   id="{{ $addressId }}"
                   name="{{ $addressName }}"
                   value="{{ $address }}"
                   @if($required) required @endif
                   placeholder="Enter address and press Enter to search"
                   class="form-control">
        </div>
    @else
        <input type="hidden" id="{{ $addressId }}" name="{{ $addressName }}" value="{{ $address }}">
    @endif

    {{-- Map --}}
    <div class="space-y-2">
        @if($showLabels)
            <label class="block text-sm font-medium text-foreground">
                Location on Map <span class="text-xs text-muted-foreground">(Click or drag marker)</span>
            </label>
        @endif
        <div id="{{ $mapId }}" class="{{ $mapClass }}" style="height: {{ $height }}"></div>
    </div>

    {{-- Latitude / Longitude --}}
    @if($showCoords)
        <div class="grid grid-cols-2 gap-4">
            <div class="space-y-2">
                <label for="{{ $latId }}" class="block text-xs font-medium text-muted-foreground">
                    <i class="bi bi-geo mr-1"></i>Latitude
                </label>
                <input type="number" id="{{ $latId }}" name="{{ $latName }}" value="{{ $lat }}"
                       step="any" placeholder="{{ $defaultLat }}" class="form-control text-sm">
            </div>
            <div class="space-y-2">
                <label for="{{ $lngId }}" class="block text-xs font-medium text-muted-foreground">
                    <i class="bi bi-geo mr-1"></i>Longitude
                </label>
                <input type="number" id="{{ $lngId }}" name="{{ $lngName }}" value="{{ $lng }}"
                       step="any" placeholder="{{ $defaultLng }}" class="form-control text-sm">
            </div>
        </div>
    @else
        <input type="hidden" id="{{ $latId }}" name="{{ $latName }}" value="{{ $lat }}">
        <input type="hidden" id="{{ $lngId }}" name="{{ $lngName }}" value="{{ $lng }}">
    @endif
</div>

{{-- ───────────────────── Shared library (loaded once) ───────────────────── --}}
@once
@push('scripts')
<script>
window.LocationMap = (function () {
    const LEAFLET_VERSION = '1.9.4';
    const BASE = 'https://unpkg.com/leaflet@' + LEAFLET_VERSION + '/dist/';
    const instances = {};
    const pending = {};          // positions set before a map is built
    let leafletPromise = null;

    /** Inject Leaflet CSS + JS once, resolve when window.L is ready. */
    function ensureLeaflet() {
        if (window.L) return Promise.resolve();
        if (leafletPromise) return leafletPromise;

        leafletPromise = new Promise(function (resolve, reject) {
            if (!document.querySelector('link[data-leaflet]')) {
                const link = document.createElement('link');
                link.rel = 'stylesheet';
                link.href = BASE + 'leaflet.css';
                link.setAttribute('data-leaflet', '');
                document.head.appendChild(link);
            }
            let script = document.querySelector('script[data-leaflet]');
            if (script && window.L) { resolve(); return; }
            if (!script) {
                script = document.createElement('script');
                script.src = BASE + 'leaflet.js';
                script.setAttribute('data-leaflet', '');
                document.head.appendChild(script);
            }
            script.addEventListener('load', function () { resolve(); });
            script.addEventListener('error', function () { reject(new Error('Leaflet failed to load')); });
            // In case it was already loaded between checks.
            if (window.L) resolve();
        });
        return leafletPromise;
    }

    /** Run cb once the element actually has layout (handles hidden tabs/modals). */
    function whenVisible(el, cb) {
        if (!el) return;
        const ready = function () { return el.clientWidth > 0 && el.clientHeight > 0 && el.offsetParent !== null; };
        if (ready()) { cb(); return; }
        if ('IntersectionObserver' in window) {
            const io = new IntersectionObserver(function (entries, obs) {
                if (ready()) { obs.disconnect(); cb(); }
            });
            io.observe(el);
        }
        // Fallback: poll briefly in case IO doesn't fire (e.g. shown without intersecting).
        let tries = 0;
        const timer = setInterval(function () {
            if (ready()) { clearInterval(timer); cb(); }
            else if (++tries > 40) { clearInterval(timer); } // ~6s
        }, 150);
    }

    function markerIcon() {
        return window.L.icon({
            iconUrl: BASE + 'images/marker-icon.png',
            iconRetinaUrl: BASE + 'images/marker-icon-2x.png',
            shadowUrl: BASE + 'images/marker-shadow.png',
            iconSize: [25, 41], iconAnchor: [12, 41], popupAnchor: [1, -34], shadowSize: [41, 41],
        });
    }

    function build(cfg) {
        if (instances[cfg.id]) return instances[cfg.id];
        const L = window.L;
        const mapEl = document.getElementById(cfg.id + 'Map');
        if (!mapEl) return null;

        const latInput = document.getElementById(cfg.id + 'Lat');
        const lngInput = document.getElementById(cfg.id + 'Lng');
        const addressInput = document.getElementById(cfg.id + 'Address');

        const start = pending[cfg.id] || {};
        const lat = start.lat ?? (parseFloat(latInput && latInput.value) || cfg.defaultLat);
        const lng = start.lng ?? (parseFloat(lngInput && lngInput.value) || cfg.defaultLng);

        const map = L.map(mapEl).setView([lat, lng], cfg.zoom);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap',
            maxZoom: 19,
        }).addTo(map);

        const marker = L.marker([lat, lng], { draggable: cfg.draggable, icon: markerIcon() }).addTo(map);

        function updateInputs(la, ln) {
            if (latInput) latInput.value = la.toFixed(6);
            if (lngInput) lngInput.value = ln.toFixed(6);
            mapEl.dispatchEvent(new CustomEvent('location-changed', { bubbles: true, detail: { lat: la, lng: ln } }));
        }

        if (cfg.draggable) {
            marker.on('dragend', function (e) {
                const p = e.target.getLatLng();
                updateInputs(p.lat, p.lng);
            });
            map.on('click', function (e) {
                marker.setLatLng(e.latlng);
                updateInputs(e.latlng.lat, e.latlng.lng);
            });
        }

        function onInputChange() {
            const la = parseFloat(latInput && latInput.value);
            const ln = parseFloat(lngInput && lngInput.value);
            if (!isNaN(la) && !isNaN(ln)) { marker.setLatLng([la, ln]); map.setView([la, ln]); }
        }
        latInput && latInput.addEventListener('change', onInputChange);
        lngInput && lngInput.addEventListener('change', onInputChange);

        function searchAddress() {
            const q = addressInput && addressInput.value.trim();
            if (!q) return;
            addressInput.disabled = true;
            fetch('https://nominatim.openstreetmap.org/search?format=json&q=' + encodeURIComponent(q))
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data && data.length) {
                        const la = parseFloat(data[0].lat), ln = parseFloat(data[0].lon);
                        updateInputs(la, ln);
                        marker.setLatLng([la, ln]);
                        map.setView([la, ln], 15);
                    }
                })
                .catch(function () {})
                .finally(function () { addressInput.disabled = false; addressInput.focus(); });
        }
        addressInput && addressInput.addEventListener('keypress', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); searchAddress(); }
        });

        // Keep the map sized correctly when its container resizes or is revealed.
        if ('ResizeObserver' in window) {
            new ResizeObserver(function () { map.invalidateSize(); }).observe(mapEl);
        }
        setTimeout(function () { map.invalidateSize(); }, 60);

        const instance = { map: map, marker: marker, updateInputs: updateInputs, cfg: cfg };
        instances[cfg.id] = instance;
        delete pending[cfg.id];
        return instance;
    }

    /** Public: create (or no-op if already created). Self-loads Leaflet + waits for visibility. */
    function create(cfg) {
        cfg = Object.assign({ defaultLat: 25.2048, defaultLng: 55.2708, zoom: 13, draggable: true }, cfg || {});
        if (!cfg.id || instances[cfg.id]) return;
        ensureLeaflet()
            .then(function () { whenVisible(document.getElementById(cfg.id + 'Map'), function () { build(cfg); }); })
            .catch(function (e) { if (window.console) console.error('[LocationMap]', e); });
    }

    return {
        create: create,
        // Back-compat: explicit init from a page.
        init: function (id, defaultLat, defaultLng) {
            create({ id: id, defaultLat: defaultLat, defaultLng: defaultLng });
            return instances[id] || null;
        },
        setPosition: function (id, lat, lng) {
            const inst = instances[id];
            if (inst) {
                inst.marker.setLatLng([lat, lng]);
                inst.map.setView([lat, lng], inst.cfg.zoom || 13);
                inst.updateInputs(lat, lng);
            } else {
                pending[id] = { lat: lat, lng: lng }; // applied when the map builds
            }
        },
        refresh: function (id) {
            const inst = instances[id];
            if (inst) setTimeout(function () { inst.map.invalidateSize(); }, 60);
        },
        get: function (id) { return instances[id] || null; },
    };
})();
</script>
@endpush
@endonce

{{-- ─────────────────── Per-instance auto-initialisation ─────────────────── --}}
@push('scripts')
<script>
    window.LocationMap.create({
        id: @json($id),
        defaultLat: {{ $defaultLat }},
        defaultLng: {{ $defaultLng }},
        zoom: {{ (int) $zoom }},
        draggable: {{ $draggable ? 'true' : 'false' }},
    });
</script>
@endpush
