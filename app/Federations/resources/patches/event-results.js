  // The Draw tab's Results and Bouts (owner, 2026-10-17: "a section to show
  // the results, and the bouts list numbered"). Read-only, from the event's
  // own bracket the page already carries (TakeOneFed.bracket ←
  // FederationEventBracket) — nothing here is fetched or written.
  drVals(sel) {
    const B = sel && window.TakeOneFed && window.TakeOneFed.bracket ? window.TakeOneFed.bracket(sel.id) : null;
    const s = this.state;
    const view = s.drView === 'bouts' ? 'bouts' : 'results';
    const flt = ['todo', 'done'].includes(s.drF) ? s.drF : 'all';
    const q = String(s.drQ || '').trim().toLowerCase();
    const css = c => this.cssToObj(c);
    // A person opens their public profile (people.show — it authorises itself).
    const go = url => url ? (e => { if (e && e.stopPropagation) e.stopPropagation(); window.location.assign(url); }) : (() => {});
    const linkCss = url => url ? 'cursor:pointer;' : '';
    // A portrait 3:4 face box (profile pictures are 3:4 on the platform).
    const face = (url, h) => Object.assign(css('width:' + Math.round(h * .75) + 'px;height:' + h + 'px;flex:none;border-radius:' + (h > 40 ? 10 : 7) + 'px;background:#eef1f6;background-size:cover;background-position:center top;box-shadow:0 0 0 1px #e3e7ee'),
      url ? {backgroundImage: 'url("' + String(url).replace(/"/g, '%22') + '")'} : {});
    const empty = {eyebrow: sel && sel.status === 'Completed' ? 'Final results' : 'Live from the mats', has: false, none: true, results: false, bouts: false, tabs: [], divs: [], pending: [], walkovers: [], mats: [], filters: [], dl: {can: false, open: false, items: []}, cover: {can: false}};
    if (!B) return empty;

    const cats = B.cats || [];
    const labelOf = {}, drawOf = {};
    cats.forEach(c => { labelOf[c.key] = c.label; drawOf[c.key] = c.draw || null; });
    const ink = l => /\bfemale\b/i.test(l) ? '#db2777' : /\bmale\b/i.test(l) ? '#2563eb' : '#7c6bf5';

    // ── Results: one card per decided division, places in order ─────────
    const decided = cats.filter(c => (c.podium || []).length);
    // The card (redesigned 2026-10-17, owner: "it doesn't look nice"): a navy
    // band naming the division, the champion featured, then the other places.
    const metal = p => p === 1 ? ['linear-gradient(140deg,#f8e4a0,#d4a940 55%,#b8892a)', 'Champion', '#8a6d20']
      : p === 2 ? ['linear-gradient(140deg,#f4f6fa,#b9c1ce 55%,#97a1b4)', 'Silver', '#5b6579']
      : p === 3 ? ['linear-gradient(140deg,#f0c49f,#c9895b 55%,#a2643a)', 'Bronze', '#8f5a35']
      : ['linear-gradient(140deg,#e9edf3,#b4bccb)', 'Place ' + p, '#6b7689'];
    const sexOf = l => /\bfemale\b/i.test(l) ? ['Female', '#db2777', 'rgba(219,39,119,.18)'] : /\bmale\b/i.test(l) ? ['Male', '#2563eb', 'rgba(37,99,235,.2)'] : null;
    const person = (p, big) => {
      const [grad, word, wordInk] = metal(p.place);
      const d = big ? 40 : 30;
      return {name: p.name, club: p.club || '', hasClub: !!p.club, word, place: String(p.place),
        faceStyle: Object.assign(face(p.photo, big ? 76 : 48), big ? {boxShadow: '0 0 0 2px #fff,0 0 0 4px #d8b25f,0 8px 18px rgba(138,109,32,.25)', borderRadius: '12px'} : {}),
        flag: p.flag || '', hasFlag: !!p.flag, logo: p.logo || '', hasLogo: !!p.logo, kg: p.kg ? p.kg + ' kg' : '', hasKg: !!p.kg,
        onOpen: go(p.profile), title: p.profile ? 'Open ' + p.name + '\u2019s profile' : '',
        rowStyle: css('display:flex;align-items:center;gap:12px;min-width:0;padding:' + (big ? '14px 16px' : '10px 16px') + ';transition:background .15s ease;' + linkCss(p.profile)
          + (big ? 'background:linear-gradient(180deg,#fdf7e6,#fff)' : 'border-top:1px solid #f1f3f7')),
        discStyle: css('width:' + d + 'px;height:' + d + 'px;border-radius:50%;flex:none;display:grid;place-items:center;font-size:' + (big ? '.95rem' : '.76rem') + ';font-weight:900;color:#1e2c4f;background:' + grad + ';box-shadow:inset 0 -2px 0 rgba(0,0,0,.14),inset 0 1px 0 rgba(255,255,255,.6),0 2px 6px rgba(30,44,79,.12)'),
        wordStyle: css('font-size:.6rem;font-weight:800;letter-spacing:.16em;text-transform:uppercase;color:' + wordInk),
        nameStyle: css('font-size:' + (big ? '.98rem' : '.84rem') + ';font-weight:' + (big ? 800 : 700) + ';color:#1e2c4f;line-height:1.25;overflow-wrap:anywhere')};
    };
    const divs = decided
      .filter(c => !q || c.label.toLowerCase().includes(q) || c.podium.some(p => (p.name + ' ' + p.club).toLowerCase().includes(q)))
      .map(c => {
        const sx = sexOf(c.label);
        const champ = c.podium.find(p => p.place === 1);
        return {
          label: c.label, onDraw: go(c.draw), drawTitle: c.draw ? 'Open the draw of ' + c.label : '', hasDraw: !!c.draw,
          headStyle: css('position:relative;overflow:hidden;padding:14px 16px 13px;color:#fff;background:linear-gradient(135deg,#1e2c4f,#2c3f6e);' + linkCss(c.draw)
            + 'border-bottom:3px solid ' + (sx ? sx[1] : '#d8b25f')),
          sex: sx ? sx[0] : '', hasSex: !!sx,
          sexStyle: css('display:inline-flex;align-items:center;height:20px;padding:0 8px;border-radius:999px;font-size:.6rem;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:#fff;'
            + 'background:' + (sx ? sx[2] : 'transparent') + ';box-shadow:inset 0 0 0 1px ' + (sx ? sx[1] : 'transparent')),
          champ: champ ? person(champ, true) : {}, hasChamp: !!champ,
          places: c.podium.filter(p => p !== champ).map(p => person(p, false))
        };
      });
    const pending = cats.filter(c => !(c.podium || []).length && c.athletes >= 2).map(c => ({label: c.label, onDraw: go(c.draw),
      style: css('padding:4px 10px;border-radius:999px;background:#fff;border:1px solid #e4d3a8;font-size:.72rem;color:#1e2c4f;font-family:inherit;' + linkCss(c.draw))}));
    // A division of ONE entrant: no bout, no podium — listed rather than left
    // to vanish from both the results and "still being fought" (QA 2026-10-22).
    const walkovers = cats.filter(c => !(c.podium || []).length && c.athletes === 1).map(c => ({label: c.label, onDraw: go(c.draw),
      who: c.only ? ' \u00b7 ' + c.only : '',
      style: css('padding:4px 10px;border-radius:999px;background:#fff;border:1px solid #d5dbe5;font-size:.72rem;color:#1e2c4f;font-family:inherit;' + linkCss(c.draw))}));

    // ── Bouts: every real bout, numbered, mat by mat ─────────────────────
    const all = (B.matches || []).slice();
    const shown = all.filter(m => (flt === 'all' || (flt === 'done' ? m.done : !m.done))
      && (!q || [labelOf[m.cat], m.A && m.A.name, m.B && m.B.name, m.A && m.A.club, m.B && m.B.club, '#' + m.no]
        .some(t => String(t || '').toLowerCase().includes(q))));
    const byMat = {};
    shown.forEach(m => {
      const k = (m.day ? 'Day ' + m.day + ' · ' : '') + (m.court || ('Mat ' + (m.ring || 1)));
      (byMat[k] = byMat[k] || []).push(m);
    });
    const side = (m, k) => {
      const p = m[k === 'a' ? 'A' : 'B'];
      const won = m.w === k, lost = m.w && m.w !== k;
      const sc = k === 'a' ? m.sa : m.sb;
      const club = p && p.club && p.club !== '—' ? p.club : '';
      return {name: p ? p.name : '—', club, hasClub: !!club, hasFace: !!p, faceStyle: face(p && p.photo, 40),
        flag: (p && p.flag) || '', hasFlag: !!(p && p.flag), logo: (club && p.logo) || '', hasLogo: !!(club && p && p.logo),
        kg: p && p.kg ? p.kg + ' kg' : '', hasKg: !!(p && p.kg),
        score: sc == null ? '' : String(sc), hasScore: !!m.done && sc != null,
        onOpen: go(p && p.profile), title: p && p.profile ? 'Open ' + p.name + '\u2019s profile' : '',
        style: css('display:flex;align-items:center;gap:8px;min-width:0;padding:6px 10px;border-radius:9px;' + linkCss(p && p.profile) + (won ? 'background:#ecf7f0;color:#1e2c4f;font-weight:700' : lost ? 'color:#97a1b4' : 'color:#1e2c4f')),
        dotStyle: css('width:6px;height:22px;border-radius:3px;flex:none;background:' + (k === 'a' ? '#dc2626' : '#2563eb')),
        crown: won ? '✓' : ''};
    };
    const mats = Object.keys(byMat).sort((x, y) => x.localeCompare(y, undefined, {numeric: true})).map(k => ({
      title: k, count: String(byMat[k].length),
      bouts: byMat[k].sort((x, y) => x.no - y.no).map(m => ({
        no: String(m.no), round: m.round || '', division: labelOf[m.cat] || '',
        onDraw: go(drawOf[m.cat]), drawTitle: drawOf[m.cat] ? 'Open the draw of ' + (labelOf[m.cat] || '') : '',
        rowStyle: css('display:grid;grid-template-columns:56px minmax(150px,1fr) minmax(0,1.2fr) minmax(0,1.2fr) auto;align-items:center;gap:10px;padding:10px 12px;border-bottom:1px solid #f1f3f7;' + linkCss(drawOf[m.cat])),
        divStyle: css('font-size:.68rem;font-weight:600;color:' + ink(labelOf[m.cat] || '')),
        a: side(m, 'a'), b: side(m, 'b'),
        how: m.done ? (m.by || 'Decided') : (m.ready ? 'To fight' : 'Waiting'),
        howStyle: css('font-size:.66rem;font-weight:700;padding:3px 9px;border-radius:999px;white-space:nowrap;' + (m.done ? 'background:#ecf7f0;color:#2f7a4f' : m.ready ? 'background:#fdf3e4;color:#b45309' : 'background:#f1f3f7;color:#6b7689'))
      }))
    }));

    const seg = (list, cur, key) => list.map(([k, l]) => ({
      label: l, pressed: cur === k ? 'true' : 'false', onClick: () => this.setState({[key]: k}),
      style: css('height:32px;padding:0 14px;border-radius:8px;border:0;font-family:inherit;font-size:.76rem;cursor:pointer;' + (cur === k ? 'font-weight:700;color:#1e2c4f;background:#fff;box-shadow:0 2px 6px rgba(30,44,79,.12)' : 'font-weight:500;color:#6b7689;background:transparent'))
    }));
    const done = all.filter(m => m.done).length;

    return {
      // A finished event's results are final, not "live" (FederationEvent::lifecycleStatus).
      eyebrow: sel && sel.status === 'Completed' ? 'Final results' : 'Live from the mats',
      dl: this.drDlVals(sel, B), cover: this.drCoverVals(sel),
      has: true, none: false, results: view === 'results', bouts: view === 'bouts',
      tabs: seg([['results', 'Results · ' + decided.length], ['bouts', 'Bouts · ' + all.length]], view, 'drView'),
      filters: seg([['all', 'All'], ['todo', 'To fight · ' + (all.length - done)], ['done', 'Done · ' + done]], flt, 'drF'),
      q: s.drQ || '', onQ: e => this.setState({drQ: e.target.value}),
      divs, hasDivs: divs.length > 0, noDivs: divs.length === 0,
      pending, hasPending: pending.length > 0, pendingCount: String(pending.length),
      walkovers, hasWalkovers: walkovers.length > 0, walkoverCount: String(walkovers.length),
      mats, hasMats: mats.length > 0, noMats: mats.length === 0
    };
  }

  // ⚠️ No literal head/body tags anywhere in this file: FederationApp's page
  // rewrites anchor on them (the 'base' rewrite counts the head tag), so they are split.
  // Download (owner, 2026-10-17: "a download button with 3 options — results,
  // bouts, both"; "make it a real pdf download", in the competition report's
  // style). The PDF is the server's (federations.results-pdf →
  // FederationResultsPdf), which re-checks who asks; the menu only appears
  // when the page was handed that door for this event.
  drDlVals(sel, B) {
    const T = window.TakeOneFed || {}, s = this.state || {};
    const url = sel && sel.id && T.resultsPdf ? T.resultsPdf(sel.id) : null;
    if (!url) return {can: false, open: false, items: []};
    const open = !!s.drDl, busy = s.drBusy || null;
    const get = kind => {
      if (busy) return;
      this.setState({drBusy: kind, drErr: ''});
      fetch(url + '?kind=' + encodeURIComponent(kind), {credentials: 'same-origin', headers: {Accept: 'application/pdf, application/json', 'X-Requested-With': 'XMLHttpRequest'}})
        .then(r => {
          const ct = r.headers.get('Content-Type') || '';
          if (r.ok && ct.indexOf('application/pdf') === 0) {
            const cd = r.headers.get('Content-Disposition') || '', m = cd.match(/filename="([^"]+)"/);
            return r.blob().then(b => {
              const a = document.createElement('a'), href = URL.createObjectURL(b);
              a.href = href; a.download = m ? m[1] : kind + '.pdf';
              document.body.appendChild(a); a.click(); a.remove();
              setTimeout(() => URL.revokeObjectURL(href), 4000);
              this.setState({drBusy: null, drDl: false});
            });
          }
          return r.json().catch(() => ({})).then(j => this.setState({drBusy: null,
            drErr: j.message || (r.status === 429 ? 'Too many downloads \u2014 wait a minute.' : 'The PDF could not be made. Try again.')}));
        })
        .catch(() => this.setState({drBusy: null, drErr: 'Could not reach the server. Try again.'}));
    };
    const item = 'display:flex;flex-direction:column;align-items:flex-start;gap:2px;width:100%;padding:10px 12px;border:0;border-radius:9px;background:#fff;font-family:inherit;text-align:start;cursor:pointer';
    return {
      can: true, open,
      label: busy ? 'Preparing PDF\u2026' : 'Download',
      err: s.drErr || '', hasErr: !!s.drErr,
      items: [['results', 'Results', 'The podium of every finished division'],
              ['bouts', 'Bouts', 'Every bout, numbered, mat by mat'],
              ['both', 'Both', 'Results, then the bout list']]
        .map(([kind, label, hint]) => ({label, hint: busy === kind ? 'Preparing\u2026' : hint,
          onClick: e => { if (e && e.stopPropagation) e.stopPropagation(); get(kind); },
          style: this.cssToObj(item + (busy && busy !== kind ? ';opacity:.45;cursor:default' : ''))})),
      onToggle: e => { if (e && e.stopPropagation) e.stopPropagation(); this.setState({drDl: !open, drErr: ''}); },
      onClose: () => this.setState({drDl: false}),
      stop: e => { if (e && e.stopPropagation) e.stopPropagation(); },
      style: this.cssToObj('display:inline-flex;align-items:center;gap:7px;height:40px;padding:0 14px;border:1px solid ' + (open ? '#caa14f' : '#d5dae3') + ';border-radius:11px;background:' + (open ? '#fdf6e6' : '#fff') + ';font-family:inherit;font-size:.8rem;font-weight:700;color:#1e2c4f;cursor:pointer;white-space:nowrap'),
      caret: open ? '\u25B4' : '\u25BE'
    };
  }

  // The report's cover page (owner, 2026-10-17: "a button to upload cover
  // page … for the pdf download report"). Managers only — the door is handed
  // to nobody else (FederationAppController::live → resultsCover), and the
  // endpoint re-checks. A PDF's first page or a picture; it opens every
  // download of the report (FederationReportCover).
  drCoverVals(sel) {
    const T = window.TakeOneFed || {}, s = this.state || {};
    const C = sel && sel.id && T.resultsCover ? T.resultsCover(sel.id) : null;
    if (!C) return {can: false};
    const open = !!s.drCv, busy = s.drCvBusy || null;
    const done = (j, ok) => {
      if (ok) { C.kind = j.cover || null; if (window.showToast) window.showToast('success', j.message); }
      this.setState({drCvBusy: null, drCvErr: ok ? '' : (j.message || 'That did not work. Try again.')});
    };
    const send = (url, body, kind) => {
      this.setState({drCvBusy: kind, drCvErr: ''});
      fetch(url, {method: 'POST', credentials: 'same-origin', body,
        headers: {Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': T.csrf ? T.csrf() : ''}})
        .then(r => r.json().catch(() => ({})).then(j => done(j, r.ok && j.success)))
        .catch(() => done({message: 'Could not reach the server. Try again.'}, false));
    };
    const has = !!C.kind;
    const btn = 'position:relative;display:inline-flex;align-items:center;justify-content:center;gap:7px;height:38px;padding:0 14px;border-radius:10px;font-family:inherit;font-size:.78rem;font-weight:700;cursor:pointer;white-space:nowrap;';
    return {
      can: true, open, has, none: !has,
      label: busy === 'up' ? 'Uploading\u2026' : 'Cover',
      status: C.kind === 'pdf' ? 'A PDF is set \u2014 its first page opens the report.' : C.kind === 'image' ? 'A picture is set \u2014 it fills the report\u2019s first page.' : 'No cover yet \u2014 the report opens with the clubs and medals.',
      pick: has ? 'Replace cover' : 'Upload cover',
      err: s.drCvErr || '', hasErr: !!s.drCvErr,
      onFile: e => {
        const f = e && e.target && e.target.files && e.target.files[0];
        if (e && e.target) e.target.value = '';
        if (!f || busy) return;
        if (f.size > 10 * 1024 * 1024) { this.setState({drCvErr: 'The cover can be 10 MB at most.'}); return; }
        const fd = new FormData(); fd.append('event', sel.id); fd.append('file', f);
        send(C.upload, fd, 'up');
      },
      onRemove: e => {
        if (e && e.stopPropagation) e.stopPropagation();
        if (busy) return;
        const fd = new FormData(); fd.append('event', sel.id);
        send(C.remove, fd, 'rm');
      },
      onToggle: e => { if (e && e.stopPropagation) e.stopPropagation(); this.setState({drCv: !open, drCvErr: ''}); },
      onClose: () => this.setState({drCv: false}),
      stop: e => { if (e && e.stopPropagation) e.stopPropagation(); },
      dot: this.cssToObj('width:8px;height:8px;border-radius:50%;flex:none;background:' + (has ? '#2f7a4f' : '#c3cad6')),
      style: this.cssToObj('display:inline-flex;align-items:center;gap:7px;height:40px;padding:0 14px;border:1px solid ' + (open ? '#caa14f' : '#d5dae3') + ';border-radius:11px;background:' + (open ? '#fdf6e6' : '#fff') + ';font-family:inherit;font-size:.8rem;font-weight:700;color:#1e2c4f;cursor:pointer;white-space:nowrap'),
      pickStyle: this.cssToObj(btn + 'flex:1;border:0;color:#1e2c4f;background:linear-gradient(100deg,#edcd85,#d0a854);box-shadow:0 6px 16px rgba(202,161,79,.3)' + (busy ? ';opacity:.6;cursor:default' : '')),
      removeStyle: this.cssToObj(btn + 'border:1px solid #f1c7c7;color:#b42318;background:#fff' + (busy ? ';opacity:.6;cursor:default' : '')),
      caret: open ? '\u25B4' : '\u25BE'
    };
  }

  // The whole competition report (owner, 2026-10-17), from the event's banner:
  // front matter, every bracket, statistics & analytics — one PDF
  // (FederationCompetitionReport). Fetched, so a refusal arrives as a message
  // and the dashboard stays where it is; it takes a while, so it says so.
  evReportVals(sel) {
    const T = window.TakeOneFed || {}, s = this.state || {};
    const url = sel && sel.id && T.competitionReport ? T.competitionReport(sel.id) : null;
    if (!url) return {can: false};
    const busy = !!s.rpBusy;
    // The banner has no toast of its own: a small bubble under the button says how it went.
    const say = (type, msg) => {
      clearTimeout(this._rpT);
      this.setState({rpMsg: msg, rpType: type});
      if (type !== 'info') this._rpT = setTimeout(() => this.setState({rpMsg: ''}), 6000);
    };
    const tone = s.rpType === 'error' ? ['#fdecec', '#b42318'] : s.rpType === 'success' ? ['#e8f5ee', '#2f7a4f'] : ['#fdf6e6', '#8a6d20'];
    return {
      can: true,
      hasMsg: !!s.rpMsg, msg: s.rpMsg || '',
      msgStyle: this.cssToObj('position:absolute;right:0;top:48px;z-index:30;width:max-content;max-width:260px;padding:8px 12px;border-radius:10px;font-size:.74rem;font-weight:600;line-height:1.4;white-space:normal;box-shadow:0 10px 26px rgba(11,19,43,.35);background:' + tone[0] + ';color:' + tone[1]),
      label: busy ? 'Preparing report\u2026' : '\u2913 Report',
      title: busy ? 'Building the report \u2014 about half a minute' : 'The whole competition as one PDF report',
      style: this.cssToObj('height:40px;padding:0 18px;font-family:inherit;font-size:.8rem;font-weight:600;color:#edcd85;background:transparent;border:1px solid rgba(216,178,95,.5);border-radius:11px;white-space:nowrap;' + (busy ? 'cursor:progress;opacity:.75' : 'cursor:pointer')),
      onClick: () => {
        if (busy) return;
        this.setState({rpBusy: true});
        say('info', 'Building the competition report \u2014 about half a minute.');
        fetch(url, {credentials: 'same-origin', headers: {Accept: 'application/pdf, application/json', 'X-Requested-With': 'XMLHttpRequest'}})
          .then(r => {
            const ct = r.headers.get('Content-Type') || '';
            if (r.ok && ct.indexOf('application/pdf') === 0) {
              const m = (r.headers.get('Content-Disposition') || '').match(/filename="([^"]+)"/);
              return r.blob().then(b => {
                const a = document.createElement('a'), href = URL.createObjectURL(b);
                a.href = href; a.download = m ? m[1] : 'competition-report.pdf';
                document.body.appendChild(a); a.click(); a.remove();
                setTimeout(() => URL.revokeObjectURL(href), 10000);
                say('success', 'Competition report downloaded.');
              });
            }
            return r.json().catch(() => ({})).then(j => say('error', j.message || (r.status === 429 ? 'Too many downloads \u2014 wait a minute.' : 'The report could not be made. Try again.')));
          })
          .catch(() => say('error', 'Could not reach the server. Try again.'))
          .finally(() => this.setState({rpBusy: false}));
      }
    };
  }

