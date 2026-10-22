<?php

namespace Tests\Feature\Events;

use App\Events\Models\ClubEventRegistration;
use App\Events\Models\EventCategory;
use App\Events\Support\OneDivisionRule;
use Tests\Feature\Contracts\ContractTestCase;

/**
 * Double entries that existed BEFORE "one division per person" was switched on
 * (OneDivisionRule::conflicts, DrawGuard).
 *
 * What this protects: turning the switch on reports everybody already in two
 * divisions instead of passing silently; the report suggests the division a
 * person fits when only one fits; and with the draw guards on, "Generate
 * draw" is refused while a conflict is open — and allowed again once it is
 * resolved. With the guards off (the default) the draw behaves as before.
 */
class OneDivisionConflictsTest extends ContractTestCase
{
    private function setUpDoubleEntry(): array
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->championship($club, $organiser);
        [$drawn] = $this->drawnDivision($event, $club);
        $heavier = EventCategory::create(['event_id' => $event->id, 'name' => 'Senior Men -68 kg', 'sort_order' => 2,
            'min_weight' => 58.01, 'max_weight' => 68]);
        $drawn->update(['min_weight' => 50, 'max_weight' => 58]);
        $someone = ClubEventRegistration::where('category_id', $drawn->id)->first();
        ClubEventRegistration::create(['event_id' => $event->id, 'user_id' => $someone->user_id, 'category_id' => $heavier->id,
            'role' => 'participant', 'paid' => true, 'weight' => 57]);

        return compact('organiser', 'event', 'drawn', 'heavier', 'someone');
    }

    public function test_switching_on_lists_the_people_already_in_two_divisions(): void
    {
        ['organiser' => $organiser, 'event' => $event, 'drawn' => $drawn, 'someone' => $someone] = $this->setUpDoubleEntry();

        $res = $this->actingAs($organiser)->putJson(route('me.events.bracket.one-division', $event->uuid), ['on' => true])
            ->assertOk()->assertJsonPath('one_division', true)->assertJsonCount(1, 'conflicts');

        $conflict = $res->json('conflicts.0');
        $this->assertSame($someone->user_id, $conflict['user_id']);
        $this->assertCount(2, $conflict['divisions']);
        // 57 kg fits -58, not -68: the one it fits is suggested.
        $this->assertSame($drawn->id, $conflict['suggested']);

        $this->actingAs($organiser)->getJson(route('me.events.bracket.one-division.conflicts', $event->uuid))
            ->assertOk()->assertJsonCount(1, 'conflicts');
    }

    public function test_nobody_in_two_divisions_means_no_conflicts(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->championship($club, $organiser);
        $this->drawnDivision($event, $club);

        $this->assertSame([], OneDivisionRule::conflicts($event));
    }

    public function test_the_conflict_list_is_for_arrangers_only(): void
    {
        ['event' => $event] = $this->setUpDoubleEntry();

        $this->actingAs($this->createUser())->getJson(route('me.events.bracket.one-division.conflicts', $event->uuid))
            ->assertForbidden();
    }

    public function test_with_the_guards_on_the_draw_is_refused_until_the_conflict_is_resolved(): void
    {
        config(['events.draw_guards' => true]);
        ['organiser' => $organiser, 'event' => $event, 'heavier' => $heavier, 'someone' => $someone] = $this->setUpDoubleEntry();
        $event->update(['one_division_per_person' => true]);

        $this->actingAs($organiser)->postJson(route('me.events.action', [$event->uuid, 'generate_draw']))
            ->assertStatus(422)->assertJsonCount(1, 'conflicts');

        // Resolved: the second entry leaves -68.
        ClubEventRegistration::where('category_id', $heavier->id)->where('user_id', $someone->user_id)->update(['category_id' => null]);

        $this->actingAs($organiser)->postJson(route('me.events.action', [$event->uuid, 'generate_draw']))->assertOk();
    }

    public function test_with_the_guards_off_the_draw_behaves_as_before(): void
    {
        config(['events.draw_guards' => false]);
        ['organiser' => $organiser, 'event' => $event] = $this->setUpDoubleEntry();
        $event->update(['one_division_per_person' => true]);

        $this->actingAs($organiser)->postJson(route('me.events.action', [$event->uuid, 'generate_draw']))
            ->assertOk()->assertJsonMissingPath('small_divisions');
    }
}
