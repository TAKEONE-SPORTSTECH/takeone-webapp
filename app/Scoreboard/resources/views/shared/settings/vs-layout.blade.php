{{--
    Settings → VS screen: where the mat's VS screen puts each competitor's
    details over their photo, so the badge, name and club never sit on the
    face (owner, 2026-10-25). Set once for the platform
    (App\Scoreboard\Support\VsLayout), read by every Karate mat screen.

    The preview is the shared partials/vs-preview — the mat's own panel at
    its own pixels — so moving a slider here moves exactly what the hall will
    see. Super-admin only (the host passes $vsLayoutUrl; the endpoint checks
    again). Saves as it is told, so the console's Apply hides.
--}}
@php $vl = \App\Scoreboard\Support\VsLayout::get(); @endphp
<style>
  #vlWrap{display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;align-items:start;}
  #vlWrap .vlrow{display:flex;flex-direction:column;gap:8px;background:var(--inset);border:1px solid var(--line);border-radius:12px;padding:14px 16px;}
  #vlWrap .vlrow span{font-size:15px;letter-spacing:.14em;text-transform:uppercase;color:var(--muted);display:flex;justify-content:space-between;}
  #vlWrap .vlrow b{font-family:'Anton',sans-serif;font-weight:400;font-size:20px;color:var(--gold);letter-spacing:.04em;}
  #vlWrap input[type=range]{width:100%;accent-color:#ffe135;}
  #vlBar{display:flex;flex-wrap:wrap;gap:10px;}
  #vlBar button, #vlBar label{font-size:16px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;border-radius:10px;padding:11px 16px;cursor:pointer;}
</style>

<div class="spanel" data-panel="vs" hidden style="display:flex;flex-direction:column;gap:14px;">
  <div style="font-size:17px;color:var(--second);line-height:1.4;">{{ __('scoreboard::karate_messages.vs_hint') }}</div>

  {{-- The VS screen itself, with this mat's competitors (or stand-ins). --}}
  @include('scoreboard::karate.scoreboard.partials.vs-live', ['frameId' => 'vlLive', 'w' => 964])

  <div id="vlWrap">
    <label class="vlrow">
      <span>{{ __('scoreboard::karate_messages.vs_bottom') }} <b id="vlBottomVal"></b></span>
      <input id="vlBottom" type="range" min="0" max="700" step="1" value="{{ $vl['info_bottom'] }}">
    </label>
    <label class="vlrow">
      <span>{{ __('scoreboard::karate_messages.vs_size') }} <b id="vlScaleVal"></b></span>
      <input id="vlScale" type="range" min="0.5" max="1.3" step="0.01" value="{{ $vl['info_scale'] }}">
    </label>
    <button type="button" id="vlStats" class="vlrow" role="switch" aria-checked="{{ $vl['show_stats'] ? 'true' : 'false' }}"
            style="flex-direction:row;align-items:center;gap:14px;cursor:pointer;text-align:left;grid-column:1 / -1;">
      <span class="rbox" id="vlStatsBox" style="width:30px;height:30px;flex:0 0 auto;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:20px;font-weight:700;color:#000;"></span>
      <span style="display:flex;flex-direction:column;gap:2px;text-transform:none;letter-spacing:0;">
        <span style="font-size:20px;font-weight:600;color:var(--text);letter-spacing:.04em;">{{ __('scoreboard::karate_messages.vs_stats') }}</span>
        <span style="font-size:15px;color:var(--faint);letter-spacing:.02em;">{{ __('scoreboard::karate_messages.vs_stats_hint') }}</span>
      </span>
    </button>
    <div style="display:flex;flex-direction:column;gap:10px;">
      <div id="vlBar">
        <label style="background:var(--fill);color:var(--ao-ink);border:1px solid rgba(138,180,255,.4);">
          {{ __('scoreboard::karate_messages.pg_try') }}
          <input id="vlFile" type="file" accept="image/*" style="display:none;">
        </label>
        <button type="button" id="vlReset" style="background:transparent;color:var(--muted);border:1px solid var(--line-2);">{{ __('scoreboard::karate_messages.pg_reset') }}</button>
      </div>
      <button type="button" id="vlSave" style="font-size:18px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;border-radius:12px;padding:14px 18px;cursor:pointer;background:var(--green);color:#000;border:none;">{{ __('scoreboard::karate_messages.vs_save') }}</button>
      <div id="vlSay" style="font-size:16px;color:var(--muted);min-height:22px;"></div>
    </div>
  </div>
</div>

<script>
(function () {
  var URL_SAVE = @json($vsLayoutUrl);
  var DEF = @json(\App\Scoreboard\Support\VsLayout::DEFAULTS);
  var T = { saved: @json(__('scoreboard::karate_messages.vs_saved')), failed: @json(__('scoreboard::karate_messages.pg_failed')),
            unsaved: @json(__('scoreboard::karate_messages.pg_unsaved')) };
  var bottom = document.getElementById('vlBottom'), scale = document.getElementById('vlScale');
  var live = window.VsLive(document.getElementById('vlLive'));
  if (!bottom || !scale) return;
  var photoUrl = null;
  // Load the VS screen the first time this tab is shown, not with the console.
  var panel = document.querySelector('.spanel[data-panel="vs"]');
  if (panel) new MutationObserver(function () { if (!panel.hidden) live.load(); }).observe(panel, { attributes: true, attributeFilter: ['hidden'] });

  function say(msg, tone) {
    var s = document.getElementById('vlSay');
    s.textContent = msg || '';
    s.style.color = tone === 'ok' ? 'var(--green)' : (tone === 'bad' ? 'var(--aka-ink)' : 'var(--muted)');
  }
  // Every VS preview on this console follows — the cropper's too.
  function setVars(el, b, k) { el.style.setProperty('--vs-info-bottom', b + 'px'); el.style.setProperty('--vs-info-scale', k); }
  var statsBtn = document.getElementById('vlStats'), stats = statsBtn && statsBtn.getAttribute('aria-checked') === 'true';
  function paint() {
    var b = parseFloat(bottom.value), k = parseFloat(scale.value);
    live.send({ layout: { b: b, k: k, s: stats ? 1 : 0 } });
    if (statsBtn) {
      statsBtn.setAttribute('aria-checked', stats ? 'true' : 'false');
      var bx = document.getElementById('vlStatsBox');
      bx.textContent = stats ? '✓' : '';
      bx.style.background = stats ? 'var(--gold)' : 'transparent';
      bx.style.border = stats ? 'none' : '2px solid var(--line-2)';
    }
    document.getElementById('vlBottomVal').textContent = Math.round(b / 1080 * 100) + '%';
    document.getElementById('vlScaleVal').textContent = Math.round(k * 100) + '%';
  }
  bottom.oninput = scale.oninput = function () { paint(); say(T.unsaved); };
  if (statsBtn) statsBtn.onclick = function () { stats = !stats; paint(); say(T.unsaved); };

  document.getElementById('vlFile').onchange = function () {
    var f = this.files && this.files[0];
    if (!f || !/^image\//.test(f.type)) return;
    if (photoUrl) { try { URL.revokeObjectURL(photoUrl); } catch (err) {} }
    photoUrl = URL.createObjectURL(f);
    live.send({ aka: { local: photoUrl, vs_frame: null } });
  };

  document.getElementById('vlReset').onclick = function () {
    bottom.value = DEF.info_bottom; scale.value = DEF.info_scale; stats = !!DEF.show_stats;
    paint(); say(T.unsaved);
  };

  var saving = false;
  document.getElementById('vlSave').onclick = function () {
    if (saving || !URL_SAVE) return;
    saving = true;
    var btn = this;
    btn.disabled = true;
    fetch(URL_SAVE, {
      method: 'POST',
      headers: { 'Accept': 'application/json', 'Content-Type': 'application/json',
                 'X-CSRF-TOKEN': (document.querySelector('meta[name=csrf-token]') || {}).content || '' },
      credentials: 'same-origin',
      body: JSON.stringify({ layout: { info_bottom: parseFloat(bottom.value), info_scale: parseFloat(scale.value), show_stats: stats } }),
    })
      .then(function (r) { return r.json().catch(function () { return {}; }).then(function (d) { return { ok: r.ok, d: d }; }); })
      .then(function (x) {
        saving = false; btn.disabled = false;
        if (!x.ok || !x.d.success) { say((x.d && x.d.message) || T.failed, 'bad'); return; }
        var l = x.d.layout;
        bottom.value = l.info_bottom; scale.value = l.info_scale; stats = !!+l.show_stats;
        document.querySelectorAll('.vsprev').forEach(function (el) { setVars(el, l.info_bottom, l.info_scale); });
        paint();
        say(x.d.message || T.saved, 'ok');
      })
      .catch(function () { saving = false; btn.disabled = false; say(T.failed, 'bad'); });
  };

  paint();
})();
</script>
