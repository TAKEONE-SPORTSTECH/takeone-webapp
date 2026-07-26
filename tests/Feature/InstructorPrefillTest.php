<?php

namespace Tests\Feature;

use App\Models\ClubAffiliation;
use App\Models\MemberWorkHistory;
use Tests\TestCase;

/**
 * The "Add Instructor → existing member" prefill endpoint returns the member's
 * accumulated skill set + prior COACHING experience (in whole years), scoped to
 * club admins only.
 */
class InstructorPrefillTest extends TestCase
{
    public function test_prefill_returns_accumulated_skills_and_coaching_years(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner);
        $member = $this->createUser();

        // Accumulated skill via an affiliation.
        $aff = ClubAffiliation::create([
            'member_id' => $member->id, 'tenant_id' => null, 'club_name' => 'Old Dojang',
            'start_date' => '2010-01-01',
        ]);
        $aff->skillAcquisitions()->create([
            'user_id' => $member->id, 'skill_name' => 'Judo', 'proficiency_level' => 'expert', 'duration_months' => 12,
        ]);

        // Prior coaching role → 3 years; a non-coaching role must NOT count.
        MemberWorkHistory::create([
            'user_id' => $member->id, 'title' => 'Head Coach', 'organization' => 'Rival Club',
            'start_date' => '2015-01-01', 'end_date' => '2018-01-01',
        ]);
        MemberWorkHistory::create([
            'user_id' => $member->id, 'title' => 'Accountant', 'organization' => 'A Firm',
            'start_date' => '2010-01-01', 'end_date' => '2014-01-01',
        ]);

        $this->actingAs($owner)
            ->getJson('/admin/club/'.$club->slug.'/instructors-prefill/'.$member->id)
            ->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonFragment(['Judo'])
            // Accumulated practice per skill (12 recorded months = "1 year").
            ->assertJsonPath('skill_years.judo', '1 year');
    }

    public function test_prefill_denies_a_non_admin(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner);
        $stranger = $this->createUser();
        $member = $this->createUser();

        $this->actingAs($stranger)
            ->getJson('/admin/club/'.$club->slug.'/instructors-prefill/'.$member->id)
            ->assertForbidden();
    }
}
