<?php

namespace Tests\Feature\Events;

use App\Events\Models\ClubEvent;
use App\Events\Models\ClubEventRegistration;
use App\Events\Models\EventCategory;
use App\Events\Models\EventMatch;
use App\Events\Support\Tournament\DrawRepair;
use Tests\TestCase;

/**
 * The draw tidies itself after a hand edit (App\Events\Support\Tournament\DrawRepair).
 *
 * What this protects: a lone fighter nothing can ever join takes their bye and
 * loses the bout number; a winner fills an EMPTY next corner; a bout still
 * waiting on an earlier one, a bout with a score, and a draw not yet started
 * are left exactly as they are; and any saved bout queues the repair itself.
 */
class DrawRepairTest extends TestCase
{
    private ClubEvent $event;
    private EventCategory $cat;
    /** @var array<string, ClubEventRegistration> */
    private array $p = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['events.draw_self_repair' => true, 'events.renumber_mats' => true]);
        $owner = $this->createUser();
        $club = $this->createClub($owner, ['country' => 'BH', 'currency' => 'BHD']);
        $this->event = ClubEvent::create([
            'tenant_id' => $club->id, 'created_by' => $owner->id,
            'title' => 'Repair Open', 'event_type' => 'championship', 'sport' => 'karate',
            'scope' => 'internal', 'status' => 'active', 'is_archived' => false,
            'date' => now()->toDateString(), 'start_time' => '09:00', 'end_time' => '17:00',
        ]);
        $this->event->forceFill(['started_at' => now()])->save();
        $this->cat = EventCategory::create(['event_id' => $this->event->id, 'name' => 'Under 12 Female -35 kg', 'sort_order' => 1]);
        foreach (['A', 'B', 'C', 'D'] as $n) {
            $this->p[$n] = ClubEventRegistration::create(['event_id' => $this->event->id, 'user_id' => $this->createUser()->id,
                'category_id' => $this->cat->id, 'role' => 'participant', 'status' => 'registered']);
        }
    }

    private function bout(int $slot, string $round, ?string $a, ?string $b, ?int $no, array $extra = []): EventMatch
    {
        return EventMatch::create($extra + [
            'event_id' => $this->event->id, 'category_id' => $this->cat->id, 'round' => $round, 'phase' => strtolower($round),
            'slot' => $slot, 'match_no' => $no, 'court' => 'Mat 1', 'status' => 'upcoming',
            'a_competitor_id' => $a ? $this->p[$a]->id : null, 'a_name' => $a,
            'b_competitor_id' => $b ? $this->p[$b]->id : null, 'b_name' => $b,
        ]);
    }

    private function repair(): array
    {
        return app(DrawRepair::class)->repair($this->cat->fresh());
    }

    /** The case from the Premier League: a semi-final emptied to one fighter, the final already fought. */
    public function test_a_first_round_bout_left_with_one_fighter_becomes_their_bye_and_the_mat_closes_up(): void
    {
        $sf1 = $this->bout(0, 'Semifinal', 'A', 'B', 1, ['winner' => 'b', 'status' => 'done', 'a_score' => '0', 'b_score' => '6']);
        $sf2 = $this->bout(1, 'Semifinal', 'C', null, 2);
        $final = $this->bout(2, 'Final', 'B', 'C', 3, ['winner' => 'a', 'status' => 'done', 'a_score' => '6', 'b_score' => '2']);

        $this->assertNotSame([], $this->repair());

        $sf2->refresh();
        $this->assertSame(['a', true, 'done', null], [$sf2->winner, $sf2->won_by_bye, $sf2->status, $sf2->match_no]);
        $this->assertSame([1, 2], [(int) $sf1->fresh()->match_no, (int) $final->fresh()->match_no]);
        // The fought final is untouched.
        $this->assertSame(['a', '6', '2'], [$final->fresh()->winner, $final->fresh()->a_score, $final->fresh()->b_score]);
    }

    public function test_a_bye_with_a_number_loses_it_and_its_winner_fills_an_empty_next_corner(): void
    {
        $sf1 = $this->bout(0, 'Semifinal', 'A', null, 1, ['winner' => 'a', 'status' => 'done', 'won_by_bye' => true]);
        $sf2 = $this->bout(1, 'Semifinal', 'C', 'D', 2);
        $final = $this->bout(2, 'Final', null, null, 3);

        $this->repair();

        $this->assertNull($sf1->fresh()->match_no);
        $this->assertSame($this->p['A']->id, $final->fresh()->a_competitor_id);
        $this->assertSame([1, 2], [(int) $sf2->fresh()->match_no, (int) $final->fresh()->match_no]);
    }

    public function test_a_bout_still_waiting_on_an_earlier_one_a_scored_bout_and_an_occupied_corner_are_left_alone(): void
    {
        $this->bout(0, 'Semifinal', 'A', 'B', 1);                              // not fought yet
        $sf2 = $this->bout(1, 'Semifinal', 'C', null, 2, ['a_score' => '3']); // points on the board
        $final = $this->bout(2, 'Final', null, 'D', 3);                        // waits on sf1

        $this->assertSame([], $this->repair());
        $this->assertNull($sf2->fresh()->winner);
        $this->assertNull($final->fresh()->winner);
        $this->assertSame($this->p['D']->id, $final->fresh()->b_competitor_id);
    }

    /** A name typed by hand, with no account behind it, is a person: their bout is a real one. */
    public function test_a_hand_typed_entrant_is_never_mistaken_for_an_empty_corner(): void
    {
        $this->bout(0, 'Semifinal', 'A', 'B', 1);
        $sf2 = $this->bout(1, 'Semifinal', 'C', null, 2, ['b_name' => 'Walk-in Fighter']);
        $this->bout(2, 'Final', null, null, 3);

        $this->assertSame([], $this->repair());
        $this->assertSame([null, 2], [$sf2->fresh()->winner, (int) $sf2->fresh()->match_no]);
    }

    /** A fighter added into a bye's empty corner makes it a real bout again: numbered, and the bye-holder steps back. */
    public function test_a_bye_that_gains_an_opponent_is_reopened_numbered_and_its_holder_steps_back(): void
    {
        $sf1 = $this->bout(0, 'Semifinal', 'A', null, null, ['winner' => 'a', 'status' => 'done', 'won_by_bye' => true]);
        $sf2 = $this->bout(1, 'Semifinal', 'C', 'D', 1);
        $final = $this->bout(2, 'Final', 'A', null, 2);

        // An organiser drops B into the empty corner (free move).
        $sf1->update(['b_competitor_id' => $this->p['B']->id, 'b_name' => 'B']);
        $this->repair();

        $sf1->refresh();
        $this->assertSame([null, false, 'upcoming'], [$sf1->winner, $sf1->won_by_bye, $sf1->status]);
        // Placed in its division's order on the mat: before the other semi-final.
        $this->assertSame([1, 2, 3], [(int) $sf1->match_no, (int) $sf2->fresh()->match_no, (int) $final->fresh()->match_no]);
        // A no longer waits in the final — they have a semi-final to fight first.
        $this->assertNull($final->fresh()->a_competitor_id);
        $this->assertNull($final->fresh()->a_name);
    }

    public function test_a_bye_whose_holder_has_already_fought_on_is_left_alone(): void
    {
        $sf1 = $this->bout(0, 'Semifinal', 'A', null, null, ['winner' => 'a', 'status' => 'done', 'won_by_bye' => true]);
        $this->bout(1, 'Semifinal', 'C', 'D', 1, ['winner' => 'a', 'status' => 'done', 'a_score' => '3', 'b_score' => '1']);
        $final = $this->bout(2, 'Final', 'A', 'C', 2, ['winner' => 'a', 'status' => 'done', 'a_score' => '4', 'b_score' => '2']);

        $sf1->update(['b_competitor_id' => $this->p['B']->id, 'b_name' => 'B']);
        $this->repair();

        $this->assertSame(['a', true], [$sf1->fresh()->winner, $sf1->fresh()->won_by_bye]);
        $this->assertSame($this->p['A']->id, $final->fresh()->a_competitor_id);
    }

    public function test_nothing_happens_before_the_competition_starts_or_with_the_switch_off(): void
    {
        $this->bout(0, 'Semifinal', 'A', 'B', 1);
        $sf2 = $this->bout(1, 'Semifinal', 'C', null, 2);
        $this->bout(2, 'Final', null, null, 3);

        $this->event->forceFill(['started_at' => null])->save();
        $this->assertSame([], $this->repair());
        $this->assertNull($sf2->fresh()->winner);

        $this->event->forceFill(['started_at' => now()])->save();
        config(['events.draw_self_repair' => false]);
        $sf2->update(['a_name' => 'C.']);
        DrawRepair::flush();
        $this->assertNull($sf2->fresh()->winner);
    }

    public function test_any_saved_bout_queues_the_repair_of_its_division(): void
    {
        $this->bout(0, 'Semifinal', 'A', 'B', 1);
        $sf2 = $this->bout(1, 'Semifinal', 'C', 'D', 2);
        $this->bout(2, 'Final', null, null, 3);
        DrawRepair::flush();

        // A hand edit drags D out of the semi-final, the way free move does.
        $sf2->update(['b_competitor_id' => null, 'b_name' => null]);
        DrawRepair::flush();

        $this->assertSame(['a', null], [$sf2->fresh()->winner, $sf2->fresh()->match_no]);
        $this->assertSame($this->p['C']->id, EventMatch::where('round', 'Final')->first()->b_competitor_id);
    }
}
