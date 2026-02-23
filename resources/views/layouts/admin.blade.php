@extends('layouts.app')

@section('content')
<div x-data="{ sidebarOpen: false }">

<!-- Mobile Sidebar Toggle -->
<div class="lg:hidden sticky top-16 z-40 bg-background border-b border-border p-4">
    <button @click="sidebarOpen = !sidebarOpen"
            class="flex items-center gap-2 px-4 py-2 border border-border rounded-lg bg-white font-semibold cursor-pointer">
        <i class="bi bi-list" x-show="!sidebarOpen"></i>
        <i class="bi bi-x-lg" x-show="sidebarOpen" x-cloak></i>
        <span>Admin Panel</span>
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

<!-- Admin Wrapper - BOXED -->
<div class="max-w-7xl mx-auto px-4 py-5 flex flex-col lg:flex-row gap-5">
    <!-- Sidebar -->
    <aside class="w-full lg:w-64 lg:min-w-64 bg-muted/30 border border-border rounded-xl p-6 h-fit lg:sticky lg:top-24"
           :class="{ 'hidden lg:block': !sidebarOpen, 'block relative z-40': sidebarOpen }">
        <h2 class="text-xs font-semibold text-muted-foreground uppercase tracking-wide mb-4">Admin Panel</h2>
        <nav class="flex flex-col gap-2">
            <a href="{{ route('admin.platform.clubs') }}"
               class="flex items-start gap-3 p-4 rounded-lg transition-all no-underline
                      {{ request()->routeIs('admin.platform.clubs*') || request()->routeIs('admin.platform.index')
                         ? 'bg-primary text-white shadow-lg'
                         : 'bg-card text-foreground hover:bg-muted hover:shadow-md' }}">
                <i class="bi bi-building w-5 h-5 flex-shrink-0 mt-0.5"></i>
                <div class="flex-1 text-left">
                    <p class="font-semibold text-sm m-0">All Clubs</p>
                    <p class="text-xs m-0 mt-1 {{ request()->routeIs('admin.platform.clubs*') || request()->routeIs('admin.platform.index') ? 'text-white/80' : 'text-muted-foreground' }}">
                        Manage {{ $clubsCount ?? 0 }} {{ ($clubsCount ?? 0) === 1 ? 'club' : 'clubs' }}
                    </p>
                </div>
            </a>
            <a href="{{ route('admin.platform.members') }}"
               class="flex items-start gap-3 p-4 rounded-lg transition-all no-underline
                      {{ request()->routeIs('admin.platform.members*')
                         ? 'bg-primary text-white shadow-lg'
                         : 'bg-card text-foreground hover:bg-muted hover:shadow-md' }}">
                <i class="bi bi-people w-5 h-5 flex-shrink-0 mt-0.5"></i>
                <div class="flex-1 text-left">
                    <p class="font-semibold text-sm m-0">All Members</p>
                    <p class="text-xs m-0 mt-1 {{ request()->routeIs('admin.platform.members*') ? 'text-white/80' : 'text-muted-foreground' }}">
                        View all platform members
                    </p>
                </div>
            </a>
            <a href="{{ route('admin.platform.backup') }}"
               class="flex items-start gap-3 p-4 rounded-lg transition-all no-underline
                      {{ request()->routeIs('admin.platform.backup*')
                         ? 'bg-primary text-white shadow-lg'
                         : 'bg-card text-foreground hover:bg-muted hover:shadow-md' }}">
                <i class="bi bi-database w-5 h-5 flex-shrink-0 mt-0.5"></i>
                <div class="flex-1 text-left">
                    <p class="font-semibold text-sm m-0">Backup & Restore</p>
                    <p class="text-xs m-0 mt-1 {{ request()->routeIs('admin.platform.backup*') ? 'text-white/80' : 'text-muted-foreground' }}">
                        Database management
                    </p>
                </div>
            </a>

            <div class="border-t border-border my-4"></div>

            <a href="{{ route('clubs.explore') }}"
               class="flex items-start gap-3 p-4 rounded-lg transition-all no-underline border border-border bg-card text-foreground hover:bg-muted hover:shadow-md">
                <i class="bi bi-eye w-5 h-5 flex-shrink-0 mt-0.5 text-primary"></i>
                <div class="flex-1 text-left">
                    <p class="font-semibold text-sm m-0">Back to Explore</p>
                    <p class="text-xs text-muted-foreground m-0 mt-1">View as user</p>
                </div>
            </a>
        </nav>
    </aside>

    <!-- Content -->
    <main class="flex-1 min-w-0">
        @yield('admin-content')
    </main>
</div>

</div>{{-- end x-data sidebarOpen --}}
@endsection
