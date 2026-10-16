<?php

namespace Tests\Feature\Events;

use App\Events\Support\BracketPdf;
use Tests\Feature\Contracts\ContractTestCase;

/**
 * GET /me/events/{uuid}/registration-form/pdf — the event's blank,
 * bilingual registration form, printed to A4.
 *
 * The browser is mocked: what is under test is WHO gets a PDF, that the form
 * speaks both languages, that the sport (never a branch) decides the entry
 * tick boxes, and that every other answer is the same 404.
 */
class RegistrationFormPdfTest extends ContractTestCase
{
    private string $html = '';

    private function enable(): void
    {
        // `/bin/true` stands in for Chromium: available() only asks whether it can run.
        config(['events.bracket_pdf' => ['enabled' => true, 'binary' => '/bin/true', 'timeout' => 5]]);
        config(['events.registration_form_pdf' => ['enabled' => true]]);

        $mock = \Mockery::mock(BracketPdf::class, [app(\App\Events\EventTypeRegistry::class)])->makePartial();
        $mock->shouldReceive('render')->andReturnUsing(function (string $html) {
            $this->html = $html;

            return '%PDF-1.4 test';
        });
        $this->app->instance(BracketPdf::class, $mock);
    }

    private function event(array $attrs = []): array
    {
        $organiser = $this->createUser(['full_name' => 'Master Kim']);
        $club = $this->clubFor($organiser);
        $event = $this->championship($club, $organiser);
        if ($attrs) {
            $event->forceFill($attrs)->save();
        }

        return [$organiser->fresh(), $event->fresh()];
    }

    public function test_the_organiser_downloads_a_bilingual_form(): void
    {
        $this->enable();
        [$organiser, $event] = $this->event();

        $res = $this->actingAs($organiser)
            ->getJson("/me/events/{$event->uuid}/registration-form/pdf")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertStringStartsWith('%PDF', $res->getContent());
        $this->assertStringContainsString('registration-form.pdf', (string) $res->headers->get('Content-Disposition'));
        $this->assertStringContainsString('Applicant information', $this->html);
        $this->assertStringContainsString('معلومات المشترك', $this->html);
        // Taekwondo names no disciplines: the form asks for the age group and weight category.
        $this->assertStringContainsString('Weight category', $this->html);
        $this->assertStringNotContainsString('Kumite', $this->html);
    }

    public function test_the_sport_supplies_the_tick_boxes(): void
    {
        $this->enable();
        [$organiser, $event] = $this->event(['sport' => 'karate']);

        $this->actingAs($organiser)->getJson("/me/events/{$event->uuid}/registration-form/pdf")->assertOk();

        $this->assertStringContainsString('Kata', $this->html);
        $this->assertStringContainsString('كوميتيه', $this->html);
    }

    public function test_the_event_title_is_escaped(): void
    {
        $this->enable();
        [$organiser, $event] = $this->event(['title' => 'Open <script>alert(1)</script> Cup']);

        $this->actingAs($organiser)->getJson("/me/events/{$event->uuid}/registration-form/pdf")->assertOk();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $this->html);
        $this->assertStringContainsString('&lt;script&gt;', $this->html);
    }

    public function test_an_official_may_print_it_and_a_stranger_gets_a_404(): void
    {
        $this->enable();
        [, $event] = $this->event();

        $scale = $this->createUser(['full_name' => 'Scale Official']);
        \App\Events\Models\EventOfficial::create(['event_id' => $event->id, 'user_id' => $scale->id, 'role' => 'weigh_in']);

        $this->actingAs($scale->fresh())
            ->getJson("/me/events/{$event->uuid}/registration-form/pdf")
            ->assertOk();

        $this->actingAs($this->createUser())
            ->getJson("/me/events/{$event->uuid}/registration-form/pdf")
            ->assertNotFound();
    }

    public function test_with_the_flag_off_the_route_is_a_404_even_for_the_organiser(): void
    {
        $this->enable();
        config(['events.registration_form_pdf' => ['enabled' => false]]);
        [$organiser, $event] = $this->event();

        $this->actingAs($organiser)
            ->getJson("/me/events/{$event->uuid}/registration-form/pdf")
            ->assertNotFound();
    }

    public function test_the_organiser_downloads_one_entrants_form_filled_in(): void
    {
        $this->enable();
        [$organiser, $event] = $this->event(['sport' => 'karate']);
        $athlete = $this->createUser(['full_name' => 'Saqr Ibrahim Saif', 'gender' => 'Male', 'birthdate' => '2018-04-24', 'nationality' => 'BH']);
        $entry = \App\Events\Models\ClubEventRegistration::create(['event_id' => $event->id, 'user_id' => $athlete->id, 'status' => 'registered', 'weight' => 22.4]);

        $res = $this->actingAs($organiser)
            ->getJson("/me/events/{$event->uuid}/entrants/{$entry->id}/registration-form/pdf")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertStringContainsString('saqr-ibrahim-saif-registration-form.pdf', (string) $res->headers->get('Content-Disposition'));
        $this->assertStringContainsString('Saqr', $this->html);
        $this->assertStringContainsString('Ibrahim', $this->html);
        $this->assertStringContainsString('Bahrain', $this->html);
        $this->assertStringContainsString('22.4', $this->html);
    }

    public function test_one_entrants_form_is_the_organisers_alone(): void
    {
        $this->enable();
        [, $event] = $this->event();
        $athlete = $this->createUser(['full_name' => 'Saqr Saif']);
        $entry = \App\Events\Models\ClubEventRegistration::create(['event_id' => $event->id, 'user_id' => $athlete->id, 'status' => 'registered']);

        // An appointed official prints the blank form, not somebody's filled one.
        $scale = $this->createUser(['full_name' => 'Scale Official']);
        \App\Events\Models\EventOfficial::create(['event_id' => $event->id, 'user_id' => $scale->id, 'role' => 'weigh_in']);
        $this->actingAs($scale->fresh())
            ->getJson("/me/events/{$event->uuid}/entrants/{$entry->id}/registration-form/pdf")
            ->assertNotFound();

        // The athlete themself, and a stranger.
        foreach ([$athlete, $this->createUser()] as $who) {
            $this->actingAs($who->fresh())
                ->getJson("/me/events/{$event->uuid}/entrants/{$entry->id}/registration-form/pdf")
                ->assertNotFound();
        }
    }

    public function test_an_entry_of_another_event_is_a_404_even_for_an_organiser(): void
    {
        $this->enable();
        [$organiser, $event] = $this->event();
        [, $other] = $this->event();
        $entry = \App\Events\Models\ClubEventRegistration::create(['event_id' => $other->id, 'user_id' => $this->createUser()->id, 'status' => 'registered']);

        $this->actingAs($organiser)
            ->getJson("/me/events/{$event->uuid}/entrants/{$entry->id}/registration-form/pdf")
            ->assertNotFound();
    }
    public function test_the_organiser_downloads_every_participants_form_filled_in(): void
    {
        $this->enable();
        [$organiser, $event] = $this->event(['sport' => 'karate']);
        $this->actingAs($organiser)
            ->getJson("/me/events/{$event->uuid}/registration-form/pdf?fill=all")
            ->assertStatus(422);

        foreach (['Zaid Zahrani', 'Adel Ahmed'] as $name) {
            $u = $this->createUser(['full_name' => $name, 'nationality' => 'BH']);
            \App\Events\Models\ClubEventRegistration::forceCreate(['event_id' => $event->id, 'user_id' => $u->id, 'role' => 'participant', 'status' => 'registered']);
        }

        $res = $this->actingAs($organiser)
            ->getJson("/me/events/{$event->uuid}/registration-form/pdf?fill=all")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('-registration-forms-filled.pdf', (string) $res->headers->get('Content-Disposition'));
        // One form each, A–Z, inside one document.
        $this->assertSame(1, substr_count($this->html, '<body>'));
        $this->assertLessThan(strpos($this->html, 'Zaid'), strpos($this->html, 'Adel'));

        // An official still prints the blank form, but never everybody's filled one.
        $scale = $this->createUser(['full_name' => 'Scale Official']);
        \App\Events\Models\EventOfficial::create(['event_id' => $event->id, 'user_id' => $scale->id, 'role' => 'weigh_in']);
        $this->actingAs($scale->fresh())->getJson("/me/events/{$event->uuid}/registration-form/pdf")->assertOk();
        $this->actingAs($scale->fresh())->getJson("/me/events/{$event->uuid}/registration-form/pdf?fill=all")->assertNotFound();
    }
}
