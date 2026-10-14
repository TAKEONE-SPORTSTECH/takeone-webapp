// The Statistics page's data layer, from the federation's own records.
//
// NOT a standalone script: FederationApp splices these three sections into
// the class body of "Karate Statistics.dc.html" at serve time (the prototype
// file itself stays byte-identical). Each section replaces the method span
// named in FederationApp::statisticsRewrites().
//
//   data     — replaces data() + factors(): load the records, filter them.
//   map      — replaces initMap() + updateGeo(): circles follow the records.
//   render   — replaces renderVals(): every panel counted from the records.
//
// The records come from GET /federation/api/statistics (FederationStatistics).
// Nothing here estimates: a figure nobody records is "—", and the panel says so.

//@@ data
  MON = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  AGES = ['Under 6 Years', 'Under 8 Years', 'Under 10 Years', 'Under 12 Years', 'Under 14 Years', 'Cadets', 'Juniors', 'Seniors', 'Veterans 1', 'Veterans 2', 'Veterans 3', 'Veterans 4', 'Veterans 5'];
  initials(n) { return String(n || '').split(/\s+/).filter(w => w && w[0] === w[0].toUpperCase()).map(w => w[0]).slice(0, 2).join('') || String(n || '?').slice(0, 1).toUpperCase(); }
  shift(iso, days) { const d = new Date(iso + 'T00:00:00Z'); d.setUTCDate(d.getUTCDate() + days); return d.toISOString().slice(0, 10); }
  dayLabel(iso) { return iso ? (+iso.slice(8, 10)) + ' ' + this.MON[+iso.slice(5, 7) - 1] : 'Date TBC'; }
  wLabel(div) { const m = String(div || '').match(/([-+]\d+(?:\.\d+)?\s*kg)/i); return m ? m[1].replace(/\s+/, ' ') : String(div || ''); }
  divDisc(div) { return /^kata/i.test(String(div || '')) ? 'Kata' : 'Kumite'; }
  pctChange(cur, prev) { return prev > 0 ? (cur - prev) / prev * 100 : null; }
  // The figures are for the body picked in the dashboard's switcher — that
  // body and everything under it. The server honours it only inside the
  // viewer's own tree; the dashboard's placeholder id is simply ignored.
  statsUrl() {
    const base = ((window.__TAKEONE_FED || {}).urls || {}).statistics;
    if (!base) return null;
    let id = null;
    try { id = JSON.parse(localStorage.getItem('fed-org-cur') || 'null'); } catch (e) { id = null; }
    return typeof id === 'string' && /^[0-9a-f-]{36}$/i.test(id) ? base + (base.indexOf('?') >= 0 ? '&' : '?') + 'body=' + encodeURIComponent(id) : base;
  }
  load(manual) {
    const url = this.statsUrl();
    if (!url) { this.setState({loadErr: true, errCleared: false}); return; }
    this.setState({fetching: true});
    fetch(url, {credentials: 'same-origin', headers: {Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest'}})
      .then(r => r.ok ? r.json() : Promise.reject(r.status))
      .then(j => { if (!j || !j.stats) throw new Error('empty');
        this.setState({stats: j.stats, fetching: false, loadErr: false, errCleared: false, updated: j.stats.generatedAt || Date.now()});
        if (manual) this.toast('Statistics refreshed'); })
      .catch(() => this.setState({fetching: false, loadErr: true, errCleared: false}));
  }
  // Live: after a FederationPulse the figures are re-read in the background —
  // no loading state, no toast; the page changes when the new numbers land.
  loadQuiet() {
    const url = this.statsUrl();
    if (!url || this.state.fetching) return;
    fetch(url, {credentials: 'same-origin', headers: {Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest'}})
      .then(r => r.ok ? r.json() : null)
      .then(j => { if (j && j.stats) { this._d = null; this.setState({stats: j.stats, loadErr: false, updated: j.stats.generatedAt || Date.now()}); } })
      .catch(() => {});
  }
  liveOn() {
    this.liveOff();
    this._live = e => { if (e.detail && e.detail.action === 'refresh') { clearTimeout(this._liveT); this._liveT = setTimeout(() => this.loadQuiet(), 400); } };
    this._vis = () => { if (document.visibilityState === 'visible') this.loadQuiet(); };
    window.addEventListener('realtime:federation', this._live);
    document.addEventListener('visibilitychange', this._vis);
    this._org = () => { this._d = null; this.load(); };
    window.addEventListener('fed-org-changed', this._org);
  }
  liveOff() {
    clearTimeout(this._liveT);
    if (this._live) window.removeEventListener('realtime:federation', this._live);
    if (this._vis) document.removeEventListener('visibilitychange', this._vis);
    if (this._org) window.removeEventListener('fed-org-changed', this._org);
  }
  data() {
    const S = this.state.stats;
    if (this._d && this._dSrc === S) return this._d;
    const R = Object.assign({today: new Date().toISOString().slice(0, 10), federation: '', bodies: [], clubs: [], athletes: [], events: [], entries: [],
      divisions: [], bouts: [], medals: [], officials: [], alerts: []}, S || {});
    const bodyName = {}; R.bodies.forEach(b => { bodyName[b.id] = b.name; });
    const clubs = R.clubs.map((c, i) => ({...c, i, region: bodyName[c.body] || '—', initials: this.initials(c.name)}));
    const seen = {}; R.events.forEach(e => { seen[e.name] = (seen[e.name] || 0) + 1; });
    const events = R.events.map((e, i) => ({...e, i, year: e.start ? +e.start.slice(0, 4) : null,
      label: seen[e.name] > 1 ? e.name + ' · ' + this.dayLabel(e.start) : e.name}));
    const athletes = R.athletes.map((a, i) => ({...a, i}));
    const entryClub = {}; R.entries.forEach(x => { if (x.c != null) entryClub[x.e + ':' + x.a] = x.c; });
    const years = new Set([+R.today.slice(0, 4)]);
    events.forEach(e => { if (e.year) years.add(e.year); });
    athletes.forEach(a => { if (a.reg) years.add(+a.reg.slice(0, 4)); });
    const ages = new Set(); athletes.forEach(a => a.age && ages.add(a.age)); R.entries.forEach(x => x.age && ages.add(x.age));
    const discs = new Set(); R.entries.forEach(x => (x.disc || []).forEach(d => discs.add(d)));
    const weights = [...new Set(R.divisions.map(d => this.wLabel(d.div)).filter(Boolean))]
      .sort((a, b) => (a[0] === '+') - (b[0] === '+') || parseFloat(a.slice(1)) - parseFloat(b.slice(1)));
    const sports = [...new Set(events.map(e => e.sport).filter(Boolean))];
    this._d = {...R, bodyName, clubs, events, athletes, entryClub, weights, sports,
      seasons: [...years].sort((a, b) => b - a).map(String),
      ages: this.AGES.filter(a => ages.has(a)),
      discs: ['Kata', 'Kumite', 'Other'].filter(d => discs.has(d)),
      regions: [...new Set(clubs.map(c => c.region))].filter(r => r !== '—').sort()};
    this._dSrc = S;
    return this._d;
  }
  // Every filter, applied to the records. `year` overrides the season (the
  // previous-season comparisons); `noWindow` ignores the date range and the
  // competition (a whole season).
  factors(opt) {
    opt = opt || {};
    const f = this.fv(), D = this.data(), today = D.today;
    const Y = opt.year || +f.season, yStart = Y + '-01-01', yEnd = Y + '-12-31';
    const days = opt.noWindow ? null : {'Last 90 days': 90, 'Last 30 days': 30}[f.range];
    const from = days ? this.shift(today, -days) : yStart, to = days ? today : yEnd;
    const comp = opt.noWindow || f.comp === 'All competitions' ? null : (D.events.find(e => e.label === f.comp) || null);
    const club = f.club === 'All clubs' ? null : (D.clubs.find(c => c.name === f.club) || null);
    const region = f.region === 'All regions' ? null : f.region;
    const curYear = +today.slice(0, 4);
    const evOk = e => e.start ? (e.start >= from && e.start <= to) : (!days && Y === curYear);
    const evSet = new Set(D.events.filter(e => comp ? e === comp : evOk(e)).map(e => e.i));
    const clubOk = ci => (!club || ci === club.i) && (!region || (ci != null && D.clubs[ci] && D.clubs[ci].region === region));
    const gOk = g => f.gender === 'All' || g === (f.gender === 'Male' ? 'M' : 'F');
    const ageOk = a => f.age === 'All' || a === f.age;
    let wSet = null;
    if (f.weight !== 'All') { wSet = new Set(); D.divisions.forEach(d => { if (this.wLabel(d.div) === f.weight) d.athletes.forEach(a => wSet.add(d.e + ':' + a)); }); }
    const discOk = x => f.disc === 'All' || (x.disc || []).includes(f.disc);
    const entOk = x => evSet.has(x.e) && discOk(x) && gOk((D.athletes[x.a] || {}).g) && ageOk(x.age) && clubOk(x.c) && (!wSet || wSet.has(x.e + ':' + x.a));
    const entries = D.entries.filter(x => x.st === 'Active' && entOk(x));
    const withdrawn = D.entries.filter(x => x.st !== 'Active' && entOk(x));
    const divOk = d => evSet.has(d.e) && (f.disc === 'All' || f.disc === this.divDisc(d.div)) && gOk(d.g) && ageOk(d.age) && (f.weight === 'All' || this.wLabel(d.div) === f.weight);
    const ec = (e, a) => D.entryClub[e + ':' + a];
    const anyClub = !club && !region;
    const divisions = D.divisions.filter(divOk);
    const bouts = D.bouts.filter(b => divOk(b) && (anyClub || clubOk(ec(b.e, b.w)) || clubOk(ec(b.e, b.l))));
    const medals = D.medals.filter(m => divOk(m) && (anyClub || clubOk(ec(m.e, m.a))));
    const officials = D.officials.filter(o => evSet.has(o.e) && (anyClub || clubOk(o.c)));
    // The athlete population: a discipline or weight filter narrows it to the
    // athletes who have ever entered that discipline or division.
    let pool = null;
    if (f.disc !== 'All' || wSet) { pool = new Set(); D.entries.forEach(x => { if (discOk(x) && (!wSet || wSet.has(x.e + ':' + x.a))) pool.add(x.a); }); }
    const registered = D.athletes.filter(a => (!a.reg || a.reg <= to) && gOk(a.g) && ageOk(a.age) && clubOk(a.club) && (!pool || pool.has(a.i)));
    const active = new Set(entries.map(x => x.a));
    // Whoever entered is registered, whatever their age today or current club.
    const regIds = new Set(registered.map(a => a.i));
    active.forEach(i => { if (!regIds.has(i) && D.athletes[i]) registered.push(D.athletes[i]); });
    let emptyWhy = '', emptyTitle = 'No statistics match these filters';
    if (this.state.stats && !D.events.length && !D.athletes.length && !D.clubs.length) {
      emptyTitle = 'Nothing recorded yet';
      emptyWhy = 'Figures appear here as clubs affiliate, enter athletes in your events and results are recorded in the draw.';
    } else if (this.state.stats && !entries.length && !registered.length && !bouts.length && !opt.year) {
      emptyWhy = f.disc === 'Kata' && f.weight !== 'All' ? 'Kata has no weight categories. Clear the weight filter or switch discipline to Kumite.'
        : comp ? comp.name + ' has no entries yet.' : 'No records match the current filter combination.';
    }
    return {Y, yStart, yEnd, from, to, days, comp, club, region, evSet, clubOk, gOk, ageOk, wSet, pool, ec,
      entries, withdrawn, divisions, bouts, medals, officials, registered, active,
      empty: !!emptyWhy, emptyWhy, emptyTitle};
  }
//@@ map
  initMap(el) {
    if (!el || el._leaflet_id) return;
    // Leaflet's panes and controls carry z-indexes of 400-1000. Without a
    // stacking context of its own the map paints over the dashboard's sticky
    // top bar (z-index 45) while the page scrolls; this keeps them inside.
    el.style.position = 'relative';
    el.style.zIndex = '0';
    el.style.isolation = 'isolate';
    const go = () => {
      if (typeof L === 'undefined') return setTimeout(go, 250);
      if (el._leaflet_id) return;
      const home = this.mapHome();
      const m = L.map(el, {zoomControl: false, scrollWheelZoom: false}).setView([home[0], home[1]], home[2]);
      // OSM's tile policy refuses a request with no Referer, and these pages send
      // `Referrer-Policy: same-origin` — so the layer names its own: the origin only.
      L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors', referrerPolicy: 'strict-origin-when-cross-origin', maxZoom: 19}).addTo(m);
      L.control.zoom({position: 'bottomright'}).addTo(m);
      this._map = m; this._geo = {}; this._venues = {}; this._fitted = false;
      setTimeout(() => m.invalidateSize(), 300);
      this.updateGeo();
    };
    go();
  }
  // Until there is something to fit, the map opens on the federation's own
  // country — not the whole world, which read as a broken map.
  mapHome() {
    return {BH: [26.07, 50.55, 10], SA: [24.2, 45.1, 5], AE: [24.3, 54.4, 7], KW: [29.3, 47.7, 8], QA: [25.3, 51.2, 9], OM: [21.0, 57.0, 6], JO: [31.2, 36.5, 7], EG: [26.8, 30.8, 5]}[String((this.state.stats || {}).country || '').toUpperCase()] || [20, 10, 2];
  }
  updateGeo() {
    if (!this._map || !this._geoRows) return;
    const m = this._map, pts = [];
    const names = new Set(this._geoRows.map(r => r.name)), keys = new Set((this._venueRows || []).map(v => String(v.key)));
    Object.keys(this._geo).forEach(k => { if (!names.has(k)) { m.removeLayer(this._geo[k]); delete this._geo[k]; } });
    Object.keys(this._venues).forEach(k => { if (!keys.has(k)) { m.removeLayer(this._venues[k]); delete this._venues[k]; } });
    this._geoRows.forEach(r => {
      if (r.lat == null || r.lng == null) return;
      pts.push([r.lat, r.lng]);
      let c = this._geo[r.name];
      if (!c) {
        c = L.circleMarker([r.lat, r.lng], {radius: 12, color: '#d8b25f', weight: 1.5, fillColor: '#d8b25f', fillOpacity: .32}).addTo(m);
        c.bindTooltip(r.name, {direction: 'top'}); c.on('click', () => this.setState({geoSel: r.name}));
        this._geo[r.name] = c;
      }
      const sel = this.state.geoSel === r.name;
      c.setRadius(r.radius); c.setStyle({fillOpacity: sel ? .62 : .3, weight: sel ? 3 : 1.5, color: sel ? '#edcd85' : '#d8b25f'});
      c.setTooltipContent(r.name + ' · ' + r.metricLabel);
    });
    (this._venueRows || []).forEach(v => {
      if (this._venues[v.key]) return;
      pts.push([v.lat, v.lng]);
      this._venues[v.key] = L.circleMarker([v.lat, v.lng], {radius: 5, color: '#5ec8e5', weight: 2, fillColor: '#ffffff', fillOpacity: 1}).addTo(m).bindTooltip('Venue · ' + v.name, {direction: 'top'});
    });
    if (!this._fitted && pts.length) { this._fitted = true; m.fitBounds(pts, {padding: [40, 40], maxZoom: 12}); }
    else if (!pts.length && !this._homed && this.state.stats) { this._homed = true; const h = this.mapHome(); m.setView([h[0], h[1]], h[2]); }
  }
//@@ render
  renderVals() {
    const s = this.state, f = this.fv(), D = this.data(), F = this.factors();
    const loading = s.loading || !!s.fetching || (!s.stats && !s.loadErr);
    const E = F.empty, mon = this.MON, today = D.today, curYear = +today.slice(0, 4);
    const v = {};
    const fmt = n => this.fmt(n || 0);
    const pct = (a, b) => b ? (a / b * 100) : null;
    const delta = (ch, what) => ch == null ? 'No previous season to compare' : (ch >= 0 ? '▲ +' : '▼ ') + ch.toFixed(1) + '% ' + what;
    const dStyle = ch => this.css('font-size:.74rem;font-weight:600;margin-top:4px;color:' + (ch == null ? '#6b7689' : ch >= 0 ? '#2f7a4f' : '#b4483c'));
    const evById = D.events;
    const P = this.factors({year: F.Y - 1, noWindow: true});
    // ---------- filters
    const defs = [['season', 'Season', D.seasons], ['range', 'Date range', ['Full season', 'Last 90 days', 'Last 30 days']],
      ['comp', 'Competition', ['All competitions', ...D.events.filter(e => e.year === +f.season || (!e.year && +f.season === curYear)).map(e => e.label)]],
      ['disc', 'Discipline', ['All', ...D.discs]], ['gender', 'Gender', ['All', 'Male', 'Female']],
      ['age', 'Age category', ['All', ...D.ages]], ['weight', 'Weight', ['All', ...D.weights]],
      ['club', 'Club', ['All clubs', ...D.clubs.map(c => c.name).sort()]], ['region', 'Region', ['All regions', ...D.regions]]];
    const activeN = defs.filter(([k]) => k !== 'season' && f[k] !== this.DEF[k]).length;
    v.filters = defs.map(([k, label, opts]) => { const on = k !== 'season' && f[k] !== this.DEF[k], open = s.fOpen === k;
      return {label, value: f[k], open,
        btnStyle: this.css('display:flex;flex-direction:column;align-items:flex-start;justify-content:center;gap:1px;height:44px;padding:0 30px 0 12px;position:relative;border-radius:11px;font-family:inherit;cursor:pointer;text-align:left;max-width:230px;box-sizing:border-box;width:100%;' + (on ? 'background:#fdf6e6;border:1px solid #caa14f;color:#1e2c4f' : 'background:#fff;border:1px solid rgba(30,44,79,0.115);color:#1e2c4f')),
        onToggle: e => { e.stopPropagation(); this.setState({fOpen: open ? null : k}); },
        opts: opts.map(o => ({label: o, on: o === f[k], style: this.css('display:flex;align-items:center;gap:10px;padding:9px 12px;border-radius:9px;font-size:.8rem;cursor:pointer;' + (o === f[k] ? 'color:#8a6d20;font-weight:600;background:rgba(216,178,95,.1)' : 'color:#2c3a5e')),
          onPick: e => { e.stopPropagation(); this.setFilter(k, o); }}))};
    });
    v.hasActive = activeN > 0; v.activeCount = activeN + ' active';
    v.activeLabel = activeN ? activeN + ' filter' + (activeN > 1 ? 's' : '') + ' active' : 'All data';
    v.resetFilters = () => { this.setState({f: {...this.DEF, season: f.season}, fOpen: null, fDrawer: false}); this.pulse(); };
    v.season = f.season;
    v.fedName = D.federation || 'Federation';
    v.pageTitle = (D.sports.length === 1 ? D.sports[0] + ' ' : '') + 'Federation Statistics';
    // ---------- KPIs
    const ath = F.active.size, reg = F.registered.length;
    const athCh = this.pctChange(ath, P.active.size);
    const affiliated = D.clubs.filter(c => c.affiliated && F.clubOk(c.i));
    const newClubs = affiliated.filter(c => c.joined && c.joined.slice(0, 4) === f.season).length;
    const seasonEvents = D.events.filter(e => F.evSet.has(e.i));
    const stN = st => seasonEvents.filter(e => e.status === st).length;
    const upcoming = stN('Planning') + stN('Registration open');
    const gM = F.medals.filter(m => m.place === 1).length, sM = F.medals.filter(m => m.place === 2).length, bM = F.medals.filter(m => m.place === 3).length;
    const medals = gM + sM + bM;
    const offKey = o => o.role + '|' + o.name.toLowerCase();
    const refs = new Set(F.officials.filter(o => o.role === 'Referee').map(offKey)), mgrs = new Set(F.officials.filter(o => o.role !== 'Referee').map(offKey));
    const part = pct(ath, reg), partPrev = pct(P.active.size, P.registered.length);
    const partCh = part != null && partPrev != null ? part - partPrev : null;
    // Sparkline: athletes entered so far this season, month by month.
    const lastM = F.Y === curYear ? +today.slice(5, 7) : 12;
    const firstEntry = {};
    F.entries.forEach(x => { const e = evById[x.e]; const mo = e && e.start ? +e.start.slice(5, 7) : 1; if (!firstEntry[x.a] || mo < firstEntry[x.a]) firstEntry[x.a] = mo; });
    const sp = Array.from({length: Math.max(lastM, 2)}, (_, i) => Object.values(firstEntry).filter(mo => mo <= i + 1).length);
    const spMax = Math.max(1, ...sp);
    const spPts = sp.map((x, i) => [(i / (sp.length - 1) * 160).toFixed(1), (46 - x / spMax * 42).toFixed(1)]);
    const spLine = spPts.map((p, i) => (i ? 'L' : 'M') + p[0] + ',' + p[1]).join(' ');
    const tip = k => ({on: () => this.setState({kpiTip: k}), off: () => this.setState({kpiTip: null})});
    const C17 = 2 * Math.PI * 17;
    const stCol = {Completed: '#4fbf8a', Live: '#d8b25f', 'Registration open': '#5ec8e5', Planning: '#f0a94b'};
    v.k = {
      ath: fmt(ath), athDelta: delta(athCh, 'from previous season'), athDeltaStyle: dStyle(athCh),
      athLine: spLine, athArea: spLine + ' L160,48 L0,48 Z', registered: fmt(reg),
      clubs: fmt(affiliated.length), clubsSub: F.club ? F.club.region : newClubs ? '+' + newClubs + ' new club' + (newClubs > 1 ? 's' : '') + ' this season' : 'No new clubs this season',
      comps: fmt(seasonEvents.length), compsSub: F.comp ? F.comp.status : upcoming + ' upcoming · ' + stN('Completed') + ' completed',
      compDots: seasonEvents.map(e => ({style: this.css('width:8px;height:8px;border-radius:2px;background:' + (stCol[e.status] || '#97a1b4') + ';opacity:' + (F.comp && F.comp !== e ? '.25' : '1'))})),
      medals: fmt(medals), gold: fmt(gM), silver: fmt(sM), bronze: fmt(bM),
      gW: this.css('background:#e3bd5c;height:100%;width:' + (medals ? gM / medals * 100 : 0) + '%'), sW: this.css('background:#c3cad6;height:100%;width:' + (medals ? sM / medals * 100 : 0) + '%'), bW: this.css('background:#c9895b;height:100%;width:' + (medals ? bM / medals * 100 : 0) + '%'),
      officials: fmt(refs.size + mgrs.size), offSub: refs.size + ' referee' + (refs.size === 1 ? '' : 's') + ' · ' + mgrs.size + ' manager' + (mgrs.size === 1 ? '' : 's'),
      part: part == null ? '—' : part.toFixed(1) + '%', partDelta: partCh == null ? 'No previous season to compare' : (partCh >= 0 ? '▲ +' : '▼ ') + partCh.toFixed(1) + ' pts from previous season', partDeltaStyle: dStyle(partCh),
      ringStyle: this.css('fill:none;stroke:#d8b25f;stroke-width:5;stroke-linecap:round;transform:rotate(-90deg);transform-origin:20px 20px;stroke-dasharray:' + ((part || 0) / 100 * C17).toFixed(1) + ' ' + C17.toFixed(1)),
      list: [['Active athletes', ath], ['Registered athletes', reg], ['Affiliated clubs', affiliated.length], ['Competitions this season', seasonEvents.length], ['Medals awarded', medals], ['Officials named', refs.size + mgrs.size], ['Participation rate', part == null ? '—' : part.toFixed(1) + '%']]
    };
    v.tip = {ath: s.kpiTip === 'ath', clubs: s.kpiTip === 'clubs', comps: s.kpiTip === 'comps', medals: s.kpiTip === 'medals', off: s.kpiTip === 'off', part: s.kpiTip === 'part'};
    v.tipOn = {ath: tip('ath').on, clubs: tip('clubs').on, comps: tip('comps').on, medals: tip('medals').on, off: tip('off').on, part: tip('part').on};
    v.tipOff = () => this.setState({kpiTip: null});
    v.kv = this.css('font-size:clamp(1.55rem,2.3vw,2.05rem);font-weight:700;letter-spacing:-.02em;line-height:1.1;font-variant-numeric:tabular-nums;' + (loading ? 'color:transparent;background:linear-gradient(90deg,rgba(30,44,79,0.069),rgba(30,44,79,0.184),rgba(30,44,79,0.069));background-size:200px 100%;animation:shimmer 1.1s linear infinite;border-radius:8px;min-width:90px;display:inline-block' : ''));
    v.kpiGridStyle = this.css(s.isMobile ? 'display:grid;grid-auto-flow:column;grid-auto-columns:78%;gap:12px;overflow-x:auto;scroll-snap-type:x mandatory;padding-bottom:6px;margin-top:18px' : 'display:grid;grid-template-columns:repeat(' + (window.innerWidth >= 1200 ? 4 : 2) + ',minmax(0,1fr));gap:14px;margin-top:18px');
    // Seven cards, two of them double: eight cells, so four columns or two
    // always fill evenly (auto-fit left a ragged second row).
    v.kpiPartStyle = this.css('position:relative;background:#fff;border:1px solid rgba(30,44,79,0.081);border-radius:18px;padding:18px;cursor:pointer;transition:transform .18s ease,border-color .18s ease;scroll-snap-align:start;min-width:0' + (s.isMobile ? '' : ';grid-column:span 2'));
    v.kpiWideStyle = this.css('position:relative;background:linear-gradient(145deg,#f1f9fc,#fff);border:1px solid rgba(94,200,229,.22);border-radius:18px;padding:18px;cursor:pointer;transition:transform .18s ease,border-color .18s ease;scroll-snap-align:start;min-width:0;' + (s.isMobile ? '' : 'grid-column:span 2'));
    ['Athletes', 'Clubs', 'Comps', 'Medals', 'Officials'].forEach(n => { v['go' + n] = () => this.scrollTo({Athletes: 'growth', Clubs: 'clubs', Comps: 'competitions', Medals: 'medals', Officials: 'officials'}[n]); });
    v.vb = this._vb;
    // ---------- growth chart
    // Registered = athlete profiles by then; active = entered a competition of
    // that season by then; participants = entered one that had started by then;
    // new = profiles created in the period.
    const gr = s.gRange;
    const ymEnd = (y, m) => { const d = new Date(Date.UTC(y, m, 0)); return d.toISOString().slice(0, 10); };
    let buckets = [];
    if (gr === '30 days') { for (let i = 9; i >= 0; i--) { const e = this.shift(today, -3 * i); buckets.push({from: this.shift(e, -2), to: e, label: this.dayLabel(e)}); } }
    else if (gr === '6 months') { const y = curYear, m = +today.slice(5, 7); for (let i = 5; i >= 0; i--) { const d = new Date(Date.UTC(y, m - 1 - i, 1)); const yy = d.getUTCFullYear(), mm = d.getUTCMonth() + 1; buckets.push({from: yy + '-' + String(mm).padStart(2, '0') + '-01', to: ymEnd(yy, mm), label: mon[mm - 1]}); } }
    else if (gr === 'Current season' || gr === 'Previous season') { const y = gr === 'Current season' ? F.Y : F.Y - 1, last = y === curYear ? +today.slice(5, 7) : 12;
      for (let mm = 1; mm <= last; mm++) buckets.push({from: y + '-' + String(mm).padStart(2, '0') + '-01', to: ymEnd(y, mm), label: mon[mm - 1]}); }
    else { const q = Math.floor((+today.slice(5, 7) - 1) / 3); for (let i = 11; i >= 0; i--) { const qq = q - i, yy = curYear + Math.floor(qq / 4), qn = ((qq % 4) + 4) % 4;
      buckets.push({from: yy + '-' + String(qn * 3 + 1).padStart(2, '0') + '-01', to: ymEnd(yy, qn * 3 + 3), label: 'Q' + (qn + 1) + (qn === 0 || i === 11 ? " '" + String(yy).slice(2) : '')}); } }
    if (buckets.length < 2) buckets = [{...buckets[0], label: buckets[0].label}, buckets[0]];
    const personOk = a => F.gOk(a.g) && F.ageOk(a.age) && F.clubOk(a.club) && (!F.pool || F.pool.has(a.i));
    const people = D.athletes.filter(personOk);
    const allEnt = D.entries.filter(x => x.st === 'Active' && (f.disc === 'All' || (x.disc || []).includes(f.disc)) && F.gOk((D.athletes[x.a] || {}).g) && F.ageOk(x.age) && F.clubOk(x.c) && (!F.wSet || F.wSet.has(x.e + ':' + x.a)));
    const S = {reg: [], act: [], part: [], nw: []};
    buckets.forEach(b => {
      const y0 = b.to.slice(0, 4) + '-01-01';
      S.reg.push(people.filter(a => a.reg && a.reg <= b.to).length);
      S.nw.push(people.filter(a => a.reg && a.reg >= b.from && a.reg <= b.to).length);
      const act = new Set(), prt = new Set();
      allEnt.forEach(x => { const e = evById[x.e]; if (!e || !e.start || e.start < y0 || e.start.slice(0, 4) !== b.to.slice(0, 4)) return;
        if (!x.at || x.at <= b.to) act.add(x.a); if (e.start <= b.to) prt.add(x.a); });
      S.act.push(act.size); S.part.push(prt.size);
    });
    const labels = buckets.map(b => b.label), n = labels.length;
    const maxL = this.nice(Math.max(1, ...S.reg, ...S.act)), maxN = this.nice(Math.max(1, ...S.nw));
    const gx = i => 20 + 960 * (n > 1 ? i / (n - 1) : .5), gy = x => 700 - x / maxL * 670;
    const line = arr => arr.map((x, i) => (i ? 'L' : 'M') + gx(i).toFixed(1) + ',' + gy(x).toFixed(1)).join(' ');
    const area = arr => line(arr) + ' L' + gx(n - 1).toFixed(1) + ',700 L' + gx(0).toFixed(1) + ',700 Z';
    const hi = s.gHover == null || s.gHover >= n ? n - 1 : s.gHover;
    const bw = 960 / n * .42;
    const hide = s.gHide;
    const ser = [['reg', 'Registered athletes', '#5ec8e5'], ['act', 'Active athletes', '#d8b25f'], ['part', 'Competition participants', '#4fbf8a'], ['nw', 'New registrations', '#9d8cf0']];
    const entCh = this.pctChange(F.entries.length, P.entries.length);
    v.g = {
      ranges: ['30 days', '6 months', 'Current season', 'Previous season', '3 years'].map(r => ({label: r, style: this.segBtn(r === gr), onClick: () => this.setState({gRange: r, gHover: null})})),
      legend: ser.map(([k, label, col]) => ({label, onClick: () => this.setState({gHide: {...hide, [k]: !hide[k]}}),
        style: this.css('display:flex;align-items:center;gap:7px;font-size:.74rem;cursor:pointer;background:none;border:0;font-family:inherit;padding:2px 0;color:' + (hide[k] ? '#9aa3b5' : '#2c3a5e') + ';text-decoration:' + (hide[k] ? 'line-through' : 'none')),
        sw: this.css('width:12px;height:' + (k === 'nw' ? '10px' : '3px') + ';border-radius:2px;background:' + col + ';opacity:' + (hide[k] ? '.3' : '1'))})),
      grid: [0, .25, .5, .75, 1].map(q => 'M0,' + (700 - q * 670).toFixed(0) + ' H1000').join(' '),
      yl: [0, .25, .5, .75, 1].map(q => ({label: this.fmtK(q * maxL), style: this.css('position:absolute;right:calc(100% + 8px);top:' + ((700 - q * 670) / 10) + '%;transform:translateY(-50%);font-size:.66rem;color:#6b7689;font-variant-numeric:tabular-nums;white-space:nowrap')})),
      xl: labels.map((l, i) => ({label: l, style: this.css('position:absolute;top:calc(100% + 8px);left:' + (gx(i) / 10) + '%;transform:translateX(-50%);font-size:.66rem;white-space:nowrap;color:' + (i === hi ? '#1e2c4f' : '#6b7689') + ';display:' + (n > 9 && i % 2 && i !== hi ? 'none' : 'block'))})),
      regLine: line(S.reg), regArea: area(S.reg), actLine: line(S.act), actArea: area(S.act), partLine: line(S.part), partArea: area(S.part),
      regOp: hide.reg ? '0' : '1', actOp: hide.act ? '0' : '1', partOp: hide.part ? '0' : '1', nwOp: hide.nw ? '0' : '1',
      bars: S.nw.map((x, i) => { const hgt = x / maxN * 170, x0 = gx(i) - bw / 2; return 'M' + x0.toFixed(1) + ',1000 V' + (1000 - hgt).toFixed(1) + ' H' + (x0 + bw).toFixed(1) + ' V1000 Z'; }).join(' '),
      barHi: (() => { const hgt = S.nw[hi] / maxN * 170, x0 = gx(hi) - bw / 2; return 'M' + x0.toFixed(1) + ',1000 V' + (1000 - hgt).toFixed(1) + ' H' + (x0 + bw).toFixed(1) + ' V1000 Z'; })(),
      guide: this.css('position:absolute;top:0;height:70%;width:1px;background:rgba(30,44,79,0.22);left:' + (gx(hi) / 10) + '%'),
      dots: ser.slice(0, 3).filter(([k]) => !hide[k]).map(([k, , col]) => ({style: this.css('position:absolute;width:10px;height:10px;border-radius:50%;background:' + col + ';border:2px solid #fff;transform:translate(-50%,-50%);left:' + (gx(hi) / 10) + '%;top:' + (gy(S[k][hi]) / 10) + '%')})),
      hits: labels.map((_, i) => ({on: () => this.setState({gHover: i})})),
      leave: () => this.setState({gHover: null}),
      tip: {label: labels[hi] + (gr === '3 years' || gr === '30 days' ? '' : ' ' + buckets[hi].to.slice(0, 4)),
        reg: fmt(S.reg[hi]), act: fmt(S.act[hi]), part: fmt(S.part[hi]), nw: fmt(S.nw[hi]),
        style: this.css('position:absolute;top:2%;z-index:3;pointer-events:none;background:#fff;border:1px solid rgba(216,178,95,.45);border-radius:12px;padding:10px 12px;min-width:176px;box-shadow:0 14px 34px rgba(30,44,79,.18);left:' + (gx(hi) / 10) + '%;transform:translateX(' + (hi > n * .62 ? 'calc(-100% - 12px)' : '12px') + ')')},
      insight: !F.entries.length ? 'No competition entries recorded this season yet.' : entCh == null ? F.entries.length + ' competition entries this season; no previous season to compare.'
        : 'Competition entries ' + (entCh >= 0 ? 'increased' : 'fell') + ' by ' + Math.abs(entCh).toFixed(1) + '% compared with the previous season.',
      expanded: s.expand === 'growth', toggleExpand: () => this.setState({expand: s.expand === 'growth' ? null : 'growth'}),
      expandLabel: s.expand === 'growth' ? 'Collapse chart' : 'Expand chart',
      cardStyle: this.css('flex:' + (s.expand === 'growth' ? '1 1 100%' : '2 1 540px') + ';min-width:0;background:#fff;border:1px solid rgba(30,44,79,0.081);border-radius:18px;padding:clamp(16px,2vw,22px);box-sizing:border-box;box-shadow:0 14px 40px rgba(30,44,79,.08)'),
      chartStyle: this.css('position:relative;padding:8px 8px 30px 46px;box-sizing:border-box;height:' + (s.expand === 'growth' ? 'clamp(320px,42vw,480px)' : 'clamp(240px,26vw,320px)'))
    };
    v.lb = {reg: 'Registered', act: 'Active', part: 'Participants', nw: 'New'};
    // ---------- composition
    const count = (list, key) => { const o = {}; list.forEach(x => { const k = key(x); o[k] = (o[k] || 0) + 1; }); return o; };
    const ageC = count(F.registered, a => a.age || 'Not recorded');
    const genC = count(F.registered, a => a.g === 'M' ? 'Male' : a.g === 'F' ? 'Female' : 'Not recorded');
    const discBy = {}; F.entries.forEach(x => { const d = discBy[x.a] || (discBy[x.a] = {}); (x.disc || []).forEach(k => { d[k] = true; }); });
    const discC = count(Object.values(discBy), d => d.Kata && d.Kumite ? 'Kata & Kumite' : d.Kata ? 'Kata only' : d.Kumite ? 'Kumite only' : 'Other');
    const approved = new Set(F.entries.filter(x => x.ok).map(x => x.a));
    const statC = count(F.registered, a => approved.has(a.i) ? 'Entered · approved' : F.active.has(a.i) ? 'Entered · awaiting approval' : 'Not entered this season');
    const order = (o, keys) => keys.filter(k => o[k]).map(k => [k, o[k]]).concat(Object.keys(o).filter(k => !keys.includes(k)).map(k => [k, o[k]]));
    const modes = {
      Age: order(ageC, [...this.AGES, 'Not recorded']).slice(0, 6),
      Gender: order(genC, ['Male', 'Female', 'Not recorded']),
      Discipline: order(discC, ['Kumite only', 'Kata only', 'Kata & Kumite', 'Other']),
      Status: order(statC, ['Entered · approved', 'Entered · awaiting approval', 'Not entered this season'])
    };
    const cm = s.compMode, segsRaw = modes[cm];
    const totBase = segsRaw.reduce((t, [, x]) => t + x, 0);
    const pal = ['#d8b25f', '#5ec8e5', '#4fbf8a', '#9d8cf0', '#f0a94b', '#ef7b8b'];
    const Cd = 2 * Math.PI * 76; let acc = 0;
    const segs = segsRaw.map(([name, x], i) => { const sh = totBase ? x / totBase : 0, len = sh * Cd; const o = {name, val: x, pct: (sh * 100).toFixed(1) + '%', col: pal[i], len, off: acc}; acc += len; return o; });
    const ch = s.compHover != null && s.compHover < segs.length ? s.compHover : null;
    v.cp = {
      modes: Object.keys(modes).map(m => ({label: m, style: this.segBtn(m === cm), onClick: () => this.setState({compMode: m, compHover: null})})),
      circles: Array.from({length: 6}, (_, i) => { const sg = segs[i]; return {style: this.css('fill:none;stroke:' + (sg ? sg.col : 'transparent') + ';stroke-width:' + (ch === i ? 26 : 20) + ';transform:rotate(-90deg);transform-origin:100px 100px;transition:stroke-width .15s ease;stroke-dasharray:' + (sg ? Math.max(0, sg.len - 3).toFixed(1) + ' ' + Cd.toFixed(1) : '0 ' + Cd.toFixed(1)) + ';stroke-dashoffset:' + (sg ? (-sg.off).toFixed(1) : 0) + ';opacity:' + (ch == null || ch === i ? 1 : .35))}; }),
      center: ch == null ? fmt(totBase) : fmt(segs[ch].val), centerLabel: ch == null ? (cm === 'Discipline' ? 'Active athletes' : 'Registered athletes') : segs[ch].name + ' · ' + segs[ch].pct,
      rows: segs.map((sg, i) => ({name: sg.name, val: fmt(sg.val), pct: sg.pct, sw: this.css('width:10px;height:10px;border-radius:3px;flex:none;background:' + sg.col),
        bar: this.css('height:4px;border-radius:2px;background:' + sg.col + ';width:' + sg.pct),
        on: () => this.setState({compHover: i}), off: () => this.setState({compHover: null})})),
      note: cm === 'Discipline' ? 'Athletes entered this season, by the disciplines they entered. Both disciplines are counted once, in “Kata & Kumite”.'
        : cm === 'Status' ? 'Registered athletes, by whether they entered a competition this season.'
        : cm === 'Age' ? 'Registered athletes by age category today' + (Object.keys(ageC).length > 6 ? ' (six largest shown)' : '') + '.' : 'Registered athletes.'
    };
    v.cp.circles.forEach((c, i) => { v.cp['c' + i] = c.style; });
    // ---------- clubs
    const regBy = count(F.registered, a => a.club), entBy = count(F.entries, x => x.c);
    const activeBy = {}; F.entries.forEach(x => { if (x.c == null) return; (activeBy[x.c] || (activeBy[x.c] = new Set())).add(x.a); });
    const winBy = count(F.bouts, b => F.ec(b.e, b.w));
    const boutBy = {}; F.bouts.forEach(b => { [F.ec(b.e, b.w), F.ec(b.e, b.l)].forEach(c => { if (c != null) boutBy[c] = (boutBy[c] || 0) + 1; }); });
    const medBy = {}; F.medals.forEach(m => { const c = F.ec(m.e, m.a); if (c == null) return; const o = medBy[c] || (medBy[c] = {g: 0, s: 0, b: 0}); o[['g', 's', 'b'][m.place - 1]]++; });
    const points = c => { const o = medBy[c] || {g: 0, s: 0, b: 0}; return o.g * 3 + o.s * 2 + o.b; };
    const topPts = Math.max(1, ...D.clubs.map(c => points(c.i)));
    const sortKey = s.clubSort;
    const clubRowsAll = D.clubs.filter(c => F.clubOk(c.i) && (c.affiliated || entBy[c.i] || regBy[c.i])).map(c => { const m = medBy[c.i] || {g: 0, s: 0, b: 0};
      return {...c, athletes: regBy[c.i] || 0, entries: entBy[c.i] || 0, wins: winBy[c.i] || 0, medals: m.g + m.s + m.b, gold: m.g, silverN: m.s, bronzeN: m.b,
        score: points(c.i) / topPts * 100, part: regBy[c.i] ? Math.round(((activeBy[c.i] || new Set()).size) / regBy[c.i] * 100) : 0, bouts: boutBy[c.i] || 0}; })
      .sort((a, b) => (b[sortKey] - a[sortKey]) || (b.score - a.score) || (b.entries - a.entries) || a.name.localeCompare(b.name)).map((c, i) => ({...c, rank: i + 1}));
    const shown = s.clubAll ? clubRowsAll : clubRowsAll.slice(0, 5);
    v.clubRowsAll = clubRowsAll.map(c => ({...c, score: c.score.toFixed(1)}));
    const in90 = this.shift(today, -90);
    const evIn90 = {}; D.entries.forEach(x => { if (x.c == null || !x.at || x.at < in90) return; (evIn90[x.c] || (evIn90[x.c] = new Set())).add(x.e); });
    const evSeason = {}; F.entries.forEach(x => { if (x.c != null) evSeason[x.c] = true; });
    const band = c => { const k = (evIn90[c.i] || new Set()).size; return k >= 3 ? 0 : k >= 1 ? 1 : evSeason[c.i] ? 2 : 3; };
    const bands = [0, 0, 0, 0]; affiliated.forEach(c => { bands[band(c)]++; });
    const bandMax = Math.max(1, ...bands), affN = Math.max(1, affiliated.length);
    v.cl = {
      sorts: [['score', 'Score'], ['medals', 'Medals'], ['gold', 'Gold'], ['wins', 'Wins'], ['athletes', 'Athletes'], ['part', 'Participation']].map(([k, l]) => ({label: l, style: this.segBtn(k === sortKey), onClick: () => this.setState({clubSort: k})})),
      rows: shown.map(c => ({...c, score: c.score.toFixed(1), partTxt: c.part + '%', athletesTxt: fmt(c.athletes), entriesTxt: fmt(c.entries), winsTxt: fmt(c.wins),
        trendTxt: '—', trendStyle: this.css('font-size:.74rem;font-weight:600;font-variant-numeric:tabular-nums;color:#6b7689'),
        rankStyle: this.css('width:26px;height:26px;border-radius:8px;display:grid;place-items:center;font-size:.74rem;font-weight:700;' + (c.rank === 1 && c.score > 0 ? 'background:#e3bd5c;color:#0b132b' : c.rank === 2 && c.score > 0 ? 'background:#c3cad6;color:#0b132b' : c.rank === 3 && c.score > 0 ? 'background:#c9895b;color:#0b132b' : 'background:rgba(30,44,79,0.069);color:#5d6880')),
        scoreBar: this.css('height:6px;border-radius:3px;background:linear-gradient(90deg,#d0a854,#edcd85);width:' + c.score + '%'),
        rowStyle: this.css('display:grid;grid-template-columns:34px minmax(200px,1.7fr) minmax(130px,1fr) 70px 70px 56px 64px 52px minmax(110px,1fr) 56px;gap:10px;align-items:center;padding:10px 8px;border-radius:12px;cursor:pointer;min-width:980px;' + (F.club && F.club.i === c.i ? 'background:rgba(216,178,95,.12);box-shadow:inset 0 0 0 1px rgba(216,178,95,.45)' : '')),
        onOpen: () => this.setState({drawer: {type: 'club', name: c.name}})})),
      hasMore: clubRowsAll.length > 5, moreLabel: s.clubAll ? 'Show top 5' : 'View all clubs (' + clubRowsAll.length + ')',
      toggleAll: () => this.setState({clubAll: !s.clubAll}), none: clubRowsAll.length === 0,
      activity: [['Highly active', '#4fbf8a', 'Entered 3+ events in the last 90 days'], ['Active', '#5ec8e5', 'Entered 1–2 events in the last 90 days'], ['Low activity', '#f0a94b', 'Entered this season, but not in the last 90 days'], ['Inactive', '#ef5b5b', 'No athlete entered this season']]
        .map(([l, col, d], i) => ({label: l, n: String(bands[i]), desc: d, pct: Math.round(bands[i] / affN * 100) + '%', seg: this.css('height:100%;min-width:0;background:' + col + ';flex:' + bands[i] + ' 1 0' + (bands[i] ? '' : ';display:none')), bar: this.css('height:8px;border-radius:4px;background:' + col + ';width:' + (bands[i] / bandMax * 100) + '%'), sw: this.css('width:10px;height:10px;border-radius:3px;flex:none;background:' + col)})),
      activitySub: affiliated.length + ' affiliated club' + (affiliated.length === 1 ? '' : 's') + ' · last 90 days',
      hasInactive: affiliated.filter(c => !(evIn90[c.i] || new Set()).size).length > 0,
      inactiveMsg: (() => { const k = affiliated.filter(c => !(evIn90[c.i] || new Set()).size).length; return k + ' club' + (k === 1 ? ' has' : 's have') + ' not entered an athlete in the last 90 days.'; })()
    };
    // ---------- competitions
    const divByEv = {}; F.divisions.forEach(d => { const o = divByEv[d.e] || (divByEv[d.e] = {t: 0, d: 0}); o.t += d.bouts; o.d += d.done; });
    const entByEv = {}; F.entries.forEach(x => { const o = entByEv[x.e] || (entByEv[x.e] = {a: new Set(), c: new Set()}); o.a.add(x.a); if (x.c != null) o.c.add(x.c); });
    const compRows = seasonEvents.filter(e => entByEv[e.i] || divByEv[e.i] || e.status === 'Completed' || e.status === 'Live');
    const maxA = Math.max(1, ...compRows.map(e => (entByEv[e.i] || {a: new Set()}).a.size)), maxM = Math.max(1, ...compRows.map(e => (divByEv[e.i] || {d: 0}).d));
    const totB = F.divisions.reduce((t, d) => t + d.bouts, 0), doneB = F.divisions.reduce((t, d) => t + d.done, 0);
    const byWithdrawal = F.bouts.filter(b => b.by === 'Withdrawal').length;
    const inBouts = new Set(); F.bouts.forEach(b => { if (b.w != null) inBouts.add(b.w); if (b.l != null) inBouts.add(b.l); });
    const stOrder = ['Completed', 'Live', 'Registration open', 'Planning'];
    const posOf = iso => iso ? ((+iso.slice(5, 7) - 1) + (+iso.slice(8, 10) - 1) / 31) / 12 * 100 : null;
    const compl = F.divisions.length ? doneB / Math.max(1, totB) * 100 : null;
    const wdr = pct(F.withdrawn.length, F.entries.length + F.withdrawn.length);
    const rate = (x, ok) => x == null ? ['—', 'No draw yet', '#6b7689'] : [x.toFixed(1) + '%', ok ? 'On target' : 'Below target', ok ? '#2f7a4f' : '#a8620f'];
    const [cVal, cSt, cCol] = rate(compl, compl >= 95), [wVal, , ] = rate(wdr, wdr < 5);
    v.cm = {
      summary: [...stOrder.map(st => [st, String(stN(st)), stCol[st]]), ['Divisions drawn', String(F.divisions.length), '#d8b25f'], ['Bouts recorded', fmt(doneB), '#9d8cf0']]
        .map(([l, nn, col]) => ({label: l, n: nn, dot: this.css('width:8px;height:8px;border-radius:50%;flex:none;background:' + col)})),
      stack: stOrder.map(st => ({style: this.css('height:100%;min-width:0;background:' + stCol[st] + ';flex:' + stN(st) + ' 1 0' + (stN(st) ? '' : ';display:none'))})),
      stackLabel: seasonEvents.length + ' competitions: ' + stOrder.map(st => stN(st) + ' ' + st.toLowerCase()).join(', '),
      rows: compRows.map(e => { const en = entByEv[e.i] || {a: new Set(), c: new Set()}, dv = divByEv[e.i] || {t: 0, d: 0}; const cp = dv.t ? Math.round(dv.d / dv.t * 100) : null; const sel = F.comp === e;
        return {name: e.name, date: this.dayLabel(e.start) + ' · ' + e.status, clubs: String(en.c.size), athletes: fmt(en.a.size), matches: fmt(dv.d), completion: cp == null ? '—' : cp + '%',
          aBar: this.css('height:7px;border-radius:4px;background:#d8b25f;width:' + (en.a.size / maxA * 100) + '%'), mBar: this.css('height:7px;border-radius:4px;background:#5ec8e5;width:' + (dv.d / maxM * 100) + '%'),
          compStyle: this.css('font-size:.68rem;font-weight:700;padding:3px 8px;border-radius:999px;justify-self:start;' + (cp == null ? 'color:#6b7689;background:rgba(30,44,79,.06)' : cp >= 98 ? 'color:#2f7a4f;background:rgba(79,191,138,.12)' : 'color:#a8620f;background:rgba(240,169,75,.12)')),
          rowStyle: this.css('display:grid;grid-template-columns:minmax(170px,1.3fr) minmax(150px,1.6fr) 48px 64px;gap:12px;align-items:center;padding:10px 8px;border-radius:12px;cursor:pointer;min-width:500px;' + (sel ? 'background:rgba(216,178,95,.12);box-shadow:inset 0 0 0 1px rgba(216,178,95,.45)' : '')),
          onOpen: () => this.setState({drawer: {type: 'comp', name: e.label}})}; }),
      none: compRows.length === 0,
      months: mon.map((m, i) => ({label: m, style: this.css('flex:1;text-align:center;font-size:.64rem;padding-top:6px;border-left:1px solid rgba(30,44,79,0.069);color:' + (F.Y === curYear && i === +today.slice(5, 7) - 1 ? '#8a6d20' : '#6b7689'))})),
      marks: seasonEvents.filter(e => e.start).map((e, i) => ({title: e.name + ' · ' + this.dayLabel(e.start) + ' · ' + e.status,
        style: this.css('position:absolute;width:14px;height:14px;border-radius:50%;transform:translate(-50%,-50%);cursor:pointer;border:2px solid #fff;background:' + (stCol[e.status] || '#97a1b4') + ';left:' + posOf(e.start) + '%;top:' + (i % 2 ? 66 : 34) + '%;box-shadow:0 0 0 ' + (F.comp === e ? '3px rgba(237,205,133,.8)' : '0 transparent')),
        onOpen: () => this.setState({drawer: {type: 'comp', name: e.label}})})),
      todayStyle: this.css('position:absolute;top:0;bottom:0;width:2px;background:#edcd85;left:' + (posOf(today) || 0) + '%;display:' + (F.Y === curYear ? 'block' : 'none')),
      ops: [['Match completion rate', cVal, 'Recorded bouts of those drawn · target ≥ 95%', cSt, cCol],
        ['Withdrawal rate', wVal, 'Entries withdrawn · target < 5%', wdr == null ? 'No entries yet' : wdr < 5 ? 'On target' : 'Above target', wdr == null ? '#6b7689' : wdr < 5 ? '#2f7a4f' : '#a8620f'],
        ['Avg. matches per athlete', inBouts.size ? (F.bouts.length * 2 / inBouts.size).toFixed(1) : '—', 'Of athletes who fought', inBouts.size ? 'From the draw' : 'No bouts yet', '#1f7fa0'],
        ['Walkovers', fmt(byWithdrawal), 'Bouts won by withdrawal', F.bouts.length ? 'From the draw' : 'No bouts yet', '#1f7fa0'],
        ['Protests', '—', 'Not recorded on the platform', 'Not recorded', '#6b7689'],
        ['Avg. event duration', '—', 'Not recorded on the platform', 'Not recorded', '#6b7689']]
        .map(([l, val, sub, st, col]) => ({label: l, value: val, sub, status: st, stStyle: this.css('display:inline-flex;align-items:center;gap:6px;font-size:.66rem;font-weight:600;color:' + col), dot: this.css('width:7px;height:7px;border-radius:50%;background:' + col)}))
    };
    // ---------- disciplines
    const kumOff = f.disc === 'Kata', kataOff = f.disc === 'Kumite' || f.weight !== 'All';
    const kb = F.bouts.filter(b => this.divDisc(b.div) === 'Kumite');
    const byM = count(kb, b => b.by || 'Not stated');
    const methods = ['Points', 'Ippon', 'Decision', 'Disqualification', 'Withdrawal', 'Not stated'].filter(k => byM[k]).map(k => [k, byM[k]]);
    const topMethod = Math.max(1, ...methods.map(m => m[1]));
    const kd = F.divisions.filter(d => this.divDisc(d.div) === 'Kumite');
    const wRows = {}; kb.forEach(b => { const k = (b.g === 'F' ? 'Female ' : 'Male ') + this.wLabel(b.div) + (b.age ? ' · ' + b.age : ''); wRows[k] = (wRows[k] || 0) + 1; });
    const wList = Object.entries(wRows).sort((a, b) => b[1] - a[1]).slice(0, 12), wMax = Math.max(1, ...wList.map(w => w[1]));
    const clubWin = clubRowsAll.filter(c => c.bouts >= 3).map(c => ({a: c.name, r: c.wins / c.bouts})).sort((a, b) => b.r - a.r).slice(0, 3);
    v.ku = {
      off: kumOff, on: !kumOff,
      stats: [['Total matches', fmt(kb.length)], ['Divisions drawn', fmt(kd.length)], ['Wins by ippon', fmt(byM.Ippon)], ['Decisions', fmt(byM.Decision)], ['Disqualifications', fmt(byM.Disqualification)], ['Walkovers', fmt(byM.Withdrawal)], ['Avg. points scored', '—'], ['Avg. match duration', '—']].map(([l, x]) => ({label: l, value: x})),
      charts: [['method', 'Wins by result method'], ['margin', 'Score difference'], ['weight', 'By weight category']].map(([k, l]) => ({label: l, style: this.segBtn(s.kum === k), onClick: () => this.setState({kum: k})})),
      isMethod: s.kum === 'method', isMargin: s.kum === 'margin', isWeight: s.kum === 'weight',
      methods: methods.map(([l, x]) => ({label: l, value: fmt(x), pct: (x / Math.max(1, kb.length) * 100).toFixed(1) + '%', bar: this.css('height:8px;border-radius:4px;background:linear-gradient(90deg,#d0a854,#edcd85);width:' + (x / topMethod * 100) + '%')})),
      margins: [],
      weights: wList.map(([l, m]) => ({label: l, value: fmt(m) + ' matches', margin: fmt(m) + ' bout' + (m === 1 ? '' : 's'), close: false,
        bar: this.css('height:8px;border-radius:4px;width:' + (m / wMax * 100) + '%;background:#5ec8e5'),
        mStyle: this.css('font-size:.7rem;font-variant-numeric:tabular-nums;white-space:nowrap;color:#6b7689')})),
      topClubs: clubWin.length ? clubWin.map(c => ({a: c.a, b: Math.round(c.r * 100) + '% win rate'})) : [{a: 'Clubs appear here after 3 recorded bouts', b: ''}]
    };
    const kataN = F.entries.filter(x => (x.disc || []).includes('Kata')).length;
    v.ka = {
      off: kataOff, on: !kataOff, offMsg: f.disc === 'Kumite' ? 'Kata is excluded by the Kumite discipline filter.' : 'Kata has no weight categories, so it is hidden while a weight filter is set.',
      stats: [['Kata entries', fmt(kataN)], ['Average score', '—'], ['Highest score', '—'], ['Avg. technical score', '—'], ['Avg. athletic score', '—'], ['Judge variation (σ)', '—'], ['Most performed kata', '—'], ['Improvement vs last season', '—']].map(([l, x]) => ({label: l, value: x})),
      charts: [['category', 'Score by category'], ['season', 'Season progression'], ['judges', 'Judge scores']].map(([k, l]) => ({label: l, style: this.segBtn(s.kata === k), onClick: () => this.setState({kata: k})})),
      isCat: s.kata === 'category', isSeason: s.kata === 'season', isJudges: s.kata === 'judges',
      cats: [], line: '', pts: [], judges: [],
      topClubs: [{a: 'Kata scores are not recorded on the platform yet', b: ''}]
    };
    // ---------- medals & rankings
    const medRows = clubRowsAll.filter(c => c.medals > 0).sort((a, b) => (b.gold - a.gold) || (b.medals - a.medals)).slice(0, 8);
    const maxTot = Math.max(1, ...medRows.map(c => c.medals));
    v.md = {rows: medRows.map((c, i) => ({rank: String(i + 1), name: c.name, initials: c.initials, athletes: fmt(c.athletes), gold: String(c.gold), silver: String(c.silverN), bronze: String(c.bronzeN), total: String(c.medals),
      eff: c.athletes ? (c.medals / c.athletes).toFixed(2) : '—',
      g: this.css('height:100%;background:#e3bd5c;width:' + (c.gold / maxTot * 100) + '%'), s: this.css('height:100%;background:#c3cad6;width:' + (c.silverN / maxTot * 100) + '%'), b: this.css('height:100%;background:#c9895b;width:' + (c.bronzeN / maxTot * 100) + '%'),
      onOpen: () => this.setState({drawer: {type: 'club', name: c.name}})})), none: medRows.length === 0};
    v.rk = {tabs: ['Rising', 'Falling', 'New', 'Consistent'].map(t => ({label: t, style: this.segBtn(t === s.rankTab), onClick: () => this.setState({rankTab: t})})), rows: [], none: true};
    v.rkShowHead = !s.isMobile && !v.rk.none;
    // Emerging: athletes whose first medal on record came this season.
    const firstMedalYear = {}; D.medals.forEach(m => { const e = evById[m.e]; const y = e && e.start ? +e.start.slice(0, 4) : curYear; if (!firstMedalYear[m.a] || y < firstMedalYear[m.a]) firstMedalYear[m.a] = y; });
    const newMed = {}; F.medals.forEach(m => { if (firstMedalYear[m.a] !== F.Y) return; if (!newMed[m.a] || m.place < newMed[m.a].place) newMed[m.a] = m; });
    const placeName = ['', 'Gold', 'Silver', 'Bronze'];
    v.em = Object.values(newMed).sort((a, b) => a.place - b.place).slice(0, 4).map(m => { const a = D.athletes[m.a] || {}, c = D.clubs[F.ec(m.e, m.a)];
      return {name: a.name || '—', club: c ? c.name : '—', meta: (m.age || '—') + ' · ' + this.divDisc(m.div), initials: this.initials(a.name),
        txt: 'First medal on record: ' + placeName[m.place].toLowerCase() + ' in ' + (m.g === 'F' ? 'Female ' : 'Male ') + this.wLabel(m.div) + ' at ' + ((evById[m.e] || {}).name || 'an event') + '.',
        mv: placeName[m.place] + ' · new medallist', prog: '', bar: this.css('display:none'),
        onOpen: () => this.setState({drawer: {type: 'ath', idx: m.a}})}; });
    v.emNone = v.em.length === 0;
    // ---------- geography (the federation's bodies)
    const gm = s.geoMetric;
    const bodyOf = ci => ci != null && D.clubs[ci] ? D.clubs[ci].region : null;
    const prevEntBy = count(P.entries, x => bodyOf(x.c));
    const geoRows = D.regions.filter(r => !F.region || r === F.region).map(r => { const cs = D.clubs.filter(c => c.region === r);
      const withXY = cs.filter(c => c.lat != null && c.lng != null);
      const entriesN = F.entries.filter(x => bodyOf(x.c) === r).length;
      return {name: r, lat: withXY.length ? withXY.reduce((t, c) => t + c.lat, 0) / withXY.length : null, lng: withXY.length ? withXY.reduce((t, c) => t + c.lng, 0) / withXY.length : null,
        clubs: cs.filter(c => c.affiliated).length, athletes: F.registered.filter(a => bodyOf(a.club) === r).length, entries: entriesN,
        medals: F.medals.filter(m => bodyOf(F.ec(m.e, m.a)) === r).length, growth: this.pctChange(entriesN, prevEntBy[r] || 0)}; });
    const gMax = Math.max(1, ...geoRows.map(r => r[gm]));
    const gLab = {athletes: 'athletes', clubs: 'clubs', entries: 'competition entries', medals: 'medals'};
    this._geoRows = geoRows.map(r => ({name: r.name, lat: r.lat, lng: r.lng, radius: 7 + 26 * Math.sqrt(r[gm] / gMax), metricLabel: fmt(r[gm]) + ' ' + gLab[gm]}));
    this._venueRows = seasonEvents.filter(e => e.lat != null && e.lng != null).map(e => ({key: e.i, name: e.venue || e.name, lat: e.lat, lng: e.lng}));
    v.geo = {
      metrics: [['athletes', 'Athletes'], ['clubs', 'Clubs'], ['entries', 'Entries'], ['medals', 'Medals']].map(([k, l]) => ({label: l, style: this.segBtn(k === gm), onClick: () => this.setState({geoMetric: k})})),
      mapRef: el => this.initMap(el),
      rows: geoRows.map(r => { const sel = s.geoSel === r.name || f.region === r.name;
        return {name: r.name, clubs: String(r.clubs), athletes: fmt(r.athletes), entries: fmt(r.entries), medals: fmt(r.medals),
          growth: r.growth == null ? '—' : (r.growth >= 0 ? '▲ ' : '▼ ') + Math.abs(r.growth).toFixed(1) + '%', gStyle: this.css('font-size:.74rem;font-weight:600;text-align:right;font-variant-numeric:tabular-nums;color:' + (r.growth == null ? '#6b7689' : r.growth >= 0 ? '#2f7a4f' : '#b4483c')),
          heat: this.css('position:absolute;left:0;top:0;bottom:0;border-radius:10px;background:rgba(216,178,95,' + (.05 + .2 * r[gm] / gMax).toFixed(3) + ');width:' + (r[gm] / gMax * 100) + '%'),
          rowStyle: this.css('position:relative;display:grid;grid-template-columns:minmax(110px,1fr) 44px 60px 54px 52px 54px;gap:8px;align-items:center;padding:10px;border-radius:10px;cursor:pointer;' + (sel ? 'box-shadow:inset 0 0 0 1px rgba(237,205,133,.7)' : '')),
          onPick: () => { this.setState({geoSel: r.name}); if (this._map && r.lat != null) this._map.flyTo([r.lat, r.lng], 11, {duration: .6}); }}; }),
      legendLabel: !geoRows.some(r => r.lat != null)
        ? 'No club has set its location yet — clubs add it in their club details, and the circles appear here.'
        : 'Circle size = ' + gLab[gm] + (geoRows.some(r => r.lat == null) ? ' · bodies without a club location are not drawn' : ''),
      focus: s.geoSel ? s.geoSel : null, none: geoRows.length === 0
    };
    // ---------- by sub-federation: each body directly under the one in view,
    // its own sub-bodies rolled into its row; the body's own records get a row too.
    {
      const focus = D.focus, parentOf = {}, nameOf = {};
      (D.bodies || []).forEach(b => { parentOf[b.id] = b.parent; nameOf[b.id] = b.name; });
      const kids = (D.bodies || []).filter(b => b.parent === focus);
      const branch = id => { let cur = id, g = 0; while (cur != null && parentOf[cur] !== focus && cur !== focus && g++ < 10) cur = parentOf[cur]; return cur; };
      const clubBranch = ci => ci != null && D.clubs[ci] ? branch(D.clubs[ci].body) : null;
      const keys = [...kids.map(b => b.id), focus];
      const tally = {}; keys.forEach(k => { tally[k] = {clubs: 0, athletes: 0, comps: 0, entries: 0, medals: 0}; });
      const add = (k, what) => { if (k != null && tally[k]) tally[k][what]++; };
      D.clubs.forEach(c => { if (c.affiliated && F.clubOk(c.i)) add(branch(c.body), 'clubs'); });
      F.registered.forEach(a => add(clubBranch(a.club), 'athletes'));
      D.events.forEach(e => { if (F.evSet.has(e.i)) add(branch(e.body), 'comps'); });
      F.entries.forEach(x => add(clubBranch(x.c), 'entries'));
      F.medals.forEach(m => add(clubBranch(F.ec(m.e, m.a)), 'medals'));
      const subsOf = id => (D.bodies || []).filter(b => b.parent === id).length;
      const own = tally[focus], ownAny = Object.values(own).some(n => n > 0);
      const rows = kids.map(b => ({id: b.id, name: b.name, note: subsOf(b.id) ? subsOf(b.id) + ' sub-bod' + (subsOf(b.id) === 1 ? 'y' : 'ies') + ' included' : '', t: tally[b.id]}));
      if (ownAny) rows.push({id: focus, name: nameOf[focus] || D.federation, note: 'Its own records', t: own});
      const max = Math.max(1, ...rows.map(r => r.t.entries));
      v.sub = {show: kids.length > 0, title: 'By sub-federation',
        intro: 'Everything under ' + (nameOf[focus] || D.federation) + ', one row per sub-federation — its own sub-bodies are counted in its row. The filters above apply.',
        rows: rows.map(r => ({name: r.name, note: r.note, hasNote: !!r.note, clubs: fmt(r.t.clubs), athletes: fmt(r.t.athletes), comps: fmt(r.t.comps), entries: fmt(r.t.entries), medals: fmt(r.t.medals),
          heat: this.css('position:absolute;left:0;top:0;bottom:0;border-radius:10px;background:rgba(216,178,95,' + (.05 + .2 * r.t.entries / max).toFixed(3) + ');width:' + (r.t.entries / max * 100) + '%')}))};
    }
    // ---------- health
    const alerts = (D.alerts || []).map(a => ({...a, action: ''}));
    const sevC = {Critical: ['#b4483c', '!'], Warning: ['#a8620f', '▲'], Informational: ['#1f7fa0', 'i'], Completed: ['#2f7a4f', '✓']};
    const sevO = {Critical: 0, Warning: 1, Informational: 2, Completed: 3};
    const alSorted = alerts.slice().sort((a, b) => sevO[a.sev] - sevO[b.sev]);
    v.hl = {
      counts: ['Critical', 'Warning', 'Informational', 'Completed'].map(sv => ({label: sv, n: String(alerts.filter(a => a.sev === sv).length), dot: this.css('width:8px;height:8px;border-radius:50%;background:' + sevC[sv][0])})),
      rows: (s.alertsAll ? alSorted : alSorted.slice(0, 5)).map(a => ({...a, hasAction: false,
        icon: sevC[a.sev][1], iconStyle: this.css('width:30px;height:30px;flex:none;border-radius:9px;display:grid;place-items:center;font-size:.78rem;font-weight:700;color:' + sevC[a.sev][0] + ';background:' + sevC[a.sev][0] + '1f;border:1px solid ' + sevC[a.sev][0] + '55'),
        sevStyle: this.css('font-size:.62rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:' + sevC[a.sev][0]),
        onAct: () => {}})),
      none: alerts.length === 0, hasMore: alerts.length > 5,
      allLabel: s.alertsAll ? 'Show fewer' : 'View all alerts (' + alerts.length + ')', toggleAll: () => this.setState({alertsAll: !s.alertsAll})
    };
    // ---------- officials
    const offRows = {}; F.officials.forEach(o => { offRows[offKey(o)] = o; });
    const refList = Object.values(offRows).filter(o => o.role === 'Referee');
    const licC = count(refList, o => o.lic || 'Not given');
    const lic = Object.entries(licC).sort((a, b) => b[1] - a[1]).slice(0, 5), licMax = Math.max(1, ...lic.map(l => l[1]));
    const noLic = licC['Not given'] || 0;
    const offByBody = count(Object.values(offRows), o => bodyOf(o.c) || '—');
    const offRegions = D.regions.map(r => [r, offByBody[r] || 0]), offMax = Math.max(1, ...offRegions.map(r => r[1]));
    const people2 = refs.size + mgrs.size;
    v.of = {
      stats: [['Referees named', fmt(refs.size)], ['Team managers', fmt(mgrs.size)], ['Assignments this season', fmt(F.officials.length)], ['Avg. per official', people2 ? (F.officials.length / people2).toFixed(1) : '—']].map(([l, x]) => ({label: l, value: x})),
      banner: !people2 ? 'No officials named yet. Clubs name their referees and team manager on each entry.' : noLic ? noLic + ' referee' + (noLic === 1 ? ' was' : 's were') + ' named without a licence' : 'Every named referee has a licence on file',
      lic: lic.map(([l, x]) => ({label: l, value: String(x), bar: this.css('height:8px;border-radius:4px;background:linear-gradient(90deg,#3aa7c7,#5ec8e5);width:' + (x / licMax * 100) + '%')})),
      regions: offRegions.map(([r, x]) => ({label: r, value: String(x), bar: this.css('width:100%;max-width:56px;border-radius:5px 5px 0 0;background:#d8b25f;height:' + (x / offMax * 100) + '%;opacity:' + (f.region === 'All regions' || f.region === r ? 1 : .3))}))
    };
    // ---------- insights — only what the records actually show
    const ins = [];
    const actF = F.registered.filter(a => F.active.has(a.i) && a.g === 'F').length, actAll = F.registered.filter(a => F.active.has(a.i)).length;
    if (actAll >= 5 && f.gender === 'All') ins.push(['Participation', 'Female athletes make up ' + (actF / actAll * 100).toFixed(1) + '% of this season’s entrants.', fmt(actF) + ' female athlete' + (actF === 1 ? '' : 's'), 'Show female athletes', '#1f7fa0', () => this.setFilters({gender: 'Female'}, 'Filtered to female athletes')]);
    const bigDiv = F.divisions.slice().sort((a, b) => b.athletes.length - a.athletes.length)[0];
    if (bigDiv && bigDiv.athletes.length >= 2) ins.push(['Categories', 'The most entered division is ' + (bigDiv.g === 'F' ? 'Female ' : 'Male ') + bigDiv.div + (bigDiv.age ? ' · ' + bigDiv.age : '') + '.', bigDiv.athletes.length + ' entrants', 'Filter to this division', '#8a6d20',
      () => this.setFilters({disc: this.divDisc(bigDiv.div), gender: bigDiv.g === 'F' ? 'Female' : 'Male', age: bigDiv.age || 'All', weight: this.wLabel(bigDiv.div)}, 'Filtered to ' + bigDiv.div)]);
    const eff = medRows.filter(c => c.athletes > 0).sort((a, b) => b.medals / b.athletes - a.medals / a.athletes)[0];
    if (eff) ins.push(['Clubs', eff.name + ' has the highest medal efficiency.', (eff.medals / eff.athletes).toFixed(2) + ' medals per athlete', 'Open club', '#94701a', () => this.setState({drawer: {type: 'club', name: eff.name}})]);
    const topBody = geoRows.slice().sort((a, b) => b.entries - a.entries)[0];
    if (topBody && topBody.entries > 0 && geoRows.length > 1) ins.push(['Regions', topBody.name + ' leads on competition entries this season.', fmt(topBody.entries) + ' entries · ' + topBody.clubs + ' club' + (topBody.clubs === 1 ? '' : 's'), 'View on map', '#2f7a4f',
      () => { this.setState({geoSel: topBody.name}); this.scrollTo('geo'); if (this._map && topBody.lat != null) this._map.flyTo([topBody.lat, topBody.lng], 11); }]);
    if (entCh != null && F.entries.length) ins.push(['Growth', 'Competition entries are ' + (entCh >= 0 ? 'up ' : 'down ') + Math.abs(entCh).toFixed(1) + '% on the previous season.', fmt(F.entries.length) + ' entries vs ' + fmt(P.entries.length), 'View growth', entCh >= 0 ? '#2f7a4f' : '#b4483c', () => this.scrollTo('growth')]);
    v.ins = ins.map(([type, text, stat, act, col, fn]) => ({type, text, stat, act, onAct: fn, tag: this.css('font-size:.62rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:' + col), edge: this.css('position:absolute;left:0;top:18px;bottom:18px;width:3px;border-radius:0 3px 3px 0;background:' + col)}));
    v.insNone = v.ins.length === 0;
    v.showInsights = this.props.showInsights !== false;
    // ---------- drawer
    const dr = s.drawer;
    v.drawerOpen = !!dr;
    let d = null;
    if (dr && dr.type === 'club') { const c = clubRowsAll.find(x => x.name === dr.name);
      if (c) { const meds = F.medals.filter(m => F.ec(m.e, m.a) === c.i).sort((a, b) => a.place - b.place);
        d = {eyebrow: 'Club preview', title: c.name, sub: c.region + (c.affiliated ? ' · affiliated' : ' · not yet on the platform') + (c.medals ? ' · score ' + c.score.toFixed(1) : ''),
          stats: [['Registered athletes', fmt(c.athletes)], ['Competition entries', fmt(c.entries)], ['Bouts won', fmt(c.wins) + ' of ' + fmt(c.bouts)], ['Medals', fmt(c.medals) + ' (' + c.gold + ' gold)'], ['Participation rate', c.athletes ? c.part + '%' : '—'], ['Ranking trend', '—']],
          listTitle: 'Medallists this season', list: meds.slice(0, 12).map(m => ({a: ((D.athletes[m.a] || {}).name || '—') + ' · ' + placeName[m.place], b: (m.g === 'F' ? 'Female ' : 'Male ') + this.wLabel(m.div) + ' · ' + ((evById[m.e] || {}).name || '')})),
          cta: 'Filter page to this club', onCta: () => { this.setFilters({club: c.name, region: 'All regions'}, 'Filtered to ' + c.name); this.setState({drawer: null}); }}; } }
    else if (dr && dr.type === 'comp') { const e = D.events.find(x => x.label === dr.name);
      if (e) { const en = entByEv[e.i] || {a: new Set(), c: new Set()}, dv = divByEv[e.i] || {t: 0, d: 0}, meds = F.medals.filter(m => m.e === e.i);
        const byClub = count(meds, m => F.ec(m.e, m.a)); const top = Object.entries(byClub).filter(([k]) => k !== 'undefined' && k !== 'null').sort((a, b) => b[1] - a[1]).slice(0, 3);
        d = {eyebrow: 'Competition preview', title: e.name, sub: this.dayLabel(e.start) + (e.start ? ' ' + e.start.slice(0, 4) : '') + (e.venue ? ' · ' + e.venue : '') + ' · ' + (e.sport || ''),
          stats: [['Status', e.status], ['Clubs', String(en.c.size)], ['Athletes', fmt(en.a.size)], ['Bouts recorded', fmt(dv.d) + (dv.t ? ' of ' + fmt(dv.t) : '')], ['Completion', dv.t ? Math.round(dv.d / dv.t * 100) + '%' : '—'], ['Medals awarded', meds.length ? String(meds.length) : '—']],
          listTitle: top.length ? 'Top clubs by medals' : 'Registrations', list: top.length ? top.map(([k, x], i) => ({a: (i + 1) + '. ' + ((D.clubs[k] || {}).name || '—'), b: x + ' medal' + (x > 1 ? 's' : '')}))
            : [{a: 'Entries', b: en.a.size ? fmt(en.a.size) + ' athletes from ' + en.c.size + ' clubs' : 'None yet'}],
          cta: en.a.size ? 'Filter page to this competition' : '', onCta: () => { this.setFilters({comp: e.label}, 'Filtered to ' + e.name); this.setState({drawer: null}); }}; } }
    else if (dr && dr.type === 'ath') { const a = D.athletes[dr.idx];
      if (a) { const meds = D.medals.filter(m => m.a === dr.idx).sort((x, y) => x.place - y.place), won = D.bouts.filter(b => b.w === dr.idx).length, lost = D.bouts.filter(b => b.l === dr.idx).length;
        const c = D.clubs[a.club];
        d = {eyebrow: 'Athlete preview', title: a.name || '—', sub: (c ? c.name : '—') + ' · ' + (a.age || 'Age not recorded'),
          stats: [['Medals', fmt(meds.length)], ['Bouts won', fmt(won)], ['Bouts lost', fmt(lost)], ['Entries', fmt(D.entries.filter(x => x.a === dr.idx && x.st === 'Active').length)], ['Age category', a.age || '—'], ['Current rank', '—']],
          listTitle: 'Medals on record', list: meds.map(m => ({a: placeName[m.place] + ' · ' + (m.g === 'F' ? 'Female ' : 'Male ') + this.wLabel(m.div), b: (evById[m.e] || {}).name || ''})), cta: '', onCta: () => {}}; } }
    if (d) { d.stats = d.stats.map(([l, x]) => ({label: l, value: x})); d.hasCta = !!d.cta; d.hasList = d.list.length > 0; v.dw = d; }
    else { v.drawerOpen = false; v.dw = {stats: [], list: []}; }
    v.closeDrawer = () => this.setState({drawer: null});
    v.stop = e => e.stopPropagation();
    // ---------- ui
    const up = new Date(s.updated);
    v.updatedLabel = s.stats ? up.toLocaleDateString('en-GB', {day: 'numeric', month: 'short', year: 'numeric'}) + ', ' + up.toLocaleTimeString('en-GB', {hour: '2-digit', minute: '2-digit'}) : '—';
    v.loading = loading; v.isMobile = s.isMobile; v.notMobile = !s.isMobile;
    v.dataStyle = this.css('transition:opacity .25s ease;opacity:' + (loading ? .45 : 1));
    v.isEmpty = E; v.notEmpty = !E; v.emptyWhy = F.emptyWhy; v.emptyTitle = F.emptyTitle;
    v.showError = !!s.loadErr && !s.errCleared;
    v.errMsg = s.stats ? 'Showing the figures from ' + v.updatedLabel + '.' : 'Check the connection and try again.';
    v.retry = () => this.refresh();
    v.doRefresh = () => this.refresh();
    v.doExport = () => { this.setState({more: false}); this.exportCsv(v); };
    v.doPrint = () => { this.setState({more: false}); setTimeout(() => window.print(), 50); };
    v.toggleMore = e => { e.stopPropagation(); this.setState({more: !s.more}); };
    v.moreOpen = s.more;
    v.openFilters = () => this.setState({fDrawer: true}); v.closeFilters = () => this.setState({fDrawer: false, fOpen: null}); v.fDrawerOpen = s.fDrawer;
    const emb = !!this.props.embedded;
    v.shellStyle = this.css("color:#1e2c4f;font-family:'Poppins',system-ui,sans-serif;" + (emb ? '' : 'min-height:100vh;background:radial-gradient(1100px 480px at 88% -8%,rgba(216,178,95,.12),transparent 62%),#eef1f6'));
    v.headStyle = this.css('display:flex;flex-wrap:wrap;align-items:flex-end;gap:16px;margin-bottom:24px;' + (emb ? '' : 'padding-top:clamp(20px,3vw,34px)'));
    v.innerStyle = this.css(emb ? 'padding:0 0 24px;box-sizing:border-box' : 'max-width:1560px;margin:0 auto;padding:0 clamp(14px,2.4vw,32px) 56px;box-sizing:border-box');
    v.filterBarStyle = this.css('position:sticky;top:' + (emb ? '64px' : '0') + ';z-index:40;margin:0 calc(-1 * ' + (emb ? 'clamp(18px,3vw,40px)' : 'clamp(14px,2.4vw,32px)') + ');padding:10px ' + (emb ? 'clamp(18px,3vw,40px)' : 'clamp(14px,2.4vw,32px)') + ';background:rgba(238,241,246,.94);backdrop-filter:blur(12px);border-top:1px solid rgba(30,44,79,0.069);border-bottom:1px solid rgba(30,44,79,0.069)');
    v.hasToast = !!s.toast; v.toast = s.toast || '';
    // The club table needs ~1000px of card; below that it would scroll
    // sideways and hide Gold, Performance and Trend, so the cards are used.
    const clubRoom = !s.isMobile && window.innerWidth >= 1320;
    v.clubCards = !clubRoom; v.clubTable = clubRoom;
    return v;
  }
