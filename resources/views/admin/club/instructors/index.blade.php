@extends('layouts.admin-club')

@php
if (!function_exists('getHoroscope')) {
    function getHoroscope($dateOfBirth) {
        $date = \Carbon\Carbon::parse($dateOfBirth);
        $day = $date->day;
        $month = $date->month;
        if (($month === 3 && $day >= 21) || ($month === 4 && $day <= 19)) return "Aries";
        if (($month === 4 && $day >= 20) || ($month === 5 && $day <= 20)) return "Taurus";
        if (($month === 5 && $day >= 21) || ($month === 6 && $day <= 20)) return "Gemini";
        if (($month === 6 && $day >= 21) || ($month === 7 && $day <= 22)) return "Cancer";
        if (($month === 7 && $day >= 23) || ($month === 8 && $day <= 22)) return "Leo";
        if (($month === 8 && $day >= 23) || ($month === 9 && $day <= 22)) return "Virgo";
        if (($month === 9 && $day >= 23) || ($month === 10 && $day <= 22)) return "Libra";
        if (($month === 10 && $day >= 23) || ($month === 11 && $day <= 21)) return "Scorpio";
        if (($month === 11 && $day >= 22) || ($month === 12 && $day <= 21)) return "Sagittarius";
        if (($month === 12 && $day >= 22) || ($month === 1 && $day <= 19)) return "Capricorn";
        if (($month === 1 && $day >= 20) || ($month === 2 && $day <= 18)) return "Aquarius";
        return "Pisces";
    }
}
@endphp

@push('styles')
<style>
    .instructor-card {
        transition: all 0.3s ease-in-out;
        cursor: pointer;
    }
    .instructor-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 12px 24px rgba(0, 0, 0, 0.15) !important;
    }
    .instructor-card:hover .profile-circle {
        transform: scale(1.05);
        transition: transform 0.3s ease-in-out;
    }
    .star-rating {
        color: #fbbf24;
    }
    .star-rating .empty {
        color: #d1d5db;
    }
</style>
@endpush

@section('club-admin-content')
<div class="space-y-6">
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
        <button class="inline-flex items-center px-4 py-2 bg-purple-500 text-white rounded-lg hover:bg-purple-600 transition-colors font-medium" data-bs-toggle="modal" data-bs-target="#addInstructorModal">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
            Add Instructor
        </button>
    </div>

    @if(isset($instructors) && count($instructors) > 0)
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach($instructors as $instructor)
        @php
            $user = $instructor->user;
            $gender = $user?->gender ?? 'm';
            $isMale = $gender === 'm' || $gender === 'male';
            $dob = $user?->birthdate ?? null;
            $age = $dob ? \Carbon\Carbon::parse($dob)->age : null;
            $nationality = $user?->nationality ?? null;
            $avgRating = $instructor->averageRating;
            $reviewsCount = $instructor->reviewsCount;

            // Horoscope symbols
            $horoscopeSymbols = [
                'Aries' => '♈', 'Taurus' => '♉', 'Gemini' => '♊', 'Cancer' => '♋',
                'Leo' => '♌', 'Virgo' => '♍', 'Libra' => '♎', 'Scorpio' => '♏',
                'Sagittarius' => '♐', 'Capricorn' => '♑', 'Aquarius' => '♒', 'Pisces' => '♓'
            ];
            $horoscope = $dob ? getHoroscope($dob) : 'N/A';
            $horoscopeSymbol = $horoscopeSymbols[$horoscope] ?? '';
        @endphp
        <div class="instructor-item">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 instructor-card h-full overflow-hidden flex flex-col">
                <!-- Header with gradient background -->
                <div class="p-4 pb-3" style="background: linear-gradient(135deg, {{ $isMale ? 'rgba(147, 51, 234, 0.1) 0%, rgba(147, 51, 234, 0.05) 50%' : 'rgba(214, 51, 132, 0.1) 0%, rgba(214, 51, 132, 0.05) 50%' }}, transparent 100%);">
                    <div class="flex items-start gap-3">
                        <div class="relative flex-shrink-0">
                            <div class="profile-circle rounded-full border-4 border-white shadow-md overflow-hidden" style="width: 80px; height: 80px; box-shadow: 0 0 0 2px {{ $isMale ? 'rgba(147, 51, 234, 0.3)' : 'rgba(214, 51, 132, 0.3)' }} !important;">
                                @if($user?->profile_picture)
                                <img src="{{ asset('storage/' . $user->profile_picture) }}"
                                     alt="{{ $user->full_name }}"
                                     class="w-full h-full object-cover">
                                @else
                                <div class="w-full h-full flex items-center justify-center text-white font-bold text-2xl" style="background: linear-gradient(135deg, {{ $isMale ? '#8b5cf6 0%, #7c3aed 100%' : '#d63384 0%, #a61e4d 100%' }});">
                                    {{ strtoupper(substr($user?->full_name ?? 'I', 0, 1)) }}
                                </div>
                                @endif
                            </div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h5 class="font-bold text-lg text-gray-900 truncate mb-1">{{ $user?->full_name ?? 'Unknown' }}</h5>
                            <div class="flex flex-wrap gap-2 mb-2">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-purple-500 text-white">{{ $instructor->role ?? 'Trainer' }}</span>
                                @if($instructor->experience_years)
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-700">{{ $instructor->experience_years }} yrs exp</span>
                                @endif
                            </div>
                            <!-- Rating -->
                            <div class="flex items-center gap-1">
                                <div class="star-rating flex">
                                    @for($i = 1; $i <= 5; $i++)
                                        @if($i <= round($avgRating))
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path></svg>
                                        @else
                                        <svg class="w-4 h-4 empty" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path></svg>
                                        @endif
                                    @endfor
                                </div>
                                <span class="text-xs text-gray-500">({{ $reviewsCount }})</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Contact Info -->
                <div class="px-4 py-3 bg-gray-50 border-y border-gray-200">
                    @if($user?->mobile && isset($user->mobile['number']))
                    <div class="flex items-center gap-2 text-sm mb-2">
                        <svg class="w-4 h-4 {{ $isMale ? 'text-purple-500' : 'text-pink-500' }}" fill="currentColor" viewBox="0 0 20 20"><path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z"></path></svg>
                        <span class="font-medium text-gray-600">{{ $user->mobile['code'] ?? '' }} {{ $user->mobile['number'] }}</span>
                    </div>
                    @elseif($user?->mobile)
                    <div class="flex items-center gap-2 text-sm mb-2">
                        <svg class="w-4 h-4 {{ $isMale ? 'text-purple-500' : 'text-pink-500' }}" fill="currentColor" viewBox="0 0 20 20"><path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z"></path></svg>
                        <span class="font-medium text-gray-600">{{ $user->mobile }}</span>
                    </div>
                    @endif
                    @if($user?->email)
                    <div class="flex items-center gap-2 text-sm">
                        <svg class="w-4 h-4 {{ $isMale ? 'text-purple-500' : 'text-pink-500' }}" fill="currentColor" viewBox="0 0 20 20"><path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"></path><path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"></path></svg>
                        <span class="font-medium text-gray-600 truncate">{{ $user->email }}</span>
                    </div>
                    @endif
                    @if(!$user?->mobile && !$user?->email)
                    <div class="flex items-center gap-2 text-sm text-gray-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <span class="font-medium">No contact info</span>
                    </div>
                    @endif
                </div>

                <!-- Instructor Details -->
                <div class="px-4 py-3 flex-grow">
                    <div class="grid grid-cols-2 gap-3 mb-3">
                        <div>
                            <div class="text-[10px] uppercase tracking-wide text-gray-400 font-semibold mb-1">Gender</div>
                            <div class="font-semibold text-gray-600 flex items-center gap-1">
                                @if($isMale)
                                <svg class="w-4 h-4 text-purple-500" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z"/></svg>
                                Male
                                @else
                                <svg class="w-4 h-4 text-pink-500" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z"/></svg>
                                Female
                                @endif
                            </div>
                        </div>
                        <div>
                            <div class="text-[10px] uppercase tracking-wide text-gray-400 font-semibold mb-1">Age</div>
                            <div class="font-semibold text-gray-600">{{ $age ? $age . ' years' : 'N/A' }}</div>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3 mb-3">
                        <div>
                            <div class="text-[10px] uppercase tracking-wide text-gray-400 font-semibold mb-1">Nationality</div>
                            <div class="font-semibold text-gray-600 text-lg nationality-display" data-iso3="{{ $nationality }}">{{ $nationality ?? 'N/A' }}</div>
                        </div>
                        <div>
                            <div class="text-[10px] uppercase tracking-wide text-gray-400 font-semibold mb-1">Horoscope</div>
                            <div class="font-semibold text-gray-600">{{ $horoscopeSymbol }} {{ $horoscope }}</div>
                        </div>
                    </div>

                    <!-- Skills -->
                    @if($instructor->skills && count($instructor->skills) > 0)
                    <div class="pt-2 border-t border-gray-100">
                        <div class="text-[10px] uppercase tracking-wide text-gray-400 font-semibold mb-2">Skills</div>
                        <div class="flex flex-wrap gap-1">
                            @foreach(array_slice($instructor->skills, 0, 4) as $skill)
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700">{{ $skill }}</span>
                            @endforeach
                            @if(count($instructor->skills) > 4)
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-200 text-gray-600">+{{ count($instructor->skills) - 4 }}</span>
                            @endif
                        </div>
                    </div>
                    @endif

                    <!-- Bio -->
                    @if($instructor->bio)
                    <div class="pt-2 mt-2 border-t border-gray-100">
                        <p class="text-sm text-gray-500 line-clamp-2">{{ $instructor->bio }}</p>
                    </div>
                    @endif
                </div>

                <!-- Action Buttons -->
                <div class="px-4 py-3 bg-gray-50 border-t border-gray-200">
                    <div class="flex gap-2">
                        <x-takeone-cropper
                            id="instructor_{{ $instructor->id }}"
                            :width="200"
                            :height="200"
                            shape="circle"
                            folder="clubs/{{ $club->id }}/instructors"
                            filename="instructor_{{ $instructor->id }}"
                            uploadUrl="{{ route('admin.club.instructors.upload-photo', [$club->id, $instructor->id]) }}"
                            :currentImage="$user?->profile_picture ? asset('storage/' . $user->profile_picture) : ''"
                            buttonText="Photo"
                            buttonClass="flex-1 inline-flex items-center justify-center px-3 py-2 bg-purple-500 text-white text-sm font-medium rounded-lg hover:bg-purple-600 transition-colors"
                        />
                        <button class="flex-1 inline-flex items-center justify-center px-3 py-2 border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors">
                            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                            Edit
                        </button>
                        <button class="inline-flex items-center justify-center px-3 py-2 bg-red-500 text-white text-sm font-medium rounded-lg hover:bg-red-600 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                        </button>
                    </div>
                </div>

                <!-- Footer -->
                <div class="px-4 py-2 {{ $isMale ? 'bg-purple-500/10' : 'bg-pink-500/10' }} border-t {{ $isMale ? 'border-purple-200' : 'border-pink-200' }}">
                    <div class="flex items-center justify-center gap-2 text-sm">
                        <span class="font-medium {{ $isMale ? 'text-purple-700' : 'text-pink-700' }}">
                            INSTRUCTOR
                        </span>
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>
    @else
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center">
        <div class="w-20 h-20 mx-auto mb-4 rounded-full bg-gray-100 flex items-center justify-center">
            <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
        </div>
        <h5 class="text-lg font-semibold text-gray-900 mb-2">No instructors yet</h5>
        <p class="text-gray-500 mb-4">Add instructors to your club</p>
        <button class="inline-flex items-center px-4 py-2 bg-purple-500 text-white rounded-lg hover:bg-purple-600 transition-colors font-medium" data-bs-toggle="modal" data-bs-target="#addInstructorModal">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
            Add Instructor
        </button>
    </div>
    @endif
</div>

@include('admin.club.instructors.add')

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

                    element.textContent = `${flagEmoji} ${country.iso2.toUpperCase()}`;
                }
            });
        })
        .catch(error => console.error('Error loading countries:', error));
}
</script>
@endpush
@endsection
