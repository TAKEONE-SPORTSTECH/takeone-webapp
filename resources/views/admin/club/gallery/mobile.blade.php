@extends('layouts.admin-club-mobile')

@section('title', ($club->club_name ?? __('admin.club')) . ' · ' . __('admin.nav_gallery'))

@section('club-admin-content')
<div class="space-y-4">

    @if($club->youtube_url)
        <div class="m-card p-4 flex items-center gap-3">
            <span class="w-10 h-10 rounded-lg bg-red-100 flex items-center justify-center flex-shrink-0"><i class="bi bi-youtube text-red-600 text-lg"></i></span>
            <a href="{{ $club->youtube_url }}" target="_blank" class="m-press text-sm text-primary font-medium truncate">{{ __('admin.gal_watch_video') }}</a>
        </div>
    @endif

    @if($images->isEmpty())
        <div class="m-card p-8 text-center">
            <i class="bi bi-images text-3xl text-gray-300 m-float"></i>
            <p class="text-sm text-muted-foreground mt-2">{{ __('admin.gal_none_yet') }}</p>
        </div>
    @else
        <div class="grid grid-cols-2 gap-2 mobile-stagger">
            @foreach($images as $img)
                <div class="rounded-xl overflow-hidden bg-muted aspect-square">
                    <img src="{{ asset('storage/'.$img->image_path) }}" alt="{{ $img->caption }}" class="w-full h-full object-cover">
                </div>
            @endforeach
        </div>
    @endif

    <p class="text-xs text-muted-foreground text-center px-4">{!! __('admin.gal_desktop_hint') !!}</p>
</div>
@endsection
