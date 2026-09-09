<?php

namespace Tests\Feature\Events;

use App\Clubs\Models\Tenant;
use App\Events\Support\EntryMail;
use App\Events\Support\PublicEntry;
use App\Mail\EventEntryEmail;
use App\Members\Models\User;
use App\Models\ClubEvent;
use App\Models\EventPublicEntry;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The entrant's confirmation email — the one thing that leaves the building.
 *
 * Somebody enters a competition from a link in a WhatsApp message, on a phone,
 * with no account until that moment. Everything the platform then told them
 * lived INSIDE the platform: an in-app notification, in a tray they may never
 * open. Close the tab and the only route back to their own entry was the
 * original message. Added 2026-09-08.
 *
 * Most of what these tests hold is the REFUSALS — who must not be mailed, and
 * what must not be in the mail — because that is where this kind of feature
 * does damage.
 */
class EntryMailTest extends TestCase
{
    private User $organiser;

    private Tenant $club;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->organiser = $this->createUser(['full_name' => 'Organiser One']);
        $this->club = $this->createClub($this->organiser, ['country' => 'BH', 'currency' => 'BHD']);
        $this->organiser->memberClubs()->syncWithoutDetaching([$this->club->id => ['status' => 'active']]);
    }

    private function event(array $attrs = []): ClubEvent
    {
        return ClubEvent::create(array_merge([
            'tenant_id' => $this->club->id,
            'created_by' => $this->organiser->id,
            'title' => 'Victory Open',
            'event_type' => 'championship',
            'sport' => 'taekwondo',
            'scope' => 'nationwide',
            'date' => now()->addWeeks(2)->toDateString(),
            'end_date' => now()->addWeeks(2)->toDateString(),
            'start_time' => '09:00',
            'end_time' => '17:00',
            'status' => 'active',
            'is_archived' => false,
            'entry_mode' => 'public',
        ], $attrs));
    }

    public function test_it_names_the_competition_and_signs_as_the_host_club(): void
    {
        $event = $this->event();
        $athlete = $this->createUser(['full_name' => 'Ali A', 'email' => 'ali@example.com']);

        app(EntryMail::class)->send($event, $athlete, EventEntryEmail::ENTERED, '-58');

        Mail::assertQueued(EventEntryEmail::class, function (EventEntryEmail $mail) {
            $envelope = $mail->envelope();

            // The subject is the EVENT's, and the sender's display name is the
            // CLUB's — they were sent a competition and have no idea what is
            // serving it. Nothing here may say TAKEONE.
            $this->assertStringContainsString('Victory Open', $envelope->subject);
            $this->assertSame($this->club->club_name, $envelope->from->name);
            $this->assertSame((string) config('mail.from.address'), $envelope->from->address);

            $body = $mail->render();

            $this->assertStringContainsString($this->club->club_name, $body);
            $this->assertStringNotContainsString('TAKEONE', $body);

            return true;
        });
    }

    public function test_it_never_mails_somebody_who_gave_no_address(): void
    {
        $event = $this->event();

        $noEmail = $this->createUser(['full_name' => 'Paper Entry']);
        $noEmail->forceFill(['email' => null])->save();

        app(EntryMail::class)->send($event, $noEmail->fresh(), EventEntryEmail::ENTERED);

        Mail::assertNothingQueued();
    }

    /**
     * People entered off a paper list get a synthetic address so the row has a
     * unique key. It is not an inbox, and mailing it is a bounce at best.
     */
    public function test_it_never_mails_a_generated_placeholder_address(): void
    {
        $event = $this->event();

        foreach (['someone-ab12cd@entries.takeone.bh', 'someone.else@imported.takeone.local'] as $address) {
            $athlete = $this->createUser(['email' => $address]);

            app(EntryMail::class)->send($event, $athlete, EventEntryEmail::ENTERED);
        }

        Mail::assertNothingQueued();
    }

    /**
     * The amount belongs in the email; the club's transfer details do not.
     * Email is forwarded, archived and read on borrowed screens, and the bank
     * fields already live on the entry panel behind a sign-in.
     */
    public function test_it_carries_what_is_owed_but_never_the_clubs_bank_details(): void
    {
        $event = $this->event(['participant_fee_amount' => 10, 'participant_fee_currency' => 'BHD']);
        $athlete = $this->createUser(['email' => 'payer@example.com']);

        $registration = \App\Models\ClubEventRegistration::create([
            'event_id' => $event->id, 'user_id' => $athlete->id,
            'role' => 'participant', 'status' => 'joined', 'registered_at' => now(),
        ]);

        app(EntryMail::class)->send($event, $athlete, EventEntryEmail::ENTERED, null, $registration);

        Mail::assertQueued(EventEntryEmail::class, function (EventEntryEmail $mail) {
            $body = $mail->render();

            $this->assertStringContainsString('10', $body, 'the fee is the fact that decides whether they act today');
            $this->assertStringNotContainsStringIgnoringCase('iban', $body);
            $this->assertDoesNotMatchRegularExpression('/\b\d{10,}\b/', $body, 'an account number reached the email');

            return true;
        });
    }

    /** A mail server having a bad afternoon must not cost anybody their place. */
    public function test_a_failing_mailer_never_breaks_the_entry(): void
    {
        $event = $this->event();
        $athlete = $this->createUser(['email' => 'ali@example.com']);

        Mail::shouldReceive('to')->andThrow(new \RuntimeException('smtp down'));

        app(EntryMail::class)->send($event, $athlete, EventEntryEmail::ENTERED);

        // Reaching here at all is the assertion: `rescue()` swallowed it.
        $this->assertTrue(true);
    }

    public function test_accepting_a_public_request_mails_the_entrant(): void
    {
        $event = $this->event();
        $athlete = $this->createUser(['full_name' => 'Requester', 'email' => 'req@example.com']);

        $entry = EventPublicEntry::create([
            'uuid' => (string) Str::uuid(),
            'event_id' => $event->id,
            'user_id' => $athlete->id,
            'state' => 'pending',
        ]);

        $result = app(PublicEntry::class)->accept($entry, $this->organiser->fresh());

        $this->assertTrue($result['ok'], $result['message'] ?? '');

        Mail::assertQueued(EventEntryEmail::class, function (EventEntryEmail $mail) use ($athlete) {
            $this->assertSame(EventEntryEmail::ENTERED, $mail->state);
            $this->assertSame($athlete->id, $mail->athlete->id);

            return true;
        });
    }

    public function test_declining_a_public_request_tells_the_entrant_too(): void
    {
        $event = $this->event();
        $athlete = $this->createUser(['full_name' => 'Requester', 'email' => 'req@example.com']);

        $entry = EventPublicEntry::create([
            'uuid' => (string) Str::uuid(),
            'event_id' => $event->id,
            'user_id' => $athlete->id,
            'state' => 'pending',
        ]);

        app(PublicEntry::class)->decline($entry, $this->organiser->fresh());

        Mail::assertQueued(EventEntryEmail::class, fn (EventEntryEmail $mail) => $mail->state === EventEntryEmail::DECLINED);
    }
}
