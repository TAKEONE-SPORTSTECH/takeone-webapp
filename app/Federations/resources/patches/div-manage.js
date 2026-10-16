  // The Divisions tab's EDITOR (FederationApp 'divs-*' rewrites) — the same
  // weight classes and categories an organiser manages on the platform page
  // (/me/events/{uuid}/divisions, <x-event-divisions>), rebuilt in the
  // dashboard's own language (owner, 2026-10-14: "rebuild natively").
  //
  // It is a second EDITOR, not a second set of rules: it reads the editor's
  // data from me.events.divisions.data (App\Events\Support\DivisionEditor —
  // the same weight-class table, belts and questions the component gets) and
  // writes ONLY through the platform's own division endpoints, which validate
  // and authorise every request. The doors (TakeOneFed.divisionDoors) are
  // handed to an organiser only. The behaviour mirrors the component function
  // for function — names that follow their settings, group letters, presets,
  // the "who fits" count, the people tab, duplicate, delete — so a division
  // made here and one made there are the same thing.
  //
  // ⚠️ KEEP IN STEP with resources/views/components/event-divisions.blade.php.
  // A change to what a division asks, or how it is named, lands in both.

  dmSet(patch) { this.setState(st => ({dm: {...(st.dm || {}), ...patch}})); }
  dmEd(patch) { this.setState(st => ({dm: {...(st.dm || {}), ed: {...((st.dm || {}).ed || {}), ...patch}}})); }
  dmPr(patch) { this.setState(st => ({dm: {...(st.dm || {}), pr: {...((st.dm || {}).pr || {}), ...patch}}})); }

  dmToast(msg, ok) {
    clearTimeout(this._dmToastT);
    this.dmSet({toast: msg, toastOk: ok !== false});
    this._dmToastT = setTimeout(() => this.dmSet({toast: ''}), 3200);
  }

  dmLoad(doors) {
    const T = window.TakeOneFed || {};
    if (!T.roFetch) return;
    this._dmLoading = doors.event;
    T.roFetch('GET', doors.data).then(d => {
      this._dmLoading = null;
      this.setState(st => ({dm: {
        ...(st.dm && st.dm.event === doors.event ? st.dm : {view: 'cards'}),
        event: doors.event,
        err: d.__ok ? '' : (d.message || 'The divisions could not be loaded. Try again.'),
        rows: d.__ok ? (d.rows || []) : [], phases: d.phases || [], days: Math.max(1, parseInt(d.days, 10) || 1),
        presets: d.presets || [], belts: d.belts || [], asks: d.asks || {weight: true, rank: true}, streams: d.streams || [],
        oneDiv: !!d.one_division,
        loaded: !!d.__ok,
      }}));
      if (d.__ok) this.dmSgLoad(doors);
    });
  }

  // One division per person (owner, 2026-10-16) — the same switch as the draw
  // desk's Division menu; the server (OneDivisionRule) is what enforces it.
  dmOneDiv(doors) {
    const T = window.TakeOneFed || {};
    const dm = this.state.dm || {};
    if (!doors || !doors.oneDivision || !T.roFetch || dm.oneDivBusy) return;
    this.dmSet({oneDivBusy: true});
    T.roFetch('PUT', doors.oneDivision, {on: !dm.oneDiv}).then(d => {
      this.dmSet({oneDivBusy: false, ...(d.__ok ? {oneDiv: !!d.one_division} : {})});
      this.dmToast(d.message || (d.__ok ? 'Saved.' : 'That did not save. Try again.'), !!d.__ok);
    });
  }

  // The merge suggestions, read alongside the divisions so every small card can
  // say what to do with it (DivisionMerge::suggest). Quiet: a failure only
  // means the cards show no suggestion.
  dmSgLoad(doors) {
    const T = window.TakeOneFed || {};
    if (!doors || !doors.merge || !T.roFetch) return;
    T.roFetch('GET', doors.merge).then(d => { if (d.__ok) this.dmSet({sg: d}); });
  }

  // One merge from a card — two taps: the first arms it (the button asks
  // "Confirm?"), the second merges. Amber ones never merge from a card: they
  // need the consent statement, which lives in the merge window.
  dmCardMerge(j, e) {
    if (e && e.stopPropagation) e.stopPropagation();
    const T = window.TakeOneFed || {}, doors = this._dmDoors, dm = this.state.dm || {};
    if (!doors || !doors.merge || !j || dm.sgBusy) return;
    if (j.grade !== 'green') { this.dmMgOpen(); return; }
    const key = j.ids.join('-');
    if (dm.sgArm !== key) {
      this.dmSet({sgArm: key});
      clearTimeout(this._dmArmT); this._dmArmT = setTimeout(() => { if ((this.state.dm || {}).sgArm === key) this.dmSet({sgArm: ''}); }, 5000);
      return;
    }
    this.dmSet({sgArm: '', sgBusy: key});
    T.roFetch('POST', doors.merge, {ids: j.ids, into: j.into}).then(d => {
      this.dmSet({sgBusy: ''});
      if (!d.__ok) { this.dmToast(d.message || 'That did not go through. Try again.', false); this.dmSgLoad(doors); return; }
      this.dmToast(d.message || 'Divisions merged.');
      this.dmLoad(doors);
    });
  }

  // Every SAFE suggestion at once, one after another; consent ones are left
  // for the merge window. The server judges each again before it merges.
  dmMergeAllSafe() {
    const T = window.TakeOneFed || {}, doors = this._dmDoors, dm = this.state.dm || {};
    const safe = ((dm.sg || {}).suggestions || []).filter(j => j.grade === 'green');
    if (!doors || !doors.merge || !safe.length || dm.sgBusy) return;
    if (dm.sgArm !== 'all') {
      this.dmSet({sgArm: 'all'});
      clearTimeout(this._dmArmT); this._dmArmT = setTimeout(() => { if ((this.state.dm || {}).sgArm === 'all') this.dmSet({sgArm: ''}); }, 5000);
      return;
    }
    this.dmSet({sgArm: '', sgBusy: 'all'});
    let done = 0, failed = '';
    safe.reduce((p, j) => p.then(() => T.roFetch('POST', doors.merge, {ids: j.ids, into: j.into}).then(d => { if (d.__ok) done++; else failed = failed || d.message || 'One merge did not go through.'; })), Promise.resolve())
      .then(() => {
        this.dmSet({sgBusy: ''});
        this.dmToast(failed ? done + ' merged — then: ' + failed : (done === 1 ? 'One division merged.' : done + ' divisions merged.'), !failed);
        this.dmLoad(doors);
      });
  }

  dmListen(doors) {
    if (this._dmRT === doors.event) return;
    if (this._dmRTfn) window.removeEventListener('realtime:events', this._dmRTfn);
    this._dmRT = doors.event;
    this._dmRTfn = e => {
      const d = (e && e.detail) || {};
      if (d.event !== doors.event || ['draw', 'refresh', 'entrants'].indexOf(d.action) < 0) return;
      clearTimeout(this._dmRTt);
      this._dmRTt = setTimeout(() => {
        const dm = this.state.dm || {};
        if (!dm.busy && !(dm.ed && dm.ed.open) && !this._dmDrag && this.state.tab === 'divs') this.dmLoad(doors);
      }, 700);
    };
    window.addEventListener('realtime:events', this._dmRTfn);
  }

  // ── The component's pure helpers, in the component's own words ──────────
  dmWords() {
    return {male: 'Male', female: 'Female', yrs: 'yrs', kg: 'kg', group: 'Group', belt_word: 'Belt',
      age_kids: 'Kids', age_youth: 'Youth', age_adult: 'Adult', age_master: 'Masters', entrants: 'entered',
      no_cap: 'No cap', any_age: 'Any age', any_gender: 'Any', belts_any: 'Any belt'};
  }

  /* A division's fields, flat. Two quiet repairs on the way in, because the
     stored row and the endpoint's rules disagree and an untouched re-save
     would otherwise be refused: gender is stored `male`/`female` and accepted
     only as `Male`/`Female`; a weight of 0 is the table's open end and the
     endpoint takes no weight under 10. */
  dmFlat(d, phases) {
    d = d || {};
    const r = d.range || d;
    const val = v => (v === null || v === undefined ? '' : v);
    const g = String(r.gender || '').toLowerCase();
    // 0 and ≥200 are the table's open ends ("no lower / upper limit").
    const w = v => (v === null || v === undefined || v === '' || Number(v) <= 0 || Number(v) >= 200 ? '' : v);
    const sched = {}; (phases || []).forEach(p => { sched[p.key] = 1; });
    return {
      name: d.name || '', is_heading: !!d.is_heading, capacity: val(d.capacity), format: d.format || 'knockout',
      gender: g === 'male' ? 'Male' : g === 'female' ? 'Female' : '',
      min_age: val(r.min_age), max_age: val(r.max_age), min_weight: w(r.min_weight), max_weight: w(r.max_weight),
      belts: Array.isArray(r.belts) ? r.belts.slice() : (Array.isArray(d.belts) ? d.belts.slice() : []),
      stream: d.stream || '', schedule: Object.assign(sched, d.schedule || {}),
    };
  }

  dmAgeBand(min, max) {
    const lo = min === '' || min == null ? null : parseInt(min, 10), hi = max === '' || max == null ? null : parseInt(max, 10), w = this.dmWords();
    if (lo === null && hi === null) return '';
    if (lo === null) { if (hi <= 11) return w.age_kids; if (hi <= 17) return w.age_youth; return '≤' + hi + ' ' + w.yrs; }
    if (hi === null) { if (lo >= 30) return w.age_master; if (lo >= 18) return w.age_adult; return lo + '+ ' + w.yrs; }
    if (hi <= 11) return w.age_kids; if (hi <= 17) return w.age_youth; if (lo >= 30) return w.age_master; if (lo >= 18) return w.age_adult;
    return lo + '–' + hi + ' ' + w.yrs;
  }

  dmWeightLabel(min, max) {
    const lo = min === '' || min == null ? null : Number(min), hi = max === '' || max == null ? null : Number(max);
    const n = v => String(Math.round(v * 10) / 10);
    if (lo == null && hi == null) return '';
    if (lo == null) return '−' + n(hi) + ' kg';
    if (hi == null) return '+' + n(lo) + ' kg';
    return n(lo) + '–' + n(hi) + ' kg';
  }

  dmLetter(rows, index) {
    if (index == null || index < 0) return '';
    let n = 0;
    for (let k = 0; k < index && k < rows.length; k++) { if (rows[k] && rows[k].is_heading) n = 0; else n++; }
    return n < 26 ? String.fromCharCode(65 + n) : '';
  }

  dmSection(rows, index) {
    for (let k = Math.min(index, rows.length) - 1; k >= 0; k--) if (rows[k] && rows[k].is_heading) return String(rows[k].name || '').trim();
    return '';
  }

  dmNameBody(form, rows, index, belts) {
    const parts = [], w = this.dmWords();
    const chosen = form.belts || [];
    if (chosen.length) {
      const names = (belts || []).filter(b => chosen.indexOf(b.value) >= 0).map(b => b.label);
      if (names.length) parts.push(names.join(', ') + ' ' + w.belt_word);
    }
    if (form.gender) parts.push(form.gender === 'Female' ? w.female : w.male);
    const age = this.dmAgeBand(form.min_age, form.max_age); if (age) parts.push(age);
    const wt = this.dmWeightLabel(form.min_weight, form.max_weight); if (wt) parts.push(wt);
    const sec = this.dmSection(rows, index); if (sec) parts.push(sec);
    return parts.join(' · ');
  }

  // The suggested name (owner, 2026-10-16): the group's letter, then its OWN
  // ages and weights — "Group A : 5-6y, 17-23kg". Never an age word ("Kids")
  // or a gender taken from any table. Same format as App\Events\Support\
  // GroupName on the server — keep the two in step.
  dmGenerated(form, rows, index, belts) {
    const letter = this.dmLetter(rows, index);
    if (!letter) return '';
    const range = this.dmGroupRange(form);
    return 'Group ' + letter + (range ? ' : ' + range : '');
  }

  // "5-6y, 17-23kg" from the division's own fields; '' when it narrows neither.
  dmGroupRange(f) {
    const n = v => (v === '' || v == null || isNaN(Number(v))) ? null : Number(v);
    const num = v => String(+v.toFixed(2));
    const a1 = n(f.min_age), a2 = n(f.max_age);
    let w1 = n(f.min_weight), w2 = n(f.max_weight);
    w1 = w1 !== null && w1 > 0 ? w1 : null;
    w2 = w2 !== null && w2 > 0 && w2 < 200 ? w2 : null;
    const bits = [];
    if (a1 !== null || a2 !== null) bits.push(a1 !== null && a2 !== null ? (a1 === a2 ? num(a1) : num(a1) + '-' + num(a2)) + 'y' : a1 !== null ? num(a1) + 'y+' : '≤' + num(a2) + 'y');
    if (w1 !== null || w2 !== null) bits.push(w1 !== null && w2 !== null ? (w1 === w2 ? num(w1) : num(w1) + '-' + num(w2)) + 'kg' : w1 !== null ? '+' + num(w1) + 'kg' : '-' + num(w2) + 'kg');
    return bits.join(', ');
  }

  // Only "Group X" or "Group X : …" counts as ours — every other name was
  // typed and is never rewritten.
  dmLooksGenerated(d, rows, index, dm) {
    return /^Group [A-Z]( : .+)?$/.test(String((d && d.name) || '').trim());
  }

  dmSummary(d, dm) {
    const r = (d && d.range) || {}, w = this.dmWords(), bits = [];
    const g = String(r.gender || '').toLowerCase();
    if (g === 'male') bits.push(w.male); else if (g === 'female') bits.push(w.female);
    const pos = v => v !== null && v !== undefined && v !== '' && Number(v) > 0;
    const kg = v => pos(v) && Number(v) < 200;   // 200 is the table's open top
    const span = (a, b, ok) => (ok(a) && ok(b)) ? a + '–' + b : ok(a) ? '≥ ' + a : '≤ ' + b;
    if (pos(r.min_age) || pos(r.max_age)) bits.push(span(r.min_age, r.max_age, pos) + ' ' + w.yrs);
    if (kg(r.min_weight) || kg(r.max_weight)) bits.push(span(r.min_weight, r.max_weight, kg) + ' ' + w.kg);
    bits.push((d.entrants || 0) + ' ' + w.entrants);
    bits.push(d.capacity ? 'Cap ' + d.capacity : w.no_cap);
    const days = Object.values(d.schedule || {}).map(n => parseInt(n, 10)).filter(n => n > 0);
    if (dm.days > 1 && days.length) bits.push('Day ' + Math.min.apply(null, days));
    return bits.join(' · ');
  }

  dmDeleteAsk(d) {
    const people = Number((d && d.entrants) || 0), bouts = Number((d && d.matches) || 0);
    if (!people && !bouts) return 'This removes the division. Nobody is entered in it and nothing is drawn, so nothing else changes.';
    const drawn = bouts === 1 ? 'one bout' : bouts + ' bouts';
    if (people && bouts) return 'This group has ' + people + ' entered and ' + drawn + ' drawn, with nothing fought yet. Deleting it clears the draw and leaves those entrants without a group — they stay in the event and can be moved to another. Delete it?';
    if (bouts) return 'This group has ' + drawn + ' drawn and nothing fought yet. Deleting it clears that draw. Delete it?';
    return 'This group has ' + people + ' entered. Deleting it leaves them without a group — they stay in the event and can be moved to another. Delete it?';
  }

  // ── Writing (each one the platform's own endpoint) ────────────────────────
  dmUpsert(division) {
    if (!division) return;
    const rows = ((this.state.dm || {}).rows || []).slice();
    const at = rows.findIndex(r => r.id === division.id);
    if (at < 0) rows.push(division); else rows.splice(at, 1, division);
    this.dmSet({rows});
  }

  dmOpenEditor(d, index) {
    const dm = this.state.dm || {}, rows = dm.rows || [];
    const isNew = !d || d.__new;
    const form = isNew ? this.dmFlat({is_heading: !!(d && d.is_heading)}, dm.phases) : this.dmFlat(d, dm.phases);
    const idx = isNew ? rows.length : index;
    const autoName = isNew ? !form.is_heading : (!form.is_heading && (!String(d.name || '').trim() || this.dmLooksGenerated(d, rows, index, dm)));
    if (autoName && !form.is_heading) form.name = this.dmGenerated(form, rows, idx, dm.belts);
    this.dmSet({addOpen: false, ed: {open: true, editing: isNew ? null : d.id, index: idx, form, autoName, errors: {}, saving: false,
      tab: 'settings', peopleTab: 'selected', roster: [], rosterLoading: false, picked: {}, delAsk: false,
      preview: {loading: false, loaded: false}}});
    if (!form.is_heading) setTimeout(() => this.dmQueuePreview(), 0);
  }

  dmFormChange(patch, nameTyped) {
    const dm = this.state.dm || {}, ed = dm.ed || {};
    const form = {...ed.form, ...patch};
    let autoName = ed.autoName;
    if (nameTyped) autoName = false;
    else if (autoName && !form.is_heading) form.name = this.dmGenerated(form, dm.rows || [], ed.index, dm.belts);
    this.dmEd({form, autoName, errors: {...(ed.errors || {}), name: nameTyped ? '' : (ed.errors || {}).name}});
    if (!nameTyped && !form.is_heading) this.dmQueuePreview();
  }

  dmQueuePreview() {
    clearTimeout(this._dmPrevT);
    this.dmEd({preview: {...(((this.state.dm || {}).ed || {}).preview || {}), loading: true}});
    this._dmPrevT = setTimeout(() => this.dmRunPreview(), 400);
  }

  dmRunPreview() {
    const T = window.TakeOneFed || {}, dm = this.state.dm || {}, ed = dm.ed || {}, doors = this._dmDoors;
    if (!doors || !ed.open || ed.form.is_heading) return;
    const f = ed.form, num = v => (v === '' || v == null ? null : Number(v)), seq = ++this._dmPrevSeq || (this._dmPrevSeq = 1);
    T.roFetch('POST', doors.preview, {gender: f.gender || null, min_age: num(f.min_age), max_age: num(f.max_age),
      min_weight: num(f.min_weight), max_weight: num(f.max_weight), belts: (f.belts || []).slice(), stream: f.stream || null,
      division: ed.editing}).then(d => {
      if (seq !== this._dmPrevSeq) return;
      this.dmEd({preview: d.__ok ? {loading: false, loaded: true, matched: d.matched || 0, unknown: d.unknown || 0, here: d.here || 0, total: d.total || 0, people: d.people || []} : {loading: false, loaded: false}});
    });
  }

  dmPayload(form) {
    const num = v => (v === '' || v == null ? null : Number(v)), int = v => (v === '' || v == null ? null : parseInt(v, 10));
    if (form.is_heading) return {name: String(form.name || '').trim(), is_heading: true};
    return {name: String(form.name || '').trim(), is_heading: false, capacity: int(form.capacity), format: form.format || 'knockout',
      gender: form.gender || null, min_age: int(form.min_age), max_age: int(form.max_age),
      min_weight: num(form.min_weight), max_weight: num(form.max_weight), belts: (form.belts || []).slice(),
      stream: form.stream || null, schedule: Object.assign({}, form.schedule || {})};
  }

  dmSave() {
    const T = window.TakeOneFed || {}, dm = this.state.dm || {}, ed = dm.ed || {}, doors = this._dmDoors;
    if (!doors || ed.saving) return;
    const body = this.dmPayload(ed.form);
    if (!body.name) { this.dmEd({errors: {name: 'A division needs a name.'}}); return; }
    const creating = ed.editing === null;
    this.dmEd({saving: true, errors: {}});
    T.roFetch(creating ? 'POST' : 'PATCH', creating ? doors.base : doors.base + '/' + ed.editing, body).then(d => {
      if (!d.__ok) {
        const errors = {}; Object.entries(d.errors || {}).forEach(([k, v]) => { errors[k] = Array.isArray(v) ? v[0] : v; });
        this.dmEd({saving: false, errors});
        this.dmToast(d.message || 'That did not go through. Try again.', false);
        return;
      }
      this.dmUpsert(d.division);
      this.dmEd({open: false, saving: false});
      this.dmToast(d.message || (creating ? 'Group created.' : 'Group saved.'));
    });
  }

  dmDelete() {
    const T = window.TakeOneFed || {}, dm = this.state.dm || {}, ed = dm.ed || {}, doors = this._dmDoors;
    const row = (dm.rows || []).find(r => r.id === ed.editing);
    if (!doors || !row || ed.saving) return;
    if (Number(row.fought || 0) > 0) { this.dmToast('Bouts in this group have already been fought. It cannot be deleted.', false); return; }
    if (!ed.delAsk) { this.dmEd({delAsk: true}); return; }
    this.dmEd({saving: true});
    T.roFetch('DELETE', doors.base + '/' + row.id, {release: true}).then(d => {
      if (!d.__ok) { this.dmEd({saving: false, delAsk: false}); this.dmToast(d.message || 'That did not go through. Try again.', false); return; }
      this.dmSet({rows: (this.state.dm.rows || []).filter(r => r.id !== row.id)});
      this.dmEd({open: false, saving: false, delAsk: false});
      this.dmToast(d.message || 'Group deleted.');
    });
  }

  dmDuplicate() {
    const T = window.TakeOneFed || {}, ed = (this.state.dm || {}).ed || {}, doors = this._dmDoors;
    if (!doors || ed.saving || ed.editing === null) return;
    this.dmEd({saving: true});
    T.roFetch('POST', doors.base + '/' + ed.editing + '/duplicate').then(d => {
      if (!d.__ok || !d.division) { this.dmEd({saving: false}); this.dmToast(d.message || 'That did not go through. Try again.', false); return; }
      const rows = (this.state.dm.rows || []).slice(), at = rows.findIndex(r => r.id === d.after);
      if (at < 0) rows.push(d.division); else rows.splice(at + 1, 0, d.division);
      this.dmSet({rows});
      this.dmEd({open: false, saving: false});
      this.dmToast(d.message || 'Division copied.');
      this.dmReletter();
    });
  }

  // Letters are positional: re-name every AUTO-named row whose letter moved.
  dmReletter() {
    const T = window.TakeOneFed || {}, dm = this.state.dm || {}, doors = this._dmDoors, rows = (dm.rows || []).slice();
    if (!doors) return;
    const changed = [];
    rows.forEach((d, i) => {
      if (!d || !d.id || d.is_heading || !this.dmLooksGenerated(d, rows, i, dm)) return;
      const next = this.dmGenerated(this.dmFlat(d, dm.phases), rows, i, dm.belts);
      if (next && next !== String(d.name || '').trim()) { changed.push({id: d.id, name: next}); rows[i] = {...d, name: next}; }
    });
    if (!changed.length) return;
    this.dmSet({rows});
    changed.reduce((p, c) => p.then(() => T.roFetch('PATCH', doors.base + '/' + c.id, {name: c.name})), Promise.resolve());
  }

  dmMove(id, toIndex) {
    const T = window.TakeOneFed || {}, dm = this.state.dm || {}, doors = this._dmDoors;
    const rows = (dm.rows || []).slice(), from = rows.findIndex(r => r.id === id);
    if (!doors || from < 0) return;
    const [row] = rows.splice(from, 1);
    if (toIndex > from) toIndex--;
    toIndex = Math.max(0, Math.min(rows.length, toIndex));
    if (toIndex === from) return;
    rows.splice(toIndex, 0, row);
    this.dmSet({rows, zone: null});
    clearTimeout(this._dmOrderT);
    this._dmOrderT = setTimeout(() => {
      T.roFetch('PUT', doors.order, {order: (this.state.dm.rows || []).map(r => r.id)}).then(d => {
        if (!d.__ok) { this.dmToast(d.message || 'The new order did not save.', false); this.dmLoad(doors); return; }
        this.dmToast('Order saved.');
        this.dmReletter();
      });
    }, 350);
  }

  dmOpenPeople() {
    const T = window.TakeOneFed || {}, ed = (this.state.dm || {}).ed || {}, doors = this._dmDoors;
    if (!doors || ed.editing === null || ed.form.is_heading) return;
    this.dmEd({tab: 'people'});
    if ((ed.roster || []).length || ed.rosterLoading) return;
    this.dmEd({rosterLoading: true});
    T.roFetch('GET', doors.base + '/' + ed.editing + '/candidates').then(d => {
      if (!d.__ok) { this.dmEd({rosterLoading: false, tab: 'settings'}); this.dmToast(d.message || 'That did not go through. Try again.', false); return; }
      this.dmEd({rosterLoading: false, roster: d.people || [], picked: {}});
    });
  }

  dmSavePeople() {
    const T = window.TakeOneFed || {}, ed = (this.state.dm || {}).ed || {}, doors = this._dmDoors;
    if (!doors || ed.saving) return;
    const add = [], remove = [];
    Object.entries(ed.picked || {}).forEach(([pid, wanted]) => {
      const p = (ed.roster || []).find(x => String(x.person_id) === String(pid));
      if (!p) return;
      if (wanted) { if (p.enter_id) add.push(p.enter_id); } else if (p.entry_here_id) remove.push(p.entry_here_id);
    });
    if (!add.length && !remove.length) { this.dmEd({picked: {}}); return; }
    this.dmEd({saving: true});
    T.roFetch('PUT', doors.base + '/' + ed.editing + '/members', {add, remove}).then(d => {
      if (!d.__ok) { this.dmEd({saving: false}); this.dmToast(d.message || 'That did not go through. Try again.', false); return; }
      this.dmEd({saving: false, roster: d.people || [], picked: {}});
      if (d.division) this.dmUpsert(d.division);
      this.dmToast('Division updated.');
    });
  }

  dmAddPresets() {
    const T = window.TakeOneFed || {}, dm = this.state.dm || {}, pr = dm.pr || {}, doors = this._dmDoors;
    const band = (dm.presets || []).find(b => b.ruleset === pr.ruleset && b.band === pr.band);
    const classes = band ? ((band.genders || {})[pr.gender] || []).filter(c => (pr.pick || {})[c.label]) : [];
    if (!doors || !band || !classes.length || pr.saving) return;
    this.dmPr({saving: true});
    let made = 0;
    const next = i => {
      if (i >= classes.length) {
        this.dmPr({saving: false, open: false, pick: {}});
        if (made) this.dmToast(made + ' weight classes added.');
        return;
      }
      const c = classes[i], rows = (this.state.dm || {}).rows || [];
      const body = {is_heading: false, capacity: null, format: pr.format || 'knockout', gender: pr.gender === 'female' ? 'Female' : 'Male',
        min_age: band.min_age == null ? null : band.min_age, max_age: band.max_age == null ? null : band.max_age,
        min_weight: c.min == null ? null : c.min, max_weight: c.max == null ? null : c.max, belts: (pr.belts || []).slice(),
        schedule: Object.fromEntries((dm.phases || []).map(p => [p.key, 1]))};
      body.name = this.dmGenerated(this.dmFlat(body, dm.phases), rows, rows.length, dm.belts) || (c.label + ' kg');
      T.roFetch('POST', doors.base, body).then(d => {
        if (!d.__ok || !d.division) { this.dmPr({saving: false}); this.dmToast(d.message || 'That did not go through. Try again.', false); if (made) this.dmToast(made + ' weight classes added — then: ' + (d.message || 'one failed.'), false); return; }
        this.dmUpsert(d.division); made++; next(i + 1);
      });
    };
    next(0);
  }

  // ── The whole draw as a PDF (App\Events\Support\BracketPdf) ──────────────
  // Fetched rather than linked, so a refusal ("nothing is drawn yet") arrives
  // as a message here instead of navigating the dashboard away.
  dmDrawPdf() {
    const doors = this._dmDoors, dm = this.state.dm || {};
    if (!doors || !doors.drawPdf || dm.pdfBusy) return;
    this.dmSet({pdfBusy: true});
    this.dmToast('Preparing the draw PDF…');
    fetch(doors.drawPdf, {credentials: 'same-origin', headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json, application/pdf'}})
      .then(res => {
        const type = res.headers.get('Content-Type') || '';
        if (!res.ok || type.indexOf('application/pdf') === -1) {
          return res.json().catch(() => ({})).then(j => { throw new Error(j.message || (res.status === 429 ? 'Too many downloads at once — wait a moment.' : 'The draw PDF could not be made. Try again.')); });
        }
        const name = ((res.headers.get('Content-Disposition') || '').match(/filename="([^"]+)"/) || [])[1] || 'draw.pdf';
        return res.blob().then(blob => {
          const a = document.createElement('a');
          a.href = URL.createObjectURL(blob); a.download = name;
          document.body.appendChild(a); a.click(); a.remove();
          setTimeout(() => URL.revokeObjectURL(a.href), 10000);
          this.dmToast('Draw PDF downloaded.');
        });
      })
      .catch(e => this.dmToast(e.message || 'The draw PDF could not be made. Try again.', false))
      .finally(() => this.dmSet({pdfBusy: false}));
  }

  // ── Divisions from a spreadsheet (App\Events\Support\DivisionImport) ──────
  // Download the template, choose a file, see every row judged by the server
  // (nothing is written yet), then create them in one request. The server
  // judges the rows again on import — what this page shows is never trusted.
  dmIm(patch) { this.setState(st => ({dm: {...(st.dm || {}), im: {...((st.dm || {}).im || {}), ...patch}}})); }

  dmImOpen() { this.dmSet({addOpen: false, im: {open: true}}); }

  dmImFile(e) {
    const doors = this._dmDoors, input = e && e.target, file = input && input.files && input.files[0];
    if (!doors || !doors.importPreview || !file) return;
    if (input) input.value = '';
    const csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
    const fd = new FormData(); fd.append('file', file);
    this.dmIm({busy: 'read', err: '', rows: null, fileName: file.name});
    fetch(doors.importPreview, {method: 'POST', credentials: 'same-origin', body: fd,
      headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest'}})
      .then(res => res.json().catch(() => ({})).then(j => ({ok: res.ok && j.success !== false, status: res.status, j})))
      .then(({ok, status, j}) => {
        if (!ok) {
          const first = j.errors ? Object.values(j.errors)[0] : null;
          this.dmIm({busy: '', err: (Array.isArray(first) ? first[0] : first) || j.message || (status === 429 ? 'Too many uploads at once — wait a moment.' : 'That file could not be read.')});
          return;
        }
        this.dmIm({busy: '', rows: j.rows || [], ignored: j.ignored || []});
      })
      .catch(() => this.dmIm({busy: '', err: 'Could not reach the server. Try again.'}));
  }

  dmImGo() {
    const T = window.TakeOneFed || {}, im = (this.state.dm || {}).im || {}, doors = this._dmDoors;
    const send = (im.rows || []).filter(r => r.status === 'ready');
    if (!doors || !doors.import || im.busy || !send.length || (im.rows || []).some(r => r.status === 'error')) return;
    this.dmIm({busy: 'save', err: ''});
    const rows = send.map(r => ({line: r.line, name: r.name, gender: r.gender, min_age: r.min_age, max_age: r.max_age,
      min_weight: r.min_weight, max_weight: r.max_weight, capacity: r.capacity}));
    T.roFetch('POST', doors.import, {rows}).then(d => {
      if (!d.__ok) { this.dmIm({busy: '', err: d.message || 'Nothing was created. Try again.', rows: d.rows || im.rows}); return; }
      (d.divisions || []).forEach(div => this.dmUpsert(div));
      this.dmSet({im: {open: false}});
      this.dmToast(d.message || 'Divisions created.');
    });
  }

  dmImVals(dm, css) {
    const im = (dm && dm.im) || {}, doors = this._dmDoors || {}, rows = im.rows || [];
    const span = (a, b, unit) => (a == null && b == null) ? '—' : (a != null && b != null ? a + '–' + b : a != null ? a + '+' : '≤ ' + b) + unit;
    const tag = {ready: ['New', '#2f7a4f'], duplicate: ['Already in the event', '#8a6d20'], error: ['Needs fixing', '#b4483c']};
    const ready = rows.filter(r => r.status === 'ready').length, dup = rows.filter(r => r.status === 'duplicate').length, bad = rows.filter(r => r.status === 'error').length;
    const busy = im.busy === 'save';
    return {
      open: !!im.open, reading: im.busy === 'read', hasErr: !!im.err, err: im.err || '',
      templateUrl: doors.importTemplate || '', hasRows: rows.length > 0, noRows: !rows.length && im.busy !== 'read',
      fileName: im.fileName || '', pickLabel: rows.length ? 'Choose another file' : 'Choose a file',
      summary: ready + ' new' + (dup ? ' · ' + dup + ' already in the event' : '') + (bad ? ' · ' + bad + ' need' + (bad === 1 ? 's' : '') + ' fixing' : ''),
      hasIgnored: (im.ignored || []).length > 0, ignoredLine: 'Not used: ' + (im.ignored || []).join(', '),
      rows: rows.map((r, i) => {
        const g = r.gender === 'Male' ? ['Male', '#2563eb'] : r.gender === 'Female' ? ['Female', '#db2777'] : ['Mixed', '#111827'];
        const t = tag[r.status] || tag.error;
        return {key: i, line: String(r.line), name: r.name || '—', gender: g[0], ink: css('color:' + g[1] + ';font-weight:700'),
          ages: span(r.min_age, r.max_age, ' yrs'), weights: span(r.min_weight, r.max_weight, ' kg'),
          tag: t[0], tagStyle: css('display:inline-block;padding:3px 9px;border-radius:999px;font-size:.62rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;white-space:nowrap;color:' + t[1] + ';background:' + t[1] + '1f'),
          hasErrors: (r.errors || []).length > 0, errors: (r.errors || []).join(' ')};
      }),
      onFile: e => this.dmImFile(e), onGo: () => this.dmImGo(), canGo: ready > 0 && !bad && !im.busy,
      goLabel: busy ? 'Creating…' : (bad ? 'Fix the file first' : 'Create ' + ready + ' division' + (ready === 1 ? '' : 's')),
      goStyle: css('height:44px;padding:0 22px;border:0;border-radius:12px;font-family:inherit;font-size:.84rem;font-weight:700;cursor:pointer;color:#1e2c4f;background:linear-gradient(100deg,#edcd85,#d0a854);'
        + (ready > 0 && !bad && !im.busy ? '' : 'opacity:.5;cursor:default')),
      onClose: () => { if (!busy) this.dmSet({im: {open: false}}); },
    };
  }

  // ── Merging small divisions (App\Events\Support\DivisionMerge) ────────────
  // The server grades every merge green / amber / red with its reasons; this
  // only shows them. A grade shown here is never trusted — the merge endpoint
  // judges again and refuses red, and amber without the consent statement.
  // ⚠️ KEEP IN STEP with the merge sheet in <x-event-divisions>.
  dmMg(patch) { this.setState(st => ({dm: {...(st.dm || {}), mg: {...((st.dm || {}).mg || {}), ...patch}}})); }

  dmMgOpen() {
    this.dmSet({addOpen: false});
    this.dmMg({open: true, picked: {}, into: null, judge: null, consent: {}, busy: '', err: ''});
    this.dmMgLoad();
  }

  dmMgLoad() {
    const T = window.TakeOneFed || {}, doors = this._dmDoors;
    if (!doors || !doors.merge) return;
    this.dmMg({loading: true});
    T.roFetch('GET', doors.merge).then(d => {
      this.dmMg({loading: false, data: d.__ok ? d : null, err: d.__ok ? '' : (d.message || 'The suggestions could not be loaded.')});
    });
  }

  dmMgPick(id) {
    const mg = (this.state.dm || {}).mg || {}, picked = {...(mg.picked || {})};
    if (picked[id]) delete picked[id]; else picked[id] = true;
    const into = picked[mg.into] ? mg.into : null;
    this.dmMg({picked, into, judge: null});
    clearTimeout(this._dmMgT);
    if (Object.keys(picked).length >= 2) this._dmMgT = setTimeout(() => this.dmMgCheck(), 250);
  }

  dmMgInto(id) { this.dmMg({into: id, judge: null}); this.dmMgCheck(); }

  dmMgCheck() {
    const T = window.TakeOneFed || {}, doors = this._dmDoors, mg = (this.state.dm || {}).mg || {};
    const ids = Object.keys(mg.picked || {}).map(Number);
    if (!doors || !doors.merge || ids.length < 2) return;
    const seq = (this._dmMgSeq = (this._dmMgSeq || 0) + 1);
    this.dmMg({judging: true});
    T.roFetch('POST', doors.merge + '/check', {ids, into: mg.into || null}).then(d => {
      if (seq !== this._dmMgSeq) return;
      this.dmMg({judging: false, judge: d.__ok ? d.judgement : null, err: d.__ok ? '' : (d.message || '')});
    });
  }

  dmMgDo(ids, into, key) {
    const T = window.TakeOneFed || {}, doors = this._dmDoors, mg = (this.state.dm || {}).mg || {};
    if (!doors || !doors.merge || mg.busy) return;
    this.dmMg({busy: key});
    T.roFetch('POST', doors.merge, {ids, into, consent: !!(mg.consent || {})[key]}).then(d => {
      this.dmMg({busy: ''});
      if (!d.__ok) { this.dmToast(d.message || 'That did not go through. Try again.', false); if (d.judgement && key === 'pick') this.dmMg({judge: d.judgement}); return; }
      this.dmToast(d.message || 'Divisions merged.');
      this.dmMg({picked: {}, into: null, judge: null, consent: {}});
      this.dmLoad(doors);
      this.dmMgLoad();
    });
  }

  // The officials' handbook PDF — fetched and handed over as a download, the
  // same way the Registrations tab downloads the entry-list PDFs.
  dmMgHandbook() {
    const doors = this._dmDoors, mg = (this.state.dm || {}).mg || {};
    if (!doors || !doors.mergeHandbook || mg.pdfBusy) return;
    this.dmMg({pdfBusy: true});
    fetch(doors.mergeHandbook, {credentials: 'same-origin', headers: {Accept: 'application/pdf, application/json', 'X-Requested-With': 'XMLHttpRequest'}})
      .then(r => {
        const ct = r.headers.get('Content-Type') || '';
        if (r.ok && ct.indexOf('application/pdf') === 0) {
          const m = (r.headers.get('Content-Disposition') || '').match(/filename="([^"]+)"/);
          return r.blob().then(b => {
            const a = document.createElement('a'), href = URL.createObjectURL(b);
            a.href = href; a.download = m ? m[1] : 'merging-handbook.pdf';
            document.body.appendChild(a); a.click(); a.remove();
            setTimeout(() => URL.revokeObjectURL(href), 4000);
            this.dmMg({pdfBusy: false});
          });
        }
        return r.json().catch(() => ({})).then(j => { this.dmMg({pdfBusy: false}); this.dmToast(j.message || (r.status === 429 ? 'Too many downloads — wait a minute.' : 'The PDF could not be made. Try again.'), false); });
      })
      .catch(() => { this.dmMg({pdfBusy: false}); this.dmToast('Could not reach the server. Try again.', false); });
  }

  dmMgVals(dm, css) {
    const mg = (dm && dm.mg) || {}, data = mg.data || {}, rows = ((dm && dm.rows) || []).filter(r => !r.is_heading);
    const tone = {green: ['#2f7a4f', '#e8f5ec', 'Safe'], amber: ['#b45309', '#fff6e6', 'Needs consent'], red: ['#b4483c', '#fcefed', 'Not safe']};
    const dot = {green: '#2f7a4f', amber: '#d97706', red: '#c0392b', info: '#97a1b4'};
    const reasons = j => (j.reasons || []).map((r, k) => ({key: k, text: r.text, dotStyle: css('width:8px;height:8px;border-radius:50%;flex:none;margin-top:5px;background:' + (dot[r.level] || '#97a1b4'))}));
    const card = (j, key) => {
      const t = tone[j.grade] || tone.red, ids = [j.into].concat(j.from || []), ok = !!(mg.consent || {})[key];
      return {key, grade: t[2], gradeStyle: css('display:inline-flex;align-items:center;height:24px;padding:0 10px;border-radius:999px;font-size:.66rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:' + t[0] + ';background:' + t[1]),
        from: j.from_names || '', into: j.into_name || '', counts: (j.moving || 0) + ' moving · ' + (j.after || 0) + ' in the group after',
        reasons: reasons(j), amber: j.grade === 'amber', red: j.grade === 'red', canMerge: j.grade !== 'red',
        consentOn: ok, consentTick: ok ? '✓' : '', consentStyle: css('width:22px;height:22px;border-radius:6px;flex:none;display:grid;place-items:center;font-size:.75rem;font-weight:800;' + (ok ? 'background:#b45309;color:#fff' : 'border:1.5px solid #d9b77a;color:transparent')),
        onConsent: () => this.dmMg({consent: {...(mg.consent || {}), [key]: !ok}}),
        label: mg.busy === key ? 'Merging…' : 'Merge into ' + (j.into_name || ''),
        btnStyle: css('height:42px;padding:0 18px;border:0;border-radius:12px;font-family:inherit;font-size:.8rem;font-weight:700;cursor:pointer;color:#1e2c4f;background:linear-gradient(100deg,#edcd85,#d0a854);overflow-wrap:anywhere;' + ((j.grade === 'amber' && !ok) || mg.busy ? 'opacity:.5;cursor:default' : '')),
        onMerge: () => { if (j.grade === 'red' || (j.grade === 'amber' && !ok)) return; this.dmMgDo(ids, j.into, key); },
        cardStyle: css('padding:14px 16px;border-radius:14px;background:#fff;border:1px solid #eef1f6;border-left:4px solid ' + t[0] + ';display:flex;flex-direction:column;gap:10px')};
    };
    const picked = mg.picked || {}, pickedIds = Object.keys(picked).map(Number);
    const g = r => String(((r.range || {}).gender) || '').toLowerCase();
    return {
      open: !!mg.open, loading: !!mg.loading, hasErr: !!mg.err, err: mg.err || '',
      hasPdf: !!(this._dmDoors && this._dmDoors.mergeHandbook), pdfLabel: mg.pdfBusy ? 'Preparing…' : 'Handbook PDF', onPdf: () => this.dmMgHandbook(),
      onClose: () => { if (!mg.busy) this.dmMg({open: false}); },
      sub: data.limits ? 'Fewer than ' + data.limits.below + ' entrants · weight gap ' + data.limits.consent_gap + '% needs consent, ' + data.limits.max_gap + '% never · ' + data.limits.max_months + ' months across age groups' : '',
      suggestions: (data.suggestions || []).map((j, i) => card(j, 's' + i)), hasSuggestions: (data.suggestions || []).length > 0,
      noSuggestions: !mg.loading && !!mg.data && !(data.suggestions || []).length,
      alone: (data.alone || []).map(a => ({key: a.id, name: a.name, why: a.why})), hasAlone: (data.alone || []).length > 0,
      wide: (data.wide || []).map(w => ({key: w.id, name: w.name, line: w.light + ' kg to ' + w.heavy + ' kg — ' + w.pct + '% apart'})), hasWide: (data.wide || []).length > 0,
      chips: rows.map(r => ({key: r.id, label: r.name + ' · ' + (r.entrants || 0), on: !!picked[r.id],
        style: css('display:inline-flex;align-items:center;gap:6px;min-height:36px;padding:4px 12px;border-radius:10px;font-family:inherit;font-size:.74rem;font-weight:600;cursor:pointer;text-align:left;overflow-wrap:anywhere;'
          + (picked[r.id] ? 'background:#1e2c4f;color:#fff;border:1px solid #1e2c4f' : 'background:#fff;color:#1e2c4f;border:1px solid ' + (g(r) === 'female' ? '#f472b6' : g(r) === 'male' ? '#60a5fa' : '#d5dae3'))),
        onClick: () => this.dmMgPick(r.id)})),
      pickedMany: pickedIds.length >= 2, pickHint: pickedIds.length >= 2 ? '' : 'Pick two or more groups to see whether they can be merged.',
      intoChips: pickedIds.length >= 2 ? rows.filter(r => picked[r.id]).map(r => {
        const on = mg.judge ? mg.judge.into === r.id : mg.into === r.id;
        return {key: r.id, label: r.name, style: css('display:inline-flex;align-items:center;min-height:32px;padding:4px 10px;border-radius:9px;font-family:inherit;font-size:.72rem;font-weight:600;cursor:pointer;overflow-wrap:anywhere;' + (on ? 'background:#fdf8ec;border:2px solid #d0a854;color:#1e2c4f' : 'background:#fff;border:1px solid #d5dae3;color:#4b5568')),
          onClick: () => this.dmMgInto(r.id)};
      }) : [],
      judging: !!mg.judging, hasJudge: !!mg.judge && pickedIds.length >= 2, judge: card(mg.judge || {}, 'pick'),
    };
  }

  // ── Start again (DivisionReset): auto-place, delete all ──────────────────
  // Auto-place puts everyone in no division where the event's rule book says,
  // creating the divisions needed — never moving anyone placed by hand.
  // Delete all asks first, in its own window, and can rebuild in the same go.
  // Auto-place asks WHICH weight table first (owner, 2026-10-15) — the
  // martial art's own tables (WKF, Shotokan, World Taekwondo, IBJJF…) and the
  // host's custom ones, from me.events.divisions.auto-place.systems.
  // mode 'auto' places; 'rebuild' clears every division first, then places.
  dmApOpen(mode) {
    const T = window.TakeOneFed || {}, doors = this._dmDoors;
    if (!doors || !doors.autoPlace || !T.roFetch) return;
    this.dmSet({rsOpen: false, ap: {open: true, mode: mode || 'auto', loading: true, pick: null, data: null}});
    T.roFetch('GET', doors.autoPlace).then(d => {
      const ap = (this.state.dm || {}).ap || {};
      // "Clear and auto-place" empties the event first, so its own divisions are not offered there.
      if (d.__ok && ap.mode === 'rebuild') d = {...d, systems: (d.systems || []).filter(s => !s.own), pick: d.current};
      this.dmSet({ap: {...ap, loading: false, data: d.__ok ? d : null, pick: d.__ok ? (d.pick || d.current || null) : null, err: d.__ok ? '' : (d.message || 'The weight tables could not be loaded.')}});
    });
  }

  dmApGo() {
    const T = window.TakeOneFed || {}, doors = this._dmDoors, dm = this.state.dm || {}, ap = dm.ap || {};
    if (!doors || dm.rsBusy || !ap.pick) return;
    const rebuild = ap.mode === 'rebuild';
    this.dmSet({rsBusy: rebuild ? 'rebuild' : 'auto'});
    const req = rebuild ? T.roFetch('POST', doors.deleteAll, {confirm: true, then_place: true, system: ap.pick})
      : T.roFetch('POST', doors.autoPlace, {system: ap.pick});
    req.then(d => {
      this.dmSet({rsBusy: '', ap: {...((this.state.dm || {}).ap || {}), open: !d.__ok ? true : false}});
      this.dmToast(d.message || (d.__ok ? 'Done.' : 'That did not go through. Try again.'), !!d.__ok);
      this.dmLoad(doors);
    });
  }

  dmApVals(dm, css) {
    const ap = (dm && dm.ap) || {}, data = ap.data || {}, busy = !!(dm && dm.rsBusy), rebuild = ap.mode === 'rebuild';
    const list = data.systems || [], pick = list.find(s => s.key === ap.pick) || null;
    return {open: !!ap.open, loading: !!ap.loading, hasErr: !!ap.err, err: ap.err || '', rebuild,
      sport: data.sport || 'this sport', eyebrow: rebuild ? 'Clear the divisions, then rebuild' : 'Auto-place',
      title: list.some(s => s.own) ? 'Place by what?' : 'Which weight table?',
      sub: 'Everyone in no division is placed by gender, age group and weight class under the table you pick. Hand placements never move.',
      cards: list.map(s => {
        const on = s.key === ap.pick;
        return {key: s.key, label: s.label + (s.note ? ' · ' + s.note : ''), tag: s.own ? 'Your divisions' : (s.custom ? 'Your own table' : (s.current ? 'In use now' : '')), hasTag: !!(s.own || s.custom || s.current),
          groups: (s.groups || []).slice(0, 6).join(' · ') + ((s.groups || []).length > 6 ? ' · +' + ((s.groups || []).length - 6) + ' more' : ''),
          classes: s.own ? (s.hint || 'Fills the divisions you made') : (s.classes ? s.classes + ' weight classes' : 'The sport\u2019s own weight classes'),
          style: css('display:flex;gap:12px;align-items:flex-start;width:100%;text-align:left;padding:14px;border-radius:14px;cursor:pointer;font-family:inherit;'
            + (on ? 'border:2px solid #d0a854;background:#fdf8ec' : 'border:1px solid #e3e7ee;background:#fff')),
          dot: on ? '✓' : '', dotStyle: css('width:22px;height:22px;border-radius:50%;flex:none;display:grid;place-items:center;font-size:.72rem;font-weight:800;margin-top:2px;'
            + (on ? 'background:#1e2c4f;color:#fff' : 'border:1.5px solid #c3cad6;color:transparent')),
          onClick: () => this.dmSet({ap: {...ap, pick: s.key}})};
      }),
      note: pick && !pick.current && !pick.own ? 'Switching to ' + pick.label + ' re-sorts anyone the platform placed, and is refused once a draw exists.' : '',
      hasNote: !!(pick && !pick.current && !pick.own),
      goLabel: busy ? 'Working…' : !pick ? 'Pick a table' : (rebuild ? 'Clear and auto-place with ' : 'Auto-place with ') + pick.label,
      goStyle: css('height:42px;padding:0 20px;border:0;border-radius:12px;font-family:inherit;font-size:.82rem;font-weight:700;cursor:pointer;'
        + (rebuild ? 'background:#c0392b;color:#fff' : 'color:#1e2c4f;background:linear-gradient(100deg,#edcd85,#d0a854)') + (!pick || busy ? ';opacity:.5;cursor:default' : '')),
      onGo: () => this.dmApGo(), onClose: () => { if (!busy) this.dmSet({ap: {...ap, open: false}}); }};
  }

  dmDeleteAll(thenPlace) {
    const T = window.TakeOneFed || {}, doors = this._dmDoors, dm = this.state.dm || {};
    if (!doors || !doors.deleteAll || dm.rsBusy) return;
    if (thenPlace && doors.autoPlace) { this.dmApOpen('rebuild'); return; }
    this.dmSet({rsBusy: thenPlace ? 'rebuild' : 'delete'});
    T.roFetch('POST', doors.deleteAll, {confirm: true, then_place: !!thenPlace}).then(d => {
      this.dmSet({rsBusy: '', rsOpen: false});
      this.dmToast(d.message || (d.__ok ? 'Done.' : 'That did not go through. Try again.'), !!d.__ok);
      this.dmLoad(doors);
    });
  }

  // ── "Not in a division": who they are, and where each can go ─────────────
  // The list comes from the platform (me.events.divisions.unplaced — the event's
  // own entries with no group, each with the divisions they fit, best first);
  // placing is the editor's own people endpoint (base/{id}/members), which
  // authorises and records it. Before that answers, the card's own rows
  // (state.regs) are shown so the popup is never empty for a moment.
  dmUpOpen() {
    this.dmSet({upOpen: true, upMore: {}, upBusy: ''});
    this.dmUpLoad();
  }

  dmUpLoad() {
    const T = window.TakeOneFed || {}, doors = this._dmDoors;
    if (!doors || !doors.unplaced || !T.roFetch) return;
    this.dmSet({upLoading: true});
    T.roFetch('GET', doors.unplaced).then(d => this.dmSet({upLoading: false, upData: d.__ok ? (d.people || []) : null}));
  }

  dmUpPlace(p, divId, divName) {
    const T = window.TakeOneFed || {}, doors = this._dmDoors, dm = this.state.dm || {};
    if (!doors || dm.upBusy) return;
    this.dmSet({upBusy: p.entry + ':' + divId});
    T.roFetch('PUT', doors.base + '/' + divId + '/members', {add: [p.entry]}).then(d => {
      this.dmSet({upBusy: ''});
      if (!d.__ok) { this.dmToast(d.message || 'That did not go through. Try again.', false); return; }
      // Out of the list at once; the reload confirms it and the cards recount.
      this.dmSet({upData: ((this.state.dm || {}).upData || []).filter(x => x.entry !== p.entry)});
      this.dmToast(p.name + ' placed in ' + divName + '.');
      if (d.division) this.dmUpsert(d.division);
      this.dmUpLoad();
      if (doors) this.dmLoad(doors);
    });
  }

  dmUnplacedVals(dm, css) {
    dm = dm || {};
    const regs = ((this.state || {}).regs || []).filter(r => r && r.unplaced);
    const byTk = {}; ((this.state || {}).regs || []).forEach(r => { if (r && r.takeoneId) byTk[r.takeoneId] = r; });
    const server = Array.isArray(dm.upData);
    // Everyone the card counts appears here. The platform's list holds the ones
    // on the event's entry list (placeable); a club-entered athlete who is not
    // on it yet — their club's entry not approved, say — is counted by the card
    // too, so they are listed with the reason instead of silently missing.
    const fromServer = server ? dm.upData.map(p => {
      const r = byTk[p.takeone_id] || {};
      return {entry: p.entry, name: p.name, club: p.club || r.club, photo: r.photo || '', gender: p.gender === 'Female' ? 'F' : p.gender === 'Male' ? 'M' : '',
        age: p.age, weight: p.weight, suggested: p.suggested, options: p.options || []};
    }) : [];
    const known = {}; (server ? dm.upData : []).forEach(p => { if (p.takeone_id) known[p.takeone_id] = true; });
    const list = fromServer.concat(regs.filter(r => !server || !known[r.takeoneId]).map(r => ({entry: null, name: r.name, club: r.club, photo: r.photo || '',
      gender: r.gender, age: r.age, weight: r.weight, suggested: null, options: null,
      notListed: server ? (r.status === 'Approved' ? 'Not on the event\u2019s entry list yet — open the Participants tab to add them' : 'Their club\u2019s entry is not approved yet — approve it in the Participants tab, then place them here') : ''})));
    const gender = g => g === 'F' ? ['Female', '#db2777'] : g === 'M' ? ['Male', '#2563eb'] : ['No gender', '#97a1b4'];
    const has = v => v !== null && v !== undefined && v !== '' && !isNaN(v);
    const close = () => this.dmSet({upOpen: false});
    const more = dm.upMore || {};
    const chip = (on, busy) => css('display:inline-flex;align-items:center;gap:6px;min-height:34px;padding:4px 12px;border-radius:10px;font-family:inherit;font-size:.74rem;font-weight:700;cursor:pointer;text-align:left;overflow-wrap:anywhere;'
      + (on ? 'border:0;color:#1e2c4f;background:linear-gradient(100deg,#edcd85,#d0a854)' : 'border:1px solid #d5dae3;color:#1e2c4f;background:#fff') + (busy ? ';opacity:.55;cursor:wait' : ''));
    return {
      open: !!dm.upOpen, count: String(list.length), onClose: close, empty: list.length === 0, loading: !!dm.upLoading && !server,
      rows: list.slice().sort((a, b) => String(a.name || '').localeCompare(String(b.name || ''), undefined, {sensitivity: 'base'})).map((p, i) => {
        const g = gender(p.gender), key = p.entry || ('r' + i);
        const missing = [has(p.weight) && Number(p.weight) > 0 ? '' : 'weight', has(p.age) ? '' : 'birthdate', p.gender ? '' : 'gender'].filter(Boolean);
        const opts = p.options || [], best = opts.find(o => o.id === p.suggested) || null;
        const others = opts.filter(o => !best || o.id !== best.id);
        const open = !!more[key];
        const place = o => ({key: o.id, label: (o.fit === 'unknown' ? '? ' : '') + o.name, title: o.fit === 'unknown' ? 'Cannot be confirmed — something about them is not on file' : 'Fits this division',
          style: chip(false, !!dm.upBusy), onClick: () => this.dmUpPlace(p, o.id, o.name)});
        return {key, name: p.name || '—', club: p.club && p.club !== '—' ? p.club : 'Independent',
          hasPhoto: !!p.photo, noPhoto: !p.photo, photo: p.photo || '',
          initials: String(p.name || '?').split(/\s+/).map(x => x[0] || '').slice(0, 2).join('').toUpperCase(),
          gender: g[0], genderStyle: css('font-size:.7rem;font-weight:700;color:' + g[1]),
          meta: [has(p.age) ? p.age + ' yrs' : 'No age', has(p.weight) && Number(p.weight) > 0 ? Math.round(Number(p.weight) * 10) / 10 + ' kg' : 'No weight'].join(' · '),
          hasMissing: missing.length > 0, missing: 'Missing ' + missing.join(', ') + (best ? '' : ' — fill it in so a division can be confirmed'),
          canPlace: !!p.entry && opts.length > 0,
          hasBest: !!best, bestLabel: best ? (dm.upBusy === p.entry + ':' + best.id ? 'Placing…' : 'Place in ' + best.name) : '',
          bestStyle: chip(true, !!dm.upBusy), onBest: () => best && this.dmUpPlace(p, best.id, best.name),
          hasOthers: others.length > 0, othersLabel: open ? 'Hide other divisions' : (best ? 'Other divisions (' + others.length + ')' : 'Choose a division (' + others.length + ')'),
          onOthers: () => this.dmSet({upMore: {...more, [key]: !open}}), othersOpen: open, others: others.map(place),
          notListed: !!p.notListed, notListedText: p.notListed || '',
          noFit: !!p.entry && opts.length === 0, noFitText: 'No division takes them — add one with + Add, or check their age, weight and gender.',
          avatarStyle: css('width:36px;height:48px;border-radius:8px;flex:none;object-fit:cover;background:' + (p.gender === 'F' ? '#fce7f3' : '#dbeafe') + ';display:grid;place-items:center;font-size:.72rem;font-weight:700;color:' + g[1] + ';overflow:hidden')};
      }),
      onAthletes: () => { close(); this.setState({tab: 'regs'}); },
    };
  }

  // ── The values the tab draws ──────────────────────────────────────────────
  dmVals(sel, divCards) {
    const s = this.state, T = window.TakeOneFed || {};
    const doors = (T.divisionDoors && sel && sel.id) ? T.divisionDoors(sel.id) : null;
    if (!doors) return {on: false, off: true};
    this._dmDoors = doors;
    this.dmListen(doors);
    const dm = s.dm && s.dm.event === doors.event ? s.dm : null;
    if (!dm && this._dmLoading !== doors.event) setTimeout(() => this.dmLoad(doors), 0);
    const css = c => this.cssToObj(c);
    const loading = !dm || (!dm.loaded && !dm.err);
    const rows = (dm && dm.rows) || [];
    // Weight classes are a table and nothing else (owner, 2026-10-16): the
    // Cards and Order views are gone from the Draw tab.
    const view = 'table';
    const ink = name => {
      const m = /^(.*?)\b(Male|Female)\b(.*)$/i.exec(String(name || ''));
      return m ? {a: m[1], g: m[2], b: m[3], gStyle: css('font-weight:700;color:' + (/^f/i.test(m[2]) ? '#db2777' : '#2563eb'))} : {a: String(name || ''), g: '', b: '', gStyle: {}};
    };
    const genderOf = r => { const g = String(((r.range || {}).gender) || '').toLowerCase(); return g === 'male' ? 'Male' : g === 'female' ? 'Female' : 'Mixed'; };
    const status = r => r.fought > 0 ? ['Running', '#b45309'] : r.matches > 0 ? ['Drawn', '#2f7a4f'] : r.entrants >= 2 ? ['Ready to draw', '#2f7a4f'] : ['Needs 2+ entries', '#8a6d20'];
    const back = encodeURIComponent(window.location.pathname + window.location.search);
    const openDraw = r => { if (doors.draw) window.location.assign(doors.draw + '?division=' + encodeURIComponent(r.id) + '&back=' + back); };
    const max = Math.max(1, ...rows.map(r => r.entrants || 0));
    // What the merge judge says about each small group (DivisionMerge).
    const sg = (dm && dm.sg) || null, below = (sg && sg.limits && sg.limits.below) || 3;
    const sugOf = {}, aloneOf = {};
    if (sg) {
      (sg.suggestions || []).forEach(j => (j.from || []).forEach(id => { sugOf[id] = j; }));
      (sg.alone || []).forEach(a => { aloneOf[a.id] = a.why; });
    }
    const mergeBits = r => {
      const n = r.entrants || 0, small = !!doors.merge && n >= 1 && n < below, j = sugOf[r.id], why = aloneOf[r.id];
      if (!small) return {hasMerge: false, mergeTag: '', hasSug: false, hasWhy: false};
      const key = j ? j.ids.join('-') : '', armed = (dm || {}).sgArm === key, busy = (dm || {}).sgBusy === key || (dm || {}).sgBusy === 'all';
      const green = j && j.grade === 'green';
      return {hasMerge: true, mergeTag: n === 1 ? 'Alone' : n + ' entrants',
        mergeTagStyle: css('position:absolute;top:12px;right:46px;height:22px;padding:0 9px;border-radius:999px;font-size:.6rem;font-weight:800;letter-spacing:.1em;text-transform:uppercase;display:inline-flex;align-items:center;'
          + (n === 1 ? 'background:#b45309;color:#fff' : 'background:rgba(255,255,255,.8);color:#8a6d20;border:1px solid #e4d3a8')),
        hasSug: !!j, sugLine: j ? (green ? 'Safe to merge into ' : 'Can merge into ') + j.into_name + (green ? '' : ' — needs consent') : '',
        sugWhy: j ? (j.headline || '') : '',
        sugBox: css('margin-top:12px;padding:10px 12px;border-radius:12px;background:rgba(255,255,255,.85);border:1px solid ' + (green ? '#9fd4b2' : '#f1d9a8')),
        sugLineStyle: css('font-size:.76rem;font-weight:700;line-height:1.35;overflow-wrap:anywhere;color:' + (green ? '#1f6f43' : '#8a4b08')),
        sugBtn: busy ? 'Merging…' : armed ? 'Confirm merge' : green ? '⇄ Merge' : 'Review…',
        sugBtnStyle: css('margin-top:8px;height:32px;padding:0 12px;border-radius:9px;font-family:inherit;font-size:.72rem;font-weight:700;cursor:pointer;'
          + (armed ? 'border:0;background:#1e2c4f;color:#fff' : green ? 'border:0;color:#1e2c4f;background:linear-gradient(100deg,#edcd85,#d0a854)' : 'border:1px solid #d9b77a;background:#fff6e6;color:#8a4b08') + (busy ? ';opacity:.6;cursor:wait' : '')),
        onSug: e => this.dmCardMerge(j, e),
        hasWhy: !j && !!why, whyLine: why ? 'No safe merge — ' + why : '',
        whyStyle: css('margin-top:10px;font-size:.7rem;line-height:1.4;color:#9b2c22;background:rgba(255,255,255,.75);border-radius:10px;padding:8px 10px'),
        stop: e => { if (e && e.stopPropagation) e.stopPropagation(); }};
    };

    const tPos = v => v !== null && v !== undefined && v !== '' && Number(v) > 0;
    const tSpan = (a, b, ok, unit) => !ok(a) && !ok(b) ? '—' : ((ok(a) && ok(b)) ? a + '–' + b : ok(a) ? a + '+' : '≤ ' + b) + unit;
    const tAge = r => { const g = r.range || {}; return tSpan(g.min_age, g.max_age, tPos, ''); };
    const tKg = r => { const g = r.range || {}, kg = v => tPos(v) && Number(v) < 200; return !kg(g.min_weight) && !kg(g.max_weight) ? 'Open' : tSpan(g.min_weight, g.max_weight, kg, ' kg'); };
    const tPill = c => 'display:inline-block;padding:3px 10px;border-radius:999px;font-size:.64rem;font-weight:600;letter-spacing:.08em;text-transform:uppercase;white-space:nowrap;color:' + c + ';background:' + c + '1f';
    const card = (r, i, n) => {
      const g = genderOf(r), st = status(r), nm = ink(r.name);
      return {...mergeBits(r), key: r.id, rank: String(n + 1), a: nm.a, g: nm.g, b: nm.b, gStyle: nm.gStyle, gender: g,
        genderStyle: css('font-size:.8rem;font-weight:600;color:' + (g === 'Female' ? '#db2777' : g === 'Male' ? '#2563eb' : '#7c6bf5')),
        detail: this.dmSummary(r, dm || {}), count: String(r.entrants || 0), bracket: st[0], bstyle: css('font-weight:600;color:' + st[1]),
        ages: (r.range && (r.range.min_age || r.range.max_age)) ? this.dmAgeBand(r.range.min_age, r.range.max_age) || '—' : '—',
        // The Table view's own columns (Participants-table look, owner 2026-10-15).
        ageNum: tAge(r), weightNum: tKg(r), pill: st[0], pillStyle: css(tPill(st[1])),
        cardStyle: css('position:relative;border-radius:16px;border-top:3px solid #d8b25f;padding:18px 18px 14px;box-shadow:0 10px 30px rgba(30,44,79,.08);cursor:' + (doors.draw ? 'pointer' : 'default') + ';transition:transform .15s ease,box-shadow .15s ease;'
          + (g === 'Female' ? 'background:#fbcfe8;border:1px solid #f472b6;border-top:3px solid #d8b25f' : g === 'Male' ? 'background:#bfdbfe;border:1px solid #60a5fa;border-top:3px solid #d8b25f' : 'background:#ede9fe;border:1px solid #a78bfa;border-top:3px solid #d8b25f')),
        bar: css('height:6px;border-radius:3px;background:linear-gradient(100deg,#edcd85,#d0a854);width:' + (100 * (r.entrants || 0) / max).toFixed(1) + '%'),
        onOpen: () => openDraw(r),
        onEdit: e => { if (e && e.stopPropagation) e.stopPropagation(); this.dmOpenEditor(r, i); },
      };
    };

    // Sorting the Cards and Table views (owner, 2026-10-15: divisions made in
    // any order were scattered). 'event' keeps the event's own order; age,
    // weight and gender each fall back on the other two, ascending or
    // descending. Remembered per browser — a viewing preference, nothing more.
    let sortPref = this._dmSortPref;
    if (!sortPref) {
      try { sortPref = JSON.parse(localStorage.getItem('takeone_fed_div_sort') || 'null'); } catch (e) { sortPref = null; }
      sortPref = sortPref && ['event', 'age', 'weight', 'gender', 'entrants'].includes(sortPref.by) ? {by: sortPref.by, dir: sortPref.dir === -1 ? -1 : 1} : {by: 'event', dir: 1};
      this._dmSortPref = sortPref;
    }
    const sortBy = sortPref.by, sortDir = sortPref.dir;
    const setSort = (by, dir) => {
      this._dmSortPref = {by, dir};
      try { localStorage.setItem('takeone_fed_div_sort', JSON.stringify(this._dmSortPref)); } catch (e) {}
      this.dmSet({sortTick: Date.now()});
    };
    const numOr = (v, d) => (v === null || v === undefined || v === '' || isNaN(Number(v))) ? d : Number(v);
    const ageKey = r => { const g = r.range || {}; return [numOr(g.min_age, 0), numOr(g.max_age, 999)]; };
    const kgKey = r => { const g = r.range || {}, top = numOr(g.max_weight, 999); return [top <= 0 || top >= 200 ? 999 : top, numOr(g.min_weight, 0)]; };
    const gRank = r => ({Male: 0, Female: 1, Mixed: 2})[genderOf(r)];
    const keyOf = r => sortBy === 'entrants' ? [r.entrants || 0, ...ageKey(r), ...kgKey(r)]
      : sortBy === 'age' ? [...ageKey(r), ...kgKey(r)]
      : sortBy === 'weight' ? [...kgKey(r), ...ageKey(r)]
      : [gRank(r), ...ageKey(r), ...kgKey(r)];
    const cmp = (a, b) => {
      if (sortBy === 'event') return 0;
      const ka = keyOf(a.r), kb = keyOf(b.r);
      for (let k = 0; k < ka.length; k++) if (ka[k] !== kb[k]) return sortDir * (ka[k] - kb[k]);
      return String(a.r.name || '').localeCompare(String(b.r.name || ''), undefined, {numeric: true, sensitivity: 'base'});
    };
    const sortChip = on => css('display:inline-flex;align-items:center;gap:5px;height:32px;padding:0 11px;border-radius:8px;border:0;font-family:inherit;font-size:.74rem;cursor:pointer;'
      + (on ? 'font-weight:700;color:#1e2c4f;background:#fff;box-shadow:0 2px 6px rgba(30,44,79,.12)' : 'font-weight:500;color:#6b7689;background:transparent'));
    const sortVals = {
      show: view === 'cards',
      chips: [['event', 'Event order'], ['age', 'Age'], ['weight', 'Weight'], ['gender', 'Gender']].map(([k, l]) => ({key: k, label: l,
        pressed: sortBy === k ? 'true' : 'false', style: sortChip(sortBy === k), onClick: () => setSort(k, sortBy === k ? sortDir : 1)})),
      hasDir: sortBy !== 'event',
      dirLabel: sortDir === 1 ? '↑ Ascending' : '↓ Descending',
      dirTitle: sortDir === 1 ? 'Youngest / lightest first — tap for the other way' : 'Oldest / heaviest first — tap for the other way',
      onDir: () => setSort(sortBy, -sortDir),
      dirStyle: css('height:40px;padding:0 12px;border:1px solid #d5dae3;border-radius:11px;font-family:inherit;font-size:.76rem;font-weight:700;color:#1e2c4f;background:#fff;cursor:pointer;white-space:nowrap')
    };

    // Gender sections, each in the event's own order (or the chosen sort).
    const divs = rows.map((r, i) => ({r, i})).filter(x => !x.r.is_heading);
    // A division nobody is in is not listed (owner, 2026-10-15) — "Show N empty"
    // brings them back. The Order view keeps every row: it is for arranging.
    // Every division is listed, empty or not (owner, 2026-10-16).
    const listedAll = divs;
    // The filter bar over Cards and Table (owner, 2026-10-15): gender, where
    // the draw stands, and an age and a weight window. A division is kept when
    // its own range OVERLAPS the window — "8–9" finds the Under-10s and the
    // 8–11s alike. A viewing aid only; it never changes a division.
    const flt = {g: 'all', st: 'all', aMin: '', aMax: '', wMin: '', wMax: '', ...((dm && dm.flt) || {})};
    const setFlt = patch => this.dmSet({flt: {...flt, ...patch}});
    const fnum = v => { const t = String(v == null ? '' : v).trim().replace(',', '.'); return t === '' || isNaN(Number(t)) ? null : Number(t); };
    const stKey = r => r.fought > 0 ? 'running' : r.matches > 0 ? 'drawn' : (r.entrants || 0) >= 2 ? 'ready' : 'few';
    const overlaps = (lo, hi, fLo, fHi) => (fLo === null || hi >= fLo) && (fHi === null || lo <= fHi);
    const fa = [fnum(flt.aMin), fnum(flt.aMax)], fw = [fnum(flt.wMin), fnum(flt.wMax)];
    const keep = r => {
      const g = r.range || {};
      if (flt.g !== 'all' && genderOf(r) !== flt.g) return false;
      if (flt.st !== 'all' && stKey(r) !== flt.st) return false;
      if ((fa[0] !== null || fa[1] !== null) && !overlaps(numOr(g.min_age, 0), numOr(g.max_age, 999), fa[0], fa[1])) return false;
      if (fw[0] !== null || fw[1] !== null) {
        const top = numOr(g.max_weight, 0);
        if (!overlaps(numOr(g.min_weight, 0), top <= 0 ? 9999 : top, fw[0], fw[1])) return false;
      }
      return true;
    };
    const listed = listedAll.filter(x => keep(x.r));
    const anyFilter = flt.g !== 'all' || flt.st !== 'all' || [flt.aMin, flt.aMax, flt.wMin, flt.wMax].some(v => String(v || '').trim() !== '');
    const fChip = (on, c) => css('height:32px;padding:0 14px;border-radius:999px;font-family:inherit;font-size:.74rem;font-weight:600;cursor:pointer;white-space:nowrap;transition:background .15s,border-color .15s;'
      + (on ? 'background:#fdf6e6;border:1px solid #caa14f;box-shadow:0 2px 8px rgba(202,161,79,.22);color:#8a6d20' : 'background:#fff;border:1px solid #d5dae3;color:' + (c || '#5d6880')));
    const fBox = v => css('height:32px;width:52px;min-width:0;max-width:52px;box-sizing:border-box;padding:0 6px;border-radius:999px;font-family:inherit;font-size:.74rem;font-weight:600;text-align:center;color:#1e2c4f;outline:none;font-variant-numeric:tabular-nums;'
      + (String(v || '').trim() !== '' ? 'background:#fdf6e6;border:1px solid #caa14f' : 'background:#fff;border:1px solid #d5dae3'));
    const hasMixed = listedAll.some(x => genderOf(x.r) === 'Mixed');
    const filterVals = {
      show: view === 'cards' && divs.length > 0, inTable: view === 'table' && divs.length > 0,
      genders: [['all', 'All', ''], ['Male', 'Male', '#2563eb'], ['Female', 'Female', '#db2777']].concat(hasMixed ? [['Mixed', 'Mixed', '#7c6bf5']] : [])
        .map(([k, l, c]) => ({key: k, label: l, pressed: String(flt.g === k), style: fChip(flt.g === k, c), onClick: () => setFlt({g: k})})),
      statuses: [['all', 'All'], ['ready', 'Ready to draw'], ['few', 'Needs 2+'], ['drawn', 'Drawn'], ['running', 'Running']]
        .map(([k, l]) => ({key: k, label: l, pressed: String(flt.st === k), checked: flt.st === k ? '\u2713' : '',
          style: css('width:100%;display:flex;align-items:center;justify-content:space-between;gap:12px;padding:9px 12px;border:0;border-radius:10px;font-family:inherit;font-size:.78rem;cursor:pointer;text-align:start;'
            + (flt.st === k ? 'background:#fdf6e6;color:#8a6d20;font-weight:700' : 'background:transparent;color:#1e2c4f;font-weight:600')),
          onClick: () => this.dmSet({flt: {...flt, st: k}, stOpen: false})})),
      // Status is one dropdown, not five chips (owner, 2026-10-16), to save room.
      stOpen: !!(dm && dm.stOpen), stCaret: (dm && dm.stOpen) ? '\u25B4' : '\u25BE',
      stLabel: ({all: 'All', ready: 'Ready to draw', few: 'Needs 2+', drawn: 'Drawn', running: 'Running'})[flt.st] || 'All',
      stStyle: css('display:inline-flex;align-items:center;gap:8px;height:32px;padding:0 12px 0 14px;border-radius:999px;font-family:inherit;font-size:.74rem;font-weight:600;cursor:pointer;white-space:nowrap;transition:background .15s,border-color .15s;'
        + (flt.st !== 'all' ? 'background:#fdf6e6;border:1px solid #caa14f;box-shadow:0 2px 8px rgba(202,161,79,.22);color:#8a6d20' : 'background:#fff;border:1px solid #d5dae3;color:#5d6880')),
      onStToggle: e => { if (e && e.stopPropagation) e.stopPropagation(); this.dmSet({stOpen: !(dm && dm.stOpen)}); },
      onStClose: () => this.dmSet({stOpen: false}),
      stStop: e => { if (e && e.stopPropagation) e.stopPropagation(); },
      aMin: flt.aMin, aMax: flt.aMax, wMin: flt.wMin, wMax: flt.wMax,
      aMinStyle: fBox(flt.aMin), aMaxStyle: fBox(flt.aMax), wMinStyle: fBox(flt.wMin), wMaxStyle: fBox(flt.wMax),
      onAMin: e => setFlt({aMin: e.target.value}), onAMax: e => setFlt({aMax: e.target.value}),
      onWMin: e => setFlt({wMin: e.target.value}), onWMax: e => setFlt({wMax: e.target.value}),
      count: listed.length + ' of ' + listedAll.length, any: anyFilter,
      onReset: () => this.dmSet({flt: null}),
      noMatch: anyFilter && listedAll.length > 0 && listed.length === 0,
    };
    const genderOrder = [['Male', '#2563eb'], ['Female', '#db2777'], ['Mixed', '#7c6bf5']];
    if (sortBy === 'gender' && sortDir === -1) genderOrder.splice(0, 2, genderOrder[1], genderOrder[0]);
    const sections = genderOrder.map(([g, color]) => {
      const list = listed.filter(x => genderOf(x.r) === g).slice().sort(cmp);
      return {title: g, has: list.length > 0, count: String(list.length),
        dotStyle: css('width:10px;height:10px;border-radius:50%;flex:none;background:' + color),
        titleStyle: css('font-size:.7rem;font-weight:700;letter-spacing:.2em;text-transform:uppercase;color:' + color),
        cards: list.map((x, n) => card(x.r, x.i, n))};
    }).filter(sec => sec.has);
    const unplaced = (divCards || []).find(d => d.unplaced);
    // The Table view: one flat list in the chosen order, headers that sort.
    const tableRows = listed.slice().sort((a, b) => sortBy === 'event' ? a.i - b.i : cmp(a, b) || a.i - b.i).map((x, n) => card(x.r, x.i, n));
    const th = by => {
      const on = sortBy === by;
      return {ariaSort: on && by !== 'event' ? (sortDir === 1 ? 'ascending' : 'descending') : 'none', arrow: on && by !== 'event' ? (sortDir === 1 ? '↑' : '↓') : '',
        style: css('display:inline-flex;align-items:center;gap:4px;padding:0;border:0;background:none;font-family:inherit;font-size:.6rem;letter-spacing:.1em;text-transform:uppercase;cursor:pointer;text-align:start;color:' + (on ? '#8a6d20;font-weight:700' : '#97a1b4')),
        onClick: () => setSort(by, on && by !== 'event' ? -sortDir : 1)};
    };
    const tableHead = {division: th('event'), gender: th('gender'), age: th('age'), weight: th('weight'), entrants: th('entrants')};

    // The order list — headings included, drag to arrange.
    const zone = dm && dm.zone, dragId = this._dmDrag || null;
    const orderRows = rows.map((r, i) => {
      const nm = ink(r.name), zt = zone && zone.id === r.id && !zone.after, zb = zone && zone.id === r.id && zone.after;
      const g = genderOf(r);
      return {key: r.id, heading: !!r.is_heading, row: !r.is_heading, a: nm.a, g: nm.g, b: nm.b, gStyle: nm.gStyle,
        name: r.name || 'Untitled division', detail: r.is_heading ? 'Heading' : this.dmSummary(r, dm || {}),
        style: css('display:flex;align-items:center;gap:12px;padding:' + (r.is_heading ? '12px 14px' : '11px 14px') + ';border-radius:12px;margin:4px 0;cursor:grab;transition:box-shadow .15s,opacity .15s;'
          + (r.is_heading ? 'background:#1e2c4f;color:#fff;' : 'background:#fff;border:1px solid #eef1f6;border-left:4px solid ' + (g === 'Female' ? '#db2777' : g === 'Male' ? '#2563eb' : '#7c6bf5') + ';')
          + (dragId === r.id ? 'opacity:.35;' : '')
          + 'box-shadow:' + ([zt ? 'inset 0 3px 0 #d0a854' : '', zb ? 'inset 0 -3px 0 #d0a854' : ''].filter(Boolean).join(',') || 'none')),
        subStyle: css('font-size:.72rem;' + (r.is_heading ? 'color:rgba(255,255,255,.7)' : 'color:#6b7689')),
        nameStyle: css('font-size:.86rem;font-weight:' + (r.is_heading ? '800;letter-spacing:.06em;text-transform:uppercase' : '600') + ';overflow-wrap:anywhere'),
        upOff: i === 0, downOff: i === rows.length - 1,   // real booleans: disabled="false" would still disable
        onUp: e => { e.stopPropagation(); if (i > 0) this.dmMove(r.id, i - 1); },
        onDown: e => { e.stopPropagation(); if (i < rows.length - 1) this.dmMove(r.id, i + 2); },
        onClick: () => this.dmOpenEditor(r, i),
        onDragStart: e => { this._dmDrag = r.id; try { e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', 'd' + r.id); } catch (x) {} this.dmSet({zone: null}); },
        onDragOver: e => {
          if (!this._dmDrag) return; e.preventDefault();
          const b = e.currentTarget.getBoundingClientRect(), after = e.clientY > b.top + b.height / 2, z = (this.state.dm || {}).zone;
          if (!z || z.id !== r.id || z.after !== after) this.dmSet({zone: {id: r.id, after}});
        },
        onDrop: e => {
          e.preventDefault(); const src = this._dmDrag, z = (this.state.dm || {}).zone; this._dmDrag = null;
          if (!src || !z) { this.dmSet({zone: null}); return; }
          const at = rows.findIndex(x => x.id === z.id) + (z.after ? 1 : 0);
          this.dmMove(src, at);
        },
        onDragEnd: () => { this._dmDrag = null; if ((this.state.dm || {}).zone) this.dmSet({zone: null}); },
      };
    });

    // ── The editor ──
    const ed = (dm && dm.ed) || {}, f = ed.form || {}, isHeading = !!f.is_heading;
    const pick = (on, c) => css('display:inline-flex;align-items:center;justify-content:center;gap:6px;min-height:38px;padding:0 14px;border-radius:10px;font-family:inherit;font-size:.78rem;font-weight:600;cursor:pointer;'
      + (on ? 'background:' + (c || '#1e2c4f') + ';color:#fff;border:1px solid ' + (c || '#1e2c4f') : 'background:#fff;color:#1e2c4f;border:1px solid #d5dae3'));
    const bandTab = on => 'height:34px;padding:0 14px;border-radius:10px;font-family:inherit;font-size:.78rem;font-weight:700;cursor:pointer;'
      + (on ? 'background:#edcd85;color:#1e2c4f;border:1px solid #edcd85' : 'background:rgba(255,255,255,.1);color:#fff;border:1px solid rgba(255,255,255,.28)');
    const genders = [['', 'Any', ''], ['Male', 'Male', '#2563eb'], ['Female', 'Female', '#db2777']].map(([v, l, c]) => ({key: v || 'any', label: l, style: pick(f.gender === v, c || '#1e2c4f'), onClick: () => this.dmFormChange({gender: v})}));
    const field = (k, numeric) => e => this.dmFormChange({[k]: e.target.value});
    const beltChips = (dm && dm.belts || []).map(b => {
      const on = (f.belts || []).indexOf(b.value) >= 0;
      return {key: b.value, label: b.label, style: css('display:inline-flex;align-items:center;gap:7px;height:34px;padding:0 12px;border-radius:999px;font-family:inherit;font-size:.74rem;font-weight:600;cursor:pointer;border:' + (on ? '2px solid #1e2c4f' : '1px solid #d5dae3') + ';background:' + (on ? '#f3f5f9' : '#fff') + ';color:#1e2c4f'),
        swatch: css('width:16px;height:8px;border-radius:2px;background:' + b.bg + ';box-shadow:inset 0 0 0 1px rgba(0,0,0,.18)'),
        onClick: () => { const list = (f.belts || []).slice(), at = list.indexOf(b.value); if (at >= 0) list.splice(at, 1); else list.push(b.value); this.dmFormChange({belts: list}); }};
    });
    const formats = [['knockout', 'Bracket', 'A knockout draw — lose once and you are out.'], ['round_robin', 'Round robin', 'Everyone meets everyone; the leaders go through to a final.']]
      .map(([k, l, h]) => ({key: k, label: l, hint: h, on: f.format === k, style: css('flex:1 1 220px;text-align:left;padding:12px 14px;border-radius:12px;cursor:pointer;font-family:inherit;border:' + (f.format === k ? '2px solid #d0a854;background:#fdf8ec' : '1px solid #e3e7ee;background:#fff')),
        onClick: () => this.dmFormChange({format: k})}));
    const streams = (dm && dm.streams || []);
    const streamChips = streams.length > 1 ? [{key: '', label: 'Any'}].concat(streams).map(st => ({key: st.key || 'any', label: st.label, style: pick((f.stream || '') === (st.key || '')), onClick: () => this.dmFormChange({stream: st.key || ''})})) : [];
    const days = (dm && dm.days) || 1;
    const phaseRows = days > 1 ? (dm.phases || []).map(ph => ({key: ph.key, label: ph.label,
      days: Array.from({length: days}, (_, k) => k + 1).map(day => ({key: day, label: 'Day ' + day, style: pick(Number((f.schedule || {})[ph.key] || 1) === day),
        onClick: () => this.dmFormChange({schedule: {...(f.schedule || {}), [ph.key]: day}})}))})) : [];
    const pv = ed.preview || {};
    const fitsLine = pv.loading ? 'Counting…' : !pv.matched ? 'Nobody entered fits this yet' : pv.matched === 1 ? 'One person fits this' : pv.matched + ' people fit this';
    const fitsSub = [pv.total ? 'of ' + pv.total + ' entered' : '', pv.here ? pv.here + ' already in it' : '', pv.unknown ? pv.unknown + ' unconfirmed' : ''].filter(Boolean).join(' · ');
    const roster = ed.roster || [], picked = ed.picked || {};
    const isIn = p => (picked[p.person_id] !== undefined ? picked[p.person_id] : !!p.here);
    const ptab = ed.peopleTab || 'selected';
    const visible = ptab === 'all' ? roster : ptab === 'fit' ? roster.filter(p => p.fit !== 'out') : roster.filter(isIn);
    const changed = Object.keys(picked).length;
    const peopleRows = visible.map(p => {
      const inNow = isIn(p);
      return {key: p.person_id, name: p.name, photo: p.photo || '', hasPhoto: !!p.photo, noPhoto: !p.photo, initials: String(p.name || '?').split(/\s+/).map(x => x[0]).slice(0, 2).join('').toUpperCase(),
        meta: [p.age != null ? p.age + ' yrs' : '', p.weight != null ? p.weight + ' kg' : '', p.belt ? p.belt + ' belt' : ''].filter(Boolean).join(' · '),
        where: !p.here && p.division_name ? 'In ' + p.division_name : '', hasWhere: !p.here && !!p.division_name,
        badge: p.fit === 'out' ? 'Outside the range' : p.fit === 'unknown' ? 'Unconfirmed' : '', hasBadge: p.fit === 'out' || p.fit === 'unknown',
        badgeStyle: css('font-size:.6rem;font-weight:700;padding:2px 7px;border-radius:999px;' + (p.fit === 'out' ? 'background:#fcefed;color:#b4483c' : 'background:#fff6e6;color:#b45309')),
        acts: (p.activities || []).join(' · '), hasActs: (p.activities || []).length > 0,
        tick: inNow ? '✓' : '', tickStyle: css('width:24px;height:24px;border-radius:7px;flex:none;display:grid;place-items:center;font-size:.8rem;font-weight:800;' + (inNow ? 'background:#1e2c4f;color:#fff' : 'border:1.5px solid #c3cad6;color:transparent')),
        rowStyle: css('display:flex;align-items:center;gap:12px;padding:9px 10px;border-radius:12px;cursor:pointer;border:1px solid ' + (inNow ? '#d0a854;background:#fdf8ec' : '#eef1f6;background:#fff')),
        avatarStyle: css('width:36px;height:48px;border-radius:8px;flex:none;object-fit:cover;background:#e8ecf3;display:grid;place-items:center;font-size:.72rem;font-weight:700;color:#6b7689;overflow:hidden'),
        onClick: () => { const now = isIn(p); const next = {...picked}; if (now === !!p.here) next[p.person_id] = !now; else delete next[p.person_id]; this.dmEd({picked: next}); }};
    });
    const editRow = (dm && dm.rows || []).find(r => r.id === ed.editing);
    const edTitle = isHeading ? (ed.editing === null ? 'New heading' : 'Heading') : (ed.editing === null ? 'New division' : (f.name || 'Edit division'));

    // ── The preset picker ──
    const pr = (dm && dm.pr) || {}, presets = (dm && dm.presets) || [];
    const rulesets = []; presets.forEach(b => { if (rulesets.indexOf(b.ruleset) < 0) rulesets.push(b.ruleset); });
    const bands = presets.filter(b => b.ruleset === pr.ruleset);
    const band = bands.find(b => b.band === pr.band) || null;
    const classes = band ? ((band.genders || {})[pr.gender] || []) : [];
    const prPick = pr.pick || {}, pickedCount = classes.filter(c => prPick[c.label]).length;
    const allPicked = classes.length > 0 && classes.every(c => prPick[c.label]);

    const openPresets = () => {
      const rs = rulesets[0] || 'gi', b0 = presets.find(b => b.ruleset === rs);
      const running = rows.filter(r => !r.is_heading).map(r => r.format || 'knockout');
      this.dmSet({addOpen: false, pr: {open: true, ruleset: rs, band: b0 ? b0.band : '', gender: b0 && (b0.genders || {}).male ? 'male' : 'female',
        pick: {}, belts: [], format: running.length && running.every(x => x === 'round_robin') ? 'round_robin' : 'knockout', saving: false}});
    };

    return {
      on: true, off: false, loading, hasErr: !!(dm && dm.err), err: (dm && dm.err) || '', ready: !!(dm && dm.loaded),
      total: String(divs.length), entries: String(divs.reduce((n, x) => n + (x.r.entrants || 0), 0)),
      readyCount: String(divs.filter(x => (x.r.entrants || 0) >= 2).length),
      views: [['cards', 'Cards', '▦'], ['table', 'Table', '☰'], ['order', 'Order', '⇅']].map(([k, l, ic]) => ({key: k, label: l, icon: ic, pressed: view === k ? 'true' : 'false',
        style: css('display:inline-flex;align-items:center;gap:6px;height:32px;padding:0 12px;border-radius:8px;border:0;font-family:inherit;font-size:.74rem;cursor:pointer;' + (view === k ? 'font-weight:700;color:#1e2c4f;background:#fff;box-shadow:0 2px 6px rgba(30,44,79,.12)' : 'font-weight:500;color:#6b7689;background:transparent')),
        onClick: () => this.dmSet({view: k})})),
      cards: view === 'cards', table: view === 'table', order: view === 'order', sort: sortVals, flt: filterVals, tableRows, hasTableRows: tableRows.length > 0, th: tableHead,
      sections, hasSections: sections.length > 0, empty: !!(dm && dm.loaded) && !rows.length,
      hasUnplaced: !!unplaced, unplacedCount: unplaced ? String(unplaced.count) : '0', onUnplaced: () => this.dmUpOpen(),
      up: this.dmUnplacedVals(dm, css),
      orderRows, hint: 'Drag a row, or use the arrows. A heading groups the divisions under it.',

      addOpen: !!(dm && dm.addOpen),
      onAdd: e => { if (e && e.stopPropagation) e.stopPropagation(); this.dmSet({addOpen: !(dm && dm.addOpen)}); },
      onAddClose: () => this.dmSet({addOpen: false}), stop: e => e.stopPropagation(),
      onAddOne: () => this.dmOpenEditor({__new: true}), onAddHeading: () => this.dmOpenEditor({__new: true, is_heading: true}),
      hasPresets: presets.length > 0, onAddPresets: openPresets,
      canImport: !!(doors.importPreview && doors.import), onAddImport: () => this.dmImOpen(), im: this.dmImVals(dm, css),
      canPdf: !!doors.drawPdf && divs.length > 0, onPdf: () => this.dmDrawPdf(), pdfLabel: (dm && dm.pdfBusy) ? 'Preparing…' : '⤓ Draw PDF',
      canOneDiv: !!doors.oneDivision, onOneDiv: () => this.dmOneDiv(doors),
      oneDivLabel: (dm && dm.oneDiv) ? 'One division per person: On' : 'One division per person: Off',
      oneDivPressed: (dm && dm.oneDiv) ? 'true' : 'false',
      oneDivStyle: css('height:40px;padding:0 14px;border-radius:11px;font-family:inherit;font-size:.8rem;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:8px;'
        + ((dm && dm.oneDiv) ? 'border:1px solid #1e2c4f;color:#fff;background:#1e2c4f' : 'border:1px solid #d5dae3;color:#1e2c4f;background:#fff')),
      oneDivDot: css('width:10px;height:10px;border-radius:50%;' + ((dm && dm.oneDiv) ? 'background:#edcd85' : 'background:#c9d0db')),

      ed: {
        open: !!ed.open, isHeading, isDivision: !isHeading, title: edTitle,
        sub: isHeading ? 'A title over the divisions under it' : (ed.editing === null ? 'One group, set up by hand' : 'Edit division'),
        isEdit: ed.editing !== null && ed.editing !== undefined, canPeople: ed.editing !== null && ed.editing !== undefined && !isHeading,
        settings: (ed.tab || 'settings') === 'settings', people: ed.tab === 'people',
        // On the navy band: the open tab is gold, the other a quiet glass chip.
        tabSettingsStyle: css(bandTab((ed.tab || 'settings') === 'settings')), tabPeopleStyle: css(bandTab(ed.tab === 'people')),
        onTabSettings: () => this.dmEd({tab: 'settings'}), onTabPeople: () => this.dmOpenPeople(),
        // Before the list has loaded, the division's own count.
        inCount: String(roster.length ? roster.filter(isIn).length : ((editRow && editRow.entrants) || 0)),
        name: f.name || '', onName: e => this.dmFormChange({name: e.target.value}, true), nameErr: (ed.errors || {}).name || '', hasNameErr: !!(ed.errors || {}).name,
        autoName: !!ed.autoName && !isHeading, nameLabel: isHeading ? 'Heading' : 'Name', namePh: isHeading ? 'e.g. GI, NO-GI, JUNIORS' : 'e.g. Male −68 kg',
        genders, minAge: f.min_age, maxAge: f.max_age, onMinAge: field('min_age'), onMaxAge: field('max_age'),
        ageErr: (ed.errors || {}).max_age || (ed.errors || {}).min_age || '', hasAgeErr: !!((ed.errors || {}).max_age || (ed.errors || {}).min_age),
        asksWeight: !!(dm && dm.asks && dm.asks.weight), minW: f.min_weight, maxW: f.max_weight, onMinW: field('min_weight'), onMaxW: field('max_weight'),
        wErr: (ed.errors || {}).max_weight || (ed.errors || {}).min_weight || '', hasWErr: !!((ed.errors || {}).max_weight || (ed.errors || {}).min_weight),
        asksRank: !!(dm && dm.asks && dm.asks.rank) && beltChips.length > 0, beltChips, anyBeltStyle: pick(!(f.belts || []).length), onAnyBelt: () => this.dmFormChange({belts: []}),
        hasStreams: streamChips.length > 0, streamChips,
        formats, capacity: f.capacity, onCapacity: e => this.dmEd({form: {...f, capacity: e.target.value}}), capErr: (ed.errors || {}).capacity || '', hasCapErr: !!(ed.errors || {}).capacity,
        hasDays: phaseRows.length > 0, phaseRows,
        fitsLine, fitsSub, hasFitsSub: !!fitsSub && !pv.loading, showFits: !isHeading && (pv.loading || pv.loaded),
        faces: (pv.people || []).slice(0, 8).map((p, k) => ({key: k, photo: p.photo || '', hasPhoto: !!p.photo, noPhoto: !p.photo, initials: String(p.name || '?').split(/\s+/).map(x => x[0]).slice(0, 2).join('').toUpperCase()})),
        saveLabel: ed.saving ? 'Saving…' : (isHeading ? 'Save heading' : 'Save division'), onSave: () => this.dmSave(), saving: !!ed.saving,
        onDuplicate: () => this.dmDuplicate(), canDuplicate: ed.editing !== null && ed.editing !== undefined && !isHeading,
        onDelete: () => this.dmDelete(), delAsk: !!ed.delAsk, delText: this.dmDeleteAsk(editRow), onDelCancel: () => this.dmEd({delAsk: false}),
        delLabel: isHeading ? 'Delete heading' : 'Delete division',
        onClose: () => { if (!ed.saving) this.dmEd({open: false, delAsk: false}); },
        hasDraw: !!doors.draw && ed.editing !== null && ed.editing !== undefined && !isHeading, onDraw: () => editRow && openDraw(editRow),
        ptabs: [['selected', 'Selected'], ['fit', 'Fits this'], ['all', 'Everyone']].map(([k, l]) => ({key: k, label: l, style: pick(ptab === k), onClick: () => this.dmEd({peopleTab: k})})),
        rosterLoading: !!ed.rosterLoading, peopleRows, noPeople: !ed.rosterLoading && !peopleRows.length,
        noPeopleText: ptab === 'selected' ? 'Nobody is in this division yet' : 'Nobody to show',
        changed: changed > 0, peopleSaveLabel: ed.saving ? 'Saving…' : 'Save people' + (changed ? ' (' + changed + ')' : ''), onSavePeople: () => this.dmSavePeople(),
      },

      pr: {
        open: !!pr.open, saving: !!pr.saving,
        hasRulesets: rulesets.length > 1,
        rulesets: rulesets.map(r => ({key: r, label: r === 'nogi' ? 'No-Gi' : 'Gi', style: pick(pr.ruleset === r),
          onClick: () => { const b0 = presets.find(b => b.ruleset === r); this.dmPr({ruleset: r, band: b0 ? b0.band : '', pick: {}}); }})),
        bands: bands.map(b => ({key: b.band, label: b.band + (b.min_age != null ? ' · ' + (b.max_age == null ? b.min_age + '+' : b.min_age + '–' + b.max_age) : ''), style: pick(pr.band === b.band),
          onClick: () => this.dmPr({band: b.band, pick: {}, gender: (b.genders || {})[pr.gender] ? pr.gender : ((b.genders || {}).male ? 'male' : 'female')})})),
        genders: ['male', 'female'].filter(g => band && (band.genders || {})[g] && band.genders[g].length).map(g => ({key: g, label: g === 'male' ? 'Male' : 'Female',
          style: pick(pr.gender === g, g === 'male' ? '#2563eb' : '#db2777'), onClick: () => this.dmPr({gender: g, pick: {}})})),
        classes: classes.map(c => ({key: c.label, label: c.label + ' kg', style: pick(!!prPick[c.label], '#1e2c4f'),
          onClick: () => { const next = {...prPick}; if (next[c.label]) delete next[c.label]; else next[c.label] = true; this.dmPr({pick: next}); }})),
        allLabel: allPicked ? 'Clear' : 'Select all',
        onAll: () => { if (allPicked) { this.dmPr({pick: {}}); return; } const next = {}; classes.forEach(c => { next[c.label] = true; }); this.dmPr({pick: next}); },
        asksRank: !!(dm && dm.asks && dm.asks.rank) && (dm.belts || []).length > 0,
        belts: (dm && dm.belts || []).map(b => { const on = (pr.belts || []).indexOf(b.value) >= 0; return {key: b.value, label: b.label,
          style: css('display:inline-flex;align-items:center;gap:7px;height:32px;padding:0 11px;border-radius:999px;font-family:inherit;font-size:.72rem;font-weight:600;cursor:pointer;border:' + (on ? '2px solid #1e2c4f' : '1px solid #d5dae3') + ';background:' + (on ? '#f3f5f9' : '#fff') + ';color:#1e2c4f'),
          swatch: css('width:16px;height:8px;border-radius:2px;background:' + b.bg + ';box-shadow:inset 0 0 0 1px rgba(0,0,0,.18)'),
          onClick: () => { const list = (pr.belts || []).slice(), at = list.indexOf(b.value); if (at >= 0) list.splice(at, 1); else list.push(b.value); this.dmPr({belts: list}); }}; }),
        allBeltsStyle: pick(!(pr.belts || []).length), onAllBelts: () => this.dmPr({belts: []}),
        formats: [['knockout', 'Bracket'], ['round_robin', 'Round robin']].map(([k, l]) => ({key: k, label: l, style: pick((pr.format || 'knockout') === k), onClick: () => this.dmPr({format: k})})),
        addLabel: pr.saving ? 'Adding…' : (pickedCount ? 'Add ' + pickedCount + ' selected' : 'Add selected'), canAdd: pickedCount > 0 && !pr.saving,
        addStyle: css('height:44px;padding:0 22px;border:0;border-radius:12px;font-family:inherit;font-size:.84rem;font-weight:700;cursor:pointer;color:#1e2c4f;background:linear-gradient(100deg,#edcd85,#d0a854);' + (pickedCount && !pr.saving ? '' : 'opacity:.5;cursor:default')),
        onAdd: () => this.dmAddPresets(), onClose: () => { if (!pr.saving) this.dmPr({open: false}); },
      },

      canMerge: !!doors.merge, onMerge: () => this.dmMgOpen(),
      canAuto: !!doors.autoPlace, onAuto: () => this.dmApOpen('auto'), ap: this.dmApVals(dm, css), autoLabel: (dm && dm.rsBusy === 'auto') ? 'Placing…' : '✦ Auto-place',
      canDelAll: !!doors.deleteAll && divs.length > 0, onDelAll: () => this.dmSet({rsOpen: true}),
      rs: {open: !!(dm && dm.rsOpen), busy: !!(dm && dm.rsBusy), count: String(divs.length),
        people: String(divs.reduce((n, x) => n + (x.r.entrants || 0), 0)),
        onClose: () => { if (!(dm && dm.rsBusy)) this.dmSet({rsOpen: false}); },
        onRebuild: () => this.dmDeleteAll(true), onDeleteOnly: () => this.dmDeleteAll(false), canRebuild: !!doors.autoPlace,
        rebuildLabel: (dm && dm.rsBusy === 'rebuild') ? 'Rebuilding…' : 'Clear and auto-place…',
        deleteLabel: (dm && dm.rsBusy === 'delete') ? 'Clearing…' : 'Clear only'},
      emptyCount: String(divs.filter(x => !(x.r.entrants || 0)).length), hasEmpty: divs.some(x => !(x.r.entrants || 0)),
      emptyLabel: (dm && dm.showEmpty ? 'Hide ' : 'Show ') + divs.filter(x => !(x.r.entrants || 0)).length + ' empty',
      onEmpty: () => this.dmSet({showEmpty: !(dm && dm.showEmpty)}),
      ...(() => {
        const list = (sg && sg.suggestions) || [], safe = list.filter(j => j.grade === 'green').length, amber = list.length - safe;
        const busy = (dm || {}).sgBusy === 'all', armed = (dm || {}).sgArm === 'all';
        return {hasStrip: !!doors.merge && list.length > 0,
          stripLine: (safe ? (safe === 1 ? 'One safe merge is ready' : safe + ' safe merges are ready') : 'No merge is safe without consent')
            + (amber ? ' · ' + amber + ' need' + (amber === 1 ? 's' : '') + ' consent' : ''),
          stripSub: 'Small divisions, matched to the closest safe neighbour — same gender, close in weight and age.',
          hasSafeAll: safe > 0, safeAllLabel: busy ? 'Merging…' : armed ? 'Confirm — merge ' + safe : (safe === 1 ? 'Merge it' : 'Merge all ' + safe + ' safe'),
          onSafeAll: () => this.dmMergeAllSafe(),
          safeAllStyle: css('height:38px;padding:0 16px;border-radius:11px;font-family:inherit;font-size:.78rem;font-weight:700;cursor:pointer;border:0;'
            + (armed ? 'background:#1e2c4f;color:#fff' : 'color:#1e2c4f;background:linear-gradient(100deg,#edcd85,#d0a854)') + (busy ? ';opacity:.6;cursor:wait' : ''))};
      })(),
      mergeBadge: (() => { const n = divs.filter(x => (x.r.entrants || 0) >= 1 && (x.r.entrants || 0) < 3).length; return n ? String(n) : ''; })(),
      mg: this.dmMgVals(dm, css),

      toast: (dm && dm.toast) || '', hasToast: !!(dm && dm.toast),
      toastStyle: css('position:fixed;left:50%;bottom:24px;transform:translateX(-50%);z-index:90;max-width:calc(100vw - 32px);padding:10px 16px;border-radius:12px;font-size:.8rem;font-weight:600;box-shadow:0 12px 30px rgba(0,0,0,.22);color:#fff;background:' + (dm && dm.toastOk === false ? '#b4483c' : '#1e2c4f')),
      onRetry: () => { this.dmSet({err: ''}); this.dmLoad(doors); },
    };
  }

