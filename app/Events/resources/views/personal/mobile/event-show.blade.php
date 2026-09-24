{{-- `$shell` is shared ONLY on the sealed event routes (/e/{uuid}/admin/…), so with
     nothing shared this is the member shell exactly as before. See entry/shell. --}}
@extends($shell ?? 'members::layouts.personal-mobile')

@section('title', $e['title'])

{{--
    Event detail — mobile. DUMMY content driven by PersonalMobileController@eventShow.
    Stylish, animated single-event page: gradient cover, going/like actions,
    capacity, agenda timeline, attendees, location, share. Reuses the shared
    mobile motion vocabulary (m-hero, m-card, m-press, m-bar-fill, m-float) and
    design tokens. Wire the action buttons to real endpoints when ready.
--}}
@section('personal-content')
@php
    /* An event can now be priced entirely out of OPTIONS, with no base fee at
       all ("Gi 15 / No-Gi 15"). Reading the base line alone then says "free"
       and the money sections of this page disappear, so the payload's own
       option list has a say too. */
    $pPaid   = (!str_contains(strtolower($e['participant_fee']), 'free') && !str_contains(strtolower($e['participant_fee']), 'qualified')) || ! empty($e['fees']['options']);
    $byQual  = str_contains(strtolower($e['participant_fee']), 'qualified');
    $hasTicket = !empty($e['spectator']);
    $ticketPaid = ($hasTicket && !str_contains(strtolower($e['spectator']['fee']), 'free')) || ! empty($e['fees']['spectator_options']);

    // Why competing is not on offer — the exact sentence the server produced, so
    // the apology dialog gives the real reason rather than a generic refusal.
    // Defined up here because partials.event-show-script (included below) reads it.
    /* Banned by a person, or taken off by the deadline — either way the
       server already wrote the exact sentence, and the apology dialog says it
       rather than a generic refusal. */
    $whyNot = (($banned ?? false) || ($excludedAt ?? null))
        ? ($eligReason ?? __('personal.event_show_removed_default'))
        : (($e['ended'] ?? false)
            ? __('personal.event_show_ended_msg')
            : ($byQual
                ? __('personal.event_show_entry_by_qualification')
                : ($eligReason ?? __('personal.event_show_not_eligible_default'))));
@endphp
<div @include('events::partials.event-show-script')
     class="-mx-4 -mt-4 pb-4">

    {{-- ===== The entry cover =====
         The same full-screen cover a shared link opens onto — the artwork, the
         name, the day, the hall — tapped away to reveal the page. One partial,
         shared with `/e/{uuid}`.

         Only on the BRANDED surface: it is a cover for an event that is its own
         app, and over a page still wearing the platform's top bar and bottom
         tabs it would be a full-screen overlay inside somebody else's chrome.
         `isset($shell)` is the "am I chrome-less" question (never `$sealed`,
         which is about ADDRESSES).

         FAIL-SAFE, and the property to preserve: the partial is `x-cloak`ed and
         teleported, so it paints only once Alpine is running. If Alpine fails,
         the cover never appears and the reader gets the page directly — the
         failure mode is "no cover", never "no page". --}}
    @if(isset($shell))
        @include('events::entry.public.partials.cover')
    @endif

    {{-- ===== The band =====
         The SAME `<x-event-poster-band>` the public poster wears — one copy,
         so an event has one face whichever door was used to reach it (asked for
         2026-09-08). It replaced this page's own `m-hero` band, which was
         Design Rule #6's standard header; the poster band is the documented
         exception to that rule, and the point of this change is that the member
         page IS the poster now.

         Back sits FIRST in the trailing cluster rather than on the leading
         edge, because the band's leading edge carries the dash and the
         classification — exactly as the poster's own reopen-cover arrow does.
         Same band, same shape, different business in the cluster. --}}
            @php
                /* Inside the sealed event app the root is the POSTER — the page
                   that was actually shared. On the platform it is the member's
                   events list, as before. */
                /* `key` is this payload's public identifier — the same one every
                   other route() call on this page uses. There is no `uuid` key. */
                /* ⚠️ `$sealed`, not `isset($shell)`. Both surfaces are dressed
                   in the event's skin now, but only the MIRRORED one lives under
                   /e/{uuid} — where the poster is guaranteed to exist, because
                   SealEventPage will not serve a page without it. On the member
                   address the poster may not exist at all (the organiser's
                   public switch is off), and sending Back there would leave a
                   chrome-less page whose only way out is a 404. */
                $backHref = ($sealed ?? false) ? url('/e/'.$e['key']) : route('me.events');
                $backLabel = ($sealed ?? false) ? __('events::events.public_enrol_back_event') : __('personal.event_show_events');
            @endphp
    @php
        /* The classification, the way the poster says it: "JIU JITSU
           CHAMPIONSHIP". An event type usually names its sport already, so the
           sport is only prefixed when the type has not said it. Same rule, same
           words, as entry/public/mobile. */
        /* The package's own name where there is one — see the controller. */
        $eyebrow = \App\Events\Support\EventClassification::line(
            $e['sport_label'] ?? null,
            $e['type_package'] ?? $e['type'] ?? null,
        );

        /* The chips the poster does not derive from the eyebrow. Type and sport
           are IN the eyebrow now, so repeating them as chips would say the same
           thing twice. */
        $mchips = [];
        if (($e['scope'] ?? 'internal') !== 'internal' && ! empty($e['scope_label'])) {
            $mchips[] = ['bi-broadcast', $e['scope_label']];
        }
        if ($pPaid || $byQual)  $mchips[] = ['bi-cash-coin', __('personal.event_show_paid_entry')];
        if ($ticketPaid)        $mchips[] = ['bi-ticket-perforated', __('personal.event_show_ticketed')];
    @endphp
    @if(isset($shell))
        {{-- BRANDED: the same `<x-event-poster-band>` the public poster wears —
             one copy, so an event has one face whichever door was used to reach
             it (asked for 2026-09-08). Back sits first in the trailing cluster
             rather than on the leading edge, because the band's leading edge
             carries the dash and the classification — exactly as the poster's
             own reopen-cover arrow does. --}}
        <x-event-poster-band :color="$e['color']" :eyebrow="$eyebrow"
                             :title="$e['title']" :owner="$e['club']" :chips="$mchips">
            {{-- BACK, at the head of the classification line, where the dash
                 was (asked for 2026-09-14).

                 It is where the poster puts its Home control, and where Design
                 Rule #6 puts back on every other page of the platform: the
                 leading edge is back, the trailing edge is actions. The dash it
                 replaces was decoration — a reader arriving on a chrome-less
                 event page had their one way out tucked into the cluster of
                 round controls opposite, reading as just another action. --}}
            <x-slot:lead>
                <a href="{{ $backHref }}" class="m-press ev-ctl flex-none"
                   @if(! ($sealed ?? false)) data-shell-link data-route="me.events" @endif
                   aria-label="{{ $backLabel }}" title="{{ $backLabel }}">
                    {{-- `rtl:rotate-180` is not decoration: a chevron that keeps
                         pointing left in Arabic points AWAY from where it goes. --}}
                    <i class="bi bi-chevron-left rtl:rotate-180"></i>
                </a>
            </x-slot:lead>

            <x-slot:controls>
                {{-- ...and therefore NOT in the trailing cluster any more: one
                     back per page, on the leading edge. --}}
                @include('events::partials.event-band-controls', ['leading' => 'none'])
            </x-slot:controls>

            <x-slot:banner>
                <div x-show="cancelled" x-cloak class="relative z-10 mt-4 -mb-2 rounded-xl bg-white/20 backdrop-blur px-3 py-2 text-xs font-bold flex items-center gap-2">
                    <i class="bi bi-exclamation-triangle-fill"></i> {{ __('personal.event_show_cancelled_banner') }}
                </div>
            </x-slot:banner>
        </x-event-poster-band>
    @else
        {{-- EVERY OTHER TYPE: the standard Design Rule #6 hero band, unchanged.
             Sparring, Open Mat and the generic bucket were never part of the
             poster decision and must not be redesigned by it. --}}
        <header class="m-hero px-5 pt-5 pb-16 text-white relative overflow-hidden"
                style="background: linear-gradient(150deg, {{ $e['color'] }}, {{ $e['color'] }}b0);">
            <div class="absolute -right-10 -top-10 w-44 h-44 rounded-full bg-white/10"></div>
            <div class="absolute right-6 bottom-8 w-24 h-24 rounded-full bg-white/10"></div>

            {{-- z-50 so the manage dropdown paints above the title block below --}}
            <div class="flex items-center justify-between relative z-50">
                @include('events::partials.event-band-controls')
            </div>

            <div x-show="cancelled" x-cloak class="relative z-10 mt-4 -mb-2 rounded-xl bg-white/20 backdrop-blur px-3 py-2 text-xs font-bold flex items-center gap-2">
                <i class="bi bi-exclamation-triangle-fill"></i> {{ __('personal.event_show_cancelled_banner') }}
            </div>

            <div class="relative z-10 mt-6">
                <div class="flex items-center gap-1.5 flex-wrap">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur">
                        <i class="bi {{ $e['icon'] }}"></i> {{ $e['type'] }}
                    </span>
                    @if(!empty($e['sport_label']))
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur"><i class="bi {{ $e['sport_icon'] ?? 'bi-dribbble' }}"></i> {{ $e['sport_label'] }}</span>
                    @endif
                    @foreach($mchips as [$ic, $label])
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur"><i class="bi {{ $ic }}"></i> {{ $label }}</span>
                    @endforeach
                </div>
                <h1 class="text-2xl font-black mt-3 leading-tight">{{ $e['title'] }}</h1>
                <p class="text-sm text-white/85 mt-1.5 flex items-center gap-1.5">
                    <i class="bi bi-building"></i>{{ $e['club'] }}
                </p>
            </div>
        </header>
    @endif

    {{-- ===== Quick facts card (overlaps cover) ===== --}}
    <div class="px-4 -mt-10 relative z-10">
        <div class="bg-white rounded-3xl shadow-lg border border-gray-100 p-4">
            {{-- Quick facts are not just a summary — each one is the door to its
                 own fuller answer further down the page. When / how much / where,
                 and a tap takes you to the run-of-show, the way in, or the map.
                 The chip does the linking work the reader would otherwise do by
                 scrolling — which on a phone is most of the page. --}}
            <div class="grid grid-cols-3 gap-2 text-center">
                {{-- When: the day AND the time it starts. Lands on the
                     run-of-show row for the event's own date. --}}
                <button type="button" @click="jump(['run-start', 'how-it-runs'])"
                        class="m-press rounded-xl -m-1 p-1"
                        aria-label="{{ __('personal.event_show_how_it_runs') }}">
                    <div class="w-10 h-10 mx-auto rounded-xl bg-accent text-primary grid place-items-center"><i class="bi bi-calendar3"></i></div>
                    <p class="text-xs font-bold text-foreground mt-1.5 truncate">{{ $e['wday'] }} {{ $e['day'] }} {{ $e['mon'] }}</p>
                    <p class="text-[10px] text-muted-foreground truncate">{{ $e['time'] }}</p>
                </button>
                {{-- How much: lands on the Participate row and makes it announce
                     itself — "what does it cost" and "how do I get in" are the
                     same question asked twice. --}}
                <button type="button" @click="jump('join-participate')"
                        class="m-press border-x border-gray-100 py-1"
                        aria-label="{{ $byQual ? __('personal.event_show_entry') : __('personal.event_show_to_join') }}">
                    <div class="w-10 h-10 mx-auto rounded-xl bg-accent text-primary grid place-items-center"><i class="bi bi-cash-coin"></i></div>
                    <p class="text-xs font-bold text-foreground mt-1.5 truncate">{{ $e['participant_fee'] }}</p>
                    <p class="text-[10px] text-muted-foreground truncate">{{ $byQual ? __('personal.event_show_entry') : __('personal.event_show_to_join') }}</p>
                </button>
                {{-- Where: lands on the map. A button like its two neighbours —
                     one inert cell in a row of three tappable ones reads as
                     broken, not as restraint. --}}
                <button type="button" @click="jump('where')"
                        class="m-press min-w-0 rounded-xl -m-1 p-1"
                        aria-label="{{ __('personal.event_show_location') }}">
                    <div class="w-10 h-10 mx-auto rounded-xl bg-accent text-primary grid place-items-center"><i class="bi bi-geo-alt"></i></div>
                    <p class="text-xs font-bold text-foreground mt-1.5 truncate" title="{{ $e['location'] }}">{{ $e['location'] }}</p>
                    <p class="text-[10px] text-muted-foreground">{{ __('personal.event_show_venue') }}</p>
                </button>
            </div>

            {{-- Capacity — only when the organiser actually set one. With no
                 limit there are no "spots left" to count, and the bar would read
                 100% full for an event anyone can still join. --}}
            @if($e['capped'] ?? false)
                <div class="mt-5">
                    <div class="flex items-center justify-between text-[11px] mb-1.5">
                        <span class="font-semibold text-foreground"><span x-text="goingCount">{{ $e['going'] }}</span> {{ __('personal.event_show_going') }}</span>
                        <span class="text-muted-foreground"><span x-text="Math.max(0, cap - goingCount)">{{ max(0, $e['cap'] - $e['going']) }}</span> {{ __('personal.event_show_spots_left') }}</span>
                    </div>
                    <div class="h-2 rounded-full bg-muted overflow-hidden">
                        <div class="m-bar-fill h-full rounded-full" :style="`width:${pct}%; background:{{ $e['color'] }}`" style="width: {{ round($e['going'] / $e['cap'] * 100) }}%"></div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- `?category=` is the bracket page's own parameter (the public page's is
         `?division=`); each host names its own door. The member page links
         whatever the draw's state, because that page says WHEN it opens when it
         is not out — which is the answer, not a dead end. --}}
    @include('events::partials.event-detail-card-mobile', [
        'divisionLink' => route('me.events.bracket.manage', $e['key']).'?division=',
    ])

    {{-- ===== The doors =====
         The same partial, the same order and the same tiles the PUBLIC poster
         renders (`partials.event-doors`) — this page and `/e/{uuid}` show one
         event to two audiences and must not look like two products.

         Only the destinations differ: these lead to the member's own screens.
         What used to sit INLINE here — the entry list, the winners, a league's
         whole table — is behind its own door now, which is what kept the way
         into the event a screen and a half below the fold. --}}
    @php
        $doors = [];

        if (! empty($e['categories'])) {
            // Entrants, not divisions: "how many are in the draw" is the number
            // an organiser is checking, and a division count reads as full when
            // nobody has entered a single one of them.
            $athleteTotal = collect($e['categories'])->sum('joined');
            $doors['draw'] = [
                'href' => route('me.events.bracket.manage', $e['key']),
                'shell' => true,
                'icon' => 'bi-diagram-3-fill bracket-icon',
                'label' => __('personal.event_show_tile_draw'),
                'sub' => $athleteTotal.' '.__('personal.event_show_entrants'),
            ];
        }

        /* The classes that are not drawn — a pool's table, or a league's whole
           table. Same door the poster carries, in the same place, from the same
           source (EventType::publicView); it opens the board, which is where a
           member reads a table. */
        if ((($typePublic ?? [])['key'] ?? null) === 'table') {
            $doors['table'] = [
                'href' => route('me.events.bracket.manage', $e['key']),
                'shell' => true,
                'icon' => $typePublic['icon'] ?? 'bi-table',
                'label' => $typePublic['label'] ?? '',
                'sub' => $typePublic['sub'] ?? '',
            ];
        }

        $doors['officials'] = [
            'href' => route('me.events.officiating', $e['key']),
            'shell' => true,
            'icon' => 'bi-person-badge-fill',
            'label' => __('personal.event_show_tile_officials'),
            'sub' => trans_choice('personal.event_manage_officials_count', $e['officials_count'] ?? 0, ['count' => $e['officials_count'] ?? 0]),
        ];

        $doors['gallery'] = [
            'href' => route('me.events.gallery', $e['key']),
            'shell' => true,
            'icon' => 'bi-camera-reels-fill',
            'label' => __('events::events.bout_gallery_title'),
            'sub' => ($e['clips_count'] ?? 0) > 0
                ? trans_choice('events::events.bout_gallery_count', $e['clips_count'], ['count' => $e['clips_count']])
                : __('events::events.bout_gallery_none'),
        ];

        /* ===== Participants =====
           The door goes to the event's OWN entry list — `/e/{uuid}/participants`
           — not to the organiser's roster (asked for on 2026-09-08). They are
           two pages for two jobs: a reader wants to know WHO IS IN, which is a
           published fact; the roster is organiser work — moderation, payment
           state, contact details — and stays a row of the CONSOLE.

           Falls back to the member roster when there is no public page to send
           anybody to: `/e/{uuid}/participants` refuses everybody while
           `entry_mode` is `members`, and a tile that lands on a refusal is a
           dead end (Navigation Integrity). */
        $peopleHref = ! empty($publicUrl ?? null)
            ? route('events.public.section', ['event' => $e['key'], 'section' => 'participants', 'from' => 'me'])
            : route('me.events.people', $e['key']);

        $doors['participants'] = [
            'href' => $peopleHref,
            // `data-shell-link` only while the destination is still a platform
            // page; the public entry list is its own sealed surface.
            'shell' => empty($publicUrl ?? null),
            'icon' => 'bi-people-fill',
            'label' => $byQual ? __('personal.event_show_finalists') : __('personal.event_show_tile_participants'),
            'sub' => '<span x-text="peopleCount">'.e($e['participants_people'] ?? $e['participants_total'] ?? $e['going']).'</span> '.e(__('personal.event_show_in'))
                .($hasTicket ? ' · <span x-text="spectators">'.e($e['spectator']['count']).'</span> '.e(__('personal.event_show_spectators')) : ''),
        ];

        /* Results — the last thing to happen at an event, and the only door
           that opens a sheet on this page rather than a page of its own. */
        if (! empty($e['bracket_results'])) {
            $doors['results'] = [
                'click' => 'showResultsOpen = true',
                'icon' => 'bi-trophy-fill',
                // The same words the poster uses — one door, one name.
                'label' => __('personal.event_show_results_medals'),
                'sub' => trans_choice('personal.event_show_results_divisions', count($e['bracket_results']), ['count' => count($e['bracket_results'])]),
                'style' => 'background: linear-gradient(135deg, #eab308, #78350f);',
            ];
        } elseif (! empty($e['results'])) {
            // An event whose winners the organiser typed rather than fought for
            // (a race, a belt test). Same door, same sheet.
            $doors['results'] = [
                'click' => 'showResultsOpen = true',
                'icon' => 'bi-trophy-fill',
                'label' => __('personal.event_show_winners'),
                'sub' => trans_choice('events::events.public_draw_competitors', count($e['results']), ['n' => count($e['results'])]),
                'style' => 'background: linear-gradient(135deg, #eab308, #78350f);',
            ];
        }
    @endphp

    @include('events::partials.event-doors')

    {{-- ===== Agenda timeline ===== --}}
    @if(!empty($e['agenda']))
    <div class="px-4 mt-4">
        <div class="m-card rounded-2xl p-4">
            <h2 class="text-sm font-bold text-foreground flex items-center gap-2"><i class="bi bi-list-check text-primary"></i> {{ __('personal.event_show_schedule') }}</h2>
            <div class="mt-3 space-y-0">
                @foreach($e['agenda'] as $i => $a)
                    <div class="flex gap-3">
                        <div class="flex flex-col items-center">
                            <span class="w-3 h-3 rounded-full" style="background: {{ $e['color'] }};"></span>
                            @if(!$loop->last)<span class="w-0.5 flex-1 bg-gray-100 my-1"></span>@endif
                        </div>
                        <div class="pb-4 -mt-1">
                            @php $at = !empty($a['t']) ? rescue(fn () => \Carbon\Carbon::parse($a['t']), null, false) : null; @endphp
                            <p class="text-xs font-bold text-foreground">{{ $at ? $at->format('M j · g:i A') : $a['t'] }}</p>
                            <p class="text-xs text-muted-foreground">{{ $a['d'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    {{-- ===== Proof of payment =====
         The join / spectate CTAs that used to sit here are gone: the two pricing
         rows above ARE the way in now, and this card only repeated them — plus an
         "you're not eligible" notice that the participant row's own dialog now
         gives on tap. What stays is the proof-of-payment flow, which has nowhere
         else to live. --}}
    {{-- REMOVED 2026-09-02 at the user's request: the "Competing for" card
         (partials.event-representing) and the "Enroll your athletes" button and
         sheet (partials.event-squad-entry). Neither is included here any more.

         The club choice is NOT lost — event-representing-cards still renders
         inside the join sheet, which an already-registered athlete reaches
         through the participant row (startJoin lets a holder back in). The two
         partials are left on disk, uncalled, so restoring either is one line.

         What is included below is teleported markup only, no visible card:
         the proof-of-payment sheet (opened by openProof() from finishJoin) and
         the join sheet (opened by startJoin() from the participant and
         spectator rows). Both are load-bearing for those buttons. --}}
    <div class="px-4 mt-4">
        @include('events::partials.event-payment-proof')
        @include('events::partials.event-join-sheet')
    </div>

    {{-- In-place division confirmation (taekwondo) --}}
    <div x-show="joinedDivision" x-cloak class="px-4 mt-2">
        <div class="m-card rounded-2xl p-3 flex items-center gap-2 text-[12px]">
            <i class="bi bi-diagram-3 bracket-icon text-primary"></i>
            <span class="text-muted-foreground">{{ __('personal.event_show_placed_in') }} <span class="font-bold text-foreground" x-text="joinedDivision"></span></span>
        </div>
    </div>

    {{-- Share handler.
         Shares the URL the server decided on ($shareUrl): the PUBLIC page when
         the organiser has published one, the member page otherwise. It used to
         share a title and a sentence with no url at all, and its "copied"
         fallback copied nothing — so the only link anybody could actually pass
         on was the member page, which asks a stranger to log in. --}}
    <div @share-event.window="
            const url = @js($shareUrl ?? url()->current());
            if (navigator.share) {
                navigator.share({ title: @js($e['title']), text: @js(__('personal.event_show_share_text', ['title' => $e['title']])), url }).catch(() => {});
                return;
            }
            navigator.clipboard?.writeText(url).then(
                () => window.showToast('success', @js(__('personal.event_show_link_copied'))),
                () => window.showToast('info', url),
            );
         "></div>

    {{-- The join/spectate buttons sit LAST on purpose: someone scrolls the
         event to decide, and the decision belongs at the end of that read
         rather than interrupting it halfway down. --}}
    {{-- ===== Join / spectate =====
         Deliberately NOT in a card. Each button is already a self-contained
         object with its own surface and shadow; wrapping them in a card put a
         frame around a frame, and its "Entry & tickets" heading only said what
         the two buttons say more clearly themselves. --}}
    <div class="px-4 mt-4">

        {{-- Each row IS the way in: the row already names the thing, its
             price and who it is for, so a separate CTA underneath only
             repeated it. Rows that cannot be taken (ineligible, already
             registered, blocked, event over) stay on screen but disabled, so
             the price list still reads as a price list.

             A row shows a tick and turns green once you hold that place. --}}
        @php
            $locked = ($banned ?? false) || ($excludedAt ?? null) !== null || ($e['ended'] ?? false);
            // $whyNot is set at the top of this view — the script partial needs it.
            $canJoin = ! $locked && ($canCompete ?? true) && ! $byQual;
        @endphp

{{-- ===== Taken off at the deadline =====
     Not a ban and not a refusal to enter: their place existed and was
     released. Said on arrival rather than behind a "Why not?" tap, because
     somebody who expected to compete this morning should not have to go
     looking for the reason. --}}
@if($excludedAt ?? null)
    <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 flex items-start gap-3 mb-4">
        <span class="w-10 h-10 rounded-xl bg-amber-100 text-amber-700 grid place-items-center flex-shrink-0">
            <i class="bi bi-clock-history text-lg"></i>
        </span>
        <div class="min-w-0 flex-1">
            <p class="text-sm font-bold text-amber-900">{{ __('personal.event_show_deadline_removed') }}</p>
            <p class="text-[12px] text-amber-800 mt-1 leading-snug">{{ $eligReason }}</p>
            <p class="text-[11px] text-amber-700/80 mt-1.5">{{ \App\Support\Cldr::shortDate($excludedAt) }}</p>
        </div>
    </div>
@endif

        {{-- ===== Join / spectate =====
             Same shape as the "Brackets & draws" card further down the page:
             a dark gradient, a soft circle bleeding off the corner, a
             translucent icon tile and a chevron. Those already read as
             pressable here, so the two ways INTO the event use the same
             language rather than inventing a second one.

             Green once you hold that place. The ineligible variant keeps the
             shape but goes slate, so it still invites a tap ("Why not?")
             without pretending to be the main action. --}}

        {{-- Participant — also the amount chip's landing point. No
             --m-attn-color: the row carries an Alpine :style binding and a
             second static style on the same element is asking for one to
             clobber the other, so the pulse uses the brand primary. --}}

        <button type="button" id="join-participate"
                x-show="entriesOpen || registered" x-cloak
                @click="{{ $canJoin ? "startJoin('participant')" : 'explainIneligible()' }}"
                :disabled="registered && !feeDue"
                class="m-press mt-3 w-full block rounded-2xl p-4 text-white text-start relative overflow-hidden
                       shadow-lg transition-all active:scale-[.98] disabled:cursor-not-allowed"
                :style="(going && !feeDue)
                    ? 'background: linear-gradient(135deg, #16a34a, #1f2937)'
                    : going
                    ? 'background: linear-gradient(135deg, #d97706, #1f2937)'
                    : 'background: linear-gradient(135deg, {{ $canJoin ? $e['color'] : '#64748b' }}, #1f2937)'">
            <div class="absolute -right-6 -top-6 w-28 h-28 rounded-full bg-white/10"></div>
            <div class="relative flex items-center gap-3">
                <div class="w-12 h-12 rounded-2xl bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0">
                    <i class="bi text-2xl" :class="going ? 'bi-check2-circle' : '{{ $canJoin ? 'bi-person-check' : 'bi-info-circle' }}'"></i>
                </div>
                <div class="min-w-0 flex-1">
                    <h3 class="font-black text-base leading-tight"
                        x-text="!going ? '{{ $byQual ? __('personal.event_show_take_part') : __('personal.event_show_join_participant') }}' : (feeDue ? '{{ __('personal.event_show_place_held_fee_due') }}' : '{{ __('personal.event_show_youre_participant') }}')">{{ $byQual ? __('personal.event_show_take_part') : __('personal.event_show_join_participant') }}</h3>
                    <p class="text-xs text-white/85 mt-0.5">
                        @if(!($canCompete ?? true)) {{ __('personal.event_show_not_eligible_spectators') }}
                        @elseif($byQual) {{ __('personal.event_show_reserved_finalists') }}
                        @elseif($pPaid) {{ __('personal.event_show_fee_paid_club') }}
                        @else {{ __('personal.event_show_free_members') }} @endif
                    </p>
                </div>
                <span class="text-sm font-black flex-shrink-0"
                      x-text="!going ? '{{ $canJoin ? $e['participant_fee'] : __('personal.event_show_cta_why') }}' : (feeDue ? '{{ __('personal.event_show_cta_fee_due') }}' : '{{ __('personal.event_show_cta_joined') }}')">{{ $canJoin ? $e['participant_fee'] : __('personal.event_show_cta_why') }}</span>
                <i class="bi bi-chevron-right text-white/80 flex-shrink-0"></i>
            </div>
        </button>

        {{-- Spectator ticket --}}
        @if($hasTicket)
            <button type="button" @click="startJoin('spectator')"
                    :disabled="(registered && !feeDue) || {{ $locked ? 'true' : 'false' }}"
                    class="m-press mt-2.5 w-full block rounded-2xl p-4 text-white text-start relative overflow-hidden
                           shadow-lg transition-all active:scale-[.98]
                           disabled:cursor-not-allowed disabled:opacity-60"
                    :style="(watching && !feeDue)
                        ? 'background: linear-gradient(135deg, #16a34a, #1f2937)'
                        : watching
                        ? 'background: linear-gradient(135deg, #d97706, #1f2937)'
                        : 'background: linear-gradient(135deg, {{ $ticketPaid ? '#7c3aed' : '#0284c7' }}, #1f2937)'">
                <div class="absolute -right-6 -top-6 w-28 h-28 rounded-full bg-white/10"></div>
                <div class="relative flex items-center gap-3">
                    <div class="w-12 h-12 rounded-2xl bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0">
                        <i class="bi text-2xl" :class="watching ? 'bi-check2-circle' : 'bi-ticket-perforated'"></i>
                    </div>
                    <div class="min-w-0 flex-1">
                        <h3 class="font-black text-base leading-tight"
                            x-text="!watching ? '{{ __('personal.event_show_spectator_ticket') }}' : (feeDue ? '{{ __('personal.event_show_place_held_fee_due') }}' : '{{ __('personal.event_show_ticket_booked') }}')">{{ __('personal.event_show_spectator_ticket') }}</h3>
                        <p class="text-xs text-white/85 mt-0.5"><span x-text="spectators">{{ $e['spectator']['count'] }}</span> {{ __('personal.event_show_watching') }}{{ $ticketPaid ? ' · '.__('personal.event_show_entry_watch_matches') : ' · '.__('personal.event_show_free_to_watch') }}</p>
                    </div>
                    <span class="text-sm font-black flex-shrink-0"
                          x-text="!watching ? '{{ $e['spectator']['fee'] }}' : (feeDue ? '{{ __('personal.event_show_cta_fee_due') }}' : '{{ __('personal.event_show_cta_booked') }}')">{{ $e['spectator']['fee'] }}</span>
                    <i class="bi bi-chevron-right text-white/80 flex-shrink-0"></i>
                </div>
            </button>
        @endif
    </div>


    {{-- ===== Results sheet (combat — computed from brackets) ===== --}}
    @if(!empty($e['bracket_results']))
        <template x-teleport="body">
        <div x-show="showResultsOpen" x-cloak class="fixed inset-0 z-[60]" style="display:none;">
            <div class="absolute inset-0 bg-black/40" @click="showResultsOpen=false" x-transition.opacity></div>
            <div class="absolute bottom-0 inset-x-0 bg-white rounded-t-3xl max-h-[88vh] flex flex-col"
                 x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0">
                <div class="flex-shrink-0 px-5 pt-3 pb-4 rounded-t-3xl text-white relative overflow-hidden"
                     style="background: linear-gradient(150deg, #b45309, #d97706b0);">
                    <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                    <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3"></div>
                    <div class="relative flex items-start gap-3">
                        <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0">
                            <i class="bi bi-trophy-fill text-xl"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-lg font-black leading-tight">{{ __('personal.event_show_results_medals') }}</h3>
                            <p class="text-[12px] text-white/85 mt-0.5 truncate">{{ $e['title'] }}</p>
                        </div>
                        <button type="button" @click="showResultsOpen=false" aria-label="{{ __('shared.close') }}"
                                class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>
                <div class="flex-1 overflow-y-auto p-4 space-y-3">
                    @foreach($e['bracket_results'] as $r)
                        <div class="rounded-2xl border border-gray-100 p-3">
                            <p class="text-sm font-bold text-foreground mb-2 flex items-center gap-2"><i class="bi bi-diagram-3 bracket-icon text-primary"></i> {{ $r['division'] }}</p>
                            <div class="space-y-1.5">
                                @foreach($r['medals'] as $m)
                                    @php
                                        /* Fourth is a PLACING, not a medal — it carries the
                                           numeral rather than a fourth colour of disc, and a
                                           place this sheet does not know falls back to its own
                                           number rather than fataling on a missing key. */
                                        /* The DRAWING is `x-medal-icon`; the tint
                                           of the row and the word beside the name
                                           are what is decided here. */
                                        $medal = [
                                            1 => ['#f59e0b', __('personal.event_show_champion')],
                                            2 => ['#9ca3af', __('personal.event_show_runner_up')],
                                            3 => ['#b45309', __('personal.event_show_third_place')],
                                            4 => ['#64748b', __('personal.event_show_fourth_place')],
                                        ][$m['place']] ?? ['#64748b', '#'.$m['place']];
                                    @endphp
                                    <div class="flex items-center gap-3 rounded-xl p-2" style="background: {{ $medal[0] }}12;">
                                        <x-medal-icon :place="$m['place']" :size="26" :label="$medal[1]" />
                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm font-bold text-foreground truncate">{{ $m['name'] }}</p>
                                            <p class="text-[11px] text-muted-foreground">{{ $medal[1] }}</p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
        </template>
    @endif


</div>
@endsection
