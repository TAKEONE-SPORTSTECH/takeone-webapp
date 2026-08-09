<?php

namespace Tests\Feature;

use App\Models\ClubEvent;
use App\Models\Tenant;
use App\Models\User;
use Tests\TestCase;

/**
 * The explore page renders a category tab only when that category can actually
 * return something — a visible tab is a promise the page has to keep.
 */
class ExploreCategoryTabsTest extends TestCase
{
    private function clubFor(User $user, array $attrs = []): Tenant
    {
        $club = $this->createClub($user, array_merge(['country' => 'BH'], $attrs));
        $user->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);

        return $club;
    }

    public function test_the_unbuilt_placeholder_categories_are_never_rendered(): void
    {
        $user = $this->createUser();
        $this->clubFor($user);

        $response = $this->actingAs($user->fresh())->get('/explore')->assertOk();

        // These had no data source and fell back to listing CLUBS, so they showed
        // the wrong results under the wrong label.
        foreach (['nutrition-clinic', 'physiotherapy-clinics', 'sports-shops', 'venues', 'supplements', 'food-plans'] as $key) {
            $response->assertDontSee('data-category="'.$key.'"', false);
        }
    }

    public function test_the_events_tab_is_hidden_when_there_are_no_open_events(): void
    {
        $user = $this->createUser();
        $this->clubFor($user);

        $this->actingAs($user->fresh())->get('/explore')->assertOk()
            ->assertDontSee('data-category="events"', false)
            ->assertSee('data-category="sports-clubs"', false);
    }

    public function test_the_events_tab_appears_once_an_open_event_exists(): void
    {
        $user = $this->createUser();
        $club = $this->clubFor($user);

        ClubEvent::create([
            'tenant_id' => $club->id, 'title' => 'Open day',
            'event_type' => 'championship', 'scope' => 'internal', 'status' => 'active',
            'is_archived' => false, 'date' => now()->addWeek()->toDateString(),
            'start_time' => '09:00:00', 'end_time' => '17:00:00',
        ]);

        $this->actingAs($user->fresh())->get('/explore')->assertOk()
            ->assertSee('data-category="events"', false);
    }

    public function test_a_finished_event_does_not_bring_the_tab_back(): void
    {
        $user = $this->createUser();
        $club = $this->clubFor($user);

        ClubEvent::create([
            'tenant_id' => $club->id, 'title' => 'Last month',
            'event_type' => 'championship', 'scope' => 'internal', 'status' => 'active',
            'is_archived' => false,
            'date' => now()->subDays(30)->toDateString(),
            'end_date' => now()->subDays(29)->toDateString(),
            'start_time' => '09:00:00', 'end_time' => '17:00:00',
        ]);

        $this->actingAs($user->fresh())->get('/explore')->assertOk()
            ->assertDontSee('data-category="events"', false);
    }

    public function test_the_trainers_tab_appears_only_when_a_trainer_exists(): void
    {
        $user = $this->createUser();
        $this->clubFor($user);

        $this->actingAs($user->fresh())->get('/explore')->assertOk()
            ->assertDontSee('data-category="personal-trainers"', false);

        $this->createUser(['is_personal_trainer' => true]);

        $this->actingAs($user->fresh())->get('/explore')->assertOk()
            ->assertSee('data-category="personal-trainers"', false);
    }

    public function test_all_is_never_offered_as_a_tab(): void
    {
        $user = $this->createUser();
        $this->clubFor($user);

        $this->actingAs($user->fresh())->get('/explore')->assertOk()
            ->assertDontSee('data-category="all"', false);

        // Still absent once there is more than one real category to filter between.
        $this->createUser(['is_personal_trainer' => true]);

        $this->actingAs($user->fresh())->get('/explore')->assertOk()
            ->assertDontSee('data-category="all"', false);
    }
}
