<?php

namespace Tests\Feature\Members;

use App\Clubs\Models\Business;
use App\Federations\Models\Federation;
use App\Members\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * An owner is never deleted out from under what they own (security fix,
 * 2026-10-03).
 *
 * `owner_user_id` on tenants, businesses and federations is ON DELETE CASCADE,
 * so permanently deleting an owner deleted their club, chain or federation with
 * them. And the delete screens only ever asked about CLUBS, so the owner of a
 * chain or a federation could be deleted, leaving it owned by nobody.
 */
class OwnerDeletionGuardTest extends TestCase
{
    use RefreshDatabase;

    private User $super;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->super = $this->createUser();
        $this->makeSuperAdmin($this->super);
    }

    private function federation(User $owner): Federation
    {
        return Federation::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(), 'owner_user_id' => $owner->id,
            'name' => 'Test Federation', 'country' => 'BH', 'sport' => 'taekwondo', 'status' => 'approved',
        ]);
    }

    public function test_a_club_owner_cannot_be_permanently_deleted(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner);

        try {
            $owner->forceDelete();
            $this->fail('the owner was permanently deleted');
        } catch (\LogicException $e) {
            $this->assertStringContainsString('Transfer ownership first', $e->getMessage());
        }

        $this->assertNotNull(User::withTrashed()->find($owner->id));
        $this->assertNotNull($club->fresh(), 'the club went with its owner');
    }

    public function test_the_owner_of_a_soft_deleted_club_is_still_protected_from_a_hard_delete(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner);
        $club->delete();

        $this->expectException(\LogicException::class);
        $owner->forceDelete();
    }

    public function test_someone_who_owns_nothing_can_still_be_permanently_deleted(): void
    {
        $user = $this->createUser();
        $user->forceDelete();

        $this->assertNull(User::withTrashed()->find($user->id));
    }

    public function test_the_admin_delete_screen_refuses_a_chain_owner(): void
    {
        $owner = $this->createUser();
        Business::create(['owner_user_id' => $owner->id, 'name' => 'Big Chain', 'slug' => 'big-chain-'.uniqid(), 'status' => Business::STATUS_APPROVED]);

        $this->actingAs($this->super)
            ->deleteJson(route('admin.platform.members.destroy', $owner->id))
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertFalse($owner->fresh()->trashed());
    }

    public function test_the_admin_delete_screen_refuses_a_federation_owner(): void
    {
        $owner = $this->createUser();
        $this->federation($owner);

        $this->actingAs($this->super)
            ->deleteJson(route('admin.platform.members.destroy', $owner->id))
            ->assertStatus(422);

        $this->assertFalse($owner->fresh()->trashed());
    }

    public function test_the_admin_delete_screen_still_refuses_a_club_owner_and_allows_everyone_else(): void
    {
        $owner = $this->createUser();
        $this->createClub($owner);
        $this->actingAs($this->super)->deleteJson(route('admin.platform.members.destroy', $owner->id))->assertStatus(422);
        $this->assertFalse($owner->fresh()->trashed());

        $plain = $this->createUser();
        $this->actingAs($this->super)->deleteJson(route('admin.platform.members.destroy', $plain->id))->assertOk();
        $this->assertTrue($plain->fresh()->trashed());
    }

    public function test_the_owner_of_only_a_deleted_club_can_still_be_soft_deleted_as_before(): void
    {
        $owner = $this->createUser();
        $this->createClub($owner)->delete();

        $this->actingAs($this->super)->deleteJson(route('admin.platform.members.destroy', $owner->id))->assertOk();
        $this->assertTrue($owner->fresh()->trashed());
    }
}
