{{--
    Karate's Rules tab for the shared Settings pop-up (scoreboard::shared.settings).
    The one tab a rule book decides, so it stays in the sport. The console's own
    script reads every row by `data-rule` and `#gapVal` — unchanged from when
    this markup lived inline in each console.

    $kyokushin  true drops Senshu (Kyokushin has none) and keeps the point-gap
                row in the page but hidden, because the engine ignores it.
--}}
@php($kyokushin = $kyokushin ?? false)
@foreach (array_values(array_filter([
    $kyokushin ? null : ['senshuRule', __('scoreboard::karate_messages.ctl_rule_senshu'), __('scoreboard::karate_messages.ctl_rule_senshu_hint')],
    $kyokushin ? null : ['autoSenshu', __('scoreboard::karate_messages.ctl_rule_auto_senshu'), __('scoreboard::karate_messages.ctl_rule_auto_senshu_hint')],
    ['winByPenalties', __('scoreboard::karate_messages.ctl_rule_penalties'), __('scoreboard::karate_messages.ctl_rule_penalties_hint')],
    ['atoshiWarn', __('scoreboard::karate_messages.ctl_rule_atoshi'), __('scoreboard::karate_messages.ctl_rule_atoshi_hint')],
    ['timeUpBuzzer', __('scoreboard::karate_messages.ctl_rule_buzzer'), __('scoreboard::karate_messages.ctl_rule_buzzer_hint')],
])) as [$rule, $ruleLabel, $ruleHint])
  <button class="rrow" data-rule="{{ $rule }}" style="display:flex;align-items:center;gap:14px;background:var(--inset);border:1px solid var(--line);border-radius:12px;padding:12px 16px;cursor:pointer;text-align:left;">
    <span class="rbox" style="width:30px;height:30px;flex:0 0 auto;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:20px;font-weight:700;color:#000;"></span>
    <span style="flex:1;display:flex;flex-direction:column;gap:2px;">
      <span style="font-size:20px;font-weight:600;color:var(--text);letter-spacing:.04em;">{{ $ruleLabel }}</span>
      <span style="font-size:15px;color:var(--faint);letter-spacing:.02em;">{{ $ruleHint }}</span>
    </span>
  </button>
@endforeach

{{-- The gap rule carries its own number, so the switch and the value it
     switches on are one row rather than two things to find. --}}
<div class="rrow"@if ($kyokushin) hidden @endif style="display:flex;align-items:center;gap:14px;background:var(--inset);border:1px solid var(--line);border-radius:12px;padding:12px 16px;">
  <button class="rbox" data-rule="gapOn" style="width:30px;height:30px;flex:0 0 auto;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:20px;font-weight:700;color:#000;cursor:pointer;padding:0;"></button>
  <span style="flex:1;display:flex;flex-direction:column;gap:2px;">
    <span style="font-size:20px;font-weight:600;color:var(--text);letter-spacing:.04em;">{{ __('scoreboard::karate_messages.ctl_rule_gap') }}</span>
    <span style="font-size:15px;color:var(--faint);letter-spacing:.02em;">{{ __('scoreboard::karate_messages.ctl_rule_gap_hint') }}</span>
  </span>
  <input id="gapVal" type="number" min="1" max="20" value="8" style="width:80px;font-family:'Anton',sans-serif;font-size:26px;text-align:center;background:var(--fill);color:var(--gold);border:none;border-radius:10px;padding:8px 6px;">
</div>
