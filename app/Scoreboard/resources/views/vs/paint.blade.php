{{--
    Fills scoreboard::vs.arena from a corner state (aka / ao: name, club,
    country, flag, logo, photo, fallback, vs_frame, record, belt, age,
    height, weight) — included INSIDE the host page's script, which supplies
    el(), text(), bg(), setName(), flagUrl(), EVENT_TITLE and COURT.
    $vsFlag = 'nationality' flies the athlete's own flag (the Combat style).
--}}
  function chip(host, value, gold) {
    if (!value) return;   // an absent fact is hidden, never invented
    var d = document.createElement('div');
    // Big enough to read from the far side of the hall (owner, 2026-10-16):
    // the age · weight line under the club was barely visible at 23.8px.
    d.style.cssText = 'font-weight:800; font-size:34px; letter-spacing:0.1em; text-transform:uppercase; padding:9px 20px; line-height:1.15;' +
      'background:rgba(10,10,14,0.82); border:2px solid ' + (gold ? 'rgba(253,196,54,0.85)' : 'rgba(255,255,255,0.55)') + ';' +
      'color:' + (gold ? '#e8c96a' : '#fff') + ';';
    d.textContent = value;
    host.appendChild(d);
  }

  function statLine(c) {
    // "24Y · 178cm · 74kg" — each part only when we actually have it.
    return [c.age ? c.age + 'Y' : null, c.height ? c.height + 'cm' : null, c.weight ? c.weight + 'kg' : null]
      .filter(Boolean).join(' · ');
  }

  function paintVs(s) {
    var a = s.aka || {}, b = s.ao || {};
    text('vsEvent', s.tournament || EVENT_TITLE);
    text('vsStage', s.stage || '');
    text('vsWeight', s.division || '');
    text('vsMatchNo', s.matchNo || '');
    // The chip is removed from the row when nobody is appointed, rather than
    // standing there empty — same rule the queue board follows.
    text('vsReferee', s.referee || '');
    el('vsRefWrap').hidden = !s.referee;
    text('vsCourt', COURT.replace(/[^0-9]/g, '') || COURT);

    [['Red', a], ['Blue', b]].forEach(function (pair) {
      var k = pair[0], c = pair[1];
      setName('vs' + k + 'Name', c.name || '', 71.3, 26);
      text('vs' + k + 'Club', c.club || '');
      // The country is spelled out — 'BAHRAIN', never 'BH' — so it is fitted
      // like the name is: a long one shrinks and then condenses rather than
      // running off the panel.
@if (($vsFlag ?? 'club') === 'nationality')
      // The VS presents the ATHLETE, so it flies their own nationality;
      // the club's country stands in only when none is on file.
      var natFlag = flagUrl(c.nationality);
      setName('vs' + k + 'Country', (natFlag ? c.nationalityName : c.country) || '', 32.4, 18);
      bg('vs' + k + 'Flag', natFlag || flagUrl(c.flag));
@else
      setName('vs' + k + 'Country', c.country || '', 32.4, 18);
      bg('vs' + k + 'Flag', flagUrl(c.flag));
@endif
      bg('vs' + k + 'Logo', c.logo);

      // Their own face, and the drawn stand-in when there is none, so an
      // introduction is never half a coloured panel with nobody in it. The
      // stand-in sits well back — this panel is two metres of wall and a
      // drawing at full strength would read as a photograph of the athlete
      // standing on the mat. Their name and club carry the half either way.
      var face = c.photo || c.fallback;
      bg('vs' + k + 'Photo', face);
      var pel = el('vs' + k + 'Photo');
      if (pel) pel.style.opacity = (face && !c.photo) ? '.45' : '1';
      // How THIS person's photo is placed (PhotoVsFrame) — zoomed and moved
      // over the untouched original, never cropped. None: the panel's own
      // cover / center 12%, reset every time because the panel is reused.
      var fr = c.photo ? c.vs_frame : null;
      var fz = fr ? parseFloat(fr.z) : NaN, fx = fr ? parseFloat(fr.x) : NaN, fy = fr ? parseFloat(fr.y) : NaN;
      if (pel) {
        var framed = isFinite(fz) && isFinite(fx) && isFinite(fy);
        pel.style.backgroundSize = framed ? (Math.min(3, Math.max(1, fz)) * 100) + '% auto' : 'cover';
        pel.style.backgroundPosition = framed ? Math.min(100, Math.max(0, fx)) + '% ' + Math.min(100, Math.max(0, fy)) + '%' : 'center 12%';
      }
      var host = el('vs' + k + 'Chips');
      host.textContent = '';
      chip(host, c.record, false);
      chip(host, c.belt, true);
      chip(host, statLine(c), false);
      if (statLine(c) && host.lastChild) host.lastChild.classList.add('vs-stat');
    });
  }
