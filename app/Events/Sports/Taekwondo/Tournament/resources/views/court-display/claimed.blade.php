@extends('layouts.app')

@section('content')
{{-- Confirmation. The organiser is looking at the wall, so this says what the
     wall now shows rather than congratulating them. --}}
<div class="px-4 sm:px-6 lg:px-8 py-10 max-w-lg mx-auto text-center">

    <span class="w-16 h-16 rounded-2xl bg-green-50 text-green-600 flex items-center justify-center mx-auto">
        <i class="bi bi-check2 text-3xl"></i>
    </span>

    <h1 class="mt-5 text-xl font-bold text-gray-900">
        {{ __('event-taekwondo_tournament::messages.claimed_title', ['court' => $device->court]) }}
    </h1>
    <p class="mt-2 text-sm text-muted-foreground">
        {{ __('event-taekwondo_tournament::messages.claimed_sub', ['event' => $device->event->title]) }}
    </p>

    <div class="mt-6 bg-white rounded-xl shadow-sm border border-gray-100 p-4 text-left space-y-3">
        <div class="flex items-center justify-between">
            <span class="text-xs text-muted-foreground uppercase tracking-wider">{{ __('event-taekwondo_tournament::messages.claimed_court') }}</span>
            <span class="text-sm font-medium text-gray-900">{{ $device->court }}</span>
        </div>
        <div class="flex items-center justify-between">
            <span class="text-xs text-muted-foreground uppercase tracking-wider">{{ __('event-taekwondo_tournament::messages.claimed_screen') }}</span>
            <span class="text-sm font-medium text-gray-900">{{ $device->label ?: '#'.$device->id }}</span>
        </div>
    </div>

    <p class="mt-5 text-xs text-muted-foreground">
        {{ __('event-taekwondo_tournament::messages.claimed_note') }}
    </p>
</div>
@endsection
