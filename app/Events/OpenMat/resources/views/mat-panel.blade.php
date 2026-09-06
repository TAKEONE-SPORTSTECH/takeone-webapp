{{--
    "Next pair" — Open Mat's own panel, INSIDE the sport's scoring console.

    ── Why this is here at all ────────────────────────────────────────────────
    Every other event type knows its bouts in advance, so its operator taps them
    off a running order and never leaves the table. An open mat has no running
    order by definition, and the first cut made the operator navigate back to a
    console, re-find two people, and come back — for every pair, all evening.

    So the mat brings its floor TO the table. Everyone who is here, sorted by
    who has fought least, two taps to set the corners, one to start, and the
    page never changes. Somebody new walks up? Search them or type their name
    right here — at an open mat the next fighter was not on anybody's list a
    minute ago.

    ── House style, and why this styles ITSELF ────────────────────────────────
    This is included INSIDE a fixed broadcast document that does not extend a
    layout. There is no Alpine, no jQuery, no Tailwind and no design system on
    that page — so this is vanilla JS in the dark broadcast idiom. Do not bring
    design-system classes in here; they resolve to nothing on these documents.

    It also brings its OWN styles, scoped to `.om-*`, and borrows nothing from
    the host. That is not tidiness: the two consoles that take this panel are
    different documents with different vocabularies — Karate says
    `modal`/`mhead`/`mtitle`/`mclose`/`cbtn`, Taekwondo says
    `sheet`/`sheet-head`/`sheet-title`/`sheet-close`/`btn`, and their CSS
    custom properties differ too (Taekwondo's are oklch). A panel written
    against either one is broken on the other. The ONE thing each console
    supplies is the button that opens it, which lives in that console's control
    row and rightly wears that console's own class.

    It talks to the console through `window.MatConsole` (send / alert / state),
    the one seam the console exposes, and to its own package through the shared
    `me.events.action` endpoint like every other Open Mat write.
--}}
<style>
  /* Scoped to this panel, borrowing nothing from the console around it. */
  #omPanel { position:fixed; inset:0; z-index:60; background:rgba(0,0,0,.72);
             display:flex; align-items:center; justify-content:center; padding:34px;
             font-family:'Barlow Condensed', sans-serif; color:#e8eaf2; }
  #omPanel[hidden] { display:none !important; }
  .om-sheet { width:1240px; max-width:100%; height:960px; max-height:100%;
              background:#12141d; border:2px solid rgba(249,115,22,.55); border-radius:20px;
              padding:26px 30px; display:flex; flex-direction:column; gap:16px;
              box-shadow:0 30px 90px rgba(0,0,0,.8); }
  .om-head { display:flex; align-items:center; justify-content:space-between; gap:20px;
             border-bottom:1px solid #1f2230; padding-bottom:14px; }
  .om-title { font-family:'Anton', sans-serif; font-size:28px; letter-spacing:.1em;
              text-transform:uppercase; color:#F97316; }
  .om-hint { font-size:19px; color:#5c6175; letter-spacing:.04em; }
  .om-btn { font-family:'Barlow Condensed', sans-serif; font-weight:700; letter-spacing:.08em;
            text-transform:uppercase; border-radius:12px; cursor:pointer; background:transparent;
            color:#e8eaf2; border:1px solid #2a2e40; transition:filter .12s, transform .12s; }
  .om-btn:hover:not(:disabled) { filter:brightness(1.25); }
  .om-btn:active:not(:disabled) { transform:scale(.97); }
  .om-btn:disabled { opacity:.4; cursor:not-allowed; }
  .om-close { width:46px; height:46px; font-size:22px; padding:0; color:#8a8fa3; }
  .om-in { font-family:'Barlow Condensed', sans-serif; background:#0a0b10; color:#e8eaf2;
           border:1px solid #2a2e40; border-radius:12px; padding:0 18px; }
  .om-in:focus { outline:2px solid #F97316; outline-offset:2px; }
  .om-row { display:flex; align-items:center; gap:14px; border-radius:12px; padding:12px 16px;
            border:1px solid #1f2230; background:#0a0b10; }
  #omPanel ::-webkit-scrollbar { width:10px; }
  #omPanel ::-webkit-scrollbar-thumb { background:#2a2e40; border-radius:8px; }
</style>

<div id="omPanel" hidden>
  <div class="om-sheet">

    <div class="om-head">
      <div class="om-title">{{ __('event-open_mat::messages.panel_title') }}</div>
      {{-- Closed by this panel's OWN script: Karate's console closes dialogs
           through a delegated `[data-close]` handler and Taekwondo's wires each
           ✕ by hand, so depending on either would leave the panel unclosable in
           the other. --}}
      <button type="button" id="omClose" class="om-btn om-close">✕</button>
    </div>

    <div class="om-hint">{{ __('event-open_mat::messages.panel_hint') }}</div>

    {{-- ── The two corners being built ──────────────────────────────────── --}}
    <div style="display:grid;grid-template-columns:1fr 120px 1fr;align-items:center;gap:16px;">
      <div id="omRed" style="border:2px solid rgba(255,107,120,.45);border-radius:16px;padding:18px 20px;min-height:118px;
                             display:flex;align-items:center;gap:16px;background:rgba(179,18,31,.10);"></div>
      <div style="display:flex;flex-direction:column;align-items:center;gap:10px;">
        <span style="font-family:'Anton',sans-serif;font-size:34px;color:#5c6175;">VS</span>
        <button id="omSwap" class="om-btn" style="min-height:52px;font-size:18px;padding:0 16px;color:#e8eaf2;border:1px solid #2a2e40;background:transparent;">
          {{ __('event-open_mat::messages.panel_swap') }}
        </button>
      </div>
      <div id="omBlue" style="border:2px solid rgba(138,180,255,.45);border-radius:16px;padding:18px 20px;min-height:118px;
                              display:flex;align-items:center;gap:16px;background:rgba(13,85,184,.10);"></div>
    </div>

    <button id="omStart" class="om-btn" style="min-height:88px;font-size:34px;background:linear-gradient(120deg,#F97316,#EF4444);color:#fff;border:none;">
      {{ __('event-open_mat::messages.panel_start') }}
    </button>

    {{-- ── The floor ────────────────────────────────────────────────────── --}}
    <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;">
      <div style="font-size:22px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#7ae582;">
        {{ __('event-open_mat::messages.panel_floor') }}
      </div>
      <div style="font-size:17px;color:#5c6175;letter-spacing:.05em;">
        {{ __('event-open_mat::messages.panel_code') }}: <b style="color:#ffe135;letter-spacing:.25em;">{{ $omCode }}</b>
      </div>
    </div>

    <div id="omFloor" style="flex:1;overflow-y:auto;display:flex;flex-direction:column;gap:8px;min-height:150px;padding-right:12px;"></div>

    {{-- ── Add somebody, without leaving ────────────────────────────────── --}}
    <div style="border-top:1px solid #1f2230;padding-top:16px;display:flex;flex-direction:column;gap:12px;">
      <div style="display:flex;gap:12px;align-items:center;">
        <input id="omSearch" type="search" autocomplete="off" placeholder="{{ __('event-open_mat::messages.panel_add_search') }}"
               class="om-in" style="flex:1;min-height:64px;font-size:24px;">
        <button id="omAddMe" class="om-btn" style="min-height:64px;font-size:20px;padding:0 20px;color:#ffe135;border:1px solid #ffe135;background:transparent;">
          {{ __('event-open_mat::messages.panel_me') }}
        </button>
      </div>
      <div id="omResults" style="display:flex;flex-direction:column;gap:6px;max-height:210px;overflow-y:auto;"></div>
      <div style="display:flex;gap:12px;align-items:center;">
        <input id="omGuest" type="text" maxlength="60" placeholder="{{ __('event-open_mat::messages.panel_add_guest') }}"
               class="om-in" style="flex:1;min-height:64px;font-size:22px;">
        <button id="omAddGuest" class="om-btn" style="min-height:64px;font-size:20px;padding:0 22px;color:#e8eaf2;border:1px solid #2a2e40;background:transparent;">
          {{ __('event-open_mat::messages.panel_add_guest_btn') }}
        </button>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  'use strict';

  var FLOOR   = @json($omFloor);
  var CORNERS = @json($omCorners);
  var MAT     = @json($omCourt);
  var ME      = @json($omMe);
  var ACTION  = @json($omActionBase);
  var SEARCH  = @json($omSearchUrl);
  var STATE_U = @json($omStateUrl);

  var T = {
    empty:   @json(__('event-open_mat::messages.panel_floor_empty')),
    red:     @json(__('event-open_mat::messages.panel_red')),
    blue:    @json(__('event-open_mat::messages.panel_blue')),
    clear:   @json(__('event-open_mat::messages.panel_clear')),
    remove:  @json(__('event-open_mat::messages.panel_remove')),
    bouts:   @json(__('event-open_mat::messages.panel_bouts_n', ['n' => ':n'])),
    busy:    @json(__('event-open_mat::messages.panel_busy')),
    failed:  @json(__('events.action_unsupported')),
  };

  function el(id) { return document.getElementById(id); }
  function csrf() { return document.querySelector('meta[name=csrf-token]').content; }
  function say(msg, ok) {
    if (window.MatConsole && window.MatConsole.alert) window.MatConsole.alert(msg, ok);
  }

  var busy = false;

  /* Every write goes to the package's own shared action endpoint — the same
     door the phone console uses, with the same authorisation and throttle. */
  function act(action, payload) {
    if (busy) return Promise.resolve(null);
    busy = true;
    return fetch(ACTION + action, {
      method: 'POST',
      headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
      credentials: 'same-origin',
      body: JSON.stringify(Object.assign({ mat: MAT }, payload || {})),
    }).then(function (r) { return r.json().catch(function () { return {}; }); })
      .then(function (d) {
        if (!d.success) { say(d.message || T.failed); return null; }
        if (d.floor)   FLOOR = d.floor;
        if (d.corners) CORNERS = d.corners;
        paint();
        return d;
      })
      .catch(function () { say(T.failed); return null; })
      .finally(function () { busy = false; });
  }

  /* ── Painting ──────────────────────────────────────────────────────────── */

  function avatar(p, size) {
    var img = document.createElement('img');
    img.src = p.photo || p.fallback;
    img.alt = '';
    // 3:4 portrait, the house ratio for a face — never a square crop.
    img.style.cssText = 'width:' + (size * 0.75) + 'px;height:' + size + 'px;object-fit:cover;border-radius:10px;background:#12141d;flex:none;';
    return img;
  }

  /* A club's flag, or nothing.

     The country beside somebody at a mat is their CLUB's country and never their
     nationality — an athlete competes for the club that brought them — which is
     why it is resolved from the club row and stored beside it. Same source as
     every other board in the product (flagcdn), so a hall shows one kind of flag.

     Built rather than interpolated, like every other node on this panel: names
     and club names are typed by people, and this markup is one string
     concatenation away from being their problem instead of ours. */
  function flag(p, height) {
    if (!p || !p.country) return null;

    var img = document.createElement('img');
    img.src = 'https://flagcdn.com/w40/' + p.country + '.png';
    img.alt = '';
    img.style.cssText = 'height:' + height + 'px;border-radius:3px;flex:none;box-shadow:0 1px 3px rgba(0,0,0,.5);';
    return img;
  }

  function paintCorner(hostId, side) {
    var host = el(hostId);
    var c = CORNERS ? CORNERS[side] : null;
    var ink = side === 'aka' ? '#ff6b78' : '#8ab4ff';
    host.textContent = '';

    var tag = document.createElement('div');
    tag.style.cssText = 'font-size:15px;letter-spacing:.16em;color:' + ink + ';text-transform:uppercase;';
    tag.textContent = side === 'aka' ? T.red : T.blue;

    if (!c) {
      var col0 = document.createElement('div');
      var none = document.createElement('div');
      none.style.cssText = 'font-size:28px;color:#5c6175;margin-top:4px;';
      none.textContent = '—';
      col0.appendChild(tag); col0.appendChild(none);
      host.appendChild(col0);
      return;
    }

    host.appendChild(avatar(c, 84));

    var col = document.createElement('div');
    col.style.cssText = 'min-width:0;flex:1;';
    var nm = document.createElement('div');
    nm.style.cssText = 'font-size:32px;font-weight:700;color:' + ink + ';white-space:nowrap;overflow:hidden;text-overflow:ellipsis;';
    nm.textContent = c.name;
    col.appendChild(tag); col.appendChild(nm);

    // Who they are fighting for, under their name — the way a hall already
    // refers to everybody in it.
    if (c.club || c.country) {
      var line = document.createElement('div');
      line.style.cssText = 'display:flex;align-items:center;gap:9px;margin-top:3px;min-width:0;';

      var fl = flag(c, 17);
      if (fl) line.appendChild(fl);

      if (c.club) {
        var cl = document.createElement('div');
        cl.style.cssText = 'font-size:18px;color:#8b90a6;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;';
        cl.textContent = c.club;
        line.appendChild(cl);
      }

      col.appendChild(line);
    }

    host.appendChild(col);

    var clr = document.createElement('button');
    clr.className = 'om-btn';
    clr.style.cssText = 'min-height:46px;font-size:16px;padding:0 14px;color:#5c6175;border:1px solid #2a2e40;background:transparent;flex:none;';
    clr.textContent = T.clear;
    clr.onclick = function () { act('clear_corner', { side: side }); };
    host.appendChild(clr);
  }

  function paintFloor() {
    var host = el('omFloor');
    host.textContent = '';

    if (!FLOOR.length) {
      var empty = document.createElement('div');
      empty.style.cssText = 'font-size:21px;color:#5c6175;padding:16px 4px;';
      empty.textContent = T.empty;
      host.appendChild(empty);
      return;
    }

    FLOOR.forEach(function (p) {
      var here = p.corner && p.corner.indexOf(MAT + '/') === 0;
      var side = here ? p.corner.split('/')[1] : null;

      var row = document.createElement('div');
      row.style.cssText = 'display:flex;align-items:center;gap:14px;background:' + (here ? '#1f2230' : '#0a0b10') +
        ';border:1px solid ' + (side === 'aka' ? 'rgba(255,107,120,.5)' : side === 'ao' ? 'rgba(138,180,255,.5)' : '#1f2230') +
        ';border-radius:12px;padding:12px 16px;';

      row.appendChild(avatar(p, 60));

      var col = document.createElement('div');
      col.style.cssText = 'flex:1;min-width:0;';
      var nm = document.createElement('div');
      nm.style.cssText = 'font-size:27px;color:#e8eaf2;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;';
      nm.textContent = p.name;
      var sub = document.createElement('div');
      sub.style.cssText = 'display:flex;align-items:center;gap:8px;font-size:16px;color:#5c6175;letter-spacing:.04em;min-width:0;';

      var rowFlag = flag(p, 13);
      if (rowFlag) sub.appendChild(rowFlag);

      var facts = document.createElement('span');
      facts.style.cssText = 'white-space:nowrap;overflow:hidden;text-overflow:ellipsis;';
      // The club first: on a floor of twenty people it is what tells two
      // similar names apart, and the bout count is the tie-breaker rather than
      // the headline.
      facts.textContent = (p.club ? p.club + '  ·  ' : '') +
        T.bouts.replace(':n', p.bouts) +
        (p.record && p.record.fought ? '  ·  ' + p.record.won + '–' + p.record.lost : '');
      sub.appendChild(facts);

      col.appendChild(nm); col.appendChild(sub);
      row.appendChild(col);

      // The two taps that are the whole point.
      [['aka', T.red, 'rgba(255,107,120,.5)', '#ff6b78'],
       ['ao',  T.blue, 'rgba(138,180,255,.5)', '#8ab4ff']].forEach(function (s) {
        var b = document.createElement('button');
        b.className = 'om-btn';
        var on = side === s[0];
        b.style.cssText = 'min-height:56px;font-size:19px;padding:0 22px;flex:none;border:1px solid ' + s[2] +
          ';background:' + (on ? s[3] : 'transparent') + ';color:' + (on ? '#0a0b10' : s[3]) + ';';
        b.textContent = s[1];
        b.onclick = function () { act(on ? 'clear_corner' : 'assign_corner', { side: s[0], person_id: p.id }); };
        row.appendChild(b);
      });

      var rm = document.createElement('button');
      rm.className = 'om-btn';
      rm.style.cssText = 'min-height:56px;font-size:16px;padding:0 14px;flex:none;color:#5c6175;border:1px solid #2a2e40;background:transparent;';
      rm.textContent = T.remove;
      rm.onclick = function () { act('remove_person', { person_id: p.id }); };
      row.appendChild(rm);

      host.appendChild(row);
    });
  }

  function paint() { paintCorner('omRed', 'aka'); paintCorner('omBlue', 'ao'); paintFloor(); }

  /* ── Search, right here ────────────────────────────────────────────────── */

  var timer = null;
  el('omSearch').addEventListener('input', function () {
    clearTimeout(timer);
    var q = this.value.trim();
    if (q.length < 2) { el('omResults').textContent = ''; return; }
    timer = setTimeout(function () { runSearch(q); }, 320);
  });

  function runSearch(q) {
    fetch(SEARCH + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        var host = el('omResults');
        host.textContent = '';
        (d.people || []).forEach(function (person) {
          var b = document.createElement('button');
          b.style.cssText = 'display:flex;align-items:center;gap:12px;background:#0a0b10;border:1px solid #1f2230;' +
            'border-radius:10px;padding:10px 14px;cursor:pointer;text-align:left;color:#e8eaf2;font-family:inherit;';
          b.appendChild(avatar(person, 48));
          var col = document.createElement('div');
          col.style.cssText = 'flex:1;min-width:0;';
          var nm = document.createElement('div');
          nm.style.cssText = 'font-size:23px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;';
          nm.textContent = person.name + (person.is_me ? ' ·' : '');
          var cl = document.createElement('div');
          cl.style.cssText = 'font-size:15px;color:#5c6175;';
          cl.textContent = person.club || '';
          col.appendChild(nm); col.appendChild(cl);
          b.appendChild(col);
          b.onclick = function () {
            act('add_member', { user_id: person.id }).then(function (d2) {
              if (d2) { el('omSearch').value = ''; host.textContent = ''; }
            });
          };
          host.appendChild(b);
        });
      })
      .catch(function () {});
  }

  el('omAddMe').onclick = function () { if (ME) act('add_member', { user_id: ME.id }); };

  el('omAddGuest').onclick = function () {
    var name = el('omGuest').value.trim();
    if (!name) return;
    act('add_guest', { name: name }).then(function (d) { if (d) el('omGuest').value = ''; });
  };
  el('omGuest').addEventListener('keydown', function (e) {
    if (e.key === 'Enter') { e.preventDefault(); el('omAddGuest').click(); }
  });

  el('omSwap').onclick = function () { act('swap_corners'); };

  /* Opening and closing is this panel's own business — see the ✕ above. */
  function close() { el('omPanel').hidden = true; }
  el('omClose').onclick = close;
  el('omPanel').onclick = function (e) { if (e.target === el('omPanel')) close(); };
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && !el('omPanel').hidden) close();
  });

  /* ── Start, without going anywhere ─────────────────────────────────────── */

  el('omStart').onclick = function () {
    var st = window.MatConsole && window.MatConsole.state ? window.MatConsole.state() : null;

    // A bout already on the mat is not replaced from here: the operator has to
    // finish it or clear it, exactly as they would between two draw bouts.
    if (st && st.matchId) { say(T.busy); return; }

    act('start_bout').then(function (d) {
      if (!d || !d.match_id) return;
      // The bout exists; now hand it to the console the operator is already
      // looking at. Its own `send` repaints from the reply, so the mat is
      // loaded and introduced without the page changing at all.
      if (window.MatConsole && window.MatConsole.send) {
        window.MatConsole.send('load', { match_id: d.match_id }).then(close);
      }
    });
  };

  /* Another console (or somebody taking a corner on their own phone with the
     join code) moved the floor. Re-read rather than guess: what each holder of
     this mat may see differs. */
  window.addEventListener('realtime:events', function (e) {
    if (!e.detail || e.detail.action !== 'open_mat') return;
    fetch(STATE_U, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        var om = (d && d.open_mat) || null;
        if (!om) return;
        FLOOR = om.floor || FLOOR;
        CORNERS = (om.corners && om.corners[MAT]) || CORNERS;
        paint();
      })
      .catch(function () {});
  });

  paint();
})();
</script>
