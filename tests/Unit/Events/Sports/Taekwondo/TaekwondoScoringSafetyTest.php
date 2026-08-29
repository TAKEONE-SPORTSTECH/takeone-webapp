<?php

namespace Tests\Unit\Events\Sports\Taekwondo;

use App\Events\Sports\Taekwondo\Tournament\Scoreboard\MatState;
use App\Events\Sports\Taekwondo\Tournament\Scoreboard\Scoring;
use PHPUnit\Framework\TestCase;

/**
 * PHASE 0 SAFETY BASELINE — Taekwondo tournament scoring.
 *
 * These tests LOCK DOWN CURRENT BEHAVIOUR. They are characterisation tests, not
 * a rulebook: every expectation below was read out of the repository, never out
 * of the World Taekwondo competition rules. If a change makes one of these fail,
 * that is a deliberate decision for a human, not a test to be edited quietly.
 *
 * Production sources under test:
 *   · app/Events/Sports/Taekwondo/Tournament/Scoreboard/MatState.php
 *   · app/Events/Sports/Taekwondo/Tournament/Scoreboard/Scoring.php  (constants only)
 *
 * Deliberately NOT touched here: Scoring::apply(). It loads a ClubEvent from the
 * database, reads and writes the cache, and every rule method beneath it
 * (score/gamjeom/checkRoundEnd/awardRound/golden/undo) is `private`. It cannot be
 * exercised without booting Laravel and a database.
 * See docs/PHASE_0_SCORING_SAFETY_REPORT.md → "Testability Gaps".
 *
 * This case extends PHPUnit's TestCase directly (as tests/Unit/ExampleTest.php
 * does) so that no framework, no database and no cache is ever touched.
 */
class TaekwondoScoringSafetyTest extends TestCase
{
    /* ---------------------------------------------------------------
     * Command vocabulary — the endpoint's allow-list
     * --------------------------------------------------------------- */

    public function test_the_accepted_command_vocabulary_is_stable(): void
    {
        // Locks App\Events\Sports\Taekwondo\Tournament\Scoreboard\Scoring::COMMANDS.
        // The scoreboard endpoint validates 'command' => in:<this list>, so it is
        // the authorization surface for what an operator may ask a mat to do.
        $commands = Scoring::COMMANDS;

        $this->assertNotEmpty($commands);
        $this->assertSame(array_values(array_unique($commands)), $commands, 'COMMANDS must contain no duplicates');

        // The commands that decide a Taekwondo match must all still be offered.
        foreach (['load', 'start', 'pause', 'score', 'gamjeom', 'commit', 'clear'] as $required) {
            $this->assertContains($required, $commands, "the '{$required}' command disappeared");
        }
    }

    public function test_karate_only_commands_are_not_accepted_by_the_taekwondo_mat(): void
    {
        // Sport isolation: the two packages must not drift into a shared
        // vocabulary. 'senshu' and 'penalty' are Karate's; Taekwondo has neither.
        foreach (['senshu', 'penalty', 'undo_point'] as $karateOnly) {
            $this->assertNotContains(
                $karateOnly,
                Scoring::COMMANDS,
                "'{$karateOnly}' is Karate vocabulary and must not reach a Taekwondo mat"
            );
        }
    }

    /* ---------------------------------------------------------------
     * Scoring actions and their values
     * --------------------------------------------------------------- */

    public function test_each_scoring_action_is_worth_the_value_the_control_page_offers(): void
    {
        // Locks MatState::ACTIONS — the five buttons on the approved control and
        // what each one adds to the round score.
        $this->assertSame([
            'punch' => ['value' => 1, 'label' => 'PUNCH'],
            'body' => ['value' => 2, 'label' => 'BODY KICK'],
            'head' => ['value' => 3, 'label' => 'HEAD KICK'],
            'turn_body' => ['value' => 4, 'label' => 'TURNING BODY'],
            'turn_head' => ['value' => 5, 'label' => 'TURNING HEAD'],
        ], MatState::ACTIONS);
    }

    public function test_an_unsupported_scoring_action_has_no_value(): void
    {
        // Locks the ACTIONS allow-list as the only vocabulary a score command
        // may name — anything else is not a scoreable technique.
        foreach (['ippon', 'yuko', 'waza_ari', 'kick', ''] as $notAnAction) {
            $this->assertArrayNotHasKey($notAnAction, MatState::ACTIONS);
        }
    }

    /* ---------------------------------------------------------------
     * Point gap — MatState::pointGapReached()
     * --------------------------------------------------------------- */

    public function test_the_point_gap_that_stops_a_round_is_twelve(): void
    {
        // Locks MatState::POINT_GAP.
        $this->assertSame(12, MatState::POINT_GAP);
    }

    public function test_a_round_is_gapped_only_once_the_lead_reaches_the_threshold(): void
    {
        // Locks MatState::pointGapReached() — inclusive comparison on the
        // ABSOLUTE difference, so it fires for either corner.
        $this->assertFalse((new MatState(akaScore: 11, aoScore: 0))->pointGapReached());
        $this->assertTrue((new MatState(akaScore: 12, aoScore: 0))->pointGapReached());
        $this->assertTrue((new MatState(akaScore: 13, aoScore: 0))->pointGapReached());

        // Symmetric for ao.
        $this->assertFalse((new MatState(akaScore: 0, aoScore: 11))->pointGapReached());
        $this->assertTrue((new MatState(akaScore: 0, aoScore: 12))->pointGapReached());

        // A lead measured against a non-zero score, not against the total.
        $this->assertFalse((new MatState(akaScore: 20, aoScore: 9))->pointGapReached());
        $this->assertTrue((new MatState(akaScore: 21, aoScore: 9))->pointGapReached());
    }

    /* ---------------------------------------------------------------
     * Gam-jeom — the ceiling and its precedence
     * --------------------------------------------------------------- */

    public function test_five_gam_jeom_is_the_ceiling(): void
    {
        // Locks MatState::GAM_JEOM_LIMIT. Counted across the whole match, per
        // the constant's own docblock — not per round.
        $this->assertSame(5, MatState::GAM_JEOM_LIMIT);
    }

    public function test_reaching_the_gam_jeom_ceiling_loses_the_round_however_far_ahead(): void
    {
        // Locks MatState::roundWinner() — the gam-jeom clause is checked BEFORE
        // the score, so a commanding lead does not save the penalised athlete.
        $state = new MatState(akaScore: 30, aoScore: 0, akaGam: 5);

        $this->assertSame('ao', $state->roundWinner());
    }

    public function test_below_the_ceiling_the_round_is_decided_on_score(): void
    {
        // Locks MatState::roundWinner() — four gam-jeom does not end anything.
        $this->assertSame('aka', (new MatState(akaScore: 5, aoScore: 3, akaGam: 4))->roundWinner());
        $this->assertSame('ao', (new MatState(akaScore: 3, aoScore: 5))->roundWinner());
    }

    public function test_a_level_round_has_no_winner(): void
    {
        // Locks MatState::roundWinner() — level and unpenalised returns null,
        // which is what sends a match to the golden round.
        $this->assertNull((new MatState(akaScore: 7, aoScore: 7))->roundWinner());
        $this->assertNull((new MatState)->roundWinner());
    }

    /* ---------------------------------------------------------------
     * Who has won the MATCH — MatState::matchWinner()
     * --------------------------------------------------------------- */

    public function test_a_match_is_won_by_rounds_not_by_aggregate_score(): void
    {
        // Locks MatState::matchWinner() + ::roundsToWin(). Best of three means
        // two rounds; the running score is irrelevant to the match result.
        $best_of_three = new MatState(akaScore: 0, aoScore: 40, akaRounds: 2, aoRounds: 0, rounds: 3);

        $this->assertSame(2, $best_of_three->roundsToWin());
        $this->assertSame('aka', $best_of_three->matchWinner());
    }

    public function test_a_match_still_in_progress_has_no_winner(): void
    {
        // Locks MatState::matchWinner() — null while it is live.
        $this->assertNull((new MatState(akaRounds: 1, aoRounds: 1, rounds: 3))->matchWinner());
        $this->assertNull((new MatState)->matchWinner());
    }

    public function test_rounds_needed_to_win_is_a_majority_of_the_rounds_scheduled(): void
    {
        // Locks MatState::roundsToWin() = intdiv(rounds, 2) + 1, for every
        // shape the console can configure.
        $this->assertSame(1, (new MatState(rounds: 1))->roundsToWin());
        $this->assertSame(2, (new MatState(rounds: 2))->roundsToWin());
        $this->assertSame(2, (new MatState(rounds: 3))->roundsToWin());
        $this->assertSame(3, (new MatState(rounds: 5))->roundsToWin());
    }

    public function test_punitive_declaration_outranks_the_round_series(): void
    {
        // Locks the punWinner branch at the top of MatState::matchWinner() —
        // PUN ends the match wherever the series stands, including 0-0.
        $level = new MatState(akaRounds: 0, aoRounds: 0, punWinner: 'ao');
        $this->assertSame('ao', $level->matchWinner());

        // …and even when the OTHER athlete already holds enough rounds to win.
        $behind = new MatState(akaRounds: 2, aoRounds: 0, rounds: 3, punWinner: 'ao');
        $this->assertSame('ao', $behind->matchWinner());
    }

    /* ---------------------------------------------------------------
     * The phase line the console prints
     * --------------------------------------------------------------- */

    public function test_the_phase_line_names_the_rule_that_stopped_the_round(): void
    {
        // Locks MatState::phaseLabel(). An operator must be able to tell a
        // round that ended on a rule from a console that has frozen.
        $this->assertSame('Round 1', (new MatState)->phaseLabel());
        $this->assertSame('Round 3', (new MatState(round: 3))->phaseLabel());

        $this->assertSame('Rest', (new MatState(phase: MatState::PHASE_REST))->phaseLabel());
        $this->assertSame(
            'Rest · round on 5 gam-jeom',
            (new MatState(phase: MatState::PHASE_REST, endReason: 'gamjeom'))->phaseLabel()
        );
        $this->assertSame(
            'Rest · round on point gap',
            (new MatState(phase: MatState::PHASE_REST, endReason: 'gap'))->phaseLabel()
        );

        $this->assertSame('Golden round', (new MatState(phase: MatState::PHASE_GOLDEN))->phaseLabel());
    }

    public function test_a_finished_match_says_how_it_was_won(): void
    {
        // Locks the matchOver branch of MatState::phaseLabel(), which outranks
        // the phase — a match over in the golden round says so.
        $this->assertSame('Match over', (new MatState(matchOver: true))->phaseLabel());
        $this->assertSame(
            'Match over · golden point',
            (new MatState(matchOver: true, endReason: 'golden'))->phaseLabel()
        );
        $this->assertSame(
            'Match over · 5 gam-jeom (PUN)',
            (new MatState(matchOver: true, endReason: 'gamjeom'))->phaseLabel()
        );
        $this->assertSame(
            'Match over · point gap',
            (new MatState(matchOver: true, endReason: 'gap'))->phaseLabel()
        );
        $this->assertSame(
            'Match over',
            (new MatState(matchOver: true, phase: MatState::PHASE_GOLDEN))->phaseLabel()
        );
    }

    /* ---------------------------------------------------------------
     * Serialisation — what every screen in the hall is handed
     * --------------------------------------------------------------- */

    public function test_the_broadcast_state_carries_every_critical_display_value(): void
    {
        // Locks MatState::toArray(). Each key is read by the wall board, the
        // court display or the operator console; dropping one blanks a
        // scoreboard in a hall rather than failing loudly.
        //
        // NOTE: corners are left empty on purpose. toArray() -> announced()
        // calls App\Support\Countries::label() for a non-empty country, which
        // reaches the Cache facade and so needs a booted container.
        $state = new MatState(
            mode: MatState::MODE_SCOREBOARD,
            matchId: 88,
            akaScore: 9,
            aoScore: 4,
            akaGam: 1,
            aoGam: 2,
            akaRounds: 1,
            round: 2,
            remaining: 61.0,
            running: true,
        );

        $out = $state->toArray();

        foreach ([
            'mode', 'matchId', 'matchNo', 'stage', 'division', 'category',
            'tournament', 'courtLabel', 'referee', 'aka', 'ao',
            'akaScore', 'aoScore', 'akaGam', 'aoGam', 'akaRounds', 'aoRounds',
            'round', 'rounds', 'phase', 'remaining', 'duration', 'restDuration',
            'running', 'finished', 'matchOver', 'endReason', 'punWinner',
            'lastEvent', 'log', 'at',
            'roundWinner', 'matchWinner', 'roundsToWin', 'phaseLabel',
        ] as $key) {
            $this->assertArrayHasKey($key, $out, "broadcast state lost '{$key}'");
        }

        // The derived truths travel with it, so no client owns a copy of the rules.
        $this->assertSame('aka', $out['roundWinner']);
        $this->assertNull($out['matchWinner']);
        $this->assertSame(2, $out['roundsToWin']);
        $this->assertSame('Round 2', $out['phaseLabel']);
    }

    public function test_state_survives_a_round_trip_through_the_cache_blob(): void
    {
        // Locks MatState::toArray() + ::fromArray(). Running match state lives
        // ONLY in the cache, so this round trip is what a wall screen relies on
        // after a reload mid-match.
        $original = new MatState(
            mode: MatState::MODE_SCOREBOARD,
            matchId: 3,
            matchNo: '18',
            akaScore: 6,
            aoScore: 6,
            akaGam: 2,
            aoGam: 1,
            akaRounds: 1,
            aoRounds: 1,
            round: 3,
            rounds: 3,
            phase: MatState::PHASE_GOLDEN,
            remaining: 30.0,
            duration: 60.0,
            running: false,
            finished: true,
            matchOver: false,
            endReason: 'gap',
        );

        $restored = MatState::fromArray($original->toArray());

        $this->assertSame($original->akaScore, $restored->akaScore);
        $this->assertSame($original->aoScore, $restored->aoScore);
        $this->assertSame($original->akaGam, $restored->akaGam);
        $this->assertSame($original->aoGam, $restored->aoGam);
        $this->assertSame($original->akaRounds, $restored->akaRounds);
        $this->assertSame($original->aoRounds, $restored->aoRounds);
        $this->assertSame($original->round, $restored->round);
        $this->assertSame($original->phase, $restored->phase);
        $this->assertSame($original->endReason, $restored->endReason);
        $this->assertSame($original->finished, $restored->finished);
        $this->assertSame($original->remaining, $restored->remaining);
    }

    public function test_a_restored_round_number_can_never_fall_below_one(): void
    {
        // Locks the max(1, ...) clamps in MatState::fromArray() — a corrupt or
        // tampered cache blob cannot produce "Round 0" on a wall screen.
        $this->assertSame(1, MatState::fromArray(['round' => 0])->round);
        $this->assertSame(1, MatState::fromArray(['round' => -4])->round);
        $this->assertSame(1, MatState::fromArray(['rounds' => 0])->rounds);
    }

    /* ---------------------------------------------------------------
     * Defaults
     * --------------------------------------------------------------- */

    public function test_a_fresh_mat_is_a_best_of_three_with_nothing_scored(): void
    {
        // Locks the MatState constructor defaults.
        $state = new MatState;

        $this->assertSame(MatState::MODE_UPCOMING, $state->mode);
        $this->assertSame(MatState::PHASE_ROUND, $state->phase);
        $this->assertSame(1, $state->round);
        $this->assertSame(3, $state->rounds);
        $this->assertSame(0, $state->akaScore);
        $this->assertSame(0, $state->aoScore);
        $this->assertSame(0, $state->akaGam);
        $this->assertSame(0, $state->aoGam);
        $this->assertSame(0, $state->akaRounds);
        $this->assertSame(0, $state->aoRounds);
        $this->assertSame(120.0, $state->duration);
        $this->assertSame(60.0, $state->restDuration);
        $this->assertFalse($state->running);
        $this->assertFalse($state->finished);
        $this->assertFalse($state->matchOver);
        $this->assertNull($state->punWinner);
        $this->assertNull($state->endReason);
    }

    public function test_the_presentation_modes_and_phases_are_stable(): void
    {
        // Locks the MODE_*/PHASE_* constants — the Blade screens branch on these.
        $this->assertSame('upcoming', MatState::MODE_UPCOMING);
        $this->assertSame('vs', MatState::MODE_VS);
        $this->assertSame('scoreboard', MatState::MODE_SCOREBOARD);
        $this->assertSame('round', MatState::PHASE_ROUND);
        $this->assertSame('rest', MatState::PHASE_REST);
        $this->assertSame('golden', MatState::PHASE_GOLDEN);
    }
}
