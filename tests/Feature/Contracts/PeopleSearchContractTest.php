<?php

namespace Tests\Feature\Contracts;

/**
 * RESPONSE-SHAPE CONTRACT — GET /me/people/search (route `me.people.search`,
 * PeopleController@search).
 *
 * CONSUMER: the "Find People" React island on /me/people (search box + result
 * cards + the follow button, which reads `is_following` and navigates to
 * `profile_url`).
 *
 * ANY CHANGE TO THIS SHAPE IS A BREAKING CHANGE for that island. In
 * particular the island keys result rows by `uuid` and links by `profile_url`
 * — neither may be dropped or replaced by a numeric id.
 */
class PeopleSearchContractTest extends ContractTestCase
{
    public function test_people_search_returns_the_documented_shape(): void
    {
        $owner = $this->createUser();
        $club = $this->clubFor($owner);

        $me = $this->createUser(['full_name' => 'Sara Ahmed']);
        $mate = $this->createUser(['full_name' => 'Confirmed Mate', 'gender' => 'Male']);
        $this->joinClub($me, $club);
        $this->joinClub($mate, $club);

        $response = $this->actingAs($me->fresh())
            ->getJson('/me/people/search?q=Confirmed')
            ->assertOk();

        $response->assertJsonStructure([
            'success',
            'query',
            'people' => [
                '*' => [
                    'uuid', 'slug', 'name', 'avatar', 'gender',
                    'is_trainer', 'is_following', 'profile_url',
                ],
            ],
        ]);

        $this->assertSame(['success', 'query', 'people'], array_keys($response->json()));
        $this->assertTrue($response->json('success'));
        $this->assertSame('Confirmed', $response->json('query'));

        $row = $response->json('people.0');
        $this->assertSame($mate->uuid, $row['uuid']);
        $this->assertSame('Confirmed Mate', $row['name']);
        $this->assertIsBool($row['is_trainer']);
        $this->assertIsBool($row['is_following']);
        $this->assertStringContainsString('/people/'.$mate->uuid, $row['profile_url']);

        // The row is exactly these eight keys — nothing else about the person.
        $this->assertSame(
            ['uuid', 'slug', 'name', 'avatar', 'gender', 'is_trainer', 'is_following', 'profile_url'],
            array_keys($row)
        );
    }

    /**
     * An empty result set keeps the same envelope — the island must not have
     * to branch on a missing `people` key.
     */
    public function test_an_empty_result_keeps_the_envelope(): void
    {
        $owner = $this->createUser();
        $club = $this->clubFor($owner);
        $me = $this->createUser(['full_name' => 'Sara Ahmed']);
        $this->joinClub($me, $club);

        $response = $this->actingAs($me->fresh())
            ->getJson('/me/people/search?q=NoSuchPerson')
            ->assertOk();

        $this->assertSame(['success', 'query', 'people'], array_keys($response->json()));
        $this->assertSame([], $response->json('people'));
    }

    /**
     * AUTHORIZATION — search only ever reaches confirmed club-mates. Someone
     * from another club is not a result, however exactly the query matches.
     */
    public function test_a_member_of_another_club_is_never_returned(): void
    {
        $ownerA = $this->createUser();
        $clubA = $this->clubFor($ownerA);
        $ownerB = $this->createUser();
        $clubB = $this->clubFor($ownerB, ['slug' => 'other-club-'.uniqid()]);

        $me = $this->createUser(['full_name' => 'Sara Ahmed']);
        $this->joinClub($me, $clubA);

        $outsider = $this->createUser(['full_name' => 'Outsider Person']);
        $this->joinClub($outsider, $clubB);

        $response = $this->actingAs($me->fresh())
            ->getJson('/me/people/search?q=Outsider')
            ->assertOk();

        $this->assertSame([], $response->json('people'));
        $this->assertStringNotContainsString($outsider->uuid, $response->getContent());
    }

    /** No emails, phone numbers, ids or credentials cross the wire. */
    public function test_people_search_exposes_nothing_sensitive(): void
    {
        $owner = $this->createUser();
        $club = $this->clubFor($owner);
        $me = $this->createUser(['full_name' => 'Sara Ahmed']);
        $mate = $this->createUser(['full_name' => 'Confirmed Mate']);
        $this->joinClub($me, $club);
        $this->joinClub($mate, $club);

        $body = $this->actingAs($me->fresh())
            ->getJson('/me/people/search?q=Confirmed')
            ->assertOk()
            ->getContent();

        $this->assertLeaksNothingSensitive($body, [$mate->email, '"email"', '"mobile"', '"id"']);
    }

    /** A JSON request from a guest is refused (401), never served. */
    public function test_a_guest_is_refused(): void
    {
        $this->getJson('/me/people/search?q=a')->assertUnauthorized();
    }
}
