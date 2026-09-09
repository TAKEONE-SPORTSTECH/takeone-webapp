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
            'label' => __('scoreboard::bjj_messages.source_'.$key),
        ];
    }

    $penaltyWords = [];
    foreach ($penaltyReasons as $reason) {
        $penaltyWords[$reason] = __('scoreboard::bjj_messages.penalty_'.$reason);
    }

    $methodWords = [];
    foreach ($winMethods as $method) {
        $methodWords[$method] = __('scoreboard::bjj_messages.method_'.$method);
    }

    $statusWords = [
        'idle' => __('scoreboard::bjj_messages.status_idle'),
        'live' => __('scoreboard::bjj_messages.status_live'),
        'paused' => __('scoreboard::bjj_messages.status_paused'),
        'review' => __('scoreboard::bjj_messages.status_review'),
        'medical' => __('scoreboard::bjj_messages.status_medical'),
        'overtime' => __('scoreboard::bjj_messages.status_overtime'),
        'submission' => __('scoreboard::bjj_messages.status_submission'),
        'dq' => __('scoreboard::bjj_messages.status_dq'),
        'walkover' => __('scoreboard::bjj_messages.status_walkover'),
        'finished' => __('scoreboard::bjj_messages.status_finished'),
    ];

    $consoleWords = [
        'undo' => __('scoreboard::bjj_messages.ctl_undo'),
        'undo_hint' => __('scoreboard::bjj_messages.ctl_undo_hint'),
        'reason' => __('scoreboard::bjj_messages.ctl_reason'),
        'reason_required' => __('scoreboard::bjj_messages.reason_required'),
        'end' => __('scoreboard::bjj_messages.ctl_end'),
        'reset' => __('scoreboard::bjj_messages.ctl_reset'),
        'commit' => __('scoreboard::bjj_messages.ctl_commit'),
        'over_result' => __('scoreboard::bjj_messages.ctl_over_result'),
        'over_next' => __('scoreboard::bjj_messages.ctl_over_next'),
        'over_undecided' => __('scoreboard::bjj_messages.ctl_over_undecided'),
        'over_by' => __('scoreboard::bjj_messages.ctl_over_by'),
        'decision' => __('scoreboard::bjj_messages.ctl_decision'),
        'overtime' => __('scoreboard::bjj_messages.ctl_overtime'),
        'penalties' => __('scoreboard::bjj_messages.penalties'),
        'winner' => __('scoreboard::bjj_messages.ctl_winner'),
        'method' => __('scoreboard::bjj_messages.ctl_method'),
        'winner_required' => __('scoreboard::bjj_messages.ctl_winner_required'),
        'type_match_no' => __('scoreboard::bjj_messages.ctl_type_match_no'),
        'no_screens' => __('scoreboard::bjj_messages.ctl_no_screens'),
        'screens' => __('scoreboard::bjj_messages.ctl_screens'),
        'blue' => __('sport-brazilianjiujitsu::messages.corner_blue'),
        'white' => __('sport-brazilianjiujitsu::messages.corner_white'),
        'tbd' => __('scoreboard::bjj_messages.court_tbd'),
        'load' => __('scoreboard::bjj_messages.ctl_load'),
        'show_board' => __('scoreboard::bjj_messages.ctl_show_board'),
        'show_intro' => __('scoreboard::bjj_messages.ctl_show_intro'),
        'resynced' => __('scoreboard::bjj_messages.ctl_resynced'),
        'sound_none' => __('events.screen_audio_none'),
        'sound_unavailable' => __('scoreboard::bjj_messages.ctl_sound_unavailable'),
        'failed' => __('scoreboard::bjj_messages.ctl_failed'),
        'no_queue' => __('scoreboard::bjj_messages.ctl_no_queue'),
        'waiting_feeder' => __('scoreboard::bjj_messages.ctl_waiting_feeder'),
        'start' => __('scoreboard::bjj_messages.ctl_start'),
        'pause' => __('scoreboard::bjj_messages.ctl_pause'),
        'resume' => __('scoreboard::bjj_messages.ctl_resume'),
    ];
@endphp
<script>
(function () {
  'use strict';

  var URL_CMD = @json($commandUrl);
  // The sounds store. Null on a console that was not given an upload address —
  // said plainly rather than failing quietly, because an organiser's laptop
  // door with no address is a bug, not an empty state.
  var AUDIO_BASE = @json($audioUploadBase ?? null);
  var AUDIO_SLOTS = @json($audioSlots ?? []);
  var AUDIO_SLOT_LIST = @json(\App\Events\Support\ScreenMedia::SLOTS);
  var CSRF = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

  var SOURCES = @json($sourceWords);
  var PENALTIES = @json($penaltyWords);
  var METHODS = @json($methodWords);

  /* Mirrors Scoring::METHODS_NEEDING_WINNER. The server refuses these without a
     winner; the console refuses them a step earlier so the official fixes it in
     the dialog they are already looking at rather than reading it back as an
     error. The server is still the one that decides — this only saves a round
     trip and a moment of confusion at a mat. */
  var METHODS_NEEDING_WINNER = ['submission', 'decision', 'dq', 'walkover', 'medical', 'forfeit'];
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
  var overShownFor = null;  // the match whose end panel has already been offered
  var bellSentFor = null;   // the match whose expiry has already been reported

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

  /* ── The toast ─────────────────────────────────────────────────────────
     A NOTICE, and only that. It used to carry an Undo for five seconds after
     every point, which is where the offer came from that popped up on each
     score — removed 2026-09-08 at the user's request. Undo now lives in the
     score log, on the row it undoes, where an official goes when something
     actually needs putting right rather than being asked to second-guess a
     call they have just made while the fight carries on. */
  var toastTimer = null;

  function flash(message) {
    if (!message) return;
    // The console is a standalone document with no app shell behind it, so it
    // cannot reach window.showToast. Same idea, its own furniture.
    var toast = el('toast');
    if (!toast) return;
    text('toastText', message);
    toast.hidden = false;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { toast.hidden = true; }, 4000);
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

    /* The bell, reported once.
       The server stores the clock as "remaining as of a moment" and only
       settles it when a command arrives — so a match that has run out of time
       is not yet OVER on the server, and this console would go on showing 0:00
       against a live match until somebody pressed something. The hall has
       already heard the buzzer by then (the board plays it off its own clock),
       which is the worst version: the mat knows and the table does not.
       One `bell` on the way down settles it, and the engine then does what it
       always does — finish the match, or raise a referee decision. */
    if (S.matchId && S.running && r <= 0 && bellSentFor !== S.matchId) {
      bellSentFor = S.matchId;
      send('bell');
    }
    if (r > 0) bellSentFor = null;
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

      // Karate's treatment, to the letter: green to go, red while it runs, and
      // a glow that pulses only while the clock is live — the one thing on this
      // panel that moves, so a glance from the mat answers "is it running?".
      //
      // ⚠️ The gloss rides OVER the colour, so the fill is set on its own
      // property: `background` is a shorthand and would drop the gradient every
      // tick, which is 10 times a second.
      start.style.backgroundImage = 'linear-gradient(rgba(255,255,255,.28),rgba(255,255,255,0) 60%,rgba(0,0,0,.14))';
      start.style.backgroundColor = running ? '#ff6b78' : '#7ae582';
      start.style.animation = running ? 'runGlow 1.8s ease-in-out infinite' : 'none';
    }
    if (el('btnPause')) el('btnPause').disabled = !loaded || over || !running;
    if (el('btnEnd')) el('btnEnd').disabled = !loaded || over;
    if (el('btnReset')) el('btnReset').disabled = !loaded;
    if (el('btnCommit')) el('btnCommit').disabled = !loaded || !over;
    // The way back to the end-of-match panel once it has been dismissed.
    if (el('btnFinalize')) el('btnFinalize').disabled = !loaded || !over;

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

  /* ── The end of a match ────────────────────────────────────────────────
     A finished match is a decision waiting to be taken, so the console OFFERS
     it rather than leaving an official to find a button: the moment the state
     says finished — whether the clock ran out, a submission was called or the
     table pressed End — this opens with the result, what is about to be written
     and what is next on the mat.

     Offered ONCE per match. Dismissing it is "not yet", not "never": the
     Finalize button in the centre column opens the same panel again, because a
     table that has dismissed this must still be able to record the result.

     It closes itself when the match is no longer finished — which is every way
     out of here at once: recording (the server loads the next bout), a reset,
     or somebody loading a different match from another console. */
  function paintOver() {
    var panel = el('overPanel');
    if (!panel) return;

    if (!S.finished) { panel.hidden = true; overShownFor = null; return; }

    var win = S.winner === 'blue' ? (S.blue || {}) : S.winner === 'white' ? (S.white || {}) : null;
    var sc = S.score || {};

    // The result in one line: who won, how, and the two point totals. Never a
    // sum — points, advantages and penalties are separate ladders in this sport.
    text('overWho', win ? (win.name || T.tbd) : T.over_undecided);
    text('overHow', win
      ? [(S.winMethod ? (METHODS[S.winMethod] || S.winMethod) : null), S.winNote || null]
          .filter(Boolean).join(' · ')
      : '');
    text('overScore', win ? (sc.bluePoints || 0) + ' — ' + (sc.whitePoints || 0) : '');

    // What the mat runs next, read from the SAME queue the wall announces, so
    // this cannot promise a bout the board is not expecting.
    var next = (QUEUE || []).filter(function (b) { return b.runnable && b.id !== S.matchId; })[0];
    text('overNext', next
      ? '#' + (next.no || '?') + '  ' + (next.blue && next.blue.name || T.tbd)
        + '  vs  ' + (next.white && next.white.name || T.tbd)
      : T.no_queue);

    // A level match has no winner to record. The referee's decision comes
    // first — the Decision button is already up in the centre column.
    var record = el('btnCommit');
    if (record) record.hidden = !win;

    if (overShownFor !== S.matchId) {
      overShownFor = S.matchId;
      panel.hidden = false;
    }
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

  /* ── The bouts card (desktop) ──────────────────────────────────────────
     The same running order #queueList carries on the tablet, drawn wide: the
     bout number, the stage and division, then the two corners facing each
     other across the "vs". It paints ONLY when the desktop console's card is
     on the page, so the mobile sheet keeps its own compact rows untouched. */
  function flagUrl(code) {
    return /^[a-z]{2}$/.test(code || '') ? 'url("https://flagcdn.com/w1280/' + code + '.png")' : 'none';
  }

  function competitorCell(side, colour, ink, mirror) {
    side = side || {};

    var wrap = document.createElement('span');
    wrap.style.cssText = 'display:flex;align-items:center;gap:12px;min-width:0;flex:1;' + (mirror ? 'flex-direction:row-reverse;' : '');

    // A PORTRAIT, three wide to four tall — the shape every screen in this
    // package draws a person in. Initials are the fallback, not the default:
    // a competitor with no photo is normal, and an empty grey box reads broken.
    var ini = document.createElement('span');
    ini.style.cssText = 'width:48px;height:64px;flex:0 0 auto;border-radius:8px;background:#1f2230;border:2px solid ' + colour +
      ';background-size:cover;background-position:center 15%;overflow:hidden;' +
      "display:flex;align-items:center;justify-content:center;font-family:'Anton',sans-serif;font-size:20px;color:" + ink + ';';

    // Their own face first; then the drawn stand-in for their gender, which the
    // server only sends when the gender is actually on file; then their
    // initials, which invent nothing about a person nobody recorded.
    var face = side.photo || side.fallback;
    if (face) {
      ini.style.backgroundImage = 'url("' + face + '")';
      // The stand-in is a drawing, not this competitor: it sits back a little
      // so a row of them never reads as a row of photographs.
      if (!side.photo) ini.style.opacity = '.72';
    } else {
      ini.textContent = (side.name || '').split(/\s+/).filter(Boolean).slice(0, 2)
        .map(function (w) { return w.charAt(0).toUpperCase(); }).join('') || '—';
    }

    var col = document.createElement('span');
    col.style.cssText = 'min-width:0;display:flex;flex-direction:column;gap:2px;' + (mirror ? 'align-items:flex-end;' : '');

    // ⚠️ WRAPS rather than truncates. An ellipsis on a competition sheet is
    // worse than a taller row: "Mohammed Abdulnabi ali zainuddin" and
    // "Victorian Academy Jiu-Jitsu" are the normal length here, not the
    // exception, and an operator calling a name off this list has to be able to
    // read all of it. The list scrolls; the row can be two lines.
    var nm = document.createElement('span');
    nm.style.cssText = 'color:' + ink + ';line-height:1.1;overflow-wrap:anywhere;' +
      (mirror ? 'text-align:right;' : '');
    nm.textContent = side.name || T.tbd;

    var cl = document.createElement('span');
    cl.style.cssText = 'display:flex;align-items:center;gap:8px;font-size:17px;color:#7d8296;flex-wrap:wrap;' +
      (mirror ? 'flex-direction:row-reverse;' : '');
    var fl = document.createElement('span');
    fl.style.cssText = 'width:24px;height:16px;flex:0 0 auto;background-size:100% 100%;background-position:center;' +
      'border:1px solid rgba(255,255,255,.2);border-radius:3px;background-image:' + flagUrl(side.flag) + ';';
    cl.appendChild(fl);

    // The club's crest, as the introduction shows it. A transparent PNG on a
    // bare sizing box — never a filled tile (CLAUDE.md Design Rule #5) — and
    // only when there is one: most clubs at a real competition are written
    // down on the day and have no crest to show.
    if (side.logo) {
      var lg = document.createElement('span');
      lg.style.cssText = 'width:22px;height:22px;flex:0 0 auto;background-size:contain;background-repeat:no-repeat;' +
        'background-position:center;background-image:url("' + side.logo + '");';
      cl.appendChild(lg);
    }

    var cn = document.createElement('span');
    cn.style.cssText = 'overflow-wrap:anywhere;';
    cn.textContent = side.club || '';
    cl.appendChild(cn);

    col.appendChild(nm); col.appendChild(cl);
    wrap.appendChild(ini); wrap.appendChild(col);

    return wrap;
  }

  function paintBouts() {
    var host = el('boutsList');
    if (!host) return;
    host.textContent = '';

    if (!QUEUE.length) {
      var empty = document.createElement('div');
      empty.style.cssText = 'font-size:21px;color:#5c6175;padding:16px 4px;';
      empty.textContent = T.no_queue;
      host.appendChild(empty);
      return;
    }

    QUEUE.forEach(function (b) {
      var row = document.createElement('button');
      row.type = 'button';
      row.style.cssText = 'display:grid;grid-template-columns:78px 164px 1fr 44px 1fr;align-items:center;gap:14px;font-size:30px;' +
        'background:' + (b.id === S.matchId ? '#1f2230' : '#0a0b10') + ';color:#e8eaf2;border:1px solid #1f2230;' +
        'border-radius:12px;padding:20px 22px;cursor:pointer;text-align:left;';

      var no = document.createElement('span');
      no.style.cssText = "font-family:'Anton',sans-serif;color:#ffe135;";
      no.textContent = '#' + (b.number == null ? '—' : b.number);

      var stage = document.createElement('span');
      stage.style.cssText = 'min-width:0;display:flex;flex-direction:column;gap:2px;';
      var st = document.createElement('span');
      st.style.cssText = 'color:#7d8296;text-transform:uppercase;font-size:22px;letter-spacing:.08em;line-height:1.1;';
      st.textContent = b.stage || '';
      var cat = document.createElement('span');
      cat.style.cssText = 'font-size:16px;color:#5c6175;letter-spacing:.04em;overflow-wrap:anywhere;';
      cat.textContent = b.division || '';
      stage.appendChild(st); stage.appendChild(cat);

      var vs = document.createElement('span');
      vs.style.cssText = 'color:#5c6175;text-align:center;';
      vs.textContent = 'vs';

      row.appendChild(no);
      row.appendChild(stage);
      // Blue is mirrored so the two portraits meet either side of the "vs" —
      // the same order the board and the wall put the corners in.
      row.appendChild(competitorCell(b.blue, '#1362d1', '#8ab4ff', true));
      row.appendChild(vs);
      row.appendChild(competitorCell(b.white, '#aab6c6', '#f4f7fb', false));

      if (!b.runnable) {
        // Listed so the operator can see it coming, but not loadable: nobody
        // can be introduced as "the winner of bout 3".
        row.style.opacity = '.45';
        row.style.cursor = 'not-allowed';
        row.disabled = true;
        row.title = T.waiting_feeder;
        host.appendChild(row);
        return;
      }

      row.onclick = function () { guarded(function () { send('load', { match_id: b.id }); }); };
      host.appendChild(row);
    });
  }

  /* ── The desktop table's own controls ──────────────────────────────────
     Present only on the karate-shaped desktop column; the tablet console has
     none of these ids, so every one of them is guarded and this whole block is
     a no-op there. */

  /** The introduction/scoreboard toggle, labelled for what it will do next. */
  function paintBoardToggle() {
    var b = el('btnBoard');
    if (!b) return;

    var showingIntro = S.mode === 'vs';
    b.textContent = showingIntro ? T.show_board : T.show_intro;

    // Two states, two HUES — not one colour filled and unfilled. This button is
    // read at a glance while an official is looking at the mat, and "solid blue
    // or outlined blue" is a difference you have to stop and study. Gold is the
    // introduction (the ceremony the hall is watching), blue is the scoreboard.
    // It is always painted as the thing it will SWITCH TO, which is also what
    // its label says.
    b.style.background = showingIntro ? '#8ab4ff' : '#12141d';
    b.style.color = showingIntro ? '#000' : '#ffe135';
    b.style.borderColor = showingIntro ? '#8ab4ff' : '#ffe135';
  }

  /* ── Mat settings ───────────────────────────────────────────────────────
     What the panel SHOWS is not what the mat is running until Apply: a switch
     flipped and then thought better of should cost nothing. */
  var FLAGS = ['referee_decision', 'time_up_buzzer'];
  var draft = null;

  function draftFromState() {
    var r = S.rules || {};
    var d = {};
    FLAGS.forEach(function (k) { d[k] = r[k] !== false; });
    d.penalty_limit = r.penalty_limit || 4;
    d.penalty_warn_at = r.penalty_warn_at || 3;
    d.stall_seconds = r.stall_seconds || 10;
    return d;
  }

  function num(id, min, max, fallback) {
    var e = el(id);
    var v = e ? parseInt(e.value, 10) : NaN;
    return isNaN(v) ? fallback : Math.max(min, Math.min(max, v));
  }

  function paintRules() {
    if (!el('settings')) return;
    if (!draft) draft = draftFromState();

    document.querySelectorAll('.rrow[data-rule]').forEach(function (row) {
      var box = row.querySelector('.rbox');
      if (!box) return;
      var on = !!draft[row.getAttribute('data-rule')];
      box.style.background = on ? '#ffe135' : '#1f2230';
      box.style.border = '1px solid ' + (on ? 'transparent' : '#2a2e40');
      box.textContent = on ? '✓' : '';
    });

    [['penaltyLimit', 'penalty_limit'], ['penaltyWarnAt', 'penalty_warn_at'],
     ['stallSeconds', 'stall_seconds']].forEach(function (pair) {
      var e = el(pair[0]);
      if (e && document.activeElement !== e) e.value = draft[pair[1]];
    });
  }

  function paintTimerFields() {
    if (!el('durMin')) return;
    var dur = Math.round(S.duration || 300);
    var warn = Math.round((S.rules || {}).warning || 0);
    if (document.activeElement !== el('durMin')) el('durMin').value = Math.floor(dur / 60);
    if (document.activeElement !== el('durSec')) el('durSec').value = dur % 60;
    if (document.activeElement !== el('warnMin')) el('warnMin').value = Math.floor(warn / 60);
    if (document.activeElement !== el('warnSec')) el('warnSec').value = warn % 60;
  }

  function paintTabs(which) {
    // Delegated, because these rows are rendered by Blade and never rebuilt.
  document.addEventListener('change', function (e) {
    var pick = e.target.closest ? e.target.closest('.audioPick') : null;
    if (pick) uploadAudio(pick.getAttribute('data-slot'), pick);
  });

  document.addEventListener('click', function (e) {
    var drop = e.target.closest ? e.target.closest('.audioDrop') : null;
    if (drop) dropAudio(drop.getAttribute('data-slot'));
  });

  paintAudio();

  document.querySelectorAll('.stab').forEach(function (t) {
      var on = t.getAttribute('data-tab') === which;
      t.style.background = on ? '#ffe135' : '#12141d';
      t.style.color = on ? '#000' : '#7d8296';
      t.style.borderColor = on ? 'transparent' : '#2a2e40';
    });
    document.querySelectorAll('.spanel').forEach(function (p) {
      p.hidden = p.getAttribute('data-panel') !== which;
    });

    // Apply belongs to the rules and the timer. The camera panel writes its own
    // changes as they are made, so offering to "apply" there would offer to
    // apply something the reader is not looking at.
    var apply = el('btnApply');
    if (apply) {
      apply.hidden = which === 'cameras';
      if (apply.nextElementSibling) apply.nextElementSibling.hidden = which === 'cameras';
    }
  }

  function matchSeconds() { return Math.max(30, num('durMin', 0, 59, 5) * 60 + num('durSec', 0, 59, 0)); }
  function warningSeconds() { return num('warnMin', 0, 59, 1) * 60 + num('warnSec', 0, 59, 0); }

  /* ── The event's sounds ─────────────────────────────────────────────────
     Rows rendered by Blade and never rebuilt, so the handlers are DELEGATED off
     document and the painting only rewrites what one row says. Uploading saves
     immediately — there is nothing to Apply, because a file either reached the
     store or it did not. */
  function kb(bytes) {
    if (!bytes) return '';
    return bytes < 1024 * 1024
      ? Math.max(1, Math.round(bytes / 1024)) + ' KB'
      : (bytes / 1024 / 1024).toFixed(1) + ' MB';
  }

  /** Redraw one row from what we know is stored. */
  function paintAudioRow(slot) {
    var name = document.querySelector('.audioName[data-slot="' + slot + '"]');
    var drop = document.querySelector('.audioDrop[data-slot="' + slot + '"]');
    var have = AUDIO_SLOTS[slot];

    if (name) {
      // textContent, never innerHTML: this is a filename somebody chose.
      name.textContent = have
        ? (have.name || '') + (have.bytes ? ' · ' + kb(have.bytes) : '')
        : T.sound_none;
      name.style.color = have ? '#7ae582' : '#5c6175';
    }
    if (drop) drop.hidden = !have;
  }

  function paintAudio() {
    if (!document.querySelector('.audioName')) return;
    AUDIO_SLOT_LIST.forEach(paintAudioRow);
  }

  function uploadAudio(slot, input) {
    var file = input.files && input.files[0];
    if (!file) return;

    if (!AUDIO_BASE) { flash(T.sound_unavailable); input.value = ''; return; }

    var body = new FormData();
    body.append('file', file);

    fetch(AUDIO_BASE + encodeURIComponent(slot), {
      method: 'POST',
      headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
      credentials: 'same-origin',
      body: body,
    })
      .then(function (r) { return r.json().catch(function () { return {}; }); })
      .then(function (d) {
        if (!d.success) throw new Error(d.message || T.failed);
        AUDIO_SLOTS[slot] = { name: d.name, bytes: d.bytes };
        paintAudioRow(slot);
        flash(d.message);
      })
      .catch(function (err) { flash(err.message || T.failed); })
      // So picking the SAME file again still fires a change.
      .finally(function () { input.value = ''; });
  }

  function dropAudio(slot) {
    if (!AUDIO_BASE) return;

    fetch(AUDIO_BASE + encodeURIComponent(slot), {
      method: 'DELETE',
      headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
      credentials: 'same-origin',
    })
      .then(function (r) { return r.json().catch(function () { return {}; }); })
      .then(function (d) {
        if (!d.success) throw new Error(d.message || T.failed);
        delete AUDIO_SLOTS[slot];
        paintAudioRow(slot);
        flash(d.message);
      })
      .catch(function (err) { flash(err.message || T.failed); });
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
    paintOver();
    paintLog();
    paintQueue();
    paintBouts();
    paintBoardToggle();
    paintRules();
    paintTimerFields();
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

  // Scoring: no confirmation, a 400ms lockout, and nothing else. The entry is
  // already on the wall and already in the score log — which is where it is
  // undone, on its own row, if it has to be. Press and carry on watching.
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-cmd]');
    if (!b || b.disabled) return;

    guarded(function () {
      send(b.dataset.cmd, { side: b.dataset.side, source: b.dataset.source });
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

  /* One button, both directions. Which one it is comes from the STATE rather
     than from a flag this page keeps: a second console on the same mat, a
     reload, or the wall being switched by somebody else all have to leave this
     button telling the truth about what it will do next. */
  if (el('btnBoard')) el('btnBoard').addEventListener('click', function () {
    guarded(function () { send(S.mode === 'vs' ? 'board' : 'intro'); });
  });

  /* Forces every screen on this mat to start again. Changes nothing about the
     match — worst case a board that was fine blinks and comes back identical.
     Deliberately NOT guarded by `guarded()`: it is most needed when something
     is stuck. */
  if (el('btnResync')) el('btnResync').addEventListener('click', function () {
    var b = el('btnResync'), was = b.textContent;
    send('resync').then(function () {
      b.textContent = T.resynced;
      setTimeout(function () { b.textContent = was; }, 1500);
    });
  });

  if (el('btnSettings')) el('btnSettings').addEventListener('click', function () {
    draft = draftFromState();
    paintRules();
    paintTimerFields();
    paintAudio();
    paintTabs('rules');
    el('settings').hidden = false;
  });

  document.querySelectorAll('.stab').forEach(function (t) {
    t.addEventListener('click', function () { paintTabs(t.getAttribute('data-tab')); });
  });

  document.querySelectorAll('.rrow[data-rule]').forEach(function (row) {
    row.addEventListener('click', function () {
      if (!draft) draft = draftFromState();
      var k = row.getAttribute('data-rule');
      draft[k] = !draft[k];
      paintRules();
    });
  });

  [['penaltyLimit', 'penalty_limit', 1, 10], ['penaltyWarnAt', 'penalty_warn_at', 1, 10],
   ['stallSeconds', 'stall_seconds', 3, 60]].forEach(function (spec) {
    var e = el(spec[0]);
    if (!e) return;
    e.addEventListener('change', function () {
      if (!draft) draft = draftFromState();
      draft[spec[1]] = num(spec[0], spec[2], spec[3], draft[spec[1]]);
      e.value = draft[spec[1]];
    });
  });

  /* Apply: the rules first, then the clock only if the length actually
     changed — `send` is single-flight, so these are chained rather than fired
     together, and re-setting a duration nobody touched would reset a running
     clock mid-match. */
  if (el('btnApply')) el('btnApply').addEventListener('click', function () {
    if (!draft) draft = draftFromState();

    var wantSeconds = matchSeconds();
    var changedDuration = Math.round(S.duration || 0) !== wantSeconds;

    var rules = {
      warning: warningSeconds(),
      penalty_limit: draft.penalty_limit,
      penalty_warn_at: Math.min(draft.penalty_warn_at, draft.penalty_limit),
      stall_seconds: draft.stall_seconds,
    };
    FLAGS.forEach(function (k) { rules[k] = !!draft[k]; });

    send('rules', rules).then(function () {
      if (!changedDuration) { el('settings').hidden = true; return; }
      // The warning travels again with the new length: the server clamps it to
      // the match, and a length change can be what put it out of range.
      send('duration', { seconds: wantSeconds }).then(function () {
        send('rules', { warning: warningSeconds() }).then(function () {
          el('settings').hidden = true;
        });
      });
    });
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
  //
  // The winner opens on whoever the SCORE says is ahead, because that is the
  // answer in most bouts and an official confirming one tap is far less likely
  // to leave it blank than one filling it in. It is still a choice — a
  // submission is routinely won by the corner that is behind on points.
  if (el('btnEnd')) el('btnEnd').addEventListener('click', function () {
    modal(T.end, [
      { name: 'winner', type: 'choice', label: T.winner, options: { blue: T.blue, white: T.white },
        value: (S.score || {}).leader || null },
      { name: 'method', type: 'choice', label: T.method, options: METHODS, value: 'points' },
      { name: 'note', type: 'text', label: T.reason }
    ], function (out) {
      // A method that names somebody, with nobody named. Left to the server
      // this used to come back as a POINTS win — the method silently dropped —
      // so it is caught here, the dialog stays open, and the official is told
      // what is missing. See Scoring::METHODS_NEEDING_WINNER.
      if (!out.winner && METHODS_NEEDING_WINNER.indexOf(out.method) >= 0) {
        flash(T.winner_required);
        return;
      }

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

  /* ── The live link ──────────────────────────────────────────────────────
     The console listens on the MAT's topic (ScreenChannel::matTopic), which is
     what makes two consoles at one mat possible: the laptop at the table and
     the tablet in the referee's hand hear the same messages, so a point scored
     on one appears on the other at once instead of whenever somebody next
     presses something. A mat with no wall screen has a live link now too — the
     old wiring only ever reached devices.

     partials/screen-link is the client, and it is included only when the server
     handed this page a credential. It reads `pinned` to decide what to do with
     each message: 'console' takes bout state straight off a `mat` push, and
     answers a running-order push by re-reading `console_url` — because a board
     payload is not the shape this page draws. */
  window.CourtBoard = {
    /**
     * Absorb an update, whichever of the two shapes it arrives in.
     *
     * A `mat` push carries the state ALONE — one object, the same one present()
     * returns — because that is the cheap message sent on every keypress.
     * A re-read carries the whole console: state, log, queue and the stalling
     * count. Told apart by shape rather than by which call produced them, so
     * there is one way in and no second path that could disagree with send().
     */
    update: function (p) {
      if (!p) return;

      if (p.state) {
        S = p.state;
        if (p.log) LOG = p.log;
        if (p.queue) QUEUE = p.queue;
        if (p.hasOwnProperty('stall')) stall = p.stall;
      } else if (p.mode) {
        S = p;
      } else {
        return;                       // not a shape this page knows
      }

      recvAt = Date.now();
      paint();
    },
    mode: function () { return S.mode || 'upcoming'; },
    pinned: 'console',
    stale: function () {}
  };
})();
</script>
