@extends('layouts.admin-club')

@section('club-admin-content')
<div id="activitiesContainer" x-data="{
    showAddModal: false,
    showEditModal: false,
    editData: {},
    duplicateData: null,
    showChooser: false,
    showLibrary: false,
    library: [],
    libraryLoading: false,
    librarySearch: '',
    openLibrary() {
        this.showChooser = false;
        this.showLibrary = true;
        if (this.library.length === 0) {
            this.libraryLoading = true;
            fetch('{{ route('admin.club.activities.library', $club->slug) }}', { headers: { 'Accept': 'application/json' } })
                .then(r => r.json()).then(d => { this.library = d.activities || []; })
                .catch(() => { this.library = []; })
                .finally(() => { this.libraryLoading = false; });
        }
    },
    get filteredLibrary() {
        const q = this.librarySearch.trim().toLowerCase();
        return q ? this.library.filter(a => (a.name || '').toLowerCase().includes(q)) : this.library;
    },
    adding: false,
    showStyle: false,
    pendingActivity: null,
    // Choosing an existing activity adds it to THIS club immediately. If the
    // discipline has styles/federations (e.g. Taekwondo → WTF/ITF) we first ask
    // which one; otherwise it's added in a single tap. Creating a new one still
    // opens the full form.
    pickLibrary(a) {
        if ((a.variants || []).length > 0) {
            this.pendingActivity = a;
            this.showStyle = true;
            return;
        }
        this.addFromLibrary(a, null, null);
    },
    chooseStyle(v) {
        const a = this.pendingActivity;
        this.showStyle = false;
        this.pendingActivity = null;
        if (a) this.addFromLibrary(a, v ? (v.name || null) : null, v ? (v.name_ar || null) : null);
    },
    async addFromLibrary(a, style, styleAr) {
        if (this.adding) return;
        this.adding = true;
        const body = {
            name: a.name_en || a.name || '',
            description: a.description || '',
            translations: Object.assign({}, a.translations || {}),
        };
        if (style) {
            body.style = style;
            if (styleAr) body.translations.style = { ar: styleAr };
        }
        if (a.picture_src) body.existing_picture_url = a.picture_src;
        try {
            const res = await fetch('{{ route('admin.club.activities.store', $club->slug) }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                credentials: 'same-origin',
                body: JSON.stringify(body),
            });
            const d = await res.json().catch(() => ({}));
            if (!res.ok || d.success === false) throw new Error(d.message || 'Error');
            this.showLibrary = false;
            window.dispatchEvent(new CustomEvent('activity-saved', { detail: { activity: d.activity, mode: 'create' } }));
            window.showToast && window.showToast('success', d.message || 'Activity added.');
        } catch (e) {
            window.showToast && window.showToast('error', e.message);
        } finally {
            this.adding = false;
        }
    }
}">
    <div class="flex justify-between items-center mb-4">
        <div>
            <h2 class="tf-section-title">{{ __('admin.club_activities_index_title') }}</h2>
            <p class="text-sm text-gray-500 mt-1">{{ __('admin.club_activities_index_subtitle') }}</p>
        </div>
        <button class="btn btn-primary" @click="showChooser = true">
            <i class="bi bi-plus-lg me-2"></i>{{ __('admin.club_activities_index_add_activity') }}
        </button>
    </div>

    <div id="activitiesGrid" class="grid gap-4 {{ (isset($activities) && count($activities) > 0) ? '' : 'hidden' }}" style="grid-template-columns: repeat(auto-fill, 320px); grid-auto-rows: 1fr; justify-content: start;">
        @if(isset($activities) && count($activities) > 0)
        @foreach($activities as $activity)
        <div id="activity-{{ $activity->id }}" data-activity-id="{{ $activity->id }}" class="card border-0 shadow-sm h-full activity-card flex flex-col">
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

            {{-- Card Header --}}
            <div class="card-header bg-white border-0 pb-0">
                <h5 class="card-title font-semibold mb-0">
                    {{ $activity->name }}@if($activity->style)<span class="badge bg-accent text-primary align-middle ms-1" style="font-size:.65rem;">{{ $activity->style }}</span>@endif
                </h5>
            </div>

            {{-- Card Body (grows so the footer pins to the bottom) --}}
            <div class="card-body pt-2 flex-1">
                @if($activity->description)
                <p class="text-muted-foreground text-sm mb-2 line-clamp-2">
                    {{ strip_tags($activity->description) }}
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
                        <i class="bi bi-geo-alt me-1"></i>{{ $activity->facility->name }}
                    </span>
                    @endif
                </div>
            </div>

            {{-- Card Footer — equal-width actions pinned to the bottom --}}
            <div class="px-3 pb-3 pt-0 mt-auto">
                <div class="grid grid-cols-4 gap-2">
                    <button class="btn btn-sm btn-outline-secondary w-full" title="{{ __('admin.club_activities_index_duplicate_activity') }}"
                            @click="duplicateData = {
                                name: '{{ addslashes($activity->name) }}',
                                description: '{{ addslashes($activity->description) }}',
                                notes: '{{ addslashes($activity->notes) }}',
                                pictureUrl: '{{ $activity->picture_url ? asset('storage/' . $activity->picture_url) : '' }}'
                            }; showAddModal = true">
                        <i class="bi bi-copy"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-secondary w-full" title="{{ __('admin.club_activities_index_equipment') }}"
                            @click="window.dispatchEvent(new CustomEvent('open-equipment-manager', { detail: { id: {{ $activity->id }}, name: '{{ addslashes($activity->name) }}' } }))">
                        <i class="bi bi-box-seam"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-primary w-full" title="{{ __('admin.club_activities_index_edit_activity') }}"
                            @click="editData = {
                                id: {{ $activity->id }},
                                name: '{{ addslashes($activity->name) }}',
                                style: '{{ addslashes($activity->style) }}',
                                description: '{{ addslashes($activity->description) }}',
                                notes: '{{ addslashes($activity->notes) }}',
                                translations: {{ Illuminate\Support\Js::from($activity->translations ?? []) }},
                                pictureUrl: '{{ $activity->picture_url ? asset('storage/' . $activity->picture_url) : '' }}',
                                action: '{{ route('admin.club.activities.update', [$club->slug, $activity->id]) }}'
                            }; showEditModal = true">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <form action="{{ route('admin.club.activities.destroy', [$club->slug, $activity->id]) }}" method="POST" class="inline w-full m-0"
                          onsubmit="event.preventDefault(); const f = this; confirmAction({ title: '{{ __('admin.club_activities_index_delete_activity') }}', message: '{{ __('admin.club_activities_index_delete_confirm') }}', confirmText: '{{ __('shared.delete') }}', type: 'danger' }).then(ok => { if (ok) f.submit(); });">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-outline-danger w-full" title="{{ __('admin.club_activities_index_delete_activity') }}">
                            <i class="bi bi-trash"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
        @endforeach
        @endif
    </div>

    <div id="activitiesEmptyState" class="card border-0 shadow-sm {{ (isset($activities) && count($activities) > 0) ? 'hidden' : '' }}">
        <div class="card-body text-center py-12">
            <i class="bi bi-activity text-muted-foreground text-6xl"></i>
            <h5 class="mt-3 mb-2">{{ __('admin.club_activities_index_empty_title') }}</h5>
            <p class="text-muted-foreground mb-3">{{ __('admin.club_activities_index_empty_subtitle') }}</p>
            <button class="btn btn-primary" @click="showChooser = true">
                <i class="bi bi-plus-lg me-2"></i>{{ __('admin.club_activities_index_add_activity') }}
            </button>
        </div>
    </div>

    <x-activity-modal :club="$club" mode="create" />
    <x-activity-modal :club="$club" mode="edit" />

    {{-- Chooser: existing vs create --}}
    <div x-show="showChooser" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/50" @click="showChooser=false"></div>
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md p-6" @click.stop>
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-bold text-foreground">{{ __('admin.club_activities_index_add_activity') }}</h3>
                <button @click="showChooser=false" class="text-muted-foreground hover:text-foreground"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <button type="button" @click="openLibrary()" class="group flex flex-col items-center gap-2 p-5 rounded-xl border-2 border-dashed border-primary/30 hover:border-primary hover:bg-accent transition-all">
                    <span class="w-12 h-12 rounded-full bg-accent text-primary flex items-center justify-center group-hover:scale-110 transition-transform"><i class="bi bi-collection text-2xl"></i></span>
                    <span class="font-semibold text-sm text-foreground">Choose from existing</span>
                    <span class="text-[11px] text-muted-foreground text-center">A common activity used across clubs</span>
                </button>
                <button type="button" @click="showChooser=false; duplicateData=null; showAddModal=true" class="group flex flex-col items-center gap-2 p-5 rounded-xl border-2 border-dashed border-gray-200 hover:border-primary hover:bg-accent transition-all">
                    <span class="w-12 h-12 rounded-full bg-gray-100 text-gray-500 group-hover:bg-accent group-hover:text-primary flex items-center justify-center group-hover:scale-110 transition-transform"><i class="bi bi-plus-lg text-2xl"></i></span>
                    <span class="font-semibold text-sm text-foreground">Create new</span>
                    <span class="text-[11px] text-muted-foreground text-center">Start from a blank form</span>
                </button>
            </div>
        </div>
    </div>

    {{-- Library picker: distinct activities from all clubs --}}
    <div x-show="showLibrary" x-cloak class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/50" @click="showLibrary=false"></div>
        <div class="relative bg-white rounded-t-3xl sm:rounded-2xl shadow-xl w-full max-w-lg max-h-[85vh] flex flex-col" @click.stop>
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 flex-shrink-0">
                <div>
                    <h3 class="text-lg font-bold text-foreground">Choose an activity</h3>
                    <p class="text-xs text-muted-foreground">Tap an activity to add it to your club instantly</p>
                </div>
                <button @click="showLibrary=false" class="text-muted-foreground hover:text-foreground"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="px-5 py-3 border-b border-gray-100 flex-shrink-0">
                <div class="relative">
                    <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                    <input type="text" x-model="librarySearch" placeholder="Search activities…" class="w-full pl-10 pr-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                </div>
            </div>
            <div class="flex-1 overflow-y-auto px-3 py-3">
                <div x-show="libraryLoading" class="text-center py-10 text-muted-foreground"><i class="bi bi-arrow-repeat animate-spin text-2xl"></i></div>
                <template x-if="!libraryLoading && filteredLibrary.length === 0">
                    <div class="text-center py-10 text-muted-foreground text-sm">No activities found.</div>
                </template>
                <template x-for="(a, i) in filteredLibrary" :key="i">
                    <button type="button" @click="pickLibrary(a)" class="w-full text-left p-3 rounded-xl hover:bg-muted/60 transition-colors flex items-start gap-3">
                        <template x-if="a.picture_src">
                            <span class="w-9 h-9 rounded-lg overflow-hidden flex-shrink-0 bg-muted"><img :src="a.picture_src" alt="" class="w-full h-full object-cover"></span>
                        </template>
                        <template x-if="!a.picture_src">
                            <span class="w-9 h-9 rounded-lg bg-accent text-primary flex items-center justify-center flex-shrink-0"><i class="bi" :class="a.icon || 'bi-activity'"></i></span>
                        </template>
                        <span class="min-w-0 flex-1">
                            <span class="block font-semibold text-sm text-foreground" x-text="a.name"></span>
                            <span class="block text-xs text-muted-foreground line-clamp-1" x-text="((a.description_local || a.description) || '').replace(/<[^>]*>/g,' ').replace(/\s+/g,' ').trim()"></span>
                        </span>
                        <i class="bi bi-plus-circle-fill text-primary text-lg self-center flex-shrink-0" x-show="!adding"></i>
                        <i class="bi bi-arrow-repeat animate-spin text-muted-foreground self-center flex-shrink-0" x-show="adding" style="display:none;"></i>
                    </button>
                </template>
            </div>
        </div>
    </div>

    {{-- Style / federation chooser (shown only when the picked discipline has styles) --}}
    <div x-show="showStyle" x-cloak class="fixed inset-0 z-[55] flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/50" @click="showStyle=false; pendingActivity=null"></div>
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md p-6" @click.stop>
            <div class="flex items-center justify-between mb-1">
                <h3 class="text-lg font-bold text-foreground">Choose a style</h3>
                <button @click="showStyle=false; pendingActivity=null" class="text-muted-foreground hover:text-foreground"><i class="bi bi-x-lg"></i></button>
            </div>
            <p class="text-xs text-muted-foreground mb-4">
                <span x-text="pendingActivity ? pendingActivity.name : ''"></span> has more than one style/federation. Pick the one you teach.
            </p>
            <div class="grid grid-cols-1 gap-2">
                <template x-for="(v, i) in (pendingActivity ? pendingActivity.variants : [])" :key="i">
                    <button type="button" @click="chooseStyle(v)" :disabled="adding"
                            class="w-full text-left px-4 py-3 rounded-xl border border-border hover:border-primary hover:bg-accent transition-all flex items-center gap-3">
                        <span class="w-8 h-8 rounded-lg bg-accent text-primary grid place-items-center flex-shrink-0"><i class="bi bi-award"></i></span>
                        <span class="font-medium text-sm text-foreground" x-text="v.name"></span>
                    </button>
                </template>
                <button type="button" @click="chooseStyle(null)" :disabled="adding"
                        class="w-full text-left px-4 py-3 rounded-xl border border-dashed border-gray-200 hover:border-primary hover:bg-accent transition-all flex items-center gap-3">
                    <span class="w-8 h-8 rounded-lg bg-gray-100 text-gray-500 grid place-items-center flex-shrink-0"><i class="bi bi-dash-lg"></i></span>
                    <span class="font-medium text-sm text-muted-foreground">Generic — no specific style</span>
                </button>
            </div>
        </div>
    </div>

    <x-activity-equipment-modal :club="$club" />

    {{-- Hidden template for building a new activity card client-side (mirrors the loop markup above exactly). --}}
    <template id="activityCardTemplate">
        <div class="card border-0 shadow-sm h-full activity-card flex flex-col">
            <div data-img-slot class="h-44 bg-primary/10 flex items-center justify-center overflow-hidden">
                <i class="bi bi-activity text-primary text-5xl"></i>
            </div>

            <div class="card-header bg-white border-0 pb-0">
                <h5 class="card-title font-semibold mb-0"><span data-name></span><span data-style class="badge bg-accent text-primary align-middle ms-1 hidden" style="font-size:.65rem;"></span></h5>
            </div>

            <div class="card-body pt-2 flex-1">
                <p data-description class="text-muted-foreground text-sm mb-2 line-clamp-2 hidden"></p>
                <div data-notes-wrap class="bg-muted/30 rounded-lg p-2 mb-2 hidden">
                    <small data-notes class="text-muted-foreground"></small>
                </div>
                <div class="flex flex-wrap gap-2 mt-3">
                    <span data-facility-wrap class="badge bg-muted/30 text-foreground border border-border hidden">
                        <i class="bi bi-geo-alt me-1"></i><span data-facility></span>
                    </span>
                </div>
            </div>

            {{-- Card Footer — equal-width actions pinned to the bottom --}}
            <div class="px-3 pb-3 pt-0 mt-auto">
                <div class="grid grid-cols-4 gap-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary w-full" data-act="duplicate" title="{{ __('admin.club_activities_index_duplicate_activity') }}">
                        <i class="bi bi-copy"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary w-full" data-act="equipment" title="{{ __('admin.club_activities_index_equipment') }}">
                        <i class="bi bi-box-seam"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-primary w-full" data-act="edit" title="{{ __('admin.club_activities_index_edit_activity') }}">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <form data-act="destroy" method="POST" class="inline w-full m-0"
                          onsubmit="event.preventDefault(); const f = this; confirmAction({ title: '{{ __('admin.club_activities_index_delete_activity') }}', message: '{{ __('admin.club_activities_index_delete_confirm') }}', confirmText: '{{ __('shared.delete') }}', type: 'danger' }).then(ok => { if (ok) f.submit(); });">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-outline-danger w-full" title="{{ __('admin.club_activities_index_delete_activity') }}">
                            <i class="bi bi-trash"></i>
                        </button>
                    </form>
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

        // Style / federation badge
        const styleEl = card.querySelector('[data-style]');
        if (styleEl) {
            if (a.style) { styleEl.textContent = a.style; styleEl.classList.remove('hidden'); }
            else { styleEl.textContent = ''; styleEl.classList.add('hidden'); }
        }

        // Description
        const desc = card.querySelector('[data-description]');
        if (a.description) { desc.textContent = (a.description || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim(); desc.classList.remove('hidden'); }
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
                style: a.style || '',
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
