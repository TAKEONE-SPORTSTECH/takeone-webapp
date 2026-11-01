<?php

namespace Tests\Feature\Registration;

use App\Members\Mail\WelcomeEmail;
use App\Members\Models\User;
use App\Members\Models\UserRelationship;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * CHARACTERISATION AUDIT of every door through which a person account is
 * created. Each test drives a real HTTP request and asserts what the code
 * does TODAY — including the behaviour we believe is wrong — so the suite
 * passes now and FAILS the moment the behaviour changes. When a bug here is
 * fixed, update the assertion (and its AUDIT line) in the same change.
 *
 * Group: registration-audit
 */
#[Group('registration-audit')]
class MemberAndClubDoorsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Notification::fake();
        // Several sign-ups per test would trip `throttle:register` (3/min/IP).
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    // ------------------------------------------------------------------ helpers

    private function signUp(array $overrides = [])
    {
        return $this->post('/register', array_merge([
            'full_name' => 'Ali Hassan',
            'email' => 'ali@x.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'mobile_number' => '33001122',
            'country_code' => '+973',
            'gender' => 'Male',
            'birthdate' => '1990-01-01',
            'nationality' => 'Bahraini',
        ], $overrides));
    }

    private function wizardPayload(string $clubSlug, array $overrides = []): array
    {
        return array_merge([
            'full_name' => 'Mona Parent',
            'email' => 'mona@x.com',
            'mobile_code' => '+973',
            'mobile_number' => '33445566',
            'nationality' => 'Bahraini',
            'club_slug' => $clubSlug,
            'self_gender' => 'Female',
            'self_birthdate' => '1985-03-03',
            'children' => [[
                'full_name' => 'Sara Kid',
                'gender' => 'Female',
                'birthdate' => '2016-05-05',
                'relationship' => 'daughter',
            ]],
        ], $overrides);
    }

    private function addDependent(User $guardian, array $overrides = [])
    {
        return $this->actingAs($guardian)->postJson('/family', array_merge([
            'full_name' => 'Sara Kid',
            'gender' => 'Female',
            'birthdate' => '2016-05-05',
            'nationality' => 'BH',
            'relationship_type' => 'daughter',
        ], $overrides));
    }

    private function namedCount(string $name): int
    {
        return User::where('full_name', $name)->count();
    }

    // ------------------------------------------------------------ 1. sign-up

    /** AUDIT: FIXED (2026-10-31) — one sign-up queues exactly ONE WelcomeEmail (the explicit one, carrying the intended URL); the Registered-event duplicate is gone. */
    public function test_1_normal_sign_up_queues_one_welcome_email(): void
    {
        $this->signUp()->assertRedirect(route('verification.notice'));

        $user = User::where('email', 'ali@x.com')->firstOrFail();
        $this->assertNull($user->email_verified_at);
        Mail::assertQueued(WelcomeEmail::class, 1);
        Mail::assertQueued(WelcomeEmail::class, fn ($m) => $m->hasTo('ali@x.com'));
        Notification::assertNothingSent();
    }

    /** AUDIT: FIXED 2026-10-31 — the unique check ignores case (App\Members\Support\UniqueEmail), so `Ali@x.com` is refused while `ali@x.com` exists; a new address is stored lower-cased. Not behind a switch. */
    public function test_2_sign_up_with_email_differing_only_in_case_is_refused(): void
    {
        $this->createUser(['full_name' => 'Ali Hassan', 'email' => 'ali@x.com']);

        $this->signUp(['email' => ' Ali@X.com '])->assertSessionHasErrors('email');

        $this->assertSame(1, User::whereRaw('LOWER(email) = ?', ['ali@x.com'])->count());
        Mail::assertNotQueued(WelcomeEmail::class);

        // A fresh mixed-case address is stored lower-cased and trimmed.
        $this->signUp(['email' => ' Sara@X.com ', 'full_name' => 'Sara Ali', 'mobile_number' => '39990001'])
            ->assertRedirect(route('verification.notice'));
        $this->assertSame(1, User::where('email', 'sara@x.com')->count());
    }

    /** AUDIT: guard OFF — a phone-only account (same name, same mobile) is not matched; sign-up creates a 2nd person. */
    public function test_3_sign_up_by_someone_with_a_phone_only_account_creates_a_second_account(): void
    {
        $existing = $this->createUser([
            'full_name' => 'Ali Hassan', 'email' => null,
            'mobile' => ['code' => '+973', 'number' => '33001122'],
        ]);

        $this->signUp()->assertRedirect(route('verification.notice'));

        $this->assertSame(2, $this->namedCount('Ali Hassan'));
        $this->assertSame(2, User::where('phone_key', $existing->fresh()->phone_key)->count());
    }

    /** AUDIT: FIXED behind duplicates.guard.registration — the same phone + a matching name on a no-email account refuses the sign-up on `mobile_number` (sign in instead); the account is never adopted. A sibling on the same phone is NOT blocked. */
    public function test_3b_phone_only_account_refuses_the_sign_up_with_the_registration_guard_on(): void
    {
        config(['duplicates.guard.registration' => true]);
        $existing = $this->createUser([
            'full_name' => 'Mohammed Hassan', 'email' => null,
            'mobile' => ['code' => '+973', 'number' => '33001122'],
        ]);

        $this->signUp(['full_name' => 'Mohd Hassan', 'mobile_number' => '033001122'])->assertSessionHasErrors('mobile_number');
        $this->assertSame(1, User::where('phone_key', $existing->fresh()->phone_key)->count());
        $this->assertNull($existing->fresh()->email, 'never adopted');

        // A sister on her father's phone is a different person.
        $this->signUp(['full_name' => 'Noor Hassan', 'email' => 'noor@x.com', 'gender' => 'Female'])
            ->assertRedirect(route('verification.notice'));
        $this->assertSame(2, User::where('phone_key', $existing->fresh()->phone_key)->count());
    }

    // ------------------------------------------------------------- 4. wizard

    /** AUDIT: duplicate BUG — a returning (OTP-verified) parent re-adding the same child gets a 2nd child account; parent gets 2 WelcomeEmails over two visits (FIXED 2026-10-31: 1 on sign-up — was 2 — and 1 more on return); child (no email) gets none. */
    public function test_4_qr_wizard_returning_parent_adding_the_same_child_creates_a_duplicate_child(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner, ['country' => 'BH']);

        $this->postJson('/register/wizard/submit', $this->wizardPayload($club->slug))->assertOk();

        $parent = User::where('email', 'mona@x.com')->firstOrFail();
        $this->assertSame(1, $this->namedCount('Sara Kid'));
        Mail::assertQueued(WelcomeEmail::class, 1);

        // Same person comes back: an un-verified session is refused…
        $this->flushSession();
        $this->postJson('/register/wizard/submit', $this->wizardPayload($club->slug))
            ->assertStatus(422)->assertJson(['existing_account' => true]);

        // …and once the OTP gate is passed, the same child is created again.
        $this->withSession(['wizard.verified' => [$parent->id => now()->timestamp]]);
        $this->postJson('/register/wizard/submit', $this->wizardPayload($club->slug))->assertOk();

        $this->assertSame(1, User::where('email', 'mona@x.com')->count());
        $this->assertSame(2, $this->namedCount('Sara Kid'));
        $this->assertSame(2, UserRelationship::where('guardian_user_id', $parent->id)->count());
        Mail::assertQueued(WelcomeEmail::class, 2);
        Mail::assertQueued(WelcomeEmail::class, fn ($m) => $m->hasTo('mona@x.com'));
    }

    /** FIXED (duplicates.guard.registration ON): a returning parent re-adding the same child enrols the existing child — no second account, no second relationship. (Mail is asserted in test_4.) */
    public function test_4b_guard_wizard_returning_parent_reuses_the_same_child(): void
    {
        config(['duplicates.guard.registration' => true]);
        $owner = $this->createUser();
        $club = $this->createClub($owner, ['country' => 'BH']);

        $this->postJson('/register/wizard/submit', $this->wizardPayload($club->slug))->assertOk();
        $parent = User::where('email', 'mona@x.com')->firstOrFail();

        $this->flushSession();
        $this->withSession(['wizard.verified' => [$parent->id => now()->timestamp]]);
        $this->postJson('/register/wizard/submit', $this->wizardPayload($club->slug, [
            'children' => [[
                'full_name' => 'sara  kid', 'gender' => 'Female', 'birthdate' => '2016-05-05', 'relationship' => 'daughter',
            ]],
        ]))->assertOk();

        $this->assertSame(1, User::whereRaw('LOWER(full_name) LIKE ?', ['%sara%kid%'])->count());
        $this->assertSame(1, UserRelationship::where('guardian_user_id', $parent->id)->count());
    }

    // ------------------------------------------------------------ 5. family

    /** AUDIT: duplicate BUG — same guardian adding the same dependent twice gets 2 accounts; mum and dad each adding the child gets a 3rd; no mail (no email). */
    public function test_5_guardians_adding_the_same_dependent_create_duplicates(): void
    {
        $mum = $this->createUser(['full_name' => 'Mona Parent']);
        $dad = $this->createUser(['full_name' => 'Hamad Parent']);

        $this->addDependent($mum)->assertOk()->assertJson(['success' => true]);
        $this->addDependent($mum)->assertOk()->assertJson(['success' => true]);
        $this->assertSame(2, $this->namedCount('Sara Kid'));

        $this->addDependent($dad)->assertOk()->assertJson(['success' => true]);
        $this->assertSame(3, $this->namedCount('Sara Kid'));

        Mail::assertNothingQueued();
        Mail::assertNothingSent();
    }

    /** FIXED (duplicates.guard.registration ON): the same guardian adding the same dependent again is refused on full_name; another guardian is NOT matched (privacy) and still creates their own. */
    public function test_5b_guard_refuses_the_same_dependent_for_the_same_guardian_only(): void
    {
        config(['duplicates.guard.registration' => true]);
        $mum = $this->createUser(['full_name' => 'Mona Parent']);
        $dad = $this->createUser(['full_name' => 'Hamad Parent']);

        $this->addDependent($mum)->assertOk()->assertJson(['success' => true]);
        $this->addDependent($mum, ['full_name' => '  sara   KID '])
            ->assertStatus(422)->assertJsonValidationErrors(['full_name']);
        $this->assertSame(1, User::whereRaw('LOWER(full_name) LIKE ?', ['%sara%kid%'])->count());

        // A different birthdate is a different child.
        $this->addDependent($mum, ['birthdate' => '2018-01-01'])->assertOk();
        $this->assertSame(2, $this->namedCount('Sara Kid'));

        // Never matched across guardians.
        $this->addDependent($dad)->assertOk()->assertJson(['success' => true]);
        $this->assertSame(3, $this->namedCount('Sara Kid'));
    }

    // ------------------------------------------------------------ 6. walk-in

    /** AUDIT: OPEN (deliberately not auto-fixed) — the desk cannot reuse an existing guardian (email → 422 unique), so it re-enters them without email and both guardian and an already-added child are duplicated; no mail. Reusing an account by typed email would hand the club that person's profile, so the duplicate watch covers this instead. */
    public function test_6_desk_walk_in_duplicates_a_child_a_guardian_already_added(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner);
        $mum = $this->createUser([
            'full_name' => 'Mona Parent', 'email' => 'mona@x.com',
            'mobile' => ['code' => '+973', 'number' => '33445566'],
        ]);
        $this->addDependent($mum)->assertOk();
        $this->assertSame(1, $this->namedCount('Sara Kid'));

        $payload = fn (?string $email) => [
            'guardian' => [
                'name' => 'Mona Parent', 'email' => $email, 'phone' => '33445566',
                'dob' => '1985-03-03', 'gender' => 'Female', 'nationality' => 'BH', 'countryCode' => '+973',
            ],
            'people' => [
                ['name' => 'Mona Parent', 'dob' => '1985-03-03', 'gender' => 'Female', 'type' => 'guardian', 'selectedPackageIds' => []],
                ['name' => 'Sara Kid', 'dob' => '2016-05-05', 'gender' => 'Female', 'type' => 'child', 'selectedPackageIds' => []],
            ],
        ];

        $this->actingAs($owner)
            ->postJson("/admin/club/{$club->slug}/members/walk-in", $payload('mona@x.com'))
            ->assertStatus(422)->assertJsonValidationErrors(['guardian.email']);

        $this->actingAs($owner)
            ->postJson("/admin/club/{$club->slug}/members/walk-in", $payload(null))
            ->assertOk()->assertJson(['success' => true]);

        $this->assertSame(2, $this->namedCount('Mona Parent'));
        $this->assertSame(2, $this->namedCount('Sara Kid'));
        // Desk accounts are marked verified.
        $this->assertNotNull(User::where('full_name', 'Sara Kid')->latest('id')->first()->email_verified_at);
        Mail::assertNothingQueued();

    }
    // ------------------------------------------------------------- 7. import

    private function importFile(array $rows): UploadedFile
    {
        $book = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->fromArray(array_merge([['First Name', 'Last Name', 'Gender', 'Date of Birth', 'Phone', 'Email']], $rows));
        $path = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save($path);

        return new UploadedFile($path, 'members.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    /** FIXED: the import works on PhpSpreadsheet 5 and staff-imported accounts are verified. Guard OFF: re-running the file re-creates the email-less row (old behaviour). */
    public function test_7_club_import_creates_verified_members(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner);
        $before = User::count();
        $rows = [
            ['Sara', 'Kid', 'Female', '2016-05-05', '+97333445566', ''],
            ['Ali', 'Hassan', 'Male', '1990-01-01', '+97333001122', 'ali@x.com'],
        ];

        $this->actingAs($owner)
            ->postJson("/admin/club/{$club->slug}/members/import", ['import_file' => $this->importFile($rows)])
            ->assertOk()->assertJson(['success' => true, 'imported' => 2]);

        $this->assertSame($before + 2, User::count());
        $sara = User::where('full_name', 'Sara Kid')->firstOrFail();
        $this->assertSame('2016-05-05', $sara->birthdate->toDateString());
        // fromArray() binds "+973…" as a number, so the cell reads back without its "+".
        $this->assertSame('+973', $sara->mobile['code']);
        $this->assertNotNull($sara->email_verified_at);
        $this->assertNotNull(User::where('email', 'ali@x.com')->first()->email_verified_at);
        $this->assertTrue(\App\Members\Models\Membership::where('tenant_id', $club->id)->where('user_id', $sara->id)->exists());

        // Guard off → the same file again duplicates the email-less row.
        $this->actingAs($owner)
            ->postJson("/admin/club/{$club->slug}/members/import", ['import_file' => $this->importFile($rows)])
            ->assertOk();
        $this->assertSame(2, $this->namedCount('Sara Kid'));
        $this->assertSame(1, User::where('email', 'ali@x.com')->count());
        Mail::assertNothingQueued();
    }

    /** FIXED (duplicates.guard.registration ON): re-running the same file skips people already members of THIS club by name + birthdate and reports them. */
    public function test_7b_guard_import_rerun_skips_existing_club_members(): void
    {
        config(['duplicates.guard.registration' => true]);
        $owner = $this->createUser();
        $club = $this->createClub($owner);
        $rows = [
            ['Sara', 'Kid', 'Female', '2016-05-05', '+97333445566', ''],
            ['Ali', 'Hassan', 'Male', '1990-01-01', '+97333001122', 'ali@x.com'],
        ];
        $this->actingAs($owner)
            ->postJson("/admin/club/{$club->slug}/members/import", ['import_file' => $this->importFile($rows)])
            ->assertOk()->assertJson(['imported' => 2, 'already_members' => 0]);
        $before = User::count();

        $this->actingAs($owner)
            ->postJson("/admin/club/{$club->slug}/members/import", ['import_file' => $this->importFile([
                ['SARA', 'kid', 'Female', '2016-05-05', '+97333445566', ''],
                ['Sara', 'Kid', 'Female', '2017-02-02', '+97333445566', ''],
            ])])
            ->assertOk()->assertJson(['success' => true, 'imported' => 1, 'already_members' => 1])
            ->assertJsonFragment(['message' => 'Import complete — 1 member(s) added. 1 already member(s) — not added again.']);

        $this->assertSame($before + 1, User::count());

        // The same person at ANOTHER club is not this club's member — imported there.
        $other = $this->createClub($owner);
        $this->actingAs($owner)
            ->postJson("/admin/club/{$other->slug}/members/import", ['import_file' => $this->importFile([
                ['Sara', 'Kid', 'Female', '2016-05-05', '+97333445566', ''],
            ])])
            ->assertOk()->assertJson(['imported' => 1, 'already_members' => 0]);
    }

    // ------------------------------------------------------- 8. super-admin

    /** AUDIT: FIXED (2026-10-31) — the super-admin-created account is trusted: email_verified_at now persists (forceFill), and no mail is sent. */
    public function test_8_super_admin_store_member_marks_the_account_verified_and_sends_no_mail(): void
    {
        $this->seedRoles();
        $super = $this->createUser();
        $this->makeSuperAdmin($super);

        $this->actingAs($super)->postJson(route('admin.platform.members.store'), [
            'full_name' => 'Admin Made', 'email' => 'made@x.com',
            'password' => 'Password123!', 'password_confirmation' => 'Password123!',
            'gender' => 'Male', 'nationality' => 'BH',
        ])->assertOk()->assertJson(['success' => true]);

        $this->assertNotNull(User::where('email', 'made@x.com')->firstOrFail()->email_verified_at);
        Mail::assertNothingQueued();
        Notification::assertNothingSent();
    }

    // ------------------------------------------------------- 9. instructor

    /** FIXED: a new instructor's mobile is stored as {code,number} like every other account; account unverified; no mail. */
    public function test_9_club_instructor_add_stores_phone_as_code_and_number_and_sends_no_mail(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner);

        $this->actingAs($owner)->post("/admin/club/{$club->slug}/instructors", [
            'creation_type' => 'new',
            'name' => 'Coach Ali', 'email' => 'coach@x.com', 'password' => 'secret123',
            'phone' => '39001234', 'country_code' => '+973',
            'gender' => 'Male', 'birthdate' => '1990-05-15', 'nationality' => 'BH',
            'specialty' => 'Karate',
        ])->assertRedirect();

        $coach = User::where('email', 'coach@x.com')->firstOrFail();
        $this->assertSame(['code' => '+973', 'number' => '39001234'], $coach->mobile);
        $this->assertSame(['code' => '+973', 'number' => '39001234'], json_decode(DB::table('users')->where('id', $coach->id)->value('mobile'), true));
        $this->assertNull($coach->email_verified_at);
        Mail::assertNothingQueued();
    }

    // ------------------------------------------------------ 10. club join

    private function joinPayload($club, $package, array $registrants): array
    {
        return ['club_id' => $club->id, 'pay_later' => true, 'registrants' => array_map(fn ($r) => array_merge([
            'type' => 'self', 'package_id' => $package->id,
        ], $r), $registrants)];
    }

    /** SECURITY: a member cannot enrol another user by id; self + own child still works. */
    public function test_10_club_join_only_enrols_self_or_own_dependents(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner, ['country' => 'BH']);
        $package = \App\Clubs\Models\ClubPackage::create([
            'tenant_id' => $club->id, 'name' => 'Monthly', 'price' => 25, 'duration_days' => 30,
        ]);
        $a = $this->createUser(['full_name' => 'User A', 'gender' => 'Male']);
        $b = $this->createUser(['full_name' => 'User B', 'gender' => 'Male']);

        $this->actingAs($a)->postJson('/bh/clubs/join', $this->joinPayload($club, $package, [
            ['name' => 'User B', 'user_id' => $b->id],
        ]))->assertForbidden();
        $this->assertSame(0, \App\Clubs\Models\ClubMemberSubscription::where('user_id', $b->id)->count());

        // Hiding the stranger behind a legitimate self entry does not help.
        $this->actingAs($a)->postJson('/bh/clubs/join', $this->joinPayload($club, $package, [
            ['name' => 'User A', 'user_id' => $a->id],
            ['name' => 'User B', 'type' => 'child', 'user_id' => $b->id],
        ]))->assertForbidden();
        $this->assertSame(0, \App\Clubs\Models\ClubMemberSubscription::where('tenant_id', $club->id)->count());

        $this->addDependent($a)->assertOk();
        $kid = User::where('full_name', 'Sara Kid')->firstOrFail();
        $this->actingAs($a)->postJson('/bh/clubs/join', $this->joinPayload($club, $package, [
            ['name' => 'User A', 'user_id' => $a->id],
            ['name' => 'Sara Kid', 'type' => 'child', 'user_id' => $kid->id],
        ]))->assertOk()->assertJson(['success' => true]);
        $this->assertSame(1, \App\Clubs\Models\ClubMemberSubscription::where('user_id', $a->id)->count());
        $this->assertSame(1, \App\Clubs\Models\ClubMemberSubscription::where('user_id', $kid->id)->count());
    }
}
