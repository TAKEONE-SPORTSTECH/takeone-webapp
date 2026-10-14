/*
 * The dashboard's image cropper — TakeOneFed.cropImage(options).
 *
 * The platform's own cropper (<x-takeone-cropper>) is a Blade component; this
 * page is not Blade, so it gets one of its own, self-contained: it opens the
 * file picker, shows the picture in a fixed frame to drag and zoom, and
 * answers with the crop as a data URL (or null when cancelled).
 *
 * The crop is drawn from the SOURCE pixels at the output size, never from the
 * on-screen frame scaled up (CLAUDE.md → One Cropper Everywhere: "never upscale
 * a viewport-sized crop"). The server checks the bytes again whatever arrives.
 *
 * Resolves to the data URL, null when the person cancelled, or {error} when
 * the file chosen was not a picture we take.
 *
 *   TakeOneFed.cropImage({width: 600, height: 800})            // a face, 3:4
 *   TakeOneFed.cropImage({width: 512, height: 512, png: true}) // a logo
 */
(function () {
  'use strict';
  window.TakeOneFed = window.TakeOneFed || {};

  var GOLD = '#d8b25f', NAVY = '#1e2c4f';

  function el(tag, css, attrs) {
    var n = document.createElement(tag);
    if (css) n.style.cssText = css;
    if (attrs) Object.keys(attrs).forEach(function (k) { n.setAttribute(k, attrs[k]); });
    return n;
  }

  function pickFile() {
    return new Promise(function (resolve) {
      var input = el('input', 'position:fixed;left:-9999px', {type: 'file', accept: 'image/jpeg,image/png,image/webp'});
      var done = false;
      input.addEventListener('change', function () { done = true; resolve(input.files && input.files[0] ? input.files[0] : null); input.remove(); });
      // A picker closed with nothing chosen fires no change; focus coming back says so.
      window.addEventListener('focus', function back() {
        window.removeEventListener('focus', back);
        setTimeout(function () { if (!done) { resolve(null); input.remove(); } }, 1500);
      });
      document.body.appendChild(input);
      input.click();
    });
  }

  function load(file) {
    return new Promise(function (resolve) {
      if (!file) { resolve(null); return; }
      if (!/^image\/(jpeg|png|webp)$/.test(file.type) || file.size > 15 * 1024 * 1024) { resolve({error: 'Choose a JPG, PNG or WebP picture under 15 MB.'}); return; }
      var url = URL.createObjectURL(file);
      var img = new Image();
      img.onload = function () { resolve({img: img, url: url}); };
      img.onerror = function () { URL.revokeObjectURL(url); resolve({error: 'That picture could not be opened.'}); };
      img.src = url;
    });
  }

  function dialog(src, opt) {
    return new Promise(function (resolve) {
      var outW = opt.width, outH = opt.height;
      // The frame on screen keeps the output's proportion, sized to fit.
      var fh = Math.min(400, Math.round(window.innerHeight * 0.55));
      var fw = Math.round(fh * outW / outH);
      if (fw > window.innerWidth - 80) { fw = window.innerWidth - 80; fh = Math.round(fw * outH / outW); }
      var iw = src.img.naturalWidth, ih = src.img.naturalHeight;
      var minScale = Math.max(fw / iw, fh / ih);
      var scale = minScale, x = (fw - iw * scale) / 2, y = (fh - ih * scale) / 2;

      var shade = el('div', 'position:fixed;inset:0;background:rgba(30,44,79,.55);z-index:90;display:grid;place-items:center;padding:20px;font-family:inherit');
      var box = el('div', 'background:#fff;border-radius:20px;border-top:4px solid ' + GOLD + ';box-shadow:0 30px 80px rgba(30,44,79,.35);padding:20px 24px;max-width:calc(100vw - 40px);box-sizing:border-box', {role: 'dialog', 'aria-modal': 'true', 'aria-label': opt.title || 'Crop the picture'});
      var head = el('div', 'display:flex;align-items:center;gap:12px;margin-bottom:14px');
      var title = el('div', 'font-size:1.05rem;font-weight:700;color:' + NAVY);
      title.textContent = opt.title || 'Crop the picture';
      var hint = el('div', 'font-size:.72rem;color:#6b7689;margin-top:2px');
      hint.textContent = 'Drag to move · scroll or use the slider to zoom';
      var tw = el('div'); tw.appendChild(title); tw.appendChild(hint); head.appendChild(tw);

      var frame = el('div', 'position:relative;width:' + fw + 'px;height:' + fh + 'px;margin:0 auto;overflow:hidden;border-radius:' + (opt.round ? '50%' : '12px') + ';background:#eef1f6;cursor:grab;touch-action:none;box-shadow:inset 0 0 0 2px rgba(216,178,95,.7)');
      var pic = el('img', 'position:absolute;left:0;top:0;max-width:none;user-select:none;pointer-events:none;transform-origin:0 0', {alt: '', draggable: 'false'});
      pic.src = src.url;
      frame.appendChild(pic);

      var zoom = el('input', 'width:100%;margin:16px 0 4px;accent-color:' + GOLD, {type: 'range', min: '1', max: '4', step: '0.01', value: '1', 'aria-label': 'Zoom'});
      var foot = el('div', 'display:flex;gap:10px;justify-content:flex-end;margin-top:12px');
      var cancel = el('button', 'height:40px;padding:0 18px;border:1px solid #d5dae3;border-radius:11px;background:#fff;font-family:inherit;font-size:.8rem;font-weight:600;color:#5d6880;cursor:pointer', {type: 'button'});
      cancel.textContent = 'Cancel';
      var ok = el('button', 'height:40px;padding:0 20px;border:0;border-radius:11px;background:linear-gradient(100deg,#edcd85,#d0a854);font-family:inherit;font-size:.8rem;font-weight:700;color:' + NAVY + ';cursor:pointer;box-shadow:0 6px 16px rgba(202,161,79,.3)', {type: 'button'});
      ok.textContent = opt.okLabel || 'Use this picture';
      foot.appendChild(cancel); foot.appendChild(ok);
      box.appendChild(head); box.appendChild(frame); box.appendChild(zoom); box.appendChild(foot);
      shade.appendChild(box);
      document.body.appendChild(shade);

      function clamp() {
        x = Math.min(0, Math.max(fw - iw * scale, x));
        y = Math.min(0, Math.max(fh - ih * scale, y));
      }
      function paint() { clamp(); pic.style.transform = 'translate(' + x + 'px,' + y + 'px) scale(' + scale + ')'; }
      function zoomTo(next, cx, cy) {
        next = Math.max(minScale, Math.min(minScale * 4, next));
        cx = cx == null ? fw / 2 : cx; cy = cy == null ? fh / 2 : cy;
        x = cx - (cx - x) * next / scale; y = cy - (cy - y) * next / scale;
        scale = next; zoom.value = String(scale / minScale); paint();
      }
      paint();

      var drag = null;
      frame.addEventListener('pointerdown', function (e) { drag = {px: e.clientX, py: e.clientY, x: x, y: y}; frame.setPointerCapture(e.pointerId); frame.style.cursor = 'grabbing'; });
      frame.addEventListener('pointermove', function (e) { if (!drag) return; x = drag.x + e.clientX - drag.px; y = drag.y + e.clientY - drag.py; paint(); });
      frame.addEventListener('pointerup', function () { drag = null; frame.style.cursor = 'grab'; });
      frame.addEventListener('pointercancel', function () { drag = null; });
      frame.addEventListener('wheel', function (e) {
        e.preventDefault();
        var r = frame.getBoundingClientRect();
        zoomTo(scale * (e.deltaY < 0 ? 1.08 : 1 / 1.08), e.clientX - r.left, e.clientY - r.top);
      }, {passive: false});
      zoom.addEventListener('input', function () { zoomTo(minScale * Number(zoom.value)); });

      function close(val) {
        document.removeEventListener('keydown', onKey, true);
        shade.remove(); URL.revokeObjectURL(src.url); resolve(val);
      }
      function onKey(e) { if (e.key === 'Escape') { e.stopPropagation(); close(null); } }
      document.addEventListener('keydown', onKey, true);
      shade.addEventListener('click', function (e) { if (e.target === shade) close(null); });
      cancel.addEventListener('click', function () { close(null); });
      ok.addEventListener('click', function () {
        // The frame's window onto the SOURCE image, drawn at the output size.
        var sx = -x / scale, sy = -y / scale, sw = fw / scale, sh = fh / scale;
        var c = el('canvas'); c.width = outW; c.height = outH;
        var g = c.getContext('2d');
        g.imageSmoothingQuality = 'high';
        if (!opt.png) { g.fillStyle = '#fff'; g.fillRect(0, 0, outW, outH); }
        g.drawImage(src.img, sx, sy, sw, sh, 0, 0, outW, outH);
        close(c.toDataURL(opt.png ? 'image/png' : 'image/jpeg', 0.92));
      });
      ok.focus();
    });
  }

  window.TakeOneFed.cropImage = function (options) {
    var opt = Object.assign({width: 600, height: 800, png: false, round: false}, options || {});
    return pickFile().then(load).then(function (src) {
      if (!src || src.error) return src;
      return dialog(src, opt);
    });
  };
})();
