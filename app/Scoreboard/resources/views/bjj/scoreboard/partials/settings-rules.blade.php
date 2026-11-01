{{--
    BJJ's Rules tab for the shared Settings pop-up (scoreboard::shared.settings).
    The one tab a rule book decides, so it stays in the sport. The console's
    runtime reads the switches by `data-rule` and the numbers by their ids —
    unchanged from when this markup lived inline in the console.
--}}
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

{{-- The numbers a rule book sets. Each carries its own value on the row
     it belongs to, rather than being a second place to look.

     ⚠️ The advantage and penalty limits both have a floor of 0, not 1,
     and that is the whole point of them: zero means NO limit. For
     advantages that is the default (jiu-jitsu as it is normally run);
     for penalties it is the escape hatch for a ruleset that never
     disqualifies on the ladder. --}}
@foreach ([
    ['penaltyLimit', __('scoreboard::bjj_messages.ctl_rule_penalty_limit'), __('scoreboard::bjj_messages.ctl_rule_penalty_limit_hint'), 0, 10],
    ['penaltyWarnAt', __('scoreboard::bjj_messages.ctl_rule_penalty_warn'), '', 1, 10],
    ['advantageLimit', __('scoreboard::bjj_messages.ctl_rule_advantage_limit'), __('scoreboard::bjj_messages.ctl_rule_advantage_limit_hint'), 0, 20],
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
