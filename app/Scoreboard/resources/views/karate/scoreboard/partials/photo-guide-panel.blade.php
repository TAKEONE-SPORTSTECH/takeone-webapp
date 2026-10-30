{{--
    Settings → Photo guide: where a face belongs in a competitor photo, set once
    for the platform (App\Scoreboard\Support\PhotoGuide) and drawn over every
    Karate console's photo cropper (partials/crop-guide).

    Left, the guide on a sample photo, with a handle for each thing that can
    move. Right, the same photo and guide as the mat's VS screen frames them —
    the panel's own numbers, not an approximation: 56% of a 1920x1080 stage
    (1075x1080), the photo `cover` at `center 12%`, the same drift, the same
    dark bands, the same corner cut. So "will the head be cut?" is answered by
    looking, not by trusting anyone's arithmetic.

    Rendered only for a super-admin (the host passes $photoGuideUrl); the
    endpoint checks again. Saves as it is told, so the console's Apply hides.
--}}
<style>
  #pgWrap{display:grid;grid-template-columns:412px minmax(0,1fr);gap:28px;align-items:start;}
  #pgWrap .pgcap{font-size:15px;letter-spacing:.16em;text-transform:uppercase;color:var(--muted);margin-bottom:8px;}
  #pgEdit{position:relative;width:412px;height:550px;border-radius:10px;border:2px solid var(--gold);
        background:var(--ink) center/cover no-repeat;touch-action:none;user-select:none;}
  #pgEdit .cropguide .hide{border-radius:0 0 8px 8px;}
  .pgh{position:absolute;width:26px;height:26px;margin:-13px 0 0 -13px;border-radius:50%;z-index:5;cursor:grab;
        background:#ffe135;border:3px solid #0a0b10;box-shadow:0 0 0 2px rgba(255,225,53,.5),0 4px 10px rgba(0,0,0,.6);}
  .pgh:active{cursor:grabbing;}
  .pgh.sq{border-radius:6px;}
  .pgh.w{background:#fff;}
  #pgVs .vsph .cropguide{opacity:.7;}
  #pgVs .vsph .cropguide .hide b, #pgVs .vsph .cropguide .hide span{display:none;}
  #pgBar{display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-top:16px;}
  #pgBar button, #pgBar label{font-size:17px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;border-radius:10px;
        padding:12px 18px;cursor:pointer;}
  #pgSay{font-size:16px;color:var(--muted);min-height:22px;margin-top:10px;}
</style>

<div class="spanel" data-panel="guide" hidden style="display:flex;flex-direction:column;gap:14px;">
  <div style="font-size:17px;color:var(--second);line-height:1.4;">{{ __('scoreboard::karate_messages.pg_hint') }}</div>

  <div id="pgWrap">
    <div>
      <div class="pgcap">{{ __('scoreboard::karate_messages.pg_cropper') }}</div>
      <div id="pgEdit" style="background-image:url('{{ \App\Members\Support\Avatar::placeholder('male') }}');">
        @include('scoreboard::karate.scoreboard.partials.crop-guide')
        <span class="pgh" data-h="move" title="{{ __('scoreboard::karate_messages.pg_h_move') }}"></span>
        <span class="pgh sq" data-h="size" title="{{ __('scoreboard::karate_messages.pg_h_size') }}"></span>
        <span class="pgh" data-h="eye" title="{{ __('scoreboard::karate_messages.pg_h_eye') }}"></span>
        <span class="pgh w" data-h="shoulder" title="{{ __('scoreboard::karate_messages.pg_h_shoulder') }}"></span>
        <span class="pgh w sq" data-h="cut" title="{{ __('scoreboard::karate_messages.pg_h_cut') }}"></span>
      </div>
    </div>

    <div>
      <div class="pgcap">{{ __('scoreboard::karate_messages.pg_vs') }}</div>
      @include('scoreboard::karate.scoreboard.partials.vs-preview', ['look' => $look, 'vsId' => 'pgVs', 'phId' => 'pgVsPhoto'])

      <div id="pgBar">
        <label style="background:var(--fill);color:var(--ao-ink);border:1px solid rgba(138,180,255,.4);">
          {{ __('scoreboard::karate_messages.pg_try') }}
          <input id="pgFile" type="file" accept="image/*" style="display:none;">
        </label>
        <button type="button" id="pgReset" style="background:transparent;color:var(--muted);border:1px solid var(--line-2);">{{ __('scoreboard::karate_messages.pg_reset') }}</button>
        <button type="button" id="pgSave" style="background:var(--green);color:#000;border:none;margin-left:auto;">{{ __('scoreboard::karate_messages.pg_save') }}</button>
      </div>
      <div id="pgSay"></div>
    </div>
  </div>
</div>

<script>
(function () {
  var URL_SAVE = @json($photoGuideUrl);
  var T = { saved: @json(__('scoreboard::karate_messages.pg_saved')), failed: @json(__('scoreboard::karate_messages.pg_failed')),
            unsaved: @json(__('scoreboard::karate_messages.pg_unsaved')) };
  var LIM = { cx: [0.2, 0.8], cy: [0.05, 0.8], rx: [0.05, 0.45], ry: [0.05, 0.45], eye: [0.02, 0.95], shoulder: [0.05, 0.98], cut: [0.3, 1] };
  var box = document.getElementById('pgEdit');
  if (!box || !window.PhotoGuide) return;
  var guide = box.querySelector('.cropguide');
  if (guide) guide.setAttribute('data-own', '1');   // a save from elsewhere must not move an unsaved drag
  var vsPhoto = document.getElementById('pgVsPhoto');
  var g = Object.assign({}, window.PhotoGuide.current);
  var photoUrl = null;

  function clamp(k, v) { return Math.min(LIM[k][1], Math.max(LIM[k][0], v)); }
  function say(msg, tone) {
    var s = document.getElementById('pgSay');
    s.textContent = msg || '';
    s.style.color = tone === 'ok' ? 'var(--green)' : (tone === 'bad' ? 'var(--aka-ink)' : 'var(--muted)');
  }

  function paint() {
    window.PhotoGuide.draw(guide, g);
    var pos = { move: [g.cx, g.cy], size: [g.cx + g.rx, g.cy + g.ry], eye: [0.93, g.eye], shoulder: [0.07, g.shoulder], cut: [0.5, g.cut] };
    box.querySelectorAll('.pgh').forEach(function (h) {
      var p = pos[h.getAttribute('data-h')];
      h.style.left = (p[0] * 100) + '%';
      h.style.top = (Math.min(p[1], 0.995) * 100) + '%';
    });
    // The VS preview: the same photo, and the same guide, framed as the mat frames it.
    vsPhoto.style.backgroundImage = box.style.backgroundImage;
    var old = vsPhoto.querySelector('.cropguide');
    var copy = guide.cloneNode(true);
    if (old) vsPhoto.replaceChild(copy, old); else vsPhoto.appendChild(copy);
  }

  // Dragging: the stage is scaled, so positions come from the box's own
  // on-screen rectangle as fractions — right at any scale.
  var drag = null;
  box.addEventListener('pointerdown', function (e) {
    var h = e.target.closest && e.target.closest('.pgh');
    if (!h) return;
    e.preventDefault();
    drag = { h: h.getAttribute('data-h'), id: e.pointerId, dy: g.eye - g.cy };
    try { box.setPointerCapture(e.pointerId); } catch (err) {}
  });
  box.addEventListener('pointermove', function (e) {
    if (!drag) return;
    var r = box.getBoundingClientRect();
    var fx = (e.clientX - r.left) / r.width, fy = (e.clientY - r.top) / r.height;
    if (drag.h === 'move') {
      g.cx = clamp('cx', fx); g.cy = clamp('cy', fy);
      g.eye = clamp('eye', g.cy + drag.dy);   // the eyes travel with the head
    } else if (drag.h === 'size') {
      g.rx = clamp('rx', Math.abs(fx - g.cx)); g.ry = clamp('ry', Math.abs(fy - g.cy));
    } else {
      g[drag.h] = clamp(drag.h, fy);
    }
    paint();
    say(T.unsaved);
  });
  ['pointerup', 'pointercancel'].forEach(function (ev) {
    box.addEventListener(ev, function (e) { if (drag) { try { box.releasePointerCapture(e.pointerId); } catch (err) {} drag = null; } });
  });

  // Try it on a real face. Stays in this browser — nothing is uploaded.
  document.getElementById('pgFile').onchange = function () {
    var f = this.files && this.files[0];
    if (!f || !/^image\//.test(f.type)) return;
    if (photoUrl) { try { URL.revokeObjectURL(photoUrl); } catch (err) {} }
    photoUrl = URL.createObjectURL(f);
    box.style.backgroundImage = 'url("' + photoUrl + '")';
    paint();
  };

  document.getElementById('pgReset').onclick = function () {
    g = Object.assign({}, window.PhotoGuide.defaults);
    paint();
    say(T.unsaved);
  };

  var saving = false;
  document.getElementById('pgSave').onclick = function () {
    if (saving || !URL_SAVE) return;
    saving = true;
    var btn = this;
    btn.disabled = true;
    fetch(URL_SAVE, {
      method: 'POST',
      headers: { 'Accept': 'application/json', 'Content-Type': 'application/json',
                 'X-CSRF-TOKEN': (document.querySelector('meta[name=csrf-token]') || {}).content || '' },
      credentials: 'same-origin',
      body: JSON.stringify({ guide: g }),
    })
      .then(function (r) { return r.json().catch(function () { return {}; }).then(function (d) { return { ok: r.ok, d: d }; }); })
      .then(function (x) {
        saving = false; btn.disabled = false;
        if (!x.ok || !x.d.success) { say((x.d && x.d.message) || T.failed, 'bad'); return; }
        g = Object.assign({}, x.d.guide);
        window.PhotoGuide.apply(x.d.guide);   // this console's cropper, at once
        paint();
        say(x.d.message || T.saved, 'ok');
      })
      .catch(function () { saving = false; btn.disabled = false; say(T.failed, 'bad'); });
  };

  paint();
})();
</script>
