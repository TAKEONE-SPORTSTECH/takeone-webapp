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

];
