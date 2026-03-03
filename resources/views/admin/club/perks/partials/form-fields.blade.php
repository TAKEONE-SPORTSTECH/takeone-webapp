<div class="space-y-4">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="md:col-span-2">
            <label class="form-label">Title <span class="text-red-500">*</span></label>
            <input type="text" name="title" class="form-control" required
                   placeholder="e.g. Partner Cafe" x-model="formData.title">
        </div>
        <div class="md:col-span-2">
            <label class="form-label">Description</label>
            <input type="text" name="description" class="form-control"
                   placeholder="e.g. Post-workout nutrition & coffee" x-model="formData.description">
        </div>
        <div>
            <label class="form-label">Badge Text <span class="text-red-500">*</span></label>
            <input type="text" name="badge" class="form-control" required
                   placeholder="e.g. -20% OFF, +500 PTS, FREE ITEM" x-model="formData.badge">
        </div>
        <div>
            <label class="form-label">Status</label>
            <select name="status" class="form-control" x-model="formData.status">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
        </div>
        <div>
            <label class="form-label">Sort Order</label>
            <input type="number" name="sort_order" class="form-control" min="0"
                   placeholder="0" x-model="formData.sort_order">
        </div>
    </div>

    <hr class="border-border">

    {{-- Background --}}
    <div>
        <label class="form-label font-semibold">Card Background</label>
        <p class="text-xs text-muted-foreground mb-3">Upload an image, or use a gradient with an icon as fallback.</p>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="form-label text-xs">Background Image <span class="text-xs text-muted-foreground">(optional)</span></label>

                {{-- Cropper component --}}
                <x-takeone-cropper
                    id="perkImageCropper"
                    :width="400" :height="267"
                    shape="rectangle" mode="form"
                    inputName="image"
                    :folder="'perks/' . (isset($club) ? $club->slug : 'club')"
                    :filename="'perk_' . time()"
                    :previewWidth="260" :previewHeight="174"
                    buttonText="Upload Image"
                    buttonClass="btn btn-outline-secondary w-full mt-2"
                    :canvasHeight="520"
                />

                {{-- Existing image when editing --}}
                <div x-show="formData.image_path && !formData.remove_image" class="mt-3">
                    <p class="text-xs text-muted-foreground mb-1">Current image:</p>
                    <div class="relative inline-block">
                        <img :src="'/storage/' + formData.image_path"
                             class="rounded-xl object-cover" style="height:80px;max-width:100%;" alt="Current">
                        <button type="button"
                                class="absolute top-1 right-1 bg-white rounded-full w-6 h-6 flex items-center justify-center shadow text-red-500 text-xs"
                                @click="formData.remove_image = true">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>
                <input type="hidden" name="remove_image" :value="formData.remove_image ? '1' : '0'">
            </div>

            <div class="space-y-3">
                <div>
                    <label class="form-label text-xs">Icon Class <span class="text-xs text-muted-foreground">(Bootstrap Icons fallback)</span></label>
                    <input type="text" name="icon" class="form-control"
                           placeholder="bi-cup-hot" x-model="formData.icon">
                </div>
                <div>
                    <label class="form-label text-xs">Gradient From</label>
                    <div class="flex items-center gap-2">
                        <input type="color" name="bg_from" class="form-control h-10 p-1 cursor-pointer w-16"
                               x-model="formData.bg_from">
                        <span class="text-sm text-muted-foreground" x-text="formData.bg_from"></span>
                    </div>
                </div>
                <div>
                    <label class="form-label text-xs">Gradient To</label>
                    <div class="flex items-center gap-2">
                        <input type="color" name="bg_to" class="form-control h-10 p-1 cursor-pointer w-16"
                               x-model="formData.bg_to">
                        <span class="text-sm text-muted-foreground" x-text="formData.bg_to"></span>
                    </div>
                </div>

                {{-- Gradient preview (shown when no image) --}}
                <div x-show="!formData.image_path || formData.remove_image" class="mt-1">
                    <div class="rounded-xl flex items-center justify-center gap-3 p-4"
                         :style="`background: linear-gradient(135deg, ${formData.bg_from}, ${formData.bg_to}); height:70px;`">
                        <i :class="'bi ' + (formData.icon || 'bi-gift')" class="text-white text-3xl"></i>
                        <span class="text-white font-bold text-sm" x-text="formData.title || 'Preview'"></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <hr class="border-border">

    {{-- Perk type & value --}}
    <div>
        <label class="form-label font-semibold">Perk Reward</label>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-2">
            <div>
                <label class="form-label text-xs">Perk Type <span class="text-red-500">*</span></label>
                <select name="perk_type" class="form-control" x-model="formData.perk_type">
                    <option value="code">Promo Code</option>
                    <option value="qr">QR Code</option>
                </select>
            </div>
            <div>
                <label class="form-label text-xs" x-text="formData.perk_type === 'qr' ? 'QR Content (URL or text)' : 'Promo Code'"></label>
                <input type="text" name="perk_value" class="form-control"
                       :placeholder="formData.perk_type === 'qr' ? 'e.g. https://partner.com/offer' : 'e.g. CAFE20'"
                       x-model="formData.perk_value">
            </div>
        </div>
        <p class="text-xs text-muted-foreground mt-2">
            <span x-show="formData.perk_type === 'code'">Members will see this code to copy and use at the partner.</span>
            <span x-show="formData.perk_type === 'qr'">Members will see a QR code they can show at the partner location.</span>
        </p>
    </div>
</div>
