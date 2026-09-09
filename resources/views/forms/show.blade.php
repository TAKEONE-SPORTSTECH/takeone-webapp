<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    {{-- This document is LIGHT by design. Say so, or a browser decides for us:
         Chrome's Auto Dark Theme and Android WebView's force-dark invert what
         they take for a light page, and Dark Reader re-paints it after first
         paint. The result is not a dark theme — this product has none — it is
         the palette inside out: a black ground behind white cards, tinted
         tiles gone navy with their icons left bright. `only light` is the
         explicit opt-out (plain `light` is not enough) and `darkreader-lock`
         is that extension's own. Both, because they answer to different
         things; an unknown meta name is ignored everywhere else. --}}
    <meta name="color-scheme" content="light">
    <meta name="darkreader-lock">
    <style>html { color-scheme: only light; }</style>

    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $form->title }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="max-w-xl mx-auto px-4 py-8">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
            @if($form->tenant)
                <div class="flex items-center gap-3 mb-4 pb-4 border-b border-gray-100">
                    @if($form->tenant->logo)
                        <img src="{{ file_url($form->tenant->logo) }}" alt="" class="w-10 h-10 rounded-lg object-cover">
                    @endif
                    <span class="text-sm font-bold text-gray-700">{{ $form->tenant->club_name }}</span>
                </div>
            @endif

            <h1 class="text-xl font-bold text-gray-900">{{ $form->title }}</h1>
            @if($form->description)
                <p class="text-sm text-gray-500 mt-1 whitespace-pre-line">{{ $form->description }}</p>
            @endif

            <div class="mt-6">
                @if($alreadyDone)
                    <div class="text-center py-10">
                        <div class="w-14 h-14 mx-auto rounded-2xl grid place-items-center bg-green-100 text-green-600"><i class="bi bi-check-lg text-2xl"></i></div>
                        <p class="text-sm font-bold text-gray-800 mt-3">{{ __('shared.forms_show_already_submitted') }}</p>
                    </div>
                @else
                    <x-dynamic-form :form="$form" />
                @endif
            </div>
        </div>
        <p class="text-center text-[11px] text-gray-400 mt-4">{{ __('shared.forms_show_powered_by') }}</p>
    </div>

    <x-toast-notification />
</body>
</html>
