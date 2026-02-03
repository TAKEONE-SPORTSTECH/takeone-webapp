@extends('layouts.admin-club')

@section('club-admin-content')
<div>
    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    @endif

    @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    @endif

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="h3 fw-bold mb-1">Activities</h2>
            <p class="text-muted mb-0">Manage club activities and classes</p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addActivityModal">
            <i class="bi bi-plus-lg me-2"></i>Add Activity
        </button>
    </div>

    @if(isset($activities) && count($activities) > 0)
    <div class="row g-4">
        @foreach($activities as $activity)
        <div class="col-md-6 col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                {{-- Activity Image --}}
                @if($activity->picture_url)
                <div class="card-img-top" style="height: 180px; overflow: hidden;">
                    <img src="{{ asset('storage/' . $activity->picture_url) }}"
                         alt="{{ $activity->name }}"
                         class="w-100 h-100"
                         style="object-fit: cover;">
                </div>
                @else
                <div class="card-img-top bg-primary bg-opacity-10 d-flex align-items-center justify-content-center" style="height: 180px;">
                    <i class="bi bi-activity text-primary" style="font-size: 3rem;"></i>
                </div>
                @endif

                {{-- Card Header with Title and Actions --}}
                <div class="card-header bg-white border-0 pb-0">
                    <div class="d-flex justify-content-between align-items-start">
                        <h5 class="card-title fw-semibold mb-0">{{ $activity->name }}</h5>
                        <div class="d-flex gap-1">
                            <button class="btn btn-sm btn-outline-secondary" title="Duplicate Activity"
                                    onclick="duplicateActivity('{{ $activity->name }}', '{{ addslashes($activity->description) }}', '{{ addslashes($activity->notes) }}', '{{ $activity->picture_url ? asset('storage/' . $activity->picture_url) : '' }}')">
                                <i class="bi bi-copy"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-primary" title="Edit Activity"
                                    onclick="openEditActivityModal(
                                        {{ $activity->id }},
                                        '{{ addslashes($activity->name) }}',
                                        '{{ addslashes($activity->description) }}',
                                        '{{ addslashes($activity->notes) }}',
                                        '{{ $activity->picture_url ? asset('storage/' . $activity->picture_url) : '' }}',
                                        '{{ route('admin.club.activities.update', [$club->id, $activity->id]) }}'
                                    )">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <form action="{{ route('admin.club.activities.destroy', [$club->id, $activity->id]) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this activity?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Activity">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                {{-- Card Body --}}
                <div class="card-body pt-2">
                    @if($activity->description)
                    <p class="text-muted small mb-2" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                        {{ $activity->description }}
                    </p>
                    @endif

                    @if($activity->notes)
                    <div class="bg-light rounded p-2 mb-2">
                        <small class="text-muted">{{ $activity->notes }}</small>
                    </div>
                    @endif

                    {{-- Additional Info Badges --}}
                    <div class="d-flex flex-wrap gap-2 mt-3">
                        @if($activity->duration_minutes)
                        <span class="badge bg-light text-dark border">
                            <i class="bi bi-clock me-1"></i>{{ $activity->duration_minutes }} min
                        </span>
                        @endif
                        @if($activity->facility)
                        <span class="badge bg-light text-dark border">
                            <i class="bi bi-geo-alt me-1"></i>{{ $activity->facility->name }}
                        </span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>
    @else
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
            <i class="bi bi-activity text-muted" style="font-size: 4rem;"></i>
            <h5 class="mt-3 mb-2">No activities yet</h5>
            <p class="text-muted mb-3">Add activities and classes for your members</p>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addActivityModal">
                <i class="bi bi-plus-lg me-2"></i>Add Activity
            </button>
        </div>
    </div>
    @endif
</div>

@include('admin.club.activities.add')
@include('admin.club.activities.edit')

@push('scripts')
<script>
function duplicateActivity(name, description, notes, pictureUrl) {
    document.getElementById('activityTitle').value = name + ' (Copy)';
    document.getElementById('activityDescription').value = description || '';
    document.getElementById('activityNotes').value = notes || '';

    // Set existing picture if available
    if (pictureUrl && typeof setExistingPicture === 'function') {
        setExistingPicture(pictureUrl);
    }

    const modal = new bootstrap.Modal(document.getElementById('addActivityModal'));
    modal.show();
}
</script>
@endpush
@endsection
