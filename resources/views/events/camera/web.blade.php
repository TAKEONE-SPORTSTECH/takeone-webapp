{{--
    The browser camera: a phone films a mat with no app on it.

    It is the SAME device to the server as the app — same enrol, same token,
    same config poll, same clip and chunked upload — so it appears in the
    console as an ordinary camera and the mat starts and stops it exactly as it
    starts and stops a phone running the APK.

    What the browser cannot do, and does not pretend to: survive the screen
    locking, keep the footage if the tab is closed, or upload a bout hours
    later. Those are the reasons the app exists, and the page says so rather
    than letting somebody discover it during a final.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ in_array(app()->getLocale(), ['ar']) ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8">
    {{-- This document is DARK by design — a wall board, a console, a review
         screen. Declared so a browser's auto-dark does not try to "help" and
         invert the one light thing on it, and so Dark Reader leaves it alone.
         Same reasoning as the light pages, opposite value. --}}
    <meta name="color-scheme" content="dark">
    <meta name="darkreader-lock">
    <style>html { color-scheme: dark; }</style>

<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>{{ __('personal.event_live_web_camera') }}</title>
<style>
  :root {
    --ink:#0b0b0f; --panel:#15151d; --line:rgba(232,230,224,.14);
    --paper:#e8e6e0; --muted:rgba(232,230,224,.55); --gold:#d8b25f; --rec:#ef4444;
  }
  * { box-sizing:border-box; -webkit-tap-highlight-color:transparent; }
  html,body { margin:0; height:100%; background:var(--ink); color:var(--paper);
    font-family:Inter,system-ui,-apple-system,"Segoe UI",sans-serif; overscroll-behavior:none; }
  #app { position:fixed; inset:0; display:flex; flex-direction:column; }

  /* ── Waiting ─────────────────────────────────────────────────────────── */
  #wait { flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center;
    gap:18px; padding:24px calc(24px + env(safe-area-inset-right)) calc(24px + env(safe-area-inset-bottom)) calc(24px + env(safe-area-inset-left)); text-align:center; }
  #qr { background:var(--paper); padding:14px; border-radius:14px; line-height:0; }
  #qr svg { width:min(52vw,230px); height:auto; display:block; }
  .cap { font-size:12px; font-weight:700; letter-spacing:.22em; text-transform:uppercase; color:var(--gold); }
  #code { font-size:clamp(38px,13vw,62px); font-weight:800; letter-spacing:.10em; line-height:1; font-variant-numeric:tabular-nums; }
  #host { font-size:12px; font-weight:700; letter-spacing:.18em; text-transform:uppercase; color:var(--muted); }
  .hint { font-size:13px; color:var(--muted); max-width:34ch; line-height:1.5; }
  .note { font-size:12px; color:var(--muted); max-width:34ch; line-height:1.5;
    border-top:1px solid var(--line); padding-top:14px; margin-top:4px; }
  .note b { color:var(--paper); font-weight:700; }

  /* ── Filming ─────────────────────────────────────────────────────────── */
  #live { flex:1; position:relative; display:none; background:#000; }
  #view { position:absolute; inset:0; width:100%; height:100%; object-fit:cover; background:#000; }
  #bar { position:absolute; left:0; right:0; top:0; display:flex; align-items:center; gap:10px;
    padding:calc(10px + env(safe-area-inset-top)) 14px 10px;
    background:linear-gradient(180deg, rgba(0,0,0,.75), transparent); }
  #dot { width:11px; height:11px; border-radius:50%; background:var(--muted); flex:0 0 auto; }
  #dot.on { background:var(--rec); animation:pulse 1.2s ease-in-out infinite; }
  @keyframes pulse { 50% { opacity:.35; } }
  #what { font-size:13px; font-weight:700; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  #mat { margin-inline-start:auto; font-size:11px; font-weight:800; letter-spacing:.14em; text-transform:uppercase;
    border:1px solid var(--line); border-radius:999px; padding:5px 11px; flex:0 0 auto; }
  #foot { position:absolute; left:0; right:0; bottom:0; padding:12px 14px calc(12px + env(safe-area-inset-bottom));
    background:linear-gradient(0deg, rgba(0,0,0,.78), transparent); font-size:12px; color:var(--muted); }
  #queue { font-variant-numeric:tabular-nums; }

  #err { position:fixed; left:14px; right:14px; bottom:calc(14px + env(safe-area-inset-bottom));
    background:var(--panel); border:1px solid var(--line); border-radius:14px; padding:12px 14px;
    font-size:13px; line-height:1.5; display:none; z-index:20; }
  @media (prefers-reduced-motion: reduce) { #dot.on { animation:none; } }
</style>
</head>
<body>
<div id="app">
  <div id="wait">
    <div class="cap">{{ __('personal.event_live_web_camera') }}</div>
    <div id="qr"></div>
    <div id="code">······</div>
    <div id="host">{{ $host }}</div>
    <p class="hint">{{ __('personal.camera_web_wait') }}</p>
    <p class="note">{!! __('personal.camera_web_note') !!}
      @if ($appUrl)<br><b>{{ $appUrl }}</b>@endif
    </p>
  </div>

  <div id="live">
    <video id="view" playsinline muted autoplay></video>
    <div id="bar"><span id="dot"></span><span id="what"></span><span id="mat"></span></div>
    <div id="foot"><span id="queue"></span></div>
  </div>
</div>
<div id="err"></div>

<script>
(function () {
  'use strict';

  var KEY = 'takeone.camera.token';
  var POLL_WAITING = 4000, POLL_LIVE = 2000;
  var CHUNK = 8 * 1024 * 1024;          // matches CameraController::MAX_CHUNK

  var token = null, cfg = null, rec = null, chunks = [], stream = null;
  var recording = false, bout = null, wake = null, poll = null, uploading = false;
  var pending = [];                      // clips filmed but not yet uploaded

  var el = function (id) { return document.getElementById(id); };
  function say(msg) { var e = el('err'); e.textContent = msg; e.style.display = msg ? 'block' : 'none'; }

  /* ── Talking to the server ─────────────────────────────────────────────
     The same four endpoints the app uses. No session, no CSRF: the token IS
     the authorisation and it reaches exactly one row. */
  async function api(path, opts) {
    var res = await fetch(path, Object.assign({ cache: 'no-store' }, opts || {}));
    var body = null;
    try { body = await res.json(); } catch (_) {}
    return { status: res.status, body: body };
  }

  async function enrol() {
    var r = await api('/camera/enroll', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify({ device_name: 'Browser camera', app_version: 'web' })
    });
    if (r.status !== 201 || !r.body || !r.body.token) { say(@json(__('personal.camera_web_enrol_failed'))); return null; }
    token = r.body.token;
    try { localStorage.setItem(KEY, token); } catch (_) {}
    paintWaiting(r.body.code, r.body.claim_url);
    return token;
  }

  /* A 404 means this identity is GONE — re-enrol rather than sit on a dead
     code. Anything else (a timeout, a captive portal, a 500) leaves it alone.
     Exactly the rule the app follows; getting this wrong is what stranded a
     phone on a code that existed nowhere. */
  async function sync() {
    if (!token) return;
    var r = await api('/camera/' + token + '/config');

    if (r.status === 404) { try { localStorage.removeItem(KEY); } catch (_) {} token = null; await enrol(); return; }
    if (r.status !== 200 || !r.body) return;

    cfg = r.body;

    if (!cfg.claimed) {
      paintWaiting(cfg.code, cfg.claim_url);
      show('wait');
      schedule(POLL_WAITING);
      return;
    }

    await filming();
    schedule(POLL_LIVE);
  }

  function schedule(ms) { clearTimeout(poll); poll = setTimeout(sync, ms); }

  /* ── Waiting ─────────────────────────────────────────────────────────── */
  var lastCode = null;
  function paintWaiting(code, claimUrl) {
    if (!code || code === lastCode) return;
    lastCode = code;
    el('code').textContent = code.slice(0, 3) + ' ' + code.slice(3);
    // The QR is drawn server-side for the screens; here the code is the thing
    // an organiser types, and the claim URL is offered as a link they can scan
    // from the console instead.
    el('qr').innerHTML = '';
    if (claimUrl && window.TakeoneQr) el('qr').innerHTML = window.TakeoneQr(claimUrl);
    else el('qr').style.display = 'none';
  }

  function show(which) {
    el('wait').style.display = which === 'wait' ? 'flex' : 'none';
    el('live').style.display = which === 'live' ? 'block' : 'none';
  }

  /* ── Filming ─────────────────────────────────────────────────────────── */
  async function filming() {
    show('live');
    el('mat').textContent = (cfg.court || '') + (cfg.angle ? ' · ' + cfg.angle : '');
    bout = cfg.match || null;
    el('what').textContent = boutLabel();

    if (!stream) {
      try {
        stream = await navigator.mediaDevices.getUserMedia({
          video: { facingMode: { ideal: 'environment' }, width: { ideal: 1920 }, height: { ideal: 1080 } },
          audio: true
        });
      } catch (e) {
        say(@json(__('personal.camera_web_no_lens')));
        return;
      }
      el('view').srcObject = stream;
      keepAwake();
    }

    // The mat decides. `recording` is what the scoring table believes this
    // camera is doing, and it is the backstop the app uses too.
    if (cfg.recording && !recording) start();
    if (!cfg.recording && recording) await stop();

    el('dot').className = recording ? 'on' : '';
    el('queue').textContent = pending.length
      ? pending.length + ' ' + @json(__('personal.camera_web_queued'))
      : '';
  }

  function boutLabel() {
    if (!bout) return @json(__('personal.camera_web_standby'));
    var bits = [];
    if (bout.number) bits.push('#' + bout.number);
    if (bout.red && bout.blue) bits.push(bout.red + ' v ' + bout.blue);
    else if (bout.stage) bits.push(bout.stage);
    return bits.join('  ') || @json(__('personal.camera_web_standby'));
  }

  /* Prefer MP4: the ingest pipeline names the stored file .mp4 and ffmpeg is
     happier with it. Fall back to whatever this browser will give. */
  function mime() {
    var want = ['video/mp4;codecs=avc1.42E01E,mp4a.40.2', 'video/mp4', 'video/webm;codecs=vp9,opus', 'video/webm'];
    for (var i = 0; i < want.length; i++) {
      if (window.MediaRecorder && MediaRecorder.isTypeSupported(want[i])) return want[i];
    }
    return '';
  }

  function start() {
    if (!stream || recording) return;
    chunks = [];
    try {
      rec = new MediaRecorder(stream, mime() ? { mimeType: mime(), videoBitsPerSecond: 6000000 } : undefined);
    } catch (e) { say(@json(__('personal.camera_web_no_record'))); return; }

    rec.ondataavailable = function (e) { if (e.data && e.data.size) chunks.push(e.data); };
    rec.start(1000);                       // a slice a second, so a crash loses one
    recording = true;
    startedAt = new Date().toISOString();
    recBout = bout;
  }

  var startedAt = null, recBout = null;

  function stop() {
    return new Promise(function (done) {
      if (!rec || !recording) { recording = false; return done(); }
      rec.onstop = function () {
        recording = false;
        var blob = new Blob(chunks, { type: rec.mimeType || 'video/mp4' });
        chunks = [];
        if (blob.size > 0) {
          pending.push({ blob: blob, match: recBout && recBout.id, started: startedAt, ended: new Date().toISOString() });
          drain();
        }
        done();
      };
      try { rec.stop(); } catch (_) { recording = false; done(); }
    });
  }

  /* ── Getting it off the phone ─────────────────────────────────────────
     File the clip, then send it in 8MB pieces with the server's offset as the
     authority — the same resumable contract the app uses, because the far end
     is the same hall wifi. */
  async function drain() {
    if (uploading || !pending.length || !token) return;
    uploading = true;

    while (pending.length) {
      var item = pending[0];
      try {
        var ref = 'web-' + (item.started || Date.now());
        var filed = await api('/camera/' + token + '/clip', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
          body: JSON.stringify({
            match_id: item.match || null, local_ref: ref,
            started_at: item.started, ended_at: item.ended,
            duration_seconds: Math.max(0, Math.round((new Date(item.ended) - new Date(item.started)) / 1000)),
            bytes: item.blob.size
          })
        });
        if (!filed.body || !filed.body.id) break;

        var id = filed.body.id, sent = 0;
        while (sent < item.blob.size) {
          var piece = item.blob.slice(sent, sent + CHUNK);
          var up = await fetch('/camera/' + token + '/clip/' + id + '/upload', {
            method: 'POST',
            headers: { 'Upload-Offset': String(sent), 'Content-Type': 'application/octet-stream' },
            body: piece
          });
          var b = null; try { b = await up.json(); } catch (_) {}
          if (up.status === 409 && b && typeof b.offset === 'number') { sent = b.offset; continue; }
          if (!up.ok) throw new Error('chunk');
          sent = (b && typeof b.offset === 'number') ? b.offset : sent + piece.size;
          el('queue').textContent = Math.round(sent / item.blob.size * 100) + '%';
        }
        pending.shift();
      } catch (e) {
        break;                              // keep it; the next stop retries
      }
    }

    uploading = false;
    el('queue').textContent = pending.length ? pending.length + ' ' + @json(__('personal.camera_web_queued')) : '';
  }

  /* A camera whose screen sleeps is not a camera. */
  async function keepAwake() {
    try { if ('wakeLock' in navigator) wake = await navigator.wakeLock.request('screen'); } catch (_) {}
  }
  document.addEventListener('visibilitychange', function () {
    if (document.visibilityState === 'visible') { keepAwake(); sync(); }
  });

  /* ── Go ───────────────────────────────────────────────────────────────── */
  (async function () {
    try { token = localStorage.getItem(KEY); } catch (_) {}
    if (!token) { await enrol(); }
    sync();
  })();
})();
</script>
</body>
</html>
