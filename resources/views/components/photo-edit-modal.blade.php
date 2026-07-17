@props([
    'user',
    'uploadUrl',
    'removeUrl',
    'visibilityUrl',
    'eventName' => 'open-photo-edit-modal',
])

@php
    $currentProfileImage = '';
    if ($user->profile_picture && file_exists(public_path('storage/' . $user->profile_picture))) {
        $currentProfileImage = asset('storage/' . $user->profile_picture) . '?v=' . $user->updated_at->timestamp;
    }
    $profilePicturePublic = $user->profile_picture_is_public ?? true;
@endphp

<div x-data="photoEditModal({
        removeUrl: '{{ $removeUrl }}',
        visibilityUrl: '{{ $visibilityUrl }}',
        initialPublic: {{ $profilePicturePublic ? 'true' : 'false' }},
        hasImage: {{ $currentProfileImage ? 'true' : 'false' }},
    })"
    x-init="init()"
    x-show="open" x-cloak
    class="fixed inset-0 z-50 overflow-y-auto"
    x-on:{{ $eventName }}.window="open = true"
    @keydown.escape.window="open = false">

    <!-- Backdrop -->
    <div x-show="open" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-black/50" @click="open = false"></div>

    <div class="flex min-h-full items-center justify-center p-4">
        <div x-show="open" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
             class="relative bg-white rounded-2xl shadow-xl w-full max-w-lg max-w-[calc(100vw-2rem)]" @click.stop>

            <!-- Header -->
            <div class="flex items-center justify-between p-4 bg-primary text-white rounded-t-2xl">
                <h5 class="text-lg font-medium flex items-center">
                    <i class="bi bi-camera-fill me-2"></i>{{ __('shared.photo_edit_modal_title') }}
                </h5>
                <button type="button" @click="open = false" class="text-white hover:text-gray-200 text-2xl leading-none">&times;</button>
            </div>

            <!-- Body -->
            <div class="p-4 overflow-y-auto" style="max-height: 75vh;">
                <div class="text-center mb-3">
                    @if($currentProfileImage)
                        <img src="{{ $currentProfileImage }}"
                             alt="{{ __('shared.profile_modal_fields_photo_alt') }}"
                             id="photoEditModal_preview"
                             class="mx-auto image-upload-preview max-w-full"
                             style="width: 220px; height: 280px; object-fit: cover; border: 3px solid #dee2e6; border-radius: 8px;">
                    @else
                        <div id="photoEditModal_placeholder"
                             class="mx-auto"
                             style="width: 220px; height: 280px; background-color: #f0f0f0; border: 3px solid #dee2e6; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                            <i class="bi bi-person-circle" style="font-size: 60px; color: #dee2e6;"></i>
                        </div>
                    @endif
                </div>

                <!-- Privacy Toggle -->
                <div class="privacy-settings-card mb-3">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body p-3">
                            <div class="flex justify-between items-center mb-2">
                                <div class="flex-1 me-2">
                                    <h6 class="mb-1">
                                        <i class="bi bi-shield-lock-fill text-primary me-1"></i>{{ __('shared.profile_modal_fields_privacy_settings') }}
                                    </h6>
                                    <p class="text-muted mb-0 small">{{ __('shared.profile_modal_fields_toggle_visibility') }}</p>
                                </div>
                                <div class="form-check form-switch" style="font-size: 1.2rem;">
                                    <input class="form-check-input" type="checkbox" role="switch"
                                           x-model="isPublic" @change="toggleVisibility()">
                                </div>
                            </div>

                            <div :class="isPublic ? 'alert alert-success' : 'alert alert-warning'"
                                 class="mb-0 p-2" role="alert">
                                <div class="flex items-start">
                                    <i :class="isPublic ? 'bi-globe' : 'bi-lock-fill'" class="bi me-2 mt-1" style="font-size: 1.3rem;"></i>
                                    <div style="flex: 1; min-width: 0;">
                                        <strong class="block" style="font-size: 0.9rem;" x-text="isPublic ? 'Public' : 'Private'"></strong>
                                        <p class="mb-0 small" x-text="isPublic ? 'Everyone can see your profile picture' : 'Only you and your family can see your profile picture'"></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Tips -->
                <div class="mb-3">
                    <h6 class="text-muted mb-2 small">
                        <i class="bi bi-check-circle-fill me-1"></i>{{ __('shared.profile_modal_fields_quick_tips') }}
                    </h6>
                    <ul class="text-muted mb-0 small grid grid-cols-2 gap-x-3" style="list-style: none; padding-left: 0;">
                        <li class="mb-1"><i class="bi bi-check text-success me-1"></i>{{ __('shared.profile_modal_fields_tip_recent') }}</li>
                        <li class="mb-1"><i class="bi bi-check text-success me-1"></i>{{ __('shared.profile_modal_fields_tip_face') }}</li>
                        <li class="mb-1"><i class="bi bi-check text-success me-1"></i>{{ __('shared.profile_modal_fields_tip_professional') }}</li>
                        <li class="mb-0"><i class="bi bi-check text-success me-1"></i>{{ __('shared.profile_modal_fields_tip_lighting') }}</li>
                    </ul>
                </div>

                <!-- Cropper -->
                @if(view()->exists('takeone::components.widget'))
                    <x-takeone-cropper
                        id="photo_edit_modal_picture"
                        width="300"
                        height="400"
                        shape="rectangle"
                        folder="images/profiles"
                        filename="profile_{{ $user->id }}"
                        uploadUrl="{{ $uploadUrl }}"
                        currentImage="{{ $currentProfileImage }}"
                        :canvasHeight="500"
                        :inline="true"
                    />
                @endif

                <button type="button" @click="removePicture()" x-show="hasImage" class="btn btn-outline-danger btn-sm w-full mt-2">
                    <i class="bi bi-trash me-1"></i>{{ __('shared.profile_modal_fields_remove') }}
                </button>
            </div>

            <!-- Footer -->
            <div class="flex justify-end p-4 bg-gray-50 border-t rounded-b-2xl">
                <button type="button" class="btn btn-secondary" @click="open = false">{{ __('shared.cancel') }}</button>
            </div>
        </div>
    </div>
</div>

<script>
function photoEditModal(config) {
    return {
        open: false,
        isPublic: config.initialPublic,
        hasImage: config.hasImage,
        removeUrl: config.removeUrl,
        visibilityUrl: config.visibilityUrl,

        init() {
            document.addEventListener('imageUploaded', (e) => {
                if (!e.detail || !e.detail.url) return;
                this.hasImage = true;

                const url = e.detail.url + '?v=' + Date.now();
                const preview = document.getElementById('photoEditModal_preview');
                const placeholder = document.getElementById('photoEditModal_placeholder');

                if (preview) {
                    preview.src = url;
                } else if (placeholder) {
                    const img = document.createElement('img');
                    img.id = 'photoEditModal_preview';
                    img.className = 'mx-auto image-upload-preview max-w-full';
                    img.style.cssText = 'width: 220px; height: 280px; object-fit: cover; border: 3px solid #dee2e6; border-radius: 8px;';
                    img.src = url;
                    placeholder.replaceWith(img);
                }
            });
        },

        async toggleVisibility() {
            const previous = !this.isPublic;
            try {
                const res = await fetch(this.visibilityUrl, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ is_public: this.isPublic }),
                });
                const data = await res.json();
                if (!data.success) throw new Error(data.message || 'Failed');
                window.showToast('success', this.isPublic ? '{{ __('shared.photo_edit_modal_now_public') }}' : '{{ __('shared.photo_edit_modal_now_private') }}');
            } catch (e) {
                this.isPublic = previous;
                window.showToast('error', '{{ __('shared.photo_edit_modal_visibility_failed') }}');
            }
        },

        async removePicture() {
            const confirmed = await window.confirmAction({
                title: '{{ __('shared.photo_edit_modal_remove_title') }}',
                message: '{{ __('shared.photo_edit_modal_remove_message') }}',
                type: 'danger',
                confirmText: '{{ __('shared.profile_modal_fields_remove') }}',
            });
            if (!confirmed) return;

            try {
                const res = await fetch(this.removeUrl, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json',
                    },
                });
                const data = await res.json();
                if (!data.success) throw new Error(data.message || 'Failed');

                this.hasImage = false;

                const preview = document.getElementById('photoEditModal_preview');
                if (preview) preview.remove();

                // Patch the big profile picture shown on the page (member/family profile views).
                const pagePic = document.getElementById('member-profile-pic');
                const pagePlaceholder = document.getElementById('member-profile-placeholder');
                if (pagePic) pagePic.remove();
                if (pagePlaceholder) pagePlaceholder.style.display = 'flex';

                window.showToast('success', data.message || '{{ __('shared.photo_edit_modal_removed') }}');
            } catch (e) {
                window.showToast('error', '{{ __('shared.photo_edit_modal_remove_failed') }}');
            }
        },
    }
}
</script>
