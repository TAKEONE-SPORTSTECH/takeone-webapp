@props([
    /* Has the entry fee been settled. Two states only, by request: green or
       grey — an organiser wants "is this one paid for", and the receipt itself
       is a tap away on the verification sheet. */
    'paid' => false,

    /* Where the weight came from, which is three different facts:
         'none'     — no weight anywhere, so nobody can be drawn against them
         'self'     — a weight the athlete put on file themselves (a claim)
         'official' — a weigh-in official put them on the scale and signed it */
    'weigh' => 'none',
])

{{--
    ===== The two readiness badges that STRADDLE an entrant card's top edge =====

    Asked for on 2026-09-04: reading down the roster, an organiser must be able
    to see which entries are paid for and which have a weight, without opening
    anything — and (second pass) each badge says its state IN WORDS, with half
    of the pill above the card's edge and half below it.

    Straddling is why this component is absolutely positioned by its CALLER's
    wrapper and not by the card: <x-entrant-card> clips its own overflow (it has
    to — the portrait and the gender rail are squared boxes inside a rounded
    card), so anything hung on the card itself is cut off at the edge. The
    wrapper around the card is not clipped, so the pill can sit half outside it.

    ⚠️ Sizing, offset, radius and colour are INLINE styles, not Tailwind
    utilities. The production CSS bundle is prebuilt (CLAUDE.md / memory:
    "Tailwind bundle is PREBUILT"), and a class nobody used before —
    `start-1/2`, `-translate-x-1/2`, `text-[9px]` — has no rule in
    `public/build` and silently renders as nothing. The colours are on palette:
    the same grey, amber and green the <x-event-status-chip> states use.

    They sit on the side of the card the PORTRAIT is not (asked for on
    2026-09-04): the picture leads the card — left in English, right in Arabic —
    so the badges take the trailing edge and the two never crowd each other.
    That is `inset-inline-end`, a logical property, so the side follows the
    document's direction with no RTL variant to keep in step.
--}}

@php
    $tones = [
        'none' => ['bg' => '#e5e7eb', 'fg' => '#4b5563', 'ring' => '#ffffff'],  // gray-200 / gray-600
        'self' => ['bg' => '#f59e0b', 'fg' => '#ffffff', 'ring' => '#ffffff'],  // amber-500
        'done' => ['bg' => '#16a34a', 'fg' => '#ffffff', 'ring' => '#ffffff'],  // green-600
    ];

    $badges = [
        [
            'tone' => $paid ? 'done' : 'none',
            'icon' => $paid ? 'bi-cash-coin' : 'bi-cash',
            'text' => $paid ? __('personal.event_badge_paid') : __('personal.event_badge_unpaid'),
            'title' => __('personal.event_show_chip_paid').' — '.($paid
                ? __('personal.event_show_chip_verified')
                : __('personal.event_show_chip_not_yet')),
        ],
        [
            'tone' => match ($weigh) { 'official' => 'done', 'self' => 'self', default => 'none' },
            'icon' => $weigh === 'none' ? 'bi-speedometer' : 'bi-speedometer2',
            'text' => match ($weigh) {
                'official' => __('personal.event_badge_weighed'),
                'self' => __('personal.event_badge_weight_declared'),
                default => __('personal.event_badge_no_weight'),
            },
            'title' => __('personal.event_show_chip_weighed').' — '.match ($weigh) {
                'official' => __('personal.event_show_chip_verified'),
                'self' => __('personal.event_show_chip_unverified'),
                default => __('personal.event_show_chip_not_yet'),
            },
        ],
    ];
@endphp

{{-- Half above the card's edge, half below it: a 24px badge offset -12px,
     inset from the card's trailing corner rather than centred. The
     white outline and the soft drop shadow lift it off the card where it
     crosses the border. `border-radius:8px` — ROUNDED, not a pill (asked for
     on 2026-09-04): a badge with the same corner as the cards and sheets around
     it reads as part of the same product, and a full pill did not. The list it
     sits in is spaced `space-y-5` (and `mt-6` from the search box) for exactly
     this overhang.

     Both badges are the SAME SIZE whatever they say (asked for on 2026-09-04):
     a `min-width` with centred content, rather than a fixed width, so a longer
     translation grows the pair instead of being clipped. --}}
<span class="absolute z-10 inline-flex items-center"
      style="top:-12px; inset-inline-end:14px; gap:5px;">
    @foreach($badges as $b)
        @php $t = $tones[$b['tone']]; @endphp
        <span title="{{ $b['title'] }}"
              style="display:inline-flex;align-items:center;justify-content:center;gap:4px;
                     height:24px;min-width:104px;padding:0 10px;
                     border-radius:8px;background:{{ $t['bg'] }};color:{{ $t['fg'] }};
                     box-shadow:0 0 0 2px {{ $t['ring'] }}, 0 1px 3px rgba(15,23,42,.14);
                     font-size:11px;font-weight:800;line-height:1;white-space:nowrap;">
            <i class="bi {{ $b['icon'] }}" style="font-size:11px;"></i>{{ $b['text'] }}
        </span>
    @endforeach
</span>
