@extends('layouts.app')

@section('hide-navbar')
@endsection

@push('styles')
<style>
    /* ── Mobile forgot-password "Aurora" — matches auth/mobile/login.blade.php exactly ── */
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

    /* Back button */
    .ml-back { position: absolute; z-index: 6; left: 18px; top: calc(14px + env(safe-area-inset-top));
        width: 38px; height: 38px; border-radius: 12px; display: flex; align-items: center; justify-content: center;
        background: rgba(255,255,255,.12); color: #fff; font-size: 18px; text-decoration: none;
        backdrop-filter: blur(6px); transition: background .15s; }
    .ml-back:active { background: rgba(255,255,255,.22); }

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

    /* Field-level error */
    .ml-err { display: flex; align-items: flex-start; gap: 5px; margin: -6px 2px 10px; font-size: 12px; font-weight: 600; color: #dc2626; }

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
        margin-top: 4px;
    }
    @keyframes ml-sheen { 0%,100% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } }
    .ml-cta:active { transform: scale(.98); box-shadow: 0 8px 16px -8px hsl(250 65% 50% / .55); }
    .ml-cta::after {
        content: ''; position: absolute; top: 0; left: -60%; width: 40%; height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,.4), transparent);
        transform: skewX(-20deg); animation: ml-shimmer 3.6s ease-in-out infinite; }
    @keyframes ml-shimmer { 0% { left: -60%; } 55%,100% { left: 130%; } }

    /* Footer — one calm, compact block */
    .ml-footer { margin-top: 14px; padding-top: 12px; border-top: 1px solid hsl(250 20% 94%); text-align: center; }
    .ml-footer p { font-size: 13px; }

    @media (prefers-reduced-motion: reduce) {
        .ml-aurora span, .ml-logo, .ml-cta, .ml-cta::after { animation: none !important; }
    }
</style>
@endpush

@section('content')
{{-- Mobile forgot-password — "Aurora". Mirrors auth/mobile/login.blade.php per CLAUDE.md split.
     Same field name + action so PasswordResetLinkController@store is unchanged. --}}
<div class="ml-screen">

    <a href="{{ route('login') }}" class="ml-back" aria-label="{{ __('auth.auth_forgot_password_back_to_login') }}">
        <i class="bi bi-arrow-left"></i>
    </a>

    {{-- Living aurora background --}}
    <div class="ml-aurora"><span class="a1"></span><span class="a2"></span><span class="a3"></span></div>
    <div class="ml-grain"></div>
    <span class="ml-spark" style="width:5px;height:5px;left:18%;bottom:44%;animation-delay:0s"></span>
    <span class="ml-spark" style="width:3px;height:3px;left:72%;bottom:50%;animation-delay:3s"></span>
    <span class="ml-spark" style="width:4px;height:4px;left:45%;bottom:40%;animation-delay:6s"></span>

    {{-- ── Hero ── --}}
    <div class="relative flex flex-col items-center justify-center text-center px-8"
         style="z-index:4; flex: 1 1 auto; min-height: 0; padding-top: calc(.75rem + env(safe-area-inset-top));">
        <div class="ml-up" style="animation-delay:.05s">
            <div class="ml-logo-wrap">
                <span class="ml-logo-halo"></span>
                <div class="ml-logo">
                    <i class="bi bi-shield-lock" style="font-size:32px; color:hsl(250 65% 55%)"></i>
                </div>
            </div>
        </div>
        <div class="ml-up ml-wordmark" style="animation-delay:.14s; margin-top:12px">TAKEONE</div>
        <h1 class="ml-up" style="animation-delay:.22s; color:#fff; font-size:25px; font-weight:800; letter-spacing:-.02em; margin-top:12px; line-height:1.15">
            {{ __('auth.auth_forgot_password_title') }}
        </h1>
        <p class="ml-up" style="animation-delay:.3s; color:rgba(255,255,255,.7); font-size:13.5px; margin-top:6px; max-width:280px">
            {{ __('auth.auth_forgot_password_subtitle') }}
        </p>
    </div>

    {{-- ── Form sheet ── --}}
    <div class="ml-sheet" style="flex: 0 0 auto;">
        <div class="ml-grab"></div>

        <form method="POST" action="{{ route('password.email') }}">
            @csrf

            {{-- Email --}}
            <div class="ml-field">
                <input id="email" type="email" name="email" value="{{ old('email') }}"
                       placeholder=" " required autocomplete="email" autofocus>
                <label for="email">{{ __('auth.auth_forgot_password_email_placeholder') }}</label>
            </div>
            @error('email')
                <p class="ml-err"><i class="bi bi-exclamation-circle" style="margin-top:1px"></i>{{ $message }}</p>
            @enderror

            {{-- CTA --}}
            <button type="submit" class="ml-cta">
                <span>{{ __('auth.auth_forgot_password_send_reset_link') }}</span><i class="bi bi-arrow-right-short" style="font-size:22px"></i>
            </button>
        </form>

        {{-- Footer: back to login --}}
        <div class="ml-footer">
            <p style="color:#6b6480; margin:0">
                <a href="{{ route('login') }}" style="font-weight:700; color:hsl(250 65% 55%)">{{ __('auth.auth_forgot_password_back_to_login') }}</a>
            </p>
        </div>
    </div>
</div>
@endsection
