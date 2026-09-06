{{--
    The standalone shell for every page a stranger sees.

    It is the APP's page environment with the app taken away: the same
    stylesheet, the same tokens, the same motion vocabulary as
    `/me/events/{uuid}` — and no top bar, no side drawer, no bottom tabs, no
    footer bar. The person opening this has no account and nothing to navigate
    to, so the EVENT is the whole of the page.

    WHITE-LABELLED. No TAKEONE mark anywhere, and that is the point: they were
    sent A COMPETITION from an Instagram story or a WhatsApp message and have no
    idea what platform is behind it. A logo in the footer tells them they landed
    somewhere else — the one thing a shared link must never do. Tab, icon,
    splash, share card and footer all say the EVENT, backed by its host club.

    It is also installable AS the event: App\Events\Support\PublicBrand builds a
    per-event web-app manifest and icon, so adding the link to a home screen
    gives the competition's own name under the club's own mark, opening with no
    browser chrome.

    Expects the payload from App\Events\Support\PublicEvent as $e — which
    deliberately speaks the SAME key vocabulary as the member page's, so both
    can render from the same partials.

    Sections: `body` (required), `body-class`, and the `styles` / `scripts`
    stacks. A page that wants the dark poster treatment overrides `body-class`
    and pushes its own surface rules; the default is this shell's own light
    ground (`--pg`), declared in the <style> block below.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ config('locales.' . app()->getLocale() . '.dir', 'ltr') }}">
<head>
    <meta charset="utf-8">
    {{-- viewport-fit=cover is load-bearing: without it every env(safe-area-inset-*)
         in the page resolves to 0 and the safe-area padding silently does nothing. --}}
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- The phone tints its status/address bar with this. It used to be the
         EVENT's colour, which painted the whole top of the handset blue on
         every screen — the chrome competing with the page instead of getting
         out of its way. It is the page's own ground now, so the bar reads as
         part of the page and the coloured hero band below it starts as
         content. The default is the white every other page sits on; the
         COVER overrides the section, because it is black. --}}
    <meta name="theme-color" content="@yield('theme-color', '#ffffff')">
    {{-- The POSTER is meant to be found; the organiser's sign-in and the entry
         form are not. `$noindex` is set by the pages that are nobody's search
         result (entry/public/manage, entry/public/enrol). --}}
    <meta name="robots" content="{{ ($noindex ?? false) ? 'noindex, nofollow' : 'index, follow' }}">

    <title>{{ $e['title'] }}@if($e['host']) · {{ $e['host'] }}@endif</title>
    <meta name="description" content="{{ Str::limit(strip_tags($e['about'] ?? ''), 160) ?: $e['title'] }}">

    {{-- The mark is the CLUB's, on the EVENT's colour — never the platform's.
         The tab of a shared link should look like the thing that was shared. --}}
    <link rel="icon" type="image/png" sizes="192x192" href="{{ $e['icons'][192] }}">
    <link rel="apple-touch-icon" href="{{ $e['icons'][180] }}">

    {{-- Installable as its own app: its own name, its own icon, its own start
         page, and no browser chrome once it is on a home screen. --}}
    <link rel="manifest" href="{{ $e['manifest'] }}">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="{{ Str::limit($e['title'], 18, '') }}">
    <meta name="application-name" content="{{ Str::limit($e['title'], 18, '') }}">

    {{-- The link is going into WhatsApp and Instagram, so the card it unfurls
         into IS part of the design. --}}
    <meta property="og:type" content="website">
    {{-- og:site_name is the EVENT's host, not ours — it is the line a chat app
         prints above the card, and it must not announce a platform the sender
         never mentioned. --}}
    @if($e['host'])<meta property="og:site_name" content="{{ $e['host'] }}">@endif
    <meta property="og:title" content="{{ $e['title'] }}">
    <meta property="og:description" content="{{ Str::limit(strip_tags($e['about'] ?? ''), 200) ?: ($e['date'] ?? '') }}">
    @if($e['photo'])<meta property="og:image" content="{{ $e['photo'] }}">@endif
    <meta property="og:url" content="{{ url()->current() }}">
    <meta name="twitter:card" content="{{ $e['photo'] ? 'summary_large_image' : 'summary' }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    {{-- The same sheet, from the same place, as layouts/app.blade.php — the
         entry list and the officiating sheet print a flag beside a name, and
         `fi fi-bh` has to resolve here too. Not a new dependency: identical
         URL and version to the one the app shell already loads. --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flag-icons@6.6.6/css/flag-icons.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>[x-cloak]{display:none!important}</style>

    {{-- The app's own stylesheet — the tokens, the components and the motion
         classes the member page uses. This is what makes the two pages look
         like one product rather than two that were styled separately. --}}
    @vite(['resources/css/app.css'])

    {{-- Live updates.

         Only for a SIGNED-IN reader: `/realtime/token` is behind `auth` and
         MQTT topics are keyed on the numeric user id, so an anonymous visitor
         has no channel to subscribe to and would buy a failed fetch and
         nothing else.

         This surface loads none of the platform shell by design, which is why
         it needs its own entry point rather than `app.js` — see
         resources/js/realtime-only.js. Before this, every page under /e/{uuid}
         was frozen until somebody pulled to refresh.

         ⚠️ Still open: an ANONYMOUS spectator's poster and bracket. That wants
         a per-EVENT public topic, not a per-user one, plus a decision about
         what a stranger may be pushed. --}}
    @auth
        @vite(['resources/js/realtime-only.js'])
    @endauth

    @include('eventlab::entry.partials.skin-style')
    @stack('styles')
</head>
<body class="@yield('body-class', 'antialiased')" style="background: var(--pg); color: var(--ink);">

    {{-- The page wrapper the member event page renders inside
         (layouts/personal-mobile's <main>), minus the shell's bottom-tab
         clearance — there is no bottom bar here to clear. --}}
    <main class="ev-app ev-app-top mobile-stagger px-4 py-4 min-h-[60vh]">
        @yield('body')
    </main>

    {{-- The foot of the page names the ORGANISER, because they are who this
         belongs to. A platform logo here would be the first thing telling a
         visitor they are on somebody else's website. --}}
    @include('eventlab::entry.partials.footer')

    @stack('scripts')

    {{-- ===== NO auto-fullscreen =====

         There WAS a script here that armed the visitor's first idle tap and
         spent it on `requestFullscreen()`, so a shared link opened without
         browser chrome. Removed on 2026-09-03 at the user's instruction: "there
         are places when I tap on any of them the display goes full screen. this
         must not be the case."

         And it is the right call. A page that takes over the whole screen from a
         tap the reader aimed at something else is a page that did something they
         did not ask for — on a competition link sent by WhatsApp, the address
         bar is the only thing telling them where they are. Chromeless is still
         available, by the honest route: the web-app manifest (PublicBrand) means
         adding the event to a home screen opens it standalone, because that is a
         decision somebody made on purpose.

         Do not reintroduce a fullscreen request on this surface. --}}
</body>
</html>
