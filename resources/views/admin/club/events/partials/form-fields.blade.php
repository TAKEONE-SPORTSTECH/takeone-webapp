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
