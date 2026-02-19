@props(['aspectRatio' => 1, 'maxSize' => 1024 * 1024, 'title' => 'Upload Image', 'uploadUrl' => '#'])

<!-- Modal using Alpine.js -->
<div x-data="imageUploadModal()" x-cloak>
    <!-- Modal Backdrop -->
    <div x-show="open"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-black/50 z-50"
         @click="close()">
    </div>

    <!-- Modal Content -->
    <div x-show="open"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         class="fixed inset-0 z-50 overflow-y-auto"
         @click.self="close()">
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="w-full max-w-2xl bg-white rounded-2xl shadow-xl" @click.stop>
                <!-- Modal Header -->
                <div class="flex items-center justify-between p-6 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">{{ $title }}</h3>
                    <button @click="close()" class="p-2 hover:bg-gray-100 rounded-lg transition-colors">
                        <i class="bi bi-x-lg text-gray-500"></i>
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="p-6">
                    <!-- File Input -->
                    <div x-show="!showCropper" class="mb-4">
                        <label for="imageFile" class="tf-label mb-2">Select Image</label>
                        <input type="file"
                               id="imageFile"
                               accept="image/*"
                               @change="handleFileSelect($event)"
                               class="tf-file"
                               required>
                    </div>

                    <!-- Cropper Container -->
                    <div x-show="showCropper">
                        <div class="text-center mb-4">
                            <div id="cropboxContainer" class="mx-auto border border-gray-300 rounded-lg" style="width: 400px; height: 400px;">
                                <img id="imagePreview" alt="Image Preview" class="block">
                            </div>
                        </div>
                        <div class="flex justify-center gap-2 mb-4">
                            <button type="button" @click="zoomIn()" class="px-4 py-2 text-sm border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                                <i class="bi bi-zoom-in mr-1"></i> Zoom In
                            </button>
                            <button type="button" @click="zoomOut()" class="px-4 py-2 text-sm border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                                <i class="bi bi-zoom-out mr-1"></i> Zoom Out
                            </button>
                            <button type="button" @click="reset()" class="px-4 py-2 text-sm border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                                <i class="bi bi-arrow-counterclockwise mr-1"></i> Reset
                            </button>
                        </div>
                    </div>

                    <!-- Upload Progress -->
                    <div x-show="uploading">
                        <div class="w-full h-2 bg-gray-200 rounded-full mb-3 overflow-hidden">
                            <div class="h-full bg-primary rounded-full transition-all duration-300" :style="'width: ' + progress + '%'"></div>
                        </div>
                        <p class="text-center text-gray-600">Compressing and uploading...</p>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="flex justify-end gap-3 p-6 border-t border-gray-200">
                    <button type="button"
                            @click="close()"
                            class="px-6 py-2 text-gray-700 bg-gray-100 rounded-xl hover:bg-gray-200 transition-colors">
                        Cancel
                    </button>
                    <button type="button"
                            @click="upload()"
                            :disabled="!canUpload || uploading"
                            class="px-6 py-2 text-white bg-primary rounded-xl hover:bg-primary/90 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                        Upload
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function imageUploadModal() {
    return {
        open: false,
        showCropper: false,
        canUpload: false,
        uploading: false,
        progress: 0,
        $cropbox: null,

        init() {
            // Global function to open modal
            window.openImageUploadModal = () => this.open = true;
        },

        close() {
            this.open = false;
            this.showCropper = false;
            this.canUpload = false;
            this.uploading = false;
            this.progress = 0;
            const fileInput = document.getElementById('imageFile');
            if (fileInput) fileInput.value = '';
        },

        handleFileSelect(event) {
            const file = event.target.files[0];
            if (!file) return;

            if (!file.type.startsWith('image/')) {
                alert('Please select a valid image file.');
                return;
            }

            if (file.size > {{ $maxSize }} * 2) {
                alert('File is too large. Please select a smaller image.');
                return;
            }

            const reader = new FileReader();
            reader.onload = (e) => {
                this.showCropper = true;
                this.canUpload = true;

                const imagePreview = document.getElementById('imagePreview');
                imagePreview.src = e.target.result;

                // Initialize jquery-cropbox
                this.$cropbox = $('#imagePreview').cropbox({
                    width: 200,
                    height: 200,
                    showControls: true
                });
            };
            reader.readAsDataURL(file);
        },

        zoomIn() {
            if (this.$cropbox) this.$cropbox.cropbox('zoomIn');
        },

        zoomOut() {
            if (this.$cropbox) this.$cropbox.cropbox('zoomOut');
        },

        reset() {
            if (this.$cropbox) this.$cropbox.cropbox('reset');
        },

        async upload() {
            if (!this.$cropbox) return;

            this.canUpload = false;
            this.uploading = true;

            try {
                const blob = this.$cropbox.cropbox('getBlob');

                let finalBlob = blob;
                if (blob.size > {{ $maxSize }}) {
                    finalBlob = await window.imageCompression(blob, {
                        maxSizeMB: {{ $maxSize }} / (1024 * 1024),
                        maxWidthOrHeight: 1920,
                        onProgress: (p) => { this.progress = p; }
                    });
                }

                const formData = new FormData();
                formData.append('image', finalBlob, 'profile-picture.jpg');

                const response = await fetch('{{ $uploadUrl }}', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    }
                });

                if (!response.ok) throw new Error('Upload failed');

                const result = await response.json();
                this.close();

                if (window.imageUploadSuccess) {
                    window.imageUploadSuccess(result);
                } else {
                    location.reload();
                }

            } catch (error) {
                console.error('Upload error:', error);
                alert('Upload failed: ' + error.message);
                this.canUpload = true;
                this.uploading = false;
            }
        }
    }
}
</script>
