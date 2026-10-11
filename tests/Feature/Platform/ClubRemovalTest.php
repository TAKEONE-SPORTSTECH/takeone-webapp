<?php

namespace Tests\Feature\Platform;

use App\Clubs\Models\Tenant;
use App\Events\Models\ClubEvent;
use App\Events\Models\ClubEventRegistration;
use App\Events\Models\EventCategory;
use App\Events\Support\EntryService;
use App\Federations\Models\Federation;
use App\Federations\Models\FederationAthlete;
use App\Federations\Models\FederationAthleteClub;
use App\Federations\Models\FederationClub;
use App\Members\Models\User;
use App\Platform\Services\ClubRemoval;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The super-admin's "Remove club" (2026-10-08). What it protects: athletes are
 * found through EVERY link a club holds them by; only an athlete the club
 * created and nobody else holds is deleted — a person with their own account,
 * or another club, is only unlinked; entries leave through the event's own
 * door; the federation's record of the club goes; nothing happens without the
 * typed name and the reviewed fingerprint; and none of it is reachable while
 * the flag is off or by anybody but a super-admin.
 */
class ClubRemovalTest extends TestCase
{
    private User $admin;
    private Tenant $club;
    private ClubEvent $event;
    private string $backups;

    protected function setUp(): void
    {
        parent::setUp();
        config(['features.club_removal_tool' => true, 'federations.enabled' => true]);
        $this->backups = sys_get_temp_dir().'/club-removal-test-'.Str::random(8);
        @mkdir($this->backups, 0775, true);
        config(['backup.path' => $this->backups]);
        Storage::fake('local');

        // No real backup in a test: the step is stood in for, never skipped.
        $this->app->instance(ClubRemoval::class, new class(app(EntryService::class)) extends ClubRemoval {
            protected function backup(): bool
            {
                return true;
            }
        });

        $this->admin = $this->createUser();
        $this->makeSuperAdmin($this->admin);
        $this->club = $this->createClub($this->createUser(), ['club_name' => 'Old Dojo']);

        $host = $this->createClub($this->createUser(), ['club_name' => 'Host Club']);
        $this->event = ClubEvent::create([
            'tenant_id' => $host->id, 'created_by' => $host->owner_user_id, 'title' => 'Spring Open',
            'event_type' => 'championship', 'sport' => 'taekwondo', 'scope' => 'internal',
            'date' => now()->addWeeks(2)->toDateString(), 'end_date' => now()->addWeeks(2)->toDateString(),
            'start_time' => '09:00', 'end_time' => '17:00', 'status' => 'active', 'is_archived' => false,
        ]);
        EventCategory::create(['event_id' => $this->event->id, 'name' => 'Senior Men -58 kg', 'sort_order' => 1]);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->backups.'/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->backups);
        parent::tearDown();
    }

    /** An athlete the club created: never claimed, no password, nothing of their own. */
    private function clubAthlete(string $name): User
    {
        $u = $this->createUser(['full_name' => $name, 'email' => null, 'password' => null]);
        $u->forceFill(['is_unclaimed' => true, 'password' => null])->saveQuietly();

        return $u->fresh();
    }

    private function enter(User $u, array $extra = []): ClubEventRegistration
    {
        return ClubEventRegistration::forceCreate(array_merge([
            'event_id' => $this->event->id, 'user_id' => $u->id, 'status' => 'joined', 'role' => 'participant',
            'entry_channel' => 'club', 'representing_tenant_id' => $this->club->id,
        ], $extra));
    }

    public function test_it_removes_only_what_is_the_clubs_and_unlinks_everybody_else(): void
    {
        // Held through the roster, through an entry it made, and through an entry it only pays for.
        $rostered = $this->clubAthlete('Rostered Athlete');
        DB::table('memberships')->insert(['tenant_id' => $this->club->id, 'user_id' => $rostered->id, 'status' => 'active']);
        $entered = $this->clubAthlete('Entered Athlete');
        $this->enter($entered);
        $paidFor = $this->clubAthlete('Paid-for Athlete');
        $this->enter($paidFor, ['representing_tenant_id' => null, 'payer_type' => 'club', 'payer_id' => $this->club->id]);

        // A real person with their own account, entered by the club: kept, stays in the event.
        $member = $this->createUser(['full_name' => 'Own Account']);
        $keptEntry = $this->enter($member);

        // Somebody the club created, but also on another club's roster: kept.
        $shared = $this->clubAthlete('Shared Athlete');
        DB::table('memberships')->insert(['tenant_id' => $this->club->id, 'user_id' => $shared->id, 'status' => 'active']);
        $other = $this->createClub($this->createUser(), ['club_name' => 'Other Club']);
        DB::table('memberships')->insert(['tenant_id' => $other->id, 'user_id' => $shared->id, 'status' => 'active']);

        // The federation's record of the club, with one of the athletes on its roster.
        $fed = (new Federation)->forceFill(['owner_user_id' => $this->admin->id, 'name' => 'Test Federation', 'short_name' => 'TF',
            'country' => 'BH', 'currency' => 'BHD', 'sport' => 'Karate', 'status' => Federation::STATUS_APPROVED, 'approved_at' => now()]);
        $fed->save();
        $fc = FederationClub::forceCreate(['federation_id' => $fed->id, 'tenant_id' => $this->club->id, 'status' => 'approved', 'uuid' => (string) Str::uuid()]);
        $fa = FederationAthlete::forceCreate(['user_id' => $rostered->id, 'takeone_id' => 'BHR-TK'.random_int(100000, 999999),
            'national_id' => '900101234', 'national_id_hash' => FederationAthlete::hashNationalId('900101234')]);
        FederationAthleteClub::forceCreate(['federation_athlete_id' => $fa->id, 'federation_id' => $fed->id, 'federation_club_id' => $fc->id, 'from_ym' => '2026-01']);

        // A photo of one of them, which must go with them.
        Storage::disk('local')->put('members/'.$entered->uuid.'/photos/face.jpg', 'jpeg');
        $entered->forceFill(['profile_picture' => 'members/'.$entered->uuid.'/photos/face.jpg'])->saveQuietly();

        $removal = app(ClubRemoval::class);
        $plan = $removal->plan($this->club->fresh());

        $this->assertEqualsCanonicalizing(['Entered Athlete', 'Paid-for Athlete', 'Rostered Athlete'], array_column($plan['delete'], 'name'));
        $this->assertEqualsCanonicalizing(['Own Account', 'Shared Athlete'], array_column($plan['keep'], 'name'));
        $this->assertSame([], $plan['blockers']);
        $this->assertSame(1, $plan['federation']['memberships']);

        // The typed name and the reviewed fingerprint are both required.
        $this->assertFalse($removal->execute($this->club->fresh(), $this->admin, 'Wrong Name', $plan['fingerprint'])['ok']);
        $this->assertFalse($removal->execute($this->club->fresh(), $this->admin, 'Old Dojo', str_repeat('0', 64))['ok']);
        $this->assertNotNull(User::find($entered->id));

        $result = $removal->execute($this->club->fresh(), $this->admin, 'Old Dojo', $plan['fingerprint']);
        $this->assertTrue($result['ok'], $result['message']);
        $this->assertSame(3, $result['removed']['athletes']);

        // Gone, rows and file.
        foreach ([$rostered, $entered, $paidFor] as $u) {
            $this->assertNull(User::withTrashed()->find($u->id));
        }
        Storage::disk('local')->assertMissing('members/'.$entered->uuid.'/photos/face.jpg');
        $this->assertFalse(FederationAthlete::where('national_id_hash', FederationAthlete::hashNationalId('900101234'))->exists());
        $this->assertFalse(FederationClub::whereKey($fc->id)->exists());
        $this->assertSame(0, ClubEventRegistration::where('event_id', $this->event->id)->whereIn('user_id', [$entered->id, $paidFor->id])->count());
        $this->assertNotEmpty(glob($this->backups.'/club-removal-*.zip'));

        // Kept: accounts intact, the kept entry still in the event — no longer under the club.
        $this->assertNotNull(User::find($member->id));
        $this->assertNotNull(User::find($shared->id));
        $this->assertNull($keptEntry->fresh()->representing_tenant_id);

        // And the club itself archived, exactly as the existing Delete does.
        $this->assertSoftDeleted('tenants', ['id' => $this->club->id]);
    }

    public function test_a_club_whose_athletes_have_fought_cannot_be_removed(): void
    {
        $a = $this->clubAthlete('Fighter A');
        $b = $this->createUser(['full_name' => 'Fighter B']);
        $ra = $this->enter($a);
        $rb = $this->enter($b, ['representing_tenant_id' => null]);
        DB::table('event_matches')->insert(['event_id' => $this->event->id, 'category_id' => EventCategory::where('event_id', $this->event->id)->value('id'), 'round' => 'Final', 'slot' => 0, 'a_name' => 'A', 'b_name' => 'B',
            'a_competitor_id' => $ra->id, 'b_competitor_id' => $rb->id, 'winner' => 'a', 'status' => 'done', 'won_by_bye' => 0,
            'created_at' => now(), 'updated_at' => now()]);

        $plan = app(ClubRemoval::class)->plan($this->club->fresh());
        $this->assertNotEmpty($plan['blockers']);
        $this->assertFalse(app(ClubRemoval::class)->execute($this->club->fresh(), $this->admin, 'Old Dojo', $plan['fingerprint'])['ok']);
        $this->assertNotNull(User::find($a->id));
        $this->assertNotSoftDeleted('tenants', ['id' => $this->club->id]);
    }

    public function test_only_a_super_admin_reaches_it_and_only_while_it_is_switched_on(): void
    {
        $url = route('admin.platform.clubs.removal', $this->club->id);

        $this->actingAs($this->createUser())->getJson($url)->assertForbidden();
        $this->actingAs($this->club->owner)->postJson($url, ['confirm_name' => 'Old Dojo', 'fingerprint' => str_repeat('a', 64)])->assertForbidden();

        $this->actingAs($this->admin)->getJson($url)->assertOk()->assertJsonPath('plan.club.name', 'Old Dojo')->assertJsonMissingPath('plan._ids');

        config(['features.club_removal_tool' => false]);
        $this->actingAs($this->admin)->getJson($url)->assertNotFound();
    }
}
