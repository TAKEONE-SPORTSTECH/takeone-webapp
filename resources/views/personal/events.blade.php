@extends('layouts.personal-mobile')

@section('title', 'Events')

@section('personal-content')
<div class="space-y-4">
    @forelse($events as $e)
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 flex items-start gap-3">
            <div class="flex flex-col items-center justify-center w-14 h-14 rounded-xl text-white flex-shrink-0" style="background: {{ $e->color ?? '#7c3aed' }};">
                <span class="text-lg font-bold leading-none">{{ optional($e->date)->format('d') }}</span>
                <span class="text-[10px] uppercase">{{ optional($e->date)->format('M') }}</span>
            </div>
            <div class="min-w-0 flex-1">
                <h3 class="font-semibold text-foreground truncate">{{ $e->title }}</h3>
                <p class="text-xs text-muted-foreground truncate">{{ $e->tenant->club_name ?? '' }}@if($e->location) · {{ $e->location }}@endif</p>
                @if($e->level)<span class="inline-block mt-1.5 px-2 py-0.5 rounded-full text-[11px] font-medium bg-accent text-primary">{{ $e->level }}</span>@endif
            </div>
        </div>
    @empty
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-10 text-center">
            <i class="bi bi-calendar-heart text-4xl text-gray-300"></i>
            <p class="text-sm text-muted-foreground mt-3">No upcoming events from your clubs.</p>
        </div>
    @endforelse
</div>
@endsection
