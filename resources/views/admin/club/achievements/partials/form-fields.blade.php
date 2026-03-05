<div class="space-y-4">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="md:col-span-2">
            <label class="form-label">Title <span class="text-red-500">*</span></label>
            <input type="text" name="title" class="form-control" required
                   placeholder="e.g. Club of the Year" x-model="formData.title">
        </div>
        <div class="md:col-span-2">
            <label class="form-label">Description</label>
            <input type="text" name="description" class="form-control"
                   placeholder="e.g. Awarded for overall performance and growth." x-model="formData.description">
        </div>
        <div>
            <label class="form-label">Tag Text <span class="text-red-500">*</span></label>
            <input type="text" name="tag" class="form-control" required
                   placeholder="e.g. Club Award, Tournament Medals" x-model="formData.tag">
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
        <div>
            <label class="form-label">Tag Icon</label>
            <input type="hidden" name="tag_icon" :value="formData.tag_icon">
            <div class="relative">
                {{-- Trigger button --}}
                <button type="button"
                        @click="showIconPicker = !showIconPicker"
                        class="form-control flex items-center gap-2 cursor-pointer text-left w-full">
                    <i :class="'bi ' + formData.tag_icon" class="text-lg flex-shrink-0"></i>
                    <span class="flex-1 truncate text-sm"
                          x-text="icons.find(i => i.value === formData.tag_icon)?.label ?? formData.tag_icon"></span>
                    <i class="bi bi-chevron-down text-xs text-muted-foreground flex-shrink-0"
                       :class="showIconPicker ? 'rotate-180' : ''"
                       style="transition:transform .15s;"></i>
                </button>

                {{-- Dropdown grid --}}
                <div x-show="showIconPicker"
                     x-cloak
                     @click.outside="showIconPicker = false"
                     class="absolute left-0 right-0 z-50 mt-1 bg-white border border-border rounded-xl shadow-lg p-3"
                     style="max-height:260px;overflow-y:auto;">
                    <div class="grid grid-cols-5 gap-1">
                        <template x-for="icon in icons" :key="icon.value">
                            <button type="button"
                                    @click="formData.tag_icon = icon.value; showIconPicker = false"
                                    :class="formData.tag_icon === icon.value
                                        ? 'bg-primary text-white'
                                        : 'text-foreground hover:bg-muted'"
                                    class="flex flex-col items-center gap-1 p-2 rounded-lg transition-colors"
                                    :title="icon.label">
                                <i :class="'bi ' + icon.value" class="text-xl leading-none"></i>
                                <span class="text-xs leading-tight truncate w-full text-center"
                                      x-text="icon.label"></span>
                            </button>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <hr class="border-border">

    {{-- Card Background --}}
    <div>
        <label class="form-label font-semibold">Card Background</label>
        <p class="text-xs text-muted-foreground mb-3">Upload an image, or use a gradient as fallback.</p>

        {{-- Image upload (full width) --}}
        <div class="mb-4">
            <label class="form-label text-xs">Background Image <span class="text-xs text-muted-foreground">(optional)</span></label>
            <x-takeone-cropper
                id="achievementImageCropper"
                :width="400" :height="267"
                shape="rectangle" mode="form"
                inputName="image"
                :folder="'achievements/' . (isset($club) ? $club->slug : 'club')"
                :filename="'achievement_' . time()"
                :previewWidth="260" :previewHeight="174"
                buttonText="Upload Image"
                buttonClass="btn btn-outline-secondary w-full mt-2"
                :canvasHeight="520"
            />
            <input type="hidden" name="remove_image" :value="formData.remove_image ? '1' : '0'">
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
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
        </div>

        {{-- Previews below the grid --}}
        <div class="mt-3 flex flex-wrap gap-4">
            {{-- Existing image when editing --}}
            <div x-show="formData.image_path && !formData.remove_image">
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

            {{-- Gradient preview (shown when no image) --}}
            <div x-show="!formData.image_path || formData.remove_image" class="flex-1" style="min-width:200px;">
                <p class="text-xs text-muted-foreground mb-1">Gradient preview:</p>
                <div class="rounded-xl flex items-center justify-center gap-3 p-4"
                     :style="`background: linear-gradient(135deg, ${formData.bg_from}, ${formData.bg_to}); height:70px;`">
                    <i :class="'bi ' + (formData.tag_icon || 'bi-trophy')" class="text-white text-3xl"></i>
                    <span class="text-white font-bold text-sm" x-text="formData.title || 'Preview'"></span>
                </div>
            </div>
        </div>
    </div>
</div>

<hr class="border-border">

{{-- Additional Images (appear in the detail popup) --}}
<div>
    <label class="form-label font-semibold">Additional Images <span class="text-xs font-normal text-muted-foreground">(shown in the popup when card is clicked)</span></label>

    {{-- Existing extra images when editing --}}
    <div x-show="formData.images_paths && formData.images_paths.length > 0" class="flex flex-wrap gap-2 mb-2">
        <template x-for="(path, idx) in formData.images_paths" :key="idx">
            <div class="relative group">
                <img :src="'/storage/' + path" class="w-20 h-20 object-cover rounded-lg border border-border">
                <button type="button"
                        @click="formData.images_paths.splice(idx, 1)"
                        class="absolute -top-1.5 -right-1.5 bg-red-500 text-white rounded-full w-5 h-5 text-xs flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                    <i class="bi bi-x"></i>
                </button>
            </div>
        </template>
    </div>
    <input type="hidden" name="keep_extra_images" :value="JSON.stringify(formData.images_paths ?? [])">

    <div id="achievementNewPreviews" class="flex flex-wrap gap-2 mb-2"></div>
    <div id="achievementBase64Inputs"></div>

    <button type="button" onclick="openAchievementCropper()"
            class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-50 transition-colors flex items-center gap-2 mt-1">
        <i class="bi bi-camera"></i> Add Image
    </button>
</div>
