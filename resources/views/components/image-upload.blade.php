@props([
    'id' => 'image_upload_' . uniqid(),
    'name' => 'image',
    'width' => 300,
    'height' => 300,
    'shape' => 'square',
    'folder' => 'uploads',
    'filename' => 'image_' . time(),
    'uploadUrl' => '',
    'currentImage' => '',
    'placeholder' => 'No image',
    'placeholderIcon' => 'bi-image',
    'buttonText' => 'Change Photo',
    'buttonClass' => 'btn btn-success',
    'previewClass' => '',
    'showPreview' => true,
    'rounded' => false
])

@php
    $cacheBuster = $currentImage ? '?v=' . time() : '';
    $imageUrl = $currentImage ? $currentImage . $cacheBuster : '';
    $borderRadius = $rounded ? '50%' : '8px';
    $previewWidth = $width . 'px';
    $previewHeight = $height . 'px';
@endphp

<div class="image-upload-component text-center {{ $previewClass }}">
    @if($showPreview)
        <div class="image-preview mb-3">
            @if($imageUrl)
                <img src="{{ $imageUrl }}"
                     alt="Preview"
                     id="{{ $id }}_preview"
                     class="image-upload-preview"
                     style="width: {{ $previewWidth }}; height: {{ $previewHeight }}; object-fit: cover; border: 3px solid #dee2e6; border-radius: {{ $borderRadius }};">
            @else
                <div class="image-placeholder"
                     id="{{ $id }}_placeholder"
                     style="width: {{ $previewWidth }}; height: {{ $previewHeight }}; background-color: #f0f0f0; border: 3px solid #dee2e6; border-radius: {{ $borderRadius }}; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                    <div class="text-center">
                        <i class="bi {{ $placeholderIcon }}" style="font-size: 60px; color: #dee2e6;"></i>
                        <p class="text-muted mt-2 mb-0">{{ $placeholder }}</p>
                    </div>
                </div>
            @endif
        </div>
    @endif

    <x-takeone-cropper
        :id="$id"
        :width="$width"
        :height="$height"
        :shape="$shape"
        :folder="$folder"
        :filename="$filename"
        :uploadUrl="$uploadUrl"
        :currentImage="$currentImage"
        :buttonText="$buttonText"
        :buttonClass="$buttonClass"
    />
</div>

@once
@push('styles')
<style>
    .image-upload-component {
        display: inline-block;
    }
    .image-upload-preview {
        transition: opacity 0.3s ease;
    }
    .image-upload-preview:hover {
        opacity: 0.9;
    }
    .image-placeholder {
        transition: all 0.3s ease;
    }
    .image-placeholder:hover {
        background-color: #e9ecef !important;
    }
</style>
@endpush
@endonce
