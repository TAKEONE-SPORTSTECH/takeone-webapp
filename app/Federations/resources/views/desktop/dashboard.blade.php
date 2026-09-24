@extends('layouts.app')

@section('title', $federation->name . ' · ' . __('federations::messages.dashboard'))

@php
    $color = $federation->bandColor();
    $maxAthletes = max(1, (int) $clubs->max('athletes'));
    $tiles = [
        ['key' => 'clubs',    'icon' => 'bi-shield-check',   'value' => $totals['clubs']],
        ['key' => 'athletes', 'icon' => 'bi-people-fill',    'value' => $totals['athletes']],
        ['key' => 'upcoming', 'icon' => 'bi-calendar-event', 'value' => $totals['upcoming']],
        ['key' => 'pending',  'icon' => 'bi-inbox-fill',     'value' => $totals['pending']],
    ];
@endphp

@section('content')
<div class="px-4 sm:px-6 lg:px-8 py-6">

    {{-- Hero band — Design Rule #6, desktop. A top-level destination, so no back control. --}}
    <div class="-mx-4 sm:-mx-6 lg:-mx-8 -mt-6 overflow-hidden shadow-sm mb-6 text-white relative"
         style="background: linear-gradient(150deg, {{ $color }}, {{ $color }}b0);">
        <div class="absolute -right-10 -top-10 w-44 h-44 rounded-full bg-white/10"></div>
        <div class="absolute right-6 bottom-8 w-24 h-24 rounded-full bg-white/10"></div>

        <div class="relative px-4 sm:px-6 lg:px-8 py-6 sm:py-8">
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <div class="flex items-center gap-1.5 flex-wrap">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur">
                            <i class="bi bi-bank2"></i> {{ __('federations::messages.federation') }}
                        </span>
                        @if($federation->short_name)
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur">{{ $federation->short_name }}</span>
                        @endif
                        @if($federation->country)
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur">
                                <x-flag :code="$federation->country" :h="10" /> {{ \App\Support\Countries::name($federation->country) }}
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

                @if($federation->logo)
                    <span class="w-16 h-16 flex-shrink-0"><img src="{{ file_url($federation->logo) }}" alt="" class="w-full h-full object-contain"></span>
                @endif
            </div>
        </div>
    </div>

    <div class="space-y-6">

        {{-- The four numbers --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            @foreach($tiles as $t)
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 relative overflow-hidden">
                    <div class="absolute -right-6 -top-6 w-20 h-20 rounded-full" style="background: {{ $color }}14;"></div>
                    <div class="relative flex items-center gap-2 text-muted-foreground text-xs font-medium uppercase tracking-wide">
                        <i class="bi {{ $t['icon'] }}" style="color: {{ $color }};"></i> {{ __('federations::messages.'.$t['key']) }}
                    </div>
                    <p class="relative text-3xl font-black text-gray-900 mt-2 tabular-nums">{{ number_format($t['value']) }}</p>
                    <p class="relative text-xs text-muted-foreground mt-1">{{ __('federations::messages.'.$t['key'].'_sub') }}</p>
                </div>
            @endforeach
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- Member clubs, each with its share of the federation's athletes --}}
            <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h2 class="font-semibold text-foreground">{{ __('federations::messages.clubs') }}</h2>
                    <p class="text-sm text-muted-foreground">{{ __('federations::messages.share_of_athletes') }}</p>
                </div>

                @if($clubs->isEmpty())
                    <div class="p-10 text-center">
                        <span class="w-16 h-16 rounded-2xl mx-auto grid place-items-center" style="background: {{ $color }}14;">
                            <i class="bi bi-shield-plus text-3xl" style="color: {{ $color }};"></i>
                        </span>
                        <p class="font-semibold text-foreground mt-4">{{ __('federations::messages.no_clubs_title') }}</p>
                        <p class="text-sm text-muted-foreground mt-1 max-w-sm mx-auto">{{ __('federations::messages.no_clubs_body') }}</p>
                    </div>
                @else
                    <ul class="divide-y divide-gray-50">
                        @foreach($clubs as $club)
                            <li class="px-5 py-4 flex items-center gap-4 hover:bg-muted/40 transition-colors">
                                <span class="w-10 h-10 flex-shrink-0 grid place-items-center">
                                    @if($club['logo'])
                                        <img src="{{ file_url($club['logo']) }}" alt="" class="w-full h-full object-contain">
                                    @else
                                        <i class="bi bi-building text-xl text-muted-foreground"></i>
                                    @endif
                                </span>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between gap-3">
                                        <p class="font-medium text-foreground">{{ $club['name'] }}</p>
                                        <p class="text-sm font-semibold tabular-nums text-foreground flex-shrink-0">{{ __('federations::messages.athletes_count', ['count' => number_format($club['athletes'])]) }}</p>
                                    </div>
                                    <div class="mt-2 h-1.5 rounded-full bg-muted overflow-hidden">
                                        <div class="h-full rounded-full m-bar-fill" style="width: {{ round($club['athletes'] / $maxAthletes * 100) }}%; background: {{ $color }};"></div>
                                    </div>
                                    @if($club['upcoming'] > 0)
                                        <p class="text-xs text-muted-foreground mt-1.5"><i class="bi bi-calendar-event me-1"></i>{{ __('federations::messages.events_count', ['count' => $club['upcoming']]) }}</p>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="space-y-6">

                {{-- Calendar across every member club --}}
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-100">
                        <h2 class="font-semibold text-foreground">{{ __('federations::messages.upcoming') }}</h2>
                        <p class="text-sm text-muted-foreground">{{ __('federations::messages.calendar') }}</p>
                    </div>
                    @forelse($upcoming as $e)
                        @php $d = \Illuminate\Support\Carbon::parse($e['date']); $ec = \App\Support\Palette::safe($e['color'], $color); @endphp
                        <div class="px-5 py-3 flex items-center gap-3 border-b border-gray-50 last:border-0">
                            <span class="w-11 h-12 rounded-lg flex-shrink-0 flex flex-col items-center justify-center text-white" style="background: linear-gradient(150deg, {{ $ec }}, {{ $ec }}b0);">
                                <span class="text-[10px] font-bold uppercase leading-none">{{ $d->translatedFormat('M') }}</span>
                                <span class="text-lg font-black leading-none mt-0.5">{{ $d->format('j') }}</span>
                            </span>
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-foreground truncate">{{ $e['title'] }}</p>
                                <p class="text-xs text-muted-foreground truncate">{{ $e['club'] }}</p>
                            </div>
                        </div>
                    @empty
                        <p class="px-5 py-6 text-sm text-muted-foreground text-center">{{ __('federations::messages.no_upcoming') }}</p>
                    @endforelse
                </div>

                {{-- Clubs that have asked to join --}}
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                        <h2 class="font-semibold text-foreground">{{ __('federations::messages.waiting') }}</h2>
                        @if($pending->isNotEmpty())
                            <span class="py-0.5 px-2.5 rounded-full text-xs font-medium bg-amber-100 text-amber-700">{{ $pending->count() }}</span>
                        @endif
                    </div>
                    @forelse($pending as $p)
                        <div class="px-5 py-3 flex items-center gap-3 border-b border-gray-50 last:border-0">
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
                        <p class="px-5 py-6 text-sm text-muted-foreground text-center">{{ __('federations::messages.no_pending') }}</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
