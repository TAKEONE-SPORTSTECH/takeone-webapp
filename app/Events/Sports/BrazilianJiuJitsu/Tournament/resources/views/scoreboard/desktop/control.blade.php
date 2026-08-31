{{--
    The Brazilian Jiu-Jitsu scoring table — the desktop console.

    A fixed operator UI, not a page anybody reads: it is the laptop at the mat,
    and it is sized to land inside 1080 without scrolling so nothing an official
    needs can be below a fold they will not think to cross mid-match.

    Its mobile twin lives beside it (../mobile/control.blade.php) and is a
    genuinely different instrument — a thumb-reachable pair of scoring columns
    for a tablet held at the mat's edge — per CLAUDE.md → Mobile / Desktop
    Separation. The two post the same commands to the same endpoint.

    ── It decides NOTHING about the score ─────────────────────────────────────
    Every button posts an INTENTION: "blue passed the guard", never "blue now
    has five". It does not even send what a pass is worth — the server prices it
    from Ledger::POINT_SOURCES — and the score that comes back is replayed from
    the ledger. A console that has fallen behind cannot overwrite the truth.

    ── Graduated friction, exactly as the design spec sets it out ─────────────
      score                    · no confirmation, 400ms lockout, 5s undo toast
      undo past the toast      · 1s hold on the log row
      pause / resume / review  · single tap
      end · reset · correction · modal
      DQ · finalize            · modal AND the match number typed in
    Nothing is ever erased: an undo APPENDS a reversal with a reason, and the
    log shows it struck through.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8">
{{-- A fixed console, not a document: the layout is sized to the glass and has
     nothing to zoom into. Same exemption the wall board takes. --}}
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ __('event-bjj_tournament::messages.ctl_title') }} · {{ $court }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">
<style>
/* The same tokens as the wall, by name. The console is the other half of one
   instrument and must never drift into its own palette. */
:root {
  --surface-base:#08111F; --surface-panel:#101E31; --surface-raised:#14263D;
  --surface-divider:#233A55; --surface-overlay:rgba(8,17,31,.85);
  --text-primary:#F8FAFC; --text-secondary:#94A3B8; --text-muted:#64748B; --text-inverse:#0F172A;
  --corner-blue:#1677FF; --corner-blue-accent:#38BDF8; --corner-blue-surface:#0D3A72;
  --corner-white:#F3F6FA; --corner-white-muted:#CBD5E1; --corner-white-surface:rgba(243,246,250,.08);
  --status-advantage:#D8B25F; --status-penalty:#EF4444; --status-confirmed:#22C55E; --status-review:#A78BFA;
  --s1:4px; --s2:8px; --s3:12px; --s4:16px; --s5:24px; --s6:32px; --s7:48px; --s8:64px;
}
*, *::before, *::after { box-sizing:border-box; }
html, body { margin:0; padding:0; height:100%; background:var(--surface-base); color:var(--text-primary);
  font-family:'Poppins', system-ui, sans-serif; overflow:hidden; }
.num { font-family:'Bebas Neue', Impact, sans-serif; }
[hidden] { display:none !important; }

/* ── Top bar: 84px ─────────────────────────────────────────────────────── */
#top { height:84px; display:flex; align-items:center; gap:var(--s5); padding:0 40px;
  border-bottom:1px solid var(--surface-divider); background:var(--surface-panel); }
#top .t { font-size:16px; font-weight:700; }
#top .m { font-size:13px; font-weight:600; letter-spacing:.16em; text-transform:uppercase; color:var(--text-secondary); }
#liveBadge { margin-inline-start:auto; display:flex; align-items:center; gap:var(--s2);
  font-size:13px; font-weight:700; letter-spacing:.28em; text-transform:uppercase; }
#liveDot { width:10px; height:10px; border-radius:50%; background:var(--text-muted); }
#liveDot.on { background:var(--status-confirmed); animation:p7blink 1.6s ease-in-out infinite; }

/* ── Body: 470 / 1fr / 470 ─────────────────────────────────────────────── */
#body { height:calc(100% - 84px - 200px); display:grid; grid-template-columns:470px 1fr 470px;
  gap:var(--s5); padding:var(--s5) 40px; min-height:0; }
.col { min-height:0; display:flex; flex-direction:column; gap:var(--s3); overflow:auto; }

.card { background:var(--surface-panel); border:1px solid var(--surface-divider); border-radius:16px; padding:var(--s4); }
/* Corner identity is a 3px TOP border on the console — the wall uses a side
   edge bar; both are a bar, never a full coloured outline. */
.card.blue  { border-top:3px solid var(--corner-blue); }
.card.white { border-top:3px solid var(--corner-white-muted); }

.cornerHead { display:flex; align-items:baseline; justify-content:space-between; gap:var(--s3); margin-bottom:var(--s3); }
.cornerName { font-size:22px; font-weight:700; text-transform:uppercase; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.cornerChip { font-size:12px; font-weight:700; letter-spacing:.24em; text-transform:uppercase; color:var(--text-secondary); }
.cornerScore { font-size:64px; line-height:1; }
.blue .cornerScore { color:var(--corner-blue-accent); }
.white .cornerScore { color:var(--corner-white); }

/* Scoring buttons: ≥88px tall, big numeral plus the action's own name. */
.scoreGrid { display:grid; grid-template-columns:1fr 1fr; gap:var(--s3); }
button { font-family:inherit; color:inherit; cursor:pointer; }
.btn { background:var(--surface-raised); border:1px solid var(--surface-divider); border-radius:12px;
  min-height:56px; padding:0 var(--s4); font-size:14px; font-weight:600; transition:background .12s, border-color .12s, transform .08s; }
.btn:hover:not(:disabled) { background:color-mix(in srgb, var(--surface-raised) 92%, #fff); border-color:var(--corner-blue-accent); }
.btn:active:not(:disabled) { transform:scale(.97); border-width:2px; border-color:var(--corner-blue-accent);
  background:color-mix(in srgb, var(--surface-raised) 92%, #000); }
.btn:focus-visible { outline:3px solid var(--status-advantage); outline-offset:0; }
.btn:disabled { opacity:.45; cursor:default; }
.btn.score { min-height:92px; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:2px; }
.btn.score .v { font-family:'Bebas Neue', Impact, sans-serif; font-size:34px; line-height:1; }
.btn.score .l { font-size:12px; font-weight:700; letter-spacing:.14em; text-transform:uppercase; color:var(--text-secondary); }
.btn.adv { min-height:88px; border-color:var(--status-advantage); color:var(--status-advantage); }
.btn.pen { min-height:88px; border-color:var(--status-penalty); color:var(--status-penalty); }
.btn.ok  { background:var(--status-confirmed); color:var(--text-inverse); border-color:var(--status-confirmed); font-weight:700; }
.btn.danger { background:transparent; border-color:var(--status-penalty); color:var(--status-penalty); }
.btn.wide { width:100%; }

.counters { display:flex; gap:var(--s3); margin-top:var(--s3); }
.counters .c { flex:1; display:flex; align-items:center; justify-content:space-between;
  border:1px solid var(--surface-divider); border-radius:12px; padding:var(--s2) var(--s3); }
.counters .c .l { font-size:12px; font-weight:700; letter-spacing:.2em; }
.counters .c.a .l { color:var(--status-advantage); }
.counters .c.p .l { color:var(--status-penalty); }
.counters .c .n { font-family:'Bebas Neue', Impact, sans-serif; font-size:28px; line-height:1; }

#warnDq { margin-top:var(--s3); font-size:12px; font-weight:700; letter-spacing:.14em;
  text-transform:uppercase; color:var(--status-penalty); }

/* ── Centre column: clock + transport + queue ──────────────────────────── */
#clockCard { text-align:center; }
#clockVal { font-size:96px; line-height:1; letter-spacing:.03em; }
#clockVal.warn { color:var(--status-penalty); animation:p7glow 1s ease-in-out infinite; }
#clockState { font-size:13px; font-weight:700; letter-spacing:.32em; text-transform:uppercase; color:var(--text-secondary); }
.transport { display:grid; grid-template-columns:1fr 1fr 1fr; gap:var(--s3); margin-top:var(--s4); }
/* Destructive and non-destructive are kept apart — the spec's ≥24px gap. */
#endRow { margin-top:var(--s5); }

#queueList { display:flex; flex-direction:column; gap:var(--s2); }
.qItem { display:flex; align-items:center; gap:var(--s3); padding:var(--s3);
  border:1px solid var(--surface-divider); border-radius:12px; background:var(--surface-raised); }
.qItem .n { font-family:'Bebas Neue', Impact, sans-serif; font-size:22px; min-width:44px; }
.qItem .who { flex:1; font-size:13px; font-weight:600; }
.qItem .who .m { color:var(--text-muted); font-size:11px; letter-spacing:.16em; text-transform:uppercase; }

/* ── Bottom band: 200px — the event log and the stalling card ───────────── */
#bottom { height:200px; display:grid; grid-template-columns:1fr 560px; gap:var(--s5);
  padding:0 40px var(--s5); min-height:0; }
#log { overflow:auto; }
.logRow { display:flex; align-items:center; gap:var(--s3); padding:var(--s2) var(--s3);
  border-bottom:1px solid var(--surface-divider); font-size:13px; }
.logRow:last-child { border-bottom:0; }
.logRow.rev { opacity:.5; text-decoration:line-through; }
.logRow .ts { font-family:'Bebas Neue', Impact, sans-serif; font-size:18px; min-width:56px; color:var(--text-secondary); }
.logChip { padding:3px 10px; border-radius:8px; font-size:11px; font-weight:700; letter-spacing:.14em;
  text-transform:uppercase; border:1px solid currentColor; }
.logChip.point { color:var(--corner-blue-accent); }
.logChip.advantage { color:var(--status-advantage); }
.logChip.penalty { color:var(--status-penalty); }
.logChip.reverse { color:var(--text-muted); }
.logChip.other { color:var(--text-secondary); }
.logRow .d { flex:1; color:var(--text-secondary); }
.logRow .undo { position:relative; overflow:hidden; }
/* Hold-to-confirm: a 1s gold sweep, and releasing cancels it. */
.logRow .undo .sweep { position:absolute; inset:0; background:var(--status-advantage); opacity:.35;
  transform:scaleX(0); transform-origin:left; }
.logRow .undo.holding .sweep { transform:scaleX(1); transition:transform 1s linear; }

#stallCard { display:flex; flex-direction:column; justify-content:center; gap:var(--s3); }
#stallCount { font-family:'Bebas Neue', Impact, sans-serif; font-size:56px; line-height:1; color:var(--status-penalty); }
#stallNote { font-size:12px; color:var(--text-muted); }

/* ── Modals ────────────────────────────────────────────────────────────── */
#scrim { position:fixed; inset:0; background:var(--surface-overlay); display:grid; place-items:center; z-index:50; }
#modal { width:520px; max-width:calc(100vw - 48px); background:var(--surface-panel);
  border:1px solid var(--surface-divider); border-radius:18px; padding:var(--s5); }
#modalTitle { font-size:20px; font-weight:700; }
#modalBody { margin-top:var(--s4); display:flex; flex-direction:column; gap:var(--s3); }
#modalBody label { font-size:12px; font-weight:600; letter-spacing:.16em; text-transform:uppercase; color:var(--text-secondary); }
#modalBody input, #modalBody textarea { width:100%; background:var(--surface-raised); color:inherit;
  border:1px solid var(--surface-divider); border-radius:10px; padding:12px 14px; font:inherit; font-size:14px; }
#modalActions { display:flex; justify-content:flex-end; gap:var(--s3); margin-top:var(--s5); }
.choiceRow { display:flex; flex-wrap:wrap; gap:var(--s2); }
.choice { border:1px solid var(--surface-divider); border-radius:10px; padding:10px 14px;
  background:var(--surface-raised); font-size:13px; font-weight:600; }
.choice.on { border-color:var(--status-advantage); color:var(--status-advantage); }

/* ── Toast: the 5-second undo ──────────────────────────────────────────── */
#toast { position:fixed; inset-inline-start:50%; bottom:24px; transform:translateX(-50%);
  background:var(--surface-raised); border:1px solid var(--surface-divider); border-radius:12px;
  padding:var(--s3) var(--s4); display:flex; align-items:center; gap:var(--s4); z-index:60; font-size:14px; }
#toast[hidden] { display:none; }

@keyframes p7blink { 0%,100% { opacity:1; } 50% { opacity:.2; } }
@keyframes p7glow  { 0%,100% { opacity:1; } 50% { opacity:.5; } }
@media (prefers-reduced-motion: reduce) { * { animation:none !important; transition:none !important; } }
</style>
</head>
<body>

<div id="top">
  <div>
    <div class="t">{{ $event->title }}</div>
    <div class="m" id="topMeta"></div>
  </div>
  <div class="m">{{ __('sport-brazilianjiujitsu::messages.mat') }} · {{ $court }}</div>
  <div class="m" id="topRuleset"></div>
  <div class="m" id="topReferee"></div>
  <div id="liveBadge"><span id="liveDot"></span><span id="liveText"></span></div>
</div>

<div id="body">

  {{-- ── Blue, on the LEFT. Always, on every surface. ─────────────────── --}}
  <div class="col">
    <div class="card blue">
      <div class="cornerHead">
        <div>
          <div class="cornerChip">{{ __('sport-brazilianjiujitsu::messages.corner_blue') }}</div>
          <div class="cornerName" id="blueName"></div>
        </div>
        <div class="cornerScore num" id="blueScore">0</div>
      </div>

      <div class="scoreGrid" id="blueScoreGrid"></div>

      <div class="scoreGrid" style="margin-top:var(--s3)">
        <button class="btn adv" data-cmd="advantage" data-side="blue">{{ __('event-bjj_tournament::messages.advantages') }}</button>
        <button class="btn pen" data-penalty="blue">{{ __('event-bjj_tournament::messages.penalties') }}</button>
      </div>

      <div class="counters">
        <div class="c a"><span class="l">{{ __('event-bjj_tournament::messages.adv_short') }}</span><span class="n" id="blueAdv">0</span></div>
        <div class="c p"><span class="l">{{ __('event-bjj_tournament::messages.pen_short') }}</span><span class="n" id="bluePen">0</span></div>
      </div>
      <div id="blueWarnDq" hidden style="margin-top:var(--s3);font-size:12px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--status-penalty)">
        {{ __('event-bjj_tournament::messages.penalty_next_is_dq') }}
      </div>
    </div>
  </div>

  {{-- ── The clock, the transport, and what is next on this mat ───────── --}}
  <div class="col">
    <div class="card" id="clockCard">
      <div class="num" id="clockVal">0:00</div>
      <div id="clockState"></div>

      <div class="transport">
        <button class="btn ok" id="btnStart">{{ __('event-bjj_tournament::messages.ctl_start') }}</button>
        <button class="btn" id="btnPause">{{ __('event-bjj_tournament::messages.ctl_pause') }}</button>
        <button class="btn" data-cmd="review">{{ __('event-bjj_tournament::messages.ctl_review') }}</button>
      </div>
      <div class="transport">
        <button class="btn" data-cmd="medical">{{ __('event-bjj_tournament::messages.ctl_medical') }}</button>
        <button class="btn" id="btnOvertime">{{ __('event-bjj_tournament::messages.ctl_overtime') }}</button>
        <button class="btn" data-cmd="intro">{{ __('event-bjj_tournament::messages.vs') }}</button>
      </div>

      {{-- Destructive controls sit apart from the ones above them. --}}
      <div class="transport" id="endRow">
        <button class="btn danger" id="btnEnd">{{ __('event-bjj_tournament::messages.ctl_end') }}</button>
        <button class="btn danger" id="btnReset">{{ __('event-bjj_tournament::messages.ctl_reset') }}</button>
        <button class="btn ok" id="btnCommit">{{ __('event-bjj_tournament::messages.ctl_commit') }}</button>
      </div>

      <div style="margin-top:var(--s4)">
        <button class="btn wide" id="btnDecision" hidden>{{ __('event-bjj_tournament::messages.ctl_decision') }}</button>
      </div>
    </div>

    <div class="card">
      <div class="cornerChip" style="margin-bottom:var(--s3)">{{ __('event-bjj_tournament::messages.ctl_queue') }}</div>
      <div id="queueList"></div>
    </div>

    <div class="card">
      <div style="display:flex;align-items:center;justify-content:space-between;gap:var(--s3)">
        <span class="cornerChip" id="screensLine"></span>
        <button class="btn" data-cmd="resync" style="min-height:40px">↻</button>
        <label style="display:flex;align-items:center;gap:var(--s2);font-size:12px">
          <input type="checkbox" id="themeToggle"> {{ __('event-bjj_tournament::messages.ctl_theme') }}
        </label>
      </div>
    </div>
  </div>

  {{-- ── White, on the RIGHT. Never red. ──────────────────────────────── --}}
  <div class="col">
    <div class="card white">
      <div class="cornerHead">
        <div>
          <div class="cornerChip">{{ __('sport-brazilianjiujitsu::messages.corner_white') }}</div>
          <div class="cornerName" id="whiteName"></div>
        </div>
        <div class="cornerScore num" id="whiteScore">0</div>
      </div>

      <div class="scoreGrid" id="whiteScoreGrid"></div>

      <div class="scoreGrid" style="margin-top:var(--s3)">
        <button class="btn adv" data-cmd="advantage" data-side="white">{{ __('event-bjj_tournament::messages.advantages') }}</button>
        <button class="btn pen" data-penalty="white">{{ __('event-bjj_tournament::messages.penalties') }}</button>
      </div>

      <div class="counters">
        <div class="c a"><span class="l">{{ __('event-bjj_tournament::messages.adv_short') }}</span><span class="n" id="whiteAdv">0</span></div>
        <div class="c p"><span class="l">{{ __('event-bjj_tournament::messages.pen_short') }}</span><span class="n" id="whitePen">0</span></div>
      </div>
      <div id="whiteWarnDq" hidden style="margin-top:var(--s3);font-size:12px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--status-penalty)">
        {{ __('event-bjj_tournament::messages.penalty_next_is_dq') }}
      </div>
    </div>
  </div>
</div>

<div id="bottom">
  <div class="card" id="logCard" style="overflow:hidden;display:flex;flex-direction:column">
    <div class="cornerChip" style="margin-bottom:var(--s2)">{{ __('event-bjj_tournament::messages.ctl_log') }}</div>
    <div id="log"></div>
  </div>

  {{-- The stalling flow is REFEREE-ONLY and lives here, on the console. The
       public board learns nothing until a penalty is actually applied. --}}
  <div class="card" id="stallCard">
    <div class="cornerChip">{{ __('event-bjj_tournament::messages.ctl_stall') }}</div>
    <div style="display:flex;align-items:center;gap:var(--s4)">
      <span class="num" id="stallCount">—</span>
      <div style="display:flex;gap:var(--s2);flex:1">
        <button class="btn" data-stall="blue">{{ __('sport-brazilianjiujitsu::messages.corner_blue') }}</button>
        <button class="btn" data-stall="white">{{ __('sport-brazilianjiujitsu::messages.corner_white') }}</button>
      </div>
    </div>
    <div style="display:flex;gap:var(--s2)">
      <button class="btn" id="stallCancel" disabled>{{ __('event-bjj_tournament::messages.ctl_stall_cancel') }}</button>
      <button class="btn pen" id="stallApply" style="min-height:56px" disabled>{{ __('event-bjj_tournament::messages.ctl_stall_apply') }}</button>
    </div>
    <div id="stallNote">{{ __('event-bjj_tournament::messages.penalty_stalling') }}</div>
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
  <button class="btn" id="toastUndo" style="min-height:40px">{{ __('event-bjj_tournament::messages.ctl_undo') }}</button>
</div>

@include('event-bjj_tournament::scoreboard.runtime')

@if (! empty($screenLink))
@include('event-bjj_tournament::partials.screen-link')
@endif
</body>
</html>
