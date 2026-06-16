@php $hu = Auth::user(); @endphp
{{-- Shared mobile header — identical in Personal and Club views so the top bar
     (and the switcher dropdown) never shifts when switching. Only the page title
     and the switcher's current-view checkmark differ. --}}
<header class="sticky top-0 z-40 bg-white border-b border-border">
    <div class="flex items-center gap-2 px-3 h-14">
        <button @click="drawer = true" class="flex items-center justify-center w-10 h-10 rounded-xl flex-shrink-0" aria-label="Menu">
            @if($hu->profile_picture)
                <img src="{{ asset('storage/'.$hu->profile_picture) }}?v={{ optional($hu->updated_at)->timestamp }}" alt="" class="w-9 h-9 rounded-lg object-cover">
            @else
                <i class="bi bi-list text-xl text-foreground"></i>
            @endif
        </button>

        <div class="flex-1 min-w-0">
            @include('partials.mobile-switcher', ['current' => $switcherCurrent ?? 'personal'])
            <p id="shell-title" class="text-base font-bold text-primary leading-tight truncate">{{ $shellTitle ?? '' }}</p>
        </div>

        <button type="button" onclick="window.showToast && window.showToast('info','Notifications coming soon')" class="relative w-9 h-9 rounded-xl bg-muted flex items-center justify-center text-muted-foreground flex-shrink-0" aria-label="Notifications">
            <i class="bi bi-bell"></i>
            <span class="absolute top-1.5 right-1.5 w-2 h-2 bg-destructive rounded-full border border-white"></span>
        </button>
        @php
            $chatUnread = \Illuminate\Support\Facades\DB::table('messages as m')
                ->join('conversation_user as cu', 'cu.conversation_id', '=', 'm.conversation_id')
                ->where('cu.user_id', Auth::id())
                ->where('m.sender_id', '!=', Auth::id())
                ->whereRaw('m.created_at > COALESCE(cu.last_read_at, ?)', ['1970-01-01 00:00:00'])
                ->count();
        @endphp
        <button type="button" onclick="window.dispatchEvent(new CustomEvent('mobile-chat:toggle'))" class="relative w-9 h-9 rounded-xl bg-muted flex items-center justify-center text-muted-foreground flex-shrink-0 chat-link" aria-label="Chat">
            <i class="bi bi-chat-dots"></i>
            @if($chatUnread > 0)
                <span class="notification-badge chat-badge">{{ $chatUnread > 99 ? '99+' : $chatUnread }}</span>
            @endif
        </button>
        <button type="button" onclick="window.showToast && window.showToast('info','Cart coming soon')" class="w-9 h-9 rounded-xl bg-muted flex items-center justify-center text-muted-foreground flex-shrink-0" aria-label="Cart"><i class="bi bi-cart"></i></button>
    </div>
</header>

@include('partials.mobile-chat')
