@extends('layouts.admin-club')

@section('club-admin-content')
<div class="bg-white rounded-lg shadow" x-data="{ showUploadModal: false }">
    <!-- Card Header -->
    <div class="flex items-center justify-between border-b border-border px-6 py-4">
        <div>
            <h5 class="text-lg font-semibold text-foreground">Club Gallery</h5>
            <p class="text-sm text-gray-500 mt-0.5">Drag to reorder — top item appears first in the banner</p>
        </div>
        <button class="btn btn-primary"
                @click="showUploadModal = true">
            <i class="bi bi-plus-lg mr-2"></i>
            Add Picture
        </button>
    </div>

    <!-- Card Body -->
    <div class="p-6">
        @if(isset($images) && count($images) > 0)

        <!-- Save order button (hidden until order changes) -->
        <div id="reorder-bar" class="hidden mb-4 flex items-center gap-3 p-3 bg-blue-50 border border-blue-200 rounded-lg">
            <i class="bi bi-info-circle text-blue-500"></i>
            <span class="text-sm text-blue-700 flex-1">Order changed — save to apply on the public page.</span>
            <button onclick="saveOrder()" class="btn btn-primary btn-sm">
                <i class="bi bi-check-lg mr-1"></i>Save Order
            </button>
            <button onclick="cancelReorder()" class="btn btn-light btn-sm">Cancel</button>
        </div>

        <div class="flex flex-col gap-3" id="gallery-list">
            @foreach($images as $index => $image)
            <div class="gallery-item flex items-center gap-3 p-3 bg-white border border-gray-200 rounded-lg transition-shadow hover:shadow-md"
                 data-id="{{ $image->id }}">

                <!-- Position badge -->
                <div class="position-badge flex items-center justify-center w-7 h-7 rounded-full bg-gray-100 text-gray-500 text-xs font-bold flex-shrink-0">
                    {{ $index + 1 }}
                </div>

                <!-- Drag Handle -->
                <div class="drag-handle cursor-grab active:cursor-grabbing text-gray-400 hover:text-gray-600 flex-shrink-0" title="Drag to reorder">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="9" cy="5" r="1"/><circle cx="9" cy="12" r="1"/><circle cx="9" cy="19" r="1"/>
                        <circle cx="15" cy="5" r="1"/><circle cx="15" cy="12" r="1"/><circle cx="15" cy="19" r="1"/>
                    </svg>
                </div>

                <!-- Thumbnail -->
                <img src="{{ asset('storage/' . $image->image_path) }}"
                     alt="{{ $image->caption ?? 'Gallery' }}"
                     class="w-20 h-16 object-cover rounded flex-shrink-0">

                <!-- Info -->
                <div class="flex-1 min-w-0">
                    @if($image->caption)
                    <p class="text-sm text-gray-700 truncate">{{ $image->caption }}</p>
                    @else
                    <p class="text-sm text-gray-400 italic">No caption</p>
                    @endif
                </div>

                <!-- First badge -->
                @if($index === 0)
                <span class="text-xs font-medium bg-primary/10 text-primary px-2 py-1 rounded-full flex-shrink-0">
                    <i class="bi bi-star-fill mr-1"></i>First in banner
                </span>
                @endif

                <!-- Delete Button -->
                <button class="inline-flex items-center justify-center w-9 h-9 bg-red-500 text-white rounded hover:bg-red-600 transition-colors flex-shrink-0"
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
            <i class="bi bi-images" style="font-size: 2.5rem; opacity: 0.3;"></i>
            <p class="mt-3">No pictures in gallery. Add some pictures to get started!</p>
        </div>
        @endif
    </div>

    @include('admin.club.gallery.add')
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
const reorderUrl = '{{ route('admin.club.gallery.reorder', $club->slug) }}';
const csrfToken = '{{ csrf_token() }}';
let originalOrder = null;

const list = document.getElementById('gallery-list');

if (list) {
    // Capture original order on page load
    originalOrder = getOrder();

    Sortable.create(list, {
        handle: '.drag-handle',
        animation: 150,
        ghostClass: 'opacity-40',
        dragClass: 'shadow-lg',
        onEnd() {
            updatePositionBadges();
            const changed = JSON.stringify(getOrder()) !== JSON.stringify(originalOrder);
            document.getElementById('reorder-bar').classList.toggle('hidden', !changed);
        }
    });
}

function getOrder() {
    return [...list.querySelectorAll('.gallery-item')].map(el => parseInt(el.dataset.id));
}

function updatePositionBadges() {
    list.querySelectorAll('.gallery-item').forEach((el, i) => {
        el.querySelector('.position-badge').textContent = i + 1;

        // Update "First in banner" badge
        const existing = el.querySelector('.first-badge');
        if (i === 0) {
            if (!existing) {
                const badge = document.createElement('span');
                badge.className = 'first-badge text-xs font-medium bg-primary/10 text-primary px-2 py-1 rounded-full flex-shrink-0';
                badge.innerHTML = '<i class="bi bi-star-fill mr-1"></i>First in banner';
                el.querySelector('button').before(badge);
            }
        } else if (existing) {
            existing.remove();
        }
    });
}

function saveOrder() {
    const order = getOrder();
    fetch(reorderUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json',
        },
        body: JSON.stringify({ order })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            originalOrder = order;
            document.getElementById('reorder-bar').classList.add('hidden');
        }
    });
}

function cancelReorder() {
    // Re-sort DOM back to original order
    originalOrder.forEach(id => {
        const el = list.querySelector(`[data-id="${id}"]`);
        list.appendChild(el);
    });
    updatePositionBadges();
    document.getElementById('reorder-bar').classList.add('hidden');
}

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
                'X-CSRF-TOKEN': csrfToken,
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
        .catch(() => alert('Failed to delete picture'));
    });
}
</script>
@endpush
@endsection
