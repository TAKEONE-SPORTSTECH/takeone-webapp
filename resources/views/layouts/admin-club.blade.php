@extends('layouts.app')

@section('content')
<style>
    /* Club Admin Layout - BOXED with Sidebar */
    .club-admin-wrapper {
        max-width: 1400px;
        margin: 20px auto;
        padding: 0 15px;
        display: flex;
        gap: 20px;
    }

    .club-admin-sidebar {
        width: 280px;
        min-width: 280px;
        background-color: hsl(var(--muted) / 0.3);
        padding: 1.5rem;
        border-radius: 0.75rem;
        height: fit-content;
        position: sticky;
        top: 92px;
    }

    .club-logo-container {
        width: 100%;
        padding: 1rem;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 1rem;
    }

    .club-logo-container img {
        max-width: 100%;
        max-height: 120px;
        object-fit: contain;
    }

    .club-name-header {
        font-size: 1.125rem;
        font-weight: 700;
        color: hsl(var(--foreground));
        padding: 0 0.5rem 1rem;
        text-align: center;
    }

    .sidebar-action-buttons {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        padding-bottom: 1rem;
        margin-bottom: 1rem;
        border-bottom: 1px solid hsl(var(--border));
    }

    .sidebar-action-btn {
        width: 36px;
        height: 36px;
        border-radius: 0.5rem;
        display: flex;
        align-items: center;
        justify-content: center;
        background-color: transparent;
        border: none;
        color: hsl(var(--foreground));
        cursor: pointer;
        transition: all 0.2s;
    }

    .sidebar-action-btn:hover {
        background-color: hsl(var(--accent));
    }

    .club-admin-sidebar nav {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }

    .club-nav-item {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.75rem 1rem;
        border-radius: 0.5rem;
        transition: all 0.2s;
        text-decoration: none;
        color: hsl(var(--foreground));
        font-size: 0.875rem;
        font-weight: 500;
    }

    .club-nav-item:hover {
        background-color: hsl(var(--muted));
        text-decoration: none;
        color: hsl(var(--foreground));
    }

    .club-nav-item.active {
        background-color: hsl(var(--primary));
        color: white;
        box-shadow: var(--shadow-primary);
    }

    .club-nav-item i {
        width: 20px;
        font-size: 1.1rem;
    }

    .club-nav-divider {
        border-top: 1px solid hsl(var(--border));
        margin: 0.75rem 0;
    }

    .club-admin-content {
        flex: 1;
        min-width: 0;
    }

    /* Mobile Sidebar Toggle */
    .mobile-sidebar-toggle {
        display: none;
        position: sticky;
        top: 72px;
        z-index: 40;
        background-color: hsl(var(--background));
        border-bottom: 1px solid hsl(var(--border));
        padding: 1rem;
    }

    .mobile-sidebar-toggle button {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 1rem;
        border: 1px solid hsl(var(--border));
        border-radius: 0.5rem;
        background: white;
        font-weight: 600;
        cursor: pointer;
    }

    @media (max-width: 992px) {
        .club-admin-wrapper {
            flex-direction: column;
        }
        .club-admin-sidebar {
            display: none;
            width: 100%;
            position: relative;
            top: 0;
        }
        .club-admin-sidebar.show {
            display: block;
        }
        .mobile-sidebar-toggle {
            display: block;
        }
    }
</style>

<!-- Mobile Sidebar Toggle -->
<div class="mobile-sidebar-toggle">
    <button onclick="document.querySelector('.club-admin-sidebar').classList.toggle('show')">
        <i class="bi bi-list"></i>
        <span>{{ $club->club_name ?? 'Club Menu' }}</span>
    </button>
</div>

<!-- Club Admin Wrapper -->
<div class="club-admin-wrapper">
    <!-- Sidebar -->
    <aside class="club-admin-sidebar">
        @if(isset($club) && $club->logo)
            <div class="club-logo-container">
                <img src="{{ asset('storage/' . $club->logo) }}" alt="{{ $club->club_name }}">
            </div>
        @else
            <h2 class="club-name-header">{{ $club->club_name ?? 'Club Admin' }}</h2>
        @endif

        <!-- Action Buttons -->
        <div class="sidebar-action-buttons">
            <a href="{{ route('admin.platform.clubs') }}" class="sidebar-action-btn" title="Back to Clubs">
                <i class="bi bi-arrow-left"></i>
            </a>
            <a href="{{ route('clubs.explore') }}" class="sidebar-action-btn" title="Preview Club" target="_blank">
                <i class="bi bi-eye"></i>
            </a>
            <button class="sidebar-action-btn" title="Send Notification">
                <i class="bi bi-send"></i>
            </button>
        </div>

        <nav>
            @php
                $clubId = $club->id ?? null;
                $currentRoute = request()->route()->getName();
            @endphp

            <a href="{{ route('admin.club.dashboard', $clubId) }}"
               class="club-nav-item {{ $currentRoute === 'admin.club.dashboard' ? 'active' : '' }}">
                <i class="bi bi-speedometer2"></i>
                <span>Dashboard</span>
            </a>

            <a href="{{ route('admin.club.details', $clubId) }}"
               class="club-nav-item {{ $currentRoute === 'admin.club.details' ? 'active' : '' }}">
                <i class="bi bi-building"></i>
                <span>Club Details</span>
            </a>

            <a href="{{ route('admin.club.gallery', $clubId) }}"
               class="club-nav-item {{ $currentRoute === 'admin.club.gallery' ? 'active' : '' }}">
                <i class="bi bi-images"></i>
                <span>Gallery</span>
            </a>

            <a href="{{ route('admin.club.facilities', $clubId) }}"
               class="club-nav-item {{ $currentRoute === 'admin.club.facilities' ? 'active' : '' }}">
                <i class="bi bi-geo-alt"></i>
                <span>Facilities</span>
            </a>

            <a href="{{ route('admin.club.instructors', $clubId) }}"
               class="club-nav-item {{ $currentRoute === 'admin.club.instructors' ? 'active' : '' }}">
                <i class="bi bi-people"></i>
                <span>Instructors</span>
            </a>

            <a href="{{ route('admin.club.activities', $clubId) }}"
               class="club-nav-item {{ $currentRoute === 'admin.club.activities' ? 'active' : '' }}">
                <i class="bi bi-activity"></i>
                <span>Activities</span>
            </a>

            <a href="{{ route('admin.club.packages', $clubId) }}"
               class="club-nav-item {{ $currentRoute === 'admin.club.packages' ? 'active' : '' }}">
                <i class="bi bi-box"></i>
                <span>Packages</span>
            </a>

            <a href="{{ route('admin.club.members', $clubId) }}"
               class="club-nav-item {{ $currentRoute === 'admin.club.members' ? 'active' : '' }}">
                <i class="bi bi-person-plus"></i>
                <span>Members</span>
            </a>

            <a href="{{ route('admin.club.roles', $clubId) }}"
               class="club-nav-item {{ $currentRoute === 'admin.club.roles' ? 'active' : '' }}">
                <i class="bi bi-shield-check"></i>
                <span>Roles</span>
            </a>

            <a href="{{ route('admin.club.financials', $clubId) }}"
               class="club-nav-item {{ $currentRoute === 'admin.club.financials' ? 'active' : '' }}">
                <i class="bi bi-currency-dollar"></i>
                <span>Financials</span>
            </a>

            <a href="{{ route('admin.club.messages', $clubId) }}"
               class="club-nav-item {{ $currentRoute === 'admin.club.messages' ? 'active' : '' }}">
                <i class="bi bi-chat-dots"></i>
                <span>Messages</span>
            </a>

            <a href="{{ route('admin.club.analytics', $clubId) }}"
               class="club-nav-item {{ $currentRoute === 'admin.club.analytics' ? 'active' : '' }}">
                <i class="bi bi-bar-chart"></i>
                <span>Analytics</span>
            </a>
        </nav>
    </aside>

    <!-- Content -->
    <main class="club-admin-content">
        @yield('club-admin-content')
    </main>
</div>
@endsection
