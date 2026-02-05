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

    <!-- Bootstrap Icons (icons only, no Bootstrap CSS) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

    <!-- Flag Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flag-icons@6.6.6/css/flag-icons.min.css">

    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <!-- Alpine.js cloak style -->
    <style>[x-cloak] { display: none !important; }</style>

    <!-- Tailwind CSS -->
    @vite(['resources/css/app.css'])

    <!-- Custom Styles -->
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }

        /* Profile dropdown styles */
        .profile-dropdown-item {
            display: flex;
            align-items: center;
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            color: #374151;
            text-decoration: none;
            transition: all 0.15s;
        }
        .profile-dropdown-item:hover {
            background-color: hsl(250 60% 70%);
            color: white;
        }

        /* Avatar styles */
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }
        .user-avatar-placeholder {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: hsl(250 60% 70%);
            color: white;
            display: inline-flex;
            align-items: center;
            justify-content: center;
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
            background-color: hsl(150 40% 70%);
            border-radius: 50%;
            border: 2px solid white;
        }

        /* Nav icon button styles */
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

        /* Notification styles */
        .notification-badge {
            position: absolute;
            top: 0;
            right: 0;
            background-color: hsl(0 50% 75%);
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
            border-bottom: 1px solid hsl(220 15% 88%);
            transition: background-color 0.2s;
            cursor: pointer;
            background-color: white;
        }
        .notification-item:hover {
            background-color: hsl(250 60% 70%);
            color: white;
        }
        .notification-item:hover .text-muted-foreground {
            color: white !important;
        }
        .notification-item:last-child {
            border-bottom: none;
        }
    </style>

    @stack('styles')
</head>
<body class="bg-background text-foreground antialiased">
    @if(!View::hasSection('hide-navbar'))
    <nav class="bg-muted shadow-sm sticky top-0 z-40" x-data="{ mobileMenuOpen: false }">
        <div class="container mx-auto px-4">
            <div class="flex items-center justify-between h-16">
                <!-- Logo -->
                <a class="flex items-center font-semibold text-xl" href="{{ Auth::check() ? route('clubs.explore') : url('/') }}">
                    <img src="{{ asset('images/logo.png') }}" alt="TAKEONE" class="h-10">
                </a>

                <!-- Mobile menu button -->
                <button @click="mobileMenuOpen = !mobileMenuOpen" class="md:hidden p-2 rounded-md text-muted-foreground hover:text-foreground focus:outline-none" type="button">
                    <i class="bi bi-list text-2xl" x-show="!mobileMenuOpen"></i>
                    <i class="bi bi-x-lg text-2xl" x-show="mobileMenuOpen" x-cloak></i>
                </button>

                <!-- Desktop Navigation -->
                <div class="hidden md:flex items-center gap-2">
                    @auth
                        <!-- Explore Button -->
                        <a class="nav-icon-btn text-muted-foreground hover:text-foreground" href="{{ route('clubs.explore') }}" title="Explore">
                            <i class="bi bi-compass text-xl"></i>
                        </a>

                        <!-- Messages Dropdown -->
                        <div class="relative" x-data="{ open: false }">
                            <button @click="open = !open" class="nav-icon-btn text-muted-foreground hover:text-foreground" title="Messages">
                                <i class="bi bi-chat text-xl"></i>
                            </button>
                            <div x-show="open" @click.outside="open = false" x-cloak
                                 x-transition:enter="transition ease-out duration-100"
                                 x-transition:enter-start="opacity-0 scale-95"
                                 x-transition:enter-end="opacity-100 scale-100"
                                 x-transition:leave="transition ease-in duration-75"
                                 x-transition:leave-start="opacity-100 scale-100"
                                 x-transition:leave-end="opacity-0 scale-95"
                                 class="absolute right-0 mt-2 w-64 bg-white rounded-lg shadow-lg border border-border py-2 z-50">
                                <h6 class="px-4 py-2 text-xs font-semibold text-muted-foreground uppercase">Messages</h6>
                                <div class="border-t border-border my-1"></div>
                                <a class="block px-4 py-2 text-sm text-muted-foreground hover:bg-primary hover:text-white" href="#">No new messages</a>
                            </div>
                        </div>

                        <!-- Notifications Dropdown -->
                        <div class="relative" x-data="{ open: false }">
                            <button @click="open = !open" class="nav-icon-btn text-muted-foreground hover:text-foreground" title="Notifications">
                                <i class="bi bi-bell text-xl"></i>
                                <span class="notification-badge">3</span>
                            </button>
                            <div x-show="open" @click.outside="open = false" x-cloak
                                 x-transition:enter="transition ease-out duration-100"
                                 x-transition:enter-start="opacity-0 scale-95"
                                 x-transition:enter-end="opacity-100 scale-100"
                                 x-transition:leave="transition ease-in duration-75"
                                 x-transition:leave-start="opacity-100 scale-100"
                                 x-transition:leave-end="opacity-0 scale-95"
                                 class="absolute right-0 mt-2 notification-dropdown bg-white rounded-lg shadow-lg border border-border z-50">
                                <h6 class="px-4 py-3 text-sm font-semibold border-b border-border">Notifications</h6>
                                <div class="notification-item">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <strong class="text-sm">New Family Member</strong>
                                            <p class="mb-0 text-xs text-muted-foreground">John Doe joined your family</p>
                                        </div>
                                        <small class="text-muted-foreground text-xs">2m</small>
                                    </div>
                                </div>
                                <div class="notification-item">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <strong class="text-sm">Invoice Due</strong>
                                            <p class="mb-0 text-xs text-muted-foreground">Payment due in 3 days</p>
                                        </div>
                                        <small class="text-muted-foreground text-xs">1h</small>
                                    </div>
                                </div>
                                <div class="notification-item">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <strong class="text-sm">Welcome!</strong>
                                            <p class="mb-0 text-xs text-muted-foreground">Thanks for joining TAKEONE</p>
                                        </div>
                                        <small class="text-muted-foreground text-xs">2d</small>
                                    </div>
                                </div>
                                <a class="block px-4 py-2 text-center text-sm text-primary hover:bg-muted border-t border-border" href="#">View All Notifications</a>
                            </div>
                        </div>

                        <!-- Profile Dropdown -->
                        <div class="relative ml-2" x-data="{ open: false }">
                            <button @click="open = !open" class="flex items-center cursor-pointer" type="button">
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

                            <div x-show="open" @click.outside="open = false" x-cloak
                                 x-transition:enter="transition ease-out duration-100"
                                 x-transition:enter-start="opacity-0 scale-95"
                                 x-transition:enter-end="opacity-100 scale-100"
                                 x-transition:leave="transition ease-in duration-75"
                                 x-transition:leave-start="opacity-100 scale-100"
                                 x-transition:leave-end="opacity-0 scale-95"
                                 class="absolute right-0 mt-2 w-56 bg-white rounded-lg shadow-lg border border-border z-50">
                                <div class="px-4 py-3 border-b border-border">
                                    <p class="text-sm font-semibold text-foreground">{{ Auth::user()->full_name }}</p>
                                    <p class="text-xs text-muted-foreground">{{ Auth::user()->email }}</p>
                                </div>
                                <div class="py-1">
                                    <a class="profile-dropdown-item" href="{{ route('member.show', Auth::id()) }}">
                                        <i class="bi bi-person mr-2"></i>Profile
                                    </a>
                                    <a class="profile-dropdown-item" href="#">
                                        <i class="bi bi-diagram-3 mr-2"></i>Affiliations
                                    </a>
                                    <a class="profile-dropdown-item" href="#">
                                        <i class="bi bi-calendar-event mr-2"></i>Sessions
                                    </a>
                                    <a class="profile-dropdown-item" href="{{ route('members.index') }}">
                                        <i class="bi bi-people mr-2"></i>Family
                                    </a>
                                    <a class="profile-dropdown-item" href="{{ route('bills.index') }}">
                                        <i class="bi bi-receipt mr-2"></i>Payments & Subscriptions
                                    </a>
                                    <a class="profile-dropdown-item" href="#">
                                        <i class="bi bi-gear mr-2"></i>Manage Business
                                    </a>
                                </div>
                                @if(Auth::user()->isSuperAdmin())
                                <div class="border-t border-border py-1">
                                    <a class="profile-dropdown-item" href="{{ route('admin.platform.index') }}">
                                        <i class="bi bi-shield-check mr-2"></i>Admin Panel
                                    </a>
                                </div>
                                @endif
                                <div class="border-t border-border py-1">
                                    <a class="profile-dropdown-item" href="{{ route('logout') }}"
                                       onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                        <i class="bi bi-box-arrow-right mr-2"></i>Sign Out
                                    </a>
                                    <form id="logout-form" action="{{ route('logout') }}" method="POST" class="hidden">
                                        @csrf
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endauth

                    @guest
                        <a class="flex items-center px-4 py-2 text-sm font-medium text-muted-foreground hover:text-foreground" href="{{ route('login') }}">
                            <i class="bi bi-box-arrow-in-right mr-1"></i>Login
                        </a>
                        @if (Route::has('register'))
                            <a class="flex items-center px-4 py-2 text-sm font-medium text-muted-foreground hover:text-foreground" href="{{ route('register') }}">
                                <i class="bi bi-person-plus mr-1"></i>Register
                            </a>
                        @endif
                    @endguest
                </div>
            </div>

            <!-- Mobile Navigation -->
            <div x-show="mobileMenuOpen" x-cloak
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 -translate-y-2"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 translate-y-0"
                 x-transition:leave-end="opacity-0 -translate-y-2"
                 class="md:hidden border-t border-border py-4">
                @auth
                    <div class="flex items-center gap-3 px-2 py-3 mb-3 bg-white rounded-lg">
                        <div class="avatar-container">
                            @if(Auth::user()->profile_picture)
                                <img src="{{ asset('storage/' . Auth::user()->profile_picture) }}" alt="{{ Auth::user()->full_name }}" class="user-avatar">
                            @else
                                <span class="user-avatar-placeholder">
                                    {{ strtoupper(substr(Auth::user()->full_name, 0, 1)) }}
                                </span>
                            @endif
                        </div>
                        <div>
                            <p class="text-sm font-semibold">{{ Auth::user()->full_name }}</p>
                            <p class="text-xs text-muted-foreground">{{ Auth::user()->email }}</p>
                        </div>
                    </div>
                    <div class="space-y-1">
                        <a class="flex items-center px-3 py-2 rounded-md text-sm hover:bg-white" href="{{ route('clubs.explore') }}">
                            <i class="bi bi-compass mr-3"></i>Explore
                        </a>
                        <a class="flex items-center px-3 py-2 rounded-md text-sm hover:bg-white" href="{{ route('member.show', Auth::id()) }}">
                            <i class="bi bi-person mr-3"></i>Profile
                        </a>
                        <a class="flex items-center px-3 py-2 rounded-md text-sm hover:bg-white" href="{{ route('members.index') }}">
                            <i class="bi bi-people mr-3"></i>Family
                        </a>
                        <a class="flex items-center px-3 py-2 rounded-md text-sm hover:bg-white" href="{{ route('bills.index') }}">
                            <i class="bi bi-receipt mr-3"></i>Payments
                        </a>
                        @if(Auth::user()->isSuperAdmin())
                        <a class="flex items-center px-3 py-2 rounded-md text-sm hover:bg-white" href="{{ route('admin.platform.index') }}">
                            <i class="bi bi-shield-check mr-3"></i>Admin Panel
                        </a>
                        @endif
                        <div class="border-t border-border my-2"></div>
                        <a class="flex items-center px-3 py-2 rounded-md text-sm text-destructive hover:bg-white" href="{{ route('logout') }}"
                           onclick="event.preventDefault(); document.getElementById('logout-form-mobile').submit();">
                            <i class="bi bi-box-arrow-right mr-3"></i>Sign Out
                        </a>
                        <form id="logout-form-mobile" action="{{ route('logout') }}" method="POST" class="hidden">
                            @csrf
                        </form>
                    </div>
                @endauth

                @guest
                    <div class="space-y-1">
                        <a class="flex items-center px-3 py-2 rounded-md text-sm hover:bg-white" href="{{ route('login') }}">
                            <i class="bi bi-box-arrow-in-right mr-3"></i>Login
                        </a>
                        @if (Route::has('register'))
                            <a class="flex items-center px-3 py-2 rounded-md text-sm hover:bg-white" href="{{ route('register') }}">
                                <i class="bi bi-person-plus mr-3"></i>Register
                            </a>
                        @endif
                    </div>
                @endguest
            </div>
        </div>
    </nav>
    @endif

    <main>
        @yield('content')
    </main>

    <!-- Toast Container (Alpine.js) -->
    <div x-data="toastManager()" x-init="init()" class="fixed top-4 right-4 z-50 space-y-2">
        <template x-for="toast in toasts" :key="toast.id">
            <div x-show="toast.visible"
                 x-transition:enter="transition ease-out duration-300 transform"
                 x-transition:enter-start="translate-x-full opacity-0"
                 x-transition:enter-end="translate-x-0 opacity-100"
                 x-transition:leave="transition ease-in duration-200 transform"
                 x-transition:leave-start="translate-x-0 opacity-100"
                 x-transition:leave-end="translate-x-full opacity-0"
                 :class="{
                     'bg-success text-white': toast.type === 'success',
                     'bg-destructive text-white': toast.type === 'error',
                     'bg-info text-white': toast.type === 'info',
                     'bg-warning text-warning-foreground': toast.type === 'warning'
                 }"
                 class="flex items-center gap-3 px-4 py-3 rounded-lg shadow-lg min-w-[300px] border-0">
                <i :class="{
                    'bi bi-check-circle': toast.type === 'success',
                    'bi bi-exclamation-triangle': toast.type === 'error' || toast.type === 'warning',
                    'bi bi-info-circle': toast.type === 'info'
                }"></i>
                <span class="flex-1 text-sm" x-text="toast.message"></span>
                <button @click="removeToast(toast.id)" class="hover:opacity-70">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        </template>
    </div>

    <script>
        // Toast Manager Alpine Component
        function toastManager() {
            return {
                toasts: [],
                init() {
                    // Check for session messages
                    @if(session('success'))
                        this.addToast('success', @json(session('success')));
                    @endif
                    @if(session('error'))
                        this.addToast('error', @json(session('error')));
                    @endif
                    @if(session('info'))
                        this.addToast('info', @json(session('info')));
                    @endif
                    @if(session('warning'))
                        this.addToast('warning', @json(session('warning')));
                    @endif
                },
                addToast(type, message, duration = 3000) {
                    const id = Date.now();
                    this.toasts.push({ id, type, message, visible: true });
                    if (duration > 0) {
                        setTimeout(() => this.removeToast(id), duration);
                    }
                },
                removeToast(id) {
                    const index = this.toasts.findIndex(t => t.id === id);
                    if (index > -1) {
                        this.toasts[index].visible = false;
                        setTimeout(() => {
                            this.toasts = this.toasts.filter(t => t.id !== id);
                        }, 200);
                    }
                }
            }
        }

        // Global toast function for programmatic use
        window.showToast = function(type, message, duration = 3000) {
            const event = new CustomEvent('show-toast', { detail: { type, message, duration } });
            document.dispatchEvent(event);
        };

        @auth
        // Request location permission for authenticated users
        document.addEventListener('DOMContentLoaded', function() {
            if ('geolocation' in navigator) {
                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        const userLocation = {
                            lat: position.coords.latitude,
                            lng: position.coords.longitude,
                            timestamp: new Date().toISOString()
                        };
                        localStorage.setItem('userLocation', JSON.stringify(userLocation));
                    },
                    function(error) {
                        localStorage.removeItem('userLocation');
                    },
                    { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
                );
            }
        });
        @endauth
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
