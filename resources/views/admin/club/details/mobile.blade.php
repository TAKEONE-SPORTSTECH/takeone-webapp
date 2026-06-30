@extends('layouts.admin-club-mobile')

@section('title', ($club->club_name ?? __('admin.club')) . ' · ' . __('admin.nav_details'))

@section('club-admin-content')
@php
    $phone = is_array($club->phone) ? trim(($club->phone['code'] ?? '').' '.($club->phone['number'] ?? '')) : ($club->phone ?? '');
@endphp
<div class="space-y-5">

    {{-- Club identity --}}
    <div class="m-card p-5 text-center">
        @if($club->logo)
            <img src="{{ asset('storage/'.$club->logo) }}" alt="" class="w-20 h-20 rounded-2xl object-cover mx-auto">
        @else
            <span class="w-20 h-20 rounded-2xl bg-accent flex items-center justify-center mx-auto text-primary font-bold text-2xl">{{ mb_strtoupper(mb_substr($club->club_name ?? 'CL', 0, 2, 'UTF-8'), 'UTF-8') }}</span>
        @endif
        <h2 class="font-bold text-foreground mt-3">{{ $club->club_name }}</h2>
        @if($club->slogan)<p class="text-sm text-muted-foreground">{{ $club->slogan }}</p>@endif
        <div class="flex items-center justify-center gap-1 mt-2">
            <i class="bi bi-star-fill text-amber-400 text-sm"></i>
            <span class="text-sm font-semibold text-foreground">{{ number_format($averageRating ?? 0, 1) }}</span>
            <span class="text-xs text-muted-foreground">({{ $reviews->count() }} {{ __('admin.det_reviews') }} · {{ $activeMembersCount ?? 0 }} {{ __('admin.det_active_members') }})</span>
        </div>
    </div>

    @if($club->description)
    <div class="m-card p-4">
        <h3 class="font-semibold text-foreground mb-1.5">{{ __('admin.det_about') }}</h3>
        <p class="text-sm text-muted-foreground">{{ $club->description }}</p>
    </div>
    @endif

    {{-- Contact --}}
    <div class="m-card p-4 space-y-3">
        <h3 class="font-semibold text-foreground">{{ __('admin.det_contact') }}</h3>
        @if($club->email)<div class="flex items-center gap-3 text-sm"><i class="bi bi-envelope text-muted-foreground w-5"></i><span class="text-foreground truncate">{{ $club->email }}</span></div>@endif
        @if($phone)<div class="flex items-center gap-3 text-sm"><i class="bi bi-telephone text-muted-foreground w-5"></i><span class="text-foreground">{{ $phone }}</span></div>@endif
        @if($club->address)<div class="flex items-center gap-3 text-sm"><i class="bi bi-geo-alt text-muted-foreground w-5"></i><span class="text-foreground">{{ $club->address }}</span></div>@endif
        @if($club->currency)<div class="flex items-center gap-3 text-sm"><i class="bi bi-cash text-muted-foreground w-5"></i><span class="text-foreground">{{ $club->currency }}</span></div>@endif
    </div>

    {{-- QR codes (share / print) --}}
    <div class="m-card p-4">
        <h3 class="font-semibold text-foreground mb-1">QR codes</h3>
        <p class="text-xs text-muted-foreground mb-3">Share or print these so people can reach the club instantly.</p>
        <div class="flex flex-wrap gap-2">
            <x-qr-code
                :url="\App\Http\Controllers\QrController::clubPageUrl($club)"
                :title="($club->club_name ?? 'Club') . ' — Club page'"
                caption="Scan to view the club page"
                :filename="'qr-' . $club->slug . '-page'"
                label="Club page"
                icon="bi-qr-code"
                :poster-url="route('qr.club.page', $club)" />
            <x-qr-code
                :url="\App\Http\Controllers\QrController::clubRegisterUrl($club)"
                :title="($club->club_name ?? 'Club') . ' — Registration'"
                caption="Scan to register and join"
                :filename="'qr-' . $club->slug . '-register'"
                label="Registration"
                icon="bi-person-plus"
                :poster-url="route('qr.club.register', $club)" />
        </div>
    </div>

    {{-- Owner --}}
    @if($club->owner)
    <div class="m-card p-4">
        <h3 class="font-semibold text-foreground mb-2">{{ __('admin.det_owner') }}</h3>
        <div class="flex items-center gap-3">
            <span class="w-10 h-10 rounded-full bg-muted flex items-center justify-center overflow-hidden flex-shrink-0">
                @if($club->owner->profile_picture)<img src="{{ asset('storage/'.$club->owner->profile_picture) }}" alt="" class="w-10 h-10 object-cover">@else<i class="bi bi-person text-muted-foreground"></i>@endif
            </span>
            <div class="min-w-0"><p class="text-sm font-medium text-foreground truncate">{{ $club->owner->full_name }}</p><p class="text-xs text-muted-foreground truncate">{{ $club->owner->email }}</p></div>
        </div>
    </div>
    @endif

    {{-- Recent reviews --}}
    @if($reviews->isNotEmpty())
    <div class="m-card p-4">
        <h3 class="font-semibold text-foreground mb-3">{{ __('admin.det_recent_reviews') }}</h3>
        <div class="space-y-3 mobile-stagger">
            @foreach($reviews->take(5) as $r)
                <div class="border-b border-gray-50 last:border-0 pb-3 last:pb-0">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-foreground truncate">{{ $r->user->full_name ?? __('admin.det_member') }}</span>
                        <span class="text-xs text-amber-500">@for($i=0;$i<5;$i++)<i class="bi bi-star{{ $i < ($r->rating ?? 0) ? '-fill' : '' }}"></i>@endfor</span>
                    </div>
                    @if($r->comment)<p class="text-xs text-muted-foreground mt-1">{{ $r->comment }}</p>@endif
                </div>
            @endforeach
        </div>
    </div>
    @endif

    <p class="text-xs text-muted-foreground text-center px-4">{{ __('admin.det_edit_from_desktop') }}</p>
</div>
@endsection
