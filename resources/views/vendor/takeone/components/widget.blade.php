@php
    $id = $attributes->get('id');
    $width = $attributes->get('width', 300);
    $height = $attributes->get('height', 300);
    $shape = $attributes->get('shape', 'circle');
    $folder = $attributes->get('folder', 'uploads');
    $filename = $attributes->get('filename', 'cropped_' . time());
    $uploadUrl = $attributes->get('uploadUrl', '');
    $currentImage = $attributes->get('currentImage', '');
    $buttonText = $attributes->get('buttonText', 'Change Photo');
    $buttonClass = $attributes->get('buttonClass', 'btn btn-success px-4 font-bold shadow-sm');
    $mode = $attributes->get('mode', 'ajax'); // 'ajax' or 'form'
    $inputName = $attributes->get('inputName', 'image'); // For form mode - the hidden input name
    $previewWidth = $attributes->get('previewWidth', $width);
    $previewHeight = $attributes->get('previewHeight', $height);
    $showPreview = $attributes->get('showPreview', $mode === 'form'); // Show preview by default in form mode

    // Cropper canvas/viewport customization
    $canvasHeight = $attributes->get('canvasHeight', 500);       // Height of the crop canvas area
    $modalMaxWidth = $attributes->get('modalMaxWidth', '75%');    // Modal max-width CSS value
    $modalWidth = $attributes->get('modalWidth', 1000);           // Modal width in px
    $viewportFill = $attributes->get('viewportFill', 0.75);      // How much of the canvas the viewport fills (0-1)
    $maxScale = $attributes->get('maxScale', 3);                  // Max scale multiplier for auto-sizing
    $uploadAsIs = $attributes->get('uploadAsIs', false);          // Show "Upload As Is" button alongside crop
    $uploadAsIsText = $attributes->get('uploadAsIsText', 'Upload As Is'); // Label for the as-is button
    $inline = filter_var($attributes->get('inline', false), FILTER_VALIDATE_BOOLEAN); // Render crop UI inline (no modal popup)
@endphp

<x-toast-notification />

@once
    <link rel="stylesheet" href="https://unpkg.com/cropme@1.4.1/dist/cropme.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/cropme@1.4.1/dist/cropme.min.js"></script>
    {{-- Styles moved to app.css (Phase 6) --}}
@endonce

@if($inline)
{{-- Inline mode: render the crop UI directly in the page (no modal popup), like a form section. --}}
<div class="cropper-inline-wrapper bg-white rounded-xl border border-border p-4 text-center" id="cropperInline_{{ $id }}">
    @if($mode === 'form')
    {{-- Preview box (shows the current/cropped image) + the hidden inputs the form submits. --}}
    <div class="cropper-preview-container mb-3" id="previewContainer_{{ $id }}">
        @if($currentImage)
        <img src="{{ $currentImage }}" id="preview_{{ $id }}" class="cropper-preview-image"
             style="width: {{ $previewWidth }}px; height: {{ $previewHeight }}px; border-radius: {{ $shape === 'circle' ? '50%' : '8px' }};">
        <button type="button" class="cropper-remove-btn" id="removeBtn_{{ $id }}" onclick="removeImage_{{ $id }}()"><i class="bi bi-x"></i></button>
        @else
        <div id="preview_{{ $id }}" class="cropper-preview-placeholder"
             style="width: {{ $previewWidth }}px; height: {{ $previewHeight }}px; border-radius: {{ $shape === 'circle' ? '50%' : '8px' }};">
            <i class="bi bi-image" style="font-size: 2rem;"></i>
        </div>
        @endif
    </div>
    <input type="hidden" name="{{ $inputName }}" id="hiddenInput_{{ $id }}" value="">
    <input type="hidden" name="{{ $inputName }}_folder" value="{{ $folder }}">
    <input type="hidden" name="{{ $inputName }}_filename" value="{{ $filename }}">
    @endif
    @include('takeone::components.widget-crop-body', ['showClose' => false])
</div>
@elseif($mode === 'form')
{{-- Form mode: Show preview and hidden input --}}
<div class="cropper-form-wrapper text-center" id="wrapper_{{ $id }}">
    <div class="cropper-preview-container mb-2" id="previewContainer_{{ $id }}">
        @if($currentImage)
        <img src="{{ $currentImage }}"
             id="preview_{{ $id }}"
             class="cropper-preview-image"
             style="width: {{ $previewWidth }}px; height: {{ $previewHeight }}px; border-radius: {{ $shape === 'circle' ? '50%' : '8px' }};">
        <button type="button" class="cropper-remove-btn" id="removeBtn_{{ $id }}" onclick="removeImage_{{ $id }}()">
            <i class="bi bi-x"></i>
        </button>
        @else
        <div id="preview_{{ $id }}"
             class="cropper-preview-placeholder"
             style="width: {{ $previewWidth }}px; height: {{ $previewHeight }}px; border-radius: {{ $shape === 'circle' ? '50%' : '8px' }};">
            <i class="bi bi-image" style="font-size: 2rem;"></i>
        </div>
        @endif
    </div>
    <input type="hidden" name="{{ $inputName }}" id="hiddenInput_{{ $id }}" value="">
    <input type="hidden" name="{{ $inputName }}_folder" value="{{ $folder }}">
    <input type="hidden" name="{{ $inputName }}_filename" value="{{ $filename }}">
    <button type="button" class="{{ $buttonClass }}" onclick="window.bsModal.show(document.querySelector('#cropperModal_{{ $id }}'))">
        <i class="bi bi-camera mr-2"></i>{{ $buttonText }}
    </button>
</div>
@else
{{-- AJAX mode: Just the button --}}
<button type="button" class="{{ $buttonClass }}" onclick="window.bsModal.show(document.querySelector('#cropperModal_{{ $id }}'))">
    <i class="bi bi-camera{{ $buttonText ? ' mr-2' : '' }}"></i>{{ $buttonText }}
</button>
@endif

@if(!$inline)
@push('modals')
<div class="modal fade" id="cropperModal_{{ $id }}" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width: {{ $modalMaxWidth }}; width: {{ $modalWidth }}px;">
        <div class="modal-content modal-content-clean shadow-lg">
            <div class="modal-body p-4 text-left" style="max-height: 85vh; overflow-y: auto;">
                @include('takeone::components.widget-crop-body', ['showClose' => true])
            </div>
        </div>
    </div>
</div>
@endpush
@endif

@push('scripts')
<script>
$(function() {
    let cropper_{{ $id }} = null;
    const el_{{ $id }} = document.getElementById("box_{{ $id }}");
    const zoomMin_{{ $id }} = 0.01;
    const zoomMax_{{ $id }} = 3;
    const currentImage_{{ $id }} = '{{ $currentImage }}';
    const mode_{{ $id }} = '{{ $mode }}';

    function applyTransform_{{ $id }}(instance) {
        if (!instance.properties.image) return;
        const p = instance.properties;
        const t = `translate3d(${p.x}px, ${p.y}px, 0) scale(${p.scale}) rotate(${p.deg}deg)`;
        p.image.style.transform = t;
    }

    function initCropper_{{ $id }}(imageUrl) {
        if (cropper_{{ $id }}) cropper_{{ $id }}.destroy();

        cropper_{{ $id }} = new Cropme(el_{{ $id }}, {
            container: { width: '100%', height: {{ $canvasHeight }} },
            viewport: {
                width: {{ $width }},
                height: {{ $height }},
                type: '{{ $shape }}',
                border: { enable: true, width: 2, color: '#fff' }
            },
            transformOrigin: 'viewport',
            zoom: { min: zoomMin_{{ $id }}, max: zoomMax_{{ $id }}, enable: true, mouseWheel: true, slider: false },
            rotation: { enable: true, slider: false }
        });

        cropper_{{ $id }}.bind({ url: imageUrl }).then(() => {
            $('#zoom_{{ $id }}').val(0);
            $('#rot_{{ $id }}').val(0);
        });
    }

@if($inline)
    // Inline mode: the crop editor is its own overlay surface, hidden until the user picks a
    // file or takes a photo. It's moved onto <body> so `position: fixed` resolves against the
    // viewport even when an ancestor (the mobile shell) carries a CSS transform.
    let editorMoved_{{ $id }} = false;
    function showEditor_{{ $id }}() {
        const ed = document.getElementById('editor_{{ $id }}');
        if (!ed) return;
        if (!editorMoved_{{ $id }}) {
            document.body.appendChild(ed);
            editorMoved_{{ $id }} = true;
        }
        ed.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
    function hideEditor_{{ $id }}() {
        const ed = document.getElementById('editor_{{ $id }}');
        if (ed) ed.style.display = 'none';
        document.body.style.overflow = '';
        // Destroy the cropper and reset the pickers so the next open starts clean.
        if (cropper_{{ $id }}) { cropper_{{ $id }}.destroy(); cropper_{{ $id }} = null; }
        const fi = document.getElementById('input_{{ $id }}');
        const ci = document.getElementById('cameraInput_{{ $id }}');
        if (fi) fi.value = '';
        if (ci) ci.value = '';
    }
    document.getElementById('cancel_{{ $id }}')?.addEventListener('click', hideEditor_{{ $id }});
    document.getElementById('cancelBtn_{{ $id }}')?.addEventListener('click', hideEditor_{{ $id }});
    document.getElementById('editorBackdrop_{{ $id }}')?.addEventListener('click', hideEditor_{{ $id }});
@else
    // Load current image when modal opens
    $('#cropperModal_{{ $id }}').on('shown.bs.modal', function() {
        if (currentImage_{{ $id }} && !cropper_{{ $id }}) {
            initCropper_{{ $id }}(currentImage_{{ $id }});
        }
    });
@endif

    function handleFileSelect_{{ $id }}(input) {
        if (input.files && input.files[0]) {
@if($inline)
            // Reveal the editor synchronously so the canvas is on-screen before Cropme measures it.
            showEditor_{{ $id }}();
@endif
            const reader = new FileReader();
            reader.onload = function(event) {
                initCropper_{{ $id }}(event.target.result);
            };
            reader.readAsDataURL(input.files[0]);
        }
    }

    $('#input_{{ $id }}').on('change', function() {
        handleFileSelect_{{ $id }}(this);
    });

    // "Take Photo" → open the device camera (capture input), then feed the same cropper flow.
    // On desktop (no camera capture support) this falls back to the normal file picker.
    $('#camera_{{ $id }}').on('click', function() {
        const camInput = document.getElementById('cameraInput_{{ $id }}');
        if (camInput && 'capture' in document.createElement('input')) {
            camInput.click();
        } else {
            document.getElementById('input_{{ $id }}').click();
        }
    });

    $('#cameraInput_{{ $id }}').on('change', function() {
        handleFileSelect_{{ $id }}(this);
        // mirror the chosen file into the visible input's label by syncing the as-is path
        if (this.files && this.files[0]) {
            try {
                const dt = new DataTransfer();
                dt.items.add(this.files[0]);
                document.getElementById('input_{{ $id }}').files = dt.files;
            } catch (e) { /* DataTransfer unsupported — cropper still works */ }
        }
    });

    $('#zoom_{{ $id }}').on('input', function() {
        if (!cropper_{{ $id }} || !cropper_{{ $id }}.properties.image) return;
        const p = parseFloat($(this).val());
        const scale = zoomMin_{{ $id }} + (zoomMax_{{ $id }} - zoomMin_{{ $id }}) * (p / 100);
        cropper_{{ $id }}.properties.scale = Math.min(Math.max(scale, zoomMin_{{ $id }}), zoomMax_{{ $id }});
        applyTransform_{{ $id }}(cropper_{{ $id }});
    });

    $('#rot_{{ $id }}').on('input', function() {
        if (cropper_{{ $id }}) {
            cropper_{{ $id }}.rotate(parseInt($(this).val(), 10));
        }
    });

    $('#save_{{ $id }}').on('click', function() {
        if (!cropper_{{ $id }}) return;
        const btn = $(this);

        if (mode_{{ $id }} === 'form') {
            // Form mode: Store base64 in hidden input and update preview
            btn.prop('disabled', true).text('Processing...');

            cropper_{{ $id }}.crop({ type: 'base64' }).then(base64 => {
                // Store in hidden input
                $('#hiddenInput_{{ $id }}').val(base64);

                // Update preview
                const previewContainer = $('#previewContainer_{{ $id }}');
                const borderRadius = '{{ $shape }}' === 'circle' ? '50%' : '8px';

                previewContainer.html(`
                    <img src="${base64}"
                         id="preview_{{ $id }}"
                         class="cropper-preview-image"
                         style="width: {{ $previewWidth }}px; height: {{ $previewHeight }}px; border-radius: ${borderRadius};">
                    <button type="button" class="cropper-remove-btn" id="removeBtn_{{ $id }}" onclick="removeImage_{{ $id }}()">
                        <i class="bi bi-x"></i>
                    </button>
                `);
                previewContainer.addClass('has-image');

                // Close modal
                (function(){ var _m = document.querySelector('#cropperModal_{{ $id }}'); if (_m && window.bsModal) { try { window.bsModal.hide(_m); } catch(e){} } })();
@if($inline)
                hideEditor_{{ $id }}();
@endif
                btn.prop('disabled', false).text('Crop & Apply');
            });
        } else {
            // AJAX mode: Upload to server
            btn.prop('disabled', true).text('Uploading...');

            cropper_{{ $id }}.crop({ type: 'base64' }).then(base64 => {
                // Resize to viewport dimensions before upload to avoid sending full-resolution image
                const img = new Image();
                img.onload = function() {
                    const canvas = document.createElement('canvas');
                    canvas.width = {{ $width }};
                    canvas.height = {{ $height }};
                    canvas.getContext('2d').drawImage(img, 0, 0, {{ $width }}, {{ $height }});
                    const compressed = canvas.toDataURL('image/jpeg', 0.88);

                    $.post("{{ $uploadUrl }}", {
                        _token: "{{ csrf_token() }}",
                        image: compressed,
                        folder: '{{ $folder }}',
                        filename: '{{ $filename }}'
                    }).done((res) => {
                    (function(){ var _m = document.querySelector('#cropperModal_{{ $id }}'); if (_m && window.bsModal) { try { window.bsModal.hide(_m); } catch(e){} } })();
@if($inline)
                    hideEditor_{{ $id }}();
@endif
                    Toast.success('Photo Updated!', 'Your image has been saved successfully.');

                    // Update the image on the page without reload
                    if (res.url) {
                        const cacheBuster = '?t=' + new Date().getTime();
                        const newUrl = res.url + cacheBuster;

                        // Try to find the image in the parent card (for facility/instructor cards)
                        const button = $('[data-bs-target="#cropperModal_{{ $id }}"]');
                        const card = button.closest('.card');
                        if (card.length) {
                            const cardImg = card.find('.card-img-top');
                            if (cardImg.length && cardImg.is('img')) {
                                // Update existing image
                                cardImg.attr('src', newUrl);
                            } else {
                                // Replace placeholder div with img
                                const placeholder = card.find('.card-img-top, .bg-muted').first();
                                if (placeholder.length && !placeholder.is('img')) {
                                    placeholder.replaceWith(`<img src="${newUrl}" alt="Image" class="card-img-top" style="height: 180px; object-fit: cover;">`);
                                }
                            }
                        }

                        // Also update any image with matching data-cropper-id
                        $('img[data-cropper-id="{{ $id }}"]').attr('src', newUrl);

                        // Fallback: Update all profile picture images on the page
                        $('img[src*="profile_{{ str_replace("profile_picture", "", $id) }}"]').attr('src', newUrl);

                        // Update any background images
                        $('[style*="profile_{{ str_replace("profile_picture", "", $id) }}"]').each(function() {
                            const style = $(this).attr('style');
                            if (style && style.includes('background-image')) {
                                $(this).attr('style', style.replace(/url\([^)]+\)/, 'url(' + newUrl + ')'));
                            }
                        });

                        // Notify page-level callbacks (e.g. profile-modal placeholder → img update)
                        if (typeof window.imageUploadSuccess === 'function') {
                            window.imageUploadSuccess(res);
                        }
                        document.dispatchEvent(new CustomEvent('imageUploaded', { detail: res }));
                    }
                }).fail((err) => {
                    Toast.error('Upload Failed', err.responseJSON?.message || 'An error occurred while uploading.');
                }).always(() => {
                    btn.prop('disabled', false).text('Crop & Save Image');
                });
                };
                img.src = base64;
            });
        }
    });

    @if($uploadAsIs)
    $('#saveAsIs_{{ $id }}').on('click', function() {
        const fileInput = document.getElementById('input_{{ $id }}');
        if (!fileInput.files || !fileInput.files[0]) return;

        const btn = $(this);
        btn.prop('disabled', true).text('Uploading...');

        const reader = new FileReader();
        reader.onload = function(event) {
            const rawImg = new Image();
            rawImg.onload = function() {
                const maxW = 1920, maxH = 1080;
                let w = rawImg.width, h = rawImg.height;
                if (w > maxW || h > maxH) {
                    const ratio = Math.min(maxW / w, maxH / h);
                    w = Math.round(w * ratio);
                    h = Math.round(h * ratio);
                }
                const canvas = document.createElement('canvas');
                canvas.width = w; canvas.height = h;
                canvas.getContext('2d').drawImage(rawImg, 0, 0, w, h);
                const base64 = canvas.toDataURL('image/jpeg', 0.88);

                if (mode_{{ $id }} === 'form') {
                    $('#hiddenInput_{{ $id }}').val(base64);

                    const previewContainer = $('#previewContainer_{{ $id }}');
                    const borderRadius = '{{ $shape }}' === 'circle' ? '50%' : '8px';
                    previewContainer.html(`
                        <img src="${base64}"
                             id="preview_{{ $id }}"
                             class="cropper-preview-image"
                             style="width: {{ $previewWidth }}px; height: {{ $previewHeight }}px; border-radius: ${borderRadius};">
                        <button type="button" class="cropper-remove-btn" id="removeBtn_{{ $id }}" onclick="removeImage_{{ $id }}()">
                            <i class="bi bi-x"></i>
                        </button>
                    `);
                    previewContainer.addClass('has-image');
                    (function(){ var _m = document.querySelector('#cropperModal_{{ $id }}'); if (_m && window.bsModal) { try { window.bsModal.hide(_m); } catch(e){} } })();
@if($inline)
                    hideEditor_{{ $id }}();
@endif
                    btn.prop('disabled', false).text('{{ $uploadAsIsText }}');
                } else {
                    // "Upload As Is": send the ORIGINAL file bytes untouched — no
                    // canvas re-encode — so quality is preserved exactly.
                    $.post("{{ $uploadUrl }}", {
                        _token: "{{ csrf_token() }}",
                        image: event.target.result,
                        folder: '{{ $folder }}',
                        filename: '{{ $filename }}'
                    }).done((res) => {
                        (function(){ var _m = document.querySelector('#cropperModal_{{ $id }}'); if (_m && window.bsModal) { try { window.bsModal.hide(_m); } catch(e){} } })();
@if($inline)
                        hideEditor_{{ $id }}();
@endif
                        Toast.success('Photo Updated!', 'Your image has been saved successfully.');

                        if (res.url) {
                            const newUrl = res.url + '?t=' + new Date().getTime();
                            $('img[data-cropper-id="{{ $id }}"]').attr('src', newUrl);
                            if (typeof window.imageUploadSuccess === 'function') {
                                window.imageUploadSuccess(res);
                            }
                            document.dispatchEvent(new CustomEvent('imageUploaded', { detail: res }));
                        }
                    }).fail((err) => {
                        Toast.error('Upload Failed', err.responseJSON?.message || 'An error occurred while uploading.');
                    }).always(() => {
                        btn.prop('disabled', false).text('{{ $uploadAsIsText }}');
                    });
                }
            };
            rawImg.src = event.target.result;
        };
        reader.readAsDataURL(fileInput.files[0]);
    });
    @endif

    // Remove image function for form mode
    window.removeImage_{{ $id }} = function() {
        const previewContainer = $('#previewContainer_{{ $id }}');
        const borderRadius = '{{ $shape }}' === 'circle' ? '50%' : '8px';

        // Clear hidden input
        $('#hiddenInput_{{ $id }}').val('');

        // Reset preview to placeholder
        previewContainer.html(`
            <div id="preview_{{ $id }}"
                 class="cropper-preview-placeholder"
                 style="width: {{ $previewWidth }}px; height: {{ $previewHeight }}px; border-radius: ${borderRadius};">
                <i class="bi bi-image" style="font-size: 2rem;"></i>
            </div>
        `);
        previewContainer.removeClass('has-image');
    };

    // Initialize remove button visibility if current image exists
    @if($currentImage && $mode === 'form')
    $('#previewContainer_{{ $id }}').addClass('has-image');
    @endif
});
</script>
@endpush
