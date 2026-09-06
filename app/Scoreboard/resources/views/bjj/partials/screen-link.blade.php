{{--
    The screen's link back to the app — a subscribe-only MQTT client, inline,
    running in a Worker.

    A hall screen has to notice things it cannot ask about: that it has been
    given a mat, that it has been taken off one, and that the queue on its mat
    has moved. All of it used to depend on a timer, and cog/WPE on DRM throttles
    background timers to minutes (measured ~2m50s for a 60s interval on a
    low-powered screen) — so an organiser unpaired a screen and then stood watching it show the
    wrong queue. An inbound socket message wakes the page immediately.

    ── Why a Worker ────────────────────────────────────────────────────────────
    Because the main thread is the thing that was slow. This page draws a
    full-screen board on a low-powered screen with no GPU acceleration, and a socket message
    arriving on that thread waits behind paint work — measured at minutes on the
    real display against seven seconds for the same page run headless. That is
    the entire reason the separate Python agent exists, and it is why the first
    version of the in-place redraw felt broken: the nudge was delivered to the
    one thread that had no time to receive it.

    A Worker has its own thread. Nothing the renderer does can starve it, so the
    message lands the moment it arrives and the main thread is asked for exactly
    one short task: patch the DOM. The board settling to a still image (see the
    CSS note in board.blade.php) is the other half of the same fix — together
    they take the update from "wait and see" to a frame or two.

    The client is written once, as a normal function, and shipped to the Worker
    via toString(). It therefore may not close over anything in this file — CFG
    and its callback are passed in. If a Worker cannot be created at all the
    same function runs inline, which is exactly the old behaviour.

    ── Why hand-written rather than mqtt.js ───────────────────────────────────
    The bundle is 369 KB, this page is served to an appliance on a metered link,
    and what is needed is CONNECT, SUBSCRIBE, inbound PUBLISH and a keepalive —
    QoS 0, no publishing, no sessions, no retained-message handling. That is a
    bounded piece of a well-specified protocol (MQTT 3.1.1, §3), and inlining it
    costs no request at all. The project's rule against adding a dependency for
    a small feature points the same way.

    Contract — see ScreenChannel for the other side:
      {action: paired|unpaired}   → this is a different page now; reload.
      {action: reload}            → the table says start again; reload.
      {action: board, payload:{}} → same page, new queue; redraw in place.

    Expects: $screenLink = ['ws_url','username','password','topic','payload_url']
    — or the partial is simply not included, and the heartbeat is the only signal.

    ── Three kinds of listener, told apart by CourtBoard.pinned ───────────────
      'bout' / 'queue' / 'both'  a WALL screen. Draws one payload; see above.
      'console'                  the SCORING TABLE. Draws the state, the log and
                                 the queue together, so it never takes a board
                                 payload — it re-reads its own `console_url`
                                 instead, and takes the bout state directly off
                                 a `mat` message. This is what keeps the two
                                 consoles at one mat agreeing with each other.
--}}
<script>
(function () {
  var CFG = @json($screenLink);
  if (!CFG || !CFG.ws_url || !('WebSocket' in window)) return;

  // ── The client ───────────────────────────────────────────────────────────
  // Self-contained on purpose: this function is stringified and handed to a
  // Worker, so it can only see its two arguments. POST is how it speaks back.
  function mqtt(CFG, POST) {
    var KEEPALIVE = 60;            // seconds, as sent in CONNECT
    var failures = 0;
    var sock = null, pinger = null;

    // ── Encoding (MQTT 3.1.1 §2) ───────────────────────────────────────────
    // Remaining Length is a 7-bit-per-byte varint with the high bit as the
    // continuation flag; strings are a 2-byte big-endian length then UTF-8.
    function varint(n) {
      var out = [];
      do { var b = n % 128; n = Math.floor(n / 128); if (n > 0) b = b | 0x80; out.push(b); } while (n > 0);
      return out;
    }
    function utf8(s) {
      var bytes = [], enc = new TextEncoder().encode(s);
      for (var i = 0; i < enc.length; i++) bytes.push(enc[i]);
      return [(bytes.length >> 8) & 0xFF, bytes.length & 0xFF].concat(bytes);
    }
    function packet(type, body) {
      return new Uint8Array([type].concat(varint(body.length), body));
    }

    function connectPacket() {
      var body = utf8('MQTT').concat([
        4,        // protocol level 3.1.1
        0xC2,     // username + password + clean session
        (KEEPALIVE >> 8) & 0xFF, KEEPALIVE & 0xFF
      ]);
      // A per-connection client id, so a reconnect never collides with the
      // session the broker has not yet reaped for this same screen.
      body = body.concat(utf8(CFG.username + '-' + Math.floor(Math.random() * 1e9)));
      body = body.concat(utf8(CFG.username));
      body = body.concat(utf8(CFG.password));
      return packet(0x10, body);
    }

    function subscribePacket() {
      return packet(0x82, [0, 1].concat(utf8(CFG.topic), [0]));   // packet id 1, QoS 0
    }

    // ── Inbound ────────────────────────────────────────────────────────────
    // One WebSocket frame is not one MQTT packet: the broker may coalesce a
    // SUBACK and a PUBLISH into a single frame, so walk the buffer rather than
    // assuming its first byte describes the whole thing.
    function onFrame(buf) {
      var at = 0;

      while (at < buf.length) {
        var type = buf[at] & 0xF0;

        // Remaining Length starts one byte after the header.
        var i = at + 1, shift = 0, len = 0, b;
        do {
          if (i >= buf.length) return;                      // truncated — drop it
          b = buf[i++];
          len += (b & 0x7F) * Math.pow(2, shift);
          shift += 7;
        } while (b & 0x80);

        var end = i + len;                                  // one past this packet
        if (end > buf.length) return;                       // truncated — drop it

        if (type === 0x20) {                                // CONNACK
          if (len >= 2 && buf[i + 1] !== 0) { POST({ t: 'auth' }); return; }
          failures = 0;
          sock.send(subscribePacket());
          POST({ t: 'up' });
        } else if (type === 0x30) {                         // PUBLISH (QoS 0)
          handlePublish(buf, i, end);
        }

        at = end;
      }
    }

    /** Variable header is the topic; everything after it, up to `end`, is payload. */
    function handlePublish(buf, i, end) {
      var topicLen = (buf[i] << 8) | buf[i + 1];
      var payload = buf.subarray(i + 2 + topicLen, end);

      var msg = null;
      try { msg = JSON.parse(new TextDecoder().decode(payload)); } catch (e) { return; }
      if (msg) POST({ t: 'msg', m: msg });
    }

    function open() {
      try { sock = new WebSocket(CFG.ws_url, 'mqtt'); } catch (e) { return retry(); }
      sock.binaryType = 'arraybuffer';

      sock.onopen = function () {
        sock.send(connectPacket());
        pinger = setInterval(function () {
          if (sock && sock.readyState === 1) sock.send(new Uint8Array([0xC0, 0x00]));
        }, (KEEPALIVE / 2) * 1000);
      };

      sock.onmessage = function (e) { onFrame(new Uint8Array(e.data)); };
      sock.onclose = function () { clearInterval(pinger); POST({ t: 'down' }); retry(); };
      sock.onerror = function () { try { sock.close(); } catch (_) {} };
    }

    function retry() {
      // Backoff to a minute. The board keeps showing its last known queue
      // throughout — a wall screen must never blank because a socket dropped.
      failures++;
      if (failures > 20) { POST({ t: 'gone' }); return; }
      setTimeout(open, Math.min(60000, 2000 * failures));
    }

    open();
  }

  // ── This side of the wire ────────────────────────────────────────────────
  var board = function () { return window.CourtBoard || null; };

  function stale(on) { var b = board(); if (b) b.stale(on); }

  /**
   * Ask for the current board, once.
   *
   * Runs on every successful connect, including the first. The window between
   * this page being rendered and its subscription going live is small but real,
   * and a result that lands inside it would otherwise never be seen — the next
   * push is the next bout, which on a quiet mat can be a long way off. Cheap to
   * be sure: update() is a no-op when the content hash has not moved.
   */
  function resync() {
    var b = board();
    if (!b) return;
    // Ask for whatever this screen is currently showing: the mat screen wants
    // the bout, the queue board wants the running order. Asking the wrong one
    // would hand a scoreboard a queue payload it cannot draw.
    //
    // A pinned screen answers from its pin, not from the mat. A bout-pinned
    // board sitting between bouts reports mode 'upcoming' — true of the BOUT,
    // but it is still the mat's screen and still wants the bout endpoint.
    // 'both' is this package's own case: ONE document draws the queue, the
    // introduction, the match and the celebration, so a screen following the
    // mat never changes page and wants BOTH endpoints on a recovery. The two
    // payloads are told apart by shape in update(), not by which call returned
    // them.
    // A CONSOLE is neither: it draws the state, the officiating log and the
    // queue together, so it has one address of its own that returns all three.
    // Asking it for a board payload would hand a scoring table a running order
    // it cannot draw.
    var urls = b.pinned === 'console'
      ? [CFG.console_url]
      : b.pinned === 'both'
      ? [CFG.state_url, CFG.payload_url]
      : [b.pinned ? (b.pinned === 'bout' ? CFG.state_url : CFG.payload_url)
                  : ((b.mode && b.mode() !== 'upcoming') ? CFG.state_url : CFG.payload_url)];

    urls.filter(Boolean).forEach(function (url) {
      // Ask for JSON explicitly. These are all JSON doors, and a JSON door
      // answers a dead mat with a 404 — which is the ONLY way this client can
      // tell "the thing I am showing no longer exists" from "the wifi dropped"
      // (CLAUDE.md → Unattended Devices Must Always Recover). Without the
      // header the app treats the request as browser navigation and redirects a
      // 404 to the home page, so the fetch succeeds, returns HTML, and the
      // failure arrives disguised as a parse error.
      fetch(url, { cache: 'no-store', headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (p) { if (p) b.update(p); })
        .catch(function () { /* keep the last known board up */ });
    });
  }

  /**
   * The credential is embedded in this page, so a broker that refuses it means
   * the PAGE is stale (a JWT that outlived its window). Reloading mints a new
   * one; the delay keeps a genuine outage from becoming a reload loop.
   */
  function giveUp() { setTimeout(function () { window.location.reload(); }, 120000); }

  function handle(m) {
    if (!m) return;

    if (m.t === 'up') { stale(false); resync(); return; }
    if (m.t === 'down') { stale(true); return; }
    if (m.t === 'auth' || m.t === 'gone') { stale(true); giveUp(); return; }
    if (m.t !== 'msg' || !m.m) return;

    var msg = m.m;

    // The queue moved, and the message brought it with it — no round trip. A
    // wall screen must never go black between bouts, so this patches in place
    // and only falls back to asking if the payload was somehow absent.
    if (msg.action === 'board') {
      // The pairing screen shares this partial and has no board to update. It
      // must IGNORE this rather than reload: a screen waiting to be claimed
      // that reloaded on every result would flicker through a whole event.
      if (!board()) return;
      // A console cares that the running order moved — its own queue panel is
      // now wrong — but it cannot use this payload: the board's queue and the
      // console's are different shapes. So it re-reads itself. This is the
      // refresh-signal pattern, and it is why a bout committed on the referee's
      // tablet now updates the laptop's queue at the table.
      if (board().pinned === 'console') { resync(); return; }
      // The mat's own screen never draws the running order — not while a bout
      // is on it, and not while it waits for the next one. It could not draw
      // this payload if it tried: a queue and a bout are different shapes.
      if (board().pinned === 'bout') return;
      // A screen currently showing a BOUT must not be dragged back to the
      // queue by a running-order nudge — the scoring table owns the screen
      // until it releases it. The mat screen advertises that by exposing
      // `mode`; the queue board does not.
      //
      // ⚠️ EXCEPT when this screen draws BOTH in one document, which is what
      // this package does (`pinned === 'both'`). Karate and Taekwondo cross
      // between queue and bout by reloading the page, so they re-fetch the
      // running order on the way; BJJ never reloads. Dropping the nudge here
      // therefore did not defer the update, it DISCARDED it — and since
      // `commit` publishes the queue first and the mat second, every single
      // bout ended with the wall showing a running order that still listed the
      // finished bout at the top and still said "to be decided" for the
      // winner's next slot, until somebody loaded another match or cut the
      // power. Accept it and cache it; the queue is not drawn until the mat
      // releases the screen, so there is nothing to be dragged away from.
      if (board().pinned !== 'both' && board().mode && board().mode() !== 'upcoming') return;
      if (msg.payload) { board().update(msg.payload); } else { resync(); }
      return;
    }

    // The bout changed: a point, the clock, hajime, or a whole new bout loaded.
    // Carries the state with it, so nothing is fetched.
    if (msg.action === 'mat') {
      if (!board()) return;

      // A board pinned to the running order is not following this mat's bout —
      // it is the corridor's answer to "when am I on". Without this it would
      // see a bout it is not showing, decide it is on the wrong page, reload,
      // be handed the queue again by the server, and do the whole thing again
      // on the next keypress at the scoring table.
      if (board().pinned === 'queue') return;

      // Between the queue and a bout is a change of PAGE — the two are
      // different documents, so a screen that FOLLOWS the mat has to reload to
      // cross between them. A pinned screen never crosses: it is already on the
      // surface it will show all day, and reloading would only throw away the
      // bout it is drawing and re-run its entrance.
      if (!board().pinned) {
        var showing = board().mode ? board().mode() : 'upcoming';
        var wanted = (msg.state && msg.state.mode) || 'upcoming';
        if ((showing === 'upcoming') !== (wanted === 'upcoming')) { window.location.reload(); return; }
      }

      if (msg.state) board().update(msg.state);
      return;
    }

    // Paired or unpaired: this is a different PAGE now, not different numbers.
    // Reload: the scoring table has asked for this screen to start again,
    // because from the floor it looks stuck. Same treatment — there is nothing
    // on this page worth preserving that is not re-fetched on load.
    if (msg.action === 'paired' || msg.action === 'unpaired' || msg.action === 'reload') {
      // Not a console. `reload` is the operator's own ↻ — "every screen on this
      // mat, start again" — and it is aimed at the wall, where a stuck board is
      // the thing being fixed. Throwing the scoring table's page away mid-bout
      // to fix a screen across the hall is a cure worse than the symptom, and
      // the console has a cheaper way to be certain: re-read itself.
      if (board() && board().pinned === 'console') { resync(); return; }
      window.location.reload();
    }
  }

  // ── Off the main thread, if we can ───────────────────────────────────────
  // The Worker is the point of the whole file (see the note above), but it must
  // never be the reason a screen has no link at all: if one cannot be built,
  // the same client runs inline, which is how this worked before.
  try {
    var src = 'self.onmessage=function(e){self.onmessage=null;(' + mqtt.toString() +
              ')(e.data,function(m){self.postMessage(m);});};';
    var worker = new Worker(URL.createObjectURL(new Blob([src], { type: 'text/javascript' })));
    var heard = false;

    worker.onmessage = function (e) { heard = true; handle(e.data); };
    // An error the Worker reports is the easy case — take the inline path at
    // once rather than waiting out the watchdog below.
    worker.onerror = function () { if (!heard) { fallback(worker); } };
    worker.postMessage(CFG);

    // ── The watchdog ───────────────────────────────────────────────────────
    // `new Worker` succeeding is not the same as the link working. A Worker
    // built from a blob can start and then fail to open its socket with no
    // error anywhere — which is what an Android TV WebView does — and the screen
    // then has no live link at all while looking perfectly healthy. Every
    // symptom of that is a delay: scores arriving late, and an unpaired screen
    // taking a minute to notice, because the slow poll is quietly doing all the
    // work.
    //
    // The client posts {t:'up'} the moment the broker accepts it, so silence for
    // eight seconds means the Worker is not going to deliver. Drop it and run
    // the same client inline, where the socket is the page's own.
    setTimeout(function () {
      if (!heard) { fallback(worker); }
    }, 8000);
  } catch (e) {
    mqtt(CFG, handle);
  }

  /** Abandon the Worker and run the client on this thread instead. */
  function fallback(worker) {
    try { worker.terminate(); } catch (e) {}
    mqtt(CFG, handle);
  }
})();
</script>
