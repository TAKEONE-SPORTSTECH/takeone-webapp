<?php

namespace Tests\Feature\Events;

use App\Clubs\Models\Tenant;
use App\Events\Models\ClubEvent;
use App\Events\Models\ClubEventRegistration;
use App\Events\Models\EventCategory;
use App\Events\Support\EntryChannels;
use App\Members\Models\HealthRecord;
use App\Members\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * How an event takes entries, and who pays for each (PLAN.md Phase 7; owner,
 * 2026-10-03): the organiser chooses individuals, clubs, or both. Turning a
 * channel off refuses NEW entries through it only; the organiser can always
 * add anyone. An athlete their club entered is the club's bill, everything
 * else the person's own.
 */
class EntryChannelsTest extends TestCase
{
    private Tenant $club;

    private User $organiser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organiser = $this->createUser(['full_name' => 'Organiser']);
        $this->club = $this->createClub($this->organiser, ['country' => 'BH', 'currency' => 'BHD']);
        $this->organiser->memberClubs()->syncWithoutDetaching([$this->club->id => ['status' => 'active']]);
    }

    private function event(array $attrs = []): ClubEvent
    {
        $event = ClubEvent::create(array_merge([
            'tenant_id' => $this->club->id, 'created_by' => $this->organiser->id,
            'title' => 'Spring Open', 'event_type' => 'championship', 'sport' => 'taekwondo',
            'scope' => 'inter_club', 'status' => 'active', 'is_archived' => false,
            'date' => now()->addWeeks(2)->toDateString(), 'end_date' => now()->addWeeks(2)->toDateString(),
            'start_time' => '09:00', 'end_time' => '17:00',
        ], $attrs));
        EventCategory::create(['event_id' => $event->id, 'name' => 'Senior Men -58 kg', 'sort_order' => 1]);

        return $event;
    }

    private function athlete(Tenant $club): User
    {
        $u = $this->createUser(['gender' => 'Male', 'birthdate' => now()->subYears(25)->toDateString()]);
        $u->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);
        HealthRecord::create(['user_id' => $u->id, 'weight' => 57, 'recorded_at' => now()]);

        return $u->fresh();
    }

    /** A coach of ANOTHER club, holding the enter-athletes grant. */
    private function visitingCoach(): array
    {
        $club = $this->createClub($this->createUser(), ['country' => 'BH', 'currency' => 'BHD']);
        $coach = $this->createUser();
        $coach->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);
        $this->makeClubAdmin($coach, $club);
        $pid = DB::table('permissions')->where('slug', 'enter-athletes')->value('id');
        DB::table('role_permission')->insertOrIgnore(['role_id' => DB::table('roles')->where('slug', 'club-admin')->value('id'), 'permission_id' => $pid, 'created_at' => now(), 'updated_at' => now()]);

        return [$coach->fresh(), $club];
    }

    public function test_an_event_that_never_chose_takes_both_exactly_as_before(): void
    {
        $event = $this->event();
        $this->assertSame(['individual', 'club'], app(EntryChannels::class)->allowed($event));
        $this->assertFalse(app(EntryChannels::class)->chosen($event));
    }

    public function test_individuals_off_refuses_a_self_entry_but_not_someone_already_in(): void
    {
        $event = $this->event();
        $in = $this->athlete($this->club);
        $this->actingAs($in)->postJson("/me/events/{$event->uuid}/register")->assertOk();

        app(EntryChannels::class)->set($event, ['club']);

        $new = $this->athlete($this->club);
        $this->actingAs($new)->postJson("/me/events/{$event->uuid}/register")
            ->assertStatus(422)->assertJsonPath('code', 'individual_closed');

        // Already entered: the same endpoint still serves them (it is how a receipt is uploaded).
        $this->actingAs($in)->postJson("/me/events/{$event->uuid}/register")->assertJsonMissing(['code' => 'individual_closed']);
        $this->assertTrue(ClubEventRegistration::where('event_id', $event->id)->where('user_id', $in->id)->exists());
    }

    public function test_clubs_off_refuses_a_visiting_coachs_squad_but_never_the_organiser(): void
    {
        $event = $this->event();
        [$coach, $theirClub] = $this->visitingCoach();
        app(EntryChannels::class)->set($event, ['individual']);

        $a = $this->athlete($theirClub);
        $this->actingAs($coach)->postJson("/me/events/{$event->uuid}/entries", ['user_ids' => [$a->id]])
            ->assertJsonCount(0, 'entered')->assertJsonPath('rejected.0.code', 'channel_closed');

        $b = $this->athlete($this->club);
        $this->actingAs($this->organiser)->postJson("/me/events/{$event->uuid}/entries", ['user_ids' => [$b->id]])
            ->assertJsonCount(1, 'entered');
    }

    public function test_the_payer_follows_the_channel(): void
    {
        $event = $this->event();
        $self = $this->athlete($this->club);
        $this->actingAs($self)->postJson("/me/events/{$event->uuid}/register")->assertOk();

        [$coach, $theirClub] = $this->visitingCoach();
        $squad = $this->athlete($theirClub);
        $this->actingAs($coach)->postJson("/me/events/{$event->uuid}/entries", ['user_ids' => [$squad->id]])->assertJsonCount(1, 'entered');

        $mine = ClubEventRegistration::where('event_id', $event->id)->where('user_id', $self->id)->first();
        $theirs = ClubEventRegistration::where('event_id', $event->id)->where('user_id', $squad->id)->first();

        $this->assertSame(['person', $self->id], [$mine->payer_type, (int) $mine->payer_id]);
        $this->assertSame(['club', $theirClub->id], [$theirs->payer_type, (int) $theirs->payer_id]);
        $this->assertNull($theirs->paid_by, 'paid_by still means "verified by", untouched');
    }

    public function test_only_the_organiser_sets_the_channels(): void
    {
        $event = $this->event();

        $this->actingAs($this->athlete($this->club))
            ->postJson(route('me.events.entry-channels', $event->uuid), ['channels' => ['club']])->assertForbidden();

        $this->actingAs($this->organiser)
            ->postJson(route('me.events.entry-channels', $event->uuid), ['channels' => ['club']])
            ->assertOk()->assertJsonPath('channels', ['club']);

        $this->actingAs($this->organiser)
            ->postJson(route('me.events.entry-channels', $event->uuid), ['channels' => []])->assertStatus(422);

        // Choosing exactly the default stores NULL — the event follows the platform again.
        $this->actingAs($this->organiser)
            ->postJson(route('me.events.entry-channels', $event->uuid), ['channels' => ['individual', 'club']])->assertOk();
        $this->assertNull($event->fresh()->entry_channels);
    }

    public function test_the_member_page_explains_instead_of_offering_a_join_that_refuses(): void
    {
        $event = $this->event();
        app(EntryChannels::class)->set($event, ['club']);

        $this->actingAs($this->athlete($this->club))->get("/me/events/{$event->uuid}")
            ->assertOk()
            ->assertViewHas('canCompete', false)
            ->assertViewHas('eligReason', __('events::events.entry_individual_closed'));
    }

    public function test_the_console_offers_the_switch(): void
    {
        $event = $this->event();

        $this->actingAs($this->organiser->fresh())->get("/me/events/{$event->uuid}/manage")
            ->assertOk()
            ->assertSee(e(__('events::events.entry_channels_title')), false)
            ->assertSee(str_replace('/', '\\/', route('me.events.entry-channels', $event->uuid)), false);
    }
}
