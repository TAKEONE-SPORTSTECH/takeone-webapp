<div class="space-y-4">
    <div>
        <label class="form-label">{{ __('admin.partials_form_fields_body') }} <span class="text-red-500">*</span></label>
        <textarea name="body" class="form-control" rows="4"
                  placeholder="{{ __('admin.partials_form_fields_body_placeholder') }}"
                  x-model="formData.body" required></textarea>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="form-label">{{ __('admin.partials_form_fields_category') }} <span class="text-red-500">*</span></label>
            <x-select-menu model="formData.category" name="category"
                :options="[
                    ['value' => 'Announcement', 'label' => __('admin.partials_form_fields_category_announcement')],
                    ['value' => 'Highlight', 'label' => __('admin.partials_form_fields_category_highlight')],
                    ['value' => 'Community', 'label' => __('admin.partials_form_fields_category_community')],
                    ['value' => 'Update', 'label' => __('admin.partials_form_fields_category_update')],
                ]" />
        </div>
        <div>
            <label class="form-label">{{ __('admin.partials_form_fields_date_time') }} <span class="text-red-500">*</span></label>
            <input type="datetime-local" name="posted_at" class="form-control"
                   x-model="formData.posted_at" required>
        </div>
        <div>
            <label class="form-label">{{ __('admin.partials_form_fields_status') }}</label>
            <x-select-menu model="formData.status" name="status"
                :options="[
                    ['value' => 'published', 'label' => __('admin.partials_form_fields_status_published')],
                    ['value' => 'draft', 'label' => __('admin.partials_form_fields_status_draft')],
                ]" />
        </div>
        <div>
            <label class="form-label">{{ __('admin.partials_form_fields_image') }} <span class="text-xs text-muted-foreground">{{ __('admin.partials_form_fields_optional') }}</span></label>
            <input type="file" name="image" class="form-control" accept="image/*"
                   @change="handleImageChange($event)">
        </div>
    </div>

    {{-- Image preview --}}
    <div x-show="formData.image_preview || formData.image_path" class="mt-2">
        <div class="relative inline-block">
            <img :src="formData.image_preview || (formData.image_path ? '/storage/' + formData.image_path : '')"
                 class="rounded-lg object-cover" style="max-height:160px; max-width:100%;" alt="{{ __('admin.partials_form_fields_preview') }}">
            <button type="button"
                    class="absolute top-1 end-1 bg-white rounded-full w-6 h-6 flex items-center justify-center shadow text-red-500 text-xs"
                    @click="removeImage()">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        {{-- Hidden field to signal image removal --}}
        <input type="hidden" name="remove_image" :value="formData.remove_image ? '1' : '0'">
    </div>
</div>
