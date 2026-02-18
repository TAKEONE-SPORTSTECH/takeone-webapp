<!-- Add Picture Modal -->
<div x-show="showUploadModal"
     x-cloak
     class="fixed inset-0 z-50 overflow-y-auto"
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0">
    <!-- Backdrop -->
    <div class="fixed inset-0 bg-black/50" @click="showUploadModal = false"></div>

    <!-- Modal Content -->
    <div class="flex min-h-full items-center justify-center p-4">
        <div class="modal-content border-0 shadow-lg w-full max-w-md relative rounded-lg overflow-hidden"
             x-data="{ currentTab: 'file' }"
             @click.stop>
            <!-- Header -->
            <div class="modal-header border-b border-border px-6 py-4">
                <h5 class="modal-title text-lg font-semibold">Add Picture to Gallery</h5>
                <button type="button" class="text-muted-foreground hover:text-foreground transition-colors" @click="showUploadModal = false">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <!-- Body -->
            <div class="modal-body px-6 py-4">
                <!-- Tabs -->
                <div class="w-full mb-4">
                    <div class="grid grid-cols-2 gap-1 p-1 bg-muted/30 rounded-lg" role="tablist">
                        <button type="button"
                                class="flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium rounded-md transition-all"
                                :class="currentTab === 'file' ? 'bg-white shadow-sm text-foreground' : 'text-muted-foreground hover:text-foreground'"
                                @click="currentTab = 'file'">
                            <i class="bi bi-image"></i>
                            Upload File
                        </button>
                        <button type="button"
                                class="flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium rounded-md transition-all"
                                :class="currentTab === 'url' ? 'bg-white shadow-sm text-foreground' : 'text-muted-foreground hover:text-foreground'"
                                @click="currentTab = 'url'">
                            <i class="bi bi-link-45deg"></i>
                            Image URL
                        </button>
                    </div>
                </div>

                <!-- File Upload Tab Content -->
                <div x-show="currentTab === 'file'">
                    <form id="fileUploadForm" action="{{ route('admin.club.gallery.upload', $club->slug) }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" name="upload_type" value="file">

                        <div class="space-y-4">
                            <div class="space-y-2">
                                <label for="imageFile" class="block text-sm font-medium text-foreground">Select Image</label>
                                <div class="flex flex-col gap-3">
                                    <input type="file"
                                           id="imageFile"
                                           name="images[]"
                                           accept="image/jpeg,image/png,image/jpg,image/gif,image/webp"
                                           multiple
                                           class="form-control">
                                    <p class="text-xs text-muted-foreground">
                                        Supported formats: JPEG, PNG, GIF, WebP. Max size: 10MB
                                    </p>
                                </div>
                            </div>

                            <!-- Preview Section -->
                            <div id="filePreviewSection" class="space-y-2 hidden">
                                <div class="flex items-center justify-between">
                                    <label class="block text-sm font-medium text-foreground">Preview</label>
                                    <button type="button"
                                            id="clearFileBtn"
                                            class="text-xs text-muted-foreground hover:text-foreground px-2 py-1 rounded hover:bg-muted/30 transition-colors">
                                        Clear
                                    </button>
                                </div>
                                <div id="filePreviewContainer" class="grid grid-cols-2 gap-2">
                                    <!-- Previews will be inserted here -->
                                </div>
                            </div>

                            <!-- Caption -->
                            <div class="space-y-2">
                                <label for="fileCaption" class="block text-sm font-medium text-foreground">Caption (optional)</label>
                                <input type="text"
                                       id="fileCaption"
                                       name="caption"
                                       placeholder="Enter caption..."
                                       class="form-control">
                            </div>
                        </div>
                    </form>
                </div>

                <!-- URL Tab Content -->
                <div x-show="currentTab === 'url'" x-cloak>
                    <form id="urlUploadForm" action="{{ route('admin.club.gallery.upload', $club->slug) }}" method="POST">
                        @csrf
                        <input type="hidden" name="upload_type" value="url">

                        <div class="space-y-4">
                            <div class="space-y-2">
                                <label for="imageUrl" class="block text-sm font-medium text-foreground">Image URL</label>
                                <input type="url"
                                       id="imageUrl"
                                       name="image_url"
                                       placeholder="https://example.com/image.jpg"
                                       class="form-control">
                            </div>

                            <!-- URL Preview Section -->
                            <div id="urlPreviewSection" class="space-y-2 hidden">
                                <label class="block text-sm font-medium text-foreground">Preview</label>
                                <img id="urlPreviewImage"
                                     src=""
                                     alt="Preview"
                                     class="w-full h-48 object-cover rounded-md border border-border">
                            </div>

                            <!-- Caption -->
                            <div class="space-y-2">
                                <label for="urlCaption" class="block text-sm font-medium text-foreground">Caption (optional)</label>
                                <input type="text"
                                       id="urlCaption"
                                       name="caption"
                                       placeholder="Enter caption..."
                                       class="form-control">
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Footer -->
            <div class="modal-footer border-t border-border px-6 py-4 flex justify-end gap-3">
                <button type="button"
                        class="btn btn-outline-secondary"
                        @click="showUploadModal = false">
                    Cancel
                </button>
                <button type="button"
                        id="submitUploadBtn"
                        class="btn btn-primary flex items-center gap-2"
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

    // Watch for tab changes via Alpine.js
    document.addEventListener('alpine:initialized', () => {
        // The tab is managed by Alpine.js now
    });

    // File input change
    fileInput?.addEventListener('change', function() {
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
                        <img src="${e.target.result}" alt="Preview ${index + 1}" class="w-full h-24 object-cover rounded-md border border-border">
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
    clearFileBtn?.addEventListener('click', function() {
        fileInput.value = '';
        filePreviewContainer.innerHTML = '';
        filePreviewSection.classList.add('hidden');
        updateSubmitButton();
    });

    // URL input change
    let urlTimeout;
    urlInput?.addEventListener('input', function() {
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
    urlPreviewImage?.addEventListener('error', function() {
        urlPreviewSection.classList.add('hidden');
    });

    // Update submit button state
    function updateSubmitButton() {
        // Get current tab from Alpine.js state
        const modalContent = document.querySelector('[x-data*="currentTab"]');
        const alpineData = modalContent?.__x?.$data;
        currentTab = alpineData?.currentTab || 'file';

        let isValid = false;
        if (currentTab === 'file') {
            isValid = fileInput?.files && fileInput.files.length > 0;
            uploadBtnText.textContent = 'Upload';
        } else {
            isValid = urlInput?.value.trim() !== '';
            uploadBtnText.textContent = 'Add Picture';
        }
        submitBtn.disabled = !isValid;
    }

    // Submit button click
    submitBtn?.addEventListener('click', function() {
        const modalContent = document.querySelector('[x-data*="currentTab"]');
        const alpineData = modalContent?.__x?.$data;
        currentTab = alpineData?.currentTab || 'file';

        const form = currentTab === 'file' ? document.getElementById('fileUploadForm') : document.getElementById('urlUploadForm');

        // Show loading state
        this.disabled = true;
        uploadIcon.classList.add('bi-arrow-repeat', 'animate-spin');
        uploadIcon.classList.remove('bi-upload');
        uploadBtnText.textContent = currentTab === 'file' ? 'Uploading...' : 'Adding...';

        form.submit();
    });

    // Re-check submit button when tab changes
    setInterval(updateSubmitButton, 500);
});
</script>
@endpush

{{-- animate-spin is a native Tailwind CSS 4 utility --}}
