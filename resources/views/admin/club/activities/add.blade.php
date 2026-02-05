<!-- Add Activity Modal -->
<div x-show="showAddModal"
     x-cloak
     x-init="$watch('showAddModal', value => { if (value && duplicateData) { setTimeout(() => initDuplicate(), 100) } })"
     class="fixed inset-0 z-50 overflow-y-auto"
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0">
    <!-- Backdrop -->
    <div class="fixed inset-0 bg-black/50" @click="showAddModal = false"></div>

    <!-- Modal Content -->
    <div class="flex min-h-full items-center justify-center p-4">
        <div class="modal-content border-0 shadow-lg w-full max-w-lg relative rounded-xl overflow-hidden"
             @click.stop>
            <!-- Header -->
            <div class="modal-header border-b border-border px-6 py-4">
                <div>
                    <h5 class="modal-title text-xl font-semibold">Create New Activity</h5>
                    <p class="text-sm text-muted-foreground mt-1">Configure your activity details</p>
                </div>
                <button type="button" class="text-muted-foreground hover:text-foreground transition-colors" @click="showAddModal = false">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <!-- Body -->
            <div class="modal-body px-6 py-6">
                <form id="addActivityForm" action="{{ route('admin.club.activities.store', $club->id) }}" method="POST" enctype="multipart/form-data">
                    @csrf

                    @if(session('success'))
                    <div class="mb-4 p-3 bg-success/10 border border-success/20 rounded-lg">
                        <p class="text-sm text-success">{{ session('success') }}</p>
                    </div>
                    @endif

                    @if ($errors->any())
                    <div class="mb-4 p-3 bg-destructive/10 border border-destructive/20 rounded-lg">
                        <ul class="text-sm text-destructive list-disc list-inside">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                    @endif

                    <div class="space-y-5">
                        <!-- Activity Title -->
                        <div class="space-y-2">
                            <label for="activityTitle" class="block text-sm font-medium text-foreground">
                                Activity Title <span class="text-destructive">*</span>
                            </label>
                            <input type="text"
                                   id="activityTitle"
                                   name="name"
                                   required
                                   placeholder="e.g., Morning Yoga Class"
                                   class="form-control">
                        </div>

                        <!-- Description -->
                        <div class="space-y-2">
                            <label for="activityDescription" class="block text-sm font-medium text-foreground">
                                Description
                            </label>
                            <textarea id="activityDescription"
                                      name="description"
                                      rows="3"
                                      placeholder="Detailed description of the activity..."
                                      class="form-control resize-none"></textarea>
                        </div>

                        <!-- Activity Picture -->
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-foreground">
                                Activity Picture
                            </label>
                            <div class="space-y-3">
                                <!-- Existing picture from duplication -->
                                <div id="existingPictureSection" class="hidden">
                                    <div class="flex items-center gap-3 p-3 bg-info/10 border border-info/20 rounded-lg">
                                        <img id="existingPictureImg" src="" alt="Existing" class="w-16 h-16 object-cover rounded">
                                        <div class="flex-1">
                                            <p class="text-sm font-medium text-foreground">Keep existing picture</p>
                                            <p class="text-xs text-muted-foreground">Or upload a new one below</p>
                                        </div>
                                        <button type="button" id="removeExistingPictureBtn" class="p-2 text-destructive hover:text-destructive/80">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                    </div>
                                    <input type="hidden" id="existingPictureUrl" name="existing_picture_url" value="">
                                </div>

                                <div class="flex items-center gap-3">
                                    <input type="file"
                                           id="activityPicture"
                                           name="picture"
                                           accept="image/jpeg,image/png,image/jpg,image/gif,image/webp"
                                           class="form-control flex-1">
                                    <button type="button" id="clearPictureBtn" class="p-2 text-muted-foreground hover:text-foreground hidden">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </div>

                                <!-- Preview -->
                                <div id="activityPicturePreview" class="hidden">
                                    <img id="picturePreviewImg" src="" alt="Preview" class="w-full h-32 object-cover rounded-lg border border-border">
                                </div>
                            </div>
                        </div>

                        <!-- Additional Notes -->
                        <div class="space-y-2">
                            <label for="activityNotes" class="block text-sm font-medium text-foreground">
                                Additional Notes
                            </label>
                            <textarea id="activityNotes"
                                      name="notes"
                                      rows="2"
                                      placeholder="Any additional information..."
                                      class="form-control resize-none"></textarea>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Footer -->
            <div class="modal-footer border-t border-border px-6 py-4 flex justify-end gap-3">
                <button type="button"
                        class="btn btn-outline-secondary"
                        @click="showAddModal = false">
                    Cancel
                </button>
                <button type="submit"
                        form="addActivityForm"
                        class="btn btn-primary flex items-center gap-2">
                    <i class="bi bi-plus-lg"></i>
                    <span>Create Activity</span>
                </button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const pictureInput = document.getElementById('activityPicture');
    const picturePreview = document.getElementById('activityPicturePreview');
    const picturePreviewImg = document.getElementById('picturePreviewImg');
    const clearPictureBtn = document.getElementById('clearPictureBtn');
    const existingPictureSection = document.getElementById('existingPictureSection');
    const existingPictureImg = document.getElementById('existingPictureImg');
    const existingPictureUrl = document.getElementById('existingPictureUrl');
    const removeExistingPictureBtn = document.getElementById('removeExistingPictureBtn');

    // Picture preview
    pictureInput?.addEventListener('change', function() {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                picturePreviewImg.src = e.target.result;
                picturePreview.classList.remove('hidden');
                clearPictureBtn.classList.remove('hidden');
                // Clear existing picture when new one is selected
                existingPictureUrl.value = '';
                existingPictureSection.classList.add('hidden');
            };
            reader.readAsDataURL(file);
        }
    });

    // Clear picture
    clearPictureBtn?.addEventListener('click', function() {
        pictureInput.value = '';
        picturePreview.classList.add('hidden');
        clearPictureBtn.classList.add('hidden');
    });

    // Remove existing picture
    removeExistingPictureBtn?.addEventListener('click', function() {
        existingPictureUrl.value = '';
        existingPictureSection.classList.add('hidden');
    });
});

// Function to initialize duplication from Alpine.js
function initDuplicate() {
    const component = document.querySelector('[x-data]')?.__x?.$data;
    if (component && component.duplicateData) {
        const data = component.duplicateData;
        document.getElementById('activityTitle').value = data.name + ' (Copy)';
        document.getElementById('activityDescription').value = data.description || '';
        document.getElementById('activityNotes').value = data.notes || '';

        if (data.pictureUrl) {
            document.getElementById('existingPictureImg').src = data.pictureUrl;
            document.getElementById('existingPictureUrl').value = data.pictureUrl;
            document.getElementById('existingPictureSection').classList.remove('hidden');
        }
    }
}

// Function to set existing picture when duplicating (backward compatibility)
function setExistingPicture(pictureUrl) {
    if (pictureUrl) {
        document.getElementById('existingPictureImg').src = pictureUrl;
        document.getElementById('existingPictureUrl').value = pictureUrl;
        document.getElementById('existingPictureSection').classList.remove('hidden');
    }
}
</script>
@endpush
