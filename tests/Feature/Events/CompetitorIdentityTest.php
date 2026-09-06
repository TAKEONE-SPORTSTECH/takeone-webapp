<?php

namespace Tests\Feature\Events;

use App\Events\EventTypeRegistry;
use App\Models\ClubEvent;
use App\Models\ClubEventRegistration;
use App\Models\EventCategory;
use App\Models\EventMatch;
use App\Members\Models\HealthRecord;
use App\Clubs\Models\Tenant;
use App\Members\Models\User;
use App\Sports\Combat\Engine\DrawEngine;
use Tests\TestCase;

/**
 * A bout must know WHO is fighting it, not just what to print on the board.
 *
 * Every downstream feature — "you're up next, Mat 3", results on an athlete's
 * profile, ranking points, medal tallies by club — depends on each corner
 * pointing at the entry that produced it. A hand-typed name with no entry behind
 * it still has to work, because invited athletes exist.
 */
class CompetitorIdentityTest extends TestCase
{
    private function club(): Tenant
    {
        return $this->createClub($this->createUser(), ['country' => 'BH', 'currency' => 'BHD']);
    }

    private function event(Tenant $club, array $attrs = []): ClubEvent
    {
        return ClubEvent::create(array_merge([
            'tenant_id' => $club->id,
            'created_by' => $club->owner_user_id,
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
        ], $attrs));
    }

    private function entrant(ClubEvent $event, EventCategory $cat, string $name, Tenant $club): ClubEventRegistration
    {
        $user = $this->createUser([
            'full_name' => $name,
            'gender' => 'Male',
            'birthdate' => now()->subYears(25)->toDateString(),
        ]);
        $user->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);
        HealthRecord::create(['user_id' => $user->id, 'weight' => 57, 'recorded_at' => now()]);

        return ClubEventRegistration::create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'category_id' => $cat->id,
            'role' => 'participant',
            'status' => 'joined',
            'paid' => true,
            'weight' => 57,
            'registered_at' => now(),
        ]);
    }

    private function package()
    {
        return app(EventTypeRegistry::class)->get('taekwondo_tournament');
    }

    public function test_a_generated_draw_records_who_is_in_each_corner(): void
    {
        $club = $this->club();
        $event = $this->event($club);
        $cat = EventCategory::create(['event_id' => $event->id, 'name' => 'Senior Men -58 kg', 'sort_order' => 1]);

        $entries = collect(['Ali', 'Bader', 'Cyrus', 'Dawid'])
            ->map(fn ($n) => $this->entrant($event, $cat, $n, $club));

        app(DrawEngine::class)->build($event, $cat->fresh(), paidOnly: false);

        $firstRound = $cat->matches()->whereNotNull('a_name')->get();
        $this->assertNotEmpty($firstRound);

        foreach ($firstRound as $bout) {
            $this->assertNotNull($bout->a_competitor_id, 'every drawn corner points at its entry');
            $this->assertContains($bout->a_competitor_id, $entries->pluck('id')->all());
        }
    }

    public function test_the_winner_is_carried_forward_as_a_competitor_not_just_a_name(): void
    {
        $club = $this->club();
        $event = $this->event($club);
        $cat = EventCategory::create(['event_id' => $event->id, 'name' => 'Senior Men -58 kg', 'sort_order' => 1]);

        $ali = $this->entrant($event, $cat, 'Ali', $club);
        $bader = $this->entrant($event, $cat, 'Bader', $club);

        $sf = EventMatch::create([
            'event_id' => $event->id, 'category_id' => $cat->id,
            'round' => 'Semifinal', 'phase' => 'finals', 'slot' => 0,
            'a_name' => 'Ali', 'a_competitor_id' => $ali->id,
            'b_name' => 'Bader', 'b_competitor_id' => $bader->id,
            'status' => 'upcoming',
        ]);
        EventMatch::create([
            'event_id' => $event->id, 'category_id' => $cat->id,
            'round' => 'Semifinal', 'phase' => 'finals', 'slot' => 1,
            'a_name' => 'Cyrus', 'b_name' => 'Dawid', 'status' => 'upcoming',
        ]);
        $final = EventMatch::create([
            'event_id' => $event->id, 'category_id' => $cat->id,
            'round' => 'Final', 'phase' => 'finals', 'slot' => 2, 'status' => 'upcoming',
        ]);

        $this->package()->recordOutcome($event, $sf->id, ['winner' => 'a']);

        $final->refresh();
        $this->assertSame('Ali', $final->a_name);
        $this->assertSame($ali->id, $final->a_competitor_id, 'the entry travels with the athlete');
        $this->assertSame($ali->user_id, $final->competitorA->user_id);
    }

    public function test_correcting_a_result_moves_the_competitor_id_too(): void
    {
        $club = $this->club();
        $event = $this->event($club);
        $cat = EventCategory::create(['event_id' => $event->id, 'name' => 'Senior Men -58 kg', 'sort_order' => 1]);

        $ali = $this->entrant($event, $cat, 'Ali', $club);
        $bader = $this->entrant($event, $cat, 'Bader', $club);

        $sf = EventMatch::create([
            'event_id' => $event->id, 'category_id' => $cat->id,
            'round' => 'Semifinal', 'phase' => 'finals', 'slot' => 0,
            'a_name' => 'Ali', 'a_competitor_id' => $ali->id,
            'b_name' => 'Bader', 'b_competitor_id' => $bader->id,
            'status' => 'upcoming',
        ]);
        EventMatch::create([
            'event_id' => $event->id, 'category_id' => $cat->id,
            'round' => 'Semifinal', 'phase' => 'finals', 'slot' => 1, 'status' => 'upcoming',
        ]);
        $final = EventMatch::create([
            'event_id' => $event->id, 'category_id' => $cat->id,
            'round' => 'Final', 'phase' => 'finals', 'slot' => 2, 'status' => 'upcoming',
        ]);

        $package = $this->package();
        $package->recordOutcome($event, $sf->id, ['winner' => 'a']);
        $this->assertSame($ali->id, $final->fresh()->a_competitor_id);

        // Scored wrong — Bader actually won.
        $package->recordOutcome($event, $sf->id, ['winner' => 'b']);

        $final->refresh();
        $this->assertSame('Bader', $final->a_name);
        $this->assertSame($bader->id, $final->a_competitor_id, 'no stale competitor may survive a correction');
    }

    public function test_medals_identify_the_athlete_who_won_them(): void
    {
        $club = $this->club();
        $event = $this->event($club);
        $cat = EventCategory::create(['event_id' => $event->id, 'name' => 'Senior Men -58 kg', 'sort_order' => 1]);

        $ali = $this->entrant($event, $cat, 'Ali', $club);
        $bader = $this->entrant($event, $cat, 'Bader', $club);

        $final = EventMatch::create([
            'event_id' => $event->id, 'category_id' => $cat->id,
            'round' => 'Final', 'phase' => 'finals', 'slot' => 0,
            'a_name' => 'Ali', 'a_competitor_id' => $ali->id,
            'b_name' => 'Bader', 'b_competitor_id' => $bader->id,
            'status' => 'upcoming',
        ]);

        $result = $this->package()->recordOutcome($event, $final->id, ['winner' => 'a']);

        $this->assertSame($ali->id, $result['podium'][0]['competitor_id']);
        $this->assertSame($bader->id, $result['podium'][1]['competitor_id']);

        // The event-level medal list carries it too.
        $medals = $this->package()->results($event);
        $this->assertSame($ali->id, $medals[0]['medals'][0]['competitor_id']);
    }

    public function test_an_invited_athlete_with_no_entry_still_appears_on_the_board(): void
    {
        $club = $this->club();
        $event = $this->event($club);
        $cat = EventCategory::create(['event_id' => $event->id, 'name' => 'Senior Men -58 kg', 'sort_order' => 1]);

        $guest = EventMatch::create([
            'event_id' => $event->id, 'category_id' => $cat->id,
            'round' => 'Final', 'phase' => 'finals', 'slot' => 0,
            'a_name' => 'Visiting Athlete', 'b_name' => 'Another Guest', 'status' => 'upcoming',
        ]);

        $result = $this->package()->recordOutcome($event, $guest->id, ['winner' => 'a']);

        $this->assertSame('Visiting Athlete', $result['podium'][0]['name']);
        $this->assertNull($result['podium'][0]['competitor_id'], 'a guest has no entry, and that is fine');
    }

    public function test_the_manual_bracket_editor_relinks_names_to_entries(): void
    {
        $club = $this->club();
        $owner = User::find($club->owner_user_id);
        $owner->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);
        $event = $this->event($club);
        $cat = EventCategory::create(['event_id' => $event->id, 'name' => 'Senior Men -58 kg', 'sort_order' => 1]);

        $ali = $this->entrant($event, $cat, 'Ali', $club);

        $this->actingAs($owner->fresh())
            ->putJson("/me/events/{$event->uuid}/categories/{$cat->id}", [
                'status' => 'live',
                'matches' => [
                    ['round' => 'Final', 'a_name' => 'Ali', 'b_name' => 'Visiting Athlete', 'status' => 'upcoming'],
                ],
            ])->assertOk();

        $bout = $cat->matches()->firstOrFail();
        $this->assertSame($ali->id, $bout->a_competitor_id, 'a known entrant is re-linked, not left as text');
        $this->assertNull($bout->b_competitor_id, 'an unknown name stays free text');
    }

    public function test_the_bout_can_name_the_people_in_it(): void
    {
        $club = $this->club();
        $event = $this->event($club);
        $cat = EventCategory::create(['event_id' => $event->id, 'name' => 'Senior Men -58 kg', 'sort_order' => 1]);

        $ali = $this->entrant($event, $cat, 'Ali', $club);
        $bader = $this->entrant($event, $cat, 'Bader', $club);

        $bout = EventMatch::create([
            'event_id' => $event->id, 'category_id' => $cat->id,
            'round' => 'Final', 'phase' => 'finals', 'slot' => 0,
            'a_name' => 'Ali', 'a_competitor_id' => $ali->id,
            'b_name' => 'Bader', 'b_competitor_id' => $bader->id,
            'winner' => 'a', 'status' => 'done',
        ]);

        $this->assertEqualsCanonicalizing([$ali->id, $bader->id], $bout->competitorIds());
        $this->assertSame($ali->id, $bout->winnerCompetitorId());
    }
}
