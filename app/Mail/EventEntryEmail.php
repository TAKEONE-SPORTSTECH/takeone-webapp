<?php

namespace App\Mail;

use App\Members\Models\User;
use App\Models\ClubEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The one email an entrant actually needs: their entry, and the way back to it.
 *
 * Somebody enters a competition from a link in a WhatsApp message, on a phone,
 * with no account until that moment. Everything the platform then told them
 * lived inside the platform — an in-app notification and a tray they may never
 * open — so if they closed the tab, the ONLY route back to their own entry was
 * the original message. This is the piece that leaves the building.
 *
 * WHITE-LABELLED, like every other surface of a shared event. The subject names
 * the COMPETITION, the sender's display name is the HOST CLUB, and nothing in it
 * says TAKEONE: they were sent a competition and have no idea what is serving
 * it (the same rule as `entry/layout.blade.php`). The from ADDRESS is the
 * platform's configured one and is not spoofed — only the name a mail client
 * shows beside it is the club's.
 *
 * Queued, like the rest of this application's mail.
 */
class EventEntryEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /** Their entry was accepted — they are competing. */
    public const ENTERED = 'entered';

    /** The organiser has it and will confirm; nothing is owed yet. */
    public const RECEIVED = 'received';

    /** The organiser could not accept it. */
    public const DECLINED = 'declined';

    public function __construct(
        public ClubEvent $event,
        public User $athlete,
        public string $state,
        public ?string $division = null,
        public ?string $entryUrl = null,
        public ?string $owed = null,
        public ?string $payHow = null,
    ) {}

    public function envelope(): Envelope
    {
        $club = trim((string) ($this->event->tenant?->club_name ?? ''));

        $subject = match ($this->state) {
            self::ENTERED => __('events.entry_mail_subject_entered', ['title' => $this->event->title]),
            self::DECLINED => __('events.entry_mail_subject_declined', ['title' => $this->event->title]),
            default => __('events.entry_mail_subject_received', ['title' => $this->event->title]),
        };

        return new Envelope(
            from: new Address(
                (string) config('mail.from.address'),
                $club !== '' ? $club : (string) config('mail.from.name'),
            ),
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.event-entry');
    }
}
