{{--
    The Karate scoring console's CUSTOM SCORING script — the mat's own scoring
    buttons (Styles\CustomButtons) and foul ladder (Styles\CustomPenalties):
    painting the buttons on the console and the Settings → Points / Fouls
    editors. ONE copy, included INSIDE each Karate console's script (it uses the
    console's el(), STATE, send(), LOOK and penSteps()). Lifted from the Combat
    console on 2026-11-01.

      $defaultButtons  the style's own buttons ([] = the built-in column)
      $seedButtons     what the Points editor starts from (default: $defaultButtons)
--}}
  /* ── Custom scoring buttons (Styles\CustomButtons) ─────────────────────
     The mat's own buttons replace the built-in column when it has any. Each
     takes an equal share of the column's height, so two fill it as two tall
     buttons and eight as eight short ones; the type shrinks with them. The
     column is rebuilt only when the list changes, never on a clock tick. */
  var BTN_TXT = {!! json_encode([
    'correct' => __('scoreboard::karate_messages.ctl_btn_correct'),
    'win' => __('scoreboard::karate_messages.ctl_btn_effect_win'),
    'add' => __('scoreboard::karate_messages.ctl_btn_effect_add'),
    'win_after' => __('scoreboard::karate_messages.ctl_btn_effect_win_after'),
    'label' => __('scoreboard::karate_messages.ctl_btn_label'),
    'value' => __('scoreboard::karate_messages.ctl_btn_value'),
    'times' => __('scoreboard::karate_messages.ctl_btn_times'),
    'wins' => __('scoreboard::karate_messages.ctl_btn_wins'),
    'saved' => __('scoreboard::karate_messages.ctl_btn_saved'),
    'empty' => __('scoreboard::karate_messages.ctl_btn_empty'),
  ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!};
  var BTN_MAX = 8;
  // The style's own buttons — shown until the mat has its own. None (a WKF style)
  // keeps the built-in Yuko / Waza-ari / Ippon column, scored through the engine's
  // own `point`, so senshu works exactly as before.
  var CB_DEFAULT_BTNS = @json($defaultButtons ?? []);
  // What Settings → Points starts from when the mat has none of its own.
  var CB_SEED_BTNS = @json($seedButtons ?? ($defaultButtons ?? []));
  var btnSig = { aka: null, ao: null };

  function paintButtons(side) {
    // The mat's own buttons, else the style's own (none: the built-in column).
    var list = Array.isArray(STATE.buttons) && STATE.buttons.length ? STATE.buttons : CB_DEFAULT_BTNS;
    var std = el('std-' + side), cus = el('cus-' + side);
    var sig = JSON.stringify(list);
    if (btnSig[side] === sig) return;
    btnSig[side] = sig;

    std.style.display = list.length ? 'none' : 'flex';
    cus.hidden = !list.length;
    cus.style.display = list.length ? 'flex' : 'none';
    cus.textContent = '';
    if (!list.length) return;

    var colour = cus.getAttribute('data-colour');
    var count = list.length + 1;
    var size = count <= 4 ? 32 : count <= 6 ? 27 : 23;

    list.forEach(function (b) {
      var btn = document.createElement('button');
      btn.className = 'sbtn bout';
      // SHIRO's buttons are white, so they take dark ink.
      btn.style.cssText = 'background:' + colour + ';color:' + ((LOOK[side] || {}).ink || '#fff') + ';font-size:' + size + 'px;min-height:0;padding:6px 0;display:flex;align-items:center;justify-content:center;gap:12px;';
      btn.dataset.cmd = 'button';
      btn.dataset.side = side;
      btn.dataset.button = b.id;
      var name = document.createElement('span');
      name.textContent = b.label;
      btn.appendChild(name);
      if (b.value > 0) {
        var v = document.createElement('span');
        v.textContent = '+' + b.value;
        v.style.cssText = 'font-size:.72em;opacity:.8;';
        btn.appendChild(v);
      }
      if (b.effect !== 'add') {
        var w = document.createElement('span');
        w.textContent = b.effect === 'win' ? '★' : '★×' + b.times;
        w.title = b.effect === 'win' ? BTN_TXT.wins : BTN_TXT.wins + ' ×' + b.times;
        w.style.cssText = 'font-size:.6em;color:' + ((LOOK[side] || {}).light ? '#9a6b00' : '#ffe135') + ';';
        btn.appendChild(w);
      }
      cus.appendChild(btn);
    });

    var undo = document.createElement('button');
    undo.className = 'sbtn bout';
    undo.style.cssText = 'background:var(--fill);color:var(--text);font-size:' + Math.min(30, size) + 'px;min-height:0;padding:6px 0;';
    undo.dataset.cmd = 'button_undo';
    undo.dataset.side = side;
    undo.textContent = BTN_TXT.correct;
    cus.appendChild(undo);
  }

  /* The editor, in Settings → Points. Works on a copy; nothing reaches
     the mat until Save. */
  var btnDraft = [];
  var FIELD = 'font-size:20px;background:var(--fill);color:var(--text);border:1px solid var(--line-2);border-radius:10px;padding:10px 12px;min-width:0;';

  function btnDraftFromState() {
    btnDraft = (Array.isArray(STATE.buttons) && STATE.buttons.length ? STATE.buttons : CB_SEED_BTNS).map(function (b) { return Object.assign({}, b); });
  }

  function paintBtnRows() {
    var host = el('btnRows');
    host.textContent = '';
    if (!btnDraft.length) {
      var e = document.createElement('div');
      e.textContent = BTN_TXT.empty;
      e.style.cssText = 'font-size:18px;color:var(--muted);background:var(--inset);border:1px dashed var(--line-2);border-radius:12px;padding:18px;text-align:center;';
      host.appendChild(e);
    }
    btnDraft.forEach(function (b, i) {
      var row = document.createElement('div');
      row.style.cssText = 'display:grid;grid-template-columns:minmax(0,2fr) 90px minmax(0,3fr) auto;gap:10px;align-items:center;background:var(--inset);border:1px solid var(--line);border-radius:12px;padding:10px 12px;';

      var label = document.createElement('input');
      label.type = 'text'; label.maxLength = 24; label.value = b.label || ''; label.placeholder = BTN_TXT.label;
      label.style.cssText = FIELD;
      label.oninput = function () { b.label = this.value; };

      var value = document.createElement('input');
      value.type = 'number'; value.min = 0; value.max = 20; value.step = 0.5; value.value = b.value; value.title = BTN_TXT.value;
      value.style.cssText = FIELD + "font-family:'Anton',sans-serif;text-align:center;color:var(--gold);";
      // Halves allowed (a Kyokushin waza-ari is 0.5); anything else rounds to the nearest half.
      value.onchange = function () { var v = parseFloat(String(this.value).replace(',', '.')); b.value = isNaN(v) ? 0 : Math.max(0, Math.min(20, Math.round(v * 2) / 2)); this.value = b.value; };

      // The effect as three cells, not a native select (Design Rule #4).
      var eff = document.createElement('div');
      eff.style.cssText = 'display:flex;gap:6px;align-items:center;min-width:0;';
      ['add', 'win', 'win_after'].forEach(function (k) {
        var c = document.createElement('button');
        c.type = 'button';
        c.textContent = BTN_TXT[k];
        var on = b.effect === k;
        c.style.cssText = 'flex:1;font-size:15px;font-weight:700;letter-spacing:.04em;border-radius:9px;padding:10px 4px;cursor:pointer;white-space:nowrap;' +
          (on ? 'background:#ffe135;color:#000;border:1px solid transparent;' : 'background:#12141d;color:#9aa0b4;border:1px solid #2a2e40;');
        c.onclick = function () { b.effect = k; if (k === 'win_after' && !b.times) b.times = 2; paintBtnRows(); };
        eff.appendChild(c);
      });
      if (b.effect === 'win_after') {
        var times = document.createElement('input');
        times.type = 'number'; times.min = 2; times.max = 9; times.value = b.times || 2; times.title = BTN_TXT.times;
        times.style.cssText = FIELD + "width:64px;flex:0 0 auto;font-family:'Anton',sans-serif;text-align:center;color:var(--gold);";
        times.onchange = function () { var v = parseInt(this.value, 10); b.times = isNaN(v) ? 2 : Math.max(2, Math.min(9, v)); this.value = b.times; };
        eff.appendChild(times);
      }

      var tools = document.createElement('div');
      tools.style.cssText = 'display:flex;gap:6px;';
      [['↑', function () { if (i > 0) { btnDraft.splice(i - 1, 0, btnDraft.splice(i, 1)[0]); paintBtnRows(); } }],
       ['↓', function () { if (i < btnDraft.length - 1) { btnDraft.splice(i + 1, 0, btnDraft.splice(i, 1)[0]); paintBtnRows(); } }],
       ['✕', function () { btnDraft.splice(i, 1); paintBtnRows(); }]].forEach(function (t) {
        var x = document.createElement('button');
        x.type = 'button'; x.textContent = t[0]; x.onclick = t[1];
        x.style.cssText = 'width:44px;height:44px;border-radius:10px;background:#12141d;color:' + (t[0] === '✕' ? 'var(--aka-ink)' : 'var(--text)') + ';border:1px solid #2a2e40;font-size:18px;cursor:pointer;';
        tools.appendChild(x);
      });

      row.appendChild(label); row.appendChild(value); row.appendChild(eff); row.appendChild(tools);
      host.appendChild(row);
    });
    el('btnRowAdd').disabled = btnDraft.length >= BTN_MAX;
    el('btnRowAdd').style.opacity = btnDraft.length >= BTN_MAX ? .4 : 1;
  }

  el('btnRowAdd').onclick = function () {
    if (btnDraft.length >= BTN_MAX) return;
    btnDraft.push({ id: '', label: '', value: 1, effect: 'add', times: 2 });
    paintBtnRows();
  };
  el('btnRowDefault').onclick = function () {
    send('buttons', { buttons_default: true }).then(function () { btnDraftFromState(); paintBtnRows(); });
  };
  el('btnRowSave').onclick = function () {
    var list = btnDraft.filter(function (b) { return (b.label || '').trim() !== ''; }).map(function (b) {
      return { id: b.id || '', label: b.label.trim(), value: b.value || 0, effect: b.effect || 'add', times: b.times || 2 };
    });
    var payload = list.length ? { buttons: list } : { buttons_default: true };
    var save = el('btnRowSave'), was = save.textContent;
    send('buttons', payload).then(function () {
      btnDraftFromState(); paintBtnRows();
      save.textContent = BTN_TXT.saved;
      setTimeout(function () { save.textContent = was; }, 1500);
    });
  };

  /* ── The penalty ladder editor (Settings → Fouls) ───────────────────
     Works on a copy; nothing reaches the mat until Save. The engine applies
     each step's effect on the way up and undoes it on the way down. */
  var PEN_TXT = {!! json_encode([
    'none' => __('scoreboard::karate_messages.ctl_pen_none'),
    'deduct' => __('scoreboard::karate_messages.ctl_pen_deduct'),
    'award' => __('scoreboard::karate_messages.ctl_pen_award'),
    'ends' => __('scoreboard::karate_messages.ctl_pen_ends'),
    'label' => __('scoreboard::karate_messages.ctl_pen_label'),
    'saved' => __('scoreboard::karate_messages.ctl_btn_saved'),
  ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!};
  var PEN_MAX = 8;
  var penDraft = [];

  function penDraftFromState() {
    penDraft = penSteps().map(function (p) { return Object.assign({}, p); });
  }

  function cell(text, on, onclick, extra) {
    var c = document.createElement('button');
    c.type = 'button';
    c.textContent = text;
    c.style.cssText = 'font-size:15px;font-weight:700;letter-spacing:.04em;border-radius:9px;padding:10px 8px;cursor:pointer;white-space:nowrap;' +
      (on ? 'background:#ffe135;color:#000;border:1px solid transparent;' : 'background:#12141d;color:#9aa0b4;border:1px solid #2a2e40;') + (extra || '');
    c.onclick = onclick;
    return c;
  }

  function paintPenRows() {
    var host = el('penRows');
    host.textContent = '';
    penDraft.forEach(function (p, i) {
      var row = document.createElement('div');
      row.style.cssText = 'display:grid;grid-template-columns:minmax(0,1.3fr) minmax(0,3.2fr) auto auto;gap:10px;align-items:center;background:var(--inset);border:1px solid var(--line);border-radius:12px;padding:10px 12px;';

      var label = document.createElement('input');
      label.type = 'text'; label.maxLength = 16; label.value = p.label || ''; label.placeholder = PEN_TXT.label;
      label.style.cssText = FIELD;
      label.oninput = function () { p.label = this.value; };

      // What it does to the score — three cells, then the points it moves.
      var eff = document.createElement('div');
      eff.style.cssText = 'display:flex;gap:6px;align-items:center;min-width:0;';
      ['none', 'deduct', 'award'].forEach(function (k) {
        var c = cell(PEN_TXT[k], p.effect === k, function () { p.effect = k; if (k !== 'none' && !p.value) p.value = 0.5; paintPenRows(); }, 'flex:1;');
        eff.appendChild(c);
      });
      if (p.effect !== 'none') {
        var v = document.createElement('input');
        v.type = 'number'; v.min = 0; v.max = 20; v.step = 0.5; v.value = p.value || 0;
        v.style.cssText = FIELD + "width:76px;flex:0 0 auto;font-family:'Anton',sans-serif;text-align:center;color:var(--gold);";
        v.onchange = function () { var x = parseFloat(String(this.value).replace(',', '.')); p.value = isNaN(x) ? 0 : Math.max(0, Math.min(20, Math.round(x * 2) / 2)); this.value = p.value; };
        eff.appendChild(v);
      }

      // Reaching this step disqualifies the corner.
      var ends = cell(PEN_TXT.ends, !!p.ends, function () { p.ends = !p.ends; paintPenRows(); },
        p.ends ? 'background:#b3121f;color:#fff;' : '');

      var tools = document.createElement('div');
      tools.style.cssText = 'display:flex;gap:6px;';
      [['↑', function () { if (i > 0) { penDraft.splice(i - 1, 0, penDraft.splice(i, 1)[0]); paintPenRows(); } }],
       ['↓', function () { if (i < penDraft.length - 1) { penDraft.splice(i + 1, 0, penDraft.splice(i, 1)[0]); paintPenRows(); } }],
       ['✕', function () { penDraft.splice(i, 1); paintPenRows(); }]].forEach(function (t) {
        var x = document.createElement('button');
        x.type = 'button'; x.textContent = t[0]; x.onclick = t[1];
        x.style.cssText = 'width:44px;height:44px;border-radius:10px;background:#12141d;color:' + (t[0] === '✕' ? 'var(--aka-ink)' : 'var(--text)') + ';border:1px solid #2a2e40;font-size:18px;cursor:pointer;';
        tools.appendChild(x);
      });

      row.appendChild(label); row.appendChild(eff); row.appendChild(ends); row.appendChild(tools);
      host.appendChild(row);
    });
    el('penRowAdd').disabled = penDraft.length >= PEN_MAX;
    el('penRowAdd').style.opacity = penDraft.length >= PEN_MAX ? .4 : 1;
  }

  el('penRowAdd').onclick = function () {
    if (penDraft.length >= PEN_MAX) return;
    penDraft.push({ id: '', label: '', effect: 'none', value: 0, ends: false });
    paintPenRows();
  };
  el('penRowDefault').onclick = function () {
    send('penalties', { penalties_default: true }).then(function () { penDraftFromState(); paintPenRows(); });
  };
  el('penRowSave').onclick = function () {
    var list = penDraft.filter(function (p) { return (p.label || '').trim() !== ''; }).map(function (p) {
      return { id: p.id || '', label: p.label.trim(), effect: p.effect || 'none', value: p.effect === 'none' ? 0 : (p.value || 0), ends: !!p.ends };
    });
    var payload = list.length ? { penalties: list } : { penalties_default: true };
    var save = el('penRowSave'), was = save.textContent;
    send('penalties', payload).then(function () {
      penDraftFromState(); paintPenRows();
      save.textContent = PEN_TXT.saved;
      setTimeout(function () { save.textContent = was; }, 1500);
    });
  };
