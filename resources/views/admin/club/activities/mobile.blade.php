@extends('layouts.admin-club-mobile')

@section('title', ($club->club_name ?? 'Club') . ' · Activities')

@section('club-admin-content')
<div class="space-y-4">

    @if($activities->isEmpty())
        <div class="m-card p-8 text-center">
            <i class="bi bi-activity text-3xl text-gray-300 m-float"></i>
            <p class="text-sm text-muted-foreground mt-2">No activities yet.</p>
        </div>
    @else
        <div class="space-y-4 mobile-stagger">
        @foreach($activities as $a)
            <div class="m-card overflow-hidden">
                @if($a->picture_url)<img src="{{ asset('storage/'.$a->picture_url) }}" alt="" class="w-full h-32 object-cover">@endif
                <div class="p-4">
                    <h3 class="font-semibold text-foreground truncate">{{ $a->name }}</h3>
                    @if($a->facility)<p class="text-xs text-muted-foreground mt-0.5"><i class="bi bi-geo-alt mr-1"></i>{{ $a->facility->name }}</p>@endif
                    @if($a->description)<p class="text-xs text-muted-foreground mt-2 line-clamp-2">{{ $a->description }}</p>@endif
                </div>
            </div>
        @endforeach
        </div>
    @endif

    <p class="text-xs text-muted-foreground text-center px-4">Add &amp; edit activities from the desktop view.</p>
</div>
@endsection
