<?php

namespace Tests\Feature\Events;

use App\Events\Models\ClubEvent;
use App\Events\Models\EventCategory;
use App\Events\Models\EventMatch;
use App\Mcp\Tools\SetMatCornerSideTool;
use App\Scoreboard\Sports\Karate\Mat\MatCorners;
use App\Scoreboard\Sports\Karate\Mat\MatState;
use App\Sports\Combat\CornerSide;
use Tests\TestCase;

/**
 * "Swap corner colours on this mat" — the PHYSICAL side of one mat's red
 * corner, set from the scoring console. Per mat, survives the rules being
 * saved, never touches who is AKA, and only for someone who may score.
 */
class KarateMatCornersTest extends TestCase
{
    private function scenario(): array
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner, ['country' => 'BH', 'currency' => 'BHD']);

        $event = ClubEvent::create([
            'tenant_id' => $club->id, 'created_by' => $owner->id,
            'title' => 'Corner Open', 'event_type' => 'championship', 'sport' => 'karate',
            'scope' => 'internal', 'status' => 'active', 'is_archived' => false,
            'date' => now()->addWeeks(2)->toDateString(), 'start_time' => '09:00', 'end_time' => '17:00',
        ]);

        $cat = EventCategory::create(['event_id' => $event->id, 'name' => 'Senior Men', 'sort_order' => 1]);
        foreach (['Mat 1', 'Mat 2'] as $i => $mat) {
            EventMatch::create([
                'event_id' => $event->id, 'category_id' => $cat->id, 'round' => 'Final', 'phase' => 'finals',
                'slot' => $i, 'a_name' => 'Ali', 'b_name' => 'Bader', 'court' => $mat, 'status' => 'upcoming',
            ]);
        }

        return [$owner, $event->fresh()];
    }

    public function test_the_console_swaps_one_mat_and_leaves_the_others_on_the_convention(): void
    {
        [$owner, $event] = $this->scenario();
        $default = CornerSide::redRight('karate');

        $this->actingAs($owner)
            ->postJson("/karate/control/{$event->uuid}/corners", ['mat' => 'Mat 1', 'red_right' => ! $default])
            ->assertOk()->assertJson(['success' => true, 'red_right' => ! $default]);

        $event->refresh();
        $this->assertSame(! $default, MatCorners::redRight($event, 'Mat 1'));
        $this->assertSame($default, MatCorners::redRight($event, 'Mat 2'));

        // The console draws this mat the swapped way round.
        $this->actingAs($owner)->get("/karate/control/{$event->uuid}?mat=".urlencode('Mat 1'))
            ->assertOk()->assertViewHas('redRight', ! $default);

        // Back to the convention clears the entry.
        $this->actingAs($owner)
            ->postJson("/karate/control/{$event->uuid}/corners", ['mat' => 'Mat 1', 'red_right' => $default])
            ->assertOk();
        $this->assertArrayNotHasKey(MatCorners::KEY, (array) $event->fresh()->scoreboard_settings);
    }

    public function test_saving_the_mat_rules_keeps_the_corner_side(): void
    {
        [, $event] = $this->scenario();
        $default = CornerSide::redRight('karate');

        MatCorners::set($event, 'Mat 1', ! $default);
        $event->refresh();

        $state = MatState::load($event, 'Mat 1');
        $state->gap = 5;
        $state->persistSettings($event);

        $event->refresh();
        $this->assertSame(5, $event->scoreboard_settings['gap']);
        $this->assertSame(! $default, MatCorners::redRight($event, 'Mat 1'));
    }

    public function test_someone_who_may_not_score_cannot_swap_and_an_unknown_mat_is_refused(): void
    {
        [$owner, $event] = $this->scenario();

        $this->actingAs($this->createUser())
            ->postJson("/karate/control/{$event->uuid}/corners", ['mat' => 'Mat 1', 'red_right' => true])
            ->assertForbidden();

        $this->actingAs($owner)
            ->postJson("/karate/control/{$event->uuid}/corners", ['mat' => 'Mat 9', 'red_right' => true])
            ->assertNotFound();

        $this->assertArrayNotHasKey(MatCorners::KEY, (array) $event->fresh()->scoreboard_settings);
    }

    public function test_the_mcp_tool_sets_a_mat_and_refuses_an_outsider(): void
    {
        [$owner, $event] = $this->scenario();

        $this->actingAs($this->createUser());
        $denied = $this->callMcp(['event' => $event->uuid, 'mat' => 'Mat 1', 'red_side' => 'left']);
        $this->assertArrayHasKey('error', $denied);

        $this->actingAs($owner);
        $set = $this->callMcp(['event' => $event->uuid, 'mat' => 'Mat 1', 'red_side' => 'left']);
        $this->assertSame('left', $set['red_side']);
        $this->assertFalse(MatCorners::redRight($event->fresh(), 'Mat 1'));
    }

    private function callMcp(array $args): array
    {
        $text = (string) (new SetMatCornerSideTool)->handle(new \Laravel\Mcp\Request($args))->content();
        $decoded = json_decode($text, true);

        return is_array($decoded) ? $decoded : ['error' => $text];
    }
}
