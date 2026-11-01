{{--
    The VS screen's script helpers — safe URLs, text and background setters,
    names that shrink-then-condense rather than wrap or truncate, flag URLs —
    included INSIDE the host page's script (it supplies el()). Needs $redRight
    (or CornerSide) and $L (CornerLook). Shared by every VS screen.
--}}
  // ── Untrusted values ─────────────────────────────────────────────────────
  // Names, clubs and image paths were all typed by a person. Text goes in
  // through textContent only, and a URL is dropped unless it is one we would
  // have generated: a hall screen is a publication surface.
  function safeUrl(u) {
    return (typeof u === 'string' && /^(https?:\/\/|\/)[^"'()\\\s]*$/.test(u)) ? u : null;
  }
  function text(id, v) { var n = el(id); if (n) n.textContent = (v == null ? '' : String(v)); }
  function bg(id, url) {
    var n = el(id), u = safeUrl(url);
    if (!n) return;
    n.style.backgroundImage = u ? 'url("' + encodeURI(u) + '")' : 'none';
  }
  /* ── A competitor's name never takes a second line ──────────────────────
     It is the thing the hall reads first, and a name broken across two lines
     reads as two people. So the type SHRINKS to fit rather than wrapping, and
     it is never truncated: an ellipsis through somebody's name on a wall is
     worse than small type.

     Measured, not guessed — scrollWidth against clientWidth with the line
     forced to nowrap. Only re-measured when the name actually changes, when a
     hidden layer becomes visible (a hidden element has no width to measure
     against), and once the webfonts have loaded, because Anton is far narrower
     than the fallback and fitting to the wrong metrics sizes every name wrong. */
  // Karate's red corner (AKA) stands on the right — App\\Sports\\Combat\\CornerSide.
  var RED_RIGHT = @json($redRight ?? \App\Sports\Combat\CornerSide::redRight('karate'));
  // Each corner's name and colours (Mat\CornerLook), for what the script draws.
  var LOOK = @json($L);
  var nameSpec = {};
  function setName(id, value, max, min) {
    var n = el(id);
    if (!n) return;
    nameSpec[id] = { v: value, max: max, min: min };
    if (n.textContent !== value) n.textContent = value;
    n.style.whiteSpace = 'nowrap';
    fitName(id);
  }
  function fitName(id) {
    var n = el(id), spec = nameSpec[id];
    if (!n || !spec) return;
    if (!n.clientWidth) return;              // not laid out yet — retry on show
    if (n.getAttribute('data-fit') === spec.v) return;

    // Step 1 — shrink the type. Measured against the panel's real width, which
    // is 639px on the scoreboard: a 24-character name lands at 40px.
    n.style.transform = 'none';
    var size = spec.max;
    n.style.fontSize = size + 'px';
    for (var i = 0; i < 120 && size > spec.min && n.scrollWidth > n.clientWidth; i++) {
      size -= 2;
      n.style.fontSize = size + 'px';
    }

    // Step 2 — a name long enough to still overflow at the floor is CONDENSED,
    // not cut. Broadcast graphics squeeze rather than truncate for the same
    // reason: half a competitor's name on a wall is a mistake, narrow type is
    // just narrow. Floored at 0.6 — past that it stops being readable and the
    // operator should shorten the name on the control page instead.
    if (n.scrollWidth > n.clientWidth) {
      var ratio = Math.max(0.6, n.clientWidth / n.scrollWidth);
      // Condense toward the edge the name is aligned to, so it never drifts
      // away from its own corner.
      // Anchored at the line's START: overflow runs toward its end however
      // the line is aligned, so an end-anchored squeeze pushed a long name's
      // tail past its own box.
      n.style.transformOrigin = getComputedStyle(n).direction === 'rtl' ? 'right center' : 'left center';
      n.style.transform = 'scaleX(' + ratio.toFixed(3) + ')';
    }

    n.setAttribute('data-fit', spec.v);
  }
  function refitNames() {
    Object.keys(nameSpec).forEach(function (id) {
      var n = el(id);
      if (n) n.removeAttribute('data-fit');   // re-measure from the top
      fitName(id);
    });
  }
  if (document.fonts && document.fonts.ready) document.fonts.ready.then(refitNames);

  function flagUrl(code) {
    return /^[a-z]{2}$/.test(String(code || '')) ? '/images/flags/' + code + '.svg' : null;
  }
