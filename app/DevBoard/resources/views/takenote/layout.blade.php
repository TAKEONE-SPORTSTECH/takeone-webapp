{{--
    The notebook laid out the way the notes apps keep notes (owner, 28 Oct
    2026: "see other apps how the notes are visible and how they behave").
    Included INSIDE takenote/script's closure, after folders and map.

    The list: compact rows grouped by when — Pinned, Today, Yesterday,
    Previous 7 days, Previous 30 days, then by month. A smart list (Pinned,
    Mine, From others) and a folder are two ways of choosing what it shows.

    A desk: three columns — the folders, the list, the note. The folder column
    folds down to a strip of icons (Ctrl+\ or ⌘\), remembered per device.

    A phone: Apple Notes' drill-down. The Folders screen (the views as tiles,
    the smart lists and the folders as grouped rows) → the list of one of them
    → the note. Going into a list is a history step, so the phone's own back
    gesture comes out again (a note's page sits on top of it, partials/page).
    A row swiped towards the edge shows Pin · Move · Bin.
--}}
    /* ── the notebook's layout ───────────────────────────────────────── */
    var justSwiped = 0;
    var $title = document.getElementById('view-title');
    S.screen = 'home';
    if (!DESK) document.body.dataset.screen = 'home';

    function dayDiff(iso) {
        var d = new Date(iso), now = new Date();
        var day = function (x) { return new Date(x.getFullYear(), x.getMonth(), x.getDate()).getTime(); };
        return Math.round((day(now) - day(d)) / 864e5);
    }
    /* what a row says for when: 21:12 · Yesterday · Sat · 12 Oct (day before month, Design Rule #9) */
    function rowDate(iso) {
        if (!iso) return '';
        var d = new Date(iso), diff = dayDiff(iso);
        if (diff <= 0) return d.toLocaleTimeString(C.locale, { hour: '2-digit', minute: '2-digit' });
        if (diff === 1) return T.nb_yesterday;
        if (diff < 7) return d.toLocaleDateString(C.locale, { weekday: 'short' });
        var o = { day: 'numeric', month: 'short' };
        if (d.getFullYear() !== new Date().getFullYear()) o.year = 'numeric';
        return d.toLocaleDateString(C.locale, o);
    }
    function groupOf(n) {
        if (n.pinned) return { key: 'pin', label: T.nb_pinned };
        if (!n.updated_at) return { key: 'old', label: '' };
        var diff = dayDiff(n.updated_at);
        if (diff <= 0) return { key: 'today', label: T.nb_today };
        if (diff === 1) return { key: 'yesterday', label: T.nb_yesterday };
        if (diff < 7) return { key: 'week', label: T.nb_week };
        if (diff < 30) return { key: 'month', label: T.nb_month };
        var d = new Date(n.updated_at), o = { month: 'long' };
        if (d.getFullYear() !== new Date().getFullYear()) o.year = 'numeric';
        return { key: d.getFullYear() + '-' + d.getMonth(), label: d.toLocaleDateString(C.locale, o) };
    }
    /* the list's rows under their headings; pinned notes first, the rest in the order they came */
    function grouped(list) {
        var out = [], key = null, prev = null;
        var ordered = list.filter(function (n) { return n.pinned; }).concat(list.filter(function (n) { return !n.pinned; }));
        ordered.forEach(function (n, i) {
            var g = groupOf(n), row = tile(n, i);
            if (g.key !== key) {
                if (prev) prev.classList.add('is-last');
                key = g.key;
                if (g.label) out.push(h('p', 'grp', g.label));
                row.classList.add('is-first');
            }
            out.push(row); prev = row;
        });
        if (prev) prev.classList.add('is-last');
        return out;
    }

    /* Pin: the note goes to the top of the list. Not an edit — it keeps its place in time. */
    function pinNote(uuid) {
        var n = find(uuid);
        if (!n) return;
        var want = !n.pinned;
        n.pinned = want; draw();
        api('POST', url('pin', uuid), { pinned: want }).then(function (r) {
            upsert(summaryOf(r.note), true);
            toast(want ? T.nb_pinned_toast : T.nb_unpinned_toast);
        }).catch(function (err) { n.pinned = !want; draw(); fail(err); });
    }
    /* Bin from the list (a swiped row): the same bin as the note's own menu, with its Undo */
    function binFromList(uuid) {
        drop(uuid);
        api('DELETE', url('destroy', uuid)).then(function () {
            toast(T.note_binned, { action: T.undo, onAction: function () {
                api('POST', url('restore', uuid)).then(function (r) { upsert(summaryOf(r.note)); toast(T.restored); }).catch(fail);
            } });
        }).catch(function (err) { load(); fail(err); });
    }

    /* what the list is showing, in words */
    function placeName() {
        if (S.view === 'board') return T.tn_view_board;
        if (S.view === 'map') return T.tn_view_map;
        if (S.filter === 'pinned') return T.nb_pinned;
        if (S.filter === 'mine') return T.filter_mine;
        if (S.filter === 'others') return T.tn_filter_others;
        if (S.folder === 'none') return T.fo_none;
        var f = folderOf(S.folder);
        return f ? f.name : T.tn_notes;
    }
    /* after every draw: the headings, the count, which way back */
    function afterDraw(list) {
        var f = S.view === 'notes' && S.filter === 'all' ? folderOf(S.folder) : null;
        var count = list.length === 1 ? T.nb_one : t('nb_many', { n: list.length });
        var name = placeName();
        if (DESK) {
            $title.textContent = name;
            var sub = document.getElementById('list-sub');
            if (sub) sub.textContent = (f && f.parent ? pathOf(f.uuid).map(function (x) { return x.name; }).join(' › ') + ' · ' : '') + count;
            // "Notes" in the folder column lights only when the whole notebook is showing
            document.querySelectorAll('#seg button').forEach(function (b) {
                var on = b.dataset.view === S.view && (S.view !== 'notes' || (S.folder === 'all' && S.filter === 'all'));
                b.classList.toggle('is-on', on); b.setAttribute('aria-selected', String(on));
            });
            return;
        }
        var home = S.screen === 'home';
        $title.textContent = home ? 'TakeNote' : name;
        $title.classList.toggle('has-folder', !home && !!f);
        $title.style.setProperty('--fc', f ? f.color : 'transparent');
        var parent = f && f.parent ? folderOf(f.parent) : null;
        document.getElementById('nb-back-label').textContent = parent ? parent.name : T.nb_back;
        document.getElementById('nb-folder-more').hidden = !f;
        document.getElementById('nb-count').textContent = S.view === 'notes' ? count : '';
        $folders.hidden = false; // the Folders screen always lists them (drawFolders hides them off the notes view)
        $q.placeholder = !home && S.view === 'notes' ? t('nb_in', { place: name }) : T.tn_search;
    }

    /* ── a phone: Folders → a list → the note ───────────────────────── */
    function setScreen(s) {
        S.screen = s;
        document.body.dataset.screen = s;
        closeSwipe();
        window.scrollTo(0, 0);
        draw();
    }
    function enterList() {
        if (DESK) return;
        if (S.screen !== 'list') history.pushState({ tnList: 1 }, '', location.pathname + location.search);
        setScreen('list');
    }
    function leaveList() {
        if (history.state && history.state.tnList) history.back(); // → popstate → the Folders screen
        else setScreen('home');
    }
    if (!DESK) {
        window.addEventListener('popstate', function (e) {
            if (e.state && e.state.marginPage) return; // a note's page, on top of the list
            if (!(e.state && e.state.tnList) && S.screen === 'list') setScreen('home');
        });
        // the views, the smart lists and the folders each go INTO a list
        document.getElementById('seg').addEventListener('click', function (e) {
            var b = e.target.closest('button[data-view]');
            if (!b) return;
            if (b.dataset.view === 'notes') wholeNotebook();
            enterList();
        });
        document.getElementById('filters').addEventListener('click', function (e) { if (e.target.closest('.filter')) enterList(); });
        document.getElementById('nb-back').addEventListener('click', function () {
            var f = S.view === 'notes' && S.filter === 'all' ? folderOf(S.folder) : null;
            if (f && f.parent) setFolder(f.parent); else leaveList();
        });
        document.getElementById('nb-folder-more').addEventListener('click', function () { var f = folderOf(S.folder); if (f && !(S.space && S.space.folder === f.uuid)) folderSheet(f); }); // the shared folder itself is its owner's
        document.getElementById('nb-new-folder').addEventListener('click', function () { newFolder(null); });
        // typing on the Folders screen searches the whole notebook
        $q.addEventListener('input', function () {
            if (S.screen !== 'home' || !S.q) return;
            S.view = 'notes'; wholeNotebook(); enterList();
        });
    }
    /* "Notes": everything, in no folder and no smart list */
    function wholeNotebook() {
        S.filter = 'all'; S.folder = 'all';
        try { localStorage.setItem('takenote.folder', 'all'); } catch (e) {}
        document.querySelectorAll('.filter').forEach(function (x) { x.classList.toggle('is-on', x.dataset.filter === 'all'); });
    }

    /* ── a phone: a row swiped towards the edge shows Pin · Move · Bin ── */
    var $acts = null, swiped = null, ACTS_W = 192;
    function closeSwipe() {
        if (swiped) { swiped.style.transform = ''; swiped.classList.remove('is-swiped'); swiped = null; }
        if ($acts) { $acts.remove(); $acts = null; }
    }
    function actsFor(row) {
        var n = find(row.dataset.uuid);
        var box = h('div', 'nb-acts');
        box.style.top = row.offsetTop + 'px'; box.style.height = row.offsetHeight + 'px';
        [
            { ic: n && n.pinned ? 'bi-pin-angle' : 'bi-pin-angle-fill', label: n && n.pinned ? T.nb_unpin : T.nb_pin, color: '#e89a1c', go: function (u) { pinNote(u); } },
            { ic: 'bi-folder-symlink', label: T.nb_move, color: '#3b9ae1', go: function (u) { var m = find(u); if (m) moveNoteSheet(m); } },
            { ic: 'bi-trash3', label: T.nb_bin, color: '#d63a5e', go: function (u) { binFromList(u); } }
        ].forEach(function (a) {
            var b = h('button'); b.type = 'button'; b.style.setProperty('--a', a.color);
            b.append(icon(a.ic), document.createTextNode(a.label));
            b.addEventListener('click', function () { var u = row.dataset.uuid; closeSwipe(); a.go(u); });
            box.appendChild(b);
        });
        return box;
    }
    if (!DESK) {
        var sw = null, RTL = document.documentElement.dir === 'rtl';
        $notes.addEventListener('touchstart', function (e) {
            var row = e.target.closest('.tile:not(.is-skeleton)');
            if (swiped && row !== swiped) closeSwipe();
            if (!row || e.touches.length !== 1) { sw = null; return; }
            sw = { row: row, x: e.touches[0].clientX, y: e.touches[0].clientY, on: null, base: row === swiped ? -ACTS_W : 0, dx: 0 };
        }, { passive: true });
        $notes.addEventListener('touchmove', function (e) {
            if (!sw || document.body.classList.contains('is-filing')) return;
            var dx = (e.touches[0].clientX - sw.x) * (RTL ? -1 : 1), dy = e.touches[0].clientY - sw.y;
            if (sw.on === null) {
                if (Math.abs(dx) > 10 && Math.abs(dx) > Math.abs(dy) * 1.3) sw.on = true;
                else if (Math.abs(dy) > 10) { sw = null; return; }
                else return;
                if (!$acts || swiped !== sw.row) { closeSwipe(); $acts = actsFor(sw.row); $notes.appendChild($acts); } // last, so no heading stops being :first-child
                sw.row.classList.remove('is-swiped');
                sw.row.style.animation = 'none'; // the entrance animation would hold the transform
            }
            e.preventDefault();
            sw.dx = Math.max(-ACTS_W - 24, Math.min(0, sw.base + dx));
            sw.row.style.transform = 'translateX(' + (RTL ? -sw.dx : sw.dx) + 'px)';
        }, { passive: false });
        $notes.addEventListener('touchend', function () {
            if (!sw || !sw.on) { sw = null; return; }
            justSwiped = Date.now();
            var row = sw.row, open = sw.dx < -ACTS_W / 2;
            row.classList.add('is-swiped');
            if (open) { swiped = row; row.style.transform = 'translateX(' + (RTL ? ACTS_W : -ACTS_W) + 'px)'; buzz(8); }
            else { row.style.transform = ''; swiped = null; setTimeout(function () { if (!swiped && $acts) { $acts.remove(); $acts = null; } }, 260); }
            sw = null;
        });
    }

    /* ── signing out: the open note saves first, then the session ends ── */
    document.querySelectorAll('form[data-signout]').forEach(function (f) {
        f.addEventListener('submit', function (e) {
            if (f.dataset.going) return;
            e.preventDefault();
            f.dataset.going = '1';
            var st = P, waited = 0;
            if (st && st.page) st.page.close(); // closing a note saves it
            (function go() {
                if (st && st.saver && (st.saver.dirty() || (st.editor && st.editor.pending())) && waited < 6000) { waited += 250; setTimeout(go, 250); return; }
                f.submit();
            })();
        });
    });

    /* ── a desk: the folder column folds to a strip ─────────────────── */
    if (DESK) {
        var railOff = false;
        try { railOff = localStorage.getItem('takenote.rail') === 'off'; } catch (e) {}
        var paintRail = function () {
            document.body.classList.toggle('rail-off', railOff);
            document.querySelectorAll('[data-rail-toggle]').forEach(function (b) {
                var label = railOff ? T.nb_show_folders : T.nb_hide_folders;
                b.setAttribute('aria-label', label); b.title = label + ' (Ctrl+\\)';
                b.setAttribute('aria-expanded', String(!railOff));
            });
        };
        var flipRail = function () { railOff = !railOff; try { localStorage.setItem('takenote.rail', railOff ? 'off' : 'on'); } catch (e) {} paintRail(); };
        document.querySelectorAll('[data-rail-toggle]').forEach(function (b) { b.addEventListener('click', flipRail); });
        document.addEventListener('keydown', function (e) { if ((e.ctrlKey || e.metaKey) && e.key === '\\') { e.preventDefault(); flipRail(); } });
        paintRail();

        // the folder column and the list each have an edge that drags; widths are remembered,
        // and the note's own pane always keeps room to be read
        var PANE_MIN = 320, COL_RTL = document.documentElement.dir === 'rtl';
        var colW = function (sel) { var el = document.querySelector(sel); return el && el.offsetParent ? el.getBoundingClientRect().width : 0; };
        var makeGrip = function (grip, prop, key, min, others) {
            if (!grip) return;
            var col = grip.parentNode;
            var maxW = function () { return Math.max(min, window.innerWidth - PANE_MIN - others()); };
            var setW = function (w, keep) {
                if (w == null) { document.body.style.removeProperty(prop); try { localStorage.removeItem(key); } catch (e) {} }
                else {
                    w = Math.round(Math.min(maxW(), Math.max(min, w)));
                    document.body.style.setProperty(prop, w + 'px');
                    if (keep) try { localStorage.setItem(key, String(w)); } catch (e) {}
                }
                grip.setAttribute('aria-valuenow', String(Math.round(col.getBoundingClientRect().width)));
            };
            try { var saved = parseInt(localStorage.getItem(key), 10); if (saved > 0) setW(saved, false); } catch (e) {}
            var drag = null;
            grip.addEventListener('pointerdown', function (e) {
                if (e.button !== 0) return;
                e.preventDefault();
                var r = col.getBoundingClientRect();
                drag = { id: e.pointerId, edge: COL_RTL ? r.right : r.left };
                grip.setPointerCapture(e.pointerId);
                document.body.classList.add('col-sizing');
            });
            grip.addEventListener('pointermove', function (e) {
                if (!drag || e.pointerId !== drag.id) return;
                setW(COL_RTL ? drag.edge - e.clientX : e.clientX - drag.edge, false);
            });
            var endDrag = function (e) {
                if (!drag || e.pointerId !== drag.id) return;
                drag = null;
                document.body.classList.remove('col-sizing');
                setW(col.getBoundingClientRect().width, true);
            };
            grip.addEventListener('pointerup', endDrag);
            grip.addEventListener('pointercancel', endDrag);
            grip.addEventListener('dblclick', function () { setW(null); });
            grip.addEventListener('keydown', function (e) {
                var step = e.shiftKey ? 48 : 16, d = 0;
                if (e.key === 'ArrowRight') d = COL_RTL ? -step : step;
                else if (e.key === 'ArrowLeft') d = COL_RTL ? step : -step;
                else if (e.key === 'Home') { e.preventDefault(); setW(null); return; }
                if (!d) return;
                e.preventDefault();
                setW(col.getBoundingClientRect().width + d, true);
            });
            window.addEventListener('resize', function () {
                var w = parseInt(document.body.style.getPropertyValue(prop), 10);
                if (w > maxW()) setW(maxW(), false);
            });
        };
        makeGrip(document.getElementById('rail-grip'), '--rail-w', 'takenote.railw', 150, function () { return colW('.dk-list'); });
        makeGrip(document.getElementById('list-grip'), '--list-w', 'takenote.listw', 240, function () { return colW('.dk-rail'); });
        // "Notes" in the folder column is the whole notebook, even when it is already the view
        document.getElementById('seg').addEventListener('click', function (e) {
            var b = e.target.closest('button[data-view]');
            if (b && b.dataset.view === 'notes') { wholeNotebook(); draw(); }
        });
    }

