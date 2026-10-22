  // The event's Clubs tab (FederationApp: 'clubs-*' rewrites). Every club with
  // an athlete entered — the same clubs the readiness checklist counts — with
  // its athletes split Male / Female (FederationEventFigures::entryClubs).
  evClubsVals(sel) {
    const css = x => this.cssToObj(x);
    const T = window.TakeOneFed || {}, st = this.state || {};
    const canRemove = !!(T.canWithdrawClub && T.canWithdrawClub()), armed = st.clubRmArmed || null;
    const list = (sel && sel.entryClubs) || [];
    const all = Math.max(1, list.reduce((n, c) => n + (c.athletes || 0), 0));
    const max = Math.max(1, ...list.map(c => c.athletes || 0));
    const inits = n => String(n || '').trim().split(/\s+/).filter(Boolean).slice(0, 2).map(w => w[0]).join('').toUpperCase();
    // Ticked clubs, by the name their certificate is held under (EventCertificates::holderKey).
    const clubKey = c => 'n:' + String(c.name || '').trim().replace(/\s+/g, ' ').toLowerCase();
    const cPicked = st.clubSelEv === (sel && sel.id) ? (st.clubSel || {}) : {};
    const cTick = on => css('width:18px;height:18px;border-radius:5px;cursor:pointer;display:grid;place-items:center;font-size:.66rem;font-weight:700;flex:none;padding:0;font-family:inherit;'
      + (on ? 'background:linear-gradient(100deg,#edcd85,#d0a854);color:#1e2c4f;border:0;box-shadow:0 2px 6px rgba(202,161,79,.3)' : 'background:#fff;color:transparent;border:1.5px solid #c3cad6'));
    const setPick = next => this.setState({clubSel: next, clubSelEv: sel && sel.id});
    const clubs = list.map((c, i) => ({
      rank: String(i + 1), name: c.name, hasContact: !!c.contact, contact: c.contact || '', contactRole: c.contactRole || '', athletes: String(c.athletes), male: String(c.male), female: String(c.female),
      hasLogo: !!c.logo, noLogo: !c.logo, initials: inits(c.name),
      checked: !!cPicked[clubKey(c)], checkMark: cPicked[clubKey(c)] ? '\u2713' : '', checkStyle: cTick(!!cPicked[clubKey(c)]),
      onToggleSel: e => { if (e && e.stopPropagation) e.stopPropagation(); setPick({...cPicked, [clubKey(c)]: !cPicked[clubKey(c)]}); },
      share: Math.round(100 * (c.athletes || 0) / all) + '%',
      logoStyle: c.logo ? Object.assign(css('display:block;width:36px;height:36px;flex:none;background-size:contain;background-repeat:no-repeat;background-position:center'),
        {backgroundImage: 'url("' + String(c.logo).replace(/["\\]/g, '') + '")'}) : {},
      maleBar: css('height:6px;background:#2563eb;width:' + (100 * (c.male || 0) / max).toFixed(1) + '%'),
      femaleBar: css('height:6px;background:#db2777;width:' + (100 * (c.female || 0) / max).toFixed(1) + '%'),
      // The row opens the club's editor window (managers); otherwise the participants.
      canOpen: !!(T.clubFrame && T.clubFrame() && c.key),
      openRegs: () => (T.clubFrame && T.clubFrame() && c.key) ? this.clubEdOpen(c.key) : this.setState({tab: 'regs'}),
      // "Remove from event", two taps, like the athlete and official windows.
      canRemove: canRemove && !!c.key, armed: armed === c.key, busy: st.clubRmBusy === c.key,
      removeLabel: st.clubRmBusy === c.key ? 'Removing…' : (armed === c.key ? 'Tap again to remove' : 'Remove'),
      removeStyle: css('height:32px;padding:0 12px;border-radius:9px;font-family:inherit;font-size:.72rem;font-weight:700;cursor:pointer;white-space:nowrap;'
        + (armed === c.key ? 'border:1px solid #c0392b;background:#c0392b;color:#fff' : 'border:1px solid #e9b8b1;background:#fff;color:#c0392b')),
      onRemove: e => {
        if (e && e.stopPropagation) e.stopPropagation();
        if (st.clubRmBusy) return;
        if (armed !== c.key) { this.setState({clubRmArmed: c.key}); return; }
        this.setState({clubRmBusy: c.key, clubRmArmed: null});
        T.withdrawClub(sel.id, c.key).then(r => {
          this.setState({clubRmBusy: null, clubFlash: r.message || (r.ok ? 'Removed.' : 'That club could not be removed.')});
          clearTimeout(this._clubFlashT);
          this._clubFlashT = setTimeout(() => this.setState({clubFlash: null}), 4000);
        });
      }
    }));
    const sum = k => list.reduce((n, c) => n + (c[k] || 0), 0);
    const ce = st.clubEd;
    const allPicked = list.length > 0 && list.every(c => cPicked[clubKey(c)]);
    return {clubs, empty: !clubs.length, hasRows: !!clubs.length, total: String(clubs.length), canRemove,
      allChecked: allPicked, allCheckMark: allPicked ? '\u2713' : '', allCheckStyle: cTick(allPicked),
      onToggleAll: () => { const next = {...cPicked}; list.forEach(c => { next[clubKey(c)] = !allPicked; }); setPick(next); },
      // The clubs' own printables — the club certificate of thanks (designVals, event-officials.js).
      design: this.designVals(sel, 'clubs'),
      edOpen: !!(ce && ce.src), edSrc: ce && ce.src ? ce.src : '', onEdClose: () => this.setState({clubEd: null}), edStop: ev => ev.stopPropagation(),
      flash: st.clubFlash || '', hasFlash: !!st.clubFlash,
      athletes: String(sum('athletes')), male: String(sum('male')), female: String(sum('female'))};
  }
  // The club editor window (federations::club.edit, a same-origin frame like
  // the athlete and official editors). After a save the dashboard re-reads.
  clubEdOpen(key) {
    const T = window.TakeOneFed || {};
    const base = T.clubFrame ? T.clubFrame() : null;
    if (!base || !key) return;
    window.__fedClubComp = this;
    if (!window.__fedClubMsg) {
      window.__fedClubMsg = (ev) => {
        const c = window.__fedClubComp;
        if (ev.origin !== window.location.origin || !ev.data || !c) return;
        if (ev.data.type === 'fed-club:close') c.setState({clubEd: null});
        if (ev.data.type === 'fed-club:saved') {
          if (window.TakeOneFed && window.TakeOneFed.refresh) window.TakeOneFed.refresh();
          c.setState({clubEd: null, clubFlash: ev.data.message || 'Club saved.'});
          clearTimeout(c._clubFlashT);
          c._clubFlashT = setTimeout(() => c.setState({clubFlash: null}), 4000);
        }
      };
      window.addEventListener('message', window.__fedClubMsg);
    }
    this.setState({clubEd: {src: base + '?club=' + encodeURIComponent(key)}});
  }
