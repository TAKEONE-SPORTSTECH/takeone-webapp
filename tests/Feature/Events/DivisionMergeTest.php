<?php

namespace Tests\Feature\Events;

use App\Events\Models\ClubEvent;
use App\Events\Models\ClubEventRegistration;
use App\Events\Models\EventCategory;
use Tests\Feature\Contracts\ContractTestCase;

/**
 * Merging small divisions (App\Events\Support\DivisionMerge) through
 * me.events.divisions.merge{,.suggest,.check}. Safety first: red is refused,
 * amber needs the consent statement, green merges; organiser only; off by flag.
 */
class DivisionMergeTest extends ContractTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['events.division_merge.enabled' => true]);
    }

    private function division(ClubEvent $event, string $name, array $range, array $people, int $order): EventCategory
    {
        $cat = EventCategory::create(['event_id' => $event->id, 'name' => $name, 'sort_order' => $order] + $range);
        foreach ($people as [$kg, $born, $gender]) {
            $u = $this->createUser(['full_name' => $name.' '.$kg, 'gender' => $gender, 'birthdate' => $born]);
            ClubEventRegistration::create(['event_id' => $event->id, 'user_id' => $u->id, 'category_id' => $cat->id,
                'role' => 'participant', 'status' => 'joined', 'weight' => $kg]);
        }

        return $cat;
    }

    private function setUpEvent(): array
    {
        $organiser = $this->createUser();
        $event = $this->championship($this->clubFor($organiser), $organiser, ['date' => now()->addMonth()->toDateString()]);
        $y = fn (int $years) => now()->addMonth()->subYears($years)->subMonths(3)->toDateString();
        $kids = ['gender' => 'male', 'min_age' => 10, 'max_age' => 11];

        $alone = $this->division($event, 'Kids Male -40', $kids, [[39.0, $y(11), 'Male']], 1);
        $near = $this->division($event, 'Kids Male -42', $kids, [[40.0, $y(11), 'Male'], [41.5, $y(11), 'Male'], [42.0, $y(10), 'Male']], 2);
        $far = $this->division($event, 'Kids Male +60', $kids, [[61.0, $y(11), 'Male'], [62.0, $y(11), 'Male'], [64.0, $y(11), 'Male']], 3);
        $girl = $this->division($event, 'Kids Female -40', ['gender' => 'female'] + $kids, [[39.5, $y(11), 'Female']], 4);

        return compact('organiser', 'event', 'alone', 'near', 'far', 'girl');
    }

    public function test_it_suggests_the_safe_neighbour_with_its_reasons(): void
    {
        ['organiser' => $o, 'event' => $e, 'alone' => $alone, 'near' => $near, 'girl' => $girl] = $this->setUpEvent();

        $res = $this->actingAs($o)->getJson(route('me.events.divisions.merge.suggest', $e->uuid))->assertOk();

        $s = collect($res->json('suggestions'))->first(fn ($x) => in_array($alone->id, $x['ids'], true));
        $this->assertNotNull($s, 'the lone -40 kid has a safe merge');
        $this->assertSame($near->id, $s['into']);
        $this->assertSame('green', $s['grade']);
        $this->assertNotEmpty($s['reasons']);

        // The girl is never offered to the boys — she is listed alone, with why.
        $this->assertTrue(collect($res->json('suggestions'))->every(fn ($x) => ! in_array($girl->id, $x['ids'], true)));
        $this->assertContains($girl->id, array_column($res->json('alone'), 'id'));
    }

    public function test_a_green_merge_moves_everyone_and_retires_the_empty_group(): void
    {
        ['organiser' => $o, 'event' => $e, 'alone' => $alone, 'near' => $near] = $this->setUpEvent();

        $this->actingAs($o)->postJson(route('me.events.divisions.merge', $e->uuid), ['ids' => [$alone->id, $near->id]])
            ->assertOk()->assertJsonPath('success', true)->assertJsonPath('into', $near->id);

        $this->assertNull(EventCategory::find($alone->id));
        $this->assertSame(4, ClubEventRegistration::where('category_id', $near->id)->count());
        // The athlete who moved is marked as placed by hand, so a re-sort never undoes it.
        $this->assertSame(1, ClubEventRegistration::where('category_id', $near->id)->where('category_source', ClubEventRegistration::PLACED_MANUAL)->count());
    }

    public function test_red_is_refused_on_the_server_whatever_the_client_says(): void
    {
        ['organiser' => $o, 'event' => $e, 'alone' => $alone, 'far' => $far, 'girl' => $girl] = $this->setUpEvent();

        // 39 kg against 64 kg.
        $this->actingAs($o)->postJson(route('me.events.divisions.merge', $e->uuid), ['ids' => [$alone->id, $far->id], 'consent' => true])
            ->assertStatus(422)->assertJsonPath('success', false);
        // Boy and girl.
        $this->actingAs($o)->postJson(route('me.events.divisions.merge', $e->uuid), ['ids' => [$alone->id, $girl->id], 'consent' => true])
            ->assertStatus(422);

        $this->assertNotNull(EventCategory::find($alone->id));
        $this->assertSame(1, ClubEventRegistration::where('category_id', $alone->id)->count());
    }

    public function test_amber_needs_the_consent_statement(): void
    {
        ['organiser' => $o, 'event' => $e, 'alone' => $alone] = $this->setUpEvent();
        // A 45 kg group: 39 → 45 is 15.4%… use 44 kg: 12.8% — over 12, under 15.
        $amber = $this->division($e, 'Kids Male -44', ['gender' => 'male', 'min_age' => 10, 'max_age' => 11],
            [[43.0, now()->subYears(11)->toDateString(), 'Male'], [44.0, now()->subYears(11)->toDateString(), 'Male']], 5);

        $check = $this->actingAs($o)->postJson(route('me.events.divisions.merge.check', $e->uuid), ['ids' => [$alone->id, $amber->id]])->assertOk();
        $this->assertSame('amber', $check->json('judgement.grade'));

        $this->actingAs($o)->postJson(route('me.events.divisions.merge', $e->uuid), ['ids' => [$alone->id, $amber->id]])
            ->assertStatus(409)->assertJsonPath('needs_consent', true);
        $this->assertNotNull(EventCategory::find($alone->id));

        $this->actingAs($o)->postJson(route('me.events.divisions.merge', $e->uuid), ['ids' => [$alone->id, $amber->id], 'consent' => true])
            ->assertOk();
        $this->assertNull(EventCategory::find($alone->id));
    }

    public function test_only_the_organiser_and_only_while_switched_on(): void
    {
        ['organiser' => $o, 'event' => $e, 'alone' => $alone, 'near' => $near] = $this->setUpEvent();

        $this->actingAs($this->createUser())->getJson(route('me.events.divisions.merge.suggest', $e->uuid))->assertNotFound();
        $this->actingAs($this->createUser())->postJson(route('me.events.divisions.merge', $e->uuid), ['ids' => [$alone->id, $near->id]])->assertNotFound();

        config(['events.division_merge.enabled' => false]);
        $this->actingAs($o)->getJson(route('me.events.divisions.merge.suggest', $e->uuid))->assertNotFound();
        $this->assertNotNull(EventCategory::find($alone->id));
    }

    public function test_the_divisions_page_draws_the_merge_row_only_while_switched_on(): void
    {
        ['organiser' => $o, 'event' => $e] = $this->setUpEvent();

        $this->actingAs($o)->get(route('me.events.divisions', $e->uuid))->assertOk()
            ->assertSee(__('events::events.merge_ui_title'))->assertSee('divisionMerge(', false)
            // The rows carry the suggestions, read from the merge door.
            ->assertSee('x-init="mgLoad()"', false)->assertSee(str_replace('/', '\\/', route('me.events.divisions.merge', $e->uuid)), false);

        config(['events.division_merge.enabled' => false]);
        $this->actingAs($o)->get(route('me.events.divisions', $e->uuid))->assertOk()
            ->assertDontSee('divisionMerge(', false);
    }

    private function fakeBrowser(): void
    {
        // `/bin/true` stands in for Chromium; render() hands back the HTML it was given.
        config(['events.bracket_pdf' => ['enabled' => true, 'binary' => '/bin/true', 'timeout' => 5]]);
        $mock = \Mockery::mock(\App\Events\Support\BracketPdf::class, [app(\App\Events\EventTypeRegistry::class)])->makePartial();
        $mock->shouldReceive('render')->andReturnUsing(fn (string $html) => '%PDF-1.4 '.$html);
        $this->app->instance(\App\Events\Support\BracketPdf::class, $mock);
    }

    public function test_the_handbook_pdf_carries_the_rules_and_this_events_chapter(): void
    {
        $this->fakeBrowser();
        ['organiser' => $o, 'event' => $e, 'girl' => $girl] = $this->setUpEvent();

        $res = $this->actingAs($o)->get(route('me.events.divisions.merge.handbook', $e->uuid))->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('attachment;', (string) $res->headers->get('Content-Disposition'));
        $body = $res->getContent();
        $this->assertStringContainsString('Merging small', $body);
        $this->assertStringContainsString('Ontario Amateur Combative Sport Rules: Judo', $body, 'the sources are quoted');
        $this->assertStringContainsString(e($girl->name), $body, 'this event\'s lone athlete is listed');
        $this->assertStringContainsString('above 15% never', $body, 'the limits in force');
    }

    public function test_the_handbook_pdf_is_for_the_events_staff_and_only_while_switched_on(): void
    {
        $this->fakeBrowser();
        ['organiser' => $o, 'event' => $e] = $this->setUpEvent();

        $this->actingAs($this->createUser())->getJson(route('me.events.divisions.merge.handbook', $e->uuid))->assertNotFound();

        config(['events.division_merge.enabled' => false]);
        $this->actingAs($o)->getJson(route('me.events.divisions.merge.handbook', $e->uuid))->assertNotFound();
    }
}
