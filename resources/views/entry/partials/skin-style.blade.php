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

        /* ── The hero band's control token ─────────────────────────────────
           ONE definition of the 40px round control, and ONE gap between them.

           Both event doors used to draw this themselves: the member door in
           Tailwind (`bg-white/15 border-white/25 backdrop-blur`) and the public
           one in inline styles (`rgba(255,255,255,.14)`, border `.3`).

           (Paths are named in words here on purpose: this stylesheet ships
           inside the public page, and the audit that proves that page never
           links back to the platform greps the served HTML for them.) Two
           implementations of the same button, differing by one percent of
           alpha — near-identical, which reads worse than plainly different.
           And the /me row nested its actions in a `gap-2` div inside a 12px
           row, so the spacing changed halfway along it. That was the mess.

           Real CSS rather than Tailwind classes on purpose: the built bundle
           only carries classes something already used (see the Tailwind note
           in CLAUDE.md), and this file is included by BOTH shells —
           entry/shell.blade.php and entry/layout.blade.php — so one rule here
           reaches every event surface there is. */
        /* 12px, which is the value the band itself settled on: "It went 8 → 12
           → 18 and 18 was too far — at 40px round each they stopped reading as
           one cluster belonging to this header and started looking like loose
           buttons" (components/event-poster-band.blade.php). The row is a SPAN
           because the band's control slot is phrasing content. */
        .ev-ctl-row { display: flex; align-items: center; gap: 12px; flex: 1 1 auto; }
        .ev-ctl-spacer { flex: 1 1 auto; }
        .ev-ctl {
            width: 40px; height: 40px; flex: none;
            display: grid; place-items: center;
            border-radius: 50%;
            background: rgba(255, 255, 255, .15);
            border: 1px solid rgba(255, 255, 255, .26);
            -webkit-backdrop-filter: blur(6px); backdrop-filter: blur(6px);
            color: #fff; font-size: 15px; line-height: 1;
            text-decoration: none;
            transition: background-color .18s ease;
        }
        .ev-ctl:hover { background: rgba(255, 255, 255, .26); color: #fff; }
        .ev-ctl:focus-visible { outline: 2px solid rgba(255, 255, 255, .8); outline-offset: 2px; }

        /* A row in the overflow sheet: an icon tile, a label, a sub-line.
           The actions in that band had no NAMES at all before — five glyphs,
           and `bi-sliders` on one door meaning what `bi-gear` meant on the
           other. */
        /* ── The band's dropdown ──────────────────────────────────────────
           A panel, not a sheet: four named rows do not earn a dimmed page and
           a drag handle. Design Rule #4's dropdown shape — rounded, hairline
           border, deep soft shadow, hover rows — pinned by the trigger's own
           rect because the band it hangs off is `overflow-hidden`. */
        .ev-menu-panel {
            position: fixed;
            width: 268px; max-width: calc(100vw - 24px);
            padding: 6px;
            background: #fff;
            border: 1px solid hsl(210 14% 91%);
            border-radius: 18px;
            box-shadow: 0 24px 60px -20px rgba(15, 23, 42, .38), 0 2px 8px rgba(15, 23, 42, .06);
            overflow: hidden;
            z-index: 1;
        }
        .ev-menu-row {
            width: 100%; display: flex; align-items: center; gap: 12px;
            padding: 10px 10px; border-radius: 13px;
            background: transparent; border: 0;
            text-align: start; text-decoration: none; color: inherit;
            cursor: pointer;
            transition: background-color .18s ease;
        }
        .ev-menu-row:hover { background: hsl(220 15% 96%); }
        .ev-menu-row + .ev-menu-row,
        .ev-menu-slot + .ev-menu-row,
        .ev-menu-row + form,
        .ev-menu-slot + form { margin-top: 2px; }
        .ev-menu-ico {
            width: 40px; height: 40px; flex: none; border-radius: 12px;
            display: grid; place-items: center; font-size: 17px;
        }
        .ev-menu-label { display: block; font-size: 13.5px; font-weight: 700; line-height: 1.2; color: hsl(222 18% 20%); }
        .ev-menu-sub { display: block; font-size: 11px; color: hsl(220 9% 48%); margin-top: 2px; line-height: 1.3; }
        /* The QR component wraps itself in a hard-coded `inline-block` div, so
           as a full-width sheet row it would shrink to its content. Corrected
           here rather than in the component, which other callers rely on being
           inline (RULE #1: additive, never alter working code).

           ⚠️ Do NOT name that component in this comment with its angle-bracket
           tag. Blade parses a component tag even inside a CSS or JS comment, so
           the literal broke this whole view with a misleading "expecting endif"
           error the moment it was added. Exactly the trap CLAUDE.md records. */
        .ev-menu-slot > div { display: block; width: 100%; }
    </style>
