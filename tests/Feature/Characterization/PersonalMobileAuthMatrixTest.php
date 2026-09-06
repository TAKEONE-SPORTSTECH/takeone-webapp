<?php

namespace Tests\Feature\Characterization;

use App\Models\ClassAttendance;
use App\Models\ClassCancellation;
use App\Models\ClassProgramOverride;
use App\Models\ClassRating;
use App\Models\ClassReaction;
use App\Models\ClassSubstitution;
use App\Clubs\Models\ClubActivity;
use App\Clubs\Models\ClubInstructor;
use App\Models\ClubMemberSubscription;
use App\Clubs\Models\ClubPackage;
use App\Clubs\Models\ClubPackageActivity;
use App\Clubs\Models\Tenant;
use App\Members\Models\User;
use App\Members\Models\UserScheduleSession;
use App\Support\SyncedClassToken;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Contracts\ContractTestCase;

/**
 * CHARACTERIZATION — the AUTHORIZATION MATRIX of PersonalMobileController's
 * write surface.
 *
 * For every POST/PUT/PATCH/DELETE route the controller serves, this pins:
 *   • what a signed-in user who does NOT own the resource gets, and
 *   • that the row they aimed at is BYTE-FOR-BYTE unchanged afterwards, and
 *   • what a guest gets.
 *
 * The refused-status vocabulary is not uniform across this controller, which
 * is itself worth pinning:
 *   • the personal-session routes scope by `where('user_id', Auth::id())
 *     ->firstOrFail()` → a stranger gets **404**, which leaks nothing;
 *   • the club-class routes hand-roll `response()->json(..., 403)`;
 *   • settlePayment uses `abort_unless(..., 403)`, so per bootstrap/app.php a
 *     JSON call gets a real 403 while a browser POST is redirected to '/'.
 *
 * Guests: a JSON call gets 401, a browser call is redirected to /login.
 */
class PersonalMobileAuthMatrixTest extends ContractTestCase
{
    private const NOW = '2026-09-07 12:00:00';   // a Monday, midday

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
    // Fixtures (kept local — the read/write characterization files each own
    // their own, so neither can be broken by an edit to the other).
    // ---------------------------------------------------------------------

    /** @return array{club: Tenant, owner: User, coach: User, package: ClubPackage, pa: ClubPackageActivity, token: string} */
    private function classFixture(): array
    {
        $owner = $this->createUser(['full_name' => 'Club Owner']);
        $club = $this->createClub($owner, ['country' => 'BH', 'currency' => 'BHD', 'timezone' => 'UTC']);

        $coach = $this->createUser(['full_name' => 'Regular Coach']);
        $instructor = ClubInstructor::factory()->create(['tenant_id' => $club->id, 'user_id' => $coach->id]);

        $package = ClubPackage::factory()->create(['tenant_id' => $club->id]);
        $activity = ClubActivity::factory()->create(['tenant_id' => $club->id, 'name' => 'Taekwondo']);

        $pa = ClubPackageActivity::create([
            'package_id' => $package->id, 'activity_id' => $activity->id, 'instructor_id' => $instructor->id,
        ]);
        $pa->schedule = json_encode([[
            'day' => 'monday', 'start_time' => '11:00', 'end_time' => '13:00', 'title' => 'Evening Sparring',
        ]]);
        $pa->save();

        return [
            'club' => $club, 'owner' => $owner, 'coach' => $coach, 'package' => $package,
            'pa' => $pa->fresh(), 'token' => SyncedClassToken::encode($pa->id, 'monday', '11:00'),
        ];
    }

    private function enrol(Tenant $club, ClubPackage $package): User
    {
        $member = $this->createUser(['full_name' => 'Enrolled Member']);
        $member->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);

        ClubMemberSubscription::create([
            'tenant_id' => $club->id, 'user_id' => $member->id, 'package_id' => $package->id,
            'type' => 'regular', 'status' => 'active', 'payment_status' => 'paid',
            'amount_paid' => 10, 'amount_due' => 0,
            'start_date' => self::TODAY, 'end_date' => '2026-10-07',
        ]);

        return $member->fresh();
    }

    /** An unrelated signed-in member: their own club, their own package, no link to the fixture. */
    private function outsider(): User
    {
        $other = $this->createUser(['full_name' => 'Unrelated Member']);
        $otherOwner = $this->createUser();
        $otherClub = $this->createClub($otherOwner, ['country' => 'BH', 'currency' => 'BHD']);
        $other->memberClubs()->syncWithoutDetaching([$otherClub->id => ['status' => 'active']]);

        return $other->fresh();
    }

    private function pngDataUri(): string
    {
        return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
    }

    // =====================================================================
    // Personal sessions — scoped by user_id, so a stranger gets 404
    // =====================================================================

    public function test_a_stranger_cannot_update_someone_elses_personal_session(): void
    {
        $owner = $this->createUser();
        $session = UserScheduleSession::create([
            'user_id' => $owner->id, 'subject_user_id' => $owner->id,
            'day' => 'monday', 'title' => 'Private plan',
        ]);

        $this->actingAs($this->outsider())->putJson(route('me.schedule.update', $session->id), [
            'subject' => 'me', 'day' => 'friday', 'title' => 'Hijacked',
        ])->assertNotFound();

        $this->assertDatabaseHas('user_schedule_sessions', [
            'id' => $session->id, 'user_id' => $owner->id, 'day' => 'monday', 'title' => 'Private plan',
        ]);
    }

    public function test_a_stranger_cannot_delete_someone_elses_personal_session(): void
    {
        $owner = $this->createUser();
        $session = UserScheduleSession::create([
            'user_id' => $owner->id, 'subject_user_id' => $owner->id,
            'day' => 'monday', 'title' => 'Private plan',
        ]);

        $this->actingAs($this->outsider())
            ->deleteJson(route('me.schedule.destroy', $session->id))
            ->assertNotFound();

        $this->assertDatabaseHas('user_schedule_sessions', ['id' => $session->id, 'user_id' => $owner->id]);
    }

    public function test_a_super_admin_still_cannot_touch_another_members_personal_session(): void
    {
        $owner = $this->createUser();
        $session = UserScheduleSession::create([
            'user_id' => $owner->id, 'subject_user_id' => $owner->id,
            'day' => 'monday', 'title' => 'Private plan',
        ]);

        $admin = $this->createUser();
        $this->makeSuperAdmin($admin);

        // Pinned as-is: the query is `where('user_id', Auth::id())`, with no
        // super-admin escape hatch. A member's personal schedule is theirs alone.
        $this->actingAs($admin)
            ->deleteJson(route('me.schedule.destroy', $session->id))
            ->assertNotFound();

        $this->assertDatabaseHas('user_schedule_sessions', ['id' => $session->id]);
    }

    public function test_guests_are_refused_on_the_personal_session_routes(): void
    {
        $owner = $this->createUser();
        $session = UserScheduleSession::create([
            'user_id' => $owner->id, 'subject_user_id' => $owner->id,
            'day' => 'monday', 'title' => 'Private plan',
        ]);

        $this->postJson(route('me.schedule.store'), ['subject' => 'me', 'day' => 'monday', 'title' => 'x'])
            ->assertUnauthorized();
        $this->putJson(route('me.schedule.update', $session->id), ['subject' => 'me', 'day' => 'monday', 'title' => 'x'])
            ->assertUnauthorized();
        $this->deleteJson(route('me.schedule.destroy', $session->id))->assertUnauthorized();

        // A browser (non-JSON) call is redirected to the login screen instead.
        $this->post(route('me.schedule.store'), ['subject' => 'me', 'day' => 'monday', 'title' => 'x'])
            ->assertRedirect(route('login'));

        $this->assertSame(1, UserScheduleSession::count());
        $this->assertDatabaseHas('user_schedule_sessions', ['id' => $session->id, 'title' => 'Private plan']);
    }

    // =====================================================================
    // /me/discoverable and /me/seen — self-scoped, guests only
    // =====================================================================

    public function test_guests_cannot_toggle_discoverability_or_mark_a_section_seen(): void
    {
        $user = $this->createUser(['is_discoverable' => true]);

        $this->putJson(route('me.discoverable.update'), ['is_discoverable' => false])->assertUnauthorized();
        $this->postJson(route('me.seen'), ['section' => 'feed:all'])->assertUnauthorized();
        $this->put(route('me.discoverable.update'), ['is_discoverable' => false])->assertRedirect(route('login'));

        $this->assertDatabaseHas('users', ['id' => $user->id, 'is_discoverable' => true]);
        $this->assertDatabaseCount('user_section_views', 0);
    }

    public function test_discoverability_only_ever_writes_the_actors_own_row(): void
    {
        $a = $this->createUser(['is_discoverable' => true]);
        $b = $this->createUser(['is_discoverable' => true]);

        // There is no id in the payload to tamper with — the subject is taken
        // from the session. A stray user_id must not change that.
        $this->actingAs($a)
            ->putJson(route('me.discoverable.update'), ['is_discoverable' => false, 'user_id' => $b->id])
            ->assertOk();

        $this->assertDatabaseHas('users', ['id' => $a->id, 'is_discoverable' => false]);
        $this->assertDatabaseHas('users', ['id' => $b->id, 'is_discoverable' => true]);
    }

    // =====================================================================
    // me.payments.settle — MONEY. The most sensitive write here.
    // =====================================================================

    /** @return array{0: User, 1: Tenant, 2: ClubMemberSubscription} */
    private function unpaidBill(): array
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner, ['country' => 'BH', 'currency' => 'BHD']);
        $package = ClubPackage::factory()->create(['tenant_id' => $club->id]);
        $member = $this->createUser(['full_name' => 'Paying Member']);
        $member->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);

        $sub = ClubMemberSubscription::create([
            'tenant_id' => $club->id, 'user_id' => $member->id, 'package_id' => $package->id,
            'type' => 'regular', 'status' => 'active', 'payment_status' => 'unpaid',
            'amount_paid' => 0, 'amount_due' => 45,
            'start_date' => self::TODAY, 'end_date' => '2026-10-07',
        ]);

        return [$member, $club, $sub];
    }

    public function test_a_stranger_cannot_settle_another_members_bill(): void
    {
        [, , $sub] = $this->unpaidBill();

        $this->actingAs($this->outsider())
            ->postJson(route('me.payments.settle', $sub->id), ['payment_proof_base64' => $this->pngDataUri()])
            ->assertForbidden();

        $this->assertDatabaseHas('club_member_subscriptions', [
            'id' => $sub->id, 'payment_status' => 'unpaid', 'proof_of_payment' => null,
        ]);
        $this->assertEmpty(Storage::disk('local')->allFiles('payment-proofs'));
        $this->assertDatabaseCount('user_notifications', 0);
    }

    public function test_the_members_own_club_owner_cannot_settle_the_bill_on_their_behalf(): void
    {
        [, $club, $sub] = $this->unpaidBill();
        $owner = User::find($club->owner_user_id);

        // Pinned as-is: settling is the MEMBER's act (self, guardian or
        // super-admin). The club owner APPROVES a payment elsewhere; they may
        // not submit one as if they were the member.
        $this->actingAs($owner)
            ->postJson(route('me.payments.settle', $sub->id), ['payment_proof_base64' => $this->pngDataUri()])
            ->assertForbidden();

        $this->assertDatabaseHas('club_member_subscriptions', [
            'id' => $sub->id, 'payment_status' => 'unpaid', 'proof_of_payment' => null,
        ]);
        $this->assertEmpty(Storage::disk('local')->allFiles('payment-proofs'));
    }

    public function test_a_browser_post_by_an_unauthorized_user_is_redirected_home_not_403(): void
    {
        [, , $sub] = $this->unpaidBill();

        // bootstrap/app.php converts a 403 on browser navigation into a redirect
        // to '/' with an error flash. Access is still denied.
        $this->actingAs($this->outsider())
            ->post(route('me.payments.settle', $sub->id), ['payment_proof_base64' => $this->pngDataUri()])
            ->assertRedirect('/');

        $this->assertDatabaseHas('club_member_subscriptions', [
            'id' => $sub->id, 'payment_status' => 'unpaid', 'proof_of_payment' => null,
        ]);
    }

    public function test_a_guest_cannot_settle_a_bill(): void
    {
        [, , $sub] = $this->unpaidBill();

        $this->postJson(route('me.payments.settle', $sub->id), ['payment_proof_base64' => $this->pngDataUri()])
            ->assertUnauthorized();

        $this->assertDatabaseHas('club_member_subscriptions', [
            'id' => $sub->id, 'payment_status' => 'unpaid', 'proof_of_payment' => null,
        ]);
        $this->assertEmpty(Storage::disk('local')->allFiles('payment-proofs'));
    }

    public function test_a_super_admin_may_settle_any_bill(): void
    {
        [, , $sub] = $this->unpaidBill();
        $admin = $this->createUser();
        $this->makeSuperAdmin($admin);

        $this->actingAs($admin)
            ->postJson(route('me.payments.settle', $sub->id), ['payment_proof_base64' => $this->pngDataUri()])
            ->assertOk()
            ->assertJson(['success' => true, 'payment_status' => 'pending_approval']);

        $this->assertDatabaseHas('club_member_subscriptions', ['id' => $sub->id, 'payment_status' => 'pending_approval']);
    }

    // =====================================================================
    // Club-class MANAGEMENT routes — coach or club manager only
    // (synced update, cancel, uncancel, program reset, substitutes, attendance)
    // =====================================================================

    public function test_an_enrolled_member_cannot_edit_the_class_schedule(): void
    {
        ['club' => $club, 'package' => $package, 'pa' => $pa, 'token' => $token] = $this->classFixture();
        $member = $this->enrol($club, $package);
        $before = $pa->schedule;

        $this->actingAs($member)->putJson(route('me.schedule.synced.update', $token), [
            'day' => 'friday', 'start_time' => '06:00', 'title' => 'Hijacked',
        ])->assertForbidden()->assertJson(['success' => false]);

        $this->assertSame($before, $pa->fresh()->schedule);
    }

    public function test_an_outsider_cannot_edit_the_class_schedule(): void
    {
        ['pa' => $pa, 'token' => $token] = $this->classFixture();
        $before = $pa->schedule;

        $this->actingAs($this->outsider())
            ->putJson(route('me.schedule.synced.update', $token), ['day' => 'friday', 'title' => 'Hijacked'])
            ->assertForbidden();

        $this->assertSame($before, $pa->fresh()->schedule);
    }

    public function test_a_club_admin_of_a_DIFFERENT_club_cannot_edit_the_class(): void
    {
        ['pa' => $pa, 'token' => $token] = $this->classFixture();
        $before = $pa->schedule;

        $stranger = $this->createUser();
        $otherOwner = $this->createUser();
        $otherClub = $this->createClub($otherOwner, ['country' => 'BH', 'currency' => 'BHD']);
        $this->makeClubAdmin($stranger, $otherClub);

        $this->actingAs($stranger)
            ->putJson(route('me.schedule.synced.update', $token), ['day' => 'friday', 'title' => 'Hijacked'])
            ->assertForbidden();

        $this->assertSame($before, $pa->fresh()->schedule);
    }

    public function test_the_assigned_coach_and_the_club_owner_may_both_edit_the_class(): void
    {
        ['owner' => $owner, 'coach' => $coach, 'pa' => $pa, 'token' => $token] = $this->classFixture();

        $this->actingAs($coach)->putJson(route('me.schedule.synced.update', $token), [
            'day' => 'monday', 'start_time' => '11:00', 'end_time' => '13:00', 'title' => 'By the coach',
        ])->assertOk();
        $this->assertSame('By the coach', json_decode($pa->fresh()->schedule, true)[0]['title']);

        $this->actingAs($owner)->putJson(route('me.schedule.synced.update', $token), [
            'day' => 'monday', 'start_time' => '11:00', 'end_time' => '13:00', 'title' => 'By the owner',
        ])->assertOk();
        $this->assertSame('By the owner', json_decode($pa->fresh()->schedule, true)[0]['title']);
    }

    public function test_an_enrolled_member_cannot_mark_attendance(): void
    {
        ['club' => $club, 'package' => $package, 'pa' => $pa, 'token' => $token] = $this->classFixture();
        $member = $this->enrol($club, $package);

        $this->actingAs($member)->postJson(route('me.schedule.attendance.toggle', $token), [
            'user_id' => $member->id, 'date' => self::TODAY,
        ])->assertForbidden()->assertJson(['success' => false]);

        $this->assertSame(0, ClassAttendance::where('package_activity_id', $pa->id)->count());
    }

    public function test_an_enrolled_member_cannot_delete_an_attendance_row_by_toggling_it(): void
    {
        ['club' => $club, 'coach' => $coach, 'package' => $package, 'pa' => $pa, 'token' => $token] = $this->classFixture();
        $member = $this->enrol($club, $package);

        $row = ClassAttendance::create([
            'package_activity_id' => $pa->id, 'slot_day' => 'monday', 'slot_start' => '11:00',
            'date' => self::TODAY, 'user_id' => $member->id, 'marked_by' => $coach->id,
        ]);

        $this->actingAs($member)->postJson(route('me.schedule.attendance.toggle', $token), [
            'user_id' => $member->id, 'date' => self::TODAY,
        ])->assertForbidden();

        $this->assertDatabaseHas('class_attendances', ['id' => $row->id, 'marked_by' => $coach->id]);
    }

    public function test_an_enrolled_member_cannot_cancel_or_uncancel_the_class(): void
    {
        ['club' => $club, 'coach' => $coach, 'package' => $package, 'pa' => $pa, 'token' => $token] = $this->classFixture();
        $member = $this->enrol($club, $package);

        $this->actingAs($member)
            ->postJson(route('me.schedule.cancel', $token), ['from' => self::TODAY])
            ->assertForbidden();
        $this->assertSame(0, ClassCancellation::where('package_activity_id', $pa->id)->count());

        // A real cancellation by the coach must survive an outsider's uncancel.
        $this->actingAs($coach)->postJson(route('me.schedule.cancel', $token), ['from' => self::TODAY])->assertOk();

        $this->actingAs($member)
            ->deleteJson(route('me.schedule.uncancel', $token), ['date' => self::TODAY])
            ->assertForbidden();
        $this->assertSame(1, ClassCancellation::where('package_activity_id', $pa->id)->count());
    }

    public function test_an_enrolled_member_cannot_reset_the_program_override(): void
    {
        ['club' => $club, 'owner' => $owner, 'package' => $package, 'pa' => $pa, 'token' => $token] = $this->classFixture();
        $member = $this->enrol($club, $package);

        $override = ClassProgramOverride::create([
            'package_activity_id' => $pa->id, 'slot_day' => 'monday', 'slot_start' => '11:00',
            'date' => self::NEXT_WEEK, 'intensity' => 'Low', 'set_by' => $owner->id,
        ]);

        $this->actingAs($member)
            ->deleteJson(route('me.schedule.program.reset', $token), ['date' => self::NEXT_WEEK])
            ->assertForbidden()->assertJson(['success' => false]);

        $this->assertDatabaseHas('class_program_overrides', ['id' => $override->id, 'intensity' => 'Low']);
    }

    public function test_an_enrolled_member_cannot_assign_or_remove_a_substitute(): void
    {
        ['club' => $club, 'owner' => $owner, 'coach' => $coach, 'package' => $package, 'pa' => $pa, 'token' => $token] = $this->classFixture();
        $member = $this->enrol($club, $package);
        $sub = $this->createUser(['full_name' => 'Cover Coach']);

        $this->actingAs($member)->postJson(route('me.schedule.substitute.assign', $token), [
            'substitute_user_id' => $sub->id, 'date' => self::NEXT_WEEK,
        ])->assertForbidden();
        $this->assertSame(0, ClassSubstitution::count());

        $row = ClassSubstitution::create([
            'package_activity_id' => $pa->id, 'slot_day' => 'monday', 'slot_start' => '11:00',
            'date' => self::NEXT_WEEK, 'original_user_id' => $coach->id,
            'substitute_user_id' => $sub->id, 'assigned_by' => $owner->id,
        ]);

        $this->actingAs($member)
            ->deleteJson(route('me.schedule.substitute.remove', $token), ['date' => self::NEXT_WEEK])
            ->assertForbidden();

        $this->assertDatabaseHas('class_substitutions', ['id' => $row->id, 'substitute_user_id' => $sub->id]);
    }

    public function test_an_assigned_substitute_may_run_the_class_but_not_edit_its_plan(): void
    {
        ['club' => $club, 'owner' => $owner, 'coach' => $coach, 'package' => $package, 'pa' => $pa, 'token' => $token] = $this->classFixture();
        $member = $this->enrol($club, $package);
        $sub = $this->createUser(['full_name' => 'Cover Coach']);

        ClassSubstitution::create([
            'package_activity_id' => $pa->id, 'slot_day' => 'monday', 'slot_start' => '11:00',
            'date' => self::TODAY, 'original_user_id' => $coach->id,
            'substitute_user_id' => $sub->id, 'assigned_by' => $owner->id,
        ]);

        // canRunClass() → attendance and cancellation are open to the substitute…
        $this->actingAs($sub)->postJson(route('me.schedule.attendance.toggle', $token), [
            'user_id' => $member->id, 'date' => self::TODAY,
        ])->assertOk()->assertJson(['attended' => true]);

        // …but canEditClass() is not, so the recurring plan and the substitute
        // roster stay out of their reach.
        $before = $pa->fresh()->schedule;
        $this->actingAs($sub)
            ->putJson(route('me.schedule.synced.update', $token), ['day' => 'friday', 'title' => 'Hijacked'])
            ->assertForbidden();
        $this->assertSame($before, $pa->fresh()->schedule);

        $this->actingAs($sub)->postJson(route('me.schedule.substitute.assign', $token), [
            'substitute_user_id' => $this->createUser()->id, 'date' => self::NEXT_WEEK,
        ])->assertForbidden();
        $this->assertSame(1, ClassSubstitution::count());
    }

    // =====================================================================
    // Club-class MEMBER routes — enrolled trainees only
    // (rate trainer, rate class, delete class review, react)
    // =====================================================================

    public function test_a_non_enrolled_user_cannot_rate_the_trainer_the_class_or_react(): void
    {
        ['token' => $token, 'pa' => $pa] = $this->classFixture();
        $outsider = $this->outsider();

        $this->actingAs($outsider)->postJson(route('me.schedule.rate', $token), ['rating' => 5])
            ->assertForbidden()->assertJson(['success' => false]);
        $this->assertDatabaseCount('instructor_reviews', 0);

        $this->actingAs($outsider)->postJson(route('me.schedule.rate.class', $token), ['rating' => 5])
            ->assertForbidden();
        $this->assertSame(0, ClassRating::count());

        $this->actingAs($outsider)->postJson(route('me.schedule.react', $token), ['emoji' => '🔥', 'date' => self::TODAY])
            ->assertForbidden();
        $this->assertSame(0, ClassReaction::where('package_activity_id', $pa->id)->count());
    }

    public function test_the_club_owner_who_is_not_enrolled_cannot_rate_the_class(): void
    {
        ['owner' => $owner, 'token' => $token] = $this->classFixture();

        // Pinned as-is: managing a club is not the same as attending its class.
        // Rating is gated on enrolment, and the owner is not enrolled.
        $this->actingAs($owner)->postJson(route('me.schedule.rate.class', $token), ['rating' => 1])
            ->assertForbidden();

        $this->assertSame(0, ClassRating::count());
    }

    public function test_a_non_enrolled_user_cannot_delete_an_enrolled_members_class_review(): void
    {
        ['club' => $club, 'package' => $package, 'pa' => $pa, 'token' => $token] = $this->classFixture();
        $member = $this->enrol($club, $package);

        $review = ClassRating::create([
            'package_activity_id' => $pa->id, 'slot_day' => 'monday', 'slot_start' => '11:00',
            'user_id' => $member->id, 'rating' => 5, 'comment' => 'Loved it.',
        ]);

        $this->actingAs($this->outsider())
            ->deleteJson(route('me.schedule.rate.class.destroy', $token))
            ->assertForbidden();

        $this->assertDatabaseHas('class_ratings', ['id' => $review->id, 'rating' => 5, 'comment' => 'Loved it.']);
    }

    public function test_one_enrolled_member_cannot_delete_another_enrolled_members_class_review(): void
    {
        ['club' => $club, 'package' => $package, 'pa' => $pa, 'token' => $token] = $this->classFixture();
        $a = $this->enrol($club, $package);
        $b = $this->enrol($club, $package);

        $reviewA = ClassRating::create([
            'package_activity_id' => $pa->id, 'slot_day' => 'monday', 'slot_start' => '11:00',
            'user_id' => $a->id, 'rating' => 5, 'comment' => 'A liked it.',
        ]);

        // The delete is scoped to `where('user_id', Auth::id())`, so B's call
        // succeeds (200) but removes only B's own review — A's is untouched.
        $this->actingAs($b)->deleteJson(route('me.schedule.rate.class.destroy', $token))->assertOk();

        $this->assertDatabaseHas('class_ratings', ['id' => $reviewA->id, 'rating' => 5, 'comment' => 'A liked it.']);
    }

    public function test_one_enrolled_member_cannot_overwrite_another_members_reaction(): void
    {
        ['club' => $club, 'package' => $package, 'pa' => $pa, 'token' => $token] = $this->classFixture();
        $a = $this->enrol($club, $package);
        $b = $this->enrol($club, $package);

        ClassReaction::create([
            'package_activity_id' => $pa->id, 'slot_day' => 'monday', 'slot_start' => '11:00',
            'date' => self::TODAY, 'user_id' => $a->id, 'emoji' => '🔥',
        ]);

        $this->actingAs($b)->postJson(route('me.schedule.react', $token), ['emoji' => '👏', 'date' => self::TODAY])
            ->assertOk()->assertJson(['mine' => '👏']);

        $this->assertDatabaseHas('class_reactions', [
            'package_activity_id' => $pa->id, 'user_id' => $a->id, 'emoji' => '🔥',
        ]);
        $this->assertSame(2, ClassReaction::where('package_activity_id', $pa->id)->count());
    }

    // =====================================================================
    // Guests — every club-class write door
    // =====================================================================

    public function test_guests_are_refused_on_every_club_class_write_route(): void
    {
        ['club' => $club, 'package' => $package, 'pa' => $pa, 'token' => $token] = $this->classFixture();
        $member = $this->enrol($club, $package);
        $before = $pa->schedule;

        $this->putJson(route('me.schedule.synced.update', $token), ['day' => 'friday'])->assertUnauthorized();
        $this->postJson(route('me.schedule.attendance.toggle', $token), ['user_id' => $member->id, 'date' => self::TODAY])->assertUnauthorized();
        $this->postJson(route('me.schedule.cancel', $token), ['from' => self::TODAY])->assertUnauthorized();
        $this->deleteJson(route('me.schedule.uncancel', $token), ['date' => self::TODAY])->assertUnauthorized();
        $this->deleteJson(route('me.schedule.program.reset', $token), ['date' => self::TODAY])->assertUnauthorized();
        $this->postJson(route('me.schedule.rate', $token), ['rating' => 5])->assertUnauthorized();
        $this->postJson(route('me.schedule.rate.class', $token), ['rating' => 5])->assertUnauthorized();
        $this->deleteJson(route('me.schedule.rate.class.destroy', $token))->assertUnauthorized();
        $this->postJson(route('me.schedule.react', $token), ['emoji' => '🔥', 'date' => self::TODAY])->assertUnauthorized();
        $this->postJson(route('me.schedule.substitute.assign', $token), ['substitute_user_id' => $member->id, 'date' => self::NEXT_WEEK])->assertUnauthorized();
        $this->deleteJson(route('me.schedule.substitute.remove', $token), ['date' => self::NEXT_WEEK])->assertUnauthorized();

        $this->assertSame($before, $pa->fresh()->schedule);
        $this->assertSame(0, ClassAttendance::count());
        $this->assertSame(0, ClassCancellation::count());
        $this->assertSame(0, ClassProgramOverride::count());
        $this->assertSame(0, ClassRating::count());
        $this->assertSame(0, ClassReaction::count());
        $this->assertSame(0, ClassSubstitution::count());
        $this->assertDatabaseCount('instructor_reviews', 0);
    }

    public function test_an_unverified_member_is_bounced_off_the_write_routes(): void
    {
        $user = $this->createUnverifiedUser();

        // EnsureEmailIsVerifiedOrImpersonating sits in front of every /me write.
        $this->actingAs($user)
            ->postJson(route('me.schedule.store'), ['subject' => 'me', 'day' => 'monday', 'title' => 'x'])
            ->assertForbidden();

        $this->assertSame(0, UserScheduleSession::count());
    }
}
