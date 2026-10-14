<?php

namespace Tests\Feature\Events;

use App\Clubs\Models\Tenant;
use App\Events\Models\ClubEvent;
use App\Events\Models\ClubEventRegistration;
use App\Events\Models\EventCategory;
use App\Members\Models\HealthRecord;
use App\Members\Models\User;
use App\Sports\Combat\Engine\DrawEngine;
use Tests\TestCase;

/**
 * A division of ONE competitor gets a board: a final with an empty corner,
 * and dropping somebody on a division with no board lays that final
 * (events.lone_entrant_final; DrawEngine::build, Tournament\LoneFinal).
 */
class LoneEntrantFinalTest extends TestCase
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

    private function event(string $sport = 'taekwondo'): ClubEvent
    {
        return ClubEvent::create([
            'tenant_id' => $this->club->id, 'created_by' => $this->organiser->id, 'title' => 'Spring Open',
            'event_type' => 'championship', 'sport' => $sport, 'scope' => 'internal',
            'date' => now()->addWeeks(2)->toDateString(), 'end_date' => now()->addWeeks(2)->toDateString(),
            'start_time' => '09:00', 'end_time' => '17:00', 'status' => 'active', 'is_archived' => false,
        ]);
    }

    private function enter(ClubEvent $event, EventCategory $category, string $name): ClubEventRegistration
    {
        $user = $this->createUser(['full_name' => $name, 'gender' => 'Male', 'birthdate' => now()->subYears(25)->toDateString()]);
        $user->memberClubs()->syncWithoutDetaching([$this->club->id => ['status' => 'active']]);
        HealthRecord::create(['user_id' => $user->id, 'weight' => 57, 'recorded_at' => now()]);

        return ClubEventRegistration::create([
            'event_id' => $event->id, 'user_id' => $user->id, 'category_id' => $category->id,
            'role' => 'participant', 'paid' => true, 'weight' => 57,
        ]);
    }

    private function loneDivision(ClubEvent $event): array
    {
        $category = EventCategory::create(['event_id' => $event->id, 'name' => 'Senior Male +87 kg', 'sort_order' => 1]);

        return [$category, $this->enter($event, $category, 'Only One')];
    }

    private function draw(ClubEvent $event): void
    {
        $this->actingAs($this->organiser)->postJson("/me/events/{$event->uuid}/actions/generate_draw")->assertOk();
    }

    public function test_with_the_flag_off_a_division_of_one_still_has_no_board(): void
    {
        config(['events.lone_entrant_final' => false]);
        $event = $this->event();
        [$category] = $this->loneDivision($event);

        $this->draw($event);

        $this->assertSame(0, $category->matches()->count());
    }

    public function test_a_division_of_one_gets_a_final_with_an_empty_corner_they_win_by_walkover(): void
    {
        config(['events.lone_entrant_final' => true]);
        $event = $this->event();
        [$category, $entry] = $this->loneDivision($event);

        $this->draw($event);

        $bouts = $category->matches()->get();
        $this->assertCount(1, $bouts);
        $final = $bouts->first();
        $this->assertSame('Final', $final->round);
        $this->assertSame($entry->id, (int) ($final->a_competitor_id ?: $final->b_competitor_id));
        $this->assertTrue((bool) $final->won_by_bye, 'a walkover, not a result');
        $this->assertNotNull($final->winner);
    }

    public function test_an_old_division_of_one_that_was_never_drawn_gets_its_final_on_the_next_pass(): void
    {
        config(['events.lone_entrant_final' => false]);
        $event = $this->event();
        [$category] = $this->loneDivision($event);
        $this->draw($event);
        $this->assertSame(0, $category->matches()->count());

        config(['events.lone_entrant_final' => true]);
        app(DrawEngine::class)->ensure($event->fresh());

        $this->assertSame(1, $category->matches()->count());
    }

    public function test_dropping_a_competitor_on_a_board_with_no_bouts_lays_its_final(): void
    {
        config(['events.lone_entrant_final' => false]);
        $event = $this->event();
        [$category, $entry] = $this->loneDivision($event);
        $this->draw($event);

        config(['events.lone_entrant_final' => true]);

        $this->actingAs($this->organiser)->putJson("/me/events/{$event->uuid}/brackets/arrange", [
            'category_id' => $category->id,
            'from' => ['type' => 'bench', 'competitor_id' => $entry->id],
            'to' => ['type' => 'slot', 'match_id' => null, 'side' => 'a'],
        ])->assertOk()->assertJson(['success' => true]);

        $final = $category->matches()->sole();
        $this->assertSame('Final', $final->round);
        $this->assertSame($entry->id, (int) $final->a_competitor_id);
    }

    public function test_a_second_competitor_in_the_empty_corner_makes_it_a_real_bout(): void
    {
        config(['events.lone_entrant_final' => true]);
        $event = $this->event();
        [$category, $entry] = $this->loneDivision($event);
        $this->draw($event);

        $second = $this->enter($event, $category, 'Second One');
        $final = $category->matches()->sole();
        $empty = $final->a_competitor_id ? 'b' : 'a';

        $this->actingAs($this->organiser)->putJson("/me/events/{$event->uuid}/brackets/arrange", [
            'category_id' => $category->id,
            'from' => ['type' => 'bench', 'competitor_id' => $second->id],
            'to' => ['type' => 'slot', 'match_id' => $final->id, 'side' => $empty],
        ])->assertOk();

        $final->refresh();
        $this->assertEqualsCanonicalizing([$entry->id, $second->id], [(int) $final->a_competitor_id, (int) $final->b_competitor_id]);
        $this->assertNull($final->winner, 'no longer a walkover');
        $this->assertFalse((bool) $final->won_by_bye);
    }

    public function test_a_drop_with_no_slot_on_a_board_that_has_bouts_is_refused(): void
    {
        config(['events.lone_entrant_final' => true]);
        $event = $this->event();
        [$category] = $this->loneDivision($event);
        $this->draw($event);
        $extra = $this->enter($event, $category, 'Extra');

        $this->actingAs($this->organiser)->putJson("/me/events/{$event->uuid}/brackets/arrange", [
            'category_id' => $category->id,
            'from' => ['type' => 'bench', 'competitor_id' => $extra->id],
            'to' => ['type' => 'slot', 'match_id' => null, 'side' => 'a'],
        ])->assertStatus(422);

        $this->assertSame(1, $category->matches()->count());
    }

    public static function sports(): array
    {
        return [['karate'], ['bjj']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('sports')]
    public function test_every_bracketed_sport_gives_a_division_of_one_its_final_and_takes_a_second(string $sport): void
    {
        config(['events.lone_entrant_final' => true]);
        $event = $this->event($sport);
        [$category, $entry] = $this->loneDivision($event);
        $this->draw($event);

        $final = $category->matches()->sole();
        $second = $this->enter($event, $category, 'Second One');
        $empty = $final->a_competitor_id ? 'b' : 'a';

        $this->actingAs($this->organiser)->putJson("/me/events/{$event->uuid}/brackets/arrange", [
            'category_id' => $category->id,
            'from' => ['type' => 'bench', 'competitor_id' => $second->id],
            'to' => ['type' => 'slot', 'match_id' => $final->id, 'side' => $empty],
        ])->assertOk();

        $this->assertNull($final->fresh()->winner);
    }
}
