@php
    /*
     * MULTI-PRICING — the seed for the editor in the sheet below.
     *
     * The sheet is opened for whichever event the member tapped, so there is no
     * single event to render server-side; what is rendered is a LOOKUP for every
     * event this club owns, and `openEdit()` reads its row out of it. That keeps
     * the feature inside this file — `window.eventsData`, built by the page
     * around it, does not have to learn about fee options.
     *
     * Only ACTIVE options are offered: a withdrawn one still exists, because the
     * frozen fee lines on entries already taken point at it.
     */
    $feeOptionEventIds = \App\Models\ClubEvent::where('tenant_id', $club->id)->pluck('id');

    $feeOptionsByEvent = \App\Models\EventFeeOption::query()
        ->whereIn('event_id', $feeOptionEventIds)
        ->forRole('participant')
        ->active()
        ->ordered()
        ->get()
        ->groupBy('event_id')
        ->map(fn ($group) => $group->map(fn ($option) => [
            'uuid' => $option->uuid,
            'label' => $option->label,
            // Trailing zeros trimmed so the box shows the 5 that was typed, not
            // the 5.000 the decimal column stores.
            'amount' => rtrim(rtrim(number_format((float) $option->amount, 3, '.', ''), '0'), '.'),
        ])->values())
        ->all();

    $lateFeeByEvent = \App\Models\ClubEvent::where('tenant_id', $club->id)
        ->whereNotNull('late_fee_from')
        ->get(['id', 'late_fee_amount', 'late_fee_from'])
        ->mapWithKeys(function ($event) {
            // No cast on the column yet, so it may arrive as a string or a
            // Carbon depending on who wrote it. Parse either.
            $from = \Illuminate\Support\Carbon::parse($event->late_fee_from);

            return [$event->id => [
                'amount' => rtrim(rtrim(number_format((float) $event->late_fee_amount, 3, '.', ''), '0'), '.'),
                'date' => $from->format('Y-m-d'),
                'time' => $from->format('H:i'),
            ]];
        })
        ->all();
@endphp

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

        // MULTI-PRICING. `total = base + what was ticked + the late penalty`;
        // the base is `feeAmount` above and is untouched, so an event with no
        // rows here prices exactly as it always has.
        feeRows: [],
        lateAmount: '', lateDate: '', lateTime: '',
        feeOptionsByEvent: @js($feeOptionsByEvent),
        lateFeeByEvent: @js($lateFeeByEvent),

        // Date and time are edited apart (Design Rule #4 forbids a native
        // datetime control) and posted as one value. No date means no late fee
        // at all, so the field goes up empty and the server clears both halves.
        get lateFromValue() {
            return this.lateDate ? this.lateDate + ' ' + (this.lateTime || '00:00') : '';
        },
        addFeeRow() { this.feeRows.push({ uuid: '', label: '', amount: '' }); },
        removeFeeRow(i) { this.feeRows.splice(i, 1); },

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
            this.feeRows = [];
            this.lateAmount = this.lateDate = this.lateTime = '';
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
            // The stated amount, when the event has one. The string is only
            // read for rows written before that column existed.
            const feeAmt = d.participant_fee_amount;
            const feeRaw = String(d.participant_fee ?? '').trim();
            if (feeAmt !== null && feeAmt !== undefined && feeAmt !== '') {
                const n = parseFloat(feeAmt);
                this.feeType = n > 0 ? 'paid' : 'free';
                this.feeAmount = n > 0 ? String(n) : '';
            } else if (feeRaw) {
                const m = feeRaw.match(/[\d.]+/);
                this.feeType = 'paid';
                this.feeAmount = m ? m[0] : '';
            } else {
                this.feeType = 'free';
                this.feeAmount = '';
            }
            // The event's own extras and late penalty, from the lookup above.
            // Copied, never referenced, so editing a row and then cancelling
            // does not leave the change behind for the next open.
            const opts = this.feeOptionsByEvent[id] || [];
            this.feeRows = opts.map(o => ({ uuid: o.uuid, label: o.label, amount: o.amount }));
            const late = this.lateFeeByEvent[id] || null;
            this.lateAmount = late ? late.amount : '';
            this.lateDate = late ? late.date : '';
            this.lateTime = late ? late.time : '';
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

            <div class="flex-shrink-0 px-5 pt-3 pb-4 rounded-t-3xl sm:rounded-t-2xl text-white relative overflow-hidden"
                 style="background: linear-gradient(150deg, #7c6bf5, #7c6bf5b0);">
                <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3"></div>
                <div class="relative flex items-start gap-3">
                    <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0">
                        <i class="bi bi-calendar-event text-xl"></i>
                    </span>
                    <div class="min-w-0 flex-1">
                        <h3 class="text-lg font-black leading-tight truncate"><span x-text="mode === 'edit' ? '{{ __('admin.evt_edit') }}' : '{{ __('admin.evt_add') }}'"></span></h3>
                        <p class="text-[12px] text-white/85 mt-0.5 truncate">{{ $club->name }}</p>
                    </div>
                    <button type="button" @click="open = false" aria-label="{{ __('shared.close') }}"
                            class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
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
                    {{-- The amount itself: the server prices from this, and
                         composes the display line above from it. --}}
                    <input type="hidden" name="participant_fee_amount"
                           :value="feeType === 'paid' && feeAmount !== '' && feeAmount !== null ? feeAmount : ''">
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

                {{-- Extras and options, and the late-entry penalty. Both are
                     ADDED to the entry fee above. --}}
                <div class="border-t border-gray-200 pt-4">
                    {{-- The form declaring it owns these fields. Without it the
                         controller cannot tell "the organiser removed the last
                         option" from "this caller never had the editor on
                         screen", and would wipe pricing set elsewhere. --}}
                    <input type="hidden" name="fee_pricing_present" value="1">

                    <label class="form-label">{{ __('events.fee_options_title') }}</label>
                    <p class="text-xs text-muted-foreground mb-2">{{ __('events.fee_options_hint') }}</p>

                    <div class="space-y-2">
                        <template x-for="(row, i) in feeRows" :key="i">
                            <div class="flex items-center gap-2">
                                {{-- The uuid says "this row is that existing
                                     option"; the server resolves it against this
                                     event's own rows and a stale one simply
                                     becomes a new option. --}}
                                <input type="hidden" :name="`fee_options[${i}][uuid]`" :value="row.uuid || ''">
                                <input type="text" class="form-control flex-1" maxlength="80"
                                       :name="`fee_options[${i}][label]`" x-model="row.label"
                                       placeholder="{{ __('events.fee_option_placeholder') }}"
                                       aria-label="{{ __('events.fee_option_label') }}">
                                <input type="number" min="0" step="any" class="form-control w-24"
                                       :name="`fee_options[${i}][amount]`" x-model="row.amount"
                                       :placeholder="currency"
                                       aria-label="{{ __('events.fee_option_amount') }}">
                                <button type="button" @click="removeFeeRow(i)"
                                        title="{{ __('events.fee_option_remove') }}"
                                        aria-label="{{ __('events.fee_option_remove') }}"
                                        class="m-press w-9 h-9 rounded-xl border border-gray-200 text-red-600 grid place-items-center flex-shrink-0">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </template>
                    </div>

                    <button type="button" @click="addFeeRow()"
                            class="m-press mt-2 w-full py-2.5 rounded-xl border-2 border-dashed border-gray-200 bg-muted/40 text-sm font-medium text-muted-foreground flex items-center justify-center gap-2">
                        <i class="bi bi-plus-lg"></i> {{ __('events.fee_option_add') }}
                    </button>

                    {{-- The late penalty. Both halves or neither — an amount with
                         no moment to start from charges nobody, and a moment with
                         no amount charges nothing. --}}
                    <div class="mt-4">
                        <label class="form-label">{{ __('events.fee_late_title') }}</label>
                        <p class="text-xs text-muted-foreground mb-2">{{ __('events.fee_late_hint') }}</p>
                        <div class="relative mb-2">
                            <span class="absolute inset-y-0 start-0 flex items-center ps-3 text-sm text-muted-foreground pointer-events-none" x-text="currency"></span>
                            <input type="number" min="0" step="any" name="late_fee_amount" x-model="lateAmount"
                                   class="form-control ps-14" aria-label="{{ __('events.fee_late_amount') }}">
                        </div>
                        <label class="form-label text-xs">{{ __('events.fee_late_from') }}</label>
                        <div class="flex items-center gap-2">
                            <div class="flex-1">
                                <x-date-picker model="lateDate" />
                            </div>
                            <input type="time" class="form-control w-32" x-model="lateTime">
                        </div>
                        <input type="hidden" name="late_fee_from" :value="lateFromValue">
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
                                <img :src="'/file/' + img" alt="" class="w-full h-20 object-cover rounded-xl border border-gray-100">
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
