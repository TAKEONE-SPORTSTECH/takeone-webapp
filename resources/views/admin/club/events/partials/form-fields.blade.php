<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div class="md:col-span-2">
        <label class="form-label">Title <span class="text-red-500">*</span></label>
        <input type="text" name="title" class="form-control" required x-model="formData.title" placeholder="e.g. Open Sparring Night">
    </div>
    <div>
        <label class="form-label">Start Date <span class="text-red-500">*</span></label>
        <input type="date" name="date" class="form-control" required x-model="formData.date">
    </div>
    <div>
        <label class="form-label">End Date</label>
        <input type="date" name="end_date" class="form-control" x-model="formData.end_date">
    </div>
    <div>
        <label class="form-label">Start Time <span class="text-red-500">*</span></label>
        <input type="time" name="start_time" class="form-control" required x-model="formData.start_time">
    </div>
    <div>
        <label class="form-label">End Time</label>
        <input type="time" name="end_time" class="form-control" x-model="formData.end_time">
    </div>
    <div class="md:col-span-2">
        <label class="form-label">Color (date pill)</label>
        <input type="color" name="color" class="form-control h-10 p-1 cursor-pointer" x-model="formData.color">
    </div>
    <div class="md:col-span-2">
        <label class="form-label">Location</label>
        <div class="flex mb-2 border border-border rounded-lg overflow-hidden text-sm">
            <button type="button"
                    @click="locationTab = 'facility'"
                    :class="locationTab === 'facility' ? 'bg-primary text-primary-foreground' : 'bg-muted/40 text-muted-foreground hover:bg-muted'"
                    class="flex-1 py-1.5 px-3 font-medium transition-colors">
                <i class="bi bi-building mr-1"></i>Facility
            </button>
            <button type="button"
                    @click="locationTab = 'url'"
                    :class="locationTab === 'url' ? 'bg-primary text-primary-foreground' : 'bg-muted/40 text-muted-foreground hover:bg-muted'"
                    class="flex-1 py-1.5 px-3 font-medium transition-colors">
                <i class="bi bi-geo-alt mr-1"></i>Map URL
            </button>
        </div>
        <select x-show="locationTab === 'facility'" class="form-control" x-model="formData.location">
            <option value="">— No facility —</option>
            @foreach($facilities as $facility)
                <option value="{{ $facility->name }}">{{ $facility->name }}{{ $facility->address ? ' — ' . $facility->address : '' }}</option>
            @endforeach
        </select>
        <input type="text" x-show="locationTab === 'url'" class="form-control"
               placeholder="https://maps.google.com/..." x-model="formData.location">
        <input type="hidden" name="location" :value="formData.location">
    </div>
    <div>
        <label class="form-label">Level / Audience</label>
        <input type="text" name="level" class="form-control" placeholder="e.g. Ages 5+, All levels" x-model="formData.level">
    </div>
    <div>
        <label class="form-label">Max Capacity</label>
        <input type="number" name="max_capacity" class="form-control" min="1" placeholder="Leave empty for unlimited" x-model="formData.max_capacity">
    </div>
    <div>
        <label class="form-label">Cancel Within <span class="text-xs text-muted-foreground">(days after joining)</span></label>
        <input type="number" name="cancel_within_days" class="form-control" min="1" max="365" placeholder="Leave empty to allow anytime" x-model="formData.cancel_within_days">
        <p class="text-xs text-muted-foreground mt-1">After this many days, members can no longer leave the event.</p>
    </div>
    <div class="md:col-span-2">
        <label class="form-label">Tags <span class="text-xs text-muted-foreground">(comma-separated)</span></label>
        <input type="text" name="tags" class="form-control" placeholder="Public event, WT rules, Highlight reels" x-model="formData.tags_str">
    </div>
    <div class="md:col-span-2">
        <label class="form-label">Description</label>
        <textarea name="description" class="form-control" rows="3" placeholder="Short description shown on the event card..." x-model="formData.description"></textarea>
    </div>
    <div class="md:col-span-2">
        <label class="form-label">Event Images</label>

        {{-- Existing images --}}
        <div x-show="formData.images && formData.images.length > 0" class="flex flex-wrap gap-2 mb-2">
            <template x-for="(img, idx) in formData.images" :key="idx">
                <div class="relative group">
                    <img :src="img" class="w-20 h-20 object-cover rounded-lg border border-border">
                    <button type="button"
                            @click="formData.images.splice(idx, 1); formData.images_paths.splice(idx, 1)"
                            class="absolute -top-1.5 -right-1.5 bg-red-500 text-white rounded-full w-5 h-5 text-xs flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
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
            <i class="bi bi-camera"></i> Add Image
        </button>
    </div>
</div>
