<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;

echo "=== Verifying Skills Data ===\n\n";

$users = User::with('clubAffiliations.skillAcquisitions')->get();

foreach ($users->take(5) as $user) {
    $skills = $user->clubAffiliations->flatMap->skillAcquisitions;
    $totalSkills = $skills->count();
    $uniqueSkills = $skills->unique('skill_name')->count();

    echo "{$user->full_name}:\n";
    echo "  Total skill records: {$totalSkills}\n";
    echo "  Unique skills: {$uniqueSkills}\n";

    if ($totalSkills !== $uniqueSkills) {
        echo "  ⚠️  WARNING: Duplicate skills found!\n";
        $duplicates = $skills->groupBy('skill_name')->filter(fn($group) => $group->count() > 1);
        foreach ($duplicates as $skillName => $group) {
            echo "    - {$skillName}: appears {$group->count()} times\n";
        }
    } else {
        echo "  ✅ All skills are unique!\n";
    }

    echo "  Skills: " . $skills->pluck('skill_name')->unique()->implode(', ') . "\n\n";
}

echo "\n=== Summary ===\n";
echo "Total skill records: " . \App\Models\SkillAcquisition::count() . "\n";
echo "Unique skill names: " . \App\Models\SkillAcquisition::distinct('skill_name')->count('skill_name') . "\n";
echo "Total affiliations: " . \App\Models\ClubAffiliation::count() . "\n";
