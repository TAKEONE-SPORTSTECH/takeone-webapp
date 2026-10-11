// Event statistics — the platform half (FederationApp::eventStatisticsRewrites).
//
// The tab's markup and its sample-data preview are lifted unchanged from
// resources/app/Event Statistics.dc.html. What that file cannot do is read
// a real event: it SIMULATES bouts. These two methods replace that for an
// event that has kumite registrations, and read only what is recorded:
//
//   - the draw each division actually has (TakeOneFed.drawSlots — the same
//     saved bracket the Draw tab shows, or the fair draw it would build);
//   - each decided bout's winner and how it was won (TakeOneFed.divResults —
//     Points, Ippon, Decision, Disqualification, Withdrawal).
//
// Scores, penalties, techniques and bout times are NOT recorded on the
// platform, so every figure built from them shows "—" and the sections that
// are nothing but them (Techniques, Pace, the session filter) are hidden.
// No number on this tab is invented.
//
// The file's own evStatsVals() is renamed evStatsValsSample() and still runs
// for the empty state and the "Preview with sample data" roster, which says
// "Sample data" in its own banner.

  evStatsVals() {
    const s = this.state;
    const regs = (s.regs || []).filter(r => /^kumite/i.test(r.division || ''));
    if (!regs.length) {
      const v = this.evStatsValsSample();
      return {...v, cats: (v.cats || []).map(c => this.esGender(c)), hasScores: true, hasBanner: !!v.banner,
        ringNote: 'Schedule assumes 4-minute slots from 09:00. Referee assignments are not tracked yet.'};
    }
    const ev = (s.events || []).find(e => e.id === s.selectedId) || {};
    const css = x => this.cssToObj(x);
    const D = this.esReal(ev, regs);
    const pct = (a, b) => b ? Math.round(a / b * 100) : 0;
    const fc = D.cats.some(c => c.key === s.esCat) ? s.esCat : 'all';
    const fr = s.esRing && +s.esRing <= D.rings ? s.esRing : 'all';
    const ringOk = m => fr === 'all' || String(m.ring) === fr;
    const catOk = m => fc === 'all' || m.cat === fc;
    const all = D.matches.filter(m => catOk(m) && ringOk(m));
    const F = all.filter(m => m.done), n = F.length;
    const catOf = k => D.cats.find(c => c.key === k) || {label: k};
    const openCat = k => this.setState({esModal: {type: 'cat', key: k}});
    const openAth = id => this.setState({esModal: {type: 'ath', id}});
    const scrollTo = id => { const el = document.getElementById(id); if (!el) return; let p = el.parentElement;
      while (p && p !== document.body && !((/(auto|scroll)/).test(getComputedStyle(p).overflowY) && p.scrollHeight > p.clientHeight)) p = p.parentElement;
      if (p && p !== document.body) p.scrollTop += el.getBoundingClientRect().top - p.getBoundingClientRect().top - 130;
      else window.scrollTo({top: el.getBoundingClientRect().top + window.scrollY - 130, behavior: 'smooth'}); };
    const bar = (w, col) => css('height:100%;border-radius:4px;background:' + col + ';width:' + Math.max(0, Math.min(100, w)) + '%');
    const kc = ['#d8b25f', '#1e2c4f', '#2f7a4f', '#a8620f', '#b4483c', '#2f5fa8'];
    const shownCats = D.cats.filter(c => fc === 'all' || c.key === fc);
    const shownAth = shownCats.reduce((x, c) => x + c.athletes, 0);
    const shownClubs = new Set(regs.filter(r => fc === 'all' || this.esKey(r) === fc).map(r => r.club || '—')).size;

    const v = {ready: true, empty: false, noData: n === 0, hasData: n > 0, hasScores: false, hasBanner: false, banner: '',
      sub: (ev.name || 'Event') + ' · ' + D.cats.length + ' categor' + (D.cats.length === 1 ? 'y' : 'ies') + ' · ' + D.athletes + ' athletes · ' + D.rings + ' tatami',
      sections: [['es-overview', 'Overview'], ['es-categories', 'Categories'], ['es-athletes', 'Athletes'], ['es-rings', 'Tatami']].map(([id, label]) => ({label, go: () => scrollTo(id)})),
      fCat: fc, fRing: fr, fSes: 'all',
      catOpts: [{value: 'all', label: 'All categories'}, ...D.cats.map(c => ({value: c.key, label: c.label}))],
      ringOpts: [{value: 'all', label: 'All tatami'}, ...Array.from({length: D.rings}, (_, i) => ({value: String(i + 1), label: 'Tatami ' + (i + 1)}))],
      onCat: e => this.setState({esCat: e.target.value, esPage: 1}), onRing: e => this.setState({esRing: e.target.value, esPage: 1}), onSes: () => {},
      hasFilter: fc !== 'all' || fr !== 'all', reset: () => this.setState({esCat: 'all', esRing: 'all', esSession: 'all', esPage: 1}),
      print: () => window.print(),
      hours: [], paceNote: '', values: [], techs: [], timing: [], senshuLine: '',
      jodanPct: '—', chudanPct: '—', jodanStyle: {}, chudanStyle: {},
      ringNote: (window.TakeOneFed && window.TakeOneFed.bracket && window.TakeOneFed.bracket(ev.id))
        ? 'Bouts, mats and results are the event\u2019s own draw, live from the mats. Bout times are not recorded yet.'
        : ev.mats
        ? 'Categories are spread across the ' + D.rings + ' tatami in order. Bout times and referee assignments are not recorded yet.'
        : 'Set the number of mats in Edit event to spread categories across tatami. Bout times are not recorded yet.'};

    const byCount = k => F.filter(m => m.by === k).length;
    const early = byCount('Disqualification') + byCount('Withdrawal');
    const finals = shownCats.filter(c => D.matches.some(m => m.cat === c.key && m.round === 'Final' && m.done)).length;
    v.kpis = [
      ['Matches completed', n + ' / ' + all.length, (all.length - n) + ' remaining', 'Bouts with a result recorded in the draw, out of every bout the draw needs'],
      ['Athletes', String(shownAth), shownClubs + ' club' + (shownClubs === 1 ? '' : 's'), 'Athletes registered in kumite divisions'],
      ['Categories', String(shownCats.length), finals + ' champion' + (finals === 1 ? '' : 's') + ' decided', 'Divisions by gender, age group and weight'],
      ['Byes', String(D.byes.filter(b => catOk(b) && ringOk(b)).length), 'advanced without a bout', 'First-round places with no opponent'],
      ['Won by Ippon', String(byCount('Ippon')), pct(byCount('Ippon'), n) + '% of decided bouts', 'Bouts whose result was recorded as Ippon'],
      ['Ended early', String(early), byCount('Disqualification') + ' disqualification · ' + byCount('Withdrawal') + ' withdrawal', 'Bouts decided by disqualification or withdrawal']
    ].map(([label, value, sub, tip], i) => ({label, value, sub, tip, bar: css('position:absolute;left:0;top:0;right:0;height:3px;background:' + kc[i])}));

    const wt = [['Points', 'Won on the scoreboard'], ['Ippon', 'Recorded as won by Ippon'], ['Decision', 'Judges decided'], ['Disqualification', 'Opponent disqualified'], ['Withdrawal', 'Opponent withdrew or could not continue']];
    const wMax = Math.max(1, ...wt.map(([k]) => byCount(k)));
    v.winTypes = wt.map(([k, tip], i) => { const c = byCount(k); return {label: k, tip, n: String(c), pct: pct(c, n) + '%', bar: bar(c / wMax * 100, i === 0 ? '#1e2c4f' : i === 1 ? '#2f7a4f' : i === 2 ? '#d8b25f' : '#b4483c')}; });

    const catRows = D.cats.map(c => { const cm = D.matches.filter(m => m.cat === c.key && ringOk(m)), cd = cm.filter(m => m.done);
      const wins = {}; cd.forEach(m => { const wn = m.w === 'a' ? m.A : m.B; wins[wn.id] = wins[wn.id] || {a: wn, n: 0}; wins[wn.id].n++; });
      const lead = Object.values(wins).sort((x, y) => y.n - x.n)[0], fin = cm.find(m => m.round === 'Final');
      return {c, cm, cd,
        status: c.athletes < 2 ? c.athletes + ' athlete · no bouts (walkover)' : !cm.length ? (fr !== 'all' ? 'no matches in filter' : 'no bouts') : fin && fin.done ? 'Champion: ' + (fin.w === 'a' ? fin.A.name : fin.B.name) : cd.length === cm.length ? 'complete' : cd.length ? 'in progress' : 'not started',
        leader: lead ? lead.a.name + ' · ' + lead.n + ' win' + (lead.n > 1 ? 's' : '') : '—'}; });
    v.cats = catRows.filter(x => fc === 'all' || x.c.key === fc).map(x => this.esGender({label: x.c.label, ring: x.c.ring, status: x.status, athletes: x.c.athletes, matches: x.cd.length + ' / ' + x.cm.length,
      prog: css('height:100%;border-radius:2px;background:#d8b25f;width:' + pct(x.cd.length, x.cm.length) + '%'),
      avg: '—', close: '—', closeStyle: css('font-weight:600;padding:2px 8px;border-radius:999px;color:#6b7689;background:#eef1f6'),
      bout: '—', pens: '—', leader: x.leader, open: () => openCat(x.c.key)}));

    const big = shownCats.slice().sort((a, b) => b.athletes - a.athletes)[0];
    const far = catRows.filter(x => x.cm.length && (fc === 'all' || x.c.key === fc)).sort((a, b) => pct(b.cd.length, b.cm.length) - pct(a.cd.length, a.cm.length))[0];
    const hl = [];
    if (big) hl.push(['Largest category', big.key, big.athletes + ' athletes', '#1e2c4f', '#']);
    if (far && far.cd.length) hl.push(['Furthest along', far.c.key, pct(far.cd.length, far.cm.length) + '% of bouts decided', '#2f7a4f', '▲']);
    v.highlights = hl.map(([label, key, stat, col, g]) => ({label, cat: catOf(key).label, stat, glyph: g, open: () => openCat(key),
      icon: css('width:36px;height:36px;flex:none;border-radius:11px;display:grid;place-items:center;font-weight:700;color:' + col + ';background:' + col + '1a;border:1px solid ' + col + '40')}));

    const A = {};
    F.forEach(m => ['a', 'b'].forEach(sd => { const at = sd === 'a' ? m.A : m.B;
      const e = A[at.id] = A[at.id] || {at, w: 0, l: 0, by: {}, ms: []};
      if (m.w === sd) { e.w++; e.by[m.by] = (e.by[m.by] || 0) + 1; } else e.l++;
      e.ms.push(m); }));
    const q = (s.esQ || '').trim().toLowerCase(), sort = s.esSort === 'matches' ? 'matches' : 'win';
    const rows = Object.values(A).filter(e => (fc === 'all' || e.at.cat === fc) && (!q || e.at.name.toLowerCase().includes(q) || e.at.club.toLowerCase().includes(q)))
      .map(e => ({...e, mt: e.w + e.l, wr: (e.w + e.l) ? e.w / (e.w + e.l) : 0}))
      .sort((x, y) => sort === 'matches' ? y.mt - x.mt : (y.wr - x.wr) || (y.w - x.w));
    const page = s.esPage || 1, shown = rows.slice(0, page * 12);
    v.q = s.esQ || ''; v.onQ = e => this.setState({esQ: e.target.value, esPage: 1});
    v.sorts = [['win', 'Win %'], ['matches', 'Matches']].map(([k, l]) => ({label: l, onClick: () => this.setState({esSort: k}),
      style: css('height:32px;padding:0 12px;border-radius:9px;font-family:inherit;font-size:.74rem;cursor:pointer;' + (k === sort ? 'font-weight:600;color:#1e2c4f;background:linear-gradient(100deg,#edcd85,#d0a854);border:0' : 'font-weight:500;color:#5d6880;background:#fff;border:1px solid #d5dae3'))}));
    v.athletes = shown.map(e => ({name: e.at.name, club: e.at.club, cat: catOf(e.at.cat).label, initials: e.at.name.split(' ').map(w => w[0]).slice(0, 2).join(''),
      wl: e.w + '–' + e.l, win: Math.round(e.wr * 100) + '%', pf: '—', pa: '—', avg: '—', tech: '—', open: () => openAth(e.at.id)}));
    v.athNone = rows.length === 0; v.athCount = 'Showing ' + shown.length + ' of ' + rows.length + ' athletes with a decided bout';
    v.athMore = rows.length > shown.length; v.showMore = () => this.setState({esPage: page + 1});

    v.rings = Array.from({length: D.rings}, (_, i) => { const rm = D.matches.filter(m => m.ring === i + 1 && catOk(m)), rd = rm.filter(m => m.done);
      const left = rm.length - rd.length, nx = rm.find(m => m.ready && !m.done);
      const st = !rm.length ? 'Idle' : !left ? 'Finished' : rd.length ? 'Running' : 'Waiting';
      return {n: String(i + 1), cats: [...new Set(rm.map(m => catOf(m.cat).label))].slice(0, 2).join(', ') || 'No matches', done: rd.length + ' / ' + rm.length,
        avg: '—', util: '—', delay: '—', delayStyle: css('display:block;font-size:.9rem;color:#6b7689'), st,
        stStyle: css('font-size:.62rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;padding:3px 8px;border-radius:999px;' + (st === 'Running' ? 'color:#2f7a4f;background:#e8f5ee' : st === 'Waiting' ? 'color:#a8620f;background:#fdf1e0' : 'color:#6b7689;background:#eef1f6')),
        prog: css('height:100%;border-radius:3px;background:#1e2c4f;width:' + pct(rd.length, rm.length) + '%'),
        next: nx ? 'Up next: ' + nx.A.name + ' vs ' + nx.B.name + ' · ' + nx.round : left ? 'Waiting for earlier results' : rm.length ? 'Every bout decided' : ''}; });

    const mrow = m => ({a: m.A ? m.A.name : 'TBD', b: m.B ? m.B.name : 'TBD', score: m.by || '—', meta: m.round + ' · T' + m.ring,
      aStyle: css('overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-weight:' + (m.w === 'a' ? 700 : 400) + ';color:' + (m.w === 'a' ? '#1e2c4f' : '#6b7689')),
      bStyle: css('overflow:hidden;text-overflow:ellipsis;white-space:nowrap;text-align:right;font-weight:' + (m.w === 'b' ? 700 : 400) + ';color:' + (m.w === 'b' ? '#1e2c4f' : '#6b7689'))});
    const md = s.esModal; v.modalOpen = !!md; v.closeModal = () => this.setState({esModal: null}); v.m = {stats: [], matches: []};
    if (md && md.type === 'cat') { const x = catRows.find(r => r.c.key === md.key);
      if (x) v.m = {isAth: false, hasTargets: false, eyebrow: 'Category', title: x.c.label, sub: 'Tatami ' + x.c.ring + ' · ' + x.c.athletes + ' athletes · ' + x.status,
        stats: [['Matches', x.cd.length + ' / ' + x.cm.length], ['Athletes', String(x.c.athletes)], ['Byes', String(D.byes.filter(b => b.cat === x.c.key).length)], ['Leader', x.leader]].map(([label, value]) => ({label, value})),
        listTitle: 'Decided bouts (Aka red · Ao blue)', matches: x.cd.slice().reverse().map(mrow)}; }
    if (md && md.type === 'ath') { const e = A[md.id];
      if (e) { const mt = e.w + e.l, top = Object.entries(e.by).sort((x, y) => y[1] - x[1])[0];
        v.m = {isAth: true, hasTargets: false, initials: e.at.name.split(' ').map(w => w[0]).slice(0, 2).join(''), eyebrow: 'Athlete profile', title: e.at.name, sub: e.at.club + ' · ' + catOf(e.at.cat).label,
          stats: [['Win rate', pct(e.w, mt) + '% (' + e.w + '–' + e.l + ')'], ['Bouts decided', String(mt)], ['Most common win', top ? top[0] : '—'], ['Category', catOf(e.at.cat).label]].map(([label, value]) => ({label, value})),
          listTitle: 'Match log', matches: e.ms.map(mrow)}; } }

    v.exportCsv = () => { const qq = x => '"' + String(x).replace(/"/g, '""') + '"';
      const out = [['Match', 'Category', 'Round', 'Tatami', 'Aka', 'Aka club', 'Ao', 'Ao club', 'Winner', 'Won by']];
      F.forEach(m => out.push([m.no, catOf(m.cat).label, m.round, m.ring, m.A.name, m.A.club, m.B.name, m.B.club, m.w === 'a' ? m.A.name : m.B.name, m.by]));
      const blob = new Blob([out.map(r => r.map(qq).join(',')).join('\n')], {type: 'text/csv'});
      const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = (ev.name || 'event').replace(/[^a-z0-9]+/gi, '-').toLowerCase() + '-kumite-results.csv'; a.click(); };
    return v;
  }
  // A category's gender, spelled out and coloured as on every other tab
  // (Male blue, Female pink); the rest of its name beside it.
  esGender(c) {
    const m = /^(Male|Female)\s+(.*)$/.exec(String(c.label || ''));
    if (!m) return {...c, gender: '', genderStyle: {}, rest: c.label};
    return {...c, gender: m[1], rest: m[2], genderStyle: this.cssToObj('font-weight:700;color:' + (m[1] === 'Female' ? '#db2777' : '#2563eb'))};
  }
  esKey(r) { return (r.gender === 'F' ? 'F' : 'M') + '|' + (r.ageGroup || 'Séniors') + '|' + r.division; }
  // Walks each division's real bracket exactly as buildBracket() does for the
  // Draw tab — same slots, same bout ids ('round-index'), same bye rule — so a
  // bout here is the bout there.
  esReal(ev, regs) {
    const T = window.TakeOneFed || {};
    // An event that runs on the platform has a REAL bracket: its divisions,
    // bouts, mats and recorded results (FederationEventBracket). Read it as it
    // is; the draw below is only for an event that has none to read.
    const B = T.bracket ? T.bracket(ev.id) : null;
    if (B) return {matches: B.matches || [], cats: B.cats || [], byes: B.byes || [], rings: Math.max(1, parseInt(B.mats) || 1), athletes: B.athletes || 0};
    const groups = {};
    regs.forEach(r => { const k = this.esKey(r); (groups[k] = groups[k] || []).push(r); });
    const rings = Math.max(1, Math.min(8, parseInt(ev.mats) || 1));
    const real = x => !!(x && x.name);
    const who = (x, gk) => ({name: x.name, club: x.club || '—', id: x.takeoneId || (x.name + '@' + (x.club || '')), cat: gk});
    const matches = [], cats = [], byes = [];
    let no = 0;
    Object.keys(groups).sort().forEach((gk, gi) => {
      const [g, age, div] = gk.split('|'), list = groups[gk], ring = gi % rings + 1;
      cats.push({key: gk, label: (g === 'F' ? 'Female' : 'Male') + ' ' + age + ' · ' + div.replace(/^Kumite\s*/i, ''), athletes: list.length, ring});
      if (list.length < 2) return;
      const slots = T.drawSlots ? T.drawSlots(gk, list, () => this.fairDraw(list)) : this.fairDraw(list);
      const res = T.divResults ? T.divResults(gk, list) : {};
      let cur = slots, ri = 0;
      while (cur.length >= 2) {
        const rn = {2: 'Final', 4: 'Semi-final', 8: 'Quarter-final'}[cur.length] || 'Round of ' + cur.length, next = [];
        for (let i = 0; i < cur.length; i += 2) {
          const a = cur[i], b = cur[i + 1], id = ri + '-' + (i / 2);
          if ((real(a) && !b) || (real(b) && !a)) { byes.push({cat: gk, ring}); next.push(real(a) ? a : b); continue; }
          const both = real(a) && real(b);
          const r0 = both && res[id] && [a.name, b.name].includes(res[id].winner) ? res[id] : null;
          const w = r0 ? (r0.winner === a.name ? 'a' : 'b') : null;
          matches.push({no: ++no, cat: gk, round: rn, ring, A: real(a) ? who(a, gk) : null, B: real(b) ? who(b, gk) : null,
            w, by: r0 ? (r0.by || 'Points') : null, done: !!r0, ready: both});
          next.push(r0 ? (w === 'a' ? a : b) : 'TBD');
        }
        cur = next.length >= 2 ? next : [];
        ri++;
      }
    });
    return {matches, cats, byes, rings, athletes: regs.length};
  }
