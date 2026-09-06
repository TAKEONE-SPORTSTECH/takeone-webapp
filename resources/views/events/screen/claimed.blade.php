@extends('layouts.app')

@section('content')
{{-- The organiser's "done". The SCREEN has already moved on by itself — it is
     polling and will navigate the moment it is told — so this page exists only
     to close the loop for the person holding the phone. --}}
<div class="px-4 sm:px-6 lg:px-8 py-10 max-w-md mx-auto text-center">
    <span class="w-16 h-16 rounded-2xl bg-green-50 text-green-600 grid place-items-center mx-auto">
        <i class="bi bi-check2-circle text-3xl"></i>
    </span>
    <h1 class="text-xl font-bold text-gray-900 mt-4">{{ __('events.screen_done_title') }}</h1>
    <p class="text-sm text-muted-foreground mt-1">{{ session('status') ?: __('events.screen_done_sub') }}</p>
    <p class="text-xs text-muted-foreground mt-6">{{ __('events.screen_done_hint') }}</p>
</div>
@endsection
