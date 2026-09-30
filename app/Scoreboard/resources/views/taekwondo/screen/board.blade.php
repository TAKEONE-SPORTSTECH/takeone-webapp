{{-- Taekwondo's up-next board: the shared page with Taekwondo's words. --}}
@include('scoreboard::shared.up-next-board', [
    'sport' => 'taekwondo',
    'ns' => 'taekwondo_messages',
    'pageTitle' => __('scoreboard::taekwondo_messages.court_page_title', ['court' => $payload['court'] ?? '', 'event' => $payload['event']['title'] ?? '']),
    'fontRoute' => 'court-display.font',
    'screenLinkView' => 'scoreboard::taekwondo.screen.partials.screen-link',
    'heartbeatMs' => 60000,
])
