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
    $buttonClass = $attributes->get('buttonClass', 'btn btn-success px-4 fw-bold shadow-sm');
    $mode = $attributes->get('mode', 'ajax'); // 'ajax' or 'form'
    $inputName = $attributes->get('inputName', 'image'); // For form mode - the hidden input name
    $previewWidth = $attributes->get('previewWidth', $width);
    $previewHeight = $attributes->get('previewHeight', $height);
    $showPreview = $attributes->get('showPreview', $mode === 'form'); // Show preview by default in form mode
@endphp

<x-toast-notification />

@once
    <link rel="stylesheet" href="https://unpkg.com/cropme@1.4.1/dist/cropme.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/cropme@1.4.1/dist/cropme.min.js"></script>
    <style>
        .modal-content-clean { border: none; border-radius: 15px; overflow: hidden; }
        .cropme-wrapper { overflow: hidden !important; border-radius: 8px; }
        .cropme-slider { display: none !important; }
        .takeone-canvas {
            background: #111;
            border-radius: 8px;
            position: relative;
            border: 1px solid #222;
        }
        .custom-slider-label {
            font-size: 0.75rem;
            font-weight: bold;
            text-transform: uppercase;
            color: #6c757d;
            letter-spacing: 0.5px;
        }
        .form-range::-webkit-slider-thumb { background: #198754; }
        .form-range::-moz-range-thumb { background: #198754; }
        .cropper-preview-container {
            position: relative;
            display: inline-block;
        }
        .cropper-preview-placeholder {
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f0f0f0;
            border: 2px dashed #dee2e6;
            color: #6c757d;
        }
        .cropper-preview-image {
            object-fit: cover;
            border: 2px solid #dee2e6;
        }
        .cropper-remove-btn {
            position: absolute;
            top: -8px;
            right: -8px;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: #dc3545;
            color: white;
            border: none;
            font-size: 14px;
            line-height: 1;
            cursor: pointer;
            display: none;
        }
        .cropper-preview-container.has-image .cropper-remove-btn {
            display: flex;
            align-items: center;
            justify-content: center;
        }
    </style>
@endonce

@if($mode === 'form')
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
    <button type="button" class="{{ $buttonClass }}" data-bs-toggle="modal" data-bs-target="#cropperModal_{{ $id }}">
        <i class="bi bi-camera me-2"></i>{{ $buttonText }}
    </button>
</div>
@else
{{-- AJAX mode: Just the button --}}
<button type="button" class="{{ $buttonClass }}" data-bs-toggle="modal" data-bs-target="#cropperModal_{{ $id }}">
    <i class="bi bi-camera me-2"></i>{{ $buttonText }}
</button>
@endif

@push('modals')
<div class="modal fade" id="cropperModal_{{ $id }}" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width: 75%; width: 1000px;">
        <div class="modal-content modal-content-clean shadow-lg">
            <div class="modal-body p-4 text-start" style="max-height: 85vh; overflow-y: auto;">
                <div class="mb-3 d-flex align-items-center">
                    <input type="file" id="input_{{ $id }}" class="form-control form-control-sm" accept="image/*">
                    <button type="button" class="btn-close ms-2" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div id="box_{{ $id }}" class="takeone-canvas" style="height: 500px;"></div>

                <div class="row mt-4">
                    <div class="col-md-6 mb-3">
                        <label class="custom-slider-label d-block mb-2">Zoom Level</label>
                        <input type="range" class="form-range" id="zoom_{{ $id }}" min="0" max="100" step="1" value="0">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="custom-slider-label d-block mb-2">Rotation</label>
                        <input type="range" class="form-range" id="rot_{{ $id }}" min="-180" max="180" step="1" value="0">
                    </div>
                </div>

                <div class="d-grid gap-2 mt-2">
                    <button type="button" class="btn btn-success btn-lg fw-bold py-3" id="save_{{ $id }}">
                        @if($mode === 'form')
                            Crop & Apply
                        @else
                            Crop & Save Image
                        @endif
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

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
            container: { width: '100%', height: 500 },
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

    // Load current image when modal opens
    $('#cropperModal_{{ $id }}').on('shown.bs.modal', function() {
        if (currentImage_{{ $id }} && !cropper_{{ $id }}) {
            initCropper_{{ $id }}(currentImage_{{ $id }});
        }
    });

    $('#input_{{ $id }}').on('change', function() {
        if (this.files && this.files[0]) {
            const reader = new FileReader();
            reader.onload = function(event) {
                initCropper_{{ $id }}(event.target.result);
            };
            reader.readAsDataURL(this.files[0]);
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
                $('#cropperModal_{{ $id }}').modal('hide');
                btn.prop('disabled', false).text('Crop & Apply');
            });
        } else {
            // AJAX mode: Upload to server
            btn.prop('disabled', true).text('Uploading...');

            cropper_{{ $id }}.crop({ type: 'base64' }).then(base64 => {
                $.post("{{ $uploadUrl }}", {
                    _token: "{{ csrf_token() }}",
                    image: base64,
                    folder: '{{ $folder }}',
                    filename: '{{ $filename }}'
                }).done((res) => {
                    $('#cropperModal_{{ $id }}').modal('hide');
                    Toast.success('Photo Updated!', 'Your image has been saved successfully.');

                    // Update the profile picture in the current page without reload
                    if (res.url) {
                        // Update all profile picture images on the page
                        $('img[src*="profile_{{ str_replace("profile_picture", "", $id) }}"]').attr('src', res.url + '?t=' + new Date().getTime());
                        // Update any background images
                        $('[style*="profile_{{ str_replace("profile_picture", "", $id) }}"]').each(function() {
                            const style = $(this).attr('style');
                            if (style && style.includes('background-image')) {
                                $(this).attr('style', style.replace(/url\([^)]+\)/, 'url(' + res.url + '?t=' + new Date().getTime() + ')'));
                            }
                        });
                    }
                }).fail((err) => {
                    Toast.error('Upload Failed', err.responseJSON?.message || 'An error occurred while uploading.');
                }).always(() => {
                    btn.prop('disabled', false).text('Crop & Save Image');
                });
            });
        }
    });

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
