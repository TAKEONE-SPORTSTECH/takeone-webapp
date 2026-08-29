<?php

namespace Tests\Feature\Events;

use App\Events\Sports\Karate\Tournament\Scoreboard\MatState;
use App\Models\ClubEvent;
use App\Models\EventCategory;
use App\Models\EventMatch;
use Tests\TestCase;

/**
 * RELEASE SAFETY — the Karate per-mat rule set and where it persists.
 *
 * This is the feature the pending Karate release adds: a mat's rules
 * (senshu, the point gap, the penalty ending, the bout length, the warning)
 * stop living only in a cache entry with a four-hour TTL and are written to
 * `club_events.scoreboard_settings`, so an official who configures a mat in the
 * morning still has those rules after lunch.
 *
 * These tests characterise CURRENT behaviour of that release. Nothing here is
 * derived from the WKF rulebook — every expectation is read out of the code:
 *   · app/Events/Sports/Karate/Tournament/Scoreboard/MatState.php
 *       ::SETTINGS, ::load(), ::persistSettings()
 *   · app/Events/Sports/Karate/Tournament/Scoreboard/Scoring.php
 *       ::apply() 'rules' → rules(), 'duration' → durationCommand()
 *   · app/Events/Sports/Karate/Tournament/Scoreboard/ScoreboardController::command()
 *   · app/Models/ClubEvent  — $fillable + 'scoreboard_settings' => 'array'
 *   · database/migrations/2026_08_24_150000_add_scoreboard_settings_to_club_events.php
 *
 * Persistence is asserted through a genuinely fresh read — the cache is
 * forgotten and `MatState::load()` is called again — because a value that only
 * survives while the cache entry lives is exactly the bug this release fixes.
 *
 * The suite runs on in-memory SQLite with array cache/session, sync queue and
 * realtime disabled (phpunit.xml), so nothing here touches a real database,
 * broker or mat.
 */
class KarateScoreboardSettingsSafetyTest extends TestCase
{
    private const MAT = 'Mat 1';

    /**
     * A Karate championship on one mat, with an organiser who may score it.
     *
     * Modelled on tests/Feature/Events/TaekwondoRealtimeTest::scenario() and
     * built from the Tests\TestCase helpers — no new factory infrastructure.
     *
     * @return array{0: \App\Models\User, 1: ClubEvent, 2: EventMatch}
     */
    private function scenario(string $sport = 'karate'): array
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner, ['country' => 'BH', 'currency' => 'BHD']);
        $owner->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);

        // created_by === the organiser, which is what EventAccess::canManage()
        // — and so canScore() — reads.
        $event = ClubEvent::create([
            'tenant_id' => $club->id,
            'created_by' => $owner->id,
            'title' => 'Settings Safety Open',
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
            'name' => 'Senior Men -75 kg',
            'sort_order' => 1,
        ]);

        // The court is what makes ScoreboardController::matExists() true.
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

    private function endpoint(ClubEvent $event, string $sport = 'karate'): string
    {
        return "/{$sport}/control/{$event->uuid}";
    }

    /**
     * Read the mat back with no help from the cache entry the write left behind.
     *
     * This is the whole point of the feature: forget the cache, then load. What
     * survives came from `club_events.scoreboard_settings`, not from memory.
     */
    private function reloadedState(ClubEvent $event): MatState
    {
        MatState::forget($event, self::MAT);

        return MatState::load($event->fresh(), self::MAT);
    }

    /** The official record must be untouched by anything in this file. */
    private function assertResultUntouched(EventMatch $match): void
    {
        $fresh = $match->fresh();

        $this->assertSame('upcoming', $fresh->status, 'settings must not change a match status');
        $this->assertNull($fresh->winner, 'settings must never decide a bout');
        $this->assertNull($fresh->a_score, 'settings must not write a score');
        $this->assertNull($fresh->b_score, 'settings must not write a score');
    }

    /* ---------------------------------------------------------------
     * 1. A brand-new event has no settings at all
     * --------------------------------------------------------------- */

    public function test_an_event_with_no_saved_settings_falls_back_to_the_built_in_defaults(): void
    {
        // Locks MatState::load()'s `foreach ((array) $event->scoreboard_settings …)`.
        // A fresh event has NULL in the column, `(array) null` is `[]`, the loop
        // is skipped, and the constructor defaults stand. The migration's own
        // docblock states those defaults ARE the previous hard-coded values, so
        // an event created before this release behaves exactly as it did.
        [, $event] = $this->scenario();

        $this->assertNull($event->scoreboard_settings, 'a new event stores nothing until a rule is set');

        $state = MatState::load($event, self::MAT);

        $this->assertTrue($state->senshuRule);
        $this->assertTrue($state->autoSenshu);
        $this->assertTrue($state->winByPenalties);
        $this->assertTrue($state->atoshiWarn);
        $this->assertTrue($state->timeUpBuzzer);
        $this->assertTrue($state->gapOn);
        $this->assertSame(8, $state->gap);
        $this->assertSame(15.0, $state->warning);
        $this->assertSame(180.0, $state->duration);

        // The clock starts where the bout length says it does.
        $this->assertSame(180.0, $state->remaining);
    }

    public function test_an_empty_settings_array_still_falls_back_to_the_defaults(): void
    {
        // Guards the boundary between "never configured" (null) and "configured
        // with nothing" ([]) — both must be safe, since the cast turns an empty
        // JSON object into an empty array.
        [, $event] = $this->scenario();

        $event->forceFill(['scoreboard_settings' => []])->save();

        $state = MatState::load($event->fresh(), self::MAT);

        $this->assertTrue($state->senshuRule);
        $this->assertSame(8, $state->gap);
        $this->assertSame(180.0, $state->duration);
    }

    /* ---------------------------------------------------------------
     * 2. An authorised organiser can save the rules
     * --------------------------------------------------------------- */

    public function test_an_authorised_organiser_can_save_the_mats_rules(): void
    {
        // Locks Scoring::rules() and the 'rules' command's validation in
        // ScoreboardController::command(). The organiser is the event's creator,
        // so EventAccess::canScore() allows it.
        [$owner, $event, $match] = $this->scenario();

        $this->actingAs($owner)
            ->postJson($this->endpoint($event), [
                'mat' => self::MAT,
                'command' => 'rules',
                'senshuRule' => false,
                'autoSenshu' => false,
                'winByPenalties' => false,
                'atoshiWarn' => false,
                'timeUpBuzzer' => false,
                'gapOn' => false,
                'gap' => 6,
                'warning' => 10,
            ])
            ->assertSuccessful();

        // Written to the event, not just to the mat's cache entry.
        $saved = $event->fresh()->scoreboard_settings;

        $this->assertIsArray($saved, 'the array cast on ClubEvent must decode the JSON column');
        $this->assertFalse($saved['senshuRule']);
        $this->assertFalse($saved['gapOn']);
        $this->assertSame(6, $saved['gap']);
        $this->assertSame(10.0, (float) $saved['warning']);

        $this->assertResultUntouched($match);
    }

    public function test_every_documented_setting_is_persisted(): void
    {
        // Locks MatState::SETTINGS — persistSettings() writes exactly these nine
        // keys. A key silently dropped from that list stops being remembered,
        // which is invisible until an official loses it over a lunch break.
        [$owner, $event] = $this->scenario();

        $this->actingAs($owner)
            ->postJson($this->endpoint($event), [
                'mat' => self::MAT, 'command' => 'rules', 'gap' => 7,
            ])
            ->assertSuccessful();

        $saved = $event->fresh()->scoreboard_settings;

        foreach (MatState::SETTINGS as $key) {
            $this->assertArrayHasKey($key, $saved, "setting '{$key}' was not persisted");
        }
        $this->assertSame(MatState::SETTINGS, array_keys($saved), 'only the declared settings are stored');
    }

    /* ---------------------------------------------------------------
     * 3. Saved settings survive a genuinely fresh MatState load
     * --------------------------------------------------------------- */

    public function test_saved_rules_survive_the_cache_entry_being_lost(): void
    {
        // THE feature. The mat's cache entry has a 240-minute TTL; forgetting it
        // simulates that expiry (or a cache flush, or a second web node). What
        // comes back must come from club_events.scoreboard_settings.
        [$owner, $event, $match] = $this->scenario();

        $this->actingAs($owner)
            ->postJson($this->endpoint($event), [
                'mat' => self::MAT,
                'command' => 'rules',
                'senshuRule' => false,
                'gapOn' => false,
                'gap' => 4,
                'warning' => 5,
            ])
            ->assertSuccessful();

        $state = $this->reloadedState($event);

        $this->assertFalse($state->senshuRule, 'senshu must still be off after the cache is gone');
        $this->assertFalse($state->gapOn);
        $this->assertSame(4, $state->gap);
        $this->assertSame(5.0, $state->warning);

        $this->assertResultUntouched($match);
    }

    public function test_the_score_itself_does_not_survive_the_cache_being_lost(): void
    {
        // The deliberate other half of the same design, asserted so the boundary
        // is explicit: the RULES are persisted, the running SCORE is not — it
        // lives only in the cache entry (MatState::save, 240-minute TTL) and
        // nothing rebuilds it. Documented in MatState's own class docblock.
        [$owner, $event] = $this->scenario();

        $this->actingAs($owner)
            ->postJson($this->endpoint($event), [
                'mat' => self::MAT, 'command' => 'rules', 'gap' => 9,
            ])
            ->assertSuccessful();

        $state = $this->reloadedState($event);

        $this->assertSame(9, $state->gap, 'the rule persists');
        $this->assertSame(0, $state->akaScore, 'the score does not');
        $this->assertNull($state->matchId, 'and neither does the bout on the mat');
    }

    /* ---------------------------------------------------------------
     * 4. Bout length is a setting too
     * --------------------------------------------------------------- */

    public function test_the_bout_length_persists_after_the_mat_is_reloaded(): void
    {
        // Locks Scoring::durationCommand() → duration() + persistSettings().
        // 'seconds' is what the settings panel sends. The regression this guards
        // is the one named in the code: an official set 2:00 and found 3:00 again
        // after the next load.
        [$owner, $event, $match] = $this->scenario();

        $this->actingAs($owner)
            ->postJson($this->endpoint($event), [
                'mat' => self::MAT, 'command' => 'duration', 'seconds' => 120,
            ])
            ->assertSuccessful();

        $state = $this->reloadedState($event);

        $this->assertSame(120.0, $state->duration, 'the configured bout length must outlive the cache');
        $this->assertSame(120.0, $state->remaining, 'a cold mat starts its clock at the configured length');

        $this->assertSame(120.0, (float) $event->fresh()->scoreboard_settings['duration']);
        $this->assertResultUntouched($match);
    }

    public function test_a_warning_longer_than_the_bout_is_pulled_back_to_fit_it(): void
    {
        // Locks the `min($state->warning, $state->duration)` clamp in duration().
        // A warning that started before the clock did would flash from hajime to
        // the bell. Asserted through a reload, so the clamped value is the one
        // that was persisted rather than merely displayed.
        [$owner, $event] = $this->scenario();

        $this->actingAs($owner)
            ->postJson($this->endpoint($event), [
                'mat' => self::MAT, 'command' => 'rules', 'warning' => 60,
            ])
            ->assertSuccessful();

        $this->actingAs($owner)
            ->postJson($this->endpoint($event), [
                'mat' => self::MAT, 'command' => 'duration', 'seconds' => 30,
            ])
            ->assertSuccessful();

        $state = $this->reloadedState($event);

        $this->assertSame(30.0, $state->duration);
        $this->assertSame(30.0, $state->warning, 'the warning cannot outlast the bout it belongs to');
    }

    /* ---------------------------------------------------------------
     * 5. Invalid settings are rejected and change nothing
     * --------------------------------------------------------------- */

    public function test_a_gap_outside_the_accepted_range_is_rejected_and_saves_nothing(): void
    {
        // Locks 'gap' => ['integer','min:1','max:20'] in
        // ScoreboardController::command(). Validation runs before Scoring::apply,
        // so a refused request must leave the stored settings exactly as they were.
        [$owner, $event, $match] = $this->scenario();

        // Establish a known-good baseline first.
        $this->actingAs($owner)->postJson($this->endpoint($event), [
            'mat' => self::MAT, 'command' => 'rules', 'gap' => 8,
        ])->assertSuccessful();

        $before = $event->fresh()->scoreboard_settings;

        $this->actingAs($owner)
            ->postJson($this->endpoint($event), [
                'mat' => self::MAT, 'command' => 'rules', 'gap' => 99,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('gap');

        $this->assertSame($before, $event->fresh()->scoreboard_settings, 'a refused request must not alter saved settings');
        $this->assertSame(8, $this->reloadedState($event)->gap);
        $this->assertResultUntouched($match);
    }

    public function test_a_non_boolean_rule_flag_is_rejected_and_saves_nothing(): void
    {
        // Locks 'senshuRule' => ['nullable','boolean'].
        [$owner, $event, $match] = $this->scenario();

        $this->actingAs($owner)->postJson($this->endpoint($event), [
            'mat' => self::MAT, 'command' => 'rules', 'gap' => 8,
        ])->assertSuccessful();

        $before = $event->fresh()->scoreboard_settings;

        $this->actingAs($owner)
            ->postJson($this->endpoint($event), [
                'mat' => self::MAT, 'command' => 'rules', 'senshuRule' => 'perhaps',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('senshuRule');

        $this->assertSame($before, $event->fresh()->scoreboard_settings);
        $this->assertResultUntouched($match);
    }

    public function test_a_bout_length_outside_the_accepted_range_is_rejected_and_saves_nothing(): void
    {
        // Locks 'seconds' => ['numeric','min:10','max:900'].
        [$owner, $event, $match] = $this->scenario();

        $this->actingAs($owner)->postJson($this->endpoint($event), [
            'mat' => self::MAT, 'command' => 'duration', 'seconds' => 120,
        ])->assertSuccessful();

        $before = $event->fresh()->scoreboard_settings;

        $this->actingAs($owner)
            ->postJson($this->endpoint($event), [
                'mat' => self::MAT, 'command' => 'duration', 'seconds' => 5000,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('seconds');

        $this->assertSame($before, $event->fresh()->scoreboard_settings);
        $this->assertSame(120.0, $this->reloadedState($event)->duration);
        $this->assertResultUntouched($match);
    }

    public function test_an_unauthorised_user_cannot_save_settings(): void
    {
        // Locks abort_unless($this->canScore($event), 403). A member of the same
        // club who is neither organiser nor appointed official: being able to see
        // an event must never imply being able to configure its mats.
        [, $event, $match] = $this->scenario();

        $stranger = $this->createUser();
        $stranger->memberClubs()->syncWithoutDetaching([$event->tenant_id => ['status' => 'active']]);

        $this->actingAs($stranger)
            ->postJson($this->endpoint($event), [
                'mat' => self::MAT, 'command' => 'rules', 'gap' => 2,
            ])
            ->assertForbidden();

        $this->assertNull($event->fresh()->scoreboard_settings, 'a refused request must save nothing at all');
        $this->assertSame(8, $this->reloadedState($event)->gap, 'the default is untouched');
        $this->assertResultUntouched($match);
    }

    /* ---------------------------------------------------------------
     * 6. Sport isolation
     * --------------------------------------------------------------- */

    public function test_a_taekwondo_event_cannot_use_the_karate_settings_endpoint(): void
    {
        // Locks abort_unless($event->sport === 'karate', 404) in the Karate
        // ScoreboardController::command(). The actor is the event's own
        // organiser, who passes canScore(), so the refusal isolates the sport
        // guard and nothing else.
        //
        // This matters beyond tidiness: MatState::SETTINGS is Karate's
        // vocabulary (senshu, the penalty ending), and Taekwondo's mat has no
        // such rules. Writing them onto a Taekwondo event would store a rule set
        // its own engine will never read.
        [$owner, $event, $match] = $this->scenario('taekwondo');

        $this->actingAs($owner)
            ->postJson($this->endpoint($event, 'karate'), [
                'mat' => self::MAT, 'command' => 'rules', 'senshuRule' => false, 'gap' => 3,
            ])
            ->assertNotFound();

        $this->assertNull($event->fresh()->scoreboard_settings, 'no Karate rule set may be written to a Taekwondo event');
        $this->assertResultUntouched($match);
    }

    public function test_a_karate_settings_command_is_refused_for_a_mat_the_event_does_not_run(): void
    {
        // Locks abort_unless($this->matExists($event, $data['mat']), 404).
        // Settings are per EVENT but are reached through a mat, so a made-up mat
        // must not become a way to write them.
        [$owner, $event, $match] = $this->scenario();

        $this->actingAs($owner)
            ->postJson($this->endpoint($event), [
                'mat' => 'Mat 99', 'command' => 'rules', 'gap' => 3,
            ])
            ->assertNotFound();

        $this->assertNull($event->fresh()->scoreboard_settings);
        $this->assertResultUntouched($match);
    }

    /* ---------------------------------------------------------------
     * 7. Saving settings never touches the result
     * --------------------------------------------------------------- */

    public function test_saving_settings_leaves_the_match_record_completely_untouched(): void
    {
        // The release's safety property in one test: configuring a mat is not
        // scoring it. Asserted against every column of the official record that
        // a result would move.
        [$owner, $event, $match] = $this->scenario();

        $before = $match->fresh()->only(['status', 'winner', 'a_score', 'b_score', 'a_name', 'b_name', 'court']);

        foreach ([
            ['command' => 'rules', 'gap' => 5, 'senshuRule' => false],
            ['command' => 'duration', 'seconds' => 90],
            ['command' => 'rules', 'winByPenalties' => false, 'warning' => 8],
        ] as $payload) {
            $this->actingAs($owner)
                ->postJson($this->endpoint($event), array_merge(['mat' => self::MAT], $payload))
                ->assertSuccessful();
        }

        $this->assertSame($before, $match->fresh()->only(array_keys($before)), 'settings must not disturb the match record');

        // …and the mat still has no bout, no score and no winner on it.
        $state = $this->reloadedState($event);
        $this->assertNull($state->matchId);
        $this->assertSame(0, $state->akaScore);
        $this->assertSame(0, $state->aoScore);
        $this->assertNull($state->winner);
        $this->assertFalse($state->finished);
    }
}
