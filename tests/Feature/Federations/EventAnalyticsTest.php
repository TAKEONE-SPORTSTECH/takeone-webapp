<?php

namespace Tests\Feature\Federations;

use App\Events\Models\ClubEventRegistration;
use App\Events\Models\EventMatch;
use App\Events\Models\EventMatchEvent;
use App\Federations\Models\Federation;
use App\Federations\Models\FederationAthlete;
use App\Federations\Models\FederationClub;
use App\Federations\Models\FederationEntry;
use App\Federations\Models\FederationEntryAthlete;
use App\Federations\Models\FederationEvent;
use App\Federations\Services\FederationPlatformEntries;
use App\Federations\Services\FederationPlatformEvent;
use App\Members\Models\User;
use Tests\TestCase;

/**
 * The federation event's Analytics tab door (GET federations.analytics →
 * FederationCompetitionReport::analytics).
 *
 * What this protects: the tab reads the competition report's own figures,
 * replayed from the mats' officiating log; only the federation's own staff
 * may read them, and any other event — someone else's, or none at all — is
 * the same 404; nobody signed out reads anything; and the payload carries no
 * registration ids.
 */
class EventAnalyticsTest extends TestCase
{
    private User $owner;
    private Federation $fed;

    protected function setUp(): void
    {
        parent::setUp();
        config(['federations.enabled' => true, 'federations.platform_events' => true]);
        [$this->owner, $this->fed] = $this->federation('Test Karate Federation');
    }

    /** @return array{0: User, 1: Federation} */
    private function federation(string $name): array
    {
        $owner = $this->createUser();
        $fed = (new Federation)->forceFill([
            'owner_user_id' => $owner->id, 'name' => $name, 'short_name' => 'TKF'.random_int(10, 99),
            'country' => 'BH', 'currency' => 'BHD', 'sport' => 'Karate', 'color' => '#1e2c4f',
            'status' => Federation::STATUS_APPROVED, 'approved_at' => now(),
        ]);
        $fed->save();

        return [$owner, $fed];
    }

    /** An event with two athletes and one fought, logged bout between them. */
    private function foughtEvent(): FederationEvent
    {
        $fe = (new FederationEvent)->forceFill([
            'federation_id' => $this->fed->id, 'created_by' => $this->owner->id,
            'name' => 'National Karate Cup', 'sport' => 'Karate', 'status' => 'Registration open',
            'starts_on' => now()->addMonth()->format('Y-m-d').'T09:00',
            'details' => ['venue' => 'Isa Sports City', 'types' => ['Individual Kumite'], 'free' => false,
                'fees' => [['label' => 'Individual Kumite', 'amount' => '15', 'type' => 'Individual Kumite']]],
        ]);
        $fe->save();
        $fe = $fe->fresh('federation');

        $users = [];
        foreach ([1, 2] as $i) {
            $club = $this->createClub($this->createUser(), ['country' => 'BH']);
            $fc = FederationClub::forceCreate(['federation_id' => $this->fed->id, 'tenant_id' => $club->id, 'status' => 'approved', 'uuid' => (string) \Illuminate\Support\Str::uuid()]);
            $users[$i] = $this->createUser(['gender' => 'Male', 'birthdate' => now()->subYears(25)->toDateString()]);
            $athlete = FederationAthlete::forceCreate(['user_id' => $users[$i]->id, 'takeone_id' => 'BHR-TK'.random_int(100000, 999999)]);
            $entry = FederationEntry::forceCreate(['federation_event_id' => $fe->id, 'federation_club_id' => $fc->id, 'ref' => 'NKC-'.random_int(1000, 9999)]);
            FederationEntryAthlete::forceCreate(['federation_entry_id' => $entry->id, 'federation_athlete_id' => $athlete->id,
                'weight' => 70, 'types' => ['Individual Kumite'], 'status' => 'Active', 'approval' => 'Approved']);
        }
        $ev = app(FederationPlatformEvent::class)->sync($fe);
        app(FederationPlatformEntries::class)->sync($fe->fresh());

        $ra = ClubEventRegistration::where('event_id', $ev->id)->where('user_id', $users[1]->id)->firstOrFail();
        $rb = ClubEventRegistration::where('event_id', $ev->id)->where('user_id', $users[2]->id)->firstOrFail();
        $m = EventMatch::forceCreate(['event_id' => $ev->id, 'category_id' => $ra->category_id, 'round' => 'Final', 'slot' => 0, 'match_no' => 1,
            'a_name' => 'A', 'b_name' => 'B', 'a_competitor_id' => $ra->id, 'b_competitor_id' => $rb->id, 'court' => 'Mat 1',
            'winner' => 'b', 'a_score' => '1', 'b_score' => '3', 'win_reason' => 'other', 'status' => 'done']);

        // Red scores a yuko, blue answers with an ippon and wins.
        $seq = 0;
        foreach ([['start', [], null, null], ['point', ['side' => 'aka', 'n' => 1], 'a', 80], ['point', ['side' => 'ao', 'n' => 3], 'b', 40], ['finish', ['winner' => 'ao'], null, 30]] as [$cmd, $payload, $side, $left]) {
            EventMatchEvent::create(['event_id' => $ev->id, 'match_id' => $m->id, 'court' => 'Mat 1', 'sport' => 'karate',
                'command' => $cmd, 'payload' => $payload, 'side' => $side, 'points' => $payload['n'] ?? null,
                'clock_duration' => 90, 'clock_remaining' => $left ?? 90, 'occurred_at' => now()->addSeconds(++$seq * 10), 'sequence' => $seq]);
        }

        return $fe->fresh();
    }

    public function test_the_federations_staff_read_the_reports_figures(): void
    {
        $fe = $this->foughtEvent();

        $res = $this->actingAs($this->owner)->getJson(route('federations.analytics', ['event' => $fe->uuid]))->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('analytics.stats.bouts', 1)
            ->assertJsonPath('analytics.stats.scored', 4)
            ->assertJsonPath('analytics.stats.tech.Ippon', 1)
            ->assertJsonPath('analytics.stats.first', ['won' => 0, 'of' => 1])
            ->assertJsonPath('analytics.stats.comebacks', 1)
            ->assertJsonPath('analytics.athletes', 2);

        $body = $res->getContent();
        $this->assertStringNotContainsString('"reg"', $body);
        $this->assertStringNotContainsString('"entries"', $body);
        $this->assertStringNotContainsString('data:image', $body);
    }

    /** An exhibition linked to the bout it really was counts as that bout (ExhibitionLink, 2026-10-25). */
    public function test_a_linked_exhibition_is_read_as_its_bout_turned_to_its_corners(): void
    {
        config(['events.exhibitions' => true]);
        $fe = $this->foughtEvent();
        $ev = \App\Events\Models\ClubEvent::findOrFail($fe->club_event_id);
        $ev->forceFill(['started_at' => now()->subHour()])->save();
        $done = EventMatch::where('event_id', $ev->id)->firstOrFail();

        // A rematch still waiting in the draw: B in red, A in blue.
        $rematch = EventMatch::forceCreate(['event_id' => $ev->id, 'category_id' => $done->category_id, 'round' => 'Final', 'slot' => 1, 'match_no' => 2,
            'a_name' => 'B', 'b_name' => 'A', 'a_competitor_id' => $done->b_competitor_id, 'b_competitor_id' => $done->a_competitor_id,
            'court' => 'Mat 1', 'status' => 'upcoming']);
        // Fought as an exhibition the other way round: A in red scored an ippon.
        $ex = \App\Events\Models\EventExhibitionBout::create([
            'event_id' => $ev->id, 'number' => 1, 'court' => 'Mat 2',
            'a_registration_id' => $done->a_competitor_id, 'b_registration_id' => $done->b_competitor_id,
            'a_name' => 'A', 'b_name' => 'B', 'a_score' => '3', 'b_score' => '0', 'winner' => 'a',
            'status' => \App\Events\Models\EventExhibitionBout::STATUS_DONE,
        ]);
        $seq = 100;
        foreach ([['exhibition', [], null, 90], ['start', [], null, 90], ['point', ['side' => 'aka', 'n' => 3], 'a', 50], ['finish', ['winner' => 'aka'], null, 45]] as [$cmd, $payload, $side, $left]) {
            EventMatchEvent::create(['event_id' => $ev->id, 'match_id' => null, 'court' => 'Mat 2', 'sport' => 'karate',
                'command' => $cmd, 'payload' => $payload, 'side' => $side, 'points' => $payload['n'] ?? null,
                'clock_duration' => 90, 'clock_remaining' => $left, 'occurred_at' => $ex->created_at, 'sequence' => ++$seq]);
        }

        $url = route('federations.analytics', ['event' => $fe->uuid]);
        $this->actingAs($this->owner)->getJson($url)->assertOk()
            ->assertJsonPath('analytics.stats.bouts', 1)->assertJsonPath('analytics.stats.exhibitions', 1);

        app(\App\Events\Support\ExhibitionLink::class)->link($ev, $ex, $rematch->id, $this->owner);
        \Illuminate\Support\Facades\Cache::flush();

        $this->actingAs($this->owner)->getJson($url)->assertOk()
            ->assertJsonPath('analytics.stats.bouts', 2)
            ->assertJsonPath('analytics.stats.exhibitions', 0)
            ->assertJsonPath('analytics.stats.tech.Ippon', 2)
            // Turned to the draw's corners: the ippon was scored from blue, and blue won.
            ->assertJsonPath('analytics.stats.corner', ['a' => 0, 'b' => 2]);
    }

    public function test_another_federation_and_an_unknown_event_get_the_same_404_and_a_guest_nothing(): void
    {
        $fe = $this->foughtEvent();
        [$stranger] = $this->federation('Another Federation');

        $this->actingAs($stranger)->getJson(route('federations.analytics', ['event' => $fe->uuid]))->assertNotFound();
        $this->actingAs($this->owner)->getJson(route('federations.analytics', ['event' => (string) \Illuminate\Support\Str::uuid()]))->assertNotFound();
        $this->actingAs($this->createUser())->getJson(route('federations.analytics', ['event' => $fe->uuid]))->assertNotFound();

        auth()->logout();
        $this->getJson(route('federations.analytics', ['event' => $fe->uuid]))->assertUnauthorized();
    }
}
