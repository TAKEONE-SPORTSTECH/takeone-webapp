{{--
    Family-tree runtime — shared by the mobile and desktop views.
    Pure logic + node styling (no page chrome), so editing a page layout can
    never break the renderer. Included INLINE inside the content section so it
    re-executes after a mobile-shell AJAX swap. Guarded so re-running is safe.

    Public API (window.FamilyTree):
      mount(cfg)  — cfg: { viewportId, dataUrl, addUrl, respondUrl, rootPersonId, csrf, focus? }
      reload()    — re-fetch the current focus (call after adding a relative)
      recenter(id)— focus the tree on a person id
--}}

{{-- Node visual system — injected once, shared by both devices for a unified look. --}}
<style id="ft-styles">
    .ft-viewport{position:relative;overflow:hidden;touch-action:none;cursor:grab;background:
        radial-gradient(circle at 1px 1px, hsl(250 30% 88% / .55) 1px, transparent 0) 0 0/22px 22px,
        linear-gradient(180deg, hsl(250 40% 98%), hsl(220 30% 96%));}
    .ft-viewport:active{cursor:grabbing;}
    .ft-canvas{position:absolute;top:0;left:0;transform-origin:0 0;will-change:transform;}
    .ft-links{position:absolute;top:0;left:0;pointer-events:none;overflow:visible;}
    .ft-rows{position:relative;display:flex;flex-direction:column;gap:64px;padding:60px;width:max-content;}
    .ft-row{display:flex;justify-content:center;align-items:flex-start;gap:26px;}

    .ft-node{position:relative;display:flex;flex-direction:column;align-items:center;width:104px;
        cursor:pointer;user-select:none;opacity:0;transform:translateY(10px) scale(.96);
        animation:ftPop .42s cubic-bezier(.2,.8,.2,1) forwards;}
    @keyframes ftPop{to{opacity:1;transform:none;}}
    .ft-node:hover .ft-avatar{transform:translateY(-2px);box-shadow:0 10px 24px hsl(250 40% 40% / .28);}

    .ft-avatar-wrap{position:relative;display:inline-flex;flex:0 0 auto;}

    .ft-avatar{width:66px;height:66px;border-radius:9999px;overflow:hidden;flex:0 0 auto;
        display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.7rem;
        background:hsl(250 55% 60%);box-shadow:0 4px 12px hsl(250 40% 40% / .18);
        border:3px solid #fff;transition:transform .2s,box-shadow .2s;}
    .ft-avatar img{width:100%;height:100%;object-fit:cover;}

    .ft-node[data-focus="1"] .ft-avatar{border-color:hsl(250 65% 65%);
        box-shadow:0 0 0 4px hsl(250 65% 65% / .28),0 8px 22px hsl(250 40% 40% / .3);width:78px;height:78px;}
    .ft-node[data-pending="1"] .ft-avatar{border-style:dashed;border-color:hsl(38 92% 55%);}
    .ft-node[data-deceased="1"] .ft-avatar{filter:grayscale(.7) opacity(.85);}

    .ft-name{margin-top:8px;font-size:.78rem;font-weight:600;color:hsl(220 20% 20%);
        text-align:center;line-height:1.15;max-width:104px;overflow:hidden;text-overflow:ellipsis;
        display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;
        background:#fff;padding:1px 6px;border-radius:6px;}
    .ft-rel{margin-top:3px;font-size:.6rem;font-weight:600;letter-spacing:.02em;
        color:hsl(250 45% 55%);background:hsl(250 60% 95%);padding:1px 7px;border-radius:9999px;
        text-transform:capitalize;white-space:nowrap;}
    .ft-node[data-root="1"] .ft-rel{color:#fff;background:hsl(250 65% 65%);}
    .ft-years{margin-top:2px;font-size:.58rem;color:hsl(220 12% 55%);
        background:#fff;padding:0 6px;border-radius:6px;}

    .ft-badge{position:absolute;top:-4px;right:14px;width:20px;height:20px;border-radius:9999px;
        display:flex;align-items:center;justify-content:center;font-size:.62rem;color:#fff;
        border:2px solid #fff;box-shadow:0 2px 6px rgb(0 0 0 / .18);}
    .ft-badge.pending{background:hsl(38 92% 52%);}
    .ft-badge.deceased{background:hsl(220 10% 55%);}

    .ft-manage{position:absolute;top:-4px;left:14px;width:20px;height:20px;border-radius:9999px;
        display:flex;align-items:center;justify-content:center;font-size:.62rem;color:hsl(220 10% 40%);
        background:#fff;border:2px solid hsl(220 15% 90%);box-shadow:0 2px 6px rgb(0 0 0 / .12);
        cursor:pointer;padding:0;}
    .ft-manage:hover{background:hsl(220 15% 96%);}

    .ft-flag{position:absolute;top:-2px;right:-2px;width:22px;height:16px;border-radius:3px;
        overflow:hidden;border:1.5px solid #fff;box-shadow:0 1px 4px rgb(0 0 0 / .35);background:#eee;
        z-index:1;}
    .ft-flag .fi{width:100%;height:100%;display:block;background-size:cover;background-position:50%;background-repeat:no-repeat;}

    .ft-actions{margin-top:8px;display:flex;gap:6px;}
    .ft-actions button{border:0;cursor:pointer;font-size:.62rem;font-weight:600;padding:4px 9px;
        border-radius:9999px;display:inline-flex;align-items:center;gap:3px;transition:transform .12s,filter .12s;}
    .ft-actions button:active{transform:scale(.94);}
    .ft-yes{background:hsl(145 60% 42%);color:#fff;}
    .ft-no{background:hsl(0 0% 94%);color:hsl(0 65% 45%);}

    .ft-empty{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;
        justify-content:center;text-align:center;padding:2rem;color:hsl(220 15% 45%);}
</style>

<script>
window.FamilyTree = window.FamilyTree || (function () {
    'use strict';

    const GENDER_BG = { m: 'hsl(250 55% 60%)', f: 'hsl(330 62% 62%)' };
    const el = (t, c) => { const n = document.createElement(t); if (c) n.className = c; return n; };

    let S = null; // active instance state

    function mount(cfg) {
        const vp = document.getElementById(cfg.viewportId);
        if (!vp) return;
        vp.classList.add('ft-viewport');
        vp.innerHTML = '';
        const canvas = el('div', 'ft-canvas');
        const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.setAttribute('class', 'ft-links');
        const rows = el('div', 'ft-rows');
        canvas.append(svg, rows);
        vp.appendChild(canvas);

        S = { cfg, vp, canvas, svg, rows, focus: cfg.focus || null,
              scale: 1, tx: 0, ty: 0, drag: null, pinch: null };

        bindPanZoom();
        load(S.focus);
    }

    function reload() { if (S) load(S.focus); }
    function recenter(id) { if (S) { S.focus = id; load(id); } }

    // Confirmed spouses of a person, from the last loaded window — used to
    // offer "who's the other parent?" when adding a child to someone with 2+
    // spouses, so their kids don't get mixed under one undifferentiated parent.
    function spousesOf(id) {
        if (!S || !S.data) return [];
        return S.data.unions
            .filter(u => u.status === 'confirmed' && (u.a === id || u.b === id))
            .map(u => {
                const otherId = u.a === id ? u.b : u.a;
                const n = S.data.nodes.find(x => x.id === otherId);
                return n ? { id: otherId, name: n.name } : null;
            })
            .filter(Boolean);
    }

    // Every relationship edge connecting a person to the currently-loaded
    // window — used by the "manage relationships" sheet so a mistaken or
    // duplicate relative can be unlinked directly from the tree.
    function edgesOf(id) {
        if (!S || !S.data) return [];
        const byId = {}; S.data.nodes.forEach(n => byId[n.id] = n);
        const out = [];
        S.data.parentEdges.forEach(e => {
            if (e.p === id && byId[e.c]) out.push({ edge_type: 'parent', edge_id: e.id, label: 'Parent of', name: byId[e.c].name, status: e.status });
            if (e.c === id && byId[e.p]) out.push({ edge_type: 'parent', edge_id: e.id, label: 'Child of', name: byId[e.p].name, status: e.status });
        });
        S.data.unions.forEach(e => {
            if (e.a === id || e.b === id) {
                const otherId = e.a === id ? e.b : e.a;
                if (byId[otherId]) out.push({ edge_type: 'union', edge_id: e.id, label: 'Spouse of', name: byId[otherId].name, status: e.status });
            }
        });
        return out;
    }

    async function load(focusId) {
        const url = new URL(S.cfg.dataUrl, window.location.origin);
        if (focusId) url.searchParams.set('focus', focusId);
        try {
            const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!res.ok) throw new Error('load failed');
            render(await res.json());
        } catch (e) {
            S.rows.innerHTML = '';
            const box = el('div', 'ft-empty');
            box.innerHTML = '<i class="bi bi-diagram-3 text-4xl mb-2"></i><div>Could not load your family tree.</div>';
            S.canvas.appendChild(box);
        }
    }

    // -----------------------------------------------------------------
    // Layout + render
    // -----------------------------------------------------------------
    function render(data) {
        S.data = data; // kept for later lookups (e.g. spousesOf() for the add-relative form)
        S.focus = data.focus;
        S.root = data.root;
        const byId = {}; data.nodes.forEach(n => byId[n.id] = n);

        // Relationship maps.
        const childrenOf = {}, parentsOf = {}, partnersOf = {};
        data.parentEdges.forEach(e => {
            (childrenOf[e.p] = childrenOf[e.p] || []).push(e.c);
            (parentsOf[e.c] = parentsOf[e.c] || []).push(e.p);
        });
        data.unions.forEach(e => {
            (partnersOf[e.a] = partnersOf[e.a] || []).push(e.b);
            (partnersOf[e.b] = partnersOf[e.b] || []).push(e.a);
        });

        // Group nodes into generational rows by depth.
        const depths = [...new Set(data.nodes.map(n => n.depth))].sort((a, b) => a - b);
        const rowOf = {}; depths.forEach(d => rowOf[d] = []);
        data.nodes.forEach(n => rowOf[n.depth].push(n.id));

        // Order key: seed focus row, then propagate down (by parents) and up (by children).
        const key = {};
        orderSeedRow(rowOf[0] || [], byId, partnersOf, key);
        depths.filter(d => d > 0).sort((a, b) => a - b)
              .forEach(d => orderRelative(rowOf[d], parentsOf, partnersOf, key));
        depths.filter(d => d < 0).sort((a, b) => b - a)
              .forEach(d => orderRelative(rowOf[d], childrenOf, partnersOf, key));

        // Build the DOM.
        S.rows.innerHTML = '';
        const answerable = {};
        [...data.parentEdges, ...data.unions].forEach(e => {
            if (e.can_respond) {
                const other = e.type === 'parent'
                    ? (e.p === S.root ? e.c : e.p)
                    : (e.a === S.root ? e.b : e.a);
                answerable[other] = { type: e.type, id: e.id };
            }
        });

        depths.forEach((d, ri) => {
            const row = el('div', 'ft-row');
            row.dataset.depth = d;
            rowOf[d].sort((x, y) => (key[x] ?? 0) - (key[y] ?? 0))
                    .forEach(id => row.appendChild(buildNode(byId[id], ri, answerable[id])));
            S.rows.appendChild(row);
        });

        requestAnimationFrame(() => { drawLinks(data, byId, partnersOf); centerOnFocus(); });
    }

    // Split a person's spouses across BOTH sides instead of bunching them all
    // on one side — e.g. two co-wives of the same man sit one on his left, one
    // on his right. Each mother's children then hang straight down from her
    // own position (see orderRelative's parent-averaging below), so the
    // connector lines fan out cleanly instead of crossing through the other
    // wife's column to reach her kids.
    function spreadPartners(centerId, partners, baseKey) {
        const placement = {};
        partners.forEach((p, i) => {
            const side = i % 2 === 0 ? 1 : -1;      // alternate right, left, right, left…
            const step = Math.floor(i / 2) + 1;      // 1st pair ±1, 2nd pair ±2, …
            placement[p] = baseKey + side * step;
        });
        return placement;
    }

    function orderSeedRow(ids, byId, partnersOf, key) {
        // Focus centered, its partners spread evenly on both sides, then any
        // remaining peers (e.g. siblings sharing this row) after that.
        const focus = ids.find(id => byId[id] && byId[id].is_focus);
        if (!focus) {
            ids.forEach((id, i) => key[id] = i - (ids.length - 1) / 2);
            return;
        }

        const partners = (partnersOf[focus] || []).filter(p => ids.includes(p));
        const placement = spreadPartners(focus, partners, 0);
        key[focus] = 0;
        Object.assign(key, placement);

        // Anything else in the row (rare — e.g. a sibling of the focus) goes
        // after the widest spread partner, evenly spaced.
        const used = new Set([focus, ...partners]);
        const rest = ids.filter(id => !used.has(id));
        const maxSide = partners.length ? Math.max(...Object.values(placement).map(Math.abs)) : 0;
        rest.forEach((id, i) => key[id] = maxSide + 1 + i);
    }

    function orderRelative(ids, upMap, partnersOf, key) {
        if (!ids || !ids.length) return;
        // Each node inherits the average key of its parents/children one row over
        // — so a child's default position is already between ITS OWN two
        // recorded parents, not some other couple's midpoint.
        ids.forEach(id => {
            const refs = (upMap[id] || []).filter(r => r in key);
            key[id] = refs.length ? refs.reduce((s, r) => s + key[r], 0) / refs.length : (key[id] ?? 0);
        });
        // Spread each person's spouses across both sides of them too (same
        // reasoning as the seed row), instead of the old "always adjacent"
        // nudge that bunched multiple spouses on one side.
        const adjusted = new Set();
        ids.forEach(id => {
            if (adjusted.has(id)) return;
            const partners = (partnersOf[id] || []).filter(p => ids.includes(p) && !adjusted.has(p));
            if (!partners.length) return;
            Object.assign(key, spreadPartners(id, partners, key[id]));
            partners.forEach(p => adjusted.add(p));
            adjusted.add(id);
        });
        // De-collide equal keys.
        const seen = {};
        ids.slice().sort((a, b) => key[a] - key[b]).forEach(id => {
            while (key[id] in seen) key[id] += 0.001;
            seen[key[id]] = 1;
        });
    }

    function buildNode(n, rowIndex, answerable) {
        const node = el('div', 'ft-node');
        node.dataset.id = n.id;
        if (n.is_focus) node.dataset.focus = '1';
        if (n.is_root) node.dataset.root = '1';
        if (n.pending) node.dataset.pending = '1';
        if (n.deceased) node.dataset.deceased = '1';
        node.style.animationDelay = (rowIndex * 60) + 'ms';

        const avWrap = el('div', 'ft-avatar-wrap');
        const av = el('div', 'ft-avatar');
        if (n.avatar) { const img = el('img'); img.src = n.avatar; img.alt = ''; av.appendChild(img); }
        else { av.style.background = GENDER_BG[n.gender] || 'hsl(220 10% 60%)';
               av.innerHTML = '<i class="bi bi-person-fill"></i>'; }
        avWrap.appendChild(av);

        if (n.nationality) {
            const code = String(n.nationality).replace(/[^a-z]/g, '').slice(0, 2);
            if (code.length === 2) {
                const fl = el('div', 'ft-flag');
                const span = document.createElement('span');
                span.className = 'fi fi-' + code; // built via className, never innerHTML — can't inject markup
                fl.appendChild(span);
                avWrap.appendChild(fl); // anchored to the avatar's own box, sits right on its border
            }
        }

        node.appendChild(avWrap);

        if (n.pending) { const b = el('div', 'ft-badge pending'); b.innerHTML = '<i class="bi bi-hourglass-split"></i>'; node.appendChild(b); }
        else if (n.deceased) { const b = el('div', 'ft-badge deceased'); b.innerHTML = '<i class="bi bi-flower1"></i>'; node.appendChild(b); }

        // Manage (unlink) — every relative except yourself can be corrected/removed.
        if (!n.is_root) {
            const mg = el('button', 'ft-manage'); mg.type = 'button'; mg.setAttribute('aria-label', 'Manage relationship');
            mg.innerHTML = '<i class="bi bi-three-dots"></i>';
            mg.addEventListener('click', ev => {
                ev.stopPropagation();
                S.vp.dispatchEvent(new CustomEvent('ft:manage', { detail: { personId: n.id, personName: n.name, edges: edgesOf(n.id) }, bubbles: true }));
            });
            node.appendChild(mg);
        }

        const name = el('div', 'ft-name'); name.textContent = n.name; node.appendChild(name);
        if (n.label) { const r = el('div', 'ft-rel'); r.textContent = n.label; node.appendChild(r); }
        if (n.birth_year || n.death_year) {
            const y = el('div', 'ft-years');
            y.textContent = (n.birth_year || '?') + (n.deceased || n.death_year ? ' – ' + (n.death_year || '') : '');
            node.appendChild(y);
        }

        // Confirm / decline for requests aimed at me.
        if (answerable) {
            const acts = el('div', 'ft-actions');
            const yes = el('button', 'ft-yes'); yes.innerHTML = '<i class="bi bi-check-lg"></i>';
            const no = el('button', 'ft-no'); no.innerHTML = '<i class="bi bi-x-lg"></i>';
            yes.onclick = ev => { ev.stopPropagation(); respond(answerable, 'confirm'); };
            no.onclick = ev => { ev.stopPropagation(); respond(answerable, 'reject'); };
            acts.append(yes, no); node.appendChild(acts);
        }

        node.addEventListener('click', ev => {
            if (S.moved) return; // was a drag, not a tap
            if (n.is_focus) {
                S.vp.dispatchEvent(new CustomEvent('ft:add', { detail: { focusId: n.id, focusName: n.name, spouses: spousesOf(n.id) }, bubbles: true }));
            } else {
                recenter(n.id);
            }
        });
        return node;
    }

    // -----------------------------------------------------------------
    // Connector lines (measured from the laid-out DOM → always correct)
    // -----------------------------------------------------------------
    function drawLinks(data, byId, partnersOf) {
        const svg = S.svg;
        while (svg.firstChild) svg.removeChild(svg.firstChild);
        const W = S.rows.scrollWidth, H = S.rows.scrollHeight;
        svg.setAttribute('width', W); svg.setAttribute('height', H);
        const cRect = S.canvas.getBoundingClientRect();
        const center = id => {
            const nEl = S.rows.querySelector('.ft-node[data-id="' + id + '"]');
            if (!nEl) return null;
            const av = nEl.querySelector('.ft-avatar').getBoundingClientRect();
            return { cx: (av.left + av.width / 2 - cRect.left) / S.scale,
                     top: (av.top - cRect.top) / S.scale,
                     bot: (av.bottom - cRect.top) / S.scale };
        };
        const line = (d, cls) => {
            const p = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            p.setAttribute('d', d);
            p.setAttribute('fill', 'none');
            p.setAttribute('stroke', cls === 'pending' ? 'hsl(38 90% 60%)' : 'hsl(250 30% 78%)');
            p.setAttribute('stroke-width', '2');
            p.setAttribute('stroke-linecap', 'round');
            if (cls === 'pending') p.setAttribute('stroke-dasharray', '5 5');
            svg.appendChild(p);
        };

        // Union connectors (short horizontal between partners).
        data.unions.forEach(e => {
            const a = center(e.a), b = center(e.b); if (!a || !b) return;
            const y = (a.top + a.bot) / 2;
            line('M' + a.cx + ' ' + y + ' H' + b.cx, e.status === 'pending' ? 'pending' : '');
        });

        // Parent→child connectors; a couple's children hang from the couple midpoint.
        const drawn = {};
        data.parentEdges.forEach(e => {
            const child = center(e.c); if (!child) return;
            const parents = (byId[e.c] ? data.parentEdges.filter(x => x.c === e.c).map(x => x.p) : [e.p])
                .filter(pid => center(pid));
            const coupleKey = e.c + ':' + parents.slice().sort().join(',');
            if (drawn[coupleKey]) return; drawn[coupleKey] = 1;

            let sx, sy;
            const twoPartnered = parents.length === 2 && (partnersOf[parents[0]] || []).includes(parents[1]);
            if (twoPartnered) {
                const p0 = center(parents[0]), p1 = center(parents[1]);
                sx = (p0.cx + p1.cx) / 2; sy = (p0.bot + p1.bot) / 2;
            } else {
                const p0 = center(parents[0]); sx = p0.cx; sy = p0.bot;
            }
            const midY = (sy + child.top) / 2;
            const pending = e.status === 'pending' ? 'pending' : '';
            line('M' + sx + ' ' + sy + ' V' + midY + ' H' + child.cx + ' V' + child.top, pending);
        });
    }

    // -----------------------------------------------------------------
    // Pan + zoom
    // -----------------------------------------------------------------
    function apply() { S.canvas.style.transform = 'translate(' + S.tx + 'px,' + S.ty + 'px) scale(' + S.scale + ')'; }

    function centerOnFocus() {
        const f = S.rows.querySelector('.ft-node[data-focus="1"]') || S.rows.querySelector('.ft-node');
        if (!f) return;
        // Measure the avatar's CURRENT position in viewport space, then nudge the
        // existing transform so it lands centred — robust to offsetParent chains.
        const vpRect = S.vp.getBoundingClientRect();
        const av = f.querySelector('.ft-avatar').getBoundingClientRect();
        S.tx += (S.vp.clientWidth / 2) - (av.left + av.width / 2 - vpRect.left);
        S.ty += (S.vp.clientHeight * 0.4) - (av.top + av.height / 2 - vpRect.top);
        apply();
    }

    function clampScale(s) { return Math.min(2.2, Math.max(0.35, s)); }
    function zoomTo(target, px, py) {
        const ns = clampScale(target);
        const k = ns / S.scale;
        S.tx = px - (px - S.tx) * k;
        S.ty = py - (py - S.ty) * k;
        S.scale = ns; apply();
    }
    function zoomAt(factor, px, py) { zoomTo(S.scale * factor, px, py); }

    // Two input paths, cleanly separated so they never double-fire:
    //   • Touch (fingers)  → native touch events with preventDefault, so the
    //     surrounding mobile-shell scroll can't swallow the gesture. This is
    //     the ONLY thing that reliably pans/zooms inside a scrollable WebView.
    //   • Mouse / pen      → pointer events (guarded to ignore pointerType touch).
    //   • Wheel            → desktop zoom.
    function bindPanZoom() {
        const vp = S.vp;
        let pinchDist = 0, pinchScale = 1;

        // ---- Touch ----
        const list = t => [...t].map(p => ({ x: p.clientX, y: p.clientY }));
        let last = [];
        vp.addEventListener('touchstart', e => {
            last = list(e.touches);
            S.moved = false;
            if (last.length === 2) { pinchDist = Math.hypot(last[0].x - last[1].x, last[0].y - last[1].y) || 1; pinchScale = S.scale; }
        }, { passive: false });
        vp.addEventListener('touchmove', e => {
            e.preventDefault();                       // block the shell from scrolling
            const now = list(e.touches);
            if (now.length >= 2 && last.length >= 2) {
                const d = Math.hypot(now[0].x - now[1].x, now[0].y - now[1].y);
                const r = vp.getBoundingClientRect();
                zoomTo(pinchScale * (d / pinchDist), (now[0].x + now[1].x) / 2 - r.left, (now[0].y + now[1].y) / 2 - r.top);
                S.moved = true;
            } else if (now.length === 1 && last.length >= 1) {
                const dx = now[0].x - last[0].x, dy = now[0].y - last[0].y;
                if (Math.abs(dx) + Math.abs(dy) > 2) S.moved = true;
                S.tx += dx; S.ty += dy; apply();
            }
            last = now;
        }, { passive: false });
        const tend = e => { last = list(e.touches); if (last.length < 2) pinchDist = 0; };
        vp.addEventListener('touchend', tend);
        vp.addEventListener('touchcancel', tend);

        // ---- Mouse / pen ----
        const pts = new Map();
        vp.addEventListener('pointerdown', e => {
            if (e.pointerType === 'touch') return;
            vp.setPointerCapture(e.pointerId);
            pts.set(e.pointerId, { x: e.clientX, y: e.clientY });
            S.moved = false;
        });
        vp.addEventListener('pointermove', e => {
            if (e.pointerType === 'touch') return;
            const prev = pts.get(e.pointerId);
            if (!prev) return;
            const cur = { x: e.clientX, y: e.clientY };
            pts.set(e.pointerId, cur);
            const dx = cur.x - prev.x, dy = cur.y - prev.y;
            if (Math.abs(dx) + Math.abs(dy) > 2) S.moved = true;
            S.tx += dx; S.ty += dy; apply();
        });
        const up = e => pts.delete(e.pointerId);
        vp.addEventListener('pointerup', up);
        vp.addEventListener('pointercancel', up);

        // ---- Wheel zoom ----
        vp.addEventListener('wheel', e => {
            e.preventDefault();
            const r = vp.getBoundingClientRect();
            zoomAt(e.deltaY < 0 ? 1.1 : 0.9, e.clientX - r.left, e.clientY - r.top);
        }, { passive: false });
    }

    // Zoom / home controls the page chrome can call.
    function zoomIn()  { zoomAt(1.15, S.vp.clientWidth / 2, S.vp.clientHeight / 2); }
    function zoomOut() { zoomAt(0.87, S.vp.clientWidth / 2, S.vp.clientHeight / 2); }
    function home()    { S.focus = S.root; load(S.root); }

    async function respond(target, action) {
        try {
            const res = await fetch(S.cfg.respondUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': S.cfg.csrf, 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ edge_type: target.type, edge_id: target.id, action })
            });
            const j = await res.json();
            if (window.showToast) window.showToast(j.success ? 'success' : 'error', j.message || '');
            if (j.success) reload();
        } catch (e) { if (window.showToast) window.showToast('error', 'Something went wrong.'); }
    }

    return { mount, reload, recenter, zoomIn, zoomOut, home, spousesOf, edgesOf };
})();

// Alpine data factory for the add-relative sheet/modal. A global (not Alpine.data)
// so it resolves immediately even after a shell swap (alpine:init won't re-fire).
window.ftAddRelativeData = window.ftAddRelativeData || function (cfg) {
    return {
        open: false, focusId: null, focusName: '', type: 'child',
        full_name: '', gender: '', birth_year: '', is_deceased: false, state: 'married', submitting: false,
        spouses: [], other_parent_id: null,
        openFor(d) {
            this.focusId = d.focusId; this.focusName = d.focusName;
            this.type = 'child'; this.full_name = ''; this.gender = '';
            this.birth_year = ''; this.is_deceased = false; this.state = 'married';
            this.spouses = d.spouses || [];
            this.other_parent_id = this.spouses.length === 1 ? this.spouses[0].id : null;
            this.open = true;
        },
        close() { this.open = false; },
        async submit() {
            if (!this.full_name.trim()) { window.showToast && window.showToast('error', 'Please enter a name.'); return; }
            if (this.type === 'child' && this.spouses.length > 1 && !this.other_parent_id) {
                window.showToast && window.showToast('error', 'Please select the other parent.');
                return;
            }
            this.submitting = true;
            try {
                const body = {
                    focus_person_id: this.focusId, type: this.type, full_name: this.full_name.trim(),
                    gender: this.gender || null, is_deceased: this.is_deceased,
                    birth_date: this.birth_year ? (this.birth_year + '-01-01') : null,
                };
                if (this.type === 'spouse') body.state = this.state;
                if (this.type === 'child' && this.other_parent_id) body.other_parent_person_id = this.other_parent_id;
                const res = await fetch(cfg.addUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': cfg.csrf, 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify(body)
                });
                const j = await res.json();
                window.showToast && window.showToast(j.success ? 'success' : 'error', j.message || '');
                if (j.success) { this.close(); window.FamilyTree && window.FamilyTree.reload(); }
            } catch (e) {
                window.showToast && window.showToast('error', 'Something went wrong.');
            } finally {
                this.submitting = false;
            }
        }
    };
};

// Alpine data factory for the "manage relationships" sheet/modal — lists a
// person's connecting edges and lets you unlink one (fix a mistake/duplicate).
window.ftManageData = window.ftManageData || function (cfg) {
    return {
        open: false, personId: null, personName: '', edges: [], removingId: null,
        openFor(d) {
            this.personId = d.personId; this.personName = d.personName;
            this.edges = d.edges || [];
            this.open = true;
        },
        close() { this.open = false; },
        async remove(edge) {
            const ok = await (window.confirmAction ? window.confirmAction({
                title: 'Remove relationship',
                message: 'Remove the "' + edge.label + ' ' + edge.name + '" relationship? This can\'t be undone.',
                type: 'danger',
                confirmText: 'Remove',
            }) : Promise.resolve(true));
            if (!ok) return;

            this.removingId = edge.edge_type + ':' + edge.edge_id;
            try {
                const res = await fetch(cfg.removeUrl, {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': cfg.csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    body: JSON.stringify({ edge_type: edge.edge_type, edge_id: edge.edge_id }),
                });
                const j = await res.json();
                window.showToast && window.showToast(j.success ? 'success' : 'error', j.message || '');
                if (j.success) {
                    this.edges = this.edges.filter(e => !(e.edge_type === edge.edge_type && e.edge_id === edge.edge_id));
                    window.FamilyTree && window.FamilyTree.reload();
                    // Pages that show this modal without a mounted tree (e.g. /family/members)
                    // listen for this to refresh their own list instead.
                    window.dispatchEvent(new CustomEvent('family-relative-removed'));
                    if (this.edges.length === 0) this.close();
                }
            } catch (e) {
                window.showToast && window.showToast('error', 'Something went wrong.');
            } finally {
                this.removingId = null;
            }
        }
    };
};
</script>
