<?php

namespace Tests\Feature\Events;

use App\Events\Mail\ClubEntryCode;
use App\Events\Models\ClubEvent;
use App\Events\Models\ClubEventRegistration;
use App\Events\Models\EventClub;
use App\Members\Models\User;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * "Register your club" on a CLUB-hosted event (PlatformClubEntryDesk): the same
 * screens as a federation's invitation, backed by TakeOne clubs and the event's
 * own entries — and never a way for one club to act for another.
 */
class PlatformClubEntryTest extends TestCase
{
    private ClubEvent $event;

    protected function setUp(): void
    {
        parent::setUp();
        config(['events.club_entry' => true, 'events.club_entry_platform' => true, 'events.unified_page' => true]);
        Mail::fake();

        $host = $this->createClub($this->createUser(), ['country' => 'BH', 'club_name' => 'Host Academy']);
        $this->event = ClubEvent::create([
            'tenant_id' => $host->id, 'created_by' => $host->owner_user_id, 'title' => 'Spring Open',
            'event_type' => 'championship', 'sport' => 'karate', 'scope' => 'inter_club', 'status' => 'active',
            'is_archived' => false, 'entry_mode' => 'public',
            'date' => now()->addMonth()->toDateString(), 'start_time' => '09:00:00',
            'enrollment_ends_at' => now()->addWeeks(2)->toDateString(),
        ]);
    }

    private function url(string $verb): string
    {
        return '/me/events/'.$this->event->uuid.'/club/api/'.$verb;
    }

    /** A club with an owner whose email is on file, and one active member. */
    private function clubWithMember(string $name, string $email): array
    {
        $owner = $this->createUser(['email' => $email]);
        $club = $this->createClub($owner, ['country' => 'BH', 'club_name' => $name, 'email' => $email]);
        $member = $this->createUser(['gender' => 'Male', 'birthdate' => now()->subYears(20)->toDateString()]);
        $member->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);

        return [$club, $member];
    }

    /** Search, ask for a code, type it: the session now acts for the club. */
    private function proveClub(string $name): string
    {
        $found = $this->getJson($this->url('clubs').'?q='.urlencode($name))->assertOk();
        $key = $found->json('clubs.0.key');
        $this->assertNotNull($key, 'search found nothing: '.$found->getContent());
        $this->postJson($this->url('code'), ['club' => $key])->assertStatus(202);
        $code = null;
        Mail::assertQueued(ClubEntryCode::class, function (ClubEntryCode $m) use (&$code) { $code = $m->code; return true; });
        $this->postJson($this->url('verify'), ['club' => $key, 'code' => $code])->assertOk()->assertJsonPath('store.verifiedClub', $key);

        return $key;
    }

    private function consent(): void
    {
        $this->postJson('/me/events/'.$this->event->uuid.'/consent')->assertOk();
    }

    public function test_the_screens_open_for_a_club_event_with_its_own_store(): void
    {
        $this->get('/me/events/'.$this->event->uuid.'/rules')->assertOk()
            ->assertSee('club-entry-store.js', false)
            ->assertSee('nationalId', false);
        $html = $this->get('/me/events/'.$this->event->uuid.'/rules')->getContent();
        $this->assertMatchesRegularExpression('/nationalId(\\\\u0022|"|&quot;):false/', $html);

        config(['events.club_entry_platform' => false]);
        $this->getJson('/me/events/'.$this->event->uuid.'/rules')->assertNotFound();
    }

    public function test_a_club_proves_itself_by_code_and_enters_its_member(): void
    {
        [$club, $member] = $this->clubWithMember('Bravo Dojo', 'bravo@example.com');
        $this->proveClub('Bravo');
        $this->consent();

        $this->postJson($this->url('submit'), [
            'submissionId' => 's-1', 'clubMode' => 'existing',
            'entries' => [['athleteId' => 'u-'.$member->uuid, 'weight' => 61, 'types' => ['Entry']]],
        ])->assertOk()->assertJsonPath('added', 1);

        $reg = ClubEventRegistration::where('event_id', $this->event->id)->where('user_id', $member->id)->firstOrFail();
        $this->assertSame((int) $club->id, (int) $reg->representing_tenant_id);
        $this->assertSame('club', $reg->entry_channel);
        $this->assertSame('club', $reg->payer_type);

        // The club withdraws its own athlete.
        $this->postJson($this->url('withdraw'), ['athleteId' => 'u-'.$member->uuid])->assertOk();
        $this->assertFalse(ClubEventRegistration::where('event_id', $this->event->id)->where('user_id', $member->id)->where('role', 'participant')->exists());
    }

    public function test_a_club_cannot_enter_or_withdraw_another_clubs_athlete(): void
    {
        [, $theirs] = $this->clubWithMember('Charlie Gym', 'charlie@example.com');
        [$mine, $myMember] = $this->clubWithMember('Bravo Dojo', 'bravo@example.com');
        $this->proveClub('Bravo');
        $this->consent();

        $this->postJson($this->url('submit'), [
            'submissionId' => 's-2', 'clubMode' => 'existing',
            'entries' => [['athleteId' => 'u-'.$theirs->uuid, 'weight' => 60, 'types' => ['Entry']]],
        ])->assertOk()->assertJsonPath('added', 0);
        $this->assertFalse(ClubEventRegistration::where('event_id', $this->event->id)->where('user_id', $theirs->id)->exists());

        // Somebody else's entry: not withdrawable from this session.
        ClubEventRegistration::forceCreate(['event_id' => $this->event->id, 'user_id' => $theirs->id, 'role' => 'participant', 'status' => 'joined', 'representing_tenant_id' => null]);
        $this->postJson($this->url('withdraw'), ['athleteId' => 'u-'.$theirs->uuid])->assertNotFound();
        $this->assertTrue(ClubEventRegistration::where('event_id', $this->event->id)->where('user_id', $theirs->id)->exists());
    }

    public function test_a_wrong_code_proves_nothing_and_the_answer_never_says_whether_a_club_exists(): void
    {
        $this->clubWithMember('Bravo Dojo', 'bravo@example.com');
        $this->postJson($this->url('code'), ['contact' => 'nobody@example.com'])->assertStatus(202);
        $this->postJson($this->url('code'), ['contact' => 'bravo@example.com'])->assertStatus(202);
        $this->postJson($this->url('verify'), ['contact' => 'bravo@example.com', 'code' => '000000'])->assertStatus(422);
        $this->postJson($this->url('submit'), ['submissionId' => 's-3', 'entries' => [['name' => 'X Y']]])->assertStatus(422);
    }

    public function test_a_new_club_registers_with_a_new_athlete(): void
    {
        $this->consent();
        $this->postJson($this->url('submit'), [
            'submissionId' => 's-4', 'clubMode' => 'new',
            'club' => ['name' => 'Zeta Fight Club', 'email' => 'zeta@example.com', 'phone' => '+97333334444'],
            'entries' => [['name' => 'New Kid', 'gender' => 'Male', 'dob' => '2012-03-04', 'weight' => 40, 'nat' => 'BHR', 'types' => ['Entry']]],
        ])->assertOk()->assertJsonPath('added', 1);

        $club = EventClub::where('event_id', $this->event->id)->where('name', 'Zeta Fight Club')->firstOrFail();
        $kid = User::where('full_name', 'New Kid')->firstOrFail();
        $this->assertTrue((bool) $kid->is_unclaimed);
        $reg = ClubEventRegistration::where('event_id', $this->event->id)->where('user_id', $kid->id)->firstOrFail();
        $this->assertSame('event_club', $reg->payer_type);
        $this->assertSame((int) $club->id, (int) $reg->payer_id);
    }

    public function test_nothing_is_entered_without_accepting_the_rules_or_after_the_window(): void
    {
        [, $member] = $this->clubWithMember('Bravo Dojo', 'bravo@example.com');
        $this->proveClub('Bravo');
        $payload = ['submissionId' => 's-5', 'clubMode' => 'existing', 'entries' => [['athleteId' => 'u-'.$member->uuid, 'weight' => 60, 'types' => ['Entry']]]];

        $this->postJson($this->url('submit'), $payload)->assertStatus(422);       // no acceptance

        $this->consent();
        $this->event->forceFill(['enrollment_ends_at' => now()->subDay()->toDateString()])->save();
        $this->postJson($this->url('submit'), ['submissionId' => 's-6'] + $payload)->assertStatus(422);
        $this->assertSame(0, ClubEventRegistration::where('event_id', $this->event->id)->count());
    }

    private const PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    public function test_a_club_sends_one_proof_and_the_organiser_approves_it(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $this->event->forceFill(['participant_fee_amount' => 10])->save();
        [, $member] = $this->clubWithMember('Bravo Dojo', 'bravo@example.com');
        $this->proveClub('Bravo');
        $this->consent();
        $this->postJson($this->url('submit'), ['submissionId' => 's-p', 'clubMode' => 'existing',
            'entries' => [['athleteId' => 'u-'.$member->uuid, 'weight' => 61, 'types' => ['Entry']]]])->assertOk();

        // A file pretending to be a picture is refused by its bytes.
        $this->postJson($this->url('proof'), ['proof' => 'data:image/png;base64,'.base64_encode('<?php echo 1;')])->assertStatus(422);

        $this->postJson($this->url('proof'), ['proof' => self::PNG])->assertOk();
        $invoice = \App\Events\Models\EventEntryInvoice::sole();
        $this->assertSame('submitted', $invoice->state);
        $this->assertCount(1, $invoice->registration_ids);
        \Illuminate\Support\Facades\Storage::disk('local')->assertExists($invoice->proof_path);

        // A stranger sees nothing and decides nothing.
        $stranger = $this->createUser();
        $this->actingAs($stranger)->getJson('/me/events/'.$this->event->uuid.'/club-payments')->assertForbidden();
        $this->actingAs($stranger)->postJson('/me/events/'.$this->event->uuid.'/club-payments/'.$invoice->uuid, ['approve' => true])->assertForbidden();

        // The organiser approves: the athlete it covers is paid.
        $organiser = User::find($this->event->created_by);
        $this->actingAs($organiser)->getJson('/me/events/'.$this->event->uuid.'/club-payments')->assertOk()->assertJsonPath('payments.0.club', 'Bravo Dojo');
        $this->actingAs($organiser)->postJson('/me/events/'.$this->event->uuid.'/club-payments/'.$invoice->uuid, ['approve' => true])->assertOk();
        $this->assertSame('approved', $invoice->fresh()->state);
        $this->assertTrue((bool) ClubEventRegistration::where('event_id', $this->event->id)->where('user_id', $member->id)->value('paid'));
    }

    public function test_the_mcp_tools_let_only_a_payment_verifier_decide(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $invoice = \App\Events\Models\EventEntryInvoice::forceCreate([
            'uuid' => (string) \Illuminate\Support\Str::uuid(), 'event_id' => $this->event->id, 'payer_type' => 'club', 'payer_id' => 1,
            'payer_name' => 'Bravo Dojo', 'amount' => 10, 'currency' => 'BHD', 'state' => 'submitted', 'registration_ids' => [], 'submitted_at' => now(),
        ]);
        $call = function (User $as, string $tool, array $args) {
            $this->actingAs($as);
            $d = json_decode((string) (new $tool)->handle(new \Laravel\Mcp\Request($args))->content(), true);

            return is_array($d) ? $d : ['error' => true];
        };
        $stranger = $this->createUser();
        $this->assertArrayHasKey('error', $call($stranger, \App\Mcp\Tools\ListEventClubPaymentsTool::class, ['event' => $this->event->uuid]));
        $this->assertArrayHasKey('error', $call($stranger, \App\Mcp\Tools\DecideEventClubPaymentTool::class, ['event' => $this->event->uuid, 'payment' => $invoice->uuid, 'decision' => 'approve']));

        $organiser = User::find($this->event->created_by);
        $this->assertSame('Bravo Dojo', $call($organiser, \App\Mcp\Tools\ListEventClubPaymentsTool::class, ['event' => $this->event->uuid])['payments'][0]['club']);
        $this->assertSame('rejected', $call($organiser, \App\Mcp\Tools\DecideEventClubPaymentTool::class,
            ['event' => $this->event->uuid, 'payment' => $invoice->uuid, 'decision' => 'reject', 'reason' => 'Amount does not match'])['state']);
        $this->assertSame('Amount does not match', $invoice->fresh()->reason);
    }

    public function test_a_club_not_on_takeone_cannot_enter_an_event_limited_to_its_own_or_one_country(): void
    {
        foreach (['internal', 'nationwide'] as $scope) {
            $this->event->forceFill(['scope' => $scope])->save();
            $this->consent();
            $this->postJson($this->url('submit'), [
                'submissionId' => 's-scope-'.$scope, 'clubMode' => 'new',
                'club' => ['name' => 'Outsider '.$scope, 'email' => 'o@example.com', 'phone' => '+97333335555'],
                'entries' => [['name' => 'Some Body', 'gender' => 'Male', 'dob' => '2010-01-01', 'weight' => 40, 'types' => ['Entry']]],
            ])->assertStatus(422);
        }
        $this->assertSame(0, EventClub::where('event_id', $this->event->id)->count());
        $this->assertSame(0, ClubEventRegistration::where('event_id', $this->event->id)->count());
    }
}
