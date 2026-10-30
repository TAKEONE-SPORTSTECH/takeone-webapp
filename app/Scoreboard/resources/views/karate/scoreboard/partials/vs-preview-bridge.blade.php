{{--
    The VS screen as a PREVIEW inside the console (ScoreboardController::
    vsPreview): the console's editors post changes here and the mat page draws
    them with its own code, so the preview is the wall, not a copy of it.

    Messages (same origin only): {type:'vs-preview',
      aka|ao: {photo?, local?, vs_frame?},   a corner's photo / framing
      layout: {b, k, s}}                     the VS layout (VsLayout; s = show stats)

    `photo` goes through the mat's own update(), which accepts only http(s)
    addresses. `local` is a picture that exists only in the console's browser
    (a photo being cropped, a "try a photo") — a blob: or data:image URL,
    checked here, and painted onto this preview's own panel; it never reaches
    a real wall screen. Rendered only when the page is a preview.
--}}
<script>
(function () {
  var base = @json($state);
  var LOCAL = /^(blob:https?:\/\/[^"'()\s]+|data:image\/(png|jpeg|webp);base64,[A-Za-z0-9+\/=]+)$/;
  var local = { aka: null, ao: null };

  function frameCss(n, fr) {
    var z = fr ? parseFloat(fr.z) : NaN, x = fr ? parseFloat(fr.x) : NaN, y = fr ? parseFloat(fr.y) : NaN;
    var ok = isFinite(z) && isFinite(x) && isFinite(y);
    n.style.backgroundSize = ok ? (Math.min(3, Math.max(1, z)) * 100) + '% auto' : 'cover';
    n.style.backgroundPosition = ok ? Math.min(100, Math.max(0, x)) + '% ' + Math.min(100, Math.max(0, y)) + '%' : 'center 12%';
  }
  function paintLocal() {
    [['aka', 'vsRedPhoto'], ['ao', 'vsBluePhoto']].forEach(function (p) {
      var n = document.getElementById(p[1]), u = local[p[0]];
      if (!n || !u) return;
      n.style.backgroundImage = 'url("' + u + '")';
      n.style.opacity = '1';
      frameCss(n, base[p[0]] && base[p[0]].vs_frame);
    });
  }

  window.addEventListener('message', function (e) {
    var d = e.data;
    if (e.origin !== window.location.origin || !d || d.type !== 'vs-preview') return;
    var s = JSON.parse(JSON.stringify(base)), touched = false;
    ['aka', 'ao'].forEach(function (k) {
      var c = d[k];
      if (!c || !s[k]) return;
      if ('photo' in c) { s[k].photo = c.photo; local[k] = null; touched = true; }
      if ('vs_frame' in c) { s[k].vs_frame = c.vs_frame; touched = true; }
      if ('local' in c) { local[k] = (typeof c.local === 'string' && LOCAL.test(c.local)) ? c.local : null; s[k].vs_frame = c.vs_frame || null; touched = true; }
    });
    if (touched) { base = s; if (window.CourtBoard) window.CourtBoard.update(s); paintLocal(); }
    if (d.layout) {
      var vs = document.getElementById('vs'), b = parseFloat(d.layout.b), k = parseFloat(d.layout.k);
      if (vs && isFinite(b) && isFinite(k)) {
        vs.style.setProperty('--vs-info-bottom', Math.min(700, Math.max(0, b)) + 'px');
        vs.style.setProperty('--vs-info-scale', Math.min(1.3, Math.max(0.5, k)));
      }
      if (vs && d.layout.s !== undefined) vs.setAttribute('data-stats', d.layout.s ? '1' : '0');
    }
  });

  if (window.parent && window.parent !== window) window.parent.postMessage({ type: 'vs-preview-ready' }, window.location.origin);
})();
</script>
