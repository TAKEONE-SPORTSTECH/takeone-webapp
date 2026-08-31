{{--
    The scoring console's behaviour — ONE implementation, both layouts.

    The desktop console and the tablet console are deliberately different
    instruments (CLAUDE.md → Mobile / Desktop Separation), but they are not
    different RULES: they post the same commands to the same endpoint and draw
    the same state back. So the markup is split and the behaviour is not — this
    partial is included by both, and binds to whatever ids each one rendered.

    Everything here is delegated off `document` and guarded on the element
    existing, so a layout that does not render a control simply does not get it.

    ── The contract with the server ───────────────────────────────────────────
    Post an INTENTION, draw the answer. The response carries the whole state,
    the whole log and the whole queue, which is the same payload every wall
    screen receives — one shape, so a second code path cannot disagree with the
    first. A 422 is a REFUSAL an official needs to read, and it comes back with
    the current state so a console that had fallen behind corrects itself.
--}}
{{-- ⚠️ Every one of these is pre-assigned rather than inlined into @json().
     Blade's bracket matcher chokes on an array literal (or a closure returning
     one) inside @json(...), and the view then fails to compile with a
     misleading "expecting endif" — documented in CLAUDE.md. --}}
@php
    // What each scoring action is called, and what it is worth. The VALUE is
    // shown for the operator's benefit only; the server prices the action
    // itself (Ledger::POINT_SOURCES), so nothing here can change a score.
    $sourceWords = [];
    foreach ($pointSources as $key => $value) {
        $sourceWords[$key] = [
            'value' => $value,
            'label' => __('event-bjj_tournament::messages.source_'.$key),
        ];
    }

    $penaltyWords = [];
    foreach ($penaltyReasons as $reason) {
        $penaltyWords[$reason] = __('event-bjj_tournament::messages.penalty_'.$reason);
    }

    $methodWords = [];
    foreach ($winMethods as $method) {
        $methodWords[$method] = __('event-bjj_tournament::messages.method_'.$method);
    }

    $statusWords = [
        'idle' => __('event-bjj_tournament::messages.status_idle'),
        'live' => __('event-bjj_tournament::messages.status_live'),
        'paused' => __('event-bjj_tournament::messages.status_paused'),
        'review' => __('event-bjj_tournament::messages.status_review'),
        'medical' => __('event-bjj_tournament::messages.status_medical'),
        'overtime' => __('event-bjj_tournament::messages.status_overtime'),
        'submission' => __('event-bjj_tournament::messages.status_submission'),
        'dq' => __('event-bjj_tournament::messages.status_dq'),
        'walkover' => __('event-bjj_tournament::messages.status_walkover'),
        'finished' => __('event-bjj_tournament::messages.status_finished'),
    ];

    $consoleWords = [
        'undo' => __('event-bjj_tournament::messages.ctl_undo'),
        'undo_hint' => __('event-bjj_tournament::messages.ctl_undo_hint'),
        'reason' => __('event-bjj_tournament::messages.ctl_reason'),
        'reason_required' => __('event-bjj_tournament::messages.reason_required'),
        'end' => __('event-bjj_tournament::messages.ctl_end'),
        'reset' => __('event-bjj_tournament::messages.ctl_reset'),
        'commit' => __('event-bjj_tournament::messages.ctl_commit'),
        'decision' => __('event-bjj_tournament::messages.ctl_decision'),
        'overtime' => __('event-bjj_tournament::messages.ctl_overtime'),
        'penalties' => __('event-bjj_tournament::messages.penalties'),
        'type_match_no' => __('event-bjj_tournament::messages.ctl_type_match_no'),
        'no_screens' => __('event-bjj_tournament::messages.ctl_no_screens'),
        'screens' => __('event-bjj_tournament::messages.ctl_screens'),
        'blue' => __('sport-brazilianjiujitsu::messages.corner_blue'),
        'white' => __('sport-brazilianjiujitsu::messages.corner_white'),
        'tbd' => __('event-bjj_tournament::messages.court_tbd'),
        'load' => __('event-bjj_tournament::messages.ctl_load'),
        'start' => __('event-bjj_tournament::messages.ctl_start'),
        'pause' => __('event-bjj_tournament::messages.ctl_pause'),
        'resume' => __('event-bjj_tournament::messages.ctl_resume'),
    ];
@endphp
<script>
(function () {
  'use strict';

  var URL_CMD = @json($commandUrl);
  var CSRF = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

  var SOURCES = @json($sourceWords);
  var PENALTIES = @json($penaltyWords);
  var METHODS = @json($methodWords);
  var STATUS = @json($statusWords);
  var T = @json($consoleWords);

  var MAT = @json($court);
  var SCREENS = @json($screens);

  var S = @json($state);
  var LOG = @json($log);
  var QUEUE = @json($queue);
  var recvAt = Date.now();
  var stall = null;         // {side, until} — private to this console
  var locked = false;       // the 400ms one-tap-one-event lockout
  var lastEntry = null;     // what the 5s toast would undo

  function el(id) { return document.getElementById(id); }
  function text(id, v) { var e = el(id); if (e) e.textContent = v == null ? '' : v; }

  /* ── Talking to the server ─────────────────────────────────────────────
     One function, because there is one contract. Nothing else in this file
     writes anything. */
  function send(command, payload, done) {
    var body = Object.assign({ mat: MAT, command: command }, payload || {});

    return fetch(URL_CMD, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
      body: JSON.stringify(body),
      credentials: 'same-origin'
    })
      .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, body: j }; }); })
      .then(function (res) {
        // A refusal carries the truth with it, so draw that too — a console
        // that was behind is now correct, and the message says why it was.
        if (res.body.state) { S = res.body.state; recvAt = Date.now(); }
        if (res.body.log) LOG = res.body.log;
        if (res.body.queue) QUEUE = res.body.queue;
        if (res.body.hasOwnProperty('stall')) stall = res.body.stall;
        paint();

        if (!res.ok) { flash(res.body.message || ''); return null; }
        if (done) done(res.body);
        return res.body;
      })
      .catch(function () { flash(''); return null; });
  }

  /**
   * One tap is one event.
   *
   * 400ms, exactly as the design spec sets out — a referee's hand on a tablet
   * at the edge of a mat produces double-taps, and the cost of one is a point
   * nobody scored on a wall in front of a hall.
   */
  function guarded(fn) {
    if (locked) return;
    locked = true;
    setTimeout(function () { locked = false; }, 400);
    fn();
  }

  /* ── The 5-second UNDO toast ───────────────────────────────────────────
     Not a delayed write: the point is ALREADY recorded and already on the wall.
     The toast is a fast path to the reversal that would otherwise take a hold
     on the log row — the same append, with a reason filled in for you. */
  var toastTimer = null;

  function offerUndo(entryId, label) {
    lastEntry = entryId;
    var toast = el('toast');
    if (!toast) return;
    text('toastText', label);
    toast.hidden = false;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { toast.hidden = true; lastEntry = null; }, 5000);
  }

  function flash(message) {
    if (!message) return;
    // The console is a standalone document with no app shell behind it, so it
    // cannot reach window.showToast. Same idea, its own furniture.
    var toast = el('toast');
    if (!toast) return;
    text('toastText', message);
    var undo = el('toastUndo');
    if (undo) undo.hidden = true;
    toast.hidden = false;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { toast.hidden = true; if (undo) undo.hidden = false; }, 4000);
  }

  /* ── Modals: the friction the spec asks for ───────────────────────────── */
  var modalOk = null;

  function modal(title, fields, onOk) {
    var scrim = el('scrim');
    if (!scrim) { if (onOk) onOk({}); return; }

    text('modalTitle', title);
    var body = el('modalBody');
    body.innerHTML = '';

    fields.forEach(function (f) {
      var wrap = document.createElement('div');

      if (f.type === 'choice') {
        var lbl = document.createElement('label'); lbl.textContent = f.label; wrap.appendChild(lbl);
        var row = document.createElement('div'); row.className = 'choiceRow';
        Object.keys(f.options).forEach(function (k) {
          var b = document.createElement('button');
          b.type = 'button'; b.className = 'choice'; b.dataset.value = k; b.textContent = f.options[k];
          b.addEventListener('click', function () {
            row.querySelectorAll('.choice').forEach(function (o) { o.classList.remove('on'); });
            b.classList.add('on');
          });
          if (f.value === k) b.classList.add('on');
          row.appendChild(b);
        });
        wrap.appendChild(row);
        wrap.dataset.name = f.name; wrap.dataset.kind = 'choice';
      } else {
        var l2 = document.createElement('label'); l2.textContent = f.label; wrap.appendChild(l2);
        var input = document.createElement('input');
        input.type = 'text'; input.value = f.value || ''; input.placeholder = f.placeholder || '';
        wrap.appendChild(input);
        wrap.dataset.name = f.name; wrap.dataset.kind = 'text';
      }

      body.appendChild(wrap);
    });

    modalOk = function () {
      var out = {};
      Array.prototype.forEach.call(body.children, function (wrap) {
        out[wrap.dataset.name] = wrap.dataset.kind === 'choice'
          ? (wrap.querySelector('.choice.on') || {}).dataset && wrap.querySelector('.choice.on').dataset.value
          : wrap.querySelector('input').value.trim();
      });
      onOk(out);
    };

    scrim.hidden = false;
  }

  function closeModal() { var s = el('scrim'); if (s) s.hidden = true; modalOk = null; }

  /* ── Painting ─────────────────────────────────────────────────────────── */
  function remaining() {
    return S.running ? Math.max(0, S.remaining - (Date.now() - recvAt) / 1000) : S.remaining;
  }

  function clock(s) {
    s = Math.max(0, Math.ceil(s));
    return Math.floor(s / 60) + ':' + ('0' + (s % 60)).slice(-2);
  }

  function buildScoreButtons() {
    ['blue', 'white'].forEach(function (side) {
      var host = el(side + 'ScoreGrid');
      if (!host || host.dataset.built) return;
      host.dataset.built = '1';

      Object.keys(SOURCES).forEach(function (key) {
        var b = document.createElement('button');
        b.className = 'btn score';
        b.dataset.cmd = 'point'; b.dataset.side = side; b.dataset.source = key;
        // The value is shown because it is what an official is thinking in,
        // and the ACTION is shown beneath it because in jiu-jitsu the number
        // does not name the action — two points is three different things.
        b.innerHTML = '<span class="v">+' + SOURCES[key].value + '</span>';
        var l = document.createElement('span'); l.className = 'l'; l.textContent = SOURCES[key].label;
        b.appendChild(l);
        host.appendChild(b);
      });
    });
  }

  function paintClock() {
    var r = remaining();
    var warn = S.status === 'live' && r > 0 && r <= ((S.rules && S.rules.warning) || 60);
    var v = el('clockVal');
    if (v) { v.textContent = clock(r); v.className = 'num' + (warn ? ' warn' : ''); }
    text('clockState', STATUS[S.status] || '');

    var dot = el('liveDot');
    if (dot) dot.className = S.status === 'live' ? 'on' : '';
    text('liveText', STATUS[S.status] || '');

    // The referee's own count, on this console and nowhere else.
    if (stall && stall.until) {
      var left = Math.max(0, Math.ceil((new Date(stall.until).getTime() - Date.now()) / 1000));
      text('stallCount', left);
      if (el('stallApply')) el('stallApply').disabled = false;
      if (el('stallCancel')) el('stallCancel').disabled = false;
    } else {
      text('stallCount', '—');
      if (el('stallApply')) el('stallApply').disabled = true;
      if (el('stallCancel')) el('stallCancel').disabled = true;
    }
  }

  function paintCorners() {
    var sc = S.score || {};

    [['blue', S.blue || {}], ['white', S.white || {}]].forEach(function (pair) {
      var k = pair[0], c = pair[1];
      text(k + 'Name', (c.name || T.tbd).toUpperCase());
      text(k + 'Score', sc[k + 'Points'] || 0);
      text(k + 'Adv', sc[k + 'Advantages'] || 0);
      text(k + 'Pen', sc[k + 'Penalties'] || 0);

      // The third penalty warns that the next one disqualifies. Console only —
      // the hall is not told what is about to happen to somebody.
      var warnAt = (S.rules && S.rules.penalty_warn_at) || 3;
      var w = el(k + 'WarnDq');
      if (w) w.hidden = (sc[k + 'Penalties'] || 0) < warnAt;
    });
  }

  function paintTransport() {
    var loaded = !!S.matchId;
    var over = !!S.finished;
    var running = !!S.running;

    var start = el('btnStart');
    if (start) {
      start.textContent = running ? T.pause : (S.status === 'idle' ? T.start : T.resume);
      start.dataset.action = running ? 'pause' : (S.status === 'idle' ? 'start' : 'resume');
      start.disabled = !loaded || over;
    }
    if (el('btnPause')) el('btnPause').disabled = !loaded || over || !running;
    if (el('btnEnd')) el('btnEnd').disabled = !loaded || over;
    if (el('btnReset')) el('btnReset').disabled = !loaded;
    if (el('btnCommit')) el('btnCommit').disabled = !loaded || !over;

    // A level match at 0:00 is not a result — IBJJF sends it to the referee, so
    // the console surfaces that rather than leaving an official to guess.
    var level = loaded && !over && S.awaitingDecision && !(S.score || {}).leader;
    if (el('btnDecision')) el('btnDecision').hidden = !level;

    document.querySelectorAll('[data-cmd]').forEach(function (b) {
      if (b.dataset.cmd === 'resync') return;
      b.disabled = !loaded || (over && b.dataset.cmd !== 'intro');
    });
    document.querySelectorAll('[data-penalty],[data-stall]').forEach(function (b) {
      b.disabled = !loaded || over;
    });
  }

  function paintLog() {
    var host = el('log');
    if (!host) return;
    host.innerHTML = '';

    LOG.forEach(function (row) {
      var line = document.createElement('div');
      line.className = 'logRow' + (row.reversed ? ' rev' : '');

      var ts = document.createElement('span'); ts.className = 'ts'; ts.textContent = row.clock || '';
      var chip = document.createElement('span');
      chip.className = 'logChip ' + (['point', 'advantage', 'penalty', 'reverse'].indexOf(row.action) >= 0 ? row.action : 'other');
      chip.textContent = row.action;

      var d = document.createElement('span'); d.className = 'd';
      d.textContent = [
        row.side ? (row.side === 'blue' ? T.blue : T.white) : null,
        row.value ? '+' + row.value : null,
        row.source ? (SOURCES[row.source] ? SOURCES[row.source].label : (PENALTIES[row.source] || row.source)) : null,
        row.reason || null,
        row.by || null
      ].filter(Boolean).join(' · ');

      line.append(ts, chip, d);

      // Correcting past the toast: a 1s hold, so a stray tap on a busy table
      // cannot rewrite a match. Releasing cancels it.
      if (!row.reversed && ['point', 'advantage', 'penalty'].indexOf(row.action) >= 0) {
        var undo = document.createElement('button');
        undo.className = 'btn undo'; undo.style.minHeight = '32px';
        undo.title = T.undo_hint;
        undo.innerHTML = '<span class="sweep"></span>';
        var label = document.createElement('span'); label.textContent = T.undo; label.style.position = 'relative';
        undo.appendChild(label);
        holdToConfirm(undo, function () { askReverse(row.id); });
        line.appendChild(undo);
      }

      host.appendChild(line);
    });
  }

  function paintQueue() {
    var host = el('queueList');
    if (!host) return;
    host.innerHTML = '';

    QUEUE.forEach(function (m) {
      var row = document.createElement('div'); row.className = 'qItem';
      var n = document.createElement('span'); n.className = 'n'; n.textContent = m.number ? '#' + m.number : '—';
      var who = document.createElement('div'); who.className = 'who';
      who.textContent = (m.blue.name || T.tbd) + '  ·  ' + (m.white.name || T.tbd);
      var meta = document.createElement('div'); meta.className = 'm';
      meta.textContent = [m.division, m.stage].filter(Boolean).join(' · ');
      who.appendChild(meta);

      var load = document.createElement('button');
      load.className = 'btn'; load.style.minHeight = '40px'; load.textContent = T.load;
      // A match still waiting on a feeder is LISTED — the operator needs to see
      // what is coming — but it cannot be loaded: nobody can be introduced as
      // "winner of match 3".
      load.disabled = !m.runnable;
      load.addEventListener('click', function () { guarded(function () { send('load', { match_id: m.id }); }); });

      row.append(n, who, load);
      host.appendChild(row);
    });
  }

  function paintMeta() {
    text('topMeta', [S.division, S.stage, S.matchNo ? '#' + S.matchNo : null].filter(Boolean).join(' · '));
    text('topRuleset', S.ruleset || '');
    text('topReferee', S.referee || '');
    text('screensLine', SCREENS ? T.screens.replace(':count', SCREENS) : T.no_screens);
    var theme = el('themeToggle');
    if (theme) theme.checked = S.theme === 'venue';
  }

  function paint() {
    buildScoreButtons();
    paintMeta();
    paintCorners();
    paintTransport();
    paintClock();
    paintLog();
    paintQueue();
  }

  /* ── Hold-to-confirm ──────────────────────────────────────────────────── */
  function holdToConfirm(button, run) {
    var timer = null;

    function down(e) {
      e.preventDefault();
      button.classList.add('holding');
      timer = setTimeout(function () { button.classList.remove('holding'); run(); }, 1000);
    }
    function up() {
      clearTimeout(timer);
      button.classList.remove('holding');
    }

    button.addEventListener('pointerdown', down);
    button.addEventListener('pointerup', up);
    button.addEventListener('pointerleave', up);
    button.addEventListener('pointercancel', up);
  }

  /* ── The commands ─────────────────────────────────────────────────────── */
  function askReverse(id) {
    modal(T.undo, [
      { name: 'reason', type: 'text', label: T.reason, placeholder: T.reason_required }
    ], function (out) {
      if (!out.reason) { flash(T.reason_required); return; }
      closeModal();
      send('reverse', { ledger_id: id, reason: out.reason });
    });
  }

  // Scoring: no confirmation at all, a 400ms lockout, and the undo toast.
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-cmd]');
    if (!b || b.disabled) return;

    var cmd = b.dataset.cmd;
    guarded(function () {
      send(cmd, { side: b.dataset.side, source: b.dataset.source }, function (body) {
        if (cmd !== 'point' && cmd !== 'advantage') return;
        var last = (body.log || [])[0];
        if (last) {
          offerUndo(last.id, (b.dataset.side === 'blue' ? T.blue : T.white) + ' · '
            + (cmd === 'point' ? '+' + last.value : T.undo_hint));
        }
      });
    });
  });

  // A penalty asks WHY. It is a formal act that reaches the record and the
  // wall's four-second notice reads the reason out of it.
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-penalty]');
    if (!b || b.disabled) return;
    var side = b.dataset.penalty;

    modal(T.penalties, [
      { name: 'source', type: 'choice', label: T.reason, options: PENALTIES, value: 'other' }
    ], function (out) {
      closeModal();
      guarded(function () { send('penalty', { side: side, source: out.source || 'other' }); });
    });
  });

  // The referee's stalling countdown: start, cancel, apply. Private throughout.
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-stall]');
    if (!b || b.disabled) return;
    guarded(function () { send('stall', { side: b.dataset.stall, phase: 'start' }); });
  });

  if (el('stallCancel')) el('stallCancel').addEventListener('click', function () { send('stall', { phase: 'cancel' }); });
  if (el('stallApply')) el('stallApply').addEventListener('click', function () {
    modal(T.penalties, [], function () { closeModal(); send('stall', { phase: 'apply' }); });
  });

  // Transport is a single tap. It is reversible by its opposite, and a pause
  // nobody meant costs nothing.
  if (el('btnStart')) el('btnStart').addEventListener('click', function () {
    guarded(function () { send(el('btnStart').dataset.action || 'start'); });
  });
  if (el('btnPause')) el('btnPause').addEventListener('click', function () { guarded(function () { send('pause'); }); });

  if (el('btnOvertime')) el('btnOvertime').addEventListener('click', function () {
    modal(T.overtime, [{ name: 'seconds', type: 'text', label: T.overtime, value: '180' }], function (out) {
      closeModal();
      send('overtime', { seconds: parseInt(out.seconds, 10) || 180 });
    });
  });

  // Ending a match is a modal: who, and how.
  if (el('btnEnd')) el('btnEnd').addEventListener('click', function () {
    modal(T.end, [
      { name: 'winner', type: 'choice', label: T.decision, options: { blue: T.blue, white: T.white } },
      { name: 'method', type: 'choice', label: T.end, options: METHODS, value: 'points' },
      { name: 'note', type: 'text', label: T.reason }
    ], function (out) {
      // A disqualification is the one ending that also takes the match number,
      // because it is the one an athlete disputes.
      if (out.method === 'dq') {
        closeModal();
        return confirmWithNumber(T.end, function () {
          send('end', out);
        });
      }
      closeModal();
      send('end', out);
    });
  });

  if (el('btnDecision')) el('btnDecision').addEventListener('click', function () {
    modal(T.decision, [
      { name: 'winner', type: 'choice', label: T.decision, options: { blue: T.blue, white: T.white } }
    ], function (out) {
      if (!out.winner) return;
      closeModal();
      send('decision', { winner: out.winner });
    });
  });

  if (el('btnReset')) el('btnReset').addEventListener('click', function () {
    modal(T.reset, [], function () { closeModal(); send('reset'); });
  });

  // Finalizing writes the result into the bracket. Modal AND the match number
  // typed in: it is the one action here that cannot be taken back from this
  // console at all.
  if (el('btnCommit')) el('btnCommit').addEventListener('click', function () {
    confirmWithNumber(T.commit, function () { send('commit'); });
  });

  function confirmWithNumber(title, run) {
    modal(title, [
      { name: 'number', type: 'text', label: T.type_match_no, placeholder: S.matchNo || '' }
    ], function (out) {
      if (String(out.number || '').trim() !== String(S.matchNo || '')) { flash(T.type_match_no); return; }
      closeModal();
      run();
    });
  }

  if (el('themeToggle')) el('themeToggle').addEventListener('change', function () {
    send('theme', { theme: this.checked ? 'venue' : 'arena' });
  });

  if (el('modalCancel')) el('modalCancel').addEventListener('click', closeModal);
  if (el('modalOk')) el('modalOk').addEventListener('click', function () { if (modalOk) modalOk(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeModal(); });

  if (el('toastUndo')) el('toastUndo').addEventListener('click', function () {
    if (lastEntry) askReverse(lastEntry);
    el('toast').hidden = true;
  });

  setInterval(paintClock, 200);
  paint();

@isset($heartbeatUrl)
  // A paired console is a SCREEN, and the organiser's panel lists it beside the
  // boards with a live dot. Commands alone would show a mat waiting twenty
  // minutes for the next match as offline — the opposite of the truth.
  setInterval(function () {
    fetch(@json($heartbeatUrl), { cache: 'no-store' })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (s) { if (s && s.claimed === false) window.location.reload(); })
      .catch(function () {});
  }, 5000);
@endisset

  // The console is also a screen on this mat, so the same socket that drives
  // the wall keeps a second console in step with the first.
  window.CourtBoard = {
    update: function (p) {
      if (!p || !p.mode) return;
      S = p; recvAt = Date.now(); paint();
    },
    mode: function () { return S.mode || 'upcoming'; },
    pinned: 'bout',
    stale: function () {}
  };
})();
</script>
