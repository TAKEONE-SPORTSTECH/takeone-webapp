<?php

namespace Tests\Feature\Ranks;

use App\Clubs\Models\ClubActivity;
use App\Clubs\Models\ClubPackage;
use App\Ranks\Models\ActivityPackage;
use App\Ranks\Models\MemberRank;
use App\Ranks\Models\RankPromotion;
use App\Ranks\Support\ActivityPackages;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Step 2 of ranks & grading: a member's rank per activity, and its history. */
class MemberRanksTest extends TestCase
{
    private function dojo(string $name = 'Brazilian Jiu-Jitsu'): array
    {
        config(['ranks.enabled' => true]);
        $owner = $this->createUser();
        $club = $this->createClub($owner, ['slug' => 'dojo-'.uniqid()]);
        $activity = ClubActivity::create(['tenant_id' => $club->id, 'name' => $name]);
        app(ActivityPackages::class)->autoLink($activity);
        $package = ClubPackage::create(['tenant_id' => $club->id, 'name' => 'Adults', 'price' => 30, 'duration_months' => 1, 'is_active' => true]);
        DB::table('club_package_activities')->insert(['package_id' => $package->id, 'activity_id' => $activity->id, 'created_at' => now(), 'updated_at' => now()]);
        $member = $this->createUser(['gender' => 'Male']);
        DB::table('club_member_subscriptions')->insert(['tenant_id' => $club->id, 'user_id' => $member->id, 'package_id' => $package->id, 'status' => 'active',
            'start_date' => now()->toDateString(), 'end_date' => now()->addMonth()->toDateString(), 'payment_status' => 'paid', 'created_at' => now(), 'updated_at' => now()]);
        $link = ActivityPackage::where('activity_id', $activity->id)->firstOrFail();

        return [$owner, $club, $activity, $link, $member];
    }

    public function test_a_rank_is_set_validated_and_kept_in_history(): void
    {
        [$owner, $club, $activity, $link, $member] = $this->dojo();
        $url = route('admin.club.ranks.set', [$club->slug, $link->uuid, $member->uuid]);

        $this->actingAs($owner)->postJson($url, ['belt' => 'white', 'grade' => '2 stripes', 'since' => now()->subYear()->toDateString()])
            ->assertOk()->assertJsonPath('member.rank.belt', 'white');
        $this->actingAs($owner)->postJson($url, ['belt' => 'blue', 'grade' => 'No stripes', 'since' => now()->toDateString()])
            ->assertOk()->assertJsonPath('member.rank.belt', 'blue');

        $this->assertSame('blue', MemberRank::where('activity_id', $activity->id)->where('user_id', $member->id)->value('belt'));
        $this->assertSame(2, RankPromotion::where('user_id', $member->id)->count());
        $this->assertSame('white', RankPromotion::where('user_id', $member->id)->orderByDesc('id')->value('from_belt'));

        $this->actingAs($owner)->getJson(route('admin.club.ranks.history', [$club->slug, $link->uuid, $member->uuid]))
            ->assertOk()->assertJsonCount(2, 'history');

        // Not on the ladder, a grade from another belt, a date in the future: refused.
        $this->actingAs($owner)->postJson($url, ['belt' => 'gold', 'since' => now()->toDateString()])->assertStatus(422);
        $this->actingAs($owner)->postJson($url, ['belt' => 'blue', 'grade' => '9th kyu', 'since' => now()->toDateString()])->assertStatus(422);
        $this->actingAs($owner)->postJson($url, ['belt' => 'blue', 'since' => now()->addDay()->toDateString()])->assertStatus(422);
    }

    public function test_another_club_and_a_stranger_cannot_touch_ranks(): void
    {
        [, $club, , $link, $member] = $this->dojo();
        [$otherOwner, $otherClub] = $this->dojo();
        $payload = ['belt' => 'blue', 'since' => now()->toDateString()];

        // The other club's admin, through their own club's URL, with this club's link: not found.
        $this->actingAs($otherOwner)->postJson(route('admin.club.ranks.set', [$otherClub->slug, $link->uuid, $member->uuid]), $payload)->assertNotFound();
        // A signed-in stranger through this club's URL: forbidden.
        $this->actingAs($this->createUser())->postJson(route('admin.club.ranks.set', [$club->slug, $link->uuid, $member->uuid]), $payload)->assertForbidden();
        // Someone who does not train in the activity cannot be given a rank in it.
        [$owner] = [$club->owner];
        $this->actingAs(\App\Members\Models\User::find($club->owner_user_id))
            ->postJson(route('admin.club.ranks.set', [$club->slug, $link->uuid, $this->createUser()->uuid]), $payload)->assertNotFound();
        $this->assertSame(0, MemberRank::count());
    }

    public function test_the_activitys_coach_may_set_ranks(): void
    {
        [, $club, $activity, $link, $member] = $this->dojo();
        $coach = $this->createUser();
        $ci = DB::table('club_instructors')->insertGetId(['tenant_id' => $club->id, 'user_id' => $coach->id, 'role' => 'Coach', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('club_activity_instructor')->insert(['activity_id' => $activity->id, 'instructor_id' => $ci, 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($coach)->postJson(route('admin.club.ranks.set', [$club->slug, $link->uuid, $member->uuid]), ['belt' => 'white', 'since' => now()->toDateString()])->assertOk();
        // …but only an admin may say which sport the activity is.
        $this->actingAs($coach)->putJson(route('admin.club.ranks.package', [$club->slug, $link->uuid]), ['sport' => null])->assertForbidden();
    }

    public function test_the_pages_render_on_desktop_and_mobile_and_are_off_when_switched_off(): void
    {
        [$owner, $club] = $this->dojo();
        $this->actingAs($owner)->get(route('admin.club.ranks', $club->slug))->assertOk()->assertSee(__('ranks::messages.title'));
        $this->actingAs($owner)->withHeaders(['User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Mobile/15E148'])
            ->get(route('admin.club.ranks', $club->slug))->assertOk()->assertSee(__('ranks::messages.title'));

        config(['ranks.enabled' => false]);
        $this->actingAs($owner)->getJson(route('admin.club.ranks', $club->slug))->assertNotFound();
    }
}
