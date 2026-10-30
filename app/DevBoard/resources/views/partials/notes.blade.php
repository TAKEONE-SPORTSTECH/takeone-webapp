{{--
    Margin's notes on the board — included INSIDE the board script's closure,
    so it shares its helpers (h, icon, t, api, toast, band, openSheet, merge…).

    The editor and its autosave are the shared MarginEditor
    (partials/editor, included before the board script); this file is the
    board's half: the card sheet's note tiles, the note sheet around the
    editor, and where its saves go.
--}}
    /* ── notes ───────────────────────────────────────────────────────── */
    var NOTE0 = 'ffffffff-ffff-ffff-ffff-ffffffffffff';
    var $noteSheet = document.getElementById('note-sheet');
    var E = null; // the open note's editing state
    S.notes = new Map(); // card uuid → its notes, in order (undefined while loading)

    function nurl(name, card, note) { return U[name].replace('__ID__', card).replace(NOTE0, note || NOTE0); }
    function picUrl(uuid, preview) { return U.picture.replace('__ID__', uuid) + (preview ? '?size=preview' : ''); }
    function person(p) { return p ? (p.id === S.me.id ? T.you : p.name) : ''; }

    function loadNotes(cardUuid) {
        return api('GET', nurl('notes', cardUuid)).then(function (r) {
            S.notes.set(cardUuid, r.notes);
            refreshNotes(cardUuid);
        }).catch(function (err) { if (S.open === cardUuid) fail(err); });
    }
    function upsertNote(cardUuid, note) {
        var list = (S.notes.get(cardUuid) || []).filter(function (n) { return n.uuid !== note.uuid; });
        list.push(note);
        list.sort(function (a, b) { return a.position - b.position; });
        S.notes.set(cardUuid, list);
        refreshNotes(cardUuid);
    }
    function dropNote(cardUuid, uuid) {
        S.notes.set(cardUuid, (S.notes.get(cardUuid) || []).filter(function (n) { return n.uuid !== uuid; }));
        refreshNotes(cardUuid);
    }
    function refreshNotes(cardUuid) {
        var box = $cardSheet.querySelector('.notes-box');
        if (box && box.dataset.card === cardUuid && !document.body.classList.contains('is-sorting-notes')) fillNotes(box, cardUuid);
    }

    /* The card sheet's Notes section */
    function notesSection(c) {
        var box = h('section', 'notes-box');
        box.dataset.card = c.uuid;
        fillNotes(box, c.uuid);
        return box;
    }
    function fillNotes(box, cardUuid) {
        var list = S.notes.get(cardUuid);
        var head = h('div', 'notes-head');
        var add = h('button', 'note-add'); add.type = 'button';
        add.append(icon('bi-plus-lg'), document.createTextNode(T.new_note));
        add.addEventListener('click', function () { openNote(cardUuid, null); });
        head.append(h('span', 'field-label', T.notes + (list && list.length ? ' · ' + list.length : '')), add);

        var wrap = h('div', 'notes');
        box.replaceChildren(head, wrap);

        if (!list) { wrap.appendChild(h('div', 'notes-loading')); return; }
        if (!list.length) {
            var e = h('button', 'notes-empty'); e.type = 'button';
            e.append(icon('bi-pencil'), h('span', null, T.notes_empty));
            e.addEventListener('click', function () { openNote(cardUuid, null); });
            wrap.appendChild(e);
            return;
        }
        list.forEach(function (n) { wrap.appendChild(noteTile(cardUuid, n)); });

        Sortable.create(wrap, {
            draggable: '.note-tile', forceFallback: true, fallbackOnBody: true, fallbackTolerance: 4,
            delay: 170, delayOnTouchOnly: true, touchStartThreshold: 6,
            animation: REDUCE ? 0 : 200, easing: 'cubic-bezier(.2,.8,.2,1)',
            ghostClass: 'is-ghost', fallbackClass: 'is-lifted',
            onChoose: function () { buzz(8); },
            onStart: function () { document.body.classList.add('is-sorting-notes'); },
            onEnd: function (e) {
                document.body.classList.remove('is-sorting-notes');
                S.justDragged = Date.now();
                if (e.oldIndex !== e.newIndex) moveNote(cardUuid, e.item);
            }
        });
    }
    function noteTile(cardUuid, n) {
        var tile = h('div', 'note-tile');
        tile.tabIndex = 0; tile.setAttribute('role', 'button');
        tile.dataset.uuid = n.uuid;
        tile.style.setProperty('--rule', (n.author && n.author.ink) || S.me.ink);

        var txt = h('div', 'nt-text');
        var title = h('p', 'nt-title', n.title || T.note_untitled);
        title.classList.toggle('is-blank', !n.title);
        txt.appendChild(title);
        if (n.excerpt) txt.appendChild(h('p', 'nt-excerpt', n.excerpt));

        var meta = h('div', 'nt-meta');
        var who = n.editor || n.author;
        if (who) {
            var g = h('span', 'sig');
            g.style.setProperty('--ink-c', who.ink);
            g.append(sigSvg(who.ink), document.createTextNode(person(who)));
            meta.appendChild(g);
        }
        if (n.assets.length) meta.appendChild(count('bi-image', n.assets.length));
        meta.appendChild(h('span', 'm age', ago(n.updated_at)));
        txt.appendChild(meta);
        tile.appendChild(txt);

        if (n.assets.length) {
            var im = h('img', 'nt-thumb');
            im.alt = ''; im.loading = 'lazy'; im.draggable = false;
            im.src = picUrl(n.assets[0], true);
            tile.appendChild(im);
        }
        tile.addEventListener('click', function () {
            if (Date.now() - S.justDragged < 350) return;
            openNote(cardUuid, n.uuid);
        });
        tile.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openNote(cardUuid, n.uuid); }
        });
        return tile;
    }
    function moveNote(cardUuid, tile) {
        var list = S.notes.get(cardUuid) || [];
        var byId = function (el) { return el && list.find(function (n) { return n.uuid === el.dataset.uuid; }); };
        var note = byId(tile), above = byId(tile.previousElementSibling), below = byId(tile.nextElementSibling);
        if (!note) return;
        note.position = between(above && above.position, below && below.position);
        list.sort(function (a, b) { return a.position - b.position; });
        api('POST', nurl('noteMove', cardUuid, note.uuid), { above: above ? above.uuid : null, below: below ? below.uuid : null })
            .then(function (r) { upsertNote(cardUuid, r.note); })
            .catch(function (err) { loadNotes(cardUuid); fail(err); });
    }


    /* ── the note itself: the shared MarginPage (reading first, tap to write) ── */
    function openNote(cardUuid, noteUuid) {
        var c = S.cards.get(cardUuid);
        if (!c || E) return;
        var lane = laneOf(c.lane) || null;
        var n = noteUuid ? (S.notes.get(cardUuid) || []).find(function (x) { return x.uuid === noteUuid; }) : null;

        var st = E = { card: cardUuid, note: n ? n.uuid : null, version: n ? n.version : 0 };
        var metaOf = function (note) {
            var who = note && (note.editor || note.author);
            return { lane: lane ? { name: lane.name, color: lane.color } : null, who: who ? { name: person(who), ink: who.ink } : null, when: note && note.updated_at };
        };

        st.page = MarginPage.open({
            T: T, locale: C.locale, fresh: !n,
            crumb: T.title + ' · ' + (lane ? lane.name + ' · ' : '') + c.title,
            meta: metaOf(n),
            editor: {
                html: n && n.html, T: T, placeholder: T.note_placeholder,
                imagePx: C.imagePx, toast: toast,
                picture: picUrl,
                upload: function (blob, name) { return uploadPicture(st.card, blob, name); },
                uploadMedia: function (blob, kind, name) { return uploadClip(st.card, blob, kind, name); },
                mediaUrl: function (uuid, poster) { return U.media.replace('__ID__', uuid) + (poster ? '?poster=1' : ''); },
                mediaStatus: function (uuid) { return api('GET', U.mediaStatus.replace('__ID__', uuid)).then(function (r) { return r.asset; }); },
                media: (n && n.media) || {},
                limits: C.limits,
                markdown: function (text) { return api('POST', U.markdown, { text: text }).then(function (r) { return r.html; }); },
                videoLink: function (u) { return api('GET', U.videoLink + '?url=' + encodeURIComponent(u)).then(function (r) { return r.embed; }); },
                ink: {
                    save: function (blob, meta) { return uploadInk(st.card, blob, meta); },
                    load: function (uuid) { return api('GET', U.inkShow.replace('__ID__', uuid)).then(function (r) { return r.ink; }); }
                }
            },
            saver: {
                exists: function () { return !!st.note; },
                send: function (html) {
                    return st.note
                        ? api('PATCH', nurl('noteUpdate', st.card, st.note), { version: st.version, html: html })
                        : api('POST', nurl('noteStore', st.card), { html: html });
                },
                saved: function (r) {
                    st.note = r.note.uuid; st.version = r.note.version;
                    upsertNote(st.card, r.note);
                    merge(r.card);
                    if (E === st) st.page.setMeta(metaOf(r.note));
                },
                conflict: function (err) {
                    if (!err.data || !err.data.note) return false;
                    // Someone else saved this note first: keep theirs AND this one.
                    upsertNote(st.card, err.data.note);
                    st.note = null; st.version = 0;
                    toast(T.note_conflict, { error: true });
                    return true;
                },
                fatal: fail
            },
            menu: [{ icon: 'bi-trash3', label: T.note_delete, danger: true, onClick: function () { binNote(st); } }],
            after: function (page) {
                st.editor = page.editor; st.saver = page.saver;
                if (!n) setTimeout(function () { if (E === st) page.editor.focus(true); }, 380);
            },
            onClose: function () { if (E === st) E = null; }
        });
    }
    function uploadPicture(cardUuid, blob, name) {
        var fd = new FormData();
        fd.append('image', blob, name);
        return fetch(nurl('pictureStore', cardUuid), {
            method: 'POST', credentials: 'same-origin', body: fd,
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (res) {
            return res.json().catch(function () { return null; }).then(function (d) {
                if (!res.ok || !d || !d.asset) { var e = new Error('upload'); e.status = res.status; e.data = d; throw e; }
                return d.asset;
            });
        });
    }

    function uploadClip(cardUuid, blob, kind, name) {
        var fd = new FormData();
        fd.append('kind', kind);
        fd.append('media', blob, name);
        return fetch(nurl('mediaStore', cardUuid), {
            method: 'POST', credentials: 'same-origin', body: fd,
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (res) {
            return res.json().catch(function () { return null; }).then(function (d) {
                if (!res.ok || !d || !d.asset) { var e = new Error('upload'); e.status = res.status; e.data = d; throw e; }
                return d.asset;
            });
        });
    }

    function uploadInk(cardUuid, blob, meta) {
        var fd = new FormData();
        fd.append('image', blob, blob.type === 'image/png' ? 'drawing.png' : 'drawing.jpg');
        fd.append('kind', meta.kind); fd.append('w', meta.w); fd.append('h', meta.h);
        fd.append('strokes', JSON.stringify(meta.strokes));
        if (meta.base) fd.append('base', meta.base);
        return fetch(nurl('inkStore', cardUuid), {
            method: 'POST', credentials: 'same-origin', body: fd,
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (res) {
            return res.json().catch(function () { return null; }).then(function (d) {
                if (!res.ok || !d || !d.asset) { var e = new Error('upload'); e.status = res.status; e.data = d; throw e; }
                return d.asset;
            });
        });
    }

    // The board's sheets ask for this when they close; the note is a page of its own now.
    function closeNote() { if (E && E.page) E.page.close(); }
    function binNote(st) {
        st.saver.kill();
        var cardUuid = st.card, uuid = st.note;
        st.page.close();
        if (!uuid) return;
        dropNote(cardUuid, uuid);
        api('DELETE', nurl('noteDestroy', cardUuid, uuid))
            .then(function (r) {
                merge(r.card);
                toast(T.note_binned, { action: T.undo, onAction: function () { restoreNote(cardUuid, uuid); } });
            })
            .catch(function (err) { loadNotes(cardUuid); fail(err); });
    }
    function restoreNote(cardUuid, uuid) {
        api('POST', nurl('noteRestore', cardUuid, uuid))
            .then(function (r) { upsertNote(cardUuid, r.note); merge(r.card); toast(T.restored); })
            .catch(fail);
    }
