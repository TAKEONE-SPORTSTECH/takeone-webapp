{{--
    Margin's behaviour. Plain JS owning its own DOM: SortableJS moves real
    nodes, and this code reads the result from the DOM and reconciles state —
    no framework re-rendering the same nodes underneath the drag.

    Security: every server value is written with textContent / setAttribute,
    never innerHTML. The only innerHTML below is static SVG with no data in it,
    and a note's body, which the server has cleaned first (partials/notes).
--}}
<script>
(function () {
    'use strict';

    var D = JSON.parse(document.getElementById('board-data').textContent);
    var C = JSON.parse(document.getElementById('board-config').textContent);
    var T = C.t, U = C.urls;
    var CSRF = document.querySelector('meta[name=csrf-token]').content;
    var REDUCE = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var GAP = 1024;
    var PRIORITY = { low: '#94a3b8', normal: '#7c6bf5', high: '#f59e0b', urgent: '#ef4444' };

    var S = { me: D.me, lanes: D.lanes, cards: new Map(), bin: D.bin, filter: 'all', active: 0, open: null, justDragged: 0 };
    D.cards.forEach(function (c) { S.cards.set(c.uuid, c); });

    var nodes = new Map(), panels = new Map(), pills = new Map(), pads = new Map();
    var $track = document.getElementById('track'), $rail = document.getElementById('rail'),
        $pads = document.getElementById('dock-pads'), $toast = document.getElementById('toast'),
        $backdrop = document.getElementById('backdrop'), $cardSheet = document.getElementById('card-sheet'),
        $binSheet = document.getElementById('bin-sheet');

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
    function url(name, id) {
        return U[name].replace('__ID__', id).replace('00000000-0000-0000-0000-000000000000', id);
    }
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
    function fail(err) {
        toast(err && err.status === 419 ? T.expired : (err && err.status === 404 ? T.gone : T.error), { error: true });
    }
    function laneOf(uuid) { return S.lanes.find(function (l) { return l.uuid === uuid; }); }
    function cardsIn(lane) {
        var out = [];
        S.cards.forEach(function (c) { if (c.lane === lane && !c.deleted_at) out.push(c); });
        return out.sort(function (a, b) { return a.position - b.position; });
    }
    function visible(c) {
        switch (S.filter) {
            case 'mine': return c.author && c.author.id === S.me.id;
            case 'for_me': return c.assignee && c.assignee.id === S.me.id;
            case 'urgent': return c.priority === 'urgent' || c.priority === 'high';
            default: return true;
        }
    }
    function ago(iso) {
        if (!iso) return '';
        var s = (Date.now() - new Date(iso).getTime()) / 1000;
        if (s < 60) return T.now;
        if (s < 3600) return Math.floor(s / 60) + 'm';
        if (s < 86400) return Math.floor(s / 3600) + 'h';
        if (s < 604800) return Math.floor(s / 86400) + 'd';
        return new Date(iso).toLocaleDateString(C.locale, { day: 'numeric', month: 'short' });
    }
    function longDate(iso) {
        return iso ? new Date(iso).toLocaleDateString(C.locale, { day: 'numeric', month: 'short', year: 'numeric' }) : '';
    }
    function between(a, b) {
        if (a == null && b == null) return GAP;
        if (a == null) return b - GAP;
        if (b == null) return a + GAP;
        return (a + b) / 2;
    }
    function sigSvg(ink) {
        var ns = 'http://www.w3.org/2000/svg';
        var svg = document.createElementNS(ns, 'svg');
        svg.setAttribute('viewBox', '0 0 28 6'); svg.setAttribute('aria-hidden', 'true');
        var p = document.createElementNS(ns, 'path');
        p.setAttribute('d', 'M1 4 C6 1, 9 6, 14 3 S 22 1, 27 3');
        p.setAttribute('fill', 'none'); p.setAttribute('stroke', ink);
        p.setAttribute('stroke-width', '2'); p.setAttribute('stroke-linecap', 'round');
        svg.appendChild(p);
        return svg;
    }
    function settle(li) {
        if (!li || REDUCE) return;
        li.classList.remove('is-settling'); void li.offsetWidth; li.classList.add('is-settling');
        setTimeout(function () { li.classList.remove('is-settling'); }, 520);
    }
    function buzz(ms) { if (navigator.vibrate) { try { navigator.vibrate(ms); } catch (e) {} } }

    /* ── cards ───────────────────────────────────────────────────────── */
    function cardNode(c) {
        var li = nodes.get(c.uuid);
        if (!li) {
            li = h('div', 'card');
            li.tabIndex = 0;
            li.setAttribute('role', 'button');
            li.addEventListener('click', function () {
                if (Date.now() - S.justDragged < 350) return;
                openCard(li.dataset.uuid);
            });
            li.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openCard(li.dataset.uuid); }
            });
            nodes.set(c.uuid, li);
        }
        var lane = laneOf(c.lane);
        li.dataset.uuid = c.uuid;
        li.dataset.p = c.priority;
        li.classList.toggle('is-pending', !!c._pending);
        li.style.setProperty('--c', lane ? lane.color : '#7c6bf5');
        li.setAttribute('aria-label', c.title);
        li.replaceChildren(slip(c));
        return li;
    }
    function slip(c) {
        var s = h('div', 'slip');
        s.appendChild(h('p', 'slip-title', c.title));
        var m = h('div', 'slip-meta');
        if (c.author) {
            var g = h('span', 'sig');
            g.style.setProperty('--ink-c', c.author.ink);
            g.append(sigSvg(c.author.ink), document.createTextNode(c.author.id === S.me.id ? T.you : c.author.name));
            m.appendChild(g);
        }
        if (c.assignee && (!c.author || c.assignee.id !== c.author.id)) {
            var a = h('span', 'sig');
            a.style.setProperty('--ink-c', c.assignee.ink);
            a.append(icon('bi-arrow-right-short'), document.createTextNode(c.assignee.id === S.me.id ? T.you : c.assignee.name));
            m.appendChild(a);
        }
        if (c.priority === 'urgent') m.appendChild(h('span', 'slip-flag', T.priority_urgent));
        var n = c.counts || {};
        if (n.notes) m.appendChild(count('bi-journal-text', n.notes));
        if (n.images) m.appendChild(count('bi-image', n.images));
        if (n.sketches) m.appendChild(count('bi-brush', n.sketches));
        if (n.steps) m.appendChild(count('bi-check2-square', n.steps_done + '/' + n.steps));
        if (n.comments) m.appendChild(count('bi-chat', n.comments));
        if (c.due_label) m.appendChild(h('span', 'slip-due', c.due_label));
        m.appendChild(h('span', 'm age', ago(c.created_at)));
        s.appendChild(m);
        return s;
    }
    function count(ic, v) { var e = h('span', 'm'); e.append(icon(ic), document.createTextNode(String(v))); return e; }

    /* ── lanes ───────────────────────────────────────────────────────── */
    function sortableFor(list) {
        return Sortable.create(list, {
            group: { name: 'cards', pull: true, put: true },
            draggable: '.card',
            filter: '.is-pending', preventOnFilter: false,
            forceFallback: true, fallbackOnBody: true, fallbackTolerance: 4,
            delay: 170, delayOnTouchOnly: true, touchStartThreshold: 6,
            animation: REDUCE ? 0 : 220, easing: 'cubic-bezier(.2,.8,.2,1)',
            ghostClass: 'is-ghost', fallbackClass: 'is-lifted',
            scroll: true, scrollSensitivity: 90, scrollSpeed: 16, bubbleScroll: true,
            onChoose: function () { buzz(8); },
            onStart: startDrag,
            onMove: function (e) { hot(e.to); return true; },
            onEnd: endDrag
        });
    }
    function makePanel(lane) {
        var el = h('section', 'lane');
        el.dataset.lane = lane.uuid;
        el.setAttribute('role', 'tabpanel');

        var head = h('div', 'lane-head');
        var title = h('input', 'lane-title');
        title.type = 'text'; title.maxLength = 60; title.setAttribute('aria-label', T.rename_lane);
        title.setAttribute('enterkeyhint', 'done');
        title.style.fieldSizing = 'content';
        title.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') title.blur();
            if (e.key === 'Escape') { title.value = laneOf(lane.uuid).name; title.blur(); }
        });
        title.addEventListener('blur', function () { renameLane(lane.uuid, title); });
        var index = h('span', 'lane-index');
        var cnt = h('span', 'lane-count');
        head.append(title, index, cnt);

        var list = h('div', 'cards');
        list.setAttribute('role', 'list');
        var empty = h('div', 'empty');
        empty.innerHTML = '<svg viewBox="0 0 64 40" fill="none" aria-hidden="true"><path d="M4 30 C 16 8, 30 36, 42 18 S 58 10, 60 14" stroke="rgba(255,255,255,.35)" stroke-width="1.5" stroke-linecap="round"/><circle cx="42" cy="18" r="3.5" fill="#a78bfa"/></svg>';
        empty.append(h('p', null, T.empty), h('small', null, T.empty_hint));

        el.append(head, makeComposer(lane.uuid), list, empty);
        sortableFor(list);

        var p = { el: el, list: list, title: title, index: index, count: cnt };
        panels.set(lane.uuid, p);
        return p;
    }
    function makeComposer(laneUuid) {
        var wrap = h('div', 'composer');
        var btn = h('button', 'composer-btn');
        btn.type = 'button';
        btn.append(icon('bi-plus-lg'), document.createTextNode(T.capture));
        var form = h('div', 'composer-form');
        var ta = h('textarea');
        ta.rows = 2; ta.maxLength = 200; ta.placeholder = T.capture;
        ta.setAttribute('enterkeyhint', 'done');
        form.append(ta, h('span', 'composer-hint', T.capture_hint));
        wrap.append(btn, form);

        function close() { wrap.classList.remove('is-open'); ta.value = ''; }
        function submit() {
            var title = ta.value.replace(/\s+/g, ' ').trim();
            ta.value = '';
            if (title) createCard(laneUuid, title, ta);
        }
        btn.addEventListener('click', function () { wrap.classList.add('is-open'); ta.focus(); });
        ta.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); submit(); }
            else if (e.key === 'Escape') { close(); }
        });
        // Leaving the composer with words in it SAVES them — the whole point is not losing an idea.
        ta.addEventListener('blur', function () {
            setTimeout(function () {
                if (document.activeElement === ta) return;
                if (ta.value.trim()) submit();
                close();
            }, 120);
        });
        return wrap;
    }
    function buildLanes() {
        S.lanes.sort(function (a, b) { return a.position - b.position; });
        var seen = new Set();
        S.lanes.forEach(function (lane, i) {
            seen.add(lane.uuid);
            var p = panels.get(lane.uuid) || makePanel(lane);
            p.el.style.setProperty('--c', lane.color);
            p.index.textContent = String(i + 1).padStart(2, '0');
            if (document.activeElement !== p.title) p.title.value = lane.name;
            $track.appendChild(p.el);

            var pill = pills.get(lane.uuid);
            if (!pill) {
                pill = h('button', 'pill');
                pill.type = 'button';
                pill.setAttribute('role', 'tab');
                pill.dataset.lane = lane.uuid;
                pill.addEventListener('click', function () { goTo(indexOf(pill.dataset.lane), true); });
                pills.set(lane.uuid, pill);
            }
            pill.style.setProperty('--c', lane.color);
            pill.replaceChildren(h('span', 'dot'), document.createTextNode(lane.name), h('span', 'n', String(cardsIn(lane.uuid).length)));
            $rail.appendChild(pill);

            var pad = pads.get(lane.uuid);
            if (!pad) {
                pad = h('div', 'pad');
                pad.dataset.lane = lane.uuid;
                Sortable.create(pad, { group: { name: 'cards', pull: false, put: true }, sort: false, draggable: '.card', forceFallback: true });
                pads.set(lane.uuid, pad);
            }
            pad.style.setProperty('--c', lane.color);
            var label = h('div', 'pad-label');
            label.append(h('span', 'dot'), document.createTextNode(lane.name));
            pad.replaceChildren(label);
            $pads.appendChild(pad);
        });
        [panels, pills, pads].forEach(function (map) {
            map.forEach(function (v, k) { if (!seen.has(k)) { (v.el || v).remove(); map.delete(k); } });
        });
    }
    function renderLane(uuid) {
        var p = panels.get(uuid);
        if (!p) return;
        var all = cardsIn(uuid);
        var keep = new Set();
        all.filter(visible).forEach(function (c) {
            var li = cardNode(c);
            keep.add(li);
            p.list.appendChild(li);
        });
        Array.prototype.slice.call(p.list.children).forEach(function (ch) { if (!keep.has(ch)) ch.remove(); });
        p.count.textContent = all.length === 1 ? T.idea : t('ideas', { n: all.length });
        var pill = pills.get(uuid);
        if (pill && pill.lastChild) pill.lastChild.textContent = String(all.length);
    }
    function renderAll() {
        buildLanes();
        S.lanes.forEach(function (l) { renderLane(l.uuid); });
        renderTotals();
        setActive(S.active);
    }
    function renderTotals() {
        var total = 0;
        S.cards.forEach(function (c) { if (!c.deleted_at && !c._pending) total++; });
        document.getElementById('hero-count').textContent = total === 1 ? T.idea : t('ideas', { n: total });
        document.getElementById('bin-count').textContent = S.bin ? String(S.bin) : T.bin;
    }
    function indexOf(laneUuid) { return Math.max(0, S.lanes.findIndex(function (l) { return l.uuid === laneUuid; })); }

    /* ── lane navigation ─────────────────────────────────────────────── */
    function setActive(i) {
        S.active = Math.max(0, Math.min(i, S.lanes.length - 1));
        S.lanes.forEach(function (l, k) {
            var pill = pills.get(l.uuid);
            pill.classList.toggle('is-on', k === S.active);
            pill.setAttribute('aria-selected', k === S.active ? 'true' : 'false');
        });
        var on = pills.get((S.lanes[S.active] || {}).uuid);
        if (on) {
            var r = on.getBoundingClientRect(), rr = $rail.getBoundingClientRect();
            if (r.left < rr.left || r.right > rr.right) $rail.scrollBy({ left: r.left - rr.left - rr.width / 2 + r.width / 2, behavior: REDUCE ? 'auto' : 'smooth' });
        }
    }
    function goTo(i, smooth) {
        setActive(i);
        var w = $track.clientWidth;
        $track.scrollTo({ left: (C.rtl ? -1 : 1) * S.active * w, behavior: smooth && !REDUCE ? 'smooth' : 'auto' });
        var top = document.querySelector('.rail-wrap').offsetTop;
        if (window.scrollY > top) window.scrollTo({ top: top, behavior: smooth && !REDUCE ? 'smooth' : 'auto' });
    }
    var scrollTimer;
    $track.addEventListener('scroll', function () {
        clearTimeout(scrollTimer);
        scrollTimer = setTimeout(function () {
            var i = Math.round(Math.abs($track.scrollLeft) / Math.max(1, $track.clientWidth));
            if (i !== S.active) setActive(i);
        }, 70);
    }, { passive: true });
    window.addEventListener('resize', function () { goTo(S.active, false); });

    Sortable.create($rail, {
        draggable: '.pill', direction: 'horizontal', forceFallback: true, fallbackOnBody: true,
        delay: 260, delayOnTouchOnly: true, animation: REDUCE ? 0 : 200,
        onChoose: function () { buzz(8); },
        onEnd: function (e) {
            if (e.oldIndex === e.newIndex) return;
            var uuid = e.item.dataset.lane;
            var lane = laneOf(uuid), activeUuid = (S.lanes[S.active] || {}).uuid;
            var prev = e.item.previousElementSibling, next = e.item.nextElementSibling;
            var before = prev ? laneOf(prev.dataset.lane) : null, after = next ? laneOf(next.dataset.lane) : null;
            var old = lane.position;
            lane.position = between(before && before.position, after && after.position);
            renderAll(); goTo(indexOf(activeUuid), false);
            api('POST', url('laneMove', uuid), { before: before && before.uuid, after: after && after.uuid })
                .catch(function (err) { lane.position = old; renderAll(); goTo(indexOf(activeUuid), false); fail(err); });
        }
    });

    function renameLane(uuid, input) {
        var lane = laneOf(uuid);
        var name = input.value.replace(/\s+/g, ' ').trim();
        if (!lane || !name || name === lane.name) { input.value = lane ? lane.name : ''; return; }
        var old = lane.name;
        lane.name = name; buildLanes();
        api('PATCH', url('laneUpdate', uuid), { name: name })
            .then(function (r) { lane.name = r.lane.name; buildLanes(); toast(T.saved); })
            .catch(function (err) { lane.name = old; buildLanes(); fail(err); });
    }

    /* ── drag and drop ───────────────────────────────────────────────── */
    function startDrag(e) {
        var card = S.cards.get(e.item.dataset.uuid);
        var ev = e.originalEvent || {}, pt = (ev.touches && ev.touches[0]) || ev, r = e.item.getBoundingClientRect();
        if (pt.clientX != null) {
            document.body.style.setProperty('--grab-x', (pt.clientX - r.left) + 'px');
            document.body.style.setProperty('--grab-y', (pt.clientY - r.top) + 'px');
        }
        document.body.classList.add('is-dragging');
        pads.forEach(function (pad, uuid) { pad.hidden = card && uuid === card.lane; });
        document.getElementById('dock').setAttribute('aria-hidden', 'false');
    }
    function hot(to) {
        document.querySelectorAll('.is-hot, .is-over').forEach(function (n) { n.classList.remove('is-hot', 'is-over'); });
        document.body.classList.toggle('over-dock', !!(to && to.classList.contains('pad')));
        if (!to) return;
        if (to.classList.contains('pad')) to.classList.add('is-over');
        else if (to.closest('.lane')) to.closest('.lane').classList.add('is-hot');
    }
    function endDrag(e) {
        S.justDragged = Date.now();
        document.body.classList.remove('is-dragging');
        document.getElementById('dock').setAttribute('aria-hidden', 'true');
        hot(null);

        var li = e.item, card = S.cards.get(li.dataset.uuid);
        if (!card) { li.remove(); return; }

        if (e.to.classList.contains('pad')) {
            var pad = e.to;
            pad.classList.add('is-landed');
            setTimeout(function () { pad.classList.remove('is-landed'); }, 520);
            buzz(14);
            moveToTop(card, pad.dataset.lane);
            return;
        }
        var lane = e.to.closest('.lane');
        if (!lane) { renderLane(card.lane); return; }
        if (e.from === e.to && e.oldIndex === e.newIndex) { settle(li); return; }

        var prev = li.previousElementSibling, next = li.nextElementSibling;
        applyMove(card, lane.dataset.lane,
            prev && prev.dataset.uuid ? S.cards.get(prev.dataset.uuid) : null,
            next && next.dataset.uuid ? S.cards.get(next.dataset.uuid) : null);
    }
    function moveToTop(card, laneUuid) {
        var first = cardsIn(laneUuid).filter(function (c) { return c.uuid !== card.uuid; })[0] || null;
        applyMove(card, laneUuid, null, first);
    }
    function applyMove(card, laneUuid, above, below) {
        var was = { lane: card.lane, position: card.position };
        card.position = between(above && above.position, below && below.position);
        card.lane = laneUuid;
        renderLane(was.lane);
        if (laneUuid !== was.lane) renderLane(laneUuid);
        settle(nodes.get(card.uuid));
        if (S.open === card.uuid) renderCardSheet(card);

        return api('POST', url('move', card.uuid), { lane: laneUuid, above: above ? above.uuid : null, below: below ? below.uuid : null })
            .then(function (r) {
                merge(r.card);
                if (laneUuid !== was.lane) toast(r.message);
            })
            .catch(function (err) {
                card.lane = was.lane; card.position = was.position;
                renderLane(laneUuid); renderLane(was.lane);
                fail(err);
            });
    }
    function merge(server) {
        var c = S.cards.get(server.uuid);
        var oldLane = c ? c.lane : null;
        S.cards.set(server.uuid, Object.assign(c || {}, server, { _pending: false }));
        if (oldLane && oldLane !== server.lane) renderLane(oldLane);
        renderLane(server.lane);
        renderTotals();
        if (S.open === server.uuid) renderCardSheet(S.cards.get(server.uuid));
    }

    /* ── create ──────────────────────────────────────────────────────── */
    function createCard(laneUuid, title, ta) {
        var tmp = 'tmp-' + Math.random().toString(36).slice(2);
        var first = cardsIn(laneUuid)[0];
        S.cards.set(tmp, {
            uuid: tmp, lane: laneUuid, title: title, priority: 'normal', version: 1, _pending: true,
            position: first ? first.position - GAP : GAP, counts: {},
            author: { id: S.me.id, name: S.me.name, ink: S.me.ink }, created_at: new Date().toISOString()
        });
        renderLane(laneUuid);
        var li = nodes.get(tmp);
        li.classList.add('is-new');
        setTimeout(function () { li.classList.remove('is-new'); }, 600);

        api('POST', U.store, { lane: laneUuid, title: title })
            .then(function (r) {
                S.cards.delete(tmp);
                nodes.delete(tmp);
                nodes.set(r.card.uuid, li);
                merge(r.card);
            })
            .catch(function (err) {
                S.cards.delete(tmp); nodes.delete(tmp); li.remove();
                renderLane(laneUuid);
                if (ta && !ta.value) { ta.value = title; ta.closest('.composer').classList.add('is-open'); }
                fail(err);
            });
    }

    /* ── sheets ──────────────────────────────────────────────────────── */
    var openSheets = [];
    function openSheet(el) {
        if (openSheets.indexOf(el) === -1) openSheets.push(el);
        $backdrop.classList.add('is-on');
        requestAnimationFrame(function () { el.classList.add('is-open'); });
    }
    function closeSheet(el) {
        el = el || openSheets[openSheets.length - 1];
        if (!el) return;
        if (el === $noteSheet) closeNote();
        el.classList.remove('is-open');
        el.style.transform = '';
        openSheets = openSheets.filter(function (s) { return s !== el; });
        if (!openSheets.length) $backdrop.classList.remove('is-on');
        if (el === $cardSheet) S.open = null;
    }
    $backdrop.addEventListener('click', function () { closeSheet(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && openSheets.length) closeSheet(); });

    function band(color, ic, title, sub, sheet, titleId) {
        var b = h('div', 'band');
        b.style.background = 'linear-gradient(150deg, ' + color + ', ' + color + 'b0)';
        b.appendChild(h('span', 'band-orb'));
        b.appendChild(h('div', 'handle'));
        var row = h('div', 'band-row');
        var tile = h('span', 'tile'); tile.appendChild(icon(ic));
        var txt = h('div'); txt.style.minWidth = '0'; txt.style.flex = '1';
        var h3 = h('h3', null, title); h3.id = titleId;
        txt.append(h3, h('p', 'sub', sub));
        var x = h('button', 'x'); x.type = 'button'; x.setAttribute('aria-label', T.close); x.appendChild(icon('bi-x-lg'));
        x.addEventListener('click', function () { closeSheet(sheet); });
        row.append(tile, txt, x);
        b.appendChild(row);
        swipeToClose(b, sheet);
        return b;
    }
    function swipeToClose(handleEl, sheet) {
        var y0 = null, dy = 0;
        handleEl.addEventListener('pointerdown', function (e) {
            if (e.target.closest('button')) return;
            y0 = e.clientY; dy = 0; sheet.classList.add('is-dragged');
            handleEl.setPointerCapture(e.pointerId);
        });
        handleEl.addEventListener('pointermove', function (e) {
            if (y0 == null) return;
            dy = Math.max(0, e.clientY - y0);
            sheet.style.transform = 'translateY(' + dy + 'px)';
        });
        function up() {
            if (y0 == null) return;
            y0 = null; sheet.classList.remove('is-dragged');
            if (dy > 90) closeSheet(sheet); else sheet.style.transform = '';
        }
        handleEl.addEventListener('pointerup', up);
        handleEl.addEventListener('pointercancel', up);
    }

    /* The card sheet: title, priority, move, its notes, bin */
    function openCard(uuid) {
        var c = S.cards.get(uuid);
        if (!c || c._pending) return;
        S.open = uuid;
        renderCardSheet(c);
        openSheet($cardSheet);
        loadNotes(uuid);
    }
    function renderCardSheet(c) {
        var lane = laneOf(c.lane) || { name: '', color: '#7c6bf5' };
        var who = c.author ? (c.author.id === S.me.id ? T.you : c.author.name) : '';
        var sub = t('in_lane', { lane: lane.name }) + (who ? ' · ' + t('by', { name: who }) : '');
        var focused = document.activeElement && $cardSheet.contains(document.activeElement) && document.activeElement.classList.contains('title-input');

        if (focused) { // never yank the text out from under the person typing
            $cardSheet.querySelector('#card-sheet-title').textContent = c.title;
            return;
        }

        var body = h('div', 'sheet-body');

        body.appendChild(h('label', 'field-label', T.title_label));
        var ta = h('textarea', 'title-input');
        ta.rows = 2; ta.maxLength = 200; ta.value = c.title; ta.setAttribute('enterkeyhint', 'done');
        ta.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); ta.blur(); } });
        ta.addEventListener('blur', function () { saveTitle(c.uuid, ta); });
        body.appendChild(ta);

        body.appendChild(h('span', 'field-label', T.priority));
        var seg = h('div', 'seg');
        ['low', 'normal', 'high', 'urgent'].forEach(function (p) {
            var b = h('button'); b.type = 'button';
            b.style.setProperty('--pc', PRIORITY[p]);
            b.classList.toggle('is-on', c.priority === p);
            b.setAttribute('aria-pressed', c.priority === p ? 'true' : 'false');
            b.append(h('span', 'dot'), document.createTextNode(T['priority_' + p]));
            b.addEventListener('click', function () { setPriority(c.uuid, p); });
            seg.appendChild(b);
        });
        body.appendChild(seg);

        body.appendChild(h('span', 'field-label', T.move_to));
        var pick = h('div', 'lanes-pick');
        S.lanes.forEach(function (l) {
            var b = h('button'); b.type = 'button';
            b.style.setProperty('--c', l.color);
            b.setAttribute('aria-current', l.uuid === c.lane ? 'true' : 'false');
            b.append(h('span', 'dot'), document.createTextNode(l.name));
            b.addEventListener('click', function () { if (l.uuid !== c.lane) moveToTop(c, l.uuid); });
            pick.appendChild(b);
        });
        body.appendChild(pick);

        body.appendChild(notesSection(c));

        var soon = h('div', 'note-soon');
        soon.append(icon('bi-stars'), h('span', null, T.more_soon));
        body.appendChild(soon);

        var meta = h('div', 'meta-line');
        meta.append(h('span', null, longDate(c.created_at)));
        body.appendChild(meta);

        var del = h('button', 'danger'); del.type = 'button';
        del.append(icon('bi-trash3'), document.createTextNode(T.delete));
        del.addEventListener('click', function () { binCard(c.uuid); });
        body.appendChild(del);

        $cardSheet.replaceChildren(band(lane.color, 'bi-sticky', c.title, sub, $cardSheet, 'card-sheet-title'), body);
    }
    function saveTitle(uuid, ta) {
        var c = S.cards.get(uuid);
        var title = ta.value.replace(/\s+/g, ' ').trim();
        if (!c || !title || title === c.title) { if (c) ta.value = c.title; return; }
        var old = c.title;
        c.title = title; renderLane(c.lane);
        api('PATCH', url('update', uuid), { version: c.version, title: title })
            .then(function (r) { merge(r.card); toast(T.saved); })
            .catch(function (err) { conflictOr(err, c, function () { c.title = old; renderLane(c.lane); }); });
    }
    function setPriority(uuid, p) {
        var c = S.cards.get(uuid);
        if (!c || c.priority === p) return;
        var old = c.priority;
        c.priority = p; renderLane(c.lane); renderCardSheet(c);
        api('PATCH', url('update', uuid), { version: c.version, priority: p })
            .then(function (r) { merge(r.card); })
            .catch(function (err) { conflictOr(err, c, function () { c.priority = old; renderLane(c.lane); renderCardSheet(c); }); });
    }
    function conflictOr(err, c, revert) {
        if (err.status === 409 && err.data && err.data.card) {
            merge(err.data.card);
            toast(T.conflict, { error: true });
            if (S.open === c.uuid) { var ta = $cardSheet.querySelector('.title-input'); if (ta) ta.blur(); renderCardSheet(S.cards.get(c.uuid)); }
            return;
        }
        revert(); fail(err);
    }
    function binCard(uuid) {
        var c = S.cards.get(uuid);
        if (!c) return;
        c.deleted_at = new Date().toISOString();
        closeSheet($cardSheet);
        renderLane(c.lane); renderTotals();
        api('DELETE', url('destroy', uuid))
            .then(function (r) {
                S.bin = r.bin; renderTotals();
                toast(T.binned, { action: T.undo, onAction: function () { restoreCard(uuid); } });
            })
            .catch(function (err) { c.deleted_at = null; renderLane(c.lane); renderTotals(); fail(err); });
    }
    function restoreCard(uuid, row) {
        return api('POST', url('restore', uuid))
            .then(function (r) {
                S.bin = r.bin;
                var c = S.cards.get(uuid);
                if (c) c.deleted_at = null;
                merge(r.card);
                if (row) row.remove();
                toast(T.restored);
                goTo(indexOf(r.card.lane), true);
            })
            .catch(fail);
    }

    /* The Bin */
    document.getElementById('bin-chip').addEventListener('click', function () {
        var body = h('div', 'sheet-body');
        body.appendChild(h('div', 'bin-empty', '…'));
        $binSheet.replaceChildren(band('#7c6bf5', 'bi-trash3', T.bin_title, T.bin_sub, $binSheet, 'bin-sheet-title'), body);
        openSheet($binSheet);
        api('GET', U.bin).then(function (r) {
            body.replaceChildren();
            if (!r.cards.length) { body.appendChild(h('div', 'bin-empty', T.bin_empty)); return; }
            r.cards.forEach(function (c) {
                var row = h('div', 'bin-row');
                var tt = h('div', 't', c.title);
                tt.appendChild(h('span', 's', (c.lane_name || '') + ' · ' + longDate(c.deleted_at)));
                var b = h('button'); b.type = 'button';
                b.append(icon('bi-arrow-counterclockwise'), document.createTextNode(T.restore));
                b.addEventListener('click', function () { b.disabled = true; restoreCard(c.uuid, row).then(function () { if (!body.querySelector('.bin-row')) body.appendChild(h('div', 'bin-empty', T.bin_empty)); }); });
                row.append(tt, b);
                body.appendChild(row);
            });
        }).catch(function (err) { closeSheet($binSheet); fail(err); });
    });

    /* ── filters ─────────────────────────────────────────────────────── */
    document.getElementById('filters').addEventListener('click', function (e) {
        var b = e.target.closest('[data-filter]');
        if (!b) return;
        S.filter = b.dataset.filter;
        document.querySelectorAll('[data-filter]').forEach(function (f) { f.classList.toggle('is-on', f === b); });
        S.lanes.forEach(function (l) { renderLane(l.uuid); });
    });

    /* ── toast ───────────────────────────────────────────────────────── */
    var toastTimer;
    function toast(msg, o) {
        o = o || {};
        $toast.replaceChildren(h('span', null, msg));
        $toast.classList.toggle('is-error', !!o.error);
        if (o.action) {
            var b = h('button', 'act', o.action); b.type = 'button';
            b.addEventListener('click', function () { hideToast(); o.onAction(); });
            $toast.appendChild(b);
        }
        $toast.classList.add('is-on');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(hideToast, o.action ? 5500 : 2400);
    }
    function hideToast() { $toast.classList.remove('is-on'); }

    /* ── stay current when coming back to the page ───────────────────── */
    document.addEventListener('visibilitychange', function () {
        if (document.hidden || document.body.classList.contains('is-dragging') || openSheets.length) return;
        api('GET', U.data).then(function (r) {
            S.lanes = r.lanes; S.bin = r.bin;
            var pending = [];
            S.cards.forEach(function (c) { if (c._pending) pending.push(c); });
            S.cards = new Map();
            r.cards.concat(pending).forEach(function (c) { S.cards.set(c.uuid, c); });
            renderAll();
            goTo(S.active, false);
        }).catch(function () {});
    });

@include('devboard::partials.notes')

    renderAll();
})();
</script>
