{{--
    Too many requests.

    This used to be the framework's bare page — black, wordless, no way out —
    and the surface it landed on most was the PUBLIC ENTRY door: somebody with
    no account, tapping "enter this competition" for the first time, told
    nothing except that they were refused. The limit itself has been lifted
    (AppServiceProvider → `public-entry`), but a limit that can be reached at
    all needs an answer a person can act on: what happened, that it is
    temporary, and roughly how long.

    Deliberately self-contained — no layout, no build asset. A throttled request
    is exactly when the rest of the app might not be reachable, and this page
    has to render on its own.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ in_array(app()->getLocale(), ['ar']) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    {{-- This document is LIGHT by design. Say so, or a browser decides for us:
         Chrome's Auto Dark Theme and Android WebView's force-dark invert what
         they take for a light page, and Dark Reader re-paints it after first
         paint. The result is not a dark theme — this product has none — it is
         the palette inside out: a black ground behind white cards, tinted
         tiles gone navy with their icons left bright. `only light` is the
         explicit opt-out (plain `light` is not enough) and `darkreader-lock`
         is that extension's own. Both, because they answer to different
         things; an unknown meta name is ignored everywhere else. --}}
    <meta name="color-scheme" content="light">
    <meta name="darkreader-lock">
    <style>html { color-scheme: only light; }</style>

    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ __('errors.429_title') }}</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh; display: grid; place-items: center;
            padding: 24px calc(24px + env(safe-area-inset-right)) calc(24px + env(safe-area-inset-bottom)) calc(24px + env(safe-area-inset-left));
            background: hsl(220 15% 97%);
            color: hsl(222 15% 18%);
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        .card {
            width: 100%; max-width: 420px; background: #fff; border: 1px solid hsl(210 14% 90%);
            border-radius: 20px; padding: 28px 24px 24px; text-align: center;
            box-shadow: 0 12px 40px -18px rgba(15, 18, 40, .35);
            animation: rise .45s cubic-bezier(.2, .8, .2, 1) both;
        }
        @keyframes rise { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: none; } }
        .badge {
            width: 64px; height: 64px; margin: 0 auto 18px; border-radius: 20px;
            display: grid; place-items: center; font-size: 30px; color: #fff;
            background: linear-gradient(135deg, hsl(250 65% 65%), hsl(250 65% 65% / .7));
        }
        /* The hand ticks rather than spins: this is a wait, not a load. */
        .badge span { display: block; animation: tick 2s steps(8) infinite; }
        @keyframes tick { to { transform: rotate(360deg); } }
        h1 { font-size: 20px; font-weight: 800; margin: 0 0 8px; letter-spacing: -.01em; }
        p { font-size: 14px; line-height: 1.55; color: hsl(222 8% 46%); margin: 0 0 6px; }
        .wait { margin-top: 16px; font-size: 13px; font-weight: 700; color: hsl(250 65% 55%); }
        a.retry {
            display: inline-block; margin-top: 20px; padding: 12px 26px; border-radius: 12px;
            background: hsl(250 65% 65%); color: #fff; text-decoration: none;
            font-size: 15px; font-weight: 700; transition: filter .15s, transform .08s;
        }
        a.retry:hover { filter: brightness(1.07); }
        a.retry:active { transform: scale(.97); }
        @media (prefers-reduced-motion: reduce) { .card, .badge span { animation: none; } }
    </style>
</head>
<body>
    <div class="card">
        <div class="badge"><span>&#9203;</span></div>

        <h1>{{ __('errors.429_title') }}</h1>
        <p>{{ __('errors.429_body') }}</p>

        @php
            /* ⚠️ The error view is handed `$exception`, NOT a $retryAfter — the
               wait is carried in the exception's own Retry-After header, which
               ThrottleRequests already set. Reading it there is what turns
               "refused" into "wait a moment", and guessing a number would be
               worse than saying none. */
            $retryAfter = (int) ($exception?->getHeaders()['Retry-After'] ?? 0);
        @endphp

        @if ($retryAfter > 0)
            <div class="wait">{{ trans_choice('errors.429_retry', $retryAfter, ['seconds' => $retryAfter]) }}</div>
        @endif

        <a class="retry" href="{{ url()->current() }}">{{ __('errors.429_retry_action') }}</a>
    </div>
</body>
</html>
