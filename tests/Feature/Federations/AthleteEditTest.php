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
 * athletes on their own events; a person the federation wrote down has their
 * details kept here, a member who claimed their account keeps their own; the
 * national ID stays one-per-person; and a new photo becomes the face every
 * entry shows.
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
            ->assertOk()->assertJsonPath('athlete.name', 'Ali Hassan')->assertJsonPath('athlete.ownDetails', true)->assertJsonPath('canEdit', true);

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

    public function test_a_claimed_account_keeps_its_own_details(): void
    {
        $a = $this->entered($this->fedEvent($this->fed), unclaimed: false);

        $this->save($this->owner, $a, ['name' => 'Someone Else', 'gender' => 'Female', 'dob' => '1990-01-01', 'natId' => '999888777'])
            ->assertOk();

        $u = $a['user']->fresh();
        $this->assertSame('Ali Hassan', $u->full_name);
        $this->assertSame('Male', $u->gender);
        $this->assertSame('2001-05-04', $u->birthdate->format('Y-m-d'));
        // What the federation itself holds still changes.
        $this->assertSame('999888777', $a['athlete']->fresh()->national_id);
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
