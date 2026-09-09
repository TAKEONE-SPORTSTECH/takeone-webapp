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

    /*
    |--------------------------------------------------------------------------
    | The branded event surface
    |--------------------------------------------------------------------------
    |
    | An event is a place you enter, not a page inside somebody else's product.
    | With this on, every page of an event whose package opts in
    | (EventType::brandedSurface() — the karate, taekwondo and jiu-jitsu
    | tournaments) is served in the ORGANISER's brand with the platform's frame
    | taken away: no top bar, no side drawer, no bottom tabs, no platform
    | footer, and the mobile app at every width. That is what `/e/{uuid}` has
    | always done for a stranger; this extends it to `/me/events/{uuid}`, so
    | one competition has one face whichever door was used to reach it.
    |
    | Defaults ON — it is what was asked for. It is a KILL SWITCH, not a
    | feature flag: turning it off restores the member shell on those pages
    | exactly as it shipped, and changes nothing else. Nothing about
    | authorization, routing or data passes through it
    | (App\Http\Middleware\BrandEventPage grants nothing and never aborts).
    |
    | Set EVENT_BRANDED_SURFACE=false in .env, then `php artisan config:clear`.
    |
    */

    'branded_surface' => (bool) env('EVENT_BRANDED_SURFACE', true),

];
