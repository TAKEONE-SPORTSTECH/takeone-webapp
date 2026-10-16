<?php

namespace Tests\Feature\Events;

use App\Events\Models\ClubEvent;
use App\Events\Models\ClubEventRegistration;
use App\Events\Models\EventCertificateSetting;
use App\Events\Models\EventOfficial;
use App\Events\Support\EventCertificates;
use App\Events\Support\EventParticipantBadges;
use Ahl\Certificates\Certificates as Engine;
use Tests\TestCase;

/**
 * The printables added 2026-10-15: officials' thank-you certificates (a third
 * certificate type, off by default) and participants' badges (the officials'
 * badge, drawn for competitors), each with its own design.
 */
class PrintablesTest extends TestCase
{
    private function event(): array
    {
        config(['certificates.enabled' => true]);
        $owner = $this->createUser();
        $club = $this->createClub($owner, ['country' => 'BH', 'currency' => 'BHD']);
        $event = ClubEvent::create([
            'tenant_id' => $club->id, 'created_by' => $owner->id,
            'title' => 'Printables Cup', 'event_type' => 'championship', 'sport' => 'karate',
            'scope' => 'internal', 'status' => 'active', 'is_archived' => false,
            'date' => now()->addWeeks(2)->toDateString(), 'start_time' => '09:00', 'end_time' => '17:00',
        ]);

        return [$owner, $event->fresh()];
    }

    public function test_officials_get_one_thank_you_each_only_when_switched_on(): void
    {
        [$owner, $event] = $this->event();
        $ref = $this->createUser(['full_name' => 'Sara Ref']);
        EventOfficial::forceCreate(['event_id' => $event->id, 'user_id' => $ref->id, 'role' => 'jury', 'compensation' => 'volunteer']);
        EventOfficial::forceCreate(['event_id' => $event->id, 'user_id' => $ref->id, 'role' => 'weigh_in', 'compensation' => 'volunteer']);
        $certs = app(EventCertificates::class);

        // Off by default: an existing event issues nothing new.
        $this->assertFalse($certs->settings($event)['officials']);
        $certs->generate($event, $owner);
        $this->assertSame(0, Engine::model()::query()->forContext($event)->where('type', 'official_thanks')->count());

        $certs->saveSettings($event, ['officials' => true, 'participation' => false, 'podium' => false]);
        $certs->generate($event, $owner);
        $certs->generate($event, $owner);   // idempotent
        $made = Engine::model()::query()->forContext($event)->where('type', 'official_thanks')->get();
        $this->assertCount(1, $made, 'one per person, however many jobs');
        $this->assertStringContainsString(' · ', (string) $made->first()->details['role']);
        $this->assertSame((string) $made->first()->details['role'], $certs->placeholders($made->first(), 'en')['role']);
    }

    public function test_participants_get_one_badge_each(): void
    {
        [$owner, $event] = $this->event();
        $athlete = $this->createUser(['full_name' => 'Ali Athlete']);
        ClubEventRegistration::forceCreate(['event_id' => $event->id, 'user_id' => $athlete->id, 'role' => 'participant', 'status' => 'joined']);
        $badges = app(EventParticipantBadges::class);

        $this->assertSame(1, $badges->printCount($event, 'participant_badge'));
        $this->assertSame(0, $badges->printCount($event, 'official_badge'));
        $this->assertSame('Ali Athlete', $badges->sampleFields($event, 'en')['name']);
        $this->assertSame(['participant_badge'], $badges->types());
    }

    public function test_each_design_room_opens_for_the_organiser(): void
    {
        [$owner, $event] = $this->event();

        $this->actingAs($owner)->get(route('me.certificates.design', ['event_participant_badges', $event->uuid]))->assertOk();
        $this->actingAs($owner)->get(route('me.certificates.design', ['club_event', $event->uuid]).'?type=official_thanks')->assertOk()
            ->assertSee('official_thanks', false);
        $this->actingAs($this->createUser())->get(route('me.certificates.design', ['event_participant_badges', $event->uuid]))->assertStatus(302);
    }

    public function test_a_design_can_have_its_own_page_size(): void
    {
        $badge = \Ahl\Certificates\Support\Formats::get('badge');
        $a5 = $badge->resized(210, 148);
        $this->assertSame([148.0, 210.0], array_map('floatval', $a5->page('portrait')));
        // Starting boxes scale with the page: the photo moves and grows in proportion.
        $photo = $badge->defaults(false, 'portrait')['photo'];
        $photo5 = $a5->defaults(false, 'portrait')['photo'];
        $this->assertEqualsWithDelta($photo['x'] * 148 / 105, $photo5['x'], 0.2);
        $this->assertEqualsWithDelta($photo['h'] * 210 / 148, $photo5['h'], 0.2);
        $this->assertSame('custom', $a5->paper);
        // Two A5 cards fit on an A4 sheet, ID cards many more; a 200 × 150 mm card only one, so one per page.
        $this->assertNotNull($a5->sheet);
        $this->assertNotNull($badge->resized(85.6, 54)->sheet);
        $this->assertNull($badge->resized(200, 150)->sheet);

        $this->assertNull(\Ahl\Certificates\Models\CertificateTemplate::sizeOf(['long' => 900, 'short' => 100]));
        $this->assertSame([210.0, 148.0], \Ahl\Certificates\Models\CertificateTemplate::sizeOf(['long' => 148, 'short' => 210]));
    }

    public function test_the_design_room_saves_and_draws_a_custom_size(): void
    {
        [$owner, $event] = $this->event();
        $p = ['club_event', $event->uuid, 'type' => 'participation'];
        $form = [
            'scope' => 'event', 'title' => ['en' => 'Certificate'], 'body' => ['en' => 'for {event}'],
            'ornament_level' => 'full', 'background' => 'plain', 'orientation' => 'landscape',
            'page_size' => ['long' => 210, 'short' => 148],
        ];

        $res = $this->actingAs($owner)->putJson(route('me.certificates.design.update', $p), $form)->assertOk();
        $t = collect($res->json('templates'))->firstWhere('type', 'participation');
        $this->assertEquals(['long' => 210, 'short' => 148], $t['page_size']);

        $png = $this->actingAs($owner)->get(route('me.certificates.design.preview', $p))->assertOk()->getContent();
        [$w, $h] = getimagesizefromstring($png);
        $this->assertEqualsWithDelta(210 / 148, $w / $h, 0.03, 'the preview is drawn on an A5 page');

        // Back to the format's own page: stored as nothing.
        $form['page_size'] = ['long' => 297, 'short' => 210];
        $res = $this->actingAs($owner)->putJson(route('me.certificates.design.update', $p), $form)->assertOk();
        $this->assertNull(collect($res->json('templates'))->firstWhere('type', 'participation')['page_size']);

        $this->actingAs($owner)->putJson(route('me.certificates.design.update', $p), ['page_size' => ['long' => 5000, 'short' => 10]] + $form)->assertStatus(422);
    }

    public function test_an_officials_badge_is_one_per_person_and_its_qr_opens_the_event(): void
    {
        [$owner, $event] = $this->event();
        $ref = $this->createUser(['full_name' => 'Sara Ref', 'nationality' => 'BH']);
        EventOfficial::forceCreate(['event_id' => $event->id, 'user_id' => $ref->id, 'role' => 'jury', 'compensation' => 'volunteer']);
        EventOfficial::forceCreate(['event_id' => $event->id, 'user_id' => $ref->id, 'role' => 'weigh_in', 'compensation' => 'volunteer']);
        $badges = app(\App\Events\Support\EventBadges::class);

        $this->assertCount(1, $badges->officials($event));
        $f = $badges->fields($badges->officials($event)->first(), 'en');
        $this->assertStringContainsString(' · ', $f['role'], 'both jobs on the one badge');
        $this->assertSame('Bahrain', $f['nationality']);
        $this->assertSame(route('me.events.show', ['event' => $event->uuid]), $f['qr']);
        $this->assertSame(route('me.events.show', ['event' => $event->uuid]), app(EventParticipantBadges::class)->sampleFields($event, 'en')['qr']);
    }

    public function test_each_design_offers_only_its_own_tokens(): void
    {
        [$owner, $event] = $this->event();
        $state = $this->actingAs($owner)->getJson(route('me.certificates.design.state', ['club_event', $event->uuid]))->assertOk()->json('templates');
        $thanks = collect($state)->firstWhere('type', 'official_thanks')['placeholders'];
        $this->assertContains('role', $thanks);
        $this->assertNotContains('division', $thanks);
        $this->assertNotContains('medal', $thanks);
        $this->assertContains('medal', collect($state)->firstWhere('type', 'podium')['placeholders']);
    }

    public function test_the_officials_pdf_is_for_the_organiser_only(): void
    {
        [$owner, $event] = $this->event();
        $ref = $this->createUser(['full_name' => 'Sara Ref', 'nationality' => 'BH']);
        EventOfficial::forceCreate(['event_id' => $event->id, 'user_id' => $ref->id, 'role' => 'jury', 'compensation' => 'volunteer']);
        $pdf = app(\App\Events\Support\OfficialsPdf::class);

        $groups = $pdf->groups($event, $owner, 'by_job');
        $this->assertSame('Sara Ref', $groups[0]['people'][0]['name']);
        $html = $pdf->html($event, $owner, $pdf->groups($event, $owner, 'signin'), 'signin');
        $this->assertStringContainsString('Sara Ref', $html);
        $this->assertStringNotContainsString((string) $ref->email, $html, 'no contact details print');

        $this->actingAs($this->createUser())->getJson(route('me.events.officials.pdf', [$event->uuid, 'kind' => 'all']))->assertNotFound();
        $this->actingAs($owner)->getJson(route('me.events.officials.pdf', [$event->uuid, 'kind' => 'nope']))->assertStatus(422);
    }
}
