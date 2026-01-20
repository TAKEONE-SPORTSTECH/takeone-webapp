@extends('layouts.app')

@section('content')
<style>
    .register-container {
        min-height: calc(100vh - 72px);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 2rem 0;
    }

    .flatpickr-input {
        width: 100%;
        padding: 0.375rem 0.75rem;
        font-size: 1rem;
        line-height: 1.5;
        color: #212529;
        background-color: #fff;
        border: 1px solid #ced4da;
        border-radius: 0.375rem;
    }

    .flatpickr-input:focus {
        border-color: #86b7fe;
        outline: 0;
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
    }
</style>

<!-- Flag Icons CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flag-icons@6.6.6/css/flag-icons.min.css">

<!-- Select2 CSS (for nationality dropdown) -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<!-- Flatpickr CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

<div class="container register-container">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card shadow">
                <div class="card-header bg-white py-3">
                    <h3 class="text-center mb-0 fw-bold">Register</h3>
                </div>
                <div class="card-body p-4">
                    <form method="POST" action="{{ route('register') }}" id="registrationForm">
                        @csrf

                        <div class="row">
                            <!-- Left Column -->
                            <div class="col-md-6">
                                <!-- Full Name -->
                                <div class="mb-3">
                                    <label for="full_name" class="form-label">Full Name</label>
                                    <input id="full_name" type="text"
                                           class="form-control @error('full_name') is-invalid @enderror"
                                           name="full_name"
                                           value="{{ old('full_name') }}"
                                           required autocomplete="name">
                                    @error('full_name')
                                        <span class="invalid-feedback" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>

                                <!-- Email Address -->
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email Address</label>
                                    <input id="email" type="email"
                                           class="form-control @error('email') is-invalid @enderror"
                                           name="email"
                                           value="{{ old('email') }}"
                                           required autocomplete="email">
                                    @error('email')
                                        <span class="invalid-feedback" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>

                                <!-- Password -->
                                <div class="mb-3">
                                    <label for="password" class="form-label">Password</label>
                                    <input id="password" type="password"
                                           class="form-control @error('password') is-invalid @enderror"
                                           name="password"
                                           required autocomplete="new-password">
                                    @error('password')
                                        <span class="invalid-feedback" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>

                                <!-- Confirm Password -->
                                <div class="mb-3">
                                    <label for="password-confirm" class="form-label">Confirm Password</label>
                                    <input id="password-confirm" type="password"
                                           class="form-control"
                                           name="password_confirmation"
                                           required autocomplete="new-password">
                                </div>
                            </div>

                            <!-- Right Column -->
                            <div class="col-md-6">
                                <!-- Mobile Number with Country Code -->
                                <div class="mb-3">
                                    <label for="mobile_number" class="form-label">Mobile Number</label>
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
                                               required autocomplete="tel"
                                               placeholder="Phone number">
                                    </x-country-code-dropdown>
                                    @error('mobile_number')
                                        <span class="invalid-feedback d-block" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>

                                <!-- Gender -->
                                <div class="mb-3">
                                    <label for="gender" class="form-label">Gender</label>
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
                                    <label for="birthdate" class="form-label">Birthdate</label>
                                    <input id="birthdate" type="text"
                                           class="flatpickr-input @error('birthdate') is-invalid @enderror"
                                           name="birthdate"
                                           value="{{ old('birthdate') }}"
                                           required
                                           placeholder="Select birthdate"
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
                            </div>
                        </div>

                        <!-- Register Button -->
                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-primary btn-lg" id="registerButton">
                                Register
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- jQuery (required for Select2) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<!-- Flatpickr JS -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

@stack('styles')
@stack('scripts')

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
@endsection
