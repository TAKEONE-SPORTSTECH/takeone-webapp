{{--
    The scoring console's CORNERS tab body — one copy for every combat sport.

      · "Swap corner colours on this mat" — which side of THIS mat the red
        corner physically stands on (App\Scoreboard\Corners\MatSides). Acts at
        once and redraws every screen and console on the mat.
      · "Corner names & colours" — one name and one colour per corner for the
        whole EVENT (App\Scoreboard\Corners\CornerLook). Swatches or a typed
        hex; no OS colour popup (Design Rule #4).

    Standalone: its own markup and fallbacks for the console's CSS variables.
    Its script is shared/corners-script, included once after the console's own.

    Inputs:
      $look           CornerLook::for() — keyed by the sport's corner ids
      $cornersUrl     POST {mat, red_right}  (null = no side switch)
      $cornerLookUrl  POST {<key>: {label, colour}, …}  (null = no names/colours)
      $redRight       this mat's side now;  $sportRedRight  the sport's own
--}}
@php $cornerKeys = array_keys($look ?? []); @endphp
<div style="display:flex;flex-direction:column;gap:12px;">
  @if (! empty($cornersUrl))
    <button id="cornerSwap" type="button" style="display:flex;align-items:center;gap:14px;background:var(--inset,#0e1017);border:1px solid var(--line,#1f2230);border-radius:12px;padding:12px 16px;cursor:pointer;text-align:left;">
      <span class="rbox" style="width:30px;height:30px;flex:0 0 auto;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:20px;font-weight:700;color:#000;"></span>
      <span style="flex:1;display:flex;flex-direction:column;gap:2px;">
        <span style="font-size:20px;font-weight:600;color:var(--text,#e8eaf2);letter-spacing:.04em;">{{ __('scoreboard::corners.swap') }}</span>
        <span style="font-size:15px;color:var(--faint,#5c6175);letter-spacing:.02em;">{{ __('scoreboard::corners.swap_hint') }}</span>
      </span>
    </button>
  @endif

  @if (! empty($cornerLookUrl) && count($cornerKeys) === 2)
    <div id="cornerLook" style="display:flex;flex-direction:column;gap:12px;background:var(--inset,#0e1017);border:1px solid var(--line,#1f2230);border-radius:12px;padding:14px 16px;">
      <div style="display:flex;flex-direction:column;gap:2px;">
        <span style="font-size:20px;font-weight:600;color:var(--text,#e8eaf2);letter-spacing:.04em;">{{ __('scoreboard::corners.look_title') }}</span>
        <span style="font-size:15px;color:var(--faint,#5c6175);letter-spacing:.02em;">{{ __('scoreboard::corners.look_hint') }}</span>
      </div>

      @foreach ($cornerKeys as $lookSide)
        <div class="lookRow" data-side="{{ $lookSide }}" style="display:flex;flex-direction:column;gap:10px;padding-top:10px;border-top:1px solid var(--line,#1f2230);">
          <div style="display:flex;align-items:center;gap:12px;">
            {{-- Live preview: the corner as the wall will draw it. --}}
            <span class="lookChip" style="min-width:132px;text-align:center;font-family:'Anton',sans-serif;font-size:24px;letter-spacing:.12em;padding:8px 14px;border-radius:10px;"></span>
            <input class="lookLabel" type="text" maxlength="{{ \App\Scoreboard\Corners\CornerLook::LABEL_MAX }}"
                   value="{{ $look[$lookSide]['customLabel'] ?? '' }}" placeholder="{{ $look[$lookSide]['defaultLabel'] }}"
                   aria-label="{{ __('scoreboard::corners.look_name') }}"
                   style="flex:1;min-width:0;font-size:22px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;background:var(--fill,#1f2230);color:var(--text,#e8eaf2);border:1px solid var(--line,#1f2230);border-radius:10px;padding:10px 14px;">
          </div>
          <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
            <button type="button" class="lookSwatch" data-colour="" title="{{ __('scoreboard::corners.look_default') }}"
                    style="height:38px;padding:0 12px;border-radius:10px;font-size:15px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;background:var(--fill,#1f2230);color:var(--second,#a7abbe);cursor:pointer;">{{ __('scoreboard::corners.look_default') }}</button>
            @foreach (['#b3121f', '#0d55b8', '#e9ecf2', '#1b1c20', '#f2c200', '#1f8a3b', '#e86a10', '#6a2bb5', '#2fa4e7', '#d63384', '#7d828f'] as $swatch)
              <button type="button" class="lookSwatch" data-colour="{{ $swatch }}" title="{{ $swatch }}"
                      style="width:38px;height:38px;border-radius:10px;background:{{ $swatch }};cursor:pointer;"></button>
            @endforeach
            <input class="lookHex" type="text" maxlength="7" value="{{ $look[$lookSide]['customColour'] ?? '' }}" placeholder="#rrggbb"
                   aria-label="{{ __('scoreboard::corners.look_hex') }}"
                   style="width:120px;font-family:'Anton',sans-serif;font-size:20px;letter-spacing:.06em;background:var(--fill,#1f2230);color:var(--text,#e8eaf2);border:1px solid var(--line,#1f2230);border-radius:10px;padding:8px 10px;">
          </div>
        </div>
      @endforeach

      <button id="lookSave" type="button"
              style="align-self:flex-end;font-size:20px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;background:var(--gold,#ffe135);color:#000;border:none;border-radius:12px;padding:12px 26px;cursor:pointer;">{{ __('scoreboard::corners.look_save') }}</button>
    </div>
  @endif
</div>
