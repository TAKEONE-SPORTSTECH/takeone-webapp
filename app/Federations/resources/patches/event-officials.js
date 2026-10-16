  // The event's Officials tab (FederationApp: 'offs-*' rewrites). Draws the
  // event's OWN appointments — the same people the readiness checklist counts
  // and the event's officiating sheet lists (FederationEventOfficials), grouped
  // by job, each with their club. A viewer who may run the event appoints,
  // changes and removes them right here (evOfficialsEditor) — through the
  // officiating endpoints themselves, which re-check every write.
  // The officials' handbook on merging small divisions (MergeHandbookPdf) —
  // the same PDF the Divisions tab's merge window offers, from the door the
  // platform hands an organiser. Fetched and handed over as a download.
  offHandbook(url) {
    if (!url || (this.state || {}).offPdfBusy) return;
    this.setState({offPdfBusy: true});
    const done = msg => this.setState({offPdfBusy: false, offFlash: msg ? {ev: (this.state || {}).offEv, text: msg} : (this.state || {}).offFlash});
    fetch(url, {credentials: 'same-origin', headers: {Accept: 'application/pdf, application/json', 'X-Requested-With': 'XMLHttpRequest'}})
      .then(r => {
        const ct = r.headers.get('Content-Type') || '';
        if (r.ok && ct.indexOf('application/pdf') === 0) {
          const m = (r.headers.get('Content-Disposition') || '').match(/filename="([^"]+)"/);
          return r.blob().then(b => {
            const a = document.createElement('a'), href = URL.createObjectURL(b);
            a.href = href; a.download = m ? m[1] : 'merging-handbook.pdf';
            document.body.appendChild(a); a.click(); a.remove();
            setTimeout(() => URL.revokeObjectURL(href), 4000);
            done('');
          });
        }
        return r.json().catch(() => ({})).then(j => done(j.message || (r.status === 429 ? 'Too many downloads — wait a minute.' : 'The PDF could not be made. Try again.')));
      })
      .catch(() => done('Could not reach the server. Try again.'));
  }

  evOfficialsVals(sel) {
    const T = window.TakeOneFed || {};
    const O = sel && sel.id && T.eventOfficials ? T.eventOfficials(sel.id) : null;
    const css = x => this.cssToObj(x);
    if (!O) {
      const none = {arrow: '', ariaSort: 'none', onClick: () => {}, style: {}};
      return {genders: [], count: '', anyFilter: false, onReset: () => {}, total: '0', chips: [], groups: [], rows: [], hasRows: false, sort: {name: none, job: none, role: none, gender: none, nat: none, club: none}, noMatch: false, query: '', onQuery: () => {}, onAll: () => {}, allStyle: {}, allCountStyle: {}, allPressed: 'true', empty: true, canManage: false, onAdd: () => {}, ed: {open: false}, flash: '', hasFlash: false, hasHandbook: false, handbookLabel: '', onHandbook: () => {},
        emptyText: 'This event is not running on the platform yet, so it has no officials of its own.'};
    }
    const tile = p => Object.assign(css('width:48px;height:64px;border-radius:10px;flex:none;display:grid;place-items:center;font-size:.8rem;font-weight:700;background-size:cover;background-position:center;'
        + (p.photo ? 'background-color:#eef1f6;color:transparent' : (p.gender === 'F' ? 'background:#fce7f3;color:#db2777' : 'background:#dbeafe;color:#2563eb'))),
      p.photo ? {backgroundImage: 'url("' + String(p.photo).replace(/["\\]/g, '') + '")'} : {});
    const logo = u => Object.assign(css('width:16px;height:16px;flex:none;background-size:contain;background-repeat:no-repeat;background-position:center'),
      {backgroundImage: 'url("' + String(u).replace(/["\\]/g, '') + '")'});
    const pill = job => css('display:inline-block;padding:3px 10px;border-radius:999px;font-size:.62rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;white-space:nowrap;'
      + (/manager/i.test(job) ? 'color:#1e2c4f;background:#eef1f6' : 'color:#8a6d20;background:rgba(216,178,95,.18)'));
    const groups = (O.groups || []).map(g => ({label: g.label, count: String(g.count),
      people: (g.people || []).map(p => ({name: p.name, role: p.role, initials: p.photo ? '' : p.initials,
        avStyle: tile(p), roleStyle: pill(g.label), hasLogo: !!p.clubLogo, logoStyle: p.clubLogo ? logo(p.clubLogo) : {},
        clubShown: p.club || 'Independent'}))}));
    // One table, everyone in it sorted by name.
    const M = O.manage || null;
    const perJob = (O.groups || []).flatMap(g => (g.people || []).map(p => ({person: p.person, name: p.name, role: p.role, job: g.label,
      editable: !!(M && p.id), plain: !(M && p.id), onOpen: () => { if (M && p.id) this.offEdOpen(sel.id, {mode: 'edit', person: p}); },
      initials: p.photo ? '' : p.initials, avStyle: Object.assign(tile(p), css('width:36px;height:48px;border-radius:8px;font-size:.72rem')),
      gender: p.gender === 'F' ? 'Female' : 'Male', genderStyle: css('font-weight:700;color:' + (p.gender === 'F' ? '#db2777' : '#2563eb')),
      genderColStyle: css('font-size:.76rem;font-weight:600;color:' + (p.gender === 'F' ? '#db2777' : '#2563eb')),
      roleStyle: pill(g.label), hasLogo: !!p.clubLogo, logoStyle: p.clubLogo ? logo(p.clubLogo) : {}, clubShown: p.club || 'Independent',
      // Where they are from: the flag and the country, as the Participants table shows it.
      nat: p.natName || '', hasNat: !!p.natName, natShown: p.natName || '\u2014', hasFlag: !!p.natFlag,
      flagStyle: p.natFlag ? Object.assign(css('width:16px;height:12px;flex:none;border-radius:2px;box-shadow:0 0 0 1px #d5dae3;background-size:cover;background-position:center'), {backgroundImage: 'url("' + String(p.natFlag).replace(/["\\]/g, '') + '")'}) : {}})));
    // One row per PERSON (owner, 2026-10-15): somebody who is Referee 1 and
    // Scorekeeper is one row with both jobs, not two rows with the same name.
    // Opening it opens their window, which lists and edits every job they hold.
    const byPerson = new Map();
    perJob.forEach(r => {
      const k = r.person || ('row-' + byPerson.size);
      const had = byPerson.get(k);
      if (!had) { byPerson.set(k, Object.assign({}, r, {jobs: [r.job], roles: [r.role]})); return; }
      if (!had.jobs.includes(r.job)) had.jobs.push(r.job);
      if (!had.roles.includes(r.role)) had.roles.push(r.role);
    });
    const rows = [...byPerson.values()].map(r => Object.assign(r, {
      job: r.jobs.join(' · '), role: r.roles.join(' · '),
      jobPills: r.jobs.map(j => ({label: j, style: pill(j)}))}));
    // The chips filter by job, the search box by name, role or club. Both
    // belong to the event they were set on, so opening another starts clean.
    const st = this.state || {};
    const mine = st.offEv === sel.id;
    const job = mine ? (st.offJob || '') : '';
    const q = mine ? String(st.offQ || '') : '';
    const needle = q.trim().toLowerCase();
    const set = patch => this.setState({offEv: sel.id, offJob: job, offQ: q, offG: mine ? st.offG : 'all', offSort: mine ? st.offSort : undefined, offSortDir: mine ? st.offSortDir : undefined, ...patch});
    // The Participants table's own filter chips, so the two tabs read as one.
    const chipCss = (on, color) => css('display:inline-flex;align-items:center;gap:6px;height:32px;padding:0 14px;border-radius:999px;font-family:inherit;font-size:.74rem;font-weight:600;cursor:pointer;white-space:nowrap;transition:background .15s,border-color .15s;'
      + (on ? 'background:#fdf6e6;border:1px solid #caa14f;box-shadow:0 2px 8px rgba(202,161,79,.22);' : 'background:#fff;border:1px solid #d5dae3;') + 'color:' + (color || (on ? '#8a6d20' : '#5d6880')));
    const countCss = on => css('font-weight:700;color:' + (on ? '#8a6d20' : '#97a1b4'));
    const gf = mine ? (st.offG || 'all') : 'all';
    const allOn = job === '';
    // Sortable columns, the Participants table's way: tap a heading, tap again to reverse.
    const by = mine && st.offSort ? st.offSort : 'name', dir = mine && st.offSortDir ? st.offSortDir : 1;
    const keys = {name: r => r.name, job: r => r.job, role: r => r.role, gender: r => r.gender, nat: r => r.nat, club: r => r.clubShown};
    const shown = rows.filter(r => (!job || r.jobs.includes(job)) && (gf === 'all' || (gf === 'F' ? r.gender === 'Female' : r.gender === 'Male'))
      && (!needle || [r.name, r.role, r.job, r.clubShown, r.nat].some(v => String(v || '').toLowerCase().includes(needle))))
      .sort((a, b) => dir * String(keys[by](a) || '').localeCompare(String(keys[by](b) || ''), undefined, {numeric: true, sensitivity: 'base'})
        || String(a.name).localeCompare(String(b.name), undefined, {sensitivity: 'base'}));
    const sort = {};
    Object.keys(keys).forEach(k => {
      const on = by === k;
      sort[k] = {arrow: on ? (dir > 0 ? '▲' : '▼') : '', ariaSort: on ? (dir > 0 ? 'ascending' : 'descending') : 'none',
        onClick: () => set({offSort: k, offSortDir: on ? -dir : 1}),
        style: css('display:inline-flex;align-items:center;gap:4px;padding:0;border:0;background:none;font-family:inherit;font-size:.6rem;letter-spacing:.1em;text-transform:uppercase;cursor:pointer;text-align:start;color:' + (on ? '#1e2c4f;font-weight:700' : '#97a1b4'))};
    });
    return {total: String(O.total || 0), chips: groups.map(g => ({label: g.label, count: g.count, hint: 'Show only ' + g.label.toLowerCase(), pressed: String(job === g.label),
        style: chipCss(job === g.label), countStyle: countCss(job === g.label), onClick: () => set({offJob: job === g.label ? '' : g.label})})),
      allStyle: chipCss(allOn), allCountStyle: countCss(allOn), allPressed: String(allOn), onAll: () => set({offJob: ''}),
      query: q, onQuery: e => set({offQ: e.target.value}),
      groups, rows: shown, hasRows: shown.length > 0, sort,
      genders: [['all', 'All', ''], ['M', 'Male', '#2563eb'], ['F', 'Female', '#db2777']].map(([id, label, c]) => ({key: id, label, pressed: String(gf === id), style: chipCss(gf === id, c), onClick: () => set({offG: id})})),
      count: shown.length + ' of ' + rows.length,
      anyFilter: !!job || gf !== 'all' || !!needle || !!(mine && st.offSort),
      onReset: () => set({offJob: '', offG: 'all', offQ: '', offSort: undefined, offSortDir: undefined}), noMatch: rows.length > 0 && shown.length === 0,
      canManage: !!M, onAdd: () => this.offEdOpen(sel.id, {mode: 'add'}), ed: this.evOfficialsEditor(sel, M), design: this.designVals(sel, 'officials'), pdf: this.offPdfVals(sel),
      flash: st.offFlash && st.offFlash.ev === sel.id ? st.offFlash.text : '', hasFlash: !!(st.offFlash && st.offFlash.ev === sel.id),
      empty: !groups.length, emptyText: 'No officials are appointed to this event yet.',
      ...(() => { const D = T.divisionDoors ? T.divisionDoors(sel.id) : null, url = D && D.mergeHandbook;
        return {hasHandbook: !!url, handbookLabel: st.offPdfBusy ? 'Preparing…' : 'Merging handbook', onHandbook: () => this.offHandbook(url)}; })(),
    };
  }

  // Participants → "Download" also hands out the registration & consent form:
  // every participant's already filled in, or the blank one (RegistrationFormPdf).
  regFormItems() {
    const T = window.TakeOneFed || {};
    const D = T.divisionDoors ? T.divisionDoors((this.state || {}).selectedId) : null;
    return D && D.regForm
      ? [['form_filled', 'Consent Forms \u2014 Filled In', 'One per athlete, ready to sign'], ['form_blank', 'Consent Form \u2014 Blank', 'To hand out and fill in by hand']]
      : [];
  }
  regPdfUrl(url, kind) {
    const T = window.TakeOneFed || {};
    const D = T.divisionDoors ? T.divisionDoors((this.state || {}).selectedId) : null;
    if (kind === 'form_blank' && D && D.regForm) return D.regForm;
    if (kind === 'form_filled' && D && D.regForm) return D.regForm + '?fill=all';
    return url + '?kind=' + encodeURIComponent(kind);
  }

  // Officials → "Download": the officials as a PDF, made by the platform event's
  // own endpoint (OfficialsPdf), which re-checks who asks — the Participants
  // tab's "Download", for the people running the event.
  offPdfVals(sel) {
    const T = window.TakeOneFed || {}, s = this.state || {};
    const O = sel && sel.id && T.eventOfficials ? T.eventOfficials(sel.id) : null;
    const url = O && O.manage ? O.manage.pdf : null;
    if (!url) return {can: false, open: false, items: []};
    const open = s.ddOpen === 'offpdf', busy = s.opBusy || null;
    const get = kind => {
      if (busy) return;
      this.setState({opBusy: kind, opErr: ''});
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
              this.setState({opBusy: null, ddOpen: null});
            });
          }
          return r.json().catch(() => ({})).then(j => this.setState({opBusy: null,
            opErr: j.message || (r.status === 429 ? 'Too many downloads \u2014 wait a minute.' : 'The PDF could not be made. Try again.')}));
        })
        .catch(() => this.setState({opBusy: null, opErr: 'Could not reach the server. Try again.'}));
    };
    const row = 'width:100%;display:flex;align-items:center;gap:10px;padding:9px 10px;border:0;border-radius:9px;background:transparent;font-family:inherit;font-size:.8rem;font-weight:600;color:#1e2c4f;text-align:left;cursor:pointer';
    return {
      can: true, open,
      label: busy ? 'Preparing PDF\u2026' : 'Download',
      toggle: e => { e.stopPropagation(); this.setState({ddOpen: open ? null : 'offpdf', opErr: ''}); },
      stop: e => e.stopPropagation(),
      err: s.opErr || '', hasErr: !!s.opErr,
      items: [['by_job', 'Officials by Job', 'One block per job, to share or pin up'], ['signin', 'Sign-In Sheet', 'Tick, sign and time, for the desk'],
        ['by_club', 'Officials by Club', 'One block per club'], ['all', 'All Officials A\u2013Z', 'Everyone once, alphabetical']]
        .map(([kind, title, sub]) => ({title, sub: busy === kind ? 'Preparing\u2026' : sub,
          style: this.cssToObj(row + (busy && busy !== kind ? ';opacity:.45;cursor:default' : '')),
          onClick: e => { e.stopPropagation(); get(kind); }}))
    };
  }

  // The "Printables" menu (owner, 2026-10-15): what can be designed and printed
  // for the people on this tab. Participants: their badges and the participation
  // certificate. Officials: their badges and a thank-you certificate. Each opens
  // the platform's own design room (App\Certificates) on that design, and comes
  // back here through `?back=`.
  designVals(sel, who) {
    const T = window.TakeOneFed || {};
    const O = sel && sel.id && T.eventOfficials ? T.eventOfficials(sel.id) : null;
    const M = O && O.manage ? O.manage : {};
    const st = this.state || {};
    const back = () => encodeURIComponent(window.location.pathname + window.location.search);
    const go = (url, type) => () => { this.setState({printOpen: null}); if (url) window.location.assign(url + '?' + (type ? 'type=' + type + '&' : '') + 'back=' + back()); };
    const items = (who === 'officials'
      ? [[M.designBadges, null, 'Officials\u2019 badges', 'A6 identity cards with a QR check'],
         [M.designCertificates, 'official_thanks', 'Officials\u2019 certificate', 'Thanks for helping make the event a success']]
      : [[M.designParticipantBadges, null, 'Participant badges', 'A6 cards for every competitor'],
         [M.designCertificates, 'participation', 'Participation certificate', 'For everyone who competed']])
      .filter(([url]) => !!url)
      .map(([url, type, label, hint]) => ({label, hint, onClick: go(url, type),
        style: this.cssToObj('display:flex;flex-direction:column;align-items:flex-start;gap:2px;width:100%;padding:10px 12px;border:0;border-radius:9px;background:#fff;font-family:inherit;text-align:start;cursor:pointer')}));
    const open = st.printOpen === who;
    return {
      any: items.length > 0, items, open,
      onToggle: e => { if (e && e.stopPropagation) e.stopPropagation(); this.setState({printOpen: open ? null : who}); },
      onClose: () => this.setState({printOpen: null}),
      stop: e => { if (e && e.stopPropagation) e.stopPropagation(); },
      style: this.cssToObj('height:40px;padding:0 14px;border:1px solid ' + (open ? '#caa14f' : '#d5dae3') + ';border-radius:11px;font-family:inherit;font-size:.78rem;font-weight:700;color:#1e2c4f;background:' + (open ? '#fdf6e6' : '#fff') + ';cursor:pointer;display:inline-flex;align-items:center;gap:8px;white-space:nowrap'),
      caret: open ? '\u25B4' : '\u25BE'
    };
  }

  // ── Officials editor ────────────────────────────────────────────────────
  // Appointing, changing and removing an official happens in a platform page
  // in a same-origin frame (federations::official.edit), built from the
  // platform's own controls — like the athlete editor. This side only opens
  // it, closes it, and re-reads the dashboard after a save.
  offEdOpen(ev, opts) {
    const T = window.TakeOneFed || {};
    const base = T.officialFrame ? T.officialFrame() : null;
    if (!base) return;
    window.__fedOffComp = this;
    if (!window.__fedOffMsg) {
      window.__fedOffMsg = (e) => {
        const c = window.__fedOffComp;
        if (e.origin !== window.location.origin || !e.data || !c) return;
        if (e.data.type === 'fed-official:close') c.setState({offEd: null});
        if (e.data.type === 'fed-official:saved') c.offEdDone(e.data);
      };
      window.addEventListener('message', window.__fedOffMsg);
    }
    const p = opts.person || null;
    const O = T.eventOfficials ? T.eventOfficials(ev) : null;
    if (!O || !O.manage || !O.manage.event) return;
    const src = base + '?event=' + encodeURIComponent(O.manage.event) + (p && p.id ? '&official=' + encodeURIComponent(p.id) : '');
    this.setState({offEd: {ev, src}});
  }
  offEdDone(d) {
    const T = window.TakeOneFed || {};
    const ev = (this.state.offEd || {}).ev;
    this.setState({offEd: null, offFlash: {ev, text: d.message || 'Saved.'}});
    clearTimeout(this._offFlashT);
    this._offFlashT = setTimeout(() => this.setState({offFlash: null}), 4000);
    // Re-read the dashboard's state: the tab, the checklist and the counts.
    if (T.refresh) T.refresh();
  }
  evOfficialsEditor(sel, M) {
    const s = (this.state || {}).offEd;
    if (!M || !s || s.ev !== sel.id || !s.src) return {open: false};
    return {open: true, src: s.src, stop: e => e.stopPropagation(), onClose: () => this.setState({offEd: null})};
  }
