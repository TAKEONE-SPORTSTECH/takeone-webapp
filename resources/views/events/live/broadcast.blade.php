{{--
    The phone that films the mat.

    A volunteer is holding this at the edge of a competition area with one hand.
    So it is one screen, one button, and nothing that needs reading: the camera
    fills the glass, the button says GO LIVE, and once it is live the only things
    on top of it are how long it has been running and how many people are
    watching.

    Deliberately NOT the shared mobile shell — this is a full-bleed camera
    viewfinder, not a page in the app, and the shell's chrome, safe-area padding
    and stagger animation all fight a viewfinder.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="utf-8">
{{-- A viewfinder is authored at one size and fills the glass; there is nothing
     here to zoom into, and a pinch would only push the button off the edge. Same
     reasoning as the screen pages. --}}
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $stream->label }} — live</title>
{{-- The same icon font the app layouts load, from the same place. --}}
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<style>
  *{box-sizing:border-box}
  html,body{margin:0;height:100%;background:#07070a;color:#fff;
            font-family:Inter,system-ui,-apple-system,sans-serif;overscroll-behavior:none}
  body{touch-action:manipulation}

  #stage{position:fixed;inset:0;display:flex;align-items:center;justify-content:center;background:#07070a}
  video{width:100%;height:100%;object-fit:cover;transform:scaleX(-1)}
  video.rear{transform:none}

  /* Everything floats over the picture. */
  .top{position:fixed;top:0;left:0;right:0;padding:calc(12px + env(safe-area-inset-top)) 14px 12px;
       display:flex;align-items:center;gap:10px;
       background:linear-gradient(180deg,rgba(0,0,0,.65),transparent)}
  .back{width:40px;height:40px;border-radius:999px;background:rgba(255,255,255,.14);
        border:1px solid rgba(255,255,255,.25);backdrop-filter:blur(8px);
        display:grid;place-items:center;color:#fff;text-decoration:none;flex:0 0 auto}
  .who{min-width:0;flex:1}
  .who .mat{font-size:15px;font-weight:800;line-height:1.15;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .who .ev{font-size:11.5px;color:rgba(255,255,255,.7);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}

  .chip{display:inline-flex;align-items:center;gap:6px;padding:5px 11px;border-radius:999px;
        font-size:12px;font-weight:700;letter-spacing:.02em;background:rgba(255,255,255,.14);
        border:1px solid rgba(255,255,255,.25);backdrop-filter:blur(8px)}
  .chip.on{background:#e0263c;border-color:#e0263c}
  .dot{width:7px;height:7px;border-radius:50%;background:#fff}
  .chip.on .dot{animation:pulse 1.4s ease-in-out infinite}
  @keyframes pulse{0%,100%{opacity:1}50%{opacity:.25}}

  .bottom{position:fixed;left:0;right:0;bottom:0;padding:16px 18px calc(20px + env(safe-area-inset-bottom));
          display:flex;flex-direction:column;align-items:center;gap:12px;
          background:linear-gradient(0deg,rgba(0,0,0,.7),transparent)}

  .go{width:100%;max-width:420px;height:60px;border:0;border-radius:18px;
      font-size:17px;font-weight:800;letter-spacing:.04em;text-transform:uppercase;
      background:#e0263c;color:#fff;display:flex;align-items:center;justify-content:center;gap:10px;
      transition:transform .12s ease,opacity .2s ease}
  .go:active{transform:scale(.97)}
  .go[disabled]{opacity:.55}
  .go.stop{background:rgba(255,255,255,.16);border:1px solid rgba(255,255,255,.3)}

  .row{display:flex;gap:10px;width:100%;max-width:420px}
  .row button, .row a{flex:1;height:44px;border-radius:12px;border:1px solid rgba(255,255,255,.26);
        background:rgba(255,255,255,.1);color:#fff;font-size:13px;font-weight:700;
        display:flex;align-items:center;justify-content:center;gap:7px;text-decoration:none;backdrop-filter:blur(8px)}

  .note{font-size:12px;color:rgba(255,255,255,.72);text-align:center;max-width:420px;line-height:1.45}
  .note.bad{color:#ff9d94}

  @media (prefers-reduced-motion:reduce){*{animation:none!important;transition:none!important}}
</style>
</head>
<body>

<div id="stage"><video id="cam" autoplay playsinline muted></video></div>

<div class="top">
  <a class="back" href="{{ route('me.events.show', $stream->event->uuid) }}" aria-label="Back">
    <i class="bi bi-arrow-left"></i>
  </a>
  <div class="who">
    <div class="mat">{{ $stream->label }}</div>
    <div class="ev">{{ $stream->event->title }}</div>
  </div>
  <span class="chip" id="state"><span class="dot"></span><span id="stateText">Ready</span></span>
</div>

<div class="bottom">
  <p class="note" id="note">Point the camera at the mat, then go live. The broadcast is recorded and kept with the bout.</p>

  <button class="go" id="go"><i class="bi bi-broadcast"></i> <span id="goText">Go live</span></button>

  <div class="row">
    <button id="flip"><i class="bi bi-arrow-repeat"></i> Flip camera</button>
    <button id="quality"><i class="bi bi-speedometer2"></i> <span id="qualityText">Quality</span></button>
  </div>
  <div class="row">
    <a href="{{ $watchUrl }}" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i> Watch link</a>
  </div>
</div>

<script>
(function () {
  'use strict';

  var TOKEN_URL  = @json(route('live.token', $stream));
  var STOP_URL   = @json(route('live.stop', $stream));
  var ORDERS_URL = @json(route('live.orders', $stream));
  var CAPTURE   = @json($capture);
  var CSRF      = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

  var video = document.getElementById('cam');
  var go = document.getElementById('go'), goText = document.getElementById('goText');
  var state = document.getElementById('state'), stateText = document.getElementById('stateText');
  var note = document.getElementById('note');

  var stream = null, pc = null, live = false, facing = 'environment', ticker = null, startedAt = null;

  /* ── Who decides when this mat is on air ─────────────────────────────────
     Both ends do, and they agree through the server rather than with each
     other. The console sets a standing order — live, or idle — and this page
     asks for it every few seconds and makes itself match.

     A beat rather than a socket, deliberately: there is nothing to keep alive,
     it survives the hall wifi dropping for a minute, and it works with the
     broker down. Three seconds is the whole latency budget between an organiser
     pressing Go live at the table and the picture appearing, which is well
     inside the warm-up before a bout.

     The same request is this phone's PRESENCE beat — answering it is what makes
     the console say a camera is standing by on this mat. So it runs whether or
     not we are publishing. */
  var ORDER_MS = 3000;
  var starting = false;      // a start is in flight; do not begin a second one
  var orderRefused = false;  // the last remote start failed — do not retry it every beat
  var orders = null;

  function beat() {
    fetch(ORDERS_URL, {
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      cache: 'no-store'
    })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (d) { if (d) obey(d); })
      .catch(function () { /* offline — the next beat will do */ });
  }

  function obey(d) {
    var wanted = d.desired === 'live';

    if (!wanted) {
      // The order was withdrawn. A failed start is forgiven here, so pressing
      // Go live again on the console actually tries again.
      orderRefused = false;

      if (live) {
        teardown();
        say('Stopped from the console. The recording is being prepared.');
      } else if (!starting) {
        standby();
      }

      return;
    }

    if (live || starting || orderRefused) return;

    // Told to go live by somebody who is not holding this phone. The camera is
    // already open and permission already granted, so nothing here needs a tap.
    say('Going live — asked for from the console.');
    goLive();
  }

  function standby() {
    if (live || starting) return;
    stateText.textContent = 'Standing by';
    say('Standing by. This mat can be put on air from the event console, or with the button below.');
  }

  /* ── Quality, and why it is not simply "the highest" ──────────────────────
     A phone filming a mat over mobile data cannot hold 1080p30: the uplink is
     the narrowest part of the whole chain, and when it saturates the result is
     not a softer picture, it is LOST PACKETS — which is what "choppy" actually
     is. So the ceiling is chosen for the link, and a fight is far better watched
     at 720p that arrives than 1080p that stutters.

     Motion matters more than detail here, so when the link tightens the encoder
     is told to drop RESOLUTION and keep the frame rate (degradationPreference):
     a blurry kick you can follow beats a sharp one that skips. */
  var PROFILES = {
    high:     { label: 'High · 1080p',   width: CAPTURE.width, height: CAPTURE.height, fps: CAPTURE.fps, bitrate: CAPTURE.bitrate },
    balanced: { label: 'Balanced · 720p', width: 1280, height: 720, fps: 30, bitrate: 2000000 },
    saver:    { label: 'Data saver · 540p', width: 960, height: 540, fps: 25, bitrate: 1000000 }
  };
  var ORDER = ['high', 'balanced', 'saver'];

  /* Mobile data defaults to the middle rung rather than the top one. Guessing
     wrong upward costs a stuttering broadcast; guessing wrong downward costs a
     little sharpness that one tap restores. */
  function defaultProfile() {
    try {
      var saved = window.localStorage.getItem('live.quality');
      if (saved && PROFILES[saved]) return saved;
    } catch (e) { /* private mode */ }

    var c = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
    if (c) {
      if (c.type === 'cellular') return 'balanced';
      if (['slow-2g', '2g', '3g'].indexOf(c.effectiveType) !== -1) return 'saver';
    }
    return 'high';
  }

  var quality = defaultProfile();

  function profile() { return PROFILES[quality]; }

  function say(text, bad) {
    note.textContent = text;
    note.className = 'note' + (bad ? ' bad' : '');
  }

  /* ── The camera ─────────────────────────────────────────────────────────
     Asked for by facing mode, at the configured ceiling. A phone that cannot
     do 1080p returns the closest it can rather than failing, which is why these
     are `ideal` and not `exact`. */
  function openCamera() {
    if (stream) { stream.getTracks().forEach(function (t) { t.stop(); }); }

    var p = profile();

    return navigator.mediaDevices.getUserMedia({
      video: {
        facingMode: { ideal: facing },
        width: { ideal: p.width },
        height: { ideal: p.height },
        frameRate: { ideal: p.fps }
      },
      audio: true
    }).then(function (s) {
      stream = s;
      video.srcObject = s;
      video.classList.toggle('rear', facing === 'environment');
      return s;
    });
  }

  /* ── Going live ─────────────────────────────────────────────────────────
     WHIP: one HTTP POST carrying an SDP offer, one SDP answer back. No
     signalling server, no websocket, nothing to keep alive. */
  function goLive() {
    starting = true;
    go.disabled = true;
    stateText.textContent = 'Connecting';
    say('Connecting to the hall…');

    fetch(TOKEN_URL, {
      method: 'POST',
      headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' }
    })
      .then(function (r) { if (!r.ok) throw new Error('token'); return r.json(); })
      .then(function (d) { return publish(d); })
      .catch(function (e) {
        starting = false;
        // Do not batter the hall's network retrying a start that just failed on
        // every beat. The console withdrawing and re-issuing the order — or the
        // button below — clears this.
        orderRefused = true;
        go.disabled = false;
        stateText.textContent = 'Ready';
        say(e && e.message === 'token'
          ? 'Could not start — you may no longer have permission to run this event.'
          : 'Could not connect. Check the wifi and try again.', true);
      });
  }

  function publish(d) {
    pc = new RTCPeerConnection({ iceServers: d.ice_servers || [] });

    var p = profile();
    var videoTrack = stream.getVideoTracks()[0];
    var audioTrack = stream.getAudioTracks()[0];

    /* ── H.264, not whatever the browser felt like offering ─────────────────
       This is not a preference, it is a requirement, and getting it wrong is
       silent: a phone that publishes VP8 streams fine over WebRTC, and then

         · the RECORDING contains no video at all — fMP4 cannot hold VP8, so the
           recorder skips the track and keeps only the audio, and the bout is
           lost while everything appears to have worked;
         · LL-HLS does not work either, so every viewer who cannot use WebRTC
           gets nothing.

       Both were observed. So H.264 is put first in the offer and, if the browser
       cannot do it at all, the operator is told rather than left to find out
       from a silent audio-only file. */
    var vt = pc.addTransceiver(videoTrack, {
      direction: 'sendonly',
      streams: [stream],
      sendEncodings: [{ maxBitrate: p.bitrate, maxFramerate: p.fps }]
    });

    if (audioTrack) {
      pc.addTransceiver(audioTrack, { direction: 'sendonly', streams: [stream] });
    }

    var caps = (window.RTCRtpSender && RTCRtpSender.getCapabilities)
      ? RTCRtpSender.getCapabilities('video') : null;

    if (caps && vt.setCodecPreferences) {
      var h264 = caps.codecs.filter(function (c) { return /h264/i.test(c.mimeType); });
      var rest = caps.codecs.filter(function (c) { return !/h264/i.test(c.mimeType); });

      if (h264.length) {
        vt.setCodecPreferences(h264.concat(rest));
      } else {
        say('This browser cannot send H.264 — the broadcast will play but will not be recorded. Try Chrome or Safari.', true);
      }
    }

    // Motion over detail: when the uplink tightens, shrink the picture rather
    // than dropping frames. A fight you can follow beats a sharp slideshow.
    try {
      var sp = vt.sender.getParameters();
      sp.degradationPreference = 'maintain-framerate';
      vt.sender.setParameters(sp).catch(function () {});
    } catch (e) { /* not supported everywhere */ }

    pc.onconnectionstatechange = function () {
      if (pc.connectionState === 'connected') { onLive(); }
      if (pc.connectionState === 'failed' || pc.connectionState === 'disconnected') { onDropped(); }
    };

    return pc.createOffer()
      .then(function (offer) { return pc.setLocalDescription(offer).then(function () { return offer; }); })
      .then(function () { return gathered(); })
      .then(function () {
        return fetch(d.whip_url, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/sdp',
            // WHIP carries the credential as a bearer token; the media server
            // hands it to Laravel, which burns it (single use).
            'Authorization': 'Bearer ' + d.token
          },
          body: pc.localDescription.sdp
        });
      })
      .then(function (r) {
        if (!r.ok) throw new Error('whip ' + r.status);
        return r.text();
      })
      .then(function (sdp) {
        // The answer is the truth about the codec, not the offer. Say so plainly
        // if it came back as anything other than H.264 — the broadcast will look
        // fine and the recording will be silent video-less audio.
        if (!/H264/i.test(sdp)) {
          say('Warning: this phone negotiated a codec other than H.264. The stream will play, but the recording will have no video.', true);
        }

        return pc.setRemoteDescription({ type: 'answer', sdp: sdp });
      });
  }

  /* Wait for ICE gathering, but never forever: on a network where some
     candidate type hangs, a broadcast that waits for completion never starts. */
  function gathered() {
    if (pc.iceGatheringState === 'complete') return Promise.resolve();

    return new Promise(function (resolve) {
      var done = false;
      function finish() { if (!done) { done = true; resolve(); } }
      pc.addEventListener('icegatheringstatechange', function () {
        if (pc.iceGatheringState === 'complete') finish();
      });
      setTimeout(finish, 2500);
    });
  }

  function onLive() {
    live = true;
    starting = false;
    orderRefused = false;
    startedAt = Date.now();
    go.disabled = false;
    go.className = 'go stop';
    goText.textContent = 'Stop';
    go.querySelector('i').className = 'bi bi-stop-fill';
    state.className = 'chip on';
    say('Live. This is being recorded and will be kept with the bout.');

    ticker = setInterval(function () {
      var s = Math.floor((Date.now() - startedAt) / 1000);
      stateText.textContent = 'Live ' + Math.floor(s / 60) + ':' + ('0' + (s % 60)).slice(-2);
    }, 1000);
  }

  function onDropped() {
    if (!live) return;
    say('The connection dropped. Press Go live to start again — what was recorded up to now is kept.', true);
    teardown();
  }

  function stop() {
    fetch(STOP_URL, {
      method: 'POST',
      headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' }
    }).catch(function () { /* the media server's own hook is the authority anyway */ });

    teardown();
    say('Stopped. The recording is being prepared — it will appear with the bout shortly.');
  }

  function teardown() {
    live = false;
    if (ticker) { clearInterval(ticker); ticker = null; }
    if (pc) { try { pc.close(); } catch (e) {} pc = null; }
    go.className = 'go';
    goText.textContent = 'Go live';
    go.querySelector('i').className = 'bi bi-broadcast';
    go.disabled = false;
    state.className = 'chip';
    stateText.textContent = 'Ready';
  }

  go.addEventListener('click', function () {
    // Pressing it here is the same instruction as pressing it on the console:
    // the token route arms the stream, the stop route disarms it, so the two
    // ends never fight over what this mat is supposed to be doing.
    orderRefused = false;
    live ? stop() : goLive();
  });

  /* Cycles High → Balanced → Data saver. Takes effect on the next Go live: the
     encoder's ceiling is set when the connection is made, and silently changing
     a broadcast that is already running is worse than making somebody restart. */
  function paintQuality() {
    document.getElementById('qualityText').textContent = profile().label;
  }

  document.getElementById('quality').addEventListener('click', function () {
    quality = ORDER[(ORDER.indexOf(quality) + 1) % ORDER.length];

    try { window.localStorage.setItem('live.quality', quality); } catch (e) {}

    paintQuality();

    if (live) {
      say('Quality changes on the next Go live — stop and start again to apply it.');
      return;
    }

    // Re-open the camera at the new size so the preview matches what will be sent.
    openCamera().catch(function () {});
  });

  paintQuality();

  document.getElementById('flip').addEventListener('click', function () {
    facing = (facing === 'environment') ? 'user' : 'environment';

    openCamera().then(function () {
      // Swap the track under a live broadcast rather than restarting it —
      // flipping the camera mid-bout must not drop the stream.
      if (!pc) return;
      var sender = pc.getSenders().filter(function (s) { return s.track && s.track.kind === 'video'; })[0];
      if (sender) sender.replaceTrack(stream.getVideoTracks()[0]);
    }).catch(function () { say('Could not switch camera.', true); });
  });

  // A broadcaster who closes the tab has stopped, whatever else happens.
  window.addEventListener('pagehide', function () { if (live) stop(); });

  openCamera().then(function () {
    standby();
    // Only once the camera is actually open: the beat reports this phone as
    // standing by, and a phone that cannot film should not be counted as one.
    beat();
    setInterval(beat, ORDER_MS);
  }).catch(function () {
    go.disabled = true;
    say('This needs camera and microphone access. Allow it in the browser, then reload.', true);
  });
})();
</script>
</body>
</html>
