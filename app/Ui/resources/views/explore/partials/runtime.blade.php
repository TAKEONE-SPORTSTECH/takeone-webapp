{{-- New Explore runtime. Global function (re-assignable) because the mobile
     shell re-runs inline scripts on every in-place navigation. Everything it
     renders goes through x-text / bound attributes — never innerHTML. --}}
<script>
window.t1Explore = function () {
    const D = JSON.parse(document.getElementById('t1-explore-data').textContent);
    const L10N = D.l10n;
    const tr = (k, n) => (L10N[k] || k).replace(':n', n).replace(':km', n);
    return {
        D, tab: 'all', act: null, grp: null, q: '', radius: 'any', radiusOpen: false,
        map: false, hl: null, pos: null, locState: 'idle',
        events: [], finished: [], pastShown: 8, eventsLoaded: false, _map: null, _layer: null,
        only: null,
        init() {
            const qs = new URLSearchParams(location.search);
            if (qs.get('only')) this.only = qs.get('only').split(',').filter(Boolean);
            if (['all', 'activities', 'offers', 'clubs', 'events', 'coaches', 'venues', 'clinics', 'specialists', 'federations', 'shops'].includes(qs.get('tab'))) this.tab = qs.get('tab');
            this.loadEvents();
            this.locate(true);
            this.$watch('tab', () => { this.map = false; this.destroyMap(); });
            this.$watch('hl', () => this.syncPins());
            this.$watch('map', (v) => v ? this.$nextTick(() => this.buildMap()) : this.destroyMap());
            ['act', 'q', 'radius', 'pos', 'eventsLoaded'].forEach(k => this.$watch(k, () => { if (this.map) this.$nextTick(() => this.buildMap()); }));
        },
        t: tr,
        loadEvents() {
            fetch(D.eventsUrl, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                .then(r => r.ok ? r.json() : { events: [], finished: [] })
                .then(j => { this.events = (j.events || []).filter(e => e.state !== 'ended'); this.finished = j.finished || []; })
                .catch(() => { this.events = []; })
                .finally(() => { this.eventsLoaded = true; });
        },
        locate(quiet) {
            if (!navigator.geolocation) { this.locState = 'off'; return; }
            this.locState = 'asking';
            navigator.geolocation.getCurrentPosition(
                p => { this.pos = { lat: p.coords.latitude, lng: p.coords.longitude }; this.locState = 'on'; },
                () => { this.locState = 'off'; },
                { enableHighAccuracy: false, timeout: 8000, maximumAge: 600000 });
        },
        km(c) {
            if (!this.pos || c.lat == null || c.lng == null) return null;
            const R = 6371, rad = d => d * Math.PI / 180;
            const dLat = rad(c.lat - this.pos.lat), dLng = rad(c.lng - this.pos.lng);
            const a = Math.sin(dLat / 2) ** 2 + Math.cos(rad(this.pos.lat)) * Math.cos(rad(c.lat)) * Math.sin(dLng / 2) ** 2;
            return Math.round(R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a)) * 10) / 10;
        },
        needle() { return this.q.trim().toLowerCase(); },
        actName() { const a = this.D.activities.find(x => x.uuid === this.act); return a ? a.name : null; },
        sportMatches(sport) {
            const n = this.actName(); if (!n) return true; if (!sport) return false;
            const a = n.toLowerCase(), s = String(sport).toLowerCase();
            const w = s.split(/[\s\-]+/)[0];
            return a.includes(s) || s.includes(a) || (w.length > 3 && a.includes(w));
        },
        get clubs() {
            const q = this.needle();
            return this.D.clubs
                .map(c => ({ ...c, km: this.km(c) }))
                .filter(c => !this.only || this.only.includes(c.key))
                .filter(c => !this.act || c.activityIds.includes(this.act))
                .filter(c => !q || (c.name + ' ' + (c.area || '') + ' ' + c.activities.join(' ')).toLowerCase().includes(q))
                .filter(c => this.radius === 'any' || !this.pos || (c.km != null && c.km <= +this.radius))
                .sort((a, b) => (a.km ?? 1e9) - (b.km ?? 1e9) || a.name.localeCompare(b.name));
        },
        get evs() {
            const q = this.needle();
            const rank = { live: 0, upcoming: 1, ended: 2 };
            return this.events
                .filter(e => this.sportMatches(e.sport))
                .filter(e => !q || ((e.title || '') + ' ' + (e.club || '') + ' ' + (e.sport || '') + ' ' + (e.location || '')).toLowerCase().includes(q))
                .sort((a, b) => (rank[a.state] ?? 3) - (rank[b.state] ?? 3));
        },
        /* Finished events, newest first (the server's order), under the same search and sport chip. */
        get pastEvs() {
            const q = this.needle();
            return this.finished
                .filter(e => this.sportMatches(e.sport))
                .filter(e => !q || ((e.title || '') + ' ' + (e.club || '') + ' ' + (e.sport || '') + ' ' + (e.location || '')).toLowerCase().includes(q));
        },
        get coaches() {
            const q = this.needle();
            return this.D.trainers.filter(c => !q || (c.name + ' ' + c.role + ' ' + (c.club || '')).toLowerCase().includes(q));
        },
        get products() {
            const q = this.needle();
            return this.D.products.filter(p => !q || (p.name + ' ' + (p.brand || '') + ' ' + p.club).toLowerCase().includes(q));
        },
        get feds() {
            const q = this.needle();
            return this.D.federations.filter(f => !q || (f.name + ' ' + (f.short || '') + ' ' + f.sports.join(' ')).toLowerCase().includes(q));
        },
        /* Federation events carry the federation's short name (or name) as their host. */
        fedHosts(f) { return [f.short, f.name].filter(Boolean).map(s => s.toLowerCase()); },
        fedEventCount(f) { const h = this.fedHosts(f); return this.events.filter(e => e.club && h.includes(e.club.toLowerCase())).length; },
        fedEvents(f) { this.q = f.short || f.name; this.go('events'); },
        /* Verticals the design promises that have no data yet. */
        get soonTabs() {
            const s = (k, icon, color) => ({ k, icon, color, title: tr('soon_' + k + '_title'), sub: tr('soon_' + k + '_sub'), filters: tr('soon_' + k + '_f').split('|') });
            return [s('offers', 'bi-tag', '#be123c'), s('venues', 'bi-pin-map', '#0369a1'), s('clinics', 'bi-heart-pulse', '#0f766e'), s('specialists', 'bi-clipboard2-pulse', '#7c3aed')];
        },
        get acts() {
            const q = this.needle();
            return this.D.activities
                .filter(a => !this.grp || a.group === this.grp)
                .filter(a => !q || (a.name + ' ' + a.description + ' ' + a.styles.join(' ')).toLowerCase().includes(q));
        },
        actsIn(g) { return this.acts.filter(a => a.group === g).sort((x, y) => y.clubs - x.clubs); },
        get popular() { return [...this.D.activities].filter(a => !this.act || a.uuid === this.act).sort((x, y) => y.clubs - x.clubs).slice(0, 8); },
        get chipActs() { return this.D.activities.filter(a => a.clubs > 0); },
        groupLabel(k) { const g = this.D.groups.find(x => x.key === k); return g ? g.label : ''; },
        tabs() {
            return [
                { k: 'all', icon: 'bi-compass', label: tr('tab_all'), n: '' },
                { k: 'activities', icon: 'bi-grid', label: tr('tab_activities'), n: this.acts.length },
                { k: 'offers', icon: 'bi-tag', label: tr('tab_offers'), n: tr('soon') },
                { k: 'clubs', icon: 'bi-building', label: tr('tab_clubs'), n: this.clubs.length },
                { k: 'events', icon: 'bi-calendar-event', label: tr('tab_events'), n: this.evs.length, hide: this.eventsLoaded && !this.events.length && !this.finished.length },
                { k: 'coaches', icon: 'bi-person-badge', label: tr('tab_coaches'), n: this.coaches.length, hide: !this.D.trainers.length },
                { k: 'venues', icon: 'bi-pin-map', label: tr('tab_venues'), n: tr('soon') },
                { k: 'clinics', icon: 'bi-heart-pulse', label: tr('tab_clinics'), n: tr('soon') },
                { k: 'specialists', icon: 'bi-clipboard2-pulse', label: tr('tab_specialists'), n: tr('soon') },
                { k: 'federations', icon: 'bi-bank', label: tr('tab_federations'), n: this.feds.length },
                { k: 'shops', icon: 'bi-bag', label: tr('tab_shop'), n: this.products.length },
            ].filter(x => !x.hide);
        },
        go(k) { this.tab = k; this.$refs.verts && this.$refs.verts.scrollIntoView({ block: 'nearest', behavior: 'smooth' }); },
        place(c) { return c.km != null ? tr('km', c.km) + (c.area ? ' · ' + c.area : '') : (c.area || ''); },
        evPrice(e) { return e.price_from ? (e.price_varies ? tr('from') + ' ' + e.price_from : e.price_from) : (e.fee || tr('free')); },
        evState(e) { return e.state === 'live' ? tr('live') : (e.state === 'ended' ? tr('finished') : (e.spots_left > 0 && e.spots_left <= 10 ? tr('spots_left', e.spots_left) : tr('open'))); },
        fileUrl(p) { return p ? '/file/' + encodeURI(p) : null; },
        /* ---- map (Leaflet, vendored; loaded only when switched on) ---- */
        loadLeaflet() {
            if (window.L) return Promise.resolve();
            if (window.__t1Leaflet) return window.__t1Leaflet;
            window.__t1Leaflet = new Promise((ok, no) => {
                const css = document.createElement('link'); css.rel = 'stylesheet'; css.href = D.leaflet.css; document.head.appendChild(css);
                const s = document.createElement('script'); s.src = D.leaflet.js; s.onload = ok; s.onerror = no; document.head.appendChild(s);
            });
            return window.__t1Leaflet;
        },
        destroyMap() { if (this._map) { this._map.remove(); this._map = null; } },
        buildMap() {
            this.loadLeaflet().then(() => {
                const el = this.$refs['lmap_' + this.tab]; if (!el || !this.map) return;
                this.destroyMap();
                const m = L.map(el, { zoomControl: true, scrollWheelZoom: true });
                L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 18, attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>' }).addTo(m);
                const pts = [];
                if (this.pos) { L.marker([this.pos.lat, this.pos.lng], { interactive: false, icon: L.divIcon({ className: 't1-pinwrap', html: '<span class="t1-you"></span>', iconSize: [0, 0] }) }).addTo(m); pts.push([this.pos.lat, this.pos.lng]); }
                this.mapItems().filter(c => c.lat != null && c.lng != null).forEach(c => {
                    const b = document.createElement('button'); b.type = 'button'; b.className = 't1-pin'; b.dataset.key = c.key;
                    if (c.icon) { const i = document.createElement('i'); i.className = 'bi ' + c.icon; b.append(i, ' '); }
                    b.append(c.pin);
                    const mk = L.marker([c.lat, c.lng], { icon: L.divIcon({ className: 't1-pinwrap', html: b, iconSize: [0, 0] }) }).addTo(m);
                    mk.on('click', () => {
                        this.hl = c.key;
                        // The card on screen if there is one (the same card can sit in a hidden tab), else open it.
                        const card = [...document.querySelectorAll('[data-card="' + CSS.escape(c.key) + '"]')].find(el => el.offsetParent);
                        if (card) card.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                        else if (c.url) location.href = c.url;
                    });
                    pts.push([c.lat, c.lng]);
                });
                if (pts.length > 1) m.fitBounds(pts, { padding: [40, 40], maxZoom: 14 });
                else m.setView(pts[0] || [26.0667, 50.5577], pts.length ? 13 : 10);
                this._map = m;
                setTimeout(() => m.invalidateSize(), 80);
            }).catch(() => { this.map = false; window.showToast && window.showToast('error', tr('map')); });
        },
        /* What the open tab lists, as pins: key · coordinates · the label on the pin. */
        mapItems() {
            if (this.tab === 'all') return [
                ...this.clubs.map(c => ({ key: c.key, lat: c.lat, lng: c.lng, pin: c.name, icon: 'bi-building', url: c.url })),
                ...this.evs.map(e => ({ key: e.key, lat: e.lat, lng: e.lng, pin: e.club || e.title, icon: 'bi-calendar-event', url: (e.cover_url || e.url) + '#cover' })),
                ...this.coaches.map(c => ({ key: c.key, lat: c.lat, lng: c.lng, pin: c.club || c.name, icon: 'bi-person-badge', url: c.url })),
            ];
            if (this.tab === 'shops') return this.products.map(p => ({ key: p.key, lat: p.lat, lng: p.lng, pin: p.club, url: p.url }));
            if (this.tab === 'events') return this.evs.map(e => ({ key: e.key, lat: e.lat, lng: e.lng, pin: e.club || e.title }));
            if (this.tab === 'coaches') return this.coaches.map(c => ({ key: c.key, lat: c.lat, lng: c.lng, pin: c.club || c.name }));
            return this.clubs.map(c => ({ key: c.key, lat: c.lat, lng: c.lng, pin: c.name }));
        },
        syncPins() { document.querySelectorAll('.t1-pin').forEach(p => p.classList.toggle('hl', p.dataset.key === this.hl)); },
    };
};
</script>
