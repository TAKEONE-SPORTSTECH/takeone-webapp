<?php

namespace Tests\Feature\Characterization;

use App\Models\ClubEvent;
use App\Models\EventCategory;
use App\Models\EventExpense;
use App\Models\EventMatch;
use App\Models\EventOfficial;
use App\Models\EventParticipantBan;
use App\Clubs\Models\Tenant;
use App\Members\Models\User;
use Database\Factories\ClubEventRegistrationFactory;
use Tests\Feature\Contracts\ContractTestCase;

/**
 * CHARACTERIZATION tests for PersonalEventController — the WRITE surface and
 * the organiser-only screens, continued from PersonalEventCharacterizationTest.
 *
 * Same rules: pin CURRENT behaviour, mark anything surprising `// DIVERGENCE:`,
 * fix nothing. Split into a second file only for size.
 */
class PersonalEventWritesCharacterizationTest extends ContractTestCase
{
    private const MOBILE_UA = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) '
        .'AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';

    private const DESKTOP_UA = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 '
        .'(KHTML, like Gecko) Chrome/120.0 Safari/537.36';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();

        /* These scenarios are all TAEKWONDO CHAMPIONSHIPS, and that package
           opted into the branded event surface on 2026-09-08 — which serves the
           MOBILE blade at every width and drops the platform's chrome
           (App\Http\Middleware\BrandEventPage). This file pins what the
           CONTROLLER does, so the dressing is switched off here and asserted
           where it belongs, in tests/Feature/Events/BrandedEventSurfaceTest.php.
           Without this the device-split assertions below would be measuring the
           middleware rather than the controller. One test at the end of the file
           pins the branded outcome so the two cannot drift apart unnoticed. */
        config(['events.branded_surface' => false]);
    }

    private function scenario(array $eventAttrs = []): array
    {
        $organiser = $this->createUser(['full_name' => 'Organiser One']);
        $club = $this->clubFor($organiser);
        $event = $this->championship($club, $organiser, $eventAttrs);

        return [$organiser, $club, $event];
    }

    private function clubMember(Tenant $club, array $attrs = []): User
    {
        $user = $this->createUser($attrs + ['full_name' => 'Ordinary Member']);
        $user->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);

        return $user->fresh();
    }

    private function asDesktop(User $user)
    {
        return $this->actingAs($user)->withHeader('User-Agent', self::DESKTOP_UA);
    }

    private function asMobile(User $user)
    {
        return $this->actingAs($user)->withHeader('User-Agent', self::MOBILE_UA);
    }

    /** The minimum payload `store()` accepts. */
    private function newEventPayload(Tenant $club, array $overrides = []): array
    {
        return array_merge([
            'tenant_id' => $club->id,
            'title' => 'Autumn Open',
            'event_type' => 'championship',
            'sport' => 'taekwondo',
            'scope' => 'internal',
            'date' => now()->addMonth()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '17:00',
            'participant_free' => true,
            'spectator_enabled' => false,
        ], $overrides);
    }

    /* =====================================================================
     * create / store / edit / update — the event's own lifecycle
     * ===================================================================== */

    public function test_create_screen_is_one_shared_view_for_both_devices(): void
    {
        [$organiser] = $this->scenario();

        // NOTE: `create` is NOT device-split — the same Blade serves both.
        $this->asDesktop($organiser)->get(route('me.events.create'))
            ->assertOk()->assertViewIs('personal.event-create');

        $this->asMobile($organiser)->get(route('me.events.create'))
            ->assertOk()->assertViewIs('personal.event-create');
    }

    public function test_create_offers_the_clubs_the_member_belongs_to_or_runs(): void
    {
        [$organiser, $club] = $this->scenario();

        $clubs = $this->asDesktop($organiser)->get(route('me.events.create'))->viewData('clubs');

        $this->assertSame([$club->id], array_column($clubs, 'id'));
        $this->assertSame(['id', 'name', 'currency'], array_keys($clubs[0]));
    }

    public function test_store_creates_an_event_owned_by_its_creator_and_redirects_to_it(): void
    {
        [$organiser, $club] = $this->scenario();

        $response = $this->actingAs($organiser)
            ->postJson(route('me.events.store'), $this->newEventPayload($club));

        $response->assertOk()->assertJson(['success' => true]);

        $created = ClubEvent::where('title', 'Autumn Open')->firstOrFail();
        $this->assertSame($organiser->id, $created->created_by);
        $this->assertSame($club->id, $created->tenant_id);
        $this->assertSame('active', $created->status);
        $this->assertFalse((bool) $created->is_archived);
        $this->assertSame(route('me.events.show', $created->uuid), $response->json('redirect'));
    }

    public function test_store_refuses_a_club_the_member_neither_belongs_to_nor_runs(): void
    {
        [$organiser] = $this->scenario();
        $foreignOwner = $this->createUser(['full_name' => 'Someone Else']);
        $foreignClub = $this->clubFor($foreignOwner, ['club_name' => 'Not Yours']);

        $this->actingAs($organiser)
            ->postJson(route('me.events.store'), $this->newEventPayload($foreignClub))
            ->assertForbidden();

        $this->assertDatabaseMissing('club_events', ['title' => 'Autumn Open']);
    }

    /**
     * A scope wider than the host club addresses people who never opted into
     * it, so only the club's owner/admin (or platform staff) may pick one.
     */
    public function test_store_refuses_a_broadcast_scope_from_an_ordinary_member(): void
    {
        [, $club] = $this->scenario();
        $member = $this->clubMember($club);

        $broadcast = config('event_notifications.broadcast_scopes', []);
        $this->assertNotEmpty($broadcast, 'the fixture needs at least one broadcast scope configured');

        $this->actingAs($member)
            ->postJson(route('me.events.store'), $this->newEventPayload($club, [
                'title' => 'Mass Mail Cup',
                'scope' => $broadcast[0],
            ]))
            ->assertForbidden();

        $this->assertDatabaseMissing('club_events', ['title' => 'Mass Mail Cup']);
    }

    public function test_store_validation_rejects_an_unknown_event_type_and_a_bad_time_format(): void
    {
        [$organiser, $club] = $this->scenario();

        $this->actingAs($organiser)
            ->postJson(route('me.events.store'), $this->newEventPayload($club, ['event_type' => 'jousting']))
            ->assertStatus(422)->assertJsonValidationErrors('event_type');

        $this->actingAs($organiser)
            ->postJson(route('me.events.store'), $this->newEventPayload($club, ['start_time' => '9am']))
            ->assertStatus(422)->assertJsonValidationErrors('start_time');
    }

    public function test_store_validation_rejects_enrolment_closing_after_the_event(): void
    {
        [$organiser, $club] = $this->scenario();

        $this->actingAs($organiser)
            ->postJson(route('me.events.store'), $this->newEventPayload($club, [
                'date' => now()->addMonth()->toDateString(),
                'enrollment_ends_at' => now()->addMonths(2)->toDateString(),
            ]))
            ->assertStatus(422)->assertJsonValidationErrors('enrollment_ends_at');
    }

    public function test_store_validation_rejects_an_end_date_before_the_start(): void
    {
        [$organiser, $club] = $this->scenario();

        $this->actingAs($organiser)
            ->postJson(route('me.events.store'), $this->newEventPayload($club, [
                'date' => now()->addMonth()->toDateString(),
                'end_date' => now()->addWeek()->toDateString(),
            ]))
            ->assertStatus(422)->assertJsonValidationErrors('end_date');
    }

    public function test_edit_is_the_create_screen_in_edit_mode_and_organiser_only(): void
    {
        [$organiser, $club, $event] = $this->scenario();

        $response = $this->asDesktop($organiser)->get(route('me.events.edit', $event->uuid));
        $response->assertOk()->assertViewIs('personal.event-create');
        $response->assertViewHas('mode', 'edit');
        $response->assertViewHas('divisions', []);

        $member = $this->clubMember($club);
        $this->asDesktop($member)->get(route('me.events.edit', $event->uuid))
            ->assertRedirect('/');
    }

    public function test_update_saves_the_title_for_the_organiser_and_is_denied_to_anyone_else(): void
    {
        [$organiser, $club, $event] = $this->scenario();
        $member = $this->clubMember($club);

        $payload = $this->newEventPayload($club, ['title' => 'Renamed Open']);
        unset($payload['tenant_id']);

        $this->actingAs($member)->putJson(route('me.events.update', $event->uuid), $payload)
            ->assertForbidden();
        $this->assertSame('Spring Open', $event->fresh()->title);

        $this->actingAs($organiser)->putJson(route('me.events.update', $event->uuid), $payload)
            ->assertOk()->assertJson(['success' => true]);
        $this->assertSame('Renamed Open', $event->fresh()->title);
    }

    /* =====================================================================
     * Results — hand-entry is refused by a type with its own engine
     * ===================================================================== */

    /**
     * A championship derives its podium from the recorded play, so typed-in
     * winners are refused outright rather than allowed to contradict it.
     */
    public function test_set_results_is_refused_for_a_type_that_derives_its_own_podium(): void
    {
        [$organiser, , $event] = $this->scenario();

        $this->actingAs($organiser)
            ->putJson(route('me.events.results', $event->uuid), ['results' => [
                ['place' => 1, 'name' => 'Invented Winner'],
            ]])
            ->assertStatus(422)->assertJson(['success' => false]);

        $this->assertNull($event->fresh()->results);
    }

    public function test_set_results_is_denied_to_a_non_organiser(): void
    {
        [, $club, $event] = $this->scenario();
        $member = $this->clubMember($club);

        $this->actingAs($member)
            ->putJson(route('me.events.results', $event->uuid), ['results' => []])
            ->assertForbidden();
    }

    /* =====================================================================
     * Moderation — remove / block / blacklist, and lifting a ban
     * ===================================================================== */

    public function test_moderate_remove_drops_the_registration_without_banning(): void
    {
        [$organiser, $club, $event] = $this->scenario();
        $member = $this->clubMember($club);
        ClubEventRegistrationFactory::new()->create([
            'event_id' => $event->id, 'user_id' => $member->id, 'role' => 'participant',
        ]);

        $this->actingAs($organiser)->postJson(
            route('me.events.participant.moderate', ['event' => $event->uuid, 'user' => $member->id]),
            ['action' => 'remove'],
        )->assertOk()->assertJson(['success' => true, 'banned' => false, 'going' => 0]);

        $this->assertDatabaseMissing('club_event_registrations', [
            'event_id' => $event->id, 'user_id' => $member->id,
        ]);
        $this->assertDatabaseCount('event_participant_bans', 0);
    }

    public function test_moderate_block_bans_for_this_event_and_lift_ban_removes_it(): void
    {
        [$organiser, $club, $event] = $this->scenario();
        $member = $this->clubMember($club);
        ClubEventRegistrationFactory::new()->create([
            'event_id' => $event->id, 'user_id' => $member->id, 'role' => 'participant',
        ]);

        $this->actingAs($organiser)->postJson(
            route('me.events.participant.moderate', ['event' => $event->uuid, 'user' => $member->id]),
            ['action' => 'block', 'reason' => 'No-show'],
        )->assertOk()->assertJson(['success' => true, 'banned' => true]);

        $this->assertDatabaseHas('event_participant_bans', [
            'scope' => 'event', 'event_id' => $event->id, 'user_id' => $member->id, 'reason' => 'No-show',
        ]);

        // A blocked member is refused at the join door.
        $this->actingAs($member)->postJson(route('me.events.register', $event->uuid))
            ->assertStatus(403)->assertJson(['code' => 'banned']);

        $this->actingAs($organiser)->deleteJson(
            route('me.events.ban.lift', ['event' => $event->uuid, 'user' => $member->id]),
        )->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseCount('event_participant_bans', 0);
    }

    /** A blacklist is club-wide: it is keyed to the tenant, not the event. */
    public function test_moderate_blacklist_bans_across_the_whole_club(): void
    {
        [$organiser, $club, $event] = $this->scenario();
        $member = $this->clubMember($club);

        $this->actingAs($organiser)->postJson(
            route('me.events.participant.moderate', ['event' => $event->uuid, 'user' => $member->id]),
            ['action' => 'blacklist'],
        )->assertOk()->assertJson(['success' => true, 'banned' => true]);

        $this->assertDatabaseHas('event_participant_bans', [
            'scope' => 'club', 'tenant_id' => $club->id, 'user_id' => $member->id, 'event_id' => null,
        ]);

        // It bars a DIFFERENT event of the same club too.
        $other = $this->championship($club, $organiser, ['title' => 'Winter Cup']);
        $this->actingAs($member)->postJson(route('me.events.register', $other->uuid))
            ->assertStatus(403)->assertJson(['code' => 'banned']);
    }

    public function test_moderate_rejects_an_unknown_action_and_self_moderation(): void
    {
        [$organiser, $club, $event] = $this->scenario();
        $member = $this->clubMember($club);

        $this->actingAs($organiser)->postJson(
            route('me.events.participant.moderate', ['event' => $event->uuid, 'user' => $member->id]),
            ['action' => 'defenestrate'],
        )->assertStatus(422)->assertJsonValidationErrors('action');

        $this->actingAs($organiser)->postJson(
            route('me.events.participant.moderate', ['event' => $event->uuid, 'user' => $organiser->id]),
            ['action' => 'block'],
        )->assertStatus(422)->assertJson(['success' => false]);
    }

    public function test_moderate_and_lift_ban_are_denied_to_a_non_organiser(): void
    {
        [, $club, $event] = $this->scenario();
        $member = $this->clubMember($club);
        $victim = $this->clubMember($club, ['full_name' => 'Victim']);

        $this->actingAs($member)->postJson(
            route('me.events.participant.moderate', ['event' => $event->uuid, 'user' => $victim->id]),
            ['action' => 'block'],
        )->assertForbidden();

        EventParticipantBan::create([
            'user_id' => $victim->id, 'event_id' => $event->id, 'tenant_id' => $club->id,
            'scope' => 'event', 'created_by' => $event->created_by,
        ]);

        $this->actingAs($member)->deleteJson(
            route('me.events.ban.lift', ['event' => $event->uuid, 'user' => $victim->id]),
        )->assertForbidden();

        $this->assertDatabaseCount('event_participant_bans', 1);
    }

    /**
     * SECURITY NOTE (pinned, not a divergence): the moderation route binds the
     * member by AUTO-INCREMENT id, not by uuid — against CLAUDE.md's
     * "Unpredictable Resource Identifiers". Authorization still holds (only the
     * organiser may call it), which is why this is a note and not a hole; but a
     * refactor should move it to `{user:uuid}` deliberately, not by accident.
     */
    public function test_moderation_routes_are_keyed_by_the_numeric_user_id(): void
    {
        [, , $event] = $this->scenario();
        $victim = $this->createUser();

        $this->assertStringEndsWith(
            '/participants/'.$victim->id.'/moderate',
            route('me.events.participant.moderate', ['event' => $event->uuid, 'user' => $victim->id]),
        );
    }

    /* =====================================================================
     * Expenses — the event P&L
     * ===================================================================== */

    public function test_expenses_add_and_delete_round_trip_for_the_organiser(): void
    {
        [$organiser, , $event] = $this->scenario();

        $added = $this->actingAs($organiser)->postJson(route('me.events.expenses.add', $event->uuid), [
            'label' => 'Mat hire', 'amount' => 120.5,
        ]);
        $added->assertOk()->assertJson(['success' => true]);
        $this->assertSame('Mat hire', $added->json('expense.label'));
        $this->assertSame(120.5, $added->json('expense.amount'));

        $expense = EventExpense::where('event_id', $event->id)->firstOrFail();
        $this->assertSame($organiser->id, $expense->created_by);

        $this->actingAs($organiser)->deleteJson(
            route('me.events.expenses.delete', ['event' => $event->uuid, 'expense' => $expense->id]),
        )->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseMissing('event_expenses', ['id' => $expense->id]);
    }

    public function test_expense_validation_and_authorization(): void
    {
        [$organiser, $club, $event] = $this->scenario();

        $this->actingAs($organiser)->postJson(route('me.events.expenses.add', $event->uuid), [
            'label' => 'No amount',
        ])->assertStatus(422)->assertJsonValidationErrors('amount');

        $this->actingAs($organiser)->postJson(route('me.events.expenses.add', $event->uuid), [
            'label' => 'Negative', 'amount' => -5,
        ])->assertStatus(422)->assertJsonValidationErrors('amount');

        $member = $this->clubMember($club);
        $this->actingAs($member)->postJson(route('me.events.expenses.add', $event->uuid), [
            'label' => 'Sneaky', 'amount' => 1,
        ])->assertForbidden();

        $this->assertDatabaseCount('event_expenses', 0);
    }

    /* =====================================================================
     * Officials
     * ===================================================================== */

    public function test_officials_json_is_organiser_facing_and_lists_role_options(): void
    {
        [$organiser, $club, $event] = $this->scenario();

        $response = $this->actingAs($organiser)->getJson(route('me.events.officials', $event->uuid));
        $response->assertOk()->assertJson(['success' => true]);

        $member = $this->clubMember($club);
        $this->actingAs($member)->getJson(route('me.events.officials', $event->uuid))
            ->assertForbidden();
    }

    public function test_store_official_appoints_someone_and_the_appointment_is_organiser_only(): void
    {
        [$organiser, $club, $event] = $this->scenario();
        $weigher = $this->clubMember($club, ['full_name' => 'Scales Person']);
        // A non-mat role grants ACCESS, so the appointee must be a member of the
        // host club — `joinClub` writes the memberships row storeOfficial checks.
        $this->joinClub($weigher, $club);

        $this->actingAs($organiser)->postJson(route('me.events.officials.store', $event->uuid), [
            'user_id' => $weigher->id,
            'role' => EventOfficial::ROLE_WEIGH_IN,
            'compensation' => EventOfficial::COMP_VOLUNTEER,
        ])->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('event_officials', [
            'event_id' => $event->id,
            'user_id' => $weigher->id,
            'role' => EventOfficial::ROLE_WEIGH_IN,
            'compensation' => EventOfficial::COMP_VOLUNTEER,
            'assigned_by' => $organiser->id,
        ]);

        // Appointing the same person to the same role twice is refused, not duplicated.
        $this->actingAs($organiser)->postJson(route('me.events.officials.store', $event->uuid), [
            'user_id' => $weigher->id,
            'role' => EventOfficial::ROLE_WEIGH_IN,
            'compensation' => EventOfficial::COMP_VOLUNTEER,
        ])->assertStatus(422)->assertJson(['success' => false]);

        $intruder = $this->clubMember($club, ['full_name' => 'Intruder']);
        $this->actingAs($intruder)->postJson(route('me.events.officials.store', $event->uuid), [
            'user_id' => $intruder->id,
            'role' => EventOfficial::ROLE_WEIGH_IN,
            'compensation' => EventOfficial::COMP_VOLUNTEER,
        ])->assertForbidden();
    }

    /**
     * A PAID appointment must state its fee — it becomes a line in the event's
     * P&L, so "paid, amount unknown" is refused.
     */
    public function test_store_official_requires_a_fee_when_the_appointment_is_paid(): void
    {
        [$organiser, $club, $event] = $this->scenario();
        $official = $this->clubMember($club, ['full_name' => 'Paid Official']);
        $this->joinClub($official, $club);

        $this->actingAs($organiser)->postJson(route('me.events.officials.store', $event->uuid), [
            'user_id' => $official->id,
            'role' => EventOfficial::ROLE_WEIGH_IN,
            'compensation' => EventOfficial::COMP_PAID,
        ])->assertStatus(422)->assertJsonValidationErrors('fee');

        $this->assertDatabaseCount('event_officials', 0);
    }

    /**
     * A role that grants ACCESS must come from the host club. A stranger with a
     * valid user id is refused with 422 — a data answer, not an authz one.
     */
    public function test_store_official_refuses_a_non_member_for_an_access_granting_role(): void
    {
        [$organiser, , $event] = $this->scenario();
        $stranger = $this->createUser(['full_name' => 'Outside Person']);

        $this->actingAs($organiser)->postJson(route('me.events.officials.store', $event->uuid), [
            'user_id' => $stranger->id,
            'role' => EventOfficial::ROLE_PAYMENTS,
            'compensation' => EventOfficial::COMP_VOLUNTEER,
        ])->assertStatus(422)->assertJson(['success' => false]);

        $this->assertDatabaseCount('event_officials', 0);
    }

    /**
     * An appointed weigh-in official reaches the CONSOLE (they have a job
     * there) but never the money or the danger zone.
     */
    public function test_an_appointed_official_reaches_the_console_without_finance_or_actions(): void
    {
        [$organiser, $club, $event] = $this->scenario();
        $official = $this->clubMember($club, ['full_name' => 'Scales Person']);

        EventOfficial::create([
            'event_id' => $event->id,
            'user_id' => $official->id,
            'role' => EventOfficial::ROLE_WEIGH_IN,
            'name' => $official->full_name,
        ]);

        $response = $this->asDesktop($official)->get(route('me.events.manage', $event->uuid));
        $response->assertOk();
        $response->assertViewHas('canManage', false);
        $response->assertViewHas('canOfficiate', true);
        $response->assertViewHas('canWeigh', true);
        $response->assertViewHas('canPay', false);
        // Money and the danger zone are the organiser's alone.
        $response->assertViewHas('finance', null);
        $response->assertViewHas('actions', []);
        $response->assertViewHas('screens', null);
        $response->assertViewHas('cameras', null);

        // …and they still cannot start, cancel or delete the event.
        $this->actingAs($official)->postJson(route('me.events.start', $event->uuid))->assertForbidden();
        $this->actingAs($official)->patchJson(route('me.events.cancel-event', $event->uuid))->assertForbidden();
        $this->actingAs($official)->deleteJson(route('me.events.destroy', $event->uuid))->assertForbidden();
    }

    /* =====================================================================
     * The draw — arrange / clear
     * ===================================================================== */

    public function test_arrange_bracket_is_denied_to_a_member_who_is_neither_organiser_nor_jury(): void
    {
        [, $club, $event] = $this->scenario();
        [$category] = $this->drawnDivision($event, $club);
        $member = $this->clubMember($club);

        $this->actingAs($member)->putJson(route('me.events.bracket.arrange', $event->uuid), [
            'category_id' => $category->id,
            'from' => ['type' => 'bench', 'competitor_id' => 1],
            'to' => ['type' => 'slot', 'match_id' => 1, 'side' => 'a'],
        ])->assertForbidden();
    }

    public function test_arrange_bracket_validation_rejects_an_unknown_slot_vocabulary(): void
    {
        [$organiser, $club, $event] = $this->scenario();
        [$category] = $this->drawnDivision($event, $club);

        $this->actingAs($organiser)->putJson(route('me.events.bracket.arrange', $event->uuid), [
            'category_id' => $category->id,
            'from' => ['type' => 'nowhere'],
            'to' => ['type' => 'slot', 'side' => 'c'],
        ])->assertStatus(422)->assertJsonValidationErrors(['from.type', 'to.side']);
    }

    /**
     * The draw is final from the first bout — for EVERYONE, including the
     * organiser. The refusal is a 422 (the action is withdrawn), never a 403
     * (they do hold the permission). Pinned because the distinction is
     * deliberate and easy to lose in a move.
     */
    public function test_arrange_bracket_is_withdrawn_with_422_once_the_event_has_started(): void
    {
        [$organiser, $club, $event] = $this->scenario();
        [$category] = $this->drawnDivision($event, $club);

        $this->actingAs($organiser)->postJson(route('me.events.start', $event->uuid))->assertOk();

        $match = EventMatch::where('event_id', $event->id)->orderBy('match_no')->firstOrFail();

        $this->actingAs($organiser)->putJson(route('me.events.bracket.arrange', $event->uuid), [
            'category_id' => $category->id,
            'from' => ['type' => 'slot', 'match_id' => $match->id, 'side' => 'a'],
            'to' => ['type' => 'bench'],
        ])->assertStatus(422)->assertJson(['success' => false]);
    }

    public function test_clear_bracket_empties_a_division_onto_the_bench_for_the_organiser(): void
    {
        [$organiser, $club, $event] = $this->scenario();
        [$category] = $this->drawnDivision($event, $club);

        $this->assertGreaterThan(0, EventMatch::where('event_id', $event->id)->count());

        $this->actingAs($organiser)
            ->putJson(route('me.events.bracket.clear', $event->uuid), ['category_id' => $category->id])
            ->assertOk()->assertJson(['success' => true]);

        // OUTPUT, not implementation: no competitor is left standing in a slot.
        $this->assertSame(
            0,
            EventMatch::where('event_id', $event->id)
                ->where(fn ($q) => $q->whereNotNull('a_competitor_id')->orWhereNotNull('b_competitor_id'))
                ->count(),
        );
    }

    public function test_clear_bracket_is_denied_to_a_non_organiser(): void
    {
        [, $club, $event] = $this->scenario();
        [$category] = $this->drawnDivision($event, $club);
        $member = $this->clubMember($club);

        $this->actingAs($member)
            ->putJson(route('me.events.bracket.clear', $event->uuid), ['category_id' => $category->id])
            ->assertForbidden();
    }

    public function test_save_category_refuses_a_division_belonging_to_another_event(): void
    {
        [$organiser, $club, $event] = $this->scenario();
        $other = $this->championship($club, $organiser, ['title' => 'Other Cup']);
        $foreign = EventCategory::create([
            'event_id' => $other->id, 'name' => 'Foreign division', 'sort_order' => 1,
        ]);

        $this->actingAs($organiser)->putJson(
            route('me.events.category.save', ['event' => $event->uuid, 'category' => $foreign->id]),
            ['status' => 'draft', 'matches' => []],
        )->assertNotFound();
    }

    /* =====================================================================
     * Run-day read screens
     * ===================================================================== */

    public function test_next_up_answers_json_or_a_device_split_view(): void
    {
        [$organiser, $club, $event] = $this->scenario();
        $this->drawnDivision($event, $club);

        $json = $this->actingAs($organiser)->getJson(route('me.events.next-up', $event->uuid));
        $json->assertOk()->assertJson(['success' => true]);
        $this->assertSame($event->uuid, $json->json('e.key'));
        $this->assertSame($event->title, $json->json('e.title'));
        $this->assertArrayHasKey('mine', $json->json());
        $this->assertArrayHasKey('squad', $json->json());

        $this->asDesktop($organiser)->get(route('me.events.next-up', $event->uuid))
            ->assertOk()->assertViewIs('personal.desktop.event-next-up');
        $this->asMobile($organiser)->get(route('me.events.next-up', $event->uuid))
            ->assertOk()->assertViewIs('personal.mobile.event-next-up');
    }

    /** The venue board is deliberately impersonal — and NOT device-split. */
    public function test_board_is_one_shared_view_and_carries_nothing_about_the_viewer(): void
    {
        [$organiser, $club, $event] = $this->scenario();
        $this->drawnDivision($event, $club);

        $view = $this->asMobile($organiser)->get(route('me.events.board', $event->uuid));
        $view->assertOk()->assertViewIs('personal.event-board');

        $payload = $this->actingAs($organiser)->getJson(route('me.events.board', $event->uuid));
        $payload->assertOk()->assertJson(['success' => true]);
        // Only the event's identity and the mats — nothing tied to a viewer.
        $this->assertSame(['success', 'e', 'mats'], array_keys($payload->json()));
        $this->assertSame(['key', 'title'], array_keys($payload->json('e')));
    }

    public function test_board_and_next_up_are_denied_to_someone_the_event_does_not_reach(): void
    {
        [, , $event] = $this->scenario();
        $stranger = $this->createUser();

        $this->actingAs($stranger)->getJson(route('me.events.board', $event->uuid))->assertForbidden();
        $this->actingAs($stranger)->getJson(route('me.events.next-up', $event->uuid))->assertForbidden();
    }

    public function test_gallery_data_is_the_same_shelves_as_json(): void
    {
        [$organiser, $club, $event] = $this->scenario();
        $this->drawnDivision($event, $club);
        $this->filmFirstBout($event);

        $response = $this->actingAs($organiser)->getJson(route('me.events.gallery.data', $event->uuid));
        $response->assertOk();

        // Nothing about where the file lives may reach the client.
        $this->assertLeaksNothingSensitive($response->getContent());

        $this->actingAs($this->createUser())
            ->getJson(route('me.events.gallery.data', $event->uuid))
            ->assertForbidden();
    }

    /* =====================================================================
     * The verification desk
     * ===================================================================== */

    public function test_verify_screen_is_one_shared_view_reachable_by_the_organiser_only(): void
    {
        [$organiser, $club, $event] = $this->scenario();

        $this->asDesktop($organiser)->get(route('me.events.verify', $event->uuid))
            ->assertOk()->assertViewIs('personal.event-verification');

        $member = $this->clubMember($club);
        $this->asDesktop($member)->get(route('me.events.verify', $event->uuid))
            ->assertRedirect('/');
    }

    public function test_people_and_officiating_screens_are_shared_views(): void
    {
        [$organiser, $club, $event] = $this->scenario();
        $this->drawnDivision($event, $club);

        $this->asDesktop($organiser)->get(route('me.events.people', $event->uuid))
            ->assertOk()->assertViewIs('personal.event-people');

        $this->asMobile($organiser)->get(route('me.events.people', $event->uuid))
            ->assertOk()->assertViewIs('personal.event-people');

        $this->asDesktop($organiser)->get(route('me.events.officiating', $event->uuid))
            ->assertOk()->assertViewIs('personal.event-officials');
    }

    public function test_entry_roster_and_claims_are_json_and_scoped(): void
    {
        [$organiser, , $event] = $this->scenario();

        $this->actingAs($organiser)->getJson(route('me.events.entry-roster', $event->uuid))->assertOk();
        $this->actingAs($organiser)->getJson(route('me.events.claims', $event->uuid))->assertOk();

        $stranger = $this->createUser();
        $this->actingAs($stranger)->getJson(route('me.events.claims', $event->uuid))->assertForbidden();
    }
    /**
     * The other half of the setUp() note: with the branded surface ON — which
     * is how a championship is actually served — a DESKTOP browser gets the
     * MOBILE blade, because the event is one app at every width.
     *
     * Pinned here so that turning the dressing off for the rest of this file
     * can never quietly become "the split is back".
     */
    public function test_a_branded_championship_serves_the_mobile_blade_at_every_width(): void
    {
        config(['events.branded_surface' => true]);

        [$organiser, , $event] = $this->scenario();

        foreach ([
            'me.events.next-up' => 'personal.mobile.event-next-up',
        ] as $name => $view) {
            $this->asDesktop($organiser)->get(route($name, $event->uuid))
                ->assertOk()
                ->assertViewIs($view);
        }
    }
}
