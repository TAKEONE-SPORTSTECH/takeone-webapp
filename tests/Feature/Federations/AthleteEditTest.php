<?php

namespace Tests\Feature\Federations;

use App\Events\Models\ClubEventRegistration;
use App\Federations\Models\Federation;
use App\Federations\Models\FederationAthlete;
use App\Federations\Models\FederationClub;
use App\Federations\Models\FederationEntry;
use App\Federations\Models\FederationEntryAthlete;
use App\Federations\Models\FederationEvent;
use App\Federations\Services\FederationPlatformEvent;
use App\Members\Models\User;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Editing an entered athlete from the event's Athletes tab
 * (FederationAthleteEditor, federations.api.regs.athlete[.update]).
 *
 * What this protects: only the federation's own managers edit, and only
 * athletes on their own events; every athlete's details are the federation's
 * to correct, own account or not (owner, 2026-10-15); a date of birth OR a
 * stated age, never an invented birthdate; a club change moves the athlete to
 * that club's entry; the national ID stays one-per-person; and a new photo
 * becomes the face every entry shows.
 */
class AthleteEditTest extends TestCase
{
    private User $owner;
    private Federation $fed;

    protected function setUp(): void
    {
        parent::setUp();
        config(['federations.enabled' => true, 'federations.platform_events' => true]);
        $this->owner = $this->createUser();
        $this->fed = (new Federation)->forceFill([
            'owner_user_id' => $this->owner->id, 'name' => 'Test Karate Federation', 'short_name' => 'TKF',
            'country' => 'BH', 'currency' => 'BHD', 'sport' => 'Karate', 'color' => '#1e2c4f',
            'status' => Federation::STATUS_APPROVED, 'approved_at' => now(),
        ]);
        $this->fed->save();
    }

    private function fedEvent(Federation $fed): FederationEvent
    {
        $e = (new FederationEvent)->forceFill([
            'federation_id' => $fed->id, 'created_by' => $fed->owner_user_id,
            'name' => 'National Karate Cup', 'sport' => 'Karate', 'status' => 'Registration open',
            'starts_on' => now()->addMonth()->format('Y-m-d').'T09:00',
            'details' => ['venue' => 'Isa Sports City', 'types' => ['Individual Kumite'], 'free' => false,
                'fees' => [['label' => 'Individual Kumite', 'amount' => '15', 'type' => 'Individual Kumite']]],
        ]);
        $e->save();

        return $e->fresh('federation');
    }

    /** An athlete on a club's entry. `unclaimed` = a person the federation wrote down. */
    private function entered(FederationEvent $fe, bool $unclaimed = true): array
    {
        $club = $this->createClub($this->createUser(), ['country' => 'BH']);
        $fc = FederationClub::forceCreate(['federation_id' => $fe->federation_id, 'tenant_id' => $club->id, 'status' => 'approved', 'uuid' => (string) \Illuminate\Support\Str::uuid()]);
        $user = $this->createUser(['full_name' => 'Ali Hassan', 'gender' => 'Male', 'birthdate' => '2001-05-04', 'is_unclaimed' => $unclaimed]);
        $athlete = FederationAthlete::forceCreate(['user_id' => $user->id, 'takeone_id' => 'BHR-AH'.random_int(100000, 999999), 'nationality' => 'BHR']);
        $entry = FederationEntry::forceCreate(['federation_event_id' => $fe->id, 'federation_club_id' => $fc->id, 'ref' => 'NKC-'.random_int(1000, 9999)]);
        FederationEntryAthlete::forceCreate(['federation_entry_id' => $entry->id, 'federation_athlete_id' => $athlete->id,
            'weight' => 70, 'types' => ['Individual Kumite'], 'status' => 'Active', 'approval' => 'Approved']);

        return compact('user', 'athlete', 'entry');
    }

    private function save(User $as, array $a, array $data)
    {
        return $this->actingAs($as)->postJson(route('federations.api.regs.athlete.update'),
            ['entry' => $a['entry']->uuid, 'athlete' => $a['athlete']->takeone_id] + $data);
    }

    public function test_a_manager_edits_an_athlete_the_federation_wrote_down(): void
    {
        $a = $this->entered($this->fedEvent($this->fed));

        $this->actingAs($this->owner)->getJson(route('federations.api.regs.athlete', ['entry' => $a['entry']->uuid, 'athlete' => $a['athlete']->takeone_id]))
            ->assertOk()->assertJsonPath('athlete.name', 'Ali Hassan')->assertJsonPath('athlete.claimed', false)->assertJsonPath('canEdit', true);

        $this->save($this->owner, $a, ['name' => 'Ali Hassan Ahmed', 'gender' => 'Male', 'dob' => '2001-06-04', 'nat' => 'KWT', 'natId' => '010101010', 'weight' => 68.5])
            ->assertOk()->assertJsonPath('success', true)->assertJsonPath('athlete.weight', 68.5);

        $u = $a['user']->fresh();
        $this->assertSame('Ali Hassan Ahmed', $u->full_name);
        $this->assertSame('2001-06-04', $u->birthdate->format('Y-m-d'));
        $this->assertSame('KW', $u->nationality);
        $at = $a['athlete']->fresh();
        $this->assertSame('KWT', $at->nationality);
        $this->assertSame('010101010', $at->national_id);
        $this->assertSame(68.5, (float) FederationEntryAthlete::where('federation_athlete_id', $at->id)->value('weight'));
    }

    public function test_a_claimed_account_is_edited_too(): void
    {
        $a = $this->entered($this->fedEvent($this->fed), unclaimed: false);

        $this->save($this->owner, $a, ['name' => 'Ali Hassan Isa', 'gender' => 'Male', 'ageMode' => 'dob', 'dob' => '2001-07-04', 'natId' => '999888777'])
            ->assertOk()->assertJsonPath('athlete.claimed', true);

        $u = $a['user']->fresh();
        $this->assertSame('Ali Hassan Isa', $u->full_name);
        $this->assertSame('2001-07-04', $u->birthdate->format('Y-m-d'));
        $this->assertSame('999888777', $a['athlete']->fresh()->national_id);
    }

    public function test_an_age_instead_of_a_date_of_birth_is_kept_on_the_entry(): void
    {
        $fe = $this->fedEvent($this->fed);
        $a = $this->entered($fe);
        $ev = app(FederationPlatformEvent::class)->sync($fe);
        app(\App\Federations\Services\FederationPlatformEntries::class)->sync($fe->fresh());

        $this->save($this->owner, $a, ['ageMode' => 'age', 'age' => 16])->assertOk()
            ->assertJsonPath('athlete.ageMode', 'age')->assertJsonPath('athlete.age', 16)->assertJsonPath('athlete.registered', true);

        $this->assertNull($a['user']->fresh()->birthdate, 'never an invented birthdate');
        $reg = ClubEventRegistration::where('event_id', $ev->id)->where('user_id', $a['user']->id)->first();
        $this->assertSame(16, (int) $reg->declared_age);
        $this->assertNotNull($reg->category_id, 'a stated age is enough to place them');
        $this->assertLessThanOrEqual(17, (int) \App\Events\Models\EventCategory::find($reg->category_id)->max_age);

        // Back to a date of birth: the stated age goes.
        $this->save($this->owner, $a, ['ageMode' => 'dob', 'dob' => '2009-03-02'])->assertOk()->assertJsonPath('athlete.ageMode', 'dob');
        $this->assertNull($reg->fresh()->declared_age);
        $this->assertSame('2009-03-02', $a['user']->fresh()->birthdate->format('Y-m-d'));

        $this->save($this->owner, $a, ['ageMode' => 'age', 'age' => 1])->assertStatus(422);
    }

    public function test_a_club_change_moves_the_athlete_to_that_clubs_entry(): void
    {
        $fe = $this->fedEvent($this->fed);
        $a = $this->entered($fe);
        $ev = app(FederationPlatformEvent::class)->sync($fe);
        app(\App\Federations\Services\FederationPlatformEntries::class)->sync($fe->fresh());
        $other = $this->createClub($this->createUser(), ['country' => 'BH']);
        $fc = FederationClub::forceCreate(['federation_id' => $this->fed->id, 'tenant_id' => $other->id, 'status' => 'approved', 'uuid' => (string) \Illuminate\Support\Str::uuid()]);

        $res = $this->save($this->owner, $a, ['club' => 'fc-'.$fc->uuid])->assertOk()->assertJsonPath('athlete.club', 'fc-'.$fc->uuid);

        $moved = FederationEntryAthlete::where('federation_athlete_id', $a['athlete']->id)->with('entry')->get();
        $this->assertCount(1, $moved);
        $this->assertSame($fc->id, (int) $moved->first()->entry->federation_club_id);
        $this->assertNotSame($a['entry']->uuid, $res->json('entry'));
        $this->assertSame($other->id, (int) ClubEventRegistration::where('event_id', $ev->id)->where('user_id', $a['user']->id)->value('representing_tenant_id'));

        // A club outside this federation is refused.
        $this->save($this->owner, ['entry' => $moved->first()->entry, 'athlete' => $a['athlete']], ['club' => 'fc-'.\Illuminate\Support\Str::uuid()])->assertStatus(422);
    }

    public function test_saving_puts_back_an_athlete_who_lost_their_entry(): void
    {
        $fe = $this->fedEvent($this->fed);
        $a = $this->entered($fe);
        $ev = app(FederationPlatformEvent::class)->sync($fe);
        app(\App\Federations\Services\FederationPlatformEntries::class)->sync($fe->fresh());
        // The request stays accepted, the entry itself is gone.
        ClubEventRegistration::where('event_id', $ev->id)->where('user_id', $a['user']->id)->delete();
        $this->assertSame('accepted', \App\Events\Models\EventPublicEntry::where('event_id', $ev->id)->where('user_id', $a['user']->id)->value('state'));

        $this->actingAs($this->owner)->getJson(route('federations.api.regs.athlete', ['entry' => $a['entry']->uuid, 'athlete' => $a['athlete']->takeone_id]))
            ->assertOk()->assertJsonPath('athlete.registered', false);

        $this->save($this->owner, $a, ['weight' => 71])->assertOk()->assertJsonPath('athlete.registered', true);
        $this->assertTrue(ClubEventRegistration::where('event_id', $ev->id)->where('user_id', $a['user']->id)->where('role', 'participant')->exists());
    }

    public function test_removing_takes_the_athlete_off_the_event(): void
    {
        $fe = $this->fedEvent($this->fed);
        $a = $this->entered($fe);
        $ev = app(FederationPlatformEvent::class)->sync($fe);
        app(\App\Federations\Services\FederationPlatformEntries::class)->sync($fe->fresh());
        $this->assertTrue(ClubEventRegistration::where('event_id', $ev->id)->where('user_id', $a['user']->id)->exists());

        $this->actingAs($this->owner)->get(route('federations.athlete-edit', ['entry' => $a['entry']->uuid, 'athlete' => $a['athlete']->takeone_id]))
            ->assertOk()->assertSee('regs', false)->assertSee('withdraw', false);
        $this->actingAs($this->owner)->postJson(route('federations.api.regs.withdraw'), ['athletes' => [['entry' => $a['entry']->uuid, 'athlete' => $a['athlete']->takeone_id]]])
            ->assertOk()->assertJsonPath('success', true);

        $this->assertSame('Withdrawn', FederationEntryAthlete::where('federation_athlete_id', $a['athlete']->id)->value('status'));
        $this->assertFalse(ClubEventRegistration::where('event_id', $ev->id)->where('user_id', $a['user']->id)->where('role', 'participant')->exists());
    }

    public function test_removing_a_club_takes_all_its_athletes_off_the_event(): void
    {
        $fe = $this->fedEvent($this->fed);
        $a = $this->entered($fe);
        $b = $this->entered($fe);
        $ev = app(FederationPlatformEvent::class)->sync($fe);
        app(\App\Federations\Services\FederationPlatformEntries::class)->sync($fe->fresh());
        $club = 'fc-'.FederationClub::find($a['entry']->federation_club_id)->uuid;

        // Somebody else's federation: not found, nothing changes.
        $other = $this->createUser();
        (new Federation)->forceFill(['owner_user_id' => $other->id, 'name' => 'Other', 'short_name' => 'OF', 'country' => 'BH', 'currency' => 'BHD',
            'sport' => 'Karate', 'color' => '#1e2c4f', 'status' => Federation::STATUS_APPROVED, 'approved_at' => now()])->save();
        $this->actingAs($other)->postJson(route('federations.api.regs.withdraw-club'), ['event' => $fe->uuid, 'club' => $club])->assertNotFound();

        $this->actingAs($this->owner)->postJson(route('federations.api.regs.withdraw-club'), ['event' => $fe->uuid, 'club' => $club])
            ->assertOk()->assertJsonPath('withdrawn', 1);

        $this->assertSame('Withdrawn', FederationEntryAthlete::where('federation_athlete_id', $a['athlete']->id)->value('status'));
        $this->assertFalse(ClubEventRegistration::where('event_id', $ev->id)->where('user_id', $a['user']->id)->where('role', 'participant')->exists());
        // Another club's athlete stays in.
        $this->assertSame('Active', FederationEntryAthlete::where('federation_athlete_id', $b['athlete']->id)->value('status'));
        $this->assertTrue(ClubEventRegistration::where('event_id', $ev->id)->where('user_id', $b['user']->id)->exists());
    }

    public function test_nil_in_an_entrys_referee_box_appoints_nobody(): void
    {
        $fe = $this->fedEvent($this->fed);
        $a = $this->entered($fe);
        $ev = app(FederationPlatformEvent::class)->sync($fe);
        $entries = app(\App\Federations\Services\FederationPlatformEntries::class);

        // As it used to be: "Nil" became a person.
        $a['entry']->forceFill(['officials' => ['mgr' => ['name' => 'Bassam Eid'], 'ref1' => ['name' => 'Nil'], 'ref2' => ['name' => ' none ']]])->save();
        $entries->sync($fe->fresh());
        $names = \App\Events\Models\EventOfficial::where('event_id', $ev->id)->with('user')->get()->map(fn ($o) => $o->user->full_name)->all();
        $this->assertSame(['Bassam Eid'], $names);
        $this->assertTrue(\App\Federations\Support\NobodyName::is('لا يوجد'));
        $this->assertFalse(\App\Federations\Support\NobodyName::is('Nilesh'));
    }

    public function test_the_entry_sync_never_undoes_the_federations_edits(): void
    {
        $fe = $this->fedEvent($this->fed);
        $a = $this->entered($fe);
        $ev = app(FederationPlatformEvent::class)->sync($fe);
        $sync = fn () => app(\App\Federations\Services\FederationPlatformEntries::class)->sync($fe->fresh());
        $officials = fn () => \App\Events\Models\EventOfficial::where('event_id', $ev->id)->with('user')->orderBy('id')->get();

        $a['entry']->forceFill(['officials' => ['mgr' => ['name' => 'Saher'], 'ref1' => ['name' => 'Abuabdallah']]])->save();
        $sync();
        $this->assertSame(['Saher', 'Abuabdallah'], $officials()->map(fn ($o) => $o->user->full_name)->all());
        $people = \App\Members\Models\User::count();

        // The federation renames one and removes the other; syncs that follow change nothing.
        $ref = $officials()->firstWhere('user.full_name', 'Abuabdallah');
        $ref->user->forceFill(['full_name' => 'Abu Abdallah', 'name' => 'Abu Abdallah'])->save();
        $officials()->firstWhere('user.full_name', 'Saher')->delete();
        $sync(); $sync();
        $this->assertSame(['Abu Abdallah'], $officials()->map(fn ($o) => $o->user->full_name)->all());
        $this->assertSame($people, \App\Members\Models\User::count(), 'no new person is created');

        // The CLUB changing its form still comes through.
        $a['entry']->forceFill(['officials' => ['mgr' => ['name' => 'Hassan Hussain'], 'ref1' => ['name' => 'Abuabdallah']]])->save();
        $sync();
        $this->assertContains('Hassan Hussain', $officials()->map(fn ($o) => $o->user->full_name)->all());
        $this->assertContains('Abu Abdallah', $officials()->map(fn ($o) => $o->user->full_name)->all());
    }

    public function test_taking_a_job_off_somebody_keeps_their_picture(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $fe = $this->fedEvent($this->fed);
        $ev = app(FederationPlatformEvent::class)->sync($fe);
        $person = $this->createUser(['full_name' => 'Ref One']);
        $path = 'members/'.$person->uuid.'/photos/face.jpg';
        Storage::disk('public')->put($path, 'x');
        $person->forceFill(['profile_picture' => $path])->save();
        $job1 = \App\Events\Models\EventOfficial::forceCreate(['event_id' => $ev->id, 'user_id' => $person->id, 'role' => 'jury', 'compensation' => 'volunteer', 'photo' => $path]);
        $job2 = \App\Events\Models\EventOfficial::forceCreate(['event_id' => $ev->id, 'user_id' => $person->id, 'role' => 'weigh_in', 'compensation' => 'volunteer', 'photo' => $path]);

        $job1->delete();
        $this->assertTrue(Storage::disk('public')->exists($path), 'their profile picture stays');
        $person->forceFill(['profile_picture' => null])->save();
        \Illuminate\Support\Facades\DB::table('user_photos')->where('path', $path)->delete();   // nor in their gallery
        $job2->delete();
        $this->assertFalse(Storage::disk('public')->exists($path), 'a photo nothing else names still goes');
    }

    public function test_the_edit_window_opens_for_staff_only(): void
    {
        $a = $this->entered($this->fedEvent($this->fed));
        $q = ['entry' => $a['entry']->uuid, 'athlete' => $a['athlete']->takeone_id];

        $this->actingAs($this->owner)->get(route('federations.athlete-edit', $q))->assertOk()
            ->assertSee('fedAthleteEdit', false)->assertDontSee('Ali Hassan');
        $this->actingAs($this->createUser())->get(route('federations.athlete-edit', $q))->assertNotFound();
    }

    public function test_another_federation_and_a_stranger_cannot_read_or_edit(): void
    {
        $a = $this->entered($this->fedEvent($this->fed));
        $otherOwner = $this->createUser();
        (new Federation)->forceFill([
            'owner_user_id' => $otherOwner->id, 'name' => 'Other Federation', 'short_name' => 'OF',
            'country' => 'BH', 'currency' => 'BHD', 'sport' => 'Karate', 'color' => '#1e2c4f',
            'status' => Federation::STATUS_APPROVED, 'approved_at' => now(),
        ])->save();

        foreach ([$otherOwner, $this->createUser()] as $who) {
            $this->actingAs($who)->getJson(route('federations.api.regs.athlete', ['entry' => $a['entry']->uuid, 'athlete' => $a['athlete']->takeone_id]))->assertNotFound();
            $this->save($who, $a, ['name' => 'Hacked Name'])->assertStatus($who === $otherOwner ? 422 : 404);
        }
        $this->assertSame('Ali Hassan', $a['user']->fresh()->full_name);
    }

    public function test_a_national_id_belongs_to_one_athlete(): void
    {
        $fe = $this->fedEvent($this->fed);
        $a = $this->entered($fe);
        $b = $this->entered($fe);
        $b['athlete']->forceFill(['national_id' => '123123123', 'national_id_hash' => FederationAthlete::hashNationalId('123123123')])->save();

        $this->save($this->owner, $a, ['natId' => '123123123'])->assertStatus(422)->assertJsonPath('success', false);
        $this->assertNull($a['athlete']->fresh()->national_id);
    }

    public function test_bad_input_is_refused(): void
    {
        $a = $this->entered($this->fedEvent($this->fed));

        $this->save($this->owner, $a, ['gender' => 'm'])->assertStatus(422);
        $this->save($this->owner, $a, ['dob' => now()->addDay()->format('Y-m-d')])->assertStatus(422);
        $this->save($this->owner, $a, ['weight' => 900])->assertStatus(422);
        $this->save($this->owner, $a, ['photo' => 'javascript:alert(1)'])->assertStatus(422);
        $this->save($this->owner, $a, ['nat' => 'ZZZ'])->assertStatus(422);
        $this->assertSame('Ali Hassan', $a['user']->fresh()->full_name);
    }

    public function test_a_new_photo_becomes_the_face_every_entry_shows(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $fe = $this->fedEvent($this->fed);
        $a = $this->entered($fe);
        $old = 'members/'.$a['user']->uuid.'/photos/old.jpg';
        $a['user']->forceFill(['profile_picture' => $old])->save();
        $ev = app(FederationPlatformEvent::class)->sync($fe);
        $reg = ClubEventRegistration::where('event_id', $ev->id)->where('user_id', $a['user']->id)->first();
        $this->assertNotNull($reg);
        $reg->forceFill(['photo' => $old])->save();

        // A real 1×1 JPEG — the bytes are checked, not the data URI's claim.
        $jpeg = 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=';
        $this->save($this->owner, $a, ['photo' => $jpeg])->assertOk();

        $new = (string) $a['user']->fresh()->profile_picture;
        $this->assertNotSame($old, $new);
        $this->assertStringStartsWith('members/'.$a['user']->uuid.'/photos/', $new);
        $this->assertSame($new, $reg->fresh()->photo);
    }
}
