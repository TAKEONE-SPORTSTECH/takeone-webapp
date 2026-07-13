@extends('layouts.app')

@section('title', __('security.security_setup_title'))

@section('content')
<div class="tf-container">

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-foreground">{{ __('security.security_setup_title') }}</h1>
        <p class="text-sm text-muted-foreground">{{ __('security.security_setup_subtitle') }}</p>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-6">

            {{-- Step 1 --}}
            <div class="flex gap-4 mb-6">
                <div class="flex-shrink-0 w-8 h-8 rounded-full bg-primary text-white flex items-center justify-center font-semibold text-sm">1</div>
                <div class="flex-1">
                    <h6 class="font-semibold text-foreground mb-1">{{ __('security.security_setup_step1_heading') }}</h6>
                    <p class="text-sm text-muted-foreground mb-0">
                        {{ __('security.security_setup_step1_download') }} <strong>{{ __('security.security_setup_step1_app_name') }}</strong> {{ __('security.security_setup_step1_from') }}
                        <a href="https://play.google.com/store/apps/details?id=com.google.android.apps.authenticator2" target="_blank" rel="noopener" class="text-primary">{{ __('security.security_setup_step1_play_store') }}</a>
                        {{ __('security.security_setup_step1_or') }}
                        <a href="https://apps.apple.com/app/google-authenticator/id388497605" target="_blank" rel="noopener" class="text-primary">{{ __('security.security_setup_step1_app_store') }}</a>.
                    </p>
                </div>
            </div>

            <hr class="mb-6">

            {{-- Step 2 --}}
            <div class="flex gap-4 mb-6">
                <div class="flex-shrink-0 w-8 h-8 rounded-full bg-primary text-white flex items-center justify-center font-semibold text-sm">2</div>
                <div class="flex-1">
                    <h6 class="font-semibold text-foreground mb-1">{{ __('security.security_setup_step2_heading') }}</h6>
                    <p class="text-sm text-muted-foreground mb-4">{{ __('security.security_setup_step2_open') }} <strong>+</strong>{{ __('security.security_setup_step2_then_choose') }} <strong>{{ __('security.security_setup_step2_scan_option') }}</strong>.</p>

                    <div class="flex flex-col sm:flex-row gap-6 items-start">
                        {{-- QR Code --}}
                        <div class="p-3 bg-white border border-border rounded-lg inline-block">
                            {!! $qrCodeSvg !!}
                        </div>

                        {{-- Manual entry --}}
                        <div>
                            <p class="text-sm text-muted-foreground mb-2">{{ __('security.security_setup_manual_entry') }}</p>
                            <code class="block bg-gray-50 border border-border rounded px-3 py-2 text-sm font-mono tracking-widest select-all">
                                {{ $secret }}
                            </code>
                            <p class="text-xs text-muted-foreground mt-2">{{ __('security.security_setup_time_based_prefix') }} <strong>{{ __('security.security_setup_time_based') }}</strong> {{ __('security.security_setup_time_based_suffix') }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <hr class="mb-6">

            {{-- Step 3 --}}
            <div class="flex gap-4">
                <div class="flex-shrink-0 w-8 h-8 rounded-full bg-primary text-white flex items-center justify-center font-semibold text-sm">3</div>
                <div class="flex-1">
                    <h6 class="font-semibold text-foreground mb-1">{{ __('security.security_setup_step3_heading') }}</h6>
                    <p class="text-sm text-muted-foreground mb-4">{{ __('security.security_setup_step3_subtitle') }}</p>

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
                                <i class="bi bi-check-lg me-1"></i>{{ __('security.security_setup_verify_button') }}
                            </button>
                        </div>
                    </form>

                    <div class="mt-4">
                        <a href="{{ route('security.show') }}" class="text-sm text-muted-foreground hover:text-foreground">
                            <i class="bi bi-arrow-left me-1"></i>{{ __('shared.cancel') }}
                        </a>
                    </div>
                </div>
            </div>

        </div>
    </div>

</div>
@endsection
