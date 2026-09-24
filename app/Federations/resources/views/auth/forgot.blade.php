{{-- Forgot password, in the federation's shell. The link it emails opens /federation/password/reset. --}}
@extends('federations::auth.layout')
@section('title', __('federations::messages.pw_forgot_title'))
@section('card')
  <div class="card">
    @include('federations::auth.logo')
    <div class="eyebrow">{{ __('federations::messages.login_eyebrow') }}</div>
    <h1>{{ __('federations::messages.pw_forgot_title') }}</h1>
    <p class="lead">{{ __('federations::messages.pw_forgot_lead') }}</p>
    @if (session('status'))<div class="ok" role="status">{{ session('status') }}</div>@endif
    <form method="POST" action="{{ route('federations.password.email') }}" onsubmit="this.querySelector('.go').disabled = true">
      @csrf
      <label for="email">{{ __('federations::messages.login_email') }}</label>
      <input class="field" id="email" name="email" type="email" value="{{ old('email') }}" placeholder="you@example.com" autocomplete="username" autofocus required>
      @if ($errors->any())<div class="error" role="alert">{{ $errors->first() }}</div>@endif
      <button class="go" type="submit">{{ __('federations::messages.pw_send_link') }}</button>
    </form>
    <div class="links" style="justify-content:center"><a href="{{ route('federations.login') }}">{{ __('federations::messages.login_back') }}</a></div>
    <p class="card-foot">{{ __('federations::messages.login_need_access') }}</p>
  </div>
@endsection
