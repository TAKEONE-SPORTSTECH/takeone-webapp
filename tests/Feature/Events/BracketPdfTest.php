<?php

namespace Tests\Feature\Events;

use App\Events\Support\BracketPdf;
use Tests\Feature\Contracts\ContractTestCase;

/**
 * GET /me/events/{uuid}/brackets/pdf — the draw printed to A4.
 *
 * The browser itself is mocked: what is under test is WHO gets a PDF, WHAT
 * goes into the print page, and that every other answer is the same 404.
 */
class BracketPdfTest extends ContractTestCase
{
    private function enable(): void
    {
        // `/bin/true` stands in for Chromium: available() only asks whether
        // the binary can be run, and render() is mocked below.
        config(['events.bracket_pdf' => ['enabled' => true, 'binary' => '/bin/true', 'timeout' => 5]]);
    }

    private function fakeBrowser(): void
    {
        // Constructed for real (it needs the event-type registry); only the
        // browser step is replaced.
        $mock = \Mockery::mock(BracketPdf::class, [app(\App\Events\EventTypeRegistry::class)])->makePartial();
        $mock->shouldReceive('render')->andReturn('%PDF-1.4 test');
        $this->app->instance(BracketPdf::class, $mock);
    }

    public function test_the_organiser_downloads_the_draw_as_a_pdf(): void
    {
        $this->enable();
        $this->fakeBrowser();

        $organiser = $this->createUser(['full_name' => 'Master Kim']);
        $club = $this->clubFor($organiser);
        $event = $this->championship($club, $organiser);
        $this->drawnDivision($event, $club);

        $res = $this->actingAs($organiser->fresh())
            ->getJson("/me/events/{$event->uuid}/brackets/pdf")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertStringStartsWith('%PDF', $res->getContent());
        $this->assertStringContainsString('attachment;', (string) $res->headers->get('Content-Disposition'));
    }

    public function test_somebody_who_does_not_run_the_draw_gets_a_404(): void
    {
        $this->enable();
        $this->fakeBrowser();

        $organiser = $this->createUser(['full_name' => 'Master Kim']);
        $club = $this->clubFor($organiser);
        $event = $this->championship($club, $organiser);
        $this->drawnDivision($event, $club);

        $stranger = $this->createUser(['full_name' => 'Somebody Else']);

        $this->actingAs($stranger)
            ->getJson("/me/events/{$event->uuid}/brackets/pdf")
            ->assertNotFound();
    }

    public function test_with_the_flag_off_the_route_is_a_404_even_for_the_organiser(): void
    {
        config(['events.bracket_pdf' => ['enabled' => false, 'binary' => '/bin/true', 'timeout' => 5]]);

        $organiser = $this->createUser(['full_name' => 'Master Kim']);
        $club = $this->clubFor($organiser);
        $event = $this->championship($club, $organiser);
        $this->drawnDivision($event, $club);

        $this->actingAs($organiser->fresh())
            ->getJson("/me/events/{$event->uuid}/brackets/pdf")
            ->assertNotFound();
    }

    public function test_layout_and_third_are_whitelisted(): void
    {
        $this->enable();
        $this->fakeBrowser();

        $organiser = $this->createUser(['full_name' => 'Master Kim']);
        $club = $this->clubFor($organiser);
        $event = $this->championship($club, $organiser);
        $this->drawnDivision($event, $club);

        $this->actingAs($organiser->fresh())
            ->getJson("/me/events/{$event->uuid}/brackets/pdf?layout=<script>&third=x")
            ->assertStatus(422);
    }

    public function test_the_print_page_runs_the_real_board_on_inline_data(): void
    {
        $this->enable();

        $organiser = $this->createUser(['full_name' => 'Master Kim']);
        $club = $this->clubFor($organiser);
        $event = $this->championship($club, $organiser);
        $this->drawnDivision($event, $club);

        $pdf = app(BracketPdf::class);
        $divisions = $pdf->divisions($event, $organiser->fresh());

        $this->assertCount(1, $divisions);

        $html = $pdf->html($event, $organiser->fresh(), $divisions, 'linear', 'match');

        // One sheet per division; four entrants is two rounds — a short
        // bracket, so portrait.
        $this->assertSame(1, substr_count($html, 'class="pdf-page '));
        $this->assertStringContainsString('pdf-page port', $html);
        // The same board component, fed a data: URL — nothing in the page
        // talks back to the server, and no write address is handed over.
        $this->assertStringContainsString('BracketBoard.mount', $html);
        $this->assertStringContainsString('data:application\\/json;base64,', $html);
        $this->assertStringNotContainsString('/brackets/arrange', $html);
        $this->assertStringNotContainsString('/brackets/data', $html);
    }

    public function test_the_full_draw_opens_with_the_competition_and_the_divisions(): void
    {
        $this->enable();

        $organiser = $this->createUser(['full_name' => 'Master Kim']);
        $club = $this->clubFor($organiser);
        $event = $this->championship($club, $organiser);
        $this->drawnDivision($event, $club);

        $pdf = app(BracketPdf::class);
        $divisions = $pdf->divisions($event, $organiser->fresh());
        $html = $pdf->html($event, $organiser->fresh(), $divisions, 'linear', 'match', true);

        // Cover + division list + one sheet; the numbering counts all three.
        $this->assertSame(3, substr_count($html, 'class="pdf-page '));
        $this->assertStringContainsString('class="band cover-band"', $html);
        $this->assertStringContainsString('class="band page-band"', $html);
        $this->assertStringContainsString('1 / 3', $html);
        $this->assertStringContainsString(e(__('events::events.draw_print_division_list')), $html);
        // An event with nobody appointed prints no officials section at all.
        $this->assertStringNotContainsString('class="offs"', $html);
        $this->assertStringNotContainsString('class="pcard"', $html);
    }

    public function test_one_division_prints_as_its_sheet_alone(): void
    {
        $this->enable();

        $organiser = $this->createUser(['full_name' => 'Master Kim']);
        $club = $this->clubFor($organiser);
        $event = $this->championship($club, $organiser);
        $this->drawnDivision($event, $club);

        $pdf = app(BracketPdf::class);
        $html = $pdf->html($event, $organiser->fresh(), $pdf->divisions($event, $organiser->fresh()), 'linear', 'match');

        $this->assertStringNotContainsString('class="band cover-band"', $html);
        $this->assertStringNotContainsString('class="band page-band"', $html);
    }

    public function test_the_controller_adds_the_cover_only_to_the_whole_draw(): void
    {
        $this->enable();

        $organiser = $this->createUser(['full_name' => 'Master Kim']);
        $club = $this->clubFor($organiser);
        $event = $this->championship($club, $organiser);
        [$division] = $this->drawnDivision($event, $club);

        $seen = [];
        $mock = \Mockery::mock(BracketPdf::class, [app(\App\Events\EventTypeRegistry::class)])->makePartial();
        $mock->shouldReceive('render')->andReturnUsing(function (string $html) use (&$seen) {
            $seen[] = str_contains($html, 'class="band cover-band"');

            return '%PDF-1.4 test';
        });
        $this->app->instance(BracketPdf::class, $mock);

        $this->actingAs($organiser->fresh())->getJson("/me/events/{$event->uuid}/brackets/pdf")->assertOk();
        $this->actingAs($organiser->fresh())->getJson("/me/events/{$event->uuid}/brackets/pdf?division={$division->id}")->assertOk();

        $this->assertSame([true, false], $seen);
    }

    public function test_appointed_people_get_their_own_page_of_cards(): void
    {
        $this->enable();

        $organiser = $this->createUser(['full_name' => 'Master Kim']);
        $club = $this->clubFor($organiser);
        $event = $this->championship($club, $organiser);
        $this->drawnDivision($event, $club);

        $referee = $this->createUser(['full_name' => 'Referee Ahmed']);
        $boss = $this->createUser(['full_name' => 'Organiser Sara']);
        \App\Events\Models\EventOfficial::create(['event_id' => $event->id, 'user_id' => $referee->id, 'role' => 'jury']);
        \App\Events\Models\EventOfficial::create(['event_id' => $event->id, 'user_id' => $boss->id, 'role' => 'organiser']);

        $pdf = app(BracketPdf::class);
        $html = $pdf->html($event, $organiser->fresh(), $pdf->divisions($event, $organiser->fresh()), 'linear', 'match', true);

        // Cover, divisions, the team, one sheet.
        $this->assertSame(4, substr_count($html, 'class="pdf-page '));
        $this->assertSame(2, substr_count($html, 'class="pcard"'));
        $this->assertStringContainsString('Referee Ahmed', $html);
        // The organiser heads the management team.
        $team = substr($html, strpos($html, 'class="pcard"'));
        $this->assertLessThan(strpos($team, 'Referee Ahmed'), strpos($team, 'Organiser Sara'));
        // No photo on file draws the silhouette, never initials.
        $this->assertStringContainsString('ph-none', $html);
    }
}
