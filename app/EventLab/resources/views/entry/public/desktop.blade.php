@extends('eventlab::entry.layout')

{{--
    The public event page — DESKTOP.

    Deliberately the SAME page as `/me/events/{uuid}` on a wide screen: the same
    full-bleed hero band, the same two-column grid, the same quick-facts aside,
    and literally the same detail card (`partials.event-detail-card-desktop`,
    included by both). What is missing is the app around it — no navbar, no
    sidebar, no footer bar — and the things a stranger has no business reading:
    no roster, no attendees, no medal table, no money owed by anybody. The DRAW
    is here, deliberately — PublicEvent::draw() says what survives onto it.

    That is not enforced here. Everything comes from
    App\Events\Support\PublicEvent, which is the one place that decides what a
    stranger may know; a section whose data it refuses to produce does not
    render. This file must never reach for a column of its own.
--}}

@section('body-class', 'bg-background text-foreground antialiased')

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

    $pPaid = (bool) ($e['fee_is_paid'] ?? false);
    $ticketPaid = ! empty($e['spectator']) && ! str_contains(mb_strtolower($e['spectator']['fee']), 'free');

    /* The band takes the event's colour DOWN towards navy rather than
       lightening it, so white type sits on it at full contrast. Mixed in PHP by
       Palette — the design's `color-mix(in oklab, …)` is dropped whole by an
       Android WebView older than Chrome 111. */
    $ev = Palette::safe($e['color']);
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

    /* A flag-icons class for a 2-letter code, or null — the same helper the
       members' roster uses; the sheet is loaded by entry.layout. */
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

    $accent = \App\Support\Palette::safe($e['color']);
    $accentSoft = \App\Support\Palette::alpha($accent, .09);
@endphp
{{-- ===== The entry cover — dismissed to reveal the page below =====
     Mobile has had this since the cover was built; desktop never included it,
     so a shared link opened straight onto a scrolling page of facts. Separate
     file because the composition genuinely diverges (partials/cover-desktop). --}}
@include('eventlab::entry.public.partials.cover-desktop')

{{-- The invitation to install — the only route to a page with no address
     bar on iOS, and the tidiest one on Android. Dismissed once, never asked
     again on that device. --}}
@include('eventlab::entry.public.partials.install-prompt')

<div x-data="publicEvent()" class="-mx-4 -my-4 px-4 sm:px-6 lg:px-8 py-6">

    {{-- ===== The band =====
         The design file's header (drafts/upper header.png), the same one the
         phone shows: the event's colour taken DOWN towards navy, a dash and the
         classification in tracked caps, the big title, whose it is, then the
         three facts that decide whether to read on. Full-bleed, and the inner
         padding mirrors the page wrapper's so the title sits on the same
         vertical axis as the content below it.

         This is the ONE page that departs from Design Rule #6's hero band, and
         deliberately — it is a poster a stranger was sent, not a screen inside
         the app. The four section pages keep the standard band. --}}
    <div class="-mx-4 sm:-mx-6 lg:-mx-8 -mt-6 overflow-hidden shadow-sm mb-6 text-white relative"
         style="background: {{ \App\Support\Palette::eventBand($e['color']) }};">
        <div class="absolute rounded-full" style="right:-56px; top:-56px; width:190px; height:190px; background:rgba(255,255,255,.07);"></div>
        <div class="absolute rounded-full" style="right:22px; bottom:26px; width:96px; height:96px; background:rgba(255,255,255,.06);"></div>

        <div class="relative px-4 sm:px-6 lg:px-8 py-6 sm:py-8">
            {{-- Control row. No back pill: this is a top-level destination, not
                 a drill-down — the reader arrived from a link. --}}
            <div class="flex items-center justify-between gap-3">
                <span class="flex items-center min-w-0" style="gap:10px;">
                    <span class="flex-none" style="width:38px; height:3px; border-radius:2px; background:rgba(255,255,255,.85);"></span>
                    @if($eyebrow)
                        <span class="uppercase truncate" style="font-size:11px; font-weight:600; letter-spacing:.2em; color:rgba(255,255,255,.85);">{{ $eyebrow }}</span>
                    @endif
                </span>

                <span class="flex items-center flex-none" style="gap:8px;">
                    {{-- The gear: the way IN for whoever is running this.
                         A link, not a button, and offered to every reader —
                         showing it only to organisers would tell a stranger who
                         the organisers are. It lands on the event's own sign-in
                         (PublicEventController@manage), so the person running
                         the competition never has to leave it to sign in, and
                         goes straight to the console when they already are. --}}
                    <a href="{{ route('testcode.e.manage', $e['key']) }}"
                       aria-label="{{ __('events.public_manage_title') }}"
                       title="{{ __('events.public_manage_title') }}"
                       class="grid place-items-center flex-none hover:bg-white/25 transition-colors"
                       style="width:40px; height:40px; border-radius:50%; border:1px solid rgba(255,255,255,.3); background:rgba(255,255,255,.14); font-size:15px;">
                        <i class="bi bi-gear"></i>
                    </a>

                    {{-- Back to the poster — the cover is dismissed once per tab.
                         Dispatched on `window`: the cover is its own Alpine root,
                         teleported to <body>. Mirrors the mobile page. --}}
                    <button type="button" @click="window.dispatchEvent(new CustomEvent('reopen-cover'))"
                            aria-label="{{ __('events.public_cover_reopen') }}"
                            class="grid place-items-center flex-none hover:bg-white/25 transition-colors"
                            style="width:40px; height:40px; border-radius:50%; border:1px solid rgba(255,255,255,.3); background:rgba(255,255,255,.14); font-size:15px;">
                        <i class="bi bi-image"></i>
                    </button>

                    <button type="button" @click="share()" aria-label="{{ __('events.public_share') }}"
                            class="grid place-items-center flex-none hover:bg-white/25 transition-colors"
                            style="width:40px; height:40px; border-radius:50%; border:1px solid rgba(255,255,255,.3); background:rgba(255,255,255,.14); font-size:15px;">
                        <i class="bi bi-share"></i>
                    </button>
                </span>
            </div>

            <h1 class="relative" style="margin:26px 0 0; font-size:32px; line-height:1.14; font-weight:700; letter-spacing:-.01em; max-width:24ch;">{{ $e['title'] }}</h1>

            @if($e['club'])
                <p class="relative flex items-center" style="margin:10px 0 0; gap:8px; font-size:13px; color:rgba(255,255,255,.82);">
                    <i class="bi bi-building"></i>{{ $e['club'] }}
                </p>
            @endif

            {{-- The three poster facts. A head COUNT is one of them; WHO is
                 entered is the Participants door in the left column. --}}
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
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-[1fr_360px] gap-6 items-start">

        {{-- ===== Left: the event, in one card — the SAME partial ===== --}}
        <div class="space-y-4 min-w-0">
            @include('partials.event-detail-card-desktop', ['documents' => $e['documents'] ?? []])

            {{-- ===== Draw · Officials · Gallery · Participants =====
                 Four doors out of this page, the same four the member page has and in the
                 same order. Each opens its own page rather than expanding here: a draw, an
                 officiating sheet, a footage gallery and an entry list are each a screen's
                 worth of reading.

                 The Draw door only exists when the event HAS divisions — an event that runs
                 no draw gets no door to one, which is the rule the member page's own Draw
                 row follows. --}}
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

                // Always a door, even with nothing behind it yet: an event's footage is a
                // place people go looking for, and a door that only appears once something
                // is behind it cannot be found before then. The page says so when empty.
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

            <div class="space-y-2">
                @foreach($doors as $d)
                    <a href="{{ $d['href'] }}"
                       class="block rounded-2xl p-4 text-white relative overflow-hidden shadow-md hover:shadow-lg transition-shadow no-underline"
                       style="background: linear-gradient(135deg, {{ $e['color'] }}, #1f2937);">
                        <div class="absolute -right-6 -top-6 w-24 h-24 rounded-full bg-white/10"></div>
                        <div class="relative flex items-center gap-3">
                            <div class="w-11 h-11 rounded-2xl bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0">
                                <i class="bi {{ $d['icon'] }} text-xl"></i>
                            </div>
                            <div class="min-w-0 flex-1">
                                <h3 class="font-black text-[15px] leading-tight">{{ $d['label'] }}</h3>
                                <p class="text-[11px] text-white/85 mt-0.5 truncate">{{ $d['sub'] }}</p>
                            </div>
                            <i class="bi bi-chevron-right text-white/80 flex-shrink-0"></i>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>

        {{-- ===== Right: quick facts and the way in ===== --}}
        <aside class="space-y-4">
            {{-- The design file's facts card: a hairline of the event's colour
                 along the top edge, three columns divided by rules, and the
                 bare glyph in the event's colour rather than an icon tile —
                 which keeps the row about the VALUES. --}}
            <div class="bg-white overflow-hidden"
                 style="border-radius:18px; border-top:3px solid {{ $ev }};
                        box-shadow:0 22px 60px rgba(30,44,79,.13), 0 2px 6px rgba(30,44,79,.06);
                        padding:18px 16px 16px;">
                <div class="grid grid-cols-3 text-center">
                    <button type="button" @click="jump(['run-start', 'how-it-runs'])"
                            style="border-right:1px solid #eef1f6; padding:2px 6px;"
                            aria-label="{{ __('personal.event_show_how_it_runs') }}">
                        <i class="bi bi-calendar3" style="font-size:17px; color:{{ $ev }};"></i>
                        <p style="margin:7px 0 0; font-size:12.5px; font-weight:700; color:#1e2c4f;">{{ $e['wday'] }} {{ $e['day'] }} {{ $e['mon'] }}</p>
                        <p style="margin:2px 0 0; font-size:10.5px; color:#6b7689;">{{ $e['time'] }}</p>
                    </button>
                    <button type="button" @click="jump('enter')"
                            style="border-right:1px solid #eef1f6; padding:2px 6px;"
                            aria-label="{{ __('personal.event_show_to_join') }}">
                        <i class="bi bi-cash-coin" style="font-size:17px; color:{{ $ev }};"></i>
                        <p style="margin:7px 0 0; font-size:12.5px; font-weight:700; color:#1e2c4f;">{{ $e['participant_fee'] }}</p>
                        <p style="margin:2px 0 0; font-size:10.5px; color:#6b7689;">{{ __('personal.event_show_to_join') }}</p>
                    </button>
                    <button type="button" @click="jump('where')"
                            class="min-w-0" style="padding:2px 6px;"
                            aria-label="{{ __('personal.event_show_location') }}">
                        <i class="bi bi-geo-alt" style="font-size:17px; color:{{ $ev }};"></i>
                        <p class="truncate" style="margin:7px 0 0; font-size:12.5px; font-weight:700; color:#1e2c4f;" title="{{ $e['location'] }}">{{ $e['location'] }}</p>
                        <p style="margin:2px 0 0; font-size:10.5px; color:#6b7689;">{{ __('personal.event_show_venue') }}</p>
                    </button>
                </div>

                {{-- Capacity — a head count is a poster fact; WHO is entered is
                     not, and never appears on this page. --}}
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

            {{-- The way in --}}
            <span id="enter" class="block"></span>
            @if($e['enrol']['open'])
                {{-- The doors' card shape, in maroon — see the mobile poster for
                     the reasoning. Kept in step with entry/public/mobile.blade.php. --}}
                <a href="{{ route('testcode.e.enter', ['event' => $e['key']]) }}"
                   class="rounded-2xl p-4 text-white relative overflow-hidden shadow-lg flex items-center gap-3.5 no-underline hover:opacity-95 transition-opacity"
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
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 text-center">
                    <i class="bi bi-lock-fill text-xl" style="color: {{ $e['color'] }}"></i>
                    <p class="text-sm font-bold text-foreground mt-2">{{ __('events.public_entries_closed') }}</p>
                    <p class="text-[12px] text-muted-foreground mt-1">{{ $e['enrol']['note'] ?: __('events.public_how_to_enter_body', ['club' => $e['host']]) }}</p>
                </div>
            @endif

            {{-- The share ROW was removed 2026-09-04: the header already carries
                 a share control, and the same action twice on one screen makes
                 the second one read as something else. --}}
        </aside>
    </div>
</div>
@endsection

@push('scripts')
<script>
    /* The two behaviours the shared markup expects, and nothing else. Outside
       the app shell there are no shell helpers to borrow — but the quick-facts
       chips are doors to the sections beside them, and a chip that does nothing
       reads as broken. */
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

    /* window.showToast belongs to the app shell, which is not loaded here. */
    function notice(msg) {
        const n = document.createElement('div');
        n.textContent = msg;
        n.style.cssText = 'position:fixed;left:50%;transform:translateX(-50%);bottom:2rem;z-index:60;'
            + 'background:#111827;color:#fff;font-size:12.5px;padding:.85rem 1.25rem;border-radius:1rem;'
            + 'box-shadow:0 20px 40px -20px rgba(0,0,0,.5);transition:opacity .3s;opacity:0';
        document.body.appendChild(n);
        requestAnimationFrame(() => n.style.opacity = '1');
        setTimeout(() => { n.style.opacity = '0'; setTimeout(() => n.remove(), 320); }, 2600);
    }
</script>
@endpush
