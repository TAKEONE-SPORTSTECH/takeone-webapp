<?php

/*
|--------------------------------------------------------------------------
| Event notifications
|--------------------------------------------------------------------------
| Every knob for the "who gets told, and when" system lives here so it can be
| retuned after testing without touching code.
|
| The audience widens with the event's scope; the trigger set is declared by
| the event-type package (EventType::notificationSchedule()).
*/

return [

    // Master switch — off disables every event notification, announcements and
    // reminders alike. Useful while load-testing a wide-scope announcement.
    'enabled' => env('EVENT_NOTIFICATIONS_ENABLED', true),

    /*
    | Fan-out safety. A nationwide announcement can address tens of thousands of
    | members; delivery is queued and chunked, and hard-capped so one event can
    | never flood the notifications table or the MQTT broker. Anything dropped
    | is RECORDED (event_notifications_sent.skipped) and logged — never silent.
    */
    'max_recipients' => (int) env('EVENT_NOTIFICATIONS_MAX_RECIPIENTS', 5000),
    'chunk' => 500,

    // Scopes that reach beyond the host club. Restricted so an ordinary member
    // cannot address a whole country: the club must hold this permission.
    'broadcast_scopes' => ['inter_club', 'nationwide', 'regional', 'worldwide'],

    // Radius (km) for an `inter_club` / "open" event — clubs within range of the host.
    'radius_km' => (int) env('EVENT_NOTIFICATIONS_RADIUS_KM', 50),

    /*
    | Timing. Lead times are in days; the morning reminder is an hour of the
    | day in the HOST CLUB's timezone, never the server's.
    */
    'enrolment_closing_lead_days' => 1,
    'event_day_reminder_hour' => 7,

    /*
    | Calling athletes to the mat: bouts-ahead => what kind of push.
    | Deliberately only two. A third makes athletes silence the app, and then
    | the one that matters ("report NOW") arrives muted.
    */
    'call_thresholds' => [
        6 => 'warmup',      // start warming up
        2 => 'call_room',   // report to the call room
    ],

    /*
    | Which neighbours a `regional` announcement may reach when the organiser
    | has not picked countries explicitly. ISO-3166 alpha-2, lower-cased to match
    | tenants.country handling elsewhere in the app.
    */
    'neighbours' => [
        'bh' => ['sa', 'qa', 'ae', 'kw', 'om'],
        'sa' => ['bh', 'qa', 'ae', 'kw', 'om', 'jo', 'iq', 'ye'],
        'qa' => ['bh', 'sa', 'ae'],
        'ae' => ['sa', 'om', 'qa', 'bh'],
        'kw' => ['sa', 'iq', 'bh'],
        'om' => ['ae', 'sa', 'ye'],
    ],
];
