@extends('layouts.app')

@php
    // Guarded: this view and member/show both declare this helper; the guard
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
<div class="tf-container">

    <!-- Header -->
    <div class="flex justify-between items-center mb-4">
        <div>
            <h2 class="font-bold mb-1">{{ __('member.family_show_member_profile') }}</h2>
            <p class="text-muted mb-0">{{ __('member.family_show_member_subtitle') }}</p>
        </div>
    </div>

    <!-- Profile Card -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="flex">
            <!-- Profile Picture -->
            <div style="width: 180px; min-height: 250px; border-radius: 0.375rem 0 0 0.375rem;">
                @if($relationship->dependent->profile_picture)
                    <img src="{{ asset('storage/' . $relationship->dependent->profile_picture) }}?v={{ $relationship->dependent->updated_at->timestamp }}" alt="{{ $relationship->dependent->full_name }}" class="w-full h-full" style="object-fit: cover; border-radius: 0.375rem 0 0 0.375rem;">
                @else
                    <div class="w-full h-full flex items-center justify-center text-white font-bold" style="font-size: 3rem; background: linear-gradient(135deg, {{ $relationship->dependent->gender === 'Male' ? '#0d6efd 0%, #0a58ca 100%' : '#d63384 0%, #a61e4d 100%' }}); border-radius: 0.375rem 0 0 0.375rem;">
                        {{ mb_strtoupper(mb_substr($relationship->dependent->full_name, 0, 1, 'UTF-8'), 'UTF-8') }}
                    </div>
                @endif
            </div>

            <!-- Profile Info -->
            <div class="flex-1 p-4">
                <div class="flex justify-between items-start mb-2">
                    <h3 class="font-bold mb-0">{{ $relationship->dependent->full_name }}</h3>
                    @if($relationship->relationship_type == 'self' || Auth::id() == $relationship->guardian_user_id)
                        <div>
                            <div class="dropdown">
                                <button class="btn btn-primary rounded-pill dropdown-toggle" type="button" id="actionDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="bi bi-lightning"></i> {{ __('member.family_show_action') }}
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 py-1" aria-labelledby="actionDropdown" style="min-width: 280px; border-radius: 0.75rem; overflow: hidden;">
                                    <li><a class="dropdown-item d-flex align-items-center gap-2 py-2 px-3 !whitespace-normal" href="#" onclick="event.preventDefault(); window.dispatchEvent(new CustomEvent('open-attendance-add-modal'));"><i class="bi bi-calendar-check text-success flex-shrink-0" style="width: 18px; text-align: center;"></i>{{ __('member.family_show_add_attendance_record') }}</a></li>
                                    <li><a class="dropdown-item d-flex align-items-center gap-2 py-2 px-3 !whitespace-normal" href="#" onclick="event.preventDefault(); window.dispatchEvent(new CustomEvent('open-event-add-modal'));"><i class="bi bi-calendar-event text-info flex-shrink-0" style="width: 18px; text-align: center;"></i>{{ __('member.family_show_add_event_participation') }}</a></li>
                                    <li><a class="dropdown-item d-flex align-items-center gap-2 py-2 px-3 !whitespace-normal" href="#" data-bs-target="#healthUpdateModal"><i class="bi bi-heart-pulse text-danger flex-shrink-0" style="width: 18px; text-align: center;"></i>{{ __('member.family_show_add_health_update') }}</a></li>
                                    <li><a class="dropdown-item d-flex align-items-center gap-2 py-2 px-3 !whitespace-normal" href="#" data-bs-toggle="modal" data-bs-target="#tournamentParticipationModal"><i class="bi bi-award text-warning flex-shrink-0" style="width: 18px; text-align: center;"></i>{{ __('member.family_show_add_tournament_participation') }}</a></li>
                                    <li><hr class="dropdown-divider my-1"></li>
                                    <li><a class="dropdown-item d-flex align-items-center gap-2 py-2 px-3 !whitespace-normal" href="#" onclick="event.preventDefault(); window.dispatchEvent(new CustomEvent('open-profile-modal'));"><i class="bi bi-pencil text-secondary flex-shrink-0" style="width: 18px; text-align: center;"></i>{{ __('member.family_show_edit_info') }}</a></li>
                                    <li><a class="dropdown-item d-flex align-items-center gap-2 py-2 px-3 !whitespace-normal" href="#" onclick="event.preventDefault(); window.dispatchEvent(new CustomEvent('open-goal-add-modal'));"><i class="bi bi-bullseye text-primary flex-shrink-0" style="width: 18px; text-align: center;"></i>{{ __('member.family_show_set_a_goal') }}</a></li>
                                </ul>
                            </div>
                        </div>
                    @else
                        <button class="btn btn-primary btn-sm rounded-pill">
                            <i class="bi bi-person-plus me-1"></i>{{ __('member.family_show_follow') }}
                        </button>
                    @endif
                </div>
                            @if($relationship->dependent->motto)
                                <p class="text-muted fst-italic mb-3">"{{ $relationship->dependent->motto }}"</p>
                            @endif

                            <!-- Achievement Badges -->
                            <div class="flex gap-2 mb-3 flex-wrap">
                                <a href="#" class="border bg-white rounded px-2 py-1 text-decoration-none achievement-badge" style="font-size: 1rem;" data-medal-type="special" onclick="filterTournamentsByMedal('special')">🏆 <span class="font-semibold text-dark">{{ $awardCounts['special'] }}</span></a>
                                <a href="#" class="border bg-white rounded px-2 py-1 text-decoration-none achievement-badge" style="font-size: 1rem;" data-medal-type="1st" onclick="filterTournamentsByMedal('1st')">🥇 <span class="font-semibold text-dark">{{ $awardCounts['1st'] }}</span></a>
                                <a href="#" class="border bg-white rounded px-2 py-1 text-decoration-none achievement-badge" style="font-size: 1rem;" data-medal-type="2nd" onclick="filterTournamentsByMedal('2nd')">🥈 <span class="font-semibold text-dark">{{ $awardCounts['2nd'] }}</span></a>
                                <a href="#" class="border bg-white rounded px-2 py-1 text-decoration-none achievement-badge" style="font-size: 1rem;" data-medal-type="3rd" onclick="filterTournamentsByMedal('3rd')">🥉 <span class="font-semibold text-dark">{{ $awardCounts['3rd'] }}</span></a>
                                <a href="#goals" class="border bg-white rounded px-2 py-1 text-decoration-none" style="font-size: 1rem;" onclick="document.getElementById('goals-tab').click();">🎯 <span class="font-semibold text-dark">{{ $activeGoalsCount + $completedGoalsCount }}</span></a>
                                <a href="#" class="border bg-white rounded px-2 py-1 text-decoration-none" style="font-size: 1rem;">⭐ <span class="font-semibold text-dark">{{ $totalAffiliations }}</span></a>
                            </div>

                            <!-- Status Badges -->
                            <div class="flex gap-3 mb-3 items-center flex-wrap">
                                <span class="text-muted small">
                                    <span class="font-semibold text-dark nationality-display" data-iso3="{{ $relationship->dependent->nationality }}">{{ $relationship->dependent->nationality }}</span>
                                </span>
                                <span class="text-muted small">
                                    <i class="bi bi-{{ $relationship->dependent->gender === 'Male' ? 'gender-male' : 'gender-female' }} me-1"></i>
                                    <span class="font-semibold text-dark">{{ $relationship->dependent->gender === 'Male' ? __('member.family_show_male') : __('member.family_show_female') }}</span>
                                </span>
                                <span class="text-muted small">
                                    <i class="bi bi-calendar-event me-1"></i>
                                    {{ __('member.family_show_age') }} <span class="font-semibold text-dark">{{ $relationship->dependent->age }}</span>
                                </span>
                                <span class="text-muted small">
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
                                    {{ $symbol }} <span class="font-semibold text-dark">{{ $horoscope }}</span>
                                </span>
                                <span class="text-muted small">
                                    <i class="bi bi-check-circle-fill text-success me-1"></i>
                                    <span class="font-semibold text-success">{{ __('member.family_show_active') }}</span>
                                </span>
                                <span class="text-muted small">
                                    <i class="bi bi-calendar-check me-1"></i>
                                    {{ __('member.family_show_joined') }} <span class="font-semibold text-dark">{{ $relationship->dependent->created_at->format('F Y') }}</span>
                                </span>
                            </div>

                            <!-- Social Media Icons -->
                            @if($relationship->dependent->social_links && count($relationship->dependent->social_links) > 0)
                                <div class="flex gap-2 flex-wrap">
                                    @php
                                        $socialLinks = $relationship->dependent->social_links;
                                        ksort($socialLinks); // Sort by platform name

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
                                            <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-secondary rounded-circle" style="width: 32px; height: 32px; padding: 0; display: flex; align-items: center; justify-content: center;" title="{{ $socialTitles[$platform] ?? ucfirst($platform) }}">
                                                @if($platform === 'twitter')
                                                    <span style="font-weight: bold; font-size: 1.2rem;">{{ $socialIcons[$platform] }}</span>
                                                @else
                                                    <i class="bi {{ $socialIcons[$platform] }}"></i>
                                                @endif
                                            </a>
                                        @endif
                                    @endforeach
                                </div>
                            @endif
            </div>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <ul class="nav nav-tabs nav-fill mb-4" id="profileTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active text-dark" id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview" type="button" role="tab">
                <i class="bi bi-eye me-2"></i>{{ __('member.family_show_tab_overview') }}
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link text-dark" id="attendance-tab" data-bs-toggle="tab" data-bs-target="#attendance" type="button" role="tab">
                <i class="bi bi-calendar-check me-2"></i>{{ __('member.family_show_tab_attendance') }}
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link text-dark" id="health-tab" data-bs-toggle="tab" data-bs-target="#health" type="button" role="tab">
                <i class="bi bi-heart-pulse me-2"></i>{{ __('member.family_show_tab_health') }}
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link text-dark" id="goals-tab" data-bs-toggle="tab" data-bs-target="#goals" type="button" role="tab">
                <i class="bi bi-bullseye me-2"></i>{{ __('member.family_show_tab_goals') }}
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link text-dark" id="affiliations-tab" data-bs-toggle="tab" data-bs-target="#affiliations" type="button" role="tab">
                <i class="bi bi-diagram-3 me-2"></i>{{ __('member.family_show_tab_affiliations') }}
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link text-dark" id="tournaments-tab" data-bs-toggle="tab" data-bs-target="#tournaments" type="button" role="tab">
                <i class="bi bi-award me-2"></i>{{ __('member.family_show_tab_tournaments') }}
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link text-dark" id="events-tab" data-bs-toggle="tab" data-bs-target="#events" type="button" role="tab">
                <i class="bi bi-calendar-event me-2"></i>{{ __('member.family_show_tab_events') }}
            </button>
        </li>
    </ul>

    <!-- Tab Content -->
    <div class="tab-content" id="profileTabsContent">
        <!-- Overview Tab -->
        <div class="tab-pane fade show active" id="overview" role="tabpanel">
            <!-- Profile Statistics and Revenue Chart Row -->
            <div class="row mb-4">
                <!-- Profile Statistics -->
                <div class="col-lg-6">
                    <div class="card shadow-sm border-0 h-full">
                        <div class="card-body p-4">
                            <div class="flex items-center mb-2">
                                <i class="bi bi-bar-chart-line text-primary me-2"></i>
                                <h5 class="mb-0 font-bold">{{ __('member.family_show_profile_statistics') }}</h5>
                            </div>
                            <p class="text-muted small mb-4">{{ __('member.family_show_profile_statistics_sub') }}</p>

                            <div class="row g-3">
                                <!-- Total Sessions -->
                                <div class="col-md-6">
                            <div class="flex items-center gap-3 p-3 bg-gray-50 rounded">
                                <div class="rounded-circle flex items-center justify-center" style="width: 48px; height: 48px; background-color: #6f42c1;">
                                    <i class="bi bi-people-fill text-white"></i>
                                </div>
                                <div class="flex-1">
                                    <div class="small text-muted mb-1">{{ __('member.family_show_total_sessions') }}</div>
                                    <div class="h4 font-bold mb-2">127</div>
                                    <div class="progress" style="height: 4px; background-color: #e9ecef;">
                                        <div class="progress-bar" role="progressbar" style="width: 85%; background: linear-gradient(90deg, #6f42c1 0%, #8b5cf6 100%);"></div>
                                    </div>
                                    <small class="text-muted" style="font-size: 0.75rem;">{{ __('member.family_show_sessions_completed_year') }}</small>
                                </div>
                            </div>
                        </div>



                                <!-- Attendance Rate -->
                                <div class="col-md-6">
                            <div class="flex items-center gap-3 p-3 bg-gray-50 rounded">
                                <div class="rounded-circle flex items-center justify-center" style="width: 48px; height: 48px; background-color: #6f42c1;">
                                    <i class="bi bi-graph-up-arrow text-white"></i>
                                </div>
                                <div class="flex-1">
                                    <div class="small text-muted mb-1">{{ __('member.family_show_attendance_rate') }}</div>
                                    <div class="h4 font-bold mb-2">85%</div>
                                    <div class="progress" style="height: 4px; background-color: #e9ecef;">
                                        <div class="progress-bar" role="progressbar" style="width: 85%; background: linear-gradient(90deg, #6f42c1 0%, #10b981 100%);"></div>
                                    </div>
                                    <small class="text-muted" style="font-size: 0.75rem;">{{ __('member.family_show_avg_session_attendance') }}</small>
                                </div>
                            </div>
                        </div>



                                <!-- Achievements -->
                                <div class="col-md-6">
                            <div class="flex items-center gap-3 p-3 bg-gray-50 rounded">
                                <div class="rounded-circle flex items-center justify-center" style="width: 48px; height: 48px; background-color: #8b5cf6;">
                                    <i class="bi bi-trophy-fill text-white"></i>
                                </div>
                                <div class="flex-1">
                                    <div class="small text-muted mb-1">{{ __('member.family_show_achievements') }}</div>
                                    <div class="h4 font-bold mb-2">8</div>
                                    <div class="progress" style="height: 4px; background-color: #e9ecef;">
                                        <div class="progress-bar" role="progressbar" style="width: 40%; background: linear-gradient(90deg, #6f42c1 0%, #10b981 100%);"></div>
                                    </div>
                                    <small class="text-muted" style="font-size: 0.75rem;">{{ __('member.family_show_total_badges_earned') }}</small>
                                </div>
                            </div>
                        </div>

                                <!-- Goal Completion -->
                                <div class="col-md-6">
                            <div class="flex items-center gap-3 p-3 bg-gray-50 rounded">
                                <div class="rounded-circle flex items-center justify-center" style="width: 48px; height: 48px; background-color: #6f42c1;">
                                    <i class="bi bi-check-circle-fill text-white"></i>
                                </div>
                                <div class="flex-1">
                                    <div class="small text-muted mb-1">{{ __('member.family_show_goal_completion') }}</div>
                                    <div class="h4 font-bold mb-2">75%</div>
                                    <div class="progress" style="height: 4px; background-color: #e9ecef;">
                                        <div class="progress-bar" role="progressbar" style="width: 75%; background: linear-gradient(90deg, #6f42c1 0%, #10b981 100%);"></div>
                                    </div>
                                    <small class="text-muted" style="font-size: 0.75rem;">{{ __('member.family_show_current_goals_achieved') }}</small>
                                </div>
                            </div>
                        </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Self Investment Chart -->
                <div class="col-lg-6">
                    <div class="card shadow-sm border-0 h-full">
                        <div class="card-body p-4">
                            <div class="flex items-center mb-2">
                                <i class="bi bi-bar-chart-line text-primary me-2"></i>
                                <h5 class="mb-0 font-bold">{{ __('member.family_show_self_investment_chart') }}</h5>
                            </div>
                            <p class="text-muted small mb-4">{{ __('member.family_show_self_investment_sub') }}</p>

                            <div class="flex items-center justify-center" style="min-height: 300px;">
                                <div class="text-center">
                                    <i class="bi bi-graph-up text-muted" style="font-size: 3rem;"></i>
                                    <p class="text-muted mt-3 mb-1">{{ __('member.family_show_revenue_chart_coming_soon') }}</p>
                                    <small class="text-muted">{{ __('member.family_show_revenue_chart_hint') }}</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Complete Payment & Revenue History -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body p-4">
                    <div class="flex items-center mb-2">
                        <i class="bi bi-receipt text-primary me-2"></i>
                        <h5 class="mb-0 font-bold">{{ __('member.family_show_payment_history_title') }}</h5>
                    </div>
                    <p class="text-muted small mb-4">{{ __('member.family_show_payment_history_sub') }}</p>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-muted small font-semibold">{{ __('member.family_show_th_date') }}</th>
                                    <th class="text-muted small font-semibold">{{ __('member.family_show_th_transaction_type') }}</th>
                                    <th class="text-muted small font-semibold">{{ __('member.family_show_th_package_item') }}</th>
                                    <th class="text-muted small font-semibold">{{ __('member.family_show_th_duration') }}</th>
                                    <th class="text-muted small font-semibold">{{ __('member.family_show_th_sessions') }}</th>
                                    <th class="text-muted small font-semibold">{{ __('member.family_show_th_amount') }}</th>
                                    <th class="text-muted small font-semibold">{{ __('member.family_show_th_status') }}</th>
                                    <th class="text-muted small font-semibold">{{ __('member.family_show_th_method') }}</th>
                                    <th class="text-muted small font-semibold">{{ __('member.family_show_th_evidence') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($invoices as $invoice)
                                <tr>
                                    <td class="small">{{ $invoice->created_at->format('Y-m-d') }}</td>
                                    <td class="small text-primary">{{ __('member.family_show_invoice') }}</td>
                                    <td class="small">{{ $invoice->tenant->club_name ?? 'N/A' }}</td>
                                    <td class="small text-muted">-</td>
                                    <td class="small">-</td>
                                    <td class="small font-semibold" style="color: {{ $invoice->status == 'paid' ? '#10b981' : '#f59e0b' }};">{{ $invoice->amount }} BHD</td>
                                    <td>
                                        @if($invoice->status == 'paid')
                                            <span class="badge bg-success-subtle text-success small">✓ {{ __('member.family_show_status_paid') }}</span>
                                        @elseif($invoice->status == 'due')
                                            <span class="badge bg-warning-subtle text-warning small">○ {{ __('member.family_show_status_due') }}</span>
                                        @else
                                            <span class="badge bg-secondary-subtle text-secondary small">{{ ucfirst($invoice->status) }}</span>
                                        @endif
                                    </td>
                                    <td class="small">-</td>
                                    <td class="small">
                                        <a href="{{ route('bills.receipt', $invoice->id) }}" target="_blank" title="{{ __('member.family_show_view_receipt') }}"><i class="bi bi-file-earmark-text text-primary"></i></a>
                                        <a href="{{ route('bills.receipt', $invoice->id) }}?download=1" download title="{{ __('member.family_show_download_receipt') }}"><i class="bi bi-download text-secondary ms-1"></i></a>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="9" class="text-center text-muted small">{{ __('member.family_show_no_invoices') }}</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>


        </div>

        <!-- Attendance Tab -->
        <div class="tab-pane fade" id="attendance" role="tabpanel">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h1 class="h3 font-bold">{{ __('member.family_show_member_attendance') }}</h1>
                            <p class="text-muted">{{ __('member.family_show_attendance_sub') }}</p>
                        </div>
                    </div>

                    <!-- Summary Cards -->
                    <div class="row g-4 mb-4">
                        <div class="col-md-4">
                            <div class="card shadow-sm bg-gray-50">
                                <div class="card-body text-center">
                                    <div class="text-5xl font-bold text-success mb-2" id="attendanceCompletedCount">{{ $sessionsCompleted }}</div>
                                    <h6 class="card-title text-muted">{{ __('member.family_show_sessions_completed') }}</h6>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card shadow-sm bg-gray-50">
                                <div class="card-body text-center">
                                    <div class="text-5xl font-bold text-danger mb-2" id="attendanceNoShowCount">{{ $noShows }}</div>
                                    <h6 class="card-title text-muted">{{ __('member.family_show_no_shows') }}</h6>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card shadow-sm bg-gray-50">
                                <div class="card-body text-center">
                                    <div class="text-5xl font-bold text-primary mb-2">{{ $attendanceRate }}%</div>
                                    <h6 class="card-title text-muted">{{ __('member.family_show_attendance_rate') }}</h6>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Attendance Table -->
                    <div class="card shadow-sm">
                        <div class="card-header bg-gray-50">
                            <h6 class="card-title mb-0">{{ __('member.family_show_session_history') }}</h6>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="border-0 font-semibold">{{ __('member.family_show_th_date_time') }}</th>
                                            <th class="border-0 font-semibold">{{ __('member.family_show_th_session_type') }}</th>
                                            <th class="border-0 font-semibold">{{ __('member.family_show_th_trainer_name') }}</th>
                                            <th class="border-0 font-semibold">{{ __('member.family_show_th_status') }}</th>
                                            <th class="border-0 font-semibold">{{ __('member.family_show_th_notes') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody id="attendanceTbody">
                                        @forelse($attendanceRecords as $record)
                                        <tr>
                                            <td class="align-middle">
                                                <div class="font-semibold">{{ $record->session_datetime->format('M j, Y') }}</div>
                                                <small class="text-muted">{{ $record->session_datetime->format('g:i A') }}</small>
                                            </td>
                                            <td class="align-middle">{{ $record->session_type }}</td>
                                            <td class="align-middle">{{ $record->trainer_name }}</td>
                                            <td class="align-middle">
                                                @if($record->status === 'completed')
                                                    <span class="badge bg-success">{{ __('member.family_show_completed') }}</span>
                                                @else
                                                    <span class="badge bg-danger">{{ __('member.family_show_no_show') }}</span>
                                                @endif
                                            </td>
                                            <td class="align-middle">
                                                <small class="text-muted">{{ $record->notes ?: '-' }}</small>
                                            </td>
                                        </tr>
                                        @empty
                                        <tr id="attendanceEmptyRow">
                                            <td colspan="5" class="text-center py-4">
                                                <i class="bi bi-calendar-check text-muted" style="font-size: 2rem;"></i>
                                                <p class="text-muted mt-2 mb-0">{{ __('member.family_show_no_attendance') }}</p>
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
        <div class="tab-pane fade" id="health" role="tabpanel">
            <!-- Health Tracking Header -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body p-4">
                    <div class="flex items-center mb-2">
                        <i class="bi bi-heart-pulse text-danger me-2"></i>
                        <h5 class="mb-0 font-bold">{{ __('member.family_show_health_metrics_overview') }}</h5>
                    </div>

                    <div class="flex justify-between items-center mb-4">
                        <p class="text-muted small mb-0">{{ __('member.family_show_health_metrics_sub') }}</p>

                        @if($latestHealthRecord)
                            @php
                                $latestDate = $latestHealthRecord->recorded_at;
                                $now = \Carbon\Carbon::now();
                                $diff = $latestDate->diff($now);
                            @endphp

                            <div class="flex items-center gap-3">
                                <div class="flex items-center gap-2">
                                    <i class="bi bi-calendar-event text-primary"></i>
                                    <span class="font-semibold">{{ __('member.family_show_snapshot_date') }}</span>
                                    <span class="text-muted">{{ $latestDate->format('F j, Y') }}</span>
                                </div>
                                <div class="w-px bg-gray-300 self-stretch"></div>
                                <div class="flex items-center gap-2">
                                    <i class="bi bi-clock-history text-primary"></i>
                                    <span class="font-semibold">{{ __('member.family_show_time_since') }}</span>
                                    <span class="text-muted">
                                        @if($diff->y > 0)
                                            {{ $diff->y }} {{ $diff->y == 1 ? __('member.family_show_year') : __('member.family_show_years') }}
                                        @endif
                                        @if($diff->m > 0)
                                            {{ $diff->m }} {{ $diff->m == 1 ? __('member.family_show_month') : __('member.family_show_months') }}
                                        @endif
                                        @if($diff->d > 0)
                                            {{ $diff->d }} {{ $diff->d == 1 ? __('member.family_show_day') : __('member.family_show_days') }}
                                        @endif
                                        {{ __('member.family_show_ago') }}
                                    </span>
                                </div>
                            </div>
                        @else
                            <div class="text-muted small">{{ __('member.family_show_no_health_records_available') }}</div>
                        @endif
                    </div>

                    <!-- Health Metrics Cards -->
                    <div class="row g-3">
                        @if($latestHealthRecord)
                            <!-- Weight -->
                            <div class="col-md-2">
                                <div class="text-center p-3 bg-gray-50 rounded">
                                    <i class="bi bi-speedometer2 text-purple mb-2" style="font-size: 1.5rem; color: #8b5cf6;"></i>
                                    <div class="h4 font-bold mb-0">{{ $latestHealthRecord->weight ?? 'N/A' }}</div>
                                    <small class="text-muted">{{ __('member.family_show_weight_kg') }}</small>
                                </div>
                            </div>

                            <!-- Body Fat -->
                            <div class="col-md-2">
                                <div class="text-center p-3 bg-gray-50 rounded">
                                    <i class="bi bi-activity text-warning mb-2" style="font-size: 1.5rem;"></i>
                                    <div class="h4 font-bold mb-0">{{ $latestHealthRecord->body_fat_percentage ?? 'N/A' }}%</div>
                                    <small class="text-muted">{{ __('member.family_show_body_fat') }}</small>
                                </div>
                            </div>

                            <!-- Body Water -->
                            <div class="col-md-2">
                                <div class="text-center p-3 bg-gray-50 rounded">
                                    <i class="bi bi-droplet text-info mb-2" style="font-size: 1.5rem;"></i>
                                    <div class="h4 font-bold mb-0">{{ $latestHealthRecord->body_water_percentage ?? 'N/A' }}%</div>
                                    <small class="text-muted">{{ __('member.family_show_body_water') }}</small>
                                </div>
                            </div>

                            <!-- Muscle Mass -->
                            <div class="col-md-2">
                                <div class="text-center p-3 bg-gray-50 rounded">
                                    <i class="bi bi-heart text-success mb-2" style="font-size: 1.5rem;"></i>
                                    <div class="h4 font-bold mb-0">{{ $latestHealthRecord->muscle_mass ?? 'N/A' }}</div>
                                    <small class="text-muted">{{ __('member.family_show_muscle_mass') }}</small>
                                </div>
                            </div>

                            <!-- Bone Mass -->
                            <div class="col-md-2">
                                <div class="text-center p-3 bg-gray-50 rounded">
                                    <i class="bi bi-capsule text-secondary mb-2" style="font-size: 1.5rem;"></i>
                                    <div class="h4 font-bold mb-0">{{ $latestHealthRecord->bone_mass ?? 'N/A' }}</div>
                                    <small class="text-muted">{{ __('member.family_show_bone_mass') }}</small>
                                </div>
                            </div>

                            <!-- BMR -->
                            <div class="col-md-2">
                                <div class="text-center p-3 bg-gray-50 rounded">
                                    <i class="bi bi-lightning text-danger mb-2" style="font-size: 1.5rem;"></i>
                                    <div class="h4 font-bold mb-0">{{ $latestHealthRecord->bmr ?? 'N/A' }}</div>
                                    <small class="text-muted">{{ __('member.family_show_bmr_cal') }}</small>
                                </div>
                            </div>
                        @else
                            <div class="col-12">
                                <div class="text-center py-4">
                                    <i class="bi bi-heart-pulse text-muted" style="font-size: 3rem;"></i>
                                    <p class="text-muted mt-3">{{ __('member.family_show_no_health_metrics') }}</p>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Body Composition Analysis & Compare Row -->
            <div class="row mb-4">
                <!-- Body Composition Analysis -->
                <div class="col-lg-7">
                    <div class="card shadow-sm border-0 h-full">
                        <div class="card-body p-4">
                            <h5 class="font-bold mb-4"><i class="bi bi-activity me-2"></i>{{ __('member.family_show_body_composition') }}</h5>

                            <div class="chart-container" style="position: relative; height: 500px; width: 100%;">
                                <canvas id="radarChart" data-current='@json($comparisonRecords->first())' data-previous='@json($comparisonRecords->skip(1)->first())'></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Compare -->
                <div class="col-lg-5">
                    <div class="card shadow-sm border-0 h-full">
                        <div class="card-body p-4">
                            <h5 class="font-bold mb-4"><i class="bi bi-bar-chart-line me-2"></i>{{ __('member.family_show_compare') }}</h5>

                            @if($comparisonRecords->count() >= 2)
                                @php
                                    $current = $comparisonRecords->first();
                                    $previous = $comparisonRecords->skip(1)->first();
                                @endphp

                                <div class="mb-3">
                                    <div class="row g-2">
                                        <div class="col-6 text-center">
                                            <label class="form-label font-bold">{{ __('member.family_show_from') }}</label>
                                            <select class="form-select form-select-sm" id="currentDate">
                                                @foreach($healthRecords as $record)
                                                    <option value="{{ $record->id }}" {{ $record->id == $current->id ? 'selected' : '' }}>
                                                        {{ $record->recorded_at->format('M j, Y') }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-6 text-center">
                                            <label class="form-label font-bold">{{ __('member.family_show_to') }}</label>
                                            <select class="form-select form-select-sm" id="previousDate">
                                                @foreach($healthRecords as $record)
                                                    <option value="{{ $record->id }}" {{ $record->id == $previous->id ? 'selected' : '' }}>
                                                        {{ $record->recorded_at->format('M j, Y') }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="mt-3">
                                        <div class="alert alert-secondary text-center py-2" id="timeDifference">
                                            @if($current && $previous)
                                                <strong>{{ __('member.family_show_time_between_records') }}</strong> {{ calculateTimeDifference($current->recorded_at, $previous->recorded_at) }}
                                            @else
                                                {{ __('member.family_show_select_dates') }}
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-sm align-middle">
                                        <thead>
                                            <tr class="border-bottom">
                                                <th class="text-muted small font-semibold">{{ __('member.family_show_th_metric') }}</th>
                                                <th class="text-muted small font-semibold text-end">{{ __('member.family_show_th_current') }}</th>
                                                <th class="text-muted small font-semibold text-end">{{ __('member.family_show_th_previous') }}</th>
                                                <th class="text-muted small font-semibold text-center">{{ __('member.family_show_th_change') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @php
                                                if (! function_exists('getChangeIcon')) {
                                                function getChangeIcon($current, $previous) {
                                                    if ($current > $previous) return '<i class="bi bi-arrow-up text-success"></i>';
                                                    if ($current < $previous) return '<i class="bi bi-arrow-down text-danger"></i>';
                                                    return '<i class="bi bi-dash text-muted"></i>';
                                                }
                                                }
                                            @endphp
                                            <tr data-metric="height">
                                                <td class="small"><i class="bi bi-rulers me-2"></i>{{ __('member.family_show_height') }}</td>
                                                <td class="small text-end font-semibold text-primary">{{ $current->height ?? 'N/A' }}cm</td>
                                                <td class="small text-end text-danger">{{ $previous->height ?? 'N/A' }}cm</td>
                                                <td class="text-center">{!! $current->height && $previous->height ? getChangeIcon($current->height, $previous->height) : '-' !!}</td>
                                            </tr>
                                            <tr data-metric="weight">
                                                <td class="small"><i class="bi bi-speedometer2 me-2"></i>{{ __('member.family_show_weight') }}</td>
                                                <td class="small text-end font-semibold text-primary">{{ $current->weight ?? 'N/A' }}kg</td>
                                                <td class="small text-end text-danger">{{ $previous->weight ?? 'N/A' }}kg</td>
                                                <td class="text-center">{!! $current->weight && $previous->weight ? getChangeIcon($current->weight, $previous->weight) : '-' !!}</td>
                                            </tr>
                                            <tr data-metric="body_fat">
                                                <td class="small"><i class="bi bi-activity me-2"></i>{{ __('member.family_show_body_fat') }}</td>
                                                <td class="small text-end font-semibold text-primary">{{ $current->body_fat_percentage ?? 'N/A' }}%</td>
                                                <td class="small text-end text-danger">{{ $previous->body_fat_percentage ?? 'N/A' }}%</td>
                                                <td class="text-center">{!! $current->body_fat_percentage && $previous->body_fat_percentage ? getChangeIcon($current->body_fat_percentage, $previous->body_fat_percentage) : '-' !!}</td>
                                            </tr>
                                            <tr data-metric="bmi">
                                                <td class="small"><i class="bi bi-calculator me-2"></i>{{ __('member.family_show_bmi') }}</td>
                                                <td class="small text-end font-semibold text-primary">{{ $current->bmi ?? 'N/A' }}</td>
                                                <td class="small text-end text-danger">{{ $previous->bmi ?? 'N/A' }}</td>
                                                <td class="text-center">{!! $current->bmi && $previous->bmi ? getChangeIcon($current->bmi, $previous->bmi) : '-' !!}</td>
                                            </tr>
                                            <tr data-metric="body_water">
                                                <td class="small"><i class="bi bi-droplet me-2"></i>{{ __('member.family_show_body_water') }}</td>
                                                <td class="small text-end font-semibold text-primary">{{ $current->body_water_percentage ?? 'N/A' }}%</td>
                                                <td class="small text-end text-danger">{{ $previous->body_water_percentage ?? 'N/A' }}%</td>
                                                <td class="text-center">{!! $current->body_water_percentage && $previous->body_water_percentage ? getChangeIcon($current->body_water_percentage, $previous->body_water_percentage) : '-' !!}</td>
                                            </tr>
                                            <tr data-metric="muscle_mass">
                                                <td class="small"><i class="bi bi-heart me-2"></i>{{ __('member.family_show_muscle_mass') }}</td>
                                                <td class="small text-end font-semibold text-primary">{{ $current->muscle_mass ?? 'N/A' }}kg</td>
                                                <td class="small text-end text-danger">{{ $previous->muscle_mass ?? 'N/A' }}kg</td>
                                                <td class="text-center">{!! $current->muscle_mass && $previous->muscle_mass ? getChangeIcon($current->muscle_mass, $previous->muscle_mass) : '-' !!}</td>
                                            </tr>
                                            <tr data-metric="bone_mass">
                                                <td class="small"><i class="bi bi-capsule me-2"></i>{{ __('member.family_show_bone_mass') }}</td>
                                                <td class="small text-end font-semibold text-primary">{{ $current->bone_mass ?? 'N/A' }}kg</td>
                                                <td class="small text-end text-danger">{{ $previous->bone_mass ?? 'N/A' }}kg</td>
                                                <td class="text-center">{!! $current->bone_mass && $previous->bone_mass ? getChangeIcon($current->bone_mass, $previous->bone_mass) : '-' !!}</td>
                                            </tr>
                                            <tr data-metric="visceral_fat">
                                                <td class="small"><i class="bi bi-activity me-2"></i>{{ __('member.family_show_visceral_fat') }}</td>
                                                <td class="small text-end font-semibold text-primary">{{ $current->visceral_fat ?? 'N/A' }}</td>
                                                <td class="small text-end text-danger">{{ $previous->visceral_fat ?? 'N/A' }}</td>
                                                <td class="text-center">{!! $current->visceral_fat && $previous->visceral_fat ? getChangeIcon($current->visceral_fat, $previous->visceral_fat) : '-' !!}</td>
                                            </tr>
                                            <tr data-metric="bmr">
                                                <td class="small"><i class="bi bi-lightning me-2"></i>{{ __('member.family_show_bmr') }}</td>
                                                <td class="small text-end font-semibold text-primary">{{ $current->bmr ?? 'N/A' }}cal</td>
                                                <td class="small text-end text-danger">{{ $previous->bmr ?? 'N/A' }}cal</td>
                                                <td class="text-center">{!! $current->bmr && $previous->bmr ? getChangeIcon($current->bmr, $previous->bmr) : '-' !!}</td>
                                            </tr>
                                            <tr data-metric="protein">
                                                <td class="small"><i class="bi bi-heart-pulse me-2"></i>{{ __('member.family_show_protein') }}</td>
                                                <td class="small text-end font-semibold text-primary">{{ $current->protein_percentage ?? 'N/A' }}%</td>
                                                <td class="small text-end text-danger">{{ $previous->protein_percentage ?? 'N/A' }}%</td>
                                                <td class="text-center">{!! $current->protein_percentage && $previous->protein_percentage ? getChangeIcon($current->protein_percentage, $previous->protein_percentage) : '-' !!}</td>
                                            </tr>
                                            <tr data-metric="body_age">
                                                <td class="small"><i class="bi bi-calendar-heart me-2"></i>{{ __('member.family_show_body_age') }}</td>
                                                <td class="small text-end font-semibold text-primary">{{ $current->body_age ?? 'N/A' }}yrs</td>
                                                <td class="small text-end text-danger">{{ $previous->body_age ?? 'N/A' }}yrs</td>
                                                <td class="text-center">{!! $current->body_age && $previous->body_age ? getChangeIcon($current->body_age, $previous->body_age) : '-' !!}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="text-center py-4">
                                    <i class="bi bi-bar-chart-line text-muted" style="font-size: 3rem;"></i>
                                    <p class="text-muted mt-3">{{ __('member.family_show_need_two_records') }}</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Health Tracking History -->
            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    <h5 class="font-bold mb-4"><i class="bi bi-heart-pulse me-2"></i>{{ __('member.family_show_health_tracking') }}</h5>

                    @if($healthRecords->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                <tr>
                                    <th class="text-muted small font-semibold">{{ __('member.family_show_th_date') }}</th>
                                    <th class="text-muted small font-semibold text-center"><i class="bi bi-rulers me-1"></i>{{ __('member.family_show_height_cm') }}</th>
                                    <th class="text-muted small font-semibold text-center"><i class="bi bi-speedometer2 me-1"></i>{{ __('member.family_show_weight_kg') }}</th>
                                    <th class="text-muted small font-semibold text-center"><i class="bi bi-activity me-1"></i>{{ __('member.family_show_body_fat_pct') }}</th>
                                    <th class="text-muted small font-semibold text-center"><i class="bi bi-calculator me-1"></i>{{ __('member.family_show_bmi') }}</th>
                                    <th class="text-muted small font-semibold text-center"><i class="bi bi-droplet me-1"></i>{{ __('member.family_show_body_water_pct') }}</th>
                                    <th class="text-muted small font-semibold text-center"><i class="bi bi-heart me-1"></i>{{ __('member.family_show_muscle_mass_kg') }}</th>
                                    <th class="text-muted small font-semibold text-center"><i class="bi bi-capsule me-1"></i>{{ __('member.family_show_bone_mass_kg') }}</th>
                                    <th class="text-muted small font-semibold text-center"><i class="bi bi-activity me-1"></i>{{ __('member.family_show_visceral_fat') }}</th>
                                    <th class="text-muted small font-semibold text-center"><i class="bi bi-lightning me-1"></i>{{ __('member.family_show_bmr') }}</th>
                                    <th class="text-muted small font-semibold text-center"><i class="bi bi-heart-pulse me-1"></i>{{ __('member.family_show_protein_pct') }}</th>
                                    <th class="text-muted small font-semibold text-center"><i class="bi bi-calendar-heart me-1"></i>{{ __('member.family_show_body_age') }}</th>
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
                            <i class="bi bi-clipboard-data text-muted" style="font-size: 3rem;"></i>
                            <p class="text-muted mt-3">{{ __('member.family_show_no_health_records') }}</p>
                            <small class="text-muted">{{ __('member.family_show_health_tracking_hint') }}</small>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Goals Tab -->
        <div class="tab-pane fade" id="goals" role="tabpanel">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    <!-- Section Title & Subtitle -->
                    <div class="flex justify-between items-center mb-4">
                        <div>
                            <h5 class="font-bold mb-1"><i class="bi bi-bullseye me-2"></i>{{ __('member.family_show_goal_tracking') }}</h5>
                            <p class="text-muted small mb-0">{{ __('member.family_show_goals_sub') }}</p>
                        </div>
                    </div>

                    <!-- Summary Cards -->
                    <div class="row g-3 mb-4">
                        <!-- Active Goals -->
                        <div class="col-md-4">
                            <div class="card shadow-sm border-0 text-center h-full goal-filter-card" style="background: linear-gradient(135deg, #8b5cf6 0%, #a855f7 100%); min-height: 120px; cursor: pointer;" data-filter="active">
                                <div class="card-body p-3 flex flex-col justify-center items-center h-full">
                                    <i class="bi bi-bullseye text-white mb-2" style="font-size: 2rem;"></i>
                                    <h4 class="text-white font-bold mb-1" id="activeGoalsCount">{{ $activeGoalsCount }}</h4>
                                    <small class="text-white/50">{{ __('member.family_show_active_goals') }}</small>
                                </div>
                            </div>
                        </div>
                        <!-- Completed Goals -->
                        <div class="col-md-4">
                            <div class="card shadow-sm border-0 text-center h-full goal-filter-card" style="background: linear-gradient(135deg, #10b981 0%, #34d399 100%); min-height: 120px; cursor: pointer;" data-filter="completed">
                                <div class="card-body p-3 flex flex-col justify-center items-center h-full">
                                    <i class="bi bi-check-circle-fill text-white mb-2" style="font-size: 2rem;"></i>
                                    <h4 class="text-white font-bold mb-1">{{ $completedGoalsCount }}</h4>
                                    <small class="text-white/50">{{ __('member.family_show_completed_goals') }}</small>
                                </div>
                            </div>
                        </div>
                        <!-- Success Rate -->
                        <div class="col-md-4">
                            <div class="card shadow-sm border-0 text-center h-full" style="background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%); min-height: 120px;">
                                <div class="card-body p-3 flex flex-col justify-center items-center h-full">
                                    <i class="bi bi-graph-up-arrow text-white mb-2" style="font-size: 2rem;"></i>
                                    <h4 class="text-white font-bold mb-1">{{ $successRate }}%</h4>
                                    <small class="text-white/50">{{ __('member.family_show_success_rate') }}</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Current Goals List -->
                        <div class="row g-4 {{ $goals->count() ? '' : 'hidden' }}" id="goalsGrid">
                            @foreach($goals as $goal)
                        <div class="col-lg-6">
                            <div class="card shadow-sm border-0 h-full relative" id="goal-{{ $goal->id }}">
                                <!-- Edit Button (only for active goals and authorized users) -->
                                @if($goal->status == 'active' && ($relationship->relationship_type == 'self' || Auth::id() == $relationship->guardian_user_id))
                                    <button class="btn btn-sm btn-outline-primary rounded-circle absolute top-0 end-0 mt-2 me-2 edit-goal-btn" style="width: 32px; height: 32px; padding: 0;" data-goal-id="{{ $goal->id }}" title="{{ __('member.family_show_edit_goal') }}">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                @endif

                                <div class="card-body p-4">
                                    <!-- Title & Icon -->
                                    <div class="flex items-center mb-3">
                                        <div class="rounded-circle flex items-center justify-center me-3" style="width: 48px; height: 48px; background-color: #8b5cf6;">
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
                                                <p class="text-muted small mb-0">{{ $goal->description }}</p>
                                            @endif
                                        </div>
                                    </div>

                                            <!-- Progress Indicator -->
                                            <div class="mb-3">
                                                <div class="flex justify-between items-center mb-2">
                                                    <small class="text-muted" data-goal-progress-text>{{ __('member.family_show_progress') }} {{ number_format($goal->current_progress_value, 1) }} / {{ number_format($goal->target_value, 1) }} {{ $goal->unit }}</small>
                                                    <small class="font-semibold" data-goal-progress-pct>{{ number_format($goal->progress_percentage, 1) }}%</small>
                                                </div>
                                                <div class="progress" style="height: 8px;">
                                                    <div class="progress-bar" role="progressbar" data-goal-progress-bar style="width: {{ $goal->progress_percentage }}%; background: linear-gradient(90deg, #8b5cf6 0%, #10b981 100%);" aria-valuenow="{{ $goal->progress_percentage }}" aria-valuemin="0" aria-valuemax="100"></div>
                                                </div>
                                            </div>

                                            <!-- Dates & Status -->
                                            <div class="row g-2 mb-3">
                                                <div class="col-6">
                                                    <small class="text-muted block">{{ __('member.family_show_started') }}</small>
                                                    <small class="font-semibold">{{ $goal->start_date->format('M d, Y') }}</small>
                                                </div>
                                                <div class="col-6 text-end">
                                                    <small class="text-muted block">{{ __('member.family_show_target') }}</small>
                                                    <small class="font-semibold">{{ $goal->target_date->format('M d, Y') }}</small>
                                                </div>
                                            </div>

                                            <!-- Status Badges -->
                                            <div class="flex gap-2 flex-wrap">
                                                <span class="badge {{ $goal->status == 'active' ? 'bg-primary' : 'bg-success' }} small" data-goal-status-badge>
                                                    {{ ucfirst($goal->status) }}
                                                </span>
                                                <span class="badge {{ $goal->priority_level == 'high' ? 'bg-danger' : ($goal->priority_level == 'medium' ? 'bg-warning text-dark' : 'bg-secondary') }} small">
                                                    {{ ucfirst($goal->priority_level) }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <div class="text-center py-4 {{ $goals->count() ? 'hidden' : '' }}" id="goalsEmpty">
                            <i class="bi bi-bullseye text-muted" style="font-size: 3rem;"></i>
                            <h5 class="text-muted mt-3 mb-2">{{ __('member.family_show_no_goals') }}</h5>
                            <p class="text-muted mb-0">{{ __('member.family_show_no_goals_hint') }}</p>
                        </div>
                </div>
            </div>
        </div>

        <!-- Affiliations Tab -->
        <div class="tab-pane fade" id="affiliations" role="tabpanel">
            @include('family.partials.affiliations-enhanced')
        </div>

        <!-- Tournaments Tab -->
        <div class="tab-pane fade" id="tournaments" role="tabpanel">
            <!-- Tournament & Event Participation Card -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body p-4">
                    <!-- Section Title & Subtitle -->
                    <div class="flex justify-between items-center mb-4">
                        <div>
                            <h5 class="font-bold mb-1"><i class="bi bi-trophy-fill text-warning me-2"></i>{{ __('member.family_show_tournament_participation_title') }}</h5>
                            <p class="text-muted small mb-0">{{ __('member.family_show_tournament_participation_sub') }}</p>
                        </div>
                        <!-- Filter Section -->
                        <div class="flex items-center">
                            <label for="sportFilter" class="form-label me-2 mb-0 font-semibold">{{ __('member.family_show_filter_by_sport') }}</label>
                            <select class="form-select form-select-sm" id="sportFilter" style="width: 150px;">
                                <option value="all">{{ __('member.family_show_all_sports') }}</option>
                                @foreach($sports as $sport)
                                    <option value="{{ $sport }}">{{ $sport }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <!-- Award Summary Cards -->
                    <div class="row g-3" id="awardCards">
                        <div class="col-md-3">
                            <div class="card shadow-sm border-0 text-center" style="background: linear-gradient(135deg, #FFD700 0%, #FFA500 100%);">
                                <div class="card-body p-3">
                                    <i class="bi bi-trophy-fill text-white mb-2" style="font-size: 2rem;"></i>
                                    <h4 class="text-white font-bold mb-1" id="specialCount">{{ $awardCounts['special'] }}</h4>
                                    <small class="text-white/50">{{ __('member.family_show_special_award') }}</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card shadow-sm border-0 text-center" style="background: linear-gradient(135deg, #FFD700 0%, #FFA500 100%);">
                                <div class="card-body p-3">
                                    <i class="bi bi-award-fill text-white mb-2" style="font-size: 2rem;"></i>
                                    <h4 class="text-white font-bold mb-1" id="firstCount">{{ $awardCounts['1st'] }}</h4>
                                    <small class="text-white/50">{{ __('member.family_show_first_place') }}</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card shadow-sm border-0 text-center" style="background: linear-gradient(135deg, #C0C0C0 0%, #A8A8A8 100%);">
                                <div class="card-body p-3">
                                    <i class="bi bi-award-fill text-white mb-2" style="font-size: 2rem;"></i>
                                    <h4 class="text-white font-bold mb-1" id="secondCount">{{ $awardCounts['2nd'] }}</h4>
                                    <small class="text-white/50">{{ __('member.family_show_second_place') }}</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card shadow-sm border-0 text-center" style="background: linear-gradient(135deg, #CD7F32 0%, #A0522D 100%);">
                                <div class="card-body p-3">
                                    <i class="bi bi-award-fill text-white mb-2" style="font-size: 2rem;"></i>
                                    <h4 class="text-white font-bold mb-1" id="thirdCount">{{ $awardCounts['3rd'] }}</h4>
                                    <small class="text-white/50">{{ __('member.family_show_third_place') }}</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tournament & Championships History Card -->
            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    <h6 class="font-bold mb-3"><i class="bi bi-list-ul me-2"></i>{{ __('member.family_show_tournament_history_title') }}</h6>

                    <div class="table-responsive" id="tournamentsTableWrapper" style="{{ $tournamentEvents->count() > 0 ? '' : 'display:none;' }}">
                            <table class="table table-hover align-middle" id="tournamentsTable">
                                <thead class="table-light">
                                    <tr>
                                        <th class="text-muted small font-semibold">{{ __('member.family_show_th_tournament_details') }}</th>
                                        <th class="text-muted small font-semibold">{{ __('member.family_show_th_club_affiliation') }}</th>
                                        <th class="text-muted small font-semibold">{{ __('member.family_show_th_performance_result') }}</th>
                                        <th class="text-muted small font-semibold">{{ __('member.family_show_th_notes_media') }}</th>
                                    </tr>
                                </thead>
                                <tbody id="tournamentsTableBody">
                                    @foreach($tournamentEvents as $event)
                                        <tr data-sport="{{ $event->sport }}">
                                            <td>
                                                <div class="font-bold">{{ $event->title }}</div>
                                                <div class="flex gap-2 mt-1 flex-wrap">
                                                    <span class="badge bg-{{ $event->type == 'championship' ? 'primary' : 'secondary' }} small">{{ ucfirst($event->type) }}</span>
                                                    <span class="badge bg-info small">{{ $event->sport }}</span>
                                                </div>
                                                <div class="text-muted small mt-1">
                                                    <i class="bi bi-calendar-event me-1"></i>{{ $event->date->format('M j, Y') }}
                                                    @if($event->time)
                                                        <i class="bi bi-clock me-1 ms-2"></i>{{ $event->time->format('H:i') }}
                                                    @endif
                                                    @if($event->location)
                                                        <i class="bi bi-geo-alt me-1 ms-2"></i>{{ $event->location }}
                                                    @endif
                                                    @if($event->participants_count)
                                                        <i class="bi bi-people me-1 ms-2"></i>{{ $event->participants_count }} {{ __('member.family_show_participants') }}
                                                    @endif
                                                </div>
                                            </td>
                                            <td>
                                                @if($event->clubAffiliation)
                                                    <div>
                                                        <div class="small font-semibold">{{ $event->clubAffiliation->club_name }}</div>
                                                        <div class="text-muted small">{{ $event->clubAffiliation->location }}</div>
                                                    </div>
                                                @else
                                                    <span class="text-muted small">{{ __('member.family_show_individual') }}</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($event->performanceResults->count() > 0)
                                                    @foreach($event->performanceResults as $result)
                                                        <div class="flex items-center gap-2 mb-1">
                                                            @if($result->medal_type == '1st')
                                                                <i class="bi bi-award-fill text-warning"></i>
                                                                <span class="badge bg-warning text-dark small">{{ __('member.family_show_first_place') }}</span>
                                                            @elseif($result->medal_type == '2nd')
                                                                <i class="bi bi-award-fill text-secondary"></i>
                                                                <span class="badge bg-secondary small">{{ __('member.family_show_second_place') }}</span>
                                                            @elseif($result->medal_type == '3rd')
                                                                <i class="bi bi-award-fill" style="color: #CD7F32;"></i>
                                                                <span class="badge" style="background-color: #CD7F32; color: white;" small>{{ __('member.family_show_third_place') }}</span>
                                                            @elseif($result->medal_type == 'special')
                                                                <i class="bi bi-trophy-fill text-warning"></i>
                                                                <span class="badge bg-warning text-dark small">{{ __('member.family_show_special_award') }}</span>
                                                            @endif
                                                            @if($result->points)
                                                                <small class="text-muted">{{ $result->points }} {{ __('member.family_show_pts') }}</small>
                                                            @endif
                                                        </div>
                                                        @if($result->description)
                                                            <small class="text-muted">{{ $result->description }}</small>
                                                        @endif
                                                    @endforeach
                                                @else
                                                    <span class="text-muted small">{{ __('member.family_show_no_results') }}</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($event->notesMedia->count() > 0)
                                                    @foreach($event->notesMedia as $note)
                                                        @if($note->note_text)
                                                            <p class="mb-1 small">{{ $note->note_text }}</p>
                                                        @endif
                                                        @if($note->media_link)
                                                            <a href="{{ $note->media_link }}" target="_blank" class="btn btn-sm btn-outline-primary small">
                                                                <i class="bi bi-image me-1"></i>{{ __('member.family_show_view_media') }}
                                                            </a>
                                                        @endif
                                                    @endforeach
                                                @else
                                                    <span class="text-muted small">{{ __('member.family_show_no_notes') }}</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="text-center py-5" id="tournamentsEmptyState" style="{{ $tournamentEvents->count() > 0 ? 'display:none;' : '' }}">
                            <i class="bi bi-trophy text-muted" style="font-size: 3rem;"></i>
                            <p class="text-muted mt-3">{{ __('member.family_show_no_tournaments') }}</p>
                            <small class="text-muted">{{ __('member.family_show_tournaments_hint') }}</small>
                        </div>
                </div>
            </div>
        </div>

        <!-- Events Tab -->
        <div class="tab-pane fade" id="events" role="tabpanel">
            @php
                $memberEventLog = $relationship->dependent->memberEvents()->orderBy('event_date', 'desc')->get();
            @endphp
            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    <div class="flex justify-between items-center mb-3">
                        <h5 class="font-bold mb-0"><i class="bi bi-journal-text me-2"></i>{{ __('member.family_show_personal_event_log') }}</h5>
                        @if($relationship->relationship_type == 'self' || Auth::id() == $relationship->guardian_user_id || $relationship->relationship_type == 'admin_view')
                            <button type="button" class="btn btn-primary btn-sm rounded-pill" onclick="window.dispatchEvent(new CustomEvent('open-event-add-modal'))">
                                <i class="bi bi-plus-lg me-1"></i>{{ __('member.family_show_add_event') }}
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
                            <div class="flex-shrink-0"><span class="inline-flex items-center gap-1 text-xs font-semibold px-2 py-1 rounded-full bg-accent text-primary">{{ $mev->result }}</span></div>
                            @endif
                        </div>
                        @endforeach
                    </div>
                    <div class="text-center py-10 {{ $memberEventLog->isEmpty() ? '' : 'hidden' }}" id="eventLogEmpty">
                        <i class="bi bi-journal-x text-gray-300" style="font-size:2.5rem;"></i>
                        <p class="text-gray-400 mt-3">{{ __('member.family_show_no_events') }}</p>
                    </div>
                </div>
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
                    <h5 class="text-lg font-medium">{{ __('member.family_show_set_a_goal') }}</h5>
                    <button type="button" @click="open = false" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
                </div>
                <form id="goalAddForm" method="POST" action="{{ route('member.store-goal', $relationship->dependent->id) }}">
                    @csrf
                    <div class="p-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div class="col-span-full">
                                <label for="goal_add_title" class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.family_show_goal_title') }} <span class="text-red-600">*</span></label>
                                <input type="text" id="goal_add_title" name="title" maxlength="150" required class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" placeholder="{{ __('member.family_show_goal_title_ph') }}">
                            </div>
                            <div class="col-span-full">
                                <label for="goal_add_description" class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.family_show_description') }}</label>
                                <textarea id="goal_add_description" name="description" rows="2" maxlength="1000" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" placeholder="{{ __('member.family_show_goal_desc_ph') }}"></textarea>
                            </div>
                            <div>
                                <label for="goal_add_target" class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.family_show_target_value') }} <span class="text-red-600">*</span></label>
                                <input type="number" step="0.1" min="0" id="goal_add_target" name="target_value" required class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" placeholder="80">
                            </div>
                            <div>
                                <label for="goal_add_unit" class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.family_show_unit') }} <span class="text-red-600">*</span></label>
                                <input type="text" id="goal_add_unit" name="unit" maxlength="30" required class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" placeholder="{{ __('member.family_show_unit_ph') }}">
                            </div>
                            <div>
                                <label for="goal_add_current" class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.family_show_current_progress') }}</label>
                                <input type="number" step="0.1" min="0" id="goal_add_current" name="current_progress_value" value="0" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary">
                            </div>
                            <div>
                                <label for="goal_add_target_date" class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.family_show_target_date') }} <span class="text-red-600">*</span></label>
                                <input type="date" id="goal_add_target_date" name="target_date" required min="{{ \Carbon\Carbon::now()->format('Y-m-d') }}" value="{{ \Carbon\Carbon::now()->addMonth()->format('Y-m-d') }}" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary">
                            </div>
                            <div>
                                <label for="goal_add_priority" class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.family_show_priority') }}</label>
                                <select id="goal_add_priority" name="priority_level" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary">
                                    <option value="low">{{ __('member.family_show_low') }}</option>
                                    <option value="medium" selected>{{ __('member.family_show_medium') }}</option>
                                    <option value="high">{{ __('member.family_show_high') }}</option>
                                </select>
                            </div>
                            <div>
                                <label for="goal_add_icon" class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.family_show_icon') }}</label>
                                <select id="goal_add_icon" name="icon_type" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary">
                                    <option value="bi-bullseye">🎯 {{ __('member.family_show_icon_target') }}</option>
                                    <option value="dumbbell">🏋️ {{ __('member.family_show_icon_strength') }}</option>
                                    <option value="clock">⏱️ {{ __('member.family_show_icon_endurance') }}</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 p-4 border-t border-gray-200 bg-gray-50">
                        <button type="button" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-md text-sm font-medium hover:bg-gray-300 transition-colors" @click="open = false">{{ __('shared.cancel') }}</button>
                        <button type="submit" id="goalAddSubmit" class="bg-primary text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-primary/90 transition-colors">{{ __('member.family_show_create_goal') }}</button>
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
                    <h5 class="text-lg font-medium">{{ __('member.family_show_add_attendance_record') }}</h5>
                    <button type="button" @click="open = false" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
                </div>
                <form id="attendanceAddForm" method="POST" action="{{ route('member.store-attendance', $relationship->dependent->id) }}">
                    @csrf
                    <div class="p-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label for="att_add_datetime" class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.family_show_date_time') }} <span class="text-red-600">*</span></label>
                                <input type="datetime-local" id="att_add_datetime" name="session_datetime" required value="{{ \Carbon\Carbon::now()->format('Y-m-d\TH:i') }}" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary">
                            </div>
                            <div>
                                <label for="att_add_status" class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.family_show_status') }} <span class="text-red-600">*</span></label>
                                <select id="att_add_status" name="status" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary">
                                    <option value="completed" selected>{{ __('member.family_show_completed') }}</option>
                                    <option value="no_show">{{ __('member.family_show_no_show') }}</option>
                                </select>
                            </div>
                            <div>
                                <label for="att_add_type" class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.family_show_th_session_type') }} <span class="text-red-600">*</span></label>
                                <input type="text" id="att_add_type" name="session_type" maxlength="100" required class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" placeholder="{{ __('member.family_show_session_type_ph') }}">
                            </div>
                            <div>
                                <label for="att_add_trainer" class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.family_show_th_trainer_name') }}</label>
                                <input type="text" id="att_add_trainer" name="trainer_name" maxlength="100" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" placeholder="{{ __('member.family_show_optional') }}">
                            </div>
                            <div class="col-span-full">
                                <label for="att_add_notes" class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.family_show_th_notes') }}</label>
                                <textarea id="att_add_notes" name="notes" rows="2" maxlength="1000" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" placeholder="{{ __('member.family_show_optional') }}"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 p-4 border-t border-gray-200 bg-gray-50">
                        <button type="button" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-md text-sm font-medium hover:bg-gray-300 transition-colors" @click="open = false">{{ __('shared.cancel') }}</button>
                        <button type="submit" id="attendanceAddSubmit" class="bg-primary text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-primary/90 transition-colors">{{ __('member.family_show_add_record') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

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
                    <h5 class="text-lg font-medium">{{ __('member.family_show_add_event_participation') }}</h5>
                    <button type="button" @click="open = false" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
                </div>
                <form id="eventAddForm" method="POST" action="{{ route('member.store-event', $relationship->dependent->id) }}">
                    @csrf
                    <div class="p-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div class="col-span-full">
                                <label for="ev_add_title" class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.family_show_event_title') }} <span class="text-red-600">*</span></label>
                                <input type="text" id="ev_add_title" name="title" maxlength="150" required class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" placeholder="{{ __('member.family_show_event_title_ph') }}">
                            </div>
                            <div>
                                <label for="ev_add_date" class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.family_show_th_date') }} <span class="text-red-600">*</span></label>
                                <input type="date" id="ev_add_date" name="event_date" required value="{{ \Carbon\Carbon::now()->format('Y-m-d') }}" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary">
                            </div>
                            <div>
                                <label for="ev_add_location" class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.family_show_location') }}</label>
                                <input type="text" id="ev_add_location" name="location" maxlength="150" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" placeholder="{{ __('member.family_show_optional') }}">
                            </div>
                            <div>
                                <label for="ev_add_role" class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.family_show_role') }}</label>
                                <input type="text" id="ev_add_role" name="role" maxlength="80" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" placeholder="{{ __('member.family_show_role_ph') }}">
                            </div>
                            <div>
                                <label for="ev_add_result" class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.family_show_result') }}</label>
                                <input type="text" id="ev_add_result" name="result" maxlength="150" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" placeholder="{{ __('member.family_show_result_ph') }}">
                            </div>
                            <div class="col-span-full">
                                <label for="ev_add_notes" class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.family_show_th_notes') }}</label>
                                <textarea id="ev_add_notes" name="notes" rows="2" maxlength="1000" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" placeholder="{{ __('member.family_show_optional') }}"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 p-4 border-t border-gray-200 bg-gray-50">
                        <button type="button" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-md text-sm font-medium hover:bg-gray-300 transition-colors" @click="open = false">{{ __('shared.cancel') }}</button>
                        <button type="submit" id="eventAddSubmit" class="bg-primary text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-primary/90 transition-colors">{{ __('member.family_show_add_event') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Goal Edit Modal -->
<div class="modal fade" id="goalEditModal" tabindex="-1" aria-labelledby="goalEditModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="goalEditModalLabel">{{ __('member.family_show_edit_goal_progress') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('member.family_show_close') }}"></button>
            </div>
            <form id="goalEditForm" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="flex items-center mb-3">
                                <div class="rounded-circle flex items-center justify-center me-3" id="goalIconDisplay" style="width: 48px; height: 48px; background-color: #8b5cf6;">
                                    <i class="bi bi-bullseye text-white"></i>
                                </div>
                                <div>
                                    <h6 class="font-bold mb-1" id="goalTitleDisplay">{{ __('member.family_show_goal_title') }}</h6>
                                    <p class="text-muted small mb-0" id="goalDescriptionDisplay">{{ __('member.family_show_goal_desc_default') }}</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="current_progress_value" class="form-label">{{ __('member.family_show_current_progress') }} <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" step="0.1" class="form-control" id="current_progress_value" name="current_progress_value" required>
                                <span class="input-group-text" id="goalUnitDisplay">{{ __('member.family_show_lbs') }}</span>
                            </div>
                            <div class="form-text">{{ __('member.family_show_target') }} <span id="goalTargetDisplay">170.0 {{ __('member.family_show_lbs') }}</span></div>
                        </div>
                        <div class="col-md-6">
                            <label for="goal_status" class="form-label">{{ __('member.family_show_status') }}</label>
                            <select class="form-select" id="goal_status" name="status">
                                <option value="active">{{ __('member.family_show_active') }}</option>
                                <option value="completed">{{ __('member.family_show_completed') }}</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar" role="progressbar" id="progressPreview" style="width: 0%; background: linear-gradient(90deg, #8b5cf6 0%, #10b981 100%);"></div>
                            </div>
                            <small class="text-muted mt-1 block" id="progressTextPreview">{{ __('member.family_show_progress') }} 0.0 / 170.0 {{ __('member.family_show_lbs') }} (0.0%)</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('shared.cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('member.family_show_update_goal') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Health Update Modal -->
<div class="modal fade" id="healthUpdateModal" tabindex="-1" aria-labelledby="healthUpdateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="healthUpdateModalLabel">{{ __('member.family_show_add_health_update') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('member.family_show_close') }}"></button>
            </div>
            <form id="healthUpdateForm" method="POST" action="{{ $relationship->relationship_type === 'admin_view' ? route('admin.platform.members.store-health', $relationship->dependent->id) : route('family.store-health', $relationship->dependent->id) }}">
                @csrf
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="recorded_at" class="form-label">{{ __('member.family_show_th_date') }} <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="recorded_at" name="recorded_at" value="{{ \Carbon\Carbon::now()->format('Y-m-d') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label for="height" class="form-label">{{ __('member.family_show_height_cm') }}</label>
                            <input type="number" step="0.1" class="form-control" id="height" name="height">
                        </div>
                        <div class="col-md-6">
                            <label for="weight" class="form-label">{{ __('member.family_show_weight_kg') }}</label>
                            <input type="number" step="0.1" class="form-control" id="weight" name="weight">
                        </div>
                        <div class="col-md-6">
                            <label for="body_fat_percentage" class="form-label">{{ __('member.family_show_body_fat_paren') }}</label>
                            <input type="number" step="0.1" class="form-control" id="body_fat_percentage" name="body_fat_percentage">
                        </div>
                        <div class="col-md-6">
                            <label for="bmi" class="form-label">{{ __('member.family_show_bmi') }}</label>
                            <input type="number" step="0.1" class="form-control" id="bmi" name="bmi">
                        </div>
                        <div class="col-md-6">
                            <label for="body_water_percentage" class="form-label">{{ __('member.family_show_body_water_paren') }}</label>
                            <input type="number" step="0.1" class="form-control" id="body_water_percentage" name="body_water_percentage">
                        </div>
                        <div class="col-md-6">
                            <label for="muscle_mass" class="form-label">{{ __('member.family_show_muscle_mass_kg') }}</label>
                            <input type="number" step="0.1" class="form-control" id="muscle_mass" name="muscle_mass">
                        </div>
                        <div class="col-md-6">
                            <label for="bone_mass" class="form-label">{{ __('member.family_show_bone_mass_kg') }}</label>
                            <input type="number" step="0.1" class="form-control" id="bone_mass" name="bone_mass">
                        </div>
                        <div class="col-md-6">
                            <label for="visceral_fat" class="form-label">{{ __('member.family_show_visceral_fat') }}</label>
                            <input type="number" class="form-control" id="visceral_fat" name="visceral_fat">
                        </div>
                        <div class="col-md-6">
                            <label for="bmr" class="form-label">{{ __('member.family_show_bmr_cal') }}</label>
                            <input type="number" class="form-control" id="bmr" name="bmr">
                        </div>
                        <div class="col-md-6">
                            <label for="protein_percentage" class="form-label">{{ __('member.family_show_protein_paren') }}</label>
                            <input type="number" step="0.1" class="form-control" id="protein_percentage" name="protein_percentage">
                        </div>
                        <div class="col-md-6">
                            <label for="body_age" class="form-label">{{ __('member.family_show_body_age_years') }}</label>
                            <input type="number" class="form-control" id="body_age" name="body_age">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('shared.cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('member.family_show_save_health_update') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Tournament Participation Modal -->
<div class="modal fade" id="tournamentParticipationModal" tabindex="-1" aria-labelledby="tournamentParticipationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="tournamentParticipationModalLabel">{{ __('member.family_show_add_tournament_participation') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('member.family_show_close') }}"></button>
            </div>
            <form id="tournamentParticipationForm" method="POST" action="{{ $relationship->relationship_type === 'admin_view' ? route('admin.platform.members.store-tournament', $relationship->dependent->id) : route('family.store-tournament', $relationship->dependent->id) }}">
                @csrf
                <div class="modal-body">
                    <div class="row g-3">
                        <!-- Tournament Details -->
                        <div class="col-md-6">
                            <label for="tournament_title" class="form-label">{{ __('member.family_show_tournament_title') }} <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="tournament_title" name="title" required>
                        </div>
                        <div class="col-md-6">
                            <label for="tournament_type" class="form-label">{{ __('member.family_show_type') }} <span class="text-danger">*</span></label>
                            <select class="form-select" id="tournament_type" name="type" required>
                                <option value="">{{ __('member.family_show_select_type') }}</option>
                                <option value="championship">{{ __('member.family_show_type_championship') }}</option>
                                <option value="tournament">{{ __('member.family_show_type_tournament') }}</option>
                                <option value="competition">{{ __('member.family_show_type_competition') }}</option>
                                <option value="exhibition">{{ __('member.family_show_type_exhibition') }}</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="tournament_sport" class="form-label">{{ __('member.family_show_sport') }} <span class="text-danger">*</span></label>
                            <select class="form-select" id="tournament_sport" name="sport" required>
                                <option value="">{{ __('member.family_show_select_sport') }}</option>
                                <option value="Boxing">{{ __('member.family_show_sport_boxing') }}</option>
                                <option value="Taekwondo">{{ __('member.family_show_sport_taekwondo') }}</option>
                                <option value="Karate">{{ __('member.family_show_sport_karate') }}</option>
                                <option value="Martial Arts">{{ __('member.family_show_sport_martial_arts') }}</option>
                                <option value="Fitness">{{ __('member.family_show_sport_fitness') }}</option>
                                <option value="Weightlifting">{{ __('member.family_show_sport_weightlifting') }}</option>
                                <option value="Other">{{ __('member.family_show_sport_other') }}</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <x-birthdate-dropdown
                                name="date"
                                id="tournament_date"
                                :label="__('member.family_show_th_date')"
                                :required="true"
                                :min-year="2000"
                                :max-year="date('Y')"
                                :error="$errors->first('date')" />
                        </div>
                        <div class="col-md-6">
                            <label for="tournament_time" class="form-label">{{ __('member.family_show_time_label') }}</label>
                            <input type="time" class="form-control" id="tournament_time" name="time">
                        </div>
                        <div class="col-md-6">
                            <label for="tournament_location" class="form-label">{{ __('member.family_show_location') }}</label>
                            <input type="text" class="form-control" id="tournament_location" name="location" placeholder="{{ __('member.family_show_venue_ph') }}">
                        </div>
                        <div class="col-md-6">
                            <label for="participants_count" class="form-label">{{ __('member.family_show_num_participants') }}</label>
                            <input type="number" class="form-control" id="participants_count" name="participants_count" min="1">
                        </div>
                        <div class="col-md-6">
                            <label for="club_affiliation_id" class="form-label">{{ __('member.family_show_th_club_affiliation') }}</label>
                            <select class="form-select" id="club_affiliation_id" name="club_affiliation_id">
                                <option value="">{{ __('member.family_show_select_club') }}</option>
                                @foreach($clubAffiliations ?? [] as $affiliation)
                                    <option value="{{ $affiliation->id }}">{{ $affiliation->club_name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Performance Results Section -->
                        <div class="col-12">
                            <hr>
                            <h6 class="mb-3">{{ __('member.family_show_performance_results') }}</h6>
                            <div id="performanceResultsContainer">
                                <div class="performance-result-item mb-3 p-3 border rounded">
                                    <div class="row g-2">
                                        <div class="col-md-4">
                                            <label class="form-label">{{ __('member.family_show_medal_type') }}</label>
                                            <select class="form-select medal-type" name="performance_results[0][medal_type]">
                                                <option value="">{{ __('member.family_show_select_medal') }}</option>
                                                <option value="special">{{ __('member.family_show_special_award') }}</option>
                                                <option value="1st">{{ __('member.family_show_first_place') }}</option>
                                                <option value="2nd">{{ __('member.family_show_second_place') }}</option>
                                                <option value="3rd">{{ __('member.family_show_third_place') }}</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">{{ __('member.family_show_points') }}</label>
                                            <input type="number" class="form-control" name="performance_results[0][points]" min="0" step="0.1">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">{{ __('member.family_show_description') }}</label>
                                            <input type="text" class="form-control" name="performance_results[0][description]" placeholder="{{ __('member.family_show_optional_description') }}">
                                        </div>
                                        <div class="col-md-1 flex items-end">
                                            <button type="button" class="btn btn-outline-danger btn-sm remove-result" style="display: none;">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <button type="button" class="btn btn-outline-primary btn-sm" id="addPerformanceResult">
                                <i class="bi bi-plus me-1"></i>{{ __('member.family_show_add_another_result') }}
                            </button>
                        </div>

                        <!-- Notes & Media Section -->
                        <div class="col-12">
                            <hr>
                            <h6 class="mb-3">{{ __('member.family_show_th_notes_media') }}</h6>
                            <div id="notesMediaContainer">
                                <div class="notes-media-item mb-3 p-3 border rounded">
                                    <div class="row g-2">
                                        <div class="col-md-6">
                                            <label class="form-label">{{ __('member.family_show_note_text') }}</label>
                                            <textarea class="form-control" name="notes_media[0][note_text]" rows="2" placeholder="{{ __('member.family_show_note_text_ph') }}"></textarea>
                                        </div>
                                        <div class="col-md-5">
                                            <label class="form-label">{{ __('member.family_show_media_link') }}</label>
                                            <input type="url" class="form-control" name="notes_media[0][media_link]" placeholder="https://example.com/photo.jpg">
                                        </div>
                                        <div class="col-md-1 flex items-end">
                                            <button type="button" class="btn btn-outline-danger btn-sm remove-note" style="display: none;">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <button type="button" class="btn btn-outline-primary btn-sm" id="addNotesMedia">
                                <i class="bi bi-plus me-1"></i>{{ __('member.family_show_add_another_note') }}
                            </button>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('shared.cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('member.family_show_save_tournament') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Styles moved to app.css (Phase 6) --}}

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

                    const country = countries.find(c => c.iso2 === iso3Code || c.iso3 === iso3Code);
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

        // Handle Add Health Update click
        document.querySelector('a[href="#"][data-bs-target="#healthUpdateModal"]').addEventListener('click', function(e) {
            e.preventDefault();
            resetHealthModal();
            const modal = new bootstrap.Modal(document.getElementById('healthUpdateModal'));
            modal.show();
        });

        // Handle Edit Health Record click
        document.addEventListener('click', function(e) {
            if (e.target.closest('.edit-record-btn')) {
                e.preventDefault();
                const recordId = e.target.closest('tr').getAttribute('data-record-id');
                populateHealthModalForEdit(recordId);
                const modal = new bootstrap.Modal(document.getElementById('healthUpdateModal'));
                modal.show();
            }
        });

        // Activate affiliations tab if URL has #affiliations
        if (window.location.hash === '#affiliations') {
            const affiliationsTab = document.querySelector('#affiliations-tab');
            if (affiliationsTab) {
                const tab = new bootstrap.Tab(affiliationsTab);
                tab.show();
            }
        }

        // Activate health tab if URL has #health
        if (window.location.hash === '#health') {
            const healthTab = document.querySelector('#health-tab');
            if (healthTab) {
                const tab = new bootstrap.Tab(healthTab);
                tab.show();
            }
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
            document.getElementById('healthUpdateModalLabel').textContent = '{{ __("member.family_show_add_health_update") }}';
            document.getElementById('healthUpdateForm').action = '{{ route("family.store-health", $relationship->dependent->id) }}';
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
            document.querySelector('#healthUpdateForm button[type="submit"]').textContent = '{{ __("member.family_show_save_health_update") }}';
        }

        // Function to populate modal for editing
        function populateHealthModalForEdit(recordId) {
            const record = healthRecordsData.find(r => r.id == recordId);
            if (!record) return;

            document.getElementById('healthUpdateModalLabel').textContent = '{{ __("member.family_show_edit_health_update") }}';
            document.getElementById('healthUpdateForm').action = '{{ route("family.update-health", ["id" => $relationship->dependent->id, "recordId" => "__RECORD_ID__"]) }}'.replace('__RECORD_ID__', recordId);
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
            document.querySelector('#healthUpdateForm button[type="submit"]').textContent = '{{ __("member.family_show_update_health_update") }}';
        }

        // Handle comparison dropdown changes
        const currentDateSelect = document.getElementById('currentDate');
        const previousDateSelect = document.getElementById('previousDate');

        if (currentDateSelect && previousDateSelect) {
            function updateComparisonTable() {
                const currentId = currentDateSelect.value;
                const previousId = previousDateSelect.value;

                if (!currentId || !previousId) {
                    document.getElementById('timeDifference').innerHTML = '{{ __("member.family_show_select_dates") }}';
                    return;
                }

                const currentRecord = healthRecordsData.find(r => r.id == currentId);
                const previousRecord = healthRecordsData.find(r => r.id == previousId);

                if (!currentRecord || !previousRecord) {
                    document.getElementById('timeDifference').innerHTML = '{{ __("member.family_show_select_dates") }}';
                    return;
                }

                // Update time difference
                const timeDiff = calculateTimeDifference(currentRecord.recorded_at, previousRecord.recorded_at);
                document.getElementById('timeDifference').innerHTML = `<strong>{{ __("member.family_show_time_between_records") }}</strong> ${timeDiff}`;

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

                return parts.length > 0 ? parts.join(' ') : '{{ __("member.family_show_same_day") }}';
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
                        let colorClass = 'text-muted';

                        if (change > 0) {
                            arrow = '↑';
                            colorClass = 'text-danger';
                        } else if (change < 0) {
                            arrow = '↓';
                            colorClass = 'text-success';
                        } else {
                            arrow = '—';
                            colorClass = 'text-muted';
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
            // Switch to tournaments tab
            const tournamentsTab = document.getElementById('tournaments-tab');
            if (tournamentsTab) {
                const tab = new bootstrap.Tab(tournamentsTab);
                tab.show();
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
            const goalCards = goalsContainer.querySelectorAll('.col-lg-6');

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
                const baseTitle = '{{ __("member.family_show_goal_tracking") }}';
                if (filterType === 'all') {
                    titleElement.innerHTML = `<i class="bi bi-bullseye me-2"></i>${baseTitle}`;
                } else {
                    const filterLabel = filterType === 'active' ? '{{ __("member.family_show_active") }}' : '{{ __("member.family_show_completed") }}';
                    titleElement.innerHTML = `<i class="bi bi-bullseye me-2"></i>${baseTitle} - ${filterLabel} {{ __("member.family_show_goals_word") }}`;
                }
            }
        }

        // Goal editing functionality
        const editGoalButtons = document.querySelectorAll('.edit-goal-btn');
        const goalEditModal = new bootstrap.Modal(document.getElementById('goalEditModal'));
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
                    goalEditModal.show();
                });
            });
        }

        function populateGoalEditModal(goalId) {
            const goal = goalsData.find(g => g.id == goalId);
            if (!goal) return;

            currentGoalId = goalId;

            // Update form action
            goalEditForm.action = `/family/goal/${goalId}`;

            // Populate modal fields
            document.getElementById('goalTitleDisplay').textContent = goal.title;
            document.getElementById('goalDescriptionDisplay').textContent = goal.description || '{{ __("member.family_show_no_description") }}';
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
            // Keep local cache in sync for subsequent edits
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
                statusBadge.classList.remove('bg-primary', 'bg-success');
                statusBadge.classList.add(goal.status === 'active' ? 'bg-primary' : 'bg-success');
                statusBadge.textContent = goal.status.charAt(0).toUpperCase() + goal.status.slice(1);
            }
            // Remove the edit button if the goal is no longer active
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
                    goalEditModal.hide();
                    if (data.goal) {
                        patchGoalCard(data.goal);
                        window.dispatchEvent(new CustomEvent('member-profile-updated', { detail: { goal: data.goal } }));
                    }
                    window.showToast('success', data.message || '{{ __("member.family_show_goal_updated") }}');
                } else {
                    window.showToast('error', '{{ __("member.family_show_error_updating_goal") }}' + (data.message || '{{ __("member.family_show_unknown_error") }}'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                window.showToast('error', '{{ __("member.family_show_error_updating_goal_retry") }}');
            });
        });

        // ===== Add-record flows (Goal / Attendance / Event) mirrored from the member profile =====
        function famEscape(s) {
            return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
            });
        }
        function famPost(form, onOk) {
            const btn = form.querySelector('button[type="submit"]');
            if (btn) btn.disabled = true;
            fetch(form.action, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || form.querySelector('input[name="_token"]').value,
                    'Accept': 'application/json',
                },
                body: new FormData(form),
                credentials: 'same-origin',
            })
            .then(async (res) => ({ ok: res.ok, status: res.status, data: await res.json().catch(() => ({})) }))
            .then(({ ok, status, data }) => {
                if (ok && data.success) { onOk(data); }
                else if (status === 422 && data.errors) {
                    const first = Object.values(data.errors)[0];
                    if (window.showToast) window.showToast('error', Array.isArray(first) ? first[0] : first);
                } else if (window.showToast) window.showToast('error', data.message || '{{ __("member.family_show_could_not_save") }}');
            })
            .catch(() => { if (window.showToast) window.showToast('error', '{{ __("member.family_show_network_error") }}'); })
            .finally(() => { if (btn) btn.disabled = false; });
        }

        // ---- Goal ----
        const goalAddForm = document.getElementById('goalAddForm');
        if (goalAddForm) {
            function famBindEdit(btn) {
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    populateGoalEditModal(this.getAttribute('data-goal-id'));
                    window.dispatchEvent(new CustomEvent('open-goal-edit-modal'));
                });
            }
            function famGoalIcon(t) { return t === 'dumbbell' ? 'bi bi-dumbbell text-white' : (t === 'clock' ? 'bi bi-clock text-white' : 'bi bi-bullseye text-white'); }
            function famRenderGoal(goal) {
                const pct = Math.max(0, Math.min(100, goal.progress_percentage || 0));
                const today = new Date().toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' });
                const prClass = goal.priority_level === 'high' ? 'bg-danger' : (goal.priority_level === 'medium' ? 'bg-warning text-dark' : 'bg-secondary');
                const col = document.createElement('div');
                col.className = 'col-lg-6';
                col.innerHTML =
                    '<div class="card shadow-sm border-0 h-full relative" id="goal-' + goal.id + '">' +
                        '<button class="btn btn-sm btn-outline-primary rounded-circle absolute top-0 end-0 mt-2 me-2 edit-goal-btn" style="width:32px;height:32px;padding:0;" data-goal-id="' + goal.id + '" title="{{ __("member.family_show_edit_goal") }}"><i class="bi bi-pencil"></i></button>' +
                        '<div class="card-body p-4">' +
                            '<div class="flex items-center mb-3">' +
                                '<div class="rounded-circle flex items-center justify-center me-3" style="width:48px;height:48px;background-color:#8b5cf6;"><i class="' + famGoalIcon(goal.icon_type) + '"></i></div>' +
                                '<div class="flex-1"><h6 class="font-bold mb-1">' + famEscape(goal.title) + '</h6>' + (goal.description ? '<p class="text-muted small mb-0">' + famEscape(goal.description) + '</p>' : '') + '</div>' +
                            '</div>' +
                            '<div class="mb-3"><div class="flex justify-between items-center mb-2">' +
                                '<small class="text-muted">{{ __("member.family_show_progress") }} ' + Number(goal.current_progress_value).toFixed(1) + ' / ' + Number(goal.target_value).toFixed(1) + ' ' + famEscape(goal.unit) + '</small>' +
                                '<small class="font-semibold">' + pct.toFixed(1) + '%</small></div>' +
                                '<div class="progress" style="height:8px;"><div class="progress-bar" role="progressbar" style="width:' + pct + '%;background:linear-gradient(90deg,#8b5cf6 0%,#10b981 100%);"></div></div>' +
                            '</div>' +
                            '<div class="row g-2 mb-3"><div class="col-6"><small class="text-muted block">{{ __("member.family_show_started") }}</small><small class="font-semibold">' + today + '</small></div>' +
                                '<div class="col-6 text-end"><small class="text-muted block">{{ __("member.family_show_target") }}</small><small class="font-semibold">' + famEscape(goal.target_date || '') + '</small></div></div>' +
                            '<div class="flex gap-2 flex-wrap"><span class="badge bg-primary small">{{ __("member.family_show_active") }}</span>' +
                                '<span class="badge ' + prClass + ' small">' + famEscape((goal.priority_level || 'medium').charAt(0).toUpperCase() + (goal.priority_level || 'medium').slice(1)) + '</span></div>' +
                        '</div>' +
                    '</div>';
                return col;
            }
            goalAddForm.addEventListener('submit', function (e) {
                e.preventDefault();
                famPost(goalAddForm, function (data) {
                    const grid = document.getElementById('goalsGrid');
                    const empty = document.getElementById('goalsEmpty');
                    const col = famRenderGoal(data.goal);
                    grid.prepend(col);
                    grid.classList.remove('hidden');
                    if (empty) empty.classList.add('hidden');
                    famBindEdit(col.querySelector('.edit-goal-btn'));
                    if (typeof goalsData !== 'undefined' && goalsData.push) goalsData.push(data.goal);
                    const cnt = document.getElementById('activeGoalsCount');
                    if (cnt) cnt.textContent = (parseInt(cnt.textContent, 10) || 0) + 1;
                    goalAddForm.reset();
                    document.getElementById('goal_add_current').value = '0';
                    window.dispatchEvent(new CustomEvent('close-goal-add-modal'));
                    if (window.showToast) window.showToast('success', data.message || '{{ __("member.family_show_goal_created") }}');
                });
            });
        }

        // ---- Attendance ----
        const attendanceAddForm = document.getElementById('attendanceAddForm');
        if (attendanceAddForm) {
            attendanceAddForm.addEventListener('submit', function (e) {
                e.preventDefault();
                famPost(attendanceAddForm, function (data) {
                    const tbody = document.getElementById('attendanceTbody');
                    const emptyRow = document.getElementById('attendanceEmptyRow');
                    if (emptyRow) emptyRow.remove();
                    const r = data.record;
                    const badge = r.status === 'completed' ? '<span class="badge bg-success">{{ __("member.family_show_completed") }}</span>' : '<span class="badge bg-danger">{{ __("member.family_show_no_show") }}</span>';
                    const tr = document.createElement('tr');
                    tr.innerHTML =
                        '<td class="align-middle"><div class="font-semibold">' + famEscape(r.date) + '</div><small class="text-muted">' + famEscape(r.time) + '</small></td>' +
                        '<td class="align-middle">' + famEscape(r.session_type) + '</td>' +
                        '<td class="align-middle">' + famEscape(r.trainer_name || '') + '</td>' +
                        '<td class="align-middle">' + badge + '</td>' +
                        '<td class="align-middle"><small class="text-muted">' + (r.notes ? famEscape(r.notes) : '-') + '</small></td>';
                    tbody.prepend(tr);
                    const cntEl = r.status === 'completed' ? document.getElementById('attendanceCompletedCount') : document.getElementById('attendanceNoShowCount');
                    if (cntEl) cntEl.textContent = (parseInt(cntEl.textContent, 10) || 0) + 1;
                    attendanceAddForm.reset();
                    window.dispatchEvent(new CustomEvent('close-attendance-add-modal'));
                    if (window.showToast) window.showToast('success', data.message || '{{ __("member.family_show_attendance_added") }}');
                });
            });
        }

        // ---- Event log ----
        const eventAddForm = document.getElementById('eventAddForm');
        if (eventAddForm) {
            eventAddForm.addEventListener('submit', function (e) {
                e.preventDefault();
                famPost(eventAddForm, function (data) {
                    const list = document.getElementById('eventLogList');
                    const empty = document.getElementById('eventLogEmpty');
                    const ev = data.event;
                    const row = document.createElement('div');
                    row.className = 'flex items-center gap-3 p-3 rounded-xl border border-gray-100';
                    row.id = 'member-event-' + ev.id;
                    row.innerHTML =
                        '<div class="flex-shrink-0 rounded-xl text-white text-center px-3 py-2 min-w-[52px]" style="background:#6d5ae0;">' +
                            '<div class="text-xs font-semibold uppercase leading-none">' + famEscape(ev.day) + '</div>' +
                            '<div class="text-xl font-extrabold leading-none">' + famEscape(ev.day_num) + '</div>' +
                            '<div class="text-xs font-semibold uppercase leading-none">' + famEscape(ev.month) + '</div></div>' +
                        '<div class="flex-1 min-w-0"><div class="font-semibold text-gray-800 truncate">' + famEscape(ev.title) + '</div>' +
                            '<div class="text-xs text-gray-500 mt-0.5">' +
                                (ev.role ? '<span class="me-3"><i class="bi bi-person-badge me-1"></i>' + famEscape(ev.role) + '</span>' : '') +
                                (ev.location ? '<span><i class="bi bi-geo-alt me-1"></i>' + famEscape(ev.location) + '</span>' : '') + '</div>' +
                            (ev.notes ? '<div class="text-xs text-gray-400 mt-0.5 truncate">' + famEscape(ev.notes) + '</div>' : '') + '</div>' +
                        (ev.result ? '<div class="flex-shrink-0"><span class="inline-flex items-center gap-1 text-xs font-semibold px-2 py-1 rounded-full bg-accent text-primary">' + famEscape(ev.result) + '</span></div>' : '');
                    list.prepend(row);
                    list.classList.remove('hidden');
                    if (empty) empty.classList.add('hidden');
                    eventAddForm.reset();
                    window.dispatchEvent(new CustomEvent('close-event-add-modal'));
                    if (window.showToast) window.showToast('success', data.message || '{{ __("member.family_show_event_added") }}');
                });
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
                    c.classList.remove('border-primary');
                });
                this.classList.add('border-primary');
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
            noSkillsMessage.classList.remove('d-none');
            return;
        }

        document.getElementById('skillsChart').style.display = 'block';
        noSkillsMessage.classList.add('d-none');

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
                                const duration = skill.formatted_duration || `${skill.duration_months} {{ __("member.family_show_months") }}`;
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
                    <p class="text-muted mb-0">${affiliation.date_range}</p>
                    <span class="badge bg-info text-dark small">${affiliation.formatted_duration}</span>
                </div>
            </div>
        `;

        if (affiliation.location) {
            html += `<p class="mb-2"><i class="bi bi-geo-alt me-2"></i><strong>{{ __("member.family_show_location_label") }}</strong> ${affiliation.location}</p>`;
        }

        if (affiliation.description) {
            html += `<p class="mb-2"><strong>{{ __("member.family_show_description_label") }}</strong> ${affiliation.description}</p>`;
        }

        if (affiliation.coaches && affiliation.coaches.length > 0) {
            html += `<p class="mb-2"><strong>{{ __("member.family_show_coaches_label") }}</strong> ${affiliation.coaches.join(', ')}</p>`;
        }

        if (affiliation.affiliation_media && affiliation.affiliation_media.length > 0) {
            html += `<div class="mt-3"><strong>{{ __("member.family_show_media_certificates") }}</strong></div>`;
            html += `<div class="row g-2 mt-1">`;

            affiliation.affiliation_media.forEach(media => {
                const iconClass = media.icon_class || 'bi-file';
                html += `
                    <div class="col-6">
                        <a href="${media.full_url}" target="_blank" class="btn btn-outline-secondary btn-sm w-full">
                            <i class="bi ${iconClass} me-1"></i>${media.title || media.media_type}
                        </a>
                    </div>
                `;
            });

            html += `</div>`;
        }

        detailsContainer.innerHTML = html;
    }

    // Initialize when affiliations tab is shown
    const affiliationsTab = document.getElementById('affiliations-tab');
    if (affiliationsTab) {
        affiliationsTab.addEventListener('shown.bs.tab', function() {
            initAffiliations();
        });
    }

    // Initialize immediately if affiliations tab is active
    if (document.getElementById('affiliations').classList.contains('show')) {
        initAffiliations();
    }
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
            <div class="row g-2">
                <div class="col-md-4">
                    <label class="form-label">{{ __("member.family_show_medal_type") }}</label>
                    <select class="form-select medal-type" name="performance_results[${performanceResultIndex}][medal_type]">
                        <option value="">{{ __("member.family_show_select_medal") }}</option>
                        <option value="special">{{ __("member.family_show_special_award") }}</option>
                        <option value="1st">{{ __("member.family_show_first_place") }}</option>
                        <option value="2nd">{{ __("member.family_show_second_place") }}</option>
                        <option value="3rd">{{ __("member.family_show_third_place") }}</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">{{ __("member.family_show_points") }}</label>
                    <input type="number" class="form-control" name="performance_results[${performanceResultIndex}][points]" min="0" step="0.1">
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __("member.family_show_description") }}</label>
                    <input type="text" class="form-control" name="performance_results[${performanceResultIndex}][description]" placeholder="{{ __("member.family_show_optional_description") }}">
                </div>
                <div class="col-md-1 flex items-end">
                    <button type="button" class="btn btn-outline-danger btn-sm remove-result">
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
            <div class="row g-2">
                <div class="col-md-6">
                    <label class="form-label">{{ __("member.family_show_note_text") }}</label>
                    <textarea class="form-control" name="notes_media[${notesMediaIndex}][note_text]" rows="2" placeholder="{{ __("member.family_show_note_text_ph") }}"></textarea>
                </div>
                <div class="col-md-5">
                    <label class="form-label">{{ __("member.family_show_media_link") }}</label>
                    <input type="url" class="form-control" name="notes_media[${notesMediaIndex}][media_link]" placeholder="https://example.com/photo.jpg">
                </div>
                <div class="col-md-1 flex items-end">
                    <button type="button" class="btn btn-outline-danger btn-sm remove-note">
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

    // Reset modal when opened
    document.getElementById('tournamentParticipationModal').addEventListener('show.bs.modal', function() {
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
                // Close modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('tournamentParticipationModal'));
                if (modal) modal.hide();

                // Insert the new tournament row in place (no reload)
                if (data.tournament) {
                    addTournamentRow(data.tournament);
                    window.dispatchEvent(new CustomEvent('member-profile-updated', { detail: { tournament: data.tournament } }));
                }

                // Show success message
                showAlert('{{ __("member.family_show_tournament_added") }}', 'success');
            } else {
                showAlert('{{ __("member.family_show_error_adding_tournament") }}' + (data.message || '{{ __("member.family_show_unknown_error") }}'), 'danger');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('{{ __("member.family_show_error_adding_tournament_retry") }}', 'danger');
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

        // Performance & Result cell
        let perfHtml = '';
        if (t.performance_results && t.performance_results.length > 0) {
            t.performance_results.forEach(r => {
                let medal = '';
                if (r.medal_type === '1st') {
                    medal = '<i class="bi bi-award-fill text-warning"></i><span class="badge bg-warning text-dark small">{{ __("member.family_show_first_place") }}</span>';
                } else if (r.medal_type === '2nd') {
                    medal = '<i class="bi bi-award-fill text-secondary"></i><span class="badge bg-secondary small">{{ __("member.family_show_second_place") }}</span>';
                } else if (r.medal_type === '3rd') {
                    medal = '<i class="bi bi-award-fill" style="color: #CD7F32;"></i><span class="badge" style="background-color: #CD7F32; color: white;" small>{{ __("member.family_show_third_place") }}</span>';
                } else if (r.medal_type === 'special') {
                    medal = '<i class="bi bi-trophy-fill text-warning"></i><span class="badge bg-warning text-dark small">{{ __("member.family_show_special_award") }}</span>';
                }
                perfHtml += `<div class="flex items-center gap-2 mb-1">${medal}${r.points ? `<small class="text-muted">${escapeHtml(r.points)} {{ __("member.family_show_pts") }}</small>` : ''}</div>`;
                if (r.description) {
                    perfHtml += `<small class="text-muted">${escapeHtml(r.description)}</small>`;
                }
            });
        } else {
            perfHtml = '<span class="text-muted small">{{ __("member.family_show_no_results") }}</span>';
        }

        // Notes & Media cell
        let notesHtml = '';
        if (t.notes_media && t.notes_media.length > 0) {
            t.notes_media.forEach(n => {
                if (n.note_text) notesHtml += `<p class="mb-1 small">${escapeHtml(n.note_text)}</p>`;
                if (n.media_link) notesHtml += `<a href="${escapeHtml(n.media_link)}" target="_blank" class="btn btn-sm btn-outline-primary small"><i class="bi bi-image me-1"></i>{{ __("member.family_show_view_media") }}</a>`;
            });
        } else {
            notesHtml = '<span class="text-muted small">{{ __("member.family_show_no_notes") }}</span>';
        }

        // Affiliation cell
        let affHtml;
        if (t.club_affiliation) {
            affHtml = `<div><div class="small font-semibold">${escapeHtml(t.club_affiliation.club_name)}</div><div class="text-muted small">${escapeHtml(t.club_affiliation.location)}</div></div>`;
        } else {
            affHtml = '<span class="text-muted small">{{ __("member.family_show_individual") }}</span>';
        }

        // Details cell
        let meta = `<i class="bi bi-calendar-event me-1"></i>${escapeHtml(t.date)}`;
        if (t.time) meta += `<i class="bi bi-clock me-1 ms-2"></i>${escapeHtml(t.time)}`;
        if (t.location) meta += `<i class="bi bi-geo-alt me-1 ms-2"></i>${escapeHtml(t.location)}`;
        if (t.participants_count) meta += `<i class="bi bi-people me-1 ms-2"></i>${escapeHtml(t.participants_count)} {{ __("member.family_show_participants") }}`;

        const typeBadge = (t.type === 'championship') ? 'bg-primary' : 'bg-secondary';

        tr.innerHTML = `
            <td>
                <div class="font-bold">${escapeHtml(t.title)}</div>
                <div class="flex gap-2 mt-1 flex-wrap">
                    <span class="badge ${typeBadge} small">${escapeHtml(t.type_label)}</span>
                    <span class="badge bg-info small">${escapeHtml(t.sport)}</span>
                </div>
                <div class="text-muted small mt-1">${meta}</div>
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

@endsection
