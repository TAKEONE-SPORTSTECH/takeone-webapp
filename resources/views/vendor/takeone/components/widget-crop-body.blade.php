{{-- Shared cropper body — used by BOTH the modal layout and the inline layout in widget.blade.php. --}}
{{-- Inherits $id, $canvasHeight, $mode, $uploadAsIs, $uploadAsIsText, $inline from the parent scope. --}}
{{-- $showClose (bool) controls whether the modal close button is rendered. --}}
@if($showClose ?? false)
<div class="flex justify-end mb-1">
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
@endif

{{-- Native file input — hidden; the two tiles below drive it (and the camera) so the picker looks intentional, not like a raw form control. --}}
<input type="file" id="input_{{ $id }}" class="hidden" style="display:none;" accept="image/*">
{{-- Hidden input that opens the device camera directly on mobile --}}
<input type="file" id="cameraInput_{{ $id }}" accept="image/*" capture="user" class="hidden" style="display:none;">

<div class="grid grid-cols-2 gap-2.5">
    {{-- Upload a photo --}}
    <button type="button" onclick="document.getElementById('input_{{ $id }}').click()"
            class="group flex flex-col items-center justify-center gap-2 px-3 py-5 rounded-2xl border-2 border-dashed border-primary/25 bg-primary/[0.03] hover:border-primary hover:bg-primary/[0.06] focus:outline-none focus:ring-2 focus:ring-primary/30 transition-all">
        <span class="w-12 h-12 rounded-full bg-primary/10 text-primary flex items-center justify-center group-hover:scale-110 transition-transform">
            <i class="bi bi-cloud-arrow-up-fill text-2xl"></i>
        </span>
        <span class="text-sm font-semibold text-gray-700">Upload Photo</span>
        <span class="text-[11px] text-gray-400">Choose from device</span>
    </button>

    {{-- Take a photo --}}
    <button type="button" id="camera_{{ $id }}"
            class="group flex flex-col items-center justify-center gap-2 px-3 py-5 rounded-2xl border-2 border-dashed border-gray-200 bg-gray-50 hover:border-primary hover:bg-primary/[0.06] focus:outline-none focus:ring-2 focus:ring-primary/30 transition-all">
        <span class="w-12 h-12 rounded-full bg-gray-100 text-gray-500 group-hover:bg-primary/10 group-hover:text-primary flex items-center justify-center group-hover:scale-110 transition-transform">
            <i class="bi bi-camera-fill text-2xl"></i>
        </span>
        <span class="text-sm font-semibold text-gray-700">Take Photo</span>
        <span class="text-[11px] text-gray-400">Use your camera</span>
    </button>
</div>

@if($inline)
{{-- Inline mode: the crop editor opens as its own focused overlay (a separate "adjust photo" --}}
{{-- surface) above the form — not expanded in place. Hidden until a file/photo is chosen; --}}
{{-- the JS moves it onto <body> so it escapes any transformed ancestor (mobile shell). --}}
<div id="editor_{{ $id }}" class="cropper-editor-overlay fixed inset-0 z-[80] flex items-end sm:items-center justify-center" style="display:none;">
    <div class="absolute inset-0 bg-black/60" id="editorBackdrop_{{ $id }}"></div>
    <div class="relative bg-white w-full rounded-t-3xl sm:rounded-2xl shadow-xl flex flex-col max-h-[92vh]"
         style="max-width: min(92vw, {{ max(512, (int) $width + 96) }}px);">
        {{-- Header --}}
        <div class="flex items-center justify-between px-4 py-3 bg-primary text-white rounded-t-3xl sm:rounded-t-2xl flex-shrink-0">
            <h5 class="text-base font-semibold flex items-center">
                <i class="bi bi-crop mr-2"></i>Adjust Photo
            </h5>
            <button type="button" id="cancel_{{ $id }}" class="text-white/90 hover:text-white text-2xl leading-none w-8 h-8 flex items-center justify-center -mr-1" aria-label="Close">&times;</button>
        </div>

        {{-- Scrollable body --}}
        <div class="flex-1 overflow-y-auto overscroll-contain p-4">
            <p class="text-xs text-muted-foreground mb-2">
                <i class="bi bi-info-circle mr-1"></i>Drag to reposition, then use the zoom &amp; rotation sliders.
            </p>

            <div id="box_{{ $id }}" class="takeone-canvas" style="height: {{ $canvasHeight }}px;"></div>

            <div class="grid grid-cols-12 gap-4 mt-4">
                <div class="col-span-12 md:col-span-6 mb-1">
                    <label class="custom-slider-label block mb-2">Zoom Level</label>
                    <input type="range" class="form-range" id="zoom_{{ $id }}" min="0" max="100" step="1" value="0">
                </div>
                <div class="col-span-12 md:col-span-6 mb-1">
                    <label class="custom-slider-label block mb-2">Rotation</label>
                    <input type="range" class="form-range" id="rot_{{ $id }}" min="-180" max="180" step="1" value="0">
                </div>
            </div>
        </div>

        {{-- Sticky footer actions --}}
        <div class="px-4 py-3 bg-gray-50 border-t flex-shrink-0 flex items-center gap-2" style="padding-bottom: max(0.75rem, env(safe-area-inset-bottom));">
            <button type="button" class="flex-shrink-0 btn btn-outline-secondary py-2.5 px-4" id="cancelBtn_{{ $id }}">
                Cancel
            </button>
            @if($uploadAsIs)
            <button type="button" class="flex-1 btn btn-outline-secondary py-2.5 font-bold" id="saveAsIs_{{ $id }}">
                {{ $uploadAsIsText }}
            </button>
            @endif
            <button type="button" class="flex-1 btn btn-success py-2.5 font-bold" id="save_{{ $id }}">
                @if($mode === 'form') Crop & Apply @else Crop & Save Image @endif
            </button>
        </div>
    </div>
</div>
@else
{{-- Modal mode: editor renders directly inside the cropper modal. --}}
<div id="editor_{{ $id }}">
    <p class="text-xs text-muted-foreground mb-2">
        <i class="bi bi-info-circle mr-1"></i>Drag to reposition, then use the zoom &amp; rotation sliders.
    </p>

    <div id="box_{{ $id }}" class="takeone-canvas" style="height: {{ $canvasHeight }}px;"></div>

    <div class="grid grid-cols-12 gap-4 mt-4">
        <div class="col-span-12 md:col-span-6 mb-3">
            <label class="custom-slider-label block mb-2">Zoom Level</label>
            <input type="range" class="form-range" id="zoom_{{ $id }}" min="0" max="100" step="1" value="0">
        </div>
        <div class="col-span-12 md:col-span-6 mb-3">
            <label class="custom-slider-label block mb-2">Rotation</label>
            <input type="range" class="form-range" id="rot_{{ $id }}" min="-180" max="180" step="1" value="0">
        </div>
    </div>

    <div class="grid gap-2 mt-2">
        @if($uploadAsIs)
        <div class="grid grid-cols-2 gap-2">
            <button type="button" class="btn btn-outline-secondary btn-lg font-bold py-3" id="saveAsIs_{{ $id }}">
                {{ $uploadAsIsText }}
            </button>
            <button type="button" class="btn btn-success btn-lg font-bold py-3" id="save_{{ $id }}">
                @if($mode === 'form') Crop & Apply @else Crop & Save Image @endif
            </button>
        </div>
        @else
        <button type="button" class="btn btn-success btn-lg font-bold py-3" id="save_{{ $id }}">
            @if($mode === 'form') Crop & Apply @else Crop & Save Image @endif
        </button>
        @endif
    </div>
</div>
@endif
