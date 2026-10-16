<?php

namespace Tests\Feature\Events;

use App\Clubs\Models\Tenant;
use App\Events\Models\ClubEvent;
use App\Events\Models\ClubEventRegistration;
use App\Events\Models\EventCategory;
use App\Events\Models\EventMatch;
use App\Events\Models\EventRunSlot;
use App\Events\Support\RunOrder;
use App\Members\Models\HealthRecord;
use App\Members\Models\User;
use App\Sports\Combat\Engine\Scheduler;
use Tests\TestCase;

/**
 * GROUPS on a running order (RunSessions): each finishes all its bouts
 * before the next, called one class at a time, round by round (World
 * Taekwondo), or manually.
 *
 * Three classes on one mat — −50 kg (8), −60 kg (4), −70 kg (2): eleven bouts.
 */
class RunOrderGroupsTest extends TestCase
{
    private Tenant $club;

    private User $organiser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organiser = $this->createUser(['full_name' => 'Master Kim']);
        $this->club = $this->createClub($this->organiser, ['country' => 'BH', 'currency' => 'BHD']);
        $this->organiser->memberClubs()->syncWithoutDetaching([$this->club->id => ['status' => 'active']]);
    }

    /** @return array{0: ClubEvent, 1: array<string, EventCategory>} */
    private function day(): array
    {
        $event = ClubEvent::create([
            'tenant_id' => $this->club->id, 'created_by' => $this->organiser->id, 'title' => 'Spring Open',
            'event_type' => 'championship', 'sport' => 'taekwondo', 'scope' => 'internal',
            'date' => now()->addWeeks(2)->toDateString(), 'end_date' => now()->addWeeks(2)->toDateString(),
            'start_time' => '09:00', 'end_time' => '17:00', 'status' => 'active', 'is_archived' => false,
            'courts' => 1,
        ]);

        $classes = [];
        foreach (['-50' => 8, '-60' => 4, '-70' => 2] as $kg => $players) {
            $cat = EventCategory::create(['event_id' => $event->id, 'name' => "Senior Male {$kg} kg", 'sort_order' => count($classes) + 1]);
            foreach (range(1, $players) as $i) {
                $u = $this->createUser(['full_name' => "{$kg} Athlete {$i}", 'gender' => 'Male', 'birthdate' => now()->subYears(25)->toDateString()]);
                HealthRecord::create(['user_id' => $u->id, 'weight' => 57, 'recorded_at' => now()]);
                ClubEventRegistration::create([
                    'event_id' => $event->id, 'user_id' => $u->id, 'category_id' => $cat->id,
                    'role' => 'participant', 'paid' => true, 'weight' => 57,
                ]);
            }
            $classes[$kg] = $cat;
        }

        $this->actingAs($this->organiser)->postJson("/me/events/{$event->uuid}/actions/generate_draw")->assertOk();

        return [$event->fresh(), $classes];
    }

    /** The mat's order as "-50 Quarterfinal", "-60 Semifinal"… by bout number. */
    private function sequence(ClubEvent $event, array $classes): array
    {
        $kgOf = array_flip(array_map(fn ($c) => $c->id, $classes));

        return EventMatch::where('event_id', $event->id)->whereNotNull('match_no')
            ->where('round', '!=', 'Third place')
            ->orderBy('match_no')->get()
            ->map(fn ($m) => $kgOf[$m->category_id].' '.$m->round)->all();
    }

    /**
     * The running order, top to bottom. Each item is a class ("-50"), a group
     * ("group:round") whose box the following classes go into, a break
     * ("break", on the main list — it closes the box), or "end" (closes it).
     *
     * @return array<int, EventRunSlot>  the groups, in order
     */
    private function lay(ClubEvent $event, array $classes, array $items): array
    {
        $order = app(RunOrder::class);
        $groups = [];
        $box = null;
        foreach ($items as $item) {
            if (str_starts_with($item, 'group:')) {
                $groups[] = $box = $order->addBlock($event, EventRunSlot::KIND_GROUP, null, null, 1, substr($item, 6));
            } elseif ($item === 'break') {
                $order->addBlock($event, EventRunSlot::KIND_BREAK, 'Lunch', 60, 1);
                $box = null;
            } elseif ($item === 'end') {
                $box = null;
            } else {
                $order->addDivisions($event, [$classes[$item]->id], 1, false, $box);
            }
        }

        return $groups;
    }

    private function slotOf(ClubEvent $event, EventCategory $class): EventRunSlot
    {
        return EventRunSlot::where('event_id', $event->id)->where('category_id', $class->id)->sole();
    }

    private function renumber(ClubEvent $event): void
    {
        app(Scheduler::class)->scheduleAndNumber($event->fresh());
    }

    public function test_with_the_flag_off_groups_change_nothing(): void
    {
        config(['events.session_modes' => true]);
        [$event, $classes] = $this->day();
        $this->lay($event, $classes, ['group:round', '-50', '-60', '-70']);
        config(['events.session_modes' => false]);
        $this->renumber($event);

        $this->assertSame([
            '-50 Quarterfinal', '-50 Quarterfinal', '-50 Quarterfinal', '-50 Quarterfinal',
            '-50 Semifinal', '-50 Semifinal', '-50 Final',
            '-60 Semifinal', '-60 Semifinal', '-60 Final',
            '-70 Final',
        ], $this->sequence($event, $classes));
    }

    public function test_a_round_by_round_group_numbers_every_class_round_before_the_next_and_the_finals_last(): void
    {
        config(['events.session_modes' => true]);
        [$event, $classes] = $this->day();
        $this->lay($event, $classes, ['group:round', '-50', '-60', '-70']);
        $this->renumber($event);

        $this->assertSame([
            '-50 Quarterfinal', '-50 Quarterfinal', '-50 Quarterfinal', '-50 Quarterfinal',
            '-50 Semifinal', '-50 Semifinal', '-60 Semifinal', '-60 Semifinal',
            '-50 Final', '-60 Final', '-70 Final',
        ], $this->sequence($event, $classes));

        $this->assertSame(range(1, 11), EventMatch::where('event_id', $event->id)->whereNotNull('match_no')
            ->where('round', '!=', 'Third place')->orderBy('match_no')->pluck('match_no')->all());
    }

    public function test_back_to_back_groups_each_finish_before_the_next_and_keep_their_own_method(): void
    {
        config(['events.session_modes' => true]);
        [$event, $classes] = $this->day();
        // Group 1: -60 and -70 one class at a time. Group 2: -50 alone, round by round.
        [$first] = $this->lay($event, $classes, ['group:class', '-60', '-70', 'group:round', '-50']);
        $this->renumber($event);

        $this->assertSame([
            '-60 Semifinal', '-60 Semifinal', '-60 Final', '-70 Final',
            '-50 Quarterfinal', '-50 Quarterfinal', '-50 Quarterfinal', '-50 Quarterfinal',
            '-50 Semifinal', '-50 Semifinal', '-50 Final',
        ], $this->sequence($event, $classes));

        // Switching the first group to round by round reorders only that group.
        $this->actingAs($this->organiser)->putJson("/me/events/{$event->uuid}/day-order-session",
            ['day' => 1, 'group' => $first->uuid, 'mode' => 'round'])->assertOk()->assertJson(['success' => true]);
        $this->assertSame('round', $first->fresh()->mode);
        $this->assertSame(['-60 Semifinal', '-60 Semifinal', '-60 Final', '-70 Final'],
            array_slice($this->sequence($event, $classes), 0, 4));
    }

    public function test_a_class_in_no_group_runs_whole_and_the_box_finishes_first(): void
    {
        config(['events.session_modes' => true]);
        [$event, $classes] = $this->day();
        $this->lay($event, $classes, ['group:round', '-60', '-70', 'break', '-50']);
        $this->renumber($event);

        $this->assertSame([
            '-60 Semifinal', '-60 Semifinal', '-60 Final', '-70 Final',
            '-50 Quarterfinal', '-50 Quarterfinal', '-50 Quarterfinal', '-50 Quarterfinal',
            '-50 Semifinal', '-50 Semifinal', '-50 Final',
        ], $this->sequence($event, $classes));
    }

    public function test_dragging_a_class_into_a_box_out_of_it_and_a_break_never_in(): void
    {
        config(['events.session_modes' => true]);
        [$event, $classes] = $this->day();
        [$box] = $this->lay($event, $classes, ['group:round', '-50', 'end', '-60', 'break', '-70']);
        $move = fn (EventRunSlot $s, ?EventRunSlot $g, int $i) => $this->actingAs($this->organiser)
            ->putJson("/me/events/{$event->uuid}/day-order-move", ['day' => 1, 'slot' => $s->uuid, 'group' => $g?->uuid, 'index' => $i]);

        // -60 into the box, first.
        $move($this->slotOf($event, $classes['-60']), $box, 0)->assertOk()->assertJson(['success' => true]);
        $this->assertSame($box->id, $this->slotOf($event, $classes['-60'])->group_id);
        $rows = collect(app(\App\Events\Support\RunSheet::class)->rows($event->fresh(), 1));
        $this->assertSame(['group', 'division', 'division', 'break', 'division'], $rows->pluck('kind')->all());
        $this->assertSame($box->uuid, $rows[1]['group']);
        $this->assertSame('Senior Male -60 kg', $rows[1]['name']);

        // -50 out of the box, to the end of the main list.
        $move($this->slotOf($event, $classes['-50']), null, 9)->assertOk();
        $this->assertNull($this->slotOf($event, $classes['-50'])->group_id);

        // A break (or the awarding) goes into a box and back out by the same drag.
        $break = EventRunSlot::where('event_id', $event->id)->where('kind', 'break')->sole();
        $move($break, $box, 0)->assertOk();
        $this->assertSame($box->id, $break->fresh()->group_id);
        $rows = collect(app(\App\Events\Support\RunSheet::class)->rows($event->fresh(), 1));
        $this->assertSame($box->uuid, $rows->firstWhere('uuid', $break->uuid)['group']);
        $move($break, null, 0)->assertOk();
        $this->assertNull($break->fresh()->group_id);

        // A group never goes inside another.
        $other = app(RunOrder::class)->addBlock($event, EventRunSlot::KIND_GROUP, null, null, 1, 'class');
        $move($other, $box, 0)->assertStatus(422);
        $this->assertNull($other->fresh()->group_id);
    }

    public function test_a_group_and_a_block_rename_in_place_and_a_class_does_not(): void
    {
        config(['events.session_modes' => true]);
        [$event, $classes] = $this->day();
        [$box] = $this->lay($event, $classes, ['group:class', '-50']);
        $class = $this->slotOf($event, $classes['-50']);

        $this->actingAs($this->organiser)->putJson("/me/events/{$event->uuid}/day-order/{$box->uuid}", ['label' => 'Kids morning'])
            ->assertOk()->assertJson(['success' => true]);
        $this->assertSame('Kids morning', $box->fresh()->label);

        // A class is named by its division — the slot has no title of its own to change.
        $this->actingAs($this->organiser)->putJson("/me/events/{$event->uuid}/day-order/{$class->uuid}", ['label' => 'Nope'])->assertOk();
        $this->assertNull($class->fresh()->label);
        $this->assertSame('Senior Male -50 kg', $classes['-50']->fresh()->name);
    }

    public function test_each_row_carries_its_classes_gender_for_the_card_colour(): void
    {
        config(['events.session_modes' => true]);
        [$event, $classes] = $this->day();
        $classes['-50']->update(['gender' => 'female']);
        $classes['-60']->update(['gender' => null]);   // read from its name ("Senior Male -60 kg")
        $classes['-70']->update(['gender' => null, 'name' => 'Open Kumite -70 kg']);
        $this->lay($event, $classes, ['-50', '-60', '-70']);
        app(RunOrder::class)->addBlock($event, EventRunSlot::KIND_CEREMONY, 'Awarding', 15, 1);

        $rows = collect(app(\App\Events\Support\RunSheet::class)->rows($event->fresh(), 1));
        $this->assertSame(['female', 'male', null, null], $rows->pluck('gender')->all());
    }

    public function test_a_box_can_add_new_classes_and_pull_in_ones_already_on_the_day(): void
    {
        config(['events.session_modes' => true]);
        [$event, $classes] = $this->day();
        [$box] = $this->lay($event, $classes, ['group:class', 'end', '-60']);

        $this->actingAs($this->organiser)->postJson("/me/events/{$event->uuid}/day-order", [
            'day' => 1, 'group' => $box->uuid,
            'divisions' => [['id' => $classes['-50']->id, 'parts' => null]],
            'slots' => [$this->slotOf($event, $classes['-60'])->uuid],
        ])->assertOk();

        $this->assertSame($box->id, $this->slotOf($event, $classes['-50'])->group_id);
        $this->assertSame($box->id, $this->slotOf($event, $classes['-60'])->group_id);
    }

    public function test_removing_a_group_keeps_its_classes_on_the_day(): void
    {
        config(['events.session_modes' => true]);
        [$event, $classes] = $this->day();
        [$box] = $this->lay($event, $classes, ['group:round', '-50', '-60']);

        $this->actingAs($this->organiser)->deleteJson("/me/events/{$event->uuid}/day-order/{$box->uuid}")->assertOk();

        $this->assertNull(EventRunSlot::find($box->id));
        $this->assertNull($this->slotOf($event, $classes['-50'])->group_id);
        $this->assertNull($this->slotOf($event, $classes['-60'])->group_id);
    }

    public function test_manual_keeps_the_order_the_bouts_already_have(): void
    {
        config(['events.session_modes' => true]);
        [$event, $classes] = $this->day();
        [$group] = $this->lay($event, $classes, ['group:class', '-50', '-60', '-70']);
        $this->renumber($event);

        // Swap the -70 final up to bout 1 by hand, as the bout board would.
        $final70 = EventMatch::where('category_id', $classes['-70']->id)->where('round', 'Final')->sole();
        $first = EventMatch::where('event_id', $event->id)->where('match_no', 1)->sole();
        $first->update(['match_no' => $final70->match_no]);
        $final70->update(['match_no' => 0]);

        $this->actingAs($this->organiser)->putJson("/me/events/{$event->uuid}/day-order-session",
            ['day' => 1, 'group' => $group->uuid, 'mode' => 'manual'])->assertOk();

        $sequence = $this->sequence($event, $classes);
        $this->assertSame('-70 Final', $sequence[0], 'the hand-set order survives a renumber');
        $this->assertCount(11, $sequence);
    }

    public function test_the_sheet_marks_groups_and_spreads_a_mixed_class_over_its_group(): void
    {
        config(['events.session_modes' => true]);
        [$event, $classes] = $this->day();
        [$group] = $this->lay($event, $classes, ['-70', 'group:round', '-50', '-60']);

        $rows = $this->actingAs($this->organiser)->getJson("/me/events/{$event->uuid}/day-order/data?day=1")
            ->assertOk()->json('sheet.rows');

        $this->assertNull($rows[0]['group'], 'a class above the first group belongs to none');
        $this->assertSame(['mode' => 'round', 'size' => 2], $rows[1]['group_head']);
        $this->assertSame(0, $rows[1]['duration'], 'a group takes no time');
        $this->assertSame($group->uuid, $rows[2]['group']);
        $this->assertTrue($rows[2]['interleaved']);
        $this->assertSame($rows[2]['starts_at'], $rows[3]['starts_at']);
    }

    public function test_a_group_can_be_added_with_its_method_and_the_public_page_does_not_show_it(): void
    {
        config(['events.session_modes' => true]);
        [$event, $classes] = $this->day();

        $this->actingAs($this->organiser)->postJson("/me/events/{$event->uuid}/day-order",
            ['day' => 1, 'kind' => 'group', 'label' => 'Seniors', 'mode' => 'round'])->assertOk();

        $group = EventRunSlot::where('event_id', $event->id)->where('kind', 'group')->sole();
        $this->assertSame(['Seniors', 'round', 0], [$group->label, $group->mode, $group->duration_minutes]);

        $html = view('components.event-run-order', ['rows' => [
            ['uuid' => 'a', 'kind' => 'group', 'name' => 'Seniors', 'starts_at' => 540, 'ends_at' => 540, 'status' => 'upcoming'],
        ]])->render();
        $this->assertStringNotContainsString('Seniors', $html);
    }

    public function test_only_the_organiser_may_change_a_group_and_only_their_own(): void
    {
        config(['events.session_modes' => true]);
        [$event, $classes] = $this->day();
        [$group] = $this->lay($event, $classes, ['group:class', '-50']);

        $this->actingAs($this->createUser())->putJson("/me/events/{$event->uuid}/day-order-session",
            ['day' => 1, 'group' => $group->uuid, 'mode' => 'round'])->assertForbidden();

        [$other] = $this->day();
        $foreign = app(RunOrder::class)->addBlock($other, EventRunSlot::KIND_GROUP, null, null, 1, 'class');
        $this->actingAs($this->organiser)->putJson("/me/events/{$event->uuid}/day-order-session",
            ['day' => 1, 'group' => $foreign->uuid, 'mode' => 'round'])->assertNotFound();
        $this->assertSame('class', $foreign->fresh()->mode);

        // A slot of this event that is not a group is refused too.
        $brk = app(RunOrder::class)->addBlock($event, EventRunSlot::KIND_BREAK, 'Lunch', 60, 1);
        $this->actingAs($this->organiser)->putJson("/me/events/{$event->uuid}/day-order-session",
            ['day' => 1, 'group' => $brk->uuid, 'mode' => 'round'])->assertStatus(422);

        $this->actingAs($this->organiser)->putJson("/me/events/{$event->uuid}/day-order-session",
            ['day' => 1, 'group' => $group->uuid, 'mode' => 'sideways'])->assertStatus(422);
    }

    public function test_the_door_and_the_kind_are_closed_while_the_flag_is_off(): void
    {
        config(['events.session_modes' => false]);
        [$event] = $this->day();

        $this->actingAs($this->organiser)->putJson("/me/events/{$event->uuid}/day-order-session",
            ['day' => 1, 'group' => (string) \Illuminate\Support\Str::uuid(), 'mode' => 'round'])->assertNotFound();
        $this->actingAs($this->organiser)->postJson("/me/events/{$event->uuid}/day-order",
            ['day' => 1, 'kind' => 'group'])->assertStatus(422);
    }

    public function test_the_running_order_page_draws_the_group_controls_on_desktop_and_mobile(): void
    {
        config(['events.session_modes' => true]);
        [$event, $classes] = $this->day();
        $this->lay($event, $classes, ['group:round', '-50']);

        $this->actingAs($this->organiser)->get("/me/events/{$event->uuid}/order?view=day")
            ->assertOk()->assertSee(__('events::events.run_group_add'))->assertSee(__('events::events.run_session_round'))
            ->assertSee('data-drop-list', false)->assertSee(__('events::events.run_group_on_day'));

        $this->actingAs($this->organiser)
            ->withHeader('User-Agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148')
            ->get("/me/events/{$event->uuid}/order?view=day")
            ->assertOk()->assertSee(__('events::events.run_group_add'))->assertSee(__('events::events.run_session_manual'));
    }

    public function test_mcp_reads_and_sets_a_group_for_the_organiser_only(): void
    {
        config(['events.session_modes' => true]);
        [$event, $classes] = $this->day();
        [$g1, $g2] = $this->lay($event, $classes, ['group:class', '-50', 'group:class', '-60', '-70']);

        $member = $this->createUser();
        $member->memberClubs()->syncWithoutDetaching([$this->club->id => ['status' => 'active']]);
        $this->actingAs($member->fresh());
        $denied = $this->callMcp(['event' => $event->uuid, 'group' => $g2->uuid, 'mode' => 'round']);
        $this->assertStringContainsString('organiser', $denied['error'] ?? '');
        $this->assertSame('class', $g2->fresh()->mode);

        $this->actingAs($this->organiser);
        $read = $this->callMcp(['event' => $event->uuid]);
        $this->assertCount(2, $read['groups']);

        $set = $this->callMcp(['event' => $event->uuid, 'group' => $g2->uuid, 'mode' => 'round']);
        $this->assertSame('round', $set['groups'][1]['mode']);
        $this->assertSame(['Senior Male -60 kg', 'Senior Male -70 kg'], $set['groups'][1]['divisions']);

        // …and puts a class into a group by name.
        $put = $this->callMcp(['event' => $event->uuid, 'group' => $g1->uuid, 'divisions' => ['Senior Male -70 kg']]);
        $this->assertSame(['Senior Male -50 kg', 'Senior Male -70 kg'], $put['groups'][0]['divisions']);
    }

    private function callMcp(array $args): array
    {
        // Same shape as McpServerTest::callTool — an error is a plain string.
        $text = (string) (new \App\Mcp\Tools\SetRunningOrderGroupTool)->handle(new \Laravel\Mcp\Request($args))->content();
        $decoded = json_decode($text, true);

        return is_array($decoded) ? $decoded : ['error' => $text];
    }
}
