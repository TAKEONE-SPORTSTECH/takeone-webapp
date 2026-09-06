<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Feature flags
    |--------------------------------------------------------------------------
    |
    | Every flag here defaults OFF. A flag guards a NEW code path that runs
    | ALONGSIDE the working one — never a replacement for it. Turning a flag off
    | must always restore the previously shipped behaviour exactly.
    |
    */

    /*
     * Phase M1 — render /me/schedule with the React island
     * (resources/js/islands/schedule.jsx) instead of the legacy inline
     * `partials/schedule-board-script` renderer.
     *
     * OFF  → the legacy include renders the board (unchanged, still the default)
     * ON   → the island mounts into #schedule-island and the legacy include is
     *        not rendered at all (only one of the two ever runs)
     *
     * Set REACT_SCHEDULE=true in .env, then `php artisan config:clear`.
     */
    'react_schedule' => (bool) env('REACT_SCHEDULE', false),

    /*
     * Phase M2 — render the athlete's "my entry" panel (/e/{uuid}/my-entry)
     * with the React island (resources/js/islands/entry.jsx) instead of the
     * Blade + Alpine panel.
     *
     * OFF  → the Blade panel renders (the default, and what every entrant gets)
     * ON   → the island mounts into #entry-island and the Blade panel is not
     *        rendered at all (only one of the two ever runs)
     *
     * Set REACT_ENTRY=true in .env, then `php artisan config:clear`.
     */
    'react_entry' => (bool) env('REACT_ENTRY', false),

    /*
     * Phase M3 — render the Brazilian Jiu-Jitsu MAT SCREEN and SCORING CONSOLE
     * with React islands (resources/js/islands/scoreboard-{board,console}.jsx)
     * instead of the hand-written renderers inside the Blade documents.
     *
     * OFF  → the Blade documents render exactly as they do today (the default)
     * ON   → the React shells are served instead; the Blade originals are not
     *        rendered at all (only one of the two ever runs)
     *
     * Both paths draw the SAME design from the SAME stylesheet partials, post
     * to the same endpoints and answer to the same `window.CourtBoard`
     * contract, so the live link (partials/screen-link) is untouched by the
     * choice and a mat can be switched back mid-event by turning this off.
     *
     * Set REACT_SCOREBOARD=true in .env, then `php artisan config:clear`.
     */
    'react_scoreboard' => (bool) env('REACT_SCOREBOARD', false),

];
