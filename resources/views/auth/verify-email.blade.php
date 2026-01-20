@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center align-items-center" style="min-height: 80vh;">
        <div class="col-md-6 col-lg-4">
            <div class="card shadow">
                <div class="card-body p-4">
                    <div class="text-center mb-4">
                        <h3 class="fw-bold">Verify Your Email</h3>
                        <p class="text-muted">We've sent a verification link to your email address.</p>
                    </div>

                    @if (session('resent'))
                        <div class="alert alert-success" role="alert">
                            A fresh verification link has been sent to your email address.
                        </div>
                    @endif

                    @if (session('verified'))
                        <div class="alert alert-success" role="alert">
                            Your email has been verified! You can now <a href="{{ route('login') }}">login</a>.
                        </div>
                    @endif

                    <p class="text-center mb-4">
                        Before proceeding, please check your email for a verification link.
                        If you did not receive the email, we will gladly send you another.
                    </p>

                    <form method="POST" action="{{ route('verification.send') }}">
                        @csrf

                        <div class="d-grid mb-3">
                            <button type="submit" class="btn btn-primary">
                                Resend Verification Email
                            </button>
                        </div>
                    </form>

                    <div class="text-center">
                        <a class="text-decoration-none" href="{{ route('logout') }}" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                            Logout
                        </a>

                        <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                            @csrf
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
