<?php

namespace Tests\Feature\Events;

use App\Models\ClubEvent;
use App\Clubs\Models\Tenant;
use App\Models\User;
use Tests\TestCase;

/**
 * Creating a championship through the real create form's payload.
 *
 * The form posts one JSON body with every field it collects — including the
 * ones only a championship has (divisions, mats, weigh-in, break window) and
 * the empty arrays it always sends. This asserts the whole shape lands, not
 * the minimal payload the other tests use, because a create that silently
 * fails validation is indistinguishable from a broken button once the
 * platform's toast toggle is off.
 */
class CreateChampionshipTest extends TestCase
{
    private function owner(): array
    {
        $owner = $this->createUser(['full_name' => 'Master Kim']);
        $club = $this->createClub($owner, ['country' => 'BH', 'currency' => 'BHD']);
        $owner->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);

        return [$owner->fresh(), $club];
    }

    /** Exactly what resources/views/personal/event-create.blade.php sends. */
    private function formPayload(Tenant $club, array $overrides = []): array
    {
        return array_merge([
            'tenant_id' => $club->id,
            'title' => 'Bahrain Spring Open 2026',
            'event_type' => 'championship',
            'scope' => 'internal',
            'sport' => 'taekwondo',
            'date' => now()->addWeeks(3)->toDateString(),
            'end_date' => now()->addWeeks(3)->toDateString(),
            'start_time' => '09:00',
            'end_time' => '17:00',
            'weigh_in_at' => now()->addWeeks(3)->subDay()->format('Y-m-d H:i:s'),
            'enrollment_starts_at' => now()->toDateString(),
            'enrollment_ends_at' => now()->addWeeks(2)->toDateString(),
            'location' => 'Isa Town Sports Hall',
            'location_url' => null,
            'gps_lat' => null,
            'gps_long' => null,
            'break_start' => '12:30',
            'break_end' => '13:30',
            'courts' => 2,
            'level' => null,
            'description' => 'Open championship.',
            'participant_free' => false,
            'participant_fee' => 'BHD 10',
            'spectator_enabled' => true,
            'spectator_fee' => 'BHD 2',
            'max_capacity' => null,
            'prize' => null,
            'agenda' => [],
            'requirements' => ['Valid licence'],
            'tags' => ['taekwondo', 'open'],
            'phases' => [],
            'divisions' => [
                ['name' => 'Senior Men -58 kg', 'capacity' => 16, 'schedule' => ['preliminary' => 1, 'quarterfinals' => 1, 'finals' => 1]],
                ['name' => 'Senior Men -68 kg', 'capacity' => null, 'schedule' => ['preliminary' => 1, 'quarterfinals' => 1, 'finals' => 1]],
            ],
            'league' => null,
        ], $overrides);
    }

    public function test_the_create_form_payload_creates_a_championship(): void
    {
        [$owner, $club] = $this->owner();

        $response = $this->actingAs($owner)->postJson('/me/events', $this->formPayload($club));

        // Surface WHY on failure — a 422 body is the thing the silent form hid.
        if ($response->status() !== 200) {
            $this->fail('Create failed with '.$response->status().': '.$response->getContent());
        }

        $response->assertJson(['success' => true]);

        $event = ClubEvent::where('title', 'Bahrain Spring Open 2026')->first();
        $this->assertNotNull($event);
        $this->assertSame('taekwondo', $event->sport);
        $this->assertSame(2, $event->courts);
        $this->assertCount(2, $event->categories()->get(), 'both divisions were saved');
    }

    /** The minimum the form will let you submit (its Save button unlocks here). */
    public function test_the_minimum_payload_creates_a_championship(): void
    {
        [$owner, $club] = $this->owner();

        $response = $this->actingAs($owner)->postJson('/me/events', [
            'tenant_id' => $club->id,
            'title' => 'Bare Minimum Cup',
            'event_type' => 'championship',
            'scope' => 'internal',
            'sport' => 'taekwondo',
            'date' => now()->addWeeks(3)->toDateString(),
            'start_time' => '09:00',
            'participant_free' => true,
            'spectator_enabled' => false,
            'divisions' => [],
        ]);

        if ($response->status() !== 200) {
            $this->fail('Create failed with '.$response->status().': '.$response->getContent());
        }

        $this->assertDatabaseHas('club_events', ['title' => 'Bare Minimum Cup']);
    }

    /**
     * A refusal must arrive as a message the form can show. When it doesn't,
     * the user sees a button that appears to do nothing.
     */
    public function test_a_refused_create_returns_a_readable_reason(): void
    {
        [$owner, $club] = $this->owner();

        $response = $this->actingAs($owner)->postJson('/me/events', $this->formPayload($club, [
            'enrollment_ends_at' => now()->addYear()->toDateString(),   // after the event itself
        ]));

        $response->assertStatus(422);
        $this->assertNotEmpty(
            $response->json('errors') ?? $response->json('message'),
            'a 422 must carry errors/message — the form renders the first one',
        );
    }

    /**
     * The refusal a user is most likely to hit: registration set to close after
     * the event itself. It must come back in words that name the thing on
     * screen, not the column.
     */
    public function test_the_common_refusal_reads_in_plain_language(): void
    {
        [$owner, $club] = $this->owner();

        $response = $this->actingAs($owner)->postJson('/me/events', $this->formPayload($club, [
            'enrollment_ends_at' => now()->addYear()->toDateString(),
        ]));

        $response->assertStatus(422);
        $this->assertSame(
            'Registration must close on or before the event date.',
            $response->json('errors.enrollment_ends_at.0'),
        );
    }
}
