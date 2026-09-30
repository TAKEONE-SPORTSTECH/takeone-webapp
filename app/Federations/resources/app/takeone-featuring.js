/*
 * takeone-featuring.js — NOT part of the designed prototype.
 *
 * Paid featuring on the members' Events page (App\Events\Support\EventFeaturing):
 * a "Featured on TAKEONE" card inside the "Invite clubs" window, mounted where
 * FederationApp's 'invite-qr' rewrite leaves a [data-fed-feature] placeholder.
 *
 * The manager sees the platform's flat fee, uploads a picture or PDF of the
 * receipt, and then where the request stands: waiting · featured · not
 * approved (with the reason). The TAKEONE super-admin decides.
 *
 * Standalone: reads window.__TAKEONE_FED only. Every value it shows is set as
 * text, never as HTML. The server decides who may ask (FederationEvents::requestFeature).
 */
(function () {
  'use strict';
  var boot = window.__TAKEONE_FED || {};
  var urls = boot.urls || {};
  if (boot.mode !== 'staff' || !boot.canFeature || !urls.feature) return;
  var states = boot.featureStates || {};

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

  var BTN = 'height:32px;padding:0 14px;border-radius:9px;font-family:inherit;font-size:.74rem;font-weight:600;cursor:pointer;';

  function render(mount) {
    var id = eventId(mount);
    mount.textContent = '';
    if (!id) return;
    var ui = {busy: false, file: null, note: '', error: false};

    var card = el('div', 'margin-top:16px;padding:14px;border-radius:14px;background:#fbf8f0;border:1px solid #f0e3c2');
    var head = el('div', 'display:flex;align-items:flex-start;gap:12px');
    var star = el('div', 'width:40px;height:40px;border-radius:12px;flex:none;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#edcd85,#d0a854)');
    var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('viewBox', '0 0 24 24'); svg.setAttribute('width', '20'); svg.setAttribute('height', '20'); svg.setAttribute('aria-hidden', 'true');
    var path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
    path.setAttribute('d', 'M12 2.5l2.9 6.1 6.6.8-4.9 4.6 1.3 6.6L12 17.3 6.1 20.6l1.3-6.6L2.5 9.4l6.6-.8z');
    path.setAttribute('fill', '#1e2c4f');
    svg.appendChild(path); star.appendChild(svg);
    var txt = el('div', 'min-width:0;flex:1');
    var label = el('div', 'font-size:.7rem;font-weight:600;letter-spacing:.16em;text-transform:uppercase;color:#8a6d20;margin-bottom:4px', 'Featured on TAKEONE');
    var desc = el('div', 'font-size:.74rem;line-height:1.5;color:#6b7689');
    txt.appendChild(label); txt.appendChild(desc);
    head.appendChild(star); head.appendChild(txt);
    card.appendChild(head);

    var fee = el('div', 'display:none;margin-top:12px;font-size:.78rem;font-weight:600;color:#1e2c4f');
    card.appendChild(fee);

    var ask = el('div', 'display:none;margin-top:12px;gap:8px;flex-wrap:wrap;align-items:center');
    var input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/jpeg,image/png,image/webp,application/pdf';
    input.style.display = 'none';
    var pick = el('button', BTN + 'border:1px solid #d5dae3;background:#fff;color:#1e2c4f', 'Choose receipt');
    var fileName = el('span', 'font-size:.72rem;color:#6b7689;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap');
    var send = el('button', BTN + 'border:0;background:linear-gradient(100deg,#edcd85,#d0a854);color:#1e2c4f', 'Send for approval');
    pick.type = send.type = 'button';
    ask.appendChild(input); ask.appendChild(pick); ask.appendChild(fileName); ask.appendChild(send);
    card.appendChild(ask);

    var note = el('div', 'display:none;margin-top:10px;font-size:.74rem;font-weight:600');
    card.appendChild(note);
    mount.appendChild(card);

    function paint() {
      // An event saved after the page opened has no state yet: the fee decides what to offer.
      var s = states[id] || {status: 'none', fee: boot.featureFee || null, can_request: !!boot.featureFee};
      var text;
      if (s.featured) text = 'Featured. This event is the spotlight card on every member\'s Events page until it ends.';
      else if (s.status === 'pending') text = 'Receipt sent' + (s.paid ? ' (' + s.paid + ')' : '') + '. Waiting for the TAKEONE team to approve it.';
      else if (s.ended) text = 'This event has ended.';
      else if (s.status === 'rejected' || s.status === 'revoked') text = (s.status === 'rejected' ? 'Not approved' : 'Featuring was removed') + (s.reason ? ': ' + s.reason : '.') + (s.can_request ? ' You can send a new receipt.' : '');
      else if (!s.fee) text = 'Featuring is not open yet.';
      else text = 'Put this event in the spotlight on every TAKEONE member\'s Events page. Pay the fee, upload the receipt, and it is featured once approved — until the event ends.';
      desc.textContent = text;
      card.style.background = s.featured ? '#fdf1e0' : '#fbf8f0';
      card.style.borderColor = s.featured ? '#efc98a' : '#f0e3c2';

      fee.style.display = s.can_request && s.fee ? 'block' : 'none';
      fee.textContent = s.fee ? 'Fee: ' + s.fee.display + ' — one payment, for as long as the event runs.' : '';
      ask.style.display = s.can_request ? 'flex' : 'none';
      fileName.textContent = ui.file ? ui.file.name : 'Picture or PDF, up to 10 MB';
      send.disabled = pick.disabled = ui.busy;
      send.style.opacity = (!ui.file || ui.busy) ? '.5' : '1';
      send.textContent = ui.busy ? 'Sending…' : 'Send for approval';
      note.style.display = ui.note ? 'block' : 'none';
      note.textContent = ui.note;
      note.style.color = ui.error ? '#b4483c' : '#2f7a4f';
    }

    pick.addEventListener('click', function () { if (!ui.busy) input.click(); });
    input.addEventListener('change', function () {
      ui.file = input.files && input.files[0] ? input.files[0] : null;
      ui.note = '';
      paint();
    });
    send.addEventListener('click', function () {
      if (ui.busy || !ui.file) return;
      ui.busy = true; ui.note = ''; paint();
      var body = new FormData();
      body.append('event', id);
      body.append('proof', ui.file);
      fetch(urls.feature, {
        method: 'POST', credentials: 'same-origin',
        headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest'},
        body: body
      }).then(function (r) { return r.json().catch(function () { return {}; }); })
        .then(function (j) {
          ui.busy = false;
          ui.error = !j.success;
          ui.note = j.message || (j.errors ? String(Object.values(j.errors)[0]) : (j.success ? '' : 'The receipt could not be sent.'));
          if (j.feature) states[id] = j.feature;
          if (j.success) { ui.file = null; input.value = ''; }
          paint();
        })
        .catch(function () { ui.busy = false; ui.error = true; ui.note = 'No connection. Nothing was sent.'; paint(); });
    });
    paint();
  }

  // The invite window is drawn and re-drawn by the prototype's runtime: mount
  // into each fresh placeholder as it appears.
  function scan() {
    var list = document.querySelectorAll('[data-fed-feature]');
    for (var i = 0; i < list.length; i++) {
      var m = list[i], id = eventId(m);
      if (m.firstChild && m.__fedFeatureId === id) continue;
      m.__fedFeatureId = id;
      render(m);
    }
  }
  var start = function () {
    scan();
    new MutationObserver(scan).observe(document.body, {childList: true, subtree: true});
  };
  if (document.body) start(); else document.addEventListener('DOMContentLoaded', start);
})();
