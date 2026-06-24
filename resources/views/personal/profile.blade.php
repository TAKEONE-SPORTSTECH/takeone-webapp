@extends('layouts.personal-mobile')

@section('title', 'My Profile')

{{-- Full-bleed (Facebook-style): edge-to-edge white block, no inset card. --}}
@section('personal-content')
<div class="-mx-4 -mt-4">
    <div class="bg-white px-5 py-6 text-center">
        <span class="w-20 h-20 rounded-2xl bg-muted flex items-center justify-center mx-auto overflow-hidden">
            @if($user->profile_picture)<img src="{{ asset('storage/'.$user->profile_picture) }}?v={{ optional($user->updated_at)->timestamp }}" alt="" class="w-20 h-20 object-cover">@else<i class="bi bi-person text-3xl text-muted-foreground"></i>@endif
        </span>
        <h2 class="font-bold text-foreground mt-3">{{ $user->full_name }}</h2>
        <p class="text-sm text-muted-foreground">{{ $user->email }}</p>
        <div class="grid grid-cols-2 gap-3 mt-4">
            <div class="bg-muted/50 rounded-xl p-3"><p class="text-xl font-bold text-primary">{{ $clubCount }}</p><p class="text-[11px] text-muted-foreground">{{ __('personal.clubs') }}</p></div>
            <div class="bg-muted/50 rounded-xl p-3"><p class="text-xl font-bold text-primary">{{ $activeSubs }}</p><p class="text-[11px] text-muted-foreground">{{ __('personal.active_packages') }}</p></div>
        </div>
        <div class="flex flex-wrap items-center justify-center gap-2 mt-4">
            <a href="{{ route('member.show', $user->uuid) }}" class="inline-flex items-center gap-1.5 border border-primary text-primary px-4 py-2 rounded-lg text-sm font-medium">
                <i class="bi bi-pencil"></i> {{ __('personal.view_full_profile') }}
            </a>
            <x-qr-code
                :url="\App\Http\Controllers\QrController::memberUrl($user)"
                :title="($user->full_name ?: $user->name) . ' — Profile'"
                caption="Scan to view my profile"
                :filename="'qr-profile-' . ($user->slug ?: $user->id)"
                label="My QR"
                icon="bi-qr-code"
                :poster-url="route('qr.member', $user)" />
        </div>
    </div>
</div>
@endsection
