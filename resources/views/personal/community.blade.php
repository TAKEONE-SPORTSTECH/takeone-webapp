@extends('layouts.personal-mobile')

@section('title', 'Club Chat')

@section('personal-content')
@forelse($rooms as $r)
    <a href="{{ route('club-chat.room', $r->club_id) }}"
       class="m-card m-press w-full flex items-center gap-3 p-3 text-left mb-2">
        <span class="shrink-0">
            @if($r->logo)
                <img src="{{ $r->logo }}" class="w-12 h-12 rounded-2xl object-cover" alt="">
            @else
                <span class="w-12 h-12 rounded-2xl bg-gradient-to-br from-primary to-purple-400 text-white flex items-center justify-center text-lg font-bold">{{ $r->initial }}</span>
            @endif
        </span>
        <span class="flex-1 min-w-0">
            <span class="flex items-center justify-between gap-2">
                <span class="font-semibold text-[15px] text-foreground truncate">{{ $r->name }}</span>
                <span class="text-[11px] text-muted-foreground shrink-0">{{ $r->last_at }}</span>
            </span>
            <span class="block text-[13px] text-muted-foreground truncate mt-0.5">
                <i class="bi bi-people-fill text-[11px]"></i> {{ $r->last_body }}
            </span>
        </span>
        <i class="bi bi-chevron-right text-muted-foreground"></i>
    </a>
@empty
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-10 text-center">
        <i class="bi bi-chat-dots text-4xl text-gray-300"></i>
        <p class="text-sm font-medium text-foreground mt-3">No club chats yet</p>
        <p class="text-xs text-muted-foreground mt-1">Join a club to chat with its members.</p>
        <a href="{{ route('clubs.explore') }}" class="inline-block mt-4 px-4 py-2 rounded-lg bg-primary text-white text-sm font-medium">Explore clubs</a>
    </div>
@endforelse
@endsection
