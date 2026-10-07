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
        config(['events.tablet_draw' => true]);
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
}
