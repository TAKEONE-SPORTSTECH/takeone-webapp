@extends('layouts.admin-club')

@section('club-admin-content')
<div id="activitiesContainer" x-data="{
    showAddModal: false,
    showEditModal: false,
    editData: {},
    duplicateData: null
}">
    @if(session('success'))
    <div class="alert alert-success mb-4" role="alert" x-data="{ show: true }" x-show="show">
        {{ session('success') }}
        <button type="button" class="btn-close" @click="show = false"></button>
    </div>
    @endif

    @if(session('error'))
    <div class="alert alert-danger mb-4" role="alert" x-data="{ show: true }" x-show="show">
        {{ session('error') }}
        <button type="button" class="btn-close" @click="show = false"></button>
    </div>
    @endif

    <div class="flex justify-between items-center mb-4">
        <div>
            <h2 class="text-2xl font-bold mb-1">Activities</h2>
            <p class="text-muted-foreground mb-0">Manage club activities and classes</p>
        </div>
        <button class="btn btn-primary" @click="showAddModal = true; duplicateData = null">
            <i class="bi bi-plus-lg mr-2"></i>Add Activity
        </button>
    </div>

    @if(isset($activities) && count($activities) > 0)
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach($activities as $activity)
        <div class="card border-0 shadow-sm h-full">
            {{-- Activity Image --}}
            @if($activity->picture_url)
            <div class="h-44 overflow-hidden">
                <img src="{{ asset('storage/' . $activity->picture_url) }}"
                     alt="{{ $activity->name }}"
                     class="w-full h-full object-cover">
            </div>
            @else
            <div class="h-44 bg-primary/10 flex items-center justify-center">
                <i class="bi bi-activity text-primary text-5xl"></i>
            </div>
            @endif

            {{-- Card Header with Title and Actions --}}
            <div class="card-header bg-white border-0 pb-0">
                <div class="flex justify-between items-start">
                    <h5 class="card-title font-semibold mb-0">{{ $activity->name }}</h5>
                    <div class="flex gap-1">
                        <button class="btn btn-sm btn-outline-secondary" title="Duplicate Activity"
                                @click="duplicateData = {
                                    name: '{{ addslashes($activity->name) }}',
                                    description: '{{ addslashes($activity->description) }}',
                                    notes: '{{ addslashes($activity->notes) }}',
                                    pictureUrl: '{{ $activity->picture_url ? asset('storage/' . $activity->picture_url) : '' }}'
                                }; showAddModal = true">
                            <i class="bi bi-copy"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-primary" title="Edit Activity"
                                @click="editData = {
                                    id: {{ $activity->id }},
                                    name: '{{ addslashes($activity->name) }}',
                                    description: '{{ addslashes($activity->description) }}',
                                    notes: '{{ addslashes($activity->notes) }}',
                                    durationMinutes: {{ $activity->duration_minutes ?? 'null' }},
                                    pictureUrl: '{{ $activity->picture_url ? asset('storage/' . $activity->picture_url) : '' }}',
                                    action: '{{ route('admin.club.activities.update', [$club->id, $activity->id]) }}'
                                }; showEditModal = true">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <form action="{{ route('admin.club.activities.destroy', [$club->id, $activity->id]) }}" method="POST" class="inline"
                              onsubmit="event.preventDefault(); const f = this; confirmAction({ title: 'Delete Activity', message: 'This activity will be permanently removed.', confirmText: 'Delete', type: 'danger' }).then(ok => { if (ok) f.submit(); });">
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
                <p class="text-muted-foreground text-sm mb-2 line-clamp-2">
                    {{ $activity->description }}
                </p>
                @endif

                @if($activity->notes)
                <div class="bg-muted/30 rounded-lg p-2 mb-2">
                    <small class="text-muted-foreground">{{ $activity->notes }}</small>
                </div>
                @endif

                {{-- Additional Info Badges --}}
                <div class="flex flex-wrap gap-2 mt-3">
                    @if($activity->duration_minutes)
                    <span class="badge bg-muted/30 text-foreground border border-border">
                        <i class="bi bi-clock mr-1"></i>{{ $activity->duration_minutes }} min
                    </span>
                    @endif
                    @if($activity->facility)
                    <span class="badge bg-muted/30 text-foreground border border-border">
                        <i class="bi bi-geo-alt mr-1"></i>{{ $activity->facility->name }}
                    </span>
                    @endif
                </div>
            </div>
        </div>
        @endforeach
    </div>
    @else
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-12">
            <i class="bi bi-activity text-muted-foreground text-6xl"></i>
            <h5 class="mt-3 mb-2">No activities yet</h5>
            <p class="text-muted-foreground mb-3">Add activities and classes for your members</p>
            <button class="btn btn-primary" @click="showAddModal = true">
                <i class="bi bi-plus-lg mr-2"></i>Add Activity
            </button>
        </div>
    </div>
    @endif

    <x-activity-modal :club="$club" mode="create" />
    <x-activity-modal :club="$club" mode="edit" />
</div>

<style>
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
@endsection
