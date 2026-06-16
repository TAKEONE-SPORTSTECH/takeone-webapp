@extends('layouts.app')

@section('title', 'Club Chat')
@section('hide-navbar', true)

@section('content')
<header class="sticky top-0 z-40 bg-white border-b border-border">
    <div class="flex items-center gap-2 px-3 h-14">
        <a href="{{ route('me.home') }}" class="flex items-center justify-center w-10 h-10 rounded-xl flex-shrink-0" aria-label="Back">
            <i class="bi bi-arrow-left text-xl text-foreground"></i>
        </a>
        <div class="flex-1 min-w-0">
            <p class="text-[10px] text-muted-foreground font-medium leading-tight">Your clubs</p>
            <p class="text-base font-bold text-primary leading-tight truncate">Club Chat</p>
        </div>
    </div>
</header>

<div class="px-3 pt-3 pb-24">
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
                    <span class="font-semibold text-[15px] text-foreground truncate flex items-center gap-1.5">
                        {{ $r->name }}
                        @if($r->muted)<i class="bi bi-bell-slash text-xs text-muted-foreground"></i>@endif
                    </span>
                    <span class="text-[11px] text-muted-foreground shrink-0">{{ $r->last_at }}</span>
                </span>
                <span class="block text-[13px] text-muted-foreground truncate mt-0.5">
                    <i class="bi bi-people-fill text-[11px]"></i> {{ $r->last_body }}
                </span>
            </span>
            <i class="bi bi-chevron-right text-muted-foreground"></i>
        </a>
    @empty
        <div class="text-center py-20">
            <i class="bi bi-people text-5xl text-gray-200 m-float inline-block"></i>
            <p class="text-sm text-muted-foreground mt-3">You're not in any club yet.<br>Join a club to access its chat.</p>
            <a href="{{ route('clubs.explore') }}" class="inline-block mt-4 px-4 py-2 rounded-lg bg-primary text-white text-sm font-medium">Explore clubs</a>
        </div>
    @endforelse
</div>
@endsection
