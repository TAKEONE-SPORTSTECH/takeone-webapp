<!-- Edit Activity Modal -->
<div class="modal fade" id="editActivityModal" tabindex="-1" aria-labelledby="editActivityModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 32rem;">
        <div class="modal-content border-0 shadow-lg rounded-xl overflow-hidden">
            <!-- Header -->
            <div class="modal-header border-b border-gray-200 px-6 py-4">
                <div>
                    <h5 class="modal-title text-xl font-semibold" id="editActivityModalLabel">Edit Activity</h5>
                    <p class="text-sm text-gray-500 mt-1">Update activity details</p>
                </div>
                <button type="button" class="text-gray-400 hover:text-gray-600 transition-colors" data-bs-dismiss="modal" aria-label="Close">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <!-- Body -->
            <div class="modal-body px-6 py-6">
                <form id="editActivityForm" method="POST" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')

                    @if ($errors->any())
                    <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg">
                        <ul class="text-sm text-red-600 list-disc list-inside">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                    @endif

                    <div class="space-y-5">
                        <!-- Activity Title -->
                        <div class="space-y-2">
                            <label for="editActivityName" class="block text-sm font-medium text-gray-700">
                                Activity Title <span class="text-red-500">*</span>
                            </label>
                            <input type="text"
                                   id="editActivityName"
                                   name="name"
                                   required
                                   placeholder="e.g., Morning Yoga Class"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                        </div>

                        <!-- Description -->
                        <div class="space-y-2">
                            <label for="editActivityDescription" class="block text-sm font-medium text-gray-700">
                                Description
                            </label>
                            <textarea id="editActivityDescription"
                                      name="description"
                                      rows="3"
                                      placeholder="Detailed description of the activity..."
                                      class="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent resize-none"></textarea>
                        </div>

                        <!-- Current Picture Preview -->
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-gray-700">Current Picture</label>
                            <div id="currentPictureContainer" class="hidden">
                                <img id="currentPictureImg" src="" alt="Current" class="w-full h-32 object-cover rounded-lg border border-gray-200">
                            </div>
                            <p id="noPictureMsg" class="text-sm text-gray-400">No picture uploaded</p>
                        </div>

                        <!-- Activity Picture -->
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-gray-700">
                                Change Picture
                            </label>
                            <div class="space-y-3">
                                <div class="flex items-center gap-3">
                                    <input type="file"
                                           id="editActivityPicture"
                                           name="picture"
                                           accept="image/jpeg,image/png,image/jpg,image/gif,image/webp"
                                           class="flex-1 text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-primary file:text-white hover:file:bg-primary/90 cursor-pointer border border-gray-300 rounded-lg">
                                    <button type="button" id="clearEditPictureBtn" class="p-2 text-gray-400 hover:text-gray-600 hidden">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </div>

                                <!-- New Picture Preview -->
                                <div id="editPicturePreview" class="hidden">
                                    <img id="editPreviewImg" src="" alt="Preview" class="w-full h-32 object-cover rounded-lg border border-gray-200">
                                </div>
                            </div>
                        </div>

                        <!-- Additional Notes -->
                        <div class="space-y-2">
                            <label for="editActivityNotes" class="block text-sm font-medium text-gray-700">
                                Additional Notes
                            </label>
                            <textarea id="editActivityNotes"
                                      name="notes"
                                      rows="2"
                                      placeholder="Any additional information..."
                                      class="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent resize-none"></textarea>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Footer -->
            <div class="modal-footer border-t border-gray-200 px-6 py-4 flex justify-end gap-3">
                <button type="button"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
                        data-bs-dismiss="modal">
                    Cancel
                </button>
                <button type="submit"
                        form="editActivityForm"
                        class="px-4 py-2 text-sm font-medium text-white bg-primary rounded-lg hover:bg-primary/90 transition-colors flex items-center gap-2">
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
    const modal = document.getElementById('editActivityModal');
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

    // Reset form on modal close
    modal.addEventListener('hidden.bs.modal', function() {
        form.reset();
        picturePreview.classList.add('hidden');
        clearPictureBtn.classList.add('hidden');
    });

    // Handle edit button clicks
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

        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();
    };
});
</script>
@endpush
