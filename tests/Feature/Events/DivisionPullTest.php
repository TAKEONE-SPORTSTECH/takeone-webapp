<?php

namespace Tests\Feature\Events;

use App\Events\Models\ClubEvent;
use App\Events\Models\ClubEventRegistration;
use App\Events\Models\EventCategory;
use App\Members\Models\User;
use Tests\Feature\Contracts\ContractTestCase;

/**
 * The draw desk's division editor (owner, 2026-10-16): who fits a range shows
 * in the holding basket, a drag puts them onto the board — which grows to take
 * them, and shrinks when a desk move empties half of it — and an event can
 * allow only one division per person, enforced by the server.
 */
class DivisionPullTest extends ContractTestCase
{
    private function entrant(ClubEvent $event, ?EventCategory $in, string $name, float $weight, string $gender = 'Male', int $age = 12): ClubEventRegistration
    {
        $u = $this->createUser(['full_name' => $name, 'gender' => $gender,
            'birthdate' => \Illuminate\Support\Carbon::parse($event->date)->subYears($age)->subMonth()->toDateString()]);

        return ClubEventRegistration::create([
            'event_id' => $event->id, 'user_id' => $u->id, 'category_id' => $in?->id,
            'role' => 'participant', 'paid' => true, 'weight' => $weight,
        ]);
    }

    private function pull(User $who, ClubEvent $event, EventCategory $cat, ClubEventRegistration $r, array $to = ['type' => 'board'])
    {
        return $this->actingAs($who)->putJson(route('me.events.bracket.pull', $event->uuid),
            ['category_id' => $cat->id, 'competitor_id' => $r->id, 'to' => $to]);
    }

    private function firstRoundSize(EventCategory $cat): int
    {
        $m = $cat->matches()->where('round', '!=', 'Third place')->orderBy('slot')->get()->groupBy('round')
            ->sortBy(fn ($b) => $b->min('slot'))->first();

        return $m ? $m->count() * 2 : 0;
    }

    private function placed(EventCategory $cat): array
    {
        return $cat->matches()->get()->flatMap(fn ($m) => $m->competitorIds())->unique()->values()->all();
    }

    public function test_candidates_are_pulled_onto_an_empty_board_and_it_grows_to_fit(): void
    {
        config(['events.lone_entrant_final' => true]);
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->championship($club, $organiser);
        $cat = EventCategory::create(['event_id' => $event->id, 'name' => 'Group A', 'sort_order' => 1]);

        $people = collect(range(1, 3))->map(fn ($i) => $this->entrant($event, null, 'Kid '.$i, 30 + $i));

        $this->pull($organiser, $event, $cat, $people[0])->assertOk();
        $this->assertSame(2, $this->firstRoundSize($cat), 'an empty board lays its final');

        $final = $cat->matches()->first();
        $empty = $final->a_competitor_id ? 'b' : 'a';
        $this->pull($organiser, $event, $cat, $people[1], ['type' => 'slot', 'match_id' => $final->id, 'side' => $empty])->assertOk();
        $this->assertCount(2, $this->placed($cat->fresh()));

        // A full board takes a third by growing a round.
        $this->pull($organiser, $event, $cat, $people[2])->assertOk();
        $this->assertSame(4, $this->firstRoundSize($cat->fresh()));
        $this->assertCount(3, $this->placed($cat->fresh()));
        $this->assertSame(3, ClubEventRegistration::where('category_id', $cat->id)->count(), 'each moved in, nobody copied');

        $stranger = $this->createUser();
        $this->pull($stranger, $event, $cat, $people[0])->assertForbidden();
    }

    public function test_someone_already_in_another_bracket_is_copied_or_refused_by_the_one_division_switch(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->championship($club, $organiser);
        [$drawn] = $this->drawnDivision($event, $club);
        $other = EventCategory::create(['event_id' => $event->id, 'name' => 'Group B', 'sort_order' => 2]);
        $someone = ClubEventRegistration::where('category_id', $drawn->id)->first();

        // Off (the default): a second entry, on a second board.
        $this->pull($organiser, $event, $other, $someone)->assertOk();
        $this->assertSame(2, ClubEventRegistration::where('event_id', $event->id)->where('user_id', $someone->user_id)->count());
        $this->assertCount(1, $this->placed($other->fresh()));

        // On: refused, by the server, for the next one.
        $this->actingAs($organiser)->putJson(route('me.events.bracket.one-division', $event->uuid), ['on' => true])
            ->assertOk()->assertJson(['one_division' => true]);
        $this->assertTrue((bool) $event->fresh()->one_division_per_person);

        $next = ClubEventRegistration::where('category_id', $drawn->id)->where('user_id', '!=', $someone->user_id)->first();
        $this->pull($organiser, $event->fresh(), $other, $next)->assertStatus(422)
            ->assertJsonFragment(['success' => false]);
        $this->assertSame(1, ClubEventRegistration::where('event_id', $event->id)->where('user_id', $next->user_id)->count());

        // …and the basket says so before anyone tries.
        $row = collect($this->actingAs($organiser)->getJson(route('me.events.bracket.candidates', $event->uuid).'?basket=1&category_id='.$other->id)
            ->assertOk()->json('candidates'))->firstWhere('id', $next->id);
        $this->assertTrue($row['blocked']);
        $this->assertSame([$drawn->name], $row['placed_in']);

        // Only an organiser may flip it.
        $this->actingAs($this->createUser())->putJson(route('me.events.bracket.one-division', $event->uuid), ['on' => false])->assertForbidden();
    }

    public function test_the_switch_also_guards_arranging_from_a_basket(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->championship($club, $organiser);
        [$drawn] = $this->drawnDivision($event, $club);
        $someone = ClubEventRegistration::where('category_id', $drawn->id)->first();

        // Their second entry waits in another division's basket, beside a board with an open corner.
        $other = EventCategory::create(['event_id' => $event->id, 'name' => 'Group B', 'sort_order' => 2]);
        foreach (range(1, 3) as $i) {
            $this->entrant($event, $other, 'B'.$i, 40);
        }
        app(\App\Events\EventTypeRegistry::class)->for($event)->performAction($event, 'generate_draw');
        $copy = $someone->replicate();
        $copy->category_id = $other->id;
        $copy->save();
        $open = $other->fresh()->matches()->get()->first(fn ($m) => ($m->a_name xor $m->b_name) && $m->round !== 'Third place');
        $this->assertNotNull($open, 'three on a board of four leaves an open corner');

        $event->update(['one_division_per_person' => true]);
        $this->actingAs($organiser)->putJson(route('me.events.bracket.arrange', $event->uuid), [
            'category_id' => $other->id,
            'from' => ['type' => 'bench', 'competitor_id' => $copy->id],
            'to' => ['type' => 'slot', 'match_id' => $open->id, 'side' => $open->a_name ? 'b' : 'a'],
        ])->assertStatus(422);
        $this->assertNotContains($copy->id, $this->placed($other->fresh()));
    }

    public function test_a_desk_move_that_empties_half_a_board_shrinks_it_and_an_ordinary_move_does_not(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->championship($club, $organiser);
        $cat = EventCategory::create(['event_id' => $event->id, 'name' => 'Group A', 'sort_order' => 1]);
        foreach (range(1, 5) as $i) {
            $this->entrant($event, $cat, 'P'.$i, 40 + $i);
        }
        app(\App\Events\EventTypeRegistry::class)->for($event)->performAction($event, 'generate_draw');
        $this->assertSame(8, $this->firstRoundSize($cat->fresh()));

        $take = function (bool $fit) use ($organiser, $event, $cat) {
            $first = $cat->fresh()->matches()->orderBy('slot')->get()->groupBy('round')->sortBy(fn ($b) => $b->min('slot'))->first();
            $bout = $first->first(fn ($m) => $m->a_competitor_id && $m->b_competitor_id) ?? $first->first(fn ($m) => $m->a_competitor_id);

            return $this->actingAs($organiser)->putJson(route('me.events.bracket.arrange', $event->uuid), array_filter([
                'category_id' => $cat->id,
                'from' => ['type' => 'slot', 'match_id' => $bout->id, 'side' => 'a'],
                'to' => ['type' => 'bench'],
                'fit' => $fit ?: null,
            ]));
        };

        $take(false)->assertOk();
        $this->assertSame(8, $this->firstRoundSize($cat->fresh()), 'the ordinary board never reshapes');
        $this->assertCount(4, $this->placed($cat->fresh()));

        // Put them back, then take one out from the desk.
        $back = ClubEventRegistration::where('category_id', $cat->id)->whereNotIn('id', $this->placed($cat->fresh()))->first();
        $this->pull($organiser, $event, $cat, $back)->assertOk();
        $this->assertCount(5, $this->placed($cat->fresh()));

        $take(true)->assertOk()->assertJson(['resized' => true]);
        $this->assertSame(4, $this->firstRoundSize($cat->fresh()), '4 people on a board of 8 → a board of 4');
        $this->assertCount(4, $this->placed($cat->fresh()));
        $this->assertSame(5, ClubEventRegistration::where('category_id', $cat->id)->count(), 'the one taken out waits in the basket');
    }

    public function test_the_basket_filters_weights_to_the_hundredth(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->championship($club, $organiser);
        foreach ([22.7, 22.75, 23.1, 24.5, 24.51] as $i => $w) {
            $this->entrant($event, null, 'W'.$i, $w, $i % 2 ? 'Female' : 'Male', 9);
        }
        $this->entrant($event, null, 'Old', 23, 'Male', 30);

        $names = fn (string $q) => collect($this->actingAs($organiser)
            ->getJson(route('me.events.bracket.candidates', $event->uuid).'?basket=1&'.$q)->assertOk()->json('candidates'))
            ->pluck('name')->sort()->values()->all();

        $this->assertSame(['W1', 'W2', 'W3'], $names('min_weight=22.75&max_weight=24.5&min_age=8&max_age=10'));
        $this->assertSame(['W1', 'W3'], $names('min_weight=22.75&max_weight=24.5&gender=Female'));
        $this->assertSame(['Old'], $names('min_age=20'));

        // The editor is on the desk.
        $cat = EventCategory::create(['event_id' => $event->id, 'name' => 'Group A', 'sort_order' => 1]);
        $this->actingAs($organiser)->get("/me/events/{$event->uuid}/draw-desk?division={$cat->id}")
            ->assertOk()->assertSee('openDivMenu()', false)->assertSee('toggleOneDivision()', false)
            ->assertSee('brackets\/pull', false);
    }

    public function test_adding_a_division_past_group_z_still_names_it(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->championship($club, $organiser);
        foreach (range(0, 25) as $i) {
            EventCategory::create(['event_id' => $event->id, 'name' => 'Group '.chr(65 + $i), 'sort_order' => $i + 1]);
        }

        $made = $this->actingAs($organiser)->postJson(route('me.events.divisions.store', $event->uuid),
            ['name' => '', 'auto_name' => true, 'is_heading' => false, 'format' => 'knockout'])->assertOk()->json('division');
        $this->assertSame('New division 27', $made['name']);
    }
}
