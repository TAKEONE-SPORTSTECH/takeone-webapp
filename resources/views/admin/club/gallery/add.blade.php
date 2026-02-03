<!-- Add Picture Modal -->
<div class="modal fade" id="uploadImageModal" tabindex="-1" aria-labelledby="uploadImageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 28rem;">
        <div class="modal-content border-0 shadow-lg rounded-lg overflow-hidden">
            <!-- Header -->
            <div class="modal-header border-b border-gray-200 px-6 py-4">
                <h5 class="modal-title text-lg font-semibold" id="uploadImageModalLabel">Add Picture to Gallery</h5>
                <button type="button" class="text-gray-400 hover:text-gray-600 transition-colors" data-bs-dismiss="modal" aria-label="Close">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <!-- Body -->
            <div class="modal-body px-6 py-4">
                <!-- Tabs -->
                <div class="w-full mb-4">
                    <div class="grid grid-cols-2 gap-1 p-1 bg-gray-100 rounded-lg" role="tablist">
                        <button type="button"
                                class="gallery-tab-btn flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium rounded-md transition-all bg-white shadow-sm text-gray-900"
                                data-tab="file"
                                role="tab"
                                aria-selected="true">
                            <i class="bi bi-image"></i>
                            Upload File
                        </button>
                        <button type="button"
                                class="gallery-tab-btn flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium rounded-md transition-all text-gray-500 hover:text-gray-700"
                                data-tab="url"
                                role="tab"
                                aria-selected="false">
                            <i class="bi bi-link-45deg"></i>
                            Image URL
                        </button>
                    </div>
                </div>

                <!-- File Upload Tab Content -->
                <div class="gallery-tab-content" id="gallery-tab-file">
                    <form id="fileUploadForm" action="{{ route('admin.club.gallery.upload', $club->id) }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" name="upload_type" value="file">

                        <div class="space-y-4">
                            <div class="space-y-2">
                                <label for="imageFile" class="block text-sm font-medium text-gray-700">Select Image</label>
                                <div class="flex flex-col gap-3">
                                    <input type="file"
                                           id="imageFile"
                                           name="images[]"
                                           accept="image/jpeg,image/png,image/jpg,image/gif,image/webp"
                                           multiple
                                           class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-primary file:text-white hover:file:bg-primary/90 cursor-pointer border border-gray-300 rounded-md">
                                    <p class="text-xs text-gray-500">
                                        Supported formats: JPEG, PNG, GIF, WebP. Max size: 10MB
                                    </p>
                                </div>
                            </div>

                            <!-- Preview Section -->
                            <div id="filePreviewSection" class="space-y-2 hidden">
                                <div class="flex items-center justify-between">
                                    <label class="block text-sm font-medium text-gray-700">Preview</label>
                                    <button type="button"
                                            id="clearFileBtn"
                                            class="text-xs text-gray-500 hover:text-gray-700 px-2 py-1 rounded hover:bg-gray-100 transition-colors">
                                        Clear
                                    </button>
                                </div>
                                <div id="filePreviewContainer" class="grid grid-cols-2 gap-2">
                                    <!-- Previews will be inserted here -->
                                </div>
                            </div>

                            <!-- Caption -->
                            <div class="space-y-2">
                                <label for="fileCaption" class="block text-sm font-medium text-gray-700">Caption (optional)</label>
                                <input type="text"
                                       id="fileCaption"
                                       name="caption"
                                       placeholder="Enter caption..."
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                            </div>
                        </div>
                    </form>
                </div>

                <!-- URL Tab Content -->
                <div class="gallery-tab-content hidden" id="gallery-tab-url">
                    <form id="urlUploadForm" action="{{ route('admin.club.gallery.upload', $club->id) }}" method="POST">
                        @csrf
                        <input type="hidden" name="upload_type" value="url">

                        <div class="space-y-4">
                            <div class="space-y-2">
                                <label for="imageUrl" class="block text-sm font-medium text-gray-700">Image URL</label>
                                <input type="url"
                                       id="imageUrl"
                                       name="image_url"
                                       placeholder="https://example.com/image.jpg"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                            </div>

                            <!-- URL Preview Section -->
                            <div id="urlPreviewSection" class="space-y-2 hidden">
                                <label class="block text-sm font-medium text-gray-700">Preview</label>
                                <img id="urlPreviewImage"
                                     src=""
                                     alt="Preview"
                                     class="w-full h-48 object-cover rounded-md border border-gray-200">
                            </div>

                            <!-- Caption -->
                            <div class="space-y-2">
                                <label for="urlCaption" class="block text-sm font-medium text-gray-700">Caption (optional)</label>
                                <input type="text"
                                       id="urlCaption"
                                       name="caption"
                                       placeholder="Enter caption..."
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Footer -->
            <div class="modal-footer border-t border-gray-200 px-6 py-4 flex justify-end gap-3">
                <button type="button"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 transition-colors"
                        data-bs-dismiss="modal">
                    Cancel
                </button>
                <button type="button"
                        id="submitUploadBtn"
                        class="px-4 py-2 text-sm font-medium text-white bg-primary rounded-md hover:bg-primary/90 transition-colors flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
                        disabled>
                    <i class="bi bi-upload" id="uploadIcon"></i>
                    <span id="uploadBtnText">Upload</span>
                </button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const tabBtns = document.querySelectorAll('#uploadImageModal .gallery-tab-btn');
    const tabContents = document.querySelectorAll('#uploadImageModal .gallery-tab-content');
    const fileInput = document.getElementById('imageFile');
    const filePreviewSection = document.getElementById('filePreviewSection');
    const filePreviewContainer = document.getElementById('filePreviewContainer');
    const clearFileBtn = document.getElementById('clearFileBtn');
    const urlInput = document.getElementById('imageUrl');
    const urlPreviewSection = document.getElementById('urlPreviewSection');
    const urlPreviewImage = document.getElementById('urlPreviewImage');
    const submitBtn = document.getElementById('submitUploadBtn');
    const uploadBtnText = document.getElementById('uploadBtnText');
    const uploadIcon = document.getElementById('uploadIcon');

    let currentTab = 'file';

    // Tab switching
    tabBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const tab = this.dataset.tab;
            currentTab = tab;

            // Update tab button styles
            tabBtns.forEach(b => {
                b.classList.remove('bg-white', 'shadow-sm', 'text-gray-900');
                b.classList.add('text-gray-500', 'hover:text-gray-700');
                b.setAttribute('aria-selected', 'false');
            });
            this.classList.remove('text-gray-500', 'hover:text-gray-700');
            this.classList.add('bg-white', 'shadow-sm', 'text-gray-900');
            this.setAttribute('aria-selected', 'true');

            // Show/hide tab content
            tabContents.forEach(content => {
                content.classList.add('hidden');
            });
            document.getElementById('gallery-tab-' + tab).classList.remove('hidden');

            // Update button text
            updateSubmitButton();
        });
    });

    // File input change
    fileInput.addEventListener('change', function() {
        const files = this.files;
        if (files.length > 0) {
            filePreviewContainer.innerHTML = '';
            filePreviewSection.classList.remove('hidden');

            Array.from(files).forEach((file, index) => {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const div = document.createElement('div');
                    div.className = 'relative';
                    div.innerHTML = `
                        <img src="${e.target.result}" alt="Preview ${index + 1}" class="w-full h-24 object-cover rounded-md border border-gray-200">
                        <span class="absolute bottom-1 right-1 bg-black/50 text-white text-xs px-1 rounded">${(file.size / 1024 / 1024).toFixed(2)} MB</span>
                    `;
                    filePreviewContainer.appendChild(div);
                };
                reader.readAsDataURL(file);
            });

            updateSubmitButton();
        }
    });

    // Clear file selection
    clearFileBtn.addEventListener('click', function() {
        fileInput.value = '';
        filePreviewContainer.innerHTML = '';
        filePreviewSection.classList.add('hidden');
        updateSubmitButton();
    });

    // URL input change
    let urlTimeout;
    urlInput.addEventListener('input', function() {
        clearTimeout(urlTimeout);
        const url = this.value.trim();

        if (url) {
            urlTimeout = setTimeout(() => {
                urlPreviewImage.src = url;
                urlPreviewSection.classList.remove('hidden');
            }, 500);
        } else {
            urlPreviewSection.classList.add('hidden');
        }
        updateSubmitButton();
    });

    // URL image error handling
    urlPreviewImage.addEventListener('error', function() {
        urlPreviewSection.classList.add('hidden');
    });

    // Update submit button state
    function updateSubmitButton() {
        let isValid = false;
        if (currentTab === 'file') {
            isValid = fileInput.files && fileInput.files.length > 0;
            uploadBtnText.textContent = 'Upload';
        } else {
            isValid = urlInput.value.trim() !== '';
            uploadBtnText.textContent = 'Add Picture';
        }
        submitBtn.disabled = !isValid;
    }

    // Submit button click
    submitBtn.addEventListener('click', function() {
        const form = currentTab === 'file' ? document.getElementById('fileUploadForm') : document.getElementById('urlUploadForm');

        // Show loading state
        this.disabled = true;
        uploadIcon.classList.add('bi-arrow-repeat', 'animate-spin');
        uploadIcon.classList.remove('bi-upload');
        uploadBtnText.textContent = currentTab === 'file' ? 'Uploading...' : 'Adding...';

        form.submit();
    });

    // Reset on modal close
    const modal = document.getElementById('uploadImageModal');
    modal.addEventListener('hidden.bs.modal', function() {
        fileInput.value = '';
        filePreviewContainer.innerHTML = '';
        filePreviewSection.classList.add('hidden');
        urlInput.value = '';
        urlPreviewSection.classList.add('hidden');
        document.getElementById('fileCaption').value = '';
        document.getElementById('urlCaption').value = '';
        uploadIcon.classList.remove('bi-arrow-repeat', 'animate-spin');
        uploadIcon.classList.add('bi-upload');
        uploadBtnText.textContent = 'Upload';
        updateSubmitButton();
    });
});
</script>
@endpush

@push('styles')
<style>
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
    .animate-spin {
        animation: spin 1s linear infinite;
    }
    .space-y-2 > * + * {
        margin-top: 0.5rem;
    }
    .space-y-4 > * + * {
        margin-top: 1rem;
    }
</style>
@endpush
