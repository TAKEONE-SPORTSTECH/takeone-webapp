<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
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
                        <img src="{{ asset('storage/' . $form->tenant->logo) }}" alt="" class="w-10 h-10 rounded-lg object-cover">
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
                        <p class="text-sm font-bold text-gray-800 mt-3">You've already submitted this form.</p>
                    </div>
                @else
                    <x-dynamic-form :form="$form" />
                @endif
            </div>
        </div>
        <p class="text-center text-[11px] text-gray-400 mt-4">Powered by TAKEONE</p>
    </div>

    <x-toast-notification />
</body>
</html>
