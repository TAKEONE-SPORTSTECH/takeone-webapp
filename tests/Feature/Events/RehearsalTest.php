<?php

namespace Tests\Feature\Events;

use App\Events\Support\Rehearsal;
use App\Models\ClubEvent;
use App\Events\Models\EventCategory;
use App\Events\Models\EventMatch;
use App\Events\Models\EventNotificationSent;
use App\Members\Models\User;
use Tests\TestCase;

/**
 * The dress rehearsal: run the whole competition before the day, then take it
 * all back.
 *
 * What is being asserted is the bargain the feature makes. It is worth nothing
 * unless BOTH halves hold:
 *
 *  1. IT REALLY RUNS. The commands that file a result — refused before the day
 *     precisely so a practice bout cannot reach a bracket — are accepted, the
 *     bout is decided, and the draw advances. A rehearsal that stops short of
 *     the part being tested tests nothing.
 *  2. IT REALLY GOES AWAY. Every row the rehearsal touched is back as it was,
 *     and the things a snapshot cannot undo — a message to an athlete, a
 *     bracket on the public page — never happened at all.
 */
class RehearsalTest extends TestCase
{
    private const MAT = 'Mat 1';

    /**
     * A jiu-jitsu championship BEFORE its day, with a two-round draw so there
     * is somewhere for a winner to advance to.
     *
     * @return array{0: User, 1: ClubEvent, 2: EventMatch, 3: EventMatch}
     */
    private function scenario(): array
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner, ['country' => 'BH', 'currency' => 'BHD']);
        $owner->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);

        $event = ClubEvent::create([
            'tenant_id' => $club->id, 'created_by' => $owner->id,
            'title' => 'Gulf Open', 'event_type' => 'championship', 'sport' => 'bjj',
            'scope' => 'internal', 'date' => now()->addWeeks(2)->toDateString(),
            'end_date' => now()->addWeeks(2)->toDateString(),
            'start_time' => '09:00', 'end_time' => '17:00',
            'status' => 'active', 'is_archived' => false,
        ]);

        $category = EventCategory::create([
            'event_id' => $event->id, 'name' => 'Adult Men Light', 'sort_order' => 1,
        ]);

        $semi = EventMatch::create([
            'event_id' => $event->id, 'category_id' => $category->id,
            'round' => 'Semi-final', 'phase' => 'semis', 'slot' => 0, 'match_no' => 1,
            'a_name' => 'Ali', 'b_name' => 'Bader', 'court' => self::MAT, 'status' => 'upcoming',
        ]);

        $final = EventMatch::create([
            'event_id' => $event->id, 'category_id' => $category->id,
            'round' => 'Final', 'phase' => 'finals', 'slot' => 0, 'match_no' => 2,
            'a_name' => null, 'b_name' => 'Chadi', 'court' => self::MAT, 'status' => 'upcoming',
        ]);

        return [$owner, $event, $semi, $final];
    }

    private function command(User $as, ClubEvent $event, string $command, array $payload = [])
    {
        return $this->actingAs($as)->postJson("/bjj/control/{$event->uuid}", array_merge([
            'mat' => self::MAT, 'command' => $command,
        ], $payload));
    }

    /** Everything a rehearsal could disturb, as one comparable value. */
    private function fingerprint(ClubEvent $event): string
    {
        return md5(json_encode([
            EventMatch::where('event_id', $event->id)->orderBy('id')->get()->toArray(),
            EventCategory::where('event_id', $event->id)->orderBy('id')->get()->toArray(),
            ClubEvent::find($event->id)->only(['status', 'started_at', 'ended_at', 'results']),
        ]));
    }

    /* ---------------- It really runs ---------------- */

    public function test_a_result_can_be_filed_during_a_rehearsal_and_the_draw_advances(): void
    {
        [$owner, $event, $semi, $final] = $this->scenario();

        // Before: the day has not started, so filing is refused. This is the
        // existing guard, and the rehearsal exists to open exactly this door.
        $this->command($owner, $event, 'load', ['match_id' => $semi->id])->assertOk();
        $this->command($owner, $event, 'start')->assertOk();
        $this->command($owner, $event, 'end', ['winner' => 'blue', 'method' => 'points'])
            ->assertStatus(422);

        app(Rehearsal::class)->begin($event->fresh(), $owner);

        $this->command($owner, $event, 'end', ['winner' => 'blue', 'method' => 'points'])->assertOk();
        $this->command($owner, $event, 'commit')->assertOk();

        $this->assertNotNull($semi->fresh()->winner, 'the bout was not decided');
        $this->assertSame('Ali', $final->fresh()->a_name, 'the winner did not advance into the final');
    }

    /* ---------------- It really goes away ---------------- */

    public function test_ending_a_rehearsal_puts_the_event_back_exactly_as_it_was(): void
    {
        [$owner, $event, $semi] = $this->scenario();

        $before = $this->fingerprint($event);

        app(Rehearsal::class)->begin($event->fresh(), $owner);

        $this->command($owner, $event, 'load', ['match_id' => $semi->id])->assertOk();
        $this->command($owner, $event, 'start')->assertOk();
        $this->command($owner, $event, 'point', ['side' => 'blue', 'source' => 'takedown'])->assertOk();
        $this->command($owner, $event, 'end', ['winner' => 'blue', 'method' => 'points'])->assertOk();
        $this->command($owner, $event, 'commit')->assertOk();

        $this->assertNotSame($before, $this->fingerprint($event), 'the rehearsal changed nothing to restore');

        app(Rehearsal::class)->end($event->fresh());

        $this->assertSame($before, $this->fingerprint($event), 'the event did not come back as it was');
        $this->assertNull($semi->fresh()->winner);

        // And the officiating rows the practice bout wrote are gone with it.
        $this->assertSame(0, \App\Scoreboard\Sports\BrazilianJiuJitsu\Mat\MatchEvent::where('event_id', $event->id)->count());
    }

    /** A message is the one thing no snapshot can take back, so none is sent. */
    public function test_a_rehearsal_notifies_nobody(): void
    {
        [$owner, $event, $semi] = $this->scenario();

        app(Rehearsal::class)->begin($event->fresh(), $owner);

        $this->command($owner, $event, 'load', ['match_id' => $semi->id])->assertOk();
        $this->command($owner, $event, 'start')->assertOk();
        $this->command($owner, $event, 'end', ['winner' => 'blue', 'method' => 'points'])->assertOk();
        $this->command($owner, $event, 'commit')->assertOk();

        // No call was claimed, which is also what stops the REAL call on the
        // day being read as already sent.
        $this->assertSame(0, EventNotificationSent::where('event_id', $event->id)->count());
    }

    /**
     * A division whose draw is RE-CUT during a rehearsal comes back whole.
     *
     * `DrawEngine` does not edit a draw, it deletes the division's bouts and
     * builds new ones — and a re-cut is triggered by ordinary things. Restoring
     * by update alone found nothing to update and deleted the new rows as
     * strangers, handing back a division with no draw at all. This is that case,
     * reduced to its bones: the bouts are gone by the time the rehearsal ends.
     */
    public function test_bouts_deleted_during_a_rehearsal_are_put_back_with_their_own_ids(): void
    {
        [$owner, $event, $semi, $final] = $this->scenario();

        $before = $this->fingerprint($event);
        $ids = EventMatch::where('event_id', $event->id)->orderBy('id')->pluck('id')->all();

        app(Rehearsal::class)->begin($event->fresh(), $owner);

        // What a re-cut does: the whole division's bouts, gone.
        EventMatch::where('event_id', $event->id)->delete();
        $this->assertSame(0, EventMatch::where('event_id', $event->id)->count());

        app(Rehearsal::class)->end($event->fresh());

        $this->assertSame($ids, EventMatch::where('event_id', $event->id)->orderBy('id')->pluck('id')->all(),
            'the bouts did not come back, or came back with new ids');
        $this->assertSame($before, $this->fingerprint($event), 'the division did not come back as it was');
        $this->assertNotNull(EventMatch::find($semi->id));
        $this->assertNotNull(EventMatch::find($final->id));
    }

    /* ---------------- The lines it will not cross ---------------- */

    public function test_a_rehearsal_cannot_begin_on_a_competition_that_has_started(): void
    {
        [$owner, $event] = $this->scenario();

        $event->started_at = now();
        $event->save();

        $this->expectException(\RuntimeException::class);
        app(Rehearsal::class)->begin($event->fresh(), $owner);
    }

    public function test_a_second_rehearsal_cannot_begin_over_a_running_one(): void
    {
        [$owner, $event] = $this->scenario();

        app(Rehearsal::class)->begin($event->fresh(), $owner);

        $this->expectException(\RuntimeException::class);
        app(Rehearsal::class)->begin($event->fresh(), $owner);
    }

    /**
     * Starting the day ends any rehearsal first, restoring the real draw.
     *
     * The fingerprint is deliberately NOT compared here: starting a competition
     * legitimately writes `started_at`, `started_by` and locks every division's
     * draw, so the event is expected to differ. What must not have survived is
     * the rehearsal's RESULT — the bout it decided and the winner it advanced.
     */
    public function test_starting_the_event_ends_a_running_rehearsal(): void
    {
        [$owner, $event, $semi] = $this->scenario();

        app(Rehearsal::class)->begin($event->fresh(), $owner);
        $this->command($owner, $event, 'load', ['match_id' => $semi->id])->assertOk();
        $this->command($owner, $event, 'start')->assertOk();
        $this->command($owner, $event, 'end', ['winner' => 'blue', 'method' => 'points'])->assertOk();
        $this->command($owner, $event, 'commit')->assertOk();

        $this->actingAs($owner)
            ->postJson(route('me.events.start', $event->uuid), ['override' => true])
            ->assertOk();

        $this->assertFalse(app(Rehearsal::class)->active($event->fresh()));
        $this->assertNotNull($event->fresh()->started_at);

        // The competition begins with nothing decided, whatever was rehearsed.
        $this->assertNull($semi->fresh()->winner, 'the day started on top of a rehearsal result');
        $this->assertSame(0, EventMatch::where('event_id', $event->id)->whereNotNull('winner')->count());
    }

    /** Only whoever runs the event may start or end one. */
    public function test_the_rehearsal_doors_are_organiser_only(): void
    {
        [, $event] = $this->scenario();
        $stranger = $this->createUser();

        $this->actingAs($stranger)->postJson(route('me.events.rehearsal.begin', $event->uuid))->assertForbidden();
        $this->actingAs($stranger)->deleteJson(route('me.events.rehearsal.end', $event->uuid))->assertForbidden();
    }
}
