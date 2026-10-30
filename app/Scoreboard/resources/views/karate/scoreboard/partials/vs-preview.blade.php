{{--
    The mat's VS screen, small: one corner's panel exactly as
    karate/*/mat.blade.php draws it, built at the screen's OWN pixels and then
    scaled down — so nothing in it is an approximation:
      · the panel is 56% of the 1920x1080 stage: 1075x1080;
      · the 3:4 photo is `cover` at `center 12%`: 1075x1433, 42.4px up, with
        the same slow drift, dark bands and slanted edge;
      · the competitor's details (badge, country, name, club) are the mat's
        own block, at the platform's VS layout (App\Scoreboard\Support\
        VsLayout: --vs-info-bottom / --vs-info-scale, set on .vsprev).

    `w` is the width to draw it at (default 438). The host fills `.vsph` — a
    photo for the settings, a live mirror of the cropper in the cropper.

    Used by partials/photo-guide-panel, partials/vs-layout-panel and
    partials/crop-guide. ONE drawing of the VS framing on the console; if the
    mat's VS screen changes, change it here too.
--}}
@php
  $vsAka = ($look ?? [])['aka'] ?? [];
  $vsW = (float) ($w ?? 438);
  $vsK = $vsW / 1075;
@endphp
@once
<style>
  .vsprev{position:relative;flex:0 0 auto;border-radius:10px;overflow:hidden;
        background:radial-gradient(120% 90% at 50% 40%,#16161f 0%,#0a0a0e 65%,#050507 100%);border:1px solid var(--line-2,#2a2e40);}
  .vsprev .vsnat{position:absolute;left:0;top:0;width:1075px;height:1080px;transform-origin:0 0;}
  .vsprev .pnl{position:absolute;inset:0;overflow:hidden;clip-path:polygon(0 0,100% 0,82% 100%,0 100%);}
  .vsprev .vsph{position:absolute;left:0;top:-42.4px;width:1075px;height:1433.33px;background:center/cover no-repeat;
        animation:vsDrift 18s ease-in-out infinite;}
  @keyframes vsDrift{0%{transform:translate3d(0,0,0) scale(1.02);}50%{transform:translate3d(0,-1.2%,0) scale(1.05);}100%{transform:translate3d(0,0,0) scale(1.02);}}
  @media (prefers-reduced-motion: reduce){.vsprev .vsph{animation:none;transform:scale(1.05);}}
  .vsprev .shade{position:absolute;inset:0;pointer-events:none;}
  .vsprev .who{position:absolute;left:43.2px;bottom:var(--vs-info-bottom, 64.8px);scale:var(--vs-info-scale, 1);
        transform-origin:left bottom;display:flex;flex-direction:column;align-items:flex-start;gap:13px;z-index:6;
        max-width:845px;font-family:'Barlow Condensed','Inter',sans-serif;color:#e8e6e0;}
  .vscap{font-size:15px;letter-spacing:.16em;text-transform:uppercase;color:var(--muted,#7d8296);margin-bottom:8px;}
</style>
@endonce
<div class="vsprev" @isset($vsId) id="{{ $vsId }}" @endisset
     style="width:{{ $vsW }}px;height:{{ round($vsW * 1080 / 1075, 2) }}px;{{ \App\Scoreboard\Support\VsLayout::cssVars() }}">
  <div class="vsnat" style="transform:scale({{ $vsK }});">
    <div class="pnl" style="background:{{ $vsAka['vsPanel'] ?? '#3f1013' }};">
      <div class="vsph" @isset($phId) id="{{ $phId }}" @endisset></div>
      <div class="shade" style="background:linear-gradient(115deg, {{ $vsAka['vsGlow'] ?? 'rgba(179,18,31,0.55)' }} 0%, transparent 55%);"></div>
      <div class="shade" style="background:linear-gradient(to top, rgba(5,5,7,0.95) 0%, rgba(5,5,7,0.72) 22%, rgba(5,5,7,0.3) 42%, transparent 62%), linear-gradient(to bottom, rgba(5,5,7,0.82) 0%, rgba(5,5,7,0.35) 18%, transparent 32%);"></div>
    </div>
    <div class="who">
      <div style="font-weight:800;font-size:21.6px;letter-spacing:.35em;color:{{ $vsAka['badgeInk'] ?? '#fff' }};background:{{ $vsAka['badgeBg'] ?? '#b3121f' }};padding:5.4px 15.1px 5.4px 18.9px;">{{ $vsAka['badge'] ?? 'AKA' }}</div>
      <div style="display:flex;align-items:center;gap:15.1px;">
        <div style="width:56.2px;aspect-ratio:4/3;background:linear-gradient(90deg,#fff 0 28%,#ce1126 28%);border:1px solid rgba(255,255,255,.35);"></div>
        <div style="font-weight:700;font-size:32.4px;letter-spacing:.28em;text-transform:uppercase;color:{{ $vsAka['countryInk'] ?? '#f2b8bd' }};">{{ __('scoreboard::karate_messages.vs_sample_country') }}</div>
      </div>
      <div style="font-family:'Anton',sans-serif;font-size:71.3px;line-height:.95;text-transform:uppercase;color:#fff;text-shadow:0 6px 30px rgba(0,0,0,.8);">{{ __('scoreboard::karate_messages.vs_sample_name') }}</div>
      <div style="display:flex;align-items:center;gap:13px;margin-top:4.3px;">
        <div style="width:69.1px;height:69.1px;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.2);border-radius:50%;"></div>
        <div style="font-weight:600;font-size:30.2px;letter-spacing:.12em;text-transform:uppercase;color:rgba(232,230,224,.9);">{{ __('scoreboard::karate_messages.vs_sample_club') }}</div>
      </div>
    </div>
  </div>
</div>
