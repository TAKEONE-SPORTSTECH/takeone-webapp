{{--
    The script half of shared/corners-panel — standalone: its own helpers, its
    own CSRF lookup, its own error bar (or the console's window.alertBar when
    there is one). Saving reloads the page: every surface draws its corners
    server-side, and the mat's live link tells every other screen and console
    to do the same.

    Inputs: $look, $cornersUrl, $cornerLookUrl, $redRight, $sportRedRight, $court
--}}
<script>
(function () {
  function $(id) { return document.getElementById(id); }
  function token() { var m = document.querySelector('meta[name=csrf-token]'); return m ? m.content : ''; }
  var FAILED = @json(__('scoreboard::corners.failed'));
  function fail(msg) {
    if (typeof window.alertBar === 'function') return window.alertBar(msg);
    var b = document.createElement('div');
    b.style.cssText = 'position:fixed;top:0;left:0;right:0;z-index:99;background:#ff3b47;color:#fff;font-size:22px;font-weight:700;letter-spacing:.08em;text-align:center;padding:14px;';
    b.textContent = msg;
    document.body.appendChild(b);
    setTimeout(function () { b.remove(); }, 4000);
  }
  function post(url, body) {
    return fetch(url, {
      method: 'POST',
      headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token() },
      body: JSON.stringify(body),
    }).then(function (r) { return r.json().catch(function () { return {}; }); })
      .then(function (d) { if (!d.success) throw new Error(d.message || FAILED); return d; });
  }

  /* ── Which side red stands on, at this mat ───────────────────────────── */
  var swap = $('cornerSwap');
  if (swap) {
    var redRight = @json((bool) ($redRight ?? false));
    var sportRedRight = @json((bool) ($sportRedRight ?? false));
    var box = swap.querySelector('.rbox');
    var on = redRight !== sportRedRight;
    box.style.background = on ? '#ffe135' : '#1f2230';
    box.style.border = '1px solid ' + (on ? 'transparent' : '#2a2e40');
    box.textContent = on ? '✓' : '';
    swap.onclick = function () {
      if (swap.disabled) return;
      swap.disabled = true;
      post(@json($cornersUrl ?? null), { mat: @json($court ?? null), red_right: !redRight })
        .then(function () { window.location.reload(); })
        .catch(function (e) { swap.disabled = false; fail(e.message); });
    };
  }

  /* ── Red's side, screen by screen (corners-panel $perScreen) ──────────── */
  // Each row starts at the side saved for that screen; a tap only drafts it.
  // saveSides() sends just the screens whose draft differs — as a flip, which
  // is what the endpoint takes — and resolves with nothing to do otherwise.
  var sides = $('cornerScreens');
  var saveSides = function () { return Promise.resolve(null); };
  if (sides) {
    var rows = Array.prototype.slice.call(sides.querySelectorAll('.sideRow'));
    var draft = {};
    rows.forEach(function (row) { draft[row.getAttribute('data-screen')] = row.getAttribute('data-now'); });
    var paintSides = function () {
      rows.forEach(function (row) {
        var scr = row.getAttribute('data-screen'), now = row.getAttribute('data-now');
        Array.prototype.forEach.call(row.querySelectorAll('.sideBtn'), function (b) {
          var on = b.getAttribute('data-side') === draft[scr];
          b.style.background = on ? '#ffe135' : 'transparent';
          b.style.color = on ? '#000' : '#a7abbe';
          b.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
        row.querySelector('.sideChanged').hidden = draft[scr] === now;
      });
    };
    rows.forEach(function (row) {
      Array.prototype.forEach.call(row.querySelectorAll('.sideBtn'), function (b) {
        b.onclick = function () { draft[row.getAttribute('data-screen')] = b.getAttribute('data-side'); paintSides(); };
      });
    });
    paintSides();
    saveSides = function () {
      var changed = rows.filter(function (row) { var s = row.getAttribute('data-screen'); return draft[s] !== row.getAttribute('data-now'); })
        .map(function (row) { return row.getAttribute('data-screen'); });
      return changed.length ? post(@json($cornersUrl ?? null), { mat: @json($court ?? null), screens: changed }) : Promise.resolve(null);
    };
    var sideSave = $('sidesSave');
    sideSave.onclick = function () {
      if (sideSave.disabled) return;
      sideSave.disabled = true;
      saveSides().then(function () { window.location.reload(); })
        .catch(function (e) { sideSave.disabled = false; fail(e.message); });
    };
  }

  /* ── Names and colours, for the event ─────────────────────────────────── */
  var host = $('cornerLook');
  if (!host) return;
  var DEFAULTS = @json(collect($look ?? [])->map(fn ($c) => $c['defaultColour'])->all());
  var LABELS = @json(collect($look ?? [])->map(fn ($c) => $c['defaultLabel'])->all());
  var HEX = /^#[0-9a-f]{6}$/i;

  function lum(hex) {
    var c = [1, 3, 5].map(function (i) {
      var v = parseInt(hex.substr(i, 2), 16) / 255;
      return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
    });
    return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2];
  }

  function paintRow(row) {
    var side = row.getAttribute('data-side');
    var hex = row.querySelector('.lookHex').value.trim();
    var colour = HEX.test(hex) ? hex : DEFAULTS[side];
    var chip = row.querySelector('.lookChip');
    chip.textContent = (row.querySelector('.lookLabel').value.trim() || LABELS[side]).toUpperCase();
    chip.style.background = colour;
    // Same rule as the server (CornerLook): dark ink on a light colour.
    chip.style.color = lum(colour) > 0.45 ? '#14161c' : '#ffffff';
    Array.prototype.forEach.call(row.querySelectorAll('.lookSwatch'), function (b) {
      var mine = (b.getAttribute('data-colour') || '') === (HEX.test(hex) ? hex.toLowerCase() : '');
      b.style.outline = mine ? '3px solid #ffe135' : 'none';
      b.style.outlineOffset = '2px';
      b.style.border = '1px solid rgba(255,255,255,.25)';
    });
  }

  Array.prototype.forEach.call(host.querySelectorAll('.lookRow'), function (row) {
    row.querySelector('.lookLabel').oninput = function () { paintRow(row); };
    row.querySelector('.lookHex').oninput = function () { paintRow(row); };
    Array.prototype.forEach.call(row.querySelectorAll('.lookSwatch'), function (b) {
      b.onclick = function () { row.querySelector('.lookHex').value = b.getAttribute('data-colour') || ''; paintRow(row); };
    });
    paintRow(row);
  });

  var save = $('lookSave');
  save.onclick = function () {
    if (save.disabled) return;
    var body = {}, bad = false;
    Array.prototype.forEach.call(host.querySelectorAll('.lookRow'), function (row) {
      var hex = row.querySelector('.lookHex').value.trim();
      if (hex && !HEX.test(hex)) bad = true;
      body[row.getAttribute('data-side')] = { label: row.querySelector('.lookLabel').value.trim() || null, colour: hex || null };
    });
    if (bad) { fail(@json(__('scoreboard::corners.look_bad_hex'))); return; }
    save.disabled = true;
    saveSides().then(function () { return post(@json($cornerLookUrl ?? null), body); })
      .then(function () { window.location.reload(); })
      .catch(function (e) { save.disabled = false; fail(e.message); });
  };
})();
</script>
