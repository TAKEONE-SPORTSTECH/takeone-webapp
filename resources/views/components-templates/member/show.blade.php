@extends('layouts.app')

@php
    // Guarded: this view and family/show both declare this helper; the guard
    // prevents a "cannot redeclare" fatal if both ever render in one process.
    if (! function_exists('calculateTimeDifference')) {
        function calculateTimeDifference($date1, $date2) {
            $diff = $date1->diff($date2);
            $parts = [];

            if ($diff->y > 0) $parts[] = $diff->y . ' year' . ($diff->y > 1 ? 's' : '');
            if ($diff->m > 0) $parts[] = $diff->m . ' month' . ($diff->m > 1 ? 's' : '');
            if ($diff->d > 0) $parts[] = $diff->d . ' day' . ($diff->d > 1 ? 's' : '');

            return implode(' ', $parts) ?: 'Same day';
        }
    }
@endphp

@section('content')
<div class="px-4 sm:px-6 lg:px-8 py-4">

    <!-- Header -->
    <div class="flex justify-between items-center mb-4">
        <div>
            <h2 class="font-bold mb-1 text-xl">{{ __('member.templates_member_show_member_profile') }}</h2>
            <p class="text-gray-500-foreground mb-0">{{ __('member.templates_member_show_member_profile_subtitle') }}</p>
        </div>
        <div>
            <button onclick="window.history.back()" class="border border-primary text-primary bg-transparent px-4 py-2 rounded-md text-sm font-medium hover:bg-primary hover:text-white transition-colors">
                <i class="bi bi-arrow-left me-2"></i>{{ __('shared.back') }}
            </button>
        </div>
    </div>

    <!-- Profile Card -->
    <div class="bg-white rounded-xl shadow-sm mb-4">
        <div class="flex flex-col sm:flex-row">
            <!-- Profile Picture -->
            <div class="relative w-full sm:w-[180px] aspect-[3/4] overflow-hidden rounded-t-xl sm:rounded-t-none sm:rounded-s-xl flex-shrink-0">
                @if($relationship->dependent->profile_picture)
                    <img id="member-profile-pic" src="{{ asset('storage/' . $relationship->dependent->profile_picture) }}?v={{ $relationship->dependent->updated_at->timestamp }}" alt="{{ $relationship->dependent->full_name }}" class="w-full h-full" style="object-fit: cover;">
                @endif
                <div id="member-profile-placeholder" class="w-full h-full flex items-center justify-center text-white font-bold" style="font-size: 3rem; background: linear-gradient(135deg, {{ $relationship->dependent->gender === 'Male' ? '#0d6efd 0%, #0a58ca 100%' : '#d63384 0%, #a61e4d 100%' }}); {{ $relationship->dependent->profile_picture ? 'display:none;' : '' }}">
                    {{ mb_strtoupper(mb_substr($relationship->dependent->full_name, 0, 1, 'UTF-8'), 'UTF-8') }}
                </div>
                @if($relationship->relationship_type == 'self' || Auth::id() == $relationship->guardian_user_id || $relationship->relationship_type == 'admin_view')
                    <button type="button"
                            onclick="window.dispatchEvent(new CustomEvent('open-photo-edit-modal'))"
                            class="absolute top-2 right-2 w-8 h-8 rounded-full bg-white shadow-sm border border-gray-100 flex items-center justify-center text-primary hover:bg-accent transition-colors"
                            aria-label="{{ __('member.templates_member_show_edit_photo') }}">
                        <i class="bi bi-pencil-fill text-sm"></i>
                    </button>
                @endif
            </div>

            <!-- Profile Info -->
            <div class="flex-1 p-4">
                <div class="flex justify-between items-start mb-2">
                    <h3 class="font-bold mb-0" id="profile-display-name">{{ $relationship->dependent->full_name }}</h3>
                    @if($relationship->relationship_type == 'self' || Auth::id() == $relationship->guardian_user_id)
                        <div x-data="{ open: false }" class="relative">
                            <button @click="open = !open" class="bg-primary text-white px-4 py-2 rounded-full text-sm font-medium hover:bg-primary/90 transition-colors" type="button">
                                <i class="bi bi-lightning me-1"></i>{{ __('member.templates_member_show_action') }}
                            </button>
                            <ul x-show="open" x-cloak @click.outside="open = false"
                                class="absolute end-0 mt-1 bg-white py-1 z-50 list-none w-64 max-w-[calc(100vw-2rem)]"
                                style="border: 1px solid rgba(0,0,0,.1); border-radius: 0.625rem; box-shadow: 0 0.5rem 1.5rem rgba(0,0,0,.12);">
                                <li><a class="flex items-center gap-2 py-2 px-4 text-gray-800 no-underline whitespace-nowrap hover:bg-gray-50 text-sm" href="#" @click="$dispatch('open-attendance-add-modal'); open = false"><i class="bi bi-calendar-check text-green-600" style="width:16px;text-align:center"></i>{{ __('member.templates_member_show_add_attendance_record') }}</a></li>
                                <li><a class="flex items-center gap-2 py-2 px-4 text-gray-800 no-underline whitespace-nowrap hover:bg-gray-50 text-sm" href="#" @click="$dispatch('open-event-add-modal'); open = false"><i class="bi bi-calendar-event text-blue-500" style="width:16px;text-align:center"></i>{{ __('member.templates_member_show_add_event_participation') }}</a></li>
                                <li><a class="flex items-center gap-2 py-2 px-4 text-gray-800 no-underline whitespace-nowrap hover:bg-gray-50 text-sm" href="#" @click="$dispatch('open-health-update-modal'); open = false"><i class="bi bi-heart-pulse text-red-500" style="width:16px;text-align:center"></i>{{ __('member.templates_member_show_add_health_update') }}</a></li>
                                <li><a class="flex items-center gap-2 py-2 px-4 text-gray-800 no-underline whitespace-nowrap hover:bg-gray-50 text-sm" href="#" @click="$dispatch('open-tournament-modal'); open = false"><i class="bi bi-award text-amber-500" style="width:16px;text-align:center"></i>{{ __('member.templates_member_show_add_tournament_participation') }}</a></li>
                                <li><a class="flex items-center gap-2 py-2 px-4 text-gray-800 no-underline whitespace-nowrap hover:bg-gray-50 text-sm" href="#" @click="$dispatch('open-profile-modal'); open = false"><i class="bi bi-pencil text-gray-500" style="width:16px;text-align:center"></i>{{ __('member.templates_member_show_edit_info') }}</a></li>
                                <li><a class="flex items-center gap-2 py-2 px-4 text-gray-800 no-underline whitespace-nowrap hover:bg-gray-50 text-sm" href="#" @click="$dispatch('open-goal-add-modal'); open = false"><i class="bi bi-bullseye text-blue-600" style="width:16px;text-align:center"></i>{{ __('member.templates_member_show_set_a_goal') }}</a></li>
                                @if($canResetPassword)
                                <li><a class="flex items-center gap-2 py-2 px-4 text-gray-800 no-underline whitespace-nowrap hover:bg-gray-50 text-sm" href="#" @click="$dispatch('open-reset-password-modal'); open = false"><i class="bi bi-key text-amber-600" style="width:16px;text-align:center"></i>{{ __('member.templates_member_show_reset_password') }}</a></li>
                                @endif
                                @if($canRegeneratePassword ?? false)
                                <li><a class="flex items-center gap-2 py-2 px-4 text-gray-800 no-underline whitespace-nowrap hover:bg-gray-50 text-sm" href="#" @click="regenerateMemberPassword(); open = false"><i class="bi bi-magic text-primary" style="width:16px;text-align:center"></i>{{ __('member.templates_member_show_generate_password') }}</a></li>
                                @endif
                                <li><hr class="my-1 border-0 border-t border-gray-100"></li>
                                <li><a class="flex items-center gap-2 py-2 px-4 text-red-600 no-underline whitespace-nowrap hover:bg-red-50 text-sm" href="#" @click="$dispatch('open-delete-account-modal'); open = false"><i class="bi bi-trash" style="width:16px;text-align:center"></i>{{ __('member.templates_member_show_delete_account') }}</a></li>
                            </ul>
                        </div>
                    @else
                        <button class="bg-primary text-white px-3 py-1.5 rounded-full text-xs font-medium hover:bg-primary/90 transition-colors">
                            <i class="bi bi-person-plus me-1"></i>{{ __('member.templates_member_show_follow') }}
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

                            {{-- Self-reported medals are visible but never counted as verified in the hero badges above --}}
                            @php $selfReportedTotal = array_sum($selfReportedCounts ?? []); @endphp
                            @if($selfReportedTotal > 0)
                                <div class="flex items-center gap-1.5 mb-3 text-xs text-gray-400" title="{{ __('Self-reported achievements are shown on your profile but are not counted as verified until a club confirms them.') }}">
                                    <i class="bi bi-person-badge"></i>
                                    <span>{{ __('+:count self-reported awaiting verification', ['count' => $selfReportedTotal]) }}</span>
                                </div>
                            @endif

                            <!-- Status Badges -->
                            <div id="profile-status-row" class="flex gap-3 mb-3 items-center flex-wrap">
                                <span class="text-gray-500 text-sm">
                                    <span class="font-semibold text-gray-900 nationality-display" data-iso3="{{ $relationship->dependent->nationality }}">{{ $relationship->dependent->nationality }}</span>
                                </span>
                                <span class="text-gray-500 text-sm">
                                    <i class="bi bi-{{ $relationship->dependent->gender === 'Male' ? 'gender-male' : 'gender-female' }} me-1" style="font-size: 1.1rem; color: {{ $relationship->dependent->gender === 'Male' ? '#17a2b8' : '#6f42c1' }};"></i>
                                    <span class="font-semibold text-gray-900">{{ $relationship->dependent->gender === 'Male' ? __('member.templates_member_show_gender_male') : __('member.templates_member_show_gender_female') }}</span>
                                </span>
                                <span id="profile-marital-wrap" class="text-gray-500 text-sm"@if(!$relationship->dependent->marital_status) style="display:none"@endif>
                                    <i class="bi bi-heart me-1" style="color: #e91e63;"></i>
                                    <span class="font-semibold text-gray-900" data-profile-marital>{{ ucfirst($relationship->dependent->marital_status ?? '') }}</span>
                                </span>
                                <span class="text-gray-500 text-sm">
                                    <i class="bi bi-calendar-event me-1"></i>
                                    {{ __('member.templates_member_show_status_age') }} <span class="font-semibold text-gray-900" data-profile-age>{{ $relationship->dependent->age }}</span>
                                </span>
                                <span id="profile-blood-type-wrap" class="text-gray-500 text-sm"@if(!$relationship->dependent->blood_type) style="display:none"@endif>
                                    <i class="bi bi-droplet-fill text-red-600 me-1"></i>
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
                                    <i class="bi bi-check-circle-fill text-green-600 me-1"></i>
                                    <span class="font-semibold text-green-600">{{ __('member.templates_member_show_status_active') }}</span>
                                </span>
                                <span class="text-gray-500 text-sm">
                                    <i class="bi bi-calendar-check me-1"></i>
                                    {{ __('member.templates_member_show_status_joined') }} <span class="font-semibold text-gray-900">{{ $relationship->dependent->created_at->format('F Y') }}</span>
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
                    <i class="bi bi-eye me-2"></i>{{ __('member.templates_member_show_tab_overview') }}
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button @click="activeTab = 'attendance'" :class="{ 'active': activeTab === 'attendance' }" class="nav-link text-dark" id="attendance-tab" type="button" role="tab">
                    <i class="bi bi-calendar-check me-2"></i>{{ __('member.templates_member_show_tab_attendance') }}
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button @click="activeTab = 'health'" :class="{ 'active': activeTab === 'health' }" class="nav-link text-dark" id="health-tab" type="button" role="tab">
                    <i class="bi bi-heart-pulse me-2"></i>{{ __('member.templates_member_show_tab_health') }}
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button @click="activeTab = 'goals'" :class="{ 'active': activeTab === 'goals' }" class="nav-link text-dark" id="goals-tab" type="button" role="tab">
                    <i class="bi bi-bullseye me-2"></i>{{ __('member.templates_member_show_tab_goals') }}
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button @click="activeTab = 'affiliations'" :class="{ 'active': activeTab === 'affiliations' }" class="nav-link text-dark" id="affiliations-tab" type="button" role="tab">
                    <i class="bi bi-diagram-3 me-2"></i>{{ __('member.templates_member_show_tab_affiliations') }}
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button @click="activeTab = 'tournaments'" :class="{ 'active': activeTab === 'tournaments' }" class="nav-link text-dark" id="tournaments-tab" type="button" role="tab">
                    <i class="bi bi-award me-2"></i>{{ __('member.templates_member_show_tab_tournaments') }}
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button @click="activeTab = 'events'" :class="{ 'active': activeTab === 'events' }" class="nav-link text-dark" id="events-tab" type="button" role="tab">
                    <i class="bi bi-calendar-event me-2"></i>{{ __('member.templates_member_show_tab_events') }}
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
                                <i class="bi bi-bar-chart-line text-primary me-2"></i>
                                <h5 class="mb-0 font-bold">{{ __('member.templates_member_show_profile_statistics') }}</h5>
                            </div>
                            <p class="text-gray-500 text-sm mb-4">{{ __('member.templates_member_show_profile_statistics_sub') }}</p>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <!-- Total Sessions -->
                                <div>
                            <div class="flex items-center gap-3 p-3 bg-gray-100 rounded">
                                <div class="rounded-full flex items-center justify-center" style="width: 48px; height: 48px; background-color: #6f42c1;">
                                    <i class="bi bi-people-fill text-white"></i>
                                </div>
                                <div class="flex-1">
                                    <div class="small text-gray-500 mb-1">{{ __('member.templates_member_show_total_sessions') }}</div>
                                    <div class="text-xl font-bold mb-2">127</div>
                                    <div class="h-2 bg-gray-200 rounded-full overflow-hidden" style="height: 4px; background-color: #e9ecef;">
                                        <div class="h-full bg-primary transition-all" role="progressbar" style="width: 85%; background: linear-gradient(90deg, #6f42c1 0%, #8b5cf6 100%);"></div>
                                    </div>
                                    <small class="text-gray-500" style="font-size: 0.75rem;">{{ __('member.templates_member_show_total_sessions_sub') }}</small>
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
                                    <div class="small text-gray-500 mb-1">{{ __('member.templates_member_show_attendance_rate') }}</div>
                                    <div class="text-xl font-bold mb-2">85%</div>
                                    <div class="h-2 bg-gray-200 rounded-full overflow-hidden" style="height: 4px; background-color: #e9ecef;">
                                        <div class="h-full bg-primary transition-all" role="progressbar" style="width: 85%; background: linear-gradient(90deg, #6f42c1 0%, #10b981 100%);"></div>
                                    </div>
                                    <small class="text-gray-500" style="font-size: 0.75rem;">{{ __('member.templates_member_show_attendance_rate_sub') }}</small>
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
                                    <div class="small text-gray-500 mb-1">{{ __('member.templates_member_show_achievements') }}</div>
                                    <div class="text-xl font-bold mb-2">8</div>
                                    <div class="h-2 bg-gray-200 rounded-full overflow-hidden" style="height: 4px; background-color: #e9ecef;">
                                        <div class="h-full bg-primary transition-all" role="progressbar" style="width: 40%; background: linear-gradient(90deg, #6f42c1 0%, #10b981 100%);"></div>
                                    </div>
                                    <small class="text-gray-500" style="font-size: 0.75rem;">{{ __('member.templates_member_show_achievements_sub') }}</small>
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
                                    <div class="small text-gray-500 mb-1">{{ __('member.templates_member_show_goal_completion') }}</div>
                                    <div class="text-xl font-bold mb-2">75%</div>
                                    <div class="h-2 bg-gray-200 rounded-full overflow-hidden" style="height: 4px; background-color: #e9ecef;">
                                        <div class="h-full bg-primary transition-all" role="progressbar" style="width: 75%; background: linear-gradient(90deg, #6f42c1 0%, #10b981 100%);"></div>
                                    </div>
                                    <small class="text-gray-500" style="font-size: 0.75rem;">{{ __('member.templates_member_show_goal_completion_sub') }}</small>
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
                                <i class="bi bi-bar-chart-line text-primary me-2"></i>
                                <h5 class="mb-0 font-bold">{{ __('member.templates_member_show_self_investment_chart') }}</h5>
                            </div>
                            <p class="text-gray-500 text-sm mb-4">{{ __('member.templates_member_show_self_investment_sub') }}</p>

                            <div class="flex items-center justify-center" style="min-height: 300px;">
                                <div class="text-center">
                                    <i class="bi bi-graph-up text-gray-500" style="font-size: 3rem;"></i>
                                    <p class="text-gray-500 mt-3 mb-1">{{ __('member.templates_member_show_revenue_chart_coming') }}</p>
                                    <small class="text-gray-500">{{ __('member.templates_member_show_revenue_chart_hint') }}</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Payment history is sensitive — only for the member's own circle or an active-package club. --}}
            @if($canViewSensitive ?? false)
            <!-- Complete Payment & Revenue History -->
            <div class="bg-white rounded-xl shadow-sm mb-4"
                 x-data="memberBilling({ settleBase: '{{ url('me/payments') }}', csrf: '{{ csrf_token() }}', canSettle: {{ ($canSettleBills ?? false) ? 'true' : 'false' }} })">
                <div class="p-4">
                    <div class="flex items-center mb-1">
                        <i class="bi bi-receipt text-primary me-2"></i>
                        <h5 class="mb-0 font-bold">{{ __('member.templates_member_show_payment_history') }}</h5>
                    </div>
                    <p class="text-gray-500 text-sm mb-4">{{ __('member.templates_member_show_payment_history_sub') }}</p>

                    <div class="overflow-x-auto rounded-lg border border-gray-100">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="bg-gray-50 border-b border-gray-200">
                                    <th class="px-4 py-3 text-start text-xs font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap">{{ __('member.templates_member_show_th_date') }}</th>
                                    <th class="px-4 py-3 text-start text-xs font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap">{{ __('member.templates_member_show_th_club_item') }}</th>
                                    <th class="px-4 py-3 text-end text-xs font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap">{{ __('member.templates_member_show_th_amount') }}</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap">{{ __('member.templates_member_show_th_status') }}</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap">{{ __('member.templates_member_show_th_receipt') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse(($payments ?? collect()) as $payment)
                                @php $isBill = $payment->type === 'subscription'; @endphp
                                <tr class="hover:bg-gray-50 transition-colors {{ $isBill ? 'cursor-pointer' : '' }}"
                                    @if($isBill)
                                        id="pay-card-{{ $payment->subscription_id }}"
                                        role="button"
                                        data-id="{{ $payment->subscription_id }}"
                                        data-club="{{ $payment->club }}"
                                        data-item="{{ $payment->item }}"
                                        data-amount="{{ $payment->amount }}"
                                        data-cur="{{ $payment->currency }}"
                                        data-period="{{ $payment->period }}"
                                        data-date="{{ optional($payment->date)->format('M j, Y') }}"
                                        data-status="{{ $payment->status_key }}"
                                        data-label="{{ $payment->status_label }}"
                                        data-settleable="{{ $payment->settleable ? '1' : '0' }}"
                                        data-hasproof="{{ $payment->has_proof ? '1' : '0' }}"
                                        data-logo="{{ $payment->club_logo }}"
                                        @click="openBill($event.currentTarget)"
                                    @endif>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <span class="text-sm text-gray-700">{{ optional($payment->date)->format('M j, Y') }}</span>
                                        <div class="text-xs text-gray-400">{{ optional($payment->date)->format('g:i A') }}</div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2.5">
                                            @if($payment->club_logo)
                                                <span class="w-8 h-8 flex-shrink-0"><img src="{{ $payment->club_logo }}" alt="" class="w-full h-full object-contain"></span>
                                            @else
                                                <span class="w-8 h-8 flex-shrink-0 grid place-items-center text-gray-400"><i class="bi bi-buildings"></i></span>
                                            @endif
                                            <div class="min-w-0">
                                                <span class="text-sm font-medium text-gray-800">{{ $payment->club }}</span>
                                                <div class="text-xs text-primary">{{ $payment->item }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-end whitespace-nowrap">
                                        <span class="text-sm font-semibold {{ $payment->status_key === 'paid' ? 'text-green-600' : 'text-amber-600' }}">
                                            {{ $payment->amount }} BHD
                                        </span>
                                    </td>
                                    <td data-role="badge-cell" class="px-4 py-3 text-center">
                                        @if($payment->status_key === 'paid')
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">
                                                <i class="bi bi-check-circle-fill"></i> {{ __('member.templates_member_show_status_paid') }}
                                            </span>
                                        @elseif($payment->status_key === 'pending')
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700">
                                                <i class="bi bi-hourglass-split"></i> {{ $payment->status_label }}
                                            </span>
                                        @elseif($payment->status_key === 'due')
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-700">
                                                <i class="bi bi-clock"></i> {{ __('member.templates_member_show_status_due') }}
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">
                                                {{ $payment->status_label }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-center whitespace-nowrap">
                                        @if($payment->type === 'invoice' && $payment->receipt_id)
                                            <a href="{{ route('bills.receipt', $payment->receipt_id) }}" target="_blank"
                                               class="inline-flex items-center justify-center w-7 h-7 rounded-lg text-primary hover:bg-primary/10 transition-colors" title="{{ __('member.templates_member_show_view_receipt') }}">
                                                <i class="bi bi-file-earmark-text"></i>
                                            </a>
                                            <a href="{{ route('bills.receipt', $payment->receipt_id) }}?download=1" download
                                               class="inline-flex items-center justify-center w-7 h-7 rounded-lg text-gray-500 hover:bg-gray-100 transition-colors ms-1" title="{{ __('member.templates_member_show_download') }}">
                                                <i class="bi bi-download"></i>
                                            </a>
                                        @elseif($isBill && $payment->settleable && ($canSettleBills ?? false))
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-medium bg-primary/10 text-primary">
                                                <i class="bi bi-cash-coin"></i> {{ __('Settle') }}
                                            </span>
                                        @elseif($isBill)
                                            <span class="inline-flex items-center gap-1 text-xs text-gray-400">
                                                <i class="bi bi-eye"></i> {{ __('View') }}
                                            </span>
                                        @elseif($payment->has_proof)
                                            <span class="inline-flex items-center gap-1 text-xs text-gray-400" title="{{ __('member.templates_member_show_proof_submitted_title') }}">
                                                <i class="bi bi-paperclip"></i> {{ __('member.templates_member_show_proof_sent') }}
                                            </span>
                                        @else
                                            <span class="text-gray-300">—</span>
                                        @endif
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-10 text-center">
                                        <i class="bi bi-receipt text-gray-200" style="font-size:2.5rem;"></i>
                                        <p class="text-gray-400 text-sm mt-2 mb-0">{{ __('member.templates_member_show_no_payment_records') }}</p>
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- ===== Bill details + settle modal ===== --}}
                <div x-show="open" x-cloak class="fixed inset-0 z-[70] flex items-center justify-center p-4" @keydown.escape.window="close()">
                    <div x-show="open" x-transition.opacity class="absolute inset-0 bg-black/50" @click="close()"></div>
                    <div x-show="open"
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                         class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md max-h-[90vh] flex flex-col overflow-hidden">

                        {{-- Header --}}
                        <div class="flex items-start justify-between gap-3 px-6 py-4 border-b border-gray-100">
                            <div class="flex items-center gap-3 min-w-0">
                                <span class="w-11 h-11 flex-shrink-0 grid place-items-center">
                                    <template x-if="current.logo"><img :src="current.logo" alt="" class="w-full h-full object-contain"></template>
                                    <template x-if="!current.logo"><i class="bi bi-buildings text-2xl text-gray-400"></i></template>
                                </span>
                                <div class="min-w-0">
                                    <h3 class="text-lg font-bold text-gray-900 truncate" x-text="current.item"></h3>
                                    <p class="text-sm text-gray-500 truncate" x-text="current.club"></p>
                                </div>
                            </div>
                            <button type="button" @click="close()" class="w-9 h-9 -me-2 rounded-lg flex items-center justify-center text-gray-400 hover:bg-gray-100 flex-shrink-0">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>

                        {{-- Body --}}
                        <div class="flex-1 overflow-y-auto px-6 py-4 space-y-4">
                            <div class="rounded-xl bg-gray-50 p-4 flex items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500" x-text="current.status === 'paid' ? '{{ __('personal.paid') }}' : '{{ __('personal.due') }}'"></p>
                                    <p class="text-2xl font-extrabold text-primary mt-0.5 truncate">
                                        <span x-text="current.cur"></span> <span x-text="current.amount"></span>
                                    </p>
                                </div>
                                <span class="inline-block px-3 py-1 rounded-full text-xs font-semibold flex-shrink-0" :class="badgeClass" x-text="current.label"></span>
                            </div>

                            <div class="rounded-xl border border-gray-100 divide-y divide-gray-100">
                                <div class="flex items-center justify-between gap-3 px-4 py-3">
                                    <span class="text-sm text-gray-500">{{ __('Club') }}</span>
                                    <span class="text-sm font-medium text-gray-800 text-end truncate" x-text="current.club"></span>
                                </div>
                                <div class="flex items-center justify-between gap-3 px-4 py-3">
                                    <span class="text-sm text-gray-500">{{ __('Package') }}</span>
                                    <span class="text-sm font-medium text-gray-800 text-end truncate" x-text="current.item"></span>
                                </div>
                                <div class="flex items-center justify-between gap-3 px-4 py-3" x-show="current.period">
                                    <span class="text-sm text-gray-500">{{ __('Period') }}</span>
                                    <span class="text-sm font-medium text-gray-800 text-end" x-text="current.period"></span>
                                </div>
                                <div class="flex items-center justify-between gap-3 px-4 py-3">
                                    <span class="text-sm text-gray-500">{{ __('Date') }}</span>
                                    <span class="text-sm font-medium text-gray-800 text-end" x-text="current.date"></span>
                                </div>
                            </div>

                            {{-- Proof upload (settleable + viewer may settle) --}}
                            <div x-show="canSettle && current.settleable">
                                <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Payment proof') }}</label>
                                <label class="relative flex flex-col items-center justify-center border-2 border-dashed border-gray-200 rounded-xl p-6 cursor-pointer hover:border-primary/50 transition-colors overflow-hidden">
                                    <template x-if="!preview">
                                        <div class="text-center">
                                            <i class="bi bi-camera text-3xl text-gray-300"></i>
                                            <p class="text-sm text-gray-500 mt-2">{{ __('Click to add a photo of the receipt') }}</p>
                                        </div>
                                    </template>
                                    <template x-if="preview">
                                        <img :src="preview" class="max-h-56 rounded-lg object-contain" alt="">
                                    </template>
                                    <input type="file" accept="image/*" class="hidden" @change="pickFile($event)">
                                </label>
                                <p class="text-[11px] text-gray-500 mt-2">
                                    <i class="bi bi-info-circle me-1"></i>
                                    <span x-show="current.hasproof && current.status === 'pending'">{{ __('A proof was already sent — uploading a new one replaces it.') }}</span>
                                    <span x-show="!(current.hasproof && current.status === 'pending')">{{ __('The club will review and approve the payment.') }}</span>
                                </p>
                            </div>

                            <p x-show="!canSettle && current.status === 'pending'" class="text-sm text-amber-600 flex items-center gap-2">
                                <i class="bi bi-hourglass-split"></i> {{ __('Awaiting club approval.') }}
                            </p>
                        </div>

                        {{-- Footer --}}
                        <div class="flex-shrink-0 px-6 py-4 border-t border-gray-100 flex justify-end gap-3">
                            <button type="button" @click="close()"
                                class="px-4 py-2 rounded-lg border border-gray-200 text-gray-700 font-medium hover:bg-gray-50 transition-colors">
                                {{ __('Close') }}
                            </button>
                            <button type="button" x-show="canSettle && current.settleable" @click="submit()" :disabled="submitting || !proof"
                                class="px-4 py-2 rounded-lg bg-primary text-white font-semibold hover:bg-primary/90 transition-colors disabled:opacity-60 flex items-center gap-2">
                                <span x-show="!submitting"><i class="bi bi-send me-1"></i>{{ __('Send for review') }}</span>
                                <span x-show="submitting" class="flex items-center gap-2"><i class="bi bi-arrow-repeat animate-spin"></i>{{ __('Sending…') }}</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            @if($canViewSensitive ?? false)
            <!-- Emergency Contacts & Documents row -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">

                <!-- Emergency Contacts -->
                <div class="bg-white rounded-xl shadow-sm">
                    <div class="p-4">
                        <div class="flex items-center mb-2">
                            <i class="bi bi-telephone-fill text-red-500 me-2"></i>
                            <h5 class="mb-0 font-bold">{{ __('member.templates_member_show_emergency_contacts') }}</h5>
                        </div>
                        <p class="text-gray-500 text-sm mb-4">{{ __('member.templates_member_show_emergency_contacts_sub') }}</p>

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
                                <p class="text-gray-400 text-sm mt-2 mb-0">{{ __('member.templates_member_show_no_emergency_contacts') }}</p>
                            </div>
                        @endif
                        </div>{{-- #emergency-contacts-list --}}
                    </div>
                </div>

                <!-- Identity Documents -->
                <div class="bg-white rounded-xl shadow-sm">
                    <div class="p-4">
                        <div class="flex items-center mb-2">
                            <i class="bi bi-file-earmark-person-fill text-primary me-2"></i>
                            <h5 class="mb-0 font-bold">{{ __('member.templates_member_show_identity_documents') }}</h5>
                        </div>
                        <p class="text-gray-500 text-sm mb-4">{{ __('member.templates_member_show_identity_documents_sub') }}</p>

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
                                        <div class="text-xs text-gray-400">{{ __('member.templates_member_show_uploaded') }} {{ \Carbon\Carbon::parse($doc['uploaded_at'])->format('M j, Y') }}</div>
                                        @endif
                                    </div>
                                    @if(!empty($doc['file_path']))
                                    <a href="{{ asset('storage/' . $doc['file_path']) }}" target="_blank"
                                       class="flex-shrink-0 w-8 h-8 rounded-lg border border-primary text-primary flex items-center justify-center hover:bg-primary hover:text-white transition-colors" title="{{ __('member.templates_member_show_view_document') }}">
                                        <i class="bi bi-eye" style="font-size:0.85rem;"></i>
                                    </a>
                                    @endif
                                </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-center py-6">
                                <i class="bi bi-file-earmark text-gray-300" style="font-size:2rem;"></i>
                                <p class="text-gray-400 text-sm mt-2 mb-0">{{ __('member.templates_member_show_no_documents') }}</p>
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
                            <h1 class="h3 font-bold">{{ __('member.templates_member_show_member_attendance') }}</h1>
                            <p class="text-gray-500">{{ __('member.templates_member_show_member_attendance_sub') }}</p>
                        </div>
                        @if($relationship->relationship_type == 'self' || Auth::id() == $relationship->guardian_user_id || $relationship->relationship_type == 'admin_view')
                            <button type="button" @click="$dispatch('open-attendance-add-modal')" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary/90 transition-colors font-medium text-sm whitespace-nowrap">
                                <i class="bi bi-plus-lg me-1"></i>{{ __('member.templates_member_show_add_record') }}
                            </button>
                        @endif
                    </div>

                    <!-- Summary Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                        <div>
                            <div class="bg-white rounded-xl shadow-sm">
                                <div class="p-6 text-center">
                                    <div class="text-4xl font-bold text-green-600 mb-2" id="attendanceCompletedCount">{{ $sessionsCompleted }}</div>
                                    <h6 class="text-lg font-semibold text-gray-500">{{ __('member.templates_member_show_sessions_completed') }}</h6>
                                </div>
                            </div>
                        </div>
                        <div>
                            <div class="bg-white rounded-xl shadow-sm">
                                <div class="p-6 text-center">
                                    <div class="text-4xl font-bold text-red-600 mb-2" id="attendanceNoShowCount">{{ $noShows }}</div>
                                    <h6 class="text-lg font-semibold text-gray-500">{{ __('member.templates_member_show_no_shows') }}</h6>
                                </div>
                            </div>
                        </div>
                        <div>
                            <div class="bg-white rounded-xl shadow-sm">
                                <div class="p-6 text-center">
                                    <div class="text-4xl font-bold text-primary mb-2">{{ $attendanceRate }}%</div>
                                    <h6 class="text-lg font-semibold text-gray-500">{{ __('member.templates_member_show_attendance_rate') }}</h6>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Attendance Table -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                        <div class="px-6 py-4 border-b border-gray-200 bg-gray-100">
                            <h6 class="text-lg font-semibold mb-0">{{ __('member.templates_member_show_session_history') }}</h6>
                        </div>
                        <div class="p-6 p-0">
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead class="bg-gray-50 border-b border-gray-200">
                                        <tr>
                                            <th class="border-0 font-semibold">{{ __('member.templates_member_show_th_date_time') }}</th>
                                            <th class="border-0 font-semibold">{{ __('member.templates_member_show_th_session_type') }}</th>
                                            <th class="border-0 font-semibold">{{ __('member.templates_member_show_th_trainer_name') }}</th>
                                            <th class="border-0 font-semibold">{{ __('member.templates_member_show_th_status') }}</th>
                                            <th class="border-0 font-semibold">{{ __('member.templates_member_show_th_notes') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody id="attendanceTbody">
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
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">{{ __('member.templates_member_show_status_completed') }}</span>
                                                @else
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">{{ __('member.templates_member_show_status_no_show') }}</span>
                                                @endif
                                            </td>
                                            <td class="align-middle">
                                                <small class="text-gray-500">{{ $record->notes ?: '-' }}</small>
                                            </td>
                                        </tr>
                                        @empty
                                        <tr id="attendanceEmptyRow">
                                            <td colspan="5" class="text-center py-4">
                                                <i class="bi bi-calendar-check text-gray-500" style="font-size: 2rem;"></i>
                                                <p class="text-gray-500 mt-2 mb-0">{{ __('member.templates_member_show_no_attendance_records') }}</p>
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
                        <i class="bi bi-clipboard2-pulse-fill text-amber-500 me-2"></i>
                        <h5 class="mb-0 font-bold">{{ __('member.templates_member_show_chronic_conditions') }}</h5>
                    </div>
                    <p class="text-gray-500 text-sm mb-4">{{ __('member.templates_member_show_chronic_conditions_sub') }}</p>
                    <div id="health-conditions-list" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                        @foreach($relationship->dependent->health_conditions ?? [] as $condition)
                        <div class="flex items-start gap-3 p-3 bg-amber-50 border border-amber-100 rounded-lg">
                            <div class="w-9 h-9 rounded-full bg-amber-100 flex items-center justify-center flex-shrink-0 mt-0.5">
                                <i class="bi bi-exclamation-circle-fill text-amber-500"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="font-semibold text-sm text-gray-800">{{ $condition['condition'] ?? '—' }}</div>
                                @if(!empty($condition['noted_at']))
                                <div class="text-xs text-gray-400 mt-0.5">{{ __('member.templates_member_show_noted') }} {{ \Carbon\Carbon::parse($condition['noted_at'])->format('M j, Y') }}</div>
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
                        <i class="bi bi-heart-pulse text-red-600 me-2"></i>
                        <h5 class="mb-0 font-bold">{{ __('member.templates_member_show_health_metrics_overview') }}</h5>
                    </div>

                    <div class="flex justify-between items-center mb-4">
                        <p class="text-gray-500 text-sm mb-0">{{ __('member.templates_member_show_health_metrics_sub') }}</p>

                        @if($latestHealthRecord)
                            @php
                                $latestDate = $latestHealthRecord->recorded_at;
                                $now = \Carbon\Carbon::now();
                                $diff = $latestDate->diff($now);
                            @endphp

                            <div class="flex items-center gap-3">
                                <div class="flex items-center gap-2">
                                    <i class="bi bi-calendar-event text-primary"></i>
                                    <span class="font-semibold">{{ __('member.templates_member_show_snapshot_date') }}</span>
                                    <span class="text-gray-500">{{ $latestDate->format('F j, Y') }}</span>
                                </div>
                                <div class="w-px h-6 bg-gray-300"></div>
                                <div class="flex items-center gap-2">
                                    <i class="bi bi-clock-history text-primary"></i>
                                    <span class="font-semibold">{{ __('member.templates_member_show_time_since') }}</span>
                                    <span class="text-gray-500">
                                        @if($diff->y > 0)
                                            {{ $diff->y }} {{ $diff->y == 1 ? __('member.templates_member_show_t_year') : __('member.templates_member_show_t_years') }}
                                        @endif
                                        @if($diff->m > 0)
                                            {{ $diff->m }} {{ $diff->m == 1 ? __('member.templates_member_show_t_month') : __('member.templates_member_show_t_months') }}
                                        @endif
                                        @if($diff->d > 0)
                                            {{ $diff->d }} {{ $diff->d == 1 ? __('member.templates_member_show_t_day') : __('member.templates_member_show_t_days') }}
                                        @endif
                                        {{ __('member.templates_member_show_t_ago') }}
                                    </span>
                                </div>
                            </div>
                        @else
                            <div class="text-gray-500 text-sm">{{ __('member.templates_member_show_no_health_records_available') }}</div>
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
                                    <small class="text-gray-500">{{ __('member.templates_member_show_metric_weight_kg') }}</small>
                                </div>
                            </div>

                            <!-- Body Fat -->
                            <div>
                                <div class="text-center p-3 bg-gray-100 rounded">
                                    <i class="bi bi-activity text-warning mb-2" style="font-size: 1.5rem;"></i>
                                    <div class="text-xl font-bold mb-0">{{ $latestHealthRecord->body_fat_percentage ?? 'N/A' }}%</div>
                                    <small class="text-gray-500">{{ __('member.templates_member_show_metric_body_fat') }}</small>
                                </div>
                            </div>

                            <!-- Body Water -->
                            <div>
                                <div class="text-center p-3 bg-gray-100 rounded">
                                    <i class="bi bi-droplet text-info mb-2" style="font-size: 1.5rem;"></i>
                                    <div class="text-xl font-bold mb-0">{{ $latestHealthRecord->body_water_percentage ?? 'N/A' }}%</div>
                                    <small class="text-gray-500">{{ __('member.templates_member_show_metric_body_water') }}</small>
                                </div>
                            </div>

                            <!-- Muscle Mass -->
                            <div>
                                <div class="text-center p-3 bg-gray-100 rounded">
                                    <i class="bi bi-heart text-green-600 mb-2" style="font-size: 1.5rem;"></i>
                                    <div class="text-xl font-bold mb-0">{{ $latestHealthRecord->muscle_mass ?? 'N/A' }}</div>
                                    <small class="text-gray-500">{{ __('member.templates_member_show_metric_muscle_mass') }}</small>
                                </div>
                            </div>

                            <!-- Bone Mass -->
                            <div>
                                <div class="text-center p-3 bg-gray-100 rounded">
                                    <i class="bi bi-capsule text-secondary mb-2" style="font-size: 1.5rem;"></i>
                                    <div class="text-xl font-bold mb-0">{{ $latestHealthRecord->bone_mass ?? 'N/A' }}</div>
                                    <small class="text-gray-500">{{ __('member.templates_member_show_metric_bone_mass') }}</small>
                                </div>
                            </div>

                            <!-- BMR -->
                            <div>
                                <div class="text-center p-3 bg-gray-100 rounded">
                                    <i class="bi bi-lightning text-red-600 mb-2" style="font-size: 1.5rem;"></i>
                                    <div class="text-xl font-bold mb-0">{{ $latestHealthRecord->bmr ?? 'N/A' }}</div>
                                    <small class="text-gray-500">{{ __('member.templates_member_show_metric_bmr_cal') }}</small>
                                </div>
                            </div>
                        @else
                            <div class="col-span-full">
                                <div class="text-center py-4">
                                    <i class="bi bi-heart-pulse text-gray-500" style="font-size: 3rem;"></i>
                                    <p class="text-gray-500 mt-3">{{ __('member.templates_member_show_no_health_metrics') }}</p>
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
                            <h5 class="font-bold mb-4"><i class="bi bi-activity me-2"></i>{{ __('member.templates_member_show_body_composition') }}</h5>

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
                            <h5 class="font-bold mb-4"><i class="bi bi-bar-chart-line me-2"></i>{{ __('member.templates_member_show_compare') }}</h5>

                            @if($comparisonRecords->count() >= 2)
                                @php
                                    $current = $comparisonRecords->first();
                                    $previous = $comparisonRecords->skip(1)->first();
                                @endphp

                                <div class="mb-3">
                                    <div class="grid grid-cols-2 gap-2">
                                        <div class="text-center">
                                            <label class="block text-sm font-medium text-gray-700 mb-1 font-bold">{{ __('member.templates_member_show_from') }}</label>
                                            <select class="rounded-md border border-gray-300 bg-white px-2 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" id="currentDate">
                                                @foreach($healthRecords as $record)
                                                    <option value="{{ $record->id }}" {{ $record->id == $current->id ? 'selected' : '' }}>
                                                        {{ $record->recorded_at->format('M j, Y') }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="text-center">
                                            <label class="block text-sm font-medium text-gray-700 mb-1 font-bold">{{ __('member.templates_member_show_to') }}</label>
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
                                                <strong>{{ __('member.templates_member_show_time_between_records') }}</strong> {{ calculateTimeDifference($current->recorded_at, $previous->recorded_at) }}
                                            @else
                                                {{ __('member.templates_member_show_select_dates_time_diff') }}
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <div class="overflow-x-auto">
                                    <table class="w-full text-sm">
                                        <thead>
                                            <tr class="border-bottom">
                                                <th class="text-gray-500 text-sm font-semibold">{{ __('member.templates_member_show_th_metric') }}</th>
                                                <th class="text-gray-500 text-sm font-semibold text-end">{{ __('member.templates_member_show_th_current') }}</th>
                                                <th class="text-gray-500 text-sm font-semibold text-end">{{ __('member.templates_member_show_th_previous') }}</th>
                                                <th class="text-gray-500 text-sm font-semibold text-center">{{ __('member.templates_member_show_th_change') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @php
                                                if (! function_exists('getChangeIcon')) {
                                                function getChangeIcon($current, $previous) {
                                                    if ($current > $previous) return '<i class="bi bi-arrow-up text-green-600"></i>';
                                                    if ($current < $previous) return '<i class="bi bi-arrow-down text-red-600"></i>';
                                                    return '<i class="bi bi-dash text-gray-500"></i>';
                                                }
                                                }
                                            @endphp
                                            <tr data-metric="height">
                                                <td class="small"><i class="bi bi-rulers me-2"></i>{{ __('member.templates_member_show_metric_height') }}</td>
                                                <td class="small text-end font-semibold text-primary">{{ $current->height ?? 'N/A' }}cm</td>
                                                <td class="small text-end text-red-600">{{ $previous->height ?? 'N/A' }}cm</td>
                                                <td class="text-center">{!! $current->height && $previous->height ? getChangeIcon($current->height, $previous->height) : '-' !!}</td>
                                            </tr>
                                            <tr data-metric="weight">
                                                <td class="small"><i class="bi bi-speedometer2 me-2"></i>{{ __('member.templates_member_show_metric_weight') }}</td>
                                                <td class="small text-end font-semibold text-primary">{{ $current->weight ?? 'N/A' }}kg</td>
                                                <td class="small text-end text-red-600">{{ $previous->weight ?? 'N/A' }}kg</td>
                                                <td class="text-center">{!! $current->weight && $previous->weight ? getChangeIcon($current->weight, $previous->weight) : '-' !!}</td>
                                            </tr>
                                            <tr data-metric="body_fat">
                                                <td class="small"><i class="bi bi-activity me-2"></i>{{ __('member.templates_member_show_metric_body_fat') }}</td>
                                                <td class="small text-end font-semibold text-primary">{{ $current->body_fat_percentage ?? 'N/A' }}%</td>
                                                <td class="small text-end text-red-600">{{ $previous->body_fat_percentage ?? 'N/A' }}%</td>
                                                <td class="text-center">{!! $current->body_fat_percentage && $previous->body_fat_percentage ? getChangeIcon($current->body_fat_percentage, $previous->body_fat_percentage) : '-' !!}</td>
                                            </tr>
                                            <tr data-metric="bmi">
                                                <td class="small"><i class="bi bi-calculator me-2"></i>{{ __('member.templates_member_show_metric_bmi') }}</td>
                                                <td class="small text-end font-semibold text-primary">{{ $current->bmi ?? 'N/A' }}</td>
                                                <td class="small text-end text-red-600">{{ $previous->bmi ?? 'N/A' }}</td>
                                                <td class="text-center">{!! $current->bmi && $previous->bmi ? getChangeIcon($current->bmi, $previous->bmi) : '-' !!}</td>
                                            </tr>
                                            <tr data-metric="body_water">
                                                <td class="small"><i class="bi bi-droplet me-2"></i>{{ __('member.templates_member_show_metric_body_water') }}</td>
                                                <td class="small text-end font-semibold text-primary">{{ $current->body_water_percentage ?? 'N/A' }}%</td>
                                                <td class="small text-end text-red-600">{{ $previous->body_water_percentage ?? 'N/A' }}%</td>
                                                <td class="text-center">{!! $current->body_water_percentage && $previous->body_water_percentage ? getChangeIcon($current->body_water_percentage, $previous->body_water_percentage) : '-' !!}</td>
                                            </tr>
                                            <tr data-metric="muscle_mass">
                                                <td class="small"><i class="bi bi-heart me-2"></i>{{ __('member.templates_member_show_metric_muscle_mass') }}</td>
                                                <td class="small text-end font-semibold text-primary">{{ $current->muscle_mass ?? 'N/A' }}kg</td>
                                                <td class="small text-end text-red-600">{{ $previous->muscle_mass ?? 'N/A' }}kg</td>
                                                <td class="text-center">{!! $current->muscle_mass && $previous->muscle_mass ? getChangeIcon($current->muscle_mass, $previous->muscle_mass) : '-' !!}</td>
                                            </tr>
                                            <tr data-metric="bone_mass">
                                                <td class="small"><i class="bi bi-capsule me-2"></i>{{ __('member.templates_member_show_metric_bone_mass') }}</td>
                                                <td class="small text-end font-semibold text-primary">{{ $current->bone_mass ?? 'N/A' }}kg</td>
                                                <td class="small text-end text-red-600">{{ $previous->bone_mass ?? 'N/A' }}kg</td>
                                                <td class="text-center">{!! $current->bone_mass && $previous->bone_mass ? getChangeIcon($current->bone_mass, $previous->bone_mass) : '-' !!}</td>
                                            </tr>
                                            <tr data-metric="visceral_fat">
                                                <td class="small"><i class="bi bi-activity me-2"></i>{{ __('member.templates_member_show_metric_visceral_fat') }}</td>
                                                <td class="small text-end font-semibold text-primary">{{ $current->visceral_fat ?? 'N/A' }}</td>
                                                <td class="small text-end text-red-600">{{ $previous->visceral_fat ?? 'N/A' }}</td>
                                                <td class="text-center">{!! $current->visceral_fat && $previous->visceral_fat ? getChangeIcon($current->visceral_fat, $previous->visceral_fat) : '-' !!}</td>
                                            </tr>
                                            <tr data-metric="bmr">
                                                <td class="small"><i class="bi bi-lightning me-2"></i>{{ __('member.templates_member_show_metric_bmr') }}</td>
                                                <td class="small text-end font-semibold text-primary">{{ $current->bmr ?? 'N/A' }}cal</td>
                                                <td class="small text-end text-red-600">{{ $previous->bmr ?? 'N/A' }}cal</td>
                                                <td class="text-center">{!! $current->bmr && $previous->bmr ? getChangeIcon($current->bmr, $previous->bmr) : '-' !!}</td>
                                            </tr>
                                            <tr data-metric="protein">
                                                <td class="small"><i class="bi bi-heart-pulse me-2"></i>{{ __('member.templates_member_show_metric_protein') }}</td>
                                                <td class="small text-end font-semibold text-primary">{{ $current->protein_percentage ?? 'N/A' }}%</td>
                                                <td class="small text-end text-red-600">{{ $previous->protein_percentage ?? 'N/A' }}%</td>
                                                <td class="text-center">{!! $current->protein_percentage && $previous->protein_percentage ? getChangeIcon($current->protein_percentage, $previous->protein_percentage) : '-' !!}</td>
                                            </tr>
                                            <tr data-metric="body_age">
                                                <td class="small"><i class="bi bi-calendar-heart me-2"></i>{{ __('member.templates_member_show_metric_body_age') }}</td>
                                                <td class="small text-end font-semibold text-primary">{{ $current->body_age ?? 'N/A' }}yrs</td>
                                                <td class="small text-end text-red-600">{{ $previous->body_age ?? 'N/A' }}yrs</td>
                                                <td class="text-center">{!! $current->body_age && $previous->body_age ? getChangeIcon($current->body_age, $previous->body_age) : '-' !!}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="text-center py-4">
                                    <i class="bi bi-bar-chart-line text-gray-500" style="font-size: 3rem;"></i>
                                    <p class="text-gray-500 mt-3">{{ __('member.templates_member_show_need_two_records') }}</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Health Tracking History -->
            <div class="bg-white rounded-xl shadow-sm">
                <div class="p-4">
                    <h5 class="font-bold mb-4"><i class="bi bi-heart-pulse me-2"></i>{{ __('member.templates_member_show_health_tracking') }}</h5>

                    @if($healthRecords->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50 border-b border-gray-200">
                                <tr>
                                    <th class="text-gray-500 text-sm font-semibold">{{ __('member.templates_member_show_th_date') }}</th>
                                    <th class="text-gray-500 text-sm font-semibold text-center"><i class="bi bi-rulers me-1"></i>{{ __('member.templates_member_show_metric_height_cm') }}</th>
                                    <th class="text-gray-500 text-sm font-semibold text-center"><i class="bi bi-speedometer2 me-1"></i>{{ __('member.templates_member_show_metric_weight_kg') }}</th>
                                    <th class="text-gray-500 text-sm font-semibold text-center"><i class="bi bi-activity me-1"></i>{{ __('member.templates_member_show_metric_body_fat_pct') }}</th>
                                    <th class="text-gray-500 text-sm font-semibold text-center"><i class="bi bi-calculator me-1"></i>{{ __('member.templates_member_show_metric_bmi') }}</th>
                                    <th class="text-gray-500 text-sm font-semibold text-center"><i class="bi bi-droplet me-1"></i>{{ __('member.templates_member_show_metric_body_water_pct') }}</th>
                                    <th class="text-gray-500 text-sm font-semibold text-center"><i class="bi bi-heart me-1"></i>{{ __('member.templates_member_show_metric_muscle_mass_kg') }}</th>
                                    <th class="text-gray-500 text-sm font-semibold text-center"><i class="bi bi-capsule me-1"></i>{{ __('member.templates_member_show_metric_bone_mass_kg') }}</th>
                                    <th class="text-gray-500 text-sm font-semibold text-center"><i class="bi bi-activity me-1"></i>{{ __('member.templates_member_show_metric_visceral_fat') }}</th>
                                    <th class="text-gray-500 text-sm font-semibold text-center"><i class="bi bi-lightning me-1"></i>{{ __('member.templates_member_show_metric_bmr') }}</th>
                                    <th class="text-gray-500 text-sm font-semibold text-center"><i class="bi bi-heart-pulse me-1"></i>{{ __('member.templates_member_show_metric_protein_pct') }}</th>
                                    <th class="text-gray-500 text-sm font-semibold text-center"><i class="bi bi-calendar-heart me-1"></i>{{ __('member.templates_member_show_metric_body_age') }}</th>
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
                                            <td class="absolute top-1/2 end-0 -translate-y-1/2 opacity-0 edit-record-btn" style="cursor: pointer; right: 10px;">
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
                            <p class="text-gray-500 mt-3">{{ __('member.templates_member_show_no_health_records') }}</p>
                            <small class="text-gray-500">{{ __('member.templates_member_show_health_tracking_hint') }}</small>
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
                            <h5 class="font-bold mb-1"><i class="bi bi-bullseye me-2"></i>{{ __('member.templates_member_show_goal_tracking') }}</h5>
                            <p class="text-gray-500 text-sm mb-0">{{ __('member.templates_member_show_goal_tracking_sub') }}</p>
                        </div>
                        @if($relationship->relationship_type == 'self' || Auth::id() == $relationship->guardian_user_id || $relationship->relationship_type == 'admin_view')
                            <button type="button" @click="$dispatch('open-goal-add-modal')" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary/90 transition-colors font-medium text-sm whitespace-nowrap">
                                <i class="bi bi-plus-lg me-1"></i>{{ __('member.templates_member_show_set_a_goal') }}
                            </button>
                        @endif
                    </div>

                    <!-- Summary Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4">
                        <!-- Active Goals -->
                        <div>
                            <div class="bg-white rounded-xl shadow-sm text-center h-full goal-filter-card" style="background: linear-gradient(135deg, #8b5cf6 0%, #a855f7 100%); min-height: 120px; cursor: pointer;" data-filter="active">
                                <div class="p-6 p-3 flex flex-col justify-center items-center h-full">
                                    <i class="bi bi-bullseye text-white mb-2" style="font-size: 2rem;"></i>
                                    <h4 class="text-white font-bold mb-1" id="activeGoalsCount">{{ $activeGoalsCount }}</h4>
                                    <small class="text-white/50">{{ __('member.templates_member_show_active_goals') }}</small>
                                </div>
                            </div>
                        </div>
                        <!-- Completed Goals -->
                        <div>
                            <div class="bg-white rounded-xl shadow-sm text-center h-full goal-filter-card" style="background: linear-gradient(135deg, #10b981 0%, #34d399 100%); min-height: 120px; cursor: pointer;" data-filter="completed">
                                <div class="p-6 p-3 flex flex-col justify-center items-center h-full">
                                    <i class="bi bi-check-circle-fill text-white mb-2" style="font-size: 2rem;"></i>
                                    <h4 class="text-white font-bold mb-1">{{ $completedGoalsCount }}</h4>
                                    <small class="text-white/50">{{ __('member.templates_member_show_completed_goals') }}</small>
                                </div>
                            </div>
                        </div>
                        <!-- Success Rate -->
                        <div>
                            <div class="bg-white rounded-xl shadow-sm text-center h-full" style="background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%); min-height: 120px;">
                                <div class="p-6 p-3 flex flex-col justify-center items-center h-full">
                                    <i class="bi bi-graph-up-arrow text-white mb-2" style="font-size: 2rem;"></i>
                                    <h4 class="text-white font-bold mb-1">{{ $successRate }}%</h4>
                                    <small class="text-white/50">{{ __('member.templates_member_show_success_rate') }}</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Current Goals List -->
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 {{ $goals->count() ? '' : 'hidden' }}" id="goalsGrid">
                            @foreach($goals as $goal)
                        <div class="goal-card">
                            <div class="bg-white rounded-xl shadow-sm h-full relative" id="goal-{{ $goal->id }}">
                                <!-- Edit Button (only for active goals and authorized users) -->
                                @if($goal->status == 'active' && ($relationship->relationship_type == 'self' || Auth::id() == $relationship->guardian_user_id))
                                    <button class="w-8 h-8 rounded-full border border-primary text-primary flex items-center justify-center hover:bg-primary hover:text-white transition-colors absolute top-0 end-0 mt-2 me-2 edit-goal-btn" data-goal-id="{{ $goal->id }}" title="{{ __('member.templates_member_show_edit_goal') }}">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                @endif

                                <div class="p-4">
                                    <!-- Title & Icon -->
                                    <div class="flex items-center mb-3">
                                        <div class="rounded-full flex items-center justify-center me-3" style="width: 48px; height: 48px; background-color: #8b5cf6;">
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
                                                    <small class="text-gray-500" data-goal-progress-text>{{ __('member.templates_member_show_progress') }} {{ number_format($goal->current_progress_value, 1) }} / {{ number_format($goal->target_value, 1) }} {{ $goal->unit }}</small>
                                                    <small class="font-semibold" data-goal-progress-pct>{{ number_format($goal->progress_percentage, 1) }}%</small>
                                                </div>
                                                <div class="h-2 bg-gray-200 rounded-full overflow-hidden" style="height: 8px;">
                                                    <div class="h-full bg-primary transition-all" role="progressbar" data-goal-progress-bar style="width: {{ $goal->progress_percentage }}%; background: linear-gradient(90deg, #8b5cf6 0%, #10b981 100%);" aria-valuenow="{{ $goal->progress_percentage }}" aria-valuemin="0" aria-valuemax="100"></div>
                                                </div>
                                            </div>

                                            <!-- Dates & Status -->
                                            <div class="grid grid-cols-2 gap-2 mb-3">
                                                <div>
                                                    <small class="text-gray-500 block">{{ __('member.templates_member_show_started') }}</small>
                                                    <small class="font-semibold">{{ $goal->start_date->format('M d, Y') }}</small>
                                                </div>
                                                <div class="text-end">
                                                    <small class="text-gray-500 block">{{ __('member.templates_member_show_target') }}</small>
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

                                            @if($goal->before_proof || $goal->after_proof)
                                                <div class="flex gap-2 mt-3">
                                                    @if($goal->before_proof)
                                                        <div class="flex-1 min-w-0">
                                                            <img src="{{ asset('storage/'.$goal->before_proof) }}" class="w-full h-20 rounded-md object-cover border border-gray-200" alt="">
                                                            <small class="text-gray-500 block text-center mt-1">{{ __('member.before') }}</small>
                                                        </div>
                                                    @endif
                                                    @if($goal->after_proof)
                                                        <div class="flex-1 min-w-0">
                                                            <img src="{{ asset('storage/'.$goal->after_proof) }}" class="w-full h-20 rounded-md object-cover border border-gray-200" alt="">
                                                            <small class="text-gray-500 block text-center mt-1">{{ __('member.after') }}</small>
                                                        </div>
                                                    @endif
                                                </div>
                                            @endif
                                            @if($goal->status === 'completed' && $goal->days_taken !== null)
                                                <div class="mt-2 text-green-700 text-sm font-semibold flex items-center gap-1"><i class="bi bi-trophy-fill"></i>{{ $goal->days_taken }} {{ __('member.days_to_achieve') }}</div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <div class="text-center py-4 {{ $goals->count() ? 'hidden' : '' }}" id="goalsEmpty">
                            <i class="bi bi-bullseye text-gray-500" style="font-size: 3rem;"></i>
                            <h5 class="text-gray-500 mt-3 mb-2">{{ __('member.templates_member_show_no_goals_yet') }}</h5>
                            <p class="text-gray-500 mb-0">{{ __('member.templates_member_show_no_goals_hint') }}</p>
                        </div>
                </div>
            </div>
        </div>

        <!-- Affiliations Tab -->
        <div x-show="activeTab === 'affiliations'" x-transition id="affiliations" role="tabpanel">
            @include('components-templates.member.partials.affiliations-enhanced')
        </div>

        <!-- Tournaments Tab -->
        <div x-show="activeTab === 'tournaments'" x-transition id="tournaments" role="tabpanel">
            @if(($awardedAchievements ?? collect())->isNotEmpty())
            <!-- Medals & Awards (earned by this member, recorded via their club's achievements) -->
            <div class="bg-white rounded-xl shadow-sm mb-4 p-4">
                <h5 class="font-bold mb-3"><i class="bi bi-award-fill text-amber-400 me-2"></i>{{ __('member.templates_member_show_medals_awards') }}</h5>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    @foreach($awardedAchievements as $a)
                        @php
                            $r = mb_strtolower($a->member_award ?? '');
                            $emoji = (str_contains($r, 'gold') ? '🥇' : '') . (str_contains($r, 'silver') ? '🥈' : '') . (str_contains($r, 'bronze') ? '🥉' : '');
                            $emoji = $emoji ?: '🏅';
                            $dateLabel = $a->date_label ?: ($a->achievement_date ? $a->achievement_date->format('M Y') : '');
                            $achLocation = $a->tr('location');
                            $metaLine = implode(' · ', array_filter([$achLocation, $dateLabel]));
                        @endphp
                        <div class="flex items-start gap-3 border border-gray-100 rounded-lg p-3">
                            <span class="w-12 h-12 rounded-full bg-amber-50 grid place-items-center text-2xl flex-shrink-0">{{ $emoji }}</span>
                            <div class="min-w-0 flex-1">
                                {{-- Member-first: the medal they won is the headline --}}
                                <p class="font-bold text-sm text-gray-900 leading-tight">{{ $a->member_award ?: __('member.award_default') }}</p>
                                <p class="text-xs text-gray-600 truncate mt-0.5"><i class="bi bi-trophy text-amber-400 me-1"></i>{{ $a->tr('short_title') ?: $a->tr('title') }}</p>
                                @if($metaLine)
                                    <p class="text-[11px] text-gray-400 truncate mt-0.5">@if($achLocation)<i class="bi bi-geo-alt me-0.5"></i>@endif{{ $metaLine }}</p>
                                @endif
                                <p class="text-[11px] text-gray-400 truncate">{{ __('member.award_via', ['club' => $a->tenant?->tr('club_name') ?? '']) }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            @endif
            <!-- Tournament & Event Participation Card -->
            <div class="bg-white rounded-xl shadow-sm mb-4">
                <div class="p-4">
                    <!-- Section Title & Subtitle -->
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-4">
                        <div>
                            <h5 class="font-bold mb-1"><i class="bi bi-trophy-fill text-warning me-2"></i>{{ __('member.templates_member_show_tournament_event_participation') }}</h5>
                            <p class="text-gray-500 text-sm mb-0">{{ __('member.templates_member_show_tournament_participation_sub') }}</p>
                        </div>
                        <!-- Filter Section -->
                        <div class="flex items-center gap-2 flex-shrink-0">
                            <label for="sportFilter" class="text-sm font-semibold text-gray-700 whitespace-nowrap">{{ __('member.templates_member_show_filter_by_sport') }}</label>
                            <select class="rounded-md border border-gray-300 bg-white px-2 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary w-full sm:w-36" id="sportFilter">
                                <option value="all">{{ __('member.templates_member_show_all_sports') }}</option>
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
                                    <small class="text-white/50">{{ __('member.templates_member_show_special_award') }}</small>
                                </div>
                            </div>
                        </div>
                        <div>
                            <div class="bg-white rounded-xl shadow-sm text-center" style="background: linear-gradient(135deg, #FFD700 0%, #FFA500 100%);">
                                <div class="p-6 p-3">
                                    <i class="bi bi-award-fill text-white mb-2" style="font-size: 2rem;"></i>
                                    <h4 class="text-white font-bold mb-1" id="firstCount">{{ $awardCounts['1st'] }}</h4>
                                    <small class="text-white/50">{{ __('member.templates_member_show_first_place') }}</small>
                                </div>
                            </div>
                        </div>
                        <div>
                            <div class="bg-white rounded-xl shadow-sm text-center" style="background: linear-gradient(135deg, #C0C0C0 0%, #A8A8A8 100%);">
                                <div class="p-6 p-3">
                                    <i class="bi bi-award-fill text-white mb-2" style="font-size: 2rem;"></i>
                                    <h4 class="text-white font-bold mb-1" id="secondCount">{{ $awardCounts['2nd'] }}</h4>
                                    <small class="text-white/50">{{ __('member.templates_member_show_second_place') }}</small>
                                </div>
                            </div>
                        </div>
                        <div>
                            <div class="bg-white rounded-xl shadow-sm text-center" style="background: linear-gradient(135deg, #CD7F32 0%, #A0522D 100%);">
                                <div class="p-6 p-3">
                                    <i class="bi bi-award-fill text-white mb-2" style="font-size: 2rem;"></i>
                                    <h4 class="text-white font-bold mb-1" id="thirdCount">{{ $awardCounts['3rd'] }}</h4>
                                    <small class="text-white/50">{{ __('member.templates_member_show_third_place') }}</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tournament & Championships History Card -->
            <div class="bg-white rounded-xl shadow-sm">
                <div class="p-4">
                    <h6 class="font-bold mb-3"><i class="bi bi-list-ul me-2"></i>{{ __('member.templates_member_show_tournament_history') }}</h6>

                    <div class="overflow-x-auto" id="tournamentsTableWrapper" style="{{ $tournamentEvents->count() > 0 ? '' : 'display:none;' }}">
                            <table class="w-full text-sm" id="tournamentsTable">
                                <thead class="bg-gray-50 border-b border-gray-200">
                                    <tr>
                                        <th class="text-gray-500 text-sm font-semibold">{{ __('member.templates_member_show_th_tournament_details') }}</th>
                                        <th class="text-gray-500 text-sm font-semibold">{{ __('member.templates_member_show_th_club_affiliation') }}</th>
                                        <th class="text-gray-500 text-sm font-semibold">{{ __('member.templates_member_show_th_performance_result') }}</th>
                                        <th class="text-gray-500 text-sm font-semibold">{{ __('member.templates_member_show_th_notes_media') }}</th>
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
                                                    <i class="bi bi-calendar-event me-1"></i>{{ $event->date->format('M j, Y') }}
                                                    @if($event->time)
                                                        <i class="bi bi-clock me-1 ms-2"></i>{{ $event->time->format('H:i') }}
                                                    @endif
                                                    @if($event->location)
                                                        <i class="bi bi-geo-alt me-1 ms-2"></i>{{ $event->location }}
                                                    @endif
                                                    @if($event->participants_count)
                                                        <i class="bi bi-people me-1 ms-2"></i>{{ $event->participants_count }} {{ __('member.templates_member_show_participants') }}
                                                    @endif
                                                </div>
                                                {{-- Provenance: honest verification state + evidence + request action --}}
                                                <div class="mt-2 flex items-center gap-2 flex-wrap" data-verify-row="{{ $event->uuid }}">
                                                    <x-verification-badge data-verify-badge :status="$event->verification_status" :club="$event->verifiedByTenant?->tr('club_name') ?? $event->verifiedByTenant?->club_name" />
                                                    @if($event->evidence_path)
                                                        <a href="{{ route('member.tournament.evidence', [$event->user_id, $event->uuid]) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-[11px] text-gray-500 hover:text-primary"><i class="bi bi-paperclip"></i>{{ __('Evidence') }}</a>
                                                    @endif
                                                    @if($event->clubAffiliation?->tenant_id && ! in_array($event->verification_status, ['verified','pending']))
                                                        <button type="button" data-verify-btn onclick="requestAchievementVerification(this)" data-verify-url="{{ route('member.tournament.request-verification', [$event->user_id, $event->uuid]) }}" class="inline-flex items-center gap-1 text-[11px] font-medium text-primary hover:underline"><i class="bi bi-patch-check"></i>{{ __('Request verification') }}</button>
                                                    @endif
                                                    @if($event->verification_status === 'rejected' && $event->verification_note)
                                                        <span class="text-[11px] text-red-500 italic">{{ $event->verification_note }}</span>
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
                                                    <span class="text-gray-500 text-sm">{{ __('member.templates_member_show_individual') }}</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($event->performanceResults->count() > 0)
                                                    @foreach($event->performanceResults as $result)
                                                        <div class="flex items-center gap-2 mb-1">
                                                            @if($result->medal_type == '1st')
                                                                <i class="bi bi-award-fill text-warning"></i>
                                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">{{ __('member.templates_member_show_first_place') }}</span>
                                                            @elseif($result->medal_type == '2nd')
                                                                <i class="bi bi-award-fill text-secondary"></i>
                                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">{{ __('member.templates_member_show_second_place') }}</span>
                                                            @elseif($result->medal_type == '3rd')
                                                                <i class="bi bi-award-fill" style="color: #CD7F32;"></i>
                                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium text-white" style="background-color: #CD7F32;">{{ __('member.templates_member_show_third_place') }}</span>
                                                            @elseif($result->medal_type == 'special')
                                                                <i class="bi bi-trophy-fill text-warning"></i>
                                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">{{ __('member.templates_member_show_special_award') }}</span>
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
                                                    <span class="text-gray-500 text-sm">{{ __('member.templates_member_show_no_results_recorded') }}</span>
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
                                                                <i class="bi bi-image me-1"></i>{{ __('member.templates_member_show_view_media') }}
                                                            </a>
                                                        @endif
                                                    @endforeach
                                                @else
                                                    <span class="text-gray-500 text-sm">{{ __('member.templates_member_show_no_notes_available') }}</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="text-center py-5" id="tournamentsEmptyState" style="{{ $tournamentEvents->count() > 0 ? 'display:none;' : '' }}">
                            <i class="bi bi-trophy text-gray-500" style="font-size: 3rem;"></i>
                            <p class="text-gray-500 mt-3">{{ __('member.templates_member_show_no_tournament_records') }}</p>
                            <small class="text-gray-500">{{ __('member.templates_member_show_tournament_hint') }}</small>
                        </div>
                </div>
            </div>
        </div>

        <!-- Events Tab -->
        <div x-show="activeTab === 'events'" x-transition id="events" role="tabpanel">
            <!-- Personal Event Log (free-form participation history) -->
            <div class="bg-white rounded-xl shadow-sm mb-4">
                <div class="p-4">
                    <div class="flex justify-between items-center mb-3">
                        <h5 class="font-bold mb-0"><i class="bi bi-journal-text me-2"></i>{{ __('member.templates_member_show_personal_event_log') }}</h5>
                        @if($relationship->relationship_type == 'self' || Auth::id() == $relationship->guardian_user_id || $relationship->relationship_type == 'admin_view')
                            <button type="button" @click="$dispatch('open-event-add-modal')" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary/90 transition-colors font-medium text-sm whitespace-nowrap">
                                <i class="bi bi-plus-lg me-1"></i>{{ __('member.templates_member_show_add_event') }}
                            </button>
                        @endif
                    </div>

                    <div class="flex flex-col gap-3 {{ $memberEventLog->isEmpty() ? 'hidden' : '' }}" id="eventLogList">
                        @foreach($memberEventLog as $mev)
                        <div class="flex items-center gap-3 p-3 rounded-xl border border-gray-100" id="member-event-{{ $mev->id }}">
                            <div class="flex-shrink-0 rounded-xl text-white text-center px-3 py-2 min-w-[52px]" style="background:#6d5ae0;">
                                <div class="text-xs font-semibold uppercase leading-none">{{ $mev->event_date->format('D') }}</div>
                                <div class="text-xl font-extrabold leading-none">{{ $mev->event_date->format('d') }}</div>
                                <div class="text-xs font-semibold uppercase leading-none">{{ $mev->event_date->format('M') }}</div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="font-semibold text-gray-800 truncate">{{ $mev->title }}</div>
                                <div class="text-xs text-gray-500 mt-0.5">
                                    @if($mev->role)<span class="me-3"><i class="bi bi-person-badge me-1"></i>{{ $mev->role }}</span>@endif
                                    @if($mev->location)<span><i class="bi bi-geo-alt me-1"></i>{{ $mev->location }}</span>@endif
                                </div>
                                @if($mev->notes)<div class="text-xs text-gray-400 mt-0.5 truncate">{{ $mev->notes }}</div>@endif
                            </div>
                            @if($mev->result)
                            <div class="flex-shrink-0">
                                <span class="inline-flex items-center gap-1 text-xs font-semibold px-2 py-1 rounded-full bg-accent text-primary">{{ $mev->result }}</span>
                            </div>
                            @endif
                        </div>
                        @endforeach
                    </div>
                    <div class="text-center py-10 {{ $memberEventLog->isEmpty() ? '' : 'hidden' }}" id="eventLogEmpty">
                        <i class="bi bi-journal-x text-gray-300" style="font-size:2.5rem;"></i>
                        <p class="text-gray-400 mt-3">{{ __('member.templates_member_show_no_personal_events') }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm">
                <div class="p-4">
                    <h5 class="font-bold mb-3"><i class="bi bi-calendar-event me-2"></i>{{ __('member.templates_member_show_club_events_joined') }}</h5>

                    @if($joinedEventRegistrations->isEmpty())
                        <div class="text-center py-10">
                            <i class="bi bi-calendar-x text-gray-300" style="font-size:2.5rem;"></i>
                            <p class="text-gray-400 mt-3">{{ __('member.templates_member_show_no_events_joined') }}</p>
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
                                        <span class="me-3"><i class="bi bi-clock me-1"></i>{{ \Carbon\Carbon::parse($ev->start_time)->format('g:i A') }}</span>
                                        @if($ev->location)<span><i class="bi bi-geo-alt me-1"></i>{{ $ev->location }}</span>@endif
                                    </div>
                                    @if($ev->tenant)
                                    <div class="text-xs text-gray-400 mt-0.5"><i class="bi bi-building me-1"></i>{{ $ev->tenant->club_name }}</div>
                                    @endif
                                </div>
                                {{-- Status badge --}}
                                <div class="flex-shrink-0">
                                    @if($reg->status === 'waitlisted')
                                        <span class="inline-flex items-center gap-1 text-xs font-semibold px-2 py-1 rounded-full bg-yellow-100 text-yellow-700">
                                            <i class="bi bi-clock-history"></i> {{ __('member.templates_member_show_status_waitlisted') }}
                                        </span>
                                    @elseif($isPast)
                                        <span class="inline-flex items-center gap-1 text-xs font-semibold px-2 py-1 rounded-full bg-gray-100 text-gray-500">
                                            <i class="bi bi-check-circle"></i> {{ __('member.templates_member_show_status_attended') }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 text-xs font-semibold px-2 py-1 rounded-full bg-green-100 text-green-700">
                                            <i class="bi bi-check-circle-fill"></i> {{ __('member.templates_member_show_status_joined_event') }}
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
<!-- Add Event (personal log) Modal -->
<div x-data="{ open: false }" @open-event-add-modal.window="open = true" @close-event-add-modal.window="open = false" x-cloak>
    <div x-show="open" class="fixed inset-0 z-50 overflow-y-auto" @keydown.escape.window="open = false">
        <div x-show="open" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
             class="fixed inset-0 bg-black/50" @click="open = false"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div x-show="open" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
                 class="relative bg-white rounded-lg shadow-xl w-full max-w-2xl" @click.stop>
                <div class="flex items-center justify-between p-4 border-b border-gray-200">
                    <h5 class="text-lg font-medium">{{ __('member.templates_member_show_add_event_participation') }}</h5>
                    <button type="button" @click="open = false" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
                </div>
                <form id="eventAddForm" method="POST" action="{{ route('member.store-event', $relationship->dependent->id) }}">
                    @csrf
                    <div class="p-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div class="col-span-full">
                                <label for="ev_add_title" class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.templates_member_show_event_title') }} <span class="text-red-600">*</span></label>
                                <input type="text" id="ev_add_title" name="title" maxlength="150" required class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" placeholder="{{ __('member.templates_member_show_ph_event_title') }}">
                            </div>
                            <div>
                                <label for="ev_add_date" class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.templates_member_show_label_date') }} <span class="text-red-600">*</span></label>
                                <input type="date" id="ev_add_date" name="event_date" required value="{{ \Carbon\Carbon::now()->format('Y-m-d') }}" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary">
                            </div>
                            <div>
                                <label for="ev_add_location" class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.templates_member_show_label_location') }}</label>
                                <input type="text" id="ev_add_location" name="location" maxlength="150" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" placeholder="{{ __('member.templates_member_show_ph_optional') }}">
                            </div>
                            <div>
                                <label for="ev_add_role" class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.templates_member_show_label_role') }}</label>
                                <input type="text" id="ev_add_role" name="role" maxlength="80" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" placeholder="{{ __('member.templates_member_show_ph_role') }}">
                            </div>
                            <div>
                                <label for="ev_add_result" class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.templates_member_show_label_result') }}</label>
                                <input type="text" id="ev_add_result" name="result" maxlength="150" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" placeholder="{{ __('member.templates_member_show_ph_result') }}">
                            </div>
                            <div class="col-span-full">
                                <label for="ev_add_notes" class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.templates_member_show_label_notes') }}</label>
                                <textarea id="ev_add_notes" name="notes" rows="2" maxlength="1000" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" placeholder="{{ __('member.templates_member_show_ph_optional') }}"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 p-4 border-t border-gray-200 bg-gray-50">
                        <button type="button" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-md text-sm font-medium hover:bg-gray-300 transition-colors" @click="open = false">{{ __('shared.cancel') }}</button>
                        <button type="submit" id="eventAddSubmit" class="bg-primary text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-primary/90 transition-colors">{{ __('member.templates_member_show_add_event') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Add Attendance Modal -->
<div x-data="{ open: false }" @open-attendance-add-modal.window="open = true" @close-attendance-add-modal.window="open = false" x-cloak>
    <div x-show="open" class="fixed inset-0 z-50 overflow-y-auto" @keydown.escape.window="open = false">
        <div x-show="open" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
             class="fixed inset-0 bg-black/50" @click="open = false"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div x-show="open" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
                 class="relative bg-white rounded-lg shadow-xl w-full max-w-2xl" @click.stop>
                <div class="flex items-center justify-between p-4 border-b border-gray-200">
                    <h5 class="text-lg font-medium">{{ __('member.templates_member_show_add_attendance_record') }}</h5>
                    <button type="button" @click="open = false" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
                </div>
                <form id="attendanceAddForm" method="POST" action="{{ route('member.store-attendance', $relationship->dependent->id) }}">
                    @csrf
                    <div class="p-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label for="att_add_datetime" class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.templates_member_show_th_date_time') }} <span class="text-red-600">*</span></label>
                                <input type="datetime-local" id="att_add_datetime" name="session_datetime" required value="{{ \Carbon\Carbon::now()->format('Y-m-d\TH:i') }}" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary">
                            </div>
                            <div>
                                <label for="att_add_status" class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.templates_member_show_label_status') }} <span class="text-red-600">*</span></label>
                                <select id="att_add_status" name="status" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary">
                                    <option value="completed" selected>{{ __('member.templates_member_show_status_completed') }}</option>
                                    <option value="no_show">{{ __('member.templates_member_show_status_no_show') }}</option>
                                </select>
                            </div>
                            <div>
                                <label for="att_add_type" class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.templates_member_show_label_session_type') }} <span class="text-red-600">*</span></label>
                                <input type="text" id="att_add_type" name="session_type" maxlength="100" required class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" placeholder="{{ __('member.templates_member_show_ph_session_type') }}">
                            </div>
                            <div>
                                <label for="att_add_trainer" class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.templates_member_show_label_trainer_name') }}</label>
                                <input type="text" id="att_add_trainer" name="trainer_name" maxlength="100" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" placeholder="{{ __('member.templates_member_show_ph_optional') }}">
                            </div>
                            <div class="col-span-full">
                                <label for="att_add_notes" class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.templates_member_show_label_notes') }}</label>
                                <textarea id="att_add_notes" name="notes" rows="2" maxlength="1000" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" placeholder="{{ __('member.templates_member_show_ph_optional') }}"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 p-4 border-t border-gray-200 bg-gray-50">
                        <button type="button" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-md text-sm font-medium hover:bg-gray-300 transition-colors" @click="open = false">{{ __('shared.cancel') }}</button>
                        <button type="submit" id="attendanceAddSubmit" class="bg-primary text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-primary/90 transition-colors">{{ __('member.templates_member_show_add_record') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Add Goal Modal -->
<div x-data="{ open: false }" @open-goal-add-modal.window="open = true" @close-goal-add-modal.window="open = false" x-cloak>
    <div x-show="open" class="fixed inset-0 z-50 overflow-y-auto" @keydown.escape.window="open = false">
        <div x-show="open" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
             class="fixed inset-0 bg-black/50" @click="open = false"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div x-show="open" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
                 class="relative bg-white rounded-lg shadow-xl w-full max-w-2xl" @click.stop>
                <div class="flex items-center justify-between p-4 border-b border-gray-200">
                    <h5 class="text-lg font-medium">{{ __('member.templates_member_show_set_a_goal') }}</h5>
                    <button type="button" @click="open = false" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
                </div>
                <form id="goalAddForm" method="POST" action="{{ route('member.store-goal', $relationship->dependent->id) }}">
                    @csrf
                    <div class="p-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div class="col-span-full">
                                <label for="goal_add_title" class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.templates_member_show_goal_title') }} <span class="text-red-600">*</span></label>
                                <input type="text" id="goal_add_title" name="title" maxlength="150" required class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" placeholder="{{ __('member.templates_member_show_ph_goal_title') }}">
                            </div>
                            <div class="col-span-full">
                                <label for="goal_add_description" class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.templates_member_show_label_description') }}</label>
                                <textarea id="goal_add_description" name="description" rows="2" maxlength="1000" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" placeholder="{{ __('member.templates_member_show_ph_goal_description') }}"></textarea>
                            </div>
                            <div>
                                <label for="goal_add_target" class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.templates_member_show_target_value') }} <span class="text-red-600">*</span></label>
                                <input type="number" step="0.1" min="0" id="goal_add_target" name="target_value" required class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" placeholder="80">
                            </div>
                            <div>
                                <label for="goal_add_unit" class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.templates_member_show_unit') }} <span class="text-red-600">*</span></label>
                                <input type="text" id="goal_add_unit" name="unit" maxlength="30" required class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" placeholder="{{ __('member.templates_member_show_ph_unit') }}">
                            </div>
                            <div>
                                <label for="goal_add_current" class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.templates_member_show_current_progress') }}</label>
                                <input type="number" step="0.1" min="0" id="goal_add_current" name="current_progress_value" value="0" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary">
                            </div>
                            <div>
                                <label for="goal_add_target_date" class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.templates_member_show_target_date') }} <span class="text-red-600">*</span></label>
                                <input type="date" id="goal_add_target_date" name="target_date" required min="{{ \Carbon\Carbon::now()->format('Y-m-d') }}" value="{{ \Carbon\Carbon::now()->addMonth()->format('Y-m-d') }}" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary">
                            </div>
                            <div>
                                <label for="goal_add_priority" class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.templates_member_show_priority') }}</label>
                                <select id="goal_add_priority" name="priority_level" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary">
                                    <option value="low">{{ __('member.templates_member_show_priority_low') }}</option>
                                    <option value="medium" selected>{{ __('member.templates_member_show_priority_medium') }}</option>
                                    <option value="high">{{ __('member.templates_member_show_priority_high') }}</option>
                                </select>
                            </div>
                            <div>
                                <label for="goal_add_icon" class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.templates_member_show_label_icon') }}</label>
                                <select id="goal_add_icon" name="icon_type" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary">
                                    <option value="bi-bullseye">{{ __('member.templates_member_show_icon_target') }}</option>
                                    <option value="dumbbell">{{ __('member.templates_member_show_icon_strength') }}</option>
                                    <option value="clock">{{ __('member.templates_member_show_icon_endurance') }}</option>
                                </select>
                            </div>
                            <div class="col-span-full">
                                <label for="goal_add_before_photo" class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.goal_before_photo') }} <span class="text-red-600">*</span></label>
                                <input type="file" accept="image/*" id="goal_add_before_photo" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary">
                                <input type="hidden" name="before_proof" id="goal_add_before_photo_b64">
                                <img id="goal_add_before_photo_preview" class="hidden mt-2 h-24 rounded-md object-cover border border-gray-200" alt="">
                            </div>
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 p-4 border-t border-gray-200 bg-gray-50">
                        <button type="button" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-md text-sm font-medium hover:bg-gray-300 transition-colors" @click="open = false">{{ __('shared.cancel') }}</button>
                        <button type="submit" id="goalAddSubmit" class="bg-primary text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-primary/90 transition-colors">{{ __('member.templates_member_show_create_goal') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Edit Goal Modal -->
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
                    <h5 class="text-lg font-medium font-medium text-lg">{{ __('member.templates_member_show_edit_goal_progress') }}</h5>
                    <button type="button" @click="open = false" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
                </div>
                <form id="goalEditForm" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="p-4 p-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div class="col-span-full">
                                <div class="flex items-center mb-3">
                                    <div class="rounded-full flex items-center justify-center me-3" id="goalIconDisplay" style="width: 48px; height: 48px; background-color: #8b5cf6;">
                                        <i class="bi bi-bullseye text-white"></i>
                                    </div>
                                    <div>
                                        <h6 class="font-bold mb-1" id="goalTitleDisplay">{{ __('member.templates_member_show_goal_title') }}</h6>
                                        <p class="text-gray-500 text-sm mb-0" id="goalDescriptionDisplay">{{ __('member.templates_member_show_goal_description_display') }}</p>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <label for="current_progress_value" class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.templates_member_show_current_progress') }} <span class="text-red-600">*</span></label>
                                <div class="flex">
                                    <input type="number" step="0.1" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" id="current_progress_value" name="current_progress_value" required>
                                    <span class="px-3 py-2 bg-gray-100 border border-gray-300 border-s-0 rounded-e-md text-sm flex items-center" id="goalUnitDisplay">lbs</span>
                                </div>
                                <div class="text-xs text-gray-500 mt-1">{{ __('member.templates_member_show_target') }} <span id="goalTargetDisplay">170.0 lbs</span></div>
                            </div>
                            <div>
                                <label for="goal_status" class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.templates_member_show_label_status') }}</label>
                                <select class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" id="goal_status" name="status">
                                    <option value="active">{{ __('member.templates_member_show_status_active') }}</option>
                                    <option value="completed">{{ __('member.templates_member_show_status_completed') }}</option>
                                </select>
                            </div>
                            <div class="col-span-full">
                                <div class="h-2 bg-gray-200 rounded-full overflow-hidden" style="height: 8px;">
                                    <div class="h-full bg-primary transition-all" role="progressbar" id="progressPreview" style="width: 0%; background: linear-gradient(90deg, #8b5cf6 0%, #10b981 100%);"></div>
                                </div>
                                <small class="text-gray-500 mt-1 block" id="progressTextPreview">Progress: 0.0 / 170.0 lbs (0.0%)</small>
                            </div>
                            <div class="col-span-full hidden" id="goalEditAfterPhotoWrap">
                                <label for="goal_edit_after_photo" class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.goal_after_photo') }} <span class="text-red-600">*</span></label>
                                <p class="text-xs text-gray-500 mb-1">{{ __('member.mark_as_achieved_hint') }}</p>
                                <input type="file" accept="image/*" id="goal_edit_after_photo" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary">
                                <input type="hidden" name="after_proof" id="goal_edit_after_photo_b64">
                                <img id="goal_edit_after_photo_preview" class="hidden mt-2 h-24 rounded-md object-cover border border-gray-200" alt="">
                            </div>
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 p-4 border-t border-gray-200 bg-gray-50 flex justify-end gap-2 p-4 border-t bg-gray-50">
                        <button type="button" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-md text-sm font-medium hover:bg-gray-300 transition-colors" @click="open = false">{{ __('shared.cancel') }}</button>
                        <button type="submit" class="bg-primary text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-primary/90 transition-colors">{{ __('member.templates_member_show_update_goal') }}</button>
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
                    <h5 class="text-lg font-medium font-medium text-lg">{{ __('member.templates_member_show_add_health_update') }}</h5>
                    <button type="button" @click="open = false" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
                </div>
                <form id="healthUpdateForm" method="POST" action="{{ $relationship->relationship_type === 'admin_view' ? route('admin.platform.members.store-health', $relationship->dependent->id) : route('member.store-health', $relationship->dependent->id) }}">
                    @csrf
                    <div class="p-4 p-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label for="recorded_at" class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.templates_member_show_label_date') }} <span class="text-red-600">*</span></label>
                                <input type="date" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" id="recorded_at" name="recorded_at" value="{{ \Carbon\Carbon::now()->format('Y-m-d') }}" required>
                            </div>
                            <div>
                                <label for="height" class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.templates_member_show_metric_height_cm') }}</label>
                                <input type="number" step="0.1" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" id="height" name="height">
                            </div>
                            <div>
                                <label for="weight" class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.templates_member_show_metric_weight_kg') }}</label>
                                <input type="number" step="0.1" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" id="weight" name="weight">
                            </div>
                            <div>
                                <label for="body_fat_percentage" class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.templates_member_show_label_body_fat_pct') }}</label>
                                <input type="number" step="0.1" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" id="body_fat_percentage" name="body_fat_percentage">
                            </div>
                            <div>
                                <label for="bmi" class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.templates_member_show_metric_bmi') }}</label>
                                <input type="number" step="0.1" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" id="bmi" name="bmi">
                            </div>
                            <div>
                                <label for="body_water_percentage" class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.templates_member_show_label_body_water_pct') }}</label>
                                <input type="number" step="0.1" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" id="body_water_percentage" name="body_water_percentage">
                            </div>
                            <div>
                                <label for="muscle_mass" class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.templates_member_show_metric_muscle_mass_kg') }}</label>
                                <input type="number" step="0.1" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" id="muscle_mass" name="muscle_mass">
                            </div>
                            <div>
                                <label for="bone_mass" class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.templates_member_show_metric_bone_mass_kg') }}</label>
                                <input type="number" step="0.1" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" id="bone_mass" name="bone_mass">
                            </div>
                            <div>
                                <label for="visceral_fat" class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.templates_member_show_metric_visceral_fat') }}</label>
                                <input type="number" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" id="visceral_fat" name="visceral_fat">
                            </div>
                            <div>
                                <label for="bmr" class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.templates_member_show_metric_bmr_cal') }}</label>
                                <input type="number" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" id="bmr" name="bmr">
                            </div>
                            <div>
                                <label for="protein_percentage" class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.templates_member_show_label_protein_pct') }}</label>
                                <input type="number" step="0.1" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" id="protein_percentage" name="protein_percentage">
                            </div>
                            <div>
                                <label for="body_age" class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.templates_member_show_label_body_age_years') }}</label>
                                <input type="number" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" id="body_age" name="body_age">
                            </div>
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 p-4 border-t border-gray-200 bg-gray-50 flex justify-end gap-2 p-4 border-t bg-gray-50">
                        <button type="button" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-md text-sm font-medium hover:bg-gray-300 transition-colors" @click="open = false">{{ __('shared.cancel') }}</button>
                        <button type="submit" class="bg-primary text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-primary/90 transition-colors">{{ __('member.templates_member_show_save_health_update') }}</button>
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
                    <h5 class="text-lg font-medium font-medium text-lg">{{ __('member.templates_member_show_add_tournament_participation') }}</h5>
                    <button type="button" @click="open = false" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
                </div>
                <form id="tournamentParticipationForm" method="POST" action="{{ $relationship->relationship_type === 'admin_view' ? route('admin.platform.members.store-tournament', $relationship->dependent->id) : route('member.store-tournament', $relationship->dependent->id) }}">
                    @csrf
                    <div class="p-4 p-4 overflow-y-auto" style="max-height: calc(90vh - 140px);">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <!-- Tournament Details -->
                            <div>
                                <label for="tournament_title" class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.templates_member_show_tournament_title') }} <span class="text-red-600">*</span></label>
                                <input type="text" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" id="tournament_title" name="title" required>
                            </div>
                            <div>
                                <label for="tournament_type" class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.templates_member_show_label_type') }} <span class="text-red-600">*</span></label>
                                <select class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" id="tournament_type" name="type" required>
                                    <option value="">{{ __('member.templates_member_show_select_type') }}</option>
                                    <option value="championship">{{ __('member.templates_member_show_type_championship') }}</option>
                                    <option value="tournament">{{ __('member.templates_member_show_type_tournament') }}</option>
                                    <option value="competition">{{ __('member.templates_member_show_type_competition') }}</option>
                                    <option value="exhibition">{{ __('member.templates_member_show_type_exhibition') }}</option>
                                </select>
                            </div>
                            <div>
                                <label for="tournament_sport" class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.templates_member_show_label_sport') }} <span class="text-red-600">*</span></label>
                                <select class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" id="tournament_sport" name="sport" required>
                                    <option value="">{{ __('member.templates_member_show_select_sport') }}</option>
                                    <option value="Boxing">{{ __('member.templates_member_show_sport_boxing') }}</option>
                                    <option value="Taekwondo">{{ __('member.templates_member_show_sport_taekwondo') }}</option>
                                    <option value="Karate">{{ __('member.templates_member_show_sport_karate') }}</option>
                                    <option value="Martial Arts">{{ __('member.templates_member_show_sport_martial_arts') }}</option>
                                    <option value="Fitness">{{ __('member.templates_member_show_sport_fitness') }}</option>
                                    <option value="Weightlifting">{{ __('member.templates_member_show_sport_weightlifting') }}</option>
                                    <option value="Other">{{ __('member.templates_member_show_sport_other') }}</option>
                                </select>
                            </div>
                            <div>
                                <x-birthdate-dropdown
                                    name="date"
                                    id="tournament_date"
                                    :label="__('member.templates_member_show_label_date')"
                                    :required="true"
                                    :min-year="2000"
                                    :max-year="date('Y')"
                                    :error="$errors->first('date')" />
                            </div>
                            <div>
                                <label for="tournament_time" class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.templates_member_show_label_time') }}</label>
                                <input type="time" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" id="tournament_time" name="time">
                            </div>
                            <div>
                                <label for="tournament_location" class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.templates_member_show_label_location') }}</label>
                                <input type="text" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" id="tournament_location" name="location" placeholder="{{ __('member.templates_member_show_ph_venue') }}">
                            </div>
                            <div>
                                <label for="participants_count" class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.templates_member_show_number_of_participants') }}</label>
                                <input type="number" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" id="participants_count" name="participants_count" min="1">
                            </div>
                            <div>
                                <label for="club_affiliation_id" class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.templates_member_show_th_club_affiliation') }}</label>
                                <select class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" id="club_affiliation_id" name="club_affiliation_id">
                                    <option value="">{{ __('member.templates_member_show_select_club_optional') }}</option>
                                    @foreach($clubAffiliations ?? [] as $affiliation)
                                        <option value="{{ $affiliation->id }}">{{ $affiliation->club_name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Performance Results Section -->
                            <div class="col-span-full">
                                <hr class="my-3">
                                <h6 class="mb-3 font-medium">{{ __('member.templates_member_show_performance_results') }}</h6>
                                <div id="performanceResultsContainer">
                                    <div class="performance-result-item mb-3 p-3 border rounded">
                                        <div class="grid grid-cols-12 gap-2">
                                            <div class="col-span-4">
                                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.templates_member_show_medal_type') }}</label>
                                                <select class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary medal-type" name="performance_results[0][medal_type]">
                                                    <option value="">{{ __('member.templates_member_show_select_medal') }}</option>
                                                    <option value="special">{{ __('member.templates_member_show_special_award') }}</option>
                                                    <option value="1st">{{ __('member.templates_member_show_first_place') }}</option>
                                                    <option value="2nd">{{ __('member.templates_member_show_second_place') }}</option>
                                                    <option value="3rd">{{ __('member.templates_member_show_third_place') }}</option>
                                                </select>
                                            </div>
                                            <div class="col-span-3">
                                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.templates_member_show_label_points') }}</label>
                                                <input type="number" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" name="performance_results[0][points]" min="0" step="0.1">
                                            </div>
                                            <div class="col-span-4">
                                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.templates_member_show_label_description') }}</label>
                                                <input type="text" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" name="performance_results[0][description]" placeholder="{{ __('member.templates_member_show_ph_optional_description') }}">
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
                                    <i class="bi bi-plus me-1"></i>{{ __('member.templates_member_show_add_another_result') }}
                                </button>
                            </div>

                            <!-- Notes & Media Section -->
                            <div class="col-span-full">
                                <hr class="my-3">
                                <h6 class="mb-3 font-medium">{{ __('member.templates_member_show_th_notes_media') }}</h6>
                                <div id="notesMediaContainer">
                                    <div class="notes-media-item mb-3 p-3 border rounded">
                                        <div class="grid grid-cols-12 gap-2">
                                            <div class="col-span-6">
                                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.templates_member_show_note_text') }}</label>
                                                <textarea class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" name="notes_media[0][note_text]" rows="2" placeholder="{{ __('member.templates_member_show_ph_tournament_notes') }}"></textarea>
                                            </div>
                                            <div class="col-span-5">
                                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.templates_member_show_media_link') }}</label>
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
                                    <i class="bi bi-plus me-1"></i>{{ __('member.templates_member_show_add_another_note') }}
                                </button>
                            </div>
                            {{-- Supporting evidence — helps a club verify the claim; never verifies it on its own --}}
                            <div class="mt-6">
                                <h6 class="mb-1 font-medium">{{ __('Supporting evidence') }} <span class="text-xs font-normal text-gray-400">({{ __('optional') }})</span></h6>
                                <p class="text-xs text-gray-500 mb-2">{{ __('Attach a certificate or medal photo. It helps a club verify your claim — it does not verify it automatically.') }}</p>
                                <label class="flex items-center gap-3 border border-dashed border-gray-300 rounded-lg px-4 py-3 cursor-pointer hover:border-primary transition-colors">
                                    <i class="bi bi-cloud-arrow-up text-xl text-gray-400"></i>
                                    <span class="text-sm text-gray-600" id="tournamentEvidenceLabel">{{ __('Choose an image (JPG, PNG, WebP)') }}</span>
                                    <input type="file" accept="image/jpeg,image/png,image/webp,image/gif" class="hidden" onchange="readTournamentEvidence(this)">
                                </label>
                                <input type="hidden" name="evidence" id="tournamentEvidenceInput">
                                <div id="tournamentEvidencePreview" class="mt-2 hidden">
                                    <img class="h-24 rounded-lg border border-gray-200 object-cover" alt="{{ __('Evidence preview') }}">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 p-4 border-t border-gray-200 bg-gray-50 flex justify-end gap-2 p-4 border-t bg-gray-50">
                        <button type="button" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-md text-sm font-medium hover:bg-gray-300 transition-colors" @click="open = false">{{ __('shared.cancel') }}</button>
                        <button type="submit" class="bg-primary text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-primary/90 transition-colors">{{ __('member.templates_member_show_save_tournament_record') }}</button>
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
        const metricLabels = ['{{ __("member.templates_member_show_metric_height") }}', '{{ __("member.templates_member_show_metric_weight") }}', '{{ __("member.templates_member_show_metric_body_fat") }}', '{{ __("member.templates_member_show_metric_bmi") }}', '{{ __("member.templates_member_show_metric_body_water") }}', '{{ __("member.templates_member_show_metric_muscle_mass") }}', '{{ __("member.templates_member_show_metric_bone_mass") }}', '{{ __("member.templates_member_show_metric_visceral_fat") }}', '{{ __("member.templates_member_show_metric_bmr") }}', '{{ __("member.templates_member_show_metric_protein") }}', '{{ __("member.templates_member_show_metric_body_age") }}'];
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
                            label: '{{ __("member.templates_member_show_current_reading") }}',
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
                            label: '{{ __("member.templates_member_show_previous_reading") }}',
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
            document.getElementById('healthUpdateModalLabel').textContent = '{{ __("member.templates_member_show_add_health_update") }}';
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
            document.querySelector('#healthUpdateForm button[type="submit"]').textContent = '{{ __("member.templates_member_show_save_health_update") }}';
        }

        // Function to populate modal for editing
        function populateHealthModalForEdit(recordId) {
            const record = healthRecordsData.find(r => r.id == recordId);
            if (!record) return;

            document.getElementById('healthUpdateModalLabel').textContent = '{{ __("member.templates_member_show_edit_health_update") }}';
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
            document.querySelector('#healthUpdateForm button[type="submit"]').textContent = '{{ __("member.templates_member_show_update_health_update") }}';
        }

        // Handle comparison dropdown changes
        const currentDateSelect = document.getElementById('currentDate');
        const previousDateSelect = document.getElementById('previousDate');

        if (currentDateSelect && previousDateSelect) {
            function updateComparisonTable() {
                const currentId = currentDateSelect.value;
                const previousId = previousDateSelect.value;

                if (!currentId || !previousId) {
                    document.getElementById('timeDifference').innerHTML = '{{ __("member.templates_member_show_select_dates_time_diff") }}';
                    return;
                }

                const currentRecord = healthRecordsData.find(r => r.id == currentId);
                const previousRecord = healthRecordsData.find(r => r.id == previousId);

                if (!currentRecord || !previousRecord) {
                    document.getElementById('timeDifference').innerHTML = '{{ __("member.templates_member_show_select_dates_time_diff") }}';
                    return;
                }

                // Update time difference
                const timeDiff = calculateTimeDifference(currentRecord.recorded_at, previousRecord.recorded_at);
                document.getElementById('timeDifference').innerHTML = `<strong>{{ __('member.templates_member_show_time_between_records') }}</strong> ${timeDiff}`;

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
                        } else if (currentMedalFilter === 'special' && badge.textContent.includes('{{ __("member.templates_member_show_special_award") }}')) {
                            hasMatchingMedal = true;
                        } else if (currentMedalFilter === '1st' && badge.textContent.includes('{{ __("member.templates_member_show_first_place") }}')) {
                            hasMatchingMedal = true;
                        } else if (currentMedalFilter === '2nd' && badge.textContent.includes('{{ __("member.templates_member_show_second_place") }}')) {
                            hasMatchingMedal = true;
                        } else if (currentMedalFilter === '3rd' && badge.textContent.includes('{{ __("member.templates_member_show_third_place") }}')) {
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
                            if (badge.textContent.includes('{{ __("member.templates_member_show_special_award") }}')) specialCount++;
                            else if (badge.textContent.includes('{{ __("member.templates_member_show_first_place") }}')) firstCount++;
                            else if (badge.textContent.includes('{{ __("member.templates_member_show_second_place") }}')) secondCount++;
                            else if (badge.textContent.includes('{{ __("member.templates_member_show_third_place") }}')) thirdCount++;
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
                    titleElement.innerHTML = `<i class="bi bi-bullseye me-2"></i>${baseTitle}`;
                } else {
                    const filterLabel = filterType === 'active' ? 'Active' : 'Completed';
                    titleElement.innerHTML = `<i class="bi bi-bullseye me-2"></i>${baseTitle} - ${filterLabel} Goals`;
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
            // Remembered so the status-change listener only demands an "after" photo
            // the first time a goal is closed — re-saving an already-completed goal
            // (e.g. tweaking progress) shouldn't ask for a new one.
            goalEditForm.dataset.originalStatus = goal.status;

            // Populate modal fields
            document.getElementById('goalTitleDisplay').textContent = goal.title;
            document.getElementById('goalDescriptionDisplay').textContent = goal.description || 'No description';
            document.getElementById('current_progress_value').value = goal.current_progress_value;
            document.getElementById('goal_status').value = goal.status;
            document.getElementById('goalUnitDisplay').textContent = goal.unit;
            document.getElementById('goalTargetDisplay').textContent = `${goal.target_value} ${goal.unit}`;

            // Reset the "after photo" upload each time the modal opens
            const afterInput = document.getElementById('goal_edit_after_photo');
            const afterB64 = document.getElementById('goal_edit_after_photo_b64');
            const afterPreview = document.getElementById('goal_edit_after_photo_preview');
            if (afterInput) afterInput.value = '';
            if (afterB64) afterB64.value = '';
            if (afterPreview) { afterPreview.src = ''; afterPreview.classList.add('hidden'); }
            toggleGoalAfterPhotoBlock();

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

        // Show the "after photo" upload only when closing a goal for the first time.
        function toggleGoalAfterPhotoBlock() {
            const wrap = document.getElementById('goalEditAfterPhotoWrap');
            if (!wrap) return;
            const closingNow = document.getElementById('goal_status').value === 'completed'
                && goalEditForm.dataset.originalStatus !== 'completed';
            wrap.classList.toggle('hidden', !closingNow);
        }
        document.getElementById('goal_status').addEventListener('change', toggleGoalAfterPhotoBlock);

        // A phone camera photo can be several MB — base64-encoded raw, that easily blows
        // past the server's post_max_size and the upload silently fails. Downscale onto a
        // canvas (max 1600px) and re-encode as JPEG before it ever becomes a data URI.
        function resizeImageToDataUrl(file, maxDim, quality) {
            return new Promise((resolve, reject) => {
                const reader = new FileReader();
                reader.onload = () => {
                    const img = new Image();
                    img.onload = () => {
                        let width = img.width, height = img.height;
                        if (width > maxDim || height > maxDim) {
                            if (width > height) { height = Math.round(height * (maxDim / width)); width = maxDim; }
                            else { width = Math.round(width * (maxDim / height)); height = maxDim; }
                        }
                        const canvas = document.createElement('canvas');
                        canvas.width = width; canvas.height = height;
                        canvas.getContext('2d').drawImage(img, 0, 0, width, height);
                        resolve(canvas.toDataURL('image/jpeg', quality || 0.85));
                    };
                    img.onerror = () => reject(new Error('image_decode_failed'));
                    img.src = reader.result;
                };
                reader.onerror = () => reject(new Error('file_read_failed'));
                reader.readAsDataURL(file);
            });
        }

        // Convert a picked file into a base64 data URI, filling a hidden input + preview <img>.
        function wireBase64Photo(fileInputId, hiddenInputId, previewId) {
            const fileInput = document.getElementById(fileInputId);
            const hidden = document.getElementById(hiddenInputId);
            const preview = document.getElementById(previewId);
            if (!fileInput || !hidden) return;
            fileInput.addEventListener('change', async function () {
                const f = fileInput.files && fileInput.files[0];
                if (!f) return;
                if (!f.type.startsWith('image/')) {
                    if (window.showToast) window.showToast('error', '{{ __('Please choose an image file.') }}');
                    fileInput.value = '';
                    return;
                }
                try {
                    const dataUrl = await resizeImageToDataUrl(f, 1600, 0.85);
                    hidden.value = dataUrl;
                    if (preview) { preview.src = dataUrl; preview.classList.remove('hidden'); }
                } catch (e) {
                    if (window.showToast) window.showToast('error', '{{ __('Please choose an image file.') }}');
                    fileInput.value = '';
                }
            });
        }
        wireBase64Photo('goal_add_before_photo', 'goal_add_before_photo_b64', 'goal_add_before_photo_preview');
        wireBase64Photo('goal_edit_after_photo', 'goal_edit_after_photo_b64', 'goal_edit_after_photo_preview');

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

            // First-time completion: server now returns before/after photos + days_taken —
            // render them once, right after the status/priority badges.
            if (goal.status === 'completed' && (goal.before_proof || goal.after_proof) && !card.querySelector('[data-goal-photos]')) {
                const badges = card.querySelector('[data-goal-status-badge]')?.closest('.flex.gap-2.flex-wrap');
                if (badges) {
                    const photos = document.createElement('div');
                    photos.className = 'flex gap-2 mt-3';
                    photos.setAttribute('data-goal-photos', '');
                    photos.innerHTML =
                        (goal.before_proof ? '<div class="flex-1 min-w-0"><img src="' + goal.before_proof + '" class="w-full h-20 rounded-md object-cover border border-gray-200" alt=""><small class="text-gray-500 block text-center mt-1">{{ __("member.before") }}</small></div>' : '') +
                        (goal.after_proof ? '<div class="flex-1 min-w-0"><img src="' + goal.after_proof + '" class="w-full h-20 rounded-md object-cover border border-gray-200" alt=""><small class="text-gray-500 block text-center mt-1">{{ __("member.after") }}</small></div>' : '');
                    badges.insertAdjacentElement('afterend', photos);

                    if (goal.days_taken !== null && goal.days_taken !== undefined) {
                        const daysEl = document.createElement('div');
                        daysEl.className = 'mt-2 text-green-700 text-sm font-semibold flex items-center gap-1';
                        daysEl.innerHTML = '<i class="bi bi-trophy-fill"></i>' + goal.days_taken + ' {{ __("member.days_to_achieve") }}';
                        photos.insertAdjacentElement('afterend', daysEl);
                    }
                }
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
                    window.showToast('success', data.message || '{{ __("member.templates_member_show_goal_updated_success") }}');
                } else {
                    window.showToast('error', '{{ __("member.templates_member_show_error_updating_goal") }}' + (data.message || '{{ __("member.templates_member_show_unknown_error") }}'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                window.showToast('error', '{{ __("member.templates_member_show_error_updating_goal_retry") }}');
            });
        });

        // ---- Add Goal (create) ----
        const goalAddForm = document.getElementById('goalAddForm');
        if (goalAddForm) {
            function bindEditButton(btn) {
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    populateGoalEditModal(this.getAttribute('data-goal-id'));
                    window.dispatchEvent(new CustomEvent('open-goal-edit-modal'));
                });
            }

            function escapeHtml(s) {
                return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
                    return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
                });
            }

            function goalIconClass(type) {
                if (type === 'dumbbell') return 'bi bi-dumbbell text-white';
                if (type === 'clock') return 'bi bi-clock text-white';
                return 'bi bi-bullseye text-white';
            }

            function renderGoalCard(goal) {
                const pct = Math.max(0, Math.min(100, goal.progress_percentage || 0));
                const today = new Date().toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' });
                const priorityClass = goal.priority_level === 'high'
                    ? 'bg-red-100 text-red-800'
                    : (goal.priority_level === 'medium' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800');
                const wrap = document.createElement('div');
                wrap.className = 'goal-card';
                wrap.innerHTML =
                    '<div class="bg-white rounded-xl shadow-sm h-full relative" id="goal-' + goal.id + '">' +
                        '<button class="w-8 h-8 rounded-full border border-primary text-primary flex items-center justify-center hover:bg-primary hover:text-white transition-colors absolute top-0 end-0 mt-2 me-2 edit-goal-btn" data-goal-id="' + goal.id + '" title="{{ __("member.templates_member_show_edit_goal") }}"><i class="bi bi-pencil"></i></button>' +
                        '<div class="p-4">' +
                            '<div class="flex items-center mb-3">' +
                                '<div class="rounded-full flex items-center justify-center me-3" style="width:48px;height:48px;background-color:#8b5cf6;"><i class="' + goalIconClass(goal.icon_type) + '"></i></div>' +
                                '<div class="flex-1"><h6 class="font-bold mb-1">' + escapeHtml(goal.title) + '</h6>' +
                                    (goal.description ? '<p class="text-gray-500 text-sm mb-0">' + escapeHtml(goal.description) + '</p>' : '') +
                                '</div>' +
                            '</div>' +
                            '<div class="mb-3">' +
                                '<div class="flex justify-between items-center mb-2">' +
                                    '<small class="text-gray-500" data-goal-progress-text>{{ __("member.templates_member_show_progress") }} ' + Number(goal.current_progress_value).toFixed(1) + ' / ' + Number(goal.target_value).toFixed(1) + ' ' + escapeHtml(goal.unit) + '</small>' +
                                    '<small class="font-semibold" data-goal-progress-pct>' + pct.toFixed(1) + '%</small>' +
                                '</div>' +
                                '<div class="h-2 bg-gray-200 rounded-full overflow-hidden" style="height:8px;">' +
                                    '<div class="h-full bg-primary transition-all" role="progressbar" data-goal-progress-bar style="width:' + pct + '%;background:linear-gradient(90deg,#8b5cf6 0%,#10b981 100%);"></div>' +
                                '</div>' +
                            '</div>' +
                            '<div class="grid grid-cols-2 gap-2 mb-3">' +
                                '<div><small class="text-gray-500 block">{{ __('member.templates_member_show_started') }}</small><small class="font-semibold">' + today + '</small></div>' +
                                '<div class="text-end"><small class="text-gray-500 block">{{ __('member.templates_member_show_target') }}</small><small class="font-semibold">' + escapeHtml(goal.target_date || '') + '</small></div>' +
                            '</div>' +
                            '<div class="flex gap-2 flex-wrap">' +
                                '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-primary/10 text-primary" data-goal-status-badge>{{ __("member.templates_member_show_status_active") }}</span>' +
                                '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ' + priorityClass + '">' + escapeHtml((goal.priority_level || 'medium').charAt(0).toUpperCase() + (goal.priority_level || 'medium').slice(1)) + '</span>' +
                            '</div>' +
                            (goal.before_proof
                                ? '<div class="flex gap-2 mt-3"><div class="flex-1 min-w-0"><img src="' + goal.before_proof + '" class="w-full h-20 rounded-md object-cover border border-gray-200" alt=""><small class="text-gray-500 block text-center mt-1">{{ __("member.before") }}</small></div></div>'
                                : '') +
                        '</div>' +
                    '</div>';
                return wrap;
            }

            goalAddForm.addEventListener('submit', function (e) {
                e.preventDefault();
                const submitBtn = document.getElementById('goalAddSubmit');
                submitBtn.disabled = true;
                fetch(goalAddForm.action, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || goalAddForm.querySelector('input[name="_token"]').value,
                        'Accept': 'application/json',
                    },
                    body: new FormData(goalAddForm),
                    credentials: 'same-origin',
                })
                .then(async (res) => ({ ok: res.ok, status: res.status, data: await res.json().catch(() => ({})) }))
                .then(({ ok, status, data }) => {
                    if (ok && data.success) {
                        const grid = document.getElementById('goalsGrid');
                        const empty = document.getElementById('goalsEmpty');
                        const card = renderGoalCard(data.goal);
                        grid.prepend(card);
                        grid.classList.remove('hidden');
                        if (empty) empty.classList.add('hidden');
                        bindEditButton(card.querySelector('.edit-goal-btn'));
                        goalsData.push(data.goal);           // keep edit-in-place working for the new goal
                        const cnt = document.getElementById('activeGoalsCount');
                        if (cnt) cnt.textContent = (parseInt(cnt.textContent, 10) || 0) + 1;
                        goalAddForm.reset();
                        document.getElementById('goal_add_current').value = '0';
                        const beforePreview = document.getElementById('goal_add_before_photo_preview');
                        if (beforePreview) { beforePreview.src = ''; beforePreview.classList.add('hidden'); }
                        window.dispatchEvent(new CustomEvent('close-goal-add-modal'));
                        if (window.showToast) window.showToast('success', data.message || '{{ __("member.templates_member_show_goal_created") }}');
                    } else if (status === 422 && data.errors) {
                        const first = Object.values(data.errors)[0];
                        if (window.showToast) window.showToast('error', Array.isArray(first) ? first[0] : first);
                    } else {
                        if (window.showToast) window.showToast('error', data.message || '{{ __("member.templates_member_show_could_not_create_goal") }}');
                    }
                })
                .catch(() => { if (window.showToast) window.showToast('error', '{{ __("member.templates_member_show_network_error_retry") }}'); })
                .finally(() => { submitBtn.disabled = false; });
            });
        }

        // ---- Add Attendance Record (create) ----
        const attendanceAddForm = document.getElementById('attendanceAddForm');
        if (attendanceAddForm) {
            function attEscape(s) {
                return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
                    return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
                });
            }

            function renderAttendanceRow(r) {
                const badge = r.status === 'completed'
                    ? '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">{{ __('member.templates_member_show_status_completed') }}</span>'
                    : '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">{{ __('member.templates_member_show_status_no_show') }}</span>';
                const tr = document.createElement('tr');
                tr.innerHTML =
                    '<td class="align-middle"><div class="font-semibold">' + attEscape(r.date) + '</div><small class="text-gray-500">' + attEscape(r.time) + '</small></td>' +
                    '<td class="align-middle">' + attEscape(r.session_type) + '</td>' +
                    '<td class="align-middle">' + attEscape(r.trainer_name || '') + '</td>' +
                    '<td class="align-middle">' + badge + '</td>' +
                    '<td class="align-middle"><small class="text-gray-500">' + (r.notes ? attEscape(r.notes) : '-') + '</small></td>';
                return tr;
            }

            attendanceAddForm.addEventListener('submit', function (e) {
                e.preventDefault();
                const btn = document.getElementById('attendanceAddSubmit');
                btn.disabled = true;
                fetch(attendanceAddForm.action, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || attendanceAddForm.querySelector('input[name="_token"]').value,
                        'Accept': 'application/json',
                    },
                    body: new FormData(attendanceAddForm),
                    credentials: 'same-origin',
                })
                .then(async (res) => ({ ok: res.ok, status: res.status, data: await res.json().catch(() => ({})) }))
                .then(({ ok, status, data }) => {
                    if (ok && data.success) {
                        const tbody = document.getElementById('attendanceTbody');
                        const emptyRow = document.getElementById('attendanceEmptyRow');
                        if (emptyRow) emptyRow.remove();
                        tbody.prepend(renderAttendanceRow(data.record));
                        const countEl = data.record.status === 'completed'
                            ? document.getElementById('attendanceCompletedCount')
                            : document.getElementById('attendanceNoShowCount');
                        if (countEl) countEl.textContent = (parseInt(countEl.textContent, 10) || 0) + 1;
                        attendanceAddForm.reset();
                        window.dispatchEvent(new CustomEvent('close-attendance-add-modal'));
                        if (window.showToast) window.showToast('success', data.message || '{{ __("member.templates_member_show_attendance_added") }}');
                    } else if (status === 422 && data.errors) {
                        const first = Object.values(data.errors)[0];
                        if (window.showToast) window.showToast('error', Array.isArray(first) ? first[0] : first);
                    } else {
                        if (window.showToast) window.showToast('error', data.message || '{{ __("member.templates_member_show_could_not_add_record") }}');
                    }
                })
                .catch(() => { if (window.showToast) window.showToast('error', '{{ __("member.templates_member_show_network_error_retry") }}'); })
                .finally(() => { btn.disabled = false; });
            });
        }

        // ---- Add Event Participation (personal log) ----
        const eventAddForm = document.getElementById('eventAddForm');
        if (eventAddForm) {
            function evEscape(s) {
                return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
                    return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
                });
            }

            function renderEventEntry(ev) {
                const row = document.createElement('div');
                row.className = 'flex items-center gap-3 p-3 rounded-xl border border-gray-100';
                row.id = 'member-event-' + ev.id;
                row.innerHTML =
                    '<div class="flex-shrink-0 rounded-xl text-white text-center px-3 py-2 min-w-[52px]" style="background:#6d5ae0;">' +
                        '<div class="text-xs font-semibold uppercase leading-none">' + evEscape(ev.day) + '</div>' +
                        '<div class="text-xl font-extrabold leading-none">' + evEscape(ev.day_num) + '</div>' +
                        '<div class="text-xs font-semibold uppercase leading-none">' + evEscape(ev.month) + '</div>' +
                    '</div>' +
                    '<div class="flex-1 min-w-0">' +
                        '<div class="font-semibold text-gray-800 truncate">' + evEscape(ev.title) + '</div>' +
                        '<div class="text-xs text-gray-500 mt-0.5">' +
                            (ev.role ? '<span class="me-3"><i class="bi bi-person-badge me-1"></i>' + evEscape(ev.role) + '</span>' : '') +
                            (ev.location ? '<span><i class="bi bi-geo-alt me-1"></i>' + evEscape(ev.location) + '</span>' : '') +
                        '</div>' +
                        (ev.notes ? '<div class="text-xs text-gray-400 mt-0.5 truncate">' + evEscape(ev.notes) + '</div>' : '') +
                    '</div>' +
                    (ev.result ? '<div class="flex-shrink-0"><span class="inline-flex items-center gap-1 text-xs font-semibold px-2 py-1 rounded-full bg-accent text-primary">' + evEscape(ev.result) + '</span></div>' : '');
                return row;
            }

            eventAddForm.addEventListener('submit', function (e) {
                e.preventDefault();
                const btn = document.getElementById('eventAddSubmit');
                btn.disabled = true;
                fetch(eventAddForm.action, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || eventAddForm.querySelector('input[name="_token"]').value,
                        'Accept': 'application/json',
                    },
                    body: new FormData(eventAddForm),
                    credentials: 'same-origin',
                })
                .then(async (res) => ({ ok: res.ok, status: res.status, data: await res.json().catch(() => ({})) }))
                .then(({ ok, status, data }) => {
                    if (ok && data.success) {
                        const list = document.getElementById('eventLogList');
                        const empty = document.getElementById('eventLogEmpty');
                        list.prepend(renderEventEntry(data.event));
                        list.classList.remove('hidden');
                        if (empty) empty.classList.add('hidden');
                        eventAddForm.reset();
                        window.dispatchEvent(new CustomEvent('close-event-add-modal'));
                        if (window.showToast) window.showToast('success', data.message || '{{ __("member.templates_member_show_event_added") }}');
                    } else if (status === 422 && data.errors) {
                        const first = Object.values(data.errors)[0];
                        if (window.showToast) window.showToast('error', Array.isArray(first) ? first[0] : first);
                    } else {
                        if (window.showToast) window.showToast('error', data.message || '{{ __("member.templates_member_show_could_not_add_event") }}');
                    }
                })
                .catch(() => { if (window.showToast) window.showToast('error', '{{ __("member.templates_member_show_network_error_retry") }}'); })
                .finally(() => { btn.disabled = false; });
            });
        }
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
                    `<img src="${affiliation.logo}" alt="${affiliation.club_name}" class="me-3 rounded" style="width: 50px; height: 50px; object-fit: cover;">` :
                    `<div class="bg-primary text-white rounded flex items-center justify-center me-3" style="width: 50px; height: 50px;">
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
            html += `<p class="mb-2"><i class="bi bi-geo-alt me-2"></i><strong>{{ __("member.templates_member_show_location_label") }}</strong> ${affiliation.location}</p>`;
        }

        if (affiliation.description) {
            html += `<p class="mb-2"><strong>{{ __("member.templates_member_show_description_label") }}</strong> ${affiliation.description}</p>`;
        }

        if (affiliation.coaches && affiliation.coaches.length > 0) {
            html += `<p class="mb-2"><strong>{{ __("member.templates_member_show_coaches_label") }}</strong> ${affiliation.coaches.join(', ')}</p>`;
        }

        if (affiliation.affiliation_media && affiliation.affiliation_media.length > 0) {
            html += `<div class="mt-3"><strong>{{ __("member.templates_member_show_media_certificates") }}</strong></div>`;
            html += `<div class="grid grid-cols-2 gap-2 mt-1">`;

            affiliation.affiliation_media.forEach(media => {
                const iconClass = media.icon_class || 'bi-file';
                html += `
                    <div>
                        <a href="${media.full_url}" target="_blank" class="border border-gray-300 text-gray-700 px-2 py-1 rounded text-xs hover:bg-gray-100 transition-colors w-full">
                            <i class="bi ${iconClass} me-1"></i>${media.title || media.media_type}
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
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.templates_member_show_medal_type') }}</label>
                    <select class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary medal-type" name="performance_results[${performanceResultIndex}][medal_type]">
                        <option value="">{{ __('member.templates_member_show_select_medal') }}</option>
                        <option value="special">{{ __('member.templates_member_show_special_award') }}</option>
                        <option value="1st">{{ __('member.templates_member_show_first_place') }}</option>
                        <option value="2nd">{{ __('member.templates_member_show_second_place') }}</option>
                        <option value="3rd">{{ __('member.templates_member_show_third_place') }}</option>
                    </select>
                </div>
                <div class="col-span-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.templates_member_show_label_points') }}</label>
                    <input type="number" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" name="performance_results[${performanceResultIndex}][points]" min="0" step="0.1">
                </div>
                <div class="col-span-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.templates_member_show_label_description') }}</label>
                    <input type="text" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" name="performance_results[${performanceResultIndex}][description]" placeholder="{{ __('member.templates_member_show_ph_optional_description') }}">
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
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.templates_member_show_note_text') }}</label>
                    <textarea class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" name="notes_media[${notesMediaIndex}][note_text]" rows="2" placeholder="{{ __('member.templates_member_show_ph_tournament_notes') }}"></textarea>
                </div>
                <div class="col-span-5">
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.templates_member_show_media_link') }}</label>
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

        // Reset evidence picker
        const evInput = document.getElementById('tournamentEvidenceInput');
        const evPrev = document.getElementById('tournamentEvidencePreview');
        const evLabel = document.getElementById('tournamentEvidenceLabel');
        if (evInput) evInput.value = '';
        if (evPrev) { evPrev.classList.add('hidden'); const img = evPrev.querySelector('img'); if (img) img.src = ''; }
        if (evLabel) evLabel.textContent = '{{ __('Choose an image (JPG, PNG, WebP)') }}';
    });

    // Read the chosen evidence image into a hidden field as a base64 data-URI.
    // Client-side guardrails only; the server re-sniffs real bytes and rejects SVG.
    function readTournamentEvidence(input) {
        const file = input.files && input.files[0];
        const hidden = document.getElementById('tournamentEvidenceInput');
        const prev = document.getElementById('tournamentEvidencePreview');
        const label = document.getElementById('tournamentEvidenceLabel');
        if (!file) return;
        if (!/^image\/(jpeg|png|webp|gif)$/.test(file.type)) {
            window.showToast && window.showToast('error', '{{ __('Unsupported image type.') }}');
            input.value = ''; return;
        }
        if (file.size > 5 * 1024 * 1024) {
            window.showToast && window.showToast('error', '{{ __('Image is too large (max 5MB).') }}');
            input.value = ''; return;
        }
        const reader = new FileReader();
        reader.onload = e => {
            if (hidden) hidden.value = e.target.result;
            if (prev) { prev.classList.remove('hidden'); const img = prev.querySelector('img'); if (img) img.src = e.target.result; }
            if (label) label.textContent = file.name;
        };
        reader.readAsDataURL(file);
    }

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
                showAlert('{{ __("member.templates_member_show_tournament_added_success") }}', 'success');
            } else {
                showAlert('{{ __("member.templates_member_show_error_adding_tournament") }}' + (data.message || '{{ __("member.templates_member_show_unknown_error") }}'), 'danger');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('{{ __("member.templates_member_show_error_adding_tournament_retry") }}', 'danger');
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
                    medal = '<i class="bi bi-award-fill text-warning"></i><span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">{{ __('member.templates_member_show_first_place') }}</span>';
                } else if (r.medal_type === '2nd') {
                    medal = '<i class="bi bi-award-fill text-secondary"></i><span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">{{ __('member.templates_member_show_second_place') }}</span>';
                } else if (r.medal_type === '3rd') {
                    medal = '<i class="bi bi-award-fill" style="color: #CD7F32;"></i><span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium text-white" style="background-color: #CD7F32;">{{ __('member.templates_member_show_third_place') }}</span>';
                } else if (r.medal_type === 'special') {
                    medal = '<i class="bi bi-trophy-fill text-warning"></i><span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">{{ __('member.templates_member_show_special_award') }}</span>';
                }
                perfHtml += `<div class="flex items-center gap-2 mb-1">${medal}${r.points ? `<small class="text-gray-500">${escapeHtml(r.points)} {{ __("member.templates_member_show_pts") }}</small>` : ''}</div>`;
                if (r.description) {
                    perfHtml += `<small class="text-gray-500">${escapeHtml(r.description)}</small>`;
                }
            });
        } else {
            perfHtml = '<span class="text-gray-500 text-sm">{{ __('member.templates_member_show_no_results_recorded') }}</span>';
        }

        let notesHtml = '';
        if (t.notes_media && t.notes_media.length > 0) {
            t.notes_media.forEach(n => {
                if (n.note_text) notesHtml += `<p class="mb-1 small">${escapeHtml(n.note_text)}</p>`;
                if (n.media_link) notesHtml += `<a href="${escapeHtml(n.media_link)}" target="_blank" class="border border-primary text-primary px-2 py-1 rounded text-xs hover:bg-primary hover:text-white transition-colors"><i class="bi bi-image me-1"></i>{{ __('member.templates_member_show_view_media') }}</a>`;
            });
        } else {
            notesHtml = '<span class="text-gray-500 text-sm">{{ __('member.templates_member_show_no_notes_available') }}</span>';
        }

        let affHtml;
        if (t.club_affiliation) {
            affHtml = `<div><div class="small font-semibold">${escapeHtml(t.club_affiliation.club_name)}</div><div class="text-gray-500 text-sm">${escapeHtml(t.club_affiliation.location)}</div></div>`;
        } else {
            affHtml = '<span class="text-gray-500 text-sm">{{ __('member.templates_member_show_individual') }}</span>';
        }

        let meta = `<i class="bi bi-calendar-event me-1"></i>${escapeHtml(t.date)}`;
        if (t.time) meta += `<i class="bi bi-clock me-1 ms-2"></i>${escapeHtml(t.time)}`;
        if (t.location) meta += `<i class="bi bi-geo-alt me-1 ms-2"></i>${escapeHtml(t.location)}`;
        if (t.participants_count) meta += `<i class="bi bi-people me-1 ms-2"></i>${escapeHtml(t.participants_count)} {{ __("member.templates_member_show_participants") }}`;

        const typeBadge = (t.type === 'championship') ? 'bg-primary/10 text-primary' : 'bg-gray-100 text-gray-800';

        tr.innerHTML = `
            <td>
                <div class="font-bold">${escapeHtml(t.title)}</div>
                <div class="flex gap-2 mt-1 flex-wrap">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${typeBadge}">${escapeHtml(t.type_label)}</span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">${escapeHtml(t.sport)}</span>
                </div>
                <div class="text-gray-500 text-sm mt-1">${meta}</div>
                <div class="mt-2 flex items-center gap-2 flex-wrap" data-verify-row="${escapeHtml(t.uuid || '')}">${buildVerifyBlock(t)}</div>
            </td>
            <td>${affHtml}</td>
            <td>${perfHtml}</td>
            <td>${notesHtml}</td>`;
        return tr;
    }

    // Honest provenance badge + evidence + request action for a JS-rendered row.
    function verifyBadgeHtml(status, club) {
        const map = {
            verified:      ['bi-patch-check-fill','text-green-700','bg-green-50','border-green-200','{{ __('Verified') }}'],
            pending:       ['bi-hourglass-split','text-amber-700','bg-amber-50','border-amber-200','{{ __('Pending review') }}'],
            rejected:      ['bi-patch-exclamation','text-red-700','bg-red-50','border-red-200','{{ __('Not verified') }}'],
            self_reported: ['bi-person-badge','text-gray-500','bg-gray-50','border-gray-200','{{ __('Self-reported') }}'],
        };
        const [icon, tc, bg, bd, label] = map[status] || map.self_reported;
        const suffix = (status === 'verified' && club) ? `<span class="opacity-70 font-normal">· ${escapeHtml(club)}</span>` : '';
        return `<span data-verify-badge class="inline-flex items-center gap-1 rounded-full border font-medium px-2 py-0.5 text-xs ${tc} ${bg} ${bd}"><i class="bi ${icon}"></i><span>${label}</span>${suffix}</span>`;
    }

    function buildVerifyBlock(t) {
        const v = t.verification || {};
        let html = verifyBadgeHtml(v.status || 'self_reported', v.verified_club);
        if (v.evidence_url) {
            html += `<a href="${escapeHtml(v.evidence_url)}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-[11px] text-gray-500 hover:text-primary"><i class="bi bi-paperclip"></i>{{ __('Evidence') }}</a>`;
        }
        if (v.can_request && v.request_url) {
            html += `<button type="button" data-verify-btn onclick="requestAchievementVerification(this)" data-verify-url="${escapeHtml(v.request_url)}" class="inline-flex items-center gap-1 text-[11px] font-medium text-primary hover:underline"><i class="bi bi-patch-check"></i>{{ __('Request verification') }}</button>`;
        }
        return html;
    }

    // Ask the named club to verify this self-claimed achievement.
    async function requestAchievementVerification(btn) {
        const url = btn.getAttribute('data-verify-url');
        if (!url) return;
        btn.disabled = true;
        try {
            const res = await fetch(url, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data = await res.json();
            if (data.success) {
                const row = btn.closest('[data-verify-row]');
                if (row && data.verification) patchVerifyRow(row, data.verification);
                window.showToast && window.showToast('success', data.message);
            } else {
                btn.disabled = false;
                window.showToast && window.showToast('error', data.message || '{{ __('Could not request verification.') }}');
            }
        } catch (e) {
            btn.disabled = false;
            window.showToast && window.showToast('error', '{{ __('Something went wrong.') }}');
        }
    }

    // Patch a row's provenance UI in place (used by the request action AND live MQTT updates).
    function patchVerifyRow(row, v) {
        const badge = row.querySelector('[data-verify-badge]');
        if (badge) badge.outerHTML = verifyBadgeHtml(v.status, v.verified_club);
        // Once verified or pending, the request button no longer applies.
        if (['verified', 'pending'].includes(v.status)) {
            const b = row.querySelector('[data-verify-btn]');
            if (b) b.remove();
        }
    }

    // Live updates: a club admin verifying/rejecting elsewhere patches this profile instantly.
    if (window.__memberVerifyHandler) window.removeEventListener('realtime:verification', window.__memberVerifyHandler);
    window.__memberVerifyHandler = function (e) {
        const d = e.detail || {};
        if (d.action !== 'status' || !d.event_uuid) return;
        const row = document.querySelector(`[data-verify-row="${d.event_uuid}"]`);
        if (row) patchVerifyRow(row, { status: d.status, verified_club: d.verified_club });
    };
    window.addEventListener('realtime:verification', window.__memberVerifyHandler);

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

<!-- Quick Photo Edit Modal (opened from the pencil icon on the profile picture) -->
<x-photo-edit-modal
    :user="$relationship->dependent"
    :uploadUrl="$relationship->relationship_type === 'admin_view' ? route('admin.platform.members.upload-picture', $relationship->dependent->id) : route('member.upload-picture', $relationship->dependent->id)"
    :removeUrl="$relationship->relationship_type === 'admin_view' ? route('admin.platform.members.remove-picture', $relationship->dependent->id) : route('member.remove-picture', $relationship->dependent->id)"
    :visibilityUrl="$relationship->relationship_type === 'admin_view' ? route('admin.platform.members.picture-visibility', $relationship->dependent->id) : route('member.picture-visibility', $relationship->dependent->id)"
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
                        <i class="bi bi-key-fill text-amber-500 me-2"></i>{{ __('member.templates_member_show_reset_password') }}
                    </h5>
                    <button type="button" @click="open = false" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
                </div>
                <form id="resetPasswordForm" onsubmit="submitResetPassword(event)">
                    @csrf
                    <div class="p-4 space-y-4">
                        <p class="text-sm text-gray-500">{{ __('member.templates_member_show_reset_password_for') }} <strong>{{ $relationship->dependent->full_name }}</strong>.</p>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.templates_member_show_new_password') }}</label>
                            <input type="password" id="resetNewPassword" name="password" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" placeholder="{{ __('member.templates_member_show_ph_min_8') }}" required minlength="8">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.templates_member_show_confirm_new_password') }}</label>
                            <input type="password" id="resetPasswordConfirm" name="password_confirmation" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" placeholder="{{ __('member.templates_member_show_ph_repeat_password') }}" required minlength="8">
                        </div>
                        <div id="resetPasswordError" class="hidden p-3 rounded-lg bg-red-50 text-red-700 border border-red-200 text-sm"></div>
                    </div>
                    <div class="flex justify-end gap-2 p-4 border-t bg-gray-50 rounded-b-lg">
                        <button type="button" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-md text-sm font-medium hover:bg-gray-300 transition-colors" @click="open = false">{{ __('shared.cancel') }}</button>
                        <button type="submit" id="resetPasswordSubmitBtn" class="bg-primary text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-primary/90 transition-colors">
                            <i class="bi bi-key me-1"></i>{{ __('member.templates_member_show_reset_password') }}
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
        errEl.textContent = '{{ __("member.templates_member_show_passwords_no_match") }}';
        errEl.classList.remove('hidden');
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>{{ __("member.templates_member_show_resetting") }}';

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
            showToast('success', '{{ __("member.templates_member_show_reset_success_title") }}', data.message || '{{ __("member.templates_member_show_password_reset_success") }}');
        } else {
            errEl.textContent = data.message || (data.errors?.password?.[0] ?? '{{ __("member.templates_member_show_something_wrong") }}');
            errEl.classList.remove('hidden');
        }
    })
    .catch(() => {
        errEl.textContent = '{{ __("member.templates_member_show_error_occurred_retry") }}';
        errEl.classList.remove('hidden');
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-key me-1"></i>{{ __("member.templates_member_show_reset_password") }}';
    });
}
</script>
@endif

<!-- Generate Password (super-admin) — result modal showing the new password -->
@if($canRegeneratePassword ?? false)
<div x-data="{ open: false, password: '', emailed: false, copied: false }"
     x-on:show-generated-password.window="open = true; password = $event.detail.password; emailed = $event.detail.emailed; copied = false"
     x-cloak>
    <div x-show="open" class="fixed inset-0 z-50 overflow-y-auto" @keydown.escape.window="open = false">
        <div x-show="open" x-transition.opacity class="fixed inset-0 bg-black/50" @click="open = false"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div x-show="open" x-transition class="relative bg-white rounded-lg shadow-xl w-full max-w-md border border-gray-200 text-center p-6" @click.stop>
                <div class="w-14 h-14 rounded-2xl bg-green-50 text-green-600 grid place-items-center mx-auto"><i class="bi bi-check-circle-fill text-2xl"></i></div>
                <h5 class="font-semibold text-lg mt-3">{{ __('member.templates_member_show_new_password_generated') }}</h5>
                <p class="text-sm text-gray-500 mt-1" x-show="emailed">{{ __('member.templates_member_show_emailed_new_password') }} <strong>{{ $relationship->dependent->full_name }}</strong>.</p>
                <p class="text-sm text-amber-600 mt-1" x-show="!emailed">{{ __('member.templates_member_show_email_not_sent') }}</p>
                <button type="button" @click="navigator.clipboard && navigator.clipboard.writeText(password); copied = true; showToast('success', '{{ __("member.templates_member_show_copied_title") }}', '{{ __("member.templates_member_show_password_copied") }}')"
                        class="w-full mt-4 flex items-center justify-between gap-2 px-4 py-3 rounded-lg bg-gray-50 border border-dashed border-primary/40 hover:bg-gray-100 transition-colors">
                    <span class="font-mono font-bold text-base tracking-wider select-all" x-text="password"></span>
                    <i class="bi" :class="copied ? 'bi-clipboard-check text-green-600' : 'bi-clipboard text-primary'"></i>
                </button>
                <button type="button" @click="open = false" class="w-full mt-4 bg-primary text-white px-4 py-2.5 rounded-md text-sm font-medium hover:bg-primary/90 transition-colors">{{ __('shared.done') }}</button>
            </div>
        </div>
    </div>
</div>
<script>
function regenerateMemberPassword() {
    window.confirmAction({
        title: '{{ __("member.templates_member_show_generate_password") }}',
        message: '{{ __("member.templates_member_show_gen_confirm_message", ["name" => addslashes($relationship->dependent->full_name)]) }}',
        type: 'warning', confirmText: '{{ __("member.templates_member_show_generate") }}',
    }).then(ok => {
        if (!ok) return;
        fetch('{{ route('member.regenerate-password', $relationship->dependent->id) }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
        })
        .then(r => r.json().then(data => ({ ok: r.ok, data })))
        .then(({ ok, data }) => {
            if (ok && data.password) {
                window.dispatchEvent(new CustomEvent('show-generated-password', { detail: { password: data.password, emailed: !!data.emailed } }));
            } else {
                showToast('error', '{{ __("member.templates_member_show_error_title") }}', data.message || '{{ __("member.templates_member_show_could_not_generate_password") }}');
            }
        })
        .catch(() => showToast('error', '{{ __("member.templates_member_show_error_title") }}', '{{ __("member.templates_member_show_error_occurred_retry") }}'));
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
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>{{ __('member.templates_member_show_delete_account') }}
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
                            <strong>{{ __('member.templates_member_show_delete_warning') }}</strong> {{ __('member.templates_member_show_delete_cannot_undo') }} <strong>{{ $relationship->dependent->full_name }}</strong> {{ __('member.templates_member_show_delete_remove_data') }}
                        </div>

                        <p class="text-gray-500 text-sm mb-3">
                            {{ __('member.templates_member_show_delete_confirm_instr') }}
                        </p>

                        <div class="mb-3">
                            <label for="confirmName" class="block text-sm font-medium text-gray-700 mb-1 font-semibold">{{ __('member.templates_member_show_delete_type_to_confirm', ['name' => $relationship->dependent->full_name]) }}</label>
                            <input type="text" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" id="confirmName" name="confirm_name" x-model="confirmName" required>
                            <div class="text-xs text-gray-500 mt-1 text-gray-500">
                                {{ __('member.templates_member_show_delete_soft_note') }}
                            </div>
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 p-4 border-t bg-gray-50">
                        <button type="button" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-md text-sm font-medium hover:bg-gray-300 transition-colors" @click="open = false">{{ __('shared.cancel') }}</button>
                        <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-red-700 transition-colors disabled:opacity-50" :disabled="confirmName !== expectedName">
                            <i class="bi bi-trash me-2"></i>{{ __('member.templates_member_show_delete_account') }}
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
            ecList.innerHTML = '<div class="text-center py-6"><i class="bi bi-telephone text-gray-300" style="font-size:2rem;"></i><p class="text-gray-400 text-sm mt-2 mb-0">{{ __('member.templates_member_show_no_emergency_contacts') }}</p></div>';
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
                        ${d.uploaded_at ? `<div class="text-xs text-gray-400">{{ __("member.templates_member_show_uploaded") }} ${d.uploaded_at}</div>` : ''}
                    </div>
                    ${d.file_path ? `<a href="/storage/${d.file_path}" target="_blank" class="flex-shrink-0 w-8 h-8 rounded-lg border border-primary text-primary flex items-center justify-center hover:bg-primary hover:text-white transition-colors"><i class="bi bi-eye" style="font-size:0.85rem;"></i></a>` : ''}
                </div>`
            ).join('') + '</div>';
        } else {
            docsList.innerHTML = '<div class="text-center py-6"><i class="bi bi-file-earmark text-gray-300" style="font-size:2rem;"></i><p class="text-gray-400 text-sm mt-2 mb-0">{{ __('member.templates_member_show_no_documents') }}</p></div>';
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
                        ${c.noted_at ? `<div class="text-xs text-gray-400 mt-0.5">{{ __("member.templates_member_show_noted") }} ${c.noted_at}</div>` : ''}
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

// ===== Billing: view a bill's details + settle it (upload proof) =====
window.memberBilling = function (cfg) {
    return {
        open: false,
        submitting: false,
        proof: null,
        preview: null,
        canSettle: !!cfg.canSettle,
        current: { id: null, club: '', item: '', amount: '', cur: '', period: '', date: '', status: '', label: '', settleable: false, hasproof: false, logo: '' },

        get badgeClass() {
            const s = this.current.status;
            if (s === 'paid')    return 'bg-green-100 text-green-700';
            if (s === 'pending') return 'bg-blue-100 text-blue-700';
            if (s === 'due')     return 'bg-amber-100 text-amber-700';
            return 'bg-gray-100 text-gray-600';
        },

        openBill(el) {
            const d = el.dataset;
            this.current = {
                id: d.id, club: d.club, item: d.item, amount: d.amount, cur: d.cur,
                period: d.period, date: d.date, status: d.status, label: d.label,
                settleable: d.settleable === '1', hasproof: d.hasproof === '1', logo: d.logo || '',
            };
            this.proof = null; this.preview = null; this.open = true;
        },
        close() { this.open = false; },

        pickFile(e) {
            const f = e.target.files && e.target.files[0];
            if (!f) return;
            if (!f.type.startsWith('image/')) { window.showToast && window.showToast('error', @js(__('Please choose an image.'))); return; }
            const r = new FileReader();
            r.onload = () => { this.proof = r.result; this.preview = r.result; };
            r.readAsDataURL(f);
        },

        async submit() {
            if (!this.proof) { window.showToast && window.showToast('error', @js(__('Add a payment proof image.'))); return; }
            this.submitting = true;
            try {
                const res = await fetch(cfg.settleBase + '/' + this.current.id + '/settle', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': cfg.csrf, 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({ payment_proof_base64: this.proof }),
                });
                const j = await res.json();
                window.showToast && window.showToast(j.success ? 'success' : 'error', j.message || '');
                if (j.success) { this.patchRow(this.current.id, 'pending'); this.close(); }
            } catch (e) {
                window.showToast && window.showToast('error', @js(__('Something went wrong.')));
            } finally {
                this.submitting = false;
            }
        },

        // Patch the table row in place — no reload.
        patchRow(id, state) {
            const row = document.getElementById('pay-card-' + id);
            if (!row) return;
            const cell = row.querySelector('[data-role=badge-cell]');
            if (state === 'pending') {
                row.dataset.status = 'pending'; row.dataset.settleable = '0'; row.dataset.hasproof = '1';
                row.dataset.label = @js(__('Pending review'));
                if (cell) cell.innerHTML = '<span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700"><i class="bi bi-hourglass-split"></i> ' + @js(__('Pending review')) + '</span>';
            } else if (state === 'paid') {
                row.dataset.status = 'paid'; row.dataset.settleable = '0';
                row.dataset.label = @js(__('Paid'));
                if (cell) cell.innerHTML = '<span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700"><i class="bi bi-check-circle-fill"></i> ' + @js(__('Paid')) + '</span>';
            }
        },
    };
};
</script>
@endpush
