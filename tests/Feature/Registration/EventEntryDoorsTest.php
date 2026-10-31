<?php

namespace Tests\Feature\Registration;

use App\Clubs\Models\Tenant;
use App\Events\Mail\ClubEntryCode;
use App\Events\Mail\EventEntryEmail;
use App\Events\Models\ClubEvent;
use App\Events\Models\ClubEventRegistration;
use App\Events\Models\EventOfficial;
use App\Events\Models\EventPublicEntry;
use App\Members\Mail\WelcomeEmail;
use App\Members\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * CHARACTERISATION audit of every door that registers a person into a
 * CLUB-hosted event. Each test asserts what the code does TODAY (so it
 * passes), and its docblock says whether that is a duplicate bug, a missing
 * email, or fine. Nothing here is a spec — when a door is fixed, the matching
 * assertion here is expected to flip.
 *
 * Config is pinned to the stage values: events.entry_dedupe ON, every
 * duplicates.guard.* OFF.
 *
 * Group: registration-audit (attribute below).
 */
#[\PHPUnit\Framework\Attributes\Group('registration-audit')]
class EventEntryDoorsTest extends TestCase
{
    use RefreshDatabase;

    private User $organiser;

    private Tenant $club;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Notification::fake();

        config([
            'events.entry_dedupe' => true,
            'duplicates.guard.officials' => false,
            'duplicates.guard.club_portal' => false,
            'duplicates.guard.staff_enrol' => false,
            'duplicates.guard.federation_links' => false,
        ]);

        $this->organiser = $this->createUser(['full_name' => 'Organiser One']);
        $this->club = $this->createClub($this->organiser, ['country' => 'BH', 'currency' => 'BHD', 'club_name' => 'Host Academy']);
        $this->organiser->memberClubs()->syncWithoutDetaching([$this->club->id => ['status' => 'active']]);
    }

    private function event(array $attrs = []): ClubEvent
    {
        return ClubEvent::create(array_merge([
            'tenant_id' => $this->club->id,
            'created_by' => $this->organiser->id,
            'title' => 'Victory Open',
            'event_type' => 'championship',
            'sport' => 'karate',
            'scope' => 'inter_club',
            'date' => now()->addWeeks(4)->toDateString(),
            'end_date' => now()->addWeeks(4)->toDateString(),
            'start_time' => '09:00',
            'end_time' => '17:00',
            'status' => 'active',
            'is_archived' => false,
            'entry_mode' => 'public',
        ], $attrs));
    }

    private function publicEntry(ClubEvent $event, array $body)
    {
        return $this->postJson(route('events.public.enter.store', $event->uuid), $body + [
            'password' => 'secret-pass-123',
            'mobile_code' => '+973',
        ]);
    }

    private function usersOnPhone(string $suffix): int
    {
        return User::where('phone_key', 'like', '%'.$suffix)->count();
    }

    /**
     * AUDIT: OK — a new athlete gets one account, a WelcomeEmail (the
     * "verification" mail) AND an "entry received" EventEntryEmail.
     */
    public function test_1_public_entry_new_athlete_with_email(): void
    {
        $event = $this->event();
        $before = User::count();

        $this->publicEntry($event, [
            'full_name' => 'Ali Hassan', 'mobile' => '33112233', 'email' => 'ali@example.com',
        ])->assertOk()->assertJsonPath('state', 'pending');

        $this->assertSame($before + 1, User::count());
        $ali = User::where('email', 'ali@example.com')->firstOrFail();
        $this->assertNull($ali->email_verified_at);
        $this->assertSame(1, EventPublicEntry::where('event_id', $event->id)->count());

        Mail::assertQueued(WelcomeEmail::class, fn (WelcomeEmail $m) => $m->hasTo('ali@example.com'));
        Mail::assertQueued(EventEntryEmail::class, fn (EventEntryEmail $m) => $m->state === EventEntryEmail::RECEIVED);
    }

    /**
     * AUDIT: FIXED 2026-10-31 — the same person re-submitting with the phone
     * typed "033112233" (vs "33112233" first time) IS recognised: a trunk zero
     * after a separated country code ("+973 033112233") is dropped by
     * User::phoneInputKeys, so KnownPerson looks up 97333112233 — the key both
     * forms are stored under. Pure normalisation; not behind a switch. Same
     * password → entered as the existing account; no second account/request.
     */
    public function test_2_public_resubmit_with_leading_zero_phone_is_recognised(): void
    {
        $event = $this->event();

        $this->publicEntry($event, ['full_name' => 'Ali Hassan', 'mobile' => '33112233', 'email' => 'ali@example.com'])->assertOk();
        Auth::logout();
        $this->publicEntry($event, ['full_name' => 'Ali Hassan', 'mobile' => '033112233'])->assertOk();

        $alis = User::where('full_name', 'Ali Hassan')->get();
        $this->assertCount(1, $alis, 'one account for one person');
        $this->assertSame('97333112233', $alis->first()->phone_key);
        $this->assertSame(1, EventPublicEntry::where('event_id', $event->id)->count());
    }

    /** The phone key rule itself: a trunk zero after a separated dial code is dropped; old keys stay reachable. */
    public function test_2b_phone_key_drops_trunk_zero_after_dial_code(): void
    {
        $this->assertSame('97333112233', User::normalisePhoneInput('+973 033112233'));
        $this->assertSame('97333112233', User::normalisePhoneInput('00973 033112233'));
        $this->assertSame('97333112233', User::normalisePhoneInput('+973 (0) 3311 2233'));
        $this->assertSame('97333112233', User::normalisePhoneInput('+973 33112233'));
        $this->assertSame('33112233', User::normalisePhoneInput('033112233'));
        // One unbroken run cannot be split safely — unchanged reading.
        $this->assertSame('973033112233', User::normalisePhoneInput('+973033112233'));
        $this->assertSame('97333112233', User::normalisePhone(['code' => '+973', 'number' => '033112233']));
        $this->assertSame('97333112233', User::normalisePhone(['code' => '', 'number' => '+973 033112233']));

        // A row stored under the pre-fix key is still found by the typed form.
        $legacy = $this->createUser(['full_name' => 'Old Key']);
        \Illuminate\Support\Facades\DB::table('users')->where('id', $legacy->id)->update(['phone_key' => '973033445599']);
        $this->assertTrue(User::withPhone('+973 033445599')->whereKey($legacy->id)->exists());
    }

    /**
     * AUDIT: OK (no duplicate) — a logged-in member posting the anonymous form
     * with their own name and phone but a NEW password is recognised by
     * KnownPerson and refused with state=known; no account is made. The
     * session's own identity is ignored entirely by this door, though.
     */
    public function test_3_public_entry_while_logged_in_as_same_member(): void
    {
        $event = $this->event();
        $member = $this->createUser(['full_name' => 'Ali Hassan', 'mobile' => ['code' => '+973', 'number' => '33112233']]);
        $before = User::count();

        $this->actingAs($member);
        $this->publicEntry($event, ['full_name' => 'Ali Hassan', 'mobile' => '33112233', 'password' => 'a-different-pass'])
            ->assertStatus(422)->assertJsonPath('state', 'known');

        $this->assertSame($before, User::count());
        $this->assertSame(0, EventPublicEntry::where('event_id', $event->id)->count());
        Mail::assertNotQueued(WelcomeEmail::class);
    }

    /**
     * AUDIT: guard OFF (stage today) — "Mohd" vs "Mohammed" on the SAME phone
     * is not the same person to KnownPerson::samePerson (skeletons mhd/mhmd
     * differ, distance 4), so a second account is created.
     */
    public function test_4_public_entry_by_existing_member_with_abbreviated_name(): void
    {
        $event = $this->event();
        $this->createUser(['full_name' => 'Mohammed Ali', 'mobile' => ['code' => '+973', 'number' => '33445566']]);

        $this->publicEntry($event, ['full_name' => 'Mohd Ali', 'mobile' => '33445566'])->assertOk()->assertJsonPath('state', 'pending');

        $this->assertSame(2, $this->usersOnPhone('33445566'), 'a second account on the same phone');
        $this->assertSame(1, EventPublicEntry::where('event_id', $event->id)->count());
    }

    /**
     * AUDIT: FIXED behind duplicates.guard.registration — with the switch on,
     * the name rule's alias table makes "Mohd" (and "Moh'd") the same name as
     * "Mohammed", so the existing member is recognised (a different password
     * → state=known) and no second account is made.
     */
    public function test_4b_abbreviated_name_is_recognised_with_the_registration_guard_on(): void
    {
        config(['duplicates.guard.registration' => true]);
        $event = $this->event();
        $this->createUser(['full_name' => 'Mohammed Ali', 'mobile' => ['code' => '+973', 'number' => '33445566']]);

        $this->publicEntry($event, ['full_name' => "Moh'd Ali", 'mobile' => '33445566', 'password' => 'not-their-pass'])
            ->assertStatus(422)->assertJsonPath('state', 'known');

        $this->assertSame(1, $this->usersOnPhone('33445566'));
        $this->assertSame(0, EventPublicEntry::where('event_id', $event->id)->count());

        $known = app(\App\Events\Support\KnownPerson::class);
        $this->assertTrue($known->samePerson('Mhd Yousif', 'Muhammad Yusuf'));
        $this->assertTrue($known->samePerson('Abdulla Ebrahim', 'Abdullah Ibrahim'));
        $this->assertTrue($known->samePerson('Khaled Hussein', 'Khalid Husain'));
        $this->assertTrue($known->samePerson('Ahmad Hassan', 'Ahmed Hasan'));
        // Brothers on one phone stay two people; siblings with different first names too.
        $this->assertFalse($known->samePerson('Hasan Ali', 'Hussain Ali'));
        $this->assertFalse($known->samePerson('Yousif Hasan', 'Noor Hasan'));

        config(['duplicates.guard.registration' => false]);
        $this->assertFalse($known->samePerson('Mohd Ali', 'Mohammed Ali'), 'switch off = old rule');
    }

    /**
     * AUDIT: DUPLICATE BUG (cross-door) — the desk entering a name with no
     * phone never looks at existing accounts, so a member who already has an
     * account becomes a new unclaimed person. OK within the event: a second
     * desk entry of the same name is refused (422).
     */
    public function test_5_desk_entry_by_name_for_someone_with_an_account(): void
    {
        $event = $this->event();
        $existing = $this->createUser(['full_name' => 'Sara Khalid', 'mobile' => ['code' => '+973', 'number' => '36667788']]);

        $res = $this->actingAs($this->organiser)->postJson(route('me.events.entries.unnamed', $event->uuid), ['full_name' => 'Sara Khalid'])
            ->assertOk()->assertJsonPath('success', true);

        $reg = ClubEventRegistration::findOrFail($res->json('claim.competitor_id'));
        $this->assertNotSame($existing->id, $reg->user_id);
        $this->assertTrue((bool) $reg->user->is_unclaimed);
        $this->assertSame(2, User::where('full_name', 'Sara Khalid')->count());
        $this->assertNotNull($res->json('claim.url'), 'a claim link was minted for the stub');

        $this->actingAs($this->organiser)->postJson(route('me.events.entries.unnamed', $event->uuid), ['full_name' => 'Sara Khalid'])
            ->assertStatus(422);
        $this->assertSame(1, ClubEventRegistration::where('event_id', $event->id)->where('role', 'participant')->count());
    }

    /**
     * AUDIT: DUPLICATE BUG (+ missing email, FIXED) — completing a minor's claim link
     * always CREATES a guardian account; a parent who already has an account is
     * refused ("email taken") if they use their own address, so they must make
     * a second account. Their second child's link with that same address is
     * refused too. MISSING EMAIL FIXED (2026-10-31): the completed claim now
     * queues ONE WelcomeEmail (verification) to the new guardian address.
     */
    public function test_6_claim_completion_for_minors(): void
    {
        config(['duplicates.guard.registration' => false]);
        $event = $this->event();
        $parent = $this->createUser(['full_name' => 'Parent Existing', 'email' => 'parent@example.com']);

        $linkFor = function (string $name) use ($event): array {
            $url = $this->actingAs($this->organiser)->postJson(route('me.events.entries.unnamed', $event->uuid), ['full_name' => $name])
                ->assertOk()->json('claim.url');
            $this->assertNotNull($url);
            preg_match('#/enter/([0-9a-f-]{36})\?t=(\w+)#', $url, $m);

            return [$m[1], $m[2]];
        };
        $complete = function (array $link, string $email) {
            Auth::logout();

            return $this->postJson('/enter/'.$link[0], [
                't' => $link[1], 'birthdate' => now()->subYears(10)->toDateString(), 'gender' => 'Male',
                'guardian_name' => 'Parent Existing', 'email' => $email, 'password' => 'guardian-pass-1',
            ]);
        };

        $kid1 = $linkFor('Kid One');
        $kid2 = $linkFor('Kid Two');

        // The parent's own, existing address: refused.
        $complete($kid1, 'parent@example.com')->assertStatus(422);

        // A fresh address: a NEW guardian account.
        $before = User::count();
        $complete($kid1, 'parent2@example.com')->assertOk()->assertJsonPath('is_minor', true);
        $this->assertSame($before + 1, User::count());
        $guardian = User::where('email', 'parent2@example.com')->firstOrFail();
        $this->assertNotSame($parent->id, $guardian->id);

        // Second child, same (now-guardian) address: refused as taken.
        $complete($kid2, 'parent2@example.com')->assertStatus(422);

        Mail::assertQueued(WelcomeEmail::class, 1);
        Mail::assertQueued(WelcomeEmail::class, fn (WelcomeEmail $m) => $m->hasTo('parent2@example.com'));
        Mail::assertNotQueued(EventEntryEmail::class);
    }

    /**
     * FIXED behind duplicates.guard.registration — a parent who already has an
     * account completes each child's link SIGNED IN as it, and every child is
     * attached to that ONE account as its guardian. Typing the address without
     * being signed in as it (signed out, or signed in as somebody else) is
     * still refused: an email alone never hands a child to an account.
     */
    public function test_6b_claim_for_minors_joins_the_signed_in_guardian(): void
    {
        config(['duplicates.guard.registration' => true]);
        $event = $this->event();
        $parent = $this->createUser(['full_name' => 'Parent Existing', 'email' => 'parent@example.com']);
        $parentPassword = $parent->password;

        $linkFor = function (string $name) use ($event): array {
            $url = $this->actingAs($this->organiser)->postJson(route('me.events.entries.unnamed', $event->uuid), ['full_name' => $name])
                ->assertOk()->json('claim.url');
            preg_match('#/enter/([0-9a-f-]{36})\?t=(\w+)#', $url, $m);

            return [$m[1], $m[2]];
        };
        $post = fn (array $link, string $email) => $this->postJson('/enter/'.$link[0], [
            't' => $link[1], 'birthdate' => now()->subYears(10)->toDateString(), 'gender' => 'Male',
            'guardian_name' => 'Someone Typed', 'email' => $email, 'password' => 'guardian-pass-1',
        ]);

        $kid1 = $linkFor('Kid One');
        $kid2 = $linkFor('Kid Two');
        $before = User::count();

        // Signed out, the parent's address: refused (sign in and come back).
        Auth::logout();
        $post($kid1, 'parent@example.com')->assertStatus(422);
        // Signed in as somebody ELSE, the parent's address: refused too.
        $this->actingAs($this->organiser);
        $post($kid1, 'parent@example.com')->assertStatus(422);

        // Signed in as the parent: both children join THAT account.
        $this->actingAs($parent);
        $post($kid1, 'parent@example.com')->assertOk()->assertJsonPath('is_minor', true);
        $this->actingAs($parent);
        $post($kid2, 'parent@example.com')->assertOk()->assertJsonPath('is_minor', true);

        $this->assertSame($before, User::count(), 'no second guardian account');
        $kids = User::whereIn('full_name', ['Kid One', 'Kid Two'])->pluck('id');
        $this->assertSame(2, \App\Members\Models\UserRelationship::where('guardian_user_id', $parent->id)->whereIn('dependent_user_id', $kids)->count());
        $parent->refresh();
        $this->assertSame('Parent Existing', $parent->full_name, 'the guardian keeps their own name');
        $this->assertSame($parentPassword, $parent->password, 'and their own password');
        Mail::assertNotQueued(WelcomeEmail::class);
    }

    /**
     * AUDIT: DUPLICATE BUG — the officials desk writes down a NEW unclaimed
     * person every time (guard off), even with the same name and phone, so one
     * referee at two events is two people.
     */
    public function test_7_same_official_at_two_events_is_two_people(): void
    {
        $a = $this->event(['title' => 'Event A']);
        $b = $this->event(['title' => 'Event B']);
        $body = ['full_name' => 'Hamad Saleh', 'phone' => '33998877', 'phone_code' => '+973', 'role' => 'other',
            'role_label' => 'Referee', 'compensation' => EventOfficial::COMP_VOLUNTEER];

        $this->actingAs($this->organiser)->postJson(route('me.events.officials.person', $a->uuid), $body)->assertOk();
        $this->actingAs($this->organiser)->postJson(route('me.events.officials.person', $b->uuid), $body)->assertOk();

        $this->assertSame(2, User::where('full_name', 'Hamad Saleh')->count());
        $this->assertSame(2, EventOfficial::whereIn('event_id', [$a->id, $b->id])->distinct()->count('user_id'));
    }

    /**
     * FIXED behind duplicates.guard.officials — the same name AND phone is the
     * same referee: one person, appointed at both events.
     */
    public function test_7b_same_official_at_two_events_is_one_person_with_the_guard(): void
    {
        config(['duplicates.guard.officials' => true]);
        $a = $this->event(['title' => 'Event A']);
        $b = $this->event(['title' => 'Event B']);
        $body = ['full_name' => 'Hamad Saleh', 'phone' => '33998877', 'phone_code' => '+973', 'role' => 'other',
            'role_label' => 'Referee', 'compensation' => EventOfficial::COMP_VOLUNTEER];

        $this->actingAs($this->organiser)->postJson(route('me.events.officials.person', $a->uuid), $body)->assertOk();
        $this->actingAs($this->organiser)->postJson(route('me.events.officials.person', $b->uuid), $body)->assertOk();

        $this->assertSame(1, User::where('full_name', 'Hamad Saleh')->count());
        $this->assertSame(2, EventOfficial::whereIn('event_id', [$a->id, $b->id])->count());
        $this->assertSame(1, EventOfficial::whereIn('event_id', [$a->id, $b->id])->distinct()->count('user_id'));
    }

    /** Sign Bravo Dojo in to the club portal; returns the API url builder. */
    private function provedClubPortal(ClubEvent $event): \Closure
    {
        config(['events.club_entry' => true, 'events.club_entry_platform' => true, 'events.unified_page' => true]);
        $owner = $this->createUser(['email' => 'bravo@example.com']);
        $this->createClub($owner, ['country' => 'BH', 'club_name' => 'Bravo Dojo', 'email' => 'bravo@example.com']);

        $api = fn (string $verb) => '/me/events/'.$event->uuid.'/club/api/'.$verb;
        $key = $this->getJson($api('clubs').'?q=Bravo')->assertOk()->json('clubs.0.key');
        $this->assertNotNull($key);
        $this->postJson($api('code'), ['club' => $key])->assertStatus(202);
        $code = null;
        Mail::assertQueued(ClubEntryCode::class, function (ClubEntryCode $m) use (&$code) { $code = $m->code; return true; });
        $this->postJson($api('verify'), ['club' => $key, 'code' => $code])->assertOk();
        $this->postJson('/me/events/'.$event->uuid.'/consent')->assertOk();

        return $api;
    }

    /**
     * FIXED (always on — it can only stop a club duplicating its own entry):
     * a proved club re-submitting the same name-entered athletes under a new
     * submissionId gets the SAME people back, and the second entry is refused.
     * Two children on one phone stay two people (different names). No mail
     * reaches the athletes (they have no address).
     */
    public function test_8_club_portal_resubmission_reuses_the_clubs_own_entrants(): void
    {
        $event = $this->event(['enrollment_ends_at' => now()->addWeeks(2)->toDateString()]);
        $api = $this->provedClubPortal($event);

        $entries = [
            ['name' => 'Yusuf Ahmed', 'gender' => 'Male', 'dob' => '2010-01-02', 'weight' => 45, 'phone' => '+973 3555 1111', 'types' => ['Entry']],
            ['name' => 'Noor Ahmed', 'gender' => 'Female', 'dob' => '2011-05-06', 'weight' => 40, 'phone' => '+973 3555 1111', 'types' => ['Entry']],
        ];
        $this->postJson($api('submit'), ['submissionId' => 's-1', 'clubMode' => 'existing', 'entries' => $entries])
            ->assertOk()->assertJsonPath('added', 2);
        $before = User::count();
        $this->postJson($api('submit'), ['submissionId' => 's-2', 'clubMode' => 'existing', 'entries' => $entries])
            ->assertOk()->assertJsonPath('added', 0);

        $this->assertSame($before, User::count(), 'nobody new written down');
        $this->assertSame(1, User::where('full_name', 'Yusuf Ahmed')->count());
        $this->assertSame(1, User::where('full_name', 'Noor Ahmed')->count());
        $this->assertSame(2, ClubEventRegistration::where('event_id', $event->id)->where('role', 'participant')->count());

        // A different birthdate under the same name is somebody else.
        $other = [['name' => 'Yusuf Ahmed', 'gender' => 'Male', 'dob' => '2012-03-04', 'weight' => 35, 'types' => ['Entry']]];
        $this->postJson($api('submit'), ['submissionId' => 's-3', 'clubMode' => 'existing', 'entries' => $other])
            ->assertOk()->assertJsonPath('added', 1);
        $this->assertSame(2, User::where('full_name', 'Yusuf Ahmed')->count());

        Mail::assertNotQueued(EventEntryEmail::class);
        Mail::assertNotQueued(WelcomeEmail::class);
    }

    /**
     * FIXED — a retry of the SAME submission after a lost response gets the
     * first answer back, even though the session id has changed since (the
     * replay key is the submission + the proved club, not the session id).
     */
    public function test_8b_club_portal_retry_after_session_rotation_is_replayed(): void
    {
        $event = $this->event(['enrollment_ends_at' => now()->addWeeks(2)->toDateString()]);
        $api = $this->provedClubPortal($event);
        $entries = [['name' => 'Yusuf Ahmed', 'gender' => 'Male', 'dob' => '2010-01-02', 'weight' => 45, 'types' => ['Entry']]];

        $first = $this->postJson($api('submit'), ['submissionId' => 'retry-1', 'clubMode' => 'existing', 'entries' => $entries])
            ->assertOk()->assertJsonPath('added', 1)->json();

        $this->app['session.store']->migrate(true);   // e.g. the club signed in meanwhile

        $again = $this->postJson($api('submit'), ['submissionId' => 'retry-1', 'clubMode' => 'existing', 'entries' => $entries])
            ->assertOk()->json();
        $this->assertSame($first, $again, 'the same answer, replayed');
        $this->assertSame(1, ClubEventRegistration::where('event_id', $event->id)->where('role', 'participant')->count());
    }
}
