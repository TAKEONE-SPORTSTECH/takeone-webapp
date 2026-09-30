<?php

namespace Tests\Feature\Ranks;

use App\Clubs\Models\ClubActivity;
use App\Clubs\Models\ClubPackage;
use App\Ranks\Models\ActivityPackage;
use App\Ranks\Ranks;
use App\Ranks\Support\ActivityPackages;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Step 3: eligibility for the next belt, from the club's requirements and real attendance. */
class EligibilityTest extends TestCase
{
    public function test_status_follows_requirements_and_attendance(): void
    {
        config(['ranks.enabled' => true]);
        $owner = $this->createUser();
        $club = $this->createClub($owner, ['slug' => 'dojo-'.uniqid()]);
        $activity = ClubActivity::create(['tenant_id' => $club->id, 'name' => 'Brazilian Jiu-Jitsu']);
        app(ActivityPackages::class)->autoLink($activity);
        $package = ClubPackage::create(['tenant_id' => $club->id, 'name' => 'Adults', 'price' => 30, 'duration_months' => 1, 'is_active' => true]);
        $pa = DB::table('club_package_activities')->insertGetId(['package_id' => $package->id, 'activity_id' => $activity->id, 'created_at' => now(), 'updated_at' => now()]);
        $member = $this->createUser(['gender' => 'Male', 'birthdate' => now()->subYears(25)->toDateString()]);
        DB::table('club_member_subscriptions')->insert(['tenant_id' => $club->id, 'user_id' => $member->id, 'package_id' => $package->id, 'status' => 'active',
            'start_date' => now()->toDateString(), 'end_date' => now()->addMonth()->toDateString(), 'payment_status' => 'paid', 'created_at' => now(), 'updated_at' => now()]);
        $link = ActivityPackage::where('activity_id', $activity->id)->firstOrFail();
        $base = [$club->slug, $link->uuid];

        // White belt since 13 months ago.
        $this->actingAs($owner)->postJson(route('admin.club.ranks.set', [...$base, $member->uuid]), ['belt' => 'white', 'since' => now()->subMonths(13)->toDateString()])->assertOk();

        // The next belt on the IBJJF ladder after adult white is grey-white only for kids; ask the ladder.
        $ladder = collect(app(\App\Ranks\Support\MemberRanks::class)->ladder($link))->pluck('value')->all();
        $next = $ladder[array_search('white', $ladder, true) + 1];

        // No requirements yet: nothing to measure.
        $row = fn () => collect($this->actingAs($owner)->get(route('admin.club.ranks', $club->slug))->viewData('ranksPayload')['activities'])->firstWhere('uuid', $link->uuid)['members'][0];
        $this->assertSame('no_rules', $row()['status']);

        // 10 classes and 12 months: 4 classes attended → not yet.
        $this->actingAs($owner)->putJson(route('admin.club.ranks.requirements', $base), ['rows' => [$next => ['min_classes' => 10, 'min_months' => 12]]])->assertOk();
        foreach (range(1, 4) as $i) {
            DB::table('class_attendances')->insert(['package_activity_id' => $pa, 'slot_day' => 'mon', 'slot_start' => '18:00', 'date' => now()->subDays($i * 7)->toDateString(), 'user_id' => $member->id, 'marked_by' => $owner->id, 'created_at' => now(), 'updated_at' => now()]);
        }
        $this->assertSame('not_yet', $row()['status']);
        $this->assertSame([4, 10], $row()['progress']['classes']);

        // 9 of 10 → nearly; 10 → eligible, and the club menu badge counts it.
        foreach (range(5, 9) as $i) {
            DB::table('class_attendances')->insert(['package_activity_id' => $pa, 'slot_day' => 'mon', 'slot_start' => '18:00', 'date' => now()->subDays($i * 7)->toDateString(), 'user_id' => $member->id, 'marked_by' => $owner->id, 'created_at' => now(), 'updated_at' => now()]);
        }
        $this->assertSame('nearly', $row()['status']);
        DB::table('class_attendances')->insert(['package_activity_id' => $pa, 'slot_day' => 'mon', 'slot_start' => '18:00', 'date' => now()->subDays(70)->toDateString(), 'user_id' => $member->id, 'marked_by' => $owner->id, 'created_at' => now(), 'updated_at' => now()]);
        $this->assertSame('eligible', $row()['status']);
        Ranks::forgetBadge($club);
        $this->assertSame(1, app(Ranks::class)->clubAdminNav($club, 'desktop')[0]['badge'] ?? 0);

        // Requirements for a belt that is not on the ladder are ignored; bad numbers refused.
        $this->actingAs($owner)->putJson(route('admin.club.ranks.requirements', $base), ['rows' => ['gold' => ['min_classes' => 5]]])->assertOk();
        $this->assertDatabaseMissing('grading_requirements', ['belt' => 'gold']);
        $this->actingAs($owner)->putJson(route('admin.club.ranks.requirements', $base), ['rows' => [$next => ['min_classes' => -3]]])->assertStatus(422);

        // A stranger cannot change another club's requirements.
        $this->actingAs($this->createUser())->putJson(route('admin.club.ranks.requirements', $base), ['rows' => []])->assertForbidden();
    }

    public function test_the_member_sees_their_belt_only_when_switched_on(): void
    {
        config(['ranks.enabled' => true]);
        $owner = $this->createUser();
        $club = $this->createClub($owner, ['slug' => 'dojo-'.uniqid()]);
        $activity = ClubActivity::create(['tenant_id' => $club->id, 'name' => 'Karate']);
        app(ActivityPackages::class)->autoLink($activity);
        $member = $this->createUser();
        $link = ActivityPackage::where('activity_id', $activity->id)->firstOrFail();
        app(\App\Ranks\Support\MemberRanks::class)->set($link, $member, 'yellow', null, now()->toDateString(), $owner);

        $html = \Illuminate\Support\Facades\Blade::render('<x-member-ranks :user="$u" />', ['u' => $member]);
        $this->assertStringContainsString(__('ranks::messages.my_belts'), $html);
        $this->assertStringContainsString('Karate', $html);

        config(['ranks.enabled' => false]);
        $this->assertStringNotContainsString(__('ranks::messages.my_belts'), \Illuminate\Support\Facades\Blade::render('<x-member-ranks :user="$u" />', ['u' => $member]));

        // Someone who trains in nothing with belts sees nothing either.
        config(['ranks.enabled' => true]);
        $this->assertStringNotContainsString(__('ranks::messages.my_belts'), \Illuminate\Support\Facades\Blade::render('<x-member-ranks :user="$u" />', ['u' => $this->createUser()]));
    }
}
