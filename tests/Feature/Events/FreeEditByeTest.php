<?php

namespace Tests\Feature\Events;

use App\Events\Models\ClubEvent;
use App\Events\Models\ClubEventRegistration;
use App\Events\Models\EventCategory;
use App\Events\Models\EventMatch;
use App\Events\Support\Tournament\FreeEdit;
use Tests\TestCase;

/**
 * A bout the organiser's free edit decides as a bye (FreeEdit::save) is not a
 * bout: it carries no number, and its mat closes the gap when mat renumbering
 * is on (owner, 2026-10-25: "how come some brackets are bye and have a bout
 * number?").
 */
class FreeEditByeTest extends TestCase
{
    /** @return array{0: \App\Members\Models\User, 1: ClubEvent, 2: array<string, ClubEventRegistration>, 3: array<string, EventMatch>} */
    private function bracket(): array
    {
        config(['events.free_edit' => true]);
        $owner = $this->createUser();
        $club = $this->createClub($owner, ['country' => 'BH', 'currency' => 'BHD']);
        $event = ClubEvent::create([
            'tenant_id' => $club->id, 'created_by' => $owner->id,
            'title' => 'Bye Open', 'event_type' => 'championship', 'sport' => 'karate',
            'scope' => 'internal', 'status' => 'active', 'is_archived' => false,
            'date' => now()->toDateString(), 'start_time' => '09:00', 'end_time' => '17:00',
        ]);
        $event->forceFill(['started_at' => now()])->save();

        $cat = EventCategory::create(['event_id' => $event->id, 'name' => 'Senior Male -60 kg', 'sort_order' => 1]);
        $p = [];
        foreach (['A', 'B', 'C', 'D'] as $n) {
            $p[$n] = ClubEventRegistration::create([
                'event_id' => $event->id, 'user_id' => $this->createUser()->id, 'category_id' => $cat->id,
                'role' => 'participant', 'status' => 'registered',
            ]);
        }
        $bout = fn (int $slot, string $round, ?string $a, ?string $b, int $no) => EventMatch::create([
            'event_id' => $event->id, 'category_id' => $cat->id, 'round' => $round, 'phase' => strtolower($round),
            'slot' => $slot, 'match_no' => $no, 'court' => 'Mat 1', 'status' => 'upcoming',
            'a_competitor_id' => $a ? $p[$a]->id : null, 'a_name' => $a,
            'b_competitor_id' => $b ? $p[$b]->id : null, 'b_name' => $b,
        ]);
        $m = ['sf1' => $bout(0, 'Semifinal', 'A', 'B', 1), 'sf2' => $bout(1, 'Semifinal', 'C', 'D', 2), 'final' => $bout(2, 'Final', null, null, 3)];

        return [$owner, $event->fresh(), $p, $m];
    }

    public function test_a_bout_written_as_a_bye_loses_its_number_and_the_mat_closes_the_gap(): void
    {
        config(['events.renumber_mats' => true]);
        [$owner, $event, $p, $m] = $this->bracket();

        $res = app(FreeEdit::class)->save($event, $m['sf1'], ['a' => $p['A']->id, 'b' => null, 'winner' => 'a', 'advance' => true], $owner);
        $this->assertTrue($res['ok'], $res['message']);

        $sf1 = $m['sf1']->fresh();
        $this->assertTrue((bool) $sf1->won_by_bye);
        $this->assertNull($sf1->match_no);
        $this->assertSame([1, 2], [(int) $m['sf2']->fresh()->match_no, (int) $m['final']->fresh()->match_no]);
        $this->assertSame($p['A']->id, $m['final']->fresh()->a_competitor_id);
    }

    public function test_with_renumbering_off_the_bye_still_loses_its_number_and_nothing_else_moves(): void
    {
        config(['events.renumber_mats' => false]);
        [$owner, $event, $p, $m] = $this->bracket();

        app(FreeEdit::class)->save($event, $m['sf1'], ['a' => $p['A']->id, 'b' => null, 'winner' => 'a', 'advance' => false], $owner);

        $this->assertNull($m['sf1']->fresh()->match_no);
        $this->assertSame([2, 3], [(int) $m['sf2']->fresh()->match_no, (int) $m['final']->fresh()->match_no]);
    }

    public function test_a_real_bout_keeps_its_number(): void
    {
        config(['events.renumber_mats' => true]);
        [$owner, $event, $p, $m] = $this->bracket();

        app(FreeEdit::class)->save($event, $m['sf1'], ['a' => $p['A']->id, 'b' => $p['B']->id, 'winner' => 'b', 'a_score' => '1', 'b_score' => '3', 'advance' => true], $owner);

        $sf1 = $m['sf1']->fresh();
        $this->assertFalse((bool) $sf1->won_by_bye);
        $this->assertSame(1, (int) $sf1->match_no);
    }
}
