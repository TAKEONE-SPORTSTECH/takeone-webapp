@extends('layouts.app')

@section('title', __('challenge.personal_challenge_history_title'))

@php
    $wins   = collect($duels)->where('result', 'win')->count();
    $losses = collect($duels)->where('result', 'loss')->count();
    $earned = collect($duels)->sum('points_earned') + collect($solo)->sum('points');
@endphp

@section('content')
<div class="px-4 sm:px-6 lg:px-8 py-6" x-data="{ filter: 'all' }">

    @include('partials.personal-desktop-subnav')

    {{-- Standard header band (Design Rule #6). Platform hub: the shared
         m-hero mesh, no back pill. --}}
    <div class="m-hero -mx-4 sm:-mx-6 lg:-mx-8 -mt-6 overflow-hidden shadow-sm mb-6 text-white relative">
        <div class="absolute -right-10 -top-10 w-44 h-44 rounded-full bg-white/10"></div>
        <div class="absolute right-6 bottom-8 w-24 h-24 rounded-full bg-white/10"></div>

        <div class="relative px-4 sm:px-6 lg:px-8 py-6 sm:py-8">
            <div class="flex items-center justify-between gap-2 mb-4">
                <a href="{{ route('me.challenge') }}"
                   class="inline-flex items-center w-10 h-10 justify-center rounded-full bg-white/15 border border-white/25 backdrop-blur text-white text-sm font-semibold hover:bg-white/25 transition-colors"
           aria-label="{{ __('challenge.subtitle') }}" title="{{ __('challenge.subtitle') }}">
                    <i class="bi bi-chevron-left"></i>
                </a>
            </div>
            <div class="flex items-center gap-1.5 flex-wrap">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur">
                    <i class="bi bi-trophy-fill"></i> {{ $wins }} {{ __('challenge.personal_challenge_history_wins') }}
                </span>
            </div>
            <h1 class="text-2xl font-black mt-3 leading-tight">{{ __('challenge.personal_challenge_history_title') }}</h1>
            <p class="text-sm text-white/85 mt-1.5 flex items-center gap-1.5">
                <i class="bi bi-clock-history"></i>{{ __('challenge.personal_challenge_history_eyebrow') }}
            </p>
        </div>
    </div>

    {{-- ===== Filter ===== --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-1 flex mb-6 max-w-md">
        @foreach(['all'=>__('challenge.personal_challenge_history_filter_all'), 'duels'=>__('challenge.personal_challenge_history_filter_duels'), 'solo'=>__('challenge.personal_challenge_history_filter_solo')] as $key=>$label)
            <button type="button" @click="filter='{{ $key }}'"
                    class="flex-1 py-2 rounded-xl text-xs font-semibold transition-colors"
                    :class="filter==='{{ $key }}' ? 'bg-primary text-white' : 'text-muted-foreground hover:bg-muted'">{{ $label }}</button>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">
        {{-- ===== Duel results ===== --}}
        <div x-show="filter==='all' || filter==='duels'" x-transition>
            <h2 class="text-xs font-bold text-muted-foreground uppercase tracking-wide mb-3">{{ __('challenge.personal_challenge_history_section_duels') }}</h2>
            <div class="space-y-3">
                @foreach($duels as $d)
                    @php $win = $d['result'] === 'win'; @endphp
                    <a href="{{ route('me.challenge.duel', $d['id']) }}"
                       class="block bg-white rounded-2xl shadow-sm border border-gray-100 hover:border-primary/30 hover:shadow-md transition-all p-3.5 flex items-center gap-3">
                        <div class="w-12 h-12 rounded-2xl grid place-items-center text-white flex-shrink-0" style="background: {{ $win ? '#10b981' : '#94a3b8' }};">
                            <i class="bi {{ $win ? 'bi-trophy-fill' : 'bi-emoji-neutral' }} text-lg"></i>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $win ? 'bg-green-50 text-green-600' : 'bg-gray-100 text-muted-foreground' }}">{{ $win ? __('challenge.personal_challenge_history_won') : __('challenge.personal_challenge_history_lost') }}</span>
                                <span class="text-[10px] text-muted-foreground">{{ __('challenge.personal_challenge_history_vs', ['name' => $d['opponent']['name']]) }}</span>
                            </div>
                            <h3 class="font-bold text-foreground mt-1 truncate">{{ $d['discipline'] }}</h3>
                            <p class="text-[11px] text-muted-foreground mt-0.5">{{ $d['final'] }} · {{ $d['date'] }}</p>
                        </div>
                        <div class="text-end flex-shrink-0">
                            <p class="text-sm font-black {{ $win ? 'text-green-600' : 'text-muted-foreground' }}">{{ $win ? '+'.$d['points_earned'] : '0' }}</p>
                            <p class="text-[10px] text-muted-foreground">{{ __('challenge.personal_challenge_history_pts') }}</p>
                        </div>
                    </a>
                @endforeach
                @if(empty($duels))
                    <div class="bg-white rounded-2xl border border-gray-100 px-5 py-10 text-center text-sm text-muted-foreground">{{ __('challenge.personal_challenge_history_no_duels') }}</div>
                @endif
            </div>
        </div>

        {{-- ===== Solo results ===== --}}
        <div x-show="filter==='all' || filter==='solo'" x-transition>
            <h2 class="text-xs font-bold text-muted-foreground uppercase tracking-wide mb-3">{{ __('challenge.personal_challenge_history_section_solo') }}</h2>
            <div class="space-y-3">
                @foreach($solo as $c)
                    <a href="{{ route('me.challenge.show', $c['id']) }}"
                       class="block bg-white rounded-2xl shadow-sm border border-gray-100 hover:border-primary/30 hover:shadow-md transition-all p-3.5 flex items-center gap-3">
                        <div class="w-12 h-12 rounded-2xl grid place-items-center text-white flex-shrink-0" style="background: linear-gradient(160deg, {{ $c['color'] }}, {{ $c['color'] }}d0);">
                            <i class="bi {{ $c['icon'] }} text-lg"></i>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-green-50 text-green-600"><i class="bi bi-check2"></i> {{ __('challenge.personal_challenge_history_completed') }}</span>
                                @if($c['rank'])<span class="text-[10px] text-muted-foreground">{{ __('challenge.personal_challenge_history_finished_rank', ['rank' => $c['rank']]) }}</span>@endif
                            </div>
                            <h3 class="font-bold text-foreground mt-1 truncate">{{ $c['title'] }}</h3>
                            <p class="text-[11px] text-muted-foreground mt-0.5">{{ $c['tag'] }} · {{ $c['participants'] }} {{ __('challenge.personal_challenge_history_joined') }}</p>
                        </div>
                        <div class="text-end flex-shrink-0">
                            <p class="text-sm font-black text-green-600">+{{ $c['points'] }}</p>
                            <p class="text-[10px] text-muted-foreground">{{ __('challenge.personal_challenge_history_pts') }}</p>
                        </div>
                    </a>
                @endforeach
                @if(empty($solo))
                    <div class="bg-white rounded-2xl border border-gray-100 px-5 py-10 text-center text-sm text-muted-foreground">{{ __('challenge.personal_challenge_history_no_solo') }}</div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
