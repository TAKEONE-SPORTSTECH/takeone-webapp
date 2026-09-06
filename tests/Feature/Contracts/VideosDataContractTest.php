<?php

namespace Tests\Feature\Contracts;

use App\Models\ClubEventRegistration;
use App\Members\Models\User;

/**
 * RESPONSE-SHAPE CONTRACT — GET /me/videos/data (route `me.videos.data`,
 * PersonalMobileController@videosData).
 *
 * CONSUMER: the member video library island on /me/videos — the hero count
 * (`total`) and one horizontal shelf per entry in `shelves`.
 *
 * ANY CHANGE TO THIS SHAPE IS A BREAKING CHANGE for that island. Shelves are
 * omitted entirely when empty (there is no fixed set of three), so the island
 * must key on `shelf.key` and never on array position — this test pins that
 * behaviour too.
 */
class VideosDataContractTest extends ContractTestCase
{
    /** The competitor on side A of the event's first (filmed) bout. */
    private function fighterOf(\App\Models\EventMatch $match): User
    {
        return ClubEventRegistration::findOrFail($match->a_competitor_id)->user;
    }

    public function test_videos_data_returns_the_documented_shape(): void
    {
        $organiser = $this->createUser(['full_name' => 'Master Kim']);
        $club = $this->clubFor($organiser);
        $event = $this->championship($club, $organiser);
        $this->drawnDivision($event, $club);
        $match = $this->filmFirstBout($event);
        $fighter = $this->fighterOf($match);

        $response = $this->actingAs($fighter->fresh())
            ->getJson('/me/videos/data')
            ->assertOk();

        $response->assertJsonStructure([
            'shelves' => [
                '*' => [
                    'key', 'title', 'subtitle', 'icon', 'count',
                    'items' => [
                        '*' => [
                            'kind', 'title', 'subtitle', 'meta', 'date',
                            'duration', 'poster', 'angles', 'url',
                        ],
                    ],
                ],
            ],
            'total',
        ]);

        $this->assertSame(['shelves', 'total'], array_keys($response->json()));
        $this->assertSame(1, $response->json('total'));

        $shelf = $response->json('shelves.0');
        $this->assertSame('bouts', $shelf['key']);
        $this->assertSame(1, $shelf['count']);

        // A bout item additionally carries the broadcast card + hover preview.
        $item = $shelf['items'][0];
        $this->assertSame('bout', $item['kind']);
        $this->assertArrayHasKey('arena', $item);
        $this->assertArrayHasKey('preview', $item);
        $this->assertArrayHasKey('won', $item);
        $this->assertArrayHasKey('decided', $item);
        $this->assertStringContainsString('/me/events/', $item['url']);
    }

    /**
     * A member with nothing filmed gets the same envelope with no shelves —
     * the island renders its empty state from `total`, not from a missing key.
     */
    public function test_a_member_with_no_footage_keeps_the_envelope(): void
    {
        $me = $this->createUser();

        $response = $this->actingAs($me)->getJson('/me/videos/data')->assertOk();

        $this->assertSame(['shelves', 'total'], array_keys($response->json()));
        $this->assertSame([], $response->json('shelves'));
        $this->assertSame(0, $response->json('total'));
    }

    /**
     * AUTHORIZATION — the library is the viewer's own footage. Another
     * member's bout never appears in it.
     */
    public function test_another_members_footage_is_never_in_my_library(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->championship($club, $organiser);
        $this->drawnDivision($event, $club);
        $this->filmFirstBout($event);

        $bystander = $this->createUser();
        $this->joinClub($bystander, $club);

        $response = $this->actingAs($bystander->fresh())
            ->getJson('/me/videos/data')
            ->assertOk();

        $this->assertSame([], $response->json('shelves'));
        $this->assertSame(0, $response->json('total'));
    }

    /** No storage paths, disk names or credentials reach the client. */
    public function test_videos_data_exposes_nothing_sensitive(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->championship($club, $organiser);
        $this->drawnDivision($event, $club);
        $match = $this->filmFirstBout($event);

        $body = $this->actingAs($this->fighterOf($match)->fresh())
            ->getJson('/me/videos/data')
            ->assertOk()
            ->getContent();

        $this->assertLeaksNothingSensitive($body, [
            'cache/hls', 'events/x/matches', '"media_file_id"', '"created_by"',
        ]);
    }

    /** A JSON request from a guest is refused (401), never served. */
    public function test_a_guest_is_refused(): void
    {
        $this->getJson('/me/videos/data')->assertUnauthorized();
    }
}
