<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div class="md:col-span-2">
        <label class="form-label">Title <span class="text-red-500">*</span></label>
        <input type="text" name="title" class="form-control" required placeholder="e.g. Open Sparring Night">
    </div>
    <div>
        <label class="form-label">Date <span class="text-red-500">*</span></label>
        <input type="date" name="date" class="form-control" required>
    </div>
    <div>
        <label class="form-label">Color (date pill)</label>
        <input type="color" name="color" class="form-control h-10 p-1 cursor-pointer" value="#1d4ed8">
    </div>
    <div>
        <label class="form-label">Start Time <span class="text-red-500">*</span></label>
        <input type="time" name="start_time" class="form-control" required>
    </div>
    <div>
        <label class="form-label">End Time</label>
        <input type="time" name="end_time" class="form-control">
    </div>
    <div>
        <label class="form-label">Location</label>
        <input type="text" name="location" class="form-control" placeholder="e.g. Main Arena">
    </div>
    <div>
        <label class="form-label">Level / Audience</label>
        <input type="text" name="level" class="form-control" placeholder="e.g. Ages 5+, All levels">
    </div>
    <div>
        <label class="form-label">Max Capacity</label>
        <input type="number" name="max_capacity" class="form-control" min="1" placeholder="Leave empty for unlimited">
    </div>
    <div>
        <label class="form-label">Spots Taken</label>
        <input type="number" name="spots_taken" class="form-control" min="0" value="0">
    </div>
    <div>
        <label class="form-label">Ribbon Label</label>
        <input type="text" name="ribbon_label" class="form-control" placeholder="e.g. Limited Seats">
    </div>
    <div>
        <label class="form-label">Ribbon Style</label>
        <select name="ribbon_type" class="form-control">
            <option value="">Default (green)</option>
            <option value="limited">Limited (red)</option>
        </select>
    </div>
    <div>
        <label class="form-label">CTA Button Text</label>
        <input type="text" name="cta_text" class="form-control" placeholder="e.g. Join Event">
    </div>
    <div>
        <label class="form-label">Status</label>
        <select name="status" class="form-control">
            <option value="active">Active</option>
            <option value="completed">Completed</option>
            <option value="cancelled">Cancelled</option>
        </select>
    </div>
    <div class="md:col-span-2">
        <label class="form-label">Tags <span class="text-xs text-muted-foreground">(comma-separated)</span></label>
        <input type="text" name="tags" class="form-control" placeholder="Public event, WT rules, Highlight reels">
    </div>
    <div class="md:col-span-2">
        <label class="form-label">Description</label>
        <textarea name="description" class="form-control" rows="3" placeholder="Short description shown on the event card..."></textarea>
    </div>
</div>
