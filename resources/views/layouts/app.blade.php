<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Club SaaS') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

    <!-- Flag Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flag-icons@6.6.6/css/flag-icons.min.css">

    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <!-- Alpine.js cloak style -->
    <style>[x-cloak] { display: none !important; }</style>

    <!-- Tailwind CSS (utilities only, no preflight - works with Bootstrap) -->
    @vite(['resources/css/app.css'])

    <!-- Custom Styles -->
    <style>
        /* Fix Bootstrap collapse conflict with Tailwind */
        .collapse:not(.show) {
            display: none !important;
        }
        .collapse.show, .navbar-collapse.collapsing {
            display: block !important;
        }
        .navbar-collapse.collapse.show {
            display: flex !important;
        }

        /* Ensure dropdowns aren't clipped */
        .navbar, .navbar-collapse, .navbar-nav {
            overflow: visible !important;
        }

        /* Profile dropdown menu items */
        .profile-dropdown-item {
            display: flex;
            align-items: center;
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            color: #374151;
            text-decoration: none;
        }
        .profile-dropdown-item:hover {
            background-color: hsl(var(--primary));
            color: white;
        }

        :root {
          /* Base Colors */
          --background: 220 15% 97%;
          --foreground: 215 25% 27%;

          --card: 0 0% 100%;
          --card-foreground: 215 25% 27%;

          /* Primary - Soft Purple */
          --primary: 250 60% 70%;
          --primary-foreground: 0 0% 100%;
          --primary-hover: 250 60% 65%;

          /* Secondary - Soft Sage Green */
          --secondary: 140 30% 75%;
          --secondary-foreground: 140 45% 25%;

          /* Success - Soft Mint */
          --success: 150 40% 70%;
          --success-foreground: 150 45% 20%;

          /* Warning - Soft Peach */
          --warning: 35 60% 80%;
          --warning-foreground: 35 60% 30%;

          /* Info - Soft Sky Blue */
          --info: 200 50% 75%;
          --info-foreground: 200 60% 25%;

          --muted: 220 15% 94%;
          --muted-foreground: 215 15% 50%;

          --accent: 250 60% 92%;
          --accent-foreground: 250 60% 30%;

          --destructive: 0 50% 75%;
          --destructive-foreground: 0 0% 100%;

          --border: 220 15% 88%;
          --input: 220 15% 92%;
          --ring: 250 60% 70%;
          --radius: 0.75rem;

          /* Sidebar */
          --sidebar-background: 250 25% 96%;
          --sidebar-foreground: 215 25% 35%;
          --sidebar-primary: 250 60% 70%;
          --sidebar-primary-foreground: 0 0% 100%;
          --sidebar-accent: 250 25% 90%;
          --sidebar-accent-foreground: 215 25% 40%;
          --sidebar-border: 250 20% 85%;
          --sidebar-ring: 250 60% 70%;

          /* Gradients */
          --gradient-primary: linear-gradient(135deg, hsl(250 60% 75%), hsl(250 60% 65%));
          --gradient-secondary: linear-gradient(135deg, hsl(140 30% 80%), hsl(140 30% 70%));
          --gradient-sidebar: linear-gradient(180deg, hsl(250 25% 98%), hsl(250 25% 94%));
          --gradient-success: linear-gradient(135deg, hsl(150 40% 75%), hsl(150 40% 65%));
          --gradient-warning: linear-gradient(135deg, hsl(35 60% 85%), hsl(35 60% 75%));
          --gradient-info: linear-gradient(135deg, hsl(200 50% 80%), hsl(200 50% 70%));

          /* Shadows */
          --shadow-card: 0 2px 12px hsl(250 20% 70% / 0.08);
          --shadow-elevated: 0 8px 30px hsl(250 20% 60% / 0.12);
          --shadow-primary: 0 4px 20px hsl(250 60% 70% / 0.25);

          /* Bootstrap Overrides */
          --bs-primary: hsl(var(--primary));
          --bs-secondary: hsl(var(--secondary));
          --bs-success: hsl(var(--success));
          --bs-info: hsl(var(--info));
          --bs-warning: hsl(var(--warning));
          --bs-danger: hsl(var(--destructive));
          --bs-light: hsl(var(--muted));
          --bs-dark: hsl(var(--foreground));
          --bs-white: hsl(var(--card));
          --bs-body-color: hsl(var(--foreground));
          --bs-body-bg: hsl(var(--background));
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: hsl(var(--background));
        }

        /* Theme Overrides */
        .text-primary { color: hsl(var(--primary)) !important; }
        .text-secondary { color: hsl(var(--secondary)) !important; }
        .text-success { color: hsl(var(--success)) !important; }
        .text-info { color: hsl(var(--info)) !important; }
        .text-warning { color: hsl(var(--warning)) !important; }
        .text-danger { color: hsl(var(--destructive)) !important; }
        .text-muted { color: hsl(var(--muted-foreground)) !important; }

        .bg-primary { background-color: hsl(var(--primary)) !important; }
        .bg-secondary { background-color: hsl(var(--secondary)) !important; }
        .bg-success { background-color: hsl(var(--success)) !important; }
        .bg-info { background-color: hsl(var(--info)) !important; }
        .bg-warning { background-color: hsl(var(--warning)) !important; }
        .bg-danger { background-color: hsl(var(--destructive)) !important; }
        .bg-light { background-color: hsl(var(--muted)) !important; }
        .bg-white { background-color: #ffffff !important; }

        .btn-primary {
            background-color: hsl(var(--primary)) !important;
            border-color: hsl(var(--primary)) !important;
        }
        .btn-primary:hover {
            background-color: hsl(var(--primary-hover)) !important;
            border-color: hsl(var(--primary-hover)) !important;
        }
        .btn-outline-primary {
            color: hsl(var(--primary)) !important;
            border-color: hsl(var(--primary)) !important;
        }
        .btn-outline-primary:hover {
            background-color: hsl(var(--primary)) !important;
            border-color: hsl(var(--primary)) !important;
        }

        .border { border-color: hsl(var(--border)) !important; }
        .border-primary { border-color: hsl(var(--primary)) !important; }

        .card { background-color: #ffffff !important; }
        .navbar-brand {
            font-weight: 600;
        }
        .nav-link {
            font-weight: 500;
        }
        .card {
            border-radius: 10px;
            border: none;
        }
        .card-header {
            border-radius: 10px 10px 0 0 !important;
            border-bottom: none;
        }
        .btn {
            border-radius: 5px;
            padding: 0.5rem 1rem;
            font-weight: 500;
        }
        .btn-primary {
            background-color: hsl(var(--primary));
            border-color: hsl(var(--primary));
        }
        .btn-primary:hover {
            background-color: hsl(var(--primary-hover));
            border-color: hsl(var(--primary-hover));
        }
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 8px;
        }
        .user-avatar-placeholder {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: hsl(var(--primary));
            color: hsl(var(--primary-foreground));
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-right: 8px;
            font-weight: 600;
        }
        .avatar-container {
            position: relative;
            display: inline-block;
        }
        .online-indicator {
            position: absolute;
            bottom: -2px;
            right: -2px;
            width: 10px;
            height: 10px;
            background-color: hsl(var(--success));
            border-radius: 50%;
            border: 2px solid white;
        }
        .dropdown-toggle::after {
            display: none;
        }
        .dropdown-toggle {
            display: flex;
            align-items: center;
        }
        .nav-icon-btn {
            position: relative;
            padding: 0.5rem;
            margin: 0 0.25rem;
            border-radius: 50%;
            transition: transform 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .nav-icon-btn:hover {
            transform: scale(1.1);
        }
        .notification-badge {
            position: absolute;
            top: 0;
            right: 0;
            background-color: hsl(var(--destructive));
            color: hsl(var(--destructive-foreground));
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        .notification-dropdown {
            min-width: 320px;
            max-height: 400px;
            overflow-y: auto;
        }
        .notification-item {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid hsl(var(--border));
            transition: background-color 0.2s;
            cursor: pointer;
            background-color: white;
        }
        .notification-item:hover {
            background-color: hsl(var(--primary)) !important;
            color: hsl(var(--primary-foreground)) !important;
        }
        .notification-item:hover .text-muted {
            color: hsl(var(--primary-foreground)) !important;
        }
        .notification-item:last-child {
            border-bottom: none;
        }
        .notification-item.unread {
            background-color: white;
        }

        .dropdown-item:hover {
            background-color: hsl(var(--primary)) !important;
            color: white !important;
        }

        .nav-icon-btn.dropdown-toggle::after {
            display: none;
        }

        /* Vertical alignment fix for navbar items */
        .navbar-nav {
            display: flex;
            align-items: center;
        }

        .navbar-nav .nav-item {
            display: flex;
            align-items: center;
        }

        .navbar-nav .nav-link {
            display: flex;
            align-items: center;
        }
    </style>

    @stack('styles')
</head>
<body>
    @if(!View::hasSection('hide-navbar'))
    <nav class="navbar navbar-expand-md navbar-light bg-light shadow-sm">
        <div class="container">
            <a class="navbar-brand" href="{{ Auth::check() ? route('clubs.explore') : url('/') }}">
                <img src="{{ asset('images/logo.png') }}" alt="TAKEONE" height="40">
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="{{ __('Toggle navigation') }}">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarSupportedContent">
                <!-- Left Side Of Navbar -->
                <ul class="navbar-nav me-auto">
                </ul>

                <!-- Right Side Of Navbar -->
                <ul class="navbar-nav ms-auto">
                    <!-- Authentication Links -->
                    @auth
                        <!-- Explore Button -->
                        <li class="nav-item">
                            <a class="nav-link nav-icon-btn" href="{{ route('clubs.explore') }}" title="Explore">
                                <i class="bi bi-compass" style="font-size: 1.25rem;"></i>
                            </a>
                        </li>

                        <!-- Messages Dropdown -->
                        <li class="nav-item dropdown">
                            <a class="nav-link nav-icon-btn dropdown-toggle" href="#" id="messagesDropdown" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="Messages">
                                <i class="bi bi-chat" style="font-size: 1.25rem;"></i>
                            </a>
                            <div class="dropdown-menu dropdown-menu-end" aria-labelledby="messagesDropdown">
                                <h6 class="dropdown-header small">Messages</h6>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item small" href="#">No new messages</a>
                            </div>
                        </li>

                        <!-- Notifications Dropdown -->
                        <li class="nav-item dropdown">
                            <a class="nav-link nav-icon-btn dropdown-toggle" href="#" id="notificationDropdown" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="Notifications">
                                <i class="bi bi-bell" style="font-size: 1.25rem;"></i>
                                <span class="notification-badge">3</span>
                            </a>
                            <div class="dropdown-menu dropdown-menu-end notification-dropdown" aria-labelledby="notificationDropdown">
                                <h6 class="dropdown-header">Notifications</h6>
                                <div class="notification-item unread">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <strong>New Family Member</strong>
                                            <p class="mb-0 small text-muted">John Doe joined your family</p>
                                        </div>
                                        <small class="text-muted">2m</small>
                                    </div>
                                </div>
                                <div class="notification-item unread">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <strong>Invoice Due</strong>
                                            <p class="mb-0 small text-muted">Payment due in 3 days</p>
                                        </div>
                                        <small class="text-muted">1h</small>
                                    </div>
                                </div>
                                <div class="notification-item unread">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <strong>Welcome!</strong>
                                            <p class="mb-0 small text-muted">Thanks for joining TAKEONE</p>
                                        </div>
                                        <small class="text-muted">2d</small>
                                    </div>
                                </div>
                                <a class="dropdown-item text-center small" href="#">View All Notifications</a>
                            </div>
                        </li>
                    @endauth

                    @auth
                        <!-- Profile Dropdown with Alpine.js -->
                        <li class="nav-item" style="position: relative;" x-data="{ open: false }" x-on:click.outside="open = false">
                            <button x-on:click="open = !open" class="nav-link dropdown-toggle" style="display: flex; align-items: center; background: none; border: none; cursor: pointer;" type="button">
                                <div class="avatar-container">
                                    @if(Auth::user()->profile_picture)
                                        <img src="{{ asset('storage/' . Auth::user()->profile_picture) }}" alt="{{ Auth::user()->full_name }}" class="user-avatar">
                                    @else
                                        <span class="user-avatar-placeholder">
                                            {{ strtoupper(substr(Auth::user()->full_name, 0, 1)) }}
                                        </span>
                                    @endif
                                    <span class="online-indicator"></span>
                                </div>
                            </button>

                            <div x-show="open"
                                 x-cloak
                                 style="position: absolute; right: 0; margin-top: 0.5rem; width: 14rem; background: white; border-radius: 0.5rem; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); border: 1px solid #e5e7eb; padding: 0.25rem 0; z-index: 9999;">
                                <div style="padding: 0.5rem 1rem; border-bottom: 1px solid #f3f4f6;">
                                    <p style="font-size: 0.875rem; font-weight: 600; color: #111827; margin: 0;">{{ Auth::user()->full_name }}</p>
                                    <p style="font-size: 0.75rem; color: #6b7280; margin: 0;">{{ Auth::user()->email }}</p>
                                </div>
                                <a class="profile-dropdown-item" href="{{ route('member.show', Auth::id()) }}">
                                    <i class="bi bi-person me-2"></i>Profile
                                </a>
                                <a class="profile-dropdown-item" href="#">
                                    <i class="bi bi-diagram-3 me-2"></i>Affiliations
                                </a>
                                <a class="profile-dropdown-item" href="#">
                                    <i class="bi bi-calendar-event me-2"></i>Sessions
                                </a>
                                <a class="profile-dropdown-item" href="{{ route('members.index') }}">
                                    <i class="bi bi-people me-2"></i>Family
                                </a>
                                <a class="profile-dropdown-item" href="{{ route('bills.index') }}">
                                    <i class="bi bi-receipt me-2"></i>Payments & Subscriptions
                                </a>
                                <a class="profile-dropdown-item" href="#">
                                    <i class="bi bi-gear me-2"></i>Manage Business
                                </a>
                                <div style="border-top: 1px solid #f3f4f6; margin: 0.25rem 0;"></div>
                                @if(Auth::user()->isSuperAdmin())
                                <a class="profile-dropdown-item" href="{{ route('admin.platform.index') }}">
                                    <i class="bi bi-shield-check me-2"></i>Admin Panel
                                </a>
                                <div style="border-top: 1px solid #f3f4f6; margin: 0.25rem 0;"></div>
                                @endif
                                <a class="profile-dropdown-item" href="{{ route('logout') }}"
                                   onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                    <i class="bi bi-box-arrow-right me-2"></i>Sign Out
                                </a>

                                <form id="logout-form" action="{{ route('logout') }}" method="POST" class="hidden">
                                    @csrf
                                </form>
                            </div>
                        </li>
                    @endauth

                    @guest
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('login') }}">
                                <i class="bi bi-box-arrow-in-right me-1"></i>Login
                            </a>
                        </li>
                        @if (Route::has('register'))
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('register') }}">
                                    <i class="bi bi-person-plus me-1"></i>Register
                                </a>
                            </li>
                        @endif
                    @endguest
                </ul>
            </div>
        </div>
    </nav>
    @endif

    <main>
        @yield('content')
    </main>

    <!-- Toast Container -->
    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 9999;">
        @if(session('success'))
            <div class="toast align-items-center text-white bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true" id="successToast">
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        @endif
        @if(session('error'))
            <div class="toast align-items-center text-white bg-danger border-0" role="alert" aria-live="assertive" aria-atomic="true" id="errorToast">
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="bi bi-exclamation-triangle me-2"></i>{{ session('error') }}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        @endif
        @if(session('info'))
            <div class="toast align-items-center text-white bg-info border-0" role="alert" aria-live="assertive" aria-atomic="true" id="infoToast">
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="bi bi-info-circle me-2"></i>{{ session('info') }}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        @endif
        @if(session('warning'))
            <div class="toast align-items-center text-dark bg-warning border-0" role="alert" aria-live="assertive" aria-atomic="true" id="warningToast">
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="bi bi-exclamation-triangle me-2"></i>{{ session('warning') }}
                    </div>
                    <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        @endif
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Auto-show and auto-hide toasts
        document.addEventListener('DOMContentLoaded', function() {
            const toasts = ['successToast', 'errorToast', 'infoToast', 'warningToast'];

            toasts.forEach(function(toastId) {
                const toastElement = document.getElementById(toastId);
                if (toastElement) {
                    const toast = new bootstrap.Toast(toastElement, {
                        autohide: true,
                        delay: 3000
                    });
                    toast.show();
                }
            });

            @auth
            // Request location permission for authenticated users
            if ('geolocation' in navigator) {
                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        // Success - store the location
                        const userLocation = {
                            lat: position.coords.latitude,
                            lng: position.coords.longitude,
                            timestamp: new Date().toISOString()
                        };
                        localStorage.setItem('userLocation', JSON.stringify(userLocation));
                        console.log('Location obtained:', userLocation);
                    },
                    function(error) {
                        console.log('Location permission denied or error:', error.message);
                        localStorage.removeItem('userLocation');
                    },
                    {
                        enableHighAccuracy: true,
                        timeout: 10000,
                        maximumAge: 0 // Always get fresh location
                    }
                );
            }
            @endauth
        });
    </script>

    <!-- jQuery (required for Select2) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <!-- Vite Compiled Assets -->
    @vite(['resources/js/app.js'])

    @stack('scripts')

    <!-- Modals Stack (for cropper and other modals) -->
    @stack('modals')
</body>
</html>
