{{--
    A claim link that leads nowhere.

    ONE page for every kind of failure — unknown, wrong secret, expired,
    revoked, already used. Distinguishing them would tell a stranger which
    links are real, which is the whole attack against a link that is itself the
    credential. It says what to DO about it, which is the only thing the person
    holding a dead link actually needs.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ config('locales.' . app()->getLocale() . '.dir', 'ltr') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ __('events.claim_dead_title') }}</title>
    <meta name="robots" content="noindex, nofollow">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    @vite(['resources/css/app.css'])
</head>
<body class="antialiased text-white" style="font-family:'Inter',sans-serif; background:#06060e;">
    <div class="min-h-[100dvh] flex flex-col items-center justify-center text-center px-8"
         style="background: radial-gradient(120% 80% at 50% 0%, hsl(250 65% 65% / .35) 0%, transparent 60%), #06060e;">

        <span class="m-float w-20 h-20 rounded-3xl grid place-items-center bg-white/10 border border-white/15 backdrop-blur">
            <i class="bi bi-link-45deg text-3xl text-white/70"></i>
        </span>

        <h1 class="m-in text-[22px] font-black mt-6 leading-tight">{{ __('events.claim_dead_title') }}</h1>
        <p class="m-in text-[13.5px] text-white/60 mt-2.5 max-w-sm leading-relaxed" style="animation-delay:.08s">
            {{ __('events.claim_dead_body') }}
        </p>

        {{-- Deliberately no logo of any kind. This page is shown for a link
             that resolved to NOTHING, so it cannot name the club or the event
             without confirming which links are real — and it must not name the
             platform, because the rest of this flow never does. --}}
    </div>
</body>
</html>
