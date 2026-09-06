<?php

namespace Tests\Feature;

use App\Mcp\Tools\AddCertificationTool;
use App\Mcp\Tools\AddWorkHistoryTool;
use App\Mcp\Tools\ClubFinancialsTool;
use App\Mcp\Tools\ClubStaffTool;
use App\Mcp\Tools\EnrollMembersTool;
use App\Mcp\Tools\GetMemberTool;
use App\Mcp\Tools\ListActivityCatalogTool;
use App\Mcp\Tools\ListClubsTool;
use App\Mcp\Tools\ManageMemberPhotoTool;
use App\Mcp\Tools\RecordTransactionTool;
use App\Mcp\Tools\SearchPeopleTool;
use App\Mcp\Tools\WhoAmITool;
use App\Clubs\Models\ClubInstructor;
use App\Clubs\Models\ClubPackage;
use App\Clubs\Models\ClubTransaction;
use App\Members\Models\Membership;
use Laravel\Mcp\Request;
use Tests\TestCase;

/**
 * The MCP tools run AS the acting user and must enforce the same tenant scope
 * and authorization the web app does. These tests exercise that directly.
 */
class McpServerTest extends TestCase
{
    /** Run a tool and decode its JSON text content. */
    private function callTool(string $tool, array $args = []): array
    {
        $text = (string) (new $tool)->handle(new Request($args))->content();
        $decoded = json_decode($text, true);

        // Error tools return a plain string, not JSON — wrap it for asserting.
        return is_array($decoded) ? $decoded : ['error' => $text];
    }

    public function test_unauthenticated_call_is_rejected(): void
    {
        config(['takeone-mcp.stdio_user_id' => null]);

        $result = $this->callTool(WhoAmITool::class);

        $this->assertStringContainsString('Not authenticated', $result['error']);
    }

    public function test_activity_catalog_requires_authentication(): void
    {
        config(['takeone-mcp.stdio_user_id' => null]);

        $result = $this->callTool(ListActivityCatalogTool::class);

        $this->assertStringContainsString('Not authenticated', $result['error']);
    }

    public function test_activity_catalog_lists_the_global_directory_with_search(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        \App\Models\ActivityCatalog::create([
            'name' => 'Taekwondo',
            'videos' => [
                ['id' => 'aqz-KE-bpKQ', 'title' => 'Poomsae demo', 'source' => 'WT'],
                ['id' => 'bad-id', 'title' => 'should be dropped'],     // invalid id → filtered out
            ],
        ]);
        \App\Models\ActivityCatalog::create(['name' => 'Underwater Basket Weaving']);

        $all = $this->callTool(ListActivityCatalogTool::class);
        $this->assertGreaterThanOrEqual(2, $all['total']);

        $filtered = $this->callTool(ListActivityCatalogTool::class, ['search' => 'taekwon']);
        $names = collect($filtered['activities'])->pluck('name');
        $this->assertTrue($names->contains('Taekwondo'));
        $this->assertFalse($names->contains('Underwater Basket Weaving'));

        // Curated videos surface through the MCP, sanitized (invalid ids dropped).
        $tkd = collect($filtered['activities'])->firstWhere('name', 'Taekwondo');
        $this->assertCount(1, $tkd['videos']);
        $this->assertSame('aqz-KE-bpKQ', $tkd['videos'][0]['id']);
        $this->assertSame('Poomsae demo', $tkd['videos'][0]['title']);
    }

    public function test_who_am_i_reports_identity_and_roles(): void
    {
        $user = $this->createUser(['full_name' => 'Mia Coach']);
        $this->makeSuperAdmin($user);
        $this->actingAs($user);

        $result = $this->callTool(WhoAmITool::class);

        $this->assertSame($user->id, $result['id']);
        $this->assertTrue($result['is_super_admin']);
    }

    public function test_list_clubs_is_scoped_to_the_users_clubs(): void
    {
        $ownerA = $this->createUser();
        $ownerB = $this->createUser();
        $clubA = $this->createClub($ownerA, ['club_name' => 'Alpha Club', 'slug' => 'alpha-club']);
        $clubB = $this->createClub($ownerB, ['club_name' => 'Beta Club', 'slug' => 'beta-club']);

        // A plain member of only clubA should not see clubB.
        $member = $this->createUser();
        Membership::create(['tenant_id' => $clubA->id, 'user_id' => $member->id, 'status' => 'active']);
        $this->actingAs($member);

        $slugs = collect($this->callTool(ListClubsTool::class)['clubs'])->pluck('slug');

        $this->assertTrue($slugs->contains('alpha-club'));
        $this->assertFalse($slugs->contains('beta-club'));
    }

    public function test_financials_require_admin_access(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner, ['slug' => 'money-club']);

        $member = $this->createUser();
        Membership::create(['tenant_id' => $club->id, 'user_id' => $member->id, 'status' => 'active']);
        $this->actingAs($member);

        $result = $this->callTool(ClubFinancialsTool::class, ['club' => 'money-club']);

        $this->assertStringContainsString('club admins', $result['error']);
    }

    public function test_club_staff_lists_scoped_staff_for_admins(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner, ['slug' => 'staff-club']);
        $this->makeClubAdmin($owner, $club);

        $secretaryUser = $this->createUser(['full_name' => 'Sam Secretary']);
        ClubInstructor::create([
            'tenant_id' => $club->id,
            'user_id' => $secretaryUser->id,
            'role' => 'Front Desk',
            'staff_type' => 'secretary',
            'compensation_type' => ClubInstructor::COMPENSATION_PAID,
            'wage_amount' => 300,
            'wage_period' => 'monthly',
        ]);

        $this->actingAs($owner);
        $result = $this->callTool(ClubStaffTool::class, ['club' => 'staff-club']);

        $this->assertSame(1, $result['count']);
        $this->assertSame('secretary', $result['staff'][0]['staff_type']);
        $this->assertEquals(300.0, $result['staff'][0]['monthly_wage_cost']);
    }

    public function test_club_staff_denies_non_admin(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner, ['slug' => 'staff-club-2']);

        $member = $this->createUser();
        Membership::create(['tenant_id' => $club->id, 'user_id' => $member->id, 'status' => 'active']);
        $this->actingAs($member);

        $result = $this->callTool(ClubStaffTool::class, ['club' => 'staff-club-2']);

        $this->assertStringContainsString('club admins', $result['error']);
    }

    public function test_admin_can_record_a_transaction(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner, ['slug' => 'txn-club']);
        $this->makeClubAdmin($owner, $club);
        $this->actingAs($owner);

        $result = $this->callTool(RecordTransactionTool::class, [
            'club' => 'txn-club',
            'type' => 'income',
            'amount' => 42.0,
            'category' => 'test',
        ]);

        $this->assertTrue($result['success']);
        $this->assertDatabaseHas('club_transactions', [
            'tenant_id' => $club->id,
            'type' => 'income',
            'category' => 'test',
        ]);
    }

    public function test_writes_are_blocked_when_disabled(): void
    {
        config(['takeone-mcp.allow_writes' => false]);

        $owner = $this->createUser();
        $club = $this->createClub($owner, ['slug' => 'ro-club']);
        $this->makeClubAdmin($owner, $club);
        $this->actingAs($owner);

        $result = $this->callTool(RecordTransactionTool::class, [
            'club' => 'ro-club',
            'type' => 'income',
            'amount' => 10.0,
        ]);

        $this->assertStringContainsString('Write operations are disabled', $result['error']);
        $this->assertSame(0, ClubTransaction::count());
    }

    public function test_admin_can_batch_enroll_active_members_into_a_package(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner, ['slug' => 'batch-club']);
        $this->makeClubAdmin($owner, $club);
        $this->actingAs($owner);

        $package = ClubPackage::create([
            'tenant_id' => $club->id,
            'name' => 'Adult Membership',
            'price' => 50,
            'duration_months' => 1,
            'is_active' => true,
        ]);

        $memberA = $this->createUser();
        $memberB = $this->createUser();
        Membership::create(['tenant_id' => $club->id, 'user_id' => $memberA->id, 'status' => 'active']);
        Membership::create(['tenant_id' => $club->id, 'user_id' => $memberB->id, 'status' => 'active']);

        // Not a member of this club — should be skipped.
        $outsider = $this->createUser();

        $result = $this->callTool(EnrollMembersTool::class, [
            'club' => 'batch-club',
            'member_ids' => [$memberA->id, $memberB->id, $outsider->id],
            'package_id' => $package->id,
            'start_date' => '2026-01-01',
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame(2, $result['enrolled_count']);
        $this->assertCount(1, $result['skipped']);
        $this->assertSame($outsider->id, $result['skipped'][0]['user_id']);

        $this->assertDatabaseHas('club_member_subscriptions', [
            'tenant_id' => $club->id,
            'user_id' => $memberA->id,
            'package_id' => $package->id,
            'status' => 'active',
            'payment_status' => 'paid',
            'amount_due' => 0,
        ]);
    }

    public function test_batch_enroll_requires_admin_access(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner, ['slug' => 'batch-ro-club']);

        $package = ClubPackage::create([
            'tenant_id' => $club->id,
            'name' => 'Adult Membership',
            'price' => 50,
            'duration_months' => 1,
            'is_active' => true,
        ]);

        $member = $this->createUser();
        Membership::create(['tenant_id' => $club->id, 'user_id' => $member->id, 'status' => 'active']);
        $this->actingAs($member);

        $result = $this->callTool(EnrollMembersTool::class, [
            'club' => 'batch-ro-club',
            'member_ids' => [$member->id],
            'package_id' => $package->id,
        ]);

        $this->assertStringContainsString('club admins', $result['error']);
    }

    public function test_search_people_is_scoped_to_confirmed_clubmates(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner, ['slug' => 'people-club']);

        $me = $this->createUser();
        \App\Models\ClubMemberSubscription::create([
            'tenant_id' => $club->id, 'user_id' => $me->id, 'type' => 'regular',
            'status' => 'active', 'payment_status' => 'paid',
            'amount_paid' => 0, 'amount_due' => 0, 'start_date' => now(), 'end_date' => now()->addYear(),
        ]);

        $confirmedMate = $this->createUser(['full_name' => 'Confirmed Mate']);
        \App\Models\ClubMemberSubscription::create([
            'tenant_id' => $club->id, 'user_id' => $confirmedMate->id, 'type' => 'regular',
            'status' => 'active', 'payment_status' => 'paid',
            'amount_paid' => 0, 'amount_due' => 0, 'start_date' => now(), 'end_date' => now()->addYear(),
        ]);

        // Same club, but membership never confirmed by the owner — must not surface.
        $pendingMate = $this->createUser(['full_name' => 'Pending Mate']);
        \App\Models\ClubMemberSubscription::create([
            'tenant_id' => $club->id, 'user_id' => $pendingMate->id, 'type' => 'regular',
            'status' => 'pending', 'payment_status' => 'pending_approval',
            'amount_paid' => 0, 'amount_due' => 0, 'start_date' => now(), 'end_date' => now()->addYear(),
        ]);

        // Discoverable, confirmed member of a DIFFERENT club — must not surface.
        $otherClubOwner = $this->createUser();
        $otherClub = $this->createClub($otherClubOwner, ['slug' => 'other-people-club']);
        $stranger = $this->createUser(['full_name' => 'Stranger Person']);
        \App\Models\ClubMemberSubscription::create([
            'tenant_id' => $otherClub->id, 'user_id' => $stranger->id, 'type' => 'regular',
            'status' => 'active', 'payment_status' => 'paid',
            'amount_paid' => 0, 'amount_due' => 0, 'start_date' => now(), 'end_date' => now()->addYear(),
        ]);

        $this->actingAs($me);
        $names = collect($this->callTool(SearchPeopleTool::class, ['query' => 'Mate'])['people'])->pluck('name');

        $this->assertTrue($names->contains('Confirmed Mate'));
        $this->assertFalse($names->contains('Pending Mate'));
        $this->assertFalse($names->contains('Stranger Person'));
    }

    public function test_search_people_returns_empty_without_a_confirmed_club(): void
    {
        $me = $this->createUser();
        $this->actingAs($me);

        $result = $this->callTool(SearchPeopleTool::class, ['query' => 'anyone']);

        $this->assertSame(0, $result['count']);
        $this->assertEmpty($result['people']);
    }

    public function test_member_can_add_own_certification_and_it_surfaces_in_get_member(): void
    {
        $me = $this->createUser();
        $this->actingAs($me);

        $result = $this->callTool(AddCertificationTool::class, [
            'member' => $me->uuid,
            'title' => 'First Aid Level 2',
            'issuer' => 'Red Crescent',
            'issue_date' => '2025-01-15',
            'credential_url' => 'https://example.org/verify/abc',
        ]);

        $this->assertTrue($result['success']);
        $this->assertDatabaseHas('member_certifications', ['user_id' => $me->id, 'title' => 'First Aid Level 2']);

        $member = $this->callTool(GetMemberTool::class, ['member' => $me->uuid]);
        $titles = collect($member['certifications'])->pluck('title');
        $this->assertTrue($titles->contains('First Aid Level 2'));
    }

    public function test_add_certification_denies_unrelated_user(): void
    {
        $owner = $this->createUser();
        $rando = $this->createUser();
        $this->actingAs($rando);

        $result = $this->callTool(AddCertificationTool::class, [
            'member' => $owner->uuid,
            'title' => 'Sneaky cert',
        ]);

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('not authorized', $result['error']);
        $this->assertDatabaseCount('member_certifications', 0);
    }

    public function test_member_can_add_own_work_history_and_it_surfaces_in_get_member(): void
    {
        $me = $this->createUser();
        $this->actingAs($me);

        $result = $this->callTool(AddWorkHistoryTool::class, [
            'member' => $me->uuid,
            'title' => 'Head Coach',
            'organization' => 'Manama TKD',
            'employment_type' => 'Full-time',
            'start_date' => '2022-03-01',
        ]);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['current']);
        $this->assertDatabaseHas('member_work_history', ['user_id' => $me->id, 'title' => 'Head Coach', 'end_date' => null]);

        $member = $this->callTool(GetMemberTool::class, ['member' => $me->uuid]);
        $this->assertTrue(collect($member['work_history'])->pluck('title')->contains('Head Coach'));
    }

    public function test_add_work_history_denies_unrelated_user(): void
    {
        $owner = $this->createUser();
        $rando = $this->createUser();
        $this->actingAs($rando);

        $result = $this->callTool(AddWorkHistoryTool::class, [
            'member' => $owner->uuid,
            'title' => 'Ghost role',
            'organization' => 'Nowhere',
            'start_date' => '2020-01-01',
        ]);

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('not authorized', $result['error']);
        $this->assertDatabaseCount('member_work_history', 0);
    }

    public function test_add_certification_blocked_when_writes_disabled(): void
    {
        config(['takeone-mcp.allow_writes' => false]);
        $me = $this->createUser();
        $this->actingAs($me);

        $result = $this->callTool(AddCertificationTool::class, [
            'member' => $me->uuid,
            'title' => 'Blocked cert',
        ]);

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('disabled', $result['error']);
        $this->assertDatabaseCount('member_certifications', 0);
    }

    /* ---------------- Event entry ---------------- */

    public function test_enter_event_athletes_lists_the_roster_then_enters_them(): void
    {
        $coach = $this->createUser();
        $club = $this->createClub($coach, ['country' => 'BH']);
        $coach->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);

        $event = \App\Models\ClubEvent::create([
            'tenant_id' => $club->id, 'created_by' => $coach->id, 'title' => 'Spring Open',
            'event_type' => 'championship', 'sport' => 'taekwondo', 'scope' => 'internal',
            'date' => now()->addWeeks(2)->toDateString(), 'start_time' => '09:00',
            'status' => 'active', 'is_archived' => false,
        ]);
        \App\Models\EventCategory::create(['event_id' => $event->id, 'name' => 'Senior Men -58 kg', 'sort_order' => 1]);

        $athlete = $this->createUser(['full_name' => 'Ali', 'gender' => 'Male', 'birthdate' => now()->subYears(25)->toDateString()]);
        $athlete->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);
        \App\Members\Models\HealthRecord::create(['user_id' => $athlete->id, 'weight' => 57, 'recorded_at' => now()]);

        $this->actingAs($coach->fresh());

        // No ids → preview only, nothing written.
        $preview = $this->callTool(\App\Mcp\Tools\EnterEventAthletesTool::class, ['event' => $event->uuid]);
        $this->assertNotEmpty($preview['roster']);
        $this->assertDatabaseMissing('club_event_registrations', ['event_id' => $event->id]);

        $result = $this->callTool(\App\Mcp\Tools\EnterEventAthletesTool::class, [
            'event' => $event->uuid,
            'athlete_ids' => [$athlete->id],
        ]);

        $this->assertCount(1, $result['entered']);
        $this->assertSame('Senior Men -58 kg', $result['entered'][0]['division']);
    }

    public function test_enter_event_athletes_denies_someone_who_runs_no_club(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner, ['country' => 'BH']);

        $event = \App\Models\ClubEvent::create([
            'tenant_id' => $club->id, 'created_by' => $owner->id, 'title' => 'Spring Open',
            'event_type' => 'championship', 'sport' => 'taekwondo', 'scope' => 'internal',
            'date' => now()->addWeeks(2)->toDateString(), 'start_time' => '09:00',
            'status' => 'active', 'is_archived' => false,
        ]);

        $member = $this->createUser();
        $member->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);
        $this->actingAs($member->fresh());

        $result = $this->callTool(\App\Mcp\Tools\EnterEventAthletesTool::class, [
            'event' => $event->uuid,
            'athlete_ids' => [$member->id],
        ]);

        $this->assertStringContainsString('club owner or club admin', $result['error']);
    }

    /* ---------------- Event brackets ---------------- */

    /**
     * A championship with a generated draw, plus the organiser who runs it.
     *
     * @return array{0: \App\Models\ClubEvent, 1: \App\Members\Models\User, 2: \App\Models\EventCategory}
     */
    private function drawnChampionship(): array
    {
        $organiser = $this->createUser(['full_name' => 'Master Kim']);
        $club = $this->createClub($organiser, ['country' => 'BH']);
        $organiser->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);

        $event = \App\Models\ClubEvent::create([
            'tenant_id' => $club->id, 'created_by' => $organiser->id, 'title' => 'Spring Open',
            'event_type' => 'championship', 'sport' => 'taekwondo', 'scope' => 'internal',
            'date' => now()->addWeeks(2)->toDateString(), 'end_date' => now()->addWeeks(2)->toDateString(),
            'start_time' => '09:00', 'end_time' => '17:00', 'status' => 'active', 'is_archived' => false,
        ]);
        $category = \App\Models\EventCategory::create([
            'event_id' => $event->id, 'name' => 'Senior Men -58 kg', 'sort_order' => 1,
        ]);

        foreach (range(1, 4) as $i) {
            $athlete = $this->createUser([
                'full_name' => 'Athlete '.$i, 'gender' => 'Male',
                'birthdate' => now()->subYears(25)->toDateString(),
            ]);
            $athlete->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);
            \App\Models\ClubEventRegistration::create([
                'event_id' => $event->id, 'user_id' => $athlete->id, 'category_id' => $category->id,
                'role' => 'participant', 'paid' => true, 'weight' => 57,
            ]);
        }

        app(\App\Events\EventTypeRegistry::class)->for($event)->performAction($event, 'generate_draw');

        return [$event, $organiser->fresh(), $category->fresh()];
    }

    /* ──────────────────────────────────────────────────────────────────
     | Video: the event gallery and one bout's footage
     ────────────────────────────────────────────────────────────────── */

    /**
     * Attach a real-looking recording to the first bout of a drawn championship,
     * plus an officiating log the timeline is derived from.
     */
    private function filmFirstBout(\App\Models\ClubEvent $event): \App\Models\EventMatch
    {
        $match = \App\Models\EventMatch::where('event_id', $event->id)->orderBy('match_no')->firstOrFail();
        $match->forceFill(['a_corner' => 'red', 'b_corner' => 'blue', 'court' => '1'])->save();

        $file = \App\Models\MediaFile::create([
            'kind' => 'clip', 'rel_path' => 'events/x/matches/1/clips/a.mp4',
            'hls_rel_path' => 'cache/hls/a', 'original_name' => 'bout.mp4',
            'mime' => 'video/mp4', 'bytes' => 1024, 'duration_seconds' => 180,
            'width' => 1920, 'height' => 1080, 'status' => \App\Models\MediaFile::STATUS_READY,
            'meta' => ['event_id' => $event->id], 'created_by' => $event->created_by,
        ]);

        $anchor = now()->startOfMinute();

        \App\Models\EventRecording::create([
            'event_id' => $event->id, 'match_id' => $match->id, 'court' => '1', 'angle' => 'main',
            'anchor_at' => $anchor, 'started_at' => $anchor, 'ended_at' => $anchor->copy()->addSeconds(180),
            'media_file_id' => $file->id, 'status' => \App\Models\EventRecording::STATUS_LINKED,
        ]);

        // Two points at the SAME instant — a simultaneous exchange, which the
        // timeline must collapse into one moment carrying the score after both.
        foreach ([
            ['s' => 1, 'off' => 10, 'side' => 'a', 'p' => 1, 'a' => 1, 'b' => 0],
            ['s' => 2, 'off' => 10, 'side' => 'b', 'p' => 1, 'a' => 1, 'b' => 1],
            ['s' => 3, 'off' => 40, 'side' => 'b', 'p' => 3, 'a' => 1, 'b' => 4],
        ] as $row) {
            \Illuminate\Support\Facades\DB::table('event_match_events')->insert([
                'event_id' => $event->id, 'match_id' => $match->id, 'court' => '1',
                'sport' => $event->sport, 'command' => 'point',
                'payload' => json_encode(['round' => 1]), 'side' => $row['side'], 'points' => $row['p'],
                'score_a' => $row['a'], 'score_b' => $row['b'],
                'occurred_at' => $anchor->copy()->addSeconds($row['off'])->toDateTimeString(),
                'sequence' => $row['s'], 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $match->fresh();
    }

    public function test_list_event_videos_groups_filmed_bouts_by_division(): void
    {
        [$event, $organiser] = $this->drawnChampionship();
        $this->filmFirstBout($event);
        $this->actingAs($organiser);

        $result = $this->callTool(\App\Mcp\Tools\ListEventVideosTool::class, ['event' => $event->uuid]);

        $this->assertSame(1, $result['filmed_bouts']);
        $this->assertSame('Senior Men -58 kg', $result['divisions'][0]['division']);
        $this->assertSame(1, $result['divisions'][0]['bouts'][0]['angles']);
    }

    public function test_list_event_videos_hides_an_event_the_user_cannot_see(): void
    {
        [$event] = $this->drawnChampionship();
        $this->filmFirstBout($event);

        $outsider = $this->createUser();
        $otherClub = $this->createClub($outsider, ['country' => 'BH']);
        $outsider->memberClubs()->syncWithoutDetaching([$otherClub->id => ['status' => 'active']]);
        $this->actingAs($outsider->fresh());

        $result = $this->callTool(\App\Mcp\Tools\ListEventVideosTool::class, ['event' => $event->uuid]);

        $this->assertStringContainsString('Event not found', $result['error']);
    }

    public function test_get_bout_video_derives_the_timeline_from_the_officiating_log(): void
    {
        [$event, $organiser] = $this->drawnChampionship();
        $match = $this->filmFirstBout($event);
        $this->actingAs($organiser);

        $result = $this->callTool(\App\Mcp\Tools\GetBoutVideoTool::class, [
            'event' => $event->uuid, 'match_no' => $match->match_no,
        ]);

        $this->assertTrue($result['timeline']['anchored']);
        // Three log rows, but the two at the same instant are ONE moment.
        $this->assertCount(2, $result['timeline']['moments']);

        $exchange = $result['timeline']['moments'][0];
        $this->assertSame('both', $exchange['side']);
        // The score AFTER the whole exchange, not after half of it.
        $this->assertSame(1, $exchange['score_red']);
        $this->assertSame(1, $exchange['score_blue']);

        $this->assertSame(1, $result['timeline']['moments'][1]['score_red']);
        $this->assertSame(4, $result['timeline']['moments'][1]['score_blue']);
    }

    public function test_get_bout_video_lets_an_athlete_reach_their_own_archived_bout(): void
    {
        [$event] = $this->drawnChampionship();
        $match = $this->filmFirstBout($event);

        // The hardest case: internal scope AND archived, so EventAccess::visible
        // says no — but the fighter still reaches their own bout.
        $event->forceFill(['scope' => 'internal', 'is_archived' => true])->save();

        $athlete = \App\Models\ClubEventRegistration::find($match->a_competitor_id)->user;
        $this->actingAs($athlete->fresh());

        $result = $this->callTool(\App\Mcp\Tools\GetBoutVideoTool::class, [
            'event' => $event->uuid, 'match_no' => $match->match_no,
        ]);

        $this->assertSame($match->match_no, $result['bout']['match_no']);
    }

    public function test_get_bout_video_refuses_someone_who_neither_fought_nor_can_see_the_event(): void
    {
        [$event] = $this->drawnChampionship();
        $match = $this->filmFirstBout($event);
        $event->forceFill(['scope' => 'internal'])->save();

        $outsider = $this->createUser();
        $otherClub = $this->createClub($outsider, ['country' => 'BH']);
        $outsider->memberClubs()->syncWithoutDetaching([$otherClub->id => ['status' => 'active']]);
        $this->actingAs($outsider->fresh());

        $result = $this->callTool(\App\Mcp\Tools\GetBoutVideoTool::class, [
            'event' => $event->uuid, 'match_no' => $match->match_no,
        ]);

        $this->assertStringContainsString('Bout not found', $result['error']);
    }

    public function test_list_events_returns_open_events_and_hands_out_the_uuid(): void
    {
        [$event, $organiser] = $this->drawnChampionship();
        $this->actingAs($organiser);

        $result = $this->callTool(\App\Mcp\Tools\ListEventsTool::class);

        $this->assertSame('open', $result['state']);
        $this->assertSame(1, $result['total']);
        $this->assertSame($event->uuid, $result['events'][0]['uuid']);
        $this->assertSame('upcoming', $result['events'][0]['state']);
        $this->assertTrue($result['events'][0]['can_manage']);
    }

    public function test_list_events_excludes_finished_events_from_the_open_state(): void
    {
        [$event, $organiser] = $this->drawnChampionship();

        // Move it into the past — "open" must drop it, "past" must find it.
        $event->update([
            'date' => now()->subDays(5)->toDateString(),
            'end_date' => now()->subDays(4)->toDateString(),
        ]);
        $this->actingAs($organiser);

        $this->assertSame(0, $this->callTool(\App\Mcp\Tools\ListEventsTool::class)['total']);
        $this->assertSame(1, $this->callTool(\App\Mcp\Tools\ListEventsTool::class, ['state' => 'past'])['total']);
    }

    public function test_list_events_hides_an_event_the_user_cannot_see(): void
    {
        $this->drawnChampionship();

        // A member of an unrelated club: an internal-scope event is not theirs to see.
        $outsider = $this->createUser();
        $otherClub = $this->createClub($outsider, ['country' => 'BH']);
        $outsider->memberClubs()->syncWithoutDetaching([$otherClub->id => ['status' => 'active']]);
        $this->actingAs($outsider->fresh());

        $result = $this->callTool(\App\Mcp\Tools\ListEventsTool::class, ['state' => 'all']);

        $this->assertSame(0, $result['total']);
    }

    public function test_list_event_documents_hides_an_event_the_user_cannot_see(): void
    {
        [$event] = $this->drawnChampionship();

        $outsider = $this->createUser();
        $otherClub = $this->createClub($outsider, ['country' => 'BH']);
        $outsider->memberClubs()->syncWithoutDetaching([$otherClub->id => ['status' => 'active']]);
        $this->actingAs($outsider->fresh());

        $result = $this->callTool(\App\Mcp\Tools\ListEventDocumentsTool::class, ['event' => $event->uuid]);

        $this->assertStringContainsString('Event not found', $result['error']);
    }

    public function test_list_event_documents_returns_attached_files(): void
    {
        [$event, $organiser] = $this->drawnChampionship();

        \App\Models\EventDocument::create([
            'event_id' => $event->id, 'title' => 'Rulebook 2026',
            'path' => 'events/'.$event->uuid.'/documents/x.pdf',
            'mime' => 'application/pdf', 'extension' => 'pdf', 'size' => 2048,
        ]);
        $this->actingAs($organiser);

        $result = $this->callTool(\App\Mcp\Tools\ListEventDocumentsTool::class, ['event' => $event->uuid]);

        $this->assertSame(1, $result['total']);
        $this->assertSame('Rulebook 2026', $result['documents'][0]['title']);
        $this->assertSame('pdf', $result['documents'][0]['type']);
        // Metadata only — a storage path must never leave the server.
        $this->assertArrayNotHasKey('path', $result['documents'][0]);
    }

    public function test_list_event_people_returns_the_athletes_and_their_clubs(): void
    {
        [$event, $organiser] = $this->drawnChampionship();
        $this->actingAs($organiser);

        $result = $this->callTool(\App\Mcp\Tools\ListEventPeopleTool::class, ['event' => $event->uuid]);

        $this->assertSame(4, $result['totals']['athletes']);
        $this->assertSame(1, $result['totals']['clubs']);
        $this->assertSame(4, $result['clubs'][0]['athletes']);
        $this->assertSame('Athlete 1', $result['athletes'][0]['name']);

        // Reading only, for everyone: the officials' data is not in this payload
        // even for the organiser who runs the event.
        $this->assertArrayNotHasKey('reg_id', $result['athletes'][0]);
        $this->assertArrayNotHasKey('weight', $result['athletes'][0]);
        $this->assertArrayNotHasKey('paid', $result['athletes'][0]);
    }

    public function test_list_event_people_hides_an_event_the_user_cannot_see(): void
    {
        [$event] = $this->drawnChampionship();

        $outsider = $this->createUser();
        $otherClub = $this->createClub($outsider, ['country' => 'BH']);
        $outsider->memberClubs()->syncWithoutDetaching([$otherClub->id => ['status' => 'active']]);
        $this->actingAs($outsider->fresh());

        $result = $this->callTool(\App\Mcp\Tools\ListEventPeopleTool::class, ['event' => $event->uuid]);

        $this->assertStringContainsString('Event not found', $result['error']);
    }

    public function test_list_events_requires_authentication(): void
    {
        config(['takeone-mcp.stdio_user_id' => null]);

        $result = $this->callTool(\App\Mcp\Tools\ListEventsTool::class);

        $this->assertStringContainsString('Not authenticated', $result['error']);
    }

    public function test_get_event_bracket_returns_the_draw(): void
    {
        [$event, $organiser] = $this->drawnChampionship();
        $this->actingAs($organiser);

        $result = $this->callTool(\App\Mcp\Tools\GetEventBracketTool::class, ['event' => $event->uuid]);

        $this->assertTrue($result['bracketed']);
        $this->assertFalse($result['event']['draw_locked']);
        $this->assertCount(1, $result['divisions']);
        $this->assertCount(2, $result['divisions'][0]['rounds'], 'four entrants → semi-finals and a final');
    }

    public function test_get_event_bracket_hides_an_event_the_user_cannot_see(): void
    {
        [$event] = $this->drawnChampionship();

        // A member of an unrelated club: an internal-scope event is not theirs
        // to see, and the answer must not reveal that the uuid is real.
        $outsider = $this->createUser();
        $otherClub = $this->createClub($outsider, ['country' => 'BH']);
        $outsider->memberClubs()->syncWithoutDetaching([$otherClub->id => ['status' => 'active']]);
        $this->actingAs($outsider->fresh());

        $result = $this->callTool(\App\Mcp\Tools\GetEventBracketTool::class, ['event' => $event->uuid]);

        $this->assertStringContainsString('Event not found', $result['error']);
    }

    public function test_arrange_event_bracket_moves_a_competitor(): void
    {
        [$event, $organiser, $category] = $this->drawnChampionship();
        $this->actingAs($organiser);

        $bout = $category->matches()->orderBy('slot')->first();
        $name = $bout->a_name;

        $result = $this->callTool(\App\Mcp\Tools\ArrangeEventBracketTool::class, [
            'event' => $event->uuid,
            'division' => 'Senior Men -58 kg',
            'from_match_id' => $bout->id,
            'from_side' => 'a',
        ]);

        $this->assertTrue($result['moved']);
        $this->assertNull($bout->fresh()->a_name);
        $this->assertNotSame($name, $bout->fresh()->a_name);
    }

    public function test_arrange_event_bracket_denies_a_non_organiser(): void
    {
        [$event, , $category] = $this->drawnChampionship();

        $watcher = $this->createUser();
        $watcher->memberClubs()->syncWithoutDetaching([$event->tenant_id => ['status' => 'active']]);
        $this->actingAs($watcher->fresh());

        $bout = $category->matches()->orderBy('slot')->first();

        $result = $this->callTool(\App\Mcp\Tools\ArrangeEventBracketTool::class, [
            'event' => $event->uuid,
            'division' => 'Senior Men -58 kg',
            'from_match_id' => $bout->id,
            'from_side' => 'a',
        ]);

        $this->assertStringContainsString('organiser', $result['error']);
        $this->assertNotNull($bout->fresh()->a_name);
    }
    public function test_member_can_promote_and_delete_own_profile_photos_through_mcp(): void
    {
        $me = $this->createUser();
        $this->actingAs($me);

        $first = $me->photos()->create(['path' => 'people/'.$me->uuid.'/photos/one.png']);
        $second = $me->photos()->create(['path' => 'people/'.$me->uuid.'/photos/two.png', 'sort_order' => 1]);
        $me->update(['profile_picture' => $second->path]);

        // The photos read back through get_member, flagged with which one is the avatar.
        $member = $this->callTool(GetMemberTool::class, ['member' => $me->uuid]);
        $this->assertCount(2, $member['photos']);
        $this->assertSame($second->uuid, collect($member['photos'])->firstWhere('is_avatar', true)['uuid']);

        // Promote the older one.
        $promoted = $this->callTool(ManageMemberPhotoTool::class, [
            'member' => $me->uuid,
            'photo' => $first->uuid,
            'action' => 'set_avatar',
        ]);
        $this->assertTrue($promoted['success']);
        $this->assertSame($first->path, $me->fresh()->profile_picture);

        // Deleting the avatar hands the role to what is left.
        $deleted = $this->callTool(ManageMemberPhotoTool::class, [
            'member' => $me->uuid,
            'photo' => $first->uuid,
            'action' => 'delete',
        ]);
        $this->assertTrue($deleted['success']);
        $this->assertTrue($deleted['was_avatar']);
        $this->assertSame($second->path, $me->fresh()->profile_picture);
        $this->assertSame(1, $me->photos()->count());
    }

    public function test_manage_member_photo_denies_unrelated_user(): void
    {
        $owner = $this->createUser();
        $photo = $owner->photos()->create(['path' => 'people/'.$owner->uuid.'/photos/one.png']);
        $owner->update(['profile_picture' => $photo->path]);

        $rando = $this->createUser();
        $this->actingAs($rando);

        $result = $this->callTool(ManageMemberPhotoTool::class, [
            'member' => $owner->uuid,
            'photo' => $photo->uuid,
            'action' => 'delete',
        ]);

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('not authorized', $result['error']);
        $this->assertSame(1, $owner->photos()->count());
    }

    /* ---------------------------------------------------------------------
     | Brazilian jiu-jitsu scoreboard
     |---------------------------------------------------------------------*/

    /** An event with one mat, one loaded bout, and a few scoring entries. */
    private function bjjMat(): array
    {
        $organiser = $this->createUser(['full_name' => 'Professor Silva']);
        $club = $this->createClub($organiser, ['country' => 'BH']);
        $organiser->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);

        $event = \App\Models\ClubEvent::create([
            'tenant_id' => $club->id, 'created_by' => $organiser->id, 'title' => 'Gulf Open',
            'event_type' => 'championship', 'sport' => 'bjj', 'scope' => 'internal',
            'date' => now()->addWeek()->toDateString(), 'end_date' => now()->addWeek()->toDateString(),
            'start_time' => '09:00', 'end_time' => '17:00', 'status' => 'active', 'is_archived' => false,
        ]);

        $mat = \App\Scoreboard\Sports\BrazilianJiuJitsu\Mat\MatState::create([
            'event_id' => $event->id, 'court' => '1', 'mode' => 'match', 'status' => 'live',
            'match_id' => 4242, 'division' => 'Adult Male Light',
            'blue' => ['name' => 'Ana Costa', 'club' => 'Alliance'],
            'white' => ['name' => 'Bia Souza', 'club' => 'Atos'],
            'remaining' => 300, 'duration' => 300, 'running' => true,
        ]);

        $ledger = app(\App\Scoreboard\Sports\BrazilianJiuJitsu\Mat\Ledger::class);
        // Mount is worth four. Called with the value the package's own table
        // gives it, so the test cannot drift from the rule it is checking.
        $mount = \App\Scoreboard\Sports\BrazilianJiuJitsu\Mat\Ledger::POINT_SOURCES['mount'];

        $ledger->append($event, '1', 'point', 4242, 'blue', $mount, 'mount', null, null, $organiser->id);
        $ledger->append($event, '1', 'advantage', 4242, 'white', 0, null, null, null, $organiser->id);
        $ledger->append($event, '1', 'penalty', 4242, 'white', 0, null, null, null, $organiser->id);

        return [$event, $organiser, $mat];
    }

    public function test_bjj_scoreboard_reports_three_counters_separately(): void
    {
        [$event, $organiser] = $this->bjjMat();
        $this->actingAs($organiser);

        $result = $this->callTool(\App\Mcp\Tools\GetBjjScoreboardTool::class, ['event' => $event->uuid]);

        $mat = $result['mats'][0];

        $this->assertSame('Ana Costa', $mat['blue']['name']);
        $this->assertSame('Bia Souza', $mat['white']['name']);

        // Mount is four, and an advantage is never folded into the points.
        $this->assertSame(4, $mat['score']['bluePoints']);
        $this->assertSame(0, $mat['score']['whitePoints']);
        $this->assertSame(1, $mat['score']['whiteAdvantages']);
        $this->assertSame(1, $mat['score']['whitePenalties']);
        $this->assertSame('blue', $mat['score']['leader']);
    }

    public function test_bjj_scoreboard_is_refused_to_an_outsider(): void
    {
        [$event] = $this->bjjMat();

        $outsider = $this->createUser();
        $otherClub = $this->createClub($outsider, ['country' => 'BH']);
        $outsider->memberClubs()->syncWithoutDetaching([$otherClub->id => ['status' => 'active']]);
        $this->actingAs($outsider->fresh());

        $result = $this->callTool(\App\Mcp\Tools\GetBjjScoreboardTool::class, ['event' => $event->uuid]);

        // The same answer a missing uuid gets, so this cannot enumerate events.
        $this->assertStringContainsString('Event not found', $result['error']);
    }

    public function test_bjj_match_log_replays_to_the_same_score(): void
    {
        [$event, $organiser] = $this->bjjMat();
        $this->actingAs($organiser);

        $result = $this->callTool(\App\Mcp\Tools\ListBjjMatchEventsTool::class, [
            'event' => $event->uuid, 'court' => '1',
        ]);

        $this->assertSame(4242, $result['match_id']);
        $this->assertCount(3, $result['entries']);

        // The log and the score must never tell two different stories.
        $this->assertSame(4, $result['score']['bluePoints']);

        // Newest first, and the corners are named rather than the neutral a/b
        // the column stores.
        $this->assertSame(['white', 'white', 'blue'], array_column($result['entries'], 'side'));
    }

    public function test_bjj_match_log_is_refused_to_someone_who_may_only_watch(): void
    {
        [$event, $organiser] = $this->bjjMat();

        // A member of the SAME club: they may see the event, and may not read an
        // officiating log, because every entry names the official who made it.
        $spectator = $this->createUser();
        $spectator->memberClubs()->syncWithoutDetaching([$event->tenant_id => ['status' => 'active']]);
        $this->actingAs($spectator->fresh());

        $result = $this->callTool(\App\Mcp\Tools\ListBjjMatchEventsTool::class, [
            'event' => $event->uuid, 'court' => '1',
        ]);

        $this->assertStringContainsString('Match log not found', $result['error']);
    }

}
