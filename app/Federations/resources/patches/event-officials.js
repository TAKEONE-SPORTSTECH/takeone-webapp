  // The event's Officials tab (FederationApp: 'offs-*' rewrites). Draws the
  // event's OWN appointments — the same people the readiness checklist counts
  // and the event's officiating sheet lists (FederationEventOfficials), grouped
  // by job, each with their club. Managing them happens on that sheet.
  evOfficialsVals(sel) {
    const T = window.TakeOneFed || {};
    const O = sel && sel.id && T.eventOfficials ? T.eventOfficials(sel.id) : null;
    const css = x => this.cssToObj(x);
    if (!O) {
      return {total: '0', chips: [], groups: [], empty: true, hasUrl: false, manage: () => {},
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
    return {total: String(O.total || 0), chips: groups.map(g => ({label: g.label, count: g.count})), groups,
      empty: !groups.length, emptyText: 'No officials are appointed to this event yet.',
      hasUrl: !!O.url, manage: () => { if (O.url) window.location.assign(O.url); }};
  }
