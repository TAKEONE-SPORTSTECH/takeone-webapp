  // An athlete's edit window on the event's Participants tab (FederationApp:
  // 'athed-*' rewrites). The form itself is a platform page in a same-origin
  // frame (federations::athlete.edit), built from the platform's own controls;
  // this side only opens it, closes it, and repaints the list after a save.
  // The frame reads and saves through federations.api.regs.athlete, where
  // FederationAthleteEditor decides what may change.
  athEdOpen(r) {
    const T = window.TakeOneFed || {};
    const base = T.athleteFrame ? T.athleteFrame() : null;
    if (!base || !r || !r.entryId || !r.takeoneId) return;
    window.__fedAthComp = this;
    if (!window.__fedAthMsg) {
      window.__fedAthMsg = (ev) => {
        const c = window.__fedAthComp;
        if (ev.origin !== window.location.origin || !ev.data || !c) return;
        if (ev.data.type === 'fed-athlete:close') c.setState({athEd: null});
        if (ev.data.type === 'fed-athlete:saved') c.athEdSaved(ev.data);
      };
      window.addEventListener('message', window.__fedAthMsg);
    }
    const src = base + '?entry=' + encodeURIComponent(r.entryId) + '&athlete=' + encodeURIComponent(r.takeoneId);
    this.setState({athEd: {src, key: r.entryId + '|' + r.takeoneId}});
  }
  athEdSaved(d) {
    const T = window.TakeOneFed || {};
    if (T.athleteSaved) T.athleteSaved(d.regs || null);
    this.setState({athEd: null, athFlash: d.message || 'Athlete updated.'});
    clearTimeout(this._athFlashT);
    this._athFlashT = setTimeout(() => this.setState({athFlash: null}), 4000);
  }
  // Weigh-in, per row: a small window asking for the scale's reading. The
  // save goes through TakeOneFed.weighIn -> the platform's weigh-in desk
  // endpoint, once per entry the athlete holds on the event.
  weighOpen(r) {
    if (!r || !r.weighUrls || !r.weighUrls.length) return;
    const kg = r.weighedKg || r.weight || '';
    this.setState({weighEd: {name: r.name, urls: r.weighUrls, kg: kg ? String(kg) : '', busy: false, err: ''}});
    setTimeout(() => { const i = document.getElementById('fed-weigh-kg'); if (i) { i.focus(); i.select(); } }, 60);
  }
  weighSave() {
    const w = (this.state || {}).weighEd;
    if (!w || w.busy) return;
    const kg = parseFloat(String(w.kg).replace(',', '.'));
    if (!(kg >= 10 && kg <= 250)) { this.setState({weighEd: {...w, err: 'Enter a weight between 10 and 250 kg.'}}); return; }
    const T = window.TakeOneFed || {};
    if (!T.weighIn) return;
    this.setState({weighEd: {...w, busy: true, err: ''}});
    T.weighIn(w.urls, Math.round(kg * 10) / 10).then(res => {
      const cur = (this.state || {}).weighEd;
      if (!res.ok) { if (cur) this.setState({weighEd: {...cur, busy: false, err: res.message || 'The weight could not be saved.'}}); return; }
      this.setState({weighEd: null, athFlash: res.message || 'Weight recorded.'});
      clearTimeout(this._athFlashT);
      this._athFlashT = setTimeout(() => this.setState({athFlash: null}), res.unplaced ? 7000 : 4000);
    });
  }
  evAthleteEditVals() {
    const e = (this.state || {}).athEd;
    const flash = (this.state || {}).athFlash || '';
    const w = (this.state || {}).weighEd;
    return {
      open: !!(e && e.src), src: e && e.src ? e.src : '',
      onClose: () => this.setState({athEd: null}), stop: ev => ev.stopPropagation(),
      flash, hasFlash: !!flash,
      wg: {
        open: !!w, name: w ? w.name : '', kg: w ? w.kg : '', err: w ? w.err : '', hasErr: !!(w && w.err),
        saveLabel: w && w.busy ? 'Saving\u2026' : 'Save weight',
        onKg: ev => { const cur = (this.state || {}).weighEd; if (cur) this.setState({weighEd: {...cur, kg: ev.target.value, err: ''}}); },
        onKey: ev => { if (ev.key === 'Enter') this.weighSave(); if (ev.key === 'Escape') this.setState({weighEd: null}); },
        onSave: () => this.weighSave(),
        onClose: () => { const cur = (this.state || {}).weighEd; if (!cur || !cur.busy) this.setState({weighEd: null}); }
      }
    };
  }
