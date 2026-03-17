@extends('layouts.app')

@section('hide-navbar')
@endsection

@section('content')
<div class="tf-auth-bg">
    <!-- Background pattern overlay -->
    <div class="tf-auth-grain bg-[url('data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%20100%20100%22%3E%3Cdefs%3E%3Cpattern%20id%3D%22grain%22%20width%3D%22100%22%20height%3D%22100%22%20patternUnits%3D%22userSpaceOnUse%22%3E%3Ccircle%20cx%3D%2225%22%20cy%3D%2225%22%20r%3D%221%22%20fill%3D%22rgba(255%2C255%2C255%2C0.1)%22%2F%3E%3Ccircle%20cx%3D%2275%22%20cy%3D%2275%22%20r%3D%221%22%20fill%3D%22rgba(255%2C255%2C255%2C0.1)%22%2F%3E%3Ccircle%20cx%3D%2250%22%20cy%3D%2210%22%20r%3D%220.5%22%20fill%3D%22rgba(255%2C255%2C255%2C0.05)%22%2F%3E%3C%2Fpattern%3E%3C%2Fdefs%3E%3Crect%20width%3D%22100%22%20height%3D%22100%22%20fill%3D%22url(%23grain)%22%2F%3E%3C%2Fsvg%3E')]"></div>

    <div class="tf-auth-box">
        <div class="tf-auth-card">

            <!-- Logo -->
            <div class="text-center mb-6">
                <a href="{{ url('/') }}">
                    <img src="{{ asset('images/logo.png') }}" alt="TAKEONE" class="h-12 mx-auto">
                </a>
            </div>

            @if (session('status'))
                {{-- Confirmation state --}}
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
                    We sent a password reset link to your email.<br>
                    Click the link to choose a new password.
                </p>

                <div class="flex items-center gap-3 mb-5">
                    <div class="flex-1 h-px bg-gray-100"></div>
                    <span class="text-xs text-gray-400 whitespace-nowrap">Didn't receive the email?</span>
                    <div class="flex-1 h-px bg-gray-100"></div>
                </div>

                <form method="POST" action="{{ route('password.email') }}">
                    @csrf
                    <input type="hidden" name="email" value="{{ old('email') }}">
                    <button type="submit" class="tf-auth-btn mb-4">
                        RESEND RESET LINK
                    </button>
                </form>

                <p class="text-center text-sm text-gray-400">
                    <a href="{{ route('login') }}" class="tf-auth-link">Back to Login</a>
                </p>

            @else
                {{-- Form state --}}
                <p class="text-center text-gray-500 text-lg mb-8 tracking-tight">Forgot your password?</p>

                <form method="POST" action="{{ route('password.email') }}">
                    @csrf

                    <div class="mb-4">
                        <input id="email" type="email"
                               class="tf-input @error('email') border-red-500 @enderror"
                               name="email"
                               value="{{ old('email') }}"
                               placeholder="Email Address"
                               required autocomplete="email"
                               autofocus>
                        @error('email')
                            <span class="tf-error" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </div>

                    <button type="submit" class="tf-auth-btn mt-2 mb-6">
                        SEND RESET LINK
                    </button>
                </form>

                <p class="text-center text-sm text-gray-500">
                    <a href="{{ route('login') }}" class="tf-auth-link">Back to Login</a>
                </p>

            @endif

        </div>
    </div>
</div>
@endsection
