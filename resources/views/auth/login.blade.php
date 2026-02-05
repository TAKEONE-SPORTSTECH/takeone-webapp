@extends('layouts.app')

@section('hide-navbar')
@endsection

@section('content')
<div class="min-h-screen flex items-center justify-center relative overflow-hidden bg-gradient-to-br from-primary/70 to-emerald-300/75">
    <!-- Background pattern overlay -->
    <div class="absolute inset-0 opacity-30 pointer-events-none bg-[url('data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%20100%20100%22%3E%3Cdefs%3E%3Cpattern%20id%3D%22grain%22%20width%3D%22100%22%20height%3D%22100%22%20patternUnits%3D%22userSpaceOnUse%22%3E%3Ccircle%20cx%3D%2225%22%20cy%3D%2225%22%20r%3D%221%22%20fill%3D%22rgba(255%2C255%2C255%2C0.1)%22%2F%3E%3Ccircle%20cx%3D%2275%22%20cy%3D%2275%22%20r%3D%221%22%20fill%3D%22rgba(255%2C255%2C255%2C0.1)%22%2F%3E%3Ccircle%20cx%3D%2250%22%20cy%3D%2210%22%20r%3D%220.5%22%20fill%3D%22rgba(255%2C255%2C255%2C0.05)%22%2F%3E%3C%2Fpattern%3E%3C%2Fdefs%3E%3Crect%20width%3D%22100%22%20height%3D%22100%22%20fill%3D%22url(%23grain)%22%2F%3E%3C%2Fsvg%3E')]"></div>

    <!-- Login box -->
    <div class="w-[420px] max-w-[90%] relative z-10 animate-[slideIn_0.6s_ease-out]">
        <div class="bg-white/95 backdrop-blur-xl rounded-2xl shadow-[0_20px_40px_rgba(0,0,0,0.1)] border border-white/30 p-10">
            <!-- Logo -->
            <div class="text-center mb-4">
                <a href="{{ url('/') }}">
                    <img src="{{ asset('images/logo.png') }}" alt="TAKEONE" class="h-12 mx-auto">
                </a>
            </div>

            <p class="text-center text-gray-500 text-lg mb-8 tracking-tight">Sign in to start your session</p>

            <form method="POST" action="{{ route('login') }}">
                @csrf

                <!-- Email -->
                <div class="mb-4">
                    <input id="email" type="text"
                           class="w-full px-4 py-3 text-base border-2 border-primary/20 rounded-xl bg-white/80 shadow-inner transition-all duration-300 focus:border-primary focus:bg-white focus:ring-4 focus:ring-primary/10 focus:outline-none @error('email') border-red-500 @enderror"
                           name="email"
                           value="{{ old('email') }}"
                           placeholder="Email or Phone"
                           required autocomplete="username"
                           autofocus>
                    @error('email')
                        <span class="text-red-500 text-sm mt-1 block" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>
                    @enderror
                </div>

                <!-- Password -->
                <div class="mb-4">
                    <input id="password" type="password"
                           class="w-full px-4 py-3 text-base border-2 border-primary/20 rounded-xl bg-white/80 shadow-inner transition-all duration-300 focus:border-primary focus:bg-white focus:ring-4 focus:ring-primary/10 focus:outline-none @error('password') border-red-500 @enderror"
                           name="password"
                           placeholder="Password"
                           required autocomplete="current-password">
                    @error('password')
                        <span class="text-red-500 text-sm mt-1 block" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>
                    @enderror
                </div>

                <!-- Remember me -->
                <div class="flex items-center gap-2 mb-4">
                    <input type="checkbox"
                           class="w-4 h-4 rounded border-primary/30 bg-white/80 text-primary focus:ring-primary/25 focus:ring-offset-0"
                           id="remember"
                           name="remember"
                           {{ old('remember') ? 'checked' : '' }}>
                    <label class="text-gray-500 text-sm" for="remember">
                        Remember Me
                    </label>
                </div>

                <!-- Sign In Button -->
                <button type="submit"
                        class="w-full py-3 px-8 mt-2 mb-2 text-base font-semibold text-white bg-gradient-to-br from-primary to-primary/90 rounded-xl shadow-lg shadow-primary/30 transition-all duration-300 hover:-translate-y-0.5 hover:shadow-xl hover:shadow-primary/40 active:translate-y-0 cursor-pointer">
                    SIGN IN
                </button>

                <!-- Register Button -->
                <a href="{{ route('register') }}"
                   class="block w-full py-3 px-8 mt-2 mb-6 text-base font-semibold text-center text-primary bg-white border-2 border-primary rounded-xl shadow-lg shadow-primary/30 transition-all duration-300 hover:bg-primary hover:text-white hover:-translate-y-0.5 hover:shadow-xl hover:shadow-primary/40 active:translate-y-0 no-underline">
                    REGISTER
                </a>
            </form>

            <!-- Forgot password link -->
            <p class="text-center text-sm text-gray-500">
                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" class="text-primary/80 font-medium no-underline transition-colors duration-300 hover:text-primary hover:underline">
                        I forgot my password
                    </a>
                @endif
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
