<?php

namespace Tests\Feature\Events;

use App\Events\Models\ClubEvent;
use App\Events\Models\ClubEventRegistration;
use App\Events\Models\EventCategory;
use App\Events\Models\EventExhibitionBout;
use App\Events\Models\EventMatch;
use App\Events\Support\Tournament\MatNumbering;
use Tests\TestCase;

/**
 * A mat's numbers after the draw is re-arranged mid-event
 * (App\Events\Support\Tournament\MatNumbering, EVENT_RENUMBER_MATS).
 */
class MatNumberingTest extends TestCase
{
    /**
     * Mat 1: #1 another division, #2 this division's first semi, #3 its final,
     * #4 another division. The second semi is a bye: no number, no mat.
     *
     * @return array{0: \App\Members\Models\User, 1: ClubEvent, 2: array<string, EventMatch>, 3: array<string, ClubEventRegistration>}
     */
    private function mat(): array
    {
        config(['events.renumber_mats' => true, 'events.exhibitions' => true]);

        $owner = $this->createUser();
        $club = $this->createClub($owner, ['country' => 'BH', 'currency' => 'BHD']);
        $event = ClubEvent::create([
            'tenant_id' => $club->id, 'created_by' => $owner->id,
            'title' => 'Numbers Open', 'event_type' => 'championship', 'sport' => 'karate',
            'scope' => 'internal', 'status' => 'active', 'is_archived' => false,
            'date' => now()->toDateString(), 'start_time' => '09:00', 'end_time' => '17:00',
        ]);
        $event->forceFill(['started_at' => now()])->save();

        $p = [];
        foreach (['A', 'B', 'C', 'D'] as $n) {
            $p[$n] = ClubEventRegistration::create([
                'event_id' => $event->id, 'user_id' => $this->createUser()->id,
                'role' => 'participant', 'status' => 'registered',
            ]);
        }

        $here = EventCategory::create(['event_id' => $event->id, 'name' => 'Cadet Kumite Male -52 kg', 'sort_order' => 1]);
        $other = EventCategory::create(['event_id' => $event->id, 'name' => 'Cadet Kumite Male -57 kg', 'sort_order' => 2]);
        $bout = fn (EventCategory $c, int $slot, string $round, ?int $no, array $extra = []) => EventMatch::create($extra + [
            'event_id' => $event->id, 'category_id' => $c->id, 'round' => $round, 'slot' => $slot,
            'match_no' => $no, 'court' => $no ? 'Mat 1' : null, 'day' => $no ? 1 : null, 'status' => 'done',
        ]);

        $m = [
            'before' => $bout($other, 0, 'Final', 1),
            'sf1' => $bout($here, 0, 'Semifinal', 2, ['a_competitor_id' => $p['A']->id, 'b_competitor_id' => $p['B']->id, 'winner' => 'a']),
            'sf2' => $bout($here, 1, 'Semifinal', null, ['a_competitor_id' => $p['C']->id, 'b_competitor_id' => null, 'winner' => 'a', 'won_by_bye' => true]),
            'final' => $bout($here, 2, 'Final', 3),
            'after' => $bout($other, 1, 'Final', 4),
        ];

        return [$owner, $event->fresh(), $m, $p];
    }

    private function numbers(array $m): array
    {
        return array_map(fn (EventMatch $b) => $b->fresh()->match_no, $m);
    }

    public function test_a_new_bout_goes_before_its_own_final_and_the_mat_is_renumbered(): void
    {
        [, , $m] = $this->mat();

        $no = app(MatNumbering::class)->place($m['sf2']->fresh(), 'Mat 1');

        $this->assertSame(3, $no);
        $this->assertSame(['before' => 1, 'sf1' => 2, 'sf2' => 3, 'final' => 4, 'after' => 5], $this->numbers($m));
        $this->assertSame('Mat 1', $m['sf2']->fresh()->court);
        $this->assertSame(1, (int) $m['sf2']->fresh()->day);
    }

    public function test_an_unknown_mat_falls_back_to_the_divisions_own(): void
    {
        [, , $m] = $this->mat();

        app(MatNumbering::class)->place($m['sf2']->fresh(), 'Mat 9');

        $this->assertSame('Mat 1', $m['sf2']->fresh()->court);
        $this->assertSame(3, $m['sf2']->fresh()->match_no);
    }

    public function test_a_number_pinned_by_hand_is_kept(): void
    {
        [, , $m] = $this->mat();
        $m['after']->forceFill(['number_pinned' => true])->save();

        app(MatNumbering::class)->place($m['sf2']->fresh(), 'Mat 1');

        // #4 is pinned to "after", so the final steps over it to #5.
        $this->assertSame(['before' => 1, 'sf1' => 2, 'sf2' => 3, 'final' => 5, 'after' => 4], $this->numbers($m));
    }

    public function test_a_bout_leaving_the_mat_leaves_no_gap(): void
    {
        [, $event, $m] = $this->mat();
        $m['sf1']->forceFill(['match_no' => null])->save();

        app(MatNumbering::class)->close($event->id, 'Mat 1', 1);

        $this->assertSame(['before' => 1, 'sf1' => null, 'sf2' => null, 'final' => 2, 'after' => 3], $this->numbers($m));
    }

    public function test_linking_an_exhibition_to_an_unnumbered_bout_numbers_it_in_place(): void
    {
        [$owner, $event, $m, $p] = $this->mat();
        $ex = EventExhibitionBout::create([
            'event_id' => $event->id, 'number' => 1, 'court' => 'Mat 1',
            'a_registration_id' => $p['C']->id, 'b_registration_id' => $p['D']->id,
            'a_name' => 'C', 'b_name' => 'D', 'a_score' => '0', 'b_score' => '6',
            'winner' => 'b', 'status' => EventExhibitionBout::STATUS_DONE,
        ]);

        $this->actingAs($owner)->postJson("/karate/control/{$event->uuid}/exhibitions/{$ex->uuid}/link",
            ['match_id' => $m['sf2']->id, 'attach_only' => true])->assertOk();

        $this->assertSame(['before' => 1, 'sf1' => 2, 'sf2' => 3, 'final' => 4, 'after' => 5], $this->numbers($m));
    }

    public function test_a_live_move_into_a_bye_numbers_the_new_bout_before_its_final(): void
    {
        [$owner, $event, $m] = $this->mat();
        config(['events.live_move' => true]);
        // The second semi still to come, its bye-holder waiting in the final.
        foreach (['sf1', 'final'] as $k) {
            $m[$k]->forceFill(['status' => 'upcoming', 'winner' => null])->save();
        }
        $m['final']->forceFill(['b_competitor_id' => $m['sf2']->a_competitor_id])->save();

        $from = EventCategory::create(['event_id' => $event->id, 'name' => 'Cadet Kumite Male -47 kg', 'sort_order' => 3]);
        $mover = ClubEventRegistration::create([
            'event_id' => $event->id, 'user_id' => $this->createUser()->id,
            'role' => 'participant', 'status' => 'registered', 'category_id' => $from->id,
        ]);

        $out = app(\App\Events\Support\Tournament\LiveMove::class)->run($event, $mover->id, $m['sf2']->category_id, $owner);

        $this->assertTrue($out['ok'], $out['message']);
        $this->assertSame(['before' => 1, 'sf1' => 2, 'sf2' => 3, 'final' => 4, 'after' => 5], $this->numbers($m));
    }

    public function test_switched_off_nothing_is_renumbered(): void
    {
        [$owner, $event, $m, $p] = $this->mat();
        config(['events.renumber_mats' => false]);
        $ex = EventExhibitionBout::create([
            'event_id' => $event->id, 'number' => 1, 'court' => 'Mat 1',
            'a_registration_id' => $p['C']->id, 'b_registration_id' => $p['D']->id,
            'a_name' => 'C', 'b_name' => 'D', 'a_score' => '0', 'b_score' => '6',
            'winner' => 'b', 'status' => EventExhibitionBout::STATUS_DONE,
        ]);

        $this->actingAs($owner)->postJson("/karate/control/{$event->uuid}/exhibitions/{$ex->uuid}/link",
            ['match_id' => $m['sf2']->id, 'attach_only' => true])->assertOk();

        $this->assertSame(['before' => 1, 'sf1' => 2, 'sf2' => null, 'final' => 3, 'after' => 4], $this->numbers($m));
    }
}
