<?php

namespace Tests\Feature\Ranks;

use App\Clubs\Models\ClubActivity;
use App\Clubs\Models\ClubPackage;
use App\Members\Models\UserNotification;
use App\Ranks\Models\ActivityPackage;
use App\Ranks\Models\Grading;
use App\Ranks\Support\ActivityPackages;
use App\Ranks\Support\Gradings;
use App\Ranks\Support\MemberRanks;
use App\Ranks\Support\RankNotices;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Step 5: reminders and notifications — each sent once, to the right people. */
class RankNoticesTest extends TestCase
{
    public function test_notices_go_to_the_member_their_guardian_and_the_staff_once(): void
    {
        config(['ranks.enabled' => true]);
        $owner = $this->createUser();
        $club = $this->createClub($owner, ['slug' => 'dojo-'.uniqid()]);
        $activity = ClubActivity::create(['tenant_id' => $club->id, 'name' => 'Karate']);
        app(ActivityPackages::class)->autoLink($activity);
        $package = ClubPackage::create(['tenant_id' => $club->id, 'name' => 'Kids', 'price' => 20, 'duration_months' => 1, 'is_active' => true]);
        $pa = DB::table('club_package_activities')->insertGetId(['package_id' => $package->id, 'activity_id' => $activity->id, 'created_at' => now(), 'updated_at' => now()]);
        $child = $this->createUser(['birthdate' => now()->subYears(10)->toDateString()]);
        $parent = $this->createUser();
        DB::table('user_relationships')->insert(['guardian_user_id' => $parent->id, 'dependent_user_id' => $child->id, 'relationship_type' => 'parent', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('club_member_subscriptions')->insert(['tenant_id' => $club->id, 'user_id' => $child->id, 'package_id' => $package->id, 'status' => 'active',
            'start_date' => now()->toDateString(), 'end_date' => now()->addMonth()->toDateString(), 'payment_status' => 'paid', 'created_at' => now(), 'updated_at' => now()]);
        $link = ActivityPackage::where('activity_id', $activity->id)->firstOrFail();
        $count = fn ($user, $type) => UserNotification::where('user_id', $user->id)->where('type', $type)->count();

        // Promotion → the child and the parent are told; the coach who did it is not.
        app(MemberRanks::class)->set($link, $child, 'white', null, now()->subMonths(3)->toDateString(), $owner);
        $this->assertSame(1, $count($child, 'rank_promoted'));
        $this->assertSame(1, $count($parent, 'rank_promoted'));
        $this->assertSame(0, $count($owner, 'rank_promoted'));

        // Requirements for the next belt: 5 classes. 3 attended → "2 classes to go", once.
        $ladder = collect(app(MemberRanks::class)->ladder($link))->pluck('value')->all();
        $next = $ladder[array_search('white', $ladder, true) + 1];
        DB::table('grading_requirements')->insert(['activity_id' => $activity->id, 'belt' => $next, 'min_classes' => 5, 'created_at' => now(), 'updated_at' => now()]);
        $attend = fn ($days) => DB::table('class_attendances')->insert(['package_activity_id' => $pa, 'slot_day' => 'mon', 'slot_start' => '17:00',
            'date' => now()->subDays($days)->toDateString(), 'user_id' => $child->id, 'marked_by' => $owner->id, 'created_at' => now(), 'updated_at' => now()]);
        foreach ([3, 10, 17, 24] as $d) { $attend($d); }
        app(RankNotices::class)->daily();
        app(RankNotices::class)->daily();
        $this->assertSame(1, $count($child, 'rank_close'));

        // 5 attended → ready: member once, staff digest once — however many times the pass runs.
        $attend(31);
        app(RankNotices::class)->daily();
        app(RankNotices::class)->daily();
        $this->assertSame(1, $count($child, 'rank_eligible'));
        $this->assertSame(1, $count($parent, 'rank_eligible'));
        $this->assertSame(1, $count($owner, 'rank_staff_ready'));

        // A grading tomorrow: candidates are invited now, reminded the day before, and staff too.
        $g = app(Gradings::class)->create($link, 'Autumn grading', now()->addDay()->toDateString(), [['uuid' => $child->uuid, 'belt' => $next]], $owner);
        $this->assertSame(1, $count($child, 'rank_invited'));
        app(RankNotices::class)->daily();
        app(RankNotices::class)->daily();
        $this->assertSame(1, $count($child, 'rank_eve'));
        $this->assertSame(1, $count($owner, 'rank_staff_eve'));

        // Held yesterday and still not recorded → the staff are nudged, once.
        Grading::whereKey($g->id)->update(['held_on' => now()->subDay()->toDateString()]);
        app(RankNotices::class)->daily();
        app(RankNotices::class)->daily();
        $this->assertSame(1, $count($owner, 'rank_staff_unrecorded'));
    }

    public function test_the_command_does_nothing_while_switched_off(): void
    {
        config(['ranks.enabled' => false]);
        $this->artisan('ranks:reminders')->expectsOutputToContain('switched off')->assertSuccessful();
    }
}
