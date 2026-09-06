{{--
    The ENTRY COVER, desktop — the first thing a shared link opens onto.

    The mobile cover (partials/cover) is measured off a 390×844 artboard: one
    column, the poster stacked above the type, a full-width CTA thumbed at the
    bottom edge. None of that composition survives a 1600px window — the poster
    strands itself in the middle of a wide field and the type line runs for
    twenty words. So this is a SEPARATE FILE, as the mobile/desktop split
    requires, and it re-composes the same elements editorially: the artwork held
    on the left, the identity and the two doors set beside it.

    Everything it inherits from the mobile cover is deliberate and not optional:

    - WHITE-LABELLED. The artwork and the name are the event's; nothing here
      says TAKEONE.
    - The EVENT's colour drives it — the dash, the entry button and the
      background wash are `$e['color']`, re-validated below because the value
      lands in a `style` attribute.
    - Nothing that names another person. Poster facts only; the payload is
      whatever `App\Events\Support\PublicEvent` chose to publish.
    - FAIL-SAFE BY CONSTRUCTION. `x-cloak` + teleport, so it paints only once
      Alpine is running: if the CDN is blocked or JS is off, the cover never
      appears and the visitor gets the page directly. A full-screen overlay a
      broken script could weld shut would make a public link unopenable.
    - State comes from the SHARED `eventCover()` (partials/cover-script) —
      shows once per tab, locks the page beneath, remembers the dismissal.

    THE ARTWORK IS THE BACKGROUND — full-bleed, `object-cover`, centred, and
    sharp. It is not a poster floating in the middle of a dark field: it fills
    the screen, and the type sits ON it. What makes that legible is the scrim,
    weighted to the bottom-left where the words are, so a bright photo and a
    dark one both carry white text. An event with no image falls back to its
    colour, so the cover is never blank.

    `object-cover` means a portrait poster is cropped left and right on a wide
    window. That is the trade the full-bleed treatment asks for and it is
    deliberate; the uncropped poster is on the page underneath.
--}}
@php
    use Illuminate\Support\Str;

    /* Re-validated at the point of use, like every other surface that paints an
       organiser-supplied colour. PublicEvent::color() already refuses anything
       but six hex digits; a partial should not be the one place that trusts its
       caller to have checked. */
    $dColor = preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($e['color'] ?? ''))
        ? $e['color']
        : '#1677FF';

    $dType   = trim($e['type'] ?? '');
    $dSport  = trim($e['sport_label'] ?? '');
    $dEyebrow = ($dSport && ! Str::contains(Str::lower($dType), Str::lower($dSport)))
        ? trim($dSport.' '.$dType)
        : $dType;

    $dWhen = trim("{$e['wday']} {$e['day']} {$e['mon']}");
    if (! empty($e['end_date']) && $e['end_date'] !== $e['date']) {
        $dWhen .= ' — '.\Illuminate\Support\Carbon::parse($e['end_date'])->format('j M');
    }
@endphp

@once
@push('styles')
<style>
    /* A still photograph fills the screen better with the faintest life in it.
       One transform, 28s, no repaint of anything else — and switched off
       entirely for anyone who asked for less motion. */
    @keyframes coverDrift {
        from { transform: scale(1.06) translate3d(0, 0, 0); }
        to   { transform: scale(1.14) translate3d(-1.2%, -1.4%, 0); }
    }
    /* The title. A compiled bundle has no CSS for `text-[44px]`/`xl:text-[52px]`,
       so the size and its one breakpoint live here. */
    .cover-title { font-size: 44px; line-height: 1.04; letter-spacing: -.015em; margin-top: 24px; }
    @media (min-width: 1280px) { .cover-title { font-size: 52px; } }

    .cover-drift {
        animation: coverDrift 28s ease-in-out infinite alternate;
        will-change: transform;
    }
    @media (prefers-reduced-motion: reduce) {
        .cover-drift { animation: none; transform: none; }
    }
</style>
@endpush
@endonce

<div x-data="eventCover()" x-cloak @reopen-cover.window="reopen()">
    <template x-teleport="body">
        <div x-show="open" x-cloak
             @keydown.escape.window="dismiss()"
             x-transition:leave="transition ease-in duration-300"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-x-0 top-0 z-[100] overflow-hidden text-white"
             {{-- `100dvh` rather than `inset-0` — see the note in the mobile
                  cover. A desktop window has no disappearing URL bar, but a
                  tablet in a mobile browser lands here too. --}}
             style="background: #070b14; height: 100vh; height: 100dvh;">

            {{-- ===== The artwork, as the screen =====
                 Full-bleed and sharp. A slow ken-burns drift keeps a still
                 photograph from feeling like a dead screenshot; it is a single
                 transform, and `prefers-reduced-motion` turns it off. --}}
            @if($e['photo'])
                <img src="{{ $e['photo'] }}" alt=""
                     class="absolute inset-0 w-full h-full object-cover cover-drift"
                     {{-- `center top`: a poster puts its name at the TOP, so when
                          `object-cover` has to crop, it must take the
                          slack off the bottom and never off the title. --}}
                     style="object-position: center top;">
            @endif

            {{-- Legibility. Weighted to the bottom-left, where the words are —
                 dark enough there to hold white type over a bright photo, and
                 clear enough at the top-right to leave the picture visible. The
                 event's colour is a whisper across the foot of the frame, so the
                 cover still belongs to the event without repainting the photo. --}}
            <div class="absolute inset-0" aria-hidden="true"
                 style="background:
                    linear-gradient(to top, rgba(7,11,20,.95) 0%, rgba(7,11,20,.86) 18%, rgba(7,11,20,.52) 46%, rgba(7,11,20,.16) 72%, rgba(7,11,20,.30) 100%),
                    linear-gradient(105deg, rgba(7,11,20,.72) 0%, rgba(7,11,20,.30) 38%, transparent 66%);"></div>
            <div class="absolute inset-x-0 bottom-0" aria-hidden="true" style="height:33%;"
                 style="background: linear-gradient(to top, {{ $dColor }}26 0%, transparent 100%);"></div>

            {{-- A hairline of the event's colour along the very top: the one
                 piece of chrome, and it belongs to the event. --}}
            <div class="absolute inset-x-0 top-0 h-[3px]" aria-hidden="true"
                 style="background: linear-gradient(90deg, {{ $dColor }}, {{ $dColor }}33 70%, transparent);"></div>

            {{-- ===== The composition =====
                 The type is anchored to the FOOT of the frame, not centred in
                 it: the artwork is the subject, and the words are the caption
                 laid across its base. A measure of ~34 characters keeps the
                 title to two or three lines on any window instead of running the
                 full width of a 27-inch screen. --}}
            <div class="relative z-10 h-full w-full overflow-y-auto flex">
                <div class="min-h-full w-full mx-auto flex flex-col justify-end"
                     style="max-width:1180px; padding:64px 40px 56px;">

                    <div style="max-width:640px;">

                        <div class="flex items-center gap-3.5 m-in" style="animation-delay:.06s">
                            <span class="rounded-full flex-shrink-0"
                                  style="width:46px; height:4px; background: {{ $dColor }};"></span>
                            @if($dEyebrow)
                                <span class="font-bold uppercase text-white/85"
                                      style="font-size:11px; letter-spacing:.16em;">
                                    {{ $dEyebrow }}
                                </span>
                            @endif
                        </div>

                        <h1 class="font-bold m-in cover-title"
                            style="animation-delay:.12s; text-shadow: 0 2px 30px rgba(7,11,20,.55);">
                            {{ $e['title'] }}
                        </h1>

                        {{-- When and where, as two facts rather than one run-on
                             line: a wide column has the room to separate them,
                             and the venue is the thing people re-read. --}}
                        <div class="space-y-2.5 m-in" style="animation-delay:.18s; margin-top:28px;">
                            <p class="flex items-center gap-3 text-white/80" style="font-size:14.5px;">
                                <i class="bi bi-calendar-event flex-shrink-0" style="color: {{ $dColor }}"></i>
                                {{ $dWhen }}@if(!empty($e['time']) && $e['time'] !== 'TBA') · {{ $e['time'] }}@endif
                            </p>
                            @if($e['location'] && $e['location'] !== 'TBA')
                                <p class="flex items-center gap-3 text-white/80" style="font-size:14.5px;">
                                    <i class="bi bi-geo-alt flex-shrink-0" style="color: {{ $dColor }}"></i>
                                    {{ $e['location'] }}
                                </p>
                            @endif
                            @if(!empty($e['host']))
                                <p class="flex items-center gap-3 text-white/60" style="font-size:14.5px;">
                                    <i class="bi bi-building flex-shrink-0" style="color: {{ $dColor }}"></i>
                                    {{ $e['host'] }}
                                </p>
                            @endif
                        </div>

                        {{-- ===== Language, then the two doors =====
                             The same shared block the mobile cover uses; only
                             the width it is allowed is different, because two
                             square tiles left unbounded on a wide window would
                             be the size of a poster. --}}
                        <div style="max-width:420px;">
                            @include('entry.public.partials.cover-actions')
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>

@include('entry.public.partials.cover-script')
