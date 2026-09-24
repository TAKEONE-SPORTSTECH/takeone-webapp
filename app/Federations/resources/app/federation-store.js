// Shared prototype data store for the federation dashboard and the club invitation page.
// Persists in localStorage so both pages see the same clubs, athletes and transfer requests.
(function () {
  var K = {clubs: 'takeone_clubs', ath: 'takeone_athletes', tr: 'takeone_transfers'};
  var mem = {};
  function get(k, d) { if (k in mem) return mem[k]; try { var v = JSON.parse(localStorage.getItem(k) || 'null'); return v == null ? d : v; } catch (e) { return d; } }
  function set(k, v, quiet) {
    try { localStorage.setItem(k, JSON.stringify(v)); delete mem[k]; } catch (e) { mem[k] = v; } // storage full: keep in memory
    if (!quiet) window.dispatchEvent(new CustomEvent('fedstore-change', {detail: k}));
  }
  var SEED_CLUBS = [
    ['c-bku', 'Bahrain Karate Union', 'Hassan Al Doseri', 'office@bku.bh', '+973 1772 4410', 'Bldg 214, Rd 3803, Block 338, Adliya, Manama'],
    ['c-mkc', 'Manama Karate Club', 'Mahmood Jaffar', 'info@manamakarate.bh', '+973 1729 3300', 'Bldg 55, Rd 1702, Block 317, Hoora, Manama'],
    ['c-rkc', 'Riffa Karate Center', 'Khalid Al Musallam', 'contact@riffakarate.bh', '+973 1777 5021', 'Bldg 12, Rd 25, Block 925, East Riffa'],
    ['c-mfl', 'Manama Fight Lab', 'Sayed Ali Kadhem', 'hello@manamafightlab.bh', '+973 3660 4100', 'Bldg 889, Rd 3618, Block 336, Adliya'],
    ['c-fdd', 'Falcon Dojo, Dubai', 'Rashed Al Ketbi', 'team@falcondojo.ae', '+971 4 338 2210', 'Al Quoz 3, Dubai, UAE'],
    ['c-dmac', 'Doha Martial Arts Ctr', 'Nasser Al Kuwari', 'admin@dmac.qa', '+974 4431 2200', 'Al Sadd, Doha, Qatar'],
    ['c-zkk', 'Zagreb Karate Klub', 'Ivan Horvat', 'ured@zkk.hr', '+385 1 4550 212', 'Savska cesta 41, Zagreb, Croatia']
  ].map(function (r) { return {id: r[0], name: r[1], coach: r[2], email: r[3], phone: r[4], address: r[5], logo: null, logoShape: 'circle', status: 'Approved'}; });
  var SEED_ATH = [
    ['BHR-00021', '870112345', 'Yusuf Al Doseri', 'Male', '1994-03-12', 'BHR', 'c-bku', []],
    ['BHR-00118', '060514227', 'Ali Hasan Jaber', 'Male', '2006-05-14', 'BHR', 'c-bku', []],
    ['BHR-00124', '090221873', 'Noor Abdulla', 'Female', '2009-02-21', 'BHR', 'c-bku', []],
    ['BHR-00131', '110907431', 'Hamad Al Sayed', 'Male', '2011-09-07', 'BHR', 'c-mkc', []],
    ['BHR-00136', '080430992', 'Reem Al Noaimi', 'Female', '2008-04-30', 'BHR', 'c-mkc', []],
    ['BHR-00102', '920414782', 'Fatima Al Sayed', 'Female', '2002-06-19', 'BHR', 'c-mfl', []],
    ['BHR-00142', '010118654', 'Omar Janahi', 'Male', '2001-01-18', 'BHR', 'c-rkc', [['c-mkc', '2022-01', '2025-08']]],
    ['BHR-00149', '070812377', 'Zainab Radhi', 'Female', '2007-08-12', 'BHR', 'c-rkc', []],
    ['ARE-00034', '784-1993-4412873-2', 'Maryam Haddad', 'Female', '1998-07-28', 'ARE', 'c-fdd', []],
    ['QAT-00052', '28935604418', 'Aisha Al Marri', 'Female', '2001-11-04', 'QAT', 'c-dmac', []],
    ['HRV-00089', '09915507212', 'Lena Kovac', 'Female', '1999-05-15', 'HRV', 'c-zkk', []]
  ].map(function (r) {
    var hist = r[7].map(function (h) { return {clubId: h[0], from: h[1], to: h[2]}; });
    hist.push({clubId: r[6], from: r[7].length ? '2025-09' : '2023-01', to: null});
    return {id: r[0], natId: r[1], name: r[2], gender: r[3], dob: r[4], nat: r[5], photo: null, clubId: r[6], history: hist};
  });
  function seed() { if (get(K.clubs, null)) return; set(K.clubs, SEED_CLUBS); set(K.ath, SEED_ATH); set(K.tr, []); }
  function norm(x) { return String(x || '').replace(/[^0-9a-z]/gi, '').toLowerCase(); }
  function ym() { var d = new Date(); return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0'); }
  var api = {
    clubs: function () { seed(); return get(K.clubs, []); },
    athletes: function () { seed(); return get(K.ath, []); },
    transfers: function () { seed(); return get(K.tr, []); },
    club: function (id) { return api.clubs().filter(function (c) { return c.id === id; })[0] || null; },
    clubByName: function (n) { return api.clubs().filter(function (c) { return c.name.toLowerCase() === String(n || '').toLowerCase(); })[0] || null; },
    findClub: function (contact) {
      var q = String(contact || '').trim().toLowerCase(), d = q.replace(/\D/g, '');
      return api.clubs().filter(function (c) {
        if (q.indexOf('@') > 0) return c.email.toLowerCase() === q;
        var cd = c.phone.replace(/\D/g, '');
        return d.length >= 7 && (cd === d || cd.slice(-d.length) === d);
      })[0] || null;
    },
    roster: function (clubId) { return api.athletes().filter(function (a) { return a.clubId === clubId; }); },
    athlete: function (id) { return api.athletes().filter(function (a) { return a.id === id; })[0] || null; },
    byNatId: function (n) { var k = norm(n); if (!k) return null; return api.athletes().filter(function (a) { return norm(a.natId) === k; })[0] || null; },
    lookup: function (q) {
      var k = norm(q); if (k.length < 4) return [];
      return api.athletes().filter(function (a) { return norm(a.natId) === k || norm(a.id) === k; });
    },
    saveClub: function (c) {
      var list = api.clubs(), i = -1;
      if (!c.id) c.id = 'c-' + Date.now().toString(36);
      list.forEach(function (x, j) { if (x.id === c.id) i = j; });
      if (i >= 0) list[i] = Object.assign({}, list[i], c); else list.push(Object.assign({status: 'Pending', logoShape: 'circle'}, c));
      set(K.clubs, list); return c.id;
    },
    nextId: function (nat) {
      var max = 0; api.athletes().forEach(function (a) { var m = String(a.id).match(/-(\d+)$/); if (m) max = Math.max(max, +m[1]); });
      return (nat || 'BHR') + '-' + String(max + 1).padStart(5, '0');
    },
    saveAthlete: function (a) {
      var list = api.athletes(), i = -1;
      if (!a.id) { a.id = api.nextId(a.nat); a.history = [{clubId: a.clubId, from: ym(), to: null}]; }
      list.forEach(function (x, j) { if (x.id === a.id) i = j; });
      if (i >= 0) list[i] = Object.assign({}, list[i], a); else list.push(a);
      set(K.ath, list); return a.id;
    },
    requestTransfer: function (athleteId, toClubId, byName) {
      var a = api.athlete(athleteId); if (!a) return null;
      var list = api.transfers();
      var dup = list.filter(function (t) { return t.athleteId === athleteId && t.toClubId === toClubId && t.status === 'Pending'; })[0];
      if (dup) return dup.id;
      var now = Date.now();
      var t = {id: 't-' + now.toString(36), athleteId: athleteId, fromClubId: a.clubId, toClubId: toClubId, requestedBy: byName || '',
        status: 'Pending', oldClub: 'Awaiting reply', ts: now, due: now + 7 * 864e5};
      list.push(t); set(K.tr, list); return t.id;
    },
    oldClubReply: function (id, release) {
      var list = api.transfers().map(function (t) { return t.id === id ? Object.assign({}, t, {oldClub: release ? 'Released' : 'Objected'}) : t; });
      set(K.tr, list);
    },
    decideTransfer: function (id, approve) {
      var list = api.transfers(), t = null;
      list = list.map(function (x) { if (x.id !== id) return x; t = Object.assign({}, x, {status: approve ? 'Approved' : 'Declined', decidedAt: Date.now()}); return t; });
      set(K.tr, list);
      if (t && approve) {
        var a = api.athlete(t.athleteId);
        if (a) {
          var hist = (a.history || []).map(function (h) { return h.to ? h : Object.assign({}, h, {to: ym()}); });
          hist.push({clubId: t.toClubId, from: ym(), to: null});
          api.saveAthlete(Object.assign({}, a, {clubId: t.toClubId, history: hist}));
        }
      }
    },
    // ---- event entries & billing (one entry + invoice per club per event)
    event: function () {
      return {id: 'gko26', code: 'GKO26', name: 'Gulf Karate Open 2026', sport: get('takeone_event_sport', 'Karate'), currency: get('takeone_event_currency', 'BHD'), deadline: '2026-11-02T23:59', drawAt: '2026-11-13T21:00', lateFee: 5,
        fees: [
          {type: 'Individual Kata', price: 10, unit: 'athlete'}, {type: 'Individual Kumite', price: 12, unit: 'athlete'},
          {type: 'Para Karate', price: 8, unit: 'athlete'}, {type: 'Kihon (youth)', price: 6, unit: 'athlete', maxAge: 13},
          {type: 'Team Kata', price: 25, unit: 'team', teamSize: 3}, {type: 'Team Kumite', price: 30, unit: 'team', teamSize: 5}
        ]};
    },
    phase: function () {
      var ev = api.event(), now = Date.now();
      if (now > new Date(ev.drawAt).getTime()) return 'locked';
      if (now > new Date(ev.deadline).getTime()) return 'late';
      return 'open';
    },
    // ---- strings table (translations). Row: { en key: { ar, fr, updated } }
    strings: function () {
      var t = get('takeone_strings', null);
      if (!t) { t = {}; var sd = window.TAKEONE_STRINGS_SEED || {}; Object.keys(sd).forEach(function (k) { t[k] = {ar: sd[k][0] || '', fr: sd[k][1] || '', updated: 0}; }); set('takeone_strings', t, true); }
      return t;
    },
    stringKeys: function () { return get('takeone_string_keys', {}); },
    registerStrings: function (keys) {
      var reg = api.stringKeys(), changed = false, now = Date.now();
      keys.forEach(function (k) { if (!reg[k]) { reg[k] = now; changed = true; } });
      if (changed) set('takeone_string_keys', reg, true);
    },
    saveString: function (k, lang, value) {
      var t = api.strings(); t[k] = Object.assign({ar: '', fr: ''}, t[k] || {}); t[k][lang] = value; t[k].updated = Date.now();
      set('takeone_strings', t);
    },
    importStrings: function (obj) {
      var t = api.strings(), n = 0;
      Object.keys(obj || {}).forEach(function (k) { var r = obj[k]; if (!r || typeof r !== 'object') return; t[k] = Object.assign({ar: '', fr: ''}, t[k] || {}, {ar: r.ar != null ? r.ar : (t[k] || {}).ar, fr: r.fr != null ? r.fr : (t[k] || {}).fr, updated: Date.now()}); n++; });
      set('takeone_strings', t); return n;
    },
    eventDocs: function () { return get('takeone_event_docs', []); },
    setEventDocs: function (list) { set('takeone_event_docs', list || []); },
    entries: function () { return get('takeone_entries', {}); },
    entry: function (clubId) { return api.entries()[clubId] || null; },
    saveEntry: function (e) {
      var all = api.entries();
      if (!e.ref) { var n = Object.keys(all).length + 1; var c = api.club(e.clubId); e.ref = api.event().code + '-' + String((c && c.name) || 'CLB').replace(/[^A-Za-z]/g, '').slice(0, 3).toUpperCase() + '-' + String(n).padStart(3, '0'); }
      all[e.clubId] = e; set('takeone_entries', all);
      // mirror active athletes into the dashboard's registration feed
      try {
        var regs = get('takeone_club_regs', []).filter(function (r) { return r.clubId !== e.clubId; });
        var c = api.club(e.clubId) || {};
        regs.push({clubId: e.clubId, ref: e.ref, club: c.name, coach: c.coach, phone: c.phone, email: c.email, address: c.address, logo: c.logo || null, logoShape: c.logoShape || 'circle', ts: e.updatedAt || Date.now(),
          athletes: e.athletes.filter(function (a) { return a.status !== 'Withdrawn'; }).map(function (a) { return {name: a.name, gender: a.gender, dob: a.dob, weight: a.weight, nat: a.nat, natId: a.natId, photo: a.photo || null, athleteId: a.athleteId, takeoneId: a.athleteId, types: a.types}; })});
        set('takeone_club_regs', regs);
      } catch (x) {}
      return e;
    },
    invoice: function (e) {
      var ev = api.event(), fee = {}; ev.fees.forEach(function (f) { fee[f.type] = f; });
      var lines = [], perAthlete = [];
      (e.athletes || []).forEach(function (a) {
        if (a.status === 'Withdrawn' && !a.chargeable) return;
        var sum = 0, items = [];
        (a.types || []).forEach(function (t) { var f = fee[t]; if (f && f.unit === 'athlete') { sum += f.price; items.push({label: t, amount: f.price}); } });
        if (a.late) { sum += ev.lateFee; items.push({label: 'Late entry fee', amount: ev.lateFee}); }
        if (sum) { perAthlete.push({name: a.name, items: items, total: sum, withdrawn: a.status === 'Withdrawn'}); lines.push({label: a.name, detail: items.map(function (i) { return i.label; }).join(' + '), amount: sum}); }
      });
      var teams = [];
      Object.keys(e.teams || {}).forEach(function (t) { var n = +e.teams[t] || 0, f = fee[t]; if (n && f) { teams.push({label: t, n: n, amount: n * f.price}); lines.push({label: t + ' × ' + n, detail: n + ' team' + (n > 1 ? 's' : '') + ' × ' + f.price, amount: n * f.price}); } });
      var total = lines.reduce(function (s, l) { return s + l.amount; }, 0);
      var paid = (e.payments || []).filter(function (p) { return p.status === 'Paid'; }).reduce(function (s, p) { return s + p.amount; }, 0);
      var pending = (e.payments || []).filter(function (p) { return p.status === 'Awaiting transfer'; }).reduce(function (s, p) { return s + p.amount; }, 0);
      return {lines: lines, perAthlete: perAthlete, teams: teams, total: total, paid: paid, pending: pending, balance: total - paid - pending, currency: ev.currency};
    },
    reset: function () { set(K.clubs, SEED_CLUBS); set(K.ath, SEED_ATH); set(K.tr, []); set('takeone_entries', {}); }
  };
  window.FedStore = api;
})();
