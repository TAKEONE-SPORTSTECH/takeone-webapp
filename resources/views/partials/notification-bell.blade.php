{{-- The notification bell — the platform top bar AND the club-mode bar
     (clubs::lock.bar) include this one copy. Its ids (rt-bell-btn, rt-notif-*)
     are what the realtime listener patches; only one bar renders per page. --}}
<!-- Notifications Dropdown -->
@auth
@php
    $recentNotifs = \App\Members\Models\UserNotification::where('user_id', Auth::id())
        ->with(['clubNotification.tenant', 'actor', 'tenant'])
        ->latest()
        ->take(5)
        ->get();
    $unreadCount = \App\Members\Models\UserNotification::where('user_id', Auth::id())
        ->where('is_read', false)
        ->count();
@endphp
<div class="relative" x-data="{ open: false }">
    <button @click="open = !open" id="rt-bell-btn" class="nav-icon-btn" title="{{ __('nav.layouts_app_notifications') }}">
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
         class="absolute end-0 mt-2 notification-dropdown bg-white rounded-xl shadow-xl ring-1 ring-black/5 border border-border/60 z-50">
        <div id="rt-notif-header" class="flex items-center justify-between px-4 py-3 border-b border-border">
            <h6 class="text-sm font-semibold mb-0">{{ __('nav.layouts_app_notifications') }}</h6>
            <div class="flex items-center gap-3">
                @if($unreadCount > 0)
                    <button onclick="markAllNotificationsRead(this)"
                            class="text-xs text-primary hover:underline cursor-pointer bg-transparent border-0 p-0">
                        {{ __('nav.layouts_app_mark_all_read') }}
                    </button>
                @endif
                @if($recentNotifs->isNotEmpty())
                    <button onclick="clearAllNotifications(this)"
                            class="text-xs text-destructive hover:underline cursor-pointer bg-transparent border-0 p-0">
                        {{ __('nav.layouts_app_clear_all') }}
                    </button>
                @endif
            </div>
        </div>

        @forelse($recentNotifs as $notif)
            @php $d = $notif->display(); $notifUrl = $d['url']; @endphp
            <div class="notification-item cursor-pointer {{ $notif->is_read ? 'opacity-70' : '' }}"
                 data-notif-id="{{ $notif->id }}"
                 onclick="markNotificationRead({{ $notif->id }}, this, @js($notifUrl))">
                <div class="flex items-center gap-2.5">
                    <span class="shrink-0 w-7 h-7 rounded-full bg-accent text-primary flex items-center justify-center overflow-hidden">
                        @if($d['avatar'])
                            <img src="{{ $d['avatar'] }}" alt="" class="w-7 h-7 object-cover">
                        @else
                            <i class="bi {{ $d['icon'] }} text-xs"></i>
                        @endif
                    </span>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <strong class="text-[13px] leading-tight truncate {{ !$notif->is_read ? 'text-primary' : '' }}">
                                {{ Str::limit($d['title'], 40) }}
                            </strong>
                            @if(!$notif->is_read)
                                <span class="shrink-0 w-1.5 h-1.5 rounded-full bg-primary"></span>
                            @endif
                        </div>
                        <p class="mb-0 text-[11px] text-muted-foreground truncate">
                            {{ $d['context'] ?? 'TakeOne' }}
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
                <p class="text-sm text-muted-foreground mb-0">{{ __('nav.layouts_app_no_notifications_yet') }}</p>
            </div>
        @endforelse

    </div>
</div>
@endauth
