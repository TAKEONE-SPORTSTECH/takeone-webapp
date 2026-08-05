<?php

namespace Tests\Feature\Events;

use App\Events\EventTypeRegistry;
use App\Events\Sports\Taekwondo\Tournament\Tournament;
use App\Models\ClubEvent;
use App\Models\ClubEventRegistration;
use App\Models\EventCategory;
use App\Models\EventMatch;
use App\Models\HealthRecord;
use App\Models\Tenant;
use App\Models\User;
use Tests\TestCase;

/**
 * The Taekwondo Tournament package — the reference event-type migration.
 *
 * Covers what the package owns end to end: which events it claims, the weight
 * gate that decides who may compete, the bracket advancement engine, medal
 * awarding, and the refusal to accept a hand-typed podium.
 */
class TaekwondoTournamentPackageTest extends TestCase
{
    private function club(User $owner): Tenant
    {
        return $this->createClub($owner, ['country' => 'BH', 'currency' => 'BHD']);
    }

    private function joinClub(User $user, Tenant $club): void
    {
        $user->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);
    }

    private function championship(User $creator, Tenant $club, array $attrs = []): ClubEvent
    {
        return ClubEvent::create(array_merge([
            'tenant_id' => $club->id,
            'created_by' => $creator->id,
            'title' => 'Spring Open',
            'event_type' => 'championship',
            'sport' => 'taekwondo',
            'scope' => 'internal',
            'date' => now()->addWeeks(2)->toDateString(),
            'end_date' => now()->addWeeks(2)->toDateString(),
            'start_time' => '09:00',
            'end_time' => '17:00',
            'status' => 'active',
            'is_archived' => false,
            'spectator_enabled' => true,
            'spectator_fee' => 'Free',
        ], $attrs));
    }

    private function competitor(Tenant $club, string $gender, string $birthdate, float $weight): User
    {
        $user = $this->createUser(['gender' => $gender, 'birthdate' => $birthdate]);
        $this->joinClub($user, $club);
        HealthRecord::create([
            'user_id' => $user->id,
            'weight' => $weight,
            'recorded_at' => now(),
        ]);

        return $user->fresh();
    }

    private function package(): Tournament
    {
        return app(EventTypeRegistry::class)->get('taekwondo_tournament');
    }

    /* ---------------- Ownership ---------------- */

    public function test_package_claims_only_its_own_events(): void
    {
        $registry = app(EventTypeRegistry::class);

        $this->assertSame('taekwondo_tournament', $registry->for(
            new ClubEvent(['event_type' => 'championship', 'sport' => 'taekwondo'])
        )->key());

        $this->assertSame('taekwondo_tournament', $registry->for(
            new ClubEvent(['event_type' => 'tournament', 'sport' => 'taekwondo'])
        )->key());

        // Same sport, wrong type → not ours.
        $this->assertSame('generic', $registry->for(
            new ClubEvent(['event_type' => 'belt_test', 'sport' => 'taekwondo'])
        )->key());

        // Same type, different sport → not ours.
        $this->assertSame('generic', $registry->for(
            new ClubEvent(['event_type' => 'championship', 'sport' => 'karate'])
        )->key());
    }

    /* ---------------- Package folder is self-contained ---------------- */

    public function test_package_ships_its_own_strings_in_both_locales(): void
    {
        // Registered from app/Events/Sports/Taekwondo/Tournament/resources/lang
        // by EventPackageServiceProvider — no path wired by hand.
        app()->setLocale('en');
        $this->assertSame('Taekwondo Championship', $this->package()->label());
        $this->assertStringContainsString('weight category', __('event-taekwondo_tournament::messages.gate_no_weight'));

        app()->setLocale('ar');
        $this->assertSame('بطولة تايكوندو', $this->package()->label());
        $this->assertStringContainsString('الفئة الوزنية', __('sport-taekwondo::messages.division_label'));

        app()->setLocale('en');
    }

    public function test_sport_level_strings_are_shared_by_the_whole_sport(): void
    {
        // Vocabulary every Taekwondo event type shares lives on the SPORT, one
        // level up from the event type, under sport-<sport>::.
        app()->setLocale('en');
        $this->assertSame('Weight category', __('sport-taekwondo::messages.division_label'));
        $this->assertSame('Taekwondo', __('sport-taekwondo::messages.sport_label'));

        app()->setLocale('ar');
        $this->assertSame('التايكوندو', __('sport-taekwondo::messages.sport_label'));

        app()->setLocale('en');
    }

    public function test_package_view_namespace_is_registered(): void
    {
        // The namespace resolves even before the package ships screens, so a
        // view added to its folder is picked up with no further wiring.
        $this->assertContains(
            realpath(app_path('Events/Sports/Taekwondo/Tournament/resources/views')),
            array_map('realpath', view()->getFinder()->getHints()['event-taekwondo_tournament'] ?? []),
        );
    }

    /* ---------------- Enrolment gate ---------------- */

    public function test_competitor_is_routed_into_the_division_matching_their_own_weight(): void
    {
        $owner = $this->createUser();
        $club = $this->club($owner);
        $event = $this->championship($owner, $club);

        EventCategory::create(['event_id' => $event->id, 'name' => 'Senior Men -58 kg', 'sort_order' => 1]);
        EventCategory::create(['event_id' => $event->id, 'name' => 'Senior Men -68 kg', 'sort_order' => 2]);

        $athlete = $this->competitor($club, 'Male', now()->subYears(25)->toDateString(), 57.0);

        $decision = $this->package()->enrolmentGate($event, $athlete);

        $this->assertTrue($decision->allowed);
        $this->assertSame('Senior Men -58 kg', $decision->category->name);
        $this->assertSame(57.0, $decision->weight);
    }

    public function test_member_whose_weight_class_is_not_being_run_cannot_compete(): void
    {
        $owner = $this->createUser();
        $club = $this->club($owner);
        $event = $this->championship($owner, $club);

        // Only a light division is offered.
        EventCategory::create(['event_id' => $event->id, 'name' => 'Senior Men -58 kg', 'sort_order' => 1]);

        $heavy = $this->competitor($club, 'Male', now()->subYears(25)->toDateString(), 95.0);

        $decision = $this->package()->enrolmentGate($event, $heavy);

        $this->assertFalse($decision->allowed);
        $this->assertSame('no_division', $decision->code);
        $this->assertTrue($decision->offerSpectator, 'spectating is offered when the event sells tickets');
    }

    public function test_member_without_a_recorded_weight_cannot_compete(): void
    {
        $owner = $this->createUser();
        $club = $this->club($owner);
        $event = $this->championship($owner, $club);
        EventCategory::create(['event_id' => $event->id, 'name' => 'Senior Men -58 kg', 'sort_order' => 1]);

        $user = $this->createUser(['gender' => 'Male', 'birthdate' => now()->subYears(25)->toDateString()]);
        $this->joinClub($user, $club);

        $decision = $this->package()->enrolmentGate($event, $user->fresh());

        $this->assertFalse($decision->allowed);
        $this->assertSame('no_weight', $decision->code);
    }

    public function test_member_without_gender_or_birthdate_cannot_compete(): void
    {
        $owner = $this->createUser();
        $club = $this->club($owner);
        $event = $this->championship($owner, $club);

        $user = $this->createUser(['gender' => null, 'birthdate' => null]);
        $this->joinClub($user, $club);

        $decision = $this->package()->enrolmentGate($event, $user->fresh());

        $this->assertFalse($decision->allowed);
        $this->assertSame('no_profile', $decision->code);
    }

    public function test_a_woman_is_never_routed_into_a_mens_division(): void
    {
        $owner = $this->createUser();
        $club = $this->club($owner);
        $event = $this->championship($owner, $club);

        // Only men's divisions exist for this weight.
        EventCategory::create(['event_id' => $event->id, 'name' => 'Senior Men -58 kg', 'sort_order' => 1]);

        $woman = $this->competitor($club, 'Female', now()->subYears(25)->toDateString(), 57.0);

        $decision = $this->package()->enrolmentGate($event, $woman);

        $this->assertFalse($decision->allowed);
        $this->assertSame('no_division', $decision->code);
    }

    /* ---------------- Advancement engine ---------------- */

    /** Build a 4-competitor bracket: 2 semifinals feeding a final. */
    private function bracket(ClubEvent $event): EventCategory
    {
        $cat = EventCategory::create(['event_id' => $event->id, 'name' => 'Senior Men -58 kg', 'sort_order' => 1]);

        foreach ([['Ali', 'Bader'], ['Cyrus', 'Dawid']] as $i => [$a, $b]) {
            EventMatch::create([
                'event_id' => $event->id, 'category_id' => $cat->id,
                'round' => 'Semifinal', 'phase' => 'finals', 'slot' => $i,
                'a_name' => $a, 'b_name' => $b, 'status' => 'upcoming',
            ]);
        }
        EventMatch::create([
            'event_id' => $event->id, 'category_id' => $cat->id,
            'round' => 'Final', 'phase' => 'finals', 'slot' => 2, 'status' => 'upcoming',
        ]);

        return $cat->fresh();
    }

    public function test_winner_is_carried_into_the_next_bout(): void
    {
        $owner = $this->createUser();
        $club = $this->club($owner);
        $event = $this->championship($owner, $club);
        $cat = $this->bracket($event);

        $sf1 = $cat->matches()->where('slot', 0)->first();
        $this->package()->recordOutcome($event, $sf1->id, ['winner' => 'a', 'a_score' => '12', 'b_score' => '7']);

        $final = $cat->matches()->where('round', 'Final')->first();
        $this->assertSame('Ali', $final->a_name, 'semifinal 1 winner takes side A of the final');
        $this->assertNull($final->b_name, 'side B waits for the other semifinal');

        $sf2 = $cat->matches()->where('slot', 1)->first();
        $this->package()->recordOutcome($event, $sf2->id, ['winner' => 'b', 'a_score' => '3', 'b_score' => '9']);

        $this->assertSame('Dawid', $final->fresh()->b_name, 'semifinal 2 winner takes side B');
    }

    public function test_correcting_an_earlier_result_re_cuts_everything_downstream(): void
    {
        $owner = $this->createUser();
        $club = $this->club($owner);
        $event = $this->championship($owner, $club);
        $cat = $this->bracket($event);

        $sf1 = $cat->matches()->where('slot', 0)->first();
        $sf2 = $cat->matches()->where('slot', 1)->first();
        $package = $this->package();

        $package->recordOutcome($event, $sf1->id, ['winner' => 'a']);
        $package->recordOutcome($event, $sf2->id, ['winner' => 'a']);

        $final = $cat->matches()->where('round', 'Final')->first();
        $package->recordOutcome($event, $final->id, ['winner' => 'a', 'a_score' => '20', 'b_score' => '1']);
        $this->assertSame('Ali', $cat->fresh()->podium[0]['name']);

        // The first semifinal was scored wrong — the other athlete actually won.
        $package->recordOutcome($event, $sf1->id, ['winner' => 'b']);

        $final = $final->fresh();
        $this->assertSame('Bader', $final->a_name, 'the corrected winner replaces the stale name');
        $this->assertNull($final->winner, 'the final result is void — those two never fought');
        $this->assertNull($final->a_score);
        $this->assertSame('upcoming', $final->status);
    }

    public function test_medals_are_awarded_when_the_final_is_decided(): void
    {
        $owner = $this->createUser();
        $club = $this->club($owner);
        $event = $this->championship($owner, $club);
        $cat = $this->bracket($event);
        $package = $this->package();

        $sf1 = $cat->matches()->where('slot', 0)->first();
        $sf2 = $cat->matches()->where('slot', 1)->first();
        $package->recordOutcome($event, $sf1->id, ['winner' => 'a']);   // Ali beats Bader
        $package->recordOutcome($event, $sf2->id, ['winner' => 'a']);   // Cyrus beats Dawid

        $final = $cat->matches()->where('round', 'Final')->first();
        $result = $package->recordOutcome($event, $final->id, ['winner' => 'b']);   // Cyrus wins

        $this->assertTrue($result['division_complete']);

        $podium = $cat->fresh()->podium;
        $this->assertSame(['Cyrus', 1], [$podium[0]['name'], $podium[0]['place']]);
        $this->assertSame(['Ali', 2], [$podium[1]['name'], $podium[1]['place']]);

        // World Taekwondo awards two bronzes — both semifinal losers.
        $bronze = collect($podium)->where('place', 3)->pluck('name')->sort()->values()->all();
        $this->assertSame(['Bader', 'Dawid'], $bronze);
        $this->assertSame('completed', $cat->fresh()->status);
    }

    public function test_a_side_with_no_competitor_cannot_be_declared_the_winner(): void
    {
        $owner = $this->createUser();
        $club = $this->club($owner);
        $event = $this->championship($owner, $club);
        $cat = $this->bracket($event);

        $final = $cat->matches()->where('round', 'Final')->first();   // both sides still empty
        $this->package()->recordOutcome($event, $final->id, ['winner' => 'a']);

        $this->assertNull($final->fresh()->winner);
        $this->assertSame([], $cat->fresh()->podium ?? []);
    }

    /* ---------------- Results are engine-derived ---------------- */

    public function test_manager_cannot_hand_type_a_podium_over_the_bracket(): void
    {
        $owner = $this->createUser();
        $club = $this->club($owner);
        $this->joinClub($owner, $club);
        $event = $this->championship($owner, $club);

        $this->actingAs($owner)
            ->putJson("/me/events/{$event->uuid}/results", [
                'results' => [['place' => 1, 'name' => 'Someone Who Did Not Compete']],
            ])
            ->assertStatus(422)
            ->assertJson(['success' => false]);

        $this->assertNull($event->fresh()->results);
    }

    public function test_a_generic_event_still_accepts_hand_entered_winners(): void
    {
        $owner = $this->createUser();
        $club = $this->club($owner);
        $this->joinClub($owner, $club);
        $event = $this->championship($owner, $club, ['sport' => 'football', 'event_type' => 'race']);

        $this->actingAs($owner)
            ->putJson("/me/events/{$event->uuid}/results", [
                'results' => [['place' => 1, 'name' => 'Fast Runner']],
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSame('Fast Runner', $event->fresh()->results[0]['name']);
    }

    /* ---------------- Authorization ---------------- */

    public function test_a_non_manager_cannot_record_a_bout_result(): void
    {
        $owner = $this->createUser();
        $club = $this->club($owner);
        $event = $this->championship($owner, $club);
        $cat = $this->bracket($event);
        $match = $cat->matches()->where('slot', 0)->first();

        $stranger = $this->createUser();
        $this->joinClub($stranger, $club);

        $this->actingAs($stranger)
            ->postJson("/me/events/{$event->uuid}/outcomes/{$match->id}", ['winner' => 'a'])
            ->assertForbidden();

        $this->assertNull($match->fresh()->winner);
    }

    public function test_a_bout_from_another_event_cannot_be_scored_through_this_event(): void
    {
        $owner = $this->createUser();
        $club = $this->club($owner);
        $this->joinClub($owner, $club);

        $mine = $this->championship($owner, $club);
        $theirs = $this->championship($owner, $club, ['title' => 'Other Open']);
        $foreignMatch = $this->bracket($theirs)->matches()->first();

        $this->actingAs($owner)
            ->postJson("/me/events/{$mine->uuid}/outcomes/{$foreignMatch->id}", ['winner' => 'a'])
            ->assertNotFound();

        $this->assertNull($foreignMatch->fresh()->winner);
    }

    public function test_only_actions_the_package_currently_offers_can_run(): void
    {
        $owner = $this->createUser();
        $club = $this->club($owner);
        $this->joinClub($owner, $club);
        $event = $this->championship($owner, $club);

        $this->actingAs($owner)
            ->postJson("/me/events/{$event->uuid}/actions/generate_draw")
            ->assertStatus(422);   // no divisions yet → the action is not on offer
    }

    /* ---------------- Financials ---------------- */

    public function test_finance_breaks_revenue_down_by_division(): void
    {
        $owner = $this->createUser();
        $club = $this->club($owner);
        $event = $this->championship($owner, $club, ['participant_fee' => 'BHD 10']);

        $light = EventCategory::create(['event_id' => $event->id, 'name' => 'Senior Men -58 kg', 'sort_order' => 1]);
        $heavy = EventCategory::create(['event_id' => $event->id, 'name' => 'Senior Men -68 kg', 'sort_order' => 2]);

        foreach ([[$light, true], [$light, true], [$heavy, false]] as [$cat, $paid]) {
            ClubEventRegistration::create([
                'event_id' => $event->id,
                'user_id' => $this->createUser()->id,
                'category_id' => $cat->id,
                'role' => 'participant',
                'status' => 'joined',
                'paid' => $paid,
                'registered_at' => now(),
            ]);
        }

        $finance = $this->package()->finance($event);

        $this->assertSame(20.0, $finance['revenue']);
        $this->assertSame(
            [['division' => 'Senior Men -58 kg', 'entries' => 2, 'paid' => 2, 'revenue' => 20.0],
                ['division' => 'Senior Men -68 kg', 'entries' => 1, 'paid' => 0, 'revenue' => 0.0]],
            $finance['breakdown']
        );
    }
}
