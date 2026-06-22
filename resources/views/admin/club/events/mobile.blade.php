@extends('layouts.admin-club-mobile')

@section('title', ($club->club_name ?? __('admin.club')) . ' · ' . __('admin.nav_events'))

@section('club-admin-content')
<div class="space-y-4 mobile-stagger">

    @if($events->isEmpty())
        <div class="m-card p-8 text-center">
            <i class="bi bi-calendar-event text-3xl text-gray-300 m-float"></i>
            <p class="text-sm text-muted-foreground mt-2">{{ __('admin.evt_none_yet') }}</p>
        </div>
    @else
        @foreach($events as $e)
            <div class="m-card p-4 {{ $e->is_archived ? 'opacity-60' : '' }}">
                <div class="flex items-start gap-3">
                    <div class="flex flex-col items-center justify-center w-14 h-14 rounded-xl text-white flex-shrink-0" style="background: {{ $e->color ?? '#7c3aed' }};">
                        <span class="text-lg font-bold leading-none">{{ optional($e->date)->format('d') }}</span>
                        <span class="text-[10px] uppercase">{{ optional($e->date)->format('M') }}</span>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-start justify-between gap-2">
                            <h3 class="font-semibold text-foreground truncate">{{ $e->title }}</h3>
                            @if($e->is_archived)<span class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-gray-100 text-gray-500 flex-shrink-0">{{ __('admin.evt_archived') }}</span>@endif
                        </div>
                        <p class="text-xs text-muted-foreground mt-0.5">
                            @if($e->start_time){{ \Illuminate\Support\Str::of($e->start_time)->before(':00') }}@endif
                            @if($e->location) · <i class="bi bi-geo-alt"></i> {{ $e->location }}@endif
                        </p>
                        @if($e->level)<span class="inline-block mt-1.5 px-2 py-0.5 rounded-full text-[11px] font-medium bg-accent text-primary">{{ $e->level }}</span>@endif
                    </div>
                </div>
                @if($e->description)<p class="text-xs text-muted-foreground mt-3 line-clamp-2">{{ $e->description }}</p>@endif
                @if($e->max_capacity)<p class="text-xs text-muted-foreground mt-2"><i class="bi bi-people mr-1"></i>{{ __('admin.evt_capacity') }} {{ $e->max_capacity }}</p>@endif
            </div>
        @endforeach
    @endif

    <p class="text-xs text-muted-foreground text-center px-4">{!! __('admin.evt_desktop_hint') !!}</p>
</div>
@endsection
