/*
 * takeone-bridge.js — NOT part of the designed prototype.
 *
 * The only file in this folder the platform wrote. It runs before the
 * prototype's runtime (support.js) and joins the page to the platform
 * WITHOUT editing the page:
 *
 *  1. The prototype keeps its data in browser storage under fixed keys. For
 *     every key the platform owns, the server's copy arrives in
 *     window.__TAKEONE_FED.store and is written into storage before the page
 *     reads it — so the page draws real records, never its demo seed.
 *  2. When the page saves one of those keys, the save is forwarded to the
 *     server, which decides what of it may happen and answers with its own
 *     copy. That copy replaces the page's, and the page redraws from it.
 *  3. Staff pages are already behind the platform's sign-in and 2FA (the
 *     controller refused anyone else), so the signed-in platform user IS the
 *     prototype's session. Log out and Security & 2FA are the platform's own.
 *
 * Boot data is written by App\Federations\Controllers\FederationAppController.
 * Nothing in it is a secret; every write is re-authorised on the server.
 */
(function () {
  'use strict';
  var boot = window.__TAKEONE_FED || {};
  var urls = boot.urls || {};
  var ls = window.localStorage;
  var rawSet = Storage.prototype.setItem;
  var quiet = false;   // true while the bridge itself is writing

  function csrf() {
    var m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.getAttribute('content') : (boot.csrf || '');
  }

  function put(key, value) {
    quiet = true;
    try { rawSet.call(ls, key, typeof value === 'string' ? value : JSON.stringify(value)); } catch (e) {} finally { quiet = false; }
  }

  // The platform's logout is a real POST with CSRF.
  function postForm(url) {
    var f = document.createElement('form');
    f.method = 'POST';
    f.action = url;
    var t = document.createElement('input');
    t.type = 'hidden';
    t.name = '_token';
    t.value = csrf();
    f.appendChild(t);
    document.body.appendChild(f);
    f.submit();
  }

  function uuid() {
    if (window.crypto && crypto.randomUUID) return crypto.randomUUID();
    var b = crypto.getRandomValues(new Uint8Array(16));
    b[6] = (b[6] & 15) | 64; b[8] = (b[8] & 63) | 128;
    var h = Array.from(b, function (x) { return (x + 256).toString(16).slice(1); }).join('');
    return h.slice(0, 8) + '-' + h.slice(8, 12) + '-' + h.slice(12, 16) + '-' + h.slice(16, 20) + '-' + h.slice(20);
  }

  window.TakeOneFed = {
    boot: boot,
    csrf: csrf,
    newId: uuid,
    // The bank reference for an account's n-th invoice. The server numbers
    // invoices the same way (FederationBilling::invoiceNumber), so what the
    // page shows before the transfer is what the invoice will carry.
    invoiceNo: function (accId, n) {
      return 'INV-' + new Date().getFullYear() + '-' + String(accId || '').replace(/-/g, '').slice(0, 4).toUpperCase() + '-' + String(n).padStart(4, '0');
    },
    // TakeOne's own account, for the subscription's bank transfer (config).
    takeoneBank: function () {
      var b = boot.takeoneBank || {};
      return {tkBeneficiary: b.beneficiary || '', tkBank: b.bank || '', tkHasBankName: !!b.bank, tkIban: b.iban || '',
        tkHasIban: !!b.iban, tkNoIban: !b.iban, tkSwift: b.swift || '', tkHasSwift: !!b.swift};
    },
    logout: function () { if (urls.logout) postForm(urls.logout); },
    security: function () { if (urls.security) { window.location.href = urls.security; return true; } return false; }
  };

  if (boot.mode !== 'staff' || !boot.user || !boot.user.email) return;

  // ------------------------------------------------------------ events
  var staged = {};   // blob: URL -> Promise<token>

  function jsonPost(url, body) {
    return fetch(url, {
      method: 'POST', credentials: 'same-origin',
      headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest'},
      body: JSON.stringify(body)
    }).then(function (res) { return res.json().catch(function () { return {}; }).then(function (j) { j.__ok = res.ok; j.__status = res.status; return j; }); });
  }

  window.TakeOneFed.events = function () { return (boot.events || []).slice(); };

  // What the page may read off an event when there is none yet.
  window.TakeOneFed.blankEvent = function () {
    return {id: null, name: '', sport: '', status: 'Planning', dates: 'TBC', venue: 'TBC', city: '', reg: 0, cap: null,
      tickets: '\u2014', revenue: '\u2014', checkins: 0, divisions: 0, docs: [], types: [], fees: [], specs: [], reqs: []};
  };

  // A document picked in the form uploads at once; the page keeps the blob
  // link it always used, and the save swaps it for the staging token.
  window.TakeOneFed.stageDoc = function (file) {
    var url = URL.createObjectURL(file);
    var fd = new FormData();
    fd.append('file', file);
    staged[url] = fetch(urls.uploads, {method: 'POST', credentials: 'same-origin', body: fd,
      headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest'}})
      .then(function (r) { return r.json(); }).then(function (j) { return j && j.success ? j.token : null; })
      .catch(function () { return null; });
    return url;
  };

  window.TakeOneFed.saveEvent = function (ev, comp) {
    var docs = (ev.docs || []).slice();
    Promise.all(docs.map(function (d) {
      if (d.uuid) return Promise.resolve({uuid: d.uuid, name: d.name});
      var p = staged[d.url];
      return p ? p.then(function (token) { return token ? {token: token, name: d.name} : null; }) : Promise.resolve(null);
    })).then(function (list) {
      var body = {}; Object.keys(ev).forEach(function (k) { body[k] = ev[k]; });
      body.docs = list.filter(Boolean);
      var org = null;
      try { org = comp && comp.curOrg ? comp.curOrg().id : null; } catch (e) {}
      return jsonPost(urls.events, {event: body, orgId: org});
    }).then(function (j) {
      if (!j.__ok || !j.event) return window.location.reload();
      if (comp && comp.setState) comp.setState(function (st) {
        return {events: (st.events || []).map(function (e) { return e.id === j.event.id ? Object.assign({}, e, j.event) : e; })};
      });
    }).catch(function () { window.location.reload(); });
  };

  // The federation approves one registered athlete.
  window.TakeOneFed.approveReg = function (r) {
    if (!r || !r.entryId || !urls.regsApprove) return;
    jsonPost(urls.regsApprove, {entry: r.entryId, athlete: r.takeoneId}).then(function (j) {
      if (!j.__ok) return window.location.reload();
      if (j.regs) { put('takeone_club_regs', j.regs); lastRegs = JSON.stringify(j.regs); }
    });
  };

  // Registrations for one event (filled from the entries clubs submit).
  window.TakeOneFed.clubRegs = function (eventId) {
    window.TakeOneFed.currentEvent = eventId || null;
    var all = [];
    try { all = JSON.parse(ls.getItem('takeone_club_regs') || '[]') || []; } catch (e) {}
    return all.filter(function (sub) { return !sub.eventId || sub.eventId === eventId; });
  };

  // ------------------------------------------------------------ draw & results
  // The server keeps, per event and division, the locked first round (Takeone
  // IDs, null = bye) and the results on it (winner as a Takeone ID). The page
  // draws and compares by NAME, so this translates both ways against the
  // division's entrants. Its own `results` state is kept but no longer read:
  // it is per page, not per event, and it dies on reload.
  var draws = boot.draws || {};
  var lastSlots = {};   // "<event>|<division>" -> the first round last drawn

  function drawOf(divKey) {
    var ev = draws[window.TakeOneFed.currentEvent];
    return ev && ev[divKey] ? ev[divKey] : null;
  }

  // The division's first round: the locked one if a result was ever recorded,
  // otherwise the page's own fair draw. A locked athlete who has since
  // withdrawn becomes a bye.
  window.TakeOneFed.drawSlots = function (divKey, entrants, fresh) {
    var d = drawOf(divKey), slots;
    if (d && d.slots && d.slots.length) {
      var byTk = {};
      entrants.forEach(function (e) { if (e && e.takeoneId) byTk[e.takeoneId] = e; });
      slots = d.slots.map(function (tk) { return tk && byTk[tk] ? byTk[tk] : null; });
    } else {
      slots = fresh();
    }
    lastSlots[window.TakeOneFed.currentEvent + '|' + divKey] = slots.map(function (e) { return e && e.takeoneId ? e.takeoneId : null; });
    return slots;
  };

  window.TakeOneFed.divResults = function (divKey, entrants) {
    var d = drawOf(divKey), out = {};
    if (!d || !d.results) return out;
    var name = {};
    entrants.forEach(function (e) { if (e && e.takeoneId) name[e.takeoneId] = e.name; });
    Object.keys(d.results).forEach(function (id) {
      var r = d.results[id];
      if (r && name[r.winner]) out[id] = {winner: name[r.winner], by: r.by};
    });
    return out;
  };

  // `by` null = clear. Shown at once; the server's answer then replaces it.
  window.TakeOneFed.saveResult = function (pk, by, comp) {
    var evId = window.TakeOneFed.currentEvent;
    if (!pk || !evId || !urls.draws) return;
    var ids = pk.ids || [];
    var winner = by ? (pk.winner === pk.a ? ids[0] : ids[1]) : null;
    if (by && !winner) return;
    var slots = lastSlots[evId + '|' + pk.divKey] || [];
    var ev = draws[evId] = draws[evId] || {};
    var d = ev[pk.divKey] = ev[pk.divKey] || {slots: slots, results: {}};
    if (by) d.results[pk.id] = {winner: winner, by: by}; else delete d.results[pk.id];
    function redraw() { if (comp && comp.setState) comp.setState(function (st) { return {fsTick: (st.fsTick || 0) + 1}; }); }
    jsonPost(urls.draws, {event: evId, division: pk.divKey, match: pk.id, winner: winner, by: by, slots: slots}).then(function (j) {
      if (j.__status === 401 || j.__status === 419) return window.location.reload();
      if (!('division' in j)) return window.location.reload();
      if (j.division) ev[pk.divKey] = j.division; else delete ev[pk.divKey];
      redraw();
    }).catch(function () { window.location.reload(); });
  };

  // ------------------------------------------------------------ messages
  // The chat dock writes to a club's admins in their own Messenger. The page
  // keeps its threads in `fed-messages`; the server's copy replaces it after
  // every send and whenever the realtime signal says something changed.
  var msgClubs = boot.messageClubs || {};
  window.TakeOneFed.canMessage = function (name) { return !!msgClubs[name]; };

  function adoptMessages(j) {
    if (j && j.messages) { put('fed-messages', j.messages); window.dispatchEvent(new CustomEvent('fedstore-change', {detail: 'fed-messages'})); }
    if (j && j.clubs) msgClubs = j.clubs;
  }
  window.TakeOneFed.sendMessage = function (name, text) {
    var key = msgClubs[name];
    if (!key || !urls.messages) return;
    jsonPost(urls.messages, {club: key, text: text}).then(function (j) {
      if (j.__status === 401 || j.__status === 419) return window.location.reload();
      adoptMessages(j);
    }).catch(function () {});
  };
  var msgT = null;
  window.addEventListener('realtime:federation', function (e) {
    if (!e.detail || e.detail.action !== 'messages' || !urls.messagesRead) return;
    clearTimeout(msgT);
    msgT = setTimeout(function () {
      fetch(urls.messagesRead, {credentials: 'same-origin', headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}})
        .then(function (r) { return r.ok ? r.json() : null; }).then(adoptMessages).catch(function () {});
    }, 150);
  });

  // ---------------------------------------------------------------- 1. hydrate
  var store = boot.store || {};
  Object.keys(store).forEach(function (k) { put(k, store[k]); });
  put('fed-session', boot.user.email);
  try { sessionStorage.removeItem('fed-session'); } catch (e) {}
  // Demo-only leftovers from running the prototype on its own.
  try { ls.removeItem('fed-brand'); } catch (e) {}

  // ------------------------------------------------------------ 2. write-back
  // key -> [endpoint, request field, response field]
  var ROUTES = {
    'fed-orgs': [urls.orgs, 'orgs', 'orgs'],
    'fed-users': [urls.users, 'users', 'users'],
    'fed-region': [urls.region, 'region', 'region'],
    // Settings → Translations: this federation's strings table.
    'takeone_strings': [urls.strings, 'strings', 'strings']
  };
  // `fed-billing:<account uuid>` — one key per billing account.
  function route(key) {
    if (ROUTES[key]) return ROUTES[key];
    if (String(key).indexOf('fed-billing:') === 0 && urls.billing) return [urls.billing, 'billing', 'billing', key.slice(12)];
    return null;
  }
  var timers = {}, inflight = {}, again = {};

  // Only what the server decides about — so a logo still being uploaded
  // (sent as a data: URL, returned as a link) does not count as a refusal.
  function shape(key, v) {
    if (key === 'fed-users') {
      // The prototype always appends its three demo accounts to the list it
      // saves. They belong to demo bodies the server never had, so only rows
      // that sit in one of OUR bodies are compared.
      var ids = {};
      try { (JSON.parse(ls.getItem('fed-orgs') || '[]') || []).forEach(function (o) { ids[o.id] = true; }); } catch (e) {}
      return JSON.stringify((Array.isArray(v) ? v : []).filter(function (u) { return u && ids[u.orgId]; })
        .map(function (u) { return String(u.email || '').toLowerCase(); }).sort());
    }
    if (key === 'fed-region') return JSON.stringify(v ? [v.country, v.tz, v.cur] : null);
    if (key === 'takeone_strings') {
      // Key order and save times are not decisions; the words are.
      var t = v && typeof v === 'object' ? v : {};
      return JSON.stringify(Object.keys(t).sort().map(function (k) { var r = t[k] || {}; return [k, String(r.ar || '').trim(), String(r.fr || '').trim()]; })
        .filter(function (x) { return x[1] || x[2]; }));
    }
    if (String(key).indexOf('fed-billing:') === 0) return JSON.stringify(v ? [v.status, (v.invoices || []).map(function (i) { return i.no + ':' + i.status; })] : null);
    if (!Array.isArray(v)) return JSON.stringify(v);
    return JSON.stringify(v.map(function (o) {
      return [o.id, o.name, o.parentId || null, (o.sports || []).slice().sort(), o.payer, o.status, o.logoShape || 'circle', !!o.logo];
    }).sort(function (a, b) { return a[0] < b[0] ? -1 : 1; }));
  }

  function send(key) {
    var r = route(key);
    if (!r || !r[0]) return;
    if (inflight[key]) { again[key] = true; return; }
    var sent;
    try { sent = JSON.parse(ls.getItem(key) || 'null'); } catch (e) { return; }
    var body = {}; body[r[1]] = sent;
    if (r[3]) body.account = r[3];
    inflight[key] = true;
    fetch(r[0], {
      method: 'POST',
      credentials: 'same-origin',
      headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest'},
      body: JSON.stringify(body)
    }).then(function (res) {
      return res.json().catch(function () { return null; }).then(function (j) { return {ok: res.ok, status: res.status, json: j}; });
    }).then(function (out) {
      inflight[key] = false;
      if (again[key]) { again[key] = false; return send(key); }
      if (out.status === 401 || out.status === 419) return window.location.reload();
      if (!out.ok || !out.json || !(r[2] in out.json)) return window.location.reload();
      var server = out.json[r[2]];
      put(key, server);
      window.dispatchEvent(new CustomEvent('fedstore-change', {detail: key}));
      // The page keeps some lists in its own state. If the server refused part
      // of a save, the only honest way back to the truth is to draw again.
      if (shape(key, server) !== shape(key, sent)) window.location.reload();
    }).catch(function () {
      inflight[key] = false;
      // Offline or the server is unreachable: try again shortly; the page's
      // own copy stands until then.
      clearTimeout(timers[key]);
      timers[key] = setTimeout(function () { send(key); }, 4000);
    });
  }

  // The Registrations tab's delete / clear-all writes the feed back without
  // the removed athletes. On the platform that is a WITHDRAWAL (managers
  // only, checked on the server), never a deletion of the person.
  function regsRemoved(value) {
    var before = [], after = [];
    try { before = JSON.parse(lastRegs || '[]') || []; after = JSON.parse(value || '[]') || []; } catch (e) { return; }
    var keep = {};
    after.forEach(function (sub) { (sub.athletes || []).forEach(function (a) { keep[sub.entryId + '|' + a.athleteId] = true; }); });
    var gone = [];
    before.forEach(function (sub) { (sub.athletes || []).forEach(function (a) { if (!keep[sub.entryId + '|' + a.athleteId]) gone.push({entry: sub.entryId, athlete: a.athleteId}); }); });
    lastRegs = value;
    if (gone.length && urls.regsWithdraw) jsonPost(urls.regsWithdraw, {athletes: gone}).then(function (j) {
      if (!j.__ok) return window.location.reload();
      if (j.regs) { put('takeone_club_regs', j.regs); lastRegs = JSON.stringify(j.regs); }
    });
  }
  var lastRegs = ls.getItem('takeone_club_regs');

  Storage.prototype.setItem = function (key, value) {
    rawSet.call(this, key, value);
    if (this === ls && !quiet && key === 'takeone_club_regs') regsRemoved(value);
    if (this === ls && !quiet && route(key)) {
      clearTimeout(timers[key]);
      timers[key] = setTimeout(function () { send(key); }, 250);
    }
  };
})();
