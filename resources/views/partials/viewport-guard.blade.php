{{--
    The viewport guard — every screen of the member app is exactly as tall as
    what the member can SEE, without anybody locking and waking the phone.

    ── The fault it repairs ───────────────────────────────────────────────────
    The member app (bh.takeone.app) is a WebView inside a Flutter shell that
    turns the Android system bars on as it starts. The WebView is laid out
    first, at the full height of the screen, and Android shrinks it to fit
    between the bars a moment later — but the page inside is not told until
    the phone is locked and woken (reported 2026-09-27: the event cover's
    language buttons sat half below the screen until then). Until that wake the
    page believes it is taller than it is: `100vh`, `100dvh`, `bottom: 0` and
    every sheet's `max-h-[92vh]` all measure against the height that is no
    longer there, and the bottom of the screen is cut off.

    ── Why the page can see it ────────────────────────────────────────────────
    A view inside the app can never be taller than the screen's usable area,
    `screen.availHeight` (the screen less the system bars). So when the page's
    own height is bigger than that, the difference is exactly what is hidden
    under the bottom bar — a physical impossibility, never a legitimate layout.
    That is the whole detector. Where a WebView reports availHeight as the full
    screen, the difference is zero and the guard simply does nothing.

    ── What it does about it ──────────────────────────────────────────────────
      · keeps `--app-h` (the visible height, px) and `--app-vh` (1% of it) on
        <html>, re-measured on load, over the first seconds while the bars
        settle, and on every resize, rotation, wake and return;
      · while — and only while — the fault is present inside the app, it
        rewrites every viewport-height rule in the page's own stylesheets to
        those variables (generated from the sheets themselves, so a class added
        next month is covered without anybody listing it), does the same for
        inline `style="…vh"` on elements as they appear, and lifts
        bottom-pinned fixed bars (tab bars, sheet footers) above the hidden
        strip;
      · the moment the page is told its real size (the wake), the difference
        is zero and every correction is removed again.

    Outside the app it only publishes the two variables and changes nothing —
    a browser measures its own viewport correctly, and rewriting `vh` there
    would make full-height sections jump as the URL bar slides.

    Standalone, no dependencies, guarded so a page that includes it twice runs
    it once. Included in the <head> of every root layout the member app
    renders (layouts/app, layouts/tailwind, the event layout, and the
    standalone registration / claim / bout pages).
--}}
<script>
(function () {
    if (window.__viewportGuard) return;
    window.__viewportGuard = true;

    var root = document.documentElement;
    var ua = navigator.userAgent || '';
    /* The member app: the Flutter shell's Capacitor-shaped bridge, or any
       Android WebView (`; wv)` is in every WebView's user agent). */
    var inApp = function () {
        try { if (window.Capacitor && window.Capacitor.isNativePlatform && window.Capacitor.isNativePlatform()) return true; } catch (e) {}
        return /; wv\)/.test(ua);
    };

    var VH = /(-?\d*\.?\d+)(d|s|l)?vh\b/g;
    var sheet = null;          // the generated override <style>
    var active = false;        // is a correction applied right now?
    var cut = 0;               // px hidden below the bottom bar
    var lifted = [];           // fixed bars we moved, to put back

    function rewrite(value) {
        return value.replace(VH, function (_, n) { return 'calc(var(--app-vh) * ' + n + ')'; });
    }

    /* Every rule in every same-origin sheet that measures in vh, re-written to
       the measured height. Cross-origin sheets (fonts, icons) cannot be read and
       carry no page layout anyway. */
    function buildOverrides() {
        var out = [];
        var walk = function (rules, wrapOpen, wrapClose) {
            for (var i = 0; i < rules.length; i++) {
                var r = rules[i];
                if (r.cssRules && r.media) { walk(r.cssRules, '@media ' + r.media.mediaText + '{', '}'); continue; }
                if (r.cssRules && r.conditionText !== undefined) { walk(r.cssRules, '@supports ' + r.conditionText + '{', '}'); continue; }
                if (! r.style || ! r.selectorText || r.cssText.indexOf('vh') === -1) continue;
                var decl = [];
                for (var j = 0; j < r.style.length; j++) {
                    var prop = r.style[j];
                    var val = r.style.getPropertyValue(prop);
                    if (VH.test(val)) { VH.lastIndex = 0; decl.push(prop + ':' + rewrite(val) + ' !important'); }
                    VH.lastIndex = 0;
                }
                if (decl.length) out.push(wrapOpen + r.selectorText + '{' + decl.join(';') + '}' + wrapClose);
            }
        };
        for (var s = 0; s < document.styleSheets.length; s++) {
            var ss = document.styleSheets[s];
            if (ss.ownerNode && ss.ownerNode.id === 'viewport-guard-css') continue;
            var rules = null;
            try { rules = ss.cssRules; } catch (e) { continue; }
            if (rules) walk(rules, '', '');
        }
        return out.join('\n');
    }

    /* Inline `style="height:100dvh"` — the event cover is one — cannot be
       reached by a stylesheet, so the element itself is corrected, and the
       original kept to put back. */
    function fixInline(el) {
        var s = el.getAttribute && el.getAttribute('style');
        if (! s || s.indexOf('vh') === -1 || el.hasAttribute('data-vg-style')) return;
        // The event cover sizes itself from --app-h already (cover-script).
        if (el.hasAttribute('data-cover-screen')) return;
        el.setAttribute('data-vg-style', s);
        el.setAttribute('style', rewrite(s));
    }
    function restoreInline() {
        document.querySelectorAll('[data-vg-style]').forEach(function (el) {
            el.setAttribute('style', el.getAttribute('data-vg-style'));
            el.removeAttribute('data-vg-style');
        });
    }

    /* A bar pinned to the bottom edge sits in the hidden strip; lift it above. */
    function liftBars() {
        dropBars();
        document.querySelectorAll('body *').forEach(function (el) {
            var cs = getComputedStyle(el);
            if (cs.position !== 'fixed' || cs.bottom !== '0px' || cs.display === 'none') return;
            lifted.push([el, el.style.bottom]);
            el.style.bottom = cut + 'px';
        });
    }
    function dropBars() {
        lifted.forEach(function (x) { x[0].style.bottom = x[1]; });
        lifted = [];
    }

    var observer = null;

    function activate() {
        if (! sheet) {
            sheet = document.createElement('style');
            sheet.id = 'viewport-guard-css';
            (document.head || root).appendChild(sheet);
        }
        sheet.textContent = buildOverrides();
        document.querySelectorAll('[style*="vh"]').forEach(fixInline);
        liftBars();
        if (! observer && window.MutationObserver) {
            /* Sheets and bars teleported in later (Alpine) get the same answer. */
            observer = new MutationObserver(function (list) {
                if (! active) return;
                var again = false;
                list.forEach(function (m) {
                    m.addedNodes.forEach(function (n) {
                        if (n.nodeType !== 1) return;
                        fixInline(n);
                        n.querySelectorAll && n.querySelectorAll('[style*="vh"]').forEach(fixInline);
                        again = true;
                    });
                });
                if (again) { clearTimeout(observer._t); observer._t = setTimeout(liftBars, 60); }
            });
            observer.observe(document.body || root, { childList: true, subtree: true });
        }
        active = true;
    }

    function deactivate() {
        if (sheet) sheet.textContent = '';
        restoreInline();
        dropBars();
        active = false;
    }

    function measure() {
        var h = Math.round(window.innerHeight || root.clientHeight || 0);
        if (h < 200) return;   // a WebView mid-layout — wait for the next pass

        var visible = h;
        if (inApp()) {
            var avail = Math.round((window.screen && window.screen.availHeight) || 0);
            /* Taller than the usable screen is impossible for a view inside the
               app — the difference is what the bottom bar is hiding. */
            if (avail >= 200 && h > avail + 2) visible = avail;
        }

        var changed = (cut !== h - visible) || root.style.getPropertyValue('--app-h') !== visible + 'px';
        cut = h - visible;
        root.style.setProperty('--app-h', visible + 'px');
        root.style.setProperty('--app-vh', (visible / 100) + 'px');

        if (cut > 0) activate();
        else if (active) deactivate();

        /* Components that size themselves from --app-h re-read it. */
        if (changed) window.dispatchEvent(new CustomEvent('viewport-guard:change', { detail: { height: visible, cut: cut } }));
    }

    window.ViewportGuard = { measure: measure, get cut() { return cut; } };

    measure();
    document.addEventListener('DOMContentLoaded', measure);
    window.addEventListener('load', measure);
    [120, 400, 900, 1600, 3000].forEach(function (ms) { setTimeout(measure, ms); });
    window.addEventListener('resize', measure, { passive: true });
    window.addEventListener('orientationchange', function () { setTimeout(measure, 250); });
    window.addEventListener('pageshow', measure);
    document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'visible') setTimeout(measure, 60); });
    if (window.visualViewport) window.visualViewport.addEventListener('resize', measure, { passive: true });
})();
</script>
