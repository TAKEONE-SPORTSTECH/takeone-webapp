<?php

namespace Tests\Feature\Events;

use App\Clubs\Models\Tenant;
use App\Members\Models\User;
use App\Models\ClubEvent;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * The shared link must survive a crowd opening it at once.
 *
 * The public event page is the one surface designed to be forwarded to a lot of
 * people, and a venue is a single NAT'd address — so an allowance that a hall
 * shares is an allowance a hall exhausts. It did: the poster ran on an inline
 * `throttle:60,1`, whose counter key carries the visitor and NOT the route
 * (ThrottleRequests::resolveRequestSignature), so every inline-throttled route
 * in the application drew down one bucket per visitor. One view of the poster
 * is roughly four requests — page, two icons, manifest — which left about
 * fifteen page views a minute for everybody in the building before the link
 * started answering "just a moment".
 *
 * Fixed with a NAMED limiter, which gets a key of its own. These tests pin both
 * halves: the surface can no longer be starved at sixty, and it is still capped.
 */
class PublicEventThrottleTest extends TestCase
{
    private function publicEvent(): ClubEvent
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner, ['country' => 'BH', 'currency' => 'BHD']);

        return ClubEvent::create([
            'tenant_id' => $club->id,
            'created_by' => $owner->id,
            'title' => 'Open Cup',
            'event_type' => 'championship',
            'sport' => 'taekwondo',
            'scope' => 'internal',
            'date' => now()->addWeek()->toDateString(),
            'end_date' => now()->addWeek()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '17:00',
            'status' => 'active',
            'is_archived' => false,
            'entry_mode' => 'public',
        ]);
    }

    public function test_a_hall_sharing_one_address_is_not_cut_off_at_sixty(): void
    {
        $event = $this->publicEvent();

        // Comfortably past the old shared ceiling, from ONE address.
        for ($i = 1; $i <= 70; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.11'])
                ->get("/e/{$event->uuid}")
                ->assertOk();
        }
    }

    public function test_the_read_surface_shares_one_named_bucket_and_the_writes_do_not(): void
    {
        $limiter = RateLimiter::limiter('public-event');

        $this->assertNotNull($limiter, 'the public-event limiter is not registered');

        $limit = $limiter(request());

        $this->assertInstanceOf(Limit::class, $limit);
        $this->assertSame(300, $limit->maxAttempts, 'the ceiling moved — it is a cap, not a formality');
        $this->assertSame(1, (int) round($limit->decaySeconds / 60));

        // Entering a competition WRITES, and keeps its own far tighter limiter.
        $write = RateLimiter::limiter('public-entry-write');
        $this->assertNotNull($write);
        $this->assertSame(20, $write(request())->maxAttempts, 'a write limit must not have been loosened with the reads');
    }

    public function test_every_read_door_of_the_public_event_is_on_that_limiter(): void
    {
        $expected = [
            'events.public',
            'events.public.section',
            'events.public.manifest',
            'events.public.icon',
            'events.public.draw.data',
        ];

        foreach ($expected as $name) {
            $route = app('router')->getRoutes()->getByName($name);

            $this->assertNotNull($route, "route {$name} is missing");

            $this->assertTrue(
                collect($route->gatherMiddleware())->contains(fn ($m) => str_contains((string) $m, 'public-event')),
                "{$name} is not on the public-event limiter — it will draw down the shared inline bucket again"
            );
        }
    }
}
