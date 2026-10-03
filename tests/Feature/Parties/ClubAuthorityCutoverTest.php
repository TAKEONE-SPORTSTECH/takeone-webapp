<?php

namespace Tests\Feature\Parties;

use App\Clubs\Models\Business;
use App\Clubs\Models\Tenant;
use App\Members\Models\User;
use App\Parties\Relations;
use App\Parties\Shadow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The cut-over for clubs (owner, 2026-10-03): with PARTIES_AUTHORITY=clubs the
 * relationships DECIDE who may manage a club or act as its owner. The old
 * check is still asked and any difference recorded; an error in the new path
 * falls back to the old answer.
 */
class ClubAuthorityCutoverTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Tenant $club;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        Shadow::flush();
        config(['parties.enabled' => true, 'parties.authority' => ['clubs']]);

        $this->owner = $this->createUser();
        $this->club = $this->createClub($this->owner);
    }

    private function details(User $u)
    {
        Shadow::flush();

        return $this->actingAs($u)->getJson(route('admin.club.details', $this->club->slug));
    }

    public function test_owner_and_admin_get_in_and_a_stranger_does_not(): void
    {
        $admin = $this->createUser();
        $admin->assignRole('club-admin', $this->club->id);

        $this->details($this->owner)->assertOk();
        $this->details($admin)->assertOk();
        $this->details($this->createUser())->assertForbidden();
        $this->assertSame(0, DB::table('party_shadow_disagreements')->count());
    }

    public function test_a_removed_admin_is_out_at_once(): void
    {
        $admin = $this->createUser();
        $admin->assignRole('club-admin', $this->club->id);
        $this->details($admin)->assertOk();

        $admin->removeRole('club-admin', $this->club->id);

        $this->details($admin->fresh())->assertForbidden();
    }

    public function test_custom_access_that_strips_the_roles_strips_the_access_too(): void
    {
        $super = $this->createUser();
        $this->makeSuperAdmin($super);
        $admin = $this->createUser();
        $admin->assignRole('club-admin', $this->club->id);

        $this->actingAs($super)->postJson(route('admin.club.roles.member.permissions.store', $this->club->slug), [
            'user_id' => $admin->id, 'permissions' => ['enter-athletes'],
        ])->assertOk();

        $this->details($admin->fresh())->assertForbidden();
        $this->assertSame(0, DB::table('party_shadow_disagreements')->count(), 'old and new still agree');
    }

    public function test_a_chain_transfer_that_reassigns_the_clubs_moves_their_ownership(): void
    {
        $chainOwner = $this->createUser();
        $business = Business::create(['owner_user_id' => $chainOwner->id, 'name' => 'Chain', 'slug' => 'chain-'.uniqid(), 'status' => Business::STATUS_APPROVED]);
        $this->club->forceFill(['business_id' => $business->id])->save();
        $next = $this->createUser();

        $business->transferOwnerTo($next->id, null, true);

        $this->assertTrue($next->is(app(Relations::class)->majorityOwner($this->club)));
        $this->details($next)->assertOk();
    }

    public function test_the_new_answer_decides_and_a_difference_is_recorded(): void
    {
        $admin = $this->createUser();
        $admin->assignRole('club-admin', $this->club->id);
        // The relationships forget the admin while the old role row stays.
        app(Relations::class)->endRole($admin, 'admin', $this->club, 'test');

        $this->details($admin)->assertForbidden();   // the NEW answer decided
        $row = DB::table('party_shadow_disagreements')->where('check', 'club.manage')->first();
        $this->assertSame([1, 0], [(int) $row->old_answer, (int) $row->new_answer]);
    }

    public function test_switched_back_the_old_check_decides_again(): void
    {
        $admin = $this->createUser();
        $admin->assignRole('club-admin', $this->club->id);
        app(Relations::class)->endRole($admin, 'admin', $this->club, 'test');

        config(['parties.authority' => []]);
        $this->details($admin)->assertOk();
    }

    public function test_if_the_new_path_fails_the_old_answer_decides(): void
    {
        \Illuminate\Support\Facades\Schema::drop('party_relations');

        $this->details($this->owner)->assertOk();
        $this->details($this->createUser())->assertForbidden();
    }
}
