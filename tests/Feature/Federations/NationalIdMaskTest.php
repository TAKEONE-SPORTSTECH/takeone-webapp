<?php

namespace Tests\Feature\Federations;

use App\Federations\Models\Federation;
use App\Federations\Models\FederationAthlete;
use App\Federations\Models\FederationClub;
use App\Federations\Models\FederationEntry;
use App\Federations\Models\FederationEntryAthlete;
use App\Federations\Models\FederationEvent;
use App\Federations\Services\FederationDesk;
use App\Federations\Services\FederationDirectory;
use App\Federations\Support\NationalIdMask;
use App\Members\Models\User;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * A child's national ID is masked in the federation's lists (NationalIdMask).
 *
 * What this protects: the participants feed shows "•••••3044" for a minor and
 * the full number for an adult; an unknown age is treated as a child's;
 * opening the athlete shows the number in full and that opening is logged;
 * and a masked value sent back from a list never overwrites the real one.
 */
class NationalIdMaskTest extends TestCase
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

    private function entered(?string $birthdate, string $natId): array
    {
        $fe = (new FederationEvent)->forceFill([
            'federation_id' => $this->fed->id, 'created_by' => $this->owner->id,
            'name' => 'National Karate Cup', 'sport' => 'Karate', 'status' => 'Registration open',
            'starts_on' => now()->addMonth()->format('Y-m-d').'T09:00',
            'details' => ['venue' => 'Isa Sports City', 'types' => ['Individual Kumite'], 'free' => false, 'fees' => []],
        ]);
        $fe->save();
        $club = $this->createClub($this->createUser(), ['country' => 'BH']);
        $fc = FederationClub::forceCreate(['federation_id' => $this->fed->id, 'tenant_id' => $club->id, 'status' => 'approved', 'uuid' => (string) \Illuminate\Support\Str::uuid()]);
        $user = $this->createUser(['full_name' => 'Young Athlete', 'gender' => 'Male', 'birthdate' => $birthdate]);
        $athlete = FederationAthlete::forceCreate(['user_id' => $user->id, 'takeone_id' => 'BHR-YA'.random_int(100000, 999999),
            'nationality' => 'BHR', 'national_id' => $natId, 'national_id_hash' => FederationAthlete::hashNationalId($natId)]);
        $entry = FederationEntry::forceCreate(['federation_event_id' => $fe->id, 'federation_club_id' => $fc->id, 'ref' => 'NKC-'.random_int(1000, 9999)]);
        FederationEntryAthlete::forceCreate(['federation_entry_id' => $entry->id, 'federation_athlete_id' => $athlete->id,
            'weight' => 40, 'types' => ['Individual Kumite'], 'status' => 'Active', 'approval' => 'Approved']);

        return compact('user', 'athlete', 'entry');
    }

    /** What the dashboard's registrations feed carries for the one entered athlete. */
    private function feedNatId(): ?string
    {
        $access = app(FederationDirectory::class)->access($this->owner);
        $regs = app(FederationDesk::class)->regs($access);

        return $regs[0]['athletes'][0]['natId'] ?? null;
    }

    public function test_the_mask_keeps_the_last_four_digits(): void
    {
        $this->assertSame('•••••3044', NationalIdMask::mask('201213044'));
        $this->assertSame('201213044', NationalIdMask::forList('201213044', Carbon::parse('1990-01-01')));
        $this->assertSame('•••••3044', NationalIdMask::forList('201213044', Carbon::now()->subYears(12)));
        $this->assertSame('•••••3044', NationalIdMask::forList('201213044', null));
        $this->assertSame('—', NationalIdMask::forList('—', null));
        $this->assertTrue(NationalIdMask::isMasked('•••••3044'));
        $this->assertFalse(NationalIdMask::isMasked('201213044'));
    }

    public function test_a_minor_is_masked_in_the_participants_feed(): void
    {
        $this->entered(now()->subYears(10)->format('Y-m-d'), '201213044');

        $this->assertSame('•••••3044', $this->feedNatId());
    }

    public function test_an_unknown_age_is_masked_too(): void
    {
        $this->entered(null, '201213044');

        $this->assertSame('•••••3044', $this->feedNatId());
    }

    public function test_an_adult_is_shown_as_before(): void
    {
        $this->entered('1995-03-02', '950302111');

        $this->assertSame('950302111', $this->feedNatId());
    }

    public function test_opening_a_minor_shows_the_full_number_and_is_logged(): void
    {
        $a = $this->entered(now()->subYears(10)->format('Y-m-d'), '201213044');

        $this->actingAs($this->owner)->getJson(route('federations.api.regs.athlete', ['entry' => $a['entry']->uuid, 'athlete' => $a['athlete']->takeone_id]))
            ->assertOk()->assertJsonPath('athlete.natId', '201213044');

        $this->assertDatabaseHas('activity_log', ['description' => 'federation.national_id_viewed', 'causer_id' => $this->owner->id]);
    }

    public function test_a_masked_value_never_overwrites_the_real_number(): void
    {
        $a = $this->entered(now()->subYears(10)->format('Y-m-d'), '201213044');

        $this->actingAs($this->owner)->postJson(route('federations.api.regs.athlete.update'),
            ['entry' => $a['entry']->uuid, 'athlete' => $a['athlete']->takeone_id, 'natId' => '•••••3044'])->assertOk();

        $this->assertSame('201213044', $a['athlete']->fresh()->national_id);
    }
}
