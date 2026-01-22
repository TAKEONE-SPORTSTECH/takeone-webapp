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
<div class="container py-4">
    <!-- Flash Messages -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">Member Profile</h2>
            <p class="text-muted mb-0">Comprehensive member information and analytics</p>
        </div>
    </div>

    <!-- Profile Card -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="d-flex">
            <!-- Profile Picture -->
            <div style="width: 180px; min-height: 250px; border-radius: 0.375rem 0 0 0.375rem;">
                @if($relationship->dependent->media_gallery[0] ?? false)
                    <img src="{{ $relationship->dependent->media_gallery[0] }}" alt="{{ $relationship->dependent->full_name }}" class="w-100 h-100" style="object-fit: cover; border-radius: 0.375rem 0 0 0.375rem;">
                @else
                    <div class="w-100 h-100 d-flex align-items-center justify-content-center text-white fw-bold" style="font-size: 3rem; background: linear-gradient(135deg, {{ $relationship->dependent->gender == 'm' ? '#0d6efd 0%, #0a58ca 100%' : '#d63384 0%, #a61e4d 100%' }}); border-radius: 0.375rem 0 0 0.375rem;">
                        {{ strtoupper(substr($relationship->dependent->full_name, 0, 1)) }}
                    </div>
                @endif
            </div>

            <!-- Profile Info -->
            <div class="flex-grow-1 p-4">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <h3 class="fw-bold mb-0">{{ $relationship->dependent->full_name }}</h3>
                    @if($relationship->relationship_type == 'self' || Auth::id() == $relationship->guardian_user_id)
                        <div>
                            <div class="dropdown">
                                <button class="btn btn-primary rounded-pill dropdown-toggle" type="button" id="actionDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="bi bi-lightning me-1"></i>Action
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="actionDropdown">
                                    <li><a class="dropdown-item" href="#"><i class="bi bi-trophy me-2"></i>Add Achievement</a></li>
                                    <li><a class="dropdown-item" href="#"><i class="bi bi-calendar-check me-2"></i>Add Attendance Record</a></li>
                                    <li><a class="dropdown-item" href="#"><i class="bi bi-calendar-event me-2"></i>Add Event Participation</a></li>
                                    <li><a class="dropdown-item" href="#" data-bs-target="#healthUpdateModal"><i class="bi bi-heart-pulse me-2"></i>Add Health Update</a></li>
                                    <li><a class="dropdown-item" href="#"><i class="bi bi-award me-2"></i>Add Tournament Participation</a></li>
                                    <li><a class="dropdown-item" href="@if($relationship->relationship_type == 'self'){{ route('profile.edit') }}@else{{ route('family.edit', $relationship->dependent->id) }}@endif">
                                        <i class="bi bi-pencil me-2"></i>Edit Info
                                    </a></li>
                                    <li><a class="dropdown-item" href="#"><i class="bi bi-bullseye me-2"></i>Set a Goal</a></li>
                                </ul>
                            </div>
                        </div>
                    @else
                        <button class="btn btn-primary btn-sm rounded-pill">
                            <i class="bi bi-person-plus me-1"></i>Follow
                        </button>
                    @endif
                </div>
                            @if($relationship->dependent->motto)
                                <p class="text-muted fst-italic mb-3">"{{ $relationship->dependent->motto }}"</p>
                            @endif

                            <!-- Achievement Badges -->
                            <div class="d-flex gap-2 mb-3 flex-wrap">
                                <a href="#" class="border bg-white rounded px-2 py-1 text-decoration-none" style="font-size: 1rem;">🏆 <span class="fw-semibold text-dark">3</span></a>
                                <a href="#" class="border bg-white rounded px-2 py-1 text-decoration-none" style="font-size: 1rem;">🥇 <span class="fw-semibold text-dark">4</span></a>
                                <a href="#" class="border bg-white rounded px-2 py-1 text-decoration-none" style="font-size: 1rem;">🥈 <span class="fw-semibold text-dark">6</span></a>
                                <a href="#" class="border bg-white rounded px-2 py-1 text-decoration-none" style="font-size: 1rem;">🥉 <span class="fw-semibold text-dark">3</span></a>
                                <a href="#" class="border bg-white rounded px-2 py-1 text-decoration-none" style="font-size: 1rem;">🎯 <span class="fw-semibold text-dark">8</span></a>
                                <a href="#" class="border bg-white rounded px-2 py-1 text-decoration-none" style="font-size: 1rem;">⭐ <span class="fw-semibold text-dark">12</span></a>
                            </div>

                            <!-- Status Badges -->
                            <div class="d-flex gap-3 mb-3 align-items-center flex-wrap">
                                <span class="text-muted small">
                                    <span class="fw-semibold text-dark nationality-display" data-iso3="{{ $relationship->dependent->nationality }}">{{ $relationship->dependent->nationality }}</span>
                                </span>
                                <span class="text-muted small">
                                    <i class="bi bi-{{ $relationship->dependent->gender == 'm' ? 'gender-male' : 'gender-female' }} me-1"></i>
                                    <span class="fw-semibold text-dark">{{ $relationship->dependent->gender == 'm' ? 'Male' : 'Female' }}</span>
                                </span>
                                <span class="text-muted small">
                                    <i class="bi bi-calendar-event me-1"></i>
                                    Age <span class="fw-semibold text-dark">{{ $relationship->dependent->age }}</span>
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
                                    {{ $symbol }} <span class="fw-semibold text-dark">{{ $horoscope }}</span>
                                </span>
                                <span class="text-muted small">
                                    <i class="bi bi-check-circle-fill text-success me-1"></i>
                                    <span class="fw-semibold text-success">Active</span>
                                </span>
                                <span class="text-muted small">
                                    <i class="bi bi-calendar-check me-1"></i>
                                    Joined <span class="fw-semibold text-dark">{{ $relationship->dependent->created_at->format('F Y') }}</span>
                                </span>
                            </div>

                            <!-- Social Media Icons -->
                            @if($relationship->dependent->social_links && count($relationship->dependent->social_links) > 0)
                                <div class="d-flex gap-2 flex-wrap">
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
                <i class="bi bi-eye me-2"></i>Overview
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link text-dark" id="attendance-tab" data-bs-toggle="tab" data-bs-target="#attendance" type="button" role="tab">
                <i class="bi bi-calendar-check me-2"></i>Attendance
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link text-dark" id="health-tab" data-bs-toggle="tab" data-bs-target="#health" type="button" role="tab">
                <i class="bi bi-heart-pulse me-2"></i>Health
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link text-dark" id="goals-tab" data-bs-toggle="tab" data-bs-target="#goals" type="button" role="tab">
                <i class="bi bi-bullseye me-2"></i>Goals
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link text-dark" id="achievements-tab" data-bs-toggle="tab" data-bs-target="#achievements" type="button" role="tab">
                <i class="bi bi-trophy me-2"></i>Achievements
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link text-dark" id="tournaments-tab" data-bs-toggle="tab" data-bs-target="#tournaments" type="button" role="tab">
                <i class="bi bi-award me-2"></i>Tournaments
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link text-dark" id="events-tab" data-bs-toggle="tab" data-bs-target="#events" type="button" role="tab">
                <i class="bi bi-calendar-event me-2"></i>Events
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
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center mb-2">
                                <i class="bi bi-bar-chart-line text-primary me-2"></i>
                                <h5 class="mb-0 fw-bold">Profile Statistics</h5>
                            </div>
                            <p class="text-muted small mb-4">Key performance metrics and milestones</p>

                            <div class="row g-3">
                                <!-- Total Sessions -->
                                <div class="col-md-6">
                            <div class="d-flex align-items-center gap-3 p-3 bg-light rounded">
                                <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background-color: #6f42c1;">
                                    <i class="bi bi-people-fill text-white"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="small text-muted mb-1">Total Sessions</div>
                                    <div class="h4 fw-bold mb-2">127</div>
                                    <div class="progress" style="height: 4px; background-color: #e9ecef;">
                                        <div class="progress-bar" role="progressbar" style="width: 85%; background: linear-gradient(90deg, #6f42c1 0%, #8b5cf6 100%);"></div>
                                    </div>
                                    <small class="text-muted">Sessions completed this year</small>
                                </div>
                            </div>
                        </div>

                                <!-- Total Revenue -->
                                <div class="col-md-6">
                            <div class="d-flex align-items-center gap-3 p-3 bg-light rounded">
                                <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background-color: #10b981;">
                                    <i class="bi bi-currency-dollar text-white"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="small text-muted mb-1">Total Revenue</div>
                                    <div class="h4 fw-bold mb-2">$4250</div>
                                    <div class="progress" style="height: 4px; background-color: #e9ecef;">
                                        <div class="progress-bar" role="progressbar" style="width: 70%; background: linear-gradient(90deg, #6f42c1 0%, #10b981 100%);"></div>
                                    </div>
                                    <small class="text-muted">Revenue generated this year</small>
                                </div>
                            </div>
                        </div>

                                <!-- Attendance Rate -->
                                <div class="col-md-6">
                            <div class="d-flex align-items-center gap-3 p-3 bg-light rounded">
                                <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background-color: #3b82f6;">
                                    <i class="bi bi-graph-up-arrow text-white"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="small text-muted mb-1">Attendance Rate</div>
                                    <div class="h4 fw-bold mb-2">85%</div>
                                    <div class="progress" style="height: 4px; background-color: #e9ecef;">
                                        <div class="progress-bar" role="progressbar" style="width: 85%; background: linear-gradient(90deg, #6f42c1 0%, #10b981 100%);"></div>
                                    </div>
                                    <small class="text-muted">Average session attendance</small>
                                </div>
                            </div>
                        </div>

                                <!-- Member Since -->
                                <div class="col-md-6">
                            <div class="d-flex align-items-center gap-3 p-3 bg-light rounded">
                                <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background-color: #f59e0b;">
                                    <i class="bi bi-calendar-check text-white"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="small text-muted mb-1">Member Since</div>
                                    <div class="h4 fw-bold mb-2">1.5</div>
                                    <div class="progress" style="height: 4px; background-color: #e9ecef;">
                                        <div class="progress-bar" role="progressbar" style="width: 30%; background: linear-gradient(90deg, #6f42c1 0%, #10b981 100%);"></div>
                                    </div>
                                    <small class="text-muted">Years of membership</small>
                                </div>
                            </div>
                        </div>

                                <!-- Achievements -->
                                <div class="col-md-6">
                            <div class="d-flex align-items-center gap-3 p-3 bg-light rounded">
                                <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background-color: #8b5cf6;">
                                    <i class="bi bi-trophy-fill text-white"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="small text-muted mb-1">Achievements</div>
                                    <div class="h4 fw-bold mb-2">8</div>
                                    <div class="progress" style="height: 4px; background-color: #e9ecef;">
                                        <div class="progress-bar" role="progressbar" style="width: 40%; background: linear-gradient(90deg, #6f42c1 0%, #10b981 100%);"></div>
                                    </div>
                                    <small class="text-muted">Total badges earned</small>
                                </div>
                            </div>
                        </div>

                                <!-- Goal Completion -->
                                <div class="col-md-6">
                            <div class="d-flex align-items-center gap-3 p-3 bg-light rounded">
                                <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background-color: #10b981;">
                                    <i class="bi bi-check-circle-fill text-white"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="small text-muted mb-1">Goal Completion</div>
                                    <div class="h4 fw-bold mb-2">75%</div>
                                    <div class="progress" style="height: 4px; background-color: #e9ecef;">
                                        <div class="progress-bar" role="progressbar" style="width: 75%; background: linear-gradient(90deg, #6f42c1 0%, #10b981 100%);"></div>
                                    </div>
                                    <small class="text-muted">Current goals achieved</small>
                                </div>
                            </div>
                        </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Revenue Chart -->
                <div class="col-lg-6">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center mb-2">
                                <i class="bi bi-bar-chart-line text-primary me-2"></i>
                                <h5 class="mb-0 fw-bold">Revenue Chart</h5>
                            </div>
                            <p class="text-muted small mb-4">Revenue analytics over time</p>

                            <div class="d-flex align-items-center justify-content-center" style="min-height: 300px;">
                                <div class="text-center">
                                    <i class="bi bi-graph-up text-muted" style="font-size: 3rem;"></i>
                                    <p class="text-muted mt-3 mb-1">Revenue chart visualization coming soon...</p>
                                    <small class="text-muted">Chart will display revenue trends over time</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Complete Payment & Revenue History -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center mb-2">
                        <i class="bi bi-receipt text-primary me-2"></i>
                        <h5 class="mb-0 fw-bold">Complete Payment & Revenue History</h5>
                    </div>
                    <p class="text-muted small mb-4">All package payments and revenue transactions in one view</p>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-muted small fw-semibold">Date</th>
                                    <th class="text-muted small fw-semibold">Transaction Type</th>
                                    <th class="text-muted small fw-semibold">Package/Item</th>
                                    <th class="text-muted small fw-semibold">Duration</th>
                                    <th class="text-muted small fw-semibold">Sessions</th>
                                    <th class="text-muted small fw-semibold">Amount</th>
                                    <th class="text-muted small fw-semibold">Status</th>
                                    <th class="text-muted small fw-semibold">Method</th>
                                    <th class="text-muted small fw-semibold">Evidence</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="small">2023-12-15</td>
                                    <td class="small text-primary">Package Payment</td>
                                    <td class="small">Premium Fitness + Personal Training</td>
                                    <td class="small text-muted">2023-12-15 to 2024-06-15</td>
                                    <td class="small">18/24</td>
                                    <td class="small fw-semibold" style="color: #10b981;">649.5 BHD</td>
                                    <td><span class="badge bg-success-subtle text-success small">✓ Paid</span></td>
                                    <td class="small">Credit Card</td>
                                    <td class="small">
                                        <i class="bi bi-file-earmark-text text-primary"></i>
                                        <i class="bi bi-download text-secondary ms-1"></i>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="small">2024-02-15</td>
                                    <td class="small text-primary">Package Payment</td>
                                    <td class="small">Premium Fitness + Personal Training</td>
                                    <td class="small text-muted">2023-12-15 to 2024-06-15</td>
                                    <td class="small">18/24</td>
                                    <td class="small fw-semibold" style="color: #f59e0b;">649.5 BHD</td>
                                    <td><span class="badge bg-warning-subtle text-warning small">○ Due</span></td>
                                    <td class="small">Auto-pay</td>
                                    <td class="small">-</td>
                                </tr>
                                <tr>
                                    <td class="small">2023-06-15</td>
                                    <td class="small text-primary">Package Payment</td>
                                    <td class="small">Basic Gym Membership</td>
                                    <td class="small text-muted">2023-06-15 to 2023-12-15</td>
                                    <td class="small">Unlimited</td>
                                    <td class="small fw-semibold" style="color: #10b981;">599 BHD</td>
                                    <td><span class="badge bg-success-subtle text-success small">✓ Paid</span></td>
                                    <td class="small">Bank Transfer</td>
                                    <td class="small">
                                        <i class="bi bi-file-earmark-text text-primary"></i>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="small">2024-02-01</td>
                                    <td class="small text-primary">Package Payment</td>
                                    <td class="small">Nutrition Consultation Package</td>
                                    <td class="small text-muted">2024-02-01 to 2024-05-01</td>
                                    <td class="small">0/6</td>
                                    <td class="small fw-semibold" style="color: #f59e0b;">450 BHD</td>
                                    <td><span class="badge bg-warning-subtle text-warning small">○ Due</span></td>
                                    <td class="small">Credit Card</td>
                                    <td class="small">-</td>
                                </tr>
                                <tr>
                                    <td class="small">2024-01-08</td>
                                    <td class="small text-secondary">Service/Product</td>
                                    <td class="small">Personal Training Session - Paid</td>
                                    <td class="small">-</td>
                                    <td class="small">-</td>
                                    <td class="small fw-semibold" style="color: #10b981;">75 BHD</td>
                                    <td><span class="badge bg-success-subtle text-success small">✓ Paid</span></td>
                                    <td class="small">Credit Card</td>
                                    <td class="small">-</td>
                                </tr>
                                <tr>
                                    <td class="small">2024-01-05</td>
                                    <td class="small text-secondary">Service/Product</td>
                                    <td class="small">Protein Supplement - Paid</td>
                                    <td class="small">-</td>
                                    <td class="small">-</td>
                                    <td class="small fw-semibold" style="color: #10b981;">45 BHD</td>
                                    <td><span class="badge bg-success-subtle text-success small">✓ Paid</span></td>
                                    <td class="small">Cash</td>
                                    <td class="small">-</td>
                                </tr>
                                <tr>
                                    <td class="small">2024-01-01</td>
                                    <td class="small text-secondary">Service/Product</td>
                                    <td class="small">Monthly Membership - Due</td>
                                    <td class="small">-</td>
                                    <td class="small">-</td>
                                    <td class="small fw-semibold" style="color: #10b981;">99 BHD</td>
                                    <td><span class="badge bg-success-subtle text-success small">✓ Paid</span></td>
                                    <td class="small">Auto-pay</td>
                                    <td class="small">-</td>
                                </tr>
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
                    <h5 class="fw-bold mb-3">Attendance Records</h5>
                    <p class="text-muted">Attendance tracking coming soon...</p>
                </div>
            </div>
        </div>

        <!-- Health Tab -->
        <div class="tab-pane fade" id="health" role="tabpanel">
            <!-- Health Tracking Header -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center mb-2">
                        <i class="bi bi-heart-pulse text-danger me-2"></i>
                        <h5 class="mb-0 fw-bold">Health Metrics Overview</h5>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <p class="text-muted small mb-0">Monitor health metrics and progress over time</p>

                        @if($latestHealthRecord)
                            @php
                                $latestDate = $latestHealthRecord->recorded_at;
                                $now = \Carbon\Carbon::now();
                                $diff = $latestDate->diff($now);
                            @endphp

                            <div class="d-flex align-items-center gap-3">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-calendar-event text-primary"></i>
                                    <span class="fw-semibold">Snapshot Date:</span>
                                    <span class="text-muted">{{ $latestDate->format('F j, Y') }}</span>
                                </div>
                                <div class="vr"></div>
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-clock-history text-primary"></i>
                                    <span class="fw-semibold">Time Since:</span>
                                    <span class="text-muted">
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
                            <div class="text-muted small">No health records available</div>
                        @endif
                    </div>

                    <!-- Health Metrics Cards -->
                    <div class="row g-3">
                        @if($latestHealthRecord)
                            <!-- Weight -->
                            <div class="col-md-2">
                                <div class="text-center p-3 bg-light rounded">
                                    <i class="bi bi-speedometer2 text-purple mb-2" style="font-size: 1.5rem; color: #8b5cf6;"></i>
                                    <div class="h4 fw-bold mb-0">{{ $latestHealthRecord->weight ?? 'N/A' }}</div>
                                    <small class="text-muted">Weight (kg)</small>
                                </div>
                            </div>

                            <!-- Body Fat -->
                            <div class="col-md-2">
                                <div class="text-center p-3 bg-light rounded">
                                    <i class="bi bi-activity text-warning mb-2" style="font-size: 1.5rem;"></i>
                                    <div class="h4 fw-bold mb-0">{{ $latestHealthRecord->body_fat_percentage ?? 'N/A' }}%</div>
                                    <small class="text-muted">Body Fat</small>
                                </div>
                            </div>

                            <!-- Body Water -->
                            <div class="col-md-2">
                                <div class="text-center p-3 bg-light rounded">
                                    <i class="bi bi-droplet text-info mb-2" style="font-size: 1.5rem;"></i>
                                    <div class="h4 fw-bold mb-0">{{ $latestHealthRecord->body_water_percentage ?? 'N/A' }}%</div>
                                    <small class="text-muted">Body Water</small>
                                </div>
                            </div>

                            <!-- Muscle Mass -->
                            <div class="col-md-2">
                                <div class="text-center p-3 bg-light rounded">
                                    <i class="bi bi-heart text-success mb-2" style="font-size: 1.5rem;"></i>
                                    <div class="h4 fw-bold mb-0">{{ $latestHealthRecord->muscle_mass ?? 'N/A' }}</div>
                                    <small class="text-muted">Muscle Mass</small>
                                </div>
                            </div>

                            <!-- Bone Mass -->
                            <div class="col-md-2">
                                <div class="text-center p-3 bg-light rounded">
                                    <i class="bi bi-capsule text-secondary mb-2" style="font-size: 1.5rem;"></i>
                                    <div class="h4 fw-bold mb-0">{{ $latestHealthRecord->bone_mass ?? 'N/A' }}</div>
                                    <small class="text-muted">Bone Mass</small>
                                </div>
                            </div>

                            <!-- BMR -->
                            <div class="col-md-2">
                                <div class="text-center p-3 bg-light rounded">
                                    <i class="bi bi-lightning text-danger mb-2" style="font-size: 1.5rem;"></i>
                                    <div class="h4 fw-bold mb-0">{{ $latestHealthRecord->bmr ?? 'N/A' }}</div>
                                    <small class="text-muted">BMR (cal)</small>
                                </div>
                            </div>
                        @else
                            <div class="col-12">
                                <div class="text-center py-4">
                                    <i class="bi bi-heart-pulse text-muted" style="font-size: 3rem;"></i>
                                    <p class="text-muted mt-3">No health metrics available</p>
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
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-body p-4">
                            <h5 class="fw-bold mb-4">Body Composition Analysis</h5>

                            <div class="text-center py-5">
                                <i class="bi bi-radar text-muted" style="font-size: 3rem;"></i>
                                <p class="text-muted mt-3 mb-1">Radar chart visualization coming soon...</p>
                                <small class="text-muted">Chart will compare current vs previous body composition metrics</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Compare -->
                <div class="col-lg-5">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-body p-4">
                            <h5 class="fw-bold mb-4">Compare</h5>

                            @if($comparisonRecords->count() >= 2)
                                @php
                                    $current = $comparisonRecords->first();
                                    $previous = $comparisonRecords->skip(1)->first();
                                @endphp

                                <div class="mb-3">
                                    <div class="row g-2">
                                        <div class="col-6 text-center">
                                            <label class="form-label fw-bold">From</label>
                                            <select class="form-select form-select-sm" id="currentDate">
                                                @foreach($healthRecords as $record)
                                                    <option value="{{ $record->id }}" {{ $record->id == $current->id ? 'selected' : '' }}>
                                                        {{ $record->recorded_at->format('M j, Y') }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-6 text-center">
                                            <label class="form-label fw-bold">To</label>
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
                                                <strong>Time between records:</strong> {{ calculateTimeDifference($current->recorded_at, $previous->recorded_at) }}
                                            @else
                                                Select dates to see time difference
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-sm align-middle">
                                        <thead>
                                            <tr class="border-bottom">
                                                <th class="text-muted small fw-semibold">Metric</th>
                                                <th class="text-muted small fw-semibold text-end">Current</th>
                                                <th class="text-muted small fw-semibold text-center">Change</th>
                                                <th class="text-muted small fw-semibold text-end">Previous</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @php
                                                function getChangeIcon($current, $previous) {
                                                    if ($current > $previous) return '<i class="bi bi-arrow-up text-success"></i>';
                                                    if ($current < $previous) return '<i class="bi bi-arrow-down text-danger"></i>';
                                                    return '<i class="bi bi-dash text-muted"></i>';
                                                }
                                            @endphp
                                            <tr data-metric="weight">
                                                <td class="small"><i class="bi bi-speedometer2 me-2"></i>Weight</td>
                                                <td class="small text-end fw-semibold">{{ $current->weight ?? 'N/A' }}kg</td>
                                                <td class="text-center">{!! $current->weight && $previous->weight ? getChangeIcon($current->weight, $previous->weight) : '-' !!}</td>
                                                <td class="small text-end text-muted">{{ $previous->weight ?? 'N/A' }}kg</td>
                                            </tr>
                                            <tr data-metric="body_fat">
                                                <td class="small"><i class="bi bi-activity me-2"></i>Body Fat</td>
                                                <td class="small text-end fw-semibold">{{ $current->body_fat_percentage ?? 'N/A' }}%</td>
                                                <td class="text-center">{!! $current->body_fat_percentage && $previous->body_fat_percentage ? getChangeIcon($current->body_fat_percentage, $previous->body_fat_percentage) : '-' !!}</td>
                                                <td class="small text-end text-muted">{{ $previous->body_fat_percentage ?? 'N/A' }}%</td>
                                            </tr>
                                            <tr data-metric="bmi">
                                                <td class="small"><i class="bi bi-calculator me-2"></i>BMI</td>
                                                <td class="small text-end fw-semibold">{{ $current->bmi ?? 'N/A' }}</td>
                                                <td class="text-center">{!! $current->bmi && $previous->bmi ? getChangeIcon($current->bmi, $previous->bmi) : '-' !!}</td>
                                                <td class="small text-end text-muted">{{ $previous->bmi ?? 'N/A' }}</td>
                                            </tr>
                                            <tr data-metric="body_water">
                                                <td class="small"><i class="bi bi-droplet me-2"></i>Body Water</td>
                                                <td class="small text-end fw-semibold">{{ $current->body_water_percentage ?? 'N/A' }}%</td>
                                                <td class="text-center">{!! $current->body_water_percentage && $previous->body_water_percentage ? getChangeIcon($current->body_water_percentage, $previous->body_water_percentage) : '-' !!}</td>
                                                <td class="small text-end text-muted">{{ $previous->body_water_percentage ?? 'N/A' }}%</td>
                                            </tr>
                                            <tr data-metric="muscle_mass">
                                                <td class="small"><i class="bi bi-heart me-2"></i>Muscle Mass</td>
                                                <td class="small text-end fw-semibold">{{ $current->muscle_mass ?? 'N/A' }}kg</td>
                                                <td class="text-center">{!! $current->muscle_mass && $previous->muscle_mass ? getChangeIcon($current->muscle_mass, $previous->muscle_mass) : '-' !!}</td>
                                                <td class="small text-end text-muted">{{ $previous->muscle_mass ?? 'N/A' }}kg</td>
                                            </tr>
                                            <tr data-metric="bone_mass">
                                                <td class="small"><i class="bi bi-capsule me-2"></i>Bone Mass</td>
                                                <td class="small text-end fw-semibold">{{ $current->bone_mass ?? 'N/A' }}kg</td>
                                                <td class="text-center">{!! $current->bone_mass && $previous->bone_mass ? getChangeIcon($current->bone_mass, $previous->bone_mass) : '-' !!}</td>
                                                <td class="small text-end text-muted">{{ $previous->bone_mass ?? 'N/A' }}kg</td>
                                            </tr>
                                            <tr data-metric="visceral_fat">
                                                <td class="small"><i class="bi bi-activity me-2"></i>Visceral Fat</td>
                                                <td class="small text-end fw-semibold">{{ $current->visceral_fat ?? 'N/A' }}</td>
                                                <td class="text-center">{!! $current->visceral_fat && $previous->visceral_fat ? getChangeIcon($current->visceral_fat, $previous->visceral_fat) : '-' !!}</td>
                                                <td class="small text-end text-muted">{{ $previous->visceral_fat ?? 'N/A' }}</td>
                                            </tr>
                                            <tr data-metric="bmr">
                                                <td class="small"><i class="bi bi-lightning me-2"></i>BMR</td>
                                                <td class="small text-end fw-semibold">{{ $current->bmr ?? 'N/A' }}cal</td>
                                                <td class="text-center">{!! $current->bmr && $previous->bmr ? getChangeIcon($current->bmr, $previous->bmr) : '-' !!}</td>
                                                <td class="small text-end text-muted">{{ $previous->bmr ?? 'N/A' }}cal</td>
                                            </tr>
                                            <tr data-metric="protein">
                                                <td class="small"><i class="bi bi-heart-pulse me-2"></i>Protein</td>
                                                <td class="small text-end fw-semibold">{{ $current->protein_percentage ?? 'N/A' }}%</td>
                                                <td class="text-center">{!! $current->protein_percentage && $previous->protein_percentage ? getChangeIcon($current->protein_percentage, $previous->protein_percentage) : '-' !!}</td>
                                                <td class="small text-end text-muted">{{ $previous->protein_percentage ?? 'N/A' }}%</td>
                                            </tr>
                                            <tr data-metric="body_age">
                                                <td class="small"><i class="bi bi-calendar-heart me-2"></i>Body Age</td>
                                                <td class="small text-end fw-semibold">{{ $current->body_age ?? 'N/A' }}yrs</td>
                                                <td class="text-center">{!! $current->body_age && $previous->body_age ? getChangeIcon($current->body_age, $previous->body_age) : '-' !!}</td>
                                                <td class="small text-end text-muted">{{ $previous->body_age ?? 'N/A' }}yrs</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="text-center py-4">
                                    <i class="bi bi-bar-chart-line text-muted" style="font-size: 3rem;"></i>
                                    <p class="text-muted mt-3">Need at least 2 health records to compare</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Health Tracking History -->
            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-4">Health Tracking History</h5>

                    @if($healthRecords->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th class="text-muted small fw-semibold">Date</th>
                                        <th class="text-muted small fw-semibold text-center"><i class="bi bi-speedometer2 me-1"></i>Weight (kg)</th>
                                        <th class="text-muted small fw-semibold text-center"><i class="bi bi-activity me-1"></i>Body Fat %</th>
                                        <th class="text-muted small fw-semibold text-center"><i class="bi bi-calculator me-1"></i>BMI</th>
                                        <th class="text-muted small fw-semibold text-center"><i class="bi bi-droplet me-1"></i>Body Water %</th>
                                        <th class="text-muted small fw-semibold text-center"><i class="bi bi-heart me-1"></i>Muscle Mass (kg)</th>
                                        <th class="text-muted small fw-semibold text-center"><i class="bi bi-capsule me-1"></i>Bone Mass (kg)</th>
                                        <th class="text-muted small fw-semibold text-center"><i class="bi bi-activity me-1"></i>Visceral Fat</th>
                                        <th class="text-muted small fw-semibold text-center"><i class="bi bi-lightning me-1"></i>BMR</th>
                                        <th class="text-muted small fw-semibold text-center"><i class="bi bi-heart-pulse me-1"></i>Protein %</th>
                                        <th class="text-muted small fw-semibold text-center"><i class="bi bi-calendar-heart me-1"></i>Body Age</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($healthRecords as $record)
                                        <tr data-record-id="{{ $record->id }}" class="position-relative history-row">
                                            <td class="small fw-semibold">{{ $record->recorded_at->format('M j, Y') }}</td>
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
                                            <td class="position-absolute top-50 end-0 translate-middle-y opacity-0 edit-record-btn" style="cursor: pointer; right: 10px;">
                                                <i class="bi bi-pencil text-primary" style="font-size: 1.2rem;"></i>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div class="d-flex justify-content-center mt-4">
                            {{ $healthRecords->links() }}
                        </div>
                    @else
                        <div class="text-center py-5">
                            <i class="bi bi-clipboard-data text-muted" style="font-size: 3rem;"></i>
                            <p class="text-muted mt-3">No health records found</p>
                            <small class="text-muted">Health tracking data will appear here once records are added</small>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Goals Tab -->
        <div class="tab-pane fade" id="goals" role="tabpanel">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3">Goals & Progress</h5>
                    <p class="text-muted">Goal tracking coming soon...</p>
                </div>
            </div>
        </div>

        <!-- Achievements Tab -->
        <div class="tab-pane fade" id="achievements" role="tabpanel">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3">Achievements & Badges</h5>
                    <p class="text-muted">Achievement system coming soon...</p>
                </div>
            </div>
        </div>

        <!-- Tournaments Tab -->
        <div class="tab-pane fade" id="tournaments" role="tabpanel">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3">Tournament History</h5>
                    <p class="text-muted">Tournament records coming soon...</p>
                </div>
            </div>
        </div>

        <!-- Events Tab -->
        <div class="tab-pane fade" id="events" role="tabpanel">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3">Event Participation</h5>
                    <p class="text-muted">Event history coming soon...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Health Update Modal -->
<div class="modal fade" id="healthUpdateModal" tabindex="-1" aria-labelledby="healthUpdateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="healthUpdateModalLabel">Add Health Update</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="healthUpdateForm" method="POST" action="{{ route('family.store-health', $relationship->dependent->id) }}">
                @csrf
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="recorded_at" class="form-label">Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="recorded_at" name="recorded_at" value="{{ \Carbon\Carbon::now()->format('Y-m-d') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label for="weight" class="form-label">Weight (kg)</label>
                            <input type="number" step="0.1" class="form-control" id="weight" name="weight">
                        </div>
                        <div class="col-md-6">
                            <label for="body_fat_percentage" class="form-label">Body Fat (%)</label>
                            <input type="number" step="0.1" class="form-control" id="body_fat_percentage" name="body_fat_percentage">
                        </div>
                        <div class="col-md-6">
                            <label for="bmi" class="form-label">BMI</label>
                            <input type="number" step="0.1" class="form-control" id="bmi" name="bmi">
                        </div>
                        <div class="col-md-6">
                            <label for="body_water_percentage" class="form-label">Body Water (%)</label>
                            <input type="number" step="0.1" class="form-control" id="body_water_percentage" name="body_water_percentage">
                        </div>
                        <div class="col-md-6">
                            <label for="muscle_mass" class="form-label">Muscle Mass (kg)</label>
                            <input type="number" step="0.1" class="form-control" id="muscle_mass" name="muscle_mass">
                        </div>
                        <div class="col-md-6">
                            <label for="bone_mass" class="form-label">Bone Mass (kg)</label>
                            <input type="number" step="0.1" class="form-control" id="bone_mass" name="bone_mass">
                        </div>
                        <div class="col-md-6">
                            <label for="visceral_fat" class="form-label">Visceral Fat</label>
                            <input type="number" class="form-control" id="visceral_fat" name="visceral_fat">
                        </div>
                        <div class="col-md-6">
                            <label for="bmr" class="form-label">BMR (cal)</label>
                            <input type="number" class="form-control" id="bmr" name="bmr">
                        </div>
                        <div class="col-md-6">
                            <label for="protein_percentage" class="form-label">Protein (%)</label>
                            <input type="number" step="0.1" class="form-control" id="protein_percentage" name="protein_percentage">
                        </div>
                        <div class="col-md-6">
                            <label for="body_age" class="form-label">Body Age (years)</label>
                            <input type="number" class="form-control" id="body_age" name="body_age">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Health Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .history-row:hover .edit-record-btn {
        opacity: 1 !important;
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

                    const country = countries.find(c => c.iso3 === iso3Code);
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

        // Function to reset modal for adding new record
        function resetHealthModal() {
            document.getElementById('healthUpdateModalLabel').textContent = 'Add Health Update';
            document.getElementById('healthUpdateForm').action = '{{ route("family.store-health", $relationship->dependent->id) }}';
            document.getElementById('healthUpdateForm').method = 'POST';
            document.getElementById('recorded_at').value = '{{ \Carbon\Carbon::now()->format("Y-m-d") }}';
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
                    if (metric === 'weight' || metric === 'muscle_mass' || metric === 'bone_mass') {
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
                    if (metric === 'weight' || metric === 'muscle_mass' || metric === 'bone_mass') {
                        cells[3].textContent = previousValue ? `${previousValue}kg` : 'N/A';
                    } else if (metric === 'body_fat' || metric === 'body_water' || metric === 'protein') {
                        cells[3].textContent = previousValue ? `${previousValue}%` : 'N/A';
                    } else if (metric === 'bmr') {
                        cells[3].textContent = previousValue ? `${previousValue}cal` : 'N/A';
                    } else if (metric === 'body_age') {
                        cells[3].textContent = previousValue ? `${previousValue}yrs` : 'N/A';
                    } else {
                        cells[3].textContent = previousValue || 'N/A';
                    }

                    // Update change cell
                    const changeCell = cells[2];
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
        }
    });
</script>
@endsection
