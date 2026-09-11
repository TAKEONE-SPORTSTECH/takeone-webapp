@extends('entry.layout')

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
    $eyebrow = \App\Events\Support\EventClassification::line($e['sport_label'] ?? null, $e['type'] ?? null);

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
@include('entry.public.partials.cover')

{{-- The invitation to install — the only route to a page with no address
     bar on iOS, and the tidiest one on Android. Dismissed once, never asked
     again on that device. --}}
@include('entry.public.partials.install-prompt')

{{-- Every language the organiser's words can be read in. Opened from the
     cover, and lives out here rather than inside it: the cover is itself
     teleported to <body>, and nesting one teleport inside another is not a
     thing to rely on. --}}
@include('entry.public.partials.language-sheet')

<div x-data="publicEvent()" class="-mx-4 -mt-4 pb-4">

    {{-- ===== The band =====
         `<x-event-poster-band>` — one copy, shared with the member event page
         since 2026-09-08 so the two surfaces cannot drift apart. Only the
         CONTROL ROW is this page's own: a stranger's poster reopens the cover,
         opens the gear, shares, and carries the account control. --}}
    @php
        $hchips = [];
        if ($pPaid)      $hchips[] = ['bi-cash-coin', __('personal.event_show_paid_entry')];
        if ($ticketPaid) $hchips[] = ['bi-ticket-perforated', __('personal.event_show_ticketed')];
        if ($e['capped'] ?? false) $hchips[] = ['bi-people', trans_choice('events.public_spots', max(0, $e['cap'] - $e['going']), ['n' => max(0, $e['cap'] - $e['going'])])];
    @endphp
    <x-event-poster-band :color="$e['color']" :eyebrow="$eyebrow"
                         :title="$e['title']" :owner="$e['club']" :chips="$hchips">
        {{-- HOME, at the head of the classification line, where the dash was
             (asked for 2026-09-09). It is the poster's one way back to the
             cover — which is this app's front page and where the language is
             chosen — and on the poster there is nothing else to go "back" to,
             so it never belonged in the control cluster opposite. --}}
        <x-slot:lead>
            <button type="button" @click="window.dispatchEvent(new CustomEvent('reopen-cover'))"
                    class="m-press ev-ctl flex-none"
                    aria-label="{{ __('events.band_home') }}" title="{{ __('events.band_home') }}">
                <i class="bi bi-house-door-fill"></i>
            </button>
        </x-slot:lead>

        <x-slot:controls>
            {{-- ONE control row, shared with /me/events/{uuid} — see
                 partials/event-band-controls for what this replaced and why.
                 This page's own copy drew the same 40px control in inline
                 styles at a different alpha, with a chevron on the leading
                 edge that reopened the poster cover rather than going back
                 anywhere, and `bi-gear` for the job /me called `bi-sliders`.

                 Account + sign-out is no longer a fourth control: the gear
                 beside it already IS the way in for whoever is running this,
                 and signing OUT is a once-in-a-while act that belongs behind
                 the ⋯ with a name on it, not a permanent glyph competing with
                 Share. `<x-event-account>` therefore no longer renders here.

                 `share()` is this page's own, from partials/page-script. --}}
            @include('partials.event-band-controls', [
                'mode' => 'public',
                'e' => $e,
                'console' => $console ?? null,
                'signedIn' => auth()->check(),
                'signedInName' => auth()->user()?->full_name ?? auth()->user()?->name,
                'signOutUrl' => route('events.public.sign-out', ['event' => $e['key']]),
            ])
        </x-slot:controls>
    </x-event-poster-band>

    {{-- ===== The three facts, riding up over the band =====
         The design file's card, kept verbatim: a white panel with a hairline of
         the event's colour along its top edge, three columns divided by rules,
         and — when the organiser set a capacity — how full it is. No icon
         tiles: the design puts the bare glyph in the event's colour, which
         keeps the row about the VALUES.

         Each fact is still a door to its fuller answer further down: when → the
         run-of-show, how much → the way in, where → the map. --}}
    <div class="relative" style="padding:0 18px; margin-top:-46px;">
        {{-- ⚠️ NO `m-in` here — removed 2026-09-08, the same reason
             `mobile-stagger` came off entry/layout on the same day.

             `m-in` starts the card at opacity 0 and rises it in over half a
             second, so the band and everything under it paint first and this
             card arrives separately a beat later. On a first arrival that runs
             behind the cover and nobody sees it; on the RELOAD that follows
             choosing a language on the cover it plays in full view, and the
             page reads as loading a second time. Reported exactly that way.

             The member page's copy of this card has never animated. The card
             simply arrives; the entrance the visitor sees is the cover lifting. --}}
        <div style="background:#fff; border-radius:18px; border-top:3px solid {{ $ev }};
                    box-shadow:0 22px 60px rgba(30,44,79,.13), 0 2px 6px rgba(30,44,79,.06);
                    padding:18px 16px 16px;">

            <div class="grid text-center" style="grid-template-columns:1fr 1fr 1fr;">
                {{-- ⚠️ The two dividing rules belong to the MIDDLE column, on
                     both of its sides — never one physical `border-right` per
                     column.

                     `border-right` is a PHYSICAL side, and this page is read in
                     Arabic as often as in English. In RTL the columns flow
                     right-to-left, so a right border on the first column landed
                     on the card's outer edge, the second column's landed in the
                     right-hand gap, and the left-hand gap had no rule at all —
                     three columns with the separators in two wrong places.
                     Hanging both rules off the middle cell is direction-proof:
                     the middle column is the middle column either way, and its
                     two sides ARE the two internal gaps. (The member card next
                     door has always done it this way with `border-x`.) --}}
                <button type="button" @click="jump(['run-start','how-it-runs'])"
                        class="m-press" style="padding:2px 6px;"
                        aria-label="{{ __('personal.event_show_how_it_runs') }}">
                    <i class="bi bi-calendar3" style="font-size:17px; color:{{ $ev }};"></i>
                    <p style="margin:7px 0 0; font-size:12.5px; font-weight:700; color:#1e2c4f;">{{ $e['wday'] }} {{ $e['day'] }} {{ $e['mon'] }}</p>
                    <p style="margin:2px 0 0; font-size:10.5px; color:#6b7689;">{{ $e['time'] }}</p>
                </button>

                <button type="button" @click="jump(['fees','enter'])"
                        class="m-press"
                        style="border-left:1px solid #eef1f6; border-right:1px solid #eef1f6; padding:2px 6px;"
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
                'href' => route('events.public.section', ['event' => $e['key'], 'section' => 'draw']),
                'icon' => 'bi-diagram-3-fill bracket-icon',
                'label' => __('personal.event_show_tile_draw'),
                'sub' => $drawn
                    ? trans_choice('events.public_draw_competitors', $e['draw']['entrants'], ['n' => $e['draw']['entrants']])
                    : __('events.public_draw_empty_title'),
            ];
        }

        $doors[] = [
            'href' => route('events.public.section', ['event' => $e['key'], 'section' => 'officials']),
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
            'href' => route('events.public.section', ['event' => $e['key'], 'section' => 'gallery']),
            'icon' => 'bi-camera-reels-fill',
            'label' => __('events.bout_gallery_title'),
            'sub' => ($e['gallery']['count'] ?? 0) > 0
                ? trans_choice('events.bout_gallery_count', $e['gallery']['count'], ['count' => $e['gallery']['count']])
                : __('events.bout_gallery_none'),
        ];

        $doors[] = [
            'href' => route('events.public.section', ['event' => $e['key'], 'section' => 'participants']),
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

    {{-- ===== The way in ===== --}}
    <span id="enter" class="block"></span>
    <div class="px-4 mt-4">
        @if(($mine ?? null))
            {{-- Already in — so the one action here is the way back to what you
                 entered, not another way to enter. The event's own colour, not
                 the maroon of the entry door: this is a place you have been,
                 not a door you have yet to open.

                 It is read for the VIEWER only, so it says nothing about
                 anybody else on a page open to the world. --}}
            <a href="{{ route('events.public.my-entry', ['event' => $e['key']]) }}"
               class="m-press rounded-2xl p-4 text-white relative overflow-hidden shadow-lg flex items-center gap-3.5 no-underline"
               style="background: {{ \App\Support\Palette::eventBand($e['color']) }};">
                <div class="absolute -right-6 -top-6 w-24 h-24 rounded-full bg-white/10"></div>

                <div class="relative w-12 h-12 rounded-2xl bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0">
                    <i class="bi {{ $mine === 'pending' ? 'bi-hourglass-split' : 'bi-person-check-fill' }} text-2xl"></i>
                </div>
                <div class="relative min-w-0 flex-1">
                    <h3 class="font-black text-base leading-tight">{{ __('events.my_entry_cta') }}</h3>
                    <p class="text-xs text-white/85 mt-0.5">
                        {{ $mine === 'pending' ? __('events.my_entry_pending_hint') : __('events.my_entry_cta_hint') }}
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
            <a href="{{ route('events.public.enter', ['event' => $e['key']]) }}"
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
