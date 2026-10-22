<?php

namespace Tests\Feature\Events;

use App\Events\Models\ClubEventRegistration;
use App\Events\Models\EventCategory;
use App\Events\Models\EventMatch;
use App\Events\Models\EventRegistrationFeeLine;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Contracts\ContractTestCase;

/**
 * A person moved between divisions leaves no stray row behind, and a second
 * division never charges them twice (App\Events\Support\SpareEntries — the
 * twelve rows Energy Zone Combat 4 carried on the day).
 */
class SpareEntriesTest extends ContractTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['events.spare_entries' => true]);
    }

    private function event(): array
    {
        $o = $this->createUser();
        $e = $this->championship($this->clubFor($o), $o, ['sport' => 'karate']);
        $x = EventCategory::create(['event_id' => $e->id, 'name' => 'Group X', 'sort_order' => 1]);
        $y = EventCategory::create(['event_id' => $e->id, 'name' => 'Group Y', 'sort_order' => 2]);
        $u = $this->createUser(['full_name' => 'Moving Kid', 'gender' => 'Male']);
        $entry = ClubEventRegistration::create(['event_id' => $e->id, 'user_id' => $u->id, 'category_id' => $x->id,
            'role' => 'participant', 'status' => 'joined', 'weight' => 30, 'paid' => true]);
        EventRegistrationFeeLine::create(['registration_id' => $entry->id, 'kind' => 'base', 'label' => 'Entry fee', 'amount' => 10, 'currency' => 'BHD']);

        return [$o, $e, $x, $y, $u, $entry];
    }

    public function test_moving_someone_between_divisions_keeps_one_entry_and_one_charge(): void
    {
        [$o, $e, $x, $y, $u, $entry] = $this->event();

        // Tick Y: a second entry, recorded as free.
        $this->actingAs($o)->putJson(route('me.events.divisions.members', [$e->uuid, $y->id]), ['add' => [$entry->id]])->assertOk();
        $copy = ClubEventRegistration::where('event_id', $e->id)->where('category_id', $y->id)->firstOrFail();
        $this->assertSame(0.0, (float) EventRegistrationFeeLine::where('registration_id', $copy->id)->sum('amount'));
        $this->assertTrue(EventRegistrationFeeLine::where('registration_id', $copy->id)->exists(), 'a zero line, so it is never re-priced');

        // Untick X: the original folds into the copy, its fee with it.
        $this->actingAs($o)->putJson(route('me.events.divisions.members', [$e->uuid, $x->id]), ['remove' => [$entry->id]])->assertOk();

        $rows = ClubEventRegistration::where('event_id', $e->id)->where('user_id', $u->id)->get();
        $this->assertCount(1, $rows);
        $this->assertSame($y->id, (int) $rows->first()->category_id);
        $this->assertSame(10.0, (float) EventRegistrationFeeLine::where('registration_id', $rows->first()->id)->sum('amount'));
    }

    public function test_deleting_a_division_folds_the_released_rows_of_people_placed_elsewhere(): void
    {
        [$o, $e, $x, $y, $u, $entry] = $this->event();
        $this->actingAs($o)->putJson(route('me.events.divisions.members', [$e->uuid, $y->id]), ['add' => [$entry->id]])->assertOk();
        $loner = ClubEventRegistration::create(['event_id' => $e->id, 'user_id' => $this->createUser()->id, 'category_id' => $x->id,
            'role' => 'participant', 'status' => 'joined']);

        $this->actingAs($o)->deleteJson(route('me.events.divisions.destroy', [$e->uuid, $x->id]), ['release' => true])->assertOk();

        $this->assertSame(1, ClubEventRegistration::where('event_id', $e->id)->where('user_id', $u->id)->count());
        // Somebody with nothing else keeps their entry, in no division.
        $this->assertNull($loner->fresh()->category_id);
    }

    public function test_a_row_a_bout_names_is_never_folded(): void
    {
        [$o, $e, $x, $y, $u, $entry] = $this->event();
        $this->actingAs($o)->putJson(route('me.events.divisions.members', [$e->uuid, $y->id]), ['add' => [$entry->id]])->assertOk();
        $entry->forceFill(['category_id' => null])->save();
        EventMatch::create(['event_id' => $e->id, 'category_id' => $y->id, 'round' => 1, 'match_no' => 1,
            'a_competitor_id' => $entry->id, 'a_name' => 'Moving Kid', 'status' => 'pending']);

        $this->assertSame(0, \App\Events\Support\SpareEntries::fold($e));
        $this->assertNotNull($entry->fresh());
    }

    public function test_switched_off_nothing_changes(): void
    {
        config(['events.spare_entries' => false]);
        [$o, $e, $x, $y, $u, $entry] = $this->event();

        $this->actingAs($o)->putJson(route('me.events.divisions.members', [$e->uuid, $y->id]), ['add' => [$entry->id]])->assertOk();
        $this->actingAs($o)->putJson(route('me.events.divisions.members', [$e->uuid, $x->id]), ['remove' => [$entry->id]])->assertOk();

        $this->assertSame(2, ClubEventRegistration::where('event_id', $e->id)->where('user_id', $u->id)->count());
        $this->assertSame(0, DB::table('event_registration_fee_lines')->whereIn('registration_id',
            ClubEventRegistration::where('event_id', $e->id)->where('category_id', $y->id)->pluck('id'))->count());
    }
}
