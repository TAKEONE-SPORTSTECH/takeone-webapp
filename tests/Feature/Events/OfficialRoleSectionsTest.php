<?php

namespace Tests\Feature\Events;

use App\Events\Models\EventOfficial;
use App\Events\Support\BracketPdf;
use App\Events\Support\EventAccess;
use App\Events\Support\OfficialRoles;
use Tests\Feature\Contracts\ContractTestCase;

/**
 * "Officials is a broad word" (owner, 2026-10-18): every officials list reads in
 * the five parts an international results book prints — organising committee,
 * technical delegate & jury, referees & judges, table, team officials — and the
 * jobs a competition credits (technical delegate, coach, doctor…) can be
 * appointed without granting any access.
 */
class OfficialRoleSectionsTest extends ContractTestCase
{
    public function test_the_role_list_carries_the_new_jobs_and_a_section_for_every_job(): void
    {
        $organiser = $this->createUser(['full_name' => 'Organiser One']);
        $event = $this->championship($this->clubFor($organiser), $organiser);

        $roles = collect($this->actingAs($organiser)->getJson(route('me.events.officials', $event->uuid))
            ->assertOk()->json('roles'))->keyBy('value');

        foreach (['technical_delegate', 'chief_referee', 'competition_manager', 'accreditation', 'medical', 'team_manager', 'coach', 'team_doctor'] as $key) {
            $this->assertTrue($roles->has($key), "$key is offered");
            $this->assertSame('mat', $roles[$key]['group'], "$key grants no access");
        }
        $this->assertSame('technical', $roles['technical_delegate']['section']);
        $this->assertSame('Technical Delegate & Jury', $roles['technical_delegate']['section_label']);
        $this->assertSame('technical', $roles['jury']['section']);
        $this->assertSame('platform', $roles['jury']['group']);
        $this->assertSame('organising', $roles['organiser']['section']);
        $this->assertSame('team', $roles['coach']['section']);
        // The sport's own jobs keep their keys and land in their sections.
        $this->assertSame('referees', $roles['center_referee']['section']);
        $this->assertSame('table', $roles['timekeeper']['section']);
        $this->assertSame('technical', $roles['review_jury']['section']);

        // Read in section order: the organising committee leads, the teams close.
        $order = array_flip(OfficialRoles::SECTIONS);
        $ranks = $roles->pluck('section')->map(fn ($s) => $order[$s])->values()->all();
        $sorted = $ranks;
        sort($sorted);
        $this->assertSame($sorted, $ranks);
    }

    public function test_a_technical_delegate_from_outside_the_club_can_be_appointed_and_gains_no_access(): void
    {
        $organiser = $this->createUser(['full_name' => 'Organiser One']);
        $event = $this->championship($this->clubFor($organiser), $organiser);
        $delegate = $this->createUser(['full_name' => 'Federation Delegate']);

        $this->actingAs($organiser)->postJson(route('me.events.officials.store', $event->uuid), [
            'user_id' => $delegate->id,
            'role' => EventOfficial::ROLE_TECHNICAL_DELEGATE,
            'compensation' => EventOfficial::COMP_VOLUNTEER,
        ])->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('event_officials', ['event_id' => $event->id, 'user_id' => $delegate->id, 'role' => 'technical_delegate']);

        $access = app(EventAccess::class);
        $this->assertFalse($access->canManage($event->fresh(), $delegate->fresh()));
        $this->assertFalse($access->canArrange($event->fresh(), $delegate->fresh()));
        $this->assertFalse($access->canVerifyWeighIn($event->fresh(), $delegate->fresh()));

        // Somebody else's event is not theirs to appoint to.
        $stranger = $this->createUser(['full_name' => 'Stranger']);
        $this->actingAs($stranger)->postJson(route('me.events.officials.store', $event->uuid), [
            'user_id' => $stranger->id,
            'role' => EventOfficial::ROLE_TECHNICAL_DELEGATE,
            'compensation' => EventOfficial::COMP_VOLUNTEER,
        ])->assertForbidden();
    }

    public function test_a_designation_not_on_the_list_can_be_typed_in_beside_listed_ones_and_grants_nothing(): void
    {
        $organiser = $this->createUser(['full_name' => 'Organiser One']);
        $event = $this->championship($this->clubFor($organiser), $organiser);
        $person = $this->createUser(['full_name' => 'Outside Helper']);
        $store = fn (array $body) => $this->actingAs($organiser)->postJson(route('me.events.officials.store', $event->uuid),
            $body + ['user_id' => $person->id, 'compensation' => EventOfficial::COMP_VOLUNTEER]);

        // Wording is required, and cleaned before it is stored.
        $store(['role' => 'other'])->assertStatus(422);
        $store(['role' => 'other', 'role_label' => '  <b>Announcer</b>  '])->assertOk()->assertJson(['success' => true]);
        $store(['role' => EventOfficial::ROLE_COACH])->assertOk();
        $this->assertDatabaseHas('event_officials', ['event_id' => $event->id, 'user_id' => $person->id, 'role' => 'other', 'role_label' => 'Announcer']);
        $this->assertSame(2, EventOfficial::where('event_id', $event->id)->where('user_id', $person->id)->count());

        // The same wording twice is a duplicate; a second wording is refused cleanly, never a 500.
        $store(['role' => 'other', 'role_label' => 'announcer'])->assertStatus(422);
        $store(['role' => 'other', 'role_label' => 'Results officer'])->assertStatus(422);

        // A brand-new person can be written down straight onto a typed-in designation.
        $this->actingAs($organiser)->postJson(route('me.events.officials.person', $event->uuid), [
            'full_name' => 'Paper Person', 'role' => 'other', 'role_label' => 'Photographer',
            'compensation' => EventOfficial::COMP_VOLUNTEER,
        ])->assertOk()->assertJson(['success' => true]);
        $this->assertDatabaseHas('event_officials', ['event_id' => $event->id, 'role' => 'other', 'role_label' => 'Photographer']);

        $this->assertFalse(app(EventAccess::class)->canManage($event->fresh(), $person->fresh()));

        // Somebody else's event is not theirs to appoint to.
        $this->actingAs($person)->postJson(route('me.events.officials.store', $event->uuid), [
            'user_id' => $person->id, 'role' => 'other', 'role_label' => 'Boss', 'compensation' => EventOfficial::COMP_VOLUNTEER,
        ])->assertForbidden();
    }

    public function test_a_typed_in_designation_is_corrected_in_place_and_keeps_its_appointment(): void
    {
        $organiser = $this->createUser(['full_name' => 'Organiser One']);
        $event = $this->championship($this->clubFor($organiser), $organiser);
        $person = $this->createUser(['full_name' => 'Referee From The Form']);
        $o = EventOfficial::create(['event_id' => $event->id, 'user_id' => $person->id, 'role' => 'other', 'role_label' => 'Referee 1']);
        $put = fn (array $body) => $this->actingAs($organiser)->putJson(route('me.events.officials.update', [$event->uuid, $o->id]), $body + ['compensation' => EventOfficial::COMP_VOLUNTEER]);

        $put(['role' => 'other', 'role_label' => ' Team <i>manager</i> '])->assertOk();
        $this->assertSame('Team manager', $o->fresh()->role_label);
        $this->assertSame(1, EventOfficial::where('event_id', $event->id)->where('user_id', $person->id)->count(), 'renamed, not added');

        $put(['role' => 'other', 'role_label' => '   '])->assertStatus(422);
        // Moving to a listed job drops the typed-in wording.
        $put(['role' => EventOfficial::ROLE_COACH])->assertOk();
        $this->assertSame([EventOfficial::ROLE_COACH, null], [$o->fresh()->role, $o->fresh()->role_label]);

        $this->actingAs($this->createUser())->putJson(route('me.events.officials.update', [$event->uuid, $o->id]),
            ['compensation' => 'volunteer', 'role' => 'other', 'role_label' => 'Boss'])->assertForbidden();
    }

    public function test_a_host_adds_its_own_designations_once_and_every_event_offers_them(): void
    {
        $organiser = $this->createUser(['full_name' => 'Organiser One']);
        $club = $this->clubFor($organiser);
        $event = $this->championship($club, $organiser);
        $add = fn (array $body) => $this->actingAs($organiser)->postJson(route('me.events.officials.designations.store', $event->uuid), $body);

        $announcer = $add(['label' => ' <b>Announcer</b> ', 'section' => 'organising'])->assertCreated()
            ->assertJsonPath('role.label', 'Announcer')->assertJsonPath('role.group', 'mat')->json('role.value');
        $results = $add(['label' => 'Results officer', 'section' => 'table'])->assertCreated()->json('role.value');
        $this->assertStringStartsWith('cd_', $announcer);

        // The same name again, or a standard job's name, answers with that one.
        $add(['label' => 'announcer', 'section' => 'team'])->assertOk()->assertJsonPath('existing', true)->assertJsonPath('role.value', $announcer);
        $add(['label' => 'Coach', 'section' => 'team'])->assertOk()->assertJsonPath('role.value', EventOfficial::ROLE_COACH);
        $add(['label' => 'X', 'section' => 'organising'])->assertStatus(422);
        $add(['label' => 'Bad section', 'section' => 'nowhere'])->assertStatus(422);

        // Offered on another event of the same host, under the section it was given.
        $second = $this->championship($club, $organiser);
        $roles = collect($this->actingAs($organiser)->getJson(route('me.events.officials', $second->uuid))->assertOk()->json('roles'))->keyBy('value');
        $this->assertSame('Results officer', $roles[$results]['label']);
        $this->assertSame('table', $roles[$results]['section']);

        // One person may hold several, and they grant nothing.
        $person = $this->createUser(['full_name' => 'Helping Hand']);
        foreach ([$announcer, $results] as $key) {
            $this->actingAs($organiser)->postJson(route('me.events.officials.store', $second->uuid), ['user_id' => $person->id, 'role' => $key, 'compensation' => 'volunteer'])->assertOk();
        }
        $jobs = EventOfficial::where('event_id', $second->id)->where('user_id', $person->id)->get();
        $this->assertCount(2, $jobs);
        $this->assertEqualsCanonicalizing(['Announcer', 'Results officer'], array_values(array_intersect_key(app(OfficialRoles::class)->labels($second), array_flip($jobs->pluck('role')->all()))));
        $this->assertFalse(app(EventAccess::class)->canManage($second->fresh(), $person->fresh()));

        // Another host does not see them; a stranger cannot add to this list.
        $other = $this->championship($this->clubFor($this->createUser()), $this->createUser());
        $this->assertArrayNotHasKey($announcer, app(OfficialRoles::class)->labels($other));
        $this->actingAs($this->createUser())->postJson(route('me.events.officials.designations.store', $event->uuid), ['label' => 'Spy', 'section' => 'organising'])->assertForbidden();
    }

    public function test_a_clubs_named_officials_read_under_team_and_referees(): void
    {
        $organiser = $this->createUser(['full_name' => 'Organiser One']);
        $event = $this->championship($this->clubFor($organiser), $organiser);
        $roles = app(OfficialRoles::class);

        $this->assertSame('team', $roles->sectionOf($event, 'other', 'Team manager'));
        $this->assertSame('referees', $roles->sectionOf($event, 'other', 'Referee 1'));
        $this->assertSame('referees', $roles->sectionOf($event, 'other', 'الحكم 2'));
        $this->assertSame('team', $roles->sectionOf($event, 'other', 'مدير الفريق'));
        $this->assertSame('organising', $roles->sectionOf($event, 'other', 'Photographer'));
        $this->assertSame('organising', $roles->sectionOf($event, 'other', null));
    }

    public function test_the_competition_report_heads_officials_by_section_and_the_draw_pdf_keeps_its_two(): void
    {
        config(['events.bracket_pdf' => ['enabled' => true, 'binary' => '/bin/true', 'timeout' => 5]]);

        $organiser = $this->createUser(['full_name' => 'Master Kim']);
        $club = $this->clubFor($organiser);
        $event = $this->championship($club, $organiser);
        $this->drawnDivision($event, $club);

        foreach ([['Delegate Dana', 'technical_delegate', null], ['Referee Ahmed', 'center_referee', null],
            ['Coach Omar', 'coach', null], ['Manager Mona', 'other', 'Team manager'], ['Organiser Sara', 'organiser', null]] as [$name, $role, $label]) {
            EventOfficial::create(['event_id' => $event->id, 'user_id' => $this->createUser(['full_name' => $name])->id, 'role' => $role, 'role_label' => $label]);
        }

        $pdf = app(BracketPdf::class);
        $viewer = $organiser->fresh();
        $headings = fn (array $team) => collect($team)->flatten(1)->groupBy('heading')
            ->map(fn ($s) => $s->flatMap(fn ($x) => collect($x['cards'])->pluck('name'))->all())->all();

        $grouped = $headings($pdf->cover($event, $pdf->divisions($event, $viewer), $viewer, officialsBySection: true)['team']);
        $this->assertSame(['Organising Committee', 'Technical Delegate & Jury', 'Referees & Judges', 'Team Officials'], array_keys($grouped));
        $this->assertSame(['Organiser Sara'], $grouped['Organising Committee']);
        $this->assertSame(['Delegate Dana'], $grouped['Technical Delegate & Jury']);
        $this->assertSame(['Referee Ahmed'], $grouped['Referees & Judges']);
        $this->assertEqualsCanonicalizing(['Coach Omar', 'Manager Mona'], $grouped['Team Officials']);

        // The draw PDF keeps its two headings, and only the sport's jobs are match officials.
        $plain = $headings($pdf->cover($event, $pdf->divisions($event, $viewer), $viewer)['team']);
        $this->assertSame([__('events::events.draw_print_management'), __('events::events.draw_print_match_officials')], array_keys($plain));
        $this->assertSame(['Referee Ahmed'], $plain[__('events::events.draw_print_match_officials')]);
    }
}
