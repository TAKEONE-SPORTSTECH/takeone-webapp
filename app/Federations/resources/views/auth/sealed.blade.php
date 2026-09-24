{{--
    The one page a sealed federation request lands on when it cannot go where
    it asked: an error (404, 403, expired form…) or an account that runs no
    federation. Rendered in place — never a redirect — so it can never loop,
    and it offers only doors inside /federation.
--}}
@extends('federations::auth.layout')
@section('title', $title)
@section('card')
  <div class="card">
    @include('federations::auth.logo')
    <div class="eyebrow">{{ __('federations::messages.login_eyebrow') }}</div>
    <h1>{{ $title }}</h1>
    <p class="lead">{{ $message }}</p>
    @if ($signedIn)
      @if ($home)<a class="go" href="{{ route('federations.home') }}" style="display:grid;place-items:center;text-decoration:none;margin-bottom:10px">{{ __('federations::messages.sec_back') }}</a>@endif
      <form method="POST" action="{{ route('federations.logout') }}">@csrf<button class="ghost" type="submit">{{ __('federations::messages.sign_out') }}</button></form>
    @elseif ($signIn)
      <a class="go" href="{{ route('federations.login') }}" style="display:grid;place-items:center;text-decoration:none">{{ __('federations::messages.login_submit') }}</a>
    @endif
    <p class="card-foot">{{ __('federations::messages.login_need_access') }}</p>
  </div>
@endsection
