{{--
    competition-brackets — runtime. Emitted ONCE per page by the component.

    A port of the Combat Sports Bracket design board (the `Component` class in
    app/Events/resources/views/personal/partials/bracket-board.html) from its
    React/dc-runtime shell onto Alpine, so it can be dropped into any view as a
    component. The draw RULES are the design file's, line for line: bye
    filling, pigtail (play-in) entry, first-round re-seeding, the holding
    basket, match and mat numbering, the pulse on the bout that is up next,
    one bronze bout or two shared bronzes, one-sided or split layout, the
    podium. Only the shell changed.

    ⚠️ THIS FILE DECIDES NOTHING ABOUT PERMISSION. It draws what it is handed
    and reports every change as a DOM event (`bracket:*`, see the component's
    header). A host that persists those changes posts them to an endpoint that
    authorises the actor itself.

    Written as a global factory (`window.competitionBrackets`) rather than
    `Alpine.data()` so it works wherever the markup lands: first paint, a
    mobile-shell swap, or a teleported template (where an inline script would
    be inert). Guarded, so a second include is free.
--}}
<script>
(function () {
    'use strict';
    if (window.competitionBrackets) return;

    var GOLD = '#d8b25f', GREY = '#42527d';
    var HL = '#2ecc71;box-shadow:0 0 0 2px #2ecc71,0 0 12px #2ecc71,0 0 26px rgba(46,204,113,.7);z-index:3';
    var PULSE = 'cb-pulse 1.8s ease-in-out infinite';
    var DASH = '2px dashed rgba(216,178,95,.9)';
    /* The board's height per draw size — the design file's own tables. */
    var HEIGHTS = {
        linear: {2: '460px', 4: '460px', 8: '760px', 16: '1420px', 32: '2700px', 64: '5200px'},
        split:  {2: '460px', 4: '680px', 8: '760px', 16: '980px', 32: '1500px', 64: '2600px'}
    };

    function sameId(a, b) { return a != null && b != null && a !== '' && String(a) === String(b); }
    function clamp(v, lo, hi) { return Math.min(hi, Math.max(lo, v)); }
    function fmt(s, vars) {
        return String(s == null ? '' : s).replace(/\{(\w+)\}/g, function (m, k) {
            return vars && vars[k] != null ? vars[k] : m;
        });
    }
    function plain(v) { return v == null ? v : JSON.parse(JSON.stringify(v)); }
    function hash(s) {
        var h = 0; s = String(s);
        for (var i = 0; i < s.length; i++) h = ((h << 5) - h + s.charCodeAt(i)) | 0;
        return Math.abs(h) || 1;
    }
    function initials(club) {
        return String(club || '').split(/\s+/).filter(Boolean).map(function (w) { return w[0]; }).join('').slice(0, 2).toUpperCase();
    }
    /* Only our own origin or an http(s) address may reach a style attribute,
       and the characters that could close `url("…")` are encoded. */
    function safeUrl(u) {
        u = String(u || '').trim();
        return /^(https?:\/\/|\/(?!\/))/i.test(u) ? u : '';
    }
    function cssUrl(u) {
        u = safeUrl(u);
        return u ? 'url("' + u.replace(/["\\\n\r\f()']/g, function (c) { return encodeURIComponent(c); }) + '")' : 'none';
    }
    function nextPow2(n) { var s = 2; while (s < n && s < 64) s *= 2; return s; }

    /* Registry — the host's handle on a mounted board. */
    window.CompetitionBrackets = window.CompetitionBrackets || {
        instances: {},
        get: function (id) { return this.instances[id] || null; },
        load: function (id, state) { var b = this.get(id); if (b) b.load(state || {}); }
    };

    window.competitionBrackets = function (cfg) {
        cfg = cfg || {};
        var t = cfg.labels || {};
        var flagSet = {};
        (cfg.flags || []).forEach(function (c) { flagSet[c] = true; });

        /* Not reactive on purpose: the camera moves sixty times a second and
           nothing on the board re-renders because of it. */
        var view = {x: 0, y: 0, z: 1}, fitZ = 1;
        var pan = null, panned = false, tpan = null, pinch = null, noZoom = false;
        var press = null, justPicked = 0, noticeTimer = null, ro = null, H = {};
        var measureCtx = null, widthCache = {}, root = null;

        function person(p) {
            if (!p || typeof p !== 'object') return null;
            var name = String(p.name || '').trim();
            if (!name) return null;
            var cc = String(p.cc || p.country || '').toLowerCase();
            if (!flagSet[cc]) cc = '';
            var club = String(p.club || '').trim();
            var img = safeUrl(p.img || p.photo) || cfg.placeholder || '';
            return {
                id: (p.id != null && p.id !== '') ? p.id : 'n' + hash(name),
                name: name.slice(0, 120),
                cc: cc,
                club: club.slice(0, 120),
                ab: String(p.ab || p.club_ab || initials(club)).slice(0, 3).toUpperCase(),
                img: img,
                imgCss: cssUrl(img),
                flagCss: cc ? cssUrl((cfg.flagBase || '') + '/' + cc + '.svg') : ''
            };
        }
        function bare(f) {
            return f ? {id: f.id, name: f.name, country: f.cc, club: f.club, club_ab: f.ab, photo: f.img} : null;
        }
        function measure(text, font) {
            var key = font + '|' + text;
            if (widthCache[key] != null) return widthCache[key];
            try {
                measureCtx = measureCtx || document.createElement('canvas').getContext('2d');
                measureCtx.font = font;
                widthCache[key] = measureCtx.measureText(text).width;
            } catch (e) { widthCache[key] = text.length * 7.4; }
            return widthCache[key];
        }
        function slotsFrom(list, size) {
            var out = [];
            for (var j = 0; j < size; j++) out.push(person((list || [])[j]));
            return out;
        }

        var size0 = cfg.size || nextPow2((cfg.competitors || []).length);

        return {
            cfg: cfg,
            t: t,
            vm: {cols: [], podium: [], playinCol: null, champ: null, bronze: null, innerHeight: ''},

            size: size0,
            third: cfg.third === 'shared' ? 'shared' : 'match',
            layout: cfg.layout === 'split' ? 'split' : 'linear',
            locked: cfg.editable ? cfg.locked !== false : true,
            edit: false,
            results: {},
            playins: [],
            fighters: [],
            roster: [],
            basket: [],

            hoverId: null, dragId: null, dragSlot: null, dragBasket: null, carry: null,
            notice: '', basketMin: false, menuOpen: false,
            addOpen: false, addMode: 'known', search: '',
            addName: '', addCountry: '', addClubSel: '', addClubNew: '',
            countryOpen: false, clubOpen: false, countryQ: '',

            /* ── Lifecycle ─────────────────────────────────────────────── */

            init: function () {
                var self = this, el = root = this.$el;
                this.applyState({
                    competitors: cfg.competitors, results: cfg.results, playins: cfg.playins,
                    roster: cfg.roster, basket: cfg.basket
                }, true);

                noZoom = !(window.CSS && CSS.supports && CSS.supports('zoom', '1'));
                window.CompetitionBrackets.instances[cfg.id] = this;

                H.wheel = function (e) {
                    e.preventDefault(); e.stopPropagation();
                    self.zoomAt(e.clientX, e.clientY, e.deltaY < 0 ? 1.12 : 0.89);
                };
                el.addEventListener('wheel', H.wheel, {passive: false});

                H.ts = function (e) {
                    if (e.touches.length === 1) {
                        var tt = e.touches[0];
                        if (tt.target.closest && tt.target.closest('input,button,textarea,[draggable="true"],[data-cb-ui]')) return;
                        tpan = {x: tt.clientX, y: tt.clientY, ox: view.x, oy: view.y};
                        pinch = null;
                    } else if (e.touches.length === 2) {
                        tpan = null;
                        var a = e.touches[0], b = e.touches[1];
                        pinch = {d: Math.hypot(a.clientX - b.clientX, a.clientY - b.clientY), z: view.z,
                            cx: (a.clientX + b.clientX) / 2, cy: (a.clientY + b.clientY) / 2, vx: view.x, vy: view.y};
                    }
                };
                H.tm = function (e) {
                    var sc = self.realScale();
                    if (e.touches.length === 1 && tpan) {
                        e.preventDefault();
                        var tt = e.touches[0];
                        view.x = tpan.ox + (tt.clientX - tpan.x) / sc;
                        view.y = tpan.oy + (tt.clientY - tpan.y) / sc;
                        self.applyView();
                    } else if (e.touches.length === 2 && pinch) {
                        e.preventDefault();
                        var a = e.touches[0], b = e.touches[1];
                        var d = Math.hypot(a.clientX - b.clientX, a.clientY - b.clientY);
                        var nz = clamp(pinch.z * d / pinch.d, self.zMin(), cfg.maxZoom || 3);
                        var r = el.getBoundingClientRect();
                        var cx = (pinch.cx - r.left) / sc, cy = (pinch.cy - r.top) / sc;
                        view.x = cx - (cx - pinch.vx) * nz / pinch.z;
                        view.y = cy - (cy - pinch.vy) * nz / pinch.z;
                        view.z = nz;
                        self.applyView();
                    }
                };
                H.te = function () { tpan = null; pinch = null; };
                el.addEventListener('touchstart', H.ts, {passive: true});
                el.addEventListener('touchmove', H.tm, {passive: false});
                el.addEventListener('touchend', H.te);
                H.ds = function () { pan = null; };
                window.addEventListener('dragstart', H.ds);

                H.load = function (e) {
                    if (e.detail && sameId(e.detail.id, cfg.id)) self.load(e.detail.state || {});
                };
                window.addEventListener('competition-brackets:load', H.load);

                if (window.ResizeObserver) {
                    var last = '';
                    ro = new ResizeObserver(function () {
                        var k = el.clientWidth + 'x' + el.clientHeight;
                        if (k !== last) { last = k; self.fit(); }
                    });
                    ro.observe(el);
                }

                this.$watch('notice', function (v) {
                    clearTimeout(noticeTimer);
                    if (v) noticeTimer = setTimeout(function () { self.notice = ''; }, 6500);
                });

                this.$nextTick(function () { requestAnimationFrame(function () { self.fit(true); }); });
                if (document.fonts && document.fonts.ready) {
                    document.fonts.ready.then(function () {
                        widthCache = {};
                        self.render();
                        self.$nextTick(function () { self.fit(); });
                    });
                }
            },

            destroy: function () {
                var el = root;
                clearTimeout(noticeTimer);
                if (ro) ro.disconnect();
                if (el && H.wheel) {
                    el.removeEventListener('wheel', H.wheel);
                    el.removeEventListener('touchstart', H.ts);
                    el.removeEventListener('touchmove', H.tm);
                    el.removeEventListener('touchend', H.te);
                }
                window.removeEventListener('dragstart', H.ds);
                window.removeEventListener('competition-brackets:load', H.load);
                if (window.CompetitionBrackets.instances[cfg.id] === this) delete window.CompetitionBrackets.instances[cfg.id];
            },

            /* ── Public API ────────────────────────────────────────────── */

            /** Replace any part of the draw: competitors, results, playins, roster, basket, size, third, layout. */
            load: function (state) {
                var before = this.size + this.layout;
                this.applyState(state || {}, false);
                if (before !== this.size + this.layout || state.competitors) {
                    var self = this;
                    this.$nextTick(function () { self.fit(); });
                }
            },

            /** The draw as it stands, in the shape `load()` accepts. */
            getState: function () {
                var self = this;
                return plain({
                    size: this.size, third: this.third, layout: this.layout,
                    competitors: this.fighters.slice(0, this.size).map(bare),
                    results: this.results,
                    playins: this.playins.map(function (p) {
                        var nc = self.fighters.find(function (f) { return f && sameId(f.id, p.newId); });
                        return {id: p.id, r: p.r, i: p.i, side: p.side, entrant: bare(nc)};
                    }),
                    roster: this.roster.map(bare),
                    basket: this.basket.map(bare)
                });
            },

            applyState: function (st, first) {
                var self = this;
                if (st.size) this.size = clamp(nextPow2(parseInt(st.size, 10) || 2), 2, 64);
                if (st.third) this.third = st.third === 'shared' ? 'shared' : 'match';
                if (st.layout) this.layout = st.layout === 'split' ? 'split' : 'linear';
                if (st.competitors) {
                    if (!st.size && !first) this.size = nextPow2(st.competitors.length);
                    this.fighters = slotsFrom(st.competitors, this.size);
                }
                if (st.results && typeof st.results === 'object') this.results = plain(st.results);
                if (st.playins) {
                    var extra = [];
                    this.playins = st.playins.filter(function (p) { return p && p.entrant; }).map(function (p) {
                        var nc = person(p.entrant);
                        if (nc) extra.push(nc);
                        return {id: p.id != null ? p.id : Date.now() + extra.length, newId: nc ? nc.id : null,
                            r: parseInt(p.r, 10) || 0, i: parseInt(p.i, 10) || 0, side: p.side ? 1 : 0};
                    });
                    this.fighters = this.fighters.slice(0, this.size).concat(extra);
                }
                if (st.roster) this.roster = st.roster.map(person).filter(Boolean);
                if (st.basket) this.basket = st.basket.map(person).filter(Boolean);
            },

            /* ── The camera ────────────────────────────────────────────── */

            realScale: function () {
                var bw = root, r = bw.getBoundingClientRect();
                return bw.offsetWidth ? (r.width / bw.offsetWidth) : 1;
            },
            zMin: function () { return Math.min(cfg.minZoom || 0.3, fitZ); },
            /* ⚠️ `zoom`, NOT `transform: scale()` — the board must stay SHARP.
               A transform blows up a bitmap; `zoom` re-lays and re-rasterises,
               so a face and a name are as crisp zoomed in as fitted. Panning
               stays a transform, divided by z because the two compose. */
            applyView: function () {
                var el = this.$refs.inner;
                if (!el) return;
                if (noZoom) {
                    el.style.transform = 'translate(' + view.x + 'px,' + view.y + 'px) scale(' + view.z + ')';
                    return;
                }
                el.style.zoom = view.z;
                el.style.transform = 'translate(' + (view.x / view.z) + 'px,' + (view.y / view.z) + 'px)';
            },
            fit: function (initial) {
                var bw = root, el = this.$refs.inner;
                if (!bw || !el) return;
                /* Measured with the zoom OFF, or the fit compounds on itself. */
                var kz = el.style.zoom, kt = el.style.transform;
                el.style.zoom = ''; el.style.transform = '';
                var w = bw.clientWidth, h = bw.clientHeight, iw = el.scrollWidth, ih = el.scrollHeight;
                el.style.zoom = kz; el.style.transform = kt;
                if (!iw || !ih || !w || !h) return;
                var top = cfg.toolbar ? 58 : 0;
                var z = Math.max(0.05, Math.min(1, (h - top - 12) / ih, (w - 12) / iw));
                fitZ = z;
                if (initial && typeof cfg.zoom === 'number') z = clamp(cfg.zoom, this.zMin(), cfg.maxZoom || 3);
                /* Centred on both axes; one that overflows starts at the top. */
                view = {x: Math.max(0, (w - iw * z) / 2), y: top + Math.max(0, (h - top - ih * z) / 2), z: z};
                this.applyView();
            },
            zoomAt: function (clientX, clientY, factor) {
                var bw = root, sc = this.realScale(), r = bw.getBoundingClientRect();
                var cx = (clientX - r.left) / sc, cy = (clientY - r.top) / sc;
                var nz = clamp(view.z * factor, this.zMin(), cfg.maxZoom || 3);
                view.x = cx - (cx - view.x) * nz / view.z;
                view.y = cy - (cy - view.y) * nz / view.z;
                view.z = nz;
                this.applyView();
            },
            zoomBy: function (factor) {
                var r = root.getBoundingClientRect();
                this.zoomAt(r.left + r.width / 2, r.top + r.height / 2, factor);
            },
            panStart: function (e) {
                if (e.button !== 0) return;
                if (e.target.closest && e.target.closest('input,button,textarea,[draggable="true"],[data-cb-ui]')) return;
                var self = this, sc = this.realScale();
                panned = false;
                pan = {sx: e.clientX, sy: e.clientY, ox: view.x, oy: view.y};
                var mv = function (ev) {
                    if (!pan) return;
                    var dx = (ev.clientX - pan.sx) / sc, dy = (ev.clientY - pan.sy) / sc;
                    if (Math.abs(dx) + Math.abs(dy) > 4) panned = true;
                    view.x = pan.ox + dx; view.y = pan.oy + dy;
                    self.applyView();
                };
                var up = function () { pan = null; window.removeEventListener('mousemove', mv); window.removeEventListener('mouseup', up); };
                window.addEventListener('mousemove', mv);
                window.addEventListener('mouseup', up);
            },
            onDbl: function (e) {
                if (e.target.closest && e.target.closest('input,button,[data-cb-ui]')) return;
                this.fit();
            },

            /* ── Events out ────────────────────────────────────────────── */

            emit: function (name, detail) {
                var d = plain(detail || {});
                d.id = cfg.id;
                root.dispatchEvent(new CustomEvent('bracket:' + name, {bubbles: true, detail: d}));
                root.dispatchEvent(new CustomEvent('bracket:change', {bubbles: true, detail: {id: cfg.id, action: name, state: this.getState()}}));
            },
            say: function (key, vars) { this.notice = fmt(t[key], vars); },
            guard: function () {
                if (!this.locked) return false;
                if (cfg.editable) this.say('lockedNote');
                return true;
            },

            /* ── The draw, read ────────────────────────────────────────── */

            nR: function () { return Math.round(Math.log2(this.size)); },
            res: function (k) { return this.results[k] || {}; },
            hasPin: function (r, i, sd) {
                return this.playins.some(function (q) { return q.r === r && q.i === i && q.side === sd; });
            },
            partBase: function (r, i, side) {
                if (r < 0) return null;
                if (r === 0) return this.fighters[2 * i + side] || null;
                return this.winner(r - 1, 2 * i + side);
            },
            part: function (r, i, side) {
                var p = this.playins.find(function (q) { return q.r === r && q.i === i && q.side === side; });
                return p ? this.playinWinner(p) : this.partBase(r, i, side);
            },
            playinWinner: function (p) {
                var w = this.res('P' + p.id).w;
                if (w == null || w === '') return null;
                var nc = this.fighters.find(function (f) { return f && sameId(f.id, p.newId); });
                if (nc && sameId(nc.id, w)) return nc;
                var occ = this.partBase(p.r, p.i, p.side);
                return (occ && sameId(occ.id, w)) ? occ : null;
            },
            winner: function (r, i) {
                if (r < 0) return null;
                var a = this.part(r, i, 0), b = this.part(r, i, 1), w = this.res(r + '-' + i).w;
                if (w != null && w !== '') {
                    if (a && sameId(a.id, w)) return a;
                    if (b && sameId(b.id, w)) return b;
                    return null;
                }
                if (r === 0) {
                    if (a && !b && !this.hasPin(0, i, 1)) return a;
                    if (b && !a && !this.hasPin(0, i, 0)) return b;
                }
                return null;
            },
            loser: function (r, i) {
                var win = this.winner(r, i);
                if (!win) return null;
                var a = this.part(r, i, 0), b = this.part(r, i, 1);
                return win === a ? b : a;
            },
            roundName: function (r, plural) {
                var nR = this.nR();
                if (r === nR - 1) return t.final;
                if (r === nR - 2) return plural ? t.semifinals : t.semifinal;
                if (r === nR - 3) return plural ? t.quarterfinals : t.quarterfinal;
                return fmt(t.roundOf, {n: this.size >> r});
            },
            roundLabel: function (r) { return r === this.nR() - 1 ? t.theFinal : this.roundName(r, false); },
            slotRef: function (tg) {
                var lbl = this.roundLabel(tg.r), pos = tg.side ? t.bottomSlot : t.topSlot;
                return lbl === t.theFinal ? fmt(t.slotOfFinal, {pos: pos}) : fmt(t.slotOf, {pos: pos, round: lbl, n: tg.i + 1});
            },
            chainClear: function (i) {
                if (this.res('0-' + i).w) return false;
                var nR = this.nR(), m = Math.floor(i / 2);
                for (var r = 1; r < nR; r++) { if (this.res(r + '-' + m).w) return false; m = Math.floor(m / 2); }
                return true;
            },
            byeMatch: function (i) {
                var a = this.fighters[2 * i], b = this.fighters[2 * i + 1];
                return (!a && !this.hasPin(0, i, 0)) || (!b && !this.hasPin(0, i, 1));
            },
            real0: function () {
                var self = this, size = this.size;
                var t0 = [];
                this.playins.forEach(function (q) { if (q.r === 0 && t0.indexOf(q.i) < 0) t0.push(q.i); });
                var order0 = [];
                for (var j = 0; j < size / 2; j++) if (t0.indexOf(j) < 0) order0.push(j);
                order0.push.apply(order0, t0);
                return order0.filter(function (j) { return !self.byeMatch(j); });
            },
            /* play-ins + real first-round bouts + every later bout incl. the final */
            totalNo: function () { return this.playins.length + this.real0().length + this.size / 2 - 1; },
            matchNo: function (r, i) {
                var size = this.size, nR = this.nR(), P = this.playins.length;
                if (r === nR - 1) return this.totalNo() + (this.hasBronzeBout() ? 1 : 0);
                var r0 = this.real0();
                if (r === 0) { var k = r0.indexOf(i); return k < 0 ? 0 : P + k + 1; }
                var before = P + r0.length;
                for (var rr = 1; rr < r; rr++) before += size >> (rr + 1);
                var nM = size >> (r + 1), tIs = [];
                this.playins.forEach(function (q) { if (q.r === r && tIs.indexOf(q.i) < 0) tIs.push(q.i); });
                /* bouts fed by a play-in go to the END of their round, so the play-in winner gets rest */
                var pos = tIs.indexOf(i) >= 0
                    ? (nM - tIs.length) + tIs.indexOf(i)
                    : i - tIs.filter(function (x) { return x < i; }).length;
                return before + pos + 1;
            },
            matNo: function (no) {
                var mats = Math.max(1, Math.round(cfg.mats || 2));
                return ((no - 1) % mats) + 1;
            },
            hasBronzeBout: function () { return this.third === 'match' && this.nR() >= 2; },
            currentNo: function () {
                var self = this, size = this.size, nR = this.nR(), min = Infinity;
                this.playins.forEach(function (p, ix) {
                    var nc = self.fighters.find(function (f) { return f && sameId(f.id, p.newId); });
                    var occ = self.partBase(p.r, p.i, p.side);
                    if (nc && occ && !self.playinWinner(p)) min = Math.min(min, ix + 1);
                });
                for (var r = 0; r < nR; r++) {
                    var nM = size >> (r + 1);
                    for (var i = 0; i < nM; i++) {
                        if (this.part(r, i, 0) && this.part(r, i, 1) && !this.res(r + '-' + i).w) {
                            var no = this.matchNo(r, i);
                            if (no > 0) min = Math.min(min, no);
                        }
                    }
                }
                if (this.hasBronzeBout()) {
                    var pool = this.bronzePool();
                    if (pool[0] && pool[1] && !this.bronzeWinner()) min = Math.min(min, this.totalNo());
                }
                return min;
            },
            bronzePool: function () {
                var nR = this.nR();
                if (nR < 2) return [null, null];
                return [this.loser(nR - 2, 0), this.loser(nR - 2, 1)];
            },
            bronzeWinner: function () {
                var w = this.res('B').w;
                if (w == null || w === '') return null;
                return this.bronzePool().find(function (f) { return f && sameId(f.id, w); }) || null;
            },

            /* ── The draw, changed ─────────────────────────────────────── */

            pickKey: function (k, f) {
                if (this.guard()) return;
                var cur = this.results[k] || {}, next = {};
                for (var p in this.results) next[p] = this.results[p];
                var w = sameId(cur.w, f.id) ? null : f.id;
                next[k] = Object.assign({}, cur, {w: w});
                this.results = next;
                var d = {key: k, winner: w};
                var m = /^(\d+)-(\d+)$/.exec(k);
                if (m) { d.round = +m[1]; d.match = +m[2]; }
                else if (k === 'B') d.bronze = true;
                else d.playin = k.slice(1);
                this.emit('winner', d);
            },
            setScore: function (k, side, v) {
                if (cfg.recordOnly) return;
                if (this.guard()) return;
                v = String(v == null ? '' : v).slice(0, 12);
                var next = {};
                for (var p in this.results) next[p] = this.results[p];
                var patch = {}; patch[side ? 'sb' : 'sa'] = v;
                next[k] = Object.assign({}, this.results[k] || {}, patch);
                this.results = next;
                this.emit('score', {key: k, side: side ? 'b' : 'a', value: v});
            },
            rename: function (id, v) {
                if (this.guard()) return;
                v = String(v || '').trim().slice(0, 120);
                if (!v) return;
                this.fighters = this.fighters.map(function (f) { return (f && sameId(f.id, id)) ? Object.assign({}, f, {name: v}) : f; });
                this.emit('rename', {competitor: id, name: v});
            },
            removeF: function (idx) {
                if (this.guard()) return;
                var gone = this.fighters[idx];
                this.fighters = this.fighters.map(function (f, j) { return j === idx ? null : f; });
                this.emit('remove', {slot: idx, competitor: bare(gone)});
            },

            /* The design file's late-entry rules, unchanged:
               0 — full and nothing fought yet: expand to the next power of two;
               1 — fill an open bye whose result chain is untouched;
               2 — a pigtail at the lowest unplayed first-round slot;
               3 — only when round one is underway, a later-round slot. */
            placeF: function (nf, extra) {
                if (this.guard()) return false;
                var self = this, size = this.size, nR = this.nR();
                var inRoster = this.roster.some(function (x) { return sameId(x.id, nf.id); });
                var dropRoster = function () { if (inRoster) self.roster = self.roster.filter(function (x) { return !sameId(x.id, nf.id); }); };
                var anyPlayed = Object.keys(this.results).some(function (k) { var x = self.results[k]; return x && x.w != null && x.w !== ''; }) || this.playins.length > 0;
                var full = true;
                for (var j = 0; j < size; j++) if (!this.fighters[j]) { full = false; break; }

                if (!anyPlayed && full && size < 16) {
                    var nfs = [];
                    for (var q = 0; q < size; q++) { nfs[2 * q] = this.fighters[q]; nfs[2 * q + 1] = null; }
                    nfs[1] = nf;
                    var seed = this.fighters[0];
                    this.size = size * 2; this.fighters = nfs; dropRoster(); Object.assign(this, extra || {});
                    this.say('expanded', {from: size, to: size * 2, name: nf.name, seed: seed ? seed.name : t.tbd});
                    this.emit('resize', {size: this.size, via: 'expand'});
                    this.emit('place', {competitor: bare(nf), slot: 1, via: 'expand'});
                    this.$nextTick(function () { self.fit(); });
                    return true;
                }
                var byeIdx = -1, byeMatch = -1;
                for (var i = 0; i < size / 2 && byeIdx < 0; i++) {
                    for (var sd = 0; sd < 2; sd++) {
                        var idx = 2 * i + sd;
                        if (this.fighters[idx] || this.hasPin(0, i, sd)) continue;
                        if (this.chainClear(i)) { byeIdx = idx; byeMatch = i; break; }
                    }
                }
                var target = null;
                if (byeIdx < 0) {
                    var nM0 = size / 2, order = [];
                    for (var a = 0, b = nM0 - 1; a <= b; a++, b--) { order.push(a); if (b !== a) order.push(b); }
                    for (var oi = 0; oi < order.length && !target; oi++) {
                        var mi = order[oi];
                        if (this.winner(0, mi)) continue;
                        for (var s2 = 1; s2 >= 0; s2--) {
                            if (this.hasPin(0, mi, s2)) continue;
                            if (this.partBase(0, mi, s2)) { target = {r: 0, i: mi, side: s2}; break; }
                        }
                    }
                    for (var r = 1; r < nR && !target; r++) {
                        var nM = size >> (r + 1);
                        for (var ii = 0; ii < nM && !target; ii++) {
                            if (this.winner(r, ii)) continue;
                            for (var s3 = 0; s3 < 2; s3++) {
                                if (this.hasPin(r, ii, s3)) continue;
                                if (this.partBase(r, ii, s3)) { target = {r: r, i: ii, side: s3}; break; }
                            }
                        }
                    }
                    if (!target) { this.say('complete'); return false; }
                }
                if (byeIdx >= 0) {
                    this.fighters = this.fighters.map(function (f, jj) { return jj === byeIdx ? nf : f; });
                    dropRoster(); Object.assign(this, extra || {});
                    this.say('byeFilled', {name: nf.name, round: this.roundLabel(0), n: byeMatch + 1});
                    this.emit('place', {competitor: bare(nf), slot: byeIdx, via: 'bye'});
                    return true;
                }
                var occ = this.partBase(target.r, target.i, target.side);
                var pin = {id: Date.now(), newId: nf.id, r: target.r, i: target.i, side: target.side};
                this.fighters = this.fighters.concat([nf]);
                this.playins = this.playins.concat([pin]);
                dropRoster(); Object.assign(this, extra || {});
                this.say('pigtail', {name: nf.name, opp: occ ? occ.name : t.tbd, slot: this.slotRef(target)});
                this.emit('playin', {playin: {id: pin.id, r: pin.r, i: pin.i, side: pin.side}, competitor: bare(nf), opponent: bare(occ)});
                return true;
            },
            moveSlot: function (from, r, i, side) {
                if (this.guard()) return;
                if (r !== 0) { this.say('reseedFirstOnly'); return; }
                var to = 2 * i + side, fi = Math.floor(from / 2);
                if (to === from) return;
                if (!this.chainClear(i) || !this.chainClear(fi)) { this.say('reseedDecided'); return; }
                if (this.hasPin(0, i, side) || this.hasPin(0, fi, from % 2)) { this.say('playinAttached'); return; }
                var A = this.fighters[from], B = this.fighters[to];
                if (!A) return;
                var fs = this.fighters.slice();
                fs[from] = null; fs[to] = A;
                this.fighters = fs;
                if (B) this.basket = this.basket.concat([B]);
                this.notice = B ? fmt(t.tookPlace, {a: A.name, b: B.name}) : fmt(t.movedOpen, {a: A.name});
                this.emit('move', {from: from, to: to, competitor: bare(A), displaced: bare(B)});
            },
            placeBasket: function (id, r, i, side) {
                if (this.guard()) return;
                if (r !== 0) { this.say('placeFirstOnly'); return; }
                if (!this.chainClear(i)) { this.say('pathDecided'); return; }
                if (this.hasPin(0, i, side)) { this.say('playinAttached'); return; }
                var f = this.basket.find(function (x) { return sameId(x.id, id); });
                if (!f) return;
                var to = 2 * i + side, B = this.fighters[to];
                var fs = this.fighters.slice(); fs[to] = f;
                this.fighters = fs;
                this.basket = this.basket.filter(function (x) { return !sameId(x.id, id); }).concat(B ? [B] : []);
                this.notice = B ? fmt(t.tookPlaceBasket, {a: f.name, b: B.name}) : fmt(t.placedInto, {a: f.name, slot: this.slotRef({r: r, i: i, side: side})});
                this.emit('place', {competitor: bare(f), slot: to, via: 'basket', displaced: bare(B)});
            },
            dropToBasket: function () {
                if (this.guard()) return;
                if (this.dragSlot == null) return;
                var from = this.dragSlot, A = this.fighters[from];
                this.dragSlot = null; this.carry = null;
                if (!A) return;
                var fs = this.fighters.slice(); fs[from] = null;
                this.fighters = fs;
                this.basket = this.basket.concat([A]);
                this.say('toBasket', {a: A.name});
                this.emit('basket', {competitor: bare(A), from: from});
            },
            dropAt: function (r, i, side) {
                if (cfg.recordOnly) return;
                if (this.guard()) return;
                this.carry = null;
                if (this.dragBasket != null) { var bid = this.dragBasket; this.dragBasket = null; this.placeBasket(bid, r, i, side); return; }
                if (this.dragSlot != null) { var from = this.dragSlot; this.dragSlot = null; this.moveSlot(from, r, i, side); return; }
                var did = this.dragId;
                var f = this.roster.find(function (x) { return sameId(x.id, did); });
                this.dragId = null;
                if (!f) return;
                if (this.winner(r, i)) { this.say('matchDecided'); return; }
                if (this.hasPin(r, i, side)) { this.say('slotHasPlayin'); return; }
                if (r === 0 && !this.fighters[2 * i + side]) {
                    if (!this.chainClear(i)) { this.say('byeBlocked'); return; }
                    var at = 2 * i + side;
                    this.fighters = this.fighters.map(function (x, j) { return j === at ? f : x; });
                    this.roster = this.roster.filter(function (x) { return !sameId(x.id, f.id); });
                    this.say('byeFilledShort', {name: f.name, round: this.roundLabel(0), n: i + 1});
                    this.emit('place', {competitor: bare(f), slot: at, via: 'roster'});
                    return;
                }
                var occ = this.partBase(r, i, side);
                if (!occ) { this.say('slotEmpty'); return; }
                var pin = {id: Date.now(), newId: f.id, r: r, i: i, side: side};
                this.fighters = this.fighters.concat([f]);
                this.roster = this.roster.filter(function (x) { return !sameId(x.id, f.id); });
                this.playins = this.playins.concat([pin]);
                this.say('pigtailShort', {name: f.name, opp: occ.name, slot: this.slotRef({r: r, i: i, side: side})});
                this.emit('playin', {playin: {id: pin.id, r: r, i: i, side: side}, competitor: bare(f), opponent: bare(occ)});
            },
            addFighter: function () {
                if (this.guard()) return;
                var name = this.addName.trim();
                if (!name) return;
                var club = this.addClubSel === '__new' ? (this.addClubNew.trim() || t.independent) : (this.addClubSel || t.independent);
                var known = this.fighters.find(function (f) { return f && f.club === club; });
                /* Negative, so a new entrant can never collide with a real id. */
                var nf = person({id: -Date.now(), name: name, cc: this.addCountry, club: club, ab: known ? known.ab : initials(club)});
                this.emit('add', {competitor: bare(nf)});
                this.placeF(nf, {addOpen: false, addName: '', addCountry: '', addClubSel: '', addClubNew: '', countryOpen: false, clubOpen: false});
            },

            /* ── Board-level controls ──────────────────────────────────── */

            toggleLock: function () {
                if (!cfg.editable) return;
                this.locked = !this.locked; this.edit = false; this.carry = null;
                this.emit('lock', {locked: this.locked});
            },
            setLayout: function (v) {
                var self = this;
                this.layout = v === 'split' ? 'split' : 'linear';
                this.emit('layout', {layout: this.layout});
                this.$nextTick(function () { self.fit(); });
            },
            setThird: function (v) {
                if (cfg.recordOnly) return;
                this.third = v === 'shared' ? 'shared' : 'match';
                this.menuOpen = false;
                this.emit('third-place', {mode: this.third});
            },
            setSize: function (v) {
                if (this.guard()) return;
                var self = this;
                this.size = clamp(nextPow2(v), 2, 64);
                this.fighters = this.fighters.slice(0, this.size);
                while (this.fighters.length < this.size) this.fighters.push(null);
                this.results = {}; this.playins = []; this.notice = '';
                this.emit('resize', {size: this.size, via: 'menu'});
                this.$nextTick(function () { self.fit(); });
            },
            toggleEdit: function () {
                if (cfg.recordOnly) return;
                if (this.guard()) return;
                this.edit = !this.edit; this.menuOpen = false;
            },
            openAdd: function () {
                if (cfg.recordOnly) return;
                this.menuOpen = false;
                if (this.guard()) return;
                this.addOpen = true;
            },
            resetResults: function () {
                if (cfg.recordOnly) return;
                if (this.guard()) return;
                var self = this;
                this.menuOpen = false;
                var go = function () { self.results = {}; self.emit('reset', {}); };
                if (window.confirmAction) {
                    window.confirmAction({title: t.resetResults, message: t.resetConfirm, type: 'danger', confirmText: t.resetResults})
                        .then(function (ok) { if (ok) go(); });
                } else go();
            },

            /* ── Pointer + touch on a competitor ───────────────────────── */

            slotClick: function (s) {
                if (Date.now() - justPicked < 600) return;
                if (this.carry) { if (s.kind === 'match') this.dropAt(s.r, s.i, s.side); return; }
                if (panned) { panned = false; return; }
                if (this.locked || this.edit || !s.f) return;
                this.pickKey(s.k, s.f);
            },
            canDrop: function (s) {
                return s.kind === 'match' && !this.locked && !cfg.recordOnly && (this.dragId != null || this.dragSlot != null || this.dragBasket != null);
            },
            dragStartSlot: function (e, s) {
                if (!s.drag) { e.preventDefault(); return; }
                try { e.dataTransfer.setData('text/plain', 'slot:' + (2 * s.i + s.side)); e.dataTransfer.effectAllowed = 'move'; } catch (err) {}
                this.dragSlot = 2 * s.i + s.side; this.dragId = null; this.dragBasket = null;
            },
            dragStartBasket: function (e, f) {
                try { e.dataTransfer.setData('text/plain', 'basket:' + f.id); e.dataTransfer.effectAllowed = 'move'; } catch (err) {}
                this.dragBasket = f.id; this.dragId = null; this.dragSlot = null;
            },
            dragStartRoster: function (e, f) {
                try { e.dataTransfer.setData('text/plain', String(f.id)); e.dataTransfer.effectAllowed = 'move'; } catch (err) {}
                this.dragId = f.id; this.dragSlot = null; this.dragBasket = null;
            },
            dragEnd: function () {
                if (this.carry) return;
                this.dragSlot = null; this.dragBasket = null; this.dragId = null;
            },
            /* A phone has no drag and drop in a WebView. Hold a competitor to
               pick them up, then tap where they go — the same moves, the same
               rules, the same events. */
            pressStart: function (e, item) {
                if (this.locked || cfg.recordOnly || !e.touches || e.touches.length !== 1) return;
                if (item.type === 'slot' && !item.s.drag) return;
                var self = this, tt = e.touches[0];
                press = {x: tt.clientX, y: tt.clientY, timer: setTimeout(function () { press = null; self.pickUp(item); }, 420)};
            },
            pressMove: function (e) {
                if (!press || !e.touches.length) return;
                var tt = e.touches[0];
                if (Math.hypot(tt.clientX - press.x, tt.clientY - press.y) > 8) { clearTimeout(press.timer); press = null; }
            },
            pressEnd: function () { if (press) { clearTimeout(press.timer); press = null; } },
            pickUp: function (item) {
                justPicked = Date.now();
                this.dragSlot = null; this.dragBasket = null; this.dragId = null;
                var f;
                if (item.type === 'slot') { this.dragSlot = 2 * item.s.i + item.s.side; f = item.s.f; }
                else if (item.type === 'basket') { this.dragBasket = item.f.id; f = item.f; }
                else { this.dragId = item.f.id; f = item.f; this.addOpen = false; }
                this.carry = {name: f ? f.name : ''};
                try { if (navigator.vibrate) navigator.vibrate(15); } catch (e) {}
            },
            cancelCarry: function () {
                this.carry = null; this.dragSlot = null; this.dragBasket = null; this.dragId = null;
            },
            basketTap: function () { if (this.carry && this.dragSlot != null) this.dropToBasket(); },

            /* ── Add-competitor form helpers ───────────────────────────── */

            rosterList: function () {
                var q = this.search.trim().toLowerCase(), countries = cfg.countries || [];
                return this.roster.filter(function (f) {
                    if (!q) return true;
                    var cn = (countries.find(function (c) { return c.cc === f.cc; }) || {}).name || '';
                    return (f.name + ' ' + f.club + ' ' + cn).toLowerCase().indexOf(q) >= 0;
                });
            },
            countryList: function () {
                var q = this.countryQ.trim().toLowerCase();
                return (cfg.countries || []).filter(function (c) { return !q || c.name.toLowerCase().indexOf(q) >= 0; }).slice(0, 80);
            },
            countryName: function (cc) { return ((cfg.countries || []).find(function (c) { return c.cc === cc; }) || {}).name || ''; },
            flagCss: function (cc) { return flagSet[cc] ? cssUrl((cfg.flagBase || '') + '/' + cc + '.svg') : 'none'; },
            clubList: function () {
                var out = [];
                this.fighters.concat(this.roster).forEach(function (f) {
                    if (f && f.club && !out.some(function (c) { return c.name === f.club; })) out.push({name: f.club, ab: f.ab});
                });
                return out;
            },

            /* ── Everything the markup draws, computed once per change ─── */

            /* ⚠️ The canvas height is written straight onto the element, never
               through a `:style` binding: a binding rewrites the whole style
               attribute on every render — a hover is a render — and that
               wiped the camera's zoom and transform, so the board jumped
               out from under the pointer mid-click. */
            render: function () {
                this.vm = this.compute();
                var el = root && root.querySelector('.cb-inner');
                if (el) el.style.height = this.vm.innerHeight;
            },

            mkSlot: function (o) {
                var f = o.f, res = this.res(o.k), locked = this.locked, edit = this.edit && !locked;
                var win = !!f && sameId(o.winId, f.id);
                var pin = o.emptyLabel === t.playinWinner;
                var hover = !!f && sameId(this.hoverId, f.id);
                return {
                    key: o.k + '-' + o.side, k: o.k, r: o.r, i: o.i, side: o.side, kind: o.kind, f: f,
                    show: !!f && !(edit && o.kind === 'match'), editing: !!f && edit && o.kind === 'match', empty: !f,
                    emptyLabel: o.emptyLabel || t.awaitingWinner,
                    emptyRing: pin ? '#caa14f' : '#d5dae3', emptyColor: pin ? '#8a6d20' : '#97a1b4',
                    canRemove: o.removeIdx != null && !locked, removeIdx: o.removeIdx,
                    drag: false, outline: 'none', win: win,
                    ring: win ? GOLD : '#d5dae3',
                    edge: o.side ? '#2f5fae' : '#c0392b',
                    /* The corners ARE the fill: red is the upper half, blue the
                       lower; the winner's tint is the same hue carried deeper;
                       hover is green because it is about the pointer. */
                    bg: hover ? '#c9f0da' : (win ? (o.side ? '#d3e0f7' : '#f8d6d1') : (o.side ? '#eaf0fb' : '#fdecea')),
                    score: res[o.side ? 'sb' : 'sa'] != null ? res[o.side ? 'sb' : 'sa'] : ''
                };
            },
            finish: function (s) {
                s.style = 'border-left-color:' + s.edge + ';background:' + s.bg + ';outline:' + s.outline
                    + ';border-bottom:' + (s.side ? 'none' : '1px solid #eef1f6');
                return s;
            },
            matchView: function (r, i, cur) {
                var self = this, k = r + '-' + i, w = this.winner(r, i);
                var a = this.part(r, i, 0), b = this.part(r, i, 1);
                var dragging = this.dragId != null || this.dragSlot != null || this.dragBasket != null;
                var slots = [0, 1].map(function (sd) {
                    var idx = 2 * i + sd, lbl = null, rm = null;
                    if (self.hasPin(r, i, sd)) lbl = t.playinWinner;
                    else if (r === 0 && !self.fighters[idx]) lbl = t.bye;
                    if (r === 0 && self.edit && !self.res(k).w && self.fighters[idx] && !w) rm = idx;
                    var s = self.mkSlot({f: sd ? b : a, k: k, side: sd, winId: w ? w.id : null, kind: 'match', emptyLabel: lbl, removeIdx: rm, r: r, i: i});
                    var eligible = !self.locked && !w && (self.dragId != null ? true : (r === 0 && self.chainClear(i)));
                    if (dragging && eligible) s.outline = DASH;
                    if (!self.locked && !self.edit && !cfg.recordOnly && r === 0 && self.fighters[idx] && self.chainClear(i) && !self.hasPin(0, i, sd)) s.drag = true;
                    return self.finish(s);
                });
                var no = this.matchNo(r, i);
                /* A bye is DRAWN — the competitor is in the draw — but it has no
                   bout, so no number and no mat, and it says why. */
                return {key: 'm' + k, slots: slots, note: '',
                    anim: (a && b && !w && no === cur) ? PULSE : 'none',
                    tagL: no > 0 ? fmt(t.match, {n: no}) : t.byeNoBout,
                    tagR: no > 0 ? fmt(t.mat, {n: this.matNo(no)}) : ''};
            },
            playinView: function (p, cur) {
                var self = this, k = 'P' + p.id, ix = this.playins.indexOf(p);
                var nc = this.fighters.find(function (f) { return f && sameId(f.id, p.newId); });
                var occ = this.partBase(p.r, p.i, p.side), w = this.playinWinner(p);
                var lbl = this.roundLabel(p.r);
                return {key: k,
                    slots: [this.finish(this.mkSlot({f: nc, k: k, side: 0, winId: w ? w.id : null, kind: 'playin', r: p.r, i: p.i})),
                            this.finish(this.mkSlot({f: occ, k: k, side: 1, winId: w ? w.id : null, kind: 'playin', r: p.r, i: p.i}))],
                    anim: (nc && occ && !w && ix + 1 === cur) ? PULSE : 'none',
                    note: fmt(t.winnerTo, {slot: lbl === t.theFinal ? t.final : lbl + ' ' + (p.i + 1)}),
                    tagL: fmt(t.match, {n: ix + 1}), tagR: fmt(t.mat, {n: self.matNo(ix + 1)})};
            },
            chip: function (pos, mc, f) {
                return f ? {pos: pos, mc: mc, f: f} : {pos: pos, mc: mc, f: null};
            },
            colWidth: function () {
                var nameFont = "600 12.48px Poppins, system-ui, sans-serif", clubFont = "400 9.92px Poppins, system-ui, sans-serif";
                var widest = 0, show = cfg.showClubs !== false;
                this.fighters.concat(this.basket).forEach(function (f) {
                    if (!f) return;
                    widest = Math.max(widest, measure(f.name, nameFont) + 24, show ? measure(f.club, clubFont) + 19 : 0);
                });
                /* photo 39 · gaps 16 · padding 20 · edge 3 · score 48 · slack 6 */
                return Math.round(Math.min(420, Math.max(242, widest + 132)));
            },
            compute: function () {
                var self = this, size = this.size, nR = this.nR(), cur = this.currentNo(), hid = this.hoverId;
                var isH = function (w) { return !!(w && sameId(w.id, hid)); };
                var lines = function (kind, mirror, c1, c2, co) {
                    var S = mirror ? 'right' : 'left';
                    if (kind === 'pair') return [
                        'position:absolute;' + S + ':100%;width:18px;height:2px;top:calc(25% - 1px);background:' + c1,
                        'position:absolute;' + S + ':100%;width:18px;height:2px;top:calc(75% - 1px);background:' + c2,
                        'position:absolute;' + S + ':calc(100% + 16px);width:2px;top:25%;height:calc(25% + 1px);background:' + c1,
                        'position:absolute;' + S + ':calc(100% + 16px);width:2px;top:50%;height:calc(25% + 1px);background:' + c2,
                        'position:absolute;' + S + ':calc(100% + 18px);width:18px;height:2px;top:calc(50% - 1px);background:' + co
                    ];
                    return ['position:absolute;' + S + ':100%;width:36px;height:2px;top:calc(50% - 1px);background:' + co];
                };
                var mkPair = function (r, i1, mirror) {
                    var w1 = self.winner(r, i1), w2 = self.winner(r, i1 + 1), h1 = isH(w1), h2 = isH(w2);
                    return {matches: [self.matchView(r, i1, cur), self.matchView(r, i1 + 1, cur)],
                        lines: lines('pair', mirror, h1 ? HL : (w1 ? GOLD : GREY), h2 ? HL : (w2 ? GOLD : GREY),
                            (h1 || h2) ? HL : ((w1 && w2) ? GOLD : GREY))};
                };
                var mkSingle = function (r, i, mirror, connected) {
                    var w = self.winner(r, i), c = isH(w) ? HL : (w ? GOLD : GREY);
                    return {matches: [self.matchView(r, i, cur)], lines: connected ? lines('single', mirror, null, null, c) : []};
                };
                var anim = function (d) { return 'cb-in .6s ease ' + d.toFixed(2) + 's both'; };
                var cols = [];
                if (this.layout === 'split' && nR >= 2) {
                    for (var r = 0; r < nR - 1; r++) {
                        var half = (size >> (r + 1)) / 2, pairs = [];
                        if (half === 1) pairs.push(mkSingle(r, 0, false, true));
                        else for (var j = 0; j < half / 2; j++) pairs.push(mkPair(r, 2 * j, false));
                        cols.push({key: 'L' + r, name: this.roundName(r, true), pairs: pairs, anim: anim(r * 0.12)});
                    }
                    cols.push({key: 'F', name: t.podiumFinal, podium: true, bz: true, w: 280,
                        podStyle: 'position:absolute;left:0;right:0;bottom:calc(50% + 78px);display:flex;flex-direction:column;gap:10px;z-index:5',
                        bzStyle: 'position:absolute;top:calc(50% + 128px);left:0;right:0;z-index:5',
                        pairs: [mkSingle(nR - 1, 0, false, false)], anim: anim((nR - 1) * 0.12)});
                    for (var r2 = nR - 2; r2 >= 0; r2--) {
                        var h2 = (size >> (r2 + 1)) / 2, p2 = [];
                        if (h2 === 1) p2.push(mkSingle(r2, 1, true, true));
                        else for (var j2 = h2 / 2; j2 < h2; j2++) p2.push(mkPair(r2, 2 * j2, true));
                        cols.push({key: 'R' + r2, name: this.roundName(r2, true), pairs: p2, anim: anim(r2 * 0.12)});
                    }
                } else {
                    for (var r3 = 0; r3 < nR - 1; r3++) {
                        var p3 = [], nP = (size >> (r3 + 1)) / 2;
                        for (var j3 = 0; j3 < nP; j3++) p3.push(mkPair(r3, 2 * j3, false));
                        cols.push({key: 'C' + r3, name: this.roundName(r3, true), pairs: p3, anim: anim(r3 * 0.12)});
                    }
                    cols.push({key: 'F', name: t.final, pairs: [mkSingle(nR - 1, 0, false, false)], anim: anim((nR - 1) * 0.12)});
                    cols.push({key: 'P', name: t.podium, podium: true, bz: true, pairs: [], w: 290, anim: 'cb-in .6s ease .55s both',
                        podStyle: 'position:absolute;left:0;right:0;bottom:calc(50% + 76px);display:flex;flex-direction:column;gap:10px;z-index:5',
                        bzStyle: 'position:absolute;top:calc(50% + 20px);transform:translateY(-50%);left:0;right:0;z-index:5'});
                }
                var colW = this.colWidth();
                cols.forEach(function (c) {
                    var w = c.w ? Math.max(c.w, colW + 34) : colW;
                    c.podium = !!c.podium; c.bz = !!c.bz;
                    c.style = 'width:' + w + 'px;animation:' + c.anim;
                    c.podStyle = c.podStyle || 'flex:1;display:flex;flex-direction:column;justify-content:center;gap:12px';
                    c.bzStyle = c.bzStyle || 'flex:none;margin-top:12px';
                });

                var playinCol = null;
                if (this.playins.length) {
                    playinCol = {w: colW + 'px', cards: this.playins.map(function (p) {
                        var nM = size >> (p.r + 1), done = !!self.playinWinner(p);
                        return {key: 'pc' + p.id, m: self.playinView(p, cur),
                            style: 'position:absolute;left:0;right:0;transform:translateY(-50%);top:' + (((2 * p.i + 1) / (2 * nM)) * 100).toFixed(2) + '%',
                            link: p.r === 0 && (self.layout !== 'split' || p.i < (size >> 1) / 2),
                            lineStyle: 'position:absolute;left:100%;top:calc(50% - 1px);width:36px;height:2px;background:'
                                + (done ? GOLD : 'repeating-linear-gradient(90deg,rgba(216,178,95,.75) 0 6px,transparent 6px 12px)')};
                    })};
                }

                var champ = this.winner(nR - 1, 0), runner = this.loser(nR - 1, 0);
                var pool = this.bronzePool(), bMode = this.hasBronzeBout(), bw = this.bronzeWinner();
                var podium = [this.chip('2', '#97a1b4', runner)];
                if (nR >= 2) {
                    if (bMode) podium.push(this.chip('3', '#b07a45', bw));
                    else { podium.push(this.chip('3', '#b07a45', pool[0])); podium.push(this.chip('3', '#b07a45', pool[1])); }
                }
                var bronze = null;
                if (bMode) {
                    var bno = this.totalNo(), bwId = bw ? bw.id : null;
                    bronze = {no: bno, mat: this.matNo(bno),
                        tag: fmt(t.thirdPlaceMatch, {n: bno}), matTag: fmt(t.mat, {n: this.matNo(bno)}),
                        anim: (pool[0] && pool[1] && !bw && bno === cur) ? PULSE : 'none',
                        slots: [0, 1].map(function (sd) {
                            return self.finish(self.mkSlot({f: pool[sd], k: 'B', side: sd, winId: bwId, kind: 'bronze', emptyLabel: t.semifinalLoser, r: -1, i: sd}));
                        })};
                }
                return {cols: cols, playinCol: playinCol, champ: champ, podium: podium, bronze: bronze,
                    celebrate: !!champ && cfg.celebration !== false,
                    innerHeight: HEIGHTS[this.layout][size] || '1420px'};
            }
        };
    };
})();
</script>
