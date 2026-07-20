{{-- Mobile "Add / Edit Facility" — bottom sheet. Opens on `open-add-facility`
     or `open-edit-facility` (detail.id). Mirrors the DESKTOP form's content
     (name, description, location map, maps URL, availability, images) and posts
     to the SAME store/update endpoints & field names, via AJAX (in-place update). --}}
<script>
window.facilityFormSheet = function () {
    return {
        open: false,
        saving: false,
        mode: 'add',            // 'add' | 'edit'
        editId: null,
        lang: 'en',             // en | ar (translatable field toggle)
        csrf: '{{ csrf_token() }}',
        mapDefaults: { lat: {{ $club->latitude ?? 25.2048 }}, lng: {{ $club->longitude ?? 55.2708 }} },

        name: '', name_ar: '',
        description: '', description_ar: '',
        address_ar: '',
        maps_url: '',
        is_available: true,

        keptImages: [],         // existing image paths retained (edit)
        newImages: [],          // newly-picked images as data URLs

        get formAction() {
            return this.mode === 'edit'
                ? `{{ url('admin/club/' . $club->slug . '/facilities') }}/${this.editId}`
                : `{{ route('admin.club.facilities.store', $club->slug) }}`;
        },
        get keepImagesJson() { return JSON.stringify(this.keptImages); },

        // ── location map helpers ──
        mapEl(suffix) { return document.getElementById('facilityMap' + suffix); },
        setMap(lat, lng, address) {
            const a = this.mapEl('Address'); if (a) a.value = address || '';
            const fLat = parseFloat(lat), fLng = parseFloat(lng);
            const la = this.mapEl('Lat'); if (la) la.value = isNaN(fLat) ? '' : fLat;
            const ln = this.mapEl('Lng'); if (ln) ln.value = isNaN(fLng) ? '' : fLng;
            // The sheet animates in over ~300ms; build once it's on-screen. Retry a
            // few times in case window.LocationMap isn't defined yet on first paint.
            let tries = 0;
            const boot = () => {
                if (!window.LocationMap) { if (tries++ < 20) setTimeout(boot, 150); return; }
                window.LocationMap.create({ id: 'facilityMap', defaultLat: this.mapDefaults.lat, defaultLng: this.mapDefaults.lng, zoom: 13 });
                if (!isNaN(fLat) && !isNaN(fLng)) window.LocationMap.setPosition('facilityMap', fLat, fLng);
                setTimeout(() => window.LocationMap.refresh('facilityMap'), 400);
            };
            this.$nextTick(boot);
        },

        onImages(e) {
            [...e.target.files].forEach(f => {
                if (!f.type.startsWith('image/')) return;
                const r = new FileReader();
                r.onload = ev => this.newImages.push(ev.target.result);
                r.readAsDataURL(f);
            });
            e.target.value = '';
        },
        removeNew(i) { this.newImages.splice(i, 1); },
        removeKept(i) { this.keptImages.splice(i, 1); },

        resetAll() {
            this.lang = 'en';
            this.name = this.name_ar = this.description = this.description_ar = this.address_ar = this.maps_url = '';
            this.is_available = true;
            this.keptImages = []; this.newImages = [];
        },
        openAdd() {
            this.mode = 'add'; this.editId = null; this.resetAll(); this.open = true;
            this.setMap(this.mapDefaults.lat, this.mapDefaults.lng, '');
        },
        openEdit(id) {
            const d = (window.facilitiesData || {})[id];
            if (!d) { window.showToast('error', 'Facility not found.'); return; }
            this.mode = 'edit'; this.editId = id; this.resetAll();
            this.name = d.name || '';
            this.name_ar = d.name_ar || '';
            this.description = d.description || '';
            this.description_ar = d.description_ar || '';
            this.address_ar = d.address_ar || '';
            this.maps_url = d.maps_url || '';
            this.is_available = !!d.is_available;
            this.keptImages = Array.isArray(d.images) ? d.images.slice() : [];
            this.open = true;
            this.setMap(d.gps_lat ?? this.mapDefaults.lat, d.gps_long ?? this.mapDefaults.lng, d.address || '');
        },

        async submit() {
            if (this.saving) return;
            if (!this.name.trim()) { this.lang = 'en'; window.showToast('warning', '{{ __('admin.fac_name_required') }}'); return; }
            this.saving = true;

            // FormData off the real <form> captures every input incl. the map's
            // address/latitude/longitude and the base64 image hidden inputs.
            const fd = new FormData(this.$refs.form);
            fd.set('keep_images', this.keepImagesJson);
            // The store endpoint reads latitude/longitude; update reads gps_lat/gps_long.
            if (this.mode === 'edit') {
                fd.set('gps_lat', fd.get('latitude') || '');
                fd.set('gps_long', fd.get('longitude') || '');
                fd.set('_method', 'PUT');
            }

            try {
                const res = await fetch(this.formAction, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                    body: fd,
                });
                const dd = await res.json().catch(() => ({}));
                if (!res.ok || dd.success === false) {
                    const msg = dd.message || (dd.errors ? Object.values(dd.errors)[0][0] : null) || 'Error';
                    throw new Error(msg);
                }
                const facility = dd.facility || dd.data;
                window.dispatchEvent(new CustomEvent('facility-saved', { detail: { facility, mode: this.mode } }));
                this.open = false;
                window.showToast('success', dd.message || '{{ __('admin.fac_saved') }}');
            } catch (e) {
                window.showToast('error', e.message);
            } finally {
                this.saving = false;
            }
        },
    };
};
</script>

<div class="contents" x-data="facilityFormSheet()"
     @open-add-facility.window="openAdd()"
     @open-edit-facility.window="openEdit($event.detail.id)"
     @keydown.escape.window="open = false">
<template x-teleport="body">
<div x-show="open" x-cloak class="fixed inset-0 z-[60] overflow-y-auto">

    <div x-show="open" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-black/50" @click="open = false"></div>

    <div class="flex min-h-full items-end justify-center sm:items-center sm:p-4">
        <div x-show="open"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-y-full sm:translate-y-4 sm:scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 translate-y-full sm:translate-y-4 sm:scale-95"
             class="relative bg-white rounded-t-3xl sm:rounded-2xl shadow-xl w-full sm:max-w-lg flex flex-col"
             style="max-height: 92vh;" @click.stop>

            <div class="pt-2.5 pb-1 flex justify-center sm:hidden flex-shrink-0"><span class="w-10 h-1.5 rounded-full bg-gray-300"></span></div>

            {{-- Header --}}
            <div class="flex items-center justify-between px-4 py-3 bg-primary text-white rounded-t-3xl sm:rounded-t-2xl flex-shrink-0">
                <h5 class="text-base font-semibold flex items-center">
                    <i class="bi bi-geo-alt mr-2"></i><span x-text="mode === 'edit' ? '{{ __('admin.fac_edit') }}' : '{{ __('admin.fac_add') }}'"></span>
                </h5>
                <button type="button" @click="open = false" class="text-white/90 hover:text-white text-2xl leading-none w-8 h-8 flex items-center justify-center -mr-1">&times;</button>
            </div>

            <form x-ref="form" method="POST" :action="formAction" enctype="multipart/form-data"
                  class="flex-1 overflow-y-auto overscroll-contain px-4 py-4 space-y-5" @submit.prevent="submit()">
                @csrf
                <input type="hidden" name="keep_images" :value="keepImagesJson">
                <template x-for="(img, i) in newImages" :key="i">
                    <input type="hidden" name="facility_images_base64[]" :value="img">
                </template>

                {{-- ===== Basic info ===== --}}
                <div class="space-y-4">
                    <h6 class="text-xs font-semibold text-gray-500 uppercase tracking-wide">{{ __('admin.club_facilities_add_basic_information') }}</h6>
                    <x-lang-toggle class="mb-1" />

                    <div>
                        <label class="form-label">{{ __('admin.fac_name') }} <span class="text-red-500">*</span></label>
                        <input type="text" name="name" x-model="name" :required="lang==='en'" x-show="lang==='en'" placeholder="{{ __('admin.fac_name_ph') }}" class="form-control">
                        <input type="text" name="translations[name][ar]" x-model="name_ar" dir="rtl" x-show="lang==='ar'" x-cloak placeholder="الاسم بالعربية" class="form-control">
                    </div>

                    <div>
                        <label class="form-label">{{ __('admin.club_facilities_add_description') }}</label>
                        <textarea name="description" x-model="description" x-show="lang==='en'" rows="3" placeholder="{{ __('admin.club_facilities_add_description_placeholder') }}" class="form-control resize-none"></textarea>
                        <textarea name="translations[description][ar]" x-model="description_ar" dir="rtl" x-show="lang==='ar'" x-cloak rows="3" placeholder="الوصف بالعربية" class="form-control resize-none"></textarea>
                    </div>
                </div>

                {{-- ===== Location ===== --}}
                <div class="space-y-3">
                    <h6 class="text-xs font-semibold text-gray-500 uppercase tracking-wide flex items-center gap-2"><i class="bi bi-geo-alt"></i>{{ __('admin.club_facilities_add_location') }}</h6>
                    <x-location-map
                        id="facilityMap"
                        latName="latitude"
                        lngName="longitude"
                        addressName="address"
                        :defaultLat="$club->latitude ?? 25.2048"
                        :defaultLng="$club->longitude ?? 55.2708"
                        height="13rem" />
                    <div x-show="lang==='ar'" x-cloak>
                        <label class="form-label">{{ __('admin.fac_address') }} (العربية)</label>
                        <input type="text" name="translations[address][ar]" x-model="address_ar" dir="rtl" placeholder="العنوان بالعربية" class="form-control">
                    </div>
                    <div>
                        <label class="form-label">{{ __('admin.fac_maps_url') }}</label>
                        <input type="url" name="maps_url" x-model="maps_url" placeholder="https://maps.google.com/..." class="form-control">
                        <p class="text-xs text-muted-foreground mt-1">{{ __('admin.club_facilities_add_maps_url_hint') }}</p>
                    </div>
                </div>

                {{-- ===== Availability ===== --}}
                <div class="space-y-3">
                    <h6 class="text-xs font-semibold text-gray-500 uppercase tracking-wide">{{ __('admin.club_facilities_add_availability') }}</h6>
                    <label class="flex items-center gap-3 rounded-2xl border border-gray-100 bg-muted/30 px-3 py-3 cursor-pointer">
                        <input type="checkbox" name="is_available" value="1" x-model="is_available" class="w-5 h-5 text-primary rounded border-gray-300 focus:ring-primary">
                        <span>
                            <span class="block text-sm font-medium text-foreground">{{ __('admin.club_facilities_add_currently_available') }}</span>
                            <span class="text-xs text-muted-foreground">{{ __('admin.club_facilities_add_currently_available_hint') }}</span>
                        </span>
                    </label>
                </div>

                {{-- ===== Images ===== --}}
                <div class="space-y-3">
                    <h6 class="text-xs font-semibold text-gray-500 uppercase tracking-wide flex items-center gap-2"><i class="bi bi-images"></i>{{ __('admin.fac_images') }}</h6>
                    <div class="grid grid-cols-3 gap-2">
                        <template x-for="(img, i) in keptImages" :key="'k' + i">
                            <div class="relative">
                                <img :src="'/storage/' + img" alt="" class="w-full h-20 object-cover rounded-xl border border-gray-100">
                                <button type="button" @click="removeKept(i)" class="m-press absolute top-1 right-1 w-6 h-6 rounded-full bg-black/60 text-white grid place-items-center text-xs"><i class="bi bi-x"></i></button>
                            </div>
                        </template>
                        <template x-for="(img, i) in newImages" :key="'n' + i">
                            <div class="relative">
                                <img :src="img" alt="" class="w-full h-20 object-cover rounded-xl border border-gray-100">
                                <button type="button" @click="removeNew(i)" class="m-press absolute top-1 right-1 w-6 h-6 rounded-full bg-black/60 text-white grid place-items-center text-xs"><i class="bi bi-x"></i></button>
                            </div>
                        </template>
                        <button type="button" @click="$refs.images.click()"
                                class="m-press h-20 rounded-xl border-2 border-dashed border-gray-200 bg-muted/40 flex flex-col items-center justify-center text-muted-foreground">
                            <i class="bi bi-camera text-xl"></i>
                            <span class="text-[11px] mt-0.5">{{ __('admin.club_facilities_add_add_image') }}</span>
                        </button>
                    </div>
                    <input type="file" x-ref="images" accept="image/*" multiple class="hidden" @change="onImages($event)">
                </div>
            </form>

            {{-- Footer --}}
            <div class="px-4 py-3 bg-gray-50 border-t flex-shrink-0 flex items-center gap-2" style="padding-bottom: max(0.75rem, env(safe-area-inset-bottom));">
                <button type="button" @click="open = false" class="px-4 py-2.5 rounded-xl border border-gray-200 text-gray-600 bg-white text-sm font-medium">{{ __('admin.cancel') }}</button>
                <button type="button" @click="submit()" :disabled="saving" class="flex-1 btn btn-primary py-2.5 disabled:opacity-60">
                    <i class="bi mr-1" :class="saving ? 'bi-arrow-repeat animate-spin' : 'bi-check-lg'"></i><span x-text="mode === 'edit' ? '{{ __('admin.update') }}' : '{{ __('admin.fac_add') }}'"></span>
                </button>
            </div>
        </div>
    </div>
</div>
</template>
</div>
