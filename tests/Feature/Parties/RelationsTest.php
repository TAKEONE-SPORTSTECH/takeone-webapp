<?php

namespace Tests\Feature\Parties;

use App\Parties\Kinds;
use App\Parties\Models\PartyRelation;
use App\Parties\Relations;
use App\Parties\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The invariants of the one relationship table (PLAN.md §3.2), proved
 * directly against App\Parties\Relations — the only thing allowed to write it.
 */
class RelationsTest extends TestCase
{
    use RefreshDatabase;

    private Relations $rel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rel = app(Relations::class);
    }

    public function test_a_party_kind_is_its_own_stable_key(): void
    {
        $user = $this->createUser();
        $club = $this->createClub($user);

        $this->assertSame('person', Kinds::keyOf($user));
        $this->assertSame('club', Kinds::keyOf($club));
        $this->assertTrue($club->is(Kinds::find('club', $club->id)));
    }

    public function test_ownership_is_set_as_a_whole_and_sums_to_one_hundred_percent(): void
    {
        [$a, $b] = [$this->createUser(), $this->createUser()];
        $club = $this->createClub($a);

        $this->rel->setOwners($club, [[$a, 6000], [$b, 4000]], 'founded');

        $owners = $this->rel->owners($club);
        $this->assertCount(2, $owners);
        $this->assertSame(10000, (int) $owners->sum('share_bp'));
        $this->assertTrue($a->is($this->rel->majorityOwner($club)));
    }

    public function test_shares_that_do_not_add_up_are_refused_and_change_nothing(): void
    {
        [$a, $b] = [$this->createUser(), $this->createUser()];
        $club = $this->createClub($a);
        $this->rel->setOwners($club, [[$a, 10000]], 'founded');

        foreach ([[[$a, 6000], [$b, 3000]], [[$a, 6000], [$b, 5000]], [], [[$a, 10000], [$a, 0]]] as $bad) {
            try {
                $this->rel->setOwners($club, $bad, 'bad');
                $this->fail('an invalid ownership set was accepted');
            } catch (\InvalidArgumentException) {
            }
        }

        $this->assertCount(1, $this->rel->owners($club));
        $this->assertSame(10000, (int) $this->rel->owners($club)->first()->share_bp);
    }

    public function test_a_fifty_fifty_split_has_no_majority_owner(): void
    {
        [$a, $b] = [$this->createUser(), $this->createUser()];
        $club = $this->createClub($a);
        $this->rel->setOwners($club, [[$a, 5000], [$b, 5000]], 'founded');

        $this->assertNull($this->rel->majorityOwner($club));
    }

    public function test_selling_part_of_a_stake_keeps_the_history(): void
    {
        [$a, $b] = [$this->createUser(), $this->createUser()];
        $club = $this->createClub($a);
        $this->rel->setOwners($club, [[$a, 10000]], 'founded');

        $this->rel->transferShare($club, $a, $b, 3000, 'sold 30%');

        $owners = $this->rel->owners($club)->mapWithKeys(fn ($r) => [$r->from_id => $r->share_bp]);
        $this->assertSame([$a->id => 7000, $b->id => 3000], $owners->all());

        // The original 100% row is ended, not deleted — and says why.
        $ended = PartyRelation::query()->in($club)->where('role', Roles::OWNER)->whereNotNull('ends_at')->get();
        $this->assertCount(1, $ended);
        $this->assertSame(10000, $ended->first()->share_bp);
        $this->assertSame('sold 30%', $ended->first()->end_reason);
        $this->assertCount(3, $this->rel->history($club));
    }

    public function test_selling_more_than_you_hold_is_refused(): void
    {
        [$a, $b] = [$this->createUser(), $this->createUser()];
        $club = $this->createClub($a);
        $this->rel->setOwners($club, [[$a, 6000], [$b, 4000]], 'founded');

        $this->expectException(\InvalidArgumentException::class);
        $this->rel->transferShare($club, $b, $a, 5000, 'too much');
    }

    public function test_an_unchanged_owner_keeps_their_original_row(): void
    {
        [$a, $b, $c] = [$this->createUser(), $this->createUser(), $this->createUser()];
        $club = $this->createClub($a);
        $this->rel->setOwners($club, [[$a, 5000], [$b, 5000]], 'founded');
        $aRow = $this->rel->owners($club)->firstWhere('from_id', $a->id);

        $this->rel->transferShare($club, $b, $c, 5000, 'b sold out');

        $this->assertSame($aRow->id, $this->rel->owners($club)->firstWhere('from_id', $a->id)->id);
    }

    public function test_owners_cannot_be_granted_or_ended_one_by_one(): void
    {
        $a = $this->createUser();
        $club = $this->createClub($a);

        $this->expectException(\LogicException::class);
        $this->rel->grant($a, Roles::OWNER, $club);
    }

    public function test_a_role_is_ended_never_deleted_and_can_be_held_again_later(): void
    {
        $coach = $this->createUser();
        $club = $this->createClub($this->createUser());

        $first = $this->rel->grant($coach, Roles::COACH, $club);
        $this->assertTrue($this->rel->holds($coach, Roles::COACH, $club));

        $this->rel->end($first, 'left the club');
        $this->assertFalse($this->rel->holds($coach, Roles::COACH, $club));
        $this->assertNotNull($first->fresh()->ends_at);

        // A second stint is a new row; the first stays as history.
        $second = $this->rel->grant($coach, Roles::COACH, $club);
        $this->assertNotSame($first->id, $second->id);
        $this->assertCount(2, $this->rel->history($club));
    }

    public function test_granting_an_active_role_twice_is_a_no_op(): void
    {
        $admin = $this->createUser();
        $club = $this->createClub($this->createUser());

        $one = $this->rel->grant($admin, Roles::ADMIN, $club);
        $two = $this->rel->grant($admin, Roles::ADMIN, $club);

        $this->assertSame($one->id, $two->id);
        $this->assertSame(1, PartyRelation::count());
    }

    public function test_a_source_makes_a_write_idempotent(): void
    {
        $admin = $this->createUser();
        $club = $this->createClub($this->createUser());

        $one = $this->rel->grant($admin, Roles::ADMIN, $club, ['source' => 'user_roles:1']);
        $this->rel->end($one, 'test');
        $again = $this->rel->grant($admin, Roles::ADMIN, $club, ['source' => 'user_roles:1']);

        $this->assertSame($one->id, $again->id, 'a re-run backfill must not re-create an ended row');
    }

    public function test_an_unknown_role_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->rel->grant($this->createUser(), 'overlord', $this->createClub($this->createUser()));
    }

    public function test_a_president_term_is_recorded_with_dates_and_does_not_expire_by_itself(): void
    {
        $president = $this->createUser();
        $fed = \App\Federations\Models\Federation::create([
            'owner_user_id' => $president->id, 'name' => 'Fed', 'country' => 'BH', 'sport' => 'taekwondo', 'status' => 'approved',
        ]);

        $term = $this->rel->grant($president, Roles::PRESIDENT, $fed, [
            'starts_at' => now()->subYears(4), 'meta' => ['term_ends' => now()->subDay()->toDateString()],
        ]);

        // The planned end of the term is a recorded fact, not a timer (owner, 2026-10-03).
        $this->assertTrue($this->rel->holds($president, Roles::PRESIDENT, $fed));
        $this->assertSame(now()->subDay()->toDateString(), $term->meta['term_ends']);
    }
}
