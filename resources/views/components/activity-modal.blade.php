@props([
    'club',
    'mode' => 'create',
])

@php
    $isEdit = $mode === 'edit';
    $prefix = $isEdit ? 'edit' : 'add';
    $showVar = $isEdit ? 'showEditModal' : 'showAddModal';
    $formId = $isEdit ? 'editActivityForm' : 'addActivityForm';
    $cropperId = $isEdit ? 'editActivityPictureCropper' : 'activityPictureCropper';
    $title = $isEdit ? 'Edit Activity' : 'Create New Activity';
    $subtitle = $isEdit ? 'Update activity details' : 'Configure your activity details';
    $submitText = $isEdit ? 'Update Activity' : 'Create Activity';
    $submitIcon = $isEdit ? 'bi-check-lg' : 'bi-plus-lg';
@endphp

<div x-show="{{ $showVar }}"
     x-cloak
     @if($isEdit)
     x-init="$watch('showEditModal', value => { if (value) { setTimeout(() => initEditForm(), 100) } })"
     @else
     x-init="$watch('showAddModal', value => { if (value && duplicateData) { setTimeout(() => initDuplicate(), 100) } })"
     @endif
     class="fixed inset-0 z-50 overflow-y-auto"
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0">
    <!-- Backdrop -->
    <div class="fixed inset-0 bg-black/50" @click="{{ $showVar }} = false"></div>

    <!-- Modal Content -->
    <div class="flex min-h-full items-center justify-center p-4">
        <div class="modal-content border-0 shadow-lg w-full max-w-lg relative rounded-xl overflow-hidden"
             @click.stop>
            <!-- Header -->
            <div class="modal-header border-b border-border px-6 py-4">
                <div>
                    <h5 class="modal-title text-xl font-semibold">{{ $title }}</h5>
                    <p class="text-sm text-muted-foreground mt-1">{{ $subtitle }}</p>
                </div>
                <button type="button" class="text-muted-foreground hover:text-foreground transition-colors" @click="{{ $showVar }} = false">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <!-- Body -->
            <div class="modal-body px-6 py-6 max-h-[70vh] overflow-y-auto">
                <form id="{{ $formId }}"
                      @if($isEdit)
                      method="POST"
                      @else
                      action="{{ route('admin.club.activities.store', $club->slug) }}"
                      method="POST"
                      @endif
                      enctype="multipart/form-data">
                    @csrf
                    @if($isEdit)
                    @method('PUT')
                    @endif

                    @if ($errors->any())
                    <div class="mb-4 p-3 bg-destructive/10 border border-destructive/20 rounded-lg">
                        <ul class="text-sm text-destructive list-disc list-inside">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                    @endif

                    <div class="space-y-5">
                        <!-- Activity Picture -->
                        <div class="space-y-3">
                            <label class="block text-sm font-medium text-foreground">
                                Activity Picture
                            </label>

                            @if(!$isEdit)
                            <!-- Existing picture from duplication -->
                            <div id="existingPictureSection" class="hidden">
                                <div class="flex items-center gap-3 p-3 bg-info/10 border border-info/20 rounded-lg">
                                    <img id="existingPictureImg" src="" alt="Existing" class="w-16 h-16 object-cover rounded">
                                    <div class="flex-1">
                                        <p class="text-sm font-medium text-foreground">Keep existing picture</p>
                                        <p class="text-xs text-muted-foreground">Or upload a new one below</p>
                                    </div>
                                    <button type="button" id="removeExistingPictureBtn" class="p-2 text-destructive hover:text-destructive/80">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </div>
                                <input type="hidden" id="existingPictureUrl" name="existing_picture_url" value="">
                            </div>
                            @endif

                            <div class="flex flex-col items-center gap-3" id="{{ $prefix }}ActivityCropperContainer">
                                <x-takeone-cropper
                                    :id="$cropperId"
                                    :width="400"
                                    :height="300"
                                    shape="square"
                                    mode="form"
                                    inputName="picture"
                                    folder="activities"
                                    :filename="'activity_' . time()"
                                    :previewWidth="280"
                                    :previewHeight="210"
                                    :buttonText="$isEdit ? 'Change Picture' : 'Upload Picture'"
                                    buttonClass="btn btn-outline-secondary w-full"
                                />
                            </div>
                        </div>

                        <!-- Activity Title -->
                        <div class="space-y-2">
                            <label for="{{ $prefix }}ActivityName" class="block text-sm font-medium text-foreground">
                                Activity Title <span class="text-destructive">*</span>
                            </label>
                            <input type="text"
                                   id="{{ $prefix }}ActivityName"
                                   name="name"
                                   required
                                   placeholder="e.g., Morning Yoga Class"
                                   class="form-control"
                                   oninput="clearActivityFieldError('{{ $prefix }}', 'name')">
                            <span id="{{ $prefix }}ActivityError_name" class="text-destructive text-xs hidden block"></span>
                        </div>

                        <!-- Description -->
                        <div class="space-y-2">
                            <label for="{{ $prefix }}ActivityDescription" class="block text-sm font-medium text-foreground">
                                Description
                            </label>
                            <textarea id="{{ $prefix }}ActivityDescription"
                                      name="description"
                                      rows="3"
                                      placeholder="Detailed description of the activity..."
                                      class="form-control resize-none"
                                      oninput="clearActivityFieldError('{{ $prefix }}', 'description')"></textarea>
                            <span id="{{ $prefix }}ActivityError_description" class="text-destructive text-xs hidden block"></span>
                        </div>

                        <!-- Additional Notes -->
                        <div class="space-y-2">
                            <label for="{{ $prefix }}ActivityNotes" class="block text-sm font-medium text-foreground">
                                Additional Notes
                            </label>
                            <textarea id="{{ $prefix }}ActivityNotes"
                                      name="notes"
                                      rows="2"
                                      placeholder="Any additional information..."
                                      class="form-control resize-none"
                                      oninput="clearActivityFieldError('{{ $prefix }}', 'notes')"></textarea>
                            <span id="{{ $prefix }}ActivityError_notes" class="text-destructive text-xs hidden block"></span>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Footer -->
            <div class="modal-footer border-t border-border px-6 py-4 flex justify-end gap-3">
                <button type="button"
                        class="btn btn-outline-secondary"
                        @click="{{ $showVar }} = false">
                    Cancel
                </button>
                <button type="button"
                        id="{{ $prefix }}ActivitySubmitBtn"
                        onclick="submitActivityForm('{{ $prefix }}', '{{ $formId }}')"
                        class="btn btn-primary flex items-center gap-2">
                    <i class="bi {{ $submitIcon }}"></i>
                    <span>{{ $submitText }}</span>
                </button>
            </div>
        </div>
    </div>
</div>

@if($isEdit)
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('editActivityForm');

    function updateCropperPreview(pictureUrl) {
        const previewContainer = $('#previewContainer_editActivityPictureCropper');
        if (pictureUrl) {
            previewContainer.html(`
                <img src="${pictureUrl}" id="preview_editActivityPictureCropper" class="cropper-preview-image" style="width: 280px; height: 210px; border-radius: 8px;">
                <button type="button" class="cropper-remove-btn" id="removeBtn_editActivityPictureCropper" onclick="removeImage_editActivityPictureCropper()"><i class="bi bi-x"></i></button>
            `);
            previewContainer.addClass('has-image');
        } else {
            previewContainer.html(`
                <div id="preview_editActivityPictureCropper" class="cropper-preview-placeholder" style="width: 280px; height: 210px; border-radius: 8px;">
                    <i class="bi bi-image" style="font-size: 2rem;"></i>
                </div>
            `);
            previewContainer.removeClass('has-image');
        }
        $('#hiddenInput_editActivityPictureCropper').val('');
    }

    window.initEditForm = function() {
        const el = document.getElementById('activitiesContainer');
        const data = el ? Alpine.$data(el).editData : null;
        if (data) {
            form.action = data.action;
            document.getElementById('editActivityName').value = data.name || '';
            document.getElementById('editActivityDescription').value = data.description || '';
            document.getElementById('editActivityNotes').value = data.notes || '';
            updateCropperPreview(data.pictureUrl);
            clearAllActivityErrors('edit');
        }
    };
});
</script>
@endpush
@else
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const removeExistingPictureBtn = document.getElementById('removeExistingPictureBtn');
    removeExistingPictureBtn?.addEventListener('click', function() {
        document.getElementById('existingPictureUrl').value = '';
        document.getElementById('existingPictureSection').classList.add('hidden');
    });
});

function initDuplicate() {
    const el = document.getElementById('activitiesContainer');
    const component = el ? Alpine.$data(el) : null;
    if (component && component.duplicateData) {
        const data = component.duplicateData;
        document.getElementById('addActivityName').value = data.name + ' (Copy)';
        document.getElementById('addActivityDescription').value = data.description || '';
        document.getElementById('addActivityNotes').value = data.notes || '';
        clearAllActivityErrors('add');

        if (data.pictureUrl) {
            document.getElementById('existingPictureImg').src = data.pictureUrl;
            document.getElementById('existingPictureUrl').value = data.pictureUrl;
            document.getElementById('existingPictureSection').classList.remove('hidden');
        }
    }
}
</script>
@endpush
@endif

@once
@push('scripts')
<script>
// ── Shared Activity form validation & AJAX submit ─────────────────────────

function showActivityFieldError(prefix, field, message) {
    const el = document.getElementById(prefix + 'ActivityError_' + field);
    if (el) { el.textContent = message; el.classList.remove('hidden'); }
    const input = document.getElementById(prefix + 'Activity' + field.charAt(0).toUpperCase() + field.slice(1));
    input?.classList.add('is-invalid');
}

function clearActivityFieldError(prefix, field) {
    const el = document.getElementById(prefix + 'ActivityError_' + field);
    if (el) { el.textContent = ''; el.classList.add('hidden'); }
    const input = document.getElementById(prefix + 'Activity' + field.charAt(0).toUpperCase() + field.slice(1));
    input?.classList.remove('is-invalid');
}

function clearAllActivityErrors(prefix) {
    ['name', 'description', 'notes', 'duration_minutes'].forEach(f => clearActivityFieldError(prefix, f));
}

function submitActivityForm(prefix, formId) {
    const form = document.getElementById(formId);
    if (!form) return;

    clearAllActivityErrors(prefix);

    // Client-side validation
    let valid = true;
    const nameEl = document.getElementById(prefix + 'ActivityName');
    if (!nameEl || !nameEl.value.trim()) {
        showActivityFieldError(prefix, 'name', 'Activity title is required.');
        valid = false;
    } else if (nameEl.value.trim().length > 255) {
        showActivityFieldError(prefix, 'name', 'Activity title must not exceed 255 characters.');
        valid = false;
    }

    const descEl = document.getElementById(prefix + 'ActivityDescription');
    if (descEl && descEl.value.length > 2000) {
        showActivityFieldError(prefix, 'description', 'Description must not exceed 2000 characters.');
        valid = false;
    }

    const notesEl = document.getElementById(prefix + 'ActivityNotes');
    if (notesEl && notesEl.value.length > 1000) {
        showActivityFieldError(prefix, 'notes', 'Additional notes must not exceed 1000 characters.');
        valid = false;
    }

    if (!valid) {
        if (typeof Toast !== 'undefined') {
            Toast.error('Validation Error', 'Please fix the highlighted fields before submitting.');
        }
        return;
    }

    const btn = document.getElementById(prefix + 'ActivitySubmitBtn');
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm mr-2"></span>Saving...';

    fetch(form.action, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
        body: new FormData(form)
    })
    .then(async response => {
        const data = await response.json();
        if (response.ok && data.success) {
            if (typeof Toast !== 'undefined') {
                Toast.success('Success', data.message || 'Activity saved successfully.');
            }
            setTimeout(() => location.reload(), 900);
        } else if (response.status === 422 && data.errors) {
            const fieldMap = { name: 'name', description: 'description', notes: 'notes', duration_minutes: 'duration_minutes' };
            let first = true;
            Object.keys(data.errors).forEach(field => {
                if (fieldMap[field]) {
                    showActivityFieldError(prefix, fieldMap[field], data.errors[field][0]);
                    if (first) {
                        document.getElementById(prefix + 'ActivityError_' + fieldMap[field])?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        first = false;
                    }
                }
            });
            const count = Object.keys(data.errors).length;
            if (typeof Toast !== 'undefined') {
                Toast.error('Validation Failed', `${count} error${count > 1 ? 's' : ''} — please review the highlighted fields.`);
            }
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        } else {
            if (typeof Toast !== 'undefined') {
                Toast.error('Error', data.message || 'Failed to save activity. Please try again.');
            }
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }
    })
    .catch(() => {
        if (typeof Toast !== 'undefined') {
            Toast.error('Error', 'An unexpected error occurred. Please try again.');
        }
        btn.disabled = false;
        btn.innerHTML = originalHtml;
    });
}
</script>
@endpush
@endonce
