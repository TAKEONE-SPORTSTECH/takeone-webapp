@once
@push('styles')
<style>
    /* Desktop admin SPA navigation — in-place content swap, no full reload. */
    [data-shell-main] { transition: opacity .14s ease; }
    [data-shell-main].is-loading { opacity: .5; pointer-events: none; }
    /* Slim top progress bar shown while a page is being fetched. */
    #shell-progress {
        position: fixed; top: 0; left: 0; height: 3px; width: 0;
        background: linear-gradient(90deg, hsl(250 65% 66%), hsl(262 60% 56%));
        z-index: 9999; opacity: 0; transition: width .2s ease, opacity .25s ease;
        box-shadow: 0 0 8px hsl(250 65% 60% / .6);
    }
    #shell-progress.active { opacity: 1; }
</style>
@endpush
@push('scripts')
<script>
(function () {
    function mainEl() { return document.querySelector('[data-shell-main]'); }
    if (!mainEl()) return; // not an admin shell page
    // This script lives in #shell-scripts and is re-run on every in-place navigation.
    // Bind the global click/popstate handlers only ONCE so they don't stack up.
    if (window.__adminShellNavInit) return;
    window.__adminShellNavInit = true;

    // ── Top progress bar ──────────────────────────────────────────────
    var bar = document.getElementById('shell-progress');
    if (!bar) { bar = document.createElement('div'); bar.id = 'shell-progress'; document.body.appendChild(bar); }
    var barTimer = null;
    function startBar() { clearInterval(barTimer); bar.classList.add('active'); var w = 8; bar.style.width = w + '%';
        barTimer = setInterval(function () { w = Math.min(w + (90 - w) * 0.12, 90); bar.style.width = w + '%'; }, 120); }
    function finishBar() { clearInterval(barTimer); bar.style.width = '100%';
        setTimeout(function () { bar.classList.remove('active'); setTimeout(function () { bar.style.width = '0'; }, 250); }, 200); }

    // Track <style>/<link> already present so we only inject a destination's NEW styles.
    var seenStyles = new Set();
    function styleKey(el) { return el.tagName === 'LINK' ? ('L:' + el.getAttribute('href')) : ('S:' + el.textContent); }
    document.head.querySelectorAll('style, link[rel="stylesheet"]').forEach(function (el) { seenStyles.add(styleKey(el)); });

    function injectStyles(doc) {
        doc.head.querySelectorAll('style, link[rel="stylesheet"]').forEach(function (el) {
            var key = styleKey(el);
            if (seenStyles.has(key)) return;
            seenStyles.add(key);
            document.head.appendChild(el.cloneNode(true));
        });
    }

    // Each unique <script> runs ONCE per session. Desktop admin pages declare
    // top-level `const`/`let`/`function` at global scope (for inline onclick=),
    // so re-running the same script would throw "already declared" and break the
    // page. Globals (toast/confirm/sidebar) and already-executed page scripts are
    // therefore skipped; their global functions persist from the first run, and
    // Alpine re-inits reactive content via its mutation observer. Pages needing
    // per-visit re-init should listen for the `shell:navigated` event.
    var ranScripts = new Set();
    function scriptKey(el) { return el.src ? ('SRC:' + el.src) : ('TXT:' + el.textContent); }
    // Seed with everything already executed by the browser on first paint. This script is
    // itself pushed onto the 'scripts' stack from the layout (before the page's own pushed
    // scripts, e.g. from index.blade.php, are appended) — so at the moment THIS <script> tag
    // is parsed and run, later sibling <script> tags inside #shell-scripts/#shell-modals may
    // not exist in the DOM yet. Querying now would under-seed ranScripts, and the very next
    // in-place navigation back to this same page would try to re-run (and redeclare) a script
    // the browser already executed natively on the hard load. Defer the seed to
    // DOMContentLoaded so every pushed script sibling has been parsed into the DOM first.
    function seedRanScripts() {
        document.querySelectorAll('#shell-scripts script, #shell-modals script, [data-shell-main] script')
            .forEach(function (el) { ranScripts.add(scriptKey(el)); });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', seedRanScripts);
    } else {
        seedRanScripts();
    }

    function runScripts(container) {
        if (!container) return;
        container.querySelectorAll('script').forEach(function (old) {
            var key = scriptKey(old);
            if (ranScripts.has(key)) { return; } // already executed once — don't redeclare
            ranScripts.add(key);
            var s = document.createElement('script');
            for (var i = 0; i < old.attributes.length; i++) { s.setAttribute(old.attributes[i].name, old.attributes[i].value); }
            if (old.src) s.src = old.src; else s.textContent = old.textContent;
            old.parentNode.replaceChild(s, old);
        });
    }

    function updateActive(route) {
        document.querySelectorAll('[data-shell-link]').forEach(function (a) {
            a.classList.toggle('active', a.getAttribute('data-route') === route);
        });
    }

    // ── Page fetch + prefetch cache ───────────────────────────────────
    // loadPage resolves to {kind:'html', text} or {kind:'hard', url} (full nav).
    var pageCache = new Map();
    function loadPage(url) {
        return fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then(function (res) {
                if (!res.ok) return { kind: 'hard', url: url };
                var ct = res.headers.get('content-type') || '';
                if (ct.indexOf('text/html') === -1) return { kind: 'hard', url: url };
                if (res.redirected && res.url && new URL(res.url).pathname !== new URL(url, window.location.href).pathname) {
                    return { kind: 'hard', url: res.url };
                }
                return res.text().then(function (t) { return { kind: 'html', text: t }; });
            });
    }
    function prefetch(url) {
        if (pageCache.has(url)) return pageCache.get(url);
        var p = loadPage(url).catch(function () { return { kind: 'hard', url: url }; });
        pageCache.set(url, p);
        setTimeout(function () { pageCache.delete(url); }, 15000); // expire so data never goes stale
        return p;
    }

    async function navigate(url, push) {
        var main = mainEl();
        if (!main) { window.location.href = url; return; }
        startBar();
        main.classList.add('is-loading');
        try {
            var result = await prefetch(url);
            pageCache.delete(url); // consume so the next click refetches fresh
            if (result.kind === 'hard') { window.location.href = result.url || url; return; }
            var doc = new DOMParser().parseFromString(result.text, 'text/html');
            var newMain = doc.querySelector('[data-shell-main]');
            // Different layout (non-admin page, mobile shell, or the OTHER admin shell
            // whose sidebar differs) → hard load so the correct shell renders.
            if (!newMain || newMain.getAttribute('data-shell-main') !== main.getAttribute('data-shell-main')) {
                window.location.href = url; return;
            }

            // 1. Bring any page-specific styles from the destination's <head>.
            injectStyles(doc);

            // 2. Swap the main content.
            main.innerHTML = newMain.innerHTML;
            var route = newMain.getAttribute('data-route') || '';
            main.setAttribute('data-route', route);

            // 3. Swap page-pushed modals + scripts (rendered outside <main> by the layout).
            var newModals = doc.getElementById('shell-modals');
            var curModals = document.getElementById('shell-modals');
            if (curModals && newModals) curModals.innerHTML = newModals.innerHTML;

            var newScripts = doc.getElementById('shell-scripts');
            var curScripts = document.getElementById('shell-scripts');
            if (curScripts && newScripts) curScripts.innerHTML = newScripts.innerHTML;

            // 4. Title + active state + history.
            if (doc.title) document.title = doc.title;
            updateActive(route);
            if (push !== false) history.pushState({ adminShell: true }, '', url);

            // 5. Run any NEW scripts (modals first, then page scripts, then anything in main).
            //    Alpine re-inits the swapped x-data via its own mutation observer — do NOT
            //    call Alpine.initTree here, or every component double-initializes (slow + buggy).
            runScripts(curModals);
            runScripts(curScripts);
            runScripts(main);

            // Existing admin pages gate their init on `DOMContentLoaded`, which never
            // fires on an in-place swap. Re-dispatch it so each page's init runs and
            // (re)binds to the freshly-swapped DOM — works on first visit AND revisit
            // because the listener registered on first visit persists and re-fires.
            try { document.dispatchEvent(new Event('DOMContentLoaded')); } catch (e) {}

            main.scrollTop = 0; window.scrollTo(0, 0);
            window.dispatchEvent(new CustomEvent('shell:navigated', { detail: { route: route, url: url } }));
        } catch (e) {
            window.location.href = url; return;
        } finally {
            main.classList.remove('is-loading');
            finishBar();
        }
    }

    // Pathname of the current shell's base (e.g. /admin/club/eta or /admin). In-content
    // links under this base navigate in place; links elsewhere (member profiles, other
    // sections) fall through to a normal full navigation.
    function shellBase() {
        var raw = mainEl() && mainEl().getAttribute('data-shell-base');
        if (!raw) return null;
        try { return new URL(raw, window.location.origin).pathname.replace(/\/$/, ''); } catch (e) { return null; }
    }

    function shouldIntercept(a) {
        // Explicit sidebar links always qualify.
        if (a.hasAttribute('data-shell-link')) return true;
        // Opt-outs / non-navigational links.
        if (a.hasAttribute('data-no-shell') || a.hasAttribute('download')) return false;
        if (a.target && a.target !== '_self') return false;
        if (a.hasAttribute('onclick') || a.getAttribute('data-bs-toggle')) return false;
        var href = a.getAttribute('href');
        if (!href || href.charAt(0) === '#' || /^(mailto:|tel:|javascript:)/i.test(href)) return false;
        // Only same-origin links under the current shell's base path.
        var u;
        try { u = new URL(href, window.location.href); } catch (e) { return false; }
        if (u.origin !== window.location.origin) return false;
        var base = shellBase();
        if (!base) return false;
        return u.pathname === base || u.pathname.indexOf(base + '/') === 0;
    }

    document.addEventListener('click', function (e) {
        var a = e.target.closest('a[href]');
        if (!a) return;
        if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button !== 0) return;
        if (!shouldIntercept(a)) return;
        var url = a.getAttribute('href');
        e.preventDefault();
        if (url === window.location.href || url === window.location.pathname) { return; }
        navigate(url, true);
    });

    // Prefetch on hover / touch-start so the click feels instant — the response is
    // usually already cached by the time the user releases the click.
    function maybePrefetch(e) {
        var a = e.target.closest && e.target.closest('a[href]');
        if (!a || !shouldIntercept(a)) return;
        var url = a.getAttribute('href');
        if (url === window.location.href || url === window.location.pathname) return;
        prefetch(url);
    }
    document.addEventListener('mouseover', maybePrefetch, { passive: true });
    document.addEventListener('touchstart', maybePrefetch, { passive: true });

    window.addEventListener('popstate', function (e) {
        if (!mainEl()) return;
        navigate(window.location.href, false);
    });
})();
</script>
@endpush
@endonce
