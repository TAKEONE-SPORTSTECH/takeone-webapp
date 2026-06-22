@extends('layouts.admin-club-mobile')

@section('title', ($club->club_name ?? __('admin.club')) . ' · ' . __('admin.nav_perks'))

@section('club-admin-content')
<div class="space-y-4">

    @if($perks->isEmpty())
        <div class="m-card p-8 text-center">
            <i class="bi bi-gift text-3xl text-gray-300 m-float"></i>
            <p class="text-sm text-muted-foreground mt-2">{{ __('admin.perk_no_perks') }}</p>
        </div>
    @else
        <div class="space-y-4 mobile-stagger">
        @foreach($perks as $perk)
            <div class="m-card p-4">
                <div class="flex items-start gap-3">
                    <span class="w-11 h-11 rounded-xl flex items-center justify-center text-white flex-shrink-0"
                          style="background: linear-gradient(135deg, {{ $perk->bg_from ?? '#7c3aed' }}, {{ $perk->bg_to ?? '#a855f7' }});">
                        <i class="bi {{ $perk->icon ?? 'bi-gift' }} text-lg"></i>
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-start justify-between gap-2">
                            <h3 class="font-semibold text-foreground truncate">{{ $perk->title }}</h3>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-medium flex-shrink-0 {{ ($perk->status ?? '') === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">{{ ucfirst($perk->status ?? __('admin.perk_inactive')) }}</span>
                        </div>
                        @if($perk->badge)<span class="inline-block mt-1 px-2 py-0.5 rounded-full text-[11px] font-medium bg-accent text-primary">{{ $perk->badge }}</span>@endif
                        @if($perk->description)<p class="text-xs text-muted-foreground mt-1.5 line-clamp-2">{{ $perk->description }}</p>@endif
                    </div>
                </div>
            </div>
        @endforeach
        </div>
    @endif

    <p class="text-xs text-muted-foreground text-center px-4">{{ __('admin.perk_edit_from_desktop') }}</p>
</div>
@endsection
