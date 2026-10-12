<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * An inline `throttle:N,M` counts per ROUTE (App\Http\Middleware\
 * ThrottleRequestsPerRoute). Laravel's own keyed every inline-throttled route
 * by the user alone, so traffic on one door used up another door's allowance.
 */
class ThrottlePerRouteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('throttle:3,1')->get('/__t/strict', fn () => 'ok')->name('__t.strict');
        Route::middleware('throttle:60,1')->get('/__t/busy', fn () => 'ok')->name('__t.busy');
        // A group's throttle and the route's own: two counters, not one counted twice.
        Route::middleware(['throttle:4,1', 'throttle:4,1'])->get('/__t/double', fn () => 'ok')->name('__t.double');
        app('router')->getRoutes()->refreshNameLookups();
    }

    public function test_busy_traffic_elsewhere_does_not_use_up_a_strict_routes_allowance(): void
    {
        $user = $this->createUser();

        for ($i = 0; $i < 20; $i++) {
            $this->actingAs($user)->get('/__t/busy')->assertOk();
        }

        $this->actingAs($user)->get('/__t/strict')->assertOk();
    }

    public function test_a_route_is_still_held_to_its_own_limit(): void
    {
        $user = $this->createUser();

        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($user)->get('/__t/strict')->assertOk();
        }

        $this->actingAs($user)->get('/__t/strict')->assertStatus(429);
    }

    public function test_one_visitor_does_not_use_up_anothers(): void
    {
        $a = $this->createUser();
        $b = $this->createUser();

        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($a)->get('/__t/strict');
        }

        $this->actingAs($a)->get('/__t/strict')->assertStatus(429);
        $this->actingAs($b)->get('/__t/strict')->assertOk();
    }

    public function test_signed_out_visitors_are_limited_per_address_per_route(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->get('/__t/strict')->assertOk();
        }

        $this->get('/__t/strict')->assertStatus(429);
        $this->get('/__t/busy')->assertOk();
    }

    public function test_two_inline_throttles_on_one_route_count_each_request_once(): void
    {
        $user = $this->createUser();

        // 4 allowed; were the two counters one, the 3rd request would be refused.
        for ($i = 0; $i < 4; $i++) {
            $this->actingAs($user)->get('/__t/double')->assertOk();
        }
    }

    public function test_named_limiters_are_unchanged(): void
    {
        $user = $this->createUser();

        // `uploads` is 20/min per user, keyed by the limiter itself.
        Route::middleware('throttle:uploads')->post('/__t/named', fn () => 'ok');
        app('router')->getRoutes()->refreshNameLookups();

        for ($i = 0; $i < 20; $i++) {
            $this->actingAs($user)->post('/__t/named')->assertOk();
        }
        $this->actingAs($user)->post('/__t/named')->assertStatus(429);
    }
}
