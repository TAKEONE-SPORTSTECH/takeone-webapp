@extends('layouts.app')

@section('hide-navbar')
@endsection

@section('content')
<style>
    .login-page {
        background: linear-gradient(135deg, hsl(250 60% 70%) 0%, hsl(140 30% 75%) 100%);
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
        overflow: hidden;
    }

    .login-page::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="75" cy="75" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="50" cy="10" r="0.5" fill="rgba(255,255,255,0.05)"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
        opacity: 0.3;
        pointer-events: none;
    }

    .login-box {
        width: 500px;
        position: relative;
        z-index: 1;
        animation: slideIn 0.6s ease-out;
    }

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

    .login-card-body {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(20px);
        border-radius: 20px;
        box-shadow: 0 20px 40px rgba(0,0,0,0.1), 0 0 0 1px rgba(255,255,255,0.2);
        border: 1px solid rgba(255,255,255,0.3);
        padding: 2.5rem;
    }

    .login-logo {
        text-align: center;
        margin-bottom: 1rem;
    }

    .login-logo a {
        color: hsl(250 60% 40%);
        font-size: 2rem;
        font-weight: bold;
        text-decoration: none;
    }

    .login-box-msg {
        margin: 0 0 2rem 0;
        padding: 0;
        color: hsl(215 15% 50%);
        text-align: center;
        font-size: 1.1rem;
        font-weight: 400;
        letter-spacing: -0.025em;
    }

    .input-group {
        margin-bottom: 1.5rem;
        position: relative;
    }

    .form-control {
        border: 2px solid rgba(250, 60, 70, 0.2);
        border-radius: 12px;
        padding: 0.75rem 1rem;
        font-size: 1rem;
        background: rgba(255,255,255,0.8);
        transition: all 0.3s ease;
        box-shadow: inset 0 1px 3px rgba(0,0,0,0.1);
    }

    .form-control:focus {
        border-color: hsl(250 60% 70%);
        background: #fff;
        box-shadow: 0 0 0 3px rgba(250, 60, 70, 0.1), inset 0 1px 3px rgba(0,0,0,0.1);
    }

    .btn-primary {
        background: linear-gradient(135deg, hsl(250 60% 70%), hsl(250 60% 65%));
        border: none;
        border-radius: 12px;
        padding: 0.75rem 2rem;
        font-weight: 600;
        font-size: 1rem;
        color: #fff;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(250, 60, 70, 0.3);
        width: 100%;
        margin-top: 0.5rem;
    }

    .btn-primary:hover {
        background: linear-gradient(135deg, hsl(250 60% 75%), hsl(250 60% 70%));
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(250, 60, 70, 0.4);
    }

    .btn-primary:active {
        transform: translateY(0);
    }

    .flatpickr-input {
        width: 100%;
        padding: 0.75rem 1rem;
        font-size: 1rem;
        border: 2px solid rgba(250, 60, 70, 0.2);
        border-radius: 12px;
        background: rgba(255,255,255,0.8);
        transition: all 0.3s ease;
        box-shadow: inset 0 1px 3px rgba(0,0,0,0.1);
    }

    .flatpickr-input:focus {
        border-color: hsl(250 60% 70%);
        background: #fff;
        box-shadow: 0 0 0 3px rgba(250, 60, 70, 0.1), inset 0 1px 3px rgba(0,0,0,0.1);
    }

    .form-select {
        border: 2px solid rgba(250, 60, 70, 0.2);
        border-radius: 12px;
        padding: 0.75rem 1rem;
        background: rgba(255,255,255,0.8);
        transition: all 0.3s ease;
        box-shadow: inset 0 1px 3px rgba(0,0,0,0.1);
    }

    .form-select:focus {
        border-color: hsl(250 60% 70%);
        background: #fff;
        box-shadow: 0 0 0 3px rgba(250, 60, 70, 0.1), inset 0 1px 3px rgba(0,0,0,0.1);
    }

    /* Seamless phone input styling */
    .input-group {
        border: 2px solid rgba(250, 60, 70, 0.2) !important;
        border-radius: 12px !important;
        background: rgba(255,255,255,0.8) !important;
        transition: all 0.3s ease !important;
        box-shadow: inset 0 1px 3px rgba(0,0,0,0.1) !important;
        position: relative !important;
    }

    .input-group:focus-within {
        border-color: hsl(250 60% 70%) !important;
        background: #fff !important;
        box-shadow: 0 0 0 3px rgba(250, 60, 70, 0.1), inset 0 1px 3px rgba(0,0,0,0.1) !important;
    }

    .country-dropdown-btn {
        border: none !important;
        border-radius: 0 !important;
        border-right: 1px solid rgba(250, 60, 70, 0.2) !important;
        padding: 0.75rem 1rem !important;
        background: transparent !important;
        transition: all 0.3s ease !important;
        box-shadow: none !important;
        color: hsl(215 25% 35%) !important;
        font-size: 1rem !important;
        height: auto !important;
        min-height: 3rem !important;
        width: auto !important;
        flex: 0 0 auto !important;
        justify-content: flex-start !important;
    }

    .country-dropdown-btn:hover {
        background: rgba(250, 60, 70, 0.05) !important;
    }

    .country-dropdown-btn:focus {
        background: rgba(250, 60, 70, 0.05) !important;
        outline: none !important;
    }

    .input-group .form-control {
        border: none !important;
        border-radius: 0 !important;
        padding: 0.75rem 1rem !important;
        background: transparent !important;
        box-shadow: none !important;
        flex: 1 !important;
    }

    .input-group .form-control:focus {
        background: transparent !important;
        box-shadow: none !important;
    }

    .country-dropdown-btn .dropdown-toggle::after {
        margin-left: auto !important;
        border-top-color: hsl(215 25% 35%) !important;
    }

    .form-select {
        min-height: 3rem !important;
    }

    .select2-container--default .select2-selection--single {
        border: 2px solid rgba(250, 60, 70, 0.2) !important;
        border-radius: 12px !important;
        padding: 0.75rem 1rem !important;
        background: rgba(255,255,255,0.8) !important;
        transition: all 0.3s ease !important;
        box-shadow: inset 0 1px 3px rgba(0,0,0,0.1) !important;
        height: auto !important;
        min-height: 3rem !important;
    }

    .select2-container--default .select2-selection--single .select2-selection__rendered {
        color: hsl(215 25% 35%) !important;
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

    .select2-container--default .select2-selection--single .select2-selection__arrow b {
        border-color: hsl(215 25% 35%) transparent transparent transparent !important;
        border-width: 5px 5px 0 5px !important;
    }

    .select2-container--default.select2-container--open .select2-selection--single {
        border-color: hsl(250 60% 70%) !important;
        background: #fff !important;
        box-shadow: 0 0 0 3px rgba(250, 60, 70, 0.1), inset 0 1px 3px rgba(0,0,0,0.1) !important;
    }

    .select2-dropdown {
        border: 2px solid rgba(250, 60, 70, 0.2) !important;
        border-radius: 12px !important;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1) !important;
    }

    .select2-container--default .select2-results__option {
        padding: 0.5rem 1rem !important;
    }

    .select2-container--default .select2-results__option--highlighted[aria-selected] {
        background-color: hsl(250 60% 95%) !important;
        color: hsl(250 60% 30%) !important;
    }

    @media (max-width: 480px) {
        .login-box {
            width: 90%;
            margin: 1rem;
        }

        .login-card-body {
            padding: 2rem;
        }

        .login-box-msg {
            font-size: 1.3rem;
        }
    }
</style>

<!-- Flag Icons CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flag-icons@6.6.6/css/flag-icons.min.css">

<!-- Select2 CSS (for nationality dropdown) -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<!-- Flatpickr CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

<div class="login-page">
    <div class="login-box">
        <div class="card">
            <div class="card-body login-card-body">
                <div class="login-logo">
                    <a href="{{ url('/') }}">
                        <img src="{{ asset('images/logo.png') }}" alt="TAKEONE" height="50">
                    </a>
                </div>
                <!-- /.login-logo -->
                <p class="login-box-msg">Register a new membership</p>

                <form method="POST" action="{{ route('register') }}" id="registrationForm">
                    @csrf

                    <!-- Full Name -->
                    <div class="mb-3">
                        <input id="full_name" type="text"
                               class="form-control @error('full_name') is-invalid @enderror"
                               name="full_name"
                               value="{{ old('full_name') }}"
                               placeholder="Full Name"
                               required autocomplete="name">
                        @error('full_name')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </div>

                    <!-- Email Address -->
                    <div class="mb-3">
                        <input id="email" type="email"
                               class="form-control @error('email') is-invalid @enderror"
                               name="email"
                               value="{{ old('email') }}"
                               placeholder="Email Address"
                               required autocomplete="email">
                        @error('email')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </div>

                    <!-- Password -->
                    <div class="mb-3">
                        <input id="password" type="password"
                               class="form-control @error('password') is-invalid @enderror"
                               name="password"
                               placeholder="Password"
                               required autocomplete="new-password">
                        @error('password')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </div>

                    <!-- Confirm Password -->
                    <div class="mb-3">
                        <input id="password-confirm" type="password"
                               class="form-control"
                               name="password_confirmation"
                               placeholder="Confirm Password"
                               required autocomplete="new-password">
                    </div>

                    <!-- Mobile Number with Country Code -->
                    <div class="mb-3">
                        <x-country-code-dropdown
                            name="country_code"
                            id="country_code"
                            :value="old('country_code', '+1')"
                            :required="true"
                            :error="$errors->first('country_code')">
                            <input id="mobile_number" type="tel"
                                   class="form-control @error('mobile_number') is-invalid @enderror"
                                   name="mobile_number"
                                   value="{{ old('mobile_number') }}"
                                   placeholder="Mobile Number"
                                   required autocomplete="tel">
                        </x-country-code-dropdown>
                        @error('mobile_number')
                            <span class="invalid-feedback d-block" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </div>

                    <!-- Gender -->
                    <div class="mb-3">
                        <select id="gender" class="form-select @error('gender') is-invalid @enderror"
                                name="gender" required>
                            <option value="">Select Gender</option>
                            <option value="m" {{ old('gender') == 'm' ? 'selected' : '' }}>Male</option>
                            <option value="f" {{ old('gender') == 'f' ? 'selected' : '' }}>Female</option>
                        </select>
                        @error('gender')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </div>

                    <!-- Birthdate -->
                    <div class="mb-3">
                        <input id="birthdate" type="text"
                               class="flatpickr-input @error('birthdate') is-invalid @enderror"
                               name="birthdate"
                               value="{{ old('birthdate') }}"
                               placeholder="Birthdate"
                               required
                               readonly>
                        @error('birthdate')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </div>

                    <!-- Nationality -->
                    <div class="mb-3">
                        <x-country-dropdown
                            name="nationality"
                            id="nationality"
                            :value="old('nationality')"
                            :required="true"
                            :error="$errors->first('nationality')" />
                    </div>

                    <button type="submit" class="btn btn-primary" id="registerButton">REGISTER</button>
                </form>
            </div>
            <!-- /.login-card-body -->
        </div>
    </div>
    <!-- /.login-box -->
</div>

<!-- jQuery (required for Select2) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<!-- Flatpickr JS -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize Flatpickr for birthdate
        flatpickr('#birthdate', {
            dateFormat: 'Y-m-d',
            maxDate: 'today',
            yearRange: [1900, new Date().getFullYear()],
            disableMobile: true,
            showMonths: 1,
            clickOpens: true,
            onReady: function(selectedDates, dateStr, instance) {
                const calendar = instance.calendarContainer;
                calendar.style.fontSize = '14px';
            }
        });

        // Form submission handler
        $('#registrationForm').on('submit', function(e) {
            console.log('Form submitting...');
            console.log('Country code:', $('#country_code').val());
            console.log('Nationality:', $('#nationality').val());
            console.log('Mobile number:', $('#mobile_number').val());
        });

        // Error handler
        window.onerror = function(message, source, lineno, colno, error) {
            console.error('JavaScript Error:', message);
            console.error('Source:', source);
            console.error('Line:', lineno);
            console.error('Error:', error);
        };
    });
</script>

@stack('styles')
@stack('scripts')
@endsection
