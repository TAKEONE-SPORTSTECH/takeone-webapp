<?php

namespace Tests\Feature\Contracts;

/**
 * RESPONSE-SHAPE CONTRACT — GET /me/events/{event:uuid}/gallery/data
 * (route `me.events.gallery.data`, PersonalEventController@galleryData).
 *
 * CONSUMER: the event video gallery island — division shelves, the stage tab
 * strip (`stages`) and the broadcast card each bout tile draws from `arena`.
 *
 * ANY CHANGE TO THIS SHAPE IS A BREAKING CHANGE for that island. `arena` is
 * shared verbatim with the member library (/me/videos) and the bout review
 * page, so a change here ripples to three consumers, not one.
 */
class EventGalleryDataContractTest extends ContractTestCase
{
    public function test_gallery_data_returns_the_documented_shape(): void
    {
        $organiser = $this->createUser(['full_name' => 'Master Kim']);
        $club = $this->clubFor($organiser);
        $event = $this->championship($club, $organiser);
        $this->drawnDivision($event, $club);
        $this->filmFirstBout($event);

        $response = $this->actingAs($organiser->fresh())
            ->getJson("/me/events/{$event->uuid}/gallery/data")
            ->assertOk();

        $response->assertJsonStructure([
            'divisions' => [
                '*' => [
                    'id', 'title', 'subtitle', 'count',
                    'bouts' => [
                        '*' => [
                            'arena' => [
                                'event', 'stage', 'weight', 'match_no', 'court', 'referee',
                                'red' => ['name', 'tag', 'country', 'club', 'club_logo', 'photo', 'score', 'won'],
                                'blue' => ['name', 'tag', 'country', 'club', 'club_logo', 'photo', 'score', 'won'],
                                'scoring',
                            ],
                            'preview', 'match_no', 'round', 'stage', 'court',
                            'a', 'b', 'score', 'winner', 'angles', 'duration', 'poster', 'url',
                        ],
                    ],
                ],
            ],
            'stages' => [
                '*' => ['key', 'label', 'count'],
            ],
            'count',
        ]);

        $this->assertSame(['divisions', 'stages', 'count'], array_keys($response->json()));
        $this->assertSame(1, $response->json('count'));

        $division = $response->json('divisions.0');
        $this->assertSame('Senior Men -58 kg', $division['title']);
        $this->assertSame(1, $division['count']);

        $bout = $division['bouts'][0];
        $this->assertSame(1, $bout['angles']);
        // Playback is always addressed by the media file's uuid through the
        // authorising media controller — never by a storage path.
        $this->assertStringContainsString('/media/', $bout['preview']);
        $this->assertStringContainsString("/me/events/{$event->uuid}/bout/", $bout['url']);
    }

    /**
     * An event with nothing filmed keeps the same envelope — the island must
     * not have to branch on missing keys to render its empty state.
     */
    public function test_an_unfilmed_event_keeps_the_envelope(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->championship($club, $organiser);

        $response = $this->actingAs($organiser->fresh())
            ->getJson("/me/events/{$event->uuid}/gallery/data")
            ->assertOk();

        $this->assertSame(['divisions', 'stages', 'count'], array_keys($response->json()));
        $this->assertSame([], $response->json('divisions'));
        $this->assertSame([], $response->json('stages'));
        $this->assertSame(0, $response->json('count'));
    }

    /** No storage paths, disk names or file system detail reach the client. */
    public function test_gallery_data_exposes_nothing_sensitive(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->championship($club, $organiser);
        $this->drawnDivision($event, $club);
        $this->filmFirstBout($event);

        $body = $this->actingAs($organiser->fresh())
            ->getJson("/me/events/{$event->uuid}/gallery/data")
            ->assertOk()
            ->getContent();

        $this->assertLeaksNothingSensitive($body, [
            'cache/hls', 'events/x/matches', '.mp4"', '"media_file_id"', '"created_by"',
        ]);
    }

    /**
     * AUTHORIZATION — an outsider with no relationship to the event is denied.
     * A JSON request gets a real 403.
     */
    public function test_an_outsider_is_denied(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->championship($club, $organiser);
        $this->drawnDivision($event, $club);
        $this->filmFirstBout($event);

        $outsider = $this->createUser();
        $this->clubFor($outsider, ['slug' => 'other-club-'.uniqid()]);

        $this->actingAs($outsider->fresh())
            ->getJson("/me/events/{$event->uuid}/gallery/data")
            ->assertForbidden();
    }

    /** A JSON request from a guest is refused (401), never served. */
    public function test_a_guest_is_refused(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->championship($club, $organiser);

        $this->getJson("/me/events/{$event->uuid}/gallery/data")->assertUnauthorized();
    }
}
