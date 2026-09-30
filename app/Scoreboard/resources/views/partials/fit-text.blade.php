{{--
  Fit a line of text to its box — the one routine every hall screen uses.

  A name on a wall screen is never cut: half a competitor's name from ten
  metres is a mistake, narrow type is only narrow. So a line marked
  `data-fit="MAX:MIN"` (px) is set at MAX, shrunk two pixels at a time to MIN
  while it overflows, and — if it still does not fit — condensed horizontally
  (never below 60%) toward the edge it is aligned to. Same two steps as
  fitName() on the scoreboards.

  ScreenFit.plate(row, plate, cols) also keeps the two halves of an "up next"
  row clear of the centre plate: the plate's width depends on its text (a long
  division name makes it wider), so each half's inner padding is set from the
  plate's real width rather than a guessed constant.

  Include once per page; @once makes a second include free.
--}}
@once
<script>
(function () {
  if (window.ScreenFit) return;

  function fit(n) {
    var spec = String(n.getAttribute('data-fit') || '').split(':');
    var max = parseFloat(spec[0]), min = parseFloat(spec[1]);
    if (!max || !min || !n.clientWidth) return;    // hidden: nothing to measure yet

    n.style.transform = 'none';
    if (n.__fitClip === undefined) n.__fitClip = n.style.overflow;   // the box's own clip, restored each pass
    n.style.overflow = n.__fitClip;
    var size = max;
    n.style.fontSize = size + 'px';
    for (var i = 0; i < 120 && size > min && n.scrollWidth > n.clientWidth; i++) {
      size -= 2;
      n.style.fontSize = size + 'px';
    }
    if (n.scrollWidth > n.clientWidth) {
      // Overflow always runs toward the END of the line (right in English,
      // left in Arabic) whatever the text alignment, so the squeeze is anchored
      // at the START: the condensed line then fills its box exactly.
      var rtl = getComputedStyle(n).direction === 'rtl';
      n.style.transformOrigin = (rtl ? 'right' : 'left') + ' center';
      var ratio = n.clientWidth / n.scrollWidth;
      n.style.transform = 'scaleX(' + Math.max(0.6, ratio).toFixed(3) + ')';
      // A clip on the box would cut the text BEFORE it is condensed, so the
      // condensed line would still end in half a word. When condensing is
      // enough, the overflow is shown and the transform brings it inside.
      if (ratio >= 0.6) n.style.overflow = 'visible';
    }
  }

  function all(root) {
    var list = (root || document).querySelectorAll('[data-fit]');
    for (var i = 0; i < list.length; i++) fit(list[i]);
  }

  /* `edge` is how far each half reaches past the row's centre line (the halves
     overlap there under their slanted cut). */
  function plate(p, left, right, edge, gap) {
    if (!p || !p.offsetWidth) return;
    var pad = Math.round(p.offsetWidth / 2 + (edge || 0) + (gap || 18)) + 'px';
    if (left) left.style.paddingRight = pad;
    if (right) right.style.paddingLeft = pad;
  }

  /** Fit a multi-line heading: whole words only, never broken inside a word,
      shrunk until its widest word fits and the block is no taller than maxH. */
  function lines(n, max, min, maxH) {
    if (!n || !n.clientWidth) return;
    var size = max;
    n.style.fontSize = size + 'px';
    for (var i = 0; i < 160 && size > min && (n.scrollWidth > n.clientWidth || (maxH && n.offsetHeight > maxH)); i++) {
      size -= 2;
      n.style.fontSize = size + 'px';
    }
  }

  var queued = false;
  function soon(root) {
    if (queued) return;
    queued = true;
    requestAnimationFrame(function () { queued = false; all(root); });
  }

  window.ScreenFit = { fit: fit, all: all, plate: plate, lines: lines, soon: soon };
  if (document.fonts && document.fonts.ready) document.fonts.ready.then(function () { all(); });
  window.addEventListener('resize', function () { soon(); });
})();
</script>
@endonce
