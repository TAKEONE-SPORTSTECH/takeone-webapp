// The Schedule tab — the platform half (FederationApp::scheduleRewrites).
//
// The prototype drew one fixed demo programme on every event. This reads the
// event's own days and sessions (TakeOneFed.schedule — FederationSchedule on
// the server) and lets any staff member who can open the event add, rename,
// reorder and delete days, and add, edit and delete the sessions on each.
// Every change is sent to the server; its answer is the event's whole
// schedule, and the tab redraws from it. Other staff see it live (Pulse).
//
// One editor is open at a time (state.schEdit); a delete asks first, inline
// (state.schDel) — never a native dialog.

  schVals(sel) {
    const s = this.state, T = window.TakeOneFed || {};
    const can = !!(T.canEditSchedule && T.canEditSchedule()) && !!(sel && sel.id);
    const days = ((T.schedule && sel && sel.id) ? T.schedule(sel.id) : []) || [];
    const ed = s.schEdit || null, busy = !!s.schBusy, err = s.schErr || '';
    const set = patch => this.setState({schEdit: {...(this.state.schEdit || {}), ...patch}, schErr: ''});
    const field = k => e => set({[k]: e.target.value});
    const close = () => { if (!this.state.schBusy) this.setState({schEdit: null, schDel: null, schErr: ''}); };
    const run = (op, body) => {
      if (this.state.schBusy || !T.scheduleOp) return;
      this.setState({schBusy: true, schErr: ''});
      T.scheduleOp(op, body).then(r => this.setState(st => r.ok
        ? {schBusy: false, schEdit: null, schDel: null, schErr: '', fsTick: (st.fsTick || 0) + 1}
        : {schBusy: false, schErr: r.message || 'That change did not save. Try again.'}));
    };
    // "930", "9.30", "9h30", "9:30" all mean 09:30.
    const hhmm = v => {
      v = String(v || '').trim();
      if (!v) return '';
      const m = v.match(/^(\d{1,2})\s*[:.h]?\s*(\d{2})$/i);
      if (!m) return v;
      return String(+m[1]).padStart(2, '0') + ':' + m[2];
    };
    const start = sel && sel.startDate ? new Date(String(sel.startDate).slice(0, 10) + 'T00:00:00') : null;
    const dateOf = i => {
      if (!start || isNaN(start.getTime())) return '';
      const d = new Date(start.getTime()); d.setDate(d.getDate() + i);
      return d.toLocaleDateString('en-GB', {weekday: 'short', day: 'numeric', month: 'short'});
    };
    const keys = (save) => e => { if (e.key === 'Enter') { e.preventDefault(); save(); } else if (e.key === 'Escape') close(); };
    const slotBody = () => {
      const e = this.state.schEdit || {};
      return {time: hhmm(e.time), end: hhmm(e.end) || null, what: String(e.what || '').trim(), where: String(e.where || '').trim() || null};
    };
    const checkSlot = b => {
      if (!/^([01]\d|2[0-3]):[0-5]\d$/.test(b.time)) return 'Enter the start time as HH:MM, for example 09:30.';
      if (b.end && !/^([01]\d|2[0-3]):[0-5]\d$/.test(b.end)) return 'Enter the end time as HH:MM, for example 11:00.';
      if (b.end && b.end <= b.time) return 'The end time must be after the start time.';
      if (!b.what) return 'Say what happens in this session.';
      return '';
    };
    const saveSlot = () => {
      const e = this.state.schEdit; if (!e) return;
      const b = slotBody(), bad = checkSlot(b);
      if (bad) { this.setState({schErr: bad}); return; }
      if (e.kind === 'slot-new') run('slot.add', {...b, day: e.dayId}); else run('slot.update', {...b, slot: e.id});
    };
    const saveDay = () => {
      const e = this.state.schEdit; if (!e) return;
      run('day.update', {day: e.id, title: String(e.title || '').trim() || null});
    };
    const nSlots = days.reduce((n, d) => n + (d.slots || []).length, 0);
    const plural = (n, w) => n + ' ' + w + (n === 1 ? '' : 's');

    const out = days.map((d, i) => {
      const slots = d.slots || [];
      const editing = !!(ed && ed.kind === 'day' && ed.id === d.id);
      const adding = !!(ed && ed.kind === 'slot-new' && ed.dayId === d.id);
      const delAsk = s.schDel === d.id;
      const last = slots[slots.length - 1];
      const date = dateOf(i);
      return {
        key: d.id, num: 'Day ' + (i + 1), date, hasDate: !!date,
        title: d.title || '', hasTitle: !!d.title, noTitle: !d.title && can,
        count: slots.length ? plural(slots.length, 'session') : 'No sessions yet',
        can, viewing: !editing, editing, delAsk,
        delText: 'Delete Day ' + (i + 1) + (slots.length ? ' and its ' + plural(slots.length, 'session') : '') + '? Later days move up.',
        canUp: can && i > 0, canDown: can && i < days.length - 1,
        onUp: () => run('day.move', {day: d.id, dir: 'up'}),
        onDown: () => run('day.move', {day: d.id, dir: 'down'}),
        onEdit: () => this.setState({schEdit: {kind: 'day', id: d.id, title: d.title || ''}, schDel: null, schErr: ''}),
        onDel: () => this.setState({schDel: d.id, schEdit: null, schErr: ''}),
        onDelYes: () => run('day.delete', {day: d.id}), onDelNo: close,
        eTitle: editing ? (ed.title || '') : '', onETitle: field('title'), onTitleKey: keys(saveDay),
        onSaveDay: saveDay, onCancel: close,
        err: (editing || delAsk) ? err : '', hasErr: !!((editing || delAsk) && err),
        saveLabel: busy ? 'Saving…' : 'Save', delLabel: busy ? 'Deleting…' : 'Delete',
        slots: slots.map(sl => {
          const on = !!(ed && ed.kind === 'slot' && ed.id === sl.id);
          const ask = s.schDel === sl.id;
          return {
            key: sl.id, time: sl.time, range: sl.end ? sl.time + ' – ' + sl.end : sl.time,
            what: sl.what, where: sl.where || '', hasWhere: !!sl.where,
            can, viewing: !on, editing: on, delAsk: ask,
            onEdit: () => this.setState({schEdit: {kind: 'slot', id: sl.id, time: sl.time, end: sl.end || '', what: sl.what, where: sl.where || ''}, schDel: null, schErr: ''}),
            onDel: () => this.setState({schDel: sl.id, schEdit: null, schErr: ''}),
            onDelYes: () => run('slot.delete', {slot: sl.id}), onDelNo: close,
            eTime: on ? ed.time : '', eEnd: on ? ed.end : '', eWhat: on ? ed.what : '', eWhere: on ? ed.where : '',
            onETime: field('time'), onEEnd: field('end'), onEWhat: field('what'), onEWhere: field('where'),
            onKey: keys(saveSlot), onSave: saveSlot, onCancel: close,
            err: (on || ask) ? err : '', hasErr: !!((on || ask) && err),
            saveLabel: busy ? 'Saving…' : 'Save', delLabel: busy ? 'Deleting…' : 'Delete'
          };
        }),
        noSlots: !slots.length && !adding,
        adding, showAdd: can && !adding,
        onAdd: () => this.setState({schEdit: {kind: 'slot-new', dayId: d.id,
          time: last ? (last.end || '') : (sel.dayStart || ''), end: '', what: '', where: last ? (last.where || '') : ''}, schDel: null, schErr: ''}),
        nTime: adding ? ed.time : '', nEnd: adding ? ed.end : '', nWhat: adding ? ed.what : '', nWhere: adding ? ed.where : '',
        onNTime: field('time'), onNEnd: field('end'), onNWhat: field('what'), onNWhere: field('where'),
        onNKey: keys(saveSlot), onNSave: saveSlot,
        nErr: adding ? err : '', nHasErr: !!(adding && err), addLabel: busy ? 'Adding…' : 'Add session'
      };
    });

    return {
      can, days: out, empty: !days.length, has: days.length > 0,
      summary: days.length ? plural(days.length, 'day') + ' · ' + plural(nSlots, 'session') : '',
      addDayLabel: busy && !ed && !s.schDel ? 'Adding…' : '+ Add day',
      onAddDay: () => run('day.add', {event: sel.id}),
      topErr: (!ed && !s.schDel) ? err : '', hasTopErr: !!(!ed && !s.schDel && err)
    };
  }

