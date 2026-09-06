<?php

namespace Tests\Feature;

use App\Clubs\Models\ClubInstructor;
use App\Members\Models\MemberWorkHistory;
use App\Support\TrainerExperience;
use Tests\TestCase;

/**
 * A trainer's experience is calculated live: prior coaching work-history PLUS their
 * platform instructor tenure (added → now), so it grows on its own over time.
 */
class TrainerExperienceTest extends TestCase
{
    public function test_platform_tenure_accumulates_live(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner);
        $coach = $this->createUser();

        $ci = ClubInstructor::create([
            'tenant_id' => $club->id, 'user_id' => $coach->id, 'role' => 'Coach',
            'staff_type' => 'instructor', 'is_active' => true, 'sort_order' => 0,
        ]);
        // Added 400 days ago → ~1 year 1 month.
        $ci->created_at = now()->subDays(400);
        $ci->save();

        $label = TrainerExperience::label($coach->fresh());
        $this->assertNotNull($label);
        $this->assertStringContainsString('1 year', $label);
    }

    public function test_a_trainer_added_today_has_no_experience_yet(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner);
        $coach = $this->createUser();

        ClubInstructor::create([
            'tenant_id' => $club->id, 'user_id' => $coach->id, 'role' => 'Coach',
            'staff_type' => 'instructor', 'is_active' => true, 'sort_order' => 0,
        ]);

        // Under a day of tenure → null, so the profile shows its "New" state.
        $this->assertNull(TrainerExperience::label($coach->fresh()));
    }

    public function test_prior_coaching_history_counts_toward_experience(): void
    {
        $coach = $this->createUser();
        MemberWorkHistory::create([
            'user_id' => $coach->id, 'title' => 'Boxing Coach', 'organization' => 'Old Gym',
            'start_date' => now()->subYears(3)->toDateString(), 'end_date' => now()->subYear()->toDateString(),
        ]);

        $label = TrainerExperience::label($coach->fresh());
        $this->assertNotNull($label);
        $this->assertStringContainsString('2 year', $label);   // ~2 years of prior coaching
    }
}
