@extends('layouts.app')

@section('hide-navbar', true)
@section('title', 'All Clubs')

@section('content')
<div class="min-h-screen bg-background pb-20">

    {{-- ===== Header ===== --}}
    <header class="sticky top-0 z-40 bg-white border-b border-border">
        <div class="flex items-center gap-2 px-3 h-14">
            <button type="button" onclick="history.length > 1 ? history.back() : (window.location.href='{{ route('admin.platform.index') }}')"
                    class="m-press w-10 h-10 -ml-1 rounded-xl flex items-center justify-center text-foreground" aria-label="{{ __('shared.back') }}">
                <i class="bi bi-arrow-left text-xl"></i>
            </button>
            <p class="flex-1 min-w-0 text-base font-bold text-primary truncate">{{ __('platform.all_clubs') }}</p>
            <button type="button" onclick="window.dispatchEvent(new CustomEvent('open-club-modal', { detail: { mode: 'create' } }))"
                    class="m-press w-10 h-10 rounded-xl flex items-center justify-center text-primary" aria-label="{{ __('platform.add_club') }}">
                <i class="bi bi-plus-circle text-xl"></i>
            </button>
        </div>
    </header>

    <div class="px-4 pt-4">
        {{-- Search --}}
        <div class="relative mb-4">
            <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
            <input type="text" id="clubSearch" autocomplete="off" value="{{ $search ?? '' }}"
                   placeholder="{{ __('platform.search_clubs') }}"
                   class="w-full pl-10 pr-3 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/40 focus:border-transparent text-sm">
        </div>

        @if($clubs->count() > 0)
            @php
                $countryNames = collect(json_decode(@file_get_contents(public_path('data/countries.json')) ?: '[]', true))->pluck('name', 'iso2');
            @endphp
            <div class="space-y-3 mobile-stagger" id="clubsGrid">
                @foreach($clubs as $club)
                    @php
                        $cc = strtoupper($club->country ?? '');
                        $flag = (strlen($cc) === 2 && ctype_alpha($cc)) ? mb_chr(127397 + ord($cc[0])) . mb_chr(127397 + ord($cc[1])) : '';
                    @endphp
                    <div class="club-card-wrapper m-card bg-white rounded-2xl overflow-hidden shadow-sm border border-gray-100"
                         data-club-id="{{ $club->id }}"
                         data-club-name="{{ $club->club_name }}"
                         data-club-address="{{ $club->address ?? '' }}"
                         data-club-owner="{{ $club->owner->full_name ?? '' }}">
                        <a href="{{ route('admin.club.dashboard', $club->slug) }}" class="block no-underline">
                            {{-- Cover --}}
                            <div class="relative h-28">
                                @if($club->cover_image)
                                    <img src="{{ asset('storage/' . $club->cover_image) }}" alt="" loading="lazy" class="club-cover-img w-full h-full object-cover">
                                @else
                                    <div class="w-full h-full m-hero"></div>
                                @endif
                                <div class="absolute inset-0 bg-gradient-to-t from-black/30 to-transparent"></div>
                                <span class="absolute top-2 left-2 px-2.5 py-0.5 rounded-full text-[10px] font-semibold text-white bg-primary/90">{{ __('platform.badge_admin') }}</span>
                                @if($flag)
                                    <span class="absolute top-2 right-2 text-base leading-none">{{ $flag }}</span>
                                @endif
                                <button type="button" aria-label="{{ __('platform.edit_club') }}"
                                        onclick="event.preventDefault(); event.stopPropagation(); window.dispatchEvent(new CustomEvent('open-club-modal', { detail: { mode: 'edit', clubId: {{ $club->id }} } }));"
                                        class="m-press absolute bottom-2 right-2 w-9 h-9 rounded-full bg-white/90 backdrop-blur flex items-center justify-center text-foreground shadow">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <span class="absolute -bottom-5 left-3 w-14 h-14 rounded-2xl bg-white p-0.5 shadow ring-1 ring-black/5 overflow-hidden flex items-center justify-center">
                                    @if($club->logo)
                                        <img src="{{ asset('storage/' . $club->logo) }}" alt="" loading="lazy" class="club-logo-img w-full h-full rounded-xl object-contain">
                                    @else
                                        <span class="w-full h-full rounded-xl bg-primary text-white flex items-center justify-center font-bold text-lg">{{ substr($club->club_name, 0, 1) }}</span>
                                    @endif
                                </span>
                            </div>
                            {{-- Body --}}
                            <div class="pt-7 px-3 pb-3">
                                <h3 class="club-title font-semibold text-foreground truncate">{{ $club->club_name }}</h3>
                                @if($club->address)
                                    <p class="club-address text-[12px] text-muted-foreground truncate mt-0.5"><i class="bi bi-geo-alt mr-1"></i>{{ $club->address }}</p>
                                @endif
                                <div class="grid grid-cols-3 gap-2 mt-3 text-center">
                                    <div class="rounded-xl bg-primary/5 py-2"><p class="font-bold text-foreground text-sm">{{ $club->members_count }}</p><p class="text-[10px] text-muted-foreground">{{ __('platform.stat_members') }}</p></div>
                                    <div class="rounded-xl bg-primary/5 py-2"><p class="font-bold text-foreground text-sm">{{ $club->packages_count }}</p><p class="text-[10px] text-muted-foreground">{{ __('platform.stat_packages') }}</p></div>
                                    <div class="rounded-xl bg-primary/5 py-2"><p class="font-bold text-foreground text-sm">{{ $club->instructors_count }}</p><p class="text-[10px] text-muted-foreground">{{ __('platform.stat_trainers') }}</p></div>
                                </div>
                            </div>
                        </a>
                    </div>
                @endforeach
            </div>

            <div class="flex justify-center mt-4">{{ $clubs->links() }}</div>
        @else
            <div class="bg-white rounded-2xl px-6 py-14 text-center shadow-sm border border-gray-100">
                <i class="bi bi-building text-5xl text-gray-300 m-float inline-block"></i>
                <p class="text-sm font-semibold text-foreground mt-4">{{ __('platform.no_clubs_found') }}</p>
                <p class="text-[12px] text-muted-foreground mt-1">{{ $search ? __('platform.no_clubs_match') : __('platform.add_first_club') }}</p>
            </div>
        @endif
    </div>
</div>

<x-club-modal mode="create" />

@include('admin.platform.clubs._scripts')
@endsection
