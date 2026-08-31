<?php

namespace Tests\Feature\Characterization;

use App\Models\ClassAttendance;
use App\Models\ClassCancellation;
use App\Models\ClassMakeupCredit;
use App\Models\ClassProgramOverride;
use App\Models\ClassRating;
use App\Models\ClassReaction;
use App\Models\ClassSubstitution;
use App\Clubs\Models\ClubActivity;
use App\Clubs\Models\ClubInstructor;
use App\Models\ClubMemberSubscription;
use App\Clubs\Models\ClubPackage;
use App\Clubs\Models\ClubPackageActivity;
use App\Models\InstructorReview;
use App\Clubs\Models\Tenant;
use App\Models\User;
use App\Models\UserScheduleSession;
use App\Support\SyncedClassToken;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Contracts\ContractTestCase;

/**
 * CHARACTERIZATION — the WRITE surface of PersonalMobileController.
 *
 * PersonalMobileController is ~3,700 lines with 47 public methods and no
 * direct coverage. These tests pin what the 17 POST/PUT/PATCH/DELETE routes
 * it serves ACTUALLY DO TODAY — the status code, the JSON shape, and the row
 * that lands in (or stays out of) the database — so a refactor that splits
 * the class up cannot move any of it by accident.
 *
 * They assert current behaviour, not desirable behaviour. Anything that looks
 * wrong is marked `// DIVERGENCE:` and left alone.
 *
 * Authorization denial and the guest path live in the sibling
 * PersonalMobileAuthMatrixTest.
 *
 * Time is frozen at Monday 2026-09-07 12:00 UTC so the club-class window
 * checks (attendance opens at the start time, closes at the end time) are
 * deterministic. The fixture class runs Mondays 11:00–13:00, i.e. "now".
 */
class PersonalMobileWriteCharacterizationTest extends ContractTestCase
{
    /** The frozen "now" — a Monday, midday. */
    private const NOW = '2026-09-07 12:00:00';

    private const TODAY = '2026-09-07';

    private const NEXT_WEEK = '2026-09-14';

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(self::NOW);
        Storage::fake('local');
        Storage::fake('public');
    }

    // ---------------------------------------------------------------------
    // Fixtures
    // ---------------------------------------------------------------------

    /**
     * A club with one package, one activity and a weekly Monday 11:00–13:00
     * class taught by $coach.
     *
     * @return array{club: Tenant, owner: User, coach: User, package: ClubPackage, pa: ClubPackageActivity, token: string}
     */
    private function classFixture(): array
    {
        $owner = $this->createUser(['full_name' => 'Club Owner']);
        // The attendance window is evaluated in the CLUB's timezone (tenants
        // default to Asia/Bahrain, UTC+3). Pinning it to UTC keeps the frozen
        // clock and the slot times in the same frame.
        $club = $this->createClub($owner, ['country' => 'BH', 'currency' => 'BHD', 'timezone' => 'UTC']);

        $coach = $this->createUser(['full_name' => 'Regular Coach']);
        $instructor = ClubInstructor::factory()->create([
            'tenant_id' => $club->id,
            'user_id' => $coach->id,
        ]);

        $package = ClubPackage::factory()->create(['tenant_id' => $club->id]);
        $activity = ClubActivity::factory()->create(['tenant_id' => $club->id, 'name' => 'Taekwondo']);

        $pa = ClubPackageActivity::create([
            'package_id' => $package->id,
            'activity_id' => $activity->id,
            'instructor_id' => $instructor->id,
        ]);
        $pa->schedule = json_encode([[
            'day' => 'monday',
            'start_time' => '11:00',
            'end_time' => '13:00',
            'title' => 'Evening Sparring',
        ]]);
        $pa->save();

        return [
            'club' => $club,
            'owner' => $owner,
            'coach' => $coach,
            'package' => $package,
            'pa' => $pa->fresh(),
            'token' => SyncedClassToken::encode($pa->id, 'monday', '11:00'),
        ];
    }

    /** Enrol a fresh member in the fixture's package (what `isEnrolledIn` looks for). */
    private function enrol(Tenant $club, ClubPackage $package, ?User $member = null): User
    {
        $member = $member ?: $this->createUser(['full_name' => 'Enrolled Member']);
        $member->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);

        ClubMemberSubscription::create([
            'tenant_id' => $club->id,
            'user_id' => $member->id,
            'package_id' => $package->id,
            'type' => 'regular',
            'status' => 'active',
            'payment_status' => 'paid',
            'amount_paid' => 10,
            'amount_due' => 0,
            'start_date' => self::TODAY,
            'end_date' => '2026-10-07',
        ]);

        return $member->fresh();
    }

    /** A minimal valid personal-session payload. */
    private function sessionPayload(array $overrides = []): array
    {
        return array_merge([
            'subject' => 'me',
            'day' => 'monday',
            'title' => 'Morning run',
            'start_time' => '6:30 AM',
            'end_time' => '7:15 AM',
            'intensity' => 'Moderate',
            'color' => '#7c3aed',
        ], $overrides);
    }

    /** A 1x1 PNG as a data URI — real PNG bytes, so the MIME sniff accepts it. */
    private function pngDataUri(): string
    {
        return 'data:image/png;base64,'.base64_encode(base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        ));
    }

    // =====================================================================
    // POST /me/schedule  →  me.schedule.store
    // =====================================================================

    public function test_schedule_store_creates_a_personal_session_for_the_actor(): void
    {
        $user = $this->createUser();

        $res = $this->actingAs($user)->postJson(route('me.schedule.store'), $this->sessionPayload());

        $res->assertOk()
            ->assertJson(['success' => true, 'message' => 'Session added.'])
            ->assertJsonStructure(['success', 'message', 'session' => ['id', 'day', 'title', 'status']]);

        $this->assertDatabaseHas('user_schedule_sessions', [
            'user_id' => $user->id,
            'subject_user_id' => $user->id,
            'day' => 'monday',
            'title' => 'Morning run',
            'intensity' => 'Moderate',
        ]);
    }

    public function test_schedule_store_rejects_an_invalid_payload_and_writes_nothing(): void
    {
        $user = $this->createUser();

        // `day` is not a weekday, `title` is missing, `color` is not a hex triplet.
        $res = $this->actingAs($user)->postJson(route('me.schedule.store'), [
            'subject' => 'me',
            'day' => 'someday',
            'color' => 'purple',
        ]);

        $res->assertStatus(422)->assertJsonValidationErrors(['day', 'title', 'color']);
        $this->assertSame(0, UserScheduleSession::count());
    }

    public function test_schedule_store_refuses_a_subject_that_is_not_the_actor_or_a_dependent(): void
    {
        $user = $this->createUser();
        $stranger = $this->createUser();

        $res = $this->actingAs($user)->postJson(route('me.schedule.store'), $this->sessionPayload([
            'subject' => 'u'.$stranger->id,
        ]));

        // DIVERGENCE: an unknown/foreign subject is reported as a 422 VALIDATION
        // failure ("Unknown family member."), not a 403. It is refused either
        // way, so nothing is written — but the status code says "bad input"
        // when the real reason is "not yours".
        $res->assertStatus(422)->assertJson(['success' => false, 'message' => 'Unknown family member.']);
        $this->assertSame(0, UserScheduleSession::count());
    }

    // =====================================================================
    // PUT /me/schedule/{session}  →  me.schedule.update
    // =====================================================================

    public function test_schedule_update_edits_the_actors_own_session(): void
    {
        $user = $this->createUser();
        $session = UserScheduleSession::create([
            'user_id' => $user->id, 'subject_user_id' => $user->id,
            'day' => 'monday', 'title' => 'Old title',
        ]);

        $res = $this->actingAs($user)->putJson(
            route('me.schedule.update', $session->id),
            $this->sessionPayload(['title' => 'New title', 'day' => 'friday'])
        );

        $res->assertOk()
            ->assertJson(['success' => true, 'message' => 'Session updated.'])
            ->assertJsonStructure(['success', 'message', 'session' => ['id', 'day', 'title', 'status']]);

        $this->assertDatabaseHas('user_schedule_sessions', [
            'id' => $session->id, 'title' => 'New title', 'day' => 'friday',
        ]);
    }

    public function test_schedule_update_rejects_an_invalid_payload_and_leaves_the_row_alone(): void
    {
        $user = $this->createUser();
        $session = UserScheduleSession::create([
            'user_id' => $user->id, 'subject_user_id' => $user->id,
            'day' => 'monday', 'title' => 'Untouched',
        ]);

        $this->actingAs($user)
            ->putJson(route('me.schedule.update', $session->id), ['subject' => 'me', 'day' => 'monday'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title']);

        $this->assertDatabaseHas('user_schedule_sessions', ['id' => $session->id, 'title' => 'Untouched', 'day' => 'monday']);
    }

    // =====================================================================
    // DELETE /me/schedule/{session}  →  me.schedule.destroy
    // =====================================================================

    public function test_schedule_destroy_removes_the_actors_own_session(): void
    {
        $user = $this->createUser();
        $session = UserScheduleSession::create([
            'user_id' => $user->id, 'subject_user_id' => $user->id,
            'day' => 'monday', 'title' => 'Gone soon',
        ]);

        $this->actingAs($user)
            ->deleteJson(route('me.schedule.destroy', $session->id))
            ->assertOk()
            ->assertJson(['success' => true, 'message' => 'Session removed.', 'id' => $session->id]);

        $this->assertDatabaseMissing('user_schedule_sessions', ['id' => $session->id]);
    }

    // =====================================================================
    // PUT /me/discoverable  →  me.discoverable.update
    // =====================================================================

    public function test_discoverable_toggle_persists_and_echoes_the_new_value(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->putJson(route('me.discoverable.update'), ['is_discoverable' => false])
            ->assertOk()
            ->assertJsonStructure(['success', 'is_discoverable', 'message'])
            ->assertJson(['success' => true, 'is_discoverable' => false]);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'is_discoverable' => false]);
    }

    public function test_discoverable_toggle_rejects_a_non_boolean_and_writes_nothing(): void
    {
        $user = $this->createUser(['is_discoverable' => true]);

        $this->actingAs($user)
            ->putJson(route('me.discoverable.update'), ['is_discoverable' => 'maybe'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['is_discoverable']);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'is_discoverable' => true]);
    }

    // =====================================================================
    // POST /me/seen  →  me.seen
    // =====================================================================

    public function test_mark_section_seen_records_a_view_row(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->postJson(route('me.seen'), ['section' => 'feed:all'])
            ->assertOk()
            ->assertExactJson(['success' => true]);

        $this->assertDatabaseHas('user_section_views', ['user_id' => $user->id, 'section' => 'feed:all']);
    }

    /**
     * FIXED 2026-08-31 (was a DIVERGENCE): the endpoint had no validation at
     * all — an unknown or missing section returned 200 {"success":true} while
     * writing nothing, so the caller was told a write succeeded that never
     * happened. `section` is now validated against
     * App\Support\SectionActivity::SECTIONS and anything else is a 422.
     */
    public function test_mark_section_seen_refuses_an_unknown_section_and_writes_nothing(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->postJson(route('me.seen'), ['section' => 'not-a-section'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['section']);

        $this->assertDatabaseMissing('user_section_views', ['user_id' => $user->id]);
    }

    /** A missing `section` is refused the same way — nothing is written. */
    public function test_mark_section_seen_refuses_a_missing_section_and_writes_nothing(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->postJson(route('me.seen'), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['section']);

        $this->assertDatabaseMissing('user_section_views', ['user_id' => $user->id]);
    }

    /**
     * Every section the client may send still succeeds, with the response shape
     * unchanged — the vocabulary is SectionActivity::SECTIONS, which is also what
     * markSeen() itself accepts.
     */
    public function test_mark_section_seen_accepts_every_known_section(): void
    {
        $user = $this->createUser();

        foreach (\App\Support\SectionActivity::SECTIONS as $section) {
            $this->actingAs($user)
                ->postJson(route('me.seen'), ['section' => $section])
                ->assertOk()
                ->assertExactJson(['success' => true]);

            $this->assertDatabaseHas('user_section_views', [
                'user_id' => $user->id, 'section' => $section,
            ]);
        }
    }

    // =====================================================================
    // POST /me/payments/{subscription}/settle  →  me.payments.settle
    // MONEY + FILE STORAGE + NOTIFICATION. Pinned in detail.
    // =====================================================================

    /** @return array{0: User, 1: Tenant, 2: ClubMemberSubscription} */
    private function unpaidBill(): array
    {
        $owner = $this->createUser(['full_name' => 'Club Owner']);
        $club = $this->createClub($owner, ['country' => 'BH', 'currency' => 'BHD']);
        $package = ClubPackage::factory()->create(['tenant_id' => $club->id]);
        $member = $this->createUser(['full_name' => 'Paying Member']);
        $member->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);

        $sub = ClubMemberSubscription::create([
            'tenant_id' => $club->id,
            'user_id' => $member->id,
            'package_id' => $package->id,
            'type' => 'regular',
            'status' => 'active',
            'payment_status' => 'unpaid',
            'amount_paid' => 0,
            'amount_due' => 45,
            'start_date' => self::TODAY,
            'end_date' => '2026-10-07',
        ]);

        return [$member, $club, $sub];
    }

    public function test_settle_payment_stores_the_proof_flips_the_status_and_notifies_the_club_owner(): void
    {
        [$member, $club, $sub] = $this->unpaidBill();

        $res = $this->actingAs($member)->postJson(
            route('me.payments.settle', $sub->id),
            ['payment_proof_base64' => $this->pngDataUri()]
        );

        $res->assertOk()
            ->assertJsonStructure(['success', 'message', 'payment_status', 'subscription_id'])
            ->assertJson([
                'success' => true,
                'payment_status' => 'pending_approval',
                'subscription_id' => $sub->id,
            ]);

        $this->assertDatabaseHas('club_member_subscriptions', [
            'id' => $sub->id,
            'payment_status' => 'pending_approval',
        ]);

        // The proof lands on the PRIVATE `local` disk, in payment-proofs/, under
        // a server-generated, non-guessable name (a uuid, extension assigned from
        // the validated bytes) — never the client's, and never clock-derived.
        $stored = $sub->fresh()->proof_of_payment;
        $this->assertMatchesRegularExpression(
            '#^payment-proofs/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\.png$#', $stored
        );
        Storage::disk('local')->assertExists($stored);
        $this->assertCount(1, Storage::disk('local')->allFiles('payment-proofs'));

        // The club owner is told there is a payment to review, deep-linked to the
        // members admin page.
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $club->owner_user_id,
            'type' => 'payment_pending',
            'tenant_id' => $club->id,
            'actor_user_id' => $member->id,
        ]);
    }

    public function test_settle_payment_rejects_a_missing_proof_and_leaves_the_bill_unpaid(): void
    {
        [$member, , $sub] = $this->unpaidBill();

        $this->actingAs($member)
            ->postJson(route('me.payments.settle', $sub->id), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['payment_proof_base64']);

        $this->assertDatabaseHas('club_member_subscriptions', [
            'id' => $sub->id, 'payment_status' => 'unpaid', 'proof_of_payment' => null,
        ]);
        $this->assertEmpty(Storage::disk('local')->allFiles('payment-proofs'));
    }

    public function test_settle_payment_refuses_a_disguised_php_file_and_stores_nothing(): void
    {
        [$member, , $sub] = $this->unpaidBill();

        // Claims to be a PNG in the data-URI header; the bytes are PHP.
        $payload = 'data:image/png;base64,'.base64_encode('<?php echo "pwned"; ?>');

        $this->actingAs($member)
            ->postJson(route('me.payments.settle', $sub->id), ['payment_proof_base64' => $payload])
            ->assertStatus(422)
            ->assertJson(['success' => false]);

        $this->assertDatabaseHas('club_member_subscriptions', [
            'id' => $sub->id, 'payment_status' => 'unpaid', 'proof_of_payment' => null,
        ]);
        $this->assertEmpty(Storage::disk('local')->allFiles('payment-proofs'));
    }

    public function test_settle_payment_refuses_a_bill_that_is_already_paid(): void
    {
        [$member, , $sub] = $this->unpaidBill();
        $sub->update(['payment_status' => 'paid']);

        $this->actingAs($member)
            ->postJson(route('me.payments.settle', $sub->id), ['payment_proof_base64' => $this->pngDataUri()])
            ->assertStatus(422)
            ->assertJson(['success' => false]);

        $this->assertDatabaseHas('club_member_subscriptions', ['id' => $sub->id, 'payment_status' => 'paid']);
        $this->assertEmpty(Storage::disk('local')->allFiles('payment-proofs'));
    }

    public function test_settle_payment_deletes_the_previous_proof_file_when_the_path_changes(): void
    {
        [$member, , $sub] = $this->unpaidBill();

        // Stand in for an earlier submission whose generated name differs.
        Storage::disk('local')->put('payment-proofs/sub_'.$sub->id.'_older.png', 'old bytes');
        $sub->update(['proof_of_payment' => 'payment-proofs/sub_'.$sub->id.'_older.png']);

        $this->actingAs($member)->postJson(
            route('me.payments.settle', $sub->id),
            ['payment_proof_base64' => $this->pngDataUri()]
        )->assertOk();

        $new = $sub->fresh()->proof_of_payment;
        $this->assertNotSame('payment-proofs/sub_'.$sub->id.'_older.png', $new);
        Storage::disk('local')->assertMissing('payment-proofs/sub_'.$sub->id.'_older.png');
        Storage::disk('local')->assertExists($new);
        $this->assertCount(1, Storage::disk('local')->allFiles('payment-proofs'));
    }

    /**
     * FIXED 2026-08-31 (was a DIVERGENCE): the stored name was
     * 'sub_<id>_'.time() — real wall clock — so two submissions inside the same
     * second produced the SAME path, the "delete the old proof" branch was
     * skipped (the path had not changed) and the earlier image was silently
     * overwritten in place. The name is now a server-generated uuid, so every
     * submission lands on its own path and the previous file is only ever
     * removed through the explicit, accounted-for delete branch.
     */
    public function test_settle_payment_generates_a_distinct_filename_within_one_second(): void
    {
        [$member, , $sub] = $this->unpaidBill();
        $url = route('me.payments.settle', $sub->id);

        $this->actingAs($member)->postJson($url, ['payment_proof_base64' => $this->pngDataUri()])->assertOk();
        $first = $sub->fresh()->proof_of_payment;
        $firstBytes = Storage::disk('local')->get($first);

        // The record no longer points at the first file (as if it had been
        // consumed elsewhere), so the delete branch cannot fire: the second
        // submission must still not land on top of it.
        $sub->update(['payment_status' => 'unpaid', 'proof_of_payment' => null]);
        $this->actingAs($member)->postJson($url, ['payment_proof_base64' => $this->pngDataUri()])->assertOk();
        $second = $sub->fresh()->proof_of_payment;

        $this->assertNotSame($first, $second);
        $this->assertCount(2, Storage::disk('local')->allFiles('payment-proofs'));
        Storage::disk('local')->assertExists($first);
        Storage::disk('local')->assertExists($second);
        $this->assertSame($firstBytes, Storage::disk('local')->get($first));
    }

    /**
     * And with the record still pointing at the previous proof, a same-second
     * resubmission takes the explicit delete branch instead of overwriting:
     * one file on disk, and it is the new one.
     */
    public function test_settle_payment_replaces_a_same_second_proof_through_the_delete_branch(): void
    {
        [$member, , $sub] = $this->unpaidBill();
        $url = route('me.payments.settle', $sub->id);

        $this->actingAs($member)->postJson($url, ['payment_proof_base64' => $this->pngDataUri()])->assertOk();
        $first = $sub->fresh()->proof_of_payment;

        $sub->update(['payment_status' => 'unpaid']);
        $this->actingAs($member)->postJson($url, ['payment_proof_base64' => $this->pngDataUri()])->assertOk();
        $second = $sub->fresh()->proof_of_payment;

        $this->assertNotSame($first, $second);
        Storage::disk('local')->assertMissing($first);
        Storage::disk('local')->assertExists($second);
        $this->assertCount(1, Storage::disk('local')->allFiles('payment-proofs'));
    }

    public function test_settle_payment_is_allowed_for_a_guardian_of_the_member(): void
    {
        [$member, , $sub] = $this->unpaidBill();
        $guardian = $this->createUser(['full_name' => 'The Guardian']);

        \App\Models\UserRelationship::create([
            'guardian_user_id' => $guardian->id,
            'dependent_user_id' => $member->id,
            'relationship_type' => 'parent',
        ]);

        $this->actingAs($guardian)->postJson(
            route('me.payments.settle', $sub->id),
            ['payment_proof_base64' => $this->pngDataUri()]
        )->assertOk()->assertJson(['success' => true, 'payment_status' => 'pending_approval']);

        $this->assertDatabaseHas('club_member_subscriptions', [
            'id' => $sub->id, 'payment_status' => 'pending_approval',
        ]);
    }

    // =====================================================================
    // PUT /me/schedule/synced/{token}  →  me.schedule.synced.update
    // =====================================================================

    public function test_synced_update_rewrites_the_recurring_slot_in_the_package_schedule(): void
    {
        ['owner' => $owner, 'pa' => $pa, 'token' => $token] = $this->classFixture();

        $res = $this->actingAs($owner)->putJson(route('me.schedule.synced.update', $token), [
            'scope' => 'recurring',
            'day' => 'wednesday',
            'start_time' => '19:00',
            'end_time' => '20:00',
            'title' => 'Rescheduled Sparring',
            'intensity' => 'High',
            'focus' => ['Legs', ''],
            'notes' => 'Bring gloves.',
            'location_type' => 'text',
            'location' => 'Main hall',
        ]);

        $res->assertOk()
            ->assertJsonStructure(['success', 'message', 'redirect', 'once'])
            ->assertJson(['success' => true, 'once' => false, 'message' => 'Class schedule updated.']);

        $slot = json_decode($pa->fresh()->schedule, true)[0];
        $this->assertSame('wednesday', $slot['day']);
        $this->assertSame('19:00', $slot['start_time']);
        $this->assertSame('Rescheduled Sparring', $slot['title']);
        $this->assertSame('High', $slot['intensity']);
        $this->assertSame(['Legs'], $slot['focus']);          // blank entries are dropped
        $this->assertSame('text', $slot['location_type']);
        $this->assertSame('Main hall', $slot['location_text']);
    }

    public function test_synced_update_with_scope_once_writes_an_override_and_leaves_the_recurring_plan_intact(): void
    {
        ['owner' => $owner, 'pa' => $pa, 'token' => $token] = $this->classFixture();
        $before = json_decode($pa->schedule, true)[0];

        $res = $this->actingAs($owner)->putJson(route('me.schedule.synced.update', $token), [
            'scope' => 'once',
            'date' => self::NEXT_WEEK,
            'day' => 'monday',
            'start_time' => '11:00',
            'end_time' => '13:00',
            'intensity' => 'Low',
            'notes' => 'Light session this week only.',
        ]);

        $res->assertOk()->assertJson(['success' => true, 'once' => true, 'message' => 'Program saved for this date.']);

        $this->assertDatabaseHas('class_program_overrides', [
            'package_activity_id' => $pa->id,
            'slot_day' => 'monday',
            'slot_start' => '11:00',
            'intensity' => 'Low',
            'notes' => 'Light session this week only.',
            'set_by' => $owner->id,
        ]);

        // The recurring plan's rich content is untouched by a `once` save.
        $after = json_decode($pa->fresh()->schedule, true)[0];
        $this->assertSame($before['day'], $after['day']);
        $this->assertArrayNotHasKey('intensity', $after);
    }

    public function test_synced_update_scope_once_is_idempotent_and_does_not_violate_the_unique_key(): void
    {
        ['owner' => $owner, 'pa' => $pa, 'token' => $token] = $this->classFixture();

        $payload = [
            'scope' => 'once', 'date' => self::NEXT_WEEK, 'day' => 'monday',
            'start_time' => '11:00', 'end_time' => '13:00', 'notes' => 'First',
        ];

        $this->actingAs($owner)->putJson(route('me.schedule.synced.update', $token), $payload)->assertOk();
        $this->actingAs($owner)->putJson(
            route('me.schedule.synced.update', $token), array_merge($payload, ['notes' => 'Second'])
        )->assertOk();

        $this->assertSame(1, ClassProgramOverride::where('package_activity_id', $pa->id)->count());
        $this->assertDatabaseHas('class_program_overrides', ['package_activity_id' => $pa->id, 'notes' => 'Second']);
    }

    public function test_synced_update_rejects_an_invalid_day_and_leaves_the_schedule_alone(): void
    {
        ['owner' => $owner, 'pa' => $pa, 'token' => $token] = $this->classFixture();
        $before = $pa->schedule;

        $this->actingAs($owner)
            ->putJson(route('me.schedule.synced.update', $token), ['day' => 'noneday'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['day']);

        $this->assertSame($before, $pa->fresh()->schedule);
    }

    public function test_synced_update_returns_404_for_a_malformed_token(): void
    {
        $user = $this->createUser();

        // DIVERGENCE: a malformed token is a JSON 404 body with 200-shaped keys
        // ({success:false, message}) rather than the framework's 404 page —
        // deliberate, since these are JSON doors for the member app.
        $this->actingAs($user)
            ->putJson(route('me.schedule.synced.update', 'not-a-real-token'), ['day' => 'monday'])
            ->assertStatus(404)
            ->assertJson(['success' => false]);
    }

    // =====================================================================
    // POST /me/schedule/synced/{token}/attendance  →  me.schedule.attendance.toggle
    // =====================================================================

    public function test_attendance_toggle_marks_then_unmarks_a_member(): void
    {
        ['club' => $club, 'coach' => $coach, 'package' => $package, 'pa' => $pa, 'token' => $token] = $this->classFixture();
        $member = $this->enrol($club, $package);

        $this->actingAs($coach)->postJson(route('me.schedule.attendance.toggle', $token), [
            'user_id' => $member->id, 'date' => self::TODAY,
        ])->assertOk()
            ->assertJsonStructure(['success', 'attended', 'user_id'])
            ->assertJson(['success' => true, 'attended' => true, 'user_id' => $member->id]);

        $this->assertDatabaseHas('class_attendances', [
            'package_activity_id' => $pa->id, 'slot_day' => 'monday', 'slot_start' => '11:00',
            'user_id' => $member->id, 'marked_by' => $coach->id,
        ]);

        // Second call is the un-mark.
        $this->actingAs($coach)->postJson(route('me.schedule.attendance.toggle', $token), [
            'user_id' => $member->id, 'date' => self::TODAY,
        ])->assertOk()->assertJson(['attended' => false]);

        $this->assertSame(0, ClassAttendance::where('package_activity_id', $pa->id)->count());
    }

    public function test_attendance_toggle_is_refused_before_the_class_starts(): void
    {
        ['coach' => $coach, 'club' => $club, 'package' => $package, 'pa' => $pa, 'token' => $token] = $this->classFixture();
        $member = $this->enrol($club, $package);

        $this->actingAs($coach)->postJson(route('me.schedule.attendance.toggle', $token), [
            'user_id' => $member->id, 'date' => self::NEXT_WEEK,     // next Monday, 11:00 — still future
        ])->assertStatus(422)->assertJson(['success' => false]);

        $this->assertSame(0, ClassAttendance::where('package_activity_id', $pa->id)->count());
    }

    public function test_attendance_toggle_rejects_an_unknown_user_id(): void
    {
        ['coach' => $coach, 'pa' => $pa, 'token' => $token] = $this->classFixture();

        $this->actingAs($coach)->postJson(route('me.schedule.attendance.toggle', $token), [
            'user_id' => 999999, 'date' => self::TODAY,
        ])->assertStatus(422)->assertJsonValidationErrors(['user_id']);

        $this->assertSame(0, ClassAttendance::where('package_activity_id', $pa->id)->count());
    }

    // =====================================================================
    // POST / DELETE /me/schedule/synced/{token}/cancel
    // =====================================================================

    public function test_class_cancel_records_the_cancellation_and_credits_enrolled_members(): void
    {
        ['coach' => $coach, 'club' => $club, 'package' => $package, 'pa' => $pa, 'token' => $token] = $this->classFixture();
        $member = $this->enrol($club, $package);
        $subBefore = ClubMemberSubscription::where('user_id', $member->id)->first();

        $res = $this->actingAs($coach)->postJson(route('me.schedule.cancel', $token), [
            'from' => self::TODAY,
            'reason' => 'Coach is ill',
        ]);

        $res->assertOk()
            ->assertJsonStructure(['success', 'message', 'redirect'])
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('class_cancellations', [
            'package_activity_id' => $pa->id, 'slot_day' => 'monday', 'slot_start' => '11:00',
            'reason' => 'Coach is ill', 'creditable' => true, 'cancelled_by' => $coach->id,
        ]);

        // Every enrolled member gets a make-up credit, and their subscription's
        // end date is nudged a week so the credit survives expiry.
        $this->assertDatabaseHas('class_makeup_credits', [
            'package_activity_id' => $pa->id, 'user_id' => $member->id,
            'credit_days' => 7, 'status' => 'open',
        ]);
        $this->assertSame(
            \Carbon\Carbon::parse($subBefore->end_date)->addDays(7)->toDateString(),
            \Carbon\Carbon::parse($subBefore->fresh()->end_date)->toDateString(),
        );
    }

    public function test_class_cancel_with_credit_false_cancels_without_crediting_anyone(): void
    {
        ['coach' => $coach, 'club' => $club, 'package' => $package, 'pa' => $pa, 'token' => $token] = $this->classFixture();
        $this->enrol($club, $package);

        $this->actingAs($coach)->postJson(route('me.schedule.cancel', $token), [
            'from' => self::TODAY, 'credit' => false,
        ])->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('class_cancellations', ['package_activity_id' => $pa->id, 'creditable' => false]);
        $this->assertSame(0, ClassMakeupCredit::count());
    }

    public function test_class_cancel_refuses_a_range_with_no_occurrence_of_this_weekday(): void
    {
        ['coach' => $coach, 'pa' => $pa, 'token' => $token] = $this->classFixture();

        // Tue–Thu: the class runs Mondays, so nothing falls in the span.
        $this->actingAs($coach)->postJson(route('me.schedule.cancel', $token), [
            'from' => '2026-09-08', 'to' => '2026-09-10',
        ])->assertStatus(422)->assertJson(['success' => false]);

        $this->assertSame(0, ClassCancellation::where('package_activity_id', $pa->id)->count());
    }

    public function test_class_cancel_rejects_a_to_date_before_from(): void
    {
        ['coach' => $coach, 'pa' => $pa, 'token' => $token] = $this->classFixture();

        $this->actingAs($coach)->postJson(route('me.schedule.cancel', $token), [
            'from' => self::NEXT_WEEK, 'to' => self::TODAY,
        ])->assertStatus(422)->assertJsonValidationErrors(['to']);

        $this->assertSame(0, ClassCancellation::where('package_activity_id', $pa->id)->count());
    }

    public function test_class_uncancel_removes_the_cancellation_and_reverses_the_open_credit(): void
    {
        ['coach' => $coach, 'club' => $club, 'package' => $package, 'pa' => $pa, 'token' => $token] = $this->classFixture();
        $member = $this->enrol($club, $package);
        $sub = ClubMemberSubscription::where('user_id', $member->id)->first();
        $endBefore = \Carbon\Carbon::parse($sub->end_date)->toDateString();

        $this->actingAs($coach)->postJson(route('me.schedule.cancel', $token), ['from' => self::TODAY])->assertOk();

        $this->actingAs($coach)
            ->deleteJson(route('me.schedule.uncancel', $token), ['date' => self::TODAY])
            ->assertOk()
            ->assertJsonStructure(['success', 'message', 'redirect'])
            ->assertJson(['success' => true, 'message' => 'Class restored.']);

        $this->assertSame(0, ClassCancellation::where('package_activity_id', $pa->id)->count());
        $this->assertSame(0, ClassMakeupCredit::count());
        $this->assertSame($endBefore, \Carbon\Carbon::parse($sub->fresh()->end_date)->toDateString());
    }

    public function test_class_uncancel_rejects_a_missing_date(): void
    {
        ['coach' => $coach, 'token' => $token] = $this->classFixture();

        $this->actingAs($coach)
            ->deleteJson(route('me.schedule.uncancel', $token), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['date']);
    }

    // =====================================================================
    // DELETE /me/schedule/synced/{token}/program  →  me.schedule.program.reset
    // =====================================================================

    public function test_program_reset_deletes_the_dated_override(): void
    {
        ['owner' => $owner, 'pa' => $pa, 'token' => $token] = $this->classFixture();

        ClassProgramOverride::create([
            'package_activity_id' => $pa->id, 'slot_day' => 'monday', 'slot_start' => '11:00',
            'date' => self::NEXT_WEEK, 'intensity' => 'Low', 'set_by' => $owner->id,
        ]);

        $this->actingAs($owner)
            ->deleteJson(route('me.schedule.program.reset', $token), ['date' => self::NEXT_WEEK])
            ->assertOk()
            ->assertJsonStructure(['success', 'message'])
            ->assertJson(['success' => true]);

        $this->assertSame(0, ClassProgramOverride::where('package_activity_id', $pa->id)->count());
    }

    public function test_program_reset_rejects_a_missing_date_and_keeps_the_override(): void
    {
        ['owner' => $owner, 'pa' => $pa, 'token' => $token] = $this->classFixture();
        ClassProgramOverride::create([
            'package_activity_id' => $pa->id, 'slot_day' => 'monday', 'slot_start' => '11:00',
            'date' => self::NEXT_WEEK, 'intensity' => 'Low', 'set_by' => $owner->id,
        ]);

        $this->actingAs($owner)
            ->deleteJson(route('me.schedule.program.reset', $token), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['date']);

        $this->assertSame(1, ClassProgramOverride::where('package_activity_id', $pa->id)->count());
    }

    // =====================================================================
    // POST /me/schedule/synced/{token}/rate  →  me.schedule.rate (the TRAINER)
    // =====================================================================

    public function test_rate_class_trainer_writes_an_instructor_review_and_syncs_the_average(): void
    {
        ['club' => $club, 'coach' => $coach, 'package' => $package, 'token' => $token] = $this->classFixture();
        $member = $this->enrol($club, $package);

        $res = $this->actingAs($member)->postJson(route('me.schedule.rate', $token), [
            'rating' => 4, 'comment' => 'Great session.',
        ]);

        $res->assertOk()
            ->assertJsonStructure(['success', 'message', 'rating', 'average'])
            ->assertJson(['success' => true, 'rating' => 4, 'average' => 4]);

        $instructor = ClubInstructor::where('tenant_id', $club->id)->where('user_id', $coach->id)->firstOrFail();
        $this->assertDatabaseHas('instructor_reviews', [
            'instructor_id' => $instructor->id, 'reviewer_user_id' => $member->id,
            'rating' => 4, 'comment' => 'Great session.',
        ]);
        $this->assertEquals(4.0, (float) $instructor->fresh()->rating);
    }

    public function test_rate_class_trainer_rejects_a_rating_out_of_range(): void
    {
        ['club' => $club, 'package' => $package, 'token' => $token] = $this->classFixture();
        $member = $this->enrol($club, $package);

        $this->actingAs($member)
            ->postJson(route('me.schedule.rate', $token), ['rating' => 9])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['rating']);

        $this->assertSame(0, InstructorReview::count());
    }

    // =====================================================================
    // POST / DELETE /me/schedule/synced/{token}/rate-class  (the CLASS)
    // =====================================================================

    public function test_rate_class_requires_having_attended_a_started_session(): void
    {
        ['club' => $club, 'package' => $package, 'pa' => $pa, 'token' => $token] = $this->classFixture();
        $member = $this->enrol($club, $package);

        $this->actingAs($member)
            ->postJson(route('me.schedule.rate.class', $token), ['rating' => 5])
            ->assertStatus(422)
            ->assertJson(['success' => false]);

        $this->assertSame(0, ClassRating::where('package_activity_id', $pa->id)->count());
    }

    public function test_rate_class_saves_a_review_once_the_member_has_attended(): void
    {
        ['club' => $club, 'coach' => $coach, 'package' => $package, 'pa' => $pa, 'token' => $token] = $this->classFixture();
        $member = $this->enrol($club, $package);

        ClassAttendance::create([
            'package_activity_id' => $pa->id, 'slot_day' => 'monday', 'slot_start' => '11:00',
            'date' => self::TODAY, 'user_id' => $member->id, 'marked_by' => $coach->id,
        ]);

        $res = $this->actingAs($member)->postJson(route('me.schedule.rate.class', $token), [
            'rating' => 5, 'comment' => 'Best class.',
        ]);

        $res->assertOk()
            ->assertJsonStructure(['success', 'message', 'rating', 'average', 'count', 'comments', 'distribution'])
            ->assertJson(['success' => true, 'rating' => 5, 'average' => 5, 'count' => 1]);

        $this->assertDatabaseHas('class_ratings', [
            'package_activity_id' => $pa->id, 'user_id' => $member->id,
            'rating' => 5, 'comment' => 'Best class.',
        ]);
    }

    public function test_rate_class_destroy_removes_the_members_own_review(): void
    {
        ['club' => $club, 'package' => $package, 'pa' => $pa, 'token' => $token] = $this->classFixture();
        $member = $this->enrol($club, $package);

        ClassRating::create([
            'package_activity_id' => $pa->id, 'slot_day' => 'monday', 'slot_start' => '11:00',
            'user_id' => $member->id, 'rating' => 2, 'comment' => 'Meh.',
        ]);

        $this->actingAs($member)
            ->deleteJson(route('me.schedule.rate.class.destroy', $token))
            ->assertOk()
            ->assertJsonStructure(['success', 'message', 'average', 'count', 'comments', 'distribution'])
            ->assertJson(['success' => true, 'count' => 0, 'average' => null]);

        $this->assertSame(0, ClassRating::where('package_activity_id', $pa->id)->count());
    }

    // =====================================================================
    // POST /me/schedule/synced/{token}/react  →  me.schedule.react
    // =====================================================================

    public function test_react_class_adds_changes_then_clears_a_reaction(): void
    {
        ['club' => $club, 'package' => $package, 'pa' => $pa, 'token' => $token] = $this->classFixture();
        $member = $this->enrol($club, $package);
        $url = route('me.schedule.react', $token);

        $this->actingAs($member)->postJson($url, ['emoji' => '🔥', 'date' => self::TODAY])
            ->assertOk()
            ->assertJsonStructure(['success', 'mine', 'counts'])
            ->assertJson(['success' => true, 'mine' => '🔥']);

        $this->assertDatabaseHas('class_reactions', [
            'package_activity_id' => $pa->id, 'user_id' => $member->id, 'emoji' => '🔥',
        ]);

        // A different emoji replaces it (still one row).
        $this->actingAs($member)->postJson($url, ['emoji' => '💪', 'date' => self::TODAY])
            ->assertOk()->assertJson(['mine' => '💪']);
        $this->assertSame(1, ClassReaction::where('package_activity_id', $pa->id)->count());

        // Tapping the same emoji again clears it.
        $this->actingAs($member)->postJson($url, ['emoji' => '💪', 'date' => self::TODAY])
            ->assertOk()->assertJson(['mine' => null]);
        $this->assertSame(0, ClassReaction::where('package_activity_id', $pa->id)->count());
    }

    public function test_react_class_rejects_an_emoji_outside_the_allowed_set(): void
    {
        ['club' => $club, 'package' => $package, 'pa' => $pa, 'token' => $token] = $this->classFixture();
        $member = $this->enrol($club, $package);

        $this->actingAs($member)
            ->postJson(route('me.schedule.react', $token), ['emoji' => '💀', 'date' => self::TODAY])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['emoji']);

        $this->assertSame(0, ClassReaction::where('package_activity_id', $pa->id)->count());
    }

    // =====================================================================
    // POST / DELETE /me/schedule/synced/{token}/substitute
    // =====================================================================

    public function test_substitute_assign_creates_the_cover_row(): void
    {
        ['owner' => $owner, 'coach' => $coach, 'pa' => $pa, 'token' => $token] = $this->classFixture();
        $sub = $this->createUser(['full_name' => 'Cover Coach']);

        $res = $this->actingAs($owner)->postJson(route('me.schedule.substitute.assign', $token), [
            'substitute_user_id' => $sub->id,
            'date' => self::NEXT_WEEK,
            'note' => 'Away that week.',
        ]);

        $res->assertOk()
            ->assertJsonStructure(['success', 'message', 'substitute' => ['name', 'date'], 'redirect'])
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('class_substitutions', [
            'package_activity_id' => $pa->id, 'slot_day' => 'monday', 'slot_start' => '11:00',
            'substitute_user_id' => $sub->id, 'original_user_id' => $coach->id,
            'assigned_by' => $owner->id, 'note' => 'Away that week.',
        ]);
    }

    public function test_substitute_assign_rejects_a_past_date_and_an_unknown_user(): void
    {
        ['owner' => $owner, 'pa' => $pa, 'token' => $token] = $this->classFixture();
        $sub = $this->createUser();

        $this->actingAs($owner)->postJson(route('me.schedule.substitute.assign', $token), [
            'substitute_user_id' => $sub->id, 'date' => '2026-01-01',
        ])->assertStatus(422)->assertJsonValidationErrors(['date']);

        $this->actingAs($owner)->postJson(route('me.schedule.substitute.assign', $token), [
            'substitute_user_id' => 999999, 'date' => self::NEXT_WEEK,
        ])->assertStatus(422)->assertJsonValidationErrors(['substitute_user_id']);

        $this->assertSame(0, ClassSubstitution::where('package_activity_id', $pa->id)->count());
    }

    public function test_substitute_assign_refuses_a_trainer_already_busy_at_that_time(): void
    {
        ['owner' => $owner, 'token' => $token] = $this->classFixture();
        $sub = $this->createUser(['full_name' => 'Busy Coach']);

        // A personal session of their own, overlapping the class window.
        UserScheduleSession::create([
            'user_id' => $sub->id, 'subject_user_id' => $sub->id,
            'day' => 'monday', 'start_time' => '11:30', 'end_time' => '12:30', 'title' => 'Own training',
        ]);

        $this->actingAs($owner)->postJson(route('me.schedule.substitute.assign', $token), [
            'substitute_user_id' => $sub->id, 'date' => self::NEXT_WEEK,
        ])->assertStatus(422)->assertJson(['success' => false]);

        $this->assertSame(0, ClassSubstitution::count());
    }

    public function test_substitute_remove_deletes_the_cover_row(): void
    {
        ['owner' => $owner, 'coach' => $coach, 'pa' => $pa, 'token' => $token] = $this->classFixture();
        $sub = $this->createUser();

        ClassSubstitution::create([
            'package_activity_id' => $pa->id, 'slot_day' => 'monday', 'slot_start' => '11:00',
            'date' => self::NEXT_WEEK, 'original_user_id' => $coach->id,
            'substitute_user_id' => $sub->id, 'assigned_by' => $owner->id,
        ]);

        $this->actingAs($owner)
            ->deleteJson(route('me.schedule.substitute.remove', $token), ['date' => self::NEXT_WEEK])
            ->assertOk()
            ->assertJsonStructure(['success', 'message', 'redirect'])
            ->assertJson(['success' => true, 'message' => 'Substitute removed.']);

        $this->assertSame(0, ClassSubstitution::where('package_activity_id', $pa->id)->count());
    }

    public function test_substitute_remove_rejects_a_missing_date_and_keeps_the_row(): void
    {
        ['owner' => $owner, 'coach' => $coach, 'pa' => $pa, 'token' => $token] = $this->classFixture();
        $sub = $this->createUser();

        ClassSubstitution::create([
            'package_activity_id' => $pa->id, 'slot_day' => 'monday', 'slot_start' => '11:00',
            'date' => self::NEXT_WEEK, 'original_user_id' => $coach->id,
            'substitute_user_id' => $sub->id, 'assigned_by' => $owner->id,
        ]);

        $this->actingAs($owner)
            ->deleteJson(route('me.schedule.substitute.remove', $token), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['date']);

        $this->assertSame(1, ClassSubstitution::where('package_activity_id', $pa->id)->count());
    }
}
