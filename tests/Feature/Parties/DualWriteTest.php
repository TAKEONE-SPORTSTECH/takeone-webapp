<?php

namespace Tests\Feature\Parties;

use App\Clubs\Models\Business;
use App\Clubs\Models\ClubInstructor;
use App\Members\Models\Membership;
use App\Parties\Backfill;
use App\Parties\Models\PartyRelation;
use App\Parties\Relations;
use App\Parties\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PLAN.md Phase 3 — writes to the OLD sources are mirrored into
 * party_relations while `parties.enabled` is on, and change nothing while it
 * is off (the default, and the live state until it is switched).
 */
class DualWriteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_switched_off_nothing_is_written(): void
    {
        config(['parties.enabled' => false]);
        $owner = $this->createUser();
        $club = $this->createClub($owner);
        $this->makeClubAdmin($this->createUser(), $club);

        $this->assertSame(0, PartyRelation::count());
    }

    public function test_switched_on_the_old_writes_are_mirrored_and_verify_stays_clean(): void
    {
        config(['parties.enabled' => true]);
        $rel = app(Relations::class);

        $owner = $this->createUser();
        $club = $this->createClub($owner);
        $this->assertTrue($owner->is($rel->majorityOwner($club)), 'a new club records its owner');

        $admin = $this->createUser();
        $admin->assignRole('club-admin', $club->id);
        $this->assertTrue($rel->holds($admin, Roles::ADMIN, $club));

        $coach = $this->createUser();
        $i = ClubInstructor::create(['tenant_id' => $club->id, 'user_id' => $coach->id, 'role' => 'Coach', 'staff_type' => 'instructor', 'is_active' => true]);
        $this->assertTrue($rel->holds($coach, Roles::STAFF, $club));

        $member = $this->createUser();
        $m = Membership::create(['tenant_id' => $club->id, 'user_id' => $member->id, 'status' => 'active']);
        $this->assertTrue($rel->holds($member, Roles::MEMBER, $club));

        $this->assertSame([], app(Backfill::class)->drift());

        // Endings are recorded, not deleted.
        $admin->removeRole('club-admin', $club->id);
        $i->update(['is_active' => false]);
        $m->update(['status' => 'inactive']);
        $this->assertFalse($rel->holds($admin, Roles::ADMIN, $club));
        $this->assertFalse($rel->holds($coach, Roles::STAFF, $club));
        $this->assertFalse($rel->holds($member, Roles::MEMBER, $club));
        $this->assertSame(3, PartyRelation::whereNotNull('ends_at')->count());
    }

    public function test_an_owner_change_on_the_column_moves_the_relationship(): void
    {
        config(['parties.enabled' => true]);
        $rel = app(Relations::class);
        $first = $this->createUser();
        $club = $this->createClub($first);
        $next = $this->createUser();

        $club->update(['owner_user_id' => $next->id]);

        $this->assertTrue($next->is($rel->majorityOwner($club)));
        $this->assertCount(2, $rel->history($club)->where('role', Roles::OWNER));
    }

    public function test_a_co_owned_club_is_never_flattened_by_the_single_owner_column(): void
    {
        config(['parties.enabled' => true]);
        $rel = app(Relations::class);
        [$a, $b] = [$this->createUser(), $this->createUser()];
        $club = $this->createClub($a);
        $rel->setOwners($club, [[$a, 6000], [$b, 4000]], 'partnership');

        $club->update(['owner_user_id' => $b->id]);

        $this->assertCount(2, $rel->owners($club), 'the shares were overwritten by the column');
    }

    public function test_joining_a_chain_is_mirrored(): void
    {
        config(['parties.enabled' => true]);
        $chainOwner = $this->createUser();
        $business = Business::create(['owner_user_id' => $chainOwner->id, 'name' => 'Chain', 'slug' => 'chain-'.uniqid(), 'status' => Business::STATUS_APPROVED]);
        $club = $this->createClub($this->createUser());

        $club->forceFill(['business_id' => $business->id])->save();

        $this->assertTrue(app(Relations::class)->holds($club, Roles::CHAIN_CLUB, $business));
    }

    public function test_a_failing_mirror_never_breaks_the_original_write(): void
    {
        config(['parties.enabled' => true]);
        \Illuminate\Support\Facades\Schema::drop('party_relations');   // the mirror now fails on every write

        $owner = $this->createUser();
        $club = $this->createClub($owner);
        $owner->assignRole('club-admin', $club->id);

        $this->assertNotNull($club->fresh());
        $this->assertTrue($owner->fresh()->isClubAdmin($club->id));
    }
}
