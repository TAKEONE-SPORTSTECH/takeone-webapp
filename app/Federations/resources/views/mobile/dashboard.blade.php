@extends('members::layouts.personal-mobile')

@section('title', $federation->name)

@php
    $color = $federation->bandColor();
    $maxAthletes = max(1, (int) $clubs->max('athletes'));
@endphp

@section('personal-content')

{{-- Hero band — Design Rule #6. The numbers ride in the band; the cards ride up over its tail. --}}
<header class="m-hero -mx-4 -mt-4 px-5 pt-5 pb-16 text-white relative overflow-hidden"
        style="background: linear-gradient(150deg, {{ $color }}, {{ $color }}b0);">
    <div class="absolute -right-10 -top-10 w-44 h-44 rounded-full bg-white/10"></div>
    <div class="absolute right-6 bottom-8 w-24 h-24 rounded-full bg-white/10"></div>

    <div class="flex items-center justify-between relative z-50">
        <a href="{{ route('me.home') }}" aria-label="{{ __('federations::messages.personal_view') }}" title="{{ __('federations::messages.personal_view') }}"
           class="m-press w-10 h-10 rounded-full bg-white/15 border border-white/25 backdrop-blur inline-flex items-center justify-center text-white">
            <i class="bi bi-chevron-left rtl:rotate-180"></i>
        </a>
        @if($federation->logo)
            <span class="w-10 h-10 flex-shrink-0"><img src="{{ file_url($federation->logo) }}" alt="" class="w-full h-full object-contain"></span>
        @endif
    </div>

    <div class="relative z-10 mt-6">
        <div class="flex items-center gap-1.5 flex-wrap">
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur">
                <i class="bi bi-bank2"></i> {{ __('federations::messages.federation') }}
            </span>
            @if($federation->country)
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur">
                    <x-flag :code="$federation->country" :h="10" /> {{ $federation->country }}
                </span>
            @endif
            @if($federation->sport)
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur">
                    <i class="bi bi-trophy"></i> {{ \Illuminate\Support\Str::headline($federation->sport) }}
                </span>
            @endif
        </div>
        <h1 class="text-2xl font-black mt-3 leading-tight">{{ $federation->name }}</h1>
        <p class="text-sm text-white/85 mt-1.5 flex items-center gap-1.5">
            <i class="bi bi-person-badge"></i>{{ __('federations::messages.run_by', ['name' => $federation->owner?->full_name ?? $federation->owner?->name]) }}
        </p>
    </div>
</header>

<div class="-mt-10 relative z-10 space-y-4">

    {{-- The four numbers --}}
    <div class="mobile-stagger grid grid-cols-2 gap-3">
        @foreach([
            ['clubs', 'bi-shield-check', $totals['clubs']],
            ['athletes', 'bi-people-fill', $totals['athletes']],
            ['upcoming', 'bi-calendar-event', $totals['upcoming']],
            ['pending', 'bi-inbox-fill', $totals['pending']],
        ] as [$key, $icon, $value])
            <div class="m-card m-press p-4">
                <div class="w-9 h-9 rounded-xl flex items-center justify-center mb-2" style="background: {{ $color }}1f;">
                    <i class="bi {{ $icon }} text-lg" style="color: {{ $color }};"></i>
                </div>
                <p class="text-2xl font-extrabold text-gray-900 tabular-nums leading-none">{{ number_format($value) }}</p>
                <p class="text-xs font-medium text-muted-foreground mt-1">{{ __('federations::messages.'.$key) }}</p>
            </div>
        @endforeach
    </div>

    {{-- Member clubs --}}
    <section class="space-y-3">
        <div class="flex items-center justify-between px-1">
            <h2 class="font-bold text-foreground">{{ __('federations::messages.clubs') }}</h2>
            <span class="text-xs text-muted-foreground">{{ $totals['clubs'] }}</span>
        </div>

        @if($clubs->isEmpty())
            <div class="m-card p-8 text-center">
                <span class="w-14 h-14 rounded-2xl mx-auto grid place-items-center m-float" style="background: {{ $color }}1f;">
                    <i class="bi bi-shield-plus text-2xl" style="color: {{ $color }};"></i>
                </span>
                <p class="font-semibold text-foreground mt-3">{{ __('federations::messages.no_clubs_title') }}</p>
                <p class="text-sm text-muted-foreground mt-1">{{ __('federations::messages.no_clubs_body') }}</p>
            </div>
        @else
            <div class="mobile-stagger space-y-3">
                @foreach($clubs as $club)
                    <div class="m-card p-4">
                        <div class="flex items-center gap-3">
                            <span class="w-11 h-11 flex-shrink-0 grid place-items-center">
                                @if($club['logo'])
                                    <img src="{{ file_url($club['logo']) }}" alt="" class="w-full h-full object-contain">
                                @else
                                    <i class="bi bi-building text-xl text-muted-foreground"></i>
                                @endif
                            </span>
                            <div class="flex-1 min-w-0">
                                <p class="font-semibold text-foreground">{{ $club['name'] }}</p>
                                <p class="text-xs text-muted-foreground">
                                    {{ __('federations::messages.athletes_count', ['count' => number_format($club['athletes'])]) }}
                                    @if($club['upcoming'] > 0) · {{ __('federations::messages.events_count', ['count' => $club['upcoming']]) }} @endif
                                </p>
                            </div>
                        </div>
                        <div class="mt-3 h-1.5 rounded-full bg-muted overflow-hidden">
                            <div class="h-full rounded-full m-bar-fill" style="width: {{ round($club['athletes'] / $maxAthletes * 100) }}%; background: {{ $color }};"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    {{-- Calendar --}}
    <section class="space-y-3">
        <h2 class="font-bold text-foreground px-1">{{ __('federations::messages.upcoming') }}</h2>
        <div class="m-card overflow-hidden">
            @forelse($upcoming as $e)
                @php $d = \Illuminate\Support\Carbon::parse($e['date']); $ec = \App\Support\Palette::safe($e['color'], $color); @endphp
                <div class="px-4 py-3 flex items-center gap-3 border-b border-gray-50 last:border-0">
                    <span class="w-11 h-12 rounded-xl flex-shrink-0 flex flex-col items-center justify-center text-white" style="background: linear-gradient(150deg, {{ $ec }}, {{ $ec }}b0);">
                        <span class="text-[10px] font-bold uppercase leading-none">{{ $d->translatedFormat('M') }}</span>
                        <span class="text-lg font-black leading-none mt-0.5">{{ $d->format('j') }}</span>
                    </span>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-foreground truncate">{{ $e['title'] }}</p>
                        <p class="text-xs text-muted-foreground truncate">{{ $e['club'] }}</p>
                    </div>
                </div>
            @empty
                <p class="px-4 py-6 text-sm text-muted-foreground text-center">{{ __('federations::messages.no_upcoming') }}</p>
            @endforelse
        </div>
    </section>

    {{-- Applications --}}
    <section class="space-y-3 pb-4">
        <div class="flex items-center justify-between px-1">
            <h2 class="font-bold text-foreground">{{ __('federations::messages.waiting') }}</h2>
            @if($pending->isNotEmpty())
                <span class="py-0.5 px-2.5 rounded-full text-xs font-medium bg-amber-100 text-amber-700">{{ $pending->count() }}</span>
            @endif
        </div>
        <div class="m-card overflow-hidden">
            @forelse($pending as $p)
                <div class="px-4 py-3 flex items-center gap-3 border-b border-gray-50 last:border-0">
                    <span class="w-8 h-8 flex-shrink-0 grid place-items-center">
                        @if($p['logo'])
                            <img src="{{ file_url($p['logo']) }}" alt="" class="w-full h-full object-contain">
                        @else
                            <i class="bi bi-building text-muted-foreground"></i>
                        @endif
                    </span>
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-foreground truncate">{{ $p['name'] }}</p>
                        <p class="text-xs text-muted-foreground">{{ __('federations::messages.applied', ['when' => $p['since']?->diffForHumans()]) }}</p>
                    </div>
                </div>
            @empty
                <p class="px-4 py-6 text-sm text-muted-foreground text-center">{{ __('federations::messages.no_pending') }}</p>
            @endforelse
        </div>
    </section>
</div>
@endsection
