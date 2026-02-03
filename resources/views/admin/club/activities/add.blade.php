<!-- Add Activity Modal -->
<div class="modal fade" id="addActivityModal" tabindex="-1" aria-labelledby="addActivityModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 32rem;">
        <div class="modal-content border-0 shadow-lg rounded-xl overflow-hidden">
            <!-- Header -->
            <div class="modal-header border-b border-gray-200 px-6 py-4">
                <div>
                    <h5 class="modal-title text-xl font-semibold" id="addActivityModalLabel">Create New Activity</h5>
                    <p class="text-sm text-gray-500 mt-1">Configure your activity details</p>
                </div>
                <button type="button" class="text-gray-400 hover:text-gray-600 transition-colors" data-bs-dismiss="modal" aria-label="Close">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <!-- Body -->
            <div class="modal-body px-6 py-6">
                <form id="addActivityForm" action="{{ route('admin.club.activities.store', $club->id) }}" method="POST" enctype="multipart/form-data">
                    @csrf

                    @if(session('success'))
                    <div class="mb-4 p-3 bg-green-50 border border-green-200 rounded-lg">
                        <p class="text-sm text-green-600">{{ session('success') }}</p>
                    </div>
                    @endif

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
                            <label for="activityTitle" class="block text-sm font-medium text-gray-700">
                                Activity Title <span class="text-red-500">*</span>
                            </label>
                            <input type="text"
                                   id="activityTitle"
                                   name="name"
                                   required
                                   placeholder="e.g., Morning Yoga Class"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                        </div>

                        <!-- Description -->
                        <div class="space-y-2">
                            <label for="activityDescription" class="block text-sm font-medium text-gray-700">
                                Description
                            </label>
                            <textarea id="activityDescription"
                                      name="description"
                                      rows="3"
                                      placeholder="Detailed description of the activity..."
                                      class="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent resize-none"></textarea>
                        </div>

                        <!-- Activity Picture -->
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-gray-700">
                                Activity Picture
                            </label>
                            <div class="space-y-3">
                                <!-- Existing picture from duplication -->
                                <div id="existingPictureSection" class="hidden">
                                    <div class="flex items-center gap-3 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                                        <img id="existingPictureImg" src="" alt="Existing" class="w-16 h-16 object-cover rounded">
                                        <div class="flex-1">
                                            <p class="text-sm font-medium text-gray-700">Keep existing picture</p>
                                            <p class="text-xs text-gray-500">Or upload a new one below</p>
                                        </div>
                                        <button type="button" id="removeExistingPictureBtn" class="p-2 text-red-500 hover:text-red-700">
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
                                           class="flex-1 text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-primary file:text-white hover:file:bg-primary/90 cursor-pointer border border-gray-300 rounded-lg">
                                    <button type="button" id="clearPictureBtn" class="p-2 text-gray-400 hover:text-gray-600 hidden">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </div>

                                <!-- Preview -->
                                <div id="activityPicturePreview" class="hidden">
                                    <img id="picturePreviewImg" src="" alt="Preview" class="w-full h-32 object-cover rounded-lg border border-gray-200">
                                </div>
                            </div>
                        </div>

                        <!-- Additional Notes -->
                        <div class="space-y-2">
                            <label for="activityNotes" class="block text-sm font-medium text-gray-700">
                                Additional Notes
                            </label>
                            <textarea id="activityNotes"
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
                        form="addActivityForm"
                        class="px-4 py-2 text-sm font-medium text-white bg-primary rounded-lg hover:bg-primary/90 transition-colors flex items-center gap-2">
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
    const modal = document.getElementById('addActivityModal');
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

    // Reset form on modal close
    modal.addEventListener('hidden.bs.modal', function() {
        document.getElementById('addActivityForm').reset();
        picturePreview.classList.add('hidden');
        clearPictureBtn.classList.add('hidden');
        existingPictureSection.classList.add('hidden');
        existingPictureUrl.value = '';
    });
});

// Function to set existing picture when duplicating
function setExistingPicture(pictureUrl) {
    if (pictureUrl) {
        document.getElementById('existingPictureImg').src = pictureUrl;
        document.getElementById('existingPictureUrl').value = pictureUrl;
        document.getElementById('existingPictureSection').classList.remove('hidden');
    }
}
</script>
@endpush
