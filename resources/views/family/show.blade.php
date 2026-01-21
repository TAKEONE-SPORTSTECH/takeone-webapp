@extends('layouts.app')

@section('content')
<div class="container py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">Member Profile</h2>
            <p class="text-muted mb-0">Comprehensive member information and analytics</p>
        </div>
        <div>
            <div class="dropdown">
                <button class="btn btn-primary rounded-pill dropdown-toggle" type="button" id="actionDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-lightning me-1"></i>Action
                </button>
                <ul class="dropdown-menu" aria-labelledby="actionDropdown">
                    <li><a class="dropdown-item" href="@if($relationship->relationship_type == 'self'){{ route('profile.edit') }}@else{{ route('family.edit', $relationship->dependent->id) }}@endif">
                        <i class="bi bi-pencil me-2"></i>Edit Info
                    </a></li>
                    <li><a class="dropdown-item" href="#"><i class="bi bi-calendar-check me-2"></i>Add Attendance Record</a></li>
                    <li><a class="dropdown-item" href="#"><i class="bi bi-heart-pulse me-2"></i>Add Health Update</a></li>
                    <li><a class="dropdown-item" href="#"><i class="bi bi-bullseye me-2"></i>Set a Goal</a></li>
                    <li><a class="dropdown-item" href="#"><i class="bi bi-trophy me-2"></i>Add Achievement</a></li>
                    <li><a class="dropdown-item" href="#"><i class="bi bi-award me-2"></i>Add Tournament Participation</a></li>
                    <li><a class="dropdown-item" href="#"><i class="bi bi-calendar-event me-2"></i>Add Event Participation</a></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Profile Card -->
    <div class="card shadow-sm border-0 mb-4 overflow-hidden">
        <div class="d-flex">
            <!-- Profile Picture -->
            <div style="width: 180px; min-height: 250px;">
                @if($relationship->dependent->media_gallery[0] ?? false)
                    <img src="{{ $relationship->dependent->media_gallery[0] }}" alt="{{ $relationship->dependent->full_name }}" class="w-100 h-100" style="object-fit: cover;">
                @else
                    <div class="w-100 h-100 d-flex align-items-center justify-content-center text-white fw-bold" style="font-size: 3rem; background: linear-gradient(135deg, {{ $relationship->dependent->gender == 'm' ? '#0d6efd 0%, #0a58ca 100%' : '#d63384 0%, #a61e4d 100%' }});">
                        {{ strtoupper(substr($relationship->dependent->full_name, 0, 1)) }}
                    </div>
                @endif
            </div>

            <!-- Profile Info -->
            <div class="flex-grow-1 p-4">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <h3 class="fw-bold mb-0">{{ $relationship->dependent->full_name }}</h3>
                    <button class="btn btn-primary btn-sm rounded-pill">
                        <i class="bi bi-person-plus me-1"></i>Follow
                    </button>
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

                        @php
                            $latestDate = \Carbon\Carbon::parse('2024-01-08');
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
                    </div>

                    <!-- Health Metrics Cards -->
                    <div class="row g-3">
                        <!-- Weight -->
                        <div class="col-md-2">
                            <div class="text-center p-3 bg-light rounded">
                                <i class="bi bi-speedometer2 text-purple mb-2" style="font-size: 1.5rem; color: #8b5cf6;"></i>
                                <div class="h4 fw-bold mb-0">75</div>
                                <small class="text-muted">Weight (kg)</small>
                            </div>
                        </div>

                        <!-- Body Fat -->
                        <div class="col-md-2">
                            <div class="text-center p-3 bg-light rounded">
                                <i class="bi bi-activity text-warning mb-2" style="font-size: 1.5rem;"></i>
                                <div class="h4 fw-bold mb-0">12.8%</div>
                                <small class="text-muted">Body Fat</small>
                            </div>
                        </div>

                        <!-- Body Water -->
                        <div class="col-md-2">
                            <div class="text-center p-3 bg-light rounded">
                                <i class="bi bi-droplet text-info mb-2" style="font-size: 1.5rem;"></i>
                                <div class="h4 fw-bold mb-0">68.2%</div>
                                <small class="text-muted">Body Water</small>
                            </div>
                        </div>

                        <!-- Muscle Mass -->
                        <div class="col-md-2">
                            <div class="text-center p-3 bg-light rounded">
                                <i class="bi bi-heart text-success mb-2" style="font-size: 1.5rem;"></i>
                                <div class="h4 fw-bold mb-0">70.5</div>
                                <small class="text-muted">Muscle Mass</small>
                            </div>
                        </div>

                        <!-- Bone Mass -->
                        <div class="col-md-2">
                            <div class="text-center p-3 bg-light rounded">
                                <i class="bi bi-capsule text-secondary mb-2" style="font-size: 1.5rem;"></i>
                                <div class="h4 fw-bold mb-0">3.7</div>
                                <small class="text-muted">Bone Mass</small>
                            </div>
                        </div>

                        <!-- BMR -->
                        <div class="col-md-2">
                            <div class="text-center p-3 bg-light rounded">
                                <i class="bi bi-lightning text-danger mb-2" style="font-size: 1.5rem;"></i>
                                <div class="h4 fw-bold mb-0">1895</div>
                                <small class="text-muted">BMR (cal)</small>
                            </div>
                        </div>
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
                                        <tr>
                                            <td class="small"><i class="bi bi-speedometer2 me-2"></i>Weight</td>
                                            <td class="small text-end fw-semibold">75kg</td>
                                            <td class="text-center"><i class="bi bi-arrow-down text-success"></i></td>
                                            <td class="small text-end text-muted">77kg</td>
                                        </tr>
                                        <tr>
                                            <td class="small"><i class="bi bi-activity me-2"></i>Body Fat</td>
                                            <td class="small text-end fw-semibold">12.8%</td>
                                            <td class="text-center"><i class="bi bi-arrow-down text-success"></i></td>
                                            <td class="small text-end text-muted">14.5%</td>
                                        </tr>
                                        <tr>
                                            <td class="small"><i class="bi bi-calculator me-2"></i>BMI</td>
                                            <td class="small text-end fw-semibold">22.5</td>
                                            <td class="text-center"><i class="bi bi-arrow-down text-success"></i></td>
                                            <td class="small text-end text-muted">23.2</td>
                                        </tr>
                                        <tr>
                                            <td class="small"><i class="bi bi-droplet me-2"></i>Body Water</td>
                                            <td class="small text-end fw-semibold">68.2%</td>
                                            <td class="text-center"><i class="bi bi-arrow-up text-success"></i></td>
                                            <td class="small text-end text-muted">65.8%</td>
                                        </tr>
                                        <tr>
                                            <td class="small"><i class="bi bi-heart me-2"></i>Muscle Mass</td>
                                            <td class="small text-end fw-semibold">70.5kg</td>
                                            <td class="text-center"><i class="bi bi-arrow-up text-success"></i></td>
                                            <td class="small text-end text-muted">68.2kg</td>
                                        </tr>
                                        <tr>
                                            <td class="small"><i class="bi bi-capsule me-2"></i>Bone Mass</td>
                                            <td class="small text-end fw-semibold">3.7kg</td>
                                            <td class="text-center"><i class="bi bi-arrow-up text-success"></i></td>
                                            <td class="small text-end text-muted">3.6kg</td>
                                        </tr>
                                        <tr>
                                            <td class="small"><i class="bi bi-activity me-2"></i>Visceral Fat</td>
                                            <td class="small text-end fw-semibold">3</td>
                                            <td class="text-center"><i class="bi bi-arrow-down text-success"></i></td>
                                            <td class="small text-end text-muted">4</td>
                                        </tr>
                                        <tr>
                                            <td class="small"><i class="bi bi-lightning me-2"></i>BMR</td>
                                            <td class="small text-end fw-semibold">1895cal</td>
                                            <td class="text-center"><i class="bi bi-arrow-up text-success"></i></td>
                                            <td class="small text-end text-muted">1865cal</td>
                                        </tr>
                                        <tr>
                                            <td class="small"><i class="bi bi-heart-pulse me-2"></i>Protein</td>
                                            <td class="small text-end fw-semibold">19.8%</td>
                                            <td class="text-center"><i class="bi bi-arrow-up text-success"></i></td>
                                            <td class="small text-end text-muted">18.9%</td>
                                        </tr>
                                        <tr>
                                            <td class="small"><i class="bi bi-calendar-heart me-2"></i>Body Age</td>
                                            <td class="small text-end fw-semibold">25yrs</td>
                                            <td class="text-center"><i class="bi bi-arrow-down text-success"></i></td>
                                            <td class="small text-end text-muted">27yrs</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Health Tracking History -->
            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-4">Health Tracking History</h5>

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
                                <tr>
                                    <td class="small fw-semibold">Jan 08, 2024</td>
                                    <td class="small text-center">75</td>
                                    <td class="small text-center">12.8</td>
                                    <td class="small text-center">22.5</td>
                                    <td class="small text-center">68.2</td>
                                    <td class="small text-center">70.5</td>
                                    <td class="small text-center">3.7</td>
                                    <td class="small text-center">3</td>
                                    <td class="small text-center">1895</td>
                                    <td class="small text-center">19.8</td>
                                    <td class="small text-center">25</td>
                                </tr>
                                <tr>
                                    <td class="small fw-semibold">Jan 01, 2024</td>
                                    <td class="small text-center">77</td>
                                    <td class="small text-center">14.5</td>
                                    <td class="small text-center">23.2</td>
                                    <td class="small text-center">65.8</td>
                                    <td class="small text-center">68.2</td>
                                    <td class="small text-center">3.6</td>
                                    <td class="small text-center">4</td>
                                    <td class="small text-center">1865</td>
                                    <td class="small text-center">18.9</td>
                                    <td class="small text-center">27</td>
                                </tr>
                                <tr>
                                    <td class="small fw-semibold">Dec 25, 2023</td>
                                    <td class="small text-center">79</td>
                                    <td class="small text-center">16.8</td>
                                    <td class="small text-center">23.9</td>
                                    <td class="small text-center">63.2</td>
                                    <td class="small text-center">66.7</td>
                                    <td class="small text-center">3.5</td>
                                    <td class="small text-center">5</td>
                                    <td class="small text-center">1845</td>
                                    <td class="small text-center">18.2</td>
                                    <td class="small text-center">29</td>
                                </tr>
                                <tr>
                                    <td class="small fw-semibold">Dec 18, 2023</td>
                                    <td class="small text-center">82</td>
                                    <td class="small text-center">19.2</td>
                                    <td class="small text-center">24.6</td>
                                    <td class="small text-center">60.8</td>
                                    <td class="small text-center">64.7</td>
                                    <td class="small text-center">3.4</td>
                                    <td class="small text-center">7</td>
                                    <td class="small text-center">1820</td>
                                    <td class="small text-center">17.6</td>
                                    <td class="small text-center">31</td>
                                </tr>
                                <tr>
                                    <td class="small fw-semibold">Dec 11, 2023</td>
                                    <td class="small text-center">84</td>
                                    <td class="small text-center">21.5</td>
                                    <td class="small text-center">25.2</td>
                                    <td class="small text-center">58.5</td>
                                    <td class="small text-center">63</td>
                                    <td class="small text-center">3.4</td>
                                    <td class="small text-center">8</td>
                                    <td class="small text-center">1795</td>
                                    <td class="small text-center">17.1</td>
                                    <td class="small text-center">33</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
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
    });
</script>
@endsection
