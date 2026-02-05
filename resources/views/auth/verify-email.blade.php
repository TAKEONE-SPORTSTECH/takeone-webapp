@extends('layouts.app')

@section('hide-navbar')
@endsection

@section('content')
<div class="min-h-screen flex items-center justify-center relative overflow-hidden bg-gradient-to-br from-primary/70 to-emerald-300/75">
    <!-- Background pattern overlay -->
    <div class="absolute inset-0 opacity-30 pointer-events-none bg-[url('data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%20100%20100%22%3E%3Cdefs%3E%3Cpattern%20id%3D%22grain%22%20width%3D%22100%22%20height%3D%22100%22%20patternUnits%3D%22userSpaceOnUse%22%3E%3Ccircle%20cx%3D%2225%22%20cy%3D%2225%22%20r%3D%221%22%20fill%3D%22rgba(255%2C255%2C255%2C0.1)%22%2F%3E%3Ccircle%20cx%3D%2275%22%20cy%3D%2275%22%20r%3D%221%22%20fill%3D%22rgba(255%2C255%2C255%2C0.1)%22%2F%3E%3Ccircle%20cx%3D%2250%22%20cy%3D%2210%22%20r%3D%220.5%22%20fill%3D%22rgba(255%2C255%2C255%2C0.05)%22%2F%3E%3C%2Fpattern%3E%3C%2Fdefs%3E%3Crect%20width%3D%22100%22%20height%3D%22100%22%20fill%3D%22url(%23grain)%22%2F%3E%3C%2Fsvg%3E')]"></div>

    <!-- Verify email box -->
    <div class="w-[420px] max-w-[90%] relative z-10 animate-[slideIn_0.6s_ease-out]">
        <div class="bg-white/95 backdrop-blur-xl rounded-2xl shadow-[0_20px_40px_rgba(0,0,0,0.1)] border border-white/30 p-10">
            <!-- Logo -->
            <div class="text-center mb-4">
                <a href="{{ url('/') }}">
                    <img src="{{ asset('images/logo.png') }}" alt="TAKEONE" class="h-12 mx-auto">
                </a>
            </div>

            <p class="text-center text-gray-500 text-lg mb-4 tracking-tight">Verify Your Email</p>

            @if (session('resent'))
                <div class="bg-green-50 border border-green-200 text-green-700 rounded-xl px-4 py-3 mb-4 text-sm" role="alert">
                    A fresh verification link has been sent to your email address.
                </div>
            @endif

            @if (session('verified'))
                <div class="bg-green-50 border border-green-200 text-green-700 rounded-xl px-4 py-3 mb-4 text-sm" role="alert">
                    Your email has been verified! You can now <a href="{{ route('login') }}" class="text-primary hover:underline font-medium">login</a>.
                </div>
            @endif

            <p class="text-center text-gray-500 text-sm mb-6">
                We've sent a verification link to your email address. Before proceeding, please check your email for a verification link. If you did not receive the email, we will gladly send you another.
            </p>

            <form method="POST" action="{{ route('verification.send') }}">
                @csrf

                <!-- Resend Button -->
                <button type="submit"
                        class="w-full py-3 px-8 mt-2 mb-4 text-base font-semibold text-white bg-gradient-to-br from-primary to-primary/90 rounded-xl shadow-lg shadow-primary/30 transition-all duration-300 hover:-translate-y-0.5 hover:shadow-xl hover:shadow-primary/40 active:translate-y-0 cursor-pointer">
                    RESEND VERIFICATION EMAIL
                </button>
            </form>

            <!-- Logout link -->
            <p class="text-center text-sm text-gray-500">
                <a href="{{ route('logout') }}"
                   class="text-primary/80 font-medium no-underline transition-colors duration-300 hover:text-primary hover:underline"
                   onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                    Logout
                </a>

                <form id="logout-form" action="{{ route('logout') }}" method="POST" class="hidden">
                    @csrf
                </form>
            </p>
        </div>
    </div>
</div>

<style>
    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
</style>
@endsection
