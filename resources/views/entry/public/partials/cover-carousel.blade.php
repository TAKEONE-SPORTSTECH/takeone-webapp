{{--
    The cover's language carousel — behaviour.

    A faithful port of `drafts/HTML Templates/Cover Page + Language Selector.html`.
    The design's arithmetic is reproduced exactly: the same scale curve, the same
    inward-pull integral, the same 190px radius, the same 110ms settle, the same
    two synthesized sounds. What changed is the runtime, and only the runtime.

    ── Why it is not the draft's React ─────────────────────────────────────────

    The draft ships as a React 18 bundle. This platform's rule is that React is
    for state-heavy component work and never a parallel frontend architecture
    (CLAUDE.md → Frontend Technology Decision Rules), and the cover is one screen
    that already lives inside an Alpine component. The logic is ~150 lines of
    scroll maths with no state to speak of — a React runtime for it would be two
    frameworks on the most-hit public page on the platform, to draw one strip.

    ── The three things that had to change to wire it up ───────────────────────

    1. The draft carries its own hardcoded list of 67 languages and its own
       translations of the event. Both come from the server now — the languages
       from config/content_locales.php (the OWNER's list, including decisions
       recorded there that a draft must not overrule), the event's words from
       App\Translation. See App\Events\Support\CoverLanguages.

    2. The draft remembers the choice in `localStorage['ps-lang-selected']` and
       starts there. That is a second source of truth for the locale, which is
       exactly the bug reported on this page — the strip could open on a
       language the server was not serving. The strip opens on the ACTIVE
       locale, from the server, always.

    3. Selecting a card dispatches `cover-pick-language`, which is the event the
       language sheet already listens for — the draft was written against that
       contract, so nothing else had to move.
--}}

@once
@push('styles')
<style>
    /* Real rules, not Tailwind utilities: the compiled bundle carries no CSS
       for a class nobody has used before, so a utility invented here would
       render as nothing at all. */
    @keyframes rise { from { opacity:0; transform:translateY(14px); } to { opacity:1; transform:none; } }
    .ps-strip { scrollbar-width:none; }
    .ps-strip::-webkit-scrollbar { display:none; }
    /* ⚠️ The strip's arrows never mirror. They point at the ends of a physical
       strip, not along a reading order, so an RTL page must not turn them
       around — see the note in cover.blade.php. Pinned rather than merely
       omitted, because every other chevron on this platform DOES flip and the
       obvious edit here is to make these match.

       ⚠️⚠️ AND THE `::before` IS WHERE IT HAS TO BE PINNED.
       The platform's RTL rule does not transform the icon element — it
       transforms the icon's pseudo-element:

           [dir="rtl"] :is(… .bi-chevron-left, .bi-chevron-right …)::before
               { display: inline-block; transform: scaleX(-1); }

       A bootstrap icon IS its `::before` (the glyph is `content` on the
       pseudo), so pinning the element left that mirroring completely
       untouched and both arrows still turned round in Arabic — reported
       2026-09-10. The element is pinned as well, because `rtl:rotate-180`
       could be added to one of these buttons by hand at any time and it
       belongs to the element. */
    [data-cover-prev] .bi, [data-cover-next] .bi, [data-cover-enter] .bi,
    [data-cover-prev] .bi::before, [data-cover-next] .bi::before, [data-cover-enter] .bi::before {
        transform: none !important;
    }

    @media (prefers-reduced-motion: reduce) {
        .ps-card { transition:none !important; }
        [style*="animation:rise"] { animation:none !important; }
    }
</style>
@endpush
@endonce

@push('scripts')
<script>
(function () {
    /* The cover is teleported into <body> by Alpine, so this script runs before
       the strip exists. Everything below waits for it, and waits again for it to
       be VISIBLE — a display:none strip has clientWidth 0 and cannot be centred
       on anything. */
    var LANGS  = @json(collect($coverLangs)->values());
    var ACTIVE  = @js($coverActive);      /* where the strip opens */
    var SERVING = @js($coverServing);     /* what the page is already rendered in */

    if (! LANGS.length) return;

    var N = LANGS.length;
    /* How many copies of the list the strip actually rendered. Three when it
       loops, one when the list is too short to be worth looping — the decision
       and the threshold live in cover.blade.php, next to the markup that acts
       on them. */
    var COPIES = @js($coverLangs ? (count($coverLangs) >= 5 ? 3 : 1) : 1);
    var CENTER_SCALE = 1.18;
    var SIDE_SCALE   = 0.72;
    var R            = 190;

    var strip, cards = [], last, raf = null, settle = null, ac = null, booted = false;

    function el(sel) { return document.querySelector(sel); }

    /* ===== The look: every card scaled by its distance from the centre =====
       Reproduced from the draft unchanged. `D` integrates the width lost to
       scale() between the centre and each card, which is what makes the pull
       continuous in x and exactly 0 at the centre — no direction flips. */
    function update() {
        if (! strip) return;
        var mid = strip.scrollLeft + strip.clientWidth / 2;

        var D = function (u) {
            var a = Math.min(u, R);
            var d = (1 - CENTER_SCALE) * a + (CENTER_SCALE - SIDE_SCALE) * a * a / (2 * R);
            if (u > R) d += (1 - SIDE_SCALE) * (u - R);
            return d;
        };

        cards.forEach(function (c) {
            var centre = c.offsetLeft + c.offsetWidth / 2;
            var x = centre - mid;
            var t = Math.min(Math.abs(x) / R, 1);
            var scale = CENTER_SCALE - (CENTER_SCALE - SIDE_SCALE) * t;
            var shift = -Math.sign(x) * D(Math.abs(x));

            c.style.transform = 'translateX(' + shift.toFixed(1) + 'px) translateY(' + (t * 4).toFixed(1) + 'px) scale(' + scale.toFixed(3) + ')';
            c.style.opacity = (1 - t * 0.5).toFixed(2);
            c.style.zIndex = t < 0.25 ? '1' : '0';

            var on = t < 0.22;
            c.style.background = on ? '#ffffff' : 'rgba(255,255,255,.09)';
            c.style.color = on ? '#0b1220' : '#ffffff';
            c.style.borderColor = on ? '#ffffff' : 'rgba(255,255,255,.2)';
            c.style.boxShadow = on ? '0 18px 40px -14px rgba(0,0,0,.7), 0 0 0 4px rgba(22,119,255,.35)' : 'none';
        });
    }

    function nearest() {
        if (! strip) return 0;
        var mid = strip.scrollLeft + strip.clientWidth / 2, best = 0, bd = Infinity;
        cards.forEach(function (c, i) {
            var d = Math.abs(c.offsetLeft + c.offsetWidth / 2 - mid);
            if (d < bd) { bd = d; best = i; }
        });
        return best;
    }

    /* One card left or right. A looping strip wraps; a single-copy strip stops
       at its ends instead of scrolling to nothing. */
    function step(by) {
        var i = nearest() + by;

        if (COPIES > 1) return centerOn(i, true);

        centerOn(Math.max(0, Math.min(cards.length - 1, i)), true);
    }

    function paintArrows() {
        var i = nearest();
        var p = el('[data-cover-prev]'), n = el('[data-cover-next]');
        if (p) p.style.visibility = i <= 0 ? 'hidden' : 'visible';
        if (n) n.style.visibility = i >= cards.length - 1 ? 'hidden' : 'visible';
    }

    function centerOn(i, smooth) {
        var c = cards[i];
        if (! strip || ! c) return;
        strip.scrollTo({ left: c.offsetLeft + c.offsetWidth / 2 - strip.clientWidth / 2, behavior: smooth ? 'smooth' : 'auto' });
    }

    /* ===== The sounds — synthesized, no files, exactly the draft's ===== */
    function audio() {
        try {
            if (! ac) ac = new (window.AudioContext || window.webkitAudioContext)();
            if (ac.state === 'suspended') ac.resume();
            return ac;
        } catch (e) { return null; }   /* no WebAudio: the strip is silent, not broken */
    }

    function playTick() {
        var a = audio(); if (! a) return;
        var t = a.currentTime, o = a.createOscillator(), g = a.createGain(), f = a.createBiquadFilter();
        o.type = 'sine'; o.frequency.setValueAtTime(1180, t); o.frequency.exponentialRampToValueAtTime(880, t + 0.06);
        f.type = 'lowpass'; f.frequency.value = 4200;
        g.gain.setValueAtTime(0.0001, t);
        g.gain.exponentialRampToValueAtTime(0.16, t + 0.008);
        g.gain.exponentialRampToValueAtTime(0.0001, t + 0.09);
        o.connect(f); f.connect(g); g.connect(a.destination);
        o.start(t); o.stop(t + 0.1);
    }

    function playSelect() {
        var a = audio(); if (! a) return;
        var t = a.currentTime;
        [[660, 0], [990, 0.055]].forEach(function (pair) {
            var o = a.createOscillator(), g = a.createGain();
            o.type = 'triangle'; o.frequency.value = pair[0];
            g.gain.setValueAtTime(0.0001, t + pair[1]);
            g.gain.exponentialRampToValueAtTime(0.18, t + pair[1] + 0.01);
            g.gain.exponentialRampToValueAtTime(0.0001, t + pair[1] + 0.22);
            o.connect(g); g.connect(a.destination);
            o.start(t + pair[1]); o.stop(t + pair[1] + 0.24);
        });
    }

    /* Infinite loop: three copies are rendered; jump one copy-width when the
       scroll is IDLE — never mid-animation, or the jump is visible. */
    function loopCheck() {
        if (! strip || cards.length !== 3 * N) return;
        var W = cards[N].offsetLeft - cards[0].offsetLeft;
        var s = strip.scrollLeft;
        if (s < W * 0.5) strip.scrollLeft = s + W;
        else if (s > W * 1.75) strip.scrollLeft = s - W;
    }

    /* ===== The whole screen re-labels itself into the centred language ===== */
    function paint(i) {
        var lang = LANGS[i % N];
        if (! lang) return;

        var set = function (sel, value, dir) {
            var node = el(sel);
            if (! node) return;
            node.textContent = value || '';
            if (dir) node.setAttribute('dir', dir);
        };

        set('[data-cover-tag]', lang.tag);
        set('[data-cover-title]', lang.ev_title, lang.dir);
        set('[data-cover-date]', lang.ev_date);
        set('[data-cover-langlabel]', lang.label_language);
        set('[data-cover-searchlabel]', lang.label_search);
        set('[data-cover-enterlabel]', lang.label_enter);
        set('[data-cover-seltitle]', lang.title);
    }

    function onScroll() {
        if (! raf) raf = requestAnimationFrame(function () {
            raf = null;
            update();
            /* Tick the instant a new card crosses the centre — not on settle. */
            var i = nearest();
            if (i !== last) {
                if (last !== undefined && i % N !== last % N) playTick();
                last = i;
                paint(i);
            }
        });

        clearTimeout(settle);
        settle = setTimeout(function () {
            loopCheck();
            paint(nearest());
        }, 110);
    }

    function select(i) {
        var lang = LANGS[i % N];
        if (! lang) return;
        playSelect();
        /* The contract the language sheet already listens for. */
        window.dispatchEvent(new CustomEvent('cover-pick-language', {
            detail: { code: lang.code, native: lang.native }
        }));
    }

    function wire() {
        cards = Array.prototype.slice.call(strip.querySelectorAll('.ps-card'));

        strip.addEventListener('scroll', onScroll, { passive: true });

        strip.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowLeft')  { e.preventDefault(); step(-1); }
            if (e.key === 'ArrowRight') { e.preventDefault(); step(1); }
        });

        cards.forEach(function (c) {
            c.addEventListener('click', function () {
                var i = +c.dataset.index;
                /* A tap on the CENTRED card chooses it; a tap on any other
                   brings it to the centre first. Two taps to change your
                   language, one to confirm — the draft's behaviour, and the
                   reason a mis-tap while flicking cannot navigate. */
                if (i === nearest()) select(i);
                else centerOn(i, true);
            });
        });

        var prev = el('[data-cover-prev]'), next = el('[data-cover-next]');
        if (prev) prev.addEventListener('click', function () { step(-1); });
        if (next) next.addEventListener('click', function () { step(1); });

        /* With one copy the strip has ends, so the arrows are hidden at them
           rather than being controls that do nothing. A looping strip has no
           ends and they always work. */
        if (COPIES === 1) {
            strip.addEventListener('scroll', paintArrows, { passive: true });
            paintArrows();
        }

        var search = el('[data-cover-search]');
        if (search) search.addEventListener('click', function () {
            window.dispatchEvent(new CustomEvent('open-language-sheet'));
        });

        /* ===== Enter CONFIRMS the centred language =====

           ⚠️ It used to only dismiss the cover, which made the screen a lie:
           you scrolled to Albanian, the whole cover became Albanian, you
           pressed Enter — and got the page in whatever language the server had
           already decided on. Reported 2026-09-09: "when I select the language
           and press enter it's not changing the language."

           Scrolling a card to the centre IS the selection — that is what the
           design says, by re-labelling the entire screen as it lands — so Enter
           has to mean "yes, this one".

           Two outcomes, and the distinction is what stops a pointless reload:
             · already reading in that language  → just close the cover;
             · anything else                     → the same path as tapping the
               centred card, which prepares the translation and reloads. */
        var enter = el('[data-cover-enter]');
        if (enter) enter.addEventListener('click', function () {
            var i = nearest();
            var lang = LANGS[i % N];

            if (! lang || lang.code === SERVING) {
                window.dispatchEvent(new CustomEvent('cover-dismiss'));
                return;
            }

            select(i);
        });
    }

    /* Open on the language the SERVER is serving — never on a remembered
       client-side value. See note 2 at the top of this file. */
    function start() {
        var idx = LANGS.findIndex(function (l) { return l.code === ACTIVE; });
        if (idx < 0) idx = LANGS.findIndex(function (l) { return l.code === 'en'; });
        if (idx < 0) idx = 0;

        /* The MIDDLE copy when the strip loops, so it can be flicked both ways.
           With a single copy there is no middle and no room either side —
           opening at `N + idx` would land past the last card and centre
           nothing. */
        var from = COPIES > 1 ? N + idx : idx;

        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                centerOn(from, false);
                update();
                last = from;
                paint(from);
            });
        });
    }

    /* The strip is teleported and starts hidden, so wait for it to exist AND to
       have a width. Capped: a cover that never opens must not leave a rAF loop
       running for the life of the page. */
    function boot(tries) {
        strip = el('[data-cover-strip]');

        if (! strip || ! strip.clientWidth) {
            if ((tries || 0) < 240) requestAnimationFrame(function () { boot((tries || 0) + 1); });
            return;
        }

        if (! booted) { booted = true; wire(); }
        start();
    }

    document.addEventListener('DOMContentLoaded', function () { boot(0); });
    if (document.readyState !== 'loading') boot(0);

    /* Re-opened from the poster's band: the strip has been display:none, so its
       scroll position means nothing until it is measured again. */
    window.addEventListener('reopen-cover', function () { boot(0); });
})();
</script>
@endpush
