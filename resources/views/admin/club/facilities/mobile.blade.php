@extends('layouts.admin-club-mobile')

@section('title', ($club->club_name ?? __('admin.club')) . ' · ' . __('admin.nav_facilities'))

@section('club-admin-content')
<div class="-mx-4 -mt-4"
     x-data="{ removeFacilityId: null, removeFacilityName: '' }"
     @facility-removed.window="removeFacilityId = null; removeFacilityName = ''">

    {{-- ===== Hero ===== --}}
    <header class="m-hero px-5 pt-7 pb-6 text-white relative overflow-hidden">
        <div class="absolute -end-8 -top-8 w-36 h-36 rounded-full bg-white/10"></div>
        <div class="flex items-center justify-between relative z-10">
            <div class="min-w-0">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-white/70 truncate">{{ $club->club_name ?? __('admin.club') }}</p>
                <h1 class="text-2xl font-black mt-0.5">{{ __('admin.nav_facilities') }}</h1>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" @click="$dispatch('open-add-facility')"
                        class="m-press w-12 h-12 rounded-2xl bg-white/20 border border-white/30 backdrop-blur grid place-items-center active:scale-95 transition-transform" aria-label="{{ __('admin.fac_add') }}">
                    <i class="bi bi-plus-lg text-xl"></i>
                </button>
                <div class="w-12 h-12 rounded-2xl bg-white/15 border border-white/25 backdrop-blur grid place-items-center">
                    <i class="bi bi-geo-alt text-xl m-float"></i>
                </div>
            </div>
        </div>
    </header>

    <div class="px-4 pt-5">

    <div id="facilitiesList" class="space-y-4 mobile-stagger">
        @foreach($facilities as $f)
            @php $img = is_array($f->images ?? null) ? ($f->images[0] ?? null) : ($f->photo ?? null); @endphp
            <div class="m-card" id="facility-{{ $f->id }}" x-data="{ openMenu: false }">
                <div class="relative">
                    @if($img)<img src="{{ asset('storage/'.$img) }}" alt="" class="w-full h-32 object-cover rounded-t-2xl">@endif

                    {{-- Actions menu --}}
                    <div class="absolute top-2 right-2 z-10" @click.stop>
                        <button type="button" @click="openMenu = !openMenu"
                                class="m-press w-8 h-8 rounded-full bg-white/90 backdrop-blur flex items-center justify-center text-foreground shadow-sm"
                                aria-label="{{ __('admin.actions') }}">
                            <i class="bi bi-three-dots-vertical"></i>
                        </button>
                        <div x-show="openMenu" x-cloak @click.outside="openMenu = false"
                             x-transition:enter="transition ease-out duration-150"
                             x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
                             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                             x-transition:leave="transition ease-in duration-100"
                             x-transition:leave-start="opacity-100 scale-100"
                             x-transition:leave-end="opacity-0 scale-95"
                             class="absolute right-0 mt-2 w-44 bg-white rounded-xl shadow-xl border border-gray-100 overflow-hidden z-20">
                            <button type="button"
                                    class="w-full text-left px-4 py-3 text-sm text-foreground hover:bg-muted/60 flex items-center gap-3"
                                    @click="openMenu = false; $dispatch('open-edit-facility', { id: {{ $f->id }} })">
                                <span class="w-7 h-7 rounded-lg bg-blue-100 flex items-center justify-center shrink-0"><i class="bi bi-pencil text-blue-600 text-xs"></i></span>
                                <span class="font-medium">{{ __('admin.fac_edit') }}</span>
                            </button>
                            <button type="button"
                                    class="w-full text-left px-4 py-3 text-sm text-red-600 hover:bg-red-50 flex items-center gap-3"
                                    @click="openMenu = false; removeFacilityId = {{ $f->id }}; removeFacilityName = @js($f->name)">
                                <span class="w-7 h-7 rounded-lg bg-red-100 flex items-center justify-center shrink-0"><i class="bi bi-trash text-red-600 text-xs"></i></span>
                                <span class="font-medium">{{ __('admin.fac_delete') }}</span>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="p-4">
                    <div class="flex items-start justify-between gap-2 pr-9">
                        <h3 class="font-semibold text-foreground truncate">{{ $f->name }}</h3>
                        @if(isset($f->is_available))
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-medium flex-shrink-0 {{ $f->is_available ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">{{ $f->is_available ? __('admin.fac_available') : __('admin.fac_unavailable') }}</span>
                        @endif
                    </div>
                    @if($f->description)<p class="text-xs text-muted-foreground mt-1 line-clamp-2">{{ $f->description }}</p>@endif
                    @if($f->address)<p class="text-xs text-muted-foreground mt-2"><i class="bi bi-geo-alt mr-1"></i>{{ $f->address }}</p>@endif
                    @if($f->maps_url && \Illuminate\Support\Str::startsWith(strtolower($f->maps_url), ['http://', 'https://']))<a href="{{ $f->maps_url }}" target="_blank" rel="noopener" class="text-xs text-primary mt-1 inline-flex items-center gap-1"><i class="bi bi-map"></i>{{ __('admin.fac_maps_url') }}</a>@endif
                </div>
            </div>
        @endforeach
    </div>

    {{-- Empty state (shown when no facilities; hidden once the list has cards) --}}
    <div id="facilitiesEmpty" class="{{ $facilities->isEmpty() ? '' : 'hidden' }}">
        <div class="m-card p-8 text-center">
            <i class="bi bi-geo-alt text-3xl text-gray-300 m-float"></i>
            <p class="text-sm text-muted-foreground mt-2">{{ __('admin.fac_none_yet') }}</p>
            <button type="button" @click="$dispatch('open-add-facility')"
                    class="mt-4 inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-primary text-white text-sm font-semibold">
                <i class="bi bi-plus-lg"></i>{{ __('admin.fac_add') }}
            </button>
        </div>
    </div>

    {{-- Delete confirm (teleported to body to escape the transformed `.mobile-stagger` container) --}}
    <template x-teleport="body">
    <div x-show="removeFacilityId !== null" x-cloak class="fixed inset-0 z-[60] overflow-y-auto"
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
        <div class="fixed inset-0 bg-black/50" @click="removeFacilityId = null"></div>
        <div class="relative flex min-h-full items-center justify-center p-4 z-10">
            <div class="bg-white w-full max-w-sm relative rounded-2xl overflow-hidden shadow-xl" @click.stop>
                <div class="flex items-center justify-between border-b border-red-100 px-5 py-4">
                    <h5 class="text-destructive font-semibold flex items-center"><i class="bi bi-trash mr-2"></i>{{ __('admin.fac_delete') }}</h5>
                    <button type="button" class="text-muted-foreground hover:text-foreground" @click="removeFacilityId = null"><i class="bi bi-x-lg"></i></button>
                </div>
                <div class="px-5 py-4">
                    <p class="mb-1 text-sm">{{ __('admin.fac_delete_confirm') }}</p>
                    <p class="font-semibold" x-text="removeFacilityName"></p>
                </div>
                <div class="border-t px-5 py-4 flex justify-end gap-2">
                    <button type="button" class="px-4 py-2 text-sm font-medium rounded-xl border border-gray-200 bg-white" @click="removeFacilityId = null">{{ __('admin.cancel') }}</button>
                    <button type="button" class="px-4 py-2 text-sm font-medium rounded-xl bg-destructive text-white flex items-center gap-1" @click="removeFacility(removeFacilityId)">
                        <i class="bi bi-trash"></i>{{ __('admin.fac_delete') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
    </template>

    @include('admin.club.facilities.mobile-form')

    {{-- Edit payloads + delete handler. Inline (inside #shell-content) so it re-runs
         after an in-shell AJAX swap. --}}
    @php
        $facilitiesData = $facilities->mapWithKeys(fn ($f) => [$f->id => [
            'id'          => $f->id,
            'name'        => $f->name,
            'name_ar'     => data_get($f->translations, 'name.ar', ''),
            'description' => $f->description,
            'description_ar' => data_get($f->translations, 'description.ar', ''),
            'address'     => $f->address,
            'address_ar'  => data_get($f->translations, 'address.ar', ''),
            'gps_lat'     => $f->gps_lat,
            'gps_long'    => $f->gps_long,
            'maps_url'    => $f->maps_url,
            'is_available' => (bool) $f->is_available,
            'images'      => is_array($f->images) ? $f->images : [],
        ]]);
    @endphp
    <script>
window.facilitiesData = @json($facilitiesData);

function escFacility(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

function facilityCardHtml(f) {
    const img = (Array.isArray(f.images) && f.images[0]) ? f.images[0] : (f.photo || null);
    // Only allow http(s) links — never a javascript:/data: URL in href.
    const safeMaps = /^https?:\/\//i.test(f.maps_url || '') ? f.maps_url : '';
    const badge = f.is_available
        ? '<span class="px-2 py-0.5 rounded-full text-[10px] font-medium flex-shrink-0 bg-green-100 text-green-700">{{ __('admin.fac_available') }}</span>'
        : '<span class="px-2 py-0.5 rounded-full text-[10px] font-medium flex-shrink-0 bg-gray-100 text-gray-500">{{ __('admin.fac_unavailable') }}</span>';
    return `
    <div class="m-card" id="facility-${f.id}" x-data="{ openMenu: false }">
        <div class="relative">
            ${img ? `<img src="/storage/${escFacility(img)}" alt="" class="w-full h-32 object-cover rounded-t-2xl">` : ''}
            <div class="absolute top-2 right-2 z-10" @click.stop>
                <button type="button" @click="openMenu = !openMenu" class="m-press w-8 h-8 rounded-full bg-white/90 backdrop-blur flex items-center justify-center text-foreground shadow-sm"><i class="bi bi-three-dots-vertical"></i></button>
                <div x-show="openMenu" x-cloak @click.outside="openMenu = false" class="absolute right-0 mt-2 w-44 bg-white rounded-xl shadow-xl border border-gray-100 overflow-hidden z-20">
                    <button type="button" class="w-full text-left px-4 py-3 text-sm text-foreground hover:bg-muted/60 flex items-center gap-3" @click="openMenu = false; $dispatch('open-edit-facility', { id: ${f.id} })"><span class="w-7 h-7 rounded-lg bg-blue-100 flex items-center justify-center shrink-0"><i class="bi bi-pencil text-blue-600 text-xs"></i></span><span class="font-medium">{{ __('admin.fac_edit') }}</span></button>
                    <button type="button" class="w-full text-left px-4 py-3 text-sm text-red-600 hover:bg-red-50 flex items-center gap-3" @click="openMenu = false; removeFacilityId = ${Number(f.id)}; removeFacilityName = (window.facilitiesData[${Number(f.id)}] || {}).name || ''"><span class="w-7 h-7 rounded-lg bg-red-100 flex items-center justify-center shrink-0"><i class="bi bi-trash text-red-600 text-xs"></i></span><span class="font-medium">{{ __('admin.fac_delete') }}</span></button>
                </div>
            </div>
        </div>
        <div class="p-4">
            <div class="flex items-start justify-between gap-2 pr-9">
                <h3 class="font-semibold text-foreground truncate">${escFacility(f.name)}</h3>
                ${badge}
            </div>
            ${f.description ? `<p class="text-xs text-muted-foreground mt-1 line-clamp-2">${escFacility(f.description)}</p>` : ''}
            ${f.address ? `<p class="text-xs text-muted-foreground mt-2"><i class="bi bi-geo-alt mr-1"></i>${escFacility(f.address)}</p>` : ''}
            ${safeMaps ? `<a href="${escFacility(safeMaps)}" target="_blank" rel="noopener" class="text-xs text-primary mt-1 inline-flex items-center gap-1"><i class="bi bi-map"></i>{{ __('admin.fac_maps_url') }}</a>` : ''}
        </div>
    </div>`;
}

// In-place add/update after a save (deduped — the mobile shell re-runs inline scripts on nav).
window.__facilitySaved && window.removeEventListener('facility-saved', window.__facilitySaved);
window.__facilitySaved = function (e) {
    const f = e.detail && e.detail.facility;
    if (!f || !f.id) return;
    const tr = f.translations || {};
    window.facilitiesData[f.id] = {
        id: f.id, name: f.name,
        name_ar: (tr.name && tr.name.ar) || '',
        description: f.description,
        description_ar: (tr.description && tr.description.ar) || '',
        address: f.address,
        address_ar: (tr.address && tr.address.ar) || '',
        gps_lat: f.gps_lat, gps_long: f.gps_long,
        maps_url: f.maps_url, is_available: !!f.is_available,
        images: Array.isArray(f.images) ? f.images : [],
    };
    const existing = document.getElementById('facility-' + f.id);
    if (existing) {
        existing.outerHTML = facilityCardHtml(f);
    } else {
        document.getElementById('facilitiesEmpty')?.classList.add('hidden');
        document.getElementById('facilitiesList')?.insertAdjacentHTML('beforeend', facilityCardHtml(f));
    }
};
window.addEventListener('facility-saved', window.__facilitySaved);

@if($errors->any())
document.addEventListener('DOMContentLoaded', function () {
    window.showToast && window.showToast('error', @json($errors->first()));
});
@endif

function removeFacility(id) {
    if (!id) return;
    fetch(`{{ url('admin/club/' . $club->slug . '/facilities') }}/${id}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
    })
    .then(r => r.ok ? r : Promise.reject())
    .then(() => {
        document.getElementById('facility-' + id)?.remove();
        delete window.facilitiesData[id];
        const list = document.getElementById('facilitiesList');
        if (list && !list.children.length) document.getElementById('facilitiesEmpty')?.classList.remove('hidden');
        window.dispatchEvent(new CustomEvent('facility-removed'));
        window.showToast('success', '{{ __('admin.fac_deleted') }}');
    })
    .catch(() => window.showToast('error', '{{ __('admin.club_facilities_add_unexpected_error') }}'));
}
    </script>
    </div>{{-- /content --}}
</div>
@endsection
