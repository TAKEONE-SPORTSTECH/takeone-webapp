{{--
    TakeNote's Board view, built the way Trello's board works (studied 27 Oct
    2026), and the quick-edit sheet. Included INSIDE takenote/script's closure,
    so it shares its state and helpers (S, T, h, icon, api, url, toast, upsert,
    draw, buzz, DESK).

      · Lists: grey rounded columns side by side, each scrolling on its own;
        on a phone one list at a time, the next one peeking, swiped across.
        A list's ⋯ collapses it to a narrow strip (remembered on this device).
      · Cards: white, a cover (a picture or a colour, half or full), its
        labels, the title, then badges — template, watching, dates (green
        complete, yellow soon, red late), text, comments, attachments,
        checklist, voice, video — and its people. Hover: an outline, the round
        "mark complete" tick and the pencil (Trello's quick-edit menu, also on
        a right-click: takenote/cardtools).
      · The board's bar: Filter and Archived items; a list's foot: "Add a
        card" and a card from a template.
      · Drag: the card tilts as it is lifted, a grey slot opens where it will
        land, and it lands exactly there, in any list.
      · "Add a card" at the foot of every list: type, Enter adds it at the
        bottom and keeps the composer open for the next; Esc closes it.
--}}
    /* ── the board view ─────────────────────────────────────────────── */
    var $board = document.getElementById('board');
    var sortables = [], justDragged = 0, composing = null, composeAway = null;
    try { S.fold = JSON.parse(localStorage.getItem('takenote.fold') || 'null'); } catch (e) { S.fold = null; }

    function folded(lane) { return !!(S.fold && S.fold[lane.uuid]); }
    function setFold(lane, on) {
        S.fold = S.fold || {};
        S.fold[lane.uuid] = on;
        try { localStorage.setItem('takenote.fold', JSON.stringify(S.fold)); } catch (e) {}
    }
    function inLane(list, lane) {
        return list.filter(function (n) { return n.lane && n.lane.uuid === lane.uuid; })
            .sort(function (a, b) { return a.position - b.position; });
    }
    function lastLane() { return S.lanes[S.lanes.length - 1]; }
    function isDone(n) { return !!n.done; } // Trello's "complete": a tick on the card, wherever it is

    function drawBoard(list) {
        sortables.forEach(function (s) { s.destroy(); });
        sortables = [];
        if (!S.lanes.length) { $board.replaceChildren(); return; }
        var keepX = ($board.querySelector('.tb') || {}).scrollLeft || 0;
        var tb = h('div', 'tb');
        S.lanes.forEach(function (lane) {
            var notes = inLane(list, lane).filter(shownOnBoard);
            var sec = h('section', 'tl' + (folded(lane) ? ' is-folded' : ''));
            sec.dataset.lane = lane.uuid; sec.style.setProperty('--c', lane.color);

            var head = h('header', 'tl-h');
            var name = h('h3', null, lane.name);
            var n = h('span', 'tl-n', String(notes.length));
            var more = h('button', 'tl-more'); more.type = 'button';
            more.setAttribute('aria-label', t('tn_fold', { lane: lane.name })); more.title = folded(lane) ? T.tn_list_expand : T.tn_list_collapse;
            more.appendChild(icon(folded(lane) ? 'bi-arrows-angle-expand' : 'bi-arrows-angle-contract'));
            more.addEventListener('click', function (e) { e.stopPropagation(); setFold(lane, !folded(lane)); draw(); });
            head.append(h('span', 'tl-dot'), name, n, more);
            if (folded(lane)) { sec.addEventListener('click', function () { setFold(lane, false); draw(); }); }

            var box = h('div', 'tl-cards');
            box.dataset.lane = lane.uuid;
            notes.forEach(function (note) { box.appendChild(card(note)); });

            var foot = h('footer', 'tl-f');
            if (composing === lane.uuid) foot.appendChild(composer(lane, box));
            else {
                var add = h('button', 'tl-add'); add.type = 'button';
                add.append(icon('bi-plus-lg'), document.createTextNode(T.tn_add_card));
                add.addEventListener('click', function () { composing = lane.uuid; draw(); });
                // Trello's template button beside "Add a card": a card from one of the board's templates
                var tpl = h('button', 'tl-x tl-tpl'); tpl.type = 'button'; tpl.setAttribute('aria-label', T.ct_from_template); tpl.title = T.ct_from_template;
                tpl.appendChild(icon('bi-card-heading'));
                tpl.addEventListener('click', function () { templatesPop(tpl, lane); });
                var fr = h('div', 'tl-frow'); fr.append(add, tpl);
                foot.appendChild(fr);
            }
            sec.append(head, box, foot);
            tb.appendChild(sec);
        });
        // the board's own bar: Filter (with how many are on) and Archived items
        var bar = h('div', 'tb-bar');
        var fb = h('button', 'tb-btn tb-filter'); fb.type = 'button'; fb.append(icon('bi-funnel'), h('span', null, T.ct_filter));
        fb.addEventListener('click', function () { filterPop(fb); });
        var ab = h('button', 'tb-btn'); ab.type = 'button'; ab.append(icon('bi-archive'), h('span', null, T.ct_archived));
        ab.addEventListener('click', function () { archivedPop(ab); });
        bar.append(fb, ab);
        $board.replaceChildren(bar, tb);
        syncFilter();
        tb.scrollLeft = keepX;
        fitBoard();
        if (window.Sortable) tb.querySelectorAll('.tl:not(.is-folded) .tl-cards').forEach(sortable);
        var open = tb.querySelector('.tl-compose textarea');
        if (open) { open.focus(); open.scrollIntoView({ block: 'nearest' }); }
    }
    /* The board fills what is left of the screen; each list scrolls inside it. */
    function fitBoard() {
        if (DESK || $board.hidden) return;
        var top = $board.getBoundingClientRect().top + window.scrollY;
        $board.style.setProperty('--tb-h', Math.max(320, window.innerHeight - top - 148) + 'px');
    }
    window.addEventListener('resize', fitBoard);

    /* One card, as Trello draws its front. */
    function card(n) {
        var full = n.cover_size === 'full' && (n.cover || n.cover_color);
        var c = h('article', 'tc' + (isDone(n) ? ' is-done' : '') + (full ? ' is-full' : '') + (full && n.cover ? ' is-photo' : ''));
        c.dataset.uuid = n.uuid; c.tabIndex = 0;
        c.setAttribute('aria-label', n.title || T.note_untitled);
        if (full) {
            // Trello's full cover: the colour (or the picture) IS the card, the title on it
            if (n.cover) { var bg = h('img', 'tc-bg'); bg.alt = ''; bg.loading = 'lazy'; bg.draggable = false; bg.src = coverUrl(n); c.appendChild(bg); }
            else { c.style.background = n.cover_color; c.style.color = inkOn(n.cover_color); }
            var ft = h('div', 'tc-in'); ft.appendChild(h('p', 'tc-title', n.title || T.note_untitled));
            c.appendChild(ft);
        } else {
            if (n.cover || n.cover_color || n.thumb || n.poster) {
                var cv = h('div', 'tc-cover' + (!n.cover && n.cover_color ? ' is-colour' : ''));
                if (!n.cover && n.cover_color) cv.style.background = n.cover_color;
                else {
                    var im = h('img'); im.alt = ''; im.loading = 'lazy'; im.draggable = false;
                    im.src = n.cover ? coverUrl(n) : (n.thumb ? picUrl(n.thumb, true) : mediaUrl(n.poster, true));
                    cv.appendChild(im);
                    if (!n.cover && !n.thumb) cv.appendChild(icon('bi-play-fill'));
                }
                c.appendChild(cv);
            }
            var inner = h('div', 'tc-in');
            if ((n.labels || []).length) {
                // labels: colour and name; a tap on one folds them all to thin bars (and back), as Trello does
                var lb = h('div', 'tc-labels' + (S.labelsWide ? '' : ' is-narrow'));
                n.labels.forEach(function (l) {
                    var x = labelBar(l, 'tc-label'); x.title = l.name || '';
                    x.addEventListener('click', function (e) { e.stopPropagation(); S.labelsWide = !S.labelsWide; try { localStorage.setItem('takenote.labels.narrow', S.labelsWide ? '0' : '1'); } catch (er) {} draw(); });
                    lb.appendChild(x);
                });
                inner.appendChild(lb);
            }
            var row = h('div', 'tc-row');
            var done = h('button', 'tc-done'); done.type = 'button';
            done.setAttribute('aria-label', isDone(n) ? T.tn_mark_open : T.tn_mark_done); done.title = done.getAttribute('aria-label');
            done.appendChild(icon('bi-check-lg'));
            done.addEventListener('click', function (e) { e.stopPropagation(); complete(n); });
            row.append(done, h('p', 'tc-title', n.title || T.note_untitled));
            inner.appendChild(row);

            var bd = h('div', 'tc-badges');
            if (n.template) { var tp = h('span', 'tc-tpl'); tp.append(icon('bi-card-heading'), document.createTextNode(T.ct_template)); bd.appendChild(tp); }
            if (hasMe(n.watchers)) bd.appendChild(badge('bi-eye', null, T.ct_watching));
            if (n.due_on || n.start_on) {
                // the dates badge: a tap ticks the card complete (and again opens it), as on Trello
                var du = h('button', 'tc-due ' + dueState(n)); du.type = 'button';
                du.title = n.due_on ? (isDone(n) ? T.tn_mark_open : T.tn_mark_done) : T.ct_dates;
                du.append(icon(isDone(n) ? 'bi-check-square-fill' : (n.due_on ? 'bi-clock' : 'bi-calendar3')), document.createTextNode(datesText(n)));
                if (n.due_on) du.addEventListener('click', function (e) { e.stopPropagation(); complete(n); });
                bd.appendChild(du);
            }
            if (n.excerpt) bd.appendChild(badge('bi-text-left', null, T.tn_has_text));
            if (n.replies) bd.appendChild(badge('bi-chat', n.replies, T.tn_comments));
            if (n.attachments) bd.appendChild(badge('bi-paperclip', n.attachments, T.tn_attachments));
            if (n.steps && n.steps[1]) { var ck = badge('bi-check2-square', n.steps[0] + '/' + n.steps[1], T.cb_checklist); if (n.steps[0] === n.steps[1]) ck.classList.add('is-all'); bd.appendChild(ck); }
            if (n.pictures) bd.appendChild(badge('bi-image', n.pictures, T.tn_pictures_in));
            if (n.voice) bd.appendChild(badge('bi-mic', n.voice, T.tn_voice_note));
            if (n.video) bd.appendChild(badge('bi-camera-video', n.video, T.tn_video_note));
            var people = n.members && n.members.length ? n.members : (n.assignee ? [n.assignee] : []);
            if (people.length) {
                var mm = h('span', 'tc-mem');
                people.slice(0, 3).forEach(function (pp) { mm.appendChild(avatar(pp)); });
                if (people.length > 3) mm.appendChild(h('span', 'av tc-more', '+' + (people.length - 3)));
                bd.appendChild(mm);
            }
            if (bd.childNodes.length) inner.appendChild(bd);
            c.appendChild(inner);
        }

        var ed = h('button', 'tc-edit'); ed.type = 'button';
        ed.setAttribute('aria-label', T.tn_quick_edit); ed.title = T.tn_quick_edit;
        ed.appendChild(icon('bi-pencil'));
        ed.addEventListener('click', function (e) { e.stopPropagation(); quickMenu(c, n.uuid); });
        c.appendChild(ed);

        c.addEventListener('click', function () { if (Date.now() - justDragged < 350) return; openNote(n.uuid, { card: true }); });
        c.addEventListener('keydown', function (e) { if (e.key === 'Enter' && e.target === c) openNote(n.uuid, { card: true }); });
        // a right-click: Trello's quick edit. A finger held on a phone fires the same event — that hold is the start
        // of a drag, so it never opens the menu (the ✎ on the card is the phone's way to it)
        c.addEventListener('contextmenu', function (e) { e.preventDefault(); if (Date.now() - lastTouch < 1200) return; quickMenu(c, n.uuid); });
        return c;
    }
    function badge(ic, count, label) {
        var b = h('span', 'tc-b'); b.title = label;
        b.appendChild(icon(ic));
        if (count != null) b.appendChild(document.createTextNode(String(count)));
        return b;
    }
    /* Trello's round "mark complete": a tick on the card; the card stays where it is. */
    function complete(n) { setDone(n.uuid, !isDone(n)); }

    /* "Add a card": the composer at the foot of a list. */
    function composer(lane, box) {
        var f = h('div', 'tl-compose');
        var ta = h('textarea'); ta.rows = 2; ta.maxLength = 300;
        ta.placeholder = T.tn_card_title_ph; ta.setAttribute('aria-label', T.tn_card_title_ph);
        var bar = h('div', 'tl-cbar');
        var go = h('button', 'tl-go', T.tn_add_card_go); go.type = 'button';
        var x = h('button', 'tl-x'); x.type = 'button'; x.setAttribute('aria-label', T.close); x.appendChild(icon('bi-x-lg'));
        function grow() { ta.style.height = 'auto'; ta.style.height = ta.scrollHeight + 'px'; }
        var emo = emojiFor(ta, grow); // the same emoji button as comments and checklist items (takenote/cardback)
        bar.append(go, x, h('span', 'cb-flex'), emo.btn);
        f.append(ta, bar, emo.box);
        ta.addEventListener('input', grow);
        function stop() { composing = null; unhook(); draw(); }
        function unhook() { if (composeAway) document.removeEventListener('pointerdown', composeAway, true); composeAway = null; }
        // A tap anywhere else closes it (adding what was typed) — one listener, however often the board redraws.
        function away(e) {
            if (!f.isConnected) { unhook(); return; }
            if (f.contains(e.target)) return;
            if (ta.value.trim()) submit();
            composing = null; unhook();
            setTimeout(draw, 0); // after this tap has landed on whatever it was meant for
        }
        unhook();
        composeAway = away;
        setTimeout(function () { if (composeAway === away) document.addEventListener('pointerdown', away, true); }, 0);
        function submit() {
            var title = ta.value.replace(/\s+/g, ' ').trim();
            if (!title) { ta.focus(); return; }
            ta.value = ''; grow();
            // the card shows at once, faded, while it is saved
            var tmp = h('article', 'tc is-pending'); var ti = h('div', 'tc-in'); var rw = h('div', 'tc-row');
            rw.appendChild(h('p', 'tc-title', title)); ti.appendChild(rw); tmp.appendChild(ti); box.appendChild(tmp);
            var esc = function (s) { return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); };
            api('POST', U.store, { html: '<h1>' + esc(title) + '</h1>', lane: lane.uuid }).then(function (r) {
                upsert(summaryOf(r.note), true);
            }).catch(function (err) { tmp.remove(); fail(err); });
        }
        ta.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) { e.preventDefault(); submit(); }
            if (e.key === 'Escape') { e.stopPropagation(); stop(); }
        });
        go.addEventListener('click', submit);
        x.addEventListener('click', stop);
        return f;
    }

    var slot = null;
    var lastTouch = 0;
    document.addEventListener('touchstart', function () { lastTouch = Date.now(); }, { passive: true, capture: true });

    /* Dragging a card sideways: near the left or right edge the board scrolls on its own — faster the closer
       to the edge — so the list being dragged towards comes into view (Trello's way; on a phone the lists
       sit one per screen). The card under the finger is re-aimed as the lists slide past. */
    var edge = { on: false, x: 0, y: 0, raf: 0 };
    function trackPointer(e) { var p = e.touches ? e.touches[0] : e; if (p) { edge.x = p.clientX; edge.y = p.clientY; } }
    function edgeLoop() {
        if (!edge.on) return;
        var tb = $board.querySelector('.tb');
        if (tb) {
            // at most ~one phone-wide list every half second, so the finger can stop on the one it wants
            var r = tb.getBoundingClientRect(), zone = Math.min(90, r.width * 0.18), top = r.width < 700 ? 10 : 16, v = 0;
            if (edge.x < r.left + zone) v = -Math.ceil(top * (1 - Math.max(0, edge.x - r.left) / zone));
            else if (edge.x > r.right - zone) v = Math.ceil(top * (1 - Math.max(0, r.right - edge.x) / zone));
            if (v) {
                var before = tb.scrollLeft;
                tb.scrollLeft += v;
                if (tb.scrollLeft !== before) document.dispatchEvent(new PointerEvent('pointermove', { bubbles: true, clientX: edge.x, clientY: edge.y, pointerType: 'touch', isPrimary: true }));
            }
        }
        edge.raf = requestAnimationFrame(edgeLoop);
    }
    function edgeStart() {
        edge.on = true;
        ['pointermove', 'touchmove', 'mousemove'].forEach(function (ev) { document.addEventListener(ev, trackPointer, { passive: true, capture: true }); });
        edge.raf = requestAnimationFrame(edgeLoop);
    }
    function edgeStop() {
        edge.on = false; cancelAnimationFrame(edge.raf);
        ['pointermove', 'touchmove', 'mousemove'].forEach(function (ev) { document.removeEventListener(ev, trackPointer, { capture: true }); });
    }

    function sortable(box) {
        sortables.push(Sortable.create(box, {
            group: 'takenote-lanes', draggable: '.tc', filter: '.tc-done, .tc-edit', preventOnFilter: false, // a card lifts from anywhere on it — its labels and date too (a tap on them still works: a drag needs movement)
            forceFallback: true, fallbackOnBody: true, fallbackTolerance: 5,
            // generous targets: an empty list, or the room under a list's last card, takes the card too
            emptyInsertThreshold: 60, swapThreshold: 0.65, invertSwap: true,
            delay: 220, delayOnTouchOnly: true, touchStartThreshold: 6,
            animation: REDUCE ? 0 : 150, easing: 'cubic-bezier(.2,.8,.2,1)',
            ghostClass: 'is-ghost', fallbackClass: 'is-lifted', chosenClass: 'is-chosen',
            scroll: true, scrollSensitivity: 80, scrollSpeed: 16, bubbleScroll: true,
            onChoose: function () { buzz(10); },
            onStart: function (e) { $board.classList.add('is-dragging'); trackPointer(e.originalEvent || {}); edgeStart(); },
            onMove: function (e) {
                $board.querySelectorAll('.tl.is-target').forEach(function (x) { x.classList.remove('is-target'); });
                var tl = e.to && e.to.closest('.tl'); if (tl) tl.classList.add('is-target');
            },
            onEnd: function (e) {
                edgeStop();
                $board.classList.remove('is-dragging');
                $board.querySelectorAll('.tl.is-target').forEach(function (x) { x.classList.remove('is-target'); });
                justDragged = Date.now();
                if (e.from === e.to && e.oldIndex === e.newIndex) return;
                placeFromDrop(e.item, e.to);
            }
        }));
    }
    /* Dropped: exactly where it was let go, in whichever list. */
    function placeFromDrop(el, box) {
        var n = S.cards.find(function (x) { return x.uuid === el.dataset.uuid; });
        var lane = S.lanes.find(function (l) { return l.uuid === box.dataset.lane; });
        if (!n || !lane) { draw(); return; }
        var prev = el.previousElementSibling, next = el.nextElementSibling;
        while (prev && !prev.dataset.uuid) prev = prev.previousElementSibling;
        while (next && !next.dataset.uuid) next = next.nextElementSibling;
        var a = prev && S.cards.find(function (x) { return x.uuid === prev.dataset.uuid; });
        var b = next && S.cards.find(function (x) { return x.uuid === next.dataset.uuid; });
        var was = { lane: n.lane, position: n.position };
        n.lane = { uuid: lane.uuid, name: lane.name, color: lane.color };
        n.position = a && b ? (a.position + b.position) / 2 : (a ? a.position + 1024 : (b ? b.position - 1024 : 1024));
        draw();
        api('POST', url('place', n.uuid), { lane: lane.uuid, above: a ? a.uuid : null, below: b ? b.uuid : null }).then(function (r) {
            upsert(r.note, true);
            if (was.lane && was.lane.uuid !== lane.uuid) toast(t('tn_moved', { lane: lane.name }));
        }).catch(function (err) { n.lane = was.lane; n.position = was.position; draw(); fail(err); });
    }

    /* ── Send to the board ──────────────────────────────────────────── */
    function iso(d) { return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); }
    function fromIso(s) { var p = s.split('-'); return new Date(+p[0], +p[1] - 1, +p[2]); }
    function addDays(n) { var d = new Date(); d.setHours(0, 0, 0, 0); d.setDate(d.getDate() + n); return d; }
    function dueLabel(s) {
        var d = fromIso(s), today = addDays(0), diff = Math.round((d - today) / 86400000);
        if (diff === 0) return { text: T.tn_today, cls: 'is-soon' };
        if (diff === 1) return { text: T.tn_tomorrow, cls: 'is-soon' };
        var text = d.toLocaleDateString(C.locale, { weekday: 'short' }) + ' ' + d.toLocaleDateString(C.locale, { day: 'numeric', month: 'short' });
        return { text: diff < 0 ? T.tn_overdue + ' · ' + text : text, cls: diff < 0 ? 'is-late' : '' };
    }

    function openSend(n, o) {
        o = o || {};
        if (!S.lanes.length || document.querySelector('.ss')) return;
        var first = S.lanes[0];
        var st = {
            lane: o.convert ? first.uuid : (n.lane && n.lane.uuid !== first.uuid ? n.lane.uuid : (o.preferNext && S.lanes[1] ? S.lanes[1].uuid : (n.lane ? n.lane.uuid : first.uuid))),
            who: n.assignee ? n.assignee.id : null,
            due: n.due_on || null
        };
        var before = { lane: n.lane ? n.lane.uuid : first.uuid, who: st.who, due: st.due };

        var root = h('div', 'ss'); root.setAttribute('role', 'dialog'); root.setAttribute('aria-modal', 'true'); root.setAttribute('aria-labelledby', 'ss-title');
        var dim = h('div', 'ss-dim');
        var sheet = h('div', 'ss-sheet');
        var band = h('div', 'ss-band');
        var row = h('div', 'r');
        var tl = h('span', 'tile2'); tl.appendChild(icon('bi-kanban'));
        var txt = h('div'); txt.style.minWidth = '0'; txt.style.flex = '1';
        var h3 = h('h3', null, o.convert ? T.tn_make_card : (o.quick ? T.tn_quick_edit : T.tn_send)); h3.id = 'ss-title';
        txt.append(h3, h('p', null, n.title || T.note_untitled));
        var x = h('button', 'x'); x.type = 'button'; x.setAttribute('aria-label', T.close); x.appendChild(icon('bi-x-lg'));
        row.append(tl, txt, x);
        band.append(h('div', 'hd'), row);

        var body = h('div', 'ss-body');
        body.appendChild(h('h4', null, T.tn_which_lane));
        var lanes = h('div', 'ss-lanes');
        var laneBtns = S.lanes.map(function (lane) {
            var b = h('button', 'ss-opt'); b.type = 'button'; b.style.setProperty('--c', lane.color);
            var count = S.cards.filter(function (m) { return m.lane && m.lane.uuid === lane.uuid; }).length;
            b.append(h('i'), document.createTextNode(lane.name), h('em', null, String(count)));
            b.addEventListener('click', function () { st.lane = lane.uuid; paint(); });
            lanes.appendChild(b); return { b: b, lane: lane };
        });
        body.appendChild(lanes);

        body.appendChild(h('h4', null, T.tn_who));
        var whoRow = h('div', 'ss-pills');
        var people = [S.me].concat(S.people.filter(function (p) { return p.id !== S.me.id; }));
        var whoBtns = people.map(function (p) {
            var b = h('button', 'ss-pill'); b.type = 'button';
            b.append(avatar(p), document.createTextNode(p.id === S.me.id ? T.tn_me : p.name));
            b.addEventListener('click', function () { st.who = p.id; paint(); });
            whoRow.appendChild(b); return { b: b, id: p.id };
        });
        var nobody = h('button', 'ss-pill', T.tn_nobody); nobody.type = 'button';
        nobody.addEventListener('click', function () { st.who = null; paint(); });
        whoRow.appendChild(nobody); whoBtns.push({ b: nobody, id: null });
        body.appendChild(whoRow);

        body.appendChild(h('h4', null, T.tn_when));
        var whenRow = h('div', 'ss-pills');
        var quick = [[T.tn_no_date, null], [T.tn_today, iso(addDays(0))], [T.tn_tomorrow, iso(addDays(1))], [T.tn_in_week, iso(addDays(7))]];
        var whenBtns = quick.map(function (q) {
            var b = h('button', 'ss-pill', q[0]); b.type = 'button';
            b.addEventListener('click', function () { st.due = q[1]; cal.hidden = true; paint(); });
            whenRow.appendChild(b); return { b: b, v: q[1] };
        });
        var pick = h('button', 'ss-pill'); pick.type = 'button';
        pick.append(icon('bi-calendar3'), document.createTextNode(T.tn_pick_day));
        pick.addEventListener('click', function () { cal.hidden = !cal.hidden; if (!cal.hidden) drawCal(); });
        whenRow.appendChild(pick);
        body.appendChild(whenRow);

        // a small month calendar: today ringed, past days off, day before month in every label
        var cal = h('div', 'ss-cal'); cal.hidden = true;
        var month = st.due ? fromIso(st.due) : addDays(0); month.setDate(1);
        function drawCal() {
            var top = h('div', 'top');
            var prev = h('button'); prev.type = 'button'; prev.setAttribute('aria-label', T.tn_prev_month); prev.appendChild(icon('bi-chevron-left flip'));
            var next = h('button'); next.type = 'button'; next.setAttribute('aria-label', T.tn_next_month); next.appendChild(icon('bi-chevron-right flip'));
            prev.addEventListener('click', function () { month.setMonth(month.getMonth() - 1); drawCal(); });
            next.addEventListener('click', function () { month.setMonth(month.getMonth() + 1); drawCal(); });
            top.append(prev, h('span', null, month.toLocaleDateString(C.locale, { month: 'long', year: 'numeric' })), next);
            var grid = h('div', 'grid');
            var monday = new Date(2024, 0, 1); // a Monday
            for (var i = 0; i < 7; i++) { var d0 = new Date(monday); d0.setDate(1 + i); grid.appendChild(h('span', null, d0.toLocaleDateString(C.locale, { weekday: 'narrow' }))); }
            var lead = (month.getDay() + 6) % 7, days = new Date(month.getFullYear(), month.getMonth() + 1, 0).getDate(), today = iso(addDays(0));
            for (var k = 0; k < lead; k++) grid.appendChild(h('span'));
            for (var dd = 1; dd <= days; dd++) {
                var day = new Date(month.getFullYear(), month.getMonth(), dd), v = iso(day);
                var b = h('button', null, String(dd)); b.type = 'button';
                if (v === today) b.classList.add('is-today');
                if (v === st.due) b.classList.add('is-on');
                if (v < today) b.disabled = true;
                b.setAttribute('aria-label', day.toLocaleDateString(C.locale, { weekday: 'long', day: 'numeric', month: 'long' }));
                (function (val) { b.addEventListener('click', function () { st.due = val; drawCal(); paint(); }); })(v);
                grid.appendChild(b);
            }
            cal.replaceChildren(top, grid);
        }
        body.appendChild(cal);

        var foot = h('div', 'ss-foot');
        var go = h('button', 'ss-go'); go.type = 'button';
        foot.appendChild(go);
        sheet.append(band, body, foot);
        root.append(dim, sheet);
        document.body.appendChild(root);

        function paint() {
            laneBtns.forEach(function (l) { l.b.classList.toggle('is-on', l.lane.uuid === st.lane); });
            whoBtns.forEach(function (w) { w.b.classList.toggle('is-on', w.id === st.who); });
            var custom = st.due && !quick.some(function (q) { return q[1] === st.due; });
            whenBtns.forEach(function (w) { w.b.classList.toggle('is-on', w.v === st.due); });
            pick.classList.toggle('is-on', !!custom);
            pick.lastChild.textContent = custom ? dueLabel(st.due).text : T.tn_pick_day;
            var lane = S.lanes.find(function (l) { return l.uuid === st.lane; });
            go.textContent = o.convert ? t('tn_make_card_in', { lane: lane ? lane.name : '' }) : (lane && lane.uuid !== before.lane ? t('tn_send_to', { lane: lane.name }) : T.tn_save_plan);
        }
        paint();

        function close() { document.removeEventListener('keydown', esc, true); root.remove(); }
        function esc(e) { if (e.key === 'Escape') { e.stopImmediatePropagation(); close(); } }
        document.addEventListener('keydown', esc, true);
        x.addEventListener('click', close);
        dim.addEventListener('click', close);

        go.addEventListener('click', function () {
            go.disabled = true;
            var lane = S.lanes.find(function (l) { return l.uuid === st.lane; });
            if (o.convert) {
                // the note becomes a card in that list, then takes its person, date and label
                api('POST', url('convert', n.uuid), { to: 'card', lane: st.lane }).then(function (r) {
                    var plan = {};
                    if (st.who !== before.who) plan.assignee = st.who;
                    if (st.due !== before.due) plan.due = st.due;
                    return Object.keys(plan).length ? api('POST', url('place', n.uuid), plan) : r;
                }).then(function (r) {
                    close();
                    upsert(r.note, true);
                    if (o.saved) o.saved(r.note);
                    buzz(10);
                    toast(t('tn_card_made', { lane: lane.name }), { action: T.tn_open_card, onAction: function () { openNote(n.uuid, { card: true }); } });
                }).catch(function (err) { go.disabled = false; fail(err); });
                return;
            }
            var payload = { assignee: st.who, due: st.due };
            if (st.lane !== before.lane) payload.lane = st.lane;
            api('POST', url('place', n.uuid), payload).then(function (r) {
                close();
                upsert(r.note, true);
                if (o.saved) o.saved(r.note);
                var whoName = r.note.assignee ? (r.note.assignee.id === S.me.id ? T.tn_me : r.note.assignee.name) : null;
                var msg = st.lane !== before.lane ? (whoName ? t('tn_sent_for', { lane: lane.name, name: whoName }) : t('tn_sent', { lane: lane.name })) : T.tn_plan_saved;
                buzz(10);
                toast(msg, { action: T.undo, onAction: function () {
                    var back = { assignee: before.who, due: before.due };
                    if (st.lane !== before.lane) back.lane = before.lane;
                    api('POST', url('place', n.uuid), back).then(function (u) { upsert(u.note, true); if (o.saved) o.saved(u.note); toast(T.restored); }).catch(fail);
                } });
            }).catch(function (err) { go.disabled = false; fail(err); });
        });
    }
