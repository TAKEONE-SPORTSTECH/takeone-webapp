{{--
    One corner's athlete bar on the Taekwondo wall board, at a POSITION — see
    partials/score-card for the corner/position split.
--}}
@php
  $k = $side === 'aka' ? 'Aka' : 'Ao';
  $c = $SK[$side];
  $logo = '<div id="sb'.$k.'Logo" style="width:170px; height:170px; flex:0 0 auto; border-radius:50%; background-color:rgba(255,255,255,0.1); background-size:cover; background-position:center; border:3px solid rgba(255,255,255,0.4); box-shadow:0 10px 40px rgba(0,0,0,0.6);"></div>';
@endphp
@if ($pos === 'l')
      <div style="display:flex; align-items:center; gap:28px; background:linear-gradient(90deg, {{ $c['barFrom'] }} 0%, rgba(10,10,14,0.95) 85%); border-top:6px solid {{ $c['barLine'] }}; padding:0 40px 0 70px; min-width:0; animation:barIn 0.7s 0.55s cubic-bezier(0.22,1,0.36,1) both;">
        {!! $logo !!}
        <div style="flex:1 1 auto; min-width:0; display:flex; flex-direction:column; gap:8px;">
          <div id="sb{{ $k }}Name" style="max-width:100%; font-family:'Anton',sans-serif; font-size:78px; line-height:1; text-transform:uppercase; color:#fff;"></div>
          <div style="display:flex; align-items:center; gap:16px; max-width:100%;">
@else
      <div style="display:flex; align-items:center; justify-content:flex-end; gap:28px; background:linear-gradient(270deg, {{ $c['barFrom'] }} 0%, rgba(10,10,14,0.95) 85%); border-top:6px solid {{ $c['barLine'] }}; padding:0 70px 0 40px; min-width:0; animation:barIn 0.7s 0.7s cubic-bezier(0.22,1,0.36,1) both;">
        <div style="flex:1 1 auto; min-width:0; display:flex; flex-direction:column; align-items:flex-end; gap:8px; text-align:right;">
          <div id="sb{{ $k }}Name" style="max-width:100%; font-family:'Anton',sans-serif; font-size:78px; line-height:1; text-transform:uppercase; color:#fff;"></div>
          <div style="display:flex; align-items:center; gap:16px; max-width:100%; flex-direction:row-reverse;">
@endif
            <div id="sb{{ $k }}Flag" style="width:84px; flex:0 0 auto; aspect-ratio:4/3; background-size:100% 100%; image-rendering:auto; background-position:center; border:2px solid rgba(255,255,255,0.45);"></div>
            <div id="sb{{ $k }}Country" style="max-width:470px; flex:0 0 auto; min-width:0; font-weight:700; font-size:44px; letter-spacing:0.1em; text-transform:uppercase; color:rgba(255,255,255,0.9); white-space:nowrap;"></div>
            <div style="width:8px; height:8px; flex:0 0 auto; transform:rotate(45deg); background:oklch(0.85 0.16 85);"></div>
            <div id="sb{{ $k }}Club" style="flex:0 1 auto; min-width:0; font-weight:600; font-size:38px; letter-spacing:0.06em; text-transform:uppercase; color:rgba(232,230,224,0.75); white-space:nowrap;"></div>
          </div>
        </div>
@if ($pos !== 'l')
        {!! $logo !!}
@endif
      </div>
