<?php

return [
    /*
     * Parties & relationships (Documentation/major-change/PLAN.md).
     *
     * `enabled` — the relationships table is written alongside the old
     * ownership/role sources (dual write). OFF: nothing writes it.
     *
     * `shadow` — Authority answers next to the old permission checks and LOGS
     * every disagreement; the old answer still decides. Turned on per module
     * only after the backfill and a clean `parties:verify`.
     *
     * Both OFF by default: nothing about the live site changes until switched.
     */
    'enabled' => (bool) env('PARTIES_ENABLED', false),
    'shadow' => (bool) env('PARTIES_SHADOW', false),
];
