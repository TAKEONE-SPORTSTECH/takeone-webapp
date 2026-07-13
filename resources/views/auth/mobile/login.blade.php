@extends('layouts.app')

@section('hide-navbar')
@endsection

@push('styles')
<style>
    /* ── Mobile login "Aurora" v2 — airier, calmer rhythm. Scoped, independent of Tailwind build ── */
    .ml-screen {
        --p: 250 65% 65%;            /* brand primary */
        position: fixed; inset: 0;
        display: flex; flex-direction: column;
        background: #0e0a1f;
        overflow: hidden;
        font-family: 'Inter', system-ui, sans-serif;
    }

    /* Animated aurora mesh */
    .ml-aurora { position: absolute; inset: -30%; z-index: 0; filter: blur(64px); opacity: .85; }
    .ml-aurora span {
        position: absolute; border-radius: 50%; mix-blend-mode: screen;
        animation: ml-drift 16s ease-in-out infinite;
    }
    .ml-aurora .a1 { width: 58vw; height: 58vw; top: -10%; left: -12%;
        background: radial-gradient(circle, hsl(250 70% 60%), transparent 70%); }
    .ml-aurora .a2 { width: 52vw; height: 52vw; top: 2%; right: -16%;
        background: radial-gradient(circle, hsl(168 65% 52%), transparent 70%); animation-delay: -4s; }
    .ml-aurora .a3 { width: 46vw; height: 46vw; top: 24%; left: 22%;
        background: radial-gradient(circle, hsl(285 72% 60%), transparent 70%); animation-delay: -8s; }
    @keyframes ml-drift {
        0%,100% { transform: translate(0,0) scale(1); }
        33%     { transform: translate(7%,5%) scale(1.1); }
        66%     { transform: translate(-5%,4%) scale(.95); }
    }

    /* Fine grain texture */
    .ml-grain {
        position: absolute; inset: 0; z-index: 1; opacity: .05; pointer-events: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='120' height='120'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.9' numOctaves='3'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
    }

    /* Floating sparks */
    .ml-spark { position: absolute; z-index: 2; border-radius: 50%; background: #fff;
        box-shadow: 0 0 8px 1px rgba(255,255,255,.65); animation: ml-rise 10s linear infinite; opacity: 0; }
    @keyframes ml-rise {
        0% { transform: translateY(20px) scale(.6); opacity: 0; }
        15% { opacity: .85; }
        85% { opacity: .6; }
        100% { transform: translateY(-120px) scale(1); opacity: 0; }
    }

    /* Hero entrance */
    .ml-up { opacity: 0; transform: translateY(20px); animation: ml-up .8s cubic-bezier(.2,.7,.2,1) forwards; }
    @keyframes ml-up { to { opacity: 1; transform: translateY(0); } }

    .ml-logo-wrap { position: relative; display: inline-flex; }
    .ml-logo-halo {
        position: absolute; inset: -16px; border-radius: 50%; z-index: -1;
        background: radial-gradient(circle, rgba(255,255,255,.32), transparent 65%);
        animation: ml-pulse 3.8s ease-in-out infinite;
    }
    @keyframes ml-pulse { 0%,100% { transform: scale(.92); opacity:.65 } 50% { transform: scale(1.1); opacity:1 } }
    .ml-logo {
        width: 84px; height: 84px; border-radius: 25px;
        background: linear-gradient(150deg, #ffffff, #f3f0fb);
        box-shadow: 0 18px 38px -10px hsl(250 70% 35% / .8), inset 0 2px 0 #fff, 0 0 0 1px rgba(255,255,255,.5);
        display: flex; align-items: center; justify-content: center;
        animation: ml-bob 5s ease-in-out infinite;
    }
    @keyframes ml-bob { 0%,100% { transform: translateY(0) rotate(-1.5deg); } 50% { transform: translateY(-7px) rotate(1.5deg); } }
    .ml-wordmark { font-weight: 800; letter-spacing: .28em; font-size: 11px; color: rgba(255,255,255,.85);
        text-indent: .28em; }

    /* Form sheet */
    .ml-sheet {
        position: relative; z-index: 5;
        background: #fff;
        border-radius: 28px 28px 0 0;
        box-shadow: 0 -18px 50px rgba(8,4,24,.4);
        padding: 18px 20px calc(16px + env(safe-area-inset-bottom));
        max-height: 74vh; overflow-y: auto;
        animation: ml-sheet-in .7s cubic-bezier(.2,.7,.2,1) both;
    }
    @keyframes ml-sheet-in { from { transform: translateY(50px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
    .ml-grab { width: 36px; height: 4px; border-radius: 99px; background: #eae7f4; margin: 0 auto 14px; }

    /* Login method tabs */
    .ml-tabs { display: flex; gap: 3px; background: #f4f2fb; padding: 3px; border-radius: 11px; margin-bottom: 14px; }
    .ml-tab { flex: 1; padding: 8px; border-radius: 8px; border: none; cursor: pointer;
              font-size: 12.5px; font-weight: 700; transition: all .15s;
              background: transparent; color: #7a7590; }
    .ml-tab.is-active { background: #fff; color: hsl(250 65% 53%); box-shadow: 0 2px 6px rgba(80,60,160,.14); }

    /* Floating-label field — compact, no left icon */
    .ml-field { position: relative; margin-bottom: 11px; }
    .ml-field input {
        width: 100%; padding: 17px 16px 6px 16px;
        background: hsl(250 25% 97%);
        border: 1.5px solid hsl(250 20% 91%);
        border-radius: 13px; font-size: 15px; color: #1a1430;
        transition: border-color .2s, box-shadow .2s, background .2s;
        appearance: none; outline: none;
    }
    .ml-field input:focus {
        border-color: hsl(var(--p));
        background: #fff;
        box-shadow: 0 0 0 3.5px hsl(var(--p) / .13);
    }
    .ml-field label {
        position: absolute; left: 16px; top: calc(50% + 4px); transform: translateY(-50%);
        font-size: 14.5px; color: hsl(250 10% 58%); pointer-events: none;
        transition: all .16s ease; }
    .ml-field input:focus + label,
    .ml-field input:not(:placeholder-shown) + label {
        top: 8px; transform: translateY(0); font-size: 10.5px; font-weight: 600; color: hsl(var(--p));
        letter-spacing: .02em; }
    .ml-eye { position: absolute; right: 4px; top: calc(50% + 4px); transform: translateY(-50%);
        width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;
        color: hsl(250 10% 60%); background: none; border: 0; cursor: pointer; font-size: 16px; }

    /* Field-level error */
    .ml-err { display: flex; align-items: flex-start; gap: 5px; margin: -6px 2px 10px; font-size: 12px; font-weight: 600; color: #dc2626; }

    /* Remember + forgot row — compact */
    .ml-row { display: flex; align-items: center; justify-content: space-between; margin: 0 2px 14px; }
    .ml-remember { display: flex; align-items: center; gap: 7px; font-size: 13px; color: #6b6480; cursor: pointer; }
    .ml-remember input { width: 16px; height: 16px; border-radius: 5px; accent-color: hsl(250 65% 58%); }
    .ml-forgot { font-size: 13px; font-weight: 600; color: hsl(250 65% 55%); }

    /* CTA with shimmer sweep */
    .ml-cta {
        position: relative; width: 100%; padding: 13px; border: 0; cursor: pointer;
        border-radius: 14px; color: #fff; font-size: 15px; font-weight: 700; letter-spacing: .02em;
        background: linear-gradient(120deg, hsl(250 65% 60%), hsl(268 68% 61%), hsl(168 58% 51%));
        background-size: 200% 100%;
        box-shadow: 0 10px 22px -10px hsl(250 65% 50% / .55);
        overflow: hidden; transition: transform .15s, box-shadow .2s;
        animation: ml-sheen 7s ease infinite;
        display: flex; align-items: center; justify-content: center; gap: 6px;
    }
    @keyframes ml-sheen { 0%,100% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } }
    .ml-cta:active { transform: scale(.98); box-shadow: 0 8px 16px -8px hsl(250 65% 50% / .55); }
    .ml-cta::after {
        content: ''; position: absolute; top: 0; left: -60%; width: 40%; height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,.4), transparent);
        transform: skewX(-20deg); animation: ml-shimmer 3.6s ease-in-out infinite; }
    @keyframes ml-shimmer { 0% { left: -60%; } 55%,100% { left: 130%; } }

    /* Footer — one calm, compact block instead of stacked cards */
    .ml-footer { margin-top: 14px; padding-top: 12px; border-top: 1px solid hsl(250 20% 94%); text-align: center; }
    .ml-footer p { font-size: 13px; }
    .ml-footer-link { display: inline-flex; align-items: center; gap: 6px; margin-top: 8px; font-size: 12px;
        font-weight: 600; color: hsl(250 30% 45%); text-decoration: none; }
    .ml-footer-link i { font-size: 14px; color: hsl(250 55% 58%); }

    @media (prefers-reduced-motion: reduce) {
        .ml-aurora span, .ml-logo, .ml-spark, .ml-cta, .ml-cta::after { animation: none !important; }
    }
</style>
@endpush

@section('content')
{{-- Mobile login — "Aurora" v2. Separate from desktop per CLAUDE.md split.
     Same field names + action so AuthenticatedSessionController@store is unchanged. --}}
<div class="ml-screen">

    {{-- Living aurora background --}}
    <div class="ml-aurora"><span class="a1"></span><span class="a2"></span><span class="a3"></span></div>
    <div class="ml-grain"></div>
    <span class="ml-spark" style="width:5px;height:5px;left:18%;bottom:44%;animation-delay:0s"></span>
    <span class="ml-spark" style="width:3px;height:3px;left:72%;bottom:50%;animation-delay:3s"></span>
    <span class="ml-spark" style="width:4px;height:4px;left:45%;bottom:40%;animation-delay:6s"></span>

    {{-- ── Hero — trimmed for a calmer, less crowded first screen ── --}}
    <div class="relative flex flex-col items-center justify-center text-center px-8"
         style="z-index:4; flex: 1 1 auto; min-height: 0; padding-top: calc(.75rem + env(safe-area-inset-top));">
        <div class="ml-up" style="animation-delay:.05s">
            <div class="ml-logo-wrap">
                <span class="ml-logo-halo"></span>
                <div class="ml-logo">
                    <img src="{{ asset('images/logo.png') }}" alt="TAKEONE" style="height:50px;width:50px;object-fit:contain">
                </div>
            </div>
        </div>
        <div class="ml-up ml-wordmark" style="animation-delay:.14s; margin-top:12px">TAKEONE</div>
        <h1 class="ml-up" style="animation-delay:.22s; color:#fff; font-size:25px; font-weight:800; letter-spacing:-.02em; margin-top:12px; line-height:1.15">
            {{ __('auth.welcome_back') }}
        </h1>
        <p class="ml-up" style="animation-delay:.3s; color:rgba(255,255,255,.7); font-size:13.5px; margin-top:6px">
            {{ __('auth.club_waiting') }}
        </p>
    </div>

    {{-- ── Form sheet ── --}}
    <div class="ml-sheet" style="flex: 0 0 auto;" x-data="{ reveal: false, tab: @js(session('magic_sent') ? 'link' : 'password') }">
        <div class="ml-grab"></div>

        {{-- Flash error (e.g. expired session / page expired) --}}
        @if(session('error'))
        <div style="display:flex; gap:10px; align-items:flex-start; margin-bottom:12px; padding:10px 13px;
                    background:#fef2f2; border:1px solid #fecaca; border-radius:14px; font-size:13px; color:#991b1b">
            <i class="bi bi-exclamation-circle" style="margin-top:2px"></i>
            <p style="flex:1; margin:0">{{ session('error') }}</p>
        </div>
        @endif

        {{-- Tabs: password vs passwordless login link --}}
        <div class="ml-tabs">
            <button type="button" @click="tab='password'" class="ml-tab" :class="{ 'is-active': tab==='password' }">
                <i class="bi bi-shield-lock" style="margin-inline-end:5px"></i>{{ __('auth.tab_password') }}
            </button>
            <button type="button" @click="tab='link'" class="ml-tab" :class="{ 'is-active': tab==='link' }">
                <i class="bi bi-envelope-paper" style="margin-inline-end:5px"></i>{{ __('auth.tab_magic') }}
            </button>
        </div>

        <form method="POST" action="{{ route('login') }}" x-show="tab==='password'" x-cloak>
            @csrf

            {{-- Email / Phone --}}
            <div class="ml-field">
                <input id="email" type="text" name="email" value="{{ old('email') }}"
                       placeholder=" " required autocomplete="username">
                <label for="email">{{ __('auth.email_or_phone') }}</label>
            </div>
            @error('email')
                <p class="ml-err"><i class="bi bi-exclamation-circle" style="margin-top:1px"></i>{{ $message }}</p>
            @enderror

            {{-- Password --}}
            <div class="ml-field">
                <input id="password" :type="reveal ? 'text' : 'password'" name="password"
                       placeholder=" " required autocomplete="current-password" style="padding-right:46px">
                <label for="password">{{ __('auth.password') }}</label>
                <button type="button" class="ml-eye" @click="reveal = !reveal"
                        :aria-label="reveal ? @js(__('auth.hide_password')) : @js(__('auth.show_password'))">
                    <i class="bi" :class="reveal ? 'bi-eye-slash' : 'bi-eye'"></i>
                </button>
            </div>
            @error('password')
                <p class="ml-err"><i class="bi bi-exclamation-circle" style="margin-top:1px"></i>{{ $message }}</p>
            @enderror

            {{-- Remember + forgot --}}
            <div class="ml-row">
                <label class="ml-remember" for="remember">
                    <input type="checkbox" id="remember" name="remember" {{ old('remember') ? 'checked' : '' }}>
                    {{ __('auth.remember_me') }}
                </label>
                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" class="ml-forgot">{{ __('auth.forgot_password') }}</a>
                @endif
            </div>

            {{-- CTA --}}
            <button type="submit" class="ml-cta">
                <span>{{ __('auth.sign_in') }}</span><i class="bi bi-arrow-right-short" style="font-size:22px"></i>
            </button>
        </form>

        {{-- Passwordless magic-link login tab --}}
        <div x-show="tab==='link'" x-cloak>
            {{-- Confirmation after a link is sent --}}
            @if(session('magic_sent'))
            <div style="display:flex; gap:12px; align-items:flex-start; margin-bottom:12px; padding:10px 13px;
                        background:#ecfdf5; border:1px solid #a7f3d0; border-radius:14px; font-size:13px; color:#065f46">
                <i class="bi bi-envelope-check" style="margin-top:2px"></i>
                <div style="flex:1">
                    <p style="font-weight:600; margin-bottom:4px">{{ __('auth.magic_sent_title') }}</p>
                    <p style="color:#047857">{{ __('auth.magic_sent_body') }}</p>
                </div>
            </div>
            @endif

            <form method="POST" action="{{ route('login.magic') }}">
                @csrf
                <p style="text-align:center; font-size:13px; color:#6b6480; margin-bottom:16px">{{ __('auth.magic_prompt') }}</p>
                <div class="ml-field">
                    <input id="magic_email" type="email" name="email" value="{{ old('email') }}" placeholder=" " required autocomplete="email">
                    <label for="magic_email">{{ __('auth.email') }}</label>
                </div>
                <button type="submit" class="ml-cta">
                    <i class="bi bi-envelope-paper" style="font-size:18px"></i><span>{{ __('auth.magic_cta') }}</span>
                </button>
            </form>
        </div>

        {{-- Unverified email notice --}}
        @if(session('unverified_email'))
        <div style="display:flex; gap:12px; align-items:flex-start; margin-top:12px; padding:10px 13px;
                    background:#fffbeb; border:1px solid #fde68a; border-radius:14px; font-size:13px; color:#92400e">
            <i class="bi bi-envelope-exclamation" style="margin-top:2px"></i>
            <div style="flex:1">
                <p style="font-weight:600; margin-bottom:4px">{{ __('auth.email_not_verified') }}</p>
                <p style="color:#b45309; margin-bottom:8px">{{ __('auth.didnt_receive') }}</p>
                <form method="POST" action="{{ route('verification.resend.public') }}">
                    @csrf
                    <input type="hidden" name="email" value="{{ session('unverified_email') }}">
                    <button type="submit" style="text-decoration:underline; font-weight:600; font-size:12px; color:#92400e">
                        {{ __('auth.resend_verification') }}
                    </button>
                </form>
            </div>
        </div>
        @endif

        {{-- Footer: register + app download, unified into one calm block --}}
        <div class="ml-footer">
            <p style="color:#6b6480; margin:0">
                {{ __('auth.new_to_takeone') }}
                <a href="{{ route('register') }}" style="font-weight:700; color:hsl(250 65% 55%)">{{ __('auth.create_account') }}</a>
            </p>

            {{-- Download the Android app (hidden inside the installed app) --}}
            <a id="ml-download" href="{{ url('/app/takeone.apk') }}" download class="ml-footer-link">
                <i class="bi bi-android2"></i>
                <span>{{ __('nav.get_app') }}</span>
                <i class="bi bi-download" style="font-size:12px; opacity:.6"></i>
            </a>
        </div>
        <script>
            (function () {
                var C = window.Capacitor;
                if (C && typeof C.isNativePlatform === 'function' && C.isNativePlatform()) {
                    var e = document.getElementById('ml-download');
                    if (e) e.style.display = 'none';
                }
            })();
        </script>
    </div>
</div>
@endsection
