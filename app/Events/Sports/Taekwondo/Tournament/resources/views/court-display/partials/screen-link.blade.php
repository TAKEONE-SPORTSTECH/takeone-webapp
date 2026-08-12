{{--
    The screen's link back to the app — a subscribe-only MQTT client, inline.

    A hall screen has to notice two things it cannot ask about: that it has just
    been given a mat, and that it has just been taken off one. Both used to
    depend on a timer, and cog/WPE on DRM throttles background timers to minutes
    (measured ~2m50s for a 60s interval on a Pi 3B) — so an organiser unpaired a
    screen and then stood watching it show the wrong queue. An inbound socket
    message wakes the page immediately.

    Why hand-written rather than mqtt.js: the bundle is 369 KB, this page is
    served to an appliance on a metered 4G link, and what is needed is CONNECT,
    SUBSCRIBE, inbound PUBLISH and a keepalive — QoS 0, no publishing, no
    sessions, no retained-message handling. That is a bounded piece of a
    well-specified protocol (MQTT 3.1.1, §3), and inlining it costs no request
    at all. The project's rule against adding a dependency for a small feature
    points the same way.

    Contract: any message on the screen's topic means "your assignment changed
    — go and find out what you are now". The page reloads and the SERVER
    decides what to render: the board, or the pairing QR. Nothing about the
    competition travels on this topic, so a screen learns nothing it could not
    already see.

    Expects: $screenLink = ['ws_url','username','password','topic'] — or the
    partial is simply not included, and the heartbeat remains the only signal.
--}}
<script>
(function () {
  var CFG = @json($screenLink);
  if (!CFG || !CFG.ws_url || !('WebSocket' in window)) return;

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
        if (len >= 2 && buf[i + 1] !== 0) { giveUp(); return; }
        failures = 0;
        sock.send(subscribePacket());
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

    // Any assignment change means the same thing: this page is now out of date.
    if (msg && (msg.action === 'paired' || msg.action === 'unpaired')) {
      window.location.reload();
    }
  }

  function giveUp() {
    // The credential is embedded in this page, so a broker that refuses it means
    // the PAGE is stale (a JWT that outlived its window). Reloading mints a new
    // one; the delay keeps a genuine outage from becoming a reload loop.
    setTimeout(function () { window.location.reload(); }, 120000);
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
    sock.onclose = function () { clearInterval(pinger); retry(); };
    sock.onerror = function () { try { sock.close(); } catch (_) {} };
  }

  function retry() {
    // Backoff to a minute. The board keeps showing its last known queue
    // throughout — a wall screen must never blank because a socket dropped.
    failures++;
    if (failures > 20) { giveUp(); return; }
    setTimeout(open, Math.min(60000, 2000 * failures));
  }

  open();
})();
</script>
