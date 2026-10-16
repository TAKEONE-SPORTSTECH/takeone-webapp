<?php

namespace Tests\Feature\Events;

use App\Clubs\Models\Tenant;
use App\Events\Models\ClubEvent;
use App\Events\Models\ClubEventRegistration;
use App\Events\Models\EventCategory;
use App\Events\Support\Tournament\FairDraw;
use App\Members\Models\User;
use Tests\Feature\Contracts\ContractTestCase;

/**
 * Shuffle — App\Events\Support\Tournament\FairDraw: highest belts seeded
 * apart, clubs split, the rest at random; offered as `shuffle_draw` by every
 * bracketed sport, before the start only.
 */
class FairDrawTest extends ContractTestCase
{
    /** The preview's sample: 13 athletes, 6 clubs, belts on an 8-step ladder. */
    private function sample(): array
    {
        $rows = [['BKC', 7], ['BKC', 5], ['BKC', 2], ['BKC', 0], ['SHA', 7], ['SHA', 4], ['SHA', 1],
            ['MUH', 6], ['MUH', 3], ['RIF', 5], ['RIF', 0], ['ISA', 3], ['BUD', 4]];

        return array_map(fn ($r, $i) => ['id' => $i + 1, 'club' => $r[0], 'belt' => $r[1]], $rows, array_keys($rows));
    }

    public function test_the_draw_seeds_belts_apart_splits_clubs_and_gives_byes_to_the_seeds(): void
    {
        $fair = app(FairDraw::class);
        $people = collect($this->sample())->keyBy('id');
        $layouts = [];

        foreach (range(1, 100) as $_) {
            $slots = $fair->bestSlots($this->sample());
            $layouts[implode(',', array_map(fn ($id) => $id ?? '-', $slots))] = true;

            $this->assertCount(16, $slots);
            $ids = array_values(array_filter($slots));
            sort($ids);
            $this->assertSame(range(1, 13), $ids, 'every athlete exactly once');

            for ($i = 0; $i < 16; $i += 2) {
                [$a, $b] = [$slots[$i], $slots[$i + 1]];
                $this->assertFalse($a === null && $b === null, 'no empty bout');
                if ($a && $b) {
                    $this->assertNotSame($people[$a]['club'], $people[$b]['club'], 'teammates never meet in round one here');
                } else {
                    // A bye stands beside a black or brown belt — a seed.
                    $this->assertGreaterThanOrEqual(6, $people[$a ?? $b]['belt']);
                }
            }

            // The two black belts are seeds 1 and 2: opposite halves.
            $black = array_keys(array_filter($slots, fn ($id) => $id && $people[$id]['belt'] === 7));
            $this->assertCount(2, $black);
            $this->assertNotSame($black[0] < 8, $black[1] < 8);
        }

        $this->assertGreaterThan(50, count($layouts), 'still a random draw');
    }

    public function test_athletes_with_no_belt_or_club_still_draw(): void
    {
        $people = array_map(fn ($i) => ['id' => $i, 'club' => null, 'belt' => null], range(1, 5));
        $slots = app(FairDraw::class)->bestSlots($people);

        $this->assertCount(8, $slots);
        $this->assertCount(5, array_filter($slots));
    }

    /** @return array{0: ClubEvent, 1: EventCategory, 2: User} */
    private function divisionOfTwoClubs(): array
    {
        $organiser = $this->createUser();
        $home = $this->clubFor($organiser);
        $away = $this->createClub($this->createUser(), ['country' => 'BH', 'currency' => 'BHD']);
        $event = $this->championship($home, $organiser);
        $category = EventCategory::create(['event_id' => $event->id, 'name' => 'Senior Male -58 kg', 'sort_order' => 1]);

        foreach (range(1, 6) as $i) {
            $athlete = $this->createUser(['full_name' => 'Athlete '.$i, 'gender' => 'Male']);
            ClubEventRegistration::create([
                'event_id' => $event->id, 'user_id' => $athlete->id, 'category_id' => $category->id,
                'role' => 'participant', 'paid' => true, 'weight' => 57,
                'representing_tenant_id' => ($i <= 3 ? $home : $away)->id,
                'belt_colour' => ['black', 'blue', 'white'][($i - 1) % 3],
            ]);
        }

        app(\App\Events\EventTypeRegistry::class)->for($event)->performAction($event, 'generate_draw');

        return [$event, $category->fresh(), $organiser];
    }

    public function test_the_organiser_shuffles_a_division_and_no_club_meets_itself_in_round_one(): void
    {
        [$event, $category, $organiser] = $this->divisionOfTwoClubs();

        foreach (range(1, 5) as $_) {
            $this->actingAs($organiser)
                ->postJson(route('me.events.action', [$event->uuid, 'shuffle_draw']), ['category_id' => $category->id])
                ->assertOk()->assertJsonPath('success', true);

            $club = ClubEventRegistration::where('category_id', $category->id)->pluck('representing_tenant_id', 'id');
            $first = $category->matches()->orderBy('slot')->get()->groupBy('round')->sortBy(fn ($b) => $b->min('slot'))->first();

            $this->assertCount(4, $first);
            foreach ($first as $bout) {
                if ($bout->a_competitor_id && $bout->b_competitor_id) {
                    $this->assertNotSame($club[$bout->a_competitor_id], $club[$bout->b_competitor_id]);
                } else {
                    $this->assertTrue((bool) $bout->won_by_bye, 'a bye is still a walkover');
                }
            }
            $this->assertSame('manual', $category->fresh()->draw_state, 'never re-cut back by housekeeping');
        }
    }

    public function test_shuffle_is_refused_to_strangers_after_the_start_and_on_a_fought_division(): void
    {
        [$event, $category, $organiser] = $this->divisionOfTwoClubs();
        $url = route('me.events.action', [$event->uuid, 'shuffle_draw']);

        $this->actingAs($this->createUser())->postJson($url, ['category_id' => $category->id])->assertForbidden();

        // A fought bout: the division is no longer the shuffle's to change.
        $bout = $category->matches()->whereNotNull('a_competitor_id')->whereNotNull('b_competitor_id')->first();
        $bout->update(['winner' => 'a', 'status' => 'done', 'won_by_bye' => false]);
        $this->actingAs($organiser)->postJson($url, ['category_id' => $category->id])->assertStatus(422);
        $this->assertSame('a', $bout->fresh()->winner, 'the result survives');

        // Started: the action is withdrawn.
        $event->update(['started_at' => now()]);
        $this->actingAs($organiser)->postJson($url, ['category_id' => $category->id])->assertStatus(422);
    }

    public function test_the_tablet_shuffle_door_follows_the_device_rules(): void
    {
        $this->postJson('/tablet/'.str_repeat('a', 40).'/draw/shuffle', ['category_id' => 1])->assertNotFound();
    }
}
