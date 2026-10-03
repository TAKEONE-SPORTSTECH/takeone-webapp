<?php

namespace Tests\Feature\Parties;

use App\Clubs\Models\Business;
use App\Clubs\Models\ClubInstructor;
use App\Events\Models\ClubEvent;
use App\Federations\Models\Federation;
use App\Members\Models\Membership;
use App\Parties\Backfill;
use App\Parties\Models\PartyRelation;
use App\Parties\Relations;
use App\Parties\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * PLAN.md Phase 2: today's scattered sources copied into party_relations —
 * dry run writes nothing, --apply is idempotent, and verify then reports no
 * drift. The old sources are never written.
 */
class BackfillTest extends TestCase
{
    use RefreshDatabase;

    private function world(): array
    {
        $this->seedRoles();
        $owner = $this->createUser();
        $admin = $this->createUser();
        $coach = $this->createUser();
        $member = $this->createUser();
        $club = $this->createClub($owner);
        $this->makeClubAdmin($admin, $club);
        ClubInstructor::create(['tenant_id' => $club->id, 'user_id' => $coach->id, 'role' => 'Head Coach', 'staff_type' => 'instructor', 'is_active' => true]);
        Membership::create(['tenant_id' => $club->id, 'user_id' => $member->id, 'status' => 'active']);

        $chainOwner = $this->createUser();
        $business = Business::create(['owner_user_id' => $chainOwner->id, 'name' => 'Chain', 'slug' => 'chain-'.uniqid(), 'status' => Business::STATUS_APPROVED]);
        $club->forceFill(['business_id' => $business->id])->save();

        $head = $this->createUser();
        $officer = $this->createUser();
        $fed = Federation::create(['owner_user_id' => $head->id, 'name' => 'Fed', 'country' => 'BH', 'sport' => 'taekwondo', 'status' => 'approved']);
        DB::table('federation_staff')->insert(['federation_id' => $fed->id, 'user_id' => $officer->id, 'role' => 'Secretary', 'sports' => json_encode(['taekwondo']), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('federation_clubs')->insert(['federation_id' => $fed->id, 'tenant_id' => $club->id, 'status' => 'approved', 'uuid' => (string) \Illuminate\Support\Str::uuid(), 'decided_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

        $event = ClubEvent::create([
            'tenant_id' => $club->id, 'created_by' => $owner->id, 'title' => 'Cup', 'event_type' => 'championship',
            'sport' => 'taekwondo', 'scope' => 'internal', 'status' => 'active', 'is_archived' => false,
            'date' => now()->addWeek()->toDateString(), 'start_time' => '09:00:00', 'end_time' => '17:00:00',
        ]);

        return compact('owner', 'admin', 'coach', 'member', 'club', 'chainOwner', 'business', 'head', 'officer', 'fed', 'event');
    }

    public function test_a_dry_run_writes_nothing(): void
    {
        $this->world();
        $counts = app(Backfill::class)->run(false);

        $this->assertGreaterThan(0, array_sum($counts));
        $this->assertSame(0, PartyRelation::count());
    }

    public function test_apply_copies_every_source_and_verify_finds_no_drift(): void
    {
        $w = $this->world();
        $before = DB::table('tenants')->get()->toJson().DB::table('user_roles')->get()->toJson();

        app(Backfill::class)->run(true);
        $rel = app(Relations::class);

        $this->assertTrue($w['owner']->is($rel->majorityOwner($w['club'])));
        $this->assertTrue($w['chainOwner']->is($rel->majorityOwner($w['business'])));
        $this->assertTrue($w['head']->is($rel->majorityOwner($w['fed'])));
        $this->assertTrue($rel->holds($w['admin'], Roles::ADMIN, $w['club']));
        $this->assertTrue($rel->holds($w['coach'], Roles::STAFF, $w['club']));
        $this->assertTrue($rel->holds($w['member'], Roles::MEMBER, $w['club']));
        $this->assertTrue($rel->holds($w['officer'], Roles::OFFICER, $w['fed']));
        $this->assertTrue($rel->holds($w['club'], Roles::MEMBER_CLUB, $w['fed']));
        $this->assertTrue($rel->holds($w['club'], Roles::CHAIN_CLUB, $w['business']));
        $this->assertTrue($rel->holds($w['club'], Roles::HOST, $w['event']));
        $this->assertTrue($rel->holds($w['owner'], Roles::ORGANISER, $w['event']));

        $this->assertSame([], app(Backfill::class)->drift());

        // The old sources were only read.
        $this->assertSame($before, DB::table('tenants')->get()->toJson().DB::table('user_roles')->get()->toJson());
    }

    public function test_a_second_run_adds_nothing(): void
    {
        $this->world();
        app(Backfill::class)->run(true);
        $n = PartyRelation::count();

        $again = app(Backfill::class)->run(true);

        $this->assertSame(0, array_sum($again));
        $this->assertSame($n, PartyRelation::count());
    }

    public function test_an_inactive_instructor_and_a_lapsed_member_arrive_as_history(): void
    {
        $w = $this->world();
        ClubInstructor::where('user_id', $w['coach']->id)->update(['is_active' => false]);
        Membership::where('user_id', $w['member']->id)->update(['status' => 'inactive']);

        app(Backfill::class)->run(true);
        $rel = app(Relations::class);

        $this->assertFalse($rel->holds($w['coach'], Roles::STAFF, $w['club']));
        $this->assertFalse($rel->holds($w['member'], Roles::MEMBER, $w['club']));
        $this->assertSame(1, PartyRelation::where('source', 'like', 'club_instructors:%')->whereNotNull('ends_at')->count());
    }

    public function test_verify_reports_an_owner_changed_behind_its_back(): void
    {
        $w = $this->world();
        app(Backfill::class)->run(true);

        $w['club']->forceFill(['owner_user_id' => $w['admin']->id])->save();

        $drift = app(Backfill::class)->drift();
        $this->assertNotEmpty($drift);
        $this->assertStringContainsString("club {$w['club']->id}", $drift[0]);
    }
}
