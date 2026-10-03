<?php

namespace Tests\Feature\Events;

use App\Clubs\Models\Business;
use App\Clubs\Models\Tenant;
use App\Events\Models\ClubEvent;
use App\Events\Models\ClubEventRegistration;
use App\Events\Support\DeskSignInCode;
use App\Federations\Models\Federation;
use App\Members\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The desk sign-in code never reaches an account with power over others
 * (security fix, 2026-10-03).
 *
 * The organiser who issues a desk code SEES the code and the phone it is for,
 * so redeeming it is in the organiser's hands — which is fine for an athlete
 * with no phone of their own, and an account takeover for anybody who runs
 * something. The protected list used to be "runs a club or an event"; a chain
 * owner, a federation owner and federation staff were missing from it.
 */
class DeskSignInCodePrivilegeTest extends TestCase
{
    use RefreshDatabase;

    private User $organiser;

    private Tenant $club;

    private ClubEvent $event;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();

        $this->organiser = $this->createUser();
        $this->club = $this->createClub($this->organiser);
        $this->event = ClubEvent::create([
            'tenant_id' => $this->club->id, 'created_by' => $this->organiser->id,
            'title' => 'Desk Cup', 'event_type' => 'championship', 'sport' => 'taekwondo',
            'scope' => 'internal', 'status' => 'active', 'is_archived' => false,
            'date' => now()->addWeek()->toDateString(), 'end_date' => now()->addWeek()->toDateString(),
            'start_time' => '09:00:00', 'end_time' => '17:00:00',
        ]);
    }

    private function athlete(): User
    {
        return $this->createUser(['mobile' => ['code' => '+973', 'number' => (string) random_int(30000000, 39999999)]]);
    }

    private function entry(User $person): ClubEventRegistration
    {
        return ClubEventRegistration::create([
            'event_id' => $this->event->id, 'user_id' => $person->id, 'status' => 'registered', 'role' => 'participant',
        ]);
    }

    private function issueFor(User $person): array
    {
        return app(DeskSignInCode::class)->issue($this->event, $this->entry($person), $this->organiser->fresh());
    }

    public function test_an_ordinary_athlete_still_gets_a_code(): void
    {
        $this->assertTrue($this->issueFor($this->athlete())['ok']);
    }

    public function test_a_chain_owner_is_protected(): void
    {
        $person = $this->athlete();
        Business::create(['owner_user_id' => $person->id, 'name' => 'Chain', 'slug' => 'chain-'.uniqid(), 'status' => Business::STATUS_APPROVED]);

        $this->assertFalse($this->issueFor($person)['ok']);
    }

    public function test_a_federation_owner_is_protected(): void
    {
        $person = $this->athlete();
        Federation::create(['owner_user_id' => $person->id, 'name' => 'Fed', 'country' => 'BH', 'sport' => 'taekwondo', 'status' => 'approved']);

        $this->assertFalse($this->issueFor($person)['ok']);
    }

    public function test_federation_staff_are_protected(): void
    {
        $head = $this->createUser();
        $fed = Federation::create(['owner_user_id' => $head->id, 'name' => 'Fed', 'country' => 'BH', 'sport' => 'taekwondo', 'status' => 'approved']);
        $person = $this->athlete();
        DB::table('federation_staff')->insert([
            'federation_id' => $fed->id, 'user_id' => $person->id, 'role' => 'Secretary',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertFalse($this->issueFor($person)['ok']);
    }

    public function test_a_code_issued_before_someone_became_privileged_cannot_be_redeemed(): void
    {
        $person = $this->athlete();
        $issued = $this->issueFor($person);
        $this->assertTrue($issued['ok']);

        // They take over a federation between the desk and the redeem.
        Federation::create(['owner_user_id' => $person->id, 'name' => 'Fed', 'country' => 'BH', 'sport' => 'taekwondo', 'status' => 'approved']);

        $m = $person->mobile;
        $this->assertNull(app(DeskSignInCode::class)->redeem($this->event, $m['code'], $m['number'], $issued['code'], 'new-password-123'));
    }

    public function test_the_form_strictness_helper_is_unchanged(): void
    {
        // holdsPrivilegedAccess() is the security question; entersPeopleOnBehalfOfOthers()
        // still answers only "how strict is a person form", exactly as before.
        $chainOwner = $this->createUser();
        Business::create(['owner_user_id' => $chainOwner->id, 'name' => 'Chain', 'slug' => 'chain-'.uniqid(), 'status' => Business::STATUS_APPROVED]);

        $this->assertTrue($chainOwner->holdsPrivilegedAccess());
        $this->assertFalse($chainOwner->entersPeopleOnBehalfOfOthers());
    }
}
