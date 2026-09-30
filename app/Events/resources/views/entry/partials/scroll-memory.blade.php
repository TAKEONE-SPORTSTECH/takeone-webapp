{{--
    Coming back to where you were — on every event page.

    Reading an event is a walk out and back: the poster, a door, the draw, back
    to the poster; the entry list, a person, back to the list. Each of those
    "backs" on this surface is an ADDRESS (the chevron in the band is a link,
    on purpose — a reader who arrived from a shared link has no history to go
    back through), so the browser treats the return as a fresh page and opens
    it at the top. Asked for 2026-09-27: "it must remember where I was".

    What it does, and the whole of it:
      · saves this page's scroll offset in sessionStorage as the reader leaves
        (and, throttled, as they scroll — an Android WebView can be killed in
        the background without a pagehide);
      · restores it when the page is reached by RETURNING — the browser's own
        back/forward, or a link back to the page the reader just came from
        (the band's chevron, the draw bar's arrow, a "back to event" row);
      · a fresh arrival — a door tapped from the poster — opens at the top, as
        a new page should.

    Per tab and per address (path + query), never across tabs, and forgotten
    after 30 minutes. Keeps retrying for a moment while the page is still
    growing (Alpine-cloaked sections, the map, images), and stops the instant
    the reader touches the screen, so it never fights a scroll they started.

    Standalone: no dependencies, guarded so a page that includes it twice runs
    it once. Included by entry/layout and entry/shell, so every event page has
    it by default.
--}}
<script>
(function () {
    if (window.__evScrollMemory) return;
    window.__evScrollMemory = true;

    var here = location.pathname + location.search;
    var KEY = 'ev-scroll:' + here;
    var RETURN = 'ev-scroll-return';
    var MAX_AGE = 30 * 60 * 1000;

    function store(k, v) { try { sessionStorage.setItem(k, v); } catch (e) {} }
    function read(k) { try { return sessionStorage.getItem(k); } catch (e) { return null; } }
    function drop(k) { try { sessionStorage.removeItem(k); } catch (e) {} }
    function pathOf(url) { try { var u = new URL(url, location.href); return u.origin === location.origin ? u.pathname + u.search : null; } catch (e) { return null; } }

    /* We restore it ourselves; the browser's attempt lands before the page has
       grown and then gets overridden by the cover's own scroll lock. */
    try { history.scrollRestoration = 'manual'; } catch (e) {}

    function save() {
        store(KEY, JSON.stringify({ y: Math.round(window.scrollY || document.documentElement.scrollTop || 0), at: Date.now() }));
    }

    var t = null;
    window.addEventListener('scroll', function () { clearTimeout(t); t = setTimeout(save, 200); }, { passive: true });
    window.addEventListener('pagehide', save);
    document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'hidden') save(); });

    /* A link back to the page we came from IS going back, whatever it looks
       like: the band's chevron, the draw bar's arrow, "back to the event". */
    var from = pathOf(document.referrer);
    document.addEventListener('click', function (ev) {
        var a = ev.target.closest && ev.target.closest('a[href]');
        if (! a || a.target === '_blank' || ev.defaultPrevented) return;
        var to = pathOf(a.href);
        if (! to || to === here) return;
        if (to === from || a.querySelector('.bi-chevron-left')) store(RETURN, to);
    }, true);

    /* Back into this page out of the browser's page cache (bfcache): the
       script does not run again, and the cache can hand back an older
       offset — so the one saved on the way out wins. */
    window.addEventListener('pageshow', function (e) {
        if (! e.persisted) return;
        var p = null;
        try { p = JSON.parse(read(KEY) || 'null'); } catch (x) {}
        if (p && p.y) window.scrollTo(0, p.y);
    });

    /* ---------------- Arriving ---------------- */
    var nav = (performance.getEntriesByType && performance.getEntriesByType('navigation')[0]) || null;
    var returned = (nav && nav.type === 'back_forward') || read(RETURN) === here;
    drop(RETURN);

    if (! returned) return;

    var place = null;
    try { place = JSON.parse(read(KEY) || 'null'); } catch (e) {}
    if (! place || ! place.y || (Date.now() - (place.at || 0)) > MAX_AGE) return;

    var target = place.y, tries = 0, stopped = false;
    function stop() { stopped = true; }
    ['touchstart', 'wheel', 'keydown', 'mousedown'].forEach(function (e) {
        window.addEventListener(e, stop, { once: true, passive: true });
    });

    function go() {
        if (stopped) return;
        window.scrollTo(0, target);
        var short = Math.abs((window.scrollY || 0) - target) > 2;
        /* Still short of it means the page has not finished growing. */
        if (short && ++tries < 40) setTimeout(go, 75);
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', go);
    else go();
    window.addEventListener('load', function () { tries = 0; go(); });
})();
</script>
