<?php

return [

    /*
    |--------------------------------------------------------------------------
    | The officiating log (event_match_events)
    |--------------------------------------------------------------------------
    |
    | Every scoring decision a mat console makes, with the wall clock it was made
    | at. This is the substrate two other things are built from — a bout's video
    | highlights bar (App\Media\BoutTimeline) and the audit trail of how a bout
    | was actually officiated — but it stands on its own and runs regardless of
    | either.
    |
    | It lived in config/play.php until the video-platform integration was
    | removed, which was always the wrong home: the log is ours and has nothing
    | to do with anybody else's platform.
    |
    | Defaults ON. It is a kill switch, not a feature flag: set
    | EVENT_MATCH_LOG=false only if the writes ever misbehave during an event.
    | Turning it off loses history for that period; it does not affect scoring
    | either way, since the recorder can never throw.
    |
    */

    'match_log' => env('EVENT_MATCH_LOG', true),

];
