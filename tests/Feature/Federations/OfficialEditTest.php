<?php

namespace Tests\Feature\Federations;

use App\Events\Models\EventOfficial;
use App\Federations\Models\Federation;
use App\Federations\Models\FederationEvent;
use App\Federations\Services\FederationPlatformEvent;
use App\Members\Models\User;
use Tests\TestCase;

/**
 * The Officials tab's edit window (FederationOfficialEditor,
 * federations.api.officials.one / .person, federations.official-edit).
 *
 * What this protects: the federation's own managers read and correct the
 * person behind an appointment on their own events — own account or not
 * (owner, 2026-10-15) — and nobody else can, whatever id they try.
 */
class OfficialEditTest extends TestCase
{
    private User $owner;

    private Federation $fed;

    protected function setUp(): void
    {
        parent::setUp();
        config(['federations.enabled' => true, 'federations.platform_events' => true]);
        $this->owner = $this->createUser();
        $this->fed = (new Federation)->forceFill([
            'owner_user_id' => $this->owner->id, 'name' => 'Test Karate Federation', 'short_name' => 'TKF',
            'country' => 'BH', 'currency' => 'BHD', 'sport' => 'Karate', 'color' => '#1e2c4f',
            'status' => Federation::STATUS_APPROVED, 'approved_at' => now(),
        ]);
        $this->fed->save();
    }

    /** @return array{0: \App\Events\Models\ClubEvent, 1: EventOfficial} */
    private function appointed(bool $claimed = false): array
    {
        $fe = (new FederationEvent)->forceFill([
            'federation_id' => $this->fed->id, 'created_by' => $this->owner->id,
            'name' => 'National Karate Cup', 'sport' => 'Karate', 'status' => 'Registration open',
            'starts_on' => now()->addMonth()->format('Y-m-d').'T09:00',
            'details' => ['venue' => 'Isa Sports City', 'types' => ['Individual Kumite'], 'free' => false,
                'fees' => [['label' => 'Individual Kumite', 'amount' => '15', 'type' => 'Individual Kumite']]],
        ]);
        $fe->save();
        $ev = app(FederationPlatformEvent::class)->sync($fe->fresh('federation'));
        $ref = $this->createUser(['full_name' => 'Sara Ali', 'gender' => 'Female', 'nationality' => 'BH', 'is_unclaimed' => ! $claimed]);
        $o = EventOfficial::forceCreate(['event_id' => $ev->id, 'user_id' => $ref->id, 'role' => 'jury', 'compensation' => 'volunteer']);

        return [$ev, $o];
    }

    public function test_a_manager_reads_and_corrects_the_person(): void
    {
        [$ev, $o] = $this->appointed(claimed: true);

        $this->actingAs($this->owner)->getJson(route('federations.api.officials.one', ['event' => $ev->uuid, 'official' => $o->id]))
            ->assertOk()->assertJsonPath('official.name', 'Sara Ali')->assertJsonPath('official.gender', 'Female')
            ->assertJsonPath('official.nat', 'BH')->assertJsonPath('official.claimed', true);

        $this->actingAs($this->owner)->postJson(route('federations.api.officials.person'),
            ['event' => $ev->uuid, 'official' => $o->id, 'name' => 'Sara Ali Hasan', 'gender' => 'Female', 'nat' => 'KW'])
            ->assertOk()->assertJsonPath('success', true)->assertJsonPath('official.nat', 'KW');

        $u = $o->user->fresh();
        $this->assertSame('Sara Ali Hasan', $u->full_name);
        $this->assertSame('KW', $u->nationality);
    }

    public function test_every_job_the_person_holds_is_listed(): void
    {
        [$ev, $o] = $this->appointed();
        EventOfficial::forceCreate(['event_id' => $ev->id, 'user_id' => $o->user_id, 'role' => 'weigh_in', 'compensation' => 'volunteer']);

        $jobs = $this->actingAs($this->owner)->getJson(route('federations.api.officials.one', ['event' => $ev->uuid, 'official' => $o->id]))
            ->assertOk()->json('official.jobs');
        $this->assertSame(['jury', 'weigh_in'], array_column($jobs, 'role'));
    }

    public function test_the_window_offers_every_club_of_the_federation(): void
    {
        [$ev] = $this->appointed();
        $club = $this->createClub($this->createUser(), ['country' => 'BH', 'club_name' => 'Energy Zone Test']);
        \App\Federations\Models\FederationClub::forceCreate(['federation_id' => $this->fed->id, 'tenant_id' => $club->id, 'status' => 'approved', 'uuid' => (string) \Illuminate\Support\Str::uuid()]);

        $this->actingAs($this->owner)->get(route('federations.official-edit', ['event' => $ev->uuid]))
            ->assertOk()->assertSee('Energy Zone Test');
    }

    public function test_the_tab_knows_two_jobs_are_one_person(): void
    {
        [$ev, $o] = $this->appointed();
        EventOfficial::forceCreate(['event_id' => $ev->id, 'user_id' => $o->user_id, 'role' => 'weigh_in', 'compensation' => 'volunteer']);
        $fe = FederationEvent::where('club_event_id', $ev->id)->first();

        $people = collect(app(\App\Federations\Services\FederationEventOfficials::class)->of($fe, $this->owner)['groups'])->flatMap(fn ($g) => $g['people']);
        $this->assertCount(2, $people);
        $this->assertCount(1, $people->pluck('person')->unique());
        // A key for the row, never the account id.
        $this->assertNotSame((string) $o->user_id, $people->first()['person']);
    }

    public function test_nobody_else_reads_or_edits_it(): void
    {
        [$ev, $o] = $this->appointed();
        $stranger = $this->createUser();
        $otherOwner = $this->createUser();
        (new Federation)->forceFill([
            'owner_user_id' => $otherOwner->id, 'name' => 'Other Federation', 'short_name' => 'OF',
            'country' => 'BH', 'currency' => 'BHD', 'sport' => 'Karate', 'color' => '#1e2c4f',
            'status' => Federation::STATUS_APPROVED, 'approved_at' => now(),
        ])->save();

        foreach ([$stranger, $otherOwner] as $who) {
            $this->actingAs($who)->getJson(route('federations.api.officials.one', ['event' => $ev->uuid, 'official' => $o->id]))->assertNotFound();
            $this->actingAs($who)->postJson(route('federations.api.officials.person'), ['event' => $ev->uuid, 'official' => $o->id, 'name' => 'Hacked'])
                ->assertStatus($who === $stranger ? 404 : 422);
            $this->actingAs($who)->get(route('federations.official-edit', ['event' => $ev->uuid, 'official' => $o->id]))->assertNotFound();
        }
        // An appointment of another event, addressed through this one, is not found.
        $this->actingAs($this->owner)->getJson(route('federations.api.officials.one', ['event' => $ev->uuid, 'official' => $o->id + 999]))->assertNotFound();
        $this->assertSame('Sara Ali', $o->user->fresh()->full_name);
    }

    public function test_bad_input_is_refused(): void
    {
        [$ev, $o] = $this->appointed();
        $post = fn (array $d) => $this->actingAs($this->owner)->postJson(route('federations.api.officials.person'), ['event' => $ev->uuid, 'official' => $o->id] + $d);

        $post(['gender' => 'f'])->assertStatus(422);
        $post(['nat' => 'ZZ'])->assertStatus(422);
        $post(['photo' => 'javascript:alert(1)'])->assertStatus(422);
        $post(['name' => 'x'])->assertStatus(422);
        $this->assertSame('Sara Ali', $o->user->fresh()->full_name);
    }

    public function test_the_edit_window_opens_for_the_manager(): void
    {
        [$ev, $o] = $this->appointed();

        $this->actingAs($this->owner)->get(route('federations.official-edit', ['event' => $ev->uuid, 'official' => $o->id]))
            ->assertOk()->assertSee('fedOfficialEdit', false)->assertDontSee('Sara Ali');
        $this->actingAs($this->owner)->get(route('federations.official-edit', ['event' => $ev->uuid]))->assertOk();
    }
}
