{{--
    The running-order page's own state: WHICH of the two views is open.

    Nothing more. The two boards underneath it are whole Alpine components of
    their own and keep every rule they had; this only decides which one is on
    screen, remembers that in the address so a reload and the back button land
    where the reader was, and carries a tap on a division across to the mats.

    Registered with Alpine.data() rather than written inline, for the reason the
    board's own script gives: an inline x-data that loses its <script> to a
    shell swap or a teleport resolves to an EMPTY scope, and then every binding
    on the page fails separately and silently.
--}}
@once
@push('scripts')
<script>
(function () {
    function register() {
        if (! window.Alpine || window.Alpine.data.__orderPage) { return; }
        window.Alpine.data.__orderPage = true;

        window.Alpine.data('orderPage', function (initial, canManage) {
            return {
                view: canManage ? (initial === 'mats' ? 'mats' : 'day') : 'mats',
                canManage: !! canManage,

                init() {
                    /*
                     * A division tapped on THE DAY asks for THE MATS, scrolled
                     * to its bouts. The day board dispatches it on the window
                     * because the two boards are siblings with no scope in
                     * common — this page is the only thing that can hear both.
                     */
                    this._focus = (e) => {
                        this.show('mats');
                        // After the panel is actually on screen, or the board
                        // is being told to highlight something with no layout.
                        this.$nextTick(() => window.dispatchEvent(
                            new CustomEvent('running-order:focus', { detail: e.detail || {} })
                        ));
                    };

                    // Deduped: the mobile shell re-runs this page's scripts on
                    // every in-place navigation, and a stacked listener would
                    // fire the jump twice (CLAUDE.md → Realtime, §6).
                    if (window.__orderPageFocus) {
                        window.removeEventListener('order-page:show-mats', window.__orderPageFocus);
                    }
                    window.__orderPageFocus = this._focus;
                    window.addEventListener('order-page:show-mats', this._focus);
                },

                show(v) {
                    if (! this.canManage) { v = 'mats'; }
                    if (v === this.view) { return; }
                    this.view = v;
                    this.remember(v);
                },

                /* The tab in the address, so a reload and Back both land on the
                   view that was open. replaceState, not push: the two views are
                   one page, and a Back that walked the tabs would trap somebody
                   who only wanted to leave. */
                remember(v) {
                    try {
                        const u = new URL(window.location.href);
                        u.searchParams.set('view', v);
                        window.history.replaceState(window.history.state, '', u.toString());
                    } catch (e) { /* an address we cannot parse is not worth a broken tab */ }
                },
            };
        });
    }

    if (window.Alpine) { register(); }
    document.addEventListener('alpine:init', register);
})();
</script>
@endpush
@endonce
