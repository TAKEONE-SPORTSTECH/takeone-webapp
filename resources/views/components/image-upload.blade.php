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
    'buttonClass' => 'px-4 py-2 bg-green-500 text-white rounded-xl hover:bg-green-600 transition-colors cursor-pointer',
    'previewClass' => '',
    'showPreview' => true,
    'rounded' => false
])

@php
    $cacheBuster = $currentImage ? '?v=' . time() : '';
    $imageUrl = $currentImage ? $currentImage . $cacheBuster : '';
    $borderRadius = $rounded ? 'rounded-full' : 'rounded-lg';
    $previewWidth = $width . 'px';
    $previewHeight = $height . 'px';
@endphp

<div class="image-upload-component inline-block text-center {{ $previewClass }}">
    @if($showPreview)
        <div class="image-preview mb-4">
            @if($imageUrl)
                <img src="{{ $imageUrl }}"
                     alt="Preview"
                     id="{{ $id }}_preview"
                     class="image-upload-preview object-cover border-3 border-gray-300 {{ $borderRadius }} transition-opacity duration-300 hover:opacity-90"
                     style="width: {{ $previewWidth }}; height: {{ $previewHeight }};">
            @else
                <div class="image-placeholder flex items-center justify-center bg-gray-100 border-3 border-gray-300 {{ $borderRadius }} mx-auto transition-all duration-300 hover:bg-gray-200"
                     id="{{ $id }}_placeholder"
                     style="width: {{ $previewWidth }}; height: {{ $previewHeight }};">
                    <div class="text-center">
                        <i class="bi {{ $placeholderIcon }} text-6xl text-gray-300"></i>
                        <p class="text-gray-500 mt-2 mb-0 text-sm">{{ $placeholder }}</p>
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
