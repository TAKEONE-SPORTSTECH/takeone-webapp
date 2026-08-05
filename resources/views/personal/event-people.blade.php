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
@endphp

@section('content')
<div @include('partials.event-show-script')>

    {{-- Top bar: back to the event, and what this list belongs to. --}}
    <header class="sticky top-0 z-30 h-14 flex items-center gap-3 px-3 border-b border-gray-200 bg-white/95 backdrop-blur">
        <a href="{{ route('me.events.show', $e['key']) }}" data-shell-link data-route="me.events"
           class="w-9 h-9 shrink-0 rounded-xl border border-gray-200 flex items-center justify-center
                  text-muted-foreground hover:bg-muted/60 transition-colors"
           title="{{ $e['title'] }}">
            <i class="bi bi-arrow-left rtl:rotate-180"></i>
        </a>
        <div class="min-w-0">
            <p class="text-[10px] font-extrabold uppercase tracking-[0.14em] text-muted-foreground leading-none">
                {{ $byQual ? __('personal.event_show_finalists') : __('personal.event_show_whos_joined') }}
            </p>
            <h1 class="text-sm font-bold text-foreground truncate leading-tight mt-0.5">{{ $e['title'] }}</h1>
        </div>
    </header>

    <div class="px-4 mt-4 pb-8">
        @include('personal.partials.event-people')
    </div>

    {{-- Moderation confirms through the same dialog the event screen uses. --}}
    <x-confirm-dialog />
</div>
@endsection
