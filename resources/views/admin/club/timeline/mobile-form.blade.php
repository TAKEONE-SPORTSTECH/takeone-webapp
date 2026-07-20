{{-- Mobile "Add / Edit Timeline Post" — bottom sheet. Opens on `open-add-timeline` or
     `open-edit-timeline` (detail.id). Mirrors the desktop timeline form fields and posts
     to the SAME store/update endpoints & field names, via AJAX (in-place update).
     `image` is a REAL uploaded file captured by `new FormData(form)` — not base64. --}}
<script>
window.timelineFormSheet = function () {
    return {
        open: false,
        saving: false,
        mode: 'add',
        editId: null,
        csrf: '{{ csrf_token() }}',

        body: '', category: 'Announcement', posted_at: '', status: 'published',
        imagePath: '',        // existing stored image (edit mode)
        imagePreview: '',     // newly-picked file preview (data URL)
        removeImage: false,

        get formAction() {
            return this.mode === 'edit'
                ? `{{ url('admin/club/' . $club->slug . '/timeline') }}/${this.editId}`
                : `{{ route('admin.club.timeline.store', $club->slug) }}`;
        },

        onImage(e) {
            const f = e.target.files[0];
            if (!f || !f.type.startsWith('image/')) { this.imagePreview = ''; return; }
            this.removeImage = false;
            const r = new FileReader();
            r.onload = ev => this.imagePreview = ev.target.result;
            r.readAsDataURL(f);
        },
        clearImage() {
            this.imagePreview = '';
            if (this.$refs.image) this.$refs.image.value = '';
            if (this.imagePath) this.removeImage = true;
            this.imagePath = '';
        },

        resetAll() {
            this.body = ''; this.category = 'Announcement'; this.posted_at = ''; this.status = 'published';
            this.imagePath = ''; this.imagePreview = ''; this.removeImage = false;
            if (this.$refs.image) this.$refs.image.value = '';
        },
        openAdd() {
            this.mode = 'add'; this.editId = null; this.resetAll();
            const now = new Date(); now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
            this.posted_at = now.toISOString().slice(0, 16);
            this.open = true;
        },
        openEdit(id) {
            const d = (window.timelineData || {})[id];
            if (!d) { window.showToast('error', 'Post not found.'); return; }
            this.mode = 'edit'; this.editId = id; this.resetAll();
            this.body = d.body || '';
            this.category = d.category || 'Announcement';
            this.posted_at = d.posted_at || '';
            this.status = d.status || 'published';
            this.imagePath = d.image_path || '';
            this.open = true;
        },

        async submit() {
            if (this.saving) return;
            if (!this.body.trim()) { window.showToast('warning', '{{ __('admin.tl_body_required') }}'); return; }
            this.saving = true;

            const fd = new FormData(this.$refs.form);
            fd.set('remove_image', this.removeImage ? '1' : '0');
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
                window.dispatchEvent(new CustomEvent('timeline-saved', { detail: { post: dd.post, mode: this.mode } }));
                this.open = false;
                window.showToast('success', dd.message || '{{ __('admin.tl_saved') }}');
            } catch (e) {
                window.showToast('error', e.message);
            } finally {
                this.saving = false;
            }
        },
    };
};
</script>

<div class="contents" x-data="timelineFormSheet()"
     @open-add-timeline.window="openAdd()"
     @open-edit-timeline.window="openEdit($event.detail.id)"
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

            <div class="flex items-center justify-between px-4 py-3 bg-primary text-white rounded-t-3xl sm:rounded-t-2xl flex-shrink-0">
                <h5 class="text-base font-semibold flex items-center">
                    <i class="bi bi-newspaper mr-2"></i><span x-text="mode === 'edit' ? '{{ __('admin.tl_edit') }}' : '{{ __('admin.tl_add') }}'"></span>
                </h5>
                <button type="button" @click="open = false" class="text-white/90 hover:text-white text-2xl leading-none w-8 h-8 flex items-center justify-center -mr-1">&times;</button>
            </div>

            <form x-ref="form" method="POST" :action="formAction" enctype="multipart/form-data"
                  class="flex-1 overflow-y-auto overscroll-contain px-4 py-4 space-y-4" @submit.prevent="submit()">
                @csrf

                {{-- Body --}}
                <div>
                    <label class="form-label">{{ __('admin.partials_form_fields_body') }} <span class="text-red-500">*</span></label>
                    <textarea name="body" x-model="body" rows="4" required placeholder="{{ __('admin.partials_form_fields_body_placeholder') }}" class="form-control resize-none"></textarea>
                </div>

                {{-- Category --}}
                <div>
                    <label class="form-label">{{ __('admin.partials_form_fields_category') }} <span class="text-red-500">*</span></label>
                    <select name="category" x-model="category" required class="form-control">
                        <option value="Announcement">{{ __('admin.partials_form_fields_category_announcement') }}</option>
                        <option value="Highlight">{{ __('admin.partials_form_fields_category_highlight') }}</option>
                        <option value="Community">{{ __('admin.partials_form_fields_category_community') }}</option>
                        <option value="Update">{{ __('admin.partials_form_fields_category_update') }}</option>
                    </select>
                </div>

                {{-- Date & time --}}
                <div>
                    <label class="form-label">{{ __('admin.partials_form_fields_date_time') }} <span class="text-red-500">*</span></label>
                    <input type="datetime-local" name="posted_at" x-model="posted_at" required class="form-control">
                </div>

                {{-- Status --}}
                <div>
                    <label class="form-label">{{ __('admin.partials_form_fields_status') }}</label>
                    <select name="status" x-model="status" class="form-control">
                        <option value="published">{{ __('admin.partials_form_fields_status_published') }}</option>
                        <option value="draft">{{ __('admin.partials_form_fields_status_draft') }}</option>
                    </select>
                </div>

                {{-- Image --}}
                <div>
                    <label class="form-label">{{ __('admin.partials_form_fields_image') }} <span class="text-xs text-muted-foreground">{{ __('admin.partials_form_fields_optional') }}</span></label>
                    <input type="hidden" name="remove_image" :value="removeImage ? '1' : '0'">
                    <div x-show="imagePreview || imagePath" class="mb-2">
                        <div class="relative inline-block">
                            <img :src="imagePreview || (imagePath ? '/storage/' + imagePath : '')" alt="" class="rounded-xl object-cover border border-gray-100" style="max-height:160px; max-width:100%;">
                            <button type="button" @click="clearImage()" class="m-press absolute top-1 right-1 w-6 h-6 rounded-full bg-black/60 text-white grid place-items-center text-xs"><i class="bi bi-x"></i></button>
                        </div>
                    </div>
                    <button type="button" x-show="!imagePreview && !imagePath" @click="$refs.image.click()"
                            class="m-press w-full h-24 rounded-xl border-2 border-dashed border-gray-200 bg-muted/40 flex flex-col items-center justify-center text-muted-foreground">
                        <i class="bi bi-camera text-xl"></i>
                        <span class="text-[11px] mt-0.5">{{ __('admin.partials_form_fields_add_image') }}</span>
                    </button>
                    <input type="file" name="image" x-ref="image" accept="image/*" class="hidden" @change="onImage($event)">
                </div>
            </form>

            <div class="px-4 py-3 bg-gray-50 border-t flex-shrink-0 flex items-center gap-2" style="padding-bottom: max(0.75rem, env(safe-area-inset-bottom));">
                <button type="button" @click="open = false" class="px-4 py-2.5 rounded-xl border border-gray-200 text-gray-600 bg-white text-sm font-medium">{{ __('admin.cancel') }}</button>
                <button type="button" @click="submit()" :disabled="saving" class="flex-1 btn btn-primary py-2.5 disabled:opacity-60">
                    <i class="bi mr-1" :class="saving ? 'bi-arrow-repeat animate-spin' : 'bi-check-lg'"></i><span x-text="mode === 'edit' ? '{{ __('admin.update') }}' : '{{ __('admin.tl_add') }}'"></span>
                </button>
            </div>
        </div>
    </div>
</div>
</template>
</div>
