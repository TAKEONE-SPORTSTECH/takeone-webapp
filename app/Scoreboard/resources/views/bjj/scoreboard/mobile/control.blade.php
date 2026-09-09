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
    {{-- Dark by design (a scoreboard console / wall board). Declared so a
         browser's auto-dark and Dark Reader both leave it alone — the same
         reasoning as the light pages, opposite value. --}}
    <meta name="color-scheme" content="dark">
    <meta name="darkreader-lock">
    <style>html { color-scheme: dark; }</style>

{{-- A fixed console rather than a document. Same exemption as the wall board:
     this surface is sized to the glass and has nothing to zoom into, and a
     pinch mid-match hides the row of controls along the bottom. --}}
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ __('scoreboard::bjj_messages.ctl_title') }} · {{ $court }}</title>
{{-- The fonts, the tokens and the thumb-sized vocabulary. Shared with the
     React island's shell so the two paths cannot drift apart. --}}
@include('scoreboard::bjj.scoreboard.partials.console-mobile-styles')
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
      <button class="btn adv" data-cmd="advantage" data-side="blue">{{ __('scoreboard::bjj_messages.adv_short') }}</button>
      <button class="btn pen" data-penalty="blue">{{ __('scoreboard::bjj_messages.pen_short') }}</button>
    </div>
    <div class="counters">
      <div class="c a"><span class="l">{{ __('scoreboard::bjj_messages.adv_short') }}</span><span class="n" id="blueAdv">0</span></div>
      <div class="c p"><span class="l">{{ __('scoreboard::bjj_messages.pen_short') }}</span><span class="n" id="bluePen">0</span></div>
    </div>
    <div class="warnDq" id="blueWarnDq" hidden>{{ __('scoreboard::bjj_messages.penalty_next_is_dq') }}</div>
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
      <button class="btn adv" data-cmd="advantage" data-side="white">{{ __('scoreboard::bjj_messages.adv_short') }}</button>
      <button class="btn pen" data-penalty="white">{{ __('scoreboard::bjj_messages.pen_short') }}</button>
    </div>
    <div class="counters">
      <div class="c a"><span class="l">{{ __('scoreboard::bjj_messages.adv_short') }}</span><span class="n" id="whiteAdv">0</span></div>
      <div class="c p"><span class="l">{{ __('scoreboard::bjj_messages.pen_short') }}</span><span class="n" id="whitePen">0</span></div>
    </div>
    <div class="warnDq" id="whiteWarnDq" hidden>{{ __('scoreboard::bjj_messages.penalty_next_is_dq') }}</div>
  </div>
</div>

<div id="foot">
  <div id="clockRow">
    <div class="num" id="clockVal">0:00</div>
    <div>
      <div id="clockState"></div>
      <button class="btn" id="btnDecision" hidden style="min-height:44px;margin-top:var(--s1)">
        {{ __('scoreboard::bjj_messages.ctl_decision') }}
      </button>
    </div>
  </div>
  <div id="transport">
    <button class="btn ok" id="btnStart">{{ __('scoreboard::bjj_messages.ctl_start') }}</button>
    <button class="btn" data-cmd="review">{{ __('scoreboard::bjj_messages.ctl_review') }}</button>
    <button class="btn danger" id="btnEnd">{{ __('scoreboard::bjj_messages.ctl_end') }}</button>
  </div>
</div>

{{-- Everything read rather than pressed: the queue, the log, the stalling
     count and the settings. Opened deliberately, so it can never be under a
     thumb that meant to score. --}}
<div id="sheetScrim" hidden></div>
<div id="sheet" hidden>
  <div id="sheetHandle"></div>
  <div class="sheetTabs">
    <button class="btn on" data-tab="queue">{{ __('scoreboard::bjj_messages.ctl_queue') }}</button>
    <button class="btn" data-tab="log">{{ __('scoreboard::bjj_messages.ctl_log') }}</button>
    <button class="btn" data-tab="more">{{ __('scoreboard::bjj_messages.ctl_settings') }}</button>
    {{-- Only when this door has camera addresses — see the desktop console. --}}
    @if ($cameraUrl ?? null)
      <button class="btn" data-tab="cameras">{{ __('events.mat_cameras_tab') }}</button>
    @endif
  </div>

  <div id="sheetBody">
    <div data-pane="queue"><div id="queueList"></div></div>

    <div data-pane="log" hidden><div id="log"></div></div>

    <div data-pane="more" hidden style="display:flex;flex-direction:column;gap:var(--s3);padding-bottom:var(--s4)">
      <div class="chip">{{ __('scoreboard::bjj_messages.ctl_stall') }}</div>
      <div style="display:flex;align-items:center;gap:var(--s3)">
        <span id="stallCount">—</span>
        <button class="btn" data-stall="blue" style="flex:1">{{ __('sport-brazilianjiujitsu::messages.corner_blue') }}</button>
        <button class="btn" data-stall="white" style="flex:1">{{ __('sport-brazilianjiujitsu::messages.corner_white') }}</button>
      </div>
      <div style="display:flex;gap:var(--s2)">
        <button class="btn" id="stallCancel" style="flex:1" disabled>{{ __('scoreboard::bjj_messages.ctl_stall_cancel') }}</button>
        <button class="btn pen" id="stallApply" style="flex:1;min-height:56px" disabled>{{ __('scoreboard::bjj_messages.ctl_stall_apply') }}</button>
      </div>

      <div class="chip" style="margin-top:var(--s4)">{{ __('scoreboard::bjj_messages.ctl_settings') }}</div>
      <div style="display:flex;gap:var(--s2)">
        <button class="btn" data-cmd="medical" style="flex:1">{{ __('scoreboard::bjj_messages.ctl_medical') }}</button>
        <button class="btn" id="btnOvertime" style="flex:1">{{ __('scoreboard::bjj_messages.ctl_overtime') }}</button>
        <button class="btn" data-cmd="intro" style="flex:1">{{ __('scoreboard::bjj_messages.vs') }}</button>
      </div>
      <div id="endRow">
        <button class="btn danger" id="btnReset">{{ __('scoreboard::bjj_messages.ctl_reset') }}</button>
        <button class="btn ok" id="btnCommit">{{ __('scoreboard::bjj_messages.ctl_commit') }}</button>
      </div>
      <div style="display:flex;align-items:center;justify-content:space-between;gap:var(--s3);margin-top:var(--s4)">
        <span class="chip" id="screensLine"></span>
        <button class="btn" data-cmd="resync" style="min-height:44px">↻</button>
        <label style="display:flex;align-items:center;gap:var(--s2);font-size:12px">
          <input type="checkbox" id="themeToggle"> {{ __('scoreboard::bjj_messages.ctl_theme') }}
        </label>
      </div>
    </div>
    {{-- The lenses pointed at this mat. The same shared panel the desktop
         console and the other sports show — it brings its own styling, which
         reads against this sheet's palette through the same variables. --}}
    @if ($cameraUrl ?? null)
      <div data-pane="cameras" hidden style="padding-bottom:var(--s4)">
        @include('partials.mat-cameras', ['cameraUrl' => $cameraUrl, 'cameraCommandBase' => $cameraCommandBase])
      </div>
    @endif
  </div>

  <div id="sheetFoot">
    <button class="btn" id="sheetClose" style="width:100%">{{ __('scoreboard::bjj_messages.ctl_cancel') }}</button>
  </div>
</div>

<div id="scrim" hidden>
  <div id="modal" role="dialog" aria-modal="true">
    <div id="modalTitle"></div>
    <div id="modalBody"></div>
    <div id="modalActions">
      <button class="btn" id="modalCancel">{{ __('scoreboard::bjj_messages.ctl_cancel') }}</button>
      <button class="btn ok" id="modalOk">{{ __('scoreboard::bjj_messages.ctl_confirm') }}</button>
    </div>
  </div>
</div>

<div id="toast" hidden>
  <span id="toastText"></span>
  <button class="btn" id="toastUndo" style="min-height:44px">{{ __('scoreboard::bjj_messages.ctl_undo') }}</button>
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

@include('scoreboard::bjj.scoreboard.runtime')

@if (! empty($screenLink))
@include('scoreboard::bjj.partials.screen-link')
@endif
</body>
</html>
