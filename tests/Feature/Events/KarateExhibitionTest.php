<?php

namespace Tests\Feature\Events;

use App\Events\Models\ClubEvent;
use App\Events\Models\ClubEventRegistration;
use App\Events\Models\EventCategory;
use App\Events\Models\EventExhibitionBout;
use App\Events\Models\EventMatch;
use App\Scoreboard\Sports\Karate\Mat\MatState;
use Tests\TestCase;

/**
 * Side exhibition bouts (the console's Arcade tab): any two entrants of the
 * event fought outside the draw, stored on their own table, and never able to
 * touch the draw, a result or a record.
 */
class KarateExhibitionTest extends TestCase
{
    private function scenario(): array
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner, ['country' => 'BH', 'currency' => 'BHD']);

        $event = ClubEvent::create([
            'tenant_id' => $club->id, 'created_by' => $owner->id,
            'title' => 'Exhibition Open', 'event_type' => 'championship', 'sport' => 'karate',
            'scope' => 'internal', 'status' => 'active', 'is_archived' => false,
            'date' => now()->addWeeks(2)->toDateString(), 'start_time' => '09:00', 'end_time' => '17:00',
        ]);

        $entry = fn () => ClubEventRegistration::create([
            'event_id' => $event->id, 'user_id' => $this->createUser()->id,
            'role' => 'participant', 'status' => 'registered',
        ]);
        [$a, $b, $c] = [$entry(), $entry(), $entry()];

        $cat = EventCategory::create(['event_id' => $event->id, 'name' => 'Senior Men', 'sort_order' => 1]);
        EventMatch::create([
            'event_id' => $event->id, 'category_id' => $cat->id, 'round' => 'Final', 'phase' => 'finals',
            'slot' => 0, 'a_competitor_id' => $a->id, 'b_competitor_id' => $b->id,
            'a_name' => 'Ali', 'b_name' => 'Bader', 'court' => 'Mat 1', 'status' => 'upcoming',
        ]);

        return [$owner, $event->fresh(), $a, $b, $c];
    }

    private function send($user, ClubEvent $event, array $body)
    {
        return $this->actingAs($user)->postJson("/karate/control/{$event->uuid}", ['mat' => 'Mat 1'] + $body);
    }

    public function test_an_exhibition_is_fought_and_filed_without_touching_the_draw(): void
    {
        config(['events.exhibitions' => true]);
        [$owner, $event, $a, , $c] = $this->scenario();
        $draw = EventMatch::where('event_id', $event->id)->get()->toArray();

        // Two people with no bout between them in the draw.
        $this->send($owner, $event, ['command' => 'exhibition', 'a' => $a->id, 'b' => $c->id])
            ->assertOk()->assertJsonPath('state.exhibition', true)->assertJsonPath('state.matchNo', 'EX1');

        $this->send($owner, $event, ['command' => 'point', 'side' => 'ao', 'n' => 2])->assertOk();
        $this->send($owner, $event, ['command' => 'finish'])->assertOk();
        $this->send($owner, $event, ['command' => 'commit'])->assertOk()->assertJsonPath('state.exhibition', false);

        $bout = EventExhibitionBout::where('event_id', $event->id)->sole();
        $this->assertSame('done', $bout->status);
        $this->assertSame('b', $bout->winner);
        $this->assertSame([$a->id, $c->id], [$bout->a_registration_id, $bout->b_registration_id]);

        // The draw is exactly as it was.
        $this->assertSame($draw, EventMatch::where('event_id', $event->id)->get()->toArray());

        MatState::forget($event, 'Mat 1');
    }

    public function test_another_events_entrant_is_refused(): void
    {
        config(['events.exhibitions' => true]);
        [$owner, $event, $a] = $this->scenario();
        [, , $foreign] = $this->scenario();

        $this->send($owner, $event, ['command' => 'exhibition', 'a' => $a->id, 'b' => $foreign->id])
            ->assertStatus(422);
        $this->assertSame(0, EventExhibitionBout::count());

        MatState::forget($event, 'Mat 1');
    }

    public function test_someone_who_cannot_score_is_refused(): void
    {
        config(['events.exhibitions' => true]);
        [, $event, $a, , $c] = $this->scenario();

        $this->send($this->createUser(), $event, ['command' => 'exhibition', 'a' => $a->id, 'b' => $c->id])
            ->assertForbidden();
        $this->assertSame(0, EventExhibitionBout::count());
    }

    public function test_switched_off_it_is_refused(): void
    {
        config(['events.exhibitions' => false]);
        [$owner, $event, $a, , $c] = $this->scenario();

        $this->send($owner, $event, ['command' => 'exhibition', 'a' => $a->id, 'b' => $c->id])
            ->assertStatus(422);
        $this->assertSame(0, EventExhibitionBout::count());

        MatState::forget($event, 'Mat 1');
    }
}
