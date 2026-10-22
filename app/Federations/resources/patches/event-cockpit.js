  // The Overview's cockpit (UX brief 2026-10-22): where the event is in its
  // life, the one next step, and what needs attention — every figure derived
  // on the server (FederationEventFigures::cockpit), every row a way to fix it.
  ckVals(sel) {
    const c = sel && sel.cockpit;
    if (!c || !c.steps) return {has: false};
    const css = x => this.cssToObj(x);
    const narrow = !!this.state.isMobile;
    const total = c.steps.length;
    const closed = c.current >= total;
    const steps = c.steps.map(st => ({
      // On a phone only the current step keeps its word; the bar says the rest.
      label: narrow && st.state !== 'current' ? '' : st.label,
      mark: st.state === 'done' && !narrow ? '✓ ' : '',
      current: st.state === 'current' ? 'step' : 'false',
      title: st.label + (st.state === 'done' ? ' — done' : st.state === 'current' ? ' — now' : ''),
      barStyle: css('display:block;height:6px;border-radius:3px;background:' + (st.state === 'done' ? '#2f7a4f' : st.state === 'current' ? '#d8b25f' : '#e3e7ee')),
      labelStyle: css('font-size:.72rem;line-height:1.3;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;' + (st.state === 'done' ? 'color:#2f7a4f;font-weight:600' : st.state === 'current' ? 'color:#1e2c4f;font-weight:700' : 'color:#6b7689;font-weight:500'))
    }));
    const sev = {critical: ['Critical', '#a12020', '#fdecec'], high: ['High', '#8a4b00', '#fff4e5'], medium: ['Medium', '#3b4558', '#eef1f6']};
    const att = (c.attention || []).map(a => {
      const [word, ink, bg] = sev[a.sev] || sev.medium;
      return {
        title: a.title, text: a.text, action: a.action, sev: word,
        sevStyle: css('flex:none;min-width:62px;text-align:center;font-size:.66rem;font-weight:700;letter-spacing:.04em;padding:4px 8px;border-radius:7px;color:' + ink + ';background:' + bg),
        onGo: () => (a.open === 'double-entries' && this.deDoors(sel)) ? this.deOpen(sel) : this.setState({tab: a.tab}),
        btnStyle: css('flex:none;min-height:40px;padding:0 14px;border-radius:10px;font-family:inherit;font-size:.78rem;font-weight:600;cursor:pointer;white-space:nowrap;'
          + (a.sev === 'critical' ? 'border:0;color:#fff;background:#1e2c4f' : 'border:1px solid #d5dae3;color:#1e2c4f;background:#fff'))
      };
    });
    const stage = closed ? 'All done — results are in' : 'Now: ' + (c.steps[c.current] || {}).label + ' · step ' + (c.current + 1) + ' of ' + total;
    return {
      has: true, steps, stage,
      hasNext: !!(c.next && c.next.tab !== 'edit'), nextLabel: c.next ? 'Next step: ' + c.next.label : '',
      onNext: () => { if (c.next && c.next.tab !== 'edit') this.setState({tab: c.next.tab}); },
      att, hasAtt: att.length > 0, noAtt: att.length === 0, attCount: String(att.length),
      glance: (c.glance || []).map(g => ({label: g.label, value: g.value})),
      de: this.deVals(sel),
      // The Draw tab's rules panel: the rules in force, and what breaks them now.
      rules: (c.rules || []).map(r => ({label: r.label, value: r.value, note: r.note,
        valueStyle: css('font-size:.95rem;font-weight:700;color:' + (r.value === 'Off' ? '#6b7689' : '#1e2c4f'))})),
      checks: (c.checks || []).map(k => {
        const bad = k.n > 0; const [, ink, bg] = sev[k.sev] || sev.medium;
        return {label: k.label, n: String(k.n), hasFix: bad, fix: k.open === 'double-entries' ? 'Resolve' : k.tab === 'regs' ? 'Check' : 'Review',
          onFix: () => (k.open === 'double-entries' && this.deDoors(sel)) ? this.deOpen(sel) : this.setState({tab: k.tab}),
          style: css('display:flex;align-items:center;gap:10px;min-height:52px;padding:8px 12px;border-radius:12px;' + (bad ? 'background:' + bg + ';color:' + ink : 'background:#ecf7f0;color:#2f7a4f')),
          nStyle: css('font-size:1.25rem;font-weight:800;font-variant-numeric:tabular-nums;min-width:28px'),
          fixStyle: css('margin-inline-start:auto;min-height:36px;padding:0 12px;border-radius:9px;border:1px solid currentColor;background:#fff;color:inherit;font-family:inherit;font-size:.74rem;font-weight:700;cursor:pointer')};
      }),
      clear: (c.checks || []).every(k => !k.n),
      blocking: (c.checks || []).filter(k => k.n > 0).length
    };
  }

  // ── Resolve double entries (brief §2.1) ────────────────────────────────
  // Everybody standing in two divisions, one row each, a choice per division
  // (the one they fit is suggested). Applying takes them out of the others
  // through the division editor's own door (divisions/{id}/members, remove),
  // so every rule that door keeps — the bout on the mat, spare entries — holds.
  deDoors(sel) {
    const T = window.TakeOneFed;
    const d = sel && T && T.divisionDoors ? T.divisionDoors(sel.id) : null;
    return d && d.conflicts && d.base ? d : null;
  }
  deOpen(sel) {
    const d = this.deDoors(sel);
    if (!d) return;
    this.setState({deOpen: true, deBusy: true, deList: [], deChoice: {}, deAgree: false, deMsg: ''});
    window.TakeOneFed.roFetch('GET', d.conflicts).then(j => {
      const list = j.__ok ? (j.conflicts || []) : [];
      const choice = {};
      list.forEach(p => { if (p.suggested) choice[p.user_id] = p.suggested; });
      this.setState({deBusy: false, deList: list, deChoice: choice, deMsg: j.__ok ? '' : (j.message || 'Could not load the double entries.')});
    });
  }
  deApply(sel) {
    const d = this.deDoors(sel); const s = this.state;
    if (!d || s.deBusy) return;
    const jobs = [];
    (s.deList || []).forEach(p => {
      const keep = (s.deChoice || {})[p.user_id];
      if (!keep) return;
      p.divisions.filter(x => x.id !== keep).forEach(x => jobs.push([x.id, x.entry_id]));
    });
    if (!jobs.length) return;
    this.setState({deBusy: true, deMsg: ''});
    const run = i => i >= jobs.length ? Promise.resolve({__ok: true})
      : window.TakeOneFed.roFetch('PUT', d.base + '/' + jobs[i][0] + '/members', {remove: [jobs[i][1]]}).then(j => j.__ok ? run(i + 1) : j);
    run(0).then(j => {
      if (!j.__ok) { this.setState({deBusy: false, deMsg: j.message || 'Something could not be changed. Nothing after it was touched.'}); return; }
      if (window.TakeOneFed.refresh) window.TakeOneFed.refresh();
      this.setState({deOpen: false, deBusy: false});
      if (window.showToast) window.showToast('success', 'Double entries resolved.');
    });
  }
  deVals(sel) {
    const s = this.state;
    if (!s.deOpen) return {open: false};
    const css = x => this.cssToObj(x);
    const choice = s.deChoice || {};
    const people = (s.deList || []).map(p => ({
      name: p.name,
      opts: p.divisions.map(x => {
        const on = choice[p.user_id] === x.id;
        return {
          label: x.name, on: on ? 'true' : 'false',
          note: (x.fit === 'in' ? 'Fits' : x.fit === 'out' ? 'Outside the range' : 'Not sure') + (x.fought ? ' \u00b7 already fought here' : '') + (p.suggested === x.id ? ' \u00b7 suggested' : ''),
          onPick: () => this.setState({deChoice: Object.assign({}, choice, {[p.user_id]: x.id})}),
          style: css('display:flex;align-items:center;gap:10px;width:100%;min-height:48px;padding:8px 12px;border-radius:12px;font-family:inherit;text-align:start;cursor:pointer;'
            + (on ? 'border:2px solid #d8b25f;background:#fdf6e6' : 'border:1px solid #d5dae3;background:#fff')),
          dotStyle: css('flex:none;box-sizing:border-box;width:18px;height:18px;border-radius:50%;' + (on ? 'border:5px solid #1e2c4f' : 'border:2px solid #b7bfcc'))
        };
      })
    }));
    const fought = (s.deList || []).some(p => p.divisions.some(x => x.fought && choice[p.user_id] && choice[p.user_id] !== x.id));
    const chosen = (s.deList || []).filter(p => choice[p.user_id]).length;
    const ready = chosen > 0 && (!fought || s.deAgree);
    return {
      open: true, busy: !!s.deBusy, people, none: !s.deBusy && people.length === 0, msg: s.deMsg || '', hasMsg: !!s.deMsg,
      count: String(people.length), chosen: String(chosen),
      fought, agree: s.deAgree ? 'true' : 'false', onAgree: () => this.setState({deAgree: !s.deAgree}),
      agreeStyle: css('flex:none;width:20px;height:20px;border-radius:6px;display:grid;place-items:center;font-size:.8rem;color:#fff;' + (s.deAgree ? 'background:#a12020;border:0' : 'background:#fff;border:2px solid #b7bfcc')),
      agreeMark: s.deAgree ? '\u2713' : '',
      onClose: () => this.setState({deOpen: false}),
      onApply: () => this.deApply(sel),
      applyLabel: s.deBusy ? 'Working\u2026' : 'Apply ' + chosen + ' of ' + people.length,
      applyStyle: css('min-height:44px;padding:0 20px;border:0;border-radius:11px;font-family:inherit;font-size:.84rem;font-weight:700;cursor:pointer;'
        + (ready && !s.deBusy ? 'color:#1e2c4f;background:linear-gradient(100deg,#edcd85,#d0a854)' : 'color:#97a1b4;background:#eef1f6;cursor:not-allowed')),
      applyDisabled: !(ready && !s.deBusy)
    };
  }
