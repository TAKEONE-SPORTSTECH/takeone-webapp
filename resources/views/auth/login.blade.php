@extends('layouts.app')

@section('hide-navbar')
@endsection

@section('content')
<div class="tf-auth-bg">
    <!-- Background pattern overlay -->
    <div class="tf-auth-grain bg-[url('data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%20100%20100%22%3E%3Cdefs%3E%3Cpattern%20id%3D%22grain%22%20width%3D%22100%22%20height%3D%22100%22%20patternUnits%3D%22userSpaceOnUse%22%3E%3Ccircle%20cx%3D%2225%22%20cy%3D%2225%22%20r%3D%221%22%20fill%3D%22rgba(255%2C255%2C255%2C0.1)%22%2F%3E%3Ccircle%20cx%3D%2275%22%20cy%3D%2275%22%20r%3D%221%22%20fill%3D%22rgba(255%2C255%2C255%2C0.1)%22%2F%3E%3Ccircle%20cx%3D%2250%22%20cy%3D%2210%22%20r%3D%220.5%22%20fill%3D%22rgba(255%2C255%2C255%2C0.05)%22%2F%3E%3C%2Fpattern%3E%3C%2Fdefs%3E%3Crect%20width%3D%22100%22%20height%3D%22100%22%20fill%3D%22url(%23grain)%22%2F%3E%3C%2Fsvg%3E')]"></div>

    <!-- Login box -->
    <div class="tf-auth-box">
        <div class="tf-auth-card">
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
                           class="tf-input @error('email') border-red-500 @enderror"
                           name="email"
                           value="{{ old('email') }}"
                           placeholder="Email or Phone"
                           required autocomplete="username"
                           autofocus>
                    @error('email')
                        <span class="tf-error" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>
                    @enderror
                </div>

                <!-- Password -->
                <div class="mb-4">
                    <input id="password" type="password"
                           class="tf-input @error('password') border-red-500 @enderror"
                           name="password"
                           placeholder="Password"
                           required autocomplete="current-password">
                    @error('password')
                        <span class="tf-error" role="alert">
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
                <button type="submit" class="tf-auth-btn mt-2 mb-2">
                    SIGN IN
                </button>

                <!-- Register Button -->
                <a href="{{ route('register') }}" class="tf-auth-btn-outline mt-2 mb-6">
                    REGISTER
                </a>
            </form>

            <!-- Forgot password link -->
            <p class="text-center text-sm text-gray-500">
                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" class="tf-auth-link">
                        I forgot my password
                    </a>
                @endif
            </p>
        </div>
    </div>
</div>
@endsection
