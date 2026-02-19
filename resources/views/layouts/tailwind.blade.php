<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'TakeOne') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap Icons (icons only, no Bootstrap CSS) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

    <!-- Vite Assets (Tailwind) -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @stack('styles')
</head>
<body class="font-sans antialiased bg-background text-foreground">
    <!-- Navigation -->
    <nav class="bg-gray-100 shadow-sm sticky top-0 z-50">
        <div class="container mx-auto px-4 md:px-6">
            <div class="flex items-center justify-between h-16">
                <!-- Logo -->
                <a href="{{ Auth::check() ? route('clubs.explore') : url('/') }}" class="flex items-center">
                    <img src="{{ asset('images/logo.png') }}" alt="TAKEONE" class="h-10">
                </a>

                <!-- Mobile Menu Button -->
                <button class="md:hidden p-2 rounded-md hover:bg-gray-200 transition-colors" x-data @click="$dispatch('toggle-mobile-menu')">
                    <i class="bi bi-list text-xl"></i>
                </button>

                <!-- Right Side Navigation -->
                <div class="hidden md:flex items-center gap-2">
                    @auth
                        <!-- Explore Button -->
                        <a href="{{ route('clubs.explore') }}" class="p-2 rounded-full hover:bg-gray-200 transition-all hover:scale-110" title="Explore">
                            <i class="bi bi-compass text-xl"></i>
                        </a>

                        <!-- Messages Dropdown -->
                        <div class="relative" x-data="{ open: false }">
                            <button @click="open = !open" class="p-2 rounded-full hover:bg-gray-200 transition-all hover:scale-110" title="Messages">
                                <i class="bi bi-chat text-xl"></i>
                            </button>
                            <div x-show="open" @click.away="open = false" x-transition
                                 class="absolute right-0 mt-2 w-64 bg-white rounded-lg shadow-lg border border-gray-200 py-1 z-50">
                                <h6 class="px-4 py-2 text-xs font-semibold text-gray-500 uppercase">Messages</h6>
                                <hr class="border-gray-200">
                                <a href="#" class="block px-4 py-2 text-sm text-gray-600 hover:bg-primary hover:text-white transition-colors">No new messages</a>
                            </div>
                        </div>

                        <!-- Notifications Dropdown -->
                        <div class="relative" x-data="{ open: false }">
                            <button @click="open = !open" class="relative p-2 rounded-full hover:bg-gray-200 transition-all hover:scale-110" title="Notifications">
                                <i class="bi bi-bell text-xl"></i>
                                <span class="absolute top-0 right-0 w-[18px] h-[18px] bg-red-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center">3</span>
                            </button>
                            <div x-show="open" @click.away="open = false" x-transition
                                 class="absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-lg border border-gray-200 z-50 max-h-[400px] overflow-y-auto">
                                <h6 class="px-4 py-3 text-sm font-semibold border-b border-gray-200">Notifications</h6>
                                <div class="hover:bg-primary hover:text-white transition-colors cursor-pointer border-b border-gray-200">
                                    <div class="px-4 py-3">
                                        <div class="flex justify-between items-start">
                                            <div>
                                                <p class="font-semibold text-sm">New Family Member</p>
                                                <p class="text-xs text-gray-500 group-hover:text-white/80">John Doe joined your family</p>
                                            </div>
                                            <span class="text-xs text-gray-400">2m</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="hover:bg-primary hover:text-white transition-colors cursor-pointer border-b border-gray-200">
                                    <div class="px-4 py-3">
                                        <div class="flex justify-between items-start">
                                            <div>
                                                <p class="font-semibold text-sm">Invoice Due</p>
                                                <p class="text-xs text-gray-500">Payment due in 3 days</p>
                                            </div>
                                            <span class="text-xs text-gray-400">1h</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="hover:bg-primary hover:text-white transition-colors cursor-pointer border-b border-gray-200">
                                    <div class="px-4 py-3">
                                        <div class="flex justify-between items-start">
                                            <div>
                                                <p class="font-semibold text-sm">Welcome!</p>
                                                <p class="text-xs text-gray-500">Thanks for joining TAKEONE</p>
                                            </div>
                                            <span class="text-xs text-gray-400">2d</span>
                                        </div>
                                    </div>
                                </div>
                                <a href="#" class="block px-4 py-2 text-sm text-center text-primary hover:bg-gray-50 transition-colors">View All Notifications</a>
                            </div>
                        </div>

                        <!-- User Dropdown -->
                        <div class="relative" x-data="{ open: false }">
                            <button @click="open = !open" class="flex items-center gap-2 p-1 rounded-full hover:bg-gray-200 transition-colors">
                                <div class="relative">
                                    @if(Auth::user()->profile_picture)
                                        <img src="{{ asset('storage/' . Auth::user()->profile_picture) }}?v={{ Auth::user()->updated_at->timestamp }}" alt="{{ Auth::user()->full_name }}" class="w-10 h-10 rounded-full object-cover">
                                    @else
                                        <span class="w-10 h-10 rounded-full bg-primary text-white flex items-center justify-center font-semibold">
                                            {{ strtoupper(substr(Auth::user()->full_name, 0, 1)) }}
                                        </span>
                                    @endif
                                    <span class="absolute -bottom-0.5 -right-0.5 w-3 h-3 bg-green-500 rounded-full border-2 border-white"></span>
                                </div>
                            </button>
                            <div x-show="open" @click.away="open = false" x-transition
                                 class="absolute right-0 mt-2 w-56 bg-white rounded-lg shadow-lg border border-gray-200 py-1 z-50">
                                <div class="px-4 py-3 border-b border-gray-200">
                                    <p class="font-semibold text-sm">{{ Auth::user()->full_name }}</p>
                                    <p class="text-xs text-gray-500">{{ Auth::user()->email }}</p>
                                </div>
                                <a href="{{ route('member.show', Auth::id()) }}" class="flex items-center gap-2 px-4 py-2 text-sm hover:bg-primary hover:text-white transition-colors">
                                    <i class="bi bi-person"></i>Profile
                                </a>
                                <a href="#" class="flex items-center gap-2 px-4 py-2 text-sm hover:bg-primary hover:text-white transition-colors">
                                    <i class="bi bi-diagram-3"></i>Affiliations
                                </a>
                                <a href="#" class="flex items-center gap-2 px-4 py-2 text-sm hover:bg-primary hover:text-white transition-colors">
                                    <i class="bi bi-calendar-event"></i>Sessions
                                </a>
                                <a href="{{ route('members.index') }}" class="flex items-center gap-2 px-4 py-2 text-sm hover:bg-primary hover:text-white transition-colors">
                                    <i class="bi bi-people"></i>Family
                                </a>
                                <a href="{{ route('bills.index') }}" class="flex items-center gap-2 px-4 py-2 text-sm hover:bg-primary hover:text-white transition-colors">
                                    <i class="bi bi-receipt"></i>Payments & Subscriptions
                                </a>
                                <a href="#" class="flex items-center gap-2 px-4 py-2 text-sm hover:bg-primary hover:text-white transition-colors">
                                    <i class="bi bi-gear"></i>Manage Business
                                </a>
                                <hr class="my-1 border-gray-200">
                                @if(Auth::user()->isSuperAdmin())
                                <a href="{{ route('admin.platform.index') }}" class="flex items-center gap-2 px-4 py-2 text-sm hover:bg-primary hover:text-white transition-colors">
                                    <i class="bi bi-shield-check"></i>Admin Panel
                                </a>
                                <hr class="my-1 border-gray-200">
                                @endif
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="w-full flex items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition-colors">
                                        <i class="bi bi-box-arrow-right"></i>Sign Out
                                    </button>
                                </form>
                            </div>
                        </div>
                    @else
                        <a href="{{ route('login') }}" class="text-sm font-medium hover:text-primary transition-colors">
                            Login
                        </a>
                        <a href="{{ route('register') }}" class="bg-primary text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-primary/90 transition-colors">
                            Register
                        </a>
                    @endauth
                </div>
            </div>
        </div>
    </nav>

    <!-- Back Button -->
    <div class="container mx-auto px-4 md:px-6 pt-4">
        <a href="{{ url()->previous() }}" class="inline-flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground transition-colors">
            <i class="bi bi-arrow-left"></i>
            Back
        </a>
    </div>

    <!-- Main Content -->
    <main>
        @yield('content')
    </main>

    <!-- Footer -->
    <footer class="bg-muted border-t border-border mt-12 py-8">
        <div class="container mx-auto px-4 md:px-6 text-center text-sm text-muted-foreground">
            <p>&copy; {{ date('Y') }} TakeOne. All rights reserved.</p>
        </div>
    </footer>

    <!-- Alpine.js for dropdowns -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    @stack('scripts')
</body>
</html>
