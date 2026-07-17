@extends('layouts.admin-club')

{{-- Styles moved to app.css (Phase 6) --}}

@section('club-admin-content')
<div class="space-y-6" x-data="{
        showAddInstructorModal: false, removeInstructorId: null, removeInstructorName: '', removeSettlement: null, showEditInstructorModal: false,
        openRemove(id, name) {
            this.removeInstructorId = id; this.removeInstructorName = name; this.removeSettlement = null;
            fetch(`{{ url('admin/club/' . $club->slug . '/instructors') }}/${id}/termination-preview`, { headers: { 'Accept': 'application/json' } })
                .then(r => r.json()).then(d => { if (d.success) this.removeSettlement = d; });
        }
     }"
     @instructor-removed.window="removeInstructorId = null; removeInstructorName = ''; removeSettlement = null">
    @if ($errors->any())
    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg" role="alert">
        <ul class="list-disc list-inside">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <!-- Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h2 class="text-xl font-bold text-gray-900">{{ __('admin.club_instructors_index_title') }}</h2>
            <p class="text-gray-500 mt-1">{{ __('admin.club_instructors_index_subtitle') }}</p>
        </div>
        <button class="btn btn-primary" @click="showAddInstructorModal = true">
            <i class="bi bi-plus-lg me-2"></i>{{ __('admin.club_instructors_index_add_instructor') }}
        </button>
    </div>

    @if(isset($instructors) && count($instructors) > 0)
    @if(count($instructors) > 1)
    <p class="text-sm text-gray-400 -mt-2 flex items-center gap-1"><i class="bi bi-grip-vertical"></i>{{ __('admin.club_instructors_index_drag_hint') }}</p>
    @endif
    <div id="instructor-sortable" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach($instructors as $instructor)
        @php $user = $instructor->user; @endphp
        @if(!$user) @continue @endif
        <div class="relative h-full" id="instructor-{{ $instructor->id }}" data-instructor-id="{{ $instructor->id }}" x-data="{ openMenu: false }">
        <div class="drag-handle absolute top-3 start-3 z-10 w-8 h-8 rounded-full bg-white/80 backdrop-blur-sm shadow-md border border-white/60 flex items-center justify-center text-gray-500 hover:text-gray-900 hover:bg-white cursor-grab active:cursor-grabbing transition-all" title="{{ __('admin.club_instructors_index_drag_to_rank') }}">
            <i class="bi bi-grip-vertical"></i>
        </div>
        <x-member-card
            class="h-full instructor-item"
            :member="$user"
            :href="route('trainer.show', $instructor->user_id)"
            variant="instructor"
            footerLabel="INSTRUCTOR"
            footerStyle="translucent"
            cardClass="instructor-card"
        >
            <x-slot:badges>
                @if(($instructor->staff_type ?? 'instructor') !== 'instructor')
                    <span class="badge bg-info">{{ __('admin.ins_staff_type_'.$instructor->staff_type) }}</span>
                @endif
                <span class="badge bg-primary">{{ $instructor->role ?? __('admin.club_instructors_index_trainer') }}</span>
                @if($instructor->user?->experience_years)
                    <span class="badge bg-info">{{ $instructor->user->experience_years }} {{ __('admin.club_instructors_index_yrs_exp') }}</span>
                @endif
                @if($instructor->compensation_type === 'paid' && $instructor->wage_amount)
                    @php $perLabels = ['monthly' => '/mo', 'session' => '/session', 'hourly' => '/hr']; @endphp
                    <span class="badge bg-success">{{ rtrim(rtrim(number_format((float) $instructor->wage_amount, 2), '0'), '.') }}{{ $perLabels[$instructor->wage_period] ?? '' }}</span>
                @else
                    <span class="badge bg-secondary">{{ __('admin.club_instructors_index_volunteer') }}</span>
                @endif
                @php $slotCount = $slotCountByInstructor[$instructor->id] ?? 0; @endphp
                @if($slotCount)
                    <span class="badge bg-accent text-primary"><i class="bi bi-calendar2-week me-1"></i>{{ $slotCount }}</span>
                @endif
            </x-slot:badges>
            <x-slot:headerExtra>
                <div class="flex items-center gap-1 mt-1">
                    <div class="star-rating flex">
                        @for($i = 1; $i <= 5; $i++)
                            <i class="bi {{ $i <= round($instructor->averageRating) ? 'bi-star-fill' : 'bi-star' }}" style="font-size: 14px; color: {{ $i <= round($instructor->averageRating) ? '#fbbf24' : '#d1d5db' }};"></i>
                        @endfor
                    </div>
                    <span class="text-xs text-muted-foreground">({{ $instructor->reviewsCount }})</span>
                </div>
            </x-slot:headerExtra>
            <x-slot:extraDetails>
                @if($instructor->user?->skills && count($instructor->user->skills) > 0)
                <div class="pt-2 border-t">
                    <div class="text-xs text-muted-foreground uppercase font-medium mb-2 tracking-wide">{{ __('admin.club_instructors_index_skills') }}</div>
                    <div class="flex flex-wrap gap-1">
                        @foreach(array_slice($instructor->user->skills, 0, 4) as $skill)
                            <span class="badge bg-secondary">{{ $skill }}</span>
                        @endforeach
                        @if(count($instructor->user->skills) > 4)
                            <span class="badge bg-secondary">+{{ count($instructor->user->skills) - 4 }}</span>
                        @endif
                    </div>
                </div>
                @endif
                @if($instructor->user?->bio)
                <div class="pt-2 mt-2 border-t">
                    <p class="text-sm text-muted-foreground line-clamp-2">{{ $instructor->user->bio }}</p>
                </div>
                @endif
            </x-slot:extraDetails>
        </x-member-card>

        {{-- Action dropdown overlay --}}
        <div class="absolute top-3 end-3 z-10" @click.stop>
            <button type="button"
                    @click="openMenu = !openMenu"
                    class="w-8 h-8 rounded-full bg-white/80 backdrop-blur-sm shadow-md border border-white/60 flex items-center justify-center text-gray-600 hover:bg-white hover:text-gray-900 hover:shadow-lg transition-all duration-200"
                    title="{{ __('admin.club_instructors_index_actions') }}">
                <i class="bi bi-three-dots-vertical text-sm"></i>
            </button>
            <div x-show="openMenu"
                 x-cloak
                 x-transition:enter="transition ease-out duration-150"
                 x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
                 x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-100"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-95"
                 @click.outside="openMenu = false"
                 class="absolute end-0 mt-2 w-48 bg-white rounded-xl shadow-xl border border-gray-100 overflow-hidden z-20">
                <div class="px-3 py-2 border-b border-gray-50">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">{{ __('admin.club_instructors_index_actions') }}</p>
                </div>
                <div class="py-1">
                    <button type="button"
                            class="w-full text-start px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 flex items-center gap-3 transition-colors duration-150"
                            @click="openMenu = false; showEditInstructorModal = true; $nextTick(() => openEditModal({{ $instructor->id }}))">
                        <span class="w-7 h-7 rounded-lg bg-blue-100 flex items-center justify-center shrink-0">
                            <i class="bi bi-pencil text-blue-600 text-xs"></i>
                        </span>
                        <span class="font-medium">{{ __('admin.club_instructors_index_edit_instructor') }}</span>
                    </button>
                    <button type="button"
                            class="w-full text-start px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 flex items-center gap-3 transition-colors duration-150"
                            @click="openMenu = false; window.dispatchEvent(new CustomEvent('open-manage-access', { detail: { userId: {{ $instructor->user_id }}, name: @js($user->full_name ?? $user->name) } }))">
                        <span class="w-7 h-7 rounded-lg bg-purple-100 flex items-center justify-center shrink-0">
                            <i class="bi bi-shield-lock text-purple-600 text-xs"></i>
                        </span>
                        <span class="font-medium">{{ __('admin.ins_manage_access') }}</span>
                    </button>
                    <button type="button"
                            class="w-full text-start px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 flex items-center gap-3 transition-colors duration-150"
                            @click="openMenu = false; openRemove({{ $instructor->id }}, @js($user->full_name ?? $user->name))">
                        <span class="w-7 h-7 rounded-lg bg-red-100 flex items-center justify-center shrink-0">
                            <i class="bi bi-person-dash text-red-600 text-xs"></i>
                        </span>
                        <span class="font-medium">{{ __('admin.club_instructors_index_remove_from_club') }}</span>
                    </button>
                </div>
            </div>
        </div>
        </div>
        @endforeach
    </div>
    @else
    <div class="tf-empty">
        <div class="tf-empty-icon">
            <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
        </div>
        <h5 class="text-lg font-semibold text-gray-900 mb-2">{{ __('admin.club_instructors_index_no_instructors') }}</h5>
        <p class="text-gray-500 mb-4">{{ __('admin.club_instructors_index_add_instructors_hint') }}</p>
        <button class="btn btn-primary" @click="showAddInstructorModal = true">
            <i class="bi bi-plus-lg me-2"></i>{{ __('admin.club_instructors_index_add_instructor') }}
        </button>
    </div>
    @endif

    {{-- Remove Instructor Confirm Modal --}}
    <div x-show="removeInstructorId !== null"
         x-cloak
         class="fixed inset-0 z-50 overflow-y-auto"
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
        <div class="fixed inset-0 bg-black/50" @click="removeInstructorId = null"></div>
        <div class="relative flex min-h-full items-center justify-center p-4 z-10">
            <div class="modal-content border-0 shadow-lg w-full max-w-sm relative rounded-lg overflow-hidden" @click.stop>
                <div class="modal-header border-b border-red-200 px-6 py-4">
                    <h5 class="modal-title text-destructive font-semibold">
                        <i class="bi bi-person-dash me-2"></i>{{ __('admin.club_instructors_index_remove_instructor_title') }}
                    </h5>
                    <button type="button" class="text-muted-foreground hover:text-foreground" @click="removeInstructorId = null">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <div class="modal-body px-6 py-4">
                    <p class="mb-1">{{ __('admin.club_instructors_index_confirm_remove') }}</p>
                    <p class="font-semibold" x-text="removeInstructorName"></p>
                    <p class="text-sm text-muted-foreground mt-1">{{ __('admin.club_instructors_index_from_this_club') }}</p>
                    <div class="alert alert-warning mt-3 text-sm space-y-1">
                        <div><i class="bi bi-info-circle me-1"></i>{{ __('admin.club_instructors_index_remove_note_pre') }}<strong>{{ __('admin.club_instructors_index_remove_note_role') }}</strong>{{ __('admin.club_instructors_index_remove_note_post') }}</div>
                        <div>{{ __('admin.club_instructors_index_remove_note2_pre') }}<strong>{{ __('admin.club_instructors_index_remove_note2_strong') }}</strong>{{ __('admin.club_instructors_index_remove_note2_post') }}</div>
                    </div>
                    <div x-show="removeSettlement && removeSettlement.settlement_amount > 0" x-cloak class="alert alert-danger mt-2 text-sm">
                        <i class="bi bi-cash-coin me-1"></i>{{ __('admin.ins_remove_settlement_note') }}
                        <span class="font-semibold" x-text="removeSettlement ? ('{{ $club->currency }} ' + Number(removeSettlement.settlement_amount).toFixed(2) + ' (' + removeSettlement.days + ' {{ __('admin.ins_days') }})') : ''"></span>
                    </div>
                </div>
                <div class="modal-footer border-t px-6 py-4 flex justify-end gap-3">
                    <button type="button" class="btn btn-secondary" @click="removeInstructorId = null">{{ __('shared.cancel') }}</button>
                    <button type="button" class="btn btn-danger" @click="removeInstructor(removeInstructorId)">
                        <i class="bi bi-person-dash me-1"></i>{{ __('admin.club_instructors_index_remove') }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    @include('admin.club.instructors.add')
    @include('admin.club.instructors.edit')
    @include('admin.club.instructors.partials.manage-access-modal')
</div>

@push('scripts')
<script>
window.instructorData = {
    @foreach($instructors ?? [] as $instructor)
    @if(!$instructor->user) @continue @endif
    {{ $instructor->id }}: {
        name: @json($instructor->user->full_name ?? $instructor->user->name ?? ''),
        role: @json($instructor->role ?? ''),
        staff_type: @json($instructor->staff_type ?? 'instructor'),
        translations: @json($instructor->translations ?? []),
        experience: @json($instructor->user->experience_years ?? null),
        skills: @json($instructor->user->skills ?? []),
        bio: @json($instructor->user->bio ?? ''),
        photo: @json($instructor->user->profile_picture ? '/storage/' . $instructor->user->profile_picture : ''),
        compensation_type: @json($instructor->compensation_type ?? 'volunteer'),
        wage_amount: @json($instructor->wage_amount !== null ? (float) $instructor->wage_amount : null),
        wage_period: @json($instructor->wage_period),
        slot_ids: @json(($packageSlots->where('instructor_id', $instructor->id)->pluck('id')->map(fn ($i) => (int) $i)->values()))
    },
    @endforeach
};

document.addEventListener('DOMContentLoaded', function() {
    loadNationalityFlags();
});

function removeInstructor(id) {
    if (!id) return;

    fetch(`{{ url('admin/club/' . $club->slug . '/instructors') }}/${id}`, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('instructor-' + id)?.remove();
            window.dispatchEvent(new CustomEvent('instructor-removed'));
            window.showToast('success', data.message || '{{ __("admin.club_instructors_index_toast_removed") }}');
        } else {
            window.showToast('error', data.message || '{{ __("admin.club_instructors_index_toast_remove_failed") }}');
        }
    })
    .catch(() => window.showToast('error', '{{ __("admin.club_instructors_index_toast_error") }}'));
}

function loadNationalityFlags() {
    fetch('/data/countries.json')
        .then(response => response.json())
        .then(countries => {
            document.querySelectorAll('.nationality-display').forEach(element => {
                const iso3Code = element.getAttribute('data-iso3');
                if (!iso3Code) return;

                const country = countries.find(c => c.iso2 === iso3Code || c.iso3 === iso3Code);
                if (country) {
                    const flagEmoji = country.iso2
                        .toUpperCase()
                        .split('')
                        .map(char => String.fromCodePoint(127397 + char.charCodeAt(0)))
                        .join('');

                    element.textContent = `${flagEmoji} ${country.name}`;
                }
            });
        })
        .catch(error => console.error('Error loading countries:', error));
}

// ---- Drag-to-rank ordering (top = highest rank) ----
(function () {
    var reorderUrl = '{{ route('admin.club.instructors.reorder', $club->slug) }}';

    function persist() {
        var ids = Array.prototype.map.call(
            document.querySelectorAll('#instructor-sortable [data-instructor-id]'),
            function (el) { return el.getAttribute('data-instructor-id'); }
        );
        fetch(reorderUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
            body: JSON.stringify({ order: ids })
        })
        .then(function (r) { return r.json(); })
        .then(function (d) { if (d && d.success) window.showToast && window.showToast('success', '{{ __("admin.club_instructors_index_toast_order_saved") }}'); })
        .catch(function () { window.showToast && window.showToast('error', '{{ __("admin.club_instructors_index_toast_order_failed") }}'); });
    }

    function init() {
        var list = document.getElementById('instructor-sortable');
        if (!list || list.dataset.sortableInit) return;
        list.dataset.sortableInit = '1';
        new Sortable(list, { handle: '.drag-handle', animation: 150, ghostClass: 'opacity-40', onEnd: persist });
    }

    if (window.Sortable) { init(); return; }
    var existing = document.getElementById('sortablejs-cdn');
    if (existing) { existing.addEventListener('load', init); return; }
    var s = document.createElement('script');
    s.id = 'sortablejs-cdn';
    s.src = 'https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js';
    s.onload = init;
    document.head.appendChild(s);
})();
</script>
@endpush
@endsection
