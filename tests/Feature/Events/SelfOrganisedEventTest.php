<?php

namespace Tests\Feature\Events;

use App\Events\Models\ClubEvent;
use App\Events\Support\EventAccess;
use App\Members\Models\User;
use App\Parties\Relations;
use App\Parties\Roles;
use Tests\TestCase;

/**
 * Self-organised events (owner, 2026-10-03): a person hosts an event with no
 * club — "Myself" in the editor, their own payment details and currency,
 * never "this club only", announced to nobody. Behind events.person_hosts.
 */
class SelfOrganisedEventTest extends TestCase
{
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'tenant_id' => 'me',
            'title' => 'Park Sparring Day',
            'event_type' => 'championship',
            'sport' => 'taekwondo',
            'scope' => 'internal',
            'date' => now()->addMonth()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '17:00',
            'participant_free' => false,
            'participant_fee_amount' => 5,
            'spectator_enabled' => false,
            'fee_currency' => 'SAR',
            'host_payment' => ['bank_name' => 'Al Rajhi', 'account_name' => 'Sara A', 'iban' => 'SA0380000000608010167519', 'evil' => 'x'],
        ], $overrides);
    }

    private function create(User $me): ClubEvent
    {
        $this->actingAs($me)->postJson(route('me.events.store'), $this->payload())->assertOk();

        return ClubEvent::where('title', 'Park Sparring Day')->firstOrFail();
    }

    public function test_switched_off_a_person_cannot_host(): void
    {
        config(['events.person_hosts' => false]);

        $this->actingAs($this->createUser())->postJson(route('me.events.store'), $this->payload())->assertStatus(422);
        $this->assertSame(0, ClubEvent::count());
    }

    public function test_someone_with_no_club_can_open_the_editor_and_is_offered_myself(): void
    {
        config(['events.person_hosts' => true]);

        $this->actingAs($this->createUser())->get(route('me.events.create'))
            ->assertOk()
            ->assertSee(e(__('personal.personal_event_create_host_money_title')), false)
            ->assertDontSee(e(__('personal.personal_event_create_join_club_first')), false);
    }

    public function test_a_person_hosts_their_own_event_with_their_own_money_details(): void
    {
        config(['events.person_hosts' => true]);
        $me = $this->createUser();
        $event = $this->create($me);

        $this->assertNull($event->tenant_id);
        $this->assertSame(['person', $me->id], [$event->host_type, (int) $event->host_id]);
        $this->assertSame('inter_club', $event->scope, 'no club, so never "this club only"');
        $this->assertSame('SAR', $event->fee_currency);
        $this->assertSame(['bank_name' => 'Al Rajhi', 'account_name' => 'Sara A', 'iban' => 'SA0380000000608010167519'], $event->host_payment, 'only known fields are kept');

        $this->assertSame('person', $event->host()->kind);
        $this->assertSame($me->full_name ?: $me->name, $event->host()->name);

        $bank = app(\App\Events\Support\EntryGates::class)->bankFor($event);
        $this->assertSame('SA0380000000608010167519', $bank['iban']);
    }

    public function test_only_the_person_runs_it_and_another_member_can_read_it(): void
    {
        config(['events.person_hosts' => true]);
        $me = $this->createUser();
        $event = $this->create($me);
        $other = $this->createUser();

        $this->assertTrue(app(EventAccess::class)->canManage($event, $me));
        $this->assertFalse((new EventAccess)->canManage($event, $other));

        $this->actingAs($me)->get("/me/events/{$event->uuid}")->assertOk();
        $this->actingAs($me)->get("/me/events/{$event->uuid}/manage")->assertOk();
        $this->actingAs($other)->get("/me/events/{$event->uuid}")->assertOk();
    }

    public function test_editing_keeps_the_host_and_updates_the_payment_details(): void
    {
        config(['events.person_hosts' => true]);
        $me = $this->createUser();
        $event = $this->create($me);

        $this->actingAs($me)->get(route('me.events.edit', $event->uuid))->assertOk();

        $this->actingAs($me)->putJson(route('me.events.update', $event->uuid), $this->payload([
            'title' => 'Park Sparring Day II',
            'host_payment' => ['bank_name' => 'NBB', 'iban' => 'BH67BMAG00001299123456'],
        ]))->assertOk();

        $event->refresh();
        $this->assertSame(['person', $me->id], [$event->host_type, (int) $event->host_id]);
        $this->assertNull($event->tenant_id);
        $this->assertSame('BH67BMAG00001299123456', $event->host_payment['iban']);
    }

    public function test_the_relationships_record_the_person_as_host(): void
    {
        config(['events.person_hosts' => true, 'parties.enabled' => true]);
        $me = $this->createUser();
        $event = $this->create($me);

        $this->assertTrue(app(Relations::class)->holds($me, Roles::HOST, $event));
        $this->assertTrue(app(\App\Parties\Authority::class)->manageEvent($me, $event));
    }
}
