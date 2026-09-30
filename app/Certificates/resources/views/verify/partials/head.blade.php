{{-- The <head> both verification documents share. Standalone (no app shell): the page is public. --}}
<meta charset="utf-8">
{{-- LIGHT by design; say so, or force-dark and Dark Reader invert it (see layouts/tailwind). --}}
<meta name="color-scheme" content="light">
<meta name="darkreader-lock">
<style>html { color-scheme: only light; }</style>
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<title>{{ __('cert::messages.verify_page_title') }} · {{ config('app.name', 'TakeOne') }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
@vite(['resources/css/app.css'])
