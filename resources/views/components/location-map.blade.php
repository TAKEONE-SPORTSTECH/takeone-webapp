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

{{-- Shared runtime (defines window.LocationMap; @once-guarded). --}}
@include('partials.location-map-runtime')

{{-- ─────────────────── Per-instance auto-initialisation ─────────────────── --}}
@push('scripts')
<script>
    window.LocationMap.create({
        id: @json($id),
        defaultLat: {{ $defaultLat }},
        defaultLng: {{ $defaultLng }},
        zoom: {{ (int) $zoom }},
        draggable: {{ $draggable ? 'true' : 'false' }},
        readonly: {{ $readonly ? 'true' : 'false' }},
    });
</script>
@endpush
