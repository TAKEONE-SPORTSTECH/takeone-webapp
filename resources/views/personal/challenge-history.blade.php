@extends('layouts.personal-mobile')

@section('title', 'Results & History')

{{--
    Challenge results & history — DUMMY. Past duels (win/loss with final scores)
    and completed solo challenges, with a record summary and an All/Duels/Solo
    filter. Reuses the shared mobile motion vocabulary and design tokens.
--}}
@php
    $wins   = collect($duels)->where('result', 'win')->count();
    $losses = collect($duels)->where('result', 'loss')->count();
    $earned = collect($duels)->sum('points_earned') + collect($solo)->sum('points');
@endphp

@section('personal-content')
<div x-data="{ filter: 'all' }" class="-mx-4 -mt-4 pb-4">

    {{-- ===== Header ===== --}}
    <header class="m-hero px-5 pt-5 pb-12 text-white relative overflow-hidden">
        <div class="absolute -right-8 -top-8 w-36 h-36 rounded-full bg-white/10"></div>
        <div class="flex items-center gap-3 relative z-10">
            <a href="{{ route('me.challenge') }}" data-shell-link data-route="me.challenge"
               class="m-press w-10 h-10 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center" aria-label="Back">
                <i class="bi bi-arrow-left text-lg"></i>
            </a>
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-wider text-white/70">Challenges</p>
                <h1 class="text-xl font-black">Results &amp; History</h1>
            </div>
        </div>

        {{-- record --}}
        <div class="flex gap-2 mt-5 relative z-10">
            <div class="flex-1 rounded-2xl bg-white/12 border border-white/20 backdrop-blur px-3 py-2.5 text-center">
                <p class="text-xl font-black leading-none">{{ $wins }}</p>
                <p class="text-[10px] text-white/75 mt-1 uppercase tracking-wide">Wins</p>
            </div>
            <div class="flex-1 rounded-2xl bg-white/12 border border-white/20 backdrop-blur px-3 py-2.5 text-center">
                <p class="text-xl font-black leading-none">{{ $losses }}</p>
                <p class="text-[10px] text-white/75 mt-1 uppercase tracking-wide">Losses</p>
            </div>
            <div class="flex-1 rounded-2xl bg-white/12 border border-white/20 backdrop-blur px-3 py-2.5 text-center">
                <p class="text-xl font-black leading-none" data-countup="{{ $earned }}">0</p>
                <p class="text-[10px] text-white/75 mt-1 uppercase tracking-wide">Points</p>
            </div>
        </div>
    </header>

    {{-- ===== Filter ===== --}}
    <div class="px-4 -mt-6 relative z-10">
        <div class="bg-white rounded-2xl shadow-md border border-gray-100 p-1 flex">
            @foreach(['all'=>'All', 'duels'=>'Duels', 'solo'=>'Solo'] as $key=>$label)
                <button type="button" @click="filter='{{ $key }}'"
                        class="m-press flex-1 py-2 rounded-xl text-xs font-semibold transition-colors"
                        :class="filter==='{{ $key }}' ? 'bg-primary text-white' : 'text-muted-foreground'">{{ $label }}</button>
            @endforeach
        </div>
    </div>

    {{-- ===== Duel results ===== --}}
    <div class="px-4 mt-5 space-y-3" x-show="filter==='all' || filter==='duels'" x-transition>
        <h2 class="text-xs font-bold text-muted-foreground uppercase tracking-wide">Duels</h2>
        @foreach($duels as $d)
            @php $win = $d['result'] === 'win'; @endphp
            <a href="{{ route('me.challenge.duel', $d['id']) }}" data-shell-link data-route="me.challenge"
               class="block m-card m-press rounded-2xl p-3.5 flex items-center gap-3">
                <div class="w-12 h-12 rounded-2xl grid place-items-center text-white flex-shrink-0" style="background: {{ $win ? '#10b981' : '#94a3b8' }};">
                    <i class="bi {{ $win ? 'bi-trophy-fill' : 'bi-emoji-neutral' }} text-lg"></i>
                </div>
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $win ? 'bg-green-50 text-green-600' : 'bg-gray-100 text-muted-foreground' }}">{{ $win ? 'WON' : 'LOST' }}</span>
                        <span class="text-[10px] text-muted-foreground">vs {{ $d['opponent']['name'] }}</span>
                    </div>
                    <h3 class="font-bold text-foreground mt-1 truncate">{{ $d['discipline'] }}</h3>
                    <p class="text-[11px] text-muted-foreground mt-0.5">{{ $d['final'] }} · {{ $d['date'] }}</p>
                </div>
                <div class="text-right flex-shrink-0">
                    <p class="text-sm font-black {{ $win ? 'text-green-600' : 'text-muted-foreground' }}">{{ $win ? '+'.$d['points_earned'] : '0' }}</p>
                    <p class="text-[10px] text-muted-foreground">pts</p>
                </div>
            </a>
        @endforeach
        @if(empty($duels))
            <div class="bg-white rounded-2xl border border-gray-100 px-5 py-8 text-center text-sm text-muted-foreground">No duel results yet.</div>
        @endif
    </div>

    {{-- ===== Solo results ===== --}}
    <div class="px-4 mt-5 space-y-3" x-show="filter==='all' || filter==='solo'" x-transition>
        <h2 class="text-xs font-bold text-muted-foreground uppercase tracking-wide">Solo challenges</h2>
        @foreach($solo as $c)
            <a href="{{ route('me.challenge.show', $c['id']) }}" data-shell-link data-route="me.challenge"
               class="block m-card m-press rounded-2xl p-3.5 flex items-center gap-3">
                <div class="w-12 h-12 rounded-2xl grid place-items-center text-white flex-shrink-0" style="background: linear-gradient(160deg, {{ $c['color'] }}, {{ $c['color'] }}d0);">
                    <i class="bi {{ $c['icon'] }} text-lg"></i>
                </div>
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-green-50 text-green-600"><i class="bi bi-check2"></i> Completed</span>
                        @if($c['rank'])<span class="text-[10px] text-muted-foreground">Finished #{{ $c['rank'] }}</span>@endif
                    </div>
                    <h3 class="font-bold text-foreground mt-1 truncate">{{ $c['title'] }}</h3>
                    <p class="text-[11px] text-muted-foreground mt-0.5">{{ $c['tag'] }} · {{ $c['participants'] }} joined</p>
                </div>
                <div class="text-right flex-shrink-0">
                    <p class="text-sm font-black text-green-600">+{{ $c['points'] }}</p>
                    <p class="text-[10px] text-muted-foreground">pts</p>
                </div>
            </a>
        @endforeach
        @if(empty($solo))
            <div class="bg-white rounded-2xl border border-gray-100 px-5 py-8 text-center text-sm text-muted-foreground">No completed solo challenges yet.</div>
        @endif
    </div>

</div>
@endsection
