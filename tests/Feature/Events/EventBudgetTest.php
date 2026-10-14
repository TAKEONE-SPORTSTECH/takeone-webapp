<?php

namespace Tests\Feature\Events;

use App\Clubs\Models\Tenant;
use App\Events\Models\ClubEvent;
use App\Events\Models\ClubEventRegistration;
use App\Events\Models\EventBudgetScenario;
use App\Events\Models\EventCategory;
use App\Events\Models\EventFeeOption;
use App\Ai\AiManager;
use App\Ai\Contracts\TextDriver;
use App\Events\Support\Budget\BudgetPlan;
use App\Mcp\Tools\GetEventBudgetTool;
use App\Mcp\Tools\ManageEventBudgetItemTool;
use App\Mcp\Tools\SetEventBudgetTool;
use App\Members\Models\User;
use Laravel\Mcp\Request;
use Tests\TestCase;

/**
 * Budget & profit (App\Events\Support\Budget\BudgetPlan).
 *
 * What this protects: the arithmetic (costs, break-even, athletes needed,
 * suggested fee), medals counted from the real divisions and the event's own
 * bronze rule, that only the organiser can read or write it, that a cost or
 * scenario of another event can never be reached through this one, and that
 * approving changes the real fee only when asked.
 */
class EventBudgetTest extends TestCase
{
    private const DESKTOP = 'Mozilla/5.0 (X11; Linux x86_64) Chrome/120';

    private const PHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Mobile/15E148';

    protected function setUp(): void
    {
        parent::setUp();
        config(['events.budget.enabled' => true]);
        // Never the real model in a test — the fake AiManager answers instead.
        config(['events.budget.advisor_model' => '']);
    }

    private function clubFor(User $user): Tenant
    {
        $club = $this->createClub($user, ['country' => 'BH']);
        $user->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);

        return $club;
    }

    private function event(User $organiser, array $attrs = []): ClubEvent
    {
        return ClubEvent::create(array_merge([
            'tenant_id' => $this->clubFor($organiser)->id, 'created_by' => $organiser->id,
            'title' => 'Kyokushin Open', 'event_type' => 'championship', 'sport' => 'karate',
            'scope' => 'internal', 'status' => 'active', 'is_archived' => false,
            'date' => now()->addWeeks(3)->toDateString(), 'start_time' => '09:00', 'end_time' => '17:00',
            'third_place_match' => false,
        ], $attrs));
    }

    /** Divisions holding 1, 2, 3 and 5 entrants, plus an empty one. */
    private function divisions(ClubEvent $event): void
    {
        foreach ([1, 2, 3, 5, 0] as $i => $n) {
            $cat = EventCategory::create(['event_id' => $event->id, 'name' => 'Division '.$i]);
            for ($k = 0; $k < $n; $k++) {
                ClubEventRegistration::create([
                    'event_id' => $event->id, 'user_id' => $this->createUser()->id,
                    'role' => 'participant', 'status' => 'joined', 'category_id' => $cat->id,
                ]);
            }
        }
    }

    private function mcp(string $tool, User $as, array $args): array
    {
        $this->actingAs($as);
        $d = json_decode((string) (new $tool)->handle(new Request($args))->content(), true);

        return is_array($d) ? $d : ['error' => true];
    }

    public function test_medals_are_counted_from_the_divisions_and_the_bronze_rule(): void
    {
        $event = $this->event($this->createUser());
        $this->divisions($event);

        $entries = BudgetPlan::medals($event, 'entries');
        // 1 → G · 2 → G S · 3 → G S B · 5 → G S B B (two bronzes) · empty → none
        $this->assertSame([4, 3, 3, 10], [$entries['gold'], $entries['silver'], $entries['bronze'], $entries['total']]);
        $this->assertSame(['1' => 1, '2' => 1, '3' => 1, '4+' => 1], $entries['spread']);
        $this->assertSame(20, BudgetPlan::medals($event, 'full')['total']);   // 5 divisions × 4

        $event->update(['third_place_match' => true]);
        $this->assertSame(9, BudgetPlan::medals($event->fresh(), 'entries')['total']);   // one bronze where five fought
        $this->assertSame(15, BudgetPlan::medals($event->fresh(), 'full')['total']);
    }

    public function test_with_nobody_placed_yet_the_count_is_a_full_set_and_says_so(): void
    {
        $event = $this->event($this->createUser());
        EventCategory::create(['event_id' => $event->id, 'name' => 'Open']);

        $m = BudgetPlan::medals($event, 'entries');
        $this->assertTrue($m['estimated']);
        $this->assertSame(4, $m['total']);
    }

    public function test_the_arithmetic(): void
    {
        $organiser = $this->createUser();
        $event = $this->event($organiser);
        $this->divisions($event);   // 10 medals to the entries

        $budget = BudgetPlan::ensure($event, $organiser);
        $budget->update(['planned_fee' => 10, 'expected_entrants' => 100, 'medal_unit_cost' => 2, 'target_profit' => 150, 'reserve_percent' => 10, 'round_to' => 0.5]);
        $budget->items()->create(['category' => 'venue', 'label' => 'Hall', 'amount' => 500, 'basis' => 'fixed', 'status' => 'quoted']);
        $budget->items()->create(['category' => 'running', 'label' => 'Water', 'amount' => 2, 'basis' => 'per_entrant', 'status' => 'planned']);

        $p = BudgetPlan::compute($event, $budget->fresh());

        $this->assertEquals(720.0, $p['expenses']);           // 500 + 2×100 + 10 medals × 2
        $this->assertEquals(1000.0, $p['revenue']);
        $this->assertEquals(280.0, $p['net']);
        $this->assertEquals(7.2, $p['break_even_fee']);
        $this->assertSame(65, $p['athletes_needed']);          // (500 + 20) / (10 − 2)
        $this->assertEquals(9.5, $p['suggested_fee']);         // (720 × 1.1 + 150) / 100 = 9.42 → 9.5
        $this->assertSame('profitable', $p['status']);

        // A sponsor covers costs, never more than there are.
        $this->assertEquals(0.0, BudgetPlan::compute($event, $budget->fresh(), ['sponsor' => 5000])['expenses']);
        $this->assertSame('loss', BudgetPlan::compute($event, $budget->fresh(), ['fee' => 5])['status']);
    }

    public function test_only_the_organiser_gets_in_and_the_switch_closes_every_door(): void
    {
        $organiser = $this->createUser();
        $event = $this->event($organiser);

        $this->actingAs($organiser)->withHeaders(['User-Agent' => self::DESKTOP])->get(route('me.events.budget', $event->uuid))->assertOk()->assertViewIs('events::budget.mobile.show');
        $this->actingAs($organiser)->withHeaders(['User-Agent' => self::PHONE])->get(route('me.events.budget', $event->uuid))->assertOk()->assertViewIs('events::budget.mobile.show');

        $stranger = $this->createUser();
        $this->actingAs($stranger)->getJson(route('me.events.budget.data', $event->uuid))->assertForbidden();
        $this->actingAs($stranger)->putJson(route('me.events.budget.update', $event->uuid), ['target_profit' => 5])->assertForbidden();
        $this->assertNull(BudgetPlan::for($event));

        config(['events.budget.enabled' => false]);
        $this->actingAs($organiser)->getJson(route('me.events.budget.data', $event->uuid))->assertNotFound();
    }

    public function test_costs_are_added_edited_and_removed_in_place(): void
    {
        $organiser = $this->createUser();
        $event = $this->event($organiser);

        $res = $this->actingAs($organiser)->postJson(route('me.events.budget.items.store', $event->uuid), [
            'category' => 'people', 'label' => 'Referees', 'amount' => 300, 'basis' => 'fixed', 'status' => 'planned',
        ])->assertOk()->assertJsonPath('success', true);
        $uuid = $res->json('budget.items.0.uuid');
        $this->assertEquals(300, $res->json('budget.plan.expenses'));

        $this->actingAs($organiser)->putJson(route('me.events.budget.items.update', [$event->uuid, $uuid]), [
            'category' => 'people', 'label' => 'Referees', 'amount' => 350, 'basis' => 'fixed', 'status' => 'paid',
        ])->assertOk()->assertJsonPath('budget.items.0.status', 'paid');

        $this->actingAs($organiser)->postJson(route('me.events.budget.items.store', $event->uuid), [
            'category' => 'nope', 'label' => 'x', 'amount' => -1, 'basis' => 'fixed', 'status' => 'planned',
        ])->assertStatus(422);

        $this->actingAs($organiser)->deleteJson(route('me.events.budget.items.destroy', [$event->uuid, $uuid]))
            ->assertOk()->assertJsonCount(0, 'budget.items');
    }

    public function test_another_events_cost_or_scenario_cannot_be_reached_through_this_one(): void
    {
        $organiser = $this->createUser();
        $mine = $this->event($organiser);
        $theirs = $this->event($other = $this->createUser());

        $theirBudget = BudgetPlan::ensure($theirs, $other);
        $item = $theirBudget->items()->create(['category' => 'other', 'label' => 'Secret', 'amount' => 1, 'basis' => 'fixed', 'status' => 'planned']);
        $scenario = $theirBudget->scenarios()->create(['name' => 'Theirs', 'fee' => 1, 'entrants' => 1, 'medals_mode' => 'entries']);
        BudgetPlan::ensure($mine, $organiser);

        $this->actingAs($organiser)->deleteJson(route('me.events.budget.items.destroy', [$mine->uuid, $item->uuid]))->assertNotFound();
        $this->actingAs($organiser)->postJson(route('me.events.budget.scenarios.approve', [$mine->uuid, $scenario->uuid]))->assertNotFound();
        $this->actingAs($organiser)->postJson(route('me.events.budget.copy', $mine->uuid), ['from' => $theirs->uuid])->assertNotFound();
        $this->assertDatabaseHas('event_budget_items', ['id' => $item->id]);
    }

    public function test_a_scenario_is_saved_and_approving_sets_the_fee_only_when_asked(): void
    {
        $organiser = $this->createUser();
        $event = $this->event($organiser);
        $joining = EventFeeOption::create(['event_id' => $event->id, 'role' => 'participant', 'label' => 'Joining fee', 'amount' => 10, 'is_active' => true, 'sort' => 0]);

        $this->actingAs($organiser)->postJson(route('me.events.budget.scenarios.store', $event->uuid), [
            'name' => '12 BHD', 'fee' => 12, 'entrants' => 99, 'medals_mode' => 'entries', 'sponsor' => 0,
        ])->assertOk()->assertJsonPath('budget.scenarios.0.net', 1188);
        $sc = EventBudgetScenario::first();

        $this->actingAs($organiser)->postJson(route('me.events.budget.scenarios.approve', [$event->uuid, $sc->uuid]), ['apply_fee' => false])
            ->assertOk()->assertJsonPath('budget.scenarios.0.approved', true);
        $this->assertEquals(10, $joining->fresh()->amount);
        $this->assertEquals(12, BudgetPlan::for($event)->planned_fee);

        $this->actingAs($organiser)->postJson(route('me.events.budget.scenarios.approve', [$event->uuid, $sc->uuid]), ['apply_fee' => true])->assertOk();
        $this->assertEquals(12, $joining->fresh()->amount);
        $this->assertStringContainsString('12', (string) $event->fresh()->participant_fee);
    }

    public function test_starting_from_another_event_copies_its_costs(): void
    {
        $organiser = $this->createUser();
        $last = $this->event($organiser, ['title' => 'Last year']);
        $budget = BudgetPlan::ensure($last, $organiser);
        $budget->update(['medal_unit_cost' => 2.46]);
        $budget->items()->create(['category' => 'venue', 'label' => 'Hall', 'amount' => 100, 'basis' => 'fixed', 'status' => 'paid']);

        $next = $this->event($organiser, ['title' => 'This year']);

        $this->actingAs($organiser)->getJson(route('me.events.budget.sources', $next->uuid))
            ->assertOk()->assertJsonPath('sources.0.uuid', $last->uuid);

        $this->actingAs($organiser)->postJson(route('me.events.budget.copy', $next->uuid), ['from' => $last->uuid])
            ->assertOk()->assertJsonPath('budget.items.0.label', 'Hall')->assertJsonPath('budget.items.0.status', 'planned');
        $this->assertEquals(2.46, BudgetPlan::for($next)->medal_unit_cost);
        $this->assertSame(1, $budget->items()->count());   // the source is untouched
    }

    public function test_the_mcp_tools_read_and_write_with_the_same_scope(): void
    {
        $organiser = $this->createUser();
        $event = $this->event($organiser);

        $out = $this->mcp(ManageEventBudgetItemTool::class, $organiser, ['event' => $event->uuid, 'label' => 'Referees', 'amount' => 300, 'category' => 'people']);
        $this->assertEquals(300, $out['plan']['expenses']);

        $this->mcp(SetEventBudgetTool::class, $organiser, ['event' => $event->uuid, 'planned_fee' => 10, 'expected_entrants' => 50]);
        $read = $this->mcp(GetEventBudgetTool::class, $organiser, ['event' => $event->uuid, 'fee' => 12]);
        $this->assertEquals(200, $read['plan']['net']);           // 10 × 50 − 300
        $this->assertEquals(300, $read['what_if']['net']);        // 12 × 50 − 300

        $stranger = $this->createUser();
        $this->assertArrayHasKey('error', $this->mcp(GetEventBudgetTool::class, $stranger, ['event' => $event->uuid]));
        $this->assertArrayHasKey('error', $this->mcp(ManageEventBudgetItemTool::class, $stranger, ['event' => $event->uuid, 'label' => 'x', 'amount' => 1]));
        $this->assertSame(1, BudgetPlan::for($event)->items()->count());
    }

    /* ===================== People, units, divisions ===================== */

    public function test_people_and_units_are_how_many_times_the_price_of_one(): void
    {
        $organiser = $this->createUser();
        $event = $this->event($organiser);

        $res = $this->actingAs($organiser)->postJson(route('me.events.budget.items.store', $event->uuid), [
            'category' => 'people', 'role' => 'referee', 'label' => 'Referees', 'quantity' => 8, 'unit_cost' => 30, 'basis' => 'per_entrant', 'status' => 'planned',
        ])->assertOk();
        $this->assertEquals(240, $res->json('budget.items.0.amount'));
        $this->assertSame('fixed', $res->json('budget.items.0.basis'));      // a headcount never grows with entrants
        $this->assertSame(8, $res->json('budget.people.headcount'));

        $this->actingAs($organiser)->postJson(route('me.events.budget.items.store', $event->uuid), [
            'category' => 'people', 'role' => 'volunteer', 'label' => 'Volunteers', 'quantity' => 6, 'unit_cost' => 0, 'basis' => 'fixed', 'status' => 'planned',
        ])->assertOk()->assertJsonPath('budget.people.volunteers', 6)->assertJsonPath('budget.people.paid', 8);

        // Any line can be priced per unit: 3 trophies × 15.
        $res = $this->actingAs($organiser)->postJson(route('me.events.budget.items.store', $event->uuid), [
            'category' => 'awards', 'role' => 'referee', 'label' => 'Trophies', 'quantity' => 3, 'unit_cost' => 15, 'basis' => 'fixed', 'status' => 'quoted',
        ])->assertOk();
        $trophy = collect($res->json('budget.items'))->firstWhere('label', 'Trophies');
        $this->assertEquals(45, $trophy['amount']);
        $this->assertNull($trophy['role']);                                  // a role belongs to people only
        $this->assertEquals(285, $res->json('budget.plan.expenses'));       // 240 + 0 + 45
    }

    public function test_medals_by_type_per_division_lines_and_headings(): void
    {
        $organiser = $this->createUser();
        $event = $this->event($organiser);
        EventCategory::create(['event_id' => $event->id, 'name' => 'Boys 8-9', 'is_heading' => true]);   // a heading, not a division
        $this->divisions($event);   // 1, 2, 3, 5 and an empty one → G4 S3 B3 to the entries

        $this->assertSame(5, BudgetPlan::medals($event, 'full')['divisions']);   // the heading is not counted
        $budget = BudgetPlan::ensure($event, $organiser);
        $budget->update(['medal_unit_cost' => 2, 'medal_gold_cost' => 5]);
        $budget->items()->create(['category' => 'awards', 'label' => 'Division trophy', 'amount' => 10, 'basis' => 'per_division', 'status' => 'planned']);

        $p = BudgetPlan::compute($event, $budget->fresh());
        $this->assertEquals(4 * 5 + 3 * 2 + 3 * 2, $p['medals']['cost']);   // gold at its own price, the rest at the default
        $this->assertSame(4, $p['medals']['divisions_running']);
        $this->assertEquals(40, $p['division_cost']);                       // 4 running divisions × 10
    }

    public function test_the_division_list_flags_small_ones_and_merging_shows_its_effect(): void
    {
        $organiser = $this->createUser();
        $event = $this->event($organiser);
        EventCategory::create(['event_id' => $event->id, 'name' => 'Seniors', 'is_heading' => true]);
        $this->divisions($event);   // 1, 2, 3, 5, 0 under one heading
        BudgetPlan::ensure($event, $organiser)->update(['medal_unit_cost' => 2]);

        $res = $this->actingAs($organiser)->postJson(route('me.events.budget.divisions', $event->uuid))->assertOk();
        $this->assertSame(1, $res->json('list.counts.alone'));
        $this->assertSame('Seniors', $res->json('list.groups.0.heading'));
        $first = $res->json('list.suggestions.0');
        $this->assertNotNull($first);

        // Merging the 1- and 2-athlete divisions (G1 + G1S1 → G1S1B1): one division less.
        $ids = collect($res->json('list.groups.0.divisions'))->whereIn('athletes', [1, 2])->pluck('id')->all();
        $res = $this->actingAs($organiser)->postJson(route('me.events.budget.divisions', $event->uuid), ['merges' => [$ids]])->assertOk();
        $this->assertSame(4, $res->json('impact.before.divisions_running'));
        $this->assertSame(3, $res->json('impact.after.divisions_running'));

        // Another event's divisions are ignored, never merged.
        $theirs = $this->event($this->createUser());
        $foreign = EventCategory::create(['event_id' => $theirs->id, 'name' => 'Theirs'])->id;
        $this->actingAs($organiser)->postJson(route('me.events.budget.divisions', $event->uuid), ['merges' => [[$foreign, $ids[0]]]])
            ->assertOk()->assertJsonPath('impact.merged_groups', 0);
    }

    /* ===================== The advisor ===================== */

    /**
     * Swap the AI for a scripted one. Each entry answers one call, in order:
     * a string is a plain reply, ['tools' => [[name, args], …]] asks for tools,
     * null means "the model is down". When the script runs out it repeats the last.
     */
    private function fakeAi(?array $script): void
    {
        $driver = new class($script) implements TextDriver {
            public array $seen = [];

            public function __construct(private ?array $script) {}

            public function chat(array $messages, array $tools = [], array $options = []): array
            {
                $this->seen[] = $messages;
                if ($this->script === null) {
                    throw new \RuntimeException('model down');
                }
                $next = count($this->script) > 1 ? array_shift($this->script) : $this->script[0];
                if ($next === null) {
                    throw new \RuntimeException('model down');
                }
                if (is_array($next) && isset($next['tools'])) {
                    return ['role' => 'assistant', 'content' => '', 'tool_calls' => array_map(fn ($t) => ['function' => ['name' => $t[0], 'arguments' => $t[1] ?? []]], $next['tools'])];
                }

                return ['role' => 'assistant', 'content' => (string) $next, 'tool_calls' => []];
            }
        };
        $ai = \Mockery::mock(AiManager::class);
        $ai->shouldReceive('text')->andReturn($driver);
        $this->app->instance(AiManager::class, $ai);
        $this->app->instance('test.ai.driver', $driver);
    }

    private function plannedEvent(User $organiser): ClubEvent
    {
        $event = $this->event($organiser);
        $budget = BudgetPlan::ensure($event, $organiser);
        $budget->update(['planned_fee' => 10, 'expected_entrants' => 100, 'target_profit' => 150]);
        $budget->items()->create(['category' => 'venue', 'label' => 'Hall', 'amount' => 1044, 'basis' => 'fixed', 'status' => 'quoted']);

        return $event;
    }

    private function ask(User $organiser, ClubEvent $event, string $message, array $extra = [])
    {
        return $this->actingAs($organiser)->postJson(route('me.events.budget.advisor', $event->uuid), ['message' => $message] + $extra)->assertOk();
    }

    public function test_the_advisor_reads_with_tools_and_the_plan_it_applies_is_the_solvers(): void
    {
        config(['events.budget.advisor' => true]);
        $organiser = $this->createUser();
        $event = $this->plannedEvent($organiser);   // Hall 1044, fee 10 × 100, target 150 → −44

        // It solves for the goal, applies the best plan, then answers in its own words.
        $this->fakeAi([
            ['tools' => [['solve_for_goal', ['target_profit' => 150]]]],
            ['tools' => [['apply_plan', ['plan_id' => 'p1']]]],
            'Raise the fee to 12 — I put it on your screen.',
        ]);
        $res = $this->ask($organiser, $event, 'How do I make 150?')->assertJsonPath('source', 'ai');

        $this->assertSame('Raise the fee to 12 — I put it on your screen.', $res->json('reply'));
        $this->assertSame(['solve_for_goal', 'apply_plan'], $res->json('tools'));
        $this->assertEquals(12, $res->json('proposal.fee'));      // (1044 + 150) / 100 = 11.94 → 12
        $this->assertEquals(156, $res->json('proposal.net'));
        $this->assertTrue($res->json('proposal.meets_target'));
        $this->assertSame(0, \App\Events\Models\EventBudgetScenario::count());   // it never writes

        // The tool results it read carry the platform's numbers.
        $seen = app('test.ai.driver')->seen;
        $toolTurn = collect(end($seen))->firstWhere('role', 'tool');
        $this->assertStringContainsString('"net_after":156', $toolTurn['content']);
    }

    public function test_a_money_question_answered_without_its_calculator_is_sent_back_once(): void
    {
        config(['events.budget.advisor' => true]);
        $organiser = $this->createUser();
        $event = $this->plannedEvent($organiser);

        $this->fakeAi([
            'You need to cut 194.',                       // a guess, no tool
            ['tools' => [['ways_to_save', []]]],         // sent back → it uses the calculator
            'Trim the hall.',
        ]);
        $res = $this->ask($organiser, $event, 'How can I cut costs?');

        $this->assertSame('Trim the hall.', $res->json('reply'));
        $this->assertContains('ways_to_save', $res->json('tools'));
    }

    public function test_a_limit_said_in_words_binds_the_tools_even_if_the_model_drops_it(): void
    {
        config(['events.budget.advisor' => true]);
        $organiser = $this->createUser();
        $event = $this->plannedEvent($organiser);

        // The model forgets "keep the fee" in its call; the toolbox still keeps it.
        $this->fakeAi([
            ['tools' => [['solve_for_goal', ['target_profit' => 150, 'keep_fee' => false]]]],
            ['tools' => [['apply_plan', ['plan_id' => 'p1']]]],
            'Done.',
        ]);
        $res = $this->ask($organiser, $event, 'Get me to 150 profit but keep the fee at 10.');

        $this->assertEquals(10, $res->json('proposal.fee'));
        $this->assertContains(__('events::events.budget_advisor_k_fee'), $res->json('kept'));
        $this->assertTrue($res->json('proposal.meets_target'));
    }

    public function test_propose_change_puts_an_exact_change_on_screen_and_never_cuts_a_paid_line(): void
    {
        config(['events.budget.advisor' => true]);
        $organiser = $this->createUser();
        $event = $this->plannedEvent($organiser);
        BudgetPlan::for($event)->items()->create(['category' => 'people', 'label' => 'Referees', 'amount' => 300, 'basis' => 'fixed', 'status' => 'paid']);

        $this->fakeAi([['tools' => [['propose_change', ['cut_line' => 'hall', 'cut_percent' => 10]]]], 'Cut the hall by 10%.']);
        $res = $this->ask($organiser, $event, 'cut the hall by 10%');
        $cut = collect($res->json('proposal.changes'))->firstWhere('field', 'item');
        $this->assertSame('Hall', $cut['label']);
        $this->assertEquals(939.6, $cut['to']);

        // A paid line is spent money — the toolbox refuses to cut it.
        $this->fakeAi([['tools' => [['propose_change', ['cut_line' => 'Referees', 'cut_percent' => 50]]]], 'ok']);
        $res = $this->ask($organiser, $event, 'cut the referees in half');
        $this->assertNull($res->json('proposal'));
    }

    public function test_without_a_model_the_advisor_still_answers_step_by_step(): void
    {
        config(['events.budget.advisor' => true]);
        $organiser = $this->createUser();
        $event = $this->plannedEvent($organiser);

        $this->fakeAi(null);
        $res = $this->ask($organiser, $event, 'help me reach my target')->assertJsonPath('source', 'facts');
        $this->assertEquals(12, $res->json('proposal.fee'));
        $this->assertNotSame('', $res->json('reply'));

        // Asked for savings with no model: the options, from the solver.
        $res = $this->ask($organiser, $event, 'what are the alternatives to save costs?')->assertJsonPath('source', 'facts');
        $this->assertStringContainsString('Hall', $res->json('reply'));
    }

    public function test_the_toolbox_answers_on_the_mcp_server_too(): void
    {
        $organiser = $this->createUser();
        $event = $this->plannedEvent($organiser);

        $out = $this->mcp(\App\Mcp\Tools\EventBudgetToolboxTool::class, $organiser, ['event' => $event->uuid, 'tool' => 'lowest_fee', 'args' => []]);
        $this->assertEquals(12, $out['result']['lowest_fee_for_target']);
        $this->assertEquals(10.5, $out['result']['break_even_fee']);   // 1044 / 100 = 10.44, rounded up to the 0.5 step

        $this->assertArrayHasKey('error', $this->mcp(\App\Mcp\Tools\EventBudgetToolboxTool::class, $this->createUser(), ['event' => $event->uuid, 'tool' => 'list_costs']));
    }

    public function test_the_agent_remembers_the_conversation_on_the_server(): void
    {
        config(['events.budget.advisor' => true]);
        $organiser = $this->createUser();
        $event = $this->plannedEvent($organiser);

        $this->fakeAi(['Hi! Happy to help.']);
        $this->ask($organiser, $event, 'hello');
        $this->fakeAi(['You said hello a moment ago.']);
        $this->ask($organiser, $event, 'what did I just say?');

        // The second call carried the first turn as memory — the client sent no history.
        $seen = app('test.ai.driver')->seen[0];
        $this->assertTrue(collect($seen)->contains(fn ($m) => $m['role'] === 'user' && $m['content'] === 'hello'));
        $this->assertTrue(collect($seen)->contains(fn ($m) => $m['role'] === 'assistant' && $m['content'] === 'Hi! Happy to help.'));

        $this->actingAs($organiser)->getJson(route('me.events.budget.advisor.history', $event->uuid))
            ->assertOk()->assertJsonCount(4, 'messages')->assertJsonPath('messages.0.content', 'hello');

        // Someone else's memory is their own.
        $this->actingAs($this->createUser())->getJson(route('me.events.budget.advisor.history', $event->uuid))->assertForbidden();

        $this->actingAs($organiser)->deleteJson(route('me.events.budget.advisor.reset', $event->uuid))->assertOk();
        $this->actingAs($organiser)->getJson(route('me.events.budget.advisor.history', $event->uuid))->assertJsonCount(0, 'messages');
    }

    public function test_the_agent_saves_when_told_and_asks_when_not(): void
    {
        config(['events.budget.advisor' => true]);
        $organiser = $this->createUser();
        $event = $this->plannedEvent($organiser);

        // Told to: it saves, and the page gets the fresh budget back.
        $this->fakeAi([['tools' => [['save_cost_line', ['name' => 'Referees', 'role' => 'referee', 'how_many' => 10, 'price_each' => 30]]]], 'Done — 10 referees at 30 each.']);
        $res = $this->ask($organiser, $event, 'add 10 referees at 30 each');
        $this->assertTrue($res->json('wrote'));
        $line = collect($res->json('budget.items'))->firstWhere('label', 'Referees');
        $this->assertSame(10, $line['quantity']);
        $this->assertEquals(300, $line['amount']);
        $this->assertSame('people', $line['category']);

        // Not told to: the tool refuses, nothing changes.
        $this->fakeAi([['tools' => [['save_cost_line', ['name' => 'Referees', 'how_many' => 6]]]], 'Shall I cut the referees to 6?']);
        $res = $this->ask($organiser, $event, 'are 10 referees too many?');
        $this->assertFalse($res->json('wrote'));
        $this->assertSame(10, BudgetPlan::for($event)->items()->where('label', 'Referees')->value('quantity'));

        // A yes to its question lets it save.
        $this->fakeAi([['tools' => [['save_cost_line', ['name' => 'Referees', 'how_many' => 6]]]], 'Saved: 6 referees.']);
        $res = $this->ask($organiser, $event, 'yes');
        $this->assertTrue($res->json('wrote'));
        $this->assertSame(6, BudgetPlan::for($event)->items()->where('label', 'Referees')->value('quantity'));
        $this->assertEquals(180, BudgetPlan::for($event)->items()->where('label', 'Referees')->value('amount'));

        // Removing a line and saving the plan, on instruction.
        $this->fakeAi([['tools' => [['remove_cost_line', ['name' => 'hall']], ['save_plan', ['target_profit' => 200, 'gold_price' => 4]]]], 'Removed the hall and set the target to 200.']);
        $res = $this->ask($organiser, $event, 'remove the hall and set the target to 200, gold medals cost 4');
        $this->assertNull(BudgetPlan::for($event)->items()->where('label', 'Hall')->first());
        $this->assertEquals(200, BudgetPlan::for($event)->target_profit);
        $this->assertEquals(4, BudgetPlan::for($event)->medal_gold_cost);
        $this->assertCount(2, $res->json('actions'));
    }

    public function test_the_advisor_is_the_organisers_alone_and_has_its_own_switch(): void
    {
        $organiser = $this->createUser();
        $event = $this->plannedEvent($organiser);
        $this->fakeAi(['hi']);

        config(['events.budget.advisor' => false]);
        $this->actingAs($organiser)->postJson(route('me.events.budget.advisor', $event->uuid), ['message' => 'x'])->assertNotFound();

        config(['events.budget.advisor' => true]);
        $this->actingAs($this->createUser())->postJson(route('me.events.budget.advisor', $event->uuid), ['message' => 'x'])->assertForbidden();
        $this->actingAs($organiser)->postJson(route('me.events.budget.advisor', $event->uuid), ['message' => str_repeat('x', 501)])->assertStatus(422);
    }
}
