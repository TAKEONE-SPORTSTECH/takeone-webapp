<?php

namespace Tests\Feature\Federations;

use App\Events\Models\ClubEvent;
use App\Federations\Models\Federation;
use App\Federations\Models\FederationEvent;
use App\Members\Models\User;
use Tests\TestCase;

/**
 * One event editor for federations too (owner, 2026-10-03): a federation's
 * manager picks their federation as the organiser in the shared editor; the
 * event is created through the federation's OWN save, so its dashboard row
 * (and through it invitations, draws, statistics) exists, and the platform
 * event follows. Behind events.federation_hosts.
 */
class FederationEditorHostTest extends TestCase
{
    private User $owner;

    private Federation $fed;

    protected function setUp(): void
    {
        parent::setUp();
        config(['federations.enabled' => true, 'federations.platform_events' => true, 'events.federation_hosts' => true]);
        $this->owner = $this->createUser();
        $this->fed = (new Federation)->forceFill([
            'owner_user_id' => $this->owner->id, 'name' => 'Test Karate Federation', 'short_name' => 'TKF',
            'country' => 'BH', 'currency' => 'BHD', 'sport' => 'Karate', 'color' => '#1e2c4f',
            'status' => Federation::STATUS_APPROVED, 'approved_at' => now(),
        ]);
        $this->fed->save();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'tenant_id' => 'federation:'.$this->fed->uuid,
            'title' => 'National Karate Cup',
            'event_type' => 'championship',
            'sport' => 'karate',
            'scope' => 'nationwide',
            'date' => now()->addMonth()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '17:00',
            'location' => 'Isa Sports City',
            'participant_free' => false,
            'spectator_enabled' => false,
            'fee_options' => [['role' => 'participant', 'label' => 'Individual Kumite', 'amount' => 15]],
        ], $overrides);
    }

    public function test_the_editor_offers_the_federation_to_its_manager_only(): void
    {
        $this->actingAs($this->owner)->get(route('me.events.create', ['host' => 'federation:'.$this->fed->uuid]))
            ->assertOk()->assertViewHas('preselectClub', 'federation:'.$this->fed->uuid);

        $this->actingAs($this->createUser())->get(route('me.events.create', ['host' => 'federation:'.$this->fed->uuid]))
            ->assertOk()->assertViewHas('preselectClub', null);
    }

    public function test_saving_creates_the_dashboard_row_and_the_platform_event(): void
    {
        $res = $this->actingAs($this->owner)->postJson(route('me.events.store'), $this->payload())->assertOk();

        $fe = FederationEvent::where('name', 'National Karate Cup')->firstOrFail();
        $this->assertSame($this->fed->id, $fe->federation_id);
        $this->assertSame('Karate', $fe->sport);
        $this->assertSame('Planning', $fe->status);
        $this->assertSame(['Individual Kumite'], $fe->details['types']);

        $event = ClubEvent::findOrFail($fe->club_event_id);
        $this->assertSame(['federation', $this->fed->id], [$event->host_type, (int) $event->host_id]);
        $this->assertNull($event->tenant_id);
        $this->assertSame('karate', $event->sport);
        $res->assertJsonPath('redirect', route('me.events.show', $event->uuid));
    }

    public function test_someone_who_does_not_run_the_federation_is_refused(): void
    {
        $this->actingAs($this->createUser())->postJson(route('me.events.store'), $this->payload())->assertStatus(422);
        $this->assertSame(0, FederationEvent::count());
    }

    public function test_switched_off_the_choice_does_not_exist(): void
    {
        config(['events.federation_hosts' => false]);

        $this->actingAs($this->owner)->postJson(route('me.events.store'), $this->payload())->assertStatus(422);
        $this->assertSame(0, FederationEvent::count());
    }

    public function test_the_dashboard_hands_its_plan_button_the_editor_address_only_when_switched_on(): void
    {
        $page = $this->actingAs($this->owner)->get('/federation')->assertOk()->getContent();
        $this->assertStringContainsString(str_replace('/', '\\/', route('me.events.create', ['host' => 'federation:'.$this->fed->uuid])), $page);
        $this->assertStringContainsString('boot.urls.eventCreate', $page);

        config(['events.federation_hosts' => false]);
        $page = $this->actingAs($this->owner)->get('/federation')->assertOk()->getContent();
        $this->assertStringContainsString('"eventCreate":null', $page);
    }
}
