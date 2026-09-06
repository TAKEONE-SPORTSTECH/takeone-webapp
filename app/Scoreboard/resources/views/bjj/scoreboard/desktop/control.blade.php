{{--
    The scoring table at a Brazilian Jiu-Jitsu mat — Match Control.

    ── Where this design came from ────────────────────────────────────────────
    The Karate console's, brought across whole at the user's request
    (2026-09-04) and re-fitted to this sport: the same 1920-wide broadcast
    console that only ever SCALES, the same Anton-over-Barlow-Condensed voice,
    the same three-panel corner/centre/corner layout, the same gold accents and
    the same one-scrim-one-card modal. Karate's own file was not touched — this
    is a COPY that now belongs to this package.

    ── What stayed this package's, and why it had to ──────────────────────────
    The BEHAVIOUR did not move an inch. Every id, class and data-attribute the
    shared runtime binds to (../runtime.blade.php) is still here and still means
    what it meant: this is a re-skin of the same instrument, not a second one.
    That runtime is included by the tablet console as well, so a control that
    quietly renamed itself here would take the tablet down with it.

    And the CONTROLS are this sport's, not Karate's:
      · six scoring buttons, built by the runtime from Ledger::POINT_SOURCES,
        because in jiu-jitsu the number does not name the action — two points is
        a takedown, a sweep or a knee-on-belly, and an official presses the
        ACTION.
      · advantages and penalties are their own ladders beside the points, never
        summed into them.
      · the stalling countdown is here and nowhere else: it is the referee's,
        and the wall learns about it only if a penalty is actually given. It
        is STARTED from the corner it is against and COUNTED in the centre
        beside the match clock (2026-09-05) — one clock, one place to look.
      · the event log opens as a modal from the centre column, because it is a
        correction tool read when something has to be put right, not a running
        commentary worth a band of the console. Nothing in this sport is ever
        erased — an undo APPENDS a reversal with a reason and the row it
        reversed stays, struck through.

    ── It decides NOTHING about the score ─────────────────────────────────────
    Every button posts an INTENTION: "blue passed the guard", never "blue now
    has five". It does not even send what a pass is worth — the server prices it
    — and the score that comes back is replayed from the ledger. A console that
    has fallen behind cannot overwrite the truth.

    ── Graduated friction, exactly as the design spec sets it out ─────────────
      score                    · no confirmation, 400ms lockout, 5s undo toast
      undo past the toast      · 1s hold on the log row
      pause / resume / review  · single tap
      end · reset · correction · modal
      DQ · finalize            · modal AND the match number typed in
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="utf-8">
{{-- A fixed console, not a document: it is authored at one size and scaled to
     the glass, so there is nothing to zoom INTO — magnifying it can only push
     the row of controls along the bottom off the edge. The one place the house
     rule against `user-scalable=no` does not apply (WCAG 1.4.4 is about pages
     people read); panning is left alone, because the console is taller than a
     10" tablet and has to scroll. --}}
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
<style>
  html { -webkit-text-size-adjust:100%; text-size-adjust:100%; touch-action:pan-x pan-y; }
  body { touch-action:pan-x pan-y; }
</style>
<script>
(function () {
  'use strict';
  ['gesturestart', 'gesturechange', 'gestureend'].forEach(function (e) {
    document.addEventListener(e, function (ev) { ev.preventDefault(); }, { passive: false });
  });
  document.addEventListener('wheel', function (ev) { if (ev.ctrlKey) ev.preventDefault(); }, { passive: false });
})();
</script>
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ __('scoreboard::bjj_messages.ctl_title') }} · {{ $event->title }}</title>

{{-- The fonts, the tokens and the vocabulary. Shared with the React island's
     shell so the two paths cannot drift apart. --}}
@include('scoreboard::bjj.scoreboard.partials.console-styles')
</head>
<body>

<div id="root"><div id="stage">

  <div style="position:absolute;left:-120px;top:240px;width:540px;height:540px;border-radius:50%;background:radial-gradient(circle,rgba(19,98,209,.16),transparent 70%);filter:blur(30px);animation:orbA 16s ease-in-out infinite;pointer-events:none;"></div>
  <div style="position:absolute;right:-120px;top:180px;width:560px;height:560px;border-radius:50%;background:radial-gradient(circle,rgba(230,235,242,.09),transparent 70%);filter:blur(30px);animation:orbB 19s ease-in-out infinite;pointer-events:none;"></div>

  {{-- ── Top bar ──────────────────────────────────────────────────────────
       Read-only. Every one of these comes from the draw, and the draw is where
       it is corrected — a name fixed here would be right for one match and
       wrong everywhere else the entry appears. --}}
  <div id="ctlTop">
    <div style="display:flex;flex-direction:column;gap:2px;flex-shrink:0;">
      <div style="font-family:'Anton',sans-serif;font-size:34px;letter-spacing:.04em;text-transform:uppercase;">{{ __('scoreboard::bjj_messages.ctl_title') }}</div>
      <div style="font-size:18px;color:var(--muted);letter-spacing:.18em;text-transform:uppercase;">{{ $event->title }}</div>
    </div>
    <div style="flex:1;min-width:0;display:flex;justify-content:flex-end;align-items:flex-end;gap:40px;">
      <div style="display:flex;flex-direction:column;gap:6px;min-width:0;">
        <span class="cap">{{ __('scoreboard::bjj_messages.sb_division') }}</span>
        <div id="topMeta" class="val" style="max-width:640px;"></div>
      </div>
      <div style="display:flex;flex-direction:column;gap:6px;min-width:0;">
        <span class="cap">{{ __('scoreboard::bjj_messages.court_court') }}</span>
        <div class="val">{{ $court }}</div>
      </div>
      <div style="display:flex;flex-direction:column;gap:6px;min-width:0;">
        <span class="cap">{{ __('scoreboard::bjj_messages.ctl_ruleset') }}</span>
        <div id="topRuleset" class="val" style="max-width:260px;"></div>
      </div>
      <div style="display:flex;flex-direction:column;gap:6px;min-width:0;">
        <span class="cap">{{ __('scoreboard::bjj_messages.ctl_referee') }}</span>
        <div id="topReferee" class="val" style="max-width:260px;"></div>
      </div>
      {{-- What the mat itself is doing, in a word and a light. The word is the
           point: a colour on its own never says anything. --}}
      <div id="liveBadge" style="display:flex;align-items:center;gap:12px;flex-shrink:0;">
        <span id="liveDot"></span>
        <span id="liveText" style="font-size:24px;font-weight:700;letter-spacing:.24em;text-transform:uppercase;color:var(--gold);"></span>
      </div>
    </div>
  </div>

  {{-- ── BLUE | centre | WHITE ────────────────────────────────────────────
       The white panel is a full mirror of the blue one: every horizontal row
       reverses and its labels sit right. The two corners face each other as
       they do on the wall, so the half of the console an official reaches for
       is the half of the mat they are looking at. --}}
  <div id="ctlBody">

    @foreach ([
        ['blue', __('sport-brazilianjiujitsu::messages.corner_blue'), 'var(--blue)', 'var(--blue-plate)', '1', '.05s', false, '100deg'],
        ['white', __('sport-brazilianjiujitsu::messages.corner_white'), 'var(--white)', 'var(--white-ink)', '3', '.3s', true, '260deg'],
    ] as [$side, $label, $colour, $plateInk, $order, $delay, $mirror, $angle])
    @php $rev = $mirror ? 'flex-direction:row-reverse;' : ''; @endphp
    <div class="card" style="border-top:8px solid {{ $colour }};padding:22px 26px;display:flex;flex-direction:column;gap:16px;order:{{ $order }};animation:cardIn .6s {{ $delay }} cubic-bezier(.2,.8,.2,1) both;min-height:0;">

      {{-- The score plate: the corner's colour bleeds under its own label, so
           the number is read against the side it belongs to. POINTS only — the
           other two ladders have their own plates below. --}}
      <div style="{{ $rev }}display:flex;align-items:center;justify-content:space-between;gap:18px;padding:8px 22px;border-radius:12px;background:linear-gradient({{ $angle }},color-mix(in srgb, {{ $colour }} 22%, transparent),#0a0b10 55%);">
        <div style="display:flex;flex-direction:column;gap:2px;min-width:0;{{ $mirror ? 'align-items:flex-end;' : '' }}">
          <div style="font-size:26px;font-weight:700;letter-spacing:.28em;color:{{ $plateInk }};text-transform:uppercase;">{{ $label }}</div>
          <div id="{{ $side }}Name" style="font-size:30px;font-weight:600;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:420px;"></div>
        </div>
        <div id="{{ $side }}Score" style="font-family:'Anton',sans-serif;font-size:96px;line-height:1;font-variant-numeric:tabular-nums;text-shadow:0 4px 30px rgba(0,0,0,.8);">0</div>
      </div>

      {{-- Six actions, and the ACTION is the button. The runtime fills this
           grid from Ledger::POINT_SOURCES, so the console cannot know a price
           the server does not. --}}
      <div class="scoreGrid" id="{{ $side }}ScoreGrid" style="flex:1;min-height:0;"></div>

      <div class="scoreGrid">
        <button class="btn adv" data-cmd="advantage" data-side="{{ $side }}">{{ __('scoreboard::bjj_messages.advantages') }}</button>
        <button class="btn pen" data-penalty="{{ $side }}">{{ __('scoreboard::bjj_messages.penalties') }}</button>
      </div>

      <div class="counters" style="{{ $rev }}">
        <div class="c a" style="{{ $rev }}"><span class="l">{{ __('scoreboard::bjj_messages.adv_short') }}</span><span class="n" id="{{ $side }}Adv">0</span></div>
        <div class="c p" style="{{ $rev }}"><span class="l">{{ __('scoreboard::bjj_messages.pen_short') }}</span><span class="n" id="{{ $side }}Pen">0</span></div>
      </div>

      {{-- The referee's stalling count STARTS in the corner it is against, so
           the half of the console an official reaches for is the half of the
           mat they are looking at. The countdown it opens, and the decision to
           turn it into a penalty, belong to the centre column — one clock, one
           place to look. --}}
      <button class="btn small" data-stall="{{ $side }}" style="width:100%;color:{{ $plateInk }};border-color:color-mix(in srgb, {{ $colour }} 45%, transparent);">{{ __('scoreboard::bjj_messages.ctl_stall') }}</button>

      {{-- Console only. The hall is never told what is about to happen to
           somebody — it learns of a disqualification when there is one. --}}
      <div id="{{ $side }}WarnDq" hidden class="warnDq">{{ __('scoreboard::bjj_messages.penalty_next_is_dq') }}</div>
    </div>
    @endforeach

    {{-- ── Centre column ───────────────────────────────────────────────────
         Built to the KARATE console's measurements, button for button, at the
         user's instruction (2026-09-06): an official who works a karate mat in
         the morning and a jiu-jitsu mat in the afternoon reaches for the same
         places. Every control here posts a command this package already
         supports — there are no ornamental buttons.

         ⚠️ What this column deliberately does NOT carry, because karate has no
         slot for it: Commit result, Referee decision, the stalling countdown,
         Review, Medical, Overtime and the event log. The commands still exist
         on the server and the tablet console still offers them; this table
         does not. --}}
    <div class="card" id="ctlCentre"
         style="padding:30px 30px 26px;display:flex;flex-direction:column;align-items:center;gap:22px;animation:cardIn .6s .18s cubic-bezier(.2,.8,.2,1) both;">

      {{-- What this mat does between matches, and the biggest thing on the
           panel after the clock: an official who has just filed a result is
           looking for the next match, not for a menu. --}}
      <button id="btnBouts" class="bigbtn" style="background:var(--gold);color:#000;">
        <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 6 L12 13 L19 6"></path><path d="M5 12 L12 19 L19 12"></path></svg>
        {{ __('scoreboard::bjj_messages.ctl_queue') }}
        <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 6 L12 13 L19 6"></path><path d="M5 12 L12 19 L19 12"></path></svg>
      </button>

      {{-- The clock plate. The two ids are the runtime's contract — it paints
           #clockVal and #clockState and re-writes #clockVal's className every
           tick, so the type is set INLINE here, where a className assignment
           cannot reach it. --}}
      <div id="ctlClock" style="width:100%;display:flex;flex-direction:column;align-items:center;gap:6px;padding:16px 20px 18px;border-radius:12px;background:var(--ink);">
        <div style="font-size:19px;letter-spacing:.32em;color:var(--muted);text-transform:uppercase;">{{ __('scoreboard::bjj_messages.ctl_bout_timer') }}</div>
        <div id="clockVal" style="font-family:'Anton',sans-serif;font-size:132px;line-height:1.06;font-variant-numeric:tabular-nums;color:var(--text);transition:color .3s;">0:00</div>
        <div id="clockState" style="font-size:32px;font-weight:700;letter-spacing:.28em;text-transform:uppercase;color:var(--gold);"></div>
      </div>

      {{-- One button, both directions — the runtime already relabels it
           Start / Pause / Resume from the state, so there is no separate
           Pause here, exactly as on the karate table. --}}
      <button id="btnStart" class="bigbtn bout" style="color:#000;"></button>

      <div style="width:100%;display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        {{-- Introduction ⇄ scoreboard. Labelled and coloured for what it will
             do NEXT, from the state rather than from a flag this page keeps:
             a second console, a reload, or the wall being switched by somebody
             else all have to leave this button telling the truth. --}}
        <button id="btnBoard" class="cbtn bout" style="color:var(--blue-ink);border:1px solid rgba(138,180,255,.45);"></button>
        {{-- Karate's KO button. Same job here: it opens the end-of-match
             dialog, so it carries the runtime's own #btnEnd id rather than a
             second copy of that flow. --}}
        <button id="btnEnd" class="cbtn bout" style="color:var(--alarm);border:1px solid rgba(255,59,71,.5);">{{ __('scoreboard::bjj_messages.ctl_finish') }}</button>
        <button id="btnSettings" class="cbtn" style="color:var(--gold);border:1px solid var(--gold);">{{ __('scoreboard::bjj_messages.ctl_settings') }}</button>
        <button id="btnHardware" class="cbtn" style="color:var(--text);border:1px solid var(--line-2);">{{ __('scoreboard::bjj_messages.ctl_hardware') }}</button>
        <button id="btnReset" class="cbtn bout" style="background:transparent;color:#ff6b78;border:1px solid rgba(255,107,120,.5);">{{ __('scoreboard::bjj_messages.ctl_reset') }}</button>
        {{-- Deliberately NOT disabled with the rest of the match controls: a
             hung screen is most likely to need this when the mat is empty, and
             a button that greys out exactly when you reach for it is worse
             than no button at all. --}}
        <button id="btnResync" class="cbtn" style="color:var(--blue-ink);border:1px solid rgba(138,180,255,.45);">{{ __('scoreboard::bjj_messages.ctl_resync') }}</button>
      </div>

      <div style="font-size:16px;color:var(--faint);letter-spacing:.06em;text-align:center;">{{ __('scoreboard::bjj_messages.ctl_resync_hint') }}</div>
    </div>

</div></div>

{{-- ── The running order ──────────────────────────────────────────────────
     A modal, because the centre column belongs to the clock: an operator opens
     it, loads a match, and it closes itself. The runtime paints the list and
     owns the Load button; this page owns only the door. --}}
<div id="queueScrim" class="scrim" hidden>
  <div class="modal"
       {{-- ⚠️ The card is TRANSFORM-SCALED by --popup-scale (see the note on
            `.modal` in console-styles): a plain pixel width is drawn and THEN
            shrunk, so 1820px never actually filled the glass. Dividing by the
            scale is what makes the card come out the size it says — this one
            fills the viewport bar a 24px margin at every scale. `max-width` is
            cleared for the same reason: the shared clamp is in unscaled pixels
            and would cut this back down. `flex-shrink:0` is the third half of
            it: the card is a flex ITEM in the scrim, so at any scale below 1 the
            over-wide layout box would simply be shrunk back to the scrim and the
            whole calculation undone. --}}
       style="width:calc((100vw - 72px) / var(--popup-scale));max-width:none;flex-shrink:0;height:900px;gap:16px;">
    <div class="mhead">
      <div class="mtitle" style="font-size:26px;color:var(--green);">{{ __('scoreboard::bjj_messages.ctl_queue') }}</div>
      <button id="queueClose" class="mclose" aria-label="{{ __('scoreboard::bjj_messages.ctl_cancel') }}">✕</button>
    </div>
    {{-- The rich running order: bout number, stage and division, then the two
         corners facing each other across the "vs" — the same portraits and the
         same club flags the wall board prints, so the table and the hall are
         reading one thing. Painted by the runtime (paintBouts). --}}
    <div id="boutsList" style="flex:1;overflow-y:auto;display:flex;flex-direction:column;gap:8px;min-height:160px;padding-right:14px;"></div>
    <div style="font-size:20px;color:var(--faint);">{{ __('scoreboard::bjj_messages.ctl_bouts_hint') }}</div>
  </div>
</div>


{{-- ── Mat settings ──────────────────────────────────────────────────────
     Not a preference panel. Everything here is posted to the mat state, so a
     second console on the same mat and the wall are all running the match the
     official at this table said it was. Karate's third tab (Sounds) is absent:
     this package has no audio slots to fill, and a tab that does nothing is
     worse than no tab. --}}
<div id="settings" hidden class="scrim" style="z-index:41;">
  <div class="modal" style="width:1020px;gap:22px;overflow-y:auto;">
    <div class="mhead">
      <div class="mtitle" style="color:var(--gold);">{{ __('scoreboard::bjj_messages.ctl_settings_title') }}</div>
      <button data-close="settings" class="mclose" aria-label="{{ __('scoreboard::bjj_messages.ctl_cancel') }}">✕</button>
    </div>

    <div style="display:flex;gap:8px;border-bottom:1px solid var(--line);padding-bottom:14px;">
      {{-- The camera tab only exists when this door was given the camera
           addresses, so a console with no camera surface shows no camera tab. --}}
      @foreach (array_merge([['rules', __('scoreboard::bjj_messages.ctl_tab_rules')], ['timer', __('scoreboard::bjj_messages.ctl_tab_timer')], ['sounds', __('scoreboard::bjj_messages.ctl_tab_sounds')]], ($cameraUrl ?? null) ? [['cameras', __('events.mat_cameras_tab')]] : []) as [$tab, $tabLabel])
        <button class="stab" data-tab="{{ $tab }}">{{ $tabLabel }}</button>
      @endforeach
    </div>

    {{-- Rules --}}
    <div class="spanel" data-panel="rules" style="display:flex;flex-direction:column;gap:12px;">
      @foreach ([
          ['referee_decision', __('scoreboard::bjj_messages.ctl_rule_referee_decision'), __('scoreboard::bjj_messages.ctl_rule_referee_decision_hint')],
          ['time_up_buzzer', __('scoreboard::bjj_messages.ctl_rule_buzzer'), __('scoreboard::bjj_messages.ctl_rule_buzzer_hint')],
      ] as [$rule, $ruleLabel, $ruleHint])
        <button class="rrow" data-rule="{{ $rule }}">
          <span class="rbox"></span>
          <span style="flex:1;display:flex;flex-direction:column;gap:2px;">
            <span style="font-size:20px;font-weight:600;color:var(--text);letter-spacing:.04em;">{{ $ruleLabel }}</span>
            <span style="font-size:15px;color:var(--faint);letter-spacing:.02em;">{{ $ruleHint }}</span>
          </span>
        </button>
      @endforeach

      {{-- The three numbers a rule book sets. Each carries its own value on the
           row it belongs to, rather than being a second place to look. --}}
      @foreach ([
          ['penaltyLimit', __('scoreboard::bjj_messages.ctl_rule_penalty_limit'), '', 1, 10],
          ['penaltyWarnAt', __('scoreboard::bjj_messages.ctl_rule_penalty_warn'), '', 1, 10],
          ['stallSeconds', __('scoreboard::bjj_messages.ctl_rule_stall_seconds'), __('scoreboard::bjj_messages.ctl_rule_stall_seconds_hint'), 3, 60],
      ] as [$numId, $numLabel, $numHint, $numMin, $numMax])
        <div class="rrow" style="cursor:default;">
          <span style="flex:1;display:flex;flex-direction:column;gap:2px;">
            <span style="font-size:20px;font-weight:600;color:var(--text);letter-spacing:.04em;">{{ $numLabel }}</span>
            @if ($numHint)
              <span style="font-size:15px;color:var(--faint);letter-spacing:.02em;">{{ $numHint }}</span>
            @endif
          </span>
          <input id="{{ $numId }}" type="number" min="{{ $numMin }}" max="{{ $numMax }}"
                 style="width:90px;flex:0 0 auto;font-family:'Anton',sans-serif;font-size:26px;text-align:center;background:var(--fill);color:var(--gold);border:none;border-radius:10px;padding:8px 6px;">
        </div>
      @endforeach
    </div>

    {{-- Timer --}}
    <div class="spanel" data-panel="timer" hidden style="display:flex;flex-direction:column;gap:20px;">
      <label style="display:flex;flex-direction:column;gap:8px;">
        <span style="font-size:17px;letter-spacing:.18em;text-transform:uppercase;color:var(--muted);">{{ __('scoreboard::bjj_messages.ctl_duration') }}</span>
        <span style="display:flex;gap:12px;align-items:center;">
          <input id="durMin" type="number" min="0" max="59" value="5" class="mm">
          <span style="font-family:'Anton',sans-serif;font-size:28px;color:var(--muted);">:</span>
          <input id="durSec" type="number" min="0" max="59" value="0" class="mm">
        </span>
      </label>
      <label style="display:flex;flex-direction:column;gap:8px;">
        <span style="font-size:17px;letter-spacing:.18em;text-transform:uppercase;color:var(--muted);">{{ __('scoreboard::bjj_messages.ctl_warning_at') }}</span>
        <span style="display:flex;gap:12px;align-items:center;">
          <input id="warnMin" type="number" min="0" max="59" value="1" class="mm" style="color:var(--gold);">
          <span style="font-family:'Anton',sans-serif;font-size:28px;color:var(--muted);">:</span>
          <input id="warnSec" type="number" min="0" max="59" value="0" class="mm" style="color:var(--gold);">
        </span>
        <span style="font-size:14px;color:var(--faint);letter-spacing:.04em;">{{ __('scoreboard::bjj_messages.ctl_warning_hint') }}</span>
      </label>
    </div>

    {{-- Sounds. Uploading REPLACES: a hall does not want two celebration
         tracks, it wants the right one. Every screen on the event is told to
         reload afterwards, because a board caches the file it fetched.

         The slots are the shared closed list (App\Events\Support\ScreenMedia)
         — one store for every combat scoreboard in the product — but the LABELS
         are this sport's, because "one point" is not a thing that happens on a
         jiu-jitsu mat.

         ⚠️ `minmax(0,1fr)`, never a bare `1fr`: a grid track's floor is its
         MIN-CONTENT, and an uploaded filename is one unbreakable token on a
         `nowrap` line — so a real upload widens its column and pushes the panel
         straight out through the side of the card. The zero floor is what lets
         the ellipsis on `.audioName` actually do its job. --}}
    <div class="spanel" data-panel="sounds" hidden style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:4px 28px;min-width:0;">
      @foreach ([
          'vs_music' => __('events.screen_audio_vs_music'),
          'match_start' => __('events.screen_audio_match_start'),
          'match_end' => __('events.screen_audio_match_end'),
          'winner_music' => __('events.screen_audio_winner_music'),
          'point_1' => __('scoreboard::bjj_messages.ctl_sound_point_1'),
          'point_2' => __('scoreboard::bjj_messages.ctl_sound_point_2'),
          'point_3' => __('scoreboard::bjj_messages.ctl_sound_point_3'),
          'foul' => __('events.screen_audio_foul'),
          'time_up' => __('events.screen_audio_time_up'),
          'atoshi' => __('events.screen_audio_atoshi'),
      ] as $slot => $slotLabel)
        <div style="display:flex;align-items:center;gap:14px;padding:10px 0;min-width:0;">
          <div style="flex:1;min-width:0;">
            <div style="font-size:23px;font-weight:600;color:var(--text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $slotLabel }}</div>
            <div class="audioName" data-slot="{{ $slot }}" style="font-size:17px;margin-top:2px;color:var(--faint);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ __('events.screen_audio_none') }}</div>
          </div>
          <label style="font-size:17px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;background:#7ae582;color:#000;border-radius:10px;padding:11px 16px;cursor:pointer;white-space:nowrap;">
            {{ __('events.screen_audio_choose') }}
            <input type="file" accept="audio/*" class="audioPick" data-slot="{{ $slot }}" style="display:none;">
          </label>
          <button class="audioDrop" data-slot="{{ $slot }}" hidden style="font-size:18px;font-weight:700;background:var(--fill);color:#ff6b78;border:1px solid rgba(255,107,120,.45);border-radius:10px;padding:12px 16px;cursor:pointer;">✕</button>
        </div>
      @endforeach
    </div>

    {{-- The cameras on this mat: telemetry, how they film, and what they have
         filmed. Sport-neutral and shared with every other console. --}}
    @if ($cameraUrl ?? null)
      <div class="spanel" data-panel="cameras" hidden>
        @include('partials.mat-cameras', ['cameraUrl' => $cameraUrl, 'cameraCommandBase' => $cameraCommandBase])
      </div>
    @endif

    <button id="btnApply" style="font-size:24px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;background:var(--gold);color:#000;border:none;border-radius:14px;padding:16px 0;cursor:pointer;">{{ __('scoreboard::bjj_messages.ctl_apply') }}</button>
    <div style="font-size:16px;color:var(--faint);letter-spacing:.04em;text-align:center;">{{ __('scoreboard::bjj_messages.ctl_apply_hint') }}</div>
  </div>
</div>

{{-- ── Hardware ──────────────────────────────────────────────────────────
     Honest emptiness. Nothing on this mat is wired yet, and this says so
     rather than being a dead "Connect" button beside a live scoring grid. --}}
<div id="hardware" hidden class="scrim" style="z-index:41;">
  <div class="modal" style="width:640px;">
    <div class="mhead">
      <div class="mtitle" style="color:var(--text);">{{ __('scoreboard::bjj_messages.ctl_hardware') }}</div>
      <button data-close="hardware" class="mclose" aria-label="{{ __('scoreboard::bjj_messages.ctl_cancel') }}">✕</button>
    </div>
    <div style="display:flex;flex-direction:column;align-items:center;gap:10px;padding:40px 0;color:var(--faint);">
      <span style="font-size:40px;">⌁</span>
      <div style="font-size:22px;letter-spacing:.06em;">{{ __('scoreboard::bjj_messages.ctl_hardware_empty') }}</div>
      <div style="font-size:17px;letter-spacing:.04em;text-align:center;">{{ __('scoreboard::bjj_messages.ctl_hardware_hint') }}</div>
    </div>
  </div>
</div>

{{-- ── The one dialog every other decision goes through ─────────────────── --}}
<div id="scrim" hidden>
  <div id="modal" role="dialog" aria-modal="true">
    <div id="modalTitle"></div>
    <div id="modalBody"></div>
    <div id="modalActions">
      <button class="btn small" id="modalCancel">{{ __('scoreboard::bjj_messages.ctl_cancel') }}</button>
      <button class="btn small ok" id="modalOk">{{ __('scoreboard::bjj_messages.ctl_confirm') }}</button>
    </div>
  </div>
</div>

<div id="toast" hidden>
  <span id="toastText"></span>
  <button class="btn small" id="toastUndo">{{ __('scoreboard::bjj_messages.ctl_undo') }}</button>
</div>

<script>
(function () {
  'use strict';

  /* The stage scales; it never reflows. Measured against what the layout
     actually came out to, so a console taller than 1080 still lands whole
     inside the glass instead of having its bottom row clipped away. */
  var root = document.getElementById('root');
  /* A fixed 16:9 canvas, so there is nothing to measure: the scale is the
     glass against 1920x1080 and the console is letterboxed inside it. This
     used to read the laid-out height, which is what let the console drift to
     1.44:1 and render at 74% on a 1080p screen. */
  function fit() {
    var r = root.getBoundingClientRect();
    if (!r.width || !r.height) return;
    var k = Math.min(r.width / 1920, r.height / 1080);
    root.style.setProperty('--stage-scale', k);
    // The pop-ups hang off <body>, outside the stage, so they cannot inherit
    // this — publish it where they can read it, or a 1100px card is drawn at
    // full size beside a console shrunk to 60%.
    document.documentElement.style.setProperty('--stage-scale', k);
  }
  (window.ResizeObserver ? new ResizeObserver(fit).observe(root) : window.addEventListener('resize', fit));
  fit();

  /* Every door on this console: one opener each, one closer each, and one
     Escape that shuts all of them. Everything INSIDE a card belongs to the
     runtime — it paints the bouts list and owns the settings values — so this
     owns only the opening and the closing. */
  function panel(id) { return document.getElementById(id); }
  function open(id, on) { var p = panel(id); if (p) p.hidden = !on; }

  document.getElementById('btnBouts').addEventListener('click', function () { open('queueScrim', true); });
  document.getElementById('queueClose').addEventListener('click', function () { open('queueScrim', false); });
  document.getElementById('btnHardware').addEventListener('click', function () { open('hardware', true); });

  // A card closes on its own scrim, never on a click inside it: adjusting two
  // rules is one visit, not two.
  ['queueScrim', 'settings', 'hardware'].forEach(function (id) {
    var p = panel(id);
    if (p) p.addEventListener('click', function (e) { if (e.target === p) open(id, false); });
  });

  // The ✕ in a card's own head, wherever it is.
  document.querySelectorAll('[data-close]').forEach(function (b) {
    b.addEventListener('click', function () { open(b.getAttribute('data-close'), false); });
  });

  // Loading a bout closes the running order — the runtime sends the command.
  document.getElementById('boutsList').addEventListener('click', function (e) {
    if (e.target.closest('button')) open('queueScrim', false);
  });

  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    ['queueScrim', 'settings', 'hardware'].forEach(function (id) { open(id, false); });
  });
})();
</script>

@include('scoreboard::bjj.scoreboard.runtime')

@if (! empty($screenLink))
@include('scoreboard::bjj.partials.screen-link')
@endif
</body>
</html>
