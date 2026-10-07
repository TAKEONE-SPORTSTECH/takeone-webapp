@extends('events::entry.layout')

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

    /* ===== A MEMBER reading this page =====
       `$memberEvent` is set only on /me/events/{uuid} with
       `events.unified_page` on (PersonalEventController::unifiedPoster). On
       /e/{uuid} it is never set, so every block gated on `$asMember` below is
       simply absent there and this page is what it always was.

       It is the member page's own payload, and it feeds only the parts a
       member has and a stranger does not: joining, paying, the reason they
       may not, and their doors into the platform. `$e` stays the poster. */
    $asMember = isset($memberEvent);
    $posterPublic = $posterPublic ?? true;

    if ($asMember) {
        $mFee = strtolower($memberEvent['participant_fee'] ?? '');
        $mByQual = str_contains($mFee, 'qualified');
        $mPaid = (! str_contains($mFee, 'free') && ! $mByQual) || ! empty($memberEvent['fees']['options']);
        $mHasTicket = ! empty($memberEvent['spectator']);
        $mTicketPaid = ($mHasTicket && ! str_contains(strtolower($memberEvent['spectator']['fee']), 'free'))
            || ! empty($memberEvent['fees']['spectator_options']);
        $mWhyNot = (($banned ?? false) || ($excludedAt ?? null))
            ? ($eligReason ?? __('personal.event_show_removed_default'))
            : (($memberEvent['ended'] ?? false)
                ? __('personal.event_show_ended_msg')
                : ($mByQual
                    ? __('personal.event_show_entry_by_qualification')
                    : ($eligReason ?? __('personal.event_show_not_eligible_default'))));
        $mLocked = ($banned ?? false) || ($excludedAt ?? null) !== null || ($memberEvent['ended'] ?? false);
        $mCanJoin = ! $mLocked && ($canCompete ?? true) && ! $mByQual;

        // What the member partials expect under their usual names.
        $memberScope = ['e' => $memberEvent, 'pPaid' => $mPaid, 'byQual' => $mByQual,
            'hasTicket' => $mHasTicket, 'ticketPaid' => $mTicketPaid, 'whyNot' => $mWhyNot];
    }

    /* Seconds as a clock, for a gallery tile's corner. */
    $clock = function ($seconds) {
        $s = max(0, (int) $seconds);

        return $s >= 3600
            ? sprintf('%d:%02d:%02d', intdiv($s, 3600), intdiv($s % 3600, 60), $s % 60)
            : sprintf('%d:%02d', intdiv($s, 60), $s % 60);
    };
@endphp
{{-- ===== The entry cover — tapped away to reveal the page below ===== --}}
@include('events::entry.public.partials.cover')

{{-- The invitation to install — the only route to a page with no address
     bar on iOS, and the tidiest one on Android. Dismissed once, never asked
     again on that device. --}}
@include('events::entry.public.partials.install-prompt')

{{-- Every language the organiser's words can be read in. Opened from the
     cover, and lives out here rather than inside it: the cover is itself
     teleported to <body>, and nesting one teleport inside another is not a
     thing to rely on. --}}
@include('events::entry.public.partials.language-sheet')

<div x-data="publicEvent()" class="-mx-4 -mt-4 pb-4">
{{-- The member's own state (joining, paying, the results sheet), nested
     INSIDE the poster's scope: the poster's share() and notice() still answer,
     and the member's jump() and sheets sit on top. --}}
@if($asMember)<div @include('events::partials.event-show-script', $memberScope)>@endif

    {{-- ===== The band =====
         `<x-event-poster-band>` — one copy, shared with the member event page
         since 2026-09-08 so the two surfaces cannot drift apart. Only the
         CONTROL ROW is this page's own: a stranger's poster reopens the cover,
         opens the gear, shares, and carries the account control. --}}
    @php
        $hchips = [];
        if ($pPaid)      $hchips[] = ['bi-cash-coin', __('personal.event_show_paid_entry')];
        if ($ticketPaid) $hchips[] = ['bi-ticket-perforated', __('personal.event_show_ticketed')];
        if ($e['capped'] ?? false) $hchips[] = ['bi-people', trans_choice('events::events.public_spots', max(0, $e['cap'] - $e['going']), ['n' => max(0, $e['cap'] - $e['going'])])];
    @endphp
    <x-event-poster-band :color="$e['color']" :eyebrow="$eyebrow"
                         :title="$e['title']" :owner="$e['club']" :chips="$hchips">
        {{-- HOME, at the head of the classification line, where the dash was
             (asked for 2026-09-09). It is the poster's one way back to the
             cover — which is this app's front page and where the language is
             chosen — and on the poster there is nothing else to go "back" to,
             so it never belonged in the control cluster opposite. --}}
        <x-slot:lead>
            @if($asMember)
                {{-- A MEMBER came here from the platform: the leading edge is
                     the way back to it, exactly as the member page it replaced
                     had it (Design Rule #6 — back leads, actions trail). --}}
                @php $back = $eventReturn ?? ['url' => route('me.events'), 'label' => __('personal.event_show_events')]; @endphp
                <a href="{{ $back['url'] }}" class="m-press ev-ctl flex-none"
                   aria-label="{{ $back['label'] }}" title="{{ $back['label'] }}">
                    <i class="bi bi-chevron-left rtl:rotate-180"></i>
                </a>
            @else
            <button type="button" @click="window.dispatchEvent(new CustomEvent('reopen-cover'))"
                    class="m-press ev-ctl flex-none"
                    aria-label="{{ __('events::events.band_home') }}" title="{{ __('events::events.band_home') }}">
                <i class="bi bi-house-door-fill"></i>
            </button>
            @endif
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
            @include('events::partials.event-band-controls', [
                'mode' => 'public',
                'e' => $e,
                'console' => $console ?? null,
                'signedIn' => auth()->check(),
                'signedInName' => auth()->user()?->full_name ?? auth()->user()?->name,
                // Signing out lands on /e/{uuid}, which 404s while the
                // organiser has not published the page.
                'signOutUrl' => $posterPublic ? route('events.public.sign-out', ['event' => $e['key']]) : null,
                // The organiser's QR and printable poster, for whoever runs it.
                'withQr' => $asMember && (($canManage ?? false) || ($canOfficiate ?? false)),
                'shareUrl' => $shareUrl ?? null,
            ])
        </x-slot:controls>

        @if($asMember && ($memberEvent['cancelled'] ?? false))
            <x-slot:banner>
                <div class="relative z-10 mt-4 -mb-2 rounded-xl bg-white/20 backdrop-blur px-3 py-2 text-xs font-bold flex items-center gap-2">
                    <i class="bi bi-exclamation-triangle-fill"></i> {{ __('personal.event_show_cancelled_banner') }}
                </div>
            </x-slot:banner>
        @endif
    </x-event-poster-band>

    @include('events::entry.public.partials.quick-facts')

    {{-- The numbers: female · male · divisions · bouts (EventStats). --}}
    @include('events::entry.public.partials.event-stats')

    {{-- ===== The event, in one card — the SAME partial the member page uses.
         No `sectionVariant`, so it draws the product's dark full-bleed section
         bands exactly as `/me/events/{uuid}` does. ===== --}}
    @include('events::partials.event-detail-card-mobile', $asMember ? [
        // A member downloads through the member door, which works whether or
        // not the organiser published a public page. `?division=` opens the
        // member draw on that group, which says WHEN when it is not out yet.
        'documents' => $documents ?? [],
        'divisionLink' => $addr->section('draw').'?division=',
        'divisionNote' => null,
        'noteTap' => null,
    ] : [
        // The organiser published the page, so the files they attached travel
        // with it. `PublicEvent` supplies download URLs on the public route —
        // the member route's auth stack is untouched.
        'documents' => $e['documents'] ?? [],

        /* Where a division chip goes: the draw section, opened on that one
           group. `?division=` is the parameter that section already reads, and
           the same door a participant card's chip uses — one convention.

           Only when the draw is actually out. `$drawOpen` is the controller's
           single answer to "would that page have anything on it", reveal
           setting and all; without it the chips stay plain labels rather than
           becoming doors onto a padlock. */
        'divisionLink' => ($drawOpen ?? false)
            ? $addr->section('draw').'?division='
            : null,

        /* And when there is no door: the chip still answers. `$drawNote` is the
           controller's own sentence — "opens on…" when the organiser is holding
           the draw back, "no draw yet" when it simply has not been cut — the
           same words the draw veil and a participant card's chip use. A chip
           that does nothing on tap reads as a broken chip; one that says WHEN
           is the answer the reader came for.

           `notice()` rather than window.showToast: the app shell is not loaded
           on this page and this page has always carried its own on-palette
           notice (see the script at the foot of this file). Never a native
           dialog. */
        'divisionNote' => ($drawOpen ?? false) ? null : ($drawNote ?? null),
        'noteTap' => ($drawOpen ?? false) ? null : 'notice('.json_encode((string) ($drawNote ?? '')).')',
    ])

    {{-- ===== The doors =====
         Built here, drawn by `partials.event-doors` — the same partial the
         member page renders, so the two surfaces cannot drift apart again.
         Each door names a public SECTION of this event.

         A door only exists when there is something behind it: no draw for an
         event that runs none, no table unless the owning package publishes one,
         no winners until somebody has won. --}}
    @php
        /* One list for every page that shows this event's doors — the federation
           invitation draws the same ones (App\Events\Support\EventDoors). */
        $doors = ($passOnly ?? false) ? [] : \App\Events\Support\EventDoors::public($e, $addr);
    @endphp

    @include('events::partials.event-doors')

    {{-- ===== The way in ===== --}}
    <span id="enter" class="block"></span>
    @if($asMember)
        @include('events::entry.public.partials.member-way-in')
    @else
        @include('events::entry.public.partials.way-in')
    @endif

    {{-- The share ROW was removed 2026-09-04: the header already carries a
         share control, and offering the same action twice on one screen makes
         the second one read as something else. `share()` stays on the Alpine
         scope — the header button is what calls it. --}}
@if($asMember)</div>@endif
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
                // EventShareLink: the event's own page, or — when it is not public —
                // its invitation link, which opens without an account.
                const data = { title: @js($e['title']), url: @js($shareUrl ?? null) || window.location.href };
                if (navigator.share) { navigator.share(data).catch(() => {}); return; }
                navigator.clipboard?.writeText(data.url).then(
                    () => notice(@js(__('events::events.public_share_copied'))),
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
