{{--
    One corner's score card on the Taekwondo wall board, drawn at a POSITION.

    The corner ($side: aka|ao) decides the ids and the colours (Mat\CornerLook
    skin); the position ($pos: l|r) decides the geometry — which way the card
    is cut, which way its gradient runs, which way it slides in. So swapping a
    mat's corners (App\Scoreboard\Corners\MatSides) moves the colour, never the
    shape. With nothing customised the output is the board as designed.
--}}
@php
  $k = $side === 'aka' ? 'Aka' : 'Ao';
  $c = $SK[$side];
  $geo = $pos === 'l'
      ? ['m' => '0 -50px 0 0', 'deg' => '155deg', 'clip' => 'polygon(0 0, 100% 0, calc(100% - 90px) 100%, 0 100%)', 'pad' => 'padding-right:60px;', 'anim' => 'cardInL 0.8s 0.25s']
      : ['m' => '0 0 0 -50px', 'deg' => '205deg', 'clip' => 'polygon(0 0, 100% 0, 100% 100%, 90px 100%)', 'pad' => 'padding-left:60px;', 'anim' => 'cardInR 0.8s 0.4s'];
@endphp
      <div style="position:relative; margin:{{ $geo['m'] }}; background:linear-gradient({{ $geo['deg'] }}, {{ $c['cardFrom'] }} 0%, {{ $c['cardTo'] }} 100%); clip-path:{{ $geo['clip'] }}; display:flex; flex-direction:column; align-items:center; justify-content:center; {{ $geo['pad'] }} box-shadow:0 30px 80px rgba(0,0,0,0.7), 0 0 60px {{ $c['cardGlow'] }}; animation:{{ $geo['anim'] }} cubic-bezier(0.22,1,0.36,1) both;">
        <div style="font-weight:800; font-size:34px; letter-spacing:0.4em; text-transform:uppercase; color:{{ $c['cardLabelInk'] }}; margin-bottom:14px;">{{ $c['label'] }}</div>
        <div id="sb{{ $k }}Score" style="font-family:'Anton',sans-serif; font-size:390px; line-height:0.95; color:{{ $c['scoreInk'] }}; text-shadow:{{ $c['scoreShadow'] }}; font-variant-numeric:tabular-nums;">0</div>
        <div style="display:flex; align-items:center; gap:14px; margin-top:18px;">
          <span style="font-weight:700; font-size:26px; letter-spacing:0.24em; text-transform:uppercase; color:{{ $c['gamInk'] }};">{{ __('scoreboard::taekwondo_messages.sb_gamjeom') }}</span>
          <div id="sb{{ $k }}Gam" style="display:flex; gap:9px;"></div>
        </div>
      </div>
