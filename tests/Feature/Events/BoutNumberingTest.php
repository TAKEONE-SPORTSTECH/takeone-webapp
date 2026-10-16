<?php

namespace Tests\Feature\Events;

use App\Events\Models\EventMatch;
use Tests\Feature\Contracts\ContractTestCase;

/**
 * "Renumber bouts" — App\Events\Support\BoutNumbering via
 * POST me.events.order.renumber: numbers every bout from the running order,
 * keeps hand-set numbers, refuses once a bout is fought, organiser only.
 */
class BoutNumberingTest extends ContractTestCase
{
    private function drawn(): array
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->championship($club, $organiser);
        [$category] = $this->drawnDivision($event, $club);

        return [$event, $category, $organiser];
    }

    public function test_the_organiser_renumbers_every_bout_and_hand_set_numbers_stay(): void
    {
        [$event, $category, $organiser] = $this->drawn();

        // Wipe the numbers, and pin one by hand.
        EventMatch::where('event_id', $event->id)->update(['match_no' => null]);
        $pinned = $category->matches()->whereNotNull('a_competitor_id')->whereNotNull('b_competitor_id')->first();
        $pinned->update(['match_no' => 7, 'number_pinned' => true, 'court' => 'Mat 1', 'day' => 1]);

        $res = $this->actingAs($organiser)
            ->postJson(route('me.events.order.renumber', $event->uuid))
            ->assertOk()->assertJsonPath('success', true)->assertJsonPath('kept', 1);

        $this->assertGreaterThan(1, $res->json('numbered'));
        $this->assertSame(7, (int) $pinned->fresh()->match_no, 'a hand-set number is kept');

        $numbers = EventMatch::where('event_id', $event->id)->whereNotNull('match_no')->pluck('match_no')->all();
        $this->assertSame(count($numbers), count(array_unique($numbers)), 'no two bouts share a number on one mat');
    }

    public function test_renumbering_is_refused_to_strangers_and_once_a_bout_is_fought(): void
    {
        [$event, $category, $organiser] = $this->drawn();
        $url = route('me.events.order.renumber', $event->uuid);

        $this->actingAs($this->createUser())->postJson($url)->assertForbidden();

        $bout = $category->matches()->whereNotNull('a_competitor_id')->whereNotNull('b_competitor_id')->first();
        $before = $bout->match_no;
        $bout->update(['winner' => 'a', 'status' => 'done', 'won_by_bye' => false]);

        $this->actingAs($organiser)->postJson($url)->assertStatus(422)->assertJsonPath('success', false);
        $this->assertSame($before, $bout->fresh()->match_no);
    }

    public function test_the_draw_desk_offers_renumber_on_the_organisers_own_door(): void
    {
        [$event, , $organiser] = $this->drawn();

        $this->actingAs($organiser)->get("/me/events/{$event->uuid}/draw-desk")
            ->assertOk()
            ->assertSee('renumberAll()', false)
            ->assertSee('running-order\\/renumber', false)
            ->assertDontSee('/tablet/', false);
    }

    public function test_the_tablet_renumber_door_404s_a_dead_token(): void
    {
        $this->postJson('/tablet/'.str_repeat('a', 40).'/draw/renumber-all')->assertNotFound();
    }
}
