@extends('layouts.app')

@section('hide-navbar')
@endsection

@section('content')
<div class="tf-auth-bg">
    <!-- Background pattern overlay -->
    <div class="tf-auth-grain bg-[url('data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%20100%20100%22%3E%3Cdefs%3E%3Cpattern%20id%3D%22grain%22%20width%3D%22100%22%20height%3D%22100%22%20patternUnits%3D%22userSpaceOnUse%22%3E%3Ccircle%20cx%3D%2225%22%20cy%3D%2225%22%20r%3D%221%22%20fill%3D%22rgba(255%2C255%2C255%2C0.1)%22%2F%3E%3Ccircle%20cx%3D%2275%22%20cy%3D%2275%22%20r%3D%221%22%20fill%3D%22rgba(255%2C255%2C255%2C0.1)%22%2F%3E%3Ccircle%20cx%3D%2250%22%20cy%3D%2210%22%20r%3D%220.5%22%20fill%3D%22rgba(255%2C255%2C255%2C0.05)%22%2F%3E%3C%2Fpattern%3E%3C%2Fdefs%3E%3Crect%20width%3D%22100%22%20height%3D%22100%22%20fill%3D%22url(%23grain)%22%2F%3E%3C%2Fsvg%3E')]"></div>

    <!-- Verify email box -->
    <div class="tf-auth-box">
        <div class="tf-auth-card">

            <!-- Logo -->
            <div class="text-center mb-6">
                @if(session('club.context'))
                    @if(session('club.context.logo'))
                        <img src="{{ asset('storage/' . session('club.context.logo')) }}" alt="{{ session('club.context.name') }}" class="h-16 mx-auto rounded-xl object-contain">
                    @endif
                    <p class="text-sm text-gray-400 mt-2">One step away from joining <span class="font-semibold text-foreground">{{ session('club.context.name') }}</span></p>
                @else
                    <a href="{{ url('/') }}">
                        <img src="{{ asset('images/logo.png') }}" alt="TAKEONE" class="h-12 mx-auto">
                    </a>
                @endif
            </div>

            <!-- Email icon -->
            <div class="flex justify-center mb-5">
                <div class="w-20 h-20 rounded-full bg-primary/10 flex items-center justify-center">
                    <svg class="w-10 h-10 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                </div>
            </div>

            <h2 class="text-center text-gray-800 text-xl font-semibold mb-2 tracking-tight">Check Your Inbox</h2>
            <p class="text-center text-gray-400 text-sm mb-6 leading-relaxed">
                We sent a verification link to your email address.<br>
                Click the link in the email to activate your account.
            </p>

            <!-- Alerts -->
            @if (session('resent') || session('status') == 'verification-link-sent')
                <div class="flex items-start gap-3 bg-green-50 border border-green-200 text-green-700 rounded-xl px-4 py-3 mb-5 text-sm">
                    <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    <span>A fresh verification link has been sent to your email address.</span>
                </div>
            @endif

            @if (session('verified'))
                <div class="flex items-start gap-3 bg-green-50 border border-green-200 text-green-700 rounded-xl px-4 py-3 mb-5 text-sm">
                    <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    <span>Your email has been verified! You can now <a href="{{ route('login') }}" class="font-medium underline">log in</a>.</span>
                </div>
            @endif

            <!-- Divider with hint -->
            <div class="flex items-center gap-3 mb-5">
                <div class="flex-1 h-px bg-gray-100"></div>
                <span class="text-xs text-gray-400 whitespace-nowrap">Didn't receive the email?</span>
                <div class="flex-1 h-px bg-gray-100"></div>
            </div>

            <!-- Resend Button -->
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <button type="submit" class="tf-auth-btn mb-4">
                    RESEND VERIFICATION EMAIL
                </button>
            </form>

            <!-- Logout link -->
            <p class="text-center text-sm text-gray-400">
                Wrong account?
                <a href="{{ route('logout') }}"
                   class="tf-auth-link"
                   onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                    Sign out
                </a>
            </p>

            <form id="logout-form" action="{{ route('logout') }}" method="POST" class="hidden">
                @csrf
            </form>

        </div>
    </div>
</div>
@endsection
