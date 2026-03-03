@extends('layouts.admin-club')

@section('club-admin-content')
<div x-data="eventsAdmin()" x-init="init()">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-xl font-bold text-foreground">Events</h2>
            <p class="text-sm text-muted-foreground mt-0.5">Manage events shown on your public club page</p>
        </div>
        <button @click="openAdd()" class="btn btn-primary">
            <i class="bi bi-plus-lg mr-2"></i>Add Event
        </button>
    </div>

    {{-- Session messages --}}
    @if(session('success'))
        <div class="alert alert-success mb-4">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger mb-4">{{ session('error') }}</div>
    @endif

    {{-- Events list --}}
    @if($events->isEmpty())
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-16">
                <i class="bi bi-calendar-x text-muted-foreground" style="font-size:2.5rem;opacity:.3;"></i>
                <p class="mt-3 text-muted-foreground">No events yet. Add your first event to display it on your club page.</p>
                <button @click="openAdd()" class="btn btn-primary mt-2">
                    <i class="bi bi-plus-lg mr-2"></i>Add Event
                </button>
            </div>
        </div>
    @else
        <div class="flex flex-col gap-3">
            @foreach($events as $event)
            @php
                $isPast = $event->date->isPast();
                $pillColor = $event->color ?: '#1d4ed8';
                $tagsArr = is_array($event->tags) ? $event->tags : [];
            @endphp
            <div class="card border-0 shadow-sm overflow-hidden {{ $isPast ? 'opacity-60' : '' }}">
                <div class="card-body p-4">
                    <div class="flex items-start gap-4">

                        {{-- Date pill --}}
                        <div class="flex-shrink-0 rounded-xl text-white text-center px-3 py-2 min-w-[56px]"
                             style="background:{{ $pillColor }};">
                            <div class="text-xs font-semibold uppercase">{{ $event->date->format('D') }}</div>
                            <div class="text-2xl font-extrabold leading-none">{{ $event->date->format('d') }}</div>
                            <div class="text-xs font-semibold uppercase">{{ $event->date->format('M') }}</div>
                        </div>

                        {{-- Main info --}}
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap mb-1">
                                <span class="font-semibold text-foreground">{{ $event->title }}</span>
                                @if($event->ribbon_label)
                                    <span class="badge text-xs {{ $event->ribbon_type === 'limited' ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700' }}">
                                        {{ $event->ribbon_label }}
                                    </span>
                                @endif
                                @if($event->status === 'cancelled')
                                    <span class="badge bg-gray-100 text-gray-500 text-xs">Cancelled</span>
                                @elseif($event->status === 'completed')
                                    <span class="badge bg-blue-100 text-blue-600 text-xs">Completed</span>
                                @endif
                            </div>
                            <div class="flex flex-wrap gap-x-4 gap-y-0.5 text-xs text-muted-foreground mb-1">
                                <span><i class="bi bi-clock mr-1"></i>{{ \Carbon\Carbon::parse($event->start_time)->format('g:i A') }}{{ $event->end_time ? ' - ' . \Carbon\Carbon::parse($event->end_time)->format('g:i A') : '' }}</span>
                                @if($event->location)<span><i class="bi bi-geo-alt mr-1"></i>{{ $event->location }}</span>@endif
                                @if($event->level)<span><i class="bi bi-bar-chart mr-1"></i>{{ $event->level }}</span>@endif
                                @if($event->max_capacity)<span><i class="bi bi-people mr-1"></i>{{ $event->spots_taken }} / {{ $event->max_capacity }} spots</span>@endif
                            </div>
                            @if($tagsArr)
                                <div class="flex flex-wrap gap-1 mt-1">
                                    @foreach($tagsArr as $tag)
                                        <span class="badge bg-muted/40 text-foreground text-xs border border-border">{{ $tag }}</span>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        {{-- Actions --}}
                        <div class="flex gap-2 flex-shrink-0">
                            <button @click="openEdit({{ $event->id }})"
                                    class="btn btn-sm btn-outline-secondary" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button @click="deleteEvent({{ $event->id }})"
                                    class="btn btn-sm btn-outline-danger" title="Delete">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    @endif

    {{-- ===== ADD MODAL ===== --}}
    <div x-show="showAddModal" x-cloak
         class="fixed inset-0 z-50 overflow-y-auto"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
        <div class="fixed inset-0 bg-black/50" @click="showAddModal = false"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="modal-content border-0 shadow-lg w-full max-w-xl relative" @click.stop>
                <div class="modal-header border-b border-border px-6 py-4">
                    <h5 class="modal-title text-lg font-semibold">Add Event</h5>
                    <button type="button" class="text-muted-foreground hover:text-foreground" @click="showAddModal = false">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <form action="{{ route('admin.club.events.store', $club->slug) }}" method="POST">
                    @csrf
                    <div class="modal-body px-6 py-4 space-y-4">
                        @include('admin.club.events.partials.form-fields')
                    </div>
                    <div class="modal-footer border-t border-border px-6 py-4 flex justify-end gap-3">
                        <button type="button" class="btn btn-outline-secondary" @click="showAddModal = false">Cancel</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg mr-1"></i>Save Event</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- ===== EDIT MODAL ===== --}}
    <div x-show="showEditModal" x-cloak
         class="fixed inset-0 z-50 overflow-y-auto"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
        <div class="fixed inset-0 bg-black/50" @click="showEditModal = false"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="modal-content border-0 shadow-lg w-full max-w-xl relative" @click.stop>
                <div class="modal-header border-b border-border px-6 py-4">
                    <h5 class="modal-title text-lg font-semibold">Edit Event</h5>
                    <button type="button" class="text-muted-foreground hover:text-foreground" @click="showEditModal = false">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <form :action="editUrl" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="modal-body px-6 py-4 space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="md:col-span-2">
                                <label class="form-label">Title <span class="text-red-500">*</span></label>
                                <input type="text" name="title" class="form-control" required :value="editData.title">
                            </div>
                            <div>
                                <label class="form-label">Date <span class="text-red-500">*</span></label>
                                <input type="date" name="date" class="form-control" required :value="editData.date">
                            </div>
                            <div>
                                <label class="form-label">Color (date pill)</label>
                                <input type="color" name="color" class="form-control h-10 p-1 cursor-pointer" :value="editData.color || '#1d4ed8'">
                            </div>
                            <div>
                                <label class="form-label">Start Time <span class="text-red-500">*</span></label>
                                <input type="time" name="start_time" class="form-control" required :value="editData.start_time">
                            </div>
                            <div>
                                <label class="form-label">End Time</label>
                                <input type="time" name="end_time" class="form-control" :value="editData.end_time">
                            </div>
                            <div>
                                <label class="form-label">Location</label>
                                <input type="text" name="location" class="form-control" :value="editData.location">
                            </div>
                            <div>
                                <label class="form-label">Level / Audience</label>
                                <input type="text" name="level" class="form-control" placeholder="e.g. Ages 5+, All levels" :value="editData.level">
                            </div>
                            <div>
                                <label class="form-label">Max Capacity</label>
                                <input type="number" name="max_capacity" class="form-control" min="1" :value="editData.max_capacity">
                            </div>
                            <div>
                                <label class="form-label">Spots Taken</label>
                                <input type="number" name="spots_taken" class="form-control" min="0" :value="editData.spots_taken || 0">
                            </div>
                            <div>
                                <label class="form-label">Ribbon Label</label>
                                <input type="text" name="ribbon_label" class="form-control" placeholder="e.g. Limited Seats" :value="editData.ribbon_label">
                            </div>
                            <div>
                                <label class="form-label">Ribbon Style</label>
                                <select name="ribbon_type" class="form-control">
                                    <option value="" :selected="!editData.ribbon_type">Default (green)</option>
                                    <option value="limited" :selected="editData.ribbon_type === 'limited'">Limited (red)</option>
                                </select>
                            </div>
                            <div>
                                <label class="form-label">CTA Button Text</label>
                                <input type="text" name="cta_text" class="form-control" placeholder="e.g. Join Event" :value="editData.cta_text">
                            </div>
                            <div>
                                <label class="form-label">Status</label>
                                <select name="status" class="form-control">
                                    <option value="active" :selected="editData.status === 'active'">Active</option>
                                    <option value="completed" :selected="editData.status === 'completed'">Completed</option>
                                    <option value="cancelled" :selected="editData.status === 'cancelled'">Cancelled</option>
                                </select>
                            </div>
                            <div class="md:col-span-2">
                                <label class="form-label">Tags <span class="text-xs text-muted-foreground">(comma-separated)</span></label>
                                <input type="text" name="tags" class="form-control" placeholder="Public event, WT rules, Highlight reels" :value="editData.tags_str">
                            </div>
                            <div class="md:col-span-2">
                                <label class="form-label">Description</label>
                                <textarea name="description" class="form-control" rows="3" x-text="editData.description"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-t border-border px-6 py-4 flex justify-end gap-3">
                        <button type="button" class="btn btn-outline-secondary" @click="showEditModal = false">Cancel</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg mr-1"></i>Update Event</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>

@push('scripts')
<script>
const eventsData = @json($events->map(fn($e) => [
    'id'           => $e->id,
    'title'        => $e->title,
    'date'         => $e->date->format('Y-m-d'),
    'start_time'   => substr($e->start_time, 0, 5),
    'end_time'     => $e->end_time ? substr($e->end_time, 0, 5) : '',
    'location'     => $e->location,
    'level'        => $e->level,
    'description'  => $e->description,
    'max_capacity' => $e->max_capacity,
    'spots_taken'  => $e->spots_taken,
    'ribbon_label' => $e->ribbon_label,
    'ribbon_type'  => $e->ribbon_type,
    'tags'         => $e->tags,
    'tags_str'     => $e->tags ? implode(', ', $e->tags) : '',
    'color'        => $e->color,
    'cta_text'     => $e->cta_text,
    'status'       => $e->status,
]));

const baseUrl = '{{ route('admin.club.events.store', $club->slug) }}';

function eventsAdmin() {
    return {
        showAddModal:  false,
        showEditModal: false,
        editData:      {},
        editUrl:       '',

        init() {},

        openAdd() {
            this.showAddModal = true;
        },

        openEdit(id) {
            const ev = eventsData.find(e => e.id === id);
            if (!ev) return;
            this.editData = { ...ev };
            this.editUrl  = baseUrl.replace('/events', '/events/' + id).replace('POST', 'PUT');
            // Build the PUT URL from the base store URL
            this.editUrl = '{{ url('admin/club/' . $club->slug . '/events') }}/' + id;
            this.showEditModal = true;
        },

        deleteEvent(id) {
            confirmAction({
                title:       'Delete Event',
                message:     'This event will be permanently removed.',
                confirmText: 'Delete',
                type:        'danger',
            }).then(confirmed => {
                if (!confirmed) return;
                fetch('{{ url('admin/club/' . $club->slug . '/events') }}/' + id, {
                    method:  'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept':       'application/json',
                    },
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) location.reload();
                    else alert(data.message || 'Failed to delete event.');
                })
                .catch(() => alert('Failed to delete event.'));
            });
        },
    };
}
</script>
@endpush
@endsection
