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
    const perJob = (O.groups || []).flatMap(g => (g.people || []).map(p => ({person: p.person, holder: p.holder || '', name: p.name, role: p.role, job: g.label,
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
    const set = patch => this.setState({offEv: sel.id, offJob: job, offQ: q, offG: mine ? st.offG : 'all', offSort: mine ? st.offSort : undefined, offSortDir: mine ? st.offSortDir : undefined, offDd: null, offSel: mine ? (st.offSel || {}) : {}, ...patch});
    // Ticked officials (holder => true), to issue or print a chosen few (owner, 2026-10-20).
    const picked = mine ? (st.offSel || {}) : {};
    const tick = on => css('width:18px;height:18px;border-radius:5px;cursor:pointer;display:grid;place-items:center;font-size:.66rem;font-weight:700;flex:none;padding:0;font-family:inherit;'
      + (on ? 'background:linear-gradient(100deg,#edcd85,#d0a854);color:#1e2c4f;border:0;box-shadow:0 2px 6px rgba(202,161,79,.3)' : 'background:#fff;color:transparent;border:1.5px solid #c3cad6'));
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
    // One dropdown: a pill showing the choice, opening a list of choices with a tick.
    const ddOpen = mine ? (st.offDd || null) : null;
    const dropdown = (name, current, count, on, options, value, pick) => ({
      label: current, hasCount: count != null, count: String(count != null ? count : ''), open: ddOpen === name, caret: ddOpen === name ? '\u25B4' : '\u25BE',
      style: css('display:inline-flex;align-items:center;gap:7px;height:32px;padding:0 11px 0 14px;border-radius:999px;font-family:inherit;font-size:.74rem;font-weight:600;cursor:pointer;white-space:nowrap;transition:background .15s,border-color .15s;'
        + (on ? 'background:#fdf6e6;border:1px solid #caa14f;box-shadow:0 2px 8px rgba(202,161,79,.22);color:#8a6d20' : 'background:#fff;border:1px solid #d5dae3;color:#5d6880')),
      countStyle: countCss(on),
      onToggle: e => { if (e && e.stopPropagation) e.stopPropagation(); set({offDd: ddOpen === name ? null : name}); },
      onClose: () => set({offDd: null}),
      stop: e => { if (e && e.stopPropagation) e.stopPropagation(); },
      items: options.map(o => ({label: o.label, hasCount: o.count != null, count: String(o.count != null ? o.count : ''), pressed: String(value === o.key), checked: value === o.key ? '\u2713' : '',
        style: css('width:100%;display:flex;align-items:center;gap:10px;padding:9px 12px;border:0;border-radius:10px;font-family:inherit;font-size:.78rem;cursor:pointer;text-align:start;'
          + (value === o.key ? 'background:#fdf6e6;color:#8a6d20;font-weight:700' : 'background:transparent;color:' + (o.color || '#1e2c4f') + ';font-weight:600')),
        countStyle: css('margin-inline-start:auto;font-weight:700;font-size:.72rem;color:' + (value === o.key ? '#8a6d20' : '#97a1b4')),
        onClick: () => pick(o.key)})),
    });
    shown.forEach(r => Object.assign(r, {canPick: !!r.holder, checked: !!picked[r.holder], checkMark: picked[r.holder] ? '\u2713' : '', checkStyle: tick(!!picked[r.holder]),
      onToggleSel: e => { if (e && e.stopPropagation) e.stopPropagation(); if (r.holder) set({offSel: {...picked, [r.holder]: !picked[r.holder]}}); }}));
    const pickable = shown.filter(r => r.holder), allPicked = pickable.length > 0 && pickable.every(r => picked[r.holder]);
    const pickedCount = Object.keys(picked).filter(k => picked[k]).length;
    return {canPick: pickable.length > 0, allChecked: allPicked, allCheckMark: allPicked ? '\u2713' : '', allCheckStyle: tick(allPicked), pickedCount,
      onToggleAll: () => { const next = {...picked}; pickable.forEach(r => { next[r.holder] = !allPicked; }); set({offSel: next}); },
      onClearSel: () => set({offSel: {}}),
      total: String(O.total || 0), chips: groups.map(g => ({label: g.label, count: g.count, hint: 'Show only ' + g.label.toLowerCase(), pressed: String(job === g.label),
        style: chipCss(job === g.label), countStyle: countCss(job === g.label), onClick: () => set({offJob: job === g.label ? '' : g.label})})),
      allStyle: chipCss(allOn), allCountStyle: countCss(allOn), allPressed: String(allOn), onAll: () => set({offJob: ''}),
      query: q, onQuery: e => set({offQ: e.target.value}),
      groups, rows: shown, hasRows: shown.length > 0, sort,
      genders: [['all', 'All', ''], ['M', 'Male', '#2563eb'], ['F', 'Female', '#db2777']].map(([id, label, c]) => ({key: id, label, pressed: String(gf === id), style: chipCss(gf === id, c), onClick: () => set({offG: id})})),
      // Job and gender are dropdowns, not rows of chips (owner, 2026-10-20) —
      // the Divisions tab's Status dropdown, so the dashboard reads as one.
      jobDd: dropdown('job', job || 'All', job ? groups.find(g => g.label === job)?.count : (O.total || 0), !!job,
        [{key: '', label: 'All', count: O.total || 0}].concat(groups.map(g => ({key: g.label, label: g.label, count: g.count}))),
        job, k => set({offJob: k, offDd: null})),
      genDd: dropdown('gender', ({all: 'All', M: 'Male', F: 'Female'})[gf] || 'All', null, gf !== 'all',
        [{key: 'all', label: 'All'}, {key: 'M', label: 'Male', color: '#2563eb'}, {key: 'F', label: 'Female', color: '#db2777'}],
        gf, k => set({offG: k, offDd: null})),
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
    const C = M.certificates || null;
    const st = this.state || {};
    const back = () => encodeURIComponent(window.location.pathname + window.location.search);
    const go = (url, type) => () => { this.setState({printOpen: null}); if (url) window.location.assign(url + '?' + (type ? 'type=' + type + '&' : '') + 'back=' + back()); };
    // Each tab's own printables (owner, 2026-10-20): officials — badges and
    // certificates, general and per job; participants — badge, participation,
    // podium; clubs — the club certificate of thanks. Certificates are issued
    // and printed here through the certificates console's own endpoints.
    const P = M.badgePdfs || {};
    // [design url, certificate type (issue + PDF) | null, label, hint, badge PDF {bundle, download} | null]
    const rows = who === 'officials'
      ? [[M.designBadges, null, 'Officials\u2019 badges', 'A6 identity cards \u2014 the general design', P.officials],
         [M.designCertificates, 'official_thanks', 'Officials\u2019 certificate', 'Thanks for helping make the event a success \u2014 the general design']]
      : who === 'clubs'
        ? [[M.designCertificates, 'club_thanks', 'Club\u2019s certificate of thanks', 'One for every club that brought athletes']]
        : [[M.designParticipantBadges, null, 'Participant badges', 'A6 cards for every competitor', P.participants],
           [M.designCertificates, 'participation', 'Participation certificate', 'For everyone who competed in a finished division'],
           [M.designCertificates, 'podium', 'Podium certificate', 'Gold, silver and bronze of each finished division']];
    const busy = st.printBusy || null, notes = st.printNotes || {};
    // Every message is a toast in the middle of the screen (owner, 2026-10-20) — never
    // text beside the buttons pushing them about. Progress stays up; a result fades.
    const note = (type, text, tone) => {
      clearTimeout(window.__fedPrintToastT);
      this.setState({printNotes: {...((this.state || {}).printNotes || {}), [type]: {text, tone}}, printToast: {text, tone}});
      if (tone !== 'busy') window.__fedPrintToastT = setTimeout(() => this.setState({printToast: null}), tone === 'err' ? 6000 : 3500);
    };
    const call = (method, url, body) => fetch(url, {method, credentials: 'same-origin',
        headers: {Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': T.csrf ? T.csrf() : ((document.querySelector('meta[name="csrf-token"]') || {}).content || '')},
        body: body ? JSON.stringify(body) : undefined})
      .then(r => r.json().catch(() => ({})).then(j => {
        if (!r.ok || j.success === false) throw new Error(j.message || (r.status === 429 ? 'Too many requests — wait a minute and try again.' : 'That did not work. Try again.'));
        return j;
      }));
    // Create every certificate of this kind that is due, and issue them at once — no drafts.
    const issue = type => async () => {
      if (busy || !C) return;
      this.setState({printBusy: type}); note(type, 'Issuing…', 'busy');
      try {
        await call('POST', C.generate, {type});
        let issued = 0;
        for (let guard = 0; guard < 400; guard++) {
          const j = await call('POST', C.approve, {all: true, type});
          issued += j.approved || 0;
          if (!j.remaining || !j.approved) break;
          note(type, 'Issuing… ' + issued + ' done', 'busy');
        }
        note(type, issued ? issued + ' issued. Download the PDF when you are ready.' : 'Nothing new to issue — every certificate that is due has been issued.', 'ok');
      } catch (e) { note(type, e.message, 'err'); }
      this.setState({printBusy: null});
    };
    // Every issued certificate of this kind in one PDF, built in the background, then downloaded.
    const pdf = (type, urls) => async () => {
      const U = urls || (C ? {bundle: C.bundle.replace('xtypex', type), download: C.download.replace('xtypex', type)} : null);
      if (busy || !U) return;
      this.setState({printBusy: type}); note(type, 'Making the PDF…', 'busy');
      try {
        let j = await call('POST', U.bundle), restarts = 0;
        for (let guard = 0; guard < 600; guard++) {
          // What the file holds changed while it was being made (more certificates
          // issued, a design saved): the half-made one no longer counts — start again.
          if (j.state === 'idle' && guard > 0 && restarts < 3) { restarts++; j = await call('POST', U.bundle); }
          if (j.state !== 'building') break;
          note(type, 'Making the PDF… ' + (j.total ? j.done + ' of ' + j.total : ''), 'busy');
          await new Promise(res => setTimeout(res, 3000));
          j = await call('GET', U.bundle);
        }
        if (j.state === 'ready') {
          const a = document.createElement('a');
          a.href = U.download; a.download = '';
          document.body.appendChild(a); a.click(); a.remove();
          note(type, 'Downloaded' + (j.size ? ' · ' + (j.size / 1048576).toFixed(1) + ' MB' : '') + '.', 'ok');
        } else if (j.state === 'failed') {
          note(type, 'The PDF could not be made. Try again.', 'err');
        } else {
          note(type, urls ? 'Nobody to print a badge for yet.' : 'None issued yet — press Issue first.', 'err');
        }
      } catch (e) { note(type, e.message, 'err'); }
      this.setState({printBusy: null});
    };
    // A chosen few (ticked in the tab's list): issue to them only, or one PDF of theirs.
    const issueTo = (type, holders) => async () => {
      if (busy || !C || !C.issueSelected || !holders.length) return;
      this.setState({printBusy: type}); note(type, 'Issuing to ' + holders.length + ' chosen\u2026', 'busy');
      try { const j = await call('POST', C.issueSelected, {type, holders}); note(type, j.message || 'Issued.', 'ok'); }
      catch (e) { note(type, e.message, 'err'); }
      this.setState({printBusy: null});
    };
    const pdfOf = (type, holders) => async () => {
      if (busy || !C || !C.selection || !holders.length) return;
      let token;
      try { token = (await call('POST', C.selection, {type, holders})).type; } catch (e) { note(type, e.message, 'err'); return; }
      return pdf(token, {bundle: C.bundle.replace('xtypex', token), download: C.download.replace('xtypex', token)})();
    };
    const chosenOf = map => Object.keys(map || {}).filter(k => map[k]);
    // The ticked officials' badges (owner, 2026-10-29): badges keep no certificate
    // records, so the chosen set is remembered by holder and printed as its own bundle.
    const badgeSel = (cfg, holders) => async () => {
      if (busy || !cfg || !cfg.selection || !cfg.bundle || !holders.length) return;
      this.setState({printBusy: 'badge-sel'}); note('badge-sel', 'Choosing\u2026', 'busy');
      let token;
      try { token = (await call('POST', cfg.selection, {type: cfg.type, holders})).type; }
      catch (e) { note('badge-sel', e.message, 'err'); this.setState({printBusy: null}); return; }
      this.setState({printBusy: null});
      return pdf(token, {bundle: cfg.bundle.replace('xtypex', token), download: cfg.download.replace('xtypex', token)})();
    };
    const offBadgeCfg = {selection: M.badgeSelection, bundle: M.badgeBundle, download: M.badgeDownload, type: 'official_badge'};
    const regBadgeCfg = {selection: M.participantBadgeSelection, bundle: M.participantBadgeBundle, download: M.participantBadgeDownload, type: 'participant_badge'};
    // An action is a row of the printable's submenu (owner, 2026-10-29), not a button
    // chip: its icon says what it does — design, issue or download.
    const chip = (label, onClick, primary, off, sub) => ({label, onClick, disabled: off, sub: sub || '',
      icon: /^Issu/.test(label) ? '✓' : /^(Download|Making)/.test(label) ? '⇣' : '✎',
      style: this.cssToObj('display:flex;align-items:center;gap:10px;width:100%;padding:9px 10px;border:0;border-radius:9px;background:transparent;font-family:inherit;text-align:left;box-sizing:border-box;cursor:' + (off ? 'default' : 'pointer') + ';opacity:' + (off ? '.5' : '1'))});
    const rowCss = this.cssToObj('display:flex;flex-direction:column;align-items:flex-start;gap:2px;width:100%;padding:10px 12px;border-radius:9px;background:#fff;box-sizing:border-box;border-bottom:1px solid #f4f6f9');
    const noteCss = n => this.cssToObj('font-size:.7rem;color:' + (n && n.tone === 'err' ? '#b4483c' : n && n.tone === 'ok' ? '#2f7d4f' : '#6b7689'));
    const busyLabel = (key, prefix, idle, during) => busy && busy === key && notes[key] && notes[key].text.indexOf(prefix) === 0 ? during : idle;
    // Athletes ticked in the Participants table: their certificates only.
    const regChosen = who === 'participants' ? ((st.regs || []).filter(r => (st.regSel || {})[r.id] && r.holder).map(r => r.holder).filter((h, i, a) => a.indexOf(h) === i)) : [];
    const items = rows.filter(([url]) => !!url).map(([url, type, label, hint, badgePdf]) => {
      const key = type || who + '-badges', n = notes[key];
      const actions = [chip('Design', go(url, type), false, !!busy)];
      if (type && C && regChosen.length) {
        actions.push(chip('Issue to ' + regChosen.length + ' chosen', issueTo(type, regChosen), true, !!busy), chip('Download ' + regChosen.length + ' chosen PDF', pdfOf(type, regChosen), false, !!busy));
        return {label, hint: 'For the ' + regChosen.length + ' athletes ticked in the table.', hintStyle: noteCss(null), actions, onClick: () => {}, style: rowCss};
      }
      if (type && C) actions.push(chip(busyLabel(key, 'Issuing', 'Issue', 'Issuing\u2026'), issue(type), true, !!busy));
      if ((type && C) || badgePdf) actions.push(chip(busyLabel(key, 'Making', 'Download PDF', 'Making PDF\u2026'), pdf(key, badgePdf || null), false, !!busy));
      return {label, hint, hintStyle: noteCss(null), actions, onClick: () => {}, style: rowCss};
    });
    // Officials: the menu follows the Job tab (owner, 2026-10-20). On "All" it is
    // the general designs, issuing and the PDFs; on a job's tab it is THAT job's
    // own badge and certificate — a job without one prints the general design.
    let label = 'Printables', quick = {show: false};
    const qBtn = primary => this.cssToObj('height:40px;padding:0 16px;border-radius:11px;font-family:inherit;font-size:.78rem;font-weight:700;cursor:pointer;white-space:nowrap;display:inline-flex;align-items:center;gap:6px;'
      + (primary ? 'border:0;color:#1e2c4f;background:linear-gradient(100deg,#edcd85,#d0a854);box-shadow:0 4px 12px rgba(202,161,79,.25)' : 'border:1px solid #d5dae3;color:#1e2c4f;background:#fff') + (busy ? ';opacity:.6' : ''));
    if (who === 'clubs' && C) {
      // Clubs ticked in the list: issue to / print those only; none ticked: every club.
      const chosen = st.clubSelEv === sel.id ? chosenOf(st.clubSel) : [];
      const all = {bundle: C.bundle.replace('xtypex', 'club_thanks'), download: C.download.replace('xtypex', 'club_thanks')};
      quick = {show: true, issueStyle: qBtn(true), pdfStyle: qBtn(false), hasStatus: false,
        issueLabel: busy === 'club_thanks' ? 'Issuing\u2026' : (chosen.length ? 'Issue to ' + chosen.length + ' chosen' : 'Issue certificates'),
        issueTitle: chosen.length ? 'Issue a certificate to the clubs you ticked' : 'Issue a certificate to every club that has none yet',
        onIssue: chosen.length ? issueTo('club_thanks', chosen) : issue('club_thanks'),
        pdfLabel: busy && (busy === 'club_thanks' || String(busy).indexOf('club_thanks.sel-') === 0) && notes[busy] && notes[busy].tone === 'busy' && notes[busy].text.indexOf('Making') === 0 ? 'Making PDF\u2026' : (chosen.length ? 'Download ' + chosen.length + ' chosen PDF' : 'Download PDF'),
        pdfTitle: chosen.length ? 'The ticked clubs\u2019 certificates in one PDF' : 'Every issued club certificate in one PDF',
        onPdf: chosen.length ? pdfOf('club_thanks', chosen) : pdf('club_thanks', all)};
    }
    if (who === 'officials') {
      const job = st.offEv === sel.id ? (st.offJob || '') : '';
      const group = job ? ((O && O.groups) || []).find(g => g.label === job) : null;
      const jd = group ? (M.jobDesigns || []).find(d => d.key === group.key) : null;
      // Issue and Download PDF beside the button (owner, 2026-10-20). Issue covers
      // every official due one; the PDF is the tab's — one job's, or everyone's on All.
      if (C) {
        const pdfKey = jd ? 'official_thanks.' + jd.key : 'official_thanks';
        const urls = {bundle: C.bundle.replace('xtypex', pdfKey), download: C.download.replace('xtypex', pdfKey)};
        const n = notes[pdfKey] || notes.official_thanks || null;
        const btn = primary => this.cssToObj('height:32px;padding:0 14px;border-radius:999px;font-family:inherit;font-size:.74rem;font-weight:700;cursor:pointer;white-space:nowrap;display:inline-flex;align-items:center;gap:6px;'
          + (primary ? 'border:0;color:#1e2c4f;background:linear-gradient(100deg,#edcd85,#d0a854);box-shadow:0 4px 12px rgba(202,161,79,.25)' : 'border:1px solid #d5dae3;color:#1e2c4f;background:#fff') + (busy ? ';opacity:.6' : ''));
        // Officials ticked in the list: the two buttons act on them only.
        const chosen = st.offEv === sel.id ? chosenOf(st.offSel) : [];
        quick = chosen.length ? {show: true,
          issueLabel: busy === 'official_thanks' ? 'Issuing\u2026' : 'Issue to ' + chosen.length + ' chosen', issueTitle: 'Issue a certificate to the officials you ticked', onIssue: issueTo('official_thanks', chosen), issueStyle: btn(true),
          pdfLabel: busy && String(busy).indexOf('.sel-') > 0 ? 'Making PDF\u2026' : 'Download ' + chosen.length + ' chosen PDF', pdfTitle: 'The ticked officials\u2019 issued certificates in one PDF',
          onPdf: pdfOf('official_thanks', chosen), pdfStyle: btn(false), hasStatus: false} : {show: true,
          issueLabel: busy === 'official_thanks' ? 'Issuing\u2026' : 'Issue certificates', issueTitle: 'Issue a certificate to every official who has none yet', onIssue: issue('official_thanks'), issueStyle: btn(true),
          pdfLabel: busy === pdfKey ? 'Making PDF\u2026' : (jd ? 'Download ' + jd.label + ' PDF' : 'Download PDF'), pdfTitle: jd ? 'Every issued ' + jd.label + ' certificate in one PDF' : 'Every issued officials\u2019 certificate in one PDF',
          onPdf: pdf(pdfKey, urls), pdfStyle: btn(false),
          hasStatus: false};
      }
      // Two rows, badge and certificate, the same on All and on a job's tab (owner,
      // 2026-10-29). Every action follows the ticks: the chosen officials when any
      // are ticked, otherwise the whole tab — everyone on All, that job's people on its tab.
      const picked = st.offEv === sel.id ? chosenOf(st.offSel) : [];
      const nPick = picked.length;
      const jobHolders = group ? (group.people || []).map(p => p.holder).filter(Boolean) : [];
      const goTo = u => () => { this.setState({printOpen: null}); if (u) window.location.assign(u + (u.indexOf('?') < 0 ? '?' : '&') + 'back=' + back()); };
      const whom = jd ? 'every ' + jd.label + ' official' : 'every official';
      if (jd) label = jd.label + ' printables';
      items.length = 0;
      const badgeKey = jd ? 'official_badge.' + jd.key : 'officials-badges';
      const badgeUrls = jd ? (jd.badgePdf || P.officials) : P.officials;
      const badgeActs = [jd
        ? chip('Design', goTo(jd.badge), false, !!busy, jd.hasBadge ? 'Its own design ✓' : 'Uses the general badge until it has its own')
        : chip('Design', go(M.designBadges, null), false, !!busy, 'The general design')];
      // Issue badges (owner, 2026-10-29): each official is notified and can open their own.
      if (M.badgeIssue) {
        const issueBadges = async () => {
          if (busy) return;
          this.setState({printBusy: 'badge-issue'}); note('badge-issue', 'Issuing badges\u2026', 'busy');
          try { const j = await call('POST', M.badgeIssue, nPick ? {holders: picked} : (jd ? {job: jd.key} : {})); note('badge-issue', j.message || 'Issued.', 'ok'); }
          catch (e) { note('badge-issue', e.message, 'err'); }
          this.setState({printBusy: null});
        };
        badgeActs.push(chip(busy === 'badge-issue' ? 'Issuing\u2026' : (nPick ? 'Issue ' + nPick + ' chosen badges' : 'Issue badges'), issueBadges, true, !!busy || (!!jd && !jobHolders.length),
          (nPick ? 'Sends the ticked officials theirs' : 'Sends ' + whom + ' theirs') + ' \u2014 only those not sent yet'));
      }
      if (nPick && M.badgeSelection) badgeActs.push(chip(busyLabel('badge-sel', 'Making', 'Download ' + nPick + ' chosen badges', 'Making PDF…'), badgeSel(offBadgeCfg, picked), false, !!busy, 'The officials ticked in the list'));
      else if (badgeUrls) badgeActs.push(chip(busyLabel(badgeKey, 'Making', 'Download badges', 'Making PDF…'), pdf(badgeKey, badgeUrls), false, !!busy, 'For ' + whom + ', four to an A4 sheet'));
      if (jd || M.designBadges) items.push({label: (jd ? jd.label + ' ' : 'Officials’ ') + 'badge', hint: 'A6 identity cards', hintStyle: noteCss(null), actions: badgeActs, onClick: () => {}, style: rowCss});
      if (C && (jd || M.designCertificates)) {
        const certKey = jd ? 'official_thanks.' + jd.key : 'official_thanks';
        const certActs = [jd
          ? chip('Design', goTo(jd.certificate), false, !!busy, jd.hasCertificate ? 'Its own design ✓' : 'Uses the general certificate until it has its own')
          : chip('Design', go(M.designCertificates, 'official_thanks'), false, !!busy, 'The general design')];
        const issuing = busy === 'official_thanks';
        if (nPick) {
          certActs.push(chip(issuing ? 'Issuing…' : 'Issue to ' + nPick + ' chosen', issueTo('official_thanks', picked), true, !!busy, 'Those who have none yet'),
            chip(busy && String(busy).indexOf('.sel-') > 0 ? 'Making PDF…' : 'Download ' + nPick + ' chosen', pdfOf('official_thanks', picked), false, !!busy, 'Their issued certificates in one PDF'));
        } else {
          certActs.push(chip(issuing ? 'Issuing…' : 'Issue certificates', jd ? issueTo('official_thanks', jobHolders) : issue('official_thanks'), true, !!busy || (!!jd && !jobHolders.length), 'To ' + whom + ' who has none yet'),
            chip(busy === certKey ? 'Making PDF…' : 'Download certificates', pdf(certKey, {bundle: C.bundle.replace('xtypex', certKey), download: C.download.replace('xtypex', certKey)}), false, !!busy, 'Every issued one, in one PDF'));
        }
        items.push({label: (jd ? jd.label + ' ' : 'Officials’ ') + 'certificate', hint: 'Thanks for helping make the event a success', hintStyle: noteCss(null), actions: certActs, onClick: () => {}, style: rowCss});
      }
      if (!jd && (M.jobDesigns || []).length) {
        items.push({label: 'A job’s own designs', hint: 'Choose a job tab — Team manager, Referee… — and open Printables there.', hintStyle: noteCss(null), actions: [], onClick: () => {},
          style: this.cssToObj('display:flex;flex-direction:column;align-items:flex-start;gap:2px;width:100%;padding:10px 12px;box-sizing:border-box;background:#f7f8fb;border-radius:9px;margin-top:4px')});
      }
    }
    // Ticked competitors get their badges too — the same option the officials have.
    // It follows the ticks like everything else: the chosen replace "everyone".
    if (who === 'participants' && regChosen.length && M.participantBadgeSelection && items[0] && items[0].actions) {
      if (items[0].actions.length > 1) items[0].actions.pop();
      items[0].actions.push(chip(busyLabel('badge-sel', 'Making', 'Download ' + regChosen.length + ' chosen badges', 'Making PDF\u2026'), badgeSel(regBadgeCfg, regChosen), false, !!busy));
    }
    // Each printable is a row opening its own submenu to the side (owner, 2026-10-29):
    // hover or click shows its actions; a note (no actions) stays a plain line.
    const open = st.printOpen === who;
    const subAt = open && st.printSub != null ? st.printSub : null;
    items.forEach((it, i) => {
      it.hasSub = !!(it.actions && it.actions.length);
      it.isNote = !it.hasSub;
      it.subOpen = it.hasSub && subAt === i;
      it.onHover = () => { if ((this.state || {}).printSub !== (it.hasSub ? i : null)) this.setState({printSub: it.hasSub ? i : null}); };
      it.onOpen = e => { if (e && e.stopPropagation) e.stopPropagation(); this.setState({printSub: it.subOpen ? null : i}); };
      if (it.hasSub) {
        it.style = this.cssToObj('display:flex;align-items:center;gap:10px;width:100%;padding:10px 12px;border:0;border-radius:9px;font-family:inherit;text-align:left;cursor:pointer;box-sizing:border-box;background:' + (it.subOpen ? '#fdf6e6' : '#fff'));
        it.subStyle = this.cssToObj('position:absolute;top:-6px;left:calc(100% + 6px);z-index:47;width:260px;padding:6px;background:#fff;border:1px solid #eef1f6;border-radius:14px;box-shadow:0 18px 40px rgba(30,44,79,.16);animation:rise .14s cubic-bezier(.22,.68,.35,1) both');
      }
    });
    return {
      any: items.length > 0, items, open,
      onToggle: e => { if (e && e.stopPropagation) e.stopPropagation(); this.setState({printOpen: open ? null : who, printSub: null}); },
      onClose: () => { if (!busy) this.setState({printOpen: null}); },
      stop: e => { if (e && e.stopPropagation) e.stopPropagation(); },
      // In the officials' Job row it sits among the tabs, so it takes their pill shape.
      style: this.cssToObj((who === 'officials' ? 'height:32px;border-radius:999px;' : 'height:40px;border-radius:11px;') + 'padding:0 14px;border:1px solid ' + (open ? '#caa14f' : '#d5dae3') + ';font-family:inherit;font-size:.78rem;font-weight:700;color:#1e2c4f;background:' + (open ? '#fdf6e6' : '#fff') + ';cursor:pointer;display:inline-flex;align-items:center;gap:8px;white-space:nowrap'),
      caret: open ? '▴' : '▾',
      label, quick,
      toast: (() => {
        const t = st.printToast;
        return {has: !!(t && t.text), text: t ? t.text : '', busy: !!(t && t.tone === 'busy'),
          style: this.cssToObj('position:fixed;left:50%;top:50%;transform:translate(-50%,-50%);z-index:95;display:flex;align-items:center;gap:12px;max-width:min(440px,calc(100vw - 32px));padding:16px 20px;border-radius:16px;font-size:.86rem;font-weight:600;line-height:1.4;color:#fff;box-shadow:0 24px 60px rgba(15,23,42,.35);animation:rise .18s cubic-bezier(.22,.68,.35,1) both;background:'
            + (t && t.tone === 'err' ? '#b4483c' : '#1e2c4f')),
          icon: t && t.tone === 'err' ? '\u26A0' : (t && t.tone === 'ok' ? '\u2713' : '\u231B'),
          onClose: () => { clearTimeout(window.__fedPrintToastT); this.setState({printToast: null}); }};
      })()
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
