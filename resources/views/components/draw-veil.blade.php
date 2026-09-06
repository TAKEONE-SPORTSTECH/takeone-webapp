@props([
    'message',            // the sentence that says when the draw opens
    'color' => '#7c3aed', // the event's own colour
])

{{--
    The draw, withheld.

    Shown in place of the readable bout list when club_events.draw_reveal is
    holding the bracket back. Deliberately says WHEN rather than nothing: a
    member who cannot tell "not published yet" from "nobody has entered" phones
    the organiser, and that call is the thing the setting exists to prevent.

    Standalone: markup and nothing else — no state, no request, no page glue.
--}}

@php
    $c = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $color) ? $color : '#7c3aed';
@endphp

<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 text-center relative overflow-hidden">
    {{-- one soft wash of the subject's colour, so the card belongs to this event --}}
    {{-- Inline opacity, not an arbitrary Tailwind class: the CSS bundle is
         prebuilt, and a utility nobody has used before has no rule in it. --}}
    <div class="absolute -right-10 -top-12 w-40 h-40 rounded-full"
         style="background: {{ $c }}; opacity: .07;"></div>

    <div class="relative">
        <span class="w-14 h-14 rounded-2xl grid place-items-center mx-auto mb-3 text-white shadow-sm"
              style="background: linear-gradient(150deg, {{ $c }}, {{ $c }}b0);">
            <i class="bi bi-lock-fill text-xl"></i>
        </span>
        <p class="text-sm font-bold text-foreground">{{ $message }}</p>
        <p class="text-[11.5px] text-muted-foreground mt-1">{{ __('events.draw_hidden_sub') }}</p>
    </div>
</div>
