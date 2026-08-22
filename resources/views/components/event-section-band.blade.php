{{--
    Event card section band — the divider between sections of the event detail
    card (About / How the event runs / Divisions / Requirements / Location), and
    the prize band itself.

    One object, two modes:
      • heading  — icon + section title.
      • value    — icon + eyebrow + a big value line (what the prize band was).
                   Pass `value` to get this mode.

    Full-bleed by design: render it as a direct child of the card, OUTSIDE the
    padded content wrapper, so it meets both edges. The card's own
    `overflow-hidden` clips it into the rounded corners.

    Self-contained: no page script, no shared state, no assumptions about its
    surroundings beyond being inside a card. Safe to drop into any event view.

    Usage:
      <x-event-section-band :color="$e['color']" icon="bi-info-circle"
                            :title="__('personal.event_show_about')" />

      <x-event-section-band :color="$e['color']" icon="bi-award-fill"
                            :title="__('personal.event_show_prize_pool')"
                            :value="$e['prize']" />
--}}
@props([
    'color' => '#7c3aed',
    'icon' => 'bi-circle',
    'title' => '',
    'value' => null,
])
@php
    // $color and $icon are organiser-supplied and land in a style attribute and a
    // class name — whitelist them rather than trusting whatever was typed into
    // the event form. Same guard the explore event cards use.
    $bandColor = preg_match('/^#[0-9a-fA-F]{3,8}$/', (string) $color) ? $color : '#7c3aed';
    $bandIcon = preg_match('/^bi-[a-z0-9-]+$/i', (string) $icon) ? $icon : 'bi-circle';
@endphp
<div {{ $attributes->merge(['class' => 'px-5 sm:px-6 py-4 text-white relative overflow-hidden']) }}
     style="background: linear-gradient(135deg, {{ $bandColor }}, #1f2937);">
    <div class="absolute -right-8 -top-8 w-28 h-28 rounded-full bg-white/10"></div>
    <div class="relative flex items-center gap-3">
        <i class="{{ \App\Support\Icon::bi($bandIcon, 'text-2xl text-white/90 flex-shrink-0') }}"></i>
        <div class="min-w-0">
            @if($value !== null)
                <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-white/70">{{ $title }}</p>
                <p class="text-base font-black leading-tight mt-0.5">{{ $value }}</p>
            @else
                <p class="text-[13px] font-black uppercase tracking-[0.16em] leading-none">{{ $title }}</p>
            @endif
        </div>
    </div>
</div>
