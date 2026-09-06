@extends('layouts.admin-club-mobile')

@section('title', ($club->club_name ?? __('admin.club')) . ' · ' . __('event-sparring::messages.label'))

{{--
    Sparring launcher — mobile.

    The screen a coach opens mid-training, one-handed, standing on the mat. So it
    asks the three questions a session actually needs — sport, mats, bout length
    — as tappable cards rather than a form, and everything else about an event
    (entries, fees, a draw, a weigh-in) is simply not here.

    When a session is already running today the questions are gone entirely and
    the screen is one big way back into it: a second session would strand every
    screen paired to the first.
--}}

@php
    // The sport labels come from each sport's own package, so the word on this
    // button is the same word that sport uses everywhere else in the product.
    $sportLabel = function (string $s) {
        $key = 'sport-'.$s.'::messages.sport_label';

        return __($key) !== $key ? __($key) : ucfirst($s);
    };
@endphp

@section('club-admin-content')
<div class="-mx-4 -mt-4" x-data="sparringLaunch()">

    {{-- ══════════════ Hero ══════════════ --}}
    <header class="m-hero px-5 pt-7 pb-6 text-white relative overflow-hidden">
        <div class="absolute -end-8 -top-8 w-36 h-36 rounded-full bg-white/10"></div>
        <div class="absolute end-6 bottom-4 w-20 h-20 rounded-full bg-white/10"></div>
        <div class="relative z-10">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-white/70 truncate">{{ $club->club_name ?? __('admin.club') }}</p>
                    <h1 class="text-2xl font-black mt-0.5">{{ __('event-sparring::messages.launch_title') }}</h1>
                    <p class="text-xs text-white/80 mt-1.5 leading-relaxed">{{ __('event-sparring::messages.launch_lead') }}</p>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0">
                    <i class="bi bi-lightning-charge text-xl m-float"></i>
                </div>
            </div>
        </div>
    </header>

    <div class="px-4 pt-5 pb-8 space-y-4 mobile-stagger">

        @if($today && ! $today['closed'])
            {{-- ── A session is already running: one way in, nothing else ── --}}
            <a href="{{ $today['url'] }}" class="m-card m-press block rounded-3xl p-5 text-white relative overflow-hidden"
               style="background: linear-gradient(150deg, #0EA5E9, #0EA5E9b0);">
                <div class="absolute -end-6 -top-6 w-28 h-28 rounded-full bg-white/10"></div>
                <div class="relative z-10">
                    <span class="inline-flex items-center gap-1.5 text-[10px] font-black uppercase tracking-wider bg-white/20 border border-white/30 rounded-full px-2.5 py-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-white animate-pulse"></span>
                        {{ __('event-sparring::messages.launch_running') }}
                    </span>
                    <p class="text-2xl font-black mt-3">{{ $today['title'] }}</p>
                    <p class="text-xs text-white/85 mt-1">
                        {{ $sportLabel($today['sport']) }} ·
                        {{ __('event-sparring::messages.console_mat_count', ['n' => $today['mats']]) }}
                        @if($today['started_at'])
                            · {{ __('event-sparring::messages.launch_open_since', ['time' => $today['started_at']]) }}
                        @endif
                    </p>
                    <span class="mt-4 inline-flex items-center gap-2 bg-white text-sky-700 font-bold text-sm rounded-xl px-4 py-2.5">
                        <i class="bi bi-arrow-right-circle"></i>{{ __('event-sparring::messages.launch_resume') }}
                    </span>
                </div>
            </a>
        @elseif(count($sports) === 0)
            <div class="m-card rounded-3xl p-6 text-center">
                <i class="bi bi-emoji-neutral text-3xl text-muted-foreground"></i>
                <p class="text-sm text-muted-foreground mt-2">{{ __('event-sparring::messages.launch_no_sport') }}</p>
            </div>
        @else
            {{-- ── Three questions, then go ── --}}
            <form method="POST" action="{{ route('admin.club.sparring.store', $club->id) }}" class="space-y-4">
                @csrf
                <input type="hidden" name="sport" :value="sport">
                <input type="hidden" name="mats" :value="mats">
                <input type="hidden" name="minutes" :value="minutes">

                {{-- Sport: selection cards, never a dropdown (Design Rule #4) --}}
                <section class="m-card rounded-3xl p-4">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground">{{ __('event-sparring::messages.launch_sport') }}</p>
                    <div class="mt-3 space-y-2">
                        @foreach($sports as $s)
                            <button type="button" @click="sport = '{{ $s }}'"
                                    class="w-full flex items-center gap-3 rounded-2xl border p-3 text-start transition-colors"
                                    :class="sport === '{{ $s }}' ? 'border-primary bg-primary/5' : 'border-border bg-white'">
                                <span class="w-10 h-10 rounded-xl grid place-items-center flex-shrink-0"
                                      :class="sport === '{{ $s }}' ? 'bg-primary text-white' : 'bg-muted text-muted-foreground'">
                                    <i class="bi bi-person-arms-up text-lg"></i>
                                </span>
                                <span class="flex-1 font-bold text-sm text-foreground">{{ $sportLabel($s) }}</span>
                                <span class="w-5 h-5 rounded-full border-2 grid place-items-center flex-shrink-0"
                                      :class="sport === '{{ $s }}' ? 'border-primary' : 'border-border'">
                                    <span x-show="sport === '{{ $s }}'" class="w-2.5 h-2.5 rounded-full bg-primary"></span>
                                </span>
                            </button>
                        @endforeach
                    </div>
                </section>

                {{-- Mats and bout length: chips, because both are small numbers --}}
                <section class="m-card rounded-3xl p-4 space-y-4">
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground">{{ __('event-sparring::messages.launch_mats') }}</p>
                        <div class="mt-2.5 flex gap-2">
                            @foreach([1,2,3,4] as $n)
                                <button type="button" @click="mats = {{ $n }}"
                                        class="flex-1 h-12 rounded-2xl font-black text-lg border transition-colors"
                                        :class="mats === {{ $n }} ? 'border-primary bg-primary text-white' : 'border-border bg-white text-foreground'">{{ $n }}</button>
                            @endforeach
                        </div>
                    </div>
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground">{{ __('event-sparring::messages.launch_minutes') }}</p>
                        <div class="mt-2.5 flex gap-2">
                            @foreach([1,2,3,4] as $n)
                                <button type="button" @click="minutes = {{ $n }}"
                                        class="flex-1 h-12 rounded-2xl font-bold text-sm border transition-colors"
                                        :class="minutes === {{ $n }} ? 'border-primary bg-primary text-white' : 'border-border bg-white text-foreground'">{{ __('event-sparring::messages.launch_minutes_n', ['n' => $n]) }}</button>
                            @endforeach
                        </div>
                    </div>
                </section>

                <button type="submit" class="m-press w-full rounded-2xl py-4 text-white font-black text-base shadow-lg"
                        style="background: linear-gradient(120deg, #0EA5E9, #6366F1);">
                    <i class="bi bi-lightning-charge-fill me-2"></i>{{ __('event-sparring::messages.launch_start') }}
                </button>
            </form>
        @endif

        {{-- ── Earlier sessions ── --}}
        <section class="pt-2">
            <p class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground px-1">{{ __('event-sparring::messages.launch_past') }}</p>
            <div class="mt-2.5 space-y-2">
                @forelse($past as $s)
                    <a href="{{ $s['url'] }}" class="m-card m-press flex items-center gap-3 rounded-2xl p-3.5">
                        <span class="w-10 h-10 rounded-xl grid place-items-center flex-shrink-0 {{ $s['closed'] ? 'bg-muted text-muted-foreground' : 'bg-sky-100 text-sky-600' }}">
                            <i class="bi bi-lightning-charge"></i>
                        </span>
                        <span class="flex-1 min-w-0">
                            <span class="block font-bold text-sm text-foreground truncate">{{ $s['title'] }}</span>
                            <span class="block text-xs text-muted-foreground">
                                {{ $sportLabel($s['sport']) }} · {{ __('event-sparring::messages.launch_bouts_n', ['n' => $s['bouts']]) }}
                            </span>
                        </span>
                        <i class="bi bi-chevron-right text-muted-foreground"></i>
                    </a>
                @empty
                    <p class="text-sm text-muted-foreground px-1">{{ __('event-sparring::messages.launch_past_none') }}</p>
                @endforelse
            </div>
        </section>
    </div>
</div>

{{-- Inline, not @push: the mobile shell swaps this content in over AJAX and a
     pushed stack would not run. --}}
<script>
function sparringLaunch() {
    return {
        sport: @json($sports[0] ?? 'karate'),
        mats: 1,
        minutes: 3,
    };
}
</script>
@endsection
