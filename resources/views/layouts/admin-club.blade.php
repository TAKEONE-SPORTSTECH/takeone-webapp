@extends('layouts.app')

@section('content')
<div x-data="{ sidebarOpen: false }">

<!-- Mobile Sidebar Toggle -->
<div class="lg:hidden sticky top-16 z-40 bg-background border-b border-border p-4">
    <button @click="sidebarOpen = !sidebarOpen"
            class="flex items-center gap-2 px-4 py-2 border border-border rounded-lg bg-white font-semibold cursor-pointer">
        <i class="bi bi-list" x-show="!sidebarOpen"></i>
        <i class="bi bi-x-lg" x-show="sidebarOpen" x-cloak></i>
        <span class="truncate max-w-[200px]">{{ $club->club_name ?? 'Club Menu' }}</span>
    </button>
</div>

<!-- Mobile Overlay Backdrop -->
<div x-show="sidebarOpen" x-cloak @click="sidebarOpen = false"
     class="lg:hidden fixed inset-0 bg-black/50 z-30"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"></div>

<!-- Club Admin Wrapper -->
<div class="max-w-[1400px] mx-auto px-4 py-5 flex flex-col lg:flex-row gap-5">
    <!-- Sidebar -->
    <aside class="w-full lg:w-72 lg:min-w-72 bg-muted/30 border border-border rounded-xl p-6 h-fit lg:sticky lg:top-24"
           :class="{ 'hidden lg:block': !sidebarOpen, 'block relative z-40': sidebarOpen }">

        @if(isset($club) && $club->logo)
            <div class="w-full p-4 flex items-center justify-center mb-2">
                <img src="{{ asset('storage/' . $club->logo) }}" alt="{{ $club->club_name }}" class="max-w-full max-h-30 object-contain">
            </div>
        @else
            <h2 class="text-lg font-bold text-foreground px-2 pb-2 text-center">{{ $club->club_name ?? 'Club Admin' }}</h2>
        @endif

        <h3 class="text-xs font-semibold text-muted-foreground uppercase tracking-wide text-center mb-4">Club Panel</h3>

        <!-- Action Buttons -->
        <div class="flex items-center justify-center gap-2 pb-4 mb-4 border-b border-border">
            <a href="{{ route('admin.platform.clubs') }}"
               class="w-9 h-9 rounded-lg flex items-center justify-center bg-card text-foreground hover:bg-accent hover:shadow-sm transition-all border border-border no-underline"
               title="Back to Clubs">
                <i class="bi bi-arrow-left"></i>
            </a>
            <a href="{{ route('clubs.show', $club->slug) }}"
               class="w-9 h-9 rounded-lg flex items-center justify-center bg-card text-foreground hover:bg-accent hover:shadow-sm transition-all border border-border no-underline"
               title="Preview Club" target="_blank">
                <i class="bi bi-eye"></i>
            </a>
            <button class="w-9 h-9 rounded-lg flex items-center justify-center bg-card text-foreground hover:bg-accent hover:shadow-sm transition-all border border-border cursor-pointer"
                    title="Send Notification">
                <i class="bi bi-send"></i>
            </button>
        </div>

        @php
            $clubId = $club->slug ?? $club->id ?? null;
            $currentRoute = request()->route()->getName();
        @endphp

        <nav class="flex flex-col gap-1">
            <a href="{{ route('admin.club.dashboard', $clubId) }}"
               class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all no-underline text-sm font-medium
                      {{ $currentRoute === 'admin.club.dashboard'
                         ? 'bg-primary text-white shadow-lg'
                         : 'text-foreground hover:bg-muted' }}">
                <i class="bi bi-speedometer2 w-5"></i>
                <span>Dashboard</span>
            </a>

            <a href="{{ route('admin.club.members', $clubId) }}"
               class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all no-underline text-sm font-medium
                      {{ $currentRoute === 'admin.club.members'
                         ? 'bg-primary text-white shadow-lg'
                         : 'text-foreground hover:bg-muted' }}">
                <i class="bi bi-person-plus w-5"></i>
                <span>Members</span>
            </a>

            <a href="{{ route('admin.club.financials', $clubId) }}"
               class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all no-underline text-sm font-medium
                      {{ $currentRoute === 'admin.club.financials'
                         ? 'bg-primary text-white shadow-lg'
                         : 'text-foreground hover:bg-muted' }}">
                <i class="bi bi-currency-dollar w-5"></i>
                <span>Financials</span>
            </a>

            <a href="{{ route('admin.club.details', $clubId) }}"
               class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all no-underline text-sm font-medium
                      {{ $currentRoute === 'admin.club.details'
                         ? 'bg-primary text-white shadow-lg'
                         : 'text-foreground hover:bg-muted' }}">
                <i class="bi bi-building w-5"></i>
                <span>Club Details</span>
            </a>

            <a href="{{ route('admin.club.facilities', $clubId) }}"
               class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all no-underline text-sm font-medium
                      {{ $currentRoute === 'admin.club.facilities'
                         ? 'bg-primary text-white shadow-lg'
                         : 'text-foreground hover:bg-muted' }}">
                <i class="bi bi-geo-alt w-5"></i>
                <span>Facilities</span>
            </a>

            <a href="{{ route('admin.club.instructors', $clubId) }}"
               class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all no-underline text-sm font-medium
                      {{ $currentRoute === 'admin.club.instructors'
                         ? 'bg-primary text-white shadow-lg'
                         : 'text-foreground hover:bg-muted' }}">
                <i class="bi bi-people w-5"></i>
                <span>Instructors</span>
            </a>

            <a href="{{ route('admin.club.activities', $clubId) }}"
               class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all no-underline text-sm font-medium
                      {{ $currentRoute === 'admin.club.activities'
                         ? 'bg-primary text-white shadow-lg'
                         : 'text-foreground hover:bg-muted' }}">
                <i class="bi bi-activity w-5"></i>
                <span>Activities</span>
            </a>

            <a href="{{ route('admin.club.packages', $clubId) }}"
               class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all no-underline text-sm font-medium
                      {{ $currentRoute === 'admin.club.packages'
                         ? 'bg-primary text-white shadow-lg'
                         : 'text-foreground hover:bg-muted' }}">
                <i class="bi bi-box w-5"></i>
                <span>Packages</span>
            </a>

            <a href="{{ route('admin.club.gallery', $clubId) }}"
               class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all no-underline text-sm font-medium
                      {{ $currentRoute === 'admin.club.gallery'
                         ? 'bg-primary text-white shadow-lg'
                         : 'text-foreground hover:bg-muted' }}">
                <i class="bi bi-images w-5"></i>
                <span>Gallery</span>
            </a>

            <a href="{{ route('admin.club.roles', $clubId) }}"
               class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all no-underline text-sm font-medium
                      {{ $currentRoute === 'admin.club.roles'
                         ? 'bg-primary text-white shadow-lg'
                         : 'text-foreground hover:bg-muted' }}">
                <i class="bi bi-shield-check w-5"></i>
                <span>Roles</span>
            </a>

            <a href="{{ route('admin.club.messages', $clubId) }}"
               class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all no-underline text-sm font-medium
                      {{ $currentRoute === 'admin.club.messages'
                         ? 'bg-primary text-white shadow-lg'
                         : 'text-foreground hover:bg-muted' }}">
                <i class="bi bi-chat-dots w-5"></i>
                <span>Messages</span>
            </a>

            <a href="{{ route('admin.club.analytics', $clubId) }}"
               class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all no-underline text-sm font-medium
                      {{ $currentRoute === 'admin.club.analytics'
                         ? 'bg-primary text-white shadow-lg'
                         : 'text-foreground hover:bg-muted' }}">
                <i class="bi bi-bar-chart w-5"></i>
                <span>Analytics</span>
            </a>
        </nav>
    </aside>

    <!-- Content -->
    <main class="flex-1 min-w-0">
        @yield('club-admin-content')
    </main>
</div>

</div>{{-- end x-data sidebarOpen --}}
@endsection
