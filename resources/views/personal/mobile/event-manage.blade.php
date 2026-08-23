@extends('layouts.personal-mobile')

@section('title', __('personal.event_manage_title').' · '.$e['title'])

{{--
    Event console — mobile. The organiser's screen, and the appointed
    official's; personal/mobile/event-show is everybody else's.

    Shape: a mobile-first hub. A status band that says where the event stands,
    then grouped rows — one per job, each carrying what is waiting inside it, so
    "what still needs me?" is answerable without opening anything. Work with a
    screen of its own (roster, entries, draw, officials, board, verification) is
    a link; work small enough to finish here (preparations, documents, winners,
    the P&L) opens in place.

    Every row is gated: an official is appointed to a job, not handed the event.
    The endpoints behind each row re-check the same rule server-side — this page
    decides only what to OFFER.

    It runs on the SAME Alpine root as the public event page
    (partials/event-show-script), so cancel/delete/edit, the winners editor and
    the P&L are the implementations that already existed, not second copies.
--}}
@section('personal-content')
@php
    $mgColor = preg_match('/^#[0-9a-fA-F]{3,8}$/', (string) ($e['color'] ?? '')) ? $e['color'] : '#7c3aed';
    $started = (bool) ($e['started'] ?? false);
    // The shared event Alpine root (included below) is the public page's root too,
    // so it reads the same locals. The console has no join flow, but the root is
    // one object — give it the values rather than fork it.
    $pPaid   = ! str_contains(strtolower($e['participant_fee'] ?? ''), 'free') && ! str_contains(strtolower($e['participant_fee'] ?? ''), 'qualified');
    $byQual  = str_contains(strtolower($e['participant_fee'] ?? ''), 'qualified');
    $hasTicket = ! empty($e['spectator']);
    $ticketPaid = $hasTicket && ! str_contains(strtolower($e['spectator']['fee'] ?? ''), 'free');
    $banned = false;                 // an organiser is never barred from their own console
    $eligReason = null;
    $whyNot = null;
    $cancelled = (bool) ($e['cancelled'] ?? false);

    // Jobs that live on a screen of their own. Rendered in the order the day
    // runs: who is coming, who is entered, how they are drawn, who officiates.
    $links = [];

    if ($canWeigh || $canPay) {
        $links[] = [
            'icon' => 'bi-patch-check-fill', 'tone' => 'bg-blue-50 text-blue-600',
            'label' => __('personal.event_manage_verification'),
            // The desk is its own screen again. "Who's joined" is now a reading
            // surface with no controls on it for anyone, so an official needs a
            // door to the place where the gates actually are.
            'sub' => trans_choice('personal.event_manage_entrants', $counts['entrants'], ['count' => $counts['entrants']])
                .' · '.__('personal.event_manage_verification_sub'),
            'href' => route('me.events.verify', $e['key']),
        ];
    }

    if ($canManage) {
        $links[] = [
            'icon' => 'bi-person-plus-fill', 'tone' => 'bg-primary/10 text-primary',
            'label' => __('personal.event_manage_entries'),
            'sub' => __('personal.event_manage_entries_sub'),
            'href' => route('me.events.entry-roster', $e['key']),
        ];
        $links[] = [
            'icon' => 'bi-diagram-3-fill', 'tone' => 'bg-purple-50 text-purple-600',
            'label' => __('personal.event_manage_draw'),
            'sub' => $started ? __('personal.event_manage_draw_locked') : __('personal.event_manage_draw_sub'),
            'href' => route('me.events.bracket.manage', $e['key']),
        ];
        $links[] = [
            'icon' => 'bi-person-badge-fill', 'tone' => 'bg-teal-50 text-teal-600',
            'label' => __('personal.event_manage_officials'),
            'sub' => trans_choice('personal.event_manage_officials_count', $counts['officials'], ['count' => $counts['officials']]),
            // The officiating sheet, not the JSON endpoint of the same name —
            // that one answers the appoint picker and rendered as raw JSON here.
            'href' => route('me.events.officiating', $e['key']),
        ];
        $links[] = [
            'icon' => 'bi-tv-fill', 'tone' => 'bg-slate-100 text-slate-600',
            'label' => __('personal.event_manage_board'),
            'sub' => __('personal.event_manage_board_sub'),
            'href' => route('me.events.board', $e['key']),
        ];
    }
@endphp

{{-- The band is full-bleed, so the page wrapper's padding is cancelled here and
     restored by the cards below it. --}}
<div @include('partials.event-show-script') class="pb-4">

    {{-- ===== Header =====
         The page-header pattern (see CLAUDE.md → Page Headers): full-bleed
         m-hero band, event colour to colour+b0, two soft circles, a control row
         on top, then chips · title · owner beneath. Never a small rounded card
         with the title squeezed beside a back arrow. --}}
    <header class="m-hero -mx-4 -mt-4 px-5 pt-5 pb-8 text-white relative overflow-hidden"
            style="background: linear-gradient(150deg, {{ $mgColor }}, {{ $mgColor }}b0);">
        <div class="absolute -right-10 -top-10 w-44 h-44 rounded-full bg-white/10"></div>
        <div class="absolute right-6 bottom-8 w-24 h-24 rounded-full bg-white/10"></div>

        <div class="flex items-center justify-between relative z-50">
            <a href="{{ route('me.events.show', $e['key']) }}" data-shell-link data-route="me.events"
               class="m-press inline-flex items-center gap-2 h-10 ps-3 pe-4 rounded-full bg-white/15 border border-white/25 backdrop-blur text-white text-sm font-semibold no-underline flex-shrink-0"
               aria-label="{{ __('personal.event_manage_back_to_page') }}">
                <i class="bi bi-arrow-left rtl:rotate-180"></i>{{ __('personal.event_show_event') }}
            </a>
            <div class="flex items-center gap-2">
                <a href="{{ route('me.events.show', $e['key']) }}" data-shell-link data-route="me.events"
                   class="m-press h-10 px-4 rounded-full bg-white/15 border border-white/25 backdrop-blur inline-flex items-center gap-2 text-xs font-bold">
                    <i class="bi bi-eye"></i>{{ __('personal.event_manage_view_public') }}
                </a>
            </div>
        </div>

        <div class="relative z-10 mt-6">
            <div class="flex items-center gap-1.5 flex-wrap">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur">
                    <i class="bi bi-sliders"></i> {{ __('personal.event_manage_title') }}
                </span>
                <span x-show="cancelled" x-cloak class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur">
                    <i class="bi bi-exclamation-triangle-fill"></i> {{ __('personal.event_show_cancelled_banner') }}
                </span>
                @if($started)
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur">
                        <i class="bi bi-play-circle-fill"></i> {{ __('personal.event_start_running') }}
                    </span>
                @endif
            </div>
            <h1 class="text-2xl font-black mt-3 leading-tight">{{ $e['title'] }}</h1>
            <p class="text-sm text-white/85 mt-1.5 flex items-center gap-1.5">
                <i class="bi bi-calendar-event"></i>{{ trim(($e['wday'] ?? '').' '.($e['day'] ?? '').' '.($e['mon'] ?? '')) }}
            </p>
        </div>
    </header>

    {{-- The cards start BELOW the band, never over it. They are controls — a
         button clipped by the header reads as broken, and half a tile sitting on
         the colour looks like a mistake rather than depth. The band's own bottom
         padding is sized to its content instead. --}}
    <div class="mt-4 relative z-10 space-y-4">

    {{-- ===== Preparations — the component brings its own button and sheet ===== --}}
    @if($canOfficiate)
        <x-event-checklist :event="$e['key']"
                           :items="$checklist ?? []"
                           :can-manage="$canManage"
                           :can-check="true"
                           :started="$started"
                           :overridden="$e['start_overridden'] ?? false"
                           :color="$mgColor" />
    @endif

    {{-- ===== Jobs with a screen of their own ===== --}}
    @if(count($links))
        <div class="space-y-2">
            @foreach($links as $row)
                <a href="{{ $row['href'] }}" data-shell-link data-route="me.events"
                   class="m-card m-press bg-white rounded-2xl border border-gray-100 shadow-sm p-3.5 flex items-center gap-3">
                    <span class="w-11 h-11 rounded-2xl grid place-items-center flex-shrink-0 {{ $row['tone'] }}">
                        <i class="{{ \App\Support\Icon::bi($row['icon'], 'text-lg') }}"></i>
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-bold text-foreground truncate">{{ $row['label'] }}</span>
                        <span class="block text-[11px] text-muted-foreground truncate mt-0.5">{{ $row['sub'] }}</span>
                    </span>
                    <i class="bi bi-chevron-right rtl:rotate-180 text-muted-foreground/50 text-xs flex-shrink-0"></i>
                </a>
            @endforeach
        </div>
    @endif

    {{-- ===== Hall screens — one row, opening the whole panel as a sheet. Only
              for a type that drives any. ===== --}}
    @if($canManage && ! empty($screens))
        <x-court-screens :event="$e['key']"
                         :mats="$screens['mats'] ?? []"
                         :screens="$screens['screens'] ?? []"
                         :surfaces="$screenSurfaces ?? []"
                         :new-url="$screenNewUrl ?? null"
                         :color="$mgColor"
                         :sheet="true" />

        {{-- What those screens PLAY. Renders nothing on the page — only the sheet
             the panel's gear opens. --}}
        <x-event-screen-audio :event="$e['key']"
                              :media="$screenAudio ?? []"
                              :audio-url="$screenAudioUrls ?? []"
                              :color="$mgColor" />
    @endif



    @if($canManage)
        {{-- ===== Finish here ===== --}}
        <div class="space-y-2">
            <button type="button" @click="openResults()"
                    class="m-card m-press w-full text-start bg-white rounded-2xl border border-gray-100 shadow-sm p-3.5 flex items-center gap-3">
                <span class="w-11 h-11 rounded-2xl grid place-items-center flex-shrink-0 bg-amber-50 text-amber-600"><i class="bi bi-trophy-fill text-lg"></i></span>
                <span class="min-w-0 flex-1">
                    <span class="block text-sm font-bold text-foreground">{{ __('personal.event_show_winners') }}</span>
                    <span class="block text-[11px] text-muted-foreground mt-0.5" x-text="results.length ? @js(__('personal.event_manage_results_recorded')) : @js(__('personal.event_manage_results_sub'))"></span>
                </span>
                <i class="bi bi-chevron-right rtl:rotate-180 text-muted-foreground/50 text-xs flex-shrink-0"></i>
            </button>

            <button type="button" @click="financeOpen=true"
                    class="m-card m-press w-full text-start bg-white rounded-2xl border border-gray-100 shadow-sm p-3.5 flex items-center gap-3">
                <span class="w-11 h-11 rounded-2xl grid place-items-center flex-shrink-0 bg-green-50 text-green-600"><i class="bi bi-cash-stack text-lg"></i></span>
                <span class="min-w-0 flex-1">
                    <span class="block text-sm font-bold text-foreground">{{ __('personal.event_show_finance') }}</span>
                    <span class="block text-[11px] text-muted-foreground mt-0.5" x-text="money(profit)"></span>
                </span>
                <i class="bi bi-chevron-right rtl:rotate-180 text-muted-foreground/50 text-xs flex-shrink-0"></i>
            </button>

            {{-- Documents — one row like the others, opening a sheet. The list and
                 its uploader used to sit open on the page, which put a file field
                 and a dashed drop box in the middle of a column of doors. --}}
            <x-event-documents :event="$e['key']" :documents="$documents ?? []"
                               :can-manage="true" :color="$mgColor" :sheet="true" />
        </div>


        {{-- ===== The event itself ===== --}}
        <div>
            <p class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground/80 mb-2 px-1">{{ __('personal.event_manage_the_event') }}</p>
            <div class="space-y-2">
                <button type="button" @click="goEdit()"
                        class="m-card m-press w-full text-start bg-white rounded-2xl border border-gray-100 shadow-sm p-3.5 flex items-center gap-3">
                    <span class="w-11 h-11 rounded-2xl grid place-items-center flex-shrink-0 bg-accent text-primary"><i class="bi bi-pencil-fill text-lg"></i></span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-bold text-foreground">{{ __('personal.event_show_edit_event') }}</span>
                        <span class="block text-[11px] text-muted-foreground mt-0.5">{{ __('personal.event_manage_edit_sub') }}</span>
                    </span>
                    <i class="bi bi-chevron-right rtl:rotate-180 text-muted-foreground/50 text-xs flex-shrink-0"></i>
                </button>

                {{-- Package actions (build the draw, clear it, …). They come from
                     the event type, so a new type brings its own without editing
                     this page — and an action the package does not offer today
                     simply is not here. --}}
                @foreach($actions as $action)
                    <form method="POST" action="{{ route('me.events.action', [$e['key'], $action['action']]) }}">
                        @csrf
                        <button type="submit"
                                class="m-card m-press w-full text-start bg-white rounded-2xl border border-gray-100 shadow-sm p-3.5 flex items-center gap-3">
                            <span class="w-11 h-11 rounded-2xl grid place-items-center flex-shrink-0 bg-primary/10 text-primary"><i class="{{ \App\Support\Icon::bi($action['icon'] ?? null, 'text-lg', 'bi-lightning-charge') }}"></i></span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-bold text-foreground">{{ $action['label'] }}</span>
                            </span>
                            <i class="bi bi-chevron-right rtl:rotate-180 text-muted-foreground/50 text-xs flex-shrink-0"></i>
                        </button>
                    </form>
                @endforeach
            </div>
        </div>

        {{-- ===== Ending it ===== --}}
        <div>
            <p class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground/80 mb-2 px-1">{{ __('personal.event_manage_danger') }}</p>
            <div class="space-y-2">
                <button type="button" x-show="! cancelled" @click="cancelEvent()"
                        class="m-press w-full text-start bg-white rounded-2xl border border-amber-200 p-3.5 flex items-center gap-3">
                    <span class="w-11 h-11 rounded-2xl grid place-items-center flex-shrink-0 bg-amber-50 text-amber-600"><i class="bi bi-slash-circle text-lg"></i></span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-bold text-amber-700">{{ __('personal.event_show_cancel_event') }}</span>
                        <span class="block text-[11px] text-amber-600/80 mt-0.5">{{ __('personal.event_manage_cancel_sub') }}</span>
                    </span>
                </button>
                <button type="button" @click="deleteEvent()"
                        class="m-press w-full text-start bg-white rounded-2xl border border-red-200 p-3.5 flex items-center gap-3">
                    <span class="w-11 h-11 rounded-2xl grid place-items-center flex-shrink-0 bg-red-50 text-red-600"><i class="bi bi-trash text-lg"></i></span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-bold text-red-700">{{ __('personal.event_show_delete_event') }}</span>
                        <span class="block text-[11px] text-red-500/80 mt-0.5">{{ __('personal.event_manage_delete_sub') }}</span>
                    </span>
                </button>
            </div>
        </div>

        {{-- The two sheets, exactly as the public page used to carry them --}}
        @include('partials.event-results-sheet')
        @include('partials.event-finance-sheet')
    @endif
    </div>{{-- /cards --}}
</div>
@endsection
