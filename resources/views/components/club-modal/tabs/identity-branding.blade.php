@props(['club' => null, 'mode' => 'create'])

@php
    $isEdit = $mode === 'edit' && $club;
@endphp

<div class="px-0">
    <h5 class="font-bold mb-3">Identity & Branding</h5>
    <p class="text-muted-foreground mb-4">Define your club's public identity, URL, and visual branding</p>

    <!-- Club Slug -->
    <div class="mb-4">
        <label for="slug" class="form-label">
            Club Slug <span class="text-destructive">*</span>
        </label>
        <div class="input-group">
            <span class="input-group-text bg-white">
                <i class="bi bi-link-45deg"></i>
            </span>
            <input type="text"
                   class="form-control"
                   id="slug"
                   name="slug"
                   value="{{ $club->slug ?? old('slug') }}"
                   required
                   pattern="[a-z0-9-]+"
                   data-error-message="Slug is required and must be URL-friendly"
                   placeholder="e.g., bh-taekwondo">
        </div>
        <small class="text-muted-foreground">URL-friendly identifier (lowercase letters, numbers, and hyphens only)</small>
        <div class="invalid-feedback">Please enter a valid slug.</div>
    </div>

    <!-- Club URL Preview -->
    <div class="mb-4">
        <label class="form-label">Club Public URL</label>
        <div class="border border-border rounded-lg p-3 bg-muted/20">
            <div class="flex items-center gap-2">
                <i class="bi bi-globe text-primary"></i>
                <code id="clubUrlPreview" class="text-primary mb-0">{{ url('/club/') }}/{{ $club->slug ?? 'your-club-slug' }}</code>
                <button type="button" class="btn btn-sm btn-outline-primary ml-auto" onclick="copyClubUrl()">
                    <i class="bi bi-clipboard"></i>
                </button>
            </div>
        </div>
        <small class="text-muted-foreground">This is the public URL where members can view your club</small>
    </div>

    <!-- QR Code -->
    <div class="mb-4">
        <label class="form-label">Club QR Code</label>
        <div class="border border-border rounded-lg p-4 text-center bg-muted/10">
            <div id="qrCodeContainer" class="inline-block mb-3"></div>
            <div>
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="downloadQRCode()">
                    <i class="bi bi-download mr-2"></i>Download QR Code
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="printQRCode()">
                    <i class="bi bi-printer mr-2"></i>Print
                </button>
            </div>
        </div>
        <small class="text-muted-foreground">Share this QR code for easy access to your club's page</small>
    </div>

    <!-- Logo and Cover Images -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
        <div>
            <label class="form-label block">Club Logo <span class="text-destructive">*</span></label>
            <div class="text-center">
                <!-- Logo Preview -->
                <div class="cropper-preview-container mb-2" id="logoPreviewContainer">
                    @if($isEdit && $club->logo)
                    <img src="{{ asset('storage/' . $club->logo) }}"
                         id="logoPreview"
                         class="cropper-preview-image"
                         style="width: 150px; height: 150px; border-radius: 8px; border: 2px solid #dee2e6;">
                    @else
                    <div id="logoPreview"
                         class="cropper-preview-placeholder"
                         style="width: 150px; height: 150px; border-radius: 8px; border: 2px dashed #dee2e6; display: flex; align-items: center; justify-content: center; background-color: #f0f0f0; color: #6c757d;">
                        <i class="bi bi-image text-2xl"></i>
                    </div>
                    @endif
                </div>
                <input type="hidden" name="logo" id="logoInput" value="{{ $isEdit && $club->logo ? $club->logo : '' }}">
                <input type="hidden" name="logo_folder" value="clubs/logos">
                <input type="hidden" name="logo_filename" value="logo_{{ time() }}">
                <button type="button" class="btn btn-outline-primary btn-sm" onclick="openLogoCropper()">
                    <i class="bi bi-camera mr-2"></i>Upload Logo
                </button>
                <small class="text-muted-foreground block mt-2">Square image recommended (400x400px)</small>
                <small class="text-muted-foreground block">Used as main logo and favicon</small>
            </div>
        </div>
        <div>
            <label class="form-label block">Cover Image</label>
            <div class="text-center">
                <!-- Cover Preview -->
                <div class="cropper-preview-container mb-2" id="coverPreviewContainer">
                    @if($isEdit && $club->cover_image)
                    <img src="{{ asset('storage/' . $club->cover_image) }}"
                         id="coverPreview"
                         class="cropper-preview-image"
                         style="width: 250px; height: 83px; border-radius: 8px; border: 2px solid #dee2e6;">
                    @else
                    <div id="coverPreview"
                         class="cropper-preview-placeholder"
                         style="width: 250px; height: 83px; border-radius: 8px; border: 2px dashed #dee2e6; display: flex; align-items: center; justify-content: center; background-color: #f0f0f0; color: #6c757d;">
                        <i class="bi bi-image text-2xl"></i>
                    </div>
                    @endif
                </div>
                <input type="hidden" name="cover_image" id="coverInput" value="{{ $isEdit && $club->cover_image ? $club->cover_image : '' }}">
                <input type="hidden" name="cover_image_folder" value="clubs/covers">
                <input type="hidden" name="cover_image_filename" value="cover_{{ time() }}">
                <button type="button" class="btn btn-outline-primary btn-sm" onclick="openCoverCropper()">
                    <i class="bi bi-camera mr-2"></i>Upload Cover
                </button>
                <small class="text-muted-foreground block mt-2">Wide banner image (1200x400px)</small>
                <small class="text-muted-foreground block">Used for club profile header</small>
            </div>
        </div>
    </div>

    <!-- PART 2: Internal Cropper Overlays (NOT separate modals) -->
    <!-- Logo Cropper Overlay -->
    <div id="logoCropperOverlay" class="cropper-overlay" style="display: none;">
        <div class="cropper-panel">
            <div class="flex justify-between items-center mb-3">
                <h5 class="mb-0 font-semibold">Crop Logo</h5>
                <button type="button" class="btn-close" onclick="closeLogoCropper()"></button>
            </div>

            <input type="file" id="logoFileInput" class="form-control form-control-sm mb-3" accept="image/*">

            <div id="logoBox" class="takeone-canvas" style="height: 400px; background: #111; border-radius: 8px;"></div>

            <div class="grid grid-cols-2 gap-4 mt-3">
                <div>
                    <label class="form-label text-sm">Zoom</label>
                    <input type="range" class="w-full" id="logoZoom" min="0" max="100" step="1" value="0">
                </div>
                <div>
                    <label class="form-label text-sm">Rotation</label>
                    <input type="range" class="w-full" id="logoRotation" min="-180" max="180" step="1" value="0">
                </div>
            </div>

            <div class="flex gap-2 mt-3">
                <button type="button" class="btn btn-secondary flex-1" onclick="closeLogoCropper()">Cancel</button>
                <button type="button" class="btn btn-primary flex-1" onclick="saveLogoCrop()">Save & Apply</button>
            </div>
        </div>
    </div>

    <!-- Cover Cropper Overlay -->
    <div id="coverCropperOverlay" class="cropper-overlay" style="display: none;">
        <div class="cropper-panel">
            <div class="flex justify-between items-center mb-3">
                <h5 class="mb-0 font-semibold">Crop Cover Image</h5>
                <button type="button" class="btn-close" onclick="closeCoverCropper()"></button>
            </div>

            <input type="file" id="coverFileInput" class="form-control form-control-sm mb-3" accept="image/*">

            <div id="coverBox" class="takeone-canvas" style="height: 400px; background: #111; border-radius: 8px;"></div>

            <div class="grid grid-cols-2 gap-4 mt-3">
                <div>
                    <label class="form-label text-sm">Zoom</label>
                    <input type="range" class="w-full" id="coverZoom" min="0" max="100" step="1" value="0">
                </div>
                <div>
                    <label class="form-label text-sm">Rotation</label>
                    <input type="range" class="w-full" id="coverRotation" min="-180" max="180" step="1" value="0">
                </div>
            </div>

            <div class="flex gap-2 mt-3">
                <button type="button" class="btn btn-secondary flex-1" onclick="closeCoverCropper()">Cancel</button>
                <button type="button" class="btn btn-primary flex-1" onclick="saveCoverCrop()">Save & Apply</button>
            </div>
        </div>
    </div>

    <!-- Social Media Links -->
    <div class="mb-4">
        <label class="form-label">Social Media Links</label>
        <p class="text-muted-foreground text-sm mb-3">Add links to your club's social media profiles</p>

        <div id="socialLinksContainer">
            @if($isEdit && $club->socialLinks && $club->socialLinks->count() > 0)
                @foreach($club->socialLinks as $index => $link)
                    <div class="social-link-row mb-3" data-index="{{ $index }}">
                        <div class="grid grid-cols-12 gap-2">
                            <div class="col-span-12 md:col-span-4">
                                <select class="form-select" name="social_links[{{ $index }}][platform]" required>
                                    <option value="">Select Platform</option>
                                    <option value="facebook" {{ $link->platform === 'facebook' ? 'selected' : '' }}>
                                        Facebook
                                    </option>
                                    <option value="instagram" {{ $link->platform === 'instagram' ? 'selected' : '' }}>
                                        Instagram
                                    </option>
                                    <option value="twitter" {{ $link->platform === 'twitter' ? 'selected' : '' }}>
                                        X (Twitter)
                                    </option>
                                    <option value="tiktok" {{ $link->platform === 'tiktok' ? 'selected' : '' }}>
                                        TikTok
                                    </option>
                                    <option value="youtube" {{ $link->platform === 'youtube' ? 'selected' : '' }}>
                                        YouTube
                                    </option>
                                    <option value="whatsapp" {{ $link->platform === 'whatsapp' ? 'selected' : '' }}>
                                        WhatsApp
                                    </option>
                                    <option value="website" {{ $link->platform === 'website' ? 'selected' : '' }}>
                                        Website
                                    </option>
                                </select>
                            </div>
                            <div class="col-span-12 md:col-span-7">
                                <input type="url"
                                       class="form-control"
                                       name="social_links[{{ $index }}][url]"
                                       value="{{ $link->url }}"
                                       placeholder="https://..."
                                       required>
                            </div>
                            <div class="col-span-12 md:col-span-1">
                                <button type="button" class="btn btn-outline-danger w-full" onclick="removeSocialLink(this)">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                @endforeach
            @endif
        </div>

        <button type="button" class="btn btn-outline-primary btn-sm" onclick="addSocialLink()">
            <i class="bi bi-plus-circle mr-2"></i>Add Social Link
        </button>
    </div>
</div>

{{-- Styles moved to app.css (Phase 6) --}}

@push('scripts')
<link rel="stylesheet" href="https://unpkg.com/cropme@1.4.1/dist/cropme.min.css">
<script src="https://unpkg.com/cropme@1.4.1/dist/cropme.min.js"></script>

<script>
    let socialLinkIndex = {{ $isEdit && $club->socialLinks ? $club->socialLinks->count() : 0 }};
    let qrCode = null;

    // PART 2: Cropper instances
    let logoCropper = null;
    let coverCropper = null;
    const zoomMin = 0.01;
    const zoomMax = 3;

    document.addEventListener('DOMContentLoaded', function() {
        // Update URL preview when slug changes
        const slugInput = document.getElementById('slug');
        const urlPreview = document.getElementById('clubUrlPreview');

        if (slugInput && urlPreview) {
            slugInput.addEventListener('input', function() {
                const baseUrl = '{{ url("/club/") }}';
                const slug = this.value || 'your-club-slug';
                urlPreview.textContent = `${baseUrl}/${slug}`;

                // Regenerate QR code
                generateQRCode(`${baseUrl}/${slug}`);
            });

            // Mark slug as manually edited when user types
            slugInput.addEventListener('keydown', function() {
                this.dataset.manuallyEdited = 'true';
            });

            // Initial QR code generation
            const initialUrl = urlPreview.textContent;
            generateQRCode(initialUrl);
        }
    });

    // Generate QR Code
    function generateQRCode(url) {
        const container = document.getElementById('qrCodeContainer');
        if (!container) return;

        // Clear existing QR code
        container.innerHTML = '';

        // Generate new QR code
        qrCode = new QRCode(container, {
            text: url,
            width: 200,
            height: 200,
            colorDark: "#000000",
            colorLight: "#ffffff",
            correctLevel: QRCode.CorrectLevel.H
        });
    }

    // Copy club URL to clipboard
    function copyClubUrl() {
        const urlPreview = document.getElementById('clubUrlPreview');
        if (!urlPreview) return;

        const url = urlPreview.textContent;
        navigator.clipboard.writeText(url).then(() => {
            if (typeof Toast !== 'undefined') {
                Toast.success('Copied!', 'Club URL copied to clipboard');
            } else {
                alert('URL copied to clipboard!');
            }
        }).catch(err => {
            console.error('Failed to copy:', err);
        });
    }

    // Download QR Code
    function downloadQRCode() {
        const container = document.getElementById('qrCodeContainer');
        if (!container) return;

        const canvas = container.querySelector('canvas');
        if (!canvas) return;

        const url = canvas.toDataURL('image/png');
        const link = document.createElement('a');
        link.download = 'club-qr-code.png';
        link.href = url;
        link.click();
    }

    // Print QR Code
    function printQRCode() {
        const container = document.getElementById('qrCodeContainer');
        if (!container) return;

        const canvas = container.querySelector('canvas');
        if (!canvas) return;

        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <html>
                <head>
                    <title>Club QR Code</title>
                    <style>
                        body { display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; font-family: Arial, sans-serif; }
                        .container { text-align: center; }
                        img { max-width: 400px; height: auto; }
                        h2 { margin-top: 20px; }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <img src="${canvas.toDataURL('image/png')}" alt="Club QR Code">
                        <h2>${document.getElementById('club_name')?.value || 'Club QR Code'}</h2>
                        <p>${document.getElementById('clubUrlPreview')?.textContent || ''}</p>
                    </div>
                </body>
            </html>
        `);
        printWindow.document.close();
        printWindow.focus();
        setTimeout(() => {
            printWindow.print();
            printWindow.close();
        }, 250);
    }

    // Add social link row
    function addSocialLink() {
        const container = document.getElementById('socialLinksContainer');
        if (!container) return;

        const row = document.createElement('div');
        row.className = 'social-link-row mb-3';
        row.dataset.index = socialLinkIndex;
        row.innerHTML = `
            <div class="grid grid-cols-12 gap-2">
                <div class="col-span-12 md:col-span-4">
                    <select class="form-select" name="social_links[${socialLinkIndex}][platform]" required>
                        <option value="">Select Platform</option>
                        <option value="facebook">Facebook</option>
                        <option value="instagram">Instagram</option>
                        <option value="twitter">X (Twitter)</option>
                        <option value="tiktok">TikTok</option>
                        <option value="youtube">YouTube</option>
                        <option value="whatsapp">WhatsApp</option>
                        <option value="website">Website</option>
                    </select>
                </div>
                <div class="col-span-12 md:col-span-7">
                    <input type="url"
                           class="form-control"
                           name="social_links[${socialLinkIndex}][url]"
                           placeholder="https://..."
                           required>
                </div>
                <div class="col-span-12 md:col-span-1">
                    <button type="button" class="btn btn-outline-danger w-full" onclick="removeSocialLink(this)">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
        `;

        container.appendChild(row);
        socialLinkIndex++;
    }

    // Remove social link row
    function removeSocialLink(button) {
        const row = button.closest('.social-link-row');
        if (row) {
            row.remove();
        }
    }

    // ============================================
    // PART 2: Cropper Overlay Functions
    // ============================================

    function applyTransform(instance) {
        if (!instance.properties.image) return;
        const p = instance.properties;
        const t = `translate3d(${p.x}px, ${p.y}px, 0) scale(${p.scale}) rotate(${p.deg}deg)`;
        p.image.style.transform = t;
    }

    function initCropper(elementId, width, height, shape, aspectRatio) {
        const el = document.getElementById(elementId);
        if (!el) return null;

        const cropper = new Cropme(el, {
            container: { width: '100%', height: 400 },
            viewport: {
                width: width,
                height: height,
                type: shape,
                border: { enable: true, width: 2, color: '#fff' }
            },
            transformOrigin: 'viewport',
            zoom: { min: zoomMin, max: zoomMax, enable: true, mouseWheel: true, slider: false },
            rotation: { enable: true, slider: false }
        });

        return cropper;
    }

    // ===== LOGO CROPPER =====
    function openLogoCropper() {
        const overlay = document.getElementById('logoCropperOverlay');
        overlay.style.display = 'flex';
        const modalBody = document.querySelector('#clubModal .modal-body');
        if (modalBody) modalBody.style.overflow = 'hidden';
    }

    function closeLogoCropper() {
        const overlay = document.getElementById('logoCropperOverlay');
        overlay.style.display = 'none';
        const modalBody = document.querySelector('#clubModal .modal-body');
        if (modalBody) modalBody.style.overflow = 'auto';
        if (logoCropper) {
            logoCropper.destroy();
            logoCropper = null;
        }
        document.getElementById('logoFileInput').value = '';
    }

    function saveLogoCrop() {
        if (!logoCropper) return;
        logoCropper.crop({ type: 'base64', width: 400, height: 400 }).then(base64 => {
            document.getElementById('logoInput').value = base64;
            const preview = document.getElementById('logoPreview');
            if (preview && preview.tagName === 'IMG') {
                preview.src = base64;
            } else {
                const container = document.getElementById('logoPreviewContainer');
                if (container) {
                    container.innerHTML = `<img src="${base64}" id="logoPreview" class="cropper-preview-image" style="width: 150px; height: 150px; border-radius: 8px; border: 2px solid #dee2e6;">`;
                }
            }
            closeLogoCropper();
        });
    }

    // Logo file input handler
    document.addEventListener('DOMContentLoaded', function() {
        const logoFileInput = document.getElementById('logoFileInput');
        if (logoFileInput) {
            logoFileInput.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    const reader = new FileReader();
                    reader.onload = function(event) {
                        if (logoCropper) logoCropper.destroy();
                        logoCropper = initCropper('logoBox', 400, 400, 'square', 1);
                        if (logoCropper) {
                            logoCropper.bind({ url: event.target.result }).then(() => {
                                document.getElementById('logoZoom').value = 0;
                                document.getElementById('logoRotation').value = 0;
                            });
                        }
                    };
                    reader.readAsDataURL(this.files[0]);
                }
            });
        }

        const logoZoom = document.getElementById('logoZoom');
        if (logoZoom) {
            logoZoom.addEventListener('input', function() {
                if (!logoCropper || !logoCropper.properties.image) return;
                const p = parseFloat(this.value);
                const scale = zoomMin + (zoomMax - zoomMin) * (p / 100);
                logoCropper.properties.scale = Math.min(Math.max(scale, zoomMin), zoomMax);
                applyTransform(logoCropper);
            });
        }

        const logoRotation = document.getElementById('logoRotation');
        if (logoRotation) {
            logoRotation.addEventListener('input', function() {
                if (logoCropper) logoCropper.rotate(parseInt(this.value, 10));
            });
        }
    });

    // ===== COVER CROPPER =====
    function openCoverCropper() {
        const overlay = document.getElementById('coverCropperOverlay');
        overlay.style.display = 'flex';
        const modalBody = document.querySelector('#clubModal .modal-body');
        if (modalBody) modalBody.style.overflow = 'hidden';
    }

    function closeCoverCropper() {
        const overlay = document.getElementById('coverCropperOverlay');
        overlay.style.display = 'none';
        const modalBody = document.querySelector('#clubModal .modal-body');
        if (modalBody) modalBody.style.overflow = 'auto';
        if (coverCropper) {
            coverCropper.destroy();
            coverCropper = null;
        }
        document.getElementById('coverFileInput').value = '';
    }

    function saveCoverCrop() {
        if (!coverCropper) return;
        coverCropper.crop({ type: 'base64', width: 1200, height: 400 }).then(base64 => {
            document.getElementById('coverInput').value = base64;
            const preview = document.getElementById('coverPreview');
            if (preview && preview.tagName === 'IMG') {
                preview.src = base64;
            } else {
                const container = document.getElementById('coverPreviewContainer');
                if (container) {
                    container.innerHTML = `<img src="${base64}" id="coverPreview" class="cropper-preview-image" style="width: 250px; height: 83px; border-radius: 8px; border: 2px solid #dee2e6;">`;
                }
            }
            closeCoverCropper();
        });
    }

    // Cover file input handler
    document.addEventListener('DOMContentLoaded', function() {
        const coverFileInput = document.getElementById('coverFileInput');
        if (coverFileInput) {
            coverFileInput.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    const reader = new FileReader();
                    reader.onload = function(event) {
                        if (coverCropper) coverCropper.destroy();
                        coverCropper = initCropper('coverBox', 600, 200, 'square', 3);
                        if (coverCropper) {
                            coverCropper.bind({ url: event.target.result }).then(() => {
                                document.getElementById('coverZoom').value = 0;
                                document.getElementById('coverRotation').value = 0;
                            });
                        }
                    };
                    reader.readAsDataURL(this.files[0]);
                }
            });
        }

        const coverZoom = document.getElementById('coverZoom');
        if (coverZoom) {
            coverZoom.addEventListener('input', function() {
                if (!coverCropper || !coverCropper.properties.image) return;
                const p = parseFloat(this.value);
                const scale = zoomMin + (zoomMax - zoomMin) * (p / 100);
                coverCropper.properties.scale = Math.min(Math.max(scale, zoomMin), zoomMax);
                applyTransform(coverCropper);
            });
        }

        const coverRotation = document.getElementById('coverRotation');
        if (coverRotation) {
            coverRotation.addEventListener('input', function() {
                if (coverCropper) coverCropper.rotate(parseInt(this.value, 10));
            });
        }
    });
</script>
@endpush
