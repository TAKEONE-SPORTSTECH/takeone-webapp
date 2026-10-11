<?php

namespace Tests\Feature\Events;

use App\Events\Models\ClubEvent;
use App\Events\Models\EventCategory;
use App\Events\Models\EventMatch;
use App\Events\Support\RunOrder;
use App\Sports\Combat\Engine\Scheduler;
use Tests\TestCase;

/**
 * Bout numbers follow the day's running order (App\Events\Support\RunOrder).
 *
 * The owner, 2026-10-07: "the bouts must be numbered as per the day schedule".
 * The shared Scheduler numbers a mat division by division in the running
 * order; arranging the day must renumber, before the event starts and never
 * after.
 */
class RunOrderNumberingTest extends TestCase
{
    /** @return array{0: ClubEvent, 1: EventCategory, 2: EventCategory} */
    private function scenario(): array
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner, ['country' => 'BH']);

        $event = ClubEvent::create([
            'tenant_id' => $club->id, 'created_by' => $owner->id,
            'title' => 'Numbered Open', 'event_type' => 'championship', 'sport' => 'karate',
            'scope' => 'internal', 'status' => 'active', 'is_archived' => false, 'courts' => 1,
            'date' => now()->addWeeks(2)->toDateString(), 'start_time' => '09:00', 'end_time' => '17:00',
        ]);

        $divs = [];
        foreach (['Boys', 'Girls'] as $o => $name) {
            $cat = EventCategory::create(['event_id' => $event->id, 'name' => $name, 'sort_order' => $o + 1]);
            // A four-man bracket: two semi-finals, then the final.
            foreach ([['Semifinal', 'quarterfinals', 0], ['Semifinal', 'quarterfinals', 1], ['Final', 'finals', 2]] as [$round, $phase, $slot]) {
                EventMatch::create([
                    'event_id' => $event->id, 'category_id' => $cat->id, 'round' => $round, 'phase' => $phase,
                    'slot' => $slot, 'a_name' => $name.' A'.$slot, 'b_name' => $name.' B'.$slot, 'status' => 'upcoming',
                ]);
            }
            $divs[] = $cat;
        }

        app(Scheduler::class)->scheduleAndNumber($event->fresh());

        return [$event->fresh(), $divs[0], $divs[1]];
    }

    /** @return list<int> the division's bout numbers, in round order */
    private function numbers(EventCategory $cat): array
    {
        return EventMatch::where('category_id', $cat->id)->orderBy('slot')->pluck('match_no')->map(fn ($n) => (int) $n)->all();
    }

    public function test_arranging_the_day_numbers_division_by_division_in_that_order(): void
    {
        [$event, $boys, $girls] = $this->scenario();

        // Girls first, then Boys.
        app(RunOrder::class)->addDivisions($event, [$girls->id, $boys->id]);

        $this->assertSame([1, 2, 3], $this->numbers($girls));
        $this->assertSame([4, 5, 6], $this->numbers($boys));

        // Swap them on the day: the numbers follow.
        $slots = \App\Events\Models\EventRunSlot::where('event_id', $event->id)->orderBy('position')->pluck('uuid')->all();
        app(RunOrder::class)->reorder($event, 1, array_reverse($slots));

        $this->assertSame([1, 2, 3], $this->numbers($boys));
        $this->assertSame([4, 5, 6], $this->numbers($girls));
    }

    public function test_once_the_event_has_started_the_numbers_stay_put(): void
    {
        [$event, $boys, $girls] = $this->scenario();
        app(RunOrder::class)->addDivisions($event, [$boys->id, $girls->id]);
        $before = [$this->numbers($boys), $this->numbers($girls)];

        $event->forceFill(['started_at' => now()])->save();
        $slots = \App\Events\Models\EventRunSlot::where('event_id', $event->id)->orderBy('position')->pluck('uuid')->all();
        app(RunOrder::class)->reorder($event->fresh(), 1, array_reverse($slots));

        $this->assertSame($before, [$this->numbers($boys), $this->numbers($girls)]);
    }

    public function test_a_division_goes_on_the_day_part_by_part_until_it_is_all_placed(): void
    {
        [$event, $boys, $girls] = $this->scenario();
        $order = app(RunOrder::class);
        $sheet = app(\App\Events\Support\RunSheet::class);

        // Every semi-final first…
        $order->addDivisions($event, [['id' => $boys->id, 'parts' => ['semis']], ['id' => $girls->id, 'parts' => ['semis']]]);

        // …both divisions are still waiting for their finals.
        $bench = $sheet->unscheduled($event)->keyBy('id');
        $this->assertSame(['finals', 'awarding'], $bench[$boys->id]->remaining_parts);
        $this->assertSame(['finals', 'awarding'], $bench[$girls->id]->remaining_parts);

        // The same part cannot go on twice.
        $this->assertSame(0, $order->addDivisions($event, [['id' => $boys->id, 'parts' => ['semis']]]));

        // …then the finals (and their awarding) at the end of the day.
        $order->addDivisions($event, [$boys->id, $girls->id]);
        $this->assertCount(0, $sheet->unscheduled($event));
        $this->assertSame(0, $order->addDivisions($event, [$boys->id]));

        // Numbered in that order: both semi-finals of each, then both finals.
        $this->assertSame([1, 2, 5], $this->numbers($boys));
        $this->assertSame([3, 4, 6], $this->numbers($girls));

        // And the day reads the parts by name, timed by their own bouts.
        $rows = $sheet->rows($event->fresh());
        $this->assertSame('Boys — Semi-finals', $rows[0]['name']);
        $this->assertSame(2, $rows[0]['bouts']);
        $this->assertSame('Boys — Finals & Awarding', $rows[2]['name']);
        $this->assertSame(1, $rows[2]['bouts']);
    }

    public function test_headings_are_never_offered_and_a_slot_from_before_parts_stays_complete(): void
    {
        [$event, $boys, $girls] = $this->scenario();
        EventCategory::create(['event_id' => $event->id, 'name' => 'Ages 8-9', 'sort_order' => 0, 'is_heading' => true]);

        $names = app(\App\Events\Support\RunSheet::class)->unscheduled($event)->pluck('name')->all();
        $this->assertSame(['Boys', 'Girls'], $names);

        // A slot written before parts existed (NULL) placed the whole division,
        // awarding and all: it never reappears on the bench.
        \App\Events\Models\EventRunSlot::create(['event_id' => $event->id, 'category_id' => $boys->id, 'day' => 1, 'position' => 10, 'kind' => 'division']);
        $this->assertSame(['Girls'], app(\App\Events\Support\RunSheet::class)->unscheduled($event)->pluck('name')->all());
    }

    public function test_the_awarding_goes_on_the_day_as_its_own_part(): void
    {
        [$event, $boys] = $this->scenario();
        $order = app(RunOrder::class);
        $sheet = app(\App\Events\Support\RunSheet::class);

        $order->addDivisions($event, [['id' => $boys->id, 'parts' => ['semis', 'finals']]]);
        $this->assertSame(['awarding'], $sheet->unscheduled($event)->keyBy('id')[$boys->id]->remaining_parts);

        $order->addDivisions($event, [['id' => $boys->id, 'parts' => ['awarding']]]);
        $this->assertFalse($sheet->unscheduled($event)->contains('id', $boys->id));

        $rows = $sheet->rows($event->fresh());
        $this->assertSame('Boys — Awarding', $rows[1]['name']);
        $this->assertSame(0, $rows[1]['bouts']);
        $this->assertSame(\App\Events\Models\EventRunSlot::DEFAULT_DURATIONS['ceremony'], $rows[1]['duration']);
    }

    public function test_the_events_own_break_is_offered_and_lands_at_its_time(): void
    {
        [$event, $boys, $girls] = $this->scenario();
        $event->forceFill(['break_start' => '09:20', 'break_end' => '10:00', 'minutes_per_match' => 8])->save();
        $order = app(RunOrder::class);

        $order->addDivisions($event, [$boys->id, $girls->id]);
        $this->assertSame(['starts_at' => 560, 'duration' => 40, 'start' => '09:20', 'end' => '10:00'], $order->eventBreak($event->fresh()));

        $order->addEventBreak($event->fresh());

        // Pinned at 09:20, slotted between the two divisions (Boys runs 09:00–09:2x).
        $rows = app(\App\Events\Support\RunSheet::class)->rows($event->fresh());
        $this->assertSame(['division', 'break', 'division'], array_column($rows, 'kind'));
        $this->assertSame(560, $rows[1]['starts_at']);
        $this->assertSame(40, $rows[1]['duration']);
        $this->assertTrue($rows[1]['pinned']);

        // On the day now, so no longer offered.
        $this->assertNull($order->eventBreak($event->fresh()));
    }

    public function test_one_medal_ceremony_for_everyone_at_the_end_of_the_day(): void
    {
        [$event, $boys, $girls] = $this->scenario();
        $order = app(RunOrder::class);
        $sheet = app(\App\Events\Support\RunSheet::class);

        // Boys, with the switch on: its bouts go on the day, its medals wait for the ceremony.
        $order->addDivisions($event, [$boys->id], 1, together: true);
        $this->assertFalse($sheet->unscheduled($event)->contains('id', $boys->id), 'no awarding of its own left to place');

        $rows = $sheet->rows($event->fresh());
        $this->assertSame(['division', 'ceremony'], array_column($rows, 'kind'));
        $this->assertSame('Boys', $rows[0]['name']);

        // Girls joins the SAME ceremony, which stays last.
        $order->addBlock($event, \App\Events\Models\EventRunSlot::KIND_BREAK, null, 10);
        $order->addDivisions($event->fresh(), [$girls->id], 1, together: true);
        $rows = $sheet->rows($event->fresh());
        $this->assertSame(['division', 'break', 'division', 'ceremony'], array_column($rows, 'kind'));
        $this->assertSame(1, \App\Events\Models\EventRunSlot::where('event_id', $event->id)->where('kind', 'ceremony')->count());
        $this->assertStringEndsWith('2 divisions', end($rows)['name']);
        $this->assertCount(0, $sheet->unscheduled($event));
    }

    public function test_the_events_break_is_edited_from_the_running_order_and_its_slot_follows(): void
    {
        [$event, $boys] = $this->scenario();
        $event->forceFill(['break_start' => '13:00', 'break_end' => '14:00'])->save();
        $owner = \App\Members\Models\User::find($event->created_by);

        // Before it is on the day: the event's own break changes.
        $this->actingAs($owner)->putJson(route('me.events.run-order.break', $event->uuid), ['start' => '12:30', 'end' => '13:15'])->assertOk();
        $this->assertSame('12:30', substr($event->fresh()->break_start, 0, 5));
        $this->assertSame(['starts_at' => 750, 'duration' => 45, 'start' => '12:30', 'end' => '13:15'], app(RunOrder::class)->eventBreak($event->fresh()));

        // On the day: the pinned slot moves with it.
        app(RunOrder::class)->addEventBreak($event->fresh());
        $this->actingAs($owner)->putJson(route('me.events.run-order.break', $event->uuid), ['start' => '13:00', 'end' => '13:30'])->assertOk();
        $slot = \App\Events\Models\EventRunSlot::where('event_id', $event->id)->where('kind', 'break')->first();
        $this->assertSame(780, $slot->pinned_at);
        $this->assertSame(30, $slot->duration_minutes);

        // An end before the start is refused; and nobody else may change it.
        $this->actingAs($owner)->putJson(route('me.events.run-order.break', $event->uuid), ['start' => '14:00', 'end' => '13:00'])->assertStatus(422);
        $this->actingAs($this->createUser())->putJson(route('me.events.run-order.break', $event->uuid), ['start' => '10:00', 'end' => '11:00'])->assertStatus(403);
    }
}
