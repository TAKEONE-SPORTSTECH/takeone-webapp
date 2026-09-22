{{--
    The running order — a competition day, part by part, as the visitor reads it.

    It sits INSIDE the day row of the event's "How it runs" spine and replaces
    the generic bullet list there ("Preliminaries · Quarter-finals · 16 weight
    classes") the moment an organiser has actually written the day down. Same
    12px type, same muted palette, same rhythm as the bullets it stands in for —
    a reader should not notice a new component, only a better answer.

    What it adds that a bullet list cannot: it says WHICH PART IS ON NOW. Parts
    that have finished collapse behind one line, the live part is set in the
    event's own colour with a NOW pill, and everything still to come keeps its
    clock. A day nobody is watching yet reads as a plan; the same markup during
    the event reads as a status board.

    ⚠️ Status arrives already decided, from `App\Events\Support\RunSheet` — this
    draws it and never works it out. That matters because the clock is only the
    first answer: a stage truly ends when its last bout is scored, and when the
    scoreboard feeds that in, this component does not change at all.

    Standalone: it owns its own styles, its own collapse state and its own
    marquee. It needs nothing from the page but a place to sit.

    Usage:
        x-event-run-order :rows="$ph['run_slots']" :color="$e['color']"
                          :division-link="$divisionLink"

    A DIVISION row is a door to that division's draw when `divisionLink` is
    given; a ceremony and a break never are, because there is nothing behind
    them. Asked for on 2026-09-18.
--}}
@props([
    'rows' => [],
    'color' => '#7c3aed',

    /* ── WHERE A DIVISION ROW GOES ────────────────────────────────────────
       A base address with `?division=` (or `?category=`) already on the end —
       the row appends its own id. It is the SAME `$divisionLink` the division
       chips further up this card are given, so a group opens the same board
       whether a reader taps its name in the list of divisions or its line in
       the running order. One convention, one destination.

       Null leaves every row as plain text, which is what a day with no draw
       out should be: a line that looks like a link and lands on a padlock is
       worse than a line that never offered. */
    'divisionLink' => null,
])
@php
    // Organiser-supplied and headed for a style attribute — whitelisted here,
    // the same guard <x-event-section-band> uses.
    $rc = preg_match('/^#[0-9a-fA-F]{3,8}$/', (string) $color) ? $color : '#7c3aed';

    $rows = array_values(array_filter((array) $rows, 'is_array'));

    $doneCount = 0;
    foreach ($rows as $r) {
        if (($r['status'] ?? 'upcoming') === 'done') { $doneCount++; }
    }

    $clock = function (int $minutes) {
        $m = (($minutes % 1440) + 1440) % 1440;
        return \App\Support\Cldr::time(\Carbon\Carbon::today()->setTime(intdiv($m, 60), $m % 60));
    };
@endphp

{{-- The no-wrap marquee, shared with both organiser boards. --}}
@include('partials.marquee-style')

{{-- The SAME pulse the spine above uses for "this is where the event is right
     now" — one definition, included @once, so the day's NOW badge and the row
     that is actually on breathe together instead of blinking at each other on
     two different clocks (CLAUDE.md → Shared Stays Shared). --}}
@include('partials.event-now-glow')

@if(! empty($rows))
    <div class="mt-3" x-data="{ showDone: false }" style="--ro: {{ $rc }}; --ev-now: {{ $rc }};">

        @if($doneCount > 0)
            {{-- The finished half of a day is a record, not clutter — but it is
                 not what somebody standing in the hall opened the page for. One
                 line, and it opens. --}}
            <button type="button" @click="showDone = !showDone"
                    class="w-full flex items-center gap-2 py-1.5 text-start">
                <i class="bi bi-check-circle-fill text-[11px] text-green-600"></i>
                <span class="text-[12px] font-bold text-green-700">{{ trans_choice('events.run_order_finished', $doneCount, ['count' => $doneCount]) }}</span>
                <span class="text-[11px] font-semibold text-muted-foreground" x-text="showDone ? @js(__('events.run_order_hide')) : @js(__('events.run_order_show'))"></span>
            </button>
        @endif

        @foreach($rows as $row)
            @php
                $status = $row['status'] ?? 'upcoming';
                $isNow = $status === 'now';
                $isDone = $status === 'done';
                $kind = $row['kind'] ?? 'division';

                /* ===== IS THIS ROW A DOOR? =====
                   Only a DIVISION, only when it still has the group behind it,
                   and only when the host gave somewhere to go. A ceremony and a
                   break are not doors — there is no board behind them — and an
                   orphaned division (its group deleted underneath the slot) has
                   nothing left to open.

                   The whole row is the target rather than the name alone: it is
                   a 12px line on a phone, and a tap aimed at four words between
                   a clock and a count is a tap that misses. */
                $rowHref = ($divisionLink && $kind === 'division' && ! empty($row['category_id']))
                    ? $divisionLink.$row['category_id']
                    : null;

                $rowTag = $rowHref ? 'a' : 'div';
            @endphp

            {{-- ⚠️ ONE `class` attribute. The link's own classes go INSIDE the
                 @class array, not beside it: two class attributes on one tag
                 means the browser keeps the first and silently drops the
                 second, and the row loses its whole layout. --}}
            <{{ $rowTag }} @if($rowHref) href="{{ $rowHref }}" @endif
                 @if($isDone) x-show="showDone" x-cloak x-transition.opacity @endif
                 @class([
                     'flex items-start gap-2',
                     'py-1.5' => ! $isNow,
                     'm-press no-underline text-inherit' => (bool) $rowHref,
                 ])
                 @if($isNow) style="padding:9px 10px; margin:3px -10px; border-radius:10px; background: {{ $rc }}0f; border:1px solid {{ $rc }}2e;" @endif>

                {{-- The marker carries the state without a word: a tick behind
                     you, a live dot where you are, and a CLOCK ahead — the same
                     clock, at the same size and in the same colour, that the
                     `Play` line above this list already uses. A bullet said
                     "list item"; a clock says "a time to be somewhere", which
                     is what every one of these rows actually is. --}}
                <span class="flex-none w-3.5 text-center text-[10px] leading-relaxed"
                      @if($isDone) style="color:#10b981;"
                      @elseif($isNow) style="color: {{ $rc }};" @endif>
                    @if($isDone)
                        <i class="bi bi-check-lg"></i>
                    @elseif($isNow)
                        <span class="ev-now-dot inline-block w-1.5 h-1.5 rounded-full align-middle" style="background: {{ $rc }};"></span>
                    @else
                        <i class="bi bi-clock text-[11px]" style="color: {{ $rc }};"></i>
                    @endif
                </span>

                <span class="flex-none text-[11.5px] leading-relaxed tabular-nums"
                      @if($isNow) style="width:58px; font-weight:800; color: {{ $rc }};"
                      @elseif($isDone) style="width:58px; font-weight:600; color:#9ca3af;"
                      @else style="width:58px; font-weight:600;" @endif
                      @class(['text-muted-foreground' => ! $isNow && ! $isDone])>{{ $clock((int) ($row['starts_at'] ?? 0)) }}</span>

                <span @class(['mq min-w-0 flex-1 text-[12px] leading-snug', 'ev-now-text' => $isNow])
                      @if($isNow) style="font-weight:800; color: {{ $rc }};"
                      @elseif($isDone) style="color:#9ca3af;"
                      @elseif($kind !== 'division') style="font-weight:600;" @endif
                      @class(['text-muted-foreground' => ! $isNow && ! $isDone])>
                    <span dir="auto">{{ $row['name'] ?? '' }}</span>
                </span>

                @if($isNow)
                    <span class="ev-now flex-none ms-auto px-1.5 py-0.5 rounded-full text-[9px] font-black text-white tracking-wide"
                          style="background: {{ $rc }};">{{ __('personal.event_show_now') }}</span>
                @elseif(! $isDone && $kind === 'break')
                    <span class="flex-none ms-auto px-1.5 py-0.5 rounded-full text-[9.5px] font-bold bg-muted text-muted-foreground">{{ __('events.run_order_break') }}</span>
                @elseif(! $isDone && $kind === 'ceremony')
                    <span class="flex-none ms-auto px-1.5 py-0.5 rounded-full text-[9.5px] font-bold bg-muted text-muted-foreground">{{ __('events.run_order_medals') }}</span>
                @elseif(! $isDone && ! empty($row['entrants']))
                    <span class="flex-none ms-auto px-1.5 py-0.5 rounded-full text-[9.5px] font-bold bg-muted text-muted-foreground">{{ $row['entrants'] }}</span>
                @endif

                {{-- The bracket glyph, quietly, on a row that opens one. The
                     same mark the division chips above this list carry, at the
                     same opacity — so "this is a door" is one thing learnt
                     once. Rotated for a draw (Design Rule #7). --}}
                @if($rowHref)
                    <i class="bi bi-diagram-3 bracket-icon flex-none text-[10px] opacity-40 mt-0.5"
                       @if(! $isNow && ! $isDone) style="color: {{ $rc }};" @endif></i>
                @endif
            </{{ $rowTag }}>
        @endforeach
    </div>
@endif
