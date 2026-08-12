@extends('layouts.personal-mobile')

@section('title', __('personal.event_show_whos_joined'))

{{--
    Who's joined — the roster on its own screen.

    Same partial the event screen used to render inline; only the surroundings
    changed. It sits inside the event-show Alpine state (partials.event-show-script)
    because the rows call moderate() and read goingCount / spectators /
    blockedCount, exactly as before — moderating from here behaves identically.
--}}

@php
    // Same derivations the event screen makes before including the script
    // partial — that partial reads $hasTicket, and the roster reads $byQual.
    $pPaid      = ! str_contains(strtolower($e['participant_fee']), 'free')
                  && ! str_contains(strtolower($e['participant_fee']), 'qualified');
    $byQual     = str_contains(strtolower($e['participant_fee']), 'qualified');
    $hasTicket  = ! empty($e['spectator']);
    $ticketPaid = $hasTicket && ! str_contains(strtolower($e['spectator']['fee']), 'free');

    // Seed the header's readiness figures from the same rows the roster builds
    // its gates from, so the first paint is right and Alpine only takes over
    // when an official signs someone off.
    $gateRows = collect($e['participants'] ?? [])->filter(fn ($p) => ($p['reg_id'] ?? null) && ($p['id'] ?? null));
    $gateTotalSeed = $gateRows->count();
    $readyCountSeed = $gateRows->filter(fn ($p) => ($p['weighed_verified'] ?? false) && ($p['paid_verified'] ?? false))->count();
@endphp

@section('content')
<div @include('partials.event-show-script')>

    {{-- ===== Header =====
         The page-header pattern (CLAUDE.md → Design Rule #6). Kept deliberately
         terse: an eyebrow for the event, the page's own name, and ONE line of
         numbers. The officials' readiness is a pill plus the progress edge along
         the bottom of the band; the sentence that explains the rule is the pill's
         tooltip, because a header should state where things stand, not teach. --}}
    <header class="m-hero px-5 pt-5 pb-10 text-white relative overflow-hidden"
            @if(($canWeigh ?? false) || ($canPay ?? false))
                x-data="{ ready: {{ $readyCountSeed }}, total: {{ $gateTotalSeed }}, tab: 'participants' }"
                @roster-readiness.window="ready = $event.detail.ready; total = $event.detail.total; tab = $event.detail.tab"
            @endif
            style="background: linear-gradient(150deg, {{ $e['color'] }}, {{ $e['color'] }}b0);">
        <div class="absolute -right-10 -top-10 w-44 h-44 rounded-full bg-white/10"></div>
        <div class="absolute right-6 bottom-8 w-24 h-24 rounded-full bg-white/10"></div>

        <div class="flex items-center justify-between gap-3 relative z-50">
            <a href="{{ route('me.events.show', $e['key']) }}" data-shell-link data-route="me.events"
               class="m-press w-10 h-10 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0"
               aria-label="{{ $e['title'] }}">
                <i class="bi bi-arrow-left text-lg rtl:rotate-180"></i>
            </a>

            @if(($canWeigh ?? false) || ($canPay ?? false))
                {{-- Officials only: how many entries can actually be drawn. --}}
                <span x-show="tab === 'participants'" x-cloak
                      title="{{ __('personal.event_verify_ready_hint') }}"
                      class="h-10 ps-3 pe-3.5 rounded-full bg-white/15 border border-white/25 backdrop-blur inline-flex items-center gap-2 flex-shrink-0">
                    <i class="bi bi-clipboard2-check"></i>
                    <span class="text-sm font-black tabular-nums leading-none">
                        <span x-text="ready">{{ $readyCountSeed }}</span><span class="text-white/55">/<span x-text="total">{{ $gateTotalSeed }}</span></span>
                    </span>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-white/70">{{ __('personal.event_verify_ready') }}</span>
                </span>
            @endif
        </div>

        <div class="relative z-10 mt-5">
            <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-white/70 truncate">{{ $e['title'] }}</p>
            <h1 class="text-2xl font-black mt-1 leading-tight">
                {{ $byQual ? __('personal.event_show_finalists') : __('personal.event_show_whos_joined') }}
            </h1>
            {{-- One line, dot-separated — the counts, nothing else. --}}
            <p class="text-[13px] font-medium text-white/85 mt-1.5 flex items-center gap-2 flex-wrap">
                <span class="inline-flex items-center gap-1.5">
                    <i class="bi bi-people-fill text-white/60"></i><span x-text="goingCount">{{ $e['participants_total'] ?? $e['going'] }}</span> {{ __('personal.event_show_in') }}
                </span>
                @if($hasTicket)
                    <span class="w-1 h-1 rounded-full bg-white/40"></span>
                    <span class="inline-flex items-center gap-1.5">
                        <i class="bi bi-ticket-perforated text-white/60"></i><span x-text="spectators">{{ $e['spectator']['count'] }}</span> {{ __('personal.event_show_spectators') }}
                    </span>
                @endif
            </p>
        </div>

        @if(($canWeigh ?? false) || ($canPay ?? false))
            {{-- The same number as the pill, read at a glance: how much of the
                 roster is cleared. Sits on the band's bottom edge, under the
                 cards that overlap it. --}}
            <div class="absolute inset-x-0 bottom-0 h-1 bg-white/15" x-show="tab === 'participants'" x-cloak>
                <div class="h-full bg-white/70 transition-all duration-500"
                     :style="`width: ${total ? Math.round(ready / total * 100) : 0}%`"></div>
            </div>
        @endif
    </header>

    {{-- -mt-6 is half a filter tray's height, so the tray (the first thing in
         here) lands with its CENTRE on the band's bottom border — floating on
         the edge, not below it. Without the tray, the search row rides up by the
         same amount instead of sitting under a strip of empty colour. --}}
    <div class="px-4 -mt-6 relative z-10 pb-8">
        @include('personal.partials.event-people')
    </div>

    {{-- Moderation confirms through the same dialog the event screen uses. --}}
    <x-confirm-dialog />
</div>
@endsection
