/*
 * takeone-rulebook.js — NOT part of the designed prototype.
 *
 * The rule book an event runs under — its category system (age groups ×
 * weight classes: WKF, Shotokan, Kyokushin, the federation's own …) and its
 * belt system — as a card inside the "Invite clubs" window, mounted where
 * FederationApp's 'invite-qr' rewrite leaves a [data-fed-rulebook] placeholder.
 *
 * The same choice as the event console's rule book (<x-event-rule-book>):
 * once the event runs as a platform event, changing it RE-SORTS every athlete
 * already entered, and it is refused once a draw is cut. The federation's own
 * systems are made in the console ("Make your own") and appear here too.
 *
 * Standalone: reads window.__TAKEONE_FED only; every value is set as text.
 * The server decides who may change it (FederationEvents::setRuleBook).
 */
(function () {
  'use strict';
  var boot = window.__TAKEONE_FED || {};
  var urls = boot.urls || {};
  if (boot.mode !== 'staff' || !boot.canRuleBook || !urls.ruleBook) return;
  var books = boot.ruleBooks || {};

  function el(tag, css, text) {
    var n = document.createElement(tag);
    if (css) n.style.cssText = css;
    if (text != null) n.textContent = text;
    return n;
  }
  function csrf() {
    var m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.getAttribute('content') : (boot.csrf || '');
  }
  function eventId(mount) {
    var id = mount.getAttribute('data-event') || '';
    return /^[0-9a-f-]{36}$/i.test(id) ? id.toLowerCase() : null;
  }

  var CHIP = 'height:32px;padding:0 13px;border-radius:999px;font-family:inherit;font-size:.74rem;font-weight:600;cursor:pointer;transition:all .15s;';

  function render(mount) {
    var id = eventId(mount);
    mount.textContent = '';
    if (!id) return;
    var book = books[id];
    // An event saved after the page opened: its choices arrive with the next visit.
    if (!book || (!book.categories.length && !book.belts.length)) return;
    var ui = {busy: false, note: '', error: false, category: book.category || null, belt: book.belt || null};

    var card = el('div', 'margin-top:16px;padding:14px;border-radius:14px;background:#f5f7fb;border:1px solid #dfe5ef');
    var head = el('div', 'display:flex;align-items:flex-start;gap:12px');
    var tile = el('div', 'width:40px;height:40px;border-radius:12px;flex:none;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#1e2c4f,#3b4f7e)');
    var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('viewBox', '0 0 24 24'); svg.setAttribute('width', '20'); svg.setAttribute('height', '20'); svg.setAttribute('aria-hidden', 'true');
    var path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
    path.setAttribute('d', 'M5 3h11l3 3v15H5zM8 8h8M8 12h8M8 16h5');
    path.setAttribute('fill', 'none'); path.setAttribute('stroke', '#edcd85'); path.setAttribute('stroke-width', '1.8'); path.setAttribute('stroke-linecap', 'round');
    svg.appendChild(path); tile.appendChild(svg);
    var txt = el('div', 'min-width:0;flex:1');
    txt.appendChild(el('div', 'font-size:.7rem;font-weight:600;letter-spacing:.16em;text-transform:uppercase;color:#3b4f7e;margin-bottom:4px', 'Rule book'));
    txt.appendChild(el('div', 'font-size:.74rem;line-height:1.5;color:#6b7689',
      'The weight classes and the belt ranking this event is run under. Changing it re-sorts every athlete already entered — until the draw is made.'));
    head.appendChild(tile); head.appendChild(txt);
    card.appendChild(head);

    function group(title, list, keyName) {
      if (!list.length) return null;
      var wrap = el('div', 'margin-top:12px');
      wrap.appendChild(el('div', 'font-size:.68rem;font-weight:700;color:#1e2c4f;margin-bottom:6px', title));
      var row = el('div', 'display:flex;flex-wrap:wrap;gap:6px');
      var buttons = [];
      list.forEach(function (opt, i) {
        var b = el('button', CHIP, opt.label + (opt.custom ? ' · own' : ''));
        b.type = 'button';
        b.setAttribute('role', 'radio');
        // The first is the sport's own default — what "nothing chosen" means.
        b.__key = i === 0 ? null : opt.key;
        b.__alt = opt.key;
        b.addEventListener('click', function () {
          if (ui.busy) return;
          save(keyName, b.__key);
        });
        buttons.push(b); row.appendChild(b);
      });
      wrap.appendChild(row);
      wrap.__paint = function () {
        buttons.forEach(function (b) {
          var on = ui[keyName] === b.__key || (ui[keyName] !== null && ui[keyName] === b.__alt);
          b.setAttribute('aria-checked', on ? 'true' : 'false');
          b.style.border = on ? '1px solid #1e2c4f' : '1px solid #d5dae3';
          b.style.background = on ? '#1e2c4f' : '#fff';
          b.style.color = on ? '#fff' : '#1e2c4f';
          b.disabled = ui.busy;
          b.style.opacity = ui.busy ? '.6' : '1';
        });
      };
      card.appendChild(wrap);
      return wrap;
    }
    var cats = group('Category system', book.categories, 'category');
    var belts = group('Belt system', book.belts, 'belt');

    var note = el('div', 'display:none;margin-top:10px;font-size:.74rem;font-weight:600');
    card.appendChild(note);
    mount.appendChild(card);

    function paint() {
      if (cats) cats.__paint();
      if (belts) belts.__paint();
      note.style.display = ui.note ? 'block' : 'none';
      note.textContent = ui.note;
      note.style.color = ui.error ? '#b4483c' : '#2f7a4f';
    }

    function save(which, value) {
      var next = {category: ui.category, belt: ui.belt};
      next[which] = value;
      if (next.category === ui.category && next.belt === ui.belt) return;
      ui.busy = true; ui.note = ''; paint();
      fetch(urls.ruleBook, {
        method: 'POST', credentials: 'same-origin',
        headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest'},
        body: JSON.stringify({event: id, category: next.category, belt: next.belt})
      }).then(function (r) { return r.json().catch(function () { return {}; }); })
        .then(function (j) {
          ui.busy = false;
          ui.error = !j.success;
          ui.note = j.message || (j.errors ? String(Object.values(j.errors)[0]) : (j.success ? '' : 'The rule book could not be saved.'));
          if (j.success && j.rulebook) {
            ui.category = j.rulebook.category; ui.belt = j.rulebook.belt;
            book.category = ui.category; book.belt = ui.belt;
          }
          paint();
        })
        .catch(function () { ui.busy = false; ui.error = true; ui.note = 'No connection. Nothing was changed.'; paint(); });
    }
    paint();
  }

  // The invite window is drawn and re-drawn by the prototype's runtime: mount
  // into each fresh placeholder as it appears.
  function scan() {
    var list = document.querySelectorAll('[data-fed-rulebook]');
    for (var i = 0; i < list.length; i++) {
      var m = list[i], id = eventId(m);
      if (m.firstChild && m.__fedRuleBookId === id) continue;
      m.__fedRuleBookId = id;
      render(m);
    }
  }
  var start = function () {
    scan();
    new MutationObserver(scan).observe(document.body, {childList: true, subtree: true});
  };
  if (document.body) start(); else document.addEventListener('DOMContentLoaded', start);
})();
