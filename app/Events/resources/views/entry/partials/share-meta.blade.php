{{-- What a shared event link unfurls into — App\Events\Support\ShareCard::meta().
     Shared by the event page's layout and the invitation link's answer to a
     link reader (events::entry.share-preview). --}}
<meta property="og:title" content="{{ $share['title'] }}">
<meta property="og:description" content="{{ $share['description'] }}">
<meta property="og:image" content="{{ $share['image'] }}">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:locale" content="{{ $share['locale'] === 'ar' ? 'ar_BH' : 'en_US' }}">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $share['title'] }}">
<meta name="twitter:description" content="{{ $share['description'] }}">
<meta name="twitter:image" content="{{ $share['image'] }}">
