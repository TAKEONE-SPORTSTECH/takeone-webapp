<?php

namespace Tests\Feature\Events;

use App\Scoreboard\Sports\BrazilianJiuJitsu\Mat\Ledger;
use App\Scoreboard\Sports\BrazilianJiuJitsu\Mat\MatchEvent;
use App\Scoreboard\Sports\BrazilianJiuJitsu\Mat\MatState;
use App\Models\ClubEvent;
use App\Models\EventCategory;
use App\Models\EventMatch;
use App\Members\Models\User;
use Tests\TestCase;

/**
 * The Brazilian Jiu-Jitsu scoring table.
 *
 * Two things are being asserted, and they are the two the package exists for:
 *
 *  1. THE SCORE IS DERIVED, NOT STORED. Points, advantages and penalties are
 *     append-only ledger rows, a correction is another row, and the total is
 *     whatever replaying the survivors says. Nothing is ever updated or deleted.
 *  2. THE ENDPOINT IS THE CONTRACT. The console is a convenience: the value of
 *     a point, the legality of a command and the right to issue it are all
 *     decided server-side, against a second laptop, a stale tab or a replayed
 *     request as much as against the page we wrote.
 *
 * All data is fake and created per test against the in-memory SQLite database
 * phpunit.xml pins. ⚠️ Run `php artisan config:clear` first — see CLAUDE.md.
 */
class BjjScoreboardTest extends TestCase
{
    private const MAT = 'Mat 1';

    /**
     * A jiu-jitsu championship on one mat, with an organiser who may score it.
     *
     * @return array{0: User, 1: ClubEvent, 2: EventMatch}
     */
    private function scenario(): array
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner, ['country' => 'BH', 'currency' => 'BHD']);
        $owner->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);

        $event = ClubEvent::create([
            'tenant_id' => $club->id,
            'created_by' => $owner->id,
            'title' => 'Gulf Open',
            'event_type' => 'championship',
            'sport' => 'bjj',
            'scope' => 'internal',
            'date' => now()->addWeeks(2)->toDateString(),
            'end_date' => now()->addWeeks(2)->toDateString(),
            'start_time' => '09:00',
            'end_time' => '17:00',
            'status' => 'active',
            'is_archived' => false,
        ]);

        $category = EventCategory::create([
            'event_id' => $event->id,
            'name' => 'Adult Men Light',
            'sort_order' => 1,
        ]);

        $match = EventMatch::create([
            'event_id' => $event->id,
            'category_id' => $category->id,
            'round' => 'Final',
            'phase' => 'finals',
            'slot' => 0,
            'match_no' => 4,
            'a_name' => 'Ali',
            'b_name' => 'Bader',
            'court' => self::MAT,
            'status' => 'upcoming',
        ]);

        return [$owner, $event, $match];
    }

    private function endpoint(ClubEvent $event): string
    {
        return "/bjj/control/{$event->uuid}";
    }

    /** Issue one command as the organiser. */
    private function command(User $as, ClubEvent $event, string $command, array $payload = [])
    {
        return $this->actingAs($as)->postJson($this->endpoint($event), array_merge([
            'mat' => self::MAT,
            'command' => $command,
        ], $payload));
    }

    private function state(ClubEvent $event): MatState
    {
        $state = MatState::forMat($event, self::MAT);
        $state->setRelation('event', $event);

        return $state;
    }

    /* ---------------- The event type is a package ---------------- */

    public function test_the_registry_hands_a_bjj_championship_to_its_own_package(): void
    {
        [, $event] = $this->scenario();

        $type = app(\App\Events\EventTypeRegistry::class)->for($event);

        $this->assertSame('bjj_tournament', $type->key());
    }

    /* ---------------- The score is replayed ---------------- */

    public function test_points_come_from_the_action_not_from_the_request(): void
    {
        [$owner, $event, $match] = $this->scenario();

        $this->command($owner, $event, 'load', ['match_id' => $match->id])->assertOk();

        // A guard pass is three because the RULES say so. The console never
        // sends a value, and one smuggled into the payload is ignored.
        $response = $this->command($owner, $event, 'point', [
            'side' => 'blue', 'source' => 'guard_pass', 'value' => 99,
        ])->assertOk();

        $this->assertSame(3, $response->json('state.score.bluePoints'));
    }

    public function test_an_unknown_scoring_action_is_refused_and_nothing_is_recorded(): void
    {
        [$owner, $event, $match] = $this->scenario();

        $this->command($owner, $event, 'load', ['match_id' => $match->id]);

        $this->command($owner, $event, 'point', ['side' => 'blue', 'source' => 'flying_armbar'])
            ->assertStatus(422);

        $this->assertSame(0, MatchEvent::where('action', 'point')->count());
    }

    public function test_advantages_and_penalties_are_never_added_to_the_points_total(): void
    {
        [$owner, $event, $match] = $this->scenario();

        $this->command($owner, $event, 'load', ['match_id' => $match->id]);
        $this->command($owner, $event, 'point', ['side' => 'blue', 'source' => 'takedown']);
        $this->command($owner, $event, 'advantage', ['side' => 'blue']);
        $response = $this->command($owner, $event, 'penalty', ['side' => 'blue', 'source' => 'stalling']);

        $score = $response->json('state.score');

        $this->assertSame(2, $score['bluePoints']);
        $this->assertSame(1, $score['blueAdvantages']);
        $this->assertSame(1, $score['bluePenalties']);
    }

    public function test_an_advantage_breaks_a_tie_on_points_and_a_penalty_breaks_a_tie_on_advantages(): void
    {
        [$owner, $event, $match] = $this->scenario();

        $this->command($owner, $event, 'load', ['match_id' => $match->id]);
        $this->command($owner, $event, 'point', ['side' => 'blue', 'source' => 'sweep']);
        $this->command($owner, $event, 'point', ['side' => 'white', 'source' => 'takedown']);

        // Level on points; the advantage decides it.
        $response = $this->command($owner, $event, 'advantage', ['side' => 'white']);
        $this->assertSame('white', $response->json('state.score.leader'));
        $this->assertSame('advantages', $response->json('state.score.decidedBy'));

        // Level again once blue matches it; the FEWER penalties then decides.
        $this->command($owner, $event, 'advantage', ['side' => 'blue']);
        $response = $this->command($owner, $event, 'penalty', ['side' => 'white', 'source' => 'fleeing']);

        $this->assertSame('blue', $response->json('state.score.leader'));
        $this->assertSame('penalties', $response->json('state.score.decidedBy'));
    }

    /* ---------------- Audit, not erase ---------------- */

    public function test_a_correction_appends_a_reversal_and_never_deletes_the_row(): void
    {
        [$owner, $event, $match] = $this->scenario();

        $this->command($owner, $event, 'load', ['match_id' => $match->id]);
        $scored = $this->command($owner, $event, 'point', ['side' => 'blue', 'source' => 'mount']);

        $entryId = $scored->json('log.0.id');
        $this->assertSame(4, $scored->json('state.score.bluePoints'));

        $undone = $this->command($owner, $event, 'reverse', [
            'ledger_id' => $entryId,
            'reason' => 'Referee signalled a sweep, not a mount',
        ])->assertOk();

        // The total is back to nought — and BOTH rows are still there, which is
        // the whole point: the record still says what happened.
        $this->assertSame(0, $undone->json('state.score.bluePoints'));
        $this->assertNotNull(MatchEvent::find($entryId));
        $this->assertSame(1, MatchEvent::where('reverses_id', $entryId)->count());
        $this->assertSame(
            'Referee signalled a sweep, not a mount',
            MatchEvent::where('reverses_id', $entryId)->first()->reason
        );
    }

    public function test_a_correction_without_a_reason_is_refused(): void
    {
        [$owner, $event, $match] = $this->scenario();

        $this->command($owner, $event, 'load', ['match_id' => $match->id]);
        $scored = $this->command($owner, $event, 'point', ['side' => 'blue', 'source' => 'sweep']);

        $this->command($owner, $event, 'reverse', ['ledger_id' => $scored->json('log.0.id')])
            ->assertStatus(422);

        $this->assertSame(2, $this->state($event)->tally()->bluePoints);
    }

    public function test_the_same_entry_cannot_be_reversed_twice(): void
    {
        [$owner, $event, $match] = $this->scenario();

        $this->command($owner, $event, 'load', ['match_id' => $match->id]);
        $scored = $this->command($owner, $event, 'point', ['side' => 'blue', 'source' => 'sweep']);
        $id = $scored->json('log.0.id');

        $this->command($owner, $event, 'reverse', ['ledger_id' => $id, 'reason' => 'first'])->assertOk();
        $this->command($owner, $event, 'reverse', ['ledger_id' => $id, 'reason' => 'again'])->assertStatus(422);

        $this->assertSame(1, MatchEvent::where('reverses_id', $id)->count());
    }

    /* ---------------- The rules the engine enforces ---------------- */

    public function test_the_fourth_penalty_disqualifies_and_hands_the_match_to_the_other_corner(): void
    {
        [$owner, $event, $match] = $this->scenario();

        $this->command($owner, $event, 'load', ['match_id' => $match->id]);
        $this->command($owner, $event, 'start');

        // Blue is well ahead on points; the ladder still takes it away.
        $this->command($owner, $event, 'point', ['side' => 'blue', 'source' => 'back_control']);

        $last = null;
        for ($i = 0; $i < 4; $i++) {
            $last = $this->command($owner, $event, 'penalty', ['side' => 'blue', 'source' => 'conduct']);
        }

        $this->assertSame('dq', $last->json('state.status'));
        $this->assertSame('white', $last->json('state.winner'));
        $this->assertSame(4, $last->json('state.score.bluePoints'));
    }

    public function test_a_level_match_cannot_be_ended_without_a_referee_decision(): void
    {
        [$owner, $event, $match] = $this->scenario();

        $this->command($owner, $event, 'load', ['match_id' => $match->id]);
        $this->command($owner, $event, 'start');

        $this->command($owner, $event, 'end')->assertStatus(422);
        $this->command($owner, $event, 'commit')->assertStatus(422);

        $this->command($owner, $event, 'decision', ['winner' => 'blue'])->assertOk();

        $this->assertSame('blue', $this->state($event)->winner);
        $this->assertSame('decision', $this->state($event)->win_method);
    }

    public function test_a_submission_outranks_the_score(): void
    {
        [$owner, $event, $match] = $this->scenario();

        $this->command($owner, $event, 'load', ['match_id' => $match->id]);
        $this->command($owner, $event, 'start');
        $this->command($owner, $event, 'point', ['side' => 'blue', 'source' => 'mount']);

        $ended = $this->command($owner, $event, 'end', [
            'winner' => 'white', 'method' => 'submission',
        ])->assertOk();

        $this->assertSame('white', $ended->json('state.winner'));
        $this->assertSame('submission', $ended->json('state.status'));
        // The points are untouched — the record says the loser was ahead.
        $this->assertSame(4, $ended->json('state.score.bluePoints'));
    }

    public function test_a_command_that_needs_a_match_is_refused_on_an_empty_mat(): void
    {
        [$owner, $event] = $this->scenario();

        $this->command($owner, $event, 'start')->assertStatus(422);
        $this->command($owner, $event, 'point', ['side' => 'blue', 'source' => 'sweep'])->assertStatus(422);

        $this->assertSame(0, MatchEvent::where('action', 'point')->count());
    }

    public function test_committing_writes_the_result_into_the_bracket(): void
    {
        [$owner, $event, $match] = $this->scenario();

        $this->command($owner, $event, 'load', ['match_id' => $match->id]);
        $this->command($owner, $event, 'start');
        $this->command($owner, $event, 'point', ['side' => 'white', 'source' => 'guard_pass']);
        $this->command($owner, $event, 'end');
        $this->command($owner, $event, 'commit')->assertOk();

        $match->refresh();

        $this->assertSame('b', $match->winner);
        $this->assertSame('3', (string) $match->b_score);
        $this->assertSame('done', $match->status);
    }

    /* ---------------- Authorization ---------------- */

    public function test_a_stranger_cannot_score_the_mat(): void
    {
        [, $event, $match] = $this->scenario();

        $stranger = $this->createUser();

        $this->command($stranger, $event, 'load', ['match_id' => $match->id])->assertForbidden();
        $this->command($stranger, $event, 'point', ['side' => 'blue', 'source' => 'mount'])->assertForbidden();

        $this->assertNull($this->state($event)->match_id);
        $this->assertSame(0, MatchEvent::count());
    }

    public function test_a_mat_this_event_does_not_run_is_refused(): void
    {
        [$owner, $event] = $this->scenario();

        $this->actingAs($owner)->postJson($this->endpoint($event), [
            'mat' => 'Mat 9',
            'command' => 'clear',
        ])->assertNotFound();
    }

    public function test_an_unknown_command_is_refused_by_validation(): void
    {
        [$owner, $event] = $this->scenario();

        $this->command($owner, $event, 'delete_everything')->assertStatus(422);
    }

    /* ---------------- The ledger's own reading ---------------- */

    public function test_the_ledger_replays_the_same_score_the_endpoint_returned(): void
    {
        [$owner, $event, $match] = $this->scenario();

        $this->command($owner, $event, 'load', ['match_id' => $match->id]);
        $this->command($owner, $event, 'point', ['side' => 'blue', 'source' => 'takedown']);
        $this->command($owner, $event, 'point', ['side' => 'blue', 'source' => 'guard_pass']);
        $this->command($owner, $event, 'advantage', ['side' => 'white']);

        $tally = app(Ledger::class)->tally($event, self::MAT, $match->id);

        $this->assertSame(5, $tally->bluePoints);
        $this->assertSame(0, $tally->whitePoints);
        $this->assertSame(1, $tally->whiteAdvantages);
        $this->assertSame('blue', $tally->leader());
    }

    public function test_every_ledger_row_names_the_operator_who_made_it(): void
    {
        [$owner, $event, $match] = $this->scenario();

        $this->command($owner, $event, 'load', ['match_id' => $match->id]);
        $this->command($owner, $event, 'point', ['side' => 'blue', 'source' => 'sweep']);

        $this->assertSame(
            [$owner->id],
            MatchEvent::whereNotNull('operator_id')->pluck('operator_id')->unique()->values()->all()
        );
    }

    /* ---------------- An ending has to name somebody ---------------- */

    public function test_a_naming_method_cannot_be_filed_without_a_winner(): void
    {
        [$owner, $event, $match] = $this->scenario();

        $this->command($owner, $event, 'load', ['match_id' => $match->id]);
        // Blue is ahead, so there IS a leader — this is not the level case. The
        // point is that a SUBMISSION is a claim about a person, and the person
        // was left out.
        $this->command($owner, $event, 'point', ['side' => 'blue', 'source' => 'mount']);

        $this->command($owner, $event, 'end', ['method' => 'submission'])
            ->assertStatus(422);

        // Nothing was filed, and in particular it was NOT quietly filed as the
        // points win the score would have produced.
        $state = $this->state($event);
        $this->assertFalse($state->isFinished());
        $this->assertNull($state->win_method);
    }

    public function test_the_same_ending_is_accepted_once_a_corner_is_named(): void
    {
        [$owner, $event, $match] = $this->scenario();

        $this->command($owner, $event, 'load', ['match_id' => $match->id]);
        $this->command($owner, $event, 'point', ['side' => 'blue', 'source' => 'mount']);

        // The corner BEHIND on points, which is the whole reason the method may
        // not be inferred from the score.
        $this->command($owner, $event, 'end', ['winner' => 'white', 'method' => 'submission'])
            ->assertOk();

        $state = $this->state($event);
        $this->assertSame('white', $state->winner);
        $this->assertSame('submission', $state->win_method);
    }

    public function test_ending_on_the_score_still_needs_no_winner(): void
    {
        [$owner, $event, $match] = $this->scenario();

        $this->command($owner, $event, 'load', ['match_id' => $match->id]);
        $this->command($owner, $event, 'point', ['side' => 'blue', 'source' => 'guard_pass']);

        $this->command($owner, $event, 'end', ['method' => 'points'])->assertOk();

        $this->assertSame('points', $this->state($event)->win_method);
    }

    /* ---------------- The console re-reads itself ---------------- */

    public function test_the_console_page_renders_and_declares_itself_a_console(): void
    {
        [$owner, $event] = $this->scenario();

        $response = $this->actingAs($owner)->get($this->endpoint($event));

        $response->assertOk();
        // The marker the live-link client reads to decide what to do with an
        // inbound message. Without it a console would be handed a wall board's
        // payload and draw nothing.
        //
        // Two renderers, one invariant: the Blade console declares it inline in
        // its runtime, the React island (features.react_scoreboard) declares it
        // in the props it is mounted with. The page must say it EITHER way —
        // asserting only the Blade spelling made this test a test of which
        // renderer is switched on, which is not what it is for.
        $this->assertTrue(
            str_contains($response->getContent(), "pinned: 'console'")
                || str_contains($response->getContent(), '&quot;pinned&quot;:&quot;console&quot;'),
            'The console page does not declare itself a console to the live link.',
        );
    }

    public function test_the_console_state_endpoint_returns_the_three_things_a_console_draws(): void
    {
        [$owner, $event, $match] = $this->scenario();

        $this->command($owner, $event, 'load', ['match_id' => $match->id]);
        $this->command($owner, $event, 'point', ['side' => 'blue', 'source' => 'sweep']);

        $response = $this->actingAs($owner)
            ->getJson($this->endpoint($event).'/state?mat='.urlencode(self::MAT));

        $response->assertOk()
            ->assertJsonPath('state.matchId', $match->id)
            ->assertJsonPath('state.score.bluePoints', 2)
            ->assertJsonStructure(['state', 'log', 'queue', 'stall']);
    }

    public function test_a_stranger_cannot_re_read_the_console(): void
    {
        [, $event] = $this->scenario();

        $this->actingAs($this->createUser())
            ->getJson($this->endpoint($event).'/state?mat='.urlencode(self::MAT))
            ->assertForbidden();
    }

    public function test_the_console_cannot_be_re_read_for_a_mat_this_event_does_not_run(): void
    {
        [$owner, $event] = $this->scenario();

        $this->actingAs($owner)
            ->getJson($this->endpoint($event).'/state?mat=Mat+9')
            ->assertNotFound();
    }

    /* ---------------- The console has a door ---------------- */

    public function test_the_management_console_offers_a_way_into_the_scoring_table(): void
    {
        [$owner, $event] = $this->scenario();

        $response = $this->actingAs($owner)->get(route('me.events.manage', $event->uuid));

        $response->assertOk();
        // The whole point: an organiser at a laptop can now REACH the console.
        // Before this it was only openable by pairing a tablet to it.
        $response->assertSee($this->endpoint($event), false);
    }

    public function test_the_scoring_door_is_shown_only_to_somebody_who_may_score(): void
    {
        [$owner, $event] = $this->scenario();

        // Appointed to check weigh-ins and nothing else: entitled to the manage
        // page (canOfficiate), not entitled to score it. Also a member of the
        // host club, because an internal event is not VISIBLE to an outsider and
        // the page would refuse them before authorisation was even reached.
        $official = $this->createUser();
        $official->memberClubs()->syncWithoutDetaching([$event->tenant_id => ['status' => 'active']]);

        \App\Models\EventOfficial::create([
            'event_id' => $event->id,
            'user_id' => $official->id,
            'role' => \App\Models\EventOfficial::ROLE_WEIGH_IN,
        ]);

        $this->assertTrue(app(\App\Events\Support\EventAccess::class)->canScore($event, $owner));
        $this->assertFalse(app(\App\Events\Support\EventAccess::class)->canScore($event, $official));

        $this->actingAs($official)->get(route('me.events.manage', $event->uuid))
            ->assertOk()
            ->assertDontSee($this->endpoint($event), false);
    }

    public function test_the_fleet_hands_out_no_console_for_a_sport_with_no_mat(): void
    {
        [, $event] = $this->scenario();

        $this->assertSame(url($this->endpoint($event)), \App\Scoreboard\Fleet::consoleUrl($event));

        $event->sport = 'swimming';

        $this->assertNull(\App\Scoreboard\Fleet::consoleUrl($event));
    }

    /* ---------------- The console's live link ---------------- */

    public function test_the_console_carries_a_socket_credential_when_realtime_is_on(): void
    {
        [$owner, $event] = $this->scenario();

        config([
            'realtime.enabled' => true,
            'realtime.broker.ws_url' => 'wss://broker.example/mqtt',
            'realtime.jwt.secret' => str_repeat('k', 32),
        ]);

        $response = $this->actingAs($owner)->get($this->endpoint($event));

        $response->assertOk();
        // The client is included, and it was handed the address this console
        // re-reads itself from. Together these are the whole of the fix: before
        // it, the console defined a CourtBoard nothing ever fed.
        $response->assertSee('console_url', false);
        // Slash-escaped, because the credential reaches the page through
        // @json() and that escapes '/' by default.
        $response->assertSee(
            str_replace('/', '\\/', '/bjj/control/'.$event->uuid.'/state'),
            false
        );
    }

    public function test_the_console_is_given_the_mats_own_topic_not_a_devices(): void
    {
        [, $event] = $this->scenario();

        config([
            'realtime.enabled' => true,
            'realtime.broker.ws_url' => 'wss://broker.example/mqtt',
            'realtime.jwt.secret' => str_repeat('k', 32),
        ]);

        $link = \App\Scoreboard\Sports\BrazilianJiuJitsu\HallScreen\ScreenChannel::consoleCredentials($event, self::MAT);

        $this->assertNotNull($link);
        $this->assertSame(
            \App\Scoreboard\Sports\BrazilianJiuJitsu\HallScreen\ScreenChannel::matTopic($event, self::MAT),
            $link['topic']
        );

        // Subscribe-only, on that one topic, and forbidden to publish anywhere.
        // A console can already write through its own authorised endpoints; the
        // socket only lets it be told things.
        $this->assertSame(
            [
                ['permission' => 'allow', 'action' => 'subscribe', 'topic' => $link['topic']],
                ['permission' => 'deny', 'action' => 'publish', 'topic' => '#'],
            ],
            json_decode(base64_decode(strtr(explode('.', $link['password'])[1], '-_', '+/')), true)['acl']
        );
    }

    public function test_two_mats_never_share_a_topic(): void
    {
        [, $event] = $this->scenario();

        $this->assertNotSame(
            \App\Scoreboard\Sports\BrazilianJiuJitsu\HallScreen\ScreenChannel::matTopic($event, 'Mat 1'),
            \App\Scoreboard\Sports\BrazilianJiuJitsu\HallScreen\ScreenChannel::matTopic($event, 'Mat 2')
        );
    }
}
