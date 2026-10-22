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

    public function test_the_rest_of_the_draw_page_does_not_use_up_the_pdf_allowance(): void
    {
        $this->enable();
        $this->fakeBrowser();

        $organiser = $this->createUser(['full_name' => 'Master Kim']);
        $club = $this->clubFor($organiser);
        $event = $this->championship($club, $organiser);
        $this->drawnDivision($event, $club);

        // The draw page's other requests: un-named `throttle:60,1` doors. With
        // the PDF on `throttle:6,1` they shared one per-user counter and the
        // first click on the PDF answered 429.
        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($organiser->fresh())->getJson("/me/events/{$event->uuid}/draw-pin");
        }

        $this->actingAs($organiser->fresh())
            ->getJson("/me/events/{$event->uuid}/brackets/pdf")
            ->assertOk();
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
        // bracket, and still landscape: every bracket sheet is.
        $this->assertSame(1, substr_count($html, 'class="pdf-page '));
        $this->assertStringContainsString('pdf-page land', $html);
        $this->assertStringNotContainsString('pdf-page port', $html);
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

        // Cover + division list + teams + one sheet; the numbering counts all four.
        $this->assertSame(4, substr_count($html, 'class="pdf-page '));
        $this->assertStringContainsString('class="band cover-band"', $html);
        $this->assertStringContainsString('class="band page-band"', $html);
        $this->assertStringContainsString('1 / 4', $html);
        // The teams page: the club, and every athlete who entered under it.
        $this->assertStringContainsString(e(__('events::events.draw_print_teams_title')), $html);
        $this->assertSame(1, substr_count($html, 'class="club"'));
        $this->assertStringContainsString(e($club->club_name), $html);
        foreach (range(1, 4) as $i) {
            $this->assertStringContainsString('Athlete '.$i, substr($html, strpos($html, 'class="club"')));
        }
        // Page 2's division and each athlete's name link to the division's sheet.
        $id = (int) $divisions[0]['id'];
        $this->assertStringContainsString('id="sheet-'.$id.'"', $html);
        $this->assertStringContainsString('<a class="drow" href="#sheet-'.$id.'"', $html);
        $this->assertSame(4, substr_count($html, '<a class="n" dir="auto" href="#sheet-'.$id.'"'));
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

    /**
     * The draw is the brackets and nothing else (owner, 2026-10-16) — no cover
     * for the whole draw either — and every sheet is titled with its name,
     * ages and weights.
     */
    public function test_the_draw_is_only_brackets_titled_with_name_ages_and_weights(): void
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

        $this->assertSame([false, false], $seen);

        $division->update(['name' => 'Group A', 'min_age' => 5, 'max_age' => 6, 'min_weight' => 17, 'max_weight' => 23.5]);
        $pdf = app(\App\Events\Support\BracketPdf::class);
        $html = $pdf->html($event, $organiser->fresh(), $pdf->divisions($event, $organiser->fresh()), 'linear', 'match');
        $this->assertStringContainsString('Group A · 5–6 yrs · 17–23.5 kg', $html);
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

        // Cover, divisions, the clubs, the officials, one sheet.
        $this->assertSame(5, substr_count($html, 'class="pdf-page '));
        $this->assertSame(2, substr_count($html, 'class="pcard"'));
        $this->assertStringContainsString('Referee Ahmed', $html);
        // The organiser heads the management team.
        $team = substr($html, strpos($html, 'class="pcard"'));
        $this->assertLessThan(strpos($team, 'Referee Ahmed'), strpos($team, 'Organiser Sara'));
        // No photo on file draws the silhouette, never initials.
        $this->assertStringContainsString('ph-none', $html);
    }

    public function test_the_podium_prints_only_once_every_bout_is_decided(): void
    {
        $pdf = app(BracketPdf::class);
        $division = [
            'name' => '8-9 Women',
            'rounds' => [
                ['name' => 'Semifinal', 'matches' => [['status' => 'done'], ['status' => 'done']]],
                ['name' => 'Final', 'matches' => [['status' => 'scheduled']]],
            ],
            'podium' => [
                ['place' => 3, 'name' => 'Third A', 'club' => 'X', 'country' => 'BH', 'photo' => null],
                ['place' => 1, 'name' => 'Gold', 'club' => 'Y', 'country' => 'BH', 'photo' => 'https://elsewhere/p.jpg'],
                ['place' => 2, 'name' => 'Sayed Ali Reda Sharif', 'club' => null, 'country' => null, 'photo' => null],
                ['place' => 3, 'name' => 'Third B', 'club' => 'Z', 'country' => 'BH', 'photo' => null],
            ],
        ];

        // A bout still to fight: no podium on paper at all.
        $this->assertNull($pdf->finishedPodium($division));

        $division['rounds'][1]['matches'][0]['status'] = 'done';
        $podium = $pdf->finishedPodium($division);

        // First and last name only on a podium card.
        $this->assertSame(['Gold', 'Sayed Sharif', 'Third A', 'Third B'], array_column($podium, 'name'));
        // Only an inlined (re-authorised) photo is printed, never a remote URL.
        $this->assertNull($podium[0]['photo']);
        $this->assertStringContainsString('female', $podium[0]['placeholder']);

        // No champion recorded: nothing to print, even with every bout done.
        $division['podium'] = array_values(array_filter($division['podium'], fn ($p) => $p['place'] !== 1));
        $this->assertNull($pdf->finishedPodium($division));
    }

    public function test_a_finished_competition_prints_as_results(): void
    {
        $pdf = app(BracketPdf::class);
        $done = [
            'name' => 'Senior Men',
            'rounds' => [['name' => 'Final', 'matches' => [[
                'status' => 'done', 'a' => ['provisional' => true], 'b' => ['provisional' => false],
            ]]]],
            'podium' => [['place' => 1, 'name' => 'Gold'], ['place' => 2, 'name' => 'Silver']],
        ];
        $open = $done;
        $open['rounds'][0]['matches'][0]['status'] = 'scheduled';

        $event = new \App\Events\Models\ClubEvent(['status' => 'active']);

        $this->assertTrue($pdf->finished($event, [$done]));
        $this->assertFalse($pdf->finished($event, [$done, $open]));

        $event->status = 'completed';
        $this->assertTrue($pdf->finished($event, [$done, $open]));
    }

    public function test_the_draw_can_be_printed_without_bout_numbers(): void
    {
        $this->enable();

        $organiser = $this->createUser(['full_name' => 'Master Kim']);
        $club = $this->clubFor($organiser);
        $event = $this->championship($club, $organiser);
        $this->drawnDivision($event, $club);
        \App\Events\Models\EventMatch::where('event_id', $event->id)->update(['match_no' => 7]);

        $pdf = app(BracketPdf::class);
        $divisions = $pdf->divisions($event, $organiser->fresh());
        $payload = function (string $html): array {
            preg_match('#data:application\\\\?/json;base64,([A-Za-z0-9+/=\\\\]+)#', $html, $m);

            return json_decode(base64_decode(str_replace('\\', '', $m[1] ?? '')), true) ?: [];
        };

        // The default keeps the numbers; the board is told only when to drop them.
        $with = $payload($pdf->html($event, $organiser->fresh(), $divisions, 'linear', 'match'));
        $this->assertFalse($with['hide_numbers']);
        $without = $payload($pdf->html($event, $organiser->fresh(), $divisions, 'linear', 'match', false, false));
        $this->assertTrue($without['hide_numbers']);
        // The bouts themselves are all still there.
        $this->assertSame(count($with['divisions'][0]['rounds'] ?? []), count($without['divisions'][0]['rounds'] ?? []));

        $this->fakeBrowser();
        $res = $this->actingAs($organiser->fresh())
            ->getJson("/me/events/{$event->uuid}/brackets/pdf?numbers=0")
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('-no-numbers.pdf', (string) $res->headers->get('Content-Disposition'));

        $this->actingAs($organiser->fresh())->getJson("/me/events/{$event->uuid}/brackets/pdf?numbers=x")->assertStatus(422);
    }

    public function test_a_finished_day_reads_from_its_first_bout_to_its_last(): void
    {
        $organiser = $this->createUser(['full_name' => 'Master Kim']);
        $club = $this->clubFor($organiser);
        $event = $this->championship($club, $organiser, ['date' => '2026-03-10', 'end_date' => '2026-03-10', 'timezone' => 'Asia/Bahrain']);
        $this->drawnDivision($event, $club);
        $event->forceFill(['status' => 'completed', 'started_at' => '2026-03-10 05:00:00'])->save();

        $bouts = \App\Events\Models\EventMatch::where('event_id', $event->id)->orderBy('id')->get();
        \App\Events\Models\EventMatch::where('event_id', $event->id)->update(['status' => 'done']);
        $log = fn ($id, $cmd, $at) => \Illuminate\Support\Facades\DB::table('event_match_events')->insert([
            'event_id' => $event->id, 'match_id' => $id, 'command' => $cmd, 'occurred_at' => $at,
            'court' => '1', 'sport' => 'taekwondo', 'sequence' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $log($bouts[0]->id, 'start', '2026-03-09 10:00:00');   // a rehearsal the day before — not counted
        $log($bouts[0]->id, 'start', '2026-03-10 06:05:00');   // 09:05 in Bahrain
        $log($bouts[1]->id, 'finish', '2026-03-10 09:00:00');
        $log($bouts[1]->id, 'finish', '2026-03-10 11:40:00');  // 14:40 in Bahrain

        $hours = (new \ReflectionMethod(BracketPdf::class, 'actualHours'))->invoke(app(BracketPdf::class), $event->fresh());

        $clock = fn ($t) => \App\Support\Cldr::time(\Illuminate\Support\Carbon::parse('2026-03-10 '.$t));
        $this->assertSame(['2026-03-10' => $clock('09:05').' – '.$clock('14:40')], $hours);
    }
}
