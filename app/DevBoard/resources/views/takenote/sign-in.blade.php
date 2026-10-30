{{--
    TakeNote's own sign-in, for a person who opens the app signed out. It posts
    to the platform's normal login (POST /login), so every rule of that door
    still applies: throttle, lockout, email verification, two-factor. On
    success the login sends people to url.intended, which the controller set to
    the app.
--}}
@php($rtl = app()->getLocale() === 'ar')
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    @include('devboard::takenote.head')
    <style>
    body { min-height: 100dvh; background: radial-gradient(80% 45% at 50% 12%, #3a2b78 0%, transparent 70%), var(--desk); }
    .door { min-height: 100dvh; max-width: 420px; margin: 0 auto; display: flex; flex-direction: column;
        padding: calc(48px + var(--safe-t)) max(22px, var(--sa-r, env(safe-area-inset-right, 0px))) calc(28px + var(--safe-b)) max(22px, var(--sa-l, env(safe-area-inset-left, 0px))); }
    .mark { width: 92px; height: 92px; border-radius: 26px; margin: 0 auto; box-shadow: 0 20px 40px -14px rgba(124,107,245,.7); animation: rise .6s var(--spring) both; }
    h1 { margin: 18px 0 0; text-align: center; font: 400 44px/1 var(--serif); animation: rise .6s var(--spring) both .05s; }
    .by { text-align: center; font-size: 11px; letter-spacing: .14em; text-transform: uppercase; color: rgba(255,255,255,.5); margin-top: 8px; }
    .tag { text-align: center; color: rgba(255,255,255,.65); font-size: 13px; line-height: 1.5; margin: 14px auto 0; max-width: 30ch; }
    form { margin-top: 30px; display: flex; flex-direction: column; gap: 10px; animation: rise .6s var(--spring) both .1s; }
    .field { height: 50px; border-radius: 14px; background: rgba(255,255,255,.07); border: 1px solid rgba(255,255,255,.13); display: flex; align-items: center; gap: 10px; padding: 0 14px; transition: border-color .2s, background .2s; }
    .field:focus-within { border-color: var(--violet); background: rgba(124,107,245,.12); }
    .field i { opacity: .55; }
    .field input { flex: 1; min-width: 0; height: 100%; border: 0; outline: 0; background: none; color: #fff; font: 500 16px var(--sans); }
    .field input::placeholder { color: rgba(255,255,255,.42); }
    .keep { display: flex; align-items: center; gap: 9px; font-size: 12px; color: rgba(255,255,255,.7); margin: 2px 2px 6px; }
    .keep i { width: 18px; height: 18px; border-radius: 6px; background: var(--violet); display: grid; place-items: center; font-size: 12px; }
    .go { height: 52px; border-radius: 15px; background: var(--violet); color: #fff; font-weight: 700; font-size: 15px; display: flex; align-items: center; justify-content: center; gap: 8px; box-shadow: 0 14px 26px -12px rgba(124,107,245,.9); transition: transform .15s var(--ease); }
    .go:active { transform: scale(.97); }
    .go[disabled] { opacity: .7; }
    .msg { border-radius: 12px; padding: 10px 12px; font-size: 13px; line-height: 1.45; background: #fef3c7; color: #78350f; display: flex; gap: 8px; }
    .msg.is-error { background: #fee2e2; color: #991b1b; }
    .links { margin-top: 16px; text-align: center; }
    .links a { color: #c4b8ff; font-size: 13px; font-weight: 600; text-decoration: none; }
    .fine { margin-top: auto; padding-top: 28px; text-align: center; font-size: 11.5px; line-height: 1.5; color: rgba(255,255,255,.42); }
    </style>
</head>
<body>
<main class="door">
    <img class="mark" src="{{ asset('images/takenote/icon-192.png') }}" alt="" width="92" height="92">
    <h1>TakeNote</h1>
    <div class="by">{{ __('devboard::messages.tn_by') }}</div>
    <p class="tag">{{ __('devboard::messages.tn_tagline') }}</p>

    <form method="POST" action="{{ url('/login') }}" id="door-form">
        @csrf
        @if ($errors->any())
            <div class="msg is-error" role="alert"><i class="bi bi-exclamation-circle"></i><span>{{ $errors->first() }}</span></div>
        @elseif (session('warning'))
            <div class="msg" role="status"><i class="bi bi-envelope-exclamation"></i><span>{{ session('warning') }}</span></div>
        @elseif (session('status'))
            <div class="msg" role="status"><i class="bi bi-info-circle"></i><span>{{ session('status') }}</span></div>
        @endif
        <label class="field">
            <i class="bi bi-person"></i>
            <input type="text" name="email" id="door-email" value="{{ old('email') }}" placeholder="{{ __('devboard::messages.tn_email') }}"
                   autocomplete="username" autocapitalize="off" spellcheck="false" required aria-label="{{ __('devboard::messages.tn_email') }}">
        </label>
        <label class="field">
            <i class="bi bi-lock"></i>
            <input type="password" name="password" id="door-password" placeholder="{{ __('devboard::messages.tn_password') }}"
                   autocomplete="current-password" required aria-label="{{ __('devboard::messages.tn_password') }}">
        </label>
        <div class="keep"><i class="bi bi-check-lg"></i>{{ __('devboard::messages.tn_keep') }}</div>
        <button type="submit" class="go" id="door-go">{{ __('devboard::messages.tn_sign_in') }}<i class="bi bi-arrow-right flip"></i></button>
    </form>
    <div class="links"><a href="{{ route('password.request') }}">{{ __('devboard::messages.tn_forgot') }}</a></div>
    {{-- somebody here without an account: TAKEONE's own sign-up (a friend with an invite LINK never lands here — the link opens its own sign-up) --}}
    <div class="links"><a href="{{ \Illuminate\Support\Facades\Route::has('takenote.signup') ? route('takenote.signup') : route('register') }}">{{ __('devboard::messages.tn_new_here') }}</a></div>
    <p class="fine">{{ __('devboard::messages.tn_fine') }}</p>
</main>
<script>
document.getElementById('door-form').addEventListener('submit', function () { document.getElementById('door-go').disabled = true; });
</script>
</body>
</html>
