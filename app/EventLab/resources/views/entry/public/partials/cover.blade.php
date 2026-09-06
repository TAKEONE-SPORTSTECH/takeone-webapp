{{--
    The ENTRY COVER — the first thing a shared link opens onto.

    Built to `drafts/Entry Cover page .png`, measured rather than eyeballed. The
    artboard in that file is 390×844 — an iPhone viewport — so the design is a
    FULL-BLEED screen, not a card on a page, and every number below is the
    measurement taken off it:

        gutters            24px both sides
        accent dash        38 × 4, radius full, the EVENT's colour
        eyebrow            ~10.5px, uppercase, tracked, 14px right of the dash
        title              21px / 25px line-height, bold, two lines in the design
        meta line          ~12.5px, muted, 11px under the title
        CTA                full-width, 52px tall, radius 12, the EVENT's colour,
                           21px under the meta, 32px clear of the bottom edge

    Why a cover exists at all: somebody is sent a competition in a WhatsApp
    message and taps it with no idea what they are about to see. Landing straight
    on a scrolling page of facts asks them to read before they have been told
    what this IS. The cover answers that in one screen — the artwork, the name,
    the day, the hall — and then gets out of the way.

    Rules it inherits, none of them optional:

    - WHITE-LABELLED. The artwork and the name are the event's. Nothing here
      says TAKEONE (`entry/layout.blade.php`).
    - The EVENT's colour drives it. The dash and the CTA are `$e['color']`;
      the design's own red is Victory Academy's, not a constant to hard-code.
    - Nothing that names another person. A head count is a poster fact; who is
      entered is not. This partial reads only the keys
      `App\Events\Support\PublicEvent` chose to publish.

    THE ARTWORK IS THE BACKGROUND, and it is the organiser's upload — a portrait
    poster in the design, but just as likely a landscape gallery photo here. It
    is laid in with `object-cover` and centred, so either crops sensibly, and the
    bottom scrim is what makes the type legible over whatever arrives. An event
    with no image at all falls back to its colour, so the cover is never blank.

    FAIL-SAFE BY CONSTRUCTION — the point to preserve if this is ever edited.
    The cover is `x-cloak`ed and teleported, so it paints only once Alpine is
    running. If the CDN is blocked, Alpine fails, or JS is off entirely, the
    cover never appears and the visitor gets the poster page directly. A
    full-screen overlay that a broken script could leave welded shut would make
    a public link unopenable — so the failure mode is "no cover", never "no
    page" (RULE #1, and the same reasoning as *Unattended Devices Must Always
    Recover*).

    Teleported to <body> because the layout's <main> carries `.mobile-stagger`,
    which leaves a transform on each child — and a transformed ancestor becomes
    the containing block for `position: fixed`, so an unteleported `inset-0`
    would size itself to a wrapper instead of the viewport.
--}}
@php
    /* Belt and braces on the one value that lands in a `style` attribute.
       `App\Events\Support\PublicEvent::color()` already refuses anything but
       six hex digits, so nothing can reach here to be injected — but every
       other surface that paints an organiser-supplied colour re-checks it at
       the point of use (`event-public-link`, `event-section-band`,
       `event-cover`), and a partial should not be the one place that trusts its
       caller to have done it. */
    $coverColor = preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($e['color'] ?? ''))
        ? $e['color']
        : '#7c3aed';

    /* The eyebrow is the classification, the way the design says it:
       "JIU JITSU CHAMPIONSHIP" — the sport, then what kind of event it is.
       An event type often NAMES its sport already ("Karate Championship"), so
       prefixing blindly gives "Karate Karate Championship". Only add the sport
       when the type has not said it. */
    $coverType  = trim($e['type'] ?? '');
    $coverSport = trim($e['sport_label'] ?? '');
    $coverEyebrow = ($coverSport && ! Str::contains(Str::lower($coverType), Str::lower($coverSport)))
        ? trim($coverSport . ' ' . $coverType)
        : $coverType;

    /* "Fri 18 Sep · Isa Sports City, Hall 2" — one line, day then place. A
       competition running over more than one day says so as a range. */
    $coverWhen = trim("{$e['wday']} {$e['day']} {$e['mon']}");
    if (($e['end_date'] ?? null) && $e['end_date'] !== ($e['date'] ?? null)) {
        $coverWhen .= ' — ' . \Illuminate\Support\Carbon::parse($e['end_date'])->format('j M');
    }
@endphp
<div x-data="eventCover()" x-cloak @reopen-cover.window="reopen()">
    <template x-teleport="body">
        <div x-show="open" x-cloak
             @keydown.escape.window="dismiss()"
             x-transition:leave="transition ease-in duration-[420ms]"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="ev-app-fixed fixed inset-x-0 top-0 z-[80] overflow-hidden text-white select-none"
             {{-- NOT `inset-0`. On a phone that resolves against the LAYOUT
                  viewport, which is the tall one — the height the page has when
                  the URL bar is hidden. With the bar showing, the overlay is
                  taller than what you can actually see and its top slides up
                  behind the browser chrome, which ate the top of the artwork.
                  `100dvh` is the DYNAMIC viewport: exactly the visible area,
                  re-measured as the bar shows and hides. `100vh` first, so a
                  browser too old for dvh still gets a full screen. --}}
             style="background: #070b14; height: 100vh; height: 100dvh;">

            {{-- ===== The ground behind the poster =====

                 A competition poster is a composed thing — its own title, its
                 own margins. It was shown WHOLE and centred for that reason,
                 with a blurred copy carried to the edges behind it — and that is
                 no longer what this is. At the user's instruction (2026-09-02)
                 THE ARTWORK IS THE SCREEN: full-bleed, `object-cover`, centred
                 and sharp, with the type laid across its base.

                 The trade that buys: `object-cover` crops to fill, so a poster
                 whose own title runs close to its edges can lose a little of it.
                 A portrait poster in a portrait phone barely crops at all, which
                 is the common case here; the uncropped artwork is on the page
                 underneath either way. --}}
            @if($e['photo'])
                <img src="{{ $e['photo'] }}" alt=""
                     class="absolute inset-0 w-full h-full object-cover"
                     {{-- `center top`: a poster puts its name at the TOP, so when
                          `object-cover` has to crop, it must take the
                          slack off the bottom and never off the title. --}}
                     style="object-position: center top;">
            @else
                <div class="absolute inset-0"
                     style="background:
                        radial-gradient(120% 80% at 82% -10%, {{ $coverColor }}cc 0%, {{ $coverColor }}44 42%, transparent 72%),
                        radial-gradient(90% 70% at -15% 42%, {{ $coverColor }}66 0%, transparent 68%);"></div>
                <div class="absolute inset-0 opacity-[.55]"
                     style="background: linear-gradient(118deg, transparent 34%, rgba(255,255,255,.06) 46%, transparent 58%);"></div>
                <div class="absolute -right-16 -top-20 w-72 h-72 rounded-full bg-white/10"></div>
                <div class="absolute -left-12 bottom-28 w-48 h-48 rounded-full bg-white/[.06]"></div>
            @endif

            {{-- The scrim: clear at the top, near-opaque by the bottom, so the
                 type block sits on a known ground whatever was uploaded. --}}
            <div class="absolute inset-0"
                 style="background: linear-gradient(180deg, rgba(7,11,20,0) 0%, rgba(7,11,20,0) 46%, rgba(7,11,20,.55) 70%, rgba(7,11,20,.90) 88%, rgba(7,11,20,.97) 100%);"></div>

            {{-- One tap anywhere opens the page — the whole cover is the
                 control, with the CTA below as the visible affordance. It is a
                 real <button>, so it is reachable by keyboard and announced as
                 what it is; the CTA inside is therefore a <span>, never a
                 nested button. --}}
            {{-- Tap anywhere still opens the page, but this is a DIV now, not one
                 screen-sized <button>: the cover carries a real <a> to the entry
                 form, and an anchor may not nest inside a button. The two CTAs
                 below are the focusable controls, which is better for a keyboard
                 than one unlabelled giant target ever was. --}}
            <div @click="dismiss()"
                    x-transition:leave="transition ease-in duration-300"
                    x-transition:leave-start="opacity-100 scale-100"
                    x-transition:leave-end="opacity-0 scale-[1.03]"
                    class="relative z-10 w-full h-full flex flex-col text-start
                           px-6 pt-[max(1.5rem,env(safe-area-inset-top))] pb-[max(2rem,env(safe-area-inset-bottom))]">

                {{-- ===== The poster, whole and centred =====
                     `flex-1 min-h-0` gives it whatever room the type block
                     leaves, and `object-contain` fits the artwork inside that
                     box without cropping a pixel off any edge. --}}
                {{-- A spacer, not a picture: the artwork is the BACKGROUND now, so
                     all this does is push the type block down to the foot of the
                     frame. --}}
                <span class="flex-1 min-h-0"></span>

                {{-- ===== Rule, eyebrow, rule =====
                     Centred, with a stroke on BOTH sides. One stroke pointed at
                     the type and made the line look like it had been pushed to
                     the left; a matching one closes it, and the classification
                     reads as a caption on the poster rather than a label stuck
                     to its edge. --}}
                <div class="flex items-center justify-center gap-3.5 m-in" style="animation-delay:.06s">
                    <span class="h-1 w-[38px] rounded-full flex-shrink-0"
                          style="background: {{ $coverColor }};"></span>
                    @if($coverEyebrow)
                        <span class="text-[10.5px] font-bold uppercase tracking-[.12em] text-white/85">
                            {{ $coverEyebrow }}
                        </span>
                        <span class="h-1 w-[38px] rounded-full flex-shrink-0"
                              style="background: {{ $coverColor }};"></span>
                    @endif
                </div>

                {{-- ===== The name of the thing ===== --}}
                <h1 class="text-[21px] leading-[25px] font-bold mt-[18px] m-in text-center" style="animation-delay:.12s">
                    {{ $e['title'] }}
                </h1>

                {{-- ===== When, and where ===== --}}
                <p class="text-[12.5px] text-white/60 mt-[11px] m-in text-center" style="animation-delay:.18s">
                    {{ $coverWhen }}@if($e['location'] && $e['location'] !== 'TBA') · {{ $e['location'] }}@endif
                </p>

                {{-- ===== Language, then the two doors =====
                     One shared block, so the flags and the tiles cannot drift
                     between the two covers (partials/cover-actions). --}}
                @include('eventlab::entry.public.partials.cover-actions')
            </div>
        </div>
    </template>
</div>

@include('eventlab::entry.public.partials.cover-script')
