<?php

namespace Tests\Feature\Federations;

use App\Federations\Models\Federation;
use App\Federations\Models\FederationEvent;
use Tests\TestCase;

/**
 * One status on every screen (FederationEvent::lifecycleStatus).
 *
 * What this protects: an event still typed "Planning" whose dates are over
 * reads Completed; an upcoming one keeps what the organiser typed; a status
 * the organiser set by hand (Live, Completed) is never second-guessed; and
 * the dashboard's event feed carries the derived status beside the typed one.
 */
class EventLifecycleStatusTest extends TestCase
{
    private function event(string $status, string $startsOn): FederationEvent
    {
        $owner = $this->createUser();
        $fed = (new Federation)->forceFill([
            'owner_user_id' => $owner->id, 'name' => 'Test Federation', 'short_name' => 'TF',
            'country' => 'BH', 'currency' => 'BHD', 'sport' => 'Karate', 'color' => '#1e2c4f',
            'status' => Federation::STATUS_APPROVED, 'approved_at' => now(),
        ]);
        $fed->save();
        $e = (new FederationEvent)->forceFill([
            'federation_id' => $fed->id, 'created_by' => $owner->id, 'name' => 'Cup', 'sport' => 'Karate',
            'status' => $status, 'starts_on' => $startsOn, 'details' => [],
        ]);
        $e->save();

        return $e;
    }

    public function test_a_past_event_still_typed_planning_reads_completed(): void
    {
        $this->assertSame('Completed', $this->event('Planning', now()->subWeek()->format('Y-m-d').'T09:00')->lifecycleStatus());
        $this->assertSame('Completed', $this->event('Registration open', now()->subWeek()->format('Y-m-d').'T09:00')->lifecycleStatus());
    }

    public function test_an_upcoming_event_keeps_what_was_typed(): void
    {
        $this->assertSame('Planning', $this->event('Planning', now()->addMonth()->format('Y-m-d').'T09:00')->lifecycleStatus());
        $this->assertSame('Registration open', $this->event('Registration open', now()->addMonth()->format('Y-m-d').'T09:00')->lifecycleStatus());
    }

    public function test_a_status_set_by_hand_is_kept(): void
    {
        $this->assertSame('Live', $this->event('Live', now()->subWeek()->format('Y-m-d').'T09:00')->lifecycleStatus());
    }

    public function test_the_feed_carries_both(): void
    {
        $shape = app(\App\Federations\Services\FederationEvents::class)->shape($this->event('Planning', now()->subWeek()->format('Y-m-d').'T09:00'));

        $this->assertSame('Completed', $shape['status']);
        $this->assertSame('Planning', $shape['statusTyped']);
    }
}
