@extends('layouts.admin-club-mobile')

@section('title', ($club->club_name ?? __('admin.club')) . ' · ' . __('admin.nav_instructors'))

@section('club-admin-content')
@php
    $insCount = $instructors->count();
    $insRated = $instructors->filter(fn ($i) => (float) ($i->rating ?? 0) > 0);
    $insAvgRating = $insRated->count() ? round($insRated->avg('rating'), 1) : 0;
@endphp
<div class="-mx-4 -mt-4"
     x-data="{
        removeInstructorId: null, removeInstructorName: '', removeSettlement: null,
        openRemove(id, name) {
            this.removeInstructorId = id; this.removeInstructorName = name; this.removeSettlement = null;
            fetch(`{{ url('admin/club/' . $club->slug . '/instructors') }}/${id}/termination-preview`, { headers: { 'Accept': 'application/json' } })
                .then(r => r.json()).then(d => { if (d.success) this.removeSettlement = d; });
        }
     }"
     @instructor-removed.window="removeInstructorId = null; removeInstructorName = ''; removeSettlement = null">

    {{-- ===== Hero ===== --}}
    <header class="m-hero px-5 pt-7 pb-6 text-white relative overflow-hidden">
        <div class="absolute -end-8 -top-8 w-36 h-36 rounded-full bg-white/10"></div>
        <div class="flex items-center justify-between relative z-10">
            <div class="min-w-0">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-white/70 truncate">{{ $club->club_name ?? __('admin.club') }}</p>
                <h1 class="text-2xl font-black mt-0.5">{{ __('admin.nav_instructors') }}</h1>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" @click="$dispatch('open-add-instructor')"
                        class="m-press w-12 h-12 rounded-2xl bg-white/20 border border-white/30 backdrop-blur grid place-items-center active:scale-95 transition-transform" aria-label="{{ __('admin.ins_add') }}">
                    <i class="bi bi-plus-lg text-xl"></i>
                </button>
                <div class="w-12 h-12 rounded-2xl bg-white/15 border border-white/25 backdrop-blur grid place-items-center">
                    <i class="bi bi-person-badge text-xl m-float"></i>
                </div>
            </div>
        </div>

        <div class="flex gap-2 mt-5 relative z-10">
            <div class="flex-1 rounded-2xl bg-white/12 border border-white/20 backdrop-blur px-3 py-2.5">
                <p class="text-lg font-black leading-none tabular-nums">{{ $insCount }}</p>
                <p class="text-[10px] text-white/75 mt-1 uppercase tracking-wide">{{ __('admin.nav_instructors') }}</p>
            </div>
            <div class="flex-1 rounded-2xl bg-white/12 border border-white/20 backdrop-blur px-3 py-2.5">
                <p class="text-lg font-black leading-none tabular-nums">{{ $insAvgRating > 0 ? number_format($insAvgRating, 1) : '—' }}</p>
                <p class="text-[10px] text-white/75 mt-1 uppercase tracking-wide">{{ __('admin.dash_rating') }}</p>
            </div>
        </div>
    </header>

    <div class="px-4 pt-5 space-y-4 mobile-stagger">

    @if($instructors->isEmpty())
        <div class="m-card p-8 text-center">
            <i class="bi bi-people text-3xl text-gray-300 m-float"></i>
            <p class="text-sm text-muted-foreground mt-2">{{ __('admin.ins_none_yet') }}</p>
        </div>
    @else
        @if($instructors->count() > 1)
            <p class="text-[11px] text-muted-foreground px-1 mb-1 flex items-center gap-1"><i class="bi bi-grip-vertical"></i>{{ __('admin.ins_drag_hint') }}</p>
        @endif
        <div id="instructor-sortable" class="space-y-2.5">
        @foreach($instructors as $ins)
            @php $u = $ins->user; @endphp
            @if(!$u) @continue @endif
            @php $isOwner = $club->owner_user_id && (int) $ins->user_id === (int) $club->owner_user_id; @endphp
            <div class="m-card p-4 relative" id="instructor-{{ $ins->id }}" data-instructor-id="{{ $ins->id }}" x-data="{ openMenu: false }">

                {{-- Actions menu --}}
                <div class="absolute top-3 right-3 z-10" @click.stop>
                    <button type="button" @click="openMenu = !openMenu"
                            class="m-press w-8 h-8 rounded-full bg-muted flex items-center justify-center text-muted-foreground"
                            aria-label="{{ __('admin.actions') }}">
                        <i class="bi bi-three-dots-vertical"></i>
                    </button>
                    <div x-show="openMenu" x-cloak @click.outside="openMenu = false"
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
                         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                         x-transition:leave="transition ease-in duration-100"
                         x-transition:leave-start="opacity-100 scale-100"
                         x-transition:leave-end="opacity-0 scale-95"
                         class="absolute right-0 mt-2 w-44 bg-white rounded-xl shadow-xl border border-gray-100 overflow-hidden z-20">
                        <button type="button"
                                class="w-full text-left px-4 py-3 text-sm text-foreground hover:bg-muted/60 flex items-center gap-3"
                                @click="openMenu = false; $dispatch('open-edit-instructor', { id: {{ $ins->id }} })">
                            <span class="w-7 h-7 rounded-lg bg-blue-100 flex items-center justify-center shrink-0"><i class="bi bi-pencil text-blue-600 text-xs"></i></span>
                            <span class="font-medium">{{ __('admin.ins_edit') }}</span>
                        </button>
                        <button type="button"
                                class="w-full text-left px-4 py-3 text-sm text-foreground hover:bg-muted/60 flex items-center gap-3"
                                @click="openMenu = false; window.dispatchEvent(new CustomEvent('open-manage-access', { detail: { userId: {{ $ins->user_id }}, name: @js($u->full_name ?? $u->name) } }))">
                            <span class="w-7 h-7 rounded-lg bg-purple-100 flex items-center justify-center shrink-0"><i class="bi bi-shield-lock text-purple-600 text-xs"></i></span>
                            <span class="font-medium">{{ __('admin.ins_manage_access') }}</span>
                        </button>
                        <button type="button"
                                class="w-full text-left px-4 py-3 text-sm text-red-600 hover:bg-red-50 flex items-center gap-3"
                                @click="openMenu = false; openRemove({{ $ins->id }}, @js($u->full_name ?? $u->name))">
                            <span class="w-7 h-7 rounded-lg bg-red-100 flex items-center justify-center shrink-0"><i class="bi bi-person-dash text-red-600 text-xs"></i></span>
                            <span class="font-medium">{{ __('admin.ins_remove') }}</span>
                        </button>
                    </div>
                </div>

                <div class="flex items-center gap-3 pr-9">
                    <span class="drag-handle cursor-grab active:cursor-grabbing text-gray-300 hover:text-gray-400 flex-shrink-0 touch-none -ml-1" title="{{ __('admin.ins_drag_rank') }}">
                        <i class="bi bi-grip-vertical text-lg"></i>
                    </span>
                    <span class="w-12 h-12 rounded-full bg-muted flex items-center justify-center overflow-hidden flex-shrink-0 ring-2 {{ $isOwner ? 'ring-amber-300' : 'ring-transparent' }}">
                        @if($u->profile_picture)<img src="{{ asset('storage/'.$u->profile_picture) }}" alt="" class="w-12 h-12 object-cover">@else<i class="bi bi-person text-muted-foreground text-lg"></i>@endif
                    </span>
                    @php $expSuffix = $u->experience_years ? ' · '.$u->experience_years.' '.__('admin.yrs') : ''; @endphp
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold text-foreground truncate flex items-center gap-1.5">
                            <span class="truncate">{{ $u->full_name ?? __('admin.instructor') }}</span>
                            @if($isOwner)<i class="bi bi-crown-fill text-amber-400 text-sm flex-shrink-0" title="{{ __('admin.owner') }}"></i>@endif
                        </p>
                        <p class="text-xs text-muted-foreground truncate">{{ ($isOwner ? __('admin.owner') : ($ins->role ?? __('admin.instructor'))) . $expSuffix }}</p>
                        <div class="mt-1">
                            @if(($ins->staff_type ?? 'instructor') !== 'instructor')
                                @php $staffIcons = ['secretary' => 'bi-person-vcard', 'operator' => 'bi-gear', 'cleaner' => 'bi-stars', 'other' => 'bi-person-badge']; @endphp
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-blue-50 text-blue-700">
                                    <i class="bi {{ $staffIcons[$ins->staff_type] ?? 'bi-person-badge' }}"></i>{{ __('admin.ins_staff_type_'.$ins->staff_type) }}
                                </span>
                            @endif
                            @if($ins->compensation_type === 'paid' && $ins->wage_amount)
                                @php $perLabels = ['monthly' => __('admin.ins_per_month'), 'session' => __('admin.ins_per_session'), 'hourly' => __('admin.ins_per_hour')]; @endphp
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-green-50 text-green-700">
                                    <i class="bi bi-cash-coin"></i>{{ rtrim(rtrim(number_format((float) $ins->wage_amount, 2), '0'), '.') }} {{ $perLabels[$ins->wage_period] ?? '' }}
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-muted text-muted-foreground">
                                    <i class="bi bi-heart"></i>{{ __('admin.ins_volunteer') }}
                                </span>
                            @endif
                            @php $slotCount = $slotCountByInstructor[$ins->id] ?? 0; @endphp
                            @if($slotCount)
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-accent text-primary ml-1">
                                    <i class="bi bi-calendar2-week"></i>{{ trans_choice('admin.ins_teaches_n', $slotCount, ['count' => $slotCount]) }}
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
                @if(!empty($u->skills) && is_array($u->skills))
                    <div class="flex flex-wrap gap-1.5 mt-3">
                        @foreach(array_slice($u->skills, 0, 5) as $skill)
                            <span class="px-2 py-0.5 rounded-full text-[11px] font-medium bg-accent text-primary">{{ $skill }}</span>
                        @endforeach
                    </div>
                @endif
                @if($u->bio)<p class="text-xs text-muted-foreground mt-2 line-clamp-2">{{ $u->bio }}</p>@endif
            </div>
        @endforeach
        </div>
    @endif

    {{-- Remove confirm (teleported to body to escape the transformed `.mobile-stagger` container) --}}
    <template x-teleport="body">
    <div x-show="removeInstructorId !== null" x-cloak class="fixed inset-0 z-50 overflow-y-auto"
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
        <div class="fixed inset-0 bg-black/50" @click="removeInstructorId = null"></div>
        <div class="relative flex min-h-full items-center justify-center p-4 z-10">
            <div class="bg-white w-full max-w-sm relative rounded-2xl overflow-hidden shadow-xl" @click.stop>
                <div class="flex items-center justify-between border-b border-red-100 px-5 py-4">
                    <h5 class="text-destructive font-semibold flex items-center"><i class="bi bi-person-dash mr-2"></i>{{ __('admin.ins_remove') }}</h5>
                    <button type="button" class="text-muted-foreground hover:text-foreground" @click="removeInstructorId = null"><i class="bi bi-x-lg"></i></button>
                </div>
                <div class="px-5 py-4">
                    <p class="mb-1 text-sm">{{ __('admin.ins_remove_confirm') }}</p>
                    <p class="font-semibold" x-text="removeInstructorName"></p>
                    <div class="rounded-xl bg-amber-50 border border-amber-100 mt-3 p-3 text-xs text-amber-800 space-y-1">
                        <div><i class="bi bi-info-circle mr-1"></i>{{ __('admin.ins_remove_note_role') }}</div>
                        <div>{{ __('admin.ins_remove_note_account') }}</div>
                    </div>
                    <div x-show="removeSettlement && removeSettlement.settlement_amount > 0" x-cloak
                         class="rounded-xl bg-red-50 border border-red-100 mt-2 p-3 text-xs text-red-800">
                        <i class="bi bi-cash-coin mr-1"></i>{{ __('admin.ins_remove_settlement_note') }}
                        <span class="font-semibold" x-text="removeSettlement ? ('{{ $club->currency }} ' + Number(removeSettlement.settlement_amount).toFixed(2) + ' (' + removeSettlement.days + ' {{ __('admin.ins_days') }})') : ''"></span>
                    </div>
                </div>
                <div class="border-t px-5 py-4 flex justify-end gap-2">
                    <button type="button" class="px-4 py-2 text-sm font-medium rounded-xl border border-gray-200 bg-white" @click="removeInstructorId = null">{{ __('admin.cancel') }}</button>
                    <button type="button" class="px-4 py-2 text-sm font-medium rounded-xl bg-destructive text-white flex items-center gap-1" @click="removeInstructor(removeInstructorId)">
                        <i class="bi bi-person-dash"></i>{{ __('admin.ins_remove') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
    </template>

    @include('admin.club.instructors.mobile-add')
    @include('admin.club.instructors.mobile-edit')
    @include('admin.club.instructors.partials.manage-access-modal')

    {{-- Inline (inside #shell-content) so it also runs after an in-shell AJAX swap,
         which only re-executes scripts within the swapped content. --}}
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

@if($errors->any())
// A failed add/edit submit returns a full-page reload; surface the reason as a toast.
document.addEventListener('DOMContentLoaded', function () {
    window.showToast && window.showToast('error', @json($errors->first()));
});
@endif

function removeInstructor(id) {
    if (!id) return;
    fetch(`{{ url('admin/club/' . $club->slug . '/instructors') }}/${id}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('instructor-' + id)?.remove();
            window.dispatchEvent(new CustomEvent('instructor-removed'));
            window.showToast('success', data.message || 'Instructor removed.');
        } else {
            window.showToast('error', data.message || 'Failed to remove instructor.');
        }
    })
    .catch(() => window.showToast('error', 'An error occurred. Please try again.'));
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
        .then(function (d) { if (d && d.success) window.showToast && window.showToast('success', '{{ __('admin.ins_rank_saved') }}'); })
        .catch(function () { window.showToast && window.showToast('error', 'Could not save the new order.'); });
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
    </div>{{-- /content --}}
</div>
@endsection
