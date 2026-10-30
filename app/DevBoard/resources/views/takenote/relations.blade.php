{{--
    Named connections between notes — Trilium's relations, TakeNote's way
    (takenote/script, inside its closure; TakeNoteRelationController).

    A connection joins two notes with a few words ("depends on", "part of"),
    pointing from one to the other. It lives apart from the notes' text, so
    making one never rewrites a note. Made three ways, all ending in the same
    sheet: "Connect" on the map's note card (then tap the other note), a
    Shift-drag from note to note on the map (desk), or "Connect to a note…"
    from a note's ⋯ menu / the Connected row under it. Tapping a connection's
    name on the map — or its pill under a note — opens it to rename, turn
    round, remove, or open either note.
--}}
    /* ── named connections between notes ─────────────────────────────── */
    var REL_INK = '#10b981';
    function relOn() { return !!(U.relations && S.relations); }
    function relTitle(uuid) { var n = find(uuid); return n ? (n.title || T.note_untitled) : T.note_untitled; }
    /* this note's connections, each with the note at its other end and which way it points */
    function relationsOf(uuid) {
        return (S.relations || []).filter(function (r) { return (r.from === uuid || r.to === uuid) && find(r.from) && find(r.to); })
            .map(function (r) { return { rel: r, other: r.from === uuid ? r.to : r.from, out: r.from === uuid }; });
    }
    function relBetween(a, b) {
        return (S.relations || []).find(function (r) { return (r.from === a && r.to === b) || (r.from === b && r.to === a); }) || null;
    }
    function relPut(rel) {
        S.relations = (S.relations || []).filter(function (r) { return r.uuid !== rel.uuid && !((r.from === rel.from && r.to === rel.to) || (r.from === rel.to && r.to === rel.from)); });
        S.relations.push(rel);
        relChanged(rel.uuid);
    }
    /* everything that draws connections paints itself again: the map, and the open note's Connected row */
    function relChanged(flash) {
        if (M && S.view === 'map' && !$map.hidden) {
            if (flash) { M.flashRel = flash; M.flashUntil = performance.now() + 1800; }
            drawMap();
            if (S.mapCenter && M.byId[S.mapCenter]) {
                // centred on a note: settle back on it, framing its neighbourhood with the new line in it
                M.follow = { id: S.mapCenter, until: performance.now() + 900 };
                setTimeout(function () { if (M && !M.connect) mapFit(true); }, 950);
                if (M.card && !M.card.hidden) showCard(M.byId[S.mapCenter]);
            }
        }
        if (P && P.linkBox) P.linkBox.paint();
    }

    /* "A —→ B", with a button to turn it round */
    function relPair(from, to, onFlip) {
        var row = h('div', 'rl-pair');
        var a = h('span', 'rl-end', relTitle(from)); a.dir = 'auto';
        var b = h('span', 'rl-end', relTitle(to)); b.dir = 'auto';
        var mid = h('span', 'rl-arrow'); mid.appendChild(icon('bi-arrow-down'));
        row.append(a, mid, b);
        if (onFlip) {
            var sw = h('button', 'rl-swap'); sw.type = 'button'; sw.title = T.rel_flip; sw.setAttribute('aria-label', T.rel_flip);
            sw.appendChild(icon('bi-arrow-down-up'));
            sw.addEventListener('click', onFlip);
            row.appendChild(sw);
        }
        return row;
    }
    /* the name: free words, or one of the usual ones */
    function relNameField(value, onEnter) {
        var wrap = h('div', 'rl-name');
        var lab = h('label', 'rl-lab', T.rel_name);
        var inp = h('input', 'fo-in'); inp.type = 'text'; inp.maxLength = 40; inp.autocomplete = 'off'; inp.dir = 'auto';
        inp.placeholder = T.rel_name_ph; inp.value = value || ''; inp.setAttribute('enterkeyhint', 'done');
        var id = 'rl-' + Math.random().toString(36).slice(2); inp.id = id; lab.htmlFor = id;
        var chips = h('div', 'rl-chips');
        function mark() { chips.querySelectorAll('button').forEach(function (c) { c.classList.toggle('is-on', c.textContent === inp.value.trim()); }); }
        ['rel_s1', 'rel_s2', 'rel_s3', 'rel_s4', 'rel_s5', 'rel_s6'].forEach(function (k) {
            var c = h('button', null, T[k]); c.type = 'button';
            c.addEventListener('click', function () { inp.value = inp.value.trim() === T[k] ? '' : T[k]; mark(); });
            chips.appendChild(c);
        });
        inp.addEventListener('input', mark);
        inp.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !e.isComposing) { e.preventDefault(); onEnter(); } });
        mark();
        wrap.append(lab, inp, chips);
        return { el: wrap, value: function () { return inp.value.trim(); }, focus: function () { inp.focus(); } };
    }

    /* Name the connection between two notes (and make it). Already connected: this renames it. */
    function connectSheet(from, to) {
        if (!relOn() || !from || !to) return;
        if (from === to) { toast(T.rel_same, { error: true }); return; }
        var had = relBetween(from, to);
        if (had) { from = had.from; to = had.to; }
        var root = sheetShell(had ? T.rel_edit : T.rel_title, '', 'bi-bezier2', REL_INK);
        var pairBox = h('div');
        function paintPair() { pairBox.replaceChildren(relPair(from, to, function () { var x = from; from = to; to = x; paintPair(); })); }
        paintPair();
        var go = h('button', 'ss-go rl-go', had ? T.rel_rename : T.rel_save); go.type = 'button';
        var name = relNameField(had ? had.label : '', function () { go.click(); });
        root.body.classList.add('rl-body');
        root.body.append(pairBox, name.el);
        root.foot.appendChild(go);
        setTimeout(function () { if (window.matchMedia('(pointer: fine)').matches) name.focus(); }, 60);
        go.addEventListener('click', function () {
            if (go.disabled) return;
            go.disabled = true;
            api('POST', U.relations, { from: from, to: to, label: name.value() || null }).then(function (r) {
                root.close(); relPut(r.relation); toast(r.message); buzz(8);
            }).catch(function (err) {
                go.disabled = false;
                if (err && err.status === 422 && err.data && err.data.message) toast(err.data.message, { error: true }); else fail(err);
            });
        });
    }

    /* "Connect to a note…": find the other note by its name */
    function pickNoteSheet(from) {
        if (!relOn() || !from) return;
        var root = sheetShell(T.rel_connect_to, relTitle(from), 'bi-bezier2', REL_INK);
        var inp = h('input', 'fo-in rl-find'); inp.type = 'search'; inp.placeholder = T.rel_find; inp.setAttribute('aria-label', T.rel_find);
        inp.autocomplete = 'off'; inp.dir = 'auto'; inp.setAttribute('enterkeyhint', 'search');
        var list = h('div', 'fo-pick rl-list');
        root.body.classList.add('rl-body');
        root.body.append(inp, list);
        function paint() {
            var q = inp.value.trim().toLowerCase();
            var all = S.notes.filter(function (n) { return n.uuid !== from && (!q || ((n.title || '') + ' ' + (n.excerpt || '')).toLowerCase().indexOf(q) !== -1); });
            if (!all.length) { list.replaceChildren(h('p', 'fr-none', q ? T.rel_none_found : T.nm_empty)); return; }
            list.replaceChildren.apply(list, all.slice(0, 40).map(function (n) {
                var b = h('button', 'fo-opt rl-opt'); b.type = 'button';
                var f = folderOf(n.folder), dot = h('i', 'rl-dot'); dot.style.background = MAP_KIND[n.lead] || MAP_KIND.text;
                var w = h('span', 'rl-w'); var tt = h('b', null, n.title || T.note_untitled); tt.dir = 'auto';
                w.appendChild(tt);
                if (f) { var fs = h('small'); var fi = icon('bi-folder-fill'); fi.style.color = f.color; var fn = h('span', null, f.name); fn.dir = 'auto'; fs.append(fi, fn); w.appendChild(fs); }
                b.append(dot, w);
                var had = relBetween(from, n.uuid);
                if (had) { var tg = h('span', 'rl-tag', had.label || T.rel_section); tg.dir = 'auto'; b.appendChild(tg); }
                b.addEventListener('click', function () { root.close(); connectSheet(from, n.uuid); });
                return b;
            }));
        }
        inp.addEventListener('input', paint);
        paint();
        setTimeout(function () { if (window.matchMedia('(pointer: fine)').matches) inp.focus(); }, 60);
    }

    /* One connection: rename it, turn it round, remove it, or open either note. */
    function relationSheet(rel) {
        if (!relOn() || !rel) return;
        var root = sheetShell(T.rel_edit, rel.label || '', 'bi-bezier2', REL_INK);
        var pairBox = h('div');
        function paintPair() {
            var p = relPair(rel.from, rel.to, flip);
            p.querySelectorAll('.rl-end').forEach(function (el, i) {
                var u = i ? rel.to : rel.from;
                el.classList.add('is-door'); el.appendChild(icon('bi-box-arrow-up-right')); el.setAttribute('role', 'button'); el.tabIndex = 0; el.title = T.rel_open;
                var open = function () { root.close(); openFromRel(u); };
                el.addEventListener('click', open);
                el.addEventListener('keydown', function (e) { if (e.key === 'Enter') open(); });
            });
            pairBox.replaceChildren(p);
        }
        function flip() {
            api('PATCH', url('relation', rel.uuid), { flip: true }).then(function (r) { rel = r.relation; relPut(rel); paintPair(); }).catch(fail);
        }
        paintPair();
        var save = h('button', 'ss-go rl-go', T.rel_rename); save.type = 'button';
        var name = relNameField(rel.label, function () { save.click(); });
        var rm = h('button', 'fo-line is-danger rl-rm'); rm.type = 'button';
        var rmText = h('span', null, T.rel_remove);
        rm.append(icon('bi-scissors'), rmText);
        rm.addEventListener('click', function () {
            if (!rm.classList.contains('is-sure')) { rm.classList.add('is-sure'); rmText.textContent = T.rel_remove_sure; return; }
            rm.disabled = true;
            api('DELETE', url('relation', rel.uuid)).then(function (r) {
                root.close();
                S.relations = (S.relations || []).filter(function (x) { return x.uuid !== rel.uuid; });
                relChanged(); toast(r.message);
            }).catch(function (err) { rm.disabled = false; fail(err); });
        });
        root.body.classList.add('rl-body');
        root.body.append(pairBox, name.el, rm);
        root.foot.appendChild(save);
        save.addEventListener('click', function () {
            if (save.disabled) return;
            save.disabled = true;
            api('PATCH', url('relation', rel.uuid), { label: name.value() || null }).then(function (r) {
                root.close(); relPut(r.relation);
            }).catch(function (err) { save.disabled = false; fail(err); });
        });
    }
    function openFromRel(uuid) {
        if (P) openLinked(uuid); else openNote(uuid);
    }

    /* Under a note: its connections, and the way to make another */
    function relRow(st) {
        var me = current(st);
        if (!relOn() || !me || st.isCard) return [];
        var mine = relationsOf(me.uuid);
        var row = h('div', 'nl-row');
        mine.forEach(function (c) {
            var b = h('span', 'nl-pill rl-pill');
            var go = h('button', 'rl-go-note'); go.type = 'button';
            var dir = icon(c.out ? 'bi-arrow-right' : 'bi-arrow-left'); dir.classList.add('rl-way');
            go.appendChild(dir);
            if (c.rel.label) { var lb = h('em', null, c.rel.label); lb.dir = 'auto'; go.appendChild(lb); }
            var tt = h('span', null, relTitle(c.other)); tt.dir = 'auto';
            go.appendChild(tt);
            go.addEventListener('click', function () { openLinked(c.other); });
            var ed = h('button', 'rl-ed'); ed.type = 'button'; ed.setAttribute('aria-label', T.rel_edit); ed.title = T.rel_edit;
            ed.appendChild(icon('bi-three-dots'));
            ed.addEventListener('click', function () { relationSheet(c.rel); });
            b.append(go, ed);
            row.appendChild(b);
        });
        var add = h('button', 'nl-pill rl-add'); add.type = 'button';
        add.append(icon('bi-plus-lg'), document.createTextNode(T.rel_connect_to));
        add.addEventListener('click', function () { pickNoteSheet(me.uuid); });
        row.appendChild(add);
        return [h('h4', null, T.rel_section + (mine.length ? ' · ' + mine.length : '')), row];
    }
