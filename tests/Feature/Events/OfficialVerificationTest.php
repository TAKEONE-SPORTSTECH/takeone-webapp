<?php

namespace Tests\Feature\Events;

use App\Models\ClubEvent;
use App\Models\ClubEventRegistration;
use App\Models\EventCategory;
use App\Models\EventOfficial;
use App\Members\Models\HealthRecord;
use App\Clubs\Models\Tenant;
use App\Members\Models\User;
use Tests\TestCase;

/**
 * Appointed officials, and the two gates they guard.
 *
 * The rule this file exists to protect: an entry reaches the FINAL draw only
 * when a payments official has approved its money and a weigh-in official has
 * signed its weight. A member ticking "paid" and typing a weight is a claim,
 * not a verification, and claims stay in the provisional draw.
 */
class OfficialVerificationTest extends TestCase
{
    private Tenant $club;

    private User $organiser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organiser = $this->createUser(['full_name' => 'Master Kim']);
        $this->club = $this->createClub($this->organiser, ['country' => 'BH', 'currency' => 'BHD']);
        $this->organiser->memberClubs()->syncWithoutDetaching([$this->club->id => ['status' => 'active']]);
    }

    private function event(array $attrs = []): ClubEvent
    {
        return ClubEvent::create(array_merge([
            'tenant_id' => $this->club->id,
            'created_by' => $this->organiser->id,
            'title' => 'Spring Open',
            'event_type' => 'championship',
            'sport' => 'taekwondo',
            'scope' => 'internal',
            'date' => now()->addWeeks(2)->toDateString(),
            'end_date' => now()->addWeeks(2)->toDateString(),
            'start_time' => '09:00',
            'end_time' => '17:00',
            'status' => 'active',
            'is_archived' => false,
        ], $attrs));
    }

    private function member(string $name): User
    {
        $user = $this->createUser([
            'full_name' => $name,
            'gender' => 'Male',
            'birthdate' => now()->subYears(25)->toDateString(),
        ]);
        $user->memberClubs()->syncWithoutDetaching([$this->club->id => ['status' => 'active']]);
        HealthRecord::create(['user_id' => $user->id, 'weight' => 57, 'recorded_at' => now()]);

        return $user->fresh();
    }

    private function entry(ClubEvent $event, EventCategory $category, User $athlete, array $attrs = []): ClubEventRegistration
    {
        return ClubEventRegistration::create(array_merge([
            'event_id' => $event->id,
            'user_id' => $athlete->id,
            'category_id' => $category->id,
            'role' => 'participant',
        ], $attrs));
    }

    private function division(ClubEvent $event): EventCategory
    {
        return EventCategory::create([
            'event_id' => $event->id, 'name' => 'Senior Men -58 kg', 'sort_order' => 1,
            'status' => 'enrolling',
        ]);
    }

    /* ---------------- Appointing ---------------- */

    /*
     * CHANGED — appointing now states how the official is compensated
     * (`volunteer` | `paid`, and a `fee` when paid), because a paid appointment
     * posts a matching event expense (EventOfficial::booted). `compensation` is
     * REQUIRED by PersonalEventController::storeOfficial(); these appointments
     * are volunteers, which is what the file was implicitly testing before the
     * field existed. What each test proves is unchanged.
     */

    public function test_one_person_can_hold_two_different_jobs(): void
    {
        $event = $this->event();
        $treasurer = $this->member('Sara Ali');

        foreach ([EventOfficial::ROLE_PAYMENTS, EventOfficial::ROLE_WEIGH_IN] as $role) {
            $this->actingAs($this->organiser)
                ->postJson("/me/events/{$event->uuid}/officials", [
                    'user_id' => $treasurer->id, 'role' => $role, 'compensation' => 'volunteer',
                ])
                ->assertOk()->assertJson(['success' => true]);
        }

        $this->assertSame(2, $event->officials()->where('user_id', $treasurer->id)->count());
    }

    public function test_the_same_job_cannot_be_given_twice(): void
    {
        $event = $this->event();
        $official = $this->member('Sara Ali');

        $this->actingAs($this->organiser)
            ->postJson("/me/events/{$event->uuid}/officials", [
                'user_id' => $official->id, 'role' => 'payments', 'compensation' => 'volunteer',
            ])
            ->assertOk();

        $this->actingAs($this->organiser)
            ->postJson("/me/events/{$event->uuid}/officials", [
                'user_id' => $official->id, 'role' => 'payments', 'compensation' => 'volunteer',
            ])
            ->assertStatus(422);

        $this->assertSame(1, $event->officials()->count());
    }

    public function test_withdrawing_one_appointment_leaves_the_other_standing(): void
    {
        $event = $this->event();
        $official = $this->member('Sara Ali');

        foreach ([EventOfficial::ROLE_PAYMENTS, EventOfficial::ROLE_WEIGH_IN] as $role) {
            $this->actingAs($this->organiser)
                ->postJson("/me/events/{$event->uuid}/officials", [
                    'user_id' => $official->id, 'role' => $role, 'compensation' => 'volunteer',
                ])
                ->assertOk();
        }

        $payments = $event->officials()->where('role', EventOfficial::ROLE_PAYMENTS)->first();

        $this->actingAs($this->organiser)
            ->deleteJson("/me/events/{$event->uuid}/officials/{$payments->id}")
            ->assertOk();

        $this->assertSame(
            [EventOfficial::ROLE_WEIGH_IN],
            $event->officials()->pluck('role')->all(),
        );
    }

    /* ---------------- Who may verify what ---------------- */

    public function test_a_weigh_in_official_signs_a_weight_but_cannot_approve_money(): void
    {
        $event = $this->event();
        $category = $this->division($event);
        $entry = $this->entry($event, $category, $this->member('Athlete One'));

        $scale = $this->member('Scale Official');
        EventOfficial::create([
            'event_id' => $event->id, 'user_id' => $scale->id,
            'role' => EventOfficial::ROLE_WEIGH_IN, 'assigned_by' => $this->organiser->id,
        ]);

        $this->actingAs($scale)
            ->putJson("/me/events/{$event->uuid}/verify/{$entry->id}/weigh-in", ['weight' => 57.4])
            ->assertOk()->assertJson(['success' => true]);

        $entry->refresh();
        $this->assertSame(57.4, (float) $entry->weight);
        $this->assertSame($scale->id, $entry->weighed_in_by, 'the weight is signed by whoever recorded it');

        $this->actingAs($scale)
            ->putJson("/me/events/{$event->uuid}/verify/{$entry->id}/payment", ['approve' => true])
            ->assertForbidden();
    }

    public function test_a_payments_official_approves_and_can_revoke(): void
    {
        $event = $this->event();
        $category = $this->division($event);
        $entry = $this->entry($event, $category, $this->member('Athlete One'));

        $treasurer = $this->member('Payments Official');
        EventOfficial::create([
            'event_id' => $event->id, 'user_id' => $treasurer->id,
            'role' => EventOfficial::ROLE_PAYMENTS, 'assigned_by' => $this->organiser->id,
        ]);

        $this->actingAs($treasurer)
            ->putJson("/me/events/{$event->uuid}/verify/{$entry->id}/payment", ['approve' => true])
            ->assertOk();

        $entry->refresh();
        $this->assertTrue((bool) $entry->paid);
        $this->assertSame($treasurer->id, $entry->paid_by);

        // Revoking must clear the signature too, or the entry keeps its place in
        // the final draw on an approval that was withdrawn.
        $this->actingAs($treasurer)
            ->putJson("/me/events/{$event->uuid}/verify/{$entry->id}/payment", ['approve' => false])
            ->assertOk();

        $entry->refresh();
        $this->assertFalse((bool) $entry->paid);
        $this->assertNull($entry->paid_by);
    }

    /**
     * The desk is a screen of its own again, and it is officials-only.
     *
     * "Who's joined" is the reading surface: it carries no controls for anybody,
     * so the question here is both halves — an ordinary member cannot open the
     * desk, and the list they CAN open hands them nothing to act with.
     */
    public function test_an_ordinary_member_cannot_open_the_desk_and_the_roster_has_no_controls(): void
    {
        $event = $this->event();
        $category = $this->division($event);
        $this->entry($event, $category, $this->member('Athlete One'));
        $nobody = $this->member('Just A Member');

        // A browser GET to a forbidden page is a redirect, not a 403 — see the
        // global handler in bootstrap/app.php.
        $this->actingAs($nobody)
            ->get("/me/events/{$event->uuid}/verify")
            ->assertRedirect('/');

        $this->actingAs($nobody)
            ->getJson("/me/events/{$event->uuid}/verify")
            ->assertForbidden();

        // The roster itself: no registration ids to act on, no officiating
        // wiring, and no link to anyone's proof of payment.
        $this->actingAs($nobody)
            ->get("/me/events/{$event->uuid}/people")
            ->assertOk()
            ->assertSee('Athlete One')
            ->assertDontSee('reg_id')
            ->assertDontSee('officiating: true', false)
            ->assertDontSee('openPerson(', false);
    }

    /**
     * An ORGANISER gets the same plain list as everyone else. This is the point
     * of the split: the screen does not change shape depending on who opened it.
     */
    public function test_even_an_organiser_gets_no_controls_on_the_roster(): void
    {
        $event = $this->event();
        $category = $this->division($event);
        $this->entry($event, $category, $this->member('Athlete One'));

        $this->actingAs($this->organiser)
            ->get("/me/events/{$event->uuid}/people")
            ->assertOk()
            ->assertSee('Athlete One')
            ->assertDontSee('reg_id')
            ->assertDontSee('officiating: true', false);
    }

    public function test_the_gates_render_on_the_desk_for_an_appointed_official(): void
    {
        $event = $this->event();
        $category = $this->division($event);
        $this->entry($event, $category, $this->member('Athlete One'));

        $scale = $this->member('Scale Official');
        EventOfficial::create([
            'event_id' => $event->id, 'user_id' => $scale->id,
            'role' => EventOfficial::ROLE_WEIGH_IN, 'assigned_by' => $this->organiser->id,
        ]);

        $this->actingAs($scale)
            ->get("/me/events/{$event->uuid}/verify")
            ->assertOk()
            ->assertSee('Athlete One')
            ->assertSee('officiating: true', false)
            ->assertSee('reg_id')
            // The row is one line that opens a sheet — the gates are not
            // stamped inline onto every card.
            ->assertSee('openPerson(', false)
            ->assertSee('sheet-weight', false)
            ->assertDontSee('id="w-', false);
    }

    /* ---------------- The gate itself ---------------- */

    public function test_only_verified_entries_reach_the_final_draw(): void
    {
        $event = $this->event(['date' => now()->subDay()->toDateString(), 'end_date' => now()->addDay()->toDateString()]);
        $category = $this->division($event);
        $official = $this->member('Official');

        // Signed off on both gates — this one fights.
        $verified = $this->entry($event, $category, $this->member('Verified One'), [
            'paid' => true, 'paid_by' => $official->id,
            'weight' => 57, 'weighed_in_by' => $official->id,
        ]);
        $alsoVerified = $this->entry($event, $category, $this->member('Verified Two'), [
            'paid' => true, 'paid_by' => $official->id,
            'weight' => 57, 'weighed_in_by' => $official->id,
        ]);

        // Claims their own weight and payment, nobody signed either.
        $this->entry($event, $category, $this->member('Self Declared'), [
            'paid' => true, 'weight' => 57,
        ]);
        // Paid for real, but never stepped on the scale.
        $this->entry($event, $category, $this->member('Never Weighed'), [
            'paid' => true, 'paid_by' => $official->id, 'weight' => 57,
        ]);

        app(\App\Sports\Combat\Engine\DrawEngine::class)->build($event, $category, paidOnly: true);

        $drawn = $category->matches()->pluck('a_competitor_id')
            ->merge($category->matches()->pluck('b_competitor_id'))
            ->filter()->sort()->values()->all();

        $this->assertSame(
            collect([$verified->id, $alsoVerified->id])->sort()->values()->all(),
            $drawn,
            'only the two doubly-signed entries were drawn',
        );
    }
}
