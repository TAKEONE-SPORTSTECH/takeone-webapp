@extends('layouts.app')

@php
    function calculateTimeDifference($date1, $date2) {
        $diff = $date1->diff($date2);
        $parts = [];

        if ($diff->y > 0) $parts[] = $diff->y . ' year' . ($diff->y > 1 ? 's' : '');
        if ($diff->m > 0) $parts[] = $diff->m . ' month' . ($diff->m > 1 ? 's' : '');
        if ($diff->d > 0) $parts[] = $diff->d . ' day' . ($diff->d > 1 ? 's' : '');

        return implode(' ', $parts) ?: 'Same day';
    }
@endphp

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">

    <!-- Header -->
    <div class="flex justify-between items-center mb-4">
        <div>
            <h2 class="font-bold mb-1 text-xl">Member Profile</h2>
            <p class="text-gray-500-foreground mb-0">Comprehensive member information and analytics</p>
        </div>
        <div>
            <button onclick="window.history.back()" class="border border-primary text-primary bg-transparent px-4 py-2 rounded-md text-sm font-medium hover:bg-primary hover:text-white transition-colors">
                <i class="bi bi-arrow-left mr-2"></i>Back
            </button>
        </div>
    </div>

    <!-- Profile Card -->
    <div class="bg-white rounded-xl shadow-sm mb-4">
        <div class="flex flex-col sm:flex-row">
            <!-- Profile Picture -->
            <div class="w-full sm:w-[180px] sm:min-h-[250px] overflow-hidden rounded-t-xl sm:rounded-t-none sm:rounded-l-xl flex-shrink-0" style="min-height: 150px;">
                @if($relationship->dependent->profile_picture)
                    <img id="member-profile-pic" src="{{ asset('storage/' . $relationship->dependent->profile_picture) }}?v={{ $relationship->dependent->updated_at->timestamp }}" alt="{{ $relationship->dependent->full_name }}" class="w-full h-full" style="object-fit: cover;">
                @endif
                <div id="member-profile-placeholder" class="w-full h-full flex items-center justify-center text-white font-bold" style="font-size: 3rem; background: linear-gradient(135deg, {{ $relationship->dependent->gender === 'Male' ? '#0d6efd 0%, #0a58ca 100%' : '#d63384 0%, #a61e4d 100%' }}); {{ $relationship->dependent->profile_picture ? 'display:none;' : '' }}">
                    {{ strtoupper(substr($relationship->dependent->full_name, 0, 1)) }}
                </div>
            </div>

            <!-- Profile Info -->
            <div class="flex-1 p-4">
                <div class="flex justify-between items-start mb-2">
                    <h3 class="font-bold mb-0" id="profile-display-name">{{ $relationship->dependent->full_name }}</h3>
                    @if($relationship->relationship_type == 'self' || Auth::id() == $relationship->guardian_user_id)
                        <div x-data="{ open: false }" class="relative">
                            <button @click="open = !open" class="bg-primary text-white px-4 py-2 rounded-full text-sm font-medium hover:bg-primary/90 transition-colors" type="button">
                                <i class="bi bi-lightning mr-1"></i>Action
                            </button>
                            <ul x-show="open" x-cloak @click.outside="open = false"
                                class="absolute right-0 mt-1 bg-white py-1 z-50 list-none w-64 max-w-[calc(100vw-2rem)]"
                                style="border: 1px solid rgba(0,0,0,.1); border-radius: 0.625rem; box-shadow: 0 0.5rem 1.5rem rgba(0,0,0,.12);">
                                <li><a class="flex items-center gap-2 py-2 px-4 text-gray-800 no-underline whitespace-nowrap hover:bg-gray-50 text-sm" href="#"><i class="bi bi-trophy text-amber-500" style="width:16px;text-align:center"></i>Add Achievement</a></li>
                                <li><a class="flex items-center gap-2 py-2 px-4 text-gray-800 no-underline whitespace-nowrap hover:bg-gray-50 text-sm" href="#"><i class="bi bi-calendar-check text-green-600" style="width:16px;text-align:center"></i>Add Attendance Record</a></li>
                                <li><a class="flex items-center gap-2 py-2 px-4 text-gray-800 no-underline whitespace-nowrap hover:bg-gray-50 text-sm" href="#"><i class="bi bi-calendar-event text-blue-500" style="width:16px;text-align:center"></i>Add Event Participation</a></li>
                                <li><a class="flex items-center gap-2 py-2 px-4 text-gray-800 no-underline whitespace-nowrap hover:bg-gray-50 text-sm" href="#" @click="$dispatch('open-health-update-modal'); open = false"><i class="bi bi-heart-pulse text-red-500" style="width:16px;text-align:center"></i>Add Health Update</a></li>
                                <li><a class="flex items-center gap-2 py-2 px-4 text-gray-800 no-underline whitespace-nowrap hover:bg-gray-50 text-sm" href="#" @click="$dispatch('open-tournament-modal'); open = false"><i class="bi bi-award text-amber-500" style="width:16px;text-align:center"></i>Add Tournament Participation</a></li>
                                <li><a class="flex items-center gap-2 py-2 px-4 text-gray-800 no-underline whitespace-nowrap hover:bg-gray-50 text-sm" href="#" @click="$dispatch('open-profile-modal'); open = false"><i class="bi bi-pencil text-gray-500" style="width:16px;text-align:center"></i>Edit Info</a></li>
                                <li><a class="flex items-center gap-2 py-2 px-4 text-gray-800 no-underline whitespace-nowrap hover:bg-gray-50 text-sm" href="#"><i class="bi bi-bullseye text-blue-600" style="width:16px;text-align:center"></i>Set a Goal</a></li>
                                @if($canResetPassword)
                                <li><a class="flex items-center gap-2 py-2 px-4 text-gray-800 no-underline whitespace-nowrap hover:bg-gray-50 text-sm" href="#" @click="$dispatch('open-reset-password-modal'); open = false"><i class="bi bi-key text-amber-600" style="width:16px;text-align:center"></i>Reset Password</a></li>
                                @endif
                                <li><hr class="my-1 border-0 border-t border-gray-100"></li>
                                <li><a class="flex items-center gap-2 py-2 px-4 text-red-600 no-underline whitespace-nowrap hover:bg-red-50 text-sm" href="#" @click="$dispatch('open-delete-account-modal'); open = false"><i class="bi bi-trash" style="width:16px;text-align:center"></i>Delete Account</a></li>
                            </ul>
                        </div>
                    @else
                        <button class="bg-primary text-white px-3 py-1.5 rounded-full text-xs font-medium hover:bg-primary/90 transition-colors">
                            <i class="bi bi-person-plus mr-1"></i>Follow
                        </button>
                    @endif
                </div>
                            <p id="profile-display-motto" class="text-gray-500 italic mb-3"@if(!$relationship->dependent->motto) style="display:none"@endif>@if($relationship->dependent->motto)"{{ $relationship->dependent->motto }}"@endif</p>

                            <!-- Achievement Badges -->
                            <div class="flex gap-2 mb-3 flex-wrap">
                                <a href="#" class="border border-gray-300 bg-white rounded px-2 py-1 no-underline achievement-badge" style="font-size: 1rem;" data-medal-type="special" onclick="filterTournamentsByMedal('special')">🏆 <span class="font-semibold text-gray-900">{{ $awardCounts['special'] }}</span></a>
                                <a href="#" class="border border-gray-300 bg-white rounded px-2 py-1 no-underline achievement-badge" style="font-size: 1rem;" data-medal-type="1st" onclick="filterTournamentsByMedal('1st')">🥇 <span class="font-semibold text-gray-900">{{ $awardCounts['1st'] }}</span></a>
                                <a href="#" class="border border-gray-300 bg-white rounded px-2 py-1 no-underline achievement-badge" style="font-size: 1rem;" data-medal-type="2nd" onclick="filterTournamentsByMedal('2nd')">🥈 <span class="font-semibold text-gray-900">{{ $awardCounts['2nd'] }}</span></a>
                                <a href="#" class="border border-gray-300 bg-white rounded px-2 py-1 no-underline achievement-badge" style="font-size: 1rem;" data-medal-type="3rd" onclick="filterTournamentsByMedal('3rd')">🥉 <span class="font-semibold text-gray-900">{{ $awardCounts['3rd'] }}</span></a>
                                <a href="#goals" class="border border-gray-300 bg-white rounded px-2 py-1 no-underline" style="font-size: 1rem;" onclick="document.getElementById('goals-tab').click();">🎯 <span class="font-semibold text-gray-900">{{ $activeGoalsCount + $completedGoalsCount }}</span></a>
                                <a href="#" class="border border-gray-300 bg-white rounded px-2 py-1 no-underline" style="font-size: 1rem;">⭐ <span class="font-semibold text-gray-900">{{ $totalAffiliations }}</span></a>
                            </div>

                            <!-- Status Badges -->
                            <div id="profile-status-row" class="flex gap-3 mb-3 items-center flex-wrap">
                                <span class="text-gray-500 text-sm">
                                    <span class="font-semibold text-gray-900 nationality-display" data-iso3="{{ $relationship->dependent->nationality }}">{{ $relationship->dependent->nationality }}</span>
                                </span>
                                <span class="text-gray-500 text-sm">
                                    <i class="bi bi-{{ $relationship->dependent->gender === 'Male' ? 'gender-male' : 'gender-female' }} mr-1" style="font-size: 1.1rem; color: {{ $relationship->dependent->gender === 'Male' ? '#17a2b8' : '#6f42c1' }};"></i>
                                    <span class="font-semibold text-gray-900">{{ $relationship->dependent->gender === 'Male' ? 'Male' : 'Female' }}</span>
                                </span>
                                <span id="profile-marital-wrap" class="text-gray-500 text-sm"@if(!$relationship->dependent->marital_status) style="display:none"@endif>
                                    <i class="bi bi-heart mr-1" style="color: #e91e63;"></i>
                                    <span class="font-semibold text-gray-900" data-profile-marital>{{ ucfirst($relationship->dependent->marital_status ?? '') }}</span>
                                </span>
                                <span class="text-gray-500 text-sm">
                                    <i class="bi bi-calendar-event mr-1"></i>
                                    Age <span class="font-semibold text-gray-900" data-profile-age>{{ $relationship->dependent->age }}</span>
                                </span>
                                <span id="profile-blood-type-wrap" class="text-gray-500 text-sm"@if(!$relationship->dependent->blood_type) style="display:none"@endif>
                                    <i class="bi bi-droplet-fill text-red-600 mr-1"></i>
                                    <span class="font-semibold text-gray-900" data-profile-blood-type>{{ $relationship->dependent->blood_type ?? '' }}</span>
                                </span>
                                <span class="text-gray-500 text-sm">
                                    @php
                                        $horoscopeSymbols = [
                                            'Aries' => '♈',
                                            'Taurus' => '♉',
                                            'Gemini' => '♊',
                                            'Cancer' => '♋',
                                            'Leo' => '♌',
                                            'Virgo' => '♍',
                                            'Libra' => '♎',
                                            'Scorpio' => '♏',
                                            'Sagittarius' => '♐',
                                            'Capricorn' => '♑',
                                            'Aquarius' => '♒',
                                            'Pisces' => '♓'
                                        ];
                                        $horoscope = $relationship->dependent->horoscope ?? 'N/A';
                                        $symbol = $horoscopeSymbols[$horoscope] ?? '';
                                    @endphp
                                    {{ $symbol }} <span class="font-semibold text-gray-900">{{ $horoscope }}</span>
                                </span>
                                @if($relationship->dependent->birthdate)
                                <span class="text-gray-500 text-sm">
                                    🎂
                                    <span class="font-semibold text-gray-900">
                                        @php
                                            $nextBirthday = $relationship->dependent->birthdate->copy()->year(now()->year);
                                            if ($nextBirthday->isPast()) {
                                                $nextBirthday->addYear();
                                            }
                                            $diff = now()->diff($nextBirthday);
                                            $parts = [];
                                            if ($diff->m > 0) $parts[] = $diff->m . ' month' . ($diff->m > 1 ? 's' : '');
                                            if ($diff->d > 0) $parts[] = $diff->d . ' day' . ($diff->d > 1 ? 's' : '');
                                            echo !empty($parts) ? implode(' ', $parts) : 'Today!';
                                        @endphp
                                    </span>
                                </span>
                                @endif
                                <span class="text-gray-500 text-sm">
                                    <i class="bi bi-check-circle-fill text-green-600 mr-1"></i>
                                    <span class="font-semibold text-green-600">Active</span>
                                </span>
                                <span class="text-gray-500 text-sm">
                                    <i class="bi bi-calendar-check mr-1"></i>
                                    Joined <span class="font-semibold text-gray-900">{{ $relationship->dependent->created_at->format('F Y') }}</span>
                                </span>
                            </div>

                            <!-- Social Media Icons -->
                            <div id="profile-social-row" class="flex gap-2 flex-wrap"@if(!$relationship->dependent->social_links || count($relationship->dependent->social_links) === 0) style="display:none"@endif>
                                    @php
                                        $socialLinks = $relationship->dependent->social_links ?? [];
                                        if (is_array($socialLinks)) ksort($socialLinks); // Sort by platform name

                                        $socialIcons = [
                                            'facebook' => 'bi-facebook',
                                            'twitter' => 'X', // Special case for Twitter/X
                                            'instagram' => 'bi-instagram',
                                            'linkedin' => 'bi-linkedin',
                                            'youtube' => 'bi-youtube',
                                            'tiktok' => 'bi-tiktok',
                                            'snapchat' => 'bi-snapchat',
                                            'whatsapp' => 'bi-whatsapp',
                                            'telegram' => 'bi-telegram',
                                            'discord' => 'bi-discord',
                                            'reddit' => 'bi-reddit',
                                            'pinterest' => 'bi-pinterest',
                                            'twitch' => 'bi-twitch',
                                            'github' => 'bi-github',
                                            'spotify' => 'bi-spotify',
                                            'skype' => 'bi-skype',
                                            'slack' => 'bi-slack',
                                            'medium' => 'bi-medium',
                                            'vimeo' => 'bi-vimeo',
                                            'messenger' => 'bi-messenger',
                                            'wechat' => 'bi-wechat',
                                            'line' => 'bi-line',
                                        ];
                                        $socialTitles = [
                                            'facebook' => 'Facebook',
                                            'twitter' => 'Twitter/X',
                                            'instagram' => 'Instagram',
                                            'linkedin' => 'LinkedIn',
                                            'youtube' => 'YouTube',
                                            'tiktok' => 'TikTok',
                                            'snapchat' => 'Snapchat',
                                            'whatsapp' => 'WhatsApp',
                                            'telegram' => 'Telegram',
                                            'discord' => 'Discord',
                                            'reddit' => 'Reddit',
                                            'pinterest' => 'Pinterest',
                                            'twitch' => 'Twitch',
                                            'github' => 'GitHub',
                                            'spotify' => 'Spotify',
                                            'skype' => 'Skype',
                                            'slack' => 'Slack',
                                            'medium' => 'Medium',
                                            'vimeo' => 'Vimeo',
                                            'messenger' => 'Messenger',
                                            'wechat' => 'WeChat',
                                            'line' => 'Line',
                                        ];
                                    @endphp
                                    @foreach($socialLinks as $platform => $url)
                                        @if(!empty($url) && isset($socialIcons[$platform]))
                                            <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" class="w-8 h-8 rounded-full border border-gray-300 flex items-center justify-center text-gray-600 hover:bg-gray-100 transition-colors" title="{{ $socialTitles[$platform] ?? ucfirst($platform) }}">
                                                @if($platform === 'twitter')
                                                    <span style="font-weight: bold; font-size: 1.2rem;">{{ $socialIcons[$platform] }}</span>
                                                @else
                                                    <i class="bi {{ $socialIcons[$platform] }}"></i>
                                                @endif
                                            </a>
                                        @endif
                                    @endforeach
                            </div>
            </div>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div x-data="{ activeTab: (window.location.hash ? window.location.hash.substring(1) : 'overview') }">
        <div class="overflow-x-auto -mx-4 px-4 md:mx-0 md:px-0">
        <ul class="nav nav-tabs nav-fill mb-4 flex-nowrap min-w-max md:min-w-0 md:flex-wrap" id="profileTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button @click="activeTab = 'overview'" :class="{ 'active': activeTab === 'overview' }" class="nav-link text-dark" id="overview-tab" type="button" role="tab">
                    <i class="bi bi-eye me-2"></i>Overview
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button @click="activeTab = 'attendance'" :class="{ 'active': activeTab === 'attendance' }" class="nav-link text-dark" id="attendance-tab" type="button" role="tab">
                    <i class="bi bi-calendar-check me-2"></i>Attendance
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button @click="activeTab = 'health'" :class="{ 'active': activeTab === 'health' }" class="nav-link text-dark" id="health-tab" type="button" role="tab">
                    <i class="bi bi-heart-pulse me-2"></i>Health
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button @click="activeTab = 'goals'" :class="{ 'active': activeTab === 'goals' }" class="nav-link text-dark" id="goals-tab" type="button" role="tab">
                    <i class="bi bi-bullseye me-2"></i>Goals
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button @click="activeTab = 'affiliations'" :class="{ 'active': activeTab === 'affiliations' }" class="nav-link text-dark" id="affiliations-tab" type="button" role="tab">
                    <i class="bi bi-diagram-3 me-2"></i>Affiliations
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button @click="activeTab = 'tournaments'" :class="{ 'active': activeTab === 'tournaments' }" class="nav-link text-dark" id="tournaments-tab" type="button" role="tab">
                    <i class="bi bi-award me-2"></i>Tournaments
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button @click="activeTab = 'events'" :class="{ 'active': activeTab === 'events' }" class="nav-link text-dark" id="events-tab" type="button" role="tab">
                    <i class="bi bi-calendar-event me-2"></i>Events
                </button>
            </li>
        </ul>
        </div>{{-- end overflow-x-auto tab wrapper --}}

        <!-- Tab Content -->
        <div id="profileTabsContent">
            <!-- Overview Tab -->
            <div x-show="activeTab === 'overview'" x-transition id="overview" role="tabpanel">
            <!-- Profile Statistics and Revenue Chart Row -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
                <!-- Profile Statistics -->
                <div>
                    <div class="bg-white rounded-xl shadow-sm h-full">
                        <div class="p-4">
                            <div class="flex items-center mb-2">
                                <i class="bi bi-bar-chart-line text-primary mr-2"></i>
                                <h5 class="mb-0 font-bold">Profile Statistics</h5>
                            </div>
                            <p class="text-gray-500 text-sm mb-4">Key performance metrics and milestones</p>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <!-- Total Sessions -->
                                <div>
                            <div class="flex items-center gap-3 p-3 bg-gray-100 rounded">
                                <div class="rounded-full flex items-center justify-center" style="width: 48px; height: 48px; background-color: #6f42c1;">
                                    <i class="bi bi-people-fill text-white"></i>
                                </div>
                                <div class="flex-1">
                                    <div class="small text-gray-500 mb-1">Total Sessions</div>
                                    <div class="text-xl font-bold mb-2">127</div>
                                    <div class="h-2 bg-gray-200 rounded-full overflow-hidden" style="height: 4px; background-color: #e9ecef;">
                                        <div class="h-full bg-primary transition-all" role="progressbar" style="width: 85%; background: linear-gradient(90deg, #6f42c1 0%, #8b5cf6 100%);"></div>
                                    </div>
                                    <small class="text-gray-500" style="font-size: 0.75rem;">Sessions completed this year</small>
                                </div>
                            </div>
                        </div>



                                <!-- Attendance Rate -->
                                <div>
                            <div class="flex items-center gap-3 p-3 bg-gray-100 rounded">
                                <div class="rounded-full flex items-center justify-center" style="width: 48px; height: 48px; background-color: #3b82f6;">
                                    <i class="bi bi-graph-up-arrow text-white"></i>
                                </div>
                                <div class="flex-1">
                                    <div class="small text-gray-500 mb-1">Attendance Rate</div>
                                    <div class="text-xl font-bold mb-2">85%</div>
                                    <div class="h-2 bg-gray-200 rounded-full overflow-hidden" style="height: 4px; background-color: #e9ecef;">
                                        <div class="h-full bg-primary transition-all" role="progressbar" style="width: 85%; background: linear-gradient(90deg, #6f42c1 0%, #10b981 100%);"></div>
                                    </div>
                                    <small class="text-gray-500" style="font-size: 0.75rem;">Average session attendance</small>
                                </div>
                            </div>
                        </div>



                                <!-- Achievements -->
                                <div>
                            <div class="flex items-center gap-3 p-3 bg-gray-100 rounded">
                                <div class="rounded-full flex items-center justify-center" style="width: 48px; height: 48px; background-color: #8b5cf6;">
                                    <i class="bi bi-trophy-fill text-white"></i>
                                </div>
                                <div class="flex-1">
                                    <div class="small text-gray-500 mb-1">Achievements</div>
                                    <div class="text-xl font-bold mb-2">8</div>
                                    <div class="h-2 bg-gray-200 rounded-full overflow-hidden" style="height: 4px; background-color: #e9ecef;">
                                        <div class="h-full bg-primary transition-all" role="progressbar" style="width: 40%; background: linear-gradient(90deg, #6f42c1 0%, #10b981 100%);"></div>
                                    </div>
                                    <small class="text-gray-500" style="font-size: 0.75rem;">Total badges earned</small>
                                </div>
                            </div>
                        </div>

                                <!-- Goal Completion -->
                                <div>
                            <div class="flex items-center gap-3 p-3 bg-gray-100 rounded">
                                <div class="rounded-full flex items-center justify-center" style="width: 48px; height: 48px; background-color: #10b981;">
                                    <i class="bi bi-check-circle-fill text-white"></i>
                                </div>
                                <div class="flex-1">
                                    <div class="small text-gray-500 mb-1">Goal Completion</div>
                                    <div class="text-xl font-bold mb-2">75%</div>
                                    <div class="h-2 bg-gray-200 rounded-full overflow-hidden" style="height: 4px; background-color: #e9ecef;">
                                        <div class="h-full bg-primary transition-all" role="progressbar" style="width: 75%; background: linear-gradient(90deg, #6f42c1 0%, #10b981 100%);"></div>
                                    </div>
                                    <small class="text-gray-500" style="font-size: 0.75rem;">Current goals achieved</small>
                                </div>
                            </div>
                        </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Self Investment Chart -->
                <div>
                    <div class="bg-white rounded-xl shadow-sm h-full">
                        <div class="p-4">
                            <div class="flex items-center mb-2">
                                <i class="bi bi-bar-chart-line text-primary mr-2"></i>
                                <h5 class="mb-0 font-bold">Self Investment Chart</h5>
                            </div>
                            <p class="text-gray-500 text-sm mb-4">Self investment analytics over time</p>

                            <div class="flex items-center justify-center" style="min-height: 300px;">
                                <div class="text-center">
                                    <i class="bi bi-graph-up text-gray-500" style="font-size: 3rem;"></i>
                                    <p class="text-gray-500 mt-3 mb-1">Revenue chart visualization coming soon...</p>
                                    <small class="text-gray-500">Chart will display revenue trends over time</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Complete Payment & Revenue History -->
            <div class="bg-white rounded-xl shadow-sm mb-4">
                <div class="p-4">
                    <div class="flex items-center mb-1">
                        <i class="bi bi-receipt text-primary mr-2"></i>
                        <h5 class="mb-0 font-bold">Payment History</h5>
                    </div>
                    <p class="text-gray-500 text-sm mb-4">All package payments and revenue transactions</p>

                    <div class="overflow-x-auto rounded-lg border border-gray-100">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="bg-gray-50 border-b border-gray-200">
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap">Date</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap">Club / Item</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap">Amount</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap">Status</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap">Receipt</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse($invoices as $invoice)
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <span class="text-sm text-gray-700">{{ $invoice->created_at->format('M j, Y') }}</span>
                                        <div class="text-xs text-gray-400">{{ $invoice->created_at->format('g:i A') }}</div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="text-sm font-medium text-gray-800">{{ $invoice->tenant->club_name ?? 'N/A' }}</span>
                                        <div class="text-xs text-primary">Invoice</div>
                                    </td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap">
                                        <span class="text-sm font-semibold {{ $invoice->status == 'paid' ? 'text-green-600' : 'text-amber-600' }}">
                                            {{ $invoice->amount }} BHD
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        @if($invoice->status == 'paid')
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">
                                                <i class="bi bi-check-circle-fill"></i> Paid
                                            </span>
                                        @elseif($invoice->status == 'due')
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-700">
                                                <i class="bi bi-clock"></i> Due
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">
                                                {{ ucfirst($invoice->status) }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-center whitespace-nowrap">
                                        <a href="{{ route('bills.receipt', $invoice->id) }}" target="_blank"
                                           class="inline-flex items-center justify-center w-7 h-7 rounded-lg text-primary hover:bg-primary/10 transition-colors" title="View Receipt">
                                            <i class="bi bi-file-earmark-text"></i>
                                        </a>
                                        <a href="{{ route('bills.receipt', $invoice->id) }}?download=1" download
                                           class="inline-flex items-center justify-center w-7 h-7 rounded-lg text-gray-500 hover:bg-gray-100 transition-colors ml-1" title="Download">
                                            <i class="bi bi-download"></i>
                                        </a>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-10 text-center">
                                        <i class="bi bi-receipt text-gray-200" style="font-size:2.5rem;"></i>
                                        <p class="text-gray-400 text-sm mt-2 mb-0">No payment records found</p>
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            @if($relationship->relationship_type == 'self' || Auth::id() == $relationship->guardian_user_id || in_array($relationship->relationship_type, ['admin_view']))
            <!-- Emergency Contacts & Documents row -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">

                <!-- Emergency Contacts -->
                <div class="bg-white rounded-xl shadow-sm">
                    <div class="p-4">
                        <div class="flex items-center mb-2">
                            <i class="bi bi-telephone-fill text-red-500 mr-2"></i>
                            <h5 class="mb-0 font-bold">Emergency Contacts</h5>
                        </div>
                        <p class="text-gray-500 text-sm mb-4">People to contact in case of an emergency</p>

                        <div id="emergency-contacts-list">
                        @if($relationship->dependent->emergency_contacts && count($relationship->dependent->emergency_contacts) > 0)
                            <div class="flex flex-col gap-3">
                                @foreach($relationship->dependent->emergency_contacts as $contact)
                                <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                                    <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center flex-shrink-0">
                                        <i class="bi bi-person-fill text-red-500"></i>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="font-semibold text-sm text-gray-800">{{ $contact['name'] ?? '—' }}</div>
                                        <div class="text-xs text-gray-500">{{ ucfirst($contact['relationship'] ?? '') }}</div>
                                    </div>
                                    @if(!empty($contact['phone']))
                                    <a href="tel:{{ ($contact['phone_code'] ?? '') }}{{ $contact['phone'] }}"
                                       class="flex items-center gap-1 text-xs text-primary font-medium hover:underline flex-shrink-0">
                                        <i class="bi bi-telephone"></i>
                                        {{ ($contact['phone_code'] ?? '') }} {{ $contact['phone'] }}
                                    </a>
                                    @endif
                                </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-center py-6">
                                <i class="bi bi-telephone text-gray-300" style="font-size:2rem;"></i>
                                <p class="text-gray-400 text-sm mt-2 mb-0">No emergency contacts added</p>
                            </div>
                        @endif
                        </div>{{-- #emergency-contacts-list --}}
                    </div>
                </div>

                <!-- Identity Documents -->
                <div class="bg-white rounded-xl shadow-sm">
                    <div class="p-4">
                        <div class="flex items-center mb-2">
                            <i class="bi bi-file-earmark-person-fill text-primary mr-2"></i>
                            <h5 class="mb-0 font-bold">Identity Documents</h5>
                        </div>
                        <p class="text-gray-500 text-sm mb-4">Official ID and verification documents</p>

                        <div id="documents-list">
                        @if($relationship->dependent->documents && count($relationship->dependent->documents) > 0)
                            <div class="flex flex-col gap-3">
                                @foreach($relationship->dependent->documents as $doc)
                                <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                                    <div class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center flex-shrink-0">
                                        <i class="bi bi-card-text text-primary"></i>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="font-semibold text-sm text-gray-800">{{ $doc['type'] ?? '—' }}</div>
                                        <div class="text-xs text-gray-500 font-mono">{{ $doc['number'] ?? '' }}</div>
                                        @if(!empty($doc['uploaded_at']))
                                        <div class="text-xs text-gray-400">Uploaded {{ \Carbon\Carbon::parse($doc['uploaded_at'])->format('M j, Y') }}</div>
                                        @endif
                                    </div>
                                    @if(!empty($doc['file_path']))
                                    <a href="{{ asset('storage/' . $doc['file_path']) }}" target="_blank"
                                       class="flex-shrink-0 w-8 h-8 rounded-lg border border-primary text-primary flex items-center justify-center hover:bg-primary hover:text-white transition-colors" title="View Document">
                                        <i class="bi bi-eye" style="font-size:0.85rem;"></i>
                                    </a>
                                    @endif
                                </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-center py-6">
                                <i class="bi bi-file-earmark text-gray-300" style="font-size:2rem;"></i>
                                <p class="text-gray-400 text-sm mt-2 mb-0">No documents uploaded</p>
                            </div>
                        @endif
                        </div>{{-- #documents-list --}}
                    </div>
                </div>

            </div>
            @endif

        </div>

        <!-- Attendance Tab -->
        <div x-show="activeTab === 'attendance'" x-transition id="attendance" role="tabpanel">
            <div class="bg-white rounded-xl shadow-sm">
                <div class="p-4">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h1 class="h3 font-bold">Member Attendance</h1>
                            <p class="text-gray-500">Track your gym session attendance and performance</p>
                        </div>
                    </div>

                    <!-- Summary Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                        <div>
                            <div class="bg-white rounded-xl shadow-sm">
                                <div class="p-6 text-center">
                                    <div class="text-4xl font-bold text-green-600 mb-2">{{ $sessionsCompleted }}</div>
                                    <h6 class="text-lg font-semibold text-gray-500">Sessions Completed</h6>
                                </div>
                            </div>
                        </div>
                        <div>
                            <div class="bg-white rounded-xl shadow-sm">
                                <div class="p-6 text-center">
                                    <div class="text-4xl font-bold text-red-600 mb-2">{{ $noShows }}</div>
                                    <h6 class="text-lg font-semibold text-gray-500">No Shows</h6>
                                </div>
                            </div>
                        </div>
                        <div>
                            <div class="bg-white rounded-xl shadow-sm">
                                <div class="p-6 text-center">
                                    <div class="text-4xl font-bold text-primary mb-2">{{ $attendanceRate }}%</div>
                                    <h6 class="text-lg font-semibold text-gray-500">Attendance Rate</h6>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Attendance Table -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                        <div class="px-6 py-4 border-b border-gray-200 bg-gray-100">
                            <h6 class="text-lg font-semibold mb-0">Session History</h6>
                        </div>
                        <div class="p-6 p-0">
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead class="bg-gray-50 border-b border-gray-200">
                                        <tr>
                                            <th class="border-0 font-semibold">Date & Time</th>
                                            <th class="border-0 font-semibold">Session Type</th>
                                            <th class="border-0 font-semibold">Trainer Name</th>
                                            <th class="border-0 font-semibold">Status</th>
                                            <th class="border-0 font-semibold">Notes</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($attendanceRecords as $record)
                                        <tr>
                                            <td class="align-middle">
                                                <div class="font-semibold">{{ $record->session_datetime->format('M j, Y') }}</div>
                                                <small class="text-gray-500">{{ $record->session_datetime->format('g:i A') }}</small>
                                            </td>
                                            <td class="align-middle">{{ $record->session_type }}</td>
                                            <td class="align-middle">{{ $record->trainer_name }}</td>
                                            <td class="align-middle">
                                                @if($record->status === 'completed')
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Completed</span>
                                                @else
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">No Show</span>
                                                @endif
                                            </td>
                                            <td class="align-middle">
                                                <small class="text-gray-500">{{ $record->notes ?: '-' }}</small>
                                            </td>
                                        </tr>
                                        @empty
                                        <tr>
                                            <td colspan="5" class="text-center py-4">
                                                <i class="bi bi-calendar-check text-gray-500" style="font-size: 2rem;"></i>
                                                <p class="text-gray-500 mt-2 mb-0">No attendance records found</p>
                                            </td>
                                        </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Health Tab -->
        <div x-show="activeTab === 'health'" x-transition id="health" role="tabpanel">

            <!-- Chronic Health Conditions -->
            <div id="health-conditions-card" class="bg-white rounded-xl shadow-sm mb-4"@if(!$relationship->dependent->health_conditions || count($relationship->dependent->health_conditions) === 0) style="display:none"@endif>
                <div class="p-4">
                    <div class="flex items-center mb-2">
                        <i class="bi bi-clipboard2-pulse-fill text-amber-500 mr-2"></i>
                        <h5 class="mb-0 font-bold">Chronic Health Conditions</h5>
                    </div>
                    <p class="text-gray-500 text-sm mb-4">Known conditions that may affect training and health plans</p>
                    <div id="health-conditions-list" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                        @foreach($relationship->dependent->health_conditions ?? [] as $condition)
                        <div class="flex items-start gap-3 p-3 bg-amber-50 border border-amber-100 rounded-lg">
                            <div class="w-9 h-9 rounded-full bg-amber-100 flex items-center justify-center flex-shrink-0 mt-0.5">
                                <i class="bi bi-exclamation-circle-fill text-amber-500"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="font-semibold text-sm text-gray-800">{{ $condition['condition'] ?? '—' }}</div>
                                @if(!empty($condition['noted_at']))
                                <div class="text-xs text-gray-400 mt-0.5">Noted {{ \Carbon\Carbon::parse($condition['noted_at'])->format('M j, Y') }}</div>
                                @endif
                                @if(!empty($condition['notes']))
                                <div class="text-xs text-gray-500 mt-1">{{ $condition['notes'] }}</div>
                                @endif
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Health Tracking Header -->
            <div class="bg-white rounded-xl shadow-sm mb-4">
                <div class="p-4">
                    <div class="flex items-center mb-2">
                        <i class="bi bi-heart-pulse text-red-600 mr-2"></i>
                        <h5 class="mb-0 font-bold">Health Metrics Overview</h5>
                    </div>

                    <div class="flex justify-between items-center mb-4">
                        <p class="text-gray-500 text-sm mb-0">Monitor health metrics and progress over time</p>

                        @if($latestHealthRecord)
                            @php
                                $latestDate = $latestHealthRecord->recorded_at;
                                $now = \Carbon\Carbon::now();
                                $diff = $latestDate->diff($now);
                            @endphp

                            <div class="flex items-center gap-3">
                                <div class="flex items-center gap-2">
                                    <i class="bi bi-calendar-event text-primary"></i>
                                    <span class="font-semibold">Snapshot Date:</span>
                                    <span class="text-gray-500">{{ $latestDate->format('F j, Y') }}</span>
                                </div>
                                <div class="w-px h-6 bg-gray-300"></div>
                                <div class="flex items-center gap-2">
                                    <i class="bi bi-clock-history text-primary"></i>
                                    <span class="font-semibold">Time Since:</span>
                                    <span class="text-gray-500">
                                        @if($diff->y > 0)
                                            {{ $diff->y }} {{ $diff->y == 1 ? 'year' : 'years' }}
                                        @endif
                                        @if($diff->m > 0)
                                            {{ $diff->m }} {{ $diff->m == 1 ? 'month' : 'months' }}
                                        @endif
                                        @if($diff->d > 0)
                                            {{ $diff->d }} {{ $diff->d == 1 ? 'day' : 'days' }}
                                        @endif
                                        ago
                                    </span>
                                </div>
                            </div>
                        @else
                            <div class="text-gray-500 text-sm">No health records available</div>
                        @endif
                    </div>

                    <!-- Health Metrics Cards -->
                    <div class="grid grid-cols-2 md:grid-cols-6 gap-3">
                        @if($latestHealthRecord)
                            <!-- Weight -->
                            <div>
                                <div class="text-center p-3 bg-gray-100 rounded">
                                    <i class="bi bi-speedometer2 text-purple mb-2" style="font-size: 1.5rem; color: #8b5cf6;"></i>
                                    <div class="text-xl font-bold mb-0">{{ $latestHealthRecord->weight ?? 'N/A' }}</div>
                                    <small class="text-gray-500">Weight (kg)</small>
                                </div>
                            </div>

                            <!-- Body Fat -->
                            <div>
                                <div class="text-center p-3 bg-gray-100 rounded">
                                    <i class="bi bi-activity text-warning mb-2" style="font-size: 1.5rem;"></i>
                                    <div class="text-xl font-bold mb-0">{{ $latestHealthRecord->body_fat_percentage ?? 'N/A' }}%</div>
                                    <small class="text-gray-500">Body Fat</small>
                                </div>
                            </div>

                            <!-- Body Water -->
                            <div>
                                <div class="text-center p-3 bg-gray-100 rounded">
                                    <i class="bi bi-droplet text-info mb-2" style="font-size: 1.5rem;"></i>
                                    <div class="text-xl font-bold mb-0">{{ $latestHealthRecord->body_water_percentage ?? 'N/A' }}%</div>
                                    <small class="text-gray-500">Body Water</small>
                                </div>
                            </div>

                            <!-- Muscle Mass -->
                            <div>
                                <div class="text-center p-3 bg-gray-100 rounded">
                                    <i class="bi bi-heart text-green-600 mb-2" style="font-size: 1.5rem;"></i>
                                    <div class="text-xl font-bold mb-0">{{ $latestHealthRecord->muscle_mass ?? 'N/A' }}</div>
                                    <small class="text-gray-500">Muscle Mass</small>
                                </div>
                            </div>

                            <!-- Bone Mass -->
                            <div>
                                <div class="text-center p-3 bg-gray-100 rounded">
                                    <i class="bi bi-capsule text-secondary mb-2" style="font-size: 1.5rem;"></i>
                                    <div class="text-xl font-bold mb-0">{{ $latestHealthRecord->bone_mass ?? 'N/A' }}</div>
                                    <small class="text-gray-500">Bone Mass</small>
                                </div>
                            </div>

                            <!-- BMR -->
                            <div>
                                <div class="text-center p-3 bg-gray-100 rounded">
                                    <i class="bi bi-lightning text-red-600 mb-2" style="font-size: 1.5rem;"></i>
                                    <div class="text-xl font-bold mb-0">{{ $latestHealthRecord->bmr ?? 'N/A' }}</div>
                                    <small class="text-gray-500">BMR (cal)</small>
                                </div>
                            </div>
                        @else
                            <div class="col-span-full">
                                <div class="text-center py-4">
                                    <i class="bi bi-heart-pulse text-gray-500" style="font-size: 3rem;"></i>
                                    <p class="text-gray-500 mt-3">No health metrics available</p>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Body Composition Analysis & Compare Row -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 mb-4">
                <!-- Body Composition Analysis -->
                <div class="lg:col-span-7">
                    <div class="bg-white rounded-xl shadow-sm h-full">
                        <div class="p-4">
                            <h5 class="font-bold mb-4"><i class="bi bi-activity mr-2"></i>Body Composition Analysis</h5>

                            <div class="chart-container" style="position: relative; height: min(500px, 60vh); width: 100%;">
                                <canvas id="radarChart" data-current='@json($comparisonRecords->first())' data-previous='@json($comparisonRecords->skip(1)->first())'></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Compare -->
                <div class="lg:col-span-5">
                    <div class="bg-white rounded-xl shadow-sm h-full">
                        <div class="p-4">
                            <h5 class="font-bold mb-4"><i class="bi bi-bar-chart-line mr-2"></i>Compare</h5>

                            @if($comparisonRecords->count() >= 2)
                                @php
                                    $current = $comparisonRecords->first();
                                    $previous = $comparisonRecords->skip(1)->first();
                                @endphp

                                <div class="mb-3">
                                    <div class="grid grid-cols-2 gap-2">
                                        <div class="text-center">
                                            <label class="block text-sm font-medium text-gray-700 mb-1 font-bold">From</label>
                                            <select class="rounded-md border border-gray-300 bg-white px-2 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" id="currentDate">
                                                @foreach($healthRecords as $record)
                                                    <option value="{{ $record->id }}" {{ $record->id == $current->id ? 'selected' : '' }}>
                                                        {{ $record->recorded_at->format('M j, Y') }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="text-center">
                                            <label class="block text-sm font-medium text-gray-700 mb-1 font-bold">To</label>
                                            <select class="rounded-md border border-gray-300 bg-white px-2 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" id="previousDate">
                                                @foreach($healthRecords as $record)
                                                    <option value="{{ $record->id }}" {{ $record->id == $previous->id ? 'selected' : '' }}>
                                                        {{ $record->recorded_at->format('M j, Y') }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="mt-3">
                                        <div class="p-3 rounded-lg bg-gray-100 text-gray-700 text-center py-2" id="timeDifference">
                                            @if($current && $previous)
                                                <strong>Time between records:</strong> {{ calculateTimeDifference($current->recorded_at, $previous->recorded_at) }}
                                            @else
                                                Select dates to see time difference
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <div class="overflow-x-auto">
                                    <table class="w-full text-sm">
                                        <thead>
                                            <tr class="border-bottom">
                                                <th class="text-gray-500 text-sm font-semibold">Metric</th>
                                                <th class="text-gray-500 text-sm font-semibold text-right">Current</th>
                                                <th class="text-gray-500 text-sm font-semibold text-right">Previous</th>
                                                <th class="text-gray-500 text-sm font-semibold text-center">Change</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @php
                                                function getChangeIcon($current, $previous) {
                                                    if ($current > $previous) return '<i class="bi bi-arrow-up text-green-600"></i>';
                                                    if ($current < $previous) return '<i class="bi bi-arrow-down text-red-600"></i>';
                                                    return '<i class="bi bi-dash text-gray-500"></i>';
                                                }
                                            @endphp
                                            <tr data-metric="height">
                                                <td class="small"><i class="bi bi-rulers mr-2"></i>Height</td>
                                                <td class="small text-right font-semibold text-primary">{{ $current->height ?? 'N/A' }}cm</td>
                                                <td class="small text-right text-red-600">{{ $previous->height ?? 'N/A' }}cm</td>
                                                <td class="text-center">{!! $current->height && $previous->height ? getChangeIcon($current->height, $previous->height) : '-' !!}</td>
                                            </tr>
                                            <tr data-metric="weight">
                                                <td class="small"><i class="bi bi-speedometer2 mr-2"></i>Weight</td>
                                                <td class="small text-right font-semibold text-primary">{{ $current->weight ?? 'N/A' }}kg</td>
                                                <td class="small text-right text-red-600">{{ $previous->weight ?? 'N/A' }}kg</td>
                                                <td class="text-center">{!! $current->weight && $previous->weight ? getChangeIcon($current->weight, $previous->weight) : '-' !!}</td>
                                            </tr>
                                            <tr data-metric="body_fat">
                                                <td class="small"><i class="bi bi-activity mr-2"></i>Body Fat</td>
                                                <td class="small text-right font-semibold text-primary">{{ $current->body_fat_percentage ?? 'N/A' }}%</td>
                                                <td class="small text-right text-red-600">{{ $previous->body_fat_percentage ?? 'N/A' }}%</td>
                                                <td class="text-center">{!! $current->body_fat_percentage && $previous->body_fat_percentage ? getChangeIcon($current->body_fat_percentage, $previous->body_fat_percentage) : '-' !!}</td>
                                            </tr>
                                            <tr data-metric="bmi">
                                                <td class="small"><i class="bi bi-calculator mr-2"></i>BMI</td>
                                                <td class="small text-right font-semibold text-primary">{{ $current->bmi ?? 'N/A' }}</td>
                                                <td class="small text-right text-red-600">{{ $previous->bmi ?? 'N/A' }}</td>
                                                <td class="text-center">{!! $current->bmi && $previous->bmi ? getChangeIcon($current->bmi, $previous->bmi) : '-' !!}</td>
                                            </tr>
                                            <tr data-metric="body_water">
                                                <td class="small"><i class="bi bi-droplet mr-2"></i>Body Water</td>
                                                <td class="small text-right font-semibold text-primary">{{ $current->body_water_percentage ?? 'N/A' }}%</td>
                                                <td class="small text-right text-red-600">{{ $previous->body_water_percentage ?? 'N/A' }}%</td>
                                                <td class="text-center">{!! $current->body_water_percentage && $previous->body_water_percentage ? getChangeIcon($current->body_water_percentage, $previous->body_water_percentage) : '-' !!}</td>
                                            </tr>
                                            <tr data-metric="muscle_mass">
                                                <td class="small"><i class="bi bi-heart mr-2"></i>Muscle Mass</td>
                                                <td class="small text-right font-semibold text-primary">{{ $current->muscle_mass ?? 'N/A' }}kg</td>
                                                <td class="small text-right text-red-600">{{ $previous->muscle_mass ?? 'N/A' }}kg</td>
                                                <td class="text-center">{!! $current->muscle_mass && $previous->muscle_mass ? getChangeIcon($current->muscle_mass, $previous->muscle_mass) : '-' !!}</td>
                                            </tr>
                                            <tr data-metric="bone_mass">
                                                <td class="small"><i class="bi bi-capsule mr-2"></i>Bone Mass</td>
                                                <td class="small text-right font-semibold text-primary">{{ $current->bone_mass ?? 'N/A' }}kg</td>
                                                <td class="small text-right text-red-600">{{ $previous->bone_mass ?? 'N/A' }}kg</td>
                                                <td class="text-center">{!! $current->bone_mass && $previous->bone_mass ? getChangeIcon($current->bone_mass, $previous->bone_mass) : '-' !!}</td>
                                            </tr>
                                            <tr data-metric="visceral_fat">
                                                <td class="small"><i class="bi bi-activity mr-2"></i>Visceral Fat</td>
                                                <td class="small text-right font-semibold text-primary">{{ $current->visceral_fat ?? 'N/A' }}</td>
                                                <td class="small text-right text-red-600">{{ $previous->visceral_fat ?? 'N/A' }}</td>
                                                <td class="text-center">{!! $current->visceral_fat && $previous->visceral_fat ? getChangeIcon($current->visceral_fat, $previous->visceral_fat) : '-' !!}</td>
                                            </tr>
                                            <tr data-metric="bmr">
                                                <td class="small"><i class="bi bi-lightning mr-2"></i>BMR</td>
                                                <td class="small text-right font-semibold text-primary">{{ $current->bmr ?? 'N/A' }}cal</td>
                                                <td class="small text-right text-red-600">{{ $previous->bmr ?? 'N/A' }}cal</td>
                                                <td class="text-center">{!! $current->bmr && $previous->bmr ? getChangeIcon($current->bmr, $previous->bmr) : '-' !!}</td>
                                            </tr>
                                            <tr data-metric="protein">
                                                <td class="small"><i class="bi bi-heart-pulse mr-2"></i>Protein</td>
                                                <td class="small text-right font-semibold text-primary">{{ $current->protein_percentage ?? 'N/A' }}%</td>
                                                <td class="small text-right text-red-600">{{ $previous->protein_percentage ?? 'N/A' }}%</td>
                                                <td class="text-center">{!! $current->protein_percentage && $previous->protein_percentage ? getChangeIcon($current->protein_percentage, $previous->protein_percentage) : '-' !!}</td>
                                            </tr>
                                            <tr data-metric="body_age">
                                                <td class="small"><i class="bi bi-calendar-heart mr-2"></i>Body Age</td>
                                                <td class="small text-right font-semibold text-primary">{{ $current->body_age ?? 'N/A' }}yrs</td>
                                                <td class="small text-right text-red-600">{{ $previous->body_age ?? 'N/A' }}yrs</td>
                                                <td class="text-center">{!! $current->body_age && $previous->body_age ? getChangeIcon($current->body_age, $previous->body_age) : '-' !!}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="text-center py-4">
                                    <i class="bi bi-bar-chart-line text-gray-500" style="font-size: 3rem;"></i>
                                    <p class="text-gray-500 mt-3">Need at least 2 health records to compare</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Health Tracking History -->
            <div class="bg-white rounded-xl shadow-sm">
                <div class="p-4">
                    <h5 class="font-bold mb-4"><i class="bi bi-heart-pulse mr-2"></i>Health Tracking</h5>

                    @if($healthRecords->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50 border-b border-gray-200">
                                <tr>
                                    <th class="text-gray-500 text-sm font-semibold">Date</th>
                                    <th class="text-gray-500 text-sm font-semibold text-center"><i class="bi bi-rulers mr-1"></i>Height (cm)</th>
                                    <th class="text-gray-500 text-sm font-semibold text-center"><i class="bi bi-speedometer2 mr-1"></i>Weight (kg)</th>
                                    <th class="text-gray-500 text-sm font-semibold text-center"><i class="bi bi-activity mr-1"></i>Body Fat %</th>
                                    <th class="text-gray-500 text-sm font-semibold text-center"><i class="bi bi-calculator mr-1"></i>BMI</th>
                                    <th class="text-gray-500 text-sm font-semibold text-center"><i class="bi bi-droplet mr-1"></i>Body Water %</th>
                                    <th class="text-gray-500 text-sm font-semibold text-center"><i class="bi bi-heart mr-1"></i>Muscle Mass (kg)</th>
                                    <th class="text-gray-500 text-sm font-semibold text-center"><i class="bi bi-capsule mr-1"></i>Bone Mass (kg)</th>
                                    <th class="text-gray-500 text-sm font-semibold text-center"><i class="bi bi-activity mr-1"></i>Visceral Fat</th>
                                    <th class="text-gray-500 text-sm font-semibold text-center"><i class="bi bi-lightning mr-1"></i>BMR</th>
                                    <th class="text-gray-500 text-sm font-semibold text-center"><i class="bi bi-heart-pulse mr-1"></i>Protein %</th>
                                    <th class="text-gray-500 text-sm font-semibold text-center"><i class="bi bi-calendar-heart mr-1"></i>Body Age</th>
                                </tr>
                                </thead>
                                <tbody>
                                    @foreach($healthRecords as $record)
                                        <tr data-record-id="{{ $record->id }}" class="relative history-row">
                                            <td class="small font-semibold">{{ $record->recorded_at->format('M j, Y') }}</td>
                                            <td class="small text-center">{{ $record->height ?? '-' }}</td>
                                            <td class="small text-center">{{ $record->weight ?? '-' }}</td>
                                            <td class="small text-center">{{ $record->body_fat_percentage ?? '-' }}</td>
                                            <td class="small text-center">{{ $record->bmi ?? '-' }}</td>
                                            <td class="small text-center">{{ $record->body_water_percentage ?? '-' }}</td>
                                            <td class="small text-center">{{ $record->muscle_mass ?? '-' }}</td>
                                            <td class="small text-center">{{ $record->bone_mass ?? '-' }}</td>
                                            <td class="small text-center">{{ $record->visceral_fat ?? '-' }}</td>
                                            <td class="small text-center">{{ $record->bmr ?? '-' }}</td>
                                            <td class="small text-center">{{ $record->protein_percentage ?? '-' }}</td>
                                            <td class="small text-center">{{ $record->body_age ?? '-' }}</td>
                                            <td class="absolute top-1/2 right-0 -translate-y-1/2 opacity-0 edit-record-btn" style="cursor: pointer; right: 10px;">
                                                <i class="bi bi-pencil text-primary" style="font-size: 1.2rem;"></i>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div class="flex justify-center mt-4">
                            {{ $healthRecords->links() }}
                        </div>
                    @else
                        <div class="text-center py-5">
                            <i class="bi bi-clipboard-data text-gray-500" style="font-size: 3rem;"></i>
                            <p class="text-gray-500 mt-3">No health records found</p>
                            <small class="text-gray-500">Health tracking data will appear here once records are added</small>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Goals Tab -->
        <div x-show="activeTab === 'goals'" x-transition id="goals" role="tabpanel">
            <div class="bg-white rounded-xl shadow-sm">
                <div class="p-4">
                    <!-- Section Title & Subtitle -->
                    <div class="flex justify-between items-center mb-4">
                        <div>
                            <h5 class="font-bold mb-1"><i class="bi bi-bullseye mr-2"></i>Goal Tracking</h5>
                            <p class="text-gray-500 text-sm mb-0">Set, track, and achieve your fitness objectives.</p>
                        </div>
                    </div>

                    <!-- Summary Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4">
                        <!-- Active Goals -->
                        <div>
                            <div class="bg-white rounded-xl shadow-sm text-center h-full goal-filter-card" style="background: linear-gradient(135deg, #8b5cf6 0%, #a855f7 100%); min-height: 120px; cursor: pointer;" data-filter="active">
                                <div class="p-6 p-3 flex flex-col justify-center items-center h-full">
                                    <i class="bi bi-bullseye text-white mb-2" style="font-size: 2rem;"></i>
                                    <h4 class="text-white font-bold mb-1">{{ $activeGoalsCount }}</h4>
                                    <small class="text-white/50">Active Goals</small>
                                </div>
                            </div>
                        </div>
                        <!-- Completed Goals -->
                        <div>
                            <div class="bg-white rounded-xl shadow-sm text-center h-full goal-filter-card" style="background: linear-gradient(135deg, #10b981 0%, #34d399 100%); min-height: 120px; cursor: pointer;" data-filter="completed">
                                <div class="p-6 p-3 flex flex-col justify-center items-center h-full">
                                    <i class="bi bi-check-circle-fill text-white mb-2" style="font-size: 2rem;"></i>
                                    <h4 class="text-white font-bold mb-1">{{ $completedGoalsCount }}</h4>
                                    <small class="text-white/50">Completed Goals</small>
                                </div>
                            </div>
                        </div>
                        <!-- Success Rate -->
                        <div>
                            <div class="bg-white rounded-xl shadow-sm text-center h-full" style="background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%); min-height: 120px;">
                                <div class="p-6 p-3 flex flex-col justify-center items-center h-full">
                                    <i class="bi bi-graph-up-arrow text-white mb-2" style="font-size: 2rem;"></i>
                                    <h4 class="text-white font-bold mb-1">{{ $successRate }}%</h4>
                                    <small class="text-white/50">Success Rate</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Current Goals List -->
                    @if($goals->count() > 0)
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4" id="goalsGrid">
                            @foreach($goals as $goal)
                        <div class="goal-card">
                            <div class="bg-white rounded-xl shadow-sm h-full relative" id="goal-{{ $goal->id }}">
                                <!-- Edit Button (only for active goals and authorized users) -->
                                @if($goal->status == 'active' && ($relationship->relationship_type == 'self' || Auth::id() == $relationship->guardian_user_id))
                                    <button class="w-8 h-8 rounded-full border border-primary text-primary flex items-center justify-center hover:bg-primary hover:text-white transition-colors absolute top-0 right-0 mt-2 mr-2 edit-goal-btn" data-goal-id="{{ $goal->id }}" title="Edit Goal">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                @endif

                                <div class="p-4">
                                    <!-- Title & Icon -->
                                    <div class="flex items-center mb-3">
                                        <div class="rounded-full flex items-center justify-center mr-3" style="width: 48px; height: 48px; background-color: #8b5cf6;">
                                            @if($goal->icon_type == 'dumbbell')
                                                <i class="bi bi-dumbbell text-white"></i>
                                            @elseif($goal->icon_type == 'clock')
                                                <i class="bi bi-clock text-white"></i>
                                            @else
                                                <i class="bi bi-bullseye text-white"></i>
                                            @endif
                                        </div>
                                        <div class="flex-1">
                                            <h6 class="font-bold mb-1">{{ $goal->title }}</h6>
                                            @if($goal->description)
                                                <p class="text-gray-500 text-sm mb-0">{{ $goal->description }}</p>
                                            @endif
                                        </div>
                                    </div>

                                            <!-- Progress Indicator -->
                                            <div class="mb-3">
                                                <div class="flex justify-between items-center mb-2">
                                                    <small class="text-gray-500" data-goal-progress-text>Progress: {{ number_format($goal->current_progress_value, 1) }} / {{ number_format($goal->target_value, 1) }} {{ $goal->unit }}</small>
                                                    <small class="font-semibold" data-goal-progress-pct>{{ number_format($goal->progress_percentage, 1) }}%</small>
                                                </div>
                                                <div class="h-2 bg-gray-200 rounded-full overflow-hidden" style="height: 8px;">
                                                    <div class="h-full bg-primary transition-all" role="progressbar" data-goal-progress-bar style="width: {{ $goal->progress_percentage }}%; background: linear-gradient(90deg, #8b5cf6 0%, #10b981 100%);" aria-valuenow="{{ $goal->progress_percentage }}" aria-valuemin="0" aria-valuemax="100"></div>
                                                </div>
                                            </div>

                                            <!-- Dates & Status -->
                                            <div class="grid grid-cols-2 gap-2 mb-3">
                                                <div>
                                                    <small class="text-gray-500 block">Started:</small>
                                                    <small class="font-semibold">{{ $goal->start_date->format('M d, Y') }}</small>
                                                </div>
                                                <div class="text-right">
                                                    <small class="text-gray-500 block">Target:</small>
                                                    <small class="font-semibold">{{ $goal->target_date->format('M d, Y') }}</small>
                                                </div>
                                            </div>

                                            <!-- Status Badges -->
                                            <div class="flex gap-2 flex-wrap">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $goal->status == 'active' ? 'bg-primary/10 text-primary' : 'bg-green-100 text-green-800' }}" data-goal-status-badge>
                                                    {{ ucfirst($goal->status) }}
                                                </span>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $goal->priority_level == 'high' ? 'bg-red-100 text-red-800' : ($goal->priority_level == 'medium' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800') }}">
                                                    {{ ucfirst($goal->priority_level) }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-4">
                            <i class="bi bi-bullseye text-gray-500" style="font-size: 3rem;"></i>
                            <h5 class="text-gray-500 mt-3 mb-2">No Goals Set Yet</h5>
                            <p class="text-gray-500 mb-0">Start your fitness journey by setting your first goal!</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Affiliations Tab -->
        <div x-show="activeTab === 'affiliations'" x-transition id="affiliations" role="tabpanel">
            @include('components-templates.member.partials.affiliations-enhanced')
        </div>

        <!-- Tournaments Tab -->
        <div x-show="activeTab === 'tournaments'" x-transition id="tournaments" role="tabpanel">
            <!-- Tournament & Event Participation Card -->
            <div class="bg-white rounded-xl shadow-sm mb-4">
                <div class="p-4">
                    <!-- Section Title & Subtitle -->
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-4">
                        <div>
                            <h5 class="font-bold mb-1"><i class="bi bi-trophy-fill text-warning mr-2"></i>Tournament & Event Participation</h5>
                            <p class="text-gray-500 text-sm mb-0">Proven champion with multiple championship wins and prestigious awards.</p>
                        </div>
                        <!-- Filter Section -->
                        <div class="flex items-center gap-2 flex-shrink-0">
                            <label for="sportFilter" class="text-sm font-semibold text-gray-700 whitespace-nowrap">Filter by Sport:</label>
                            <select class="rounded-md border border-gray-300 bg-white px-2 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary w-full sm:w-36" id="sportFilter">
                                <option value="all">All Sports</option>
                                @foreach($sports as $sport)
                                    <option value="{{ $sport }}">{{ $sport }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <!-- Award Summary Cards -->
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3" id="awardCards">
                        <div>
                            <div class="bg-white rounded-xl shadow-sm text-center" style="background: linear-gradient(135deg, #FFD700 0%, #FFA500 100%);">
                                <div class="p-6 p-3">
                                    <i class="bi bi-trophy-fill text-white mb-2" style="font-size: 2rem;"></i>
                                    <h4 class="text-white font-bold mb-1" id="specialCount">{{ $awardCounts['special'] }}</h4>
                                    <small class="text-white/50">Special Award</small>
                                </div>
                            </div>
                        </div>
                        <div>
                            <div class="bg-white rounded-xl shadow-sm text-center" style="background: linear-gradient(135deg, #FFD700 0%, #FFA500 100%);">
                                <div class="p-6 p-3">
                                    <i class="bi bi-award-fill text-white mb-2" style="font-size: 2rem;"></i>
                                    <h4 class="text-white font-bold mb-1" id="firstCount">{{ $awardCounts['1st'] }}</h4>
                                    <small class="text-white/50">1st Place</small>
                                </div>
                            </div>
                        </div>
                        <div>
                            <div class="bg-white rounded-xl shadow-sm text-center" style="background: linear-gradient(135deg, #C0C0C0 0%, #A8A8A8 100%);">
                                <div class="p-6 p-3">
                                    <i class="bi bi-award-fill text-white mb-2" style="font-size: 2rem;"></i>
                                    <h4 class="text-white font-bold mb-1" id="secondCount">{{ $awardCounts['2nd'] }}</h4>
                                    <small class="text-white/50">2nd Place</small>
                                </div>
                            </div>
                        </div>
                        <div>
                            <div class="bg-white rounded-xl shadow-sm text-center" style="background: linear-gradient(135deg, #CD7F32 0%, #A0522D 100%);">
                                <div class="p-6 p-3">
                                    <i class="bi bi-award-fill text-white mb-2" style="font-size: 2rem;"></i>
                                    <h4 class="text-white font-bold mb-1" id="thirdCount">{{ $awardCounts['3rd'] }}</h4>
                                    <small class="text-white/50">3rd Place</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tournament & Championships History Card -->
            <div class="bg-white rounded-xl shadow-sm">
                <div class="p-4">
                    <h6 class="font-bold mb-3"><i class="bi bi-list-ul mr-2"></i>Tournament & Championships History</h6>

                    <div class="overflow-x-auto" id="tournamentsTableWrapper" style="{{ $tournamentEvents->count() > 0 ? '' : 'display:none;' }}">
                            <table class="w-full text-sm" id="tournamentsTable">
                                <thead class="bg-gray-50 border-b border-gray-200">
                                    <tr>
                                        <th class="text-gray-500 text-sm font-semibold">Tournament Details</th>
                                        <th class="text-gray-500 text-sm font-semibold">Club Affiliation</th>
                                        <th class="text-gray-500 text-sm font-semibold">Performance & Result</th>
                                        <th class="text-gray-500 text-sm font-semibold">Notes & Media</th>
                                    </tr>
                                </thead>
                                <tbody id="tournamentsTableBody">
                                    @foreach($tournamentEvents as $event)
                                        <tr data-sport="{{ $event->sport }}">
                                            <td>
                                                <div class="font-bold">{{ $event->title }}</div>
                                                <div class="flex gap-2 mt-1 flex-wrap">
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $event->type == 'championship' ? 'bg-primary/10 text-primary' : 'bg-gray-100 text-gray-800' }}">{{ ucfirst($event->type) }}</span>
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">{{ $event->sport }}</span>
                                                </div>
                                                <div class="text-gray-500 text-sm mt-1">
                                                    <i class="bi bi-calendar-event mr-1"></i>{{ $event->date->format('M j, Y') }}
                                                    @if($event->time)
                                                        <i class="bi bi-clock mr-1 ml-2"></i>{{ $event->time->format('H:i') }}
                                                    @endif
                                                    @if($event->location)
                                                        <i class="bi bi-geo-alt mr-1 ml-2"></i>{{ $event->location }}
                                                    @endif
                                                    @if($event->participants_count)
                                                        <i class="bi bi-people mr-1 ml-2"></i>{{ $event->participants_count }} participants
                                                    @endif
                                                </div>
                                            </td>
                                            <td>
                                                @if($event->clubAffiliation)
                                                    <div>
                                                        <div class="small font-semibold">{{ $event->clubAffiliation->club_name }}</div>
                                                        <div class="text-gray-500 text-sm">{{ $event->clubAffiliation->location }}</div>
                                                    </div>
                                                @else
                                                    <span class="text-gray-500 text-sm">Individual</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($event->performanceResults->count() > 0)
                                                    @foreach($event->performanceResults as $result)
                                                        <div class="flex items-center gap-2 mb-1">
                                                            @if($result->medal_type == '1st')
                                                                <i class="bi bi-award-fill text-warning"></i>
                                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">1st Place</span>
                                                            @elseif($result->medal_type == '2nd')
                                                                <i class="bi bi-award-fill text-secondary"></i>
                                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">2nd Place</span>
                                                            @elseif($result->medal_type == '3rd')
                                                                <i class="bi bi-award-fill" style="color: #CD7F32;"></i>
                                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium text-white" style="background-color: #CD7F32;">3rd Place</span>
                                                            @elseif($result->medal_type == 'special')
                                                                <i class="bi bi-trophy-fill text-warning"></i>
                                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">Special Award</span>
                                                            @endif
                                                            @if($result->points)
                                                                <small class="text-gray-500">{{ $result->points }} pts</small>
                                                            @endif
                                                        </div>
                                                        @if($result->description)
                                                            <small class="text-gray-500">{{ $result->description }}</small>
                                                        @endif
                                                    @endforeach
                                                @else
                                                    <span class="text-gray-500 text-sm">No results recorded</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($event->notesMedia->count() > 0)
                                                    @foreach($event->notesMedia as $note)
                                                        @if($note->note_text)
                                                            <p class="mb-1 small">{{ $note->note_text }}</p>
                                                        @endif
                                                        @if($note->media_link)
                                                            <a href="{{ $note->media_link }}" target="_blank" class="border border-primary text-primary px-2 py-1 rounded text-xs hover:bg-primary hover:text-white transition-colors">
                                                                <i class="bi bi-image mr-1"></i>View Media
                                                            </a>
                                                        @endif
                                                    @endforeach
                                                @else
                                                    <span class="text-gray-500 text-sm">No notes available</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="text-center py-5" id="tournamentsEmptyState" style="{{ $tournamentEvents->count() > 0 ? 'display:none;' : '' }}">
                            <i class="bi bi-trophy text-gray-500" style="font-size: 3rem;"></i>
                            <p class="text-gray-500 mt-3">No tournament records found</p>
                            <small class="text-gray-500">Tournament participation will appear here once records are added</small>
                        </div>
                </div>
            </div>
        </div>

        <!-- Events Tab -->
        <div x-show="activeTab === 'events'" x-transition id="events" role="tabpanel">
            <div class="bg-white rounded-xl shadow-sm">
                <div class="p-4">
                    <h5 class="font-bold mb-3"><i class="bi bi-calendar-event mr-2"></i>Event Participation</h5>

                    @if($joinedEventRegistrations->isEmpty())
                        <div class="text-center py-10">
                            <i class="bi bi-calendar-x text-gray-300" style="font-size:2.5rem;"></i>
                            <p class="text-gray-400 mt-3">No events joined yet.</p>
                        </div>
                    @else
                        <div class="flex flex-col gap-3">
                            @foreach($joinedEventRegistrations as $reg)
                            @php
                                $ev        = $reg->event;
                                $pillColor = $ev->color ?: '#1d4ed8';
                                $isPast    = $ev->date->isPast();
                            @endphp
                            <div class="flex items-center gap-3 p-3 rounded-xl border border-gray-100 {{ $isPast ? 'opacity-60' : '' }}">
                                {{-- Date pill --}}
                                <div class="flex-shrink-0 rounded-xl text-white text-center px-3 py-2 min-w-[52px]"
                                     style="background:{{ $pillColor }};">
                                    <div class="text-xs font-semibold uppercase leading-none">{{ $ev->date->format('D') }}</div>
                                    <div class="text-xl font-extrabold leading-none">{{ $ev->date->format('d') }}</div>
                                    <div class="text-xs font-semibold uppercase leading-none">{{ $ev->date->format('M') }}</div>
                                </div>
                                {{-- Info --}}
                                <div class="flex-1 min-w-0">
                                    <div class="font-semibold text-gray-800 truncate">{{ $ev->title }}</div>
                                    <div class="text-xs text-gray-500 mt-0.5">
                                        <span class="mr-3"><i class="bi bi-clock mr-1"></i>{{ \Carbon\Carbon::parse($ev->start_time)->format('g:i A') }}</span>
                                        @if($ev->location)<span><i class="bi bi-geo-alt mr-1"></i>{{ $ev->location }}</span>@endif
                                    </div>
                                    @if($ev->tenant)
                                    <div class="text-xs text-gray-400 mt-0.5"><i class="bi bi-building mr-1"></i>{{ $ev->tenant->club_name }}</div>
                                    @endif
                                </div>
                                {{-- Status badge --}}
                                <div class="flex-shrink-0">
                                    @if($reg->status === 'waitlisted')
                                        <span class="inline-flex items-center gap-1 text-xs font-semibold px-2 py-1 rounded-full bg-yellow-100 text-yellow-700">
                                            <i class="bi bi-clock-history"></i> Waitlisted
                                        </span>
                                    @elseif($isPast)
                                        <span class="inline-flex items-center gap-1 text-xs font-semibold px-2 py-1 rounded-full bg-gray-100 text-gray-500">
                                            <i class="bi bi-check-circle"></i> Attended
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 text-xs font-semibold px-2 py-1 rounded-full bg-green-100 text-green-700">
                                            <i class="bi bi-check-circle-fill"></i> Joined
                                        </span>
                                    @endif
                                </div>
                            </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
    </div><!-- End of Alpine x-data for tabs -->
</div>

<!-- Goal Edit Modal -->
<div x-data="{ open: false }" @open-goal-edit-modal.window="open = true" @close-goal-edit-modal.window="open = false" x-cloak>
    <div x-show="open" class="fixed inset-0 z-50 overflow-y-auto" @keydown.escape.window="open = false">
        <div x-show="open" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
             class="fixed inset-0 bg-black/50" @click="open = false"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div x-show="open" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
                 class="relative bg-white rounded-lg shadow-xl w-full max-w-2xl" @click.stop>
                <div class="flex items-center justify-between p-4 border-b border-gray-200 flex items-center justify-between p-4 border-b">
                    <h5 class="text-lg font-medium font-medium text-lg">Edit Goal Progress</h5>
                    <button type="button" @click="open = false" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
                </div>
                <form id="goalEditForm" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="p-4 p-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div class="col-span-full">
                                <div class="flex items-center mb-3">
                                    <div class="rounded-full flex items-center justify-center mr-3" id="goalIconDisplay" style="width: 48px; height: 48px; background-color: #8b5cf6;">
                                        <i class="bi bi-bullseye text-white"></i>
                                    </div>
                                    <div>
                                        <h6 class="font-bold mb-1" id="goalTitleDisplay">Goal Title</h6>
                                        <p class="text-gray-500 text-sm mb-0" id="goalDescriptionDisplay">Goal description</p>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <label for="current_progress_value" class="block text-sm font-medium text-gray-700 mb-1">Current Progress <span class="text-red-600">*</span></label>
                                <div class="flex">
                                    <input type="number" step="0.1" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" id="current_progress_value" name="current_progress_value" required>
                                    <span class="px-3 py-2 bg-gray-100 border border-gray-300 border-l-0 rounded-r-md text-sm flex items-center" id="goalUnitDisplay">lbs</span>
                                </div>
                                <div class="text-xs text-gray-500 mt-1">Target: <span id="goalTargetDisplay">170.0 lbs</span></div>
                            </div>
                            <div>
                                <label for="goal_status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                                <select class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" id="goal_status" name="status">
                                    <option value="active">Active</option>
                                    <option value="completed">Completed</option>
                                </select>
                            </div>
                            <div class="col-span-full">
                                <div class="h-2 bg-gray-200 rounded-full overflow-hidden" style="height: 8px;">
                                    <div class="h-full bg-primary transition-all" role="progressbar" id="progressPreview" style="width: 0%; background: linear-gradient(90deg, #8b5cf6 0%, #10b981 100%);"></div>
                                </div>
                                <small class="text-gray-500 mt-1 block" id="progressTextPreview">Progress: 0.0 / 170.0 lbs (0.0%)</small>
                            </div>
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 p-4 border-t border-gray-200 bg-gray-50 flex justify-end gap-2 p-4 border-t bg-gray-50">
                        <button type="button" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-md text-sm font-medium hover:bg-gray-300 transition-colors" @click="open = false">Cancel</button>
                        <button type="submit" class="bg-primary text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-primary/90 transition-colors">Update Goal</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Health Update Modal -->
<div x-data="{ open: false }" @open-health-update-modal.window="open = true" x-cloak>
    <div x-show="open" class="fixed inset-0 z-50 overflow-y-auto" @keydown.escape.window="open = false">
        <div x-show="open" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
             class="fixed inset-0 bg-black/50" @click="open = false"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div x-show="open" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
                 class="relative bg-white rounded-lg shadow-xl w-full max-w-2xl" @click.stop>
                <div class="flex items-center justify-between p-4 border-b border-gray-200 flex items-center justify-between p-4 border-b">
                    <h5 class="text-lg font-medium font-medium text-lg">Add Health Update</h5>
                    <button type="button" @click="open = false" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
                </div>
                <form id="healthUpdateForm" method="POST" action="{{ $relationship->relationship_type === 'admin_view' ? route('admin.platform.members.store-health', $relationship->dependent->id) : route('member.store-health', $relationship->dependent->id) }}">
                    @csrf
                    <div class="p-4 p-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label for="recorded_at" class="block text-sm font-medium text-gray-700 mb-1">Date <span class="text-red-600">*</span></label>
                                <input type="date" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" id="recorded_at" name="recorded_at" value="{{ \Carbon\Carbon::now()->format('Y-m-d') }}" required>
                            </div>
                            <div>
                                <label for="height" class="block text-sm font-medium text-gray-700 mb-1">Height (cm)</label>
                                <input type="number" step="0.1" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" id="height" name="height">
                            </div>
                            <div>
                                <label for="weight" class="block text-sm font-medium text-gray-700 mb-1">Weight (kg)</label>
                                <input type="number" step="0.1" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" id="weight" name="weight">
                            </div>
                            <div>
                                <label for="body_fat_percentage" class="block text-sm font-medium text-gray-700 mb-1">Body Fat (%)</label>
                                <input type="number" step="0.1" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" id="body_fat_percentage" name="body_fat_percentage">
                            </div>
                            <div>
                                <label for="bmi" class="block text-sm font-medium text-gray-700 mb-1">BMI</label>
                                <input type="number" step="0.1" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" id="bmi" name="bmi">
                            </div>
                            <div>
                                <label for="body_water_percentage" class="block text-sm font-medium text-gray-700 mb-1">Body Water (%)</label>
                                <input type="number" step="0.1" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" id="body_water_percentage" name="body_water_percentage">
                            </div>
                            <div>
                                <label for="muscle_mass" class="block text-sm font-medium text-gray-700 mb-1">Muscle Mass (kg)</label>
                                <input type="number" step="0.1" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" id="muscle_mass" name="muscle_mass">
                            </div>
                            <div>
                                <label for="bone_mass" class="block text-sm font-medium text-gray-700 mb-1">Bone Mass (kg)</label>
                                <input type="number" step="0.1" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" id="bone_mass" name="bone_mass">
                            </div>
                            <div>
                                <label for="visceral_fat" class="block text-sm font-medium text-gray-700 mb-1">Visceral Fat</label>
                                <input type="number" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" id="visceral_fat" name="visceral_fat">
                            </div>
                            <div>
                                <label for="bmr" class="block text-sm font-medium text-gray-700 mb-1">BMR (cal)</label>
                                <input type="number" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" id="bmr" name="bmr">
                            </div>
                            <div>
                                <label for="protein_percentage" class="block text-sm font-medium text-gray-700 mb-1">Protein (%)</label>
                                <input type="number" step="0.1" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" id="protein_percentage" name="protein_percentage">
                            </div>
                            <div>
                                <label for="body_age" class="block text-sm font-medium text-gray-700 mb-1">Body Age (years)</label>
                                <input type="number" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" id="body_age" name="body_age">
                            </div>
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 p-4 border-t border-gray-200 bg-gray-50 flex justify-end gap-2 p-4 border-t bg-gray-50">
                        <button type="button" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-md text-sm font-medium hover:bg-gray-300 transition-colors" @click="open = false">Cancel</button>
                        <button type="submit" class="bg-primary text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-primary/90 transition-colors">Save Health Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Tournament Participation Modal -->
<div x-data="{ open: false }" @open-tournament-modal.window="open = true" @close-tournament-modal.window="open = false" x-cloak>
    <div x-show="open" class="fixed inset-0 z-50 overflow-y-auto" @keydown.escape.window="open = false">
        <div x-show="open" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
             class="fixed inset-0 bg-black/50" @click="open = false"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div x-show="open" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
                 class="relative bg-white rounded-lg shadow-xl w-full max-w-5xl max-h-[90vh] overflow-hidden" @click.stop>
                <div class="flex items-center justify-between p-4 border-b border-gray-200 flex items-center justify-between p-4 border-b">
                    <h5 class="text-lg font-medium font-medium text-lg">Add Tournament Participation</h5>
                    <button type="button" @click="open = false" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
                </div>
                <form id="tournamentParticipationForm" method="POST" action="{{ $relationship->relationship_type === 'admin_view' ? route('admin.platform.members.store-tournament', $relationship->dependent->id) : route('member.store-tournament', $relationship->dependent->id) }}">
                    @csrf
                    <div class="p-4 p-4 overflow-y-auto" style="max-height: calc(90vh - 140px);">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <!-- Tournament Details -->
                            <div>
                                <label for="tournament_title" class="block text-sm font-medium text-gray-700 mb-1">Tournament Title <span class="text-red-600">*</span></label>
                                <input type="text" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" id="tournament_title" name="title" required>
                            </div>
                            <div>
                                <label for="tournament_type" class="block text-sm font-medium text-gray-700 mb-1">Type <span class="text-red-600">*</span></label>
                                <select class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" id="tournament_type" name="type" required>
                                    <option value="">Select Type</option>
                                    <option value="championship">Championship</option>
                                    <option value="tournament">Tournament</option>
                                    <option value="competition">Competition</option>
                                    <option value="exhibition">Exhibition</option>
                                </select>
                            </div>
                            <div>
                                <label for="tournament_sport" class="block text-sm font-medium text-gray-700 mb-1">Sport <span class="text-red-600">*</span></label>
                                <select class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" id="tournament_sport" name="sport" required>
                                    <option value="">Select Sport</option>
                                    <option value="Boxing">Boxing</option>
                                    <option value="Taekwondo">Taekwondo</option>
                                    <option value="Karate">Karate</option>
                                    <option value="Martial Arts">Martial Arts</option>
                                    <option value="Fitness">Fitness</option>
                                    <option value="Weightlifting">Weightlifting</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div>
                                <x-birthdate-dropdown
                                    name="date"
                                    id="tournament_date"
                                    label="Date"
                                    :required="true"
                                    :min-year="2000"
                                    :max-year="date('Y')"
                                    :error="$errors->first('date')" />
                            </div>
                            <div>
                                <label for="tournament_time" class="block text-sm font-medium text-gray-700 mb-1">Time</label>
                                <input type="time" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" id="tournament_time" name="time">
                            </div>
                            <div>
                                <label for="tournament_location" class="block text-sm font-medium text-gray-700 mb-1">Location</label>
                                <input type="text" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" id="tournament_location" name="location" placeholder="Venue name or address">
                            </div>
                            <div>
                                <label for="participants_count" class="block text-sm font-medium text-gray-700 mb-1">Number of Participants</label>
                                <input type="number" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" id="participants_count" name="participants_count" min="1">
                            </div>
                            <div>
                                <label for="club_affiliation_id" class="block text-sm font-medium text-gray-700 mb-1">Club Affiliation</label>
                                <select class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" id="club_affiliation_id" name="club_affiliation_id">
                                    <option value="">Select Club (Optional)</option>
                                    @foreach($clubAffiliations ?? [] as $affiliation)
                                        <option value="{{ $affiliation->id }}">{{ $affiliation->club_name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Performance Results Section -->
                            <div class="col-span-full">
                                <hr class="my-3">
                                <h6 class="mb-3 font-medium">Performance Results</h6>
                                <div id="performanceResultsContainer">
                                    <div class="performance-result-item mb-3 p-3 border rounded">
                                        <div class="grid grid-cols-12 gap-2">
                                            <div class="col-span-4">
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Medal Type</label>
                                                <select class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary medal-type" name="performance_results[0][medal_type]">
                                                    <option value="">Select Medal</option>
                                                    <option value="special">Special Award</option>
                                                    <option value="1st">1st Place</option>
                                                    <option value="2nd">2nd Place</option>
                                                    <option value="3rd">3rd Place</option>
                                                </select>
                                            </div>
                                            <div class="col-span-3">
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Points</label>
                                                <input type="number" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" name="performance_results[0][points]" min="0" step="0.1">
                                            </div>
                                            <div class="col-span-4">
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                                                <input type="text" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" name="performance_results[0][description]" placeholder="Optional description">
                                            </div>
                                            <div class="col-span-1 flex items-end">
                                                <button type="button" class="border border-red-500 text-red-500 px-2 py-1 rounded text-xs hover:bg-red-500 hover:text-white transition-colors remove-result" style="display: none;">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <button type="button" class="border border-primary text-primary px-3 py-1.5 rounded text-sm hover:bg-primary hover:text-white transition-colors" id="addPerformanceResult">
                                    <i class="bi bi-plus mr-1"></i>Add Another Result
                                </button>
                            </div>

                            <!-- Notes & Media Section -->
                            <div class="col-span-full">
                                <hr class="my-3">
                                <h6 class="mb-3 font-medium">Notes & Media</h6>
                                <div id="notesMediaContainer">
                                    <div class="notes-media-item mb-3 p-3 border rounded">
                                        <div class="grid grid-cols-12 gap-2">
                                            <div class="col-span-6">
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Note Text</label>
                                                <textarea class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" name="notes_media[0][note_text]" rows="2" placeholder="Optional notes about the tournament"></textarea>
                                            </div>
                                            <div class="col-span-5">
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Media Link</label>
                                                <input type="url" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" name="notes_media[0][media_link]" placeholder="https://example.com/photo.jpg">
                                            </div>
                                            <div class="col-span-1 flex items-end">
                                                <button type="button" class="border border-red-500 text-red-500 px-2 py-1 rounded text-xs hover:bg-red-500 hover:text-white transition-colors remove-note" style="display: none;">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <button type="button" class="border border-primary text-primary px-3 py-1.5 rounded text-sm hover:bg-primary hover:text-white transition-colors" id="addNotesMedia">
                                    <i class="bi bi-plus mr-1"></i>Add Another Note/Media
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 p-4 border-t border-gray-200 bg-gray-50 flex justify-end gap-2 p-4 border-t bg-gray-50">
                        <button type="button" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-md text-sm font-medium hover:bg-gray-300 transition-colors" @click="open = false">Cancel</button>
                        <button type="submit" class="bg-primary text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-primary/90 transition-colors">Save Tournament Record</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
    .history-row:hover .edit-record-btn {
        opacity: 1 !important;
    }

    /* Timeline Styles */
    .timeline {
        position: relative;
        padding-left: 30px;
    }

    .timeline::before {
        content: '';
        position: absolute;
        left: 15px;
        top: 0;
        bottom: 0;
        width: 2px;
        background: #e9ecef;
    }

    .timeline-item {
        position: relative;
        margin-bottom: 20px;
    }

    .timeline-marker {
        position: absolute;
        left: -22px;
        top: 20px;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: #6c757d;
        border: 2px solid #fff;
        z-index: 1;
    }

    .timeline-marker.bg-primary {
        background: #0d6efd;
    }

    .timeline-content {
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        transition: all 0.3s ease;
    }

    .timeline-content:hover {
        box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        transform: translateY(-2px);
    }

    .affiliation-card {
        transition: all 0.3s ease;
    }

    .affiliation-card:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.15) !important;
        transform: translateY(-2px);
    }

    .affiliation-card.border-primary {
        border-color: #0d6efd !important;
        box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25) !important;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Load countries from JSON file
        fetch('/data/countries.json')
            .then(response => response.json())
            .then(countries => {
                // Convert all nationality displays from ISO3 to country name with flag
                document.querySelectorAll('.nationality-display').forEach(element => {
                    const iso3Code = element.getAttribute('data-iso3');
                    if (!iso3Code) return;

                    const country = countries.find(c => c.iso3 === iso3Code || c.iso2 === iso3Code);
                    if (country) {
                        // Get flag emoji from ISO2 code
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

        // Handle Add Health Update click - Now using Alpine.js events
        // The health update modal is triggered via @open-health-update-modal.window event

        // Handle Edit Health Record click - Now using Alpine.js events
        document.addEventListener('click', function(e) {
            if (e.target.closest('.edit-record-btn')) {
                e.preventDefault();
                const recordId = e.target.closest('tr').getAttribute('data-record-id');
                populateHealthModalForEdit(recordId);
                window.dispatchEvent(new CustomEvent('open-health-update-modal'));
            }
        });

        // Activate health tab if URL has #health - Now using Alpine.js
        if (window.location.hash === '#health') {
            // The tab is controlled by Alpine.js x-data, will be handled by Alpine
        }

        // Store health records data for dynamic comparison
        const healthRecordsData = @json($healthRecords->items());

        // Radar chart variables
        let radarChart = null;
        const metricLabels = ['Height', 'Weight', 'Body Fat', 'BMI', 'Body Water', 'Muscle Mass', 'Bone Mass', 'Visceral Fat', 'BMR', 'Protein', 'Body Age'];
        const metricKeys = ['height', 'weight', 'body_fat_percentage', 'bmi', 'body_water_percentage', 'muscle_mass', 'bone_mass', 'visceral_fat', 'bmr', 'protein_percentage', 'body_age'];

        // Function to create/update radar chart
        function updateRadarChart(currentRecord, previousRecord) {
            console.log('updateRadarChart called', currentRecord, previousRecord);
            const ctx = document.getElementById('radarChart').getContext('2d');

            // Extract data for current and previous records
            const currentData = metricKeys.map(key => currentRecord ? (currentRecord[key] || 0) : 0);
            const previousData = metricKeys.map(key => previousRecord ? (previousRecord[key] || 0) : 0);

            if (radarChart) {
                radarChart.destroy();
            }

            radarChart = new window.Chart(ctx, {
                type: 'radar',
                data: {
                    labels: metricLabels,
                    datasets: [
                        {
                            label: 'Current Reading',
                            data: currentData,
                            fill: true,
                            backgroundColor: 'rgba(54, 162, 235, 0.2)',
                            borderColor: 'rgb(54, 162, 235)',
                            pointBackgroundColor: 'rgb(54, 162, 235)',
                            pointBorderColor: '#fff',
                            pointHoverBackgroundColor: '#fff',
                            pointHoverBorderColor: 'rgb(54, 162, 235)'
                        },
                        {
                            label: 'Previous Reading',
                            data: previousData,
                            fill: false,
                            borderColor: 'rgb(255, 99, 132)',
                            pointBackgroundColor: 'rgb(255, 99, 132)',
                            pointBorderColor: '#fff',
                            pointHoverBackgroundColor: '#fff',
                            pointHoverBorderColor: 'rgb(255, 99, 132)'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    elements: {
                        line: {
                            borderWidth: 3
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    label += context.parsed.r;
                                    return label;
                                }
                            }
                        }
                    },
                    scales: {
                        r: {
                            beginAtZero: true,
                            ticks: {
                                display: false
                            }
                        }
                    }
                }
            });
        }

        // Function to calculate BMI
        function calculateBMI() {
            const height = parseFloat(document.getElementById('height').value);
            const weight = parseFloat(document.getElementById('weight').value);
            const bmiField = document.getElementById('bmi');

            if (height > 0 && weight > 0) {
                const heightInMeters = height / 100;
                const bmi = weight / (heightInMeters * heightInMeters);
                bmiField.value = bmi.toFixed(1);
            } else {
                bmiField.value = '';
            }
        }

        // Add event listeners for BMI calculation
        document.getElementById('height').addEventListener('input', calculateBMI);
        document.getElementById('weight').addEventListener('input', calculateBMI);

        // Function to reset modal for adding new record
        function resetHealthModal() {
            document.getElementById('healthUpdateModalLabel').textContent = 'Add Health Update';
            document.getElementById('healthUpdateForm').action = '{{ route("member.store-health", $relationship->dependent->id) }}';
            document.getElementById('healthUpdateForm').method = 'POST';
            document.getElementById('recorded_at').value = '{{ \Carbon\Carbon::now()->format("Y-m-d") }}';
            document.getElementById('height').value = '';
            document.getElementById('weight').value = '';
            document.getElementById('body_fat_percentage').value = '';
            document.getElementById('bmi').value = '';
            document.getElementById('body_water_percentage').value = '';
            document.getElementById('muscle_mass').value = '';
            document.getElementById('bone_mass').value = '';
            document.getElementById('visceral_fat').value = '';
            document.getElementById('bmr').value = '';
            document.getElementById('protein_percentage').value = '';
            document.getElementById('body_age').value = '';
            document.querySelector('#healthUpdateForm button[type="submit"]').textContent = 'Save Health Update';
        }

        // Function to populate modal for editing
        function populateHealthModalForEdit(recordId) {
            const record = healthRecordsData.find(r => r.id == recordId);
            if (!record) return;

            document.getElementById('healthUpdateModalLabel').textContent = 'Edit Health Update';
            document.getElementById('healthUpdateForm').action = '{{ route("member.update-health", ["id" => $relationship->dependent->id, "recordId" => "__RECORD_ID__"]) }}'.replace('__RECORD_ID__', recordId);
            document.getElementById('healthUpdateForm').method = 'POST';

            // Add method spoofing for PUT
            let methodInput = document.querySelector('#healthUpdateForm input[name="_method"]');
            if (!methodInput) {
                methodInput = document.createElement('input');
                methodInput.type = 'hidden';
                methodInput.name = '_method';
                document.getElementById('healthUpdateForm').appendChild(methodInput);
            }
            methodInput.value = 'PUT';

            document.getElementById('recorded_at').value = record.recorded_at.split('T')[0];
            document.getElementById('height').value = record.height || '';
            document.getElementById('weight').value = record.weight || '';
            document.getElementById('body_fat_percentage').value = record.body_fat_percentage || '';
            document.getElementById('bmi').value = record.bmi || '';
            document.getElementById('body_water_percentage').value = record.body_water_percentage || '';
            document.getElementById('muscle_mass').value = record.muscle_mass || '';
            document.getElementById('bone_mass').value = record.bone_mass || '';
            document.getElementById('visceral_fat').value = record.visceral_fat || '';
            document.getElementById('bmr').value = record.bmr || '';
            document.getElementById('protein_percentage').value = record.protein_percentage || '';
            document.getElementById('body_age').value = record.body_age || '';
            document.querySelector('#healthUpdateForm button[type="submit"]').textContent = 'Update Health Update';
        }

        // Handle comparison dropdown changes
        const currentDateSelect = document.getElementById('currentDate');
        const previousDateSelect = document.getElementById('previousDate');

        if (currentDateSelect && previousDateSelect) {
            function updateComparisonTable() {
                const currentId = currentDateSelect.value;
                const previousId = previousDateSelect.value;

                if (!currentId || !previousId) {
                    document.getElementById('timeDifference').innerHTML = 'Select dates to see time difference';
                    return;
                }

                const currentRecord = healthRecordsData.find(r => r.id == currentId);
                const previousRecord = healthRecordsData.find(r => r.id == previousId);

                if (!currentRecord || !previousRecord) {
                    document.getElementById('timeDifference').innerHTML = 'Select dates to see time difference';
                    return;
                }

                // Update time difference
                const timeDiff = calculateTimeDifference(currentRecord.recorded_at, previousRecord.recorded_at);
                document.getElementById('timeDifference').innerHTML = `<strong>Time between records:</strong> ${timeDiff}`;

                // Update the table rows
                updateTableRow('height', currentRecord.height, previousRecord.height);
                updateTableRow('weight', currentRecord.weight, previousRecord.weight);
                updateTableRow('body_fat', currentRecord.body_fat_percentage, previousRecord.body_fat_percentage);
                updateTableRow('bmi', currentRecord.bmi, previousRecord.bmi);
                updateTableRow('body_water', currentRecord.body_water_percentage, previousRecord.body_water_percentage);
                updateTableRow('muscle_mass', currentRecord.muscle_mass, previousRecord.muscle_mass);
                updateTableRow('bone_mass', currentRecord.bone_mass, previousRecord.bone_mass);
                updateTableRow('visceral_fat', currentRecord.visceral_fat, previousRecord.visceral_fat);
                updateTableRow('bmr', currentRecord.bmr, previousRecord.bmr);
                updateTableRow('protein', currentRecord.protein_percentage, previousRecord.protein_percentage);
                updateTableRow('body_age', currentRecord.body_age, previousRecord.body_age);

                // Update the radar chart
                updateRadarChart(currentRecord, previousRecord);
            }

            function calculateTimeDifference(date1, date2) {
                const d1 = new Date(date1);
                const d2 = new Date(date2);
                const diff = Math.abs(d1 - d2);

                const years = Math.floor(diff / (1000 * 60 * 60 * 24 * 365));
                const months = Math.floor((diff % (1000 * 60 * 60 * 24 * 365)) / (1000 * 60 * 60 * 24 * 30));
                const days = Math.floor((diff % (1000 * 60 * 60 * 24 * 30)) / (1000 * 60 * 60 * 24));

                const parts = [];
                if (years > 0) parts.push(`${years} year${years > 1 ? 's' : ''}`);
                if (months > 0) parts.push(`${months} month${months > 1 ? 's' : ''}`);
                if (days > 0) parts.push(`${days} day${days > 1 ? 's' : ''}`);

                return parts.length > 0 ? parts.join(' ') : 'Same day';
            }

            function updateTableRow(metric, currentValue, previousValue) {
                const row = document.querySelector(`tr[data-metric="${metric}"]`);
                if (!row) return;

                const cells = row.querySelectorAll('td');
                if (cells.length >= 4) {
                    // Update current value
                    if (metric === 'height') {
                        cells[1].textContent = currentValue ? `${currentValue}cm` : 'N/A';
                    } else if (metric === 'weight' || metric === 'muscle_mass' || metric === 'bone_mass') {
                        cells[1].textContent = currentValue ? `${currentValue}kg` : 'N/A';
                    } else if (metric === 'body_fat' || metric === 'body_water' || metric === 'protein') {
                        cells[1].textContent = currentValue ? `${currentValue}%` : 'N/A';
                    } else if (metric === 'bmr') {
                        cells[1].textContent = currentValue ? `${currentValue}cal` : 'N/A';
                    } else if (metric === 'body_age') {
                        cells[1].textContent = currentValue ? `${currentValue}yrs` : 'N/A';
                    } else {
                        cells[1].textContent = currentValue || 'N/A';
                    }

                    // Update previous value
                    if (metric === 'height') {
                        cells[2].textContent = previousValue ? `${previousValue}cm` : 'N/A';
                    } else if (metric === 'weight' || metric === 'muscle_mass' || metric === 'bone_mass') {
                        cells[2].textContent = previousValue ? `${previousValue}kg` : 'N/A';
                    } else if (metric === 'body_fat' || metric === 'body_water' || metric === 'protein') {
                        cells[2].textContent = previousValue ? `${previousValue}%` : 'N/A';
                    } else if (metric === 'bmr') {
                        cells[2].textContent = previousValue ? `${previousValue}cal` : 'N/A';
                    } else if (metric === 'body_age') {
                        cells[2].textContent = previousValue ? `${previousValue}yrs` : 'N/A';
                    } else {
                        cells[2].textContent = previousValue || 'N/A';
                    }

                    // Update change cell
                    const changeCell = cells[3];
                    if (currentValue && previousValue) {
                        const change = currentValue - previousValue;
                        let arrow = '';
                        let colorClass = 'text-gray-500';

                        if (change > 0) {
                            arrow = '↑';
                            colorClass = 'text-red-600';
                        } else if (change < 0) {
                            arrow = '↓';
                            colorClass = 'text-green-600';
                        } else {
                            arrow = '—';
                            colorClass = 'text-gray-500';
                        }

                        changeCell.innerHTML = `<span class="${colorClass}">${arrow} ${Math.abs(change).toFixed(1)}</span>`;
                    } else {
                        changeCell.textContent = '-';
                    }
                }
            }



            currentDateSelect.addEventListener('change', updateComparisonTable);
            previousDateSelect.addEventListener('change', updateComparisonTable);

            // Initialize chart with default values
            updateComparisonTable();
        }

        // Initialize radar chart with data from data attributes after a delay to ensure tab is shown
        setTimeout(() => {
            const radarCanvas = document.getElementById('radarChart');
            if (radarCanvas) {
                const currentData = radarCanvas.dataset.current ? JSON.parse(radarCanvas.dataset.current) : null;
                const previousData = radarCanvas.dataset.previous ? JSON.parse(radarCanvas.dataset.previous) : null;
                if (currentData && previousData) {
                    updateRadarChart(currentData, previousData);
                } else {
                    // Initialize with empty chart or message
                    updateRadarChart(null, null);
                }
            }
        }, 1000);

        // Tournament filtering functionality
        const sportFilter = document.getElementById('sportFilter');
        const tournamentsTable = document.getElementById('tournamentsTable');
        const awardCards = document.getElementById('awardCards');

        // Global variables for current filters
        let currentSportFilter = 'all';
        let currentMedalFilter = 'all';

        function applyTournamentFilters() {
            const rows = tournamentsTable.querySelectorAll('tbody tr');

            let visibleRows = 0;
            let specialCount = 0, firstCount = 0, secondCount = 0, thirdCount = 0;

            rows.forEach(row => {
                const sport = row.getAttribute('data-sport');
                const performanceCell = row.querySelector('td:nth-child(3)');
                let hasMatchingMedal = false;

                if (performanceCell) {
                    const badges = performanceCell.querySelectorAll('.badge');
                    badges.forEach(badge => {
                        if (currentMedalFilter === 'all') {
                            hasMatchingMedal = true;
                        } else if (currentMedalFilter === 'special' && badge.textContent.includes('Special Award')) {
                            hasMatchingMedal = true;
                        } else if (currentMedalFilter === '1st' && badge.textContent.includes('1st Place')) {
                            hasMatchingMedal = true;
                        } else if (currentMedalFilter === '2nd' && badge.textContent.includes('2nd Place')) {
                            hasMatchingMedal = true;
                        } else if (currentMedalFilter === '3rd' && badge.textContent.includes('3rd Place')) {
                            hasMatchingMedal = true;
                        }
                    });
                }

                const sportMatch = currentSportFilter === 'all' || sport === currentSportFilter;
                const medalMatch = currentMedalFilter === 'all' || hasMatchingMedal;

                if (sportMatch && medalMatch) {
                    row.style.display = '';
                    visibleRows++;

                    // Count awards in visible rows
                    if (performanceCell) {
                        const badges = performanceCell.querySelectorAll('.badge');
                        badges.forEach(badge => {
                            if (badge.textContent.includes('Special Award')) specialCount++;
                            else if (badge.textContent.includes('1st Place')) firstCount++;
                            else if (badge.textContent.includes('2nd Place')) secondCount++;
                            else if (badge.textContent.includes('3rd Place')) thirdCount++;
                        });
                    }
                } else {
                    row.style.display = 'none';
                }
            });

            // Update award counts
            document.getElementById('specialCount').textContent = specialCount;
            document.getElementById('firstCount').textContent = firstCount;
            document.getElementById('secondCount').textContent = secondCount;
            document.getElementById('thirdCount').textContent = thirdCount;

            // Show/hide award cards based on visible rows
            if (visibleRows === 0) {
                awardCards.style.display = 'none';
            } else {
                awardCards.style.display = '';
            }
        }

        if (sportFilter && tournamentsTable) {
            sportFilter.addEventListener('change', function() {
                currentSportFilter = this.value;
                applyTournamentFilters();
            });
        }

        // Function to filter tournaments by medal type (called from achievement badges)
        window.filterTournamentsByMedal = function(medalType) {
            // Switch to tournaments tab using Alpine.js
            const tournamentsTab = document.getElementById('tournaments-tab');
            if (tournamentsTab) {
                tournamentsTab.click();
            }

            // Set medal filter
            currentMedalFilter = medalType;
            currentSportFilter = 'all'; // Reset sport filter

            // Reset sport filter dropdown
            if (sportFilter) {
                sportFilter.value = 'all';
            }

            // Apply filters
            applyTournamentFilters();
        };

        // Goals filtering functionality
        const goalFilterCards = document.querySelectorAll('.goal-filter-card');
        const goalsContainer = document.querySelector('.row.g-4'); // Container with goal cards
        const goalsTitle = document.querySelector('h5.font-bold'); // The "Goal Tracking" title

        if (goalFilterCards.length > 0 && goalsContainer) {
            // Add click event to filter cards
            goalFilterCards.forEach(card => {
                card.addEventListener('click', function() {
                    const filterType = this.getAttribute('data-filter');
                    filterGoals(filterType);

                    // Update card styles to show active filter
                    goalFilterCards.forEach(c => c.classList.remove('border', 'border-primary', 'shadow-lg'));
                    this.classList.add('border', 'border-primary', 'shadow-lg');
                });
            });

            // Add click event to title to show all goals
            if (goalsTitle) {
                goalsTitle.style.cursor = 'pointer';
                goalsTitle.addEventListener('click', function() {
                    filterGoals('all');
                    // Remove active styles from filter cards
                    goalFilterCards.forEach(c => c.classList.remove('border', 'border-primary', 'shadow-lg'));
                });
            }
        }

        function filterGoals(filterType) {
            const goalCards = document.querySelectorAll('.goal-card');

            goalCards.forEach(card => {
                const statusBadge = card.querySelector('.badge');
                if (!statusBadge) return;

                const statusText = statusBadge.textContent.toLowerCase();

                if (filterType === 'all') {
                    card.style.display = '';
                } else if (filterType === 'active' && statusText === 'active') {
                    card.style.display = '';
                } else if (filterType === 'completed' && statusText === 'completed') {
                    card.style.display = '';
                } else {
                    card.style.display = 'none';
                }
            });

            // Update title to show current filter
            const titleElement = document.querySelector('h5.font-bold');
            if (titleElement) {
                const baseTitle = 'Goal Tracking';
                if (filterType === 'all') {
                    titleElement.innerHTML = `<i class="bi bi-bullseye mr-2"></i>${baseTitle}`;
                } else {
                    const filterLabel = filterType === 'active' ? 'Active' : 'Completed';
                    titleElement.innerHTML = `<i class="bi bi-bullseye mr-2"></i>${baseTitle} - ${filterLabel} Goals`;
                }
            }
        }

        // Goal editing functionality - Now using Alpine.js events
        const editGoalButtons = document.querySelectorAll('.edit-goal-btn');
        const goalEditForm = document.getElementById('goalEditForm');
        let currentGoalId = null;

        // Store goals data for editing
        const goalsData = @json($goals);

        if (editGoalButtons.length > 0) {
            editGoalButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    const goalId = this.getAttribute('data-goal-id');
                    populateGoalEditModal(goalId);
                    window.dispatchEvent(new CustomEvent('open-goal-edit-modal'));
                });
            });
        }

        function populateGoalEditModal(goalId) {
            const goal = goalsData.find(g => g.id == goalId);
            if (!goal) return;

            currentGoalId = goalId;

            // Update form action
            goalEditForm.action = `/member/goal/${goalId}`;

            // Populate modal fields
            document.getElementById('goalTitleDisplay').textContent = goal.title;
            document.getElementById('goalDescriptionDisplay').textContent = goal.description || 'No description';
            document.getElementById('current_progress_value').value = goal.current_progress_value;
            document.getElementById('goal_status').value = goal.status;
            document.getElementById('goalUnitDisplay').textContent = goal.unit;
            document.getElementById('goalTargetDisplay').textContent = `${goal.target_value} ${goal.unit}`;

            // Update icon
            const iconElement = document.getElementById('goalIconDisplay').querySelector('i');
            if (goal.icon_type === 'dumbbell') {
                iconElement.className = 'bi bi-dumbbell text-white';
            } else if (goal.icon_type === 'clock') {
                iconElement.className = 'bi bi-clock text-white';
            } else {
                iconElement.className = 'bi bi-bullseye text-white';
            }

            // Update progress preview
            updateProgressPreview();
        }

        function updateProgressPreview() {
            const currentValue = parseFloat(document.getElementById('current_progress_value').value) || 0;
            const goal = goalsData.find(g => g.id == currentGoalId);
            if (!goal) return;

            const targetValue = goal.target_value;
            const percentage = targetValue > 0 ? Math.min(100, (currentValue / targetValue) * 100) : 0;

            document.getElementById('progressPreview').style.width = `${percentage}%`;
            document.getElementById('progressTextPreview').textContent = `Progress: ${currentValue.toFixed(1)} / ${targetValue.toFixed(1)} ${goal.unit} (${percentage.toFixed(1)}%)`;
        }

        // Update progress preview on input change
        document.getElementById('current_progress_value').addEventListener('input', updateProgressPreview);

        // Patch a goal card in place from the server-returned goal object (no reload)
        function patchGoalCard(goal) {
            const idx = goalsData.findIndex(g => g.id == goal.id);
            if (idx !== -1) {
                goalsData[idx] = Object.assign({}, goalsData[idx], goal);
            }

            const card = document.getElementById('goal-' + goal.id);
            if (!card) return;

            const current = parseFloat(goal.current_progress_value) || 0;
            const target = parseFloat(goal.target_value) || 0;
            const pct = parseFloat(goal.progress_percentage) || 0;

            const progressText = card.querySelector('[data-goal-progress-text]');
            if (progressText) {
                progressText.textContent = `Progress: ${current.toFixed(1)} / ${target.toFixed(1)} ${goal.unit}`;
            }
            const pctText = card.querySelector('[data-goal-progress-pct]');
            if (pctText) {
                pctText.textContent = `${pct.toFixed(1)}%`;
            }
            const bar = card.querySelector('[data-goal-progress-bar]');
            if (bar) {
                bar.style.width = `${pct}%`;
                bar.setAttribute('aria-valuenow', pct);
            }
            const statusBadge = card.querySelector('[data-goal-status-badge]');
            if (statusBadge) {
                statusBadge.classList.remove('bg-primary/10', 'text-primary', 'bg-green-100', 'text-green-800');
                if (goal.status === 'active') {
                    statusBadge.classList.add('bg-primary/10', 'text-primary');
                } else {
                    statusBadge.classList.add('bg-green-100', 'text-green-800');
                }
                statusBadge.textContent = goal.status.charAt(0).toUpperCase() + goal.status.slice(1);
            }
            if (goal.status !== 'active') {
                const editBtn = card.querySelector('.edit-goal-btn');
                if (editBtn) editBtn.remove();
            }
        }

        // Handle form submission
        goalEditForm.addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            formData.append('_method', 'PUT');

            fetch(this.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.dispatchEvent(new CustomEvent('close-goal-edit-modal'));
                    if (data.goal) {
                        patchGoalCard(data.goal);
                        window.dispatchEvent(new CustomEvent('member-profile-updated', { detail: { goal: data.goal } }));
                    }
                    window.showToast('success', data.message || 'Goal updated successfully');
                } else {
                    window.showToast('error', 'Error updating goal: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                window.showToast('error', 'Error updating goal. Please try again.');
            });
        });
    });
</script>

<!-- Chart.js for Skills Wheel -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Affiliations Tab JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Affiliations data
    const affiliationsData = @json($clubAffiliations);

    let skillsChart = null;
    let selectedAffiliationId = null;

    // Initialize affiliations functionality
    function initAffiliations() {
        // Set up timeline click handlers
        document.querySelectorAll('.affiliation-card').forEach(card => {
            card.addEventListener('click', function() {
                const affiliationId = this.getAttribute('data-affiliation-id');
                selectAffiliation(affiliationId);

                // Update visual selection
                document.querySelectorAll('.affiliation-card').forEach(c => {
                    c.classList.remove('border-primary', 'border-2');
                });
                this.classList.add('border-primary', 'border-2');
            });
        });

        // Select first affiliation by default if available
        if (affiliationsData.length > 0) {
            selectAffiliation(affiliationsData[0].id);
        }
    }

    function selectAffiliation(affiliationId) {
        selectedAffiliationId = affiliationId;
        const affiliation = affiliationsData.find(a => a.id == affiliationId);

        if (!affiliation) return;

        // Update skills chart
        updateSkillsChart(affiliation.skill_acquisitions || []);

        // Update affiliation details
        updateAffiliationDetails(affiliation);
    }

    function updateSkillsChart(skills) {
        const ctx = document.getElementById('skillsChart').getContext('2d');
        const noSkillsMessage = document.getElementById('noSkillsMessage');

        if (skills.length === 0) {
            if (skillsChart) {
                skillsChart.destroy();
                skillsChart = null;
            }
            document.getElementById('skillsChart').style.display = 'none';
            noSkillsMessage.classList.remove('hidden');
            return;
        }

        document.getElementById('skillsChart').style.display = 'block';
        noSkillsMessage.classList.add('hidden');

        // Prepare data for polar area chart
        const labels = skills.map(skill => skill.skill_name);
        const data = skills.map(skill => skill.duration_months);
        const backgroundColors = [
            'rgba(54, 162, 235, 0.8)',
            'rgba(255, 99, 132, 0.8)',
            'rgba(255, 205, 86, 0.8)',
            'rgba(75, 192, 192, 0.8)',
            'rgba(153, 102, 255, 0.8)',
            'rgba(255, 159, 64, 0.8)',
            'rgba(199, 199, 199, 0.8)',
            'rgba(83, 102, 255, 0.8)',
            'rgba(255, 99, 255, 0.8)',
            'rgba(99, 255, 132, 0.8)'
        ];

        if (skillsChart) {
            skillsChart.destroy();
        }

        skillsChart = new Chart(ctx, {
            type: 'polarArea',
            data: {
                labels: labels,
                datasets: [{
                    data: data,
                    backgroundColor: backgroundColors.slice(0, skills.length),
                    borderWidth: 2,
                    borderColor: backgroundColors.slice(0, skills.length).map(color => color.replace('0.8', '1')),
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const skill = skills[context.dataIndex];
                                const duration = skill.formatted_duration || `${skill.duration_months} months`;
                                return `${context.label}: ${duration}`;
                            }
                        }
                    }
                },
                scales: {
                    r: {
                        beginAtZero: true,
                        ticks: {
                            display: false
                        }
                    }
                },
                animation: {
                    animateScale: true,
                    animateRotate: true
                }
            }
        });
    }

    function updateAffiliationDetails(affiliation) {
        const detailsContainer = document.getElementById('affiliationDetails');

        let html = `
            <div class="flex items-center mb-3">
                ${affiliation.logo ?
                    `<img src="${affiliation.logo}" alt="${affiliation.club_name}" class="mr-3 rounded" style="width: 50px; height: 50px; object-fit: cover;">` :
                    `<div class="bg-primary text-white rounded flex items-center justify-center mr-3" style="width: 50px; height: 50px;">
                        <i class="bi bi-building"></i>
                    </div>`
                }
                <div>
                    <h5 class="mb-1">${affiliation.club_name}</h5>
                    <p class="text-gray-500 mb-0">${affiliation.date_range}</p>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">${affiliation.formatted_duration}</span>
                </div>
            </div>
        `;

        if (affiliation.location) {
            html += `<p class="mb-2"><i class="bi bi-geo-alt mr-2"></i><strong>Location:</strong> ${affiliation.location}</p>`;
        }

        if (affiliation.description) {
            html += `<p class="mb-2"><strong>Description:</strong> ${affiliation.description}</p>`;
        }

        if (affiliation.coaches && affiliation.coaches.length > 0) {
            html += `<p class="mb-2"><strong>Coaches:</strong> ${affiliation.coaches.join(', ')}</p>`;
        }

        if (affiliation.affiliation_media && affiliation.affiliation_media.length > 0) {
            html += `<div class="mt-3"><strong>Media & Certificates:</strong></div>`;
            html += `<div class="grid grid-cols-2 gap-2 mt-1">`;

            affiliation.affiliation_media.forEach(media => {
                const iconClass = media.icon_class || 'bi-file';
                html += `
                    <div>
                        <a href="${media.full_url}" target="_blank" class="border border-gray-300 text-gray-700 px-2 py-1 rounded text-xs hover:bg-gray-100 transition-colors w-full">
                            <i class="bi ${iconClass} mr-1"></i>${media.title || media.media_type}
                        </a>
                    </div>
                `;
            });

            html += `</div>`;
        }

        detailsContainer.innerHTML = html;
    }

    // Initialize when affiliations tab is clicked (Alpine.js approach)
    const affiliationsTab = document.getElementById('affiliations-tab');
    if (affiliationsTab) {
        affiliationsTab.addEventListener('click', function() {
            // Delay to allow tab content to be visible
            setTimeout(initAffiliations, 100);
        });
    }

    // Initialize on page load after a delay
    setTimeout(initAffiliations, 500);
});

// Tournament Participation Modal Functionality
document.addEventListener('DOMContentLoaded', function() {
    let performanceResultIndex = 1;
    let notesMediaIndex = 1;

    // Add Performance Result
    document.getElementById('addPerformanceResult').addEventListener('click', function() {
        const container = document.getElementById('performanceResultsContainer');
        const newItem = document.createElement('div');
        newItem.className = 'performance-result-item mb-3 p-3 border rounded';
        newItem.innerHTML = `
            <div class="grid grid-cols-12 gap-2">
                <div class="col-span-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Medal Type</label>
                    <select class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary medal-type" name="performance_results[${performanceResultIndex}][medal_type]">
                        <option value="">Select Medal</option>
                        <option value="special">Special Award</option>
                        <option value="1st">1st Place</option>
                        <option value="2nd">2nd Place</option>
                        <option value="3rd">3rd Place</option>
                    </select>
                </div>
                <div class="col-span-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Points</label>
                    <input type="number" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" name="performance_results[${performanceResultIndex}][points]" min="0" step="0.1">
                </div>
                <div class="col-span-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <input type="text" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" name="performance_results[${performanceResultIndex}][description]" placeholder="Optional description">
                </div>
                <div class="col-span-1 flex items-end">
                    <button type="button" class="border border-red-500 text-red-500 px-2 py-1 rounded text-xs hover:bg-red-500 hover:text-white transition-colors remove-result">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
        `;
        container.appendChild(newItem);
        performanceResultIndex++;

        // Show remove buttons if more than one result
        updateRemoveButtons('performance-result-item', 'remove-result');
    });

    // Add Notes & Media
    document.getElementById('addNotesMedia').addEventListener('click', function() {
        const container = document.getElementById('notesMediaContainer');
        const newItem = document.createElement('div');
        newItem.className = 'notes-media-item mb-3 p-3 border rounded';
        newItem.innerHTML = `
            <div class="grid grid-cols-12 gap-2">
                <div class="col-span-6">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Note Text</label>
                    <textarea class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" name="notes_media[${notesMediaIndex}][note_text]" rows="2" placeholder="Optional notes about the tournament"></textarea>
                </div>
                <div class="col-span-5">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Media Link</label>
                    <input type="url" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" name="notes_media[${notesMediaIndex}][media_link]" placeholder="https://example.com/photo.jpg">
                </div>
                <div class="col-span-1 flex items-end">
                    <button type="button" class="border border-red-500 text-red-500 px-2 py-1 rounded text-xs hover:bg-red-500 hover:text-white transition-colors remove-note">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
        `;
        container.appendChild(newItem);
        notesMediaIndex++;

        // Show remove buttons if more than one note
        updateRemoveButtons('notes-media-item', 'remove-note');
    });

    // Remove Performance Result
    document.addEventListener('click', function(e) {
        if (e.target.closest('.remove-result')) {
            e.target.closest('.performance-result-item').remove();
            updateRemoveButtons('performance-result-item', 'remove-result');
        }
    });

    // Remove Notes & Media
    document.addEventListener('click', function(e) {
        if (e.target.closest('.remove-note')) {
            e.target.closest('.notes-media-item').remove();
            updateRemoveButtons('notes-media-item', 'remove-note');
        }
    });

    function updateRemoveButtons(itemClass, buttonClass) {
        const items = document.querySelectorAll('.' + itemClass);
        const buttons = document.querySelectorAll('.' + buttonClass);

        if (items.length > 1) {
            buttons.forEach(button => button.style.display = 'block');
        } else {
            buttons.forEach(button => button.style.display = 'none');
        }
    }

    // Reset modal when opened - Listen for Alpine.js custom event
    window.addEventListener('open-tournament-modal', function() {
        // Reset form
        document.getElementById('tournamentParticipationForm').reset();

        // Reset dynamic content
        const performanceContainer = document.getElementById('performanceResultsContainer');
        const notesContainer = document.getElementById('notesMediaContainer');

        // Keep only the first item in each container
        const performanceItems = performanceContainer.querySelectorAll('.performance-result-item');
        const notesItems = notesContainer.querySelectorAll('.notes-media-item');

        for (let i = 1; i < performanceItems.length; i++) {
            performanceItems[i].remove();
        }
        for (let i = 1; i < notesItems.length; i++) {
            notesItems[i].remove();
        }

        // Reset indices
        performanceResultIndex = 1;
        notesMediaIndex = 1;

        // Hide remove buttons
        document.querySelectorAll('.remove-result, .remove-note').forEach(button => {
            button.style.display = 'none';
        });
    });

    // Delete Account Modal functionality is now handled by Alpine.js

    // Handle form submission
    document.getElementById('tournamentParticipationForm').addEventListener('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(this);

        fetch(this.action, {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Close modal and insert the new row in place (no reload)
                window.dispatchEvent(new CustomEvent('close-tournament-modal'));
                if (data.tournament) {
                    addTournamentRow(data.tournament);
                    window.dispatchEvent(new CustomEvent('member-profile-updated', { detail: { tournament: data.tournament } }));
                }
                showAlert('Tournament record added successfully!', 'success');
            } else {
                showAlert('Error adding tournament record: ' + (data.message || 'Unknown error'), 'danger');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('Error adding tournament record. Please try again.', 'danger');
        });
    });

    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str).replace(/[&<>"']/g, s => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        })[s]);
    }

    function buildTournamentRow(t) {
        const tr = document.createElement('tr');
        tr.setAttribute('data-sport', t.sport || '');

        let perfHtml = '';
        if (t.performance_results && t.performance_results.length > 0) {
            t.performance_results.forEach(r => {
                let medal = '';
                if (r.medal_type === '1st') {
                    medal = '<i class="bi bi-award-fill text-warning"></i><span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">1st Place</span>';
                } else if (r.medal_type === '2nd') {
                    medal = '<i class="bi bi-award-fill text-secondary"></i><span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">2nd Place</span>';
                } else if (r.medal_type === '3rd') {
                    medal = '<i class="bi bi-award-fill" style="color: #CD7F32;"></i><span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium text-white" style="background-color: #CD7F32;">3rd Place</span>';
                } else if (r.medal_type === 'special') {
                    medal = '<i class="bi bi-trophy-fill text-warning"></i><span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">Special Award</span>';
                }
                perfHtml += `<div class="flex items-center gap-2 mb-1">${medal}${r.points ? `<small class="text-gray-500">${escapeHtml(r.points)} pts</small>` : ''}</div>`;
                if (r.description) {
                    perfHtml += `<small class="text-gray-500">${escapeHtml(r.description)}</small>`;
                }
            });
        } else {
            perfHtml = '<span class="text-gray-500 text-sm">No results recorded</span>';
        }

        let notesHtml = '';
        if (t.notes_media && t.notes_media.length > 0) {
            t.notes_media.forEach(n => {
                if (n.note_text) notesHtml += `<p class="mb-1 small">${escapeHtml(n.note_text)}</p>`;
                if (n.media_link) notesHtml += `<a href="${escapeHtml(n.media_link)}" target="_blank" class="border border-primary text-primary px-2 py-1 rounded text-xs hover:bg-primary hover:text-white transition-colors"><i class="bi bi-image mr-1"></i>View Media</a>`;
            });
        } else {
            notesHtml = '<span class="text-gray-500 text-sm">No notes available</span>';
        }

        let affHtml;
        if (t.club_affiliation) {
            affHtml = `<div><div class="small font-semibold">${escapeHtml(t.club_affiliation.club_name)}</div><div class="text-gray-500 text-sm">${escapeHtml(t.club_affiliation.location)}</div></div>`;
        } else {
            affHtml = '<span class="text-gray-500 text-sm">Individual</span>';
        }

        let meta = `<i class="bi bi-calendar-event mr-1"></i>${escapeHtml(t.date)}`;
        if (t.time) meta += `<i class="bi bi-clock mr-1 ml-2"></i>${escapeHtml(t.time)}`;
        if (t.location) meta += `<i class="bi bi-geo-alt mr-1 ml-2"></i>${escapeHtml(t.location)}`;
        if (t.participants_count) meta += `<i class="bi bi-people mr-1 ml-2"></i>${escapeHtml(t.participants_count)} participants`;

        const typeBadge = (t.type === 'championship') ? 'bg-primary/10 text-primary' : 'bg-gray-100 text-gray-800';

        tr.innerHTML = `
            <td>
                <div class="font-bold">${escapeHtml(t.title)}</div>
                <div class="flex gap-2 mt-1 flex-wrap">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${typeBadge}">${escapeHtml(t.type_label)}</span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">${escapeHtml(t.sport)}</span>
                </div>
                <div class="text-gray-500 text-sm mt-1">${meta}</div>
            </td>
            <td>${affHtml}</td>
            <td>${perfHtml}</td>
            <td>${notesHtml}</td>`;
        return tr;
    }

    function addTournamentRow(t) {
        const tbody = document.getElementById('tournamentsTableBody');
        if (!tbody) return;
        tbody.insertBefore(buildTournamentRow(t), tbody.firstChild);
        const wrapper = document.getElementById('tournamentsTableWrapper');
        if (wrapper) wrapper.style.display = '';
        const empty = document.getElementById('tournamentsEmptyState');
        if (empty) empty.style.display = 'none';
    }

    function showAlert(message, type) {
        // Route through the global toast — never render an inline alert on the page.
        window.showToast(type === 'danger' ? 'error' : type, message);
    }
});
</script>
<!-- Edit Profile Modal Component -->
<x-profile-modal
    :user="$relationship->dependent"
    :formAction="$relationship->relationship_type === 'admin_view' ? route('admin.platform.members.update', $relationship->dependent->id) : route('member.update', $relationship->dependent->id)"
    formMethod="PUT"
    :cancelUrl="null"
    :uploadUrl="$relationship->relationship_type === 'admin_view' ? route('admin.platform.members.upload-picture', $relationship->dependent->id) : route('member.upload-picture', $relationship->dependent->id)"
    :showRelationshipFields="$relationship->relationship_type !== 'admin_view' && $relationship->relationship_type !== 'self'"
    :relationship="$relationship"
/>

<!-- Reset Password Modal -->
@if($canResetPassword)
<div x-data="{ open: false }" @open-reset-password-modal.window="open = true" @close-reset-password-modal.window="open = false; document.getElementById('resetPasswordForm').reset(); document.getElementById('resetPasswordError').classList.add('hidden')" x-cloak>
    <div x-show="open" class="fixed inset-0 z-50 overflow-y-auto" @keydown.escape.window="open = false">
        <div x-show="open" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
             class="fixed inset-0 bg-black/50" @click="open = false"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div x-show="open" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
                 class="relative bg-white rounded-lg shadow-xl w-full max-w-md border border-gray-200" @click.stop>
                <div class="flex items-center justify-between p-4 border-b rounded-t-lg">
                    <h5 class="font-medium text-lg flex items-center">
                        <i class="bi bi-key-fill text-amber-500 mr-2"></i>Reset Password
                    </h5>
                    <button type="button" @click="open = false" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
                </div>
                <form id="resetPasswordForm" onsubmit="submitResetPassword(event)">
                    @csrf
                    <div class="p-4 space-y-4">
                        <p class="text-sm text-gray-500">Set a new password for <strong>{{ $relationship->dependent->full_name }}</strong>.</p>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
                            <input type="password" id="resetNewPassword" name="password" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" placeholder="Min. 8 characters" required minlength="8">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label>
                            <input type="password" id="resetPasswordConfirm" name="password_confirmation" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" placeholder="Repeat password" required minlength="8">
                        </div>
                        <div id="resetPasswordError" class="hidden p-3 rounded-lg bg-red-50 text-red-700 border border-red-200 text-sm"></div>
                    </div>
                    <div class="flex justify-end gap-2 p-4 border-t bg-gray-50 rounded-b-lg">
                        <button type="button" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-md text-sm font-medium hover:bg-gray-300 transition-colors" @click="open = false">Cancel</button>
                        <button type="submit" id="resetPasswordSubmitBtn" class="bg-primary text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-primary/90 transition-colors">
                            <i class="bi bi-key mr-1"></i>Reset Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<script>
function submitResetPassword(e) {
    e.preventDefault();
    const newPass = document.getElementById('resetNewPassword').value;
    const confirm = document.getElementById('resetPasswordConfirm').value;
    const errEl = document.getElementById('resetPasswordError');
    const btn = document.getElementById('resetPasswordSubmitBtn');

    errEl.classList.add('hidden');

    if (newPass !== confirm) {
        errEl.textContent = 'Passwords do not match.';
        errEl.classList.remove('hidden');
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split mr-1"></i>Resetting...';

    fetch('{{ route('member.reset-password', $relationship->dependent->id) }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
        body: JSON.stringify({ password: newPass, password_confirmation: confirm }),
    })
    .then(r => r.json().then(data => ({ ok: r.ok, data })))
    .then(({ ok, data }) => {
        if (ok) {
            window.dispatchEvent(new CustomEvent('close-reset-password-modal'));
            showToast('success', 'Success', data.message || 'Password reset successfully.');
        } else {
            errEl.textContent = data.message || (data.errors?.password?.[0] ?? 'Something went wrong.');
            errEl.classList.remove('hidden');
        }
    })
    .catch(() => {
        errEl.textContent = 'An error occurred. Please try again.';
        errEl.classList.remove('hidden');
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-key mr-1"></i>Reset Password';
    });
}
</script>
@endif

<!-- Delete Account Modal -->
<div x-data="{ open: false, confirmName: '', expectedName: '{{ $relationship->dependent->full_name }}' }" @open-delete-account-modal.window="open = true; confirmName = ''" x-cloak>
    <div x-show="open" class="fixed inset-0 z-50 overflow-y-auto" @keydown.escape.window="open = false">
        <div x-show="open" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
             class="fixed inset-0 bg-black/50" @click="open = false"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div x-show="open" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
                 class="relative bg-white rounded-lg shadow-xl w-full max-w-md border-2 border-danger" @click.stop>
                <div class="flex items-center justify-between p-4 bg-danger text-white rounded-t-lg">
                    <h5 class="font-medium text-lg flex items-center">
                        <i class="bi bi-exclamation-triangle-fill mr-2"></i>Delete Account
                    </h5>
                    <button type="button" @click="open = false" class="text-white hover:text-gray-200 text-2xl leading-none">&times;</button>
                </div>
                <form id="deleteAccountForm" method="POST" action="{{ $relationship->relationship_type === 'admin_view' ? route('admin.platform.members.destroy', $relationship->dependent->id) : route('member.confirm-delete', $relationship->dependent->id) }}">
                    @csrf
                    @method('DELETE')
                    <div class="p-4">
                        <div class="text-center mb-4">
                            <i class="bi bi-exclamation-triangle-fill text-red-600" style="font-size: 3rem;"></i>
                        </div>

                        <div class="p-4 rounded-lg bg-red-50 text-red-700 border border-red-200">
                            <strong>Warning!</strong> This action cannot be undone. This will permanently delete the account for <strong>{{ $relationship->dependent->full_name }}</strong> and remove all associated data.
                        </div>

                        <p class="text-gray-500 text-sm mb-3">
                            To confirm deletion, please type the full name of the account holder below:
                        </p>

                        <div class="mb-3">
                            <label for="confirmName" class="block text-sm font-medium text-gray-700 mb-1 font-semibold">Type "{{ $relationship->dependent->full_name }}" to confirm:</label>
                            <input type="text" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" id="confirmName" name="confirm_name" x-model="confirmName" required>
                            <div class="text-xs text-gray-500 mt-1 text-gray-500">
                                This action will soft delete the account. The account can be restored by an administrator if needed.
                            </div>
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 p-4 border-t bg-gray-50">
                        <button type="button" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-md text-sm font-medium hover:bg-gray-300 transition-colors" @click="open = false">Cancel</button>
                        <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-red-700 transition-colors disabled:opacity-50" :disabled="confirmName !== expectedName">
                            <i class="bi bi-trash mr-2"></i>Delete Account
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
window.addEventListener('member-profile-updated', function(e) {
    const m = e.detail;

    // Name
    const nameEl = document.getElementById('profile-display-name');
    if (nameEl) nameEl.textContent = m.full_name;

    // Motto
    const mottoEl = document.getElementById('profile-display-motto');
    if (mottoEl) {
        if (m.motto) {
            mottoEl.textContent = '"' + m.motto + '"';
            mottoEl.style.display = '';
        } else {
            mottoEl.style.display = 'none';
        }
    }

    // Age in status row
    const statusRow = document.getElementById('profile-status-row');
    if (statusRow && m.age !== undefined) {
        const ageSpan = statusRow.querySelector('[data-profile-age]');
        if (ageSpan) ageSpan.textContent = m.age;
    }

    // Blood type
    const bloodWrap = document.getElementById('profile-blood-type-wrap');
    if (bloodWrap) {
        if (m.blood_type) {
            bloodWrap.querySelector('[data-profile-blood-type]').textContent = m.blood_type;
            bloodWrap.style.display = '';
        } else {
            bloodWrap.style.display = 'none';
        }
    }

    // Marital status
    const maritalWrap = document.getElementById('profile-marital-wrap');
    if (maritalWrap) {
        if (m.marital_status) {
            maritalWrap.querySelector('[data-profile-marital]').textContent = m.marital_status.charAt(0).toUpperCase() + m.marital_status.slice(1);
            maritalWrap.style.display = '';
        } else {
            maritalWrap.style.display = 'none';
        }
    }

    // Social links
    const socialRow = document.getElementById('profile-social-row');
    if (socialRow) {
        const icons = { facebook:'bi-facebook', twitter:'X', instagram:'bi-instagram', linkedin:'bi-linkedin', youtube:'bi-youtube', tiktok:'bi-tiktok', snapchat:'bi-snapchat', whatsapp:'bi-whatsapp', telegram:'bi-telegram', discord:'bi-discord', reddit:'bi-reddit', pinterest:'bi-pinterest', twitch:'bi-twitch', github:'bi-github', spotify:'bi-spotify', skype:'bi-skype', slack:'bi-slack', medium:'bi-medium', vimeo:'bi-vimeo', messenger:'bi-messenger', wechat:'bi-wechat', line:'bi-line' };
        const titles = { facebook:'Facebook', twitter:'Twitter/X', instagram:'Instagram', linkedin:'LinkedIn', youtube:'YouTube', tiktok:'TikTok', snapchat:'Snapchat', whatsapp:'WhatsApp', telegram:'Telegram', discord:'Discord', reddit:'Reddit', pinterest:'Pinterest', twitch:'Twitch', github:'GitHub', spotify:'Spotify', skype:'Skype', slack:'Slack', medium:'Medium', vimeo:'Vimeo', messenger:'Messenger', wechat:'WeChat', line:'Line' };
        const links = m.social_links || {};
        const keys = Object.keys(links).sort();
        if (keys.length) {
            socialRow.innerHTML = keys.filter(p => links[p] && icons[p]).map(p =>
                p === 'twitter'
                ? `<a href="${links[p]}" target="_blank" rel="noopener noreferrer" class="w-8 h-8 rounded-full border border-gray-300 flex items-center justify-center text-gray-600 hover:bg-gray-100 transition-colors" title="${titles[p]}"><span style="font-weight:bold;font-size:1.2rem">X</span></a>`
                : `<a href="${links[p]}" target="_blank" rel="noopener noreferrer" class="w-8 h-8 rounded-full border border-gray-300 flex items-center justify-center text-gray-600 hover:bg-gray-100 transition-colors" title="${titles[p]}"><i class="bi ${icons[p]}"></i></a>`
            ).join('');
            socialRow.style.display = '';
        } else {
            socialRow.innerHTML = '';
            socialRow.style.display = 'none';
        }
    }

    // Emergency contacts
    const ecList = document.getElementById('emergency-contacts-list');
    if (ecList) {
        const contacts = m.emergency_contacts || [];
        if (contacts.length) {
            ecList.innerHTML = '<div class="flex flex-col gap-3">' + contacts.map(c =>
                `<div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                    <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center flex-shrink-0"><i class="bi bi-person-fill text-red-500"></i></div>
                    <div class="flex-1 min-w-0">
                        <div class="font-semibold text-sm text-gray-800">${c.name || '—'}</div>
                        <div class="text-xs text-gray-500">${c.relationship ? c.relationship.charAt(0).toUpperCase() + c.relationship.slice(1) : ''}</div>
                    </div>
                    ${(c.phone) ? `<a href="tel:${c.phone_code || ''}${c.phone}" class="flex items-center gap-1 text-xs text-primary font-medium hover:underline flex-shrink-0"><i class="bi bi-telephone"></i> ${c.phone_code || ''} ${c.phone}</a>` : ''}
                </div>`
            ).join('') + '</div>';
        } else {
            ecList.innerHTML = '<div class="text-center py-6"><i class="bi bi-telephone text-gray-300" style="font-size:2rem;"></i><p class="text-gray-400 text-sm mt-2 mb-0">No emergency contacts added</p></div>';
        }
    }

    // Identity documents
    const docsList = document.getElementById('documents-list');
    if (docsList) {
        const docs = m.documents || [];
        if (docs.length) {
            docsList.innerHTML = '<div class="flex flex-col gap-3">' + docs.map(d =>
                `<div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                    <div class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center flex-shrink-0"><i class="bi bi-card-text text-primary"></i></div>
                    <div class="flex-1 min-w-0">
                        <div class="font-semibold text-sm text-gray-800">${d.type || '—'}</div>
                        <div class="text-xs text-gray-500 font-mono">${d.number || ''}</div>
                        ${d.uploaded_at ? `<div class="text-xs text-gray-400">Uploaded ${d.uploaded_at}</div>` : ''}
                    </div>
                    ${d.file_path ? `<a href="/storage/${d.file_path}" target="_blank" class="flex-shrink-0 w-8 h-8 rounded-lg border border-primary text-primary flex items-center justify-center hover:bg-primary hover:text-white transition-colors"><i class="bi bi-eye" style="font-size:0.85rem;"></i></a>` : ''}
                </div>`
            ).join('') + '</div>';
        } else {
            docsList.innerHTML = '<div class="text-center py-6"><i class="bi bi-file-earmark text-gray-300" style="font-size:2rem;"></i><p class="text-gray-400 text-sm mt-2 mb-0">No documents uploaded</p></div>';
        }
    }

    // Health conditions
    const hcCard = document.getElementById('health-conditions-card');
    const hcList = document.getElementById('health-conditions-list');
    if (hcCard && hcList) {
        const conditions = m.health_conditions || [];
        if (conditions.length) {
            hcList.innerHTML = conditions.map(c =>
                `<div class="flex items-start gap-3 p-3 bg-amber-50 border border-amber-100 rounded-lg">
                    <div class="w-9 h-9 rounded-full bg-amber-100 flex items-center justify-center flex-shrink-0 mt-0.5"><i class="bi bi-exclamation-circle-fill text-amber-500"></i></div>
                    <div class="flex-1 min-w-0">
                        <div class="font-semibold text-sm text-gray-800">${c.condition || '—'}</div>
                        ${c.noted_at ? `<div class="text-xs text-gray-400 mt-0.5">Noted ${c.noted_at}</div>` : ''}
                        ${c.notes ? `<div class="text-xs text-gray-500 mt-1">${c.notes}</div>` : ''}
                    </div>
                </div>`
            ).join('');
            hcCard.style.display = '';
        } else {
            hcCard.style.display = 'none';
        }
    }
});
</script>
@endpush
