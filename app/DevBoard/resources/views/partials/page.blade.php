{{--
    The note page: ONE full-screen page for reading and writing a note, used
    by the board and by TakeNote. Include it once, after partials/editor; it
    defines window.MarginPage and owns its own styles.

    It behaves the way good notes apps do (researched 27 Oct 2026):
      · an existing note opens for READING — no keyboard, no toolbar; a large
        title, then who changed it last and when, then the text at a
        comfortable measure;
      · tap the words (or the pencil) to edit: the caret lands where you
        tapped, the tools rise above the keyboard (across the top on a desk),
        and Done puts them away; saving is automatic throughout;
      · on a long note the title shrinks into the top bar and a button takes
        you back to the top;
      · a new note opens ready to type.

      MarginPage.open({
        T, locale,
        editor: { …MarginEditor.create options except onEdit },
        saver:  { send, exists, saved, conflict, fatal },   (MarginEditor.saver options)
        meta:   { lane: {name, color}, who: {name, ink}, when: iso },
        load:   () => Promise<{ html, media, meta }>         (optional: fetch first)
        fresh:  true → open in writing mode
        crumb:  'Margin · Ideas'                             (shown on a desk; or crumbEl: an element instead)
        menu:   [{ icon, label, href } | { icon, label, onClick, danger }],
        onLane(page)                                         (the lane chip becomes a button)
        after(page)                                          (run once mounted)
        onClose(page)
        titleField: true                                     (optional: a Title box above the text. It IS the
                                                              note's first heading — `<h1>` — so what is stored,
                                                              listed and searched is unchanged)
        — for a CARD of the board (Trello's card back), all optional:
        window: true          a window over the board on a desk (full screen on a phone)
        cover:  url           a picture across the top (page.setCover(url, colour) changes it)
        coverColor: '#hex'    or a colour band across the top instead (a card's colour cover)
        chips(page) → el      the card's facts under its title, in place of the "edited by" line
        below:  el            after the text: attachments, checklists (hidden while writing)
        aside:  el            comments and activity — beside the page on a desk, under it on a phone
        onLink(href) → true    a link in the text is the host's to follow (TakeNote: a link to another note opens it)
        tapToWrite: false     reading stays reading: only the pen starts writing (TakeNote's notes; default true)
        host:   element                                      (optional: open INSIDE it — a desk's reading
                                                              pane — instead of over the whole screen; no
                                                              history entry then, the host owns navigation)
      }) → page { editor, saver, setMeta(meta), setTitle(), setFooter(el), close(), editing(), el }

    setFooter(el) puts one action at the foot of the page while READING (e.g.
    "Send to the board"); it gives way to the keyboard and the tools when writing.

    The page pushes one history entry, so the phone's Back closes it.
--}}
<style>
.mp { position: fixed; z-index: 90; inset: 0; display: flex; flex-direction: column; background: var(--paper, #fbf7ef); color: var(--ink, #1d1530);
    transform: translateX(100%); visibility: hidden; transition: transform .36s cubic-bezier(.2,.8,.2,1), visibility 0s .36s; }
[dir=rtl] .mp { transform: translateX(-100%); }
.mp.is-hosted { position: absolute; z-index: 20; transform: translateY(10px); opacity: 0; transition: transform .26s cubic-bezier(.2,.8,.2,1), opacity .2s, visibility 0s .26s; }
.mp.is-hosted.is-open { transform: none; opacity: 1; transition: transform .26s cubic-bezier(.2,.8,.2,1), opacity .2s; }
.mp.is-open { transform: none; visibility: visible; transition: transform .36s cubic-bezier(.2,.8,.2,1); }
.mp-bar { flex-shrink: 0; display: flex; align-items: center; gap: 8px; padding: calc(8px + var(--sa-t, env(safe-area-inset-top, 0px))) 14px 8px; position: relative; z-index: 3;
    background: var(--paper, #fbf7ef); transition: box-shadow .2s; }
.mp.is-scrolled .mp-bar, .mp.is-editing .mp-bar { box-shadow: 0 1px 0 rgba(29,21,48,.08); }
.mp-round { width: 38px; height: 38px; border-radius: 50%; display: grid; place-items: center; flex-shrink: 0; font-size: 16px; color: inherit;
    background: rgba(29,21,48,.06); border: 1px solid rgba(29,21,48,.1); transition: transform .15s; }
.mp-round:active { transform: scale(.9); }
.mp-round[hidden] { display: none; }
.mp-crumb { display: none; font-size: 12.5px; color: var(--ink-soft, #6b6280); white-space: nowrap; }
.mp-mini { flex: 1; min-width: 0; text-align: center; font: 400 18px/1.1 var(--serif, Georgia, serif); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    opacity: 0; transform: translateY(4px); transition: opacity .2s, transform .2s; }
.mp.is-scrolled:not(.is-editing) .mp-mini { opacity: 1; transform: none; }
.mp-state { font-size: 11.5px; font-weight: 600; color: var(--ink-soft, #6b6280); display: none; align-items: center; gap: 5px; white-space: nowrap; }
.mp.is-editing .mp-state { display: inline-flex; }
.mp-state .bi-check2 { color: #16a394; font-size: 13px; }
.mp-state.is-warn { color: #b45309; }
.mp-state .spin { width: 10px; height: 10px; border-radius: 50%; border: 1.6px solid currentColor; border-top-color: transparent; animation: mp-spin .8s linear infinite; }
@keyframes mp-spin { to { transform: rotate(360deg); } }
.mp-done { height: 36px; padding: 0 15px; border-radius: 999px; background: var(--primary, #7c6bf5); color: #fff; font-weight: 700; font-size: 13px; display: none; align-items: center; gap: 6px; border: 0; }
.mp.is-editing .mp-done { display: inline-flex; }
.mp.is-editing .mp-edit, .mp.is-editing .mp-more { display: none; }
.mp-scroll { flex: 1; min-height: 0; overflow-y: auto; -webkit-overflow-scrolling: touch; overscroll-behavior: contain; position: relative; }
.mp-col { position: relative; max-width: 680px; margin: 0 auto; padding: 8px 22px 120px; }
.mp .ed { min-height: 50vh; font: 400 16px/1.7 var(--sans, system-ui); cursor: text; }
.mp .ed[contenteditable=false] { cursor: default; -webkit-user-select: text; user-select: text; }
.mp .ed > h1:first-child { font-size: 34px; line-height: 1.04; margin: 6px 0 10px; }
.mp-title { display: block; width: 100%; margin: 6px 0 12px; padding: 0; border: 0; outline: 0; resize: none; overflow: hidden; background: none; color: inherit;
    font: 400 34px/1.08 var(--serif, Georgia, serif); unicode-bidi: plaintext; text-align: start; }
.mp-title::placeholder { color: rgba(29,21,48,.28); }
.mp-title[readonly] { cursor: text; }
.mp .ed p, .mp .ed li { font-size: 16px; line-height: 1.7; }
.mp .mp-meta { display: flex; flex-wrap: wrap; align-items: center; gap: 6px 10px; margin: 0 0 18px; padding-bottom: 14px; border-bottom: 1px solid rgba(29,21,48,.08);
    font: 500 12px/1.4 var(--sans, system-ui); color: var(--ink-soft, #6b6280); -webkit-user-select: none; user-select: none; }
.mp-lane { display: inline-flex; align-items: center; gap: 6px; height: 24px; padding: 0 10px; border-radius: 999px; font-weight: 700; color: var(--lc); background: color-mix(in srgb, var(--lc) 13%, transparent); }
.mp-lane::before { content: ''; width: 7px; height: 7px; border-radius: 50%; background: var(--lc); }
button.mp-lane { border: 1px solid color-mix(in srgb, var(--lc) 30%, transparent); cursor: pointer; font: inherit; font-weight: 700; }
button.mp-lane::after { content: ''; width: 6px; height: 6px; margin-inline-start: 2px; border-right: 1.6px solid currentColor; border-bottom: 1.6px solid currentColor; transform: rotate(45deg) translateY(-2px); opacity: .7; }
.mp-foot { position: absolute; z-index: 4; left: 0; right: 0; bottom: 0; padding: 26px max(16px, var(--sa-l, env(safe-area-inset-left, 0px))) calc(16px + var(--sa-b, env(safe-area-inset-bottom, 0px)));
    background: linear-gradient(transparent, var(--paper, #fbf7ef) 38%); display: flex; justify-content: center; pointer-events: none; }
.mp-foot > * { pointer-events: auto; width: min(100%, 520px); }
.mp-foot[hidden], .mp.is-editing .mp-foot { display: none; }
.mp.has-foot .mp-top { bottom: calc(92px + var(--sa-b, env(safe-area-inset-bottom, 0px))); }
.mp-who { display: inline-flex; align-items: center; gap: 6px; }
.mp-av { width: 20px; height: 20px; border-radius: 50%; display: grid; place-items: center; font-size: 9.5px; font-weight: 800; color: #fff; background: var(--c, #7c6bf5); }
.mp-top { position: absolute; z-index: 4; inset-inline-end: 18px; bottom: calc(22px + var(--sa-b, env(safe-area-inset-bottom, 0px))); width: 46px; height: 46px; border-radius: 16px; border: 0;
    display: grid; place-items: center; font-size: 18px; color: #fff; background: var(--primary, #7c6bf5); box-shadow: 0 12px 22px -8px rgba(124,107,245,.9);
    opacity: 0; transform: translateY(12px) scale(.9); pointer-events: none; transition: opacity .25s, transform .25s; }
.mp.is-far:not(.is-editing) .mp-top { opacity: 1; transform: none; pointer-events: auto; }
.mp-tools { flex-shrink: 0; display: none; }
.mp.is-editing .mp-tools { display: block; }
.mp-tools .tools { padding-bottom: calc(6px + var(--kb-safe, var(--sa-b, env(safe-area-inset-bottom, 0px)))); }
.mp-menu { position: absolute; z-index: 6; top: calc(54px + var(--sa-t, env(safe-area-inset-top, 0px))); inset-inline-end: 14px; min-width: 220px; padding: 6px; border-radius: 16px; background: #fff;
    box-shadow: 0 20px 40px -12px rgba(29,21,48,.45), 0 0 0 1px rgba(29,21,48,.06); animation: mp-rise .2s cubic-bezier(.2,.9,.25,1.15); }
.mp-menu[hidden] { display: none; }
@keyframes mp-rise { from { opacity: 0; transform: translateY(-6px); } }
.mp-menu a, .mp-menu button { width: 100%; display: flex; align-items: center; gap: 10px; padding: 11px 12px; border-radius: 11px; font-size: 13.5px; font-weight: 600; color: inherit;
    text-decoration: none; text-align: start; background: none; border: 0; }
.mp-menu a:active, .mp-menu button:active { background: rgba(124,107,245,.1); }
.mp-menu .is-danger { color: #dc2626; }
.mp.is-loading .mp-col { min-height: 60vh; background: linear-gradient(100deg, transparent 30%, rgba(255,255,255,.7) 50%, transparent 70%) 0 0/300% 100%; animation: mp-shim 1.2s linear infinite; }
.mp.is-loading .ed { visibility: hidden; }
@keyframes mp-shim { to { background-position: -300% 0; } }

/* A desk: a centred page, the crumb in the bar, the tools across the top. */
@media (min-width: 900px) {
    .mp-crumb { display: inline; }
    .mp .ed > h1:first-child, .mp-title { font-size: 44px; }
    .mp .ed p, .mp .ed li { font-size: 17px; line-height: 1.72; }
    .mp-col { padding-top: 34px; }
    .mp-tools .tools { width: max-content; max-width: calc(100% - 40px); margin: 12px auto 0; border-radius: 14px; padding: 6px; }
    .mp-tools .tools-row { flex-wrap: wrap; justify-content: center; row-gap: 4px; overflow: visible; } /* a narrow desk: a second line, never cut off */
}
/* ── A card of the board (window, cover, chips, below, aside) ─────────── */
.mp-cover { height: 150px; overflow: hidden; background: #2c2463; }
.mp-cover[hidden], .mp.is-editing .mp-cover, .mp.is-editing .mp-below { display: none; }
.mp-cover img { width: 100%; height: 100%; object-fit: cover; display: block; }
.mp-cover.is-colour, .mp.is-window .mp-cover.is-colour { height: 96px; }
.mp-body { flex: 1; min-height: 0; display: flex; }
.mp-main { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.mp-main > .mp-scroll { flex: 1; }
.mp-col > .mp-aside { margin-top: 30px; }
.mp-dim { display: none; }
@media (min-width: 900px) {
    .mp-dim { display: block; position: fixed; inset: 0; z-index: 89; background: rgba(12,8,24,.55); animation: mp-fade .2s; transition: opacity .25s; }
    .mp-dim.is-gone { opacity: 0; }
    /* overflow: hidden — the bar, the cover and the comments column have their own backgrounds and
       would otherwise paint over three of the four rounded corners. Pop-ups live on <body>, so nothing is cut. */
    .mp.is-window { inset: 28px max(40px, calc((100vw - 1320px) / 2)); border-radius: 20px; overflow: hidden; box-shadow: 0 40px 80px -24px rgba(0,0,0,.75); transform: translateY(18px) scale(.985); opacity: 0;
        transition: transform .28s cubic-bezier(.2,.8,.2,1), opacity .2s, visibility 0s .28s; }
    .mp.is-window.is-open { transform: none; opacity: 1; transition: transform .28s cubic-bezier(.2,.8,.2,1), opacity .2s; }
    .mp.is-window .mp-col { max-width: 860px; padding-inline: 44px; }
    .mp.is-window .mp-cover { height: 160px; }
    .mp-body > .mp-aside { width: 380px; flex-shrink: 0; min-height: 0; display: flex; flex-direction: column; background: #f4f0e7; border-inline-start: 1px solid #ece6d9; }
}
@keyframes mp-fade { from { opacity: 0; } }
@media (prefers-reduced-motion: reduce) { .mp, .mp.is-open { transition: none; } }
</style>
<script>
(function () {
    'use strict';
    if (window.MarginPage) return;

    var DESK = window.matchMedia('(min-width: 900px)');
    var current = null;

    function h(tag, cls, text) {
        var el = document.createElement(tag);
        if (cls) el.className = cls;
        if (text != null) el.textContent = text;
        return el;
    }
    function icon(name) { return h('i', 'bi ' + name); }
    function btn(cls, ic, label) {
        var b = h('button', cls); b.type = 'button';
        if (ic) b.appendChild(icon(ic));
        if (label) { b.setAttribute('aria-label', label); b.title = label; }
        return b;
    }
    function when(iso, locale, T) {
        if (!iso) return '';
        var d = new Date(iso), s = (Date.now() - d.getTime()) / 1000;
        if (s < 60) return T.now;
        var time = d.toLocaleTimeString(locale, { hour: '2-digit', minute: '2-digit' });
        if (new Date().toDateString() === d.toDateString()) return time;
        return d.toLocaleDateString(locale, { day: 'numeric', month: 'short' }) + ' ' + time;
    }
    function caretAt(x, y) {
        if (document.caretRangeFromPoint) return document.caretRangeFromPoint(x, y);
        if (document.caretPositionFromPoint) {
            var p = document.caretPositionFromPoint(x, y);
            if (!p) return null;
            var r = document.createRange(); r.setStart(p.offsetNode, p.offset); r.collapse(true);
            return r;
        }
        return null;
    }

    function open(o) {
        if (current) current.close();
        var T = o.T, locale = o.locale || undefined;
        var page = { editor: null, saver: null, el: null };
        var meta = o.meta || {}, editing = !!o.fresh, closed = false, metaEl = null;

        var root = h('section', 'mp' + (o.host ? ' is-hosted' : '') + (o.window ? ' is-window' : '') + (o.aside ? ' has-aside' : ''));
        var dim = o.window ? h('div', 'mp-dim') : null; // a desk's window sits on the dimmed board; a tap on it closes
        root.setAttribute('role', 'dialog'); root.setAttribute('aria-modal', 'true'); root.setAttribute('aria-label', T.note);
        var bar = h('header', 'mp-bar');
        var back = btn('mp-round', 'bi-chevron-left flip', T.tn_back || T.back);
        var crumb = o.crumbEl || h('span', 'mp-crumb', o.crumb || ''); // a card puts its list here (a button that moves it)
        var mini = h('span', 'mp-mini');
        var state = h('span', 'mp-state');
        var edit = btn('mp-round mp-edit', 'bi-pencil', T.mp_edit);
        var more = btn('mp-round mp-more', 'bi-three-dots', T.tn_more);
        var done = h('button', 'mp-done'); done.type = 'button';
        done.append(icon('bi-check-lg'), document.createTextNode(T.ink_done));
        bar.append(back, crumb, mini, state, edit, more, done);
        if (!o.menu || !o.menu.length) more.hidden = true;

        var toolsSlot = h('div', 'mp-tools');
        var scroll = h('div', 'mp-scroll');
        var col = h('article', 'mp-col');
        var titleEl = null;
        if (o.titleField) {
            titleEl = h('textarea', 'mp-title'); titleEl.rows = 1;
            titleEl.placeholder = T.note_title_ph; titleEl.setAttribute('aria-label', T.note_title_ph);
            titleEl.maxLength = 300; titleEl.setAttribute('enterkeyhint', 'next');
        }
        var top = btn('mp-top', 'bi-arrow-up', T.mp_top);
        var coverEl = h('div', 'mp-cover'); coverEl.hidden = true;
        scroll.append(coverEl, col);
        var main = o.aside ? h('div', 'mp-main') : null, body = o.aside ? h('div', 'mp-body') : null;
        if (main) { main.appendChild(scroll); body.appendChild(main); }
        var menu = h('div', 'mp-menu'); menu.hidden = true; menu.setAttribute('role', 'menu');
        (o.menu || []).forEach(function (m) {
            var item = m.href ? h('a') : h('button');
            if (m.href) item.href = m.href; else item.type = 'button';
            item.setAttribute('role', 'menuitem');
            if (m.danger) item.className = 'is-danger';
            item.append(icon(m.icon), document.createTextNode(m.label));
            if (m.onClick) item.addEventListener('click', function () { menu.hidden = true; m.onClick(page); });
            menu.appendChild(item);
        });
        var foot = h('div', 'mp-foot'); foot.hidden = true;
        root.append(bar, toolsSlot, body || scroll, menu, top, foot);
        if (dim) { dim.addEventListener('click', function () { page.close(); }); document.body.appendChild(dim); }
        (o.host || document.body).appendChild(root);
        page.el = root;
        placeTools();
        page.setCover = function (url, color) {
            coverEl.replaceChildren();
            coverEl.hidden = !url && !color;
            coverEl.classList.toggle('is-colour', !url && !!color); // a colour cover (Trello's): a band in that colour
            coverEl.style.background = !url && color ? color : '';
            if (url) { var im = h('img'); im.alt = ''; im.src = url; im.draggable = false; coverEl.appendChild(im); }
        };
        page.setCover(o.cover || null, o.coverColor || null);

        /* The editor and its autosave — the page decides when it is editable. */
        function mount(html, media) {
            if (titleEl) { var split = splitTitle(html); titleEl.value = split.title; html = split.body; }
            var opts = Object.assign({}, o.editor, { html: html, media: media || (o.editor && o.editor.media) || {}, onEdit: function () { page.saver.schedule(); },
                // the toolbar's emoji go into the title when the title is where the cursor last was
                emojiTarget: function () { return titleEl && lastField === titleEl ? titleEl : null; } });
            page.editor = MarginEditor.create(opts);
            page.editor.el.setAttribute('aria-label', T.note);
            if (titleEl) {
                // What is saved is the title as the note's first heading, then the text.
                var body = page.editor.serialise;
                page.editor.serialise = function () {
                    var t = titleEl.value.replace(/\s+/g, ' ').trim(), rest = body();
                    return t ? '<h1>' + esc(t) + '</h1>' + rest : rest;
                };
            }
            page.saver = MarginEditor.saver(Object.assign({}, o.saver, { editor: page.editor, state: setState }));
            page.editor.el.addEventListener('focus', function () { lastField = page.editor.el; });
            if (titleEl) { col.replaceChildren(titleEl, page.editor.el); grow(); } else col.replaceChildren(page.editor.el);
            if (o.below) { o.below.classList.add('mp-below'); col.appendChild(o.below); }
            placeAside();
            toolsSlot.replaceChildren(page.editor.toolbar);
            setTitle();
            if (editing) startEditing(null, true); else stopEditing(true);
        }

        /* The Title box holds the note's first heading; the rest is the text below it. */
        function splitTitle(html) {
            var box = document.implementation.createHTMLDocument('').body; box.innerHTML = html || ''; // an inert document: nothing in it loads or runs
            var first = box.firstElementChild;
            if (first && first.tagName === 'H1' && !first.querySelector('img')) {
                var t = first.textContent.replace(/\s+/g, ' ').trim();
                first.remove();
                return { title: t, body: box.innerHTML };
            }
            return { title: '', body: html || '' };
        }
        function esc(t) { return t.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }
        function grow() { if (!titleEl) return; titleEl.style.height = 'auto'; titleEl.style.height = titleEl.scrollHeight + 'px'; }
        var lastField = null; // the title or the text: where the cursor last was (the emoji go there)
        if (titleEl) {
            titleEl.addEventListener('focus', function () { lastField = titleEl; });
            ['input', 'select', 'keyup', 'click'].forEach(function (ev) { titleEl.addEventListener(ev, function () { titleEl._caret = [titleEl.selectionStart, titleEl.selectionEnd]; }); });
            titleEl.addEventListener('input', function () { grow(); setTitle(); if (page.saver) page.saver.schedule(); });
            titleEl.addEventListener('keydown', function (e) {
                if (e.key !== 'Enter' || e.isComposing) return;
                e.preventDefault(); // one line; Enter goes on to the text
                if (page.editor) page.editor.focus(false);
            });
            window.addEventListener('resize', grow);
        }
        page.focusTitle = function () { if (!titleEl) return false; if (!editing) startEditing(null, true); titleEl.focus(); return true; };

        function setTitle() {
            if (titleEl) { mini.textContent = titleEl.value.trim() || T.note_untitled; return; }
            var first = page.editor && page.editor.el.firstElementChild;
            if (first && first.dataset.ui) first = first.nextElementSibling;
            mini.textContent = (first && first.textContent.trim()) || T.note_untitled;
        }
        function drawMeta() {
            if (metaEl) { metaEl.remove(); metaEl = null; }
            if (editing || !page.editor) return;
            var m = o.chips ? o.chips(page) : h('div', 'mp-meta');
            if (!m) return;
            m.dataset.ui = '1'; // never part of the note: the editor leaves [data-ui] out of what it saves
            m.contentEditable = 'false';
            if (!o.chips && meta.lane) {
                var l = h(o.onLane ? 'button' : 'span', 'mp-lane', meta.lane.name); l.style.setProperty('--lc', meta.lane.color);
                if (o.onLane) { l.type = 'button'; l.addEventListener('click', function (e) { e.stopPropagation(); o.onLane(page); }); }
                m.appendChild(l);
            }
            if (!o.chips && meta.who) {
                var w = h('span', 'mp-who');
                var av = h('span', 'mp-av', (meta.who.name || '?').charAt(0).toUpperCase()); av.style.setProperty('--c', meta.who.ink);
                w.append(av, document.createTextNode(T.edited_by.replace(':name', meta.who.name).replace(':when', when(meta.when, locale, T))));
                m.appendChild(w);
            }
            if (!m.childNodes.length) return;
            var ed = page.editor.el, first = ed.firstElementChild;
            if (titleEl) titleEl.after(m); // between the title and the text, never inside the text
            else if (first && /^H[1-3]$/.test(first.tagName)) first.after(m); else ed.insertBefore(m, ed.firstChild);
            metaEl = m;
        }
        function setState(s) {
            state.classList.toggle('is-warn', s === 'offline');
            var label = { new: T.new_note, saving: T.note_saving, saved: T.note_saved, offline: T.note_offline }[s] || '';
            state.replaceChildren(s === 'saving' ? h('span', 'spin') : icon(s === 'offline' ? 'bi-cloud-slash' : (s === 'new' ? 'bi-pencil' : 'bi-check2')), document.createTextNode(label));
            if (s === 'saved') setTitle();
        }

        function startEditing(range, quiet) {
            editing = true;
            if (metaEl) { metaEl.remove(); metaEl = null; }
            root.classList.add('is-editing');
            page.editor.el.contentEditable = 'true';
            if (titleEl) titleEl.readOnly = false;
            setState(o.saver.exists() ? 'saved' : 'new');
            fit();
            if (quiet) return; // a new note: the host decides what comes first (typing, a photo, a recording…)
            if (range && page.editor.el.contains(range.startContainer)) {
                page.editor.el.focus({ preventScroll: true });
                var sel = window.getSelection(); sel.removeAllRanges(); sel.addRange(range);
            } else {
                page.editor.focus(true);
            }
        }
        function stopEditing(first) {
            editing = false;
            root.classList.remove('is-editing');
            if (document.activeElement && root.contains(document.activeElement)) document.activeElement.blur();
            page.editor.el.contentEditable = 'false';
            if (titleEl) titleEl.readOnly = true;
            if (!first) page.saver.save();
            setTitle();
            drawMeta();
            unfit();
        }

        // Reading: tap the words to start writing exactly there. Links open,
        // players play, pictures offer their "Mark up" — none of those edit.
        scroll.addEventListener('click', function (e) {
            if (editing || !page.editor || root.classList.contains('is-loading')) return;
            var a = e.target.closest('a[href]');
            if (a && page.editor.el.contains(a)) { e.preventDefault(); if (o.onLink && o.onLink(a.getAttribute('href'))) return; window.open(a.href, '_blank', 'noopener'); return; }
            if (e.target.closest('.clip, img, button, .img-chip, .mp-meta, .mp-foot, .mp-below, .mp-aside, .mp-cover, a, input, textarea, label')) return;
            if (o.tapToWrite === false) return; // only the pen starts writing
            if (titleEl && e.target === titleEl) { page.focusTitle(); return; }
            if (window.getSelection && String(window.getSelection()).length) return; // selecting to copy, not to edit
            startEditing(caretAt(e.clientX, e.clientY));
        });
        edit.addEventListener('click', function () { startEditing(null); });
        done.addEventListener('click', function () { stopEditing(false); });
        more.addEventListener('click', function (e) { e.stopPropagation(); menu.hidden = !menu.hidden; });
        root.addEventListener('click', function (e) { if (!menu.hidden && !menu.contains(e.target) && !more.contains(e.target)) menu.hidden = true; });
        top.addEventListener('click', function () { scroll.scrollTo({ top: 0, behavior: 'smooth' }); });
        back.addEventListener('click', function () { page.close(); });

        scroll.addEventListener('scroll', function () {
            var y = scroll.scrollTop;
            root.classList.toggle('is-scrolled', y > 64);
            root.classList.toggle('is-far', y > scroll.clientHeight * 1.2);
        }, { passive: true });

        function placeTools() {
            // Below the page on a phone (riding the keyboard), above it on a desk.
            if (DESK.matches) (main || root).insertBefore(toolsSlot, scroll); else root.insertBefore(toolsSlot, menu);
            placeAside();
        }
        /* A card's comments: their own column beside the page on a desk, the end of the page on a phone. */
        function placeAside() {
            if (!o.aside) return;
            o.aside.classList.add('mp-aside');
            if (DESK.matches) body.appendChild(o.aside); else col.appendChild(o.aside);
        }
        if (DESK.addEventListener) DESK.addEventListener('change', placeTools);

        /* The page fits what is left above the keyboard, so the tools ride on it. */
        function fit() {
            var vv = window.visualViewport;
            if (!vv || closed || DESK.matches || !editing || o.host) return;
            var below = Math.max(0, window.innerHeight - vv.height - vv.offsetTop);
            root.style.top = vv.offsetTop + 'px'; root.style.bottom = 'auto'; root.style.height = vv.height + 'px';
            root.style.setProperty('--kb-safe', below > 80 ? '0px' : 'var(--sa-b, env(safe-area-inset-bottom, 0px))');
        }
        function unfit() { root.style.top = ''; root.style.bottom = ''; root.style.height = ''; root.style.removeProperty('--kb-safe'); }
        if (window.visualViewport) { window.visualViewport.addEventListener('resize', fit); window.visualViewport.addEventListener('scroll', fit); }

        function keys(e) {
            if (e.key !== 'Escape' || current !== page || document.querySelector('.ink, .rec, .ss, .at-pop, .ct')) return; // a sheet or tool on top closes first
            e.stopImmediatePropagation();
            if (!menu.hidden) { menu.hidden = true; return; }
            if (editing) stopEditing(false); else page.close();
        }
        window.addEventListener('keydown', keys, true);
        function hide() { if (document.hidden && page.saver) page.saver.save(); }
        document.addEventListener('visibilitychange', hide);
        window.addEventListener('pagehide', hide);

        /* Back closes the page. */
        var pushed = !o.host;
        if (pushed) history.pushState({ marginPage: 1 }, '', location.pathname + location.search + '#note');
        function onPop() { pushed = false; finish(); }
        if (pushed) window.addEventListener('popstate', onPop);

        function finish() {
            if (closed) return;
            closed = true;
            if (current === page) current = null;
            window.removeEventListener('popstate', onPop);
            window.removeEventListener('keydown', keys, true);
            if (titleEl) window.removeEventListener('resize', grow);
            document.removeEventListener('visibilitychange', hide);
            window.removeEventListener('pagehide', hide);
            if (DESK.removeEventListener) DESK.removeEventListener('change', placeTools);
            if (window.visualViewport) { window.visualViewport.removeEventListener('resize', fit); window.visualViewport.removeEventListener('scroll', fit); }
            if (document.activeElement && root.contains(document.activeElement)) document.activeElement.blur();
            if (page.saver) page.saver.save();
            if (page.editor) page.editor.destroy();
            root.classList.remove('is-open');
            unfit();
            setTimeout(function () { root.remove(); }, 420);
            if (dim) { dim.classList.add('is-gone'); setTimeout(function () { dim.remove(); }, 300); }
            if (o.onClose) o.onClose(page);
        }
        page.close = function () {
            if (closed) return;
            if (pushed) history.back(); // → popstate → finish
            else finish();
        };
        page.editing = function () { return editing; };
        page.setMeta = function (m) { meta = Object.assign({}, meta, m || {}); if (!editing) drawMeta(); };
        page.setTitle = setTitle;
        page.setFooter = function (el) {
            foot.replaceChildren();
            if (el) foot.appendChild(el);
            foot.hidden = !el;
            root.classList.toggle('has-foot', !!el);
        };
        current = page;

        if (o.load) {
            root.classList.add('is-loading');
            o.load().then(function (r) {
                if (closed) return;
                if (r.meta) meta = Object.assign({}, meta, r.meta);
                mount(r.html, r.media);
                root.classList.remove('is-loading');
                if (o.after) o.after(page);
            }).catch(function (err) { if (o.saver && o.saver.fatal) o.saver.fatal(err); page.close(); });
        } else {
            mount(o.editor && o.editor.html, o.editor && o.editor.media);
            if (o.after) o.after(page);
        }
        requestAnimationFrame(function () { root.classList.add('is-open'); });
        return page;
    }

    window.MarginPage = { open: open };
})();
</script>
