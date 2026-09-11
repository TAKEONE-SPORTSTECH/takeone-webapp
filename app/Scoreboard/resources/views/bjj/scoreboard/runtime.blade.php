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
        'bouts_hint' => __('scoreboard::bjj_messages.ctl_bouts_hint'),
        'bout_done' => __('scoreboard::bjj_messages.ctl_bout_done'),
        'no_bouts' => __('scoreboard::bjj_messages.ctl_no_bouts'),
        'find_none' => __('scoreboard::bjj_messages.ctl_find_none'),
        'find_hint' => __('scoreboard::bjj_messages.ctl_find_hint'),
        'reading_draw' => __('scoreboard::bjj_messages.ctl_reading_draw'),
        'draw_failed' => __('scoreboard::bjj_messages.ctl_draw_failed'),
        'arcade_hint' => __('scoreboard::bjj_messages.ctl_arcade_hint'),
        'arc_none' => __('scoreboard::bjj_messages.ctl_arc_none'),
        'arc_done' => __('scoreboard::bjj_messages.ctl_arc_done'),
        'arc_found' => __('scoreboard::bjj_messages.ctl_arc_found'),
        'arc_swapped' => __('scoreboard::bjj_messages.ctl_arc_swapped'),
        'arc_same' => __('scoreboard::bjj_messages.ctl_arc_same'),
        'waiting_feeder' => __('scoreboard::bjj_messages.ctl_waiting_feeder'),
        'start' => __('scoreboard::bjj_messages.ctl_start'),
        'pause' => __('scoreboard::bjj_messages.ctl_pause'),
        'resume' => __('scoreboard::bjj_messages.ctl_resume'),
        'stall' => __('scoreboard::bjj_messages.ctl_stall'),
        'stall_threshold' => __('scoreboard::bjj_messages.ctl_stall_threshold'),
        'stall_prompt' => __('scoreboard::bjj_messages.ctl_stall_prompt'),
        'stall_penalty_to' => __('scoreboard::bjj_messages.ctl_stall_penalty_to'),
        'stall_dismiss' => __('scoreboard::bjj_messages.ctl_stall_dismiss'),
        'stall_award_to' => __('scoreboard::bjj_messages.ctl_stall_award_to'),
        'end_time_up' => __('scoreboard::bjj_messages.ctl_end_time_up'),
        'end_now' => __('scoreboard::bjj_messages.ctl_end_now'),
        'decided_points' => __('scoreboard::bjj_messages.decided_by_points'),
        'decided_advantages' => __('scoreboard::bjj_messages.decided_by_advantages'),
        'decided_penalties' => __('scoreboard::bjj_messages.decided_by_penalties'),
        'award' => __('scoreboard::bjj_messages.ctl_award'),
        'correct' => __('scoreboard::bjj_messages.ctl_correct'),
        'points' => __('scoreboard::bjj_messages.source_points'),
        // The pick card's corner label, reused by the result card so the two
        // say the same words about the same man.
        'end_corner' => __('scoreboard::bjj_messages.ctl_end_corner', ['corner' => ':corner']),
        'adv_short' => __('scoreboard::bjj_messages.adv_short'),
        'pen_short' => __('scoreboard::bjj_messages.pen_short'),
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
  /* The whole draw, for the bouts card's three browsing tabs. Null on a door
     that did not pass one — the tabs then say so rather than failing silently.
     Read-only; nothing in this file writes to it. */
  var CATALOGUE_URL = @json($catalogueUrl ?? null);
  var AUDIO_SLOTS = @json($audioSlots ?? []);
  var AUDIO_SLOT_LIST = @json(\App\Events\Support\ScreenMedia::SLOTS);
  var CSRF = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

  var SOURCES = @json($sourceWords);
  /* Sources that appear in the RECORD but are never a button. SOURCES is
     walked to build the six scoring controls (buildScoreButtons), so a
     stalling award cannot live in it — it would become a seventh button in
     both corners. It still has to read as words in the score log. */
  var LOG_SOURCES = { stalling_award: @json(__('scoreboard::bjj_messages.source_stalling_award')) };
  var STALL_AWARDS = @json(\App\Scoreboard\Sports\BrazilianJiuJitsu\Mat\Ledger::STALL_AWARDS);
  /* The amounts the scoring grid offers, from the server's own list. The grid
     is built from THIS rather than from SOURCES: three buttons reading "+2" is
     a row an official has to read instead of hit, and the table also needs to
     take a score back at the same reach. See Ledger::POINT_VALUES. */
  var POINT_VALUES = @json(\App\Scoreboard\Sports\BrazilianJiuJitsu\Mat\Ledger::POINT_VALUES);
  var PENALTIES = @json($penaltyWords);
  var METHODS = @json($methodWords);

  /* Mirrors Scoring::METHODS_NEEDING_WINNER. The server refuses these without a
     winner; the console refuses them a step earlier so the official fixes it in
     the dialog they are already looking at rather than reading it back as an
     error. The server is still the one that decides — this only saves a round
     trip and a moment of confusion at a mat. */
  var METHODS_NEEDING_WINNER = ['submission', 'decision', 'dq', 'walkover', 'medical', 'forfeit', 'advantages'];
  var STATUS = @json($statusWords);
  var T = @json($consoleWords);

  var MAT = @json($court);
  var SCREENS = @json($screens);

  var S = @json($state);
  var LOG = @json($log);
  var QUEUE = @json($queue);
  var recvAt = Date.now();
  var stall = null;         // {side, until, seconds} — private to this console
  /* WHEN the stall payload above arrived, by this machine's clock. The count is
     drawn from `stall.seconds` measured against THIS, never from `until`
     against the wall clock — see setStall(). */
  var stallAt = 0;
  /* The `stallAt` of the double stall this console has already applied, so the
     automatic apply fires once per count rather than five times a second. */
  var stallAppliedFor = null;
  var ac = null;            // the console's own beeper, built on a real tap
  var beepedAt = null;      // the last whole second of a stall already beeped
  var bellBeepedFor = null; // the match whose final second has already beeped
  var locked = false;       // the 400ms one-tap-one-event lockout
  var overShownFor = null;  // the match whose end panel has already been offered
  var endPick = null;       // the corner the Match result card has chosen
  var endMethod = 'points'; // and how it says the bout was won
  var bellSentFor = null;   // the match whose expiry has already been reported

  function el(id) { return document.getElementById(id); }
  function text(id, v) { var e = el(id); if (e) e.textContent = v == null ? '' : v; }

  /* ── Talking to the server ─────────────────────────────────────────────
     One function, because there is one contract. Nothing else in this file
     writes anything. */
  /*
   * `quiet` suppresses the toast on a REFUSAL — never the refusal itself, and
   * never the state that rides back with it. For commands this console sends
   * on its own initiative rather than because somebody pressed something: two
   * consoles at one mat both notice a double stall run out, both send the
   * apply, and the second is correctly told there is no count running. That is
   * the system working, and it is not a sentence to put in front of an
   * official mid-bout.
   */
  function send(command, payload, done, quiet) {
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
        if (res.body.hasOwnProperty('stall')) setStall(res.body.stall);

        // The three that MOVE the draw — which bout is on the mat, and which
        // ones have been run. The cached catalogue behind the other three
        // tabs is now wrong; see catStale().
        if (res.ok && ['load', 'commit', 'clear'].indexOf(command) >= 0) catStale();

        paint();

        if (!res.ok) { if (!quiet) flash(res.body.message || ''); return null; }
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

  /* ── The console's own beeper ──────────────────────────────────────────
     The draft's (drafts/Score Control Board.html), verbatim: a short square
     blip each second while a stalling count runs, and a louder, longer one on
     its last second and on the bout clock's. No audio file — it is two
     oscillator nodes, so there is nothing to upload, cache or lose, and it is
     the console's own sound rather than the hall's (the wall board has its own
     slots for what the room hears).

     ⚠️ Built lazily on a real tap and NEVER before. A browser refuses an
     AudioContext created outside a user gesture, and an Android WebView refuses
     it more strictly than a desktop browser does — so this is called from the
     handlers an official actually presses (Stalling, and the transport button).
     The consequence is deliberate and worth keeping: a console nobody has
     touched is silent, so a second table at the same mat does not beep along
     with the first until somebody has used it. */
  function ensureAudio() {
    try {
      var Ctx = window.AudioContext || window.webkitAudioContext;
      if (!Ctx) return;
      if (!ac) ac = new Ctx();
      if (ac.state === 'suspended') ac.resume();
    } catch (e) { ac = null; }
  }

  function tickSound(final) {
    if (!ac) return;
    try {
      var t = ac.currentTime, o = ac.createOscillator(), g = ac.createGain();
      o.type = 'square';
      o.frequency.value = final ? 1400 : 1000;
      g.gain.setValueAtTime(0.0001, t);
      g.gain.exponentialRampToValueAtTime(final ? 0.25 : 0.12, t + 0.005);
      g.gain.exponentialRampToValueAtTime(0.0001, t + (final ? 0.25 : 0.06));
      o.connect(g); g.connect(ac.destination);
      o.start(t); o.stop(t + (final ? 0.3 : 0.08));
    } catch (e) { /* a beep is never worth an exception at a mat */ }
  }

  /* ── Painting ─────────────────────────────────────────────────────────── */
  function remaining() {
    return S.running ? Math.max(0, S.remaining - (Date.now() - recvAt) / 1000) : S.remaining;
  }

  function clock(s) {
    s = Math.max(0, Math.ceil(s));
    return Math.floor(s / 60) + ':' + ('0' + (s % 60)).slice(-2);
  }

  /*
   * The referee's count — set in ONE place, and anchored to when it ARRIVED.
   *
   * ── Why not `until - Date.now()` ───────────────────────────────────────
   *
   * Because that is a subtraction between two different clocks. A tablet at a
   * mat that has drifted a second and a half ahead of the server started a
   * five second count at three, and blipped twice on the way in: the payload
   * landed mid-second, so the first number it drew was already almost expired
   * and gave way ~200ms later, two beeps back to back. Both complaints — "it
   * skips a second or two at the start" and "I hear a double sound" — were
   * that one subtraction.
   *
   * `stall.seconds` is how much is left as the SERVER measured it, so the only
   * clock involved is this machine's own, measuring elapsed time from arrival.
   * Exactly what the bout clock does with `S.remaining` and `recvAt`, and for
   * exactly the same reason. Every fresh payload re-anchors, which is correct:
   * the server recomputes the remainder each time it answers.
   *
   * `until` is still honoured as a fallback, so a payload from something that
   * has not been taught the new field still counts down rather than stopping.
   */
  function setStall(v) {
    stall = v || null;
    stallAt = Date.now();
  }

  /** Whole seconds left on the count, or null when none is running. */
  function stallLeft() {
    if (!stall) return null;

    if (typeof stall.seconds === 'number') {
      return Math.max(0, Math.ceil(stall.seconds - (Date.now() - stallAt) / 1000));
    }

    return stall.until
      ? Math.max(0, Math.ceil((new Date(stall.until).getTime() - Date.now()) / 1000))
      : null;
  }

  /*
   * The scoring grid: three rows of two — +2/-2, +3/-3, +4/-4.
   *
   * Two columns, two jobs, and the row is the amount: AWARD on the leading
   * edge, CORRECT on the trailing one. The grid is a 2-column CSS grid filled
   * in DOM order, so each amount is appended as a pair — give, then take back
   * — and BOTH corners get the same markup. The white corner reverses it in
   * CSS along with every other row on that side (`.corner.mirror`), so this
   * function never has to know which corner it is building.
   *
   * ⚠️ Neither button carries a price. The + posts the AMOUNT and the server
   * checks it against Ledger::POINT_VALUES; the - posts the same amount as a
   * `deduct` and the server decides whether that reverses a standing score or
   * writes a correction. This console still cannot mint a number.
   */
  function buildScoreButtons() {
    ['blue', 'white'].forEach(function (side) {
      var host = el(side + 'ScoreGrid');
      if (!host || host.dataset.built) return;
      host.dataset.built = '1';

      POINT_VALUES.forEach(function (value) {
        [['point', '+', T.award, ''], ['deduct', '\u2212', T.correct, ' minus']].forEach(function (col) {
          var b = document.createElement('button');
          b.className = 'btn score' + col[3];
          b.dataset.cmd = col[0]; b.dataset.side = side; b.dataset.value = value;
          // The amount is the headline because it is what an official is
          // thinking in; the word under it says which of the two things this
          // press does, because +2 and -2 differ by one glyph.
          b.innerHTML = '<span class="v">' + col[1] + value + '</span>';
          var l = document.createElement('span'); l.className = 'l'; l.textContent = col[2];
          b.appendChild(l);
          host.appendChild(b);
        });
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

    /* The clock reaching zero, once per match — the one warning an official
       gets while looking at the mat rather than at the console. AT ZERO, by the
       same rule as the stalling count above: the sound belongs to the thing
       having happened, not to the second before it. The hall's buzzer is a
       separate thing, played by the board off its own clock and its own audio
       slot. */
    if (S.matchId && r <= 0 && bellBeepedFor !== S.matchId) {
      bellBeepedFor = S.matchId;
      tickSound(true);
    }
    if (r > 0) bellBeepedFor = null;
    var warn = S.status === 'live' && r > 0 && r <= ((S.rules && S.rules.warning) || 60);
    var v = el('clockVal');
    if (v) { v.textContent = clock(r); v.className = 'num' + (warn ? ' warn' : ''); }
    text('clockState', STATUS[S.status] || '');

    /* The state word takes the state's OWN colour — green while it runs, gold
       while it waits, alarm when the time is gone — because "RUNNING" and
       "PAUSED" in the same ink are two words an official has to stop and read.
       Guarded, like everything in this block: a console without the element is
       untouched, and one whose stylesheet colours it itself simply has this
       written over the top of an identical value. */
    var st = el('clockState');
    if (st) st.style.color = r <= 0 ? 'var(--alarm)' : S.running ? 'var(--green)' : 'var(--gold)';

    /* The bar under the digits: how much of the bout is left, in one glance
       from across the table. Measured against the match's OWN duration, so a
       mat running four-minute bouts is not drawn against five. */
    var bar = el('clockBar');
    if (bar) {
      var dur = S.duration || 0;
      bar.style.width = (dur > 0 ? Math.max(0, Math.min(100, r / dur * 100)) : 0) + '%';
      bar.style.background = (r <= 0 || warn) ? 'var(--alarm)' : S.running ? 'var(--green)' : 'var(--gold)';
    }

    var dot = el('liveDot');
    if (dot) dot.className = S.status === 'live' ? 'on' : '';
    text('liveText', STATUS[S.status] || '');

    // The referee's own count, on this console and nowhere else.
    var left = stallLeft();

    if (left !== null) {
      text('stallCount', left);
      if (el('stallApply')) el('stallApply').disabled = false;
      if (el('stallCancel')) el('stallCancel').disabled = false;
      paintStall(stall.side, left);

      /* One blip per whole second, and the loud one AT ZERO — not at one.
         Zero is the moment the threshold card opens, so the sound and the
         question land together; a loud beep a second early announces a
         decision the referee has not been asked for yet.

         Keyed on the second itself rather than on a timer of this page's own:
         paintClock runs five times a second, and the count comes from the
         server's own remainder, so the only honest trigger is "this second has
         not been beeped yet". A reload mid-count therefore beeps from wherever
         it picked the count up, and never twice for the same second —
         including zero, which stays on screen until the referee answers.

         This is only true because stallLeft() counts from ARRIVAL. While it
         subtracted an absolute `until` from the local clock, the first number
         drawn was whatever was left of a second already running, so it expired
         within a couple of paints and blipped again immediately — one press,
         two sounds. See setStall(). */
      if (beepedAt !== left) {
        beepedAt = left;
        tickSound(left === 0);
      }

      /* A DOUBLE stall applies itself.
         Neither man was working, so there is nothing to ask: at zero both
         corners take a stalling penalty. Sent from here for the same reason
         `bell` is — the server has no timer, so the only thing that knows the
         count has run out is whatever is watching it, and this console is
         watching it five times a second.

         Guarded on the count's own arrival stamp so it is sent ONCE per count
         and never again for the same one. A second console at the same mat
         will send it too and be refused with "no stalling count is running",
         because the first apply cleared the stall — which is the right answer
         and costs nothing. flash() is skipped for that one case: a refusal
         nobody caused is not a message anybody needs. */
      if (stall && stall.side === 'both' && left === 0 && stallAppliedFor !== stallAt) {
        stallAppliedFor = stallAt;
        send('stall', { phase: 'apply' }, null, true);
      }
    } else {
      beepedAt = null;
      text('stallCount', '—');
      if (el('stallApply')) el('stallApply').disabled = true;
      if (el('stallCancel')) el('stallCancel').disabled = true;
      paintStall(null, null);
    }
  }

  /* ── The stalling countdown, in the corner it is against ────────────────
     The draft's treatment (drafts/Score Control Board.html), adopted 2026-09-10:
     the count runs INSIDE the Stalling button of the corner it is against, the
     button glows and the number pumps while it runs, and when it reaches zero a
     card appears on its own and asks the referee once.

     Which corner is not a guess — `stall.side` comes off the console's private
     stall payload, so a second console at the same mat, and this one after a
     reload, both light the same button. There is only ever ONE count: starting
     one on the other corner replaces it, because that is what the server
     stores.

     Every write is guarded. A host without these elements (the react island,
     the retired thumb console) gets a no-op, exactly as it did before. */
  function paintStall(side, left) {
    var both = side === 'both';

    // A double stall runs against the pair, so BOTH corner buttons glow and
    // both carry the number — the count is one thing, shown wherever the
    // referee happens to be looking.
    ['blue', 'white'].forEach(function (k) {
      var btn = document.querySelector('[data-stall="' + k + '"]');
      if (btn) btn.classList.toggle('stalling', both || k === side);
      // Blanked rather than dashed on the corner it is NOT against: a number
      // beside the other man's name is a number an official has to discount.
      text(k + 'StallCount', (both || k === side) && left != null ? left : '');
    });

    var bothBtn = document.querySelector('[data-stall="both"]');
    if (bothBtn) bothBtn.classList.toggle('stalling', both);
    text('bothStallCount', both && left != null ? left : '');

    var p = el('stallPrompt');
    if (!p) return;

    // Asked from the STATE, not from a timer this page keeps: the count has run
    // out for as long as the server still holds the stall, so dismissing it has
    // to cancel the count or the card would simply come back.
    //
    // ⚠️ Never for a DOUBLE stall. That one has no question in it: neither man
    // was working, so both are penalised and the count applies itself the
    // moment it runs out (see the trigger in paintClock). A card asking which
    // of the two ways out the referee wants would be a card with one button on
    // it, in front of a referee who has already decided.
    var due = !both && !!side && left === 0;
    p.hidden = !due;
    if (!due) return;

    var corner = side === 'blue' ? T.blue : T.white;
    var other = side === 'blue' ? T.white : T.blue;
    text('stallPromptTitle', (T.stall_prompt || '').replace(':corner', corner));
    text('stallPromptApply', (T.stall_penalty_to || '').replace(':corner', corner));
    // The award goes to the corner that was BEING stalled against, so the label
    // names the other man. The server decides that too — this only says so.
    text('stallPromptAwardTo', (T.stall_award_to || '').replace(':corner', other));
    var accent = el('stallPromptAccent');
    if (accent) accent.style.background = side === 'blue' ? 'var(--blue-plate)' : 'var(--white)';
  }

  function paintCorners() {
    var sc = S.score || {};

    [['blue', S.blue || {}], ['white', S.white || {}]].forEach(function (pair) {
      var k = pair[0], c = pair[1];
      text(k + 'Name', (c.name || T.tbd).toUpperCase());
      text(k + 'Score', sc[k + 'Points'] || 0);
      text(k + 'Adv', sc[k + 'Advantages'] || 0);
      text(k + 'Pen', sc[k + 'Penalties'] || 0);

      /* The rest of the corner's plate, which the console's layout reads the
         way the wall's introduction does: the club they compete FOR, its crest
         and the flag of that club's country — never their own nationality. A
         crest is a transparent PNG on a bare sizing box (Design Rule #5) and
         both it and the flag are HIDDEN when there is nothing to show, because
         most clubs at a real competition are written down on the day.
         Guarded: the tablet console has none of these ids and this is a no-op
         there. */
      text(k + 'Club', c.club || '');

      var fl = el(k + 'Flag');
      if (fl) {
        var flag = flagUrl(c.flag);
        fl.hidden = flag === 'none';
        fl.style.backgroundImage = flag;
      }

      var lg = el(k + 'Logo');
      if (lg) {
        lg.hidden = !c.logo;
        lg.style.backgroundImage = c.logo ? 'url("' + c.logo + '")' : 'none';
      }

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

    var side = S.winner === 'blue' || S.winner === 'white' ? S.winner : null;
    var win = side ? ((side === 'blue' ? S.blue : S.white) || {}) : null;
    var sc = S.score || {};

    /* The winner, on the SAME card the end dialog picks him with — the face,
       the flag, the club and the numbers in the same places, so confirming a
       result is a recognition rather than a second reading.
       Its colour is the winner's and is set here: the two pick cards bake
       theirs per side in Blade, but this one card has to be either. */
    var card = el('overCard');
    var none = el('overNone');

    if (card) card.hidden = !win;
    if (none) { none.hidden = !!win; text('overNone', win ? '' : T.over_undecided); }

    if (win) {
      var colour = side === 'blue' ? 'var(--blue)' : 'var(--white)';
      var ink = side === 'blue' ? 'var(--blue-ink)' : 'var(--white)';

      if (card) {
        card.style.background = 'linear-gradient(140deg,color-mix(in srgb, ' + colour +
          ' 22%, transparent),#0a0b10 60%)';
        card.style.border = '1px solid color-mix(in srgb, ' + colour + ' 45%, transparent)';
      }

      var ph = el('overPhoto');
      if (ph) ph.style.border = '2px solid ' + colour;

      var cn = el('overCorner');
      if (cn) {
        cn.style.color = ink;
        cn.textContent = (T.end_corner || '').replace(':corner', side === 'blue' ? T.blue : T.white);
      }

      text('overName', (win.name || T.tbd).toUpperCase());
      text('overClub', win.club || '');
      text('overScore', sc[side + 'Points'] || 0);
      // Spelled out rather than bare numbers, exactly as on the pick card: this
      // is read once, under pressure, and "3" beside "1" is two guesses.
      text('overAdv', T.adv_short + ' ' + (sc[side + 'Advantages'] || 0));
      text('overPen', T.pen_short + ' ' + (sc[side + 'Penalties'] || 0));

      // Their own face, then the drawn stand-in the server only sends when the
      // gender is on file, then nothing — the pick card's own rule.
      var photo = el('overPhoto');
      if (photo) {
        var face = win.photo || win.fallback;
        photo.style.backgroundImage = face ? 'url("' + face + '")' : 'none';
        photo.style.opacity = face && !win.photo ? '.72' : '1';
      }

      var fl = el('overFlag');
      if (fl) {
        var flag = flagUrl(win.flag);
        fl.hidden = flag === 'none';
        fl.style.backgroundImage = flag;
      }

      var lg = el('overLogo');
      if (lg) {
        lg.hidden = !win.logo;
        lg.style.backgroundImage = win.logo ? 'url("' + win.logo + '")' : 'none';
      }
    }

    // HOW it was won, in the card's note slot.
    text('overHow', win
      ? [(S.winMethod ? (METHODS[S.winMethod] || S.winMethod) : null), S.winNote || null]
          .filter(Boolean).join(' · ')
      : '');

    /* Both totals, each NAMED and each in its corner's own ink.
       "4 — 7" on its own does not say whose 4 it is, and on a card that can
       show either corner there is nothing to infer it from. Worse on a
       disqualification, which hands the bout to the man with FEWER points:
       the card was showing a winner with 4 against 7 and no word anywhere
       saying he had won. */
    ['a', 'b'].forEach(function (slot) {
      var k = slot === 'a' ? 'blue' : 'white';
      var box = el('overTotals' + slot.toUpperCase());
      if (!box) return;

      box.innerHTML = '';
      if (!win) return;

      var name = document.createElement('span');
      name.textContent = k === 'blue' ? T.blue : T.white;
      name.style.cssText = 'font-family:inherit;font-size:14px;letter-spacing:.16em;' +
        'text-transform:uppercase;color:var(--faint);';

      var n = document.createElement('span');
      n.textContent = sc[k + 'Points'] || 0;
      // The winner's number in the card's own ink, the other muted: the pair
      // then reads as a result rather than as two numbers side by side.
      n.style.color = k === side
        ? (k === 'blue' ? 'var(--blue-ink)' : 'var(--white)')
        : 'var(--muted)';

      box.append(name, n);
    });

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

  /* ── Finish: the Match result card ─────────────────────────────────────
     The two corners as the hall sees them, and the choice made by tapping the
     man rather than a chip with his colour on it. Repainted from the state on
     every pass, so a point scored on the other console while this card is open
     changes the scoreline the official is looking at rather than leaving them
     confirming a number that has moved.

     It does not open itself — Finish opens it. Everything here is guarded, so a
     console without the card is a no-op. */
  function paintEnd() {
    var panel = el('endPanel');
    if (!panel || panel.hidden) return;

    var sc = S.score || {};
    var r = remaining();

    text('endWhy', r <= 0 ? T.end_time_up : T.end_now);

    ['blue', 'white'].forEach(function (k) {
      var K = k.charAt(0).toUpperCase() + k.slice(1);
      var c = (k === 'blue' ? S.blue : S.white) || {};

      text('end' + K + 'Name', (c.name || T.tbd).toUpperCase());
      text('end' + K + 'Club', c.club || '');
      text('end' + K + 'Score', sc[k + 'Points'] || 0);
      // Spelled out rather than a bare number: this card is read once, under
      // pressure, and "3" beside "1" with no words is two guesses.
      text('end' + K + 'Adv', T.adv_short + ' ' + (sc[k + 'Advantages'] || 0));
      text('end' + K + 'Pen', T.pen_short + ' ' + (sc[k + 'Penalties'] || 0));

      // Their own face, then the drawn stand-in the server only sends when the
      // gender is on file, then nothing. The stand-in sits back a little so a
      // card of them never reads as a photograph.
      var photo = el('end' + K + 'Photo');
      if (photo) {
        var face = c.photo || c.fallback;
        photo.style.backgroundImage = face ? 'url("' + face + '")' : 'none';
        photo.style.opacity = face && !c.photo ? '.72' : '1';
      }

      var fl = el('end' + K + 'Flag');
      if (fl) {
        var flag = flagUrl(c.flag);
        fl.hidden = flag === 'none';
        fl.style.backgroundImage = flag;
      }

      var lg = el('end' + K + 'Logo');
      if (lg) {
        lg.hidden = !c.logo;
        lg.style.backgroundImage = c.logo ? 'url("' + c.logo + '")' : 'none';
      }

      // WHY this corner is ahead, in words, off the tally's own verdict — so
      // the tap is a confirmation rather than a guess. Never invented here: a
      // level bout says nothing on either side, which is the honest answer and
      // the moment the referee decides.
      var note = '';
      if (sc.leader === k && sc.decidedBy) {
        note = T['decided_' + sc.decidedBy] || '';
      }
      text('end' + K + 'Note', note);

      var card = el('end' + K + 'Card');
      if (card) card.classList.toggle('on', endPick === k);
    });

    document.querySelectorAll('[data-end-method]').forEach(function (b) {
      b.classList.toggle('on', b.getAttribute('data-end-method') === endMethod);
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
      chip.className = 'logChip ' + (['point', 'advantage', 'penalty', 'reverse', 'correction'].indexOf(row.action) >= 0 ? row.action : 'other');
      chip.textContent = row.action;

      var d = document.createElement('span'); d.className = 'd';
      d.textContent = [
        row.side ? (row.side === 'blue' ? T.blue : T.white) : null,
        // A correction is the one scoring row that TAKES, so it is signed the
        // other way. "+2" against a row that removed two points is how a score
        // log stops being checkable.
        row.value ? (row.action === 'correction' ? '−' : '+') + row.value : null,
        // A point given by AMOUNT has no action to name — it reads "POINTS", so
        // the row does not trail off after the number.
        row.source ? (SOURCES[row.source] ? SOURCES[row.source].label
          : (LOG_SOURCES[row.source] || PENALTIES[row.source] || row.source))
          : (row.action === 'point' ? T.points : null),
        row.reason || null,
        row.by || null
      ].filter(Boolean).join(' · ');

      line.append(ts, chip, d);

      /* Correcting past the toast: a 1s hold, so a stray tap on a busy table
         cannot rewrite a match. Releasing cancels it.

         EVERY row that moved the score carries it, an undo included — taking
         back a correction is an ordinary act at a mat ("no, that point was
         good"), and it used to be the one thing the log showed you and would
         not let you touch. The server decides in the end (Ledger::reversible);
         this only offers what it will accept.

         The rows with no button are the ones with nothing to take back: the
         match's own history — started, paused, the clock corrected, the names
         fixed, the rules changed, the result filed. Those are context, not
         score, and the ledger has no opposite for them. */
      if (!row.reversed && ['point', 'advantage', 'penalty', 'correction', 'reverse'].indexOf(row.action) >= 0) {
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

  /**
   * One bout, as every list on this console prints it.
   *
   * Extracted from paintBouts so the running order, a weight class, a member's
   * bouts and the arcade's answer are all the SAME row — the alternative was
   * four painters drifting apart, which is the trap CLAUDE.md's *Shared Stays
   * Shared* is about. The rows differ in one thing only: which list they are
   * in.
   *
   * `data-bout-load` is what tells the card to close when this row is pressed.
   * It is set only on a row that can actually load, so a row waiting on a
   * feeder does not shut the card the operator is still reading.
   */
  function boutRow(b) {
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
    // The mat, but only when the row can be on a mat this console is not at —
    // the running order is one mat by definition and repeating it on every row
    // is noise.
    cat.textContent = (b.division || '') + (b.court && b.court !== MAT ? ' · ' + b.court : '');
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

    // A bout already fought is listed and not loadable. Reloading it would put
    // a decided result back on a wall as an idle bout about to start.
    if (b.done) {
      row.style.opacity = '.5';
      row.style.cursor = 'not-allowed';
      row.disabled = true;
      row.title = T.bout_done;
      return row;
    }

    if (!b.runnable) {
      // Listed so the operator can see it coming, but not loadable: nobody
      // can be introduced as "the winner of bout 3".
      row.style.opacity = '.45';
      row.style.cursor = 'not-allowed';
      row.disabled = true;
      row.title = T.waiting_feeder;
      return row;
    }

    row.setAttribute('data-bout-load', b.id);
    row.onclick = function () { guarded(function () { send('load', { match_id: b.id }); }); };

    return row;
  }

  /** An empty list, said in words rather than left blank. */
  function boutsEmpty(host, message) {
    var empty = document.createElement('div');
    empty.style.cssText = 'font-size:21px;color:#5c6175;padding:16px 4px;';
    empty.textContent = message;
    host.appendChild(empty);
  }

  function paintBouts() {
    var host = el('boutsList');
    if (!host) return;
    host.textContent = '';

    if (!QUEUE.length) {
      boutsEmpty(host, T.no_queue);
      return;
    }

    QUEUE.forEach(function (b) { host.appendChild(boutRow(b)); });
  }

  /* ── Browsing the whole draw ────────────────────────────────────────────
     Three of the bouts card's four tabs. The running order above answers "what
     is next on this mat"; these answer the question an official at the table
     is actually asked when a competitor turns up early or out of order — which
     weight class, which person, or these two standing here.

     Read-only from end to end. The catalogue is a GET, it is fetched once per
     opening of the console, and pressing any row here is the same `load`
     command the running order sends — so nothing in this block can put a
     corner on a wall that the mat did not already have in its draw.

     The card owns which pane shows (see the tab bar in control.blade.php) and
     calls in through window.__bjjBoutsTab; everything drawn inside a pane is
     owned here, which is the division of labour this card has always had. */
  var CAT = null;          // {divisions, roster, bouts} — the draw, as last read
  var catState = 'idle';   // idle | loading | ready | failed
  var boutsTab = 'arranged';  // which pane of the bouts card is showing
  var divOpen = null;      // the weight class being read, if any
  var arcPick = { a: null, b: null };

  /** A corner, rebuilt from the roster the catalogue keyed once by entry id. */
  function catCorner(id, name) {
    var r = (id != null && CAT) ? CAT.roster[String(id)] : null;

    // A competitor the draw named but has no entry row for — written in on the
    // day — still has to read on the sheet, with their name and nothing else.
    if (!r) return { name: name || null };

    return { name: name || r.name, photo: r.photo, fallback: r.fallback,
             club: r.club, logo: r.logo, flag: r.flag };
  }

  /** A catalogue row in the shape boutRow draws. One row, four lists. */
  function catBout(b) {
    return { id: b.id, number: b.number, stage: b.stage, division: b.division,
             court: b.court, done: b.done, runnable: b.runnable,
             blue: catCorner(b.a, b.a_name), white: catCorner(b.b, b.b_name) };
  }

  /** Read the draw, once, and then paint whichever tab asked for it. */
  function catLoad(then) {
    if (catState === 'ready') { if (then) then(); return; }
    if (catState === 'loading') return;

    if (!CATALOGUE_URL) { catState = 'failed'; if (then) then(); return; }

    catState = 'loading';
    if (then) then();

    fetch(CATALOGUE_URL, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (j) {
        if (!j || !j.bouts) { catState = 'failed'; if (then) then(); return; }
        CAT = j;
        catState = 'ready';
        if (then) then();
      })
      .catch(function () { catState = 'failed'; if (then) then(); });
  }

  /** The one line a pane shows while the draw is being read, or could not be. */
  function catNotReady(host) {
    if (catState === 'ready') return false;
    host.textContent = '';
    boutsEmpty(host, catState === 'failed' ? T.draw_failed : T.reading_draw);
    return true;
  }

  /* ── Weight class ─────────────────────────────────────────────────────── */

  function paintDivisions() {
    var host = el('divList');
    if (!host) return;

    // Coming back to the index closes whatever was open, so the tab is always
    // entered at the top of the list.
    if (divOpen === null) {
      var idx = el('divIndex'), det = el('divDetail');
      if (idx) idx.hidden = false;
      if (det) det.hidden = true;
    }

    var chip = el('btabDivisionCount');
    if (catNotReady(host)) {
      if (chip) chip.hidden = true;
      return;
    }

    host.textContent = '';

    if (chip) {
      chip.textContent = CAT.divisions.length;
      chip.hidden = CAT.divisions.length === 0;
    }

    if (!CAT.divisions.length) { boutsEmpty(host, T.no_bouts); return; }

    CAT.divisions.forEach(function (d) {
      var row = document.createElement('button');
      row.type = 'button';
      row.className = 'bdiv';

      var left = document.createElement('span');
      left.style.cssText = 'min-width:0;';
      var name = document.createElement('span');
      name.style.cssText = 'overflow-wrap:anywhere;';
      name.textContent = d.label;
      left.appendChild(name);

      // The division's own name too, when the label is a weight class and the
      // two are different words — the operator is looking for whichever of the
      // two the organiser wrote on their sheet.
      if (d.name && d.name !== d.label) {
        var sub = document.createElement('span');
        sub.className = 'sub';
        sub.textContent = d.name;
        left.appendChild(sub);
      }

      // Fought / total, because the question behind picking a class is how
      // much of it is left.
      var cnt = document.createElement('span');
      cnt.className = 'cnt';
      cnt.textContent = (d.bouts - d.done) + '/' + d.bouts;

      row.appendChild(left);
      row.appendChild(cnt);
      row.onclick = function () { openDivision(d); };
      host.appendChild(row);
    });
  }

  function openDivision(d) {
    divOpen = d.id;

    var idx = el('divIndex'), det = el('divDetail');
    if (idx) idx.hidden = true;
    if (det) det.hidden = false;
    text('divTitle', d.label);

    var host = el('divBouts');
    if (!host) return;
    host.textContent = '';

    var rows = CAT.bouts.filter(function (b) { return b.division_id === d.id; });
    if (!rows.length) { boutsEmpty(host, T.no_bouts); return; }

    rows.forEach(function (b) { host.appendChild(boutRow(catBout(b))); });
  }

  /* ── One member's bouts ───────────────────────────────────────────────── */

  /** Fold case for matching. Arabic has no case, so this is a no-op there. */
  function fold(v) { return (v == null ? '' : String(v)).toLowerCase(); }

  /** Every name a bout can be searched by: the draw's, and the roster's. */
  function boutNames(b) {
    var out = [];
    [[b.a, b.a_name], [b.b, b.b_name]].forEach(function (pair) {
      if (pair[1]) out.push(fold(pair[1]));
      var r = pair[0] != null && CAT ? CAT.roster[String(pair[0])] : null;
      if (r) {
        if (r.name) out.push(fold(r.name));
        // Their club too — "everyone from Victorian" is a question asked at a
        // table as often as a person's name is.
        if (r.club) out.push(fold(r.club));
      }
    });
    return out;
  }

  function paintFind() {
    var host = el('findBouts');
    var input = el('boutFind');
    if (!host || !input) return;

    if (catNotReady(host)) return;

    var q = fold(input.value).trim();
    host.textContent = '';

    if (q.length < 2) {
      text('findHint', T.find_hint);
      return;
    }

    var rows = CAT.bouts.filter(function (b) {
      return boutNames(b).some(function (n) { return n.indexOf(q) !== -1; });
    });

    text('findHint', rows.length ? T.bouts_hint : T.find_none);
    if (!rows.length) { boutsEmpty(host, T.find_none); return; }

    rows.forEach(function (b) { host.appendChild(boutRow(catBout(b))); });
  }

  /* ── Arcade ───────────────────────────────────────────────────────────── */

  /**
   * The roster, as two facing lists.
   *
   * Only entrants the draw has actually put in a bout appear — a character
   * select where half the faces can never fight is worse than a shorter list.
   * That is the catalogue's decision, not this one: it keys the roster off the
   * bouts.
   */
  function arcRoster() {
    if (!CAT) return [];

    return Object.keys(CAT.roster).map(function (id) {
      var r = CAT.roster[id];
      return { id: parseInt(id, 10), name: r.name || '', club: r.club || '',
               photo: r.photo, fallback: r.fallback, flag: r.flag, logo: r.logo };
    }).sort(function (x, y) { return x.name.localeCompare(y.name); });
  }

  function paintArcSide(side) {
    var host = el(side === 'a' ? 'arcListA' : 'arcListB');
    var input = el(side === 'a' ? 'arcFindA' : 'arcFindB');
    if (!host) return;

    if (catNotReady(host)) return;

    host.textContent = '';

    var q = fold(input ? input.value : '').trim();
    var people = arcRoster().filter(function (p) {
      return !q || fold(p.name).indexOf(q) !== -1 || fold(p.club).indexOf(q) !== -1;
    });

    if (!people.length) { boutsEmpty(host, T.find_none); return; }

    var ink = side === 'a' ? '#8ab4ff' : '#f4f7fb';
    var rim = side === 'a' ? '#1362d1' : '#aab6c6';

    people.forEach(function (p) {
      var row = document.createElement('button');
      row.type = 'button';
      row.className = 'arcrow';
      row.setAttribute('aria-pressed', arcPick[side] === p.id ? 'true' : 'false');
      if (arcPick[side] === p.id) row.style.borderColor = rim;

      // The same cell the bout rows use, so a face is the same face in both
      // lists — 3:4 portrait, the drawn stand-in when there is no photo, the
      // club's crest bare beside its name.
      row.appendChild(competitorCell(
        { name: p.name, photo: p.photo, fallback: p.fallback, club: p.club, logo: p.logo, flag: p.flag },
        rim, ink, false
      ));

      row.onclick = function () {
        // Pressing the chosen one again clears it, which is how somebody
        // corrects a mis-tap without hunting for a Clear button.
        arcPick[side] = arcPick[side] === p.id ? null : p.id;
        paintArcSide('a');
        paintArcSide('b');
        paintArcVerdict();
      };

      host.appendChild(row);
    });
  }

  /**
   * What the draw says about the pair.
   *
   * The console does not make a bout. It looks for the one the draw already
   * has between these two and offers to load it; when there is none it says
   * so plainly, because "no bout" is the answer, not a failure. An exhibition
   * would be a row written into a live event's draw, and a scoreboard is not
   * where that decision belongs.
   */
  function arcFound() {
    if (catState !== 'ready' || arcPick.a == null || arcPick.b == null) return null;
    if (arcPick.a === arcPick.b) return { same: true };

    var hits = CAT.bouts.filter(function (b) {
      return (b.a === arcPick.a && b.b === arcPick.b) || (b.a === arcPick.b && b.b === arcPick.a);
    });

    if (!hits.length) return { none: true };

    // An unfought bout wins over a decided one: two people can meet twice in a
    // round robin, and the one still to come is the one being asked about.
    var live = hits.filter(function (b) { return !b.done; });

    return live.length
      ? { bout: live[0], swapped: live[0].a !== arcPick.a }
      : { bout: hits[0], done: true };
  }

  function paintArcVerdict() {
    var btn = el('arcFight');
    var say = el('arcSay');
    var vs = el('arcVs');
    if (!btn) return;

    var f = arcFound();

    // The two names, so the operator can check their picks without looking
    // back at two scrolled lists.
    if (vs) {
      var a = arcPick.a != null && CAT ? CAT.roster[String(arcPick.a)] : null;
      var b = arcPick.b != null && CAT ? CAT.roster[String(arcPick.b)] : null;
      vs.textContent = (a && b) ? ((a.name || '') + ' · ' + (b.name || '')) : '';
    }

    btn.disabled = true;
    btn.removeAttribute('data-bout-load');
    btn.onclick = null;

    if (!f) { if (say) say.textContent = T.arcade_hint; return; }
    if (f.same) { if (say) say.textContent = T.arc_same; return; }
    if (f.none) { if (say) say.textContent = T.arc_none; return; }

    if (f.done) {
      if (say) say.textContent = T.arc_done.replace(':no', f.bout.number == null ? '—' : f.bout.number);
      return;
    }

    var line = T.arc_found
      .replace(':no', f.bout.number == null ? '—' : f.bout.number)
      .replace(':stage', f.bout.stage || f.bout.division || '');

    // A bout the draw is not ready to run is named and not offered — the same
    // rule the running order applies, for the same reason.
    if (!f.bout.runnable) {
      if (say) say.textContent = line + ' — ' + T.waiting_feeder;
      return;
    }

    if (say) say.textContent = f.swapped ? line + ' — ' + T.arc_swapped : line;

    btn.disabled = false;
    // Marked so the card closes on the load, exactly as a bout row does.
    btn.setAttribute('data-bout-load', f.bout.id);
    btn.onclick = function () { guarded(function () { send('load', { match_id: f.bout.id }); }); };
  }

  function paintArcade() {
    paintArcSide('a');
    paintArcSide('b');
    paintArcVerdict();
  }

  /* ── The door the card knocks on ──────────────────────────────────────── */

  /**
   * A tab became visible. Read the draw if it has not been read, then paint.
   *
   * Published on `window` because the tab bar lives in the page and the
   * painting lives here — the same seam the rest of this console uses. Guarded
   * on the other side, so a console rendered without these panes is fine.
   */
  window.__bjjBoutsTab = function (name) {
    boutsTab = name;

    if (name === 'arranged') { paintBouts(); return; }

    catLoad(catRepaint);
  };

  /** Redraw whichever catalogue-backed pane is showing. */
  function catRepaint() {
    if (boutsTab === 'division') { divOpen = null; paintDivisions(); }
    else if (boutsTab === 'member') paintFind();
    else if (boutsTab === 'arcade') paintArcade();
  }

  /*
   * The draw has MOVED — throw the cached copy away.
   *
   * ── The bug this fixes ─────────────────────────────────────────────────
   *
   * catLoad() returns early for ever once catState is 'ready', which is right
   * for what it was written for: the draw is a few hundred rows and reading it
   * on every tab press would be rude to a tablet. But nothing ever marked it
   * stale, so the copy fetched when the console first opened the card was the
   * copy it showed all day. Load a different bout, or record a result, and the
   * Division, Member and Arcade tabs still listed the old one as upcoming and
   * the new one as not yet run. The Arranged tab was fine throughout — it
   * draws from QUEUE, which every response carries — which is exactly why this
   * looked like "some of the lists update and some do not".
   *
   * Invalidated on the three commands that actually move the draw, and on a
   * full console re-read (which is what a `court` notification produces, and
   * those are published for the same three). NOT on a point or a penalty: the
   * catalogue does not carry a score, and re-reading the whole draw on every
   * keypress at a mat is the cost this cache exists to avoid.
   *
   * If the card is open on a catalogue tab, it re-reads at once so the list
   * under the operator's hand is right; otherwise it is simply marked, and the
   * next tab press pays for it.
   */
  function catStale() {
    CAT = null;
    catState = 'idle';

    var card = el('queueScrim');
    if (card && !card.hidden && boutsTab !== 'arranged') catLoad(catRepaint);
  }

  (function () {
    var back = el('divBack');
    if (back) {
      back.addEventListener('click', function () { divOpen = null; paintDivisions(); });
    }

    // Searching is DEBOUNCED, not because the list is expensive — it is a few
    // hundred rows in memory — but because rebuilding it on every keystroke
    // makes a ten-inch tablet at a mat feel like it is refusing the keyboard.
    function debounced(id, fn) {
      var input = el(id);
      if (!input) return;
      var t = null;
      input.addEventListener('input', function () {
        clearTimeout(t);
        t = setTimeout(fn, 140);
      });
    }

    debounced('boutFind', paintFind);
    debounced('arcFindA', function () { paintArcSide('a'); });
    debounced('arcFindB', function () { paintArcSide('b'); });
  })();

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
    // ⚠️ NOT `|| 0` — that reads the same for "unset" and "zero", and zero is
    // this one's meaningful value (no limit) as well as its default. A mat that
    // has turned the cap off must not have it reappear as a blank field.
    d.advantage_limit = r.advantage_limit == null ? 0 : r.advantage_limit;
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
     ['advantageLimit', 'advantage_limit'], ['stallSeconds', 'stall_seconds']].forEach(function (pair) {
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
    paintEnd();
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
      // `value` rides along for the scoring grid (a point by amount, and a
      // deduction); `source` for everything that still names an action. An
      // undefined key drops out of the JSON, so a control that sets neither
      // posts neither and the server sees exactly what it saw before.
      send(b.dataset.cmd, {
        side: b.dataset.side,
        source: b.dataset.source,
        ladder: b.dataset.ladder,
        value: b.dataset.value ? Number(b.dataset.value) : undefined
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
  /* The Stalling button is a TOGGLE, which is the draft's behaviour
     (drafts/Score Control Board.html — `blueStallToggle`): press it and the
     count starts; press the SAME corner again before it runs out and the count
     stops and is thrown away. It used to only ever send `start`, so a second
     press restarted the count from the top — the one thing a referee reaching
     for it a second time does not mean.

     Pressing the OTHER corner replaces the count rather than running a second
     one beside it, because the mat holds ONE count (`stall_side` +
     `stall_until` on the state row) and that is deliberate: it is the referee's
     own count, and a referee watches one man at a time. */
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-stall]');
    if (!b || b.disabled) return;
    var side = b.dataset.stall;
    // Read from the state, never from a flag this page keeps: the count may
    // have been started on the other console at the same table.
    // Asked of the COUNT rather than of the payload's shape, so the toggle
    // agrees with what the button is showing — a count whose last second has
    // run out is not one a second press should try to cancel. `side` is
    // 'blue', 'white' or 'both', and the comparison is the same for all three:
    // pressing the button a count is ALREADY running on cancels it, pressing
    // any other one replaces it.
    var already = !!(stall && stall.side === side && stallLeft() > 0);
    // The tap that starts the count is also the gesture that buys the beeper.
    ensureAudio();
    guarded(function () {
      send('stall', already ? { phase: 'cancel' } : { side: side, phase: 'start' });
    });
  });

  if (el('stallCancel')) el('stallCancel').addEventListener('click', function () { send('stall', { phase: 'cancel' }); });

  /* The threshold card's own pair. It IS the modal the friction rule asks for
     — it named the corner and appeared on its own — so applying the penalty
     from here does not raise a second confirmation on top of it. Dismiss
     CANCELS the count rather than only hiding the card: the server holds the
     stall until one of these two lands, so a card that merely closed would
     re-open on the next tick. */
  if (el('stallPromptApply')) el('stallPromptApply').addEventListener('click', function () {
    guarded(function () { send('stall', { phase: 'apply' }); });
  });
  if (el('stallPromptDismiss')) el('stallPromptDismiss').addEventListener('click', function () {
    guarded(function () { send('stall', { phase: 'cancel' }); });
  });

  /* Points to the corner that was being stalled against — the other half of the
     draft's card. The console sends the AMOUNT and nothing else: which corner
     receives it is read off the mat state by the server, and the amount is held
     to Ledger::STALL_AWARDS both at the endpoint and inside Scoring. So this
     still prices nothing — it names one of three numbers the rule book already
     allowed. */
  document.querySelectorAll('[data-stall-award]').forEach(function (b) {
    b.addEventListener('click', function () {
      var pts = parseInt(b.getAttribute('data-stall-award'), 10);
      if (STALL_AWARDS.indexOf(pts) < 0) return;
      guarded(function () { send('stall', { phase: 'award', points: pts }); });
    });
  });
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
   // Floor of 0 — no limit — which is this one's default. See the panel.
   ['advantageLimit', 'advantage_limit', 0, 20],
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
      advantage_limit: draft.advantage_limit,
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
    // So the clock's final-second warning can sound. Same gesture rule as the
    // stalling count — see ensureAudio().
    ensureAudio();
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
  /* ── Finish ────────────────────────────────────────────────────────────
     Opens the Match result card (the draft's, see #endPanel in the console
     document) rather than the shared three-question dialog it used to raise.
     The card is only a nicer way to answer the SAME three questions, and it
     posts the same `end` command through the same guards — so a console that
     does not render the card keeps the old dialog, and neither can send an
     ending the engine would refuse.

     It opens on whoever the SCORE says is ahead, because that is the answer in
     most bouts and an official confirming one tap is far less likely to leave
     it blank than one filling it in. It is still a choice — a submission is
     routinely won by the corner that is behind on points. */
  if (el('btnEnd')) el('btnEnd').addEventListener('click', function () {
    if (el('endPanel')) {
      endPick = (S.score || {}).leader || null;
      endMethod = 'points';
      if (el('endNote')) el('endNote').value = '';
      el('endPanel').hidden = false;
      paintEnd();
      return;
    }

    // The fallback dialog, for a console with no card. Unchanged.
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

  /* Tapping a corner picks it; tapping a winning type picks that. Neither
     sends anything — nothing about this card reaches the mat until Declare. */
  document.querySelectorAll('[data-end-pick]').forEach(function (b) {
    b.addEventListener('click', function () {
      endPick = b.getAttribute('data-end-pick');
      paintEnd();
    });
  });
  document.querySelectorAll('[data-end-method]').forEach(function (b) {
    b.addEventListener('click', function () {
      endMethod = b.getAttribute('data-end-method');
      paintEnd();
    });
  });

  if (el('endDeclare')) el('endDeclare').addEventListener('click', function () {
    var out = {
      winner: endPick || null,
      method: endMethod,
      note: el('endNote') ? el('endNote').value.trim() : '',
    };

    // The same refusal the dialog carried: a method that names somebody, with
    // nobody named. The card STAYS OPEN and the official is told what is
    // missing, rather than the server quietly returning a points win with the
    // method dropped. See Scoring::METHODS_NEEDING_WINNER.
    if (!out.winner && METHODS_NEEDING_WINNER.indexOf(out.method) >= 0) {
      flash(T.winner_required);
      return;
    }

    function done() {
      el('endPanel').hidden = true;
      // The state comes back finished, so the end-of-match panel offers itself
      // next — which is where the result is RECORDED. Declaring is not
      // recording; it is saying how the bout ended.
      send('end', out);
    }

    // A disqualification is the one ending that also takes the match number,
    // because it is the one an athlete disputes. The card is hidden first so
    // the question is not asked behind it.
    if (out.method === 'dq') {
      el('endPanel').hidden = true;
      return confirmWithNumber(T.end, function () { send('end', out); });
    }

    done();
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
        if (p.hasOwnProperty('stall')) setStall(p.stall);
        // A FULL re-read, which is what a `court` notification asks for — and
        // those are published exactly when the draw moved, on this mat or on
        // another one carrying a winner into a slot of ours. The cheap `mat`
        // push below is state alone and leaves the catalogue be.
        catStale();
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
