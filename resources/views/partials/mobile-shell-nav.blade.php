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

    function updateActive(route) {
        document.querySelectorAll('[data-shell-link]').forEach(function (a) {
            a.classList.toggle('is-active', a.getAttribute('data-route') === route);
        });
    }

    // Re-execute any inline <script> tags that arrived inside the swapped content.
    function runScripts(container) {
        container.querySelectorAll('script').forEach(function (old) {
            var s = document.createElement('script');
            if (old.src) s.src = old.src; else s.textContent = old.textContent;
            old.parentNode.replaceChild(s, old);
        });
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
            if (!nc) { window.location.href = url; return; } // different shell -> full load
            c.innerHTML = nc.innerHTML;
            var route = nc.getAttribute('data-route') || '';
            c.setAttribute('data-route', route);
            var titleEl = document.getElementById('shell-title');
            if (titleEl && nc.getAttribute('data-title')) titleEl.textContent = nc.getAttribute('data-title');
            if (doc.title) document.title = doc.title;
            updateActive(route);
            // Update the URL BEFORE running inline scripts so they can read the
            // destination's query string (e.g. the schedule list reading ?day=).
            if (push !== false) history.pushState({ shell: true }, '', url);
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
