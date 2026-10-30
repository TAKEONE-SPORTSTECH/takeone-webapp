{{--
    The notebook's folders. Included INSIDE takenote/script's closure.

    A tree, as Trilium keeps notes: a folder may sit inside another (four
    deep at most). On a phone the folders are rows on the Folders screen
    (takenote/layout) — tap one to go into it; on a desk they are a tree in
    the folder column. Either way a note is DRAGGED onto a
    folder to file it, and a folder's sheet renames, recolours, nests or
    removes it (a removed folder hands its notes and folders up a level —
    nothing is deleted with it). The open folder shows its notes and those of
    its sub-folders.
--}}
    /* ── folders ─────────────────────────────────────────────────────── */
    var FOLDER_COLORS = ['#7c6bf5', '#e0457b', '#e89a1c', '#16a394', '#3b9ae1', '#22a06b', '#c9372c', '#6b6280'];
    var $folders = document.getElementById('folders');
    S.folders = S.folders || [];
    S.folder = 'all';
    try { S.folder = localStorage.getItem('takenote.folder') || 'all'; } catch (e) {}
    try { S.openTree = JSON.parse(localStorage.getItem('takenote.tree') || '{}') || {}; } catch (e) { S.openTree = {}; }

    function folderOf(uuid) { return S.folders.find(function (f) { return f.uuid === uuid; }) || null; }
    function childrenOf(uuid) { return S.folders.filter(function (f) { return (f.parent || null) === (uuid || null); }).sort(function (a, b) { return a.position - b.position; }); }
    function subtree(uuid) { var out = [uuid]; childrenOf(uuid).forEach(function (c) { out = out.concat(subtree(c.uuid)); }); return out; }
    function pathOf(uuid) { var p = [], f = folderOf(uuid), guard = 0; while (f && guard++ < 8) { p.unshift(f); f = folderOf(f.parent); } return p; }
    function inFolder(n) {
        if (S.folder === 'all' || !S.folders.length) return true;
        if (S.folder === 'none') return !n.folder;
        if (!folderOf(S.folder)) return true;
        return subtree(S.folder).indexOf(n.folder) !== -1;
    }
    function countIn(uuid) { var set = subtree(uuid); return S.notes.filter(function (n) { return set.indexOf(n.folder) !== -1; }).length; }
    function setFolder(v) {
        S.folder = v;
        try { localStorage.setItem('takenote.folder', v); } catch (e) {}
        // a folder and a smart list are two ways of choosing the list: a folder lets go of the smart list
        if (S.filter !== 'all') { S.filter = 'all'; document.querySelectorAll('.filter').forEach(function (x) { x.classList.toggle('is-on', x.dataset.filter === 'all'); }); }
        if (S.view !== 'notes' && !DESK) { S.view = 'notes'; try { localStorage.setItem('takenote.view', 'notes'); } catch (e) {} }
        draw();
    }

    function drawFolders() {
        if (!$folders) return;
        if (S.folder !== 'all' && S.folder !== 'none' && !folderOf(S.folder)) S.folder = 'all';
        $folders.hidden = S.view !== 'notes' && !DESK;
        if (DESK) drawTree(); else drawFolderRows();
        notesDraggable(); // a note dragged onto a folder is filed there
    }

    /* a phone: the Folders screen's grouped rows — the whole tree, each level indented; a tap goes into it */
    function drawFolderRows() {
        var head = h('div', 'fo-head');
        var add = h('button', 'fo-plus'); add.type = 'button'; add.setAttribute('aria-label', T.fo_new); add.title = T.fo_new; add.appendChild(icon('bi-folder-plus'));
        add.addEventListener('click', function () { newFolder(null); });
        head.append(h('span', null, T.fo_folders), add);
        var box = h('div', 'nb-group');
        (function walk(parent, depth) {
            childrenOf(parent).forEach(function (f) {
                box.appendChild(folderLine(f, f.name, depth, countIn(f.uuid), function () { setFolder(f.uuid); enterList(); }));
                walk(f.uuid, depth + 1);
            });
        })(null, 0);
        var none = S.notes.filter(function (n) { return !n.folder; }).length;
        if (S.folders.length && none) box.appendChild(folderLine(null, T.fo_none, 0, none, function () { setFolder('none'); enterList(); }));
        if (!S.folders.length) {
            var first = h('button', 'nb-line nb-add'); first.type = 'button';
            first.append(icon('bi-folder-plus'), h('span', 'nb-name', T.fo_new));
            first.addEventListener('click', function () { newFolder(null); });
            box.appendChild(first);
        }
        $folders.replaceChildren(head, box);
    }
    function folderLine(f, label, depth, n, click) {
        var b = h('button', 'nb-line'); b.type = 'button';
        b.dataset.folder = f ? f.uuid : ''; // a note dropped on "Not in a folder" leaves its folder
        b.style.setProperty('--d', depth);
        var ic = icon(f ? 'bi-folder-fill' : 'bi-inbox'); if (f) ic.style.color = f.color;
        b.append(ic, h('span', 'nb-name', label));
        if (f && f.shared) { var sh = icon('bi-people-fill'); sh.className += ' fo-shared'; sh.title = T.sh_shared; b.appendChild(sh); } // shared with somebody
        if (n) b.appendChild(h('span', 'n', String(n)));
        b.appendChild(icon('bi-chevron-right'));
        b.addEventListener('click', click);
        return b;
    }

    /* a desk: the tree */
    function drawTree() {
        var head = h('div', 'fo-head');
        var add = h('button', 'fo-plus'); add.type = 'button'; add.setAttribute('aria-label', T.fo_new); add.title = T.fo_new; add.appendChild(icon('bi-folder-plus'));
        add.addEventListener('click', function () { newFolder(null); });
        head.append(h('span', null, T.fo_folders), add);
        var tree = h('div', 'fo-tree'); tree.setAttribute('role', 'tree');
        var all = row(null, T.fo_all, 0, S.folder === 'all', S.notes.length);
        all.addEventListener('click', function () { setFolder('all'); });
        all.dataset.folder = '';
        tree.appendChild(all);
        (function walk(parent, depth) {
            childrenOf(parent).forEach(function (f) {
                var kids = childrenOf(f.uuid).length, open = S.openTree[f.uuid] !== false;
                var r = row(f, f.name, depth, S.folder === f.uuid, countIn(f.uuid), kids ? open : null);
                r.dataset.folder = f.uuid;
                tree.appendChild(r);
                if (kids && open) walk(f.uuid, depth + 1);
            });
        })(null, 1);
        var none = S.notes.filter(function (n) { return !n.folder; }).length;
        if (S.folders.length && none) {
            var nr = row(null, T.fo_none, 0, S.folder === 'none', none, null, 'bi-inbox');
            nr.addEventListener('click', function () { setFolder('none'); });
            nr.dataset.folder = ''; // a note dropped here leaves its folder
            tree.appendChild(nr);
        }
        $folders.replaceChildren(head, tree);
    }
    function row(f, label, depth, on, n, open, ic) {
        var r = h('div', 'fo-row' + (on ? ' is-on' : '')); r.setAttribute('role', 'treeitem'); r.tabIndex = 0;
        r.style.setProperty('--d', depth);
        var tw = h('button', 'fo-tw'); tw.type = 'button'; tw.tabIndex = -1;
        if (open !== null && open !== undefined) {
            tw.appendChild(icon(open ? 'bi-chevron-down' : 'bi-chevron-right'));
            tw.setAttribute('aria-label', open ? T.fo_fold : T.fo_unfold);
            tw.addEventListener('click', function (e) { e.stopPropagation(); S.openTree[f.uuid] = !open; try { localStorage.setItem('takenote.tree', JSON.stringify(S.openTree)); } catch (er) {} drawFolders(); });
        }
        var ico = h('span', 'fo-ic');
        if (f) { ico.appendChild(icon(on ? 'bi-folder2-open' : 'bi-folder-fill')); ico.style.color = f.color; } else ico.appendChild(icon(ic || 'bi-collection'));
        var nm = h('span', 'fo-name', label); nm.title = label; // the whole name, when the column is too narrow for it
        r.append(tw, ico, nm);
        if (f && f.shared) { var sh = icon('bi-people-fill'); sh.className += ' fo-shared'; sh.title = T.sh_shared; r.appendChild(sh); } // shared with somebody
        if (n) r.appendChild(h('span', 'n', String(n)));
        if (f && S.space && S.space.folder === f.uuid) { r.addEventListener('click', function () { setFolder(f.uuid); }); } // the shared folder itself is its owner's to change
        else if (f) {
            r.addEventListener('click', function () { setFolder(f.uuid); });
            var more = h('button', 'fo-more'); more.type = 'button'; more.setAttribute('aria-label', T.fo_edit); more.title = T.fo_edit; more.appendChild(icon('bi-three-dots'));
            more.addEventListener('click', function (e) { e.stopPropagation(); folderSheet(f); });
            r.appendChild(more);
        }
        r.addEventListener('keydown', function (e) { if (e.key === 'Enter') r.click(); });
        return r;
    }

    /* dragging a note onto a folder files it there: the folder under the finger when it is let go */
    var dragXY = null;
    function overFolder(x, y) {
        var el = document.elementFromPoint(x, y);
        el = el && el.closest ? el.closest('[data-folder]') : null;
        return el && $folders.contains(el) ? el : null;
    }
    function trackDrag(e) {
        var p = e.touches && e.touches[0] ? e.touches[0] : e;
        if (p.clientX == null) return;
        dragXY = { x: p.clientX, y: p.clientY };
        var t = overFolder(dragXY.x, dragXY.y);
        $folders.querySelectorAll('.is-drop').forEach(function (x) { if (x !== t) x.classList.remove('is-drop'); });
        if (t) t.classList.add('is-drop');
    }
    function notesDraggable() {
        if (!window.Sortable || $notes.__sortable) return;
        $notes.__sortable = Sortable.create($notes, {
            group: { name: 'takenote-notes', pull: false, put: false }, sort: false, draggable: '.tile',
            forceFallback: true, fallbackOnBody: true, fallbackTolerance: 6, delay: 260, delayOnTouchOnly: true,
            fallbackClass: 'is-lifted', ghostClass: 'is-ghost',
            onStart: function () {
                dragXY = null;
                document.body.classList.add('is-filing'); buzz(10);
                ['mousemove', 'touchmove', 'pointermove'].forEach(function (ev) { document.addEventListener(ev, trackDrag, { passive: true, capture: true }); });
            },
            onEnd: function (e) {
                ['mousemove', 'touchmove', 'pointermove'].forEach(function (ev) { document.removeEventListener(ev, trackDrag, { capture: true }); });
                document.body.classList.remove('is-filing');
                var t = dragXY && overFolder(dragXY.x, dragXY.y);
                $folders.querySelectorAll('.is-drop').forEach(function (x) { x.classList.remove('is-drop'); });
                justDragged = Date.now();
                if (t) fileNote(e.item.dataset.uuid, t.dataset.folder || null);
            }
        });
    }
    function fileNote(uuid, folder, done) {
        var n = S.notes.find(function (x) { return x.uuid === uuid; });
        if (!n) return;
        var was = n.folder || null;
        if (was === folder) { draw(); return; }
        n.folder = folder; draw();
        api('POST', url('file', uuid), { folder: folder }).then(function (r) {
            upsert(summaryOf(r.note), true);
            if (done) done(r.note);
            var f = folderOf(folder);
            toast(f ? t('fo_filed', { folder: f.name }) : T.fo_unfiled, { action: T.undo, onAction: function () {
                api('POST', url('file', uuid), { folder: was }).then(function (u) { upsert(summaryOf(u.note), true); }).catch(fail);
            } });
        }).catch(function (err) { n.folder = was; draw(); fail(err); });
    }

    /* a folder's name box with its emoji button beside it */
    function nameRow(inp, emo) { var r = h('div', 'fo-namerow'); r.append(inp, emo.btn); return r; }

    /* new folder: named straight away, opened */
    function newFolder(parent) {
        var root = sheetShell(T.fo_new, parent ? t('fo_inside', { folder: (folderOf(parent) || {}).name || '' }) : T.fo_new_sub, 'bi-folder-plus', '#7c6bf5');
        var inp = h('input', 'fo-in'); inp.type = 'text'; inp.maxLength = 80; inp.placeholder = T.fo_name; inp.setAttribute('aria-label', T.fo_name);
        var colors = swatches(FOLDER_COLORS[S.folders.length % FOLDER_COLORS.length]);
        var go = h('button', 'ss-go', T.fo_create); go.type = 'button';
        var emo = emojiFor(inp); // the same emoji button as comments and labels (takenote/cardback)
        root.body.append(nameRow(inp, emo), emo.box, colors.el);
        root.foot.appendChild(go);
        setTimeout(function () { inp.focus(); }, 60);
        var submit = function () {
            var name = inp.value.trim(); if (!name) { inp.focus(); return; }
            go.disabled = true;
            api('POST', U.folders, { name: name, parent: parent, color: colors.value() }).then(function (r) {
                S.folders = r.folders; root.close(); setFolder(r.folder.uuid);
            }).catch(function (err) { go.disabled = false; fail(err); });
        };
        inp.addEventListener('keydown', function (e) { if (e.key === 'Enter') submit(); });
        go.addEventListener('click', submit);
    }

    /* a folder's sheet: name, colour, where it sits, and removing it */
    function folderSheet(f) {
        var root = sheetShell(f.name, T.fo_sheet_sub, 'bi-folder-fill', f.color);
        var inp = h('input', 'fo-in'); inp.type = 'text'; inp.maxLength = 80; inp.value = f.name; inp.setAttribute('aria-label', T.fo_name);
        var colors = swatches(f.color);
        var emo = emojiFor(inp);
        root.body.append(h('h4', null, T.fo_name), nameRow(inp, emo), emo.box, h('h4', null, T.fo_colour), colors.el, h('h4', null, T.fo_sits_in));
        var where = h('div', 'ss-pills'), dest = f.parent || null;
        var mine = subtree(f.uuid);
        var opts = [{ uuid: null, name: T.fo_top }].concat(S.folders.filter(function (x) { return mine.indexOf(x.uuid) === -1; }));
        var btns = opts.map(function (o) {
            var b = h('button', 'ss-pill'); b.type = 'button';
            if (o.uuid) { var d = h('i', 'fo-dot'); d.style.background = o.color; b.appendChild(d); }
            b.appendChild(document.createTextNode(o.uuid ? pathOf(o.uuid).map(function (p) { return p.name; }).join(' › ') : o.name));
            b.addEventListener('click', function () { dest = o.uuid; paint(); });
            where.appendChild(b); return { b: b, uuid: o.uuid };
        });
        function paint() { btns.forEach(function (x) { x.b.classList.toggle('is-on', x.uuid === dest); }); }
        paint();
        root.body.appendChild(where);
        var sub = h('button', 'fo-line'); sub.type = 'button'; sub.append(icon('bi-folder-plus'), document.createTextNode(T.fo_new_inside));
        sub.addEventListener('click', function () { root.close(); newFolder(f.uuid); });
        // sharing it: only one's own folders, from one's own notebook
        if (S.sharing && !S.space && U.shares) {
            var shr = h('button', 'fo-line'); shr.type = 'button'; shr.append(icon('bi-person-plus'), document.createTextNode(T.sh_action));
            if (f.shared) shr.appendChild(h('span', 'fo-line-n', String(f.shared)));
            shr.addEventListener('click', function () { root.close(); shareSheet(f); });
            root.body.appendChild(shr);
        }
        var del = h('button', 'fo-line is-danger'); del.type = 'button'; del.append(icon('bi-trash3'), document.createTextNode(T.fo_delete));
        del.addEventListener('click', function () {
            if (!del.classList.contains('is-sure')) { del.classList.add('is-sure'); del.lastChild.textContent = T.fo_delete_sure; return; }
            api('DELETE', url('folder', f.uuid)).then(function (r) {
                S.folders = r.folders; S.notes = r.notes; root.close();
                if (S.folder === f.uuid) S.folder = f.parent || 'all';
                draw(); toast(t('fo_deleted', { folder: f.name }));
            }).catch(fail);
        });
        root.body.append(sub, del);
        var go = h('button', 'ss-go', T.fo_save); go.type = 'button';
        root.foot.appendChild(go);
        go.addEventListener('click', function () {
            var body = { name: inp.value.trim() || f.name, color: colors.value() };
            if (dest !== (f.parent || null)) body.parent = dest;
            go.disabled = true;
            api('PATCH', url('folder', f.uuid), body).then(function (r) { S.folders = r.folders; root.close(); draw(); }).catch(function (err) { go.disabled = false; fail(err); });
        });
    }

    /* a note's folder: pick one (or none, or a new one) */
    function moveNoteSheet(n, done) {
        var root = sheetShell(T.fo_move_note, n.title || T.note_untitled, 'bi-folder-symlink', '#7c6bf5');
        var list = h('div', 'fo-pick');
        var pick = function (uuid) { root.close(); fileNote(n.uuid, uuid, done); };
        var none = h('button', 'fo-opt' + (!n.folder ? ' is-on' : '')); none.type = 'button';
        none.append(icon('bi-inbox'), document.createTextNode(T.fo_none));
        none.addEventListener('click', function () { pick(null); });
        list.appendChild(none);
        (function walk(parent, depth) {
            childrenOf(parent).forEach(function (f) {
                var b = h('button', 'fo-opt' + (n.folder === f.uuid ? ' is-on' : '')); b.type = 'button'; b.style.setProperty('--d', depth);
                var ic = icon('bi-folder-fill'); ic.style.color = f.color;
                b.append(ic, document.createTextNode(f.name));
                b.addEventListener('click', function () { pick(f.uuid); });
                list.appendChild(b);
                walk(f.uuid, depth + 1);
            });
        })(null, 0);
        var add = h('button', 'fo-line'); add.type = 'button'; add.append(icon('bi-folder-plus'), document.createTextNode(T.fo_new));
        add.addEventListener('click', function () { root.close(); newFolder(null); });
        root.body.append(list, add);
    }

    /* the sheets share one shell (the plan sheet's look: Design Rule #8's band) */
    function sheetShell(title, sub, ic, color) {
        var root = h('div', 'ss ss-folder'); root.setAttribute('role', 'dialog'); root.setAttribute('aria-modal', 'true');
        var dim = h('div', 'ss-dim'), sheet = h('div', 'ss-sheet');
        var band = h('div', 'ss-band');
        if (/^#[0-9a-f]{6}$/i.test(color)) band.style.background = 'linear-gradient(150deg, ' + color + ', ' + color + 'b0)';
        var r = h('div', 'r'); var tl = h('span', 'tile2'); tl.appendChild(icon(ic));
        var txt = h('div'); txt.style.minWidth = '0'; txt.style.flex = '1'; txt.append(h('h3', null, title), h('p', null, sub));
        var x = h('button', 'x'); x.type = 'button'; x.setAttribute('aria-label', T.close); x.appendChild(icon('bi-x-lg'));
        r.append(tl, txt, x); band.append(h('div', 'hd'), r);
        var body = h('div', 'ss-body fo-body'), foot = h('div', 'ss-foot');
        sheet.append(band, body, foot); root.append(dim, sheet); document.body.appendChild(root);
        var api2 = { body: body, foot: foot, close: function () { document.removeEventListener('keydown', esc, true); root.remove(); } };
        function esc(e) { if (e.key === 'Escape') { e.stopImmediatePropagation(); api2.close(); } }
        document.addEventListener('keydown', esc, true);
        x.addEventListener('click', api2.close); dim.addEventListener('click', api2.close);
        setTimeout(function () { if (!foot.childNodes.length) foot.remove(); }, 0);
        return api2;
    }
    function swatches(current) {
        var el = h('div', 'fo-colors'), val = current;
        FOLDER_COLORS.forEach(function (c) {
            var b = h('button', 'fo-sw'); b.type = 'button'; b.style.setProperty('--c', c); b.setAttribute('aria-label', c);
            b.addEventListener('click', function () { val = c; el.querySelectorAll('.fo-sw').forEach(function (x) { x.classList.toggle('is-on', x === b); }); });
            if (c === current) b.classList.add('is-on');
            el.appendChild(b);
        });
        return { el: el, value: function () { return val; } };
    }
