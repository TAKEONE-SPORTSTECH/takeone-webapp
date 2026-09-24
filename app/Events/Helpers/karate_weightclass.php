<?php

if (! function_exists('classifyKarate')) {
    /**
     * Classify a Karate competitor by age group and kumite weight category.
     *
     * Age groups follow WKF competition bands: Kids (under 12), Cadet (12–14),
     * Junior (16–17), U21 (18–20), Senior (18+).
     *
     * Two things differ from classifyTaekwondo() and are deliberate:
     *
     *  · 15 is a real gap in the WKF ladder — too old for cadet, too young for
     *    junior — and national bodies resolve it differently. Rather than guess,
     *    a 15-year-old is placed in Cadet, which is the conservative choice: it
     *    keeps them with the lighter, closer field instead of promoting them
     *    into bouts against 17-year-olds. Change it here if your federation
     *    rules otherwise.
     *
     *  · U21 and Senior share weights and overlap in age (a 19-year-old is
     *    eligible for both), so age alone cannot pick between them. This returns
     *    Senior for 18+ because it is the division that always exists; an event
     *    that actually runs U21 selects that division explicitly.
     *
     * @param  string  $gender  "male" or "female"
     * @param  int  $age  Age in years
     * @param  float  $weight  Weight in kg
     * @return array{age_group: string, category: string, min: float, max: float}|null
     */
    function classifyKarate(string $gender, int $age, float $weight): ?array
    {
        $gender = strtolower($gender);

        if ($age >= 6 && $age <= 11) {
            $group = 'Kids';
        } elseif ($age >= 12 && $age <= 15) {
            $group = 'Cadet';
        } elseif ($age >= 16 && $age <= 17) {
            $group = 'Junior';
        } elseif ($age >= 18) {
            $group = 'Senior';
        } else {
            return null; // younger than 6
        }

        $map = config('karate_divisions', []);

        if (! isset($map[$group][$gender])) {
            return null;
        }

        foreach ($map[$group][$gender] as $class) {
            if ($weight >= $class['min'] && $weight <= $class['max']) {
                return [
                    'age_group' => $group,
                    'category' => $class['label'],
                    'name' => $class['name'] ?? null,
                    'min' => $class['min'],
                    'max' => $class['max'],
                ];
            }
        }

        return null;
    }
}
