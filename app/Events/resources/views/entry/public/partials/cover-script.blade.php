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

        const seen = () => {
            try { return sessionStorage.getItem(KEY) === '1'; } catch { return false; }
        };

        return {
            open: ! seen(),

            init() {
                /* The page beneath must not scroll under the cover — a stray
                   swipe otherwise leaves the visitor somewhere down the poster
                   the moment they dismiss it. */
                if (this.open) document.documentElement.style.overflow = 'hidden';
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
                document.documentElement.style.overflow = 'hidden';
                try { sessionStorage.removeItem(KEY); } catch { /* nothing was remembered anyway */ }
            },
        };
    }
</script>
@endpush

@endonce
