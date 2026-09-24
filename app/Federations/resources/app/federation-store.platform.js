/*
 * federation-store.platform.js — NOT part of the designed prototype.
 *
 * The prototype's federation-store.js kept clubs, athletes, transfers and
 * entries in this browser. Its README says the production move is "replace
 * the body of each function with an API call; keep the same function names
 * and the pages will not need changes". This file is exactly that: the same
 * window.FedStore, same names, same shapes — served in its place by
 * App\Federations\Services\FederationApp.
 *
 * The pages call the store synchronously, so reads come from a copy the
 * server handed over when the page loaded (window.__TAKEONE_FED.store), and
 * writes go to the server, whose answer replaces the copy and redraws the
 * page (`fedstore-change`, which both pages already listen for).
 *
 * The strings table (translations) is the federation's own, kept on the
 * server (FederationStrings) and filled in by the bridge.
 */
(function () {
  'use strict';
  var boot = window.__TAKEONE_FED || {};
  var urls = boot.urls || {};
  // Staff pages: `fedstore` (their `store` is the prototype's own keys).
  var S = (boot.mode === 'staff' ? boot.fedstore : boot.store) || {};
  var C = {
    clubs: S.clubs || [], athletes: S.athletes || [], transfers: S.transfers || [],
    entries: S.entries || {},               // invitation: {clubKey: entry}
    byEvent: S.entriesByEvent || {},        // dashboard: {eventId: {clubKey: entry}}
    events: S.inviteEvents || {},           // dashboard: {eventId: event()}
    links: S.clubLinks || {},               // dashboard: {clubKey: personal token}
    event: S.event || null, phase: S.phase || 'open', docs: S.docs || [],
    verified: S.verifiedClub || null, lookups: {}
  };

  // The event the dashboard has open, set by the bridge when one is opened.
  function cur() { return (window.TakeOneFed && window.TakeOneFed.currentEvent) || null; }
  function changed(k) { window.dispatchEvent(new CustomEvent('fedstore-change', {detail: k || 'store'})); }
  function csrf() { var m = document.querySelector('meta[name="csrf-token"]'); return m ? m.getAttribute('content') : ''; }
  function req(method, url, body) {
    return fetch(url, {
      method: method, credentials: 'same-origin',
      headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest'},
      body: body ? JSON.stringify(body) : undefined
    }).then(function (r) { return r.json().catch(function () { return {}; }).then(function (j) { j.__ok = r.ok; return j; }); });
  }
  function norm(x) { return String(x || '').replace(/[^0-9a-z]/gi, '').toLowerCase(); }
  function mergeBy(list, rows) {
    var out = list.slice();
    (rows || []).forEach(function (r) { var i = out.findIndex(function (x) { return x.id === r.id; }); if (i >= 0) out[i] = Object.assign({}, out[i], r); else out.push(r); });
    return out;
  }
  // An answer from the invitation's doors carries the whole store again.
  function adopt(st) {
    if (!st) return;
    C.clubs = st.clubs || []; C.athletes = st.athletes || []; C.transfers = st.transfers || [];
    // A new identity may see what the old one could not: ask again.
    if ((st.verifiedClub || null) !== C.verified) C.lookups = {};
    C.entries = st.entries || {}; C.verified = st.verifiedClub || null;
    if (st.event) C.event = st.event; if (st.phase) C.phase = st.phase; if (st.docs) C.docs = st.docs;
    changed('store');
  }
  // An answer from the dashboard's doors carries its own lists.
  function adoptStaff(j) {
    if (j.clubs) C.clubs = j.clubs; if (j.athletes) C.athletes = j.athletes; if (j.transfers) C.transfers = j.transfers;
    if (j.entriesByEvent) C.byEvent = j.entriesByEvent; if (j.clubLinks) C.links = j.clubLinks;
    changed('store');
  }

  // --------------------------------------------------------------- strings
  // The prototype's own code: it reads and writes `takeone_strings` in
  // browser storage. The bridge fills that key from the federation's table
  // (FederationStrings) and forwards every save to the server.
  function get(k, d) { try { var v = JSON.parse(localStorage.getItem(k) || 'null'); return v == null ? d : v; } catch (e) { return d; } }
  function set(k, v, quiet) { try { localStorage.setItem(k, JSON.stringify(v)); } catch (e) {} if (!quiet) changed(k); }

  var api = {
    // ------------------------------------------------------------- reads
    clubs: function () { return C.clubs; },
    athletes: function () { return C.athletes; },
    transfers: function () { return C.transfers; },
    club: function (id) { return C.clubs.filter(function (c) { return c.id === id; })[0] || null; },
    clubByName: function (n) { return C.clubs.filter(function (c) { return String(c.name).toLowerCase() === String(n || '').toLowerCase(); })[0] || null; },
    verifiedClub: function () { return C.verified; },
    // Never answered in the browser: a code is asked for through the server.
    findClub: function () { return null; },
    roster: function (clubId) { return C.athletes.filter(function (a) { return a.clubId === clubId && a.natId !== undefined; }); },
    athlete: function (id) { return C.athletes.filter(function (a) { return a.id === id; })[0] || null; },

    // Exact match only, asked of the server and answered on the next draw.
    lookup: function (q) {
      var k = norm(q);
      if (k.length < 4) return [];
      if (C.lookups[k]) return C.lookups[k].map(function (id) { return api.athlete(id); }).filter(Boolean);
      var own = C.athletes.filter(function (a) { return norm(a.id) === k || norm(a.natId) === k; });
      if (own.length) return own;
      if (urls.lookup && !C.lookups['__' + k]) {
        C.lookups['__' + k] = true;
        clearTimeout(api._lt);
        api._lt = setTimeout(function () {
          req('GET', urls.lookup + '?q=' + encodeURIComponent(q)).then(function (j) {
            if (!j.__ok) return;
            C.athletes = mergeBy(C.athletes, j.athletes); C.clubs = mergeBy(C.clubs, j.clubs);
            C.lookups[k] = (j.athletes || []).map(function (a) { return a.id; });
            changed('lookup');
          });
        }, 350);
      }
      return [];
    },
    byNatId: function (n) {
      var k = norm(n); if (!k) return null;
      var own = C.athletes.filter(function (a) { return a.natId !== undefined && norm(a.natId) === k; })[0];
      if (own) return own;
      var found = api.lookup(n);
      return found.filter(function (a) { return a.natKey && norm(a.natKey) === k; })[0] || null;
    },

    // ------------------------------------------------------------- event
    event: function () {
      if (C.event) return C.event;
      if (cur() && C.events[cur()]) return C.events[cur()];
      return {id: null, code: '', name: '', sport: 'Karate', currency: 'BHD', deadline: '2099-12-31T23:59', drawAt: '2099-12-31T23:59', lateFee: 0, fees: []};
    },
    phase: function () {
      if (C.event) return C.phase;
      var ev = api.event(), now = Date.now();
      if (now > new Date(ev.drawAt).getTime()) return 'locked';
      if (now > new Date(ev.deadline).getTime()) return 'late';
      return 'open';
    },
    eventDocs: function () { return C.docs; },
    setEventDocs: function () {},   // documents are saved with the event

    // ----------------------------------------------------------- entries
    entries: function () { return C.event ? C.entries : ((cur() && C.byEvent[cur()]) || {}); },
    entry: function (clubId) { return api.entries()[clubId] || null; },
    saveEntry: function (e) {
      var before = api.entry(e.clubId) || {athletes: [], payments: []};
      if (C.event) {
        // The invitation's amend screen: the one write it makes is a withdrawal.
        (e.athletes || []).forEach(function (a) {
          var was = (before.athletes || []).filter(function (b) { return b.athleteId === a.athleteId; })[0];
          if (a.status === 'Withdrawn' && was && was.status !== 'Withdrawn' && urls.withdraw) {
            req('POST', urls.withdraw, {athleteId: a.athleteId}).then(function (j) { if (j.__ok) adopt(j.store); });
          }
        });
        C.entries[e.clubId] = e; changed('entry');
        return e;
      }
      // The dashboard: confirming a bank transfer arrived.
      var confirmed = (e.payments || []).some(function (p, i) { return p.status === 'Paid' && (before.payments[i] || {}).status === 'Awaiting transfer'; });
      if (confirmed && urls.entryConfirm && e.id) {
        req('POST', urls.entryConfirm, {entry: e.id}).then(function (j) { if (j.__ok) adoptStaff(j); else window.location.reload(); });
      }
      if (cur()) { C.byEvent[cur()] = C.byEvent[cur()] || {}; C.byEvent[cur()][e.clubId] = e; }
      changed('entry');
      return e;
    },
    // The prototype's own arithmetic, unchanged: it draws the figures; the
    // server computes the same figures again when it matters.
    invoice: function (e) {
      var ev = (e && e.eventId && C.events[e.eventId]) || api.event(), fee = {};
      (ev.fees || []).forEach(function (f) { fee[f.type] = f; });
      var lines = [], perAthlete = [];
      (e.athletes || []).forEach(function (a) {
        if (a.status === 'Withdrawn' && !a.chargeable) return;
        var sum = 0, items = [];
        (a.types || []).forEach(function (t) { var f = fee[t]; if (f && f.unit === 'athlete') { sum += f.price; items.push({label: t, amount: f.price}); } });
        if (a.late) { sum += ev.lateFee || 0; items.push({label: 'Late entry fee', amount: ev.lateFee || 0}); }
        if (sum) { perAthlete.push({name: a.name, items: items, total: sum, withdrawn: a.status === 'Withdrawn'}); lines.push({label: a.name, detail: items.map(function (i) { return i.label; }).join(' + '), amount: sum}); }
      });
      var teams = [];
      Object.keys(e.teams || {}).forEach(function (t) { var n = +e.teams[t] || 0, f = fee[t]; if (n && f) { teams.push({label: t, n: n, amount: n * f.price}); lines.push({label: t + ' × ' + n, detail: n + ' team' + (n > 1 ? 's' : '') + ' × ' + f.price, amount: n * f.price}); } });
      var total = lines.reduce(function (s, l) { return s + l.amount; }, 0);
      var paid = (e.payments || []).filter(function (p) { return p.status === 'Paid'; }).reduce(function (s, p) { return s + p.amount; }, 0);
      var pending = (e.payments || []).filter(function (p) { return p.status === 'Awaiting transfer'; }).reduce(function (s, p) { return s + p.amount; }, 0);
      return {lines: lines, perAthlete: perAthlete, teams: teams, total: total, paid: paid, pending: pending, balance: total - paid - pending, currency: ev.currency};
    },

    // --------------------------------------------------------- transfers
    oldClubReply: function (id, release) {
      C.transfers = C.transfers.map(function (t) { return t.id === id ? Object.assign({}, t, {oldClub: release ? 'Released' : 'Objected'}) : t; });
      changed('transfers');
      if (urls.transferReply) req('POST', urls.transferReply, {transfer: id, released: !!release}).then(function (j) { if (j.__ok) adoptStaff(j); else window.location.reload(); });
    },
    decideTransfer: function (id, approve) {
      C.transfers = C.transfers.map(function (t) { return t.id === id ? Object.assign({}, t, {status: approve ? 'Approved' : 'Declined', decidedAt: Date.now()}) : t; });
      changed('transfers');
      if (urls.transferDecide) req('POST', urls.transferDecide, {transfer: id, approve: !!approve}).then(function (j) { if (j.__ok) adoptStaff(j); else window.location.reload(); });
    },

    // Writes the invitation used to make one by one. It now submits once
    // (TakeOneFed.submitEntry); these stay so nothing calling them breaks.
    saveClub: function (c) { return c.id || null; },
    saveAthlete: function (a) { return a.id || null; },
    requestTransfer: function () { return null; },
    nextId: function (nat) { return (nat || 'XXX') + '-·····'; },

    // ----------------------------------------------------------- strings
    strings: function () {
      var t = get('takeone_strings', null);
      if (!t) { t = {}; var sd = window.TAKEONE_STRINGS_SEED || {}; Object.keys(sd).forEach(function (k) { t[k] = {ar: sd[k][0] || '', fr: sd[k][1] || '', updated: 0}; }); set('takeone_strings', t, true); }
      return t;
    },
    stringKeys: function () { return get('takeone_string_keys', {}); },
    registerStrings: function (keys) {
      var reg = api.stringKeys(), ch = false, now = Date.now();
      keys.forEach(function (k) { if (!reg[k]) { reg[k] = now; ch = true; } });
      if (ch) set('takeone_string_keys', reg, true);
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
    reset: function () {}
  };

  // ------------------------------------------------------------ platform
  var T = window.TakeOneFed = window.TakeOneFed || {};

  T.sendCode = function (contact) { if (urls.code) req('POST', urls.code, {contact: contact}); };
  T.verifyCode = function (contact, code) {
    if (!urls.verify) return Promise.resolve(null);
    return req('POST', urls.verify, {contact: contact, code: code}).then(function (j) {
      if (!j.__ok || !j.store) return null;
      adopt(j.store);
      return C.verified;
    }).catch(function () { return null; });
  };
  T.submitEntry = function (payload) {
    return req('POST', urls.submit, payload).then(function (j) {
      if (!j.__ok) return {ok: false, message: j.message};
      adopt(j.store);
      return {ok: true, ref: j.ref, balance: +j.balance || 0, clubId: C.verified};
    }).catch(function () { return {ok: false, message: 'Could not reach the server. Your entry was not sent — try again.'}; });
  };
  // The Athletes page's rows (the prototype's `athData` shape).
  T.athleteDirectory = function () { return (S.directory || []).map(function (a) { return Object.assign({}, a, {age: String(a.age)}); }); };
  T.officials = function () { return (S.officials || []).slice(); };
  T.eventTexts = function () { return S.texts || null; };
  T.coverArt = function () { return S.cover || ''; };
  // The account a club pays entry fees into (the federation's own, from its record).
  T.bank = function () { return (S.federation && S.federation.bank) || null; };
  T.beneficiary = function () { var b = T.bank(); return (b && b.beneficiary) || (S.federation && S.federation.name) || ''; };
  T.clubLink = function (origin, clubId) {
    var token = C.links[clubId];
    var base = String(origin).split('?')[0];
    return base + '?event=' + encodeURIComponent(cur() || '') + (token ? '&club=' + encodeURIComponent(token) : '');
  };

  window.FedStore = api;
})();
