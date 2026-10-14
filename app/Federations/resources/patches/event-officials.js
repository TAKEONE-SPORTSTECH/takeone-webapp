  // The event's Officials tab (FederationApp: 'offs-*' rewrites). Draws the
  // event's OWN appointments — the same people the readiness checklist counts
  // and the event's officiating sheet lists (FederationEventOfficials), grouped
  // by job, each with their club. A viewer who may run the event appoints,
  // changes and removes them right here (evOfficialsEditor) — through the
  // officiating endpoints themselves, which re-check every write.
  evOfficialsVals(sel) {
    const T = window.TakeOneFed || {};
    const O = sel && sel.id && T.eventOfficials ? T.eventOfficials(sel.id) : null;
    const css = x => this.cssToObj(x);
    if (!O) {
      return {total: '0', chips: [], groups: [], rows: [], hasRows: false, noMatch: false, query: '', onQuery: () => {}, onAll: () => {}, allStyle: {}, allCountStyle: {}, allPressed: 'true', empty: true, canManage: false, onAdd: () => {}, ed: {open: false}, flash: '', hasFlash: false,
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
    const rows = (O.groups || []).flatMap(g => (g.people || []).map(p => ({name: p.name, role: p.role, job: g.label,
      editable: !!(M && p.id), plain: !(M && p.id), onOpen: () => { if (M && p.id) this.offEdOpen(sel.id, {mode: 'edit', person: p}); },
      initials: p.photo ? '' : p.initials, avStyle: Object.assign(tile(p), css('width:36px;height:48px;border-radius:8px;font-size:.72rem')),
      roleStyle: pill(g.label), hasLogo: !!p.clubLogo, logoStyle: p.clubLogo ? logo(p.clubLogo) : {}, clubShown: p.club || 'Independent'})))
      .sort((a, b) => String(a.name).localeCompare(String(b.name), undefined, {sensitivity: 'base'}));
    // The chips filter by job, the search box by name, role or club. Both
    // belong to the event they were set on, so opening another starts clean.
    const st = this.state || {};
    const mine = st.offEv === sel.id;
    const job = mine ? (st.offJob || '') : '';
    const q = mine ? String(st.offQ || '') : '';
    const needle = q.trim().toLowerCase();
    const set = patch => this.setState({offEv: sel.id, offJob: job, offQ: q, ...patch});
    const chipCss = (on, all) => css('display:inline-flex;align-items:center;gap:8px;height:36px;padding:0 14px;border-radius:999px;font-family:inherit;font-size:.78rem;cursor:pointer;transition:background .15s,border-color .15s;'
      + (on ? 'background:#1e2c4f;color:#fff;border:1px solid #1e2c4f;font-weight:600' : 'background:#fff;color:#1e2c4f;border:1px solid #e4d3a8;font-weight:500'));
    const countCss = on => css('font-weight:700;color:' + (on ? '#edcd85' : '#8a6d20'));
    const allOn = job === '';
    const shown = rows.filter(r => (!job || r.job === job)
      && (!needle || [r.name, r.role, r.job, r.clubShown].some(v => String(v || '').toLowerCase().includes(needle))));
    return {total: String(O.total || 0), chips: groups.map(g => ({label: g.label, count: g.count, hint: 'Show only ' + g.label.toLowerCase(), pressed: String(job === g.label),
        style: chipCss(job === g.label), countStyle: countCss(job === g.label), onClick: () => set({offJob: job === g.label ? '' : g.label})})),
      allStyle: chipCss(allOn), allCountStyle: countCss(allOn), allPressed: String(allOn), onAll: () => set({offJob: ''}),
      query: q, onQuery: e => set({offQ: e.target.value}),
      groups, rows: shown, hasRows: shown.length > 0, noMatch: rows.length > 0 && shown.length === 0,
      canManage: !!M, onAdd: () => this.offEdOpen(sel.id, {mode: 'add'}), ed: this.evOfficialsEditor(sel, M),
      flash: st.offFlash && st.offFlash.ev === sel.id ? st.offFlash.text : '', hasFlash: !!(st.offFlash && st.offFlash.ev === sel.id),
      empty: !groups.length, emptyText: 'No officials are appointed to this event yet.',
    };
  }

  // ── Officials editor ────────────────────────────────────────────────────
  // One dialog for appointing (a member found by name/phone/email, or a new
  // person typed in by name) and for changing or removing an appointment.
  // Every write goes to the event's officiating endpoints, which authorise
  // and validate it; this only offers what they accept (roles, clubs and pay
  // types come from the same endpoint, GET officials).
  offEdOpen(ev, opts) {
    const p = opts.person || null;
    this.setState({offEd: {ev, mode: opts.mode, tab: 'member', q: '', results: [], searching: false, pick: null, newName: '',
      id: p ? p.id : null, name: p ? p.name : '', currentRole: p ? p.role : '', roleKey: p ? p.roleKey : '', role: p ? p.roleKey : '',
      club: p ? (p.clubKey || '') : '', comp: p ? (p.compensation || 'volunteer') : 'volunteer', fee: p && p.fee != null ? String(p.fee) : '',
      msg: '', busy: false, confirmDel: false}});
    this.offEdMeta(ev, false);
  }
  offEdSet(patch) { this.setState(s => ({offEd: Object.assign({}, s.offEd, patch)})); }
  offEdCall(url, method, body) {
    const T = window.TakeOneFed || {};
    return fetch(url, {method, credentials: 'same-origin',
      headers: Object.assign({'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': T.csrf ? T.csrf() : ''},
        body ? {'Content-Type': 'application/json'} : {}),
      body: body ? JSON.stringify(body) : undefined})
      .then(r => r.json().catch(() => ({})).then(j => { j.__ok = r.ok; return j; }))
      .catch(() => ({__ok: false, message: 'The connection dropped. Nothing was changed — try again.'}));
  }
  offEdErr(j) {
    if (j && j.errors) { const first = Object.values(j.errors)[0]; if (first && first[0]) return first[0]; }
    return (j && j.message) || 'That did not save. Try again.';
  }
  // Roles, clubs, pay types and currency — read once per event.
  offEdMeta(ev, force) {
    const M = this.offEdManage(ev);
    if (!M || (!force && this._offMeta && this._offMeta.ev === ev)) return;
    this.offEdCall(M.list + '?q=', 'GET').then(j => {
      if (j.__ok) { this._offMeta = {ev, roles: j.roles || [], clubs: j.clubs || [], comps: j.compensations || [], currency: j.currency || ''}; this.setState({offMetaTick: Date.now()}); }
    });
  }
  offEdManage(ev) {
    const T = window.TakeOneFed || {};
    const O = T.eventOfficials ? T.eventOfficials(ev) : null;
    return O && O.manage ? O.manage : null;
  }
  offEdSearch(q) {
    const s = this.state.offEd || {};
    const M = this.offEdManage(s.ev);
    this.offEdSet({q, pick: null});
    clearTimeout(this._offT);
    if (!M || q.trim().length < 2) { this.offEdSet({results: [], searching: false}); return; }
    this.offEdSet({searching: true});
    this._offT = setTimeout(() => {
      const role = (this.state.offEd || {}).role || '';
      this.offEdCall(M.list + '?q=' + encodeURIComponent(q.trim()) + (role ? '&role=' + encodeURIComponent(role) : ''), 'GET').then(j => {
        const cur = this.state.offEd || {};
        if (cur.q !== q) return;
        this.offEdSet({searching: false, results: (j.candidates || []).slice(0, 12)});
      });
    }, 300);
  }
  offEdSave() {
    const s = this.state.offEd || {};
    const M = this.offEdManage(s.ev);
    if (!M || s.busy) return;
    const pay = {compensation: s.comp, fee: s.comp === 'paid' ? s.fee : null, club: s.club || ''};
    let req;
    if (s.mode === 'edit') {
      req = this.offEdCall(M.one.replace('__ID__', encodeURIComponent(s.id)), 'PUT', Object.assign(pay, s.role && s.role !== s.roleKey ? {role: s.role} : {}));
    } else if (s.tab === 'new') {
      req = this.offEdCall(M.person, 'POST', Object.assign(pay, {full_name: s.newName.trim(), role: s.role}));
    } else {
      req = this.offEdCall(M.store, 'POST', Object.assign(pay, {user_id: s.pick && s.pick.id, role: s.role}));
    }
    this.offEdSet({busy: true, msg: ''});
    req.then(j => this.offEdDone(j, s.ev));
  }
  offEdRemove() {
    const s = this.state.offEd || {};
    const M = this.offEdManage(s.ev);
    if (!M || s.busy) return;
    if (!s.confirmDel) { this.offEdSet({confirmDel: true}); return; }
    this.offEdSet({busy: true, msg: ''});
    this.offEdCall(M.one.replace('__ID__', encodeURIComponent(s.id)), 'DELETE').then(j => this.offEdDone(j, s.ev));
  }
  offEdDone(j, ev) {
    if (!j.__ok || j.success === false) { this.offEdSet({busy: false, confirmDel: false, msg: this.offEdErr(j)}); return; }
    const T = window.TakeOneFed || {};
    this.setState({offEd: null, offFlash: {ev, text: j.message || 'Saved.'}});
    clearTimeout(this._offFlashT);
    this._offFlashT = setTimeout(() => this.setState({offFlash: null}), 4000);
    // Re-read the dashboard's state: the tab, the checklist and the counts.
    if (T.refresh) T.refresh();
  }
  evOfficialsEditor(sel, M) {
    const s = (this.state || {}).offEd;
    if (!M || !s || s.ev !== sel.id) return {open: false};
    const css = x => this.cssToObj(x);
    const meta = this._offMeta && this._offMeta.ev === sel.id ? this._offMeta : null;
    const isNew = s.mode === 'add' && s.tab === 'new';
    const opt = on => css('display:flex;align-items:center;gap:8px;min-height:40px;padding:8px 12px;border-radius:11px;font-family:inherit;font-size:.78rem;text-align:start;cursor:pointer;transition:background .15s,border-color .15s;'
      + (on ? 'background:#fdf6e6;border:1px solid #caa14f;color:#1e2c4f;font-weight:600' : 'background:#fff;border:1px solid #e3e7ee;color:#1e2c4f;font-weight:500'));
    const dot = on => css('width:14px;height:14px;border-radius:50%;flex:none;box-sizing:border-box;' + (on ? 'border:4px solid #caa14f;background:#fff' : 'border:1.5px solid #c3cad6'));
    // A new person may only fill a job on the mat — the server refuses a
    // role that grants access to somebody with no account (storeOfficialPerson).
    const roles = (meta ? meta.roles : []).filter(r => !isNew || r.group !== 'platform');
    const roleList = roles.map(r => ({label: r.label, hint: r.hint || '', on: s.role === r.value, style: opt(s.role === r.value), dot: dot(s.role === r.value),
      // The search pool follows the job (a referee is found platform-wide,
      // an access role only among the host's people), so a pick re-asks.
      onClick: () => { this.offEdSet({role: r.value}); if (!s.pick && s.mode === 'add' && s.tab === 'member' && s.q.trim().length >= 2) setTimeout(() => this.offEdSearch(s.q), 0); }}));
    const keepsCustom = s.mode === 'edit' && s.roleKey && !roles.some(r => r.value === s.roleKey);
    const clubs = [{slug: '', name: 'Independent'}].concat(meta ? meta.clubs : []);
    const clubList = clubs.map(c => ({label: c.name, style: opt(s.club === c.slug), dot: dot(s.club === c.slug), onClick: () => this.offEdSet({club: c.slug})}));
    const comps = (meta ? meta.comps : [{value: 'volunteer', label: 'Volunteer'}, {value: 'paid', label: 'Paid'}]);
    const compList = comps.map(c => ({label: c.label, hint: c.hint || '', style: opt(s.comp === c.value), dot: dot(s.comp === c.value), onClick: () => this.offEdSet({comp: c.value})}));
    const who = s.mode === 'edit' ? true : (isNew ? s.newName.trim().length >= 2 : !!s.pick);
    const hasRole = s.mode === 'edit' ? true : !!s.role;
    const feeOk = s.comp !== 'paid' || Number(s.fee) > 0;
    const ready = who && hasRole && feeOk && !s.busy && !!meta;
    const tabCss = on => css('flex:1;height:36px;border:0;border-radius:9px;font-family:inherit;font-size:.76rem;cursor:pointer;' + (on ? 'background:#fff;color:#1e2c4f;font-weight:700;box-shadow:0 2px 6px rgba(30,44,79,.12)' : 'background:transparent;color:#5d6880;font-weight:500'));
    return {
      open: true, isEdit: s.mode === 'edit', isAdd: s.mode === 'add', title: s.mode === 'edit' ? s.name : 'Appoint an official',
      eyebrow: sel.name || 'Officials', loading: !meta, ready: !!meta,
      stop: e => e.stopPropagation(), onClose: () => { clearTimeout(this._offT); this.setState({offEd: null}); },
      memberTab: tabCss(s.tab === 'member'), newTab: tabCss(s.tab === 'new'),
      onMemberTab: () => this.offEdSet({tab: 'member', role: ''}), onNewTab: () => this.offEdSet({tab: 'new', role: ''}),
      isMember: s.mode === 'add' && s.tab === 'member', isNew,
      q: s.q, onQ: e => this.offEdSearch(e.target.value), searching: s.searching,
      hasPick: !!s.pick, pickName: s.pick ? s.pick.name : '', onUnpick: () => this.offEdSet({pick: null}),
      showResults: !s.pick && s.results.length > 0, noResults: !s.pick && !s.searching && s.q.trim().length >= 2 && s.results.length === 0,
      results: s.results.map(c => ({name: c.name, sub: [c.nationality, (c.roles || []).length ? 'already: ' + c.roles.join(', ') : ''].filter(Boolean).join(' · '),
        onPick: () => this.offEdSet({pick: {id: c.id, name: c.name}, results: []})})),
      newName: s.newName, onNewName: e => this.offEdSet({newName: e.target.value}),
      roles: roleList, keepsCustom, currentRole: s.currentRole, keepStyle: opt(s.role === s.roleKey), keepDot: dot(s.role === s.roleKey),
      onKeep: () => this.offEdSet({role: s.roleKey}),
      clubs: clubList, comps: compList, isPaid: s.comp === 'paid', fee: s.fee, onFee: e => this.offEdSet({fee: e.target.value}),
      currency: meta ? meta.currency : '',
      hasMsg: !!s.msg, msg: s.msg,
      saveLabel: s.busy ? 'Saving…' : (s.mode === 'edit' ? 'Save changes' : 'Appoint'),
      saveStyle: css('margin-left:auto;height:42px;padding:0 22px;border:0;border-radius:11px;font-family:inherit;font-size:.84rem;font-weight:700;color:#1e2c4f;background:linear-gradient(100deg,#edcd85,#d0a854);box-shadow:0 6px 16px rgba(202,161,79,.3);' + (ready ? 'cursor:pointer' : 'opacity:.5;cursor:not-allowed')),
      onSave: () => { if (ready) this.offEdSave(); },
      removeLabel: s.confirmDel ? 'Tap again to remove' : 'Remove from event',
      removeStyle: css('height:42px;padding:0 16px;border-radius:11px;font-family:inherit;font-size:.78rem;font-weight:600;cursor:pointer;' + (s.confirmDel ? 'border:1px solid #c0392b;background:#c0392b;color:#fff' : 'border:1px solid #e9b8b1;background:#fff;color:#c0392b')),
      onRemove: () => this.offEdRemove()
    };
  }
