<?php

namespace Tests\Feature\Events;

use App\Clubs\Models\Tenant;
use App\Events\Models\ClubEvent;
use App\Events\Models\EventFeatureRequest;
use App\Events\Support\EventFeaturing;
use App\Mcp\Tools\DecideEventFeatureRequestTool;
use App\Mcp\Tools\GetEventFeaturingTool;
use App\Mcp\Tools\ListEventFeatureRequestsTool;
use App\Members\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Mcp\Request;
use Tests\TestCase;

/**
 * Paid featuring on /me/events (App\Events\Support\EventFeaturing).
 *
 * What this protects: only an organiser may ask, only with a real receipt and
 * while a fee is set; only a super-admin decides; and only an APPROVED event
 * takes the spotlight — with none approved, there is no spotlight at all.
 */
class EventFeaturingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        EventFeaturing::setFee(25, 'BHD');
    }

    private function clubFor(User $user): Tenant
    {
        $club = $this->createClub($user, ['country' => 'BH']);
        $user->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);

        return $club;
    }

    private function event(Tenant $club, User $organiser, array $attrs = []): ClubEvent
    {
        return ClubEvent::create(array_merge([
            'tenant_id' => $club->id, 'created_by' => $organiser->id,
            'title' => 'Spring Open', 'event_type' => 'championship', 'sport' => 'taekwondo',
            'scope' => 'internal', 'status' => 'active', 'is_archived' => false,
            'date' => now()->addWeeks(2)->toDateString(),
            'start_time' => '09:00', 'end_time' => '17:00',
        ], $attrs));
    }

    private function receipt(): UploadedFile
    {
        return UploadedFile::fake()->image('receipt.png', 300, 400);
    }

    private function admin(): User
    {
        $admin = $this->createUser();
        $this->makeSuperAdmin($admin);

        return $admin->fresh();
    }

    private function mcp(string $tool, User $as, array $args): array
    {
        $this->actingAs($as);
        $text = (string) (new $tool)->handle(new Request($args))->content();
        $d = json_decode($text, true);

        return is_array($d) ? $d : ['error' => $text];
    }

    /* ===================== Asking ===================== */

    public function test_the_organiser_sends_a_receipt_and_the_request_waits(): void
    {
        $this->admin();
        $organiser = $this->createUser();
        $event = $this->event($this->clubFor($organiser), $organiser);

        $this->actingAs($organiser->fresh())
            ->post(route('me.events.feature.store', $event->uuid), ['proof' => $this->receipt()], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('feature.status', 'pending')
            ->assertJsonPath('feature.can_request', false);

        $r = EventFeatureRequest::firstOrFail();
        $this->assertSame($event->id, $r->club_event_id);
        $this->assertSame('25.000', (string) $r->amount);
        $this->assertStringStartsWith('events/', $r->proof_path);
        $this->assertStringNotContainsString('receipt', $r->proof_path);   // server-generated name
        Storage::disk('local')->assertExists($r->proof_path);
    }

    public function test_somebody_who_does_not_run_the_event_cannot_ask_or_read(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->event($club, $organiser);
        $member = $this->createUser();
        $member->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);

        $this->actingAs($member->fresh())
            ->post(route('me.events.feature.store', $event->uuid), ['proof' => $this->receipt()], ['Accept' => 'application/json'])
            ->assertForbidden();
        $this->actingAs($member->fresh())->getJson(route('me.events.feature', $event->uuid))->assertForbidden();
        $this->assertSame(0, EventFeatureRequest::count());
    }

    public function test_a_file_that_is_not_a_receipt_is_refused(): void
    {
        $organiser = $this->createUser();
        $event = $this->event($this->clubFor($organiser), $organiser);

        $fake = UploadedFile::fake()->createWithContent('receipt.png', '<?php echo "x";');
        $this->actingAs($organiser->fresh())
            ->post(route('me.events.feature.store', $event->uuid), ['proof' => $fake], ['Accept' => 'application/json'])
            ->assertStatus(422);
        $this->assertSame(0, EventFeatureRequest::count());
    }

    public function test_no_fee_means_featuring_is_closed(): void
    {
        EventFeaturing::setFee(null, 'BHD');
        $organiser = $this->createUser();
        $event = $this->event($this->clubFor($organiser), $organiser);

        $this->actingAs($organiser->fresh())
            ->post(route('me.events.feature.store', $event->uuid), ['proof' => $this->receipt()], ['Accept' => 'application/json'])
            ->assertStatus(422);
        $this->assertFalse(EventFeaturing::state($event)['can_request']);
    }

    public function test_a_second_request_while_one_waits_is_refused(): void
    {
        $organiser = $this->createUser();
        $event = $this->event($this->clubFor($organiser), $organiser);
        $this->actingAs($organiser->fresh());

        $this->post(route('me.events.feature.store', $event->uuid), ['proof' => $this->receipt()], ['Accept' => 'application/json'])->assertOk();
        $this->post(route('me.events.feature.store', $event->uuid), ['proof' => $this->receipt()], ['Accept' => 'application/json'])->assertStatus(422);
        $this->assertSame(1, EventFeatureRequest::count());
    }

    /* ===================== Deciding ===================== */

    public function test_only_a_super_admin_decides_and_the_spotlight_follows(): void
    {
        $admin = $this->admin();
        $organiser = $this->createUser();
        $event = $this->event($this->clubFor($organiser), $organiser, ['title' => 'Paid Spotlight Cup']);
        $this->actingAs($organiser->fresh())
            ->post(route('me.events.feature.store', $event->uuid), ['proof' => $this->receipt()], ['Accept' => 'application/json'])->assertOk();
        $r = EventFeatureRequest::firstOrFail();

        // Before approval: listed, but no spotlight.
        $page = $this->actingAs($organiser->fresh())->get(route('me.events'))->assertOk()->getContent();
        $this->assertStringNotContainsString(__('personal.personal_events_featured'), $page);

        // The organiser cannot approve their own request.
        $this->actingAs($organiser->fresh())
            ->postJson(route('admin.platform.featured-events.decide', [$r->uuid, 'approve']))->assertForbidden();
        $this->assertSame('pending', $r->fresh()->status);

        $this->actingAs($admin)
            ->postJson(route('admin.platform.featured-events.decide', [$r->uuid, 'approve']))
            ->assertOk()->assertJsonPath('request.status', 'approved');

        $page = $this->actingAs($organiser->fresh())->get(route('me.events'))->assertOk()->getContent();
        $this->assertStringContainsString(__('personal.personal_events_featured'), $page);

        // Revoking needs a reason, and takes the spotlight away.
        $this->actingAs($admin)->postJson(route('admin.platform.featured-events.decide', [$r->uuid, 'revoke']))->assertStatus(422);
        $this->actingAs($admin)
            ->postJson(route('admin.platform.featured-events.decide', [$r->uuid, 'revoke']), ['reason' => 'Refund issued'])
            ->assertOk()->assertJsonPath('request.status', 'revoked');
        $page = $this->actingAs($organiser->fresh())->get(route('me.events'))->assertOk()->getContent();
        $this->assertStringNotContainsString(__('personal.personal_events_featured'), $page);
    }

    public function test_the_proof_is_served_only_to_a_super_admin(): void
    {
        $admin = $this->admin();
        $organiser = $this->createUser();
        $event = $this->event($this->clubFor($organiser), $organiser);
        $this->actingAs($organiser->fresh())
            ->post(route('me.events.feature.store', $event->uuid), ['proof' => $this->receipt()], ['Accept' => 'application/json'])->assertOk();
        $r = EventFeatureRequest::firstOrFail();

        $this->actingAs($organiser->fresh())->getJson(route('admin.platform.featured-events.proof', $r->uuid))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.platform.featured-events.proof', $r->uuid))->assertOk();
        $this->actingAs($admin)->get(route('admin.platform.featured-events'))->assertOk()->assertSee('Spring Open');
    }

    public function test_the_admin_sets_the_fee(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)
            ->putJson(route('admin.platform.featured-events.fee'), ['amount' => 40, 'currency' => 'bhd'])
            ->assertOk()->assertJsonPath('fee.amount', 40);
        $this->assertSame(40.0, EventFeaturing::fee()['amount']);

        $member = $this->createUser();
        $this->actingAs($member)->putJson(route('admin.platform.featured-events.fee'), ['amount' => 1, 'currency' => 'BHD'])->assertForbidden();
        $this->assertSame(40.0, EventFeaturing::fee()['amount']);
    }

    /* ===================== MCP ===================== */

    public function test_mcp_mirrors_the_same_rules(): void
    {
        $admin = $this->admin();
        $organiser = $this->createUser();
        $event = $this->event($this->clubFor($organiser), $organiser);
        $this->actingAs($organiser->fresh())
            ->post(route('me.events.feature.store', $event->uuid), ['proof' => $this->receipt()], ['Accept' => 'application/json'])->assertOk();
        $r = EventFeatureRequest::firstOrFail();

        // Organiser: may read their event's state, may not list or decide.
        $this->assertSame('pending', $this->mcp(GetEventFeaturingTool::class, $organiser->fresh(), ['event' => $event->uuid])['featuring']['status']);
        $this->assertArrayHasKey('error', $this->mcp(ListEventFeatureRequestsTool::class, $organiser->fresh(), []));
        $this->assertArrayHasKey('error', $this->mcp(DecideEventFeatureRequestTool::class, $organiser->fresh(), ['uuid' => $r->uuid, 'decision' => 'approve']));
        $this->assertSame('pending', $r->fresh()->status);

        // A stranger cannot read somebody else's event.
        $this->assertArrayHasKey('error', $this->mcp(GetEventFeaturingTool::class, $this->createUser(), ['event' => $event->uuid]));

        // Super-admin: lists and approves.
        $this->assertCount(1, $this->mcp(ListEventFeatureRequestsTool::class, $admin, ['status' => 'pending'])['requests']);
        $this->assertSame('approved', $this->mcp(DecideEventFeatureRequestTool::class, $admin, ['uuid' => $r->uuid, 'decision' => 'approve'])['request']['status']);
    }
}
