{{--
    What a screen shows before it knows what it is — for EVERY screen there is.

    A machine is carried into a hall, plugged in, and this is the first and only
    thing on it. Whoever is standing in front of it may never have seen the
    product, is holding a phone, and has a competition starting — so the screen
    asks for one action and gives one thing to point a camera at.

    ── Why this is a component ────────────────────────────────────────────────
    There are three doors into a screen and they are all the same door to the
    person in the hall: the sport-neutral `/screen`, and each sport package's own
    `/court/{token}` and `/karate/court/{token}`. They had drifted into two
    different designs, so a wall could show one screen waiting in red and the one
    beside it waiting in gold. Whatever a screen is for, being unpaired looks the
    same now, and it is fixed in one place.

    The packages keep owning their own screens, their own routes and their own
    poll endpoints — this owns only how being-unpaired LOOKS. Which is the same
    bargain as any other shared component in this project.

    ── The design ─────────────────────────────────────────────────────────────
    Dark, like every other screen this product puts on a wall. A single white
    panel in a row of black ones reads as a screen that has crashed, which is the
    opposite of what this one means.

    The QR is dark-on-light on its own lit card. Not a style choice: a decoder
    finds a symbol by looking for dark modules on a light field, and inverted
    symbols are read by some scanners and not others. This code is the only way
    into a screen, so it takes the polarity everything agrees on.

    The code beside it is not decoration. Hall lighting, a dirty lens, a cracked
    phone or a screen at an angle all beat a scanner, so the fallback is printed
    large, one character per tile, in an alphabet with no vowels and no 0/O/1/I.

    Everything is served by us — the fonts, the mark, the QR. No Google Fonts, no
    favicon service, no CDN: a venue's wifi is captive, metered or filtered as
    often as not, and a screen that needs the open internet to render its own
    pairing code fails on the one morning it matters.
--}}
@props([
    // The six characters on the glass, and the address its QR encodes.
    'code',
    'claimUrl',

    // "Have I been told what I am yet?" — the package's own endpoint, whatever
    // shape it answers in. Optional: a screen with a realtime link of its own
    // may not need to ask.
    'statusUrl' => null,

    // What the answer looks like. The sport-neutral room hands back the address
    // to go to (`{go}`); the package rooms answer `{claimed}` and expect a
    // reload, because the same URL renders the board once it is claimed. Both
    // predate this component and neither is worth changing to suit it.
    'pollMode' => 'go',
    'pollEvery' => 4000,

    // Where "set up another screen" goes. Defaults to the sport-neutral door,
    // which is the right answer for a machine that wants to be something else.
    'restartUrl' => null,
])
@php
    $restartUrl ??= route('screen.new').'?new';

    // Asked here rather than passed in, so no caller has to know how the app is
    // published — and so a screen that offers it can never offer a build that is
    // not there.
    $appUrl = \App\Events\Support\ScreenPairingController::appAvailable('tv')
        ? preg_replace('#^https?://#', '', route('screen.app'))
        : null;
    $tabUrl = \App\Events\Support\ScreenPairingController::appAvailable('tab')
        ? preg_replace('#^https?://#', '', route('screen.app.tab'))
        : null;
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ __('events.screen_title') }}</title>

<style>
@php
    $faces = [
        ['Space Grotesk', 400, 'space-grotesk-400'],
        ['Space Grotesk', 700, 'space-grotesk-700'],
        ['IBM Plex Mono', 400, 'ibm-plex-mono-400'],
        ['IBM Plex Mono', 600, 'ibm-plex-mono-600'],
    ];
    $ranges = [
        'latin' => 'U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD',
        'latin-ext' => 'U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF',
    ];
@endphp
@foreach ($faces as [$family, $weight, $slug])
@foreach ($ranges as $subset => $range)
@font-face {
  font-family: '{{ $family }}';
  font-style: normal;
  font-weight: {{ $weight }};
  font-display: swap;
  src: url("{{ asset('fonts/screen/'.$slug.'-'.$subset.'.woff2') }}") format('woff2');
  unicode-range: {{ $range }};
}
@endforeach
@endforeach

  * { box-sizing: border-box; }
  html, body { margin: 0; padding: 0; background: oklch(0.15 0.015 20); }
  body { min-height: 100vh; color: oklch(0.96 0.005 20); font-family: 'Space Grotesk', sans-serif; }
  a { color: oklch(0.62 0.21 25); text-decoration: none; }
  a:hover { color: oklch(0.7 0.19 25); }

  @keyframes pulse { 0%, 100% { opacity: 1; transform: scale(1); } 50% { opacity: 0.35; transform: scale(0.75); } }

  /* Sized to the viewport rather than on a fixed scaled stage, so one page fills
     a 1080p wall, a 4K panel and a 10" tablet with no scaling layer.

     ── Every size below is bounded by HEIGHT as well as width ────────────────
     This started as min-height:100vh with width-only clamps, and on a wall
     screen it ate its own footer: the app pins overflow shut on a television
     (a scrollbar down the side of a board is a defect), so anything past the
     fold was not merely below the fold, it was unreachable — no scrollbar, no
     remote, no way to see the download buttons at all.
     So the page is exactly one viewport tall and every element is clamped by
     min(<vw>, <vh>): whichever runs out first wins, and the layout can never be
     taller than the glass it is on. A short-and-wide window (a 21:9 TV, a
     tablet with the keyboard up) shrinks the QR instead of pushing the footer
     off the bottom. */
  .page { height: 100vh; height: 100dvh; overflow: hidden;
          display: flex; flex-direction: column;
          /* Overscan. A surprising number of televisions still crop 2-3% of
             every edge, and this page's whole job lives near those edges. */
          padding: 1.4vh 1.2vw;
          background: radial-gradient(110% 80% at 50% -15%, oklch(0.22 0.03 25) 0%, oklch(0.15 0.015 20) 65%); }

  header { display: flex; align-items: center; justify-content: space-between; flex: 0 0 auto;
           padding: clamp(10px, 2.4vh, 34px) clamp(20px, 3.4vw, 64px); }
  .brand { display: flex; align-items: center; gap: 14px; }
  {{-- The bare mark on the background, never a white tile: the logo is a
       transparent PNG, and the tile is exactly what makes it look broken on a
       dark panel. It is red on near-black here, which is the mark's own
       contrast, not something a box has to provide. --}}
  .brand img { width: clamp(30px, min(3vw, 5.6vh), 52px); height: clamp(30px, min(3vw, 5.6vh), 52px);
               object-fit: contain; display: block; }
  .brand-name { font-size: clamp(14px, min(1.4vw, 2.8vh), 24px); font-weight: 700; letter-spacing: 0.14em; }
  .status { display: flex; align-items: center; gap: 10px; padding: clamp(6px, 1.2vh, 10px) clamp(12px, 1.6vw, 18px);
            background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.16);
            border-radius: 999px; font-size: clamp(12px, min(1vw, 2vh), 18px); color: oklch(0.78 0.01 20); }
  /* A live dot, so a passer-by can see the screen is awake and not frozen on a
     still image. Deliberately the only thing on the page that moves — anything
     changing under the symbol makes a phone hunt for focus. */
  .dot { width: 10px; height: 10px; border-radius: 50%; background: oklch(0.62 0.21 25);
         animation: pulse 1.8s ease-in-out infinite; }

  main { flex: 1 1 auto; min-height: 0; display: flex; align-items: center; justify-content: center;
         padding: 0 clamp(20px, 3.4vw, 64px); }
  .cols { display: flex; flex-wrap: wrap; align-items: center; justify-content: center;
          gap: clamp(24px, min(6vw, 7vh), 110px); max-width: 1500px; width: 100%; }
  .left { flex: 1 1 420px; max-width: 720px; display: flex; flex-direction: column;
          gap: clamp(10px, min(2.2vw, 2.6vh), 32px); }
  .kicker { font-family: 'IBM Plex Mono', monospace; font-size: clamp(11px, min(0.95vw, 1.9vh), 17px);
            letter-spacing: 0.22em; text-transform: uppercase; color: oklch(0.62 0.21 25); }
  h1 { margin: 0; font-size: clamp(22px, min(3.2vw, 5.6vh), 54px); font-weight: 700; line-height: 1.12;
       text-wrap: pretty; color: oklch(0.98 0.003 20); }
  .code-label { font-size: clamp(12px, min(1vw, 2vh), 18px); color: oklch(0.68 0.01 20);
                margin-bottom: clamp(6px, 1.2vh, 12px); }
  .code { display: flex; gap: clamp(8px, 0.8vw, 14px); }
  .code span { width: clamp(38px, min(4.6vw, 8vh), 84px); height: clamp(48px, min(5.8vw, 10.4vh), 106px);
               display: flex; align-items: center; justify-content: center;
               background: oklch(0.22 0.02 20); border: 1px solid oklch(0.35 0.03 25);
               border-bottom: 3px solid oklch(0.55 0.21 25); border-radius: 12px;
               font-family: 'IBM Plex Mono', monospace; font-size: clamp(22px, min(3vw, 5.4vh), 56px);
               font-weight: 600; color: oklch(0.97 0.003 20); }
  ol { margin: 0; padding: 0 0 0 1.3em; display: flex; flex-direction: column; gap: clamp(4px, 1vh, 10px);
       font-size: clamp(13px, min(1.15vw, 2.3vh), 21px); line-height: 1.45; color: oklch(0.8 0.008 20); }

  .qr-wrap { display: flex; flex-direction: column; align-items: center; }
  .qr-frame { position: relative; padding: clamp(10px, min(1.2vw, 2.2vh), 20px); }
  .corner { position: absolute; width: 34px; height: 34px; border: 0 solid oklch(0.62 0.21 25); }
  .corner.tl { top: 0; left: 0; border-top-width: 4px; border-left-width: 4px; border-top-left-radius: 14px; }
  .corner.tr { top: 0; right: 0; border-top-width: 4px; border-right-width: 4px; border-top-right-radius: 14px; }
  .corner.bl { bottom: 0; left: 0; border-bottom-width: 4px; border-left-width: 4px; border-bottom-left-radius: 14px; }
  .corner.br { bottom: 0; right: 0; border-bottom-width: 4px; border-right-width: 4px; border-bottom-right-radius: 14px; }
  .qr-card { background: #fff; padding: clamp(10px, min(1.4vw, 2.4vh), 24px); border-radius: 16px;
             box-shadow: 0 16px 50px rgba(0,0,0,0.5); }
  .qr-card svg { display: block; width: clamp(150px, min(22vw, 40vh), 400px); height: auto;
                 shape-rendering: crispEdges; }
  .scan-pill { margin-top: clamp(10px, 2vh, 20px); display: flex; align-items: center; gap: 10px;
               padding: clamp(6px, 1.2vh, 10px) clamp(14px, 2vw, 20px);
               background: oklch(0.62 0.21 25); border-radius: 999px; font-family: 'IBM Plex Mono', monospace;
               font-size: clamp(12px, 0.85vw, 15px); font-weight: 600; letter-spacing: 0.1em; color: #fff; }

  footer { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; flex: 0 0 auto;
           gap: clamp(10px, 1.6vh, 18px);
           padding: clamp(10px, 2.2vh, 32px) clamp(20px, 3.4vw, 64px); border-top: 1px solid rgba(255,255,255,0.12); }
  .dl-group { display: flex; flex-wrap: wrap; gap: clamp(14px, 1.6vw, 24px); }
  .btn { display: flex; align-items: center; gap: clamp(9px, 1.4vw, 14px);
         padding: clamp(7px, 1.4vh, 12px) clamp(14px, 1.8vw, 22px) clamp(7px, 1.4vh, 12px) clamp(11px, 1.4vw, 16px);
         background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.16); border-radius: 14px; }
  .btn:hover { background: rgba(255,255,255,0.1); border-color: oklch(0.62 0.21 25); }
  /* :focus-visible carries as much weight as :hover: on a television these are
     reached with a D-pad, and a button you cannot see the focus on is a button
     you cannot press. */
  .btn:focus-visible { outline: none; background: rgba(255,255,255,0.1);
                       border-color: oklch(0.62 0.21 25); box-shadow: 0 0 0 4px oklch(0.62 0.21 25 / 0.3); }
  .btn .icon { width: clamp(28px, min(2.6vw, 5.2vh), 40px); height: clamp(28px, min(2.6vw, 5.2vh), 40px);
               border-radius: 10px; background: oklch(0.62 0.21 25 / 0.16);
               display: flex; align-items: center; justify-content: center; flex: 0 0 auto; }
  .btn .icon svg { width: 60%; height: 60%; }
  .btn .txt { display: flex; flex-direction: column; gap: 2px; }
  .btn .title { font-size: clamp(12px, min(1.05vw, 2.1vh), 19px); font-weight: 600; color: oklch(0.96 0.005 20); }
  /* The typeable half: a bare TV has no pointer, and somebody enters this into a
     sideloader one letter at a time. */
  .btn .sub { font-family: 'IBM Plex Mono', monospace; font-size: clamp(9px, min(0.8vw, 1.6vh), 14px);
             color: oklch(0.6 0.01 20); }

  @media (prefers-reduced-motion: reduce) { * { animation: none !important; } }
</style>
</head>
<body>

<div class="page">
  <header>
    <div class="brand">
      <img src="{{ asset('images/logo.png') }}" alt="">
      <div class="brand-name">TAKEONE</div>
    </div>
    <div class="status"><span class="dot"></span>{{ __('events.screen_waiting') }}</div>
  </header>

  <main>
    <div class="cols">
      <div class="left">
        <div class="kicker">{{ __('events.screen_eyebrow') }}</div>
        <h1>{{ __('events.screen_headline') }}</h1>

        <div>
          <div class="code-label">{{ __('events.screen_code_label') }}</div>
          {{-- One tile per character. Split rather than printed as one word: at
               this size a six-character block reads as a single unbroken shape
               from across a hall, and the tiles are what make somebody read it
               out correctly. --}}
          <div class="code">
            @foreach (str_split($code) as $character)
              <span>{{ $character }}</span>
            @endforeach
          </div>
        </div>

        <ol>
          <li>{{ __('events.screen_step_open') }}</li>
          <li>{{ __('events.screen_step_scan') }}</li>
          <li>{{ __('events.screen_step_choose') }}</li>
        </ol>
      </div>

      <div class="qr-wrap">
        <div class="qr-frame">
          <span class="corner tl"></span><span class="corner tr"></span>
          <span class="corner bl"></span><span class="corner br"></span>
          {{-- Rendered offline by bacon/bacon-qr-code — a hall screen must never
               wait on an external QR service, and this one has to work the
               moment it boots.

               Margin 4 is the spec's quiet zone, not padding taste: a scanner
               needs four clear modules around the symbol to lock onto it, and
               this one gets read at an angle, across a hall, on a phone somebody
               is holding one handed. The card's white padding adds to that; it
               is not a substitute, which is why the quiet zone is baked into the
               symbol itself.

               DARK modules on a LIGHT field, which is the argument order:
               svg($data, $size, $margin, $dark, $light). The page this replaced
               passed those the other way round and had been rendering an
               INVERTED symbol — legal, and phones read it, but some
               library-based scanners only ever try one polarity, and this code
               is the only way into a screen. Not a thing to leave to chance on a
               competition morning. --}}
          <div class="qr-card">{!! \App\Support\Qr::svg($claimUrl, 400, 4, '#141010', '#ffffff') !!}</div>
        </div>
        <div class="scan-pill">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
               stroke-width="2.2" stroke-linecap="round" aria-hidden="true">
            <rect x="3" y="3" width="7" height="7" rx="1"></rect>
            <rect x="14" y="3" width="7" height="7" rx="1"></rect>
            <rect x="3" y="14" width="7" height="7" rx="1"></rect>
            <line x1="14" y1="14" x2="21" y2="21"></line>
            <line x1="21" y1="14" x2="14" y2="21"></line>
          </svg>
          {{ __('events.screen_scan_pill') }}
        </div>
      </div>
    </div>
  </main>

  <footer>
    {{-- The app, for a machine that does not have it yet. Each button renders
         only when that build has actually been published, so the page can never
         offer a download that 404s. --}}
    <div class="dl-group">
      @if ($appUrl)
        <a class="btn" href="{{ route('screen.app') }}" download>
          <span class="icon">
            {{-- A television: a wide screen on a stand. The device drawn, not a
                 download arrow — two identical buttons say nothing about which
                 machine each is for, and a silhouette reads faster than a word
                 from across a room. --}}
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="oklch(0.68 0.2 25)"
                 stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <rect x="2.5" y="4" width="19" height="13" rx="2"></rect>
              <line x1="8" y1="20.5" x2="16" y2="20.5"></line>
            </svg>
          </span>
          <span class="txt">
            <span class="title">{{ __('events.screen_app_tv') }}</span>
            <span class="sub">{{ $appUrl }}</span>
          </span>
        </a>
      @endif

      @if ($tabUrl)
        <a class="btn" href="{{ route('screen.app.tab') }}" download>
          <span class="icon">
            {{-- A tablet: taller than wide, with a home dot. The silhouette is
                 what separates it from the television at a glance. --}}
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="oklch(0.68 0.2 25)"
                 stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <rect x="4.5" y="2.5" width="15" height="19" rx="2.5"></rect>
              <line x1="10.5" y1="18.5" x2="13.5" y2="18.5"></line>
            </svg>
          </span>
          <span class="txt">
            <span class="title">{{ __('events.screen_app_tab') }}</span>
            <span class="sub">{{ $tabUrl }}</span>
          </span>
        </a>
      @endif
    </div>

    {{-- The explicit start-over, so a machine that has already been a screen can
         become a second one. Last and quietest: findable by the person setting
         the wall up, ignorable by everyone else. --}}
    <a class="btn" href="{{ route('screen.new') }}?new">
      <span class="icon">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="oklch(0.68 0.2 25)"
             stroke-width="1.8" stroke-linecap="round" aria-hidden="true">
          <line x1="12" y1="5" x2="12" y2="19"></line>
          <line x1="5" y1="12" x2="19" y2="12"></line>
        </svg>
      </span>
      <span class="title">{{ __('events.screen_another') }}</span>
    </a>
  </footer>
</div>

@if ($statusUrl)
<script>
(function () {
  'use strict';

  // The screen has no keyboard and nobody watching it, so it has to notice being
  // claimed by itself.
  //
  // Slow on purpose. Pairing is a one-off human action measured in tens of
  // seconds, this runs on a low-powered screen over a metered 4G link, and every hall screen
  // does it at once — so seconds are responsive to a person and nothing to the
  // server.
  var GO = @json($pollMode === 'go');

  setInterval(function () {
    fetch(@json($statusUrl), {
      cache: 'no-store',
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    })
      .then(function (r) {
        // This screen is nobody any more — the row was pruned or tidied away.
        // Start again rather than standing here showing a code that will never be
        // found. Nobody in a hall can fix that by hand, so it has to fix itself.
        if (r.status === 404) {
          window.location.replace(@json(route('screen.new', [], false)));

          return null;
        }

        return r.ok ? r.json() : null;
      })
      .then(function (s) {
        if (!s) return;

        // Told where to go — go there. Or told only THAT it was claimed, in which
        // case this same address now renders the board, so reload into it.
        if (GO && s.go) { window.location.replace(s.go); }
        if (!GO && s.claimed) { window.location.reload(); }
      })
      .catch(function () { /* offline — keep showing the code and try again */ });
  }, @json($pollEvery));
})();
</script>
@endif

{{-- Anything the calling screen wants at the tail of the document — a package's
     own realtime link, for instance, so it jumps to its board the moment it is
     paired instead of waiting out the poll. --}}
{{ $slot }}

</body>
</html>
