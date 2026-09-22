<?php

namespace Tests\Feature\Events;

use App\Clubs\Models\Tenant;
use App\Events\Support\Tournament\OrderBoard;
use App\Members\Models\User;
use App\Models\ClubEvent;
use App\Models\EventCategory;
use App\Models\ClubEventRegistration;
use App\Models\EventMatch;
use Tests\TestCase;

/**
 * The running order as something an organiser drags.
 *
 * Two things are asserted, and they are the bargain the board makes:
 *
 *  1. IT REALLY MOVES. A bout dragged to a mat and a position lands there, the
 *     numbers close up behind it, and the mat it came off has no hole in it.
 *  2. IT WILL NOT LET YOU BREAK THE BRACKET. A bout cannot be placed ahead of
 *     the bouts whose winners fill its corners — refused, with the offender
 *     named. Everything else an organiser wants to do is allowed, because
 *     everything else is somebody's hall.
 */
class RunningOrderBoardTest extends TestCase
{
    /**
     * A four-man division: two semi-finals feeding a final, plus a third-place
     * bout — which is the case a naive "previous round" rule gets backwards.
     *
     * @return array{0: User, 1: ClubEvent, 2: array<string, EventMatch>}
     */
    private function scenario(): array
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner, ['country' => 'BH', 'currency' => 'BHD']);

        $event = ClubEvent::create([
            'tenant_id' => $club->id, 'created_by' => $owner->id,
            'title' => 'Gulf Open', 'event_type' => 'championship', 'sport' => 'bjj',
            'scope' => 'internal', 'date' => now()->addWeek()->toDateString(),
            'end_date' => now()->addWeek()->toDateString(),
            'start_time' => '09:00', 'end_time' => '17:00',
            'status' => 'active', 'is_archived' => false, 'courts' => 2,
        ]);

        $cat = EventCategory::create(['event_id' => $event->id, 'name' => 'Adult Men Light', 'sort_order' => 1]);

        // Real entries, because `a_competitor_id` is a real foreign key and
        // because a bout is only RUNNABLE when both corners name one.
        $entry = function (string $name) use ($event, $club, $cat) {
            $athlete = $this->createUser(['full_name' => $name]);
            $athlete->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);

            return ClubEventRegistration::create([
                'event_id' => $event->id, 'tenant_id' => $club->id, 'user_id' => $athlete->id,
                'category_id' => $cat->id, 'role' => 'participant', 'status' => 'joined',
                'paid' => true, 'registered_at' => now(),
            ]);
        };

        $ali = $entry('Ali');
        $bader = $entry('Bader');
        $chadi = $entry('Chadi');
        $dawoud = $entry('Dawoud');

        /*
         * The entry ids matter as much as the names: a bout is RUNNABLE only
         * when both corners name an entry (RunningOrder::isRunnable), which is
         * what greys out a semi-final whose quarter-final has not happened. A
         * fixture with names and no ids is a draw nobody can call.
         */
        $make = fn (string $round, int $slot, int $no, ?string $a, ?string $b, ?int $aId = null, ?int $bId = null) => EventMatch::create([
            'event_id' => $event->id, 'category_id' => $cat->id,
            'round' => $round, 'slot' => $slot, 'match_no' => $no,
            'a_name' => $a, 'b_name' => $b,
            'a_competitor_id' => $aId, 'b_competitor_id' => $bId,
            'court' => 'Mat 1', 'day' => 1, 'status' => 'upcoming',
        ]);

        return [$owner, $event, [
            'semi1' => $make('Semifinal', 0, 1, 'Ali', 'Bader', $ali->id, $bader->id),
            'semi2' => $make('Semifinal', 1, 2, 'Chadi', 'Dawoud', $chadi->id, $dawoud->id),
            'final' => $make('Final', 2, 3, null, null),
            'third' => $make('Third place', 3, 4, null, null),
        ]];
    }

    public function test_a_bout_can_be_dragged_up_its_own_mat_and_the_numbers_close_up(): void
    {
        [, $event, $m] = $this->scenario();

        $result = app(OrderBoard::class)->move($event, $m['semi2']->id, 'Mat 1', 0);

        $this->assertTrue($result['ok'], $result['message'] ?? '');
        $this->assertSame(1, $m['semi2']->fresh()->match_no);
        $this->assertSame(2, $m['semi1']->fresh()->match_no);
        // No gaps: a number is a position, and a caller announcing bout 4 after
        // bout 2 is the fault this closes.
        $this->assertSame([1, 2, 3, 4], EventMatch::where('event_id', $event->id)
            ->orderBy('match_no')->pluck('match_no')->all());
    }

    public function test_a_bout_can_be_dragged_to_another_mat(): void
    {
        [, $event, $m] = $this->scenario();

        $this->assertTrue(app(OrderBoard::class)->move($event, $m['semi2']->id, 'Mat 2', 0)['ok']);

        $this->assertSame('Mat 2', $m['semi2']->fresh()->court);
        $this->assertSame(1, $m['semi2']->fresh()->match_no);

        // And the mat it left is renumbered from 1, with no hole where it was.
        $this->assertSame([1, 2, 3], EventMatch::where('event_id', $event->id)
            ->where('court', 'Mat 1')->orderBy('match_no')->pluck('match_no')->all());
    }

    public function test_a_final_cannot_be_placed_ahead_of_its_semi_finals(): void
    {
        [, $event, $m] = $this->scenario();

        $result = app(OrderBoard::class)->move($event, $m['final']->id, 'Mat 1', 0);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('Semifinal', $result['message']);
        // Refused means UNCHANGED, not partly applied.
        $this->assertSame(3, $m['final']->fresh()->match_no);
        $this->assertSame(1, $m['semi1']->fresh()->match_no);
    }

    /**
     * The third-place bout is fed by the SEMI-FINALS, not by the final it sits
     * after — the one relation a "previous round" rule reads backwards.
     */
    public function test_the_third_place_bout_is_bound_to_the_semi_finals_not_the_final(): void
    {
        [, $event, $m] = $this->scenario();

        $this->assertFalse(app(OrderBoard::class)->move($event, $m['third']->id, 'Mat 1', 0)['ok']);

        // But it may run BEFORE the final, which is ordinary at a real event.
        $this->assertTrue(app(OrderBoard::class)->move($event, $m['third']->id, 'Mat 1', 2)['ok']);
        $this->assertSame(3, $m['third']->fresh()->match_no);
        $this->assertSame(4, $m['final']->fresh()->match_no);
    }

    public function test_a_bout_that_has_been_fought_cannot_be_moved(): void
    {
        [, $event, $m] = $this->scenario();

        $m['semi1']->forceFill(['winner' => 'a'])->save();

        $result = app(OrderBoard::class)->move($event, $m['semi1']->id, 'Mat 2', 0);

        $this->assertFalse($result['ok']);
        $this->assertSame('Mat 1', $m['semi1']->fresh()->court);
    }

    public function test_the_board_lists_every_mat_and_marks_what_cannot_run_yet(): void
    {
        [, $event] = $this->scenario();

        $plan = app(OrderBoard::class)->plan($event, 1);

        $this->assertSame(1, $plan['day']);
        $this->assertContains('Mat 1', array_column($plan['mats'], 'court'));
        // The event is configured for two mats, so the empty one is offered as
        // a place to drag onto rather than being absent.
        $this->assertContains('Mat 2', array_column($plan['mats'], 'court'));

        $bouts = collect($plan['mats'])->firstWhere('court', 'Mat 1')['bouts'];
        $this->assertTrue(collect($bouts)->firstWhere('round', 'Semifinal')['runnable']);
        $this->assertFalse(collect($bouts)->firstWhere('round', 'Final')['runnable']);
    }

    /* ---------------- The doors ---------------- */

    public function test_only_somebody_who_may_manage_the_event_can_drag(): void
    {
        [, $event, $m] = $this->scenario();
        $stranger = $this->createUser();

        $this->actingAs($stranger)->putJson(route('me.events.order.move', $event->uuid), [
            'match' => $m['semi2']->id, 'court' => 'Mat 2', 'position' => 0,
        ])->assertForbidden();

        $this->actingAs($stranger)->getJson(route('me.events.order', $event->uuid))->assertForbidden();
    }

    public function test_the_organiser_can_drag_through_the_endpoint_and_gets_the_new_board_back(): void
    {
        [$owner, $event, $m] = $this->scenario();

        $this->actingAs($owner)->putJson(route('me.events.order.move', $event->uuid), [
            'match' => $m['semi2']->id, 'court' => 'Mat 1', 'position' => 0,
        ])->assertOk()->assertJson(['success' => true]);

        $this->assertSame(1, $m['semi2']->fresh()->match_no);
    }

    public function test_a_refused_move_answers_422_with_the_bout_it_must_follow(): void
    {
        [$owner, $event, $m] = $this->scenario();

        $this->actingAs($owner)->putJson(route('me.events.order.move', $event->uuid), [
            'match' => $m['final']->id, 'court' => 'Mat 1', 'position' => 0,
        ])->assertStatus(422)->assertJson(['success' => false]);
    }
}
