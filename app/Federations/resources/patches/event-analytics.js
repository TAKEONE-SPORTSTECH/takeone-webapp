  // The event's Analytics tab (owner, 2026-10-25: "the same statistic and
  // analytics section … on the federation event tab"). The competition
  // report's statistics & analytics part, live: the figures come from
  // /federation/analytics/{event} (FederationCompetitionReport::analytics —
  // the very numbers the printed report draws), read once when the tab opens
  // and again whenever the event's bracket moves on (a bout finished, a
  // podium decided). Read-only; nothing here writes.
  anLoad(id, url, sig) {
    if (this._anBusy === id + sig) return;
    this._anBusy = id + sig;
    const prev = this.state.anData && this.state.anData.id === id ? this.state.anData.data : null;
    this.setState({anData: {id, sig, loading: true, data: prev, err: false}});
    fetch(url, {credentials: 'same-origin', headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}})
      .then(r => r.ok ? r.json() : Promise.reject(r.status))
      .then(j => { this._anBusy = null; this.setState({anData: {id, sig, loading: false, data: j && j.analytics ? j.analytics : null, err: false}}); })
      .catch(() => { this._anBusy = null; this.setState({anData: {id, sig, loading: false, data: prev, err: true}}); });
  }
  anVals(sel) {
    const css = c => this.cssToObj(c);
    const T = window.TakeOneFed || {};
    const url = sel && T.analytics ? T.analytics(sel.id) : null;
    const none = {has: false, loading: false, empty: true, retry: false, msg: 'Analytics appear here once this event is running on the platform with a draw.'};
    if (!url) return none;

    // Re-read when the bracket moves on: bouts finished, podiums decided.
    const B = T.bracket ? T.bracket(sel.id) : null;
    const sig = B ? (B.matches || []).filter(m => m.done).length + ':' + (B.cats || []).reduce((n, c) => n + (c.podium || []).length, 0) : '0';
    const st = this.state.anData && this.state.anData.id === sel.id ? this.state.anData : null;
    if (!st || (st.sig !== sig && !st.loading)) setTimeout(() => this.anLoad(sel.id, url, sig), 0);
    const D = st && st.data;
    if (!D) {
      return Object.assign({}, none, st && st.err
        ? {msg: 'The analytics could not be loaded just now.', retry: true, onRetry: () => { this._anBusy = null; this.anLoad(sel.id, url, sig); }}
        : st && !st.loading ? {msg: 'There is nothing to analyse yet — this event has no draw.'} : {loading: true, empty: false, msg: ''});
    }

    const s = D.stats;
    const pct = (a, b) => b ? Math.round(a / b * 100) : 0;
    const mmss = v => v == null ? '—' : Math.floor(v / 60) + ':' + String(Math.round(v) % 60).padStart(2, '0');
    const hm = v => Math.floor(v / 3600) + 'h ' + String(Math.floor(v % 3600 / 60)).padStart(2, '0') + 'm';
    const hhmm = m => m == null ? '' : String(Math.floor(m / 60)).padStart(2, '0') + ':' + String(m % 60).padStart(2, '0');
    const n2 = v => (Math.round(v * 100) / 100).toFixed(2);
    const go = u => u ? (e => { if (e && e.stopPropagation) e.stopPropagation(); window.location.assign(u); }) : (() => {});
    const cat = ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4', '#6250d6'];
    const techInk = {1: '#86b6ef', 2: '#2a78d6', 3: '#104281'};
    const techName = {1: 'Yuko', 2: 'Waza-ari', 3: 'Ippon'};
    const foulInk = {1: '#f4b48f', 2: '#eb6834', 3: '#c4511f', 4: '#8f3813', 5: '#5c230b'};
    const foulName = {1: 'Chui 1', 2: 'Chui 2', 3: 'Chui 3', 4: 'Hansoku-chui', 5: 'Hansoku'};
    const reasonInk = {Points: '#2a78d6', Decision: '#eb6834', Withdrawal: '#1baf7a', Disqualification: '#eda100', Ippon: '#6250d6'};

    // A donut from [[label, value, colour], …] — a conic gradient with a hole.
    const donut = (parts, big, small) => {
      const total = parts.reduce((t, p) => t + Math.max(0, p[1]), 0);
      let at = 0;
      const stops = total ? parts.filter(p => p[1] > 0).map(p => { const a = at; at += p[1] / total * 360; return p[2] + ' ' + a.toFixed(2) + 'deg ' + at.toFixed(2) + 'deg'; }).join(',') : '#eef1f6 0deg 360deg';
      return {big: String(big), small,
        ring: css('position:relative;width:132px;height:132px;flex:none;border-radius:50%;background:conic-gradient(' + stops + ');display:grid;place-items:center;box-shadow:0 8px 22px rgba(30,44,79,.1)'),
        rows: parts.map(p => ({label: p[0], value: String(p[1]), pct: pct(p[1], total) + '%', dot: css('width:10px;height:10px;border-radius:3px;flex:none;background:' + p[2])}))};
    };
    // Horizontal bars: [{label, value, logo?, flag?}].
    const bars = (list, ink, fmt) => {
      const max = Math.max(1e-9, ...list.map(r => r.value));
      return list.map(r => ({label: r.label, value: fmt ? fmt(r.value) : String(r.value), logo: r.logo || '', hasLogo: !!r.logo, flag: r.flag || '', hasFlag: !!r.flag,
        fill: css('display:block;height:100%;border-radius:999px;width:' + Math.max(2, r.value / max * 100) + '%;background:' + ink)}));
    };
    // Columns: [[axis label, value, colour]].
    const cols = (list) => {
      const max = Math.max(1, ...list.map(c => c[1]));
      return list.map(c => ({x: c[0], v: c[1] ? String(c[1]) : '', bar: css('width:100%;max-width:38px;border-radius:7px 7px 3px 3px;height:' + (c[1] ? Math.max(3, c[1] / max * 100) : 0) + '%;background:' + c[2])}));
    };
    // A portrait 3:4 face box (profile pictures are 3:4 on the platform).
    const face = (p, h) => Object.assign(css('width:' + Math.round(h * .75) + 'px;height:' + h + 'px;flex:none;border-radius:8px;background:#eef1f6;background-size:cover;background-position:center top;box-shadow:0 0 0 1px #e3e7ee'),
      p && p.photo ? {backgroundImage: 'url("' + String(p.photo).replace(/"/g, '%22') + '")'} : {});
    // A person opens their public profile (people.show — it authorises itself).
    const who = (p) => ({name: p && p.name ? p.name : '—', club: p && p.club ? p.club : '', flag: p && p.flag ? p.flag : '', hasFlag: !!(p && p.flag),
      onOpen: go(p && p.profile), link: css(p && p.profile ? 'cursor:pointer' : '')});
    const kpi = (label, value, unit, ink, dark) => ({label, value: String(value), unit: unit || '', hasUnit: !!unit,
      style: css('position:relative;overflow:hidden;padding:16px 16px 14px 18px;border-radius:16px;' + (dark ? 'background:linear-gradient(135deg,#1e2c4f,#2c3f6e);color:#fff' : 'background:#fff;border:1px solid #e9edf3;color:#1e2c4f') + ';box-shadow:0 10px 26px rgba(30,44,79,.07)'),
      bar: css('position:absolute;left:0;top:0;bottom:0;width:4px;background:' + ink),
      labelStyle: css('margin-top:4px;font-size:.64rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:' + (dark ? '#edcd85' : '#6b7689'))});

    const techTotal = Math.max(1, s.tech.Yuko + s.tech['Waza-ari'] + s.tech.Ippon);
    const gender = D.gender || {Male: 0, Female: 0};
    const ages = D.ages || {};
    const ageNames = Object.keys(ages);
    Object.keys(s.techByAge || {}).forEach(a => { if (!ageNames.includes(a)) ageNames.push(a); });
    const ageInk = {};
    ageNames.forEach((a, i) => { ageInk[a] = cat[i % cat.length]; });
    const ageMax = Math.max(1, ...Object.values(ages).map(m => (m.M || 0) + (m.F || 0)));

    // ── The day, mat by mat ─────────────────────────────────────────────
    const h0 = s.day.from != null ? Math.floor(s.day.from / 60) : 9;
    const h1 = s.day.to != null ? Math.floor(s.day.to / 60) + 1 : 18;
    const span = Math.max(60, (h1 - h0) * 60);
    const pos = m => Math.max(0, Math.min(100, (m - h0 * 60) / span * 100));
    const hours = [];
    for (let h = h0; h <= h1; h++) hours.push({label: String(h).padStart(2, '0') + ':00', style: css('position:absolute;top:0;transform:translateX(-50%);font-size:.62rem;font-weight:700;color:#6b7689;white-space:nowrap;left:' + pos(h * 60) + '%')});
    const perHour = [];
    for (let h = h0; h < h1; h++) perHour.push([String(h).padStart(2, '0'), s.perHour[String(h).padStart(2, '0')] || 0, '#1baf7a']);

    const corner = Math.max(1, s.corner.a + s.corner.b);
    const fpbMax = Math.max(.01, ...(s.foulsByAge || []).map(r => r.perBout));
    const tsMax = Math.max(1, ...(s.topScorers || []).map(r => r.points));
    const perf = D.perf || [];
    const medals = r => r.medals[1] + r.medals[2] + r.medals[3];
    const nTop = (D.nations || []).filter(n => n.iso).sort((a, b) => b.athletes - a.athletes).slice(0, 8);

    // The event's ranking coefficient (owner, 2026-10-31: "a place to set the
    // coefficient for the event"). Managers get the control — the boot only
    // carries its address for them — and the endpoint authorises regardless.
    const rkUrl = ((T.boot || {}).urls || {}).rankingFactor || null;
    const rkEd = this.state.rkEd && this.state.rkEd.id === sel.id ? this.state.rkEd : null;
    const rkSave = () => {
      const cur = this.state.rkEd;
      if (!cur || cur.saving || !rkUrl) return;
      const n = Number(String(cur.val).trim());
      if (!Number.isInteger(n) || n < 1 || n > 20) { this.setState({rkEd: {...cur, err: 'Enter a whole number from 1 to 20.'}}); return; }
      this.setState({rkEd: {...cur, saving: true, err: ''}});
      fetch(rkUrl, {method: 'POST', credentials: 'same-origin', body: JSON.stringify({event: sel.id, factor: n}),
        headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': T.csrf ? T.csrf() : '', 'X-Requested-With': 'XMLHttpRequest'}})
        .then(r => r.json().catch(() => ({})).then(j => ({ok: r.ok, j})))
        .then(({ok, j}) => {
          if (!ok) { this.setState({rkEd: {...(this.state.rkEd || cur), saving: false, err: (j && j.message) || 'The coefficient could not be saved.'}}); return; }
          this.setState({rkEd: null});
          if (window.showToast) window.showToast('success', j.message || 'Ranking coefficient saved.');
          this._anBusy = null;
          this.anLoad(sel.id, url, sig);
        })
        .catch(() => this.setState({rkEd: {...(this.state.rkEd || cur), saving: false, err: 'The coefficient could not be saved just now.'}}));
    };
    const cTop = (D.clubs || []).slice().sort((a, b) => b.count - a.count).slice(0, 8);

    return {
      has: true, loading: false, empty: false, retry: false, refreshing: !!st.loading,
      eyebrow: sel && sel.status === 'Completed' ? 'Final figures' : 'Live from the mats',
      bouts: s.bouts > 0, noBouts: !(s.bouts > 0),
      exhibitions: s.exhibitions ? s.exhibitions + (s.exhibitions === 1 ? ' exhibition bout was' : ' exhibition bouts were') + ' fought outside the draw and are not counted here.' : '',
      hasExhibitions: !!s.exhibitions,
      kpis: [
        kpi('Bouts on the mat', s.decided, '', '#2a78d6'), kpi('Points scored', s.scored, '', '#eb6834'),
        kpi('Ippons', s.tech.Ippon, '', '#104281'), kpi('Fouls called', s.foulsTotal, '', '#c4511f'),
        kpi('Total fight time', hm(s.fight.total), '', '#1baf7a'), kpi('Average bout', mmss(s.fight.avg), '', '#eda100'),
        kpi('First point wins', pct(s.first.won, s.first.of), '%', '#d8b25f', true), kpi('Comebacks', s.comebacks, '', '#6250d6')],

      // ── 1 · In numbers ─────────────────────────────────────────────────
      athletes: donut([['Male', gender.Male, '#2563eb'], ['Female', gender.Female, '#db2777']], D.athletes, 'athletes'),
      ages: ageNames.filter(a => ages[a]).map(a => {
        const m = ages[a].M || 0, f = ages[a].F || 0;
        return {label: a, value: m + ' · ' + f,
          track: css('display:flex;height:100%;border-radius:999px;overflow:hidden;width:' + Math.max(4, (m + f) / ageMax * 100) + '%'),
          m: css('flex:' + m + ';background:#2563eb'), f: css('flex:' + f + ';background:#db2777')};
      }),
      nations: bars(nTop.map(n => ({label: n.name, value: n.athletes, flag: n.flag})), '#1baf7a'),
      clubsTop: bars(cTop.map(c => ({label: c.name, value: c.count, logo: c.logo})), '#2a78d6'),

      // ── 2 · How bouts are won ──────────────────────────────────────────
      reasons: donut(Object.keys(s.reasons || {}).map(k => [k, s.reasons[k], reasonInk[k] || '#6250d6']), s.decided, 'bouts'),
      tech: donut([1, 2, 3].map(n => [techName[n] + ' · ' + n + ' pt', s.tech[techName[n]], techInk[n]]), s.points, 'scores'),
      pointsPerBout: String(s.pointsPerBout),
      techByAge: Object.keys(s.techByAge || {}).map(a => {
        const t = s.techByAge[a], sum = (t[1] || 0) + (t[2] || 0) + (t[3] || 0);
        return {label: a, dot: css('width:9px;height:9px;border-radius:50%;flex:none;background:' + (ageInk[a] || '#9aa3b2')),
          parts: [1, 2, 3].filter(n => t[n]).map(n => ({style: css('flex:' + t[n] + ';background:' + techInk[n]), title: techName[n] + ': ' + t[n]})),
          value: pct(t[3] || 0, sum) + '% ippon'};
      }),
      techLegend: [1, 2, 3].map(n => ({label: techName[n], dot: css('width:10px;height:10px;border-radius:3px;background:' + techInk[n])})),
      margins: cols(Object.keys(s.margins || {}).map(k => [k === '0' ? 'Level' : k, s.margins[k], k === '0' ? '#eb6834' : '#2a78d6'])),
      insights: [
        {head: pct(s.tech.Ippon, techTotal) + '% of scores were Ippons', text: s.tech.Ippon + ' three-point techniques across ' + s.decided + ' bouts, worth ' + (s.tech.Ippon * 3) + ' of the ' + s.scored + ' points scored.'},
        {head: s.early + ' bouts ended before the bell', text: 'Stopped on the points gap, by disqualification or by withdrawal — ' + pct(s.early, s.decided) + '% of all bouts.'},
        {head: (s.margins['0'] || 0) + ' bouts finished level', text: 'Settled by senshu or the referees’ decision; ' + (s.margins['1'] || 0) + ' more were won by a single point.'}],

      // ── 3 · Time on the mat ────────────────────────────────────────────
      firstBout: hhmm(s.day.from), lastBell: hhmm(s.day.to), hasDay: s.day.from != null,
      phase: cols((s.phase || []).map((v, i) => [(i * 10) + '–' + ((i + 1) * 10) + '%', v, i === 9 ? '#d8b25f' : '#2a78d6'])),
      lastSeconds: s.lastSeconds + ' points came in the final 15 seconds of a bout',
      lastSecondsText: pct(s.lastSeconds, s.points) + '% of all scores. The gold column is the last tenth of the clock.',
      timeKpis: [kpi('Fight time a bout', mmss(s.fight.avg), '', '#1baf7a'), kpi('On the mat a bout', mmss(s.wall.avg), '', '#eda100'), kpi('Clock running', pct(s.fight.avg, s.wall.avg), '%', '#d8b25f', true)],
      mats: (s.mats || []).map(m => ({mat: m.mat, sub: m.bouts + ' bouts' + (m.changeover ? ' · ' + mmss(m.changeover) + ' changeover' : ''),
        blocks: m.blocks.map(b => ({title: 'Bout ' + b.no + ' · ' + b.age + ' · ' + hhmm(b.from) + '–' + hhmm(b.to),
          style: css('position:absolute;top:6px;bottom:6px;border-radius:3px;left:' + pos(b.from).toFixed(2) + '%;width:' + Math.max(.35, pos(b.to) - pos(b.from)).toFixed(2) + '%;background:' + (ageInk[b.age] || '#9aa3b2'))}))})),
      hours, perHour: cols(perHour),
      ageLegend: ageNames.map(a => ({label: a, dot: css('width:10px;height:10px;border-radius:3px;background:' + ageInk[a])})),

      // ── 4 · Discipline & corners ───────────────────────────────────────
      foulsTotal: String(s.foulsTotal),
      foulsPerBout: (Math.round(s.foulsTotal / Math.max(1, s.decided) * 10) / 10).toFixed(1),
      fouls: cols([1, 2, 3, 4, 5].map(l => [foulName[l], s.fouls[l] || 0, foulInk[l]])),
      foulsByAge: (s.foulsByAge || []).map(r => ({label: r.age, value: n2(r.perBout), fill: css('display:block;height:100%;border-radius:999px;width:' + Math.max(2, r.perBout / fpbMax * 100) + '%;background:#eb6834')})),
      aka: s.corner.a + ' · ' + pct(s.corner.a, corner) + '%', ao: s.corner.b + ' · ' + pct(s.corner.b, corner) + '%',
      akaStyle: css('flex:' + Math.max(1, s.corner.a) + ';display:flex;align-items:center;justify-content:space-between;gap:8px;padding:0 16px;background:#dc2626'),
      aoStyle: css('flex:' + Math.max(1, s.corner.b) + ';display:flex;align-items:center;justify-content:space-between;gap:8px;padding:0 16px;background:#2563eb;box-shadow:-2px 0 0 #fff'),
      cornerKpis: [kpi('Scored first, won', s.first.won, '/ ' + s.first.of, '#d8b25f', true), kpi('Senshu holders won', s.senshu.won, '/ ' + s.senshu.of, '#6250d6'), kpi('Won from behind', s.comebacks, '', '#1baf7a')],
      highlights: [['highest', 'Highest scoring'], ['widest', 'Widest margin'], ['fastest', 'Fastest win'], ['closest', 'Closest finish']]
        .filter(([k]) => s.highlights && s.highlights[k])
        .map(([k, label]) => { const h = s.highlights[k]; return {label, no: 'Bout ' + h.no, a: who(h.A), b: who(h.B), score: h.sa + ' – ' + h.sb, division: h.division, round: h.round + ' · ' + mmss(h.fight)}; }),

      // ── 5 · Stars ──────────────────────────────────────────────────────
      topScorers: (s.topScorers || []).map((r, i) => Object.assign(who(r.person), {rank: String(i + 1), face: face(r.person, 48), points: String(r.points), won: r.wins + '/' + r.bouts, ippon: String(r.ippon),
        rankStyle: css('width:28px;height:28px;flex:none;border-radius:50%;display:grid;place-items:center;font-size:.78rem;font-weight:900;color:#1e2c4f;' + (i === 0 ? 'background:linear-gradient(140deg,#f8e4a0,#d4a940 55%,#b8892a)' : i === 1 ? 'background:linear-gradient(140deg,#f4f6fa,#b9c1ce 55%,#97a1b4)' : i === 2 ? 'background:linear-gradient(140deg,#f0c49f,#c9895b 55%,#a2643a)' : 'background:#f1f3f7;color:#6b7689')),
        fill: css('display:block;height:100%;border-radius:999px;background:linear-gradient(90deg,#d4a940,#b8892a);width:' + Math.max(2, r.points / tsMax * 100) + '%')})),
      ipponMasters: (s.ipponMasters || []).map(r => Object.assign(who(r.person), {face: face(r.person, 40), value: String(r.ippon)})),
      unbeaten: (s.unbeaten || []).map(r => Object.assign(who(r.person), {face: face(r.person, 40), value: r.wins + '–0'})),
      hasIppon: (s.ipponMasters || []).length > 0, hasUnbeaten: (s.unbeaten || []).length > 0, hasStars: (s.topScorers || []).length > 0,

      // ── 6 · Club performance ───────────────────────────────────────────
      perf: perf.map(r => ({name: r.name, logo: r.logo || '', hasLogo: !!r.logo, noLogo: !r.logo, athletes: String(r.athletes), bouts: String(r.bouts), wins: String(r.wins),
        rate: pct(r.wins, r.bouts) + '%', rateFill: css('display:block;height:100%;border-radius:999px;background:#1baf7a;width:' + pct(r.wins, r.bouts) + '%'),
        pf: String(r.for), pa: String(r.against), g: String(r.medals[1]), s: String(r.medals[2]), b: String(r.medals[3]),
        gs: css(r.medals[1] ? 'font-weight:800;color:#8a6d20' : 'color:#c3c9d6'), ss: css(r.medals[2] ? 'font-weight:800;color:#5b6579' : 'color:#c3c9d6'), bs: css(r.medals[3] ? 'font-weight:800;color:#8f5a35' : 'color:#c3c9d6'),
        per: n2(medals(r) / Math.max(1, r.athletes))})),
      hasPerf: perf.length > 0,
      efficiency: bars(perf.map(r => ({label: r.name, logo: r.logo, value: medals(r) / Math.max(1, r.athletes)})).sort((a, b) => b.value - a.value), '#d8b25f', n2),
      winRate: bars(perf.filter(r => r.bouts > 0).map(r => ({label: r.name, logo: r.logo, value: pct(r.wins, r.bouts)})).sort((a, b) => b.value - a.value), '#1baf7a', v => v + '%'),

      // ── 7 · National ranking — who leads each weight class ─────────────
      // (owner, 2026-10-31: "the first ranked, then the second, then third,
      // each in a stylish manner and with their ranking"). From App\Rankings:
      // the WKF formula over every federation event, decay applied.
      rankings: (D.rankings || []).map(c => ({
        name: c.name,
        sub: (c.discipline === 'kata' ? 'Kata' : 'Kumite') + ' · ' + c.athletes + ' ranked',
        top: (c.top || []).map((r, i) => {
          const dark = i === 0;
          const metal = ['linear-gradient(140deg,#f8e4a0,#d4a940 55%,#b8892a)', 'linear-gradient(140deg,#f4f6fa,#b9c1ce 55%,#97a1b4)', 'linear-gradient(140deg,#f0c49f,#c9895b 55%,#a2643a)'][i] || '#f1f3f7';
          const edge = ['#d4a940', '#a9b2c3', '#c9895b'][i] || '#e9edf3';
          const nth = n => n + (n % 100 >= 11 && n % 100 <= 13 ? 'th' : ({1: 'st', 2: 'nd', 3: 'rd'}[n % 10] || 'th'));
          const won = [[r.gold, 'gold'], [r.silver, 'silver'], [r.bronze, 'bronze']].filter(m => m[0]).map(m => m[0] + ' ' + m[1]).join(' · ');
          return Object.assign(who(r.person), {
            rank: '#' + r.position, label: 'Ranked ' + nth(r.position), points: r.points, medals: won, hasMedals: !!won,
            face: face(r.person, dark ? 80 : 68),
            card: css('position:relative;overflow:hidden;height:100%;box-sizing:border-box;padding:16px;border-radius:16px;display:flex;flex-direction:column;gap:12px;'
              + (dark ? 'background:linear-gradient(150deg,#1e2c4f,#2c3f6e);color:#fff;box-shadow:0 16px 34px rgba(30,44,79,.28)'
                      : 'background:#fff;color:#1e2c4f;border:1px solid #e9edf3;border-top:3px solid ' + edge + ';box-shadow:0 10px 26px rgba(30,44,79,.07)')),
            glow: css('position:absolute;right:-30px;top:-30px;width:110px;height:110px;border-radius:50%;background:' + (dark ? 'rgba(237,205,133,.14)' : 'rgba(30,44,79,.04)')),
            disc: css('width:38px;height:38px;flex:none;border-radius:50%;display:grid;place-items:center;font-size:.86rem;font-weight:900;color:#1e2c4f;box-shadow:0 4px 12px rgba(30,44,79,.18);background:' + metal),
            labelStyle: css('font-size:.62rem;font-weight:800;letter-spacing:.14em;text-transform:uppercase;color:' + (dark ? '#edcd85' : '#8a93a8')),
            clubStyle: css('font-size:.64rem;font-weight:700;text-transform:uppercase;overflow-wrap:anywhere;color:' + (dark ? '#c9d3e6' : '#6b7689')),
            ptsStyle: css('font-size:' + (dark ? '1.7rem' : '1.4rem') + ';font-weight:900;line-height:1;font-variant-numeric:tabular-nums;color:' + (dark ? '#edcd85' : '#1e2c4f')),
            subStyle: css('font-size:.68rem;font-weight:700;color:' + (dark ? '#c9d3e6' : '#6b7689'))
          });
        })
      })),
      hasRankings: (D.rankings || []).length > 0,
      rkShow: D.rankingFactor != null,
      rkEmpty: D.rankingFactor != null && !(D.rankings || []).length,
      rkFactor: '×' + D.rankingFactor,
      rkCanEdit: !!rkUrl && !rkEd, rkEditing: !!rkEd, rkNotEditing: !rkEd,
      rkVal: rkEd ? rkEd.val : '', rkErr: rkEd ? rkEd.err : '', rkHasErr: !!(rkEd && rkEd.err),
      rkSaveLabel: rkEd && rkEd.saving ? 'Saving…' : 'Save',
      onRkEdit: () => this.setState({rkEd: {id: sel.id, val: String(D.rankingFactor), saving: false, err: ''}}),
      onRkVal: ev => { const cur = this.state.rkEd; if (cur) this.setState({rkEd: {...cur, val: ev.target.value, err: ''}}); },
      onRkKey: ev => { if (ev.key === 'Enter') rkSave(); if (ev.key === 'Escape') this.setState({rkEd: null}); },
      onRkSave: rkSave,
      onRkCancel: () => this.setState({rkEd: null})
    };
  }
