<?php

namespace Tests\Feature\Events;

use App\Events\Sports\Karate\Tournament\Scoreboard\MatState as KarateMatState;
use App\Events\Sports\Taekwondo\Tournament\Scoreboard\MatState as TaekwondoMatState;
use App\Models\ClubEvent;
use App\Models\EventCategory;
use App\Models\EventMatch;
use App\Models\User;
use Tests\TestCase;

/**
 * PHASE 1 SAFETY BASELINE — who may command a live scoring mat, and with what.
 *
 * The scoring command endpoint is the attack surface of the whole competition:
 * it decides official results. The operator page in front of it is only a
 * convenience, so every guard has to hold at the ENDPOINT — against a second
 * laptop, a stale tab, a replayed request or someone who simply typed the URL.
 *
 * These tests assert AUTHORIZATION AND REQUEST VALIDATION ONLY. No scoring rule
 * is exercised here and no expectation is derived from the WKF or World
 * Taekwondo rulebooks — the Phase 0 unit tests cover current rule behaviour.
 *
 * Production sources under test (NOT modified):
 *   · app/Events/Sports/Karate/Tournament/Scoreboard/ScoreboardController::command()
 *   · app/Events/Sports/Taekwondo/Tournament/Scoreboard/ScoreboardController::command()
 *   · app/Events/Support/EventAccess::canScore()
 *   · routes/web.php — the 'auth','verified','two-factor' groups around both endpoints
 *
 * Every test that asserts a refusal ALSO asserts that nothing moved: neither the
 * mat's live state nor the match row that becomes the official record. A guard
 * that returns the right status code while still applying the command would be
 * the worst possible failure, and a status assertion alone would not catch it.
 */
class ScoringAuthorizationSafetyTest extends TestCase
{
    private const MAT = 'Mat 1';

    /**
     * A championship on one mat, with an organiser who may score it.
     *
     * Modelled on tests/Feature/Events/TaekwondoRealtimeTest::scenario() and
     * built entirely from the Tests\TestCase helpers — no new factory
     * infrastructure. All data is fake and created per test against the
     * in-memory SQLite database.
     *
     * @return array{0: User, 1: ClubEvent, 2: EventMatch}
     */
    private function scenario(string $sport): array
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner, ['country' => 'BH', 'currency' => 'BHD']);
        $owner->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);

        // created_by === the organiser, which is what EventAccess::canManage()
        // (and therefore canScore()) reads.
        $event = ClubEvent::create([
            'tenant_id' => $club->id,
            'created_by' => $owner->id,
            'title' => 'Safety Baseline Open',
            'event_type' => 'championship',
            'sport' => $sport,
            'scope' => 'internal',
            'date' => now()->addWeeks(2)->toDateString(),
            'end_date' => now()->addWeeks(2)->toDateString(),
            'start_time' => '09:00',
            'end_time' => '17:00',
            'status' => 'active',
            'is_archived' => false,
        ]);

        $cat = EventCategory::create([
            'event_id' => $event->id,
            'name' => 'Senior Men -58 kg',
            'sort_order' => 1,
        ]);

        // The court is what makes ScoreboardController::matExists() true for
        // self::MAT — an event with no mat of that name refuses the command.
        $match = EventMatch::create([
            'event_id' => $event->id,
            'category_id' => $cat->id,
            'round' => 'Final',
            'phase' => 'finals',
            'slot' => 0,
            'a_name' => 'Ali',
            'b_name' => 'Bader',
            'court' => self::MAT,
            'status' => 'upcoming',
        ]);

        return [$owner, $event, $match];
    }

    /** The signed-in POST url for a sport's scoring command endpoint. */
    private function endpoint(string $sport, ClubEvent $event): string
    {
        return "/{$sport}/control/{$event->uuid}";
    }

    /** The mat's live state, read back through the package's own loader. */
    private function state(string $sport, ClubEvent $event): array
    {
        return $sport === 'karate'
            ? KarateMatState::load($event, self::MAT)->toArray()
            : TaekwondoMatState::load($event, self::MAT)->toArray();
    }

    /**
     * Nothing moved: no bout on the mat, no score on it, and the match row that
     * becomes the official record is untouched.
     *
     * The mat's running state lives in the cache, which the test environment
     * exposes safely because phpunit.xml pins CACHE_STORE=array — an in-process
     * store, per test, that reaches no shared cache server.
     */
    private function assertNothingScored(string $sport, ClubEvent $event, EventMatch $match): void
    {
        $state = $this->state($sport, $event);

        $this->assertNull($state['matchId'], 'a refused command must not put a bout on the mat');
        $this->assertSame(0, $state['akaScore'], 'a refused command must not score');
        $this->assertSame(0, $state['aoScore'], 'a refused command must not score');
        $this->assertSame('upcoming', $state['mode'], 'a refused command must not change what the wall shows');

        $fresh = $match->fresh();
        $this->assertSame('upcoming', $fresh->status, 'a refused command must not change the match status');
        $this->assertNull($fresh->winner, 'a refused command must never decide a bout');
        $this->assertNull($fresh->a_score, 'a refused command must not write a score to the record');
        $this->assertNull($fresh->b_score, 'a refused command must not write a score to the record');
    }

    public static function sports(): array
    {
        return ['taekwondo' => ['taekwondo'], 'karate' => ['karate']];
    }

    /* ---------------------------------------------------------------
     * Positive control
     *
     * Runs FIRST in intent: without it, every refusal test below could pass
     * against a route that is simply broken for everyone. This proves the
     * endpoint accepts an authorised organiser and really does write.
     * --------------------------------------------------------------- */

    /**
     * @dataProvider sports
     */
    public function test_an_authorised_organiser_may_command_the_mat(string $sport): void
    {
        // Locks EventAccess::canScore() via canManage() — the event's creator.
        // 'meta' is in both sports' Scoring::COMMANDS and is one of the few
        // commands that may run on an empty mat, so this proves the endpoint
        // works without touching a single scoring rule.
        [$owner, $event, $match] = $this->scenario($sport);

        $this->actingAs($owner)
            ->postJson($this->endpoint($sport, $event), [
                'mat' => self::MAT,
                'command' => 'meta',
                'tournament' => 'Safety Baseline Open',
            ])
            ->assertSuccessful();

        $this->assertSame(
            'Safety Baseline Open',
            $this->state($sport, $event)['tournament'],
            'an authorised command must actually reach the mat'
        );

        // Even an accepted header command writes no result.
        $this->assertSame('upcoming', $match->fresh()->status);
    }

    /* ---------------------------------------------------------------
     * 1. Unauthenticated
     * --------------------------------------------------------------- */

    /**
     * @dataProvider sports
     */
    public function test_an_unauthenticated_request_cannot_command_the_mat(string $sport): void
    {
        // Locks the 'auth' middleware on both scoring routes (routes/web.php).
        // Status is asserted as 401 because the request is made as JSON;
        // Laravel's auth middleware redirects a browser navigation instead, so
        // this is the JSON contract, not an assumption about the browser path.
        [, $event, $match] = $this->scenario($sport);

        $this->postJson($this->endpoint($sport, $event), [
            'mat' => self::MAT,
            'command' => 'meta',
            'tournament' => 'Intruder',
        ])->assertUnauthorized();

        $this->assertNothingScored($sport, $event, $match);
    }

    /* ---------------------------------------------------------------
     * 2. Authenticated but not permitted
     * --------------------------------------------------------------- */

    /**
     * @dataProvider sports
     */
    public function test_a_signed_in_user_without_scoring_authority_is_refused(string $sport): void
    {
        // Locks abort_unless($this->canScore($event), 403) in both
        // ScoreboardController::command() methods. A member of the same club
        // who is neither the organiser nor an appointed official: being able to
        // SEE an event must never imply being able to score it.
        [, $event, $match] = $this->scenario($sport);

        $stranger = $this->createUser();
        $stranger->memberClubs()->syncWithoutDetaching([$event->tenant_id => ['status' => 'active']]);

        $this->actingAs($stranger)
            ->postJson($this->endpoint($sport, $event), [
                'mat' => self::MAT,
                'command' => 'meta',
                'tournament' => 'Not mine to set',
            ])
            ->assertForbidden();

        $this->assertNothingScored($sport, $event, $match);
    }

    /* ---------------------------------------------------------------
     * 3. Wrong sport
     * --------------------------------------------------------------- */

    public function test_a_karate_event_is_refused_by_the_taekwondo_endpoint(): void
    {
        // Locks abort_unless($event->sport === 'taekwondo', 404) in the
        // Taekwondo ScoreboardController::command().
        //
        // The actor is the event's own ORGANISER, who passes canScore() — so
        // the refusal isolates the sport guard and nothing else. Sport packages
        // must stay separate: a Karate bout must never be scored by Taekwondo's
        // engine, which counts rounds rather than points.
        [$owner, $event, $match] = $this->scenario('karate');

        $this->actingAs($owner)
            ->postJson($this->endpoint('taekwondo', $event), [
                'mat' => self::MAT,
                'command' => 'meta',
                'tournament' => 'Wrong engine',
            ])
            ->assertNotFound();

        $this->assertNothingScored('karate', $event, $match);
    }

    public function test_a_taekwondo_event_is_refused_by_the_karate_endpoint(): void
    {
        // Locks abort_unless($event->sport === 'karate', 404) in the Karate
        // ScoreboardController::command(). Same reasoning, opposite direction.
        [$owner, $event, $match] = $this->scenario('taekwondo');

        $this->actingAs($owner)
            ->postJson($this->endpoint('karate', $event), [
                'mat' => self::MAT,
                'command' => 'meta',
                'tournament' => 'Wrong engine',
            ])
            ->assertNotFound();

        $this->assertNothingScored('taekwondo', $event, $match);
    }

    /* ---------------------------------------------------------------
     * 4. Unsupported command
     * --------------------------------------------------------------- */

    /**
     * @dataProvider sports
     */
    public function test_a_command_outside_the_sports_vocabulary_is_rejected(string $sport): void
    {
        // Locks 'command' => in:<Scoring::COMMANDS> in both controllers.
        // 'obliterate' belongs to no sport at all.
        [$owner, $event, $match] = $this->scenario($sport);

        $this->actingAs($owner)
            ->postJson($this->endpoint($sport, $event), [
                'mat' => self::MAT,
                'command' => 'obliterate',
                'side' => 'aka',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('command');

        $this->assertNothingScored($sport, $event, $match);
    }

    public function test_a_karate_command_is_rejected_by_the_taekwondo_endpoint(): void
    {
        // Sport isolation at the request layer, mirroring the Phase 0 unit test
        // that asserts 'senshu' is absent from Taekwondo's Scoring::COMMANDS.
        // Here it is proven end to end: the vocabulary is enforced by the
        // endpoint, not merely declared in a constant.
        [$owner, $event, $match] = $this->scenario('taekwondo');

        $this->actingAs($owner)
            ->postJson($this->endpoint('taekwondo', $event), [
                'mat' => self::MAT,
                'command' => 'senshu',
                'side' => 'aka',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('command');

        $this->assertNothingScored('taekwondo', $event, $match);
    }

    public function test_a_taekwondo_command_is_rejected_by_the_karate_endpoint(): void
    {
        // The other direction: 'gamjeom' is Taekwondo's and is absent from
        // Karate's Scoring::COMMANDS.
        [$owner, $event, $match] = $this->scenario('karate');

        $this->actingAs($owner)
            ->postJson($this->endpoint('karate', $event), [
                'mat' => self::MAT,
                'command' => 'gamjeom',
                'side' => 'aka',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('command');

        $this->assertNothingScored('karate', $event, $match);
    }

    /* ---------------------------------------------------------------
     * 5. A mat this event does not run
     * --------------------------------------------------------------- */

    /**
     * @dataProvider sports
     */
    public function test_a_mat_the_event_does_not_run_is_rejected(string $sport): void
    {
        // Locks abort_unless($this->matExists($event, $data['mat']), 404).
        // Without it a caller could mint cache entries at will by naming any
        // string as a mat — and a wall screen could be pointed at a mat that
        // exists nowhere in the draw.
        [$owner, $event, $match] = $this->scenario($sport);

        $this->actingAs($owner)
            ->postJson($this->endpoint($sport, $event), [
                'mat' => 'Mat 99',
                'command' => 'meta',
                'tournament' => 'Phantom mat',
            ])
            ->assertNotFound();

        $this->assertNothingScored($sport, $event, $match);
    }

    /**
     * @dataProvider sports
     */
    public function test_a_missing_mat_is_rejected(string $sport): void
    {
        // Locks 'mat' => ['required', ...]. A command with no mat names no
        // scoreboard at all.
        [$owner, $event, $match] = $this->scenario($sport);

        $this->actingAs($owner)
            ->postJson($this->endpoint($sport, $event), ['command' => 'meta'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('mat');

        $this->assertNothingScored($sport, $event, $match);
    }

    /* ---------------------------------------------------------------
     * 6. Malformed action payload
     * --------------------------------------------------------------- */

    /**
     * @dataProvider sports
     */
    public function test_a_side_outside_the_two_corners_is_rejected(string $sport): void
    {
        // Locks 'side' => in:aka,ao in both controllers. There are two corners
        // on a mat; 'red' is not one of them.
        [$owner, $event, $match] = $this->scenario($sport);

        $this->actingAs($owner)
            ->postJson($this->endpoint($sport, $event), [
                'mat' => self::MAT,
                'command' => 'corner',
                'side' => 'red',
                'name' => 'Nobody',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('side');

        $this->assertNothingScored($sport, $event, $match);
    }

    public function test_a_taekwondo_scoring_action_outside_the_approved_five_is_rejected(): void
    {
        // Locks 'action' => in:<array_keys(MatState::ACTIONS)>. 'ippon' is
        // Karate's word and is worth nothing on a Taekwondo mat.
        [$owner, $event, $match] = $this->scenario('taekwondo');

        $this->actingAs($owner)
            ->postJson($this->endpoint('taekwondo', $event), [
                'mat' => self::MAT,
                'command' => 'score',
                'side' => 'aka',
                'action' => 'ippon',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('action');

        $this->assertNothingScored('taekwondo', $event, $match);
    }

    public function test_a_karate_point_outside_one_to_three_is_rejected(): void
    {
        // Locks 'n' => ['integer','min:1','max:3'] on the Karate endpoint —
        // yuko, waza-ari and ippon are the whole range a point can be worth.
        [$owner, $event, $match] = $this->scenario('karate');

        $this->actingAs($owner)
            ->postJson($this->endpoint('karate', $event), [
                'mat' => self::MAT,
                'command' => 'point',
                'side' => 'aka',
                'n' => 99,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('n');

        $this->assertNothingScored('karate', $event, $match);
    }

    public function test_a_taekwondo_adjustment_outside_its_range_is_rejected(): void
    {
        // Locks 'n' => ['integer','min:-5','max:5'] on the Taekwondo endpoint —
        // the ±1 correction on the approved control, bounded to the largest
        // technique value.
        [$owner, $event, $match] = $this->scenario('taekwondo');

        $this->actingAs($owner)
            ->postJson($this->endpoint('taekwondo', $event), [
                'mat' => self::MAT,
                'command' => 'adjust',
                'side' => 'aka',
                'n' => 50,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('n');

        $this->assertNothingScored('taekwondo', $event, $match);
    }
}
