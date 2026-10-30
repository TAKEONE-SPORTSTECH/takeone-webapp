{{--
    The federation's own shell for its account pages — sign-in, 2FA, password
    reset, security, and the sealed error / no-access page. Standalone (no
    platform layout, no platform links): the federation is its own product.
    The design is the prototype's staff sign-in card.

    @section('card') — the card's contents · $title · $favicon (optional)
--}}
@php
    $rtl = in_array(app()->getLocale(), ['ar', 'fa', 'ur', 'he'], true);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8">
{{-- Light by design: keep the browser's auto-dark and Dark Reader from inverting it (as layouts/app). --}}
<meta name="color-scheme" content="only light">
<meta name="darkreader-lock">
<style>html { color-scheme: only light; }</style>
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex">
<title>@yield('title') · {{ __('federations::messages.login_eyebrow') }}</title>
<link rel="icon" href="{{ $favicon ?? \App\Federations\Support\FederationBrand::favicon(null) }}">
<link rel="stylesheet" href="/federation/app/vendor/fonts/poppins-tajawal.css">
<style>
  *, *::before, *::after { box-sizing: border-box; }
  html, body { margin: 0; min-height: 100%; }
  body {
    background: #0b132b; color: #1e2c4f;
    font-family: 'Poppins', 'Tajawal', system-ui, sans-serif;
    display: grid; place-items: center; min-height: 100vh; min-height: 100dvh;
    padding: max(20px, env(safe-area-inset-top)) 20px max(20px, env(safe-area-inset-bottom));
  }
  .wrap { width: 100%; max-width: 420px; }
  .mark { display: flex; align-items: center; gap: 12px; justify-content: center; margin-bottom: 26px; }
  .mark i { display: block; width: 38px; height: 3px; border-radius: 2px; background: #d8b25f; }
  .mark span { font-size: .8rem; font-weight: 600; letter-spacing: .22em; text-transform: uppercase; color: #fff; }
  .card {
    background: #fff; border-radius: 20px; border-top: 4px solid #d8b25f; padding: 32px 28px;
    box-shadow: 0 30px 80px rgba(0,0,0,.4); animation: rise .3s cubic-bezier(.22,.68,.35,1) both;
  }
  @keyframes rise { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: none; } }
  @media (prefers-reduced-motion: reduce) { .card { animation: none; } }
  .card-foot { margin: 22px 0 0; padding-top: 16px; border-top: 1px solid #eef1f6; text-align: center; font-size: .72rem; color: #97a1b4; }
  .card-logo { display: block; height: 56px; width: auto; max-width: 100%; margin: 0 auto 22px; }
  .eyebrow { font-size: .66rem; font-weight: 700; letter-spacing: .2em; text-transform: uppercase; color: #8a6d20; margin-bottom: 6px; }
  h1 { margin: 0 0 6px; font-size: 1.45rem; font-weight: 700; letter-spacing: -.01em; color: #1e2c4f; }
  .lead { margin: 0 0 22px; font-size: .8rem; color: #6b7689; line-height: 1.5; }
  label { display: block; font-size: .76rem; font-weight: 500; color: #1e2c4f; margin-bottom: 6px; }
  .field {
    width: 100%; height: 48px; border: 1px solid #d5dae3; border-radius: 11px; padding: 0 14px;
    font-family: inherit; font-size: .86rem; color: #1e2c4f; outline: none; margin-bottom: 14px; background: #fff;
    transition: border-color .15s ease, box-shadow .15s ease;
  }
  .field:focus { border-color: #caa14f; box-shadow: 0 0 0 3px rgba(216,178,95,.25); }
  .pw { position: relative; margin-bottom: 14px; }
  .pw .field { margin-bottom: 0; padding-inline-end: 64px; }
  .pw button {
    position: absolute; inset-inline-end: 8px; top: 8px; height: 32px; padding: 0 10px; border: 0; border-radius: 8px;
    background: transparent; font-family: inherit; font-size: .72rem; font-weight: 600; color: #8a6d20; cursor: pointer;
  }
  .pw button:hover { background: rgba(216,178,95,.14); }
  .otp { height: 56px; font-family: ui-monospace, Menlo, monospace; font-size: 1.4rem; letter-spacing: .45em; text-align: center; }
  .error { font-size: .78rem; color: #c0392b; background: #fdecea; border-radius: 10px; padding: 10px 12px; margin-bottom: 14px; line-height: 1.45; }
  .go {
    width: 100%; height: 52px; border: 0; border-radius: 12px; background: linear-gradient(100deg,#edcd85,#d0a854);
    font-family: inherit; font-size: .92rem; font-weight: 600; letter-spacing: .02em; color: #1e2c4f; cursor: pointer;
    box-shadow: 0 8px 20px rgba(202,161,79,.32); transition: transform .15s ease, opacity .15s ease;
  }
  .go:hover { transform: translateY(-1px); }
  .go[disabled] { opacity: .7; cursor: default; transform: none; }
  .links { display: flex; justify-content: space-between; gap: 12px; margin-top: 16px; font-size: .8rem; }
  .links a { font-weight: 500; color: #8a6d20; text-decoration: none; }
  .links a:hover { text-decoration: underline; }
  .foot { text-align: center; margin-top: 22px; font-size: .72rem; color: rgba(255,255,255,.55); }
  .foot a { color: rgba(255,255,255,.75); text-decoration: none; }
  .foot a:hover { color: #edcd85; }
</style>
<style>
  .card + .card { margin-top: 14px; }
  .ok { font-size: .78rem; color: #14663c; background: #e8f5ee; border-radius: 10px; padding: 10px 12px; margin-bottom: 14px; line-height: 1.45; }
  .muted { font-size: .76rem; color: #6b7689; line-height: 1.5; }
  .ghost { width: 100%; height: 46px; border: 1px solid #d5dae3; border-radius: 12px; background: #fff; font-family: inherit;
    font-size: .84rem; font-weight: 600; color: #1e2c4f; cursor: pointer; transition: border-color .15s ease; }
  .ghost:hover { border-color: #caa14f; }
  .danger { color: #c0392b; }
  .status { display: flex; align-items: center; gap: 10px; padding: 12px 14px; border-radius: 12px; margin-bottom: 16px; font-size: .82rem; font-weight: 600; }
  .status i { width: 10px; height: 10px; border-radius: 50%; flex: none; }
  .status.on { background: #e8f5ee; color: #14663c; } .status.on i { background: #1e8a52; }
  .status.off { background: #fdf1e0; color: #a8620f; } .status.off i { background: #e0962a; }
  .qr { display: grid; place-items: center; padding: 14px; border-radius: 14px; background: #f4f6fa; margin-bottom: 12px; }
  .qr svg { width: 180px; height: 180px; }
  .secret { font-family: ui-monospace, Menlo, monospace; font-size: .8rem; letter-spacing: .12em; text-align: center; word-break: break-all; color: #1e2c4f; margin-bottom: 16px; }
  .codes { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 18px; }
  .codes div { font-family: ui-monospace, Menlo, monospace; font-size: .82rem; font-weight: 600; letter-spacing: .06em; text-align: center; padding: 9px; border-radius: 8px; background: #f4f6fa; border: 1px solid #eef1f6; }
  .inline { display: inline; } .inline button { background: none; border: 0; padding: 0; font: inherit; color: #8a6d20; font-weight: 500; cursor: pointer; }
  .inline button:hover { text-decoration: underline; }
  .sub { font-size: .7rem; font-weight: 700; letter-spacing: .16em; text-transform: uppercase; color: #8a6d20; margin: 0 0 8px; }
</style>

</head>
<body>
<main class="wrap">
  {{-- A page about one federation (Security) names it above the card; every
       other page carries the TakeOne logo INSIDE its card (auth/logo). --}}
  @hasSection('brand')
    <div class="mark"><i aria-hidden="true"></i><span>@yield('brand')</span></div>
  @endif
  @yield('card')
</main>
</body>
</html>
