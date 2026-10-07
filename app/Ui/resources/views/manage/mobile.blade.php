{{-- Manage on a phone: its own header (the Play/Manage switch, set to
     Manage) and its own bottom tabs, over the shared Manage body. --}}
@extends('layouts.app')

@section('hide-navbar', true)
@section('title', __('ui::manage.manage'))

@section('content')
@php
    $o = $current;
    $slug = ($o && $o['kind'] === 'club') ? $o['club']->slug : null;
    $tabs = $slug ? [
        [route('me.manage', ['org' => $o['key']]), 'bi-grid', __('ui::manage.overview'), true],
        [route('admin.club.members', $slug), 'bi-people', __('ui::manage.members'), false],
        [route('admin.club.packages', $slug), 'bi-calendar-week', __('ui::manage.packages'), false],
        [route('admin.club.financials', $slug), 'bi-cash-coin', __('ui::manage.finance'), false],
        [route('admin.club.details', $slug), 'bi-gear', __('ui::manage.settings'), false],
    ] : [
        [route('me.manage', ['org' => $o['key'] ?? null]), 'bi-grid', __('ui::manage.overview'), true],
    ];
@endphp
<div class="t1">
    <header class="t1-phead">
        @include('ui::shell.mode-switch', ['onFace' => '', 'manage' => true])
        <span class="t1-grow"></span>
        <a href="{{ route('messages.index') }}" class="t1-hbtn chat-link" aria-label="{{ __('nav.layouts_app_messages') }}"><i class="bi bi-chat-dots"></i></a>
    </header>
    <div style="padding:0 16px 96px">
        @include('ui::manage.partials.body')
    </div>
    <nav class="t1-tabs" aria-label="{{ __('ui::manage.manage') }}">
        @foreach($tabs as [$url, $icon, $label, $on])
            <a href="{{ $url }}" class="{{ $on ? 'is-active' : '' }}"><i class="bi {{ $icon }}"></i><span>{{ $label }}</span></a>
        @endforeach
        <a href="{{ route('me.home') }}"><i class="bi bi-person-circle"></i><span>{{ __('ui::messages.play') }}</span></a>
    </nav>
</div>
@endsection
