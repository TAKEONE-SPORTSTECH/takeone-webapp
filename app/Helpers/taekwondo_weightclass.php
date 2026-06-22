<?php

if (! function_exists('classifyTaekwondo')) {
    /**
     * Classify a Taekwondo competitor by age group and weight category.
     *
     * Age groups: Kids (6–11), Cadet (12–14), Junior (15–17), Senior (18–30), Masters (31+)
     *
     * @param  string  $gender  "male" or "female"
     * @param  int     $age     Age in years
     * @param  float   $weight  Weight in kg
     * @return array{age_group: string, category: string, min: float, max: float}|null
     */
    function classifyTaekwondo(string $gender, int $age, float $weight): ?array
    {
        $gender = strtolower($gender);

        if ($age >= 6 && $age <= 11) {
            $group = 'Kids';
        } elseif ($age >= 12 && $age <= 14) {
            $group = 'Cadet';
        } elseif ($age >= 15 && $age <= 17) {
            $group = 'Junior';
        } elseif ($age >= 18 && $age <= 30) {
            $group = 'Senior';
        } elseif ($age >= 31) {
            $group = 'Masters';
        } else {
            return null; // younger than 6
        }

        $map = config('taekwondo_divisions', []);

        if (! isset($map[$group][$gender])) {
            return null;
        }

        foreach ($map[$group][$gender] as $class) {
            if ($weight >= $class['min'] && $weight <= $class['max']) {
                return [
                    'age_group' => $group,
                    'category'  => $class['label'],
                    'min'       => $class['min'],
                    'max'       => $class['max'],
                ];
            }
        }

        return null;
    }
}

if (! function_exists('classifyOlympicTaekwondo')) {
    /**
     * Olympic Games taekwondo classifier (senior only, age-checked).
     *
     * Men:   -58, -68, -80, +80
     * Women: -49, -57, -67, +67
     *
     * @param  string  $gender  "male" or "female"
     * @param  int     $age     Age in years
     * @param  float   $weight  Weight in kg
     * @return array{status: string, gender?: string, age?: int, category?: string, code?: string, min?: float, max?: float, reason?: string}
     */
    function classifyOlympicTaekwondo(string $gender, int $age, float $weight): array
    {
        $gender = strtolower($gender);

        $minAge = 17;
        $maxAge = 40;

        if ($age < $minAge) {
            return [
                'status' => 'Not Applicable',
                'reason' => 'Too young for Olympic senior division.',
            ];
        }

        if ($age > $maxAge) {
            return [
                'status' => 'Not Applicable',
                'reason' => 'Above typical Olympic senior age range.',
            ];
        }

        if (! in_array($gender, ['male', 'female'], true)) {
            return [
                'status' => 'Not Applicable',
                'reason' => 'Gender must be male or female.',
            ];
        }

        if ($gender === 'male') {
            $classes = [
                ['label' => 'Flyweight',     'code' => 'M-OLY-FLY', 'min' => 0,     'max' => 58],
                ['label' => 'Featherweight', 'code' => 'M-OLY-FEA', 'min' => 58.01, 'max' => 68],
                ['label' => 'Middleweight',  'code' => 'M-OLY-MID', 'min' => 68.01, 'max' => 80],
                ['label' => 'Heavyweight',   'code' => 'M-OLY-HVY', 'min' => 80.01, 'max' => 200],
            ];
        } else {
            $classes = [
                ['label' => 'Flyweight',     'code' => 'F-OLY-FLY', 'min' => 0,     'max' => 49],
                ['label' => 'Featherweight', 'code' => 'F-OLY-FEA', 'min' => 49.01, 'max' => 57],
                ['label' => 'Middleweight',  'code' => 'F-OLY-MID', 'min' => 57.01, 'max' => 67],
                ['label' => 'Heavyweight',   'code' => 'F-OLY-HVY', 'min' => 67.01, 'max' => 200],
            ];
        }

        foreach ($classes as $class) {
            if ($weight >= $class['min'] && $weight <= $class['max']) {
                return [
                    'status'   => 'OK',
                    'gender'   => $gender,
                    'age'      => $age,
                    'category' => $class['label'],
                    'code'     => $class['code'],
                    'min'      => $class['min'],
                    'max'      => $class['max'],
                ];
            }
        }

        return [
            'status' => 'Not Applicable',
            'reason' => 'Weight out of Olympic range for this gender.',
        ];
    }
}
