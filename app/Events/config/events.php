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
    | (App\Events\Middleware\BrandEventPage grants nothing and never aborts).
    |
    | Set EVENT_BRANDED_SURFACE=false in .env, then `php artisan config:clear`.
    |
    */

    'branded_surface' => (bool) env('EVENT_BRANDED_SURFACE', true),

    /*
     |--------------------------------------------------------------------------
     | One event page — /me/events/{uuid} renders the public poster
     |--------------------------------------------------------------------------
     | OFF by default (RULE #1). When ON, the member address renders the SAME
     | page file as /e/{uuid} (entry/public/mobile), for every reader: a
     | stranger may open it without signing in while the organiser's public
     | switch is on, and a member gets the same page with their own flow —
     | join, pay, why-not — switched on inside it. Only for event types that
     | opted into the branded surface; the others keep their page.
     */
    'unified_page' => (bool) env('EVENT_UNIFIED_PAGE', false),

    /*
     | ONE event editor (owner, 2026-10-03; PLAN.md Phase 6): the club admin
     | panel's Add / Edit event open the shared full editor (/me/events/create,
     | /me/events/{uuid}/edit) with the club already chosen, instead of the
     | panel's own smaller form. OFF: the panel's form, exactly as before. The
     | small form is retired only after this has run (RULE #1).
     */
    'one_form' => (bool) env('EVENT_ONE_FORM', false),

    /*
     | Karate on the shared championship base (App\Events\Support\Tournament),
     | the one BJJ already runs on. OFF by default: the karate package keeps its
     | own copies until this has been verified on real events. See
     | app/Events/Sports/Karate/Tournament/SharedBase.
     */
    'karate_shared_base' => (bool) env('EVENT_KARATE_SHARED_BASE', false),
    'taekwondo_shared_base' => (bool) env('EVENT_TAEKWONDO_SHARED_BASE', false),

    /*
     | Sport championships — App\Events\Championship runs a tournament or a
     | championship in any sport with a recipe (padel, football, swimming,
     | chess…). OFF by default. It only ever claims events it created itself
     | (club_events.competition), so turning it on changes no existing event.
     */
    'sport_championships' => (bool) env('EVENT_SPORT_CHAMPIONSHIPS', false),

    /*
     |--------------------------------------------------------------------------
     | Which midnight an organiser means
     |--------------------------------------------------------------------------
     |
     | `enrollment_starts_at` / `enrollment_ends_at` are calendar DATES. Turning
     | one into an instant needs a timezone, and it must be the timezone the
     | competition is actually in — not the app's storage zone. Entries used to
     | close at 00:00 UTC, three hours after the named day had ended in Bahrain.
     |
     | Read ONLY by App\Events\Support\EntryWindow. See the note there before
     | changing it, and never conflate it with `app.timezone`.
     */
    'entry_timezone' => env('EVENT_ENTRY_TIMEZONE', 'Asia/Bahrain'),

    /*
     |--------------------------------------------------------------------------
     | One athlete, one profile
     |--------------------------------------------------------------------------
     |
     | Every entry door used to make a new person on every submit, so the same
     | athlete entering twice — another day, another phone, or entered by a
     | coach after entering themselves — became several profiles and several
     | places in the draw. With this ON (App\Events\Support\KnownPerson):
     |
     |   · the public form recognises a phone + name it already has: the right
     |     password enters them as themselves, a wrong one is told they already
     |     have a profile (never a second account), a coach's unclaimed record
     |     becomes theirs;
     |   · athletes get their own sign-in page on the event, and a one-time code
     |     from the entry desk when the password is forgotten;
     |   · the coach's desk finds the athlete across the platform first.
     |
     | OFF by default (RULE #1): every door behaves exactly as it shipped.
     | Set EVENT_ENTRY_DEDUPE=true in .env, then `php artisan config:cache`.
     */
    'entry_dedupe' => (bool) env('EVENT_ENTRY_DEDUPE', false),

];
