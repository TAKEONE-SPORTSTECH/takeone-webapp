<?php

use App\Models\ClubInstructor;
use App\Models\SkillAcquisition;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Unifies the two skill representations onto SkillAcquisition. The flat
 * `users.skills` array was admin-set for instructors (certifications, no
 * provenance). Each entry becomes a SkillAcquisition row owned by the user
 * (no affiliation), marked verified/club_confirm (a club had listed it),
 * attributed to the instructor's club when known. Idempotent: re-running does
 * not duplicate. `users.skills` is kept as legacy but no longer read.
 */
return new class extends Migration
{
    public function up(): void
    {
        User::query()
            ->whereNotNull('skills')
            ->select(['id', 'skills'])
            ->chunkById(200, function ($users) {
                foreach ($users as $user) {
                    $skills = is_array($user->skills) ? $user->skills : [];
                    if (empty($skills)) {
                        continue;
                    }

                    $tenantId = ClubInstructor::where('user_id', $user->id)->value('tenant_id');

                    foreach ($skills as $skill) {
                        $name = trim((string) $skill);
                        if ($name === '') {
                            continue;
                        }

                        $exists = SkillAcquisition::where('user_id', $user->id)
                            ->whereNull('club_affiliation_id')
                            ->where('skill_name', $name)
                            ->exists();
                        if ($exists) {
                            continue;
                        }

                        SkillAcquisition::create([
                            'user_id' => $user->id,
                            'club_affiliation_id' => null,
                            'skill_name' => $name,
                            'activity_name' => null,
                            'proficiency_level' => 'advanced',
                            'duration_months' => 1,
                            'icon' => 'bi-patch-check',
                            'verification_status' => SkillAcquisition::STATUS_VERIFIED,
                            'verification_method' => 'club_confirm',
                            'verified_by_tenant_id' => $tenantId,
                            'verified_at' => now(),
                        ]);
                    }
                }
            });
    }

    public function down(): void
    {
        // Remove only the rows this backfill created (certifications: no affiliation, club_confirm).
        DB::table('skill_acquisitions')
            ->whereNull('club_affiliation_id')
            ->where('verification_method', 'club_confirm')
            ->where('icon', 'bi-patch-check')
            ->delete();
    }
};
