{{--
    The note editor: ONE implementation for every place a Margin note is
    written (the board's note sheet and the TakeNote app). Include it once,
    before the page's own script; it defines window.MarginEditor.

    MarginEditor.create(o) builds an editable page and its ink toolbar.
      o.html         the note's HTML, ALREADY cleaned by the server (NoteHtml)
      o.T            strings (devboard::messages)
      o.placeholder  shown while the page is empty
      o.labelledBy   id of the element naming the page, for screen readers
      o.picture(uuid, preview)  → the URL a picture is served from
      o.upload(blob, filename)  → Promise<{uuid}>: stores a picture, the HOST decides where
      o.imagePx      long side a picture is shrunk to before upload
      o.toast(msg, {error})     the host's toast
      o.onEdit()     called after every change
      — voice notes and videos (optional; the tools appear only when given, and need partials/recorder):
      o.uploadMedia(blob, kind, filename) → Promise<asset>: stores a recording, the HOST decides where
      o.mediaUrl(uuid, poster)  → the URL a recording (or a video's first frame) is served from
      o.mediaStatus(uuid)       → Promise<asset>: where a video being prepared stands
      o.media        { uuid: asset } for the recordings already in o.html (kind, status, duration, peaks)
      o.limits       { voice: seconds, video: seconds, mb: megabytes }
      — drawings (optional; needs partials/ink):
      o.ink          { save(blob, {kind, w, h, strokes, base}) → Promise<asset>, load(uuid) → Promise<ink> }
    → { el, toolbar, serialise(), isBlank(), pending(), focus(toEnd), pickPicture(), record(kind), draw(), destroy() }

    A drawing is a picture like any other (the phone renders it, the server
    re-encodes it); tapping a picture offers "Mark up", which reopens the
    drawing — or the photo under it — with every stroke still editable.

    A recording sits in the text as a block nobody can type into (a "clip"):
    a voice note is a play button over its waveform, a video a player. Stored,
    it is the same server-written marker as a picture, <img data-asset
    data-kind="audio|video">, and the server decides the kind.

    A video from YouTube, Vimeo, Dailymotion, Instagram or TikTok, pasted as
    its address on its own, becomes a clip too: a cover with a play button, the site's player
    loaded only when it is tapped. Stored as <img data-embed="site:id">; the
    player's address is built here from the site and id, never taken from the
    paste, and the server keeps only sites and ids it recognises (NoteHtml).
    Markdown — a .md file (the Markdown tool, or dropped on the page) or text
    pasted that reads as Markdown — becomes the note's own formatting:
    o.markdown(text) → Promise<html> asks the server to convert it (optional;
    without it a paste stays plain text). A paste converted by mistake has
    "Keep as text" in its toast.
    A TikTok short share link carries no id: o.videoLink(url) → Promise<"site:id"|null>
    asks the server which video it is (optional; without it, it stays a link).

    MarginEditor.saver(o) is the quiet autosave both hosts use.
      o.editor       the editor above
      o.send(html)   → Promise: the host's POST or PATCH (it knows which)
      o.exists()     true once the note is on the server
      o.saved(r)     the server's answer to a successful send
      o.conflict(err) → true when the host has dealt with a 409 and wants an immediate re-send
      o.fatal(err)   gave up (404/419/422, or too many tries)
      o.state(s)     'new' | 'saving' | 'saved' | 'offline'
    → { schedule(), save(), kill(), dirty() }

    Security: the only innerHTML is o.html, which the server has cleaned
    (allow-listed tags; pictures only as <img data-asset>). Paste is reduced to
    plain text or pictures, a drop to pictures, and the server cleans again on
    every save. Links are typed into the toolbar and must be http(s).

    Styles live in partials/editor-styles.
--}}
<script>
(function () {
    'use strict';
    if (window.MarginEditor) return;

    var TEXT_INKS = ['#1d1530', '#6d5ce8', '#db2777', '#2563eb', '#059669', '#b45309', '#dc2626'];
    /* Laid out as Word's Home tab, so it reads as familiar: Undo/Redo first, then the
       font (B I U S, font colour, clear formatting), the paragraph (lists, then Word's
       own ¶→ / ←¶ text-direction buttons), the styles (headings, quote), and what goes
       INTO the note last. A phone keeps the font and styles in "Aa", its own panel; a
       wide screen shows them inline where "Aa" stands. */
    var TOOLS = [
        ['undo', 'bi-arrow-counterclockwise', 'fmt_undo'], ['redo', 'bi-arrow-clockwise', 'fmt_redo'], '|',
        ['format', 'bi-type', 'fmt_format', 'is-format'], '|',
        ['ltr', 'svg:ltr', 'fmt_ltr'], ['rtl', 'svg:rtl', 'fmt_rtl'], '|',
        ['link', 'bi-link-45deg', 'fmt_link'], ['emoji', 'bi-emoji-smile', 'fmt_emoji'], ['table', 'bi-table', 'fmt_table'], ['markdown', 'bi-markdown', 'fmt_markdown'], '|',
        ['picture', 'bi-image', 'fmt_picture', 'is-accent']
    ];
    var FORMAT = [
        ['bold', 'bi-type-bold', 'fmt_bold'], ['italic', 'bi-type-italic', 'fmt_italic'],
        ['underline', 'bi-type-underline', 'fmt_underline'], ['strikeThrough', 'bi-type-strikethrough', 'fmt_strike'],
        ['colour', 'svg:colour', 'fmt_colour'], ['clear', 'svg:clear', 'fmt_clear'], '|',
        ['insertUnorderedList', 'bi-list-ul', 'fmt_bullets'], ['insertOrderedList', 'bi-list-ol', 'fmt_numbers'], ['checklist', 'bi-check2-square', 'fmt_checklist'],
        ['outdent', 'bi-text-indent-right', 'fmt_outdent', 'is-flip'], ['indent', 'bi-text-indent-left', 'fmt_indent', 'is-flip'], '|',
        ['h1', 'bi-type-h1', 'fmt_h1'], ['h2', 'bi-type-h2', 'fmt_h2'], ['blockquote', 'bi-quote', 'fmt_quote'], ['rule', 'bi-dash-lg', 'fmt_rule']
    ];
    /* Word's own glyphs, which Bootstrap Icons does not have: the ¶ with its arrow (Left-to-Right /
       Right-to-Left Text Direction), "A" over a colour bar (Font Color), "A" with an eraser (Clear
       All Formatting). currentColor, so they take the bar's ink like every other tool. */
    var PILCROW = '<path d="M12 3h6.5v1.8h-1.3V15h-1.8V4.8h-1.6V15H12v-4.6A3.7 3.7 0 0 1 12 3z"/><path d="M3.5 18.3h13v-2.6l4.5 3.6-4.5 3.6v-2.6h-13z"/>';
    var GLYPHS = {
        ltr: PILCROW,
        rtl: '<g transform="matrix(-1 0 0 1 24 0)">' + PILCROW + '</g>',
        colour: '<path d="M10.9 2.5h2.2l5 12.5h-2.3l-1.2-3.2H9.4L8.2 15H5.9zm-.8 7.4h3.8L12 4.9z"/><rect class="tool-ink" x="3.5" y="17.5" width="17" height="4" rx="1"/>',
        clear: '<path d="M8.4 2.5h2.2l4.2 10.4-1.6 1.6-.9-2.3H7.4l-1.2 3.2H3.9zm-.3 7.8h3.4L9.5 5.4z"/><path d="M17.3 12.2l3.6 3.6-5.6 5.6h-3.9l-1.9-1.9z" opacity=".85"/><path d="M9.8 21.4h11" stroke="currentColor" stroke-width="1.4" fill="none"/>'
    };
    var WIDE = window.matchMedia('(min-width: 900px)');
    /* Every standard emoji (the set WhatsApp and phone keyboards offer), as characters, from our own copy of
       Unicode's list (public/data/emoji) — loaded the first time the panel opens. */
    var EMOJI_URL = @json(asset('data/emoji/emoji.json'));
    var EMOJI_TABS = [['recent', 'bi-clock-history'], ['Smileys & Emotion', 'bi-emoji-smile'], ['People & Body', 'bi-person'], ['Animals & Nature', 'bi-tree'],
        ['Food & Drink', 'bi-cup-hot'], ['Travel & Places', 'bi-airplane'], ['Activities', 'bi-dribbble'], ['Objects', 'bi-lightbulb'], ['Symbols', 'bi-heart'], ['Flags', 'bi-flag']];
    var emojiData = null;
    var STATEFUL = ['bold', 'italic', 'underline', 'strikeThrough', 'insertUnorderedList', 'insertOrderedList'];

    var active = null; // the editor the selection belongs to

    function h(tag, cls, text) {
        var el = document.createElement(tag);
        if (cls) el.className = cls;
        if (text != null) el.textContent = text;
        return el;
    }
    function icon(name) {
        if (name.indexOf('svg:') === 0) {
            var sp = h('span', 'tool-glyph'); sp.setAttribute('aria-hidden', 'true');
            sp.innerHTML = '<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">' + GLYPHS[name.slice(4)] + '</svg>'; // our own constant markup, never data
            return sp;
        }
        return h('i', 'bi ' + name);
    }

    document.addEventListener('selectionchange', function () {
        if (!active) return;
        var sel = window.getSelection();
        if (sel.rangeCount && active.el.contains(sel.anchorNode)) {
            active.range = sel.getRangeAt(0).cloneRange();
            syncTools(active);
            linkChip(active);
            tableBar(active);
        }
    });

    function create(o) {
        var ed = h('div', 'ed');
        ed.contentEditable = 'true';
        ed.spellcheck = true;
        // No direction on the page as a whole: each paragraph finds its own (see editor-styles),
        // and the LTR / RTL tools fix one where the guess is wrong.
        ed.setAttribute('role', 'textbox');
        ed.setAttribute('aria-multiline', 'true');
        if (o.labelledBy) ed.setAttribute('aria-labelledby', o.labelledBy);
        ed.dataset.placeholder = o.placeholder || '';
        if (o.html) ed.innerHTML = o.html; // cleaned by the server's NoteHtml — see the header
        hydrate(ed, o.picture);
        wrapLoose(ed);
        var st = { o: o, T: o.T, el: ed, range: null, uploads: 0, tools: null, sub: null };
        clips(st);
        if (!ed.firstChild) { var p0 = h('p'); p0.appendChild(h('br')); ed.appendChild(p0); }

        st.toolbar = toolbar(st);
        bind(st);
        blankCheck(st);
        active = st;

        return {
            el: ed,
            toolbar: st.toolbar,
            serialise: function () { return serialise(ed); },
            isBlank: function () { return ed.classList.contains('is-blank'); },
            pending: function () { return st.uploads; },
            focus: function (toEnd) { active = st; ed.focus(); if (toEnd) caretToEnd(ed); },
            pickPicture: function () { st.sub.file.click(); }, // call inside a tap, or the browser refuses
            record: function (kind) { return record(st, kind); },
            draw: function () { return sketch(st); },
            destroy: function () {
                if (active === st) active = null;
                st.dead = true;
                hideChip(st); hideTableBar(st);
                if (playing && st.el.contains(playing.clip)) { playing.audio.pause(); playing = null; }
            }
        };
    }

    function hydrate(root, picture) {
        root.querySelectorAll('img').forEach(function (img) {
            if (img.dataset.embed) return; // another site's video: clips() draws it
            if (!img.dataset.asset) { img.remove(); return; }
            if (img.dataset.kind) return; // a recording: clips() draws it
            img.alt = ''; img.draggable = false;
            img.src = picture(img.dataset.asset);
        });
    }
    /* Words, links and the like sitting loose in the page, outside any paragraph (an older paste or
       link could leave them there), go into a paragraph of their own: only a paragraph follows its
       first letter's side, and only a paragraph can be turned left or right. */
    var BLOCK_TAGS = /^(P|H1|H2|H3|H4|H5|H6|UL|OL|LI|BLOCKQUOTE|DIV|HR|PRE|TABLE|FIGURE)$/;
    function wrapLoose(root) {
        var sel = window.getSelection(), keep = sel.rangeCount && root.contains(sel.anchorNode) ? [sel.anchorNode, sel.anchorOffset, sel.focusNode, sel.focusOffset] : null;
        var run = null, moved = false;
        Array.prototype.slice.call(root.childNodes).forEach(function (n) {
            var loose = n.nodeType === 3 ? (run || n.textContent.trim() !== '') : (n.nodeType === 1 && !BLOCK_TAGS.test(n.tagName) && !(n.classList && n.classList.contains('clip')) && !(n.dataset && n.dataset.ui) && n.tagName !== 'IMG' && n.tagName !== 'BR');
            if (n.nodeType === 1 && n.tagName === 'BR' && run) loose = true;
            if (!loose) { run = null; return; }
            if (!run) { run = document.createElement('p'); root.insertBefore(run, n); }
            run.appendChild(n); moved = true;
        });
        if (moved && keep) { try { sel.setBaseAndExtent(keep[0], keep[1], keep[2], keep[3]); } catch (e) {} }
        return moved;
    }
    function caretToEnd(el) {
        var r = document.createRange();
        r.selectNodeContents(el); r.collapse(false);
        var sel = window.getSelection(); sel.removeAllRanges(); sel.addRange(r);
    }
    function blankCheck(st) {
        var text = st.el.textContent, ui = st.el.querySelector('[data-ui]');
        if (ui) text = text.replace(ui.textContent, '');
        st.el.classList.toggle('is-blank', !text.trim() && !st.el.querySelector('img,hr,li,blockquote,.clip,table'));
    }
    function edited(st) { blankCheck(st); syncTools(st); if (st.o.onEdit) st.o.onEdit(); }

    function bind(st) {
        var ed = st.el;
        try { document.execCommand('defaultParagraphSeparator', false, 'p'); document.execCommand('styleWithCSS', false, false); } catch (e) {}
        ed.addEventListener('focus', function () { active = st; });
        ed.addEventListener('input', function (e) {
            // Enter on a ticked item copies its tick onto the new one: a new item always starts unticked
            if (e && e.inputType === 'insertParagraph') { var li = checkItemAt(); if (li && !li.textContent.trim()) li.removeAttribute('data-checked'); }
            edited(st);
        });
        // A checklist's box ticks with a tap — reading or writing — and saves like any other change.
        ed.addEventListener('click', function (e) {
            var li = e.target.closest && e.target.closest('ul[data-check] > li');
            if (!li || !ed.contains(li) || !onBox(li, e.clientX)) return;
            e.preventDefault(); e.stopPropagation();
            if (li.hasAttribute('data-checked')) li.removeAttribute('data-checked'); else li.setAttribute('data-checked', '1');
            edited(st);
        });
        ed.addEventListener('paste', function (e) {
            var cd = e.clipboardData;
            if (!cd) return;
            e.preventDefault();
            var files = Array.prototype.filter.call(cd.files || [], function (f) { return /^image\//.test(f.type); });
            if (files.length) { files.forEach(function (f) { insertPicture(st, f); }); return; }
            var text = cd.getData('text/plain');
            // A web address pasted on its own becomes a link: over selected words, those words; otherwise a short title (the site's name).
            var t = (text || '').trim();
            // …unless it is a video on a site a note can play from: then the video itself, as a block.
            var vid = /^https?:\/\/\S+$/i.test(t) && t.length < 2000 ? EMBEDS.fromUrl(t) : null;
            if (vid) {
                var vs = window.getSelection();
                if (vs.rangeCount && st.el.contains(vs.anchorNode) && vs.isCollapsed && !inLink(vs.anchorNode)) {
                    st.range = vs.getRangeAt(0).cloneRange();
                    placeBlock(st, embedClip(st, vid));
                    edited(st); return;
                }
            } else if (st.o.videoLink && t.length < 2000 && EMBEDS.isShort(t)) {
                var ss = window.getSelection();
                if (ss.rangeCount && st.el.contains(ss.anchorNode) && ss.isCollapsed && !inLink(ss.anchorNode)) {
                    st.range = ss.getRangeAt(0).cloneRange();
                    resolveEmbed(st, t);
                    edited(st); return;
                }
            }
            if (/^https?:\/\/\S+$/i.test(t) && t.length < 2000) {
                var sel = window.getSelection();
                if (sel.rangeCount && st.el.contains(sel.anchorNode) && !inLink(sel.anchorNode)) {
                    if (!sel.isCollapsed) document.execCommand('createLink', false, t);
                    else putLink(sel.getRangeAt(0), t, shortLabel(t));
                    edited(st); return;
                }
            }
            if (text && st.o.markdown && looksMarkdown(text)) {
                var ms = window.getSelection();
                if (ms.rangeCount && st.el.contains(ms.anchorNode) && !cellAt(ms.anchorNode)) {
                    if (!ms.isCollapsed) ms.deleteFromDocument();
                    pasteMarkdown(st, text, ms.getRangeAt(0).cloneRange(), true);
                    return;
                }
            }
            if (text) document.execCommand('insertText', false, text);
        });
        ed.addEventListener('dragover', function (e) { e.preventDefault(); });
        ed.addEventListener('drop', function (e) {
            e.preventDefault();
            var md = Array.prototype.filter.call((e.dataTransfer && e.dataTransfer.files) || [], function (f) { return isMarkdownFile(f); })[0];
            var files = Array.prototype.filter.call((e.dataTransfer && e.dataTransfer.files) || [], function (f) { return /^image\//.test(f.type); });
            if (md && st.o.markdown) { var mat = document.caretRangeFromPoint ? document.caretRangeFromPoint(e.clientX, e.clientY) : null; if (mat) st.range = mat; markdownFile(st, md); return; }
            var at = document.caretRangeFromPoint ? document.caretRangeFromPoint(e.clientX, e.clientY) : null;
            if (at) { st.range = at; }
            if (files.length) files.forEach(function (f) { insertPicture(st, f); });
            else if (e.dataTransfer && e.dataTransfer.getData('text/plain')) { restoreRange(st); document.execCommand('insertText', false, e.dataTransfer.getData('text/plain')); }
        });
        ed.addEventListener('click', function (e) { // a link in a note opens; editing it is the toolbar's job
            var a = e.target.closest('a[href]');
            if (a && (e.metaKey || e.ctrlKey)) window.open(a.href, '_blank', 'noopener');
            var img = e.target.closest('img[data-asset]');
            if (img && !img.dataset.kind && !img.classList.contains('is-uploading')) chip(st, img); else hideChip(st);
        });
        ed.addEventListener('input', function () { hideChip(st); });
        if (st.o.noteLinks) mentions(st);
        bindTables(st);
        // In a table: Enter is a new line inside the cell, Tab the next cell. In a list: Tab indents, Shift+Tab outdents.
        ed.addEventListener('keydown', function (e) {
            if (e.defaultPrevented || ed.contentEditable !== 'true' || e.ctrlKey || e.metaKey || e.altKey) return;
            var cell = caretCell(st);
            if (cell && e.key === 'Enter' && !e.isComposing) { e.preventDefault(); document.execCommand('insertLineBreak'); return; }
            if (e.key !== 'Tab') return;
            if (cell) { e.preventDefault(); nextCell(st, cell, e.shiftKey); return; }
            if (checkItemAt() || dirBlocks(st).some(function (b) { return b.tagName === 'LI'; })) {
                e.preventDefault();
                if (indentItems(st, !e.shiftKey)) edited(st);
            }
        });
        // Ctrl+L / ⌘L: link — straight to the box where a note's name (or an address) is typed
        ed.addEventListener('keydown', function (e) {
            if (!(e.ctrlKey || e.metaKey) || e.altKey || (e.key || '').toLowerCase() !== 'l' || !st.sub) return;
            e.preventDefault();
            var sel = window.getSelection();
            if (sel.rangeCount && ed.contains(sel.anchorNode)) st.range = sel.getRangeAt(0).cloneRange();
            if (st.sub.link.hidden) command(st, 'link'); else st.sub.input.focus();
        });
    }

    /* What a bare address shows as: the site's name ("samsung.com"), never the whole long address. */
    function shortLabel(href) {
        try { var u = new URL(href); return u.hostname.replace(/^www\./, '') || href; } catch (e) { return href; }
    }
    function inLink(node) { if (node && node.nodeType === 3) node = node.parentNode; return node && node.closest ? node.closest('a[href]') : null; }

    /* A link put straight into the text (no execCommand, which drags the page's computed styles in with it),
       then a space, with the caret after it. */
    function putLink(range, href, text) {
        var a = document.createElement('a'); a.href = href; a.textContent = text;
        range.deleteContents();
        // a space after it — unless one is already there (a link put over selected words)
        var n = range.endContainer, next = n.nodeType === 3 ? n.data.charAt(range.endOffset) : '';
        var gap = document.createTextNode(/\s/.test(next) ? '' : '\u00a0');
        range.insertNode(gap); range.insertNode(a);
        var r = document.createRange(); r.setStartAfter(gap); r.collapse(true);
        var sel = window.getSelection(); sel.removeAllRanges(); sel.addRange(r);
    }

    /* "@" + a few letters of a note's name: a list at the caret; arrows and Enter (or a tap) put the link in.
       Trilium's way of linking notes, for editors given noteLinks. */
    function mentions(st) {
        var pop = null, at = null, items = [], idx = 0;
        function close() { if (pop) { pop.remove(); pop = null; } at = null; }
        function caret() {
            var sel = window.getSelection();
            if (!sel.rangeCount || !sel.isCollapsed) return null;
            var r = sel.getRangeAt(0), node = r.startContainer;
            if (node.nodeType !== 3 || !st.el.contains(node) || (node.parentNode.closest && node.parentNode.closest('a'))) return null;
            var m = /(^|[\s\u00a0(])@([^\s@]{0,40})$/.exec(node.data.slice(0, r.startOffset));
            if (!m) return null;
            var rect = r.getBoundingClientRect();
            if (!rect.height) rect = node.parentNode.getBoundingClientRect();
            return { node: node, start: r.startOffset - m[2].length - 1, end: r.startOffset, q: m[2], rect: rect };
        }
        function show() {
            var c = caret(); if (!c) { close(); return; }
            at = c;
            items = st.o.noteLinks.search(c.q).slice(0, 7);
            if (!items.length) { close(); return; }
            idx = Math.max(0, Math.min(idx, items.length - 1));
            if (!pop) { pop = h('div', 'at-pop'); pop.setAttribute('role', 'listbox'); pop.setAttribute('aria-label', st.T.nl_notes); document.body.appendChild(pop); }
            pop.replaceChildren(h('div', 'at-head', st.T.nl_notes));
            items.forEach(function (it, i) {
                var b = h('button', 'at-item' + (i === idx ? ' is-on' : '')); b.type = 'button'; b.setAttribute('role', 'option'); b.setAttribute('aria-selected', String(i === idx));
                b.append(icon('bi-journal-text'), document.createTextNode(it.title));
                press(b, function () { pick(i); });
                pop.appendChild(b);
            });
            var left = Math.max(8, Math.min(window.innerWidth - 296, c.rect.left)), below = c.rect.bottom + 8;
            pop.style.left = left + 'px'; pop.style.top = below + 'px';
            var vh = window.visualViewport ? window.visualViewport.height + window.visualViewport.offsetTop : window.innerHeight;
            if (below + pop.offsetHeight > vh - 8) pop.style.top = Math.max(8, c.rect.top - pop.offsetHeight - 8) + 'px'; // above the caret when the keyboard is in the way
        }
        function pick(i) {
            var it = items[i]; if (!it || !at) return;
            var r = document.createRange(); r.setStart(at.node, at.start); r.setEnd(at.node, at.end);
            putLink(r, st.o.noteLinks.href(it.uuid), it.title);
            close(); edited(st);
        }
        st.el.addEventListener('input', function () { idx = 0; show(); });
        st.el.addEventListener('keydown', function (e) {
            if (!pop) return;
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') { e.preventDefault(); idx = (idx + (e.key === 'ArrowDown' ? 1 : items.length - 1)) % items.length; show(); }
            else if (e.key === 'Enter' || e.key === 'Tab') { e.preventDefault(); e.stopPropagation(); pick(idx); }
            else if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); close(); }
        }, true);
        st.el.addEventListener('blur', function () { setTimeout(close, 180); });
        window.addEventListener('scroll', close, { passive: true, capture: true });
    }
    function restoreRange(st) {
        if (document.activeElement !== st.el) st.el.focus({ preventScroll: true });
        if (st.range && st.el.contains(st.range.startContainer)) {
            var sel = window.getSelection(); sel.removeAllRanges(); sel.addRange(st.range);
        }
    }
    /* The paragraphs (or list items, headings, quotes) the selection touches. */
    var DIR_BLOCKS = 'p,h1,h2,h3,h4,li,blockquote,div';
    function dirBlocks(st) {
        var sel = window.getSelection();
        if (!sel.rangeCount || !st.el.contains(sel.anchorNode)) return [];
        var r = sel.getRangeAt(0), out = [];
        var near = function (n) { if (n && n.nodeType === 3) n = n.parentNode; var b = n && n.closest ? n.closest(DIR_BLOCKS) : null; return b && b !== st.el && st.el.contains(b) && !b.closest('.clip') ? b : null; };
        st.el.querySelectorAll(DIR_BLOCKS).forEach(function (b) {
            if (b.closest('.clip') || (b.dataset && b.dataset.ui)) return;
            if (r.intersectsNode(b) && !b.querySelector(DIR_BLOCKS)) out.push(b);
        });
        if (!out.length) { var one = near(r.startContainer); if (one) out.push(one); }
        return out;
    }
    /* Word's "text direction" for the paragraphs under the caret. The same
       button again hands the paragraph back to its own automatic direction. */
    function setDir(st, dir) {
        wrapLoose(st.el);
        var blocks = dirBlocks(st);
        if (!blocks.length) {
            // an empty page: start a paragraph to hold the direction
            var p = h('p'); p.appendChild(h('br')); st.el.appendChild(p); blocks = [p];
            var rr = document.createRange(); rr.setStart(p, 0); rr.collapse(true);
            var sel = window.getSelection(); sel.removeAllRanges(); sel.addRange(rr);
        }
        var off = blocks.every(function (b) { return b.getAttribute('dir') === dir; });
        blocks.forEach(function (b) {
            if (off) b.removeAttribute('dir'); else b.setAttribute('dir', dir);
            var list = b.tagName === 'LI' ? b.parentNode : null; // a list turns as one, so its bullets move side too
            if (list && /^(UL|OL)$/.test(list.tagName)) { if (off) list.removeAttribute('dir'); else list.setAttribute('dir', dir); }
        });
    }
    /* Which way the line under the caret runs: its own setting, else what its first letter decides
       (the paragraphs run "plaintext", so Arabic first means right to left). */
    var RTL_FIRST = /^[^A-Za-z\u00C0-\u02AF\u0370-\u058F\u0590-\u08FF\uFB1D-\uFDFF\uFE70-\uFEFF]*[\u0590-\u08FF\uFB1D-\uFDFF\uFE70-\uFEFF]/;
    function lineDir(st) {
        var b = dirBlocks(st)[0];
        if (!b) return document.documentElement.dir === 'rtl' ? 'rtl' : 'ltr';
        var own = b.getAttribute('dir') || (b.closest('[dir]') !== st.el && b.closest('[dir]') && st.el.contains(b.closest('[dir]')) ? b.closest('[dir]').getAttribute('dir') : null);
        if (own === 'rtl' || own === 'ltr') return own;
        var t = (b.textContent || '').trim();
        if (!t) return document.documentElement.dir === 'rtl' ? 'rtl' : 'ltr';
        return RTL_FIRST.test(t) ? 'rtl' : 'ltr';
    }
    function blockTag(st) {
        var sel = window.getSelection();
        var n = sel.rangeCount ? sel.anchorNode : null;
        if (n && n.nodeType === 3) n = n.parentNode;
        var b = n && n.closest ? n.closest('h1,h2,h3,h4,blockquote') : null;
        return b && st.el.contains(b) ? b.tagName.toLowerCase() : 'p';
    }

    /* The toolbar */
    function toolbar(st) {
        var T = st.T;
        var bar = h('div', 'tools');
        var row = h('div', 'tools-row');
        row.setAttribute('role', 'toolbar');
        st.tools = {};
        var tools = TOOLS.slice();
        var after = tools.findIndex(function (d) { return d[0] === 'picture'; }) + 1;
        if (st.o.uploadMedia && window.MarginRecorder) {
            tools.splice(after, 0, ['voice', 'bi-mic-fill', 'fmt_voice', 'is-accent is-rose'], ['video', 'bi-camera-video-fill', 'fmt_video', 'is-accent is-teal']);
            after += 2;
        }
        if (st.o.ink && window.MarginInk) {
            tools.splice(after, 0, ['draw', 'bi-brush-fill', 'fmt_draw', 'is-accent is-sky']);
        }
        function add(d, into) {
            if (d === '|') { into.appendChild(h('span', 'tool-sep')); return; }
            var b = h('button', 'tool' + (d[3] ? ' ' + d[3] : ''));
            b.type = 'button';
            b.setAttribute('aria-label', T[d[2]]); b.title = T[d[2]];
            b.appendChild(icon(d[1]));
            press(b, function () { command(st, d[0]); });
            st.tools[d[0]] = b;
            into.appendChild(b);
        }
        if (!st.o.markdown) tools = tools.filter(function (d) { return d[0] !== 'markdown'; });
        tools.forEach(function (d) { add(d, row); });
        var fmt = h('div', 'tools-sub tools-fmt'); fmt.setAttribute('role', 'group');
        FORMAT.forEach(function (d) { add(d, fmt); });

        // Colour: a row of inks that slides in above the tools.
        var colours = h('div', 'tools-sub'); colours.hidden = true;
        TEXT_INKS.forEach(function (hex) {
            var s = h('button', 'swatch'); s.type = 'button';
            s.style.setProperty('--sw', hex);
            s.setAttribute('aria-label', T.fmt_colour + ' ' + hex);
            press(s, function () {
                restoreRange(st); document.execCommand('foreColor', false, hex); colours.hidden = true;
                var bar = st.tools.colour && st.tools.colour.querySelector('.tool-ink'); if (bar) bar.style.fill = hex; // as Word: the bar shows the last colour
                edited(st);
            });
            colours.appendChild(s);
        });

        // Link: the URL is typed here (no native prompt), checked, then applied.
        var links = h('div', 'tools-sub is-link'); links.hidden = true;
        var inp = h('input', 'link-in');
        inp.type = st.o.noteLinks ? 'text' : 'url'; inp.placeholder = st.o.noteLinks ? T.nl_search : T.link_placeholder; inp.setAttribute('enterkeyhint', 'done'); inp.autocapitalize = 'off';
        // "Link to a note" (opt-in: noteLinks {search(q) → [{uuid,title}], href(uuid)}): typing a name offers the notes that match
        var sug = h('div', 'link-sug'); sug.hidden = true;
        var looksUrl = function (v) { return /^[a-z][a-z0-9+.-]*:/i.test(v) || /^[\w-]+(\.[\w-]+)+(\/|$)/.test(v); };
        function suggest() {
            var q = inp.value.trim();
            var items = st.o.noteLinks && q && !looksUrl(q) ? st.o.noteLinks.search(q).slice(0, 6) : [];
            sug.replaceChildren.apply(sug, items.map(function (it) {
                var b = h('button', 'link-note'); b.type = 'button';
                b.append(icon('bi-journal-text'), document.createTextNode(it.title));
                press(b, function () { linkNote(it); });
                return b;
            }));
            sug.hidden = !items.length;
            return items;
        }
        function linkNote(it) {
            put(st.o.noteLinks.href(it.uuid), txt.value.trim() || null, it.title);
        }
        /* Put the link in: into the link being edited, over the selected words, or as new words (the text
           typed in "Text to show", else the fallback — a note's name, or the site's name for an address). */
        function put(href, label, fallback) {
            links.hidden = true; sug.hidden = true; inp.value = ''; txt.value = '';
            restoreRange(st);
            var a = st.editLink && st.el.contains(st.editLink) ? st.editLink : null;
            st.editLink = null;
            var sel = window.getSelection();
            if (a) { a.href = href; if (label) a.textContent = label; }
            else if (label && sel.rangeCount) putLink(sel.getRangeAt(0), href, label);
            else if (sel.rangeCount && !sel.isCollapsed) document.execCommand('createLink', false, href);
            else if (sel.rangeCount) putLink(sel.getRangeAt(0), href, fallback);
            edited(st);
        }
        inp.addEventListener('input', suggest);
        var go = h('button', 'link-go', T.link_add); go.type = 'button';
        function applyLink() {
            var v = inp.value.trim();
            if (st.o.noteLinks && v && !looksUrl(v)) { var hits = suggest(); if (hits.length) linkNote(hits[0]); else inp.focus(); return; }
            if (v && !/^[a-z][a-z0-9+.-]*:/i.test(v)) v = 'https://' + v;
            if (!/^https?:\/\/[^\s]+$/i.test(v)) { inp.focus(); return; }
            put(v, txt.value.trim() || null, shortLabel(v));
        }
        go.addEventListener('click', applyLink);
        inp.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); applyLink(); } if (e.key === 'Escape') { e.stopPropagation(); links.hidden = true; restoreRange(st); } });
        // "Text to show": what the reader sees instead of the long address
        var txt = h('input', 'link-in is-text'); txt.type = 'text'; txt.placeholder = T.link_text; txt.setAttribute('aria-label', T.link_text); txt.maxLength = 200; txt.setAttribute('enterkeyhint', 'done');
        txt.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); applyLink(); } if (e.key === 'Escape') { e.stopPropagation(); links.hidden = true; restoreRange(st); } });
        /* Opening the panel: inside a link, edit that link; over selected words, they are the text. */
        links.prefill = function () {
            var r = st.range && st.el.contains(st.range.startContainer) ? st.range : null;
            var a = r ? inLink(r.startContainer) : null;
            st.editLink = a && st.el.contains(a) ? a : null;
            inp.value = a ? a.getAttribute('href') : '';
            txt.value = a ? a.textContent : (r ? r.toString().replace(/\s+/g, ' ').trim().slice(0, 200) : '');
            sug.hidden = true;
        };
        links.append(inp, go, txt, sug);

        var file = h('input');
        file.type = 'file'; file.accept = 'image/*'; file.multiple = true; file.hidden = true;
        file.addEventListener('change', function () {
            Array.prototype.forEach.call(file.files || [], function (f) { insertPicture(st, f); });
            file.value = '';
        });

        var emojis = h('div', 'tools-sub is-emoji'); emojis.hidden = true;

        var mdfile = h('input');
        mdfile.type = 'file'; mdfile.accept = '.md,.markdown,.mdown,.mkd,.txt,text/markdown,text/plain'; mdfile.hidden = true;
        mdfile.addEventListener('change', function () {
            var f = mdfile.files && mdfile.files[0];
            mdfile.value = '';
            if (f) markdownFile(st, f);
        });

        // Table: pick its size on a grid (columns × rows), as Word's Insert Table does.
        var tpick = h('div', 'tools-sub is-table'); tpick.hidden = true;
        var tsize = h('span', 'tpick-size', T.table_pick), tgrid = h('div', 'tpick-grid'), TMAX = 6;
        function light(r, c) {
            Array.prototype.forEach.call(tgrid.children, function (b) { b.classList.toggle('is-on', +b.dataset.r <= r && +b.dataset.c <= c); });
            tsize.textContent = r ? c + ' × ' + r : T.table_pick;
        }
        for (var tr0 = 1; tr0 <= TMAX; tr0++) for (var tc0 = 1; tc0 <= TMAX; tc0++) (function (r, c) {
            var b = h('button', 'tpick-cell'); b.type = 'button'; b.dataset.r = r; b.dataset.c = c;
            b.setAttribute('aria-label', c + ' × ' + r);
            b.addEventListener('pointerenter', function () { light(r, c); });
            press(b, function () { tpick.hidden = true; light(0, 0); insertTable(st, r, c); });
            tgrid.appendChild(b);
        })(tr0, tc0);
        tgrid.addEventListener('pointerleave', function () { light(0, 0); });
        tpick.append(tsize, tgrid);

        st.sub = { colour: colours, link: links, input: inp, file: file, format: fmt, emoji: emojis, table: tpick, mdfile: mdfile };
        bar.append(colours, links, emojis, tpick, row, file, mdfile);
        // Phone: the formatting is a panel above the bar. Wide: it sits in the bar, where "Aa" is.
        function place() {
            if (WIDE.matches) { fmt.hidden = false; st.tools.format.after(fmt); }
            else { fmt.hidden = true; bar.insertBefore(fmt, row); }
            st.tools.format.classList.remove('is-on');
        }
        place();
        if (WIDE.addEventListener) WIDE.addEventListener('change', place);
        return bar;
    }
    /* The emoji panel: search, a tab per group (and the ones used lately), a skin tone, and the grid —
       ONE panel, used by the editor's toolbar and by anything else that takes text (window.MarginEmoji).
       pick(ch) puts the emoji in; the panel stays open for the next one, as on WhatsApp. */
    function emojiGrid(T, put) {
        var box = h('div', 'emo-panel');
        var recent = [], tone = 0;
        try { recent = JSON.parse(localStorage.getItem('margin.emoji.recent') || '[]').slice(0, 32); tone = +(localStorage.getItem('margin.emoji.tone') || 0) || 0; } catch (e) {}
        var top = h('div', 'emo-top');
        var q = h('input', 'link-in emo-search'); q.type = 'search'; q.placeholder = T.emoji_search; q.setAttribute('aria-label', T.emoji_search); q.autocapitalize = 'off';
        var TONES = ['✋', '✋\u{1F3FB}', '✋\u{1F3FC}', '✋\u{1F3FD}', '✋\u{1F3FE}', '✋\u{1F3FF}'];
        var toneBtn = h('button', 'emo-tone', TONES[tone]); toneBtn.type = 'button'; toneBtn.setAttribute('aria-label', T.emoji_tone); toneBtn.title = T.emoji_tone;
        top.append(q, toneBtn);
        var tabs = h('div', 'emo-tabs'); tabs.setAttribute('role', 'tablist');
        var grid = h('div', 'emo-grid');
        box.append(top, tabs, grid);
        grid.appendChild(h('p', 'emo-none', T.emoji_loading));

        function pick(e) {
            put(tone && e[2] ? e[2][tone - 1] : e[0]);
            recent = [e[0]].concat(recent.filter(function (x) { return x !== e[0]; })).slice(0, 32);
            try { localStorage.setItem('margin.emoji.recent', JSON.stringify(recent)); } catch (er) {}
        }
        function cell(e) {
            var b = h('button', 'emo', tone && e[2] ? e[2][tone - 1] : e[0]); b.type = 'button';
            b.setAttribute('aria-label', e[1]); b.title = e[1];
            press(b, function () { pick(e); });
            return b;
        }
        var byChar = {};
        function draw() {
            var term = q.value.trim().toLowerCase(), parts = [];
            if (term) {
                var hits = [];
                emojiData.groups.forEach(function (g) { g.e.forEach(function (e) { if (e[1].indexOf(term) !== -1) hits.push(e); }); });
                hits.slice(0, 240).forEach(function (e) { parts.push(cell(e)); });
                if (!hits.length) parts.push(h('p', 'emo-none', T.emoji_none));
            } else {
                var rec = recent.map(function (c) { return byChar[c]; }).filter(Boolean);
                if (rec.length) { var hr = h('h5', 'emo-h', T.emoji_recent); hr.dataset.group = 'recent'; parts.push(hr); rec.forEach(function (e) { parts.push(cell(e)); }); }
                emojiData.groups.forEach(function (g) {
                    var hh = h('h5', 'emo-h', T['emoji_g_' + g.g.split(' ')[0].toLowerCase()] || g.g); hh.dataset.group = g.g; parts.push(hh);
                    g.e.forEach(function (e) { parts.push(cell(e)); });
                });
            }
            grid.replaceChildren.apply(grid, parts);
            grid.scrollTop = 0;
        }
        (emojiData ? Promise.resolve(emojiData) : fetch(EMOJI_URL, { credentials: 'same-origin' }).then(function (r) { if (!r.ok) throw new Error('emoji'); return r.json(); }).then(function (d) { emojiData = d; return d; }))
            .then(function () {
                emojiData.groups.forEach(function (g) { g.e.forEach(function (e) { byChar[e[0]] = e; }); });
                EMOJI_TABS.forEach(function (t) {
                    var b = h('button', 'emo-tab'); b.type = 'button'; b.setAttribute('role', 'tab');
                    b.setAttribute('aria-label', t[0] === 'recent' ? T.emoji_recent : (T['emoji_g_' + t[0].split(' ')[0].toLowerCase()] || t[0]));
                    b.appendChild(icon(t[1]));
                    press(b, function () {
                        if (q.value) { q.value = ''; draw(); }
                        var to = grid.querySelector('[data-group="' + t[0] + '"]');
                        if (to) grid.scrollTo({ top: to.offsetTop - grid.offsetTop, behavior: 'smooth' });
                    });
                    tabs.appendChild(b);
                });
                draw();
            })
            .catch(function () { box.failed = true; grid.replaceChildren(h('p', 'emo-none', T.emoji_failed)); });
        var timer = null;
        q.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(function () { if (emojiData) draw(); }, 120); });
        press(toneBtn, function () {
            tone = (tone + 1) % TONES.length; toneBtn.textContent = TONES[tone];
            try { localStorage.setItem('margin.emoji.tone', String(tone)); } catch (e) {}
            if (emojiData) { var y = grid.scrollTop; draw(); grid.scrollTop = y; }
        });
        box.search = q;
        return box;
    }
    function emojiPanel(st) {
        var box = st.sub.emoji;
        if (box.firstChild && !box.firstChild.failed) return;
        var panel = emojiGrid(st.T, function (ch) {
            // the host may say the emoji belongs in another field (the note's title, when that is where the cursor was)
            var tgt = st.o.emojiTarget && st.o.emojiTarget();
            if (tgt) {
                var v = tgt.value, c = tgt._caret || [v.length, v.length], a = Math.min(c[0], v.length), b = Math.min(c[1], v.length);
                if (tgt.maxLength > 0 && v.length - (b - a) + ch.length > tgt.maxLength) return;
                tgt.value = v.slice(0, a) + ch + v.slice(b);
                tgt._caret = [a + ch.length, a + ch.length];
                tgt.dispatchEvent(new Event('input', { bubbles: true }));
                return;
            }
            restoreRange(st);
            document.execCommand('insertText', false, ch);
            var sel = window.getSelection(); if (sel.rangeCount) st.range = sel.getRangeAt(0).cloneRange();
            edited(st);
        });
        panel.search.addEventListener('keydown', function (e) { if (e.key === 'Escape') { e.stopPropagation(); box.hidden = true; restoreRange(st); } });
        box.replaceChildren(panel);
    }

    /* A tool acts on release, and never takes focus from the page — so the
       keyboard stays up and the selection stays where it was. A finger that
       slid along the bar was scrolling it, not pressing. */
    function press(btn, fn) {
        var x0 = 0, y0 = 0, down = false;
        btn.addEventListener('pointerdown', function (e) { e.preventDefault(); down = true; x0 = e.clientX; y0 = e.clientY; });
        btn.addEventListener('pointerup', function (e) {
            if (!down) return;
            down = false;
            if (Math.abs(e.clientX - x0) > 8 || Math.abs(e.clientY - y0) > 8) return;
            fn();
        });
        btn.addEventListener('pointercancel', function () { down = false; });
        btn.addEventListener('click', function (e) { if (e.detail === 0) fn(); }); // keyboard
    }
    function command(st, name) {
        // the notebook out of space: nothing new goes in — no picture picked, no recording or drawing started
        if (/^(picture|voice|video|draw)$/.test(name) && st.o.isFull && st.o.isFull()) { st.o.toast(st.T.st_full, { error: true }); return; }
        if (name === 'picture') { st.sub.file.click(); return; }
        if (name === 'markdown') { st.sub.mdfile.click(); return; }
        if (name === 'voice' || name === 'video') { record(st, name === 'voice' ? 'audio' : 'video'); return; }
        if (name === 'draw') { sketch(st); return; }
        if (name === 'table' && caretCell(st)) { tableBar(st); return; } // already in a table: its own bar is the tool
        if (name === 'colour' || name === 'link' || name === 'format' || name === 'emoji' || name === 'table') {
            var open = st.sub[name].hidden;
            st.sub.colour.hidden = true; st.sub.link.hidden = true; st.sub.emoji.hidden = true; st.sub.table.hidden = true;
            if (name === 'emoji' && open) emojiPanel(st);
            if (!WIDE.matches) st.sub.format.hidden = true;
            st.sub[name].hidden = !open;
            st.tools.format.classList.toggle('is-on', !WIDE.matches && !st.sub.format.hidden);
            if (name === 'link' && open) { st.sub.link.prefill(); setTimeout(function () { st.sub.input.focus(); }, 30); }
            return;
        }
        restoreRange(st);
        switch (name) {
            case 'ltr': case 'rtl':
                setDir(st, name);
                break;
            case 'checklist':
                toggleChecklist(st);
                break;
            case 'indent': case 'outdent':
                indentItems(st, name === 'indent');
                break;
            case 'h1': case 'h2': case 'blockquote':
                document.execCommand('formatBlock', false, blockTag(st) === name ? '<p>' : '<' + name + '>');
                break;
            case 'clear':
                document.execCommand('removeFormat'); document.execCommand('unlink');
                document.execCommand('formatBlock', false, '<p>');
                break;
            case 'rule':
                document.execCommand('insertHorizontalRule');
                break;
            case 'insertUnorderedList': case 'insertOrderedList':
                listCommand(st, name);
                break;
            default:
                document.execCommand(name);
        }
        edited(st);
    }
    function syncTools(st) {
        if (!st.tools) return;
        STATEFUL.forEach(function (k) {
            var on = false;
            try { on = document.queryCommandState(k); } catch (e) {}
            st.tools[k].classList.toggle('is-on', on);
        });
        var tag = blockTag(st);
        ['h1', 'h2', 'blockquote'].forEach(function (k) { st.tools[k].classList.toggle('is-on', tag === k); });
        var inCheck = !!checkItemAt();
        if (st.tools.checklist) st.tools.checklist.classList.toggle('is-on', inCheck);
        if (st.tools.table) st.tools.table.classList.toggle('is-on', !!caretCell(st));
        if (inCheck && st.tools.insertUnorderedList) st.tools.insertUnorderedList.classList.remove('is-on'); // a checklist is not "bullets"
        var side = lineDir(st); // as Word: the button for the side the line runs from is lit, set by hand or not
        ['ltr', 'rtl'].forEach(function (k) { if (st.tools[k]) st.tools[k].classList.toggle('is-on', side === k); });
    }

    /* Pictures: shrunk on the phone (a 12-megapixel photo becomes ~1 MB),
       re-encoded again by the server, shown at once from a local blob. */
    function insertPicture(st, file) {
        var T = st.T, o = st.o;
        if (!/^image\//.test(file.type)) { o.toast(T.bad_image, { error: true }); return; }
        if (o.isFull && o.isFull()) { o.toast(T.st_full, { error: true }); return; } // pasted or dropped: out of space, nothing goes in
        st.uploads++;
        var tmp = 'up' + Math.random().toString(36).slice(2);
        var local = URL.createObjectURL(file);
        restoreRange(st);
        document.execCommand('insertHTML', false, '<img data-tmp="' + tmp + '" class="is-uploading" alt="">');
        var img = st.el.querySelector('img[data-tmp="' + tmp + '"]');
        if (img) img.src = local;
        blankCheck(st);
        o.toast(T.uploading);

        shrink(file, o.imagePx).then(function (blob) {
            return o.upload(blob, blob.type === 'image/png' ? 'picture.png' : 'picture.jpg');
        }).then(function (asset) {
            if (!img) return;
            img.dataset.asset = asset.uuid;
            img.removeAttribute('data-tmp');
            img.classList.remove('is-uploading');
            img.src = o.picture(asset.uuid);
        }).catch(function (err) {
            if (img) img.remove();
            blankCheck(st);
            o.toast((err && err.data && err.data.message) || (err && err.status === 419 ? T.expired : T.bad_image), { error: true });
        }).then(function () {
            st.uploads--;
            setTimeout(function () { URL.revokeObjectURL(local); }, 4000);
            if (o.onEdit) o.onEdit();
        });
    }
    function shrink(file, px) {
        if (file.type === 'image/gif') return Promise.resolve(file);
        return new Promise(function (resolve) {
            var im = new Image(), u = URL.createObjectURL(file);
            im.onload = function () {
                var w = im.naturalWidth, hh = im.naturalHeight, k = Math.min(1, (px || 2400) / Math.max(w, hh));
                if (k === 1 && file.size < 3e6) { URL.revokeObjectURL(u); resolve(file); return; }
                var cv = document.createElement('canvas');
                cv.width = Math.round(w * k); cv.height = Math.round(hh * k);
                cv.getContext('2d').drawImage(im, 0, 0, cv.width, cv.height);
                URL.revokeObjectURL(u);
                var type = file.type === 'image/png' ? 'image/png' : 'image/jpeg';
                cv.toBlob(function (b) { resolve(b || file); }, type, 0.9);
            };
            im.onerror = function () { URL.revokeObjectURL(u); resolve(file); };
            im.src = u;
        });
    }

    /* ── Recordings ───────────────────────────────────────────────────
       A voice note or a video is a clip: a block in the text that nobody
       types into. Recorded with the shared MarginRecorder, shown at once from
       the phone's own copy, uploaded, then pointed at the server's copy. */
    var playing = null; // the one voice note playing, page-wide
    var POLL_MS = 3000, POLL_FOR = 15 * 60 * 1000;

    function clock(ms) {
        var s = Math.round((ms || 0) / 1000);
        return Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0');
    }
    function clips(st) {
        st.el.querySelectorAll('img[data-embed]').forEach(function (img) {
            var v = EMBEDS.parse(img.dataset.embed);
            if (v) img.replaceWith(embedClip(st, v)); else img.remove();
        });
        st.el.querySelectorAll('img[data-kind]').forEach(function (img) {
            var kind = img.dataset.kind;
            if (kind !== 'audio' && kind !== 'video') { img.remove(); return; }
            var info = (st.o.media && st.o.media[img.dataset.asset]) || { uuid: img.dataset.asset, kind: kind, status: 'ready' };
            img.replaceWith(clip(st, kind, info, null));
        });
    }
    function clip(st, kind, info, local) {
        var T = st.T;
        var c = h('div', 'clip is-' + kind);
        c.contentEditable = 'false';
        c.dataset.kind = kind;
        if (info && info.uuid) c.dataset.asset = info.uuid;

        var grip = h('button', 'clip-grip'); grip.type = 'button';
        grip.setAttribute('aria-label', T.move_this); grip.title = T.move_this;
        grip.appendChild(icon('bi-grip-vertical'));
        grip.addEventListener('pointerdown', function (e) { startMove(st, c, e); });
        grip.addEventListener('click', function (e) { e.preventDefault(); e.stopPropagation(); });

        var x = h('button', 'clip-x'); x.type = 'button';
        x.setAttribute('aria-label', T.clip_remove); x.title = T.clip_remove;
        x.appendChild(icon('bi-x-lg'));
        x.addEventListener('click', function (e) {
            e.preventDefault(); e.stopPropagation();
            if (playing && playing.clip === c) { playing.audio.pause(); playing = null; }
            removeBlock(st, c, T.clip_removed);
        });

        if (kind === 'audio') {
            var play = h('button', 'clip-play'); play.type = 'button';
            play.setAttribute('aria-label', T.clip_play);
            play.appendChild(icon('bi-play-fill'));
            var wave = h('span', 'clip-wave');
            var peaks = (info && info.peaks) || Array.apply(null, Array(40)).map(function (_, i) { return 0.25 + 0.2 * Math.abs(Math.sin(i * 0.9)); });
            peaks.forEach(function (p) { var b = h('i'); b.style.height = Math.round(Math.max(0.12, p) * 100) + '%'; wave.appendChild(b); });
            var time = h('span', 'clip-time', clock(info && info.duration_ms));
            c.append(grip, play, wave, time, x);
            c._src = function () { return c._local || (c.dataset.asset ? st.o.mediaUrl(c.dataset.asset) : null); };
            play.addEventListener('click', function (e) { e.preventDefault(); e.stopPropagation(); toggle(st, c, play, wave, time); });
            wave.addEventListener('click', function (e) { // tap the waveform to jump
                if (!playing || playing.clip !== c || !playing.audio.duration) return;
                var r = wave.getBoundingClientRect(), f = (e.clientX - r.left) / r.width;
                if (getComputedStyle(wave).direction === 'rtl') f = 1 - f;
                playing.audio.currentTime = Math.max(0, Math.min(1, f)) * playing.audio.duration;
            });
        } else {
            var v = h('video', 'clip-video');
            v.controls = true; v.playsInline = true; v.preload = 'metadata'; v.setAttribute('playsinline', '');
            var wait = h('div', 'clip-wait');
            wait.append(h('span', 'clip-spin'), h('span', null, T.clip_preparing));
            c.append(v, wait, grip, x);
            showVideo(st, c, info, local);
        }
        if (local) { c._local = local; c.classList.add('is-uploading'); }
        if (info && info.status === 'failed') failed(st, c);
        return c;
    }
    function showVideo(st, c, info, local) {
        var v = c.querySelector('video');
        var ready = info && info.status === 'ready' && c.dataset.asset;
        c.classList.toggle('is-preparing', !ready && !(info && info.status === 'failed'));
        if (ready) {
            if (info.poster) v.poster = st.o.mediaUrl(c.dataset.asset, true);
            v.src = st.o.mediaUrl(c.dataset.asset);
            c.classList.remove('is-preparing');
        } else if (local) {
            v.src = local; // the phone's own copy plays while the server prepares its own
        }
        if (!ready && c.dataset.asset && info && info.status === 'processing') poll(st, c);
    }
    function poll(st, c) {
        if (c._polling) return;
        c._polling = true;
        var until = Date.now() + POLL_FOR;
        (function tick() {
            setTimeout(function () {
                if (st.dead || !c.isConnected || !st.o.mediaStatus) { c._polling = false; return; }
                st.o.mediaStatus(c.dataset.asset).then(function (a) {
                    if (a.status === 'ready') { c._polling = false; var playingLocal = c._local; showVideo(st, c, a, null); if (playingLocal) setTimeout(function () { URL.revokeObjectURL(playingLocal); }, 4000); c._local = null; return; }
                    if (a.status === 'failed') { c._polling = false; failed(st, c); return; }
                    if (Date.now() < until) tick(); else c._polling = false;
                }).catch(function () { if (Date.now() < until) tick(); else c._polling = false; });
            }, POLL_MS);
        })();
    }
    function failed(st, c) {
        c.classList.remove('is-preparing', 'is-uploading');
        c.classList.add('is-failed');
        var msg = c.querySelector('.clip-wait');
        if (msg) msg.lastChild.textContent = st.T.clip_failed;
        else c.insertBefore(h('span', 'clip-time', st.T.clip_failed), c.querySelector('.clip-x'));
    }
    function toggle(st, c, play, wave, time) {
        if (playing && playing.clip === c) {
            if (playing.audio.paused) playing.audio.play().catch(function () {}); else playing.audio.pause();
            return;
        }
        if (playing) playing.audio.pause();
        var src = c._src();
        if (!src) return;
        var a = new Audio(src);
        var bars = wave.children;
        playing = { clip: c, audio: a };
        a.addEventListener('play', function () { play.replaceChildren(icon('bi-pause-fill')); play.setAttribute('aria-label', st.T.clip_pause); c.classList.add('is-playing'); });
        a.addEventListener('pause', function () { play.replaceChildren(icon('bi-play-fill')); play.setAttribute('aria-label', st.T.clip_play); c.classList.remove('is-playing'); });
        a.addEventListener('timeupdate', function () {
            if (!a.duration || !isFinite(a.duration)) return;
            var f = a.currentTime / a.duration, lit = Math.round(f * bars.length);
            for (var i = 0; i < bars.length; i++) bars[i].classList.toggle('is-on', i < lit);
            time.textContent = clock(a.currentTime * 1000);
        });
        a.addEventListener('ended', function () {
            for (var i = 0; i < bars.length; i++) bars[i].classList.remove('is-on');
            time.textContent = clock(a.duration * 1000);
            if (playing && playing.audio === a) playing = null;
        });
        a.play().catch(function () {});
    }

    /* ── Indenting a list: an item goes under the one above it (a list of its own
       inside that item), or back out again; the items after it come along. ── */
    function newList(like) {
        var l = document.createElement(like.tagName);
        if (like.hasAttribute('data-check')) l.setAttribute('data-check', '1');
        return l;
    }
    function indentLi(li) {
        var prev = li.previousElementSibling;
        if (!prev || prev.tagName !== 'LI') return false;
        var list = li.parentNode, sub = prev.lastElementChild;
        if (!sub || sub.tagName !== list.tagName) { sub = newList(list); prev.appendChild(sub); }
        sub.appendChild(li);
        return true;
    }
    function outdentLi(li) {
        var list = li.parentNode, outer = list.parentNode;
        if (!outer || outer.tagName !== 'LI') return false;
        var rest = [];
        for (var n = li.nextElementSibling; n; n = n.nextElementSibling) rest.push(n);
        if (rest.length) {
            var sub = li.lastElementChild && li.lastElementChild.tagName === list.tagName ? li.lastElementChild : null;
            if (!sub) { sub = newList(list); li.appendChild(sub); }
            rest.forEach(function (r) { sub.appendChild(r); });
        }
        outer.after(li);
        if (!list.children.length) list.remove();
        return true;
    }
    function indentItems(st, deeper) {
        var items = dirBlocks(st).filter(function (b) { return b.tagName === 'LI'; });
        if (!items.length) return false;
        var here = items[0], at = caretChars(here), moved = false;
        (deeper ? items : items.slice().reverse()).forEach(function (li) { moved = (deeper ? indentLi(li) : outdentLi(li)) || moved; });
        caretTo(here, at);
        var sel = window.getSelection();
        if (sel.rangeCount && st.el.contains(sel.anchorNode)) st.range = sel.getRangeAt(0).cloneRange(); // the next tool acts here, not where the item used to be
        return moved;
    }

    /* ── Tables ───────────────────────────────────────────────────────
       A table is plain rows and cells. The grid (which cell covers which
       row and column, spans included) is worked out afresh before every
       change, so merged cells never confuse a row or column added later. */
    var FILLS = [1, 2, 3, 4, 5, 6];
    function cellAt(node) {
        if (node && node.nodeType === 3) node = node.parentNode;
        return node && node.closest ? node.closest('td,th') : null;
    }
    function caretCell(st) {
        var sel = window.getSelection();
        if (!sel.rangeCount || !st.el.contains(sel.anchorNode)) return null;
        var c = cellAt(sel.anchorNode);
        return c && st.el.contains(c) ? c : null;
    }
    function span(cell, a) { var n = parseInt(cell.getAttribute(a), 10); return n > 1 ? n : 1; }
    function setSpan(cell, a, n) { if (n > 1) cell.setAttribute(a, n); else cell.removeAttribute(a); }
    function newCell(tag) { var c = document.createElement(tag || 'td'); c.appendChild(h('br')); return c; }
    function gridOf(table) {
        var rows = Array.prototype.filter.call(table.querySelectorAll('tr'), function (tr) { return tr.closest('table') === table; });
        var g = rows.map(function () { return []; });
        rows.forEach(function (tr, r) {
            var c = 0;
            Array.prototype.forEach.call(tr.children, function (cell) {
                if (!/^T[DH]$/.test(cell.tagName)) return;
                while (g[r][c]) c++;
                var cs = span(cell, 'colspan'), rs = span(cell, 'rowspan');
                cell._r = r; cell._c = c;
                for (var i = 0; i < rs && r + i < rows.length; i++) for (var j = 0; j < cs; j++) g[r + i][c + j] = cell;
                c += cs;
            });
        });
        var w = 0; g.forEach(function (row) { w = Math.max(w, row.length); });
        return { rows: rows, g: g, w: w };
    }
    /* put a cell into row r so that it sits at column col (cells already there keep their places) */
    function placeCell(G, r, col, cell) {
        var before = null;
        Array.prototype.some.call(G.rows[r].children, function (x) { if (x._r === r && x._c > col) { before = x; return true; } return false; });
        G.rows[r].insertBefore(cell, before);
        cell._r = r; cell._c = col;
    }
    function rowTag(G, r) { return G.rows[r] && G.rows[r].firstElementChild && G.rows[r].firstElementChild.tagName === 'TH' ? 'th' : 'td'; }
    function insertRow(table, R, below) {
        var G = gridOf(table), at = below ? R + 1 : R, tr = h('tr'), seen = [];
        for (var c = 0; c < G.w; c++) {
            var up = G.g[at - 1] && G.g[at - 1][c], down = G.g[at] && G.g[at][c];
            if (up && up === down) { if (seen.indexOf(up) === -1) { seen.push(up); setSpan(up, 'rowspan', span(up, 'rowspan') + 1); } }
            else tr.appendChild(newCell());
        }
        var ref = G.rows[at] || null, parent = (ref || G.rows[R]).parentNode;
        parent.insertBefore(tr, ref);
        return tr.firstElementChild;
    }
    function insertCol(table, C, after) {
        var G = gridOf(table), at = after ? C + 1 : C, seen = [], first = null;
        for (var r = 0; r < G.rows.length; r++) {
            var left = G.g[r][at - 1], right = G.g[r][at];
            if (left && left === right) { if (seen.indexOf(left) === -1) { seen.push(left); setSpan(left, 'colspan', span(left, 'colspan') + 1); } continue; }
            var c = newCell(rowTag(G, r));
            placeCell(G, r, at - 0.5, c);
            if (!first) first = c;
        }
        return first;
    }
    function deleteRow(table, R) {
        var G = gridOf(table), seen = [];
        if (G.rows.length < 2) return null;
        for (var c = 0; c < G.w; c++) {
            var cell = G.g[R][c];
            if (!cell || seen.indexOf(cell) !== -1) continue;
            seen.push(cell);
            var rs = span(cell, 'rowspan');
            if (cell._r < R) setSpan(cell, 'rowspan', rs - 1);
            else if (rs > 1) { setSpan(cell, 'rowspan', rs - 1); placeCell(G, R + 1, cell._c - 0.5, cell); }
        }
        G.rows[R].remove();
        var G2 = gridOf(table), row = G2.g[Math.min(R, G2.rows.length - 1)] || [];
        return row[0] || null;
    }
    function deleteCol(table, C) {
        var G = gridOf(table), seen = [];
        if (G.w < 2) return null;
        for (var r = 0; r < G.rows.length; r++) {
            var cell = G.g[r][C];
            if (!cell || seen.indexOf(cell) !== -1) continue;
            seen.push(cell);
            var cs = span(cell, 'colspan');
            if (cs > 1) setSpan(cell, 'colspan', cs - 1); else cell.remove();
        }
        var G2 = gridOf(table);
        return (G2.g[0] || [])[Math.min(C, G2.w - 1)] || null;
    }
    /* the rectangle a and b mark out, grown until no merged cell sticks out of it */
    function rectOf(G, a, b) {
        var r1 = Math.min(a._r, b._r), r2 = Math.max(a._r + span(a, 'rowspan'), b._r + span(b, 'rowspan')) - 1;
        var c1 = Math.min(a._c, b._c), c2 = Math.max(a._c + span(a, 'colspan'), b._c + span(b, 'colspan')) - 1;
        for (var grew = true; grew;) {
            grew = false;
            for (var r = r1; r <= r2; r++) for (var c = c1; c <= c2; c++) {
                var x = G.g[r] && G.g[r][c]; if (!x) continue;
                var xr2 = x._r + span(x, 'rowspan') - 1, xc2 = x._c + span(x, 'colspan') - 1;
                if (x._r < r1) { r1 = x._r; grew = true; } if (xr2 > r2) { r2 = xr2; grew = true; }
                if (x._c < c1) { c1 = x._c; grew = true; } if (xc2 > c2) { c2 = xc2; grew = true; }
            }
        }
        var cells = [];
        for (var rr = r1; rr <= r2; rr++) for (var cc = c1; cc <= c2; cc++) { var y = G.g[rr] && G.g[rr][cc]; if (y && cells.indexOf(y) === -1) cells.push(y); }
        return { r1: r1, r2: r2, c1: c1, c2: c2, cells: cells };
    }
    function blankCell(c) { return !c.textContent.trim() && !c.querySelector('img'); }
    function mergeCells(table, sel) {
        var G = gridOf(table), R = rectOf(G, sel.a, sel.b), keep = G.g[R.r1][R.c1];
        R.cells.forEach(function (x) {
            if (x === keep) return;
            if (!blankCell(x)) {
                if (blankCell(keep)) keep.replaceChildren(); else keep.appendChild(h('br'));
                while (x.firstChild) keep.appendChild(x.firstChild);
            }
            x.remove();
        });
        setSpan(keep, 'colspan', R.c2 - R.c1 + 1);
        setSpan(keep, 'rowspan', R.r2 - R.r1 + 1);
        return keep;
    }
    function splitCell(table, cell) {
        var G = gridOf(table), cs = span(cell, 'colspan'), rs = span(cell, 'rowspan'), r0 = cell._r, c0 = cell._c;
        if (cs > 1 || rs > 1) { // a merged cell goes back to the cells it covered
            setSpan(cell, 'colspan', 1); setSpan(cell, 'rowspan', 1);
            for (var r = r0; r < r0 + rs; r++) for (var c = c0; c < c0 + cs; c++) if (r !== r0 || c !== c0) placeCell(G, r, c - 0.5, newCell(rowTag(G, r)));
            return cell;
        }
        // a single cell becomes two side by side; the rest of its column widens to keep the rows even
        var seen = [];
        for (var rr = 0; rr < G.rows.length; rr++) {
            var x = G.g[rr][c0];
            if (!x || x === cell || seen.indexOf(x) !== -1) continue;
            seen.push(x); setSpan(x, 'colspan', span(x, 'colspan') + 1);
        }
        var twin = newCell(cell.tagName.toLowerCase());
        cell.after(twin);
        return twin;
    }
    function rename(cell, tag) {
        if (cell.tagName.toLowerCase() === tag) return cell;
        var n = document.createElement(tag);
        Array.prototype.forEach.call(cell.attributes, function (a) { n.setAttribute(a.name, a.value); });
        while (cell.firstChild) n.appendChild(cell.firstChild);
        cell.replaceWith(n);
        return n;
    }
    function toggleHeader(table) {
        var G = gridOf(table), row = G.rows[0];
        if (!row) return;
        var on = Array.prototype.every.call(row.children, function (c) { return c.tagName === 'TH'; });
        Array.prototype.slice.call(row.children).forEach(function (c) { rename(c, on ? 'td' : 'th'); });
    }
    function insertTable(st, rows, cols) {
        restoreRange(st);
        var table = h('table'), body = h('tbody');
        for (var r = 0; r < rows; r++) {
            var tr = h('tr');
            for (var c = 0; c < cols; c++) tr.appendChild(newCell(r === 0 && rows > 1 ? 'th' : 'td'));
            body.appendChild(tr);
        }
        table.appendChild(body);
        placeBlock(st, table);
        caretIn(st, table.querySelector('th,td'));
        edited(st);
    }
    function caretIn(st, cell) {
        if (!cell || !cell.isConnected) return;
        var r = document.createRange(); r.selectNodeContents(cell); r.collapse(true);
        var sel = window.getSelection(); sel.removeAllRanges(); sel.addRange(r);
        st.range = r.cloneRange();
    }
    function nextCell(st, cell, back) {
        var table = cell.closest('table'), all = Array.prototype.filter.call(table.querySelectorAll('td,th'), function (c) { return c.closest('table') === table; });
        var i = all.indexOf(cell) + (back ? -1 : 1);
        if (i >= 0 && i < all.length) { caretIn(st, all[i]); return; }
        if (back) return;
        var G = gridOf(table);
        caretIn(st, insertRow(table, G.rows.length - 1, true)); // Tab in the last cell: a new row, as Word does
        edited(st);
    }

    /* Several cells picked at once (to merge, shade or align): dragged across with a mouse,
       shift-clicked, or — on a phone — "Select cells" then a tap on the last one. */
    function selectCells(st, a, b) {
        clearSel(st);
        var table = a.closest('table'), R = rectOf(gridOf(table), a, b);
        R.cells.forEach(function (c) { c.classList.add('is-sel'); });
        st.tsel = { table: table, a: a, b: b, cells: R.cells };
    }
    function clearSel(st) {
        if (!st.tsel) return;
        st.tsel.cells.forEach(function (c) { c.classList.remove('is-sel'); if (!c.getAttribute('class')) c.removeAttribute('class'); });
        st.tsel = null;
    }
    function tableTargets(st, cell) { return st.tsel && st.tsel.table.isConnected ? st.tsel.cells.filter(function (c) { return c.isConnected; }) : (cell ? [cell] : []); }

    /* The table's own bar, over the table while the caret is in it. */
    function tableBar(st) {
        var cell = st.el.contentEditable === 'true' ? caretCell(st) : null;
        var table = cell ? cell.closest('table') : (st.tsel && st.tsel.table.isConnected ? st.tsel.table : null);
        if (!table || !st.el.contains(table)) { hideTableBar(st); return; }
        if (cell) st.tcell = cell;
        if (st.tbar && st.tbarFor === table) { st.tbarSync(); st.tbarPlace(); return; }
        hideTableBar(st);
        var T = st.T, host = st.el.parentNode, bar = h('div', 'img-chips tbl-bar'); bar.dataset.ui = '1';
        bar.setAttribute('role', 'toolbar'); bar.setAttribute('aria-label', T.fmt_table);
        function live() { var c = st.tcell && st.tcell.isConnected && st.tcell.closest('table') === table ? st.tcell : null; return c || table.querySelector('td,th'); }
        function btn(ic, label, fn, cls, quiet) {
            var b = h('button', 'img-chip' + (cls ? ' ' + cls : '')); b.type = 'button';
            b.appendChild(icon(ic));
            if (quiet) { b.setAttribute('aria-label', label); b.title = label; } else b.appendChild(document.createTextNode(label));
            b.addEventListener('pointerdown', function (e) { e.preventDefault(); });
            b.addEventListener('click', function (e) { e.preventDefault(); fn(); });
            bar.appendChild(b);
            return b;
        }
        function sep() { bar.appendChild(h('span', 'tbl-sep')); }
        /* every change: worked out, the caret put somewhere sensible, saved, the bar brought up to date */
        function run(fn) {
            var c = live(); if (!c) return;
            var go = fn(c);
            clearSel(st);
            if (!table.isConnected) { hideTableBar(st); edited(st); return; }
            if (go && go.isConnected) caretIn(st, go); else if (c.isConnected) caretIn(st, c);
            edited(st);
            tableBar(st);
        }
        btn('bi-arrow-bar-up', T.table_row_above, function () { run(function (c) { gridOf(table); insertRow(table, c._r, false); return c; }); });
        btn('bi-arrow-bar-down', T.table_row_below, function () { run(function (c) { gridOf(table); insertRow(table, c._r + span(c, 'rowspan') - 1, true); return c; }); });
        btn('bi-arrow-bar-left', T.table_col_before, function () { run(function (c) { gridOf(table); insertCol(table, c._c, false); return c; }); }, 'is-flip');
        btn('bi-arrow-bar-right', T.table_col_after, function () { run(function (c) { gridOf(table); insertCol(table, c._c + span(c, 'colspan') - 1, true); return c; }); }, 'is-flip');
        sep();
        btn('bi-subtract', T.table_merge, function () {
            if (!st.tsel || st.tsel.cells.length < 2) { st.o.toast(T.table_merge_hint); return; }
            var s = st.tsel; run(function () { return mergeCells(table, s); });
        });
        btn('bi-layout-split', T.table_split, function () { run(function (c) { return splitCell(table, c); }); });
        var pick = btn('bi-bounding-box-circles', T.table_select, function () {
            if (st.tpick) { st.tpick = null; pick.classList.remove('is-on'); return; }
            st.tpick = { table: table, from: live() }; pick.classList.add('is-on');
            st.o.toast(T.table_select_hint);
        });
        sep();
        var head = btn('bi-layout-text-window', T.table_header, function () { run(function (c) { var r = c._r, col = c._c; toggleHeader(table); var G = gridOf(table); return (G.g[r] || [])[col]; }); });
        var looks = [['grid', 'bi-border-all', T.table_grid], ['stripes', 'bi-list', T.table_stripes], ['plain', 'bi-border-outer', T.table_plain]].map(function (l) {
            var b = btn(l[1], l[2], function () { run(function (c) { if (l[0] === 'grid') table.removeAttribute('data-look'); else table.dataset.look = l[0]; return c; }); }, null, true);
            b.dataset.look = l[0];
            return b;
        });
        sep();
        FILLS.concat([0]).forEach(function (n) {
            var s = h('button', 'tbl-fill' + (n ? '' : ' is-none')); s.type = 'button';
            if (n) s.dataset.fill = n;
            var lbl = n ? T.table_fill : T.table_no_fill; s.setAttribute('aria-label', lbl); s.title = lbl;
            if (!n) s.appendChild(icon('bi-slash-lg'));
            s.addEventListener('pointerdown', function (e) { e.preventDefault(); });
            s.addEventListener('click', function (e) {
                e.preventDefault();
                var cells = tableTargets(st, live());
                run(function (c) { cells.forEach(function (x) { if (n) x.dataset.fill = n; else x.removeAttribute('data-fill'); }); return c; });
            });
            bar.appendChild(s);
        });
        sep();
        [['start', 'bi-text-left', T.table_align_start], ['center', 'bi-text-center', T.table_align_center], ['end', 'bi-text-right', T.table_align_end]].forEach(function (a) {
            btn(a[1], a[2], function () {
                var cells = tableTargets(st, live());
                run(function (c) { cells.forEach(function (x) { x.style.textAlign = a[0] === 'start' ? '' : a[0]; if (!x.getAttribute('style')) x.removeAttribute('style'); }); return c; });
            }, 'is-flip', true);
        });
        sep();
        btn('bi-dash-square', T.table_del_row, function () { run(function (c) { gridOf(table); if (gridOf(table).rows.length < 2) { table.remove(); return null; } return deleteRow(table, c._r); }); }, 'is-danger');
        btn('bi-dash-square', T.table_del_col, function () { run(function (c) { var G = gridOf(table); if (G.w < 2) { table.remove(); return null; } return deleteCol(table, c._c); }); }, 'is-danger');
        btn('bi-trash3', T.table_delete, function () { clearSel(st); hideTableBar(st); removeBlock(st, table, T.table_removed); }, 'is-danger');

        host.appendChild(bar);
        st.tbar = bar; st.tbarFor = table;
        st.tbarSync = function () {
            var first = gridOf(table).rows[0];
            head.classList.toggle('is-on', !!first && Array.prototype.every.call(first.children, function (c) { return c.tagName === 'TH'; }));
            var look = table.dataset.look || 'grid';
            looks.forEach(function (b) { b.classList.toggle('is-on', b.dataset.look === look); });
            pick.classList.toggle('is-on', !!st.tpick);
        };
        st.tbarPlace = function () {
            var hr = host.getBoundingClientRect(), tr = table.getBoundingClientRect();
            var above = tr.top - hr.top - bar.offsetHeight - 8;
            bar.style.top = (above > 4 ? above : tr.bottom - hr.top + 8) + host.scrollTop + 'px';
            bar.style.left = Math.max(8, tr.left - hr.left + host.scrollLeft) + 'px';
        };
        st.tbarSync(); st.tbarPlace();
    }
    function hideTableBar(st) { if (st.tbar) { st.tbar.remove(); st.tbar = null; st.tbarFor = null; } }
    function bindTables(st) {
        var ed = st.el;
        ed.addEventListener('pointerdown', function (e) {
            if (ed.contentEditable !== 'true') return;
            var c = cellAt(e.target);
            if (c && !ed.contains(c)) c = null;
            if (st.tpick && c && st.tpick.table === c.closest('table') && st.tpick.from && st.tpick.from.isConnected) {
                e.preventDefault();
                var from = st.tpick.from; st.tpick = null;
                if (from !== c) selectCells(st, from, c);
                tableBar(st); return;
            }
            clearSel(st);
            if (!c || e.pointerType !== 'mouse' || e.button !== 0) return;
            var was = caretCell(st);
            if (e.shiftKey && was && was !== c && was.closest('table') === c.closest('table')) { e.preventDefault(); selectCells(st, was, c); tableBar(st); return; }
            var drag = { a: c, table: c.closest('table') };
            function move(ev) {
                var over = cellAt(document.elementFromPoint(ev.clientX, ev.clientY));
                if (!over || over.closest('table') !== drag.table) return;
                if (over === drag.a && !st.tsel) return;
                drag.table.classList.add('is-picking');
                selectCells(st, drag.a, over);
                var s = window.getSelection(); if (s.rangeCount && !s.isCollapsed) s.collapseToStart();
            }
            function up() {
                document.removeEventListener('pointermove', move, true);
                document.removeEventListener('pointerup', up, true);
                drag.table.classList.remove('is-picking');
                if (!drag.table.getAttribute('class')) drag.table.removeAttribute('class');
                if (st.tsel) { caretIn(st, drag.a); tableBar(st); }
            }
            document.addEventListener('pointermove', move, true);
            document.addEventListener('pointerup', up, true);
        });
        ed.addEventListener('input', function () { clearSel(st); });
    }

    /* ── Markdown ──────────────────────────────────────────────────────
       Converted by the server (NoteHtml::fromMarkdown), which returns the
       note's own cleaned formatting; it goes in where the caret was. */
    var MD_MAX = 512 * 1024;
    function isMarkdownFile(f) { return /\.(md|markdown|mdown|mkd)$/i.test(f.name || '') || f.type === 'text/markdown'; }
    /* Does pasted text read as Markdown? Only on a sign nobody types by accident in plain prose. */
    function looksMarkdown(t) {
        if (t.length > MD_MAX) return false;
        var lines = t.split(/\r?\n/), many = lines.length > 1;
        var bullets = lines.filter(function (l) { return /^\s*([-*+]|\d+[.)])\s+\S/.test(l); }).length;
        return /\*\*[^*\n]+\*\*/.test(t) || /\[[^\]\n]+\]\(https?:\/\/[^)\s]+\)/.test(t)
            || (many && (/^#{1,6}\s+\S/m.test(t) || /^\s*```/m.test(t) || /^\s*[-*+]\s+\[[ xX]\]\s/m.test(t)
                || (/^\s*\|.*\|\s*$/m.test(t) && /^\s*\|?\s*:?-{3,}:?\s*\|/m.test(t)) || /^>\s/m.test(t) || bullets >= 2));
    }
    function markdownFile(st, f) {
        var T = st.T;
        if (!isMarkdownFile(f) && !/\.txt$/i.test(f.name || '')) { st.o.toast(T.md_failed, { error: true }); return; }
        if (f.size > MD_MAX) { st.o.toast(T.md_too_big, { error: true }); return; }
        f.text().then(function (text) {
            var sel = window.getSelection();
            var r = st.range && st.el.contains(st.range.startContainer) ? st.range.cloneRange() : (sel.rangeCount && st.el.contains(sel.anchorNode) ? sel.getRangeAt(0).cloneRange() : null);
            pasteMarkdown(st, text, r, false);
        }).catch(function () { st.o.toast(T.md_failed, { error: true }); });
    }
    function pasteMarkdown(st, text, range, pasted) {
        var T = st.T;
        st.uploads++;
        st.o.markdown(text).then(function (html) {
            st.uploads--;
            if (st.dead) return;
            var nodes = putMarkdown(st, html || '', range);
            if (!nodes.length) return;
            if (pasted) st.o.toast(T.md_converted, { action: T.md_keep_text, onAction: function () { asText(st, nodes, text); } });
        }).catch(function (err) {
            st.uploads--;
            if (st.dead) return;
            if (pasted) { st.range = range; restoreRange(st); document.execCommand('insertText', false, text); return; } // the server could not convert: the text, as typed
            st.o.toast((err && err.data && err.data.message) || T.md_failed, { error: true });
        });
    }
    /* the converted blocks, where the caret was; a single line of words goes in inline */
    function putMarkdown(st, html, range) {
        var tpl = document.createElement('template');
        tpl.innerHTML = html; // the server's cleaned note formatting (NoteHtml), never the raw text
        var nodes = Array.prototype.filter.call(tpl.content.childNodes, function (n) { return n.nodeType === 1 || (n.nodeType === 3 && n.data.trim()); });
        if (!nodes.length) return [];
        if (range && !st.el.contains(range.startContainer)) range = null;
        if (nodes.length === 1 && nodes[0].tagName === 'P' && range && range.startContainer !== st.el) { // **bold** and a link: words in the line, not a new paragraph
            var frag = document.createDocumentFragment(), inl = Array.prototype.slice.call(nodes[0].childNodes);
            inl.forEach(function (n) { frag.appendChild(n); });
            range.deleteContents(); range.insertNode(frag);
            var after = document.createRange(); after.setStartAfter(inl[inl.length - 1]); after.collapse(true);
            var sel = window.getSelection(); sel.removeAllRanges(); sel.addRange(after); st.range = after.cloneRange();
            edited(st);
            return inl;
        }
        nodes = nodes.map(function (n) { if (n.nodeType === 3) { var p = h('p'); p.appendChild(n); return p; } return n; });
        st.range = range;
        placeBlock(st, nodes[0]);
        for (var i = 1; i < nodes.length; i++) nodes[i - 1].after(nodes[i]);
        var last = nodes[nodes.length - 1], next = last.nextElementSibling;
        if (!next || (next.dataset && next.dataset.ui)) { next = h('p'); next.appendChild(h('br')); last.after(next); }
        caretIn(st, next);
        wrapLoose(st.el);
        edited(st);
        return nodes;
    }
    /* "Keep as text": the converted blocks go, the text goes in as it was typed */
    function asText(st, nodes, text) {
        var live = nodes.filter(function (n) { return n.isConnected && st.el.contains(n); });
        if (!live.length || st.dead) return;
        var holder = live[0].nodeType === 1 && live[0].parentNode === st.el ? null : live[0].parentNode;
        var place = text.split(/\r?\n/).map(function (line) { var p = h('p'); if (line) p.textContent = line; else p.appendChild(h('br')); return p; });
        if (holder) { // it went in inline: back to the words, inline
            var t = document.createTextNode(text); live[0].before(t); live.forEach(function (n) { n.remove(); });
        } else {
            place.forEach(function (p) { live[0].before(p); });
            live.forEach(function (n) { n.remove(); });
        }
        edited(st);
    }

    /* ── Videos from other sites ────────────────────────────────────────
       Only these sites, only an id of their own shape (NoteHtml::EMBEDS holds
       the same list). Nothing of the site loads until the play button is
       tapped, except a YouTube video's still frame. */
    var EMBEDS = {
        sites: {
            youtube: { id: /^[A-Za-z0-9_-]{11}$/, name: 'YouTube', icon: 'bi-youtube',
                player: function (id) { return 'https://www.youtube-nocookie.com/embed/' + id + '?autoplay=1&rel=0&playsinline=1'; },
                page: function (id) { return 'https://www.youtube.com/watch?v=' + id; },
                cover: function (id) { return 'https://i.ytimg.com/vi/' + id + '/hqdefault.jpg'; } },
            vimeo: { id: /^[0-9]{6,12}$/, name: 'Vimeo', icon: 'bi-vimeo',
                player: function (id) { return 'https://player.vimeo.com/video/' + id + '?autoplay=1'; },
                page: function (id) { return 'https://vimeo.com/' + id; } },
            dailymotion: { id: /^x[A-Za-z0-9]{4,12}$/, name: 'Dailymotion', icon: 'bi-play-btn-fill',
                player: function (id) { return 'https://www.dailymotion.com/embed/video/' + id + '?autoplay=1'; },
                page: function (id) { return 'https://www.dailymotion.com/video/' + id; } },
            // a post or a reel: Instagram's own embed page (no script of theirs runs on ours)
            instagram: { id: /^[A-Za-z0-9_-]{5,40}$/, name: 'Instagram', icon: 'bi-instagram', tall: true,
                player: function (id) { return 'https://www.instagram.com/p/' + id + '/embed/'; },
                page: function (id) { return 'https://www.instagram.com/p/' + id + '/'; } },
            tiktok: { id: /^[0-9]{15,22}$/, name: 'TikTok', icon: 'bi-tiktok', tall: true,
                player: function (id) { return 'https://www.tiktok.com/player/v1/' + id + '?autoplay=1&rel=0'; },
                page: function (id) { return 'https://www.tiktok.com/@/video/' + id; } }
        },
        /* "site:id" as stored → { site, id }, or null */
        parse: function (v) {
            var m = /^([a-z]+):([A-Za-z0-9_-]+)$/.exec(v || ''), s = m && EMBEDS.sites[m[1]];
            return s && s.id.test(m[2]) ? { site: m[1], id: m[2] } : null;
        },
        /* a pasted address → { site, id }, or null when it is not a video we can play */
        fromUrl: function (href) {
            var u; try { u = new URL(href); } catch (e) { return null; }
            if (u.protocol !== 'https:' && u.protocol !== 'http:') return null;
            var host = u.hostname.toLowerCase().replace(/^(www|m|music)\./, ''), path = u.pathname.split('/').filter(Boolean), id = null, site = null;
            if (host === 'youtube.com' || host === 'youtube-nocookie.com') {
                site = 'youtube';
                id = path[0] === 'watch' ? u.searchParams.get('v') : (/^(shorts|embed|live|v)$/.test(path[0] || '') ? path[1] : null);
            } else if (host === 'youtu.be') { site = 'youtube'; id = path[0]; }
            else if (host === 'vimeo.com') { site = 'vimeo'; id = path.filter(function (p) { return /^[0-9]+$/.test(p); })[0]; }
            else if (host === 'player.vimeo.com' && path[0] === 'video') { site = 'vimeo'; id = path[1]; }
            else if (host === 'dailymotion.com') { site = 'dailymotion'; id = path[0] === 'video' || (path[0] === 'embed' && path[1] === 'video') ? path[path[0] === 'video' ? 1 : 2] : null; }
            else if (host === 'dai.ly') { site = 'dailymotion'; id = path[0]; }
            else if (host === 'instagram.com') { // /p/{code}, /reel/{code}, /reels/{code}, /tv/{code}, and the newer /{user}/p/{code}
                var at = path.findIndex(function (p) { return /^(p|reel|reels|tv)$/.test(p); });
                site = 'instagram'; id = at !== -1 ? path[at + 1] : null;
            } else if (host === 'tiktok.com') { // /@user/video/{id}, /embed/v2/{id}, /player/v1/{id}
                var vi = path.findIndex(function (p) { return p === 'video' || p === 'v2' || p === 'v1' || p === 'v'; });
                site = 'tiktok'; id = vi !== -1 && path[vi + 1] ? path[vi + 1].replace(/\.html$/, '') : null;
            }
            if (site === 'dailymotion' && id) id = id.split('_')[0]; // dailymotion.com/video/x8abc_some-title
            return site && id ? EMBEDS.parse(site + ':' + id) : null;
        },
        /* a TikTok share link that names no video until it is followed (the server does that) */
        isShort: function (href) {
            var u; try { u = new URL(href); } catch (e) { return false; }
            var host = u.hostname.toLowerCase();
            return u.protocol === 'https:' && (host === 'vm.tiktok.com' || host === 'vt.tiktok.com' || (/^(www\.)?tiktok\.com$/.test(host) && u.pathname.indexOf('/t/') === 0));
        }
    };
    /* A short TikTok link: a card saying it is being found, then the video — or, if it is not one, the plain link it was. */
    function resolveEmbed(st, href) {
        var T = st.T, c = h('div', 'clip is-embed is-tall is-tiktok is-resolving');
        c.contentEditable = 'false';
        var stage = h('div', 'embed-stage'), wait = h('span', 'embed-site');
        wait.append(h('span', 'clip-spin'), document.createTextNode(T.embed_finding));
        stage.appendChild(wait); c.appendChild(stage);
        placeBlock(st, c);
        st.uploads++;
        st.o.videoLink(href).catch(function () { return null; }).then(function (v) {
            st.uploads--;
            if (st.dead || !c.isConnected) return;
            v = EMBEDS.parse(v);
            if (v) { c.replaceWith(embedClip(st, v)); edited(st); return; }
            var p = h('p'), a = h('a', null, shortLabel(href)); a.href = href; p.appendChild(a);
            c.replaceWith(p); edited(st);
        });
    }
    function embedClip(st, v) {
        var T = st.T, s = EMBEDS.sites[v.site];
        var c = h('div', 'clip is-embed is-' + v.site + (s.tall ? ' is-tall' : ''));
        c.contentEditable = 'false';
        c.dataset.embed = v.site + ':' + v.id;

        var stage = h('div', 'embed-stage');
        if (s.cover) { var im = h('img', 'embed-cover'); im.alt = ''; im.loading = 'lazy'; im.referrerPolicy = 'no-referrer'; im.draggable = false; im.src = s.cover(v.id); im.onerror = function () { im.remove(); }; stage.appendChild(im); }
        var play = h('button', 'embed-play'); play.type = 'button';
        play.setAttribute('aria-label', T.embed_play); play.title = T.embed_play;
        play.appendChild(icon('bi-play-fill'));
        var badge = h('span', 'embed-site'); badge.append(icon(s.icon), document.createTextNode(s.name));
        var open = h('a', 'embed-open'); open.href = s.page(v.id); open.target = '_blank'; open.rel = 'noopener noreferrer';
        var openLabel = (T.embed_open || '').replace(':site', s.name);
        open.setAttribute('aria-label', openLabel); open.title = openLabel;
        open.appendChild(icon('bi-box-arrow-up-right'));
        if (!s.cover) { var mark = h('span', 'embed-mark'); mark.appendChild(icon(s.icon)); stage.appendChild(mark); } // the site's own sign, large and faint, where there is no still frame
        stage.append(play, badge, open);

        var grip = h('button', 'clip-grip'); grip.type = 'button';
        grip.setAttribute('aria-label', T.move_this); grip.title = T.move_this;
        grip.appendChild(icon('bi-grip-vertical'));
        grip.addEventListener('pointerdown', function (e) { startMove(st, c, e); });
        grip.addEventListener('click', function (e) { e.preventDefault(); e.stopPropagation(); });

        var x = h('button', 'clip-x'); x.type = 'button';
        x.setAttribute('aria-label', T.clip_remove); x.title = T.clip_remove;
        x.appendChild(icon('bi-x-lg'));
        x.addEventListener('click', function (e) { e.preventDefault(); e.stopPropagation(); c._stop(); removeBlock(st, c, T.embed_removed); });

        play.addEventListener('click', function (e) {
            e.preventDefault(); e.stopPropagation();
            var f = h('iframe', 'embed-frame');
            f.src = s.player(v.id); // built from the site's fixed address and a checked id — never the pasted text
            f.title = s.name;
            f.allow = 'autoplay; encrypted-media; picture-in-picture; fullscreen';
            f.allowFullscreen = true;
            f.referrerPolicy = 'strict-origin-when-cross-origin';
            stage.appendChild(f);
            c.classList.add('is-playing');
        });
        c._stop = function () { var f = stage.querySelector('iframe'); if (f) f.remove(); c.classList.remove('is-playing'); };
        open.addEventListener('click', function (e) { e.stopPropagation(); });

        c.append(stage, grip, x);
        return c;
    }

    /* ── Drawings ─────────────────────────────────────────────────────
       A sketch on a blank page, or marks on a photo, with the shared
       MarginInk. The result is a picture; its strokes travel beside it. */
    function chip(st, img) {
        var editing = st.el.contentEditable === 'true', canInk = st.o.ink && window.MarginInk, canCrop = !!st.o.upload;
        var host = st.el.parentNode;
        hideChip(st);
        var bar = h('div', 'img-chips');
        var hr = host.getBoundingClientRect(), ir = img.getBoundingClientRect();
        bar.style.top = (ir.top - hr.top + host.scrollTop + 10) + 'px';
        bar.style.left = (ir.left - hr.left + host.scrollLeft + 10) + 'px';
        if (editing) {
            var mv = h('button', 'img-chip is-move'); mv.type = 'button';
            mv.setAttribute('aria-label', st.T.move_this); mv.title = st.T.move_this;
            mv.append(icon('bi-arrows-move'), document.createTextNode(st.T.move_short));
            mv.addEventListener('pointerdown', function (e) { startMove(st, img, e); });
            bar.appendChild(mv);
        }
        if (canInk) {
            var b = h('button', 'img-chip'); b.type = 'button';
            b.append(icon('bi-pencil-fill'), document.createTextNode(st.T.img_markup));
            b.addEventListener('pointerdown', function (e) { e.preventDefault(); });
            b.addEventListener('click', function (e) { e.preventDefault(); hideChip(st); if (st.o.isFull && st.o.isFull()) { st.o.toast(st.T.st_full, { error: true }); return; } markup(st, img); });
            bar.appendChild(b);
        }
        if (canCrop) {
            var cr = h('button', 'img-chip'); cr.type = 'button';
            cr.append(icon('bi-crop'), document.createTextNode(st.T.img_crop));
            cr.addEventListener('pointerdown', function (e) { e.preventDefault(); });
            cr.addEventListener('click', function (e) { e.preventDefault(); hideChip(st); if (st.o.isFull && st.o.isFull()) { st.o.toast(st.T.st_full, { error: true }); return; } cropPicture(st, img); });
            bar.appendChild(cr);
        }
        var del = h('button', 'img-chip is-del'); del.type = 'button';
        del.setAttribute('aria-label', st.T.clip_remove); del.title = st.T.clip_remove;
        del.appendChild(icon('bi-trash3'));
        del.addEventListener('pointerdown', function (e) { e.preventDefault(); });
        del.addEventListener('click', function (e) { e.preventDefault(); hideChip(st); removeBlock(st, img, st.T.img_removed); });
        bar.appendChild(del);
        host.appendChild(bar);
        st.chip = bar;
    }
    function hideChip(st) { if (st.chip) { st.chip.remove(); st.chip = null; } }

    /* Checklists: a bulleted list marked data-check, its items ticked with data-checked. */
    function checkItemAt() {
        var sel = window.getSelection(), n = sel.rangeCount ? sel.anchorNode : null;
        if (n && n.nodeType === 3) n = n.parentNode;
        return n && n.closest ? n.closest('ul[data-check] > li') : null;
    }
    function onBox(li, x) { // the box sits in the item's leading 30px
        var r = li.getBoundingClientRect(), rtl = getComputedStyle(li).direction === 'rtl';
        return rtl ? x >= r.right - 30 : x <= r.left + 30;
    }
    /* Where the caret is, as a character count inside an element — survives the element's
       nodes being rebuilt (a list command does), unlike a node and an offset. */
    function caretChars(el) {
        var sel = window.getSelection(); if (!sel.rangeCount || !el.contains(sel.anchorNode)) return -1;
        var r = document.createRange(); r.selectNodeContents(el); r.setEnd(sel.anchorNode, sel.anchorOffset);
        return r.toString().length;
    }
    function caretTo(el, n) {
        if (!el || n < 0) return;
        var walk = document.createTreeWalker(el, NodeFilter.SHOW_TEXT), t, left = n;
        while ((t = walk.nextNode())) {
            if (left <= t.data.length) { var r = document.createRange(); r.setStart(t, left); r.collapse(true); var sel = window.getSelection(); sel.removeAllRanges(); sel.addRange(r); return; }
            left -= t.data.length;
        }
        var r2 = document.createRange(); r2.selectNodeContents(el); r2.collapse(false); var s2 = window.getSelection(); s2.removeAllRanges(); s2.addRange(r2);
    }
    /* Chrome's list command leaves the new list INSIDE the paragraph (<p><ul>…</ul></p>), which no
       page keeps: the list comes out, the empty paragraph goes. */
    function tidyLists(st) {
        st.el.querySelectorAll('p > ul, p > ol, h1 > ul, h2 > ul, div > ul, div > ol').forEach(function (list) {
            var p = list.parentNode; if (!st.el.contains(p) || p === st.el) return;
            while (p.firstChild) p.parentNode.insertBefore(p.firstChild, p);
            p.remove();
        });
        st.el.querySelectorAll('p').forEach(function (p) { if (!p.childNodes.length) p.remove(); });
    }
    function listCommand(st, name) {
        var block = (dirBlocks(st)[0] || null), at = block ? caretChars(block) : -1, text = block ? block.textContent : '';
        document.execCommand(name);
        tidyLists(st);
        // back to the same words: the item (or line) now holding them
        var sel = window.getSelection(), n = sel.rangeCount ? sel.anchorNode : null; if (n && n.nodeType === 3) n = n.parentNode;
        var now = n && n.closest ? n.closest('li,p,h1,h2,h3,h4,blockquote,div') : null;
        if (!now || now === st.el || now.textContent !== text) { // the command left the caret on the page itself: find the words
            now = Array.prototype.find.call(st.el.querySelectorAll('li,p,h1,h2,h3,h4,blockquote'), function (b) { return b.textContent === text && !b.querySelector('li,p'); }) || null;
        }
        if (now) caretTo(now, at);
    }
    function toggleChecklist(st) {
        var blocks = dirBlocks(st);
        var sel = window.getSelection(), n = sel.rangeCount ? sel.anchorNode : null;
        if (n && n.nodeType === 3) n = n.parentNode;
        var list = n && n.closest ? n.closest('ul,ol') : null;
        if (list && !st.el.contains(list)) list = null;
        var here = blocks[0] || null, at = here ? caretChars(here) : -1;
        if (list && list.hasAttribute('data-check')) { // off again: each item back to a plain line
            var first = null;
            Array.prototype.slice.call(list.children).forEach(function (li) {
                var p = h('p'); while (li.firstChild) p.appendChild(li.firstChild);
                if (!p.childNodes.length) p.appendChild(h('br'));
                list.parentNode.insertBefore(p, list);
                if (li === here) first = p;
            });
            list.remove();
            caretTo(first, at);
            return;
        }
        if (list) { // a bulleted or numbered list becomes a checklist, items and all
            var ul = list;
            if (list.tagName === 'OL') { ul = h('ul'); while (list.firstChild) ul.appendChild(list.firstChild); if (list.getAttribute('dir')) ul.setAttribute('dir', list.getAttribute('dir')); list.replaceWith(ul); }
            ul.setAttribute('data-check', '1');
            caretTo(here, at);
            return;
        }
        // lines become items of one checklist, joined to a checklist right above or below
        var lines = blocks.filter(function (b) { return b.tagName !== 'LI' && b.parentNode === st.el; });
        if (!lines.length) return;
        var box = h('ul'); box.setAttribute('data-check', '1');
        if (lines[0].getAttribute('dir')) box.setAttribute('dir', lines[0].getAttribute('dir'));
        lines[0].parentNode.insertBefore(box, lines[0]);
        var target = null;
        lines.forEach(function (b) {
            var li = h('li'); while (b.firstChild) li.appendChild(b.firstChild);
            if (!li.childNodes.length) li.appendChild(h('br'));
            box.appendChild(li); b.remove();
            if (b === here) target = li;
        });
        var prev = box.previousElementSibling, next = box.nextElementSibling;
        if (prev && prev.matches('ul[data-check]')) { while (box.firstChild) prev.appendChild(box.firstChild); box.remove(); box = prev; }
        if (next && next.matches('ul[data-check]')) { while (next.firstChild) box.appendChild(next.firstChild); next.remove(); }
        caretTo(target, at);
    }

    /* The cursor in a link, while writing: a small bar under it — Edit (the link panel, filled in),
       Button (shown as a button, or back to a plain link), Open, Remove. Reading, a tap simply opens it. */
    function linkChip(st) {
        var sel = window.getSelection();
        var a = st.el.contentEditable === 'true' && sel.rangeCount && st.el.contains(sel.anchorNode) ? inLink(sel.anchorNode) : null;
        if (!a || !st.el.contains(a)) { hideLinkChip(st); return; }
        var host = st.el.parentNode;
        if (st.lchip && st.lchipFor === a) { st.lchipPlace(); return; }
        hideLinkChip(st);
        var T = st.T, bar = h('div', 'img-chips link-chips'); bar.dataset.ui = '1';
        function chipBtn(ic, label, fn, cls) {
            var b = h('button', 'img-chip' + (cls ? ' ' + cls : '')); b.type = 'button';
            b.append(icon(ic)); if (label) b.appendChild(document.createTextNode(label)); else { b.setAttribute('aria-label', T.link_remove); b.title = T.link_remove; }
            b.addEventListener('pointerdown', function (e) { e.preventDefault(); });
            b.addEventListener('click', function (e) { e.preventDefault(); fn(); });
            bar.appendChild(b);
            return b;
        }
        chipBtn('bi-pencil', T.link_edit, function () {
            var r = document.createRange(); r.selectNodeContents(a); r.collapse(false); st.range = r;
            hideLinkChip(st);
            if (st.sub && st.sub.link.hidden) command(st, 'link');
        });
        var asBtn = chipBtn('bi-app', T.link_button, function () {
            if (a.dataset.look === 'button') delete a.dataset.look; else a.dataset.look = 'button';
            asBtn.classList.toggle('is-on', a.dataset.look === 'button');
            edited(st); place();
        });
        asBtn.classList.toggle('is-on', a.dataset.look === 'button');
        chipBtn('bi-box-arrow-up-right', T.link_open, function () { window.open(a.href, '_blank', 'noopener'); });
        // a link alone on its line, under a line of words: one tap puts it at the end of that line
        var line = a.closest('p,div,h1,h2,h3,h4'), above = line && line.parentNode === st.el ? line.previousElementSibling : null;
        if (line && above && /^(P|DIV|H1|H2|H3|H4)$/.test(above.tagName) && above.textContent.trim() && line.textContent.replace(a.textContent, '').trim() === '') {
            chipBtn('bi-arrow-return-left', T.link_join, function () {
                hideLinkChip(st);
                var last = above.lastChild; if (last && last.nodeName === 'BR') last.remove();
                if (!/\s$/.test(above.textContent)) above.appendChild(document.createTextNode(' '));
                while (line.firstChild) above.appendChild(line.firstChild);
                line.remove();
                var r = document.createRange(); r.setStartAfter(a); r.collapse(true); var sel2 = window.getSelection(); sel2.removeAllRanges(); sel2.addRange(r);
                edited(st);
            });
        }
        chipBtn('bi-x-lg', null, function () {
            hideLinkChip(st);
            var parent = a.parentNode; while (a.firstChild) parent.insertBefore(a.firstChild, a); a.remove();
            edited(st);
        }, 'is-del');
        host.appendChild(bar);
        st.lchip = bar; st.lchipFor = a; st.lchipPlace = place;
        function place() {
            var hr = host.getBoundingClientRect(), ar = a.getBoundingClientRect();
            bar.style.top = (ar.bottom - hr.top + host.scrollTop + 8) + 'px';
            var rtl = getComputedStyle(a.closest('p,li,h1,h2,h3,h4,blockquote,div') || a).direction === 'rtl' || (a.closest('[dir]') && a.closest('[dir]').getAttribute('dir') === 'rtl');
            var left = (rtl ? ar.right - bar.offsetWidth : ar.left) - hr.left + host.scrollLeft; // under the link's own starting edge
            bar.style.left = Math.max(8, Math.min(left, host.clientWidth - bar.offsetWidth - 8)) + 'px';
        }
        place();
    }
    function hideLinkChip(st) { if (st.lchip) { st.lchip.remove(); st.lchip = null; st.lchipFor = null; st.lchipPlace = null; } }

    /* Removing a picture or a recording: gone at once (it saves), with Undo in the toast
       for a slip of the finger. A paragraph left holding nothing goes with it. */
    function removeBlock(st, node, msg) {
        var parent = node.parentNode, next = node.nextSibling;
        if (!parent) return;
        var holder = parent !== st.el && parent.parentNode && !parent.textContent.trim() && parent.querySelectorAll('img,.clip,hr').length === 1 ? parent : null;
        var gone = holder || node, at = gone.parentNode, after = gone.nextSibling;
        gone.remove();
        edited(st);
        st.o.toast(msg, { action: st.T.undo, onAction: function () {
            if (st.dead || !at.isConnected) return;
            at.insertBefore(gone, after && after.parentNode === at ? after : null);
            edited(st);
        } });
    }

    /* Cropping a picture: a frame over the photo, dragged by its corners and edges (or
       moved whole); Done uploads the cut as a new picture in the old one's place, and the
       old one stays until the new one has landed — never a note left without either. */
    function cropPicture(st, img) {
        var T = st.T, o = st.o, uuid = img.dataset.asset;
        if (!uuid || !o.upload) return;
        var MIN = 0.06, box = { x: 0, y: 0, w: 1, h: 1 }, drag = null;
        var sheet = h('div', 'crop-sheet'); sheet.setAttribute('role', 'dialog'); sheet.setAttribute('aria-label', T.img_crop);
        var bar = h('div', 'crop-bar');
        var cancel = h('button', 'crop-btn', T.rec_cancel); cancel.type = 'button';
        var title = h('span', 'crop-title'); title.append(icon('bi-crop'), document.createTextNode(T.img_crop));
        var reset = h('button', 'crop-btn is-ghost'); reset.type = 'button'; reset.title = T.undo; reset.setAttribute('aria-label', T.undo); reset.appendChild(icon('bi-arrow-counterclockwise'));
        var done = h('button', 'crop-btn is-go', T.ink_done); done.type = 'button';
        var right = h('span', 'crop-right'); right.append(reset, done);
        bar.append(cancel, title, right);
        var stage = h('div', 'crop-stage'), frame = h('div', 'crop-frame');
        var pic = new Image(); pic.alt = ''; pic.draggable = false;
        var cut = h('div', 'crop-box');
        ['n', 's', 'e', 'w', 'nw', 'ne', 'sw', 'se'].forEach(function (k) { var g = h('span', 'crop-h is-' + k); g.dataset.h = k; cut.appendChild(g); });
        frame.append(pic, cut); stage.appendChild(frame); sheet.append(bar, stage);
        document.body.appendChild(sheet);

        function paint() {
            cut.style.left = box.x * 100 + '%'; cut.style.top = box.y * 100 + '%';
            cut.style.width = box.w * 100 + '%'; cut.style.height = box.h * 100 + '%';
            done.disabled = box.w > 0.995 && box.h > 0.995;
        }
        function fit() {
            if (!pic.naturalWidth) return;
            var r = stage.getBoundingClientRect(), k = Math.min((r.width - 32) / pic.naturalWidth, (r.height - 32) / pic.naturalHeight);
            frame.style.width = Math.round(pic.naturalWidth * k) + 'px'; frame.style.height = Math.round(pic.naturalHeight * k) + 'px';
        }
        function close() { window.removeEventListener('resize', fit); document.removeEventListener('keydown', key, true); sheet.classList.add('is-gone'); setTimeout(function () { sheet.remove(); }, 220); }
        function key(e) {
            if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); close(); }
            else if (e.key === 'Enter' && !done.disabled) { e.preventDefault(); e.stopPropagation(); finish(); }
        }
        pic.onload = function () { fit(); paint(); sheet.classList.add('is-ready'); };
        pic.onerror = function () { close(); o.toast(T.bad_image, { error: true }); };
        pic.src = o.picture(uuid);
        window.addEventListener('resize', fit);
        document.addEventListener('keydown', key, true);
        cancel.addEventListener('click', close);
        reset.addEventListener('click', function () { box = { x: 0, y: 0, w: 1, h: 1 }; paint(); });

        frame.addEventListener('pointerdown', function (e) {
            var hd = e.target.closest('.crop-h'), on = hd || e.target.closest('.crop-box');
            if (!on) return;
            e.preventDefault();
            frame.setPointerCapture(e.pointerId);
            var r = frame.getBoundingClientRect();
            drag = { id: e.pointerId, h: hd ? hd.dataset.h : 'move', x: e.clientX, y: e.clientY, W: r.width, H: r.height, b: Object.assign({}, box) };
        });
        frame.addEventListener('pointermove', function (e) {
            if (!drag || e.pointerId !== drag.id) return;
            var dx = (e.clientX - drag.x) / drag.W, dy = (e.clientY - drag.y) / drag.H, b = drag.b, k = drag.h;
            var cl = function (v, lo, hi) { return Math.max(lo, Math.min(hi, v)); };
            if (k === 'move') { box.x = cl(b.x + dx, 0, 1 - b.w); box.y = cl(b.y + dy, 0, 1 - b.h); }
            else {
                var x1 = b.x, y1 = b.y, x2 = b.x + b.w, y2 = b.y + b.h;
                if (k.indexOf('w') !== -1) x1 = cl(b.x + dx, 0, x2 - MIN);
                if (k.indexOf('e') !== -1) x2 = cl(x2 + dx, x1 + MIN, 1);
                if (k.indexOf('n') !== -1) y1 = cl(b.y + dy, 0, y2 - MIN);
                if (k.indexOf('s') !== -1) y2 = cl(y2 + dy, y1 + MIN, 1);
                box = { x: x1, y: y1, w: x2 - x1, h: y2 - y1 };
            }
            paint();
        });
        var end = function (e) { if (drag && e.pointerId === drag.id) drag = null; };
        frame.addEventListener('pointerup', end); frame.addEventListener('pointercancel', end);

        function finish() {
            var nw = pic.naturalWidth, nh = pic.naturalHeight;
            var sx = Math.round(box.x * nw), sy = Math.round(box.y * nh), sw = Math.max(1, Math.round(box.w * nw)), sh = Math.max(1, Math.round(box.h * nh));
            var cv = document.createElement('canvas'); cv.width = sw; cv.height = sh;
            var cx = cv.getContext('2d'); cx.fillStyle = '#fff'; cx.fillRect(0, 0, sw, sh);
            cx.drawImage(pic, sx, sy, sw, sh, 0, 0, sw, sh);
            close();
            cv.toBlob(function (blob) {
                if (!blob || st.dead || !img.isConnected) return;
                var local = URL.createObjectURL(blob);
                img.classList.add('is-uploading'); img.src = local;
                img.removeAttribute('data-asset'); // the old picture or the new one, never half of each
                st.uploads++;
                o.toast(T.uploading);
                o.upload(blob, 'picture.jpg').then(function (asset) {
                    img.dataset.asset = asset.uuid;
                    img.classList.remove('is-uploading');
                    img.src = o.picture(asset.uuid);
                }).catch(function (err) {
                    img.dataset.asset = uuid; img.src = o.picture(uuid); img.classList.remove('is-uploading');
                    o.toast((err && err.data && err.data.message) || (err && err.status === 419 ? T.expired : T.bad_image), { error: true });
                }).then(function () {
                    st.uploads--;
                    setTimeout(function () { URL.revokeObjectURL(local); }, 4000);
                    edited(st);
                });
            }, 'image/jpeg', 0.92);
        }
        done.addEventListener('click', finish);
    }

    function sketch(st) {
        if (!st.o.ink || !window.MarginInk) return Promise.resolve(false);
        var saved = st.range;
        return window.MarginInk.open({ T: st.T, mode: 'sketch' }).then(function (r) {
            if (!r || st.dead) return false;
            st.range = saved;
            placeDrawing(st, null, r, { kind: 'sketch', base: null });
            return true;
        });
    }
    function markup(st, img) {
        var T = st.T, uuid = img.dataset.asset, info;
        return st.o.ink.load(uuid).then(function (ink) {
            info = ink;
            return window.MarginInk.open({
                T: T, mode: ink.kind, w: ink.w, h: ink.h, strokes: ink.strokes,
                image: ink.kind === 'markup' ? st.o.picture(ink.base) : null
            });
        }).then(function (r) {
            if (!r || st.dead || !img.isConnected) return;
            placeDrawing(st, img, r, { kind: info.kind, base: info.base });
        }).catch(function (err) {
            st.o.toast((err && err.status === 419) ? T.expired : T.bad_drawing, { error: true });
        });
    }
    function placeDrawing(st, img, r, meta) {
        var T = st.T, o = st.o;
        var local = URL.createObjectURL(r.blob), was = img ? img.dataset.asset : null;
        if (!img) {
            // a new drawing is a block of its own, placed like a recording
            img = h('img', 'is-uploading'); img.alt = '';
            var holder = h('p'); holder.appendChild(img);
            placeBlock(st, holder);
        } else {
            img.classList.add('is-uploading');
        }
        img.src = local;
        img.draggable = false;
        blankCheck(st);
        o.toast(T.ink_saving);
        st.uploads++;
        if (was) img.removeAttribute('data-asset'); // never saved half-way: the old picture or the new one, not both

        o.ink.save(r.blob, { kind: meta.kind, w: r.w, h: r.h, strokes: r.strokes, base: meta.base }).then(function (asset) {
            img.dataset.asset = asset.uuid;
            img.classList.remove('is-uploading');
            img.src = o.picture(asset.uuid);
        }).catch(function (err) {
            if (was) { img.dataset.asset = was; img.src = o.picture(was); img.classList.remove('is-uploading'); }
            else img.remove();
            blankCheck(st);
            o.toast((err && err.data && err.data.message) || (err && err.status === 419 ? T.expired : T.bad_drawing), { error: true });
        }).then(function () {
            st.uploads--;
            setTimeout(function () { URL.revokeObjectURL(local); }, 4000);
            if (o.onEdit) o.onEdit();
        });
    }

    /* Where a block (a recording) goes: straight after the paragraph the caret
       is in — never splitting a word — or before it when the caret sits at its
       very start; at the END of the note when there is no caret to go by. An
       empty paragraph the caret is in is simply replaced. */
    function topBlock(st, node) {
        while (node && node.parentNode !== st.el) node = node.parentNode;
        return node && node.parentNode === st.el ? node : null;
    }
    function placeBlock(st, node) {
        var r = st.range, block = null, before = false;
        if (r && st.el.contains(r.startContainer)) {
            block = r.startContainer === st.el ? st.el.childNodes[Math.min(r.startOffset, st.el.childNodes.length - 1)] : topBlock(st, r.startContainer);
            if (block && block.dataset && block.dataset.ui) block = block.nextElementSibling;
            if (block) {
                var pre = document.createRange();
                pre.setStart(block, 0);
                try { pre.setEnd(r.startContainer, r.startOffset); } catch (e) { pre.setEnd(block, 0); }
                before = !pre.toString().trim() && !!block.textContent.trim();
            }
        }
        if (block && block.nodeType === 1 && !block.textContent.trim() && !block.querySelector('img,.clip,hr') && !/^(UL|OL|TABLE)$/.test(block.tagName)) block.replaceWith(node);
        else if (block && before) block.before(node);
        else if (block) block.after(node);
        else {
            // No caret: the end, in place of a trailing empty line if there is one.
            var last = st.el.lastElementChild;
            if (last && !last.textContent.trim() && !last.querySelector('img,.clip,hr') && last.tagName !== 'TABLE' && !(last.dataset && last.dataset.ui)) last.replaceWith(node); else st.el.appendChild(node);
        }
        if (!node.nextElementSibling) { var p = h('p'); p.appendChild(h('br')); node.after(p); }
        // the caret goes to the line after it, ready to carry on writing
        var after = node.nextElementSibling, sel = window.getSelection(), rr = document.createRange();
        rr.setStart(after, 0); rr.collapse(true);
        st.range = rr;
        if (document.activeElement === st.el) { sel.removeAllRanges(); sel.addRange(rr); }
        blankCheck(st);
    }

    /* ── Moving things: drag a recording by its grip, a picture by its Move
       handle, and drop it between any two paragraphs. A line shows where it
       will land; near the top or bottom edge the page scrolls by itself. ── */
    function scroller(el) {
        while (el && el !== document.body) {
            var oy = getComputedStyle(el).overflowY;
            if ((oy === 'auto' || oy === 'scroll') && el.scrollHeight > el.clientHeight) return el;
            el = el.parentNode;
        }
        return document.scrollingElement;
    }
    function startMove(st, unit, e) {
        if (st.el.contentEditable !== 'true') return;
        e.preventDefault(); e.stopPropagation();
        hideChip(st);
        if (unit._stop) unit._stop(); // another site's player is back to its cover: an iframe reloads when it moves
        var host = st.el.parentNode, sc = scroller(st.el), pid = e.pointerId, target = undefined, raf = 0, lastY = e.clientY, lastX = e.clientX;
        var moving = unit.tagName === 'IMG' ? (topBlock(st, unit) || unit) : unit;
        var ghost = unit.tagName === 'IMG' ? h('img', 'move-ghost') : unit.cloneNode(true);
        if (unit.tagName === 'IMG') { ghost.src = unit.src; ghost.alt = ''; } else ghost.className += ' move-ghost';
        ghost.removeAttribute('data-asset');
        var w = Math.min(unit.getBoundingClientRect().width, unit.tagName === 'IMG' ? 170 : 280);
        ghost.style.width = w + 'px';
        document.body.appendChild(ghost);
        var line = h('div', 'drop-line'); host.appendChild(line);
        moving.classList.add('is-moving');
        buzz(10);

        function blocks() { return Array.prototype.filter.call(st.el.children, function (c) { return c !== moving && !(c.dataset && c.dataset.ui); }); }
        function track(x, y) {
            ghost.style.transform = 'translate(' + (x - w / 2) + 'px,' + (y - 24) + 'px) rotate(-1.5deg)';
            var list = blocks(), hostBox = host.getBoundingClientRect(), edBox = st.el.getBoundingClientRect(), at = null;
            for (var i = 0; i < list.length; i++) { var b = list[i].getBoundingClientRect(); if (b.top + b.height / 2 > y) { at = list[i]; break; } }
            target = at;
            var ref = at ? at.getBoundingClientRect().top - 6 : (list.length ? list[list.length - 1].getBoundingClientRect().bottom + 6 : edBox.top);
            line.style.top = (ref - hostBox.top) + 'px';
            line.style.left = (edBox.left - hostBox.left) + 'px';
            line.style.width = edBox.width + 'px';
        }
        function autoscroll() {
            raf = 0;
            var box = sc === document.scrollingElement ? { top: 0, bottom: window.innerHeight } : sc.getBoundingClientRect();
            var d = lastY < box.top + 70 ? -(box.top + 70 - lastY) : (lastY > box.bottom - 70 ? lastY - (box.bottom - 70) : 0);
            if (d) { sc.scrollTop += Math.max(-18, Math.min(18, d / 3)); track(lastX, lastY); raf = requestAnimationFrame(autoscroll); }
        }
        function move(ev) {
            if (ev.pointerId !== pid) return;
            ev.preventDefault();
            lastX = ev.clientX; lastY = ev.clientY;
            track(lastX, lastY);
            if (!raf) raf = requestAnimationFrame(autoscroll);
        }
        function end(ev, cancel) {
            if (ev && ev.pointerId !== pid) return;
            document.removeEventListener('pointermove', move, true);
            document.removeEventListener('pointerup', up, true);
            document.removeEventListener('pointercancel', cancelled, true);
            if (raf) cancelAnimationFrame(raf);
            ghost.remove(); line.remove(); moving.classList.remove('is-moving');
            if (!moving.getAttribute('class')) moving.removeAttribute('class');
            if (cancel || target === undefined) return;
            var node = moving;
            if (unit.tagName === 'IMG' && moving !== unit && (moving.textContent.trim() || moving.querySelectorAll('img,.clip').length > 1)) {
                // a picture sharing its paragraph with words: it travels alone, in a paragraph of its own
                node = h('p'); node.appendChild(unit);
            }
            var from = node.parentNode;
            if (target) st.el.insertBefore(node, target); else st.el.appendChild(node);
            if (from && from !== st.el && from.parentNode && !from.textContent.trim() && !from.querySelector('img,.clip,hr')) from.remove();
            if (!node.nextElementSibling) { var p = h('p'); p.appendChild(h('br')); node.after(p); }
            buzz(8);
            edited(st);
        }
        function up(ev) { end(ev, false); }
        function cancelled(ev) { end(ev, true); }
        document.addEventListener('pointermove', move, true);
        document.addEventListener('pointerup', up, true);
        document.addEventListener('pointercancel', cancelled, true);
        track(e.clientX, e.clientY);
    }
    function buzz(ms) { if (navigator.vibrate) { try { navigator.vibrate(ms); } catch (e) {} } }

    /* Record with the shared recorder, then put it where the caret was. */
    function record(st, kind) {
        var T = st.T, L = st.o.limits || {};
        var saved = st.range; // where the caret was; the keyboard stays down while recording
        var go = kind === 'audio'
            ? window.MarginRecorder.voice({ T: T, maxSeconds: L.voice })
            : window.MarginRecorder.video({ T: T, maxSeconds: L.video, maxBytes: (L.mb || 10) * 1024 * 1024 });
        return go.then(function (r) {
            if (!r || st.dead) return false;
            st.range = saved;
            insertClip(st, kind, r.blob, r.ms);
            return true;
        }).catch(function (err) {
            var code = err && err.code;
            st.o.toast(code === 'denied' ? (kind === 'audio' ? T.mic_denied : T.cam_denied)
                : code === 'too_big' ? T.rec_too_big.replace(':mb', L.mb || 10) : T.no_recorder, { error: true });
            return false;
        });
    }
    function insertClip(st, kind, blob, ms) {
        var T = st.T, o = st.o;
        var local = URL.createObjectURL(blob);
        var c = clip(st, kind, { status: 'uploading', duration_ms: ms }, local);
        placeBlock(st, c);
        blankCheck(st);
        o.toast(T.uploading_clip);
        st.uploads++;

        var ext = /mp4/.test(blob.type) ? 'mp4' : (/ogg/.test(blob.type) ? 'ogg' : 'webm');
        o.uploadMedia(blob, kind, (kind === 'audio' ? 'voice.' : 'video.') + ext).then(function (asset) {
            c.dataset.asset = asset.uuid;
            c.classList.remove('is-uploading');
            if (kind === 'audio') {
                c._local = null;
                setTimeout(function () { URL.revokeObjectURL(local); }, 4000);
                if (asset.duration_ms) c.querySelector('.clip-time').textContent = clock(asset.duration_ms);
                if (asset.peaks) {
                    var bars = c.querySelectorAll('.clip-wave i');
                    if (bars.length === asset.peaks.length) asset.peaks.forEach(function (p, i) { bars[i].style.height = Math.round(Math.max(0.12, p) * 100) + '%'; });
                    else { var w = c.querySelector('.clip-wave'); w.replaceChildren(); asset.peaks.forEach(function (p) { var b = h('i'); b.style.height = Math.round(Math.max(0.12, p) * 100) + '%'; w.appendChild(b); }); }
                }
            } else {
                showVideo(st, c, asset, local);
            }
        }).catch(function (err) {
            c.remove();
            URL.revokeObjectURL(local);
            blankCheck(st);
            o.toast((err && err.data && err.data.message) || (err && err.status === 419 ? T.expired : T.bad_media), { error: true });
        }).then(function () {
            st.uploads--;
            if (o.onEdit) o.onEdit();
        });
    }

    /* What is sent: pictures still uploading are left out, and a picture
       keeps nothing but its data-asset (the server rebuilds the rest). */
    function serialise(el) {
        var clone = el.cloneNode(true);
        clone.querySelectorAll('[data-ui]').forEach(function (n) { n.remove(); }); // the page's own decorations, never the note
        clone.querySelectorAll('.clip[data-embed]').forEach(function (c) {
            var m = document.createElement('img');
            m.dataset.embed = c.dataset.embed;
            c.replaceWith(m);
        });
        clone.querySelectorAll('.clip').forEach(function (c) {
            if (!c.dataset.asset || c.classList.contains('is-failed')) { c.remove(); return; }
            var m = document.createElement('img');
            m.dataset.asset = c.dataset.asset; m.dataset.kind = c.dataset.kind;
            c.replaceWith(m);
        });
        clone.querySelectorAll('img').forEach(function (img) {
            if (img.dataset.embed) { Array.prototype.slice.call(img.attributes).forEach(function (a) { if (a.name !== 'data-embed') img.removeAttribute(a.name); }); return; }
            if (!img.dataset.asset) { img.remove(); return; }
            ['src', 'class', 'alt', 'draggable'].forEach(function (a) { img.removeAttribute(a); });
            if (img.dataset.kind && img.dataset.kind !== 'audio' && img.dataset.kind !== 'video') img.removeAttribute('data-kind');
        });
        clone.querySelectorAll('table,td,th').forEach(function (n) { n.removeAttribute('class'); });
        return clone.textContent.trim() || clone.querySelector('img,hr,table') ? clone.innerHTML : '';
    }

    /* Saving: quietly, a moment after the typing stops, and again on close.
       A note nobody wrote in is never created. */
    function saver(o) {
        var s = { dirty: false, saving: false, again: false, retryNow: false, timer: null, tries: 0, dead: false };

        function schedule() {
            if (s.dead) return;
            s.dirty = true;
            o.state('saving');
            clearTimeout(s.timer);
            s.timer = setTimeout(save, 900);
        }
        function save() {
            clearTimeout(s.timer);
            if (s.dead || !s.dirty) return;
            if (o.editor.pending()) { s.timer = setTimeout(save, 600); return; }
            if (s.saving) { s.again = true; return; }

            var html = o.editor.serialise();
            if (!o.exists() && !html) { s.dirty = false; o.state('new'); return; }

            s.saving = true; s.dirty = false;
            o.send(html).then(function (r) {
                s.tries = 0;
                o.saved(r);
                if (!s.dirty) o.state('saved', r);
            }).catch(function (err) {
                if (err && err.status === 409 && o.conflict(err)) {
                    s.dirty = true; s.retryNow = true;
                    return;
                }
                s.dirty = true;
                o.state('offline');
                if ([404, 419, 422].indexOf(err && err.status) !== -1 || ++s.tries > 8) { o.fatal(err); return; }
                s.timer = setTimeout(save, Math.min(30000, 2000 * s.tries));
            }).then(function () {
                s.saving = false;
                if (s.again || s.retryNow) { s.again = false; s.retryNow = false; save(); }
            });
        }
        return {
            schedule: schedule,
            save: save,
            kill: function () { s.dead = true; clearTimeout(s.timer); },
            dirty: function () { return s.dirty || s.saving; }
        };
    }

    window.MarginEditor = { create: create, saver: saver, hydrate: hydrate };
    window.MarginEmoji = { panel: emojiGrid }; // the same panel, for any text box (TakeNote's card comments)
})();
</script>
