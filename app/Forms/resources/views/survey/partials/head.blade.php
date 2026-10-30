{{-- Shared <head> for the paged survey (both layouts). Light by design, same
     reasons as forms::show. Alpine is the self-hosted copy — a club hall's
     wifi is no place to depend on a CDN. --}}
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="color-scheme" content="only light">
<meta name="darkreader-lock">
<meta name="theme-color" content="#8b7cf0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="robots" content="noindex">
<title>{{ $form->title }}</title>
{{-- The phone app's WebView can start taller than the screen; without the
     guard the bottom-pinned Start/Next bar sits below the visible edge. --}}
@include('partials.viewport-guard')
@include('forms::survey.partials.style')
@include('forms::survey.partials.runtime')
<script defer src="{{ asset('vendor/alpinejs/3.17.4/alpine.min.js') }}"></script>
