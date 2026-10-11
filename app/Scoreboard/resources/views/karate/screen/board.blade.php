{{-- Karate's up-next board: the shared page with Karate's words. --}}
@include('scoreboard::shared.up-next-board', [
    'sport' => 'karate',
    'ns' => 'karate_messages',
    'pageTitle' => __('scoreboard::karate_messages.court_title'),
    'fontRoute' => 'karate-court-display.font',
    'screenLinkView' => 'scoreboard::karate.screen.partials.screen-link',
    'heartbeatMs' => 5000,
    // This mat's own corner layout (Mat\MatCorners), when the controller knows it.
    'redRight' => $redRight ?? null,
    // Kyokushin's second corner is SHIRO (white), not AO (blue).
    'secondTone' => $secondTone ?? 'blue',
    // The event's own corner names/colours (Mat\CornerLook).
    'look' => $look ?? null,
])
