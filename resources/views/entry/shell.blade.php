{{--
    The SEALED shell — a platform screen wearing the event's skin.

    The public event surface is a standalone app: somebody who opens a shared
    link must never be handed off to the platform behind it, and that includes
    the organiser who signs in to RUN the event. So the management screens are
    served a second time under /e/{uuid}/admin/… and rendered through here
    instead of the member shell.

    What it is, precisely:
      · `layouts.app`, so every runtime a management screen depends on is
        present and identical — jQuery, Alpine, the toast container, the
        confirm dialog, the data-bs-* bridge, realtime.js, the Vite bundle.
        Re-implementing those here would be a second product.
      · `hide-navbar`, and it does NOT extend `layouts.personal-mobile` — so
        the platform's top bar, side drawer and bottom tab bar are simply not
        rendered. That chrome is where every route out of the event lived.
      · the event's own skin over the top: its palette, its column on a wide
        screen, its organiser footer — the same two partials the public poster
        uses, so the two surfaces are one design.
      · the event's title and icon in the tab, never the platform's. The reader
        was sent a competition and has no idea what is serving it.

    Pages reach it by extending `$shell` rather than a hard-coded layout:

        @extends($shell ?? 'layouts.personal-mobile')

    `App\Http\Middleware\SealEventPage` shares `$shell` (and `$skin`) on the
    mirrored routes only, so with nothing shared every page behaves exactly as
    it did before. A page whose body sits in `content` rather than
    `personal-content` writes `@section($contentSection ?? 'content')`, and the
    same middleware shares that too.

    Expects `$skin` from App\Events\Support\PublicEventSkin.
--}}
@extends('layouts.app')

@section('title', $skin['title'].($skin['host'] ? ' · '.$skin['host'] : ''))

{{-- The tab wears the club's mark on the event's colour — the same icon the
     public poster and the installed home-screen app use. --}}
@section('favicon')
    <link rel="icon" type="image/png" sizes="192x192" href="{{ $skin['icons'][192] }}">
    <link rel="apple-touch-icon" href="{{ $skin['icons'][180] }}">

    {{-- The sealed screens are the same app as the poster, so the phone's
         status bar is tinted with the same black ground. `layouts.app` declares
         no theme-color of its own — without this the management pages would sit
         under the phone's default white bar while every other page of the app
         sits under a black one. Ridden in on the `favicon` section because that
         is the only hook layouts.app opens in its <head>. --}}
    <meta name="theme-color" content="{{ \App\Events\Support\PublicBrand::GROUND }}">
@endsection

@section('hide-navbar', true)

@section('content')
    @include('entry.partials.skin-style')

    {{-- The same wrapper as the poster's <main>, as a div: `layouts.app`
         already opened a <main> and nesting one inside it is invalid. Padding
         is the mobile shell's `px-4 py-4` verbatim, because these blades cancel
         it with `-mx-4` to bleed their hero bands to both edges — a wider
         wrapper leaves a strip of ground down each side of every header. The
         bottom-tab clearance is dropped with the bottom tabs.

         ⚠️ The column from entry/partials/skin-style STAYS. Every page under
         /e/{uuid} is the MOBILE app at every width, management screens
         included; serving a laptop the desktop blades full-width was tried on
         2026-09-03 and reverted at the user's instruction. --}}
    <div class="ev-app ev-app-top mobile-stagger px-4 py-4 min-h-[60vh]">
        @yield('personal-content')
    </div>

    @include('entry.partials.footer')
@endsection
