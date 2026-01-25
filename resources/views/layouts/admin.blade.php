@extends('layouts.app')

@section('content')
<style>
    /* Admin Layout - BOXED with Sidebar */
    .admin-wrapper {
        max-width: 1320px;
        margin: 20px auto;
        padding: 0 15px;
        display: flex;
        gap: 20px;
    }

    .admin-sidebar {
        width: 256px;
        min-width: 256px;
        border-right: 1px solid hsl(var(--border));
        background-color: hsl(var(--muted) / 0.3);
        padding: 1.5rem;
        border-radius: 0.75rem;
        height: fit-content;
        position: sticky;
        top: 92px;
    }

    .admin-sidebar h2 {
        font-size: 0.875rem;
        font-weight: 600;
        color: hsl(var(--muted-foreground));
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 1rem;
    }

    .admin-sidebar nav {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .admin-nav-item {
        display: flex;
        align-items: flex-start;
        gap: 0.75rem;
        padding: 1rem;
        border-radius: 0.5rem;
        transition: all 0.2s;
        text-decoration: none;
        color: hsl(var(--foreground));
        background-color: hsl(var(--card));
        border: 1px solid transparent;
    }

    .admin-nav-item:hover {
        background-color: hsl(var(--muted));
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        text-decoration: none;
        color: hsl(var(--foreground));
    }

    .admin-nav-item.active {
        background-color: hsl(var(--primary));
        color: white;
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
    }

    .admin-nav-item.active .nav-item-text,
    .admin-nav-item.active .nav-item-subtitle {
        color: white !important;
    }

    .admin-nav-item i {
        width: 20px;
        height: 20px;
        flex-shrink: 0;
        margin-top: 2px;
    }

    .nav-item-content {
        flex: 1;
        text-align: left;
    }

    .nav-item-text {
        font-weight: 600;
        font-size: 0.875rem;
        margin: 0;
    }

    .nav-item-subtitle {
        font-size: 0.75rem;
        color: hsl(var(--muted-foreground));
        margin: 0.25rem 0 0 0;
    }

    .admin-nav-item.active .nav-item-subtitle {
        color: rgba(255, 255, 255, 0.8);
    }

    .admin-nav-divider {
        border-top: 1px solid hsl(var(--border));
        margin: 1rem 0;
    }

    .admin-content {
        flex: 1;
        min-width: 0;
    }

    @media (max-width: 992px) {
        .admin-wrapper {
            flex-direction: column;
        }
        .admin-sidebar {
            width: 100%;
            position: relative;
            top: 0;
        }
    }
</style>

<!-- Admin Wrapper - BOXED -->
<div class="admin-wrapper">
    <!-- Sidebar -->
    <aside class="admin-sidebar">
        <h2>Admin Panel</h2>
        <nav>
            <a href="{{ route('admin.platform.clubs') }}" class="admin-nav-item {{ request()->routeIs('admin.platform.clubs*') || request()->routeIs('admin.platform.index') ? 'active' : '' }}">
                <i class="bi bi-building"></i>
                <div class="nav-item-content">
                    <p class="nav-item-text">All Clubs</p>
                    <p class="nav-item-subtitle">Manage {{ $clubsCount ?? 0 }} {{ ($clubsCount ?? 0) === 1 ? 'club' : 'clubs' }}</p>
                </div>
            </a>
            <a href="{{ route('admin.platform.members') }}" class="admin-nav-item {{ request()->routeIs('admin.platform.members*') ? 'active' : '' }}">
                <i class="bi bi-people"></i>
                <div class="nav-item-content">
                    <p class="nav-item-text">All Members</p>
                    <p class="nav-item-subtitle">View all platform members</p>
                </div>
            </a>
            <a href="{{ route('admin.platform.backup') }}" class="admin-nav-item {{ request()->routeIs('admin.platform.backup*') ? 'active' : '' }}">
                <i class="bi bi-database"></i>
                <div class="nav-item-content">
                    <p class="nav-item-text">Backup & Restore</p>
                    <p class="nav-item-subtitle">Database management</p>
                </div>
            </a>
            <div class="admin-nav-divider"></div>
            <a href="{{ route('clubs.explore') }}" class="admin-nav-item" style="border: 1px solid hsl(var(--border));">
                <i class="bi bi-eye text-primary"></i>
                <div class="nav-item-content">
                    <p class="nav-item-text">Back to Explore</p>
                    <p class="nav-item-subtitle">View as user</p>
                </div>
            </a>
        </nav>
    </aside>

        <!-- Content -->
        <main class="admin-content">
            @yield('admin-content')
        </main>
</div>
@endsection
