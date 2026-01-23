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
        width: 420px;
        position: relative;
        z-index: 1;
        animation: slideIn 0.6s ease-out;
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

    .btn-register {
        background: #fff;
        border: 2px solid hsl(250 60% 70%);
        border-radius: 12px;
        padding: 0.75rem 2rem;
        font-weight: 600;
        font-size: 1rem;
        color: hsl(250 60% 70%);
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(250, 60, 70, 0.3);
        width: 100%;
        margin-top: 0.5rem;
    }

    .btn-register:hover {
        background: hsl(250 60% 70%);
        color: #fff;
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(250, 60, 70, 0.4);
    }

    .btn-register:active {
        transform: translateY(0);
    }

    .form-check-input {
        border-color: rgba(250, 60, 70, 0.3);
        background-color: rgba(255,255,255,0.8);
    }

    .form-check-input:checked {
        background-color: hsl(250 60% 70%);
        border-color: hsl(250 60% 70%);
    }

    .form-check-input:focus {
        border-color: hsl(250 60% 70%);
        box-shadow: 0 0 0 0.25rem rgba(250, 60, 70, 0.25);
    }

    .form-check-label {
        color: hsl(215 25% 50%);
        font-size: 0.9rem;
    }

    .text-muted {
        color: hsl(215 15% 60%) !important;
        font-size: 0.9rem;
    }

    .text-muted a {
        color: hsl(250 60% 60%);
        text-decoration: none;
        font-weight: 500;
        transition: color 0.3s ease;
    }

    .text-muted a:hover {
        color: hsl(250 60% 70%);
        text-decoration: underline;
    }

    .social-auth-links {
        margin-top: 1.5rem;
    }

    .social-auth-links p {
        margin: 0.5rem 0;
        color: hsl(215 15% 60%);
        font-weight: 500;
    }

    .social-auth-links .btn {
        margin-bottom: 0.5rem;
        border-radius: 12px;
        font-weight: 500;
        transition: all 0.3s ease;
    }

    .social-auth-links .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.2);
    }

    .social-auth-links .btn-primary {
        background: linear-gradient(135deg, #1877f2, #42a5f5);
        border: none;
    }

    .social-auth-links .btn-danger {
        background: linear-gradient(135deg, #db4437, #ff7043);
        border: none;
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

    .form-switch .form-check-input {
        width: 3em;
        height: 1.5em;
        border-radius: 1em;
        background-color: rgba(250, 60, 70, 0.2);
        border: none;
        transition: all 0.3s ease;
    }

    .form-switch .form-check-input:checked {
        background-color: hsl(250 60% 70%);
    }

    .form-switch .form-check-input:focus {
        box-shadow: 0 0 0 3px rgba(250, 60, 70, 0.2);
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
                <p class="login-box-msg">Forgot your password?</p>

                <form method="POST" action="{{ route('password.email') }}">
                    @csrf

                    <div class="mb-3">
                        <input id="email" type="email"
                               class="form-control @error('email') is-invalid @enderror"
                               name="email"
                               value="{{ old('email') }}"
                               placeholder="Email Address"
                               required autocomplete="email"
                               autofocus>
                        @error('email')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </div>

                    <button type="submit" class="btn btn-primary w-100 mb-4">SEND RESET LINK</button>
                </form>

                <p class="mb-1 text-center">
                    <a href="{{ route('login') }}">Back to Login</a>
                </p>
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



@stack('styles')
@stack('scripts')
@endsection
