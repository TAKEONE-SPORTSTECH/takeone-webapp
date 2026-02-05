<!-- Edit Activity Modal -->
<div x-show="showEditModal"
     x-cloak
     x-init="$watch('showEditModal', value => { if (value) { setTimeout(() => initEditForm(), 100) } })"
     class="fixed inset-0 z-50 overflow-y-auto"
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0">
    <!-- Backdrop -->
    <div class="fixed inset-0 bg-black/50" @click="showEditModal = false"></div>

    <!-- Modal Content -->
    <div class="flex min-h-full items-center justify-center p-4">
        <div class="modal-content border-0 shadow-lg w-full max-w-lg relative rounded-xl overflow-hidden"
             @click.stop>
            <!-- Header -->
            <div class="modal-header border-b border-border px-6 py-4">
                <div>
                    <h5 class="modal-title text-xl font-semibold">Edit Activity</h5>
                    <p class="text-sm text-muted-foreground mt-1">Update activity details</p>
                </div>
                <button type="button" class="text-muted-foreground hover:text-foreground transition-colors" @click="showEditModal = false">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <!-- Body -->
            <div class="modal-body px-6 py-6">
                <form id="editActivityForm" method="POST" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')

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
                            <label for="editActivityName" class="block text-sm font-medium text-foreground">
                                Activity Title <span class="text-destructive">*</span>
                            </label>
                            <input type="text"
                                   id="editActivityName"
                                   name="name"
                                   required
                                   placeholder="e.g., Morning Yoga Class"
                                   class="form-control">
                        </div>

                        <!-- Description -->
                        <div class="space-y-2">
                            <label for="editActivityDescription" class="block text-sm font-medium text-foreground">
                                Description
                            </label>
                            <textarea id="editActivityDescription"
                                      name="description"
                                      rows="3"
                                      placeholder="Detailed description of the activity..."
                                      class="form-control resize-none"></textarea>
                        </div>

                        <!-- Current Picture Preview -->
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-foreground">Current Picture</label>
                            <div id="currentPictureContainer" class="hidden">
                                <img id="currentPictureImg" src="" alt="Current" class="w-full h-32 object-cover rounded-lg border border-border">
                            </div>
                            <p id="noPictureMsg" class="text-sm text-muted-foreground">No picture uploaded</p>
                        </div>

                        <!-- Activity Picture -->
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-foreground">
                                Change Picture
                            </label>
                            <div class="space-y-3">
                                <div class="flex items-center gap-3">
                                    <input type="file"
                                           id="editActivityPicture"
                                           name="picture"
                                           accept="image/jpeg,image/png,image/jpg,image/gif,image/webp"
                                           class="form-control flex-1">
                                    <button type="button" id="clearEditPictureBtn" class="p-2 text-muted-foreground hover:text-foreground hidden">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </div>

                                <!-- New Picture Preview -->
                                <div id="editPicturePreview" class="hidden">
                                    <img id="editPreviewImg" src="" alt="Preview" class="w-full h-32 object-cover rounded-lg border border-border">
                                </div>
                            </div>
                        </div>

                        <!-- Additional Notes -->
                        <div class="space-y-2">
                            <label for="editActivityNotes" class="block text-sm font-medium text-foreground">
                                Additional Notes
                            </label>
                            <textarea id="editActivityNotes"
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
                        @click="showEditModal = false">
                    Cancel
                </button>
                <button type="submit"
                        form="editActivityForm"
                        class="btn btn-primary flex items-center gap-2">
                    <i class="bi bi-check-lg"></i>
                    <span>Update Activity</span>
                </button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('editActivityForm');
    const pictureInput = document.getElementById('editActivityPicture');
    const picturePreview = document.getElementById('editPicturePreview');
    const previewImg = document.getElementById('editPreviewImg');
    const clearPictureBtn = document.getElementById('clearEditPictureBtn');
    const currentPictureContainer = document.getElementById('currentPictureContainer');
    const currentPictureImg = document.getElementById('currentPictureImg');
    const noPictureMsg = document.getElementById('noPictureMsg');

    // Picture preview
    pictureInput?.addEventListener('change', function() {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                previewImg.src = e.target.result;
                picturePreview.classList.remove('hidden');
                clearPictureBtn.classList.remove('hidden');
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

    // Initialize edit form from Alpine.js
    window.initEditForm = function() {
        const component = document.querySelector('[x-data]')?.__x?.$data;
        if (component && component.editData) {
            const data = component.editData;
            form.action = data.action;
            document.getElementById('editActivityName').value = data.name || '';
            document.getElementById('editActivityDescription').value = data.description || '';
            document.getElementById('editActivityNotes').value = data.notes || '';

            // Show current picture if exists
            if (data.pictureUrl) {
                currentPictureImg.src = data.pictureUrl;
                currentPictureContainer.classList.remove('hidden');
                noPictureMsg.classList.add('hidden');
            } else {
                currentPictureContainer.classList.add('hidden');
                noPictureMsg.classList.remove('hidden');
            }

            // Reset new picture preview
            picturePreview.classList.add('hidden');
            pictureInput.value = '';
        }
    };

    // Handle edit button clicks (backward compatibility)
    window.openEditActivityModal = function(activityId, name, description, notes, pictureUrl, updateUrl) {
        form.action = updateUrl;
        document.getElementById('editActivityName').value = name || '';
        document.getElementById('editActivityDescription').value = description || '';
        document.getElementById('editActivityNotes').value = notes || '';

        // Show current picture if exists
        if (pictureUrl) {
            currentPictureImg.src = pictureUrl;
            currentPictureContainer.classList.remove('hidden');
            noPictureMsg.classList.add('hidden');
        } else {
            currentPictureContainer.classList.add('hidden');
            noPictureMsg.classList.remove('hidden');
        }

        // Reset new picture preview
        picturePreview.classList.add('hidden');
        pictureInput.value = '';
    };
});
</script>
@endpush
