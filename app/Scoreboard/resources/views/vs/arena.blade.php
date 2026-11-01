{{--
    THE VS screen — one design for every screen that introduces a bout: the
    Karate mats (every style), the Taekwondo and BJJ mats, the bout page's
    intro, and the boutcam (VsScreen.kt draws the same layout natively).
    Docs: docs/events/vs-screen.md

    Markup only, at the 1920x1080 stage; filled by scoreboard::vs.paint.
    Needs: $rr (red stands right — CornerSide), $L (CornerLook::for()).
--}}
  {{-- ── The introduction, over the top of it ───────────────────────────── --}}
  {{-- The age · height · weight chip obeys the platform's VS layout (VsLayout show_stats). --}}
  <style>#vs[data-stats="0"] .vs-stat{display:none !important;}</style>
  <div id="vs" class="layer" hidden data-stats="{{ \App\Scoreboard\Support\VsLayout::get()['show_stats'] ? 1 : 0 }}" style="{{ \App\Scoreboard\Support\VsLayout::cssVars() }} z-index:12; background:radial-gradient(120% 90% at 50% 40%, #16161f 0%, #0a0a0e 65%, #050507 100%); font-family:'Barlow Condensed',sans-serif; color:#e8e6e0; overflow:hidden;">
    <div style="position:absolute; {{ $rr ? 'inset:0 0 0 auto; width:56%; clip-path:polygon(18% 0, 100% 0, 100% 100%, 0 100%);' : 'inset:0 auto 0 0; width:56%; clip-path:polygon(0 0, 100% 0, 82% 100%, 0 100%);' }} overflow:hidden; background:{{ $L['aka']['vsPanel'] }}; animation:{{ $rr ? 'panelR' : 'panelL' }} 0.9s cubic-bezier(0.22,1,0.36,1) both;">
      <div id="vsRedPhoto" style="position:absolute; inset:0; background-size:cover; background-position:center 12%; animation:slowDrift 18s ease-in-out infinite;"></div>
      <div style="position:absolute; inset:0; pointer-events:none; background:linear-gradient({{ $rr ? '245deg' : '115deg' }}, {{ $L['aka']['vsGlow'] }} 0%, transparent 55%);"></div>
      <div style="position:absolute; inset:0; pointer-events:none; background:linear-gradient(to top, rgba(5,5,7,0.95) 0%, rgba(5,5,7,0.72) 22%, rgba(5,5,7,0.3) 42%, transparent 62%), linear-gradient(to bottom, rgba(5,5,7,0.82) 0%, rgba(5,5,7,0.35) 18%, transparent 32%);"></div>
    </div>
    <div style="position:absolute; {{ $rr ? 'inset:0 auto 0 0; width:56%; clip-path:polygon(0 0, 100% 0, 82% 100%, 0 100%);' : 'inset:0 0 0 auto; width:56%; clip-path:polygon(18% 0, 100% 0, 100% 100%, 0 100%);' }} overflow:hidden; background:{{ $L['ao']['vsPanel'] }}; animation:{{ $rr ? 'panelL' : 'panelR' }} 0.9s cubic-bezier(0.22,1,0.36,1) both;">
      <div id="vsBluePhoto" style="position:absolute; inset:0; background-size:cover; background-position:center 12%; animation:slowDrift 18s ease-in-out infinite reverse;"></div>
      <div style="position:absolute; inset:0; pointer-events:none; background:linear-gradient({{ $rr ? '115deg' : '245deg' }}, {{ $L['ao']['vsGlow'] }} 0%, transparent 55%);"></div>
      <div style="position:absolute; inset:0; pointer-events:none; background:linear-gradient(to top, rgba(5,5,7,0.95) 0%, rgba(5,5,7,0.72) 22%, rgba(5,5,7,0.3) 42%, transparent 62%), linear-gradient(to bottom, rgba(5,5,7,0.82) 0%, rgba(5,5,7,0.35) 18%, transparent 32%);"></div>
    </div>
    <div style="position:absolute; top:-6%; bottom:-6%; left:50%; width:3px; margin-left:-1.5px; transform:rotate(10.15deg); pointer-events:none; background:linear-gradient(to bottom, transparent, rgba(253,196,54,0.9) 20%, rgba(253,196,54,0.9) 80%, transparent);background:linear-gradient(to bottom, transparent, oklch(0.85 0.16 85 / 0.9) 20%, oklch(0.85 0.16 85 / 0.9) 80%, transparent); filter:blur(1px);"></div>

    <div style="position:absolute; {{ $rr ? 'right:43.2px; bottom:var(--vs-info-bottom, 64.8px); scale:var(--vs-info-scale, 1); transform-origin:right bottom; display:flex; flex-direction:column; align-items:flex-end; gap:13px; z-index:6; max-width:44%; min-width:0; text-align:right;' : 'left:43.2px; bottom:var(--vs-info-bottom, 64.8px); scale:var(--vs-info-scale, 1); transform-origin:left bottom; display:flex; flex-direction:column; align-items:flex-start; gap:13px; z-index:6; max-width:44%; min-width:0;' }} animation:riseUp 0.8s 0.7s cubic-bezier(0.22,1,0.36,1) both;">
      <div style="font-weight:800; font-size:21.6px; letter-spacing:0.35em; color:{{ $L['aka']['badgeInk'] }}; background:{{ $L['aka']['badgeBg'] }}; padding:5.4px 15.1px 5.4px 18.9px;">{{ $L['aka']['badge'] }}</div>
      <div style="display:flex; align-items:center; {{ $rr ? 'gap:15.1px; flex-direction:row-reverse;' : 'gap:15.1px;' }}">
        <div id="vsRedFlag" style="width:56.2px; aspect-ratio:4/3; background-size:100% 100%; image-rendering:auto; background-position:center; border:1px solid rgba(255,255,255,0.35); box-shadow:0 4px 18px rgba(0,0,0,0.6);"></div>
        <div id="vsRedCountry" style="max-width:760px; font-weight:700; font-size:32.4px; letter-spacing:0.28em; text-transform:uppercase; color:{{ $L['aka']['countryInk'] }};"></div>
      </div>
      <div id="vsRedName" style="max-width:100%; font-family:'Anton',sans-serif; font-size:71.3px; line-height:0.95; text-transform:uppercase; color:#fff; text-shadow:0 6px 30px rgba(0,0,0,0.8);"></div>
      <div style="display:flex; align-items:center; gap:13px; {{ $rr ? 'margin-top:4.3px; flex-direction:row-reverse;' : 'margin-top:4.3px;' }}">
        <div id="vsRedLogo" style="width:69.1px; height:69.1px; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.2); border-radius:50%; background-size:cover; background-position:center;"></div>
        <div id="vsRedClub" style="font-weight:600; font-size:30.2px; letter-spacing:0.12em; text-transform:uppercase; color:rgba(232,230,224,0.9);"></div>
      </div>
      <div id="vsRedChips" style="display:flex; flex-wrap:wrap; gap:9.7px; {{ $rr ? 'margin-top:5.4px; justify-content:flex-end;' : 'margin-top:5.4px;' }}"></div>
    </div>

    <div style="position:absolute; {{ $rr ? 'left:43.2px; bottom:var(--vs-info-bottom, 64.8px); scale:var(--vs-info-scale, 1); transform-origin:left bottom; display:flex; flex-direction:column; align-items:flex-start; gap:13px; z-index:6; max-width:44%; min-width:0;' : 'right:43.2px; bottom:var(--vs-info-bottom, 64.8px); scale:var(--vs-info-scale, 1); transform-origin:right bottom; display:flex; flex-direction:column; align-items:flex-end; gap:13px; z-index:6; max-width:44%; min-width:0; text-align:right;' }} animation:riseUp 0.8s 0.85s cubic-bezier(0.22,1,0.36,1) both;">
      <div style="font-weight:800; font-size:21.6px; letter-spacing:0.35em; color:{{ $L['ao']['badgeInk'] }}; background:{{ $L['ao']['badgeBg'] }}; padding:5.4px 15.1px 5.4px 18.9px;">{{ $L['ao']['badge'] }}</div>
      <div style="display:flex; align-items:center; {{ $rr ? 'gap:15.1px;' : 'gap:15.1px; flex-direction:row-reverse;' }}">
        <div id="vsBlueFlag" style="width:56.2px; aspect-ratio:4/3; background-size:100% 100%; image-rendering:auto; background-position:center; border:1px solid rgba(255,255,255,0.35); box-shadow:0 4px 18px rgba(0,0,0,0.6);"></div>
        <div id="vsBlueCountry" style="max-width:760px; font-weight:700; font-size:32.4px; letter-spacing:0.28em; text-transform:uppercase; color:{{ $L['ao']['countryInk'] }};"></div>
      </div>
      <div id="vsBlueName" style="max-width:100%; font-family:'Anton',sans-serif; font-size:71.3px; line-height:0.95; text-transform:uppercase; color:#fff; text-shadow:0 6px 30px rgba(0,0,0,0.8);"></div>
      <div style="display:flex; align-items:center; gap:13px; {{ $rr ? 'margin-top:4.3px;' : 'margin-top:4.3px; flex-direction:row-reverse;' }}">
        <div id="vsBlueLogo" style="width:69.1px; height:69.1px; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.2); border-radius:50%; background-size:cover; background-position:center;"></div>
        <div id="vsBlueClub" style="font-weight:600; font-size:30.2px; letter-spacing:0.12em; text-transform:uppercase; color:rgba(232,230,224,0.9);"></div>
      </div>
      <div id="vsBlueChips" style="display:flex; flex-wrap:wrap; gap:9.7px; {{ $rr ? 'margin-top:5.4px;' : 'margin-top:5.4px; justify-content:flex-end;' }}"></div>
    </div>

    <div style="position:absolute; top:34.6px; left:50%; transform:translateX(-50%); display:flex; flex-direction:column; align-items:center; gap:10.8px; z-index:8; width:92%; pointer-events:none; animation:dropIn 0.8s 0.5s cubic-bezier(0.22,1,0.36,1) both;">
      <div id="vsEvent" style="font-weight:700; font-size:30.2px; letter-spacing:0.42em; text-transform:uppercase; color:rgba(232,230,224,0.92); text-align:center; text-shadow:0 2px 14px rgba(0,0,0,0.9);"></div>
      <div style="display:flex; align-items:center; gap:17.3px;">
        <div style="height:2px; width:64.8px; background:linear-gradient(to left, #fdc436, transparent);background:linear-gradient(to left, oklch(0.85 0.16 85), transparent);"></div>
        <div id="vsStage" style="font-family:'Anton',sans-serif; font-size:38.9px; letter-spacing:0.3em; padding-left:0.3em; color:#fdc436; color:oklch(0.85 0.16 85); text-transform:uppercase;"></div>
        <div style="height:2px; width:64.8px; background:linear-gradient(to right, #fdc436, transparent);background:linear-gradient(to right, oklch(0.85 0.16 85), transparent);"></div>
      </div>
      <div id="vsWeight" style="font-weight:600; font-size:28.1px; letter-spacing:0.3em; text-transform:uppercase; color:rgba(232,230,224,0.75);"></div>
    </div>

    <div style="position:absolute; top:50%; left:50%; transform:translate(-50%,-52%); z-index:7; pointer-events:none; display:flex; align-items:center; justify-content:center; animation:vsSlam 0.7s 1.1s cubic-bezier(0.22,1,0.36,1) both;">
      <div style="position:relative; font-family:'Anton',sans-serif; font-size:183.6px; font-style:italic; color:#fffdf5; line-height:1; animation:vsPulse 2.4s ease-in-out infinite; -webkit-text-stroke:2px rgba(253,196,54,0.6); -webkit-text-stroke:2px oklch(0.85 0.16 85 / 0.6); overflow:visible;">VS
        <div style="position:absolute; inset:-10% -20%; overflow:hidden; pointer-events:none;">
          <div style="position:absolute; top:0; bottom:0; width:34%; background:linear-gradient(to right, transparent, rgba(255,255,255,0.16), transparent); animation:shineSweep 5s ease-in-out infinite;"></div>
        </div>
      </div>
    </div>
    <div style="position:absolute; inset:0; background:#fff; opacity:0; pointer-events:none; z-index:9; animation:flashOut 0.9s 1.5s ease-out both;"></div>

    <div style="position:absolute; bottom:32.4px; left:50%; transform:translateX(-50%); display:flex; gap:17.3px; z-index:8; align-items:center; flex-wrap:wrap; justify-content:center; max-width:94%; animation:riseC 0.8s 1.3s cubic-bezier(0.22,1,0.36,1) both;">
      <div style="display:flex; align-items:baseline; gap:8.6px; background:rgba(10,10,14,0.72); border:1px solid rgba(253,196,54,0.45); border:1px solid oklch(0.85 0.16 85 / 0.45); padding:10.8px 23.8px; backdrop-filter:blur(6px);">
        <span style="font-weight:600; font-size:23.8px; letter-spacing:0.3em; color:rgba(232,230,224,0.65); text-transform:uppercase;">{{ __('scoreboard::karate_messages.court_match') }}</span>
        <span id="vsMatchNo" style="font-family:'Anton',sans-serif; font-size:34.6px; color:#fff;"></span>
      </div>
      <div style="width:6px; height:6px; transform:rotate(45deg); background:#fdc436; background:oklch(0.85 0.16 85);"></div>
      <div style="display:flex; align-items:baseline; gap:8.6px; background:rgba(10,10,14,0.72); border:1px solid rgba(253,196,54,0.45); border:1px solid oklch(0.85 0.16 85 / 0.45); padding:10.8px 23.8px; backdrop-filter:blur(6px);">
        <span style="font-weight:600; font-size:23.8px; letter-spacing:0.3em; color:rgba(232,230,224,0.65); text-transform:uppercase;">{{ __('scoreboard::karate_messages.court_court') }}</span>
        <span id="vsCourt" style="font-family:'Anton',sans-serif; font-size:34.6px; color:#fff;"></span>
      </div>
      <div style="width:6px; height:6px; transform:rotate(45deg); background:#fdc436; background:oklch(0.85 0.16 85);"></div>
      <div id="vsRefWrap" style="display:flex; align-items:baseline; gap:8.6px; background:rgba(10,10,14,0.72); border:1px solid rgba(253,196,54,0.45); border:1px solid oklch(0.85 0.16 85 / 0.45); padding:10.8px 23.8px; backdrop-filter:blur(6px);">
        <span style="font-weight:600; font-size:23.8px; letter-spacing:0.3em; color:rgba(232,230,224,0.65); text-transform:uppercase;">{{ __('scoreboard::karate_messages.vs_referee') }}</span>
        <span id="vsReferee" style="font-weight:700; font-size:28.1px; letter-spacing:0.08em; color:#fff; text-transform:uppercase;"></span>
      </div>
    </div>
  </div>
