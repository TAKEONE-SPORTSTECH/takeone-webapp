<?php

namespace Tests\Feature\Events;

use App\Events\Models\EventCategory;
use App\Events\Models\EventRunSlot;
use Tests\Feature\Contracts\ContractTestCase;

/**
 * Auto-schedule (me.events.run-order.auto): every division not on the day yet
 * goes onto it — youngest, then lightest, then male first — and what the
 * organiser already placed stays where it is. Organiser only.
 */
class RunOrderAutoTest extends ContractTestCase
{
    public function test_it_fills_the_day_in_age_weight_gender_order_and_keeps_what_was_placed(): void
    {
        $organiser = $this->createUser();
        $event = $this->championship($this->clubFor($organiser), $organiser);
        EventCategory::where('event_id', $event->id)->delete();

        $mk = fn (string $name, int $a1, int $a2, ?float $w2, ?string $g, int $order) => EventCategory::create([
            'event_id' => $event->id, 'name' => $name, 'min_age' => $a1, 'max_age' => $a2,
            'max_weight' => $w2, 'gender' => $g, 'sort_order' => $order, 'status' => 'enrolling',
        ]);
        $older = $mk('Older', 13, 14, 50, 'Male', 1);
        $girl = $mk('Kid girl', 5, 6, 23, 'Female', 2);
        $heavy = $mk('Kid heavy', 5, 6, 30, 'Male', 3);
        $boy = $mk('Kid boy', 5, 6, 23, 'Male', 4);
        $placed = $mk('Placed by hand', 16, 18, 60, 'Male', 5);

        // The organiser already put one on the day.
        EventRunSlot::create(['event_id' => $event->id, 'category_id' => $placed->id, 'day' => 1, 'position' => 1, 'kind' => EventRunSlot::KIND_DIVISION]);

        $this->actingAs($organiser)->postJson(route('me.events.run-order.auto', $event->uuid))
            ->assertOk()->assertJsonPath('success', true)->assertJsonPath('added', 4);

        $order = EventRunSlot::where('event_id', $event->id)->whereNotNull('category_id')
            ->orderBy('day')->orderBy('position')->orderBy('id')->pluck('category_id')->all();
        $this->assertSame([$placed->id, $boy->id, $girl->id, $heavy->id, $older->id], $order);

        // Again: nothing left to add, nothing moves.
        $this->actingAs($organiser)->postJson(route('me.events.run-order.auto', $event->uuid))
            ->assertOk()->assertJsonPath('added', 0);
        $this->assertSame(5, EventRunSlot::where('event_id', $event->id)->whereNotNull('category_id')->count());
    }

    /**
     * World Taekwondo (owner, 2026-10-16): with groups on, the day becomes one
     * ROUND BY ROUND group, and each court calls every class's first round,
     * then the next, the finals last.
     */
    public function test_with_groups_on_each_court_runs_round_by_round(): void
    {
        config(['events.session_modes' => true]);

        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->championship($club, $organiser, ['courts' => 1]);
        [$a] = $this->drawnDivision($event, $club);
        [$b] = $this->drawnDivision($event, $club);
        $a->update(['name' => 'A', 'min_age' => 18, 'max_age' => 30, 'max_weight' => 58, 'gender' => 'Male']);
        $b->update(['name' => 'B', 'min_age' => 18, 'max_age' => 30, 'max_weight' => 68, 'gender' => 'Male']);

        // A class already standing on the day in no group joins the group too.
        EventRunSlot::create(['event_id' => $event->id, 'category_id' => $b->id, 'day' => 1, 'position' => 1, 'kind' => EventRunSlot::KIND_DIVISION]);

        $this->actingAs($organiser)->postJson(route('me.events.run-order.auto', $event->uuid))
            ->assertOk()->assertJsonPath('success', true);

        $group = EventRunSlot::where('event_id', $event->id)->where('kind', EventRunSlot::KIND_GROUP)->sole();
        $this->assertSame('round', $group->mode);
        $this->assertSame(0, EventRunSlot::where('event_id', $event->id)->where('kind', EventRunSlot::KIND_DIVISION)
            ->where(fn ($q) => $q->whereNull('group_id')->orWhere('group_id', '!=', $group->id))->count(), 'every class is in the WT group');

        // The court's calling order: B was on the day first, so it leads each round.
        $called = \App\Events\Models\EventMatch::where('event_id', $event->id)->whereNotNull('match_no')
            ->whereNotNull('a_competitor_id')->whereNotNull('b_competitor_id')
            ->orderBy('match_no')->get()->map(fn ($m) => ($m->category_id === $a->id ? 'A' : 'B').':'.$m->round)->all();
        $rounds = array_map(fn ($x) => (int) explode(':', $x)[1], $called);
        $this->assertSame($rounds, collect($rounds)->sort()->values()->all(), 'a later round never comes before an earlier one: '.implode(' ', $called));
        $this->assertSame(['B', 'B', 'A', 'A'], array_map(fn ($x) => explode(':', $x)[0], array_slice($called, 0, 4)));

        // Pressed again: still one group, nothing added.
        $this->actingAs($organiser)->postJson(route('me.events.run-order.auto', $event->uuid))->assertOk()->assertJsonPath('added', 0);
        $this->assertSame(1, EventRunSlot::where('event_id', $event->id)->where('kind', EventRunSlot::KIND_GROUP)->count());
    }

    /** When the day starts (owner, 2026-10-16): only the start is written. */
    public function test_the_organiser_sets_when_the_day_starts(): void
    {
        $organiser = $this->createUser();
        $event = $this->championship($this->clubFor($organiser), $organiser);   // 09:00–17:00

        $this->actingAs($organiser)->putJson(route('me.events.run-order.start', $event->uuid), ['start' => '07:30'])
            ->assertOk()->assertJsonPath('success', true)->assertJsonPath('sheet.start_time', '07:30');
        $fresh = $event->fresh();
        $this->assertSame('07:30', substr((string) $fresh->start_time, 0, 5));
        $this->assertSame('17:00', substr((string) $fresh->end_time, 0, 5), 'nothing else on the event moves');

        // Not at or after the end, and not a made-up time.
        $this->actingAs($organiser)->putJson(route('me.events.run-order.start', $event->uuid), ['start' => '17:00'])->assertStatus(422);
        $this->actingAs($organiser)->putJson(route('me.events.run-order.start', $event->uuid), ['start' => '7.30'])->assertStatus(422);
        $this->assertSame('07:30', substr((string) $event->fresh()->start_time, 0, 5));

        $this->actingAs($this->createUser())->putJson(route('me.events.run-order.start', $event->uuid), ['start' => '06:00'])->assertForbidden();
        $this->assertSame('07:30', substr((string) $event->fresh()->start_time, 0, 5));
    }

    public function test_nobody_else_may_run_it(): void
    {
        $organiser = $this->createUser();
        $event = $this->championship($this->clubFor($organiser), $organiser);

        $this->actingAs($this->createUser())->postJson(route('me.events.run-order.auto', $event->uuid))->assertForbidden();
        $this->assertSame(0, EventRunSlot::where('event_id', $event->id)->count());
    }
}
