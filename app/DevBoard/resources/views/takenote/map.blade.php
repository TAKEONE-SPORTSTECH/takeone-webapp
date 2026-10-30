{{--
    The note map — TakeNote's answer to Trilium's Note Map. Included INSIDE
    takenote/script's closure.

    Every note is a dot, coloured by what it holds (text, picture, voice,
    video, drawing); every folder is a larger hub in its own colour, joined
    to the folder it sits in. Lines show the TREE (where a note is filed) and
    the LINKS (a note that links to another, made with "Link to a note") —
    either or both, like Trilium's tree map and link map — and, when switched
    on, the named CONNECTIONS (takenote/relations): green lines carrying their
    name, made right here with "Connect" on a note's card (then a tap on the
    other note) or a Shift-drag from note to note; a tap on a name opens it. Hover lights a note
    and everything it touches; drag a dot to place it; click it to open it;
    a folder hub zooms to its notes. Pan, wheel / pinch to zoom, fit.

    A small force layout written here (no library): repulsion, springs along
    the lines, a pull to the middle, cooling to rest — positions are kept
    between redraws, so the map does not reshuffle when a note changes.
    Canvas, drawn at the screen's pixel density; every label is text drawn on
    the canvas, never HTML.
--}}
    /* ── the note map ────────────────────────────────────────────────── */
    var $map = document.getElementById('map');
    var MAP_KIND = { text: '#a78bfa', image: '#f5b54a', audio: '#ff7aa2', video: '#3fd3c1', sketch: '#6bb8f0' };
    var M = null;
    try { S.mapMode = localStorage.getItem('takenote.mapmode') || 'both'; } catch (e) { S.mapMode = 'both'; }
    // Trilium's way: the map centres on a note and shows its neighbourhood, so many steps out (0 = everything)
    try { S.mapCenter = localStorage.getItem('takenote.mapcenter') || null; S.mapDepth = +(localStorage.getItem('takenote.mapdepth') || 2); } catch (e) { S.mapCenter = null; S.mapDepth = 2; }
    function mapCentre(id) {
        S.mapCenter = id || null;
        try { if (id) localStorage.setItem('takenote.mapcenter', id); else localStorage.removeItem('takenote.mapcenter'); } catch (e) {}
    }

    function drawMap() {
        if (!M) mapShell();
        mapGraph();
        mapSize();
        if (!M.fitted) { for (var i = 0; i < 260; i++) mapTick(); mapFit(false); M.fitted = true; }
        M.alpha = Math.max(M.alpha, 0.3);
        mapRun();
    }

    function mapShell() {
        M = { nodes: [], edges: [], byId: {}, pos: {}, view: { x: 0, y: 0, k: 1 }, alpha: 1, raf: 0, hover: null, focus: null, ptrs: {}, dpr: Math.max(1, Math.min(3, window.devicePixelRatio || 1)) };
        var wrap = h('div', 'nm');
        var cv = h('canvas', 'nm-canvas'); cv.setAttribute('role', 'img'); cv.setAttribute('aria-label', T.nm_label);
        var bar = h('div', 'nm-bar');
        var modes = h('div', 'nm-modes'); modes.setAttribute('role', 'tablist');
        [['both', T.nm_both], ['tree', T.nm_tree], ['links', T.nm_links]].forEach(function (d) {
            var b = h('button', null, d[1]); b.type = 'button'; b.dataset.mode = d[0]; b.setAttribute('role', 'tab');
            b.addEventListener('click', function () { S.mapMode = d[0]; try { localStorage.setItem('takenote.mapmode', d[0]); } catch (e) {} drawMap(); });
            modes.appendChild(b);
        });
        var zoom = h('div', 'nm-zoom');
        [['bi-plus-lg', T.nm_zoom_in, function () { mapZoom(1.25); }], ['bi-dash-lg', T.nm_zoom_out, function () { mapZoom(0.8); }], ['bi-arrows-fullscreen', T.nm_fit, function () { M.focus = null; mapFit(true); }]].forEach(function (d) {
            var b = h('button'); b.type = 'button'; b.setAttribute('aria-label', d[1]); b.title = d[1]; b.appendChild(icon(d[0]));
            b.addEventListener('click', d[2]); zoom.appendChild(b);
        });
        bar.append(modes, zoom);
        // what the map is centred on, and how far out it looks
        var focus = h('div', 'nm-focus');
        // the note you clicked: open it, or keep exploring from it
        var card = h('div', 'nm-card'); card.hidden = true;
        var legend = h('div', 'nm-legend');
        [['text', T.note], ['image', T.tn_photo], ['audio', T.tn_voice], ['video', T.tn_video], ['sketch', T.tn_drawing]].forEach(function (d) {
            var s = h('span'); var dot = h('i'); dot.style.background = MAP_KIND[d[0]]; s.append(dot, document.createTextNode(d[1])); legend.appendChild(s);
        });
        var lk = h('span', 'is-link'); lk.append(h('i'), document.createTextNode(T.nm_link)); legend.appendChild(lk);
        if (relOn()) { var rl = h('span', 'is-rel'); rl.append(h('i'), document.createTextNode(T.rel_legend)); legend.appendChild(rl); }
        var tip = h('div', 'nm-tip'); tip.hidden = true;
        var empty = h('div', 'nm-empty'); empty.hidden = true;
        // connecting: which note it starts from, and the way out
        var cbar = h('div', 'nm-connect'); cbar.hidden = true; cbar.setAttribute('role', 'status');
        wrap.append(cv, bar, focus, legend, tip, empty, card, cbar);
        M.focusBar = focus; M.card = card; M.cbar = cbar;
        $map.replaceChildren(wrap);
        M.wrap = wrap; M.cv = cv; M.ctx = cv.getContext('2d'); M.tip = tip; M.empty = empty; M.modes = modes;
        mapInput();
        if (window.ResizeObserver) new ResizeObserver(function () { if (!$map.hidden) { mapSize(); if (!M.handled) mapFit(false); mapPaint(); } }).observe(wrap);
        ['pointerdown', 'wheel'].forEach(function (ev) { wrap.addEventListener(ev, function () { M.handled = true; }, { passive: true }); });
        window.addEventListener('resize', function () { if (!$map.hidden) { mapSize(); mapPaint(); } });
    }

    /* the graph: a root, the folders, the notes; tree lines and link lines */
    function mapGraph() {
        M.modes.querySelectorAll('button').forEach(function (b) { var on = b.dataset.mode === S.mapMode; b.classList.toggle('is-on', on); b.setAttribute('aria-selected', String(on)); });
        var tree = S.mapMode !== 'links', links = S.mapMode !== 'tree';
        var nodes = [], edges = [], byId = {};
        function add(n) {
            var p = M.pos[n.id];
            if (!p) { n.fresh = true; p = { x: (Math.random() - 0.5) * 60, y: (Math.random() - 0.5) * 60, vx: 0, vy: 0 }; }
            n.x = p.x; n.y = p.y; n.vx = p.vx || 0; n.vy = p.vy || 0; n.fixed = p.fixed || false; n.born = p.born || 0;
            M.pos[n.id] = n; byId[n.id] = n; nodes.push(n);
        }
        add({ id: 'root', type: 'root', label: 'TakeNote', r: 13, color: '#ffffff' });
        (S.folders || []).forEach(function (f) {
            var n = S.notes.filter(function (x) { return x.folder === f.uuid; }).length;
            add({ id: f.uuid, type: 'folder', label: f.name, r: 11 + Math.min(10, Math.sqrt(n) * 2.6), color: f.color, folder: f });
        });
        var linkCount = {};
        S.notes.forEach(function (n) { (n.links || []).forEach(function (u) { linkCount[u] = (linkCount[u] || 0) + 1; linkCount[n.uuid] = (linkCount[n.uuid] || 0) + 1; }); });
        (S.relations || []).forEach(function (r) { linkCount[r.from] = (linkCount[r.from] || 0) + 1; linkCount[r.to] = (linkCount[r.to] || 0) + 1; });
        S.notes.forEach(function (n) {
            var lead = MAP_KIND[n.lead] ? n.lead : 'text';
            add({ id: n.uuid, type: 'note', label: n.title || T.note_untitled, r: 6 + Math.min(6, (linkCount[n.uuid] || 0) * 1.6), color: MAP_KIND[lead], note: n, hit: matches(n) });
        });
        if (tree) {
            (S.folders || []).forEach(function (f) { edges.push({ a: byId[f.parent] ? f.parent : 'root', b: f.uuid, kind: 'tree', len: 90 }); });
            S.notes.forEach(function (n) { edges.push({ a: n.folder && byId[n.folder] ? n.folder : 'root', b: n.uuid, kind: 'tree', len: n.folder ? 55 : 120 }); });
        }
        if (links) {
            S.notes.forEach(function (n) { (n.links || []).forEach(function (u) { if (byId[u] && u !== n.uuid) edges.push({ a: n.uuid, b: u, kind: 'link', len: 110 }); }); });
            // named connections: their own line, bending the other way from a link between the same two notes
            (S.relations || []).forEach(function (r) { if (byId[r.from] && byId[r.to]) edges.push({ a: r.from, b: r.to, kind: 'rel', len: 150, rel: r }); });
        }
        edges.forEach(function (e) { e.A = byId[e.a]; e.B = byId[e.b]; });
        edges = edges.filter(function (e) { return e.A && e.B; });
        var nb = {}; edges.forEach(function (e) { (nb[e.a] = nb[e.a] || []).push(e.b); (nb[e.b] = nb[e.b] || []).push(e.a); });
        // centred: only what lies within so many steps of the centre
        if (S.mapCenter && !byId[S.mapCenter]) mapCentre(null);
        if (S.mapCenter) {
            var keep = {}, frontier = [S.mapCenter], step = 0, max = S.mapDepth || 99;
            keep[S.mapCenter] = 1;
            while (frontier.length && step < max) {
                var next = [];
                frontier.forEach(function (id) { (nb[id] || []).forEach(function (o) { if (!keep[o]) { keep[o] = 1; next.push(o); } }); });
                frontier = next; step++;
            }
            nodes = nodes.filter(function (n) { return keep[n.id]; });
            edges = edges.filter(function (e) { return keep[e.a] && keep[e.b]; });
            nb = {}; edges.forEach(function (e) { (nb[e.a] = nb[e.a] || []).push(e.b); (nb[e.b] = nb[e.b] || []).push(e.a); });
            byId[S.mapCenter].center = true;
            byId[S.mapCenter].r += 5;
        }
        // a dot that is new grows out of a neighbour already on the map, instead of appearing anywhere
        var now = performance.now();
        nodes.forEach(function (n) {
            if (!n.fresh) return;
            var from = (nb[n.id] || []).map(function (id) { return byId[id]; }).find(function (o) { return o && !o.fresh; });
            if (from) { n.x = from.x + (Math.random() - 0.5) * 24; n.y = from.y + (Math.random() - 0.5) * 24; }
            n.born = M.fitted ? now : 0; n.fresh = false;
        });
        drawFocusBar(byId);
        M.nodes = nodes; M.edges = edges; M.byId = byId; M.nb = nb;
        M.empty.hidden = S.notes.length > 0;
        if (!S.notes.length) M.empty.textContent = T.nm_empty;
    }

    /* one step of the layout */
    function mapTick() {
        var ns = M.nodes, i, j, a, b, dx, dy, d2, d, f;
        for (i = 0; i < ns.length; i++) {
            a = ns[i];
            for (j = i + 1; j < ns.length; j++) {
                b = ns[j]; dx = a.x - b.x; dy = a.y - b.y; d2 = dx * dx + dy * dy;
                if (d2 > 250000) continue; // far apart: no push
                if (d2 < 1) { dx = Math.random() - 0.5; dy = Math.random() - 0.5; d2 = 1; }
                f = (a.type === 'note' ? 1 : 2.4) * (b.type === 'note' ? 1 : 2.4) * 900 / d2;
                a.vx += dx * f * 0.05; a.vy += dy * f * 0.05; b.vx -= dx * f * 0.05; b.vy -= dy * f * 0.05;
            }
        }
        M.edges.forEach(function (e) {
            dx = e.B.x - e.A.x; dy = e.B.y - e.A.y; d = Math.sqrt(dx * dx + dy * dy) || 1;
            f = (d - e.len) * (e.kind === 'link' ? 0.012 : 0.03) / d;
            e.A.vx += dx * f; e.A.vy += dy * f; e.B.vx -= dx * f; e.B.vy -= dy * f;
        });
        ns.forEach(function (n) {
            if (n.type === 'root') { n.vx -= n.x * 0.05; n.vy -= n.y * 0.05; }
            n.vx -= n.x * 0.0015; n.vy -= n.y * 0.0015;
            if (n.fixed || n === M.dragging) { n.vx = 0; n.vy = 0; return; }
            n.vx *= 0.82; n.vy *= 0.82;
            n.x += Math.max(-30, Math.min(30, n.vx)); n.y += Math.max(-30, Math.min(30, n.vy));
        });
    }
    function mapRun() {
        if (M.raf) return;
        var step = function () {
            M.raf = 0;
            if ($map.hidden) return;
            var now = performance.now();
            if (M.alpha > 0.008 || M.dragging) { mapTick(); M.alpha *= 0.985; }
            // the camera glides to keep the centre in the middle while the map finds its shape
            var f = M.follow && now < M.follow.until && M.byId[M.follow.id];
            if (f) {
                var tx = M.w / 2 - f.x * M.view.k, ty = M.h / 2 - f.y * M.view.k;
                M.view.x += (tx - M.view.x) * 0.1; M.view.y += (ty - M.view.y) * 0.1;
            }
            mapPaint();
            var growing = M.nodes.some(function (n) { return n.born && now - n.born < 500; });
            if (M.alpha > 0.008 || M.dragging || f || growing || now < (M.pulseUntil || 0) || now < (M.flashUntil || 0)) M.raf = requestAnimationFrame(step);
        };
        M.raf = requestAnimationFrame(step);
    }

    function mapSize() {
        if (!DESK) { var top = $map.getBoundingClientRect().top + window.scrollY; $map.style.setProperty('--map-h', Math.max(360, window.innerHeight - top - 100) + 'px'); }
        var w = M.wrap.clientWidth, hh = M.wrap.clientHeight;
        if (!w || !hh) return;
        M.w = w; M.h = hh;
        M.cv.width = Math.round(w * M.dpr); M.cv.height = Math.round(hh * M.dpr);
        M.cv.style.width = w + 'px'; M.cv.style.height = hh + 'px';
    }
    function mapFit(animate) {
        var ns = M.focus ? M.nodes.filter(function (n) { return n.id === M.focus || subtree(M.focus).indexOf(n.id) !== -1 || (n.note && subtree(M.focus).indexOf(n.note.folder) !== -1); }) : M.nodes;
        if (!ns.length || !M.w) return;
        var x0 = Infinity, y0 = Infinity, x1 = -Infinity, y1 = -Infinity;
        ns.forEach(function (n) { x0 = Math.min(x0, n.x - n.r); y0 = Math.min(y0, n.y - n.r); x1 = Math.max(x1, n.x + n.r); y1 = Math.max(y1, n.y + n.r); });
        var pad = 70, k = Math.min(2.2, Math.max(0.2, Math.min((M.w - pad * 2) / Math.max(1, x1 - x0), (M.h - pad * 2) / Math.max(1, y1 - y0))));
        var to = { k: k, x: M.w / 2 - (x0 + x1) / 2 * k, y: M.h / 2 - (y0 + y1) / 2 * k };
        if (!animate || REDUCE) { M.view = to; mapPaint(); return; }
        var from = Object.assign({}, M.view), t0 = performance.now();
        (function go(now) {
            var p = Math.min(1, (now - t0) / 420), e = 1 - Math.pow(1 - p, 3);
            M.view = { k: from.k + (to.k - from.k) * e, x: from.x + (to.x - from.x) * e, y: from.y + (to.y - from.y) * e };
            mapPaint();
            if (p < 1) requestAnimationFrame(go);
        })(t0);
    }
    function mapZoom(f, cx, cy) {
        cx = cx == null ? M.w / 2 : cx; cy = cy == null ? M.h / 2 : cy;
        var k = Math.max(0.15, Math.min(4, M.view.k * f)), r = k / M.view.k;
        M.view.x = cx - (cx - M.view.x) * r; M.view.y = cy - (cy - M.view.y) * r; M.view.k = k;
        mapPaint();
    }

    function mapPaint() {
        if (!M || !M.w) return;
        var c = M.ctx, v = M.view, dpr = M.dpr;
        c.setTransform(dpr, 0, 0, dpr, 0, 0);
        c.clearRect(0, 0, M.w, M.h);
        var lit = null;
        if (M.hover) { lit = {}; lit[M.hover.id] = 1; (M.nb[M.hover.id] || []).forEach(function (id) { lit[id] = 1; }); }
        c.save(); c.translate(v.x, v.y); c.scale(v.k, v.k);
        // lines: the tree quietly, links bright and curved
        M.edges.forEach(function (e) {
            var on = !lit || (lit[e.a] && lit[e.b] && (e.a === M.hover.id || e.b === M.hover.id));
            c.globalAlpha = on ? 1 : 0.12;
            if (e.kind === 'tree') {
                c.strokeStyle = 'rgba(255,255,255,' + (lit && on ? 0.5 : 0.16) + ')'; c.lineWidth = 1.2 / v.k + 0.4;
                c.beginPath(); c.moveTo(e.A.x, e.A.y); c.lineTo(e.B.x, e.B.y); c.stroke();
            } else if (e.kind === 'rel') {
                var rmx = (e.A.x + e.B.x) / 2, rmy = (e.A.y + e.B.y) / 2, rdx = e.B.x - e.A.x, rdy = e.B.y - e.A.y;
                var rcx = rmx + rdy * 0.14, rcy = rmy - rdx * 0.14;
                var glow = M.flashRel === e.rel.uuid && performance.now() < (M.flashUntil || 0);
                c.strokeStyle = '#34d399'; c.lineWidth = glow ? 3.6 : 2.4;
                c.shadowColor = 'rgba(52,211,153,.85)'; c.shadowBlur = glow ? 24 : (lit && on ? 14 : 6);
                c.beginPath(); c.moveTo(e.A.x, e.A.y); c.quadraticCurveTo(rcx, rcy, e.B.x, e.B.y); c.stroke();
                c.shadowBlur = 0;
                var rang = Math.atan2(e.B.y - rcy, e.B.x - rcx), rex = e.B.x - Math.cos(rang) * (e.B.r + 3), rey = e.B.y - Math.sin(rang) * (e.B.r + 3);
                c.fillStyle = '#6ee7b7'; c.beginPath(); c.moveTo(rex, rey);
                c.lineTo(rex - Math.cos(rang - 0.45) * 8, rey - Math.sin(rang - 0.45) * 8); c.lineTo(rex - Math.cos(rang + 0.45) * 8, rey - Math.sin(rang + 0.45) * 8); c.closePath(); c.fill();
                e.mx = (e.A.x + 2 * rcx + e.B.x) / 4; e.my = (e.A.y + 2 * rcy + e.B.y) / 4; e.on = on; // the curve's middle: where its name sits
            } else {
                var mx = (e.A.x + e.B.x) / 2, my = (e.A.y + e.B.y) / 2, dx = e.B.x - e.A.x, dy = e.B.y - e.A.y;
                var cx = mx - dy * 0.18, cy = my + dx * 0.18;
                c.strokeStyle = '#a78bfa'; c.lineWidth = 2.2; c.shadowColor = 'rgba(167,139,250,.8)'; c.shadowBlur = lit && on ? 14 : 6;
                c.beginPath(); c.moveTo(e.A.x, e.A.y); c.quadraticCurveTo(cx, cy, e.B.x, e.B.y); c.stroke();
                c.shadowBlur = 0;
                // an arrowhead where the link lands
                var ang = Math.atan2(e.B.y - cy, e.B.x - cx), ex = e.B.x - Math.cos(ang) * (e.B.r + 3), ey = e.B.y - Math.sin(ang) * (e.B.r + 3);
                c.fillStyle = '#c4b8ff'; c.beginPath(); c.moveTo(ex, ey);
                c.lineTo(ex - Math.cos(ang - 0.45) * 8, ey - Math.sin(ang - 0.45) * 8); c.lineTo(ex - Math.cos(ang + 0.45) * 8, ey - Math.sin(ang + 0.45) * 8); c.closePath(); c.fill();
            }
        });
        c.globalAlpha = 1;
        // dots and hubs
        M.nodes.forEach(function (n) {
            var dim = (lit && !lit[n.id]) || (n.type === 'note' && S.q && !n.hit);
            var grow = n.born ? Math.min(1, (performance.now() - n.born) / 450) : 1;
            c.globalAlpha = (dim ? 0.18 : 1) * grow;
            if (n.center) { // the centre: a soft halo that breathes for a moment
                var tt = performance.now(), pulse = tt < (M.pulseUntil || 0) && !REDUCE ? Math.sin(tt / 240) * 3 : 0;
                c.fillStyle = 'rgba(255,255,255,.08)'; c.beginPath(); c.arc(n.x, n.y, n.r + 12 + pulse, 0, Math.PI * 2); c.fill();
                c.strokeStyle = 'rgba(255,255,255,.75)'; c.lineWidth = 2; c.beginPath(); c.arc(n.x, n.y, n.r + 5 + pulse * 0.5, 0, Math.PI * 2); c.stroke();
            }
            var r0 = n.r; n.r = r0 * (0.4 + 0.6 * grow);
            if (n.type === 'root') {
                c.fillStyle = '#160f24'; c.strokeStyle = '#fff'; c.lineWidth = 2.5;
                c.beginPath(); c.arc(n.x, n.y, n.r, 0, Math.PI * 2); c.fill(); c.stroke();
                c.fillStyle = '#7c6bf5'; c.beginPath(); c.arc(n.x, n.y, n.r * 0.45, 0, Math.PI * 2); c.fill();
            } else if (n.type === 'folder') {
                c.shadowColor = n.color; c.shadowBlur = 18;
                c.fillStyle = n.color; c.beginPath(); c.arc(n.x, n.y, n.r, 0, Math.PI * 2); c.fill();
                c.shadowBlur = 0;
                c.strokeStyle = 'rgba(255,255,255,.85)'; c.lineWidth = 2; c.stroke();
                // a little folder drawn on the hub
                var s = n.r * 0.5; c.fillStyle = 'rgba(255,255,255,.92)';
                c.beginPath(); c.moveTo(n.x - s, n.y - s * 0.55); c.lineTo(n.x - s * 0.2, n.y - s * 0.55); c.lineTo(n.x, n.y - s * 0.3); c.lineTo(n.x + s, n.y - s * 0.3); c.lineTo(n.x + s, n.y + s * 0.65); c.lineTo(n.x - s, n.y + s * 0.65); c.closePath(); c.fill();
            } else {
                c.fillStyle = n.color; c.shadowColor = n.color; c.shadowBlur = M.hover === n ? 20 : 8;
                c.beginPath(); c.arc(n.x, n.y, n.r, 0, Math.PI * 2); c.fill();
                c.shadowBlur = 0;
                if (S.q && n.hit) { c.strokeStyle = '#fff'; c.lineWidth = 2; c.stroke(); }
            }
            n.r = r0;
        });
        // connections' names, on their line: a tap opens one (a dot stands in for one with no name, or far out)
        c.textAlign = 'center'; c.textBaseline = 'middle';
        M.edges.forEach(function (e) {
            if (e.kind !== 'rel' || e.mx == null) return;
            c.globalAlpha = e.on ? 1 : 0.15;
            var named = e.rel.label && (v.k > 0.55 || (lit && e.on) || M.flashRel === e.rel.uuid);
            if (!named) {
                var dr = 4.5 / Math.max(0.6, v.k);
                c.fillStyle = '#10b981'; c.strokeStyle = '#d1fae5'; c.lineWidth = 1.5 / v.k;
                c.beginPath(); c.arc(e.mx, e.my, dr, 0, Math.PI * 2); c.fill(); c.stroke();
                e.box = { x: e.mx - dr - 6 / v.k, y: e.my - dr - 6 / v.k, w: (dr + 6 / v.k) * 2, h: (dr + 6 / v.k) * 2 };
                return;
            }
            var lfs = 10.5 / Math.max(0.75, v.k), ltxt = e.rel.label.length > 26 ? e.rel.label.slice(0, 25) + '…' : e.rel.label;
            c.font = '700 ' + lfs + 'px Inter, system-ui, sans-serif';
            var lw = c.measureText(ltxt).width + 14 / v.k, lh = lfs + 8 / v.k;
            e.box = { x: e.mx - lw / 2, y: e.my - lh / 2, w: lw, h: lh };
            c.fillStyle = '#0f3b2e'; mapPill(c, e.box.x, e.box.y, lw, lh, lh / 2);
            c.strokeStyle = 'rgba(110,231,183,.75)'; c.lineWidth = 1 / v.k; c.stroke();
            c.fillStyle = '#d1fae5'; c.fillText(ltxt, e.mx, e.my + 0.5 / v.k);
        });
        c.globalAlpha = 1;
        // connecting: a line from the first note to the pointer, snapping to the note under it
        if (M.connect) {
            var cf = M.byId[M.connect.from.id] || M.connect.from, cto = M.connect.over || M.connect.pt;
            c.strokeStyle = 'rgba(255,255,255,.9)'; c.lineWidth = 2.4 / v.k;
            c.beginPath(); c.arc(cf.x, cf.y, cf.r + 6, 0, Math.PI * 2); c.stroke();
            if (cto) {
                c.save(); c.setLineDash([7 / v.k, 6 / v.k]); c.strokeStyle = '#6ee7b7'; c.lineWidth = 2.4 / v.k;
                c.beginPath(); c.moveTo(cf.x, cf.y); c.lineTo(cto.x, cto.y); c.stroke(); c.restore();
            }
            if (M.connect.over) {
                var ov = M.connect.over; c.strokeStyle = '#34d399'; c.lineWidth = 3 / v.k; c.shadowColor = '#34d399'; c.shadowBlur = 16;
                c.beginPath(); c.arc(ov.x, ov.y, ov.r + 6, 0, Math.PI * 2); c.stroke(); c.shadowBlur = 0;
            }
        }
        // names: hubs always, notes when close enough or lit
        c.textAlign = 'center'; c.textBaseline = 'top';
        M.nodes.forEach(function (n) {
            var show = n.type !== 'note' || n.center || (M.connect && n.type === 'note') || v.k > 0.85 || (lit && lit[n.id]) || (S.q && n.hit) || (S.mapCenter && M.nodes.length < 30);
            if (!show || (lit && !lit[n.id] && n.type === 'note')) return;
            var fs = (n.type === 'note' ? 11.5 : 13) / Math.max(0.75, v.k), label = n.label.length > 34 ? n.label.slice(0, 33) + '…' : n.label;
            c.font = (n.type === 'note' ? '600 ' : '800 ') + fs + 'px Inter, system-ui, sans-serif';
            var w = c.measureText(label).width, y = n.y + n.r + 5 / v.k;
            c.globalAlpha = lit && !lit[n.id] ? 0.3 : 1;
            c.fillStyle = 'rgba(14,9,26,.72)';
            mapPill(c, n.x - w / 2 - 5 / v.k, y - 2 / v.k, w + 10 / v.k, fs + 5 / v.k, 6 / v.k);
            c.fillStyle = n.type === 'note' ? 'rgba(255,255,255,.9)' : '#fff';
            c.fillText(label, n.x, y);
        });
        c.restore();
        c.globalAlpha = 1;
    }
    function mapPill(c, x, y, w, hh, r) { c.beginPath(); c.moveTo(x + r, y); c.arcTo(x + w, y, x + w, y + hh, r); c.arcTo(x + w, y + hh, x, y + hh, r); c.arcTo(x, y + hh, x, y, r); c.arcTo(x, y, x + w, y, r); c.closePath(); c.fill(); }

    function mapAt(px, py) {
        var v = M.view, x = (px - v.x) / v.k, y = (py - v.y) / v.k, best = null, bd = Infinity;
        var slop = (M.connect ? 16 : 6) / v.k; // picking the other note: a fingertip's reach
        M.nodes.forEach(function (n) { if (M.connect && n.type !== 'note') return; var d = Math.hypot(n.x - x, n.y - y); if (d < n.r + slop && d < bd) { bd = d; best = n; } });
        return best;
    }
    function relAt(px, py) {
        var v = M.view, x = (px - v.x) / v.k, y = (py - v.y) / v.k;
        for (var i = M.edges.length - 1; i >= 0; i--) {
            var e = M.edges[i], b = e.box;
            if (e.kind === 'rel' && b && x >= b.x && x <= b.x + b.w && y >= b.y && y <= b.y + b.h) return e;
        }
        return null;
    }
    function mapTip(n, px, py) {
        if (!n || n.type === 'root') { M.tip.hidden = true; return; }
        var lines = n.type === 'folder'
            ? [n.label, t('nm_folder_notes', { n: S.notes.filter(function (x) { return x.folder === n.id; }).length })]
            : [n.label, [n.note.excerpt || '', ((n.note.links || []).length ? t('nm_links_out', { n: n.note.links.length }) : '')].filter(Boolean).join(' · ')];
        M.tip.replaceChildren(h('b', null, lines[0]), h('span', null, lines[1] || ''));
        M.tip.hidden = false;
        M.tip.style.left = Math.min(M.w - 260, px + 14) + 'px'; M.tip.style.top = Math.min(M.h - 80, py + 14) + 'px';
    }

    /* mouse, pen and fingers: drag a dot, pan the paper, pinch or wheel to zoom, tap to open */
    function mapInput() {
        var cv = M.cv, start = null;
        function pt(e) { var r = cv.getBoundingClientRect(); return { x: e.clientX - r.left, y: e.clientY - r.top }; }
        function world(p) { return { x: (p.x - M.view.x) / M.view.k, y: (p.y - M.view.y) / M.view.k }; }
        cv.addEventListener('pointerdown', function (e) {
            cv.setPointerCapture(e.pointerId);
            var p = pt(e); M.ptrs[e.pointerId] = p;
            var ids = Object.keys(M.ptrs);
            if (ids.length === 2) { var a = M.ptrs[ids[0]], b = M.ptrs[ids[1]]; M.pinch = { d: Math.hypot(a.x - b.x, a.y - b.y), k: M.view.k }; M.dragging = null; start = null; return; }
            var n = mapAt(p.x, p.y);
            // Shift-drag from one note to another connects them (a desk's shortcut for "Connect")
            if (!M.connect && e.shiftKey && n && n.type === 'note' && relOn()) connectStart(n, true);
            if (M.connect) { M.connect.pt = world(p); start = { x: p.x, y: p.y, vx: M.view.x, vy: M.view.y, node: n, moved: false }; mapPaint(); return; }
            start = { x: p.x, y: p.y, vx: M.view.x, vy: M.view.y, node: n, moved: false };
            if (n && n.type !== 'root') { M.dragging = n; M.alpha = Math.max(M.alpha, 0.25); mapRun(); }
        });
        cv.addEventListener('pointermove', function (e) {
            var p = pt(e);
            if (M.ptrs[e.pointerId]) M.ptrs[e.pointerId] = p;
            var ids = Object.keys(M.ptrs);
            if (M.pinch && ids.length === 2) {
                var a = M.ptrs[ids[0]], b = M.ptrs[ids[1]], d = Math.hypot(a.x - b.x, a.y - b.y);
                mapZoom((M.pinch.k * d / M.pinch.d) / M.view.k, (a.x + b.x) / 2, (a.y + b.y) / 2);
                return;
            }
            if (M.connect) {
                M.connect.pt = world(p);
                var o = mapAt(p.x, p.y);
                M.connect.over = o && o.type === 'note' && o.id !== M.connect.from.id ? o : null;
                cv.style.cursor = M.connect.over ? 'pointer' : 'crosshair';
                if (start) {
                    if (Math.hypot(p.x - start.x, p.y - start.y) > 4) start.moved = true;
                    if (start.moved && !M.connect.drag) { M.view.x = start.vx + p.x - start.x; M.view.y = start.vy + p.y - start.y; } // waiting for a tap: the paper still pans
                }
                mapPaint();
                return;
            }
            if (start) {
                if (Math.hypot(p.x - start.x, p.y - start.y) > 4) start.moved = true;
                if (M.dragging) { M.dragging.x = (p.x - M.view.x) / M.view.k; M.dragging.y = (p.y - M.view.y) / M.view.k; M.dragging.fixed = true; M.alpha = Math.max(M.alpha, 0.2); mapRun(); }
                else if (start.moved) { M.view.x = start.vx + p.x - start.x; M.view.y = start.vy + p.y - start.y; mapPaint(); }
                return;
            }
            var n = mapAt(p.x, p.y);
            if (n !== M.hover) { M.hover = n && n.type !== 'root' ? n : null; mapPaint(); }
            cv.style.cursor = M.hover || relAt(p.x, p.y) ? 'pointer' : 'grab';
            mapTip(M.hover, p.x, p.y);
        });
        function up(e) {
            delete M.ptrs[e.pointerId];
            if (Object.keys(M.ptrs).length < 2) M.pinch = null;
            if (M.connect) {
                var s0 = start; start = null;
                if (!s0) return;
                var cp = pt(e), to = mapAt(cp.x, cp.y);
                if (to && to.type === 'note' && to.id !== M.connect.from.id) { var from = M.connect.from; connectEnd(); connectSheet(from.id, to.id); return; }
                if (M.connect.drag) connectEnd(); // let go on nothing: no connection
                else if (!s0.moved && to && to.id === M.connect.from.id) toast(T.rel_same);
                return; // otherwise still waiting for the tap on the other note (the banner's Cancel ends it)
            }
            if (!start) return;
            var s = start; start = null; M.dragging = null;
            if (s.moved) return;
            if (!s.node) { var re = relAt(s.x, s.y); if (re) relationSheet(re.rel); return; }
            var n = s.node;
            // a click explores: the map re-centres on that note or folder (a double-click opens it)
            focusOn(n);
        }
        cv.addEventListener('pointerup', up);
        cv.addEventListener('pointercancel', up);
        cv.addEventListener('pointerleave', function () { if (!start) { M.hover = null; M.tip.hidden = true; mapPaint(); } });
        cv.addEventListener('wheel', function (e) { e.preventDefault(); var p = pt(e); mapZoom(Math.exp(-e.deltaY * 0.0015), p.x, p.y); }, { passive: false });
        cv.addEventListener('dblclick', function (e) {
            if (M.connect) return;
            var p = pt(e), n = mapAt(p.x, p.y);
            if (n && n.type === 'note') openNote(n.id);
            else if (n && n.type === 'folder') { S.view = 'notes'; setFolder(n.id); }
        });
    }

    /* Connecting on the map: from this note, to the next note tapped (or the one a Shift-drag lands on) */
    function connectStart(n, drag) {
        M.connect = { from: n, drag: !!drag, pt: null, over: null, back: null };
        M.card.hidden = true; M.tip.hidden = true; M.hover = null;
        M.wrap.classList.add('is-connecting');
        // centred on a few steps round a note: open the whole notebook while picking, so every note can be reached
        if (S.mapCenter && !drag) {
            M.connect.back = S.mapCenter;
            S.mapCenter = null; mapGraph();
            for (var i = 0; i < 180; i++) mapTick(); // let the notes that were out of sight find their places first…
            M.connect.from = M.byId[n.id] || n;
            mapFit(true); // …then take in all of it
        }
        if (!drag) {
            var ic = h('span', 'nm-connect-ic'); ic.appendChild(icon('bi-bezier2'));
            var tx = h('span', 'nm-connect-t', t('rel_pick', { name: n.label })); tx.dir = 'auto';
            var x = h('button', null, T.rel_cancel); x.type = 'button';
            x.addEventListener('click', function () { connectEnd(); if (M.byId[n.id]) showCard(M.byId[n.id]); });
            M.cbar.replaceChildren(ic, tx, x);
            M.cbar.hidden = false;
            buzz(8);
        }
        document.addEventListener('keydown', connectEsc, true);
        mapPaint();
    }
    function connectEnd() {
        var back = M.connect && M.connect.back;
        M.connect = null;
        if (back) { S.mapCenter = back; mapGraph(); M.follow = { id: back, until: performance.now() + 1200 }; M.alpha = Math.max(M.alpha, 0.3); mapRun(); }
        M.cbar.hidden = true;
        M.wrap.classList.remove('is-connecting');
        M.cv.style.cursor = 'grab';
        document.removeEventListener('keydown', connectEsc, true);
        mapPaint();
    }
    function connectEsc(e) { if (e.key === 'Escape' && M && M.connect) { e.stopImmediatePropagation(); connectEnd(); } }

    /* centring: on a note or a folder, the camera following it while the neighbourhood grows round it */
    function focusOn(n) {
        if (!n || n.type === 'root') return;
        var now = performance.now();
        mapCentre(n.id);
        M.hover = null; M.tip.hidden = true;
        mapGraph();
        M.follow = { id: n.id, until: now + 1600 };
        M.pulseUntil = now + 2600;
        M.alpha = Math.max(M.alpha, 0.5);
        showCard(M.byId[n.id]);
        mapRun();
    }
    function drawFocusBar(byId) {
        var bar = M.focusBar, c = S.mapCenter && byId[S.mapCenter];
        if (!c) {
            bar.replaceChildren(h('span', 'nm-hint', T.nm_hint + (relOn() && DESK ? ' · ' + T.rel_hint_desk : '')));
            M.card.hidden = true;
            return;
        }
        var chip = h('span', 'nm-centre');
        var dot = h('i'); dot.style.background = c.color;
        var x = h('button'); x.type = 'button'; x.setAttribute('aria-label', T.nm_whole); x.title = T.nm_whole; x.appendChild(icon('bi-x-lg'));
        x.addEventListener('click', function () { mapCentre(null); M.card.hidden = true; mapGraph(); M.alpha = 0.4; mapRun(); setTimeout(function () { mapFit(true); }, 60); });
        chip.append(icon('bi-bullseye'), dot, h('b', null, c.label), x);
        var depth = h('div', 'nm-depth'); depth.setAttribute('role', 'tablist'); depth.setAttribute('aria-label', T.nm_depth);
        depth.appendChild(h('span', null, T.nm_depth));
        [[1, '1'], [2, '2'], [3, '3'], [0, T.nm_all]].forEach(function (d) {
            var b = h('button', (S.mapDepth || 0) === d[0] ? 'is-on' : null, d[1]); b.type = 'button'; b.setAttribute('role', 'tab');
            b.addEventListener('click', function () {
                S.mapDepth = d[0]; try { localStorage.setItem('takenote.mapdepth', String(d[0])); } catch (e) {}
                mapGraph(); M.follow = { id: S.mapCenter, until: performance.now() + 1200 }; M.alpha = Math.max(M.alpha, 0.5); mapRun();
                setTimeout(function () { mapFit(true); }, 700);
            });
            depth.appendChild(b);
        });
        bar.replaceChildren(chip, depth);
    }
    function showCard(n) {
        var card = M.card;
        if (!n) { card.hidden = true; return; }
        var head = h('div', 'nm-card-h');
        var dot = h('i'); dot.style.background = n.color;
        var x = h('button', 'nm-x'); x.type = 'button'; x.setAttribute('aria-label', T.close); x.appendChild(icon('bi-x-lg'));
        x.addEventListener('click', function () { card.hidden = true; });
        head.append(dot, h('b', null, n.label), x);
        var sub = n.type === 'folder'
            ? t('nm_folder_notes', { n: S.notes.filter(function (m) { return m.folder === n.id; }).length })
            : (n.note.excerpt || '');
        var links = n.type === 'note' ? (M.nb[n.id] || []).length : 0;
        var acts = h('div', 'nm-card-a');
        var open = h('button', 'is-main'); open.type = 'button';
        if (n.type === 'note') { open.append(icon('bi-journal-text'), document.createTextNode(T.nm_open)); open.addEventListener('click', function () { openNote(n.id); }); }
        else { open.append(icon('bi-folder2-open'), document.createTextNode(T.nm_open_folder)); open.addEventListener('click', function () { S.view = 'notes'; setFolder(n.id); }); }
        acts.appendChild(open);
        if (n.type === 'note' && relOn()) {
            var con = h('button', 'is-rel'); con.type = 'button';
            con.append(icon('bi-bezier2'), document.createTextNode(T.rel_connect));
            con.addEventListener('click', function () { connectStart(M.byId[n.id] || n, false); });
            acts.appendChild(con);
        }
        card.replaceChildren(head, h('p', null, sub), acts);
        if (n.type === 'note' && links) card.insertBefore(h('span', 'nm-card-l', t('nm_connections', { n: links })), acts);
        // its named connections, each a door to rename / turn round / remove
        var mine = n.type === 'note' && relOn() ? relationsOf(n.id) : [];
        if (mine.length) {
            var list = h('div', 'nm-rels');
            mine.slice(0, 4).forEach(function (c) {
                var b = h('button', 'nm-rel'); b.type = 'button';
                b.appendChild(icon(c.out ? 'bi-arrow-right' : 'bi-arrow-left'));
                if (c.rel.label) { var em = h('em', null, c.rel.label); em.dir = 'auto'; b.appendChild(em); }
                var ot = h('span', null, relTitle(c.other)); ot.dir = 'auto'; b.appendChild(ot);
                b.addEventListener('click', function () { relationSheet(c.rel); });
                list.appendChild(b);
            });
            if (mine.length > 4) list.appendChild(h('small', null, '+' + (mine.length - 4)));
            card.insertBefore(list, acts);
        }
        card.hidden = false;
    }
    /* "Show on map" from a note: the map, centred on it */
    function showOnMap(uuid) {
        mapCentre(uuid);
        S.view = 'map';
        try { localStorage.setItem('takenote.view', 'map'); } catch (e) {}
        draw();
        if (M && M.byId[uuid]) { M.follow = { id: uuid, until: performance.now() + 1600 }; M.pulseUntil = performance.now() + 2600; showCard(M.byId[uuid]); mapRun(); }
    }

