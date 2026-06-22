@extends('layouts.admin-club-mobile')

@section('title', ($club->club_name ?? __('admin.club')) . ' · ' . __('admin.nav_notifications'))

@section('club-admin-content')
<div class="space-y-4">

    <button type="button" @click="showNotificationModal = true"
            class="w-full bg-primary text-white px-4 py-3 rounded-lg font-medium flex items-center justify-center gap-2">
        <i class="bi bi-send"></i> {{ __('admin.send_notification') }}
    </button>

    @if($notifications->isEmpty())
        <div class="m-card p-8 text-center">
            <i class="bi bi-bell text-3xl text-gray-300 m-float"></i>
            <p class="text-sm text-muted-foreground mt-2">{{ __('admin.notif_none_sent') }}</p>
        </div>
    @else
        <div class="space-y-3 mobile-stagger">
            @foreach($notifications as $n)
                <div class="m-card p-4">
                    <div class="flex items-start justify-between gap-2">
                        <h3 class="font-semibold text-foreground truncate">{{ $n->subject }}</h3>
                        <span class="text-[11px] text-muted-foreground flex-shrink-0">{{ optional($n->sent_at)->format('d M') }}</span>
                    </div>
                    @if($n->message)<p class="text-xs text-muted-foreground mt-1 line-clamp-2 whitespace-pre-line">{{ $n->message }}</p>@endif
                    <div class="flex items-center gap-3 mt-2 text-[11px] text-muted-foreground">
                        <span><i class="bi bi-person mr-1"></i>{{ $n->sender->full_name ?? __('admin.notif_system') }}</span>
                        <span><i class="bi bi-people mr-1"></i>{{ $n->recipient_type === 'all' ? __('admin.notif_all_members') : ($n->recipient_count ?? 0).' '.__('admin.notif_selected') }}</span>
                    </div>
                </div>
            @endforeach
        </div>
        <div>{{ $notifications->links() }}</div>
    @endif
</div>
@endsection
