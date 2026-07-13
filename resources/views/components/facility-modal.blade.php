@props(['club'])
{{-- Inline add/edit facility modal for the public club page (owner only).
     Listens for `open-facility-modal` (create) and `edit-facility-modal`
     (edit — fetches the row first). Saves via the admin facility routes and
     dispatches `facility-saved` so the page patches/inserts the card in place. --}}
<div x-data="facilityModal({
        storeUrl: @js(route('admin.club.facilities.store', $club)),
        base: @js(route('admin.club.facilities.store', $club)),
        csrf: document.querySelector('meta[name=csrf-token]')?.content || '',
     })"
     @open-facility-modal.window="openCreate()"
     @edit-facility-modal.window="openEdit($event.detail.id)"
     @keydown.escape.window="close()"
     x-show="open" x-cloak class="fixed inset-0 z-[80] flex items-center justify-center p-4">

    <div x-show="open" x-transition.opacity class="absolute inset-0 bg-black/50" @click="close()"></div>

    <div x-show="open"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-y-4 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
         class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[92vh] flex flex-col overflow-hidden">

        {{-- Header --}}
        <div class="flex-shrink-0 flex items-center justify-between px-5 py-4 border-b border-gray-100">
            <h3 class="text-lg font-bold text-gray-900" x-text="editingId ? @js(__('Edit facility')) : @js(__('Add facility'))"></h3>
            <button type="button" @click="close()" class="w-9 h-9 -mr-2 rounded-lg flex items-center justify-center text-gray-400 hover:bg-gray-100"><i class="bi bi-x-lg"></i></button>
        </div>

        {{-- Body --}}
        <div class="flex-1 overflow-y-auto px-5 py-4 space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Name') }} <span class="text-red-500">*</span></label>
                <input type="text" x-model="form.name" class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent" placeholder="{{ __('e.g. Olympic pool') }}">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Description') }}</label>
                <textarea x-model="form.description" rows="2" class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent" placeholder="{{ __('Short description') }}"></textarea>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Address') }}</label>
                <input type="text" x-model="form.address" class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Map link') }}</label>
                <input type="url" x-model="form.maps_url" class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent" placeholder="https://maps.google.com/…">
            </div>
            {{-- Photo --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Photo') }}</label>
                <label class="relative flex flex-col items-center justify-center border-2 border-dashed border-gray-200 rounded-xl p-5 cursor-pointer hover:border-purple-400 transition-colors overflow-hidden">
                    <template x-if="!preview">
                        <div class="text-center"><i class="bi bi-image text-2xl text-gray-300"></i><p class="text-xs text-gray-500 mt-1">{{ __('Tap to add a photo') }}</p></div>
                    </template>
                    <template x-if="preview"><img :src="preview" class="max-h-40 rounded-lg object-contain" alt=""></template>
                    <input type="file" accept="image/*" class="hidden" @change="pickPhoto($event)">
                </label>
            </div>
            <label class="flex items-center gap-2.5 cursor-pointer">
                <input type="checkbox" x-model="form.is_available" class="w-4 h-4 rounded border-gray-300 text-purple-600 focus:ring-purple-500">
                <span class="text-sm text-gray-700">{{ __('Visible to the public') }}</span>
            </label>
        </div>

        {{-- Footer --}}
        <div class="flex-shrink-0 px-5 py-4 border-t border-gray-100 flex justify-end gap-3">
            <button type="button" @click="close()" class="px-4 py-2 rounded-lg border border-gray-200 text-gray-700 font-medium hover:bg-gray-50">{{ __('Cancel') }}</button>
            <button type="button" @click="save()" :disabled="saving || !form.name.trim()" class="px-4 py-2 rounded-lg bg-primary text-white font-semibold hover:bg-primary/90 disabled:opacity-60 flex items-center gap-2">
                <span x-show="!saving">{{ __('Save') }}</span>
                <span x-show="saving" class="flex items-center gap-2"><i class="bi bi-arrow-repeat animate-spin"></i>{{ __('Saving…') }}</span>
            </button>
        </div>
    </div>
</div>

@once
@push('scripts')
<script>
window.facilityModal = function (cfg) {
    return {
        open: false, saving: false, editingId: null,
        preview: null, photoB64: null,
        form: { name: '', description: '', address: '', maps_url: '', is_available: true },
        blank() { this.form = { name: '', description: '', address: '', maps_url: '', is_available: true }; this.preview = null; this.photoB64 = null; },
        openCreate() { this.blank(); this.editingId = null; this.open = true; },
        async openEdit(id) {
            this.blank(); this.editingId = id; this.open = true;
            try {
                const res = await fetch(`${cfg.base}/${id}`, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
                const j = await res.json();
                const f = j.data || {};
                this.form = {
                    name: f.name || '', description: f.description || '', address: f.address || '',
                    maps_url: f.maps_url || '', is_available: !!f.is_available,
                };
                const imgs = f.images || [];
                if (imgs.length) this.preview = '/storage/' + imgs[0];
                else if (f.photo) this.preview = '/storage/' + f.photo;
            } catch (e) { window.showToast && window.showToast('error', e.message); }
        },
        close() { this.open = false; },
        pickPhoto(e) {
            const file = e.target.files && e.target.files[0];
            if (!file || !file.type.startsWith('image/')) return;
            const r = new FileReader();
            r.onload = () => { this.photoB64 = r.result; this.preview = r.result; };
            r.readAsDataURL(file);
        },
        async save() {
            if (!this.form.name.trim()) return;
            this.saving = true;
            try {
                const fd = new FormData();
                fd.append('name', this.form.name);
                fd.append('description', this.form.description || '');
                fd.append('address', this.form.address || '');
                fd.append('maps_url', this.form.maps_url || '');
                if (this.form.is_available) fd.append('is_available', '1');
                if (this.photoB64) fd.append('facility_images_base64[]', this.photoB64);

                let url = cfg.storeUrl, method = 'POST';
                if (this.editingId) { url = `${cfg.base}/${this.editingId}`; fd.append('_method', 'PUT'); fd.append('keep_images', '[]'); }

                const res = await fetch(url, {
                    method, body: fd,
                    headers: { 'X-CSRF-TOKEN': cfg.csrf, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                const j = await res.json();
                if (!res.ok || j.success === false) throw new Error(j.message || 'Error');
                const facility = j.facility || j.data || {};
                window.dispatchEvent(new CustomEvent('facility-saved', { detail: { mode: this.editingId ? 'edit' : 'create', id: this.editingId, facility } }));
                window.showToast && window.showToast('success', j.message || 'Saved');
                this.close();
            } catch (e) {
                window.showToast && window.showToast('error', e.message);
            } finally {
                this.saving = false;
            }
        },
    };
};
</script>
@endpush
@endonce
