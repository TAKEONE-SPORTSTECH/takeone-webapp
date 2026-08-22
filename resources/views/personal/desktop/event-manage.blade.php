@extends('layouts.app')

@section('title', __('personal.event_manage_title').' · '.$e['title'])

{{--
    Event console — desktop. The organiser's screen, and the appointed
    official's; personal/desktop/event-show is everybody else's.

    Same jobs as the mobile console, laid out for a wide screen: preparations
    across the top (it is the run-day gate), then a grid of job cards, then the
    event itself and the danger zone in a narrower column — destructive things
    should not be the widest thing on the page.

    Every card is gated: an official is appointed to a job, not handed the
    event. The endpoints behind each card re-check the same rule server-side.

    Runs on the SAME Alpine root as the public event page
    (partials/event-show-script), so cancel/delete/edit, the winners editor and
    the P&L are the implementations that already existed, not second copies.
--}}
@section('content')
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

    $cards = [];

    if ($canWeigh || $canPay) {
        $cards[] = [
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
        $cards[] = [
            'icon' => 'bi-person-plus-fill', 'tone' => 'bg-primary/10 text-primary',
            'label' => __('personal.event_manage_entries'),
            'sub' => __('personal.event_manage_entries_sub'),
            'href' => route('me.events.entry-roster', $e['key']),
        ];
        $cards[] = [
            'icon' => 'bi-diagram-3-fill', 'tone' => 'bg-purple-50 text-purple-600',
            'label' => __('personal.event_manage_draw'),
            'sub' => $started ? __('personal.event_manage_draw_locked') : __('personal.event_manage_draw_sub'),
            'href' => route('me.events.bracket.manage', $e['key']),
        ];
        $cards[] = [
            'icon' => 'bi-person-badge-fill', 'tone' => 'bg-teal-50 text-teal-600',
            'label' => __('personal.event_manage_officials'),
            'sub' => trans_choice('personal.event_manage_officials_count', $counts['officials'], ['count' => $counts['officials']]),
            'href' => route('me.events.officials', $e['key']),
        ];
        $cards[] = [
            'icon' => 'bi-tv-fill', 'tone' => 'bg-slate-100 text-slate-600',
            'label' => __('personal.event_manage_board'),
            'sub' => __('personal.event_manage_board_sub'),
            'href' => route('me.events.board', $e['key']),
        ];
    }
@endphp

<div class="px-4 sm:px-6 lg:px-8 py-6" @include('partials.event-show-script')>

    {{-- ===== Header =====
         The page-header pattern (see CLAUDE.md → Page Headers): full-bleed
         m-hero band, event colour to colour+b0, two soft circles, a control row
         on top, then chips · title · owner beneath. --}}
    <header class="m-hero -mx-4 sm:-mx-6 lg:-mx-8 -mt-6 px-6 lg:px-8 pt-6 pb-20 text-white relative overflow-hidden"
            style="background: linear-gradient(150deg, {{ $mgColor }}, {{ $mgColor }}b0);">
        <div class="absolute -right-10 -top-10 w-56 h-56 rounded-full bg-white/10"></div>
        <div class="absolute right-24 bottom-6 w-28 h-28 rounded-full bg-white/10"></div>

        <div class="flex items-center justify-between relative z-50">
            <a href="{{ route('me.events.show', $e['key']) }}"
               class="w-10 h-10 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center hover:bg-white/25 transition-colors"
               title="{{ __('personal.event_manage_back_to_page') }}">
                <i class="bi bi-arrow-left text-lg rtl:rotate-180"></i>
            </a>
            <a href="{{ route('me.events.show', $e['key']) }}"
               class="h-10 px-4 rounded-full bg-white/15 border border-white/25 backdrop-blur inline-flex items-center gap-2 text-xs font-bold hover:bg-white/25 transition-colors">
                <i class="bi bi-eye"></i>{{ __('personal.event_manage_view_public') }}
            </a>
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
            <h1 class="text-3xl font-black mt-3 leading-tight">{{ $e['title'] }}</h1>
            <p class="text-sm text-white/85 mt-1.5 flex items-center gap-1.5">
                <i class="bi bi-calendar-event"></i>{{ trim(($e['wday'] ?? '').' '.($e['day'] ?? '').' '.($e['mon'] ?? '')) }}
            </p>
        </div>
    </header>

    {{-- The cards ride up over the band's tail, as on the event page. --}}
    <div class="-mt-12 relative z-10 space-y-6">

    {{-- ===== Preparations — the component brings its own button and dialog ===== --}}
    @if($canOfficiate)
        <x-event-checklist :event="$e['key']"
                           :items="$checklist ?? []"
                           :can-manage="$canManage"
                           :can-check="true"
                           :started="$started"
                           :overridden="$e['start_overridden'] ?? false"
                           :color="$mgColor" />
    @endif

    {{-- ===== The jobs ===== --}}
    @if(count($cards))
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($cards as $card)
                <a href="{{ $card['href'] }}"
                   class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 flex items-start gap-4 hover:shadow-md hover:border-primary/30 transition-all group">
                    <span class="w-12 h-12 rounded-xl grid place-items-center flex-shrink-0 {{ $card['tone'] }}">
                        <i class="{{ \App\Support\Icon::bi($card['icon'], 'text-xl') }}"></i>
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-bold text-gray-900">{{ $card['label'] }}</span>
                        <span class="block text-xs text-muted-foreground mt-1">{{ $card['sub'] }}</span>
                    </span>
                    <i class="bi bi-chevron-right rtl:rotate-180 text-gray-300 group-hover:text-primary transition-colors"></i>
                </a>
            @endforeach

            @if($canManage)
                <button type="button" @click="openResults()"
                        class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 flex items-start gap-4 text-start hover:shadow-md hover:border-primary/30 transition-all group">
                    <span class="w-12 h-12 rounded-xl grid place-items-center flex-shrink-0 bg-amber-50 text-amber-600"><i class="bi bi-trophy-fill text-xl"></i></span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-bold text-gray-900">{{ __('personal.event_show_winners') }}</span>
                        <span class="block text-xs text-muted-foreground mt-1" x-text="results.length ? @js(__('personal.event_manage_results_recorded')) : @js(__('personal.event_manage_results_sub'))"></span>
                    </span>
                    <i class="bi bi-chevron-right rtl:rotate-180 text-gray-300 group-hover:text-primary transition-colors"></i>
                </button>

                <button type="button" @click="financeOpen=true"
                        class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 flex items-start gap-4 text-start hover:shadow-md hover:border-primary/30 transition-all group">
                    <span class="w-12 h-12 rounded-xl grid place-items-center flex-shrink-0 bg-green-50 text-green-600"><i class="bi bi-cash-stack text-xl"></i></span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-bold text-gray-900">{{ __('personal.event_show_finance') }}</span>
                        <span class="block text-xs text-muted-foreground mt-1" x-text="money(profit)"></span>
                    </span>
                    <i class="bi bi-chevron-right rtl:rotate-180 text-gray-300 group-hover:text-primary transition-colors"></i>
                </button>
            @endif
        </div>
    @endif

    {{-- ===== Hall screens — only for a type that drives any ===== --}}
    @if($canManage && ! empty($screens))
        <div class="max-w-md">
            <x-court-screens :event="$e['key']"
                             :mats="$screens['mats'] ?? []"
                             :screens="$screens['screens'] ?? []"
                             :surfaces="$screenSurfaces ?? []"
                             :new-url="$screenNewUrl ?? null"
                             :color="$mgColor" />
        </div>
    @endif

    {{-- ===== What the screens play — same condition as the screens themselves,
              since audio with nothing to play it on is a setting nobody can
              hear. ===== --}}
    @if($canManage && ! empty($screens))
        <div class="max-w-md">
            <x-event-screen-audio :event="$e['key']"
                                  :media="$screenAudio ?? []"
                                  :audio-url="$screenAudioUrls ?? []"
                                  :color="$mgColor" />
        </div>
    @endif

    @if($canManage)
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Documents --}}
            <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h2 class="text-sm font-bold text-gray-900 flex items-center gap-2 mb-4">
                    <i class="bi bi-paperclip text-primary"></i>{{ __('personal.event_manage_documents') }}
                </h2>
                <x-event-documents :event="$e['key']" :documents="$documents ?? []" :can-manage="true" :color="$mgColor" />
            </div>

            {{-- The event itself, and ending it --}}
            <div class="space-y-4">
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-2">
                    <h2 class="text-sm font-bold text-gray-900 flex items-center gap-2 mb-3">
                        <i class="bi bi-gear text-primary"></i>{{ __('personal.event_manage_the_event') }}
                    </h2>
                    <button type="button" @click="goEdit()"
                            class="w-full text-start flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-muted transition-colors">
                        <i class="bi bi-pencil text-primary"></i>
                        <span class="text-sm font-medium text-gray-900">{{ __('personal.event_show_edit_event') }}</span>
                    </button>

                    {{-- Package actions come from the event type, so a new type
                         brings its own without editing this page. --}}
                    @foreach($actions as $action)
                        <form method="POST" action="{{ route('me.events.action', [$e['key'], $action['action']]) }}">
                            @csrf
                            <button type="submit"
                                    class="w-full text-start flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-muted transition-colors">
                                <i class="{{ \App\Support\Icon::bi($action['icon'] ?? null, 'text-primary', 'bi-lightning-charge') }}"></i>
                                <span class="text-sm font-medium text-gray-900">{{ $action['label'] }}</span>
                            </button>
                        </form>
                    @endforeach
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-red-100 p-6 space-y-2">
                    <h2 class="text-sm font-bold text-red-700 flex items-center gap-2 mb-3">
                        <i class="bi bi-exclamation-triangle"></i>{{ __('personal.event_manage_danger') }}
                    </h2>
                    <button type="button" x-show="! cancelled" @click="cancelEvent()"
                            class="w-full text-start flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-amber-50 transition-colors">
                        <i class="bi bi-slash-circle text-amber-600"></i>
                        <span class="min-w-0">
                            <span class="block text-sm font-medium text-amber-700">{{ __('personal.event_show_cancel_event') }}</span>
                            <span class="block text-xs text-amber-600/80">{{ __('personal.event_manage_cancel_sub') }}</span>
                        </span>
                    </button>
                    <button type="button" @click="deleteEvent()"
                            class="w-full text-start flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-red-50 transition-colors">
                        <i class="bi bi-trash text-red-600"></i>
                        <span class="min-w-0">
                            <span class="block text-sm font-medium text-red-700">{{ __('personal.event_show_delete_event') }}</span>
                            <span class="block text-xs text-red-500/80">{{ __('personal.event_manage_delete_sub') }}</span>
                        </span>
                    </button>
                </div>
            </div>
        </div>

        {{-- The two sheets, exactly as the public page used to carry them --}}
        @include('partials.event-results-sheet')
        @include('partials.event-finance-sheet')
    @endif
    </div>{{-- /cards --}}
</div>
@endsection
