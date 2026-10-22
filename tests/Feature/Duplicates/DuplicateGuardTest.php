<?php

namespace Tests\Feature\Duplicates;

use App\Clubs\Models\Tenant;
use App\Clubs\Support\ClubMatch;
use App\Duplicates\Models\DuplicateFinding;
use App\Events\Models\ClubEvent;
use App\Events\Models\ClubEventRegistration;
use App\Events\Models\EventClub;
use App\Events\Models\EventOfficial;
use App\Events\Support\Clubs\ClubPromotion;
use App\Events\Support\Clubs\EventClubLink;
use App\Members\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The duplicate guard and watch (App\Duplicates): a typed club is linked to
 * the platform club it is, a promotion never makes a second club, the watch
 * finds and — only when certain and only when allowed — fixes, and with every
 * switch off nothing behaves differently from before.
 */
class DuplicateGuardTest extends TestCase
{
    private function event(Tenant $club, User $owner, array $attrs = []): ClubEvent
    {
        return ClubEvent::create($attrs + [
            'tenant_id' => $club->id, 'created_by' => $owner->id,
            'title' => 'Open', 'event_type' => 'championship', 'sport' => 'karate',
            'scope' => 'inter_club', 'status' => 'active', 'is_archived' => false,
            'date' => now()->addWeeks(2)->toDateString(), 'start_time' => '09:00', 'end_time' => '17:00',
        ]);
    }

    private function entry(ClubEvent $event, User $u, ?EventClub $club = null, ?int $representing = null): ClubEventRegistration
    {
        $r = ClubEventRegistration::create([
            'event_id' => $event->id, 'user_id' => $u->id, 'role' => 'participant', 'status' => 'joined',
            'representing_tenant_id' => $representing,
        ]);
        if ($club) {
            DB::table('event_club_entrants')->insert([
                'registration_id' => $r->id, 'event_club_id' => $club->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $r;
    }

    private function stub(string $name, array $attrs = []): User
    {
        return User::create($attrs + [
            'full_name' => $name, 'name' => $name, 'email' => null, 'password' => null,
            'is_unclaimed' => true, 'is_discoverable' => false,
        ]);
    }

    public function test_club_names_match_across_case_punctuation_and_spelling(): void
    {
        $owner = $this->createUser();
        $this->createClub($owner, ['club_name' => 'JKS Bahrain']);
        $this->createClub($owner, ['club_name' => 'Riffa Karate Club']);
        $m = app(ClubMatch::class);

        $this->assertCount(1, $m->sameName('jks - BAHRAIN.'));
        $this->assertNotNull($m->uniqueSameName('Jks Bahrain'));
        $this->assertSame('Riffa Karate Club', $m->similar('Riffa Karate Academy')->first()['tenant']->club_name);
        $this->assertNull($m->uniqueSameName('Nobody Club'));

        $this->createClub($owner, ['club_name' => 'JKS-Bahrain']);
        $this->assertNull($m->uniqueSameName('JKS Bahrain'), 'two clubs of one name is not a match to act on');
    }

    public function test_linking_a_typed_club_fills_only_what_is_blank(): void
    {
        $owner = $this->createUser();
        $host = $this->createClub($owner);
        $real = $this->createClub($owner, ['club_name' => 'Okami Dojo']);
        $elsewhere = $this->createClub($owner, ['club_name' => 'Elsewhere']);
        $event = $this->event($host, $owner);
        $typed = EventClub::create(['event_id' => $event->id, 'name' => 'okami dojo', 'state' => 'listed']);

        $blank = $this->entry($event, $this->createUser(), $typed);
        $named = $this->entry($event, $this->createUser(), $typed, $elsewhere->id);
        $official = EventOfficial::create(['event_id' => $event->id, 'user_id' => $this->createUser()->id, 'role' => EventOfficial::ROLE_OTHER]);
        $official->forceFill(['event_club_id' => $typed->id])->save();

        $r = EventClubLink::link($typed, $real, $owner);

        $this->assertTrue($r['ok']);
        $this->assertSame($real->id, (int) $typed->fresh()->tenant_id);
        $this->assertSame($real->id, (int) $blank->fresh()->representing_tenant_id);
        $this->assertSame($elsewhere->id, (int) $named->fresh()->representing_tenant_id, 'a club already named is kept');
        $this->assertSame($real->id, (int) $official->fresh()->representing_tenant_id);
        $this->assertTrue(EventClubLink::link($typed->fresh(), $real, $owner)['ok'], 'linking twice is harmless');
    }

    public function test_a_typed_copy_of_an_invited_club_hands_its_athletes_over(): void
    {
        $owner = $this->createUser();
        $host = $this->createClub($owner);
        $real = $this->createClub($owner, ['club_name' => 'Okami Dojo']);
        $event = $this->event($host, $owner);
        $invited = EventClub::create(['event_id' => $event->id, 'tenant_id' => $real->id, 'name' => 'Okami Dojo', 'state' => 'accepted']);
        $typed = EventClub::create(['event_id' => $event->id, 'name' => 'Okami Dojo', 'state' => 'listed']);
        $entry = $this->entry($event, $this->createUser(), $typed);

        $this->assertTrue(EventClubLink::link($typed, $real, $owner)['ok']);

        $this->assertNull($typed->fresh()->tenant_id, 'the unique (event, club) pair is never broken');
        $this->assertSame($invited->id, (int) DB::table('event_club_entrants')->where('registration_id', $entry->id)->value('event_club_id'));
        $this->assertSame($real->id, (int) $entry->fresh()->representing_tenant_id);
    }

    public function test_promotion_links_to_the_existing_club_only_when_the_guard_is_on(): void
    {
        $owner = $this->createUser();
        $host = $this->createClub($owner);
        $real = $this->createClub($owner, ['club_name' => 'Tora Dojo']);
        $past = ['date' => now()->subWeek()->toDateString(), 'status' => 'completed'];

        // Off: exactly the old behaviour — a new (sleeping) club.
        config(['duplicates.guard.promotion' => false]);
        $event = $this->event($host, $owner, $past);
        $typed = EventClub::create(['event_id' => $event->id, 'name' => 'TORA DOJO', 'state' => 'listed', 'created_by' => $owner->id]);
        $this->entry($event, $this->createUser(), $typed);
        $before = Tenant::count();
        app(ClubPromotion::class)->forEvent($event);
        $this->assertSame($before + 1, Tenant::count());

        // On: linked, nothing created.
        config(['duplicates.guard.promotion' => true]);
        $event2 = $this->event($host, $owner, $past);
        $typed2 = EventClub::create(['event_id' => $event2->id, 'name' => 'tora dojo', 'state' => 'listed', 'created_by' => $owner->id]);
        $this->entry($event2, $this->createUser(), $typed2);
        // The first run made a second "Tora Dojo"; the name is ambiguous now, so it must NOT link.
        $count = Tenant::count();
        app(ClubPromotion::class)->forEvent($event2);
        $this->assertSame($count + 1, Tenant::count(), 'two clubs of that name: no guess, the old path runs');

        // With one unambiguous club, it links.
        $event3 = $this->event($host, $owner, $past);
        $solo = $this->createClub($owner, ['club_name' => 'Kensei Budokan']);
        $typed3 = EventClub::create(['event_id' => $event3->id, 'name' => 'KENSEI BUDOKAN', 'state' => 'listed', 'created_by' => $owner->id]);
        $this->entry($event3, $this->createUser(), $typed3);
        $count = Tenant::count();
        app(ClubPromotion::class)->forEvent($event3);
        $this->assertSame($count, Tenant::count());
        $this->assertSame($solo->id, (int) $typed3->fresh()->tenant_id);
        $this->assertNotNull($real);
    }

    public function test_the_watch_does_nothing_while_it_is_off(): void
    {
        // Explicit: the stage .env may have it on, and phpunit reads that file.
        config(['duplicates.watch' => false, 'duplicates.autofix' => false]);
        $this->stub('Hamad Saleh', ['mobile' => ['code' => '+973', 'number' => '33112233']]);
        $this->stub('Hamad Saleh', ['mobile' => ['code' => '+973', 'number' => '33112233']]);

        $this->artisan('duplicates:watch')->assertSuccessful();

        $this->assertSame(0, DuplicateFinding::count());
    }

    public function test_the_watch_merges_an_unclaimed_twin_into_the_account(): void
    {
        config(['duplicates.watch' => true, 'duplicates.autofix' => true]);
        $this->makeSuperAdmin($this->createUser());
        $account = $this->createUser(['full_name' => 'Hamad Saleh', 'name' => 'Hamad Saleh', 'mobile' => ['code' => '+973', 'number' => '33112233']]);
        $stub = $this->stub('Hamad Saleh', ['mobile' => ['code' => '+973', 'number' => '33112233']]);

        $this->artisan('duplicates:watch')->assertSuccessful();

        $this->assertSoftDeleted('users', ['id' => $stub->id]);
        $this->assertNotSoftDeleted('users', ['id' => $account->id]);
        $f = DuplicateFinding::where('kind', 'person')->firstOrFail();
        $this->assertSame(DuplicateFinding::FIXED, $f->status);
        $this->assertSame($account->id, $f->fix['kept']);
        $this->assertNotNull($f->fix['merge_id'], 'kept in the undo ledger');
    }

    public function test_the_watch_never_merges_two_accounts_that_sign_in(): void
    {
        config(['duplicates.watch' => true, 'duplicates.autofix' => true]);
        $this->makeSuperAdmin($this->createUser());
        $a = $this->createUser(['full_name' => 'Hamad Saleh', 'name' => 'Hamad Saleh', 'mobile' => ['code' => '+973', 'number' => '33112233']]);
        $b = $this->createUser(['full_name' => 'Hamad Saleh', 'name' => 'Hamad Saleh', 'mobile' => ['code' => '+973', 'number' => '33112233']]);

        $this->artisan('duplicates:watch')->assertSuccessful();

        $this->assertNotSoftDeleted('users', ['id' => $a->id]);
        $this->assertNotSoftDeleted('users', ['id' => $b->id]);
        $f = DuplicateFinding::where('kind', 'person')->firstOrFail();
        $this->assertSame(DuplicateFinding::OPEN, $f->status);
        $this->assertSame('both_sign_in', $f->fix['refused']);
    }

    public function test_a_likely_pair_is_only_recorded_never_fixed(): void
    {
        config(['duplicates.watch' => true, 'duplicates.autofix' => true]);
        $this->makeSuperAdmin($this->createUser());
        $a = $this->stub('Hussam Eid Al Juma');
        $b = $this->stub('Hussam Eid Aljuma');

        $this->artisan('duplicates:watch')->assertSuccessful();

        $f = DuplicateFinding::where('signature', 'person:'.min($a->id, $b->id).'-'.max($a->id, $b->id))->firstOrFail();
        $this->assertSame('likely', $f->strength);
        $this->assertSame(DuplicateFinding::OPEN, $f->status);
        $this->assertNotSoftDeleted('users', ['id' => $a->id]);
        $this->assertNotSoftDeleted('users', ['id' => $b->id]);
    }

    public function test_the_club_portal_catches_a_respelled_name_and_a_shared_phone_only_when_guarded(): void
    {
        $owner = $this->createUser(['mobile' => ['code' => '+973', 'number' => '36001122']]);
        $this->createClub($owner, ['club_name' => 'JKS Bahrain']);
        $event = $this->event($this->createClub($this->createUser()), $owner);
        $portal = new \App\Events\Support\ClubEntry\PlatformClubEntry($event);
        config(['duplicates.guard.club_portal' => false]);

        $this->assertNull($portal->checkClub('Jks - Bahrain.', '', ''), 'off: exactly the old exact-name check');
        $this->assertNull($portal->checkClub('Totally New Club', '', '+973 3600 1122'));

        config(['duplicates.guard.club_portal' => true]);
        $this->assertSame('name', $portal->checkClub('Jks - Bahrain.', '', '')['reason']);
        $this->assertSame('phone', $portal->checkClub('Totally New Club', '', '+973 3600 1122')['reason']);
        $this->assertSame('phone', $portal->checkClub('Jks Bahrain', '', '+973 3600 1122', contactOnly: true)['reason'],
            '"we are a different club" no longer waves a shared phone past');
        $this->assertNull($portal->checkClub('Totally New Club', '', '+973 3999 0000'));
    }

    public function test_a_new_official_with_a_known_phone_and_name_is_that_person(): void
    {
        config(['duplicates.guard.officials' => true]);
        $organiser = $this->createUser();
        $event = $this->event($this->createClub($organiser), $organiser);
        $known = $this->createUser(['full_name' => 'Hamad Saleh', 'name' => 'Hamad Saleh', 'mobile' => ['code' => '+973', 'number' => '33112233']]);
        $people = User::count();
        $body = ['full_name' => 'Hamad Saleh', 'phone' => '33112233', 'phone_code' => '+973', 'role' => 'other',
            'role_label' => 'Photographer', 'compensation' => EventOfficial::COMP_VOLUNTEER];

        $this->actingAs($organiser)->postJson(route('me.events.officials.person', $event->uuid), $body)->assertOk();

        $this->assertSame($people, User::count(), 'nobody new was created');
        $this->assertDatabaseHas('event_officials', ['event_id' => $event->id, 'user_id' => $known->id, 'role' => 'other']);

        $this->actingAs($organiser)->postJson(route('me.events.officials.person', $event->uuid), $body)->assertStatus(422);
    }

    public function test_a_club_portal_athlete_with_a_known_phone_and_name_is_that_person(): void
    {
        $known = $this->createUser(['full_name' => 'Hamad Saleh', 'name' => 'Hamad Saleh', 'birthdate' => '2010-03-04',
            'mobile' => ['code' => '+973', 'number' => '33112233']]);
        $event = $this->event($this->createClub($this->createUser()), $this->createUser());
        $portal = new \App\Events\Support\ClubEntry\PlatformClubEntry($event);
        $athleteFor = fn (array $row) => (new \ReflectionMethod($portal, 'athleteFor'))->invoke($portal, $row, collect());
        $row = ['name' => 'Hamad Saleh', 'dob' => '2010-03-04', 'gender' => 'Male', 'phone' => '+973 3311 2233'];

        // Off: exactly as before — a new person (who now keeps the phone for next time).
        config(['duplicates.guard.club_portal' => false]);
        $first = $athleteFor($row);
        $this->assertNotSame($known->id, $first->id);
        $this->assertTrue((bool) $first->is_unclaimed);

        config(['duplicates.guard.club_portal' => true]);
        $first->forceDelete();
        $this->assertSame($known->id, $athleteFor($row)->id, 'same phone + same name = that person');
        $this->assertNotSame($known->id, $athleteFor(['dob' => '2012-01-01'] + $row)->id, 'a different birthdate is somebody else');
        $this->assertNotSame($known->id, $athleteFor(['name' => 'Noor Saleh'] + $row)->id, 'a sister on the same phone is somebody else');
    }

    public function test_the_duplicates_page_is_for_super_admins_only(): void
    {
        $admin = $this->createUser();
        $this->makeSuperAdmin($admin);
        $this->stub('Hussam Eid Al Juma');
        $this->stub('Hussam Eid Aljuma');
        app(\App\Duplicates\Support\DuplicateWatch::class)->record();
        $finding = DuplicateFinding::firstOrFail();

        $this->actingAs($admin)->get(route('admin.duplicates.index'))->assertOk()->assertSee(__('duplicates::messages.title'));
        $this->actingAs($admin)->withHeaders(['User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148'])
            ->get(route('admin.duplicates.index'))->assertOk()->assertSee('m-hero', false);
        $this->actingAs($admin)->getJson(route('admin.duplicates.list'))->assertOk()->assertJsonPath('findings.0.id', $finding->uuid);

        $other = $this->createUser();
        $this->actingAs($other)->get(route('admin.duplicates.index'))->assertRedirect('/');
        $this->actingAs($other)->getJson(route('admin.duplicates.list'))->assertForbidden();
        $this->actingAs($other)->postJson(route('admin.duplicates.dismiss', $finding->uuid))->assertForbidden();
        $this->assertSame('open', $finding->fresh()->status);

        // A likely person pair cannot be "fixed" from here — only merged on the members screen.
        $this->actingAs($admin)->postJson(route('admin.duplicates.fix', $finding->uuid))->assertStatus(422);
        $this->actingAs($admin)->postJson(route('admin.duplicates.dismiss', $finding->uuid))
            ->assertOk()->assertJsonPath('finding.status', 'dismissed');
        $this->actingAs($admin)->postJson(route('admin.duplicates.dismiss', $finding->uuid))->assertStatus(422);
    }

    public function test_a_finding_that_stops_being_true_is_marked_gone(): void
    {
        config(['duplicates.watch' => true]);
        $a = $this->stub('Hussam Eid Al Juma');
        $this->stub('Hussam Eid Aljuma');
        $this->artisan('duplicates:watch')->assertSuccessful();
        $this->assertSame(1, DuplicateFinding::where('status', 'open')->count());

        $a->forceFill(['full_name' => 'Someone Else', 'name' => 'Someone Else'])->save();
        $this->artisan('duplicates:watch')->assertSuccessful();

        $this->assertSame(0, DuplicateFinding::where('status', 'open')->count());
        $this->assertSame(1, DuplicateFinding::where('status', 'gone')->count());
    }
}
