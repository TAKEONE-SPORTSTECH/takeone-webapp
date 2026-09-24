{{--
    Security & 2FA, in the federation's shell (App\Members\Support\TwoFactor —
    the same secret the sign-in challenge checks). $state: on | off | setup | codes.
--}}
@extends('federations::auth.layout')
@section('title', __('federations::messages.sec_title'))
@section('brand', $federation->name)
@section('card')
  <div class="card">
    <div class="eyebrow">{{ __('federations::messages.sec_title') }}</div>
    <h1>{{ __('federations::messages.sec_heading') }}</h1>
    <p class="lead">{{ $user->email }}</p>
    @if (session('status'))<div class="ok" role="status">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="error" role="alert">{{ $errors->first() }}</div>@endif

    @if ($state === 'codes')
      <p class="sub">{{ __('federations::messages.sec_codes') }}</p>
      <p class="muted" style="margin:0 0 10px">{{ __('federations::messages.sec_codes_lead') }}</p>
      <div class="codes">@foreach ($codes as $c)<div>{{ $c }}</div>@endforeach</div>
      <a class="go" href="{{ route('federations.security') }}" style="display:grid;place-items:center;text-decoration:none">{{ __('federations::messages.sec_codes_saved') }}</a>

    @elseif ($state === 'setup')
      <p class="sub">{{ __('federations::messages.sec_setup') }}</p>
      <p class="muted" style="margin:0 0 12px">{{ __('federations::messages.sec_setup_lead') }}</p>
      <div class="qr">{!! $setup['qr'] !!}</div>
      <div class="secret">{{ trim(chunk_split($setup['secret'], 4, ' ')) }}</div>
      <form method="POST" action="{{ route('federations.security.confirm') }}" onsubmit="this.querySelector('.go').disabled = true">
        @csrf
        <input class="field otp" name="code" inputmode="numeric" maxlength="6" placeholder="000000" autocomplete="one-time-code" autofocus required>
        <button class="go" type="submit">{{ __('federations::messages.sec_turn_on') }}</button>
      </form>

    @elseif ($state === 'on')
      <div class="status on"><i></i>{{ __('federations::messages.sec_is_on') }}</div>
      <form method="POST" action="{{ route('federations.security.codes') }}" style="margin-bottom:18px">
        @csrf
        <p class="sub">{{ __('federations::messages.sec_new_codes') }}</p>
        <input class="field otp" name="code" inputmode="numeric" maxlength="6" placeholder="000000" autocomplete="one-time-code" required>
        <button class="ghost" type="submit">{{ __('federations::messages.sec_new_codes') }}</button>
      </form>
      <form method="POST" action="{{ route('federations.security.disable') }}">
        @csrf
        <p class="sub danger">{{ __('federations::messages.sec_turn_off') }}</p>
        <input class="field" name="code" maxlength="11" placeholder="{{ __('federations::messages.sec_code_or_backup') }}" autocomplete="one-time-code" required>
        <button class="ghost danger" type="submit">{{ __('federations::messages.sec_turn_off') }}</button>
      </form>

    @else
      <div class="status off"><i></i>{{ __('federations::messages.sec_is_off') }}</div>
      <p class="muted" style="margin:0 0 16px">{{ __('federations::messages.sec_off_lead') }}</p>
      <form method="POST" action="{{ route('federations.security.setup') }}">
        @csrf
        <button class="go" type="submit">{{ __('federations::messages.sec_start') }}</button>
      </form>
    @endif

    <div class="links" style="justify-content:space-between">
      <a href="{{ route('federations.home') }}">{{ __('federations::messages.sec_back') }}</a>
      <form method="POST" action="{{ route('federations.logout') }}" class="inline">@csrf<button type="submit">{{ __('federations::messages.sign_out') }}</button></form>
    </div>
  </div>
@endsection
