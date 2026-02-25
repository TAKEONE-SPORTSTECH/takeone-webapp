@extends('layouts.admin-club')

{{-- Styles moved to app.css (Phase 6) --}}

@section('club-admin-content')
<div class="space-y-6" x-data="{ showAddInstructorModal: false }">
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
        <x-member-card
            :member="$user"
            :href="route('trainer.show', $instructor->user_id)"
            footerLabel="INSTRUCTOR"
            footerStyle="translucent"
            cardClass="instructor-card"
            class="instructor-item"
        >
            <x-slot:badges>
                <span class="badge bg-primary">{{ $instructor->role ?? 'Trainer' }}</span>
                @if($user->experience_years)
                    <span class="badge bg-info">{{ $user->experience_years }} yrs exp</span>
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
                @if($user->skills && count($user->skills) > 0)
                <div class="pt-2 border-t">
                    <div class="text-xs text-muted-foreground uppercase font-medium mb-2 tracking-wide">Skills</div>
                    <div class="flex flex-wrap gap-1">
                        @foreach(array_slice($user->skills, 0, 4) as $skill)
                            <span class="badge bg-secondary">{{ $skill }}</span>
                        @endforeach
                        @if(count($user->skills) > 4)
                            <span class="badge bg-secondary">+{{ count($user->skills) - 4 }}</span>
                        @endif
                    </div>
                </div>
                @endif
                @if($user->bio)
                <div class="pt-2 mt-2 border-t">
                    <p class="text-sm text-muted-foreground line-clamp-2">{{ $user->bio }}</p>
                </div>
                @endif
            </x-slot:extraDetails>
        </x-member-card>
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

    @include('admin.club.instructors.add')
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    loadNationalityFlags();
});

function loadNationalityFlags() {
    fetch('/data/countries.json')
        .then(response => response.json())
        .then(countries => {
            document.querySelectorAll('.nationality-display').forEach(element => {
                const iso3Code = element.getAttribute('data-iso3');
                if (!iso3Code) return;

                const country = countries.find(c => c.iso3 === iso3Code);
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
