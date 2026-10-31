{{--
    THE scoring console's Settings pop-up — one file for every combat sport.
    Docs: docs/events/console-settings.md

    The best of each console, as one: every tab any console had, each shown
    only where that console's engine can honour it.

      rules      the sport's own rule book — `$rulesView` (a partial the sport
                 owns: karate/…/settings-rules, bjj/…/settings-rules); none, no tab
      corners    which side red stands on, and what each corner is called —
                 `$perScreen` adds the per-screen sides
      buttons    this mat's own scoring buttons ┐ `$customScoring` = 'points'
      penalties  this mat's own foul ladder     ┘ (Combat's words) or 'buttons'
                 (Kyokushin's) — only engines that read Styles\Custom* offer them
      timer      bout length and the warning — `$timer` (null: no tab)
      sounds     the event's sound slots — `$sounds` (labels; null: no tab)
      cameras    this mat's cameras — `$cameraUrl` (null: no tab)
      guide      the cropper's face guide, platform-wide — `$photoGuideUrl` (super-admin)
      vs         the VS screen's layout, platform-wide — `$vsLayoutUrl` (super-admin)

    ⚠️ Every id, class and data-attribute a console's script reads is kept
    exactly as it was (`.stab`/`data-tab`, `.spanel`/`data-panel`, `data-rule`,
    `#durMin`, `#atoMin`/`#warnMin`, `.audioPick`, `#btnRows`, `#btnApply`…), so
    the consoles drive this file with the scripts they already had.

    Parameters
      $sport        'karate' | 'bjj' | 'taekwondo' (the corner panel's default side)
      $L            the corner look
      $z            z-index of the scrim (default 21)
      $chrome       'console' (.modal/.mhead/.mtitle/.mclose) or
                    'sheet' (.sheet/.sheet-head/.sheet-title) — whichever the console draws
      $timer        … plus an optional 'hint': the rule book's own line under the warning
      $inlineTabs   style the tab buttons inline (consoles whose stylesheet has no .stab)
      $ownScript    this file opens/closes itself and switches its own tabs
                    (`#btnSettings` opens it) — for a console with no Settings script
      $apply        show Apply (default: when there are rules or a timer)
--}}
@php
    $sport = $sport ?? 'karate';
    $z = $z ?? 21;
    $chrome = $chrome ?? 'console';
    $rulesView = $rulesView ?? null;
    $perScreen = $perScreen ?? false;
    $customScoring = $customScoring ?? null;
    $timer = $timer ?? null;
    // true = the shared closed list of slots (App\Events\Support\ScreenMedia),
    // each labelled with the platform's words unless `$soundLabels` says the
    // sport's own (BJJ: its points are not "one point").
    $sounds = ($sounds ?? false) ? array_merge([
        'vs_music' => __('events::events.screen_audio_vs_music'),
        'match_start' => __('events::events.screen_audio_match_start'),
        'match_end' => __('events::events.screen_audio_match_end'),
        'winner_music' => __('events::events.screen_audio_winner_music'),
        'point_1' => __('events::events.screen_audio_point_1'),
        'point_2' => __('events::events.screen_audio_point_2'),
        'point_3' => __('events::events.screen_audio_point_3'),
        'foul' => __('events::events.screen_audio_foul'),
        'time_up' => __('events::events.screen_audio_time_up'),
        'atoshi' => __('events::events.screen_audio_atoshi'),
    ], $soundLabels ?? []) : null;
    $cameraUrl = $cameraUrl ?? null;
    $photoGuideUrl = $photoGuideUrl ?? null;
    $vsLayoutUrl = $vsLayoutUrl ?? null;
    $ownScript = $ownScript ?? false;
    $apply = $apply ?? ($rulesView !== null || $timer !== null);

    $tabs = array_values(array_filter([
        $rulesView ? ['rules', __('scoreboard::settings.tab_rules')] : null,
        ['corners', __('scoreboard::settings.tab_corners')],
        $customScoring ? ['buttons', __('scoreboard::settings.'.($customScoring === 'points' ? 'tab_points' : 'tab_buttons'))] : null,
        $customScoring ? ['penalties', __('scoreboard::settings.'.($customScoring === 'points' ? 'tab_fouls' : 'tab_penalties'))] : null,
        $timer ? ['timer', __('scoreboard::settings.tab_timer')] : null,
        $sounds ? ['sounds', __('scoreboard::settings.tab_sounds')] : null,
        $cameraUrl ? ['cameras', __('events::events.mat_cameras_tab')] : null,
        $photoGuideUrl ? ['guide', __('scoreboard::settings.tab_guide')] : null,
        $vsLayoutUrl ? ['vs', __('scoreboard::settings.tab_vs')] : null,
    ]));
    $first = $tabs[0][0] ?? 'corners';
    $closeLabel = __('scoreboard::settings.close');
    $panelHidden = fn (string $tab) => $tab === $first ? '' : ' hidden';
    // The VS panels speak the VS screen's corner keys (red = aka, blue = ao),
    // whatever a sport calls its corners (BJJ: blue/white).
    $stVsLook = isset($L['aka'], $L['ao']) ? $L : array_combine(['aka', 'ao'], array_slice(array_values((array) $L), 0, 2));
@endphp
<div id="settings" hidden class="scrim" style="z-index:{{ $z }};">
  @if ($chrome === 'sheet')
  <div class="sheet" style="width:1020px;gap:22px;max-height:900px;overflow-y:auto;">
    <div class="sheet-head">
      <div class="sheet-title" style="flex:1;">{{ __('scoreboard::settings.title') }}</div>
      <button type="button" data-close="settings" class="btn sheet-close" aria-label="{{ $closeLabel }}">✕</button>
    </div>
  @else
  <div class="modal" style="width:1020px;gap:22px;overflow-y:auto;">
    <div class="mhead">
      <div class="mtitle" style="color:var(--gold);">{{ __('scoreboard::settings.title') }}</div>
      <button data-close="settings" class="mclose" aria-label="{{ $closeLabel }}">✕</button>
    </div>
  @endif

    <div style="display:flex;gap:8px;border-bottom:1px solid var(--line);padding-bottom:14px;">
      {{-- A tab exists only where this console can honour it: no camera
           addresses, no camera tab; an engine with no custom buttons, no
           Points tab — the same way a type with no wall boards shows no
           screen panel. --}}
      @foreach ($tabs as [$tab, $tabLabel])
        @if ($inlineTabs ?? false)
          <button class="stab" data-tab="{{ $tab }}" style="flex:1;font-size:20px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;border-radius:10px;padding:12px 0;cursor:pointer;">{{ $tabLabel }}</button>
        @else
          <button class="stab" data-tab="{{ $tab }}">{{ $tabLabel }}</button>
        @endif
      @endforeach
    </div>

    {{-- Rules — the sport's own. --}}
    @if ($rulesView)
      <div class="spanel" data-panel="rules"{{ $panelHidden('rules') }} style="display:flex;flex-direction:column;gap:12px;">
        @include($rulesView, $rulesData ?? [])
      </div>
    @endif

    {{-- Corners — which side red stands on at this mat, and what each corner
         is called and coloured for the event. Both save as they are made,
         so this tab has no Apply. --}}
    <div class="spanel" data-panel="corners"{{ $panelHidden('corners') }} style="display:flex;flex-direction:column;gap:12px;">
      @include('scoreboard::shared.corners-panel', ['look' => $L, 'sportRedRight' => \App\Sports\Combat\CornerSide::redRight($sport)] + ($perScreen ? ['perScreen' => true] : []))
    </div>

    {{-- Photo guide — where a face goes in the cropper, platform-wide (super-admin only). --}}
    @if ($photoGuideUrl)
      @include('scoreboard::shared.settings.photo-guide', ['look' => $stVsLook, 'photoGuideUrl' => $photoGuideUrl])
    @endif

    {{-- VS screen — where each competitor's details sit over their photo, platform-wide (super-admin only). --}}
    @if ($vsLayoutUrl)
      @include('scoreboard::shared.settings.vs-layout', ['look' => $stVsLook, 'vsLayoutUrl' => $vsLayoutUrl])
    @endif

    {{-- Timer --}}
    @if ($timer)
      <div class="spanel" data-panel="timer"{{ $panelHidden('timer') }} style="display:flex;flex-direction:column;gap:20px;">
        <label style="display:flex;flex-direction:column;gap:8px;">
          <span style="font-size:17px;letter-spacing:.18em;text-transform:uppercase;color:var(--muted);">{{ __('scoreboard::settings.duration') }}</span>
          <span style="display:flex;gap:12px;align-items:center;">
            <input id="durMin" type="number" min="0" max="59" value="{{ $timer['dur'][0] }}" class="mm">
            <span style="font-family:'Anton',sans-serif;font-size:28px;color:var(--muted);">:</span>
            <input id="durSec" type="number" min="0" max="59" value="{{ $timer['dur'][1] }}" class="mm">
          </span>
        </label>
        <label style="display:flex;flex-direction:column;gap:8px;">
          <span style="font-size:17px;letter-spacing:.18em;text-transform:uppercase;color:var(--muted);">{{ __('scoreboard::settings.warning_at') }}</span>
          <span style="display:flex;gap:12px;align-items:center;">
            <input id="{{ $timer['warnId'] }}Min" type="number" min="0" max="59" value="{{ $timer['warn'][0] }}" class="mm" style="color:{{ $timer['warnInk'] }};">
            <span style="font-family:'Anton',sans-serif;font-size:28px;color:var(--muted);">:</span>
            <input id="{{ $timer['warnId'] }}Sec" type="number" min="0" max="59" value="{{ $timer['warn'][1] }}" class="mm" style="color:{{ $timer['warnInk'] }};">
          </span>
          <span style="font-size:14px;color:var(--faint);letter-spacing:.04em;">{{ $timer['hint'] ?? __('scoreboard::settings.warning_hint') }}</span>
        </label>
      </div>
    @endif

    {{-- Sounds. Uploading replaces: a hall does not want two celebration
         tracks, it wants the right one. Every screen is told to reload
         afterwards, because a board caches the file it fetched. The slots are
         the shared closed list (App\Events\Support\ScreenMedia); the LABELS are
         the sport's ("one point" is not a thing on a jiu-jitsu mat). --}}
    {{-- ⚠️ `minmax(0,1fr)`, never a bare `1fr`: a grid track's floor is its
         MIN-CONTENT, and an uploaded filename is one unbreakable token on a
         `nowrap` line — a real upload widened its column and pushed this panel
         out through the side of the card. The zero floor lets the ellipsis on
         `.audioName` do its job. --}}
    @if ($sounds)
      <div class="spanel" data-panel="sounds"{{ $panelHidden('sounds') }} style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:4px 28px;min-width:0;">
        @foreach ($sounds as $slot => $slotLabel)
          <div style="display:flex;align-items:center;gap:14px;padding:10px 0;min-width:0;">
            <div style="flex:1;min-width:0;">
              <div style="font-size:23px;font-weight:600;color:var(--text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $slotLabel }}</div>
              <div class="audioName" data-slot="{{ $slot }}" style="font-size:17px;margin-top:2px;color:var(--faint);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ __('events::events.screen_audio_none') }}</div>
            </div>
            <label style="font-size:17px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;background:var(--green, #7ae582);color:#000;border-radius:10px;padding:11px 16px;cursor:pointer;white-space:nowrap;">
              {{ __('events::events.screen_audio_choose') }}
              <input type="file" accept="audio/*" class="audioPick" data-slot="{{ $slot }}" style="display:none;">
            </label>
            <button class="audioDrop" data-slot="{{ $slot }}" hidden style="font-size:18px;font-weight:700;background:var(--fill);color:var(--aka-ink, #ff6b78);border:1px solid rgba(255,107,120,.45);border-radius:10px;padding:12px 16px;cursor:pointer;">✕</button>
          </div>
        @endforeach
      </div>
    @endif

    {{-- The cameras on this mat: telemetry, how they film, and what they have
         filmed. Sport-neutral (partials/mat-cameras). --}}
    @if ($cameraUrl)
      <div class="spanel" data-panel="cameras"{{ $panelHidden('cameras') }}>
        @include('partials.mat-cameras', ['cameraUrl' => $cameraUrl, 'cameraCommandBase' => $cameraCommandBase ?? null])
      </div>
    @endif

    {{-- This mat's own scoring buttons and foul ladder (Styles\CustomButtons /
         CustomPenalties). Each saves for the whole event by its own button, like
         the camera panel, so Apply is hidden on these tabs. --}}
    @if ($customScoring)
      @php($cs = $customScoring === 'points' ? ['fouls_add', 'fouls_default', 'fouls_save'] : ['pen_add', 'pen_default', 'pen_save'])
      <div class="spanel" data-panel="buttons" hidden style="display:flex;flex-direction:column;gap:12px;">
        <div style="font-size:16px;color:var(--faint);letter-spacing:.02em;">{{ __('scoreboard::settings.btn_hint') }}</div>
        <div id="btnRows" style="display:flex;flex-direction:column;gap:10px;"></div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1.4fr;gap:10px;">
          <button id="btnRowAdd" class="cbtn" style="color:var(--text);border:1px dashed var(--line-2);">+ {{ __('scoreboard::settings.btn_add') }}</button>
          <button id="btnRowDefault" class="cbtn" style="color:var(--muted);border:1px solid var(--line-2);">{{ __('scoreboard::settings.btn_default') }}</button>
          <button id="btnRowSave" class="cbtn" style="background:var(--gold);color:#000;border:none;">{{ __('scoreboard::settings.btn_save') }}</button>
        </div>
      </div>

      <div class="spanel" data-panel="penalties" hidden style="display:flex;flex-direction:column;gap:12px;">
        <div style="font-size:16px;color:var(--faint);letter-spacing:.02em;">{{ __('scoreboard::settings.pen_hint') }}</div>
        <div id="penRows" style="display:flex;flex-direction:column;gap:10px;"></div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1.4fr;gap:10px;">
          <button id="penRowAdd" class="cbtn" style="color:var(--text);border:1px dashed var(--line-2);">+ {{ __('scoreboard::settings.'.$cs[0]) }}</button>
          <button id="penRowDefault" class="cbtn" style="color:var(--muted);border:1px solid var(--line-2);">{{ __('scoreboard::settings.'.$cs[1]) }}</button>
          <button id="penRowSave" class="cbtn" style="background:var(--gold);color:#000;border:none;">{{ __('scoreboard::settings.'.$cs[2]) }}</button>
        </div>
      </div>
    @endif

    @if ($apply)
      <button id="btnApply" style="font-size:24px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;background:var(--gold);color:#000;border:none;border-radius:14px;padding:16px 0;cursor:pointer;">{{ __('scoreboard::settings.apply') }}</button>
      <div style="font-size:16px;color:var(--faint);letter-spacing:.04em;text-align:center;">{{ __('scoreboard::settings.apply_hint') }}</div>
    @endif
  </div>
</div>

@if ($ownScript)
{{-- For a console with no Settings script of its own: open on #btnSettings,
     close on ✕ / the backdrop / Escape, and switch tabs. The tab colours are
     the console's gold. --}}
<style>
  #settings .stab{background:transparent;color:var(--muted);border:1px solid var(--line, rgba(255,255,255,.12));}
  #settings .stab[aria-selected="true"]{background:var(--gold);color:#000;border-color:transparent;}
</style>
<script>
(function () {
  var box = document.getElementById('settings');
  if (!box) return;
  function show(name) {
    box.querySelectorAll('.stab').forEach(function (t) { t.setAttribute('aria-selected', t.getAttribute('data-tab') === name ? 'true' : 'false'); });
    box.querySelectorAll('.spanel').forEach(function (p) { p.hidden = p.getAttribute('data-panel') !== name; });
  }
  box.addEventListener('click', function (e) {
    var t = e.target.closest('.stab');
    if (t) { show(t.getAttribute('data-tab')); return; }
    if (e.target === box || e.target.closest('[data-close="settings"]')) box.hidden = true;
  });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') box.hidden = true; });
  var open = document.getElementById('btnSettings');
  if (open) open.addEventListener('click', function () { box.hidden = false; });
  show(@json($first));
})();
</script>
@endif
