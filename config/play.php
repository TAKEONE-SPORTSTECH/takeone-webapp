<?php

return [

    /*
    |--------------------------------------------------------------------------
    | TAKEONE Play integration
    |--------------------------------------------------------------------------
    |
    | Connection details for the sibling video platform (video.takeone.bh).
    | Design and phasing: Documentation/VIDEO-INTEGRATION.md
    |
    */

    /*
     | Master switch. OFF by default, deliberately: with this false an event
     | must be indistinguishable from one running before any of this existed
     | (VIDEO-INTEGRATION.md §0). Nothing may call Play unless this is true.
     */
    'enabled' => env('PLAY_INTEGRATION_ENABLED', false),

    /*
     | The officiating timeline (event_match_events).
     |
     | INDEPENDENT of the flag above. The log is the substrate the video
     | timeline is built from, but it stands on its own as an audit trail of
     | officiating, so it runs whether or not the video integration is enabled.
     |
     | Defaults ON. It is a kill switch, not a feature flag: set
     | EVENT_MATCH_LOG=false only if the writes ever misbehave during an event.
     | Turning it off loses history for that period; it does not affect scoring
     | either way, since the recorder can never throw.
     */
    'event_log' => env('EVENT_MATCH_LOG', true),

    'url' => env('PLAY_API_URL', 'https://video.takeone.bh'),

    /*
     | The service-client token, minted on Play with:
     |   php artisan takeone:integration-token
     |
     | It authenticates takeone as a service, never as a person, and carries
     | explicit abilities (match:read, match:write). It lives in .env on this
     | side only — never in the repo, a commit message, a log line, or anything
     | rendered to a client.
     */
    'token' => env('PLAY_API_TOKEN'),

    /*
     | Seconds before a call to Play is abandoned. Kept short: an event day must
     | never stall on the video platform being slow or unreachable. The database
     | is the source of truth and a failed push is retried, never blocking.
     */
    'timeout' => (int) env('PLAY_API_TIMEOUT', 10),

    /*
     | Maximum tolerated clock difference between the two hosts, in seconds.
     |
     | Every marker position is derived from wall-clock stamps (§4), so drift
     | translates directly into highlights landing on the wrong technique. The
     | health check compares both clocks and refuses to publish beyond this,
     | rather than silently writing a corrupted timeline.
     */
    'max_clock_skew' => (int) env('PLAY_MAX_CLOCK_SKEW', 2),

];
