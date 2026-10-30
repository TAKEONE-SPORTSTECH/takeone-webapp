{{--
    Trello's card tools on TakeNote's board (studied 28 Oct 2026): the small
    popovers Trello opens from a card — Labels, Members, Dates, Cover, Move,
    Copy, Share — the quick-edit menu (the pencil, a right-click, or E), the
    board's Filter and Archived items, cards from templates, and the keyboard
    shortcuts (L labels, D dates, M members, Space join, C archive, E edit).

    Included INSIDE takenote/script's closure (shares S, T, C, U, h, icon, api,
    url, toast, upsert, draw, avatar, find, P, buzz, iso, fromIso, addDays,
    coverUrl, url2, openNote, DESK).

    A popover sits under what opened it on a desk, and rises from the bottom as
    a sheet (with its gradient band) on a phone. Everything the server sends is
    drawn with textContent; a label's colour is one of the board's twenty.
--}}
    /* ── Trello's card tools ─────────────────────────────────────────── */
    var POP = null;
    var REMINDERS = [null, 0, 5, 10, 15, 60, 120, 1440, 2880];
    try { S.labelsWide = localStorage.getItem('takenote.labels.narrow') !== '1'; } catch (e) { S.labelsWide = true; }
    S.bf = { q: '', members: [], labels: [], due: '', done: '' };

    function inkOn(hex) {
        var c = String(hex || '#888888').replace('#', '');
        var r = parseInt(c.substr(0, 2), 16), g = parseInt(c.substr(2, 2), 16), b = parseInt(c.substr(4, 2), 16);
        return (0.299 * r + 0.587 * g + 0.114 * b) > 150 ? '#172b4d' : '#fff';
    }
    function labelBar(l, cls) {
        var s = h('span', (cls || 'ct-lab') + (l.name ? '' : ' is-blank'), l.name || '');
        s.style.background = l.color; s.style.color = inkOn(l.color);
        return s;
    }
    function hasMe(list) { return (list || []).some(function (x) { return (x && x.id !== undefined ? x.id : x) === S.me.id; }); }
    function fmtDay(s, year) { var d = fromIso(s); var o = { day: 'numeric', month: 'short' }; if (year) o.year = 'numeric'; return d.toLocaleDateString(C.locale, o); }
    /* When it is due, as a moment (09:00 when no time was set, as the server reminds). */
    function dueMoment(n) {
        if (!n.due_on) return null;
        var d = fromIso(n.due_on), t = (n.due_time || '09:00').split(':');
        d.setHours(+t[0], +t[1], 0, 0);
        return d;
    }
    /* The dates badge Trello draws: start – due, the time, and green / yellow / red. */
    function datesText(n) {
        var due = n.due_on ? fmtDay(n.due_on) + (n.due_time ? ', ' + n.due_time : '') : '';
        if (n.start_on && n.due_on) return fmtDay(n.start_on) + ' – ' + due;
        if (n.start_on) return T.ct_starts + ' ' + fmtDay(n.start_on);
        return due;
    }
    function dueState(n) {
        if (n.done) return 'is-done';
        var m = dueMoment(n); if (!m) return '';
        var left = m - new Date();
        if (left < 0) return 'is-late';
        return left < 86400000 ? 'is-soon' : '';
    }

    /* A change came back from the server: the board, and the open card if it is this one. */
    function touched(note) {
        upsert(note, true);
        if (P && P.uuid === note.uuid && P.page) {
            P.page.setMeta({});
            P.page.setCover(coverUrl(note), note.cover_color);
        }
        if (POP && POP.uuid === note.uuid) POP.render();
    }
    function sendTool(name, uuid, body) {
        return api('POST', url(name, uuid), body).then(function (r) { touched(r.note); return r; }).catch(function (err) { load(); fail(err); });
    }

    /* ── the popover ─────────────────────────────────────────────────── */
    function ctClose() {
        if (!POP) return;
        var p = POP; POP = null;
        document.removeEventListener('keydown', p.esc, true);
        window.removeEventListener('resize', p.place);
        if (p.ro) p.ro.disconnect();
        p.root.remove();
    }
    function ctPop(anchor, title, build, o) {
        o = o || {};
        ctClose();
        var sheet = window.matchMedia('(max-width: 640px)').matches;
        var root = h('div', 'ct' + (sheet ? ' is-sheet' : ''));
        var dim = h('div', 'ct-dim');
        var box = h('div', 'ct-box'); box.setAttribute('role', 'dialog'); box.setAttribute('aria-modal', 'true');
        var head = h('div', 'ct-ph');
        if (sheet) head.appendChild(h('div', 'hd'));
        var row = h('div', 'ct-pr');
        var back = h('button', 'ct-hb'); back.type = 'button'; back.setAttribute('aria-label', T.ct_back); back.appendChild(icon('bi-chevron-left flip'));
        var ttl = h('h3');
        var x = h('button', 'ct-hb'); x.type = 'button'; x.setAttribute('aria-label', T.close); x.appendChild(icon('bi-x-lg'));
        row.append(back, ttl, x); head.appendChild(row);
        var body = h('div', 'ct-pb');
        box.append(head, body); root.append(dim, box);
        document.body.appendChild(root);

        var p = POP = { root: root, body: body, uuid: o.uuid || null, stack: [], close: ctClose, anchor: anchor };
        function show(t, b) {
            ttl.textContent = t; box.setAttribute('aria-label', t); p.build = b; back.hidden = !p.stack.length;
            body.scrollTop = 0; p.render();
            var f = body.querySelector('input[type=text]');
            if (f && !sheet) setTimeout(function () { f.focus(); }, 30);
        }
        p.render = function () { var y = body.scrollTop; body.replaceChildren(p.build(p)); body.scrollTop = y; p.place(); };
        p.view = function (t, b) { p.stack.push({ t: ttl.textContent, b: p.build }); show(t, b); };
        p.back = function () { var prev = p.stack.pop(); if (!prev) { ctClose(); return; } ttl.textContent = prev.t; p.build = prev.b; back.hidden = !p.stack.length; p.render(); };
        p.place = function () {
            if (sheet) return;
            var w = box.offsetWidth, hh = box.offsetHeight, r = anchor && anchor.isConnected ? anchor.getBoundingClientRect() : null;
            if (!r) {
                // no anchor (a page menu), or it was redrawn: keep the place, but always on screen
                if (!box.style.left) { box.style.left = Math.max(8, (innerWidth - w) / 2) + 'px'; box.style.top = Math.max(8, (innerHeight - hh) / 3) + 'px'; }
                var t0 = parseFloat(box.style.top) || 8;
                if (t0 + hh > innerHeight - 8) box.style.top = Math.max(8, innerHeight - hh - 8) + 'px';
                return;
            }
            var left = document.dir === 'rtl' ? r.right - w : r.left;
            left = Math.min(Math.max(8, left), innerWidth - w - 8);
            var top = r.bottom + 6;
            if (top + hh > innerHeight - 8) top = Math.max(8, Math.min(r.top - hh - 6, innerHeight - hh - 8));
            box.style.left = left + 'px'; box.style.top = top + 'px';
        };
        p.esc = function (e) { if (e.key === 'Escape') { e.stopImmediatePropagation(); e.preventDefault(); p.back(); } };
        document.addEventListener('keydown', p.esc, true);
        window.addEventListener('resize', p.place);
        // content that grows (an emoji panel opening inside) moves the popover back on screen
        if (window.ResizeObserver) { p.ro = new ResizeObserver(function () { p.place(); }); p.ro.observe(body); }
        dim.addEventListener('click', ctClose);
        x.addEventListener('click', ctClose);
        back.addEventListener('click', p.back);
        show(title, build);
        return p;
    }
    function ctSearch(ph, value, onInput) {
        var wrap = h('label', 'ct-search');
        var inp = h('input'); inp.type = 'text'; inp.placeholder = ph; inp.value = value || ''; inp.setAttribute('aria-label', ph); inp.maxLength = 60;
        wrap.append(icon('bi-search'), inp);
        inp.addEventListener('input', function () { onInput(inp.value.trim().toLowerCase()); });
        return wrap;
    }
    function ctBtn(text, cls, fn) { var b = h('button', 'ct-btn' + (cls ? ' ' + cls : ''), text); b.type = 'button'; if (fn) b.addEventListener('click', fn); return b; }
    function ctHead(text) { return h('h4', 'ct-h', text); }
    function sure(b, fn) {
        b.addEventListener('click', function () {
            if (!b.classList.contains('is-sure')) { var was = b.textContent; b.classList.add('is-sure'); b.textContent = T.cb_sure; setTimeout(function () { if (b.isConnected) { b.classList.remove('is-sure'); b.textContent = was; } }, 3000); return; }
            fn();
        });
    }

    /* ── Labels ──────────────────────────────────────────────────────── */
    function labelsPop(anchor, uuid) {
        var q = '';
        ctPop(anchor, T.ct_labels, function (p) {
            var wrap = h('div', 'ct-col'), list = h('div', 'ct-list');
            wrap.append(ctSearch(T.ct_search_labels, q, function (v) { q = v; rows(); }), ctHead(T.ct_labels), list);
            function rows() {
                var n = find(uuid); if (!n) return;
                var on = (n.labels || []).map(function (l) { return l.uuid; });
                list.replaceChildren.apply(list, (S.labels || []).filter(function (l) { return !q || (l.name || '').toLowerCase().indexOf(q) !== -1; }).map(function (l) {
                    var r = h('div', 'ct-lrow'), isOn = on.indexOf(l.uuid) !== -1;
                    var box = h('button', 'ct-check' + (isOn ? ' is-on' : '')); box.type = 'button';
                    box.setAttribute('role', 'checkbox'); box.setAttribute('aria-checked', String(isOn)); box.setAttribute('aria-label', l.name || T.ct_label_blank);
                    box.appendChild(icon('bi-check-lg'));
                    var bar = h('button', 'ct-lbar'); bar.type = 'button'; bar.appendChild(labelBar(l));
                    var ed = h('button', 'ct-hb'); ed.type = 'button'; ed.setAttribute('aria-label', T.ct_label_edit); ed.appendChild(icon('bi-pencil'));
                    var flip = function () { toggleLabel(uuid, l, !isOn); rows(); };
                    box.addEventListener('click', flip); bar.addEventListener('click', flip);
                    ed.addEventListener('click', function () { p.view(T.ct_label_edit, function () { return labelForm(p, uuid, l); }); });
                    r.append(box, bar, ed);
                    return r;
                }));
            }
            rows();
            wrap.appendChild(ctBtn(T.ct_label_new, 'is-wide', function () { p.view(T.ct_label_new, function () { return labelForm(p, uuid, null); }); }));
            return wrap;
        }, { uuid: uuid });
    }
    function toggleLabel(uuid, l, on) {
        var n = find(uuid); if (!n) return;
        n.labels = on ? (n.labels || []).concat([{ uuid: l.uuid, name: l.name, color: l.color }]) : (n.labels || []).filter(function (x) { return x.uuid !== l.uuid; });
        draw(); if (P && P.uuid === uuid) P.page.setMeta({});
        sendTool('cardLabels', uuid, { label: l.uuid, on: on });
    }
    function labelForm(p, uuid, l) {
        var st = { name: l ? (l.name || '') : '', color: l ? l.color : C.labelColors[5] };
        var wrap = h('div', 'ct-col');
        var prev = h('div', 'ct-lprev');
        var name = h('input'); name.type = 'text'; name.maxLength = 40; name.value = st.name; name.placeholder = T.ct_label_name;
        var field = h('label', 'ct-f'), nameRow = h('div', 'ct-frow');
        var emo = emojiFor(name, function () { st.name = name.value; paint(); }); // the same emoji button as comments (takenote/cardback)
        nameRow.append(name, emo.btn);
        field.append(h('span', null, T.ct_label_name), nameRow);
        var grid = h('div', 'ct-swatches');
        function paint() {
            prev.replaceChildren(labelBar({ name: st.name, color: st.color }, 'ct-lab is-big'));
            grid.querySelectorAll('button').forEach(function (b) { b.classList.toggle('is-on', b.dataset.c === st.color); });
        }
        C.labelColors.forEach(function (c) {
            var b = h('button'); b.type = 'button'; b.dataset.c = c; b.style.background = c; b.setAttribute('aria-label', c);
            b.addEventListener('click', function () { st.color = c; paint(); });
            grid.appendChild(b);
        });
        name.addEventListener('input', function () { st.name = name.value; paint(); });
        paint();
        var save = ctBtn(l ? T.cb_save : T.ct_create, 'is-main', function () {
            save.disabled = true;
            var body = { name: st.name.trim() || null, color: st.color };
            (l ? api('PATCH', url('label', l.uuid), body) : api('POST', U.labels, body)).then(function (r) {
                gotLabels(r.labels);
                if (!l && r.label && uuid) toggleLabel(uuid, r.label, true);
                p.back();
            }).catch(function (err) { save.disabled = false; fail(err); });
        });
        wrap.append(prev, field, emo.box, ctHead(T.ct_colour), grid, save);
        if (l) {
            var del = ctBtn(T.cb_delete, 'is-danger');
            sure(del, function () { api('DELETE', url('label', l.uuid)).then(function (r) { gotLabels(r.labels); p.back(); }).catch(fail); });
            wrap.append(del, h('p', 'ct-hint', T.ct_label_delete_hint));
        }
        return wrap;
    }
    /* The board's labels changed: every card shows the new name and colour (or loses a deleted one). */
    function gotLabels(labels) {
        S.labels = labels;
        var by = {}; labels.forEach(function (l) { by[l.uuid] = l; });
        (S.cards || []).forEach(function (n) { n.labels = (n.labels || []).filter(function (x) { return by[x.uuid]; }).map(function (x) { return by[x.uuid]; }); });
        draw(); if (P && P.page && P.isCard) P.page.setMeta({});
    }

    /* ── Members ─────────────────────────────────────────────────────── */
    function membersPop(anchor, uuid) {
        var q = '';
        ctPop(anchor, T.ct_members, function () {
            var wrap = h('div', 'ct-col'), list = h('div', 'ct-list');
            wrap.append(ctSearch(T.ct_search_members, q, function (v) { q = v; rows(); }), list);
            function rows() {
                var n = find(uuid); if (!n) return;
                var ids = (n.members || []).map(function (m) { return m.id; });
                var match = function (pp) { return !q || pp.name.toLowerCase().indexOf(q) !== -1; };
                var on = S.people.filter(function (pp) { return ids.indexOf(pp.id) !== -1 && match(pp); });
                var off = S.people.filter(function (pp) { return ids.indexOf(pp.id) === -1 && match(pp); });
                var parts = [];
                if (on.length) parts.push(ctHead(T.ct_card_members));
                on.forEach(function (pp) { parts.push(personRow(pp, true)); });
                if (off.length) parts.push(ctHead(T.ct_board_members));
                off.forEach(function (pp) { parts.push(personRow(pp, false)); });
                list.replaceChildren.apply(list, parts);
            }
            function personRow(pp, on) {
                var b = h('button', 'ct-prow' + (on ? ' is-on' : '')); b.type = 'button';
                b.append(avatar(pp), h('span', null, pp.id === S.me.id ? pp.name + ' (' + T.tn_me + ')' : pp.name), icon(on ? 'bi-x-lg' : 'bi-plus-lg'));
                b.addEventListener('click', function () { toggleMember(uuid, pp, !on); rows(); });
                return b;
            }
            rows();
            return wrap;
        }, { uuid: uuid });
    }
    function toggleMember(uuid, pp, on) {
        var n = find(uuid); if (!n) return;
        n.members = on ? (n.members || []).concat([pp]) : (n.members || []).filter(function (m) { return m.id !== pp.id; });
        draw(); if (P && P.uuid === uuid) P.page.setMeta({});
        sendTool('cardMembers', uuid, { user: pp.id, on: on });
    }

    /* ── Dates: a start, a due day and time, a reminder ─────────────── */
    function remText(m) {
        if (m === null) return T.ct_rem_none;
        if (m === 0) return T.ct_rem_at;
        if (m < 60) return t('ct_rem_min', { n: m });
        return m < 1440 ? t('ct_rem_hour', { n: m / 60 }) : t('ct_rem_day', { n: m / 1440 });
    }
    function datesPop(anchor, uuid) {
        var n = find(uuid); if (!n) return;
        var st = {
            start: n.start_on, startOn: !!n.start_on,
            due: n.due_on || iso(addDays(1)), dueOn: !!n.due_on || !n.start_on,
            time: n.due_time || '', reminder: n.due_on ? (n.reminder != null ? n.reminder : null) : 1440,
            focus: n.start_on && !n.due_on ? 'start' : 'due'
        };
        var month = fromIso(st.focus === 'start' ? st.start : st.due); month.setDate(1);
        ctPop(anchor, T.ct_dates, function (p) {
            var wrap = h('div', 'ct-col');
            var cal = h('div', 'ss-cal ct-cal');
            var top = h('div', 'top');
            var prev = h('button'); prev.type = 'button'; prev.setAttribute('aria-label', T.tn_prev_month); prev.appendChild(icon('bi-chevron-left flip'));
            var next = h('button'); next.type = 'button'; next.setAttribute('aria-label', T.tn_next_month); next.appendChild(icon('bi-chevron-right flip'));
            prev.addEventListener('click', function () { month.setMonth(month.getMonth() - 1); p.render(); });
            next.addEventListener('click', function () { month.setMonth(month.getMonth() + 1); p.render(); });
            top.append(prev, h('span', null, month.toLocaleDateString(C.locale, { month: 'long', year: 'numeric' })), next);
            var grid = h('div', 'grid');
            var monday = new Date(2024, 0, 1);
            for (var i = 0; i < 7; i++) { var d0 = new Date(monday); d0.setDate(1 + i); grid.appendChild(h('span', null, d0.toLocaleDateString(C.locale, { weekday: 'narrow' }))); }
            var lead = (month.getDay() + 6) % 7, days = new Date(month.getFullYear(), month.getMonth() + 1, 0).getDate(), today = iso(addDays(0));
            for (var k = 0; k < lead; k++) grid.appendChild(h('span'));
            var lo = st.startOn ? st.start : null, hi = st.dueOn ? st.due : null;
            for (var dd = 1; dd <= days; dd++) {
                var day = new Date(month.getFullYear(), month.getMonth(), dd), v = iso(day);
                var b = h('button', null, String(dd)); b.type = 'button';
                if (v === today) b.classList.add('is-today');
                if (v === lo || v === hi) b.classList.add('is-on');
                if (lo && hi && v > lo && v < hi) b.classList.add('is-range');
                b.setAttribute('aria-label', day.toLocaleDateString(C.locale, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }));
                (function (val) { b.addEventListener('click', function () {
                    if (st.focus === 'start') { st.start = val; st.startOn = true; if (st.dueOn && st.due < val) st.due = val; }
                    else { st.due = val; st.dueOn = true; if (st.startOn && st.start > val) st.start = val; }
                    p.render();
                }); })(v);
                grid.appendChild(b);
            }
            cal.append(top, grid);
            wrap.appendChild(cal);

            function dateRow(label, key, onKey) {
                var r = h('div', 'ct-drow' + (st.focus === key && st[onKey] ? ' is-focus' : ''));
                var cb = h('button', 'ct-check' + (st[onKey] ? ' is-on' : '')); cb.type = 'button'; cb.setAttribute('role', 'checkbox'); cb.setAttribute('aria-checked', String(st[onKey])); cb.setAttribute('aria-label', label);
                cb.appendChild(icon('bi-check-lg'));
                cb.addEventListener('click', function () { st[onKey] = !st[onKey]; if (st[onKey]) { st.focus = key; if (!st[key]) st[key] = iso(addDays(key === 'start' ? 0 : 1)); } p.render(); });
                var day = h('button', 'ct-dval', st[onKey] && st[key] ? fmtDay(st[key], true) : T.ct_pick_day); day.type = 'button';
                day.disabled = !st[onKey];
                day.addEventListener('click', function () { st.focus = key; var m = fromIso(st[key]); month = new Date(m.getFullYear(), m.getMonth(), 1); p.render(); });
                r.append(h('span', 'ct-dl', label), cb, day);
                return r;
            }
            wrap.appendChild(dateRow(T.ct_start_date, 'start', 'startOn'));
            var dueRow = dateRow(T.ct_due_date, 'due', 'dueOn');
            var time = h('input', 'ct-time'); time.type = 'text'; time.inputMode = 'numeric'; time.maxLength = 5; time.placeholder = '09:00'; time.value = st.time;
            time.setAttribute('aria-label', T.ct_time); time.disabled = !st.dueOn;
            time.addEventListener('input', function () {
                var v = time.value.replace(/[^\d:]/g, '');
                if (/^\d{3,4}$/.test(v)) v = v.slice(0, v.length - 2) + ':' + v.slice(-2);
                time.value = v; st.time = v;
            });
            dueRow.appendChild(time);
            wrap.appendChild(dueRow);

            wrap.appendChild(ctHead(T.ct_reminder));
            var rem = h('div', 'ct-pills');
            REMINDERS.forEach(function (m) {
                var b = h('button', 'ct-pill' + (st.reminder === m ? ' is-on' : ''), remText(m));
                b.type = 'button'; b.disabled = !st.dueOn;
                b.addEventListener('click', function () { st.reminder = m; p.render(); });
                rem.appendChild(b);
            });
            wrap.append(rem, h('p', 'ct-hint', T.ct_rem_hint));

            var save = ctBtn(T.cb_save, 'is-main', function () {
                var tm = st.dueOn && st.time ? st.time : null;
                if (tm && tm.length === 4) tm = '0' + tm;
                if (tm && !/^([01]\d|2[0-3]):[0-5]\d$/.test(tm)) { toast(T.ct_bad_time, { error: true }); time.focus(); return; }
                save.disabled = true;
                sendTool('cardDates', uuid, { start: st.startOn ? st.start : null, due: st.dueOn ? st.due : null, time: tm, reminder: st.dueOn ? st.reminder : null }).then(ctClose);
            });
            var remove = ctBtn(T.ct_remove_dates, null, function () { sendTool('cardDates', uuid, { start: null, due: null, time: null, reminder: null }).then(ctClose); });
            wrap.append(save, remove);
            return wrap;
        });
    }

    /* ── Cover: a colour or a picture, half or full ──────────────────── */
    function coverPop(anchor, uuid) {
        var pics = null;
        api('GET', url('cardShow', uuid)).then(function (r) {
            pics = (r.card.attachment_list || []).filter(function (a) { return a.image; });
            if (POP && POP.uuid === uuid) POP.render();
        }).catch(function () { pics = []; });
        ctPop(anchor, T.ct_cover, function () {
            var n = find(uuid) || {}, has = !!(n.cover || n.cover_color), size = n.cover_size || 'half';
            var wrap = h('div', 'ct-col');
            wrap.appendChild(ctHead(T.ct_size));
            var sizes = h('div', 'ct-sizes');
            ['half', 'full'].forEach(function (s) {
                var b = h('button', 'ct-size is-' + s + (has && size === s ? ' is-on' : '')); b.type = 'button'; b.disabled = !has;
                b.setAttribute('aria-label', s === 'full' ? T.ct_size_full : T.ct_size_half);
                var face = h('span', 'face'); if (n.cover_color) face.style.background = n.cover_color;
                b.append(face, h('i'), h('i'));
                b.addEventListener('click', function () { sendTool('cardCoverStyle', uuid, { color: n.cover_color || null, size: s }); });
                sizes.appendChild(b);
            });
            wrap.appendChild(sizes);
            if (has) wrap.appendChild(ctBtn(T.ct_cover_remove, 'is-wide', function () {
                var go = n.cover ? api('POST', url('cardCover', uuid), { uuid: null }) : Promise.resolve();
                go.then(function () { return sendTool('cardCoverStyle', uuid, { color: null, size: null }); }).catch(fail);
            }));
            wrap.appendChild(ctHead(T.ct_colours));
            var sw = h('div', 'ct-swatches is-cover');
            C.labelColors.slice(10).forEach(function (c) {
                var b = h('button'); b.type = 'button'; b.style.background = c; b.setAttribute('aria-label', c);
                if (n.cover_color === c) b.classList.add('is-on');
                b.addEventListener('click', function () { sendTool('cardCoverStyle', uuid, { color: c, size: size }); });
                sw.appendChild(b);
            });
            wrap.appendChild(sw);
            wrap.appendChild(ctHead(T.ct_pictures));
            if (pics === null) wrap.appendChild(h('p', 'ct-hint', T.tn_loading));
            else if (!pics.length) wrap.appendChild(h('p', 'ct-hint', T.ct_no_pictures));
            else {
                var pg = h('div', 'ct-pics');
                pics.forEach(function (a) {
                    var b = h('button'); b.type = 'button'; b.setAttribute('aria-label', a.name);
                    if (n.cover === a.uuid) b.classList.add('is-on');
                    var im = h('img'); im.alt = ''; im.loading = 'lazy'; im.src = url2('cardFile', uuid, a.uuid) + '?preview=1';
                    b.appendChild(im);
                    b.addEventListener('click', function () {
                        api('POST', url('cardCover', uuid), { uuid: a.uuid }).then(function (r) { touched(r.note); }).catch(fail);
                    });
                    pg.appendChild(b);
                });
                wrap.appendChild(pg);
            }
            return wrap;
        }, { uuid: uuid });
    }

    /* ── Move: a list, and a place in it ─────────────────────────────── */
    function inLaneCards(lane, except) {
        return (S.cards || []).filter(function (c) { return !c.archived && c.lane && c.lane.uuid === lane && c.uuid !== except; })
            .sort(function (a, b) { return a.position - b.position; });
    }
    function movePop(anchor, uuid) {
        var n = find(uuid); if (!n || !S.lanes.length) return;
        var st = { lane: n.lane ? n.lane.uuid : S.lanes[0].uuid, at: null };
        ctPop(anchor, T.ct_move, function () {
            var wrap = h('div', 'ct-col');
            wrap.appendChild(ctHead(T.ct_list));
            var lanes = h('div', 'ct-list');
            S.lanes.forEach(function (lane) {
                var b = h('button', 'ss-opt' + (lane.uuid === st.lane ? ' is-on' : '')); b.type = 'button'; b.style.setProperty('--c', lane.color);
                b.append(h('i'), document.createTextNode(lane.name), h('em', null, lane.uuid === (n.lane && n.lane.uuid) ? T.ct_current : ''));
                b.addEventListener('click', function () { st.lane = lane.uuid; st.at = null; POP.render(); });
                lanes.appendChild(b);
            });
            wrap.appendChild(lanes);
            var others = inLaneCards(st.lane, uuid);
            var mine = n.lane && n.lane.uuid === st.lane ? inLaneCards(st.lane, null).findIndex(function (c) { return c.uuid === uuid; }) : -1;
            if (st.at === null) st.at = mine >= 0 ? mine : others.length;
            wrap.appendChild(ctHead(T.ct_position));
            var pos = h('div', 'ct-pills');
            for (var i = 0; i <= others.length; i++) {
                (function (k) {
                    var b = h('button', 'ct-pill' + (k === st.at ? ' is-on' : ''), String(k + 1) + (k === mine ? ' · ' + T.ct_current : '')); b.type = 'button';
                    b.addEventListener('click', function () { st.at = k; POP.render(); });
                    pos.appendChild(b);
                })(i);
            }
            wrap.appendChild(pos);
            wrap.appendChild(ctBtn(T.ct_move_go, 'is-main', function () {
                var above = others[st.at - 1] || null, below = others[st.at] || null, lane = S.lanes.find(function (l) { return l.uuid === st.lane; });
                api('POST', url('place', uuid), { lane: st.lane, above: above ? above.uuid : null, below: below ? below.uuid : null }).then(function (r) {
                    ctClose(); touched(r.note); toast(t('tn_moved', { lane: lane.name }));
                }).catch(fail);
            }));
            return wrap;
        });
    }

    /* ── Copy (and a card from a template) ───────────────────────────── */
    function copyPop(anchor, uuid, o) {
        o = o || {};
        var n = find(uuid); if (!n || !S.lanes.length) return;
        var st = { title: n.title || '', lane: o.lane || (n.lane ? n.lane.uuid : S.lanes[0].uuid), where: o.lane ? 'bottom' : 'top',
            keep: { checklists: true, labels: true, members: true, attachments: true } };
        ctPop(anchor, o.template ? T.ct_from_template : T.ct_copy, function () {
            var wrap = h('div', 'ct-col');
            var f = h('label', 'ct-f'); var ta = h('textarea'); ta.rows = 2; ta.maxLength = 200; ta.value = st.title;
            ta.addEventListener('input', function () { st.title = ta.value; });
            f.append(h('span', null, T.ct_title), ta);
            wrap.appendChild(f);
            var keeps = [['checklists', (n.steps || [0, 0])[1]], ['labels', (n.labels || []).length], ['members', (n.members || []).length], ['attachments', n.attachments || 0]].filter(function (k) { return k[1]; });
            if (keeps.length) {
                wrap.appendChild(ctHead(T.ct_keep));
                keeps.forEach(function (k) {
                    var r = h('button', 'ct-krow'); r.type = 'button';
                    var cb = h('span', 'ct-check' + (st.keep[k[0]] ? ' is-on' : '')); cb.appendChild(icon('bi-check-lg'));
                    r.append(cb, h('span', null, T['ct_keep_' + k[0]] + ' (' + k[1] + ')'));
                    r.addEventListener('click', function () { st.keep[k[0]] = !st.keep[k[0]]; POP.render(); });
                    wrap.appendChild(r);
                });
            }
            wrap.appendChild(ctHead(T.ct_copy_to));
            var lanes = h('div', 'ct-pills');
            S.lanes.forEach(function (lane) {
                var b = h('button', 'ct-pill' + (lane.uuid === st.lane ? ' is-on' : ''), lane.name); b.type = 'button';
                b.addEventListener('click', function () { st.lane = lane.uuid; POP.render(); });
                lanes.appendChild(b);
            });
            wrap.appendChild(lanes);
            var where = h('div', 'ct-pills');
            [['top', T.ct_top], ['bottom', T.ct_bottom]].forEach(function (w) {
                var b = h('button', 'ct-pill' + (w[0] === st.where ? ' is-on' : ''), w[1]); b.type = 'button';
                b.addEventListener('click', function () { st.where = w[0]; POP.render(); });
                where.appendChild(b);
            });
            wrap.appendChild(where);
            var go = ctBtn(o.template ? T.ct_create : T.ct_copy_go, 'is-main', function () {
                var title = ta.value.replace(/\s+/g, ' ').trim(); if (!title) { ta.focus(); return; }
                go.disabled = true;
                api('POST', url('cardCopy', uuid), { title: title, lane: st.lane, where: st.where, keep: Object.keys(st.keep).filter(function (k) { return st.keep[k]; }) }).then(function (r) {
                    ctClose(); upsert(r.note, true); buzz(10);
                    toast(T.ct_copied, { action: T.tn_open_card, onAction: function () { openNote(r.note.uuid, { card: true }); } });
                }).catch(function (err) { go.disabled = false; fail(err); });
            });
            wrap.appendChild(go);
            return wrap;
        });
    }

    /* ── Share: the card's own link ──────────────────────────────────── */
    function cardLink(uuid) { return U.app + '?card=' + uuid; }
    function copyLink(uuid) {
        if (navigator.clipboard && window.isSecureContext) navigator.clipboard.writeText(cardLink(uuid)).then(function () { toast(T.ct_link_copied); }, function () { sharePop(null, uuid); });
        else sharePop(null, uuid);
    }
    function sharePop(anchor, uuid) {
        ctPop(anchor, T.ct_share, function () {
            var wrap = h('div', 'ct-col');
            var inp = h('input', 'ct-in'); inp.type = 'text'; inp.readOnly = true; inp.value = cardLink(uuid); inp.setAttribute('aria-label', T.ct_share);
            inp.addEventListener('focus', function () { inp.select(); });
            wrap.append(h('p', 'ct-hint', T.ct_share_hint), inp, ctBtn(T.ct_copy_link, 'is-main', function () {
                inp.select();
                if (navigator.clipboard && window.isSecureContext) { navigator.clipboard.writeText(inp.value).then(function () { toast(T.ct_link_copied); }); return; }
                var ok = false; try { ok = document.execCommand('copy'); } catch (e) {}
                if (ok) toast(T.ct_link_copied);
            }));
            return wrap;
        });
    }

    /* ── Archive, complete, watch, template ──────────────────────────── */
    function archiveCard(uuid, on) {
        return api('POST', url('cardArchive', uuid), { on: on }).then(function (r) {
            if (on) {
                if (P && P.uuid === uuid) P.page.close();
                S.cards = (S.cards || []).filter(function (c) { return c.uuid !== uuid; }); draw();
                toast(T.ct_archived_one, { action: T.undo, onAction: function () { archiveCard(uuid, false); } });
            } else { touched(r.note); toast(T.ct_unarchived); }
            return r;
        }).catch(fail);
    }
    function setDone(uuid, on) {
        var n = find(uuid); if (!n) return;
        n.done = on; draw(); if (P && P.uuid === uuid) P.page.setMeta({});
        buzz(10);
        sendTool('cardComplete', uuid, { done: on });
    }
    function setWatch(uuid, on) {
        var n = find(uuid); if (!n) return;
        n.watchers = on ? (n.watchers || []).concat([S.me.id]) : (n.watchers || []).filter(function (i) { return i !== S.me.id; });
        draw(); if (P && P.uuid === uuid) P.page.setMeta({});
        sendTool('cardWatch', uuid, { on: on }).then(function () { toast(on ? T.ct_watching_now : T.ct_unwatched); });
    }
    function setTemplate(uuid) {
        var n = find(uuid); if (!n) return;
        var on = !n.template;
        sendTool('cardTemplate', uuid, { on: on }).then(function () { toast(on ? T.ct_template_on : T.ct_template_off); });
    }

    /* ── the quick-edit menu: the pencil, a right-click, or E ───────── */
    function quickMenu(anchor, uuid) {
        var n = find(uuid); if (!n) return;
        ctPop(anchor, n.title || T.note_untitled, function () {
            var m = find(uuid) || n;
            var wrap = h('div', 'ct-menu');
            [['bi-window', T.tn_open_card, function () { ctClose(); openNote(uuid, { card: true }); }],
             ['bi-tag', T.ct_labels, function () { labelsPop(anchor, uuid); }, 'L'],
             ['bi-person', T.ct_members, function () { membersPop(anchor, uuid); }, 'M'],
             ['bi-card-image', T.ct_cover, function () { coverPop(anchor, uuid); }],
             ['bi-clock', T.ct_dates, function () { datesPop(anchor, uuid); }, 'D'],
             ['bi-arrow-right', T.ct_move, function () { movePop(anchor, uuid); }],
             ['bi-copy', T.ct_copy, function () { copyPop(anchor, uuid); }],
             ['bi-link-45deg', T.ct_copy_link, function () { ctClose(); copyLink(uuid); }],
             ['bi-card-heading', m.template ? T.ct_template_stop : T.ct_template_make, function () { ctClose(); setTemplate(uuid); }],
             ['bi-archive', T.ct_archive, function () { ctClose(); archiveCard(uuid, true); }, 'C']
            ].forEach(function (d) {
                var b = h('button', 'ct-mrow'); b.type = 'button';
                b.append(icon(d[0]), h('span', null, d[1]));
                if (d[3] && DESK) b.appendChild(h('kbd', null, d[3]));
                b.addEventListener('click', d[2]);
                wrap.appendChild(b);
            });
            return wrap;
        }, { uuid: uuid });
    }

    /* ── the board's own tools: Filter, Archived items ───────────────── */
    function filterCount() { var f = S.bf; return (f.q ? 1 : 0) + f.members.length + f.labels.length + (f.due ? 1 : 0) + (f.done ? 1 : 0); }
    function shownOnBoard(n) {
        var f = S.bf;
        if (n.archived) return false;
        if (f.q && ((n.title || '') + ' ' + (n.excerpt || '') + ' ' + (n.labels || []).map(function (l) { return l.name || ''; }).join(' ')).toLowerCase().indexOf(f.q) === -1) return false;
        if (f.members.length) {
            var ids = (n.members || []).map(function (m) { return m.id; });
            if (!f.members.some(function (k) { return k === 'none' ? !ids.length : ids.indexOf(k) !== -1; })) return false;
        }
        if (f.labels.length) {
            var ls = (n.labels || []).map(function (l) { return l.uuid; });
            if (!f.labels.some(function (k) { return k === 'none' ? !ls.length : ls.indexOf(k) !== -1; })) return false;
        }
        if (f.done === 'yes' && !n.done) return false;
        if (f.done === 'no' && n.done) return false;
        if (f.due) {
            var m = dueMoment(n), now = new Date();
            if (f.due === 'none') { if (m) return false; }
            else if (!m) return false;
            else if (f.due === 'overdue') { if (!(m < now) || n.done) return false; }
            else { var span = { day: 1, week: 7, month: 30 }[f.due]; if (m < now || m - now > span * 86400000) return false; }
        }
        return true;
    }
    function filterPop(anchor) {
        ctPop(anchor, T.ct_filter, function (p) {
            var f = S.bf, wrap = h('div', 'ct-col');
            wrap.append(ctHead(T.ct_keyword), ctSearch(T.ct_keyword_ph, f.q, function (v) { f.q = v; draw(); syncFilter(); }));
            function opt(text, on, fn, lead) {
                var b = h('button', 'ct-krow'); b.type = 'button';
                var cb = h('span', 'ct-check' + (on ? ' is-on' : '')); cb.appendChild(icon('bi-check-lg'));
                b.appendChild(cb); if (lead) b.appendChild(lead); if (text) b.appendChild(h('span', null, text));
                b.addEventListener('click', function () { fn(); draw(); syncFilter(); p.render(); });
                return b;
            }
            function flip(list, v) { var i = list.indexOf(v); if (i === -1) list.push(v); else list.splice(i, 1); }
            wrap.appendChild(ctHead(T.ct_members));
            wrap.appendChild(opt(T.ct_no_members, f.members.indexOf('none') !== -1, function () { flip(f.members, 'none'); }));
            S.people.forEach(function (pp) { wrap.appendChild(opt(pp.id === S.me.id ? T.ct_assigned_me : pp.name, f.members.indexOf(pp.id) !== -1, function () { flip(f.members, pp.id); }, avatar(pp))); });
            wrap.appendChild(ctHead(T.ct_card_status));
            wrap.appendChild(opt(T.ct_done_yes, f.done === 'yes', function () { f.done = f.done === 'yes' ? '' : 'yes'; }));
            wrap.appendChild(opt(T.ct_done_no, f.done === 'no', function () { f.done = f.done === 'no' ? '' : 'no'; }));
            wrap.appendChild(ctHead(T.ct_due));
            [['none', T.ct_due_none], ['overdue', T.ct_due_overdue], ['day', T.ct_due_day], ['week', T.ct_due_week], ['month', T.ct_due_month]].forEach(function (d) {
                wrap.appendChild(opt(d[1], f.due === d[0], function () { f.due = f.due === d[0] ? '' : d[0]; }));
            });
            wrap.appendChild(ctHead(T.ct_labels));
            wrap.appendChild(opt(T.ct_no_labels, f.labels.indexOf('none') !== -1, function () { flip(f.labels, 'none'); }));
            (S.labels || []).forEach(function (l) { wrap.appendChild(opt('', f.labels.indexOf(l.uuid) !== -1, function () { flip(f.labels, l.uuid); }, labelBar(l, 'ct-lab is-fill'))); });
            if (filterCount()) wrap.appendChild(ctBtn(T.ct_clear_filters, 'is-wide', function () { S.bf = { q: '', members: [], labels: [], due: '', done: '' }; draw(); syncFilter(); p.render(); }));
            return wrap;
        });
    }
    function syncFilter() {
        var b = document.querySelector('.tb-filter'); if (!b) return;
        var n = filterCount();
        b.classList.toggle('is-on', !!n);
        b.lastChild.textContent = n ? t('ct_filters_n', { n: n }) : T.ct_filter;
    }
    function archivedPop(anchor) {
        var list = null, q = '';
        api('GET', U.archived).then(function (r) { list = r.cards; if (POP) POP.render(); }).catch(function (err) { list = []; fail(err); });
        ctPop(anchor, T.ct_archived, function () {
            var wrap = h('div', 'ct-col'), box = h('div', 'ct-list');
            wrap.append(ctSearch(T.ct_search_archive, q, function (v) { q = v; rows(); }), box);
            function rows() {
                if (list === null) { box.replaceChildren(h('p', 'ct-hint', T.tn_loading)); return; }
                var shown = list.filter(function (n) { return !q || (n.title || '').toLowerCase().indexOf(q) !== -1; });
                if (!shown.length) { box.replaceChildren(h('p', 'ct-hint', T.ct_none_archived)); return; }
                box.replaceChildren.apply(box, shown.map(function (n) {
                    var r = h('div', 'ct-arow');
                    var open = h('button', 'ct-atitle'); open.type = 'button';
                    open.append(h('b', null, n.title || T.note_untitled), h('span', null, n.lane ? n.lane.name : ''));
                    open.addEventListener('click', function () { ctClose(); openNote(n.uuid, { card: true }); });
                    var back = ctBtn(T.ct_restore, null, function () { archiveCard(n.uuid, false).then(function () { list = list.filter(function (x) { return x.uuid !== n.uuid; }); rows(); }); });
                    var del = ctBtn(T.cb_delete, 'is-danger');
                    sure(del, function () {
                        api('DELETE', url('destroy', n.uuid)).then(function () { list = list.filter(function (x) { return x.uuid !== n.uuid; }); rows(); toast(T.note_binned); }).catch(fail);
                    });
                    var acts = h('div', 'ct-aacts'); acts.append(back, del);
                    r.append(open, acts);
                    return r;
                }));
            }
            rows();
            return wrap;
        });
    }
    /* A list's foot: a card from one of the board's templates. */
    function templatesPop(anchor, lane) {
        ctPop(anchor, T.ct_templates, function () {
            var wrap = h('div', 'ct-col');
            var list = (S.cards || []).filter(function (c) { return c.template && !c.archived; });
            if (!list.length) wrap.appendChild(h('p', 'ct-hint', T.ct_no_templates));
            list.forEach(function (c) {
                var b = h('button', 'ct-tpl'); b.type = 'button';
                if (c.cover_color) b.style.setProperty('--cc', c.cover_color);
                var lb = h('span', 'ct-tlabels'); (c.labels || []).forEach(function (l) { var s = h('i'); s.style.background = l.color; lb.appendChild(s); });
                b.append(lb, h('b', null, c.title || T.note_untitled));
                b.addEventListener('click', function () { copyPop(anchor, c.uuid, { template: true, lane: lane.uuid }); });
                wrap.appendChild(b);
            });
            return wrap;
        });
    }

    /* ── Trello's shortcuts, on the board: hover a card and press a key ── */
    var hovered = null;
    document.addEventListener('mouseover', function (e) { if (!e.target.closest) return; hovered = e.target.closest('.tc[data-uuid]') || null; });
    document.addEventListener('keydown', function (e) {
        if (S.view !== 'board' || P || POP || e.ctrlKey || e.metaKey || e.altKey) return;
        if (e.target.closest && e.target.closest('input, textarea, [contenteditable=true], .ss')) return;
        var el = hovered && hovered.isConnected ? hovered : (document.activeElement && document.activeElement.closest ? document.activeElement.closest('.tc[data-uuid]') : null);
        if (!el) return;
        var uuid = el.dataset.uuid, n = find(uuid); if (!n) return;
        var go = { l: function () { labelsPop(el, uuid); }, d: function () { datesPop(el, uuid); }, m: function () { membersPop(el, uuid); },
            e: function () { quickMenu(el, uuid); }, c: function () { archiveCard(uuid, true); },
            ' ': function () { toggleMember(uuid, S.me, !hasMe(n.members)); } }[e.key.toLowerCase()];
        if (!go) return;
        e.preventDefault(); go();
    });
