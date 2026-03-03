@extends('layouts.admin-club')

@section('club-admin-content')

@php
$achievementsJson = $achievements->map(function($a) {
    return [
        'id'          => $a->id,
        'title'       => $a->title,
        'description' => $a->description ?? '',
        'tag'         => $a->tag,
        'tag_icon'    => $a->tag_icon,
        'image_path'  => $a->image_path ?? '',
        'bg_from'     => $a->bg_from,
        'bg_to'       => $a->bg_to,
        'status'      => $a->status,
        'sort_order'  => $a->sort_order,
    ];
});
@endphp

<div x-data="achievementsAdmin()">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-xl font-bold text-foreground">Latest Achievements</h2>
            <p class="text-sm text-muted-foreground mt-0.5">Manage club achievements and milestones shown on your public page (top 3 active shown)</p>
        </div>
        <button @click="openAdd()" class="btn btn-primary">
            <i class="bi bi-plus-lg mr-2"></i>Add Achievement
        </button>
    </div>

    {{-- Session messages --}}
    @if(session('success'))
        <div class="alert alert-success mb-4">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger mb-4">{{ session('error') }}</div>
    @endif

    {{-- Achievements list --}}
    @if($achievements->isEmpty())
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-16">
                <i class="bi bi-trophy text-muted-foreground" style="font-size:2.5rem;opacity:.3;"></i>
                <p class="mt-3 text-muted-foreground">No achievements yet. Add your first club achievement.</p>
                <button @click="openAdd()" class="btn btn-primary mt-2">
                    <i class="bi bi-plus-lg mr-2"></i>Add Achievement
                </button>
            </div>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            @foreach($achievements as $achievement)
            @php $isInactive = $achievement->status === 'inactive'; @endphp
            <div class="card border-0 shadow-sm overflow-hidden {{ $isInactive ? 'opacity-60' : '' }}">
                {{-- Card visual --}}
                <div class="relative" style="height:120px;">
                    @if($achievement->image_path)
                        <img src="{{ asset('storage/' . $achievement->image_path) }}"
                             class="w-full h-full object-cover" alt="{{ $achievement->title }}">
                    @else
                        <div class="w-full h-full flex items-center justify-center"
                             style="background: linear-gradient(135deg, {{ $achievement->bg_from }}, {{ $achievement->bg_to }});"></div>
                    @endif
                    {{-- Tag --}}
                    <span class="absolute bottom-2 left-2 text-xs font-semibold px-2 py-1 rounded-full bg-black/50 text-white">
                        <i class="bi {{ $achievement->tag_icon }} mr-1"></i>{{ $achievement->tag }}
                    </span>
                </div>
                {{-- Card body --}}
                <div class="card-body p-4">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <div class="font-semibold text-foreground truncate">{{ $achievement->title }}</div>
                            @if($achievement->description)
                            <div class="text-xs text-muted-foreground mt-0.5 truncate">{{ $achievement->description }}</div>
                            @endif
                        </div>
                        <div class="flex gap-1.5 flex-shrink-0">
                            <button @click="openEdit({{ $achievement->id }})"
                                    class="btn btn-sm btn-outline-secondary" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button @click="deleteAchievement({{ $achievement->id }})"
                                    class="btn btn-sm btn-outline-danger" title="Delete">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                    @if($isInactive)
                    <span class="badge bg-gray-100 text-gray-500 text-xs mt-2">Inactive</span>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
    @endif

    {{-- ===== SINGLE MODAL (Add & Edit) ===== --}}
    <div x-show="showModal" x-cloak
         class="fixed inset-0 z-50 overflow-y-auto"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
        <div class="fixed inset-0 bg-black/50" @click="showModal = false"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="modal-content border-0 shadow-lg w-full max-w-2xl relative" @click.stop>
                <div class="modal-header border-b border-border px-6 py-4">
                    <h5 class="modal-title text-lg font-semibold" x-text="isEdit ? 'Edit Achievement' : 'Add Achievement'"></h5>
                    <button type="button" class="text-muted-foreground hover:text-foreground" @click="showModal = false">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <form :action="formAction" method="POST" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="_method" :value="isEdit ? 'PUT' : 'POST'">
                    <div class="modal-body px-6 py-4 max-h-[70vh] overflow-y-auto">
                        @include('admin.club.achievements.partials.form-fields')
                    </div>
                    <div class="modal-footer border-t border-border px-6 py-4 flex justify-end gap-3">
                        <button type="button" class="btn btn-outline-secondary" @click="showModal = false">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg mr-1"></i>
                            <span x-text="isEdit ? 'Update Achievement' : 'Save Achievement'"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>

@push('scripts')
<script>
const achievementsData = @json($achievementsJson);
const storeUrl         = '{{ route('admin.club.achievements.store', $club->slug) }}';
const baseEditUrl      = '{{ url('admin/club/' . $club->slug . '/achievements') }}';

const emptyForm = {
    title: '', description: '', tag: '', tag_icon: 'bi-trophy',
    image_path: '', remove_image: false,
    bg_from: '#f59e0b', bg_to: '#f97316',
    status: 'active', sort_order: 0,
};

const achievementIcons = [
    { value: 'bi-trophy',          label: 'Trophy' },
    { value: 'bi-trophy-fill',     label: 'Trophy Fill' },
    { value: 'bi-award',           label: 'Award' },
    { value: 'bi-award-fill',      label: 'Award Fill' },
    { value: 'bi-star',            label: 'Star' },
    { value: 'bi-star-fill',       label: 'Star Fill' },
    { value: 'bi-medal',           label: 'Medal' },
    { value: 'bi-patch-check',     label: 'Verified' },
    { value: 'bi-patch-check-fill',label: 'Verified Fill' },
    { value: 'bi-patch-star',      label: 'Star Patch' },
    { value: 'bi-gem',             label: 'Gem' },
    { value: 'bi-crown',           label: 'Crown' },
    { value: 'bi-crown-fill',      label: 'Crown Fill' },
    { value: 'bi-shield-check',    label: 'Shield' },
    { value: 'bi-flag',            label: 'Flag' },
    { value: 'bi-flag-fill',       label: 'Flag Fill' },
    { value: 'bi-lightning',       label: 'Lightning' },
    { value: 'bi-lightning-fill',  label: 'Lightning Fill' },
    { value: 'bi-fire',            label: 'Fire' },
    { value: 'bi-rocket',          label: 'Rocket' },
    { value: 'bi-rocket-fill',     label: 'Rocket Fill' },
    { value: 'bi-bullseye',        label: 'Target' },
    { value: 'bi-graph-up-arrow',  label: 'Growth' },
    { value: 'bi-people',          label: 'Team' },
    { value: 'bi-people-fill',     label: 'Team Fill' },
    { value: 'bi-hand-thumbs-up',  label: 'Thumbs Up' },
    { value: 'bi-heart',           label: 'Heart' },
    { value: 'bi-heart-fill',      label: 'Heart Fill' },
    { value: 'bi-bookmark-star',   label: 'Bookmark Star' },
    { value: 'bi-emoji-smile',     label: 'Smile' },
];

function achievementsAdmin() {
    return {
        showModal:      false,
        isEdit:         false,
        formAction:     storeUrl,
        formData:       { ...emptyForm },
        showIconPicker: false,
        icons:          achievementIcons,

        openAdd() {
            this.isEdit         = false;
            this.formAction     = storeUrl;
            this.formData       = { ...emptyForm };
            this.showIconPicker = false;
            this.showModal      = true;
        },

        openEdit(id) {
            const a = achievementsData.find(a => a.id === id);
            if (!a) return;
            this.isEdit         = true;
            this.formAction     = baseEditUrl + '/' + id;
            this.formData       = { ...emptyForm, ...a, remove_image: false };
            this.showIconPicker = false;
            this.showModal      = true;
        },

        deleteAchievement(id) {
            confirmAction({
                title:       'Delete Achievement',
                message:     'This achievement will be permanently removed.',
                confirmText: 'Delete',
                type:        'danger',
            }).then(confirmed => {
                if (!confirmed) return;
                fetch(baseEditUrl + '/' + id, {
                    method:  'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept':       'application/json',
                    },
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) location.reload();
                    else alert(data.message || 'Failed to delete achievement.');
                })
                .catch(() => alert('Failed to delete achievement.'));
            });
        },
    };
}
</script>
@endpush
@endsection
