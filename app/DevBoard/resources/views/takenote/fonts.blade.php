{{--
    TakeNote's fonts: the pair a person reads and writes their notes in — one
    for English, one for Arabic — chosen in the Fonts sheet and kept on this
    device. Included INSIDE takenote/script's closure (shares h, icon, T, C).

    The typefaces are the certificates designer's own library
    (config('printables.fonts') → public/fonts/designer, via
    App\Printables\Support\Fonts), never a CDN: English from its sans, serif
    and mono groups, Arabic from its arabic group. Nothing is downloaded until
    it is needed — the chosen pair at start, and in the sheet only the rows
    that scroll into view (FontFace, one load per family).

    The pair becomes --note: "English", "Arabic", system fallbacks — so each
    letter takes the font of its own script, in the same line. The head
    (takenote/head) applies a stored pair before the page paints.
--}}
    /* ── fonts ───────────────────────────────────────────────────────── */
    var FONTS = { en: [], ar: [] }, FONT_DEFAULT = { en: 'Inter', ar: 'IBM Plex Sans Arabic' };
    (C.fonts || []).forEach(function (f) { (f.group === 'arabic' ? FONTS.ar : FONTS.en).push(f); });
    var loadedFonts = {};
    function fontEntry(lang, family) { return FONTS[lang].find(function (f) { return f.family === family; }) || null; }
    function safeFontUrl(u) {
        try { var x = new URL(u, location.href); return x.origin === location.origin && x.pathname.indexOf('/fonts/designer/') === 0 ? x.href : null; } catch (e) { return null; }
    }
    function loadFont(f) {
        if (!f || loadedFonts[f.family] || !window.FontFace) return;
        loadedFonts[f.family] = true;
        f.faces.forEach(function (face) {
            var u = safeFontUrl(face.url);
            if (!u) return;
            try {
                var ff = new FontFace(f.family, 'url(' + JSON.stringify(u) + ')', { weight: face.weight, style: face.style, display: 'swap' });
                document.fonts.add(ff); ff.load().catch(function () {});
            } catch (e) {}
        });
    }
    function fontStack(en, ar) {
        return JSON.stringify(en) + ', ' + JSON.stringify(ar) + ", system-ui, -apple-system, 'Segoe UI', Tahoma, sans-serif";
    }
    function readFontChoice() {
        var c = null;
        try { c = JSON.parse(localStorage.getItem('takenote.fonts') || 'null'); } catch (e) {}
        var en = c && c.en && fontEntry('en', c.en.family) ? c.en.family : FONT_DEFAULT.en;
        var ar = c && c.ar && fontEntry('ar', c.ar.family) ? c.ar.family : FONT_DEFAULT.ar;
        return { en: en, ar: ar };
    }
    var fontChoice = readFontChoice();
    function applyFonts(save) {
        var en = fontEntry('en', fontChoice.en), ar = fontEntry('ar', fontChoice.ar);
        loadFont(en); loadFont(ar);
        document.documentElement.style.setProperty('--note', fontStack(fontChoice.en, fontChoice.ar));
        if (!save) return;
        // The faces travel with the choice so the head can load them before the first paint.
        try { localStorage.setItem('takenote.fonts', JSON.stringify({ en: en, ar: ar })); } catch (e) {}
    }
    applyFonts(false);

    function openFonts() {
        if (document.querySelector('.ss')) return;
        var lang = 'en', group = 'all', q = '';
        var root = h('div', 'ss ss-fonts'); root.setAttribute('role', 'dialog'); root.setAttribute('aria-modal', 'true'); root.setAttribute('aria-labelledby', 'fs-title');
        var dim = h('div', 'ss-dim'), sheet = h('div', 'ss-sheet');
        var band = h('div', 'ss-band'), row = h('div', 'r');
        var tl = h('span', 'tile2'); tl.appendChild(icon('bi-fonts'));
        var txt = h('div'); txt.style.minWidth = '0'; txt.style.flex = '1';
        var h3 = h('h3', null, T.tn_fonts); h3.id = 'fs-title';
        txt.append(h3, h('p', null, T.tn_fonts_sub));
        var x = h('button', 'x'); x.type = 'button'; x.setAttribute('aria-label', T.close); x.appendChild(icon('bi-x-lg'));
        row.append(tl, txt, x);
        band.append(h('div', 'hd'), row);

        // What a note looks like in the pair, as it changes.
        var prev = h('div', 'fs-prev');
        var pT = h('b', null, T.tn_fonts_prev_title), pE = h('p', null, T.tn_fonts_sample_en), pA = h('p', null, T.tn_fonts_sample_ar);
        pA.dir = 'rtl';
        prev.append(pT, pE, pA);

        var tabs = h('div', 'fs-tabs'); tabs.setAttribute('role', 'tablist');
        var tabEn = h('button', null, T.tn_fonts_en), tabAr = h('button', null, T.tn_fonts_ar);
        [tabEn, tabAr].forEach(function (b, i) { b.type = 'button'; b.setAttribute('role', 'tab'); b.addEventListener('click', function () { lang = i ? 'ar' : 'en'; group = 'all'; q = ''; find.value = ''; paint(); }); });
        tabs.append(tabEn, tabAr);
        var tools = h('div', 'fs-tools');
        var search = h('label', 'fs-find'); search.appendChild(icon('bi-search'));
        var find = h('input'); find.type = 'search'; find.placeholder = T.tn_fonts_find; find.setAttribute('aria-label', T.tn_fonts_find);
        search.appendChild(find);
        var chips = h('div', 'fs-chips');
        tools.append(search, chips);
        var list = h('div', 'fs-list'); list.setAttribute('role', 'listbox');

        var body = h('div', 'ss-body fs-body');
        body.append(prev, tabs, tools, list);
        sheet.append(band, body);
        root.append(dim, sheet);
        document.body.appendChild(root);

        var seen = window.IntersectionObserver ? new IntersectionObserver(function (es) {
            es.forEach(function (e) { if (e.isIntersecting) { loadFont(e.target._font); seen.unobserve(e.target); } });
        }, { root: body, rootMargin: '200px 0px' }) : null;

        find.addEventListener('input', function () { q = find.value.trim().toLowerCase(); drawList(); });
        function drawChips() {
            var gs = lang === 'en' ? ['all', 'sans', 'serif', 'mono'] : [];
            chips.replaceChildren.apply(chips, gs.map(function (g) {
                var c = h('button', 'fs-chip' + (g === group ? ' is-on' : ''), T['tn_font_' + g]); c.type = 'button';
                c.addEventListener('click', function () { group = g; drawChips(); drawList(); });
                return c;
            }));
        }
        function drawList() {
            if (seen) seen.disconnect();
            var cur = fontChoice[lang];
            var items = FONTS[lang].filter(function (f) {
                return (group === 'all' || f.group === group) && (!q || f.family.toLowerCase().indexOf(q) !== -1);
            });
            list.replaceChildren.apply(list, items.map(function (f) {
                var b = h('button', 'fs-row' + (f.family === cur ? ' is-on' : '')); b.type = 'button';
                b.setAttribute('role', 'option'); b.setAttribute('aria-selected', String(f.family === cur));
                b._font = f;
                var face = JSON.stringify(f.family) + ', system-ui';
                var nm = h('span', 'nm', f.family); nm.style.fontFamily = face;
                var sm = h('span', 'sm', lang === 'en' ? T.tn_fonts_sample_en : T.tn_fonts_sample_ar); sm.style.fontFamily = face;
                if (lang === 'ar') sm.dir = 'rtl';
                var meta = h('span', 'gp', T['tn_font_' + f.group] || '');
                var ck = h('i', 'ck'); ck.appendChild(icon('bi-check-lg'));
                var tx = h('span', 'tx'); tx.append(nm, sm);
                b.append(tx, meta, ck);
                b.addEventListener('click', function () {
                    fontChoice[lang] = f.family;
                    applyFonts(true);
                    list.querySelectorAll('.fs-row').forEach(function (r) { var on = r === b; r.classList.toggle('is-on', on); r.setAttribute('aria-selected', String(on)); });
                });
                if (seen) seen.observe(b); else loadFont(f);
                return b;
            }));
            if (!items.length) list.appendChild(h('p', 'fs-none', T.tn_no_match));
        }
        function paint() {
            [tabEn, tabAr].forEach(function (b, i) { var on = (i ? 'ar' : 'en') === lang; b.classList.toggle('is-on', on); b.setAttribute('aria-selected', String(on)); });
            search.hidden = FONTS[lang].length < 10;
            drawChips(); drawList();
            body.scrollTop = 0;
        }
        paint();

        function close() { document.removeEventListener('keydown', esc, true); if (seen) seen.disconnect(); root.remove(); }
        function esc(e) { if (e.key === 'Escape' && document.activeElement !== find) { e.stopImmediatePropagation(); close(); } }
        document.addEventListener('keydown', esc, true);
        x.addEventListener('click', close);
        dim.addEventListener('click', close);
    }
    document.querySelectorAll('[data-fonts]').forEach(function (b) { b.addEventListener('click', openFonts); });
