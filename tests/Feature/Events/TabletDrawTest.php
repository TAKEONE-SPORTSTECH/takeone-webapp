<?php

namespace Tests\Feature\Events;

use App\Events\Models\EventDrawPin;
use App\Scoreboard\Sports\Taekwondo\HallScreen\CourtDisplayDevice;
use Tests\Feature\Contracts\ContractTestCase;

/**
 * The draw on a scoring tablet (App\Events\Support\TabletDraw) and the
 * organiser's PIN that lets it edit (App\Events\Controllers\DrawPinController).
 *
 * The device rules are the ones CLAUDE.md demands of every unattended surface:
 * a page never 404s, a JSON door 404s a dead identity, one answer for every
 * failure. The edit rules are this feature's own: reading rides on the
 * pairing, editing needs the PIN and runs as the PIN's owner.
 */
class TabletDrawTest extends ContractTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // The tests below are the PIN mode's; the no-PIN default has its own
        // at the end of the file.
        config(['events.tablet_draw' => true, 'events.tablet_draw_pin' => true]);
    }

    /** @return array{0: \App\Events\Models\ClubEvent, 1: string, 2: \App\Members\Models\User, 3: array} */
    private function pairedTablet(): array
    {
        $organiser = $this->createUser(['full_name' => 'Master Kim']);
        $club = $this->clubFor($organiser);
        $event = $this->championship($club, $organiser);
        $draw = $this->drawnDivision($event, $club);

        ['device' => $device, 'token' => $token] = CourtDisplayDevice::issue($event, 'Mat 1', $organiser->id);
        $device->forceFill(['surface' => 'control'])->save();

        return [$event, $token, $organiser->fresh(), $draw];
    }

    public function test_a_dead_token_page_goes_to_the_pairing_room_and_its_data_door_404s(): void
    {
        $dead = str_repeat('a', 40);

        $this->get("/tablet/{$dead}/draw")->assertRedirect(route('screen.new'));
        $this->getJson("/tablet/{$dead}/draw/data")->assertNotFound();
        $this->postJson("/tablet/{$dead}/draw/unlock", ['pin' => '1234'])->assertNotFound();
    }

    public function test_a_paired_tablet_reads_the_draw_but_cannot_edit_it(): void
    {
        [, $token] = $this->pairedTablet();

        $this->get("/tablet/{$token}/draw")->assertOk()->assertSee(__('events::events.tablet_unlock'));

        $data = $this->getJson("/tablet/{$token}/draw/data")->assertOk();
        $this->assertNotEmpty($data->json('divisions'));
        $this->assertFalse($data->json('can_arrange'));
        $this->assertFalse($data->json('can_manage'));

        $this->putJson("/tablet/{$token}/draw/arrange", ['category_id' => 1])->assertForbidden();
        $this->getJson("/tablet/{$token}/draw/candidates?category_id=1")->assertForbidden();
    }

    public function test_an_editing_tablet_offers_two_boards_only_while_the_flag_is_on(): void
    {
        config(['events.tablet_draw_pin' => false, 'events.draw_transfer' => true]);
        [, $token] = $this->pairedTablet();

        $this->get("/tablet/{$token}/draw")->assertOk()
            ->assertSee(__('events::events.transfer_split'))
            ->assertSee('drawSplit(', false)
            // The tablet's own door, never the member route (JSON-escaped in the page).
            ->assertSee('draw\\\\\\/transfer', false);

        config(['events.draw_transfer' => false]);

        $this->get("/tablet/{$token}/draw")->assertOk()
            ->assertDontSee(__('events::events.transfer_split'))
            ->assertDontSee('drawSplit(', false);
    }

    public function test_the_transfer_door_follows_the_same_device_rules(): void
    {
        config(['events.draw_transfer' => true]);
        [, $token] = $this->pairedTablet();
        $body = ['from_category_id' => 1, 'competitor_id' => 1, 'to_category_id' => 2, 'to' => ['type' => 'bench'], 'mode' => 'move'];

        // Locked tablet: no edits. Dead token: 404, like every JSON door.
        $this->putJson("/tablet/{$token}/draw/transfer", $body)->assertForbidden();
        $this->putJson('/tablet/'.str_repeat('a', 40).'/draw/transfer', $body)->assertNotFound();
    }

    public function test_the_right_pin_unlocks_editing_and_locking_ends_it(): void
    {
        [$event, $token, $organiser, $draw] = $this->pairedTablet();

        $this->postJson("/tablet/{$token}/draw/unlock", ['pin' => '2468'])->assertStatus(422);   // none set yet

        EventDrawPin::setFor($event, $organiser, '2468');

        $this->postJson("/tablet/{$token}/draw/unlock", ['pin' => '1111'])->assertStatus(422);
        $this->postJson("/tablet/{$token}/draw/unlock", ['pin' => '2468'])->assertOk()->assertJson(['success' => true]);

        $this->assertTrue($this->getJson("/tablet/{$token}/draw/data")->json('can_arrange'));
        $this->getJson("/tablet/{$token}/draw/candidates?category_id={$draw[0]->id}")->assertOk();

        $this->postJson("/tablet/{$token}/draw/lock")->assertOk();
        $this->getJson("/tablet/{$token}/draw/candidates?category_id={$draw[0]->id}")->assertForbidden();
    }

    public function test_five_wrong_pins_lock_it_even_against_the_right_one(): void
    {
        [$event, $token, $organiser] = $this->pairedTablet();
        EventDrawPin::setFor($event, $organiser, '2468');

        for ($i = 0; $i < EventDrawPin::MAX_ATTEMPTS; $i++) {
            $this->postJson("/tablet/{$token}/draw/unlock", ['pin' => '0000']);
        }

        $this->postJson("/tablet/{$token}/draw/unlock", ['pin' => '2468'])->assertStatus(429);
        $this->assertFalse($this->getJson("/tablet/{$token}/draw/data")->json('can_arrange'));
    }

    public function test_a_new_pin_ends_the_grant_the_old_one_opened(): void
    {
        [$event, $token, $organiser] = $this->pairedTablet();
        EventDrawPin::setFor($event, $organiser, '2468');
        $this->postJson("/tablet/{$token}/draw/unlock", ['pin' => '2468'])->assertOk();

        EventDrawPin::setFor($event, $organiser, '1357');

        $this->assertFalse($this->getJson("/tablet/{$token}/draw/data")->json('can_arrange'));
    }

    public function test_only_an_organiser_may_set_the_pin_and_it_is_never_read_back(): void
    {
        [$event, , $organiser] = $this->pairedTablet();
        $stranger = $this->createUser(['full_name' => 'Somebody Else']);

        $this->actingAs($stranger)->putJson("/me/events/{$event->uuid}/draw-pin", ['pin' => '2468', 'pin_confirmation' => '2468'])
            ->assertForbidden();

        $this->actingAs($organiser)->putJson("/me/events/{$event->uuid}/draw-pin", ['pin' => '2468', 'pin_confirmation' => '1111'])
            ->assertStatus(422);
        $this->actingAs($organiser)->putJson("/me/events/{$event->uuid}/draw-pin", ['pin' => '12ab', 'pin_confirmation' => '12ab'])
            ->assertStatus(422);

        $res = $this->actingAs($organiser)->putJson("/me/events/{$event->uuid}/draw-pin", ['pin' => '2468', 'pin_confirmation' => '2468'])
            ->assertOk()->assertJsonPath('pin.set', true);
        $this->assertStringNotContainsString('2468', $res->getContent());
        $this->assertStringNotContainsString('pin_hash', $res->getContent());

        $this->actingAs($organiser)->deleteJson("/me/events/{$event->uuid}/draw-pin")->assertOk()->assertJsonPath('pin.set', false);
        $this->assertSame(0, EventDrawPin::where('event_id', $event->id)->count());
    }

    public function test_with_the_flag_off_the_page_returns_to_the_console_and_the_doors_404(): void
    {
        [$event, $token] = $this->pairedTablet();
        config(['events.tablet_draw' => false]);

        $this->get("/tablet/{$token}/draw")->assertRedirect(route('taekwondo-scoreboard.token-control', $token));
        $this->getJson("/tablet/{$token}/draw/data")->assertNotFound();
        $this->actingAs($event->creator ?? $this->createUser())->getJson("/me/events/{$event->uuid}/draw-pin")->assertNotFound();
    }

    public function test_under_a_pin_the_tablet_decides_a_bout_and_corrects_only_a_name(): void
    {
        [$event, $token, $organiser, $draw] = $this->pairedTablet();
        EventDrawPin::setFor($event, $organiser, '2468');

        $bout = \App\Events\Models\EventMatch::where('category_id', $draw[0]->id)
            ->whereNotNull('a_competitor_id')->whereNotNull('b_competitor_id')->first();
        $this->assertNotNull($bout);

        // Locked: neither door opens.
        $this->postJson("/tablet/{$token}/draw/outcomes/{$bout->id}", ['winner' => 'a'])->assertForbidden();

        $this->postJson("/tablet/{$token}/draw/unlock", ['pin' => '2468'])->assertOk();
        $this->assertTrue($this->getJson("/tablet/{$token}/draw/data")->json('can_manage'));

        $this->postJson("/tablet/{$token}/draw/outcomes/{$bout->id}", ['winner' => 'a'])->assertOk();
        $this->assertSame('a', $bout->fresh()->winner);

        $entry = \App\Events\Models\ClubEventRegistration::find($bout->a_competitor_id);
        $weight = $entry->weight;
        $this->putJson("/tablet/{$token}/draw/entrants/{$entry->id}", ['name' => 'Corrected Name', 'weight' => 99])->assertOk();
        $this->assertSame('Corrected Name', $entry->user->fresh()->full_name);
        $this->assertEquals($weight, $entry->fresh()->weight);

        // An entry from somewhere else is not this tablet's to touch.
        $this->putJson("/tablet/{$token}/draw/entrants/999999", ['name' => 'Nope'])->assertNotFound();
    }

    public function test_without_a_pin_the_paired_tablet_edits_as_the_organiser_who_paired_it(): void
    {
        config(['events.tablet_draw_pin' => false]);
        [, $token, $organiser] = $this->pairedTablet();

        // No pad, no lock: the board opens ready to edit.
        $this->get("/tablet/{$token}/draw")->assertOk()
            ->assertDontSee(__('events::events.tablet_unlock'))
            ->assertSee($organiser->full_name)
            // Done closes the board to edits; Edit opens it again.
            ->assertSee(__('events::events.tablet_done'))
            ->assertSee('resume()', false)
            // It opens in VIEW mode: Edit unlocks, Done locks it back.
            ->assertSee('viewOnly: ! false', false);

        $data = $this->getJson("/tablet/{$token}/draw/data")->assertOk();
        $this->assertTrue($data->json('can_arrange'));
        $this->assertTrue($data->json('can_manage'));
    }

    public function test_without_a_pin_a_tablet_whose_pairer_lost_the_event_reads_only(): void
    {
        config(['events.tablet_draw_pin' => false]);
        [$event, $token] = $this->pairedTablet();

        // The person who paired it no longer runs this event: hand it to someone else.
        $other = $this->createUser(['full_name' => 'New Owner']);
        $event->forceFill(['created_by' => $other->id, 'tenant_id' => $this->clubFor($other)->id])->save();

        $this->putJson("/tablet/{$token}/draw/arrange", ['category_id' => 1])->assertStatus(404);
    }

    public function test_a_wrong_winner_is_taken_back_and_everything_after_it_with_it(): void
    {
        config(['events.tablet_draw_pin' => false]);
        [, $token, , $draw] = $this->pairedTablet();
        $bouts = \App\Events\Models\EventMatch::where('category_id', $draw[0]->id)->orderBy('slot')->get();
        $semis = $bouts->filter(fn ($m) => $m->a_competitor_id && $m->b_competitor_id)->values();
        $final = $bouts->first(fn ($m) => $m->round === 'Final');
        $this->assertCount(2, $semis);

        // Three mistakes in a row: both semi-finals and the final.
        $this->postJson("/tablet/{$token}/draw/outcomes/{$semis[0]->id}", ['winner' => 'a'])->assertOk();
        $this->postJson("/tablet/{$token}/draw/outcomes/{$semis[1]->id}", ['winner' => 'a'])->assertOk();
        $this->postJson("/tablet/{$token}/draw/outcomes/{$final->id}", ['winner' => 'a'])->assertOk();
        $this->assertSame('a', $final->fresh()->winner);

        // Un-win the first semi-final (the same tap again sends an empty winner):
        // its winner leaves the final, and the final's result goes with it.
        $this->postJson("/tablet/{$token}/draw/outcomes/{$semis[0]->id}", ['winner' => ''])->assertOk();
        $this->assertNull($semis[0]->fresh()->winner);
        $this->assertNull($final->fresh()->winner);

        // The other semi-final keeps its result until it is taken back too.
        $this->assertSame('a', $semis[1]->fresh()->winner);
        $this->postJson("/tablet/{$token}/draw/outcomes/{$semis[1]->id}", ['winner' => ''])->assertOk();
        $this->assertNull($semis[1]->fresh()->winner);
    }
}
