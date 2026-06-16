<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @auth
    <meta name="rt-user" content="{{ Auth::id() }}">
    @endauth

    <title>@yield('title', config('app.name', 'Club SaaS'))</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap Icons (icons only, no Bootstrap CSS) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

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
            bottom: 2px;
            right: 2px;
            width: 10px;
            height: 10px;
            background-color: hsl(150 40% 70%);
            border-radius: 50%;
            border: 2px solid white;
            z-index: 1;
        }

        /* Refined frosted top bar */
        .to-bar {
            position: sticky; top: 0; z-index: 40;
            background: hsl(220 15% 99% / .82);
            backdrop-filter: saturate(180%) blur(14px);
            -webkit-backdrop-filter: saturate(180%) blur(14px);
            border-bottom: 1px solid hsl(210 14% 88% / .8);
        }
        /* Gradient hairline accent under the bar */
        .to-bar::after {
            content: ""; position: absolute; left: 0; right: 0; bottom: -1px; height: 1px;
            background: linear-gradient(90deg, transparent, hsl(var(--primary) / .45), transparent);
        }

        /* Nav icon button — soft rounded square, accent hover, tactile press */
        .nav-icon-btn {
            position: relative;
            width: 2.5rem; height: 2.5rem;
            padding: 0; margin: 0;
            border-radius: 0.75rem;
            color: hsl(220 9% 46%);
            display: flex; align-items: center; justify-content: center;
            transition: background-color .18s ease, color .18s ease, transform .18s cubic-bezier(.22,.61,.36,1);
        }
        .nav-icon-btn:hover { background: hsl(250 60% 92%); color: hsl(var(--primary)); transform: translateY(-1px); }
        .nav-icon-btn:active { transform: scale(.92); }

        /* Notification styles */
        .notification-badge {
            position: absolute;
            top: 2px;
            right: 2px;
            background-color: hsl(0 72% 60%);
            color: white;
            border-radius: 999px;
            min-width: 17px;
            height: 17px;
            padding: 0 4px;
            font-size: 10px;
            line-height: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            box-shadow: 0 0 0 2px hsl(220 15% 99%);
        }
        .notification-badge::after {
            content: ""; position: absolute; inset: 0; border-radius: 999px;
            box-shadow: 0 0 0 0 hsl(0 72% 60% / .55);
            animation: to-badge-pulse 2s ease-out infinite;
        }
        @keyframes to-badge-pulse { 0% { box-shadow: 0 0 0 0 hsl(0 72% 60% / .5); } 70%,100% { box-shadow: 0 0 0 7px hsl(0 72% 60% / 0); } }

        /* Drawer nav links — icon chip + accent hover + active press */
        .drawer-link {
            display: flex; align-items: center; gap: 0.75rem;
            padding: 0.625rem 0.75rem; border-radius: 0.75rem;
            font-size: 0.875rem; font-weight: 500; color: hsl(222 13% 25%);
            transition: background-color .18s ease, color .18s ease, transform .14s cubic-bezier(.22,.61,.36,1);
        }
        .drawer-link i { width: 1.85rem !important; height: 1.85rem; display: inline-flex; align-items: center; justify-content: center;
            border-radius: 0.5rem; background: hsl(220 15% 95%); color: hsl(220 9% 40%); flex-shrink: 0; font-size: 1rem; transition: inherit; }
        .drawer-link:hover { background: hsl(250 60% 96%); color: hsl(var(--primary)); }
        .drawer-link:hover i { background: hsl(250 60% 90%); color: hsl(var(--primary)); }
        .drawer-link:active { transform: scale(.98); }
        .notification-dropdown {
            width: min(320px, calc(100vw - 2rem));
            max-height: 400px;
            overflow-y: auto;
        }
        .notification-item {
            padding: 0.5rem 0.75rem;
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
        @keyframes bell-ring {
            0%, 100% { transform: rotate(0); }
            15% { transform: rotate(14deg); }
            30% { transform: rotate(-12deg); }
            45% { transform: rotate(9deg); }
            60% { transform: rotate(-6deg); }
            75% { transform: rotate(3deg); }
        }
        .bell-ringing {
            animation: bell-ring 0.9s ease-in-out;
            transform-origin: top center;
        }
    </style>

    @stack('styles')
</head>
<body class="bg-background text-foreground antialiased">
    @if(!request()->routeIs('clubs.show.public') && !(session('club.context') && request()->routeIs('register', 'verification.notice')) && !$__env->hasSection('hide-navbar'))
    <div x-data="{ mobileMenuOpen: false }" @keydown.escape.window="mobileMenuOpen = false">
    <nav class="to-bar">
        <div class="container mx-auto px-4">
            <div class="flex items-center justify-between h-16">
                <!-- Logo -->
                <div class="flex items-center gap-2">
                    @stack('navbar-left')
                    <a class="flex items-center font-semibold text-xl transition-transform duration-200 hover:scale-[1.03] active:scale-95" href="{{ Auth::check() ? route('clubs.explore') : url('/') }}">
                        <img src="{{ asset('images/fullLogo.png') }}" alt="TAKEONE" class="h-10">
                    </a>
                </div>

                @auth
                @php
                    // Unread DMs across all of the user's conversations (single query) — used by both the mobile and desktop chat icons.
                    $chatUnread = \Illuminate\Support\Facades\DB::table('messages as m')
                        ->join('conversation_user as cu', 'cu.conversation_id', '=', 'm.conversation_id')
                        ->where('cu.user_id', Auth::id())
                        ->where('m.sender_id', '!=', Auth::id())
                        ->whereRaw('m.created_at > COALESCE(cu.last_read_at, ?)', ['1970-01-01 00:00:00'])
                        ->count();
                @endphp
                @endauth

                <!-- Mobile actions (chat + menu) -->
                <div class="md:hidden flex items-center gap-1">
                    @auth
                    <a href="{{ route('messages.index') }}" class="nav-icon-btn chat-link" title="Messages">
                        <i class="bi bi-chat-dots text-xl"></i>
                        @if($chatUnread > 0)
                            <span class="notification-badge chat-badge">{{ $chatUnread > 99 ? '99+' : $chatUnread }}</span>
                        @endif
                    </a>
                    @endauth
                    <button @click="mobileMenuOpen = true" class="nav-icon-btn" type="button" aria-label="Open menu">
                        <i class="bi bi-list text-2xl"></i>
                    </button>
                </div>

                <!-- Desktop Navigation -->
                <div class="hidden md:flex items-center gap-2">
                    @auth
                        <!-- Exit Impersonation Button -->
                        <x-impersonation-banner />

                        <!-- Explore Button -->
                        <a class="nav-icon-btn" href="{{ route('clubs.explore') }}" title="Explore">
                            <i class="bi bi-compass text-xl"></i>
                        </a>

                        <!-- Messages Dropdown -->
                        <div class="relative" x-data="chatDropdown()">
                            <button @click="toggle()" type="button" class="nav-icon-btn chat-link" title="Messages">
                                <i class="bi bi-chat-dots text-xl"></i>
                                @if($chatUnread > 0)
                                    <span class="notification-badge chat-badge">{{ $chatUnread > 99 ? '99+' : $chatUnread }}</span>
                                @endif
                            </button>
                            <div x-show="open" @click.outside="open = false" x-cloak
                                 x-transition:enter="transition ease-out duration-100"
                                 x-transition:enter-start="opacity-0 scale-95"
                                 x-transition:enter-end="opacity-100 scale-100"
                                 x-transition:leave="transition ease-in duration-75"
                                 x-transition:leave-start="opacity-100 scale-100"
                                 x-transition:leave-end="opacity-0 scale-95"
                                 class="absolute right-0 mt-2 notification-dropdown bg-white rounded-xl shadow-xl ring-1 ring-black/5 border border-border/60 z-50">
                                <div class="flex items-center justify-between px-4 py-3 border-b border-border">
                                    <h6 class="text-sm font-semibold mb-0">Messages</h6>
                                    <a href="{{ route('messages.index') }}" class="text-xs text-primary hover:underline">Open inbox</a>
                                </div>

                                <template x-if="loading">
                                    <div class="px-4 py-8 text-center">
                                        <i class="bi bi-arrow-repeat text-2xl text-gray-300 block mb-2 animate-spin"></i>
                                        <p class="text-sm text-muted-foreground mb-0">Loading…</p>
                                    </div>
                                </template>

                                <template x-if="!loading && chats.length === 0">
                                    <div class="px-4 py-8 text-center">
                                        <i class="bi bi-chat-square-dots text-2xl text-gray-300 block mb-2"></i>
                                        <p class="text-sm text-muted-foreground mb-0">No conversations yet</p>
                                    </div>
                                </template>

                                <template x-for="c in chats" :key="c.id">
                                    <a :href="'/messages/' + c.id" class="notification-item block no-underline" :class="c.unread_count > 0 ? '' : 'opacity-70'">
                                        <div class="flex items-center gap-2.5">
                                            <span class="shrink-0 w-8 h-8 rounded-full bg-accent text-primary flex items-center justify-center overflow-hidden">
                                                <template x-if="c.partner.avatar">
                                                    <img :src="c.partner.avatar" :alt="c.partner.name" class="w-full h-full object-cover">
                                                </template>
                                                <template x-if="!c.partner.avatar">
                                                    <span class="text-xs font-semibold" x-text="c.partner.initial"></span>
                                                </template>
                                            </span>
                                            <div class="flex-1 min-w-0">
                                                <div class="flex items-center gap-2">
                                                    <strong class="text-[13px] leading-tight truncate" :class="c.unread_count > 0 ? 'text-primary' : ''" x-text="c.partner.name"></strong>
                                                    <span x-show="c.unread_count > 0" class="shrink-0 w-1.5 h-1.5 rounded-full bg-primary"></span>
                                                </div>
                                                <p class="mb-0 text-[11px] text-muted-foreground truncate">
                                                    <span x-show="c.last_mine">You: </span><span x-text="c.last_body || 'New message'"></span>
                                                    <span x-show="c.last_at_human"> · <span x-text="c.last_at_human"></span></span>
                                                </p>
                                            </div>
                                            <span x-show="c.unread_count > 0" class="shrink-0 min-w-[18px] h-[18px] px-1 rounded-full bg-primary text-white text-[10px] font-semibold flex items-center justify-center"
                                                  x-text="c.unread_count > 99 ? '99+' : c.unread_count"></span>
                                        </div>
                                    </a>
                                </template>
                            </div>
                        </div>

                        <!-- Notifications Dropdown -->
                        @auth
                        @php
                            $recentNotifs = \App\Models\UserNotification::where('user_id', Auth::id())
                                ->with('clubNotification.tenant')
                                ->latest()
                                ->take(5)
                                ->get();
                            $unreadCount = \App\Models\UserNotification::where('user_id', Auth::id())
                                ->where('is_read', false)
                                ->count();
                        @endphp
                        <div class="relative" x-data="{ open: false }">
                            <button @click="open = !open" id="rt-bell-btn" class="nav-icon-btn" title="Notifications">
                                <i class="bi bi-bell text-xl" id="navNotifBell"></i>
                                @if($unreadCount > 0)
                                    <span class="notification-badge">{{ $unreadCount > 99 ? '99+' : $unreadCount }}</span>
                                @endif
                            </button>
                            <div x-show="open" @click.outside="open = false" x-cloak
                                 x-transition:enter="transition ease-out duration-100"
                                 x-transition:enter-start="opacity-0 scale-95"
                                 x-transition:enter-end="opacity-100 scale-100"
                                 x-transition:leave="transition ease-in duration-75"
                                 x-transition:leave-start="opacity-100 scale-100"
                                 x-transition:leave-end="opacity-0 scale-95"
                                 id="rt-notif-dropdown"
                                 class="absolute right-0 mt-2 notification-dropdown bg-white rounded-xl shadow-xl ring-1 ring-black/5 border border-border/60 z-50">
                                <div id="rt-notif-header" class="flex items-center justify-between px-4 py-3 border-b border-border">
                                    <h6 class="text-sm font-semibold mb-0">Notifications</h6>
                                    @if($unreadCount > 0)
                                        <button onclick="markAllNotificationsRead(this)"
                                                class="text-xs text-primary hover:underline cursor-pointer bg-transparent border-0 p-0">
                                            Mark all read
                                        </button>
                                    @endif
                                </div>

                                @forelse($recentNotifs as $notif)
                                    @php $notifUrl = $notif->clubNotification->action_url; @endphp
                                    <div class="notification-item cursor-pointer {{ $notif->is_read ? 'opacity-70' : '' }}"
                                         onclick="markNotificationRead({{ $notif->id }}, this, @js($notifUrl))">
                                        <div class="flex items-center gap-2.5">
                                            <span class="shrink-0 w-7 h-7 rounded-full bg-accent text-primary flex items-center justify-center">
                                                <i class="bi bi-bell-fill text-xs"></i>
                                            </span>
                                            <div class="flex-1 min-w-0">
                                                <div class="flex items-center gap-2">
                                                    <strong class="text-[13px] leading-tight truncate {{ !$notif->is_read ? 'text-primary' : '' }}">
                                                        {{ Str::limit($notif->clubNotification->subject, 40) }}
                                                    </strong>
                                                    @if(!$notif->is_read)
                                                        <span class="shrink-0 w-1.5 h-1.5 rounded-full bg-primary"></span>
                                                    @endif
                                                </div>
                                                <p class="mb-0 text-[11px] text-muted-foreground truncate">
                                                    {{ $notif->clubNotification->tenant->club_name ?? 'Club' }}
                                                    · {{ $notif->created_at->diffForHumans(null, true, true) }}
                                                </p>
                                            </div>
                                            @if($notifUrl)
                                                <i class="bi bi-chevron-right text-muted-foreground text-xs shrink-0"></i>
                                            @endif
                                        </div>
                                    </div>
                                @empty
                                    <div class="px-4 py-8 text-center">
                                        <i class="bi bi-bell-slash text-2xl text-gray-300 block mb-2"></i>
                                        <p class="text-sm text-muted-foreground mb-0">No notifications yet</p>
                                    </div>
                                @endforelse

                            </div>
                        </div>
                        @endauth

                        <!-- Personal / Business View Switcher (Facebook-style) -->
                        @if(Auth::user()->hasApprovedBusiness())
                        @php $currentViewMode = session('view_mode', 'personal'); @endphp
                        <div class="relative ml-2" x-data="{ open: false }">
                            <button @click="open = !open" type="button"
                                    class="flex items-center gap-2 px-3 py-1.5 rounded-full border border-border bg-card text-sm font-medium text-foreground hover:bg-accent transition-colors cursor-pointer">
                                <i class="bi {{ $currentViewMode === 'business' ? 'bi-buildings' : 'bi-person' }} text-primary"></i>
                                <span class="hidden sm:inline">{{ $currentViewMode === 'business' ? 'Business' : 'Personal' }}</span>
                                <i class="bi bi-chevron-down text-xs text-muted-foreground"></i>
                            </button>
                            <div x-show="open" @click.outside="open = false" x-cloak
                                 x-transition:enter="transition ease-out duration-100"
                                 x-transition:enter-start="opacity-0 scale-95"
                                 x-transition:enter-end="opacity-100 scale-100"
                                 class="absolute right-0 mt-2 w-60 bg-white rounded-xl shadow-xl ring-1 ring-black/5 border border-border/60 z-50 p-1">
                                <p class="px-3 py-2 text-xs font-semibold text-muted-foreground uppercase tracking-wide">Switch view</p>
                                <form action="{{ route('view.switch') }}" method="POST">
                                    @csrf
                                    <input type="hidden" name="mode" value="personal">
                                    <button type="submit" class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-accent transition-colors text-left {{ $currentViewMode === 'personal' ? 'bg-accent/60' : '' }}">
                                        <span class="w-8 h-8 rounded-full bg-muted flex items-center justify-center"><i class="bi bi-person text-foreground"></i></span>
                                        <span class="flex-1">
                                            <span class="block text-sm font-medium text-foreground">Personal</span>
                                            <span class="block text-xs text-muted-foreground">Your member profile</span>
                                        </span>
                                        @if($currentViewMode === 'personal')<i class="bi bi-check-lg text-primary"></i>@endif
                                    </button>
                                </form>
                                <form action="{{ route('view.switch') }}" method="POST">
                                    @csrf
                                    <input type="hidden" name="mode" value="business">
                                    <button type="submit" class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-accent transition-colors text-left {{ $currentViewMode === 'business' ? 'bg-accent/60' : '' }}">
                                        <span class="w-8 h-8 rounded-full bg-accent flex items-center justify-center"><i class="bi bi-buildings text-primary"></i></span>
                                        <span class="flex-1">
                                            <span class="block text-sm font-medium text-foreground">Business</span>
                                            <span class="block text-xs text-muted-foreground">{{ Auth::user()->ownedBusiness->name }}</span>
                                        </span>
                                        @if($currentViewMode === 'business')<i class="bi bi-check-lg text-primary"></i>@endif
                                    </button>
                                </form>
                            </div>
                        </div>
                        @endif

                        <!-- Profile Dropdown -->
                        <div class="relative ml-2" x-data="{ open: false }">
                            <button @click="open = !open" class="flex items-center cursor-pointer rounded-full ring-2 ring-transparent hover:ring-accent transition-all duration-200 hover:scale-105 active:scale-95" type="button">
                                <div class="avatar-container">
                                    @if(Auth::user()->profile_picture)
                                        <img src="{{ asset('storage/' . Auth::user()->profile_picture) }}?v={{ Auth::user()->updated_at->timestamp }}"
                                             alt="{{ Auth::user()->full_name }}"
                                             class="user-avatar"
                                             onerror="this.style.display='none';this.nextElementSibling.style.display='inline-flex';">
                                        <span class="user-avatar-placeholder" style="display:none;">
                                            {{ strtoupper(substr(Auth::user()->full_name, 0, 1)) }}
                                        </span>
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
                                 class="absolute right-0 mt-2 w-56 bg-white rounded-xl shadow-xl ring-1 ring-black/5 border border-border/60 z-50">
                                <div class="px-4 py-3 border-b border-border bg-gradient-to-br from-accent/50 to-transparent rounded-t-xl">
                                    <p class="text-sm font-semibold text-foreground">{{ Auth::user()->full_name }}</p>
                                    <p class="text-xs text-muted-foreground">{{ Auth::user()->email }}</p>
                                </div>
                                <div class="py-1">
                                    <a class="profile-dropdown-item" href="{{ route('member.show', Auth::user()->uuid) }}">
                                        <i class="bi bi-person mr-2"></i>Profile
                                    </a>
                                    <a class="profile-dropdown-item" href="{{ route('member.show', Auth::user()->uuid) }}#affiliations">
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
                                    @php
                                        $ownedBusiness = Auth::user()->ownedBusiness;
                                    @endphp
                                    @if($ownedBusiness && $ownedBusiness->isApproved())
                                    <a class="profile-dropdown-item" href="{{ route('business.dashboard') }}">
                                        <i class="bi bi-buildings mr-2"></i>Manage Business
                                    </a>
                                    @endif
                                    @if(!$ownedBusiness || !$ownedBusiness->isApproved())
                                    <a class="profile-dropdown-item" href="{{ route('business.setup') }}">
                                        <i class="bi bi-buildings mr-2"></i>{{ $ownedBusiness ? 'Business (' . $ownedBusiness->status . ')' : 'Create a Business' }}
                                    </a>
                                    @endif
                                </div>
                                @if(Auth::user()->isSuperAdmin())
                                <div class="border-t border-border py-1">
                                    <a class="profile-dropdown-item" href="{{ route('admin.platform.index') }}">
                                        <i class="bi bi-shield-check mr-2"></i>Admin Panel
                                    </a>
                                </div>
                                @endif
                                <div class="border-t border-border py-1">
                                    <a class="profile-dropdown-item" href="{{ route('security.show') }}">
                                        <i class="bi bi-shield-lock mr-2"></i>Security
                                    </a>
                                </div>
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
        </div>
    </nav>

    <!-- Mobile Backdrop -->
    <div x-show="mobileMenuOpen"
         @click="mobileMenuOpen = false"
         x-cloak
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-black/50 z-50 md:hidden">
    </div>

    <!-- Mobile Sidebar Drawer -->
    <div x-show="mobileMenuOpen"
         x-cloak
         x-transition:enter="transition ease-out duration-300 transform"
         x-transition:enter-start="translate-x-full"
         x-transition:enter-end="translate-x-0"
         x-transition:leave="transition ease-in duration-200 transform"
         x-transition:leave-start="translate-x-0"
         x-transition:leave-end="translate-x-full"
         class="fixed inset-y-0 right-0 w-72 max-w-[85vw] bg-background z-50 md:hidden shadow-2xl flex flex-col overflow-hidden">

        <!-- Drawer Header -->
        <div class="m-hero flex items-center justify-between px-4 h-16 shrink-0">
            <img src="{{ asset('images/fullLogo.png') }}" alt="TAKEONE" class="h-8 relative z-10 brightness-0 invert">
            <button @click="mobileMenuOpen = false" class="relative z-10 w-9 h-9 rounded-xl bg-white/15 backdrop-blur flex items-center justify-center text-white hover:bg-white/25 active:scale-90 transition" type="button" aria-label="Close menu">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <!-- Drawer Content -->
        <div class="flex-1 overflow-y-auto p-4 flex flex-col">
            @auth
                <!-- User Info -->
                <div class="flex items-center gap-3 p-3 mb-4 bg-white rounded-lg">
                    <div class="avatar-container">
                        @if(Auth::user()->profile_picture)
                            <img src="{{ asset('storage/' . Auth::user()->profile_picture) }}?v={{ Auth::user()->updated_at->timestamp }}"
                                 alt="{{ Auth::user()->full_name }}"
                                 class="user-avatar"
                                 onerror="this.style.display='none';this.nextElementSibling.style.display='inline-flex';">
                            <span class="user-avatar-placeholder" style="display:none;">
                                {{ strtoupper(substr(Auth::user()->full_name, 0, 1)) }}
                            </span>
                        @else
                            <span class="user-avatar-placeholder">
                                {{ strtoupper(substr(Auth::user()->full_name, 0, 1)) }}
                            </span>
                        @endif
                        <span class="online-indicator"></span>
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold truncate">{{ Auth::user()->full_name }}</p>
                        <p class="text-xs text-muted-foreground truncate">{{ Auth::user()->email }}</p>
                    </div>
                </div>

                <!-- Personal / Business View Switcher (mobile) -->
                @if(Auth::user()->hasApprovedBusiness())
                @php $currentViewMode = session('view_mode', 'personal'); @endphp
                <div class="mb-4">
                    <p class="px-1 pb-2 text-xs font-semibold text-muted-foreground uppercase tracking-wide">Switch view</p>
                    <div class="grid grid-cols-2 gap-2">
                        <form action="{{ route('view.switch') }}" method="POST">
                            @csrf
                            <input type="hidden" name="mode" value="personal">
                            <button type="submit" class="w-full flex flex-col items-center gap-1 px-2 py-3 rounded-lg border transition-colors {{ $currentViewMode === 'personal' ? 'border-primary bg-accent text-primary' : 'border-border bg-white text-foreground' }}">
                                <i class="bi bi-person text-lg"></i>
                                <span class="text-xs font-medium">Personal</span>
                            </button>
                        </form>
                        <form action="{{ route('view.switch') }}" method="POST">
                            @csrf
                            <input type="hidden" name="mode" value="business">
                            <button type="submit" class="w-full flex flex-col items-center gap-1 px-2 py-3 rounded-lg border transition-colors {{ $currentViewMode === 'business' ? 'border-primary bg-accent text-primary' : 'border-border bg-white text-foreground' }}">
                                <i class="bi bi-buildings text-lg"></i>
                                <span class="text-xs font-medium">Business</span>
                            </button>
                        </form>
                    </div>
                </div>
                @endif

                <!-- Nav Links (mirrors the desktop profile menu) -->
                @php $ownedBusinessMobile = Auth::user()->ownedBusiness; @endphp
                <nav class="space-y-1">
                    <a class="drawer-link" href="{{ route('clubs.explore') }}">
                        <i class="bi bi-compass text-lg w-5 text-center"></i>Explore
                    </a>
                    <a class="drawer-link" href="{{ route('messages.index') }}">
                        <i class="bi bi-chat-dots text-lg w-5 text-center"></i>Messages
                        @if(($chatUnread ?? 0) > 0)
                            <span class="ml-auto min-w-[18px] h-[18px] px-1 rounded-full bg-primary text-white text-[10px] font-bold flex items-center justify-center">{{ $chatUnread > 99 ? '99+' : $chatUnread }}</span>
                        @endif
                    </a>
                    <a class="drawer-link" href="{{ route('member.show', Auth::user()->uuid) }}">
                        <i class="bi bi-person text-lg w-5 text-center"></i>Profile
                    </a>
                    <a class="drawer-link" href="{{ route('member.show', Auth::user()->uuid) }}#affiliations">
                        <i class="bi bi-diagram-3 text-lg w-5 text-center"></i>Affiliations
                    </a>
                    <a class="drawer-link" href="#">
                        <i class="bi bi-calendar-event text-lg w-5 text-center"></i>Sessions
                    </a>
                    <a class="drawer-link" href="{{ route('members.index') }}">
                        <i class="bi bi-people text-lg w-5 text-center"></i>Family
                    </a>
                    <a class="drawer-link" href="{{ route('bills.index') }}">
                        <i class="bi bi-receipt text-lg w-5 text-center"></i>Payments &amp; Subscriptions
                    </a>
                    @if($ownedBusinessMobile && $ownedBusinessMobile->isApproved())
                    <a class="drawer-link" href="{{ route('business.dashboard') }}">
                        <i class="bi bi-buildings text-lg w-5 text-center"></i>Manage Business
                    </a>
                    @else
                    <a class="drawer-link" href="{{ route('business.setup') }}">
                        <i class="bi bi-buildings text-lg w-5 text-center"></i>{{ $ownedBusinessMobile ? 'Business (' . $ownedBusinessMobile->status . ')' : 'Create a Business' }}
                    </a>
                    @endif
                    @if(Auth::user()->isSuperAdmin())
                    <a class="drawer-link" href="{{ route('admin.platform.index') }}">
                        <i class="bi bi-shield-check text-lg w-5 text-center"></i>Admin Panel
                    </a>
                    @endif
                    <a class="drawer-link" href="{{ route('security.show') }}">
                        <i class="bi bi-shield-lock text-lg w-5 text-center"></i>Security
                    </a>
                </nav>

                <div class="mt-auto pt-4 border-t border-border">
                    @if(session()->has('impersonate.original_id'))
                    <form method="POST" action="{{ route('impersonate.leave') }}">
                        @csrf
                        <button type="submit" class="drawer-link w-full !text-amber-700">
                            <i class="bi bi-incognito text-lg w-5 text-center"></i>Exit impersonation
                        </button>
                    </form>
                    @endif
                    <a class="drawer-link !text-destructive" href="{{ route('logout') }}"
                       onclick="event.preventDefault(); document.getElementById('logout-form-mobile').submit();">
                        <i class="bi bi-box-arrow-right text-lg w-5 text-center"></i>Sign Out
                    </a>
                    <form id="logout-form-mobile" action="{{ route('logout') }}" method="POST" class="hidden">
                        @csrf
                    </form>
                </div>
            @endauth

            @guest
                <nav class="space-y-1">
                    <a class="drawer-link" href="{{ route('login') }}">
                        <i class="bi bi-box-arrow-in-right text-lg w-5 text-center"></i>Login
                    </a>
                    @if (Route::has('register'))
                        <a class="drawer-link" href="{{ route('register') }}">
                            <i class="bi bi-person-plus text-lg w-5 text-center"></i>Register
                        </a>
                    @endif
                </nav>
            @endguest
        </div>
    </div>
    </div>
    @endif

    <main>
        @yield('content')
    </main>

    <!-- Confirm Dialog -->
    <x-confirm-dialog />

    <!-- Toast Container (Alpine.js) -->
    <div x-data="toastManager()" x-init="init()" class="fixed top-20 right-4 z-50 space-y-2 pointer-events-none">
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
                 class="flex items-center gap-3 px-4 py-3 rounded-lg shadow-lg w-[min(300px,calc(100vw-2rem))] border-0">
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
                    // Surface ALL server-side flash + validation messages as toasts —
                    // never as inline page banners (banners removed from individual views).
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
                    @if(session('status'))
                        this.addToast('info', @json(session('status')));
                    @endif
                    @if(session('message') && is_string(session('message')))
                        this.addToast('info', @json(session('message')));
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
        // Request location permission for authenticated users (cache for 10 minutes to avoid repeated iOS prompts)
        document.addEventListener('DOMContentLoaded', function() {
            if ('geolocation' in navigator) {
                const cached = localStorage.getItem('userLocation');
                const cacheAge = cached ? (Date.now() - new Date(JSON.parse(cached).timestamp).getTime()) : Infinity;
                if (cacheAge > 10 * 60 * 1000) {
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
                        { enableHighAccuracy: true, timeout: 10000, maximumAge: 600000 }
                    );
                }
            }
        });
        @endauth
    </script>

    <!-- jQuery (required for plugins and legacy code) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- Bootstrap Bridge: handles data-bs-* attributes without Bootstrap JS -->
    <script>
    (function() {
        // --- Modal Support ---
        function showModal(modal) {
            if (!modal) return;
            // Create backdrop
            let backdrop = document.getElementById('bs-bridge-backdrop');
            if (!backdrop) {
                backdrop = document.createElement('div');
                backdrop.id = 'bs-bridge-backdrop';
                backdrop.className = 'modal-backdrop';
                backdrop.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:60;';
                backdrop.addEventListener('click', function() {
                    const openModal = document.querySelector('.modal.show');
                    if (openModal && !openModal.hasAttribute('data-bs-backdrop')) hideModal(openModal);
                });
            }
            document.body.appendChild(backdrop);
            document.body.style.overflow = 'hidden';
            modal.style.display = 'block';
            modal.classList.add('show');
            modal.style.zIndex = '70';
            modal.style.position = 'fixed';
            modal.style.inset = '0';
            modal.style.overflowY = 'auto';
            // Fire shown event
            modal.dispatchEvent(new Event('shown.bs.modal'));
            // jQuery event compat
            if (window.jQuery) jQuery(modal).trigger('shown.bs.modal');
        }

        function hideModal(modal) {
            if (!modal) return;
            modal.classList.remove('show');
            modal.style.display = '';
            modal.style.zIndex = '';
            modal.style.position = '';
            modal.style.inset = '';
            modal.style.overflowY = '';
            const backdrop = document.getElementById('bs-bridge-backdrop');
            if (backdrop) backdrop.remove();
            document.body.style.overflow = '';
            // Fire hidden event
            modal.dispatchEvent(new Event('hidden.bs.modal'));
            if (window.jQuery) jQuery(modal).trigger('hidden.bs.modal');
        }

        // jQuery .modal() compat
        if (window.jQuery) {
            const origFn = jQuery.fn.modal;
            jQuery.fn.modal = function(action) {
                return this.each(function() {
                    if (action === 'hide') hideModal(this);
                    else if (action === 'show') showModal(this);
                });
            };
        }

        // --- Alert Dismiss Support ---
        function dismissAlert(alert) {
            if (!alert) return;
            alert.style.transition = 'opacity 0.15s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 150);
        }

        // --- Tab Support ---
        function activateTab(tabBtn) {
            const target = tabBtn.getAttribute('data-bs-target');
            if (!target) return;
            // Deactivate siblings
            const parent = tabBtn.closest('.nav, [role="tablist"]');
            if (parent) {
                parent.querySelectorAll('[data-bs-toggle="tab"], [data-bs-toggle="pill"]').forEach(btn => {
                    btn.classList.remove('active');
                    btn.setAttribute('aria-selected', 'false');
                    const pane = document.querySelector(btn.getAttribute('data-bs-target'));
                    if (pane) { pane.classList.remove('show', 'active'); pane.style.display = 'none'; }
                });
            }
            // Activate clicked
            tabBtn.classList.add('active');
            tabBtn.setAttribute('aria-selected', 'true');
            const pane = document.querySelector(target);
            if (pane) { pane.classList.add('show', 'active'); pane.style.display = ''; }
        }

        // --- Dropdown Support ---
        function toggleDropdown(btn) {
            const menu = btn.nextElementSibling || btn.parentElement.querySelector('.dropdown-menu');
            if (!menu) return;
            const isOpen = menu.classList.contains('show');
            // Close all open dropdowns first
            document.querySelectorAll('.dropdown-menu.show').forEach(m => {
                m.classList.remove('show');
                m.style.display = '';
            });
            if (!isOpen) {
                menu.classList.add('show');
                menu.style.display = 'block';
            }
        }

        // --- Tooltip Support (CSS-only title attr) ---
        // data-bs-toggle="tooltip" just uses native title - no action needed

        // --- Event Delegation ---
        document.addEventListener('click', function(e) {
            const target = e.target.closest('[data-bs-toggle]');
            if (target) {
                const toggle = target.getAttribute('data-bs-toggle');
                if (toggle === 'modal') {
                    e.preventDefault();
                    const selector = target.getAttribute('data-bs-target');
                    if (selector) showModal(document.querySelector(selector));
                } else if (toggle === 'tab' || toggle === 'pill') {
                    e.preventDefault();
                    activateTab(target);
                } else if (toggle === 'dropdown') {
                    e.preventDefault();
                    e.stopPropagation();
                    toggleDropdown(target);
                }
            }

            // Dismiss handlers
            const dismissTarget = e.target.closest('[data-bs-dismiss]');
            if (dismissTarget) {
                const dismiss = dismissTarget.getAttribute('data-bs-dismiss');
                if (dismiss === 'modal') {
                    e.preventDefault();
                    hideModal(dismissTarget.closest('.modal'));
                } else if (dismiss === 'alert') {
                    e.preventDefault();
                    dismissAlert(dismissTarget.closest('.alert'));
                }
            }

            // Close dropdowns when clicking outside
            if (!e.target.closest('.dropdown')) {
                document.querySelectorAll('.dropdown-menu.show').forEach(m => {
                    m.classList.remove('show');
                    m.style.display = '';
                });
            }
        });

        // Close modals with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const openModal = document.querySelector('.modal.show');
                if (openModal && !openModal.hasAttribute('data-bs-backdrop')) hideModal(openModal);
            }
        });

        // Expose globally for programmatic use
        window.bsModal = { show: showModal, hide: hideModal };

        // --- Bootstrap constructor compat ---
        window.bootstrap = window.bootstrap || {};
        window.bootstrap.Modal = function(el) {
            this._el = el;
            this.show = function() { showModal(el); };
            this.hide = function() { hideModal(el); };
        };
        window.bootstrap.Tab = function(el) {
            this._el = el;
            this.show = function() { activateTab(el); };
        };
        window.bootstrap.Tooltip = function(el) {
            // CSS-only tooltip via title attr - no-op
        };
    })();
    </script>

    <!-- Vite Compiled Assets -->
    @vite(['resources/js/app.js'])

    @stack('scripts')

    <!-- Modals Stack (for cropper and other modals) -->
    @stack('modals')

    <script>
        // Soft two-tone chime synthesized on the fly — no audio asset needed.
        function playNotificationChime() {
            try {
                const Ctx = window.AudioContext || window.webkitAudioContext;
                if (!Ctx) return;
                const ctx = new Ctx();
                if (ctx.state === 'suspended') ctx.resume();
                const now = ctx.currentTime;
                [880, 1174.66].forEach((freq, i) => {
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    osc.type = 'sine';
                    osc.frequency.value = freq;
                    const start = now + i * 0.13;
                    gain.gain.setValueAtTime(0.0001, start);
                    gain.gain.exponentialRampToValueAtTime(0.07, start + 0.02);
                    gain.gain.exponentialRampToValueAtTime(0.0001, start + 0.5);
                    osc.connect(gain).connect(ctx.destination);
                    osc.start(start);
                    osc.stop(start + 0.55);
                });
                setTimeout(() => ctx.close(), 1500);
            } catch (e) { /* audio unavailable — fail silently */ }
        }

        // Ring (sound + shake) once when a notification newer than the last one
        // the user saw shows up. Deduped via localStorage so navigating between
        // pages does not re-trigger it for the same notification.
        @auth
        (function () {
            const latestId = {{ optional(($recentNotifs ?? collect())->first())->id ?? 0 }};
            const unread = {{ (int) ($unreadCount ?? 0) }};
            if (!latestId || unread === 0) return;

            const KEY = 'takeone:lastSeenNotifId';
            const lastSeen = parseInt(localStorage.getItem(KEY) || '0', 10);

            if (latestId > lastSeen) {
                const fire = () => {
                    playNotificationChime();
                    const bell = document.getElementById('navNotifBell');
                    if (bell) {
                        bell.classList.add('bell-ringing');
                        bell.addEventListener('animationend', () => bell.classList.remove('bell-ringing'), { once: true });
                    }
                };
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', fire, { once: true });
                } else {
                    fire();
                }
            }
            localStorage.setItem(KEY, String(latestId));
        })();

        // ── Live notifications over MQTT (takeone/realtime) ──
        // realtime.js dispatches 'realtime:notification' with the new record;
        // we patch the bell badge + dropdown in place (no reload), then ring.
        function rtRingBell() {
            playNotificationChime();
            const bell = document.getElementById('navNotifBell');
            if (bell) {
                bell.classList.add('bell-ringing');
                bell.addEventListener('animationend', () => bell.classList.remove('bell-ringing'), { once: true });
            }
        }

        function rtBumpBadge() {
            let badge = document.querySelector('.notification-badge');
            if (badge) {
                const next = (parseInt(badge.textContent) || 0) + 1;
                badge.textContent = next > 99 ? '99+' : next;
            } else {
                const btn = document.getElementById('rt-bell-btn');
                if (!btn) return;
                badge = document.createElement('span');
                badge.className = 'notification-badge';
                badge.textContent = '1';
                btn.appendChild(badge);
            }
        }

        function rtPrependNotification(n) {
            const dropdown = document.getElementById('rt-notif-dropdown');
            const header   = document.getElementById('rt-notif-header');
            if (!dropdown || !header) return;

            // Drop the "No notifications yet" empty state if present.
            dropdown.querySelector('.bi-bell-slash')?.closest('div')?.remove();

            const subject  = (n.subject || 'Notification').slice(0, 40);
            const club     = n.club_name || 'Club';
            const when     = n.created_at_human || 'just now';
            const url      = n.action_url || null;

            const item = document.createElement('div');
            item.className = 'notification-item cursor-pointer';
            item.setAttribute('onclick', `markNotificationRead(${Number(n.id)}, this, ${url ? JSON.stringify(url) : 'null'})`);
            item.innerHTML =
                '<div class="flex items-center gap-2.5">' +
                    '<span class="shrink-0 w-7 h-7 rounded-full bg-accent text-primary flex items-center justify-center">' +
                        '<i class="bi bi-bell-fill text-xs"></i></span>' +
                    '<div class="flex-1 min-w-0">' +
                        '<div class="flex items-center gap-2">' +
                            '<strong class="text-[13px] leading-tight truncate text-primary"></strong>' +
                            '<span class="shrink-0 w-1.5 h-1.5 rounded-full bg-primary"></span>' +
                        '</div>' +
                        '<p class="mb-0 text-[11px] text-muted-foreground truncate"></p>' +
                    '</div>' +
                    (url ? '<i class="bi bi-chevron-right text-muted-foreground text-xs shrink-0"></i>' : '') +
                '</div>';
            // Assign user-controlled text via textContent to avoid HTML injection.
            item.querySelector('strong').textContent = subject;
            item.querySelector('p').textContent = club + ' · ' + when;

            header.insertAdjacentElement('afterend', item);

            // Keep the dropdown trimmed to the latest few entries.
            const items = dropdown.querySelectorAll('.notification-item');
            if (items.length > 6) items[items.length - 1].remove();

            // Ensure a "Mark all read" control exists now there is an unread item.
            if (!header.querySelector('button')) {
                const b = document.createElement('button');
                b.setAttribute('onclick', 'markAllNotificationsRead(this)');
                b.className = 'text-xs text-primary hover:underline cursor-pointer bg-transparent border-0 p-0';
                b.textContent = 'Mark all read';
                header.appendChild(b);
            }
        }

        window.addEventListener('realtime:notification', (e) => {
            const n = e.detail || {};
            rtPrependNotification(n);
            rtBumpBadge();
            rtRingBell();
            if (n.id) localStorage.setItem('takeone:lastSeenNotifId', String(n.id));
        });

        // Soft "message" sound — gentler/shorter than the notification chime.
        window.playMessageSound = function () {
            try {
                const Ctx = window.AudioContext || window.webkitAudioContext;
                if (!Ctx) return;
                const ctx = new Ctx();
                if (ctx.state === 'suspended') ctx.resume();
                const now = ctx.currentTime;
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'sine';
                osc.frequency.setValueAtTime(660, now);
                osc.frequency.exponentialRampToValueAtTime(990, now + 0.08);
                gain.gain.setValueAtTime(0.0001, now);
                gain.gain.exponentialRampToValueAtTime(0.05, now + 0.02);
                gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.3);
                osc.connect(gain).connect(ctx.destination);
                osc.start(now); osc.stop(now + 0.32);
                setTimeout(() => ctx.close(), 800);
            } catch (e) { /* audio unavailable — ignore */ }
        };

        // Update the chat unread badge on every chat icon (mobile + desktop)
        // by a delta (clamped at 0). The Messenger page manages it precisely;
        // everywhere else we just +1 on arrival.
        window.updateChatBadge = function (delta) {
            document.querySelectorAll('.chat-link').forEach((btn) => {
                let badge = btn.querySelector('.chat-badge');
                const current = badge ? (parseInt(badge.textContent) || 0) : 0;
                const next = Math.max(0, current + delta);
                if (next <= 0) { badge?.remove(); return; }
                if (!badge) {
                    badge = document.createElement('span');
                    badge.className = 'notification-badge chat-badge';
                    btn.appendChild(badge);
                }
                badge.textContent = next > 99 ? '99+' : next;
            });
        };

        // Top-bar Messages dropdown: lists conversations with unread messages,
        // fetched on open from the Messenger inbox JSON endpoint.
        window.chatDropdown = function () {
            return {
                open: false,
                loading: false,
                chats: [],
                toggle() {
                    this.open = !this.open;
                    if (this.open) this.load();
                },
                load() {
                    this.loading = true;
                    fetch('{{ route('messages.conversations') }}', {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    })
                        .then((r) => r.json())
                        .then((data) => {
                            this.chats = (data && data.conversations) || [];
                        })
                        .catch(() => { this.chats = []; })
                        .finally(() => { this.loading = false; });
                },
            };
        };

        // Live DMs: the Messenger screen patches itself in place. Elsewhere we
        // surface a toast + soft sound and bump the header chat badge.
        window.addEventListener('realtime:message', (e) => {
            if (window.location.pathname.startsWith('/messages')) return;
            const m = e.detail || {};
            // Club-room messages are handled by the Club Chat page itself (its own
            // tone + unread); never surface them through the DM toast/badge.
            if (m.club_room) return;
            // Edits and deletes patch open threads silently — never toast, bump
            // the badge, or play a sound for them.
            if (m.action === 'edit' || m.action === 'delete') return;
            // Skip the toast/badge/sound when the user is actively reading this
            // conversation in an expanded mobile chat head — it handles it itself.
            if (typeof window.__chatHeadActive === 'function' && window.__chatHeadActive(m.conversation_id)) return;
            const who = m.from_name || m.club_name || 'New message';
            const body = (m.body || '').slice(0, 80);
            if (window.showToast) window.showToast('info', who + ': ' + body);
            // The top-bar chat icon lists Messenger DMs only (its dropdown loads
            // from the Messenger inbox). Club messages ride the same realtime
            // channel but live on the club Messages page — bumping the chat badge
            // for them produces a count with an empty dropdown, so only count DMs.
            if (m.conversation_id) window.updateChatBadge(1);
            window.playMessageSound();
        });

        // ── Chat attachments (pictures & files), shared by every chat UI.
        //    Images are downscaled client-side, then uploaded (multipart) to be
        //    stored encrypted-at-rest with an expiry; the thread renders them
        //    from an authorised URL. ──
        window.ChatAttach = {
            MAX_BYTES: 8 * 1024 * 1024, // mirrors the server cap (8 MB)

            loadImage(file) {
                return new Promise((res, rej) => {
                    const u = URL.createObjectURL(file);
                    const im = new Image();
                    im.onload = () => { URL.revokeObjectURL(u); res(im); };
                    im.onerror = (e) => { URL.revokeObjectURL(u); rej(e); };
                    im.src = u;
                });
            },
            // Downscale big photos to a sane size/quality; fall back to original.
            async toBlob(file) {
                try {
                    const img = await this.loadImage(file);
                    const maxDim = 1600;
                    const scale = Math.min(1, maxDim / Math.max(img.width, img.height));
                    const c = document.createElement('canvas');
                    c.width = Math.max(1, Math.round(img.width * scale));
                    c.height = Math.max(1, Math.round(img.height * scale));
                    c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
                    const blob = await new Promise((r) => c.toBlob(r, 'image/jpeg', 0.85));
                    return blob || file;
                } catch (e) { return file; }
            },
            humanSize(b) { return b < 1024 ? b + ' B' : (b < 1048576 ? (b / 1024).toFixed(0) + ' KB' : (b / 1048576).toFixed(1) + ' MB'); },

            /** Upload a file; resolves to the stored message object, or null. */
            async send(convId, file) {
                if (!convId || !file) return null;
                const isImage = (file.type || '').startsWith('image/');
                let blob = file, filename = file.name || 'file';
                if (isImage) {
                    blob = await this.toBlob(file);
                    filename = (file.name || 'photo').replace(/\.[^.]+$/, '') + '.jpg';
                }
                if (blob.size > this.MAX_BYTES) {
                    window.showToast && window.showToast('error', 'File is too large (max 8 MB).');
                    return null;
                }
                const fd = new FormData();
                fd.append('file', blob, filename);
                try {
                    const r = await fetch(`/messages/${convId}/attachments`, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' },
                        body: fd, // browser sets multipart boundary
                    });
                    const d = await r.json();
                    if (!d.success) { window.showToast && window.showToast('error', d.message || 'Could not send file.'); return null; }
                    return d.data; // presented message (mine:true) with attachment.url
                } catch (e) { window.showToast && window.showToast('error', 'Could not send file.'); return null; }
            },
        };

        // ── Custom audio player (mobile chat bubbles). Colour comes from Tailwind
        //    classes bound on m.mine in the markup; this only drives behaviour. ──
        window.audioPlayer = () => ({
            playing: false, current: 0, duration: 0, ready: false, dragging: false, bars: [],
            get pct() { return this.duration ? Math.min(100, this.current / this.duration * 100) : 0; },
            get timeLabel() { return this.fmt((this.playing || this.current > 0) ? this.current : this.duration); },
            barPlayed(i) { return ((i + 0.5) / this.bars.length) * 100 <= this.pct; },
            init() {
                // Deterministic faux-waveform (stable across renders, no layout shift).
                this.bars = Array.from({ length: 34 }, (_, i) =>
                    34 + Math.round(Math.abs(Math.sin(i * 0.9) * Math.cos(i * 0.37) + Math.sin(i * 0.5)) * 46));
                const a = this.$refs.audio;
                a.addEventListener('loadedmetadata', () => { this.duration = isFinite(a.duration) ? a.duration : 0; this.ready = true; });
                a.addEventListener('timeupdate', () => { if (!this.dragging) this.current = a.currentTime; });
                a.addEventListener('play', () => {
                    this.playing = true;
                    if (window.__audioEl && window.__audioEl !== a) window.__audioEl.pause();
                    window.__audioEl = a; // only one plays at a time
                });
                a.addEventListener('pause', () => this.playing = false);
                a.addEventListener('ended', () => { this.playing = false; this.current = 0; });
            },
            toggle() { const a = this.$refs.audio; a.paused ? a.play() : a.pause(); },
            _ratio(e) {
                const rect = this.$refs.track.getBoundingClientRect();
                const x = (e.clientX ?? (e.touches && e.touches[0] && e.touches[0].clientX) ?? 0) - rect.left;
                return Math.min(1, Math.max(0, x / rect.width));
            },
            startDrag(e) { this.dragging = true; try { this.$refs.track.setPointerCapture(e.pointerId); } catch (_) {} this.seek(e); },
            onDrag(e) { if (this.dragging) this.seek(e); },
            endDrag(e) { if (this.dragging) { this.dragging = false; try { this.$refs.track.releasePointerCapture(e.pointerId); } catch (_) {} } },
            seek(e) { const a = this.$refs.audio; if (!a.duration) return; a.currentTime = this._ratio(e) * a.duration; this.current = a.currentTime; },
            fmt(s) { s = Math.floor(s || 0); return Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0'); },
        });

        // ── Link previews: detect URLs in message text, linkify them, and fetch
        //    Open-Graph metadata / video embeds (cached per URL). ──
        window.LinkPreview = {
            _cache: new Map(),
            esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c])); },
            extractUrl(text) { const m = String(text || '').match(/(https?:\/\/[^\s<]+)/i); return m ? m[0].replace(/[.,;:!?)\]]+$/, '') : null; },
            linkifyHtml(text) {
                return this.esc(text).replace(/(https?:\/\/[^\s<]+)/gi, (u) => {
                    const clean = u.replace(/[.,;:!?)\]]+$/, '');
                    return `<a href="${clean}" target="_blank" rel="noopener noreferrer" class="underline break-all">${clean}</a>`;
                });
            },
            fetch(url) {
                if (this._cache.has(url)) return this._cache.get(url);
                const p = fetch(`/messages/link-preview?url=${encodeURIComponent(url)}`, { headers: { 'Accept': 'application/json' } })
                    .then((r) => r.json()).then((d) => (d && d.success ? d.preview : null)).catch(() => null);
                this._cache.set(url, p);
                return p;
            },
        };
        // Club-room message body: escape + linkify + highlight @mentions.
        window.renderRoomBody = (text) => {
            let h = window.LinkPreview.linkifyHtml(text);
            return h.replace(/(^|[\s>(])@([\p{L}\p{N}_.\-]{2,40})/gu, (m, p, n) => `${p}<span class="font-semibold underline decoration-dotted">@${n}</span>`);
        };
        window.linkCard = () => ({
            preview: null, fetched: false,
            load(text) {
                if (this.fetched) return; this.fetched = true;
                const url = window.LinkPreview.extractUrl(text);
                if (url) window.LinkPreview.fetch(url).then((p) => { this.preview = p; });
            },
        });
        @endauth

        function markNotificationRead(id, el, url = null) {
            const wasUnread = !el.classList.contains('opacity-70');

            const done = fetch('{{ route('notifications.mark-read') }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ notification_id: id })
            }).then(() => {
                el.classList.add('opacity-70');
                el.querySelector('strong')?.classList.remove('text-primary');
                if (wasUnread) updateBadgeCount(-1);
            });

            // When the notification carries an action target, navigate there once
            // it has been marked read so the badge count stays accurate.
            if (url) {
                done.finally(() => { window.location.href = url; });
            }
        }

        function markAllNotificationsRead(btn) {
            fetch('{{ route('notifications.mark-read') }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({})
            }).then(() => {
                document.querySelectorAll('.notification-item').forEach(el => {
                    el.classList.add('opacity-70');
                    el.querySelector('strong')?.classList.remove('text-primary');
                });
                document.querySelector('.notification-badge')?.remove();
                btn.remove();
            });
        }

        function updateBadgeCount(delta) {
            const badge = document.querySelector('.notification-badge');
            if (!badge) return;
            const current = parseInt(badge.textContent) || 0;
            const next = current + delta;
            if (next <= 0) {
                badge.remove();
            } else {
                badge.textContent = next > 99 ? '99+' : next;
            }
        }
    </script>
</body>
</html>
