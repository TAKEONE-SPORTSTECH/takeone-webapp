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
    /* An event can now be priced entirely out of OPTIONS, with no base fee at
       all ("Gi 15 / No-Gi 15"). Reading the base line alone then says "free"
       and the money sections of this page disappear, so the payload's own
       option list has a say too. */
    $pPaid   = (! str_contains(strtolower($e['participant_fee'] ?? ''), 'free') && ! str_contains(strtolower($e['participant_fee'] ?? ''), 'qualified')) || ! empty($e['fees']['options']);
    $byQual  = str_contains(strtolower($e['participant_fee'] ?? ''), 'qualified');
    $hasTicket = ! empty($e['spectator']);
    $ticketPaid = ($hasTicket && ! str_contains(strtolower($e['spectator']['fee'] ?? ''), 'free')) || ! empty($e['fees']['spectator_options']);
    $banned = false;                 // an organiser is never barred from their own console
    $eligReason = null;
    $whyNot = null;

    $cards = [];

    /* No verification tile: the desk's sheet opens from a person's card on
       "Who's joined" now, and the separate desk screen is gone with it. */

    if ($canManage) {
        // Who is on the list. The console had no door to the roster at all:
        // the Entries card opens the ENTERING flow, and reading down the
        // entrants — which is where an organiser strikes names off — was
        // reachable only from the public event page.
        $cards[] = [
            'icon' => 'bi-people-fill', 'tone' => 'bg-amber-50 text-amber-600',
            'label' => __('personal.event_show_whos_joined'),
            'sub' => trans_choice('personal.event_manage_entrants', $counts['entrants'], ['count' => $counts['entrants']]),
            'href' => route('testcode.me.events.people', $e['key']),
        ];
        /* The clubs standing behind the competition — its own screen, because
           it is its own job: who is COMPETING is settled before the draw, and
           it is answered once at the start rather than glanced at while
           reading the console. */
        $cards[] = [
            'icon' => 'bi-shield-fill-check', 'tone' => 'bg-indigo-50 text-indigo-600',
            'label' => __('eventlab::messages.clubs_title'),
            'sub' => __('eventlab::messages.clubs_tile_sub'),
            'href' => route('testcode.me.events.clubs', $e['key']),
        ];
        /* ===== "Enter your athletes" — HIDDEN until it exists =====
         *
         * This tile is not another door to the roster. It is for a CLUB OWNER
         * who was invited by the organiser: they sign in, see their own club's
         * athletes, and enter a batch of them into the competition in one go.
         * That screen has not been built, and the tile had been quietly
         * repointed at the entry list, which is a different job — so it
         * promised something the tap could not deliver.
         *
         * Hidden on 2026-09-03 at the user's request ("for now this is not
         * needed, we will work on it later"). The strings it used
         * (`personal.event_manage_entries*`) are still in the language files,
         * waiting for the real screen.
         */
        $cards[] = [
            'icon' => 'bi-diagram-3-fill', 'tone' => 'bg-purple-50 text-purple-600',
            'label' => __('personal.event_manage_draw'),
            'sub' => $started ? __('personal.event_manage_draw_locked') : __('personal.event_manage_draw_sub'),
            'href' => route('testcode.me.events.bracket.manage', $e['key']),
        ];
        $cards[] = [
            'icon' => 'bi-person-badge-fill', 'tone' => 'bg-teal-50 text-teal-600',
            'label' => __('personal.event_manage_officials'),
            'sub' => trans_choice('personal.event_manage_officials_count', $counts['officials'], ['count' => $counts['officials']]),
            // The officiating sheet, not the JSON endpoint of the same name —
            // that one answers the appoint picker and rendered as raw JSON here.
            'href' => route('testcode.me.events.officiating', $e['key']),
        ];
        $cards[] = [
            'icon' => 'bi-tv-fill', 'tone' => 'bg-slate-100 text-slate-600',
            'label' => __('personal.event_manage_board'),
            'sub' => __('personal.event_manage_board_sub'),
            'href' => route('testcode.me.events.board', $e['key']),
        ];
    }

    // The scoring table. Gated on canScore rather than canManage — an appointed
    // official scores a mat they cannot otherwise manage — and rendered only for
    // a sport that HAS a mat, so a type with no scoreboard shows no card.
    // Deliberately last: it is the run-day surface, and it opens a full-screen
    // console rather than a page inside the shell.
    if (! empty($scoringUrl)) {
        $cards[] = [
            'icon' => 'bi-stopwatch-fill', 'tone' => 'bg-rose-50 text-rose-600',
            'label' => __('personal.event_manage_scoring'),
            'sub' => __('personal.event_manage_scoring_sub'),
            'href' => $scoringUrl,
        ];
    }
@endphp

<div class="px-4 sm:px-6 lg:px-8 py-6" @include('eventlab::partials.event-show-script')>

    {{-- ===== Header =====
         The page-header pattern (see CLAUDE.md → Page Headers): full-bleed
         m-hero band, event colour to colour+b0, two soft circles, a control row
         on top, then chips · title · owner beneath. --}}
    <header class="m-hero -mx-4 sm:-mx-6 lg:-mx-8 -mt-6 px-4 sm:px-6 lg:px-8 pt-6 pb-20 text-white relative overflow-hidden"
            style="background: linear-gradient(150deg, {{ $mgColor }}, {{ $mgColor }}b0);">
        <div class="absolute -right-10 -top-10 w-44 h-44 rounded-full bg-white/10"></div>
        <div class="absolute right-6 bottom-8 w-24 h-24 rounded-full bg-white/10"></div>

        <div class="flex items-center justify-between relative z-50">
            {{-- Back is a LABELLED pill, never a bare arrow (Design Rule #6). --}}
            <a href="{{ route('testcode.me.events.show', $e['key']) }}"
               class="inline-flex items-center w-10 h-10 justify-center rounded-full bg-white/15 border border-white/25 backdrop-blur text-white text-sm font-semibold hover:bg-white/25 transition-colors"
           aria-label="{{ __('personal.event_show_event') }}" title="{{ __('personal.event_show_event') }}">
                <i class="bi bi-chevron-left"></i>
            </a>
            <a href="{{ route('testcode.me.events.show', $e['key']) }}"
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
            <h1 class="text-2xl font-black mt-3 leading-tight">{{ $e['title'] }}</h1>
            <p class="text-sm text-white/85 mt-1.5 flex items-center gap-1.5">
                <i class="bi bi-calendar-event"></i>{{ trim(($e['wday'] ?? '').' '.($e['day'] ?? '').' '.($e['mon'] ?? '')) }}
            </p>
        </div>
    </header>

    {{-- The cards ride up over the band's tail, as on the event page. --}}
    <div class="-mt-12 relative z-10 space-y-6">

    {{-- ===== Preparations — the component brings its own button and dialog ===== --}}
    @if($canOfficiate)
        <x-eventlab::event-checklist :event="$e['key']"
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
                    <i class="bi bi-chevron-right text-gray-300 group-hover:text-primary transition-colors"></i>
                </a>
            @endforeach

            @if($canManage)
                {{-- Winners. The tile is one door onto two sheets, and the
                     TYPE decides which — see the mobile console's note. --}}
                <button type="button" @click="openResults()"
                        class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 flex items-start gap-4 text-start hover:shadow-md hover:border-primary/30 transition-all group">
                    <span class="w-12 h-12 rounded-xl grid place-items-center flex-shrink-0 bg-amber-50 text-amber-600"><i class="bi bi-trophy-fill text-xl"></i></span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-bold text-gray-900">{{ __('personal.event_podium_title') }}</span>
                        @if($manual_results ?? true)
                            <span class="block text-xs text-muted-foreground mt-1" x-text="results.length ? @js(__('personal.event_manage_results_recorded')) : @js(__('personal.event_manage_results_sub'))"></span>
                        @else
                            <span class="block text-xs text-muted-foreground mt-1">
                                {{ count($e['divisions'] ?? [])
                                    ? __('personal.event_podium_decided', ['done' => count($e['bracket_results'] ?? []), 'total' => count($e['divisions'])])
                                    : __('personal.event_podium_sub') }}
                            </span>
                        @endif
                    </span>
                    <i class="bi bi-chevron-right text-gray-300 group-hover:text-primary transition-colors"></i>
                </button>

                <button type="button" @click="financeOpen=true"
                        class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 flex items-start gap-4 text-start hover:shadow-md hover:border-primary/30 transition-all group">
                    <span class="w-12 h-12 rounded-xl grid place-items-center flex-shrink-0 bg-green-50 text-green-600"><i class="bi bi-cash-stack text-xl"></i></span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-bold text-gray-900">{{ __('personal.event_show_finance') }}</span>
                        <span class="block text-xs text-muted-foreground mt-1" x-text="money(profit)"></span>
                    </span>
                    <i class="bi bi-chevron-right text-gray-300 group-hover:text-primary transition-colors"></i>
                </button>
            @endif
        </div>
    @endif

    {{-- ===== The hall's wiring: this event's screens, and the cameras on its
              mats. Screens exist only for a type that drives them; cameras work
              for any event with mats, so the panel opens for either. ===== --}}


    @if($canManage && (! empty($screens) || ! empty($cameras)))
        <div class="max-w-md">
            <x-eventlab::court-screens :event="$e['key']"
                             :mats="$screens['mats'] ?? ($cameras['mats'] ?? [])"
                             :screens="$screens['screens'] ?? []"
                             :surfaces="$screenSurfaces ?? []"
                             :new-url="$screenNewUrl ?? null"
                             :cameras="$cameras['cameras'] ?? []"
                             :camera-max="$cameras['max'] ?? 4"
                             :color="$mgColor" />

            {{-- What those screens PLAY. Renders nothing on the page — only the
                 sheet the panel's gear opens. --}}
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
                <x-eventlab::event-documents :event="$e['key']" :documents="$documents ?? []" :can-manage="true" :color="$mgColor" />
            </div>

            {{-- The event itself, and ending it --}}
            <div class="space-y-4">
                {{-- The page anybody may open. Its own card, because publishing
                     is a decision of a different kind from editing a field —
                     and the same standalone component the mobile console uses. --}}
                <x-eventlab::event-public-link :event="$e['key']" :is-public="$isPublic ?? false"
                                     :url="$publicUrl" :color="$mgColor ?? $e['color']" :title="$e['title']" :auto-accept="$autoAccept ?? false" />

                {{-- The picture that page opens onto. Beside the switch on purpose:
                     there is no point choosing a face for a page nobody may open. --}}
                <x-eventlab::event-cover :event="$e['key']" :photo="$coverPhoto ?? null"
                               :color="$mgColor" :title="$e['title']" />

                {{-- Strangers waiting to be let in. Renders nothing when nobody
                     is (EVENTS-PUBLIC-ENTRY.md, Phase C). --}}
                <x-eventlab::event-entry-review :event="$e['key']" :entries="$publicEntries ?? []"
                                      :color="$mgColor ?? $e['color']" :title="$e['title']" />

                {{-- When the draw stops being the organiser's working copy and
                     becomes the sheet on the wall. Only for a type that draws
                     one — an event with no divisions has nothing to reveal. --}}
                @if(! empty($e['categories']))
                    <x-eventlab::event-draw-visibility :event="$e['key']" :reveal="$drawReveal ?? 'always'"
                                             :date="$drawRevealDate ?? null"
                                             :color="$mgColor ?? $e['color']" :title="$e['title']" />
                @endif

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
                    {{-- The draw's verbs live on the draw screen (see the mobile console). --}}
                @foreach(collect($actions)->reject(fn ($a) => str_contains($a['action'] ?? '', 'draw')) as $action)
                        <form method="POST" action="{{ route('testcode.me.events.action', [$e['key'], $action['action']]) }}">
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
        @include(($manual_results ?? true) ? 'partials.event-results-sheet' : 'eventlab::partials.event-podium-sheet')
        @include('partials.event-finance-sheet')
    @endif
    </div>{{-- /cards --}}
</div>
@endsection
