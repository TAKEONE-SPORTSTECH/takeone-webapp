<?php

namespace Tests\Feature\Characterization;

use App\Models\ClubEvent;
use App\Models\ClubEventRegistration;
use App\Models\EventChecklistItem;
use App\Models\EventParticipantBan;
use App\Clubs\Models\Tenant;
use App\Models\User;
use Database\Factories\ClubEventRegistrationFactory;
use Illuminate\Support\Facades\Event as EventFacade;
use Tests\Feature\Contracts\ContractTestCase;

/**
 * CHARACTERIZATION tests for App\Http\Controllers\PersonalEventController.
 *
 * 3,535 lines, 53 methods, effectively no direct coverage. These tests pin
 * CURRENT behaviour so the logic can be moved into the event packages
 * (CLAUDE.md → "Events Are Self-Contained Packages") without silently
 * changing what the platform does.
 *
 * They assert nothing about whether the behaviour is RIGHT. Where the current
 * behaviour is known to be wrong or surprising it is marked `// DIVERGENCE:`
 * and pinned as-is — a refactor that flips it must fail here first.
 */
class PersonalEventCharacterizationTest extends ContractTestCase
{
    /** A phone user-agent — DetectDevice sets request attribute `is_mobile`. */
    private const MOBILE_UA = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) '
        .'AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';

    private const DESKTOP_UA = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 '
        .'(KHTML, like Gecko) Chrome/120.0 Safari/537.36';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    /** Organiser + their club + a championship they created. */
    private function scenario(array $eventAttrs = []): array
    {
        $organiser = $this->createUser(['full_name' => 'Organiser One']);
        $club = $this->clubFor($organiser);
        $event = $this->championship($club, $organiser, $eventAttrs);

        return [$organiser, $club, $event];
    }

    /** A member of the same club who did NOT create the event. */
    private function clubMember(Tenant $club, array $attrs = []): User
    {
        $user = $this->createUser($attrs + ['full_name' => 'Ordinary Member']);
        $user->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);

        return $user->fresh();
    }

    private function asMobile(User $user)
    {
        return $this->actingAs($user)->withHeader('User-Agent', self::MOBILE_UA);
    }

    private function asDesktop(User $user)
    {
        return $this->actingAs($user)->withHeader('User-Agent', self::DESKTOP_UA);
    }

    /* =====================================================================
     * 1. PAGES — status, view name (desktop AND mobile), key view data
     * ===================================================================== */

    public function test_index_renders_device_split_views(): void
    {
        [$organiser] = $this->scenario();

        $this->asDesktop($organiser)->get(route('me.events'))
            ->assertOk()->assertViewIs('personal.desktop.events');

        $this->asMobile($organiser)->get(route('me.events'))
            ->assertOk()->assertViewIs('personal.mobile.events');
    }

    public function test_show_renders_device_split_views_for_the_organiser(): void
    {
        [$organiser, , $event] = $this->scenario();

        $desktop = $this->asDesktop($organiser)->get(route('me.events.show', $event->uuid));
        $desktop->assertOk()->assertViewIs('personal.desktop.event-show');
        $desktop->assertViewHas('canManage', true);
        $desktop->assertViewHas('canOfficiate', true);

        $this->asMobile($organiser)->get(route('me.events.show', $event->uuid))
            ->assertOk()->assertViewIs('personal.mobile.event-show');
    }

    public function test_show_view_data_shape_for_the_organiser(): void
    {
        [$organiser, , $event] = $this->scenario();

        $response = $this->asDesktop($organiser)->get(route('me.events.show', $event->uuid));

        $e = $response->viewData('e');

        // The keys `show` adds on top of eventView() — a package move must keep them.
        $this->assertSame(false, $e['cancelled']);
        $this->assertSame(0, $e['officials_count']);
        $this->assertArrayHasKey('clips_count', $e);

        $response->assertViewHas('banned', false);
        $response->assertViewHas('payment');
        $response->assertViewHas('documents', []);
        $response->assertViewHas('checklist', []);

        // `representing` is only ASKED at an event that reaches past one club.
        $representing = $response->viewData('representing');
        $this->assertFalse($representing['ask'], 'an internal-scope event asks nobody who they represent');
        $this->assertSame(0, $representing['claim']);
        $this->assertFalse($representing['disowned']);
    }

    public function test_show_for_an_ordinary_club_member_hides_the_organisers_tools(): void
    {
        [, $club, $event] = $this->scenario();
        $member = $this->clubMember($club);

        $response = $this->asDesktop($member)->get(route('me.events.show', $event->uuid));

        $response->assertOk()->assertViewIs('personal.desktop.event-show');
        $response->assertViewHas('canManage', false);
        $response->assertViewHas('canOfficiate', false);
        // Actions and finance are the organiser's alone.
        $response->assertViewHas('actions', []);
        $response->assertViewHas('finance', null);
        $response->assertViewHas('checklist', []);
    }

    public function test_show_is_denied_to_someone_the_event_scope_does_not_reach(): void
    {
        [, , $event] = $this->scenario();
        $stranger = $this->createUser();

        // CLAUDE.md: a browser GET that is forbidden is rerouted to '/' by the
        // global handler in bootstrap/app.php — a 302, not a 403.
        $this->asDesktop($stranger)->get(route('me.events.show', $event->uuid))
            ->assertRedirect('/');
    }

    /**
     * An archived event is `abort(404)` inside assertVisible() — but the global
     * handler in bootstrap/app.php reroutes a signed-in WEB navigation away
     * from a dead-end 404 page, so the browser sees a 302. Only a JSON caller
     * gets the real 404. Both halves are pinned.
     */
    public function test_an_archived_event_is_a_404_for_json_and_a_reroute_for_the_browser(): void
    {
        [$organiser, , $event] = $this->scenario();
        $event->forceFill(['is_archived' => true])->save();

        $this->asDesktop($organiser)->get(route('me.events.show', $event->uuid))
            ->assertStatus(302);

        $this->actingAs($organiser)->getJson(route('me.events.show', $event->uuid))
            ->assertNotFound();
    }

    public function test_manage_renders_device_split_consoles_for_the_organiser(): void
    {
        [$organiser, , $event] = $this->scenario();

        $desktop = $this->asDesktop($organiser)->get(route('me.events.manage', $event->uuid));
        $desktop->assertOk()->assertViewIs('personal.desktop.event-manage');
        $desktop->assertViewHas('canManage', true);
        $desktop->assertViewHas('canWeigh', true);
        $desktop->assertViewHas('canPay', true);

        $counts = $desktop->viewData('counts');
        $this->assertSame(['entrants', 'officials', 'documents', 'outstanding'], array_keys($counts));
        $this->assertSame(0, $counts['entrants']);
        $this->assertSame(0, $counts['officials']);

        $this->asMobile($organiser)->get(route('me.events.manage', $event->uuid))
            ->assertOk()->assertViewIs('personal.mobile.event-manage');
    }

    public function test_manage_is_403_for_a_member_who_can_see_the_event_but_not_run_it(): void
    {
        [, $club, $event] = $this->scenario();
        $member = $this->clubMember($club);

        // Visible to them (same club) but they neither manage nor officiate:
        // the console aborts 403, which the web handler reroutes to '/'.
        $this->asDesktop($member)->get(route('me.events.manage', $event->uuid))
            ->assertRedirect('/');

        $this->asDesktop($member)->getJson(route('me.events.manage', $event->uuid))
            ->assertForbidden();
    }

    public function test_bracket_renders_device_split_boards_and_opens_on_a_division(): void
    {
        [$organiser, $club, $event] = $this->scenario();
        [$category] = $this->drawnDivision($event, $club);

        $desktop = $this->asDesktop($organiser)->get(route('me.events.bracket', $event->uuid));
        $desktop->assertOk()->assertViewIs('personal.desktop.event-bracket');
        $desktop->assertViewHas('canManage', true);
        $desktop->assertViewHas('canArrange', true);

        $categories = $desktop->viewData('categories');
        $this->assertNotEmpty($categories, 'a drawn division must appear on the board');
        // NOTE: runData() keys `categories` BY CATEGORY ID, not 0..n — a consumer
        // that indexes it as a list breaks. Pinned so a package move keeps it.
        $this->assertSame([$category->id], array_keys($categories));
        $this->assertSame('c'.$category->id, $desktop->viewData('initialCategory'));
        $this->assertSame(collect($categories)->first()['key'], $desktop->viewData('initialCategory'));

        $this->asMobile($organiser)->get(route('me.events.bracket', $event->uuid))
            ->assertOk()->assertViewIs('personal.mobile.event-bracket');
    }

    public function test_bracket_opens_on_the_division_named_by_the_bout_query(): void
    {
        [$organiser, $club, $event] = $this->scenario();
        [$category] = $this->drawnDivision($event, $club);

        $match = \App\Models\EventMatch::where('event_id', $event->id)->orderBy('match_no')->firstOrFail();

        $response = $this->asDesktop($organiser)
            ->get(route('me.events.bracket', ['event' => $event->uuid, 'bout' => $match->match_no]));

        $response->assertOk();
        $expected = collect($response->viewData('categories'))->firstWhere('id', $category->id)['key'];
        $this->assertSame($expected, $response->viewData('initialCategory'));
    }

    public function test_bracket_falls_back_to_the_first_division_for_a_foreign_category_id(): void
    {
        [$organiser, $club, $event] = $this->scenario();
        $this->drawnDivision($event, $club);

        $response = $this->asDesktop($organiser)
            ->get(route('me.events.bracket', ['event' => $event->uuid, 'category' => 999999]));

        $response->assertOk();
        $this->assertSame(
            collect($response->viewData('categories'))->first()['key'],
            $response->viewData('initialCategory'),
            'an id belonging to no division of this event falls back to the first'
        );
    }

    public function test_bracket_is_readable_but_not_arrangeable_by_an_ordinary_member(): void
    {
        [, $club, $event] = $this->scenario();
        $this->drawnDivision($event, $club);
        $member = $this->clubMember($club);

        $response = $this->asDesktop($member)->get(route('me.events.bracket', $event->uuid));
        $response->assertOk();
        $response->assertViewHas('canManage', false);
        $response->assertViewHas('canArrange', false);
        $response->assertViewHas('actions', []);
    }

    public function test_gallery_renders_device_split_views(): void
    {
        [$organiser, $club, $event] = $this->scenario();
        $this->drawnDivision($event, $club);
        $this->filmFirstBout($event);

        $desktop = $this->asDesktop($organiser)->get(route('me.events.gallery', $event->uuid));
        $desktop->assertOk()->assertViewIs('personal.desktop.event-gallery');
        $this->assertSame(1, $desktop->viewData('clipCount'));

        $this->asMobile($organiser)->get(route('me.events.gallery', $event->uuid))
            ->assertOk()->assertViewIs('personal.mobile.event-gallery');
    }

    public function test_bout_renders_device_split_views(): void
    {
        [$organiser, $club, $event] = $this->scenario();
        $this->drawnDivision($event, $club);
        $match = \App\Models\EventMatch::where('event_id', $event->id)->orderBy('match_no')->firstOrFail();

        $desktop = $this->asDesktop($organiser)
            ->get(route('me.events.bout', ['event' => $event->uuid, 'matchNo' => $match->match_no]));
        $desktop->assertOk()->assertViewIs('personal.desktop.event-bout');
        $desktop->assertViewHas('canManage', true);
        $desktop->assertViewHas('officials', []);

        $this->asMobile($organiser)
            ->get(route('me.events.bout', ['event' => $event->uuid, 'matchNo' => $match->match_no]))
            ->assertOk()->assertViewIs('personal.mobile.event-bout');
    }

    public function test_bout_number_that_does_not_exist_is_a_404_for_json_and_a_reroute_for_the_browser(): void
    {
        [$organiser, $club, $event] = $this->scenario();
        $this->drawnDivision($event, $club);

        // Same shape as the archived event: `abort_if($match === null, 404)`
        // becomes a 302 for browser navigation, a real 404 for JSON.
        $this->asDesktop($organiser)
            ->get(route('me.events.bout', ['event' => $event->uuid, 'matchNo' => 9999]))
            ->assertStatus(302);

        $this->actingAs($organiser)
            ->getJson(route('me.events.bout', ['event' => $event->uuid, 'matchNo' => 9999]))
            ->assertNotFound();
    }

    /* =====================================================================
     * 2. boutSide() — the minor safeguard
     * ===================================================================== */

    /**
     * DIVERGENCE (known + documented in CLAUDE.md → "Who Fills The Form
     * Decides What It Demands"):
     *
     *   $isMinor = $user?->birthdate ? Carbon::parse(...)->age < 18 : false;
     *
     * A NULL birthdate is read as an ADULT, so the minor protection — no public
     * profile link from a bout page — is SILENTLY REMOVED for exactly the
     * athletes most likely to have no birthdate on file (someone entered at a
     * weigh-in off a paper sheet by an organiser, whose form is name-only).
     *
     * Pinned as CURRENT behaviour on purpose: this is the semantics a refactor
     * into an event package could flip by accident, in either direction. If
     * this test starts failing, the safeguard changed — decide deliberately.
     */
    public function test_bout_side_treats_a_null_birthdate_as_an_adult_and_publishes_the_profile_link(): void
    {
        [$organiser, $club, $event] = $this->scenario();
        [$category] = $this->drawnDivision($event, $club);

        $match = \App\Models\EventMatch::where('event_id', $event->id)->orderBy('match_no')->firstOrFail();

        $noBirthdate = $match->competitorA->user;
        $noBirthdate->forceFill(['birthdate' => null, 'is_discoverable' => true])->save();

        $bout = $this->asDesktop($organiser)
            ->get(route('me.events.bout', ['event' => $event->uuid, 'matchNo' => $match->match_no]))
            ->viewData('bout');

        $this->assertNotNull(
            $bout['a']['profile_url'],
            'CURRENT behaviour: a NULL birthdate is read as an adult, so the profile link is published.'
        );
        $this->assertSame(route('people.show', $noBirthdate->uuid), $bout['a']['profile_url']);
    }

    /** A KNOWN minor is protected — the link is withheld, and silently. */
    public function test_bout_side_withholds_the_profile_link_for_a_known_minor(): void
    {
        [$organiser, $club, $event] = $this->scenario();
        $this->drawnDivision($event, $club);

        $match = \App\Models\EventMatch::where('event_id', $event->id)->orderBy('match_no')->firstOrFail();

        $minor = $match->competitorA->user;
        $minor->forceFill([
            'birthdate' => now()->subYears(14)->toDateString(),
            'is_discoverable' => true,
        ])->save();

        $bout = $this->asDesktop($organiser)
            ->get(route('me.events.bout', ['event' => $event->uuid, 'matchNo' => $match->match_no]))
            ->viewData('bout');

        $this->assertNull($bout['a']['profile_url']);
        // Withholding is silent — no marker of any kind is added to the corner.
        $this->assertArrayNotHasKey('profile_hidden', $bout['a']);
        $this->assertSame($minor->gender, $bout['a']['gender']);
    }

    /** Opting out of discovery withholds the link for an adult too. */
    public function test_bout_side_withholds_the_profile_link_for_a_non_discoverable_adult(): void
    {
        [$organiser, $club, $event] = $this->scenario();
        $this->drawnDivision($event, $club);

        $match = \App\Models\EventMatch::where('event_id', $event->id)->orderBy('match_no')->firstOrFail();
        $match->competitorA->user->forceFill(['is_discoverable' => false])->save();

        $bout = $this->asDesktop($organiser)
            ->get(route('me.events.bout', ['event' => $event->uuid, 'matchNo' => $match->match_no]))
            ->viewData('bout');

        $this->assertNull($bout['a']['profile_url']);
    }

    /** The face follows profile_picture_is_public, not the viewer's rights. */
    public function test_bout_side_withholds_the_photo_unless_the_athlete_made_it_public(): void
    {
        [$organiser, $club, $event] = $this->scenario();
        $this->drawnDivision($event, $club);

        $match = \App\Models\EventMatch::where('event_id', $event->id)->orderBy('match_no')->firstOrFail();
        $match->competitorA->user->forceFill([
            'profile_picture' => 'members/x/profile/a.jpg',
            'profile_picture_is_public' => false,
        ])->save();

        $bout = $this->asDesktop($organiser)
            ->get(route('me.events.bout', ['event' => $event->uuid, 'matchNo' => $match->match_no]))
            ->viewData('bout');

        $this->assertNull($bout['a']['photo']);

        $match->competitorA->user->forceFill(['profile_picture_is_public' => true])->save();

        $bout = $this->asDesktop($organiser)
            ->get(route('me.events.bout', ['event' => $event->uuid, 'matchNo' => $match->match_no]))
            ->viewData('bout');

        $this->assertNotNull($bout['a']['photo']);
    }

    /** Corner colour falls back to red/blue when the mat recorded nothing. */
    public function test_bout_side_corner_defaults_to_red_for_a_and_blue_for_b(): void
    {
        [$organiser, $club, $event] = $this->scenario();
        $this->drawnDivision($event, $club);

        $match = \App\Models\EventMatch::where('event_id', $event->id)->orderBy('match_no')->firstOrFail();
        $match->forceFill(['a_corner' => null, 'b_corner' => null])->save();

        $bout = $this->asDesktop($organiser)
            ->get(route('me.events.bout', ['event' => $event->uuid, 'matchNo' => $match->match_no]))
            ->viewData('bout');

        $this->assertSame('red', $bout['a']['corner']);
        $this->assertSame('blue', $bout['b']['corner']);
    }

    /** A bout with no playable media offers no watch link rather than a broken one. */
    public function test_bout_view_has_no_video_url_when_nothing_was_filmed(): void
    {
        [$organiser, $club, $event] = $this->scenario();
        $this->drawnDivision($event, $club);

        $match = \App\Models\EventMatch::where('event_id', $event->id)->orderBy('match_no')->firstOrFail();

        $bout = $this->asDesktop($organiser)
            ->get(route('me.events.bout', ['event' => $event->uuid, 'matchNo' => $match->match_no]))
            ->viewData('bout');

        $this->assertNull($bout['video_url']);
        // The bracket link is scoped to THIS bout's own division.
        $this->assertStringContainsString('category='.$match->category_id, $bout['bracket_url']);
        $this->assertStringContainsString('bout='.$match->match_no, $bout['bracket_url']);
    }

    /* =====================================================================
     * 3. LIFECYCLE WRITES — join, ticket, cancel, start, cancel-event, delete
     * ===================================================================== */

    /**
     * The division a member lands in is decided by the PACKAGE from their own
     * profile — they never pick it. So the fixture asks the sport for the name
     * it will classify this athlete into rather than hard-coding a string.
     */
    private function divisionFor(ClubEvent $event, string $gender, int $age, float $weight): \App\Models\EventCategory
    {
        $sport = app(\App\Sports\Combat\SportRegistry::class)->get($event->sport);
        $class = $sport->classify($gender, $age, $weight);

        $this->assertNotNull($class, 'the sport must classify this athlete for the fixture to be meaningful');

        return \App\Models\EventCategory::create([
            'event_id' => $event->id,
            'name' => $sport->divisionName($class['age_group'], $gender, $class['category']),
            'sort_order' => 1,
        ]);
    }

    public function test_register_happy_path_creates_a_participant_registration_in_the_classified_division(): void
    {
        [, $club, $event] = $this->scenario();
        $division = $this->divisionFor($event, 'Male', 25, 57.0);

        $member = $this->clubMember($club, [
            'gender' => 'Male',
            'birthdate' => now()->subYears(25)->toDateString(),
        ]);
        \App\Models\HealthRecord::create(['user_id' => $member->id, 'weight' => 57, 'recorded_at' => now()]);

        $this->actingAs($member)->postJson(route('me.events.register', $event->uuid))
            ->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('club_event_registrations', [
            'event_id' => $event->id,
            'user_id' => $member->id,
            'role' => 'participant',
            // Placed by the package, never chosen by the member.
            'category_id' => $division->id,
        ]);
    }

    /**
     * A member with no gender / birthdate on file is DENIED with code
     * `no_profile` — the package cannot place them in a weight division.
     *
     * Worth pinning next to the boutSide() case below: this is the OTHER place
     * a missing birthdate changes the answer, and here it fails CLOSED.
     */
    public function test_register_denies_a_member_with_no_birthdate_with_code_no_profile(): void
    {
        [, $club, $event] = $this->scenario();
        $this->divisionFor($event, 'Male', 25, 57.0);

        $member = $this->clubMember($club, ['gender' => null, 'birthdate' => null]);

        $this->actingAs($member)->postJson(route('me.events.register', $event->uuid))
            ->assertStatus(422)
            ->assertJson(['success' => false, 'code' => 'no_profile', 'spectator' => false]);

        $this->assertDatabaseMissing('club_event_registrations', [
            'event_id' => $event->id, 'user_id' => $member->id,
        ]);
    }

    /**
     * DIVERGENCE: the package calls "no weight on file" a DEFERRAL — the
     * athlete stands on the scale on the day — but `EnrolmentDecision::defer()`
     * is built as `deny(..., deferrable: true)`, so `$decision->allowed` is
     * FALSE and self-registration is refused 422 with code `no_weight`.
     *
     * The deferral only actually defers on the club/coach entry path
     * (EntryService), not here. Pinned as CURRENT behaviour, not endorsed.
     */
    public function test_register_refuses_a_member_with_no_weight_on_file_despite_the_deferral(): void
    {
        [, $club, $event] = $this->scenario();
        $this->divisionFor($event, 'Male', 25, 57.0);

        $member = $this->clubMember($club, [
            'gender' => 'Male',
            'birthdate' => now()->subYears(25)->toDateString(),
        ]);
        // No HealthRecord at all.

        $this->actingAs($member)->postJson(route('me.events.register', $event->uuid))
            ->assertStatus(422)
            ->assertJson(['success' => false, 'code' => 'no_weight']);

        $this->assertDatabaseMissing('club_event_registrations', [
            'event_id' => $event->id, 'user_id' => $member->id,
        ]);
    }

    public function test_register_is_refused_once_the_event_has_ended(): void
    {
        [, $club, $event] = $this->scenario();
        // DIVERGENCE: `hasEnded()` is DATE-based only — status 'completed' does
        // NOT end an event as far as this endpoint is concerned. The dates are
        // what closes the door, so they are what this test moves.
        $event->forceFill([
            'status' => 'completed',
            'date' => now()->subWeeks(2)->toDateString(),
            'end_date' => now()->subWeeks(2)->toDateString(),
        ])->save();
        $member = $this->clubMember($club);

        $this->actingAs($member)->postJson(route('me.events.register', $event->uuid))
            ->assertStatus(422)
            ->assertJson(['success' => false, 'message' => 'This event has ended.']);

        $this->assertDatabaseMissing('club_event_registrations', [
            'event_id' => $event->id, 'user_id' => $member->id,
        ]);
    }

    public function test_register_is_refused_with_403_for_a_banned_member(): void
    {
        [, $club, $event] = $this->scenario();
        $member = $this->clubMember($club);

        EventParticipantBan::create([
            'user_id' => $member->id,
            'event_id' => $event->id,
            'tenant_id' => $event->tenant_id,
            'scope' => 'event',
            'created_by' => $event->created_by,
        ]);

        $this->actingAs($member)->postJson(route('me.events.register', $event->uuid))
            ->assertStatus(403)
            ->assertJson(['success' => false, 'code' => 'banned']);
    }

    public function test_register_validation_rejects_a_category_from_another_event(): void
    {
        [, $club, $event] = $this->scenario();
        $other = $this->championship($club, $event->createdBy ?? $this->createUser(), ['title' => 'Other']);
        $foreign = \App\Models\EventCategory::create([
            'event_id' => $other->id, 'name' => 'Foreign division', 'sort_order' => 1,
        ]);

        $member = $this->clubMember($club);

        $this->actingAs($member)->postJson(route('me.events.register', $event->uuid), [
            'category_id' => $foreign->id,
        ])->assertStatus(422)->assertJsonValidationErrors('category_id');
    }

    public function test_register_validation_rejects_a_payment_proof_that_is_not_a_data_uri(): void
    {
        [, $club, $event] = $this->scenario();
        $member = $this->clubMember($club);

        $this->actingAs($member)->postJson(route('me.events.register', $event->uuid), [
            'payment_proof' => 'https://example.com/receipt.png',
        ])->assertStatus(422)->assertJsonValidationErrors('payment_proof');
    }

    public function test_register_is_denied_to_someone_the_event_does_not_reach(): void
    {
        [, , $event] = $this->scenario();
        $stranger = $this->createUser();

        $this->actingAs($stranger)->postJson(route('me.events.register', $event->uuid))
            ->assertForbidden();

        $this->assertDatabaseMissing('club_event_registrations', [
            'event_id' => $event->id, 'user_id' => $stranger->id,
        ]);
    }

    public function test_register_is_refused_after_the_enrolment_window_closes(): void
    {
        [, $club, $event] = $this->scenario();
        $event->forceFill(['enrollment_ends_at' => now()->subDays(3)->toDateString()])->save();
        $member = $this->clubMember($club);

        $this->actingAs($member)->postJson(route('me.events.register', $event->uuid))
            ->assertStatus(422)
            ->assertJsonFragment(['success' => false]);

        $this->assertDatabaseMissing('club_event_registrations', [
            'event_id' => $event->id, 'user_id' => $member->id,
        ]);
    }

    public function test_register_is_refused_when_the_event_is_full(): void
    {
        [, $club, $event] = $this->scenario();
        $event->forceFill(['max_capacity' => 1])->save();

        $taken = $this->clubMember($club, ['full_name' => 'First In']);
        ClubEventRegistrationFactory::new()->create([
            'event_id' => $event->id, 'user_id' => $taken->id, 'role' => 'participant',
        ]);

        $late = $this->clubMember($club, ['full_name' => 'Too Late']);

        $this->actingAs($late)->postJson(route('me.events.register', $event->uuid))
            ->assertStatus(422)
            ->assertJson(['success' => false, 'message' => 'This event is full.']);
    }

    /**
     * DIVERGENCE (surprising but deliberate — see the method's own comment):
     * `DELETE /register` never cancels anything. An entered member is told
     * their spot is final (422); someone who never entered gets success:true
     * with the message "Nothing to cancel".
     */
    public function test_cancel_never_cancels_an_existing_registration(): void
    {
        [, $club, $event] = $this->scenario();
        $member = $this->clubMember($club);

        ClubEventRegistrationFactory::new()->create([
            'event_id' => $event->id, 'user_id' => $member->id, 'role' => 'participant',
        ]);

        $this->actingAs($member)->deleteJson(route('me.events.cancel', $event->uuid))
            ->assertStatus(422)
            ->assertJson(['success' => false]);

        // The row is still there — "cancel" is a message, not a delete.
        $this->assertDatabaseHas('club_event_registrations', [
            'event_id' => $event->id, 'user_id' => $member->id,
        ]);
    }

    public function test_cancel_with_no_registration_reports_success_with_a_null_role(): void
    {
        [, $club, $event] = $this->scenario();
        $member = $this->clubMember($club);

        $this->actingAs($member)->deleteJson(route('me.events.cancel', $event->uuid))
            ->assertOk()
            ->assertJson(['success' => true, 'message' => 'Nothing to cancel', 'role' => null]);
    }

    public function test_ticket_is_422_when_the_event_sells_no_spectator_tickets(): void
    {
        [, $club, $event] = $this->scenario(['spectator_enabled' => false]);
        $member = $this->clubMember($club);

        $this->actingAs($member)->postJson(route('me.events.ticket', $event->uuid))
            ->assertStatus(422);
    }

    public function test_ticket_books_a_spectator_seat_when_spectating_is_enabled(): void
    {
        [, $club, $event] = $this->scenario(['spectator_enabled' => true]);
        $member = $this->clubMember($club);

        $this->actingAs($member)->postJson(route('me.events.ticket', $event->uuid))
            ->assertOk()
            ->assertJson(['success' => true, 'role' => 'spectator', 'spectators' => 1]);

        $this->assertDatabaseHas('club_event_registrations', [
            'event_id' => $event->id, 'user_id' => $member->id,
            'role' => 'spectator', 'status' => 'joined',
        ]);
    }

    public function test_start_event_stamps_started_at_and_the_starter(): void
    {
        [$organiser, , $event] = $this->scenario();

        $this->actingAs($organiser)->postJson(route('me.events.start', $event->uuid))
            ->assertOk()
            ->assertJson(['success' => true, 'overridden' => false]);

        $event->refresh();
        $this->assertNotNull($event->started_at);
        $this->assertSame($organiser->id, $event->started_by);
        $this->assertFalse((bool) $event->start_overridden);
    }

    public function test_start_event_is_blocked_by_an_outstanding_checklist_and_allowed_with_override(): void
    {
        [$organiser, , $event] = $this->scenario();

        EventChecklistItem::create([
            'event_id' => $event->id,
            'label' => 'Lay the mats',
            'created_by' => $organiser->id,
        ]);

        $this->actingAs($organiser)->postJson(route('me.events.start', $event->uuid))
            ->assertStatus(422)
            ->assertJson(['success' => false, 'code' => 'checklist_incomplete', 'outstanding' => 1]);

        $this->assertNull($event->fresh()->started_at);

        $this->actingAs($organiser)
            ->postJson(route('me.events.start', $event->uuid), ['override' => true])
            ->assertOk()
            ->assertJson(['success' => true, 'overridden' => true]);

        $this->assertNotNull($event->fresh()->started_at);
    }

    public function test_start_event_is_refused_twice_and_refused_on_a_cancelled_event(): void
    {
        [$organiser, , $event] = $this->scenario();

        $this->actingAs($organiser)->postJson(route('me.events.start', $event->uuid))->assertOk();
        $this->actingAs($organiser)->postJson(route('me.events.start', $event->uuid))
            ->assertStatus(422)->assertJson(['success' => false]);

        [$org2, , $cancelled] = $this->scenario(['title' => 'Cancelled Cup']);
        $cancelled->forceFill(['status' => 'cancelled'])->save();

        $this->actingAs($org2)->postJson(route('me.events.start', $cancelled->uuid))
            ->assertStatus(422)->assertJson(['success' => false]);
    }

    public function test_start_event_is_denied_to_a_member_who_is_not_the_organiser(): void
    {
        [, $club, $event] = $this->scenario();
        $member = $this->clubMember($club);

        $this->actingAs($member)->postJson(route('me.events.start', $event->uuid))
            ->assertForbidden();

        $this->assertNull($event->fresh()->started_at);
    }

    public function test_cancel_event_sets_the_status_and_returns_the_events_list(): void
    {
        [$organiser, , $event] = $this->scenario();

        $this->actingAs($organiser)->patchJson(route('me.events.cancel-event', $event->uuid))
            ->assertOk()
            ->assertJson(['success' => true, 'redirect' => route('me.events')]);

        $this->assertSame('cancelled', $event->fresh()->status);
    }

    public function test_cancel_event_is_denied_to_a_non_organiser(): void
    {
        [, $club, $event] = $this->scenario();
        $member = $this->clubMember($club);

        $this->actingAs($member)->patchJson(route('me.events.cancel-event', $event->uuid))
            ->assertForbidden();

        $this->assertSame('active', $event->fresh()->status);
    }

    public function test_destroy_deletes_the_event_for_its_organiser_only(): void
    {
        [, $club, $event] = $this->scenario();
        $member = $this->clubMember($club);

        $this->actingAs($member)->deleteJson(route('me.events.destroy', $event->uuid))
            ->assertForbidden();
        $this->assertDatabaseHas('club_events', ['id' => $event->id]);

        $organiser = User::find($event->created_by);
        $this->actingAs($organiser)->deleteJson(route('me.events.destroy', $event->uuid))
            ->assertOk()
            ->assertJson(['success' => true, 'redirect' => route('me.events')]);

        $this->assertDatabaseMissing('club_events', ['id' => $event->id]);
    }

    /** A super-admin is the platform override on canManage(). */
    public function test_a_super_admin_may_manage_an_event_they_did_not_create(): void
    {
        [, , $event] = $this->scenario();
        $admin = $this->createUser(['full_name' => 'Platform Staff']);
        $this->makeSuperAdmin($admin);

        $this->asDesktop($admin->fresh())->get(route('me.events.manage', $event->uuid))
            ->assertOk()->assertViewHas('canManage', true);
    }

    /* =====================================================================
     * 4. INLINE DOMAIN LOGIC — pinned by OUTPUT, so it can move into a package
     * ===================================================================== */

    /**
     * `performAction` denies by default: only an action the PACKAGE currently
     * offers may run. The controller itself must never branch on sport.
     */
    public function test_perform_action_refuses_an_action_the_package_does_not_offer(): void
    {
        [$organiser, , $event] = $this->scenario();

        $this->actingAs($organiser)
            ->postJson(route('me.events.action', ['event' => $event->uuid, 'action' => 'not_a_real_action']))
            ->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    public function test_perform_action_generate_draw_builds_the_bracket_and_redirects_to_it(): void
    {
        [$organiser, $club, $event] = $this->scenario();

        // Four entrants in one division, but NO draw yet.
        $category = \App\Models\EventCategory::create([
            'event_id' => $event->id, 'name' => 'Senior Men -58 kg', 'sort_order' => 1,
        ]);

        foreach (range(1, 4) as $i) {
            $athlete = $this->clubMember($club, [
                'full_name' => 'Draw Athlete '.$i,
                'gender' => 'Male',
                'birthdate' => now()->subYears(25)->toDateString(),
            ]);
            ClubEventRegistrationFactory::new()->create([
                'event_id' => $event->id, 'user_id' => $athlete->id,
                'category_id' => $category->id, 'role' => 'participant', 'paid' => true, 'weight' => 57,
            ]);
        }

        $response = $this->actingAs($organiser)
            ->postJson(route('me.events.action', ['event' => $event->uuid, 'action' => 'generate_draw']));

        $response->assertOk()->assertJson([
            'success' => true,
            'redirect' => route('me.events.bracket', $event->uuid),
        ]);

        // OUTPUT, not implementation: 4 entrants → a 4-slot knockout = 3 bouts.
        $this->assertSame(3, \App\Models\EventMatch::where('event_id', $event->id)->count());
    }

    public function test_perform_action_is_denied_to_a_non_organiser(): void
    {
        [, $club, $event] = $this->scenario();
        $member = $this->clubMember($club);

        $this->actingAs($member)
            ->postJson(route('me.events.action', ['event' => $event->uuid, 'action' => 'generate_draw']))
            ->assertForbidden();
    }

    /**
     * `bracketData` is the JSON the board consumes. Pinned here for its
     * top-level shape only — the deep shape is the bracket contract test's job.
     */
    public function test_bracket_data_is_scoped_to_the_viewer_and_denied_to_a_stranger(): void
    {
        [$organiser, $club, $event] = $this->scenario();
        $this->drawnDivision($event, $club);

        $this->actingAs($organiser)->getJson(route('me.events.bracket.data', $event->uuid))
            ->assertOk();

        $this->actingAs($this->createUser())
            ->getJson(route('me.events.bracket.data', $event->uuid))
            ->assertForbidden();
    }

    /**
     * `recordOutcome` validates the vocabulary it accepts. Pinned so a move
     * into the package keeps the same contract with the run-day table.
     */
    public function test_record_outcome_rejects_an_unknown_winner_and_status(): void
    {
        [$organiser, $club, $event] = $this->scenario();
        $this->drawnDivision($event, $club);
        $match = \App\Models\EventMatch::where('event_id', $event->id)->orderBy('match_no')->firstOrFail();

        $this->actingAs($organiser)
            ->postJson(route('me.events.outcome', ['event' => $event->uuid, 'unit' => $match->id]), [
                'winner' => 'c',
            ])->assertStatus(422)->assertJsonValidationErrors('winner');

        $this->actingAs($organiser)
            ->postJson(route('me.events.outcome', ['event' => $event->uuid, 'unit' => $match->id]), [
                'status' => 'paused',
            ])->assertStatus(422)->assertJsonValidationErrors('status');
    }

    public function test_record_outcome_is_denied_to_a_non_organiser(): void
    {
        [, $club, $event] = $this->scenario();
        $this->drawnDivision($event, $club);
        $match = \App\Models\EventMatch::where('event_id', $event->id)->orderBy('match_no')->firstOrFail();
        $member = $this->clubMember($club);

        $this->actingAs($member)
            ->postJson(route('me.events.outcome', ['event' => $event->uuid, 'unit' => $match->id]), [
                'winner' => 'a',
            ])->assertForbidden();
    }

    /* =====================================================================
     * 5. CHECKLIST — a small self-contained write surface
     * ===================================================================== */

    public function test_checklist_create_toggle_and_delete_round_trip(): void
    {
        [$organiser, , $event] = $this->scenario();

        $created = $this->actingAs($organiser)
            ->postJson(route('me.events.checklist.store', $event->uuid), ['label' => 'Lay the mats']);
        $created->assertOk()->assertJson(['success' => true]);

        $item = EventChecklistItem::where('event_id', $event->id)->firstOrFail();
        $this->assertSame('Lay the mats', $item->label);
        $this->assertNull($item->checked_at);

        $this->actingAs($organiser)->putJson(
            route('me.events.checklist.toggle', ['event' => $event->uuid, 'checklistItem' => $item->uuid]),
            ['checked' => true],
        )->assertOk()->assertJson(['success' => true]);

        $this->assertNotNull($item->fresh()->checked_at);

        $this->actingAs($organiser)->deleteJson(
            route('me.events.checklist.destroy', ['event' => $event->uuid, 'checklistItem' => $item->uuid]),
        )->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseMissing('event_checklist_items', ['id' => $item->id]);
    }

    public function test_checklist_store_is_denied_to_a_member_with_no_job_at_the_event(): void
    {
        [, $club, $event] = $this->scenario();
        $member = $this->clubMember($club);

        $this->actingAs($member)
            ->postJson(route('me.events.checklist.store', $event->uuid), ['label' => 'Sneak an item in'])
            ->assertForbidden();

        $this->assertDatabaseCount('event_checklist_items', 0);
    }
}
