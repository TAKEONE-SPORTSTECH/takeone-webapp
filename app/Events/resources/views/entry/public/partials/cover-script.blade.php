{{--
    The cover's state, shared by both breakpoints.

    It was declared inside the MOBILE cover partial, which was fine while the
    cover existed only on a phone. The desktop cover needs the identical
    behaviour — show once per tab, lock the page beneath, remember the dismissal
    — and forking it would guarantee the two drift (CLAUDE.md → Shared Stays
    Shared). One copy, included by both.
--}}
@once
@push('scripts')
<script>
    /* The cover shows on the FIRST arrival and not again for the rest of the
       visit: coming back from the entry form should return somebody to the page
       they left, not make them tap past the poster a second time. sessionStorage
       is per-tab and forgets on close, so a link opened tomorrow covers again.
       Every access is guarded — a private window or a browser set to block site
       data throws on read, and that must degrade to "show the cover", never to a
       page that fails to render. */
    function eventCover() {
        const KEY = 'takeone:cover:' + @js($e['key']);

        /* Arriving from an event CARD (explore, the map) is a fresh arrival,
           whatever this tab remembers: the card's address carries `#cover`, so
           the visitor lands on the language selector every time they open the
           event from a list. The marker is dropped straight away, so a reload
           or a Back into this page does not re-open it. */
        try {
            if (location.hash === '#cover') {
                sessionStorage.removeItem(KEY);
                history.replaceState(history.state, '', location.pathname + location.search);
            }
        } catch { /* storage blocked: seen() already reads as "show the cover" */ }

        const seen = () => {
            try { return sessionStorage.getItem(KEY) === '1'; } catch { return false; }
        };

        return {
            /* `$coverStartsClosed`: a page INSIDE the event's flow (the club
               steps, /me/events/{uuid}/rules…) keeps the cover for when it is
               asked for — the home control — instead of opening over the step. */
            open: ! @js((bool) ($coverStartsClosed ?? false)) && ! seen(),

            init() {
                /* The page beneath must not scroll under the cover — a stray
                   swipe otherwise leaves the visitor somewhere down the poster
                   the moment they dismiss it. */
                if (this.open) document.documentElement.style.overflow = 'hidden';

                /* ===== The cover is exactly as tall as what you can SEE =====
                   `100dvh` is right in a browser, and wrong inside the member
                   app: Android's WebView measures it once, before the system
                   bars have settled around the app (the shell goes edge-to-edge
                   on start), and does not measure again until the phone is
                   locked and woken — so the language buttons sat half below
                   the screen until then (reported 2026-09-27). The visible
                   height in pixels is what JavaScript is told the moment it
                   changes, so the cover takes that, and keeps taking it: now,
                   over the first seconds while the bars settle, and on every
                   resize, rotation, wake and return. CSS stays as the fallback
                   for a page whose script never runs. */
                const fit = () => {
                    /* The viewport guard's measurement where it is loaded
                       (partials/viewport-guard) — it also knows when the app
                       is hiding a strip under the bottom bar — else our own. */
                    const guarded = parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--app-h'));
                    const h = Math.round(guarded || window.innerHeight || 0);
                    if (h < 200) return;   // a WebView mid-layout; wait for the next pass
                    document.querySelectorAll('[data-cover-screen]').forEach((el) => {
                        el.style.height = h + 'px';
                    });
                };

                this.$nextTick(fit);
                [60, 250, 600, 1200, 2500].forEach((ms) => setTimeout(fit, ms));
                window.addEventListener('resize', fit, { passive: true });
                window.addEventListener('viewport-guard:change', fit);
                window.addEventListener('orientationchange', () => setTimeout(fit, 250));
                window.addEventListener('pageshow', fit);
                document.addEventListener('visibilitychange', () => {
                    if (document.visibilityState === 'visible') setTimeout(fit, 60);
                });
                window.visualViewport?.addEventListener('resize', fit, { passive: true });
            },

            dismiss() {
                if (! this.open) return;
                this.open = false;
                document.documentElement.style.overflow = '';
                try { sessionStorage.setItem(KEY, '1'); } catch { /* nothing to remember; the cover simply shows again */ }
            },

            /* Chosen a language — so they are going in.
               The language buttons are the ONLY way off the cover now, and they
               submit a form, which reloads the page. Without remembering the
               dismissal first the cover would simply paint again on the way
               back and the visitor would be stuck on it. No animation: the
               screen is about to be replaced. */
            markSeen() {
                try { sessionStorage.setItem(KEY, '1'); } catch { /* the cover shows again; harmless */ }
            },

            /* Back to the poster.
               Dismissing is remembered per tab so a visitor returning from the
               entry form is not made to tap past the artwork again — which left
               no way BACK to it short of opening a new tab. This is that way:
               it forgets the dismissal as well as re-opening, so the cover
               behaves like a first arrival rather than something that will snap
               shut on the next navigation. */
            reopen() {
                this.open = true;
                /* Re-measured on the way back up: the screen may have changed
                   size since the cover was last open. */
                this.$nextTick(() => window.dispatchEvent(new Event('resize')));
                document.documentElement.style.overflow = 'hidden';
                try { sessionStorage.removeItem(KEY); } catch { /* nothing was remembered anyway */ }
            },
        };
    }
</script>
@endpush

@endonce
