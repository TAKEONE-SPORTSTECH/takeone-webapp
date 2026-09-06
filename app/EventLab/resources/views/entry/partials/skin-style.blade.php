{{--
    The public event surface's own palette and its one-column app frame.

    Extracted from entry/layout so the SEALED ADMIN SHELL (entry/shell) paints
    the identical skin over a platform screen. One copy, two shells — a second
    copy would drift the moment either was touched (Shared Stays Shared).

    Expects $skin (the sealed shell) or $e (the public poster) — both carry
    `color`; the skin wins where both are in scope.
--}}
@php $__evColor = $skin['color'] ?? ($e['color'] ?? '#7c6bf5'); @endphp
    <style>
        /* The public shell's own palette, taken off the design file. It is
           declared HERE rather than in the app's `@theme` because these pages
           are a standalone white-label shell — giving the app's tokens these
           values would repaint the whole product. `--ev` is the event's,
           guarded by App\Support\Palette before it reaches the page. */
        :root {
            --ev:    {{ \App\Support\Palette::safe($__evColor) }};
            --pg:    #ffffff;   /* page ground   */
            /* Type drawn ON the ground rather than on a card. A page that
               paints itself dark (the cover) redefines these two with the
               ground, so the footer follows without knowing which page it is
               on. */
            --on-pg:      #6b7689;  /* ground type          */
            --on-pg-line: rgba(30,44,79,.14);  /* ground hairline */
            --ink:   #1e2c4f;   /* body type     */
            --ink-2: #6b7689;   /* muted type    */
            --ink-3: #97a1b4;   /* faintest type */
            --line:  #e6eaf1;   /* hairlines     */
        }
        body { font-family: 'Inter', sans-serif; }

        /* Every page of the event app EXCEPT the cover sits on white, the
           sealed management screens included. They need the rule because
           layouts.app hardcodes `bg-background` on their <body>, and a class
           (0,1,0) outranks a bare element (0,0,1) — `body.antialiased` is
           (0,1,1), so it wins without reaching for !important. The poster's
           own <body> carries `background: var(--pg)` inline and follows the
           token on its own, which is how the cover stays black. */
        body.antialiased { background: var(--pg); }

        /* ===== Icons sit in the MIDDLE of a round control =====

           Bootstrap Icons ship `vertical-align: -.125em` on their ::before, so
           the glyph is drawn ~2px BELOW the text baseline — which means a 40px
           circle centred perfectly with `grid place-items-center` still looks
           wrong, because what is centred is a box whose contents have been
           pushed down. Turning the ::before into a block takes it out of
           baseline alignment entirely, and then the centring is the only thing
           positioning it.

           Scoped to `.ev-ico`, so it only touches the controls on these pages
           and never an icon sitting inline with text. */
        .ev-ico { line-height: 1; }
        .ev-ico > i,
        .ev-ico > i::before { line-height: 1; }
        .ev-ico > i::before { display: block; vertical-align: 0; }

        /* The same rule the app shell applies, so a component that measures the
           viewport behaves identically here. */
        html { -webkit-text-size-adjust: 100%; }

        /* ===== One reading, at every width =====

           There is no desktop version of an event and there must not be: this
           is an APP that happens to have a URL, and a competition is read in a
           hall, on a phone, one-handed. The controllers serve the mobile blades
           to everybody (PublicEventController::show/section); this is the other
           half of that decision — on a big screen the app becomes a column of
           its own natural width, centred on the ground, instead of a phone
           layout smeared across a monitor.

           A MOUSE and a wide window, both — the same coarse/fine test the
           fullscreen script below uses. A phone turned sideways and a tablet
           are wide too, and they are still being held: capping them would take
           width away from a bracket at exactly the moment somebody turned the
           device to get more of it. So touch keeps the full bleed at every
           size, and only a desktop gets the column. */
        @media (min-width: 560px) and (hover: hover) and (pointer: fine) {
            .ev-app {
                max-width: 520px;
                margin-inline: auto;
                background: #fff;
                box-shadow: 0 30px 80px -40px rgba(15, 23, 42, .45);
            }
            /* The column stands off the top and bottom edges so it reads as an
               object on the page rather than a page that failed to fill. */
            body { padding-block: 26px; }
            .ev-app-top { border-radius: 26px 26px 0 0; overflow: hidden; }
            .ev-app-bottom { border-radius: 0 0 26px 26px; }

            /* Anything the app pins to the viewport — the poster cover, a
               sticky Continue bar — is pinned to the COLUMN instead, or it
               would run the whole width of a monitor with a 520px app sitting
               in the middle of it. `inset-x-0` plus an auto margin centres it. */
            .ev-app-fixed { max-width: 520px; margin-inline: auto; }
        }
    </style>
