{{--
    One corner's half of the Taekwondo introduction, at a POSITION — see
    partials/score-card. $part is 'panel' (the tinted backdrop) or 'info' (the
    name block); the two are drawn in separate passes so the stacking order
    of the original layout is kept.
--}}
@php
  $k = $side === 'aka' ? 'Red' : 'Blue';
  $c = $SK[$side];
  $l = $pos === 'l';
@endphp
@if ($part === 'panel')
    <div style="position:absolute; {{ $l ? 'inset:0 auto 0 0' : 'inset:0 0 0 auto' }}; width:56%; clip-path:{{ $l ? 'polygon(0 0, 100% 0, 82% 100%, 0 100%)' : 'polygon(18% 0, 100% 0, 100% 100%, 0 100%)' }}; overflow:hidden; {!! e($c['vsPanel']) !!} animation:{{ $l ? 'panelL' : 'panelR' }} 0.9s cubic-bezier(0.22,1,0.36,1) both;">
      <div id="vs{{ $k }}Photo" style="position:absolute; inset:0; background-size:cover; background-position:center 12%; animation:slowDrift 18s ease-in-out infinite{{ $l ? '' : ' reverse' }};"></div>
      <div style="position:absolute; inset:0; pointer-events:none; background:linear-gradient({{ $l ? '115deg' : '245deg' }}, {{ $c['vsGlow'][0] }} 0%, transparent 55%);background:linear-gradient({{ $l ? '115deg' : '245deg' }}, {{ $c['vsGlow'][1] }} 0%, transparent 55%);"></div>
      <div style="position:absolute; inset:0; pointer-events:none; background:linear-gradient(to top, rgba(5,5,7,0.95) 0%, rgba(5,5,7,0.72) 22%, rgba(5,5,7,0.3) 42%, transparent 62%), linear-gradient(to bottom, rgba(5,5,7,0.82) 0%, rgba(5,5,7,0.35) 18%, transparent 32%);"></div>
    </div>
@else
    <div style="position:absolute; {{ $l ? 'left:43.2px; bottom:118.8px; display:flex; flex-direction:column; align-items:flex-start; gap:13px; z-index:6; max-width:44%; min-width:0;' : 'right:43.2px; bottom:118.8px; display:flex; flex-direction:column; align-items:flex-end; gap:13px; z-index:6; max-width:44%; min-width:0; text-align:right;' }} animation:riseUp 0.8s {{ $l ? '0.7s' : '0.85s' }} cubic-bezier(0.22,1,0.36,1) both;">
      <div style="font-weight:800; font-size:21.6px; letter-spacing:0.35em; color:{{ $c['badgeInk'] }}; {!! e($c['badgeBg']) !!} padding:5.4px 15.1px 5.4px 18.9px;">{{ $c['badge'] }}</div>
      <div style="display:flex; align-items:center; gap:15.1px;{{ $l ? '' : ' flex-direction:row-reverse;' }}">
        <div id="vs{{ $k }}Flag" style="width:56.2px; aspect-ratio:4/3; background-size:100% 100%; image-rendering:auto; background-position:center; border:1px solid rgba(255,255,255,0.35); box-shadow:0 4px 18px rgba(0,0,0,0.6);"></div>
        <div id="vs{{ $k }}Country" style="max-width:760px; font-weight:700; font-size:32.4px; letter-spacing:0.28em; text-transform:uppercase; {!! e($c['country']) !!}"></div>
      </div>
      <div id="vs{{ $k }}Name" style="max-width:100%; font-family:'Anton',sans-serif; font-size:71.3px; line-height:0.95; text-transform:uppercase; color:#fff; text-shadow:0 6px 30px rgba(0,0,0,0.8);"></div>
      <div style="display:flex; align-items:center; gap:13px; margin-top:4.3px;{{ $l ? '' : ' flex-direction:row-reverse;' }}">
        <div id="vs{{ $k }}Logo" style="width:69.1px; height:69.1px; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.2); border-radius:50%; background-size:cover; background-position:center;"></div>
        <div id="vs{{ $k }}Club" style="font-weight:600; font-size:30.2px; letter-spacing:0.12em; text-transform:uppercase; color:rgba(232,230,224,0.9);"></div>
      </div>
      <div id="vs{{ $k }}Chips" style="display:flex; flex-wrap:wrap; gap:9.7px; margin-top:5.4px;{{ $l ? '' : ' justify-content:flex-end;' }}"></div>
    </div>
@endif
