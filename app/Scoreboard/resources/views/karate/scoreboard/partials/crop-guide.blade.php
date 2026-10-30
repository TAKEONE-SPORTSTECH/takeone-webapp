{{--
    The face guide over the console's photo cropper (owner, 2026-10-24:
    "people don't know how to position the picture for the VS screen").

    Placed inside #cropView, straight after #cropFrame, in every Karate console;
    it shows only while a photo is loaded (it hides with the frame), and never
    takes a pointer, so dragging the photo works through it.

    WHERE the lines go is not written here: it is the platform's setting
    (App\Scoreboard\Support\PhotoGuide), which a super-admin sets in Settings →
    Photo guide. Values are fractions of the photo; window.PhotoGuide.draw()
    turns them into this drawing, and the settings editor draws with the SAME
    function, so the editor and the cropper cannot disagree.
--}}
@once
<style>
  #cropFrame[hidden] + .cropguide{display:none;}
  .cropguide{position:absolute;inset:0;pointer-events:none;z-index:2;}
  .cropguide svg{display:block;width:100%;height:100%;overflow:visible;}
  .cropguide .ln{fill:none;stroke:rgba(255,255,255,.85);stroke-width:2;stroke-dasharray:7 6;
        vector-effect:non-scaling-stroke;filter:drop-shadow(0 0 2px rgba(0,0,0,.9));}
  .cropguide .eye{fill:none;stroke:#ffe135;stroke-width:2;vector-effect:non-scaling-stroke;
        filter:drop-shadow(0 0 2px rgba(0,0,0,.9));}
  .cropguide .hide{position:absolute;left:0;right:0;bottom:0;display:flex;flex-direction:column;
        align-items:center;justify-content:center;gap:4px;text-align:center;padding:0 12px;overflow:hidden;
        background:repeating-linear-gradient(135deg,rgba(10,11,16,.72) 0 8px,rgba(10,11,16,.58) 8px 16px);
        border-top:1px dashed rgba(255,255,255,.55);border-radius:0 0 10px 10px;}
  .cropguide .hide b{font-size:12px;letter-spacing:.12em;text-transform:uppercase;color:#ffe135;font-weight:700;}
  .cropguide .hide span{font-size:12px;letter-spacing:.06em;color:rgba(255,255,255,.8);}
</style>
<script>
  window.PhotoGuide = window.PhotoGuide || {
    current: @json(\App\Scoreboard\Support\PhotoGuide::get()),
    defaults: @json(\App\Scoreboard\Support\PhotoGuide::DEFAULTS),

    /* Draw guide g (fractions of the photo) into one .cropguide. Drawn in a
       330x440 viewBox — the cropper's own pixels — stretched to its box. */
    draw: function (root, g) {
      var W = 330, H = 440;
      var cx = g.cx * W, cy = g.cy * H, rx = g.rx * W, ry = g.ry * H, ey = g.eye * H, sh = g.shoulder * H;
      var set = function (sel, a) { var n = root.querySelector(sel); if (n) Object.keys(a).forEach(function (k) { n.setAttribute(k, a[k]); }); };
      set('.g-head', { cx: cx, cy: cy, rx: rx, ry: ry });
      set('.g-eye1', { x1: 18, x2: Math.max(18, cx - rx - 10), y1: ey, y2: ey });
      set('.g-eye2', { x1: Math.min(W - 18, cx + rx + 10), x2: W - 18, y1: ey, y2: ey });
      var nl = cx - rx * 0.49, nr = cx + rx * 0.49, chin = cy + ry;
      set('.g-body', { d: 'M' + nl + ' ' + chin + ' L' + nl + ' ' + sh + ' Q' + (nl - 64) + ' ' + (sh + 8) + ' ' + (nl - 108) + ' ' + (sh + 78)
                       + ' M' + nr + ' ' + chin + ' L' + nr + ' ' + sh + ' Q' + (nr + 64) + ' ' + (sh + 8) + ' ' + (nr + 108) + ' ' + (sh + 78) });
      set('.g-mid', { x1: cx, x2: cx, y1: 10, y2: Math.max(10, cy - ry - 8) });
      var band = root.querySelector('.hide');
      if (band) { band.style.height = ((1 - g.cut) * 100) + '%'; band.hidden = g.cut >= 0.995; }
    },

    /* A new guide for every cropper on the page (after a save). */
    apply: function (g) {
      var self = this;
      self.current = g;
      // An editor mid-change (data-own) keeps its draft; it redraws itself.
      document.querySelectorAll('.cropguide:not([data-own])').forEach(function (r) { self.draw(r, g); });
    },
  };
</script>
{{-- The VS screen beside the cropper (owner, 2026-10-25: "show the full VS
     screen as it shows, with all the data"). The REAL mat page (partials/
     vs-live → ScoreboardController::vsPreview), with this mat's competitors,
     and the photo being cropped drawn into the red corner as Save would send
     it: the crop box rendered to a canvas from the cropper's own photo layer
     (its natural size, position, translate, rotate and scale — the same
     transform the box shows), throttled, and handed over as a local image
     that never leaves this browser. Held in a hidden box until the crop
     window first opens, then moved beside the crop box. --}}
<div id="cropVsHold" hidden>
  <div>
    <div class="vscap">{{ __('scoreboard::karate_messages.pg_vs') }}</div>
    @include('scoreboard::karate.scoreboard.partials.vs-live', ['frameId' => 'cropLive', 'w' => 1000])
  </div>
</div>
<script>
  (function () {
    function mount() {
      var view = document.getElementById('cropView'), img = document.getElementById('cropImg'),
          hold = document.getElementById('cropVsHold'), box = document.getElementById('crop'),
          frame = document.getElementById('cropLive');
      if (!view || !img || !hold || !box || !frame || !window.VsLive || view.getAttribute('data-vs') === '1') return;
      view.setAttribute('data-vs', '1');

      var modal = view.closest('.modal');
      if (modal) modal.style.width = '1440px';
      var row = document.createElement('div');
      row.style.cssText = 'display:flex;gap:28px;align-items:center;justify-content:center;';
      view.parentNode.insertBefore(row, view);
      view.style.margin = '0';
      row.appendChild(view);
      row.appendChild(hold.firstElementChild);

      var live = window.VsLive(frame), side = 'aka';
      // Who is being cropped: an entry from the Players tab (window.__cropReg —
      // drawn in the red corner with their real name, club, age and weight), or
      // a corner of this mat's bout (window.__cropSide). Each opening starts the
      // VS screen afresh, so a previous crop never lingers in the other corner.
      new MutationObserver(function () {
        if (box.hidden) return;
        var reg = window.__cropReg;
        side = reg ? 'aka' : (window.__cropSide === 'ao' ? 'ao' : 'aka');
        live.reset();
        live.load(reg ? 'reg=' + encodeURIComponent(reg) : '', true);
      }).observe(box, { attributes: true, attributeFilter: ['hidden'] });

      var cache = { url: null, img: null }, timer = null;
      function draw(im) {
        var cv = document.createElement('canvas');
        cv.width = 660; cv.height = 880;
        var x = cv.getContext('2d');
        x.scale(2, 2);
        x.fillStyle = '#0a0b10'; x.fillRect(0, 0, 330, 440);
        var nw = parseFloat(img.style.width), nh = parseFloat(img.style.height);
        var L = parseFloat(img.style.left) || 0, T = parseFloat(img.style.top) || 0, t = img.style.transform || '';
        var tr = /translate\(([-\d.]+)px,\s*([-\d.]+)px\)/.exec(t) || [0, 0, 0];
        var ro = /rotate\(([-\d.]+)deg\)/.exec(t) || [0, 0];
        var sc = /scale\(([-\d.]+)\)/.exec(t) || [0, 1];
        if (!isFinite(nw) || !isFinite(nh)) return;
        x.translate(L + nw / 2 + (+tr[1]), T + nh / 2 + (+tr[2]));
        x.rotate((+ro[1]) * Math.PI / 180);
        x.scale(+sc[1], +sc[1]);
        x.drawImage(im, -nw / 2, -nh / 2, nw, nh);
        var msg = {};
        msg[side] = { local: cv.toDataURL('image/jpeg', 0.85), vs_frame: null };
        try { live.send(msg); } catch (e) {}
      }
      function render() {
        if (box.hidden) return;
        if (img.hidden) { var off = {}; off[side] = { local: null }; live.send(off); return; }
        var m = /url\("?([^")]+)"?\)/.exec(img.style.backgroundImage || '');
        if (!m) return;
        var url = m[1];
        if (cache.url === url && cache.img) { draw(cache.img); return; }
        var im = new Image();
        im.onload = function () { cache = { url: url, img: im }; draw(im); };
        im.src = url;
      }
      new MutationObserver(function () { clearTimeout(timer); timer = setTimeout(render, 90); })
        .observe(img, { attributes: true, attributeFilter: ['style', 'hidden'] });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', mount);
    else setTimeout(mount, 0);
  })();
</script>
@endonce
<div class="cropguide" aria-hidden="true">
  <svg viewBox="0 0 330 440" preserveAspectRatio="none">
    <ellipse class="ln g-head"></ellipse>
    <line class="eye g-eye1"></line>
    <line class="eye g-eye2"></line>
    <path class="ln g-body"></path>
    <line class="ln g-mid"></line>
  </svg>
  <div class="hide">
    <b>{{ __('scoreboard::karate_messages.ctl_crop_guide_fit') }}</b>
    <span>{{ __('scoreboard::karate_messages.ctl_crop_guide_hidden') }}</span>
  </div>
</div>
<script>
  (function () {
    var s = document.currentScript, r = s && s.previousElementSibling;
    if (r && window.PhotoGuide) window.PhotoGuide.draw(r, window.PhotoGuide.current);
  })();
</script>
