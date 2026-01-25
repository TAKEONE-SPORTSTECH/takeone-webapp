@php
    $id = $attributes->get('id');
    $width = $attributes->get('width', 300);
    $height = $attributes->get('height', 300);
    $shape = $attributes->get('shape', 'circle');
    $folder = $attributes->get('folder', 'uploads');
    $filename = $attributes->get('filename', 'cropped_' . time());
    $uploadUrl = $attributes->get('uploadUrl', route('profile.upload-picture'));
@endphp

@once
    <link rel="stylesheet" href="https://unpkg.com/cropme@1.4.1/dist/cropme.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/cropme@1.4.1/dist/cropme.min.js"></script>
    <style>
        .modal-content-clean { border: none; border-radius: 15px; overflow: hidden; }
        .cropme-wrapper { overflow: hidden !important; border-radius: 8px; }
        .cropme-slider { display: none !important; }
        .takeone-canvas {
            height: 400px;
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
    </style>
@endonce

<button type="button" class="btn btn-success px-4 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#cropperModal_{{ $id }}">
    Change Photo
</button>

@push('modals')
<div class="modal fade" id="cropperModal_{{ $id }}" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content modal-content-clean shadow-lg">
            <div class="modal-body p-4 text-start">
                <div class="mb-3 d-flex align-items-center">
                    <input type="file" id="input_{{ $id }}" class="form-control form-control-sm" accept="image/*">
                    <button type="button" class="btn-close ms-2" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div id="box_{{ $id }}" class="takeone-canvas"></div>

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
                        Crop & Save Image
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

    function applyTransform_{{ $id }}(instance) {
        if (!instance.properties.image) return;
        const p = instance.properties;
        const t = `translate3d(${p.x}px, ${p.y}px, 0) scale(${p.scale}) rotate(${p.deg}deg)`;
        p.image.style.transform = t;
    }

    $('#input_{{ $id }}').on('change', function() {
        if (this.files && this.files[0]) {
            const reader = new FileReader();
            reader.onload = function(event) {
                if (cropper_{{ $id }}) cropper_{{ $id }}.destroy();

                cropper_{{ $id }} = new Cropme(el_{{ $id }}, {
                    container: { width: '100%', height: 400 },
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

                cropper_{{ $id }}.bind({ url: event.target.result }).then(() => {
                    $('#zoom_{{ $id }}').val(0);
                    $('#rot_{{ $id }}').val(0);
                });
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
        btn.prop('disabled', true).text('Uploading...');

        cropper_{{ $id }}.crop({ type: 'base64' }).then(base64 => {
            $.post("{{ $uploadUrl }}", {
                _token: "{{ csrf_token() }}",
                image: base64,
                folder: '{{ $folder }}',
                filename: '{{ $filename }}'
            }).done((res) => {
                alert('Saved successfully!');
                $('#cropperModal_{{ $id }}').modal('hide');
                // Reload page to show new image
                location.reload();
            }).fail((err) => {
                alert('Upload failed: ' + (err.responseJSON?.message || 'Unknown error'));
            }).always(() => {
                btn.prop('disabled', false).text('Crop & Save Image');
            });
        });
    });
});
</script>
@endpush
