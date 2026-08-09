<?php

namespace Tests\Feature\Events;

use App\Models\ClubEvent;
use App\Models\ClubEventRegistration;
use App\Models\EventCategory;
use App\Models\EventMatch;
use App\Models\HealthRecord;
use App\Models\Tenant;
use App\Models\User;
use Tests\TestCase;

/**
 * Hand-arranging a draw before a championship starts.
 *
 * The rules that matter: an organiser may move competitors around their own
 * first round, nobody else may touch it, and the moment the event starts the
 * draw is final. A hand-arranged draw is also never silently re-cut by the
 * auto-draw engine — that would throw away the matchups the organiser
 * deliberately set.
 */
class BracketArrangementTest extends TestCase
{
    private Tenant $club;

    private User $organiser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organiser = $this->createUser(['full_name' => 'Master Kim']);
        $this->club = $this->createClub($this->organiser, ['country' => 'BH', 'currency' => 'BHD']);
        $this->organiser->memberClubs()->syncWithoutDetaching([$this->club->id => ['status' => 'active']]);
    }

    private function event(array $attrs = []): ClubEvent
    {
        return ClubEvent::create(array_merge([
            'tenant_id' => $this->club->id,
            'created_by' => $this->organiser->id,
            'title' => 'Spring Open',
            'event_type' => 'championship',
            'sport' => 'taekwondo',
            'scope' => 'internal',
            'date' => now()->addWeeks(2)->toDateString(),
            'end_date' => now()->addWeeks(2)->toDateString(),
            'start_time' => '09:00',
            'end_time' => '17:00',
            'status' => 'active',
            'is_archived' => false,
        ], $attrs));
    }

    private function athlete(string $name, float $weight = 57): User
    {
        $user = $this->createUser([
            'full_name' => $name,
            'gender' => 'Male',
            'birthdate' => now()->subYears(25)->toDateString(),
        ]);
        $user->memberClubs()->syncWithoutDetaching([$this->club->id => ['status' => 'active']]);
        HealthRecord::create(['user_id' => $user->id, 'weight' => $weight, 'recorded_at' => now()]);

        return $user->fresh();
    }

    /** A division with four entrants and a generated draw. */
    private function drawnDivision(ClubEvent $event, int $entrants = 4): EventCategory
    {
        $category = EventCategory::create([
            'event_id' => $event->id, 'name' => 'Senior Men -58 kg', 'sort_order' => 1,
        ]);

        foreach (range(1, $entrants) as $i) {
            $athlete = $this->athlete('Athlete '.$i);
            ClubEventRegistration::create([
                'event_id' => $event->id,
                'user_id' => $athlete->id,
                'category_id' => $category->id,
                'role' => 'participant',
                'paid' => true,
                'weight' => 57,
            ]);
        }

        $this->actingAs($this->organiser)
            ->postJson("/me/events/{$event->uuid}/actions/generate_draw")
            ->assertOk();

        return $category->fresh();
    }

    private function firstRound(EventCategory $category)
    {
        return $category->matches()->orderBy('slot')->get()
            ->groupBy('round')
            ->sortBy(fn ($bouts) => $bouts->min('slot'))
            ->first()->sortBy('slot')->values();
    }

    /* ---------------- The happy path ---------------- */

    public function test_an_organiser_swaps_two_competitors_between_slots(): void
    {
        $event = $this->event();
        $category = $this->drawnDivision($event);
        $round = $this->firstRound($category);

        [$boutOne, $boutTwo] = [$round[0], $round[1]];
        $movedName = $boutOne->a_name;
        $displacedName = $boutTwo->b_name;

        $this->actingAs($this->organiser)
            ->putJson("/me/events/{$event->uuid}/brackets/arrange", [
                'category_id' => $category->id,
                'from' => ['type' => 'slot', 'match_id' => $boutOne->id, 'side' => 'a'],
                'to' => ['type' => 'slot', 'match_id' => $boutTwo->id, 'side' => 'b'],
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSame($movedName, $boutTwo->fresh()->b_name, 'the moved competitor took the target slot');
        $this->assertSame($displacedName, $boutOne->fresh()->a_name, 'the displaced competitor took the vacated slot');
    }

    public function test_a_competitor_can_be_taken_out_of_the_draw_and_put_back(): void
    {
        $event = $this->event();
        $category = $this->drawnDivision($event);
        $bout = $this->firstRound($category)[0];
        $competitorId = $bout->a_competitor_id;

        // Out — onto the bench.
        $this->actingAs($this->organiser)
            ->putJson("/me/events/{$event->uuid}/brackets/arrange", [
                'category_id' => $category->id,
                'from' => ['type' => 'slot', 'match_id' => $bout->id, 'side' => 'a'],
                'to' => ['type' => 'bench'],
            ])
            ->assertOk();

        $this->assertNull($bout->fresh()->a_name);
        $this->assertNull($bout->fresh()->a_competitor_id);

        // Their opponent is now alone in the bout — that is a bye, and it wins.
        $this->assertSame('b', $bout->fresh()->winner);
        $this->assertSame('done', $bout->fresh()->status);

        // And back in again, into a different slot.
        $other = $this->firstRound($category->fresh())[1];
        $this->actingAs($this->organiser)
            ->putJson("/me/events/{$event->uuid}/brackets/arrange", [
                'category_id' => $category->id,
                'from' => ['type' => 'bench', 'competitor_id' => $competitorId],
                'to' => ['type' => 'slot', 'match_id' => $bout->id, 'side' => 'a'],
            ])
            ->assertOk();

        $this->assertSame($competitorId, $bout->fresh()->a_competitor_id);
        $this->assertNull($bout->fresh()->winner, 'a contested bout has no winner again');
        unset($other);
    }

    public function test_clearing_a_draw_empties_every_slot_onto_the_bench(): void
    {
        $event = $this->event();
        $category = $this->drawnDivision($event);

        $this->actingAs($this->organiser)
            ->putJson("/me/events/{$event->uuid}/brackets/clear", ['category_id' => $category->id])
            ->assertOk()
            ->assertJson(['success' => true]);

        foreach ($this->firstRound($category->fresh()) as $bout) {
            $this->assertNull($bout->a_name);
            $this->assertNull($bout->b_name);
            $this->assertNull($bout->winner);
        }

        // Everyone is back on the bench, ready to be placed by hand.
        $this->assertCount(4, $this->bracketData($event)['divisions'][0]['bench']);
    }

    /* ---------------- The rules ---------------- */

    public function test_a_non_organiser_cannot_arrange_the_draw(): void
    {
        $event = $this->event();
        $category = $this->drawnDivision($event);
        $bout = $this->firstRound($category)[0];

        $intruder = $this->athlete('Nosy Parker');

        $this->actingAs($intruder)
            ->putJson("/me/events/{$event->uuid}/brackets/arrange", [
                'category_id' => $category->id,
                'from' => ['type' => 'slot', 'match_id' => $bout->id, 'side' => 'a'],
                'to' => ['type' => 'slot', 'match_id' => $bout->id, 'side' => 'b'],
            ])
            ->assertForbidden();
    }

    public function test_the_draw_is_final_once_the_championship_has_started(): void
    {
        $event = $this->event();
        $category = $this->drawnDivision($event);
        $bout = $this->firstRound($category)[0];

        // The event is under way — nobody's opponent changes now. Backdating no
        // longer does this: an event runs because someone STARTED it, not
        // because the clock passed its start time.
        $event->update(['date' => now()->subDay()->toDateString(), 'end_date' => now()->addDay()->toDateString()]);
        $event->forceFill(['started_at' => now(), 'started_by' => $this->organiser->id])->save();

        $before = $bout->fresh()->a_name;

        $this->actingAs($this->organiser)
            ->putJson("/me/events/{$event->uuid}/brackets/arrange", [
                'category_id' => $category->id,
                'from' => ['type' => 'slot', 'match_id' => $bout->id, 'side' => 'a'],
                'to' => ['type' => 'bench'],
            ])
            ->assertStatus(422);

        $this->assertSame($before, $bout->fresh()->a_name);
    }

    public function test_a_later_round_cannot_be_arranged(): void
    {
        $event = $this->event();
        $category = $this->drawnDivision($event);
        $first = $this->firstRound($category);

        // The final: derived from the semi-finals, never placed by hand.
        // (reorder() first — the matches relation already sorts by slot.)
        $final = $category->matches()->reorder()->orderByDesc('slot')->first();
        $this->assertSame('Final', $final->round);

        $this->actingAs($this->organiser)
            ->putJson("/me/events/{$event->uuid}/brackets/arrange", [
                'category_id' => $category->id,
                'from' => ['type' => 'slot', 'match_id' => $first[0]->id, 'side' => 'a'],
                'to' => ['type' => 'slot', 'match_id' => $final->id, 'side' => 'a'],
            ])
            ->assertStatus(422);

        $this->assertNull($final->fresh()->a_name);
    }

    public function test_a_competitor_from_another_division_cannot_be_dropped_in(): void
    {
        $event = $this->event();
        $category = $this->drawnDivision($event);
        $bout = $this->firstRound($category)[0];

        // An entrant of a DIFFERENT division of the same event.
        $other = EventCategory::create(['event_id' => $event->id, 'name' => 'Senior Men -68 kg', 'sort_order' => 2]);
        $outsider = $this->athlete('Wrong Weight', 66);
        $entry = ClubEventRegistration::create([
            'event_id' => $event->id, 'user_id' => $outsider->id, 'category_id' => $other->id,
            'role' => 'participant', 'paid' => true, 'weight' => 66,
        ]);

        // Empty a slot first so there is somewhere to drop them.
        $this->actingAs($this->organiser)
            ->putJson("/me/events/{$event->uuid}/brackets/arrange", [
                'category_id' => $category->id,
                'from' => ['type' => 'slot', 'match_id' => $bout->id, 'side' => 'a'],
                'to' => ['type' => 'bench'],
            ])->assertOk();

        $this->actingAs($this->organiser)
            ->putJson("/me/events/{$event->uuid}/brackets/arrange", [
                'category_id' => $category->id,
                'from' => ['type' => 'bench', 'competitor_id' => $entry->id],
                'to' => ['type' => 'slot', 'match_id' => $bout->id, 'side' => 'a'],
            ])
            ->assertStatus(422);

        $this->assertNull($bout->fresh()->a_competitor_id);
    }

    public function test_the_same_competitor_cannot_stand_in_two_slots(): void
    {
        $event = $this->event();
        $category = $this->drawnDivision($event);
        $round = $this->firstRound($category);

        // Already in the draw, so the bench path must refuse them.
        $this->actingAs($this->organiser)
            ->putJson("/me/events/{$event->uuid}/brackets/arrange", [
                'category_id' => $category->id,
                'from' => ['type' => 'bench', 'competitor_id' => $round[0]->a_competitor_id],
                'to' => ['type' => 'slot', 'match_id' => $round[1]->id, 'side' => 'a'],
            ])
            ->assertStatus(422);

        $this->assertSame(
            4,
            EventMatch::where('category_id', $category->id)->get()
                ->flatMap(fn (EventMatch $m) => $m->competitorIds())->unique()->count(),
            'still four distinct competitors in the draw',
        );
    }

    public function test_a_hand_arranged_draw_is_not_re_cut_by_the_auto_draw(): void
    {
        $event = $this->event();
        $category = $this->drawnDivision($event);
        $bout = $this->firstRound($category)[0];

        $this->actingAs($this->organiser)
            ->putJson("/me/events/{$event->uuid}/brackets/arrange", [
                'category_id' => $category->id,
                'from' => ['type' => 'slot', 'match_id' => $bout->id, 'side' => 'a'],
                'to' => ['type' => 'bench'],
            ])->assertOk();

        $this->assertSame('manual', $category->fresh()->draw_state);
        $arranged = $bout->fresh()->b_name;

        // A newcomer enters. The draw must survive it — they wait on the bench.
        $newcomer = $this->athlete('Late Entry');
        ClubEventRegistration::create([
            'event_id' => $event->id, 'user_id' => $newcomer->id, 'category_id' => $category->id,
            'role' => 'participant', 'paid' => true, 'weight' => 57,
        ]);

        // Viewing the bracket is what brings derived state up to date.
        $this->actingAs($this->organiser)->get("/me/events/{$event->uuid}/brackets")->assertOk();

        $this->assertSame('manual', $category->fresh()->draw_state);
        $this->assertNull($bout->fresh()->a_name, 'the emptied slot stayed empty');
        $this->assertSame($arranged, $bout->fresh()->b_name, 'the arranged matchup survived');
    }

    public function test_a_withdrawn_competitor_is_lifted_out_of_a_hand_arranged_draw(): void
    {
        $event = $this->event();
        $category = $this->drawnDivision($event);
        $bout = $this->firstRound($category)[0];

        // Make it a manual draw.
        $this->actingAs($this->organiser)
            ->putJson("/me/events/{$event->uuid}/brackets/arrange", [
                'category_id' => $category->id,
                'from' => ['type' => 'slot', 'match_id' => $bout->id, 'side' => 'a'],
                'to' => ['type' => 'bench'],
            ])->assertOk();

        $second = $this->firstRound($category->fresh())[1];
        $withdrawnId = $second->a_competitor_id;
        ClubEventRegistration::whereKey($withdrawnId)->delete();

        $this->actingAs($this->organiser)->get("/me/events/{$event->uuid}/brackets")->assertOk();

        $this->assertNull($second->fresh()->a_competitor_id, 'the withdrawn competitor left their slot');
    }

    /* ---------------- The data feed ---------------- */

    public function test_the_bracket_feed_returns_rounds_and_the_bench(): void
    {
        $event = $this->event();
        $this->drawnDivision($event);

        $data = $this->bracketData($event);

        $this->assertTrue($data['can_arrange'], 'the organiser may arrange before the event starts');
        $this->assertFalse($data['locked']);
        $this->assertCount(2, $data['divisions'][0]['rounds'], 'four entrants → semi-finals and a final');
        $this->assertSame([], $data['divisions'][0]['bench'], 'everyone is in the draw');
    }

    public function test_a_viewer_who_cannot_manage_is_not_told_they_can_arrange(): void
    {
        $event = $this->event();
        $this->drawnDivision($event);

        $watcher = $this->athlete('Spectator');

        $this->actingAs($watcher)
            ->getJson("/me/events/{$event->uuid}/brackets/data")
            ->assertOk()
            ->assertJson(['can_arrange' => false]);
    }

    public function test_the_arrange_control_is_not_rendered_for_a_viewer_who_cannot_manage(): void
    {
        $event = $this->event();
        $this->drawnDivision($event);
        $watcher = $this->athlete('Spectator');

        // The URL is emitted through @json, which escapes forward slashes.
        $needle = trim(json_encode(route('me.events.bracket.arrange', $event->uuid)), '"');

        $this->actingAs($this->organiser)->get("/me/events/{$event->uuid}/brackets")
            ->assertOk()
            ->assertSee($needle, false);

        $this->actingAs($watcher)->get("/me/events/{$event->uuid}/brackets")
            ->assertOk()
            ->assertDontSee($needle, false);
    }

    private function bracketData(ClubEvent $event): array
    {
        return $this->actingAs($this->organiser)
            ->getJson("/me/events/{$event->uuid}/brackets/data")
            ->assertOk()
            ->json();
    }
}
