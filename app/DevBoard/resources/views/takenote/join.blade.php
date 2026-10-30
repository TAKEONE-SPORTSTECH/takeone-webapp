{{--
    The door an invite link opens (TakeNoteJoinController): a friend makes an
    account right here — a name, an email, a password — and is writing a
    moment later, no confirmation email to wait for. "I already have an
    account" goes to the platform's login, which comes back to the link.
    $state: 'open' (the form) or 'gone' (used, removed or never real — one
    answer for all three). Also TakeNote's own sign-up for a friend invited by
    EMAIL: 'signup' (the form), 'check' (confirm your email — the link comes
    back here) and 'noaccess' (signed in, but nobody has invited them).
    The look is TakeNote's own sign-in door.
--}}
@php($rtl = app()->getLocale() === 'ar')
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    @include('devboard::takenote.head')
    <meta name="robots" content="noindex, nofollow">
    <style>
    body { min-height: 100dvh; background: radial-gradient(80% 45% at 50% 12%, #3a2b78 0%, transparent 70%), var(--desk); }
    .door { min-height: 100dvh; max-width: 420px; margin: 0 auto; display: flex; flex-direction: column;
        padding: calc(40px + var(--safe-t)) max(22px, var(--sa-r, env(safe-area-inset-right, 0px))) calc(28px + var(--safe-b)) max(22px, var(--sa-l, env(safe-area-inset-left, 0px))); }
    .mark { width: 84px; height: 84px; border-radius: 24px; margin: 0 auto; box-shadow: 0 20px 40px -14px rgba(124,107,245,.7); animation: rise .6s var(--spring) both; }
    .who { margin: 18px auto 0; display: inline-flex; align-self: center; align-items: center; gap: 8px; padding: 6px 12px; border-radius: 999px;
        background: rgba(124,107,245,.2); border: 1px solid rgba(167,139,250,.35); color: #d9d2ff; font-size: 12.5px; font-weight: 700; animation: rise .6s var(--spring) both .03s; }
    h1 { margin: 14px 0 0; text-align: center; font: 800 28px/1.2 var(--sans); letter-spacing: -.02em; animation: rise .6s var(--spring) both .05s; }
    .tag { text-align: center; color: rgba(255,255,255,.7); font-size: 14px; line-height: 1.55; margin: 10px auto 0; max-width: 32ch; }
    form { margin-top: 26px; display: flex; flex-direction: column; gap: 10px; animation: rise .6s var(--spring) both .1s; }
    .field { height: 50px; border-radius: 14px; background: rgba(255,255,255,.07); border: 1px solid rgba(255,255,255,.13); display: flex; align-items: center; gap: 10px; padding: 0 14px; transition: border-color .2s, background .2s; }
    .field:focus-within { border-color: var(--violet); background: rgba(124,107,245,.12); }
    .field.is-bad { border-color: #f87171; }
    .field i { opacity: .55; }
    .field input { flex: 1; min-width: 0; height: 100%; border: 0; outline: 0; background: none; color: #fff; font: 500 16px var(--sans); }
    .field input::placeholder { color: rgba(255,255,255,.42); }
    .go { height: 52px; margin-top: 6px; border-radius: 15px; background: var(--violet); color: #fff; font-weight: 800; font-size: 15px; display: flex; align-items: center; justify-content: center; gap: 8px;
        box-shadow: 0 14px 26px -12px rgba(124,107,245,.95); transition: transform .15s var(--ease); text-decoration: none; }
    .go:active { transform: scale(.97); }
    .go[disabled] { opacity: .7; }
    .msg { border-radius: 12px; padding: 10px 12px; font-size: 13px; line-height: 1.45; background: #fee2e2; color: #991b1b; display: flex; gap: 8px; }
    .private { display: flex; align-items: flex-start; gap: 9px; margin: 4px 2px 0; font-size: 12px; line-height: 1.5; color: rgba(255,255,255,.62); }
    .private i { color: #34d399; margin-top: 1px; }
    .links { margin-top: 18px; text-align: center; }
    .links a { color: #c4b8ff; font-size: 13.5px; font-weight: 600; text-decoration: none; }
    .fine { margin-top: auto; padding-top: 26px; text-align: center; font-size: 11.5px; line-height: 1.5; color: rgba(255,255,255,.42); }
    [dir=rtl] .flip { transform: scaleX(-1); }
    </style>
</head>
<body>
<main class="door">
    <img class="mark" src="{{ asset('images/takenote/icon-192.png') }}" alt="" width="84" height="84">

    @if ($state === 'gone')
        <h1>{{ __('devboard::messages.jn_gone_title') }}</h1>
        <p class="tag">{{ __('devboard::messages.jn_gone_body') }}</p>
        <div class="links"><a href="{{ route('takenote.app') }}">{{ __('devboard::messages.jn_open') }}</a></div>
    @elseif ($state === 'check')
        <h1>{{ __('devboard::messages.jn_check_title') }}</h1>
        <p class="tag">{{ __('devboard::messages.jn_check_body', ['email' => $email]) }}</p>
        @if (session('resent'))
            <p class="private" role="status"><i class="bi bi-check-circle-fill"></i><span>{{ __('devboard::messages.jn_resent') }}</span></p>
        @endif
        <form method="POST" action="{{ route('takenote.signup.resend') }}" id="join-form">
            @csrf
            <button type="submit" class="go" id="join-go"><i class="bi bi-envelope-arrow-up"></i>{{ __('devboard::messages.jn_resend') }}</button>
        </form>
    @elseif ($state === 'noaccess')
        <h1>{{ __('devboard::messages.jn_no_title') }}</h1>
        <p class="tag">{{ __('devboard::messages.jn_no_body') }}</p>
    @else
        @if ($inviter)
            <span class="who"><i class="bi bi-person-heart"></i>{{ __('devboard::messages.mi_title', ['name' => $inviter]) }}</span>
        @endif
        <h1>{{ __($state === 'signup' ? 'devboard::messages.jn_signup_title' : 'devboard::messages.jn_title') }}</h1>
        <p class="tag">{{ __($state === 'signup' ? 'devboard::messages.jn_signup_lead' : 'devboard::messages.jn_lead') }}</p>

        <form method="POST" action="{{ $state === 'signup' ? route('takenote.signup.store') : route('takenote.join.store', $token) }}" id="join-form" novalidate>
            @csrf
            @if ($errors->any())
                <div class="msg" role="alert"><i class="bi bi-exclamation-circle"></i><span>{{ $errors->first() }}</span></div>
            @endif
            <label class="field {{ $errors->has('full_name') ? 'is-bad' : '' }}">
                <i class="bi bi-person"></i>
                <input type="text" name="full_name" value="{{ old('full_name') }}" placeholder="{{ __('devboard::messages.jn_name') }}" aria-label="{{ __('devboard::messages.jn_name') }}" autocomplete="name" maxlength="255" required>
            </label>
            <label class="field {{ $errors->has('email') ? 'is-bad' : '' }}">
                <i class="bi bi-envelope"></i>
                <input type="email" name="email" value="{{ old('email') }}" placeholder="{{ __('devboard::messages.jn_email') }}" aria-label="{{ __('devboard::messages.jn_email') }}" autocomplete="email" autocapitalize="off" spellcheck="false" maxlength="255" required>
            </label>
            <label class="field {{ $errors->has('password') ? 'is-bad' : '' }}">
                <i class="bi bi-lock"></i>
                <input type="password" name="password" placeholder="{{ __('devboard::messages.jn_password') }}" aria-label="{{ __('devboard::messages.jn_password') }}" autocomplete="new-password" required>
            </label>
            <label class="field">
                <i class="bi bi-lock-fill"></i>
                <input type="password" name="password_confirmation" placeholder="{{ __('devboard::messages.jn_password2') }}" aria-label="{{ __('devboard::messages.jn_password2') }}" autocomplete="new-password" required>
            </label>
            <p class="private"><i class="bi bi-shield-lock-fill"></i><span>{{ __($state === 'signup' ? 'devboard::messages.jn_signup_private' : 'devboard::messages.jn_private') }}</span></p>
            <button type="submit" class="go" id="join-go">{{ __($state === 'signup' ? 'devboard::messages.jn_signup_go' : 'devboard::messages.jn_go') }}<i class="bi bi-arrow-right flip"></i></button>
        </form>
        {{-- a link's door: the platform login comes back to the link; the sign-up's: TakeNote's own sign-in --}}
        <div class="links"><a href="{{ $state === 'signup' ? route('takenote.app') : route('login') }}">{{ __('devboard::messages.jn_have') }}</a></div>
    @endif

    <p class="fine">TakeNote · {{ __('devboard::messages.tn_by') }}</p>
</main>
<script>
(function () {
    var f = document.getElementById('join-form');
    if (f) f.addEventListener('submit', function () { document.getElementById('join-go').disabled = true; });
})();
</script>
</body>
</html>
