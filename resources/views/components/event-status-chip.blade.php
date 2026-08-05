@props([
    // 'none'     — hasn't happened
    // 'claimed'  — happened, but no official has confirmed it (amber)
    // 'verified' — confirmed by an official (green)
    'state' => 'none',
    'icon',               // Bootstrap icon for none/claimed
    'doneIcon' => null,   // filled variant once verified (falls back to $icon)
    'label',
])

@php
    $tone = match ($state) {
        'verified' => 'bg-green-50 text-green-600',
        'claimed' => 'bg-amber-50 text-amber-600',
        default => 'bg-muted text-muted-foreground',
    };

    // The icon carries the state too, so it still reads without colour: a tick
    // once verified, an hourglass while it waits on an official.
    $mark = match ($state) {
        'verified' => $doneIcon ?: $icon,
        'claimed' => 'bi-hourglass-split',
        default => $icon,
    };

    $title = match ($state) {
        'verified' => $label.' — '.__('personal.event_show_chip_verified'),
        'claimed' => $label.' — '.__('personal.event_show_chip_unverified'),
        default => $label.' — '.__('personal.event_show_chip_not_yet'),
    };
@endphp

{{--
    One step of an entry's readiness — enrolled, paid, weighed in.

    Three states, not two: an official confirming a payment is a different fact
    from a competitor saying they paid, and an organiser needs to see which is
    which before the draw. Shown even when nothing has happened, so the gap is
    visible rather than absent.
--}}
<span title="{{ $title }}"
      class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-[0.6rem] font-extrabold {{ $tone }}">
    <i class="bi {{ $mark }}"></i>
    <span>{{ $label }}</span>
</span>
