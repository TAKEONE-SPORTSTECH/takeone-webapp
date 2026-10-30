<?php

namespace Tests\Feature\Federations;

use App\Events\Models\ClubEvent;
use App\Events\Models\ClubEventRegistration;
use App\Events\Models\EventOfficial;
use App\Events\Models\EventPublicEntry;
use App\Events\Support\EventAccess;
use App\Federations\Models\Federation;
use App\Federations\Models\FederationAthlete;
use App\Federations\Models\FederationClub;
use App\Federations\Models\FederationEntry;
use App\Federations\Models\FederationEntryAthlete;
use App\Federations\Models\FederationEvent;
use App\Federations\Services\FederationPlatformEntries;
use App\Federations\Services\FederationPlatformEvent;
use App\Members\Models\User;
use Tests\TestCase;

/**
 * A federation's competition, running as a platform event (2026-09-28).
 *
 * What this protects: the event belongs to the FEDERATION (never a club);
 * the federation's managers run it and nobody else gains anything; the
 * clubs' athletes arrive as entry requests the organiser decides, both ways;
 * the clubs' named officials arrive without powers; and a console edit flows
 * back without ever detaching the event from its host.
 */
class PlatformEventsTest extends TestCase
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

    private function fedEvent(): FederationEvent
    {
        $e = (new FederationEvent)->forceFill([
            'federation_id' => $this->fed->id, 'created_by' => $this->owner->id,
            'name' => 'National Karate Cup', 'sport' => 'Karate', 'status' => 'Registration open',
            'starts_on' => now()->addMonth()->format('Y-m-d').'T09:00',
            'details' => ['venue' => 'Isa Sports City', 'types' => ['Individual Kumite'], 'free' => false,
                'fees' => [['label' => 'Individual Kumite', 'amount' => '15', 'type' => 'Individual Kumite']]],
        ]);
        $e->save();

        return $e->fresh('federation');
    }

    /** A club of the federation, its entry to the event, and one athlete on it. */
    private function clubEntry(FederationEvent $fe, string $approval = 'Pending'): array
    {
        $club = $this->createClub($this->createUser(), ['country' => 'BH']);
        $fc = FederationClub::forceCreate(['federation_id' => $this->fed->id, 'tenant_id' => $club->id, 'status' => 'approved', 'uuid' => (string) \Illuminate\Support\Str::uuid()]);
        $user = $this->createUser(['gender' => 'Male', 'birthdate' => now()->subYears(25)->toDateString()]);
        $athlete = FederationAthlete::forceCreate(['user_id' => $user->id, 'takeone_id' => 'BHR-TK'.random_int(100000, 999999)]);
        $entry = FederationEntry::forceCreate(['federation_event_id' => $fe->id, 'federation_club_id' => $fc->id, 'ref' => 'NKC-'.random_int(1000, 9999),
            'officials' => ['mgr' => ['name' => 'Team Boss'], 'ref1' => ['name' => 'Ref One', 'license' => '']]]);
        $row = FederationEntryAthlete::forceCreate(['federation_entry_id' => $entry->id, 'federation_athlete_id' => $athlete->id,
            'weight' => 70, 'types' => ['Individual Kumite'], 'status' => 'Active', 'approval' => $approval]);

        return compact('club', 'user', 'entry', 'row');
    }

    public function test_it_is_hosted_by_the_federation_and_run_by_its_managers_only(): void
    {
        $ev = app(FederationPlatformEvent::class)->sync($this->fedEvent());

        $this->assertNull($ev->tenant_id);
        $this->assertSame('federation', $ev->host_type);
        $this->assertSame($this->fed->id, (int) $ev->host_id);
        $this->assertSame('Test Karate Federation', $ev->host()->name);
        $this->assertInstanceOf(\App\Events\Sports\Karate\Tournament\Tournament::class, app(\App\Events\EventTypeRegistry::class)->for($ev));

        $access = app(EventAccess::class);
        $this->assertTrue($access->canManage($ev, $this->owner->fresh()));
        $this->assertFalse($access->canManage($ev, $this->createUser()));
        $this->actingAs($this->owner->fresh())->get('/me/events/'.$ev->uuid.'/manage')->assertOk();
    }

    public function test_the_schedule_tab_gets_the_running_order_doors_and_only_an_organiser_may_write(): void
    {
        $fe = $this->fedEvent();
        $ev = app(FederationPlatformEvent::class)->sync($fe);

        $ctl = app(\App\Federations\Controllers\FederationAppController::class);
        $doorsFor = new \ReflectionMethod($ctl, 'platformDoors');
        $access = app(\App\Federations\Services\FederationDirectory::class)->access($this->owner->fresh());
        $doors = (array) $doorsFor->invoke($ctl, $access, $this->owner->fresh())['runOrder'];

        $mine = $doors[$fe->uuid] ?? null;
        $this->assertNotNull($mine, 'the organiser gets the running order');
        $this->assertSame($ev->uuid, $mine['event']);
        $this->assertTrue($mine['manage']);
        foreach (['mats', 'day', 'reorder', 'boutMove', 'renumber'] as $k) {
            $this->assertNotEmpty($mine[$k], "organiser door: {$k}");
        }

        // …and the Divisions tab's editor doors, the platform's own endpoints.
        $div = ((array) $doorsFor->invoke($ctl, $access, $this->owner->fresh())['divisions'])[$fe->uuid] ?? null;
        $this->assertNotNull($div, 'the organiser gets the divisions editor');
        $this->assertSame(route('me.events.divisions.data', $ev->uuid), $div['data']);
        $this->assertSame(route('me.events.divisions.store', $ev->uuid), $div['base']);

        // Somebody who may only read gets no write door at all.
        $readOnly = (new \ReflectionMethod($ctl, 'runOrderDoors'))->invoke($ctl, $ev, false);
        $this->assertArrayHasKey('mats', $readOnly);
        foreach (['day', 'reorder', 'move', 'boutMove', 'renumber'] as $k) {
            $this->assertArrayNotHasKey($k, $readOnly, "no write door for a reader: {$k}");
        }
    }

    public function test_switched_off_nothing_is_created(): void
    {
        config(['federations.platform_events' => false]);

        $this->assertNull(app(FederationPlatformEvent::class)->sync($this->fedEvent()));
        $this->assertSame(0, ClubEvent::whereNotNull('host_type')->count());
    }

    public function test_a_clubs_athletes_are_entered_straight_away_and_withdrawing_takes_them_off(): void
    {
        $fe = $this->fedEvent();
        $a = $this->clubEntry($fe);
        $ev = app(FederationPlatformEvent::class)->sync($fe);   // first link brings the entry across

        // No approval step (2026-10-02): entered at once, placed in the division
        // the rule book says, and marked Approved on the federation's side too.
        $req = EventPublicEntry::where('event_id', $ev->id)->where('user_id', $a['user']->id)->first();
        $this->assertNotNull($req);
        $this->assertSame('accepted', $req->state);
        $this->assertSame($a['club']->id, (int) $req->representing_tenant_id);
        $reg = ClubEventRegistration::where('event_id', $ev->id)->where('user_id', $a['user']->id)->first();
        $this->assertNotNull($reg);
        $this->assertNotNull($reg->category_id);
        $this->assertStringContainsString('-75', $reg->category->name);
        $this->assertSame('Approved', $a['row']->fresh()->approval);

        // Withdrawn on the dashboard: taken off the event.
        $a['row']->forceFill(['status' => 'Withdrawn', 'withdrawn_at' => now()])->save();
        app(FederationPlatformEntries::class)->sync($fe->fresh());
        $this->assertFalse(ClubEventRegistration::where('event_id', $ev->id)->where('user_id', $a['user']->id)->where('role', 'participant')->exists());
    }

    /**
     * A dashboard save after the event has started — editing somebody's
     * nationality, say — runs this sync too. It must never place a stray entry
     * or create a division then: the draw is the record (2026-10-26).
     */
    public function test_a_started_event_s_divisions_are_never_touched_by_the_sync(): void
    {
        $fe = $this->fedEvent();
        $a = $this->clubEntry($fe);
        $ev = app(FederationPlatformEvent::class)->sync($fe);
        $reg = ClubEventRegistration::where('event_id', $ev->id)->where('user_id', $a['user']->id)->first();
        $this->assertNotNull($reg->category_id);

        $ev->forceFill(['started_at' => now()])->save();
        $reg->forceFill(['category_id' => null, 'category_source' => null])->save();
        $divisions = \App\Events\Models\EventCategory::where('event_id', $ev->id)->count();

        app(FederationPlatformEntries::class)->sync($fe->fresh());

        $this->assertNull($reg->fresh()->category_id);
        $this->assertSame($divisions, \App\Events\Models\EventCategory::where('event_id', $ev->id)->count());
    }

    /**
     * Removed in the CONSOLE (not on the dashboard): the federation's row and
     * every figure follow, and re-entering brings both back — the dashboard
     * used to keep counting an athlete the event no longer had (2026-10-08).
     */
    public function test_a_console_removal_moves_the_federations_record_and_numbers(): void
    {
        $fe = $this->fedEvent();
        $a = $this->clubEntry($fe);
        $ev = app(FederationPlatformEvent::class)->sync($fe);
        $figures = fn () => app(\App\Federations\Services\FederationEventFigures::class)->of($fe->fresh(), (array) $fe->details);

        $this->assertSame(1, $figures()['reg']);
        $this->assertSame(1, $figures()['divisions']);
        $this->assertNotContains('18 of 22', array_column($figures()['readiness'], 'note'));

        $reg = ClubEventRegistration::where('event_id', $ev->id)->where('user_id', $a['user']->id)->firstOrFail();
        $this->assertTrue(app(\App\Events\Support\EntryService::class)->remove($ev, $this->owner->fresh(), $reg, notify: false)['ok']);

        $this->assertSame('Withdrawn', $a['row']->fresh()->status);
        $this->assertSame(0, $figures()['reg']);
        $this->assertSame(0, $figures()['divisions']);

        // Back in: Active again, so the next sync does not take them back out.
        $reg->replicate()->save();
        $this->assertSame('Active', $a['row']->fresh()->status);
        app(FederationPlatformEntries::class)->sync($fe->fresh());
        $this->assertSame(1, $figures()['reg']);
    }

    /** The Statistics tab reads the event's real bouts and results, not a draw of its own. */
    public function test_the_statistics_bracket_is_the_events_own_draw_and_results(): void
    {
        $fe = $this->fedEvent();
        $a = $this->clubEntry($fe);
        $b = $this->clubEntry($fe);
        $ev = app(FederationPlatformEvent::class)->sync($fe);
        app(FederationPlatformEntries::class)->sync($fe->fresh());

        $ra = ClubEventRegistration::where('event_id', $ev->id)->where('user_id', $a['user']->id)->firstOrFail();
        $rb = ClubEventRegistration::where('event_id', $ev->id)->where('user_id', $b['user']->id)->firstOrFail();
        $this->assertSame($ra->category_id, $rb->category_id);

        \App\Events\Models\EventMatch::forceCreate(['event_id' => $ev->id, 'category_id' => $ra->category_id, 'round' => 'Final', 'slot' => 0,
            'a_name' => 'A', 'b_name' => 'B', 'a_competitor_id' => $ra->id, 'b_competitor_id' => $rb->id, 'court' => 'Mat 2',
            'winner' => 'b', 'a_score' => '1', 'b_score' => '3', 'win_reason' => 'other', 'status' => 'done']);

        $bracket = app(\App\Federations\Services\FederationEventBracket::class)->of($fe->fresh());
        $this->assertCount(1, $bracket['cats']);
        $this->assertSame(2, $bracket['cats'][0]['athletes']);
        $this->assertSame(2, $bracket['mats']);
        $m = $bracket['matches'][0];
        $this->assertSame(['Final', 2, 'b', 'Points', true], [$m['round'], $m['ring'], $m['w'], $m['by'], $m['done']]);
        $this->assertSame($b['user']->full_name, $m['B']['name']);
    }

    /** Deleting an athlete's account releases their national ID, so the same person can be entered again (2026-10-08). */
    public function test_a_deleted_athletes_national_id_is_released(): void
    {
        $user = $this->createUser();
        $hash = FederationAthlete::hashNationalId('880112345');
        $a = FederationAthlete::forceCreate(['user_id' => $user->id, 'takeone_id' => 'BHR-TK'.random_int(100000, 999999),
            'national_id' => '880112345', 'national_id_hash' => $hash]);

        $user->delete();

        $a->refresh();
        $this->assertNull($a->national_id_hash);
        $this->assertNull($a->national_id);
        $this->assertNotNull($a->anonymised_at);
        $this->assertFalse(FederationAthlete::where('national_id_hash', $hash)->exists());

        // The same ID registers again, as a new athlete.
        FederationAthlete::forceCreate(['user_id' => $this->createUser()->id, 'takeone_id' => 'BHR-TK'.random_int(100000, 999999),
            'national_id' => '880112345', 'national_id_hash' => $hash]);
        $this->assertTrue(FederationAthlete::where('national_id_hash', $hash)->exists());
    }

    public function test_club_officials_arrive_without_powers_and_are_never_duplicated(): void
    {
        $fe = $this->fedEvent();
        $this->clubEntry($fe);
        $ev = app(FederationPlatformEvent::class)->sync($fe);
        app(FederationPlatformEntries::class)->sync($fe->fresh());

        $officials = EventOfficial::where('event_id', $ev->id)->get();
        $this->assertCount(2, $officials);
        $this->assertTrue($officials->every(fn ($o) => $o->role === EventOfficial::ROLE_OTHER));
        $ref = $officials->first(fn ($o) => str_contains($o->role_label, 'Referee'));
        $this->assertFalse(app(EventAccess::class)->canManage($ev, $ref->user));
        $this->assertFalse(app(EventAccess::class)->isOfficial($ev, $ref->user, EventOfficial::ROLE_JURY));
    }

    public function test_a_console_edit_flows_back_and_the_host_is_kept(): void
    {
        $fe = $this->fedEvent();
        $ev = app(FederationPlatformEvent::class)->sync($fe);

        $this->actingAs($this->owner->fresh())->putJson('/me/events/'.$ev->uuid, [
            'title' => 'National Karate Cup (final)', 'event_type' => 'championship', 'sport' => 'karate', 'scope' => 'nationwide',
            'date' => $ev->date->toDateString(), 'start_time' => '10:00', 'location' => 'Khalifa Sports City',
            'participant_free' => false, 'spectator_enabled' => false, 'tenant_id' => null,
        ])->assertOk();

        $ev->refresh();
        $this->assertNull($ev->tenant_id);
        $this->assertSame('federation', $ev->host_type);
        $fe->refresh();
        $this->assertSame('National Karate Cup (final)', $fe->name);
        $this->assertStringEndsWith('T10:00', $fe->starts_on);
        $this->assertSame('Khalifa Sports City', $fe->details['venue']);
    }

    public function test_the_members_events_card_opens_the_platform_event(): void
    {
        $fe = $this->fedEvent();
        $a = $this->clubEntry($fe);
        $ev = app(FederationPlatformEvent::class)->sync($fe);
        \App\Members\Models\Membership::forceCreate(['user_id' => $a['user']->id, 'tenant_id' => $a['club']->id, 'status' => 'active']);

        $card = collect(\App\Federations\Support\MemberFederationEvents::for($a['user']->fresh()))->firstWhere('title', 'National Karate Cup');
        $this->assertNotNull($card);
        $this->assertSame(route('me.events.show', ['event' => $ev->uuid]), $card['href']);
        $this->assertFalse($card['external']);
    }

    public function test_members_do_not_enter_themselves_their_club_does(): void
    {
        $fe = $this->fedEvent();
        $ev = app(FederationPlatformEvent::class)->sync($fe);
        $ev->forceFill(['enrollment_starts_at' => null, 'enrollment_ends_at' => null])->save();
        $member = $this->createUser(['gender' => 'Male', 'birthdate' => now()->subYears(20)->toDateString()]);
        $club = $this->createClub($this->createUser(), ['country' => 'BH']);
        \App\Members\Models\Membership::forceCreate(['user_id' => $member->id, 'tenant_id' => $club->id, 'status' => 'active']);

        $this->actingAs($member)->postJson(route('me.events.register', $ev->uuid), [])
            ->assertStatus(422)->assertJson(['code' => 'club_entry']);
        $this->assertSame(0, ClubEventRegistration::where('event_id', $ev->id)->count());

        // A club's owner is handed the federation's invitation instead…
        config(['events.club_entry' => false]);
        $door = $ev->host()->entryDoor($ev, \App\Members\Models\User::find($club->owner_user_id));
        $this->assertTrue($door['enters']);
        $this->assertStringContainsString($fe->uuid, $door['url']);
        // …or, with the shared club screens on, the event's own "Register your club" address.
        config(['events.club_entry' => true, 'federations.invite_v2' => true]);
        $this->assertSame(route('me.events.club-entry.rules', $ev->uuid),
            $ev->host()->entryDoor($ev, \App\Members\Models\User::find($club->owner_user_id))['url']);
        config(['events.club_entry' => false]);
        $this->assertFalse($ev->host()->entryDoor($ev, $member->fresh())['enters']);

        // The page says so: the member is told who enters them, the owner is handed the door.
        config(['events.unified_page' => true]);
        $this->actingAs($member->fresh())->get(route('me.events.show', $ev->uuid))
            ->assertOk()->assertSee(__('events::events.host_entry_title'));
        $this->actingAs(\App\Members\Models\User::find($club->owner_user_id))->get(route('me.events.show', $ev->uuid))
            ->assertOk()->assertSee(__('events::events.host_entry_manager'))->assertSee($fe->uuid);

        // Past the closing date the door is gone, and the card says why.
        $ev->forceFill(['enrollment_ends_at' => now()->subDays(2)])->save();
        $this->actingAs(\App\Members\Models\User::find($club->owner_user_id))->get(route('me.events.show', $ev->uuid))
            ->assertOk()->assertDontSee(__('events::events.host_entry_manager'))->assertSee(__('events::events.host_entry_title'))
            ->assertSee(__('events::events.entry_closed', ['date' => \App\Support\Cldr::shortDate($ev->fresh()->enrollment_ends_at)]), false);

        // A club's own event is untouched: nobody is sent anywhere.
        $clubEvent = ClubEvent::create(['tenant_id' => $club->id, 'created_by' => $club->owner_user_id, 'title' => 'Club Open',
            'event_type' => 'championship', 'sport' => 'karate', 'scope' => 'internal', 'status' => 'active', 'date' => now()->addWeek()->toDateString(), 'start_time' => '09:00']);
        $this->assertNull($clubEvent->host()->entryDoor($clubEvent, $member->fresh()));
    }

    public function test_kata_and_kumite_are_two_entries_in_their_own_divisions(): void
    {
        $fe = $this->fedEvent();
        $d = (array) $fe->details;
        $d['types'] = ['Individual Kumite', 'Individual Kata'];
        $d['fees'] = [['label' => 'Individual Kumite', 'amount' => '15', 'type' => 'Individual Kumite'], ['label' => 'Individual Kata', 'amount' => '10', 'type' => 'Individual Kata']];
        $fe->forceFill(['details' => $d])->save();
        $a = $this->clubEntry($fe->fresh('federation'), 'Approved');
        $a['row']->forceFill(['types' => ['Individual Kumite', 'Individual Kata']])->save();

        $ev = app(FederationPlatformEvent::class)->sync($fe->fresh('federation'));
        $this->assertTrue(\App\Events\Support\EventStreams::many($ev->fresh()));
        app(FederationPlatformEntries::class)->sync($fe->fresh());

        $regs = ClubEventRegistration::where('event_id', $ev->id)->where('user_id', $a['user']->id)->with('category')->get();
        $this->assertCount(2, $regs);
        $kata = $regs->first(fn ($r) => $r->category && $r->category->stream === 'individualkata');
        $kumite = $regs->first(fn ($r) => $r->category && $r->category->stream === 'individualkumite');
        $this->assertNotNull($kata);
        $this->assertNotNull($kumite);
        $this->assertNull($kata->category->max_weight);
        $this->assertStringContainsString('-75', $kumite->category->name);

        // Idempotent: the next sync adds nothing.
        app(FederationPlatformEntries::class)->sync($fe->fresh());
        $this->assertSame(2, ClubEventRegistration::where('event_id', $ev->id)->where('user_id', $a['user']->id)->count());
    }

    public function test_a_club_official_s_photo_becomes_their_face(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        \Illuminate\Support\Facades\Storage::fake('public');
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        \Illuminate\Support\Facades\Storage::disk('local')->put('federations/x/clubs/y/officials/ref.png', $png);

        $fe = $this->fedEvent();
        $a = $this->clubEntry($fe);
        $a['entry']->forceFill(['officials' => ['ref1' => ['name' => 'Ref One', 'photo' => 'federations/x/clubs/y/officials/ref.png']]])->save();
        $ev = app(FederationPlatformEvent::class)->sync($fe);
        app(FederationPlatformEntries::class)->sync($fe->fresh());

        $o = EventOfficial::where('event_id', $ev->id)->first();
        $this->assertNotNull($o->photo);
        $this->assertSame($o->photo, $o->user->fresh()->profile_picture);
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($o->photo);

        // The club empties the slot: the appointment goes, the person's face stays.
        $a['entry']->forceFill(['officials' => []])->save();
        app(FederationPlatformEntries::class)->sync($fe->fresh());
        $this->assertSame(0, EventOfficial::where('event_id', $ev->id)->count());
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($o->photo);
    }

    public function test_the_dashboard_chooses_the_rule_book_and_the_console_follows(): void
    {
        $fe = $this->fedEvent();
        $access = app(\App\Federations\Services\FederationDirectory::class)->access($this->owner->fresh());
        $events = app(\App\Federations\Services\FederationEvents::class);

        $book = $events->ruleBooks($access)[$fe->uuid];
        $this->assertContains('shotokan', array_column($book['categories'], 'key'));

        $this->assertFalse($events->setRuleBook($this->owner, $access, $fe->uuid, 'custom:nowhere', null)['ok']);
        $this->assertTrue($events->setRuleBook($this->owner, $access, $fe->uuid, 'shotokan', null)['ok']);
        $this->assertSame('shotokan', $fe->fresh()->details['categorySystem']);

        // Linked afterwards: the platform event starts under that rule book.
        $ev = app(FederationPlatformEvent::class)->sync($fe->fresh('federation'));
        $this->assertSame('shotokan', $ev->category_system);

        // Changed in the console: the dashboard record follows.
        $this->actingAs($this->owner->fresh())->putJson(route('me.events.rulebook', $ev->uuid), ['category_system' => null])->assertOk();
        $this->assertNull($fe->fresh()->details['categorySystem']);

        // The dashboard offers it: the card's script, and each event's choices.
        $this->actingAs($this->owner->fresh())->get('/federation/')->assertOk()
            ->assertSee('takeone-rulebook.js', false)->assertSee('"ruleBooks"', false)->assertSee('data-fed-rulebook', false);

        // Not a manager: refused.
        $this->assertFalse($events->setRuleBook($this->owner, ['manager' => false] + $access, $fe->uuid, 'shotokan', null)['ok']);
    }

    /**
     * Explore lists the platform card once, and it opens on the federation's
     * invitation — also when the card comes through the club-events list (the
     * viewer made the event) rather than the viewer's federations.
     */
    public function test_explore_opens_a_federation_event_on_its_invitation(): void
    {
        $fe = $this->fedEvent();
        $ev = app(FederationPlatformEvent::class)->sync($fe);
        $viewer = $this->createUser();   // in none of the federation's clubs
        $ev->forceFill(['entry_mode' => 'public', 'created_by' => $viewer->id])->save();

        $card = collect($this->actingAs($viewer)->getJson('/explore/events?scope=public')->assertOk()->json('events'))
            ->firstWhere('key', $ev->uuid);

        $this->assertNotNull($card, 'the public platform event is listed');
        $this->assertSame(route('federations.app.invitation').'?event='.$fe->uuid, $card['cover_url']);
    }
}
