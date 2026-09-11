<?php

namespace Tests\Feature\Events;

use App\Events\Support\BracketView;
use App\Events\Support\DivisionSections;
use App\Models\ClubEvent;
use App\Models\ClubEventRegistration;
use App\Models\EventCategory;
use App\Models\EventMatch;
use App\Members\Models\User;
use App\Translation\Translations;
use Tests\TestCase;

/**
 * The order of the divisions list, and what a heading means because of it.
 *
 * A heading (`event_categories.is_heading`) captions the divisions BELOW it,
 * until the next heading. That is a statement about POSITION, so a heading
 * that cannot be moved says nothing: new rows append, so every heading landed
 * underneath the twelve divisions it was written to label.
 *
 * Two things are protected here, and the second matters more than the first:
 *
 *  1. The order persists, and only the organiser may change it.
 *  2. Changing it moves NOTHING ELSE. Reordering the list a reader sees must
 *     never move an athlete between divisions or re-cut a bracket. Section
 *     membership is positional and does change — that is what dragging is for
 *     — but a division keeps every entrant and every bout it had.
 */
class DivisionOrderTest extends TestCase
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
            'title' => 'Ordered Open',
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

    private function row(ClubEvent $event, string $name, bool $heading = false, int $order = 0): EventCategory
    {
        return EventCategory::create([
            'event_id' => $event->id,
            'name' => $name,
            'is_heading' => $heading,
            'status' => 'enrolling',
            'sort_order' => $order,
        ]);
    }

    private function url(ClubEvent $event, string $tail = ''): string
    {
        return "/me/events/{$event->uuid}/divisions".$tail;
    }

    private function sections(ClubEvent $event): array
    {
        return (new DivisionSections)->build($event->fresh(), Translations::of($event));
    }

    /* ---------------- Reordering ---------------- */

    public function test_an_organiser_can_reorder_the_list_and_it_persists(): void
    {
        [$owner, $event] = $this->scenario();

        $a = $this->row($event, 'Group A', false, 1);
        $b = $this->row($event, 'Group B', false, 2);
        $gi = $this->row($event, 'Gi', true, 3);

        // The whole point: the heading created last belongs FIRST.
        $this->actingAs($owner)
            ->putJson($this->url($event, '/order'), ['order' => [$gi->id, $a->id, $b->id]])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame(
            [$gi->id, $a->id, $b->id],
            $event->fresh()->categories()->pluck('id')->all()
        );
        $this->assertSame([1, 2, 3], $event->fresh()->categories()->pluck('sort_order')->all());
    }

    /**
     * The assertion this whole feature has to earn.
     *
     * An organiser arranging a list must never wonder whether they have moved
     * an athlete. Every entrant, every bout and every draw state is
     * fingerprinted before and compared after.
     */
    public function test_reordering_moves_no_entrant_no_bout_and_no_draw(): void
    {
        [$owner, $event] = $this->scenario();

        $a = $this->row($event, 'Group A', false, 1);
        $b = $this->row($event, 'Group B', false, 2);
        $gi = $this->row($event, 'Gi', true, 3);
        $a->update(['draw_state' => 'provisional']);

        foreach ([[$a, 'Anne'], [$a, 'Beth'], [$b, 'Carl'], [$b, 'Dave']] as [$cat, $name]) {
            $user = User::create([
                'full_name' => $name, 'name' => $name,
                'email' => strtolower($name).'-'.str()->random(5).'@example.test',
                'password' => bcrypt('secret-secret'), 'email_verified_at' => now(),
            ]);
            ClubEventRegistration::create([
                'event_id' => $event->id, 'user_id' => $user->id, 'category_id' => $cat->id,
                'role' => 'participant', 'status' => 'joined', 'paid' => true,
                'registered_at' => now(), 'weight' => 70,
            ]);
        }

        $entrants = ClubEventRegistration::where('event_id', $event->id)
            ->orderBy('id')->get(['id', 'user_id', 'category_id'])->toJson();

        EventMatch::create([
            'event_id' => $event->id, 'category_id' => $a->id,
            'round' => 'Final', 'phase' => 'finals', 'slot' => 0,
            'a_name' => 'Anne', 'b_name' => 'Beth', 'status' => 'upcoming',
        ]);

        $bouts = EventMatch::where('event_id', $event->id)
            ->orderBy('id')->get(['id', 'category_id', 'a_name', 'b_name', 'winner'])->toJson();
        $draws = $event->categories()->orderBy('id')->pluck('draw_state', 'id')->sortKeys()->all();

        $this->actingAs($owner)
            ->putJson($this->url($event, '/order'), ['order' => [$gi->id, $b->id, $a->id]])
            ->assertOk();

        $this->assertSame($entrants, ClubEventRegistration::where('event_id', $event->id)
            ->orderBy('id')->get(['id', 'user_id', 'category_id'])->toJson());
        $this->assertSame($bouts, EventMatch::where('event_id', $event->id)
            ->orderBy('id')->get(['id', 'category_id', 'a_name', 'b_name', 'winner'])->toJson());
        $this->assertSame($draws, $event->fresh()->categories()
            ->orderBy('id')->pluck('draw_state', 'id')->sortKeys()->all());
    }

    public function test_an_order_that_does_not_describe_the_event_is_refused_whole(): void
    {
        [$owner, $event] = $this->scenario();

        $a = $this->row($event, 'A', false, 1);
        $b = $this->row($event, 'B', false, 2);
        $c = $this->row($event, 'C', false, 3);

        // A page held open while somebody else added a division is describing
        // an event that no longer exists. Applying part of it would interleave
        // the two silently, so none of it is applied.
        $this->actingAs($owner)
            ->putJson($this->url($event, '/order'), ['order' => [$c->id, $a->id]])
            ->assertStatus(409)
            ->assertJsonPath('stale', true);

        $this->assertSame([$a->id, $b->id, $c->id], $event->fresh()->categories()->pluck('id')->all());
    }

    public function test_an_order_naming_another_events_division_is_refused(): void
    {
        [$owner, $event] = $this->scenario();
        [, $other] = $this->scenario();

        $a = $this->row($event, 'A', false, 1);
        $b = $this->row($event, 'B', false, 2);
        $foreign = $this->row($other, 'Theirs', false, 1);

        $this->actingAs($owner)
            ->putJson($this->url($event, '/order'), ['order' => [$foreign->id, $a->id]])
            ->assertStatus(409);

        $this->assertSame([$a->id, $b->id], $event->fresh()->categories()->pluck('id')->all());
        $this->assertSame(1, $foreign->fresh()->sort_order);
    }

    public function test_a_stranger_cannot_reorder_the_list(): void
    {
        [, $event] = $this->scenario();
        $stranger = $this->createUser();

        $a = $this->row($event, 'A', false, 1);
        $b = $this->row($event, 'B', false, 2);

        $this->actingAs($stranger)
            ->putJson($this->url($event, '/order'), ['order' => [$b->id, $a->id]])
            ->assertForbidden();

        $this->assertSame([$a->id, $b->id], $event->fresh()->categories()->pluck('id')->all());
    }

    public function test_a_new_division_still_appends_to_the_end(): void
    {
        [$owner, $event] = $this->scenario();

        $this->row($event, 'A', false, 1);
        $gi = $this->row($event, 'Gi', true, 2);

        $this->actingAs($owner)->postJson($this->url($event), ['name' => 'Newest'])->assertOk();

        $names = $event->fresh()->categories()->pluck('name')->all();
        $this->assertSame('Newest', end($names));
        unset($gi);
    }

    /* ---------------- What a heading is, and is not ---------------- */

    public function test_a_heading_is_not_a_division_in_the_public_payload(): void
    {
        [, $event] = $this->scenario();

        $gi = $this->row($event, 'Gi', true, 1);
        $this->row($event, 'Group A', false, 2);
        $this->row($event, 'Group B', false, 3);

        $out = $this->sections($event);

        $this->assertSame(['Group A', 'Group B'], $out['divisions']);
        $this->assertNotContains('Gi', $out['divisions']);
        unset($gi);
    }

    public function test_the_sections_group_each_heading_with_the_divisions_under_it(): void
    {
        [, $event] = $this->scenario();

        $this->row($event, 'Gi', true, 1);
        $this->row($event, 'Gi -60', false, 2);
        $this->row($event, 'Gi -70', false, 3);
        $this->row($event, 'No-Gi', true, 4);
        $this->row($event, 'No-Gi -60', false, 5);

        $sections = $this->sections($event)['sections'];

        $this->assertCount(2, $sections);
        $this->assertSame('Gi', $sections[0]['heading']);
        $this->assertSame(['Gi -60', 'Gi -70'], $sections[0]['items']);
        $this->assertSame('No-Gi', $sections[1]['heading']);
        $this->assertSame(['No-Gi -60'], $sections[1]['items']);
    }

    public function test_divisions_before_the_first_heading_keep_a_section_of_their_own(): void
    {
        [, $event] = $this->scenario();

        $this->row($event, 'Loose One', false, 1);
        $this->row($event, 'Gi', true, 2);
        $this->row($event, 'Gi -60', false, 3);

        $sections = $this->sections($event)['sections'];

        $this->assertCount(2, $sections);
        // An event that never used a heading is ALL of this, so it must keep
        // rendering exactly as it always has.
        $this->assertNull($sections[0]['heading']);
        $this->assertSame(['Loose One'], $sections[0]['items']);
        $this->assertSame('Gi', $sections[1]['heading']);
    }

    public function test_a_heading_with_nothing_under_it_is_dropped(): void
    {
        [, $event] = $this->scenario();

        $this->row($event, 'Group A', false, 1);
        // Exactly the state the organiser was stuck in: headings appended
        // below every division, captioning nothing.
        $this->row($event, 'Gi', true, 2);
        $this->row($event, 'No-Gi', true, 3);

        $sections = $this->sections($event)['sections'];

        $this->assertCount(1, $sections);
        $this->assertNull($sections[0]['heading']);
        $this->assertSame(['Group A'], $sections[0]['items']);
    }

    public function test_the_bracket_payload_has_no_headings_in_it(): void
    {
        [, $event] = $this->scenario();

        $this->row($event, 'Gi', true, 1);
        $this->row($event, 'Group A', false, 2);

        $names = collect((new BracketView)->divisions($event->fresh()))->pluck('name')->all();

        // One payload feeds the manage board, the PUBLIC draw board and the MCP
        // bracket tool — a heading here was an empty bracket on all three.
        $this->assertSame(['Group A'], $names);
    }

    public function test_a_heading_survives_a_round_trip_through_the_event_edit_form(): void
    {
        [$owner, $event] = $this->scenario();

        $gi = $this->row($event, 'Gi', true, 1);
        $a = $this->row($event, 'Group A', false, 2);

        // What the edit form loads, and posts back. Before `is_heading` was
        // carried through, this turned every heading into a division.
        $type = app(\App\Events\EventTypeRegistry::class)->for($event);
        $type->saveRelatedData($event, [
            'divisions' => [
                ['name' => 'Gi', 'is_heading' => true],
                ['name' => 'Group A', 'capacity' => 16],
            ],
        ]);

        $this->assertTrue((bool) $gi->fresh()->is_heading);
        $this->assertFalse((bool) $a->fresh()->is_heading);
        // And a heading still carries nothing it must not.
        $this->assertNull($gi->fresh()->capacity);
    }

    public function test_a_heading_cannot_be_entered(): void
    {
        [$owner, $event] = $this->scenario();

        $gi = $this->row($event, 'Gi', true, 1);
        $athlete = $this->createUser();

        $decision = app(\App\Events\Support\EntryService::class)
            ->enter($event, $athlete, $owner, ['category_id' => $gi->id]);

        $this->assertFalse($decision->allowed ?? false, 'A heading accepted an entrant.');
        $this->assertSame(0, ClubEventRegistration::where('event_id', $event->id)->count());
    }
}
