<?php

namespace Tests\Feature\Events;

use App\Clubs\Models\Tenant;
use App\Members\Models\HealthRecord;
use App\Members\Models\User;
use App\Events\Models\ClubEvent;
use App\Events\Models\ClubEventRegistration;
use App\Events\Models\EventCategory;
use App\Sports\Combat\Engine\DrawEngine;
use Tests\TestCase;

/**
 * Admitting an athlete to a division that is already being fought.
 *
 * The competition this was written for ran on 2026-09-18 and was finished by
 * hand, because an entered, eligible competitor who turned up for a division
 * three bouts deep could not be put on its board by any control the system
 * offered. The controls that could change a draw all re-cut it, and a re-cut
 * deletes results, so they were — correctly — withdrawn once the day began.
 *
 * Every test here asserts the same invariant from a different angle: a bout
 * that was fought is a fact, and admitting somebody never spends one.
 */
class LateEntryTest extends TestCase
{
    private Tenant $club;

    private User $organiser;

    protected function setUp(): void
    {
        parent::setUp();
        // Midday in Bahrain, so "today's" event is under way whatever time the
        // suite runs — late in the UTC evening it had already ended there.
        $this->travelTo(\Carbon\Carbon::parse('2026-06-15 09:00:00', 'UTC'));
        $this->organiser = $this->createUser(['full_name' => 'Master Kim']);
        $this->club = $this->createClub($this->organiser, ['country' => 'BH', 'currency' => 'BHD']);
        $this->organiser->memberClubs()->syncWithoutDetaching([$this->club->id => ['status' => 'active']]);
    }

    private function event(array $attrs = []): ClubEvent
    {
        return ClubEvent::create(array_merge([
            'tenant_id' => $this->club->id,
            'created_by' => $this->organiser->id,
            'title' => 'Victory Open',
            'event_type' => 'championship',
            'sport' => 'taekwondo',
            'scope' => 'internal',
            'date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '17:00',
            'status' => 'active',
            'is_archived' => false,
        ], $attrs));
    }

    private function athlete(string $name): User
    {
        $user = $this->createUser([
            'full_name' => $name,
            'gender' => 'Male',
            'birthdate' => now()->subYears(25)->toDateString(),
        ]);
        $user->memberClubs()->syncWithoutDetaching([$this->club->id => ['status' => 'active']]);
        HealthRecord::create(['user_id' => $user->id, 'weight' => 57, 'recorded_at' => now()]);

        return $user->fresh();
    }

    private function enter(ClubEvent $event, User $user, ?EventCategory $category): ClubEventRegistration
    {
        return ClubEventRegistration::create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'category_id' => $category?->id,
            'role' => 'participant',
            'paid' => true,
            'weight' => 57,
        ]);
    }

    /** A division with a generated draw, and the event under way. */
    private function runningDivision(ClubEvent $event, int $entrants): EventCategory
    {
        $category = EventCategory::create([
            'event_id' => $event->id, 'name' => 'Senior Men -58 kg', 'sort_order' => 1,
        ]);

        foreach (range(1, $entrants) as $i) {
            $this->enter($event, $this->athlete('Athlete '.$i), $category);
        }

        $this->actingAs($this->organiser)
            ->postJson("/me/events/{$event->uuid}/actions/generate_draw")
            ->assertOk();

        $event->update(['started_at' => now()]);

        return $category->fresh();
    }

    /** Fight the first contested bout in the division, and return it. */
    private function fightOne(ClubEvent $event, EventCategory $category)
    {
        $bout = $category->matches()->orderBy('slot')->get()
            ->first(fn ($m) => $m->a_name && $m->b_name);

        $this->actingAs($this->organiser)
            ->postJson("/me/events/{$event->uuid}/outcomes/{$bout->id}", [
                'winner' => 'a', 'a_score' => '7', 'b_score' => '2', 'status' => 'done',
            ])->assertOk();

        return $bout->fresh();
    }

    /** Every fought bout, as a comparable fingerprint. */
    private function fought(EventCategory $category): array
    {
        return $category->matches()->contested()->get()
            ->mapWithKeys(fn ($m) => [$m->id => $m->winner.'|'.$m->a_name.'|'.$m->b_name.'|'.$m->a_score.'|'.$m->b_score])
            ->all();
    }

    private function admit(ClubEvent $event, EventCategory $category, ClubEventRegistration $entry)
    {
        return $this->actingAs($this->organiser)
            ->putJson("/me/events/{$event->uuid}/brackets/admit", [
                'category_id' => $category->id,
                'competitor_id' => $entry->id,
            ]);
    }

    /* ---------------- The cases the hall actually produces ---------------- */

    public function test_a_late_athlete_takes_an_empty_corner_and_nothing_fought_changes(): void
    {
        $event = $this->event();
        // Three entrants in a bracket of four: one bout, one bye, one final.
        $category = $this->runningDivision($event, 3);
        $this->fightOne($event, $category);

        $before = $this->fought($category);
        $this->assertNotEmpty($before, 'the fixture must have fought something');

        $late = $this->enter($event, $this->athlete('Latecomer'), $category);

        $this->admit($event, $category, $late)->assertOk()->assertJson(['success' => true]);

        $this->assertSame($before, $this->fought($category), 'a fought bout was changed');

        $standing = $category->fresh()->matches()->get()
            ->first(fn ($m) => in_array($late->id, $m->competitorIds(), true));

        $this->assertNotNull($standing, 'the late athlete is not on the board');
    }

    public function test_a_full_bracket_grows_a_round_rather_than_refusing(): void
    {
        $event = $this->event();
        // Four entrants: a full bracket with no empty corner anywhere.
        $category = $this->runningDivision($event, 4);
        $this->fightOne($event, $category);

        $before = $this->fought($category);
        $boutsBefore = $category->matches()->count();

        $late = $this->enter($event, $this->athlete('Latecomer'), $category);

        $this->admit($event, $category, $late)->assertOk()->assertJson(['success' => true]);

        $this->assertSame($before, $this->fought($category), 'a fought bout was changed');
        $this->assertGreaterThan($boutsBefore, $category->fresh()->matches()->count(), 'the draw did not grow');

        $playIn = $category->fresh()->matches()->get()
            ->first(fn ($m) => in_array($late->id, $m->competitorIds(), true));

        $this->assertNotNull($playIn);
        $this->assertNull($playIn->winner, 'the late athlete was handed a result nobody fought');
        $this->assertNotNull($playIn->a_name);
        $this->assertNotNull($playIn->b_name, 'the play-in has no opponent in it');
    }

    public function test_an_entrant_with_no_division_at_all_can_be_admitted(): void
    {
        // The Victory BJJ case: entered in the championship, placed nowhere, and
        // therefore on no board and in nobody's list.
        $event = $this->event();
        $category = $this->runningDivision($event, 3);
        $this->fightOne($event, $category);

        $orphan = $this->enter($event, $this->athlete('Nobody Placed Me'), null);

        $this->admit($event, $category, $orphan)->assertOk()->assertJson(['success' => true]);

        $this->assertSame($category->id, $orphan->fresh()->category_id);
        $this->assertTrue(
            $category->fresh()->matches()->get()
                ->contains(fn ($m) => in_array($orphan->id, $m->competitorIds(), true)),
            'the orphaned entrant never reached the board'
        );
    }

    public function test_the_picker_lists_the_unplaced_first_and_never_someone_already_drawn(): void
    {
        $event = $this->event();
        $category = $this->runningDivision($event, 3);

        $orphan = $this->enter($event, $this->athlete('Nobody Placed Me'), null);

        $body = $this->actingAs($this->organiser)
            ->getJson("/me/events/{$event->uuid}/brackets/admit-candidates?category_id={$category->id}")
            ->assertOk()->json();

        $ids = array_column($body['candidates'], 'id');

        $this->assertContains($orphan->id, $ids);
        $this->assertSame($orphan->id, $ids[0], 'the unplaced entrant is not at the top');

        $drawn = $category->matches()->get()->flatMap(fn ($m) => $m->competitorIds())->all();
        $this->assertEmpty(array_intersect($drawn, $ids), 'someone already on the board was offered');
    }

    public function test_a_finished_division_says_so_rather_than_pretending_it_is_full(): void
    {
        $event = $this->event();
        $category = $this->runningDivision($event, 2);   // one bout: the final

        $this->fightOne($event, $category);

        $late = $this->enter($event, $this->athlete('Latecomer'), $category);

        $this->admit($event, $category, $late)
            ->assertStatus(422)
            ->assertJson(['success' => false, 'message' => __('events::events.late_entry_division_over')]);
    }

    public function test_a_stranger_cannot_admit_anybody(): void
    {
        $event = $this->event();
        $category = $this->runningDivision($event, 3);
        $late = $this->enter($event, $this->athlete('Latecomer'), $category);

        $stranger = $this->createUser(['full_name' => 'Passing Stranger']);

        $this->actingAs($stranger)
            ->putJson("/me/events/{$event->uuid}/brackets/admit", [
                'category_id' => $category->id, 'competitor_id' => $late->id,
            ])->assertForbidden();

        $this->actingAs($stranger)
            ->getJson("/me/events/{$event->uuid}/brackets/admit-candidates?category_id={$category->id}")
            ->assertForbidden();
    }

    /* ---------------- The landmine underneath all of it ---------------- */

    public function test_the_draw_engine_refuses_to_re_cut_a_division_that_has_been_fought(): void
    {
        /*
         * `build()` deletes a division's bouts and lays new ones. Its guard used
         * to live in `ensure()`, and only on the branch where the event had
         * STARTED — which is a button somebody presses, not a fact about whether
         * anybody has fought. An organiser who never pressed it had no guard at
         * all: adding an entrant could delete a morning's results.
         */
        $event = $this->event();
        $category = $this->runningDivision($event, 4);
        $this->fightOne($event, $category);

        $before = $this->fought($category);
        $count = $category->matches()->count();

        app(DrawEngine::class)->build($event, $category->fresh(), paidOnly: false);

        $this->assertSame($before, $this->fought($category), 'a re-cut spent a result');
        $this->assertSame($count, $category->fresh()->matches()->count());
    }
}
