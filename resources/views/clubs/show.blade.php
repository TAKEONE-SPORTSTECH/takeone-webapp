@extends('layouts.tailwind')

@section('content')
<div class="min-h-screen bg-background">

    <!-- Header Bar with Logo and Club Info + Banner -->
    <div class="container mx-auto px-4 md:px-6 pt-4">
        <!-- Header Bar -->
        <div class="bg-gradient-to-r from-black/80 to-black/70 backdrop-blur-sm text-white border-2 border-white/20 p-4 md:p-6 rounded-t-lg border-b-0">
            <div class="flex items-start gap-4 md:gap-6">
                <!-- Logo -->
                @if($club->logo)
                <div class="flex-shrink-0 w-16 h-16 md:w-20 md:h-20 rounded-full border-2 border-white/20 shadow-md bg-black/50 overflow-hidden">
                    <img src="{{ asset('storage/' . $club->logo) }}"
                         alt="{{ $club->club_name }} logo"
                         class="w-full h-full object-contain">
                </div>
                @endif

                <!-- Club Info -->
                <div class="flex-1 min-w-0">
                    <!-- Club Name -->
                    <h1 class="text-xl md:text-3xl lg:text-4xl font-bold uppercase tracking-wide text-white">
                        {{ $club->club_name }}
                    </h1>

                    <!-- Separator Line -->
                    <div class="h-[2px] bg-gradient-to-r from-primary via-primary/50 to-transparent my-2 md:my-3"></div>

                    <!-- Slogan -->
                    @if($club->slogan)
                    <p class="text-sm md:text-base lg:text-lg text-white/80 font-medium">
                        {{ $club->slogan }}
                    </p>
                    @endif
                </div>

                <!-- Message Club Button -->
                <div class="flex-shrink-0">
                    <button class="bg-primary hover:bg-primary/90 text-white px-4 py-2 rounded-md font-medium flex items-center gap-2">
                        <i class="bi bi-chat"></i>
                        <span class="hidden sm:inline">Message Club</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Banner Image -->
        @if($club->cover_image || $club->galleryImages->count() > 0)
        <div class="relative border-2 border-t-0 border-white/20 overflow-hidden" style="height: 300px;">
            @if($club->cover_image)
            <img src="{{ asset('storage/' . $club->cover_image) }}"
                 alt="{{ $club->club_name }}"
                 class="w-full h-full object-cover">
            @elseif($club->galleryImages->first())
            <img src="{{ asset('storage/' . $club->galleryImages->first()->image_path) }}"
                 alt="{{ $club->club_name }}"
                 class="w-full h-full object-cover">
            @endif

            <!-- Rating Badge -->
            @if($averageRating > 0)
            <div class="absolute top-4 right-4">
                <span class="bg-yellow-400 text-black px-3 py-1 rounded-full font-bold flex items-center gap-1">
                    <i class="bi bi-star-fill"></i>
                    {{ number_format($averageRating, 1) }}
                </span>
            </div>
            @endif
        </div>
        @endif
    </div>

    <!-- Stats Bar -->
    <div class="container mx-auto px-4 md:px-6 -mt-2">
        <div class="bg-gradient-to-r from-black/80 to-black/70 backdrop-blur-sm text-white border-2 border-white/20 rounded-b-lg">
            <div class="py-4 px-4 md:py-6">
                <!-- Stats Grid -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-2 sm:gap-4">
                    <div class="flex items-center justify-center gap-2 sm:gap-3">
                        <i class="bi bi-people text-primary text-xl sm:text-2xl"></i>
                        <div>
                            <p class="text-xl sm:text-2xl font-bold">{{ $activeMembersCount }}</p>
                            <p class="text-[10px] sm:text-xs text-gray-300">Members</p>
                        </div>
                    </div>
                    <div class="flex items-center justify-center gap-2 sm:gap-3">
                        <i class="bi bi-box text-primary text-xl sm:text-2xl"></i>
                        <div>
                            <p class="text-xl sm:text-2xl font-bold">{{ $club->packages->count() }}</p>
                            <p class="text-[10px] sm:text-xs text-gray-300">Packages</p>
                        </div>
                    </div>
                    <div class="flex items-center justify-center gap-2 sm:gap-3">
                        <i class="bi bi-person-badge text-primary text-xl sm:text-2xl"></i>
                        <div>
                            <p class="text-xl sm:text-2xl font-bold">{{ $club->instructors->count() }}</p>
                            <p class="text-[10px] sm:text-xs text-gray-300">Trainers</p>
                        </div>
                    </div>
                    <div class="flex items-center justify-center gap-2 sm:gap-3">
                        <i class="bi bi-clock text-primary text-xl sm:text-2xl"></i>
                        <div>
                            <p class="text-xs sm:text-sm font-bold">{{ $club->peak_hours ?? 'Varies' }}</p>
                            <p class="text-[10px] sm:text-xs text-gray-300">Peak Hours</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tab Navigation -->
    <div class="bg-background border-b mt-8">
        <div class="container mx-auto px-4 md:px-6">
            <div class="w-full">
                <!-- Tab Buttons -->
                <div class="w-full flex justify-center bg-transparent border-0 h-auto p-0 flex-wrap gap-1 sm:gap-0" role="tablist">
                    <button class="tab-trigger rounded-none border-b-2 border-primary bg-transparent px-3 sm:px-6 md:px-8 py-2 sm:py-3 text-sm sm:text-base font-medium"
                            data-tab="overview" role="tab" aria-selected="true">
                        Overview
                    </button>
                    <button class="tab-trigger rounded-none border-b-2 border-transparent hover:border-primary/50 bg-transparent px-3 sm:px-6 md:px-8 py-2 sm:py-3 text-sm sm:text-base font-medium text-muted-foreground"
                            data-tab="packages" role="tab">
                        Packages
                    </button>
                    <button class="tab-trigger rounded-none border-b-2 border-transparent hover:border-primary/50 bg-transparent px-3 sm:px-6 md:px-8 py-2 sm:py-3 text-sm sm:text-base font-medium text-muted-foreground"
                            data-tab="schedule" role="tab">
                        <span class="hidden sm:inline">Today's Schedule</span>
                        <span class="sm:hidden">Today</span>
                    </button>
                    <button class="tab-trigger rounded-none border-b-2 border-transparent hover:border-primary/50 bg-transparent px-3 sm:px-6 md:px-8 py-2 sm:py-3 text-sm sm:text-base font-medium text-muted-foreground"
                            data-tab="statistics" role="tab">
                        <span class="hidden sm:inline">Statistics</span>
                        <span class="sm:hidden">Stats</span>
                    </button>
                </div>

                <!-- Tab Content -->
                <div class="py-8">
                    <!-- Overview Tab -->
                    <div class="tab-content space-y-8" id="tab-overview">

                        <!-- Club Introduction -->
                        @if($club->description)
                        <div class="rounded-lg border border-l-4 border-l-primary bg-gradient-to-r from-primary/5 to-transparent shadow-sm">
                            <div class="p-6">
                                <h3 class="flex items-center gap-2 text-lg font-semibold mb-4">
                                    <i class="bi bi-stars text-primary"></i>
                                    {{ $club->slogan ?? 'About Us' }}
                                </h3>
                                <p class="text-muted-foreground leading-relaxed">
                                    {{ $club->description }}
                                </p>
                            </div>
                        </div>
                        @endif

                        <!-- About Section -->
                        <div class="rounded-lg border bg-card shadow-sm">
                            <div class="p-6">
                                <h3 class="flex items-center gap-2 text-lg font-semibold mb-4">
                                    <i class="bi bi-info-circle text-primary"></i>
                                    About & Contact
                                </h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div class="space-y-4">
                                        <!-- Location -->
                                        @if($club->address)
                                        <div class="flex items-start gap-3">
                                            <div class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center flex-shrink-0">
                                                <i class="bi bi-geo-alt text-primary text-lg"></i>
                                            </div>
                                            <div>
                                                <p class="font-medium">Location</p>
                                                <p class="text-muted-foreground">{{ $club->address }}</p>
                                            </div>
                                        </div>
                                        @endif

                                        <!-- Map Link -->
                                        @if($club->gps_lat && $club->gps_long)
                                        <a href="https://www.google.com/maps?q={{ $club->gps_lat }},{{ $club->gps_long }}"
                                           target="_blank"
                                           class="inline-flex items-center gap-2 px-4 py-2 border border-primary text-primary rounded-md hover:bg-primary hover:text-white transition-colors">
                                            <i class="bi bi-map"></i>
                                            View on Map
                                        </a>
                                        @endif

                                        <!-- Email -->
                                        @if($club->email)
                                        <div class="flex items-start gap-3">
                                            <div class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center flex-shrink-0">
                                                <i class="bi bi-envelope text-primary text-lg"></i>
                                            </div>
                                            <div>
                                                <p class="font-medium">Email</p>
                                                <a href="mailto:{{ $club->email }}" class="text-primary hover:underline">{{ $club->email }}</a>
                                            </div>
                                        </div>
                                        @endif

                                        <!-- Phone -->
                                        @if($club->phone)
                                        <div class="flex items-start gap-3">
                                            <div class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center flex-shrink-0">
                                                <i class="bi bi-phone text-primary text-lg"></i>
                                            </div>
                                            <div>
                                                <p class="font-medium">Phone</p>
                                                <p class="text-muted-foreground">
                                                    @if(is_array($club->phone))
                                                        {{ implode(', ', $club->phone) }}
                                                    @else
                                                        {{ $club->phone }}
                                                    @endif
                                                </p>
                                            </div>
                                        </div>
                                        @endif
                                    </div>
                                    <div class="space-y-4">
                                        <!-- Owner Info -->
                                        @if($club->owner)
                                        <div class="flex items-start gap-3">
                                            <div class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center flex-shrink-0">
                                                <i class="bi bi-person text-primary text-lg"></i>
                                            </div>
                                            <div>
                                                <p class="font-medium">Owner</p>
                                                <p class="text-muted-foreground">{{ $club->owner->full_name }}</p>
                                            </div>
                                        </div>
                                        @if($club->owner->email)
                                        <div class="flex items-start gap-3">
                                            <div class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center flex-shrink-0">
                                                <i class="bi bi-envelope text-primary text-lg"></i>
                                            </div>
                                            <div>
                                                <p class="font-medium">Owner Email</p>
                                                <a href="mailto:{{ $club->owner->email }}" class="text-primary hover:underline">{{ $club->owner->email }}</a>
                                            </div>
                                        </div>
                                        @endif
                                        @if($club->owner->mobile_formatted)
                                        <div class="flex items-start gap-3">
                                            <div class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center flex-shrink-0">
                                                <i class="bi bi-telephone text-primary text-lg"></i>
                                            </div>
                                            <div>
                                                <p class="font-medium">Owner Phone</p>
                                                <p class="text-muted-foreground">{{ $club->owner->mobile_formatted }}</p>
                                            </div>
                                        </div>
                                        @endif
                                        @endif
                                    </div>
                                </div>

                                <!-- Social Links -->
                                @if($club->socialLinks->count() > 0)
                                <hr class="my-4 border-border">
                                <div class="flex gap-2 flex-wrap">
                                    @foreach($club->socialLinks as $link)
                                    @php
                                        $iconClass = 'bi-link-45deg';
                                        $platform = strtolower($link->platform);
                                        if (str_contains($platform, 'facebook')) $iconClass = 'bi-facebook';
                                        elseif (str_contains($platform, 'instagram')) $iconClass = 'bi-instagram';
                                        elseif (str_contains($platform, 'twitter') || str_contains($platform, 'x')) $iconClass = 'bi-twitter-x';
                                        elseif (str_contains($platform, 'youtube')) $iconClass = 'bi-youtube';
                                        elseif (str_contains($platform, 'tiktok')) $iconClass = 'bi-tiktok';
                                        elseif (str_contains($platform, 'linkedin')) $iconClass = 'bi-linkedin';
                                        elseif (str_contains($platform, 'whatsapp')) $iconClass = 'bi-whatsapp';
                                    @endphp
                                    <a href="{{ $link->url }}" target="_blank"
                                       class="inline-flex items-center gap-2 px-3 py-2 border rounded-md text-sm hover:bg-primary hover:text-white hover:border-primary transition-colors">
                                        <i class="bi {{ $iconClass }}"></i>
                                        {{ $link->platform }}
                                    </a>
                                    @endforeach
                                </div>
                                @endif
                            </div>
                        </div>

                        <!-- Two Column Layout: Trainers & Facilities -->
                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                            <!-- Trainers Section - 1/3 Width -->
                            <div class="lg:col-span-1">
                                <div class="rounded-lg border bg-card shadow-sm h-full">
                                    <div class="p-6">
                                        <h3 class="flex items-center gap-2 text-lg font-semibold mb-4">
                                            <i class="bi bi-award text-primary"></i>
                                            Our Expert Trainers
                                        </h3>
                                        @if($club->instructors->count() > 0)
                                        <div class="space-y-4">
                                            @foreach($club->instructors as $index => $instructor)
                                            <div class="rounded-lg border p-4 hover:shadow-lg transition-all cursor-pointer relative">
                                                @if($index === 0)
                                                <span class="absolute top-2 right-2 bg-primary text-white text-xs px-2 py-1 rounded">Owner</span>
                                                @endif
                                                <div class="flex items-center gap-4">
                                                    @if($instructor->photo)
                                                    <img src="{{ asset('storage/' . $instructor->photo) }}"
                                                         alt="{{ $instructor->name }}"
                                                         class="w-16 h-16 rounded-full object-cover border-2 border-primary/20">
                                                    @else
                                                    <div class="w-16 h-16 rounded-full bg-primary text-white flex items-center justify-center text-xl font-bold">
                                                        {{ strtoupper(substr($instructor->name, 0, 1)) }}
                                                    </div>
                                                    @endif
                                                    <div class="flex-1">
                                                        <h4 class="font-semibold">{{ $instructor->name }}</h4>
                                                        @if($instructor->specialty)
                                                        <p class="text-sm text-muted-foreground">{{ $instructor->specialty }}</p>
                                                        @endif
                                                        <p class="text-xs text-muted-foreground mt-1">No reviews yet</p>
                                                    </div>
                                                </div>
                                            </div>
                                            @endforeach
                                        </div>
                                        @else
                                        <p class="text-center text-muted-foreground py-8">No trainers listed</p>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <!-- Facilities Section - 2/3 Width -->
                            <div class="lg:col-span-2">
                                <div class="rounded-lg border bg-card shadow-sm h-full">
                                    <div class="p-6">
                                        <h3 class="flex items-center gap-2 text-lg font-semibold mb-4">
                                            <i class="bi bi-building text-primary"></i>
                                            Our Facilities
                                        </h3>
                                        @if($club->facilities->count() > 0)
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            @foreach($club->facilities as $facility)
                                            <div class="rounded-lg border overflow-hidden hover:shadow-lg transition-all">
                                                @if($facility->image)
                                                <div class="h-40 overflow-hidden">
                                                    <img src="{{ asset('storage/' . $facility->image) }}"
                                                         alt="{{ $facility->name }}"
                                                         class="w-full h-full object-cover hover:scale-110 transition-transform duration-300">
                                                </div>
                                                @endif
                                                <div class="p-4">
                                                    <h4 class="font-semibold mb-2">{{ $facility->name }}</h4>
                                                    @if($facility->description)
                                                    <p class="text-sm text-muted-foreground mb-3 line-clamp-2">{{ $facility->description }}</p>
                                                    @endif
                                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700">
                                                        Available
                                                    </span>
                                                </div>
                                            </div>
                                            @endforeach
                                        </div>
                                        @else
                                        <p class="text-center text-muted-foreground py-8">No facilities listed</p>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Member Reviews -->
                        <div class="rounded-lg border bg-card shadow-sm">
                            <div class="p-6">
                                <div class="flex items-center justify-between mb-4">
                                    <div>
                                        <h3 class="flex items-center gap-2 text-lg font-semibold">
                                            <i class="bi bi-chat-dots text-primary"></i>
                                            Member Reviews
                                        </h3>
                                        <p class="text-sm text-muted-foreground">{{ $reviews->count() }} {{ $reviews->count() === 1 ? 'review' : 'reviews' }}</p>
                                    </div>
                                    @if($reviews->count() > 6)
                                    <button class="text-primary hover:underline text-sm">See More Reviews</button>
                                    @endif
                                </div>
                                @if($reviews->count() > 0)
                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4">
                                    @foreach($reviews->take(6) as $review)
                                    <div class="rounded-lg border p-4 hover:shadow-md transition-shadow">
                                        <div class="flex items-start gap-3 mb-3">
                                            <div class="w-10 h-10 rounded-full bg-muted flex items-center justify-center">
                                                <i class="bi bi-person text-muted-foreground"></i>
                                            </div>
                                            <div class="flex-1">
                                                <h4 class="font-semibold text-sm">{{ $review->user->full_name ?? 'Anonymous' }}</h4>
                                                <div class="flex items-center gap-1 mt-1">
                                                    @for($i = 1; $i <= 5; $i++)
                                                    <i class="bi bi-star{{ $i <= $review->rating ? '-fill' : '' }} text-yellow-400 text-xs"></i>
                                                    @endfor
                                                </div>
                                            </div>
                                        </div>
                                        <p class="text-sm text-muted-foreground line-clamp-3">{{ $review->comment }}</p>
                                        <p class="text-xs text-muted-foreground mt-2">{{ $review->created_at->format('M d, Y') }}</p>
                                    </div>
                                    @endforeach
                                </div>
                                @else
                                <div class="text-center py-12">
                                    <i class="bi bi-chat-dots text-muted-foreground text-5xl"></i>
                                    <p class="text-muted-foreground mt-4">No reviews yet. Be the first to review!</p>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Packages Tab -->
                    <div class="tab-content space-y-6 hidden" id="tab-packages">
                        @if($club->packages->count() > 0)
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                            @foreach($club->packages as $package)
                            <div class="rounded-lg border bg-card shadow-sm overflow-hidden hover:shadow-lg transition-all group">
                                @if($package->image)
                                <div class="relative overflow-hidden">
                                    <img src="{{ asset('storage/' . $package->image) }}"
                                         alt="{{ $package->name }}"
                                         class="w-full h-44 object-cover group-hover:scale-105 transition-transform duration-300">
                                </div>
                                @else
                                <div class="w-full h-44 bg-gradient-to-br from-primary to-primary/70 flex items-center justify-center">
                                    <i class="bi bi-box text-white text-5xl"></i>
                                </div>
                                @endif
                                <div class="p-4">
                                    <h4 class="font-semibold text-lg mb-2">{{ $package->name }}</h4>
                                    @if($package->description)
                                    <p class="text-sm text-muted-foreground mb-3 line-clamp-2">{{ $package->description }}</p>
                                    @endif
                                    <div class="flex items-center justify-between mb-4">
                                        @if($package->price)
                                        <span class="text-2xl font-bold text-primary">{{ $club->currency ?? '$' }}{{ number_format($package->price, 2) }}</span>
                                        @endif
                                        @if($package->duration_months)
                                        <span class="bg-muted px-2 py-1 rounded text-xs">{{ $package->duration_months }} {{ $package->duration_months == 1 ? 'month' : 'months' }}</span>
                                        @endif
                                    </div>
                                    @if($package->activities->count() > 0)
                                    <div class="mb-4">
                                        <p class="text-xs text-muted-foreground mb-2">Activities included:</p>
                                        <div class="flex flex-wrap gap-1">
                                            @foreach($package->activities->take(3) as $activity)
                                            <span class="bg-primary/10 text-primary px-2 py-1 rounded text-xs">{{ $activity->name }}</span>
                                            @endforeach
                                            @if($package->activities->count() > 3)
                                            <span class="bg-muted px-2 py-1 rounded text-xs">+{{ $package->activities->count() - 3 }} more</span>
                                            @endif
                                        </div>
                                    </div>
                                    @endif
                                    <button class="w-full bg-primary text-white py-2 rounded-md hover:bg-primary/90 transition-colors flex items-center justify-center gap-2">
                                        <i class="bi bi-cart-plus"></i>
                                        Enroll Now
                                    </button>
                                </div>
                            </div>
                            @endforeach
                        </div>
                        @else
                        <div class="text-center py-12">
                            <i class="bi bi-box text-muted-foreground text-5xl"></i>
                            <p class="text-lg font-medium mt-4">No packages available</p>
                            <p class="text-sm text-muted-foreground mt-2">Check back later for available packages</p>
                        </div>
                        @endif
                    </div>

                    <!-- Schedule Tab -->
                    <div class="tab-content space-y-4 hidden" id="tab-schedule">
                        @if($club->activities->count() > 0)
                        @foreach($club->activities as $activity)
                        <div class="rounded-lg border-l-4 border-l-primary overflow-hidden hover:shadow-xl transition-all duration-300 bg-card group">
                            <div class="flex flex-col sm:flex-row">
                                <div class="relative w-full sm:w-48 h-40 sm:h-auto flex-shrink-0 overflow-hidden">
                                    <div class="w-full h-full bg-gradient-to-br from-primary to-primary/70 flex items-center justify-center min-h-[120px]">
                                        <i class="bi bi-activity text-white text-3xl"></i>
                                    </div>
                                    <div class="absolute top-3 right-3">
                                        <span class="bg-blue-500/90 text-white px-2 py-1 rounded text-xs backdrop-blur-sm">Available</span>
                                    </div>
                                </div>
                                <div class="flex-1 p-5 sm:p-6">
                                    <div class="flex items-start justify-between mb-3">
                                        <div class="flex-1">
                                            <h3 class="font-bold text-xl mb-1 group-hover:text-primary transition-colors">{{ $activity->name }}</h3>
                                            @if($activity->packages && $activity->packages->count() > 0)
                                            <div class="flex items-center gap-2 mb-2">
                                                <i class="bi bi-box text-primary"></i>
                                                <p class="text-sm font-medium text-primary">
                                                    {{ $activity->packages->pluck('name')->implode(', ') }}
                                                </p>
                                            </div>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="space-y-2 mb-3">
                                        @if($activity->duration_minutes)
                                        <div class="flex items-center gap-2">
                                            <div class="w-8 h-8 rounded-full bg-primary/10 flex items-center justify-center">
                                                <i class="bi bi-clock text-primary text-sm"></i>
                                            </div>
                                            <div>
                                                <p class="text-xs text-muted-foreground">Duration</p>
                                                <p class="text-sm font-bold">{{ $activity->duration_minutes }} minutes</p>
                                            </div>
                                        </div>
                                        @endif

                                        @if($activity->frequency_per_week)
                                        <div class="flex items-center gap-2">
                                            <div class="w-8 h-8 rounded-full bg-primary/10 flex items-center justify-center">
                                                <i class="bi bi-calendar-week text-primary text-sm"></i>
                                            </div>
                                            <div>
                                                <p class="text-xs text-muted-foreground">Frequency</p>
                                                <p class="text-sm font-bold">{{ $activity->frequency_per_week }}x per week</p>
                                            </div>
                                        </div>
                                        @endif

                                        @if($activity->facility)
                                        <div class="flex items-center gap-2">
                                            <div class="w-8 h-8 rounded-full bg-primary/10 flex items-center justify-center">
                                                <i class="bi bi-building text-primary text-sm"></i>
                                            </div>
                                            <div>
                                                <p class="text-xs text-muted-foreground">Location</p>
                                                <p class="text-sm font-medium">{{ $activity->facility->name }}</p>
                                            </div>
                                        </div>
                                        @endif
                                    </div>

                                    @if($activity->description)
                                    <p class="text-sm text-muted-foreground line-clamp-2 leading-relaxed mt-3">
                                        {{ $activity->description }}
                                    </p>
                                    @endif

                                    <div class="mt-4 pt-4 border-t">
                                        <button class="bg-primary text-white px-4 py-2 rounded-md hover:bg-primary/90 transition-colors flex items-center gap-2">
                                            <i class="bi bi-info-circle"></i>
                                            View Details
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endforeach
                        @else
                        <div class="rounded-lg border bg-card p-12 text-center">
                            <i class="bi bi-calendar-x text-muted-foreground text-5xl"></i>
                            <p class="text-lg font-medium mt-4">No activities available</p>
                            <p class="text-sm text-muted-foreground mt-2">Check back later or view our packages to see available activities</p>
                        </div>
                        @endif
                    </div>

                    <!-- Statistics Tab -->
                    <div class="tab-content hidden" id="tab-statistics">
                        <div class="rounded-lg border bg-card shadow-sm">
                            <div class="p-6">
                                <h3 class="flex items-center gap-2 text-lg font-semibold mb-6">
                                    <i class="bi bi-bar-chart text-primary"></i>
                                    Club Statistics
                                </h3>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div class="rounded-lg bg-gradient-to-br from-primary/10 to-primary/5 p-6 text-center border">
                                        <i class="bi bi-graph-up text-primary text-5xl mb-2"></i>
                                        <p class="text-3xl font-bold">{{ $activeMembersCount }}</p>
                                        <p class="text-sm text-muted-foreground">Active Members</p>
                                    </div>
                                    <div class="rounded-lg bg-gradient-to-br from-primary/10 to-primary/5 p-6 text-center border">
                                        <i class="bi bi-box text-primary text-5xl mb-2"></i>
                                        <p class="text-3xl font-bold">{{ $club->packages->count() }}</p>
                                        <p class="text-sm text-muted-foreground">Total Packages</p>
                                    </div>
                                    <div class="rounded-lg bg-gradient-to-br from-yellow-100 to-yellow-50 p-6 text-center border">
                                        <i class="bi bi-star-fill text-yellow-400 text-5xl mb-2"></i>
                                        <p class="text-3xl font-bold">{{ number_format($averageRating, 1) }}</p>
                                        <p class="text-sm text-muted-foreground">Average Rating</p>
                                    </div>
                                </div>

                                <!-- Additional Stats -->
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-6">
                                    <div class="rounded-lg border p-4 text-center">
                                        <i class="bi bi-person-badge text-primary text-2xl mb-2"></i>
                                        <p class="text-2xl font-bold">{{ $club->instructors->count() }}</p>
                                        <p class="text-xs text-muted-foreground">Trainers</p>
                                    </div>
                                    <div class="rounded-lg border p-4 text-center">
                                        <i class="bi bi-building text-primary text-2xl mb-2"></i>
                                        <p class="text-2xl font-bold">{{ $club->facilities->count() }}</p>
                                        <p class="text-xs text-muted-foreground">Facilities</p>
                                    </div>
                                    <div class="rounded-lg border p-4 text-center">
                                        <i class="bi bi-activity text-primary text-2xl mb-2"></i>
                                        <p class="text-2xl font-bold">{{ $club->activities->count() }}</p>
                                        <p class="text-xs text-muted-foreground">Activities</p>
                                    </div>
                                    <div class="rounded-lg border p-4 text-center">
                                        <i class="bi bi-chat-dots text-primary text-2xl mb-2"></i>
                                        <p class="text-2xl font-bold">{{ $reviews->count() }}</p>
                                        <p class="text-xs text-muted-foreground">Reviews</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const tabTriggers = document.querySelectorAll('.tab-trigger');
    const tabContents = document.querySelectorAll('.tab-content');

    tabTriggers.forEach(trigger => {
        trigger.addEventListener('click', function() {
            const targetTab = this.dataset.tab;

            // Update trigger styles
            tabTriggers.forEach(t => {
                t.classList.remove('border-primary', 'text-foreground');
                t.classList.add('border-transparent', 'text-muted-foreground');
            });
            this.classList.remove('border-transparent', 'text-muted-foreground');
            this.classList.add('border-primary', 'text-foreground');

            // Show/hide content
            tabContents.forEach(content => {
                if (content.id === 'tab-' + targetTab) {
                    content.classList.remove('hidden');
                } else {
                    content.classList.add('hidden');
                }
            });
        });
    });
});
</script>
@endpush
@endsection
