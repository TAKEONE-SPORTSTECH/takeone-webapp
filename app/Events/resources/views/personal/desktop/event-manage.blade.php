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

    /* ===== "Who's joined" — for the OFFICIALS too =====
       Everybody who may open this console gets this row, not only whoever
       manages the event. The note above says the verification desk's sheet now
       opens from a person's card here — and the row that says so was inside the
       organiser's block, so a weigh-in or payments official arrived at the
       console and found no door to the one screen their job lives on. They
       could still reach it by typing the URL, which is not a door.

       Safe to widen: the page authorises per viewer (people() re-checks, and
       the gates are attached only for the role that signs them), so linking it
       discloses nothing an official could not already read there. Fixed
       2026-09-15; the tests that caught it are in EventConsoleTest. */
    $cards[] = [
        'icon' => 'bi-people-fill', 'tone' => 'bg-amber-50 text-amber-600',
        'label' => __('personal.event_show_whos_joined'),
        'sub' => trans_choice('personal.event_manage_entrants', $counts['entrants'], ['count' => $counts['entrants']]),
        'href' => route('me.events.people', $e['key']),
    ];

    if ($canManage) {
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
        /* The weight classes, and then the draw cut from them — adjacent in
           the same grid, because they are one job (asked for 2026-09-06; the
           mobile console groups them the same way). This tile used to be a
           lone row further down the page, nowhere near the draw. */
        $cards[] = [
            'icon' => 'bi-diagram-3', 'tone' => 'bg-accent text-primary',
            'label' => __('events::events.divisions_page_title'),
            'sub' => __('events::events.divisions_console_sub'),
            'href' => route('me.events.divisions', $e['key']),
        ];
        $cards[] = [
            'icon' => 'bi-diagram-3-fill', 'tone' => 'bg-purple-50 text-purple-600',
            'label' => __('personal.event_manage_draw'),
            'sub' => $started ? __('personal.event_manage_draw_locked') : __('personal.event_manage_draw_sub'),
            'href' => route('me.events.bracket.manage', $e['key']),
        ];
        /* Officials are NOT a grid card any more — they live at the bottom of
           "The event itself" panel, mirroring the mobile console (moved
           2026-09-06 at the user's request): appointing who runs the event is
           a property of the event, not one of the run-day tiles. */
        $cards[] = [
            'icon' => 'bi-tv-fill', 'tone' => 'bg-slate-100 text-slate-600',
            'label' => __('personal.event_manage_board'),
            'sub' => __('personal.event_manage_board_sub'),
            'href' => route('me.events.board', $e['key']),
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

<div class="px-4 sm:px-6 lg:px-8 py-6" @include('events::partials.event-show-script')>

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
            <a href="{{ route('me.events.show', $e['key']) }}"
               class="inline-flex items-center w-10 h-10 justify-center rounded-full bg-white/15 border border-white/25 backdrop-blur text-white text-sm font-semibold hover:bg-white/25 transition-colors"
           aria-label="{{ __('personal.event_show_event') }}" title="{{ __('personal.event_show_event') }}">
                <i class="bi bi-chevron-left"></i>
            </a>
            {{-- The public page, not the organiser's own — see the note on the
                 mobile console. Shown only when a poster exists, and opened
                 beside the console rather than instead of it. --}}
            @if(($isPublic ?? false) && ! empty($publicUrl))
                <a href="{{ $publicUrl }}" target="_blank" rel="noopener"
                   class="h-10 px-4 rounded-full bg-white/15 border border-white/25 backdrop-blur inline-flex items-center gap-2 text-xs font-bold hover:bg-white/25 transition-colors">
                    <i class="bi bi-eye"></i>{{ __('personal.event_manage_view_public') }}
                </a>
            @endif
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

    @if($canManage)
        {{-- ===== Adding a participant, first =====
             The console's first control, because on the morning of an event it
             is the one an organiser reaches for over and over: somebody turns
             up who is not on the list. It sat below the screens and the hall
             wiring, which is a scroll away from where the work is (moved
             2026-09-12).

             Organiser (and the platform team) only — every other door into the entry list belongs to
             somebody else: the athlete, their coach, or a stranger following
             the public link. Same component as the mobile console. --}}
        <div class="mb-6">
            {{-- ⚠️ ORGANISER ONLY — the comment above said so and nothing
                 enforced it, so an appointed official was shown a control
                 whose every endpoint would refuse them (entry-search and
                 entries both assert canManage). A button that 403s is worse
                 than no button. Added 2026-09-15. --}}
            @if($canManage)
            <x-event-add-person :event="$e['key']"
                                :fees="$e['fees'] ?? []"
                                :clubs="$entryClubs ?? []"
                                :color="$mgColor"
                                :title="$e['title']" />
            @endif
        </div>

        {{-- Clubs, immediately under it: the two doors an organiser uses on the
             morning are "somebody turned up" and "a club turned up", and they
             belong together. It used to sit below the hall wiring, which is
             also the one way in to that page on desktop (moved 2026-09-12). --}}
        <div class="mb-6">
            <a href="{{ route('me.events.clubs', $e['key']) }}" data-shell-link
               class="mb-6 bg-white rounded-2xl border border-gray-100 shadow-sm p-4 flex items-center gap-3 no-underline hover:border-primary/40 transition-colors">
                <span class="w-11 h-11 rounded-2xl grid place-items-center flex-shrink-0 bg-accent text-primary"><i class="bi bi-buildings-fill text-lg"></i></span>
                <span class="min-w-0 flex-1">
                    <span class="block text-sm font-bold text-foreground truncate">{{ __('events::events.clubs_add_tile') }}</span>
                    <span class="block text-[11px] text-muted-foreground mt-0.5 truncate">{{ __('events::events.clubs_sub') }}</span>
                </span>
                <i class="bi bi-chevron-right text-muted-foreground/50 text-xs flex-shrink-0 rtl:rotate-180"></i>
            </a>
        </div>
    @endif

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
                        <span class="block text-xs text-muted-foreground mt-1 truncate">{{ $card['sub'] }}</span>
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
                            <span class="block text-xs text-muted-foreground mt-1 truncate" x-text="results.length ? @js(__('personal.event_manage_results_recorded')) : @js(__('personal.event_manage_results_sub'))"></span>
                        @else
                            <span class="block text-xs text-muted-foreground mt-1 truncate">
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
                        <span class="block text-xs text-muted-foreground mt-1 truncate" x-text="money(profit)"></span>
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
            <x-court-screens :event="$e['key']"
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

    @endif

    {{-- An official who scores but does not manage still runs the day from the
         running order (the page allows them) — mobile shows them this row. --}}
    @if(! $canManage && ! empty($scoringUrl))
        <div class="mb-6 max-w-xl">
            <a href="{{ route('me.events.order.page', $e['key']) }}" data-shell-link
               class="block bg-white rounded-xl shadow-sm border border-gray-100 p-4 hover:shadow-md transition-shadow">
                <div class="flex items-center gap-3">
                    <span class="w-11 h-11 rounded-xl grid place-items-center flex-shrink-0"
                          style="background: {{ $mgColor }}14; color: {{ $mgColor }};"><i class="bi bi-list-ol text-lg"></i></span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-bold text-gray-900 truncate">{{ __('events::events.order_title') }}</p>
                        <p class="text-[12px] text-muted-foreground mt-0.5 truncate">{{ __('events::events.order_console_sub') }}</p>
                    </div>
                    <i class="bi bi-chevron-right rtl:rotate-180 text-muted-foreground/50 text-xs flex-shrink-0"></i>
                </div>
            </a>
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
                {{-- The page anybody may open. Its own card, because publishing
                     is a decision of a different kind from editing a field —
                     and the same standalone component the mobile console uses. --}}
                <x-event-public-link :event="$e['key']" :is-public="$isPublic ?? false"
                                     :url="$publicUrl" :color="$mgColor ?? $e['color']" :title="$e['title']" :auto-accept="$autoAccept ?? false" />

                {{-- Listed or hidden: whether the event appears in any list. --}}
                <x-event-visibility :event="$e['key']" :visibility="$visibility ?? 'listed'"
                                    :url="route('me.events.show', $e['key'])" :color="$mgColor ?? $e['color']" :title="$e['title']" />

                {{-- Club payments: the proofs clubs sent, approved or rejected here. --}}
                @if($clubPayments ?? false)
                    <x-event-club-payments :event="$e['key']" :waiting="$clubPaymentsWaiting ?? 0" :color="$mgColor ?? $e['color']" :title="$e['title']" />
                @endif

                {{-- The picture that page opens onto. Beside the switch on purpose:
                     there is no point choosing a face for a page nobody may open. --}}
                <x-event-cover :event="$e['key']" :photo="$coverPhoto ?? null"
                               :color="$mgColor" :title="$e['title']" />

                {{-- Strangers waiting to be let in. Renders nothing when nobody
                     is (EVENTS-PUBLIC-ENTRY.md, Phase C). --}}
                <x-event-entry-review :event="$e['key']" :entries="$publicEntries ?? []"
                                      :color="$mgColor ?? $e['color']" :title="$e['title']" />

                {{-- Anybody in this competition twice? Every likely pair among the
                     entrants, to settle before the draw is built. Renders nothing
                     when there are none (EventDuplicates). --}}
                <x-event-duplicate-check :event="$e['key']" :color="$mgColor ?? $e['color']" :title="$e['title']" />

                {{-- Who is barred, and why — the moderation list, which had no
                     screen at all between 2026-09-03 and 2026-09-15: the desk
                     that carried it was removed and its tab went with it, so
                     `me.events.ban.lift` was an endpoint with no door. Beside
                     the review queue on purpose: who is waiting to be let in,
                     then who may not come in at all. --}}
                <x-event-bans :event="$e['key']" :count="count($e['bans_list'] ?? [])"
                              :color="$mgColor" :title="$e['title']"
                              :club="$e['club'] ?? null" />

                {{-- The running order: ONE screen with two views — the day part
                     by part (where a ceremony or a prayer break gets its place,
                     and the one board that can say an athlete is in two parts
                     running), and the bouts on each mat. They were two boards on
                     two addresses, and this console row was the only door to the
                     first of them — the mat board had none at all on desktop. --}}
                <a href="{{ route('me.events.order.page', $e['key']) }}" data-shell-link
                   class="block bg-white rounded-xl shadow-sm border border-gray-100 p-4 hover:shadow-md transition-shadow">
                    <div class="flex items-center gap-3">
                        <span class="w-11 h-11 rounded-xl grid place-items-center flex-shrink-0"
                              style="background: {{ $mgColor }}14; color: {{ $mgColor }};"><i class="bi bi-list-ol text-lg"></i></span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-bold text-gray-900 truncate">{{ __('events::events.order_title') }}</p>
                            <p class="text-[12px] text-muted-foreground mt-0.5 truncate">{{ __('events::events.order_console_sub') }}</p>
                        </div>
                        <i class="bi bi-chevron-right rtl:rotate-180 text-muted-foreground/50 text-xs flex-shrink-0"></i>
                    </div>
                </a>

                {{-- The rule book: category system + belt system (CategorySystems). --}}
                @php $rbEvent = \App\Events\Models\ClubEvent::where('uuid', $e['key'])->first(); @endphp
                @if($rbEvent && app(\App\Sports\Combat\SportRegistry::class)->has($rbEvent->sport))
                    <x-event-rule-book :event="$rbEvent" :color="$mgColor ?? $e['color']" />
                @endif

                {{-- When the draw stops being the organiser's working copy and
                     becomes the sheet on the wall. Only for a type that draws
                     one — an event with no divisions has nothing to reveal. --}}
                @if(! empty($e['categories']))
                    <x-event-draw-visibility :event="$e['key']" :reveal="$drawReveal ?? 'always'"
                                             :date="$drawRevealDate ?? null"
                                             :color="$mgColor ?? $e['color']" :title="$e['title']" />
                @endif

                {{-- How athletes enter: individuals, clubs, or both (EntryChannels). --}}
                @if($entryChannelsEditable ?? false)
                    <x-event-entry-channels :event="$e['key']" :channels="$entryChannels ?? ['individual', 'club']"
                                            :color="$mgColor ?? $e['color']" :title="$e['title']" />
                @endif

                {{-- Certificates for the people who competed: participation and
                     podium, drafted from the finished draw, approved here
                     (App\Certificates). Only for a type with divisions, and only
                     while the feature is switched on. --}}
                @if(config('certificates.enabled') && ! empty($e['categories']))
                    <x-certificates-console context="club_event" :subject="$e['key']" :color="$mgColor ?? $e['color']" :title="$e['title']" />
                @endif

                {{-- Paid featuring: the spotlight card on everyone's /me/events
                     (App\Events\Support\EventFeaturing). The component reads its
                     own state; the endpoint decides who may ask. --}}
                <x-event-featuring :event="$e['key']" :color="$mgColor ?? $e['color']" :title="$e['title']" />

                {{-- Every language this event is read in, and the organiser's
                     power to correct any of it. It appears here rather than in
                     the edit form because a translation is not a field of the
                     event — it is a copy of the whole thing, and it arrives on
                     its own when a visitor asks for it. --}}
                <x-event-languages :event="$e['key']" :color="$mgColor" :title="$e['title']"
                                   :source-locale="$sourceLocale ?? null" />


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
                        <form method="POST" action="{{ route('me.events.action', [$e['key'], $action['action']]) }}"
                              data-event-action data-failed="{{ __('events::events.action_failed') }}">
                            @csrf
                            <button type="submit"
                                    class="w-full text-start flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-muted transition-colors">
                                <i class="{{ \App\Support\Icon::bi($action['icon'] ?? null, 'text-primary', 'bi-lightning-charge') }}"></i>
                                <span class="text-sm font-medium text-gray-900">{{ $action['label'] }}</span>
                            </button>
                        </form>
                    @endforeach

                    {{-- Who runs it, last in the panel. Same place the mobile
                         console puts it. --}}
                    <a href="{{ route('me.events.officiating', $e['key']) }}" data-shell-link
                       class="w-full text-start flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-muted transition-colors no-underline">
                        <i class="bi bi-person-badge text-primary"></i>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-medium text-gray-900 truncate">{{ __('personal.event_manage_officials') }}</span>
                            <span class="block text-xs text-muted-foreground truncate">{{ trans_choice('personal.event_manage_officials_count', $counts['officials'], ['count' => $counts['officials']]) }}</span>
                        </span>
                        <i class="bi bi-chevron-right text-muted-foreground/50 text-xs flex-shrink-0 rtl:rotate-180"></i>
                    </a>
                </div>

                {{-- The dress rehearsal, above the danger zone: it is the
                     opposite of destructive and must not read as part of it. --}}
                @include('events::partials.event-rehearsal-card')

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
        @include(($manual_results ?? true) ? 'events::partials.event-results-sheet' : 'events::partials.event-podium-sheet')
        @include('events::partials.event-finance-sheet')
    @endif

    {{-- The gate in front of Cancel and Delete. Inside the x-data above (it
         reads `danger`), outside the owner-only block (the two buttons it
         guards are not owner-only), and teleported to <body> from there. --}}
    @include('events::partials.event-danger-sheet')
    </div>{{-- /cards --}}
</div>
@include('events::partials.event-action-runner')
@endsection
