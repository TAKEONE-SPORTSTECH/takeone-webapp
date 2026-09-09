@props([
    'color' => '#7c6bf5',   // the event's own colour
    'eyebrow' => null,      // "JIU JITSU CHAMPIONSHIP" — the classification, in tracked caps
    'title' => '',
    'owner' => null,        // the host club
    'chips' => [],          // [[icon, label], …] — the few facts that decide whether to read on
])

{{--
    The POSTER BAND — an event's own header, on every surface that shows one.

    The design file's header (drafts/upper header.png), kept verbatim at the
    user's request: the event's colour taken DOWN towards navy, a dash and the
    classification in tracked caps, then the big title, then whose it is, then
    the facts that decide whether to read on.

    This is the ONE band that departs from Design Rule #6's hero band, and
    deliberately — it is a POSTER, not a screen inside the app. Every other
    page, the four section pages included, keeps the standard band.

    ONE COPY, because it is worn by two surfaces now: `/e/{uuid}`, where a
    stranger meets the competition, and `/me/events/{uuid}`, where a member and
    the organiser read the same event (asked for 2026-09-08 — "one event, one
    face, whichever door was used"). Extracted from entry/public/mobile rather
    than copied into the member page: two copies of a header is how the two
    surfaces drift apart, which is the whole reason this work exists
    (CLAUDE.md → *Shared Stays Shared*).

    What is NOT shared is the CONTROL ROW, and that is the point of the slot: a
    stranger's poster carries reopen-cover · gear · share · account, while the
    member page carries back · console · open-public-page · QR · share. Same
    band, different business — so the caller supplies it.

        <x-event-poster-band :color="$e['color']" :eyebrow="$eyebrow"
                             :title="$e['title']" :owner="$e['club']" :chips="$chips">
            <x-slot:controls> … </x-slot:controls>
        </x-event-poster-band>
--}}

<header class="relative overflow-hidden text-white"
        style="padding: 22px 24px 72px; background: {{ \App\Support\Palette::eventBand($color) }};">

    <div class="absolute rounded-full" style="right:-56px; top:-56px; width:190px; height:190px; background:rgba(255,255,255,.07);"></div>
    <div class="absolute rounded-full" style="right:22px; bottom:26px; width:96px; height:96px; background:rgba(255,255,255,.06);"></div>

    {{-- Control row: the dash and the classification on the leading edge, the
         caller's controls on the trailing one. --}}
    <div class="relative flex items-center justify-between">
        <span class="flex items-center" style="gap:10px;">
            <span class="flex-none" style="width:38px; height:3px; border-radius:2px; background:rgba(255,255,255,.85);"></span>
            @if($eyebrow)
                <span class="uppercase" style="font-size:11px; font-weight:600; letter-spacing:.2em; color:rgba(255,255,255,.85);">{{ $eyebrow }}</span>
            @endif
        </span>

        {{-- 12px between the controls. It went 8 → 12 → 18 and 18 was too far:
             at 40px round each they stopped reading as one cluster belonging to
             this header and started looking like loose buttons. --}}
        <span class="flex items-center flex-none" style="gap:12px;">
            {{ $controls ?? '' }}
        </span>
    </div>

    {{-- An optional state banner, above the identity block — "this event was
         cancelled", and nothing else so far. Design Rule #6 puts it here on the
         desktop band too. The public poster passes none. --}}
    {{ $banner ?? '' }}

    <h1 class="relative" style="margin:26px 0 0; font-size:27px; line-height:1.18; font-weight:700; letter-spacing:-.01em;">{{ $title }}</h1>

    @if($owner)
        <p class="relative flex items-center" style="margin:10px 0 0; gap:8px; font-size:13px; color:rgba(255,255,255,.82);">
            <i class="bi bi-building"></i>{{ $owner }}
        </p>
    @endif

    @if($chips)
        <div class="relative flex flex-wrap" style="gap:6px; margin-top:14px;">
            @foreach($chips as [$ic, $label])
                <span class="inline-flex items-center uppercase"
                      style="gap:6px; padding:5px 11px; border-radius:999px; font-size:10px; font-weight:600; letter-spacing:.08em; background:rgba(255,255,255,.16); border:1px solid rgba(255,255,255,.22);">
                    <i class="bi {{ $ic }}"></i>{{ $label }}
                </span>
            @endforeach
        </div>
    @endif
</header>
