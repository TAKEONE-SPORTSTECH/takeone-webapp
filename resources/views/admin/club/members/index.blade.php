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
    .member-card {
        transition: all 0.3s ease-in-out;
        cursor: pointer;
    }
    .member-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 12px 24px rgba(0, 0, 0, 0.15) !important;
    }
    .member-card:hover .rounded-full {
        transform: scale(1.05);
        transition: transform 0.3s ease-in-out;
    }
    .status-btn.active {
        @apply bg-purple-500 text-white border-purple-500;
    }
    .search-result-card.selected,
    .package-card.selected {
        @apply border-purple-500 bg-purple-50;
    }
    .check-circle.checked {
        @apply bg-purple-500 border-purple-500 scale-110;
    }
    .member-item {
        transition: opacity 0.3s ease;
    }
    .member-item[style*="display: none"] {
        opacity: 0;
    }
</style>
@endpush

@section('club-admin-content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h2 class="text-3xl font-bold text-gray-900">Members Management</h2>
            <p class="text-gray-500 mt-1">Manage club members and subscriptions</p>
        </div>
        <div class="flex gap-3 flex-wrap">
            <button onclick="openAddExistingUserModal()" class="inline-flex items-center px-4 py-2 border border-purple-500 text-purple-600 rounded-lg hover:bg-purple-50 transition-colors font-medium">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
                Add Existing User
            </button>
            <button onclick="openWalkInModal()" class="inline-flex items-center px-4 py-2 bg-purple-500 text-white rounded-lg hover:bg-purple-600 transition-colors font-medium">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                Walk-In Registration
            </button>
        </div>
    </div>

    <!-- Tabs -->
    <div class="border-b border-gray-200">
        <nav class="-mb-px flex gap-8">
            <button id="members-tab-btn" onclick="switchTab('members')" class="py-4 px-1 border-b-2 border-purple-500 text-purple-600 font-medium text-sm whitespace-nowrap">
                Current Members
                <span class="ml-2 py-0.5 px-2.5 rounded-full text-xs font-medium bg-purple-100 text-purple-600" id="membersCount">{{ $members->total() ?? 0 }}</span>
            </button>
            <button id="requests-tab-btn" onclick="switchTab('requests')" class="py-4 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 font-medium text-sm whitespace-nowrap">
                Pending Requests
                <span class="ml-2 py-0.5 px-2.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-700" id="requestsCount">{{ $pendingRequests ?? 0 }}</span>
            </button>
        </nav>
    </div>

    <!-- Members Tab Content -->
    <div id="members-content">
        <!-- Search & Filter -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-6">
            <div class="flex flex-col lg:flex-row gap-4">
                <div class="flex-1">
                    <div class="relative">
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        <input type="text" id="searchMembers" placeholder="Search members by name or rank..." class="w-full pl-10 pr-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    </div>
                </div>
                <div class="flex flex-col sm:flex-row gap-2">
                    <span class="text-sm font-medium text-gray-600 self-center">Status:</span>
                    <div class="inline-flex rounded-lg border border-gray-200 p-1 bg-gray-50">
                        <button type="button" class="status-btn active px-3 py-1.5 text-sm font-medium rounded-md transition-colors" data-status="active">
                            Active <span class="ml-1 text-xs opacity-75" id="activeCount">0</span>
                        </button>
                        <button type="button" class="status-btn px-3 py-1.5 text-sm font-medium rounded-md text-gray-600 hover:bg-gray-100 transition-colors" data-status="not_active">
                            Not Active <span class="ml-1 text-xs opacity-75" id="notActiveCount">0</span>
                        </button>
                        <button type="button" class="status-btn px-3 py-1.5 text-sm font-medium rounded-md text-gray-600 hover:bg-gray-100 transition-colors" data-status="all">
                            All <span class="ml-1 text-xs opacity-75" id="allCount">0</span>
                        </button>
                        <button type="button" class="status-btn px-3 py-1.5 text-sm font-medium rounded-md text-gray-600 hover:bg-gray-100 transition-colors" data-status="former">
                            Former <span class="ml-1 text-xs opacity-75" id="formerCount">0</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Members Grid -->
        @if(isset($members) && count($members) > 0)
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" id="membersGrid">
            @foreach($members as $member)
            @php
                $user = $member->user;
                $profile = $user;
                $gender = $profile->gender ?? 'm';
                $isMale = $gender === 'm' || $gender === 'male';
                $dob = $profile->birthdate ?? null;
                $age = $dob ? \Carbon\Carbon::parse($dob)->age : null;
                $nationality = $profile->nationality ?? null;
                $hasActiveEnrollment = $member->status === 'active';

                // Age group calculation
                $ageGroup = 'Adult';
                if ($age !== null) {
                    if ($age < 2) {
                        $ageGroup = 'Infant';
                    } elseif ($age < 4) {
                        $ageGroup = 'Toddler';
                    } elseif ($age < 6) {
                        $ageGroup = 'Preschooler';
                    } elseif ($age < 13) {
                        $ageGroup = 'Child';
                    } elseif ($age < 20) {
                        $ageGroup = 'Teenager';
                    } elseif ($age < 40) {
                        $ageGroup = 'Young Adult';
                    } elseif ($age < 60) {
                        $ageGroup = 'Adult';
                    } else {
                        $ageGroup = 'Senior';
                    }
                }

                // Horoscope symbols
                $horoscopeSymbols = [
                    'Aries' => '♈', 'Taurus' => '♉', 'Gemini' => '♊', 'Cancer' => '♋',
                    'Leo' => '♌', 'Virgo' => '♍', 'Libra' => '♎', 'Scorpio' => '♏',
                    'Sagittarius' => '♐', 'Capricorn' => '♑', 'Aquarius' => '♒', 'Pisces' => '♓'
                ];
                $horoscope = $dob ? getHoroscope($dob) : 'N/A';
                $horoscopeSymbol = $horoscopeSymbols[$horoscope] ?? '';
            @endphp
            <div class="member-item"
                 data-name="{{ strtolower($profile->full_name ?? '') }}"
                 data-rank="member"
                 data-status="{{ $member->status }}"
                 data-has-enrollment="{{ $hasActiveEnrollment ? '1' : '0' }}"
                 data-member-phone="{{ $profile->mobileFormatted ?? '' }}"
                 data-member-nationality="{{ $nationality ?? '' }}"
                 data-member-gender="{{ $gender }}">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 member-card h-full overflow-hidden flex flex-col">
                    <!-- Header with gradient background -->
                    <div class="p-4 pb-3" style="background: linear-gradient(135deg, {{ $isMale ? 'rgba(147, 51, 234, 0.1) 0%, rgba(147, 51, 234, 0.05) 50%' : 'rgba(214, 51, 132, 0.1) 0%, rgba(214, 51, 132, 0.05) 50%' }}, transparent 100%);">
                        <div class="flex items-start gap-3">
                            <div class="relative flex-shrink-0">
                                <div class="rounded-full border-4 border-white shadow-md overflow-hidden" style="width: 80px; height: 80px; box-shadow: 0 0 0 2px {{ $isMale ? 'rgba(147, 51, 234, 0.3)' : 'rgba(214, 51, 132, 0.3)' }} !important;">
                                    @if($profile->profile_picture)
                                    <img src="{{ asset('storage/' . $profile->profile_picture) }}"
                                         alt="{{ $profile->full_name }}"
                                         class="w-full h-full object-cover">
                                    @else
                                    <div class="w-full h-full flex items-center justify-content-center text-white font-bold text-2xl" style="background: linear-gradient(135deg, {{ $isMale ? '#8b5cf6 0%, #7c3aed 100%' : '#d63384 0%, #a61e4d 100%' }}); display: flex; align-items: center; justify-content: center;">
                                        {{ strtoupper(substr($profile->full_name ?? 'M', 0, 1)) }}
                                    </div>
                                    @endif
                                </div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h5 class="font-bold text-lg text-gray-900 truncate mb-2">{{ $profile->full_name ?? 'Unknown' }}</h5>
                                <div class="flex flex-wrap gap-2">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold {{ $isMale ? 'bg-purple-500' : 'bg-pink-500' }} text-white">{{ $ageGroup }}</span>
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-purple-100 text-purple-700">Member</span>
                                    @if($member->achievements > 0)
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-amber-100 text-amber-700">{{ $member->achievements }} &#127942;</span>
                                    @endif
                                </div>

                                @php
                                    $guardianRelation = $profile->guardians->first();
                                    $guardian = $guardianRelation ? $guardianRelation->guardian : null;
                                @endphp
                            </div>
                        </div>
                    </div>

                    <!-- Contact Info -->
                    @php
                        // Determine which contact info to show (member's own or guardian's)
                        $displayMobile = $profile->mobile;
                        $displayEmail = $profile->email;
                        $isGuardianContact = false;

                        // If member has no contact info but has a guardian, use guardian's contact
                        if ((!$displayMobile || (is_array($displayMobile) && empty($displayMobile['number']))) && !$displayEmail && $guardian) {
                            $displayMobile = $guardian->mobile;
                            $displayEmail = $guardian->email;
                            $isGuardianContact = true;
                        }
                    @endphp
                    <div class="px-4 py-3 bg-gray-50 border-y border-gray-200">
                        @if($isGuardianContact && $guardian)
                        <div class="flex items-center gap-1 text-xs text-blue-600 mb-2">
                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path></svg>
                            <span class="font-medium">Guardian's contact ({{ $guardian->full_name }})</span>
                        </div>
                        @endif
                        @if($displayMobile && is_array($displayMobile) && isset($displayMobile['number']))
                        <div class="flex items-center gap-2 text-sm mb-2">
                            <svg class="w-4 h-4 {{ $isMale ? 'text-purple-500' : 'text-pink-500' }}" fill="currentColor" viewBox="0 0 20 20"><path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z"></path></svg>
                            <span class="font-medium text-gray-600">{{ $displayMobile['code'] ?? '' }} {{ $displayMobile['number'] }}</span>
                        </div>
                        @elseif($displayMobile && is_string($displayMobile))
                        <div class="flex items-center gap-2 text-sm mb-2">
                            <svg class="w-4 h-4 {{ $isMale ? 'text-purple-500' : 'text-pink-500' }}" fill="currentColor" viewBox="0 0 20 20"><path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z"></path></svg>
                            <span class="font-medium text-gray-600">{{ $displayMobile }}</span>
                        </div>
                        @endif
                        @if($displayEmail)
                        <div class="flex items-center gap-2 text-sm">
                            <svg class="w-4 h-4 {{ $isMale ? 'text-purple-500' : 'text-pink-500' }}" fill="currentColor" viewBox="0 0 20 20"><path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"></path><path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"></path></svg>
                            <span class="font-medium text-gray-600 truncate">{{ $displayEmail }}</span>
                        </div>
                        @endif
                        @if(!$displayMobile && !$displayEmail)
                        <div class="flex items-center gap-2 text-sm text-gray-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            <span class="font-medium">No contact info</span>
                        </div>
                        @endif
                    </div>

                    <!-- Member Details -->
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
                        <div class="pt-2 border-t border-gray-100">
                            <div class="flex justify-between items-center text-sm mb-2">
                                <span class="text-gray-500 font-medium">Next Birthday</span>
                                <span class="font-semibold text-gray-600">
                                    @if($dob)
                                        {{ \Carbon\Carbon::parse($dob)->copy()->year(now()->year)->isFuture()
                                            ? \Carbon\Carbon::parse($dob)->copy()->year(now()->year)->diffForHumans(['parts' => 2, 'syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE])
                                            : \Carbon\Carbon::parse($dob)->copy()->year(now()->year + 1)->diffForHumans(['parts' => 2, 'syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE]) }}
                                    @else
                                        N/A
                                    @endif
                                </span>
                            </div>
                            <div class="flex justify-between items-center text-sm">
                                <span class="text-gray-500 font-medium">Member Since</span>
                                <span class="font-semibold text-gray-600">{{ $member->created_at ? $member->created_at->format('d/m/Y') : 'N/A' }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    @if($member->status === 'active')
                    <div class="px-4 py-3 bg-gray-50 border-t border-gray-200">
                        <div class="flex gap-2 flex-wrap">
                            <button onclick='openEditModal(@json($member))' class="flex-1 inline-flex items-center justify-center px-3 py-2 bg-purple-500 text-white text-sm font-medium rounded-lg hover:bg-purple-600 transition-colors">
                                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                Edit
                            </button>

                            @if($profile->guardians->isNotEmpty())
                            <button onclick='openGraduateModal(@json($member))' class="flex-1 inline-flex items-center justify-center px-3 py-2 border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors">
                                Graduate
                            </button>
                            @else
                            <button onclick='openDegradateModal(@json($member))' class="flex-1 inline-flex items-center justify-center px-3 py-2 border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors">
                                To Child
                            </button>
                            @endif

                            @if(Auth::user()->isSuperAdmin())
                            <button onclick="deleteMember({{ $member->id }})" class="inline-flex items-center justify-center px-3 py-2 bg-red-500 text-white text-sm font-medium rounded-lg hover:bg-red-600 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                            </button>
                            @endif
                        </div>
                    </div>
                    @endif

                    <!-- Footer -->
                    <div class="px-4 py-2 {{ $isMale ? 'bg-purple-500/10' : 'bg-pink-500/10' }} border-t {{ $isMale ? 'border-purple-200' : 'border-pink-200' }}">
                        <div class="flex items-center justify-center gap-2 text-sm">
                            @if($member->status === 'inactive')
                            <span class="font-medium text-gray-500">
                                INACTIVE
                            </span>
                            @elseif($hasActiveEnrollment)
                            <span class="font-medium {{ $isMale ? 'text-purple-700' : 'text-pink-700' }}">
                                ACTIVE MEMBER
                            </span>
                            @else
                            <span class="font-medium text-gray-500">
                                CLUB MEMBER
                            </span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        <!-- Pagination -->
        @if($members instanceof \Illuminate\Pagination\LengthAwarePaginator && $members->hasPages())
        <div class="flex justify-center mt-8">
            {{ $members->links() }}
        </div>
        @endif
        @else
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center">
            <div class="w-20 h-20 mx-auto mb-4 rounded-full bg-gray-100 flex items-center justify-center">
                <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
            </div>
            <h5 class="text-lg font-semibold text-gray-900 mb-2">No members found</h5>
            <p class="text-gray-500 mb-4">Start adding members to your club</p>
            <button onclick="openWalkInModal()" class="inline-flex items-center px-4 py-2 bg-purple-500 text-white rounded-lg hover:bg-purple-600 transition-colors font-medium">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                Add Member
            </button>
        </div>
        @endif
    </div>

    <!-- Requests Tab Content -->
    <div id="requests-content" class="hidden">
        @if(isset($membershipRequests) && count($membershipRequests) > 0)
        <div class="space-y-4">
            @foreach($membershipRequests as $request)
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-4">
                            @if($request->user->profile_picture)
                            <img src="{{ asset('storage/' . $request->user->profile_picture) }}" alt="" class="w-12 h-12 rounded-full object-cover">
                            @else
                            <div class="w-12 h-12 rounded-full bg-purple-500 flex items-center justify-center">
                                <span class="text-white font-bold">{{ strtoupper(substr($request->user->full_name ?? '?', 0, 1)) }}</span>
                            </div>
                            @endif
                            <div>
                                <h6 class="font-bold text-gray-900">{{ $request->user->full_name ?? 'Unknown' }}</h6>
                                <p class="text-sm text-gray-500 flex items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                    Requested on {{ $request->created_at->format('M d, Y') }}
                                </p>
                            </div>
                        </div>
                        <span class="px-3 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-700">Pending</span>
                    </div>
                </div>
                <div class="p-6 space-y-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Review Notes</label>
                        <textarea class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent resize-none" rows="2" placeholder="Add notes about this request..." id="reviewNotes_{{ $request->id }}"></textarea>
                    </div>
                    <div class="flex gap-3">
                        <button onclick="approveRequest({{ $request->id }}, {{ $request->user_id }})" class="flex-1 inline-flex items-center justify-center px-4 py-2.5 bg-green-500 text-white font-medium rounded-lg hover:bg-green-600 transition-colors">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            Approve
                        </button>
                        <button onclick="rejectRequest({{ $request->id }})" class="flex-1 inline-flex items-center justify-center px-4 py-2.5 bg-red-500 text-white font-medium rounded-lg hover:bg-red-600 transition-colors">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                            Reject
                        </button>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        @else
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center">
            <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path></svg>
            <h5 class="text-lg font-semibold text-gray-900 mb-2">No pending requests</h5>
            <p class="text-gray-500">All membership requests have been processed</p>
        </div>
        @endif
    </div>
</div>

<!-- Add Existing User Modal -->
<div id="addExistingUserModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex min-h-screen items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/50 transition-opacity" onclick="closeModal('addExistingUserModal')"></div>
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-2xl max-h-[90vh] overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100">
                <h3 class="text-xl font-bold text-gray-900">Add Existing User</h3>
                <p class="text-sm text-gray-500 mt-1">Search for a user by email or phone number to add them and their children as members.</p>
            </div>
            <div class="p-6 overflow-y-auto max-h-[60vh]">
                <div class="flex gap-3 mb-6">
                    <input type="text" id="searchUserInput" placeholder="Enter email or phone number..." class="flex-1 px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    <button onclick="searchUser()" id="searchUserBtn" class="inline-flex items-center px-4 py-2.5 bg-purple-500 text-white font-medium rounded-lg hover:bg-purple-600 transition-colors">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        Search
                    </button>
                </div>
                <div id="searchResults" class="hidden">
                    <label class="block text-sm font-semibold text-gray-700 mb-3">Select members to add:</label>
                    <div id="searchResultsList" class="space-y-3 max-h-96 overflow-y-auto"></div>
                </div>
            </div>
            <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-3">
                <button onclick="closeModal('addExistingUserModal')" class="px-4 py-2.5 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition-colors">Cancel</button>
                <button onclick="addSelectedMembers()" id="addMembersBtn" disabled class="px-4 py-2.5 bg-purple-500 text-white font-medium rounded-lg hover:bg-purple-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                    Add <span id="selectedCount">0</span> Member(s)
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Walk-In Registration Modal (4-Step Wizard) -->
<div id="walkInModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex min-h-screen items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/50 transition-opacity" onclick="closeModal('walkInModal')"></div>
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-3xl max-h-[90vh] overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100">
                <h3 class="text-xl font-bold text-gray-900">Walk-In Registration</h3>
                <p class="text-sm text-gray-500 mt-1">Step <span id="currentStepNum">1</span> of 4: <span id="currentStepName">Personal Information</span></p>
            </div>

            <!-- Step Indicator -->
            <div class="flex items-center justify-center gap-2 py-4 px-6 bg-gray-50">
                <div id="stepIndicator1" class="w-10 h-10 rounded-full flex items-center justify-center font-semibold bg-purple-500 text-white transition-colors">1</div>
                <div id="stepLine1" class="h-0.5 w-12 bg-gray-200 transition-colors"></div>
                <div id="stepIndicator2" class="w-10 h-10 rounded-full flex items-center justify-center font-semibold bg-gray-200 text-gray-500 transition-colors">2</div>
                <div id="stepLine2" class="h-0.5 w-12 bg-gray-200 transition-colors"></div>
                <div id="stepIndicator3" class="w-10 h-10 rounded-full flex items-center justify-center font-semibold bg-gray-200 text-gray-500 transition-colors">3</div>
                <div id="stepLine3" class="h-0.5 w-12 bg-gray-200 transition-colors"></div>
                <div id="stepIndicator4" class="w-10 h-10 rounded-full flex items-center justify-center font-semibold bg-gray-200 text-gray-500 transition-colors">4</div>
            </div>

            <div class="overflow-y-auto max-h-[60vh]">
                <!-- Step 1: Personal Information -->
                <div id="walkInStep1" class="p-6 space-y-6">
                    <div class="bg-gray-50 rounded-xl p-4">
                        <h4 class="flex items-center gap-2 font-semibold text-gray-900 mb-4">
                            <svg class="w-5 h-5 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                            Personal Information
                        </h4>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="sm:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Full Name <span class="text-red-500">*</span></label>
                                <input type="text" id="walkIn_name" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent" placeholder="John Doe">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Email <span class="text-red-500">*</span></label>
                                <input type="email" id="walkIn_email" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent" placeholder="member@example.com">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Password <span class="text-red-500">*</span></label>
                                <input type="password" id="walkIn_password" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent" placeholder="Minimum 6 characters">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Phone Number <span class="text-red-500">*</span></label>
                                <div class="flex">
                                    <select id="walkIn_countryCode" class="px-3 py-2.5 border border-gray-200 rounded-l-lg bg-gray-50 text-sm">
                                        <option value="+973">+973 BH</option>
                                        <option value="+966">+966 SA</option>
                                        <option value="+971">+971 AE</option>
                                        <option value="+965">+965 KW</option>
                                        <option value="+1">+1 US</option>
                                    </select>
                                    <input type="text" id="walkIn_phone" class="flex-1 px-4 py-2.5 border-y border-r border-gray-200 rounded-r-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent" placeholder="Phone number">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Date of Birth <span class="text-red-500">*</span></label>
                                <input type="date" id="walkIn_dob" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Gender <span class="text-red-500">*</span></label>
                                <div class="grid grid-cols-2 gap-2">
                                    <button type="button" onclick="selectGender('male')" id="genderMale" class="px-4 py-2.5 border-2 border-gray-200 rounded-lg font-medium text-gray-700 hover:border-purple-400 transition-colors">Male</button>
                                    <button type="button" onclick="selectGender('female')" id="genderFemale" class="px-4 py-2.5 border-2 border-gray-200 rounded-lg font-medium text-gray-700 hover:border-purple-400 transition-colors">Female</button>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Nationality <span class="text-red-500">*</span></label>
                                <select id="walkIn_nationality" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                                    <option value="">Select nationality</option>
                                    <option value="Bahrain">Bahrain</option>
                                    <option value="Saudi Arabia">Saudi Arabia</option>
                                    <option value="UAE">United Arab Emirates</option>
                                    <option value="Kuwait">Kuwait</option>
                                    <option value="Qatar">Qatar</option>
                                    <option value="Oman">Oman</option>
                                    <option value="India">India</option>
                                    <option value="Pakistan">Pakistan</option>
                                    <option value="Philippines">Philippines</option>
                                    <option value="United States">United States</option>
                                    <option value="United Kingdom">United Kingdom</option>
                                </select>
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Address (Optional)</label>
                                <input type="text" id="walkIn_address" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent" placeholder="Street address">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Guardian & Children -->
                <div id="walkInStep2" class="p-6 space-y-6 hidden">
                    <div>
                        <h4 class="text-lg font-semibold text-gray-900 mb-4">Are you registering children?</h4>
                        <div class="grid grid-cols-2 gap-4">
                            <button type="button" onclick="setIsGuardian(true)" id="isGuardianYes" class="px-4 py-3 border-2 border-gray-200 rounded-lg font-medium text-gray-700 hover:border-purple-400 transition-colors flex items-center justify-center gap-2">
                                <svg class="w-5 h-5 hidden" id="guardianYesCheck" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                Yes, I'm a guardian
                            </button>
                            <button type="button" onclick="setIsGuardian(false)" id="isGuardianNo" class="px-4 py-3 border-2 border-gray-200 rounded-lg font-medium text-gray-700 hover:border-purple-400 transition-colors flex items-center justify-center gap-2">
                                <svg class="w-5 h-5 hidden" id="guardianNoCheck" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                No, just myself
                            </button>
                        </div>
                    </div>

                    <!-- Children Section -->
                    <div id="childrenSection" class="hidden space-y-4">
                        <div class="flex items-center justify-between">
                            <h4 class="font-semibold text-gray-900">Children</h4>
                            <button type="button" onclick="addChildForm()" class="inline-flex items-center px-3 py-1.5 bg-purple-500 text-white text-sm font-medium rounded-lg hover:bg-purple-600 transition-colors">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                                Add Child
                            </button>
                        </div>
                        <div id="childrenList" class="space-y-4"></div>
                    </div>
                </div>

                <!-- Step 3: Package Selection -->
                <div id="walkInStep3" class="p-6 space-y-6 hidden">
                    <div>
                        <h4 class="text-lg font-semibold text-gray-900 mb-2">Select Packages for Each Person</h4>
                        <p class="text-sm text-gray-500">Package selection is optional. You can register members without selecting packages.</p>
                    </div>

                    <div id="peoplePackagesList" class="space-y-4 max-h-80 overflow-y-auto"></div>

                    <!-- Cost Summary -->
                    <div class="bg-gray-50 rounded-xl p-4">
                        <h4 class="font-semibold text-gray-900 mb-3">Cost Summary</h4>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Enrollment Fee (<span id="memberCountText">0</span> members)</span>
                                <span id="enrollmentFeeText">{{ $club->currency ?? 'BHD' }} 0.00</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Packages Total</span>
                                <span id="packagesTotalText">{{ $club->currency ?? 'BHD' }} 0.00</span>
                            </div>
                            <div class="flex justify-between font-medium pt-2 border-t border-gray-200">
                                <span>Subtotal</span>
                                <span id="subtotalText">{{ $club->currency ?? 'BHD' }} 0.00</span>
                            </div>
                            <div class="pt-2">
                                <label class="text-xs text-gray-500">Discount (Optional)</label>
                                <div class="flex gap-2 mt-1">
                                    <button type="button" onclick="setDiscountType('percentage')" id="discountPercent" class="px-3 py-1.5 border border-gray-200 rounded-lg text-sm font-medium bg-purple-500 text-white">%</button>
                                    <button type="button" onclick="setDiscountType('fixed')" id="discountFixed" class="px-3 py-1.5 border border-gray-200 rounded-lg text-sm font-medium text-gray-700">{{ $club->currency ?? 'BHD' }}</button>
                                    <input type="number" id="discountValue" onchange="calculateTotals()" class="flex-1 px-3 py-1.5 border border-gray-200 rounded-lg text-sm" placeholder="0" min="0">
                                </div>
                            </div>
                            <div class="flex justify-between text-green-600" id="discountRow" style="display: none;">
                                <span>Discount</span>
                                <span id="discountText">-{{ $club->currency ?? 'BHD' }} 0.00</span>
                            </div>
                            @if(($club->vat_percentage ?? 0) > 0)
                            <div class="flex justify-between">
                                <span class="text-gray-600">VAT ({{ $club->vat_percentage }}%)</span>
                                <span id="vatText">{{ $club->currency ?? 'BHD' }} 0.00</span>
                            </div>
                            @endif
                            <div class="flex justify-between font-bold text-lg pt-3 border-t border-gray-200">
                                <span>Total Amount</span>
                                <span id="grandTotalText" class="text-purple-600">{{ $club->currency ?? 'BHD' }} 0.00</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 4: Payment Confirmation -->
                <div id="walkInStep4" class="p-6 space-y-6 hidden">
                    <div>
                        <h4 class="text-lg font-semibold text-gray-900 mb-2">Payment Confirmation</h4>
                        <p class="text-sm text-gray-500">Please collect the payment and confirm to complete registration.</p>
                    </div>

                    <div class="bg-purple-50 rounded-xl p-6 text-center">
                        <p class="text-sm text-gray-600 mb-2">Total Amount to Collect</p>
                        <p id="finalAmountText" class="text-4xl font-bold text-purple-600">{{ $club->currency ?? 'BHD' }} 0.00</p>
                    </div>

                    <div class="bg-white border border-gray-200 rounded-xl p-4">
                        <h5 class="font-semibold text-gray-900 mb-3">Registration Summary</h5>
                        <div id="registrationSummary" class="space-y-4"></div>
                    </div>
                </div>
            </div>

            <!-- Footer with Navigation -->
            <div class="px-6 py-4 border-t border-gray-100 flex justify-between">
                <button type="button" onclick="walkInPrevStep()" id="walkInBackBtn" class="px-4 py-2.5 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition-colors hidden">
                    <svg class="w-5 h-5 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                    Back
                </button>
                <button type="button" onclick="closeModal('walkInModal')" id="walkInCancelBtn" class="px-4 py-2.5 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition-colors">Cancel</button>
                <button type="button" onclick="walkInNextStep()" id="walkInNextBtn" class="px-6 py-2.5 bg-purple-500 text-white font-medium rounded-lg hover:bg-purple-600 transition-colors">
                    Next
                    <svg class="w-5 h-5 ml-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                </button>
                <button type="button" onclick="submitWalkInRegistration()" id="walkInSubmitBtn" class="px-6 py-2.5 bg-green-500 text-white font-medium rounded-lg hover:bg-green-600 transition-colors hidden">
                    <svg class="w-5 h-5 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    Confirm & Register
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Member Modal -->
<div id="editMemberModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex min-h-screen items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/50 transition-opacity" onclick="closeModal('editMemberModal')"></div>
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-xl overflow-hidden">
            <div class="px-6 py-4 bg-gradient-to-r from-purple-500/10 to-purple-500/5 border-b border-gray-100">
                <h3 class="text-xl font-bold text-gray-900">Edit Member Profile</h3>
                <p class="text-sm text-gray-500 mt-1">Update details for <span id="editMemberName" class="font-medium">Member</span></p>
            </div>
            <div class="p-6">
                <input type="hidden" id="editMemberId">
                <div class="space-y-5">
                    <div>
                        <label class="flex items-center gap-2 text-sm font-semibold text-gray-700 mb-2">
                            <span>&#127941;</span> Rank Level
                        </label>
                        <select id="editRank" class="w-full px-4 py-3 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-base">
                            <option value="Beginner">&#129353; Beginner</option>
                            <option value="Member">&#128100; Member</option>
                            <option value="Advanced">&#11088; Advanced</option>
                            <option value="Elite">&#128142; Elite</option>
                            <option value="Champion">&#127942; Champion</option>
                        </select>
                    </div>
                    <div>
                        <label class="flex items-center gap-2 text-sm font-semibold text-gray-700 mb-2">
                            <span>&#127942;</span> Achievements Count
                        </label>
                        <input type="number" id="editAchievements" min="0" class="w-full px-4 py-3 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-base" placeholder="Number of achievements earned">
                    </div>
                </div>
            </div>
            <div class="px-6 py-4 bg-gray-50 border-t border-gray-100">
                <div class="flex flex-wrap gap-3">
                    <button onclick="closeModal('editMemberModal')" class="px-4 py-2.5 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-100 transition-colors">Cancel</button>
                    <button onclick="openEnrollModal()" class="flex-1 px-4 py-2.5 bg-gray-600 text-white font-medium rounded-lg hover:bg-gray-700 transition-colors">
                        <svg class="w-5 h-5 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
                        Enroll
                    </button>
                    <button onclick="openLeaveModal()" class="flex-1 px-4 py-2.5 bg-red-500 text-white font-medium rounded-lg hover:bg-red-600 transition-colors">
                        <svg class="w-5 h-5 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                        Leave
                    </button>
                    <button onclick="saveEditMember()" class="flex-1 px-4 py-2.5 bg-purple-500 text-white font-medium rounded-lg hover:bg-purple-600 transition-colors">
                        <svg class="w-5 h-5 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        Save
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Enroll in Package Modal -->
<div id="enrollPackageModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex min-h-screen items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/50 transition-opacity" onclick="closeModal('enrollPackageModal')"></div>
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-3xl max-h-[90vh] overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100">
                <h3 class="text-xl font-bold text-gray-900">Enroll in Package</h3>
                <p class="text-sm text-gray-500 mt-1">Select the perfect package for <span id="enrollMemberName" class="font-medium">member</span></p>
            </div>
            <div class="p-6 overflow-y-auto max-h-[60vh]">
                <div id="enrollMemberInfo" class="hidden mb-6"></div>
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <h4 class="font-semibold text-gray-900">Available Packages</h4>
                        <span class="px-2.5 py-1 bg-gray-100 text-gray-600 text-sm rounded-full" id="packagesCount">0 packages</span>
                    </div>
                    <div id="enrollPackagesList" class="space-y-4"></div>
                </div>
            </div>
            <div class="px-6 py-4 border-t border-gray-100 flex gap-3">
                <button onclick="closeModal('enrollPackageModal')" class="flex-1 px-4 py-2.5 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition-colors">Cancel</button>
                <button onclick="confirmEnrollment()" id="confirmEnrollBtn" disabled class="flex-1 px-4 py-2.5 bg-purple-500 text-white font-medium rounded-lg hover:bg-purple-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                    <svg class="w-5 h-5 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    Confirm Enrollment
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Leave Club Modal -->
<div id="leaveClubModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex min-h-screen items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/50 transition-opacity" onclick="closeModal('leaveClubModal')"></div>
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100">
                <h3 class="text-xl font-bold text-gray-900">Confirm Member Leave</h3>
            </div>
            <div class="p-6">
                <p class="text-gray-600 mb-4">Are you sure you want to process <strong id="leaveMemberName">this member</strong>'s departure from the club?</p>
                <div class="mb-4">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Leave Reason (Optional)</label>
                    <textarea id="leaveReason" rows="3" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent resize-none" placeholder="Enter reason for leaving..."></textarea>
                </div>
                <div class="bg-gray-50 rounded-lg p-4">
                    <p class="text-sm font-semibold text-gray-700 mb-2">What will happen:</p>
                    <ul class="text-sm text-gray-500 space-y-1 list-disc list-inside">
                        <li>Member status will be set to inactive</li>
                        <li>All package enrollments will be deactivated</li>
                        <li>Membership history will be recorded</li>
                    </ul>
                </div>
            </div>
            <div class="px-6 py-4 border-t border-gray-100 flex gap-3">
                <button onclick="closeModal('leaveClubModal')" class="flex-1 px-4 py-2.5 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition-colors">Cancel</button>
                <button onclick="confirmLeave()" class="flex-1 px-4 py-2.5 bg-red-500 text-white font-medium rounded-lg hover:bg-red-600 transition-colors">Confirm Leave</button>
            </div>
        </div>
    </div>
</div>

<!-- Graduate Child Modal -->
<div id="graduateChildModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex min-h-screen items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/50 transition-opacity" onclick="closeModal('graduateChildModal')"></div>
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100">
                <h3 class="text-xl font-bold text-gray-900">Graduate Child to Adult</h3>
            </div>
            <div class="p-6">
                <p class="text-gray-600 mb-4"><strong id="graduateChildName">Child</strong> will become an independent adult member with their own account.</p>
                <input type="hidden" id="graduateChildId">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Email <span class="text-red-500">*</span></label>
                        <input type="email" id="graduateEmail" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent" placeholder="member@example.com">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Password <span class="text-red-500">*</span></label>
                        <input type="password" id="graduatePassword" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent" placeholder="Create a strong password">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Phone <span class="text-red-500">*</span></label>
                        <div class="flex">
                            <select id="graduateCountryCode" class="px-3 py-2.5 border border-gray-200 rounded-l-lg bg-gray-50 text-sm">
                                <option value="+973">+973</option>
                                <option value="+966">+966</option>
                                <option value="+971">+971</option>
                            </select>
                            <input type="text" id="graduatePhone" class="flex-1 px-4 py-2.5 border-y border-r border-gray-200 rounded-r-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent" placeholder="Phone number">
                        </div>
                    </div>
                </div>
            </div>
            <div class="px-6 py-4 border-t border-gray-100 flex gap-3">
                <button onclick="closeModal('graduateChildModal')" class="flex-1 px-4 py-2.5 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition-colors">Cancel</button>
                <button onclick="confirmGraduate()" class="flex-1 px-4 py-2.5 bg-purple-500 text-white font-medium rounded-lg hover:bg-purple-600 transition-colors">Graduate to Adult</button>
            </div>
        </div>
    </div>
</div>

<!-- Degrade to Child Modal -->
<div id="degradeToChildModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex min-h-screen items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/50 transition-opacity" onclick="closeModal('degradeToChildModal')"></div>
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100">
                <h3 class="text-xl font-bold text-gray-900">Move to Child Status</h3>
            </div>
            <div class="p-6">
                <p class="text-gray-600 mb-4"><strong id="degradeMemberName">Member</strong> will be managed as a child by a parent member.</p>
                <input type="hidden" id="degradeMemberId">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Parent Email or Phone <span class="text-red-500">*</span></label>
                    <div class="flex gap-2">
                        <input type="text" id="parentSearchInput" class="flex-1 px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent" placeholder="parent@example.com">
                        <button onclick="searchParent()" class="px-4 py-2.5 border border-purple-500 text-purple-600 rounded-lg hover:bg-purple-50 transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        </button>
                    </div>
                </div>
                <div id="parentSearchResults" class="hidden mb-4"></div>
                <div id="selectedParent" class="hidden mb-4 bg-purple-50 rounded-lg p-4"></div>
            </div>
            <div class="px-6 py-4 border-t border-gray-100 flex gap-3">
                <button onclick="closeModal('degradeToChildModal')" class="flex-1 px-4 py-2.5 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition-colors">Cancel</button>
                <button onclick="confirmDegrade()" id="confirmDegradeBtn" disabled class="flex-1 px-4 py-2.5 bg-purple-500 text-white font-medium rounded-lg hover:bg-purple-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">Move to Child</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const clubId = '{{ $club->id }}';
let selectedMembers = new Set();
let searchResults = [];
let currentEditingMember = null;
let selectedPackageId = null;
let selectedParentId = null;
let availablePackages = @json($packages ?? []);

document.addEventListener('DOMContentLoaded', function() {
    updateStatusCounts();
    initializeSearch();
    initializeStatusFilters();
    loadNationalityFlags();
});

// Load countries and convert ISO3 to flag emoji
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

// Tab switching
function switchTab(tab) {
    const membersTab = document.getElementById('members-tab-btn');
    const requestsTab = document.getElementById('requests-tab-btn');
    const membersContent = document.getElementById('members-content');
    const requestsContent = document.getElementById('requests-content');

    if (tab === 'members') {
        membersTab.classList.add('border-purple-500', 'text-purple-600');
        membersTab.classList.remove('border-transparent', 'text-gray-500');
        requestsTab.classList.remove('border-purple-500', 'text-purple-600');
        requestsTab.classList.add('border-transparent', 'text-gray-500');
        membersContent.classList.remove('hidden');
        requestsContent.classList.add('hidden');
    } else {
        requestsTab.classList.add('border-purple-500', 'text-purple-600');
        requestsTab.classList.remove('border-transparent', 'text-gray-500');
        membersTab.classList.remove('border-purple-500', 'text-purple-600');
        membersTab.classList.add('border-transparent', 'text-gray-500');
        requestsContent.classList.remove('hidden');
        membersContent.classList.add('hidden');
    }
}

// Modal functions
function openModal(id) { document.getElementById(id).classList.remove('hidden'); }
function closeModal(id) { document.getElementById(id).classList.add('hidden'); }
function openAddExistingUserModal() {
    openModal('addExistingUserModal');
    document.getElementById('searchUserInput').value = '';
    document.getElementById('searchResults').classList.add('hidden');
    document.getElementById('searchResultsList').innerHTML = '';
    selectedMembers.clear();
    updateSelectedCount();
}
function openWalkInModal() { openModal('walkInModal'); }

// Search existing users
async function searchUser() {
    const query = document.getElementById('searchUserInput').value.trim();
    if (query.length < 2) {
        alert('Please enter at least 2 characters to search');
        return;
    }

    const btn = document.getElementById('searchUserBtn');
    btn.disabled = true;
    btn.innerHTML = '<svg class="w-5 h-5 mr-2 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Searching...';

    try {
        const response = await fetch(`/admin/club/${clubId}/members/search?query=${encodeURIComponent(query)}`);
        const data = await response.json();

        const resultsContainer = document.getElementById('searchResults');
        const resultsList = document.getElementById('searchResultsList');
        resultsList.innerHTML = '';

        if (data.users && data.users.length > 0) {
            data.users.forEach(user => {
                const userCard = createUserCard(user);
                resultsList.appendChild(userCard);

                // Add dependents if any
                if (user.dependents && user.dependents.length > 0) {
                    user.dependents.forEach(dep => {
                        const depCard = createUserCard(dep, true);
                        resultsList.appendChild(depCard);
                    });
                }
            });
            resultsContainer.classList.remove('hidden');
        } else {
            resultsList.innerHTML = '<div class="text-center py-8 text-gray-500"><svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg><p>No users found matching your search</p></div>';
            resultsContainer.classList.remove('hidden');
        }
    } catch (error) {
        console.error('Search error:', error);
        alert('Error searching users. Please try again.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>Search';
    }
}

function createUserCard(user, isDependent = false) {
    const card = document.createElement('div');
    const isMale = user.gender === 'm' || user.gender === 'male';

    card.className = `search-result-card p-4 border-2 rounded-xl cursor-pointer transition-all ${user.is_member ? 'border-gray-200 bg-gray-50 opacity-60' : 'border-gray-200 hover:border-purple-400'} ${isDependent ? 'ml-8' : ''}`;
    card.dataset.userId = user.id;

    const initial = (user.name || 'U').charAt(0).toUpperCase();
    const gradientColor = isMale ? '#8b5cf6, #7c3aed' : '#d63384, #a61e4d';

    // Build contact info string
    let contactInfo = '';
    if (user.email) {
        contactInfo = user.email;
    } else if (user.mobile) {
        contactInfo = (user.mobile.code || '') + ' ' + (user.mobile.number || '');
    }

    // For children, show guardian info
    let guardianInfo = '';
    if (isDependent && user.is_child && user.guardian_name) {
        guardianInfo = `<span class="text-xs text-blue-600">Guardian: ${user.guardian_name}</span>`;
    }

    // Relationship badge color
    let badgeColor = 'bg-blue-500'; // default for children
    if (user.relationship_type === 'Spouse') {
        badgeColor = 'bg-pink-500';
    } else if (user.relationship_type === 'Son') {
        badgeColor = 'bg-blue-500';
    } else if (user.relationship_type === 'Daughter') {
        badgeColor = 'bg-purple-500';
    }

    card.innerHTML = `
        <div class="flex items-center gap-4">
            <div class="relative flex-shrink-0">
                ${user.profile_picture
                    ? `<img src="${user.profile_picture}" class="w-14 h-14 rounded-full object-cover border-2 border-white shadow">`
                    : `<div class="w-14 h-14 rounded-full flex items-center justify-center text-white font-bold text-xl shadow" style="background: linear-gradient(135deg, ${gradientColor});">${initial}</div>`
                }
                ${isDependent ? `<span class="absolute -top-1 -right-1 ${badgeColor} text-white text-xs px-1.5 py-0.5 rounded-full">${user.relationship_type || 'Family'}</span>` : ''}
            </div>
            <div class="flex-1 min-w-0">
                <h6 class="font-semibold text-gray-900 truncate">${user.name}</h6>
                <p class="text-sm text-gray-500">${contactInfo || 'No contact info'}</p>
                <div class="flex items-center gap-2">
                    ${user.age ? `<span class="text-xs text-gray-400">${user.age} years old</span>` : ''}
                    ${guardianInfo}
                </div>
            </div>
            <div class="flex-shrink-0">
                ${user.is_member
                    ? '<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700">Already Member</span>'
                    : `<div class="check-circle w-6 h-6 rounded-full border-2 border-gray-300 flex items-center justify-center transition-all">
                        <svg class="w-4 h-4 text-white hidden" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>
                       </div>`
                }
            </div>
        </div>
    `;

    if (!user.is_member) {
        card.addEventListener('click', () => toggleUserSelection(user.id, card));
    }

    return card;
}

function toggleUserSelection(userId, card) {
    if (selectedMembers.has(userId)) {
        selectedMembers.delete(userId);
        card.classList.remove('selected');
        card.querySelector('.check-circle').classList.remove('checked');
        card.querySelector('.check-circle svg').classList.add('hidden');
    } else {
        selectedMembers.add(userId);
        card.classList.add('selected');
        card.querySelector('.check-circle').classList.add('checked');
        card.querySelector('.check-circle svg').classList.remove('hidden');
    }
    updateSelectedCount();
}

function updateSelectedCount() {
    const count = selectedMembers.size;
    document.getElementById('selectedCount').textContent = count;
    document.getElementById('addMembersBtn').disabled = count === 0;
}

async function addSelectedMembers() {
    if (selectedMembers.size === 0) return;

    const btn = document.getElementById('addMembersBtn');
    btn.disabled = true;
    btn.innerHTML = 'Adding...';

    try {
        const response = await fetch(`/admin/club/${clubId}/members`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify({ user_ids: Array.from(selectedMembers) }),
        });

        const data = await response.json();

        if (response.ok && data.success) {
            closeModal('addExistingUserModal');
            window.location.reload();
        } else {
            alert(data.message || 'Error adding members');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error adding members. Please try again.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = 'Add <span id="selectedCount">0</span> Member(s)';
    }
}

// Allow search on Enter key
document.getElementById('searchUserInput')?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') searchUser();
});

// Status Filters
function initializeStatusFilters() {
    document.querySelectorAll('.status-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.status-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            filterMembers();
        });
    });
}

function updateStatusCounts() {
    const items = document.querySelectorAll('.member-item');
    let active = 0, notActive = 0, all = 0, former = 0;
    items.forEach(item => {
        const status = item.dataset.status;
        const hasEnrollment = item.dataset.hasEnrollment === '1';
        if (status === 'inactive') former++;
        else if (status === 'active') {
            all++;
            if (hasEnrollment) active++; else notActive++;
        }
    });
    document.getElementById('activeCount').textContent = active;
    document.getElementById('notActiveCount').textContent = notActive;
    document.getElementById('allCount').textContent = all;
    document.getElementById('formerCount').textContent = former;
}

function initializeSearch() {
    const input = document.getElementById('searchMembers');
    if (input) input.addEventListener('input', filterMembers);
}

function filterMembers() {
    const query = document.getElementById('searchMembers').value.toLowerCase();
    const activeBtn = document.querySelector('.status-btn.active');
    const statusFilter = activeBtn ? activeBtn.dataset.status : 'active';
    document.querySelectorAll('.member-item').forEach(item => {
        const name = item.dataset.name;
        const rank = item.dataset.rank;
        const status = item.dataset.status;
        const hasEnrollment = item.dataset.hasEnrollment === '1';
        const matchesSearch = name.includes(query) || rank.includes(query);
        let matchesStatus = false;
        switch(statusFilter) {
            case 'active': matchesStatus = status === 'active' && hasEnrollment; break;
            case 'not_active': matchesStatus = status === 'active' && !hasEnrollment; break;
            case 'all': matchesStatus = status === 'active'; break;
            case 'former': matchesStatus = status === 'inactive'; break;
        }
        item.style.display = matchesSearch && matchesStatus ? '' : 'none';
    });
}

// Edit Member
function openEditModal(member) {
    currentEditingMember = member;
    document.getElementById('editMemberId').value = member.id;
    document.getElementById('editMemberName').textContent = member.user?.full_name || member.name || 'Member';
    document.getElementById('editRank').value = member.rank || 'Member';
    document.getElementById('editAchievements').value = member.achievements || 0;
    openModal('editMemberModal');
}

async function saveEditMember() {
    const id = document.getElementById('editMemberId').value;
    try {
        const res = await fetch(`/admin/club/${clubId}/members/${id}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            body: JSON.stringify({ rank: document.getElementById('editRank').value, achievements: parseInt(document.getElementById('editAchievements').value) })
        });
        if (res.ok) { showToast('Member updated', 'success'); closeModal('editMemberModal'); location.reload(); }
        else throw new Error();
    } catch { showToast('Error updating member', 'error'); }
}

// Enroll
function openEnrollModal() {
    if (!currentEditingMember) return;
    document.getElementById('enrollMemberName').textContent = currentEditingMember.user?.full_name || 'member';
    displayPackages();
    closeModal('editMemberModal');
    openModal('enrollPackageModal');
}

function displayPackages() {
    const container = document.getElementById('enrollPackagesList');
    container.innerHTML = '';
    selectedPackageId = null;
    document.getElementById('confirmEnrollBtn').disabled = true;
    if (!availablePackages.length) {
        container.innerHTML = '<div class="text-center py-8 text-gray-500">No packages available</div>';
        return;
    }
    document.getElementById('packagesCount').textContent = `${availablePackages.length} package${availablePackages.length !== 1 ? 's' : ''}`;
    availablePackages.forEach(pkg => {
        const card = document.createElement('div');
        card.className = 'package-card bg-white border-2 border-gray-200 rounded-xl p-4 cursor-pointer transition-all hover:border-purple-400';
        card.dataset.id = pkg.id;
        card.onclick = () => selectPackage(pkg.id, card);
        card.innerHTML = `
            <div class="flex items-start gap-4">
                <div class="check-circle w-7 h-7 border-2 border-gray-300 rounded-full flex items-center justify-center transition-all flex-shrink-0">
                    <svg class="w-4 h-4 text-white hidden check-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                </div>
                <div class="flex-1">
                    <div class="flex items-center gap-2 mb-2">
                        <h5 class="font-bold text-gray-900">${pkg.name}</h5>
                        ${pkg.is_popular ? '<span class="px-2 py-0.5 bg-purple-500 text-white text-xs rounded-full">Popular</span>' : ''}
                    </div>
                    ${pkg.description ? `<p class="text-sm text-gray-500 mb-3">${pkg.description}</p>` : ''}
                    <div class="flex gap-6 text-sm">
                        <div><span class="text-gray-400">Price:</span> <span class="font-bold text-purple-600">${pkg.currency || 'BHD'} ${parseFloat(pkg.price).toFixed(2)}</span></div>
                        <div><span class="text-gray-400">Duration:</span> <span class="font-semibold">${pkg.duration_days} days</span></div>
                    </div>
                </div>
            </div>`;
        container.appendChild(card);
    });
}

function selectPackage(id, card) {
    document.querySelectorAll('.package-card').forEach(c => {
        c.classList.remove('selected', 'border-purple-500', 'bg-purple-50');
        c.classList.add('border-gray-200');
        c.querySelector('.check-circle').classList.remove('checked', 'bg-purple-500', 'border-purple-500');
        c.querySelector('.check-circle').classList.add('border-gray-300');
        c.querySelector('.check-icon').classList.add('hidden');
    });
    selectedPackageId = id;
    card.classList.add('selected', 'border-purple-500', 'bg-purple-50');
    card.classList.remove('border-gray-200');
    card.querySelector('.check-circle').classList.add('checked', 'bg-purple-500', 'border-purple-500');
    card.querySelector('.check-circle').classList.remove('border-gray-300');
    card.querySelector('.check-icon').classList.remove('hidden');
    document.getElementById('confirmEnrollBtn').disabled = false;
}

async function confirmEnrollment() {
    if (!selectedPackageId || !currentEditingMember) return;
    try {
        const res = await fetch(`/admin/club/${clubId}/members/${currentEditingMember.id}/enroll`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            body: JSON.stringify({ package_id: selectedPackageId })
        });
        if (res.ok) { showToast('Member enrolled', 'success'); closeModal('enrollPackageModal'); location.reload(); }
        else throw new Error();
    } catch { showToast('Error enrolling member', 'error'); }
}

// Leave
function openLeaveModal() {
    if (!currentEditingMember) return;
    document.getElementById('leaveMemberName').textContent = currentEditingMember.user?.full_name || 'this member';
    document.getElementById('leaveReason').value = '';
    closeModal('editMemberModal');
    openModal('leaveClubModal');
}

async function confirmLeave() {
    if (!currentEditingMember) return;
    try {
        const res = await fetch(`/admin/club/${clubId}/members/${currentEditingMember.id}/leave`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            body: JSON.stringify({ leave_reason: document.getElementById('leaveReason').value })
        });
        if (res.ok) { showToast('Member left club', 'success'); closeModal('leaveClubModal'); location.reload(); }
        else throw new Error();
    } catch { showToast('Error processing leave', 'error'); }
}

// Graduate
function openGraduateModal(member) {
    currentEditingMember = member;
    document.getElementById('graduateChildId').value = member.id;
    document.getElementById('graduateChildName').textContent = member.user?.full_name || 'Child';
    openModal('graduateChildModal');
}

async function confirmGraduate() {
    const email = document.getElementById('graduateEmail').value;
    const password = document.getElementById('graduatePassword').value;
    const phone = document.getElementById('graduatePhone').value;
    if (!email || !password || !phone) { showToast('Fill all fields', 'warning'); return; }
    try {
        const res = await fetch(`/admin/club/${clubId}/members/${currentEditingMember.id}/graduate`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            body: JSON.stringify({ email, password, phone, country_code: document.getElementById('graduateCountryCode').value })
        });
        if (res.ok) { showToast('Child graduated', 'success'); closeModal('graduateChildModal'); location.reload(); }
        else throw new Error();
    } catch { showToast('Error graduating child', 'error'); }
}

// Degrade
function openDegradateModal(member) {
    currentEditingMember = member;
    document.getElementById('degradeMemberId').value = member.id;
    document.getElementById('degradeMemberName').textContent = member.user?.full_name || 'Member';
    document.getElementById('parentSearchInput').value = '';
    document.getElementById('parentSearchResults').classList.add('hidden');
    document.getElementById('selectedParent').classList.add('hidden');
    selectedParentId = null;
    document.getElementById('confirmDegradeBtn').disabled = true;
    openModal('degradeToChildModal');
}

async function searchParent() {
    const q = document.getElementById('parentSearchInput').value.trim();
    if (!q) { showToast('Enter parent email/phone', 'warning'); return; }
    try {
        const res = await fetch(`/api/users/search?q=${encodeURIComponent(q)}`);
        const data = await res.json();
        if (data.success && data.data.length) displayParentResults(data.data);
        else showToast('No parent found', 'warning');
    } catch { showToast('Error searching', 'error'); }
}

function displayParentResults(results) {
    const container = document.getElementById('parentSearchResults');
    container.innerHTML = results.map(r => `
        <div onclick="selectParent(${JSON.stringify(r).replace(/"/g, '&quot;')})" class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg cursor-pointer hover:bg-gray-100 transition-colors mb-2">
            <div class="w-10 h-10 rounded-full bg-purple-500 flex items-center justify-center text-white font-bold">${(r.name || '?').charAt(0).toUpperCase()}</div>
            <div><div class="font-semibold text-gray-900">${r.name}</div><div class="text-sm text-gray-500">${r.email || r.phone || ''}</div></div>
        </div>
    `).join('');
    container.classList.remove('hidden');
    document.getElementById('selectedParent').classList.add('hidden');
}

function selectParent(parent) {
    selectedParentId = parent.user_id || parent.id;
    document.getElementById('parentSearchResults').classList.add('hidden');
    const selected = document.getElementById('selectedParent');
    selected.innerHTML = `<div class="flex items-center gap-3"><div class="w-10 h-10 rounded-full bg-purple-500 flex items-center justify-center text-white font-bold">${(parent.name || '?').charAt(0).toUpperCase()}</div><div><div class="font-semibold text-gray-900">${parent.name}</div><div class="text-sm text-gray-500">${parent.email || ''}</div></div></div>`;
    selected.classList.remove('hidden');
    document.getElementById('confirmDegradeBtn').disabled = false;
}

async function confirmDegrade() {
    if (!selectedParentId || !currentEditingMember) return;
    try {
        const res = await fetch(`/admin/club/${clubId}/members/${currentEditingMember.id}/degrade`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            body: JSON.stringify({ parent_id: selectedParentId })
        });
        if (res.ok) { showToast('Moved to child', 'success'); closeModal('degradeToChildModal'); location.reload(); }
        else throw new Error();
    } catch { showToast('Error moving to child', 'error'); }
}

async function deleteMember(id) {
    if (!confirm('Delete this member?')) return;
    try {
        const res = await fetch(`/admin/club/${clubId}/members/${id}`, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content } });
        if (res.ok) { showToast('Member deleted', 'success'); location.reload(); }
        else throw new Error();
    } catch { showToast('Error deleting', 'error'); }
}

// ============================================
// WALK-IN REGISTRATION MULTI-STEP WIZARD
// ============================================
let walkInStep = 1;
let walkInData = {
    guardian: { name: '', email: '', password: '', phone: '', countryCode: '+973', dob: '', gender: '', nationality: '', address: '' },
    isGuardian: false,
    children: [],
    people: [],
    discountType: 'percentage',
    discountValue: 0
};
const currency = '{{ $club->currency ?? "BHD" }}';
const enrollmentFee = {{ $club->enrollment_fee ?? 0 }};
const vatPercentage = {{ $club->vat_percentage ?? 0 }};
const stepNames = ['Personal Information', 'Guardian & Children', 'Package Selection', 'Payment Confirmation'];

function resetWalkInForm() {
    walkInStep = 1;
    walkInData = {
        guardian: { name: '', email: '', password: '', phone: '', countryCode: '+973', dob: '', gender: '', nationality: '', address: '' },
        isGuardian: false,
        children: [],
        people: [],
        discountType: 'percentage',
        discountValue: 0
    };
    document.getElementById('walkIn_name').value = '';
    document.getElementById('walkIn_email').value = '';
    document.getElementById('walkIn_password').value = '';
    document.getElementById('walkIn_phone').value = '';
    document.getElementById('walkIn_dob').value = '';
    document.getElementById('walkIn_nationality').value = '';
    document.getElementById('walkIn_address').value = '';
    document.getElementById('genderMale').classList.remove('border-purple-500', 'bg-purple-50');
    document.getElementById('genderFemale').classList.remove('border-purple-500', 'bg-purple-50');
    document.getElementById('childrenList').innerHTML = '';
    updateWalkInStepUI();
}

function openWalkInModal() {
    resetWalkInForm();
    openModal('walkInModal');
}

function updateWalkInStepUI() {
    // Update step indicator
    for (let i = 1; i <= 4; i++) {
        const indicator = document.getElementById(`stepIndicator${i}`);
        const line = document.getElementById(`stepLine${i}`);
        if (i < walkInStep) {
            indicator.className = 'w-10 h-10 rounded-full flex items-center justify-center font-semibold bg-purple-500 text-white transition-colors';
            indicator.innerHTML = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>';
            if (line) line.className = 'h-0.5 w-12 bg-purple-500 transition-colors';
        } else if (i === walkInStep) {
            indicator.className = 'w-10 h-10 rounded-full flex items-center justify-center font-semibold bg-purple-500 text-white transition-colors';
            indicator.innerHTML = i;
        } else {
            indicator.className = 'w-10 h-10 rounded-full flex items-center justify-center font-semibold bg-gray-200 text-gray-500 transition-colors';
            indicator.innerHTML = i;
            if (line) line.className = 'h-0.5 w-12 bg-gray-200 transition-colors';
        }
    }

    // Update step name
    document.getElementById('currentStepNum').textContent = walkInStep;
    document.getElementById('currentStepName').textContent = stepNames[walkInStep - 1];

    // Show/hide steps
    for (let i = 1; i <= 4; i++) {
        document.getElementById(`walkInStep${i}`).classList.toggle('hidden', i !== walkInStep);
    }

    // Show/hide buttons
    document.getElementById('walkInBackBtn').classList.toggle('hidden', walkInStep === 1);
    document.getElementById('walkInCancelBtn').classList.toggle('hidden', walkInStep > 1);
    document.getElementById('walkInNextBtn').classList.toggle('hidden', walkInStep === 4);
    document.getElementById('walkInSubmitBtn').classList.toggle('hidden', walkInStep !== 4);
}

function selectGender(gender) {
    walkInData.guardian.gender = gender;
    document.getElementById('genderMale').classList.toggle('border-purple-500', gender === 'male');
    document.getElementById('genderMale').classList.toggle('bg-purple-50', gender === 'male');
    document.getElementById('genderFemale').classList.toggle('border-purple-500', gender === 'female');
    document.getElementById('genderFemale').classList.toggle('bg-purple-50', gender === 'female');
}

function setIsGuardian(isGuardian) {
    walkInData.isGuardian = isGuardian;
    document.getElementById('isGuardianYes').classList.toggle('border-purple-500', isGuardian);
    document.getElementById('isGuardianYes').classList.toggle('bg-purple-50', isGuardian);
    document.getElementById('guardianYesCheck').classList.toggle('hidden', !isGuardian);
    document.getElementById('isGuardianNo').classList.toggle('border-purple-500', !isGuardian);
    document.getElementById('isGuardianNo').classList.toggle('bg-purple-50', !isGuardian);
    document.getElementById('guardianNoCheck').classList.toggle('hidden', isGuardian);
    document.getElementById('childrenSection').classList.toggle('hidden', !isGuardian);
    if (!isGuardian) {
        walkInData.children = [];
        document.getElementById('childrenList').innerHTML = '';
    }
}

function addChildForm() {
    const childId = 'child_' + Date.now();
    walkInData.children.push({ id: childId, name: '', dob: '', gender: 'male', nationality: walkInData.guardian.nationality });
    renderChildren();
}

function removeChild(childId) {
    walkInData.children = walkInData.children.filter(c => c.id !== childId);
    renderChildren();
}

function renderChildren() {
    const container = document.getElementById('childrenList');
    container.innerHTML = walkInData.children.map((child, index) => `
        <div class="bg-white border border-gray-200 rounded-xl p-4" id="childCard_${child.id}">
            <div class="flex justify-between items-start mb-4">
                <h5 class="font-medium text-gray-900">Child ${index + 1}</h5>
                <button type="button" onclick="removeChild('${child.id}')" class="text-gray-400 hover:text-red-500 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Name <span class="text-red-500">*</span></label>
                    <input type="text" id="child_name_${child.id}" value="${child.name}" onchange="updateChild('${child.id}', 'name', this.value)" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-purple-500" placeholder="Child's name">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date of Birth <span class="text-red-500">*</span></label>
                    <input type="date" id="child_dob_${child.id}" value="${child.dob}" onchange="updateChild('${child.id}', 'dob', this.value)" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-purple-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Gender <span class="text-red-500">*</span></label>
                    <div class="grid grid-cols-2 gap-2">
                        <button type="button" onclick="updateChild('${child.id}', 'gender', 'male')" id="child_gender_male_${child.id}" class="px-3 py-2 border-2 ${child.gender === 'male' ? 'border-purple-500 bg-purple-50' : 'border-gray-200'} rounded-lg text-sm font-medium">Male</button>
                        <button type="button" onclick="updateChild('${child.id}', 'gender', 'female')" id="child_gender_female_${child.id}" class="px-3 py-2 border-2 ${child.gender === 'female' ? 'border-purple-500 bg-purple-50' : 'border-gray-200'} rounded-lg text-sm font-medium">Female</button>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nationality <span class="text-red-500">*</span></label>
                    <select id="child_nationality_${child.id}" onchange="updateChild('${child.id}', 'nationality', this.value)" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-purple-500">
                        <option value="">Select</option>
                        <option value="Bahrain" ${child.nationality === 'Bahrain' ? 'selected' : ''}>Bahrain</option>
                        <option value="Saudi Arabia" ${child.nationality === 'Saudi Arabia' ? 'selected' : ''}>Saudi Arabia</option>
                        <option value="UAE" ${child.nationality === 'UAE' ? 'selected' : ''}>UAE</option>
                        <option value="Kuwait" ${child.nationality === 'Kuwait' ? 'selected' : ''}>Kuwait</option>
                        <option value="India" ${child.nationality === 'India' ? 'selected' : ''}>India</option>
                        <option value="Pakistan" ${child.nationality === 'Pakistan' ? 'selected' : ''}>Pakistan</option>
                    </select>
                </div>
            </div>
        </div>
    `).join('');
}

function updateChild(childId, field, value) {
    const child = walkInData.children.find(c => c.id === childId);
    if (child) {
        child[field] = value;
        if (field === 'gender') renderChildren();
    }
}

function buildPeopleList() {
    walkInData.people = [];
    // Add guardian if they have DOB and gender
    if (walkInData.guardian.dob && walkInData.guardian.gender) {
        walkInData.people.push({
            id: 'guardian',
            name: walkInData.guardian.name,
            dob: walkInData.guardian.dob,
            gender: walkInData.guardian.gender,
            nationality: walkInData.guardian.nationality,
            type: 'guardian',
            isJoining: false,
            selectedPackageIds: []
        });
    }
    // Add children
    walkInData.children.forEach(child => {
        if (child.name && child.dob) {
            walkInData.people.push({
                id: child.id,
                name: child.name,
                dob: child.dob,
                gender: child.gender,
                nationality: child.nationality,
                type: 'child',
                isJoining: false,
                selectedPackageIds: []
            });
        }
    });
    renderPeoplePackages();
}

function calculateAge(dob) {
    const today = new Date();
    const birth = new Date(dob);
    let age = today.getFullYear() - birth.getFullYear();
    const m = today.getMonth() - birth.getMonth();
    if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) age--;
    return age;
}

function getEligiblePackages(person) {
    if (!person.dob) return [];
    const age = calculateAge(person.dob);
    return availablePackages.filter(pkg => {
        if (pkg.age_min && age < pkg.age_min) return false;
        if (pkg.age_max && age > pkg.age_max) return false;
        if (pkg.gender_restriction === 'male' && person.gender !== 'male') return false;
        if (pkg.gender_restriction === 'female' && person.gender !== 'female') return false;
        return true;
    });
}

function renderPeoplePackages() {
    const container = document.getElementById('peoplePackagesList');
    container.innerHTML = walkInData.people.map(person => {
        const age = calculateAge(person.dob);
        const eligiblePkgs = getEligiblePackages(person);
        return `
            <div class="bg-white border border-gray-200 rounded-xl p-4">
                <div class="flex items-start justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <input type="checkbox" id="joining_${person.id}" ${person.isJoining ? 'checked' : ''} onchange="togglePersonJoining('${person.id}')" class="w-5 h-5 text-purple-500 rounded border-gray-300 focus:ring-purple-500">
                        <div>
                            <h5 class="font-semibold text-gray-900">${person.name}</h5>
                            <p class="text-sm text-gray-500">${person.type === 'guardian' ? 'Guardian' : 'Child'} &bull; Age ${age} &bull; ${person.gender}</p>
                        </div>
                    </div>
                </div>
                <div id="packages_${person.id}" class="${person.isJoining ? '' : 'hidden'} pl-8 space-y-2">
                    <p class="text-sm font-medium text-gray-700 mb-2">Select Packages (optional):</p>
                    ${eligiblePkgs.length === 0 ? '<p class="text-sm text-gray-500 italic">No packages available for this age/gender</p>' :
                    eligiblePkgs.map(pkg => `
                        <div onclick="togglePackageForPerson('${person.id}', '${pkg.id}')" class="flex items-start gap-3 p-3 border ${person.selectedPackageIds.includes(pkg.id) ? 'border-purple-500 bg-purple-50' : 'border-gray-200'} rounded-lg cursor-pointer hover:border-purple-400 transition-colors">
                            <input type="checkbox" ${person.selectedPackageIds.includes(pkg.id) ? 'checked' : ''} class="mt-1 w-4 h-4 text-purple-500 rounded">
                            <div class="flex-1">
                                <div class="flex items-center justify-between">
                                    <span class="font-medium text-gray-900">${pkg.name}</span>
                                    <span class="text-sm font-semibold text-purple-600">${currency} ${parseFloat(pkg.price).toFixed(2)}</span>
                                </div>
                                ${pkg.description ? `<p class="text-sm text-gray-500 mt-1">${pkg.description}</p>` : ''}
                            </div>
                        </div>
                    `).join('')}
                </div>
            </div>
        `;
    }).join('');
    calculateTotals();
}

function togglePersonJoining(personId) {
    const person = walkInData.people.find(p => p.id === personId);
    if (person) {
        person.isJoining = !person.isJoining;
        if (!person.isJoining) person.selectedPackageIds = [];
        renderPeoplePackages();
    }
}

function togglePackageForPerson(personId, packageId) {
    const person = walkInData.people.find(p => p.id === personId);
    if (person) {
        const idx = person.selectedPackageIds.indexOf(packageId);
        if (idx > -1) person.selectedPackageIds.splice(idx, 1);
        else person.selectedPackageIds.push(packageId);
        renderPeoplePackages();
    }
}

function setDiscountType(type) {
    walkInData.discountType = type;
    document.getElementById('discountPercent').classList.toggle('bg-purple-500', type === 'percentage');
    document.getElementById('discountPercent').classList.toggle('text-white', type === 'percentage');
    document.getElementById('discountPercent').classList.toggle('text-gray-700', type !== 'percentage');
    document.getElementById('discountFixed').classList.toggle('bg-purple-500', type === 'fixed');
    document.getElementById('discountFixed').classList.toggle('text-white', type === 'fixed');
    document.getElementById('discountFixed').classList.toggle('text-gray-700', type !== 'fixed');
    calculateTotals();
}

function calculateTotals() {
    const joiningPeople = walkInData.people.filter(p => p.isJoining);
    const memberCount = joiningPeople.length;
    const enrollmentTotal = enrollmentFee * memberCount;
    let packagesTotal = 0;
    joiningPeople.forEach(person => {
        person.selectedPackageIds.forEach(pkgId => {
            const pkg = availablePackages.find(p => p.id == pkgId);
            if (pkg) packagesTotal += parseFloat(pkg.price);
        });
    });
    const subtotal = enrollmentTotal + packagesTotal;
    walkInData.discountValue = parseFloat(document.getElementById('discountValue')?.value || 0);
    let discount = 0;
    if (walkInData.discountValue > 0) {
        discount = walkInData.discountType === 'percentage' ? (subtotal * walkInData.discountValue / 100) : walkInData.discountValue;
    }
    const afterDiscount = subtotal - discount;
    const vat = afterDiscount * (vatPercentage / 100);
    const grandTotal = afterDiscount + vat;

    document.getElementById('memberCountText').textContent = memberCount;
    document.getElementById('enrollmentFeeText').textContent = `${currency} ${enrollmentTotal.toFixed(2)}`;
    document.getElementById('packagesTotalText').textContent = `${currency} ${packagesTotal.toFixed(2)}`;
    document.getElementById('subtotalText').textContent = `${currency} ${subtotal.toFixed(2)}`;
    document.getElementById('discountRow').style.display = discount > 0 ? 'flex' : 'none';
    document.getElementById('discountText').textContent = `-${currency} ${discount.toFixed(2)}`;
    if (document.getElementById('vatText')) document.getElementById('vatText').textContent = `${currency} ${vat.toFixed(2)}`;
    document.getElementById('grandTotalText').textContent = `${currency} ${grandTotal.toFixed(2)}`;
    document.getElementById('finalAmountText').textContent = `${currency} ${grandTotal.toFixed(2)}`;

    return { enrollmentTotal, packagesTotal, subtotal, discount, vat, grandTotal, memberCount };
}

function renderRegistrationSummary() {
    const joiningPeople = walkInData.people.filter(p => p.isJoining);
    const container = document.getElementById('registrationSummary');
    container.innerHTML = joiningPeople.map(person => {
        const personPkgs = person.selectedPackageIds.map(id => availablePackages.find(p => p.id == id)).filter(Boolean);
        const personTotal = enrollmentFee + personPkgs.reduce((s, pkg) => s + parseFloat(pkg.price), 0);
        return `
            <div class="border-b border-gray-100 pb-4 last:border-0 last:pb-0">
                <div class="flex items-center justify-between mb-2">
                    <div>
                        <p class="font-medium text-gray-900">${person.name}</p>
                        <p class="text-xs text-gray-500">${person.type === 'guardian' ? 'Guardian' : 'Child'}</p>
                    </div>
                    <p class="font-semibold text-gray-900">${currency} ${personTotal.toFixed(2)}</p>
                </div>
                <div class="pl-4 space-y-1 text-sm">
                    <div class="flex justify-between text-gray-500">
                        <span>Enrollment Fee</span>
                        <span>${currency} ${enrollmentFee.toFixed(2)}</span>
                    </div>
                    ${personPkgs.length === 0 ? '<p class="text-gray-400 italic">No packages selected</p>' : personPkgs.map(pkg => `
                        <div class="flex justify-between text-gray-500">
                            <span>${pkg.name}</span>
                            <span>${currency} ${parseFloat(pkg.price).toFixed(2)}</span>
                        </div>
                    `).join('')}
                </div>
            </div>
        `;
    }).join('');
}

function validateStep1() {
    const g = walkInData.guardian;
    g.name = document.getElementById('walkIn_name').value.trim();
    g.email = document.getElementById('walkIn_email').value.trim();
    g.password = document.getElementById('walkIn_password').value;
    g.phone = document.getElementById('walkIn_phone').value.trim();
    g.countryCode = document.getElementById('walkIn_countryCode').value;
    g.dob = document.getElementById('walkIn_dob').value;
    g.nationality = document.getElementById('walkIn_nationality').value;
    g.address = document.getElementById('walkIn_address').value.trim();

    if (!g.name) { showToast('Please enter full name', 'warning'); return false; }
    if (!g.email) { showToast('Please enter email', 'warning'); return false; }
    if (!g.password || g.password.length < 6) { showToast('Password must be at least 6 characters', 'warning'); return false; }
    if (!g.phone) { showToast('Please enter phone number', 'warning'); return false; }
    if (!g.dob) { showToast('Please enter date of birth', 'warning'); return false; }
    if (!g.gender) { showToast('Please select gender', 'warning'); return false; }
    if (!g.nationality) { showToast('Please select nationality', 'warning'); return false; }
    return true;
}

function validateStep2() {
    if (walkInData.isGuardian) {
        for (const child of walkInData.children) {
            if (!child.name) { showToast('Please enter name for all children', 'warning'); return false; }
            if (!child.dob) { showToast('Please enter date of birth for all children', 'warning'); return false; }
            if (!child.nationality) { showToast('Please select nationality for all children', 'warning'); return false; }
        }
    }
    return true;
}

function validateStep3() {
    const joiningPeople = walkInData.people.filter(p => p.isJoining);
    if (joiningPeople.length === 0) {
        showToast('Please select at least one person to register', 'warning');
        return false;
    }
    return true;
}

function walkInNextStep() {
    if (walkInStep === 1 && !validateStep1()) return;
    if (walkInStep === 2 && !validateStep2()) return;
    if (walkInStep === 3 && !validateStep3()) return;

    if (walkInStep === 2) buildPeopleList();
    if (walkInStep === 3) renderRegistrationSummary();

    walkInStep++;
    updateWalkInStepUI();
}

function walkInPrevStep() {
    if (walkInStep > 1) {
        walkInStep--;
        updateWalkInStepUI();
    }
}

async function submitWalkInRegistration() {
    const btn = document.getElementById('walkInSubmitBtn');
    btn.disabled = true;
    btn.innerHTML = '<svg class="w-5 h-5 mr-2 inline animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Processing...';

    try {
        const joiningPeople = walkInData.people.filter(p => p.isJoining);
        const formData = {
            guardian: walkInData.guardian,
            people: joiningPeople,
            discount_type: walkInData.discountType,
            discount_value: walkInData.discountValue
        };

        const res = await fetch(`/admin/club/${clubId}/walk-in-registration`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify(formData)
        });

        if (res.ok) {
            showToast('Registration completed successfully!', 'success');
            closeModal('walkInModal');
            location.reload();
        } else {
            const data = await res.json();
            throw new Error(data.message || 'Registration failed');
        }
    } catch (error) {
        showToast(error.message || 'Error completing registration', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<svg class="w-5 h-5 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Confirm & Register';
    }
}

function showToast(msg, type = 'info') {
    const colors = { success: 'bg-green-500', error: 'bg-red-500', warning: 'bg-yellow-500', info: 'bg-blue-500' };
    const toast = document.createElement('div');
    toast.className = `fixed top-4 right-4 z-[9999] px-6 py-3 rounded-lg text-white font-medium shadow-lg ${colors[type]} animate-fade-in`;
    toast.textContent = msg;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}
</script>
@endpush

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

if (!function_exists('getNextBirthdayCountdown')) {
    function getNextBirthdayCountdown($dateOfBirth) {
        $today = \Carbon\Carbon::now();
        $birthDate = \Carbon\Carbon::parse($dateOfBirth);
        $nextBirthday = \Carbon\Carbon::create($today->year, $birthDate->month, $birthDate->day);
        if ($nextBirthday->lt($today)) $nextBirthday->addYear();
        $days = $today->diffInDays($nextBirthday);
        return floor($days / 30) . "m " . ($days % 30) . "d";
    }
}
@endphp
