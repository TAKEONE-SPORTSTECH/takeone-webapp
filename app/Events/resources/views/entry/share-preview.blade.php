{{-- The invitation link's answer to a LINK READER (ShareCard::isLinkReader):
     only the preview tags. A person following the link is forwarded to the
     event page instead, carrying the invitation pass. --}}
<!doctype html>
<html lang="{{ $share['locale'] }}" dir="{{ $share['locale'] === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <title>{{ $share['title'] }}</title>
    <meta name="description" content="{{ $share['description'] }}">
    <meta name="robots" content="noindex">
    <meta property="og:type" content="website">
    @if($host)<meta property="og:site_name" content="{{ $host }}">@endif
    <meta property="og:url" content="{{ $url }}">
    @include('events::entry.partials.share-meta', ['share' => $share])
</head>
<body><a href="{{ $url }}">{{ $share['title'] }}</a></body>
</html>
