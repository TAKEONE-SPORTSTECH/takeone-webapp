<?php

namespace Tests\Feature\Events;

use App\Clubs\Models\Tenant;
use App\Events\Models\ClubEvent;
use App\Members\Models\User;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * The organiser picks the event's theme colour in the editor (2026-10-12).
 * Every header is drawn from it, so it is a 6-digit hex or it is refused; left
 * unpicked, the event keeps its type's colour; an edit that does not send one
 * leaves the colour alone.
 */
class EventThemeColorTest extends TestCase
{
    private function setUpClub(): array
    {
        Queue::fake();
        $owner = $this->createUser();
        $club = $this->createClub($owner, ['country' => 'BH']);
        $owner->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);

        return [$owner->fresh(), $club];
    }

    private function payload(Tenant $club, array $extra = []): array
    {
        return $extra + [
            'tenant_id' => $club->id, 'title' => 'Colour Cup', 'event_type' => 'race', 'scope' => 'internal',
            'date' => now()->addWeek()->toDateString(), 'start_time' => '09:00',
            'participant_free' => true, 'spectator_enabled' => false,
        ];
    }

    public function test_a_chosen_colour_is_saved_at_creation(): void
    {
        [$owner, $club] = $this->setUpClub();

        $this->actingAs($owner)->postJson('/me/events', $this->payload($club, ['color' => '#2563eb']))->assertOk();

        $this->assertSame('#2563eb', ClubEvent::where('title', 'Colour Cup')->value('color'));
    }

    public function test_without_a_choice_the_type_colour_is_kept(): void
    {
        [$owner, $club] = $this->setUpClub();

        $this->actingAs($owner)->postJson('/me/events', $this->payload($club))->assertOk();

        $this->assertSame('#7c3aed', ClubEvent::where('title', 'Colour Cup')->value('color'));   // a race's colour
    }

    public function test_anything_but_a_hex_colour_is_refused(): void
    {
        [$owner, $club] = $this->setUpClub();

        foreach (['red', '#12345', 'url(javascript:alert(1))', '#2563eb;background:url(x)'] as $bad) {
            $this->actingAs($owner)->postJson('/me/events', $this->payload($club, ['color' => $bad]))
                ->assertStatus(422)->assertJsonValidationErrors('color');
        }
        $this->assertSame(0, ClubEvent::where('title', 'Colour Cup')->count());
    }

    public function test_an_edit_changes_the_colour_and_one_that_sends_none_keeps_it(): void
    {
        [$owner, $club] = $this->setUpClub();
        $this->actingAs($owner)->postJson('/me/events', $this->payload($club, ['color' => '#10b981']))->assertOk();
        $event = ClubEvent::where('title', 'Colour Cup')->firstOrFail();

        $this->actingAs($owner)->putJson("/me/events/{$event->uuid}", $this->payload($club, ['color' => '#e11d48']))->assertOk();
        $this->assertSame('#e11d48', $event->fresh()->color);

        $this->actingAs($owner)->putJson("/me/events/{$event->uuid}", $this->payload($club))->assertOk();
        $this->assertSame('#e11d48', $event->fresh()->color);
    }

    public function test_somebody_who_does_not_run_the_event_cannot_recolour_it(): void
    {
        [$owner, $club] = $this->setUpClub();
        $this->actingAs($owner)->postJson('/me/events', $this->payload($club, ['color' => '#10b981']))->assertOk();
        $event = ClubEvent::where('title', 'Colour Cup')->firstOrFail();

        $this->actingAs(User::find($this->createUser()->id))
            ->putJson("/me/events/{$event->uuid}", $this->payload($club, ['color' => '#000000']))->assertForbidden();
        $this->assertSame('#10b981', $event->fresh()->color);
    }
}
