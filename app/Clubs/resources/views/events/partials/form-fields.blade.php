@php
    /*
     * MULTI-PRICING — the seed for the editor below.
     *
     * The event being edited is chosen in the browser (`formData` is swapped
     * wholesale by openAdd/openEdit in index.blade.php), so there is no single
     * event to render server-side. What is rendered instead is a LOOKUP for
     * every event this club owns, and the Alpine block reads its row out of it
     * whenever `formData` is replaced. That keeps the whole feature inside this
     * partial — nothing in the page around it has to learn about fee options.
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
            // Trailing zeros trimmed so the box shows the 5 the organiser typed,
            // not the 5.000 the decimal column stores.
            'label' => $option->label,
            'amount' => rtrim(rtrim(number_format((float) $option->amount, 3, '.', ''), '0'), '.'),
        ])->values())
        ->all();

    $lateFeeByEvent = \App\Models\ClubEvent::where('tenant_id', $club->id)
        ->whereNotNull('late_fee_from')
        ->get(['id', 'late_fee_amount', 'late_fee_from'])
        ->mapWithKeys(function ($event) {
            // The column carries no cast on the model yet, so it may arrive as a
            // string or as a Carbon depending on who wrote it. Parse either.
            $from = \Illuminate\Support\Carbon::parse($event->late_fee_from);

            return [$event->id => [
                'amount' => rtrim(rtrim(number_format((float) $event->late_fee_amount, 3, '.', ''), '0'), '.'),
                'date' => $from->format('Y-m-d'),
                'time' => $from->format('H:i'),
            ]];
        })
        ->all();
@endphp

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div class="md:col-span-2">
        <label class="form-label">{{ __('admin.partials_form_fields_title') }} <span class="text-red-500">*</span></label>
        <input type="text" name="title" class="form-control" required x-model="formData.title" placeholder="{{ __('admin.partials_form_fields_title_placeholder') }}">
    </div>
    <div>
        <label class="form-label">{{ __('admin.partials_form_fields_start_date') }} <span class="text-red-500">*</span></label>
        <input type="date" name="date" class="form-control" required x-model="formData.date">
    </div>
    <div>
        <label class="form-label">{{ __('admin.partials_form_fields_end_date') }}</label>
        <input type="date" name="end_date" class="form-control" x-model="formData.end_date">
    </div>
    <div>
        <label class="form-label">{{ __('admin.partials_form_fields_start_time') }} <span class="text-red-500">*</span></label>
        <input type="time" name="start_time" class="form-control" required x-model="formData.start_time">
    </div>
    <div>
        <label class="form-label">{{ __('admin.partials_form_fields_end_time') }}</label>
        <input type="time" name="end_time" class="form-control" x-model="formData.end_time">
    </div>
    <div class="md:col-span-2">
        <label class="form-label">{{ __('admin.partials_form_fields_color') }}</label>
        <input type="color" name="color" class="form-control h-10 p-1 cursor-pointer" x-model="formData.color">
    </div>
    <div class="md:col-span-2">
        <label class="form-label">{{ __('admin.partials_form_fields_location') }}</label>
        <div class="flex mb-2 border border-border rounded-lg overflow-hidden text-sm">
            <button type="button"
                    @click="locationTab = 'facility'"
                    :class="locationTab === 'facility' ? 'bg-primary text-primary-foreground' : 'bg-muted/40 text-muted-foreground hover:bg-muted'"
                    class="flex-1 py-1.5 px-3 font-medium transition-colors">
                <i class="bi bi-building me-1"></i>{{ __('admin.partials_form_fields_facility') }}
            </button>
            <button type="button"
                    @click="locationTab = 'url'"
                    :class="locationTab === 'url' ? 'bg-primary text-primary-foreground' : 'bg-muted/40 text-muted-foreground hover:bg-muted'"
                    class="flex-1 py-1.5 px-3 font-medium transition-colors">
                <i class="bi bi-geo-alt me-1"></i>{{ __('admin.partials_form_fields_map_url') }}
            </button>
        </div>
        <div x-show="locationTab === 'facility'">
            <x-select-menu model="formData.location" :placeholder="__('admin.partials_form_fields_no_facility')"
                :options="collect($facilities)->map(fn ($facility) => [
                    'value' => $facility->name,
                    'label' => $facility->name . ($facility->address ? ' — ' . $facility->address : ''),
                ])->prepend(['value' => '', 'label' => __('admin.partials_form_fields_no_facility')])->values()->all()" />
        </div>
        <input type="text" x-show="locationTab === 'url'" class="form-control"
               placeholder="{{ __('admin.partials_form_fields_map_url_placeholder') }}" x-model="formData.location">
        <input type="hidden" name="location" :value="formData.location">
    </div>
    <div>
        <label class="form-label">{{ __('admin.partials_form_fields_level_audience') }}</label>
        <input type="text" name="level" class="form-control" placeholder="{{ __('admin.partials_form_fields_level_placeholder') }}" x-model="formData.level">
    </div>
    <div>
        <label class="form-label">{{ __('admin.partials_form_fields_max_capacity') }}</label>
        <input type="number" name="max_capacity" class="form-control" min="1" placeholder="{{ __('admin.partials_form_fields_max_capacity_placeholder') }}" x-model="formData.max_capacity">
    </div>
    <div>
        <label class="form-label">{{ __('admin.partials_form_fields_cancel_within') }} <span class="text-xs text-muted-foreground">{{ __('admin.partials_form_fields_cancel_within_hint') }}</span></label>
        <input type="number" name="cancel_within_days" class="form-control" min="1" max="365" placeholder="{{ __('admin.partials_form_fields_cancel_within_placeholder') }}" x-model="formData.cancel_within_days">
        <p class="text-xs text-muted-foreground mt-1">{{ __('admin.partials_form_fields_cancel_within_note') }}</p>
    </div>
    <div class="md:col-span-2">
        <label class="form-label">{{ __('admin.evt_entry_fee') }}</label>
        <div class="flex mb-2 border border-border rounded-lg overflow-hidden text-sm">
            <button type="button"
                    @click="formData.fee_type = 'free'"
                    :class="formData.fee_type !== 'paid' ? 'bg-primary text-primary-foreground' : 'bg-muted/40 text-muted-foreground hover:bg-muted'"
                    class="flex-1 py-1.5 px-3 font-medium transition-colors">
                <i class="bi bi-unlock me-1"></i>{{ __('admin.evt_fee_free') }}
            </button>
            <button type="button"
                    @click="formData.fee_type = 'paid'"
                    :class="formData.fee_type === 'paid' ? 'bg-primary text-primary-foreground' : 'bg-muted/40 text-muted-foreground hover:bg-muted'"
                    class="flex-1 py-1.5 px-3 font-medium transition-colors">
                <i class="bi bi-cash-coin me-1"></i>{{ __('admin.evt_fee_paid') }}
            </button>
        </div>
        <div x-show="formData.fee_type === 'paid'" class="relative">
            <span class="absolute inset-y-0 start-0 flex items-center ps-3 text-sm text-muted-foreground pointer-events-none">{{ $club->currency }}</span>
            <input type="number" min="0" step="any" x-model="formData.fee_amount" class="form-control ps-14"
                   placeholder="{{ __('admin.evt_fee_amount_placeholder') }}">
        </div>
        <input type="hidden" name="participant_fee"
               :value="formData.fee_type === 'paid' && formData.fee_amount !== '' && formData.fee_amount !== null ? '{{ $club->currency }} ' + formData.fee_amount : ''">
        {{-- The amount itself: the server prices from this, and composes the
             display line above from it. --}}
        <input type="hidden" name="participant_fee_amount"
               :value="formData.fee_type === 'paid' && formData.fee_amount !== '' && formData.fee_amount !== null ? formData.fee_amount : ''">
    </div>
    {{-- ===== Extras and options, and the late-entry penalty =====
         Both are ADDED to the entry fee above: total = base + what was ticked +
         the penalty when the entry lands after the stated moment. An event that
         adds neither prices exactly as it always has. --}}
    <div class="md:col-span-2 border-t border-border pt-4"
         x-data="{
            feeRows: [],
            lateAmount: '', lateDate: '', lateTime: '',
            feeOptionsByEvent: @js($feeOptionsByEvent),
            lateFeeByEvent: @js($lateFeeByEvent),

            get lateFromValue() {
                return this.lateDate ? this.lateDate + ' ' + (this.lateTime || '00:00') : '';
            },

            loadPricing(fd) {
                const id = (fd && fd.id) ? fd.id : null;
                const opts = (id && this.feeOptionsByEvent[id]) ? this.feeOptionsByEvent[id] : [];
                this.feeRows = opts.map(o => ({ uuid: o.uuid, label: o.label, amount: o.amount }));
                const late = (id && this.lateFeeByEvent[id]) ? this.lateFeeByEvent[id] : null;
                this.lateAmount = late ? late.amount : '';
                this.lateDate = late ? late.date : '';
                this.lateTime = late ? late.time : '';
            },
            addFeeRow() { this.feeRows.push({ uuid: '', label: '', amount: '' }); },
            removeFeeRow(i) { this.feeRows.splice(i, 1); },
         }"
         x-init="loadPricing(formData); $watch('formData', (v, old) => { if (v !== old) loadPricing(v); })">

        {{-- The form declaring it owns these fields. Without it the controller
             cannot tell "the organiser removed the last option" from "this
             caller never had the editor on screen", and would wipe pricing set
             elsewhere. --}}
        <input type="hidden" name="fee_pricing_present" value="1">

        <label class="form-label">{{ __('events.fee_options_title') }}</label>
        <p class="text-xs text-muted-foreground mb-2">{{ __('events.fee_options_hint') }}</p>

        <div class="space-y-2">
            <template x-for="(row, i) in feeRows" :key="i">
                <div class="flex items-center gap-2">
                    {{-- The uuid says "this row is that existing option". The
                         server resolves it against this event's own rows; a
                         stale one simply becomes a new option. --}}
                    <input type="hidden" :name="`fee_options[${i}][uuid]`" :value="row.uuid || ''">
                    <input type="text" class="form-control flex-1" maxlength="80"
                           :name="`fee_options[${i}][label]`" x-model="row.label"
                           placeholder="{{ __('events.fee_option_placeholder') }}"
                           aria-label="{{ __('events.fee_option_label') }}">
                    <div class="relative w-32">
                        <span class="absolute inset-y-0 start-0 flex items-center ps-3 text-sm text-muted-foreground pointer-events-none">{{ $club->currency }}</span>
                        <input type="number" min="0" step="any" class="form-control ps-14"
                               :name="`fee_options[${i}][amount]`" x-model="row.amount"
                               aria-label="{{ __('events.fee_option_amount') }}">
                    </div>
                    <button type="button" @click="removeFeeRow(i)"
                            title="{{ __('events.fee_option_remove') }}"
                            aria-label="{{ __('events.fee_option_remove') }}"
                            class="w-9 h-9 rounded-lg flex items-center justify-center text-red-600 hover:bg-red-50 transition-colors border border-border">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </template>
        </div>

        <button type="button" @click="addFeeRow()"
                class="mt-2 px-4 py-2 border border-dashed border-border rounded-lg text-sm font-medium text-muted-foreground hover:bg-muted transition-colors flex items-center gap-2">
            <i class="bi bi-plus-lg"></i> {{ __('events.fee_option_add') }}
        </button>

        {{-- The late penalty. Both halves or neither — an amount with no moment
             to start from charges nobody, and a moment with no amount charges
             nothing. The server stores a half-set pair as no late fee at all. --}}
        <div class="mt-4">
            <label class="form-label">{{ __('events.fee_late_title') }}</label>
            <p class="text-xs text-muted-foreground mb-2">{{ __('events.fee_late_hint') }}</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="form-label text-xs">{{ __('events.fee_late_amount') }}</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 start-0 flex items-center ps-3 text-sm text-muted-foreground pointer-events-none">{{ $club->currency }}</span>
                        <input type="number" min="0" step="any" name="late_fee_amount" class="form-control ps-14" x-model="lateAmount">
                    </div>
                </div>
                <div>
                    <label class="form-label text-xs">{{ __('events.fee_late_from') }}</label>
                    <div class="flex items-center gap-2">
                        <div class="flex-1">
                            <x-date-picker model="lateDate" />
                        </div>
                        <input type="time" class="form-control w-32" x-model="lateTime">
                    </div>
                    {{-- The pair, joined. A blank date means no late fee at all,
                         so the field posts empty and the server clears both. --}}
                    <input type="hidden" name="late_fee_from" :value="lateFromValue">
                </div>
            </div>
        </div>
    </div>
    <div class="md:col-span-2">
        <label class="form-label">{{ __('admin.partials_form_fields_tags') }} <span class="text-xs text-muted-foreground">{{ __('admin.partials_form_fields_tags_hint') }}</span></label>
        <input type="text" name="tags" class="form-control" placeholder="{{ __('admin.partials_form_fields_tags_placeholder') }}" x-model="formData.tags_str">
    </div>
    <div class="md:col-span-2">
        <label class="form-label">{{ __('admin.partials_form_fields_description') }}</label>
        <textarea name="description" class="form-control" rows="3" placeholder="{{ __('admin.partials_form_fields_description_placeholder') }}" x-model="formData.description"></textarea>
    </div>
    <div class="md:col-span-2">
        <label class="form-label">{{ __('admin.partials_form_fields_event_images') }}</label>

        {{-- Existing images --}}
        <div x-show="formData.images && formData.images.length > 0" class="flex flex-wrap gap-2 mb-2">
            <template x-for="(img, idx) in formData.images" :key="idx">
                <div class="relative group">
                    <img :src="img" class="w-20 h-20 object-cover rounded-lg border border-border">
                    <button type="button"
                            @click="formData.images.splice(idx, 1); formData.images_paths.splice(idx, 1)"
                            class="absolute -top-1.5 -end-1.5 bg-red-500 text-white rounded-full w-5 h-5 text-xs flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                        <i class="bi bi-x"></i>
                    </button>
                </div>
            </template>
        </div>
        <input type="hidden" name="keep_images" :value="JSON.stringify(formData.images_paths ?? [])">

        {{-- New cropped images --}}
        <div id="eventNewPreviews" class="flex flex-wrap gap-2 mb-2"></div>
        <div id="eventBase64Inputs"></div>

        <button type="button" onclick="openEventCropper()"
                class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-50 transition-colors flex items-center gap-2">
            <i class="bi bi-camera"></i> {{ __('admin.partials_form_fields_add_image') }}
        </button>
    </div>
</div>
