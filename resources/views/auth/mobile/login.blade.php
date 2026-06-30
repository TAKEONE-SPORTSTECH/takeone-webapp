@extends('layouts.app')

@section('hide-navbar')
@endsection

@push('styles')
<style>
    /* ── Mobile login "Aurora" — scoped so it renders independent of Tailwind build ── */
    .ml-screen {
        --p: 250 65% 65%;            /* brand primary */
        position: fixed; inset: 0;
        display: flex; flex-direction: column;
        background: #0e0a1f;
        overflow: hidden;
        font-family: 'Inter', system-ui, sans-serif;
    }

    /* Animated aurora mesh */
    .ml-aurora { position: absolute; inset: -30%; z-index: 0; filter: blur(60px); opacity: .9; }
    .ml-aurora span {
        position: absolute; border-radius: 50%; mix-blend-mode: screen;
        animation: ml-drift 14s ease-in-out infinite;
    }
    .ml-aurora .a1 { width: 60vw; height: 60vw; top: -8%; left: -10%;
        background: radial-gradient(circle, hsl(250 70% 60%), transparent 70%); }
    .ml-aurora .a2 { width: 55vw; height: 55vw; top: 5%; right: -15%;
        background: radial-gradient(circle, hsl(168 70% 55%), transparent 70%); animation-delay: -3s; }
    .ml-aurora .a3 { width: 50vw; height: 50vw; top: 28%; left: 20%;
        background: radial-gradient(circle, hsl(285 75% 62%), transparent 70%); animation-delay: -7s; }
    @keyframes ml-drift {
        0%,100% { transform: translate(0,0) scale(1); }
        33%     { transform: translate(8%,6%) scale(1.12); }
        66%     { transform: translate(-6%,4%) scale(.94); }
    }

    /* Fine grain texture */
    .ml-grain {
        position: absolute; inset: 0; z-index: 1; opacity: .06; pointer-events: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='120' height='120'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.9' numOctaves='3'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
    }

    /* Floating sparks */
    .ml-spark { position: absolute; z-index: 2; border-radius: 50%; background: #fff;
        box-shadow: 0 0 8px 1px rgba(255,255,255,.7); animation: ml-rise 9s linear infinite; opacity: 0; }
    @keyframes ml-rise {
        0% { transform: translateY(20px) scale(.6); opacity: 0; }
        15% { opacity: .9; }
        85% { opacity: .7; }
        100% { transform: translateY(-120px) scale(1); opacity: 0; }
    }

    /* Hero entrance */
    .ml-up { opacity: 0; transform: translateY(22px); animation: ml-up .8s cubic-bezier(.2,.7,.2,1) forwards; }
    @keyframes ml-up { to { opacity: 1; transform: translateY(0); } }

    /* Logo: entrance handled by .ml-up wrapper; bob/halo live on inner els (no animation clash) */
    .ml-logo-wrap { position: relative; display: inline-flex; }
    .ml-logo-halo {
        position: absolute; inset: -18px; border-radius: 50%; z-index: -1;
        background: radial-gradient(circle, rgba(255,255,255,.35), transparent 65%);
        animation: ml-pulse 3.6s ease-in-out infinite;
    }
    @keyframes ml-pulse { 0%,100% { transform: scale(.92); opacity:.7 } 50% { transform: scale(1.12); opacity:1 } }
    .ml-logo {
        width: 92px; height: 92px; border-radius: 28px;
        background: linear-gradient(150deg, #ffffff, #f3f0fb);
        box-shadow: 0 22px 50px -12px hsl(250 70% 35% / .85), inset 0 2px 0 #fff, 0 0 0 1px rgba(255,255,255,.5);
        display: flex; align-items: center; justify-content: center;
        animation: ml-bob 4.8s ease-in-out infinite;
    }
    @keyframes ml-bob { 0%,100% { transform: translateY(0) rotate(-1.5deg); } 50% { transform: translateY(-8px) rotate(1.5deg); } }
    .ml-wordmark { font-weight: 800; letter-spacing: .34em; font-size: 13px; color: rgba(255,255,255,.92);
        text-indent: .34em; }

    /* Form sheet */
    .ml-sheet {
        position: relative; z-index: 5;
        background: #fff;
        border-radius: 30px 30px 0 0;
        box-shadow: 0 -16px 50px rgba(8,4,24,.45);
        padding: 22px 24px calc(26px + env(safe-area-inset-bottom));
        animation: ml-sheet-in .7s cubic-bezier(.2,.7,.2,1) both;
    }
    @keyframes ml-sheet-in { from { transform: translateY(60px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
    .ml-grab { width: 44px; height: 5px; border-radius: 99px; background: #e6e2f0; margin: 0 auto 18px; }

    /* Floating-label field */
    .ml-field { position: relative; margin-bottom: 16px; }
    .ml-field input {
        width: 100%; padding: 22px 46px 10px 46px;
        background: hsl(250 30% 97%);
        border: 1.5px solid hsl(250 25% 90%);
        border-radius: 16px; font-size: 16px; color: #1a1430;
        transition: border-color .2s, box-shadow .2s, background .2s;
        appearance: none; outline: none;
    }
    .ml-field input:focus {
        border-color: hsl(var(--p));
        background: #fff;
        box-shadow: 0 0 0 4px hsl(var(--p) / .14);
    }
    .ml-field .ml-ico { position: absolute; left: 15px; top: calc(50% + 6px); transform: translateY(-50%);
        font-size: 18px; color: hsl(250 15% 60%); transition: color .2s; pointer-events: none; }
    .ml-field input:focus ~ .ml-ico { color: hsl(var(--p)); }
    .ml-field label {
        position: absolute; left: 46px; top: calc(50% + 6px); transform: translateY(-50%);
        font-size: 16px; color: hsl(250 12% 55%); pointer-events: none;
        transition: all .18s ease; }
    .ml-field input:focus + label,
    .ml-field input:not(:placeholder-shown) + label {
        top: 13px; transform: translateY(0); font-size: 11px; font-weight: 600; color: hsl(var(--p));
        letter-spacing: .02em; }
    .ml-eye { position: absolute; right: 12px; top: calc(50% + 6px); transform: translateY(-50%);
        width: 34px; height: 34px; display: flex; align-items: center; justify-content: center;
        color: hsl(250 12% 55%); background: none; border: 0; cursor: pointer; font-size: 18px; }

    /* CTA with shimmer sweep */
    .ml-cta {
        position: relative; width: 100%; padding: 16px; border: 0; cursor: pointer;
        border-radius: 16px; color: #fff; font-size: 16px; font-weight: 700; letter-spacing: .02em;
        background: linear-gradient(120deg, hsl(250 65% 60%), hsl(268 70% 62%), hsl(168 60% 52%));
        background-size: 200% 100%;
        box-shadow: 0 14px 30px -8px hsl(250 65% 50% / .6);
        overflow: hidden; transition: transform .15s, box-shadow .2s;
        animation: ml-sheen 6s ease infinite;
        display: flex; align-items: center; justify-content: center; gap: 8px;
    }
    @keyframes ml-sheen { 0%,100% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } }
    .ml-cta:active { transform: scale(.98); box-shadow: 0 8px 18px -8px hsl(250 65% 50% / .6); }

    /* Login method tabs */
    .ml-tab { flex: 1; padding: 11px; border-radius: 10px; border: none; cursor: pointer;
              font-size: 13.5px; font-weight: 700; transition: all .15s;
              background: transparent; color: #6b6480; }
    .ml-tab.is-active { background: #fff; color: hsl(250 65% 55%); box-shadow: 0 1px 4px rgba(80,60,160,.14); }
    .ml-cta::after {
        content: ''; position: absolute; top: 0; left: -60%; width: 40%; height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,.45), transparent);
        transform: skewX(-20deg); animation: ml-shimmer 3.2s ease-in-out infinite; }
    @keyframes ml-shimmer { 0% { left: -60%; } 55%,100% { left: 130%; } }

    @media (prefers-reduced-motion: reduce) {
        .ml-aurora span, .ml-logo, .ml-spark, .ml-cta, .ml-cta::after { animation: none !important; }
    }
</style>
@endpush

@section('content')
{{-- Mobile login — "Aurora". Separate from desktop per CLAUDE.md split.
     Same field names + action so AuthenticatedSessionController@store is unchanged. --}}
<div class="ml-screen">

    {{-- Living aurora background --}}
    <div class="ml-aurora"><span class="a1"></span><span class="a2"></span><span class="a3"></span></div>
    <div class="ml-grain"></div>
    <span class="ml-spark" style="width:5px;height:5px;left:18%;bottom:42%;animation-delay:0s"></span>
    <span class="ml-spark" style="width:3px;height:3px;left:72%;bottom:48%;animation-delay:2.5s"></span>
    <span class="ml-spark" style="width:4px;height:4px;left:45%;bottom:38%;animation-delay:5s"></span>
    <span class="ml-spark" style="width:3px;height:3px;left:60%;bottom:52%;animation-delay:7s"></span>

    {{-- ── Hero ── --}}
    <div class="relative flex-1 flex flex-col items-center justify-center text-center px-8"
         style="z-index:4; padding-top: calc(1rem + env(safe-area-inset-top));">
        <div class="ml-up" style="animation-delay:.05s">
            <div class="ml-logo-wrap">
                <span class="ml-logo-halo"></span>
                <div class="ml-logo">
                    <img src="{{ asset('images/logo.png') }}" alt="TAKEONE" style="height:52px;width:52px;object-fit:contain">
                </div>
            </div>
        </div>
        <div class="ml-up ml-wordmark" style="animation-delay:.14s; margin-top:18px">TAKEONE</div>
        <h1 class="ml-up" style="animation-delay:.22s; color:#fff; font-size:30px; font-weight:800; letter-spacing:-.02em; margin-top:14px; line-height:1.1">
            {{ __('auth.welcome_back') }}
        </h1>
        <p class="ml-up" style="animation-delay:.3s; color:rgba(255,255,255,.78); font-size:15px; margin-top:8px">
            {{ __('auth.club_waiting') }}
        </p>
    </div>

    {{-- ── Form sheet ── --}}
    <div class="ml-sheet" x-data="{ reveal: false, tab: @js(session('magic_sent') ? 'link' : 'password') }">
        <div class="ml-grab"></div>

        {{-- Flash error (e.g. expired session / page expired) --}}
        @if(session('error'))
        <div style="display:flex; gap:10px; align-items:flex-start; margin-bottom:18px; padding:13px 15px;
                    background:#fef2f2; border:1px solid #fecaca; border-radius:14px; font-size:13px; color:#991b1b">
            <i class="bi bi-exclamation-circle" style="margin-top:2px"></i>
            <p style="flex:1; margin:0">{{ session('error') }}</p>
        </div>
        @endif

        {{-- Tabs: password vs passwordless login link --}}
        <div style="display:flex; gap:6px; background:#f3f1fb; padding:5px; border-radius:14px; margin-bottom:22px">
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
                <i class="bi bi-person ml-ico"></i>
            </div>
            @error('email')
                <p style="margin:-8px 4px 14px; font-size:12.5px; font-weight:600; color:#dc2626">
                    <i class="bi bi-exclamation-circle mr-1"></i>{{ $message }}
                </p>
            @enderror

            {{-- Password --}}
            <div class="ml-field">
                <input id="password" :type="reveal ? 'text' : 'password'" name="password"
                       placeholder=" " required autocomplete="current-password" style="padding-right:50px">
                <label for="password">{{ __('auth.password') }}</label>
                <i class="bi bi-shield-lock ml-ico"></i>
                <button type="button" class="ml-eye" @click="reveal = !reveal"
                        :aria-label="reveal ? @js(__('auth.hide_password')) : @js(__('auth.show_password'))">
                    <i class="bi" :class="reveal ? 'bi-eye-slash' : 'bi-eye'"></i>
                </button>
            </div>
            @error('password')
                <p style="margin:-8px 4px 14px; font-size:12.5px; font-weight:600; color:#dc2626">
                    <i class="bi bi-exclamation-circle mr-1"></i>{{ $message }}
                </p>
            @enderror

            {{-- Remember + forgot --}}
            <div class="flex items-center justify-between" style="margin:4px 2px 20px">
                <label class="flex items-center gap-2" for="remember" style="font-size:13.5px; color:#5b5470; cursor:pointer">
                    <input type="checkbox" id="remember" name="remember" {{ old('remember') ? 'checked' : '' }}
                           style="width:17px;height:17px;border-radius:5px;accent-color:hsl(250 65% 60%)">
                    {{ __('auth.remember_me') }}
                </label>
                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" style="font-size:13.5px; font-weight:600; color:hsl(250 65% 58%)">{{ __('auth.forgot_password') }}</a>
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
            <div style="display:flex; gap:12px; align-items:flex-start; margin-bottom:18px; padding:13px 15px;
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
                <p style="text-align:center; font-size:13px; color:#6b6480; margin-bottom:12px">{{ __('auth.magic_prompt') }}</p>
                <div class="ml-field">
                    <input id="magic_email" type="email" name="email" value="{{ old('email') }}" placeholder=" " required autocomplete="email">
                    <label for="magic_email">{{ __('auth.email') }}</label>
                    <i class="bi bi-envelope ml-ico"></i>
                </div>
                <button type="submit" class="ml-cta">
                    <i class="bi bi-envelope-paper" style="font-size:18px"></i><span>{{ __('auth.magic_cta') }}</span>
                </button>
            </form>
        </div>

        {{-- Unverified email notice --}}
        @if(session('unverified_email'))
        <div style="display:flex; gap:12px; align-items:flex-start; margin-top:18px; padding:13px 15px;
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

        {{-- Register --}}
        <p style="text-align:center; margin-top:22px; font-size:14px; color:#6b6480">
            {{ __('auth.new_to_takeone') }}
            <a href="{{ route('register') }}" style="font-weight:700; color:hsl(250 65% 58%)">{{ __('auth.create_account') }}</a>
        </p>
    </div>
</div>
@endsection
