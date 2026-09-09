<?php

namespace Tests\Feature\Events;

use App\Clubs\Models\Tenant;
use App\Members\Models\User;
use App\Models\ClubEvent;
use App\Models\ClubEventRegistration;
use App\Models\EventCategory;
use App\Models\EventMatch;
use App\Support\BoutHistory;
use Tests\TestCase;

/**
 * "When do I fight?" — the entrant's own bouts, on their own entry.
 *
 * The panel at `/e/{uuid}/my-entry` said what the ENTRY was — name, division,
 * fee, receipt — and nothing whatsoever about competing. The draw, the bracket
 * and the shaping of a bout from a competitor's own corner all already existed;
 * none of it had ever been shown to the person it is about. Added 2026-09-08.
 *
 * What these tests hold in place is mostly about restraint: the division is
 * shown because it is the athlete's own fact, the DRAW is not shown when the
 * organiser is holding it back, a bye is called a bye, and nothing is invented
 * where the organiser has not decided it yet.
 */
class MyBoutsTest extends TestCase
{
    private User $organiser;

    private Tenant $club;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organiser = $this->createUser(['full_name' => 'Organiser One']);
        $this->club = $this->createClub($this->organiser, ['country' => 'BH', 'currency' => 'BHD']);
        $this->organiser->memberClubs()->syncWithoutDetaching([$this->club->id => ['status' => 'active']]);
    }

    private function event(array $attrs = []): ClubEvent
    {
        return ClubEvent::create(array_merge([
            'tenant_id' => $this->club->id,
            'created_by' => $this->organiser->id,
            'title' => 'Spring Open',
            'event_type' => 'championship',
            'sport' => 'taekwondo',
            'scope' => 'internal',
            'date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '17:00',
            'status' => 'active',
            'is_archived' => false,
            'entry_mode' => 'public',
        ], $attrs));
    }

    private function athlete(ClubEvent $event, EventCategory $cat, string $name): ClubEventRegistration
    {
        $user = $this->createUser([
            'full_name' => $name,
            'gender' => 'Male',
            'birthdate' => now()->subYears(25)->toDateString(),
        ]);
        $user->memberClubs()->syncWithoutDetaching([$this->club->id => ['status' => 'active']]);

        return ClubEventRegistration::create([
            'event_id' => $event->id, 'user_id' => $user->id, 'category_id' => $cat->id,
            'role' => 'participant', 'status' => 'joined', 'paid' => true,
            'weight' => 57, 'registered_at' => now(),
        ]);
    }

    private function category(ClubEvent $event): EventCategory
    {
        return EventCategory::create([
            'event_id' => $event->id,
            'name' => 'Senior Men -58 kg',
            'weight_class' => '-58',
            'sort_order' => 1,
        ]);
    }

    public function test_it_returns_nothing_for_somebody_who_is_not_entered(): void
    {
        $event = $this->event();
        $stranger = $this->createUser();

        $this->assertNull(app(BoutHistory::class)->forEvent($event, $stranger, $stranger));
    }

    public function test_it_reports_the_bout_from_this_competitors_corner(): void
    {
        $event = $this->event();
        $cat = $this->category($event);
        $me = $this->athlete($event, $cat, 'Mine');
        $them = $this->athlete($event, $cat, 'Theirs');

        EventMatch::create([
            'event_id' => $event->id, 'category_id' => $cat->id,
            'round' => 'Final', 'phase' => 'finals', 'slot' => 1,
            'court' => 'Mat 1', 'match_no' => 7,
            'a_name' => 'Mine', 'a_competitor_id' => $me->id,
            'b_name' => 'Theirs', 'b_competitor_id' => $them->id,
            'a_score' => 12, 'b_score' => 5, 'winner' => 'a',
            'status' => 'done',
        ]);

        $out = app(BoutHistory::class)->forEvent($event, $me->user, $me->user);

        $this->assertSame('-58', $out['entry']['division']);
        $this->assertCount(1, $out['bouts']);

        $bout = $out['bouts'][0];

        $this->assertSame('Theirs', $bout['opponent']);
        $this->assertTrue($bout['decided']);
        $this->assertTrue($bout['won'], 'the winner is read from this competitor\'s own corner');
        // assertEquals, not assertSame: the scores come back as the strings
        // SQLite hands over, which is what `shape()` has always returned and
        // what both views print. Not this change's business to re-type.
        $this->assertEquals(12, $bout['my_score']);
        $this->assertEquals(5, $bout['their_score']);
        $this->assertSame('Mat 1', $bout['mat']);
        $this->assertEquals(7, $bout['match_no']);
        $this->assertFalse($bout['bye']);

        // And the loser's own panel says the same bout the other way round.
        $theirs = app(BoutHistory::class)->forEvent($event, $them->user, $them->user)['bouts'][0];

        $this->assertSame('Mine', $theirs['opponent']);
        $this->assertFalse($theirs['won']);
        $this->assertEquals(5, $theirs['my_score']);
    }

    /**
     * A bye is scaffolding in a profile and a FACT on the entrant's panel: "you
     * start in the next round" is one of the things they most need told.
     */
    public function test_a_bye_is_kept_and_flagged_rather_than_shown_as_an_opponent(): void
    {
        $event = $this->event();
        $cat = $this->category($event);
        $me = $this->athlete($event, $cat, 'Mine');

        EventMatch::create([
            'event_id' => $event->id, 'category_id' => $cat->id,
            'round' => 'Round of 16', 'phase' => 'preliminary', 'slot' => 1,
            'a_name' => 'Mine', 'a_competitor_id' => $me->id,
            'b_name' => null, 'b_competitor_id' => null,
            'winner' => 'a', 'status' => 'done',
        ]);

        $bouts = app(BoutHistory::class)->forEvent($event, $me->user, $me->user)['bouts'];

        $this->assertCount(1, $bouts, 'a bye must not be dropped from the entrant\'s own list');
        $this->assertTrue($bouts[0]['bye']);
    }

    public function test_the_panel_shows_the_division_but_withholds_a_draw_the_organiser_is_holding_back(): void
    {
        $event = $this->event(['draw_reveal' => ClubEvent::DRAW_HIDDEN]);
        $cat = $this->category($event);
        $me = $this->athlete($event, $cat, 'Mine');
        $them = $this->athlete($event, $cat, 'Theirs');

        EventMatch::create([
            'event_id' => $event->id, 'category_id' => $cat->id,
            'round' => 'Final', 'phase' => 'finals', 'slot' => 1,
            'a_name' => 'Mine', 'a_competitor_id' => $me->id,
            'b_name' => 'Theirs', 'b_competitor_id' => $them->id,
            'status' => 'upcoming',
        ]);

        $response = $this->actingAs($me->user)->get("/e/{$event->uuid}/my-entry")->assertOk();

        // The division is the athlete's OWN fact and is shown.
        $response->assertViewHas('drawOpen', false);
        $response->assertSee('-58', false);

        // The draw is not — and the page says WHEN, rather than going quiet.
        $response->assertDontSee('Theirs', false);
        $response->assertSee(app(\App\Events\Support\EventAccess::class)->drawHiddenMessage($event->fresh()), false);
    }

    public function test_the_panel_shows_the_bout_once_the_draw_is_open(): void
    {
        $event = $this->event(['draw_reveal' => ClubEvent::DRAW_ALWAYS]);
        $cat = $this->category($event);
        $me = $this->athlete($event, $cat, 'Mine');
        $them = $this->athlete($event, $cat, 'Theirs');

        EventMatch::create([
            'event_id' => $event->id, 'category_id' => $cat->id,
            'round' => 'Final', 'phase' => 'finals', 'slot' => 1,
            'court' => 'Tatami A', 'match_no' => 3,
            'a_name' => 'Mine', 'a_competitor_id' => $me->id,
            'b_name' => 'Theirs', 'b_competitor_id' => $them->id,
            'status' => 'upcoming',
        ]);

        $this->actingAs($me->user)
            ->get("/e/{$event->uuid}/my-entry")
            ->assertOk()
            ->assertViewHas('drawOpen', true)
            ->assertSee('Theirs', false)
            // The organiser's own name for the mat, printed verbatim — wrapping
            // it in a "Mat :mat" label produced "Mat Mat 1".
            ->assertSee('Tatami A', false)
            ->assertDontSee('Mat Tatami A', false);
    }
}
