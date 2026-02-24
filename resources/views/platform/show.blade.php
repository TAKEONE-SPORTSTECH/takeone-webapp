@extends('layouts.app')

@section('title', $club->club_name)

@push('styles')
@if($club->logo)
<link rel="icon" type="image/png" href="{{ asset('storage/' . $club->logo) }}">
@endif
<style>main { overflow-x: hidden; }</style>
@endpush

@section('content')
@php
    // --- YouTube video --- (gallery tab setting takes priority over social links)
    $youtubeVideoId = null;
    if ($club->youtube_url && preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $club->youtube_url, $matches)) {
        $youtubeVideoId = $matches[1];
    }
    if (!$youtubeVideoId) {
        foreach ($club->socialLinks as $link) {
            if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $link->url ?? '', $matches)) {
                $youtubeVideoId = $matches[1];
                break;
            }
        }
    }

    // --- Hero slides ---
    $heroSlides = collect();
    if ($club->cover_image) {
        $heroSlides->push(asset('storage/' . $club->cover_image));
    }
    foreach ($club->galleryImages as $img) {
        $heroSlides->push(asset('storage/' . $img->image_path));
    }
    // Test fallback images (sports/martial arts themed)
    if ($heroSlides->isEmpty()) {
        $heroSlides = collect([
            'https://images.unsplash.com/photo-1549060279-7e168fcee0c2?auto=format&fit=crop&w=1920&q=80',
            'https://images.unsplash.com/photo-1571019613454-1cb2f99b2d8b?auto=format&fit=crop&w=1920&q=80',
            'https://images.unsplash.com/photo-1534438327276-14e5300c3a48?auto=format&fit=crop&w=1920&q=80',
            'https://images.unsplash.com/photo-1540497077202-7c8a3999166f?auto=format&fit=crop&w=1920&q=80',
            'https://images.unsplash.com/photo-1574680096145-d05b474e2155?auto=format&fit=crop&w=1920&q=80',
        ]);
    }
@endphp
<div class="page-container">
    {{-- HERO BANNER --}}
    <div class="hero-banner" id="heroBanner">
        <div class="hero-bg-image" id="heroSlider">
            @foreach($heroSlides as $i => $slide)
            <div class="hero-bg-slide {{ $i === 0 ? 'active' : '' }}"
                 style="background-image: url('{{ $slide }}');"></div>
            @endforeach
        </div>
        @if($youtubeVideoId)
        <div class="hero-bg-video">
            <div id="heroVideoIframe"></div>
        </div>
        @endif

        {{-- Banner Top: Logo + Social Links --}}
        <div class="banner-top">
            <div>
                @if($club->logo)
                <div class="club-logo-wrapper">
                    <img src="{{ asset('storage/' . $club->logo) }}" alt="{{ $club->club_name }}" style="width:100%">
                </div>
                @endif
            </div>
            <div class="glass-hub">
                @foreach($club->socialLinks as $link)
                    @php
                        $icon = 'bi-link-45deg';
                        $platform = strtolower($link->platform);
                        if (str_contains($platform, 'whatsapp')) $icon = 'bi-whatsapp';
                        elseif (str_contains($platform, 'instagram')) $icon = 'bi-instagram';
                        elseif (str_contains($platform, 'facebook')) $icon = 'bi-facebook';
                        elseif (str_contains($platform, 'twitter') || str_contains($platform, 'x')) $icon = 'bi-twitter-x';
                        elseif (str_contains($platform, 'youtube')) $icon = 'bi-youtube';
                        elseif (str_contains($platform, 'tiktok')) $icon = 'bi-tiktok';
                        elseif (str_contains($platform, 'linkedin')) $icon = 'bi-linkedin';
                    @endphp
                    <a href="{{ $link->url }}" target="_blank" class="hub-link" title="{{ $link->platform }}">
                        <i class="bi {{ $icon }}"></i>
                    </a>
                @endforeach
                @if($club->phone)
                <a href="tel:{{ is_array($club->phone) ? (($club->phone['code'] ?? '') . ($club->phone['number'] ?? '')) : $club->phone }}" class="hub-link" title="Call">
                    <i class="bi bi-telephone"></i>
                </a>
                @endif
            </div>
        </div>

        {{-- Banner Bottom: Club Info + Stats --}}
        <div class="banner-bottom">
            <h1 class="text-2xl md:text-4xl font-extrabold text-white mb-2 uppercase">{{ $club->club_name }}</h1>
            <p class="text-white/50 text-lg">
                @if($club->address)
                <i class="bi bi-geo-alt-fill mr-2 text-primary"></i>{{ $club->address }}
                @endif
                @if($club->established_date)
                <i class="bi bi-fire ml-3 mr-2 text-primary"></i>
                <span>Since {{ \Carbon\Carbon::parse($club->established_date)->format('Y') }} &middot; {{ \Carbon\Carbon::parse($club->established_date)->diffInYears(now()) }} years</span>
                @endif
            </p>
            <div class="stats-row mt-4">
                <div class="stat-item">
                    <h3>{{ number_format($averageRating, 1) }}/5</h3>
                    <span>Rating</span>
                </div>
                <div class="stat-item">
                    <h3>{{ $club->peak_hours ?? '24/7' }}</h3>
                    <span>Access</span>
                </div>
                <div class="stat-item">
                    <h3>{{ $club->activities->count() }}+</h3>
                    <span>Classes</span>
                </div>
                <div class="stat-item">
                    <h3>{{ $activeMembersCount }}+</h3>
                    <span>Active Members</span>
                </div>
            </div>
        </div>
    </div>

    {{-- CONTENT CARD WITH TABS --}}
    <div class="content-card">
        {{-- Tab Navigation --}}
        <ul class="nav nav-pills nav-fill" id="mainTabs">
            <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#tab-overview">Overview</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-packages">Packages</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-scheduled">Schedule</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-events">Events</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-stats">Statistics</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-timeline">Timeline</button></li>
        </ul>

        <div class="tab-content">

            {{-- ==================== OVERVIEW TAB ==================== --}}
            <div class="tab-pane fade show active" id="tab-overview">

                {{-- Member Perks --}}
                <div class="mb-12">
                    <div class="flex justify-between items-end mb-4">
                        <div>
                            <h4 class="text-xl font-extrabold mb-1">Member Exclusive Perks</h4>
                            <p class="text-muted-foreground text-sm">Partnering businesses and offers available when you register.</p>
                        </div>
                    </div>
                    <div class="perks-grid grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div class="perk-card">
                            <span class="perk-badge">-20% OFF</span>
                            <div class="w-full h-full bg-gradient-to-br from-amber-400 to-orange-500 flex items-center justify-center">
                                <i class="bi bi-cup-hot text-white text-5xl"></i>
                            </div>
                            <div class="perk-overlay">
                                <h5 class="text-white font-bold mb-0">Partner Cafe</h5>
                                <p class="text-white/50 text-sm mb-0">Post-workout nutrition & coffee</p>
                            </div>
                        </div>
                        <div class="perk-card">
                            <span class="perk-badge">-15% OFF</span>
                            <div class="w-full h-full bg-gradient-to-br from-blue-400 to-cyan-500 flex items-center justify-center">
                                <i class="bi bi-bandaid text-white text-5xl"></i>
                            </div>
                            <div class="perk-overlay">
                                <h5 class="text-white font-bold mb-0">Physio Clinic</h5>
                                <p class="text-white/50 text-sm mb-0">Recovery & Sports Massage</p>
                            </div>
                        </div>
                        <div class="perk-card">
                            <span class="perk-badge">+500 PTS</span>
                            <div class="w-full h-full bg-gradient-to-br from-green-400 to-emerald-500 flex items-center justify-center">
                                <i class="bi bi-check2-circle text-white text-5xl"></i>
                            </div>
                            <div class="perk-overlay">
                                <h5 class="text-white font-bold mb-0">Daily Check-in</h5>
                                <p class="text-white/50 text-sm mb-0">Earn points for every workout</p>
                            </div>
                        </div>
                    </div>
                </div>

                <hr class="my-8 opacity-10">

                {{-- Trainers & Facilities --}}
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    {{-- Trainers --}}
                    <div>
                        <div class="flex justify-between items-end mb-4">
                            <div>
                                <h4 class="text-xl font-extrabold mb-1">Meet The Experts</h4>
                                <p class="text-muted-foreground text-sm">Our trainers with exceptional expertise</p>
                            </div>
                        </div>

                        @forelse($club->instructors as $instructor)
                        @php $trainerUser = $instructor->user; @endphp
                        <a href="{{ $trainerUser ? route('trainer.show.public', $instructor->id) : '#' }}" class="mini-trainer mb-3 block no-underline text-foreground">
                            @if($trainerUser && $trainerUser->profile_picture)
                            <img src="{{ asset('storage/' . $trainerUser->profile_picture) }}" class="mini-pfp" alt="{{ $trainerUser->full_name ?? $trainerUser->name }}">
                            @else
                            <div class="mini-pfp-placeholder">{{ strtoupper(substr($trainerUser->name ?? 'T', 0, 1)) }}</div>
                            @endif
                            <div>
                                <h6 class="font-bold mb-1">{{ $trainerUser->full_name ?? $trainerUser->name ?? 'Trainer' }}</h6>
                                <div class="flex items-center mb-1" style="font-size:11px;">
                                    <span class="mr-1 text-yellow-400">
                                        @php $rating = $instructor->reviews->avg('rating') ?? 0; @endphp
                                        @for($i = 1; $i <= 5; $i++)
                                            @if($i <= floor($rating))
                                                <i class="bi bi-star-fill"></i>
                                            @elseif($i - $rating < 1 && $i - $rating > 0)
                                                <i class="bi bi-star-half"></i>
                                            @else
                                                <i class="bi bi-star"></i>
                                            @endif
                                        @endfor
                                    </span>
                                    <span class="text-muted-foreground">{{ number_format($rating, 1) }} &middot; {{ $instructor->reviews->count() }} reviews</span>
                                </div>
                                @if($instructor->role && $instructor->role !== 'Instructor')
                                <p class="text-muted-foreground text-sm mb-0">{{ $instructor->role }}</p>
                                @elseif($instructor->bio)
                                <p class="text-muted-foreground text-sm mb-0">{{ Str::limit($instructor->bio, 60) }}</p>
                                @endif
                            </div>
                        </a>
                        @empty
                        <p class="text-muted-foreground text-center py-8">No trainers listed yet</p>
                        @endforelse
                    </div>

                    {{-- Facilities --}}
                    <div>
                        <div class="flex justify-between items-end mb-4">
                            <div>
                                <h4 class="text-xl font-extrabold mb-1">Elite Facilities</h4>
                                <p class="text-muted-foreground text-sm">State-of-the-art training environments</p>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            @forelse($club->facilities as $facility)
                            <div>
                                @if($facility->photo)
                                <img src="{{ asset('storage/' . $facility->photo) }}" class="fac-preview" alt="{{ $facility->name }}">
                                @else
                                <div class="fac-placeholder">
                                    <i class="bi bi-building text-white text-3xl"></i>
                                </div>
                                @endif
                                <h6 class="font-bold mb-1 mt-2">{{ $facility->name }}</h6>
                                @if($facility->description)
                                <p class="text-muted-foreground text-xs">{{ Str::limit($facility->description, 50) }}</p>
                                @endif
                            </div>
                            @empty
                            <div class="col-span-2 text-center py-8">
                                <p class="text-muted-foreground">No facilities listed yet</p>
                            </div>
                            @endforelse
                        </div>
                    </div>
                </div>

                <hr class="my-8 opacity-10">

                {{-- Achievements --}}
                <div class="latest-achievements">
                    <div class="flex justify-between items-end mb-4">
                        <div>
                            <h4 class="text-xl font-extrabold mb-1">Latest Achievements</h4>
                            <p class="text-muted-foreground text-sm">Celebrating our champions and club milestones.</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <article class="achievement-card h-full">
                            <div class="achievement-image">
                                <div class="w-full h-full bg-gradient-to-br from-amber-500 to-orange-600"></div>
                                <span class="achievement-tag"><i class="bi bi-trophy mr-1"></i>Club Award</span>
                            </div>
                            <div class="achievement-body">
                                <h6 class="text-sm font-bold mb-1">Club of the Year</h6>
                                <p class="text-sm mb-0" style="color:#6b7280;">Awarded for overall performance and growth.</p>
                            </div>
                        </article>
                        <article class="achievement-card h-full">
                            <div class="achievement-image">
                                <div class="w-full h-full bg-gradient-to-br from-blue-500 to-indigo-600"></div>
                                <span class="achievement-tag"><i class="bi bi-award mr-1"></i>Tournament Medals</span>
                            </div>
                            <div class="achievement-body">
                                <h6 class="text-sm font-bold mb-1">Championship Medals</h6>
                                <p class="text-sm mb-0" style="color:#6b7280;">Team podium finishes across divisions.</p>
                            </div>
                        </article>
                        <article class="achievement-card h-full">
                            <div class="achievement-image">
                                <div class="w-full h-full bg-gradient-to-br from-violet-500 to-purple-700"></div>
                                <span class="achievement-tag"><i class="bi bi-star mr-1"></i>Student Success</span>
                            </div>
                            <div class="achievement-body">
                                <h6 class="text-sm font-bold mb-1">Student Promotions</h6>
                                <p class="text-sm mb-0" style="color:#6b7280;">Successful gradings this season.</p>
                            </div>
                        </article>
                    </div>
                </div>
            </div>

            {{-- ==================== PACKAGES TAB ==================== --}}
            <div class="tab-pane fade" id="tab-packages">
                @if($club->packages->count() > 0)
                <div class="grid-packages">
                    @foreach($club->packages as $package)
                    <div class="package-card">
                        <div class="package-img-wrapper">
                            @if($package->cover_image)
                            <img src="{{ asset('storage/' . $package->cover_image) }}" class="package-img" alt="{{ $package->name }}">
                            @else
                            <div class="w-full h-full bg-gradient-to-br from-primary to-primary/70 flex items-center justify-center">
                                <i class="bi bi-box text-white text-5xl"></i>
                            </div>
                            @endif
                            <div class="package-title-overlay">
                                <span class="package-title">{{ $package->name }}</span>
                            </div>
                        </div>
                        <div class="p-4 flex-grow">
                            <div class="flex flex-wrap gap-1 mb-3">
                                <span class="badge-pill bg-secondary-light">{{ $package->type ?? 'Package' }}</span>
                                @if($package->gender)
                                <span class="badge-pill"><i class="bi bi-people mr-1"></i>{{ ucfirst($package->gender) }}</span>
                                @endif
                                @if($package->age_min || $package->age_max)
                                <span class="badge-pill">{{ $package->age_min ?? '?' }}-{{ $package->age_max ?? '?' }}y</span>
                                @endif
                            </div>
                            <div class="mb-3">
                                @if($package->price)
                                <span class="text-2xl font-bold text-primary">{{ $club->currency ?? 'USD' }} {{ number_format($package->price, 0) }}</span>
                                @endif
                                @if($package->duration_months)
                                <span class="text-muted-foreground text-sm ml-2">
                                    <i class="bi bi-calendar mr-1"></i>{{ $package->duration_months }}mo
                                </span>
                                @endif
                            </div>

                            @if($package->packageActivities && $package->packageActivities->count() > 0)
                            <div class="pt-3 border-t border-gray-200">
                                <h6 class="text-sm font-bold mb-3">
                                    <i class="bi bi-box2 mr-2"></i>Included Activities ({{ $package->packageActivities->count() }})
                                </h6>
                                @foreach($package->packageActivities as $pa)
                                <div class="activity-item">
                                    <div class="flex justify-between items-start mb-2">
                                        <h6 class="text-sm font-bold mb-0">{{ $pa->activity->name ?? 'Activity' }}</h6>
                                        @if($pa->instructor)
                                        <div class="instructor-tag">
                                            @if($pa->instructor->photo)
                                            <img src="{{ asset('storage/' . $pa->instructor->photo) }}" class="instructor-img" alt="{{ $pa->instructor->name }}">
                                            @endif
                                            <span style="font-size: 9px; font-weight: 700; color: var(--color-primary);">{{ Str::before($pa->instructor->name, ' ') }}</span>
                                        </div>
                                        @endif
                                    </div>
                                    <div class="flex gap-3 text-muted-foreground" style="font-size: 10px;">
                                        @if($pa->activity && $pa->activity->duration_minutes)
                                        <span><i class="bi bi-clock mr-1"></i>{{ $pa->activity->duration_minutes }} min</span>
                                        @endif
                                        @if($pa->schedule)
                                        <span><i class="bi bi-calendar mr-1"></i>
                                            @if(is_array($pa->schedule))
                                                @foreach($pa->schedule as $sched)
                                                    {{ $sched['days'] ?? '' }}: {{ $sched['time_from'] ?? '' }} - {{ $sched['time_to'] ?? '' }}@if(!$loop->last), @endif
                                                @endforeach
                                            @endif
                                        </span>
                                        @endif
                                    </div>
                                </div>
                                @endforeach
                            </div>
                            @endif
                        </div>
                        <div class="p-4 pt-0">
                            <button class="w-full bg-primary text-white font-bold py-2 shadow-sm rounded-xl hover:bg-primary/90 transition-colors">
                                Select Package
                            </button>
                        </div>
                    </div>
                    @endforeach
                </div>
                @else
                <div class="text-center py-16">
                    <i class="bi bi-box text-muted-foreground text-5xl"></i>
                    <p class="text-lg font-medium mt-4">No packages available</p>
                    <p class="text-sm text-muted-foreground mt-2">Check back later for available packages</p>
                </div>
                @endif
            </div>

            {{-- ==================== SCHEDULE TAB ==================== --}}
            <div class="tab-pane fade" id="tab-scheduled">
                <div class="flex justify-between items-center mb-4">
                    <div>
                        <h4 class="text-xl font-extrabold mb-1">Today's Classes</h4>
                        <p class="text-muted-foreground text-sm">Live status, flexible sessions, and booking availability.</p>
                    </div>
                </div>

                @if($club->activities->count() > 0)
                <div class="max-w-3xl mx-auto">
                    @foreach($club->activities as $index => $activity)
                    @php
                        $statuses = ['ongoing', 'bookable', 'finished', 'full'];
                        $status = $statuses[$index % 4];
                        $statusLabels = ['ongoing' => 'Live now', 'bookable' => 'Open', 'finished' => 'Finished', 'full' => 'Full'];
                    @endphp
                    <div class="class-card" @if($status === 'finished') style="opacity:0.5;" @endif>
                        <div class="class-thumb">
                            @if($activity->picture_url)
                            <img src="{{ asset('storage/' . $activity->picture_url) }}" alt="{{ $activity->name }}">
                            @else
                            <div class="w-full h-full bg-gradient-to-br from-primary to-primary/70 flex items-center justify-center min-h-[80px]">
                                <i class="bi bi-activity text-white text-xl"></i>
                            </div>
                            @endif
                        </div>
                        <div class="flex-grow flex flex-col">
                            <div class="flex justify-between items-start mb-1">
                                <div>
                                    <h6 class="text-sm font-bold mb-0">{{ $activity->name }}</h6>
                                    <div class="class-meta text-muted-foreground">
                                        @if($activity->duration_minutes)
                                        <span><i class="bi bi-clock mr-1"></i>{{ $activity->duration_minutes }} min</span>
                                        @endif
                                        @if($activity->facility)
                                        <span class="ml-2"><i class="bi bi-geo-alt mr-1"></i>{{ $activity->facility->name }}</span>
                                        @endif
                                    </div>
                                </div>
                                <span class="status-chip status-{{ $status }}">
                                    @if($status === 'ongoing')
                                    <span class="inline-block w-1.5 h-1.5 rounded-full bg-green-600"></span>
                                    @elseif($status === 'full')
                                    <i class="bi bi-x-circle"></i>
                                    @elseif($status === 'finished')
                                    <i class="bi bi-clock"></i>
                                    @else
                                    <i class="bi bi-check-circle"></i>
                                    @endif
                                    {{ $statusLabels[$status] }}
                                </span>
                            </div>

                            <div class="flex justify-between items-center mt-2">
                                <div class="flex flex-wrap gap-1">
                                    @if($activity->packages->count() > 0)
                                    <span class="pill-tag">{{ $activity->packages->first()->name }}</span>
                                    @endif
                                    @if($activity->frequency_per_week)
                                    <span class="pill-tag">{{ $activity->frequency_per_week }}x/week</span>
                                    @endif
                                </div>
                                @if($status === 'ongoing' || $status === 'bookable')
                                <button class="bg-green-500 text-white text-xs font-semibold px-3 py-1 rounded-md">Book Spot</button>
                                @elseif($status === 'full')
                                <button class="border border-gray-300 text-gray-500 text-xs font-semibold px-3 py-1 rounded-md" disabled>Join Waitlist</button>
                                @endif
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
                @else
                <div class="text-center py-16">
                    <i class="bi bi-calendar-x text-muted-foreground text-5xl"></i>
                    <p class="text-lg font-medium mt-4">No classes scheduled</p>
                    <p class="text-sm text-muted-foreground mt-2">Check back later for available sessions</p>
                </div>
                @endif
            </div>

            {{-- ==================== EVENTS TAB ==================== --}}
            <div class="tab-pane fade" id="tab-events">
                <div class="flex justify-between items-center mb-4">
                    <div>
                        <h4 class="text-xl font-extrabold mb-1">Upcoming Events</h4>
                        <p class="text-muted-foreground text-sm">Open to everyone. Reserve your spot before it sells out.</p>
                    </div>
                    <button class="border border-gray-800 text-sm font-semibold rounded-full px-3 py-1">
                        <i class="bi bi-calendar-plus mr-1"></i> Event Calendar
                    </button>
                </div>

                <div class="events-lane">
                    {{-- Event 1 --}}
                    <div class="event-node" style="top: 10px;"></div>
                    <article class="event-card mb-4">
                        <div class="event-ribbon limited">Limited Seats</div>
                        <div class="event-header">
                            <div class="event-date-pill">
                                <div class="day">Thu</div>
                                <div class="date">19</div>
                                <div class="month">Feb</div>
                            </div>
                            <div class="event-body-main">
                                <div class="event-title">Open Sparring Night - All Clubs Welcome</div>
                                <div class="event-meta mb-1">
                                    <span class="mr-3"><i class="bi bi-clock"></i> 7:30 PM - 9:30 PM</span>
                                    <span class="mr-3"><i class="bi bi-geo-alt"></i> Main Arena</span>
                                    <span><i class="bi bi-bar-chart"></i> Intermediate & Above</span>
                                </div>
                                <p class="text-xs text-gray-500 mb-0 mt-1">
                                    High-energy sparring rounds with referees, music and live scoring. Bring your gear, we bring the atmosphere.
                                </p>
                            </div>
                        </div>
                        <div class="event-chip-row">
                            <span class="event-chip"><i class="bi bi-people"></i> Public event</span>
                            <span class="event-chip"><i class="bi bi-shield"></i> WT rules</span>
                            <span class="event-chip"><i class="bi bi-camera"></i> Highlight reels</span>
                        </div>
                        <div class="event-footer">
                            <div class="event-capacity">
                                <i class="bi bi-people"></i>
                                <span>24 / 30 spots taken</span>
                                <div class="capacity-bar"><div class="capacity-fill" style="width:80%;"></div></div>
                            </div>
                            <button class="event-cta-open"><i class="bi bi-ticket"></i> Join Event</button>
                        </div>
                    </article>

                    {{-- Event 2 --}}
                    <div class="event-node" style="top: 250px;"></div>
                    <article class="event-card mb-4">
                        <div class="event-ribbon">Family Friendly</div>
                        <div class="event-header">
                            <div class="event-date-pill" style="background:#16a34a;">
                                <div class="day">Sat</div>
                                <div class="date">21</div>
                                <div class="month">Feb</div>
                            </div>
                            <div class="event-body-main">
                                <div class="event-title">Free Beginner Try-Out Day</div>
                                <div class="event-meta mb-1">
                                    <span class="mr-3"><i class="bi bi-clock"></i> 10:00 AM - 1:00 PM</span>
                                    <span class="mr-3"><i class="bi bi-geo-alt"></i> Dojo & Lobby</span>
                                    <span><i class="bi bi-person"></i> Ages 5+</span>
                                </div>
                                <p class="text-xs text-gray-500 mb-0 mt-1">
                                    Open doors for anyone curious. Meet the coaches, try a safe intro class, and tour the facility.
                                </p>
                            </div>
                        </div>
                        <div class="event-chip-row">
                            <span class="event-chip"><i class="bi bi-check-circle"></i> No experience needed</span>
                            <span class="event-chip"><i class="bi bi-cup-hot"></i> Coffee & snacks</span>
                            <span class="event-chip"><i class="bi bi-house"></i> Parents welcome</span>
                        </div>
                        <div class="event-footer">
                            <div class="event-capacity">
                                <i class="bi bi-infinity"></i>
                                <span>Unlimited guests</span>
                            </div>
                            <button class="event-cta-open" style="background:#0f172a;"><i class="bi bi-person-plus"></i> I'm Interested</button>
                        </div>
                    </article>

                    {{-- Event 3 --}}
                    <div class="event-node" style="top: 490px;"></div>
                    <article class="event-card mb-4">
                        <div class="event-ribbon limited">Grading</div>
                        <div class="event-header">
                            <div class="event-date-pill" style="background:#f97316;">
                                <div class="day">Fri</div>
                                <div class="date">28</div>
                                <div class="month">Feb</div>
                            </div>
                            <div class="event-body-main">
                                <div class="event-title">Monthly Belt Grading - Kids & Juniors</div>
                                <div class="event-meta mb-1">
                                    <span class="mr-3"><i class="bi bi-clock"></i> 5:00 PM - 8:00 PM</span>
                                    <span class="mr-3"><i class="bi bi-geo-alt"></i> Main Dojang</span>
                                    <span><i class="bi bi-person-badge"></i> Invite & open spectators</span>
                                </div>
                                <p class="text-xs text-gray-500 mb-0 mt-1">
                                    Formal grading with photo booth and awards stage. Families and friends are invited to cheer.
                                </p>
                            </div>
                        </div>
                        <div class="event-chip-row">
                            <span class="event-chip"><i class="bi bi-award"></i> White to Red Stripe</span>
                            <span class="event-chip"><i class="bi bi-camera-reels"></i> Professional photography</span>
                            <span class="event-chip"><i class="bi bi-star"></i> Medal ceremony</span>
                        </div>
                        <div class="event-footer">
                            <div class="event-capacity">
                                <i class="bi bi-people"></i>
                                <span>48 / 50 candidates</span>
                                <div class="capacity-bar"><div class="capacity-fill" style="width:96%;"></div></div>
                            </div>
                            <button class="event-cta-open" style="background:#f97316;"><i class="bi bi-clipboard-check"></i> Reserve Slot</button>
                        </div>
                    </article>

                    {{-- Event 4 --}}
                    <div class="event-node" style="top: 730px;"></div>
                    <article class="event-card mb-2">
                        <div class="event-ribbon limited">Almost Full</div>
                        <div class="event-header">
                            <div class="event-date-pill" style="background:#0369a1;">
                                <div class="day">Mon</div>
                                <div class="date">02</div>
                                <div class="month">Mar</div>
                            </div>
                            <div class="event-body-main">
                                <div class="event-title">Summer Camp 2026 - Preview Session</div>
                                <div class="event-meta mb-1">
                                    <span class="mr-3"><i class="bi bi-clock"></i> 6:30 PM - 8:00 PM</span>
                                    <span class="mr-3"><i class="bi bi-geo-alt"></i> Mixed Zones</span>
                                    <span><i class="bi bi-bicycle"></i> Camp activities demo</span>
                                </div>
                                <p class="text-xs text-gray-500 mb-0 mt-1">
                                    One-night preview of our signature summer camp: games, team-building, mini-workouts and Q&A.
                                </p>
                            </div>
                        </div>
                        <div class="event-chip-row">
                            <span class="event-chip"><i class="bi bi-fire"></i> Limited preview</span>
                            <span class="event-chip"><i class="bi bi-mortarboard"></i> Ideal for 8-14 yrs</span>
                        </div>
                        <div class="event-footer">
                            <div class="event-capacity">
                                <i class="bi bi-people"></i>
                                <span>Full &middot; Waitlist only</span>
                                <div class="capacity-bar"><div class="capacity-fill" style="width:100%;"></div></div>
                            </div>
                            <button class="event-cta-wait"><i class="bi bi-clock-history"></i> Join Waitlist</button>
                        </div>
                    </article>
                </div>
            </div>

            {{-- ==================== STATISTICS TAB ==================== --}}
            <div class="tab-pane fade" id="tab-stats">
                <div class="flex justify-between items-center mb-4">
                    <div>
                        <h4 class="text-xl font-extrabold mb-1">Club Statistics</h4>
                        <p class="text-muted-foreground text-sm">Who trains with us, how they train, and how the club is growing.</p>
                    </div>
                    <span class="bg-gray-900 text-white text-xs font-semibold rounded-full px-3 py-2 flex items-center gap-2">
                        <i class="bi bi-bar-chart text-yellow-400"></i>
                        Live snapshot &middot; {{ now()->format('M Y') }}
                    </span>
                </div>

                {{-- Row 1: Nationality, Age, Gender --}}
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-3">
                    {{-- Nationality --}}
                    <div class="stat-card">
                        <h6 class="mb-0 flex items-center justify-between text-sm font-bold">
                            Active Members by Nationality
                        </h6>
                        <div class="mt-3">
                            <canvas id="donutNationalities" height="160"></canvas>
                        </div>
                        <ul class="stat-legend">
                            @php $natColors = ['#ef4444', '#0ea5e9', '#22c55e', '#8b5cf6']; $ni = 0; @endphp
                            @foreach($nationalityStats as $country => $count)
                            <li>
                                <span><span class="legend-dot" style="background:{{ $natColors[$ni % 4] }};"></span> {{ $country ?: 'Unknown' }}</span>
                                <span>{{ $totalMembers > 0 ? round($count / $totalMembers * 100) : 0 }}%</span>
                            </li>
                            @php $ni++; @endphp
                            @endforeach
                        </ul>
                    </div>

                    {{-- Age Groups --}}
                    <div class="stat-card">
                        <h6 class="mb-0 flex items-center justify-between text-sm font-bold">
                            Members by Age Group
                        </h6>
                        <div class="mt-3">
                            <canvas id="donutAgeGroups" height="160"></canvas>
                        </div>
                        <ul class="stat-legend">
                            @php $ageColors = ['#f97316', '#22c55e', '#3b82f6', '#94a3b8']; $ai = 0; @endphp
                            @foreach($ageGroups as $group => $count)
                            <li>
                                <span><span class="legend-dot" style="background:{{ $ageColors[$ai % 4] }};"></span> {{ $group }}</span>
                                <span>{{ $totalMembers > 0 ? round($count / $totalMembers * 100) : 0 }}%</span>
                            </li>
                            @php $ai++; @endphp
                            @endforeach
                        </ul>
                    </div>

                    {{-- Gender --}}
                    <div class="stat-card">
                        <h6 class="mb-0 flex items-center justify-between text-sm font-bold">
                            Gender Ratio
                        </h6>
                        <div class="mt-3">
                            <canvas id="donutGender" height="160"></canvas>
                        </div>
                        <ul class="stat-legend">
                            @php $genderColors = ['#3b82f6', '#ec4899', '#94a3b8']; $gi = 0; @endphp
                            @foreach($genderStats as $gender => $count)
                            <li>
                                <span><span class="legend-dot" style="background:{{ $genderColors[$gi % 3] }};"></span> {{ ucfirst($gender ?: 'Unknown') }}</span>
                                <span>{{ $totalMembers > 0 ? round($count / $totalMembers * 100) : 0 }}%</span>
                            </li>
                            @php $gi++; @endphp
                            @endforeach
                        </ul>
                    </div>
                </div>

                {{-- Row 2: Horoscope, Blood Type, Championships --}}
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-3">
                    {{-- Horoscope --}}
                    <div class="stat-card">
                        <h6 class="mb-0 flex items-center justify-between text-sm font-bold">
                            Active Members - Horoscope
                            <span class="badge-pill bg-secondary-light">For fun</span>
                        </h6>
                        <div class="mt-3">
                            <canvas id="donutHoroscope" height="160"></canvas>
                        </div>
                        <ul class="stat-legend">
                            @php $hColors = ['#6366f1', '#22c55e', '#0ea5e9', '#ec4899']; $hi = 0; @endphp
                            @foreach($horoscopeGroups as $element => $count)
                            <li>
                                <span><span class="legend-dot" style="background:{{ $hColors[$hi % 4] }};"></span> {{ $element }} signs</span>
                                <span>{{ $totalMembers > 0 ? round($count / $totalMembers * 100) : 0 }}%</span>
                            </li>
                            @php $hi++; @endphp
                            @endforeach
                        </ul>
                    </div>

                    {{-- Blood Type --}}
                    <div class="stat-card">
                        <h6 class="mb-0 flex items-center justify-between text-sm font-bold">
                            Members by Blood Type
                            <span class="badge-pill bg-secondary-light">Self-reported</span>
                        </h6>
                        <div class="mt-3">
                            <canvas id="donutBloodType" height="160"></canvas>
                        </div>
                        <ul class="stat-legend">
                            @php $btColors = ['#ef4444', '#f97316', '#22c55e', '#3b82f6']; $bi_idx = 0; @endphp
                            @foreach($bloodTypeStats as $type => $count)
                            <li>
                                <span><span class="legend-dot" style="background:{{ $btColors[$bi_idx % 4] }};"></span> {{ $type }}</span>
                                <span>{{ $totalMembers > 0 ? round($count / $totalMembers * 100) : 0 }}%</span>
                            </li>
                            @php $bi_idx++; @endphp
                            @endforeach
                        </ul>
                    </div>

                    {{-- Championships (placeholder) --}}
                    <div class="stat-card">
                        <h6 class="mb-0 flex items-center justify-between text-sm font-bold">
                            Members with Championships
                            <span class="badge-pill bg-secondary-light">Achievements</span>
                        </h6>
                        <div class="mt-3">
                            <canvas id="donutChampions" height="160"></canvas>
                        </div>
                        <ul class="stat-legend">
                            <li><span><span class="legend-dot" style="background:#22c55e;"></span> Medalists</span><span>18%</span></li>
                            <li><span><span class="legend-dot" style="background:#eab308;"></span> Podium finishes</span><span>24%</span></li>
                            <li><span><span class="legend-dot" style="background:#94a3b8;"></span> Competitors</span><span>28%</span></li>
                            <li><span><span class="legend-dot" style="background:#e5e7eb;"></span> Yet to compete</span><span>30%</span></li>
                        </ul>
                    </div>
                </div>

                {{-- Monthly Trend --}}
                <div class="grid grid-cols-1 gap-3">
                    <div class="stat-card">
                        <div class="flex justify-between items-center mb-2">
                            <h6 class="mb-0 text-sm font-bold">Active Members - Last 12 Months</h6>
                            <span class="bg-gray-900 text-white text-xs font-semibold rounded-full px-2 py-1 flex items-center gap-1">
                                <i class="bi bi-people"></i> 12-month trend
                            </span>
                        </div>
                        <p class="text-muted-foreground text-sm mb-2">Membership trends across seasons and holidays.</p>
                        <div class="bar-wrapper-fixed">
                            <canvas id="barMonthlyMembers"></canvas>
                        </div>
                    </div>
                </div>

                {{-- Rating Breakdown --}}
                <div class="rating-breakdown-card">
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-center">
                        <div class="md:col-span-3">
                            <div class="rating-main-score">{{ number_format($averageRating, 1) }}</div>
                            <div class="rating-stars">
                                @for($i = 1; $i <= 5; $i++)
                                    @if($i <= floor($averageRating))
                                        <i class="bi bi-star-fill"></i>
                                    @elseif($i - $averageRating < 1 && $i - $averageRating > 0)
                                        <i class="bi bi-star-half"></i>
                                    @else
                                        <i class="bi bi-star"></i>
                                    @endif
                                @endfor
                            </div>
                            <div class="rating-subtext mt-1">Based on {{ $reviews->count() }} verified member reviews</div>
                        </div>
                        <div class="md:col-span-6">
                            <div class="aspect-row">
                                <div class="aspect-label"><i class="bi bi-person-check text-green-500"></i> Trainers</div>
                                <div class="aspect-bar"><div class="aspect-fill" style="width:{{ min($averageRating * 20, 100) }}%;"></div></div>
                                <div class="aspect-score">{{ number_format($averageRating, 1) }}</div>
                            </div>
                            <div class="aspect-row">
                                <div class="aspect-label"><i class="bi bi-droplet text-sky-500"></i> Cleanliness</div>
                                <div class="aspect-bar"><div class="aspect-fill" style="width:{{ min(($averageRating - 0.1) * 20, 100) }}%;"></div></div>
                                <div class="aspect-score">{{ number_format(max($averageRating - 0.1, 0), 1) }}</div>
                            </div>
                            <div class="aspect-row">
                                <div class="aspect-label"><i class="bi bi-house text-indigo-400"></i> Comfort</div>
                                <div class="aspect-bar"><div class="aspect-fill" style="width:{{ min(($averageRating - 0.2) * 20, 100) }}%;"></div></div>
                                <div class="aspect-score">{{ number_format(max($averageRating - 0.2, 0), 1) }}</div>
                            </div>
                            <div class="aspect-row">
                                <div class="aspect-label"><i class="bi bi-bullseye text-amber-400"></i> Keeps on track</div>
                                <div class="aspect-bar"><div class="aspect-fill" style="width:{{ min(($averageRating - 0.1) * 20, 100) }}%;"></div></div>
                                <div class="aspect-score">{{ number_format(max($averageRating - 0.1, 0), 1) }}</div>
                            </div>
                            <div class="aspect-row mb-0">
                                <div class="aspect-label"><i class="bi bi-heart text-rose-400"></i> Community vibe</div>
                                <div class="aspect-bar"><div class="aspect-fill" style="width:{{ min($averageRating * 20, 100) }}%;"></div></div>
                                <div class="aspect-score">{{ number_format($averageRating, 1) }}</div>
                            </div>
                        </div>
                        <div class="md:col-span-3">
                            <div class="rating-badge-row">
                                <span class="rating-badge"><i class="bi bi-emoji-smile text-yellow-400"></i> 9.7 / 10 enjoyment</span>
                                <span class="rating-badge"><i class="bi bi-shield-check text-green-500"></i> Members feel safe</span>
                                <span class="rating-badge"><i class="bi bi-stopwatch text-sky-500"></i> 92% better discipline</span>
                            </div>
                            <div class="rating-trend">
                                <i class="bi bi-graph-up-arrow"></i>
                                Rating up +0.2 vs last year
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ==================== TIMELINE TAB ==================== --}}
            <div class="tab-pane fade" id="tab-timeline">
                <div class="flex justify-between items-center mb-4">
                    <div>
                        <h4 class="text-xl font-extrabold mb-1">Club Timeline</h4>
                        <p class="text-muted-foreground text-sm">Daily moments, announcements, and highlights.</p>
                    </div>
                </div>

                <div class="news-timeline">
                    <div class="timeline-date"><span>{{ now()->format('d M Y') }}</span></div>

                    {{-- Post 1 --}}
                    <article class="news-card">
                        <span class="news-dot"></span>
                        <div class="news-header flex items-center gap-3">
                            @if($club->logo)
                            <img class="news-avatar" src="{{ asset('storage/' . $club->logo) }}" alt="{{ $club->club_name }}">
                            @else
                            <div class="w-[42px] h-[42px] rounded-full bg-primary text-white flex items-center justify-center font-bold">
                                {{ strtoupper(substr($club->club_name, 0, 1)) }}
                            </div>
                            @endif
                            <div>
                                <h6 class="mb-0 font-bold text-sm">{{ $club->club_name }}</h6>
                                <small class="text-muted-foreground">Just now &middot; {{ $club->address ?? 'Announcement' }}</small>
                            </div>
                        </div>
                        <div class="news-content">
                            @if($club->galleryImages->count() > 0)
                            <img class="news-img" src="{{ asset('storage/' . $club->galleryImages->first()->image_path) }}" alt="Club photo">
                            @endif
                            <p class="mb-2 text-sm">
                                What a season! Our team brought home incredible results. Proud of every athlete who stepped up and represented {{ $club->club_name }}.
                            </p>
                            <div class="news-actions">
                                <a href="#" class="news-action"><i class="bi bi-heart"></i> 142</a>
                                <a href="#" class="news-action"><i class="bi bi-chat"></i> 18</a>
                                <a href="#" class="news-action"><i class="bi bi-share"></i> Share</a>
                            </div>
                        </div>
                    </article>

                    {{-- Post 2 --}}
                    <article class="news-card">
                        <span class="news-dot"></span>
                        <div class="news-header flex items-center gap-3">
                            @if($club->logo)
                            <img class="news-avatar" src="{{ asset('storage/' . $club->logo) }}" alt="{{ $club->club_name }}">
                            @else
                            <div class="w-[42px] h-[42px] rounded-full bg-primary text-white flex items-center justify-center font-bold">
                                {{ strtoupper(substr($club->club_name, 0, 1)) }}
                            </div>
                            @endif
                            <div>
                                <h6 class="mb-0 font-bold text-sm">{{ $club->club_name }}</h6>
                                <small class="text-muted-foreground">1 hour ago &middot; Announcement</small>
                            </div>
                        </div>
                        <div class="news-content">
                            @if($club->galleryImages->count() > 1)
                            <img class="news-img" src="{{ asset('storage/' . $club->galleryImages->skip(1)->first()->image_path) }}" alt="Club photo">
                            @endif
                            <p class="mb-2 text-sm">
                                New beginners class launching next week. Build confidence, discipline, and quality time. Perfect for newcomers of all ages!
                            </p>
                            <div class="news-actions">
                                <a href="#" class="news-action"><i class="bi bi-heart"></i> 96</a>
                                <a href="#" class="news-action"><i class="bi bi-chat"></i> 12</a>
                                <a href="#" class="news-action"><i class="bi bi-share"></i> Share</a>
                            </div>
                        </div>
                    </article>

                    <div class="timeline-date"><span>{{ now()->subDay()->format('d M Y') }}</span></div>

                    {{-- Post 3 --}}
                    <article class="news-card">
                        <span class="news-dot"></span>
                        <div class="news-header flex items-center gap-3">
                            @if($club->logo)
                            <img class="news-avatar" src="{{ asset('storage/' . $club->logo) }}" alt="{{ $club->club_name }}">
                            @else
                            <div class="w-[42px] h-[42px] rounded-full bg-primary text-white flex items-center justify-center font-bold">
                                {{ strtoupper(substr($club->club_name, 0, 1)) }}
                            </div>
                            @endif
                            <div>
                                <h6 class="mb-0 font-bold text-sm">{{ $club->club_name }}</h6>
                                <small class="text-muted-foreground">Yesterday &middot; Highlight</small>
                            </div>
                        </div>
                        <div class="news-content">
                            @if($club->galleryImages->count() > 2)
                            <img class="news-img" src="{{ asset('storage/' . $club->galleryImages->skip(2)->first()->image_path) }}" alt="Club photo">
                            @endif
                            <p class="mb-2 text-sm">
                                Congratulations to our newest members who passed their assessments with distinction. Years of hard work paying off!
                            </p>
                            <div class="news-actions">
                                <a href="#" class="news-action"><i class="bi bi-heart"></i> 210</a>
                                <a href="#" class="news-action"><i class="bi bi-chat"></i> 34</a>
                                <a href="#" class="news-action"><i class="bi bi-share"></i> Share</a>
                            </div>
                        </div>
                    </article>

                    <div class="timeline-date"><span>{{ now()->subDays(2)->format('d M Y') }}</span></div>

                    {{-- Post 4 --}}
                    <article class="news-card">
                        <span class="news-dot"></span>
                        <div class="news-header flex items-center gap-3">
                            @if($club->logo)
                            <img class="news-avatar" src="{{ asset('storage/' . $club->logo) }}" alt="{{ $club->club_name }}">
                            @else
                            <div class="w-[42px] h-[42px] rounded-full bg-primary text-white flex items-center justify-center font-bold">
                                {{ strtoupper(substr($club->club_name, 0, 1)) }}
                            </div>
                            @endif
                            <div>
                                <h6 class="mb-0 font-bold text-sm">{{ $club->club_name }}</h6>
                                <small class="text-muted-foreground">2 days ago &middot; Community</small>
                            </div>
                        </div>
                        <div class="news-content">
                            <p class="mb-2 text-sm">
                                Reminder: Monthly grading is coming up. Please confirm your attendance with reception and ensure you've completed your attendance requirements.
                            </p>
                            <div class="news-actions">
                                <a href="#" class="news-action"><i class="bi bi-heart"></i> 52</a>
                                <a href="#" class="news-action"><i class="bi bi-chat"></i> 5</a>
                                <a href="#" class="news-action"><i class="bi bi-share"></i> Share</a>
                            </div>
                        </div>
                    </article>
                </div>
            </div>

        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function() {
    const slides = document.querySelectorAll('.hero-bg-slide');
    if (slides.length > 1) {
        let current = 0;
        setInterval(function() {
            slides[current].classList.remove('active');
            current = (current + 1) % slides.length;
            slides[current].classList.add('active');
        }, 4000);
    }
})();
</script>
@if($youtubeVideoId)
<script>
(function() {
    const VIDEO_ID = '{{ $youtubeVideoId }}';
    const banner = document.getElementById('heroBanner');
    let player;

    const tag = document.createElement('script');
    tag.src = 'https://www.youtube.com/iframe_api';
    document.head.appendChild(tag);

    window.onYouTubeIframeAPIReady = function() {
        player = new YT.Player('heroVideoIframe', {
            videoId: VIDEO_ID,
            width: '100%',
            height: '100%',
            playerVars: {
                autoplay: 1,
                controls: 0,
                loop: 1,
                mute: 1,
                playsinline: 1,
                modestbranding: 1,
                rel: 0,
                showinfo: 0,
                playlist: VIDEO_ID,
                origin: window.location.origin
            },
            events: {
                onReady: function(e) {
                    e.target.mute();
                    e.target.playVideo();
                    banner.classList.add('video-ready');
                },
                onStateChange: function(e) {
                    // Restart if video ends (fallback in case loop param doesn't work)
                    if (e.data === YT.PlayerState.ENDED) {
                        e.target.playVideo();
                    }
                }
            }
        });
    };
})();
</script>
@endif
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {

    // --- Chart Data from Controller ---
    const nationalityLabels = @json($nationalityStats->keys()->toArray());
    const nationalityData = @json($nationalityStats->values()->toArray());
    const ageLabels = @json(array_keys($ageGroups));
    const ageData = @json(array_values($ageGroups));
    const genderLabels = @json($genderStats->keys()->map(fn($g) => ucfirst($g ?: 'Unknown'))->toArray());
    const genderData = @json($genderStats->values()->toArray());
    const horoscopeLabels = @json(array_keys($horoscopeGroups));
    const horoscopeData = @json(array_values($horoscopeGroups));
    const bloodLabels = @json($bloodTypeStats->keys()->toArray());
    const bloodData = @json($bloodTypeStats->values()->toArray());
    const monthlyLabels = @json(array_keys($monthlyTrend));
    const monthlyData = @json(array_values($monthlyTrend));

    const donutOptions = {
        cutout: '65%',
        plugins: {
            legend: { display: false },
            tooltip: { callbacks: { label: ctx => `${ctx.label}: ${ctx.parsed}` } }
        }
    };

    // Nationality
    const natCtx = document.getElementById('donutNationalities');
    if (natCtx) {
        new Chart(natCtx, {
            type: 'doughnut',
            data: {
                labels: nationalityLabels,
                datasets: [{ data: nationalityData, backgroundColor: ['#ef4444','#0ea5e9','#22c55e','#8b5cf6'], borderWidth: 0 }]
            },
            options: donutOptions
        });
    }

    // Age Groups
    const ageCtx = document.getElementById('donutAgeGroups');
    if (ageCtx) {
        new Chart(ageCtx, {
            type: 'doughnut',
            data: {
                labels: ageLabels,
                datasets: [{ data: ageData, backgroundColor: ['#f97316','#22c55e','#3b82f6','#94a3b8'], borderWidth: 0 }]
            },
            options: donutOptions
        });
    }

    // Gender
    const genderCtx = document.getElementById('donutGender');
    if (genderCtx) {
        new Chart(genderCtx, {
            type: 'doughnut',
            data: {
                labels: genderLabels,
                datasets: [{ data: genderData, backgroundColor: ['#3b82f6','#ec4899','#94a3b8'], borderWidth: 0 }]
            },
            options: donutOptions
        });
    }

    // Horoscope
    const horoCtx = document.getElementById('donutHoroscope');
    if (horoCtx) {
        new Chart(horoCtx, {
            type: 'doughnut',
            data: {
                labels: horoscopeLabels,
                datasets: [{ data: horoscopeData, backgroundColor: ['#6366f1','#22c55e','#0ea5e9','#ec4899'], borderWidth: 0 }]
            },
            options: donutOptions
        });
    }

    // Blood Type
    const bloodCtx = document.getElementById('donutBloodType');
    if (bloodCtx) {
        new Chart(bloodCtx, {
            type: 'doughnut',
            data: {
                labels: bloodLabels,
                datasets: [{ data: bloodData, backgroundColor: ['#ef4444','#f97316','#22c55e','#3b82f6'], borderWidth: 0 }]
            },
            options: donutOptions
        });
    }

    // Championships (placeholder)
    const champCtx = document.getElementById('donutChampions');
    if (champCtx) {
        new Chart(champCtx, {
            type: 'doughnut',
            data: {
                labels: ['Medalists', 'Podium finishes', 'Competitors', 'Yet to compete'],
                datasets: [{ data: [18, 24, 28, 30], backgroundColor: ['#22c55e','#eab308','#94a3b8','#e5e7eb'], borderWidth: 0 }]
            },
            options: donutOptions
        });
    }

    // Monthly Bar Chart
    const barCtx = document.getElementById('barMonthlyMembers');
    if (barCtx) {
        new Chart(barCtx, {
            type: 'bar',
            data: {
                labels: monthlyLabels,
                datasets: [{
                    label: 'Members',
                    data: monthlyData,
                    backgroundColor: getComputedStyle(document.documentElement).getPropertyValue('--color-primary').trim(),
                    borderWidth: 0,
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: false,
                scales: {
                    x: { grid: { display: false }, ticks: { color: '#6b7280', font: { size: 11 } } },
                    y: { grid: { color: '#e5e7eb' }, ticks: { color: '#6b7280', font: { size: 11 }, stepSize: 10 } }
                },
                plugins: {
                    legend: { labels: { font: { size: 11 }, color: '#374151' } }
                }
            }
        });
    }
});
</script>
@endpush
