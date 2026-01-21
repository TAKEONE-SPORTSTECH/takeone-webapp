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

    <!-- Custom Styles -->
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f9fa;
        }
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
            background-color: #0d6efd;
            border-color: #0d6efd;
        }
        .btn-primary:hover {
            background-color: #0b5ed7;
            border-color: #0a58ca;
        }
        .user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 8px;
        }
        .user-avatar-placeholder {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background-color: #0d6efd;
            color: white;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-right: 8px;
            font-weight: 600;
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
            transition: background-color 0.2s;
        }
        .nav-icon-btn:hover {
            background-color: #f8f9fa;
        }
        .notification-badge {
            position: absolute;
            top: 0;
            right: 0;
            background-color: #dc3545;
            color: white;
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
            border-bottom: 1px solid #f0f0f0;
            transition: background-color 0.2s;
        }
        .notification-item:hover {
            background-color: #f8f9fa;
        }
        .notification-item:last-child {
            border-bottom: none;
        }
        .notification-item.unread {
            background-color: #f0f7ff;
        }
    </style>

    @stack('styles')
</head>
<body>
    <nav class="navbar navbar-expand-md navbar-light bg-white shadow-sm">
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
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item text-center small" href="#">View All Notifications</a>
                            </div>
                        </li>
                    @endauth

                    @guest
                        @if (Route::has('login'))
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('login') }}">{{ __('Login') }}</a>
                            </li>
                        @endif

                        @if (Route::has('register'))
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('register') }}">{{ __('Register') }}</a>
                            </li>
                        @endif
                    @else
                        <li class="nav-item dropdown">
                            <a id="navbarDropdown" class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" v-pre>
                                @if(Auth::user()->profile_picture)
                                    <img src="{{ asset('storage/' . Auth::user()->profile_picture) }}" alt="{{ Auth::user()->full_name }}" class="user-avatar">
                                @else
                                    <span class="user-avatar-placeholder">
                                        {{ strtoupper(substr(Auth::user()->full_name, 0, 1)) }}
                                    </span>
                                @endif
                                {{ Auth::user()->full_name }}
                            </a>

                            <div class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                                <a class="dropdown-item" href="{{ route('family.dashboard') }}">
                                    <i class="bi bi-people me-2"></i>Family
                                </a>
                                <a class="dropdown-item" href="{{ route('invoices.index') }}">
                                    <i class="bi bi-receipt me-2"></i>Invoices
                                </a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item" href="{{ route('logout') }}"
                                   onclick="event.preventDefault();
                                                 document.getElementById('logout-form').submit();">
                                    <i class="bi bi-box-arrow-right me-2"></i>{{ __('Logout') }}
                                </a>

                                <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                                    @csrf
                                </form>
                            </div>
                        </li>
                    @endguest
                </ul>
            </div>
        </div>
    </nav>

    <main>
        @yield('content')
    </main>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

    <!-- jQuery (required for Select2) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    @stack('scripts')
</body>
</html>
