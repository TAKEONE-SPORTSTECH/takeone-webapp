@props(['aspectRatio' => 1, 'maxSize' => 1024 * 1024, 'title' => 'Upload Image', 'uploadUrl' => '#'])

<div class="modal fade" id="imageUploadModal" tabindex="-1" aria-labelledby="imageUploadModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="imageUploadModalLabel">{{ $title }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- File Input -->
                <div class="mb-3" id="fileInputContainer">
                    <label for="imageFile" class="form-label">Select Image</label>
                    <input type="file" class="form-control" id="imageFile" accept="image/*" required>
                </div>

                <!-- Cropper Container -->
                <div id="cropperContainer" class="d-none">
                    <div class="text-center mb-3">
                        <div id="cropboxContainer" style="width: 400px; height: 400px; margin: 0 auto; border: 1px solid #ccc;">
                            <img id="imagePreview" alt="Image Preview" style="display: block;">
                        </div>
                    </div>
                    <div class="d-flex justify-content-center gap-2 mb-3">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="zoomIn">
                            <i class="fas fa-search-plus"></i> Zoom In
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="zoomOut">
                            <i class="fas fa-search-minus"></i> Zoom Out
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="reset">
                            <i class="fas fa-sync"></i> Reset
                        </button>
                    </div>
                </div>

                <!-- Upload Progress -->
                <div id="uploadProgress" class="d-none">
                    <div class="progress mb-3">
                        <div class="progress-bar" role="progressbar" style="width: 0%"></div>
                    </div>
                    <p class="text-center">Compressing and uploading...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="uploadBtn" disabled>Upload</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let $cropbox = null;
    const fileInput = document.getElementById('imageFile');
    const cropperContainer = document.getElementById('cropperContainer');
    const fileInputContainer = document.getElementById('fileInputContainer');
    const imagePreview = document.getElementById('imagePreview');
    const uploadBtn = document.getElementById('uploadBtn');
    const uploadProgress = document.getElementById('uploadProgress');

    // File selection
    fileInput.addEventListener('change', function(event) {
        const file = event.target.files[0];
        if (!file) return;

        // Validate file type
        if (!file.type.startsWith('image/')) {
            alert('Please select a valid image file.');
            return;
        }

        // Validate file size (rough check before compression)
        if (file.size > {{ $maxSize }} * 2) {
            alert('File is too large. Please select a smaller image.');
            return;
        }

        const reader = new FileReader();
        reader.onload = function(e) {
            // Hide file input, show cropper
            fileInputContainer.classList.add('d-none');
            cropperContainer.classList.remove('d-none');
            uploadBtn.disabled = false;

            // Set image source
            imagePreview.src = e.target.result;

            // Initialize jquery-cropbox with track ball controls
            $cropbox = $('#imagePreview').cropbox({
                width: 200,
                height: 200,
                showControls: true
            });
        };
        reader.readAsDataURL(file);
    });

    // Zoom controls
    document.getElementById('zoomIn').addEventListener('click', function() {
        if ($cropbox) {
            $cropbox.cropbox('zoomIn');
        }
    });

    document.getElementById('zoomOut').addEventListener('click', function() {
        if ($cropbox) {
            $cropbox.cropbox('zoomOut');
        }
    });

    document.getElementById('reset').addEventListener('click', function() {
        if ($cropbox) {
            $cropbox.cropbox('reset');
        }
    });

    // Upload button
    uploadBtn.addEventListener('click', async function() {
        if (!$cropbox) return;

        uploadBtn.disabled = true;
        uploadProgress.classList.remove('d-none');

        try {
            // Get cropped blob from cropbox
            const blob = $cropbox.cropbox('getBlob');

            // Compress if needed
            let finalBlob = blob;
            if (blob.size > {{ $maxSize }}) {
                const progressBar = uploadProgress.querySelector('.progress-bar');
                finalBlob = await window.imageCompression(blob, {
                    maxSizeMB: {{ $maxSize }} / (1024 * 1024),
                    maxWidthOrHeight: 1920,
                    onProgress: (progress) => {
                        progressBar.style.width = progress + '%';
                    }
                });
            }

            // Create form data
            const formData = new FormData();
            formData.append('image', finalBlob, 'profile-picture.jpg');

            // Upload
            const response = await fetch('{{ $uploadUrl }}', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            });

            if (!response.ok) {
                throw new Error('Upload failed');
            }

            const result = await response.json();

            // Close modal and refresh
            const modal = bootstrap.Modal.getInstance(document.getElementById('imageUploadModal'));
            modal.hide();

            // Reset form
            fileInput.value = '';
            cropperContainer.classList.add('d-none');
            fileInputContainer.classList.remove('d-none');
            uploadProgress.classList.add('d-none');
            uploadBtn.disabled = true;

            // Trigger success callback or reload
            if (window.imageUploadSuccess) {
                window.imageUploadSuccess(result);
            } else {
                location.reload();
            }

        } catch (error) {
            console.error('Upload error:', error);
            alert('Upload failed: ' + error.message);
            uploadBtn.disabled = false;
            uploadProgress.classList.add('d-none');
        }
    });
});
</script>
