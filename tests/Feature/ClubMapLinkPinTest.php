<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Saving a club with a Google Maps link and no pin reads the pin from the link
 * on the server, so the club lands on the map even when the form's browser
 * script never ran.
 */
class ClubMapLinkPinTest extends TestCase
{
    private const SHORT = 'https://maps.app.goo.gl/AbCdEf123?g_st=iw';

    private function save($owner, $club, array $fields): void
    {
        $this->actingAs($owner)->put(route('admin.club.update', $club->slug), ['club_name' => $club->club_name] + $fields);
    }

    public function test_a_short_link_with_no_pin_fills_the_pin(): void
    {
        Http::fake(['maps.app.goo.gl/*' => Http::response('', 302, ['Location' => 'https://maps.google.com?q=26.1150000,50.5091000&entry=gps'])]);
        $owner = $this->createUser();
        $club = $this->createClub($owner);
        $this->makeClubAdmin($owner, $club);

        $this->save($owner, $club, ['maps_url' => self::SHORT, 'gps_lat' => '', 'gps_long' => '']);

        $club->refresh();
        $this->assertSame(self::SHORT, $club->maps_url);
        $this->assertEqualsWithDelta(26.115, (float) $club->gps_lat, 0.00001);
        $this->assertEqualsWithDelta(50.5091, (float) $club->gps_long, 0.00001);
    }

    public function test_a_pin_the_admin_placed_is_kept(): void
    {
        Http::fake();
        $owner = $this->createUser();
        $club = $this->createClub($owner);
        $this->makeClubAdmin($owner, $club);

        $this->save($owner, $club, ['maps_url' => self::SHORT, 'gps_lat' => '26.2', 'gps_long' => '50.6']);

        $club->refresh();
        $this->assertEqualsWithDelta(26.2, (float) $club->gps_lat, 0.00001);
        $this->assertEqualsWithDelta(50.6, (float) $club->gps_long, 0.00001);
        Http::assertNothingSent();
    }

    public function test_a_link_that_is_not_google_is_never_followed(): void
    {
        Http::fake();
        $owner = $this->createUser();
        $club = $this->createClub($owner);
        $this->makeClubAdmin($owner, $club);

        $this->save($owner, $club, ['maps_url' => 'https://evil.example.com/x', 'gps_lat' => '', 'gps_long' => '']);

        Http::assertNothingSent();
        $this->assertNull($club->fresh()->gps_lat);
    }
}
