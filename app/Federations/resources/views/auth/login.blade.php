{{--
    The federation's own sign-in — the designed prototype's "Staff sign in"
    card (Events Dashboard.dc.html), rebuilt as a real form. Same navy ground,
    gold-edged card and Poppins as the dashboard behind it, so federation
    staff know which door they are at. The prototype's demo-accounts box and
    its "keep me signed in" switch are gone: there are no demo accounts, and
    the platform always keeps a session signed in until the user logs out.

    Standalone on purpose (no app layout): the dashboard is its own product.
--}}
@extends('federations::auth.layout')

@section('title', __('federations::messages.login_title'))

@php
    $err = $errors->first('email') ?: $errors->first('code') ?: session('error') ?: session('warning');
    $status = session('status');
@endphp

@section('card')
  <div class="card">
    @include('federations::auth.logo')
    <div class="eyebrow">{{ __('federations::messages.login_eyebrow') }}</div>

    @if ($status)<div class="ok" role="status">{{ $status }}</div>@endif
    @if ($step === 'otp')
      <h1>{{ __('federations::messages.login_otp_title') }}</h1>
      <p class="lead">{{ __('federations::messages.login_otp_lead') }}</p>
      <form method="POST" action="{{ route('two-factor.verify') }}" onsubmit="this.querySelector('.go').disabled = true">
        @csrf
        <input class="field otp" name="code" inputmode="numeric" maxlength="11" placeholder="000000"
               autocomplete="one-time-code" autofocus required aria-label="{{ __('federations::messages.login_otp_title') }}">
        @if ($err)<div class="error" role="alert">{{ $err }}</div>@endif
        <button class="go" type="submit">{{ __('federations::messages.login_verify') }}</button>
      </form>
      <div class="links" style="justify-content:center">
        <a href="{{ route('federations.login') }}">{{ __('federations::messages.login_back') }}</a>
      </div>
    @else
      <h1>{{ __('federations::messages.login_heading') }}</h1>
      <p class="lead">{{ __('federations::messages.login_lead') }}</p>
      <form method="POST" action="{{ route('federations.login.submit') }}" onsubmit="this.querySelector('.go').disabled = true">
        @csrf
        <label for="email">{{ __('federations::messages.login_email') }}</label>
        <input class="field" id="email" name="email" type="text" inputmode="email" value="{{ old('email', session('unverified_email')) }}"
               placeholder="you@example.com" autocomplete="username" autofocus required>
        <label for="password">{{ __('federations::messages.login_password') }}</label>
        <div class="pw">
          <input class="field" id="password" name="password" type="password" placeholder="••••••••" autocomplete="current-password" required>
          <button type="button" aria-controls="password"
                  onclick="var i=document.getElementById('password'),s=i.type==='password';i.type=s?'text':'password';this.textContent=s?@js(__('federations::messages.login_hide')):@js(__('federations::messages.login_show'));">{{ __('federations::messages.login_show') }}</button>
        </div>
        @if ($err)<div class="error" role="alert">{{ $err }}</div>@endif
        <button class="go" type="submit">{{ __('federations::messages.login_submit') }}</button>
      </form>
      <div class="links">
        <a href="{{ route('federations.password.request') }}">{{ __('federations::messages.login_forgot') }}</a>
      </div>
    @endif
    <p class="card-foot">{{ __('federations::messages.login_need_access') }}</p>
  </div>

@endsection
