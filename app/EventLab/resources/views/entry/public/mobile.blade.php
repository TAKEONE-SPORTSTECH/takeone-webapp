@extends('eventlab::entry.layout')

{{--
    The public event page — MOBILE.

    The SAME page as `/me/events/{uuid}` on a phone, down to the classes: the
    m-hero cover band, the quick-facts card riding up over it, the shared detail
    card (`partials.event-detail-card-mobile`, included by both) with its dark
    full-bleed section bands, and the four sections beneath it in the same white
    cards. Restyled to this on 2026-09-02 at the user's request — the page had
    its own lighter design and now wears the product's.

    What is missing is the app around it — no top bar, no drawer, no bottom tabs
    — and the things a stranger has no business reading: no medal table, no money
    owed by anybody, no roster of anyone's contact details. The DRAW, the
    OFFICIALS, the GALLERY and the ENTRY LIST are here deliberately: all four are
    published by a competition already.

    That is not enforced here. Everything on this page comes from
    App\Events\Support\PublicEvent, which is the one place that decides what a
    stranger may know; a section whose data it refuses to produce says so and
    nothing more. This file must never reach for a column of its own.
--}}

{{-- The ground is white, so the phone's status bar matches it. --}}
@section('theme-color', '#ffffff')

@push('styles')
<style>
    /* The event page wears the product's design, so it sits on the product's
       GROUND too — WHITE, like every other public page here (asked for on
       2026-09-04: the cover page must not be black).

       The tokens are redefined rather than overridden on <body>, because
       entry.layout sets `--pg`/`--ink` inline and an inline style beats any
       class. `body`, not `:root`: this block is pushed into the <head>, while
       entry/partials/skin-style is included in the BODY and therefore comes
       LATER in the document — a `:root` here would lose the tie to the skin's
       own `:root`. A declaration on `body` overrides what body would inherit
       from html whatever the source order.

       `--on-pg` / `--on-pg-line` are deliberately NOT set: they fall back to
       the skin's light values, so the ground type and the shared footer read
       correctly without either knowing where they are. */
    body {
        --pg:  #ffffff;
        --ink: var(--color-foreground);
    }
</style>
@endpush

@section('body')
@php
    use App\Support\Palette;

    $pPaid      = (bool) ($e['fee_is_paid'] ?? false);
    $hasTicket  = ! empty($e['spectator']);
    $ticketPaid = $hasTicket && ! str_contains(mb_strtolower($e['spectator']['fee']), 'free');

    /* The event's own colour, guarded before it lands in a style attribute, and
       the one soft tint the sections use for an icon plate. */
    $ev = Palette::safe($e['color']);
    $evSoft = Palette::alpha($ev, .09);

    /* The band takes the event's colour DOWN towards navy rather than
       lightening it, so white type sits on it at full contrast. Both shades are
       mixed in PHP by Palette, because the design's `color-mix(in oklab, …)` is
       dropped whole by an Android WebView older than Chrome 111 — which would
       leave this header with no background at all. */
    $evDeep = Palette::shade($ev, 82);
    $evFade = Palette::shade($ev, 38);

    /* The classification, the way the design says it: "JIU JITSU CHAMPIONSHIP".
       An event type usually names its sport already, so the sport is only
       prefixed when the type has not said it. */
    $type    = trim($e['type'] ?? '');
    $sport   = trim($e['sport_label'] ?? '');
    $eyebrow = ($sport && ! Str::contains(Str::lower($type), Str::lower($sport)))
        ? trim($sport.' '.$type)
        : $type;

    /* A flag-icons class for a 2-letter code, or null. Same helper the members'
       roster uses; the sheet is loaded by entry.layout. */
    $flagClass = function ($code) {
        $c = strtolower(substr(preg_replace('/[^a-zA-Z]/', '', (string) $code), 0, 2));

        return strlen($c) === 2 ? 'fi fi-'.$c : null;
    };

    /* Seconds as a clock, for a gallery tile's corner. */
    $clock = function ($seconds) {
        $s = max(0, (int) $seconds);

        return $s >= 3600
            ? sprintf('%d:%02d:%02d', intdiv($s, 3600), intdiv($s % 3600, 60), $s % 60)
            : sprintf('%d:%02d', intdiv($s, 60), $s % 60);
    };
@endphp
{{-- ===== The entry cover — tapped away to reveal the page below ===== --}}
@include('eventlab::entry.public.partials.cover')

{{-- The invitation to install — the only route to a page with no address
     bar on iOS, and the tidiest one on Android. Dismissed once, never asked
     again on that device. --}}
@include('eventlab::entry.public.partials.install-prompt')

<div x-data="publicEvent()" class="-mx-4 -mt-4 pb-4">

    {{-- ===== The band =====
         The design file's header (drafts/upper header.png), kept verbatim at
         the user's request: the event's colour taken DOWN towards navy, a dash
         and the classification in tracked caps, then the big title, then whose
         it is, then the three facts that decide whether to read on.

         This is the ONE page that departs from Design Rule #6's hero band, and
         deliberately — it is a poster a stranger was sent, not a screen inside
         the app. Every other page, the four section pages included, keeps the
         standard band. --}}
    <header class="relative overflow-hidden text-white"
            style="padding: 22px 24px 72px; background: {{ \App\Support\Palette::eventBand($e['color']) }};">

        <div class="absolute rounded-full" style="right:-56px; top:-56px; width:190px; height:190px; background:rgba(255,255,255,.07);"></div>
        <div class="absolute rounded-full" style="right:22px; bottom:26px; width:96px; height:96px; background:rgba(255,255,255,.06);"></div>

        {{-- Control row. No back pill: a stranger arrived from a link, and there
             is nothing behind this page to go back TO. --}}
        <div class="relative flex items-center justify-between">
            <span class="flex items-center" style="gap:10px;">
                <span class="flex-none" style="width:38px; height:3px; border-radius:2px; background:rgba(255,255,255,.85);"></span>
                @if($eyebrow)
                    <span class="uppercase" style="font-size:11px; font-weight:600; letter-spacing:.2em; color:rgba(255,255,255,.85);">{{ $eyebrow }}</span>
                @endif
            </span>

            {{-- 12px between the three controls. It went 8 → 12 → 18 and 18 was
                 too far: at 40px round each they stopped reading as one cluster
                 of controls belonging to this header and started looking like
                 three loose buttons. 12 separates them without scattering them. --}}
            <span class="flex items-center flex-none" style="gap:12px;">
                {{-- Back to the poster. The cover is dismissed once per tab, which
                     left no way to see the artwork again without opening a new
                     tab; this is it. Dispatched on `window` because the cover is
                     its own Alpine root, teleported to <body>.

                     FIRST in the row, and a back ARROW: it is the one control
                     here that goes BACKWARDS, and back always sits on the
                     leading edge. `rtl:rotate-180` because an arrow is
                     direction, not decoration. --}}
                <button type="button" @click="window.dispatchEvent(new CustomEvent('reopen-cover'))"
                        aria-label="{{ __('events.public_cover_reopen') }}"
                        title="{{ __('events.public_cover_reopen') }}"
                        class="m-press ev-ico grid place-items-center flex-none"
                        style="width:40px; height:40px; border-radius:50%; border:1px solid rgba(255,255,255,.3); background:rgba(255,255,255,.14); font-size:15px;">
                    <i class="bi bi-chevron-left"></i>
                </button>

                {{-- The gear: the way IN for whoever is running this.
                     A link, not a button, and offered to every reader —
                     showing it only to organisers would tell a stranger who
                     the organisers are. It lands on the event's own sign-in
                     (PublicEventController@manage), so the person running
                     the competition never has to leave it to sign in — and
                     STRAIGHT to the console when they already run it, so the
                     sign-in page never enters the history stack for them. That
                     page no longer redirects either; the two together are what
                     un-trapped the Back button. --}}
                <a href="{{ $console ?? route('testcode.e.manage', $e['key']) }}"
                   aria-label="{{ __('events.public_manage_title') }}"
                   title="{{ __('events.public_manage_title') }}"
                   class="m-press ev-ico grid place-items-center flex-none"
                   style="width:40px; height:40px; border-radius:50%; border:1px solid rgba(255,255,255,.3); background:rgba(255,255,255,.14); font-size:15px;">
                    <i class="bi bi-gear"></i>
                </a>

                <button type="button" @click="share()" aria-label="{{ __('events.public_share') }}"
                        class="m-press ev-ico grid place-items-center flex-none"
                        style="width:40px; height:40px; border-radius:50%; border:1px solid rgba(255,255,255,.3); background:rgba(255,255,255,.14); font-size:15px;">
                    <i class="bi bi-share"></i>
                </button>

                {{-- Account + sign-out. See the component for why it must be in
                     the HEADER: the gear beside it takes a manager straight to
                     the console, so they never see the sign-in page where this
                     first lived. --}}
                <x-eventlab::event-account :event="$e['key']" :color="$e['color']"
                    style="width:40px; height:40px; border-radius:50%; border:1px solid rgba(255,255,255,.3); background:rgba(255,255,255,.14); font-size:15px;" />
            </span>
        </div>

        <h1 class="relative" style="margin:26px 0 0; font-size:27px; line-height:1.18; font-weight:700; letter-spacing:-.01em;">{{ $e['title'] }}</h1>

        @if($e['club'])
            <p class="relative flex items-center" style="margin:10px 0 0; gap:8px; font-size:13px; color:rgba(255,255,255,.82);">
                <i class="bi bi-building"></i>{{ $e['club'] }}
            </p>
        @endif

        {{-- The three poster facts. A head COUNT is one of them; WHO is entered
             is the Participants door further down. --}}
        <div class="relative flex flex-wrap" style="gap:6px; margin-top:14px;">
            @php
                $hchips = [];
                if ($pPaid)      $hchips[] = ['bi-cash-coin', __('personal.event_show_paid_entry')];
                if ($ticketPaid) $hchips[] = ['bi-ticket-perforated', __('personal.event_show_ticketed')];
                if ($e['capped'] ?? false) $hchips[] = ['bi-people', trans_choice('events.public_spots', max(0, $e['cap'] - $e['going']), ['n' => max(0, $e['cap'] - $e['going'])])];
            @endphp
            @foreach($hchips as [$ic, $label])
                <span class="inline-flex items-center uppercase"
                      style="gap:6px; padding:5px 11px; border-radius:999px; font-size:10px; font-weight:600; letter-spacing:.08em; background:rgba(255,255,255,.16); border:1px solid rgba(255,255,255,.22);">
                    <i class="bi {{ $ic }}"></i>{{ $label }}
                </span>
            @endforeach
        </div>
    </header>

    {{-- ===== The three facts, riding up over the band =====
         The design file's card, kept verbatim: a white panel with a hairline of
         the event's colour along its top edge, three columns divided by rules,
         and — when the organiser set a capacity — how full it is. No icon
         tiles: the design puts the bare glyph in the event's colour, which
         keeps the row about the VALUES.

         Each fact is still a door to its fuller answer further down: when → the
         run-of-show, how much → the way in, where → the map. --}}
    <div class="relative" style="padding:0 18px; margin-top:-46px;">
        <div class="m-in"
             style="background:#fff; border-radius:18px; border-top:3px solid {{ $ev }};
                    box-shadow:0 22px 60px rgba(30,44,79,.13), 0 2px 6px rgba(30,44,79,.06);
                    padding:18px 16px 16px;">

            <div class="grid text-center" style="grid-template-columns:1fr 1fr 1fr;">
                <button type="button" @click="jump(['run-start','how-it-runs'])"
                        class="m-press" style="border-right:1px solid #eef1f6; padding:2px 6px;"
                        aria-label="{{ __('personal.event_show_how_it_runs') }}">
                    <i class="bi bi-calendar3" style="font-size:17px; color:{{ $ev }};"></i>
                    <p style="margin:7px 0 0; font-size:12.5px; font-weight:700; color:#1e2c4f;">{{ $e['wday'] }} {{ $e['day'] }} {{ $e['mon'] }}</p>
                    <p style="margin:2px 0 0; font-size:10.5px; color:#6b7689;">{{ $e['time'] }}</p>
                </button>

                <button type="button" @click="jump('enter')"
                        class="m-press" style="border-right:1px solid #eef1f6; padding:2px 6px;"
                        aria-label="{{ __('personal.event_show_to_join') }}">
                    <i class="bi bi-cash-coin" style="font-size:17px; color:{{ $ev }};"></i>
                    <p style="margin:7px 0 0; font-size:12.5px; font-weight:700; color:#1e2c4f;">{{ $e['participant_fee'] }}</p>
                    <p style="margin:2px 0 0; font-size:10.5px; color:#6b7689;">{{ __('personal.event_show_to_join') }}</p>
                </button>

                <button type="button" @click="jump('where')"
                        class="m-press min-w-0" style="padding:2px 6px;"
                        aria-label="{{ __('personal.event_show_location') }}">
                    <i class="bi bi-geo-alt" style="font-size:17px; color:{{ $ev }};"></i>
                    <p class="truncate" style="margin:7px 0 0; font-size:12.5px; font-weight:700; color:#1e2c4f;" title="{{ $e['location'] }}">{{ $e['location'] }}</p>
                    <p style="margin:2px 0 0; font-size:10.5px; color:#6b7689;">{{ __('personal.event_show_venue') }}</p>
                </button>
            </div>

            @if($e['capped'] ?? false)
                <div style="margin-top:16px;">
                    <div class="flex justify-between" style="font-size:11px; margin-bottom:6px;">
                        <span style="font-weight:600; color:#1e2c4f;">{{ $e['going'] }} {{ __('personal.event_show_going') }}</span>
                        <span style="color:#6b7689;">{{ max(0, $e['cap'] - $e['going']) }} {{ __('personal.event_show_spots_left') }}</span>
                    </div>
                    <div style="height:6px; border-radius:999px; background:#eef1f6; overflow:hidden;">
                        <div class="m-bar-fill" style="height:100%; border-radius:999px; background:{{ $ev }}; width:{{ min(100, round($e['going'] / max(1, $e['cap']) * 100)) }}%;"></div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- ===== The event, in one card — the SAME partial the member page uses.
         No `sectionVariant`, so it draws the product's dark full-bleed section
         bands exactly as `/me/events/{uuid}` does. ===== --}}
    @include('partials.event-detail-card-mobile', [
        // The organiser published the page, so the files they attached travel
        // with it. `PublicEvent` supplies download URLs on the public route —
        // the member route's auth stack is untouched.
        'documents' => $e['documents'] ?? [],
    ])

    {{-- ===== Draw · Officials · Gallery · Participants =====
         Four doors out of this page, the same four the member page has and in
         the same order. Each opens its own page rather than expanding here: a
         draw, an officiating sheet, a footage gallery and an entry list are
         each a screen's worth of reading, and stacked inline they pushed the
         way in a screen and a half below the fold.

         The Draw door only exists when the event HAS divisions — an event that
         runs no draw gets no door to one, which is the rule the member page's
         own Draw row follows. --}}
    @php
        $doors = [];

        if (!empty($e['divisions']) || ($e['draw']['published'] ?? false)) {
            $drawn = ($e['draw']['published'] ?? false);
            $doors[] = [
                'href' => route('testcode.e.section', ['event' => $e['key'], 'section' => 'draw']),
                'icon' => 'bi-diagram-3-fill bracket-icon',
                'label' => __('personal.event_show_tile_draw'),
                'sub' => $drawn
                    ? trans_choice('events.public_draw_competitors', $e['draw']['entrants'], ['n' => $e['draw']['entrants']])
                    : __('events.public_draw_empty_title'),
            ];
        }

        $doors[] = [
            'href' => route('testcode.e.section', ['event' => $e['key'], 'section' => 'officials']),
            'icon' => 'bi-person-badge-fill',
            'label' => __('personal.event_show_tile_officials'),
            'sub' => ($e['officials']['count'] ?? 0) > 0
                ? trans_choice('events.public_officials_count', $e['officials']['count'], ['n' => $e['officials']['count']])
                : __('events.public_officials_empty_title'),
        ];

        // Always a door, even with nothing behind it yet: an event's footage is
        // a place people go looking for, and a door that only appears once
        // something is behind it cannot be found before then. The page says so
        // itself when empty.
        $doors[] = [
            'href' => route('testcode.e.section', ['event' => $e['key'], 'section' => 'gallery']),
            'icon' => 'bi-camera-reels-fill',
            'label' => __('events.bout_gallery_title'),
            'sub' => ($e['gallery']['count'] ?? 0) > 0
                ? trans_choice('events.bout_gallery_count', $e['gallery']['count'], ['count' => $e['gallery']['count']])
                : __('events.bout_gallery_none'),
        ];

        $doors[] = [
            'href' => route('testcode.e.section', ['event' => $e['key'], 'section' => 'participants']),
            'icon' => 'bi-people-fill',
            'label' => __('personal.event_show_tile_participants'),
            'sub' => ($e['participants']['count'] ?? 0) > 0
                ? trans_choice('events.public_draw_competitors', $e['participants']['count'], ['n' => $e['participants']['count']])
                : __('events.public_participants_empty_title'),
        ];
    @endphp
    <div class="px-4 mt-4 space-y-3">
        @foreach($doors as $d)
            <a href="{{ $d['href'] }}"
               class="m-press rounded-2xl p-4 text-white relative overflow-hidden shadow-lg flex items-center gap-3.5 no-underline"
               style="background: linear-gradient(135deg, {{ $e['color'] }}, #1f2937);">
                <div class="absolute -right-6 -top-6 w-24 h-24 rounded-full bg-white/10"></div>
                {{-- inline-block via .bracket-icon: a bare <i> is an inline box
                     and CSS transforms do not apply to those, so the bracket's
                     quarter turn would silently do nothing. --}}
                <div class="relative w-12 h-12 rounded-2xl bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0">
                    <i class="bi {{ $d['icon'] }} text-2xl"></i>
                </div>
                <div class="relative min-w-0 flex-1">
                    <h3 class="font-black text-base leading-tight">{{ $d['label'] }}</h3>
                    <p class="text-xs text-white/85 mt-0.5 truncate">{{ $d['sub'] }}</p>
                </div>
                <i class="bi bi-chevron-right text-white/80 relative flex-shrink-0"></i>
            </a>
        @endforeach
    </div>

    {{-- ===== An invitation this reader can answer =====
         The notification about it links to this page, so the answer belongs on
         this page. Above the way in, because for the person it is addressed to
         it IS the way in. ===== --}}
    @if($invite ?? null)
        <div class="px-4 mt-4" x-data="clubInvite()">
            <div class="rounded-2xl p-4 text-white relative overflow-hidden shadow-lg"
                 style="background: {{ \App\Support\Palette::eventBand($e['color']) }};">
                <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>

                <div class="relative flex items-start gap-3">
                    <span class="w-12 h-12 flex-shrink-0 grid place-items-center">
                        @if($invite->tenant?->logo ?: $invite->logo)
                            <img src="{{ file_url($invite->tenant?->logo ?: $invite->logo) }}"
                                 alt="{{ $invite->name }}" class="w-full h-full object-contain">
                        @else
                            <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center">
                                <i class="bi bi-people-fill text-xl"></i>
                            </span>
                        @endif
                    </span>
                    <div class="min-w-0 flex-1">
                        <h3 class="font-black text-base leading-tight">{{ __('eventlab::messages.club_banner_title') }}</h3>
                        <p class="text-xs text-white/85 mt-0.5">
                            {{ __('eventlab::messages.club_banner_body', ['club' => $invite->tenant?->club_name ?: $invite->name]) }}
                        </p>
                    </div>
                </div>

                <div class="relative mt-4 flex items-center gap-2" x-show="!done" x-cloak>
                    <button type="button" @click="answer('accepted')" :disabled="busy"
                            class="m-press flex-1 h-11 rounded-2xl bg-white text-[13.5px] font-black inline-flex items-center justify-center gap-2"
                            style="color: {{ $e['color'] }};">
                        <i class="bi bi-check-lg"></i>{{ __('eventlab::messages.club_accept') }}
                    </button>
                    <button type="button" @click="answer('declined')" :disabled="busy"
                            class="m-press h-11 px-4 rounded-2xl bg-white/15 border border-white/25 backdrop-blur text-[13.5px] font-bold">
                        {{ __('eventlab::messages.club_decline') }}
                    </button>
                </div>

                <p class="relative mt-4 text-[12.5px] font-bold flex items-center gap-2" x-show="done" x-cloak>
                    <i class="bi bi-check-circle-fill"></i><span x-text="doneText"></span>
                </p>
            </div>
        </div>

        @push('scripts')
        <script>
        /* Inline on purpose: this banner exists only when the server rendered
           it, so there is nothing to register for a page that has none. */
        function clubInvite() {
            return {
                busy: false, done: false, doneText: '',

                async answer(a) {
                    if (this.busy) return;
                    this.busy = true;

                    try {
                        const res = await fetch(@json(route('testcode.me.events.clubs.respond', ['event' => $e['key'], 'eventClub' => $invite->uuid])), {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            credentials: 'same-origin',
                            body: JSON.stringify({ answer: a }),
                        });
                        const data = await res.json();

                        if (!res.ok || !data.success) {
                            window.showToast('error', data.message || 'That did not work.');
                            return;
                        }

                        this.doneText = data.message;
                        this.done = true;
                        window.showToast('success', data.message);
                    } catch (e) {
                        window.showToast('error', 'That did not work.');
                    } finally {
                        this.busy = false;
                    }
                },
            };
        }
        </script>
        @endpush
    @endif

    {{-- ===== The way in ===== --}}
    <span id="enter" class="block"></span>
    <div class="px-4 mt-4">
        @if(($mine ?? null))
            {{-- Already in — so the one action here is the way back to what you
                 entered, not another way to enter. The event's own colour, not
                 the maroon of the entry door: this is a place you have been,
                 not a door you have yet to open. --}}
            <a href="{{ route('testcode.e.my-entry', ['event' => $e['key']]) }}"
               class="m-press rounded-2xl p-4 text-white relative overflow-hidden shadow-lg flex items-center gap-3.5 no-underline"
               style="background: {{ \App\Support\Palette::eventBand($e['color']) }};">
                <div class="absolute -right-6 -top-6 w-24 h-24 rounded-full bg-white/10"></div>

                <div class="relative w-12 h-12 rounded-2xl bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0">
                    <i class="bi {{ $mine === 'pending' ? 'bi-hourglass-split' : 'bi-person-check-fill' }} text-2xl"></i>
                </div>
                <div class="relative min-w-0 flex-1">
                    <h3 class="font-black text-base leading-tight">{{ __('eventlab::messages.my_entry_cta') }}</h3>
                    <p class="text-xs text-white/85 mt-0.5">
                        {{ $mine === 'pending' ? __('eventlab::messages.my_entry_pending_hint') : __('eventlab::messages.my_entry_cta_hint') }}
                    </p>
                </div>
                <i class="bi bi-chevron-right text-white/80 relative flex-shrink-0"></i>
            </a>
        @elseif($e['enrol']['open'])
            {{-- The same card as the doors above it — icon tile, title, sub-line,
                 chevron — so the way IN reads as one of the places you can go
                 rather than a button bolted underneath them. Maroon rather than
                 the event's own colour: it is the one action on this page, and
                 it should not be mistaken for another door. The hint that used
                 to sit under the button is now its sub-line, where a sub-line
                 already goes. --}}
            <a href="{{ route('testcode.e.enter', ['event' => $e['key']]) }}"
               class="m-press rounded-2xl p-4 text-white relative overflow-hidden shadow-lg flex items-center gap-3.5 no-underline"
               style="background: linear-gradient(135deg, #7f1d1d, #1f2937);">
                <div class="absolute -right-6 -top-6 w-24 h-24 rounded-full bg-white/10"></div>

                <div class="relative w-12 h-12 rounded-2xl bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0">
                    <i class="bi bi-person-plus-fill text-2xl"></i>
                </div>
                <div class="relative min-w-0 flex-1">
                    <h3 class="font-black text-base leading-tight">{{ __('events.public_enrol_cta') }}</h3>
                    <p class="text-xs text-white/85 mt-0.5">{{ __('events.public_enrol_cta_hint') }}</p>
                </div>
                <i class="bi bi-chevron-right text-white/80 relative flex-shrink-0"></i>
            </a>
        @else
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 text-center">
                <i class="bi bi-lock-fill text-xl" style="color: {{ $e['color'] }}"></i>
                <p class="text-sm font-bold text-foreground mt-2">{{ __('events.public_entries_closed') }}</p>
                <p class="text-[12px] text-muted-foreground mt-1 leading-relaxed">{{ $e['enrol']['note'] ?: __('events.public_how_to_enter_body', ['club' => $e['host']]) }}</p>
            </div>
        @endif
    </div>

    {{-- The share ROW was removed 2026-09-04: the header already carries a
         share control, and offering the same action twice on one screen makes
         the second one read as something else. `share()` stays on the Alpine
         scope — the header button is what calls it. --}}
</div>
@endsection

@push('scripts')
<script>
    /* The two behaviours the shared markup expects, and nothing else. This page
       is outside the app shell, so it cannot borrow the shell's helpers — but
       the quick-facts chips are doors to the sections below them, and a chip
       that does nothing reads as broken. */
    function publicEvent() {
        return {
            jump(id) {
                const ids = Array.isArray(id) ? id : [id];
                for (const one of ids) {
                    const el = document.getElementById(one);
                    if (el) { el.scrollIntoView({ behavior: 'smooth', block: 'start' }); return; }
                }
            },
            share() {
                const data = { title: @js($e['title']), url: window.location.href };
                if (navigator.share) { navigator.share(data).catch(() => {}); return; }
                navigator.clipboard?.writeText(data.url).then(
                    () => notice(@js(__('events.public_share_copied'))),
                    () => notice(data.url),
                );
            },
        };
    }

    /* window.showToast belongs to the app shell, which is not loaded here. One
       small on-palette notice instead — never a native dialog. */
    function notice(msg) {
        const n = document.createElement('div');
        n.textContent = msg;
        n.style.cssText = 'position:fixed;left:1rem;right:1rem;bottom:calc(1.5rem + env(safe-area-inset-bottom));z-index:60;'
            + 'background:#111827;color:#fff;font-size:12.5px;padding:.85rem 1rem;border-radius:1rem;text-align:center;'
            + 'box-shadow:0 20px 40px -20px rgba(0,0,0,.5);transition:opacity .3s;opacity:0';
        document.body.appendChild(n);
        requestAnimationFrame(() => n.style.opacity = '1');
        setTimeout(() => { n.style.opacity = '0'; setTimeout(() => n.remove(), 320); }, 2600);
    }
</script>
@endpush
