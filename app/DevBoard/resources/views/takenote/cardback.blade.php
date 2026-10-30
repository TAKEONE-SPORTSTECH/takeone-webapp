{{--
    The open CARD — Trello's card back, on TakeNote's note page (partials/page,
    opened with window/cover/chips/below/aside). Included INSIDE
    takenote/script's closure (shares S, T, C, U, h, icon, api, url, toast,
    upsert, draw, avatar, dueLabel, openSend, picUrl, find, buzz).

    Reading: the cover, the title, one quiet row of chips (label, person, due,
    checklist, attachments, + Add), then THE TEXT large — then attachments and
    checklists. Comments and activity sit beside it on a desk (their own
    scroll, the box pinned to the foot) and under it on a phone (the box pinned
    to the bottom of the screen). Writing is in place, at full size.

    Every server value is drawn with textContent; links open with
    rel="noopener noreferrer"; files are reached only through the card's file
    door, which re-checks access and only ever hands them over as downloads
    (a picture may also be shown, for its preview).
--}}
    /* ── the open card ───────────────────────────────────────────────── */
    var ID1 = '00000000-0000-0000-0000-000000000001';
    function url2(name, id, child) { return U[name].replace(ID0, id).replace(ID1, child); }
    function coverUrl(n) {
        if (!n || !n.cover) return null;
        return n.cover_file ? url2('cardFile', n.uuid, n.cover) + '?preview=1' : picUrl(n.cover, true);
    }
    var FILE_EXT = C.fileTypes || []; // what the server takes (by the bytes, not the name)
    /* a file reads as a file: the document icon of its kind, in its kind's colour (as a file manager shows it) */
    var FILE_GLYPH = { pdf: 'bi-file-earmark-pdf-fill', doc: 'bi-file-earmark-word-fill', docx: 'bi-file-earmark-word-fill', odt: 'bi-file-earmark-word-fill', rtf: 'bi-file-earmark-word-fill',
        xls: 'bi-file-earmark-excel-fill', xlsx: 'bi-file-earmark-excel-fill', ods: 'bi-file-earmark-excel-fill', csv: 'bi-file-earmark-spreadsheet-fill',
        ppt: 'bi-file-earmark-ppt-fill', pptx: 'bi-file-earmark-ppt-fill', odp: 'bi-file-earmark-ppt-fill', zip: 'bi-file-earmark-zip-fill', rar: 'bi-file-earmark-zip-fill', '7z': 'bi-file-earmark-zip-fill',
        txt: 'bi-file-earmark-text-fill', md: 'bi-file-earmark-text-fill', mp3: 'bi-file-earmark-music-fill', m4a: 'bi-file-earmark-music-fill', wav: 'bi-file-earmark-music-fill',
        mp4: 'bi-file-earmark-play-fill', mov: 'bi-file-earmark-play-fill', webm: 'bi-file-earmark-play-fill' };
    /* Which way words run, decided by the words: each text's first strong letter (Arabic and Hebrew
       read right to left), the majority deciding for a group; nothing to go on → the page's own way. */
    var RTL_FIRST = /^[^A-Za-z\u00C0-\u02AF\u0370-\u058F\u0590-\u08FF\uFB1D-\uFDFF\uFE70-\uFEFF]*[\u0590-\u08FF\uFB1D-\uFDFF\uFE70-\uFEFF]/;
    var STRONG = /[A-Za-z\u00C0-\u02AF\u0370-\u058F\u0590-\u08FF\uFB1D-\uFDFF\uFE70-\uFEFF]/;
    function sideOf(texts) {
        var r = 0, l = 0;
        texts.forEach(function (t) { t = String(t || ''); if (!STRONG.test(t)) return; if (RTL_FIRST.test(t)) r++; else l++; });
        if (!r && !l) return document.documentElement.dir === 'rtl' ? 'rtl' : 'ltr';
        return r > l ? 'rtl' : 'ltr';
    }
    var EXT_TONE = { pdf: '#c9372c', doc: '#2b579a', docx: '#2b579a', odt: '#2b579a', rtf: '#2b579a', xls: '#1d6f42', xlsx: '#1d6f42', ods: '#1d6f42', csv: '#1d6f42',
        ppt: '#c43e1c', pptx: '#c43e1c', odp: '#c43e1c', zip: '#6b6280', rar: '#6b6280', '7z': '#6b6280', gz: '#6b6280', tar: '#6b6280', txt: '#6b6280', md: '#6b6280', json: '#6b6280',
        mp3: '#e0457b', m4a: '#e0457b', wav: '#e0457b', ogg: '#e0457b', mp4: '#0c66e4', mov: '#0c66e4', webm: '#0c66e4', psd: '#227d9b', ai: '#c25100', eps: '#c25100', heic: '#6e5dc6' };
    function bytes(n) { return n >= 1048576 ? (n / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(n / 1024)) + ' KB'; }
    function stamp(iso) {
        if (!iso) return '';
        var d = new Date(iso);
        return d.toLocaleDateString(C.locale, { day: 'numeric', month: 'short' }) + ', ' + d.toLocaleTimeString(C.locale, { hour: '2-digit', minute: '2-digit' });
    }

    /* o.filesOnly: a NOTE's part of this — only its attachments, under its text (no checklists, no comments). */
    function cardParts(st, o) {
        o = o || {};
        var P2 = { detail: null, only: false };

        /* chips: the card's facts in one row; each one opens what changes it */
        function chips(page) {
            var n = current(st); if (!n) return null;
            var row = h('div', 'cb-chips');
            if (n.archived) {
                var ar = h('span', 'cb-chip cb-banner is-archived'); ar.append(icon('bi-archive'), document.createTextNode(T.ct_is_archived));
                var rb = h('button', 'cb-chip'); rb.type = 'button'; rb.append(icon('bi-arrow-counterclockwise'), document.createTextNode(T.ct_restore));
                rb.addEventListener('click', function () { archiveCard(n.uuid, false); });
                row.append(ar, rb);
            }
            if (n.template) { var tb = h('span', 'cb-chip cb-banner'); tb.append(icon('bi-card-heading'), document.createTextNode(T.ct_is_template)); row.appendChild(tb); }
            // labels: each one, by name and colour; a tap opens Labels
            (n.labels || []).forEach(function (l) {
                var lb = h('button', 'cb-chip cb-label' + (l.name ? '' : ' is-blank'), l.name || ''); lb.type = 'button';
                lb.style.background = l.color; lb.style.color = inkOn(l.color); lb.setAttribute('aria-label', T.ct_labels + (l.name ? ': ' + l.name : ''));
                lb.addEventListener('click', function () { labelsPop(lb, n.uuid); }); row.appendChild(lb);
            });
            // members: their faces; a tap opens Members
            if ((n.members || []).length) {
                var pc = h('button', 'cb-chip cb-people'); pc.type = 'button'; pc.setAttribute('aria-label', T.ct_members);
                n.members.forEach(function (pp) { pc.appendChild(avatar(pp)); });
                if (n.members.length === 1) pc.appendChild(document.createTextNode(n.members[0].id === S.me.id ? T.tn_me : n.members[0].name));
                pc.addEventListener('click', function () { membersPop(pc, n.uuid); }); row.appendChild(pc);
            }
            // dates: the tick (complete) and the dates; the tick ticks, the rest opens Dates
            if (n.due_on || n.start_on) {
                var dc = h('span', 'cb-chip cb-due ' + dueState(n));
                if (n.due_on) {
                    var tick = h('button', 'cb-tick'); tick.type = 'button'; tick.setAttribute('role', 'checkbox'); tick.setAttribute('aria-checked', String(!!n.done)); tick.setAttribute('aria-label', n.done ? T.tn_mark_open : T.tn_mark_done);
                    tick.appendChild(icon(n.done ? 'bi-check-square-fill' : 'bi-square'));
                    tick.addEventListener('click', function () { setDone(n.uuid, !n.done); });
                    dc.appendChild(tick);
                }
                var dv = h('button', 'cb-dval'); dv.type = 'button';
                var state = n.done ? T.ct_complete : ({ 'is-late': T.tn_overdue, 'is-soon': T.ct_due_soon }[dueState(n)] || '');
                dv.append(document.createTextNode(datesText(n)));
                if (state) dv.appendChild(h('em', null, state));
                dv.appendChild(icon('bi-chevron-down'));
                dv.addEventListener('click', function () { datesPop(dv, n.uuid); });
                dc.appendChild(dv); row.appendChild(dc);
            }
            var steps = (P2.detail ? P2.detail.checklists : []).reduce(function (a, l) { a[0] += l.steps.filter(function (s) { return s.done; }).length; a[1] += l.steps.length; return a; }, [0, 0]);
            if (steps[1]) { var sc = h('span', 'cb-chip' + (steps[0] === steps[1] ? ' is-all' : '')); sc.append(icon('bi-check2-square'), document.createTextNode(steps[0] + '/' + steps[1])); row.appendChild(sc); }
            var na = P2.detail ? P2.detail.attachment_list.length : n.attachments;
            if (na) { var ac = h('span', 'cb-chip'); ac.append(icon('bi-paperclip'), document.createTextNode(String(na))); row.appendChild(ac); }
            // watch: hear about every comment, and the reminder
            var watching = hasMe(n.watchers);
            var wc = h('button', 'cb-chip cb-watch' + (watching ? ' is-on' : '')); wc.type = 'button'; wc.setAttribute('aria-pressed', String(watching));
            wc.append(icon('bi-eye'), document.createTextNode(watching ? T.ct_watching : T.ct_watch));
            if (watching) wc.appendChild(icon('bi-check-lg'));
            wc.addEventListener('click', function () { setWatch(n.uuid, !watching); });
            row.appendChild(wc);
            var add = h('button', 'cb-chip cb-add'); add.type = 'button'; add.append(icon('bi-plus-lg'), document.createTextNode(T.cb_add));
            add.addEventListener('click', function (e) { e.stopPropagation(); addMenu(add); });
            row.appendChild(add);
            return row;
        }
        function addMenu(anchor) {
            var uuid = st.uuid;
            ctPop(anchor, T.ct_add_to_card, function () {
                var wrap = h('div', 'ct-menu');
                [['bi-tag', T.ct_labels, T.ct_add_labels, function () { labelsPop(anchor, uuid); }],
                 ['bi-clock', T.ct_dates, T.ct_add_dates, function () { datesPop(anchor, uuid); }],
                 ['bi-check2-square', T.cb_checklist, T.ct_add_checklist, function () { ctClose(); addChecklist(); }],
                 ['bi-person', T.ct_members, T.ct_add_members, function () { membersPop(anchor, uuid); }],
                 ['bi-paperclip', T.cb_attachment, T.ct_add_attachment, function () { ctClose(); openAttach(st, P2); }],
                 ['bi-card-image', T.ct_cover, T.ct_add_cover, function () { coverPop(anchor, uuid); }]
                ].forEach(function (d) {
                    var b = h('button', 'ct-mrow is-two'); b.type = 'button';
                    var tx = h('span'); tx.append(h('b', null, d[1]), h('small', null, d[2]));
                    b.append(icon(d[0]), tx);
                    b.addEventListener('click', d[3]);
                    wrap.appendChild(b);
                });
                return wrap;
            }, { uuid: uuid });
        }
        function awayMenu(e) { if (!e.target.closest('.cb-pop')) closeMenus(); }
        function closeMenus() { document.querySelectorAll('.cb-pop').forEach(function (x) { x.remove(); }); document.removeEventListener('pointerdown', awayMenu, true); }

        /* below the text: attachments, then checklists */
        var below = h('div', 'cb-below');
        var secAtt = h('section', 'cb-sec'), lists = h('div', 'cb-lists');
        below.append(secAtt, lists);

        /* A NOTE's files: one quiet row of chips under the text — an icon and a name; a tap opens it. */
        function drawChips() {
            var list = P2.detail.attachment_list;
            var row = h('div', 'nf-row');
            list.forEach(function (a) {
                var chip = h('a', 'nf-chip');
                chip.title = a.name;
                if (a.kind === 'link') { chip.href = a.url; chip.target = '_blank'; chip.rel = 'noopener noreferrer'; }
                else if (a.image) { chip.href = url2('cardFile', st.uuid, a.uuid) + '?preview=1'; chip.target = '_blank'; chip.rel = 'noopener'; }
                else { chip.href = url2('cardFile', st.uuid, a.uuid); chip.setAttribute('download', ''); }
                var ic;
                if (a.image) { ic = h('img', 'nf-thumb'); ic.alt = ''; ic.loading = 'lazy'; ic.src = url2('cardFile', st.uuid, a.uuid) + '?preview=1'; }
                else if (a.kind === 'link') { ic = h('span', 'nf-ic'); ic.appendChild(icon('bi-link-45deg')); ic.style.setProperty('--x', '#5b4bd6'); }
                else { ic = h('span', 'nf-ic is-file'); ic.appendChild(icon(FILE_GLYPH[a.ext] || 'bi-file-earmark-fill')); ic.style.setProperty('--x', EXT_TONE[a.ext] || '#6b6280'); }
                var x = h('button', 'nf-x'); x.type = 'button'; x.setAttribute('aria-label', T.cb_remove + ': ' + a.name); x.title = T.cb_remove;
                x.appendChild(icon('bi-x'));
                x.addEventListener('click', function (e) {
                    e.preventDefault(); e.stopPropagation();
                    if (!x.classList.contains('is-sure')) { x.classList.add('is-sure'); x.replaceChildren(document.createTextNode(T.cb_sure)); setTimeout(function () { if (x.isConnected) { x.classList.remove('is-sure'); x.replaceChildren(icon('bi-x')); } }, 3000); return; }
                    api('DELETE', url2('cardAttachment', st.uuid, a.uuid)).then(function (r) {
                        P2.detail.attachment_list = P2.detail.attachment_list.filter(function (y) { return y.uuid !== a.uuid; });
                        upsert(summaryOf(r.note), true); paintAll();
                    }).catch(fail);
                });
                // writing the note: each file can be renamed right where it sits (its extension is kept)
                var ren = h('button', 'nf-ren'); ren.type = 'button'; ren.setAttribute('aria-label', T.cb_rename + ': ' + a.name); ren.title = T.cb_rename;
                ren.appendChild(icon('bi-pencil'));
                ren.addEventListener('click', function (e) { e.preventDefault(); e.stopPropagation(); renameChip(chip, a); });
                var words = h('span', 'nf-words');
                var nm = h('span', 'nf-name', a.name); nm.dir = 'auto'; words.appendChild(nm);
                if (a.kind !== 'link' && a.size) words.appendChild(h('span', 'nf-size', (a.ext ? a.ext.toUpperCase() + ' · ' : '') + bytes(a.size)));
                chip.append(ic, words, ren, x);
                chip.addEventListener('click', function (e) { if (chip.classList.contains('is-renaming')) e.preventDefault(); });
                row.appendChild(chip);
            });
            var add = h('button', 'nf-chip nf-add'); add.type = 'button'; add.setAttribute('aria-label', T.cb_attach_file);
            add.append(icon('bi-paperclip'), h('span', 'nf-name', T.cb_attach));
            add.addEventListener('click', function () { openAttach(st, P2, 'file'); });
            row.appendChild(add);
            secAtt.replaceChildren(row);
            secAtt.hidden = !list.length;
        }
        function renameChip(chip, a) {
            if (chip.classList.contains('is-renaming')) return;
            var ext = a.kind !== 'link' && a.ext && a.name.toLowerCase().endsWith('.' + a.ext.toLowerCase()) ? a.name.slice(-(a.ext.length + 1)) : '';
            var nameEl = chip.querySelector('.nf-name');
            var inp = h('input', 'nf-in'); inp.type = 'text'; inp.maxLength = 190; inp.value = ext ? a.name.slice(0, -ext.length) : a.name;
            inp.setAttribute('aria-label', T.cb_rename); inp.setAttribute('enterkeyhint', 'done');
            chip.classList.add('is-renaming');
            nameEl.replaceWith(inp);
            inp.focus(); inp.select();
            var done = false;
            function finish(save) {
                if (done) return; done = true;
                var v = inp.value.replace(/\s+/g, ' ').trim();
                chip.classList.remove('is-renaming');
                inp.replaceWith(nameEl);
                if (!save || !v || v + ext === a.name) return;
                nameEl.textContent = v + ext; // at once; the server's answer settles it
                api('PATCH', url2('cardAttachment', st.uuid, a.uuid), { name: v + ext }).then(function (r) {
                    Object.assign(a, r.attachment || {}); drawAttachments();
                }).catch(function (err) { nameEl.textContent = a.name; fail(err); });
            }
            inp.addEventListener('keydown', function (e) {
                e.stopPropagation();
                if (e.key === 'Enter') { e.preventDefault(); finish(true); }
                if (e.key === 'Escape') { e.preventDefault(); finish(false); }
            });
            inp.addEventListener('blur', function () { finish(true); });
            inp.addEventListener('click', function (e) { e.preventDefault(); e.stopPropagation(); });
        }
        function drawAttachments() {
            if (o.filesOnly) { drawChips(); return; }
            var list = P2.detail.attachment_list, n = current(st);
            var head = h('div', 'cb-sh');
            var t = h('h2', null, T.cb_attachments + (list.length ? ' · ' + list.length : ''));
            var go = h('button', 'cb-btn is-main'); go.type = 'button'; go.append(icon('bi-paperclip'), document.createTextNode(T.cb_attach));
            go.addEventListener('click', function () { openAttach(st, P2); });
            head.append(t, go);
            var grid = h('div', 'cb-grid');
            list.forEach(function (a) { grid.appendChild(tile(a, n)); });
            var drop = h('button', 'cb-drop', T.cb_drop); drop.type = 'button';
            drop.addEventListener('click', function () { openAttach(st, P2, 'file'); });
            secAtt.replaceChildren(head);
            if (list.length) secAtt.appendChild(grid);
            secAtt.appendChild(drop);
        }
        function tile(a, n) {
            var t = h('div', 'cb-tile');
            var face = h('div', 'cb-face');
            if (a.kind === 'link') {
                face.classList.add('is-link');
                var fav = h('span', 'fav', (a.host || '?').replace(/^www\./, '').charAt(0).toUpperCase());
                face.append(fav, h('span', 'host', a.host || ''));
            } else if (a.image) {
                var im = h('img'); im.alt = ''; im.loading = 'lazy'; im.src = url2('cardFile', st.uuid, a.uuid) + '?preview=1';
                face.appendChild(im);
                if (n && n.cover === a.uuid) face.appendChild(h('span', 'tag', T.cb_cover));
            } else {
                var doc = h('span', 'doc', (a.ext || '?').toUpperCase()); doc.style.setProperty('--x', EXT_TONE[a.ext] || '#6b6280');
                face.appendChild(doc);
            }
            var info = h('div', 'cb-info');
            var name;
            if (a.kind === 'link') {
                name = h('a', 'cb-nm', a.name); name.href = a.url; name.target = '_blank'; name.rel = 'noopener noreferrer';
            } else {
                name = h('a', 'cb-nm', a.name); name.href = url2('cardFile', st.uuid, a.uuid); name.setAttribute('download', '');
            }
            var meta = [a.kind === 'link' ? T.cb_link : (a.size ? bytes(a.size) : ''), a.by ? (a.by.id === S.me.id ? T.tn_me : a.by.name) : '', stamp(a.at)].filter(Boolean).join(' · ');
            info.append(name, h('span', 'mt', meta));
            var acts = h('div', 'cb-acts');
            if (a.image) {
                var cov = h('button', 'cb-mini'); cov.type = 'button';
                cov.textContent = n && n.cover === a.uuid ? T.cb_cover_remove : T.cb_cover_make;
                cov.addEventListener('click', function () { setCover(n && n.cover === a.uuid ? null : a.uuid); });
                acts.appendChild(cov);
            }
            if (a.kind === 'file') {
                var dlb = h('a', 'cb-icon'); dlb.href = url2('cardFile', st.uuid, a.uuid); dlb.setAttribute('download', ''); dlb.setAttribute('aria-label', T.cb_download); dlb.title = T.cb_download;
                dlb.appendChild(icon('bi-download')); acts.appendChild(dlb);
            }
            var del = h('button', 'cb-icon is-danger'); del.type = 'button'; del.setAttribute('aria-label', T.cb_remove); del.title = T.cb_remove;
            del.appendChild(icon('bi-trash3'));
            del.addEventListener('click', function () {
                if (!del.classList.contains('is-sure')) { del.classList.add('is-sure'); del.replaceChildren(document.createTextNode(T.cb_sure)); setTimeout(function () { if (del.isConnected) { del.classList.remove('is-sure'); del.replaceChildren(icon('bi-trash3')); } }, 3000); return; }
                api('DELETE', url2('cardAttachment', st.uuid, a.uuid)).then(function (r) {
                    P2.detail.attachment_list = P2.detail.attachment_list.filter(function (x) { return x.uuid !== a.uuid; });
                    upsert(summaryOf(r.note), true); paintAll();
                }).catch(fail);
            });
            acts.appendChild(del);
            t.append(face, info, acts);
            return t;
        }
        function setCover(uuid) {
            api('POST', url('cardCover', st.uuid), { uuid: uuid }).then(function (r) {
                upsert(summaryOf(r.note), true);
                if (st.page) st.page.setCover(coverUrl(r.note));
                paintAll();
            }).catch(fail);
        }

        function drawLists() {
            lists.replaceChildren.apply(lists, P2.detail.checklists.map(function (l) {
                var sec = h('section', 'cb-sec');
                var nn = current(st);
                // an Arabic checklist runs right to left as a whole — boxes, tools, heading, progress bar
                sec.dir = sideOf(l.steps.length ? l.steps.map(function (s) { return s.text; }) : [nn && nn.title]);
                var done = l.steps.filter(function (s) { return s.done; }).length;
                var head = h('div', 'cb-sh');
                var del = h('button', 'cb-btn'); del.type = 'button'; del.textContent = T.cb_delete;
                del.addEventListener('click', function () {
                    if (!del.classList.contains('is-sure')) { del.classList.add('is-sure'); del.textContent = T.cb_sure; return; }
                    api('DELETE', url2('cardChecklist', st.uuid, l.uuid)).then(gotLists).catch(fail);
                });
                var hide = !!(P2.hide && P2.hide[l.uuid]);
                var hb = h('button', 'cb-btn'); hb.type = 'button'; hb.textContent = hide ? t('ct_show_checked', { n: done }) : T.ct_hide_checked;
                hb.hidden = !done;
                hb.addEventListener('click', function () { P2.hide = P2.hide || {}; P2.hide[l.uuid] = !hide; drawLists(); });
                var hacts = h('div', 'cb-hacts'); hacts.append(hb, del);
                head.append(h('h2', null, l.title + ' · ' + done + '/' + l.steps.length), hacts);
                var bar = h('div', 'cb-bar'); var fill = h('i'); fill.style.width = (l.steps.length ? Math.round(done * 100 / l.steps.length) : 0) + '%'; bar.appendChild(fill);
                var box = h('div', 'cb-steps');
                l.steps.forEach(function (s) {
                    if (hide && s.done) return;
                    var row = h('label', 'cb-step' + (s.done ? ' is-done' : ''));
                    var cb = h('input'); cb.type = 'checkbox'; cb.checked = s.done;
                    cb.addEventListener('change', function () { api('PATCH', url2('cardStep', st.uuid, s.uuid), { done: cb.checked }).then(gotLists).catch(fail); });
                    // Trello's item tools: who does it, by when, or make it a card of its own
                    var who = s.assignee ? S.people.find(function (pp) { return pp.id === s.assignee; }) : null;
                    var pb = h('button', 'cb-sb' + (who ? ' is-set' : '')); pb.type = 'button'; pb.setAttribute('aria-label', T.ct_item_person); pb.title = T.ct_item_person;
                    pb.appendChild(who ? avatar(who) : icon('bi-person-plus'));
                    pb.addEventListener('click', function (e) { e.preventDefault(); stepPerson(pb, s); });
                    var db = h('button', 'cb-sb' + (s.due ? ' is-set ' + (s.due < iso(addDays(0)) && !s.done ? 'is-late' : '') : '')); db.type = 'button'; db.setAttribute('aria-label', T.ct_item_due); db.title = T.ct_item_due;
                    db.appendChild(icon('bi-clock')); if (s.due) db.appendChild(document.createTextNode(fmtDay(s.due)));
                    db.addEventListener('click', function (e) { e.preventDefault(); stepDue(db, s); });
                    var mb = h('button', 'cb-sb'); mb.type = 'button'; mb.setAttribute('aria-label', T.ct_item_card); mb.title = T.ct_item_card;
                    mb.appendChild(icon('bi-kanban'));
                    mb.addEventListener('click', function (e) {
                        e.preventDefault();
                        api('POST', url2('stepCard', st.uuid, s.uuid)).then(function (r) { gotLists(r); upsert(r.note, true); toast(T.ct_item_carded, { action: T.tn_open_card, onAction: function () { st.page.close(); setTimeout(function () { openNote(r.note.uuid, { card: true }); }, DESK ? 0 : 380); } }); }).catch(fail);
                    });
                    var x = h('button', 'cb-x'); x.type = 'button'; x.setAttribute('aria-label', T.cb_remove); x.appendChild(icon('bi-x-lg'));
                    x.addEventListener('click', function (e) { e.preventDefault(); api('DELETE', url2('cardStep', st.uuid, s.uuid)).then(gotLists).catch(fail); });
                    var tools = h('span', 'cb-stools'); tools.append(pb, db, mb, x);
                    var stext = h('span', 'cb-stext', s.text); stext.title = T.ct_item_edit; stext.dir = 'auto';
                    // a tap on the words edits them (Trello's way), not ticks the item
                    stext.addEventListener('click', function (e) { e.preventDefault(); editStep(row, s); });
                    row.append(cb, stext, tools);
                    box.appendChild(row);
                });
                var add = h('input', 'cb-additem'); add.type = 'text'; add.placeholder = T.cb_add_item; add.maxLength = 500; add.setAttribute('aria-label', T.cb_add_item); add.dir = 'auto';
                add.setAttribute('enterkeyhint', 'done');
                // A form, so an item goes in from ANY keyboard's Enter / Go — a phone keyboard is usually still
                // "composing" the word when Enter is pressed, and a bare keydown test missed every one of them —
                // or from the Add button beside the box.
                var addRow = h('form', 'cb-addrow'); addRow.noValidate = true;
                var go = h('button', 'cb-addgo'); go.type = 'submit'; go.setAttribute('aria-label', T.cb_add_item); go.title = T.cb_add_item; go.appendChild(icon('bi-plus-lg'));
                addRow.addEventListener('submit', function (e) {
                    e.preventDefault();
                    var v = add.value.trim(); if (!v || add.disabled) { add.focus(); return; }
                    add.disabled = true; go.disabled = true;
                    api('POST', url2('cardSteps', st.uuid, l.uuid), { text: v }).then(function (r) { gotLists(r); var again = lists.querySelector('[data-list="' + l.uuid + '"] .cb-additem'); if (again) again.focus(); }).catch(function (err) { add.disabled = false; go.disabled = false; fail(err); });
                });
                sec.dataset.list = l.uuid;
                var addEmo = emojiFor(add);
                addRow.append(add, addEmo.btn, go);
                sec.append(head, bar, box, addRow, addEmo.box);
                return sec;
            }));
        }
        function gotLists(r) { P2.detail.checklists = r.checklists; drawLists(); if (st.page) st.page.setMeta({}); }
        /* An item's words, edited in place: Enter or Save keeps them, Esc or Cancel leaves them; emoji as in the add box. */
        function editStep(row, s) {
            var ed = h('div', 'cb-sedit');
            var inp = h('input', 'cb-additem'); inp.type = 'text'; inp.maxLength = 500; inp.value = s.text; inp.dir = 'auto'; inp.setAttribute('aria-label', T.ct_item_edit);
            var emo = emojiFor(inp);
            var bar = h('div', 'cb-sebar');
            var save = h('button', 'cb-send', T.cb_save); save.type = 'button';
            var cancel = h('button', 'cb-btn', T.ct_cancel); cancel.type = 'button';
            bar.append(save, cancel, h('span', 'cb-flex'), emo.btn);
            ed.append(inp, bar, emo.box);
            row.replaceWith(ed);
            inp.focus(); inp.setSelectionRange(inp.value.length, inp.value.length);
            function back() { ed.replaceWith(row); }
            function keep() {
                var v = inp.value.trim();
                if (!v) { inp.focus(); return; }
                if (v === s.text) { back(); return; }
                save.disabled = true;
                api('PATCH', url2('cardStep', st.uuid, s.uuid), { text: v }).then(gotLists).catch(function (err) { save.disabled = false; fail(err); });
            }
            save.addEventListener('click', keep);
            cancel.addEventListener('click', back);
            inp.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' && !e.isComposing) { e.preventDefault(); keep(); }
                if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); back(); }
            });
        }
        function planStep(s, body) { api('PATCH', url2('stepPlan', st.uuid, s.uuid), body).then(function (r) { ctClose(); gotLists(r); }).catch(fail); }
        function stepPerson(anchor, s) {
            ctPop(anchor, T.ct_item_person, function () {
                var wrap = h('div', 'ct-list');
                S.people.forEach(function (pp) {
                    var b = h('button', 'ct-prow' + (s.assignee === pp.id ? ' is-on' : '')); b.type = 'button';
                    b.append(avatar(pp), h('span', null, pp.id === S.me.id ? pp.name + ' (' + T.tn_me + ')' : pp.name), icon(s.assignee === pp.id ? 'bi-check-lg' : 'bi-plus-lg'));
                    b.addEventListener('click', function () { planStep(s, { assignee: s.assignee === pp.id ? null : pp.id }); });
                    wrap.appendChild(b);
                });
                return wrap;
            });
        }
        function stepDue(anchor, s) {
            var month = fromIso(s.due || iso(addDays(0))); month.setDate(1);
            ctPop(anchor, T.ct_item_due, function (p) {
                var wrap = h('div', 'ct-col'), cal = h('div', 'ss-cal ct-cal'), top = h('div', 'top');
                var prev = h('button'); prev.type = 'button'; prev.setAttribute('aria-label', T.tn_prev_month); prev.appendChild(icon('bi-chevron-left flip'));
                var next = h('button'); next.type = 'button'; next.setAttribute('aria-label', T.tn_next_month); next.appendChild(icon('bi-chevron-right flip'));
                prev.addEventListener('click', function () { month.setMonth(month.getMonth() - 1); p.render(); });
                next.addEventListener('click', function () { month.setMonth(month.getMonth() + 1); p.render(); });
                top.append(prev, h('span', null, month.toLocaleDateString(C.locale, { month: 'long', year: 'numeric' })), next);
                var grid = h('div', 'grid'), monday = new Date(2024, 0, 1);
                for (var i = 0; i < 7; i++) { var d0 = new Date(monday); d0.setDate(1 + i); grid.appendChild(h('span', null, d0.toLocaleDateString(C.locale, { weekday: 'narrow' }))); }
                var lead = (month.getDay() + 6) % 7, days = new Date(month.getFullYear(), month.getMonth() + 1, 0).getDate(), today = iso(addDays(0));
                for (var k = 0; k < lead; k++) grid.appendChild(h('span'));
                for (var dd = 1; dd <= days; dd++) {
                    var day = new Date(month.getFullYear(), month.getMonth(), dd), v = iso(day);
                    var b = h('button', null, String(dd)); b.type = 'button';
                    if (v === today) b.classList.add('is-today');
                    if (v === s.due) b.classList.add('is-on');
                    b.setAttribute('aria-label', day.toLocaleDateString(C.locale, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }));
                    (function (val) { b.addEventListener('click', function () { planStep(s, { due: val }); }); })(v);
                    grid.appendChild(b);
                }
                cal.append(top, grid); wrap.appendChild(cal);
                if (s.due) wrap.appendChild(ctBtn(T.ct_remove_dates, null, function () { planStep(s, { due: null }); }));
                return wrap;
            });
        }
        var addingList = false; // one tap, one checklist — a second tap (or a doubled one) while it is being made does nothing
        function addChecklist() {
            if (addingList) return;
            addingList = true;
            api('POST', url('cardChecklists', st.uuid), { title: T.cb_checklist }).then(function (r) {
                addingList = false;
                gotLists(r);
                var last = lists.lastElementChild; if (last) { last.scrollIntoView({ block: 'center', behavior: 'smooth' }); var inp = last.querySelector('.cb-additem'); if (inp) inp.focus(); }
            }).catch(function (err) { addingList = false; fail(err); });
        }

        /* comments and activity */
        var aside = h('aside', 'cb-aside');
        var ahead = h('div', 'cb-ahead');
        var only = h('button', 'cb-btn'); only.type = 'button';
        only.addEventListener('click', function () { P2.only = !P2.only; drawFeed(); });
        ahead.append(h('h2', null, T.cb_activity), only);
        var feed = h('div', 'cb-feed');
        var composer = h('div', 'cb-compose');
        var ta = h('textarea'); ta.dir = 'auto'; ta.rows = 1; ta.placeholder = T.cb_write_comment; ta.setAttribute('aria-label', T.cb_write_comment); ta.maxLength = 4000;
        var cbar = h('div', 'cb-cbar');
        var clip = h('button', 'cb-icon'); clip.type = 'button'; clip.setAttribute('aria-label', T.cb_attach); clip.title = T.cb_attach; clip.appendChild(icon('bi-paperclip'));
        clip.addEventListener('click', function () { openAttach(st, P2); });
        var sendC = h('button', 'cb-send'); sendC.type = 'button'; sendC.append(icon('bi-send-fill'), document.createTextNode(T.cb_send));
        // emoji: the editor's own panel, opened above the box; a tap puts one in where the cursor is
        var emo = emojiFor(ta, grow), emoB = emo.btn, emoBox = emo.box;
        // @: the people to mention, as typing "@" would show them
        var atB = h('button', 'cb-icon'); atB.type = 'button'; atB.setAttribute('aria-label', T.cb_mention); atB.title = T.cb_mention; atB.appendChild(icon('bi-at'));
        atB.addEventListener('pointerdown', function (e) { e.preventDefault(); });
        atB.addEventListener('click', function () {
            ta.focus();
            var pos = ta.selectionStart || ta.value.length, pre = ta.value.slice(0, pos);
            var ins = (pre && !/\s$/.test(pre) ? ' ' : '') + '@';
            ta.value = pre + ins + ta.value.slice(pos);
            ta.selectionStart = ta.selectionEnd = pos + ins.length;
            ta.dispatchEvent(new Event('input')); grow();
        });
        cbar.append(clip, emoB, atB, h('span', 'cb-flex'), sendC);
        composer.append(emoBox, ta, cbar);
        aside.append(ahead, feed, composer);
        function grow() { ta.style.height = 'auto'; ta.style.height = Math.min(160, ta.scrollHeight) + 'px'; }
        ta.addEventListener('input', grow);
        ta.addEventListener('keydown', function (e) { if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); postComment(); } });
        sendC.addEventListener('click', postComment);
        /* @people in a comment: type @, pick a person; they hear about it */
        var atPop = null;
        function closeAt() { if (atPop) { atPop.remove(); atPop = null; } }
        ta.addEventListener('input', function () {
            var before = ta.value.slice(0, ta.selectionStart), m = /(^|\s)@([^\s@]{0,20})$/.exec(before);
            if (!m) { closeAt(); return; }
            var q = m[2].toLowerCase(), list = S.people.filter(function (pp) { return pp.id !== S.me.id && pp.name.toLowerCase().indexOf(q) === 0; }); // never yourself
            if (!list.length) { closeAt(); return; }
            if (!atPop) { atPop = h('div', 'cb-atpop'); composer.appendChild(atPop); }
            atPop.replaceChildren.apply(atPop, list.map(function (pp) {
                var b = h('button'); b.type = 'button'; b.append(avatar(pp), document.createTextNode(pp.name));
                b.addEventListener('pointerdown', function (e) {
                    e.preventDefault();
                    var pos = ta.selectionStart, start = pos - m[2].length - 1;
                    ta.value = ta.value.slice(0, start) + '@' + pp.name + ' ' + ta.value.slice(pos);
                    ta.selectionStart = ta.selectionEnd = start + pp.name.length + 2;
                    closeAt(); grow(); ta.focus();
                });
                return b;
            }));
        });
        ta.addEventListener('blur', function () { setTimeout(closeAt, 150); });
        function mentioned(text) {
            return S.people.filter(function (pp) { return new RegExp('(^|\\s)@' + pp.name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '(?![\\w])', 'i').test(text); }).map(function (pp) { return pp.id; });
        }
        function postComment() {
            var v = ta.value.trim(); if (!v || sendC.disabled) return;
            sendC.disabled = true; closeAt();
            api('POST', url('cardComments', st.uuid), { body: v, mentions: mentioned(v) }).then(function (r) {
                ta.value = ''; grow(); sendC.disabled = false; emo.close();
                P2.detail.comments.push(r.comment); drawFeed(true);
                var n = current(st); if (n) { n.replies = (n.replies || 0) + 1; draw(); }
            }).catch(function (err) { sendC.disabled = false; fail(err); });
        }
        /* Reply (Trello's way): the comment box opens with "@Name " in front, ready to write */
        function replyTo(c) {
            var name = c.by && c.by.name; if (!name) return;
            var tag = '@' + name + ' ';
            if (ta.value.indexOf(tag) !== 0) ta.value = tag + ta.value.replace(/^\s+/, '');
            grow();
            ta.focus(); ta.selectionStart = ta.selectionEnd = ta.value.length;
            composer.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        }
        var ACT = {
            created: 'cb_a_created', moved: 'cb_a_moved', planned: 'cb_a_planned', converted: 'cb_a_converted', attached: 'cb_a_attached',
            linked: 'cb_a_linked', detached: 'cb_a_detached', checklist_added: 'cb_a_checklist', cover_set: 'cb_a_cover', cover_removed: 'cb_a_uncover',
            note_edited: 'cb_a_edited', restored: 'cb_a_restored', dates: 'cb_a_dates', completed: 'cb_a_completed', reopened: 'cb_a_reopened',
            archived: 'cb_a_archived', unarchived: 'cb_a_unarchived', copied: 'cb_a_copied'
        };
        var ACT_ICON = { created: 'bi-plus-lg', moved: 'bi-arrow-right', planned: 'bi-calendar3', converted: 'bi-kanban', attached: 'bi-paperclip', linked: 'bi-link-45deg',
            detached: 'bi-trash3', checklist_added: 'bi-check2-square', cover_set: 'bi-image', cover_removed: 'bi-image', note_edited: 'bi-pencil', restored: 'bi-arrow-counterclockwise',
            dates: 'bi-clock', completed: 'bi-check-square', reopened: 'bi-square', archived: 'bi-archive', unarchived: 'bi-arrow-counterclockwise', copied: 'bi-copy' };
        function actText(a) {
            var key = ACT[a.action]; if (!key) return null;
            if (a.action === 'converted') key = a.meta && a.meta.to === 'note' ? 'cb_a_to_note' : 'cb_a_to_card';
            var s = T[key] || '';
            ['name', 'lane', 'from', 'to', 'title'].forEach(function (k) { s = s.replace(':' + k, (a.meta && a.meta[k]) || ''); });
            return s;
        }
        function drawFeed(toEnd) {
            only.textContent = P2.only ? T.cb_show_all : T.cb_comments_only;
            var items = P2.detail.comments.map(function (c) { return { c: c, at: c.at }; });
            if (!P2.only) {
                var lastEdit = 0;
                P2.detail.activity.forEach(function (a) {
                    if (!ACT[a.action]) return;
                    // a run of edits is one line, not dozens
                    if (a.action === 'note_edited') { var t = +new Date(a.at); if (Math.abs(t - lastEdit) < 3600000) return; lastEdit = t; }
                    items.push({ a: a, at: a.at });
                });
            }
            items.sort(function (x, y) { return new Date(x.at) - new Date(y.at); });
            feed.replaceChildren.apply(feed, items.map(function (it) { return it.c ? commentRow(it.c) : actRow(it.a); }).filter(Boolean));
            if (!items.length) feed.appendChild(h('p', 'cb-none', T.cb_no_comments));
            if (toEnd) feed.scrollTop = feed.scrollHeight;
        }
        function commentRow(c) {
            var row = h('div', 'cb-c');
            var av = avatar(c.by || { name: '?' });
            var body = h('div', 'cb-cbody');
            var who = h('div', 'cb-who'); who.append(h('b', null, c.by ? (c.by.id === S.me.id ? T.tn_me : c.by.name) : '?'), document.createTextNode(' · ' + stamp(c.at) + (c.edited ? ' · ' + T.cb_edited : '')));
            var bub = h('div', 'cb-bub'); bub.dir = 'auto';
            paintMentions(bub, c.body);
            // one quiet line right under the bubble: reactions, then Reply — or, your own, Edit · Delete
            var line = reactionsRow(c), acts = h('span', 'cb-cacts');
            line.appendChild(acts);
            body.append(who, bub, line);
            var mine = c.by && c.by.id === S.me.id;
            if (!mine && c.by) {
                var rp = h('button', null, T.cb_reply); rp.type = 'button';
                rp.addEventListener('click', function () { replyTo(c); });
                acts.appendChild(rp);
            }
            if (mine) {
                var ed = h('button', null, T.cb_edit); ed.type = 'button';
                var dl = h('button', null, T.cb_delete); dl.type = 'button';
                ed.addEventListener('click', function () {
                    var area = h('textarea', 'cb-cedit'); area.dir = 'auto'; area.value = c.body; area.maxLength = 4000;
                    var save = h('button', 'cb-send', T.cb_save); save.type = 'button';
                    var cancel = h('button', 'cb-btn', T.ct_cancel); cancel.type = 'button';
                    var ebar = h('div', 'cb-ebar'); ebar.append(save, cancel);
                    bub.replaceWith(area); line.replaceWith(ebar); area.focus();
                    cancel.addEventListener('click', function () { drawFeed(); });
                    save.addEventListener('click', function () {
                        var v = area.value.trim(); if (!v) return;
                        api('PATCH', url2('cardComment', st.uuid, c.uuid), { body: v, mentions: mentioned(v) }).then(function (r) { Object.assign(c, r.comment); drawFeed(); }).catch(fail);
                    });
                });
                dl.addEventListener('click', function () {
                    if (!dl.classList.contains('is-sure')) { dl.classList.add('is-sure'); dl.textContent = T.cb_sure; return; }
                    api('DELETE', url2('cardComment', st.uuid, c.uuid)).then(function () {
                        P2.detail.comments = P2.detail.comments.filter(function (x) { return x.uuid !== c.uuid; }); drawFeed();
                    }).catch(fail);
                });
                acts.append(ed, dl);
            }
            row.append(av, body);
            return row;
        }
        /* the text as written, with each @person picked out (still text nodes only) */
        function paintMentions(el, text) {
            var names = S.people.map(function (pp) { return pp.name; }).filter(Boolean).sort(function (a, b) { return b.length - a.length; });
            if (!names.length) { el.textContent = text; return; }
            var re = new RegExp('@(' + names.map(function (n) { return n.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); }).join('|') + ')', 'g'), last = 0, m;
            while ((m = re.exec(text))) {
                if (m.index > last) el.appendChild(document.createTextNode(text.slice(last, m.index)));
                el.appendChild(h('b', 'cb-ment', m[0]));
                last = m.index + m[0].length;
            }
            if (last < text.length) el.appendChild(document.createTextNode(text.slice(last)));
        }
        // the first six reactions were icons (still shown); any standard emoji is one now, WhatsApp's six first
        var REACT_ICON = { up: 'bi-hand-thumbs-up-fill', heart: 'bi-heart-fill', check: 'bi-check-circle-fill', star: 'bi-star-fill', fire: 'bi-fire', eyes: 'bi-eye-fill' };
        var QUICK = ['\u{1F44D}', '❤️', '\u{1F602}', '\u{1F62E}', '\u{1F622}', '\u{1F64F}'];
        function reactionsRow(c) {
            var row = h('div', 'cb-reacts');
            Object.keys(c.reactions || {}).forEach(function (k) {
                var ids = c.reactions[k]; if (!ids || !ids.length) return;
                var mine = ids.indexOf(S.me.id) !== -1;
                var b = h('button', 'cb-react' + (REACT_ICON[k] ? ' is-' + k : ' is-emoji') + (mine ? ' is-mine' : '')); b.type = 'button';
                b.title = ids.map(function (id) { var pp = S.people.find(function (x) { return x.id === id; }); return pp ? pp.name : ''; }).join(', ');
                b.append(REACT_ICON[k] ? icon(REACT_ICON[k]) : h('span', 'cb-remo', k), document.createTextNode(String(ids.length)));
                b.addEventListener('click', function () { react(c, k); });
                row.appendChild(b);
            });
            var add = h('button', 'cb-react is-add'); add.type = 'button'; add.setAttribute('aria-label', T.ct_react); add.title = T.ct_react;
            add.appendChild(icon('bi-emoji-smile'));
            add.addEventListener('click', function () {
                ctPop(add, T.ct_react, function (p) {
                    var g = h('div', 'ct-reacts');
                    QUICK.forEach(function (k) {
                        var b = h('button', 'ct-remo', k); b.type = 'button'; b.setAttribute('aria-label', k);
                        if ((c.reactions[k] || []).indexOf(S.me.id) !== -1) b.classList.add('is-mine');
                        b.addEventListener('click', function () { ctClose(); react(c, k); });
                        g.appendChild(b);
                    });
                    // "+": every emoji, the editor's own panel
                    var more = h('button', 'ct-remo is-more'); more.type = 'button'; more.setAttribute('aria-label', T.ct_react_more); more.title = T.ct_react_more;
                    more.appendChild(icon('bi-plus-lg'));
                    more.addEventListener('click', function () {
                        if (!window.MarginEmoji) return;
                        p.view(T.ct_react_more, function () {
                            var wrap = h('div', 'cb-emo is-pop');
                            wrap.appendChild(MarginEmoji.panel(T, function (ch) { ctClose(); react(c, ch); }));
                            return wrap;
                        });
                    });
                    g.appendChild(more);
                    return g;
                });
            });
            row.appendChild(add);
            return row;
        }
        function react(c, k) {
            api('POST', url2('react', st.uuid, c.uuid), { kind: k }).then(function (r) { Object.assign(c, r.comment); drawFeed(); }).catch(fail);
        }
        function actRow(a) {
            var text = actText(a); if (text === null) return null;
            var row = h('div', 'cb-act');
            var ic = h('span', 'cb-aic'); ic.appendChild(icon(ACT_ICON[a.action] || 'bi-dot'));
            var line = h('div');
            line.append(h('b', null, a.by ? (a.by.id === S.me.id ? T.tn_me : a.by.name) : ''), document.createTextNode(' ' + text), h('span', 'cb-at', ' · ' + stamp(a.at)));
            row.append(ic, line);
            return row;
        }

        function paintAll() {
            if (!P2.detail) return;
            drawAttachments();
            if (o.filesOnly) return;
            drawLists(); drawFeed(); if (st.page) st.page.setMeta({});
        }

        return {
            chips: chips, below: below, aside: aside, composer: composer,
            set: function (detail) { P2.detail = detail; paintAll(); feed.scrollTop = feed.scrollHeight; },
            refresh: paintAll,
            get: function () { return P2; },
            attach: function (tab) { (st.uuid ? Promise.resolve() : ensure(st)).then(function () { if (!P2.detail) P2.detail = { attachment_list: [] }; openAttach(st, P2, tab); }).catch(fail); },
            drop: function (files) { (st.uuid ? Promise.resolve() : ensure(st)).then(function () { if (!P2.detail) P2.detail = { attachment_list: [] }; uploadMany(st, P2, files, null); }).catch(fail); }
        };
    }

    /* An emoji button for a text box (a comment, a checklist item): the editor's own panel (MarginEmoji)
       in a dark box; a tap puts the emoji in where the cursor was, and the panel stays open for more. */
    function emojiFor(field, after) {
        var btn = h('button', 'cb-icon'); btn.type = 'button'; btn.setAttribute('aria-label', T.fmt_emoji); btn.title = T.fmt_emoji; btn.setAttribute('aria-expanded', 'false');
        btn.appendChild(icon('bi-emoji-smile'));
        var box = h('div', 'cb-emo'); box.hidden = true;
        // where the cursor was in the box (a browser forgets it once the panel's search takes the focus)
        var caret = null;
        ['input', 'select', 'keyup', 'click'].forEach(function (ev) { field.addEventListener(ev, function () { caret = [field.selectionStart, field.selectionEnd]; }); });
        function set(open) { box.hidden = !open; btn.classList.toggle('is-on', open); btn.setAttribute('aria-expanded', String(open)); }
        btn.addEventListener('click', function () {
            if (!window.MarginEmoji) return;
            if (!box.firstChild || box.firstChild.failed) {
                var panel = MarginEmoji.panel(T, function (ch) {
                    var v = field.value, a = Math.min(caret ? caret[0] : v.length, v.length), b = Math.min(caret ? caret[1] : a, v.length);
                    if (field.maxLength > 0 && v.length - (b - a) + ch.length > field.maxLength) return;
                    field.value = v.slice(0, a) + ch + v.slice(b);
                    caret = [a + ch.length, a + ch.length];
                    field.setSelectionRange(caret[0], caret[1]);
                    if (after) after();
                });
                panel.search.addEventListener('keydown', function (e) { if (e.key === 'Escape') { e.stopPropagation(); set(false); } });
                box.replaceChildren(panel);
            }
            set(box.hidden);
        });
        return { btn: btn, box: box, close: function () { set(false); } };
    }

    /* ── Attach: a link, or files (a sheet with two tabs) ─────────────── */
    function openAttach(st, P2, tab) {
        if (document.querySelector('.ss')) return;
        var n = current(st);
        var root = h('div', 'ss ss-attach'); root.setAttribute('role', 'dialog'); root.setAttribute('aria-modal', 'true'); root.setAttribute('aria-labelledby', 'at-title');
        var dim = h('div', 'ss-dim'), sheet = h('div', 'ss-sheet');
        var band = h('div', 'ss-band'), row = h('div', 'r');
        var tl = h('span', 'tile2'); tl.appendChild(icon('bi-paperclip'));
        var txt = h('div'); txt.style.minWidth = '0'; txt.style.flex = '1';
        var h3 = h('h3', null, T.cb_attach); h3.id = 'at-title';
        txt.append(h3, h('p', null, n ? n.title : ''));
        var x = h('button', 'x'); x.type = 'button'; x.setAttribute('aria-label', T.close); x.appendChild(icon('bi-x-lg'));
        row.append(tl, txt, x); band.append(h('div', 'hd'), row);

        var body = h('div', 'ss-body at-body');
        var tabs = h('div', 'fs-tabs at-tabs'); tabs.setAttribute('role', 'tablist');
        var tLink = h('button', null, T.cb_tab_link), tFile = h('button', null, T.cb_tab_file);
        [tLink, tFile].forEach(function (b) { b.type = 'button'; b.setAttribute('role', 'tab'); tabs.appendChild(b); });

        // link
        var pLink = h('div', 'at-pane');
        var uL = h('label', 'at-f'); var uIn = h('input'); uIn.type = 'url'; uIn.placeholder = 'https://'; uIn.setAttribute('inputmode', 'url'); uIn.autocapitalize = 'off'; uIn.maxLength = 1000;
        uL.append(h('span', null, T.cb_paste_link), uIn);
        var hostRow = h('div', 'at-host'); hostRow.hidden = true;
        var nL = h('label', 'at-f'); var nIn = h('input'); nIn.type = 'text'; nIn.maxLength = 200;
        nL.append(h('span', null, T.cb_link_name), nIn);
        var goL = h('button', 'ss-go'); goL.type = 'button'; goL.textContent = T.cb_attach_link;
        pLink.append(uL, hostRow, nL, goL);
        function readUrl() {
            var v = uIn.value.trim(); if (v && !/^[a-z][a-z0-9+.-]*:/i.test(v)) v = 'https://' + v;
            try { var u = new URL(v); if (u.protocol === 'http:' || u.protocol === 'https:') return u; } catch (e) {}
            return null;
        }
        uIn.addEventListener('input', function () {
            var u = readUrl(); hostRow.hidden = !u;
            if (u) { hostRow.replaceChildren(h('span', 'fav', u.hostname.replace(/^www\./, '').charAt(0).toUpperCase()), h('span', null, u.hostname)); }
        });
        goL.addEventListener('click', function () {
            var u = readUrl(); if (!u) { uIn.focus(); toast(T.cb_link_refused, { error: true }); return; }
            goL.disabled = true;
            api('POST', url('cardLinks', st.uuid), { url: u.href, name: nIn.value.trim() || null }).then(function (r) {
                close(); added(st, P2, r);
            }).catch(function (err) { goL.disabled = false; fail(err); });
        });

        // file
        var pFile = h('div', 'at-pane');
        var pick = h('input'); pick.type = 'file'; pick.multiple = true; pick.hidden = true; pick.accept = FILE_EXT.map(function (e) { return '.' + e; }).join(',');
        var zone = h('button', 'at-zone'); zone.type = 'button';
        var zi = h('span', 'zi'); zi.appendChild(icon('bi-upload'));
        zone.append(zi, h('b', null, T.cb_choose_files), h('span', null, T.cb_file_kinds.replace(':mb', C.fileMb)));
        var queue = h('div', 'at-queue');
        pFile.append(zone, pick, queue);
        zone.addEventListener('click', function () { pick.click(); });
        pick.addEventListener('change', function () { uploadMany(st, P2, Array.prototype.slice.call(pick.files || []), queue); pick.value = ''; });
        ['dragover', 'dragenter'].forEach(function (ev) { zone.addEventListener(ev, function (e) { e.preventDefault(); zone.classList.add('is-over'); }); });
        ['dragleave', 'drop'].forEach(function (ev) { zone.addEventListener(ev, function () { zone.classList.remove('is-over'); }); });
        zone.addEventListener('drop', function (e) { e.preventDefault(); uploadMany(st, P2, Array.prototype.slice.call(e.dataTransfer.files || []), queue); });

        body.append(tabs, pLink, pFile);
        sheet.append(band, body); root.append(dim, sheet); document.body.appendChild(root);
        function show(which) {
            [[tLink, pLink, 'link'], [tFile, pFile, 'file']].forEach(function (d) { var on = d[2] === which; d[0].classList.toggle('is-on', on); d[0].setAttribute('aria-selected', String(on)); d[1].hidden = !on; });
            if (which === 'link') setTimeout(function () { uIn.focus(); }, 60);
        }
        tLink.addEventListener('click', function () { show('link'); });
        tFile.addEventListener('click', function () { show('file'); });
        show(tab || 'link');
        function close() { document.removeEventListener('keydown', esc, true); root.remove(); }
        function esc(e) { if (e.key === 'Escape') { e.stopImmediatePropagation(); close(); } }
        document.addEventListener('keydown', esc, true);
        x.addEventListener('click', close); dim.addEventListener('click', close);
    }
    function added(st, P2, r) {
        if (P2 && P2.detail) P2.detail.attachment_list.push(r.attachment);
        upsert(summaryOf(r.note), true);
        if (st.parts) st.parts.refresh();
        toast(T.cb_attached.replace(':name', r.attachment.name));
    }
    /* Files go one by one, each with its own progress; one we cannot take says why, the rest carry on. */
    function uploadMany(st, P2, files, queue) {
        files.forEach(function (f) {
            var ext = (f.name.split('.').pop() || '').toLowerCase();
            var row = queue ? h('div', 'at-item') : null, bar = h('i'), meta = h('span', 'mt');
            if (row) {
                var doc = h('span', 'doc', ext.toUpperCase().slice(0, 4)); doc.style.setProperty('--x', EXT_TONE[ext] || '#6b6280');
                var info = h('div', 'at-info'); var track = h('div', 'at-track'); track.appendChild(bar);
                info.append(h('b', null, f.name), track, meta);
                row.append(doc, info); queue.appendChild(row);
            }
            var refuse = function (msg) { if (row) { row.classList.add('is-bad'); meta.textContent = msg; } else toast(msg, { error: true }); };
            if (FILE_EXT.indexOf(ext) === -1) { refuse(T.cb_not_allowed); return; }
            if (f.size > C.fileMb * 1048576) { refuse(T.cb_too_big.replace(':mb', C.fileMb)); return; }
            if (roomLeft() < f.size) { refuse(T.st_full); return; } // the notebook's 1 GB: refused before the upload, not after
            var fd = new FormData(); fd.append('file', f, f.name);
            var xhr = new XMLHttpRequest();
            xhr.open('POST', url('cardFiles', st.uuid));
            xhr.setRequestHeader('Accept', 'application/json'); xhr.setRequestHeader('X-CSRF-TOKEN', CSRF); xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.upload.onprogress = function (e) { if (e.lengthComputable) { bar.style.width = Math.round(e.loaded * 100 / e.total) + '%'; meta.textContent = bytes(e.loaded) + ' / ' + bytes(e.total); } };
            xhr.onload = function () {
                var d = null; try { d = JSON.parse(xhr.responseText); } catch (e) {}
                if (xhr.status === 200 && d && d.attachment) { if (row) { row.classList.add('is-ok'); meta.textContent = T.cb_done; } spent(f.size); added(st, P2, d); }
                else refuse((d && d.message) || T.cb_file_refused);
            };
            xhr.onerror = function () { refuse(T.note_offline); };
            xhr.send(fd);
        });
    }

    /* ── a card goes back to the notebook ─────────────────────────────── */
    function convertToNote(st) {
        var uuid = st.uuid; if (!uuid) return;
        api('POST', url('convert', uuid), { to: 'note' }).then(function (r) {
            st.page.close();
            upsert(summaryOf(r.note));
            toast(T.cb_now_note, { action: T.undo, onAction: function () {
                api('POST', url('convert', uuid), { to: 'card', lane: r.note.lane ? r.note.lane.uuid : null }).then(function (u) { upsert(summaryOf(u.note)); }).catch(fail);
            } });
        }).catch(fail);
    }
