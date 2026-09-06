{{--
    The foot of the public event surface — it names the ORGANISER, never the
    platform. Shared by entry/layout (the poster) and entry/shell (the sealed
    admin surface) so both feet stay the same foot.

    Expects $skin (the sealed shell) or $e (the public poster) — either carries
    `host` and `host_logo`; the skin wins where both are in scope.
--}}
@php
    $__evHost = $skin['host'] ?? ($e['host'] ?? null);
    $__evHostLogo = $skin['host_logo'] ?? ($e['host_logo'] ?? null);
@endphp
    <footer class="ev-app ev-app-bottom" style="padding:34px 24px 30px; text-align:center;">
        {{-- Drawn on the GROUND, not on a card, and the same footer serves a
             black cover and a light section page — so it takes its colours from
             the `--on-pg-*` tokens the page sets with its ground, never the card
             ink (var(--ink-2)). --}}
        <div style="height:1px; max-width:220px; margin:0 auto 22px; background:linear-gradient(90deg, transparent, var(--on-pg-line), transparent);"></div>

        @if($__evHostLogo)
            {{-- A logo is a transparent PNG of its own shape: a sizing box and
                 object-contain, never a filled tile. The design draws a white
                 disc here, but it draws it for a club with no mark on file —
                 Design Rule #5 is STRICT and outranks it where a mark exists. --}}
            <span class="mx-auto block" style="width:46px; height:46px;">
                <img src="{{ $__evHostLogo }}" alt="" class="w-full h-full object-contain">
            </span>
        @elseif($__evHost)
            {{-- No mark on file: the design's disc, carrying the club's initials
                 in the event's colour. --}}
            <span class="mx-auto grid place-items-center"
                  style="width:46px; height:46px; border-radius:50%; background:#fff;
                         box-shadow:0 6px 18px rgba(30,44,79,.1); font-size:14px; font-weight:700;
                         color: var(--ev);">{{ Str::of($__evHost)->explode(' ')->take(2)->map(fn ($w) => Str::upper(Str::substr($w, 0, 1)))->implode('') }}</span>
        @endif

        @if($__evHost)
            <p class="uppercase" style="margin:10px 0 0; font-size:9.5px; font-weight:700; letter-spacing:.18em; color: var(--on-pg);">{{ __('events.public_organised_by') }}</p>
            <p style="margin:2px 0 0; font-size:12px; font-weight:600; color: var(--on-pg);">{{ $__evHost }}</p>
        @endif
    </footer>
