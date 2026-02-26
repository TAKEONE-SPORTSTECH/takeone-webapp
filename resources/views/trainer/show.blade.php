@extends('layouts.app')

@section('title', ($user->full_name ?? 'Trainer') . ' — ' . ($user->clubInstructors->first()?->tenant->club_name ?? 'Trainer'))

@push('styles')
@if($user->profile_picture)
<link rel="icon" type="image/png" href="{{ asset('storage/' . $user->profile_picture) }}">
@elseif($user->clubInstructors->first()?->tenant->logo)
<link rel="icon" type="image/png" href="{{ asset('storage/' . $user->clubInstructors->first()->tenant->logo) }}">
@endif
@if(request()->routeIs('trainer.show.public'))
<style>@media (max-width: 768px) { nav { display: none !important; } }</style>
@endif
@endpush

@section('content')
@php
    // $user is the primary model (User). Club context comes from their instructor records.
    $primaryInstructor = $user->clubInstructors->first();
    $club = $primaryInstructor?->tenant;
    $isMale = ($user->gender ?? '') === 'm';
    // $reviews and $stats are passed from the controller
@endphp

<div class="min-h-screen bg-gray-50" x-data="{ activeTab: 'about' }">
    <div class="max-w-6xl mx-auto p-4 space-y-6">

        {{-- Back Button --}}
        @if(!request()->routeIs('trainer.show.public'))
        <a href="{{ url()->previous() }}" class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-gray-800 transition-colors mb-2">
            <i class="bi bi-arrow-left"></i>
            <span>Back</span>
        </a>
        @endif

        {{-- ========== Header Card ========== --}}
        <div class="tf-card">
            <div class="flex flex-col md:flex-row gap-6">
                {{-- Avatar --}}
                <div class="relative flex-shrink-0">
                    @if($user->profile_picture)
                        <img src="{{ asset('storage/' . $user->profile_picture) }}"
                             alt="{{ $user->full_name }}"
                             class="w-32 h-32 md:w-48 md:h-48 rounded-full object-cover border-4 {{ $isMale ? 'border-blue-400/40' : 'border-purple-400/20' }}">
                    @else
                        <div class="w-32 h-32 md:w-48 md:h-48 rounded-full border-4 {{ $isMale ? 'border-blue-400/40 bg-blue-100' : 'border-purple-400/20 bg-purple-100' }} flex items-center justify-center">
                            <span class="text-4xl md:text-6xl font-bold {{ $isMale ? 'text-blue-600' : 'text-purple-600' }}">
                                {{ strtoupper(substr($user->full_name, 0, 1)) }}
                            </span>
                        </div>
                    @endif
                </div>

                <div class="flex-1">
                    <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4 mb-4">
                        <div>
                            <h1 class="text-3xl font-bold mb-2">{{ $user->full_name }}</h1>
                            <div class="flex items-center gap-4 mb-3 flex-wrap">
                                {{-- Rating --}}
                                <div class="flex items-center gap-1">
                                    <i class="bi bi-star-fill text-yellow-400"></i>
                                    <span class="font-semibold">{{ $stats['rating'] > 0 ? $stats['rating'] : 'N/A' }}</span>
                                    <span class="text-gray-500">({{ $stats['certifications'] }} certifications)</span>
                                </div>
                                {{-- Specialty Badge --}}
                                @if($primaryInstructor?->role)
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-purple-600 text-white">
                                    {{ $primaryInstructor->role }}
                                </span>
                                @endif
                                {{-- Experience Badge --}}
                                @if($user->experience_years)
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-700 border border-gray-200">
                                    {{ $user->experience_years }} {{ $user->experience_years == 1 ? 'year' : 'years' }} experience
                                </span>
                                @endif
                            </div>
                            @if($user->bio)
                                <p class="text-gray-500 mb-4">{{ $user->bio }}</p>
                            @endif
                            @if($club)
                                <div class="flex items-center gap-2 text-sm text-gray-500">
                                    <i class="bi bi-geo-alt"></i>
                                    <span>{{ $club->club_name }}</span>
                                </div>
                            @endif
                        </div>

                        <div class="flex flex-col gap-2">
                            <button class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors text-sm font-medium">
                                <i class="bi bi-calendar"></i>
                                Book Session
                            </button>
                            <button class="inline-flex items-center justify-center gap-2 px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors text-sm font-medium">
                                <i class="bi bi-chat"></i>
                                Message
                            </button>
                        </div>
                    </div>

                    {{-- Stats Grid --}}
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div class="text-center p-3 border rounded-lg {{ $isMale ? 'border-blue-200' : 'border-gray-200' }}">
                            <p class="text-2xl font-bold {{ $isMale ? 'text-blue-600' : 'text-purple-600' }}">{{ $stats['clients'] }}</p>
                            <p class="text-xs text-gray-500">Clients</p>
                        </div>
                        <div class="text-center p-3 border rounded-lg {{ $isMale ? 'border-blue-200' : 'border-gray-200' }}">
                            <p class="text-2xl font-bold {{ $isMale ? 'text-sky-600' : 'text-teal-600' }}">{{ $stats['sessions'] }}</p>
                            <p class="text-xs text-gray-500">Sessions</p>
                        </div>
                        <div class="text-center p-3 border rounded-lg {{ $isMale ? 'border-blue-200' : 'border-gray-200' }}">
                            <p class="text-2xl font-bold {{ $isMale ? 'text-indigo-600' : 'text-amber-600' }}">{{ $stats['rating'] }}</p>
                            <p class="text-xs text-gray-500">Rating</p>
                        </div>
                        <div class="text-center p-3 border rounded-lg {{ $isMale ? 'border-blue-200' : 'border-gray-200' }}">
                            <p class="text-2xl font-bold {{ $isMale ? 'text-blue-600' : 'text-purple-600' }}">{{ $stats['certifications'] }}</p>
                            <p class="text-xs text-gray-500">Certifications</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ========== Tabs ========== --}}
        <div>
            {{-- Tab Buttons --}}
            <div class="grid grid-cols-4 bg-gray-100 rounded-lg p-1 gap-1">
                <button @click="activeTab = 'about'"
                        :class="activeTab === 'about' ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500 hover:text-gray-700'"
                        class="py-2 px-4 rounded-md text-sm font-medium transition-all">
                    About
                </button>
                <button @click="activeTab = 'schedule'"
                        :class="activeTab === 'schedule' ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500 hover:text-gray-700'"
                        class="py-2 px-4 rounded-md text-sm font-medium transition-all">
                    Schedule
                </button>
                <button @click="activeTab = 'reviews'"
                        :class="activeTab === 'reviews' ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500 hover:text-gray-700'"
                        class="py-2 px-4 rounded-md text-sm font-medium transition-all">
                    Reviews
                </button>
                <button @click="activeTab = 'contact'"
                        :class="activeTab === 'contact' ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500 hover:text-gray-700'"
                        class="py-2 px-4 rounded-md text-sm font-medium transition-all">
                    Contact
                </button>
            </div>

            {{-- ===== ABOUT TAB ===== --}}
            <div x-show="activeTab === 'about'" x-cloak class="mt-6 space-y-6">

                {{-- Bio Card --}}
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="bg-gradient-to-br {{ $isMale ? 'from-blue-50/50 via-blue-100/30 to-blue-50/20' : 'from-purple-50 via-teal-50 to-amber-50' }} p-6 border-b">
                        <div class="flex items-start gap-4">
                            <div class="p-3 rounded-xl bg-white shadow-sm border">
                                <i class="bi bi-people text-xl text-purple-600"></i>
                            </div>
                            <div class="flex-1">
                                <h3 class="text-xl font-bold mb-2">About {{ $user->full_name }}</h3>
                                <p class="text-sm text-gray-500">Professional trainer dedicated to your fitness journey</p>
                            </div>
                        </div>
                    </div>

                    <div class="p-6 space-y-6">
                        {{-- Biography --}}
                        <div class="space-y-3">
                            <div class="flex items-center gap-2 mb-3">
                                <div class="h-1 w-8 bg-gradient-to-r {{ $isMale ? 'from-blue-500 to-sky-500' : 'from-purple-500 to-teal-500' }} rounded-full"></div>
                                <span class="text-sm font-semibold text-gray-500 uppercase tracking-wide">Biography</span>
                            </div>
                            <p class="text-gray-800 leading-relaxed text-base pl-10">
                                {{ $user->bio ?? 'No biography available.' }}
                            </p>
                        </div>

                        {{-- Info Grid --}}
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-4">
                            {{-- Specialty Card --}}
                            <div class="group relative overflow-hidden rounded-xl border bg-gradient-to-br {{ $isMale ? 'from-blue-100/50 to-blue-50/30' : 'from-purple-50 to-purple-100/50' }} p-5 hover:shadow-md transition-all duration-300">
                                <div class="absolute top-0 right-0 w-24 h-24 {{ $isMale ? 'bg-blue-500/10' : 'bg-purple-500/10' }} rounded-full -mr-12 -mt-12 transition-transform group-hover:scale-110"></div>
                                <div class="relative flex items-start gap-4">
                                    <div class="p-2.5 rounded-lg {{ $isMale ? 'bg-blue-100/50' : 'bg-purple-100' }}">
                                        <i class="bi bi-award text-lg {{ $isMale ? 'text-blue-600' : 'text-purple-600' }}"></i>
                                    </div>
                                    <div class="flex-1">
                                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Specialty</p>
                                        <p class="text-lg font-bold text-gray-900">{{ $primaryInstructor?->role ?? 'Trainer' }}</p>
                                    </div>
                                </div>
                            </div>

                            {{-- Experience Card --}}
                            <div class="group relative overflow-hidden rounded-xl border bg-gradient-to-br {{ $isMale ? 'from-sky-50/50 to-sky-100/30' : 'from-teal-50 to-teal-100/50' }} p-5 hover:shadow-md transition-all duration-300">
                                <div class="absolute top-0 right-0 w-24 h-24 {{ $isMale ? 'bg-blue-500/10' : 'bg-purple-500/10' }} rounded-full -mr-12 -mt-12 transition-transform group-hover:scale-110"></div>
                                <div class="relative flex items-start gap-4">
                                    <div class="p-2.5 rounded-lg {{ $isMale ? 'bg-sky-100/50' : 'bg-teal-100' }}">
                                        <i class="bi bi-graph-up text-lg {{ $isMale ? 'text-sky-600' : 'text-teal-600' }}"></i>
                                    </div>
                                    <div class="flex-1">
                                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Experience</p>
                                        <p class="text-lg font-bold text-gray-900">{{ $user->experience_years ?? 0 }} {{ ($user->experience_years ?? 0) == 1 ? 'year' : 'years' }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Classes Taught --}}
                        @if($activities->count() > 0)
                            <div class="space-y-4 pt-2">
                                <div class="flex items-center gap-2">
                                    <div class="h-1 w-8 bg-gradient-to-r from-amber-500 to-purple-500 rounded-full"></div>
                                    <span class="text-sm font-semibold text-gray-500 uppercase tracking-wide">Classes Taught</span>
                                </div>
                                <div class="flex flex-wrap gap-2.5 pl-10">
                                    @foreach($activities as $activity)
                                        <span class="inline-flex items-center gap-1.5 px-4 py-1.5 text-sm font-medium border-2 rounded-full hover:bg-purple-50 hover:border-purple-400 transition-all cursor-default">
                                            <i class="bi bi-activity text-sm"></i>
                                            {{ $activity->name }}
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Achievements Card --}}
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="bg-gradient-to-br {{ $isMale ? 'from-blue-50/50 via-blue-100/30 to-blue-50/20' : 'from-purple-50 via-teal-50 to-amber-50' }} p-6 border-b">
                        <div class="flex items-start gap-4">
                            <div class="p-3 rounded-xl bg-white shadow-sm border">
                                <i class="bi bi-award text-xl text-amber-500"></i>
                            </div>
                            <div class="flex-1">
                                <h3 class="text-xl font-bold mb-2">Achievements & Milestones</h3>
                                <p class="text-sm text-gray-500">Recognition for excellence and dedication</p>
                            </div>
                        </div>
                    </div>
                    <div class="p-6">
                        @php
                            $achievements = [
                                ['title' => 'Top Rated Trainer', 'icon' => 'bi-award', 'gradient' => $isMale ? 'from-blue-100/40 to-blue-50/20' : 'from-purple-100/50 to-purple-50', 'iconColor' => $isMale ? 'text-blue-600' : 'text-purple-600'],
                                ['title' => 'Sessions Completed', 'icon' => 'bi-activity', 'gradient' => $isMale ? 'from-sky-100/40 to-sky-50/20' : 'from-teal-100/50 to-teal-50', 'iconColor' => $isMale ? 'text-sky-600' : 'text-teal-600'],
                                ['title' => 'Client Favorite', 'icon' => 'bi-heart', 'gradient' => $isMale ? 'from-indigo-100/40 to-indigo-50/20' : 'from-amber-100/50 to-amber-50', 'iconColor' => $isMale ? 'text-indigo-600' : 'text-amber-600'],
                            ];
                        @endphp
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            @foreach($achievements as $achievement)
                                <div class="group relative overflow-hidden rounded-xl border bg-gradient-to-br {{ $achievement['gradient'] }} p-5 hover:shadow-lg transition-all duration-300 hover:-translate-y-1">
                                    <div class="absolute top-0 right-0 w-20 h-20 bg-gradient-to-br from-white/50 to-transparent rounded-full -mr-10 -mt-10 transition-transform group-hover:scale-150"></div>
                                    <div class="relative flex flex-col items-center text-center gap-3">
                                        <div class="w-14 h-14 rounded-full bg-white shadow-md flex items-center justify-center group-hover:scale-110 transition-transform">
                                            <i class="bi {{ $achievement['icon'] }} text-2xl {{ $achievement['iconColor'] }}"></i>
                                        </div>
                                        <p class="font-bold text-sm">{{ $achievement['title'] }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Certifications Card --}}
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="bg-gradient-to-br {{ $isMale ? 'from-blue-50/50 via-blue-100/30 to-blue-50/20' : 'from-purple-50 via-teal-50 to-amber-50' }} p-6 border-b">
                        <div class="flex items-start gap-4">
                            <div class="p-3 rounded-xl bg-white shadow-sm border">
                                <i class="bi bi-award text-xl text-teal-600"></i>
                            </div>
                            <div class="flex-1">
                                <h3 class="text-xl font-bold mb-2">Certifications & Credentials</h3>
                                <p class="text-sm text-gray-500">Professional qualifications verified for authenticity</p>
                            </div>
                        </div>
                    </div>
                    <div class="p-6">
                        @if(is_array($user->skills) && count($user->skills) > 0)
                            <div class="space-y-6">
                                @foreach($user->skills as $skill)
                                    <div class="group border rounded-xl overflow-hidden bg-gradient-to-br from-white {{ $isMale ? 'to-blue-50/30' : 'to-teal-50/30' }} hover:shadow-lg transition-all">
                                        <div class="p-5 space-y-4">
                                            <div class="mb-3">
                                                <h4 class="font-bold text-xl mb-1">{{ $skill }}</h4>
                                            </div>
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                <div class="flex items-center gap-3 p-3 bg-white border rounded-lg">
                                                    <div class="p-2 rounded-lg bg-purple-100">
                                                        <i class="bi bi-award text-purple-600"></i>
                                                    </div>
                                                    <div class="flex-1">
                                                        <p class="text-xs text-gray-500 font-medium uppercase tracking-wide">Certification</p>
                                                        <p class="font-semibold">{{ $skill }}</p>
                                                    </div>
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border border-gray-200">
                                                        Verified
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-center py-12">
                                <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-gray-100 flex items-center justify-center">
                                    <i class="bi bi-award text-2xl text-gray-400"></i>
                                </div>
                                <p class="text-gray-500">No certifications added yet</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- ===== SCHEDULE TAB ===== --}}
            @php
                $dayOrder = ['saturday','sunday','monday','tuesday','wednesday','thursday','friday'];
                $dayAbbr  = ['saturday'=>'Sat','sunday'=>'Sun','monday'=>'Mon','tuesday'=>'Tue','wednesday'=>'Wed','thursday'=>'Thu','friday'=>'Fri'];
                $todayKey = strtolower(now()->format('l'));
            @endphp
            <div x-show="activeTab === 'schedule'" x-cloak class="mt-6 space-y-6"
                 x-data="{ activeDay: '{{ $todayKey }}' }">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="bg-gradient-to-br {{ $isMale ? 'from-blue-50/50 via-blue-100/30 to-blue-50/20' : 'from-purple-50 via-teal-50 to-amber-50' }} p-6 border-b">
                        <div class="flex items-start gap-4">
                            <div class="p-3 rounded-xl bg-white shadow-sm border">
                                <i class="bi bi-clock text-xl text-blue-500"></i>
                            </div>
                            <div class="flex-1">
                                <h3 class="text-xl font-bold mb-2">Weekly Schedule</h3>
                                <p class="text-sm text-gray-500">Available training sessions throughout the week</p>
                            </div>
                        </div>
                    </div>
                    <div class="p-6">

                @if(count($scheduleSlots) > 0)
                {{-- Day filter chips --}}
                <div class="flex flex-wrap justify-center gap-2 mb-5">
                    <button type="button"
                            @click="activeDay = '{{ $todayKey }}'"
                            :class="activeDay === '{{ $todayKey }}' ? 'bg-primary text-white border-primary' : 'bg-white text-gray-600 border-gray-200 hover:border-primary hover:text-primary'"
                            class="px-5 py-2 rounded-full border text-sm font-semibold transition-colors">
                        Today
                    </button>
                    @foreach($dayOrder as $dayKey)
                    <button type="button"
                            @click="activeDay = '{{ $dayKey }}'"
                            :class="activeDay === '{{ $dayKey }}' ? 'bg-primary text-white border-primary' : 'bg-white text-gray-600 border-gray-200 hover:border-primary hover:text-primary'"
                            class="px-5 py-2 rounded-full border text-sm font-semibold transition-colors {{ $dayKey === $todayKey ? 'ring-2 ring-primary/30' : '' }}">
                        {{ $dayAbbr[$dayKey] }}
                    </button>
                    @endforeach
                </div>

                <div class="max-w-3xl mx-auto flex flex-col gap-3">
                    @foreach($scheduleSlots as $slot)
                    <div class="class-card"
                         x-data="{
                             start: '{{ $slot['start'] }}',
                             end: '{{ $slot['end'] }}',
                             get classStatus() {
                                 const now = new Date();
                                 const pad = n => String(n).padStart(2, '0');
                                 const nowStr = pad(now.getHours()) + ':' + pad(now.getMinutes());
                                 if (nowStr < this.start) return 'upcoming';
                                 if (nowStr <= this.end) return 'live';
                                 return 'finished';
                             }
                         }"
                         x-show="@js(array_map('strval', $slot['days'])).includes(activeDay)"
                         :class="activeDay === '{{ $todayKey }}' ? classStatus + '-card' : ''"
                         :style="activeDay === '{{ $todayKey }}' ? 'order:' + (classStatus === 'live' ? 0 : classStatus === 'upcoming' ? 1 : 2) : ''"
                         x-cloak>
                        <div class="class-thumb">
                            @if($slot['picture_url'])
                            <img src="{{ asset('storage/' . $slot['picture_url']) }}" alt="{{ $slot['activity_name'] }}" class="w-full h-full object-cover">
                            @else
                            <div class="w-full h-full bg-gradient-to-br from-primary to-primary/70 flex items-center justify-center min-h-[80px]">
                                <i class="bi bi-activity text-white text-xl"></i>
                            </div>
                            @endif
                        </div>
                        <div class="flex-grow flex flex-col">
                            <div class="flex justify-between items-start mb-1">
                                <div>
                                    <h6 class="text-base font-bold mb-0">{{ $slot['activity_name'] }}</h6>
                                    <div class="class-meta text-muted-foreground flex items-center gap-x-4 mt-0.5 text-sm">
                                        <span><i class="bi bi-clock mr-1"></i>{{ \Carbon\Carbon::parse($slot['start'])->format('g:i A') }} – {{ \Carbon\Carbon::parse($slot['end'])->format('g:i A') }}</span>
                                        <span class="flex items-center gap-1 ml-2"><i class="bi bi-stopwatch"></i>{{ $slot['duration'] }} min</span>
                                    </div>
                                    @if($slot['facility_name'])
                                    <div class="text-sm text-muted-foreground mt-0.5">
                                        <i class="bi bi-geo-alt mr-1"></i>{{ $slot['facility_name'] }}
                                    </div>
                                    @endif
                                </div>
                                {{-- Status badge + club link --}}
                                <div class="flex flex-col items-end gap-2 shrink-0">
                                    <div x-show="activeDay === '{{ $todayKey }}'">
                                        <span x-show="classStatus === 'live'" class="status-chip status-ongoing">
                                            <span class="live-dot"></span> Ongoing
                                        </span>
                                        <span x-show="classStatus === 'upcoming'" class="status-chip status-bookable">
                                            <i class="bi bi-clock-fill"></i> Upcoming
                                        </span>
                                        <span x-show="classStatus === 'finished'" class="status-chip status-finished">
                                            <i class="bi bi-check-circle-fill"></i> Finished
                                        </span>
                                    </div>
                                    @if($slot['club_name'])
                                    <a href="{{ $slot['club_slug'] ? route('clubs.show', $slot['club_slug']) : '#' }}"
                                       class="text-xs font-medium text-primary hover:underline flex items-center gap-1">
                                        <i class="bi bi-building"></i> {{ $slot['club_name'] }}
                                    </a>
                                    @endif
                                </div>
                            </div>
                            <div class="flex flex-wrap gap-1 mt-2">
                                @if($slot['package_name'])
                                <span class="pill-tag">{{ $slot['package_name'] }}</span>
                                @endif
                                @foreach($slot['days'] as $d)
                                <span class="pill-tag">{{ $dayAbbr[$d] ?? ucfirst(substr($d,0,3)) }}</span>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    @endforeach

                    {{-- Empty state for filtered day --}}
                    <div x-show="!@js(collect($scheduleSlots)->map(fn($s) => $s['days'])->flatten()->unique()->values()->toArray()).includes(activeDay)"
                         class="text-center py-16">
                        <i class="bi bi-calendar-x text-muted-foreground text-5xl"></i>
                        <p class="text-lg font-medium mt-4">No classes on this day</p>
                        <p class="text-sm text-muted-foreground mt-2">Try selecting a different day</p>
                    </div>
                </div>
                @else
                <div class="text-center py-12">
                    <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-gray-100 flex items-center justify-center">
                        <i class="bi bi-calendar-x text-2xl text-gray-400"></i>
                    </div>
                    <p class="text-gray-500">No classes scheduled</p>
                </div>
                @endif

                    </div>{{-- /card body --}}
                </div>{{-- /card --}}
            </div>

            {{-- ===== REVIEWS TAB ===== --}}
            <div x-show="activeTab === 'reviews'" x-cloak class="mt-6 space-y-6">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="bg-gradient-to-br {{ $isMale ? 'from-blue-50/50 via-blue-100/30 to-blue-50/20' : 'from-purple-50 via-teal-50 to-amber-50' }} p-6 border-b">
                        <div class="flex items-start gap-4">
                            <div class="p-3 rounded-xl bg-white shadow-sm border">
                                <i class="bi bi-chat text-xl text-teal-600"></i>
                            </div>
                            <div class="flex-1">
                                <h3 class="text-xl font-bold mb-2">Client Reviews</h3>
                                <p class="text-sm text-gray-500">What our clients say about their experience</p>
                            </div>
                        </div>
                    </div>
                    <div class="p-6 space-y-4">
                        @forelse($reviews as $review)
                            <div class="group p-5 border rounded-xl bg-gradient-to-br from-white {{ $isMale ? 'to-blue-50/30' : 'to-purple-50/30' }} hover:shadow-md transition-all">
                                <div class="flex items-start justify-between mb-3">
                                    <div class="flex items-center gap-3">
                                        <div class="w-12 h-12 rounded-full bg-gradient-to-br {{ $isMale ? 'from-blue-500 to-sky-500' : 'from-purple-500 to-teal-500' }} flex items-center justify-center shadow-sm">
                                            <span class="text-lg font-bold text-white">
                                                {{ strtoupper(substr($review->reviewer->full_name ?? 'A', 0, 1)) }}
                                            </span>
                                        </div>
                                        <div>
                                            <p class="font-bold text-base">{{ $review->reviewer->full_name ?? 'Anonymous' }}</p>
                                            <p class="text-xs text-gray-500 flex items-center gap-1">
                                                <i class="bi bi-clock text-xs"></i>
                                                {{ $review->formatted_date }}
                                            </p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-1 px-3 py-1.5 bg-yellow-50 rounded-lg border border-yellow-200">
                                        @for($i = 0; $i < $review->rating; $i++)
                                            <i class="bi bi-star-fill text-yellow-500 text-sm"></i>
                                        @endfor
                                    </div>
                                </div>
                                @if($review->comment)
                                    <p class="text-gray-500 leading-relaxed pl-15">{{ $review->comment }}</p>
                                @endif
                            </div>
                        @empty
                            <div class="text-center py-12">
                                <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-gray-100 flex items-center justify-center">
                                    <i class="bi bi-chat text-2xl text-gray-400"></i>
                                </div>
                                <p class="text-gray-500">No reviews yet</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- ===== CONTACT TAB ===== --}}
            <div x-show="activeTab === 'contact'" x-cloak class="mt-6 space-y-6">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="bg-gradient-to-br {{ $isMale ? 'from-blue-50/50 via-blue-100/30 to-blue-50/20' : 'from-purple-50 via-teal-50 to-amber-50' }} p-6 border-b">
                        <div class="flex items-start gap-4">
                            <div class="p-3 rounded-xl bg-white shadow-sm border">
                                <i class="bi bi-telephone text-xl text-blue-500"></i>
                            </div>
                            <div class="flex-1">
                                <h3 class="text-xl font-bold mb-2">Contact Information</h3>
                                <p class="text-sm text-gray-500">Get in touch to start your training journey</p>
                            </div>
                        </div>
                    </div>
                    <div class="p-6 space-y-4">
                        {{-- Phone --}}
                        <div class="group flex items-center gap-4 p-5 border rounded-xl bg-gradient-to-r from-white {{ $isMale ? 'to-blue-50/30 hover:border-blue-400' : 'to-blue-50/30 hover:border-blue-400' }} hover:shadow-md transition-all">
                            <div class="p-3 rounded-xl bg-blue-50 group-hover:bg-blue-100 transition-colors">
                                <i class="bi bi-telephone text-xl text-blue-500"></i>
                            </div>
                            <div class="flex-1">
                                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Phone</p>
                                <p class="font-bold text-base">Contact through club</p>
                            </div>
                        </div>

                        {{-- Email --}}
                        <div class="group flex items-center gap-4 p-5 border rounded-xl bg-gradient-to-r from-white {{ $isMale ? 'to-sky-50/30 hover:border-sky-400' : 'to-purple-50/30 hover:border-purple-400' }} hover:shadow-md transition-all">
                            <div class="p-3 rounded-xl bg-purple-50 group-hover:bg-purple-100 transition-colors">
                                <i class="bi bi-envelope text-xl text-purple-600"></i>
                            </div>
                            <div class="flex-1">
                                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Email</p>
                                <p class="font-bold text-base">Available upon request</p>
                            </div>
                        </div>

                        {{-- Social Media --}}
                        <div class="pt-4">
                            <p class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4">Connect on Social Media</p>
                            <div class="flex gap-3">
                                <button class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-3 border border-gray-300 rounded-lg hover:bg-gradient-to-r hover:from-pink-500 hover:to-orange-500 hover:text-white hover:border-transparent transition-all">
                                    <i class="bi bi-instagram text-lg"></i>
                                </button>
                                <button class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-3 border border-gray-300 rounded-lg hover:bg-gradient-to-r hover:from-blue-600 hover:to-blue-500 hover:text-white hover:border-transparent transition-all">
                                    <i class="bi bi-facebook text-lg"></i>
                                </button>
                                <button class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-3 border border-gray-300 rounded-lg hover:bg-gradient-to-r hover:from-sky-500 hover:to-blue-400 hover:text-white hover:border-transparent transition-all">
                                    <i class="bi bi-twitter-x text-lg"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
@endsection
