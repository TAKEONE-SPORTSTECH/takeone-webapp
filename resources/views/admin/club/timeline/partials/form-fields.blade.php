<div class="space-y-4">
    <div>
        <label class="form-label">Body <span class="text-red-500">*</span></label>
        <textarea name="body" class="form-control" rows="4"
                  placeholder="What's happening at your club..."
                  x-model="formData.body" required></textarea>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="form-label">Category <span class="text-red-500">*</span></label>
            <select name="category" class="form-control" x-model="formData.category">
                <option value="Announcement">Announcement</option>
                <option value="Highlight">Highlight</option>
                <option value="Community">Community</option>
                <option value="Update">Update</option>
            </select>
        </div>
        <div>
            <label class="form-label">Date & Time <span class="text-red-500">*</span></label>
            <input type="datetime-local" name="posted_at" class="form-control"
                   x-model="formData.posted_at" required>
        </div>
        <div>
            <label class="form-label">Status</label>
            <select name="status" class="form-control" x-model="formData.status">
                <option value="published">Published</option>
                <option value="draft">Draft</option>
            </select>
        </div>
        <div>
            <label class="form-label">Image <span class="text-xs text-muted-foreground">(optional)</span></label>
            <input type="file" name="image" class="form-control" accept="image/*"
                   @change="handleImageChange($event)">
        </div>
    </div>

    {{-- Image preview --}}
    <div x-show="formData.image_preview || formData.image_path" class="mt-2">
        <div class="relative inline-block">
            <img :src="formData.image_preview || (formData.image_path ? '/storage/' + formData.image_path : '')"
                 class="rounded-lg object-cover" style="max-height:160px; max-width:100%;" alt="Preview">
            <button type="button"
                    class="absolute top-1 right-1 bg-white rounded-full w-6 h-6 flex items-center justify-center shadow text-red-500 text-xs"
                    @click="removeImage()">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        {{-- Hidden field to signal image removal --}}
        <input type="hidden" name="remove_image" :value="formData.remove_image ? '1' : '0'">
    </div>
</div>
