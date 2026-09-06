<?php

namespace Tests\Feature\Architecture;

use App\Clubs\Models\ClubActivity;
use App\Models\ClubEvent;
use App\Models\ClubEventRegistration;
use App\Clubs\Models\ClubInstructor;
use App\Models\ClubMemberSubscription;
use App\Clubs\Models\ClubPackage;
use App\Members\Models\Membership;
use App\Clubs\Models\Tenant;
use App\Members\Models\User;
use Database\Factories\ClubEventRegistrationFactory;
use Tests\TestCase;

/**
 * Architecture characterization: the core model factories actually work.
 *
 * Tests used to hand-build every fixture with `Model::create([...])`, which
 * made new coverage expensive to write and quietly rotted whenever a NOT-NULL
 * column, an enum vocabulary or a check constraint moved. The factories are the
 * shared fixture layer; this file is what proves they still produce a model the
 * database will accept.
 *
 * Two passes per factory:
 *   - ->make() with the foreign keys supplied, so nothing is written at all
 *     (a nested factory in a definition WOULD create its parent row, even on
 *     make(), so the FKs are passed explicitly here).
 *   - ->create() with no overrides, so the factory has to stand up its own
 *     parents and satisfy every constraint on the table.
 */
class FactoriesResolveTest extends TestCase
{
    public function test_factories_make_in_memory_without_touching_the_database(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $package = ClubPackage::factory()->create(['tenant_id' => $tenant->id]);
        $event = ClubEvent::factory()->create(['tenant_id' => $tenant->id]);

        $before = [
            'tenants' => Tenant::count(),
            'users' => User::count(),
            'club_packages' => ClubPackage::count(),
            'club_events' => ClubEvent::count(),
        ];

        $made = [
            'tenant' => Tenant::factory()->make(['owner_user_id' => $user->id]),
            'event' => ClubEvent::factory()->make(['tenant_id' => $tenant->id]),
            'membership' => Membership::factory()->make([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
            ]),
            'package' => ClubPackage::factory()->make(['tenant_id' => $tenant->id]),
            'activity' => ClubActivity::factory()->make(['tenant_id' => $tenant->id]),
            'subscription' => ClubMemberSubscription::factory()->make([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'package_id' => $package->id,
            ]),
            'registration' => ClubEventRegistrationFactory::new()->make([
                'event_id' => $event->id,
                'user_id' => $user->id,
            ]),
            'instructor' => ClubInstructor::factory()->make([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
            ]),
        ];

        foreach ($made as $label => $model) {
            $this->assertFalse($model->exists, "{$label} factory persisted on ->make()");
        }

        // Nothing was written while making them.
        $this->assertSame($before['tenants'], Tenant::count());
        $this->assertSame($before['users'], User::count());
        $this->assertSame($before['club_packages'], ClubPackage::count());
        $this->assertSame($before['club_events'], ClubEvent::count());
    }

    public function test_tenant_factory_creates_a_valid_club(): void
    {
        $tenant = Tenant::factory()->create();

        $this->assertTrue($tenant->exists);
        $this->assertDatabaseHas('tenants', ['id' => $tenant->id]);
        $this->assertNotNull($tenant->owner_user_id);
        $this->assertNotNull(User::find($tenant->owner_user_id));

        // The public club URL is /{country}/clubs/{slug} and its country
        // segment is [a-z]{2,3} — a full country name 404s.
        $this->assertMatchesRegularExpression('/^[A-Z]{2,3}$/', $tenant->country);
        $this->assertNotEmpty($tenant->slug);
        $this->assertSame($tenant->id, Tenant::where('slug', $tenant->slug)->value('id'));
    }

    public function test_club_event_factory_creates_a_valid_event(): void
    {
        $event = ClubEvent::factory()->create();

        $this->assertDatabaseHas('club_events', ['id' => $event->id]);
        $this->assertNotNull($event->tenant);
        $this->assertNotNull($event->uuid);
        $this->assertNotNull($event->date);
        $this->assertNotEmpty($event->start_time);
    }

    public function test_membership_factory_creates_a_valid_membership(): void
    {
        $membership = Membership::factory()->create();

        $this->assertDatabaseHas('memberships', ['id' => $membership->id]);
        $this->assertNotNull($membership->tenant);
        $this->assertNotNull($membership->user);
        $this->assertSame('active', $membership->status);
    }

    public function test_club_package_factory_creates_a_valid_package(): void
    {
        $package = ClubPackage::factory()->create();

        $this->assertDatabaseHas('club_packages', ['id' => $package->id]);
        $this->assertNotNull($package->tenant);
        // DB check constraints on club_packages.
        $this->assertContains($package->type, ['single', 'multi']);
        $this->assertContains($package->gender, ['mixed', 'male', 'female']);
        $this->assertGreaterThan(0, (float) $package->price);
    }

    public function test_club_activity_factory_creates_a_valid_activity(): void
    {
        $activity = ClubActivity::factory()->create();

        $this->assertDatabaseHas('club_activities', ['id' => $activity->id]);
        $this->assertNotNull($activity->tenant);
        $this->assertNotEmpty($activity->name);
    }

    public function test_club_member_subscription_factory_creates_a_valid_subscription(): void
    {
        $subscription = ClubMemberSubscription::factory()->create();

        $this->assertDatabaseHas('club_member_subscriptions', ['id' => $subscription->id]);
        $this->assertNotNull($subscription->tenant);
        $this->assertNotNull($subscription->user);
        $this->assertNotNull($subscription->package);
        // The package must belong to the same club as the subscription.
        $this->assertSame($subscription->tenant_id, $subscription->package->tenant_id);
        // booted() derives the dedup key for active/pending rows.
        $this->assertNotNull($subscription->active_key);
        $this->assertNotNull($subscription->is_test);

        $paid = ClubMemberSubscription::factory()->paid()->create();
        $this->assertSame('paid', $paid->payment_status);

        // An expired row frees the dedup slot, so active_key is null.
        $expired = ClubMemberSubscription::factory()->expired()->create();
        $this->assertNull($expired->active_key);
    }

    public function test_club_event_registration_factory_creates_a_valid_registration(): void
    {
        // ClubEventRegistration does not use HasFactory, so the factory is
        // instantiated directly rather than via Model::factory().
        $registration = ClubEventRegistrationFactory::new()->create();

        $this->assertInstanceOf(ClubEventRegistration::class, $registration);
        $this->assertDatabaseHas('club_event_registrations', ['id' => $registration->id]);
        $this->assertNotNull($registration->event);
        $this->assertNotNull($registration->user);
        $this->assertSame('participant', $registration->role);
        $this->assertSame('individual', $registration->entry_channel);
    }

    public function test_club_instructor_factory_creates_a_valid_instructor(): void
    {
        $instructor = ClubInstructor::factory()->create();

        $this->assertDatabaseHas('club_instructors', ['id' => $instructor->id]);
        $this->assertNotNull($instructor->tenant);
        $this->assertNotNull($instructor->user);
        $this->assertContains($instructor->staff_type, ClubInstructor::STAFF_TYPES);
        $this->assertFalse($instructor->isPaid());

        $paid = ClubInstructor::factory()->paid()->create();
        $this->assertTrue($paid->isPaid());
    }
}
