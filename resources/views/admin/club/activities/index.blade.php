@extends('layouts.admin-club')

@section('club-admin-content')
<div id="activitiesContainer" x-data="{
    showAddModal: false,
    showEditModal: false,
    editData: {},
    duplicateData: null
}">
    <div class="flex justify-between items-center mb-4">
        <div>
            <h2 class="tf-section-title">Activities</h2>
            <p class="text-sm text-gray-500 mt-1">Manage club activities and classes</p>
        </div>
        <button class="btn btn-primary" @click="showAddModal = true; duplicateData = null">
            <i class="bi bi-plus-lg mr-2"></i>Add Activity
        </button>
    </div>

    <div id="activitiesGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 {{ (isset($activities) && count($activities) > 0) ? '' : 'hidden' }}">
        @if(isset($activities) && count($activities) > 0)
        @foreach($activities as $activity)
        <div id="activity-{{ $activity->id }}" data-activity-id="{{ $activity->id }}" class="card border-0 shadow-sm h-full activity-card">
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
                        <button class="btn btn-sm btn-outline-secondary" title="Equipment"
                                @click="window.dispatchEvent(new CustomEvent('open-equipment-manager', { detail: { id: {{ $activity->id }}, name: '{{ addslashes($activity->name) }}' } }))">
                            <i class="bi bi-box-seam"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-primary" title="Edit Activity"
                                @click="editData = {
                                    id: {{ $activity->id }},
                                    name: '{{ addslashes($activity->name) }}',
                                    description: '{{ addslashes($activity->description) }}',
                                    notes: '{{ addslashes($activity->notes) }}',
                                    translations: {{ Illuminate\Support\Js::from($activity->translations ?? []) }},
                                    pictureUrl: '{{ $activity->picture_url ? asset('storage/' . $activity->picture_url) : '' }}',
                                    action: '{{ route('admin.club.activities.update', [$club->slug, $activity->id]) }}'
                                }; showEditModal = true">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <form action="{{ route('admin.club.activities.destroy', [$club->slug, $activity->id]) }}" method="POST" class="inline"
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
                    @if($activity->facility)
                    <span class="badge bg-muted/30 text-foreground border border-border">
                        <i class="bi bi-geo-alt mr-1"></i>{{ $activity->facility->name }}
                    </span>
                    @endif
                </div>
            </div>
        </div>
        @endforeach
        @endif
    </div>

    <div id="activitiesEmptyState" class="card border-0 shadow-sm {{ (isset($activities) && count($activities) > 0) ? 'hidden' : '' }}">
        <div class="card-body text-center py-12">
            <i class="bi bi-activity text-muted-foreground text-6xl"></i>
            <h5 class="mt-3 mb-2">No activities yet</h5>
            <p class="text-muted-foreground mb-3">Add activities and classes for your members</p>
            <button class="btn btn-primary" @click="showAddModal = true">
                <i class="bi bi-plus-lg mr-2"></i>Add Activity
            </button>
        </div>
    </div>

    <x-activity-modal :club="$club" mode="create" />
    <x-activity-modal :club="$club" mode="edit" />
    <x-activity-equipment-modal :club="$club" />

    {{-- Hidden template for building a new activity card client-side (mirrors the loop markup above exactly). --}}
    <template id="activityCardTemplate">
        <div class="card border-0 shadow-sm h-full activity-card">
            <div data-img-slot class="h-44 bg-primary/10 flex items-center justify-center overflow-hidden">
                <i class="bi bi-activity text-primary text-5xl"></i>
            </div>

            <div class="card-header bg-white border-0 pb-0">
                <div class="flex justify-between items-start">
                    <h5 data-name class="card-title font-semibold mb-0"></h5>
                    <div class="flex gap-1">
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-act="duplicate" title="Duplicate Activity">
                            <i class="bi bi-copy"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-act="equipment" title="Equipment">
                            <i class="bi bi-box-seam"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-primary" data-act="edit" title="Edit Activity">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <form data-act="destroy" method="POST" class="inline"
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

            <div class="card-body pt-2">
                <p data-description class="text-muted-foreground text-sm mb-2 line-clamp-2 hidden"></p>
                <div data-notes-wrap class="bg-muted/30 rounded-lg p-2 mb-2 hidden">
                    <small data-notes class="text-muted-foreground"></small>
                </div>
                <div class="flex flex-wrap gap-2 mt-3">
                    <span data-facility-wrap class="badge bg-muted/30 text-foreground border border-border hidden">
                        <i class="bi bi-geo-alt mr-1"></i><span data-facility></span>
                    </span>
                </div>
            </div>
        </div>
    </template>
</div>

@push('scripts')
<script>
(function () {
    const DESTROY_TPL = "{{ route('admin.club.activities.destroy', [$club->slug, '__ID__']) }}";
    const UPDATE_TPL  = "{{ route('admin.club.activities.update', [$club->slug, '__ID__']) }}";

    function bust(src, ts) {
        if (!src) return '';
        return src + (src.indexOf('?') === -1 ? '?' : '&') + 't=' + (ts || Date.now());
    }

    function patchCard(card, a) {
        // Image
        const imgSlot = card.querySelector('[data-img-slot]');
        if (a.picture_src) {
            imgSlot.className = 'h-44 overflow-hidden';
            imgSlot.innerHTML = '<img alt="" class="w-full h-full object-cover">';
            const img = imgSlot.querySelector('img');
            img.alt = a.name || '';
            img.src = bust(a.picture_src, a.updated_at);
        } else {
            imgSlot.className = 'h-44 bg-primary/10 flex items-center justify-center overflow-hidden';
            imgSlot.innerHTML = '<i class="bi bi-activity text-primary text-5xl"></i>';
        }

        // Name
        card.querySelector('[data-name]').textContent = a.name || '';

        // Description
        const desc = card.querySelector('[data-description]');
        if (a.description) { desc.textContent = a.description; desc.classList.remove('hidden'); }
        else { desc.textContent = ''; desc.classList.add('hidden'); }

        // Notes
        const notesWrap = card.querySelector('[data-notes-wrap]');
        const notes = card.querySelector('[data-notes]');
        if (a.notes) { notes.textContent = a.notes; notesWrap.classList.remove('hidden'); }
        else { notes.textContent = ''; notesWrap.classList.add('hidden'); }

        // Facility badge
        const facWrap = card.querySelector('[data-facility-wrap]');
        const fac = card.querySelector('[data-facility]');
        if (a.facility && a.facility.name) { fac.textContent = a.facility.name; facWrap.classList.remove('hidden'); }
        else { fac.textContent = ''; facWrap.classList.add('hidden'); }

        // Wire action buttons (clone template only)
        wireActions(card, a);
    }

    function wireActions(card, a) {
        const container = document.getElementById('activitiesContainer');
        const cmp = container && window.Alpine ? Alpine.$data(container) : null;
        const picUrl = a.picture_src ? a.picture_src : '';

        const dup = card.querySelector('[data-act="duplicate"]');
        if (dup) dup.onclick = function () {
            if (!cmp) return;
            cmp.duplicateData = { name: a.name || '', description: a.description || '', notes: a.notes || '', pictureUrl: picUrl };
            cmp.showAddModal = true;
        };

        const edit = card.querySelector('[data-act="edit"]');
        if (edit) edit.onclick = function () {
            if (!cmp) return;
            cmp.editData = {
                id: a.id,
                name: a.name || '',
                description: a.description || '',
                notes: a.notes || '',
                translations: a.translations || {},
                pictureUrl: picUrl,
                action: UPDATE_TPL.replace('__ID__', a.id)
            };
            cmp.showEditModal = true;
        };

        const equip = card.querySelector('[data-act="equipment"]');
        if (equip) equip.onclick = function () {
            window.dispatchEvent(new CustomEvent('open-equipment-manager', { detail: { id: a.id, name: a.name || '' } }));
        };

        const form = card.querySelector('form[data-act="destroy"]');
        if (form) form.setAttribute('action', DESTROY_TPL.replace('__ID__', a.id));
    }

    function createCard(a) {
        const tpl = document.getElementById('activityCardTemplate');
        const card = tpl.content.firstElementChild.cloneNode(true);
        card.id = 'activity-' + a.id;
        card.setAttribute('data-activity-id', a.id);
        patchCard(card, a);
        return card;
    }

    window.addEventListener('activity-saved', function (e) {
        const a = e.detail && e.detail.activity;
        const mode = e.detail && e.detail.mode;
        if (!a) return;

        const grid = document.getElementById('activitiesGrid');
        const emptyState = document.getElementById('activitiesEmptyState');

        if (mode === 'edit') {
            const card = document.getElementById('activity-' + a.id);
            if (card) { patchCard(card, a); }
            return;
        }

        // CREATE
        const card = createCard(a);
        grid.appendChild(card);
        grid.classList.remove('hidden');
        if (emptyState) emptyState.classList.add('hidden');
    });
})();
</script>
@endpush

{{-- line-clamp-2 is a native Tailwind CSS 4 utility --}}
@endsection
