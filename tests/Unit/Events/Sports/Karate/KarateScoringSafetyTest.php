<?php

namespace Tests\Unit\Events\Sports\Karate;

use App\Scoreboard\Sports\Karate\Mat\MatState;
use App\Scoreboard\Sports\Karate\Mat\Scoring;
use PHPUnit\Framework\TestCase;

/**
 * PHASE 0 SAFETY BASELINE — Karate tournament scoring.
 *
 * These tests LOCK DOWN CURRENT BEHAVIOUR. They are characterisation tests, not
 * a rulebook: every expectation below was read out of the repository, never out
 * of the WKF competition rules. If a change makes one of these fail, that is a
 * deliberate decision to be made by a human, not a test to be edited quietly.
 *
 * Production sources under test:
 *   · app/Events/Sports/Karate/Tournament/Scoreboard/MatState.php
 *   · app/Events/Sports/Karate/Tournament/Scoreboard/Scoring.php  (constants only)
 *
 * Deliberately NOT touched here: Scoring::apply(). It loads a ClubEvent from the
 * database, reads and writes the cache, appends to MatchEventLog and notifies
 * CameraFleet, and every rule method beneath it (point/penalty/senshu/finish) is
 * `private`. It cannot be exercised without booting Laravel and a database.
 * See docs/PHASE_0_SCORING_SAFETY_REPORT.md → "Testability Gaps".
 *
 * This case extends PHPUnit's TestCase directly (as tests/Unit/ExampleTest.php
 * does) so that no framework, no database and no cache is ever touched.
 */
class KarateScoringSafetyTest extends TestCase
{
    /* ---------------------------------------------------------------
     * Command vocabulary — the endpoint's allow-list
     *
     * ScoreboardController::command() validates with
     *   'command' => in:<Scoring::COMMANDS>
     *   'reason'  => in:<Scoring::WIN_REASONS>
     * so these constants ARE the authorization surface for what an operator may
     * ask a mat to do. Silently adding or removing an entry widens or narrows
     * that surface without any other test noticing.
     * --------------------------------------------------------------- */

    public function test_the_accepted_command_vocabulary_is_exactly_the_documented_list(): void
    {
        // Locks App\Scoreboard\Sports\Karate\Mat\Scoring::COMMANDS
        $this->assertSame([
            'load', 'start', 'pause', 'point', 'undo_point', 'penalty', 'senshu',
            'time', 'reset', 'finish', 'clear', 'commit', 'dismiss', 'celebrate',
            'resync', 'board', 'intro', 'duration', 'corner', 'meta', 'rules',
        ], Scoring::COMMANDS);
    }

    public function test_an_unsupported_command_is_not_in_the_accepted_vocabulary(): void
    {
        // Scoring::apply()'s match() has `default => null` — an unknown command
        // mutates nothing. The endpoint rejects it earlier, via this allow-list.
        foreach (['score', 'gamjeom', 'award_round', 'golden', 'delete', 'set_score'] as $notACommand) {
            $this->assertNotContains(
                $notACommand,
                Scoring::COMMANDS,
                "'{$notACommand}' must not be an accepted Karate command"
            );
        }
    }

    public function test_the_win_reason_vocabulary_is_exactly_the_documented_list(): void
    {
        // Locks Scoring::WIN_REASONS — this list reaches the official record.
        $this->assertSame(
            ['points', 'hansoku', 'shikkaku', 'kiken', 'medical', 'no_show', 'other'],
            Scoring::WIN_REASONS
        );
    }

    /* ---------------------------------------------------------------
     * Penalty ladder and point callouts
     * --------------------------------------------------------------- */

    public function test_the_penalty_ladder_has_five_rungs_in_ascending_severity(): void
    {
        // Locks MatState::PENALTIES. Its COUNT is load-bearing: Scoring::penalty()
        // clamps to count(MatState::PENALTIES) and treats reaching the top as
        // hansoku, and ScoreboardController validates 'level' => max:count(...).
        $this->assertSame(['C1', 'C2', 'C3', 'HC', 'H'], MatState::PENALTIES);
        $this->assertCount(5, MatState::PENALTIES);
    }

    public function test_a_point_is_called_out_by_its_value(): void
    {
        // Locks MatState::CALLOUTS. Points are validated 'n' => min:1, max:3 by
        // ScoreboardController, so the map covers the whole accepted range.
        $this->assertSame([1 => 'YUKO', 2 => 'WAZA-ARI', 3 => 'IPPON'], MatState::CALLOUTS);
    }

    /* ---------------------------------------------------------------
     * Who is leading — MatState::akaLeads() / aoLeads()
     *
     * These two decide the colour on the wall board, the celebration, and which
     * athlete is carried into the bracket. They are the highest-consequence
     * pure functions in the Karate package.
     * --------------------------------------------------------------- */

    public function test_the_higher_score_leads_the_bout(): void
    {
        // Locks MatState::akaLeads()/aoLeads() — plain score comparison.
        $state = new MatState(akaScore: 4, aoScore: 1);

        $this->assertTrue($state->akaLeads());
        $this->assertFalse($state->aoLeads());
    }

    public function test_a_level_bout_with_no_senshu_has_no_leader(): void
    {
        // Locks MatState::akaLeads()/aoLeads() — level and unheld means neither.
        $state = new MatState(akaScore: 3, aoScore: 3);

        $this->assertFalse($state->akaLeads());
        $this->assertFalse($state->aoLeads());
    }

    public function test_senshu_breaks_a_tie_in_favour_of_whoever_holds_it(): void
    {
        // Locks MatState::akaLeads()/aoLeads() — the senshu tie-break clause.
        $akaHolds = new MatState(akaScore: 3, aoScore: 3, akaSenshu: true);
        $this->assertTrue($akaHolds->akaLeads());
        $this->assertFalse($akaHolds->aoLeads());

        $aoHolds = new MatState(akaScore: 3, aoScore: 3, aoSenshu: true);
        $this->assertFalse($aoHolds->akaLeads());
        $this->assertTrue($aoHolds->aoLeads());
    }

    public function test_senshu_does_not_override_a_higher_score(): void
    {
        // Locks MatState::akaLeads()/aoLeads() — senshu applies only when level.
        $state = new MatState(akaScore: 1, aoScore: 5, akaSenshu: true);

        $this->assertFalse($state->akaLeads());
        $this->assertTrue($state->aoLeads());
    }

    public function test_a_declared_winner_outranks_both_the_score_and_senshu(): void
    {
        // Locks the override branch at the top of MatState::akaLeads()/aoLeads().
        // This is how hansoku / kiken / medical retirement hand a bout to the
        // side with FEWER points, which must not read as a mistake on the wall.
        $state = new MatState(akaScore: 0, aoScore: 7, aoSenshu: true, winner: 'aka');

        $this->assertTrue($state->akaLeads());
        $this->assertFalse($state->aoLeads());
    }

    /* ---------------------------------------------------------------
     * Bout status line
     * --------------------------------------------------------------- */

    public function test_the_bout_status_reads_hajime_yame_or_time(): void
    {
        // Locks MatState::boutStatus(). `finished` outranks `running`.
        $this->assertSame('Yame', (new MatState)->boutStatus());
        $this->assertSame('Hajime', (new MatState(running: true))->boutStatus());
        $this->assertSame('Time', (new MatState(running: false, finished: true))->boutStatus());
        $this->assertSame('Time', (new MatState(running: true, finished: true))->boutStatus());
    }

    /* ---------------------------------------------------------------
     * Serialisation — what every screen in the hall is handed
     * --------------------------------------------------------------- */

    public function test_the_broadcast_state_carries_every_critical_display_value(): void
    {
        // Locks MatState::toArray(). Each key below is read by the wall board,
        // the court display and the operator console; dropping one blanks a
        // scoreboard in a hall rather than failing loudly.
        //
        // NOTE: corners are left empty on purpose. toArray() -> announced()
        // calls App\Support\Countries::label() for a non-empty country, which
        // reaches the Cache facade and so needs a booted container.
        $state = new MatState(
            mode: MatState::MODE_SCOREBOARD,
            matchId: 41,
            akaScore: 6,
            aoScore: 2,
            akaPen: 2,
            aoPen: 0,
            akaSenshu: true,
            remaining: 42.5,
            running: true,
        );

        $out = $state->toArray();

        foreach ([
            'mode', 'matchId', 'matchNo', 'stage', 'division', 'tournament',
            'courtLabel', 'referee', 'aka', 'ao', 'akaScore', 'aoScore',
            'akaPen', 'aoPen', 'akaSenshu', 'aoSenshu', 'remaining', 'duration',
            'running', 'finished', 'celebrationClosed', 'winner', 'winReason',
            'winNote', 'lastEvent', 'at', 'akaLeads', 'aoLeads', 'boutStatus',
            'senshuRule', 'autoSenshu', 'winByPenalties', 'atoshiWarn',
            'timeUpBuzzer', 'gapOn', 'gap', 'warning', 'awaitingDecision',
        ] as $key) {
            $this->assertArrayHasKey($key, $out, "broadcast state lost '{$key}'");
        }

        // The derived truths travel with it, so no client owns a copy of the rules.
        $this->assertSame(6, $out['akaScore']);
        $this->assertSame(2, $out['aoScore']);
        $this->assertTrue($out['akaLeads']);
        $this->assertFalse($out['aoLeads']);
        $this->assertSame('Hajime', $out['boutStatus']);
    }

    public function test_state_survives_a_round_trip_through_the_cache_blob(): void
    {
        // Locks MatState::toArray() + ::fromArray(). Running match state lives
        // ONLY in the cache, so this round trip is what a wall screen relies on
        // after a reload mid-bout.
        $original = new MatState(
            mode: MatState::MODE_SCOREBOARD,
            matchId: 7,
            matchNo: '12',
            akaScore: 5,
            aoScore: 5,
            akaPen: 3,
            aoPen: 1,
            aoSenshu: true,
            remaining: 12.3,
            duration: 120.0,
            running: false,
            finished: true,
            winner: 'ao',
            winReason: 'hansoku',
            gap: 8,
        );

        $restored = MatState::fromArray($original->toArray());

        $this->assertSame($original->akaScore, $restored->akaScore);
        $this->assertSame($original->aoScore, $restored->aoScore);
        $this->assertSame($original->akaPen, $restored->akaPen);
        $this->assertSame($original->aoPen, $restored->aoPen);
        $this->assertSame($original->aoSenshu, $restored->aoSenshu);
        $this->assertSame($original->finished, $restored->finished);
        $this->assertSame($original->winner, $restored->winner);
        $this->assertSame($original->winReason, $restored->winReason);
        $this->assertSame($original->remaining, $restored->remaining);
        $this->assertTrue($restored->aoLeads());
    }

    public function test_a_restored_winner_outside_the_known_corners_is_discarded(): void
    {
        // Locks the winner guard in MatState::fromArray() — only 'aka'/'ao'
        // survive, so a corrupted or tampered cache blob cannot declare a
        // winner the bout never had.
        $this->assertNull(MatState::fromArray(['winner' => 'red'])->winner);
        $this->assertNull(MatState::fromArray(['winner' => true])->winner);
        $this->assertSame('aka', MatState::fromArray(['winner' => 'aka'])->winner);
    }

    /* ---------------------------------------------------------------
     * Event-level rules
     * --------------------------------------------------------------- */

    public function test_the_event_level_rule_defaults_are_wkf_as_normally_run(): void
    {
        // Locks the MatState constructor defaults. A mat nobody configures must
        // behave exactly as it did before these settings existed.
        $state = new MatState;

        $this->assertTrue($state->senshuRule);
        $this->assertTrue($state->autoSenshu);
        $this->assertTrue($state->winByPenalties);
        $this->assertTrue($state->atoshiWarn);
        $this->assertTrue($state->timeUpBuzzer);
        $this->assertTrue($state->gapOn);
        $this->assertSame(8, $state->gap);
        $this->assertSame(15.0, $state->warning);
        $this->assertSame(180.0, $state->duration);
        $this->assertFalse($state->finished);
        $this->assertFalse($state->awaitingDecision);
        $this->assertSame(MatState::MODE_UPCOMING, $state->mode);
    }

    public function test_the_settings_that_outlive_a_bout_are_exactly_the_documented_list(): void
    {
        // Locks MatState::SETTINGS — the keys ::load() restores from the event
        // and ::persistSettings() writes back. An entry dropped here silently
        // stops persisting an official's morning configuration.
        $this->assertSame([
            'senshuRule', 'autoSenshu', 'winByPenalties', 'atoshiWarn',
            'timeUpBuzzer', 'gapOn', 'gap', 'warning', 'duration',
        ], MatState::SETTINGS);
    }

    public function test_the_three_presentation_modes_are_stable(): void
    {
        // Locks the MODE_* constants — the Blade screens branch on these strings.
        $this->assertSame('upcoming', MatState::MODE_UPCOMING);
        $this->assertSame('vs', MatState::MODE_VS);
        $this->assertSame('scoreboard', MatState::MODE_SCOREBOARD);
    }
}
