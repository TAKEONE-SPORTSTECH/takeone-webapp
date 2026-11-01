<?php

namespace Tests\Feature\Federations;

use App\Events\Models\ClubEvent;
use App\Federations\Models\Federation;
use App\Federations\Models\FederationClub;
use App\Federations\Models\FederationClubApplication;
use App\Federations\Models\FederationEvent;
use App\Federations\Models\FederationFee;
use App\Federations\Models\FederationFeeSeason;
use App\Federations\Services\FederationClubFees;
use App\Federations\Services\FederationPlatformEvent;
use App\Federations\Support\ClubRecognition;
use App\Members\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The club yearly federation fee: only a club recognised for the season may
 * enter a RANKED national event, only results earned while recognised count
 * toward the ranking — and nothing changes until the switch is on, the
 * federation has a season, and the day is on or after that season's start.
 */
class ClubFeesTest extends TestCase
{
    private User $owner;
    private Federation $fed;
    private FederationClub $club;

    protected function setUp(): void
    {
        parent::setUp();
        config(['federations.enabled' => true, 'federations.platform_events' => true, 'federations.club_fees' => true]);
        ClubRecognition::forget();
        $this->owner = $this->createUser();
        $this->fed = (new Federation)->forceFill([
            'owner_user_id' => $this->owner->id, 'name' => 'Bahrain Karate Federation', 'short_name' => 'BKF',
            'country' => 'BH', 'currency' => 'BHD', 'sport' => 'Karate', 'color' => '#1e2c4f',
            'status' => Federation::STATUS_APPROVED, 'approved_at' => now(),
        ]);
        $this->fed->save();
        $this->club = FederationClub::forceCreate([
            'federation_id' => $this->fed->id, 'tenant_id' => $this->createClub($this->createUser())->id,
            'status' => FederationClub::STATUS_APPROVED, 'uuid' => (string) Str::uuid(),
        ]);
    }

    /** A federation event whose platform event grants ranking points (Karate, Shotokan rule book). */
    private function rankedEvent(string $day = '+1 month', string $system = 'shotokan'): FederationEvent
    {
        $e = (new FederationEvent)->forceFill([
            'federation_id' => $this->fed->id, 'created_by' => $this->owner->id,
            'name' => 'National Karate Cup', 'sport' => 'Karate', 'status' => 'Registration open',
            'starts_on' => CarbonImmutable::parse($day)->format('Y-m-d').'T09:00',
            'details' => ['venue' => 'Isa Sports City', 'types' => ['Individual Kumite'], 'free' => false,
                'fees' => [['label' => 'Individual Kumite', 'amount' => '15', 'type' => 'Individual Kumite']]],
        ]);
        $e->save();
        $ce = app(FederationPlatformEvent::class)->sync($e->fresh('federation'));
        $this->assertInstanceOf(ClubEvent::class, $ce);
        $ce->forceFill(['sport' => 'karate', 'category_system' => $system, 'status' => 'active'])->save();

        return $e->fresh(['federation']);
    }

    private function season(string $start = '-1 month', string $end = '+11 months', int $grace = 30): FederationFeeSeason
    {
        [$s, $err] = app(FederationClubFees::class)->saveSeason($this->owner, $this->fed, [
            'name' => 'Season '.Str::random(4), 'starts_on' => CarbonImmutable::parse($start)->toDateString(),
            'ends_on' => CarbonImmutable::parse($end)->toDateString(), 'grace_days' => $grace, 'club_fee' => 50,
        ]);
        $this->assertNull($err);

        return $s;
    }

    private function feeFor(FederationFeeSeason $s): FederationFee
    {
        app(FederationClubFees::class)->issue($s);

        return FederationFee::where('season_id', $s->id)->where('federation_club_id', $this->club->id)->firstOrFail();
    }

    public function test_nothing_is_enforced_while_switched_off_or_before_a_season_exists(): void
    {
        $event = $this->rankedEvent();
        $this->assertNull(ClubRecognition::entryRefusal($event, $this->club), 'no season yet');

        $this->season();
        config(['federations.club_fees' => false]);
        ClubRecognition::forget();
        $this->assertNull(ClubRecognition::entryRefusal($event, $this->club), 'switched off');
    }

    public function test_an_unpaid_club_cannot_enter_a_ranked_event_and_a_paid_one_can(): void
    {
        $event = $this->rankedEvent();
        $fee = $this->feeFor($this->season());
        $this->assertSame(FederationFee::DUE, $fee->status);

        $this->assertStringContainsString('ranked national event', (string) ClubRecognition::entryRefusal($event, $this->club));

        // an application (not yet a platform club) is never recognised
        $app = FederationClubApplication::forceCreate([
            'federation_id' => $this->fed->id, 'name' => 'New Dojo', 'coach' => 'Coach',
            'status' => FederationClubApplication::STATUS_PENDING, 'invite_token' => Str::random(48),
        ]);
        $this->assertNotNull(ClubRecognition::entryRefusal($event, $app));

        $fee->forceFill(['status' => FederationFee::APPROVED])->save();
        $this->assertNull(ClubRecognition::entryRefusal($event, $this->club));
    }

    public function test_unranked_events_and_days_before_the_first_season_are_never_gated(): void
    {
        $this->feeFor($this->season('+2 months', '+14 months'));

        // before the first season starts — nothing retroactive
        $this->assertNull(ClubRecognition::entryRefusal($this->rankedEvent('+1 month'), $this->club));
        // a friendly (rule book not ranked)
        $this->assertNull(ClubRecognition::entryRefusal($this->rankedEvent('+3 months', 'wkf'), $this->club));
        // a ranked event inside the season, unpaid → gated
        $this->assertNotNull(ClubRecognition::entryRefusal($this->rankedEvent('+3 months'), $this->club));
    }

    public function test_grace_keeps_a_club_recognised_after_its_season_ends(): void
    {
        $fee = $this->feeFor($this->season('-13 months', '-10 days', 30));
        $fee->forceFill(['status' => FederationFee::APPROVED])->save();
        $tenant = (int) $this->club->tenant_id;

        $this->assertTrue(ClubRecognition::recognised($this->fed, $tenant, CarbonImmutable::parse('-5 days')));
        $this->assertFalse(ClubRecognition::recognised($this->fed, $tenant, CarbonImmutable::parse('+25 days')));
    }

    public function test_ranking_counts_only_results_earned_while_recognised(): void
    {
        $event = $this->rankedEvent();
        $ce = app(FederationPlatformEvent::class)->of($event);
        $fee = $this->feeFor($this->season());
        $day = CarbonImmutable::parse('+1 month');
        $tenant = (int) $this->club->tenant_id;

        $this->assertFalse(ClubRecognition::countsForRanking($ce, $tenant, $day));
        $this->assertTrue(ClubRecognition::countsForRanking($ce, null, $day), 'no recorded club is not judged');
        $fee->forceFill(['status' => FederationFee::WAIVED])->save();
        $this->assertTrue(ClubRecognition::countsForRanking($ce, $tenant, $day));

        config(['federations.club_fees' => false]);
        $fee->forceFill(['status' => FederationFee::DUE])->save();
        $this->assertTrue(ClubRecognition::countsForRanking($ce, $tenant, $day), 'off = unchanged ranking');
    }

    public function test_proof_and_decisions_follow_the_rules(): void
    {
        Storage::fake('local');
        $svc = app(FederationClubFees::class);
        $fee = $this->feeFor($this->season());
        $pdf = UploadedFile::fake()->createWithContent('receipt.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n");

        // a renamed non-PDF is refused
        $fake = UploadedFile::fake()->createWithContent('receipt.pdf', '<?php echo 1;');
        $this->assertFalse($svc->submitProof($this->owner, $fee, $fake)['ok']);

        $this->assertTrue($svc->submitProof($this->owner, $fee, $pdf)['ok']);
        $fee->refresh();
        $this->assertSame(FederationFee::SUBMITTED, $fee->status);
        Storage::disk('local')->assertExists($fee->proof_path);
        $this->assertStringNotContainsString('receipt', $fee->proof_path, 'server-generated name');

        $this->assertFalse($svc->decide($this->owner, $fee, 'reject')['ok'], 'a rejection needs a reason');
        $this->assertTrue($svc->decide($this->owner, $fee, 'reject', 'Amount does not match')['ok']);
        $this->assertTrue($svc->submitProof($this->owner, $fee->fresh(), $pdf)['ok'], 'a rejected fee takes a new proof');
        $this->assertTrue($svc->decide($this->owner, $fee->fresh(), 'approve')['ok']);
        $this->assertFalse($svc->decide($this->owner, $fee->fresh(), 'approve')['ok'], 'decided once');
        $this->assertTrue($fee->fresh()->recognises());
    }

    public function test_issue_is_idempotent_and_lookups_stay_inside_the_tree(): void
    {
        $svc = app(FederationClubFees::class);
        $s = $this->season();
        $this->assertSame(1, $svc->issue($s));
        $this->assertSame(0, $svc->issue($s), 'never issued twice');

        $other = (new Federation)->forceFill(['owner_user_id' => $this->createUser()->id, 'name' => 'Other', 'country' => 'BH', 'currency' => 'BHD', 'status' => Federation::STATUS_APPROVED]);
        $other->save();
        $fee = FederationFee::where('season_id', $s->id)->first();
        $this->assertNull($svc->fee($other, $fee->uuid));
        $this->assertNull($svc->season($other, $s->uuid));
        $this->assertNotNull($svc->fee($this->fed, $fee->uuid));

        [, $err] = $svc->saveSeason($this->owner, $this->fed, ['name' => 'Bad', 'starts_on' => '2026-12-01', 'ends_on' => '2026-01-01', 'club_fee' => 10]);
        $this->assertNotNull($err);
    }
}
