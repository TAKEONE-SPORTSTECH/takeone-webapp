{{--
    The livestream lower third — 1920×160, on alpha.

    A separate surface rather than a mode of the wall board, because it is
    COMPOSITED into a video feed rather than hung on a wall: no background at
    all (the page is transparent so the encoder keys it out), a 96px safe area
    at each end, and only the four things a viewer at home needs — the two
    names, the two scores, the clock, and whichever counter is not zero.

    Same tokens, same rules: blue left, white right, red only on a penalty, and
    every coloured plate carries its own word. The plates are `surface/overlay`
    with a 1px divider and a 6px blur, so the video reads through them without
    the text losing contrast.

    The division and the last action ride ABOVE the bar as a 48px strip that
    dismisses itself, rather than crowding the four things that matter.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="utf-8">
    {{-- Dark by design (a scoreboard console / wall board). Declared so a
         browser's auto-dark and Dark Reader both leave it alone — the same
         reasoning as the light pages, opposite value. --}}
    <meta name="color-scheme" content="dark">
    <meta name="darkreader-lock">
    <style>html { color-scheme: dark; }</style>

<meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no">
<title>{{ __('scoreboard::bjj_messages.board_title') }} · {{ $court }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Poppins:wght@600;700&display=swap" rel="stylesheet">
<style>
:root {
  --surface-overlay: rgba(8,17,31,.85);
  --surface-divider: #233A55;
  --text-primary: #F8FAFC;
  --text-secondary: #94A3B8;
  --corner-blue: #1677FF;
  --corner-blue-accent: #38BDF8;
  --corner-white: #F3F6FA;
  --corner-white-muted: #CBD5E1;
  --status-advantage: #D8B25F;
  --status-penalty: #EF4444;
}
/* Transparent all the way down: whatever paints a background here ends up
   keyed into somebody's broadcast. */
html, body { margin:0; padding:0; background:transparent; overflow:hidden; }
#stage {
  position:absolute; left:50%; top:50%; width:1920px; height:160px;
  transform:translate(-50%,-50%) scale(var(--stage-scale, 1)); transform-origin:center;
  font-family:'Poppins', system-ui, sans-serif; color:var(--text-primary);
}
.num { font-family:'Bebas Neue', Impact, sans-serif; }

#bar { position:absolute; inset:0; display:flex; align-items:center; gap:12px; padding:0 96px; }
.plate {
  background:var(--surface-overlay); border:1px solid var(--surface-divider);
  backdrop-filter:blur(6px); -webkit-backdrop-filter:blur(6px);
  border-radius:12px; padding:14px 22px; display:flex; align-items:center; gap:18px;
}
/* Corner identity is a 5px bar, exactly as on the wall — never a full outline. */
.plate.blue  { border-left:5px solid var(--corner-blue); }
.plate.white { border-right:5px solid var(--corner-white-muted); }
.pName { font-size:26px; font-weight:700; text-transform:uppercase; white-space:nowrap;
  max-width:420px; overflow:hidden; text-overflow:ellipsis; }
.pScore { font-size:52px; line-height:1; }
.blue .pScore { color:var(--corner-blue-accent); }
.white .pScore { color:var(--corner-white); }
.mini { display:flex; gap:10px; }
.mini span { font-size:15px; font-weight:700; letter-spacing:.18em; }
.mini .a { color:var(--status-advantage); }
.mini .p { color:var(--status-penalty); }
#clockPlate { flex-direction:column; gap:2px; padding:12px 26px; }
#clock { font-size:40px; line-height:1; }
#state { font-size:13px; font-weight:700; letter-spacing:.28em; text-transform:uppercase; color:var(--text-secondary); }
#spacer { flex:1; }

/* The dismissible strip above the bar. */
#strip {
  position:absolute; left:96px; right:96px; top:-56px; height:48px;
  display:flex; align-items:center; gap:16px; padding:0 22px;
  background:var(--surface-overlay); border:1px solid var(--surface-divider);
  backdrop-filter:blur(6px); -webkit-backdrop-filter:blur(6px); border-radius:12px;
  font-size:16px; font-weight:600; letter-spacing:.16em; text-transform:uppercase;
  color:var(--text-secondary);
  transition:opacity .3s;
}
#strip[hidden] { display:none; }
</style>
</head>
<body>
<div id="stage">
  <div id="strip"></div>
  <div id="bar">
    <div class="plate blue">
      <div class="pName" id="blueName"></div>
      <div class="pScore num" id="blueScore">0</div>
      <div class="mini"><span class="a" id="blueAdv"></span><span class="p" id="bluePen"></span></div>
    </div>
    <div class="plate" id="clockPlate">
      <div class="num" id="clock">0:00</div>
      <div id="state"></div>
    </div>
    <div class="plate white">
      <div class="mini"><span class="p" id="whitePen"></span><span class="a" id="whiteAdv"></span></div>
      <div class="pScore num" id="whiteScore">0</div>
      <div class="pName" id="whiteName"></div>
    </div>
    <div id="spacer"></div>
  </div>
</div>

{{-- ⚠️ Pre-assigned, never inlined into @json(). Blade's bracket matcher
     chokes on an array literal inside @json(...) and the whole view then fails
     to compile with a misleading error — documented in CLAUDE.md. --}}
@php
    $statusWords = [
      'idle' => __('scoreboard::bjj_messages.status_idle'),
      'live' => __('scoreboard::bjj_messages.status_live'),
      'paused' => __('scoreboard::bjj_messages.status_paused'),
      'review' => __('scoreboard::bjj_messages.status_review'),
      'medical' => __('scoreboard::bjj_messages.status_medical'),
      'overtime' => __('scoreboard::bjj_messages.status_overtime'),
      'submission' => __('scoreboard::bjj_messages.status_submission'),
      'dq' => __('scoreboard::bjj_messages.status_dq'),
      'walkover' => __('scoreboard::bjj_messages.status_walkover'),
      'finished' => __('scoreboard::bjj_messages.status_finished'),
    ];
@endphp
<script>
(function () {
  'use strict';

  var S = @json($state);
  var recvAt = Date.now();

  var STATUS = @json($statusWords);
  var ADV = @json(__('scoreboard::bjj_messages.adv_short'));
  var PEN = @json(__('scoreboard::bjj_messages.pen_short'));
  var TBD = @json(__('scoreboard::bjj_messages.court_tbd'));

  function el(id) { return document.getElementById(id); }
  function text(id, v) { var e = el(id); if (e) e.textContent = v == null ? '' : v; }

  function fit() {
    el('stage').style.setProperty('--stage-scale', Math.min(window.innerWidth / 1920, 1));
  }
  window.addEventListener('resize', fit); fit();

  function remaining() {
    return S.running ? Math.max(0, S.remaining - (Date.now() - recvAt) / 1000) : S.remaining;
  }

  function paint() {
    var b = S.blue || {}, w = S.white || {}, sc = S.score || {};
    var r = Math.ceil(remaining());

    text('blueName', (b.name || TBD).toUpperCase());
    text('whiteName', (w.name || TBD).toUpperCase());
    text('blueScore', sc.bluePoints || 0);
    text('whiteScore', sc.whitePoints || 0);

    // A counter at zero is not printed. On a broadcast bar the four things that
    // matter have to stay big, and an "ADV 0" beside every name is noise that
    // costs one of them room.
    text('blueAdv', sc.blueAdvantages ? ADV + ' ' + sc.blueAdvantages : '');
    text('whiteAdv', sc.whiteAdvantages ? ADV + ' ' + sc.whiteAdvantages : '');
    text('bluePen', sc.bluePenalties ? PEN + ' ' + sc.bluePenalties : '');
    text('whitePen', sc.whitePenalties ? PEN + ' ' + sc.whitePenalties : '');

    text('clock', Math.floor(r / 60) + ':' + ('0' + (r % 60)).slice(-2));
    text('state', STATUS[S.status] || '');

    var line = [S.division, S.stage].filter(Boolean).join(' · ');
    el('strip').hidden = !line;
    text('strip', line);
  }

  setInterval(paint, 200);
  paint();

  window.CourtBoard = {
    // Same contract as the wall board, so the shared socket partial drives both
    // without knowing which surface it is talking to.
    update: function (p) { if (p && p.mode) { S = p; recvAt = Date.now(); paint(); } },
    mode: function () { return S.mode || 'upcoming'; },
    pinned: 'bout',
    stale: function () {}
  };
})();
</script>

@if (! empty($screenLink))
@include('scoreboard::bjj.partials.screen-link')
@endif
</body>
</html>
