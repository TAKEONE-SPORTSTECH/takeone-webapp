/*
 * takeone-test-mode.js — NOT part of the designed prototype.
 *
 * Test mode for one event (App\Federations\Support\FederationTestMode).
 *
 *  - Staff dashboard: a "Test mode" card inside the "Invite clubs" window
 *    (mounted where FederationApp's 'invite-test-mode' rewrite leaves a
 *    [data-fed-test] placeholder). On → everything entered for the event from
 *    then on is test data. Off → asks once, then the server backs up the
 *    database and deletes all of it; the page redraws from the server.
 *  - Invitation page: a banner, so a real club never mistakes a test for the
 *    real thing.
 *
 * Standalone: reads window.__TAKEONE_FED only. Every value it shows is set as
 * text, never as HTML. The server decides who may switch (FederationEvents).
 */
(function () {
  'use strict';
  var boot = window.__TAKEONE_FED || {};
  var urls = boot.urls || {};
  var on = {};
  (boot.testEvents || []).forEach(function (id) { on[id] = true; });

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

  // ------------------------------------------------------ invitation page
  if (boot.mode === 'public') {
    if (!boot.testMode) return;
    var show = function () {
      if (document.getElementById('fed-test-banner')) return;
      var b = el('div', 'position:fixed;top:0;left:0;right:0;z-index:2147483000;display:flex;align-items:center;justify-content:center;gap:10px;padding:8px 16px;background:#a8620f;color:#fff;font:600 13px/1.4 Poppins,system-ui,sans-serif;text-align:center;box-shadow:0 2px 12px rgba(0,0,0,.2)');
      b.id = 'fed-test-banner';
      b.setAttribute('role', 'status');
      b.appendChild(el('span', 'padding:2px 8px;border-radius:999px;background:rgba(255,255,255,.22);font-size:11px;letter-spacing:.12em', 'TEST MODE'));
      b.appendChild(el('span', '', 'This event is being tested. Anything you enter here is not a real registration and will be deleted.'));
      document.body.appendChild(b);
      document.body.style.paddingTop = b.offsetHeight + 'px';
    };
    if (document.body) show(); else document.addEventListener('DOMContentLoaded', show);
    return;
  }

  // ------------------------------------------------------ staff dashboard
  if (!boot.canTestMode || !urls.testMode) return;

  function eventId(mount) {
    var id = mount.getAttribute('data-event') || '';
    if (/^[0-9a-f-]{36}$/i.test(id)) return id.toLowerCase();
    var m = location.pathname.match(/\/events\/([0-9a-f-]{36})/i);
    return m ? m[1].toLowerCase() : null;
  }

  function render(mount) {
    var id = eventId(mount);
    mount.textContent = '';
    if (!id) return;
    var state = {busy: false, confirming: false, note: ''};

    var card = el('div', '');
    var head = el('div', 'display:flex;align-items:center;gap:12px');
    var txt = el('div', 'min-width:0;flex:1');
    var label = el('div', 'font-size:.7rem;font-weight:600;letter-spacing:.16em;text-transform:uppercase;margin-bottom:4px', 'Test mode');
    var desc = el('div', 'font-size:.74rem;line-height:1.5');
    txt.appendChild(label); txt.appendChild(desc);
    var sw = el('button', 'position:relative;width:46px;height:26px;border-radius:999px;border:0;cursor:pointer;flex:none;transition:background .2s');
    sw.type = 'button';
    sw.setAttribute('role', 'switch');
    var knob = el('span', 'position:absolute;top:3px;width:20px;height:20px;border-radius:50%;background:#fff;box-shadow:0 1px 4px rgba(0,0,0,.25);transition:left .2s');
    sw.appendChild(knob);
    head.appendChild(txt); head.appendChild(sw);
    card.appendChild(head);

    var confirmRow = el('div', 'display:none;margin-top:12px;padding-top:12px;border-top:1px solid rgba(168,98,15,.25)');
    confirmRow.appendChild(el('div', 'font-size:.78rem;font-weight:600;color:#7a4608;margin-bottom:8px', 'Switch off and delete every club, athlete, official and entry made while testing?'));
    var btns = el('div', 'display:flex;gap:8px;flex-wrap:wrap');
    var del = el('button', 'height:32px;padding:0 14px;border-radius:9px;border:0;background:#b4483c;color:#fff;font-family:inherit;font-size:.74rem;font-weight:600;cursor:pointer', 'Delete test data');
    var keep = el('button', 'height:32px;padding:0 14px;border-radius:9px;border:1px solid #d5dae3;background:#fff;color:#1e2c4f;font-family:inherit;font-size:.74rem;font-weight:600;cursor:pointer', 'Keep testing');
    del.type = keep.type = 'button';
    btns.appendChild(del); btns.appendChild(keep);
    confirmRow.appendChild(btns);
    card.appendChild(confirmRow);
    var note = el('div', 'display:none;margin-top:10px;font-size:.74rem;font-weight:600');
    card.appendChild(note);
    mount.appendChild(card);

    function paint() {
      var isOn = !!on[id];
      card.style.cssText = 'margin-top:16px;padding:14px;border-radius:14px;transition:background .2s,border-color .2s;' +
        (isOn ? 'background:#fdf1e0;border:1px solid #efc98a' : 'background:#f7f8fb;border:1px solid #e3e7ee');
      label.style.color = isOn ? '#a8620f' : '#8a6d20';
      desc.style.color = isOn ? '#7a4608' : '#6b7689';
      desc.textContent = isOn
        ? 'On. Everything entered for this event now, through the link or here, is test data. Switching off deletes all of it.'
        : 'Try the whole registration for real: clubs, athletes, officials, approvals. Nothing is kept when you switch it off.';
      sw.style.background = isOn ? '#a8620f' : '#c9ced8';
      sw.style.opacity = state.busy ? '.5' : '1';
      sw.disabled = state.busy;
      knob.style.left = isOn ? '23px' : '3px';
      sw.setAttribute('aria-checked', isOn ? 'true' : 'false');
      sw.setAttribute('aria-label', isOn ? 'Switch test mode off' : 'Switch test mode on');
      confirmRow.style.display = state.confirming ? 'block' : 'none';
      del.disabled = keep.disabled = state.busy;
      del.textContent = state.busy ? 'Deleting…' : 'Delete test data';
      note.style.display = state.note ? 'block' : 'none';
      note.textContent = state.note;
      note.style.color = state.error ? '#b4483c' : '#2f7a4f';
    }

    function send(want) {
      state.busy = true; state.note = ''; paint();
      fetch(urls.testMode, {
        method: 'POST', credentials: 'same-origin',
        headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest'},
        body: JSON.stringify({event: id, on: want})
      }).then(function (r) { return r.json().catch(function () { return {}; }); })
        .then(function (j) {
          state.busy = false; state.confirming = false;
          state.error = !j.success;
          state.note = j.message || (j.success ? '' : 'Test mode could not be changed.');
          if (j.success && j.event) { if (j.event.testMode) on[id] = true; else delete on[id]; }
          paint();
          if (j.success && !want && window.TakeOneFed && window.TakeOneFed.refresh) window.TakeOneFed.refresh();
        })
        .catch(function () { state.busy = false; state.error = true; state.note = 'No connection. Nothing was changed.'; paint(); });
    }

    sw.addEventListener('click', function () {
      if (state.busy) return;
      if (on[id]) { state.confirming = !state.confirming; state.note = ''; paint(); } else send(true);
    });
    keep.addEventListener('click', function () { state.confirming = false; paint(); });
    del.addEventListener('click', function () { if (!state.busy) send(false); });
    paint();
  }

  // The invite window is drawn and re-drawn by the prototype's runtime: mount
  // into each fresh placeholder as it appears.
  function scan() {
    // A placeholder the runtime emptied or re-pointed at another event is drawn again.
    var list = document.querySelectorAll('[data-fed-test]');
    for (var i = 0; i < list.length; i++) {
      var m = list[i], id = eventId(m);
      if (m.firstChild && m.__fedTestId === id) continue;
      m.__fedTestId = id;
      render(m);
    }
  }
  var start = function () {
    scan();
    new MutationObserver(scan).observe(document.body, {childList: true, subtree: true});
  };
  if (document.body) start(); else document.addEventListener('DOMContentLoaded', start);
})();
