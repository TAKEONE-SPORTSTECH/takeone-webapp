<div class="space-y-4">
    <x-lang-toggle class="mb-4" />
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="md:col-span-2">
            <label class="form-label">{{ __('admin.partials_perk_form_fields_title') }} <span class="text-red-500">*</span></label>
            <input type="text" name="title" class="form-control" required x-show="lang==='en'"
                   placeholder="{{ __('admin.partials_perk_form_fields_title_placeholder') }}" x-model="formData.title">
            <input type="text" name="translations[title][ar]" dir="rtl" x-show="lang==='ar'" x-cloak
                   class="form-control" placeholder="العنوان بالعربية" x-model="formData.translations_title_ar">
        </div>
        <div class="md:col-span-2">
            <label class="form-label">{{ __('admin.partials_perk_form_fields_description') }}</label>
            <input type="text" name="description" class="form-control" x-show="lang==='en'"
                   placeholder="{{ __('admin.partials_perk_form_fields_description_placeholder') }}" x-model="formData.description">
            <input type="text" name="translations[description][ar]" dir="rtl" x-show="lang==='ar'" x-cloak
                   class="form-control" placeholder="الوصف بالعربية" x-model="formData.translations_description_ar">
        </div>
        <div>
            <label class="form-label">{{ __('admin.partials_perk_form_fields_badge_text') }} <span class="text-red-500">*</span></label>
            <input type="text" name="badge" class="form-control" required x-show="lang==='en'"
                   placeholder="{{ __('admin.partials_perk_form_fields_badge_placeholder') }}" x-model="formData.badge">
            <input type="text" name="translations[badge][ar]" dir="rtl" x-show="lang==='ar'" x-cloak
                   class="form-control" placeholder="الشارة بالعربية" x-model="formData.translations_badge_ar">
        </div>
        <div>
            <label class="form-label">{{ __('admin.partials_perk_form_fields_status') }}</label>
            <x-select-menu model="formData.status" name="status"
                :options="[
                    ['value' => 'active', 'label' => __('admin.partials_perk_form_fields_active')],
                    ['value' => 'inactive', 'label' => __('admin.partials_perk_form_fields_inactive')],
                ]" />
        </div>
        <div>
            <label class="form-label">{{ __('admin.partials_perk_form_fields_sort_order') }}</label>
            <input type="number" name="sort_order" class="form-control" min="0"
                   placeholder="0" x-model="formData.sort_order">
        </div>
        <div>
            <label class="form-label">{{ __('admin.partials_perk_form_fields_icon_class') }} <span class="text-xs text-muted-foreground">{{ __('admin.partials_perk_form_fields_bootstrap_icons') }}</span></label>
            <input type="text" name="icon" class="form-control"
                   placeholder="bi-cup-hot" x-model="formData.icon">
        </div>
    </div>

    <hr class="border-border">

    {{-- Background --}}
    <div>
        <label class="form-label font-semibold">{{ __('admin.partials_perk_form_fields_card_background') }}</label>
        <p class="text-xs text-muted-foreground mb-3">{{ __('admin.partials_perk_form_fields_card_background_hint') }}</p>

        {{-- Image upload (full width) --}}
        <div class="mb-4">
            <label class="form-label text-xs">{{ __('admin.partials_perk_form_fields_background_image') }} <span class="text-xs text-muted-foreground">{{ __('admin.partials_perk_form_fields_optional') }}</span></label>
            <x-takeone-cropper
                id="perkImageCropper"
                :width="400" :height="267"
                shape="rectangle" mode="form"
                inputName="image"
                :folder="'perks/' . (isset($club) ? $club->slug : 'club')"
                :filename="'perk_' . time()"
                :previewWidth="260" :previewHeight="174"
                buttonText="{{ __('admin.partials_perk_form_fields_upload_image') }}"
                buttonClass="btn btn-outline-secondary w-full mt-2"
                :canvasHeight="520"
            />
            <input type="hidden" name="remove_image" :value="formData.remove_image ? '1' : '0'">
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="form-label text-xs">{{ __('admin.partials_perk_form_fields_gradient_from') }}</label>
                <div class="flex items-center gap-2">
                    <input type="color" name="bg_from" class="form-control h-10 p-1 cursor-pointer w-16"
                           x-model="formData.bg_from">
                    <span class="text-sm text-muted-foreground" x-text="formData.bg_from"></span>
                </div>
            </div>

            <div>
                <label class="form-label text-xs">{{ __('admin.partials_perk_form_fields_gradient_to') }}</label>
                <div class="flex items-center gap-2">
                    <input type="color" name="bg_to" class="form-control h-10 p-1 cursor-pointer w-16"
                           x-model="formData.bg_to">
                    <span class="text-sm text-muted-foreground" x-text="formData.bg_to"></span>
                </div>
            </div>
        </div>

        {{-- Previews below the grid --}}
        <div class="mt-3 flex flex-wrap gap-4">
            {{-- Existing image when editing --}}
            <div x-show="formData.image_path && !formData.remove_image">
                <p class="text-xs text-muted-foreground mb-1">{{ __('admin.partials_perk_form_fields_current_image') }}</p>
                <div class="relative inline-block">
                    <img :src="'/storage/' + formData.image_path"
                         class="rounded-xl object-cover" style="height:80px;max-width:100%;" alt="{{ __('admin.partials_perk_form_fields_current_alt') }}">
                    <button type="button"
                            class="absolute top-1 end-1 bg-white rounded-full w-6 h-6 flex items-center justify-center shadow text-red-500 text-xs"
                            @click="formData.remove_image = true">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            </div>

            {{-- Gradient preview (shown when no image) --}}
            <div x-show="!formData.image_path || formData.remove_image" class="flex-1" style="min-width:200px;">
                <p class="text-xs text-muted-foreground mb-1">{{ __('admin.partials_perk_form_fields_gradient_preview') }}</p>
                <div class="rounded-xl flex items-center justify-center gap-3 p-4"
                     :style="`background: linear-gradient(135deg, ${formData.bg_from}, ${formData.bg_to}); height:70px;`">
                    <i :class="'bi ' + (formData.icon || 'bi-gift')" class="text-white text-3xl"></i>
                    <span class="text-white font-bold text-sm" x-text="formData.title || 'Preview'"></span>
                </div>
            </div>
        </div>
    </div>

    <hr class="border-border">

    {{-- Perk type & value --}}
    <div>
        <label class="form-label font-semibold">{{ __('admin.partials_perk_form_fields_perk_reward') }}</label>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-2">
            <div>
                <label class="form-label text-xs">{{ __('admin.partials_perk_form_fields_perk_type') }} <span class="text-red-500">*</span></label>
                <x-select-menu model="formData.perk_type" name="perk_type"
                    :options="[
                        ['value' => 'code', 'label' => __('admin.partials_perk_form_fields_promo_code')],
                        ['value' => 'qr', 'label' => __('admin.partials_perk_form_fields_qr_code')],
                    ]" />
            </div>
            <div>
                <label class="form-label text-xs" x-text="formData.perk_type === 'qr' ? 'QR Content (URL or text)' : 'Promo Code'"></label>
                <input type="text" name="perk_value" class="form-control"
                       :placeholder="formData.perk_type === 'qr' ? 'e.g. https://partner.com/offer' : 'e.g. CAFE20'"
                       x-model="formData.perk_value">
            </div>
        </div>
        <p class="text-xs text-muted-foreground mt-2">
            <span x-show="formData.perk_type === 'code'">{{ __('admin.partials_perk_form_fields_code_hint') }}</span>
            <span x-show="formData.perk_type === 'qr'">{{ __('admin.partials_perk_form_fields_qr_hint') }}</span>
        </p>
    </div>
</div>
