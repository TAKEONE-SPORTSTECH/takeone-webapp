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
    'readonly' => false,
    'showAddress' => true,
    'showCoords' => true,
    'showLabels' => true,
    'addressPlaceholder' => 'Enter address and press Enter to search',
    'mapClass' => 'rounded-lg overflow-hidden border border-border bg-muted/30',
    // Opt-in: a "Google Maps link" box. Pasting a link moves the pin to it
    // (read from the link, or — for a short maps.app.goo.gl link — through
    // me.geo.map-link, which only ever follows Google). Absent, nothing changes.
    'mapsUrlName' => null,
    'mapsUrl' => '',
])

@php
    $mapId = $id . 'Map';
    $latId = $id . 'Lat';
    $lngId = $id . 'Lng';
    $addressId = $id . 'Address';
    $linkId = $id . 'MapsUrl';
    $hasPin = is_numeric($lat) && is_numeric($lng);
@endphp

{{-- ===== The map keeps its z-indexes to itself =====

     Leaflet paints its own layers with z-index 400 (panes) up to 1200
     (popups), and those numbers are absolute — nothing about being inside a
     card contains them. With no stacking context of its own the map therefore
     competed with the PAGE, and won: open any bottom sheet (`z-[60]`) and
     scroll, and the tiles rode up over it, on the member page and the public
     one alike.

     `isolation: isolate` gives the map element its own stacking context, so
     everything Leaflet does inside it is painted as one z-0 box. The internal
     ordering — tiles under markers under controls — is untouched, because it
     is all relative to this element now.

     Once, here, rather than at each of the places a map is embedded: a
     component owns its own behaviour (CLAUDE.md → Standalone Components). --}}
@once
    @push('styles')
        <style>
            .leaflet-container { isolation: isolate; z-index: 0; }
        </style>
    @endpush
@endonce

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
                   placeholder="{{ $addressPlaceholder }}"
                   class="form-control">
        </div>
    @else
        <input type="hidden" id="{{ $addressId }}" name="{{ $addressName }}" value="{{ $address }}">
    @endif

    {{-- Google Maps link (opt-in) — the pin follows it --}}
    @if($mapsUrlName)
        <div class="space-y-2">
            <label for="{{ $linkId }}" class="block text-sm font-medium text-foreground">{{ __('shared.map_link_label') }}</label>
            <input type="url" id="{{ $linkId }}" name="{{ $mapsUrlName }}" value="{{ $mapsUrl }}" maxlength="500"
                   data-map-link-for="{{ $id }}" placeholder="https://maps.app.goo.gl/…" class="form-control" autocomplete="off">
            <p class="text-xs text-muted-foreground" id="{{ $linkId }}Hint" data-map-link-hint="{{ $id }}">{{ __('shared.map_link_help') }}</p>
        </div>
    @endif

    {{-- Map --}}
    <div class="space-y-2">
        @if($showLabels)
            <label class="block text-sm font-medium text-foreground">
                Location on Map <span class="text-xs text-muted-foreground">(Click or drag marker)</span>
            </label>
        @endif
        <div id="{{ $mapId }}" class="{{ $mapClass }}" style="height: {{ $height }}"></div>
        @if($mapsUrlName)
            {{-- The same place, opened in either map. Hrefs follow the pin. --}}
            <div class="flex flex-wrap gap-2" data-map-open-for="{{ $id }}" @if(! $hasPin) style="display:none" @endif>
                <a data-map-open="google" target="_blank" rel="noopener noreferrer"
                   href="{{ $hasPin ? 'https://www.google.com/maps?q='.(float) $lat.','.(float) $lng : '#' }}"
                   class="px-3 py-1.5 rounded-full text-xs font-medium border border-primary text-primary hover:bg-primary hover:text-white transition-colors inline-flex items-center gap-1.5">
                    <i class="bi bi-google"></i>{{ __('shared.map_link_open_google') }}
                </a>
                <a data-map-open="osm" target="_blank" rel="noopener noreferrer"
                   href="{{ $hasPin ? 'https://www.openstreetmap.org/?mlat='.(float) $lat.'&mlon='.(float) $lng.'#map=17/'.(float) $lat.'/'.(float) $lng : '#' }}"
                   class="px-3 py-1.5 rounded-full text-xs font-medium border border-primary text-primary hover:bg-primary hover:text-white transition-colors inline-flex items-center gap-1.5">
                    <i class="bi bi-map"></i>{{ __('shared.map_link_open_osm') }}
                </a>
            </div>
        @endif
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

{{-- Shared runtime (defines window.LocationMap; @once-guarded). --}}
@include('components.fields.support.location-map-runtime')

@if($mapsUrlName)
@once
@push('scripts')
<script>
/* The Google Maps link box: delegated off document, so it works however the
   page arrived (admin shell swap, teleport) and binds once per session. */
(function () {
    if (window.__tkMapLink) return; window.__tkMapLink = true;
    const RESOLVE = @json(route('me.geo.map-link'));
    const SAID = { found: @json(__('shared.map_link_found')), none: @json(__('shared.map_link_not_found')) };
    const read = (u) => {
        const s = decodeURIComponent(String(u || ''));
        const res = [/!3d(-?\d{1,2}(?:\.\d+)?)!4d(-?\d{1,3}(?:\.\d+)?)/, /@(-?\d{1,2}(?:\.\d+)?),(-?\d{1,3}(?:\.\d+)?)/,
                     /[?&](?:q|query|ll|destination|center)=(-?\d{1,2}(?:\.\d+)?),\s*(-?\d{1,3}(?:\.\d+)?)/];
        for (const re of res) { const m = s.match(re); if (m) { const la = +m[1], ln = +m[2];
            if (la >= -90 && la <= 90 && ln >= -180 && ln <= 180 && (la || ln)) return { lat: la, lng: ln }; } }
        return null;
    };
    const hint = (id, text, ok) => { const h = document.querySelector('[data-map-link-hint="' + id + '"]');
        if (h) { h.textContent = text; h.style.color = ok === false ? '#b4483c' : ok ? '#2f7a4f' : ''; } };
    const openLinks = (id, lat, lng) => {
        const box = document.querySelector('[data-map-open-for="' + id + '"]'); if (!box) return;
        const num = (x) => (x === '' || x == null) ? NaN : Number(x);
        const la = num(lat), ln = num(lng);
        if (!isFinite(la) || !isFinite(ln)) { box.style.display = 'none'; return; }
        box.querySelector('[data-map-open="google"]').href = 'https://www.google.com/maps?q=' + la + ',' + ln;
        box.querySelector('[data-map-open="osm"]').href = 'https://www.openstreetmap.org/?mlat=' + la + '&mlon=' + ln + '#map=17/' + la + '/' + ln;
        box.style.display = '';
    };
    const place = (id, at) => {
        const latI = document.getElementById(id + 'Lat'), lngI = document.getElementById(id + 'Lng');
        if (latI) latI.value = at.lat.toFixed(6); if (lngI) lngI.value = at.lng.toFixed(6);
        if (window.LocationMap) window.LocationMap.setPosition(id, at.lat, at.lng);
        openLinks(id, at.lat, at.lng); hint(id, SAID.found, true);
    };
    let seq = 0;
    const follow = (input) => {
        const id = input.getAttribute('data-map-link-for'), url = input.value.trim();
        if (!url) return;
        const at = read(url); if (at) return place(id, at);
        const mine = ++seq;
        fetch(RESOLVE + '?url=' + encodeURIComponent(url), { credentials: 'same-origin', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.ok ? r.json() : null)
            .then(j => { if (mine !== seq) return; if (j && j.lat != null && j.lng != null) place(id, { lat: +j.lat, lng: +j.lng }); else hint(id, SAID.none, false); })
            .catch(() => { if (mine === seq) hint(id, SAID.none, false); });
    };
    document.addEventListener('change', (e) => { const t = e.target; if (t && t.matches && t.matches('[data-map-link-for]')) follow(t); });
    document.addEventListener('paste', (e) => { const t = e.target; if (t && t.matches && t.matches('[data-map-link-for]')) setTimeout(() => follow(t), 0); });
    // The "open in" links follow the pin however it moved: a drag, a click, an address search, typed coordinates.
    document.addEventListener('location-changed', (e) => { const el = e.target; if (!el || !el.id || !/Map$/.test(el.id)) return;
        openLinks(el.id.slice(0, -3), e.detail.lat, e.detail.lng); });
    document.addEventListener('change', (e) => { const t = e.target; if (!t || !t.id || !/(Lat|Lng)$/.test(t.id)) return;
        const id = t.id.slice(0, -3); if (!document.querySelector('[data-map-open-for="' + id + '"]')) return;
        openLinks(id, (document.getElementById(id + 'Lat') || {}).value, (document.getElementById(id + 'Lng') || {}).value); });
})();
</script>
@endpush
@endonce
@endif

{{-- ─────────────────── Per-instance auto-initialisation ─────────────────── --}}
@push('scripts')
<script>
    /* The runtime is pushed once onto the same stack, but a page can push
       this instance ABOVE it (the public event page did, and threw "reading
       'create'" on every load). So never assume it is there yet: create now if
       it is, otherwise once the document has parsed every inline script, with
       a short retry for a page already past that point. */
    (function (opts) {
        let tries = 0;
        function boot() {
            if (window.LocationMap) { window.LocationMap.create(opts); return; }
            if (++tries <= 20) setTimeout(boot, 50);
        }
        if (window.LocationMap) { window.LocationMap.create(opts); }
        else if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot, { once: true }); }
        else { boot(); }
    })({
        id: @json($id),
        defaultLat: {{ $defaultLat }},
        defaultLng: {{ $defaultLng }},
        zoom: {{ (int) $zoom }},
        draggable: {{ $draggable ? 'true' : 'false' }},
        readonly: {{ $readonly ? 'true' : 'false' }},
    });
</script>
@endpush
