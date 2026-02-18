@extends('layouts.app')

@section('hide-navbar')
@endsection

@section('content')
<!-- Flag Icons CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flag-icons@6.6.6/css/flag-icons.min.css">
<!-- Select2 CSS (for nationality dropdown) -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<!-- Flatpickr CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

<div class="tf-auth-bg-scroll">
    <!-- Background pattern overlay -->
    <div class="tf-auth-grain bg-[url('data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%20100%20100%22%3E%3Cdefs%3E%3Cpattern%20id%3D%22grain%22%20width%3D%22100%22%20height%3D%22100%22%20patternUnits%3D%22userSpaceOnUse%22%3E%3Ccircle%20cx%3D%2225%22%20cy%3D%2225%22%20r%3D%221%22%20fill%3D%22rgba(255%2C255%2C255%2C0.1)%22%2F%3E%3Ccircle%20cx%3D%2275%22%20cy%3D%2275%22%20r%3D%221%22%20fill%3D%22rgba(255%2C255%2C255%2C0.1)%22%2F%3E%3Ccircle%20cx%3D%2250%22%20cy%3D%2210%22%20r%3D%220.5%22%20fill%3D%22rgba(255%2C255%2C255%2C0.05)%22%2F%3E%3C%2Fpattern%3E%3C%2Fdefs%3E%3Crect%20width%3D%22100%22%20height%3D%22100%22%20fill%3D%22url(%23grain)%22%2F%3E%3C%2Fsvg%3E')]"></div>

    <!-- Register box -->
    <div class="tf-auth-box-lg">
        <div class="tf-auth-card">
            <!-- Logo -->
            <div class="text-center mb-4">
                <a href="{{ url('/') }}">
                    <img src="{{ asset('images/logo.png') }}" alt="TAKEONE" class="h-12 mx-auto">
                </a>
            </div>

            <p class="text-center text-gray-500 text-lg mb-8 tracking-tight">Register a new membership</p>

            <form method="POST" action="{{ route('register') }}" id="registrationForm">
                @csrf

                <!-- Full Name -->
                <div class="mb-4">
                    <label for="full_name" class="tf-label">Full Name</label>
                    <input id="full_name" type="text"
                           class="tf-input @error('full_name') border-red-500 @enderror"
                           name="full_name"
                           value="{{ old('full_name') }}"
                           required autocomplete="name">
                    @error('full_name')
                        <span class="tf-error" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>
                    @enderror
                </div>

                <!-- Email Address -->
                <div class="mb-4">
                    <label for="email" class="tf-label">Email Address</label>
                    <input id="email" type="email"
                           class="tf-input @error('email') border-red-500 @enderror"
                           name="email"
                           value="{{ old('email') }}"
                           required autocomplete="email">
                    @error('email')
                        <span class="tf-error" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>
                    @enderror
                </div>

                <!-- Password -->
                <div class="mb-4">
                    <label for="password" class="tf-label">Password</label>
                    <input id="password" type="password"
                           class="tf-input @error('password') border-red-500 @enderror"
                           name="password"
                           required autocomplete="new-password">
                    @error('password')
                        <span class="tf-error" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>
                    @enderror
                </div>

                <!-- Confirm Password -->
                <div class="mb-4">
                    <label for="password-confirm" class="tf-label">Confirm Password</label>
                    <input id="password-confirm" type="password"
                           class="tf-input"
                           name="password_confirmation"
                           required autocomplete="new-password">
                </div>

                <!-- Mobile Number with Country Code -->
                <div class="mb-4">
                    <label for="mobile_number" class="tf-label">Mobile Number</label>
                    <x-country-code-dropdown
                        name="country_code"
                        id="country_code"
                        :value="old('country_code', '+1')"
                        :required="true"
                        :error="$errors->first('country_code')">
                        <input id="mobile_number" type="tel"
                               class="w-full px-4 py-3 text-base bg-transparent focus:outline-none @error('mobile_number') border-red-500 @enderror"
                               name="mobile_number"
                               value="{{ old('mobile_number') }}"
                               required autocomplete="tel">
                    </x-country-code-dropdown>
                    @error('mobile_number')
                        <span class="tf-error" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>
                    @enderror
                </div>

                <!-- Gender -->
                <x-gender-dropdown
                    name="gender"
                    id="gender"
                    :value="old('gender')"
                    :required="true"
                    :error="$errors->first('gender')" />

                <!-- Birthdate -->
                <x-birthdate-dropdown
                    name="birthdate"
                    id="birthdate"
                    label="Birthdate"
                    :value="old('birthdate')"
                    :required="true"
                    :min-age="10"
                    :max-age="120"
                    :error="$errors->first('birthdate')" />

                <!-- Nationality -->
                <div class="mb-4">
                    <x-country-dropdown
                        name="nationality"
                        id="nationality"
                        label="Nationality"
                        :value="old('nationality')"
                        :required="true"
                        :error="$errors->first('nationality')" />
                </div>

                <!-- Register Button -->
                <button type="submit" id="registerButton" class="tf-auth-btn mt-2">
                    REGISTER
                </button>
            </form>
        </div>
    </div>
</div>

{{-- Select2/Flatpickr styles moved to app.css (Phase 6) --}}

<!-- jQuery (required for Select2) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<!-- Flatpickr JS -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        window.onerror = function(message, source, lineno, colno, error) {
            console.error('JavaScript Error:', message);
            console.error('Source:', source);
            console.error('Line:', lineno);
            console.error('Error:', error);
        };
    });

    document.getElementById('registrationForm').addEventListener('submit', function(e) {
        console.log('Form submitting...');
        console.log('Country code:', document.getElementById('country_code').value);
        console.log('Nationality:', document.getElementById('nationality').value);
        console.log('Mobile number:', document.getElementById('mobile_number').value);
        console.log('Gender:', document.getElementById('gender').value);
        console.log('Birthdate:', document.getElementById('birthdate').value);
    });
</script>

@stack('styles')
@endsection
