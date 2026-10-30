{{--
    The score bar for a bout sent out of the platform (App\Media\BoutShareRenderer).

    One frame of the bout page's on-video scoreboard, frozen at one moment and
    screenshotted with a transparent background, so it can be laid over the
    footage by ffmpeg. The markup is the bout page's own (drafts/score-bar.html,
    as used verbatim in events::personal.mobile.bout-video) — same geometry,
    same colours — drawn server-side instead of by the player's script.

    The clock box is drawn EMPTY on purpose: the clock ticks every second and
    ffmpeg draws it, so a five-minute bout is a dozen screenshots, not three
    hundred.

    Every value arrives escaped through {{ }}; the page fetches nothing (the
    renderer resolves no host names) and reads only local fonts and flags.
--}}
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
@font-face { font-family: 'Barlow Condensed'; font-weight: 400; src: url('file://{{ $fonts }}/barlow-condensed/BarlowCondensed-Regular.ttf'); }
@font-face { font-family: 'Barlow Condensed'; font-weight: 600 700; src: url('file://{{ $fonts }}/barlow-condensed/BarlowCondensed-Bold.ttf'); }
@font-face { font-family: 'Barlow Condensed'; font-weight: 800 900; src: url('file://{{ $fonts }}/barlow-condensed/BarlowCondensed-Black.ttf'); }
@font-face { font-family: 'Zen Old Mincho'; font-weight: 400; src: url('file://{{ $fonts }}/zen-old-mincho/ZenOldMincho-Regular.ttf'); }
@font-face { font-family: 'Zen Old Mincho'; font-weight: 700; src: url('file://{{ $fonts }}/zen-old-mincho/ZenOldMincho-Bold.ttf'); }

html, body { margin: 0; padding: 0; background: transparent; overflow: hidden; }
.msb-root {
    position: absolute; inset: 0;
    font-family: 'Barlow Condensed', sans-serif;
    color: #efe9e0;
    -webkit-font-smoothing: antialiased;
    overflow: hidden;
}
.msb-root * { box-sizing: border-box; }
.msb-scale { position: absolute; top: 0; left: 0; width: 1150px; height: {{ $canvasHeight }}px; transform-origin: top left; transform: scale({{ $scale }}); }

.msb-info { position: absolute; top: 22px; left: 26px; display: flex; align-items: stretch; gap: 12px; }
.msb-info .rule { width: 4px; background: linear-gradient(#e8534a, #7c1d18); }
/* A sent video cannot switch these rows off the way the page's gear menu can,
   and a white hall wall swallows them — so they get the ticker rows' own dark
   backing. The only departure from the page's markup. */
.msb-info .txt  { display: flex; flex-direction: column; gap: 2px; padding: 5px 10px; background: rgba(10,10,12,.62); }
.msb-info .disc { font-family: 'Zen Old Mincho', serif; font-size: 15px; letter-spacing: .34em; color: #efe9e0; text-transform: uppercase; text-shadow: 0 1px 6px rgba(0,0,0,.9); }
.msb-info .sub  { font-size: 13px; letter-spacing: .28em; color: #cfc9c1; text-transform: uppercase; text-shadow: 0 1px 6px rgba(0,0,0,.9); }

.msb-feed { position: absolute; top: 56px; right: 24px; width: 262px; display: flex; flex-direction: column; gap: 7px; }
.msb-feed .hdr { align-self: flex-end; padding: 4px 10px; background: rgba(10,10,12,.62); display: flex; align-items: center; justify-content: flex-end; gap: 8px; font-size: 12px; letter-spacing: .3em; color: #d5cfc7; text-transform: uppercase; text-shadow: 0 1px 6px rgba(0,0,0,.9); }
.msb-feed .hdr .dot { width: 7px; height: 7px; border-radius: 50%; background: #e8534a; }
.msb-feed .list { display: flex; flex-direction: column; gap: 7px; }
.msb-feed .entry { display: flex; align-items: center; justify-content: flex-end; gap: 10px; padding: 7px 10px; background: rgba(10,10,12,.62); border-right: 3px solid transparent; }
.msb-feed .entry .ts   { font-size: 12px; line-height: 1; letter-spacing: .16em; color: #79736c; }
.msb-feed .entry .name { font-size: 17px; line-height: 1; letter-spacing: .12em; font-weight: 700; color: #efe9e0; text-transform: uppercase; }
.msb-feed .entry .pts  { font-family: 'Zen Old Mincho', serif; font-size: 19px; line-height: 1; font-weight: 700; }
</style>
</head>
<body>
<div class="msb-root">
  <div class="msb-scale">

    @if ($disc !== '' || $sub !== '')
    <div class="msb-info">
        <div class="rule"></div>
        <div class="txt">
            @if ($disc !== '')<div class="disc">{{ $disc }}</div>@endif
            @if ($sub !== '')<div class="sub">{{ $sub }}</div>@endif
        </div>
    </div>
    @endif

    @if ($feed !== [])
    <div class="msb-feed">
        <div class="hdr"><span class="dot"></span><span>{{ $feedTitle }}</span></div>
        <div class="list">
            @foreach ($feed as $entry)
            <div class="entry" style="border-color:{{ $entry['colour'] }}">
                <span class="ts">{{ $entry['ts'] }}</span>
                <span class="name">{{ $entry['name'] }}</span>
                <span class="pts" style="color:{{ $entry['colour'] }}">+{{ $entry['pts'] }}</span>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    <div style="position:absolute;left:0;right:0;bottom:56px;padding:0 26px;display:flex;align-items:stretch;gap:0;height:84px;z-index:2">

      <div style="flex:1 1 0;min-width:0;transform:skewX(-9deg);overflow:hidden;background:linear-gradient(90deg, rgba(122,26,22,.94), rgba(180,52,44,.9));border-bottom:3px solid #ff6a5e">
        <div style="transform:skewX(9deg);height:100%;padding:0 16px;display:flex;align-items:center;gap:12px">
          <div style="flex:1 1 auto;min-width:0;display:flex;flex-direction:column;gap:3px">
            <div style="display:flex;align-items:center;gap:10px;min-width:0">
              @if ($red['flag'])
              <span style="width:26px;height:17px;flex:none;background:#000 url('file://{{ $red['flag'] }}') center/cover;box-shadow:0 0 0 1px rgba(255,255,255,.3)"></span>
              @endif
              <span style="flex:1 1 auto;min-width:0;font-size:25px;line-height:1;font-weight:800;color:#fff;text-transform:uppercase;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ $red['name'] }}</span>
            </div>
            @if ($red['club'] !== '')
            <div style="display:flex;align-items:center;gap:8px;min-width:0">
              <span style="flex:1 1 auto;min-width:0;font-size:12px;letter-spacing:.03em;color:rgba(255,232,228,.85);text-transform:uppercase;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ $red['club'] }}</span>
            </div>
            @endif
          </div>
          <div style="width:60px;flex:none;display:flex;flex-direction:column;align-items:flex-end;gap:5px">
            <span style="font-size:11px;letter-spacing:.24em;color:rgba(255,236,232,.7)">{{ $redLabel }}</span>
            <span style="font-family:'Zen Old Mincho',serif;font-size:46px;line-height:.8;font-weight:700;color:#fff;text-shadow:0 6px 20px rgba(0,0,0,.5)">{{ $scoreRed }}</span>
          </div>
        </div>
      </div>

      {{-- The clock box. Its numerals are ffmpeg's (see the file header). --}}
      <div style="width:118px;flex:none;transform:skewX(-9deg);background:rgba(9,9,11,.9);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:2px;border-bottom:3px solid #2c2a28">
        <div style="transform:skewX(9deg);font-size:11px;letter-spacing:.3em;color:#8f8a83;text-transform:uppercase">{{ $roundText }}</div>
        <div style="transform:skewX(9deg);font-size:34px;line-height:1;height:34px"></div>
        <div style="transform:skewX(9deg);display:flex;gap:4px">
          <span style="width:20px;height:3px;background:#e8534a"></span>
          <span style="width:20px;height:3px;background:#e8534a"></span>
          <span style="width:20px;height:3px;background:#3a3734"></span>
        </div>
      </div>

      <div style="flex:1 1 0;min-width:0;transform:skewX(-9deg);overflow:hidden;background:linear-gradient(90deg, rgba(30,72,140,.9), rgba(18,44,92,.94));border-bottom:3px solid #6aa6ff">
        <div style="transform:skewX(9deg);height:100%;padding:0 16px;display:flex;align-items:center;gap:12px">
          <div style="width:60px;flex:none;display:flex;flex-direction:column;align-items:flex-start;gap:5px">
            <span style="font-size:11px;letter-spacing:.24em;color:rgba(226,238,255,.7)">{{ $blueLabel }}</span>
            <span style="font-family:'Zen Old Mincho',serif;font-size:46px;line-height:.8;font-weight:700;color:#fff;text-shadow:0 6px 20px rgba(0,0,0,.5)">{{ $scoreBlue }}</span>
          </div>
          <div style="flex:1 1 auto;min-width:0;display:flex;flex-direction:column;gap:3px;align-items:flex-end;text-align:right">
            <div style="display:flex;align-items:center;gap:10px;min-width:0">
              <span style="flex:1 1 auto;min-width:0;font-size:25px;line-height:1;font-weight:800;color:#fff;text-transform:uppercase;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ $blue['name'] }}</span>
              @if ($blue['flag'])
              <span style="width:26px;height:17px;flex:none;background:#000 url('file://{{ $blue['flag'] }}') center/cover;box-shadow:0 0 0 1px rgba(255,255,255,.3)"></span>
              @endif
            </div>
            @if ($blue['club'] !== '')
            <div style="display:flex;align-items:center;gap:8px;min-width:0">
              <span style="flex:1 1 auto;min-width:0;font-size:12px;letter-spacing:.03em;color:rgba(226,238,255,.85);text-transform:uppercase;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ $blue['club'] }}</span>
            </div>
            @endif
          </div>
        </div>
      </div>
    </div>

    <div style="position:absolute;left:26px;right:26px;bottom:48px;height:5px;display:flex;gap:2px;z-index:2">
        @foreach ($segments as $colour)
        <div style="flex:1;background:{{ $colour }}"></div>
        @endforeach
    </div>

  </div>
</div>
</body>
</html>
