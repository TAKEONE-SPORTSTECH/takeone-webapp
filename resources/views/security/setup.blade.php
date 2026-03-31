@extends('layouts.app')

@section('title', 'Set Up Two-Factor Authentication')

@section('content')
<div class="tf-container">

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-foreground">Set Up Two-Factor Authentication</h1>
        <p class="text-sm text-muted-foreground">Follow the steps below to secure your account with Google Authenticator.</p>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-6">

            {{-- Step 1 --}}
            <div class="flex gap-4 mb-6">
                <div class="flex-shrink-0 w-8 h-8 rounded-full bg-primary text-white flex items-center justify-center font-semibold text-sm">1</div>
                <div class="flex-1">
                    <h6 class="font-semibold text-foreground mb-1">Install Google Authenticator</h6>
                    <p class="text-sm text-muted-foreground mb-0">
                        Download <strong>Google Authenticator</strong> on your phone from the
                        <a href="https://play.google.com/store/apps/details?id=com.google.android.apps.authenticator2" target="_blank" rel="noopener" class="text-primary">Play Store</a>
                        or
                        <a href="https://apps.apple.com/app/google-authenticator/id388497605" target="_blank" rel="noopener" class="text-primary">App Store</a>.
                    </p>
                </div>
            </div>

            <hr class="mb-6">

            {{-- Step 2 --}}
            <div class="flex gap-4 mb-6">
                <div class="flex-shrink-0 w-8 h-8 rounded-full bg-primary text-white flex items-center justify-center font-semibold text-sm">2</div>
                <div class="flex-1">
                    <h6 class="font-semibold text-foreground mb-1">Scan the QR Code</h6>
                    <p class="text-sm text-muted-foreground mb-4">Open the app, tap <strong>+</strong>, then choose <strong>Scan a QR code</strong>.</p>

                    <div class="flex flex-col sm:flex-row gap-6 items-start">
                        {{-- QR Code --}}
                        <div class="p-3 bg-white border border-border rounded-lg inline-block">
                            {!! $qrCodeSvg !!}
                        </div>

                        {{-- Manual entry --}}
                        <div>
                            <p class="text-sm text-muted-foreground mb-2">Can't scan? Enter this key manually in the app:</p>
                            <code class="block bg-gray-50 border border-border rounded px-3 py-2 text-sm font-mono tracking-widest select-all">
                                {{ $secret }}
                            </code>
                            <p class="text-xs text-muted-foreground mt-2">Select <strong>Time based</strong> when prompted.</p>
                        </div>
                    </div>
                </div>
            </div>

            <hr class="mb-6">

            {{-- Step 3 --}}
            <div class="flex gap-4">
                <div class="flex-shrink-0 w-8 h-8 rounded-full bg-primary text-white flex items-center justify-center font-semibold text-sm">3</div>
                <div class="flex-1">
                    <h6 class="font-semibold text-foreground mb-1">Confirm Your Code</h6>
                    <p class="text-sm text-muted-foreground mb-4">Enter the 6-digit code shown in the app to verify setup.</p>

                    <form action="{{ route('security.2fa.confirm') }}" method="POST">
                        @csrf
                        <div class="flex flex-col sm:flex-row gap-3 items-start">
                            <div class="flex-1 max-w-xs">
                                <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code"
                                       maxlength="6"
                                       class="tf-input text-center text-xl tracking-widest font-mono @error('code') border-red-500 @enderror"
                                       placeholder="000000" autofocus>
                                @error('code')
                                    <span class="tf-error">{{ $message }}</span>
                                @enderror
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg mr-1"></i>Verify & Enable
                            </button>
                        </div>
                    </form>

                    <div class="mt-4">
                        <a href="{{ route('security.show') }}" class="text-sm text-muted-foreground hover:text-foreground">
                            <i class="bi bi-arrow-left mr-1"></i>Cancel
                        </a>
                    </div>
                </div>
            </div>

        </div>
    </div>

</div>
@endsection
