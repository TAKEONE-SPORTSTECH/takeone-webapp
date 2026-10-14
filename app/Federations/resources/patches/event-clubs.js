  // The event's Clubs tab (FederationApp: 'clubs-*' rewrites). Every club with
  // an athlete entered — the same clubs the readiness checklist counts — with
  // its athletes split Male / Female (FederationEventFigures::entryClubs).
  evClubsVals(sel) {
    const css = x => this.cssToObj(x);
    const list = (sel && sel.entryClubs) || [];
    const all = Math.max(1, list.reduce((n, c) => n + (c.athletes || 0), 0));
    const max = Math.max(1, ...list.map(c => c.athletes || 0));
    const inits = n => String(n || '').trim().split(/\s+/).filter(Boolean).slice(0, 2).map(w => w[0]).join('').toUpperCase();
    const clubs = list.map((c, i) => ({
      rank: String(i + 1), name: c.name, hasContact: !!c.contact, contact: c.contact || '', contactRole: c.contactRole || '', athletes: String(c.athletes), male: String(c.male), female: String(c.female),
      hasLogo: !!c.logo, noLogo: !c.logo, initials: inits(c.name),
      share: Math.round(100 * (c.athletes || 0) / all) + '%',
      logoStyle: c.logo ? Object.assign(css('display:block;width:36px;height:36px;flex:none;background-size:contain;background-repeat:no-repeat;background-position:center'),
        {backgroundImage: 'url("' + String(c.logo).replace(/["\\]/g, '') + '")'}) : {},
      maleBar: css('height:6px;background:#2563eb;width:' + (100 * (c.male || 0) / max).toFixed(1) + '%'),
      femaleBar: css('height:6px;background:#db2777;width:' + (100 * (c.female || 0) / max).toFixed(1) + '%'),
      openRegs: () => this.setState({tab: 'regs'})
    }));
    const sum = k => list.reduce((n, c) => n + (c[k] || 0), 0);
    return {clubs, empty: !clubs.length, hasRows: !!clubs.length, total: String(clubs.length),
      athletes: String(sum('athletes')), male: String(sum('male')), female: String(sum('female'))};
  }
