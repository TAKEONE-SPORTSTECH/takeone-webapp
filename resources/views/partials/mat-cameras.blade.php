{{--
    THE MAT'S CAMERAS — one panel, every sport's scoring table.

    Dropped inside a console's settings dialog as a tab body. It is standalone
    by contract (Component-First → Standalone): it owns its markup, its styling,
    its polling, its requests and its confirmations, and it is wired by two data
    attributes and nothing else. No console has to know how it works, and a
    fourth sport gets it by including this file.

    ── What it answers ─────────────────────────────────────────────────────────

    A person at the scoring table is not asking the questions the event console
    answers. They are asking: will angle 2 last the session, did last bout's
    video ever leave the phone, and can I fix how it is filming without walking
    over to the tripod. So the panel is a RAIL of cameras (because a mat has up
    to four and the answer is per-camera), and for the selected one: telemetry,
    how it films, and every recording with what became of it.

    ── Intent and fact are drawn apart ─────────────────────────────────────────

    Every order is a message to a phone on a hall's wifi. A press therefore
    shows as "asked · waiting for the camera" until the phone's next beat
    reports the value back — the panel never redraws a number as if the order
    had already been obeyed. That is the same rule the live-feed switch follows,
    and it is the difference between a panel and a wish.

    ── Nothing here can strand a mat ───────────────────────────────────────────

    It polls only while it is on screen, it never blocks the console, and every
    failure is a line inside this panel. A camera asleep in a bag must not be
    able to slow down scoring a bout.

    Inputs (both required, both server-built):
      $cameraUrl          GET  — the panel's own JSON for this mat
      $cameraCommandBase  POST — prefix; the camera's id is appended
--}}

@php
    // A console renders the tab only when it was given the addresses, so a
    // surface with no camera door shows no camera tab rather than an empty one.
    $cameraUrl = $cameraUrl ?? null;
    $cameraCommandBase = $cameraCommandBase ?? null;
@endphp

@if ($cameraUrl && $cameraCommandBase)
<div class="mc" id="matCameras"
     data-url="{{ $cameraUrl }}"
     data-command-base="{{ $cameraCommandBase }}">

    <div class="mc-head">
        <span class="mc-title">{{ __('events.mat_cameras_footage') }}</span>
        <span class="mc-note" data-mc-note></span>
        <button type="button" class="mc-btn" data-mc-refresh>
            <span aria-hidden="true">⟳</span> {{ __('events.mat_cameras_refresh') }}
        </button>
    </div>

    {{-- The rail. Every camera on this mat at a glance — which is the whole
         answer to "there may be more than one": the reader never has to open
         anything to see that angle 3 is on 4% battery. --}}
    <div class="mc-rail" data-mc-rail></div>

    {{-- The selected camera. --}}
    <div class="mc-body" data-mc-body></div>

    <div class="mc-empty" data-mc-empty hidden>
        <div class="mc-empty-t">{{ __('events.mat_cameras_none') }}</div>
        <div class="mc-empty-s">{{ __('events.mat_cameras_none_hint') }}</div>
    </div>
</div>

{{-- The verbatim block below is load-bearing. This stylesheet contains a
     media query, and Blade reads an at-rule followed by a bracket as one of
     its own directives and tries to balance the brackets — which fails on the
     attribute selectors further down and takes the whole console page with it,
     at RENDER time rather than at compile time. Note the same trap applies to
     THIS comment: naming that at-rule here, even inside a Blade comment, is
     enough to trip it. --}}
@verbatim
<style>
/* Scoped to .mc, and every colour falls back twice: the three consoles name
   their palettes differently (--panel/--inset here, --card/--sheet there), and
   a panel that inherited nothing would render black on black. */
.mc{display:flex;flex-direction:column;gap:14px;min-width:0;
    --mc-panel:var(--panel,var(--card,#111320));
    --mc-inset:var(--inset,var(--sheet,#0e1017));
    --mc-line:var(--line,rgba(255,255,255,.18));
    --mc-text:var(--text,#e8eaf2);
    --mc-muted:var(--muted,rgba(232,230,224,.55));
    --mc-faint:var(--faint,rgba(232,230,224,.38));
    --mc-gold:var(--gold,#ffe135);
    --mc-green:var(--green,#3ddc84);
    --mc-red:#ff6b78;}
.mc *{box-sizing:border-box;}
.mc-head{display:flex;align-items:center;gap:12px;}
.mc-title{font-size:17px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--mc-muted);}
.mc-note{flex:1;font-size:14px;color:var(--mc-faint);min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.mc-note[data-tone="bad"]{color:var(--mc-red);}
.mc-note[data-tone="good"]{color:var(--mc-green);}
.mc-btn{font-size:14px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;background:var(--mc-inset);
        color:var(--mc-text);border:1px solid var(--mc-line);border-radius:10px;padding:9px 14px;cursor:pointer;}
.mc-btn:hover{border-color:var(--mc-gold);color:var(--mc-gold);}
.mc-btn[disabled]{opacity:.4;cursor:default;}
.mc-btn.is-danger:hover{border-color:var(--mc-red);color:var(--mc-red);}

/* The rail */
.mc-rail{display:flex;gap:10px;flex-wrap:wrap;}
.mc-cam{flex:1 1 190px;min-width:0;text-align:left;background:var(--mc-inset);border:1px solid var(--mc-line);
        border-radius:14px;padding:12px 14px;cursor:pointer;display:flex;flex-direction:column;gap:6px;}
.mc-cam[aria-selected="true"]{border-color:var(--mc-gold);box-shadow:inset 0 0 0 1px var(--mc-gold);}
.mc-cam-top{display:flex;align-items:center;gap:8px;}
.mc-cam-name{font-size:17px;font-weight:700;color:var(--mc-text);letter-spacing:.04em;
             overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.mc-dot{width:9px;height:9px;border-radius:50%;flex:0 0 auto;background:var(--mc-green);}
.mc-dot.is-off{background:var(--mc-gold);}
.mc-rec{font-size:11px;font-weight:800;letter-spacing:.12em;background:#e5342c;color:#fff;border-radius:6px;padding:2px 6px;}
.mc-cam-facts{display:flex;gap:10px;flex-wrap:wrap;font-size:13px;color:var(--mc-muted);}
.mc-warn{color:var(--mc-gold);font-weight:700;}

/* Telemetry tiles */
.mc-tiles{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;}
.mc-tile{background:var(--mc-inset);border:1px solid var(--mc-line);border-radius:14px;padding:12px 14px;min-width:0;}
.mc-tile-k{font-size:12px;letter-spacing:.14em;text-transform:uppercase;color:var(--mc-faint);}
.mc-tile-v{font-size:26px;font-weight:700;color:var(--mc-text);line-height:1.15;margin-top:4px;
           overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.mc-tile-s{font-size:13px;color:var(--mc-muted);margin-top:2px;
           overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.mc-bar{height:5px;border-radius:99px;background:rgba(255,255,255,.1);margin-top:8px;overflow:hidden;}
.mc-bar span{display:block;height:100%;background:var(--mc-green);border-radius:99px;}
.mc-bar.is-low span{background:var(--mc-gold);}

/* How it films */
.mc-sec{background:var(--mc-inset);border:1px solid var(--mc-line);border-radius:14px;padding:14px;
        display:flex;flex-direction:column;gap:12px;}
.mc-sec-h{font-size:13px;letter-spacing:.16em;text-transform:uppercase;color:var(--mc-faint);}
.mc-row{display:flex;align-items:center;gap:12px;flex-wrap:wrap;}
.mc-row-k{font-size:16px;color:var(--mc-muted);min-width:120px;}
.mc-seg{display:flex;gap:6px;}
.mc-seg button{font-size:15px;font-weight:700;background:transparent;color:var(--mc-text);
               border:1px solid var(--mc-line);border-radius:9px;padding:8px 14px;cursor:pointer;}
.mc-seg button[aria-pressed="true"]{border-color:var(--mc-gold);color:var(--mc-gold);}
.mc-seg button.is-pending{border-style:dashed;color:var(--mc-gold);opacity:.75;}
.mc-val{font-size:18px;font-weight:700;color:var(--mc-text);min-width:56px;text-align:center;}
.mc-hint{font-size:13px;color:var(--mc-faint);}
.mc-switch{width:52px;height:28px;border-radius:99px;border:1px solid var(--mc-line);background:rgba(255,255,255,.06);
           position:relative;cursor:pointer;padding:0;flex:0 0 auto;}
.mc-switch span{position:absolute;top:3px;left:3px;width:20px;height:20px;border-radius:50%;
                background:var(--mc-muted);transition:left .16s ease,background .16s ease;}
.mc-switch[aria-checked="true"]{background:rgba(61,220,132,.22);border-color:var(--mc-green);}
.mc-switch[aria-checked="true"] span{left:26px;background:var(--mc-green);}
.mc-switch.is-pending{border-style:dashed;}

/* Footage */
.mc-clips{display:flex;flex-direction:column;gap:1px;max-height:320px;overflow-y:auto;}
.mc-clip{display:flex;align-items:center;gap:12px;padding:10px 4px;border-bottom:1px solid var(--mc-line);}
.mc-clip:last-child{border-bottom:none;}
.mc-clip-m{flex:1;min-width:0;}
.mc-clip-t{font-size:17px;font-weight:600;color:var(--mc-text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.mc-clip-s{font-size:13px;color:var(--mc-faint);margin-top:2px;}
.mc-chip{font-size:11px;font-weight:800;letter-spacing:.1em;text-transform:uppercase;border-radius:99px;
         padding:4px 9px;border:1px solid currentColor;flex:0 0 auto;}
.mc-chip.is-vault{color:var(--mc-green);}
.mc-chip.is-sending{color:var(--mc-gold);}
.mc-chip.is-failed{color:var(--mc-red);}
.mc-chip.is-phone{color:var(--mc-faint);}
.mc-chip.is-gone{color:var(--mc-faint);opacity:.6;}
.mc-act{width:36px;height:36px;border-radius:10px;background:transparent;border:1px solid var(--mc-line);
        color:var(--mc-text);cursor:pointer;font-size:15px;line-height:1;flex:0 0 auto;}
.mc-act:hover{border-color:var(--mc-gold);color:var(--mc-gold);}
.mc-act.is-danger:hover{border-color:var(--mc-red);color:var(--mc-red);}
.mc-act.is-armed{border-color:var(--mc-red);color:var(--mc-red);}
.mc-act[disabled]{opacity:.35;cursor:default;}
.mc-hold{position:relative;overflow:hidden;}
.mc-hold i{position:absolute;inset:0 auto 0 0;background:rgba(255,107,120,.28);width:0;pointer-events:none;}
.mc-empty{padding:26px 8px;text-align:center;}
.mc-empty-t{font-size:19px;font-weight:700;color:var(--mc-muted);}
.mc-empty-s{font-size:14px;color:var(--mc-faint);margin-top:6px;}
@media (max-width:900px){.mc-tiles{grid-template-columns:repeat(2,minmax(0,1fr));}}
</style>
@endverbatim

@php
    /*
     * Every string this panel says, handed to its runtime in one object.
     *
     * Built HERE rather than inline: Blade's `@json()` stops at the first `])`
     * it meets, so an array with a nested one (a translation taking a
     * replacement, which several of these do) is silently truncated and the
     * compiled view is broken PHP. Pre-assigning is the documented shape.
     */
    $matCameraStrings = [
    'live' => __('events.mat_cameras_live'),
    'offline' => __('events.mat_cameras_offline'),
    'rec' => __('events.mat_cameras_rec'),
    'angle' => __('events.mat_cameras_angle', ['n' => '%n%']),
    'storage' => __('events.mat_cameras_storage'),
    'battery' => __('events.mat_cameras_battery'),
    'clips' => __('events.mat_cameras_clips'),
    'beat' => __('events.mat_cameras_beat'),
    'of' => __('events.mat_cameras_of', ['total' => '%total%']),
    'on_phone' => __('events.mat_cameras_on_phone', ['n' => '%n%']),
    'in_vault' => __('events.mat_cameras_in_vault', ['n' => '%n%']),
    'never' => __('events.mat_cameras_never'),
    'capture' => __('events.mat_cameras_capture'),
    'fps' => __('events.mat_cameras_fps'),
    'zoom' => __('events.mat_cameras_zoom'),
    'exposure' => __('events.mat_cameras_exposure'),
    'auto' => __('events.mat_cameras_auto_upload'),
    'auto_hint' => __('events.mat_cameras_auto_upload_hint'),
    'pending' => __('events.mat_cameras_pending'),
    'unknown' => __('events.mat_cameras_unknown'),
    'footage' => __('events.mat_cameras_footage'),
    'footage_none' => __('events.mat_cameras_footage_none'),
    'send_all' => __('events.mat_cameras_send_all'),
    'free_space' => __('events.mat_cameras_free_space'),
    'free_space_hint' => __('events.mat_cameras_free_space_hint'),
    'erase_all' => __('events.mat_cameras_erase_all'),
    'erase_all_hint' => __('events.mat_cameras_erase_all_hint', ['n' => '%n%']),
    'erase_hold' => __('events.mat_cameras_erase_all_hold'),
    'bout' => __('events.mat_cameras_bout', ['n' => '%n%']),
    'clip' => __('events.mat_cameras_clip'),
    'play' => __('events.mat_cameras_play'),
    'upload' => __('events.mat_cameras_upload'),
    'del' => __('events.mat_cameras_delete'),
    'del_confirm' => __('events.mat_cameras_delete_confirm'),
    'del_only' => __('events.mat_cameras_delete_only_copy'),
    's_phone' => __('events.mat_cameras_state_phone'),
    's_sending' => __('events.mat_cameras_state_sending'),
    's_processing' => __('events.mat_cameras_state_processing'),
    's_vault' => __('events.mat_cameras_state_vault'),
    's_failed' => __('events.mat_cameras_state_failed'),
    's_gone' => __('events.mat_cameras_state_gone'),
    'asked' => __('events.mat_cameras_asked_report'),
    ];
@endphp

<script>
/*
 * The panel's whole runtime. Vanilla, because the three consoles it is dropped
 * into are vanilla — a panel that needed Alpine would work on one of them.
 *
 * Guarded against double-definition: a console may include this once, but the
 * mobile shell re-runs inline scripts on navigation and two copies of this
 * would poll twice and fight over the same DOM.
 */
(function () {
  if (window.__matCamerasInit) return;
  window.__matCamerasInit = true;

  var root = document.getElementById('matCameras');
  if (!root) return;

  var URL_PANEL = root.dataset.url;
  var URL_CMD   = root.dataset.commandBase;
  var railEl    = root.querySelector('[data-mc-rail]');
  var bodyEl    = root.querySelector('[data-mc-body]');
  var emptyEl   = root.querySelector('[data-mc-empty]');
  var noteEl    = root.querySelector('[data-mc-note]');

  var T = @json($matCameraStrings);

  var cameras = [];
  var picked = null;     // id of the camera whose detail is open
  var busy = false;
  var loading = false;

  function csrf() {
    var m = document.querySelector('meta[name=csrf-token]');
    return m ? m.content : '';
  }

  /* Everything server-supplied goes through here before it reaches the DOM.
     A camera's label is typed by whoever set the phone up, and a clip's ref is
     a filename off that phone — both are strangers' text on an organiser's
     screen. Built with textContent where possible; this is for the few places
     a string is composed into markup. */
  function esc(v) {
    return String(v === null || v === undefined ? '' : v)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  function fill(text, token, value) { return String(text).split(token).join(value); }

  function size(bytes) {
    if (!bytes && bytes !== 0) return '';
    if (bytes >= 1073741824) return (bytes / 1073741824).toFixed(1) + ' GB';
    if (bytes >= 1048576) return Math.round(bytes / 1048576) + ' MB';
    if (bytes >= 1024) return Math.round(bytes / 1024) + ' KB';
    return bytes + ' B';
  }

  function clock(seconds) {
    if (!seconds && seconds !== 0) return '';
    var m = Math.floor(seconds / 60), s = seconds % 60;
    return m + ':' + (s < 10 ? '0' : '') + s;
  }

  function note(message, tone) {
    noteEl.textContent = message || '';
    if (tone) { noteEl.setAttribute('data-tone', tone); } else { noteEl.removeAttribute('data-tone'); }
  }

  /* ── Talking to the server ─────────────────────────────────────────────── */

  function load() {
    if (loading) return Promise.resolve();
    loading = true;

    return fetch(URL_PANEL, {
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    }).then(function (r) { return r.ok ? r.json() : Promise.reject(r); })
      .then(function (d) {
        cameras = (d && d.cameras) || [];
        paint();
      })
      .catch(function () {
        /* A read that failed is not worth a red line at a mat mid-bout: the
           panel simply keeps showing the last answer it had. */
      })
      .finally(function () { loading = false; });
  }

  /* One order. `body.do` names it; the camera id goes in the path. */
  function send(id, body, said) {
    if (busy) return Promise.resolve();
    busy = true;
    paint();

    return fetch(URL_CMD + id, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrf(),
        'X-Requested-With': 'XMLHttpRequest',
      },
      credentials: 'same-origin',
      body: JSON.stringify(body),
    }).then(function (r) { return r.json().catch(function () { return {}; }).then(function (d) {
        if (!r.ok || d.success === false) throw new Error(d.message || ('HTTP ' + r.status));
        return d;
      }); })
      .then(function (d) {
        // A settings write hands back the camera; everything else is an ask
        // whose answer arrives on the phone's next beat.
        if (d.camera) { replace(d.camera); }
        note(said || d.message || '', 'good');
      })
      .catch(function (e) { note(e.message || 'Failed', 'bad'); })
      .finally(function () { busy = false; paint(); });
  }

  function replace(camera) {
    for (var i = 0; i < cameras.length; i++) {
      if (cameras[i].id === camera.id) { cameras[i] = camera; return; }
    }
    cameras.push(camera);
  }

  function current() {
    for (var i = 0; i < cameras.length; i++) { if (cameras[i].id === picked) return cameras[i]; }
    return cameras[0] || null;
  }

  /* ── Drawing ──────────────────────────────────────────────────────────── */

  function paint() {
    emptyEl.hidden = cameras.length > 0;
    railEl.innerHTML = '';
    bodyEl.innerHTML = '';

    if (!cameras.length) return;

    var cam = current();
    picked = cam.id;

    // The rail: every camera on this mat, always visible. With one camera it is
    // one card, which reads as a header rather than a chooser.
    cameras.forEach(function (c) {
      railEl.appendChild(railCard(c, c.id === picked));
    });

    bodyEl.appendChild(tiles(cam));
    bodyEl.appendChild(capture(cam));
    bodyEl.appendChild(footage(cam));
  }

  function name(c) {
    return c.label || fill(T.angle, '%n%', c.angle == null ? '?' : c.angle);
  }

  function railCard(c, isOn) {
    var el = document.createElement('button');
    el.type = 'button';
    el.className = 'mc-cam';
    el.setAttribute('aria-selected', isOn ? 'true' : 'false');
    el.onclick = function () { picked = c.id; paint(); };

    var facts = [];
    if (c.storage_free_gb !== null && c.storage_free_gb !== undefined) {
      facts.push('<span class="' + ((c.storage_free_percent !== null && c.storage_free_percent < 10) ? 'mc-warn' : '') + '">'
        + esc(c.storage_free_gb) + ' GB</span>');
    }
    if (c.battery !== null && c.battery !== undefined) {
      facts.push('<span class="' + (c.battery < 20 ? 'mc-warn' : '') + '">' + esc(c.battery) + '%</span>');
    }
    facts.push(esc(c.clips || 0) + ' ' + esc(T.clips.toLowerCase()));

    el.innerHTML =
      '<span class="mc-cam-top">'
      + '<span class="mc-dot ' + (c.live ? '' : 'is-off') + '"></span>'
      + '<span class="mc-cam-name">' + esc(name(c)) + '</span>'
      + (c.recording ? '<span class="mc-rec">' + esc(T.rec) + '</span>' : '')
      + '</span>'
      + '<span class="mc-cam-facts">' + facts.join('') + '</span>';

    return el;
  }

  function tile(k, v, s, bar, low) {
    var el = document.createElement('div');
    el.className = 'mc-tile';
    el.innerHTML = '<div class="mc-tile-k">' + esc(k) + '</div>'
      + '<div class="mc-tile-v">' + esc(v) + '</div>'
      + '<div class="mc-tile-s">' + esc(s || '') + '</div>'
      + (bar === null || bar === undefined ? ''
         : '<div class="mc-bar' + (low ? ' is-low' : '') + '"><span style="width:'
           + Math.max(0, Math.min(100, bar)) + '%"></span></div>');
    return el;
  }

  function tiles(c) {
    var wrap = document.createElement('div');
    wrap.className = 'mc-tiles';

    var freePct = (c.storage_free_percent === null || c.storage_free_percent === undefined) ? null : c.storage_free_percent;

    wrap.appendChild(tile(
      T.storage,
      (c.storage_free_gb === null || c.storage_free_gb === undefined) ? '—' : c.storage_free_gb + ' GB',
      c.storage_total_gb ? fill(T.of, '%total%', c.storage_total_gb + ' GB') : '',
      freePct, freePct !== null && freePct < 10
    ));

    wrap.appendChild(tile(
      T.battery,
      (c.battery === null || c.battery === undefined) ? '—' : c.battery + '%',
      c.recording ? T.rec : '',
      (c.battery === null || c.battery === undefined) ? null : c.battery,
      c.battery !== null && c.battery !== undefined && c.battery < 20
    ));

    wrap.appendChild(tile(
      T.clips,
      String(c.clips || 0),
      fill(T.on_phone, '%n%', c.on_phone || 0) + ' · ' + fill(T.in_vault, '%n%', c.in_vault || 0)
    ));

    wrap.appendChild(tile(
      T.beat,
      c.live ? T.live : T.offline,
      c.last_seen || T.never
    ));

    return wrap;
  }

  /* How it films. Each control shows the REPORTED value, and marks itself
     pending when an order for a different value has not been confirmed. */
  function capture(c) {
    var s = c.settings || {}, p = c.pending || {};

    var sec = document.createElement('div');
    sec.className = 'mc-sec';

    var h = document.createElement('div');
    h.className = 'mc-sec-h';
    h.textContent = T.capture;
    sec.appendChild(h);

    /* Frame rate */
    var fpsRow = row(T.fps);
    var seg = document.createElement('div');
    seg.className = 'mc-seg';
    [30, 60].forEach(function (rate) {
      var b = document.createElement('button');
      b.type = 'button';
      b.textContent = rate;
      b.setAttribute('aria-pressed', s.fps === rate ? 'true' : 'false');
      if (p.fps === rate) b.className = 'is-pending';
      b.disabled = busy;
      b.onclick = function () { send(c.id, { do: 'settings', settings: { fps: rate } }); };
      seg.appendChild(b);
    });
    fpsRow.appendChild(seg);
    if (p.fps !== undefined) fpsRow.appendChild(hint(T.pending));
    else if (s.fps === undefined) fpsRow.appendChild(hint(T.unknown));
    sec.appendChild(fpsRow);

    /* Zoom — presets plus a nudge either way, which is what somebody framing a
       mat from a tablet actually does. */
    var zoomRow = row(T.zoom);
    var zseg = document.createElement('div');
    zseg.className = 'mc-seg';
    [1, 2, 4].forEach(function (z) {
      var b = document.createElement('button');
      b.type = 'button';
      b.textContent = z + '×';
      b.setAttribute('aria-pressed', Math.abs((s.zoom || 1) - z) < 0.05 ? 'true' : 'false');
      if (p.zoom !== undefined && Math.abs(p.zoom - z) < 0.05) b.className = 'is-pending';
      b.disabled = busy;
      b.onclick = function () { send(c.id, { do: 'settings', settings: { zoom: z } }); };
      zseg.appendChild(b);
    });
    zoomRow.appendChild(zseg);
    zoomRow.appendChild(stepper(
      (s.zoom === undefined ? '—' : Number(s.zoom).toFixed(1) + '×'),
      function (delta) {
        var next = Math.round(Math.max(1, Math.min(10, (s.zoom || 1) + delta)) * 10) / 10;
        send(c.id, { do: 'settings', settings: { zoom: next } });
      }, 0.5
    ));
    if (p.zoom !== undefined) zoomRow.appendChild(hint(T.pending));
    sec.appendChild(zoomRow);

    /* Exposure, in the sensor's own steps. */
    var evRow = row(T.exposure);
    evRow.appendChild(stepper(
      (s.exposure === undefined ? '—' : (s.exposure > 0 ? '+' : '') + Number(s.exposure).toFixed(1)),
      function (delta) {
        var next = Math.round(Math.max(-4, Math.min(4, (s.exposure || 0) + delta)) * 10) / 10;
        send(c.id, { do: 'settings', settings: { exposure: next } });
      }, 0.3
    ));
    if (p.exposure !== undefined) evRow.appendChild(hint(T.pending));
    sec.appendChild(evRow);

    /* Auto upload. The one setting that decides what a camera does with a bout
       once it is over, so it says what OFF means rather than leaving it to be
       discovered at the end of the day. */
    var upRow = row(T.auto);
    var sw = document.createElement('button');
    sw.type = 'button';
    sw.className = 'mc-switch' + (p.auto_upload !== undefined ? ' is-pending' : '');
    sw.setAttribute('role', 'switch');
    sw.setAttribute('aria-checked', s.auto_upload ? 'true' : 'false');
    sw.setAttribute('aria-label', T.auto);
    sw.disabled = busy;
    sw.innerHTML = '<span></span>';
    sw.onclick = function () {
      send(c.id, { do: 'settings', settings: { auto_upload: !s.auto_upload } });
    };
    upRow.appendChild(sw);
    upRow.appendChild(hint(p.auto_upload !== undefined ? T.pending
      : (s.auto_upload ? '' : T.auto_hint)));
    sec.appendChild(upRow);

    return sec;
  }

  function row(label) {
    var r = document.createElement('div');
    r.className = 'mc-row';
    var k = document.createElement('span');
    k.className = 'mc-row-k';
    k.textContent = label;
    r.appendChild(k);
    return r;
  }

  function hint(text) {
    var h = document.createElement('span');
    h.className = 'mc-hint';
    h.textContent = text || '';
    return h;
  }

  function stepper(value, onStep, step) {
    var wrap = document.createElement('span');
    wrap.className = 'mc-seg';

    var minus = document.createElement('button');
    minus.type = 'button'; minus.textContent = '−'; minus.disabled = busy;
    minus.onclick = function () { onStep(-step); };

    var val = document.createElement('span');
    val.className = 'mc-val';
    val.textContent = value;

    var plus = document.createElement('button');
    plus.type = 'button'; plus.textContent = '+'; plus.disabled = busy;
    plus.onclick = function () { onStep(step); };

    wrap.appendChild(minus); wrap.appendChild(val); wrap.appendChild(plus);
    return wrap;
  }

  /* What it has filmed, and what became of each one. */
  function footage(c) {
    var sec = document.createElement('div');
    sec.className = 'mc-sec';

    var head = document.createElement('div');
    head.className = 'mc-row';
    var h = document.createElement('span');
    h.className = 'mc-sec-h';
    h.style.flex = '1';
    h.textContent = T.footage;
    head.appendChild(h);

    var unsent = (c.footage || []).filter(function (f) { return !f.uploaded && f.on_device !== false; }).length;

    head.appendChild(button(T.send_all, function () {
      send(c.id, { do: 'upload', clip: 'all' });
    }, busy || !(c.footage || []).length));

    head.appendChild(button(T.free_space, function () {
      send(c.id, { do: 'wipe', scope: 'uploaded' });
    }, busy || !c.in_vault, T.free_space_hint));

    // Erase everything is the one order here that can destroy the only copy of
    // a bout, so it is not a press: it is a hold, and it says out loud how many
    // recordings exist nowhere else.
    head.appendChild(hold(T.erase_all, T.erase_hold, function () {
      send(c.id, { do: 'wipe', scope: 'all' });
    }, busy || !(c.footage || []).length,
      unsent ? fill(T.erase_all_hint, '%n%', unsent) : ''));

    sec.appendChild(head);

    var list = document.createElement('div');
    list.className = 'mc-clips';

    if (!(c.footage || []).length) {
      list.appendChild(hint(T.footage_none));
    }

    (c.footage || []).forEach(function (f) { list.appendChild(clipRow(c, f)); });
    sec.appendChild(list);

    return sec;
  }

  function state(f) {
    if (f.on_device === false && !f.uploaded) return { k: 'is-gone', t: T.s_gone };
    if (f.uploaded) return { k: 'is-vault', t: T.s_vault };
    if (f.status === 'uploading') return { k: 'is-sending', t: T.s_sending };
    if (f.status === 'processing') return { k: 'is-sending', t: T.s_processing };
    if (f.status === 'failed') return { k: 'is-failed', t: T.s_failed };
    return { k: 'is-phone', t: T.s_phone };
  }

  function clipRow(c, f) {
    var el = document.createElement('div');
    el.className = 'mc-clip';

    var meta = document.createElement('div');
    meta.className = 'mc-clip-m';

    var title = document.createElement('div');
    title.className = 'mc-clip-t';
    title.textContent = f.bout ? fill(T.bout, '%n%', f.bout) : (T.clip + ' ' + (f.at || ''));

    var sub = document.createElement('div');
    sub.className = 'mc-clip-s';
    sub.textContent = [f.at, clock(f.seconds), size(f.bytes)].filter(Boolean).join(' · ');

    meta.appendChild(title);
    meta.appendChild(sub);
    el.appendChild(meta);

    var st = state(f);
    var chip = document.createElement('span');
    chip.className = 'mc-chip ' + st.k;
    chip.textContent = st.t;
    el.appendChild(chip);

    var gone = f.on_device === false;

    el.appendChild(act('▶', T.play, function () {
      send(c.id, { do: 'play', clip: f.ref });
    }, busy || gone));

    el.appendChild(act('↑', T.upload, function () {
      send(c.id, { do: 'upload', clip: f.ref });
    }, busy || gone || f.uploaded));

    // Two presses, never one, and the second says what is being lost when the
    // recording has never left the phone.
    var del = act('✕', T.del, null, busy || gone, true);
    del.onclick = function () {
      if (del.classList.contains('is-armed')) {
        send(c.id, { do: 'delete', clip: f.ref });
        return;
      }
      del.classList.add('is-armed');
      del.title = f.uploaded ? T.del_confirm : (T.del_confirm + ' ' + T.del_only);
      note(f.uploaded ? T.del_confirm : (T.del_confirm + ' ' + T.del_only), f.uploaded ? null : 'bad');
      setTimeout(function () { del.classList.remove('is-armed'); }, 4000);
    };
    el.appendChild(del);

    return el;
  }

  function act(glyph, label, onClick, disabled, danger) {
    var b = document.createElement('button');
    b.type = 'button';
    b.className = 'mc-act' + (danger ? ' is-danger' : '');
    b.textContent = glyph;
    b.title = label;
    b.setAttribute('aria-label', label);
    b.disabled = !!disabled;
    if (onClick) b.onclick = onClick;
    return b;
  }

  function button(label, onClick, disabled, title) {
    var b = document.createElement('button');
    b.type = 'button';
    b.className = 'mc-btn';
    b.textContent = label;
    if (title) b.title = title;
    b.disabled = !!disabled;
    b.onclick = onClick;
    return b;
  }

  /* Hold-to-confirm. 1.6 seconds, with the bar filling as it goes, and it does
     nothing at all if the finger leaves early. */
  function hold(label, holdLabel, onDone, disabled, warning) {
    var b = document.createElement('button');
    b.type = 'button';
    b.className = 'mc-btn is-danger mc-hold';
    b.disabled = !!disabled;
    b.title = warning || '';
    b.innerHTML = '<i></i>' + esc(label);

    var bar = b.querySelector('i');
    var started = 0, raf = 0;

    function stop() {
      cancelAnimationFrame(raf);
      raf = 0; started = 0;
      bar.style.width = '0';
      b.innerHTML = '<i></i>' + esc(label);
      bar = b.querySelector('i');
    }

    function tick() {
      var pct = Math.min(100, (performance.now() - started) / 1600 * 100);
      bar.style.width = pct + '%';
      if (pct >= 100) { stop(); onDone(); return; }
      raf = requestAnimationFrame(tick);
    }

    function begin(e) {
      if (b.disabled || started) return;
      e.preventDefault();
      started = performance.now();
      b.innerHTML = '<i></i>' + esc(holdLabel);
      bar = b.querySelector('i');
      if (warning) note(warning, 'bad');
      raf = requestAnimationFrame(tick);
    }

    b.addEventListener('mousedown', begin);
    b.addEventListener('touchstart', begin, { passive: false });
    ['mouseup', 'mouseleave', 'touchend', 'touchcancel'].forEach(function (ev) {
      b.addEventListener(ev, function () { if (started) stop(); });
    });

    return b;
  }

  /* ── When it runs ─────────────────────────────────────────────────────── */

  root.querySelector('[data-mc-refresh]').onclick = function () {
    var c = current();
    if (c) send(c.id, { do: 'report' }, T.asked);
    setTimeout(load, 1500);
  };

  /*
   * Polls only while it is on screen.
   *
   * No console has to tell this panel that its tab was opened: an element
   * inside a hidden dialog has no offsetParent, so the tick simply does
   * nothing until somebody looks at it. That is what keeps it drop-in — and it
   * is also what stops a tablet at a mat spending the day polling a panel
   * nobody has opened.
   */
  var seen = false;
  var ticks = 0;

  setInterval(function () {
    var visible = root.offsetParent !== null;

    if (!visible) { seen = false; return; }

    // The tab was just opened: read at once, so nobody looks at an empty panel
    // while a poll interval runs down.
    if (!seen) { seen = true; ticks = 0; load(); return; }

    // Then every ~10 seconds. A camera beats every 30, so polling faster only
    // re-reads the same row; this is fast enough that a press followed by the
    // phone's answer looks like one action.
    if (++ticks % 4 === 0) load();
  }, 2500);

  // And once now, for a console that renders this panel already open.
  if (root.offsetParent !== null) { seen = true; load(); }
})();
</script>
@endif
