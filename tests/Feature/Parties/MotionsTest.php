<?php

namespace Tests\Feature\Parties;

use App\Parties\Models\PartyMotion;
use App\Parties\Motions;
use App\Parties\Relations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "The majority decides" (owner, 2026-10-03): a majority owner acts alone;
 * otherwise the owners vote weighted by share — MORE than 50% passes, a 50/50
 * split never does. Every motion and vote is kept.
 */
class MotionsTest extends TestCase
{
    use RefreshDatabase;

    private Relations $rel;

    private Motions $motions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->rel = app(Relations::class);
        $this->motions = app(Motions::class);
    }

    private function shares($club): array
    {
        return $this->rel->owners($club)->mapWithKeys(fn ($r) => [$r->from_id => $r->share_bp])->sortKeys()->all();
    }

    public function test_a_majority_owner_decides_alone(): void
    {
        [$a, $b, $c] = [$this->createUser(), $this->createUser(), $this->createUser()];
        $club = $this->createClub($a);
        $this->rel->setOwners($club, [[$a, 6000], [$b, 4000]], 'founded');

        $m = $this->motions->propose($club, $a, [[$a, 6000], [$c, 4000]], 'buy b out');

        $this->assertSame('passed', $m->status);
        $this->assertSame([$a->id => 6000, $c->id => 4000], $this->shares($club));
    }

    public function test_a_minority_owner_proposes_and_the_vote_decides(): void
    {
        [$a, $b, $c, $d] = [$this->createUser(), $this->createUser(), $this->createUser(), $this->createUser()];
        $club = $this->createClub($a);
        $this->rel->setOwners($club, [[$a, 4000], [$b, 3500], [$c, 2500]], 'founded');

        $m = $this->motions->propose($club, $c, [[$a, 4000], [$b, 3500], [$d, 2500]], 'c sells to d');
        $this->assertSame('open', $m->status, '25% alone cannot decide');

        $m = $this->motions->vote($m, $b, true);   // 25 + 35 = 60%
        $this->assertSame('passed', $m->status);
        $this->assertSame([$a->id => 4000, $b->id => 3500, $d->id => 2500], $this->shares($club));
    }

    public function test_a_fifty_fifty_split_never_passes(): void
    {
        [$a, $b, $c] = [$this->createUser(), $this->createUser(), $this->createUser()];
        $club = $this->createClub($a);
        $this->rel->setOwners($club, [[$a, 5000], [$b, 5000]], 'founded');

        $m = $this->motions->propose($club, $a, [[$a, 5000], [$c, 5000]], 'replace b');
        $this->assertSame('open', $m->status, '50% is not a majority');

        $m = $this->motions->vote($m, $b, false);
        $this->assertSame('rejected', $m->status);
        $this->assertSame([$a->id => 5000, $b->id => 5000], $this->shares($club));
    }

    public function test_only_owners_may_propose_or_vote(): void
    {
        [$a, $b, $stranger] = [$this->createUser(), $this->createUser(), $this->createUser()];
        $club = $this->createClub($a);
        $this->rel->setOwners($club, [[$a, 5000], [$b, 5000]], 'founded');

        try {
            $this->motions->propose($club, $stranger, [[$stranger, 10000]], 'take it');
            $this->fail('a stranger proposed');
        } catch (\DomainException) {
        }

        $m = $this->motions->propose($club, $a, [[$a, 10000]], 'buy out');
        $this->expectException(\DomainException::class);
        $this->motions->vote($m, $stranger, true);
    }

    public function test_a_super_admin_decides_alone(): void
    {
        [$a, $b] = [$this->createUser(), $this->createUser()];
        $super = $this->createUser();
        $this->makeSuperAdmin($super);
        $club = $this->createClub($a);
        $this->rel->setOwners($club, [[$a, 5000], [$b, 5000]], 'founded');

        $this->assertSame('passed', $this->motions->propose($club, $super, [[$b, 10000]], 'ruling')->status);
        $this->assertSame([$b->id => 10000], $this->shares($club));
    }

    public function test_a_new_proposal_supersedes_the_open_one_and_the_proposer_may_withdraw(): void
    {
        [$a, $b, $c] = [$this->createUser(), $this->createUser(), $this->createUser()];
        $club = $this->createClub($a);
        $this->rel->setOwners($club, [[$a, 5000], [$b, 5000]], 'founded');

        $first = $this->motions->propose($club, $a, [[$a, 10000]], 'one');
        $second = $this->motions->propose($club, $b, [[$b, 10000]], 'two');

        $this->assertSame('superseded', $first->fresh()->status);
        $this->assertSame('withdrawn', $this->motions->withdraw($second, $b)->status);
        $this->assertSame(2, PartyMotion::count(), 'motions are kept');
    }

    public function test_the_old_owner_column_and_club_admin_follow_an_applied_change(): void
    {
        [$a, $b] = [$this->createUser(), $this->createUser()];
        $club = $this->createClub($a);
        $this->rel->setOwners($club, [[$a, 10000]], 'founded');

        $this->motions->propose($club, $a, [[$a, 3000], [$b, 7000]], 'sold the majority');

        $this->assertSame($b->id, (int) $club->fresh()->owner_user_id, 'the column names the largest holder');
        $this->assertTrue($a->fresh()->isClubAdmin($club->id));
        $this->assertTrue($b->fresh()->isClubAdmin($club->id));
    }
}
