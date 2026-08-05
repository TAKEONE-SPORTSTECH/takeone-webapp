<?php

namespace Tests\Feature\Events;

use App\Events\EventTypeRegistry;
use App\Models\ClubEvent;
use App\Models\ClubEventRegistration;
use App\Models\EventCategory;
use App\Models\EventMatch;
use Takeone\Realtime\Contracts\Publisher;
use Tests\TestCase;

/**
 * Realtime fan-out for the Taekwondo championship.
 *
 * Every write that other people can see must reach them over MQTT without a
 * refresh (CLAUDE.md → "Realtime / MQTT — Always Instant"), and the audience is
 * resolved by the PACKAGE because only it knows who a change concerns.
 */
class TaekwondoRealtimeTest extends TestCase
{
    /** Captures what would have gone to the broker. */
    private function spyOnBroker(): object
    {
        $spy = new class implements Publisher
        {
            public array $sent = [];

            public function publish(string $topic, array $payload): bool
            {
                $this->sent[] = ['topic' => $topic, 'payload' => $payload];

                return true;
            }

            public function publishMany(array $messages): bool
            {
                foreach ($messages as $m) {
                    $this->publish($m['topic'], $m['payload']);
                }

                return true;
            }
        };

        $this->app->instance(Publisher::class, $spy);
        config(['realtime.enabled' => true]);

        return $spy;
    }

    private function scenario(): array
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner, ['country' => 'BH', 'currency' => 'BHD']);
        $owner->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);

        $event = ClubEvent::create([
            'tenant_id' => $club->id,
            'created_by' => $owner->id,
            'title' => 'Spring Open',
            'event_type' => 'championship',
            'sport' => 'taekwondo',
            'scope' => 'internal',
            'date' => now()->addWeeks(2)->toDateString(),
            'end_date' => now()->addWeeks(2)->toDateString(),
            'start_time' => '09:00',
            'end_time' => '17:00',
            'status' => 'active',
            'is_archived' => false,
        ]);

        $cat = EventCategory::create(['event_id' => $event->id, 'name' => 'Senior Men -58 kg', 'sort_order' => 1]);

        // Two competitors and a spectator — all should hear about the bout.
        $watchers = [];
        foreach ([['participant', 'Ali'], ['participant', 'Bader'], ['spectator', null]] as [$role, $_]) {
            $u = $this->createUser();
            $u->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);
            ClubEventRegistration::create([
                'event_id' => $event->id, 'user_id' => $u->id,
                'category_id' => $role === 'participant' ? $cat->id : null,
                'role' => $role, 'status' => 'joined', 'paid' => true, 'registered_at' => now(),
            ]);
            $watchers[] = $u->id;
        }

        foreach ([['Ali', 'Bader'], ['Cyrus', 'Dawid']] as $i => [$a, $b]) {
            EventMatch::create([
                'event_id' => $event->id, 'category_id' => $cat->id,
                'round' => 'Semifinal', 'phase' => 'finals', 'slot' => $i,
                'a_name' => $a, 'b_name' => $b, 'status' => 'upcoming',
            ]);
        }
        EventMatch::create([
            'event_id' => $event->id, 'category_id' => $cat->id,
            'round' => 'Final', 'phase' => 'finals', 'slot' => 2, 'status' => 'upcoming',
        ]);

        return [$owner, $event, $cat->fresh(), $watchers];
    }

    private function package()
    {
        return app(EventTypeRegistry::class)->get('taekwondo_tournament');
    }

    public function test_a_bout_result_reaches_every_competitor_spectator_and_the_organiser(): void
    {
        $spy = $this->spyOnBroker();
        [$owner, $event, $cat, $watchers] = $this->scenario();

        $sf1 = $cat->matches()->where('slot', 0)->first();
        $this->package()->recordOutcome($event, $sf1->id, ['winner' => 'a', 'a_score' => '12', 'b_score' => '7']);

        $outcome = collect($spy->sent)->filter(fn ($m) => $m['payload']['action'] === 'outcome');
        $this->assertNotEmpty($outcome, 'a decided bout must publish');

        // Everyone registered, plus the organiser.
        foreach (array_merge($watchers, [$owner->id]) as $id) {
            $this->assertTrue(
                $outcome->contains(fn ($m) => $m['topic'] === \Realtime()->userTopic($id, 'events')),
                "user {$id} should have been told about the bout",
            );
        }

        $payload = $outcome->first()['payload'];
        $this->assertSame($event->uuid, $payload['event'], 'payload identifies the event by uuid, never its id');
        $this->assertSame('a', $payload['match']['winner']);
        $this->assertNotEmpty($payload['advanced'], 'the advanced athlete travels with the message');
    }

    public function test_completing_a_division_publishes_its_podium(): void
    {
        $spy = $this->spyOnBroker();
        [, $event, $cat] = $this->scenario();
        $package = $this->package();

        $package->recordOutcome($event, $cat->matches()->where('slot', 0)->first()->id, ['winner' => 'a']);
        $package->recordOutcome($event, $cat->matches()->where('slot', 1)->first()->id, ['winner' => 'a']);

        $sentBefore = count($spy->sent);
        $package->recordOutcome($event, $cat->matches()->where('round', 'Final')->first()->id, ['winner' => 'a']);

        $podium = collect(array_slice($spy->sent, $sentBefore))
            ->filter(fn ($m) => $m['payload']['action'] === 'podium');

        $this->assertNotEmpty($podium, 'the final landing must publish the medals');
        $this->assertSame('Ali', $podium->first()['payload']['podium'][0]['name']);
    }

    public function test_regenerating_the_draw_sends_a_refresh_signal(): void
    {
        $spy = $this->spyOnBroker();
        [, $event] = $this->scenario();

        $result = $this->package()->performAction($event, 'generate_draw');

        $this->assertTrue($result['success']);
        $this->assertTrue(
            collect($spy->sent)->contains(fn ($m) => $m['payload']['action'] === 'draw'),
            'a re-cut draw changes every opponent and mat — everyone must be signalled',
        );
    }

    public function test_viewing_the_bracket_page_does_not_broadcast(): void
    {
        $spy = $this->spyOnBroker();
        [$owner, $event] = $this->scenario();

        $this->actingAs($owner)->get("/me/events/{$event->uuid}/brackets")->assertOk();

        $this->assertSame([], $spy->sent, 'a page view must never spam the championship');
    }

    public function test_nothing_is_published_when_realtime_is_disabled(): void
    {
        $spy = $this->spyOnBroker();
        config(['realtime.enabled' => false]);
        [, $event, $cat] = $this->scenario();

        $this->package()->recordOutcome($event, $cat->matches()->where('slot', 0)->first()->id, ['winner' => 'a']);

        $this->assertSame([], $spy->sent);
    }
}
