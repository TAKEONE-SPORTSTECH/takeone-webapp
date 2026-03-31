@extends('layouts.app')

@section('hide-navbar')
@endsection

@section('content')
<div class="tf-auth-bg">
    <div class="tf-auth-grain bg-[url('data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%20100%20100%22%3E%3Cdefs%3E%3Cpattern%20id%3D%22grain%22%20width%3D%22100%22%20height%3D%22100%22%20patternUnits%3D%22userSpaceOnUse%22%3E%3Ccircle%20cx%3D%2225%22%20cy%3D%2225%22%20r%3D%221%22%20fill%3D%22rgba(255%2C255%2C255%2C0.1)%22%2F%3E%3Ccircle%20cx%3D%2275%22%20cy%3D%2275%22%20r%3D%221%22%20fill%3D%22rgba(255%2C255%2C255%2C0.1)%22%2F%3E%3C%2Fpattern%3E%3C%2Fdefs%3E%3Crect%20width%3D%22100%22%20height%3D%22100%22%20fill%3D%22url(%23grain)%22%2F%3E%3C%2Fsvg%3E')]"></div>

    <div class="tf-auth-box">
        <div class="tf-auth-card">
            <div class="text-center mb-6">
                <a href="{{ url('/') }}">
                    <img src="{{ asset('images/logo.png') }}" alt="TAKEONE" class="h-12 mx-auto">
                </a>
            </div>

            <div class="text-center mb-6">
                <div class="w-14 h-14 rounded-full bg-primary/10 flex items-center justify-center mx-auto mb-3">
                    <i class="bi bi-shield-lock text-primary" style="font-size:1.6rem;"></i>
                </div>
                <h2 class="text-xl font-semibold text-foreground">Two-Factor Authentication</h2>
                <p class="text-sm text-gray-500 mt-1">Enter the code from your authenticator app.</p>
            </div>

            @if(session('error'))
            <div class="alert alert-danger mb-4 text-sm">
                <i class="bi bi-exclamation-triangle mr-2"></i>{{ session('error') }}
            </div>
            @endif

            <form method="POST" action="{{ route('two-factor.verify') }}" x-data="{ useRecovery: false }">
                @csrf

                <div x-show="!useRecovery">
                    <div class="mb-4">
                        <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code"
                               maxlength="6"
                               class="tf-input text-center text-2xl tracking-widest font-mono @error('code') border-red-500 @enderror"
                               placeholder="000000" autofocus>
                        @error('code')
                            <span class="tf-error" role="alert"><strong>{{ $message }}</strong></span>
                        @enderror
                    </div>
                    <button type="submit" class="tf-auth-btn mb-4">
                        VERIFY
                    </button>
                    <p class="text-center text-sm text-gray-500">
                        <button type="button" class="tf-auth-link" @click="useRecovery = true">
                            Use a recovery code
                        </button>
                    </p>
                </div>

                <div x-show="useRecovery" x-cloak>
                    <div class="mb-4">
                        <input type="text" name="code" autocomplete="off"
                               class="tf-input text-center font-mono tracking-widest @error('code') border-red-500 @enderror"
                               placeholder="XXXXX-XXXXX">
                        @error('code')
                            <span class="tf-error" role="alert"><strong>{{ $message }}</strong></span>
                        @enderror
                        <p class="text-xs text-gray-400 mt-1 text-center">Enter one of your 8-character backup codes.</p>
                    </div>
                    <button type="submit" class="tf-auth-btn mb-4">
                        VERIFY
                    </button>
                    <p class="text-center text-sm text-gray-500">
                        <button type="button" class="tf-auth-link" @click="useRecovery = false">
                            Use authenticator app instead
                        </button>
                    </p>
                </div>
            </form>

            <div class="text-center mt-4">
                <a href="{{ route('login') }}" class="text-sm text-gray-400 hover:text-gray-600">
                    <i class="bi bi-arrow-left mr-1"></i>Back to login
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
