@props([
    'data',                 // App\Events\Support\DrawWaitingRoom::build() output
    'color' => '#7c3aed',   // the event's own colour
])

{{--
    The waiting room — what a withheld draw shows instead of itself.

    A member who taps "Draw" a week early used to get a padlock and one
    sentence. True, and almost useless: the question underneath the one they
    asked is "is this happening, and how big is it?" — and the event knows. So
    the field is laid out as it stands instead: a clock to the weigh-in, the
    head count, the spread across the weight classes, the flags and the clubs.

    None of this is the draw. Who is entered has always been public on the
    participants list; what is withheld is who FIGHTS WHO, and nothing here
    says that.

    ── Standalone ─────────────────────────────────────────────────────────────
    Markup, one inline Alpine scope for the clock, and one @once stylesheet.
    No page glue, no global, no companion script — drop it into any view that
    can hand it a payload. The clock is inline `x-data` deliberately: a
    registered component would be inert if this ever landed inside a teleported
    template, and inline survives the mobile shell's content swaps.

    ── Inputs ─────────────────────────────────────────────────────────────────
    `data`    — the array from App\Events\Support\DrawWaitingRoom
    `color`   — the event's colour; anything that is not a #rrggbb is refused,
                because it lands in a style attribute.
--}}

@php
    $c = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $color) ? $color : '#7c3aed';

    $d = $data;
    $at = $d['at'] ?? null;
    $athletes = (int) ($d['athletes'] ?? 0);
    $entries = (int) ($d['entries'] ?? 0);
    $divisions = $d['divisions'] ?? [];
    $countries = $d['countries'] ?? [];
    $clubs = $d['clubs'] ?? [];

    // The moment being counted to, spelled out under the clock. Through Cldr,
    // never format() — a raw format() prints an English date on an Arabic page.
    $when = $at ? \Illuminate\Support\Carbon::parse($at) : null;
    $whenText = $when
        ? \App\Support\Cldr::skeleton($when, 'EEE d MMM', 'D j M').' · '.\App\Support\Cldr::time($when)
        : null;
@endphp

@once
@push('styles')
<style>
    /* The clock. Tiles rather than a run of digits: a number that changes
       every second needs an edge to change inside, or the whole line jitters
       as the glyph widths shift. `tabular-nums` does the rest. */
    .tk-wr-tile {
        position: relative;
        border-radius: 1rem;
        padding: .6rem .25rem .5rem;
        background: rgba(255,255,255,.16);
        border: 1px solid rgba(255,255,255,.26);
        backdrop-filter: blur(6px);
        text-align: center;
        overflow: hidden;
    }
    /* A hairline of light along the top edge — the one detail that stops four
       flat boxes reading as a form. */
    .tk-wr-tile::before {
        content: "";
        position: absolute; inset-inline: 18%; top: 0; height: 1px;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,.75), transparent);
    }
    .tk-wr-num {
        display: block;
        font-size: 1.6rem; line-height: 1.05; font-weight: 900;
        font-variant-numeric: tabular-nums; font-feature-settings: "tnum";
        letter-spacing: -.02em;
    }
    .tk-wr-unit {
        display: block; margin-top: .15rem;
        font-size: .6rem; font-weight: 700; letter-spacing: .08em;
        text-transform: uppercase; opacity: .72;
    }
    /* The seconds tile breathes, so the card is visibly alive without a
       spinner. One element, one transform — cheap on a phone. */
    @keyframes tk-wr-beat { 0%,100% { transform: scale(1); } 45% { transform: scale(1.045); } }
    .tk-wr-beat { animation: tk-wr-beat 1s ease-in-out infinite; }

    /* Division bars. The track is the event colour at 8%, so the row belongs to
       this event rather than to the design system's grey. */
    .tk-wr-track { height: .5rem; border-radius: 999px; overflow: hidden; background: rgba(15,23,42,.07); }
    .tk-wr-bar { height: 100%; border-radius: 999px; transition: width .6s cubic-bezier(.22,.61,.36,1); }

    @media (prefers-reduced-motion: reduce) {
        .tk-wr-beat { animation: none; }
        .tk-wr-bar { transition: none; }
    }
</style>
@endpush
@endonce

<div class="space-y-4">

    {{-- No padlock card. It said "the draw has not been published yet" over a
         clock counting down to the weigh-in and a card full of entries, which
         is the same fact told three times and the flattest of the three. The
         organiser's setting is what withholds the draw; the reader does not
         need it announced, they need to know when to come back — which is what
         the clock is. (Removed 2026-09-16 at the user's request. The plain
         <x-draw-veil> is still what the host renders when there is nothing to
         count and nobody entered.) --}}

    {{-- ===== The clock ===== --}}
    @if($at)
        <div class="m-card rounded-3xl overflow-hidden"
             x-data="{
                at: Date.parse('{{ $at }}'),
                d: '00', h: '00', m: '00', s: '00', done: false, timer: null,
                init() {
                    this.calc();
                    this.timer = setInterval(() => this.calc(), 1000);
                },
                destroy() { clearInterval(this.timer); },
                calc() {
                    const left = this.at - Date.now();
                    if (!(left > 0)) {
                        this.done = true;
                        clearInterval(this.timer);
                        this.d = this.h = this.m = this.s = '00';
                        return;
                    }
                    const p = (n) => String(n).padStart(2, '0');
                    this.d = p(Math.floor(left / 86400000));
                    this.h = p(Math.floor(left / 3600000) % 24);
                    this.m = p(Math.floor(left / 60000) % 60);
                    this.s = p(Math.floor(left / 1000) % 60);
                }
             }">
            <div class="relative px-5 pt-5 pb-6 text-white overflow-hidden"
                 style="background: linear-gradient(150deg, {{ $c }}, {{ $c }}b0);">
                {{-- The house depth pattern: two soft circles, nothing else. --}}
                <div class="absolute -right-10 -top-12 w-44 h-44 rounded-full bg-white/10"></div>
                <div class="absolute right-6 bottom-6 w-24 h-24 rounded-full bg-white/10"></div>

                <div class="relative">
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-white/90 animate-pulse"></span>
                        <p class="text-[11px] font-bold uppercase" style="letter-spacing: .1em;">
                            {{ ($d['target'] ?? null) === 'weigh_in' ? __('events::events.waiting_until_weigh_in') : __('events::events.waiting_until_start') }}
                        </p>
                    </div>

                    <div class="grid grid-cols-4 gap-2 mt-3" x-show="!done">
                        <div class="tk-wr-tile">
                            <span class="tk-wr-num" x-text="d">00</span>
                            <span class="tk-wr-unit">{{ __('events::events.waiting_days') }}</span>
                        </div>
                        <div class="tk-wr-tile">
                            <span class="tk-wr-num" x-text="h">00</span>
                            <span class="tk-wr-unit">{{ __('events::events.waiting_hours') }}</span>
                        </div>
                        <div class="tk-wr-tile">
                            <span class="tk-wr-num" x-text="m">00</span>
                            <span class="tk-wr-unit">{{ __('events::events.waiting_minutes') }}</span>
                        </div>
                        <div class="tk-wr-tile tk-wr-beat">
                            <span class="tk-wr-num" x-text="s">00</span>
                            <span class="tk-wr-unit">{{ __('events::events.waiting_seconds') }}</span>
                        </div>
                    </div>

                    {{-- The clock ran out while the page was open. It must not
                         sit at 00:00:00 pretending — that reads as broken. --}}
                    <div x-show="done" x-cloak class="mt-3 rounded-2xl bg-white/15 border border-white/25 px-4 py-3 text-sm font-bold flex items-center gap-2">
                        <i class="bi bi-hourglass-bottom"></i>
                        {{ ($d['target'] ?? null) === 'weigh_in' ? __('events::events.waiting_weigh_in_now') : __('events::events.waiting_start_now') }}
                    </div>

                    @if($whenText)
                        <p class="text-[12px] text-white/85 mt-3 flex items-center gap-1.5">
                            <i class="bi bi-calendar-event"></i>{{ $whenText }}
                        </p>
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- ===== The field ===== --}}
    <div class="m-card rounded-3xl p-4">
        <h3 class="text-sm font-bold text-foreground flex items-center gap-2">
            <i class="bi bi-bar-chart-line-fill" style="color: {{ $c }};"></i>
            {{ __('events::events.waiting_field_title') }}
        </h3>
        <p class="text-[11px] text-muted-foreground mt-0.5">{{ __('events::events.waiting_field_sub') }}</p>

        {{-- Four numbers, one glance. `athletes` is PEOPLE and `entries` is
             places in the draw: an athlete in three divisions is one person and
             three entries, and a card that blurs the two is the reason "102
             entrants, 62 participants" was a support question. --}}
        <div class="grid grid-cols-2 gap-2 mt-3">
            @php
                $tiles = [
                    ['i' => 'bi-people-fill', 'v' => $athletes, 'l' => __('events::events.waiting_athletes')],
                    ['i' => 'bi-grid-1x2-fill', 'v' => (int) ($d['divisions_total'] ?? 0), 'l' => __('events::events.waiting_divisions')],
                    ['i' => 'bi-globe2', 'v' => (int) ($d['countries_total'] ?? 0), 'l' => __('events::events.waiting_countries')],
                    ['i' => 'bi-buildings-fill', 'v' => (int) ($d['clubs_total'] ?? 0), 'l' => __('events::events.waiting_clubs')],
                ];
            @endphp
            @foreach($tiles as $t)
                <div class="rounded-2xl p-3 flex items-center gap-3" style="background: {{ $c }}0f;">
                    <span class="w-9 h-9 rounded-xl grid place-items-center text-white flex-shrink-0"
                          style="background: linear-gradient(150deg, {{ $c }}, {{ $c }}b0);">
                        <i class="bi {{ $t['i'] }}"></i>
                    </span>
                    <div class="min-w-0">
                        <p class="text-lg font-black text-foreground leading-none">{{ \App\Support\Cldr::number($t['v']) }}</p>
                        <p class="text-[10px] font-semibold text-muted-foreground uppercase mt-1" style="letter-spacing: .06em;">{{ $t['l'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>

        <p class="text-[11px] text-muted-foreground mt-3 flex items-center gap-1.5">
            <i class="bi bi-info-circle"></i>
            {{ __('events::events.waiting_entries_note', ['entries' => \App\Support\Cldr::number($entries), 'people' => \App\Support\Cldr::number($athletes)]) }}
        </p>
    </div>

    {{-- ===== Per weight class ===== --}}
    @if($divisions)
        <div class="m-card rounded-3xl p-4">
            <h3 class="text-sm font-bold text-foreground flex items-center gap-2 mb-3">
                <i class="bi bi-list-ol" style="color: {{ $c }};"></i>
                {{ __('events::events.waiting_by_division') }}
            </h3>

            <div class="space-y-2.5">
                @foreach($divisions as $div)
                    <div>
                        <div class="flex items-baseline justify-between gap-3 mb-1">
                            <p class="text-[12.5px] font-semibold text-foreground truncate">{{ $div['name'] }}</p>
                            <span class="text-[11px] font-bold flex-shrink-0" style="color: {{ $c }};">
                                {{ \App\Support\Cldr::number($div['count']) }}
                            </span>
                        </div>
                        <div class="tk-wr-track">
                            {{-- Measured against the BIGGEST division, not the
                                 total: thirteen divisions of a share each would
                                 be thirteen identical slivers. --}}
                            <div class="tk-wr-bar m-bar-fill"
                                 style="width: {{ max(2, (int) $div['pct']) }}%; background: linear-gradient(90deg, {{ $c }}, {{ $c }}b0);"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- ===== Flags ===== --}}
    @if($countries || ($d['countries_unknown'] ?? 0))
        <div class="m-card rounded-3xl p-4">
            <h3 class="text-sm font-bold text-foreground flex items-center gap-2 mb-3">
                <i class="bi bi-flag-fill" style="color: {{ $c }};"></i>
                {{ __('events::events.waiting_countries_title') }}
            </h3>
            <div class="flex flex-wrap gap-2">
                @foreach($countries as $country)
                    <span class="inline-flex items-center gap-2 ps-1.5 pe-2.5 py-1.5 rounded-full bg-muted">
                        <span class="fi fi-{{ $country['code'] }} rounded-sm"></span>
                        <span class="text-[11px] font-bold text-foreground uppercase">{{ $country['code'] }}</span>
                        <span class="text-[11px] font-semibold text-muted-foreground">{{ \App\Support\Cldr::number($country['count']) }}</span>
                    </span>
                @endforeach

                {{-- Nobody's flag, counted. Without this the chips added up to
                     52 over a field of 62 and the reader was left hunting for
                     the missing ten — a row that does not reconcile reads as a
                     broken page rather than as the gap in the data that it is.
                     Muted and flagless on purpose: it is an absence, and it
                     must not look like a country. --}}
                @if($d['countries_unknown'] ?? 0)
                    <span class="inline-flex items-center gap-2 ps-2.5 pe-2.5 py-1.5 rounded-full border border-dashed border-border">
                        <i class="bi bi-dash-circle text-muted-foreground text-[11px]"></i>
                        <span class="text-[11px] font-bold text-muted-foreground">{{ __('events::events.waiting_no_country') }}</span>
                        <span class="text-[11px] font-semibold text-muted-foreground">{{ \App\Support\Cldr::number($d['countries_unknown']) }}</span>
                    </span>
                @endif
            </div>
        </div>
    @endif

    {{-- ===== Clubs ===== --}}
    @if($clubs || ($d['clubs_unattached'] ?? 0))
        <div class="m-card rounded-3xl p-4"
             x-data="{ all: false }">
            <h3 class="text-sm font-bold text-foreground flex items-center gap-2 mb-3">
                <i class="bi bi-buildings-fill" style="color: {{ $c }};"></i>
                {{ __('events::events.waiting_clubs_title') }}
            </h3>

            <div class="space-y-2">
                @foreach($clubs as $i => $club)
                    <div class="flex items-center gap-3" @if($i >= 8) x-show="all" x-cloak @endif>
                        {{-- A logo is a transparent PNG and gets a sizing box and
                             nothing else — never a white tile (Design Rule #5). --}}
                        <span class="w-9 h-9 flex-shrink-0 grid place-items-center">
                            @if($club['logo'])
                                <img src="{{ $club['logo'] }}" alt="" class="w-full h-full object-contain">
                            @else
                                <i class="bi bi-buildings text-muted-foreground"></i>
                            @endif
                        </span>
                        <p class="text-[13px] font-semibold text-foreground truncate flex-1 min-w-0">{{ $club['name'] }}</p>
                        <span class="text-[11px] font-bold px-2 py-0.5 rounded-full flex-shrink-0"
                              style="background: {{ $c }}1a; color: {{ $c }};">
                            {{ \App\Support\Cldr::number($club['count']) }}
                        </span>
                    </div>
                @endforeach

                {{-- Competing on their own. A real standing at a competition,
                     not a missing value — and counted for the same reason the
                     flagless are: these rows must add up to the head count. --}}
                @if($d['clubs_unattached'] ?? 0)
                    <div class="flex items-center gap-3">
                        <span class="w-9 h-9 flex-shrink-0 grid place-items-center rounded-xl border border-dashed border-border">
                            <i class="bi bi-person text-muted-foreground"></i>
                        </span>
                        <p class="text-[13px] font-semibold text-muted-foreground truncate flex-1 min-w-0">{{ __('events::events.waiting_unattached') }}</p>
                        <span class="text-[11px] font-bold px-2 py-0.5 rounded-full bg-muted text-muted-foreground flex-shrink-0">
                            {{ \App\Support\Cldr::number($d['clubs_unattached']) }}
                        </span>
                    </div>
                @endif
            </div>

            @if(count($clubs) > 8)
                <button type="button" @click="all = !all"
                        class="m-press mt-3 w-full py-2 rounded-xl bg-muted text-[12px] font-bold text-foreground">
                    <span x-show="!all">{{ __('events::events.waiting_clubs_more', ['n' => count($clubs) - 8]) }}</span>
                    <span x-show="all" x-cloak>{{ __('events::events.waiting_clubs_less') }}</span>
                </button>
            @endif
        </div>
    @endif
</div>
