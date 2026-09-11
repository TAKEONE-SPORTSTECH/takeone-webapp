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

        /*
         * STARTED. Filing a result — `end`, `decision`, `commit` — is refused
         * until the competition day has been started
         * (Scoring::NEEDS_EVENT_STARTED), so a scenario that tests those has to
         * be a competition that is under way. The unstarted case has its own
         * test below.
         *
         * Set here rather than in create(): `started_at` is deliberately NOT
         * mass-assignable, because starting a competition is a decision with a
         * button, not a field on a form.
         *
         * Added 2026-09-10 with that guard: a mat is set up and rehearsed days
         * before an event, and a rehearsal must not be able to advance a
         * bracket. One event had 509 officiating rows against 52 of its bouts a
         * week before its date.
         */
        $event->started_at = now();
        $event->save();

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

    /** The score as the ledger replays it, independent of any response body. */
    private function tally(ClubEvent $event)
    {
        $state = $this->state($event);

        return app(\App\Scoreboard\Sports\BrazilianJiuJitsu\Mat\Ledger::class)
            ->tally($event, self::MAT, $state->match_id);
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

        // A guard pass is three because the RULES say so. An amount sent
        // alongside a named action does not get a say — the source is priced
        // first and wins, even when the amount is one the server would
        // otherwise accept on its own.
        $response = $this->command($owner, $event, 'point', [
            'side' => 'blue', 'source' => 'guard_pass', 'value' => 2,
        ])->assertOk();

        $this->assertSame(3, $response->json('state.score.bluePoints'));

        // And an amount outside the server's own list is refused outright
        // rather than quietly dropped, valid action beside it or not.
        $this->command($owner, $event, 'point', [
            'side' => 'blue', 'source' => 'guard_pass', 'value' => 99,
        ])->assertStatus(422);

        $this->assertSame(3, $this->tally($event)->bluePoints);
    }

    /* ---------------- The grid gives points by AMOUNT, and takes them back -- */

    public function test_a_point_can_be_given_by_amount_with_no_action_named(): void
    {
        [$owner, $event, $match] = $this->scenario();

        $this->command($owner, $event, 'load', ['match_id' => $match->id])->assertOk();

        $response = $this->command($owner, $event, 'point', ['side' => 'blue', 'value' => 4])->assertOk();

        $this->assertSame(4, $response->json('state.score.bluePoints'));

        // The row carries no action, and that is the honest record of what the
        // table pressed — a four, not a mount it never named.
        $row = MatchEvent::where('action', 'point')->latest('sequence')->first();
        $this->assertNull($row->source);
        $this->assertSame(4, (int) $row->value);
    }

    public function test_an_amount_the_server_does_not_price_is_refused(): void
    {
        [$owner, $event, $match] = $this->scenario();

        $this->command($owner, $event, 'load', ['match_id' => $match->id]);

        foreach ([1, 5, 99, 0, -2] as $amount) {
            $this->command($owner, $event, 'point', ['side' => 'blue', 'value' => $amount])
                ->assertStatus(422);
        }

        $this->assertSame(0, MatchEvent::where('action', 'point')->count());
    }

    public function test_a_deduction_reverses_the_matching_score_and_both_rows_survive(): void
    {
        [$owner, $event, $match] = $this->scenario();

        $this->command($owner, $event, 'load', ['match_id' => $match->id]);
        $this->command($owner, $event, 'point', ['side' => 'blue', 'value' => 2])->assertOk();
        $this->command($owner, $event, 'point', ['side' => 'blue', 'value' => 3])->assertOk();

        $response = $this->command($owner, $event, 'deduct', ['side' => 'blue', 'value' => 2])->assertOk();

        // The two came off; the three did not.
        $this->assertSame(3, $response->json('state.score.bluePoints'));

        // Nothing was deleted: the original point is still there, and so is the
        // row that took it back.
        $this->assertSame(2, MatchEvent::where('action', 'point')->count());
        $this->assertSame(1, MatchEvent::where('action', 'reverse')->count());
        $this->assertSame(0, MatchEvent::where('action', 'correction')->count());
    }

    public function test_a_deduction_with_nothing_to_reverse_records_a_correction(): void
    {
        [$owner, $event, $match] = $this->scenario();

        $this->command($owner, $event, 'load', ['match_id' => $match->id]);
        $this->command($owner, $event, 'point', ['side' => 'blue', 'value' => 4])->assertOk();

        // No two-point score has ever been given to this corner, so there is
        // nothing to reverse — the table is correcting a total, and the row
        // says exactly that.
        $response = $this->command($owner, $event, 'deduct', ['side' => 'blue', 'value' => 2])->assertOk();

        $this->assertSame(2, $response->json('state.score.bluePoints'));
        $this->assertSame(1, MatchEvent::where('action', 'correction')->count());
    }

    public function test_a_corner_can_never_owe_points(): void
    {
        [$owner, $event, $match] = $this->scenario();

        $this->command($owner, $event, 'load', ['match_id' => $match->id]);
        $this->command($owner, $event, 'point', ['side' => 'blue', 'value' => 2])->assertOk();

        // Two corrections against a two-point score: the first takes it back,
        // the second has nothing left and is recorded anyway.
        $this->command($owner, $event, 'deduct', ['side' => 'blue', 'value' => 2])->assertOk();
        $response = $this->command($owner, $event, 'deduct', ['side' => 'blue', 'value' => 4])->assertOk();

        $this->assertSame(0, $response->json('state.score.bluePoints'));
    }

    public function test_a_deduction_does_not_shout_on_the_wall(): void
    {
        [$owner, $event, $match] = $this->scenario();

        $this->command($owner, $event, 'load', ['match_id' => $match->id]);
        $this->command($owner, $event, 'point', ['side' => 'blue', 'value' => 3])->assertOk();

        $response = $this->command($owner, $event, 'deduct', ['side' => 'blue', 'value' => 3])->assertOk();

        // A correction is housekeeping, not a callout. The hall is shown
        // scores, never the table taking one back.
        $this->assertNull($response->json('state.lastEvent'));
    }

    public function test_an_advantage_can_be_taken_back_and_the_row_survives(): void
    {
        [$owner, $event, $match] = $this->scenario();

        $this->command($owner, $event, 'load', ['match_id' => $match->id]);
        $this->command($owner, $event, 'advantage', ['side' => 'blue'])->assertOk();
        $this->command($owner, $event, 'advantage', ['side' => 'blue'])->assertOk();

        $response = $this->command($owner, $event, 'deduct', ['side' => 'blue', 'ladder' => 'advantage'])->assertOk();

        $this->assertSame(1, $response->json('state.score.blueAdvantages'));
        $this->assertSame(2, MatchEvent::where('action', 'advantage')->count());
        $this->assertSame(1, MatchEvent::where('action', 'reverse')->count());
    }

    public function test_a_penalty_can_be_taken_back_which_is_what_lowers_the_dq_ladder(): void
    {
        [$owner, $event, $match] = $this->scenario();

        $this->command($owner, $event, 'load', ['match_id' => $match->id]);
        $this->command($owner, $event, 'penalty', ['side' => 'blue', 'source' => 'fleeing'])->assertOk();

        $response = $this->command($owner, $event, 'deduct', ['side' => 'blue', 'ladder' => 'penalty'])->assertOk();

        $this->assertSame(0, $response->json('state.score.bluePenalties'));
        $this->assertSame(0, $this->tally($event)->bluePenalties);
    }

    public function test_an_empty_ladder_is_refused_rather_than_going_negative(): void
    {
        [$owner, $event, $match] = $this->scenario();

        $this->command($owner, $event, 'load', ['match_id' => $match->id]);

        // Nothing was ever given, so there is nothing to take back — and a
        // ladder must never be lowered by a row pointing at no act. The penalty
        // ladder ends matches; an untraceable count on it is a disqualification
        // that can be argued away.
        $this->command($owner, $event, 'deduct', ['side' => 'blue', 'ladder' => 'advantage'])->assertStatus(422);
        $this->command($owner, $event, 'deduct', ['side' => 'white', 'ladder' => 'penalty'])->assertStatus(422);

        $this->assertSame(0, MatchEvent::where('action', 'correction')->count());
        $this->assertSame(0, MatchEvent::where('action', 'reverse')->count());
    }

    public function test_a_deduction_only_reaches_the_corner_it_names(): void
    {
        [$owner, $event, $match] = $this->scenario();

        $this->command($owner, $event, 'load', ['match_id' => $match->id]);
        $this->command($owner, $event, 'point', ['side' => 'blue', 'value' => 2])->assertOk();
        $this->command($owner, $event, 'advantage', ['side' => 'blue'])->assertOk();

        // White has nothing; blue must be untouched by a deduction aimed at
        // white, and a correction row on white cannot lower blue's total.
        $this->command($owner, $event, 'deduct', ['side' => 'white', 'ladder' => 'advantage'])->assertStatus(422);
        $this->command($owner, $event, 'deduct', ['side' => 'white', 'value' => 2])->assertOk();

        $tally = $this->tally($event);
        // 2 for the takedown + 1 for blue's own advantage, untouched by either
        // deduction aimed at white.
        $this->assertSame(3, $tally->bluePoints);
        $this->assertSame(1, $tally->blueAdvantages);
        // White's own correction floors at zero and never reaches blue.
        $this->assertSame(0, $tally->whitePoints);
    }

    public function test_a_deduction_is_held_to_the_same_closed_list_of_amounts(): void
    {
        [$owner, $event, $match] = $this->scenario();

        $this->command($owner, $event, 'load', ['match_id' => $match->id]);
        $this->command($owner, $event, 'point', ['side' => 'blue', 'value' => 2])->assertOk();

        $this->command($owner, $event, 'deduct', ['side' => 'blue', 'value' => 7])->assertStatus(422);
        $this->command($owner, $event, 'deduct', ['value' => 2])->assertStatus(422);
        $this->command($owner, $event, 'deduct', ['side' => 'blue', 'ladder' => 'trophies'])->assertStatus(422);

        $this->assertSame(2, $this->tally($event)->bluePoints);
    }

    public function test_an_unknown_scoring_action_is_refused_and_nothing_is_recorded(): void
    {
        [$owner, $event, $match] = $this->scenario();

        $this->command($owner, $event, 'load', ['match_id' => $match->id]);

        $this->command($owner, $event, 'point', ['side' => 'blue', 'source' => 'flying_armbar'])
            ->assertStatus(422);

        $this->assertSame(0, MatchEvent::where('action', 'point')->count());
    }

    public function test_an_advantage_scores_for_its_own_corner_and_a_penalty_for_the_other(): void
    {
        [$owner, $event, $match] = $this->scenario();

        $this->command($owner, $event, 'load', ['match_id' => $match->id]);
        $this->command($owner, $event, 'point', ['side' => 'blue', 'source' => 'takedown']);
        $this->command($owner, $event, 'advantage', ['side' => 'blue']);
        $response = $this->command($owner, $event, 'penalty', ['side' => 'blue', 'source' => 'stalling']);

        $score = $response->json('state.score');

        // 2 for the takedown + 1 for blue's own advantage. The penalty does not
        // come off blue — it goes ON to white, so a penalty against a corner
        // sitting on zero still shows somewhere.
        $this->assertSame(3, $score['bluePoints']);
        $this->assertSame(1, $score['whitePoints']);

        // And the ladders are still their own numbers, not folded away.
        $this->assertSame(1, $score['blueAdvantages']);
        $this->assertSame(1, $score['bluePenalties']);
        $this->assertSame(0, $score['whiteAdvantages']);
        $this->assertSame(0, $score['whitePenalties']);
    }

    /* ---------------- An undo is itself undoable ---------------- */

    public function test_an_undo_can_itself_be_undone_and_the_points_come_back(): void
    {
        [$owner, $event, $match] = $this->scenario();

        $this->command($owner, $event, 'load', ['match_id' => $match->id]);
        $this->command($owner, $event, 'point', ['side' => 'blue', 'value' => 2])->assertOk();

        $point = MatchEvent::where('action', 'point')->latest('sequence')->first();

        // Took it back…
        $this->command($owner, $event, 'reverse', ['ledger_id' => $point->id, 'reason' => 'mis-tap'])->assertOk();
        $this->assertSame(0, $this->tally($event)->bluePoints);

        $undo = MatchEvent::where('action', 'reverse')->latest('sequence')->first();

        // …and changed their mind. The score must come BACK: a one-pass sweep
        // over `reverses_id` would drop the original for ever and leave the
        // board silently wrong, which is the whole reason the replay resolves
        // the chain.
        $this->command($owner, $event, 'reverse', ['ledger_id' => $undo->id, 'reason' => 'the point was good'])
            ->assertOk();

        $this->assertSame(2, $this->tally($event)->bluePoints);

        // Nothing was deleted on the way: three rows, all of them still there.
        $this->assertSame(1, MatchEvent::where('action', 'point')->count());
        $this->assertSame(2, MatchEvent::where('action', 'reverse')->count());
    }

    public function test_a_chain_of_undos_resolves_to_the_right_score_at_any_depth(): void
    {
        [$owner, $event, $match] = $this->scenario();

        $this->command($owner, $event, 'load', ['match_id' => $match->id]);
        $this->command($owner, $event, 'point', ['side' => 'blue', 'value' => 4])->assertOk();

        $target = MatchEvent::where('action', 'point')->latest('sequence')->first()->id;

        // Undo, redo, undo, redo — the score has to alternate all the way down.
        foreach ([0, 4, 0, 4] as $i => $expected) {
            $this->command($owner, $event, 'reverse', ['ledger_id' => $target, 'reason' => 'pass '.$i])
                ->assertOk();

            $this->assertSame($expected, $this->tally($event)->bluePoints, 'after undo #'.($i + 1));

            $target = MatchEvent::where('action', 'reverse')->latest('sequence')->first()->id;
        }
    }

    public function test_the_same_entry_cannot_be_undone_twice(): void
    {
        [$owner, $event, $match] = $this->scenario();

        $this->command($owner, $event, 'load', ['match_id' => $match->id]);
        $this->command($owner, $event, 'point', ['side' => 'blue', 'value' => 3])->assertOk();

        $point = MatchEvent::where('action', 'point')->latest('sequence')->first();

        $this->command($owner, $event, 'reverse', ['ledger_id' => $point->id, 'reason' => 'first'])->assertOk();

        // A second undo of the SAME row would subtract twice from a score
        // nobody could trace back to an act.
        $this->command($owner, $event, 'reverse', ['ledger_id' => $point->id, 'reason' => 'again'])
            ->assertStatus(422);

        $this->assertSame(0, $this->tally($event)->bluePoints);
        $this->assertSame(1, MatchEvent::where('action', 'reverse')->count());
    }

    public function test_the_log_strikes_through_only_what_no_longer_stands(): void
    {
        [$owner, $event, $match] = $this->scenario();

        $this->command($owner, $event, 'load', ['match_id' => $match->id]);
        $this->command($owner, $event, 'point', ['side' => 'blue', 'value' => 2])->assertOk();

        $point = MatchEvent::where('action', 'point')->latest('sequence')->first();
        $this->command($owner, $event, 'reverse', ['ledger_id' => $point->id, 'reason' => 'mis-tap']);
        $undo = MatchEvent::where('action', 'reverse')->latest('sequence')->first();
        $response = $this->command($owner, $event, 'reverse', ['ledger_id' => $undo->id, 'reason' => 'restore']);

        $log = collect($response->json('log'))->keyBy('id');

        // The point stands again, the first undo does not, and the row that
        // restored it is the only live reversal.
        $this->assertFalse($log[$point->id]['reversed']);
        $this->assertTrue($log[$undo->id]['reversed']);
    }

    /* ---------------- Ending at the top of a ladder ---------------- */

    public function test_there_is_no_advantage_limit_unless_a_mat_asks_for_one(): void
    {
        [$owner, $event, $match] = $this->scenario();

        $this->command($owner, $event, 'load', ['match_id' => $match->id]);
        $this->command($owner, $event, 'start');

        // Jiu-jitsu as it is normally run has no such rule, so the default must
        // not end a bout — six advantages and the match is still live.
        for ($i = 0; $i < 6; $i++) {
            $this->command($owner, $event, 'advantage', ['side' => 'blue'])->assertOk();
        }

        $state = $this->state($event);
        $this->assertFalse($state->isFinished());
        $this->assertNull($state->win_method);
    }

    public function test_the_advantage_limit_ends_the_match_for_the_corner_that_reached_it(): void
    {
        [$owner, $event, $match] = $this->scenario();

        $this->command($owner, $event, 'load', ['match_id' => $match->id]);
        $this->command($owner, $event, 'rules', ['advantage_limit' => 3])->assertOk();
        $this->command($owner, $event, 'start');

        $this->command($owner, $event, 'advantage', ['side' => 'blue'])->assertOk();
        $this->command($owner, $event, 'advantage', ['side' => 'white'])->assertOk();
        $response = $this->command($owner, $event, 'advantage', ['side' => 'blue'])->assertOk();

        // Two is not three: still running.
        $this->assertFalse($this->state($event)->isFinished());

        $response = $this->command($owner, $event, 'advantage', ['side' => 'blue'])->assertOk();

        // The mirror image of the penalty ladder: reaching the top of THIS one
        // ends it in that corner's favour.
        $state = $this->state($event);
        $this->assertTrue($state->isFinished());
        $this->assertSame('blue', $state->winner);
        $this->assertSame('advantages', $state->win_method);

        // Which is what puts the Match result card up on the console.
        $this->assertTrue($response->json('state.finished'));
        $this->assertSame('blue', $response->json('state.winner'));
        $this->assertSame('advantages', $response->json('state.winMethod'));
    }

    public function test_an_advantage_that_was_taken_back_does_not_count_towards_the_limit(): void
    {
        [$owner, $event, $match] = $this->scenario();

        $this->command($owner, $event, 'load', ['match_id' => $match->id]);
        $this->command($owner, $event, 'rules', ['advantage_limit' => 2])->assertOk();
        $this->command($owner, $event, 'start');

        $this->command($owner, $event, 'advantage', ['side' => 'blue'])->assertOk();
        $this->command($owner, $event, 'deduct', ['side' => 'blue', 'ladder' => 'advantage'])->assertOk();
        $this->command($owner, $event, 'advantage', ['side' => 'blue'])->assertOk();

        // Two rows, one reversed — the cap counts the REPLAY, not the presses.
        $this->assertFalse($this->state($event)->isFinished());
        $this->assertSame(1, $this->tally($event)->blueAdvantages);
    }

    public function test_the_advantage_limit_is_held_to_the_servers_own_range(): void
    {
        [$owner, $event, $match] = $this->scenario();

        $this->command($owner, $event, 'load', ['match_id' => $match->id]);

        $this->command($owner, $event, 'rules', ['advantage_limit' => 99])->assertStatus(422);
        $this->command($owner, $event, 'rules', ['advantage_limit' => -1])->assertStatus(422);

        // Zero is not out of range — it is how a mat turns the cap back OFF.
        $this->command($owner, $event, 'rules', ['advantage_limit' => 0])->assertOk();
        $this->assertSame(0, $this->state($event)->rule('advantage_limit'));
    }

    public function test_the_penalty_limit_still_ends_the_match_against_the_offender(): void
    {
        [$owner, $event, $match] = $this->scenario();

        $this->command($owner, $event, 'load', ['match_id' => $match->id]);
        $this->command($owner, $event, 'rules', ['penalty_limit' => 2, 'penalty_warn_at' => 1])->assertOk();
        $this->command($owner, $event, 'start');

        $this->command($owner, $event, 'penalty', ['side' => 'blue'])->assertOk();
        $response = $this->command($owner, $event, 'penalty', ['side' => 'blue'])->assertOk();

        // The two ladders end a match in OPPOSITE directions, and both put the
        // same Match result card up.
        $state = $this->state($event);
        $this->assertTrue($state->isFinished());
        $this->assertSame('white', $state->winner);
        $this->assertSame('dq', $state->win_method);
        $this->assertTrue($response->json('state.finished'));
    }

    /* ---------------- The double stalling count ---------------- */

    public function test_a_double_stall_runs_as_one_count_against_both_corners(): void
    {
        [$owner, $event, $match] = $this->scenario();

        $this->command($owner, $event, 'load', ['match_id' => $match->id]);

        // Against one corner first, then against both: the mat holds ONE count,
        // so starting the double one REPLACES it rather than running a second
        // clock beside it.
        $this->command($owner, $event, 'stall', ['side' => 'blue', 'phase' => 'start'])->assertOk();
        $response = $this->command($owner, $event, 'stall', ['side' => 'both', 'phase' => 'start'])->assertOk();

        $this->assertSame('both', $response->json('stall.side'));
        $this->assertSame('both', $this->state($event)->stall_side);
    }

    public function test_a_running_stalling_count_reaches_the_wall_board(): void
    {
        [$owner, $event, $match] = $this->scenario();

        $this->command($owner, $event, 'load', ['match_id' => $match->id]);
        $this->command($owner, $event, 'rules', ['stall_seconds' => 8])->assertOk();

        // Nothing running: the board is told so explicitly rather than left to
        // infer it from a missing key.
        $this->assertNull($this->state($event)->present()['stall']);

        $response = $this->command($owner, $event, 'stall', ['side' => 'white', 'phase' => 'start'])->assertOk();

        // ⚠️ This used to be deliberately absent from present() — the wall was
        // never told a penalty might be coming. Shown on the board at the
        // organiser's instruction, 2026-09-12.
        $stall = $response->json('state.stall');
        $this->assertSame('white', $stall['side']);

        // A REMAINDER, never a deadline: a wall screen's clock is frequently
        // wrong, and it counts down from arrival like the bout clock does.
        // The window is loose on the low side on purpose — this asserts the
        // SHAPE of the value, and a slow test run must not fail a scoreboard.
        $this->assertGreaterThan(5.0, $stall['seconds']);
        $this->assertLessThanOrEqual(8.0, $stall['seconds']);

        // A double stall names both corners, and the board lights both.
        $response = $this->command($owner, $event, 'stall', ['side' => 'both', 'phase' => 'start'])->assertOk();
        $this->assertSame('both', $response->json('state.stall.side'));

        // And it clears the moment the count is spent.
        $response = $this->command($owner, $event, 'stall', ['phase' => 'cancel'])->assertOk();
        $this->assertNull($response->json('state.stall'));
    }

    public function test_applying_a_double_stall_penalises_both_corners(): void
    {
        [$owner, $event, $match] = $this->scenario();

        $this->command($owner, $event, 'load', ['match_id' => $match->id]);
        $this->command($owner, $event, 'stall', ['side' => 'both', 'phase' => 'start'])->assertOk();

        $response = $this->command($owner, $event, 'stall', ['phase' => 'apply'])->assertOk();

        // One penalty each, both recorded as ordinary stalling penalties — the
        // same two rows the referee would have made by hand.
        $score = $response->json('state.score');
        $this->assertSame(1, $score['bluePenalties']);
        $this->assertSame(1, $score['whitePenalties']);
        $this->assertSame(2, MatchEvent::where('action', 'penalty')->count());
        $this->assertSame(
            ['stalling', 'stalling'],
            MatchEvent::where('action', 'penalty')->orderBy('sequence')->pluck('source')->all()
        );

        // Each penalty gives the OTHER man a point, so a double stall leaves
        // the score exactly where it was — only the ladders moved.
        $this->assertSame(1, $score['bluePoints']);
        $this->assertSame(1, $score['whitePoints']);

        // And the count is spent.
        $this->assertNull($this->state($event)->stall_side);
        $this->assertNull($response->json('stall'));
    }

    public function test_a_double_stall_has_no_corner_to_award_points_to(): void
    {
        [$owner, $event, $match] = $this->scenario();

        $this->command($owner, $event, 'load', ['match_id' => $match->id]);
        $this->command($owner, $event, 'stall', ['side' => 'both', 'phase' => 'start'])->assertOk();

        $this->command($owner, $event, 'stall', ['phase' => 'award', 'points' => 2])
            ->assertStatus(422);

        // Refused without spending the count — the referee can still give the
        // two penalties, or dismiss it.
        $this->assertSame('both', $this->state($event)->stall_side);
        $this->assertSame(0, MatchEvent::where('action', 'point')->count());
    }

    public function test_a_double_stall_can_be_dismissed_like_any_other_count(): void
    {
        [$owner, $event, $match] = $this->scenario();

        $this->command($owner, $event, 'load', ['match_id' => $match->id]);
        $this->command($owner, $event, 'stall', ['side' => 'both', 'phase' => 'start'])->assertOk();
        $this->command($owner, $event, 'stall', ['phase' => 'cancel'])->assertOk();

        $this->assertNull($this->state($event)->stall_side);
        $this->assertSame(0, MatchEvent::where('action', 'penalty')->count());
    }

    public function test_only_the_stalling_count_may_name_both_corners(): void
    {
        [$owner, $event, $match] = $this->scenario();

        $this->command($owner, $event, 'load', ['match_id' => $match->id]);

        // Widening the endpoint's `side` vocabulary for the stalling count must
        // not let anything else be given to two people at once.
        $this->command($owner, $event, 'point', ['side' => 'both', 'value' => 2])->assertStatus(422);
        $this->command($owner, $event, 'advantage', ['side' => 'both'])->assertStatus(422);
        $this->command($owner, $event, 'penalty', ['side' => 'both'])->assertStatus(422);
        $this->command($owner, $event, 'deduct', ['side' => 'both', 'value' => 2])->assertStatus(422);

        $this->assertSame(0, MatchEvent::whereIn('action', ['point', 'advantage', 'penalty'])->count());
    }

    public function test_a_penalty_lands_without_being_told_what_kind_it_was(): void
    {
        [$owner, $event, $match] = $this->scenario();

        $this->command($owner, $event, 'load', ['match_id' => $match->id]);

        // No `source`: the console stopped asking on 2026-09-12, because a
        // question between the referee's decision and the score is asked at the
        // worst possible moment.
        $response = $this->command($owner, $event, 'penalty', ['side' => 'blue'])->assertOk();

        $this->assertSame(1, $response->json('state.score.bluePenalties'));
        $this->assertSame(1, $response->json('state.score.whitePoints'));

        // Recorded as `other` — the truthful record of a penalty given with no
        // reason stated, and still a real row that can be taken back.
        $row = MatchEvent::where('action', 'penalty')->latest('sequence')->first();
        $this->assertSame('other', $row->source);

        // A reason is still ACCEPTED, because the referee's stalling count
        // applies one and names it.
        $this->command($owner, $event, 'penalty', ['side' => 'blue', 'source' => 'stalling'])->assertOk();
        $this->assertSame(
            'stalling',
            MatchEvent::where('action', 'penalty')->latest('sequence')->first()->source
        );
    }

    public function test_taking_back_a_ladder_entry_takes_its_point_with_it(): void
    {
        [$owner, $event, $match] = $this->scenario();

        $this->command($owner, $event, 'load', ['match_id' => $match->id]);
        $this->command($owner, $event, 'advantage', ['side' => 'blue'])->assertOk();
        $this->command($owner, $event, 'penalty', ['side' => 'blue', 'source' => 'fleeing'])->assertOk();

        // blue: 1 point from its own advantage. white: 1 point from blue's penalty.
        $score = $this->tally($event);
        $this->assertSame(1, $score->bluePoints);
        $this->assertSame(1, $score->whitePoints);

        // A reversal removes the act, so BOTH of its effects go at once — there
        // is no second row to find and nothing to keep in step by hand.
        $this->command($owner, $event, 'deduct', ['side' => 'blue', 'ladder' => 'advantage'])->assertOk();
        $this->command($owner, $event, 'deduct', ['side' => 'blue', 'ladder' => 'penalty'])->assertOk();

        $score = $this->tally($event);
        $this->assertSame(0, $score->bluePoints);
        $this->assertSame(0, $score->whitePoints);
        $this->assertSame(0, $score->blueAdvantages);
        $this->assertSame(0, $score->bluePenalties);
    }

    public function test_an_advantage_breaks_a_tie_on_points_and_a_penalty_breaks_a_tie_on_advantages(): void
    {
        [$owner, $event, $match] = $this->scenario();

        $this->command($owner, $event, 'load', ['match_id' => $match->id]);
        $this->command($owner, $event, 'point', ['side' => 'blue', 'source' => 'sweep']);
        $this->command($owner, $event, 'point', ['side' => 'white', 'source' => 'takedown']);

        // 2–2. An advantage now moves the score as well as the ladder, so it
        // does not leave a TIE for itself to break — white goes ahead 3–2 and
        // it is the points that say so. The ladder is still kept, and is still
        // what decides a score that ends up level; it just no longer decides
        // this particular kind of level.
        $response = $this->command($owner, $event, 'advantage', ['side' => 'white']);
        $this->assertSame('white', $response->json('state.score.leader'));
        $this->assertSame('points', $response->json('state.score.decidedBy'));

        // Blue matches it: 3–3 on points and 1–1 on advantages. NOW the ladders
        // are the only thing left, and a penalty on white both puts blue a
        // point up and puts white a penalty down.
        $this->command($owner, $event, 'advantage', ['side' => 'blue']);
        $response = $this->command($owner, $event, 'penalty', ['side' => 'white', 'source' => 'fleeing']);

        $this->assertSame('blue', $response->json('state.score.leader'));
        $this->assertSame('points', $response->json('state.score.decidedBy'));
        $this->assertSame(4, $response->json('state.score.bluePoints'));
        $this->assertSame(3, $response->json('state.score.whitePoints'));

        // The advantage ladder still breaks a genuine tie on points: reversing
        // white's penalty puts the score back to 3–3, and blue took its
        // advantage second, so the two are level there too — leaving the
        // penalty count, which is now 0–0, and therefore no winner at all.
        $this->command($owner, $event, 'deduct', ['side' => 'white', 'ladder' => 'penalty'])->assertOk();
        $level = $this->tally($event);
        $this->assertSame(3, $level->bluePoints);
        $this->assertSame(3, $level->whitePoints);
        $this->assertNull($level->leader());
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
        // White's advantage is worth a point to white as well as a place on
        // its ladder.
        $this->assertSame(1, $tally->whitePoints);
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

    public function test_the_stalling_count_is_sent_as_a_remainder_not_only_a_deadline(): void
    {
        [$owner, $event, $match] = $this->scenario();

        $this->command($owner, $event, 'load', ['match_id' => $match->id]);
        $this->command($owner, $event, 'rules', ['stall_seconds' => 5])->assertOk();

        $response = $this->command($owner, $event, 'stall', ['side' => 'blue', 'phase' => 'start'])->assertOk();

        // The console counts down from THIS, measured against the moment the
        // payload arrived — never by subtracting `until` from its own clock,
        // which is a subtraction between two machines that do not agree. A
        // table drifting a second ahead of the server used to open a five
        // second count at three and blip twice on the way in.
        $this->assertSame('blue', $response->json('stall.side'));
        $seconds = $response->json('stall.seconds');
        $this->assertIsNumeric($seconds);
        $this->assertGreaterThan(2.0, $seconds);
        $this->assertLessThanOrEqual(5.0, $seconds);

        // The deadline stays for anything still reading the absolute form.
        $this->assertNotNull($response->json('stall.until'));
    }

    public function test_the_console_renders_a_take_back_beside_every_way_of_giving(): void
    {
        [$owner, $event] = $this->scenario();

        $response = $this->actingAs($owner)->get($this->endpoint($event));
        $response->assertOk();

        // The points grid is built by the runtime, so what the document carries
        // is the server's own list of amounts — not six hard-coded buttons.
        $response->assertSee('POINT_VALUES', false);
        $response->assertSee('[2,3,4]', false);

        // Both ladders are split, in both corners.
        foreach (['blue', 'white'] as $side) {
            $response->assertSee('data-cmd="deduct" data-side="'.$side.'" data-ladder="advantage"', false);
            $response->assertSee('data-cmd="deduct" data-side="'.$side.'" data-ladder="penalty"', false);
        }
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

    public function test_a_rehearsal_cannot_file_a_result_before_the_day_is_started(): void
    {
        [$owner, $event, $match] = $this->scenario();

        // The same competition, not yet under way.
        $event->started_at = null;
        $event->save();

        $this->actingAs($owner);

        // Rehearsal: all of it still works. This is the point of the guard —
        // an official learns the instrument on the real mat, with the real
        // bouts, and nothing they press can reach the record.
        foreach ([
            ['command' => 'load', 'match_id' => $match->id],
            ['command' => 'start'],
            ['command' => 'point', 'side' => 'blue', 'source' => 'guard_pass'],
            ['command' => 'advantage', 'side' => 'white'],
            ['command' => 'penalty', 'side' => 'blue', 'source' => 'stalling'],
            ['command' => 'reset'],
        ] as $payload) {
            $this->postJson(route('bjj-scoreboard.command', ['event' => $event->uuid]),
                $payload + ['mat' => self::MAT])
                ->assertOk();
        }

        // Filing: refused, all three ways.
        foreach ([
            ['command' => 'end', 'winner' => 'blue', 'method' => 'submission'],
            ['command' => 'decision', 'winner' => 'blue'],
            ['command' => 'commit'],
        ] as $payload) {
            $this->postJson(route('bjj-scoreboard.command', ['event' => $event->uuid]),
                $payload + ['mat' => self::MAT])
                ->assertStatus(422);
        }

        // And the bracket is untouched by any of it.
        $this->assertNull($match->fresh()->winner);
        $this->assertNotSame('done', $match->fresh()->status);
    }
}
