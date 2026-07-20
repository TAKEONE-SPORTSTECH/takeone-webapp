{{-- Mobile "Add / Edit Event" — bottom sheet. Opens on `open-add-event` or
     `open-edit-event` (detail.id). Mirrors the desktop event form fields and posts
     to the SAME store/update endpoints & field names, via AJAX (in-place update). --}}
<script>
window.eventFormSheet = function () {
    return {
        open: false,
        saving: false,
        mode: 'add',
        editId: null,
        csrf: '{{ csrf_token() }}',
        colors: ['#7c3aed', '#ec4899', '#0ea5e9', '#f59e0b', '#10b981', '#ef4444', '#8b5cf6', '#14b8a6'],

        title: '', date: '', end_date: '', start_time: '', end_time: '',
        color: '#7c3aed', location: '', level: '', max_capacity: '', cancel_within_days: '',
        tags: '', description: '',
        feeType: 'free', feeAmount: '',
        currency: @json($club->currency),

        get participantFee() {
            return this.feeType === 'paid' && this.feeAmount !== '' && this.feeAmount !== null
                ? `${this.currency} ${this.feeAmount}` : '';
        },

        keptImages: [],
        newImages: [],

        get formAction() {
            return this.mode === 'edit'
                ? `{{ url('admin/club/' . $club->slug . '/events') }}/${this.editId}`
                : `{{ route('admin.club.events.store', $club->slug) }}`;
        },
        get keepImagesJson() { return JSON.stringify(this.keptImages); },

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
            this.title = this.date = this.end_date = this.start_time = this.end_time = '';
            this.color = '#7c3aed'; this.location = this.level = this.max_capacity = this.cancel_within_days = '';
            this.tags = ''; this.description = '';
            this.feeType = 'free'; this.feeAmount = '';
            this.keptImages = []; this.newImages = [];
        },
        openAdd() { this.mode = 'add'; this.editId = null; this.resetAll(); this.open = true; },
        openEdit(id) {
            const d = (window.eventsData || {})[id];
            if (!d) { window.showToast('error', 'Event not found.'); return; }
            this.mode = 'edit'; this.editId = id; this.resetAll();
            this.title = d.title || '';
            this.date = d.date || '';
            this.end_date = d.end_date || '';
            this.start_time = d.start_time || '';
            this.end_time = d.end_time || '';
            this.color = d.color || '#7c3aed';
            this.location = d.location || '';
            this.level = d.level || '';
            this.max_capacity = d.max_capacity ?? '';
            this.cancel_within_days = d.cancel_within_days ?? '';
            this.tags = Array.isArray(d.tags) ? d.tags.join(', ') : (d.tags || '');
            this.description = d.description || '';
            const feeRaw = String(d.participant_fee ?? '').trim();
            if (feeRaw) {
                const m = feeRaw.match(/[\d.]+/);
                this.feeType = 'paid';
                this.feeAmount = m ? m[0] : '';
            } else {
                this.feeType = 'free';
                this.feeAmount = '';
            }
            this.keptImages = Array.isArray(d.images) ? d.images.slice() : [];
            this.open = true;
        },

        async submit() {
            if (this.saving) return;
            if (!this.title.trim()) { window.showToast('warning', '{{ __('admin.evt_name_required') }}'); return; }
            if (!this.date || !this.start_time) { window.showToast('warning', '{{ __('admin.evt_date_required') }}'); return; }
            this.saving = true;

            const fd = new FormData(this.$refs.form);
            fd.set('keep_images', this.keepImagesJson);
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
                window.dispatchEvent(new CustomEvent('event-saved', { detail: { event: dd.event, mode: this.mode } }));
                this.open = false;
                window.showToast('success', dd.message || '{{ __('admin.evt_saved') }}');
            } catch (e) {
                window.showToast('error', e.message);
            } finally {
                this.saving = false;
            }
        },
    };
};
</script>

<div class="contents" x-data="eventFormSheet()"
     @open-add-event.window="openAdd()"
     @open-edit-event.window="openEdit($event.detail.id)"
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
                    <i class="bi bi-calendar-event mr-2"></i><span x-text="mode === 'edit' ? '{{ __('admin.evt_edit') }}' : '{{ __('admin.evt_add') }}'"></span>
                </h5>
                <button type="button" @click="open = false" class="text-white/90 hover:text-white text-2xl leading-none w-8 h-8 flex items-center justify-center -mr-1">&times;</button>
            </div>

            <form x-ref="form" method="POST" :action="formAction" enctype="multipart/form-data"
                  class="flex-1 overflow-y-auto overscroll-contain px-4 py-4 space-y-4" @submit.prevent="submit()">
                @csrf
                <input type="hidden" name="keep_images" :value="keepImagesJson">
                <template x-for="(img, i) in newImages" :key="i">
                    <input type="hidden" name="event_images_base64[]" :value="img">
                </template>

                {{-- Title --}}
                <div>
                    <label class="form-label">{{ __('admin.partials_form_fields_title') }} <span class="text-red-500">*</span></label>
                    <input type="text" name="title" x-model="title" required placeholder="{{ __('admin.partials_form_fields_title_placeholder') }}" class="form-control">
                </div>

                {{-- Dates --}}
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="form-label">{{ __('admin.partials_form_fields_start_date') }} <span class="text-red-500">*</span></label>
                        <input type="date" name="date" x-model="date" required class="form-control">
                    </div>
                    <div>
                        <label class="form-label">{{ __('admin.partials_form_fields_end_date') }}</label>
                        <input type="date" name="end_date" x-model="end_date" class="form-control">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="form-label">{{ __('admin.partials_form_fields_start_time') }} <span class="text-red-500">*</span></label>
                        <input type="time" name="start_time" x-model="start_time" required class="form-control">
                    </div>
                    <div>
                        <label class="form-label">{{ __('admin.partials_form_fields_end_time') }}</label>
                        <input type="time" name="end_time" x-model="end_time" class="form-control">
                    </div>
                </div>

                {{-- Color --}}
                <div>
                    <label class="form-label">{{ __('admin.partials_form_fields_color') }}</label>
                    <input type="hidden" name="color" :value="color">
                    <div class="flex flex-wrap gap-2">
                        <template x-for="c in colors" :key="c">
                            <button type="button" @click="color = c"
                                    class="w-8 h-8 rounded-full border-2 transition-transform"
                                    :class="color === c ? 'ring-2 ring-offset-2 ring-primary scale-110 border-white' : 'border-white/70'"
                                    :style="`background:${c}`"></button>
                        </template>
                    </div>
                </div>

                {{-- Location (maps URL) --}}
                <div>
                    <label class="form-label">{{ __('admin.partials_form_fields_location') }} <span class="text-xs text-muted-foreground">{{ __('admin.partials_form_fields_map_url') }}</span></label>
                    <input type="text" name="location" x-model="location" placeholder="{{ __('admin.partials_form_fields_map_url_placeholder') }}" class="form-control">
                </div>

                {{-- Level --}}
                <div>
                    <label class="form-label">{{ __('admin.partials_form_fields_level_audience') }}</label>
                    <input type="text" name="level" x-model="level" placeholder="{{ __('admin.partials_form_fields_level_placeholder') }}" class="form-control">
                </div>

                {{-- Capacity + cancel-within --}}
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="form-label">{{ __('admin.partials_form_fields_max_capacity') }}</label>
                        <input type="number" name="max_capacity" x-model="max_capacity" min="1" placeholder="{{ __('admin.partials_form_fields_max_capacity_placeholder') }}" class="form-control">
                    </div>
                    <div>
                        <label class="form-label">{{ __('admin.partials_form_fields_cancel_within') }}</label>
                        <input type="number" name="cancel_within_days" x-model="cancel_within_days" min="1" max="365" placeholder="{{ __('admin.partials_form_fields_cancel_within_placeholder') }}" class="form-control">
                    </div>
                </div>

                {{-- Entry fee --}}
                <div>
                    <label class="form-label">{{ __('admin.evt_entry_fee') }}</label>
                    <input type="hidden" name="participant_fee" :value="participantFee">
                    <div class="flex mb-2 rounded-xl overflow-hidden border border-gray-200 text-sm">
                        <button type="button" @click="feeType = 'free'"
                                :class="feeType !== 'paid' ? 'bg-primary text-white' : 'bg-white text-muted-foreground'"
                                class="flex-1 py-2 px-3 font-medium transition-colors">
                            <i class="bi bi-unlock mr-1"></i>{{ __('admin.evt_fee_free') }}
                        </button>
                        <button type="button" @click="feeType = 'paid'"
                                :class="feeType === 'paid' ? 'bg-primary text-white' : 'bg-white text-muted-foreground'"
                                class="flex-1 py-2 px-3 font-medium transition-colors border-l border-gray-200">
                            <i class="bi bi-cash-coin mr-1"></i>{{ __('admin.evt_fee_paid') }}
                        </button>
                    </div>
                    <div x-show="feeType === 'paid'" x-cloak class="relative">
                        <span class="absolute inset-y-0 start-0 flex items-center ps-3 text-sm text-muted-foreground pointer-events-none" x-text="currency"></span>
                        <input type="number" min="0" step="any" x-model="feeAmount" class="form-control ps-14" placeholder="{{ __('admin.evt_fee_amount_placeholder') }}">
                    </div>
                </div>

                {{-- Tags --}}
                <div>
                    <label class="form-label">{{ __('admin.partials_form_fields_tags') }} <span class="text-xs text-muted-foreground">{{ __('admin.partials_form_fields_tags_hint') }}</span></label>
                    <input type="text" name="tags" x-model="tags" placeholder="{{ __('admin.partials_form_fields_tags_placeholder') }}" class="form-control">
                </div>

                {{-- Description --}}
                <div>
                    <label class="form-label">{{ __('admin.partials_form_fields_description') }}</label>
                    <textarea name="description" x-model="description" rows="3" placeholder="{{ __('admin.partials_form_fields_description_placeholder') }}" class="form-control resize-none"></textarea>
                </div>

                {{-- Images --}}
                <div>
                    <label class="form-label">{{ __('admin.partials_form_fields_event_images') }}</label>
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
                            <span class="text-[11px] mt-0.5">{{ __('admin.partials_form_fields_add_image') }}</span>
                        </button>
                    </div>
                    <input type="file" x-ref="images" accept="image/*" multiple class="hidden" @change="onImages($event)">
                </div>
            </form>

            <div class="px-4 py-3 bg-gray-50 border-t flex-shrink-0 flex items-center gap-2" style="padding-bottom: max(0.75rem, env(safe-area-inset-bottom));">
                <button type="button" @click="open = false" class="px-4 py-2.5 rounded-xl border border-gray-200 text-gray-600 bg-white text-sm font-medium">{{ __('admin.cancel') }}</button>
                <button type="button" @click="submit()" :disabled="saving" class="flex-1 btn btn-primary py-2.5 disabled:opacity-60">
                    <i class="bi mr-1" :class="saving ? 'bi-arrow-repeat animate-spin' : 'bi-check-lg'"></i><span x-text="mode === 'edit' ? '{{ __('admin.update') }}' : '{{ __('admin.evt_add') }}'"></span>
                </button>
            </div>
        </div>
    </div>
</div>
</template>
</div>
