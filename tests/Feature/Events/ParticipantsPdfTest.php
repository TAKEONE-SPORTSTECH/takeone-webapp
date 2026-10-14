<?php

namespace Tests\Feature\Events;

use App\Events\Support\BracketPdf;
use App\Events\Support\ParticipantsPdf;
use Tests\Feature\Contracts\ContractTestCase;

/**
 * GET /me/events/{uuid}/participants/pdf — the entry list and the desk
 * check-in sheet, printed to A4.
 *
 * The browser is mocked: what is under test is WHO gets a PDF, WHAT goes
 * into each edition, and that every other answer is the same 404.
 */
class ParticipantsPdfTest extends ContractTestCase
{
    private function enable(): void
    {
        // `/bin/true` stands in for Chromium: available() only asks whether it can run.
        config(['events.bracket_pdf' => ['enabled' => true, 'binary' => '/bin/true', 'timeout' => 5]]);
        config(['events.participants_pdf' => ['enabled' => true]]);
    }

    private function fakeBrowser(?\Closure $seen = null): void
    {
        $mock = \Mockery::mock(BracketPdf::class, [app(\App\Events\EventTypeRegistry::class)])->makePartial();
        $mock->shouldReceive('render')->andReturnUsing(function (string $html) use ($seen) {
            $seen && $seen($html);

            return '%PDF-1.4 test';
        });
        $this->app->instance(BracketPdf::class, $mock);
    }

    private function event(): array
    {
        $organiser = $this->createUser(['full_name' => 'Master Kim']);
        $club = $this->clubFor($organiser);
        $event = $this->championship($club, $organiser);
        $this->drawnDivision($event, $club);

        return [$organiser->fresh(), $event];
    }

    public function test_the_organiser_downloads_both_editions(): void
    {
        $this->enable();
        $this->fakeBrowser();
        [$organiser, $event] = $this->event();

        foreach (['list' => 'entry-list', 'checkin' => 'check-in'] as $kind => $file) {
            $res = $this->actingAs($organiser)
                ->getJson("/me/events/{$event->uuid}/participants/pdf?kind={$kind}")
                ->assertOk()
                ->assertHeader('Content-Type', 'application/pdf');

            $this->assertStringStartsWith('%PDF', $res->getContent());
            $this->assertStringContainsString($file.'.pdf', (string) $res->headers->get('Content-Disposition'));
        }
    }

    public function test_the_weigh_in_desk_may_print_it_and_the_jury_may_not(): void
    {
        $this->enable();
        $this->fakeBrowser();
        [, $event] = $this->event();

        // The same people who may read payment and weight on the page
        // (EventAccess::canOfficiate) — the scale and the payments desk.
        $scale = $this->createUser(['full_name' => 'Scale Official']);
        $jury = $this->createUser(['full_name' => 'Referee Ahmed']);
        \App\Events\Models\EventOfficial::create(['event_id' => $event->id, 'user_id' => $scale->id, 'role' => 'weigh_in']);
        \App\Events\Models\EventOfficial::create(['event_id' => $event->id, 'user_id' => $jury->id, 'role' => 'jury']);

        $this->actingAs($scale->fresh())
            ->getJson("/me/events/{$event->uuid}/participants/pdf?kind=checkin")
            ->assertOk();

        $this->actingAs($jury->fresh())
            ->getJson("/me/events/{$event->uuid}/participants/pdf?kind=checkin")
            ->assertNotFound();
    }

    public function test_an_entrant_or_a_stranger_gets_a_404(): void
    {
        $this->enable();
        $this->fakeBrowser();
        [, $event] = $this->event();

        $athlete = \App\Members\Models\User::where('full_name', 'Athlete 1')->firstOrFail();
        $stranger = $this->createUser(['full_name' => 'Somebody Else']);

        foreach ([$athlete, $stranger] as $who) {
            foreach (['list', 'checkin', 'checkin_club'] as $kind) {
                $this->actingAs($who)
                    ->getJson("/me/events/{$event->uuid}/participants/pdf?kind={$kind}")
                    ->assertNotFound();
            }
        }
    }

    public function test_with_the_flag_off_the_route_is_a_404_even_for_the_organiser(): void
    {
        $this->enable();
        config(['events.participants_pdf' => ['enabled' => false]]);
        [$organiser, $event] = $this->event();

        $this->actingAs($organiser)
            ->getJson("/me/events/{$event->uuid}/participants/pdf?kind=list")
            ->assertNotFound();
    }

    public function test_the_kind_is_whitelisted(): void
    {
        $this->enable();
        $this->fakeBrowser();
        [$organiser, $event] = $this->event();

        $this->actingAs($organiser)
            ->getJson("/me/events/{$event->uuid}/participants/pdf?kind=../../etc")
            ->assertStatus(422);
    }

    public function test_only_the_check_in_sheet_carries_payment_and_weight(): void
    {
        $this->enable();
        [$organiser, $event] = $this->event();
        $pdf = app(ParticipantsPdf::class);

        $list = $pdf->html($event, $organiser, $pdf->divisions($event, $organiser, 'list'), 'list');
        $check = $pdf->html($event, $organiser, $pdf->divisions($event, $organiser, 'checkin'), 'checkin');

        foreach ([$list, $check] as $html) {
            $this->assertStringContainsString('Senior Men -58 kg', $html);
            $this->assertSame(4, substr_count($html, '<bdi>Athlete '));
        }

        // The entry list: faces (or the silhouette), age and weight, no payment.
        $this->assertStringContainsString('class="ph"', $list);
        $this->assertStringContainsString('57<small class="kg">kg</small>', $list);
        $this->assertStringContainsString('<span class="ag">25</span>', $list);
        $this->assertStringNotContainsString('box on', $list);

        // The desk sheet: the weight on file, four paid entries, and the faces.
        $this->assertSame(4, substr_count($check, 'box on'));
        $this->assertStringContainsString('57', $check);
        // Faces on the desk sheet too, so the desk can match the athlete to the row.
        $this->assertStringContainsString('class="ph"', $check);
    }

    public function test_a_long_division_runs_on_to_the_next_page(): void
    {
        $pdf = app(ParticipantsPdf::class);
        $people = array_fill(0, 40, ['name' => 'A']);

        $pages = $pdf->paginate([['id' => 1, 'name' => 'Big', 'gender' => null, 'people' => $people]], 'list');

        $this->assertGreaterThan(1, count($pages));
        $this->assertFalse($pages[0][0]['continued']);
        $this->assertTrue($pages[1][0]['continued']);
        // Numbering carries on from where the last page stopped.
        $this->assertSame(count($pages[0][0]['people']), $pages[1][0]['start']);
        $this->assertSame(40, array_sum(array_map(fn ($p) => count($p[0]['people']), $pages)));
    }

    public function test_the_participants_page_offers_the_download_only_to_the_people_it_serves(): void
    {
        $this->enable();
        config(['events.unified_page' => true]);
        [$organiser, $event] = $this->event();
        $athlete = \App\Members\Models\User::where('full_name', 'Athlete 1')->firstOrFail();

        $page = fn ($who) => $this->actingAs($who)
            ->withHeader('User-Agent', 'Mozilla/5.0 (Linux; Android 14) Mobile')
            ->get("/me/events/{$event->uuid}/participants");

        // Two rows in the band's ⋯ menu, never a card on the page.
        $page($organiser)->assertOk()
            ->assertSee('window.participantsPdf(', false)
            ->assertSee('kind=list', false)
            ->assertSee('kind=checkin', false)
            ->assertSee('kind=all', false)
            ->assertDontSee("ActionSheet.open('participants-pdf')", false);

        $page($athlete)->assertOk()
            ->assertDontSee('window.participantsPdf(', false)
            ->assertDontSee('kind=checkin', false);
    }

    public function test_the_flat_list_has_everybody_once_with_their_gender(): void
    {
        $this->enable();
        $this->fakeBrowser();
        [$organiser, $event] = $this->event();

        // A second division for Athlete 1: on the flat list they are still ONE row.
        $other = \App\Events\Models\EventCategory::create(['event_id' => $event->id, 'name' => 'Open Kata', 'sort_order' => 2]);
        $a1 = \App\Members\Models\User::where('full_name', 'Athlete 1')->firstOrFail();
        \App\Events\Models\ClubEventRegistration::create([
            'event_id' => $event->id, 'user_id' => $a1->id, 'category_id' => $other->id,
            'role' => 'participant', 'paid' => false, 'weight' => 57,
        ]);
        $a1->forceFill(['gender' => 'Female'])->save();

        $pdf = app(ParticipantsPdf::class);
        $all = $pdf->html($event, $organiser, $pdf->divisions($event, $organiser, 'all'), 'all');

        $this->assertSame(4, substr_count($all, '<bdi>Athlete '));
        $this->assertStringNotContainsString('Senior Men -58 kg', $all);
        $this->assertStringContainsString('All participants', $all);
        $this->assertStringContainsString('57<small class="kg">kg</small>', $all);
        $this->assertStringNotContainsString('box on', $all);
        // Gender on every edition: three men and one woman.
        foreach (['all', 'list', 'checkin'] as $kind) {
            $html = $kind === 'all' ? $all : $pdf->html($event, $organiser, $pdf->divisions($event, $organiser, $kind), $kind);
            $this->assertStringContainsString('bi-gender-female', $html, $kind);
            $this->assertStringContainsString('bi-gender-male', $html, $kind);
        }

        $res = $this->actingAs($organiser)
            ->getJson("/me/events/{$event->uuid}/participants/pdf?kind=all")
            ->assertOk();
        $this->assertStringContainsString('-participants.pdf', (string) $res->headers->get('Content-Disposition'));
    }

    public function test_the_check_in_sheet_by_club_lists_each_athlete_once_under_their_club(): void
    {
        $this->enable();
        $this->fakeBrowser();
        [$organiser, $event] = $this->event();

        $res = $this->actingAs($organiser)
            ->getJson("/me/events/{$event->uuid}/participants/pdf?kind=checkin_club")
            ->assertOk();
        $this->assertStringContainsString('-check-in-by-club.pdf', (string) $res->headers->get('Content-Disposition'));

        $pdf = app(ParticipantsPdf::class);
        $groups = $pdf->divisions($event, $organiser, 'checkin_club');
        $people = collect($groups)->flatMap(fn ($g) => $g['people']);

        // One block per club, each athlete once, filed under their own club.
        $this->assertSame(4, $people->count());
        $this->assertSame($people->pluck('key')->unique()->count(), $people->count());
        foreach ($groups as $g) {
            foreach ($g['people'] as $p) {
                $this->assertSame($g['name'], $p['club'] ?? __('events::events.participants_pdf_no_club'));
            }
        }

        // Inside each club: males A–Z, then females A–Z.
        foreach ($groups as $g) {
            $order = collect($g['people'])->sortBy([fn ($a, $b) => ($a['gender'] === 'female') <=> ($b['gender'] === 'female'),
                fn ($a, $b) => strnatcasecmp($a['name'], $b['name'])])->pluck('key')->all();
            $this->assertSame($order, array_column($g['people'], 'key'));
        }

        // The desk's facts are there, and the club column now names the division.
        $html = $pdf->html($event, $organiser, $groups, 'checkin_club');
        $this->assertStringContainsString('Check-in sheet by club', $html);
        $this->assertSame(4, substr_count($html, 'box on'));
        $this->assertStringContainsString('Senior Men -58 kg', $html);
        $this->assertStringContainsString('class="ph"', $html);
    }
}
