<?php

namespace Tests\Feature\Events;

use App\Events\Models\ClubEventRegistration;
use App\Events\Models\EventCategory;
use App\Events\Models\EventMatch;
use Tests\Feature\Contracts\ContractTestCase;

/**
 * Delete every division / auto-place by the rule book (App\Events\Support\DivisionReset)
 * through me.events.divisions.delete-all and .auto-place.
 */
class DivisionResetTest extends ContractTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['events.division_merge.enabled' => true]);
    }

    private function event(): array
    {
        $o = $this->createUser();
        $e = $this->championship($this->clubFor($o), $o, ['sport' => 'karate']);
        $old = EventCategory::create(['event_id' => $e->id, 'name' => 'Old group', 'sort_order' => 1]);
        $kids = [];
        foreach ([[24, 9], [25, 9], [60, 30]] as $k => [$kg, $age]) {
            $u = $this->createUser(['full_name' => 'Kid '.$k, 'gender' => 'Male', 'birthdate' => now()->subYears($age)->subMonths(2)->toDateString()]);
            $kids[] = ClubEventRegistration::create(['event_id' => $e->id, 'user_id' => $u->id, 'category_id' => $old->id, 'role' => 'participant', 'status' => 'joined', 'weight' => $kg]);
        }
        // Somebody with no weight on file cannot be classified.
        $u = $this->createUser(['full_name' => 'No Weight', 'gender' => 'Male', 'birthdate' => now()->subYears(20)->toDateString()]);
        ClubEventRegistration::create(['event_id' => $e->id, 'user_id' => $u->id, 'role' => 'participant', 'status' => 'joined']);

        return [$o, $e, $old, $kids];
    }

    public function test_delete_all_then_auto_place_rebuilds_only_divisions_somebody_fits(): void
    {
        [$o, $e, $old] = $this->event();
        $entries = ClubEventRegistration::where('event_id', $e->id)->orderBy('id')->get(['id', 'user_id', 'paid', 'weight', 'status'])->toArray();

        $this->actingAs($o)->postJson(route('me.events.divisions.delete-all', $e->uuid), ['confirm' => true])->assertOk();

        // Every athlete who joined is still in the event — same rows, same people,
        // same payment and weight — only their division is gone.
        $this->assertSame($entries, ClubEventRegistration::where('event_id', $e->id)->orderBy('id')->get(['id', 'user_id', 'paid', 'weight', 'status'])->toArray());
        $this->assertSame(0, ClubEventRegistration::where('event_id', $e->id)->whereNotNull('category_id')->count());

        $this->actingAs($o)->postJson(route('me.events.divisions.delete-all', $e->uuid), ['confirm' => true, 'then_place' => true])
            ->assertOk()->assertJsonPath('success', true)->assertJsonPath('placed', 3)->assertJsonPath('left', 1);

        $this->assertNull(EventCategory::find($old->id));
        $cats = EventCategory::where('event_id', $e->id)->get();
        $this->assertNotEmpty($cats);
        foreach ($cats as $c) {
            $this->assertGreaterThan(0, ClubEventRegistration::where('category_id', $c->id)->count(), $c->name.' is not empty');
        }
        $this->assertSame(1, ClubEventRegistration::where('event_id', $e->id)->whereNull('category_id')->count(), 'only the one with no weight');
    }

    public function test_delete_all_needs_the_confirmation_and_refuses_after_a_fought_bout(): void
    {
        [$o, $e, $old, $kids] = $this->event();

        $this->actingAs($o)->postJson(route('me.events.divisions.delete-all', $e->uuid), [])->assertStatus(422);
        $this->assertNotNull(EventCategory::find($old->id));

        EventMatch::create(['event_id' => $e->id, 'category_id' => $old->id, 'round' => 1, 'match_no' => 1,
            'a_name' => 'A', 'b_name' => 'B', 'status' => 'done', 'winner' => 'a']);
        $this->actingAs($o)->postJson(route('me.events.divisions.delete-all', $e->uuid), ['confirm' => true])
            ->assertStatus(422)->assertJsonPath('success', false);
        $this->assertNotNull(EventCategory::find($old->id));
    }

    /** Auto-place can fill the divisions the organiser made by hand (owner, 2026-10-15). */
    public function test_auto_place_fills_the_events_own_divisions_and_creates_none(): void
    {
        $o = $this->createUser();
        $e = $this->championship($this->clubFor($o), $o, ['sport' => 'karate']);
        $light = EventCategory::create(['event_id' => $e->id, 'name' => 'My Boys up to 30', 'gender' => 'male', 'min_age' => 6, 'max_age' => 12, 'min_weight' => 0, 'max_weight' => 30, 'sort_order' => 1]);
        $wide = EventCategory::create(['event_id' => $e->id, 'name' => 'My Boys up to 45', 'gender' => 'male', 'min_age' => 6, 'max_age' => 12, 'min_weight' => 0, 'max_weight' => 45, 'sort_order' => 2]);
        $mk = function (string $name, string $gender, int $age, float $kg) use ($e) {
            $u = $this->createUser(['full_name' => $name, 'gender' => $gender, 'birthdate' => now()->subYears($age)->subMonths(2)->toDateString()]);

            return ClubEventRegistration::create(['event_id' => $e->id, 'user_id' => $u->id, 'role' => 'participant', 'status' => 'joined', 'weight' => $kg]);
        };
        $a = $mk('Light Boy', 'Male', 9, 27);     // fits both — the tighter one wins
        $b = $mk('Heavier Boy', 'Male', 10, 40);  // fits only the wide one
        $c = $mk('A Girl', 'Female', 9, 27);      // fits neither: left for the list

        $sys = $this->actingAs($o)->getJson(route('me.events.divisions.auto-place', $e->uuid))->assertOk();
        $this->assertSame('event-own', $sys->json('pick'));
        $this->assertSame('event-own', $sys->json('systems.0.key'));

        $this->actingAs($o)->postJson(route('me.events.divisions.auto-place', $e->uuid), ['system' => 'event-own'])
            ->assertOk()->assertJsonPath('placed', 2)->assertJsonPath('created', 0)->assertJsonPath('left', 1);

        $this->assertSame($light->id, (int) $a->fresh()->category_id);
        $this->assertSame($wide->id, (int) $b->fresh()->category_id);
        $this->assertNull($c->fresh()->category_id);
        $this->assertSame(2, EventCategory::where('event_id', $e->id)->count(), 'no division was created');
        $this->assertNull($e->fresh()->category_system, 'the rule book is untouched');
    }

    /** Automatic placement obeys the organiser's own divisions and creates none (owner, 2026-10-15). */
    public function test_automatic_placement_uses_the_organisers_own_divisions_and_never_creates_one(): void
    {
        $o = $this->createUser();
        $e = $this->championship($this->clubFor($o), $o, ['sport' => 'karate']);
        $mine = EventCategory::create(['event_id' => $e->id, 'name' => 'Group A · 6–7 yrs', 'gender' => 'Male', 'min_age' => 6, 'max_age' => 7, 'min_weight' => 15, 'max_weight' => 30, 'sort_order' => 1]);
        $mk = function (int $age, float $kg) use ($e) {
            $u = $this->createUser(['gender' => 'Male', 'birthdate' => now()->subYears($age)->subMonths(2)->toDateString()]);

            return ClubEventRegistration::create(['event_id' => $e->id, 'user_id' => $u->id, 'role' => 'participant', 'status' => 'joined', 'weight' => $kg]);
        };
        $fits = $mk(6, 22);
        $nine = $mk(9, 28);   // the WKF table would make "Kids Kumite Male -30 kg" (6–11) for him

        $systems = app(\App\Events\Support\CategorySystems::class);
        $this->assertSame($mine->id, $systems->placeCreating($e, $fits)?->id);
        $this->assertNull($systems->placeCreating($e, $nine));

        $this->assertNull($nine->fresh()->category_id, 'left for "Not in a division"');
        $this->assertSame(1, EventCategory::where('event_id', $e->id)->count(), 'no rule-book division was created');
    }

    /** The organiser's choice in the auto-place popup is kept — own divisions or a table (owner, 2026-10-15). */
    public function test_the_choice_of_own_divisions_or_a_table_sticks(): void
    {
        $o = $this->createUser();
        $e = $this->championship($this->clubFor($o), $o, ['sport' => 'karate']);
        // A division that LOOKS like the rule book's (it carries a weight class) —
        // with the choice made, it is still only "the event's own".
        $mine = EventCategory::create(['event_id' => $e->id, 'name' => 'My 6–7 group', 'gender' => 'Male', 'min_age' => 6, 'max_age' => 7, 'min_weight' => 15, 'max_weight' => 30, 'weight_class' => '-30', 'sort_order' => 1]);
        $mk = function (int $age, float $kg) use ($e) {
            $u = $this->createUser(['gender' => 'Male', 'birthdate' => now()->subYears($age)->subMonths(2)->toDateString()]);

            return ClubEventRegistration::create(['event_id' => $e->id, 'user_id' => $u->id, 'role' => 'participant', 'status' => 'joined', 'weight' => $kg]);
        };
        $nine = $mk(9, 28);

        $this->actingAs($o)->postJson(route('me.events.divisions.auto-place', $e->uuid), ['system' => 'event-own'])->assertOk();
        $this->assertSame('own', $e->fresh()->division_source);
        $this->assertSame('event-own', $this->actingAs($o)->getJson(route('me.events.divisions.auto-place', $e->uuid))->json('pick'));

        // Any automatic placement afterwards (sync, editor, club entry) obeys it.
        $this->assertNull(app(\App\Events\Support\CategorySystems::class)->placeCreating($e->fresh(), $nine->fresh()));
        $this->assertSame(1, EventCategory::where('event_id', $e->id)->count(), 'nothing created while "own" is chosen');
        $this->assertSame(6, (int) $mine->fresh()->min_age, 'the organiser\'s ages are untouched');
        $this->assertSame(7, (int) $mine->fresh()->max_age);

        // Choosing a table is kept the same way.
        $this->actingAs($o)->postJson(route('me.events.divisions.auto-place', $e->uuid), ['system' => 'wkf'])->assertOk();
        $this->assertSame('table', $e->fresh()->division_source);
        $this->assertSame('wkf', $this->actingAs($o)->getJson(route('me.events.divisions.auto-place', $e->uuid))->json('pick'));
    }

    /** The weigh-in weight is the authentic one (owner, 2026-10-15) — App\Events\Support\BodyWeight. */
    public function test_auto_place_uses_the_weigh_in_weight_not_the_declared_one(): void
    {
        $o = $this->createUser();
        $e = $this->championship($this->clubFor($o), $o, ['sport' => 'karate']);
        $u = $this->createUser(['full_name' => 'Two Entries', 'gender' => 'Male', 'birthdate' => now()->subYears(25)->toDateString()]);
        // Declared 60 kg on the first entry; the scale said 84 kg on the second.
        $first = ClubEventRegistration::create(['event_id' => $e->id, 'user_id' => $u->id, 'role' => 'participant', 'status' => 'joined', 'weight' => 60]);
        ClubEventRegistration::create(['event_id' => $e->id, 'user_id' => $u->id, 'role' => 'participant', 'status' => 'joined', 'weight' => 84])
            ->forceFill(['weighed_in_at' => now(), 'weighed_in_by' => $o->id])->save();

        $this->actingAs($o)->postJson(route('me.events.divisions.auto-place', $e->uuid))->assertOk()->assertJsonPath('placed', 1);

        $first->refresh();
        $this->assertSame(84.0, (float) $first->weight);
        $this->assertNull($first->weighed_in_at, 'the scale reading is carried over, the entry is not signed off');
        $cat = EventCategory::find($first->category_id);
        $this->assertNotNull($cat);
        $this->assertTrue(($cat->min_weight === null || 84 > (float) $cat->min_weight) && ($cat->max_weight === null || 84 <= (float) $cat->max_weight),
            $cat->name.' must hold 84 kg');
    }

    public function test_weighing_one_entry_carries_the_reading_to_the_persons_other_entries(): void
    {
        $o = $this->createUser();
        $e = $this->championship($this->clubFor($o), $o, ['sport' => 'karate']);
        $u = $this->createUser(['full_name' => 'Kata And Kumite', 'gender' => 'Male', 'birthdate' => now()->subYears(25)->toDateString()]);
        $a = ClubEventRegistration::create(['event_id' => $e->id, 'user_id' => $u->id, 'role' => 'participant', 'status' => 'joined', 'weight' => 60]);
        $b = ClubEventRegistration::create(['event_id' => $e->id, 'user_id' => $u->id, 'role' => 'participant', 'status' => 'joined', 'weight' => 60]);

        $this->actingAs($o)->putJson(route('me.events.verify.weigh-in', [$e->uuid, $a->id]), ['weight' => 72.5])->assertOk();

        $this->assertSame(72.5, (float) $b->fresh()->weight);
        $this->assertNull($b->fresh()->weighed_in_at);
        $this->assertNotNull($a->fresh()->weighed_in_at);
    }

    public function test_auto_place_never_moves_a_hand_placed_entry(): void
    {
        [$o, $e, $old, $kids] = $this->event();
        $kids[0]->forceFill(['category_source' => ClubEventRegistration::PLACED_MANUAL])->save();

        $this->actingAs($o)->postJson(route('me.events.divisions.auto-place', $e->uuid))->assertOk();

        $this->assertSame($old->id, (int) $kids[0]->fresh()->category_id);
    }

    public function test_only_the_organiser_and_only_while_switched_on(): void
    {
        [$o, $e] = $this->event();

        $this->actingAs($this->createUser())->postJson(route('me.events.divisions.delete-all', $e->uuid), ['confirm' => true])->assertNotFound();
        config(['events.division_merge.enabled' => false]);
        $this->actingAs($o)->postJson(route('me.events.divisions.auto-place', $e->uuid))->assertNotFound();
    }

    public function test_auto_place_follows_the_weight_table_the_organiser_picks(): void
    {
        [$o, $e] = $this->event();

        $list = $this->actingAs($o)->getJson(route('me.events.divisions.auto-place.systems', $e->uuid))->assertOk();
        $this->assertContains('shotokan', array_column($list->json('systems'), 'key'));
        $this->assertSame('wkf', $list->json('current'));

        $this->actingAs($o)->postJson(route('me.events.divisions.delete-all', $e->uuid), ['confirm' => true])->assertOk();
        $this->actingAs($o)->postJson(route('me.events.divisions.auto-place', $e->uuid), ['system' => 'shotokan'])->assertOk();

        $this->assertSame('shotokan', $e->fresh()->category_system);
        // Shotokan's own age groups name the divisions ("Under 10 Years"), not WKF's "Kids".
        $this->assertTrue(EventCategory::where('event_id', $e->id)->pluck('name')->contains(fn ($n) => str_contains($n, 'Under 10 Years')));

        // A table the event's sport does not have is refused.
        $this->actingAs($o)->postJson(route('me.events.divisions.auto-place', $e->uuid), ['system' => 'ibjjf'])->assertStatus(422);
    }
}
