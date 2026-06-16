@extends('layouts.admin-club-mobile')

@section('title', ($club->club_name ?? 'Club') . ' · Packages')

@section('club-admin-content')
@php $cur = $club->currency ?: ''; @endphp
<div class="space-y-4 mobile-stagger">

    @if($packages->isEmpty())
        <div class="m-card p-8 text-center">
            <i class="bi bi-box text-3xl text-gray-300 m-float"></i>
            <p class="text-sm text-muted-foreground mt-2">No packages yet.</p>
        </div>
    @else
        @foreach($packages as $pkg)
            <div class="m-card overflow-hidden">
                @if($pkg->cover_image)
                    <img src="{{ asset('storage/'.$pkg->cover_image) }}" alt="" class="w-full h-32 object-cover">
                @endif
                <div class="p-4">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <h3 class="font-semibold text-foreground truncate">{{ $pkg->name }}</h3>
                            @if($pkg->description)<p class="text-xs text-muted-foreground line-clamp-2 mt-0.5">{{ $pkg->description }}</p>@endif
                        </div>
                        @if(!$pkg->is_active)<span class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-gray-100 text-gray-500 flex-shrink-0">Inactive</span>@endif
                    </div>
                    <div class="flex items-center flex-wrap gap-2 mt-3">
                        <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-accent text-primary">{{ $cur }} {{ number_format((float)($pkg->price ?? 0), 0) }}</span>
                        @if($pkg->duration_months)<span class="px-2.5 py-1 rounded-full text-xs font-medium bg-muted text-muted-foreground">{{ $pkg->duration_months }} mo</span>@endif
                        @if($pkg->gender && $pkg->gender !== 'mixed')<span class="px-2.5 py-1 rounded-full text-xs font-medium bg-muted text-muted-foreground capitalize">{{ $pkg->gender }}</span>@endif
                        @if($pkg->age_min || $pkg->age_max)<span class="px-2.5 py-1 rounded-full text-xs font-medium bg-muted text-muted-foreground">{{ $pkg->age_min ?? 0 }}–{{ $pkg->age_max ?? '∞' }} yrs</span>@endif
                        <span class="px-2.5 py-1 rounded-full text-xs font-medium bg-muted text-muted-foreground"><i class="bi bi-activity mr-1"></i>{{ $pkg->activities->count() }}</span>
                    </div>
                </div>
            </div>
        @endforeach
    @endif

    <p class="text-xs text-muted-foreground text-center px-4">Create &amp; edit packages from the desktop view.</p>
</div>
@endsection
