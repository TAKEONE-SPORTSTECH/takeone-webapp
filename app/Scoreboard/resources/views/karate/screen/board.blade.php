{{-- Karate's up-next board: the shared page with Karate's words. --}}
@include('scoreboard::shared.up-next-board', [
    'sport' => 'karate',
    'ns' => 'karate_messages',
    'pageTitle' => __('scoreboard::karate_messages.court_title'),
    'fontRoute' => 'karate-court-display.font',
    'screenLinkView' => 'scoreboard::karate.screen.partials.screen-link',
    'heartbeatMs' => 5000,
])
