<?php

namespace Tests\Feature\Federations;

use App\Federations\Mail\ClubVerificationCode;
use App\Federations\Models\Federation;
use App\Federations\Models\FederationAthlete;
use App\Federations\Models\FederationClub;
use App\Federations\Models\FederationClubApplication;
use App\Federations\Models\FederationEntryAthlete;
use App\Federations\Models\FederationEvent;
use App\Federations\Services\FederationClubApproval;
use App\Federations\Services\FederationInvitation;
use App\Members\Models\User;
use App\Members\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * CHARACTERISATION audit of the federation club portal's doors
 * (FederationInvitation::submit + FederationClubApproval::approve).
 * Each test asserts what the code does TODAY — a passing test is a record,
 * not an endorsement. Bugs are named in each test's AUDIT line.
 *
 * @group registration-audit
 */
#[Group('registration-audit')]
class FederationPortalDoorsTest extends TestCase
{
    use RefreshDatabase;

    private Federation $fed;

    private FederationEvent $event;

    private User $head;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'federations.enabled' => true,
            'duplicates.guard.federation_links' => false,
            'duplicates.guard.staff_enrol' => false,
            'duplicates.guard.officials' => false,
        ]);
        Mail::fake();
        Notification::fake();
        $this->seedRoles();

        $this->head = $this->createUser();
        $f = new Federation;
        $f->forceFill([
            'owner_user_id' => $this->head->id, 'name' => 'Test Karate Federation', 'short_name' => 'TKF',
            'country' => 'BH', 'currency' => 'BHD', 'sport' => 'Karate', 'color' => '#1e2c4f',
            'status' => Federation::STATUS_APPROVED, 'approved_at' => now(),
        ])->save();
        $this->fed = $f->fresh();

        $e = new FederationEvent;
        $e->forceFill([
            'uuid' => (string) Str::uuid(),
            'federation_id' => $this->fed->id, 'created_by' => $this->head->id,
            'name' => 'Spring Karate Open', 'sport' => 'Karate', 'status' => 'Planning',
            'starts_on' => now()->addMonth()->format('Y-m-d').'T09:00',
            'details' => [
                'enrollEnd' => now()->addWeeks(2)->toDateString(), 'types' => ['Individual Kumite'],
                'fees' => [['label' => 'Individual Kumite', 'amount' => '15']], 'free' => false,
            ],
        ])->save();
        $this->event = $e->fresh();
    }

    private function portal(): FederationInvitation
    {
        return app(FederationInvitation::class);
    }

    /** A brand-new club's first submission (no session yet). */
    private function submitNew(array $club, array $entries): array
    {
        return $this->portal()->submit($this->event, [
            'submissionId' => (string) Str::uuid(),
            'clubMode' => 'new',
            'club' => $club,
            'entries' => $entries,
        ]);
    }

    /** The club comes back in the session that is already bound to it. */
    private function submitAgain(array $entries): array
    {
        return $this->portal()->submit($this->event, [
            'submissionId' => (string) Str::uuid(),
            'clubMode' => 'existing',
            'entries' => $entries,
        ]);
    }

    /** A new browser: nothing proved. */
    private function newBrowser(): void
    {
        Session::flush();
    }

    private function athlete(string $name, ?string $dob = '2012-03-04', string $gender = 'Male'): array
    {
        return array_filter(['name' => $name, 'dob' => $dob, 'gender' => $gender, 'nat' => 'BHR', 'weight' => 40, 'types' => ['Individual Kumite']], fn ($v) => $v !== null);
    }

    private function riffa(array $over = []): array
    {
        return $over + ['name' => 'Riffa Karate Club', 'coach' => 'Coach Ali', 'email' => 'riffa@example.test', 'phone' => '+973 3333 4444'];
    }

    private function usersNamed(string $name): int
    {
        return User::where('full_name', $name)->count();
    }

    /** AUDIT: OK — identical re-submission reuses both athletes (rosterMatch), nothing duplicated. */
    public function test_1_same_athletes_same_spelling_are_reused(): void
    {
        $r1 = $this->submitNew($this->riffa(), [$this->athlete('Salman Al Jasim'), $this->athlete('Fatima Hasan', '2011-05-06', 'Female')]);
        $this->assertTrue($r1['ok'], $r1['message'] ?? '');
        $this->assertSame(2, $r1['added']);

        $r2 = $this->submitAgain([$this->athlete('Salman Al Jasim'), $this->athlete('Fatima Hasan', '2011-05-06', 'Female')]);
        $this->assertTrue($r2['ok'], $r2['message'] ?? '');
        $this->assertSame(0, $r2['added']);
        $this->assertCount(2, $r2['skipped']);
        $this->assertSame(2, FederationAthlete::count());
        $this->assertSame(1, $this->usersNamed('Salman Al Jasim'));
        $this->assertSame(1, FederationClubApplication::count());
    }

    /** AUDIT: DUPLICATE BUG — a re-spelled name (extra middle name / Arabic) with the same birthdate makes a second person and enters them twice. */
    public function test_2_respelled_name_same_birthdate_is_duplicated(): void
    {
        $this->assertTrue($this->submitNew($this->riffa(), [$this->athlete('Salman Al Jasim')])['ok']);

        $r = $this->submitAgain([$this->athlete('Salman Ali Al Jasim')]);
        $this->assertTrue($r['ok'], $r['message'] ?? '');
        $this->assertSame(1, $r['added']);

        $r = $this->submitAgain([$this->athlete('سلمان الجاسم')]);
        $this->assertTrue($r['ok'], $r['message'] ?? '');
        $this->assertSame(1, $r['added']);

        $this->assertSame(3, FederationAthlete::count(), 'one person, three athlete records');
        $this->assertSame(3, User::whereDate('birthdate', '2012-03-04')->count());
        $this->assertSame(3, FederationEntryAthlete::where('status', 'Active')->count(), 'the same child entered three times in one event');
    }

    /** AUDIT: OK — re-submitted without a birthdate, the name alone matches the single roster athlete and is reused. */
    public function test_3_resubmitted_without_birthdate_is_reused(): void
    {
        $this->assertTrue($this->submitNew($this->riffa(), [$this->athlete('Salman Al Jasim')])['ok']);

        $r = $this->submitAgain([$this->athlete('Salman Al Jasim', null)]);
        $this->assertTrue($r['ok'], $r['message'] ?? '');
        $this->assertSame(0, $r['added']);
        $this->assertSame(1, FederationAthlete::count());
        $this->assertNotNull(User::where('full_name', 'Salman Al Jasim')->first()->birthdate, 'the birthdate on file is kept');
    }

    /** AUDIT: OK with birthdate (refused, transfer required); DUPLICATE BUG without birthdate (second club makes a second person). */
    public function test_4_two_clubs_enter_the_same_athlete(): void
    {
        $this->assertTrue($this->submitNew($this->riffa(), [$this->athlete('Salman Al Jasim')])['ok']);

        // Club B, same name + birthdate → refused.
        $this->newBrowser();
        $muharraq = ['name' => 'Muharraq Dojo Champions', 'coach' => 'Coach Isa', 'email' => 'muh@example.test', 'phone' => '+973 3555 6666'];
        $r = $this->submitNew($muharraq, [$this->athlete('Salman Al Jasim')]);
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('already registered', $r['message']);
        $this->assertSame(1, FederationAthlete::count());
        $this->assertSame(1, FederationClubApplication::count(), 'the whole submission rolled back, club too');

        // Club B, same name, NO birthdate → a second person, entered twice in the event.
        $this->newBrowser();
        $r = $this->submitNew($muharraq, [$this->athlete('Salman Al Jasim', null)]);
        $this->assertTrue($r['ok'], $r['message'] ?? '');
        $this->assertSame(2, FederationAthlete::count());
        $this->assertSame(2, $this->usersNamed('Salman Al Jasim'));
        $this->assertSame(2, FederationEntryAthlete::where('status', 'Active')->count());
    }

    /** AUDIT: DUPLICATE BUG — an existing TakeOne member (same name + birthdate) is never matched; the portal makes a second, unclaimed account. */
    public function test_5_existing_takeone_member_gets_a_second_account(): void
    {
        $member = $this->createUser([
            'full_name' => 'Salman Al Jasim', 'name' => 'Salman Al Jasim', 'birthdate' => '2012-03-04', 'gender' => 'Male',
            'email' => 'salman@example.test',
        ]);

        $r = $this->submitNew($this->riffa(), [$this->athlete('Salman Al Jasim')]);
        $this->assertTrue($r['ok'], $r['message'] ?? '');

        $athlete = FederationAthlete::with('user')->sole();
        $this->assertNotSame($member->id, $athlete->user_id);
        $this->assertTrue((bool) $athlete->user->is_unclaimed);
        $this->assertNull($athlete->user->email);
        $this->assertSame(2, $this->usersNamed('Salman Al Jasim'));
    }

    /** AUDIT: DUPLICATE BUG (by design of the "different club" override) — a second application with new contacts goes through, and approval makes a second platform club + owner. */
    public function test_6_same_club_applies_twice_with_override_and_gets_two_platform_clubs(): void
    {
        $this->assertTrue($this->submitNew($this->riffa(), [$this->athlete('Salman Al Jasim')])['ok']);

        // New browser, same-ish name, different email + phone, no override → refused as a name match.
        $this->newBrowser();
        $second = ['name' => 'Riffa Club', 'coach' => 'Coach Ali', 'email' => 'riffa.office@example.test', 'phone' => '+973 3999 8888'];
        $r = $this->submitNew($second, [$this->athlete('Fatima Hasan', '2011-05-06', 'Female')]);
        $this->assertFalse($r['ok']);
        $this->assertSame('club_exists', $r['code'] ?? null);
        $this->assertSame('name', $r['match']['reason']);

        // Same email is refused even with the override.
        $this->newBrowser();
        $r = $this->submitNew($this->riffa(['name' => 'Riffa Club', 'phone' => '+973 3999 8888', 'distinct' => true]), []);
        $this->assertFalse($r['ok']);
        $this->assertSame('email', $r['match']['reason']);

        // Ticking "different club" → a second application.
        $this->newBrowser();
        $r = $this->submitNew($second + ['distinct' => true], [$this->athlete('Fatima Hasan', '2011-05-06', 'Female')]);
        $this->assertTrue($r['ok'], $r['message'] ?? '');
        $this->assertSame(2, FederationClubApplication::count());

        $approval = app(FederationClubApproval::class);
        foreach (FederationClubApplication::orderBy('id')->get() as $app) {
            $approval->approve($app, $this->head);
        }
        $links = FederationClub::with('club')->get();
        $this->assertCount(2, $links);
        $this->assertNotSame($links[0]->tenant_id, $links[1]->tenant_id);
        $this->assertNotSame($links[0]->club->owner_user_id, $links[1]->club->owner_user_id, 'two owner accounts for one coach');
        $this->assertSame(2, User::where('full_name', 'Coach Ali')->count());
    }

    /** AUDIT: MISSING EMAIL — submitting sends the club NO confirmation mail (only an in-app notice to federation staff); athletes get nothing; on approval the owner gets only a password-reset link. */
    public function test_7_emails_on_the_portal_doors(): void
    {
        $r = $this->submitNew($this->riffa(), [$this->athlete('Salman Al Jasim')]);
        $this->assertTrue($r['ok'], $r['message'] ?? '');

        Mail::assertNothingSent();
        Mail::assertNothingQueued();
        Notification::assertNothingSent();
        $this->assertNull(FederationAthlete::with('user')->sole()->user->email, 'athlete accounts have no email, so no verification can go');
        $this->assertDatabaseHas('user_notifications', ['user_id' => $this->head->id]);

        // A returning club asks for a code: that one mail is queued.
        $this->newBrowser();
        $this->portal()->sendCode($this->event, 'riffa@example.test');
        Mail::assertQueued(ClubVerificationCode::class, fn ($m) => $m->hasTo('riffa@example.test'));
        Mail::assertQueuedCount(1);

        // Approval: the coach's new account is sent a password-reset link, nothing else.
        $link = app(FederationClubApproval::class)->approve(FederationClubApplication::sole(), $this->head);
        $owner = $link->club->owner;
        $this->assertSame('riffa@example.test', $owner->email);
        Notification::assertSentTo($owner, ResetPasswordNotification::class);
        Notification::assertCount(1);
        Mail::assertNothingSent();
        Mail::assertQueuedCount(1);
    }
}
