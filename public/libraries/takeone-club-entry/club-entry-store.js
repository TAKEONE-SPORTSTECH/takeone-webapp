/*
 * The browser half of "Register your club" on a CLUB-hosted event
 * (App\Events\Support\ClubEntry\PlatformClubEntryDesk) — the same two globals
 * the shared screens talk to (window.FedStore for reads, window.TakeOneFed for
 * writes), in the same shapes as the federation's own store, backed by the
 * event's own JSON doors (boot.urls). Self-hosted; no dependencies.
 *
 * Every number shown here is a PREVIEW: the server recomputes the bill.
 */
(function () {
  'use strict';
  var boot = window.__TAKEONE_FED || {};
  var urls = boot.urls || {};
  var S = boot.store || {};
  var C = {
    clubs: S.clubs || [], athletes: S.athletes || [], entries: S.entries || {},
    event: S.event || null, phase: S.phase || 'open', docs: S.docs || [], verified: S.verifiedClub || null
  };

  function changed(k) { window.dispatchEvent(new CustomEvent('fedstore-change', {detail: k || 'store'})); }
  function csrf() { var m = document.querySelector('meta[name="csrf-token"]'); return m ? m.getAttribute('content') : (boot.csrf || ''); }
  function req(method, url, body) {
    return fetch(url, {
      method: method, credentials: 'same-origin',
      headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest'},
      body: body ? JSON.stringify(body) : undefined
    }).then(function (r) { return r.json().catch(function () { return {}; }).then(function (j) { j.__ok = r.ok; return j; }); });
  }
  function adopt(st) {
    if (!st) return;
    C.clubs = st.clubs || []; C.athletes = st.athletes || []; C.entries = st.entries || {}; C.verified = st.verifiedClub || null;
    if (st.event) C.event = st.event; if (st.phase) C.phase = st.phase; if (st.docs) C.docs = st.docs;
    changed('store');
  }

  var api = {
    clubs: function () { return C.clubs; },
    athletes: function () { return C.athletes; },
    transfers: function () { return []; },
    club: function (id) { return C.clubs.filter(function (c) { return c.id === id; })[0] || null; },
    verifiedClub: function () { return C.verified; },
    roster: function (clubId) { return C.athletes.filter(function (a) { return a.clubId === clubId; }); },
    athlete: function (id) { return C.athletes.filter(function (a) { return a.id === id; })[0] || null; },
    lookup: function () { return []; },          // no platform-wide athlete search from an invitation
    byNatId: function () { return null; },
    event: function () { return C.event || {id: null, name: '', sport: '', currency: 'BHD', deadline: '2099-12-31T23:59', drawAt: '2099-12-31T23:59', lateFee: 0, base: 0, fees: []}; },
    phase: function () { return C.phase; },
    eventDocs: function () { return C.docs; },
    entries: function () { return C.entries; },
    entry: function (clubId) { return C.entries[clubId] || null; },
    saveEntry: function (e) {
      var before = C.entries[e.clubId] || {athletes: []};
      (e.athletes || []).forEach(function (a) {
        var was = (before.athletes || []).filter(function (b) { return b.athleteId === a.athleteId; })[0];
        if (was && was.status !== 'Withdrawn' && a.status === 'Withdrawn') {
          req('POST', urls.withdraw, {athleteId: a.athleteId}).then(function (j) { if (j.__ok) adopt(j.store); });
        }
      });
      C.entries[e.clubId] = e; changed('entries');
    },
    // The event's base price per athlete, plus each option ticked, plus the late fee.
    invoice: function (e) {
      var ev = api.event(), fee = {}, lines = [], perAthlete = [], base = +ev.base || 0, late = +ev.lateFee || 0;
      (ev.fees || []).forEach(function (f) { fee[f.type] = f; });
      (e.athletes || []).forEach(function (a) {
        if (a.status === 'Withdrawn' && !a.chargeable) return;
        var items = [], sum = 0;
        if (base) { items.push({label: 'Entry', amount: base}); sum += base; }
        (a.types || []).forEach(function (t) { var f = fee[t]; if (f && f.price) { sum += f.price; items.push({label: t, amount: f.price}); } });
        if (late && a.late) { items.push({label: 'Late', amount: late}); sum += late; }
        if (sum) { perAthlete.push({name: a.name, items: items, total: sum, withdrawn: a.status === 'Withdrawn'}); lines.push({label: a.name, detail: items.map(function (i) { return i.label; }).join(' + '), amount: sum}); }
      });
      var total = lines.reduce(function (s, l) { return s + l.amount; }, 0);
      var paid = (e.payments || []).filter(function (p) { return p.status === 'Paid'; }).reduce(function (s, p) { return s + p.amount; }, 0);
      var pending = (e.payments || []).filter(function (p) { return p.status === 'Awaiting transfer'; }).reduce(function (s, p) { return s + p.amount; }, 0);
      return {lines: lines, perAthlete: perAthlete, teams: [], total: total, paid: paid, pending: pending, balance: Math.max(0, total - paid)};
    },
    reset: function () {}
  };

  var T = window.TakeOneFed = window.TakeOneFed || {};
  T.sendCode = function (contact) { if (urls.code) req('POST', urls.code, {contact: contact}); };
  T.verifyCode = function (contact, code) {
    return req('POST', urls.verify, {contact: contact, code: code}).then(function (j) {
      if (j.__ok && j.choices && j.choices.length) return {choices: j.choices};
      if (!j.__ok || !j.store) return null;
      adopt(j.store); return C.verified;
    }).catch(function () { return null; });
  };
  T.chooseClub = function (key) {
    return req('POST', urls.choose, {club: key}).then(function (j) { if (!j.__ok || !j.store) return null; adopt(j.store); return C.verified; }).catch(function () { return null; });
  };
  T.searchClubs = function (q) {
    if (String(q || '').trim().length < 2) return Promise.resolve([]);
    return req('GET', urls.clubs + '?q=' + encodeURIComponent(String(q).trim())).then(function (j) { return j.__ok ? (j.clubs || []) : []; }).catch(function () { return []; });
  };
  T.sendClubCode = function (key) { return req('POST', urls.code, {club: key}).catch(function () {}); };
  T.verifyClubCode = function (key, code) {
    return req('POST', urls.verify, {club: key, code: code}).then(function (j) { if (!j.__ok || !j.store) return null; adopt(j.store); return C.verified; }).catch(function () { return null; });
  };
  T.checkClub = function (c) {
    return req('POST', urls.checkClub, {name: c.name || '', email: c.email || '', phone: c.phone || ''}).then(function (j) { return j.__ok ? (j.match || null) : null; }).catch(function () { return null; });
  };
  T.dial = function () { return boot.dial || []; };
  T.fedCountry = function () { return (S.federation && S.federation.country) || ''; };
  T.submitEntry = function (payload) {
    return req('POST', urls.submit, payload).then(function (j) {
      if (!j.__ok) return {ok: false, message: j.message, code: j.code || null, match: j.match || null};
      adopt(j.store);
      return {ok: true, ref: j.ref, balance: +j.balance || 0, clubId: C.verified, added: j.added, skipped: j.skipped || []};
    }).catch(function () { return {ok: false, network: true, message: 'Could not reach the server. Tap Submit again — nothing is sent twice.'}; });
  };
  // The club's proof of payment (a picture as a data URL). The server checks the bytes.
  T.uploadProof = function (dataUrl) {
    if (!urls.proof) return Promise.resolve({ok: false});
    return req('POST', urls.proof, {proof: dataUrl}).then(function (j) {
      if (j.__ok) adopt(j.store);
      return {ok: !!j.__ok, message: j.message || ''};
    }).catch(function () { return {ok: false, message: 'Could not reach the server. Try again.'}; });
  };
  T.bank = function () { return (S.federation && S.federation.bank) || null; };
  T.beneficiary = function () { var b = T.bank(); return (b && b.beneficiary) || (S.federation && S.federation.name) || ''; };

  window.FedStore = api;
})();
