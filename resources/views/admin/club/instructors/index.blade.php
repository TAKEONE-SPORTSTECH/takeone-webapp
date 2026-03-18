@extends('layouts.admin-club')

{{-- Styles moved to app.css (Phase 6) --}}

@section('club-admin-content')
<div class="space-y-6" x-data="{ showAddInstructorModal: false, removeInstructorId: null, removeInstructorName: '', showEditInstructorModal: false }">
    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg relative" role="alert">
        {{ session('success') }}
        <button type="button" class="absolute top-3 right-3 text-green-500 hover:text-green-700" onclick="this.parentElement.remove()">
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>
        </button>
    </div>
    @endif

    @if(session('error'))
    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg relative" role="alert">
        {{ session('error') }}
        <button type="button" class="absolute top-3 right-3 text-red-500 hover:text-red-700" onclick="this.parentElement.remove()">
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>
        </button>
    </div>
    @endif

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
            <h2 class="text-3xl font-bold text-gray-900">Instructors</h2>
            <p class="text-gray-500 mt-1">Manage your club instructors and trainers</p>
        </div>
        <button class="inline-flex items-center px-4 py-2 bg-purple-500 text-white rounded-lg hover:bg-purple-600 transition-colors font-medium" @click="showAddInstructorModal = true">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
            Add Instructor
        </button>
    </div>

    @if(isset($instructors) && count($instructors) > 0)
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach($instructors as $instructor)
        @php $user = $instructor->user; @endphp
        @if(!$user) @continue @endif
        <div class="relative h-full" x-data="{ openMenu: false }">
        <x-member-card
            class="h-full instructor-item"
            :member="$user"
            :href="route('trainer.show', $instructor->user_id)"
            footerLabel="INSTRUCTOR"
            footerStyle="translucent"
            cardClass="instructor-card"
        >
            <x-slot:badges>
                <span class="badge bg-primary">{{ $instructor->role ?? 'Trainer' }}</span>
                @if($instructor->user?->experience_years)
                    <span class="badge bg-info">{{ $instructor->user->experience_years }} yrs exp</span>
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
                    <div class="text-xs text-muted-foreground uppercase font-medium mb-2 tracking-wide">Skills</div>
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
        <div class="absolute top-3 right-3 z-10" @click.stop>
            <button type="button"
                    @click="openMenu = !openMenu"
                    class="w-8 h-8 rounded-full bg-white/80 backdrop-blur-sm shadow-md border border-white/60 flex items-center justify-center text-gray-600 hover:bg-white hover:text-gray-900 hover:shadow-lg transition-all duration-200"
                    title="Actions">
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
                 class="absolute right-0 mt-2 w-48 bg-white rounded-xl shadow-xl border border-gray-100 overflow-hidden z-20">
                <div class="px-3 py-2 border-b border-gray-50">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Actions</p>
                </div>
                <div class="py-1">
                    <button type="button"
                            class="w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 flex items-center gap-3 transition-colors duration-150"
                            @click="openMenu = false; showEditInstructorModal = true; $nextTick(() => openEditModal({{ $instructor->id }}))">
                        <span class="w-7 h-7 rounded-lg bg-blue-100 flex items-center justify-center shrink-0">
                            <i class="bi bi-pencil text-blue-600 text-xs"></i>
                        </span>
                        <span class="font-medium">Edit Instructor</span>
                    </button>
                    <button type="button"
                            class="w-full text-left px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 flex items-center gap-3 transition-colors duration-150"
                            @click="openMenu = false; removeInstructorId = {{ $instructor->id }}; removeInstructorName = '{{ addslashes($user->full_name ?? $user->name) }}'">
                        <span class="w-7 h-7 rounded-lg bg-red-100 flex items-center justify-center shrink-0">
                            <i class="bi bi-person-dash text-red-600 text-xs"></i>
                        </span>
                        <span class="font-medium">Remove from Club</span>
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
        <h5 class="text-lg font-semibold text-gray-900 mb-2">No instructors yet</h5>
        <p class="text-gray-500 mb-4">Add instructors to your club</p>
        <button class="inline-flex items-center px-4 py-2 bg-purple-500 text-white rounded-lg hover:bg-purple-600 transition-colors font-medium" @click="showAddInstructorModal = true">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
            Add Instructor
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
                        <i class="bi bi-person-dash mr-2"></i>Remove Instructor
                    </h5>
                    <button type="button" class="text-muted-foreground hover:text-foreground" @click="removeInstructorId = null">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <div class="modal-body px-6 py-4">
                    <p class="mb-1">Are you sure you want to remove</p>
                    <p class="font-semibold" x-text="removeInstructorName"></p>
                    <p class="text-sm text-muted-foreground mt-1">from this club?</p>
                    <div class="alert alert-warning mt-3 text-sm space-y-1">
                        <div><i class="bi bi-info-circle mr-1"></i>This only removes their <strong>instructor role</strong> from this club.</div>
                        <div>Their platform account and any <strong>package subscriptions</strong> they hold in this club will remain unchanged.</div>
                    </div>
                </div>
                <div class="modal-footer border-t px-6 py-4 flex justify-end gap-3">
                    <button type="button" class="btn btn-secondary" @click="removeInstructorId = null">Cancel</button>
                    <button type="button" class="btn btn-danger" @click="removeInstructor(removeInstructorId)">
                        <i class="bi bi-person-dash mr-1"></i>Remove
                    </button>
                </div>
            </div>
        </div>
    </div>

    @include('admin.club.instructors.add')
    @include('admin.club.instructors.edit')
</div>

@push('scripts')
<script>
window.instructorData = {
    @foreach($instructors ?? [] as $instructor)
    @if(!$instructor->user) @continue @endif
    {{ $instructor->id }}: {
        name: @json($instructor->user->full_name ?? $instructor->user->name ?? ''),
        role: @json($instructor->role ?? ''),
        experience: @json($instructor->user->experience_years ?? null),
        skills: @json($instructor->user->skills ?? []),
        bio: @json($instructor->user->bio ?? ''),
        photo: @json($instructor->user->profile_picture ? '/storage/' . $instructor->user->profile_picture : '')
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
            location.reload();
        } else {
            alert(data.message || 'Failed to remove instructor.');
        }
    })
    .catch(() => alert('An error occurred. Please try again.'));
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
</script>
@endpush
@endsection
