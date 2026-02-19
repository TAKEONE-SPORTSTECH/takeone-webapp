@extends('layouts.admin-club')

@section('club-admin-content')
<div class="bg-white rounded-lg shadow" x-data="{ showUploadModal: false }">
    <!-- Card Header -->
    <div class="flex items-center justify-between border-b border-border px-6 py-4">
        <h5 class="text-lg font-semibold text-foreground">Club Gallery</h5>
        <button class="btn btn-primary"
                @click="showUploadModal = true">
            <i class="bi bi-plus-lg mr-2"></i>
            Add Picture
        </button>
    </div>

    <!-- Card Body -->
    <div class="p-6">
        @if(isset($images) && count($images) > 0)
        <div class="flex flex-col gap-3" id="gallery-list">
            @foreach($images as $index => $image)
            <div class="gallery-item flex items-center gap-3 p-3 bg-white border border-gray-200 rounded-lg transition-shadow hover:shadow-md"
                 data-id="{{ $image->id }}">
                <!-- Drag Handle -->
                <div class="drag-handle cursor-grab active:cursor-grabbing text-gray-400">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="9" cy="5" r="1"/><circle cx="9" cy="12" r="1"/><circle cx="9" cy="19" r="1"/>
                        <circle cx="15" cy="5" r="1"/><circle cx="15" cy="12" r="1"/><circle cx="15" cy="19" r="1"/>
                    </svg>
                </div>

                <!-- Thumbnail -->
                <img src="{{ asset('storage/' . $image->image_path) }}"
                     alt="{{ $image->caption ?? 'Gallery' }}"
                     class="w-20 h-20 object-cover rounded">

                <!-- Info -->
                <div class="flex-1">
                    <p class="text-sm text-gray-500">Order: {{ $image->display_order ?? $index }}</p>
                    @if($image->caption)
                    <p class="text-sm text-gray-700 truncate max-w-[200px]">{{ $image->caption }}</p>
                    @endif
                </div>

                <!-- Delete Button -->
                <button class="inline-flex items-center justify-center w-9 h-9 bg-red-500 text-white rounded hover:bg-red-600 transition-colors"
                        onclick="deleteImage({{ $image->id }})">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="3 6 5 6 21 6"></polyline>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                        <line x1="10" y1="11" x2="10" y2="17"></line>
                        <line x1="14" y1="11" x2="14" y2="17"></line>
                    </svg>
                </button>
            </div>
            @endforeach
        </div>
        @else
        <div class="text-center py-8 text-gray-500">
            <p>No pictures in gallery. Add some pictures to get started!</p>
        </div>
        @endif
    </div>

    @include('admin.club.gallery.add')
</div>

@push('scripts')
<script>
function deleteImage(id) {
    confirmAction({
        title: 'Delete Picture',
        message: 'This picture will be permanently removed from the gallery.',
        confirmText: 'Delete',
        type: 'danger',
    }).then(confirmed => {
        if (!confirmed) return;

        fetch(`{{ url('admin/club/' . $club->slug . '/gallery') }}/${id}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert(data.message || 'Failed to delete picture');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to delete picture');
        });
    });
}
</script>
@endpush
@endsection
