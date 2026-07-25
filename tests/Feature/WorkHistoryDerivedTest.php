<?php

namespace Tests\Feature;

use App\Models\ClubInstructor;
use Tests\TestCase;

/**
 * A member's platform trainer/staff roles (ClubInstructor) surface automatically in
 * their profile Work History as read-only, derived entries.
 */
class WorkHistoryDerivedTest extends TestCase
{
    public function test_platform_trainer_role_appears_in_work_history(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner, ['club_name' => 'Iron Gym']);
        $member = $this->createUser();

        ClubInstructor::create([
            'tenant_id' => $club->id, 'user_id' => $member->id, 'role' => 'Head Coach',
            'staff_type' => 'instructor', 'is_active' => true, 'sort_order' => 0,
        ]);

        $res = $this->actingAs($member)->get('/member/'.$member->uuid);
        $res->assertOk();
        // The member has NO manual work history, so a Head Coach role at Iron Gym in the
        // payload can only be the derived platform entry.
        $res->assertSee('Head Coach', false);
        $res->assertSee('Iron Gym', false);
        $res->assertSee('derived', false);   // the derived flag ships in the work items
    }
}
