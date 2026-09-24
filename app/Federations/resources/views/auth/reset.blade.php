{{-- Choose a new password, in the federation's shell. Posts through the platform's own reset handler. --}}
@extends('federations::auth.layout')
@section('title', __('federations::messages.pw_reset_title'))
@section('card')
  <div class="card">
    @include('federations::auth.logo')
    <div class="eyebrow">{{ __('federations::messages.login_eyebrow') }}</div>
    <h1>{{ __('federations::messages.pw_reset_title') }}</h1>
    <p class="lead">{{ __('federations::messages.pw_reset_lead') }}</p>
    <form method="POST" action="{{ route('federations.password.update') }}" onsubmit="this.querySelector('.go').disabled = true">
      @csrf
      <input type="hidden" name="token" value="{{ $token }}">
      <label for="email">{{ __('federations::messages.login_email') }}</label>
      <input class="field" id="email" name="email" type="email" value="{{ old('email', $email) }}" autocomplete="username" required>
      <label for="password">{{ __('federations::messages.pw_new') }}</label>
      <input class="field" id="password" name="password" type="password" autocomplete="new-password" required autofocus>
      <label for="password_confirmation">{{ __('federations::messages.pw_confirm') }}</label>
      <input class="field" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
      @if ($errors->any())<div class="error" role="alert">{{ $errors->first() }}</div>@endif
      <button class="go" type="submit">{{ __('federations::messages.pw_save') }}</button>
    </form>
    <p class="card-foot">{{ __('federations::messages.login_need_access') }}</p>
  </div>
@endsection
