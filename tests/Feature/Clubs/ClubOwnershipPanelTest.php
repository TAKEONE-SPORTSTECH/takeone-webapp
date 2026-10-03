<?php

namespace Tests\Feature\Clubs;

use App\Clubs\Models\Tenant;
use App\Members\Models\User;
use App\Parties\Relations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The club's owners panel (PLAN.md Phase 5): shares, history, proposals and
 * votes over HTTP — and the old single-owner transfer refusing to flatten a
 * co-owned club.
 */
class ClubOwnershipPanelTest extends TestCase
{
    use RefreshDatabase;

    private User $a;

    private User $b;

    private Tenant $club;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        config(['parties.enabled' => true]);

        $this->a = $this->createUser();
        $this->b = $this->createUser();
        $this->club = $this->createClub($this->a);   // dual write records a as 100%
    }

    private function coOwn(int $aBp = 6000): void
    {
        app(Relations::class)->setOwners($this->club, [[$this->a, $aBp], [$this->b, 10000 - $aBp]], 'partnership');
        $this->b->assignRole('club-admin', $this->club->id);
    }

    public function test_switched_off_every_door_is_a_404_and_the_panel_draws_nothing(): void
    {
        config(['parties.enabled' => false]);

        $this->actingAs($this->a)->getJson(route('admin.club.owners', $this->club->slug))->assertNotFound();
        $html = $this->actingAs($this->a)->get(route('admin.club.details', $this->club->slug))->assertOk()->getContent();
        $this->assertStringNotContainsString(route('admin.club.owners', $this->club->slug), $html);
    }

    public function test_an_owner_reads_the_owners_and_a_stranger_cannot(): void
    {
        $this->coOwn();

        $this->actingAs($this->a)->getJson(route('admin.club.owners', $this->club->slug))
            ->assertOk()
            ->assertJsonCount(2, 'owners')
            ->assertJsonPath('owners.0.share_bp', 6000)
            ->assertJsonPath('can_propose', true)
            ->assertJsonPath('i_decide', true)
            ->assertJsonMissingPath('owners.0.email');

        $this->actingAs($this->createUser())->getJson(route('admin.club.owners', $this->club->slug))->assertForbidden();
    }

    public function test_a_club_admin_who_owns_nothing_sees_the_owners_but_may_not_propose(): void
    {
        $admin = $this->createUser();
        $admin->assignRole('club-admin', $this->club->id);

        $this->actingAs($admin)->getJson(route('admin.club.owners', $this->club->slug))
            ->assertOk()->assertJsonPath('can_propose', false);

        $this->actingAs($admin)->postJson(route('admin.club.owners.propose', $this->club->slug), [
            'owners' => [['user_id' => $admin->id, 'share' => 100]],
        ])->assertForbidden();
        $this->assertTrue($this->a->is(app(Relations::class)->majorityOwner($this->club)));
    }

    public function test_the_majority_owner_changes_ownership_at_once(): void
    {
        $this->coOwn(6000);
        $c = $this->createUser();

        $this->actingAs($this->a)->postJson(route('admin.club.owners.propose', $this->club->slug), [
            'owners' => [['user_id' => $this->a->id, 'share' => 60], ['user_id' => $c->id, 'share' => 40]],
            'reason' => 'b sold to c',
        ])->assertOk()->assertJsonPath('motion', null)->assertJsonCount(2, 'owners');

        $ids = app(Relations::class)->owners($this->club)->pluck('from_id')->sort()->values()->all();
        $this->assertSame([$this->a->id, $c->id], $ids);
    }

    public function test_a_minority_owners_proposal_waits_for_the_vote_and_passes_with_it(): void
    {
        $this->coOwn(6000);

        $res = $this->actingAs($this->b)->postJson(route('admin.club.owners.propose', $this->club->slug), [
            'owners' => [['user_id' => $this->a->id, 'share' => 50], ['user_id' => $this->b->id, 'share' => 50]],
        ])->assertOk();
        $uuid = $res->json('motion.uuid');
        $this->assertNotNull($uuid, '40% alone cannot decide');

        $this->actingAs($this->a)->postJson(route('admin.club.owners.vote', [$this->club->slug, $uuid]), ['approve' => true])
            ->assertOk()->assertJsonPath('motion', null);

        $this->assertSame([5000, 5000], app(Relations::class)->owners($this->club)->pluck('share_bp')->all());
    }

    public function test_shares_must_total_one_hundred(): void
    {
        $this->actingAs($this->a)->postJson(route('admin.club.owners.propose', $this->club->slug), [
            'owners' => [['user_id' => $this->a->id, 'share' => 70], ['user_id' => $this->b->id, 'share' => 20]],
        ])->assertStatus(422);
    }

    public function test_a_proposal_is_reached_only_through_its_own_club(): void
    {
        $this->coOwn(4000);   // b holds the majority, so a's proposal stays open
        $uuid = $this->actingAs($this->a)->postJson(route('admin.club.owners.propose', $this->club->slug), [
            'owners' => [['user_id' => $this->a->id, 'share' => 100]],
        ])->json('motion.uuid');

        $other = $this->createClub($this->a);
        $this->actingAs($this->a)->postJson(route('admin.club.owners.vote', [$other->slug, $uuid]), ['approve' => true])->assertNotFound();
    }

    public function test_the_single_owner_transfer_refuses_a_co_owned_club(): void
    {
        $this->coOwn(6000);
        $c = $this->createUser();

        $this->actingAs($this->a)->postJson(route('admin.club.transfer-ownership', $this->club->slug), ['mode' => 'existing', 'user_id' => $c->id])
            ->assertStatus(422);

        $this->assertCount(2, app(Relations::class)->owners($this->club));
        $this->assertSame($this->a->id, (int) $this->club->fresh()->owner_user_id);
    }

    public function test_the_details_page_draws_the_panel_on_desktop_and_mobile(): void
    {
        foreach (['Mozilla/5.0 (X11; Linux x86_64) Chrome/120', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Mobile/15E148'] as $ua) {
            $html = $this->actingAs($this->a)->withHeaders(['User-Agent' => $ua])
                ->get(route('admin.club.details', $this->club->slug))->assertOk()->getContent();
            $this->assertStringContainsString(e(__('parties::parties.owners_title')), $html);
        }
    }
}
