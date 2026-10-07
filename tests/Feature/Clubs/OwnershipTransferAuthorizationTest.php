<?php

namespace Tests\Feature\Clubs;

use App\Clubs\Models\Business;
use App\Clubs\Models\Tenant;
use App\Members\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Who may hand a club to somebody else (security fix, 2026-10-03).
 *
 * Both ownership doors — transfer to an existing person, and create-a-new-owner
 * — were gated only by authorizeClub(), so ANY club admin could make anyone on
 * the platform the owner, and create-owner could restore a deleted account and
 * set its password. Changing who owns a club is now for the people who own it:
 * the owner, the approved chain owner above it, or a super-admin. A club admin
 * still runs the club; they just cannot give it away.
 */
class OwnershipTransferAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Tenant $club;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();

        $this->owner = $this->createUser(['email' => 'owner@example.test']);
        $this->club = $this->createClub($this->owner);
    }

    private function transfer(User $actor, User $to)
    {
        return $this->actingAs($actor)->postJson(
            route('admin.club.transfer-ownership', $this->club->slug),
            ['mode' => 'existing', 'user_id' => $to->id]
        );
    }

    private function createOwner(User $actor)
    {
        return $this->actingAs($actor)->postJson(route('admin.club.create-owner', $this->club->slug), [
            'full_name' => 'New Owner', 'email' => 'new-owner@example.test', 'gender' => 'Male',
            'birthdate' => '1990-01-01', 'nationality' => 'BH', 'blood_type' => 'O+', 'password' => 'secret-password',
        ]);
    }

    public function test_a_club_admin_cannot_transfer_the_club(): void
    {
        $admin = $this->createUser();
        $this->makeClubAdmin($admin, $this->club);

        $this->transfer($admin, $admin)->assertForbidden();

        $this->assertSame($this->owner->id, $this->club->fresh()->owner_user_id);
    }

    public function test_a_club_admin_cannot_create_a_new_owner(): void
    {
        $admin = $this->createUser();
        $this->makeClubAdmin($admin, $this->club);

        $this->createOwner($admin)->assertForbidden();

        $this->assertSame($this->owner->id, $this->club->fresh()->owner_user_id);
        $this->assertFalse(User::where('email', 'new-owner@example.test')->exists());
    }

    public function test_a_stranger_cannot_transfer_the_club(): void
    {
        $stranger = $this->createUser();

        $this->transfer($stranger, $stranger)->assertForbidden();
        $this->assertSame($this->owner->id, $this->club->fresh()->owner_user_id);
    }

    public function test_the_owner_can_transfer_the_club(): void
    {
        $next = $this->createUser();

        $this->transfer($this->owner, $next)->assertOk()->assertJson(['success' => true]);

        $this->assertSame($next->id, $this->club->fresh()->owner_user_id);
        $this->assertTrue($next->fresh()->isClubAdmin($this->club->id));
    }

    public function test_the_owner_can_create_a_new_owner(): void
    {
        $this->createOwner($this->owner)->assertOk()->assertJson(['success' => true]);

        $new = User::where('email', 'new-owner@example.test')->firstOrFail();
        $this->assertSame($new->id, $this->club->fresh()->owner_user_id);
    }

    public function test_a_super_admin_can_transfer_the_club(): void
    {
        $super = $this->createUser();
        $this->makeSuperAdmin($super);
        $next = $this->createUser();

        $this->transfer($super, $next)->assertOk();
        $this->assertSame($next->id, $this->club->fresh()->owner_user_id);
    }

    public function test_the_approved_chain_owner_can_transfer_the_club(): void
    {
        $chainOwner = $this->createUser();
        $business = Business::create([
            'owner_user_id' => $chainOwner->id, 'name' => 'Chain', 'slug' => 'chain-'.uniqid(),
            'status' => Business::STATUS_APPROVED,
        ]);
        $this->club->forceFill(['business_id' => $business->id])->save();
        $next = $this->createUser();

        $this->transfer($chainOwner, $next)->assertOk();
        $this->assertSame($next->id, $this->club->fresh()->owner_user_id);
    }

    public function test_the_details_page_offers_the_transfer_only_to_those_who_may_use_it(): void
    {
        $admin = $this->createUser();
        $this->makeClubAdmin($admin, $this->club);
        $url = route('admin.club.details', $this->club->slug);
        $transferUrl = route('admin.club.transfer-ownership', $this->club->slug);

        foreach (['desktop' => 'Mozilla/5.0 (X11; Linux x86_64) Chrome/120', 'mobile' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Mobile/15E148'] as $device => $ua) {
            $asAdmin = $this->actingAs($admin)->withHeaders(['User-Agent' => $ua])->get($url)->assertOk()->getContent();
            $this->assertStringNotContainsString(str_replace('/', '\/', $transferUrl), $asAdmin, "{$device}: a club admin was offered the transfer");
            $this->assertStringNotContainsString($transferUrl, $asAdmin, "{$device}: a club admin was offered the transfer");

            $asOwner = $this->actingAs($this->owner)->withHeaders(['User-Agent' => $ua])->get($url)->assertOk()->getContent();
            $this->assertTrue(
                str_contains($asOwner, $transferUrl) || str_contains($asOwner, str_replace('/', '\/', $transferUrl)),
                "{$device}: the owner lost the transfer"
            );
        }
    }

    /* ---- Deleting the club is the owner's call too (same fix, same day) ---- */

    public function test_a_club_admin_cannot_delete_the_club(): void
    {
        $admin = $this->createUser();
        $this->makeClubAdmin($admin, $this->club);

        $this->actingAs($admin)->deleteJson(route('admin.club.destroy', $this->club->slug))->assertForbidden();

        $this->assertNotNull(Tenant::find($this->club->id), 'a club admin deleted the club');
    }

    public function test_the_owner_can_still_delete_the_club(): void
    {
        $this->actingAs($this->owner)->delete(route('admin.club.destroy', $this->club->slug), ['confirm_name' => $this->club->club_name])->assertRedirect();

        $this->assertNull(Tenant::find($this->club->id));
    }

    public function test_the_danger_zone_is_drawn_only_for_those_who_may_use_it(): void
    {
        $admin = $this->createUser();
        $this->makeClubAdmin($admin, $this->club);
        $url = route('admin.club.details', $this->club->slug);
        // Not the form's address — the club's own edit form posts to the same
        // URL (PUT vs DELETE). The danger zone's own words are the marker.
        $markers = [__('admin.club_details_index_delete_this_club'), __('admin.club_details_index_delete_permanently')];
        $has = fn (string $html) => (bool) array_filter($markers, fn ($m) => str_contains($html, e($m)));

        foreach (['Mozilla/5.0 (X11; Linux x86_64) Chrome/120', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Mobile/15E148'] as $ua) {
            $asAdmin = $this->actingAs($admin)->withHeaders(['User-Agent' => $ua])->get($url)->assertOk()->getContent();
            $this->assertFalse($has($asAdmin), "a club admin was shown the danger zone ({$ua})");

            $asOwner = $this->actingAs($this->owner)->withHeaders(['User-Agent' => $ua])->get($url)->assertOk()->getContent();
            $this->assertTrue($has($asOwner), "the owner lost the danger zone ({$ua})");
        }
    }
}
