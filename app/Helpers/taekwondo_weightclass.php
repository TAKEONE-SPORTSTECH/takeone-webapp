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

        $map = [
            'Kids' => [
                'male' => [
                    ['label' => '-26', 'min' => 0,  'max' => 26],
                    ['label' => '-30', 'min' => 0,  'max' => 30],
                    ['label' => '-33', 'min' => 0,  'max' => 33],
                    ['label' => '-36', 'min' => 0,  'max' => 36],
                    ['label' => '-40', 'min' => 0,  'max' => 40],
                    ['label' => '-45', 'min' => 0,  'max' => 45],
                    ['label' => '-50', 'min' => 0,  'max' => 50],
                    ['label' => '+50', 'min' => 50, 'max' => 200],
                ],
                'female' => [
                    ['label' => '-26', 'min' => 0,  'max' => 26],
                    ['label' => '-30', 'min' => 0,  'max' => 30],
                    ['label' => '-33', 'min' => 0,  'max' => 33],
                    ['label' => '-36', 'min' => 0,  'max' => 36],
                    ['label' => '-40', 'min' => 0,  'max' => 40],
                    ['label' => '-45', 'min' => 0,  'max' => 45],
                    ['label' => '-50', 'min' => 0,  'max' => 50],
                    ['label' => '+50', 'min' => 50, 'max' => 200],
                ],
            ],
            'Cadet' => [
                'male' => [
                    ['label' => '-33', 'min' => 0,  'max' => 33],
                    ['label' => '-37', 'min' => 0,  'max' => 37],
                    ['label' => '-41', 'min' => 0,  'max' => 41],
                    ['label' => '-45', 'min' => 0,  'max' => 45],
                    ['label' => '-49', 'min' => 0,  'max' => 49],
                    ['label' => '-53', 'min' => 0,  'max' => 53],
                    ['label' => '-57', 'min' => 0,  'max' => 57],
                    ['label' => '-61', 'min' => 0,  'max' => 61],
                    ['label' => '-65', 'min' => 0,  'max' => 65],
                    ['label' => '+65', 'min' => 65, 'max' => 200],
                ],
                'female' => [
                    ['label' => '-29', 'min' => 0,  'max' => 29],
                    ['label' => '-33', 'min' => 0,  'max' => 33],
                    ['label' => '-37', 'min' => 0,  'max' => 37],
                    ['label' => '-41', 'min' => 0,  'max' => 41],
                    ['label' => '-44', 'min' => 0,  'max' => 44],
                    ['label' => '-47', 'min' => 0,  'max' => 47],
                    ['label' => '-51', 'min' => 0,  'max' => 51],
                    ['label' => '-55', 'min' => 0,  'max' => 55],
                    ['label' => '-59', 'min' => 0,  'max' => 59],
                    ['label' => '+59', 'min' => 59, 'max' => 200],
                ],
            ],
            'Junior' => [
                'male' => [
                    ['label' => '-45', 'min' => 0,  'max' => 45],
                    ['label' => '-48', 'min' => 0,  'max' => 48],
                    ['label' => '-51', 'min' => 0,  'max' => 51],
                    ['label' => '-55', 'min' => 0,  'max' => 55],
                    ['label' => '-59', 'min' => 0,  'max' => 59],
                    ['label' => '-63', 'min' => 0,  'max' => 63],
                    ['label' => '-68', 'min' => 0,  'max' => 68],
                    ['label' => '-73', 'min' => 0,  'max' => 73],
                    ['label' => '-78', 'min' => 0,  'max' => 78],
                    ['label' => '+78', 'min' => 78, 'max' => 200],
                ],
                'female' => [
                    ['label' => '-42', 'min' => 0,  'max' => 42],
                    ['label' => '-44', 'min' => 0,  'max' => 44],
                    ['label' => '-46', 'min' => 0,  'max' => 46],
                    ['label' => '-49', 'min' => 0,  'max' => 49],
                    ['label' => '-52', 'min' => 0,  'max' => 52],
                    ['label' => '-55', 'min' => 0,  'max' => 55],
                    ['label' => '-59', 'min' => 0,  'max' => 59],
                    ['label' => '-63', 'min' => 0,  'max' => 63],
                    ['label' => '-68', 'min' => 0,  'max' => 68],
                    ['label' => '+68', 'min' => 68, 'max' => 200],
                ],
            ],
            'Senior' => [
                'male' => [
                    ['label' => '-54', 'min' => 0,  'max' => 54],
                    ['label' => '-58', 'min' => 0,  'max' => 58],
                    ['label' => '-63', 'min' => 0,  'max' => 63],
                    ['label' => '-68', 'min' => 0,  'max' => 68],
                    ['label' => '-74', 'min' => 0,  'max' => 74],
                    ['label' => '-80', 'min' => 0,  'max' => 80],
                    ['label' => '-87', 'min' => 0,  'max' => 87],
                    ['label' => '+87', 'min' => 87, 'max' => 200],
                ],
                'female' => [
                    ['label' => '-46', 'min' => 0,  'max' => 46],
                    ['label' => '-49', 'min' => 0,  'max' => 49],
                    ['label' => '-53', 'min' => 0,  'max' => 53],
                    ['label' => '-57', 'min' => 0,  'max' => 57],
                    ['label' => '-62', 'min' => 0,  'max' => 62],
                    ['label' => '-67', 'min' => 0,  'max' => 67],
                    ['label' => '-73', 'min' => 0,  'max' => 73],
                    ['label' => '+73', 'min' => 73, 'max' => 200],
                ],
            ],
            'Masters' => [
                'male' => [
                    ['label' => '-60', 'min' => 0,  'max' => 60],
                    ['label' => '-70', 'min' => 0,  'max' => 70],
                    ['label' => '-80', 'min' => 0,  'max' => 80],
                    ['label' => '+80', 'min' => 80, 'max' => 200],
                ],
                'female' => [
                    ['label' => '-55', 'min' => 0,  'max' => 55],
                    ['label' => '-63', 'min' => 0,  'max' => 63],
                    ['label' => '-72', 'min' => 0,  'max' => 72],
                    ['label' => '+72', 'min' => 72, 'max' => 200],
                ],
            ],
        ];

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
