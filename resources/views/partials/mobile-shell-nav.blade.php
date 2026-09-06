@once
@push('styles')
<style>
    /* Active states toggled by the shell navigator (no full reload). */
    .shell-nav-link { color: #1f2937; }
    .shell-nav-link:hover { background: hsl(250 60% 92%); }
    .shell-nav-link.is-active { background: hsl(250 65% 65%); color: #fff; box-shadow: 0 1px 3px rgba(0,0,0,.12); }
    .shell-nav-link.is-active i { color: #fff; }
    .shell-tab { color: #6b7280; }
    .shell-tab.is-active { color: hsl(250 65% 65%); }
    #shell-content { transition: opacity .15s ease; }
    #shell-content.is-loading { opacity: .45; pointer-events: none; }
</style>
@endpush
@push('scripts')
<script>
(function () {
    function content() { return document.getElementById('shell-content'); }
    if (!content()) return;

    // This script itself lives in #shell-scripts, which a navigation now replaces.
    // Its listeners survive that (the closure outlives the tag), so a second run
    // would only double-bind every click handler.
    if (window.__mobileShellNavInit) return;
    window.__mobileShellNavInit = true;

    function updateActive(route) {
        document.querySelectorAll('[data-shell-link]').forEach(function (a) {
            a.classList.toggle('is-active', a.getAttribute('data-route') === route);
        });
    }

    // Re-execute any inline <script> tags that arrived inside the swapped content.
    // Content scripts re-run on EVERY visit on purpose: they bind handlers to the
    // DOM that was just replaced, so skipping them would leave the new markup dead.
    function runScripts(container) {
        if (!container) return;
        container.querySelectorAll('script').forEach(function (old) {
            var s = document.createElement('script');
            for (var i = 0; i < old.attributes.length; i++) { s.setAttribute(old.attributes[i].name, old.attributes[i].value); }
            if (old.src) s.src = old.src; else s.textContent = old.textContent;
            old.parentNode.replaceChild(s, old);
        });
    }

    /* ── Page-pushed scripts and modals ──────────────────────────────────────
       Blade's pushed 'scripts' / 'modals' stacks render OUTSIDE #shell-content, in
       the layout's #shell-scripts / #shell-modals wrappers. Until this existed
       the mobile navigator never fetched them, so a component whose behaviour is
       registered from the script stack simply had no behaviour after an in-place
       navigation.

       The x-qr-code component is the case that exposed it: `Alpine.data('qrCode')` was
       never registered, so `x-data="qrCode(…)"` threw, the component's scope was
       EMPTY, and `x-show="open"` then resolved `open` against the global scope —
       finding `window.open`, a function, which is truthy. The teleported sheet
       therefore rendered permanently, with no title, no QR and no link. An empty
       sheet over the page, on arrival, every time.

       These run ONCE per session, deduped by content, because a pushed script
       declares top-level `const`/`function` at global scope and re-running it
       throws "already declared". The set is seeded with what the browser already
       executed on first paint — deferred to DOMContentLoaded, because later
       siblings in the same stack are not parsed yet at the moment this runs.
       Same mechanics as the desktop admin shell (partials/admin-shell-nav). ── */
    var ranScripts = new Set();
    function scriptKey(el) { return el.src ? ('SRC:' + el.src) : ('TXT:' + el.textContent); }
    function seedRanScripts() {
        document.querySelectorAll('#shell-scripts script, #shell-modals script')
            .forEach(function (el) { ranScripts.add(scriptKey(el)); });
    }
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', seedRanScripts); }
    else { seedRanScripts(); }

    function runStackScripts(container) {
        if (!container) return;
        container.querySelectorAll('script').forEach(function (old) {
            var key = scriptKey(old);
            if (ranScripts.has(key)) return;
            ranScripts.add(key);
            var s = document.createElement('script');
            for (var i = 0; i < old.attributes.length; i++) { s.setAttribute(old.attributes[i].name, old.attributes[i].value); }
            if (old.src) s.src = old.src; else s.textContent = old.textContent;
            old.parentNode.replaceChild(s, old);
        });
    }

    function swapStacks(doc) {
        [['shell-modals', false], ['shell-scripts', true]].forEach(function (pair) {
            var cur = document.getElementById(pair[0]);
            var next = doc.getElementById(pair[0]);
            if (cur && next) cur.innerHTML = next.innerHTML;
        });
        runStackScripts(document.getElementById('shell-modals'));
        runStackScripts(document.getElementById('shell-scripts'));
    }

    async function navigate(url, push) {
        var c = content();
        if (!c) { window.location.href = url; return; }
        c.classList.add('is-loading');
        try {
            var res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
            if (!res.ok) { window.location.href = url; return; }
            var html = await res.text();
            var doc = new DOMParser().parseFromString(html, 'text/html');
            var nc = doc.getElementById('shell-content');
            if (!nc) { window.location.href = url; return; } // no shell content -> full load
            // Cross-shell guard: every mobile shell reuses id="shell-content", so a
            // link that crosses shells (personal ↔ admin-club ↔ business) would swap
            // foreign content under the wrong header/drawer/tab-bar. If the destination
            // declares a different data-shell-id, hard-load instead of swapping.
            var here = c.getAttribute('data-shell-id');
            var there = nc.getAttribute('data-shell-id');
            if (here && there && here !== there) { window.location.href = url; return; }
            c.innerHTML = nc.innerHTML;
            var route = nc.getAttribute('data-route') || '';
            c.setAttribute('data-route', route);
            var titleEl = document.getElementById('shell-title');
            if (titleEl && nc.getAttribute('data-title')) titleEl.textContent = nc.getAttribute('data-title');
            // Page actions live in the header, outside #shell-content, so swap them
            // too — and clear them when the destination declares none, or the previous
            // page's buttons linger on a page they don't belong to.
            var actionsEl = document.getElementById('shell-actions');
            if (actionsEl) {
                var newActions = doc.getElementById('shell-actions');
                actionsEl.innerHTML = newActions ? newActions.innerHTML : '';
            }
            if (doc.title) document.title = doc.title;
            updateActive(route);
            // Update the URL BEFORE running inline scripts so they can read the
            // destination's query string (e.g. the schedule list reading ?day=).
            if (push !== false) history.pushState({ shell: true }, '', url);
            swapStacks(doc);
            runScripts(c);
            window.scrollTo(0, 0);
            window.dispatchEvent(new CustomEvent('shell:navigated'));
        } catch (e) {
            window.location.href = url; return;
        } finally {
            c.classList.remove('is-loading');
        }
    }

    document.addEventListener('click', function (e) {
        var a = e.target.closest('a[data-shell-link]');
        if (!a) return;
        if (a.target === '_blank' || e.metaKey || e.ctrlKey || e.shiftKey) return;
        var url = a.getAttribute('href');
        if (!url || url.charAt(0) === '#') return;
        e.preventDefault();
        navigate(url, true);
    });

    window.addEventListener('popstate', function () { navigate(window.location.href, false); });

    // Count-up enhancement (progressive): real value is server-rendered; this just
    // animates 0 → value. Reusable across any mobile view via [data-countup].
    function runCountUps(scope) {
        (scope || document).querySelectorAll('[data-countup]').forEach(function (el) {
            if (el.dataset.cuDone) return;
            el.dataset.cuDone = '1';
            var target = parseFloat(el.getAttribute('data-countup')) || 0;
            var prefix = el.getAttribute('data-prefix') || '';
            if (target <= 0) return;
            var dur = 900, start = null;
            function step(ts) {
                if (!start) start = ts;
                var p = Math.min((ts - start) / dur, 1);
                var eased = 1 - Math.pow(1 - p, 3);
                el.textContent = prefix + Math.round(target * eased).toLocaleString();
                if (p < 1) requestAnimationFrame(step);
            }
            requestAnimationFrame(step);
        });
    }

    // Replay entrance animation + count-ups after each in-place navigation.
    window.addEventListener('shell:navigated', function () {
        var c = content();
        if (c && c.classList.contains('mobile-stagger')) {
            c.classList.remove('mobile-stagger');
            void c.offsetWidth;            // force reflow so the animation restarts
            c.classList.add('mobile-stagger');
        }
        if (c) c.querySelectorAll('[data-countup]').forEach(function (el) { el.dataset.cuDone = ''; });
        runCountUps(c);
    });

    if (document.readyState !== 'loading') runCountUps(); else document.addEventListener('DOMContentLoaded', function () { runCountUps(); });
})();
</script>
@endpush
@endonce
