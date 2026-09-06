<?php

namespace Tests\Feature\Events;

use App\Scoreboard\Sports\Karate\Mat\Scoring as KarateScoring;
use App\Models\ClubEvent;
use App\Models\ClubEventRegistration;
use App\Models\EventCategory;
use App\Models\EventMatch;
use App\Models\EventMatchEvent;
use App\Clubs\Models\Tenant;
use App\Members\Models\User;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The officiating timeline — what the mat did, in the order it did it.
 *
 * Two things are being proven here and the second matters more than the first:
 *
 *   1. Every command that reaches Scoring::apply() lands in the log, carrying
 *      the running score as of that moment and a neutral side.
 *   2. The log can fail in any way at all and the bout carries on. A referee
 *      pressing a button mid-bout must never be shown an error because an
 *      audit row could not be written.
 */
class MatchEventLogTest extends TestCase
{
    private Tenant $club;

    private User $organiser;

    private ClubEvent $event;

    private EventMatch $match;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organiser = $this->createUser(['full_name' => 'Sensei Ito']);
        $this->club = $this->createClub($this->organiser, ['country' => 'BH']);

        $this->event = ClubEvent::create([
            'tenant_id'  => $this->club->id,
            'created_by' => $this->organiser->id,
            'title'      => 'Karate Open',
            'event_type' => 'championship',
            'sport'      => 'karate',
            'scope'      => 'internal',
            'date'       => now()->toDateString(),
            'start_time' => '09:00',
            'status'     => 'active',
        ]);

        $category = EventCategory::create([
            'event_id' => $this->event->id,
            'name'     => 'Men -75 kg',
            'capacity' => 8,
        ]);

        $this->match = EventMatch::create([
            'event_id'         => $this->event->id,
            'category_id'      => $category->id,
            'round'            => 'Final',
            'slot'             => 0,
            'a_name'           => 'Red Fighter',
            'b_name'           => 'Blue Fighter',
            'a_competitor_id'  => $this->registration('Red Fighter', $category->id)->id,
            'b_competitor_id'  => $this->registration('Blue Fighter', $category->id)->id,
            'court'            => '1',
            'status'           => 'upcoming',
        ]);
    }

    private function registration(string $name, int $categoryId): ClubEventRegistration
    {
        return ClubEventRegistration::create([
            'event_id'    => $this->event->id,
            'user_id'     => $this->createUser(['full_name' => $name])->id,
            'category_id' => $categoryId,
            'status'      => 'joined',
            'role'        => 'participant',
        ]);
    }

    private function scoring(): KarateScoring
    {
        return app(KarateScoring::class);
    }

    /** Put the bout on the mat and start it, as an official would. */
    private function loadAndStart(): void
    {
        $this->scoring()->apply($this->event, '1', 'load', ['match_id' => $this->match->id]);
        $this->scoring()->apply($this->event, '1', 'start', []);
    }

    // ── What gets recorded ──────────────────────────────────────────────────

    public function test_loading_a_bout_is_recorded_against_that_bout(): void
    {
        $this->scoring()->apply($this->event, '1', 'load', ['match_id' => $this->match->id]);

        $row = EventMatchEvent::firstOrFail();

        $this->assertSame('load', $row->command);
        $this->assertSame('karate', $row->sport);
        $this->assertSame('1', $row->court);
        $this->assertSame($this->match->id, $row->match_id);
        $this->assertSame($this->event->id, $row->event_id);
        $this->assertNotNull($row->occurred_at);
    }

    public function test_a_point_records_its_side_and_the_running_score(): void
    {
        $this->loadAndStart();

        $this->scoring()->apply($this->event, '1', 'point', ['side' => 'aka', 'n' => 2]);

        $row = EventMatchEvent::where('command', 'point')->firstOrFail();

        // aka is side 'a', matching event_matches — never the sport's own word.
        $this->assertSame('a', $row->side);
        $this->assertSame(2, $row->points);
        $this->assertSame(2, $row->score_a);
        $this->assertSame(0, $row->score_b);
    }

    public function test_the_running_score_accumulates_across_commands(): void
    {
        $this->loadAndStart();

        $this->scoring()->apply($this->event, '1', 'point', ['side' => 'aka', 'n' => 2]);
        $this->scoring()->apply($this->event, '1', 'point', ['side' => 'ao', 'n' => 3]);
        $this->scoring()->apply($this->event, '1', 'point', ['side' => 'aka', 'n' => 1]);

        $last = EventMatchEvent::orderByDesc('id')->firstOrFail();

        $this->assertSame('a', $last->side);
        $this->assertSame(3, $last->score_a);
        $this->assertSame(3, $last->score_b);
    }

    public function test_taking_a_point_back_is_recorded_as_its_own_row(): void
    {
        $this->loadAndStart();

        $this->scoring()->apply($this->event, '1', 'point', ['side' => 'aka', 'n' => 2]);
        $this->scoring()->apply($this->event, '1', 'undo_point', ['side' => 'aka', 'n' => 2]);

        // The correction is history too — the earlier row is never edited away.
        $this->assertSame(1, EventMatchEvent::where('command', 'point')->count());
        $this->assertSame(1, EventMatchEvent::where('command', 'undo_point')->count());
        $this->assertSame(0, EventMatchEvent::orderByDesc('id')->first()->score_a);
    }

    public function test_the_timeline_is_ordered_as_it_happened(): void
    {
        $this->loadAndStart();
        $this->scoring()->apply($this->event, '1', 'point', ['side' => 'aka', 'n' => 1]);
        $this->scoring()->apply($this->event, '1', 'pause', []);

        $commands = EventMatchEvent::forMatch($this->match->id)->pluck('command')->all();

        $this->assertSame(['load', 'start', 'point', 'pause'], $commands);
    }

    public function test_sequence_advances_per_mat(): void
    {
        $this->loadAndStart();

        $sequences = EventMatchEvent::orderBy('id')->pluck('sequence')->all();

        $this->assertSame([1, 2], $sequences);
    }

    public function test_the_payload_is_kept_verbatim(): void
    {
        $this->scoring()->apply($this->event, '1', 'load', ['match_id' => $this->match->id, 'minutes' => 3]);

        $row = EventMatchEvent::firstOrFail();

        $this->assertSame($this->match->id, $row->payload['match_id']);
        $this->assertSame(3, $row->payload['minutes']);
    }

    // ── The kill switch ─────────────────────────────────────────────────────

    public function test_nothing_is_recorded_when_the_log_is_switched_off(): void
    {
        // Key renamed from play.event_log when the Play platform was disconnected (2026-08-27).
        config(['events.match_log' => false]);

        $this->scoring()->apply($this->event, '1', 'load', ['match_id' => $this->match->id]);

        $this->assertSame(0, EventMatchEvent::count());
    }

    public function test_scoring_still_works_when_the_log_is_switched_off(): void
    {
        // Key renamed from play.event_log when the Play platform was disconnected (2026-08-27).
        config(['events.match_log' => false]);

        $this->scoring()->apply($this->event, '1', 'load', ['match_id' => $this->match->id]);
        $this->scoring()->apply($this->event, '1', 'start', []);
        $state = $this->scoring()->apply($this->event, '1', 'point', ['side' => 'aka', 'n' => 3]);

        $this->assertSame(3, $state->akaScore);
    }

    // ── The one that matters: the log must never stop a mat ─────────────────

    public function test_a_broken_log_does_not_stop_the_bout(): void
    {
        $this->loadAndStart();

        // The most total failure available: the table is gone underneath a live
        // mat. Scoring must not notice.
        Schema::drop('event_match_events');

        $state = $this->scoring()->apply($this->event, '1', 'point', ['side' => 'aka', 'n' => 2]);

        $this->assertSame(2, $state->akaScore, 'The point must still be scored.');

        $state = $this->scoring()->apply($this->event, '1', 'point', ['side' => 'ao', 'n' => 3]);

        $this->assertSame(3, $state->aoScore, 'And the bout must carry on.');
    }
}
