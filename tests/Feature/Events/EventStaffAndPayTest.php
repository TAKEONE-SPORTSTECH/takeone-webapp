<?php

namespace Tests\Feature\Events;

use App\Models\ClubEvent;
use App\Models\EventExpense;
use App\Models\EventOfficial;
use App\Models\EventParticipantBan;
use App\Clubs\Models\Tenant;
use App\Members\Models\User;
use Tests\TestCase;

/**
 * The event page serves four audiences, and they must not see the same thing.
 *
 * Two halves here:
 *   • what a competitor is NOT shown (moderation data, other people's money
 *     and body weight)
 *   • officiating as volunteer vs paid, and a paid appointment landing in the
 *     event's P&L by itself
 */
class EventStaffAndPayTest extends TestCase
{
    /**
     * The page's own gate payload, in a form a test can ask questions of.
     *
     * The desk's data reaches the browser through `@js()`, which escapes every
     * quote as `\u0022` and every slash as `\/`. Asserting against the raw HTML
     * without undoing that is how the two tests below used to pass for the
     * wrong reason: `'"weight"'` never matched the payload at all — it matched
     * the markup attribute `data-ec="weight"` on an empty hidden span, so the
     * assertion held whether or not a body weight was actually being handed to
     * the cashier. (It was.)
     */
    private function payload(string $body): string
    {
        return str_replace(['\u0022', '\\/'], ['"', '/'], $body);
    }

    private function clubFor(User $user, array $attrs = []): Tenant
    {
        $club = $this->createClub($user, array_merge(['country' => 'BH'], $attrs));
        $user->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);

        return $club;
    }

    private function event(Tenant $club, User $organiser): ClubEvent
    {
        return ClubEvent::create([
            'tenant_id' => $club->id, 'created_by' => $organiser->id,
            'title' => 'Spring Open', 'event_type' => 'championship', 'sport' => 'taekwondo',
            'scope' => 'internal', 'status' => 'active', 'is_archived' => false,
            'date' => now()->addWeeks(2)->toDateString(),
            'start_time' => '09:00', 'end_time' => '17:00',
        ]);
    }

    /* ===================== Who sees what ===================== */

    public function test_a_competitor_is_not_shown_who_is_blocked(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->event($club, $organiser);

        $blocked = $this->createUser(['full_name' => 'Blocked Person']);
        EventParticipantBan::create([
            'event_id' => $event->id, 'tenant_id' => $club->id,
            'user_id' => $blocked->id, 'scope' => 'event',
        ]);

        $member = $this->createUser();
        $member->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);

        // The blocked TAB was already gated — the names were still serialised
        // into the page for everyone, which View Source reveals.
        $this->actingAs($member->fresh())
            ->get("/me/events/{$event->uuid}/people")
            ->assertOk()
            ->assertDontSee('Blocked Person');
    }

    /**
     * ⚠️ RECORDS A GAP, not a satisfying rule.
     *
     * This used to assert that the organiser reads the blocked list on the
     * verification desk while a competitor cannot. The desk went on 2026-09-03
     * and its blocked tab went with it: `me.events.ban.lift` still exists and
     * has no screen behind it, so nobody — organiser included — can see or lift
     * a ban from the entry list today.
     *
     * So what is pinned here is the half that is a SECURITY rule and must hold
     * however the UI is rebuilt: a moderated member's name is not in this
     * page's source for anybody. It leaked until 2026-09-15, serialised into
     * the roster partial's client-side search index for a tab the page does not
     * render.
     */
    public function test_nobody_reads_the_blocked_list_from_the_entry_list(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->event($club, $organiser);

        $blocked = $this->createUser(['full_name' => 'Blocked Person']);
        EventParticipantBan::create([
            'event_id' => $event->id, 'tenant_id' => $club->id,
            'user_id' => $blocked->id, 'scope' => 'event',
        ]);

        $this->actingAs($organiser->fresh())
            ->get("/me/events/{$event->uuid}/people")
            ->assertOk()
            ->assertDontSee('Blocked Person');
    }

    /* ============ One roster, and what each role may do to it ============ */

    /**
     * Enter a competitor whose fee has been paid but not yet checked off, so
     * both gates on the roster row have something real to show.
     */
    private function entrant(ClubEvent $event, Tenant $club, string $name): User
    {
        $athlete = $this->createUser(['full_name' => $name]);
        $athlete->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);

        \App\Models\ClubEventRegistration::create([
            'event_id' => $event->id, 'tenant_id' => $club->id, 'user_id' => $athlete->id,
            'role' => 'participant', 'status' => 'joined',
            'weight' => 55, 'paid' => false,
            'payment_proof' => 'payment-proofs/whatever.jpg',
            'registered_at' => now(),
        ]);

        return $athlete;
    }

    public function test_the_entry_list_opens_the_desk_for_the_organiser(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->event($club, $organiser);

        // This URL is linked from the event screen and gets bookmarked, so it
        // must open rather than bounce anywhere. It used to be /verify, a desk
        // of its own; since 2026-09-03 the gates open from a person's card on
        // the entry list and /verify is gone — the write endpoints under it are
        // unchanged and still carry that prefix.
        $this->actingAs($organiser->fresh())
            ->get("/me/events/{$event->uuid}/people")
            ->assertOk();
    }

    public function test_a_competitor_gets_no_verification_controls_on_the_roster(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->event($club, $organiser);
        $this->entrant($event, $club, 'Rival Athlete');

        $onlooker = $this->createUser();
        $onlooker->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);

        // Same list, no gates: nothing to press, and — the part that matters —
        // no registration id and no link to anyone's bank receipt.
        $this->actingAs($onlooker->fresh())
            ->get("/me/events/{$event->uuid}/people")
            ->assertOk()
            ->assertSee('Rival Athlete')
            ->assertDontSee('reg_id')
            ->assertDontSee('/verify/', false)
            ->assertDontSee('officiating: true', false);
    }

    public function test_a_weigh_in_official_may_scale_but_never_sees_a_receipt(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->event($club, $organiser);
        $this->entrant($event, $club, 'Weighed Athlete');

        $scaler = $this->createUser();
        $scaler->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);
        EventOfficial::create([
            'event_id' => $event->id, 'user_id' => $scaler->id,
            'role' => EventOfficial::ROLE_WEIGH_IN, 'compensation' => 'volunteer',
        ]);

        $body = $this->actingAs($scaler->fresh())
            ->get("/me/events/{$event->uuid}/people")
            ->assertOk()
            ->assertSee('Weighed Athlete')
            ->getContent();

        // The scale is theirs; the money is not. The whole payment section is
        // withheld, not just its buttons — so there is no receipt link, no
        // has_proof, and no way to approve.
        $gates = $this->payload($body);

        $this->assertStringContainsString('"reg_id":', $gates, 'the desk needs an entry to sign for');
        $this->assertStringContainsString('"weight":55', $gates, 'the scale is this official\'s job');
        $this->assertStringContainsString('sheet-weight', $body);
        $this->assertStringNotContainsString('/proof', $gates);
        $this->assertStringNotContainsString('"has_proof"', $gates);
        $this->assertStringNotContainsString('event_verify_confirm_cash', $body);
        $this->assertStringNotContainsString('Confirm cash received', $body);
    }

    public function test_a_payments_official_never_receives_the_athletes_weight(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->event($club, $organiser);
        $this->entrant($event, $club, 'Paying Athlete');

        $cashier = $this->createUser();
        $cashier->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);
        EventOfficial::create([
            'event_id' => $event->id, 'user_id' => $cashier->id,
            'role' => EventOfficial::ROLE_PAYMENTS, 'compensation' => 'volunteer',
        ]);

        $body = $this->actingAs($cashier->fresh())
            ->get("/me/events/{$event->uuid}/people")
            ->assertOk()
            ->assertSee('Paying Athlete')
            ->getContent();

        // These are Kids divisions. Someone checking bank transfers has no
        // business holding another family's child's body weight — so the scale
        // section and the number behind it are both absent.
        $gates = $this->payload($body);

        $this->assertStringContainsString('"has_proof":true', $gates, 'the receipt is this official\'s job');
        $this->assertStringContainsString('/verify/', $gates);
        $this->assertStringContainsString('/proof', $gates);
        $this->assertStringNotContainsString('sheet-weight', $body);
        $this->assertStringNotContainsString('Weight on the scale', $body);
        // The point of the test: the number itself never reaches them. Asked of
        // the DECODED payload, so a weight handed over as `\u0022weight\u0022:55`
        // is caught.
        $this->assertStringNotContainsString('"weight":', $gates);
    }

    public function test_the_payments_official_gets_the_receipt(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->event($club, $organiser);
        $this->entrant($event, $club, 'Paying Athlete');

        $cashier = $this->createUser();
        $cashier->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);
        EventOfficial::create([
            'event_id' => $event->id, 'user_id' => $cashier->id,
            'role' => EventOfficial::ROLE_PAYMENTS, 'compensation' => 'volunteer',
        ]);

        $body = $this->actingAs($cashier->fresh())
            ->get("/me/events/{$event->uuid}/people")
            ->assertOk()
            ->assertSee('Paying Athlete')
            ->getContent();

        $this->assertStringContainsString('/proof', $this->payload($body));
    }

    /* ===================== Volunteer vs paid ===================== */

    public function test_a_volunteer_official_costs_the_event_nothing(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->event($club, $organiser);

        $ref = $this->createUser();
        $ref->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);

        $this->actingAs($organiser->fresh())->postJson("/me/events/{$event->uuid}/officials", [
            'user_id' => $ref->id, 'role' => 'jury', 'compensation' => 'volunteer',
        ])->assertOk();

        $this->assertSame(0, EventExpense::where('event_id', $event->id)->count());
    }

    public function test_a_paid_official_is_added_to_the_event_expenses(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->event($club, $organiser);

        $ref = $this->createUser(['full_name' => 'Sami Referee']);
        $ref->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);

        $this->actingAs($organiser->fresh())->postJson("/me/events/{$event->uuid}/officials", [
            'user_id' => $ref->id, 'role' => 'weigh_in', 'compensation' => 'paid', 'fee' => 25,
        ])->assertOk();

        $expense = EventExpense::where('event_id', $event->id)->firstOrFail();
        $this->assertSame('25.000', $expense->amount);
        $this->assertStringContainsString('Sami Referee', $expense->label);
        $this->assertTrue($expense->isSystemManaged());
    }

    public function test_a_paid_official_requires_a_fee(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->event($club, $organiser);

        $ref = $this->createUser();
        $ref->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);

        $this->actingAs($organiser->fresh())->postJson("/me/events/{$event->uuid}/officials", [
            'user_id' => $ref->id, 'role' => 'jury', 'compensation' => 'paid',
        ])->assertStatus(422)->assertJsonValidationErrors('fee');

        $this->assertSame(0, EventOfficial::count());
    }

    public function test_changing_the_fee_moves_the_line_rather_than_stacking_one(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->event($club, $organiser);

        $ref = $this->createUser();
        $ref->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);

        $this->actingAs($organiser->fresh())->postJson("/me/events/{$event->uuid}/officials", [
            'user_id' => $ref->id, 'role' => 'jury', 'compensation' => 'paid', 'fee' => 25,
        ])->assertOk();

        $official = EventOfficial::firstOrFail();

        $this->actingAs($organiser->fresh())
            ->putJson("/me/events/{$event->uuid}/officials/{$official->id}", [
                'compensation' => 'paid', 'fee' => 40,
            ])->assertOk();

        $this->assertSame(1, EventExpense::where('event_id', $event->id)->count());
        $this->assertSame('40.000', EventExpense::firstOrFail()->amount);
    }

    public function test_moving_a_paid_official_to_volunteer_removes_the_cost(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->event($club, $organiser);

        $ref = $this->createUser();
        $ref->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);

        $this->actingAs($organiser->fresh())->postJson("/me/events/{$event->uuid}/officials", [
            'user_id' => $ref->id, 'role' => 'jury', 'compensation' => 'paid', 'fee' => 25,
        ])->assertOk();

        $official = EventOfficial::firstOrFail();

        $this->actingAs($organiser->fresh())
            ->putJson("/me/events/{$event->uuid}/officials/{$official->id}", [
                'compensation' => 'volunteer',
            ])->assertOk();

        $this->assertSame(0, EventExpense::where('event_id', $event->id)->count());
        $this->assertNull($official->fresh()->fee);
    }

    public function test_unappointing_a_paid_official_removes_the_cost(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->event($club, $organiser);

        $ref = $this->createUser();
        $ref->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);

        $this->actingAs($organiser->fresh())->postJson("/me/events/{$event->uuid}/officials", [
            'user_id' => $ref->id, 'role' => 'jury', 'compensation' => 'paid', 'fee' => 25,
        ])->assertOk();

        $official = EventOfficial::firstOrFail();

        $this->actingAs($organiser->fresh())
            ->deleteJson("/me/events/{$event->uuid}/officials/{$official->id}")
            ->assertOk();

        $this->assertSame(0, EventExpense::where('event_id', $event->id)->count());
    }

    public function test_an_officials_fee_cannot_be_hand_deleted_from_the_ledger(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->event($club, $organiser);

        $ref = $this->createUser();
        $ref->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);

        $this->actingAs($organiser->fresh())->postJson("/me/events/{$event->uuid}/officials", [
            'user_id' => $ref->id, 'role' => 'jury', 'compensation' => 'paid', 'fee' => 25,
        ])->assertOk();

        $expense = EventExpense::firstOrFail();

        // Deleting it here would drop a real cost while the person is still
        // recorded as paid — the ledger must not be able to drift.
        $this->actingAs($organiser->fresh())
            ->deleteJson("/me/events/{$event->uuid}/expenses/{$expense->id}")
            ->assertStatus(422);

        $this->assertSame(1, EventExpense::count());
    }

    public function test_a_non_organiser_cannot_change_what_an_official_is_paid(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->event($club, $organiser);

        $ref = $this->createUser();
        $ref->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);

        $this->actingAs($organiser->fresh())->postJson("/me/events/{$event->uuid}/officials", [
            'user_id' => $ref->id, 'role' => 'jury', 'compensation' => 'paid', 'fee' => 25,
        ])->assertOk();

        $official = EventOfficial::firstOrFail();

        $this->actingAs($ref->fresh())
            ->putJson("/me/events/{$event->uuid}/officials/{$official->id}", [
                'compensation' => 'paid', 'fee' => 9999,
            ])->assertForbidden();

        $this->assertSame('25.000', EventExpense::firstOrFail()->amount);
    }

    /**
     * Appointing somebody the event's ORGANISER hands them this event, and
     * nothing beyond it.
     *
     * This test used to assert the opposite — that the organiser role was a pay
     * grade and conferred nothing. It widened deliberately on 2026-09-03
     * (EventAccess::canManage, whose docblock records why): a club owner who
     * could not open their own club's event console had to be appointed to it
     * by somebody else first, and "this person runs it too" is exactly what the
     * appointment means. So the test now pins the widening — and, more usefully,
     * pins its EDGE: the grant is scoped to the one event it was made on.
     */
    public function test_the_organiser_role_runs_this_event_and_no_other(): void
    {
        $organiser = $this->createUser();
        $club = $this->clubFor($organiser);
        $event = $this->event($club, $organiser);
        $other = $this->event($club, $organiser);

        $helper = $this->createUser();
        $helper->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);

        $this->actingAs($organiser->fresh())->postJson("/me/events/{$event->uuid}/officials", [
            'user_id' => $helper->id, 'role' => 'organiser', 'compensation' => 'paid', 'fee' => 50,
        ])->assertOk();

        $access = app(\App\Events\Support\EventAccess::class);
        $helper = $helper->fresh();

        $this->assertTrue($access->canManage($event, $helper));
        $this->assertTrue($access->canVerifyWeighIn($event, $helper));
        $this->assertTrue($access->canVerifyPayments($event, $helper));

        // The edge, which is the part worth a test: one appointment, one event.
        $this->assertFalse($access->canManage($other, $helper), 'an appointment must not travel to the next event');
        $this->assertFalse($access->canArrange($other, $helper));

        // …and the fee is still on the books.
        $this->assertSame('50.000', EventExpense::firstOrFail()->amount);
    }
}
