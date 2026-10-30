{{--
    The recorder: ONE voice-note and video recorder for every Margin page
    (the TakeNote app and the board's note sheet). Include it once; it defines
    window.MarginRecorder and owns its own full-screen overlay and styles.

      MarginRecorder.voice({ T, maxSeconds })            → Promise<{ blob, ms } | null>
      MarginRecorder.video({ T, maxSeconds, maxBytes })  → Promise<{ blob, ms } | null>

    null = the person cancelled. A rejection carries .code: 'denied' (no
    permission), 'too_big'. The phone records (MediaRecorder); the server
    re-encodes what it gets (Services\Clips), so whatever container the phone
    chose is fine.

    Device capability rules: support is detected first, permission is asked
    only when the person starts, the screen is kept awake while recording
    (Wake Lock, when the browser has it), and every stream is stopped on close
    so the camera / microphone light goes out. Where recording is impossible,
    the phone's own recorder is offered through a file input instead.
--}}
<style>
.rec { position: fixed; inset: 0; z-index: 120; display: flex; flex-direction: column; color: #fff; font-family: var(--sans, system-ui);
    padding: var(--sa-t, env(safe-area-inset-top, 0px)) 0 var(--sa-b, env(safe-area-inset-bottom, 0px)); animation: rec-in .28s cubic-bezier(.2,.8,.2,1); }
@keyframes rec-in { from { opacity: 0; transform: scale(1.03); } }
.rec.is-voice { background: radial-gradient(90% 55% at 50% 38%, #5a1f3f 0%, transparent 70%), #140d1f; }
.rec.is-video { background: #000; }
.rec-top { position: relative; z-index: 3; display: flex; align-items: center; gap: 10px; padding: 12px 16px; }
.rec-round { width: 42px; height: 42px; border-radius: 50%; display: grid; place-items: center; font-size: 17px; background: rgba(255,255,255,.12); border: 1px solid rgba(255,255,255,.18); color: #fff; flex-shrink: 0; transition: transform .15s; }
.rec-round:active { transform: scale(.9); }
.rec-round[disabled] { opacity: .35; }
.rec-chip { margin-inline: auto; display: inline-flex; align-items: center; gap: 7px; padding: 6px 12px; border-radius: 10px; font-weight: 700; font-size: 13px; font-variant-numeric: tabular-nums; background: rgba(0,0,0,.35); }
.rec-chip.is-live { background: #e0245e; }
.rec-dot { width: 8px; height: 8px; border-radius: 50%; background: #ff4d7d; animation: rec-pulse 1.2s ease-in-out infinite; }
.rec-chip .rec-dot { background: rgba(255,255,255,.6); animation: none; }
.rec-chip.is-live .rec-dot { background: #fff; animation: rec-pulse 1.2s ease-in-out infinite; }
.rec.is-paused .rec-dot { animation: none; opacity: .4; }
@keyframes rec-pulse { 50% { opacity: .25; } }
.rec-label { text-align: center; font-size: 11px; letter-spacing: .14em; text-transform: uppercase; color: rgba(255,255,255,.62); margin-top: 6vh; display: flex; justify-content: center; align-items: center; gap: 8px; }
.rec-time { text-align: center; font: 400 72px/1 var(--serif, Georgia, serif); margin-top: 12px; font-variant-numeric: tabular-nums; }
.rec-limit { text-align: center; font-size: 12px; color: rgba(255,255,255,.45); margin-top: 6px; }
.rec-wave { display: block; width: calc(100% - 36px); height: 128px; margin: 5vh auto 0; }
.rec-hint { margin: 22px 30px 0; text-align: center; font-size: 12.5px; line-height: 1.5; color: rgba(255,255,255,.6); }
.rec-bar { margin-top: auto; display: flex; align-items: center; justify-content: center; gap: 34px; padding: 18px 0 30px; position: relative; z-index: 3; }
.rec-big { width: 78px; height: 78px; border-radius: 50%; border: 3px solid #fff; display: grid; place-items: center; background: none; transition: transform .15s; }
.rec-big:active { transform: scale(.94); }
.rec-big i { display: block; width: 30px; height: 30px; border-radius: 8px; background: #ff4d7d; transition: border-radius .25s, width .25s, height .25s; }
.rec.is-video .rec-big i { width: 58px; height: 58px; border-radius: 50%; background: #e0245e; }
.rec.is-video.is-live .rec-big i { width: 28px; height: 28px; border-radius: 8px; }
.rec-spacer { width: 42px; height: 42px; flex-shrink: 0; }
.rec-view { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; background: #000; }
.rec-view.is-mirror { transform: scaleX(-1); }
.rec-grid { position: absolute; inset: 0; pointer-events: none; background-image: linear-gradient(rgba(255,255,255,.1) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.1) 1px, transparent 1px); background-size: 33.33% 33.33%; }
.rec-progress { position: absolute; left: 16px; right: 16px; bottom: calc(132px + var(--sa-b, env(safe-area-inset-bottom, 0px))); height: 4px; border-radius: 4px; background: rgba(255,255,255,.25); overflow: hidden; z-index: 3; }
.rec-progress i { display: block; height: 100%; width: 0; background: #fff; }
.rec.is-video .rec-bar { background: linear-gradient(transparent, rgba(0,0,0,.6)); }
.rec-ask { position: absolute; z-index: 5; left: 14px; right: 14px; bottom: calc(24px + var(--sa-b, env(safe-area-inset-bottom, 0px))); border-radius: 18px; background: #fff; color: #1d1530; padding: 14px; box-shadow: 0 20px 40px -10px rgba(0,0,0,.7); animation: rec-in .2s; }
.rec-ask b { display: block; font-size: 15px; margin-bottom: 12px; }
.rec-ask div { display: flex; gap: 8px; }
.rec-ask button { flex: 1; height: 44px; border-radius: 12px; font-weight: 700; font-size: 14px; border: 0; }
.rec-ask .no { background: #fee2e2; color: #b91c1c; }
.rec-ask .yes { background: #1d1530; color: #fff; }
@media (min-width: 700px) { .rec.is-voice .rec-wave { max-width: 520px; } }
@media (prefers-reduced-motion: reduce) { .rec, .rec-ask { animation: none; } .rec-dot, .rec-chip.is-live .rec-dot { animation: none; } }
</style>
<script>
(function () {
    'use strict';
    if (window.MarginRecorder) return;

    var AUDIO_TYPES = ['audio/webm;codecs=opus', 'audio/mp4', 'audio/webm', 'audio/ogg;codecs=opus'];
    var VIDEO_TYPES = ['video/mp4;codecs=avc1,mp4a', 'video/mp4', 'video/webm;codecs=vp9,opus', 'video/webm;codecs=vp8,opus', 'video/webm'];

    function h(tag, cls, text) {
        var el = document.createElement(tag);
        if (cls) el.className = cls;
        if (text != null) el.textContent = text;
        return el;
    }
    function icon(name) { return h('i', 'bi ' + name); }
    function btn(cls, ic, label) {
        var b = h('button', cls); b.type = 'button';
        if (ic) b.appendChild(icon(ic));
        if (label) { b.setAttribute('aria-label', label); b.title = label; }
        return b;
    }
    function clock(ms) {
        var s = Math.floor(ms / 1000);
        return Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0');
    }
    function supported() { return !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia && window.MediaRecorder); }
    function pickType(list) {
        for (var i = 0; i < list.length; i++) { try { if (MediaRecorder.isTypeSupported(list[i])) return list[i]; } catch (e) {} }
        return '';
    }
    function err(code) { var e = new Error(code); e.code = code; return e; }
    function stopStream(s) { if (s) s.getTracks().forEach(function (t) { t.stop(); }); }
    function wake() {
        var lock = null, gone = false;
        if (navigator.wakeLock && navigator.wakeLock.request) {
            navigator.wakeLock.request('screen').then(function (l) { if (gone) l.release().catch(function () {}); else lock = l; }).catch(function () {});
        }
        return function () { gone = true; if (lock) { lock.release().catch(function () {}); lock = null; } };
    }
    function buzz(ms) { if (navigator.vibrate) { try { navigator.vibrate(ms); } catch (e) {} } }

    /* Where the browser cannot record, the phone's own recorder through a file input. */
    function viaFile(accept, capture, maxBytes) {
        return new Promise(function (resolve, reject) {
            var inp = h('input'); inp.type = 'file'; inp.accept = accept; inp.hidden = true;
            if (capture) inp.setAttribute('capture', capture);
            inp.addEventListener('change', function () {
                var f = inp.files && inp.files[0];
                inp.remove();
                if (!f) { resolve(null); return; }
                if (maxBytes && f.size > maxBytes) { reject(err('too_big')); return; }
                resolve({ blob: f, ms: 0 });
            });
            document.body.appendChild(inp);
            inp.click();
        });
    }

    /* A small "throw it away?" panel — never a native dialog. */
    function ask(root, T, onYes) {
        if (root.querySelector('.rec-ask')) return;
        var p = h('div', 'rec-ask');
        var row = h('div');
        var no = h('button', 'no', T.rec_discard), yes = h('button', 'yes', T.rec_keep);
        no.type = yes.type = 'button';
        no.addEventListener('click', function () { p.remove(); onYes(); });
        yes.addEventListener('click', function () { p.remove(); });
        row.append(no, yes);
        p.append(h('b', null, T.rec_discard_q), row);
        root.appendChild(p);
    }

    /* ── voice ──────────────────────────────────────────────────────── */
    function voice(o) {
        var T = o.T, max = (o.maxSeconds || 900) * 1000;
        if (!supported()) return viaFile('audio/*', 'user');

        return navigator.mediaDevices.getUserMedia({ audio: { echoCancellation: true, noiseSuppression: true } }).catch(function () {
            throw err('denied');
        }).then(function (stream) {
            return new Promise(function (resolve) {
                var type = pickType(AUDIO_TYPES);
                var rec = new MediaRecorder(stream, type ? { mimeType: type, audioBitsPerSecond: 64000 } : undefined);
                var chunks = [], started = Date.now(), pausedAt = 0, pausedFor = 0, done = false, raf = 0, timer = 0;
                var release = wake();

                var root = h('div', 'rec is-voice'); root.setAttribute('role', 'dialog'); root.setAttribute('aria-label', T.rec_voice);
                var top = h('div', 'rec-top');
                var x = btn('rec-round', 'bi-x-lg', T.rec_cancel);
                top.appendChild(x);
                var label = h('div', 'rec-label'); label.append(h('span', 'rec-dot'), h('span', null, T.rec_recording));
                var time = h('div', 'rec-time', '0:00');
                time.setAttribute('role', 'timer');
                var limit = h('div', 'rec-limit', T.rec_limit.replace(':n', clock(max)));
                var canvas = h('canvas', 'rec-wave');
                canvas.setAttribute('aria-hidden', 'true');
                var hint = h('p', 'rec-hint', T.rec_screen_on);
                var bar = h('div', 'rec-bar');
                var pause = btn('rec-round', 'bi-pause-fill', T.rec_pause);
                var stop = btn('rec-big', null, T.rec_stop); stop.appendChild(h('i'));
                bar.append(pause, stop, h('span', 'rec-spacer'));
                root.append(top, label, time, limit, canvas, hint, bar);
                document.body.appendChild(root);

                // The live waveform: the level of the last few seconds, as bars.
                var levels = [], ctx2 = canvas.getContext('2d'), actx = null, analyser = null, data = null;
                try {
                    actx = new (window.AudioContext || window.webkitAudioContext)();
                    analyser = actx.createAnalyser(); analyser.fftSize = 512;
                    actx.createMediaStreamSource(stream).connect(analyser);
                    data = new Uint8Array(analyser.fftSize);
                } catch (e) {}
                var lastPush = 0;
                function draw(t) {
                    raf = requestAnimationFrame(draw);
                    var w = canvas.clientWidth, hh = canvas.clientHeight, dpr = window.devicePixelRatio || 1;
                    if (canvas.width !== Math.round(w * dpr)) { canvas.width = Math.round(w * dpr); canvas.height = Math.round(hh * dpr); }
                    if (t - lastPush > 70) {
                        lastPush = t;
                        var lvl = 0.04;
                        if (analyser && rec.state === 'recording') {
                            analyser.getByteTimeDomainData(data);
                            var sum = 0;
                            for (var i = 0; i < data.length; i++) { var v = (data[i] - 128) / 128; sum += v * v; }
                            lvl = Math.min(1, Math.sqrt(sum / data.length) * 3.2 + 0.04);
                        }
                        levels.push(lvl);
                    }
                    var n = Math.floor(w / 7);
                    while (levels.length > n) levels.shift();
                    ctx2.setTransform(dpr, 0, 0, dpr, 0, 0);
                    ctx2.clearRect(0, 0, w, hh);
                    var g = ctx2.createLinearGradient(0, 0, 0, hh); g.addColorStop(0, '#ff8fb1'); g.addColorStop(1, '#e0457b');
                    ctx2.fillStyle = g;
                    for (var k = 0; k < levels.length; k++) {
                        var bh = Math.max(4, levels[k] * hh);
                        var xk = w - (levels.length - k) * 7;
                        ctx2.beginPath();
                        if (ctx2.roundRect) ctx2.roundRect(xk, (hh - bh) / 2, 4, bh, 2); else ctx2.rect(xk, (hh - bh) / 2, 4, bh);
                        ctx2.fill();
                    }
                }
                raf = requestAnimationFrame(draw);

                function elapsed() { return (pausedAt || Date.now()) - started - pausedFor; }
                timer = setInterval(function () {
                    var ms = elapsed();
                    time.textContent = clock(ms);
                    if (ms >= max) finish(true);
                }, 250);

                rec.addEventListener('dataavailable', function (e) { if (e.data && e.data.size) chunks.push(e.data); });
                rec.start(1000);
                buzz(12);

                pause.addEventListener('click', function () {
                    if (rec.state === 'recording') {
                        rec.pause(); pausedAt = Date.now();
                        root.classList.add('is-paused'); label.lastChild.textContent = T.rec_paused;
                        pause.replaceChildren(icon('bi-mic-fill')); pause.setAttribute('aria-label', T.rec_resume); pause.title = T.rec_resume;
                    } else if (rec.state === 'paused') {
                        rec.resume(); pausedFor += Date.now() - pausedAt; pausedAt = 0;
                        root.classList.remove('is-paused'); label.lastChild.textContent = T.rec_recording;
                        pause.replaceChildren(icon('bi-pause-fill')); pause.setAttribute('aria-label', T.rec_pause); pause.title = T.rec_pause;
                    }
                });
                stop.addEventListener('click', function () { finish(true); });
                x.addEventListener('click', function () {
                    if (elapsed() < 2500) { finish(false); return; }
                    ask(root, T, function () { finish(false); });
                });

                function finish(keep) {
                    if (done) return;
                    done = true;
                    var ms = elapsed();
                    clearInterval(timer); cancelAnimationFrame(raf); release();
                    rec.addEventListener('stop', function () {
                        stopStream(stream);
                        if (actx) actx.close().catch(function () {});
                        root.remove();
                        if (!keep || !chunks.length) { resolve(null); return; }
                        buzz(10);
                        resolve({ blob: new Blob(chunks, { type: rec.mimeType || type || 'audio/webm' }), ms: ms });
                    });
                    try { rec.stop(); } catch (e) { stopStream(stream); root.remove(); resolve(null); }
                }
            });
        });
    }

    /* ── video ──────────────────────────────────────────────────────── */
    function video(o) {
        var T = o.T, max = (o.maxSeconds || 60) * 1000, maxBytes = o.maxBytes || 10 * 1024 * 1024;
        if (!supported()) return viaFile('video/*', 'environment', maxBytes);

        var facing = 'environment';
        function camera() {
            return navigator.mediaDevices.getUserMedia({
                audio: { echoCancellation: true, noiseSuppression: true },
                video: { facingMode: { ideal: facing }, width: { ideal: 1280 }, height: { ideal: 720 }, frameRate: { ideal: 30, max: 30 } }
            });
        }

        return camera().catch(function () { throw err('denied'); }).then(function (first) {
            return new Promise(function (resolve, reject) {
                var stream = first, rec = null, chunks = [], started = 0, timer = 0, done = false;
                var release = function () {};

                var root = h('div', 'rec is-video'); root.setAttribute('role', 'dialog'); root.setAttribute('aria-label', T.rec_video);
                var view = h('video', 'rec-view'); view.muted = true; view.playsInline = true; view.autoplay = true; view.setAttribute('playsinline', '');
                var top = h('div', 'rec-top');
                var x = btn('rec-round', 'bi-x-lg', T.rec_cancel);
                var chip = h('span', 'rec-chip'); chip.append(h('span', 'rec-dot'), h('span', null, '0:00 / ' + clock(max)));
                chip.setAttribute('role', 'timer');
                top.append(x, chip, h('span', 'rec-spacer'));
                var progress = h('div', 'rec-progress'); var fill = h('i'); progress.appendChild(fill);
                var bar = h('div', 'rec-bar');
                var gallery = btn('rec-round', 'bi-images', T.rec_gallery);
                var big = btn('rec-big', null, T.rec_start); big.appendChild(h('i'));
                var flip = btn('rec-round', 'bi-arrow-repeat', T.rec_flip);
                bar.append(gallery, big, flip);
                root.append(view, h('div', 'rec-grid'), top, progress, bar);
                document.body.appendChild(root);
                show();

                function show() {
                    view.srcObject = stream;
                    var s = stream.getVideoTracks()[0], set = s && s.getSettings ? s.getSettings() : {};
                    view.classList.toggle('is-mirror', set.facingMode ? set.facingMode === 'user' : facing === 'user');
                    view.play().catch(function () {});
                }
                function close() { clearInterval(timer); release(); stopStream(stream); root.remove(); }

                flip.addEventListener('click', function () {
                    if (rec) return;
                    facing = facing === 'environment' ? 'user' : 'environment';
                    stopStream(stream);
                    camera().then(function (s) { stream = s; show(); }).catch(function () { done = true; close(); reject(err('denied')); });
                });
                gallery.addEventListener('click', function () {
                    if (rec) return;
                    done = true; close();
                    viaFile('video/*', null, maxBytes).then(resolve, reject);
                });
                big.addEventListener('click', function () { if (!rec) start(); else finish(true); });
                x.addEventListener('click', function () {
                    if (!rec || Date.now() - started < 2500) { finish(false); return; }
                    ask(root, T, function () { finish(false); });
                });

                function start() {
                    var type = pickType(VIDEO_TYPES);
                    var opts = { videoBitsPerSecond: 1000000, audioBitsPerSecond: 64000 };
                    if (type) opts.mimeType = type;
                    try { rec = new MediaRecorder(stream, opts); } catch (e) { rec = new MediaRecorder(stream); }
                    rec.addEventListener('dataavailable', function (e) { if (e.data && e.data.size) chunks.push(e.data); });
                    rec.start(1000);
                    started = Date.now();
                    release = wake();
                    root.classList.add('is-live'); chip.classList.add('is-live');
                    big.setAttribute('aria-label', T.rec_stop); big.title = T.rec_stop;
                    flip.disabled = true; gallery.disabled = true;
                    buzz(12);
                    timer = setInterval(function () {
                        var ms = Date.now() - started;
                        chip.lastChild.textContent = clock(ms) + ' / ' + clock(max);
                        fill.style.width = Math.min(100, ms / max * 100) + '%';
                        if (ms >= max) finish(true);
                    }, 200);
                }
                function finish(keep) {
                    if (done) return;
                    done = true;
                    if (!rec) { close(); resolve(null); return; }
                    var ms = Date.now() - started;
                    clearInterval(timer);
                    rec.addEventListener('stop', function () {
                        close();
                        if (!keep || !chunks.length) { resolve(null); return; }
                        var blob = new Blob(chunks, { type: rec.mimeType || 'video/webm' });
                        if (blob.size > maxBytes) { reject(err('too_big')); return; }
                        buzz(10);
                        resolve({ blob: blob, ms: ms });
                    });
                    try { rec.stop(); } catch (e) { close(); resolve(null); }
                }
            });
        });
    }

    window.MarginRecorder = { voice: voice, video: video, supported: supported };
})();
</script>
