<?php

/*
 * classifyTaekwondo() / classifyOlympicTaekwondo() — kept so every existing
 * caller (the showcase and championship seeders among them) keeps working.
 *
 * The classifiers now belong to the Taekwondo package
 * (App\Events\Sports\Taekwondo\Classifier, Sport Package Plan phase 2); these
 * only forward to it. Registered in Documentation/HOUSE-CLEANING.md: remove
 * once nothing calls the global functions.
 */
if (! function_exists('classifyTaekwondo')) {
    /** @return array{age_group: string, category: string, name: ?string, min: float, max: float}|null */
    function classifyTaekwondo(string $gender, int $age, float $weight): ?array
    {
        return \App\Events\Sports\Taekwondo\Classifier::classify($gender, $age, $weight);
    }
}

if (! function_exists('classifyOlympicTaekwondo')) {
    function classifyOlympicTaekwondo(string $gender, int $age, float $weight): array
    {
        return \App\Events\Sports\Taekwondo\Classifier::olympic($gender, $age, $weight);
    }
}
