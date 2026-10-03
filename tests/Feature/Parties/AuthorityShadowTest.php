<?php

namespace Tests\Feature\Parties;

use App\Clubs\Models\Business;
use App\Events\Models\ClubEvent;
use App\Events\Models\EventOfficial;
use App\Events\Support\EventAccess;
use App\Federations\Models\Federation;
use App\Members\Models\User;
use App\Parties\Authority;
use App\Parties\Backfill;
use App\Parties\Relations;
use App\Parties\Shadow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * PLAN.md Phase 4 — App\Parties\Authority must give EXACTLY today's answers
 * before any check is cut over to it. Every person in a realistic world is
 * asked every question both ways; the two must agree for all of them.
 */
class AuthorityShadowTest extends TestCase
{
    use RefreshDatabase;

    private array $w;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        Shadow::flush();

        $owner = $this->createUser();
        $admin = $this->createUser();
        $member = $this->createUser();
        $stranger = $this->createUser();
        $chainOwner = $this->createUser();
        $super = $this->createUser();
        $this->makeSuperAdmin($super);
        $organiser = $this->createUser();

        $club = $this->createClub($owner);
        $this->makeClubAdmin($admin, $club);
        \App\Members\Models\Membership::create(['tenant_id' => $club->id, 'user_id' => $member->id, 'status' => 'active']);
        $business = Business::create(['owner_user_id' => $chainOwner->id, 'name' => 'Chain', 'slug' => 'chain-'.uniqid(), 'status' => Business::STATUS_APPROVED]);
        $club->forceFill(['business_id' => $business->id])->save();

        $event = ClubEvent::create([
            'tenant_id' => $club->id, 'created_by' => $owner->id, 'title' => 'Cup', 'event_type' => 'championship',
            'sport' => 'taekwondo', 'scope' => 'internal', 'status' => 'active', 'is_archived' => false,
            'date' => now()->addWeek()->toDateString(), 'start_time' => '09:00:00', 'end_time' => '17:00:00',
        ]);
        EventOfficial::create(['event_id' => $event->id, 'user_id' => $organiser->id, 'role' => 'organiser']);

        $head = $this->createUser();
        $officer = $this->createUser();
        $scoped = $this->createUser();
        $fed = Federation::create(['owner_user_id' => $head->id, 'name' => 'Fed', 'country' => 'BH', 'sport' => 'taekwondo', 'status' => 'approved']);
        $sub = Federation::create(['owner_user_id' => $this->createUser()->id, 'parent_id' => $fed->id, 'name' => 'Sub', 'country' => 'BH', 'sport' => 'taekwondo', 'status' => 'approved']);
        DB::table('federation_staff')->insert([
            ['federation_id' => $fed->id, 'user_id' => $officer->id, 'role' => 'Secretary', 'sports' => json_encode([]), 'created_at' => now(), 'updated_at' => now()],
            ['federation_id' => $fed->id, 'user_id' => $scoped->id, 'role' => 'Karate desk', 'sports' => json_encode(['Karate']), 'created_at' => now(), 'updated_at' => now()],
        ]);
        $fedEvent = ClubEvent::create([
            'tenant_id' => null, 'host_type' => 'federation', 'host_id' => $sub->id, 'created_by' => $head->id,
            'title' => 'Nationals', 'event_type' => 'championship', 'sport' => 'taekwondo', 'scope' => 'nationwide',
            'status' => 'active', 'is_archived' => false,
            'date' => now()->addWeek()->toDateString(), 'start_time' => '09:00:00', 'end_time' => '17:00:00',
        ]);

        app(Backfill::class)->run(true);

        $this->w = compact('owner', 'admin', 'member', 'stranger', 'chainOwner', 'super', 'organiser', 'head', 'officer', 'scoped', 'club', 'event', 'fed', 'sub', 'fedEvent');
    }

    /** @return list<User> */
    private function everyone(): array
    {
        return array_values(array_filter($this->w, fn ($v) => $v instanceof User));
    }

    public function test_club_management_agrees_for_everyone(): void
    {
        foreach ($this->everyone() as $u) {
            $old = $u->isSuperAdmin() || $this->w['club']->owner_user_id === $u->id || $u->isClubAdmin($this->w['club']->id)
                || Business::where('id', $this->w['club']->business_id)->where('owner_user_id', $u->id)->where('status', 'approved')->exists();
            $this->assertSame($old, app(Authority::class)->manageClub($u, $this->w['club']), "manageClub disagrees for user {$u->id}");
        }
    }

    public function test_acting_as_the_owner_agrees_for_everyone(): void
    {
        foreach ($this->everyone() as $u) {
            $old = $u->isSuperAdmin() || $this->w['club']->owner_user_id === $u->id
                || Business::where('id', $this->w['club']->business_id)->where('owner_user_id', $u->id)->where('status', 'approved')->exists();
            $this->assertSame($old, app(Authority::class)->actAsClubOwner($u, $this->w['club']), "actAsClubOwner disagrees for user {$u->id}");
        }
    }

    public function test_event_management_agrees_for_everyone_on_a_club_and_a_federation_event(): void
    {
        config(['federations.enabled' => true]);
        foreach ([$this->w['event'], $this->w['fedEvent']] as $event) {
            foreach ($this->everyone() as $u) {
                $old = (new EventAccess)->canManage($event->fresh(), $u);
                $this->assertSame($old, app(Authority::class)->manageEvent($u, $event->fresh()), "manageEvent disagrees for user {$u->id} on event {$event->id}");
            }
        }
    }

    public function test_shadow_mode_records_nothing_when_they_agree_and_never_changes_the_answer(): void
    {
        config(['parties.shadow' => true, 'federations.enabled' => true]);

        foreach ($this->everyone() as $u) {
            $this->actingAs($u);
            (new EventAccess)->canManage($this->w['event'], $u);
            (new EventAccess)->canManage($this->w['fedEvent'], $u);
        }

        $this->assertSame(0, DB::table('party_shadow_disagreements')->count());
    }

    public function test_a_disagreement_is_recorded_but_the_old_answer_still_decides(): void
    {
        config(['parties.shadow' => true]);
        // The relationships forget the admin; the old role row still says yes.
        app(Relations::class)->endRole($this->w['admin'], 'admin', $this->w['club'], 'test');

        $this->actingAs($this->w['admin'])
            ->get(route('admin.club.details', $this->w['club']->slug))
            ->assertOk();   // the old check still decides

        $row = DB::table('party_shadow_disagreements')->where('check', 'club.manage')->first();
        $this->assertNotNull($row);
        $this->assertSame($this->w['admin']->id, (int) $row->user_id);
        $this->assertSame(1, (int) $row->old_answer);
        $this->assertSame(0, (int) $row->new_answer);
    }
}
