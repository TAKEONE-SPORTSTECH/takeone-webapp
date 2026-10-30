<?php

namespace Tests\Feature\Events;

use App\Events\Models\ClubEvent;
use App\Events\Models\ClubEventRegistration;
use App\Events\Models\EventCategory;
use App\Events\Models\EventExhibitionBout;
use App\Events\Models\EventMatch;
use App\Events\Models\EventOfficial;
use Tests\TestCase;

/**
 * Linking a side exhibition to the draw bout it really was
 * (App\Events\Support\ExhibitionLink, the console's Exhibitions tab).
 */
class ExhibitionLinkTest extends TestCase
{
    /**
     * Four entrants: two semi-finals (A–B, C–D) feeding an empty final.
     *
     * @return array{0: \App\Members\Models\User, 1: ClubEvent, 2: array<string, ClubEventRegistration>, 3: array<string, EventMatch>}
     */
    private function bracket(bool $started = true): array
    {
        config(['events.exhibitions' => true]);

        $owner = $this->createUser();
        $club = $this->createClub($owner, ['country' => 'BH', 'currency' => 'BHD']);

        $event = ClubEvent::create([
            'tenant_id' => $club->id, 'created_by' => $owner->id,
            'title' => 'Link Open', 'event_type' => 'championship', 'sport' => 'karate',
            'scope' => 'internal', 'status' => 'active', 'is_archived' => false,
            'date' => now()->toDateString(), 'start_time' => '09:00', 'end_time' => '17:00',
        ]);
        if ($started) {
            $event->forceFill(['started_at' => now()])->save();
        }

        $p = [];
        foreach (['A', 'B', 'C', 'D'] as $n) {
            $p[$n] = ClubEventRegistration::create([
                'event_id' => $event->id, 'user_id' => $this->createUser()->id,
                'role' => 'participant', 'status' => 'registered',
            ]);
        }

        $cat = EventCategory::create(['event_id' => $event->id, 'name' => 'Senior Male -60 kg', 'sort_order' => 1]);
        $bout = fn (int $slot, string $round, ?string $a, ?string $b, int $no) => EventMatch::create([
            'event_id' => $event->id, 'category_id' => $cat->id, 'round' => $round, 'phase' => strtolower($round),
            'slot' => $slot, 'match_no' => $no, 'court' => 'Mat 1', 'status' => 'upcoming',
            'a_competitor_id' => $a ? $p[$a]->id : null, 'a_name' => $a,
            'b_competitor_id' => $b ? $p[$b]->id : null, 'b_name' => $b,
        ]);

        $m = [
            'sf1' => $bout(0, 'Semifinal', 'A', 'B', 1),
            'sf2' => $bout(1, 'Semifinal', 'C', 'D', 2),
            'final' => $bout(2, 'Final', null, null, 3),
        ];

        return [$owner, $event->fresh(), $p, $m];
    }

    private function exhibition(ClubEvent $event, ClubEventRegistration $a, ClubEventRegistration $b, string $winner, array $extra = []): EventExhibitionBout
    {
        $n = (int) EventExhibitionBout::where('event_id', $event->id)->max('number') + 1;

        return EventExhibitionBout::create($extra + [
            'event_id' => $event->id, 'number' => $n, 'court' => 'Mat 2',
            'a_registration_id' => $a->id, 'b_registration_id' => $b->id,
            'a_name' => 'x', 'b_name' => 'y', 'a_score' => '3', 'b_score' => '1',
            'winner' => $winner, 'status' => EventExhibitionBout::STATUS_DONE,
        ]);
    }

    private function link($user, ClubEvent $event, EventExhibitionBout $ex, EventMatch $m)
    {
        return $this->actingAs($user)->postJson(
            "/karate/control/{$event->uuid}/exhibitions/{$ex->uuid}/link",
            ['match_id' => $m->id]
        );
    }

    public function test_the_same_pair_is_offered_first_and_linking_files_the_result(): void
    {
        [$owner, $event, $p, $m] = $this->bracket();
        $ex = $this->exhibition($event, $p['A'], $p['B'], 'a');

        $list = $this->actingAs($owner)->getJson("/karate/control/{$event->uuid}/exhibitions")->assertOk();
        $this->assertSame(1, $list->json('matched'));
        $this->assertSame($m['sf1']->id, $list->json('exhibitions.0.candidates.0.id'));
        $this->assertSame('exact', $list->json('exhibitions.0.candidates.0.kind'));

        $this->link($owner, $event, $ex, $m['sf1'])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('exhibition.status', 'linked')
            ->assertJsonPath('exhibition.linked.id', $m['sf1']->id);

        $sf1 = $m['sf1']->fresh();
        $this->assertSame('a', $sf1->winner);
        $this->assertSame('3', $sf1->a_score);
        $this->assertSame('done', $sf1->status);
        // The winner walked into the final, exactly as from the scoring table.
        $this->assertSame($p['A']->id, $m['final']->fresh()->a_competitor_id);

        $ex->refresh();
        $this->assertSame('linked', $ex->status);
        $this->assertSame($m['sf1']->id, $ex->linked_match_id);
        $this->assertSame($owner->id, $ex->linked_by);
    }

    public function test_corners_fought_the_other_way_round_are_turned(): void
    {
        [$owner, $event, $p, $m] = $this->bracket();
        // B stood in the red corner, and won.
        $ex = $this->exhibition($event, $p['B'], $p['A'], 'a');

        $this->link($owner, $event, $ex, $m['sf1'])->assertOk();

        $sf1 = $m['sf1']->fresh();
        $this->assertSame('b', $sf1->winner);
        $this->assertSame('3', $sf1->b_score);
        $this->assertSame('1', $sf1->a_score);
        $this->assertSame($p['B']->id, $m['final']->fresh()->a_competitor_id);
    }

    public function test_a_bout_still_waiting_on_earlier_results_seats_them_and_survives_those_results(): void
    {
        [$owner, $event, $p, $m] = $this->bracket();
        // The final was fought before either semi was filed.
        $ex = $this->exhibition($event, $p['A'], $p['C'], 'a');

        $cands = $this->actingAs($owner)->getJson("/karate/control/{$event->uuid}/exhibitions")
            ->json('exhibitions.0.candidates');
        $this->assertSame([$m['final']->id], array_column($cands, 'id'));
        $this->assertSame('seat', $cands[0]['kind']);

        $this->link($owner, $event, $ex, $m['final'])->assertOk();

        $final = $m['final']->fresh();
        $this->assertSame([$p['A']->id, $p['C']->id], [$final->a_competitor_id, $final->b_competitor_id]);
        $this->assertSame('a', $final->winner);

        // The semis land later, the same way: the final's result stands.
        $sf = new \App\Events\Sports\Karate\Tournament\Advancement(app(\App\Events\Sports\Karate\Karate::class));
        $sf->record($m['sf1']->fresh()->load('category'), ['winner' => 'a']);
        $sf->record($m['sf2']->fresh()->load('category'), ['winner' => 'a']);
        $this->assertSame('a', $m['final']->fresh()->winner);
    }

    public function test_a_bout_holding_somebody_else_or_a_first_round_bye_is_never_offered_or_taken(): void
    {
        [$owner, $event, $p, $m] = $this->bracket();
        // A and C never meet in a semi; A–C is only possible as the final.
        $ex = $this->exhibition($event, $p['A'], $p['C'], 'a');

        $this->link($owner, $event, $ex, $m['sf1'])->assertStatus(422);
        $this->assertNull($m['sf1']->fresh()->winner);

        // A first-round slot left empty is a bye, not a place to fill.
        $m['sf2']->forceFill(['b_competitor_id' => null, 'b_name' => null])->save();
        $ex2 = $this->exhibition($event, $p['C'], $p['B'], 'a');
        $this->link($owner, $event, $ex2, $m['sf2'])->assertStatus(422);
        $this->assertNull($m['sf2']->fresh()->b_competitor_id);
        $this->assertSame('done', $ex2->fresh()->status);
    }

    public function test_a_decided_bout_and_an_already_linked_exhibition_are_refused(): void
    {
        [$owner, $event, $p, $m] = $this->bracket();
        $ex = $this->exhibition($event, $p['A'], $p['B'], 'a');
        $this->link($owner, $event, $ex, $m['sf1'])->assertOk();

        // Linking it again, or a second copy of the same bout, files nothing.
        $this->link($owner, $event, $ex->fresh(), $m['sf1'])->assertStatus(422);
        $copy = $this->exhibition($event, $p['A'], $p['B'], 'b');
        $this->link($owner, $event, $copy, $m['sf1'])->assertStatus(422);
        $this->assertSame('a', $m['sf1']->fresh()->winner);
    }

    public function test_a_bout_already_typed_into_the_draw_takes_the_real_score_without_moving_the_winner(): void
    {
        [$owner, $event, $p, $m] = $this->bracket();
        // On the day the result was typed in by hand: A won, 0–0, "kiken".
        app(\App\Events\EventTypeRegistry::class)->for($event)->recordOutcome($event, $m['sf1']->id,
            ['winner' => 'a', 'a_score' => '0', 'b_score' => '0', 'win_reason' => 'kiken', 'status' => 'done']);
        // What was really scored, B standing in red.
        $ex = $this->exhibition($event, $p['B'], $p['A'], 'b', ['a_score' => '2', 'b_score' => '5']);

        $c = $this->actingAs($owner)->getJson("/karate/control/{$event->uuid}/exhibitions")->json('exhibitions.0.candidates.0');
        $this->assertSame([$m['sf1']->id, 'recorded', true], [$c['id'], $c['kind'], $c['agrees']]);
        $this->assertSame('0', $c['recorded']['a']);

        $this->link($owner, $event, $ex, $m['sf1'])->assertOk()->assertJsonPath('exhibition.status', 'linked');

        $sf1 = $m['sf1']->fresh();
        $this->assertSame(['a', '5', '2', null], [$sf1->winner, $sf1->a_score, $sf1->b_score, $sf1->win_reason]);
        $this->assertSame($p['A']->id, $m['final']->fresh()->a_competitor_id);
        $this->assertSame($m['sf1']->id, $ex->fresh()->linked_match_id);
    }

    public function test_a_bout_the_draw_gave_to_the_other_competitor_is_shown_but_never_linked(): void
    {
        [$owner, $event, $p, $m] = $this->bracket();
        app(\App\Events\EventTypeRegistry::class)->for($event)->recordOutcome($event, $m['sf1']->id,
            ['winner' => 'a', 'a_score' => '0', 'b_score' => '0', 'status' => 'done']);
        $ex = $this->exhibition($event, $p['A'], $p['B'], 'b');

        $list = $this->actingAs($owner)->getJson("/karate/control/{$event->uuid}/exhibitions");
        $this->assertFalse($list->json('exhibitions.0.candidates.0.agrees'));
        $this->assertSame(0, $list->json('matched'));

        $this->link($owner, $event, $ex, $m['sf1'])->assertStatus(422);
        $sf1 = $m['sf1']->fresh();
        $this->assertSame(['a', '0', '0'], [$sf1->winner, $sf1->a_score, $sf1->b_score]);
        $this->assertSame('done', $ex->fresh()->status);
    }

    public function test_the_footage_and_the_score_log_follow_the_link(): void
    {
        [$owner, $event, $p, $m] = $this->bracket();
        $ex = $this->exhibition($event, $p['B'], $p['A'], 'a', ['number' => 8, 'court' => 'Mat 2']);

        $camera = \App\Events\Models\EventCamera::create([
            'event_id' => $event->id, 'court' => 'Mat 2', 'angle' => 1, 'token_hash' => hash('sha256', 'cam'),
        ]);
        $clip = fn (string $ref) => \App\Events\Models\EventCameraClip::create([
            'camera_id' => $camera->id, 'event_id' => $event->id, 'court' => 'Mat 2', 'local_ref' => $ref,
        ]);
        $mine = $clip('mat-mat-2_angle-1_bout-ex8_20261017-160954.mp4');
        $other = $clip('mat-mat-2_angle-1_bout-ex81_20261017-170000.mp4');

        // What the mat logged while the exhibition ran: red (its a = B) scored.
        \App\Events\Models\EventMatchEvent::create([
            'event_id' => $event->id, 'match_id' => null, 'court' => 'Mat 2', 'sport' => 'karate',
            'command' => 'point', 'payload' => ['side' => 'aka', 'n' => 3], 'side' => 'a', 'points' => 3,
            'score_a' => 3, 'score_b' => 0, 'occurred_at' => $ex->created_at, 'sequence' => 1,
        ]);

        $this->link($owner, $event, $ex, $m['sf1'])->assertOk();

        $this->assertSame($m['sf1']->id, $mine->fresh()->match_id);
        $this->assertNull($other->fresh()->match_id);

        // The bout reads the exhibition's log, turned to the draw's corners.
        $rows = app(\App\Events\Support\ExhibitionLink::class)->rowsForBout($m['sf1']->fresh());
        $this->assertSame(1, $rows->count());
        $this->assertSame(['b', 0, 3], [$rows[0]->side, (int) $rows[0]->score_a, (int) $rows[0]->score_b]);

        // Any other bout reads its own log, as before.
        $this->assertNull(app(\App\Events\Support\ExhibitionLink::class)->rowsForBout($m['sf2']));
    }

    public function test_any_bout_can_be_chosen_and_is_attached_without_touching_the_draw(): void
    {
        [$owner, $event, $p, $m] = $this->bracket();
        // A pair the system cannot place (A–C in a semi that holds C–D).
        $ex = $this->exhibition($event, $p['A'], $p['C'], 'a');
        $before = $m['sf2']->fresh()->toArray();

        // Not without saying so…
        $this->link($owner, $event, $ex, $m['sf2'])->assertStatus(422);

        // …but chosen by hand, it is attached.
        $this->actingAs($owner)->postJson("/karate/control/{$event->uuid}/exhibitions/{$ex->uuid}/link",
            ['match_id' => $m['sf2']->id, 'attach_only' => true])
            ->assertOk()->assertJsonPath('exhibition.status', 'linked')->assertJsonPath('exhibition.linked.id', $m['sf2']->id);

        $this->assertSame($before, $m['sf2']->fresh()->toArray());
        $this->assertSame($m['sf2']->id, $ex->fresh()->linked_match_id);

        // One exhibition per bout.
        $again = $this->exhibition($event, $p['A'], $p['C'], 'b');
        $this->actingAs($owner)->postJson("/karate/control/{$event->uuid}/exhibitions/{$again->uuid}/link",
            ['match_id' => $m['sf2']->id, 'attach_only' => true])->assertStatus(422);
        $this->assertSame('done', $again->fresh()->status);
    }

    public function test_choosing_a_suggested_bout_by_hand_still_files_the_result(): void
    {
        [$owner, $event, $p, $m] = $this->bracket();
        $ex = $this->exhibition($event, $p['A'], $p['B'], 'b');

        $this->actingAs($owner)->postJson("/karate/control/{$event->uuid}/exhibitions/{$ex->uuid}/link",
            ['match_id' => $m['sf1']->id, 'attach_only' => true])->assertOk();

        $this->assertSame('b', $m['sf1']->fresh()->winner);
    }

    public function test_attaching_needs_an_organiser_and_a_started_day(): void
    {
        [$owner, $event, $p, $m] = $this->bracket(started: false);
        $ex = $this->exhibition($event, $p['A'], $p['C'], 'a');
        $jury = $this->createUser();
        EventOfficial::create(['event_id' => $event->id, 'user_id' => $jury->id, 'role' => EventOfficial::ROLE_JURY, 'assigned_by' => $owner->id]);

        $this->actingAs($jury)->postJson("/karate/control/{$event->uuid}/exhibitions/{$ex->uuid}/link",
            ['match_id' => $m['sf2']->id, 'attach_only' => true])->assertForbidden();
        $this->actingAs($owner)->postJson("/karate/control/{$event->uuid}/exhibitions/{$ex->uuid}/link",
            ['match_id' => $m['sf2']->id, 'attach_only' => true])->assertStatus(422);
        $this->assertSame('done', $ex->fresh()->status);
    }

    public function test_nothing_is_filed_before_the_competition_has_started(): void
    {
        [$owner, $event, $p, $m] = $this->bracket(started: false);
        $ex = $this->exhibition($event, $p['A'], $p['B'], 'a');

        $this->link($owner, $event, $ex, $m['sf1'])->assertStatus(422)->assertJsonPath('can_start_day', true);
        $this->assertNull($m['sf1']->fresh()->winner);
        $this->assertSame('done', $ex->fresh()->status);
    }

    public function test_an_unfinished_exhibition_cannot_be_linked(): void
    {
        [$owner, $event, $p, $m] = $this->bracket();
        $ex = $this->exhibition($event, $p['A'], $p['B'], 'a', ['status' => EventExhibitionBout::STATUS_ABANDONED]);

        $this->link($owner, $event, $ex, $m['sf1'])->assertStatus(422);
        $this->assertNull($m['sf1']->fresh()->winner);
    }

    public function test_only_an_organiser_may_link_and_only_this_events_exhibitions(): void
    {
        [$owner, $event, $p, $m] = $this->bracket();
        $ex = $this->exhibition($event, $p['A'], $p['B'], 'a');

        // A jury member scores the mat but does not manage the event.
        $jury = $this->createUser();
        EventOfficial::create(['event_id' => $event->id, 'user_id' => $jury->id, 'role' => EventOfficial::ROLE_JURY, 'assigned_by' => $owner->id]);
        $this->actingAs($jury)->getJson("/karate/control/{$event->uuid}/exhibitions")->assertForbidden();
        $this->link($jury, $event, $ex, $m['sf1'])->assertForbidden();
        $this->link($this->createUser(), $event, $ex, $m['sf1'])->assertForbidden();

        // Another event's organiser, with that event's address, cannot reach it.
        [$other, $otherEvent] = $this->bracket();
        $this->link($other, $otherEvent, $ex, $m['sf1'])->assertNotFound();

        $this->assertNull($m['sf1']->fresh()->winner);
        $this->assertSame('done', $ex->fresh()->status);
    }

    public function test_the_console_offers_the_tab_to_an_organiser_only(): void
    {
        [$owner, $event] = $this->bracket();

        $this->actingAs($owner)->get("/karate/control/{$event->uuid}?mat=Mat%201")->assertOk()
            ->assertSee('data-btab="exhibitions"', false)
            ->assertSee(json_encode("/karate/control/{$event->uuid}/exhibitions"), false);

        $jury = $this->createUser();
        EventOfficial::create(['event_id' => $event->id, 'user_id' => $jury->id, 'role' => EventOfficial::ROLE_JURY, 'assigned_by' => $owner->id]);
        $this->actingAs($jury)->get("/karate/control/{$event->uuid}?mat=Mat%201")->assertOk()
            ->assertDontSee('data-btab="exhibitions"', false);
    }

    public function test_the_draw_opens_in_link_mode_for_the_organiser_only(): void
    {
        config(['events.unified_page' => true]);
        [$owner, $event, $p, $m] = $this->bracket();
        $ex = $this->exhibition($event, $p['A'], $p['B'], 'a');
        $url = "/me/events/{$event->uuid}/brackets/manage?link_exhibition={$ex->uuid}&mat=Mat%201";

        $page = $this->actingAs($owner)->get($url)->assertOk();
        $page->assertSee('xl-banner', false)->assertSee('EX'.$ex->number, false)
            ->assertSee($ex->uuid, false);

        // Without the parameter, or with someone else's exhibition, the board is as it was.
        $this->actingAs($owner)->get("/me/events/{$event->uuid}/brackets/manage")->assertOk()->assertDontSee('xl-banner', false);
        [$other, $otherEvent, $op] = $this->bracket();
        $foreign = $this->exhibition($otherEvent, $op['A'], $op['B'], 'a');
        $this->actingAs($owner)->get("/me/events/{$event->uuid}/brackets/manage?link_exhibition={$foreign->uuid}")
            ->assertOk()->assertDontSee('xl-banner', false);

        // An official who may score but not manage never gets it.
        $jury = $this->createUser();
        EventOfficial::create(['event_id' => $event->id, 'user_id' => $jury->id, 'role' => EventOfficial::ROLE_JURY, 'assigned_by' => $owner->id]);
        $this->actingAs($jury)->get($url)->assertDontSee('xl-banner', false);
    }

    private function unlink($user, ClubEvent $event, EventExhibitionBout $ex)
    {
        return $this->actingAs($user)->postJson("/karate/control/{$event->uuid}/exhibitions/{$ex->uuid}/unlink");
    }

    public function test_unlinking_a_filed_result_clears_it_and_the_winner_it_advanced(): void
    {
        [$owner, $event, $p, $m] = $this->bracket();
        $ex = $this->exhibition($event, $p['A'], $p['B'], 'a');
        $this->link($owner, $event, $ex, $m['sf1'])->assertOk();
        $this->assertSame($p['A']->id, $m['final']->fresh()->a_competitor_id);

        $this->unlink($owner, $event, $ex->fresh())->assertOk()->assertJsonPath('exhibition.status', 'done');

        $sf1 = $m['sf1']->fresh();
        $this->assertNull($sf1->winner);
        $this->assertNull($m['final']->fresh()->a_competitor_id);
        $this->assertNull($ex->fresh()->linked_match_id);
        // Linkable again.
        $this->link($owner, $event, $ex->fresh(), $m['sf1'])->assertOk();
    }

    public function test_unlinking_a_seated_bout_takes_the_seated_fighters_out_again(): void
    {
        [$owner, $event, $p, $m] = $this->bracket();
        $ex = $this->exhibition($event, $p['A'], $p['C'], 'a');
        $this->link($owner, $event, $ex, $m['final'])->assertOk();

        $this->unlink($owner, $event, $ex->fresh())->assertOk();

        $final = $m['final']->fresh();
        $this->assertSame([null, null, null], [$final->winner, $final->a_competitor_id, $final->b_competitor_id]);
    }

    public function test_unlinking_a_recorded_bout_brings_its_old_score_back(): void
    {
        [$owner, $event, $p, $m] = $this->bracket();
        app(\App\Events\EventTypeRegistry::class)->for($event)->recordOutcome($event, $m['sf1']->id,
            ['winner' => 'a', 'a_score' => '0', 'b_score' => '0', 'win_reason' => 'kiken', 'status' => 'done']);
        $ex = $this->exhibition($event, $p['A'], $p['B'], 'a', ['a_score' => '5', 'b_score' => '2']);
        $this->link($owner, $event, $ex, $m['sf1'])->assertOk();
        $this->assertSame('5', $m['sf1']->fresh()->a_score);

        $this->unlink($owner, $event, $ex->fresh())->assertOk();

        $sf1 = $m['sf1']->fresh();
        $this->assertSame(['a', '0', '0', 'kiken'], [$sf1->winner, $sf1->a_score, $sf1->b_score, $sf1->win_reason]);
    }

    public function test_unlinking_an_attachment_leaves_the_draw_and_returns_the_footage(): void
    {
        [$owner, $event, $p, $m] = $this->bracket();
        $ex = $this->exhibition($event, $p['A'], $p['C'], 'a', ['number' => 5]);
        $camera = \App\Events\Models\EventCamera::create([
            'event_id' => $event->id, 'court' => 'Mat 2', 'angle' => 1, 'token_hash' => hash('sha256', 'cam2'),
        ]);
        $clip = \App\Events\Models\EventCameraClip::create([
            'camera_id' => $camera->id, 'event_id' => $event->id, 'court' => 'Mat 2',
            'local_ref' => 'mat-mat-2_angle-1_bout-ex5_20261017-160954.mp4',
        ]);
        $this->actingAs($owner)->postJson("/karate/control/{$event->uuid}/exhibitions/{$ex->uuid}/link",
            ['match_id' => $m['sf2']->id, 'attach_only' => true])->assertOk();
        $this->assertSame($m['sf2']->id, $clip->fresh()->match_id);
        $before = $m['sf2']->fresh()->toArray();

        $this->unlink($owner, $event, $ex->fresh())->assertOk();

        $this->assertSame($before, $m['sf2']->fresh()->toArray());
        $this->assertNull($clip->fresh()->match_id);
        $this->assertSame('done', $ex->fresh()->status);
    }

    public function test_unlink_is_refused_once_the_winner_has_fought_on(): void
    {
        [$owner, $event, $p, $m] = $this->bracket();
        $ex = $this->exhibition($event, $p['A'], $p['B'], 'a');
        $this->link($owner, $event, $ex, $m['sf1'])->assertOk();
        $type = app(\App\Events\EventTypeRegistry::class)->for($event);
        $type->recordOutcome($event, $m['sf2']->id, ['winner' => 'a', 'status' => 'done']);
        $type->recordOutcome($event, $m['final']->id, ['winner' => 'a', 'status' => 'done']);

        $this->unlink($owner, $event, $ex->fresh())->assertStatus(422);

        $this->assertSame('a', $m['sf1']->fresh()->winner);
        $this->assertSame('a', $m['final']->fresh()->winner);
        $this->assertSame('linked', $ex->fresh()->status);
    }

    public function test_only_an_organiser_may_unlink(): void
    {
        [$owner, $event, $p, $m] = $this->bracket();
        $ex = $this->exhibition($event, $p['A'], $p['B'], 'a');
        $this->link($owner, $event, $ex, $m['sf1'])->assertOk();
        $jury = $this->createUser();
        EventOfficial::create(['event_id' => $event->id, 'user_id' => $jury->id, 'role' => EventOfficial::ROLE_JURY, 'assigned_by' => $owner->id]);

        $this->unlink($jury, $event, $ex->fresh())->assertForbidden();
        $this->unlink($this->createUser(), $event, $ex->fresh())->assertForbidden();
        $this->assertSame('linked', $ex->fresh()->status);
        $this->assertSame('a', $m['sf1']->fresh()->winner);
    }

    public function test_switched_off_the_tab_is_not_served(): void
    {
        [$owner, $event, $p, $m] = $this->bracket();
        $ex = $this->exhibition($event, $p['A'], $p['B'], 'a');
        config(['events.exhibitions' => false]);

        $this->actingAs($owner)->getJson("/karate/control/{$event->uuid}/exhibitions")->assertForbidden();
        $this->link($owner, $event, $ex, $m['sf1'])->assertForbidden();
    }
}
