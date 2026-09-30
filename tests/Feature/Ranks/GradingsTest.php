<?php

namespace Tests\Feature\Ranks;

use App\Clubs\Models\ClubActivity;
use App\Clubs\Models\ClubPackage;
use App\Ranks\Models\ActivityPackage;
use App\Ranks\Models\Grading;
use App\Ranks\Models\MemberRank;
use App\Ranks\Models\RankPromotion;
use App\Ranks\Support\ActivityPackages;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Step 4: a grading promotes everyone who passed — once. */
class GradingsTest extends TestCase
{
    public function test_plan_record_and_finish_a_grading(): void
    {
        config(['ranks.enabled' => true]);
        $owner = $this->createUser();
        $club = $this->createClub($owner, ['slug' => 'dojo-'.uniqid()]);
        $activity = ClubActivity::create(['tenant_id' => $club->id, 'name' => 'Karate']);
        app(ActivityPackages::class)->autoLink($activity);
        $package = ClubPackage::create(['tenant_id' => $club->id, 'name' => 'Kids', 'price' => 20, 'duration_months' => 1, 'is_active' => true]);
        DB::table('club_package_activities')->insert(['package_id' => $package->id, 'activity_id' => $activity->id, 'created_at' => now(), 'updated_at' => now()]);
        $a = $this->createUser(); $b = $this->createUser(); $outsider = $this->createUser();
        foreach ([$a, $b] as $m) {
            DB::table('club_member_subscriptions')->insert(['tenant_id' => $club->id, 'user_id' => $m->id, 'package_id' => $package->id, 'status' => 'active',
                'start_date' => now()->toDateString(), 'end_date' => now()->addMonth()->toDateString(), 'payment_status' => 'paid', 'created_at' => now(), 'updated_at' => now()]);
        }
        $link = ActivityPackage::where('activity_id', $activity->id)->firstOrFail();
        $base = [$club->slug, $link->uuid];

        $res = $this->actingAs($owner)->postJson(route('admin.club.ranks.gradings.store', $base), [
            'title' => 'Spring grading', 'held_on' => now()->toDateString(),
            'candidates' => [['uuid' => $a->uuid, 'belt' => 'yellow'], ['uuid' => $b->uuid, 'belt' => 'yellow'], ['uuid' => $outsider->uuid, 'belt' => 'yellow'], ['uuid' => $a->uuid, 'belt' => 'gold']],
        ])->assertOk();
        $g = Grading::where('uuid', $res->json('grading.uuid'))->firstOrFail();
        $this->assertSame(2, $g->candidates()->count());   // the outsider and the made-up belt are dropped

        $url = route('admin.club.ranks.gradings.record', [...$base, $g->uuid]);
        $this->actingAs($owner)->putJson($url, ['results' => [$a->uuid => 'passed', $b->uuid => 'failed'], 'finish' => true])->assertOk()->assertJsonPath('grading.status', 'done');

        $this->assertSame('yellow', MemberRank::where('user_id', $a->id)->value('belt'));
        $this->assertNull(MemberRank::where('user_id', $b->id)->first());
        $this->assertSame($g->id, RankPromotion::where('user_id', $a->id)->value('grading_id'));

        // Finishing twice never promotes twice; a finished grading cannot be changed or removed.
        $this->actingAs($owner)->putJson($url, ['results' => [$b->uuid => 'passed'], 'finish' => true])->assertStatus(422);
        $this->actingAs($owner)->deleteJson($url)->assertStatus(422);
        $this->assertSame(1, RankPromotion::count());

        // Another club cannot read it.
        $other = $this->createClub($this->createUser(), ['slug' => 'other-'.uniqid()]);
        $this->actingAs(\App\Members\Models\User::find($other->owner_user_id))
            ->getJson(route('admin.club.ranks.gradings.show', [$other->slug, $link->uuid, $g->uuid]))->assertNotFound();
    }
}
