<?php

namespace Tests\Feature\Events;

use App\Models\ClubEvent;
use App\Models\ClubEventRegistration;
use App\Models\EventCategory;
use App\Models\EventMatch;
use App\Models\EventOfficial;
use App\Members\Models\User;
use Tests\TestCase;

/**
 * Building a bracket by hand.
 *
 * The thing being protected here is the ONE behaviour the feature exists for:
 * an organiser must be able to put an athlete into a group they do not strictly
 * belong to. A fourteen-year-old moved up because there is no junior bracket, a
 * lighter athlete given a fight at all — that is competition management, not a
 * mistake to be prevented. The range sorts the roster and marks the exception;
 * it never refuses one.
 *
 * The rest is the usual: scope every division to its event, and let the people
 * who may arrange a draw fill a group without letting them edit the event.
 */
class DivisionGroupsTest extends TestCase
{
    /** @return array{0: User, 1: ClubEvent} */
    private function scenario(): array
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner, ['country' => 'BH', 'currency' => 'BHD']);
        $owner->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);

        $event = ClubEvent::create([
            'tenant_id' => $club->id,
            'created_by' => $owner->id,
            'title' => 'Manual Draw Open',
            'event_type' => 'championship',
            'sport' => 'bjj',
            'scope' => 'internal',
            'date' => now()->addWeeks(2)->toDateString(),
            'end_date' => now()->addWeeks(2)->toDateString(),
            'start_time' => '09:00',
            'end_time' => '17:00',
            'status' => 'active',
            'is_archived' => false,
        ]);

        return [$owner, $event];
    }

    /** An entrant with whatever is known about them — often not much. */
    private function entrant(ClubEvent $event, string $name, ?string $gender, ?int $age, ?float $weight): ClubEventRegistration
    {
        $user = User::create([
            'full_name' => $name,
            'name' => $name,
            'email' => str()->slug($name).'-'.str()->random(5).'@example.test',
            'password' => bcrypt('secret-secret'),
            'gender' => $gender,
            'birthdate' => $age === null ? null : now()->subYears($age)->subMonths(2)->toDateString(),
            'email_verified_at' => now(),
        ]);

        return ClubEventRegistration::create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'role' => 'participant',
            'status' => 'joined',
            'paid' => true,
            'registered_at' => now(),
            'weight' => $weight,
        ]);
    }

    private function url(ClubEvent $event, string $tail = ''): string
    {
        return "/me/events/{$event->uuid}/divisions".$tail;
    }

    /* ---------------- Making a group ---------------- */

    public function test_an_organiser_can_create_a_group_by_hand(): void
    {
        [$owner, $event] = $this->scenario();

        $response = $this->actingAs($owner)->postJson($this->url($event), [
            'name' => 'Kids Male -40 kg',
            'gender' => 'Male',
            'min_age' => 10, 'max_age' => 13,
            'min_weight' => 30, 'max_weight' => 40,
        ]);

        $response->assertOk()
            ->assertJsonPath('division.name', 'Kids Male -40 kg')
            ->assertJsonPath('division.range.gender', 'Male')
            ->assertJsonPath('division.range.min_age', 10)
            ->assertJsonPath('division.range.max_weight', 40);

        $this->assertDatabaseHas('event_categories', [
            'event_id' => $event->id, 'name' => 'Kids Male -40 kg', 'gender' => 'Male',
        ]);
    }

    public function test_a_group_with_no_range_at_all_is_allowed(): void
    {
        [$owner, $event] = $this->scenario();

        $this->actingAs($owner)->postJson($this->url($event), ['name' => 'Open mat'])
            ->assertOk()
            ->assertJsonPath('division.range.open', true);
    }

    public function test_a_backwards_range_is_refused(): void
    {
        [$owner, $event] = $this->scenario();

        $this->actingAs($owner)->postJson($this->url($event), [
            'name' => 'Impossible', 'min_age' => 20, 'max_age' => 10,
        ])->assertStatus(422)->assertJsonValidationErrors('max_age');

        $this->actingAs($owner)->postJson($this->url($event), [
            'name' => 'Impossible', 'min_weight' => 90, 'max_weight' => 60,
        ])->assertStatus(422)->assertJsonValidationErrors('max_weight');
    }

    public function test_a_stranger_cannot_create_a_group(): void
    {
        [, $event] = $this->scenario();

        $this->actingAs($this->createUser())
            ->postJson($this->url($event), ['name' => 'Theirs'])
            ->assertForbidden();
    }

    /* ---------------- Who could go in it ---------------- */

    public function test_the_picker_offers_every_entrant_and_says_how_each_one_fits(): void
    {
        [$owner, $event] = $this->scenario();

        $fits    = $this->entrant($event, 'Fits Well', 'Male', 12, 35.0);
        $tooOld  = $this->entrant($event, 'Too Old', 'Male', 30, 35.0);
        $woman   = $this->entrant($event, 'Wrong Gender', 'Female', 12, 35.0);
        $noData  = $this->entrant($event, 'Nothing Known', null, null, null);

        $division = EventCategory::create([
            'event_id' => $event->id, 'name' => 'Kids Male -40', 'status' => 'enrolling',
            'gender' => 'Male', 'min_age' => 10, 'max_age' => 13, 'min_weight' => 30, 'max_weight' => 40,
        ]);

        $response = $this->actingAs($owner)->getJson($this->url($event, "/{$division->id}/candidates"));
        $response->assertOk();

        $by = collect($response->json('people'))->keyBy('competitor_id');

        // Nobody is hidden — the filtering is the client's job precisely so the
        // organiser can reach past it.
        $this->assertCount(4, $by);

        $this->assertSame('in', $by[$fits->id]['fit']);
        $this->assertSame('out', $by[$tooOld->id]['fit']);
        $this->assertSame(['age'], $by[$tooOld->id]['misses']);
        $this->assertSame('out', $by[$woman->id]['fit']);
        $this->assertSame(['gender'], $by[$woman->id]['misses']);

        // Missing data is its own answer, never a mismatch: birthdate is never
        // required on this platform and weight is only known after a weigh-in.
        $this->assertSame('unknown', $by[$noData->id]['fit']);
        $this->assertSame([], $by[$noData->id]['misses']);
        $this->assertNull($by[$noData->id]['age']);
        $this->assertNull($by[$noData->id]['weight']);
    }

    public function test_age_is_measured_on_the_day_of_the_event_not_today(): void
    {
        [$owner, $event] = $this->scenario();

        // Turns 14 the week after the event: still 13 on the day, so still in.
        $user = User::create([
            'full_name' => 'Birthday Soon', 'name' => 'Birthday Soon',
            'email' => 'bd-'.str()->random(5).'@example.test',
            'password' => bcrypt('secret-secret'), 'gender' => 'Male',
            'birthdate' => now()->addWeeks(3)->subYears(14)->toDateString(),
            'email_verified_at' => now(),
        ]);
        $reg = ClubEventRegistration::create([
            'event_id' => $event->id, 'user_id' => $user->id, 'role' => 'participant',
            'status' => 'joined', 'paid' => true, 'registered_at' => now(),
        ]);

        $division = EventCategory::create([
            'event_id' => $event->id, 'name' => 'Kids', 'status' => 'enrolling',
            'min_age' => 10, 'max_age' => 13,
        ]);

        $people = $this->actingAs($owner)
            ->getJson($this->url($event, "/{$division->id}/candidates"))->json('people');

        $row = collect($people)->firstWhere('competitor_id', $reg->id);

        $this->assertSame(13, $row['age']);
        $this->assertSame('in', $row['fit']);
    }

    /* ---------------- The whole point ---------------- */

    public function test_somebody_outside_the_range_can_still_be_added(): void
    {
        [$owner, $event] = $this->scenario();

        $child = $this->entrant($event, 'Young Prospect', 'Male', 14, 60.0);

        $division = EventCategory::create([
            'event_id' => $event->id, 'name' => 'Adult Male', 'status' => 'enrolling',
            'gender' => 'Male', 'min_age' => 18, 'max_age' => 40,
        ]);

        $this->actingAs($owner)
            ->putJson($this->url($event, "/{$division->id}/members"), ['add' => [$child->id]])
            ->assertOk();

        $this->assertSame($division->id, $child->fresh()->category_id);

        // And the exception stays visible afterwards rather than becoming the record.
        $row = collect($this->actingAs($owner)
            ->getJson($this->url($event, "/{$division->id}/candidates"))->json('people'))
            ->firstWhere('competitor_id', $child->id);

        $this->assertTrue($row['here']);
        $this->assertSame('out', $row['fit']);
        $this->assertSame(['age'], $row['misses']);
    }

    /*
     * ⚠️ This test used to assert the opposite.
     *
     * Ticking a placed athlete into another group MOVED them, emptying the
     * bracket they came from. That control was removed on 2026-09-09: it sat
     * one click from the ordinary tick, and the destructive one kept getting
     * clicked. Being in a group now never takes anything from another group,
     * so the old bracket must survive untouched — which is what this asserts.
     */
    public function test_entering_a_second_group_leaves_the_first_bracket_standing(): void
    {
        [$owner, $event] = $this->scenario();

        $from = EventCategory::create(['event_id' => $event->id, 'name' => 'From', 'status' => 'enrolling']);
        $to = EventCategory::create(['event_id' => $event->id, 'name' => 'To', 'status' => 'enrolling']);

        $athlete = $this->entrant($event, 'Doubles Up', 'Male', 25, 70.0);
        $athlete->update(['category_id' => $from->id]);

        $bout = EventMatch::create([
            'event_id' => $event->id, 'category_id' => $from->id,
            'round' => 'Final', 'phase' => 'finals', 'slot' => 0,
            'a_competitor_id' => $athlete->id, 'a_name' => 'Doubles Up',
            'b_name' => 'Someone', 'status' => 'upcoming',
        ]);

        $this->actingAs($owner)
            ->putJson($this->url($event, "/{$to->id}/members"), ['add' => [$athlete->id]])
            ->assertOk();

        // The original entry did not move.
        $this->assertSame($from->id, $athlete->fresh()->category_id);

        // And they are still standing in the bracket it was drawn into. This is
        // the regression the removal exists to prevent: an entry somebody paid
        // for, vanishing out of a draw nobody was asked about.
        $this->assertTrue(
            EventMatch::where('id', $bout->id)->where('a_competitor_id', $athlete->id)->exists(),
            'The first bracket lost its competitor.'
        );

        // A second entry now holds the second group.
        $this->assertSame(1, $to->registrations()->count());
        $this->assertSame(1, $from->registrations()->count());
    }

    /**
     * Moving somebody, the way it is done now: two ordinary ticks.
     *
     * No special code path, no confirmation, and each half reversible on its
     * own — which is the whole argument for having removed the one-click move.
     */
    public function test_moving_between_groups_is_two_ordinary_ticks(): void
    {
        [$owner, $event] = $this->scenario();

        $from = EventCategory::create(['event_id' => $event->id, 'name' => 'From', 'status' => 'enrolling']);
        $to = EventCategory::create(['event_id' => $event->id, 'name' => 'To', 'status' => 'enrolling']);

        $athlete = $this->entrant($event, 'Regrouped', 'Male', 25, 70.0);
        $athlete->update(['category_id' => $from->id]);

        // Tick them into the new group…
        $this->actingAs($owner)
            ->putJson($this->url($event, "/{$to->id}/members"), ['add' => [$athlete->id]])
            ->assertOk();

        // …and untick them in the old one. The picker nominates the entry each
        // group holds, so the removal names the `from` entry.
        $this->actingAs($owner)
            ->putJson($this->url($event, "/{$from->id}/members"), ['remove' => [$athlete->id]])
            ->assertOk();

        $this->assertSame(0, $from->registrations()->count());
        $this->assertSame(1, $to->registrations()->count());

        // One entry each way, and the athlete ends up in exactly one group.
        $this->assertSame(1, ClubEventRegistration::where('event_id', $event->id)
            ->where('user_id', $athlete->user_id)->whereNotNull('category_id')->count());
    }

    /** A competitor taken out of a group cannot stay in its draw. */
    public function test_unticking_someone_takes_them_out_of_that_groups_draw(): void
    {
        [$owner, $event] = $this->scenario();

        $division = EventCategory::create(['event_id' => $event->id, 'name' => 'G', 'status' => 'enrolling']);

        $athlete = $this->entrant($event, 'Withdrawn', 'Male', 25, 70.0);
        $other = $this->entrant($event, 'Opponent', 'Male', 25, 70.0);

        $this->actingAs($owner)
            ->putJson($this->url($event, "/{$division->id}/members"),
                ['add' => [$athlete->id, $other->id]])
            ->assertOk();

        $this->actingAs($owner)
            ->putJson($this->url($event, "/{$division->id}/members"), ['remove' => [$athlete->id]])
            ->assertOk();

        $this->assertSame(0, EventMatch::where('event_id', $event->id)
            ->where('category_id', $division->id)
            ->where(fn ($q) => $q->where('a_competitor_id', $athlete->id)
                                 ->orWhere('b_competitor_id', $athlete->id))
            ->count());
    }

    public function test_removing_someone_leaves_them_in_no_group(): void
    {
        [$owner, $event] = $this->scenario();

        $division = EventCategory::create(['event_id' => $event->id, 'name' => 'G', 'status' => 'enrolling']);
        $person = $this->entrant($event, 'Leaver', 'Male', 25, 70.0);
        $person->update(['category_id' => $division->id]);

        $this->actingAs($owner)
            ->putJson($this->url($event, "/{$division->id}/members"), ['remove' => [$person->id]])
            ->assertOk();

        $this->assertNull($person->fresh()->category_id);
    }

    /* ---------------- Two groups at once ---------------- */

    /*
     * The requirement this whole section exists for.
     *
     * Gi and No-Gi are not a flag on an event, they are two divisions an
     * organiser creates by hand, and an athlete who has paid for both must
     * stand in both brackets. Membership is one registration row PER DIVISION
     * (`cer_event_user_category_unq`), so "in two groups" means two entries —
     * and the acts that make and unmake them must never reach across.
     */

    public function test_entering_an_athlete_in_a_second_group_keeps_the_first(): void
    {
        [$owner, $event] = $this->scenario();

        $gi = EventCategory::create(['event_id' => $event->id, 'name' => 'Group A [Gi]', 'status' => 'enrolling']);
        $noGi = EventCategory::create(['event_id' => $event->id, 'name' => 'Group B No-Gi', 'status' => 'enrolling']);

        $athlete = $this->entrant($event, 'Both Divisions', 'Male', 25, 70.0);
        $athlete->update(['category_id' => $gi->id]);

        // The picker offers them for the second group as an ADD, not a move.
        $row = collect($this->actingAs($owner)
            ->getJson($this->url($event, "/{$noGi->id}/candidates"))->json('people'))
            ->firstWhere('person_id', $athlete->user_id);

        $this->assertFalse($row['here']);
        $this->assertSame($athlete->id, $row['enter_id']);

        $this->actingAs($owner)
            ->putJson($this->url($event, "/{$noGi->id}/members"), ['add' => [$athlete->id]])
            ->assertOk();

        // The first entry is untouched, and a second one now holds the second group.
        $this->assertSame($gi->id, $athlete->fresh()->category_id);
        $this->assertSame(1, ClubEventRegistration::where('event_id', $event->id)
            ->where('user_id', $athlete->user_id)->where('category_id', $noGi->id)->count());

        // Both counts went UP. This is the bug in one assertion: it used to be
        // 1 and 0, because the second entry took the place of the first.
        $this->assertSame(1, $gi->registrations()->count());
        $this->assertSame(1, $noGi->registrations()->count());
    }

    public function test_the_second_entry_carries_the_weigh_in_and_the_payment(): void
    {
        [$owner, $event] = $this->scenario();

        $gi = EventCategory::create(['event_id' => $event->id, 'name' => 'Gi', 'status' => 'enrolling']);
        $noGi = EventCategory::create(['event_id' => $event->id, 'name' => 'No-Gi', 'status' => 'enrolling']);

        $athlete = $this->entrant($event, 'Weighed And Paid', 'Male', 25, 71.5);
        $athlete->update([
            'category_id' => $gi->id,
            'belt_colour' => 'blue',
            'paid' => true,
            'weighed_in_at' => now(),
        ]);

        $this->actingAs($owner)
            ->putJson($this->url($event, "/{$noGi->id}/members"), ['add' => [$athlete->id]])
            ->assertOk();

        // One athlete, one weigh-in, one payment — however many divisions it
        // covers. A sibling that had to be weighed again would be a different
        // person to every screen that reads it.
        $sibling = ClubEventRegistration::where('event_id', $event->id)
            ->where('user_id', $athlete->user_id)->where('category_id', $noGi->id)->firstOrFail();

        $this->assertSame(71.5, (float) $sibling->weight);
        $this->assertSame('blue', $sibling->belt_colour);
        $this->assertTrue((bool) $sibling->paid);
        $this->assertNotNull($sibling->weighed_in_at);
    }

    public function test_leaving_one_group_leaves_the_other_alone(): void
    {
        [$owner, $event] = $this->scenario();

        $gi = EventCategory::create(['event_id' => $event->id, 'name' => 'Gi', 'status' => 'enrolling']);
        $noGi = EventCategory::create(['event_id' => $event->id, 'name' => 'No-Gi', 'status' => 'enrolling']);

        $athlete = $this->entrant($event, 'Two Groups', 'Male', 25, 70.0);
        $athlete->update(['category_id' => $gi->id]);

        $this->actingAs($owner)
            ->putJson($this->url($event, "/{$noGi->id}/members"), ['add' => [$athlete->id]])->assertOk();

        $sibling = ClubEventRegistration::where('event_id', $event->id)
            ->where('user_id', $athlete->user_id)->where('category_id', $noGi->id)->firstOrFail();

        // Take them out of the Gi group only.
        $this->actingAs($owner)
            ->putJson($this->url($event, "/{$gi->id}/members"), ['remove' => [$athlete->id]])
            ->assertOk();

        $this->assertNull($athlete->fresh()->category_id);
        $this->assertSame($noGi->id, $sibling->fresh()->category_id);
        $this->assertSame(0, $gi->registrations()->count());
        $this->assertSame(1, $noGi->registrations()->count());
    }

    public function test_the_picker_lists_a_person_once_and_names_every_group_they_are_in(): void
    {
        [$owner, $event] = $this->scenario();

        $a = EventCategory::create(['event_id' => $event->id, 'name' => 'Group A', 'status' => 'enrolling']);
        $b = EventCategory::create(['event_id' => $event->id, 'name' => 'Group B', 'status' => 'enrolling']);
        $c = EventCategory::create(['event_id' => $event->id, 'name' => 'Group C', 'status' => 'enrolling']);

        $athlete = $this->entrant($event, 'Busy Competitor', 'Male', 25, 70.0);
        $athlete->update(['category_id' => $a->id]);

        $this->actingAs($owner)
            ->putJson($this->url($event, "/{$b->id}/members"), ['add' => [$athlete->id]])->assertOk();

        $people = $this->actingAs($owner)
            ->getJson($this->url($event, "/{$c->id}/candidates"))->json('people');

        // ONE row, however many entries they hold. Two rows for one name with
        // nothing to tell them apart is what made the second one a trap.
        $rows = collect($people)->where('person_id', $athlete->user_id);
        $this->assertCount(1, $rows);

        $row = $rows->first();
        $names = collect($row['other_divisions'])->pluck('name')->sort()->values()->all();

        // BOTH groups named, not just whichever came back first.
        $this->assertSame(['Group A', 'Group B'], $names);

        // One control, and it acts on one entry — whichever the row nominates.
        $this->assertFalse($row['here']);
        $this->assertContains($row['enter_id'], $row['entry_ids']);
    }

    /*
     * There is nothing left to collide.
     *
     * These three cases used to be refusals (422) — a move aimed at a division
     * the athlete already held would have violated
     * `unique(event_id, user_id, category_id)`, so the endpoint had to catch it
     * and say so. With the move gone, "be in this group" is simply already
     * true, and the honest answer is to do nothing rather than to complain.
     */

    public function test_ticking_someone_already_in_the_group_changes_nothing(): void
    {
        [$owner, $event] = $this->scenario();

        $gi = EventCategory::create(['event_id' => $event->id, 'name' => 'Gi', 'status' => 'enrolling']);
        $noGi = EventCategory::create(['event_id' => $event->id, 'name' => 'No-Gi', 'status' => 'enrolling']);

        $athlete = $this->entrant($event, 'Already Here', 'Male', 25, 70.0);
        $athlete->update(['category_id' => $gi->id]);

        $this->actingAs($owner)
            ->putJson($this->url($event, "/{$noGi->id}/members"), ['add' => [$athlete->id]])->assertOk();

        $sibling = ClubEventRegistration::where('event_id', $event->id)
            ->where('user_id', $athlete->user_id)->where('category_id', $noGi->id)->firstOrFail();

        // Asking for the Gi group again, naming the No-Gi entry. They already
        // hold Gi, so there is nothing to add — and no third entry appears.
        $this->actingAs($owner)
            ->putJson($this->url($event, "/{$gi->id}/members"), ['add' => [$sibling->id]])
            ->assertOk();

        $this->assertSame($gi->id, $athlete->fresh()->category_id);
        $this->assertSame($noGi->id, $sibling->fresh()->category_id);
        $this->assertSame(2, ClubEventRegistration::where('event_id', $event->id)
            ->where('user_id', $athlete->user_id)->count());
    }

    public function test_a_batch_mixing_a_newcomer_and_an_existing_member_lands_both(): void
    {
        [$owner, $event] = $this->scenario();

        $division = EventCategory::create(['event_id' => $event->id, 'name' => 'G', 'status' => 'enrolling']);

        $existing = $this->entrant($event, 'Already In', 'Male', 25, 70.0);
        $existing->update(['category_id' => $division->id]);
        $newcomer = $this->entrant($event, 'Newcomer', 'Male', 26, 72.0);

        // A save carries every ticked row, including the ones that were already
        // ticked when the sheet opened. The no-op must not stop the newcomer.
        $this->actingAs($owner)
            ->putJson($this->url($event, "/{$division->id}/members"),
                ['add' => [$existing->id, $newcomer->id]])
            ->assertOk();

        $this->assertSame($division->id, $newcomer->fresh()->category_id);
        $this->assertSame(2, $division->registrations()->count());
    }

    public function test_both_entries_of_one_athlete_aimed_at_one_group_land_once(): void
    {
        [$owner, $event] = $this->scenario();

        $gi = EventCategory::create(['event_id' => $event->id, 'name' => 'Gi', 'status' => 'enrolling']);
        $noGi = EventCategory::create(['event_id' => $event->id, 'name' => 'No-Gi', 'status' => 'enrolling']);
        $target = EventCategory::create(['event_id' => $event->id, 'name' => 'Target', 'status' => 'enrolling']);

        $athlete = $this->entrant($event, 'Twice Over', 'Male', 25, 70.0);
        $athlete->update(['category_id' => $gi->id]);
        $this->actingAs($owner)
            ->putJson($this->url($event, "/{$noGi->id}/members"), ['add' => [$athlete->id]])->assertOk();
        $sibling = ClubEventRegistration::where('event_id', $event->id)
            ->where('user_id', $athlete->user_id)->where('category_id', $noGi->id)->firstOrFail();

        // Both of one athlete's entries told to be in one group. The second is
        // already true by the time it is read, so exactly one entry is made —
        // and the unique index is never argued with.
        $this->actingAs($owner)
            ->putJson($this->url($event, "/{$target->id}/members"),
                ['add' => [$athlete->id, $sibling->id]])
            ->assertOk();

        $this->assertSame(1, $target->registrations()->count());
        $this->assertSame($gi->id, $athlete->fresh()->category_id);
        $this->assertSame($noGi->id, $sibling->fresh()->category_id);
    }

    /** Three groups at once, and the row says so about all three. */
    public function test_an_athlete_in_three_groups_shows_all_three(): void
    {
        [$owner, $event] = $this->scenario();

        $a = EventCategory::create(['event_id' => $event->id, 'name' => 'Gi 60', 'status' => 'enrolling']);
        $b = EventCategory::create(['event_id' => $event->id, 'name' => 'No-Gi 60', 'status' => 'enrolling']);
        $c = EventCategory::create(['event_id' => $event->id, 'name' => 'Absolute', 'status' => 'enrolling']);
        $d = EventCategory::create(['event_id' => $event->id, 'name' => 'Elsewhere', 'status' => 'enrolling']);

        $athlete = $this->entrant($event, 'Three Divisions', 'Male', 25, 70.0);
        $athlete->update(['category_id' => $a->id]);

        foreach ([$b, $c] as $division) {
            $this->actingAs($owner)
                ->putJson($this->url($event, "/{$division->id}/members"), ['add' => [$athlete->id]])
                ->assertOk();
        }

        $this->assertSame(3, ClubEventRegistration::where('event_id', $event->id)
            ->where('user_id', $athlete->user_id)->whereNotNull('category_id')->count());

        $row = collect($this->actingAs($owner)
            ->getJson($this->url($event, "/{$d->id}/candidates"))->json('people'))
            ->firstWhere('person_id', $athlete->user_id);

        // ONE row, and ALL THREE groups named on it — never one standing in for
        // the rest, which is what the row used to do.
        $names = collect($row['other_divisions'])->pluck('name')->sort()->values()->all();
        $this->assertSame(['Absolute', 'Gi 60', 'No-Gi 60'], $names);
    }

    public function test_the_draw_in_each_group_includes_the_athlete_who_is_in_both(): void
    {
        [$owner, $event] = $this->scenario();

        $gi = EventCategory::create(['event_id' => $event->id, 'name' => 'Gi', 'status' => 'enrolling']);
        $noGi = EventCategory::create(['event_id' => $event->id, 'name' => 'No-Gi', 'status' => 'enrolling']);

        // A bracket needs somebody to fight.
        $giOpponent = $this->entrant($event, 'Gi Opponent', 'Male', 25, 70.0);
        $noGiOpponent = $this->entrant($event, 'No-Gi Opponent', 'Male', 25, 70.0);
        $athlete = $this->entrant($event, 'Doubles Up', 'Male', 25, 70.0);

        // Filled through the endpoint rather than by setting the column, so
        // each division's draw is actually CUT — the point of the test is that
        // the second entry joins a bracket that already exists and does not
        // disturb the first.
        $this->actingAs($owner)
            ->putJson($this->url($event, "/{$gi->id}/members"), ['add' => [$giOpponent->id, $athlete->id]])
            ->assertOk();
        $this->actingAs($owner)
            ->putJson($this->url($event, "/{$noGi->id}/members"), ['add' => [$noGiOpponent->id]])
            ->assertOk();

        $this->actingAs($owner)
            ->putJson($this->url($event, "/{$noGi->id}/members"), ['add' => [$athlete->id]])
            ->assertOk();

        // Every entry this athlete holds, whichever division cut it.
        $mine = ClubEventRegistration::where('event_id', $event->id)
            ->where('user_id', $athlete->user_id)->pluck('id');

        $this->assertCount(2, $mine);

        // They stand in BOTH draws. Asserting per division rather than in
        // total: two bouts in one bracket and none in the other would satisfy
        // a count and miss the entire point.
        foreach ([$gi, $noGi] as $division) {
            $this->assertTrue(
                EventMatch::where('event_id', $event->id)
                    ->where('category_id', $division->id)
                    ->where(fn ($q) => $q->whereIn('a_competitor_id', $mine)
                                         ->orWhereIn('b_competitor_id', $mine))
                    ->exists(),
                "The athlete is missing from the {$division->name} draw."
            );
        }
    }

    /*
     * The schema invariant the whole feature rests on.
     *
     * `unique(event_id, user_id)` became `unique(event_id, user_id,
     * category_id)` on 2026-09-08. Both halves matter and neither is obvious
     * from reading a model, so they are pinned here: one entry per DIVISION is
     * allowed, and a second entry in the SAME division still is not.
     */
    public function test_one_entry_per_division_is_allowed_and_two_in_one_is_not(): void
    {
        [$owner, $event] = $this->scenario();

        $gi = EventCategory::create(['event_id' => $event->id, 'name' => 'Gi', 'status' => 'enrolling']);
        $noGi = EventCategory::create(['event_id' => $event->id, 'name' => 'No-Gi', 'status' => 'enrolling']);

        $athlete = $this->entrant($event, 'Index Subject', 'Male', 25, 70.0);
        $athlete->update(['category_id' => $gi->id]);

        // One per division: allowed.
        $second = $athlete->replicate();
        $second->category_id = $noGi->id;
        $second->save();
        $this->assertTrue($second->exists);

        // Two in one division: refused by the database, not merely by a
        // controller that could be bypassed.
        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);

        $third = $athlete->replicate();
        $third->category_id = $noGi->id;
        $third->save();

        unset($owner);
    }

    /* ---------------- Scope and permission ---------------- */

    public function test_a_division_from_another_event_is_invisible(): void
    {
        [$owner, $event] = $this->scenario();
        [, $other] = $this->scenario();

        $theirs = EventCategory::create(['event_id' => $other->id, 'name' => 'Theirs', 'status' => 'enrolling']);

        $this->actingAs($owner)
            ->getJson($this->url($event, "/{$theirs->id}/candidates"))
            ->assertNotFound();

        $this->actingAs($owner)
            ->putJson($this->url($event, "/{$theirs->id}/members"), ['add' => []])
            ->assertNotFound();
    }

    public function test_an_entrant_from_another_event_cannot_be_dragged_in(): void
    {
        [$owner, $event] = $this->scenario();
        [, $other] = $this->scenario();

        $division = EventCategory::create(['event_id' => $event->id, 'name' => 'G', 'status' => 'enrolling']);
        $outsider = $this->entrant($other, 'Outsider', 'Male', 25, 70.0);

        $this->actingAs($owner)
            ->putJson($this->url($event, "/{$division->id}/members"), ['add' => [$outsider->id]])
            ->assertOk();

        $this->assertNull($outsider->fresh()->category_id);
    }

    public function test_the_appointed_jury_may_fill_a_group_but_not_create_one(): void
    {
        [$owner, $event] = $this->scenario();

        $jury = $this->createUser();
        $jury->memberClubs()->syncWithoutDetaching([$event->tenant_id => ['status' => 'active']]);
        EventOfficial::create([
            'event_id' => $event->id, 'user_id' => $jury->id, 'role' => EventOfficial::ROLE_JURY,
        ]);

        $division = EventCategory::create(['event_id' => $event->id, 'name' => 'G', 'status' => 'enrolling']);
        $person = $this->entrant($event, 'Anyone', 'Male', 25, 70.0);

        // Filling one is the same act as arranging a draw.
        $this->actingAs($jury)
            ->putJson($this->url($event, "/{$division->id}/members"), ['add' => [$person->id]])
            ->assertOk();

        // Creating and deleting one is managing the event.
        $this->actingAs($jury)->postJson($this->url($event), ['name' => 'Mine'])->assertForbidden();
    }

    /* ---------------- Deleting ---------------- */

    public function test_a_group_with_people_in_it_is_not_deleted(): void
    {
        [$owner, $event] = $this->scenario();

        $division = EventCategory::create(['event_id' => $event->id, 'name' => 'Busy', 'status' => 'enrolling']);
        $this->entrant($event, 'Held', 'Male', 25, 70.0)->update(['category_id' => $division->id]);

        $this->actingAs($owner)
            ->deleteJson($this->url($event, "/{$division->id}"))
            ->assertStatus(422);

        $this->assertDatabaseHas('event_categories', ['id' => $division->id]);
    }

    public function test_an_empty_group_is_deleted(): void
    {
        [$owner, $event] = $this->scenario();

        $division = EventCategory::create(['event_id' => $event->id, 'name' => 'Spare', 'status' => 'enrolling']);

        $this->actingAs($owner)
            ->deleteJson($this->url($event, "/{$division->id}"))
            ->assertOk();

        $this->assertDatabaseMissing('event_categories', ['id' => $division->id]);
    }

    public function test_a_group_can_be_renamed_and_its_range_changed(): void
    {
        [$owner, $event] = $this->scenario();

        $division = EventCategory::create([
            'event_id' => $event->id, 'name' => 'Old', 'status' => 'enrolling', 'min_age' => 10,
        ]);

        $this->actingAs($owner)
            ->patchJson($this->url($event, "/{$division->id}"), [
                'name' => 'New', 'min_age' => null, 'gender' => 'Female',
            ])
            ->assertOk()
            ->assertJsonPath('division.name', 'New')
            ->assertJsonPath('division.range.min_age', null)
            ->assertJsonPath('division.range.gender', 'Female');
    }
}
