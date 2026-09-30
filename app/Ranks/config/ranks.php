<?php

return [
    // Ranks & grading for activities whose sport has belts. OFF by default:
    // nothing about any club changes until this is switched on.
    'enabled' => (bool) env('RANKS_ENABLED', false),
];
