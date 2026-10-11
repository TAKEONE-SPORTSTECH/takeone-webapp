{{-- Taekwondo's up-next board: the shared page with Taekwondo's words. --}}
@php
  // The event's own corner colours paint the lanes only when somebody chose
  // one (Mat\CornerLook) — otherwise the board keeps its designed red/blue.
  $tkdLook = $look ?? null;
  $tkdLookOn = $tkdLook && collect($tkdLook)->contains(fn ($c) => $c['customColour'] !== null);
@endphp
@include('scoreboard::shared.up-next-board', [
    'sport' => 'taekwondo',
    'ns' => 'taekwondo_messages',
    'pageTitle' => __('scoreboard::taekwondo_messages.court_page_title', ['court' => $payload['court'] ?? '', 'event' => $payload['event']['title'] ?? '']),
    'fontRoute' => 'court-display.font',
    'screenLinkView' => 'scoreboard::taekwondo.screen.partials.screen-link',
    'heartbeatMs' => 60000,
    // This mat's own corner side (App\Scoreboard\Corners\MatSides).
    'redRight' => $redRight ?? null,
    'look' => $tkdLookOn ? $tkdLook : null,
])
