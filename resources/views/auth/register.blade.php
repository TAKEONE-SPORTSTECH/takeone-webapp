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

<div class="min-h-screen flex items-center justify-center relative overflow-hidden bg-gradient-to-br from-primary/70 to-emerald-300/75 py-8">
    <!-- Background pattern overlay -->
    <div class="absolute inset-0 opacity-30 pointer-events-none bg-[url('data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%20100%20100%22%3E%3Cdefs%3E%3Cpattern%20id%3D%22grain%22%20width%3D%22100%22%20height%3D%22100%22%20patternUnits%3D%22userSpaceOnUse%22%3E%3Ccircle%20cx%3D%2225%22%20cy%3D%2225%22%20r%3D%221%22%20fill%3D%22rgba(255%2C255%2C255%2C0.1)%22%2F%3E%3Ccircle%20cx%3D%2275%22%20cy%3D%2275%22%20r%3D%221%22%20fill%3D%22rgba(255%2C255%2C255%2C0.1)%22%2F%3E%3Ccircle%20cx%3D%2250%22%20cy%3D%2210%22%20r%3D%220.5%22%20fill%3D%22rgba(255%2C255%2C255%2C0.05)%22%2F%3E%3C%2Fpattern%3E%3C%2Fdefs%3E%3Crect%20width%3D%22100%22%20height%3D%22100%22%20fill%3D%22url(%23grain)%22%2F%3E%3C%2Fsvg%3E')]"></div>

    <!-- Register box -->
    <div class="w-[500px] max-w-[90%] relative z-10 animate-[slideIn_0.6s_ease-out]">
        <div class="bg-white/95 backdrop-blur-xl rounded-2xl shadow-[0_20px_40px_rgba(0,0,0,0.1)] border border-white/30 p-10">
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
                    <label for="full_name" class="block text-sm font-medium text-gray-600 mb-1">Full Name</label>
                    <input id="full_name" type="text"
                           class="w-full px-4 py-3 text-base border-2 border-primary/20 rounded-xl bg-white/80 shadow-inner transition-all duration-300 focus:border-primary focus:bg-white focus:ring-4 focus:ring-primary/10 focus:outline-none @error('full_name') border-red-500 @enderror"
                           name="full_name"
                           value="{{ old('full_name') }}"
                           required autocomplete="name">
                    @error('full_name')
                        <span class="text-red-500 text-sm mt-1 block" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>
                    @enderror
                </div>

                <!-- Email Address -->
                <div class="mb-4">
                    <label for="email" class="block text-sm font-medium text-gray-600 mb-1">Email Address</label>
                    <input id="email" type="email"
                           class="w-full px-4 py-3 text-base border-2 border-primary/20 rounded-xl bg-white/80 shadow-inner transition-all duration-300 focus:border-primary focus:bg-white focus:ring-4 focus:ring-primary/10 focus:outline-none @error('email') border-red-500 @enderror"
                           name="email"
                           value="{{ old('email') }}"
                           required autocomplete="email">
                    @error('email')
                        <span class="text-red-500 text-sm mt-1 block" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>
                    @enderror
                </div>

                <!-- Password -->
                <div class="mb-4">
                    <label for="password" class="block text-sm font-medium text-gray-600 mb-1">Password</label>
                    <input id="password" type="password"
                           class="w-full px-4 py-3 text-base border-2 border-primary/20 rounded-xl bg-white/80 shadow-inner transition-all duration-300 focus:border-primary focus:bg-white focus:ring-4 focus:ring-primary/10 focus:outline-none @error('password') border-red-500 @enderror"
                           name="password"
                           required autocomplete="new-password">
                    @error('password')
                        <span class="text-red-500 text-sm mt-1 block" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>
                    @enderror
                </div>

                <!-- Confirm Password -->
                <div class="mb-4">
                    <label for="password-confirm" class="block text-sm font-medium text-gray-600 mb-1">Confirm Password</label>
                    <input id="password-confirm" type="password"
                           class="w-full px-4 py-3 text-base border-2 border-primary/20 rounded-xl bg-white/80 shadow-inner transition-all duration-300 focus:border-primary focus:bg-white focus:ring-4 focus:ring-primary/10 focus:outline-none"
                           name="password_confirmation"
                           required autocomplete="new-password">
                </div>

                <!-- Mobile Number with Country Code -->
                <div class="mb-4">
                    <label for="mobile_number" class="block text-sm font-medium text-gray-600 mb-1">Mobile Number</label>
                    <x-country-code-dropdown
                        name="country_code"
                        id="country_code"
                        :value="old('country_code', '+1')"
                        :required="true"
                        :error="$errors->first('country_code')">
                        <input id="mobile_number" type="tel"
                               class="w-full px-4 py-3 text-base border-2 border-primary/20 rounded-xl bg-white/80 shadow-inner transition-all duration-300 focus:border-primary focus:bg-white focus:ring-4 focus:ring-primary/10 focus:outline-none @error('mobile_number') border-red-500 @enderror"
                               name="mobile_number"
                               value="{{ old('mobile_number') }}"
                               required autocomplete="tel">
                    </x-country-code-dropdown>
                    @error('mobile_number')
                        <span class="text-red-500 text-sm mt-1 block" role="alert">
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
                <button type="submit" id="registerButton"
                        class="w-full py-3 px-8 mt-2 text-base font-semibold text-white bg-gradient-to-br from-primary to-primary/90 rounded-xl shadow-lg shadow-primary/30 transition-all duration-300 hover:-translate-y-0.5 hover:shadow-xl hover:shadow-primary/40 active:translate-y-0 cursor-pointer">
                    REGISTER
                </button>
            </form>
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

    /* Select2 styling to match auth theme */
    .select2-container--default .select2-selection--single {
        border: 2px solid rgba(139, 92, 246, 0.2) !important;
        border-radius: 0.75rem !important;
        padding: 0.75rem 1rem !important;
        background: rgba(255,255,255,0.8) !important;
        transition: all 0.3s ease !important;
        box-shadow: inset 0 1px 3px rgba(0,0,0,0.1) !important;
        height: auto !important;
        min-height: 3rem !important;
    }

    .select2-container--default .select2-selection--single .select2-selection__rendered {
        color: #4b5563 !important;
        font-size: 1rem !important;
        line-height: 1.5 !important;
        padding: 0 !important;
    }

    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 100% !important;
        right: 10px !important;
        top: 50% !important;
        transform: translateY(-50%) !important;
    }

    .select2-container--default.select2-container--open .select2-selection--single {
        border-color: hsl(250 60% 70%) !important;
        background: #fff !important;
        box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1), inset 0 1px 3px rgba(0,0,0,0.1) !important;
    }

    .select2-dropdown {
        border: 2px solid rgba(139, 92, 246, 0.2) !important;
        border-radius: 0.75rem !important;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1) !important;
    }

    .select2-container--default .select2-results__option--highlighted[aria-selected] {
        background-color: hsl(250 60% 95%) !important;
        color: hsl(250 60% 30%) !important;
    }

    /* Flatpickr styling */
    .flatpickr-input {
        width: 100%;
        padding: 0.75rem 1rem;
        font-size: 1rem;
        border: 2px solid rgba(139, 92, 246, 0.2);
        border-radius: 0.75rem;
        background: rgba(255,255,255,0.8);
        transition: all 0.3s ease;
        box-shadow: inset 0 1px 3px rgba(0,0,0,0.1);
    }

    .flatpickr-input:focus {
        border-color: hsl(250 60% 70%);
        background: #fff;
        box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1), inset 0 1px 3px rgba(0,0,0,0.1);
        outline: none;
    }
</style>

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
