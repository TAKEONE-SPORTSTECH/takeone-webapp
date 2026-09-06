{{--
    The clubs standing behind a competition — its own screen, mobile.

    Reached from a tile on the organiser's console, because it is a job with a
    beginning and an end ("who is competing?") rather than something to glance
    at while reading something else.

    ⚠️ The shell yields `personal-content`, NOT `content`. Declaring the wrong
    section renders the page OUTSIDE the padded <main>, which is what left this
    screen with no gutters at all and every card touching the screen edge.

    Structure copied from the console (personal/mobile/event-manage): the shell
    supplies `px-4 py-4`, the band cancels it to run full-bleed, and the content
    starts BELOW the band rather than riding over it — these are controls, and a
    button clipped by a header reads as broken.
--}}
@extends($shell ?? 'layouts.personal-mobile')

@section('title', __('events.clubs_title').' · '.$e['title'])

@section('personal-content')
@php
    $ev = preg_match('/^#[0-9a-fA-F]{3,8}$/', (string) ($e['color'] ?? '')) ? $e['color'] : '#7c3aed';
@endphp

<div class="pb-4">

    {{-- ===== Header =====
         The page-header pattern: full-bleed m-hero band, event colour to
         colour+b0, two soft circles, a control row on top, then chips · title ·
         owner beneath. --}}
    <header class="m-hero -mx-4 -mt-4 px-5 pt-5 pb-8 text-white relative overflow-hidden"
            style="background: linear-gradient(150deg, {{ $ev }}, {{ $ev }}b0);">
        <div class="absolute -right-10 -top-10 w-44 h-44 rounded-full bg-white/10"></div>
        <div class="absolute right-6 bottom-8 w-24 h-24 rounded-full bg-white/10"></div>

        <div class="flex items-center justify-between relative z-50" x-data>
            {{-- Back is a round control holding a tail-less chevron, never a
                 labelled pill and never an arrow (Design Rule #6). --}}
            <a href="{{ route('me.events.manage', $e['key']) }}"
               class="m-press inline-flex items-center w-10 h-10 justify-center rounded-full bg-white/15 border border-white/25 backdrop-blur text-white no-underline flex-shrink-0"
               aria-label="{{ __('personal.event_manage_back_to_page') }}"
               title="{{ __('personal.event_manage_back_to_page') }}">
                <i class="bi bi-chevron-left rtl:rotate-180"></i>
            </a>

            {{-- Actions cluster on the trailing edge. Registering the clubs that
                 competed lives here because it is the thing you come back to
                 this screen to do once the competition is over — and a control
                 in the band is reachable without scrolling past every club.

                 It only appears while there is a temporary club to register;
                 the server refuses it before the event is over, and says why. --}}
            @if($canManage && collect($eventClubs)->contains('temporary', true))
                <button type="button" @click="$dispatch('clubs-promote')"
                        class="m-press w-10 h-10 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center text-white flex-shrink-0"
                        aria-label="{{ __('events.clubs_promote') }}"
                        title="{{ __('events.clubs_promote') }}">
                    <i class="bi bi-patch-check"></i>
                </button>
            @endif
        </div>

        <div class="relative z-10 mt-6">
            <div class="flex items-center gap-1.5 flex-wrap">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur">
                    <i class="bi bi-shield-fill-check"></i> {{ trans_choice('events.clubs_count', count($eventClubs), ['count' => count($eventClubs)]) }}
                </span>
                @if(collect($eventClubs)->contains('temporary', true))
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur">
                        <i class="bi bi-clock-history"></i> {{ __('events.club_temporary') }}
                    </span>
                @endif
            </div>
            <h1 class="text-2xl font-black mt-3 leading-tight">{{ __('events.clubs_title') }}</h1>
            <p class="text-sm text-white/85 mt-1.5 flex items-center gap-1.5">
                <i class="bi bi-trophy"></i>{{ $e['title'] }}
            </p>
        </div>
    </header>

    {{-- Below the band, not over it — the cards here are controls. --}}
    <div class="mt-4 relative z-10">
        <x-event-clubs :event="$e['key']"
                                 :clubs="$eventClubs"
                                 :can-manage="$canManage"
                                 :color="$ev" />
    </div>
</div>
@endsection
