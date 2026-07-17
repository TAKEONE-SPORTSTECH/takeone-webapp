@extends('layouts.app')

{{-- Single mobile shell — hide the global brand navbar so the shared header is
     the only top bar (identical to Personal & Club views). --}}
@section('hide-navbar', true)

@section('content')
@php
    $currentRoute = request()->route()?->getName();
    $chainClubs = $clubs ?? collect();
@endphp

<div x-data="{ drawer:false, switcher:false }" @shell:navigated.window="drawer=false; switcher=false">

    {{-- ===== Header (shared, identical to Personal & Club) ===== --}}
    @include('partials.mobile-header', ['switcherCurrent' => 'business', 'shellTitle' => __('business.dashboard')])

    {{-- ===== Left drawer ===== --}}
    <div x-show="drawer" x-cloak class="fixed inset-0 z-50" style="display:none;">
        <div class="absolute inset-0 bg-black/40" @click="drawer=false"
             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"></div>
        <aside class="absolute top-0 start-0 h-full w-[280px] max-w-[85vw] bg-white shadow-2xl flex flex-col overflow-y-auto"
               x-transition:enter="transition ease-out duration-250" x-transition:enter-start="ltr:-translate-x-full rtl:translate-x-full" x-transition:enter-end="translate-x-0"
               x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-x-0" x-transition:leave-end="ltr:-translate-x-full rtl:translate-x-full">
            <div class="flex items-center justify-between p-4 border-b border-border">
                <div class="flex items-center gap-2 min-w-0">
                    <span class="w-10 h-10 rounded-lg bg-accent flex items-center justify-center flex-shrink-0 overflow-hidden">
                        @if($business->logo)<img src="{{ asset('storage/'.$business->logo) }}" alt="" class="w-10 h-10 object-cover">@else<i class="bi bi-buildings text-primary"></i>@endif
                    </span>
                    <div class="min-w-0">
                        <p class="font-bold text-foreground truncate text-sm">{{ $business->name }}</p>
                        <p class="text-[10px] text-muted-foreground">{{ __('business.chain') }}</p>
                    </div>
                </div>
                <button @click="drawer=false" class="w-8 h-8 rounded-lg bg-muted flex items-center justify-center text-muted-foreground"><i class="bi bi-x-lg"></i></button>
            </div>
            <nav class="p-3 flex-1">
                <p class="px-2 mt-1 mb-1 text-[10px] font-bold uppercase tracking-wide text-muted-foreground">{{ __('business.chain') }}</p>
                <a href="{{ route('business.dashboard') }}" data-shell-link data-route="business.dashboard"
                   class="shell-nav-link flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-colors {{ $currentRoute === 'business.dashboard' ? 'is-active' : '' }}">
                    <i class="bi bi-speedometer2 text-lg w-5 text-center"></i>{{ __('business.dashboard') }}
                </a>

                <p class="px-2 mt-3 mb-1 text-[10px] font-bold uppercase tracking-wide text-muted-foreground">{{ __('business.clubs') }}</p>
                @forelse($chainClubs as $c)
                    <a href="{{ route('admin.club.dashboard', $c['slug']) }}"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium text-foreground hover:bg-accent transition-colors">
                        <span class="w-6 h-6 rounded bg-muted flex items-center justify-center overflow-hidden flex-shrink-0">
                            @if(!empty($c['logo']))<img src="{{ asset('storage/'.$c['logo']) }}" alt="" class="w-6 h-6 object-cover">@else<i class="bi bi-building text-[11px] text-muted-foreground"></i>@endif
                        </span>
                        <span class="truncate">{{ $c['name'] }}</span>
                    </a>
                @empty
                    <p class="px-3 py-2 text-xs text-muted-foreground">{{ __('business.no_clubs_linked') }}</p>
                @endforelse
                <a href="{{ route('clubs.explore') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium text-muted-foreground hover:bg-accent transition-colors">
                    <i class="bi bi-compass text-lg w-5 text-center"></i>{{ __('business.explore_clubs') }}
                </a>

                {{-- Switch context + account actions --}}
                <div class="border-t border-border mt-3 pt-3">
                    <a href="{{ route('me.home') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium text-foreground hover:bg-accent">
                        <i class="bi bi-person-circle text-lg w-5 text-center"></i>{{ __('business.personal_view') }}
                    </a>
                    @include('partials.mobile-account-links')
                </div>
            </nav>
        </aside>
    </div>

    {{-- ===== Content ===== --}}
    <main id="shell-content" data-shell-id="business" data-route="{{ $currentRoute }}" data-title="{{ __('business.dashboard') }}" class="mobile-stagger px-4 py-4 min-h-[60vh]">
        @yield('chain-content')
    </main>

</div>

@include('partials.mobile-shell-nav')
@endsection
