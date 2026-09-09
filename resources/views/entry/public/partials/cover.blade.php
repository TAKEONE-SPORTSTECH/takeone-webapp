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
    use App\Events\Support\CoverLanguages;

    /* Belt and braces on the one value that lands in a `style` attribute.
       `App\Events\Support\PublicEvent::color()` already refuses anything but
       six hex digits, so nothing can reach here to be injected — but every
       other surface that paints an organiser-supplied colour re-checks it at
       the point of use, and a partial should not be the one place that trusts
       its caller to have done it. */
    $coverColor = preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($e['color'] ?? ''))
        ? $e['color']
        : '#1677FF';

    /* Every language the carousel offers, already rendered into every language
       — see App\Events\Support\CoverLanguages for why the whole set has to
       arrive with the page rather than be fetched per scroll. */
    $coverEvent = \App\Models\ClubEvent::where('uuid', $e['uuid'] ?? $e['key'])->first();
    $coverLangs = $coverEvent ? CoverLanguages::for($coverEvent, $e) : [];

    /* ===== Where the strip starts =====

       ENGLISH, unless this reader has explicitly chosen a language for THIS
       event (asked for 2026-09-09).

       ⚠️ Not `app()->getLocale()`, which was the bug. That resolves through the
       whole chain — a signed-in member's saved account language, and failing
       that the browser's `Accept-Language` header — so a visitor whose phone
       happens to be set to Turkish opened the cover with the Turkish card
       centred and the whole screen already in Turkish, having chosen nothing.
       A browser header is a hint about what somebody CAN read, not a decision
       they made, and the cover exists to ask for the decision.

       `EventLocale::get()` returns a value only when a person actually picked a
       language on this event, in this session — so an explicit choice is still
       honoured when the cover is reopened from the poster's band, and
       everything else starts where a stranger should: English. */
    $coverChosen = \App\Translation\EventLocale::get(request(), (string) ($e['uuid'] ?? $e['key']));
    $coverActive = $coverChosen ?: 'en';

    /* ⚠️ A DIFFERENT QUESTION from where the strip starts: what language the
       page underneath is actually rendered in. Enter needs both — it reloads
       only when the two differ, so confirming the language you are already
       reading costs nothing. */
    $coverServing = app()->getLocale();
@endphp

<div x-data="eventCover()" x-cloak @reopen-cover.window="reopen()"
     {{-- The language sheet navigates away by submitting a form. It says so
          first, so the cover does not paint over the page on the way back. --}}
     @cover-seen.window="markSeen()"
     {{-- The carousel is plain JS and has no Alpine scope of its own, so this
          is how it asks the cover to close — when Enter is pressed on the
          language the page is ALREADY being served in and there is nothing to
          reload. See partials/cover-carousel. --}}
     @cover-dismiss.window="dismiss()">
    <template x-teleport="body">
        <div x-show="open" x-cloak
             @keydown.escape.window="dismiss()"
             x-transition:leave="transition ease-in duration-[420ms]"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="ev-app-fixed fixed inset-x-0 top-0 z-[80] overflow-hidden text-white select-none"
             {{-- NOT `inset-0`. On a phone that resolves against the LAYOUT
                  viewport, which is the tall one — the height the page has when
                  the URL bar is hidden. `100dvh` is the DYNAMIC viewport:
                  exactly the visible area, re-measured as the bar shows and
                  hides. `100vh` first, so a browser too old for dvh still gets
                  a full screen. --}}
             style="background:#070b14; height:100vh; height:100dvh;">

            {{-- ===== The frame, exactly as the draft measures it =====
                 520px maximum, centred, the artwork full-bleed behind it. --}}
            <div style="position:relative; width:100%; max-width:520px; min-height:100%; height:100%; margin:0 auto; overflow:hidden; background:#070b14; color:#ffffff; user-select:none;">

                @if($e['photo'])
                    {{-- `center top`: a poster puts its name at the TOP, so when
                         `object-cover` has to crop it takes the slack off the
                         bottom and never off the title. --}}
                    <img src="{{ $e['photo'] }}" alt=""
                         style="position:absolute; inset:0; width:100%; height:100%; object-fit:cover; object-position:center top;">
                @else
                    <div style="position:absolute; inset:0; background:
                        radial-gradient(120% 80% at 82% -10%, {{ $coverColor }}cc 0%, {{ $coverColor }}44 42%, transparent 72%),
                        radial-gradient(90% 70% at -15% 42%, {{ $coverColor }}66 0%, transparent 68%);"></div>
                @endif

                {{-- The scrim: clear at the top, near-opaque by the bottom, so
                     the type sits on a known ground whatever was uploaded. --}}
                <div style="position:absolute; inset:0; background:linear-gradient(180deg, rgba(7,11,20,0) 0%, rgba(7,11,20,0) 40%, rgba(7,11,20,.55) 62%, rgba(7,11,20,.92) 82%, rgba(7,11,20,.98) 100%);"></div>

                <div style="position:relative; z-index:1; min-height:100%; height:100%; display:flex; flex-direction:column; padding:24px 0 max(28px, env(safe-area-inset-bottom));">

                    {{-- The artwork gets whatever room the type block leaves. --}}
                    <div style="flex:1 1 auto;"></div>

                    {{-- ===== Rule · classification · rule ===== --}}
                    <div style="display:flex; align-items:center; justify-content:center; gap:14px; padding:0 24px; animation:rise .5s ease both;">
                        <span style="height:4px; width:38px; border-radius:9999px; background:{{ $coverColor }}; flex:none;"></span>
                        <span data-cover-tag style="font-size:13px; font-weight:700; text-transform:uppercase; letter-spacing:.12em; color:rgba(255,255,255,.9); text-align:center;"></span>
                        <span style="height:4px; width:38px; border-radius:9999px; background:{{ $coverColor }}; flex:none;"></span>
                    </div>

                    <h1 data-cover-title style="font-size:26px; line-height:31px; font-weight:800; margin:18px 16px 0; text-align:center; animation:rise .5s .06s ease both;"></h1>
                    <p data-cover-date style="font-size:15px; color:rgba(255,255,255,.7); margin:11px 0 0; text-align:center; animation:rise .5s .12s ease both;"></p>

                    {{-- ===== The language carousel ===== --}}
                    <div style="margin-top:24px; animation:rise .5s .18s ease both;">
                        <p data-cover-langlabel style="font-size:13px; line-height:18px; font-weight:700; text-transform:uppercase; letter-spacing:.08em; color:rgba(255,255,255,.7); margin:0; text-align:center;"></p>

                        {{-- ⚠️ THE STRIP'S ARROWS NEVER MIRROR — `dir="ltr"`, and no
                             `rtl:rotate-180` on either chevron.

                             Every other chevron on this platform flips in RTL,
                             because it points along the reading order — back,
                             forward, next page. These two do not point along
                             anything a language decides. They point at the ENDS
                             OF A PHYSICAL STRIP that is itself pinned
                             `direction:ltr` (below, from the draft), so the
                             cards sit in the same order for an Arabic reader as
                             for an English one. Mirroring the arrows would aim
                             them away from the card they move you to.

                             The rule in the stylesheet enforces it, because
                             this convention is unusual here and the obvious
                             "fix" is to add the flip back (stated 2026-09-09).
                             The Enter button's arrow is NOT exempt: that one
                             does mean "forward", and forward is leftwards in
                             Arabic. --}}
                        <div style="position:relative;" dir="ltr">
                            <button type="button" dir="ltr" data-cover-prev aria-label="{{ __('events.cover_language') }}"
                                    style="position:absolute; left:8px; top:50%; transform:translateY(-50%); z-index:2; width:30px; height:30px; border-radius:9999px; display:grid; place-items:center; color:#fff; background:rgba(255,255,255,.14); border:1px solid rgba(255,255,255,.22); backdrop-filter:blur(8px); -webkit-backdrop-filter:blur(8px); cursor:pointer;">
                                <i class="bi bi-chevron-left"></i>
                            </button>
                            <button type="button" dir="ltr" data-cover-next aria-label="{{ __('events.cover_language') }}"
                                    style="position:absolute; right:8px; top:50%; transform:translateY(-50%); z-index:2; width:30px; height:30px; border-radius:9999px; display:grid; place-items:center; color:#fff; background:rgba(255,255,255,.14); border:1px solid rgba(255,255,255,.22); backdrop-filter:blur(8px); -webkit-backdrop-filter:blur(8px); cursor:pointer;">
                                <i class="bi bi-chevron-right"></i>
                            </button>

                            <div class="ps-strip" data-cover-strip tabindex="0"
                                 style="display:flex; align-items:center; gap:14px; direction:ltr; overflow-x:auto; scroll-snap-type:x mandatory; padding:26px calc(50% - 52px) 26px; outline:none; -webkit-mask-image:linear-gradient(to right, transparent, #000 30px, #000 calc(100% - 30px), transparent); mask-image:linear-gradient(to right, transparent, #000 30px, #000 calc(100% - 30px), transparent);">
                                {{-- THREE copies of the list: the strip loops by
                                     silently jumping one copy-width when the
                                     scroll settles near an edge, so it can be
                                     flicked for ever in either direction. --}}
                                @for($copy = 0; $copy < 3; $copy++)
                                    @foreach($coverLangs as $i => $lang)
                                        <button type="button" class="ps-card" data-index="{{ $copy * count($coverLangs) + $i }}"
                                                title="{{ $lang['title'] }}"
                                                style="scroll-snap-align:center; flex:none; width:104px; aspect-ratio:1/1; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:9px; padding:10px; border-radius:20px; background:rgba(255,255,255,.09); border:1px solid rgba(255,255,255,.2); color:#fff; cursor:pointer; backdrop-filter:blur(10px); -webkit-backdrop-filter:blur(10px); transition:background-color .28s ease, border-color .28s ease, box-shadow .28s ease, color .28s ease; will-change:transform;">
                                            @if($lang['flag'])
                                                <span class="fi fi-{{ $lang['flag'] }}" style="width:44px; height:33px; border-radius:8px; background-size:cover; flex:none; box-shadow:0 6px 18px rgba(0,0,0,.45); outline:1px solid rgba(255,255,255,.35); outline-offset:-1px;"></span>
                                            @else
                                                {{-- A language with no honest flag gets its own code on a
                                                     plate, never a borrowed country. --}}
                                                <span style="width:44px; height:33px; border-radius:8px; flex:none; display:grid; place-items:center; font-size:12px; font-weight:800; background:rgba(255,255,255,.16); outline:1px solid rgba(255,255,255,.35); outline-offset:-1px;">{{ strtoupper(substr($lang['code'], 0, 2)) }}</span>
                                            @endif
                                            <span dir="{{ $lang['dir'] }}" style="font-size:12px; font-weight:800; line-height:1.15; letter-spacing:-.01em; max-width:94px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $lang['native'] }}</span>
                                        </button>
                                    @endforeach
                                @endfor
                            </div>
                        </div>

                        <div style="text-align:center; height:18px; margin-top:-5px;">
                            <span data-cover-seltitle style="font-size:13px; line-height:18px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:rgba(255,255,255,.7); display:inline-block;"></span>
                        </div>
                    </div>

                    {{-- ===== Search · Enter ===== --}}
                    <div style="display:flex; align-items:center; justify-content:center; gap:10px; flex-wrap:wrap; margin-top:20px; padding:0 20px; animation:rise .5s .24s ease both;">
                        @if(count($coverLangs) > 1)
                            <button type="button" data-cover-search
                                    style="display:inline-flex; align-items:center; justify-content:center; gap:8px; width:170px; height:54px; padding:0; border-radius:9999px; background:rgba(255,255,255,.10); border:1px solid rgba(255,255,255,.18); font-size:16px; font-weight:700; color:rgba(255,255,255,.85); cursor:pointer;">
                                <i class="bi bi-search" style="font-size:13px;"></i><span data-cover-searchlabel></span>
                            </button>
                        @endif

                        {{-- ⚠️ The ONLY deliberate way past the cover for somebody
                             happy with the language they are already reading in.
                             Tapping the artwork does NOT enter — removed
                             2026-09-09 at the user's instruction, because the
                             gestures of CHOOSING a language and of giving up and
                             going in were the same gesture. --}}
                        <button type="button" data-cover-enter
                                style="display:inline-flex; align-items:center; justify-content:center; gap:8px; width:170px; height:54px; padding:0; border:0; border-radius:9999px; font-size:16px; font-weight:800; color:#fff; cursor:pointer; background:{{ $coverColor }}; box-shadow:0 10px 26px rgba(0,0,0,.35);">
                            <span data-cover-enterlabel></span>
                            <i class="bi bi-arrow-right rtl:rotate-180" style="font-size:12px;"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>

@include('entry.public.partials.cover-carousel', [
    'coverLangs' => $coverLangs,
    'coverActive' => $coverActive,
    'coverServing' => $coverServing,
])

@include('entry.public.partials.cover-script')
