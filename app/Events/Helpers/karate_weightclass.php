<?php

/*
 * classifyKarate() — kept so every existing caller keeps working.
 *
 * The classifier now belongs to the Karate package
 * (App\Events\Sports\Karate\Classifier, Sport Package Plan phase 2); this only
 * forwards to it. Registered in Documentation/HOUSE-CLEANING.md: remove once
 * nothing calls the global function.
 */
if (! function_exists('classifyKarate')) {
    /** @return array{age_group: string, category: string, name: ?string, min: float, max: float}|null */
    function classifyKarate(string $gender, int $age, float $weight): ?array
    {
        return \App\Events\Sports\Karate\Classifier::classify($gender, $age, $weight);
    }
}
