{{-- Mobile "Add / Edit Perk" — bottom sheet. Opens on `open-add-perk` or
     `open-edit-perk` (detail.id). Mirrors the DESKTOP perk form fields
     (title, description, badge — all translatable — status, sort order, icon,
     gradient colors, background image, perk type & value) and posts to the SAME
     store/update endpoints & field names, via AJAX (in-place update). --}}
<script>
window.perkFormSheet = function () {
    return {
        open: false,
        saving: false,
        mode: 'add',            // 'add' | 'edit'
        editId: null,
        lang: 'en',             // en | ar (translatable field toggle)
        csrf: '{{ csrf_token() }}',

        title: '', title_ar: '',
        description: '', description_ar: '',
        badge: '', badge_ar: '',
        icon: 'bi-gift',
        bg_from: '#f59e0b', bg_to: '#f97316',
        perk_type: 'code', perk_value: '',
        status: 'active',
        sort_order: 0,

        imagePath: '',          // existing stored image path (edit)
        imageData: '',          // newly-picked image as data URL
        removeImage: false,

        get formAction() {
            return this.mode === 'edit'
                ? `{{ url('admin/club/' . $club->slug . '/perks') }}/${this.editId}`
                : `{{ route('admin.club.perks.store', $club->slug) }}`;
        },
        get previewImage() {
            if (this.imageData) return this.imageData;
            if (this.imagePath && !this.removeImage) return '/storage/' + this.imagePath;
            return '';
        },

        onImage(e) {
            const f = e.target.files[0];
            if (!f || !f.type.startsWith('image/')) { e.target.value = ''; return; }
            const r = new FileReader();
            r.onload = ev => { this.imageData = ev.target.result; this.removeImage = false; };
            r.readAsDataURL(f);
            e.target.value = '';
        },
        clearImage() {
            this.imageData = '';
            if (this.imagePath) this.removeImage = true;
        },

        resetAll() {
            this.lang = 'en';
            this.title = this.title_ar = '';
            this.description = this.description_ar = '';
            this.badge = this.badge_ar = '';
            this.icon = 'bi-gift';
            this.bg_from = '#f59e0b'; this.bg_to = '#f97316';
            this.perk_type = 'code'; this.perk_value = '';
            this.status = 'active';
            this.sort_order = 0;
            this.imagePath = ''; this.imageData = ''; this.removeImage = false;
        },
        openAdd() { this.mode = 'add'; this.editId = null; this.resetAll(); this.open = true; },
        openEdit(id) {
            const d = (window.perksData || {})[id];
            if (!d) { window.showToast('error', 'Perk not found.'); return; }
            this.mode = 'edit'; this.editId = id; this.resetAll();
            this.title = d.title || '';
            this.title_ar = d.title_ar || '';
            this.description = d.description || '';
            this.description_ar = d.description_ar || '';
            this.badge = d.badge || '';
            this.badge_ar = d.badge_ar || '';
            this.icon = d.icon || 'bi-gift';
            this.bg_from = d.bg_from || '#f59e0b';
            this.bg_to = d.bg_to || '#f97316';
            this.perk_type = d.perk_type || 'code';
            this.perk_value = d.perk_value || '';
            this.status = d.status || 'active';
            this.sort_order = d.sort_order ?? 0;
            this.imagePath = d.image_path || '';
            this.open = true;
        },

        async submit() {
            if (this.saving) return;
            if (!this.title.trim()) { this.lang = 'en'; window.showToast('warning', '{{ __('admin.perk_title_required') }}'); return; }
            this.saving = true;

            // FormData off the real <form> captures every named input, incl. the
            // hidden base64 image input and the translations[*][ar] fields.
            const fd = new FormData(this.$refs.form);
            if (this.mode === 'edit') fd.set('_method', 'PUT');

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
                window.dispatchEvent(new CustomEvent('perk-saved', { detail: { perk: dd.perk || dd.data, mode: this.mode } }));
                this.open = false;
                window.showToast('success', dd.message || '{{ __('admin.perk_saved') }}');
            } catch (e) {
                window.showToast('error', e.message);
            } finally {
                this.saving = false;
            }
        },
    };
};
</script>

<div class="contents" x-data="perkFormSheet()"
     @open-add-perk.window="openAdd()"
     @open-edit-perk.window="openEdit($event.detail.id)"
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
                    <i class="bi bi-gift mr-2"></i><span x-text="mode === 'edit' ? '{{ __('admin.perk_edit') }}' : '{{ __('admin.perk_add') }}'"></span>
                </h5>
                <button type="button" @click="open = false" class="text-white/90 hover:text-white text-2xl leading-none w-8 h-8 flex items-center justify-center -mr-1">&times;</button>
            </div>

            <form x-ref="form" method="POST" :action="formAction" enctype="multipart/form-data"
                  class="flex-1 overflow-y-auto overscroll-contain px-4 py-4 space-y-5" @submit.prevent="submit()">
                @csrf
                {{-- Single base64 image (only sent when a new one was picked) --}}
                <template x-if="imageData">
                    <input type="hidden" name="image" :value="imageData">
                </template>
                <input type="hidden" name="remove_image" :value="removeImage ? '1' : '0'">

                {{-- ===== Basic info ===== --}}
                <div class="space-y-4">
                    <h6 class="text-xs font-semibold text-gray-500 uppercase tracking-wide">{{ __('admin.partials_perk_form_fields_title') }}</h6>
                    <x-lang-toggle class="mb-1" />

                    <div>
                        <label class="form-label">{{ __('admin.partials_perk_form_fields_title') }} <span class="text-red-500">*</span></label>
                        <input type="text" name="title" x-model="title" :required="lang==='en'" x-show="lang==='en'" placeholder="{{ __('admin.partials_perk_form_fields_title_placeholder') }}" class="form-control">
                        <input type="text" name="translations[title][ar]" x-model="title_ar" dir="rtl" x-show="lang==='ar'" x-cloak placeholder="العنوان بالعربية" class="form-control">
                    </div>

                    <div>
                        <label class="form-label">{{ __('admin.partials_perk_form_fields_description') }}</label>
                        <input type="text" name="description" x-model="description" x-show="lang==='en'" placeholder="{{ __('admin.partials_perk_form_fields_description_placeholder') }}" class="form-control">
                        <input type="text" name="translations[description][ar]" x-model="description_ar" dir="rtl" x-show="lang==='ar'" x-cloak placeholder="الوصف بالعربية" class="form-control">
                    </div>

                    <div>
                        <label class="form-label">{{ __('admin.partials_perk_form_fields_badge_text') }} <span class="text-red-500">*</span></label>
                        <input type="text" name="badge" x-model="badge" :required="lang==='en'" x-show="lang==='en'" placeholder="{{ __('admin.partials_perk_form_fields_badge_placeholder') }}" class="form-control">
                        <input type="text" name="translations[badge][ar]" x-model="badge_ar" dir="rtl" x-show="lang==='ar'" x-cloak placeholder="الشارة بالعربية" class="form-control">
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="form-label">{{ __('admin.partials_perk_form_fields_status') }}</label>
                            <x-select-menu model="status" name="status"
                                :options="[
                                    ['value' => 'active', 'label' => __('admin.partials_perk_form_fields_active')],
                                    ['value' => 'inactive', 'label' => __('admin.partials_perk_form_fields_inactive')],
                                ]" />
                        </div>
                        <div>
                            <label class="form-label">{{ __('admin.partials_perk_form_fields_sort_order') }}</label>
                            <input type="number" name="sort_order" x-model="sort_order" min="0" placeholder="0" class="form-control">
                        </div>
                    </div>

                    <div>
                        <label class="form-label">{{ __('admin.partials_perk_form_fields_icon_class') }} <span class="text-xs text-muted-foreground">{{ __('admin.partials_perk_form_fields_bootstrap_icons') }}</span></label>
                        <input type="text" name="icon" x-model="icon" placeholder="bi-cup-hot" class="form-control">
                    </div>
                </div>

                {{-- ===== Card background ===== --}}
                <div class="space-y-3">
                    <div>
                        <h6 class="text-xs font-semibold text-gray-500 uppercase tracking-wide">{{ __('admin.partials_perk_form_fields_card_background') }}</h6>
                        <p class="text-xs text-muted-foreground mt-1">{{ __('admin.partials_perk_form_fields_card_background_hint') }}</p>
                    </div>

                    {{-- Background image (optional) --}}
                    <div>
                        <label class="form-label">{{ __('admin.partials_perk_form_fields_background_image') }} <span class="text-xs text-muted-foreground">{{ __('admin.partials_perk_form_fields_optional') }}</span></label>
                        <div class="grid grid-cols-3 gap-2">
                            <template x-if="previewImage">
                                <div class="relative col-span-2">
                                    <img :src="previewImage" alt="" class="w-full h-24 object-cover rounded-xl border border-gray-100">
                                    <button type="button" @click="clearImage()" class="m-press absolute top-1 right-1 w-6 h-6 rounded-full bg-black/60 text-white grid place-items-center text-xs"><i class="bi bi-x"></i></button>
                                </div>
                            </template>
                            <button type="button" @click="$refs.image.click()"
                                    class="m-press h-24 rounded-xl border-2 border-dashed border-gray-200 bg-muted/40 flex flex-col items-center justify-center text-muted-foreground">
                                <i class="bi bi-camera text-xl"></i>
                                <span class="text-[11px] mt-0.5">{{ __('admin.partials_perk_form_fields_upload_image') }}</span>
                            </button>
                        </div>
                        <input type="file" x-ref="image" accept="image/*" class="hidden" @change="onImage($event)">
                    </div>

                    {{-- Gradient colors (used when no image) --}}
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="form-label text-xs">{{ __('admin.partials_perk_form_fields_gradient_from') }}</label>
                            <div class="flex items-center gap-2">
                                <input type="color" name="bg_from" x-model="bg_from" class="form-control h-10 p-1 cursor-pointer w-16">
                                <span class="text-sm text-muted-foreground" x-text="bg_from"></span>
                            </div>
                        </div>
                        <div>
                            <label class="form-label text-xs">{{ __('admin.partials_perk_form_fields_gradient_to') }}</label>
                            <div class="flex items-center gap-2">
                                <input type="color" name="bg_to" x-model="bg_to" class="form-control h-10 p-1 cursor-pointer w-16">
                                <span class="text-sm text-muted-foreground" x-text="bg_to"></span>
                            </div>
                        </div>
                    </div>

                    {{-- Gradient preview (when no image) --}}
                    <div x-show="!previewImage">
                        <p class="text-xs text-muted-foreground mb-1">{{ __('admin.partials_perk_form_fields_gradient_preview') }}</p>
                        <div class="rounded-xl flex items-center justify-center gap-3 p-4"
                             :style="`background: linear-gradient(135deg, ${bg_from}, ${bg_to}); height:70px;`">
                            <i :class="'bi ' + (icon || 'bi-gift')" class="text-white text-3xl"></i>
                            <span class="text-white font-bold text-sm" x-text="title || 'Preview'"></span>
                        </div>
                    </div>
                </div>

                {{-- ===== Perk reward ===== --}}
                <div class="space-y-3">
                    <h6 class="text-xs font-semibold text-gray-500 uppercase tracking-wide">{{ __('admin.partials_perk_form_fields_perk_reward') }}</h6>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="form-label text-xs">{{ __('admin.partials_perk_form_fields_perk_type') }} <span class="text-red-500">*</span></label>
                            <x-select-menu model="perk_type" name="perk_type"
                                :options="[
                                    ['value' => 'code', 'label' => __('admin.partials_perk_form_fields_promo_code')],
                                    ['value' => 'qr', 'label' => __('admin.partials_perk_form_fields_qr_code')],
                                ]" />
                        </div>
                        <div>
                            <label class="form-label text-xs" x-text="perk_type === 'qr' ? 'QR Content (URL or text)' : 'Promo Code'"></label>
                            <input type="text" name="perk_value" x-model="perk_value"
                                   :placeholder="perk_type === 'qr' ? 'e.g. https://partner.com/offer' : 'e.g. CAFE20'" class="form-control">
                        </div>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        <span x-show="perk_type === 'code'">{{ __('admin.partials_perk_form_fields_code_hint') }}</span>
                        <span x-show="perk_type === 'qr'">{{ __('admin.partials_perk_form_fields_qr_hint') }}</span>
                    </p>
                </div>
            </form>

            {{-- Footer --}}
            <div class="px-4 py-3 bg-gray-50 border-t flex-shrink-0 flex items-center gap-2" style="padding-bottom: max(0.75rem, env(safe-area-inset-bottom));">
                <button type="button" @click="open = false" class="px-4 py-2.5 rounded-xl border border-gray-200 text-gray-600 bg-white text-sm font-medium">{{ __('admin.cancel') }}</button>
                <button type="button" @click="submit()" :disabled="saving" class="flex-1 btn btn-primary py-2.5 disabled:opacity-60">
                    <i class="bi mr-1" :class="saving ? 'bi-arrow-repeat animate-spin' : 'bi-check-lg'"></i><span x-text="mode === 'edit' ? '{{ __('admin.update') }}' : '{{ __('admin.perk_add') }}'"></span>
                </button>
            </div>
        </div>
    </div>
</div>
</template>
</div>
