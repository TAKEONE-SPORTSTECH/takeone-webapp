<?php

namespace Tests\Feature\Ranks;

use App\Clubs\Models\ClubActivity;
use App\Ranks\Models\ActivityPackage;
use App\Ranks\Ranks;
use App\Ranks\Support\ActivityPackages;
use Tests\TestCase;

/**
 * Step 1 of ranks & grading: an activity is linked to its sport package, and
 * only a ranked activity brings ranks into a club.
 */
class ActivityPackagesTest extends TestCase
{
    public function test_names_match_to_the_right_package(): void
    {
        $m = app(ActivityPackages::class);
        $this->assertSame(['taekwondo', 'wt'], $m->match('TaeKwonDo WT'));
        $this->assertSame(['taekwondo', 'wt'], $m->match('Kids TKD'));
        $this->assertSame(['karate', 'shotokan'], $m->match('Karate', 'Shotokan'));
        $this->assertSame(['karate', 'wkf'], $m->match('Karate Kumite'));
        $this->assertSame(['bjj', 'ibjjf'], $m->match('Brazilian Jiu-Jitsu'));
        $this->assertSame(['bjj', 'ibjjf'], $m->match('Jiu Jitsu'));
        $this->assertNull($m->match('Football'));
        $this->assertNull($m->match('Boxing'));
        $this->assertNull($m->match(''));
    }

    public function test_an_activity_is_linked_when_saved_and_a_clubs_choice_is_kept(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner);

        $this->actingAs($owner)->postJson(route('admin.club.activities.store', $club->slug), ['name' => 'Taekwondo Kids', 'duration_minutes' => 60])->assertOk();
        $a = ClubActivity::where('tenant_id', $club->id)->where('name', 'like', '%Taekwondo%')->firstOrFail();
        $this->assertSame('taekwondo', ActivityPackage::where('activity_id', $a->id)->value('sport'));

        // The club says it is not a ranked sport after all — a rename must not undo that.
        app(ActivityPackages::class)->choose($a, null, null);
        $this->actingAs($owner)->putJson(route('admin.club.activities.update', [$club->slug, $a->id]), ['name' => 'Taekwondo Juniors', 'duration_minutes' => 60])->assertOk();
        $this->assertSame('club', ActivityPackage::where('activity_id', $a->id)->value('matched_by'));
        $this->assertNull(app(ActivityPackages::class)->ruleBook($a->fresh()));
    }

    public function test_only_a_club_with_a_ranked_activity_gets_the_menu_entry_and_only_when_switched_on(): void
    {
        $owner = $this->createUser();
        $football = $this->createClub($owner, ['slug' => 'fc-'.uniqid()]);
        $dojo = $this->createClub($owner, ['slug' => 'dojo-'.uniqid()]);
        $f = ClubActivity::create(['tenant_id' => $football->id, 'name' => 'Football']);
        $k = ClubActivity::create(['tenant_id' => $dojo->id, 'name' => 'Karate']);
        app(ActivityPackages::class)->autoLink($f);
        app(ActivityPackages::class)->autoLink($k);

        config(['ranks.enabled' => true]);
        $this->assertSame([], app(Ranks::class)->clubAdminNav($football, 'desktop'));
        $this->assertNotEmpty(app(Ranks::class)->clubAdminNav($dojo, 'desktop'));

        config(['ranks.enabled' => false]);
        $this->assertSame([], app(Ranks::class)->clubAdminNav($dojo, 'desktop'));
    }
}
