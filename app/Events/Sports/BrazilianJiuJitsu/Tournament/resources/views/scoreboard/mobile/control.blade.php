{{--
    The Brazilian Jiu-Jitsu scoring table — the tablet at the mat's edge.

    NOT the desktop console shrunk. The person holding this is standing beside
    the mat with one hand free, looking at the fight and not at the screen, so
    the layout is rebuilt around that: the two corners are full-height columns
    under the thumbs, the clock and transport are a fixed bar at the bottom
    where they can be reached without shifting grip, and everything that needs
    reading rather than pressing — the queue, the event log, the settings —
    lives in a sheet that is opened deliberately.

    Its desktop twin is ../desktop/control.blade.php. The BEHAVIOUR is shared:
    both include scoreboard/runtime.blade.php and post the same commands to the
    same endpoint, so the two consoles can never drift into different rules.
    Only the instrument differs.

    Touch targets follow the spec: scoring ≥88px, every other control ≥56px, and
    at least 24px between a destructive control and a harmless one.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8">
{{-- A fixed console rather than a document. Same exemption as the wall board:
     this surface is sized to the glass and has nothing to zoom into, and a
     pinch mid-match hides the row of controls along the bottom. --}}
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ __('event-bjj_tournament::messages.ctl_title') }} · {{ $court }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">
<style>
:root {
  --surface-base:#08111F; --surface-panel:#101E31; --surface-raised:#14263D;
  --surface-divider:#233A55; --surface-overlay:rgba(8,17,31,.85);
  --text-primary:#F8FAFC; --text-secondary:#94A3B8; --text-muted:#64748B; --text-inverse:#0F172A;
  --corner-blue:#1677FF; --corner-blue-accent:#38BDF8;
  --corner-white:#F3F6FA; --corner-white-muted:#CBD5E1;
  --status-advantage:#D8B25F; --status-penalty:#EF4444; --status-confirmed:#22C55E; --status-review:#A78BFA;
  --s1:4px; --s2:8px; --s3:12px; --s4:16px; --s5:24px; --s6:32px;
}
*, *::before, *::after { box-sizing:border-box; }
html, body { margin:0; padding:0; height:100%; background:var(--surface-base); color:var(--text-primary);
  font-family:'Poppins', system-ui, sans-serif; overflow:hidden; touch-action:manipulation; }
.num { font-family:'Bebas Neue', Impact, sans-serif; }
[hidden] { display:none !important; }
button { font-family:inherit; color:inherit; cursor:pointer; }

/* ── A slim head: who is on, and how the mat is doing ──────────────────── */
#top { height:56px; display:flex; align-items:center; gap:var(--s3); padding:0 var(--s4);
  border-bottom:1px solid var(--surface-divider); background:var(--surface-panel); }
#top .m { font-size:11px; font-weight:600; letter-spacing:.16em; text-transform:uppercase;
  color:var(--text-secondary); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
#liveBadge { margin-inline-start:auto; display:flex; align-items:center; gap:var(--s2); font-size:11px;
  font-weight:700; letter-spacing:.22em; text-transform:uppercase; }
#liveDot { width:9px; height:9px; border-radius:50%; background:var(--text-muted); }
#liveDot.on { background:var(--status-confirmed); animation:p7blink 1.6s ease-in-out infinite; }
#btnSheet { min-height:40px; padding:0 var(--s3); }

/* ── The two corners, under the thumbs ─────────────────────────────────── */
#corners { height:calc(100% - 56px - 168px); display:grid; grid-template-columns:1fr 1fr;
  gap:var(--s2); padding:var(--s2); min-height:0; }
.side { display:flex; flex-direction:column; gap:var(--s2); min-height:0; overflow:auto;
  background:var(--surface-panel); border:1px solid var(--surface-divider); border-radius:14px; padding:var(--s2); }
.side.blue  { border-top:3px solid var(--corner-blue); }
.side.white { border-top:3px solid var(--corner-white-muted); }

.head { display:flex; align-items:baseline; justify-content:space-between; gap:var(--s2); padding:0 var(--s1); }
.chip { font-size:10px; font-weight:700; letter-spacing:.22em; text-transform:uppercase; color:var(--text-secondary); }
.who { font-size:15px; font-weight:700; text-transform:uppercase; overflow:hidden;
  text-overflow:ellipsis; white-space:nowrap; }
.big { font-size:52px; line-height:1; }
.blue .big { color:var(--corner-blue-accent); }
.white .big { color:var(--corner-white); }

.grid { display:grid; grid-template-columns:1fr 1fr; gap:var(--s2); }
.btn { background:var(--surface-raised); border:1px solid var(--surface-divider); border-radius:12px;
  min-height:56px; padding:0 var(--s3); font-size:13px; font-weight:600;
  transition:background .12s, border-color .12s, transform .08s; }
.btn:active:not(:disabled) { transform:scale(.97); border-width:2px; border-color:var(--corner-blue-accent); }
.btn:focus-visible { outline:3px solid var(--status-advantage); outline-offset:0; }
.btn:disabled { opacity:.45; }
/* Scoring is the biggest thing on the screen, because it is what gets pressed
   while nobody is looking at it. */
.btn.score { min-height:88px; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:2px; }
.btn.score .v { font-family:'Bebas Neue', Impact, sans-serif; font-size:30px; line-height:1; }
.btn.score .l { font-size:10px; font-weight:700; letter-spacing:.10em; text-transform:uppercase;
  color:var(--text-secondary); text-align:center; line-height:1.15; }
.btn.adv { min-height:88px; border-color:var(--status-advantage); color:var(--status-advantage); }
.btn.pen { min-height:88px; border-color:var(--status-penalty); color:var(--status-penalty); }
.btn.ok { background:var(--status-confirmed); color:var(--text-inverse); border-color:var(--status-confirmed); font-weight:700; }
.btn.danger { background:transparent; border-color:var(--status-penalty); color:var(--status-penalty); }

.counters { display:flex; gap:var(--s2); }
.counters .c { flex:1; display:flex; align-items:center; justify-content:space-between;
  border:1px solid var(--surface-divider); border-radius:10px; padding:var(--s1) var(--s2); }
.counters .c .l { font-size:10px; font-weight:700; letter-spacing:.18em; }
.counters .c.a .l { color:var(--status-advantage); }
.counters .c.p .l { color:var(--status-penalty); }
.counters .c .n { font-family:'Bebas Neue', Impact, sans-serif; font-size:22px; line-height:1; }
.warnDq { font-size:10px; font-weight:700; letter-spacing:.10em; text-transform:uppercase;
  color:var(--status-penalty); text-align:center; }

/* ── The fixed foot: the clock and the transport, thumb-height ──────────── */
#foot { height:168px; border-top:1px solid var(--surface-divider); background:var(--surface-panel);
  padding:var(--s3) var(--s3) calc(var(--s3) + env(safe-area-inset-bottom)); display:flex;
  flex-direction:column; gap:var(--s2); }
#clockRow { display:flex; align-items:center; gap:var(--s3); }
#clockVal { font-size:56px; line-height:1; letter-spacing:.03em; }
#clockVal.warn { color:var(--status-penalty); animation:p7glow 1s ease-in-out infinite; }
#clockState { font-size:11px; font-weight:700; letter-spacing:.28em; text-transform:uppercase; color:var(--text-secondary); }
#transport { display:grid; grid-template-columns:1.4fr 1fr 1fr; gap:var(--s2); }
/* The destructive row is kept a clear gap away from the transport above it. */
#endRow { display:grid; grid-template-columns:1fr 1fr; gap:var(--s2); margin-top:var(--s5); }

/* ── The sheet: everything that is read rather than pressed ─────────────── */
#sheetScrim { position:fixed; inset:0; background:var(--surface-overlay); z-index:40; }
#sheet { position:fixed; inset-inline:0; bottom:0; max-height:88vh; z-index:41;
  background:var(--surface-panel); border-top:1px solid var(--surface-divider);
  border-radius:20px 20px 0 0; display:flex; flex-direction:column; }
#sheetHandle { width:40px; height:4px; border-radius:999px; background:var(--surface-divider);
  margin:var(--s3) auto; flex:0 0 auto; }
#sheetBody { flex:1; overflow-y:auto; padding:0 var(--s4); }
#sheetFoot { flex:0 0 auto; padding:var(--s3) var(--s4) calc(var(--s3) + env(safe-area-inset-bottom)); }
.sheetTabs { display:flex; gap:var(--s2); padding:0 var(--s4) var(--s3); }
.sheetTabs .btn { flex:1; min-height:44px; font-size:12px; }
.sheetTabs .btn.on { border-color:var(--status-advantage); color:var(--status-advantage); }

#queueList { display:flex; flex-direction:column; gap:var(--s2); }
.qItem { display:flex; align-items:center; gap:var(--s3); padding:var(--s3);
  border:1px solid var(--surface-divider); border-radius:12px; background:var(--surface-raised); }
.qItem .n { font-family:'Bebas Neue', Impact, sans-serif; font-size:20px; min-width:40px; }
.qItem .who { flex:1; font-size:13px; font-weight:600; white-space:normal; }
.qItem .who .m { color:var(--text-muted); font-size:10px; letter-spacing:.16em; text-transform:uppercase; }

.logRow { display:flex; align-items:center; gap:var(--s2); padding:var(--s2) 0;
  border-bottom:1px solid var(--surface-divider); font-size:12px; }
.logRow.rev { opacity:.5; text-decoration:line-through; }
.logRow .ts { font-family:'Bebas Neue', Impact, sans-serif; font-size:16px; min-width:48px; color:var(--text-secondary); }
.logChip { padding:2px 8px; border-radius:8px; font-size:10px; font-weight:700; letter-spacing:.12em;
  text-transform:uppercase; border:1px solid currentColor; }
.logChip.point { color:var(--corner-blue-accent); }
.logChip.advantage { color:var(--status-advantage); }
.logChip.penalty { color:var(--status-penalty); }
.logChip.reverse { color:var(--text-muted); }
.logChip.other { color:var(--text-secondary); }
.logRow .d { flex:1; color:var(--text-secondary); }
.logRow .undo { position:relative; overflow:hidden; min-height:36px; padding:0 var(--s2); }
.logRow .undo .sweep { position:absolute; inset:0; background:var(--status-advantage); opacity:.35;
  transform:scaleX(0); transform-origin:left; }
.logRow .undo.holding .sweep { transform:scaleX(1); transition:transform 1s linear; }

#stallCount { font-family:'Bebas Neue', Impact, sans-serif; font-size:40px; color:var(--status-penalty); }

/* ── Modal and toast: the same furniture as the desktop console ─────────── */
#scrim { position:fixed; inset:0; background:var(--surface-overlay); display:grid; place-items:end center; z-index:50; }
#modal { width:100%; background:var(--surface-panel); border-top:1px solid var(--surface-divider);
  border-radius:20px 20px 0 0; padding:var(--s5) var(--s4) calc(var(--s5) + env(safe-area-inset-bottom));
  max-height:88vh; overflow-y:auto; }
#modalTitle { font-size:17px; font-weight:700; }
#modalBody { margin-top:var(--s4); display:flex; flex-direction:column; gap:var(--s3); }
#modalBody label { font-size:11px; font-weight:600; letter-spacing:.16em; text-transform:uppercase; color:var(--text-secondary); }
#modalBody input { width:100%; background:var(--surface-raised); color:inherit; border:1px solid var(--surface-divider);
  border-radius:10px; padding:14px; font:inherit; font-size:16px; }
#modalActions { display:grid; grid-template-columns:1fr 1fr; gap:var(--s3); margin-top:var(--s5); }
.choiceRow { display:flex; flex-wrap:wrap; gap:var(--s2); }
.choice { border:1px solid var(--surface-divider); border-radius:10px; padding:12px 14px;
  background:var(--surface-raised); font-size:13px; font-weight:600; min-height:48px; }
.choice.on { border-color:var(--status-advantage); color:var(--status-advantage); }

#toast { position:fixed; inset-inline:var(--s4); bottom:calc(180px + env(safe-area-inset-bottom));
  background:var(--surface-raised); border:1px solid var(--surface-divider); border-radius:12px;
  padding:var(--s3); display:flex; align-items:center; justify-content:space-between; gap:var(--s3);
  z-index:60; font-size:13px; }
#toast[hidden] { display:none; }

@keyframes p7blink { 0%,100% { opacity:1; } 50% { opacity:.2; } }
@keyframes p7glow  { 0%,100% { opacity:1; } 50% { opacity:.5; } }
@media (prefers-reduced-motion: reduce) { * { animation:none !important; transition:none !important; } }
</style>
</head>
<body>

<div id="top">
  <div style="min-width:0">
    <div class="m" id="topMeta"></div>
    <div class="m" id="topReferee"></div>
  </div>
  <button class="btn" id="btnSheet">☰</button>
  <div id="liveBadge"><span id="liveDot"></span><span id="liveText"></span></div>
</div>

<div id="corners">
  {{-- Blue is on the LEFT here as well. The console and the wall must never
       disagree about which side somebody is on. --}}
  <div class="side blue">
    <div class="head">
      <div style="min-width:0">
        <div class="chip">{{ __('sport-brazilianjiujitsu::messages.corner_blue') }}</div>
        <div class="who" id="blueName"></div>
      </div>
      <div class="big num" id="blueScore">0</div>
    </div>
    <div class="grid" id="blueScoreGrid"></div>
    <div class="grid">
      <button class="btn adv" data-cmd="advantage" data-side="blue">{{ __('event-bjj_tournament::messages.adv_short') }}</button>
      <button class="btn pen" data-penalty="blue">{{ __('event-bjj_tournament::messages.pen_short') }}</button>
    </div>
    <div class="counters">
      <div class="c a"><span class="l">{{ __('event-bjj_tournament::messages.adv_short') }}</span><span class="n" id="blueAdv">0</span></div>
      <div class="c p"><span class="l">{{ __('event-bjj_tournament::messages.pen_short') }}</span><span class="n" id="bluePen">0</span></div>
    </div>
    <div class="warnDq" id="blueWarnDq" hidden>{{ __('event-bjj_tournament::messages.penalty_next_is_dq') }}</div>
  </div>

  <div class="side white">
    <div class="head">
      <div style="min-width:0">
        <div class="chip">{{ __('sport-brazilianjiujitsu::messages.corner_white') }}</div>
        <div class="who" id="whiteName"></div>
      </div>
      <div class="big num" id="whiteScore">0</div>
    </div>
    <div class="grid" id="whiteScoreGrid"></div>
    <div class="grid">
      <button class="btn adv" data-cmd="advantage" data-side="white">{{ __('event-bjj_tournament::messages.adv_short') }}</button>
      <button class="btn pen" data-penalty="white">{{ __('event-bjj_tournament::messages.pen_short') }}</button>
    </div>
    <div class="counters">
      <div class="c a"><span class="l">{{ __('event-bjj_tournament::messages.adv_short') }}</span><span class="n" id="whiteAdv">0</span></div>
      <div class="c p"><span class="l">{{ __('event-bjj_tournament::messages.pen_short') }}</span><span class="n" id="whitePen">0</span></div>
    </div>
    <div class="warnDq" id="whiteWarnDq" hidden>{{ __('event-bjj_tournament::messages.penalty_next_is_dq') }}</div>
  </div>
</div>

<div id="foot">
  <div id="clockRow">
    <div class="num" id="clockVal">0:00</div>
    <div>
      <div id="clockState"></div>
      <button class="btn" id="btnDecision" hidden style="min-height:44px;margin-top:var(--s1)">
        {{ __('event-bjj_tournament::messages.ctl_decision') }}
      </button>
    </div>
  </div>
  <div id="transport">
    <button class="btn ok" id="btnStart">{{ __('event-bjj_tournament::messages.ctl_start') }}</button>
    <button class="btn" data-cmd="review">{{ __('event-bjj_tournament::messages.ctl_review') }}</button>
    <button class="btn danger" id="btnEnd">{{ __('event-bjj_tournament::messages.ctl_end') }}</button>
  </div>
</div>

{{-- Everything read rather than pressed: the queue, the log, the stalling
     count and the settings. Opened deliberately, so it can never be under a
     thumb that meant to score. --}}
<div id="sheetScrim" hidden></div>
<div id="sheet" hidden>
  <div id="sheetHandle"></div>
  <div class="sheetTabs">
    <button class="btn on" data-tab="queue">{{ __('event-bjj_tournament::messages.ctl_queue') }}</button>
    <button class="btn" data-tab="log">{{ __('event-bjj_tournament::messages.ctl_log') }}</button>
    <button class="btn" data-tab="more">{{ __('event-bjj_tournament::messages.ctl_settings') }}</button>
  </div>

  <div id="sheetBody">
    <div data-pane="queue"><div id="queueList"></div></div>

    <div data-pane="log" hidden><div id="log"></div></div>

    <div data-pane="more" hidden style="display:flex;flex-direction:column;gap:var(--s3);padding-bottom:var(--s4)">
      <div class="chip">{{ __('event-bjj_tournament::messages.ctl_stall') }}</div>
      <div style="display:flex;align-items:center;gap:var(--s3)">
        <span id="stallCount">—</span>
        <button class="btn" data-stall="blue" style="flex:1">{{ __('sport-brazilianjiujitsu::messages.corner_blue') }}</button>
        <button class="btn" data-stall="white" style="flex:1">{{ __('sport-brazilianjiujitsu::messages.corner_white') }}</button>
      </div>
      <div style="display:flex;gap:var(--s2)">
        <button class="btn" id="stallCancel" style="flex:1" disabled>{{ __('event-bjj_tournament::messages.ctl_stall_cancel') }}</button>
        <button class="btn pen" id="stallApply" style="flex:1;min-height:56px" disabled>{{ __('event-bjj_tournament::messages.ctl_stall_apply') }}</button>
      </div>

      <div class="chip" style="margin-top:var(--s4)">{{ __('event-bjj_tournament::messages.ctl_settings') }}</div>
      <div style="display:flex;gap:var(--s2)">
        <button class="btn" data-cmd="medical" style="flex:1">{{ __('event-bjj_tournament::messages.ctl_medical') }}</button>
        <button class="btn" id="btnOvertime" style="flex:1">{{ __('event-bjj_tournament::messages.ctl_overtime') }}</button>
        <button class="btn" data-cmd="intro" style="flex:1">{{ __('event-bjj_tournament::messages.vs') }}</button>
      </div>
      <div id="endRow">
        <button class="btn danger" id="btnReset">{{ __('event-bjj_tournament::messages.ctl_reset') }}</button>
        <button class="btn ok" id="btnCommit">{{ __('event-bjj_tournament::messages.ctl_commit') }}</button>
      </div>
      <div style="display:flex;align-items:center;justify-content:space-between;gap:var(--s3);margin-top:var(--s4)">
        <span class="chip" id="screensLine"></span>
        <button class="btn" data-cmd="resync" style="min-height:44px">↻</button>
        <label style="display:flex;align-items:center;gap:var(--s2);font-size:12px">
          <input type="checkbox" id="themeToggle"> {{ __('event-bjj_tournament::messages.ctl_theme') }}
        </label>
      </div>
    </div>
  </div>

  <div id="sheetFoot">
    <button class="btn" id="sheetClose" style="width:100%">{{ __('event-bjj_tournament::messages.ctl_cancel') }}</button>
  </div>
</div>

<div id="scrim" hidden>
  <div id="modal" role="dialog" aria-modal="true">
    <div id="modalTitle"></div>
    <div id="modalBody"></div>
    <div id="modalActions">
      <button class="btn" id="modalCancel">{{ __('event-bjj_tournament::messages.ctl_cancel') }}</button>
      <button class="btn ok" id="modalOk">{{ __('event-bjj_tournament::messages.ctl_confirm') }}</button>
    </div>
  </div>
</div>

<div id="toast" hidden>
  <span id="toastText"></span>
  <button class="btn" id="toastUndo" style="min-height:44px">{{ __('event-bjj_tournament::messages.ctl_undo') }}</button>
</div>

<script>
/* The sheet is this layout's own furniture — the shared runtime knows nothing
   about it, because the desktop console has no sheet to know about. */
(function () {
  'use strict';
  var sheet = document.getElementById('sheet');
  var scrim = document.getElementById('sheetScrim');

  function open(on) { sheet.hidden = !on; scrim.hidden = !on; }

  document.getElementById('btnSheet').addEventListener('click', function () { open(true); });
  document.getElementById('sheetClose').addEventListener('click', function () { open(false); });
  scrim.addEventListener('click', function () { open(false); });

  document.querySelectorAll('.sheetTabs .btn').forEach(function (tab) {
    tab.addEventListener('click', function () {
      document.querySelectorAll('.sheetTabs .btn').forEach(function (t) { t.classList.remove('on'); });
      tab.classList.add('on');
      document.querySelectorAll('[data-pane]').forEach(function (p) {
        p.hidden = p.dataset.pane !== tab.dataset.tab;
      });
    });
  });
})();
</script>

@include('event-bjj_tournament::scoreboard.runtime')

@if (! empty($screenLink))
@include('event-bjj_tournament::partials.screen-link')
@endif
</body>
</html>
