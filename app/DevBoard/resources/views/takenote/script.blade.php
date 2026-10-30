{{--
    TakeNote's behaviour: the list, the capture bar and the note page. Plain
    JS owning its own DOM, like the board. The editor and its autosave are the
    shared MarginEditor (partials/editor).

    A new note is created on the server the first time there is something to
    keep (a word typed, a picture added) — never on open. A note started and
    left empty is removed again on close, through the guarded `discard` path.
--}}
<script>
(function () {
    'use strict';

    var C = JSON.parse(document.getElementById('tn-config').textContent);
    var T = C.t, U = C.urls;
    var CSRF = document.querySelector('meta[name=csrf-token]').content;
    var ID0 = '00000000-0000-0000-0000-000000000000';
    var KIND = { text: '#7c6bf5', image: '#e89a1c', audio: '#e0457b', video: '#16a394', sketch: '#3b9ae1' };

    var S = { me: null, people: [], lanes: [], notes: [], filter: 'all', q: '', loaded: false, view: 'notes' };
    var REDUCE = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    try { if (localStorage.getItem('takenote.view') === 'board') S.view = 'board'; } catch (e) {}
    var P = null; // the open note
    // The desk (/me/notes, full screen in a browser): the list stays on the left, a note opens in
    // the pane beside it and another note simply replaces it, and the board fills that pane.
    var DESK = !!C.desk, $pane = document.getElementById('pane');

    var $notes = document.getElementById('notes'),
        $toast = document.getElementById('toast'), $capture = document.getElementById('capture');

    /* ── helpers ─────────────────────────────────────────────────────── */
    function h(tag, cls, text) {
        var el = document.createElement(tag);
        if (cls) el.className = cls;
        if (text != null) el.textContent = text;
        return el;
    }
    function icon(name) { return h('i', 'bi ' + name); }
    function t(key, rep) {
        var s = T[key] != null ? T[key] : key;
        Object.keys(rep || {}).forEach(function (k) { s = s.replace(':' + k, rep[k]); });
        return s;
    }
    function url(name, id) { return U[name].replace(ID0, id); }
    function api(method, u, body) {
        return fetch(u, {
            method: method, credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
            body: body ? JSON.stringify(body) : undefined
        }).then(function (res) {
            return res.json().catch(function () { return null; }).then(function (data) {
                if (!res.ok) { var e = new Error('http ' + res.status); e.status = res.status; e.data = data; throw e; }
                return data;
            });
        });
    }
    var toastTimer = null;
    function toast(msg, o) {
        o = o || {};
        clearTimeout(toastTimer);
        $toast.classList.toggle('is-error', !!o.error);
        var parts = [h('span', 'msg', msg)];
        if (o.action) {
            var b = h('button', 'act', o.action); b.type = 'button';
            b.addEventListener('click', function () { hideToast(); o.onAction(); });
            parts.push(b);
        }
        $toast.replaceChildren.apply($toast, parts);
        $toast.classList.add('is-on');
        toastTimer = setTimeout(hideToast, o.action ? 6000 : 2600);
    }
    function hideToast() { $toast.classList.remove('is-on'); }
    function fail(err) {
        toast(err && err.status === 419 ? T.expired : (err && err.status === 404 ? T.gone : T.error), { error: true });
    }
    function ago(iso) {
        if (!iso) return '';
        var d = new Date(iso), s = (Date.now() - d.getTime()) / 1000;
        if (s < 60) return T.now;
        if (s < 3600) return Math.floor(s / 60) + 'm';
        if (new Date().toDateString() === d.toDateString()) return d.toLocaleTimeString(C.locale, { hour: '2-digit', minute: '2-digit' });
        if (s < 6 * 86400) return d.toLocaleDateString(C.locale, { weekday: 'short' });
        return d.toLocaleDateString(C.locale, { day: 'numeric', month: 'short' });
    }
    function avatar(p) {
        var a = h('span', 'av', ((p && p.name) || '?').charAt(0).toUpperCase());
        if (p) { a.style.setProperty('--c', p.ink); a.title = p.id === (S.me && S.me.id) ? T.you : p.name; }
        return a;
    }
    function picUrl(uuid, preview) { return url('picture', uuid) + (preview ? '?size=preview' : ''); }
    function mediaUrl(uuid, poster) { return url('media', uuid) + (poster ? '?poster=1' : ''); }
    function clock(ms) { var s = Math.round((ms || 0) / 1000); return Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0'); }
    function buzz(ms) { if (navigator.vibrate) { try { navigator.vibrate(ms); } catch (e) {} } }

    /* ── the list ────────────────────────────────────────────────────── */
    function load() {
        return api('GET', U.data).then(function (r) {
            S.me = r.me; S.people = r.people; S.lanes = r.lanes || []; S.notes = r.notes; S.cards = r.cards || []; S.folders = r.folders || []; S.labels = r.labels || []; S.loaded = true;
            S.sharing = !!r.sharing; S.shared = r.shared || []; S.space = r.space || null; S.storage = r.storage || null; S.room = r.room || null;
            S.relations = Array.isArray(r.relations) ? r.relations : null; // named connections between notes, when switched on
            drawPeople();
            drawSharing();
            draw();
        }).catch(function (err) {
            if (!S.loaded) {
                var e = h('div', 'empty');
                e.append(h('b', null, T.tn_loading_fail));
                $notes.replaceChildren(e);
            }
            if (err && err.status === 401) location.reload();
        });
    }
    function drawPeople() {
        var box = document.getElementById('people');
        box.replaceChildren.apply(box, S.people.map(avatar));
    }
    function matches(n) {
        if (S.filter === 'mine' && !(n.author && n.author.id === S.me.id)) return false;
        if (S.filter === 'others' && (!n.author || n.author.id === S.me.id)) return false;
        if (S.filter === 'pinned' && !n.pinned) return false;
        if (S.q) {
            var hay = ((n.title || '') + ' ' + (n.excerpt || '')).toLowerCase();
            if (hay.indexOf(S.q) === -1) return false;
        }
        return true;
    }
    function draw() {
        if (!S.loaded) return;
        var list = S.notes.filter(matches).filter(inFolder);
        var cards = (S.cards || []).filter(matches); // the board is cards, never notes
        var board = S.view === 'board', map = S.view === 'map';
        $notes.hidden = (board || map) && !DESK; $board.hidden = !board; $map.hidden = !map;
        document.body.dataset.view = S.view;
        document.getElementById('view-title').textContent = board ? T.tn_view_board : (map ? T.tn_view_map : T.tn_notes);
        drawFolders();
        document.querySelectorAll('#seg button').forEach(function (b) {
            var on = b.dataset.view === S.view;
            b.classList.toggle('is-on', on); b.setAttribute('aria-selected', String(on));
            b.querySelector('.n').textContent = on || b.dataset.view === 'map' ? '' : String((b.dataset.view === 'board' ? (S.cards || []).length : S.notes.length) || '');
        });
        if (board) drawBoard(cards);
        if (map) drawMap();
        if ((board || map) && !DESK) {
            // the board or the map replaces the list
        } else if (!list.length) {
            var e = h('div', 'empty');
            if (S.notes.length) {
                e.append(h('b', null, T.tn_no_match));
            } else {
                e.append(inkSquiggle(), h('b', null, T.tn_empty), h('p', null, T.tn_empty_hint));
            }
            $notes.replaceChildren(e);
        } else {
            $notes.replaceChildren.apply($notes, grouped(list));
        }
        var counts = { all: S.notes.length, mine: 0, others: 0 };
        S.notes.forEach(function (n) { if (n.author && n.author.id === S.me.id) counts.mine++; else counts.others++; });
        counts.pinned = S.notes.filter(function (n) { return n.pinned; }).length;
        document.querySelectorAll('.filter').forEach(function (f) { f.querySelector('.n').textContent = counts[f.dataset.filter] || ''; });
        afterDraw(list);
    }
    function tile(n, i, inBoard) {
        var b = h('button', 'tile'); b.type = 'button';
        b.dataset.uuid = n.uuid;
        if (DESK && !inBoard && P && P.uuid === n.uuid) b.classList.add('is-open');
        if (n.pinned) b.classList.add('is-pinned');
        var lead = KIND[n.lead] ? n.lead : 'text';
        b.style.setProperty('--k', KIND[lead]);
        b.style.animationDelay = Math.min(i, 8) * 30 + 'ms';
        var tx = h('div', 'tx');
        var tt = h('p', 'tt', n.title || T.note_untitled);
        if (n.pinned) tt.prepend(icon('bi-pin-angle-fill'));
        tx.appendChild(tt);
        // second line: when, then what it opens with — the text, or what kind of note it is
        var ex = h('p', 'ex');
        ex.appendChild(h('b', 'age', rowDate(n.updated_at)));
        var first = n.excerpt || '';
        if (lead === 'audio') first = T.tn_voice_note + (n.lead_ms ? ' · ' + clock(n.lead_ms) : '');
        else if (lead === 'video') first = T.tn_video_note + (n.lead_ms ? ' · ' + clock(n.lead_ms) : '');
        else if (!first && lead === 'sketch') first = T.tn_drawing;
        else if (!first && lead === 'image') first = n.pictures === 1 ? T.tn_picture : t('tn_pictures', { n: n.pictures });
        if (first) { var fx = h('span', 'fx', first); ex.appendChild(fx); }
        tx.appendChild(ex);
        if (lead === 'audio') tx.appendChild(waveOf(n.uuid));
        // anything else it holds, as small marks — only when there is something to say
        var mt = h('div', 'mt');
        if (lead !== 'audio' && n.voice) mt.appendChild(mark('bi-mic-fill', n.voice));
        if (lead !== 'video' && n.video) mt.appendChild(mark('bi-camera-video-fill', n.video));
        if (lead !== 'image' && lead !== 'sketch' && n.pictures) mt.appendChild(mark('bi-image', n.pictures));
        if (n.attachments) mt.appendChild(mark('bi-paperclip', n.attachments)); // files attached to the note
        // a note shows its folder when the list is wider than that folder; a card shows its list of the board
        var fo = n.kind === 'card' && !n.in_notebook ? null : folderOf(n.folder);
        if (n.kind === 'card' && n.lane) { var ln = h('span', 'ln'); ln.style.setProperty('--lc', n.lane.color); ln.append(icon('bi-kanban'), document.createTextNode(n.lane.name)); mt.appendChild(ln); }
        if (fo && fo.uuid !== S.folder) { var fl = h('span', 'ln'); fl.style.setProperty('--lc', fo.color); fl.append(icon('bi-folder-fill'), document.createTextNode(fo.name)); mt.appendChild(fl); }
        if (n.assignee) { var wh = h('span', 'who', t('tn_for', { name: n.assignee.id === (S.me && S.me.id) ? T.tn_me : n.assignee.name })); mt.appendChild(wh); }
        if (n.due_on) { var dl = dueLabel(n.due_on), du = h('span', 'due ' + dl.cls); du.append(icon('bi-calendar3'), document.createTextNode(' ' + dl.text)); mt.appendChild(du); }
        if (n.replies) { var rp = h('span', 'k'); rp.append(icon('bi-chat'), document.createTextNode(String(n.replies))); mt.appendChild(rp); }
        if (mt.childNodes.length) tx.appendChild(mt);
        b.appendChild(tx);
        if (n.thumb || n.poster) {
            var wrap = h('span', 'th-wrap');
            var im = h('img', 'th'); im.alt = ''; im.loading = 'lazy'; im.draggable = false;
            im.src = n.thumb ? picUrl(n.thumb, true) : mediaUrl(n.poster, true);
            wrap.appendChild(im);
            if (!n.thumb) wrap.appendChild(icon('bi-play-fill'));
            b.appendChild(wrap);
        } else {
            // somebody else's note says whose it is; your own does not need to
            var who = n.editor || n.author;
            if (who && S.me && who.id !== S.me.id) { var av = avatar(who); av.classList.add('row-av'); b.appendChild(av); }
        }
        b.addEventListener('click', function () { if (Date.now() - justDragged < 350 || Date.now() - justSwiped < 350) return; openNote(n.uuid); });
        return b;
    }
    function mark(ic, n) {
        var m = h('span', 'k'); m.style.color = 'var(--ink-soft)';
        m.append(icon(ic), document.createTextNode(n > 1 ? String(n) : ''));
        return m;
    }
    /* A voice note's tile carries a waveform: its shape is decoration, seeded by the note so it never jumps. */
    function waveOf(seed) {
        var w = h('span', 'wave'), x = 0;
        for (var i = 0; i < seed.length; i++) x = (x * 31 + seed.charCodeAt(i)) % 233280;
        for (var j = 0; j < 34; j++) {
            x = (x * 9301 + 49297) % 233280;
            var bar = h('i'); bar.style.height = Math.round(4 + (x / 233280) * 16 * (0.45 + 0.55 * Math.sin(j / 34 * Math.PI))) + 'px';
            w.appendChild(bar);
        }
        w.setAttribute('aria-hidden', 'true');
        return w;
    }
    function inkSquiggle() {
        var ns = 'http://www.w3.org/2000/svg', svg = document.createElementNS(ns, 'svg');
        svg.setAttribute('viewBox', '0 0 120 46'); svg.setAttribute('class', 'ink'); svg.setAttribute('aria-hidden', 'true');
        var p = document.createElementNS(ns, 'path');
        p.setAttribute('d', 'M4 30 C 16 8, 26 40, 40 22 S 62 6, 72 24 S 96 38, 116 14');
        p.setAttribute('fill', 'none'); p.setAttribute('stroke', 'currentColor'); p.setAttribute('stroke-width', '3'); p.setAttribute('stroke-linecap', 'round');
        svg.appendChild(p);
        return svg;
    }
    /* A row goes to the list of what it is: notes to the notebook, cards to the board — and a note put on the board to both. */
    function upsert(note, inPlace) {
        S.cards = S.cards || [];
        if (note.kind === 'card') {
            var ci = S.cards.findIndex(function (n) { return n.uuid === note.uuid; });
            if (ci >= 0) S.cards[ci] = note; else S.cards.push(note);
            var ni = S.notes.findIndex(function (n) { return n.uuid === note.uuid; });
            if (!note.in_notebook) S.notes = S.notes.filter(function (n) { return n.uuid !== note.uuid; });
            else if (ni >= 0) S.notes[ni] = note; // it keeps its place in the notebook
            else S.notes.unshift(note);
            draw(); return;
        }
        S.cards = S.cards.filter(function (n) { return n.uuid !== note.uuid; });
        var at = S.notes.findIndex(function (n) { return n.uuid === note.uuid; });
        if (inPlace && at >= 0) { S.notes[at] = note; draw(); return; } // a change that keeps its place in the list
        S.notes = S.notes.filter(function (n) { return n.uuid !== note.uuid; });
        S.notes.unshift(note);
        draw();
    }
    function drop(uuid) {
        S.notes = S.notes.filter(function (n) { return n.uuid !== uuid; });
        S.cards = (S.cards || []).filter(function (n) { return n.uuid !== uuid; });
        draw();
    }

    document.getElementById('seg').addEventListener('click', function (e) {
        var b = e.target.closest('button[data-view]');
        if (!b || b.dataset.view === S.view) return;
        S.view = b.dataset.view;
        try { localStorage.setItem('takenote.view', S.view); } catch (er) {}
        window.scrollTo({ top: 0 });
        draw();
    });
    document.getElementById('filters').addEventListener('click', function (e) {
        var f = e.target.closest('.filter');
        if (!f) return;
        S.filter = f.dataset.filter;
        document.querySelectorAll('.filter').forEach(function (x) { x.classList.toggle('is-on', x === f); });
        if (S.filter !== 'all') { S.folder = 'all'; try { localStorage.setItem('takenote.folder', 'all'); } catch (er) {} }
        if (S.view !== 'notes') { S.view = 'notes'; try { localStorage.setItem('takenote.view', 'notes'); } catch (er) {} }
        draw();
    });
    var $search = document.getElementById('search'), $q = document.getElementById('search-input'), $st = document.getElementById('search-toggle');
    if ($st) $st.addEventListener('click', function () {
        var open = $search.hidden;
        $search.hidden = !open;
        $st.classList.toggle('is-on', open);
        $st.setAttribute('aria-expanded', String(open));
        if (open) $q.focus(); else { $q.value = ''; S.q = ''; draw(); }
    });
    $q.addEventListener('input', function () { S.q = $q.value.trim().toLowerCase(); draw(); });

    /* ── the note page: the shared MarginPage (reading first, tap to write) ── */
    function markOpen() {
        if (!DESK) return;
        $notes.querySelectorAll('.tile').forEach(function (b) { b.classList.toggle('is-open', !!(P && P.uuid && P.uuid === b.dataset.uuid)); });
        document.body.classList.toggle('has-note', !!P);
    }
    function openNote(uuid, o) {
        o = o || {};
        if (P && DESK) {
            if (uuid && P.uuid === uuid) return;
            P.page.close(); // on the desk another note simply takes its place (it saves on closing)
        }
        if (P) return;
        var summary = uuid ? find(uuid) : null;
        var st = P = { uuid: uuid || null, version: 0, created: false, creating: null, editor: null, saver: null };
        var fresh = !uuid;
        // From the board (or an "Open card") a card opens as Trello's card back, a window on a desk; from the
        // notes it is a note like any other — its words, its files — even once it has become a card (28 Oct 2026).
        var isCard = !fresh && !!o.card;
        var parts = st.parts = isCard ? cardParts(st) : null;
        var linkBox = isCard ? null : linksBox(st);
        // a NOTE takes files too (any kind the server accepts): its attachments sit under the text, above its links
        var noteFiles = isCard ? null : cardParts(st, { filesOnly: true });
        if (noteFiles) { st.parts = noteFiles; noteFiles.set({ attachment_list: [] }); }
        var noteBelow = isCard ? null : h('div', 'note-below');
        if (noteBelow) noteBelow.append(noteFiles.below, linkBox.el);
        st.isCard = isCard; st.linkBox = linkBox;

        st.page = MarginPage.open({
            T: T, locale: C.locale, fresh: fresh, host: DESK && !isCard ? $pane : null, titleField: true,
            window: isCard, cover: isCard ? coverUrl(summary) : null, coverColor: isCard && summary ? summary.cover_color : null, crumbEl: isCard ? listPill(st, summary) : null,
            chips: parts ? parts.chips : null, below: parts ? parts.below : noteBelow, aside: parts ? parts.aside : null,
            onLink: function (href) { var m = /\/me\/takenote\?note=([0-9a-f-]{36})/.exec(href || ''); if (!m) return false; openLinked(m[1]); return true; },
            crumb: 'TakeNote',
            tapToWrite: isCard, // a note opens to read; the pen (top right) starts writing (28 Oct 2026)
            meta: summary ? metaOf(summary) : {},
            editor: {
                html: fresh ? (o.picture || o.record || o.draw ? '' : '<h1><br></h1>') : '', // a written note opens on its title line
                T: T, placeholder: T.note_placeholder, imagePx: C.imagePx, toast: toast,
                picture: picUrl,
                isFull: isFull,
                upload: function (blob, name) { return ensure(st).then(function () { return uploadPicture(st.uuid, blob, name); }); },
                uploadMedia: function (blob, kind, name) { return ensure(st).then(function () { return uploadClip(st.uuid, blob, kind, name); }); },
                mediaUrl: mediaUrl,
                mediaStatus: function (u) { return api('GET', url('mediaStatus', u)).then(function (r) { return r.asset; }); },
                limits: C.limits,
                markdown: function (text) { return api('POST', U.markdown, { text: text }).then(function (r) { return r.html; }); },
                videoLink: function (u) { return api('GET', U.videoLink + '?url=' + encodeURIComponent(u)).then(function (r) { return r.embed; }); },
                noteLinks: {
                    search: function (q) {
                        q = q.toLowerCase();
                        return S.notes.concat(S.cards || []).filter(function (n) { return n.uuid !== st.uuid && (n.title || '').toLowerCase().indexOf(q) !== -1; })
                            .map(function (n) { return { uuid: n.uuid, title: n.title || T.note_untitled }; });
                    },
                    href: function (u) { return U.app + '?note=' + u; }
                },
                ink: {
                    save: function (blob, meta) { return ensure(st).then(function () { return uploadInk(st.uuid, blob, meta); }); },
                    load: function (u) { return api('GET', url('inkShow', u)).then(function (r) { return r.ink; }); }
                }
            },
            load: fresh ? null : isCard ? function () {
                return api('GET', url('cardShow', uuid)).then(function (r) {
                    st.version = r.card.version;
                    parts.set(r.card);
                    return { html: r.card.html, media: r.card.media, meta: metaOf(r.card) };
                });
            } : function () {
                return api('GET', url('show', uuid)).then(function (r) {
                    st.version = r.note.version;
                    noteFiles.set({ attachment_list: r.note.attachment_list || [] });
                    return { html: r.note.html, media: r.note.media, meta: metaOf(r.note) };
                });
            },
            saver: {
                exists: function () { return !!st.uuid || !!st.creating; },
                send: function (html) { return send(st, html); },
                saved: function (r) { upsert(summaryOf(r.note)); if (P === st) { st.page.setMeta(metaOf(r.note)); foot(st); if (linkBox) linkBox.paint(); } },
                conflict: function (err) {
                    if (!err.data || !err.data.note) return false;
                    // Someone else saved this note first: keep theirs AND this one, as a new note.
                    upsert(summaryOf(err.data.note));
                    st.uuid = null; st.version = 0; st.creating = null;
                    toast(T.note_conflict, { error: true });
                    return true;
                },
                fatal: fail
            },
            onLane: function () { var n = current(st); if (!n) return; if (isCard) sendToBoard(st); else moveNoteSheet(n, function (m) { if (P === st) st.page.setMeta(metaOf(m)); }); },
            menu: isCard ? [
                { icon: 'bi-arrow-right', label: T.ct_move, onClick: function () { if (st.uuid) movePop(null, st.uuid); } },
                { icon: 'bi-copy', label: T.ct_copy, onClick: function () { if (st.uuid) copyPop(null, st.uuid); } },
                { icon: 'bi-share', label: T.ct_share, onClick: function () { if (st.uuid) sharePop(null, st.uuid); } },
                { icon: 'bi-card-heading', label: T.ct_template_toggle, onClick: function () { if (st.uuid) setTemplate(st.uuid); } },
                { icon: 'bi-archive', label: T.ct_archive, onClick: function () { if (st.uuid) archiveCard(st.uuid, true); } },
                { icon: 'bi-journal-text', label: T.cb_make_note, onClick: function () { convertToNote(st); } },
                { icon: 'bi-trash3', label: T.cb_delete_card, danger: true, onClick: function () { binNote(st); } }
            ] : [
                { icon: 'bi-paperclip', label: T.cb_attach_file, onClick: function () { noteFiles.attach('file'); } },
                { icon: 'bi-pin-angle', label: summary && summary.pinned ? T.nb_unpin : T.nb_pin, onClick: function () { if (st.uuid) pinNote(st.uuid); } },
                relOn() ? { icon: 'bi-bezier2', label: T.rel_connect_to, onClick: function () { if (st.uuid) pickNoteSheet(st.uuid); } } : null,
                summary && summary.kind === 'card'
                    ? { icon: 'bi-window', label: T.tn_open_the_card, onClick: function () { openTheCard(st); } }
                    : { icon: 'bi-kanban', label: T.tn_make_card, onClick: function () { sendToBoard(st); } },
                { icon: 'bi-diagram-2', label: T.nm_show, onClick: function () { var u = st.uuid; if (!u) return; st.page.close(); setTimeout(function () { showOnMap(u); }, DESK ? 0 : 380); } },
                { icon: 'bi-trash3', label: T.note_delete, danger: true, onClick: function () { binNote(st); } }
            ].filter(Boolean),
            after: function (page) {
                st.page = page; // after() runs inside open(), before open() has returned
                st.editor = page.editor; st.saver = page.saver;
                foot(st);
                dropFiles(st, page); // files dropped on a card or a note are attached to it
                if (linkBox) linkBox.paint();
                if (!fresh) return;
                // What the capture button asked for comes first; giving up on it leaves nothing behind.
                var gaveUp = function (kept) { if (!kept && P === st && !st.uuid && !st.creating) st.page.close(); };
                if (o.picture) page.editor.pickPicture();
                else if (o.draw) page.editor.draw().then(gaveUp);
                else if (o.record) page.editor.record(o.record).then(gaveUp);
                else setTimeout(function () { if (P === st && !page.focusTitle()) page.editor.focus(true); }, 380);
            },
            onClose: function () { closeNote(st); }
        });

        document.body.classList.add('is-writing');
        $capture.classList.add('is-away');
        markOpen();
    }
    /* "Send to the board": the sheet, from the lane chip or the bar at the foot of a note still in the first lane. */
    function find(uuid) { return S.notes.concat(S.cards || []).find(function (n) { return n.uuid === uuid; }) || null; }
    function current(st) { return st.uuid ? find(st.uuid) : null; }
    /* "Make it a card": the note leaves the notebook for a list on the board (and takes a person, date, label on the way). */
    function sendToBoard(st) {
        var n = current(st);
        if (!n) return;
        openSend(n, { convert: true, saved: function () { if (P === st) st.page.close(); } });
    }
    /* A link to another note opens that note here (it was made with "Link to a note"). */
    function openLinked(uuid) {
        if (!find(uuid)) { toast(T.tn_loading_fail, { error: true }); return; }
        if (P) { P.page.close(); setTimeout(function () { openNote(uuid); }, DESK ? 0 : 380); }
        else openNote(uuid);
    }
    /* Under a note: the notes that link here, and the ones it links to — each a door to it. */
    function linksBox(st) {
        var el = h('div', 'nl-box');
        function pills(list) {
            var row = h('div', 'nl-row');
            list.forEach(function (n) {
                var b = h('button', 'nl-pill'); b.type = 'button';
                var dot = h('i'); var f = n.kind === 'card' ? null : folderOf(n.folder);
                dot.style.background = n.kind === 'card' ? (n.lane ? n.lane.color : '#7c6bf5') : (f ? f.color : '#a78bfa');
                b.append(dot, document.createTextNode(n.title || T.note_untitled));
                if (n.kind === 'card') b.appendChild(icon('bi-kanban'));
                b.addEventListener('click', function () { openLinked(n.uuid); });
                row.appendChild(b);
            });
            return row;
        }
        return {
            el: el,
            paint: function () {
                var me = current(st); if (!me) { el.replaceChildren(); return; }
                var all = S.notes.concat(S.cards || []);
                var from = all.filter(function (n) { return (n.links || []).indexOf(me.uuid) !== -1; });
                var to = (me.links || []).map(function (u) { return all.find(function (n) { return n.uuid === u; }); }).filter(Boolean);
                var parts = [];
                if (from.length) parts.push(h('h4', null, T.nl_from + ' · ' + from.length), pills(from));
                if (to.length) parts.push(h('h4', null, T.nl_to + ' · ' + to.length), pills(to));
                parts = parts.concat(relRow(st)); // named connections, and "Connect to a note…"
                el.replaceChildren.apply(el, parts);
            }
        };
    }
    /* A card's list, at the top of the open card: tap it to move the card (and plan it). */
    function listPill(st, n) {
        var b = h('button', 'cb-list'); b.type = 'button';
        var paint = function (m) { var dot = h('i'); dot.style.background = m && m.lane ? m.lane.color : '#7c6bf5'; b.replaceChildren(dot, document.createTextNode(m && m.lane ? m.lane.name : ''), icon('bi-chevron-down')); };
        paint(n);
        b.addEventListener('click', function () { var m = current(st); if (m) openSend(m, { quick: true, saved: function (note) { paint(note); if (st.page) st.page.setMeta({}); } }); });
        return b;
    }
    /* Files dropped anywhere on an open card are attached to it. */
    function dropFiles(st, page) {
        var el = page.el;
        el.addEventListener('dragover', function (e) { if (e.dataTransfer && Array.prototype.indexOf.call(e.dataTransfer.types || [], 'Files') !== -1) { e.preventDefault(); el.classList.add('is-dropping'); } });
        el.addEventListener('dragleave', function (e) { if (e.target === el || !el.contains(e.relatedTarget)) el.classList.remove('is-dropping'); });
        el.addEventListener('drop', function (e) {
            el.classList.remove('is-dropping');
            var files = e.dataTransfer && e.dataTransfer.files;
            if (!files || !files.length || e.target.closest('.ed[contenteditable=true]')) return; // into the text while writing: the editor's own drop
            e.preventDefault();
            st.parts.drop(Array.prototype.slice.call(files));
        });
    }
    function foot(st) {
        // a card: on a phone its comment box rides at the foot of the screen; on a desk it lives in the side column
        if (st.isCard) { st.page.setFooter(window.matchMedia('(min-width: 900px)').matches ? null : st.parts.composer); return; }
        // a note: "Make it a card" / "Open the card" live in the ⋯ menu, not across the foot of the page (28 Oct 2026)
        st.page.setFooter(null);
    }
    /* A note already on the board: the way to its card back. */
    function openTheCard(st) {
        var u = st.uuid; if (!u) return;
        st.page.close(); setTimeout(function () { openNote(u, { card: true }); }, DESK ? 0 : 380);
    }
    function metaOf(n) {
        // a card shows its list; a note shows its folder (tap it to move the note) — a note is not on the board
        var f = n.kind === 'card' ? null : folderOf(n.folder);
        var lane = n.kind === 'card' ? (n.lane || null) : (f ? { name: f.name, color: f.color } : { name: T.fo_none, color: '#8a8199' });
        return { lane: lane, who: n.editor || n.author || null, when: n.updated_at || null };
    }
    function summaryOf(n) {
        var s = Object.assign({}, n);
        delete s.html; delete s.version; delete s.media;
        return s;
    }

    /* The note exists on the server once there is something to keep. One
       create, however many saves and uploads ask for it at the same time. */
    function ensure(st) {
        if (st.uuid) return Promise.resolve();
        if (!st.creating) {
            st.creating = api('POST', U.store, { html: st.editor.serialise() }).then(function (r) {
                st.uuid = r.note.uuid; st.version = r.note.version; st.created = true;
                upsert(summaryOf(r.note));
                return r;
            });
            st.creating.catch(function () { st.creating = null; });
        }
        return st.creating;
    }
    function send(st, html) {
        if (!st.uuid && !st.creating) return ensure(st);
        return ensure(st).then(function () {
            return api('PATCH', url('update', st.uuid), { version: st.version, html: html });
        }).then(function (r) { st.version = r.note.version; return r; });
    }
    /* The notebook's space (1 GB): checked BEFORE anything is uploaded or recorded — no room, nothing starts —
       and what goes in is counted at once. The server refuses the same way; this saves the wasted upload. */
    function roomLeft() { return S.room ? Math.max(0, S.room.limit - S.room.used) : Infinity; }
    function isFull() { return roomLeft() < 16384; }
    function fullError() { var e = new Error('full'); e.status = 422; e.data = { message: T.st_full }; return e; }
    function spent(n) {
        if (S.room) S.room.used += n || 0;
        if (S.storage && !S.space && S.room) S.storage.used = S.room.used;
        drawSharing();
    }
    function counted(n) { return function (asset) { spent(n); return asset; }; }

    function uploadPicture(cardUuid, blob, name) {
        if (roomLeft() < blob.size) return Promise.reject(fullError());
        var fd = new FormData();
        fd.append('image', blob, name);
        return fetch(url('pictureStore', cardUuid), {
            method: 'POST', credentials: 'same-origin', body: fd,
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (res) {
            return res.json().catch(function () { return null; }).then(function (d) {
                if (!res.ok || !d || !d.asset) { var e = new Error('upload'); e.status = res.status; e.data = d; throw e; }
                return d.asset;
            });
        }).then(counted(blob.size));
    }

    function uploadClip(cardUuid, blob, kind, name) {
        if (roomLeft() < blob.size) return Promise.reject(fullError());
        var fd = new FormData();
        fd.append('kind', kind);
        fd.append('media', blob, name);
        return fetch(url('mediaStore', cardUuid), {
            method: 'POST', credentials: 'same-origin', body: fd,
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (res) {
            return res.json().catch(function () { return null; }).then(function (d) {
                if (!res.ok || !d || !d.asset) { var e = new Error('upload'); e.status = res.status; e.data = d; throw e; }
                return d.asset;
            });
        }).then(counted(blob.size));
    }

    function uploadInk(cardUuid, blob, meta) {
        if (roomLeft() < blob.size) return Promise.reject(fullError());
        var fd = new FormData();
        fd.append('image', blob, blob.type === 'image/png' ? 'drawing.png' : 'drawing.jpg');
        fd.append('kind', meta.kind); fd.append('w', meta.w); fd.append('h', meta.h);
        fd.append('strokes', JSON.stringify(meta.strokes));
        if (meta.base) fd.append('base', meta.base);
        return fetch(url('inkStore', cardUuid), {
            method: 'POST', credentials: 'same-origin', body: fd,
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (res) {
            return res.json().catch(function () { return null; }).then(function (d) {
                if (!res.ok || !d || !d.asset) { var e = new Error('upload'); e.status = res.status; e.data = d; throw e; }
                return d.asset;
            });
        }).then(counted(blob.size));
    }

    function closeNote(st) {
        if (P !== st) return;
        P = null;
        if (st.saver) {
            // Started here and left empty: take it away again, quietly.
            var settle = function () {
                if (st.saver.dirty() || st.editor.pending()) { setTimeout(settle, 500); return; }
                if (st.created && st.uuid && !st.binned && !st.editor.serialise()) {
                    drop(st.uuid);
                    api('DELETE', url('destroy', st.uuid) + '?discard=1').catch(function () { load(); });
                }
            };
            if (st.created || st.creating) setTimeout(settle, 300);
        }
        document.body.classList.remove('is-writing');
        $capture.classList.remove('is-away');
        markOpen();
    }
    function binNote(st) {
        st.binned = true;
        if (st.saver) st.saver.kill();
        var uuid = st.uuid;
        st.page.close();
        if (!uuid) return;
        drop(uuid);
        api('DELETE', url('destroy', uuid)).then(function () {
            toast(T.note_binned, { action: T.undo, onAction: function () {
                api('POST', url('restore', uuid)).then(function (r) { upsert(summaryOf(r.note)); toast(T.restored); }).catch(fail);
            } });
        }).catch(function (err) { load(); fail(err); });
    }

@include('devboard::takenote.board')
@include('devboard::takenote.fonts')
@include('devboard::takenote.cardback')
@include('devboard::takenote.cardtools')
@include('devboard::takenote.folders')
@include('devboard::takenote.map')
@include('devboard::takenote.layout')
@include('devboard::takenote.friends')
@include('devboard::takenote.sharing')
@include('devboard::takenote.relations')

    /* ── capture ─────────────────────────────────────────────────────── */
    document.getElementById('cap-write').addEventListener('click', function () { buzz(8); openNote(null); });
    document.getElementById('cap-photo').addEventListener('click', function () { buzz(8); openNote(null, { picture: true }); });
    document.getElementById('cap-voice').addEventListener('click', function () { buzz(8); openNote(null, { record: 'audio' }); });
    document.getElementById('cap-video').addEventListener('click', function () { buzz(8); openNote(null, { record: 'video' }); });
    document.getElementById('cap-draw').addEventListener('click', function () { buzz(8); openNote(null, { draw: true }); });

    // Coming back to the app shows what changed (the page saves itself on leaving).
    document.addEventListener('visibilitychange', function () { if (!document.hidden && !P) load(); });
    setInterval(function () { if (!document.hidden && !P) load(); }, 60000);

    /* ── install ─────────────────────────────────────────────────────── */
    var standalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
    var dismissed = false;
    try { dismissed = localStorage.getItem('takenote.install.dismissed') === '1'; } catch (e) {}
    var $install = document.getElementById('install'), deferred = null;
    if ($install && !standalone && !dismissed) {
        window.addEventListener('beforeinstallprompt', function (e) {
            e.preventDefault(); deferred = e;
            $install.hidden = false;
        });
        if (/iphone|ipad|ipod/i.test(navigator.userAgent)) {
            document.getElementById('install-text').textContent = T.tn_install_ios;
            document.getElementById('install-go').hidden = true;
            $install.hidden = false;
        }
    }
    if ($install) document.getElementById('install-go').addEventListener('click', function () {
        if (!deferred) return;
        deferred.prompt();
        deferred.userChoice.finally(function () { deferred = null; $install.hidden = true; });
    });
    if ($install) document.getElementById('install-x').addEventListener('click', function () {
        $install.hidden = true;
        try { localStorage.setItem('takenote.install.dismissed', '1'); } catch (e) {}
    });
    if (!DESK && 'serviceWorker' in navigator) {
        navigator.serviceWorker.register(U.worker, { scope: U.scope }).catch(function () {});
    }

    /* ── start ───────────────────────────────────────────────────────── */
    var now = new Date(); // "Tue 27 Oct" — day before month, no comma
    document.getElementById('today').textContent = now.toLocaleDateString(C.locale, { weekday: 'short' }) + ' ' + now.toLocaleDateString(C.locale, { day: 'numeric', month: 'short' });
    if (location.hash === '#note') history.replaceState(null, '', location.pathname + location.search);
    load().then(function () {
        var p = new URLSearchParams(location.search);
        if (p.get('new') === 'write') { history.replaceState(null, '', location.pathname); openNote(null); }
        var linked = /^[0-9a-f-]{36}$/.test(p.get('note') || '') ? p.get('note') : null; // a note link followed from anywhere
        if (linked) { history.replaceState(null, '', location.pathname); if (find(linked)) openNote(linked); }
        // a card's own link (Share, Copy link, a reminder or a mention): open it, even an archived one
        var card = /^[0-9a-f-]{36}$/.test(p.get('card') || '') ? p.get('card') : null;
        if (card) { history.replaceState(null, '', location.pathname); openNote(card, { card: true }); }
    });
})();
</script>
