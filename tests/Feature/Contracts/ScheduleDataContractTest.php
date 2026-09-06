<?php

namespace Tests\Feature\Contracts;

use App\Members\Models\User;
use App\Members\Models\UserScheduleSession;

/**
 * RESPONSE-SHAPE CONTRACT — GET /me/schedule/data (route `me.schedule.data`,
 * PersonalMobileController@scheduleData).
 *
 * CONSUMER: the M1 React island on /me/schedule (and the existing
 * `realtime:schedule` refresh handler, which re-fetches this endpoint and
 * re-renders the week in place).
 *
 * ANY CHANGE TO THIS SHAPE IS A BREAKING CHANGE for that island — renaming,
 * removing or re-nesting a key here silently blanks the schedule. Add keys
 * additively; never repurpose one.
 */
class ScheduleDataContractTest extends ContractTestCase
{
    private function personalSession(User $owner, User $subject, array $attrs = []): UserScheduleSession
    {
        return UserScheduleSession::create(array_merge([
            'user_id' => $owner->id,
            'subject_user_id' => $subject->id,
            'day' => 'monday',
            'start_time' => '06:30',
            'end_time' => '07:30',
            'title' => 'Morning Strength',
            'discipline' => 'Strength',
            'icon' => 'bi-lightning',
            'color' => '#7c3aed',
            'coach' => 'Self',
            'location' => 'Home gym',
            'location_meta' => ['type' => 'text'],
            'intensity' => 'medium',
            'focus' => ['legs'],
            'notes' => 'Deload week',
            'workout' => ['warmup' => ['Row 5 min'], 'main' => ['Squat 5x5'], 'cooldown' => ['Stretch']],
        ], $attrs));
    }

    /**
     * The documented payload. Every top-level key, plus the full shape of one
     * element of each collection.
     */
    public function test_schedule_data_returns_the_documented_shape(): void
    {
        $me = $this->createUser(['full_name' => 'Sara Ahmed']);
        $this->personalSession($me, $me);

        $response = $this->actingAs($me)->getJson('/me/schedule/data')->assertOk();

        $response->assertJsonStructure([
            'weekDays' => [
                '*' => ['key', 'short', 'd', 'isToday', 'isPast'],
            ],
            'sessions' => [
                '*' => [
                    'id', 'source', 'editable', 'who', 'day',
                    'start', 'end', 'start_raw', 'end_raw', 'duration',
                    'title', 'discipline', 'icon', 'color', 'coach',
                    'location', 'location_type', 'location_lat', 'location_lng', 'location_address',
                    'intensity', 'focus', 'notes',
                    'workout' => ['warmup', 'main', 'cooldown'],
                    'status',
                ],
            ],
            'members' => [
                'me' => ['key', 'user_id', 'name', 'relation', 'initials', 'color', 'avatar'],
            ],
            'todayKey',
        ]);

        // Exactly four top-level keys — an island destructuring this response
        // must be able to rely on the set, not just on the ones it reads.
        $this->assertSame(
            ['weekDays', 'sessions', 'members', 'todayKey'],
            array_keys($response->json())
        );

        $this->assertCount(7, $response->json('weekDays'));
        $this->assertContains($response->json('todayKey'), [
            'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday',
        ]);

        $session = $response->json('sessions.0');
        $this->assertSame('personal', $session['source']);
        $this->assertTrue($session['editable']);
        $this->assertSame('me', $session['who']);
        $this->assertContains($session['status'], ['done', 'today', 'upcoming']);

        // "me" is always present, and is the viewer.
        $this->assertSame($me->id, $response->json('members.me.user_id'));
    }

    /** The payload carries no secrets, storage paths or credentials. */
    public function test_schedule_data_exposes_nothing_sensitive(): void
    {
        $me = $this->createUser(['full_name' => 'Sara Ahmed']);
        $this->personalSession($me, $me);

        $body = $this->actingAs($me)->getJson('/me/schedule/data')->assertOk()->getContent();

        $this->assertLeaksNothingSensitive($body, [$me->email]);
    }

    /**
     * AUTHORIZATION — the schedule is the viewer's own. Another member's
     * sessions never appear in it, and a guest gets nothing at all.
     */
    public function test_another_members_sessions_are_never_in_my_schedule(): void
    {
        $me = $this->createUser(['full_name' => 'Sara Ahmed']);
        $stranger = $this->createUser(['full_name' => 'Omar Ali']);
        $this->personalSession($stranger, $stranger, ['title' => 'Private Sparring']);

        $response = $this->actingAs($me)->getJson('/me/schedule/data')->assertOk();

        $this->assertSame([], $response->json('sessions'));
        $this->assertSame(['me'], array_keys($response->json('members')));
        $this->assertStringNotContainsString('Private Sparring', $response->getContent());
    }

    /** A JSON request from a guest is refused (401), never served. */
    public function test_a_guest_is_refused(): void
    {
        $this->getJson('/me/schedule/data')->assertUnauthorized();
    }
}
