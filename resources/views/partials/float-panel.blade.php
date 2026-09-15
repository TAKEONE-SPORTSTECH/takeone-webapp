@once
@push('scripts')
{{-- ANCHORED DROPDOWN PANELS — the third answer to a problem that only had two.

     A picker inside a bottom sheet has been stuck between two bad options:

       · `position: absolute` overlays the fields, which is what a dropdown
         should do — but the sheet body is `overflow-y-auto`, so a panel near
         the bottom is CLIPPED by its own scrolling container and half the list
         cannot be reached.
       · expanding IN THE NORMAL FLOW is never clipped — but it SHOVES every
         field below it down the sheet as it opens, which is what this was
         asked to stop.

     A `position: fixed` panel does neither: fixed elements are not clipped by
     an ancestor's overflow, and they take no space in the flow. The cost is
     that they no longer follow their trigger, so this positions them by hand —
     under the trigger, flipped above it when there is more room there, clamped
     to the viewport, and re-measured on every scroll and resize while open.

     ⚠️ `position: fixed` resolves against the nearest ancestor carrying a
     `transform`/`filter`/`will-change`, not always the viewport. Everything
     using this sits inside a sheet that is ITSELF `position: fixed` and works,
     which is the proof there is no such ancestor in the way. Do not reach for
     this inside `.mobile-stagger` content, where `m-rise` leaves a transform on
     every direct child — that is the trap the teleport rule exists for.

     Usage, from any Alpine scope:
         <button x-ref="fooTrigger" @click="fooOpen = ! fooOpen;
                 $nextTick(() => window.tkFloat($refs.fooTrigger, $refs.fooPanel, fooOpen))">
         <div x-ref="fooPanel" x-show="fooOpen" style="z-index:78"> … </div>

     Passing `false` as the third argument releases the panel. --}}
<script>
(function () {
    if (window.tkFloat) return;

    // Panels currently anchored, and what they are anchored to.
    var live = new Map();

    function place(trigger, panel, matchWidth) {
        if (! trigger || ! panel || ! trigger.isConnected) return;

        var r = trigger.getBoundingClientRect();

        panel.style.position = 'fixed';
        panel.style.margin = '0';

        /* A panel usually spans its trigger. One that carries its own width —
           a dial-code list opening off a narrow flag button — keeps it, and is
           only nudged back inside the screen if it would hang off the edge. */
        if (matchWidth === false) {
            var w = panel.offsetWidth;
            panel.style.left = Math.round(Math.max(8, Math.min(r.left, window.innerWidth - w - 8))) + 'px';
        } else {
            panel.style.left = Math.round(r.left) + 'px';
            panel.style.width = Math.round(r.width) + 'px';
        }

        // Measure at full height before deciding which way to open.
        panel.style.maxHeight = '';
        var wanted = panel.offsetHeight;

        var gap = 6;
        var edge = 8;
        var below = window.innerHeight - r.bottom - gap - edge;
        var above = r.top - gap - edge;

        // Open downwards unless it does not fit and there is more room up.
        var up = wanted > below && above > below;
        var room = Math.max(120, up ? above : below);

        panel.style.maxHeight = room + 'px';
        panel.style.overflowY = 'auto';

        panel.style.top = up
            ? Math.max(edge, r.top - Math.min(wanted, room) - gap) + 'px'
            : Math.round(r.bottom + gap) + 'px';
    }

    /* Hidden means released: Alpine's x-show sets display:none, so a panel that
       was closed by anything other than its own toggle still lets go. */
    function sync() {
        live.forEach(function (entry, panel) {
            if (! panel.isConnected || panel.style.display === 'none') {
                live.delete(panel);

                return;
            }

            place(entry.trigger, panel, entry.matchWidth);
        });
    }

    window.tkFloat = function (trigger, panel, open, matchWidth) {
        if (! panel) return;

        if (open === false) {
            live.delete(panel);

            return;
        }

        live.set(panel, { trigger: trigger, matchWidth: matchWidth });
        place(trigger, panel, matchWidth);
        // Again after the enter transition has painted: a panel measured while
        // it is still fading in reports the wrong height.
        requestAnimationFrame(function () { place(trigger, panel, matchWidth); });
    };

    // `true` — capture, so a scroll inside the sheet's own body counts too.
    window.addEventListener('scroll', sync, true);
    window.addEventListener('resize', sync);
})();
</script>
@endpush
@endonce
